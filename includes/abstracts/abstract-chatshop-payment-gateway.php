<?php

/**
 * Abstract Payment Gateway
 *
 * Base class for all payment gateways providing common functionality
 * and enforcing implementation of required methods.
 *
 * @package    ChatShop
 * @subpackage ChatShop/includes/abstracts
 * @since      1.0.0
 */

namespace ChatShop;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Abstract Payment Gateway Class
 *
 * @since 1.0.0
 */
abstract class ChatShop_Abstract_Payment_Gateway
{
    /**
     * Gateway ID
     *
     * @since 1.0.0
     * @var string
     */
    protected $id;

    /**
     * Gateway name
     *
     * @since 1.0.0
     * @var string
     */
    protected $name;

    /**
     * Gateway description
     *
     * @since 1.0.0
     * @var string
     */
    protected $description;

    /**
     * Gateway enabled status
     *
     * @since 1.0.0
     * @var bool
     */
    protected $enabled = false;

    /**
     * Supported currencies
     *
     * @since 1.0.0
     * @var array
     */
    protected $supported_currencies = array();

    /**
     * Supported countries
     *
     * @since 1.0.0
     * @var array
     */
    protected $supported_countries = array();

    /**
     * Gateway version
     *
     * @since 1.0.0
     * @var string
     */
    protected $version = '1.0.0';

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        // Allow child classes to initialize
        $this->init();
    }

    /**
     * Initialize gateway
     *
     * Override this method in child classes for custom initialization
     *
     * @since 1.0.0
     */
    protected function init()
    {
        // Default implementation - override in child classes
    }

    /**
     * Process payment
     *
     * @since 1.0.0
     * @param float  $amount Payment amount
     * @param string $currency Currency code
     * @param array  $customer_data Customer information
     * @param array  $options Additional options
     * @return array Payment result
     */
    abstract public function process_payment($amount, $currency, $customer_data, $options = array());

    /**
     * Verify transaction
     *
     * @since 1.0.0
     * @param string $reference Transaction reference
     * @return array Verification result
     */
    abstract public function verify_transaction($reference);

    /**
     * Handle webhook
     *
     * @since 1.0.0
     * @param array $payload Webhook payload
     * @return bool Processing result
     */
    abstract public function handle_webhook($payload);

    /**
     * Generate payment link
     *
     * Optional method - default implementation returns error
     *
     * @since 1.0.0
     * @param float  $amount Payment amount
     * @param string $currency Currency code
     * @param array  $customer_data Customer information
     * @param array  $options Additional options
     * @return array Payment link result
     */
    public function generate_payment_link($amount, $currency, $customer_data, $options = array())
    {
        return $this->error_response(__('Payment link generation not supported by this gateway', 'chatshop'));
    }

    /**
     * Test connection
     *
     * Optional method - default implementation returns success
     *
     * @since 1.0.0
     * @return array Test result
     */
    public function test_connection()
    {
        return array(
            'success' => true,
            'message' => __('Connection test not implemented', 'chatshop')
        );
    }

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
     * Check if gateway is enabled
     *
     * @since 1.0.0
     * @return bool Enabled status
     */
    public function is_enabled()
    {
        return $this->enabled;
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
     * Check if currency is supported
     *
     * @since 1.0.0
     * @param string $currency Currency code
     * @return bool Support status
     */
    public function supports_currency($currency)
    {
        return in_array(strtoupper($currency), $this->supported_currencies, true);
    }

    /**
     * Check if country is supported
     *
     * @since 1.0.0
     * @param string $country Country code
     * @return bool Support status
     */
    public function supports_country($country)
    {
        return in_array(strtoupper($country), $this->supported_countries, true);
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
     * Format amount to gateway requirements
     *
     * Most gateways expect amounts in subunits (cents/kobo)
     *
     * @since 1.0.0
     * @param float  $amount Amount in main units
     * @param string $currency Currency code
     * @return int Amount in subunits
     */
    protected function format_amount($amount, $currency = 'NGN')
    {
        // Special handling for XOF (no subunit but still multiply by 100)
        if (strtoupper($currency) === 'XOF') {
            return intval($amount * 100);
        }

        // Standard conversion to subunits
        return intval($amount * 100);
    }

    /**
     * Format amount from gateway response
     *
     * Convert from subunits to main units
     *
     * @since 1.0.0
     * @param int    $amount Amount in subunits
     * @param string $currency Currency code
     * @return float Amount in main units
     */
    protected function unformat_amount($amount, $currency = 'NGN')
    {
        return floatval($amount / 100);
    }

    /**
     * Validate required fields
     *
     * @since 1.0.0
     * @param array $data Data to validate
     * @param array $required_fields Required field names
     * @return true|WP_Error Validation result
     */
    protected function validate_required_fields($data, $required_fields)
    {
        $missing_fields = array();

        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                $missing_fields[] = $field;
            }
        }

        if (!empty($missing_fields)) {
            return new \WP_Error(
                'missing_fields',
                sprintf(
                    __('Required fields missing: %s', 'chatshop'),
                    implode(', ', $missing_fields)
                )
            );
        }

        return true;
    }

    /**
     * Sanitize customer data
     *
     * @since 1.0.0
     * @param array $customer_data Raw customer data
     * @return array Sanitized customer data
     */
    protected function sanitize_customer_data($customer_data)
    {
        $sanitized = array();

        if (!empty($customer_data['email'])) {
            $sanitized['email'] = sanitize_email($customer_data['email']);
        }

        if (!empty($customer_data['first_name'])) {
            $sanitized['first_name'] = sanitize_text_field($customer_data['first_name']);
        }

        if (!empty($customer_data['last_name'])) {
            $sanitized['last_name'] = sanitize_text_field($customer_data['last_name']);
        }

        if (!empty($customer_data['phone'])) {
            $sanitized['phone'] = sanitize_text_field($customer_data['phone']);
        }

        if (!empty($customer_data['address'])) {
            $sanitized['address'] = sanitize_textarea_field($customer_data['address']);
        }

        if (!empty($customer_data['city'])) {
            $sanitized['city'] = sanitize_text_field($customer_data['city']);
        }

        if (!empty($customer_data['state'])) {
            $sanitized['state'] = sanitize_text_field($customer_data['state']);
        }

        if (!empty($customer_data['country'])) {
            $sanitized['country'] = sanitize_text_field($customer_data['country']);
        }

        if (!empty($customer_data['postal_code'])) {
            $sanitized['postal_code'] = sanitize_text_field($customer_data['postal_code']);
        }

        return $sanitized;
    }

    /**
     * Generate transaction reference
     *
     * @since 1.0.0
     * @param string $prefix Optional prefix
     * @return string Transaction reference
     */
    protected function generate_reference($prefix = '')
    {
        if (empty($prefix)) {
            $prefix = $this->id . '_';
        }

        $timestamp = time();
        $random = wp_generate_password(8, false);

        return $prefix . $timestamp . '_' . $random;
    }

    /**
     * Log gateway activity
     *
     * @since 1.0.0
     * @param string $message Log message
     * @param string $level Log level
     * @param array  $context Additional context
     */
    protected function log($message, $level = 'info', $context = array())
    {
        $log_message = sprintf('[%s] %s', strtoupper($this->id), $message);

        if (!empty($context)) {
            $log_message .= ' Context: ' . wp_json_encode($context);
        }

        chatshop_log($log_message, $level);
    }

    /**
     * Create success response
     *
     * @since 1.0.0
     * @param array  $data Response data
     * @param string $message Success message
     * @return array Success response
     */
    protected function success_response($data, $message = '')
    {
        if (empty($message)) {
            $message = __('Operation completed successfully', 'chatshop');
        }

        return array(
            'success' => true,
            'message' => $message,
            'data' => $data,
            'gateway' => $this->id
        );
    }

    /**
     * Create error response
     *
     * @since 1.0.0
     * @param string $message Error message
     * @param string $code Error code
     * @param array  $data Additional data
     * @return array Error response
     */
    protected function error_response($message, $code = 'gateway_error', $data = null)
    {
        // Log the error
        $this->log('Error: ' . $message, 'error');

        return array(
            'success' => false,
            'message' => $message,
            'error_code' => $code,
            'data' => $data,
            'gateway' => $this->id
        );
    }

    /**
     * Make HTTP request
     *
     * Helper method for making HTTP requests with common settings
     *
     * @since 1.0.0
     * @param string $url Request URL
     * @param array  $args Request arguments
     * @return array|WP_Error Response or error
     */
    protected function make_http_request($url, $args = array())
    {
        $default_args = array(
            'timeout' => 30,
            'sslverify' => true,
            'user-agent' => 'ChatShop/' . CHATSHOP_VERSION . ' (WordPress/' . get_bloginfo('version') . ')'
        );

        $args = wp_parse_args($args, $default_args);

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        return array(
            'code' => $response_code,
            'body' => $response_body,
            'response' => $response
        );
    }

    /**
     * Parse JSON response
     *
     * @since 1.0.0
     * @param string $json_string JSON string
     * @return array|WP_Error Parsed data or error
     */
    protected function parse_json_response($json_string)
    {
        if (empty($json_string)) {
            return new \WP_Error('empty_response', __('Empty response received', 'chatshop'));
        }

        $decoded = json_decode($json_string, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new \WP_Error(
                'json_decode_error',
                sprintf(__('JSON decode error: %s', 'chatshop'), json_last_error_msg())
            );
        }

        return $decoded;
    }

    /**
     * Get current timestamp
     *
     * @since 1.0.0
     * @return string Current timestamp in MySQL format
     */
    protected function get_current_timestamp()
    {
        return current_time('mysql');
    }

    /**
     * Get client IP address
     *
     * @since 1.0.0
     * @return string Client IP address
     */
    protected function get_client_ip()
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
     * Encrypt sensitive data
     *
     * @since 1.0.0
     * @param string $data Data to encrypt
     * @return string Encrypted data
     */
    protected function encrypt_data($data)
    {
        if (empty($data)) {
            return '';
        }

        $key = wp_salt('auth');
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, substr($key, 0, 16));

        return $encrypted !== false ? $encrypted : '';
    }

    /**
     * Decrypt sensitive data
     *
     * @since 1.0.0
     * @param string $encrypted_data Encrypted data
     * @return string Decrypted data
     */
    protected function decrypt_data($encrypted_data)
    {
        if (empty($encrypted_data)) {
            return '';
        }

        $key = wp_salt('auth');
        $decrypted = openssl_decrypt($encrypted_data, 'AES-256-CBC', $key, 0, substr($key, 0, 16));

        return $decrypted !== false ? $decrypted : '';
    }

    /**
     * Rate limit check
     *
     * Simple rate limiting implementation
     *
     * @since 1.0.0
     * @param string $key Rate limit key
     * @param int    $limit Number of requests allowed
     * @param int    $window Time window in seconds
     * @return bool Whether request is allowed
     */
    protected function check_rate_limit($key, $limit = 100, $window = 3600)
    {
        $cache_key = 'chatshop_rate_limit_' . md5($key);
        $requests = get_transient($cache_key);

        if ($requests === false) {
            set_transient($cache_key, 1, $window);
            return true;
        }

        if ($requests >= $limit) {
            return false;
        }

        set_transient($cache_key, $requests + 1, $window);
        return true;
    }
}
