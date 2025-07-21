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
     * Make GET request
     *
     * @since 1.0.0
     * @param string $endpoint API endpoint
     * @param array  $params Query parameters
     * @param array  $headers Additional headers
     * @return array|WP_Error Response or error
     */
    protected function get($endpoint, $params = array(), $headers = array())
    {
        return $this->request('GET', $endpoint, $params, $headers);
    }

    /**
     * Make POST request
     *
     * @since 1.0.0
     * @param string $endpoint API endpoint
     * @param array  $data Request body data
     * @param array  $headers Additional headers
     * @return array|WP_Error Response or error
     */
    protected function post($endpoint, $data = array(), $headers = array())
    {
        return $this->request('POST', $endpoint, $data, $headers);
    }

    /**
     * Make PUT request
     *
     * @since 1.0.0
     * @param string $endpoint API endpoint
     * @param array  $data Request body data
     * @param array  $headers Additional headers
     * @return array|WP_Error Response or error
     */
    protected function put($endpoint, $data = array(), $headers = array())
    {
        return $this->request('PUT', $endpoint, $data, $headers);
    }

    /**
     * Make DELETE request
     *
     * @since 1.0.0
     * @param string $endpoint API endpoint
     * @param array  $params Query parameters
     * @param array  $headers Additional headers
     * @return array|WP_Error Response or error
     */
    protected function delete($endpoint, $params = array(), $headers = array())
    {
        return $this->request('DELETE', $endpoint, $params, $headers);
    }

    /**
     * Make HTTP request
     *
     * @since 1.0.0
     * @param string $method HTTP method
     * @param string $endpoint API endpoint
     * @param array  $data Request data
     * @param array  $headers Additional headers
     * @return array|WP_Error Response or error
     */
    protected function request($method, $endpoint, $data = array(), $headers = array())
    {
        $url = $this->build_url($endpoint);
        $args = $this->build_request_args($method, $data, $headers);

        // Log request in debug mode
        if ($this->debug) {
            $this->log_request($method, $url, $args);
        }

        $response = wp_remote_request($url, $args);

        // Store last response
        $this->last_response = $response;

        if (is_wp_error($response)) {
            $this->log_error('HTTP request failed', $response->get_error_message());
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        // Log response in debug mode
        if ($this->debug) {
            $this->log_response($response_code, $response_body);
        }

        // Handle HTTP errors
        if ($response_code >= 400) {
            return $this->handle_http_error($response_code, $response_body);
        }

        // Parse response
        $parsed_response = $this->parse_response($response_body);

        if (is_wp_error($parsed_response)) {
            return $parsed_response;
        }

        return $parsed_response;
    }

    /**
     * Build request URL
     *
     * @since 1.0.0
     * @param string $endpoint API endpoint
     * @return string Full URL
     */
    protected function build_url($endpoint)
    {
        $url = rtrim($this->api_base_url, '/');

        if (!empty($this->api_version)) {
            $url .= '/' . trim($this->api_version, '/');
        }

        $url .= '/' . ltrim($endpoint, '/');

        return $url;
    }

    /**
     * Build request arguments
     *
     * @since 1.0.0
     * @param string $method HTTP method
     * @param array  $data Request data
     * @param array  $headers Additional headers
     * @return array Request arguments
     */
    protected function build_request_args($method, $data, $headers)
    {
        $method = strtoupper($method);

        $args = array(
            'method' => $method,
            'timeout' => $this->timeout,
            'sslverify' => true,
            'headers' => array_merge($this->default_headers, $headers)
        );

        // Handle request body
        if (!empty($data)) {
            if (in_array($method, array('POST', 'PUT', 'PATCH'), true)) {
                $args['body'] = wp_json_encode($data);
            } elseif ($method === 'GET') {
                // Add query parameters to URL for GET requests
                // This will be handled in the request method
            }
        }

        return $args;
    }

    /**
     * Parse API response
     *
     * @since 1.0.0
     * @param string $response_body Response body
     * @return array|WP_Error Parsed response or error
     */
    protected function parse_response($response_body)
    {
        if (empty($response_body)) {
            return new \WP_Error('empty_response', __('Empty response received from API', 'chatshop'));
        }

        $decoded = json_decode($response_body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new \WP_Error(
                'json_decode_error',
                sprintf(__('Failed to decode JSON response: %s', 'chatshop'), json_last_error_msg())
            );
        }

        return $decoded;
    }

    /**
     * Handle HTTP errors
     *
     * @since 1.0.0
     * @param int    $response_code HTTP response code
     * @param string $response_body Response body
     * @return WP_Error Error object
     */
    protected function handle_http_error($response_code, $response_body)
    {
        $error_message = $this->extract_error_message($response_body);

        if (empty($error_message)) {
            $error_message = sprintf(__('HTTP %d error occurred', 'chatshop'), $response_code);
        }

        $error_code = $this->get_error_code_from_http_status($response_code);

        $this->log_error('HTTP error', $error_message, array(
            'response_code' => $response_code,
            'response_body' => $response_body
        ));

        return new \WP_Error($error_code, $error_message, array(
            'response_code' => $response_code,
            'response_body' => $response_body
        ));
    }

    /**
     * Extract error message from response body
     *
     * @since 1.0.0
     * @param string $response_body Response body
     * @return string Error message
     */
    protected function extract_error_message($response_body)
    {
        $decoded = json_decode($response_body, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            // Common error message fields
            $error_fields = array('message', 'error', 'error_description', 'detail');

            foreach ($error_fields as $field) {
                if (!empty($decoded[$field])) {
                    return sanitize_text_field($decoded[$field]);
                }
            }
        }

        return '';
    }

    /**
     * Get error code from HTTP status
     *
     * @since 1.0.0
     * @param int $status_code HTTP status code
     * @return string Error code
     */
    protected function get_error_code_from_http_status($status_code)
    {
        $error_codes = array(
            400 => 'bad_request',
            401 => 'unauthorized',
            403 => 'forbidden',
            404 => 'not_found',
            405 => 'method_not_allowed',
            409 => 'conflict',
            422 => 'unprocessable_entity',
            429 => 'too_many_requests',
            500 => 'internal_server_error',
            502 => 'bad_gateway',
            503 => 'service_unavailable',
            504 => 'gateway_timeout'
        );

        return isset($error_codes[$status_code]) ? $error_codes[$status_code] : 'api_error';
    }

    /**
     * Set authentication header
     *
     * @since 1.0.0
     * @param string $type Authentication type (Bearer, Basic, etc.)
     * @param string $credentials Authentication credentials
     */
    protected function set_auth_header($type, $credentials)
    {
        $this->default_headers['Authorization'] = $type . ' ' . $credentials;
    }

    /**
     * Set API key header
     *
     * @since 1.0.0
     * @param string $header_name Header name
     * @param string $api_key API key
     */
    protected function set_api_key_header($header_name, $api_key)
    {
        $this->default_headers[$header_name] = $api_key;
    }

    /**
     * Add custom header
     *
     * @since 1.0.0
     * @param string $name Header name
     * @param string $value Header value
     */
    protected function add_header($name, $value)
    {
        $this->default_headers[$name] = $value;
    }

    /**
     * Remove header
     *
     * @since 1.0.0
     * @param string $name Header name
     */
    protected function remove_header($name)
    {
        unset($this->default_headers[$name]);
    }

    /**
     * Set request timeout
     *
     * @since 1.0.0
     * @param int $timeout Timeout in seconds
     */
    public function set_timeout($timeout)
    {
        $this->timeout = absint($timeout);
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
     * Get last response code
     *
     * @since 1.0.0
     * @return int|null Last response code
     */
    public function get_last_response_code()
    {
        if (empty($this->last_response)) {
            return null;
        }

        return wp_remote_retrieve_response_code($this->last_response);
    }

    /**
     * Get last response body
     *
     * @since 1.0.0
     * @return string|null Last response body
     */
    public function get_last_response_body()
    {
        if (empty($this->last_response)) {
            return null;
        }

        return wp_remote_retrieve_body($this->last_response);
    }

    /**
     * Log request in debug mode
     *
     * @since 1.0.0
     * @param string $method HTTP method
     * @param string $url Request URL
     * @param array  $args Request arguments
     */
    protected function log_request($method, $url, $args)
    {
        $log_data = array(
            'method' => $method,
            'url' => $url,
            'headers' => isset($args['headers']) ? $args['headers'] : array(),
            'body' => isset($args['body']) ? $args['body'] : ''
        );

        chatshop_log('API Request: ' . wp_json_encode($log_data), 'debug');
    }

    /**
     * Log response in debug mode
     *
     * @since 1.0.0
     * @param int    $response_code Response code
     * @param string $response_body Response body
     */
    protected function log_response($response_code, $response_body)
    {
        $log_data = array(
            'response_code' => $response_code,
            'response_body' => $response_body
        );

        chatshop_log('API Response: ' . wp_json_encode($log_data), 'debug');
    }

    /**
     * Log error
     *
     * @since 1.0.0
     * @param string $title Error title
     * @param string $message Error message
     * @param array  $context Additional context
     */
    protected function log_error($title, $message, $context = array())
    {
        $log_message = $title . ': ' . $message;

        if (!empty($context)) {
            $log_message .= ' Context: ' . wp_json_encode($context);
        }

        chatshop_log($log_message, 'error');
    }

    /**
     * Enable debug mode
     *
     * @since 1.0.0
     */
    public function enable_debug()
    {
        $this->debug = true;
    }

    /**
     * Disable debug mode
     *
     * @since 1.0.0
     */
    public function disable_debug()
    {
        $this->debug = false;
    }

    /**
     * Check if debug mode is enabled
     *
     * @since 1.0.0
     * @return bool Debug status
     */
    public function is_debug_enabled()
    {
        return $this->debug;
    }

    /**
     * Validate SSL certificate
     *
     * @since 1.0.0
     * @param bool $verify Whether to verify SSL
     */
    public function set_ssl_verify($verify)
    {
        $this->ssl_verify = (bool) $verify;
    }

    /**
     * Set user agent
     *
     * @since 1.0.0
     * @param string $user_agent User agent string
     */
    public function set_user_agent($user_agent)
    {
        $this->default_headers['User-Agent'] = sanitize_text_field($user_agent);
    }

    /**
     * Reset headers to defaults
     *
     * @since 1.0.0
     */
    public function reset_headers()
    {
        $this->setup_default_headers();
    }

    /**
     * Get current headers
     *
     * @since 1.0.0
     * @return array Current headers
     */
    public function get_headers()
    {
        return $this->default_headers;
    }

    /**
     * Validate URL
     *
     * @since 1.0.0
     * @param string $url URL to validate
     * @return bool Validation result
     */
    protected function validate_url($url)
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Sanitize endpoint
     *
     * @since 1.0.0
     * @param string $endpoint API endpoint
     * @return string Sanitized endpoint
     */
    protected function sanitize_endpoint($endpoint)
    {
        return sanitize_text_field(trim($endpoint, '/'));
    }

    /**
     * Handle rate limiting
     *
     * Basic rate limiting implementation
     *
     * @since 1.0.0
     * @param string $key Rate limit key
     * @param int    $limit Requests per window
     * @param int    $window Time window in seconds
     * @return bool Whether request is allowed
     */
    protected function check_rate_limit($key, $limit = 100, $window = 3600)
    {
        $cache_key = 'chatshop_api_rate_limit_' . md5($key);
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

    /**
     * Cache response
     *
     * @since 1.0.0
     * @param string $key Cache key
     * @param mixed  $data Data to cache
     * @param int    $expiration Cache expiration in seconds
     */
    protected function cache_response($key, $data, $expiration = 300)
    {
        $cache_key = 'chatshop_api_cache_' . md5($key);
        set_transient($cache_key, $data, $expiration);
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
     * Clear cached response
     *
     * @since 1.0.0
     * @param string $key Cache key
     */
    protected function clear_cached_response($key)
    {
        $cache_key = 'chatshop_api_cache_' . md5($key);
        delete_transient($cache_key);
    }

    /**
     * Get API base URL
     *
     * @since 1.0.0
     * @return string API base URL
     */
    public function get_api_base_url()
    {
        return $this->api_base_url;
    }

    /**
     * Set API base URL
     *
     * @since 1.0.0
     * @param string $url API base URL
     */
    public function set_api_base_url($url)
    {
        $this->api_base_url = esc_url_raw($url);
    }

    /**
     * Get API version
     *
     * @since 1.0.0
     * @return string API version
     */
    public function get_api_version()
    {
        return $this->api_version;
    }

    /**
     * Set API version
     *
     * @since 1.0.0
     * @param string $version API version
     */
    public function set_api_version($version)
    {
        $this->api_version = sanitize_text_field($version);
    }
}
