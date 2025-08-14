<?php

/**
 * Paystack Webhook Handler
 *
 * Processes webhook events from Paystack with signature verification,
 * duplicate prevention, and comprehensive event handling.
 *
 * @package    ChatShop
 * @subpackage ChatShop/components/payment/gateways/paystack
 * @since      1.0.0
 */

namespace ChatShop;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Paystack Webhook Handler Class
 *
 * @since 1.0.0
 */
class ChatShop_Paystack_Webhook
{
    /**
     * Paystack IP addresses for webhook verification
     *
     * @since 1.0.0
     * @var array
     */
    private $paystack_ips = array(
        '52.31.139.75',
        '52.49.173.169',
        '52.214.14.220'
    );

    /**
     * Secret key for signature verification
     *
     * @since 1.0.0
     * @var string
     */
    private $secret_key;

    /**
     * Supported webhook events
     *
     * @since 1.0.0
     * @var array
     */
    private $supported_events = array(
        'charge.success',
        'charge.dispute.create',
        'charge.dispute.remind',
        'charge.dispute.resolve',
        'customeridentification.failed',
        'customeridentification.success',
        'paymentrequest.pending',
        'paymentrequest.success',
        'refund.failed',
        'refund.pending',
        'refund.processed',
        'transfer.failed',
        'transfer.success',
        'transfer.reversed'
    );

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        $this->init_secret_key();
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     *
     * @since 1.0.0
     */
    private function init_hooks()
    {
        add_action('wp_ajax_nopriv_chatshop_paystack_webhook', array($this, 'handle_webhook'));
        add_action('wp_ajax_chatshop_paystack_webhook', array($this, 'handle_webhook'));
        add_action('init', array($this, 'add_rewrite_rule'));
        add_filter('query_vars', array($this, 'add_query_vars'));
        add_action('template_redirect', array($this, 'handle_webhook_endpoint'));
    }

    /**
     * Add rewrite rule for webhook endpoint
     *
     * @since 1.0.0
     */
    public function add_rewrite_rule()
    {
        add_rewrite_rule(
            '^chatshop/webhook/paystack/?$',
            'index.php?chatshop_webhook=paystack',
            'top'
        );
    }

    /**
     * Add query vars for webhook
     *
     * @since 1.0.0
     * @param array $vars Existing query vars
     * @return array Modified query vars
     */
    public function add_query_vars($vars)
    {
        $vars[] = 'chatshop_webhook';
        return $vars;
    }

    /**
     * Handle webhook endpoint
     *
     * @since 1.0.0
     */
    public function handle_webhook_endpoint()
    {
        $webhook = get_query_var('chatshop_webhook');

        if ($webhook === 'paystack') {
            $this->handle_webhook();
        }
    }

    /**
     * Handle incoming webhook
     *
     * @since 1.0.0
     */
    public function handle_webhook()
    {
        // Only allow POST requests
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->send_response(405, 'Method Not Allowed');
            return;
        }

