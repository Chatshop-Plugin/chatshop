<?php

/**
 * Abstract API Client
 *
 * File: components/payment/abstracts/abstract-chatshop-api-client.php
 * 
 * Base class for all API clients providing common functionality
 * for HTTP requests, authentication, caching, and error handling.
 *
 * @package ChatShop
 * @subpackage Abstracts
 * @since 1.0.0
 */

namespace ChatShop;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Abstract ChatShop API Client Class
 *
 * Provides base functionality for all API clients including
 * HTTP requests, authentication, rate limiting, and caching.
 *
 * @since 1.0.0
 */
abstract class ChatShop_Abstract_API_Client
{
    /**
     * API base URL
     *
     * @var string
     * @since 1.0.0
     */
    protected $api_base_url = '';

    /**
     * API version
     *
     * @var string
     * @since 1.0.0
     */
    protected $api_version = 'v1';

    /**
     * Request timeout in seconds
     *
     * @var int
     * @since 1.0.0
     */
    protected $timeout = 30;

    /**
     * Default headers for requests
     *
     * @var array
     * @since 1.0.0
     */
    protected $default_headers = array();

    /**
     * Last response data
     *
     * @var array
     * @since 1.0.0
     */
    protected $last_response = array();

    /**
     * Debug mode
     *
     * @var bool
     * @since 1.0.0
     */
    protected $debug = false;

    /**
     * Rate limit settings
     *
     * @var array
     * @since 1.0.0
     */
    protected $rate_limits = array(
        'requests_per_minute' => 60,
        'requests_per_hour' => 1000
    );

    /**
     * Cache duration in seconds
     *
     * @var int
     * @since 1.0.0
     */
    protected $cache_duration = 300; // 5 minutes

    /**
     * API client identifier
     *
     * @var string
     * @since 1.0.0
     */
    protected $client_id = '';

    /**
     * Constructor
     *
     * @since 1.0.0
     * @param array $config Configuration options
     */
    public function __construct($config = array())
    {
        $this->debug = defined('WP_DEBUG') && WP_DEBUG;
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
            $this->timeout = intval($config['timeout']);
        }

        if (isset($config['debug'])) {
            $this->debug = (bool) $config['debug'];
        }

