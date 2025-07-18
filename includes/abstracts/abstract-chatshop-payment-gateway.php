<?php

/**
 * Abstract Payment Gateway Class
 *
 * Base class for all payment gateways in ChatShop plugin.
 * Implements strategy pattern for payment processing and observer pattern for events.
 *
 * @package    ChatShop
 * @subpackage Abstracts
 * @since      1.0.0
 * @author     Modewebhost
 */

namespace ChatShop;

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Abstract Payment Gateway Class
 *
 * Provides a standardized interface for all payment gateways with event handling,
 * logging, and security features built-in.
 *
 * @since 1.0.0
 */
abstract class ChatShop_Payment_Gateway
{
    /**
     * Gateway ID
     *
     * @var string
     * @since 1.0.0
     */
    protected $id;

    /**
     * Gateway name
     *
     * @var string
     * @since 1.0.0
     */
    protected $name;

    /**
     * Gateway description
     *
     * @var string
     * @since 1.0.0
     */
    protected $description;

    /**
     * Whether the gateway is enabled
     *
     * @var bool
     * @since 1.0.0
     */
    protected $enabled = false;

    /**
     * Test mode flag
     *
     * @var bool
     * @since 1.0.0
     */
    protected $test_mode = true;

    /**
     * Supported currencies
     *
     * @var array
     * @since 1.0.0
     */
    protected $supported_currencies = array();

    /**
     * Gateway settings
     *
     * @var array
     * @since 1.0.0
     */
    protected $settings = array();

