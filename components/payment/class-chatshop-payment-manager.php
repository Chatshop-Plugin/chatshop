<?php

/**
 * Payment Manager Class
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
 * Payment manager class
 *
 * @since 1.0.0
 */
class ChatShop_Payment_Manager
{
    /**
     * Registered payment gateways
     *
     * @var array
     * @since 1.0.0
     */
    private $gateways = array();

    /**
     * Payment factory instance
     *
     * @var ChatShop_Payment_Factory
     * @since 1.0.0
     */
    private $factory;

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        $this->factory = new ChatShop_Payment_Factory();
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     *
     * @since 1.0.0
     */
    private function init_hooks()
    {
        add_action('chatshop_register_payment_gateways', array($this, 'register_default_gateways'), 10);
        add_action('init', array($this, 'load_gateways'), 20);
    }

    /**
     * Register a payment gateway
     *
     * @param ChatShop_Abstract_Payment_Gateway $gateway Gateway instance
     * @return bool Success status
     * @since 1.0.0
     */
    public function register_gateway($gateway)
    {
        if (!$gateway instanceof ChatShop_Abstract_Payment_Gateway) {
            return false;
        }

        $gateway_id = $gateway->get_id();

        if (empty($gateway_id)) {
            return false;
        }

        $this->gateways[$gateway_id] = $gateway;

        // Log gateway registration
        if (function_exists('chatshop_log')) {
            chatshop_log("Payment gateway registered: {$gateway_id}", 'info');
        }

        return true;
    }

    /**
     * Unregister a payment gateway
     *
     * @param string $gateway_id Gateway ID
     * @return bool Success status
     * @since 1.0.0
     */
    public function unregister_gateway($gateway_id)
    {
        if (isset($this->gateways[$gateway_id])) {
            unset($this->gateways[$gateway_id]);
            return true;
        }

        return false;
    }

    /**
     * Get all registered gateways
     *
     * @return array Registered gateways
     * @since 1.0.0
     */
    public function get_gateways()
    {
        return $this->gateways;
    }

    /**
     * Get a specific gateway
     *
     * @param string $gateway_id Gateway ID
     * @return ChatShop_Abstract_Payment_Gateway|null Gateway instance or null
     * @since 1.0.0
     */
    public function get_gateway($gateway_id)
    {
        return isset($this->gateways[$gateway_id]) ? $this->gateways[$gateway_id] : null;
    }

    /**
     * Get enabled gateways
     *
     * @return array Enabled gateways
     * @since 1.0.0
     */
    public function get_enabled_gateways()
    {
        $enabled = array();

        foreach ($this->gateways as $gateway_id => $gateway) {
            if ($gateway->is_enabled()) {
                $enabled[$gateway_id] = $gateway;
            }
        }

        return $enabled;
    }

    /**
     * Check if gateway exists
     *
     * @param string $gateway_id Gateway ID
     * @return bool Whether gateway exists
     * @since 1.0.0
     */
    public function gateway_exists($gateway_id)
    {
        return isset($this->gateways[$gateway_id]);
    }

    /**
     * Process payment through a specific gateway
     *
     * @param string $gateway_id Gateway ID
     * @param float  $amount Payment amount
     * @param string $currency Currency code
     * @param array  $customer_data Customer information
     * @param array  $options Additional options
     * @return array Payment result
     * @since 1.0.0
     */
    public function process_payment($gateway_id, $amount, $currency, $customer_data, $options = array())
    {
        $gateway = $this->get_gateway($gateway_id);

        if (!$gateway) {
            return array(
                'success' => false,
                'message' => __('Payment gateway not found', 'chatshop'),
                'error_code' => 'gateway_not_found'
            );
        }

        if (!$gateway->is_enabled()) {
            return array(
                'success' => false,
                'message' => __('Payment gateway is disabled', 'chatshop'),
                'error_code' => 'gateway_disabled'
            );
        }

        try {
            $result = $gateway->process_payment($amount, $currency, $customer_data, $options);

            // Hook for payment processing
            do_action('chatshop_payment_processed', $result, $gateway_id, $amount, $currency, $customer_data);

            return $result;
        } catch (Exception $e) {
            if (function_exists('chatshop_log')) {
                chatshop_log("Payment processing error: " . $e->getMessage(), 'error');
            }

            return array(
                'success' => false,
                'message' => __('Payment processing failed', 'chatshop'),
                'error_code' => 'processing_failed'
            );
        }
    }