        if (isset($config['cache_duration'])) {
            $this->cache_duration = intval($config['cache_duration']);
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
            'User-Agent' => 'ChatShop/' . CHATSHOP_VERSION . ' (WordPress/' . get_bloginfo('version') . ')',
            'X-Requested-With' => 'XMLHttpRequest'
        );
    }

    /**
     * Make HTTP request
     *
     * @since 1.0.0
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @param string $method HTTP method
     * @param array $headers Additional headers
     * @return array|WP_Error Response data or error
     */
    public function request($endpoint, $data = array(), $method = 'GET', $headers = array())
    {
        // Check rate limits
        if (!$this->check_rate_limits()) {
            return new \WP_Error('rate_limit_exceeded', 'API rate limit exceeded');
        }

        // Build URL
        $url = $this->build_url($endpoint);
        if (!$url) {
            return new \WP_Error('invalid_url', 'Invalid API URL');
        }

        // Prepare headers
        $request_headers = wp_parse_args($headers, $this->default_headers);

        // Prepare request arguments
        $args = array(
            'method' => strtoupper($method),
            'headers' => $request_headers,
            'timeout' => $this->timeout,
            'sslverify' => !$this->is_test_mode(),
            'user-agent' => $this->default_headers['User-Agent']
        );

        // Add body for POST/PUT/PATCH requests
        if (in_array($method, array('POST', 'PUT', 'PATCH')) && !empty($data)) {
            if (isset($request_headers['Content-Type']) && $request_headers['Content-Type'] === 'application/json') {
                $args['body'] = wp_json_encode($data);
            } else {
                $args['body'] = $data;
            }
        }

        // Check cache for GET requests
        if ($method === 'GET' && $this->cache_duration > 0) {
            $cache_key = $this->get_cache_key($url, $data);
            $cached_response = $this->get_cached_response($cache_key);
            if ($cached_response !== false) {
                return $cached_response;
            }
        }

        // Log request
        $this->log_request($url, $method, $data);

        // Make request
        $response = wp_remote_request($url, $args);

        // Handle response
        if (is_wp_error($response)) {
            $this->log_error('Request failed: ' . $response->get_error_message());
            return $response;
        }

        // Process response
        $processed_response = $this->process_response($response);

        // Cache successful GET responses
        if ($method === 'GET' && $this->cache_duration > 0 && !is_wp_error($processed_response)) {
            $this->cache_response($cache_key, $processed_response);
        }

        return $processed_response;
    }

    /**
     * Build API URL
     *
     * @since 1.0.0
     * @param string $endpoint API endpoint
     * @return string|false Complete URL or false on error
     */
    protected function build_url($endpoint)
    {
        if (empty($this->api_base_url)) {
            return false;
        }

        $base_url = trailingslashit($this->api_base_url);

        // Add API version if specified
        if (!empty($this->api_version)) {
            $base_url .= trailingslashit($this->api_version);
        }

        return $base_url . ltrim($endpoint, '/');
    }

    /**
     * Process HTTP response
     *
     * @since 1.0.0
     * @param array $response HTTP response
     * @return array|WP_Error Processed response or error
     */
    protected function process_response($response)
    {
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        // Store last response
        $this->last_response = array(
            'code' => $response_code,
            'body' => $response_body,
            'headers' => wp_remote_retrieve_headers($response)
        );

        // Log response
        $this->log_response($response_code, $response_body);

        // Handle HTTP errors
        if ($response_code >= 400) {
            $error_message = $this->extract_error_message($response_body, $response_code);
            return new \WP_Error('api_error', $error_message, array('response_code' => $response_code));
        }

        // Decode JSON response
        $decoded_body = json_decode($response_body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new \WP_Error('json_decode_error', 'Invalid JSON response: ' . json_last_error_msg());
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

        // Check limits
        if (isset($this->rate_limits['requests_per_minute']) && $minute_count >= $this->rate_limits['requests_per_minute']) {
            return false;
        }

        if (isset($this->rate_limits['requests_per_hour']) && $hour_count >= $this->rate_limits['requests_per_hour']) {
            return false;
        }

        // Increment counters
        set_transient($minute_key, $minute_count + 1, 60);
        set_transient($hour_key, $hour_count + 1, 3600);

        return true;
    }

    /**
     * Get cache key for request
     *
     * @since 1.0.0
     * @param string $url Request URL
     * @param array $data Request data
     * @return string Cache key
     */
    protected function get_cache_key($url, $data = array())
    {
        $key_data = array(
            'url' => $url,
            'data' => $data,
            'client' => $this->client_id
        );

        return 'chatshop_api_cache_' . md5(wp_json_encode($key_data));
    }

    /**
     * Get cached response
     *
     * @since 1.0.0
     * @param string $cache_key Cache key
     * @return array|false Cached response or false if not found
     */
    protected function get_cached_response($cache_key)
    {
        return get_transient($cache_key);
    }

    /**
     * Cache response
     *
     * @since 1.0.0
     * @param string $cache_key Cache key
     * @param array $response Response data
     */
    protected function cache_response($cache_key, $response)
    {
        set_transient($cache_key, $response, $this->cache_duration);
    }

    /**
     * Clear cache for specific pattern
     *
     * @since 1.0.0
     * @param string $pattern Cache key pattern
     */
    protected function clear_cache($pattern = '')
    {
        global $wpdb;

        if (empty($pattern)) {
            $pattern = "chatshop_api_cache_{$this->client_id}_%";
        }

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $pattern
            )
        );
    }

    /**
     * Check if in test mode
     *
     * @since 1.0.0
     * @return bool Whether in test mode
     */
    protected function is_test_mode()
    {
        // Override in child classes
        return defined('WP_DEBUG') && WP_DEBUG;
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
        $response = $this->request($this->get_health_endpoint(), array(), 'GET');

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
     * Decrypt API key
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
            'last_response_code' => $this->last_response['code'] ?? null,
            'cache_duration' => $this->cache_duration
        );
    }
}
