<?php

/**
 * Abstract Payment Gateway Class
 *
 * Base class for all payment gateway implementations.
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
 * Abstract ChatShop Payment Gateway Class
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
     * Gateway version
     *
     * @var string
     * @since 1.0.0
     */
    protected $version = '1.0.0';

    /**
     * Supported features
     *
     * @var array
     * @since 1.0.0
     */
    protected $supports = array();

    /**
     * Supported countries
     *
     * @var array
     * @since 1.0.0
     */
    protected $supported_countries = array();

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
     * Test mode flag
     *
     * @var bool
     * @since 1.0.0
     */
    protected $test_mode = false;

    /**
     * Gateway enabled flag
     *
     * @var bool
     * @since 1.0.0
     */
    protected $enabled = false;

    /**
     * API endpoint URLs
     *
     * @var array
     * @since 1.0.0
     */
    protected $api_endpoints = array();

    /**
     * Constructor
     *
     * @since 1.0.0
     * @param string $gateway_id Gateway identifier
     */
    public function __construct($gateway_id = '')
    {
        if (!empty($gateway_id)) {
            $this->id = $gateway_id;
        }

        $this->init();
        $this->load_settings();
    }

    /**
     * Initialize gateway
     *
     * @since 1.0.0
     */
    protected function init()
    {
        // Override in child classes
    }

    /**
     * Load gateway settings
     *
     * @since 1.0.0
     */
    protected function load_settings()
    {
        $this->settings = get_option("chatshop_{$this->id}_settings", array());
        $this->enabled = $this->get_setting('enabled', false);
        $this->test_mode = $this->get_setting('test_mode', false);
    }

    /**
     * Process payment
     *
     * @since 1.0.0
     * @param float $amount Payment amount
     * @param string $currency Currency code
     * @param array $customer_data Customer information
     * @return array Payment result
     */
    abstract public function process_payment($amount, $currency, $customer_data);

    /**
     * Verify transaction
     *
     * @since 1.0.0
     * @param string $transaction_id Transaction identifier
     * @return array Verification result
     */
    abstract public function verify_transaction($transaction_id);

    /**
     * Handle webhook
     *
     * @since 1.0.0
     * @param array $payload Webhook payload
     * @return bool True on success, false on failure
     */
    abstract public function handle_webhook($payload);

    /**
     * Get gateway ID
     *
     * @since 1.0.0
     * @return string Gateway ID
     */
    public function get_id()
    {
        return $this->id;
    }

    /**
     * Get gateway name
     *
     * @since 1.0.0
     * @return string Gateway name
     */
    public function get_name()
    {
        return $this->name;
    }

    /**
     * Get gateway description
     *
     * @since 1.0.0
     * @return string Gateway description
     */
    public function get_description()
    {
        return $this->description;
    }

    /**
     * Get gateway version
     *
     * @since 1.0.0
     * @return string Gateway version
     */
    public function get_version()
    {
        return $this->version;
    }

    /**
     * Check if gateway supports a feature
     *
     * @since 1.0.0
     * @param string $feature Feature to check
     * @return bool True if supported, false otherwise
     */
    public function supports($feature)
    {
        return in_array($feature, $this->supports);
    }

    /**
     * Get supported features
     *
     * @since 1.0.0
     * @return array Supported features
     */
    public function get_supported_features()
    {
        return $this->supports;
    }

    /**
     * Check if gateway supports country
     *
     * @since 1.0.0
     * @param string $country_code Country code
     * @return bool True if supported, false otherwise
     */
    public function supports_country($country_code)
    {
        return empty($this->supported_countries) || in_array($country_code, $this->supported_countries);
    }

    /**
     * Check if gateway supports currency
     *
     * @since 1.0.0
     * @param string $currency_code Currency code
     * @return bool True if supported, false otherwise
     */
    public function supports_currency($currency_code)
    {
        return empty($this->supported_currencies) || in_array($currency_code, $this->supported_currencies);
    }

    /**
     * Get supported countries
     *
     * @since 1.0.0
     * @return array Supported countries
     */
    public function get_supported_countries()
    {
        return $this->supported_countries;
    }

    /**
     * Get supported currencies
     *
     * @since 1.0.0
     * @return array Supported currencies
     */
    public function get_supported_currencies()
    {
        return $this->supported_currencies;
    }

    /**
     * Check if gateway is enabled
     *
     * @since 1.0.0
     * @return bool True if enabled, false otherwise
     */
    public function is_enabled()
    {
        return $this->enabled;
    }

    /**
     * Check if in test mode
     *
     * @since 1.0.0
     * @return bool True if in test mode, false otherwise
     */
    public function is_test_mode()
    {
        return $this->test_mode;
    }

    /**
     * Get setting value
     *
     * @since 1.0.0
     * @param string $key Setting key
     * @param mixed $default Default value
     * @return mixed Setting value
     */
    public function get_setting($key, $default = null)
    {
        return isset($this->settings[$key]) ? $this->settings[$key] : $default;
    }

    /**
     * Update setting value
     *
     * @since 1.0.0
     * @param string $key Setting key
     * @param mixed $value Setting value
     * @return bool True on success, false on failure
     */
    public function update_setting($key, $value)
    {
        $this->settings[$key] = $value;
        return update_option("chatshop_{$this->id}_settings", $this->settings);
    }

    /**
     * Get all settings
     *
     * @since 1.0.0
     * @return array All settings
     */
    public function get_settings()
    {
        return $this->settings;
    }

    /**
     * Update multiple settings
     *
     * @since 1.0.0
     * @param array $settings Settings array
     * @return bool True on success, false on failure
     */
    public function update_settings($settings)
    {
        $this->settings = array_merge($this->settings, $settings);
        return update_option("chatshop_{$this->id}_settings", $this->settings);
    }

    /**
     * Get API endpoint URL
     *
     * @since 1.0.0
     * @param string $endpoint Endpoint name
     * @return string|false Endpoint URL or false if not found
     */
    protected function get_api_endpoint($endpoint)
    {
        return isset($this->api_endpoints[$endpoint]) ? $this->api_endpoints[$endpoint] : false;
    }

    /**
     * Make API request
     *
     * @since 1.0.0
     * @param string $endpoint Endpoint name or URL
     * @param array $data Request data
     * @param string $method HTTP method
     * @param array $headers Additional headers
     * @return array|false Response data or false on failure
     */
    protected function make_api_request($endpoint, $data = array(), $method = 'POST', $headers = array())
    {
        // Get full URL
        $url = $this->get_api_endpoint($endpoint);
        if (!$url) {
            $url = $endpoint; // Assume it's a full URL
        }

        // Prepare request args
        $args = array(
            'method' => $method,
            'headers' => array_merge($this->get_default_headers(), $headers),
            'timeout' => 30
        );

        // Add body for POST/PUT requests
        if (in_array($method, array('POST', 'PUT', 'PATCH'))) {
            $args['body'] = $this->format_request_data($data);
        } else if (!empty($data)) {
            $url = add_query_arg($data, $url);
        }

        // Make request
        $response = wp_remote_request($url, $args);

        // Handle response
        if (is_wp_error($response)) {
            $this->log_error('API request failed: ' . $response->get_error_message());
            return false;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        // Log request for debugging
        $this->log_api_request($url, $args, $status_code, $body);

        // Parse response
        $parsed_response = $this->parse_api_response($body, $status_code);

        return $parsed_response;
    }

    /**
     * Get default API headers
     *
     * @since 1.0.0
     * @return array Default headers
     */
    protected function get_default_headers()
    {
        return array(
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'User-Agent' => 'ChatShop/' . CHATSHOP_VERSION
        );
    }

    /**
     * Format request data
     *
     * @since 1.0.0
     * @param array $data Request data
     * @return string Formatted data
     */
    protected function format_request_data($data)
    {
        return wp_json_encode($data);
    }

    /**
     * Parse API response
     *
     * @since 1.0.0
     * @param string $body Response body
     * @param int $status_code HTTP status code
     * @return array|false Parsed response or false on failure
     */
    protected function parse_api_response($body, $status_code)
    {
        // Try to decode JSON
        $decoded = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log_error('Invalid JSON response: ' . json_last_error_msg());
            return false;
        }

        return array(
            'status_code' => $status_code,
            'data' => $decoded,
            'success' => $status_code >= 200 && $status_code < 300
        );
    }

    /**
     * Generate transaction reference
     *
     * @since 1.0.0
     * @return string Transaction reference
     */
    protected function generate_reference()
    {
        return strtoupper($this->id) . '_' . time() . '_' . wp_rand(1000, 9999);
    }

    /**
     * Validate webhook signature
     *
     * @since 1.0.0
     * @param array $payload Webhook payload
     * @param string $signature Webhook signature
     * @return bool True if valid, false otherwise
     */
    protected function validate_webhook_signature($payload, $signature)
    {
        // Override in child classes for specific validation
        return true;
    }

    /**
     * Get webhook URL
     *
     * @since 1.0.0
     * @return string Webhook URL
     */
    public function get_webhook_url()
    {
        return add_query_arg(array(
            'action' => 'chatshop_payment_webhook',
            'gateway' => $this->id
        ), admin_url('admin-ajax.php'));
    }

    /**
     * Format amount for API
     *
     * @since 1.0.0
     * @param float $amount Amount
     * @param string $currency Currency code
     * @return int|float Formatted amount
     */
    protected function format_amount($amount, $currency)
    {
        // Most APIs expect amounts in the smallest currency unit (e.g., kobo for NGN)
        $multipliers = array(
            'NGN' => 100, // kobo
            'USD' => 100, // cents
            'EUR' => 100, // cents
            'GBP' => 100, // pence
        );

        $multiplier = isset($multipliers[$currency]) ? $multipliers[$currency] : 100;
        return intval($amount * $multiplier);
    }

    /**
     * Create success response
     *
     * @since 1.0.0
     * @param array $data Response data
     * @return array Success response
     */
    protected function success_response($data = array())
    {
        return array_merge(array(
            'success' => true,
            'error' => false
        ), $data);
    }

    /**
     * Create error response
     *
     * @since 1.0.0
     * @param string $message Error message
     * @param array $data Additional data
     * @return array Error response
     */
    protected function error_response($message, $data = array())
    {
        return array_merge(array(
            'success' => false,
            'error' => true,
            'message' => $message
        ), $data);
    }

    /**
     * Log API request for debugging
     *
     * @since 1.0.0
     * @param string $url Request URL
     * @param array $args Request arguments
     * @param int $status_code Response status code
     * @param string $body Response body
     */
    protected function log_api_request($url, $args, $status_code, $body)
    {
        if (!$this->test_mode && !WP_DEBUG) {
            return;
        }

        $log_data = array(
            'gateway' => $this->id,
            'url' => $url,
            'method' => $args['method'],
            'status_code' => $status_code,
            'response_length' => strlen($body)
        );

        $this->log_info('API request: ' . wp_json_encode($log_data));
    }

    /**
     * Log error message
     *
     * @since 1.0.0
     * @param string $message Error message
     */
    protected function log_error($message)
    {
        if (function_exists('chatshop_log')) {
            chatshop_log("[{$this->id}] {$message}", 'error');
        } else {
            error_log("ChatShop Gateway [{$this->id}]: {$message}");
        }
    }

    /**
     * Log info message
     *
     * @since 1.0.0
     * @param string $message Info message
     */
    protected function log_info($message)
    {
        if (function_exists('chatshop_log')) {
            chatshop_log("[{$this->id}] {$message}", 'info');
        } else {
            error_log("ChatShop Gateway [{$this->id}]: {$message}");
        }
    }

    /**
     * Get gateway configuration for registration
     *
     * @since 1.0.0
     * @return array Gateway configuration
     */
    public function get_gateway_config()
    {
        return array(
            'class_name' => get_class($this),
            'name' => $this->name,
            'description' => $this->description,
            'version' => $this->version,
            'supports' => $this->supports,
            'countries' => $this->supported_countries,
            'currencies' => $this->supported_currencies,
            'enabled' => $this->enabled
        );
    }

    /**
     * Get admin settings fields
     *
     * @since 1.0.0
     * @return array Settings fields
     */
    public function get_admin_settings_fields()
    {
        return array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'chatshop'),
                'type' => 'checkbox',
                'description' => sprintf(__('Enable %s', 'chatshop'), $this->name),
                'default' => 'no'
            ),
            'test_mode' => array(
                'title' => __('Test Mode', 'chatshop'),
                'type' => 'checkbox',
                'description' => __('Enable test mode for development', 'chatshop'),
                'default' => 'yes'
            )
        );
    }
}
