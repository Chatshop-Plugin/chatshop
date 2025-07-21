<?php

/**
 * Payment Manager
 *
 * Central orchestrator for all payment operations. Manages gateway registration,
 * payment processing, verification, and webhook handling with proper error
 * handling and security measures.
 *
 * @package    ChatShop
 * @subpackage ChatShop/components/payment
 * @since      1.0.0
 */

namespace ChatShop;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Payment Manager Class
 *
 * @since 1.0.0
 */
class ChatShop_Payment_Manager
{
    /**
     * Registered payment gateways
     *
     * @since 1.0.0
     * @var array
     */
    private $gateways = array();

    /**
     * Default gateway ID
     *
     * @since 1.0.0
     * @var string
     */
    private $default_gateway = 'paystack';

    /**
     * Payment logs enabled
     *
     * @since 1.0.0
     * @var bool
     */
    private $logging_enabled = true;

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        $this->init_hooks();
        $this->init_settings();
    }

    /**
     * Initialize hooks
     *
     * @since 1.0.0
     */
    private function init_hooks()
    {
        add_action('init', array($this, 'init'));
        add_action('wp_ajax_chatshop_process_payment', array($this, 'ajax_process_payment'));
        add_action('wp_ajax_nopriv_chatshop_process_payment', array($this, 'ajax_process_payment'));
        add_action('wp_ajax_chatshop_verify_payment', array($this, 'ajax_verify_payment'));
        add_action('wp_ajax_nopriv_chatshop_verify_payment', array($this, 'ajax_verify_payment'));
        add_action('wp_ajax_chatshop_generate_payment_link', array($this, 'ajax_generate_payment_link'));
        add_action('wp_ajax_nopriv_chatshop_generate_payment_link', array($this, 'ajax_generate_payment_link'));
    }

    /**
     * Initialize settings
     *
     * @since 1.0.0
     */
    private function init_settings()
    {
        $general_options = chatshop_get_option('general', '', array());
        $this->default_gateway = isset($general_options['default_gateway']) ?
            $general_options['default_gateway'] : 'paystack';
        $this->logging_enabled = isset($general_options['payment_logging']) ?
            $general_options['payment_logging'] : true;
    }

    /**
     * Initialize payment manager
     *
     * @since 1.0.0
     */
    public function init()
    {
        do_action('chatshop_payment_manager_init', $this);
    }

    /**
     * Register a payment gateway
     *
     * @since 1.0.0
     * @param ChatShop_Abstract_Payment_Gateway $gateway Gateway instance
     * @return bool Registration result
     */
    public function register_gateway($gateway)
    {
        if (!$gateway instanceof ChatShop_Abstract_Payment_Gateway) {
            chatshop_log('Invalid gateway instance provided for registration', 'error');
            return false;
        }

        $gateway_id = $gateway->get_id();

        if (empty($gateway_id)) {
            chatshop_log('Gateway registration failed: Gateway ID is empty', 'error');
            return false;
        }

        if (isset($this->gateways[$gateway_id])) {
            chatshop_log("Gateway {$gateway_id} is already registered", 'warning');
            return false;
        }

        $this->gateways[$gateway_id] = $gateway;

        chatshop_log("Gateway {$gateway_id} registered successfully", 'info');

        do_action('chatshop_gateway_registered', $gateway_id, $gateway);

        return true;
    }

    /**
     * Unregister a payment gateway
     *
     * @since 1.0.0
     * @param string $gateway_id Gateway ID
     * @return bool Unregistration result
     */
    public function unregister_gateway($gateway_id)
    {
        if (!isset($this->gateways[$gateway_id])) {
            return false;
        }

        unset($this->gateways[$gateway_id]);

        chatshop_log("Gateway {$gateway_id} unregistered", 'info');

        do_action('chatshop_gateway_unregistered', $gateway_id);

        return true;
    }

    /**
     * Get registered gateway
     *
     * @since 1.0.0
     * @param string $gateway_id Gateway ID
     * @return ChatShop_Abstract_Payment_Gateway|null Gateway instance or null
     */
    public function get_gateway($gateway_id)
    {
        return isset($this->gateways[$gateway_id]) ? $this->gateways[$gateway_id] : null;
    }

    /**
     * Get all registered gateways
     *
     * @since 1.0.0
     * @return array Registered gateways
     */
    public function get_gateways()
    {
        return $this->gateways;
    }

    /**
     * Get enabled gateways
     *
     * @since 1.0.0
     * @return array Enabled gateways
     */
    public function get_enabled_gateways()
    {
        $enabled = array();

        foreach ($this->gateways as $id => $gateway) {
            if ($gateway->is_enabled()) {
                $enabled[$id] = $gateway;
            }
        }

        return $enabled;
    }

    /**
     * Process payment
     *
     * @since 1.0.0
     * @param string $gateway_id Gateway ID
     * @param float  $amount Payment amount
     * @param string $currency Currency code
     * @param array  $customer_data Customer information
     * @param array  $options Additional options
     * @return array Payment result
     */
    public function process_payment($gateway_id, $amount, $currency, $customer_data, $options = array())
    {
        // Validate input
        $validation = $this->validate_payment_input($gateway_id, $amount, $currency, $customer_data);
        if (is_wp_error($validation)) {
            return $this->error_response($validation->get_error_message());
        }

        $gateway = $this->get_gateway($gateway_id);
        if (!$gateway) {
            return $this->error_response(__('Payment gateway not found', 'chatshop'));
        }

        if (!$gateway->is_enabled()) {
            return $this->error_response(__('Payment gateway is not enabled', 'chatshop'));
        }

        // Log payment attempt
        $this->log_payment_attempt($gateway_id, $amount, $currency, $customer_data);

        try {
            $result = $gateway->process_payment($amount, $currency, $customer_data, $options);

            // Log result
            $this->log_payment_result($gateway_id, $result);

            return $result;
        } catch (\Exception $e) {
            $error_msg = 'Payment processing failed: ' . $e->getMessage();
            chatshop_log($error_msg, 'error');

            return $this->error_response(__('Payment processing failed', 'chatshop'));
        }
    }

    /**
     * Verify payment
     *
     * @since 1.0.0
     * @param string $gateway_id Gateway ID
     * @param string $reference Payment reference
     * @return array Verification result
     */
    public function verify_payment($gateway_id, $reference)
    {
        if (empty($gateway_id) || empty($reference)) {
            return $this->error_response(__('Gateway ID and reference are required', 'chatshop'));
        }

        $gateway = $this->get_gateway($gateway_id);
        if (!$gateway) {
            return $this->error_response(__('Payment gateway not found', 'chatshop'));
        }

        try {
            $result = $gateway->verify_transaction($reference);

            // Log verification
            $this->log_payment_verification($gateway_id, $reference, $result);

            return $result;
        } catch (\Exception $e) {
            $error_msg = 'Payment verification failed: ' . $e->getMessage();
            chatshop_log($error_msg, 'error');

            return $this->error_response(__('Payment verification failed', 'chatshop'));
        }
    }

    /**
     * Generate payment link
     *
     * @since 1.0.0
     * @param string $gateway_id Gateway ID
     * @param float  $amount Payment amount
     * @param string $currency Currency code
     * @param array  $customer_data Customer information
     * @param array  $options Additional options
     * @return array Payment link result
     */
    public function generate_payment_link($gateway_id, $amount, $currency, $customer_data, $options = array())
    {
        // Use default gateway if none specified
        if (empty($gateway_id)) {
            $gateway_id = $this->default_gateway;
        }

        // Validate input
        $validation = $this->validate_payment_input($gateway_id, $amount, $currency, $customer_data);
        if (is_wp_error($validation)) {
            return $this->error_response($validation->get_error_message());
        }

        $gateway = $this->get_gateway($gateway_id);
        if (!$gateway) {
            return $this->error_response(__('Payment gateway not found', 'chatshop'));
        }

        if (!$gateway->is_enabled()) {
            return $this->error_response(__('Payment gateway is not enabled', 'chatshop'));
        }

        try {
            $result = $gateway->generate_payment_link($amount, $currency, $customer_data, $options);

            // Log link generation
            $this->log_payment_link_generation($gateway_id, $amount, $currency, $result);

            return $result;
        } catch (\Exception $e) {
            $error_msg = 'Payment link generation failed: ' . $e->getMessage();
            chatshop_log($error_msg, 'error');

            return $this->error_response(__('Payment link generation failed', 'chatshop'));
        }
    }

    /**
     * Process webhook
     *
     * @since 1.0.0
     * @param array  $payload Webhook payload
     * @param string $gateway_id Gateway ID
     * @return bool Processing result
     */
    public function process_webhook($payload, $gateway_id)
    {
        if (empty($payload) || empty($gateway_id)) {
            chatshop_log('Invalid webhook data provided', 'error');
            return false;
        }

        $gateway = $this->get_gateway($gateway_id);
        if (!$gateway) {
            chatshop_log("Webhook gateway {$gateway_id} not found", 'error');
            return false;
        }

        try {
            $result = $gateway->handle_webhook($payload);

            // Log webhook processing
            $this->log_webhook_processing($gateway_id, $payload, $result);

            return $result;
        } catch (\Exception $e) {
            chatshop_log('Webhook processing failed: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * AJAX process payment
     *
     * @since 1.0.0
     */
    public function ajax_process_payment()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'chatshop_payment_nonce')) {
            wp_die(__('Security check failed', 'chatshop'));
        }

        $gateway_id = sanitize_key($_POST['gateway_id'] ?? '');
        $amount = floatval($_POST['amount'] ?? 0);
        $currency = sanitize_text_field($_POST['currency'] ?? 'NGN');

        $customer_data = array(
            'email' => sanitize_email($_POST['customer_email'] ?? ''),
            'first_name' => sanitize_text_field($_POST['customer_first_name'] ?? ''),
            'last_name' => sanitize_text_field($_POST['customer_last_name'] ?? ''),
            'phone' => sanitize_text_field($_POST['customer_phone'] ?? '')
        );

        $options = array();
        if (!empty($_POST['description'])) {
            $options['description'] = sanitize_textarea_field($_POST['description']);
        }

        $result = $this->process_payment($gateway_id, $amount, $currency, $customer_data, $options);

        wp_send_json($result);
    }

    /**
     * AJAX verify payment
     *
     * @since 1.0.0
     */
    public function ajax_verify_payment()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'chatshop_payment_nonce')) {
            wp_die(__('Security check failed', 'chatshop'));
        }

        $gateway_id = sanitize_key($_POST['gateway_id'] ?? '');
        $reference = sanitize_text_field($_POST['reference'] ?? '');

        $result = $this->verify_payment($gateway_id, $reference);

        wp_send_json($result);
    }

    /**
     * AJAX generate payment link
     *
     * @since 1.0.0
     */
    public function ajax_generate_payment_link()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'chatshop_payment_nonce')) {
            wp_die(__('Security check failed', 'chatshop'));
        }

        $gateway_id = sanitize_key($_POST['gateway_id'] ?? $this->default_gateway);
        $amount = floatval($_POST['amount'] ?? 0);
        $currency = sanitize_text_field($_POST['currency'] ?? 'NGN');

        $customer_data = array(
            'email' => sanitize_email($_POST['customer_email'] ?? ''),
            'first_name' => sanitize_text_field($_POST['customer_first_name'] ?? ''),
            'last_name' => sanitize_text_field($_POST['customer_last_name'] ?? ''),
            'phone' => sanitize_text_field($_POST['customer_phone'] ?? '')
        );

        $options = array();
        if (!empty($_POST['description'])) {
            $options['description'] = sanitize_textarea_field($_POST['description']);
        }
        if (!empty($_POST['due_date'])) {
            $options['due_date'] = sanitize_text_field($_POST['due_date']);
        }

        $result = $this->generate_payment_link($gateway_id, $amount, $currency, $customer_data, $options);

        wp_send_json($result);
    }

    /**
     * Validate payment input
     *
     * @since 1.0.0
     * @param string $gateway_id Gateway ID
     * @param float  $amount Payment amount
     * @param string $currency Currency code
     * @param array  $customer_data Customer data
     * @return true|WP_Error Validation result
     */
    private function validate_payment_input($gateway_id, $amount, $currency, $customer_data)
    {
        // Validate gateway ID
        if (empty($gateway_id)) {
            return new \WP_Error('missing_gateway', __('Payment gateway is required', 'chatshop'));
        }

        // Validate amount
        if (!is_numeric($amount) || $amount <= 0) {
            return new \WP_Error('invalid_amount', __('Invalid payment amount', 'chatshop'));
        }

        // Validate currency
        if (empty($currency) || strlen($currency) !== 3) {
            return new \WP_Error('invalid_currency', __('Invalid currency code', 'chatshop'));
        }

        // Validate customer email
        if (empty($customer_data['email']) || !is_email($customer_data['email'])) {
            return new \WP_Error('invalid_email', __('Valid customer email is required', 'chatshop'));
        }

        return true;
    }

    /**
     * Log payment attempt
     *
     * @since 1.0.0
     * @param string $gateway_id Gateway ID
     * @param float  $amount Payment amount
     * @param string $currency Currency code
     * @param array  $customer_data Customer data
     */
    private function log_payment_attempt($gateway_id, $amount, $currency, $customer_data)
    {
        if (!$this->logging_enabled) {
            return;
        }

        $log_data = array(
            'action' => 'payment_attempt',
            'gateway' => $gateway_id,
            'amount' => $amount,
            'currency' => $currency,
            'customer_email' => $customer_data['email'],
            'timestamp' => current_time('mysql'),
            'ip_address' => $this->get_client_ip()
        );

        $this->store_payment_log($log_data);
    }

    /**
     * Log payment result
     *
     * @since 1.0.0
     * @param string $gateway_id Gateway ID
     * @param array  $result Payment result
     */
    private function log_payment_result($gateway_id, $result)
    {
        if (!$this->logging_enabled) {
            return;
        }

        $log_data = array(
            'action' => 'payment_result',
            'gateway' => $gateway_id,
            'success' => isset($result['success']) ? $result['success'] : false,
            'message' => isset($result['message']) ? $result['message'] : '',
            'reference' => isset($result['data']['reference']) ? $result['data']['reference'] : '',
            'timestamp' => current_time('mysql')
        );

        $this->store_payment_log($log_data);
    }

    /**
     * Log payment verification
     *
     * @since 1.0.0
     * @param string $gateway_id Gateway ID
     * @param string $reference Payment reference
     * @param array  $result Verification result
     */
    private function log_payment_verification($gateway_id, $reference, $result)
    {
        if (!$this->logging_enabled) {
            return;
        }

        $log_data = array(
            'action' => 'payment_verification',
            'gateway' => $gateway_id,
            'reference' => $reference,
            'success' => isset($result['success']) ? $result['success'] : false,
            'status' => isset($result['data']['status']) ? $result['data']['status'] : '',
            'timestamp' => current_time('mysql')
        );

        $this->store_payment_log($log_data);
    }

    /**
     * Log payment link generation
     *
     * @since 1.0.0
     * @param string $gateway_id Gateway ID
     * @param float  $amount Payment amount
     * @param string $currency Currency code
     * @param array  $result Generation result
     */
    private function log_payment_link_generation($gateway_id, $amount, $currency, $result)
    {
        if (!$this->logging_enabled) {
            return;
        }

        $log_data = array(
            'action' => 'payment_link_generation',
            'gateway' => $gateway_id,
            'amount' => $amount,
            'currency' => $currency,
            'success' => isset($result['success']) ? $result['success'] : false,
            'payment_url' => isset($result['data']['payment_url']) ? $result['data']['payment_url'] : '',
            'timestamp' => current_time('mysql')
        );

        $this->store_payment_log($log_data);
    }

    /**
     * Log webhook processing
     *
     * @since 1.0.0
     * @param string $gateway_id Gateway ID
     * @param array  $payload Webhook payload
     * @param bool   $result Processing result
     */
    private function log_webhook_processing($gateway_id, $payload, $result)
    {
        if (!$this->logging_enabled) {
            return;
        }

        $log_data = array(
            'action' => 'webhook_processing',
            'gateway' => $gateway_id,
            'event_type' => isset($payload['event']) ? $payload['event'] : 'unknown',
            'success' => $result,
            'timestamp' => current_time('mysql'),
            'ip_address' => $this->get_client_ip()
        );

        $this->store_payment_log($log_data);
    }

    /**
     * Store payment log
     *
     * @since 1.0.0
     * @param array $log_data Log data
     */
    private function store_payment_log($log_data)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'chatshop_payment_logs';

        $data = array(
            'action' => sanitize_text_field($log_data['action']),
            'gateway' => sanitize_text_field($log_data['gateway']),
            'reference' => isset($log_data['reference']) ? sanitize_text_field($log_data['reference']) : '',
            'amount' => isset($log_data['amount']) ? floatval($log_data['amount']) : 0,
            'currency' => isset($log_data['currency']) ? sanitize_text_field($log_data['currency']) : '',
            'customer_email' => isset($log_data['customer_email']) ? sanitize_email($log_data['customer_email']) : '',
            'status' => isset($log_data['status']) ? sanitize_text_field($log_data['status']) : '',
            'success' => isset($log_data['success']) ? intval($log_data['success']) : 0,
            'message' => isset($log_data['message']) ? sanitize_text_field($log_data['message']) : '',
            'event_type' => isset($log_data['event_type']) ? sanitize_text_field($log_data['event_type']) : '',
            'payment_url' => isset($log_data['payment_url']) ? esc_url_raw($log_data['payment_url']) : '',
            'ip_address' => isset($log_data['ip_address']) ? sanitize_text_field($log_data['ip_address']) : '',
            'created_at' => $log_data['timestamp']
        );

        $wpdb->insert($table_name, $data);
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
     * Get payment statistics
     *
     * @since 1.0.0
     * @param string $gateway_id Gateway ID (optional)
     * @param string $period Period (day, week, month, year)
     * @return array Payment statistics
     */
    public function get_payment_statistics($gateway_id = '', $period = 'month')
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'chatshop_payments';

        // Determine date range
        $date_format = '%Y-%m-%d';
        $date_interval = '1 MONTH';

        switch ($period) {
            case 'day':
                $date_format = '%Y-%m-%d %H:00:00';
                $date_interval = '1 DAY';
                break;
            case 'week':
                $date_interval = '1 WEEK';
                break;
            case 'year':
                $date_format = '%Y-%m';
                $date_interval = '1 YEAR';
                break;
        }

        $where_clause = "WHERE created_at >= DATE_SUB(NOW(), INTERVAL {$date_interval})";

        if (!empty($gateway_id)) {
            $where_clause .= $wpdb->prepare(" AND gateway = %s", $gateway_id);
        }

        // Get total payments
        $total_query = "SELECT COUNT(*) as total_count, SUM(amount) as total_amount FROM {$table_name} {$where_clause}";
        $total_stats = $wpdb->get_row($total_query);

        // Get successful payments
        $success_query = "SELECT COUNT(*) as success_count, SUM(amount) as success_amount FROM {$table_name} {$where_clause} AND status = 'completed'";
        $success_stats = $wpdb->get_row($success_query);

        // Get failed payments
        $failed_query = "SELECT COUNT(*) as failed_count FROM {$table_name} {$where_clause} AND status = 'failed'";
        $failed_stats = $wpdb->get_row($failed_query);

        return array(
            'period' => $period,
            'gateway' => $gateway_id ?: 'all',
            'total_payments' => intval($total_stats->total_count),
            'total_amount' => floatval($total_stats->total_amount),
            'successful_payments' => intval($success_stats->success_count),
            'successful_amount' => floatval($success_stats->success_amount),
            'failed_payments' => intval($failed_stats->failed_count),
            'success_rate' => $total_stats->total_count > 0 ?
                round(($success_stats->success_count / $total_stats->total_count) * 100, 2) : 0
        );
    }

    /**
     * Get recent payments
     *
     * @since 1.0.0
     * @param int    $limit Number of payments to retrieve
     * @param string $gateway_id Gateway ID (optional)
     * @return array Recent payments
     */
    public function get_recent_payments($limit = 10, $gateway_id = '')
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'chatshop_payments';

        $where_clause = '';
        if (!empty($gateway_id)) {
            $where_clause = $wpdb->prepare("WHERE gateway = %s", $gateway_id);
        }

        $query = $wpdb->prepare(
            "SELECT * FROM {$table_name} {$where_clause} ORDER BY created_at DESC LIMIT %d",
            $limit
        );

        return $wpdb->get_results($query, ARRAY_A);
    }

    /**
     * Register REST API endpoints
     *
     * @since 1.0.0
     */
    public function register_api_endpoints()
    {
        register_rest_route('chatshop/v1', '/payment/process', array(
            'methods' => 'POST',
            'callback' => array($this, 'api_process_payment'),
            'permission_callback' => array($this, 'check_api_permission'),
            'args' => $this->get_api_payment_args()
        ));

        register_rest_route('chatshop/v1', '/payment/verify', array(
            'methods' => 'POST',
            'callback' => array($this, 'api_verify_payment'),
            'permission_callback' => array($this, 'check_api_permission'),
            'args' => array(
                'gateway_id' => array('required' => true, 'type' => 'string'),
                'reference' => array('required' => true, 'type' => 'string')
            )
        ));

        register_rest_route('chatshop/v1', '/payment/link', array(
            'methods' => 'POST',
            'callback' => array($this, 'api_generate_payment_link'),
            'permission_callback' => array($this, 'check_api_permission'),
            'args' => $this->get_api_payment_args()
        ));
    }

    /**
     * Get API payment arguments
     *
     * @since 1.0.0
     * @return array API arguments
     */
    private function get_api_payment_args()
    {
        return array(
            'gateway_id' => array('required' => false, 'type' => 'string'),
            'amount' => array('required' => true, 'type' => 'number'),
            'currency' => array('required' => true, 'type' => 'string'),
            'customer_email' => array('required' => true, 'type' => 'string'),
            'customer_first_name' => array('required' => false, 'type' => 'string'),
            'customer_last_name' => array('required' => false, 'type' => 'string'),
            'customer_phone' => array('required' => false, 'type' => 'string'),
            'description' => array('required' => false, 'type' => 'string')
        );
    }

    /**
     * Check API permission
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Request object
     * @return bool Permission result
     */
    public function check_api_permission($request)
    {
        // Check if API access is enabled
        $api_options = chatshop_get_option('api', '', array());
        if (!isset($api_options['enabled']) || !$api_options['enabled']) {
            return false;
        }

        // Check API key if required
        $api_key = $request->get_header('X-ChatShop-API-Key');
        if (empty($api_key)) {
            return false;
        }

        $stored_api_key = isset($api_options['api_key']) ? $api_options['api_key'] : '';

        return !empty($stored_api_key) && hash_equals($stored_api_key, $api_key);
    }

    /**
     * API process payment
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response API response
     */
    public function api_process_payment($request)
    {
        $gateway_id = $request->get_param('gateway_id') ?: $this->default_gateway;
        $amount = floatval($request->get_param('amount'));
        $currency = $request->get_param('currency');

        $customer_data = array(
            'email' => $request->get_param('customer_email'),
            'first_name' => $request->get_param('customer_first_name'),
            'last_name' => $request->get_param('customer_last_name'),
            'phone' => $request->get_param('customer_phone')
        );

        $options = array();
        if ($request->get_param('description')) {
            $options['description'] = $request->get_param('description');
        }

        $result = $this->process_payment($gateway_id, $amount, $currency, $customer_data, $options);

        return rest_ensure_response($result);
    }

    /**
     * API verify payment
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response API response
     */
    public function api_verify_payment($request)
    {
        $gateway_id = $request->get_param('gateway_id');
        $reference = $request->get_param('reference');

        $result = $this->verify_payment($gateway_id, $reference);

        return rest_ensure_response($result);
    }

    /**
     * API generate payment link
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response API response
     */
    public function api_generate_payment_link($request)
    {
        $gateway_id = $request->get_param('gateway_id') ?: $this->default_gateway;
        $amount = floatval($request->get_param('amount'));
        $currency = $request->get_param('currency');

        $customer_data = array(
            'email' => $request->get_param('customer_email'),
            'first_name' => $request->get_param('customer_first_name'),
            'last_name' => $request->get_param('customer_last_name'),
            'phone' => $request->get_param('customer_phone')
        );

        $options = array();
        if ($request->get_param('description')) {
            $options['description'] = $request->get_param('description');
        }

        $result = $this->generate_payment_link($gateway_id, $amount, $currency, $customer_data, $options);

        return rest_ensure_response($result);
    }

    /**
     * Error response helper
     *
     * @since 1.0.0
     * @param string $message Error message
     * @return array Error response
     */
    private function error_response($message)
    {
        return array(
            'success' => false,
            'message' => $message,
            'data' => null
        );
    }

    /**
     * Success response helper
     *
     * @since 1.0.0
     * @param array $data Response data
     * @return array Success response
     */
    private function success_response($data)
    {
        return array(
            'success' => true,
            'message' => __('Operation completed successfully', 'chatshop'),
            'data' => $data
        );
    }

    /**
     * Get default gateway
     *
     * @since 1.0.0
     * @return string Default gateway ID
     */
    public function get_default_gateway()
    {
        return $this->default_gateway;
    }

    /**
     * Set default gateway
     *
     * @since 1.0.0
     * @param string $gateway_id Gateway ID
     * @return bool Update result
     */
    public function set_default_gateway($gateway_id)
    {
        if (!isset($this->gateways[$gateway_id])) {
            return false;
        }

        $this->default_gateway = $gateway_id;

        // Update settings
        $general_options = chatshop_get_option('general', '', array());
        $general_options['default_gateway'] = $gateway_id;

        return chatshop_update_option('general', '', $general_options);
    }
}