    /**
     * Verify transaction through a specific gateway
     *
     * @param string $gateway_id Gateway ID
     * @param string $reference Transaction reference
     * @return array Verification result
     * @since 1.0.0
     */
    public function verify_transaction($gateway_id, $reference)
    {
        $gateway = $this->get_gateway($gateway_id);

        if (!$gateway) {
            return array(
                'success' => false,
                'message' => __('Payment gateway not found', 'chatshop'),
                'error_code' => 'gateway_not_found'
            );
        }

        try {
            $result = $gateway->verify_transaction($reference);

            // Hook for transaction verification
            do_action('chatshop_transaction_verified', $result, $gateway_id, $reference);

            return $result;
        } catch (Exception $e) {
            if (function_exists('chatshop_log')) {
                chatshop_log("Transaction verification error: " . $e->getMessage(), 'error');
            }

            return array(
                'success' => false,
                'message' => __('Transaction verification failed', 'chatshop'),
                'error_code' => 'verification_failed'
            );
        }
    }

    /**
     * Process webhook for a specific gateway
     *
     * @param array  $payload Webhook payload
     * @param string $gateway_id Gateway ID
     * @return bool Success status
     * @since 1.0.0
     */
    public function process_webhook($payload, $gateway_id)
    {
        $gateway = $this->get_gateway($gateway_id);

        if (!$gateway) {
            if (function_exists('chatshop_log')) {
                chatshop_log("Webhook received for unknown gateway: {$gateway_id}", 'warning');
            }
            return false;
        }

        try {
            $result = $gateway->handle_webhook($payload);

            // Hook for webhook processing
            do_action('chatshop_webhook_processed', $result, $gateway_id, $payload);

            return $result;
        } catch (Exception $e) {
            if (function_exists('chatshop_log')) {
                chatshop_log("Webhook processing error: " . $e->getMessage(), 'error');
            }

            return false;
        }
    }

    /**
     * Get supported currencies from all gateways
     *
     * @return array Supported currencies
     * @since 1.0.0
     */
    public function get_supported_currencies()
    {
        $currencies = array();

        foreach ($this->gateways as $gateway) {
            $gateway_currencies = $gateway->get_supported_currencies();
            $currencies = array_merge($currencies, $gateway_currencies);
        }

        return array_unique($currencies);
    }

    /**
     * Get gateways that support a specific currency
     *
     * @param string $currency Currency code
     * @return array Supporting gateways
     * @since 1.0.0
     */
    public function get_gateways_for_currency($currency)
    {
        $supporting_gateways = array();

        foreach ($this->gateways as $gateway_id => $gateway) {
            if ($gateway->supports_currency($currency)) {
                $supporting_gateways[$gateway_id] = $gateway;
            }
        }

        return $supporting_gateways;
    }

    /**
     * Register default gateways
     *
     * @since 1.0.0
     */
    public function register_default_gateways()
    {
        // Register Paystack gateway if class exists
        if (class_exists('ChatShop\ChatShop_Paystack_Gateway')) {
            try {
                $paystack = new ChatShop_Paystack_Gateway();
                $this->register_gateway($paystack);
            } catch (Exception $e) {
                if (function_exists('chatshop_log')) {
                    chatshop_log("Failed to register Paystack gateway: " . $e->getMessage(), 'error');
                }
            }
        }

        // Hook for additional gateway registration
        do_action('chatshop_register_additional_gateways', $this);
    }

    /**
     * Load gateways
     *
     * @since 1.0.0
     */
    public function load_gateways()
    {
        // Load gateway classes
        $this->load_gateway_classes();

        // Register gateways
        do_action('chatshop_register_payment_gateways', $this);
    }