        try {
            // Get raw payload
            $payload = file_get_contents('php://input');

            if (empty($payload)) {
                chatshop_log('Empty webhook payload received', 'warning');
                $this->send_response(400, 'Empty payload');
                return;
            }

            // Verify webhook authenticity
            if (!$this->verify_webhook($payload)) {
                chatshop_log('Webhook verification failed', 'error');
                $this->send_response(401, 'Unauthorized');
                return;
            }

            // Decode payload
            $event = json_decode($payload, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                chatshop_log('Invalid JSON in webhook payload', 'error');
                $this->send_response(400, 'Invalid JSON');
                return;
            }

            // Process the event
            $result = $this->process_event($event);

            if ($result) {
                $this->send_response(200, 'OK');
            } else {
                $this->send_response(400, 'Processing failed');
            }
        } catch (\Exception $e) {
            chatshop_log('Webhook processing exception: ' . $e->getMessage(), 'error');
            $this->send_response(500, 'Internal Server Error');
        }
    }

    /**
     * Verify webhook authenticity
     *
     * @since 1.0.0
     * @param string $payload Raw webhook payload
     * @return bool Verification result
     */
    private function verify_webhook($payload)
    {
        // Check signature if available
        if (isset($_SERVER['HTTP_X_PAYSTACK_SIGNATURE'])) {
            return $this->verify_signature($payload, $_SERVER['HTTP_X_PAYSTACK_SIGNATURE']);
        }

        // Fallback to IP whitelist verification
        return $this->verify_ip_address();
    }

    /**
     * Verify webhook signature
     *
     * @since 1.0.0
     * @param string $payload Raw payload
     * @param string $signature Provided signature
     * @return bool Verification result
     */
    private function verify_signature($payload, $signature)
    {
        if (empty($this->secret_key)) {
            chatshop_log('No secret key available for signature verification', 'warning');
            return false;
        }

        $computed_signature = hash_hmac('sha512', $payload, $this->secret_key);

        return hash_equals($computed_signature, $signature);
    }

    /**
     * Verify IP address
     *
     * @since 1.0.0
     * @return bool Verification result
     */
    private function verify_ip_address()
    {
        $client_ip = $this->get_client_ip();

        if (empty($client_ip)) {
            return false;
        }

        return in_array($client_ip, $this->paystack_ips, true);
    }

    /**
     * Get client IP address
     *
     * @since 1.0.0
     * @return string Client IP
     */
    private function get_client_ip()
    {
        $ip_headers = array(
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );

        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = sanitize_text_field($_SERVER[$header]);

                // Handle comma-separated IPs
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }

                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return '';
    }

    /**
     * Process webhook event
     *
     * @since 1.0.0
     * @param array $event Event data
     * @return bool Processing result
     */
    private function process_event($event)
    {
        if (!isset($event['event']) || !isset($event['data'])) {
            chatshop_log('Invalid event structure', 'error');
            return false;
        }

        $event_type = sanitize_text_field($event['event']);
        $event_data = $event['data'];

        // Check if event is supported
        if (!in_array($event_type, $this->supported_events, true)) {
            chatshop_log("Unsupported event type: {$event_type}", 'info');
            return true; // Return true to acknowledge receipt
        }

        // Prevent duplicate processing
        if ($this->is_duplicate_event($event)) {
            chatshop_log("Duplicate event detected: {$event_type}", 'info');
            return true;
        }

        // Log event for debugging
        chatshop_log("Processing webhook event: {$event_type}", 'info');

        // Route to appropriate handler
        switch ($event_type) {
            case 'charge.success':
                return $this->handle_charge_success($event_data);

            case 'paymentrequest.success':
                return $this->handle_payment_request_success($event_data);

            case 'paymentrequest.pending':
                return $this->handle_payment_request_pending($event_data);

            case 'charge.dispute.create':
                return $this->handle_dispute_created($event_data);

            case 'refund.processed':
                return $this->handle_refund_processed($event_data);

            default:
                return $this->handle_generic_event($event_type, $event_data);
        }
    }

    /**
     * Handle successful charge
     *
     * @since 1.0.0
     * @param array $data Event data
     * @return bool Processing result
     */
    private function handle_charge_success($data)
    {
        if (!isset($data['reference'])) {
            chatshop_log('Charge success event missing reference', 'error');
            return false;
        }

        $reference = sanitize_text_field($data['reference']);

        // Update payment status
        $payment_data = array(
            'reference' => $reference,
            'status' => 'completed',
            'gateway_response' => $data,
            'amount' => isset($data['amount']) ? absint($data['amount']) : 0,
            'currency' => isset($data['currency']) ? sanitize_text_field($data['currency']) : '',
            'customer_email' => isset($data['customer']['email']) ? sanitize_email($data['customer']['email']) : '',
            'paid_at' => isset($data['paid_at']) ? sanitize_text_field($data['paid_at']) : current_time('mysql'),
            'authorization_code' => isset($data['authorization']['authorization_code']) ?
                sanitize_text_field($data['authorization']['authorization_code']) : ''
        );

        // Store payment record
        $this->store_payment_record($payment_data);

        // Trigger action for other components
        do_action('chatshop_payment_completed', $payment_data, 'paystack');

        // Send notification if WhatsApp is enabled
        $this->send_payment_notification($payment_data);

        return true;
    }

    /**
     * Handle payment request success
     *
     * @since 1.0.0
     * @param array $data Event data
     * @return bool Processing result
     */
    private function handle_payment_request_success($data)
    {
        if (!isset($data['request_code'])) {
            chatshop_log('Payment request success event missing request_code', 'error');
            return false;
        }

        $request_code = sanitize_text_field($data['request_code']);

        $payment_data = array(
            'request_code' => $request_code,
            'status' => 'completed',
            'gateway_response' => $data,
            'amount' => isset($data['amount']) ? absint($data['amount']) : 0,
            'currency' => isset($data['currency']) ? sanitize_text_field($data['currency']) : '',
            'customer_email' => isset($data['customer']['email']) ? sanitize_email($data['customer']['email']) : '',
            'paid_at' => current_time('mysql')
        );

        // Store payment request record
        $this->store_payment_request_record($payment_data);

        // Trigger action for other components
        do_action('chatshop_payment_request_completed', $payment_data, 'paystack');

        return true;
    }

    /**
     * Handle payment request pending
     *
     * @since 1.0.0
     * @param array $data Event data
     * @return bool Processing result
     */
    private function handle_payment_request_pending($data)
    {
        if (!isset($data['request_code'])) {
            return false;
        }

        $request_code = sanitize_text_field($data['request_code']);

        // Update status to pending
        $payment_data = array(
            'request_code' => $request_code,
            'status' => 'pending',
            'gateway_response' => $data
        );

        $this->store_payment_request_record($payment_data);

        do_action('chatshop_payment_request_pending', $payment_data, 'paystack');

        return true;
    }

    /**
     * Handle dispute created
     *
     * @since 1.0.0
     * @param array $data Event data
     * @return bool Processing result
     */
    private function handle_dispute_created($data)
    {
        if (!isset($data['transaction']['reference'])) {
            return false;
        }

        $reference = sanitize_text_field($data['transaction']['reference']);

        $dispute_data = array(
            'reference' => $reference,
            'dispute_id' => isset($data['id']) ? absint($data['id']) : 0,
            'reason' => isset($data['reason']) ? sanitize_text_field($data['reason']) : '',
            'status' => 'created',
            'amount' => isset($data['transaction']['amount']) ? absint($data['transaction']['amount']) : 0,
            'created_at' => current_time('mysql')
        );

        // Store dispute record
        $this->store_dispute_record($dispute_data);

        // Notify admin
        $this->send_dispute_notification($dispute_data);

        do_action('chatshop_dispute_created', $dispute_data, 'paystack');

        return true;
    }

    /**
     * Handle refund processed
     *
     * @since 1.0.0
     * @param array $data Event data
     * @return bool Processing result
     */
    private function handle_refund_processed($data)
    {
        if (!isset($data['transaction']['reference'])) {
            return false;
        }

        $reference = sanitize_text_field($data['transaction']['reference']);

        $refund_data = array(
            'reference' => $reference,
            'refund_id' => isset($data['id']) ? absint($data['id']) : 0,
            'amount' => isset($data['amount']) ? absint($data['amount']) : 0,
            'status' => 'processed',
            'processed_at' => current_time('mysql')
        );

        $this->store_refund_record($refund_data);

        do_action('chatshop_refund_processed', $refund_data, 'paystack');

        return true;
    }

    /**
     * Handle generic events
     *
     * @since 1.0.0
     * @param string $event_type Event type
     * @param array  $data Event data
     * @return bool Processing result
     */
    private function handle_generic_event($event_type, $data)
    {
        // Store event for reference
        $this->store_webhook_event($event_type, $data);

        // Trigger generic action
        do_action('chatshop_webhook_event', $event_type, $data, 'paystack');

        return true;
    }

    /**
     * Check if event is duplicate
     *
     * @since 1.0.0
     * @param array $event Event data
     * @return bool True if duplicate
     */
    private function is_duplicate_event($event)
    {
        if (!isset($event['data']['id']) && !isset($event['data']['reference'])) {
            return false;
        }

        $event_id = isset($event['data']['id']) ? $event['data']['id'] : $event['data']['reference'];
        $event_type = $event['event'];

        $cache_key = 'chatshop_webhook_' . md5($event_type . '_' . $event_id);

        if (get_transient($cache_key)) {
            return true;
        }

        // Cache for 1 hour to prevent duplicates
        set_transient($cache_key, true, 3600);

        return false;
    }

    /**
     * Store payment record
     *
     * @since 1.0.0
     * @param array $payment_data Payment data
     */
    private function store_payment_record($payment_data)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'chatshop_payments';

        $data = array(
            'reference' => $payment_data['reference'],
            'gateway' => 'paystack',
            'status' => $payment_data['status'],
            'amount' => $payment_data['amount'],
            'currency' => $payment_data['currency'],
            'customer_email' => $payment_data['customer_email'],
            'gateway_response' => wp_json_encode($payment_data['gateway_response']),
            'authorization_code' => isset($payment_data['authorization_code']) ? $payment_data['authorization_code'] : '',
            'paid_at' => $payment_data['paid_at'],
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );

        $wpdb->replace($table_name, $data);

        if ($wpdb->last_error) {
            chatshop_log('Database error storing payment record: ' . $wpdb->last_error, 'error');
        }
    }

    /**
     * Store payment request record
     *
     * @since 1.0.0
     * @param array $payment_data Payment request data
     */
    private function store_payment_request_record($payment_data)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'chatshop_payment_requests';

        $data = array(
            'request_code' => $payment_data['request_code'],
            'gateway' => 'paystack',
            'status' => $payment_data['status'],
            'amount' => $payment_data['amount'],
            'currency' => $payment_data['currency'],
            'customer_email' => isset($payment_data['customer_email']) ? $payment_data['customer_email'] : '',
            'gateway_response' => wp_json_encode($payment_data['gateway_response']),
            'paid_at' => isset($payment_data['paid_at']) ? $payment_data['paid_at'] : null,
            'updated_at' => current_time('mysql')
        );

        $wpdb->replace($table_name, $data);
    }

    /**
     * Store dispute record
     *
     * @since 1.0.0
     * @param array $dispute_data Dispute data
     */
    private function store_dispute_record($dispute_data)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'chatshop_disputes';

        $data = array(
            'reference' => $dispute_data['reference'],
            'dispute_id' => $dispute_data['dispute_id'],
            'gateway' => 'paystack',
            'reason' => $dispute_data['reason'],
            'status' => $dispute_data['status'],
            'amount' => $dispute_data['amount'],
            'created_at' => $dispute_data['created_at']
        );

        $wpdb->insert($table_name, $data);
    }

    /**
     * Store refund record
     *
     * @since 1.0.0
     * @param array $refund_data Refund data
     */
    private function store_refund_record($refund_data)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'chatshop_refunds';

        $data = array(
            'reference' => $refund_data['reference'],
            'refund_id' => $refund_data['refund_id'],
            'gateway' => 'paystack',
            'amount' => $refund_data['amount'],
            'status' => $refund_data['status'],
            'processed_at' => $refund_data['processed_at']
        );

        $wpdb->insert($table_name, $data);
    }

    /**
     * Store webhook event for debugging
     *
     * @since 1.0.0
     * @param string $event_type Event type
     * @param array  $data Event data
     */
    private function store_webhook_event($event_type, $data)
    {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        global $wpdb;

        $table_name = $wpdb->prefix . 'chatshop_webhook_logs';

        $log_data = array(
            'gateway' => 'paystack',
            'event_type' => $event_type,
            'payload' => wp_json_encode($data),
            'ip_address' => $this->get_client_ip(),
            'created_at' => current_time('mysql')
        );

        $wpdb->insert($table_name, $log_data);
    }

    /**
     * Send payment notification
     *
     * @since 1.0.0
     * @param array $payment_data Payment data
     */
    private function send_payment_notification($payment_data)
    {
        // Check if WhatsApp component is available and enabled
        if (!chatshop_is_premium_feature_available('whatsapp_automation')) {
            return;
        }

        $whatsapp_manager = chatshop_get_component('whatsapp_manager');

        if (!$whatsapp_manager) {
            return;
        }

        // Prepare notification message
        $message = sprintf(
            __('Payment confirmed! Reference: %s, Amount: %s %s', 'chatshop'),
            $payment_data['reference'],
            number_format($payment_data['amount'] / 100, 2),
            strtoupper($payment_data['currency'])
        );

        // Send WhatsApp notification if phone number is available
        if (!empty($payment_data['customer_phone'])) {
            $whatsapp_manager->send_message($payment_data['customer_phone'], $message);
        }
    }

    /**
     * Send dispute notification to admin
     *
     * @since 1.0.0
     * @param array $dispute_data Dispute data
     */
    private function send_dispute_notification($dispute_data)
    {
        $admin_email = get_option('admin_email');

        $subject = __('Payment Dispute Created - ChatShop', 'chatshop');
        $message = sprintf(
            __('A payment dispute has been created for transaction %s. Reason: %s. Amount: %s. Please review in your Paystack dashboard.', 'chatshop'),
            $dispute_data['reference'],
            $dispute_data['reason'],
            number_format($dispute_data['amount'] / 100, 2)
        );

        wp_mail($admin_email, $subject, $message);
    }

    /**
     * Initialize secret key
     *
     * @since 1.0.0
     */
    private function init_secret_key()
    {
        $options = chatshop_get_option('paystack', '', array());
        $test_mode = isset($options['test_mode']) ? $options['test_mode'] : true;

        $key = $test_mode ? 'test_secret_key' : 'live_secret_key';

        if (!empty($options[$key])) {
            $this->secret_key = $this->decrypt_api_key($options[$key]);
        }
    }

    /**
     * Decrypt API key
     *
     * @since 1.0.0
     * @param string $encrypted_key Encrypted API key
     * @return string Decrypted key
     */
    private function decrypt_api_key($encrypted_key)
    {
        if (empty($encrypted_key)) {
            return '';
        }

        $encryption_key = wp_salt('auth');
        $decrypted = openssl_decrypt($encrypted_key, 'AES-256-CBC', $encryption_key, 0, substr($encryption_key, 0, 16));

        return $decrypted !== false ? $decrypted : '';
    }

    /**
     * Send HTTP response
     *
     * @since 1.0.0
     * @param int    $status_code HTTP status code
     * @param string $message Response message
     */
    private function send_response($status_code, $message)
    {
        http_response_code($status_code);

        header('Content-Type: application/json');

        echo wp_json_encode(array(
            'status' => $status_code,
            'message' => $message
        ));

        exit;
    }

    /**
     * Get webhook URL
     *
     * @since 1.0.0
     * @return string Webhook URL
     */
    public static function get_webhook_url()
    {
        return home_url('/chatshop/webhook/paystack/');
    }

    /**
     * Get supported events
     *
     * @since 1.0.0
     * @return array Supported events
     */
    public function get_supported_events()
    {
        return $this->supported_events;
    }
}
