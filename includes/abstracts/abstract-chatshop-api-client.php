<?php

/**
 * Abstract API Client
 *
 * Base class for all API clients providing common functionality
 * for HTTP requests, authentication, and error handling.
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
 * Abstract API Client Class
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
            'User-Agent' => 'ChatShop/' . CHATSHOP_VERSION . ' (WordPress/' . get_bloginfo('version') . ')'
        );
    }

    /**
     * Make HTTP request
     *
     * @since 1.0.0
     * @param string $method HTTP method (GET, POST, PUT, DELETE)
     * @param string $endpoint API endpoint
     * @param array  $data Request data
     * @param array  $headers Additional headers
     * @return array|WP_Error Response data or error
     */
    protected function make_request($method, $endpoint, $data = array(), $headers = array())
    {
        // Check rate limits
        if (!$this->check_rate_limits()) {
            return new \WP_Error('rate_limit_exceeded', __('API rate limit exceeded. Please try again later.', 'chatshop'));
        }

        // Build URL
        $url = $this->build_url($endpoint);

        // Prepare headers
        $request_headers = array_merge($this->default_headers, $headers);

        // Prepare arguments
        $args = array(
            'method' => strtoupper($method),
            'headers' => $request_headers,
            'timeout' => $this->timeout,
            'user-agent' => $request_headers['User-Agent']
        );

        // Add body for POST/PUT requests
        if (in_array(strtoupper($method), array('POST', 'PUT', 'PATCH')) && !empty($data)) {
            $args['body'] = wp_json_encode($data);
        } elseif (strtoupper($method) === 'GET' && !empty($data)) {
            // Add query parameters for GET requests
            $url = add_query_arg($data, $url);
        }

        // Log request in debug mode
        if ($this->debug) {
            chatshop_log("API Request: {$method} {$url}", 'debug', array(
                'headers' => $request_headers,
                'body' => $args['body'] ?? null
            ));
        }

        // Make request
        $response = wp_remote_request($url, $args);

        // Store last response
        $this->last_response = $response;

        // Update rate limit counters
        $this->update_rate_limit_counters();

        // Handle response
        return $this->handle_response($response);
    }

    /**
     * Handle API response
     *
     * @since 1.0.0
     * @param array|WP_Error $response WordPress HTTP response
     * @return array|WP_Error Processed response
     */
    protected function handle_response($response)
    {
        if (is_wp_error($response)) {
            chatshop_log('API Request Error: ' . $response->get_error_message(), 'error');
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        // Log response in debug mode
        if ($this->debug) {
            chatshop_log("API Response: Status {$status_code}", 'debug', array(
                'body' => $body
            ));
        }

        // Parse JSON response
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new \WP_Error('json_decode_error', __('Invalid JSON response from API', 'chatshop'));
        }

        // Handle HTTP errors
        if ($status_code >= 400) {
            $error_message = $this->get_error_message($data, $status_code);
            return new \WP_Error('api_error', $error_message, array('status_code' => $status_code, 'data' => $data));
        }

        return $data;
    }

    /**
     * Build API URL
     *
     * @since 1.0.0
     * @param string $endpoint API endpoint
     * @return string Complete URL
     */
    protected function build_url($endpoint)
    {
        $url = rtrim($this->api_base_url, '/');

        if (!empty($this->api_version)) {
            $url .= '/' . ltrim($this->api_version, '/');
        }

        $url .= '/' . ltrim($endpoint, '/');

        return $url;
    }

    /**
     * Get error message from response
     *
     * @since 1.0.0
     * @param array $data Response data
     * @param int   $status_code HTTP status code
     * @return string Error message
     */
    protected function get_error_message($data, $status_code)
    {
        // Try to extract error message from response
        $message = '';

        if (isset($data['message'])) {
            $message = $data['message'];
        } elseif (isset($data['error'])) {
            $message = is_array($data['error']) ? ($data['error']['message'] ?? 'Unknown error') : $data['error'];
        } elseif (isset($data['errors'])) {
            $errors = is_array($data['errors']) ? $data['errors'] : array($data['errors']);
            $message = implode(', ', $errors);
        } else {
            $message = sprintf(__('HTTP Error %d', 'chatshop'), $status_code);
        }

        return $message;
    }

    /**
     * Check rate limits
     *
     * @since 1.0.0
     * @return bool Whether request is allowed
     */
    protected function check_rate_limits()
    {
        $cache_key = 'chatshop_api_rate_limit_' . $this->get_client_identifier();

        $current_counts = get_transient($cache_key);

        if ($current_counts === false) {
            $current_counts = array(
                'minute' => 0,
                'hour' => 0,
                'minute_start' => time(),
                'hour_start' => time()
            );
        }

        $now = time();

        // Reset minute counter if needed
        if ($now - $current_counts['minute_start'] >= 60) {
            $current_counts['minute'] = 0;
            $current_counts['minute_start'] = $now;
        }

        // Reset hour counter if needed
        if ($now - $current_counts['hour_start'] >= 3600) {
            $current_counts['hour'] = 0;
            $current_counts['hour_start'] = $now;
        }

        // Check limits
        if ($current_counts['minute'] >= $this->rate_limits['requests_per_minute']) {
            chatshop_log('API rate limit exceeded: requests per minute', 'warning');
            return false;
        }

        if ($current_counts['hour'] >= $this->rate_limits['requests_per_hour']) {
            chatshop_log('API rate limit exceeded: requests per hour', 'warning');
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
        $cache_key = 'chatshop_api_rate_limit_' . $this->get_client_identifier();

        $current_counts = get_transient($cache_key);

        if ($current_counts === false) {
            $current_counts = array(
                'minute' => 0,
                'hour' => 0,
                'minute_start' => time(),
                'hour_start' => time()
            );
        }

        $current_counts['minute']++;
        $current_counts['hour']++;

        set_transient($cache_key, $current_counts, 3600); // Cache for 1 hour
    }

    /**
     * Get client identifier for rate limiting
     *
     * @since 1.0.0
     * @return string Client identifier
     */
    protected function get_client_identifier()
    {
        return md5(get_class($this) . get_site_url());
    }

    /**
     * Cache API response
     *
     * @since 1.0.0
     * @param string $key Cache key
     * @param mixed  $data Data to cache
     * @param int    $duration Cache duration in seconds
     */
    protected function cache_response($key, $data, $duration = null)
    {
        if ($duration === null) {
            $duration = $this->cache_duration;
        }

        $cache_key = 'chatshop_api_cache_' . md5($key);
        set_transient($cache_key, $data, $duration);
    }

    /**
     * Get cached response
     *
     * @since 1.0.0
     * @param string $key Cache key
     * @return mixed|false Cached data or false if not found
     */
    protected function get_cached_response($key)
    {
        $cache_key = 'chatshop_api_cache_' . md5($key);
        return get_transient($cache_key);
    }

    /**
     * Clear cached responses
     *
     * @since 1.0.0
     * @param string $pattern Optional pattern to match cache keys
     */
    protected function clear_cache($pattern = null)
    {
        global $wpdb;

        if ($pattern) {
            $like_pattern = 'chatshop_api_cache_' . $pattern . '%';
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $like_pattern
            ));
        } else {
            // Clear all API cache
            $wpdb->query(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'chatshop_api_cache_%'"
            );
        }
    }

    /**
     * Get last response
     *
     * @since 1.0.0
     * @return array Last HTTP response
     */
    public function get_last_response()
    {
        return $this->last_response;
    }

    /**
     * Set debug mode
     *
     * @since 1.0.0
     * @param bool $debug Debug mode
     */
    public function set_debug($debug)
    {
        $this->debug = (bool) $debug;
    }

    /**
     * Set timeout
     *
     * @since 1.0.0
     * @param int $timeout Timeout in seconds
     */
    public function set_timeout($timeout)
    {
        $this->timeout = absint($timeout);
    }

    /**
     * Set rate limits
     *
     * @since 1.0.0
     * @param array $limits Rate limit settings
     */
    public function set_rate_limits($limits)
    {
        $this->rate_limits = array_merge($this->rate_limits, $limits);
    }

    /**
     * Validate API response structure
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
     * @param array $data Raw response data
     * @return array Transformed data
     */
    protected function transform_response($data)
    {
        return $data;
    }

    /**
     * Get API health status
     *
     * @since 1.0.0
     * @return array Health status information
     */
    public function get_health_status()
    {
        $health_endpoint = $this->get_health_endpoint();

        if (empty($health_endpoint)) {
            return array(
                'status' => 'unknown',
                'message' => __('Health check not implemented', 'chatshop')
            );
        }

        $response = $this->make_request('GET', $health_endpoint);

        if (is_wp_error($response)) {
            return array(
                'status' => 'down',
                'message' => $response->get_error_message()
            );
        }

        return array(
            'status' => 'up',
            'message' => __('API is healthy', 'chatshop'),
            'response_time' => $this->get_last_response_time()
        );
    }

    /**
     * Get health check endpoint
     *
     * Override in child classes
     *
     * @since 1.0.0
     * @return string Health endpoint
     */
    protected function get_health_endpoint()
    {
        return '';
    }

    /**
     * Get last response time
     *
     * @since 1.0.0
     * @return float Response time in seconds
     */
    protected function get_last_response_time()
    {
        if (empty($this->last_response)) {
            return 0;
        }

        $headers = wp_remote_retrieve_headers($this->last_response);
        return isset($headers['x-response-time']) ? floatval($headers['x-response-time']) : 0;
    }

    /**
     * Log API activity
     *
     * @since 1.0.0
     * @param string $message Log message
     * @param string $level Log level
     * @param array  $context Additional context
     */
    protected function log($message, $level = 'info', $context = array())
    {
        $context['api_client'] = get_class($this);
        chatshop_log($message, $level, $context);
    }
}
