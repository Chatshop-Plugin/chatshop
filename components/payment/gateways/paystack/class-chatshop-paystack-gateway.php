<?php

/**
 * Paystack Payment Gateway for ChatShop
 *
 * @package ChatShop
 * @subpackage Payment\Gateways\Paystack
 * @since 1.0.0
 */

namespace ChatShop;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ChatShop Paystack Gateway Class
 *
 * Handles Paystack payment processing including cards, bank transfers,
 * multi-currency support, payment links, and abandoned payment recovery.
 *
 * @since 1.0.0
 */
class ChatShop_Paystack_Gateway extends ChatShop_Payment_Gateway
{
    /**
     * Gateway ID
     *
     * @var string
     * @since 1.0.0
     */
    public $id = 'paystack';

    /**
     * Gateway title
     *
     * @var string
     * @since 1.0.0
     */
    public $title = 'Paystack';

    /**
     * Gateway description
     *
     * @var string
     * @since 1.0.0
     */
    public $description = 'Pay securely with cards, bank transfers, or mobile money via Paystack';

    /**
     * Supported currencies
     *
     * @var array
     * @since 1.0.0
     */
    public $supported_currencies = ['NGN', 'USD', 'GHS', 'ZAR', 'KES', 'XOF'];

    /**
     * Paystack API client
     *
     * @var ChatShop_Paystack_API
     * @since 1.0.0
     */
    private $api_client;

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        // Call parent constructor if it exists
        if (method_exists('ChatShop\ChatShop_Payment_Gateway', '__construct')) {
            parent::__construct();
        }

