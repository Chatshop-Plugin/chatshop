<?php

/**
 * Payment Manager Class
 *
 * Orchestrates payment processing using the payment factory.
 * Manages multiple gateways and integrates with component registry.
 *
 * @package    ChatShop
 * @subpackage Components\Payment
 * @since      1.0.0
 * @author     Modewebhost
 */

namespace ChatShop;

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Payment Manager Class
 *
 * Main orchestrator for payment processing, gateway management,
 * and component registration within ChatShop.
 *
 * @since 1.0.0
 */
class ChatShop_Payment_Manager
{
    /**
     * Component ID
     *
     * @var string
     * @since 1.0.0
     */
    const COMPONENT_ID = 'payment';

    /**
     * Payment statuses
     *
     * @var array
     * @since 1.0.0
     */
    const PAYMENT_STATUSES = array(
        'pending'   => 'pending',
        'success'   => 'success',
        'failed'    => 'failed',
        'cancelled' => 'cancelled',
        'refunded'  => 'refunded',
    );

    /**
     * Primary gateway ID
     *
     * @var string
     * @since 1.0.0
     */
    private $primary_gateway;

    /**
     * Fallback gateways
     *
     * @var array
     * @since 1.0.0
     */
    private $fallback_gateways;

    /**
     * Payment cache
     *
     * @var array
     * @since 1.0.0
     */
    private $payment_cache = array();

    /**
     * Initialize payment manager
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        $this->register_component();
        $this->init_hooks();
        $this->load_settings();
    }

    /**
     * Load payment settings
     *
     * @since 1.0.0
     */
    private function load_settings()
    {
        $this->primary_gateway = chatshop_get_option('payment', 'primary_gateway', 'paystack');
        $this->fallback_gateways = chatshop_get_option('payment', 'fallback_gateways', array());
    }

    /**
     * Register payment component
     *
     * @since 1.0.0
     */
    private function register_component()
    {
        if (class_exists('ChatShop_Component_Registry')) {
            ChatShop_Component_Registry::register_component(self::COMPONENT_ID, array(
                'name'         => __('Payment Processing', 'chatshop'),
                'description'  => __('Handles payment processing via multiple gateways', 'chatshop'),
                'class'        => __CLASS__,
                'dependencies' => array(),
                'premium'      => false,
                'version'      => '1.0.0',
                'supports'     => array('multiple_gateways', 'webhooks', 'refunds'),
            ));
        }
    }

    /**
     * Initialize hooks
     *
     * @since 1.0.0
     */
    private function init_hooks()
    {
        add_action('init', array('ChatShop_Payment_Factory', 'init'), 5);
        add_action('wp_ajax_chatshop_process_payment', array($this, 'handle_payment_ajax'));
        add_action('wp_ajax_nopriv_chatshop_process_payment', array($this, 'handle_payment_ajax'));
        add_action('wp_ajax_chatshop_verify_payment', array($this, 'handle_verify_ajax'));
        add_action('rest_api_init', array($this, 'register_api_endpoints'));
        add_filter('chatshop_payment_gateways_loaded', array($this, 'on_gateways_loaded'));
    }

