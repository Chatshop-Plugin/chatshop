<?php

/**
 * Payment Manager Class
 *
 * Main orchestrator for payment processing and gateway management.
 *
 * @package ChatShop
 * @since 1.0.0
 */

namespace ChatShop;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ChatShop Payment Manager Class
 *
 * @since 1.0.0
 */
class ChatShop_Payment_Manager
{
    /**
     * Payment factory instance
     *
     * @var ChatShop_Payment_Factory
     * @since 1.0.0
     */
    private $factory;

    /**
     * Current transaction data
     *
     * @var array
     * @since 1.0.0
     */
    private $current_transaction = array();

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        $this->factory = ChatShop_Payment_Factory::class;
        $this->init_hooks();
        $this->log_info('Payment manager initialized');
    }

    /**
     * Initialize WordPress hooks
     *
     * @since 1.0.0
     */
    private function init_hooks()
    {
        // WooCommerce integration hooks
        add_action('woocommerce_init', array($this, 'integrate_with_woocommerce'));

        // Payment processing hooks
        add_action('chatshop_process_payment', array($this, 'process_payment'), 10, 3);
        add_action('chatshop_verify_payment', array($this, 'verify_payment'), 10, 2);

        // Webhook handling
        add_action('wp_ajax_nopriv_chatshop_payment_webhook', array($this, 'handle_webhook'));
        add_action('wp_ajax_chatshop_payment_webhook', array($this, 'handle_webhook'));

        // Cleanup hooks
        add_action('chatshop_cleanup_expired_transactions', array($this, 'cleanup_expired_transactions'));
    }

    /**
     * Initialize component
     *
     * @since 1.0.0
     */
    public function init()
    {
        // Component initialization logic
        $this->log_info('Payment manager component initialized');
    }

    /**
     * Integrate with WooCommerce
     *
     * @since 1.0.0
     */
    public function integrate_with_woocommerce()
    {
        if (!class_exists('WooCommerce')) {
            return;
        }

        // Add payment gateways to WooCommerce
        add_filter('woocommerce_payment_gateways', array($this, 'add_wc_gateways'));

        // Order status hooks
        add_action('woocommerce_order_status_completed', array($this, 'handle_order_completed'));
        add_action('woocommerce_order_status_cancelled', array($this, 'handle_order_cancelled'));
        add_action('woocommerce_order_status_refunded', array($this, 'handle_order_refunded'));

        $this->log_info('WooCommerce integration initialized');
    }

    /**
     * Add ChatShop gateways to WooCommerce
     *
     * @since 1.0.0
     * @param array $gateways Existing WooCommerce gateways
     * @return array Modified gateways array
     */
    public function add_wc_gateways($gateways)
    {
        $chatshop_gateways = $this->factory::get_enabled_gateways();

        foreach ($chatshop_gateways as $gateway_id => $config) {
            // Create WooCommerce gateway wrapper if needed
            $wc_gateway_class = $this->create_wc_gateway_wrapper($gateway_id, $config);
            if ($wc_gateway_class) {
                $gateways[] = $wc_gateway_class;
            }
        }

        return $gateways;
    }

    /**
     * Create WooCommerce gateway wrapper
     *
     * @since 1.0.0
     * @param string $gateway_id Gateway identifier
     * @param array $config Gateway configuration
     * @return string|false Gateway class name or false on failure
     */
    private function create_wc_gateway_wrapper($gateway_id, $config)
    {
        // This would create a WooCommerce-compatible wrapper
        // For now, we'll log and return false
        $this->log_info("WooCommerce wrapper needed for gateway: {$gateway_id}");
        return false;
    }

    /**
     * Process a payment
     *
     * @since 1.0.0
     * @param float $amount Payment amount
     * @param string $currency Currency code
     * @param array $payment_data Payment data including gateway, customer info, etc.
     * @return array Payment result
     */
    public function process_payment($amount, $currency, $payment_data)
    {
        try {
            // Validate input
            $validation_result = $this->validate_payment_data($amount, $currency, $payment_data);
            if (!$validation_result['valid']) {
                return $this->error_response($validation_result['message']);
            }

            // Get gateway
            $gateway_id = sanitize_key($payment_data['gateway']);
            $gateway = $this->factory::get_gateway_instance($gateway_id);

            if (!$gateway) {
                return $this->error_response('Payment gateway not available');
            }

            // Prepare transaction data
            $transaction_data = $this->prepare_transaction_data($amount, $currency, $payment_data);

            // Store current transaction
            $this->current_transaction = $transaction_data;

            // Process payment through gateway
            $result = $gateway->process_payment($amount, $currency, $payment_data);

            // Log transaction
            $this->log_transaction($transaction_data, $result);

            // Return result
            return $this->format_payment_result($result);
        } catch (\Exception $e) {
            $this->log_error('Payment processing failed: ' . $e->getMessage());
            return $this->error_response('Payment processing failed');
        }
    }

    /**
     * Verify a payment transaction
     *
     * @since 1.0.0
     * @param string $transaction_id Transaction identifier
     * @param string $gateway_id Gateway identifier
     * @return array Verification result
     */
    public function verify_payment($transaction_id, $gateway_id)
    {
        try {
            // Get gateway
            $gateway = $this->factory::get_gateway_instance($gateway_id);

            if (!$gateway) {
                return $this->error_response('Payment gateway not available');
            }

            // Verify transaction
            $result = $gateway->verify_transaction($transaction_id);

            // Update transaction status
            $this->update_transaction_status($transaction_id, $result);

            return $this->format_verification_result($result);
        } catch (\Exception $e) {
            $this->log_error('Payment verification failed: ' . $e->getMessage());
            return $this->error_response('Payment verification failed');
        }
    }

    /**
     * Handle webhook requests
     *
     * @since 1.0.0
     */
    public function handle_webhook()
    {
        // Get gateway ID from request
        $gateway_id = sanitize_key($_GET['gateway'] ?? '');

        if (empty($gateway_id)) {
            wp_die('Invalid webhook request', 400);
        }

        // Get request payload
        $payload = file_get_contents('php://input');
        $decoded_payload = json_decode($payload, true);

        // Process webhook
        $result = $this->process_webhook($decoded_payload, $gateway_id);

        if ($result) {
            wp_die('OK', 200);
        } else {
            wp_die('Webhook processing failed', 400);
        }
    }

    /**
     * Process webhook payload
     *
     * @since 1.0.0
     * @param array $payload Webhook payload
     * @param string $gateway_id Gateway identifier
     * @return bool True on success, false on failure
     */
    public function process_webhook($payload, $gateway_id)
    {
        try {
            // Get gateway
            $gateway = $this->factory::get_gateway_instance($gateway_id);

            if (!$gateway) {
                $this->log_error("Gateway not found for webhook: {$gateway_id}");
                return false;
            }

            // Handle webhook through gateway
            $result = $gateway->handle_webhook($payload);

            // Log webhook
            $this->log_webhook($gateway_id, $payload, $result);

            return $result;
        } catch (\Exception $e) {
            $this->log_error('Webhook processing failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate payment link
     *
     * @since 1.0.0
     * @param array $payment_data Payment data
     * @return string|false Payment link or false on failure
     */
    public function generate_payment_link($payment_data)
    {
        try {
            // Validate required data
            if (empty($payment_data['amount']) || empty($payment_data['currency'])) {
                return false;
            }

            // Create unique reference
            $reference = $this->generate_payment_reference();
            $payment_data['reference'] = $reference;

            // Store payment data temporarily
            $this->store_payment_data($reference, $payment_data);

            // Generate link
            $payment_url = add_query_arg(array(
                'chatshop_payment' => 1,
                'ref' => $reference
            ), home_url('/'));

            return $payment_url;
        } catch (\Exception $e) {
            $this->log_error('Payment link generation failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get available payment methods
     *
     * @since 1.0.0
     * @param array $filters Optional filters (country, currency, amount)
     * @return array Available payment methods
     */
    public function get_available_payment_methods($filters = array())
    {
        $methods = array();
        $enabled_gateways = $this->factory::get_enabled_gateways();

        foreach ($enabled_gateways as $gateway_id => $config) {
            // Apply filters
            if (!$this->gateway_matches_filters($gateway_id, $config, $filters)) {
                continue;
            }

            $methods[$gateway_id] = array(
                'id' => $gateway_id,
                'name' => $config['name'],
                'description' => $config['description'],
                'supports' => $config['supports'],
                'icon' => $this->get_gateway_icon($gateway_id)
            );
        }

        return $methods;
    }

    /**
     * Register API endpoints
     *
     * @since 1.0.0
     */
    public function register_api_endpoints()
    {
        // Register REST API endpoints for payment operations
        register_rest_route('chatshop/v1', '/payments/process', array(
            'methods' => 'POST',
            'callback' => array($this, 'api_process_payment'),
            'permission_callback' => array($this, 'api_permission_check')
        ));

        register_rest_route('chatshop/v1', '/payments/verify/(?P<transaction_id>[a-zA-Z0-9_-]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'api_verify_payment'),
            'permission_callback' => array($this, 'api_permission_check')
        ));

        register_rest_route('chatshop/v1', '/payments/methods', array(
            'methods' => 'GET',
            'callback' => array($this, 'api_get_payment_methods'),
            'permission_callback' => '__return_true'
        ));

        $this->log_info('Payment API endpoints registered');
    }

    /**
     * Handle order completion
     *
     * @since 1.0.0
     * @param int $order_id Order ID
     */
    public function handle_order_completed($order_id)
    {
        // Handle order completion logic
        $this->log_info("Order completed: {$order_id}");
        do_action('chatshop_order_completed', $order_id);
    }

    /**
     * Handle order cancellation
     *
     * @since 1.0.0
     * @param int $order_id Order ID
     */
    public function handle_order_cancelled($order_id)
    {
        // Handle order cancellation logic
        $this->log_info("Order cancelled: {$order_id}");
        do_action('chatshop_order_cancelled', $order_id);
    }

    /**
     * Handle order refund
     *
     * @since 1.0.0
     * @param int $order_id Order ID
     */
    public function handle_order_refunded($order_id)
    {
        // Handle order refund logic
        $this->log_info("Order refunded: {$order_id}");
        do_action('chatshop_order_refunded', $order_id);
    }

    /**
     * Cleanup expired transactions
     *
     * @since 1.0.0
     */
    public function cleanup_expired_transactions()
    {
        // Cleanup logic for expired transactions
        $this->log_info('Cleaning up expired transactions');
    }

    /**
     * Validate payment data
     *
     * @since 1.0.0
     * @param float $amount Payment amount
     * @param string $currency Currency code
     * @param array $payment_data Payment data
     * @return array Validation result
     */
    private function validate_payment_data($amount, $currency, $payment_data)
    {
        // Basic validation
        if (empty($amount) || $amount <= 0) {
            return array('valid' => false, 'message' => 'Invalid amount');
        }

        if (empty($currency) || strlen($currency) !== 3) {
            return array('valid' => false, 'message' => 'Invalid currency');
        }

        if (empty($payment_data['gateway'])) {
            return array('valid' => false, 'message' => 'Gateway not specified');
        }

        return array('valid' => true, 'message' => 'Valid');
    }

    /**
     * Prepare transaction data
     *
     * @since 1.0.0
     * @param float $amount Payment amount
     * @param string $currency Currency code
     * @param array $payment_data Payment data
     * @return array Transaction data
     */
    private function prepare_transaction_data($amount, $currency, $payment_data)
    {
        return array(
            'amount' => $amount,
            'currency' => $currency,
            'gateway' => $payment_data['gateway'],
            'reference' => $this->generate_payment_reference(),
            'customer_data' => $payment_data['customer'] ?? array(),
            'metadata' => $payment_data['metadata'] ?? array(),
            'created_at' => current_time('mysql'),
            'status' => 'pending'
        );
    }

    /**
     * Generate unique payment reference
     *
     * @since 1.0.0
     * @return string Payment reference
     */
    private function generate_payment_reference()
    {
        return 'CS_' . wp_generate_uuid4();
    }

    /**
     * Store payment data
     *
     * @since 1.0.0
     * @param string $reference Payment reference
     * @param array $data Payment data
     */
    private function store_payment_data($reference, $data)
    {
        // Store in transients for temporary storage
        set_transient("chatshop_payment_{$reference}", $data, 24 * HOUR_IN_SECONDS);
    }

    /**
     * Format payment result
     *
     * @since 1.0.0
     * @param mixed $result Gateway result
     * @return array Formatted result
     */
    private function format_payment_result($result)
    {
        if (is_array($result)) {
            return $result;
        }

        return array(
            'success' => false,
            'message' => 'Unknown payment result'
        );
    }

    /**
     * Format verification result
     *
     * @since 1.0.0
     * @param mixed $result Gateway result
     * @return array Formatted result
     */
    private function format_verification_result($result)
    {
        if (is_array($result)) {
            return $result;
        }

        return array(
            'verified' => false,
            'message' => 'Unknown verification result'
        );
    }

    /**
     * Create error response
     *
     * @since 1.0.0
     * @param string $message Error message
     * @return array Error response
     */
    private function error_response($message)
    {
        return array(
            'success' => false,
            'error' => true,
            'message' => $message
        );
    }

    /**
     * Log transaction
     *
     * @since 1.0.0
     * @param array $transaction_data Transaction data
     * @param array $result Processing result
     */
    private function log_transaction($transaction_data, $result)
    {
        // Log transaction for debugging/auditing
        $this->log_info('Transaction processed: ' . $transaction_data['reference']);
    }

    /**
     * Log webhook
     *
     * @since 1.0.0
     * @param string $gateway_id Gateway ID
     * @param array $payload Webhook payload
     * @param bool $result Processing result
     */
    private function log_webhook($gateway_id, $payload, $result)
    {
        // Log webhook for debugging/auditing
        $this->log_info("Webhook processed for gateway: {$gateway_id}");
    }

    /**
     * Update transaction status
     *
     * @since 1.0.0
     * @param string $transaction_id Transaction ID
     * @param array $result Verification result
     */
    private function update_transaction_status($transaction_id, $result)
    {
        // Update transaction status in database
        $this->log_info("Transaction status updated: {$transaction_id}");
    }

    /**
     * Check if gateway matches filters
     *
     * @since 1.0.0
     * @param string $gateway_id Gateway ID
     * @param array $config Gateway config
     * @param array $filters Filters to apply
     * @return bool True if matches, false otherwise
     */
    private function gateway_matches_filters($gateway_id, $config, $filters)
    {
        // Apply country filter
        if (!empty($filters['country']) && !empty($config['countries'])) {
            if (!in_array($filters['country'], $config['countries'])) {
                return false;
            }
        }

        // Apply currency filter
        if (!empty($filters['currency']) && !empty($config['currencies'])) {
            if (!in_array($filters['currency'], $config['currencies'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get gateway icon
     *
     * @since 1.0.0
     * @param string $gateway_id Gateway ID
     * @return string Icon URL
     */
    private function get_gateway_icon($gateway_id)
    {
        // Return default icon for now
        return CHATSHOP_PLUGIN_URL . 'assets/images/gateways/' . $gateway_id . '.png';
    }

    /**
     * API permission check
     *
     * @since 1.0.0
     * @return bool True if allowed, false otherwise
     */
    public function api_permission_check()
    {
        // Basic permission check - can be enhanced
        return true;
    }

    /**
     * API process payment endpoint
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request REST request
     * @return \WP_REST_Response Response
     */
    public function api_process_payment($request)
    {
        $amount = $request->get_param('amount');
        $currency = $request->get_param('currency');
        $payment_data = $request->get_param('payment_data');

        $result = $this->process_payment($amount, $currency, $payment_data);

        return new \WP_REST_Response($result);
    }

    /**
     * API verify payment endpoint
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request REST request
     * @return \WP_REST_Response Response
     */
    public function api_verify_payment($request)
    {
        $transaction_id = $request->get_param('transaction_id');
        $gateway_id = $request->get_param('gateway');

        $result = $this->verify_payment($transaction_id, $gateway_id);

        return new \WP_REST_Response($result);
    }

    /**
     * API get payment methods endpoint
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request REST request
     * @return \WP_REST_Response Response
     */
    public function api_get_payment_methods($request)
    {
        $filters = array(
            'country' => $request->get_param('country'),
            'currency' => $request->get_param('currency'),
            'amount' => $request->get_param('amount')
        );

        $methods = $this->get_available_payment_methods($filters);

        return new \WP_REST_Response($methods);
    }

    /**
     * Log error message
     *
     * @since 1.0.0
     * @param string $message Error message
     */
    private function log_error($message)
    {
        if (function_exists('chatshop_log')) {
            chatshop_log($message, 'error');
        } else {
            error_log("ChatShop Payment Manager: {$message}");
        }
    }

    /**
     * Log info message
     *
     * @since 1.0.0
     * @param string $message Info message
     */
    private function log_info($message)
    {
        if (function_exists('chatshop_log')) {
            chatshop_log($message, 'info');
        } else {
            error_log("ChatShop Payment Manager: {$message}");
        }
    }
}
