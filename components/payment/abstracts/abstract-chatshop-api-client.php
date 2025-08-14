<?php

/**
 * Abstract API Client - COMPLETE VERSION
 *
 * Base class for all API clients providing common functionality
 * for HTTP requests, authentication, and error handling.
 *
 * @package    ChatShop
 * @subpackage ChatShop/components/payment/abstracts
 * @since      1.0.0
 */

namespace ChatShop;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Abstract API Client Class - COMPLETE VERSION
 *
 * @since 1.0.0
 */
abstract class ChatShop_Abstract_API_Client
{
    /**
     * API base URL
     *
     * @since 1.0.0
     * @var string
     */
    protected $api_base_url = '';

    /**
     * API version
     *
     * @since 1.0.0
     * @var string
     */
    protected $api_version = 'v1';

    /**
     * Client identifier
     *
     * @since 1.0.0
     * @var string
     */
    protected $client_id = '';

    /**
     * Request timeout in seconds
     *
     * @since 1.0.0
     * @var int
     */
    protected $timeout = 30;

    /**
     * Default headers for requests
     *
     * @since 1.0.0
     * @var array
     */
    protected $default_headers = array();

    /**
     * Last response data
     *
     * @since 1.0.0
     * @var array
     */
    protected $last_response = array();

    /**
     * Debug mode
     *
     * @since 1.0.0
     * @var bool
     */
    protected $debug = false;

    /**
     * Rate limit settings
     *
     * @since 1.0.0
     * @var array
     */
    protected $rate_limits = array(
        'requests_per_minute' => 60,
        'requests_per_hour' => 1000
    );

    /**
     * Cache duration in seconds
     *
     * @since 1.0.0
     * @var int
     */
    protected $cache_duration = 300; // 5 minutes

    /**
     * Constructor
     *
     * @since 1.0.0
     * @param array $config Configuration options
     */
    public function __construct($config = array())
    {
        $this->debug = defined('WP_DEBUG') && WP_DEBUG;
        $this->client_id = get_class($this);
        $this->setup_default_headers();
        $this->init($config);
    }

    /**
     * Initialize client
     *
     * Override in child classes for specific initialization
     *
     * @since 1.0.0
     * @param array $config Configuration options
     */
    protected function init($config = array())
    {
        // Default implementation - override in child classes
        if (isset($config['timeout'])) {
            $this->timeout = absint($config['timeout']);
        }

        if (isset($config['debug'])) {
            $this->debug = (bool) $config['debug'];
        }
    }

    /**
     * Setup default headers
     *
     * @since 1.0.0
     */
    protected function setup_default_headers()
    {
        $this->default_headers = array(
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'User-Agent' => 'ChatShop/' . (defined('CHATSHOP_VERSION') ? CHATSHOP_VERSION : '1.0.0') . ' (WordPress/' . get_bloginfo('version') . ')'
        );
    }

    /**
     * Make HTTP request
     *
     * @since 1.0.0
     * @param string $method HTTP method
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @param array $headers Additional headers
     * @return array|WP_Error Response data or error
     */
    protected function make_request($method, $endpoint, $data = array(), $headers = array())
    {
        // Check rate limits
        if (!$this->check_rate_limits()) {
            return new \WP_Error('rate_limit', 'Rate limit exceeded');
        }

        // Build URL
        $url = $this->build_url($endpoint);

        // Prepare headers
        $request_headers = array_merge($this->default_headers, $headers);

        // Prepare request arguments
        $args = array(
            'method' => strtoupper($method),
            'timeout' => $this->timeout,
            'headers' => $request_headers,
            'sslverify' => true
        );

        // Add body for POST/PUT requests
        if (in_array(strtoupper($method), array('POST', 'PUT', 'PATCH'), true)) {
            if (!empty($data)) {
                $args['body'] = wp_json_encode($data);
            }
        } elseif (strtoupper($method) === 'GET' && !empty($data)) {
            // Add query parameters for GET requests
            $url = add_query_arg($data, $url);
        }

        // Log request if debug mode
        $this->log_request($url, $method, $data);

        // Make the request
        $response = wp_remote_request($url, $args);

        // Store response for debugging
        $this->last_response = $response;

        // Check for WP errors
        if (is_wp_error($response)) {
            $this->log_error('HTTP request failed: ' . $response->get_error_message());
            return $response;
        }

        // Get response details
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        // Log response if debug mode
        $this->log_response($response_code, $response_body);

        // Update rate limit counters
        $this->update_rate_limit_counters();

        // Handle response
        return $this->handle_response($response_code, $response_body);
    }