    /**
     * Process payment with gateway failover
     *
     * @param float  $amount        Payment amount.
     * @param string $currency      Currency code.
     * @param array  $customer_data Customer information.
     * @param string $gateway_id    Optional specific gateway ID.
     * @param array  $metadata      Additional payment metadata.
     * @return array Payment result.
     * @since 1.0.0
     */
    public function process_payment($amount, $currency, $customer_data, $gateway_id = null, $metadata = array())
    {
        // Input validation
        $validation = $this->validate_payment_data($amount, $currency, $customer_data);
        if (!$validation['is_valid']) {
            return $this->error_response($validation['errors'][0]);
        }

        // Generate payment reference
        $reference = $this->generate_payment_reference();
        $payment_data = array(
            'reference'     => $reference,
            'amount'        => $amount,
            'currency'      => strtoupper($currency),
            'customer_data' => $customer_data,
            'metadata'      => $metadata,
            'timestamp'     => current_time('mysql'),
        );

        // Get gateway(s) to try
        $gateways = $gateway_id ?
            array(ChatShop_Payment_Factory::get_gateway($gateway_id)) :
            $this->get_payment_gateways_for_currency($currency);

        $last_error = null;

        foreach ($gateways as $gateway) {
            if (!$gateway || !$gateway->is_enabled()) {
                continue;
            }

            try {
                chatshop_log("Attempting payment via {$gateway->get_id()}", 'info', $payment_data);

                $result = $gateway->process_payment($amount, $currency, $customer_data);

                if ($result['success']) {
                    $this->cache_payment_result($reference, $result, $gateway->get_id());
                    $this->trigger_payment_event('payment_processed', $result, $gateway);

                    chatshop_log("Payment successful via {$gateway->get_id()}", 'info');
                    return $result;
                }

                $last_error = $result['message'] ?? __('Payment failed', 'chatshop');
                chatshop_log("Payment failed via {$gateway->get_id()}: {$last_error}", 'warning');
            } catch (\Exception $e) {
                $last_error = $e->getMessage();
                chatshop_log("Payment exception via {$gateway->get_id()}: {$last_error}", 'error');
            }
        }

        $error_message = $last_error ?? __('No available payment gateway', 'chatshop');
        return $this->error_response($error_message);
    }

    /**
     * Verify transaction with caching
     *
     * @param string $transaction_id Transaction ID.
     * @param string $gateway_id     Gateway ID.
     * @return array Verification result.
     * @since 1.0.0
     */
    public function verify_transaction($transaction_id, $gateway_id)
    {
        $transaction_id = sanitize_text_field($transaction_id);
        $cache_key = "verify_{$gateway_id}_{$transaction_id}";

        // Check cache first
        if (isset($this->payment_cache[$cache_key])) {
            return $this->payment_cache[$cache_key];
        }

        $gateway = ChatShop_Payment_Factory::get_gateway($gateway_id);

        if (!$gateway) {
            return $this->error_response(__('Gateway not found', 'chatshop'));
        }

        try {
            $result = $gateway->verify_transaction($transaction_id);

            // Cache successful verifications for 5 minutes
            if ($result['success']) {
                $this->payment_cache[$cache_key] = $result;
                wp_cache_set($cache_key, $result, 'chatshop_payments', 300);
            }

            return $result;
        } catch (\Exception $e) {
            chatshop_log("Transaction verification failed: {$e->getMessage()}", 'error');
            return $this->error_response(__('Verification failed', 'chatshop'));
        }
    }

    /**
     * Process webhook from any gateway
     *
     * @param array  $payload    Webhook payload.
     * @param string $gateway_id Gateway ID.
     * @return bool Success status.
     * @since 1.0.0
     */
    public function process_webhook($payload, $gateway_id)
    {
        $gateway = ChatShop_Payment_Factory::get_gateway($gateway_id);

        if (!$gateway) {
            chatshop_log("Webhook received for unknown gateway: {$gateway_id}", 'warning');
            return false;
        }

        try {
            $result = $gateway->handle_webhook($payload);
            $this->trigger_payment_event('webhook_processed', $payload, $gateway);

            chatshop_log("Webhook processed successfully for {$gateway_id}", 'info');
            return $result;
        } catch (\Exception $e) {
            chatshop_log("Webhook processing failed for {$gateway_id}: {$e->getMessage()}", 'error');
            return false;
        }
    }

    /**
     * Get payment gateways for currency with fallback
     *
     * @param string $currency Currency code.
     * @return array Array of gateway instances.
     * @since 1.0.0
     */
    private function get_payment_gateways_for_currency($currency)
    {
        $gateways = array();

        // Primary gateway first
        $primary = ChatShop_Payment_Factory::get_gateway($this->primary_gateway);
        if ($primary && $primary->is_enabled() && $primary->supports_currency($currency)) {
            $gateways[] = $primary;
        }

        // Add fallback gateways (premium feature)
        if (!empty($this->fallback_gateways)) {
            foreach ($this->fallback_gateways as $gateway_id) {
                $gateway = ChatShop_Payment_Factory::get_gateway($gateway_id);
                if ($gateway && $gateway->is_enabled() && $gateway->supports_currency($currency)) {
                    $gateways[] = $gateway;
                }
            }
        }

        // If no gateways found, try factory best match
        if (empty($gateways)) {
            $best = ChatShop_Payment_Factory::get_best_gateway_for_currency($currency);
            if ($best) {
                $gateways[] = $best;
            }
        }

        return $gateways;
    }