        $this->init_api_client();
        $this->init_hooks();
    }

    /**
     * Initialize API client
     *
     * @since 1.0.0
     */
    private function init_api_client()
    {
        if (!class_exists('ChatShop\ChatShop_Paystack_API')) {
            require_once CHATSHOP_PLUGIN_DIR . 'components/payment/gateways/paystack/class-chatshop-paystack-api.php';
        }

        $this->api_client = new ChatShop_Paystack_API($this->get_api_key());
    }

    /**
     * Initialize WordPress hooks
     *
     * @since 1.0.0
     */
    private function init_hooks()
    {
        add_action('wp_ajax_chatshop_paystack_verify', array($this, 'ajax_verify_payment'));
        add_action('wp_ajax_nopriv_chatshop_paystack_verify', array($this, 'ajax_verify_payment'));

        // Abandoned payment recovery
        add_action('chatshop_daily_cleanup', array($this, 'process_abandoned_payments'));
    }

    /**
     * Process payment
     *
     * @param float  $amount Payment amount
     * @param string $currency Currency code
     * @param array  $customer_data Customer information
     * @param array  $options Additional options
     * @return array Payment response
     * @since 1.0.0
     */
    public function process_payment($amount, $currency, $customer_data, $options = array())
    {
        try {
            // Validate inputs
            $validation = $this->validate_payment_data($amount, $currency, $customer_data);
            if (!$validation['valid']) {
                return $this->error_response($validation['message']);
            }

            // Generate unique reference
            $reference = $this->generate_reference();

            // Prepare payment data
            $payment_data = array(
                'email' => sanitize_email($customer_data['email']),
                'amount' => $this->format_amount($amount, $currency),
                'currency' => sanitize_text_field($currency),
                'reference' => $reference,
                'callback_url' => $this->get_callback_url(),
                'metadata' => array(
                    'customer_id' => isset($customer_data['id']) ? (int) $customer_data['id'] : null,
                    'source' => 'chatshop_whatsapp',
                    'payment_method' => $this->id,
                ),
            );

            // Add optional data
            if (!empty($customer_data['phone'])) {
                $payment_data['metadata']['phone'] = sanitize_text_field($customer_data['phone']);
            }

            // Check if creating payment link
            if (!empty($options['create_payment_link']) && $options['create_payment_link']) {
                return $this->create_payment_request($payment_data, $options);
            }

            // Initialize transaction
            $response = $this->api_client->initialize_transaction($payment_data);

            if (!$response['success']) {
                chatshop_log('Paystack transaction initialization failed: ' . $response['message'], 'error');
                return $this->error_response($response['message']);
            }

            // Store transaction data
            $this->store_transaction_data($reference, array(
                'gateway' => $this->id,
                'amount' => $amount,
                'currency' => $currency,
                'customer_data' => $customer_data,
                'paystack_data' => $response['data'],
                'status' => 'pending',
                'created_at' => current_time('mysql'),
            ));

            return array(
                'success' => true,
                'data' => array(
                    'reference' => $reference,
                    'authorization_url' => $response['data']['authorization_url'],
                    'access_code' => $response['data']['access_code'],
                    'payment_url' => $response['data']['authorization_url'],
                ),
                'message' => __('Payment initialized successfully', 'chatshop'),
            );
        } catch (\Exception $e) {
            chatshop_log('Paystack payment processing error: ' . $e->getMessage(), 'error');
            return $this->error_response(__('Payment processing failed. Please try again.', 'chatshop'));
        }
    }

    /**
     * Create payment request (for WhatsApp sharing)
     *
     * @param array $payment_data Payment data
     * @param array $options Additional options
     * @return array Payment request response
     * @since 1.0.0
     */
    private function create_payment_request($payment_data, $options = array())
    {
        $request_data = array(
            'description' => isset($options['description']) ? sanitize_text_field($options['description']) : __('Payment via ChatShop', 'chatshop'),
            'amount' => $payment_data['amount'],
            'currency' => $payment_data['currency'],
            'customer' => $payment_data['email'],
            'metadata' => $payment_data['metadata'],
        );

        // Add line items if provided
        if (!empty($options['line_items'])) {
            $request_data['line_items'] = $this->sanitize_line_items($options['line_items']);
        }

        // Add due date
        if (!empty($options['due_date'])) {
            $request_data['due_date'] = sanitize_text_field($options['due_date']);
        }

        $response = $this->api_client->create_payment_request($request_data);

        if (!$response['success']) {
            return $this->error_response($response['message']);
        }

        // Store payment request data
        $this->store_transaction_data($payment_data['reference'], array(
            'gateway' => $this->id,
            'type' => 'payment_request',
            'amount' => $payment_data['amount'],
            'currency' => $payment_data['currency'],
            'paystack_data' => $response['data'],
            'status' => 'pending',
            'created_at' => current_time('mysql'),
        ));

        return array(
            'success' => true,
            'data' => array(
                'reference' => $payment_data['reference'],
                'payment_url' => $response['data']['url'],
                'request_code' => $response['data']['request_code'],
                'shareable_url' => $response['data']['url'],
            ),
            'message' => __('Payment link created successfully', 'chatshop'),
        );
    }

    /**
     * Verify transaction
     *
     * @param string $reference Transaction reference
     * @return array Verification response
     * @since 1.0.0
     */
    public function verify_transaction($reference)
    {
        try {
            $reference = sanitize_text_field($reference);

            if (empty($reference)) {
                return $this->error_response(__('Invalid transaction reference', 'chatshop'));
            }

            // Get stored transaction data
            $transaction_data = $this->get_transaction_data($reference);
            if (!$transaction_data) {
                return $this->error_response(__('Transaction not found', 'chatshop'));
            }

            // Verify with Paystack
            $response = $this->api_client->verify_transaction($reference);

            if (!$response['success']) {
                return $this->error_response($response['message']);
            }

            $paystack_data = $response['data'];
            $status = $paystack_data['status'];

            // Validate amount
            $expected_amount = $this->format_amount($transaction_data['amount'], $transaction_data['currency']);
            if ((int) $paystack_data['amount'] !== (int) $expected_amount) {
                chatshop_log("Amount mismatch for reference {$reference}. Expected: {$expected_amount}, Got: {$paystack_data['amount']}", 'error');
                return $this->error_response(__('Payment amount verification failed', 'chatshop'));
            }

            // Update transaction status
            $this->update_transaction_status($reference, $status, $paystack_data);

            // Handle successful payment
            if ($status === 'success') {
                $this->handle_successful_payment($reference, $paystack_data);
            }

            return array(
                'success' => true,
                'data' => array(
                    'reference' => $reference,
                    'status' => $status,
                    'amount' => $paystack_data['amount'],
                    'currency' => $paystack_data['currency'],
                    'paid_at' => $paystack_data['paid_at'],
                    'channel' => $paystack_data['channel'],
                ),
                'message' => $this->get_status_message($status),
            );
        } catch (\Exception $e) {
            chatshop_log('Paystack verification error: ' . $e->getMessage(), 'error');
            return $this->error_response(__('Payment verification failed', 'chatshop'));
        }
    }

    /**
     * Handle webhook
     *
     * @param array $payload Webhook payload
     * @return array Webhook response
     * @since 1.0.0
     */
    public function handle_webhook($payload)
    {
        try {
            // Validate webhook signature
            if (!$this->api_client->validate_webhook($payload)) {
                chatshop_log('Invalid Paystack webhook signature', 'error');
                return $this->error_response(__('Invalid webhook signature', 'chatshop'));
            }

            $event = sanitize_text_field($payload['event']);
            $data = $payload['data'];

            switch ($event) {
                case 'charge.success':
                    return $this->handle_charge_success($data);

                case 'paymentrequest.success':
                    return $this->handle_payment_request_success($data);

                case 'paymentrequest.pending':
                    return $this->handle_payment_request_pending($data);

                default:
                    chatshop_log("Unhandled Paystack webhook event: {$event}", 'info');
                    return array('success' => true, 'message' => 'Event acknowledged');
            }
        } catch (\Exception $e) {
            chatshop_log('Paystack webhook error: ' . $e->getMessage(), 'error');
            return $this->error_response(__('Webhook processing failed', 'chatshop'));
        }
    }

    /**
     * Handle successful charge webhook
     *
     * @param array $data Charge data
     * @return array Response
     * @since 1.0.0
     */
    private function handle_charge_success($data)
    {
        $reference = sanitize_text_field($data['reference']);

        // Update transaction status
        $this->update_transaction_status($reference, 'success', $data);

        // Handle successful payment
        $this->handle_successful_payment($reference, $data);

        return array('success' => true, 'message' => 'Charge success processed');
    }

    /**
     * Handle successful payment
     *
     * @param string $reference Transaction reference
     * @param array  $paystack_data Paystack response data
     * @since 1.0.0
     */
    private function handle_successful_payment($reference, $paystack_data)
    {
        // Trigger payment success actions
        do_action('chatshop_payment_success', array(
            'gateway' => $this->id,
            'reference' => $reference,
            'amount' => $paystack_data['amount'],
            'currency' => $paystack_data['currency'],
            'customer_email' => $paystack_data['customer']['email'],
            'paystack_data' => $paystack_data,
        ));

        // Send WhatsApp notification if enabled
        $this->send_payment_notification($reference, 'success');
    }

    /**
     * Process abandoned payments (Premium feature)
     *
     * @since 1.0.0
     */
    public function process_abandoned_payments()
    {
        if (!$this->is_premium_feature_enabled('abandoned_payment_recovery')) {
            return;
        }

        $abandoned_payments = $this->get_abandoned_payments();

        foreach ($abandoned_payments as $payment) {
            $this->send_abandoned_payment_reminder($payment);
        }
    }

    /**
     * AJAX verify payment handler
     *
     * @since 1.0.0
     */
    public function ajax_verify_payment()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'chatshop_verify_payment')) {
            wp_die(__('Security check failed', 'chatshop'));
        }

        $reference = sanitize_text_field($_POST['reference']);
        $response = $this->verify_transaction($reference);

        wp_send_json($response);
    }

    /**
     * Get API key based on environment
     *
     * @return string API key
     * @since 1.0.0
     */
    protected function get_api_key()
    {
        $test_mode = chatshop_get_option('paystack', 'test_mode', true);
        $key_type = $test_mode ? 'test_secret_key' : 'live_secret_key';

        $encrypted_key = chatshop_get_option('paystack', $key_type, '');
        return $this->decrypt_api_key($encrypted_key);
    }

    /**
     * Decrypt API key
     *
     * @param string $encrypted_key Encrypted API key
     * @return string Decrypted API key
     * @since 1.0.0
     */
    protected function decrypt_api_key($encrypted_key)
    {
        if (empty($encrypted_key)) {
            return '';
        }

        // Use WordPress salt for encryption key
        $key = wp_salt('auth');
        return openssl_decrypt($encrypted_key, 'AES-256-CBC', $key, 0, substr($key, 0, 16));
    }

    /**
     * Validate payment data
     *
     * @param float  $amount Payment amount
     * @param string $currency Currency code
     * @param array  $customer_data Customer data
     * @return array Validation result
     * @since 1.0.0
     */
    protected function validate_payment_data($amount, $currency, $customer_data)
    {
        // Validate amount
        if (!is_numeric($amount) || $amount <= 0) {
            return array('valid' => false, 'message' => __('Invalid payment amount', 'chatshop'));
        }

        // Validate currency
        if (!in_array($currency, $this->supported_currencies, true)) {
            return array('valid' => false, 'message' => __('Unsupported currency', 'chatshop'));
        }

        // Validate minimum amount
        if (!$this->meets_minimum_amount($amount, $currency)) {
            return array('valid' => false, 'message' => __('Amount below minimum threshold', 'chatshop'));
        }

        // Validate email
        if (empty($customer_data['email']) || !is_email($customer_data['email'])) {
            return array('valid' => false, 'message' => __('Valid email address required', 'chatshop'));
        }

        return array('valid' => true);
    }

    /**
     * Check if amount meets minimum requirement
     *
     * @param float  $amount Amount to check
     * @param string $currency Currency code
     * @return bool Whether amount meets minimum
     * @since 1.0.0
     */
    protected function meets_minimum_amount($amount, $currency)
    {
        $minimums = array(
            'NGN' => 50,
            'USD' => 2,
            'GHS' => 0.10,
            'ZAR' => 1,
            'KES' => 3,
            'XOF' => 1,
        );

        return isset($minimums[$currency]) && $amount >= $minimums[$currency];
    }

    /**
     * Format amount for Paystack (in subunits)
     *
     * @param float  $amount Amount to format
     * @param string $currency Currency code
     * @return int Formatted amount
     * @since 1.0.0
     */
    protected function format_amount($amount, $currency)
    {
        // Convert to subunits (multiply by 100)
        return (int) ($amount * 100);
    }

    /**
     * Generate unique transaction reference
     *
     * @return string Transaction reference
     * @since 1.0.0
     */
    public function generate_reference()
    {
        return 'CS_' . time() . '_' . wp_generate_password(8, false);
    }

    /**
     * Get callback URL
     *
     * @return string Callback URL
     * @since 1.0.0
     */
    private function get_callback_url()
    {
        return add_query_arg(array(
            'action' => 'chatshop_payment_callback',
            'gateway' => $this->id,
        ), admin_url('admin-ajax.php'));
    }

    /**
     * Get status message for display
     *
     * @param string $status Payment status
     * @return string Status message
     * @since 1.0.0
     */
    protected function get_status_message($status)
    {
        $messages = array(
            'success' => __('Payment completed successfully', 'chatshop'),
            'pending' => __('Payment is being processed', 'chatshop'),
            'failed' => __('Payment failed', 'chatshop'),
            'abandoned' => __('Payment was abandoned', 'chatshop'),
        );

        return isset($messages[$status]) ? $messages[$status] : __('Unknown payment status', 'chatshop');
    }

    /**
     * Create error response
     *
     * @param string $message Error message
     * @param array  $data Additional error data
     * @return array Error response
     * @since 1.0.0
     */
    protected function error_response($message, $data = [])
    {
        return array(
            'success' => false,
            'message' => $message,
            'data' => $data,
        );
    }
}