    /**
     * Build full URL from endpoint
     *
     * @since 1.0.0
     * @param string $endpoint API endpoint
     * @return string Full URL
     */
    protected function build_url($endpoint)
    {
        $base_url = rtrim($this->api_base_url, '/');
        $endpoint = ltrim($endpoint, '/');

        if (!empty($this->api_version)) {
            return $base_url . '/' . $this->api_version . '/' . $endpoint;
        }

        return $base_url . '/' . $endpoint;
    }

    /**
     * Handle API response
     *
     * @since 1.0.0
     * @param int $response_code HTTP response code
     * @param string $response_body Response body
     * @return array|WP_Error Processed response or error
     */
    protected function handle_response($response_code, $response_body)
    {
        // Handle error status codes
        if ($response_code >= 400) {
            $error_message = $this->extract_error_message($response_body, $response_code);
            $this->log_error("API error {$response_code}: {$error_message}");

            return new \WP_Error(
                'api_error',
                $error_message,
                array('status_code' => $response_code)
            );
        }

        // Parse JSON response
        $decoded_body = json_decode($response_body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log_error('JSON decode error: ' . json_last_error_msg());
            return new \WP_Error('json_error', 'Invalid JSON response: ' . json_last_error_msg());
        }

        // Validate response format
        if (!$this->validate_response($decoded_body)) {
            return new \WP_Error('invalid_response', 'Invalid response format');
        }

        // Transform response if needed
        return $this->transform_response($decoded_body);
    }

    /**
     * Extract error message from response
     *
     * @since 1.0.0
     * @param string $response_body Response body
     * @param int $response_code Response code
     * @return string Error message
     */
    protected function extract_error_message($response_body, $response_code)
    {
        $decoded = json_decode($response_body, true);

        if (is_array($decoded)) {
            // Try common error message fields
            $error_fields = array('message', 'error', 'error_description', 'detail');
            foreach ($error_fields as $field) {
                if (isset($decoded[$field])) {
                    return sanitize_text_field($decoded[$field]);
                }
            }
        }

        // Fallback to HTTP status message
        return "HTTP {$response_code}: " . wp_remote_retrieve_response_message($this->last_response);
    }

    /**
     * Validate response structure
     *
     * Override in child classes for specific validation
     *
     * @since 1.0.0
     * @param array $data Response data
     * @return bool Whether response is valid
     */
    protected function validate_response($data)
    {
        return is_array($data);
    }

    /**
     * Transform response data
     *
     * Override in child classes for specific transformations
     *
     * @since 1.0.0
     * @param array $data Response data
     * @return array Transformed data
     */
    protected function transform_response($data)
    {
        return $data;
    }

    /**
     * Check rate limits
     *
     * @since 1.0.0
     * @return bool Whether request is within rate limits
     */
    protected function check_rate_limits()
    {
        if (empty($this->rate_limits)) {
            return true;
        }

        $current_time = current_time('timestamp');
        $minute_key = "chatshop_api_rate_limit_{$this->client_id}_minute_" . floor($current_time / 60);
        $hour_key = "chatshop_api_rate_limit_{$this->client_id}_hour_" . floor($current_time / 3600);

        $minute_count = get_transient($minute_key) ?: 0;
        $hour_count = get_transient($hour_key) ?: 0;

        if ($minute_count >= $this->rate_limits['requests_per_minute']) {
            return false;
        }

        if ($hour_count >= $this->rate_limits['requests_per_hour']) {
            return false;
        }

        return true;
    }

    /**
     * Update rate limit counters
     *
     * @since 1.0.0
     */
    protected function update_rate_limit_counters()
    {
        if (empty($this->rate_limits)) {
            return;
        }

        $current_time = current_time('timestamp');
        $minute_key = "chatshop_api_rate_limit_{$this->client_id}_minute_" . floor($current_time / 60);
        $hour_key = "chatshop_api_rate_limit_{$this->client_id}_hour_" . floor($current_time / 3600);

        $minute_count = get_transient($minute_key) ?: 0;
        $hour_count = get_transient($hour_key) ?: 0;

        set_transient($minute_key, $minute_count + 1, 60);
        set_transient($hour_key, $hour_count + 1, 3600);
    }

    /**
     * Cache response
     *
     * @since 1.0.0
     * @param string $cache_key Cache key
     * @param mixed $data Data to cache
     * @param int $duration Cache duration in seconds
     */
    protected function cache_response($cache_key, $data, $duration = null)
    {
        if ($duration === null) {
            $duration = $this->cache_duration;
        }

        set_transient($cache_key, $data, $duration);
    }

    /**
     * Get cached response
     *
     * @since 1.0.0
     * @param string $cache_key Cache key
     * @return mixed Cached data or false
     */
    protected function get_cached_response($cache_key)
    {
        return get_transient($cache_key);
    }