    /**
     * Get best gateway for currency (legacy method)
     *
     * @param string $currency Currency code.
     * @return ChatShop_Payment_Gateway|null
     * @since 1.0.0
     */
    private function get_best_gateway($currency)
    {
        $gateways = $this->get_payment_gateways_for_currency($currency);
        return !empty($gateways) ? $gateways[0] : null;
    }

    /**
     * Handle AJAX payment request
     *
     * @since 1.0.0
     */
    public function handle_payment_ajax()
    {
        // Security check
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'chatshop_payment_nonce')) {
            wp_send_json_error(__('Security check failed', 'chatshop'));
        }

        $amount = floatval($_POST['amount'] ?? 0);
        $currency = sanitize_text_field($_POST['currency'] ?? 'NGN');
        $gateway_id = sanitize_key($_POST['gateway'] ?? '');

        $customer_data = array(
            'email' => sanitize_email($_POST['email'] ?? ''),
            'name'  => sanitize_text_field($_POST['name'] ?? ''),
            'phone' => sanitize_text_field($_POST['phone'] ?? ''),
        );

        $metadata = array(
            'source'    => 'ajax',
            'ip'        => $this->get_client_ip(),
            'user_id'   => get_current_user_id(),
            'timestamp' => current_time('mysql'),
        );

        $result = $this->process_payment($amount, $currency, $customer_data, $gateway_id, $metadata);
        wp_send_json($result);
    }

    /**
     * Handle AJAX verification request
     *
     * @since 1.0.0
     */
    public function handle_verify_ajax()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'chatshop_payment_nonce')) {
            wp_send_json_error(__('Security check failed', 'chatshop'));
        }

        $transaction_id = sanitize_text_field($_POST['transaction_id'] ?? '');
        $gateway_id = sanitize_key($_POST['gateway_id'] ?? '');

        if (empty($transaction_id) || empty($gateway_id)) {
            wp_send_json_error(__('Missing required parameters', 'chatshop'));
        }

        $result = $this->verify_transaction($transaction_id, $gateway_id);
        wp_send_json($result);
    }

    /**
     * Register REST API endpoints
     *
     * @since 1.0.0
     */
    public function register_api_endpoints()
    {
        register_rest_route('chatshop/v1', '/payments/process', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'api_process_payment'),
            'permission_callback' => array($this, 'api_permission_check'),
        ));

        register_rest_route('chatshop/v1', '/payments/webhook/(?P<gateway>[a-zA-Z0-9_-]+)', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'api_handle_webhook'),
            'permission_callback' => '__return_true',
        ));
    }

    /**
     * Get available gateways with metadata
     *
     * @param bool $enabled_only Get only enabled gateways.
     * @return array
     * @since 1.0.0
     */
    public function get_gateways($enabled_only = true)
    {
        $gateways = $enabled_only ?
            ChatShop_Payment_Factory::get_enabled_gateways() :
            ChatShop_Payment_Factory::get_available_gateways(true);

        $gateway_data = array();
        foreach ($gateways as $id => $gateway) {
            $metadata = ChatShop_Payment_Factory::get_gateway_metadata($id);
            $gateway_data[$id] = array(
                'instance' => $gateway,
                'metadata' => $metadata,
                'status'   => $gateway->get_status(),
            );
        }

        return $gateway_data;
    }

    /**
     * Get component status with detailed metrics
     *
     * @return array Component status data.
     * @since 1.0.0
     */
    public function get_status()
    {
        $factory_stats = ChatShop_Payment_Factory::get_stats();
        $gateways = $this->get_gateways(false);

        $gateway_health = array();
        foreach ($gateways as $id => $data) {
            $gateway_health[$id] = array(
                'enabled' => $data['instance']->is_enabled(),
                'test_mode' => $data['instance']->is_test_mode(),
                'premium' => $data['metadata']['is_premium'] ?? false,
            );
        }

        return array(
            'component_id'     => self::COMPONENT_ID,
            'enabled'          => $factory_stats['total_enabled'] > 0,
            'primary_gateway'  => $this->primary_gateway,
            'fallback_gateways' => $this->fallback_gateways,
            'total_gateways'   => $factory_stats['total_registered'],
            'enabled_gateways' => $factory_stats['total_enabled'],
            'premium_gateways' => $factory_stats['premium_gateways'],
            'gateway_health'   => $gateway_health,
            'cache_size'       => count($this->payment_cache),
        );
    }

    /**
     * Validate payment data
     *
     * @param float  $amount        Payment amount.
     * @param string $currency      Currency code.
     * @param array  $customer_data Customer data.
     * @return array Validation result.
     * @since 1.0.0
     */
    private function validate_payment_data($amount, $currency, $customer_data)
    {
        $errors = array();

        if (!is_numeric($amount) || $amount <= 0) {
            $errors[] = __('Invalid payment amount', 'chatshop');
        }

        if (empty($customer_data['email']) || !is_email($customer_data['email'])) {
            $errors[] = __('Valid email required', 'chatshop');
        }

        if (strlen($currency) !== 3) {
            $errors[] = __('Invalid currency code', 'chatshop');
        }

        return array(
            'is_valid' => empty($errors),
            'errors'   => $errors,
        );
    }

    /**
     * Generate payment reference
     *
     * @return string
     * @since 1.0.0
     */
    private function generate_payment_reference()
    {
        return 'cs_' . uniqid() . '_' . wp_generate_uuid4();
    }

    /**
     * Cache payment result
     *
     * @param string $reference  Payment reference.
     * @param array  $result     Payment result.
     * @param string $gateway_id Gateway ID.
     * @since 1.0.0
     */
    private function cache_payment_result($reference, $result, $gateway_id)
    {
        $cache_key = "payment_{$reference}";
        $cache_data = array(
            'result'     => $result,
            'gateway_id' => $gateway_id,
            'timestamp'  => current_time('mysql'),
        );

        $this->payment_cache[$cache_key] = $cache_data;
        wp_cache_set($cache_key, $cache_data, 'chatshop_payments', 3600);
    }

    /**
     * Trigger payment event
     *
     * @param string $event   Event name.
     * @param mixed  $data    Event data.
     * @param object $gateway Gateway instance.
     * @since 1.0.0
     */
    private function trigger_payment_event($event, $data, $gateway)
    {
        do_action("chatshop_payment_{$event}", $data, $gateway, $this);
        do_action("chatshop_payment_{$gateway->get_id()}_{$event}", $data, $gateway, $this);
    }

    /**
     * Get client IP address
     *
     * @return string
     * @since 1.0.0
     */
    private function get_client_ip()
    {
        $ip_fields = array('HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR');

        foreach ($ip_fields as $field) {
            if (!empty($_SERVER[$field])) {
                $ip = sanitize_text_field($_SERVER[$field]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? '');
    }

    /**
     * API permission check
     *
     * @return bool
     * @since 1.0.0
     */
    public function api_permission_check()
    {
        return current_user_can('manage_options');
    }

    /**
     * Handle gateways loaded event
     *
     * @param array $gateways Loaded gateways.
     * @return array
     * @since 1.0.0
     */
    public function on_gateways_loaded($gateways)
    {
        // Perform any post-loading gateway setup
        chatshop_log('Payment gateways loaded: ' . count($gateways), 'debug');
        return $gateways;
    }

    /**
     * Create error response
     *
     * @param string $message Error message.
     * @return array
     * @since 1.0.0
     */
    private function error_response($message)
    {
        return array(
            'success' => false,
            'message' => $message,
            'data'    => null,
        );
    }
}