    /**
     * Load gateway classes
     *
     * @since 1.0.0
     */
    private function load_gateway_classes()
    {
        $gateways_dir = CHATSHOP_PLUGIN_DIR . 'components/payment/gateways/';

        if (!is_dir($gateways_dir)) {
            return;
        }

        $gateway_dirs = glob($gateways_dir . '*', GLOB_ONLYDIR);

        foreach ($gateway_dirs as $gateway_dir) {
            $gateway_id = basename($gateway_dir);
            $gateway_file = $gateway_dir . "/class-chatshop-{$gateway_id}-gateway.php";

            if (file_exists($gateway_file)) {
                require_once $gateway_file;

                if (function_exists('chatshop_log')) {
                    chatshop_log("Loaded gateway class: {$gateway_id}", 'debug');
                }
            }
        }
    }

    /**
     * Get gateway statistics
     *
     * @return array Gateway statistics
     * @since 1.0.0
     */
    public function get_gateway_stats()
    {
        $stats = array(
            'total' => count($this->gateways),
            'enabled' => 0,
            'configured' => 0
        );

        foreach ($this->gateways as $gateway) {
            if ($gateway->is_enabled()) {
                $stats['enabled']++;
            }

            if (method_exists($gateway, 'is_configured') && $gateway->is_configured()) {
                $stats['configured']++;
            }
        }

        return $stats;
    }

    /**
     * Register API endpoints
     *
     * @since 1.0.0
     */
    public function register_api_endpoints()
    {
        // Register REST API endpoints for payment processing
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }

    /**
     * Register REST routes
     *
     * @since 1.0.0
     */
    public function register_rest_routes()
    {
        register_rest_route('chatshop/v1', '/gateways', array(
            'methods' => 'GET',
            'callback' => array($this, 'api_get_gateways'),
            'permission_callback' => array($this, 'api_permissions_check')
        ));

        register_rest_route('chatshop/v1', '/payment/(?P<gateway>[\w-]+)', array(
            'methods' => 'POST',
            'callback' => array($this, 'api_process_payment'),
            'permission_callback' => array($this, 'api_permissions_check')
        ));

        register_rest_route('chatshop/v1', '/verify/(?P<gateway>[\w-]+)/(?P<reference>[\w-]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'api_verify_transaction'),
            'permission_callback' => array($this, 'api_permissions_check')
        ));
    }

    /**
     * API permissions check
     *
     * @param WP_REST_Request $request Request object
     * @return bool Permission status
     * @since 1.0.0
     */
    public function api_permissions_check($request)
    {
        return current_user_can('manage_options');
    }

    /**
     * API get gateways endpoint
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response
     * @since 1.0.0
     */
    public function api_get_gateways($request)
    {
        $gateways_data = array();

        foreach ($this->gateways as $gateway_id => $gateway) {
            $gateways_data[$gateway_id] = array(
                'id' => $gateway->get_id(),
                'title' => $gateway->get_title(),
                'description' => $gateway->get_description(),
                'enabled' => $gateway->is_enabled(),
                'test_mode' => $gateway->is_test_mode(),
                'supported_currencies' => $gateway->get_supported_currencies()
            );
        }

        return rest_ensure_response($gateways_data);
    }

    /**
     * API process payment endpoint
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response
     * @since 1.0.0
     */
    public function api_process_payment($request)
    {
        $gateway_id = $request->get_param('gateway');
        $amount = $request->get_param('amount');
        $currency = $request->get_param('currency');
        $customer_data = $request->get_param('customer_data');
        $options = $request->get_param('options') ?? array();

        $result = $this->process_payment($gateway_id, $amount, $currency, $customer_data, $options);

        return rest_ensure_response($result);
    }

    /**
     * API verify transaction endpoint
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response
     * @since 1.0.0
     */
    public function api_verify_transaction($request)
    {
        $gateway_id = $request->get_param('gateway');
        $reference = $request->get_param('reference');

        $result = $this->verify_transaction($gateway_id, $reference);

        return rest_ensure_response($result);
    }
}