    /**
     * Clear cache for pattern
     *
     * @since 1.0.0
     * @param string $pattern Cache key pattern
     */
    protected function clear_cache($pattern = '')
    {
        global $wpdb;

        if (empty($pattern)) {
            $pattern = "chatshop_api_{$this->client_id}_%";
        }

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                "_transient_{$pattern}"
            )
        );

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                "_transient_timeout_{$pattern}"
            )
        );
    }

    /**
     * Health check endpoint
     *
     * Override in child classes to provide specific health check
     *
     * @since 1.0.0
     * @return string Health check endpoint
     */
    protected function get_health_endpoint()
    {
        return 'health';
    }

    /**
     * Test API connection
     *
     * @since 1.0.0
     * @return bool|WP_Error True if connection successful, WP_Error on failure
     */
    public function test_connection()
    {
        $response = $this->make_request('GET', $this->get_health_endpoint());

        if (is_wp_error($response)) {
            return $response;
        }

        return true;
    }

    /**
     * Log API request
     *
     * @since 1.0.0
     * @param string $url Request URL
     * @param string $method HTTP method
     * @param array $data Request data
     */
    protected function log_request($url, $method, $data)
    {
        if (!$this->debug) {
            return;
        }

        if (function_exists('ChatShop\\chatshop_log')) {
            chatshop_log(
                "API Request: {$method} {$url}",
                'debug',
                array(
                    'client' => $this->client_id,
                    'method' => $method,
                    'url' => $url,
                    'data_size' => strlen(wp_json_encode($data))
                )
            );
        }
    }

    /**
     * Log API response
     *
     * @since 1.0.0
     * @param int $response_code HTTP response code
     * @param string $response_body Response body
     */
    protected function log_response($response_code, $response_body)
    {
        if (!$this->debug) {
            return;
        }

        if (function_exists('ChatShop\\chatshop_log')) {
            chatshop_log(
                "API Response: {$response_code}",
                'debug',
                array(
                    'client' => $this->client_id,
                    'response_code' => $response_code,
                    'response_size' => strlen($response_body)
                )
            );
        }
    }

    /**
     * Log error
     *
     * @since 1.0.0
     * @param string $message Error message
     */
    protected function log_error($message)
    {
        if (function_exists('ChatShop\\chatshop_log')) {
            chatshop_log(
                "API Error: {$message}",
                'error',
                array('client' => $this->client_id)
            );
        }
    }

    /**
     * Decrypt API key - FIXED VISIBILITY
     *
     * @since 1.0.0
     * @param string $encrypted_key Encrypted key
     * @return string Decrypted key
     */
    protected function decrypt_key($encrypted_key)
    {
        if (empty($encrypted_key)) {
            return '';
        }

        // Use WordPress salt for encryption key
        $encryption_key = wp_salt('auth');

        try {
            $decrypted = openssl_decrypt(
                $encrypted_key,
                'AES-256-CBC',
                $encryption_key,
                0,
                substr($encryption_key, 0, 16)
            );

            return $decrypted !== false ? $decrypted : $encrypted_key;
        } catch (Exception $e) {
            $this->log_error('Key decryption failed: ' . $e->getMessage());
            return $encrypted_key;
        }
    }

    /**
     * Encrypt API key
     *
     * @since 1.0.0
     * @param string $key Plain key
     * @return string Encrypted key
     */
    protected function encrypt_key($key)
    {
        if (empty($key)) {
            return '';
        }

        // Use WordPress salt for encryption key
        $encryption_key = wp_salt('auth');

        try {
            $encrypted = openssl_encrypt(
                $key,
                'AES-256-CBC',
                $encryption_key,
                0,
                substr($encryption_key, 0, 16)
            );

            return $encrypted !== false ? $encrypted : $key;
        } catch (Exception $e) {
            $this->log_error('Key encryption failed: ' . $e->getMessage());
            return $key;
        }
    }

    /**
     * Get client status
     *
     * @since 1.0.0
     * @return array Client status
     */
    public function get_status()
    {
        return array(
            'client_id' => $this->client_id,
            'api_base_url' => $this->api_base_url,
            'api_version' => $this->api_version,
            'timeout' => $this->timeout,
            'debug' => $this->debug,
            'last_response_code' => isset($this->last_response['response']['code']) ? $this->last_response['response']['code'] : null,
            'rate_limits' => $this->rate_limits
        );
    }

    /**
     * Set debug mode
     *
     * @since 1.0.0
     * @param bool $debug Debug status
     */
    public function set_debug($debug)
    {
        $this->debug = (bool) $debug;
    }

    /**
     * Get last response
     *
     * @since 1.0.0
     * @return array Last response data
     */
    public function get_last_response()
    {
        return $this->last_response;
    }
}