    /**
     * Event observers
     *
     * @var array
     * @since 1.0.0
     */
    private $observers = array();

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        $this->init_settings();
        $this->init_hooks();
    }

    /**
     * Initialize gateway settings
     *
     * @since 1.0.0
     */
    protected function init_settings()
    {
        $this->settings = chatshop_get_option('payment', $this->id . '_settings', array());
        $this->enabled = isset($this->settings['enabled']) ? (bool) $this->settings['enabled'] : false;
        $this->test_mode = isset($this->settings['test_mode']) ? (bool) $this->settings['test_mode'] : true;
    }

    /**
     * Initialize hooks
     *
     * @since 1.0.0
     */
    protected function init_hooks()
    {
        add_action('chatshop_payment_gateway_' . $this->id . '_init', array($this, 'init_gateway'));
        add_filter('chatshop_payment_gateways', array($this, 'register_gateway'));
    }

    /**
     * Register this gateway with the payment system
     *
     * @param array $gateways Existing gateways.
     * @return array
     * @since 1.0.0
     */
    public function register_gateway($gateways)
    {
        $gateways[$this->id] = $this;
        return $gateways;
    }

    /**
     * Initialize the gateway
     *
     * Override in child classes for gateway-specific initialization.
     *
     * @since 1.0.0
     */
    public function init_gateway()
    {
        // Override in child classes
    }

    /**
     * Process payment (Strategy Pattern)
     *
     * @param float  $amount        Payment amount.
     * @param string $currency      Currency code.
     * @param array  $customer_data Customer information.
     * @return array Payment result with status and data.
     * @since 1.0.0
     */
    abstract public function process_payment($amount, $currency, $customer_data);

    /**
     * Verify transaction status
     *
     * @param string $transaction_id Transaction ID to verify.
     * @return array Verification result with status and data.
     * @since 1.0.0
     */
    abstract public function verify_transaction($transaction_id);

    /**
     * Handle webhook notifications
     *
     * @param array $payload Webhook payload data.
     * @return bool True on successful handling, false otherwise.
     * @since 1.0.0
     */
    abstract public function handle_webhook($payload);

    /**
     * Get gateway ID
     *
     * @return string
     * @since 1.0.0
     */
    public function get_id()
    {
        return $this->id;
    }

    /**
     * Get gateway name
     *
     * @return string
     * @since 1.0.0
     */
    public function get_name()
    {
        return $this->name;
    }

    /**
     * Get gateway description
     *
     * @return string
     * @since 1.0.0
     */
    public function get_description()
    {
        return $this->description;
    }

    /**
     * Check if gateway is enabled
     *
     * @return bool
     * @since 1.0.0
     */
    public function is_enabled()
    {
        return $this->enabled;
    }

    /**
     * Check if gateway is in test mode
     *
     * @return bool
     * @since 1.0.0
     */
    public function is_test_mode()
    {
        return $this->test_mode;
    }

    /**
     * Get supported currencies
     *
     * @return array
     * @since 1.0.0
     */
    public function get_supported_currencies()
    {
        return $this->supported_currencies;
    }

    /**
     * Check if currency is supported
     *
     * @param string $currency Currency code.
     * @return bool
     * @since 1.0.0
     */
    public function supports_currency($currency)
    {
        return in_array(strtoupper($currency), $this->supported_currencies, true);
    }

    /**
     * Get gateway setting
     *
     * @param string $key     Setting key.
     * @param mixed  $default Default value.
     * @return mixed
     * @since 1.0.0
     */
    public function get_setting($key, $default = null)
    {
        return isset($this->settings[$key]) ? $this->settings[$key] : $default;
    }

    /**
     * Update gateway setting
     *
     * @param string $key   Setting key.
     * @param mixed  $value Setting value.
     * @return bool
     * @since 1.0.0
     */
    public function update_setting($key, $value)
    {
        $this->settings[$key] = $value;
        return chatshop_update_option('payment', $this->id . '_settings', $this->settings);
    }

    /**
     * Add event observer (Observer Pattern)
     *
     * @param string   $event    Event name.
     * @param callable $callback Observer callback.
     * @param int      $priority Priority (default 10).
     * @since 1.0.0
     */
    public function add_observer($event, $callback, $priority = 10)
    {
        if (!isset($this->observers[$event])) {
            $this->observers[$event] = array();
        }

        $this->observers[$event][] = array(
            'callback' => $callback,
            'priority' => $priority,
        );

        // Sort by priority
        usort($this->observers[$event], function ($a, $b) {
            return $a['priority'] <=> $b['priority'];
        });
    }

    /**
     * Trigger event to observers
     *
     * @param string $event Event name.
     * @param mixed  $data  Event data.
     * @since 1.0.0
     */
    protected function trigger_event($event, $data = null)
    {
        if (!isset($this->observers[$event])) {
            return;
        }

        foreach ($this->observers[$event] as $observer) {
            if (is_callable($observer['callback'])) {
                call_user_func($observer['callback'], $data, $this);
            }
        }

        // WordPress action hook for external observers
        do_action('chatshop_payment_gateway_' . $event, $data, $this);
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
    protected function validate_payment_data($amount, $currency, $customer_data)
    {
        $errors = array();

        // Validate amount
        if (!is_numeric($amount) || $amount <= 0) {
            $errors[] = __('Invalid payment amount.', 'chatshop');
        }

        // Validate currency
        if (!$this->supports_currency($currency)) {
            $errors[] = sprintf(__('Currency %s is not supported.', 'chatshop'), $currency);
        }

        // Validate customer data
        if (empty($customer_data['email']) || !is_email($customer_data['email'])) {
            $errors[] = __('Valid customer email is required.', 'chatshop');
        }

        return array(
            'is_valid' => empty($errors),
            'errors'   => $errors,
        );
    }

    /**
     * Sanitize transaction ID
     *
     * @param string $transaction_id Transaction ID.
     * @return string
     * @since 1.0.0
     */
    protected function sanitize_transaction_id($transaction_id)
    {
        return sanitize_text_field($transaction_id);
    }

    /**
     * Generate payment reference
     *
     * @param string $prefix Optional prefix.
     * @return string
     * @since 1.0.0
     */
    protected function generate_payment_reference($prefix = '')
    {
        $prefix = !empty($prefix) ? $prefix . '_' : 'cs_';
        return $prefix . $this->id . '_' . wp_generate_uuid4();
    }

    /**
     * Log gateway activity
     *
     * @param string $message Log message.
     * @param string $level   Log level (error, warning, info, debug).
     * @param array  $context Additional context data.
     * @since 1.0.0
     */
    protected function log($message, $level = 'info', $context = array())
    {
        $log_message = sprintf('[%s Gateway] %s', $this->name, $message);

        if (!empty($context)) {
            $log_message .= ' Context: ' . wp_json_encode($context);
        }

        chatshop_log($log_message, $level);
    }

    /**
     * Format amount for API
     *
     * @param float  $amount   Amount to format.
     * @param string $currency Currency code.
     * @return float
     * @since 1.0.0
     */
    protected function format_amount($amount, $currency)
    {
        // Most gateways expect amounts in the smallest currency unit (e.g., kobo for NGN)
        $decimals = $this->get_currency_decimals($currency);
        return round($amount * pow(10, $decimals));
    }

    /**
     * Get number of decimals for currency
     *
     * @param string $currency Currency code.
     * @return int
     * @since 1.0.0
     */
    protected function get_currency_decimals($currency)
    {
        $zero_decimal_currencies = array('JPY', 'KRW', 'CLP', 'ISK');
        return in_array(strtoupper($currency), $zero_decimal_currencies, true) ? 0 : 2;
    }

    /**
     * Make HTTP request with error handling
     *
     * @param string $url     Request URL.
     * @param array  $args    Request arguments.
     * @param bool   $log_request Whether to log the request.
     * @return array|WP_Error
     * @since 1.0.0
     */
    protected function make_request($url, $args = array(), $log_request = true)
    {
        // Set default timeout and user agent
        $args = wp_parse_args($args, array(
            'timeout'    => 30,
            'user-agent' => 'ChatShop/' . CHATSHOP_VERSION . ' (WordPress)',
            'sslverify'  => !$this->test_mode,
        ));

        if ($log_request) {
            $this->log("Making request to: {$url}", 'debug', array(
                'args' => wp_array_slice_assoc($args, array('method', 'headers')),
            ));
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            $this->log("Request failed: {$response->get_error_message()}", 'error');
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code >= 400) {
            $this->log("Request returned error status: {$status_code}", 'error', array(
                'body' => wp_remote_retrieve_body($response),
            ));
        }

        return $response;
    }

    /**
     * Test gateway connection
     *
     * Override in child classes to implement gateway-specific connection testing.
     *
     * @return array Test result with status and message.
     * @since 1.0.0
     */
    public function test_connection()
    {
        return array(
            'success' => false,
            'message' => __('Connection test not implemented for this gateway.', 'chatshop'),
        );
    }

    /**
     * Get gateway status
     *
     * @return array Gateway status information.
     * @since 1.0.0
     */
    public function get_status()
    {
        return array(
            'id'          => $this->id,
            'name'        => $this->name,
            'enabled'     => $this->enabled,
            'test_mode'   => $this->test_mode,
            'currencies'  => $this->supported_currencies,
            'last_test'   => $this->get_setting('last_connection_test'),
        );
    }
}
