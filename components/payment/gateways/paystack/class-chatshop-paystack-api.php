<?php

/**
 * Paystack API Client for ChatShop
 *
 * @package ChatShop
 * @subpackage Payment\Gateways\Paystack
 * @since 1.0.0
 */

namespace ChatShop;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ChatShop Paystack API Client Class
 *
 * Handles all Paystack API communications with rate limiting,
 * webhook validation, and multi-currency support.
 *
 * @since 1.0.0
 */
class ChatShop_Paystack_API
{
    /**
     * Paystack API base URL
     *
     * @var string
     * @since 1.0.0
     */
    private $base_url = 'https://api.paystack.co';

    /**
     * API secret key
     *
     * @var string
     * @since 1.0.0
     */
    private $secret_key;

    /**
     * Rate limiting settings
     *
     * @var array
     * @since 1.0.0
     */
    private $rate_limits = array(
        'requests_per_minute' => 60,
        'burst_limit' => 10,
    );

    /**
     * Request timeout in seconds
     *
     * @var int
     * @since 1.0.0
     */
    private $timeout = 30;

    /**
     * Constructor
     *
     * @param string $secret_key Paystack secret key
     * @since 1.0.0
     */
    public function __construct($secret_key)
    {
        $this->secret_key = sanitize_text_field($secret_key);
    }

    /**
     * Initialize transaction
     *
     * @param array $data Transaction data
     * @return array API response
     * @since 1.0.0
     */
    public function initialize_transaction($data)
    {
        $endpoint = '/transaction/initialize';

        // Sanitize transaction data
        $sanitized_data = $this->sanitize_transaction_data($data);

        return $this->make_request('POST', $endpoint, $sanitized_data);
    }

    /**
     * Verify transaction
     *
     * @param string $reference Transaction reference
     * @return array API response
     * @since 1.0.0
     */
    public function verify_transaction($reference)
    {
        $reference = sanitize_text_field($reference);
        $endpoint = "/transaction/verify/{$reference}";

        return $this->make_request('GET', $endpoint);
    }

    /**
     * Create payment request
     *
     * @param array $data Payment request data
     * @return array API response
     * @since 1.0.0
     */
    public function create_payment_request($data)
    {
        $endpoint = '/paymentrequest';

        // Sanitize payment request data
        $sanitized_data = $this->sanitize_payment_request_data($data);

        return $this->make_request('POST', $endpoint, $sanitized_data);
    }

    /**
     * Fetch payment request
     *
     * @param string $id_or_code Payment request ID or code
     * @return array API response
     * @since 1.0.0
     */
    public function fetch_payment_request($id_or_code)
    {
        $id_or_code = sanitize_text_field($id_or_code);
        $endpoint = "/paymentrequest/{$id_or_code}";

        return $this->make_request('GET', $endpoint);
    }

    /**
     * Verify payment request
     *
     * @param string $code Payment request code
     * @return array API response
     * @since 1.0.0
     */
    public function verify_payment_request($code)
    {
        $code = sanitize_text_field($code);
        $endpoint = "/paymentrequest/verify/{$code}";

        return $this->make_request('GET', $endpoint);
    }

    /**
     * Send payment request notification
     *
     * @param string $code Payment request code
     * @return array API response
     * @since 1.0.0
     */
    public function notify_payment_request($code)
    {
        $code = sanitize_text_field($code);
        $endpoint = "/paymentrequest/notify/{$code}";

        return $this->make_request('POST', $endpoint);
    }

    /**
     * List supported banks
     *
     * @param string $currency Currency code
     * @return array API response
     * @since 1.0.0
     */
    public function list_banks($currency = 'NGN')
    {
        $currency = sanitize_text_field($currency);
        $endpoint = "/bank?currency={$currency}";

        return $this->make_request('GET', $endpoint);
    }

    /**
     * Validate webhook signature
     *
     * @param array $payload Webhook payload
     * @return bool Whether signature is valid
     * @since 1.0.0
     */
    public function validate_webhook($payload)
    {
        $signature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';

        if (empty($signature)) {
            return false;
        }

        // Get raw payload
        $raw_payload = file_get_contents('php://input');

        // Compute expected signature
        $expected_signature = hash_hmac('sha512', $raw_payload, $this->secret_key);

        // Compare signatures
        return hash_equals($expected_signature, $signature);
    }

    /**
     * Validate webhook IP
     *
     * @return bool Whether IP is valid
     * @since 1.0.0
     */
    public function validate_webhook_ip()
    {
        $allowed_ips = array(
            '52.31.139.75',
            '52.49.173.169',
            '52.214.14.220',
        );

        $client_ip = $this->get_client_ip();

        return in_array($client_ip, $allowed_ips, true);
    }

    /**
     * Make API request with rate limiting
     *
     * @param string $method HTTP method
     * @param string $endpoint API endpoint
     * @param array  $data Request data
     * @return array API response
     * @since 1.0.0
     */
    private function make_request($method, $endpoint, $data = array())
    {
        try {
            // Check rate limiting
            if (!$this->check_rate_limit()) {
                return $this->error_response(__('Rate limit exceeded. Please try again later.', 'chatshop'));
            }

            // Prepare request
            $url = $this->base_url . $endpoint;
            $args = $this->prepare_request_args($method, $data);

            // Log request (exclude sensitive data)
            $this->log_request($method, $endpoint, $this->sanitize_log_data($data));

            // Make request
            $response = wp_remote_request($url, $args);

            // Handle response
            return $this->handle_response($response, $endpoint);
        } catch (\Exception $e) {
            chatshop_log("Paystack API error for {$endpoint}: " . $e->getMessage(), 'error');
            return $this->error_response(__('API request failed', 'chatshop'));
        }
    }

    /**
     * Prepare request arguments
     *
     * @param string $method HTTP method
     * @param array  $data Request data
     * @return array Request arguments
     * @since 1.0.0
     */
    private function prepare_request_args($method, $data = array())
    {
        $args = array(
            'method' => strtoupper($method),
            'timeout' => $this->timeout,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->secret_key,
                'Content-Type' => 'application/json',
                'User-Agent' => 'ChatShop/' . CHATSHOP_VERSION . ' (WordPress)',
            ),
            'sslverify' => true,
        );

        // Add body for POST/PUT requests
        if (in_array($method, array('POST', 'PUT'), true) && !empty($data)) {
            $args['body'] = wp_json_encode($data);
        }

        return $args;
    }

    /**
     * Handle API response
     *
     * @param array|\WP_Error $response HTTP response
     * @param string         $endpoint API endpoint
     * @return array Processed response
     * @since 1.0.0
     */
    private function handle_response($response, $endpoint)
    {
        // Check for HTTP errors
        if (is_wp_error($response)) {
            chatshop_log("Paystack API HTTP error for {$endpoint}: " . $response->get_error_message(), 'error');
            return $this->error_response(__('Network error occurred', 'chatshop'));
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        // Log response
        $this->log_response($endpoint, $status_code, $body);

        // Parse JSON response
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            chatshop_log("Paystack API JSON error for {$endpoint}: " . json_last_error_msg(), 'error');
            return $this->error_response(__('Invalid API response format', 'chatshop'));
        }

        // Handle different status codes
        if ($status_code >= 200 && $status_code < 300) {
            return array(
                'success' => true,
                'data' => $data['data'] ?? $data,
                'message' => $data['message'] ?? __('Request successful', 'chatshop'),
            );
        }

        // Handle API errors
        $error_message = $data['message'] ?? __('API request failed', 'chatshop');
        chatshop_log("Paystack API error {$status_code} for {$endpoint}: {$error_message}", 'error');

        return $this->error_response($error_message);
    }

    /**
     * Check rate limiting
     *
     * @return bool Whether request is allowed
     * @since 1.0.0
     */
    private function check_rate_limit()
    {
        $transient_key = 'chatshop_paystack_rate_limit_' . md5($this->secret_key);
        $current_count = get_transient($transient_key);

        if ($current_count === false) {
            // First request in this minute
            set_transient($transient_key, 1, 60);
            return true;
        }

        if ($current_count >= $this->rate_limits['requests_per_minute']) {
            return false;
        }

        // Increment counter
        set_transient($transient_key, $current_count + 1, 60);
        return true;
    }

    /**
     * Sanitize transaction data
     *
     * @param array $data Transaction data
     * @return array Sanitized data
     * @since 1.0.0
     */
    private function sanitize_transaction_data($data)
    {
        $sanitized = array();

        // Required fields
        $sanitized['email'] = sanitize_email($data['email']);
        $sanitized['amount'] = (int) $data['amount'];

        // Optional fields
        if (!empty($data['currency'])) {
            $sanitized['currency'] = sanitize_text_field($data['currency']);
        }

        if (!empty($data['reference'])) {
            $sanitized['reference'] = sanitize_text_field($data['reference']);
        }

        if (!empty($data['callback_url'])) {
            $sanitized['callback_url'] = esc_url_raw($data['callback_url']);
        }

        if (!empty($data['metadata']) && is_array($data['metadata'])) {
            $sanitized['metadata'] = $this->sanitize_metadata($data['metadata']);
        }

        if (!empty($data['channels']) && is_array($data['channels'])) {
            $sanitized['channels'] = array_map('sanitize_text_field', $data['channels']);
        }

        return $sanitized;
    }

    /**
     * Sanitize payment request data
     *
     * @param array $data Payment request data
     * @return array Sanitized data
     * @since 1.0.0
     */
    private function sanitize_payment_request_data($data)
    {
        $sanitized = array();

        // Required fields
        if (!empty($data['description'])) {
            $sanitized['description'] = sanitize_textarea_field($data['description']);
        }

        if (!empty($data['amount'])) {
            $sanitized['amount'] = (int) $data['amount'];
        }

        // Optional fields
        if (!empty($data['currency'])) {
            $sanitized['currency'] = sanitize_text_field($data['currency']);
        }

        if (!empty($data['customer'])) {
            $sanitized['customer'] = sanitize_email($data['customer']);
        }

        if (!empty($data['due_date'])) {
            $sanitized['due_date'] = sanitize_text_field($data['due_date']);
        }

        if (!empty($data['line_items']) && is_array($data['line_items'])) {
            $sanitized['line_items'] = $this->sanitize_line_items($data['line_items']);
        }

        if (!empty($data['tax']) && is_array($data['tax'])) {
            $sanitized['tax'] = $this->sanitize_tax_items($data['tax']);
        }

        if (!empty($data['metadata']) && is_array($data['metadata'])) {
            $sanitized['metadata'] = $this->sanitize_metadata($data['metadata']);
        }

        return $sanitized;
    }

    /**
     * Sanitize line items
     *
     * @param array $items Line items
     * @return array Sanitized items
     * @since 1.0.0
     */
    private function sanitize_line_items($items)
    {
        $sanitized = array();

        foreach ($items as $item) {
            if (is_array($item)) {
                $sanitized_item = array();

                if (!empty($item['name'])) {
                    $sanitized_item['name'] = sanitize_text_field($item['name']);
                }

                if (!empty($item['amount'])) {
                    $sanitized_item['amount'] = (int) $item['amount'];
                }

                if (!empty($item['quantity'])) {
                    $sanitized_item['quantity'] = (int) $item['quantity'];
                }

                $sanitized[] = $sanitized_item;
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize tax items
     *
     * @param array $items Tax items
     * @return array Sanitized items
     * @since 1.0.0
     */
    private function sanitize_tax_items($items)
    {
        $sanitized = array();

        foreach ($items as $item) {
            if (is_array($item)) {
                $sanitized_item = array();

                if (!empty($item['name'])) {
                    $sanitized_item['name'] = sanitize_text_field($item['name']);
                }

                if (!empty($item['amount'])) {
                    $sanitized_item['amount'] = (int) $item['amount'];
                }

                $sanitized[] = $sanitized_item;
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize metadata
     *
     * @param array $metadata Metadata array
     * @return array Sanitized metadata
     * @since 1.0.0
     */
    private function sanitize_metadata($metadata)
    {
        $sanitized = array();

        foreach ($metadata as $key => $value) {
            $clean_key = sanitize_key($key);

            if (is_string($value)) {
                $sanitized[$clean_key] = sanitize_text_field($value);
            } elseif (is_numeric($value)) {
                $sanitized[$clean_key] = $value;
            } elseif (is_bool($value)) {
                $sanitized[$clean_key] = $value;
            } elseif (is_array($value)) {
                $sanitized[$clean_key] = $this->sanitize_metadata($value);
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize data for logging (remove sensitive information)
     *
     * @param array $data Data to sanitize
     * @return array Sanitized data
     * @since 1.0.0
     */
    private function sanitize_log_data($data)
    {
        if (!is_array($data)) {
            return $data;
        }

        $sensitive_keys = array('email', 'phone', 'card', 'authorization');
        $sanitized = $data;

        foreach ($sensitive_keys as $key) {
            if (isset($sanitized[$key])) {
                $sanitized[$key] = '[REDACTED]';
            }
        }

        return $sanitized;
    }

    /**
     * Log API request
     *
     * @param string $method HTTP method
     * @param string $endpoint API endpoint
     * @param array  $data Request data (sanitized)
     * @since 1.0.0
     */
    private function log_request($method, $endpoint, $data)
    {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        chatshop_log("Paystack API Request: {$method} {$endpoint} - Data: " . wp_json_encode($data), 'debug');
    }

    /**
     * Log API response
     *
     * @param string $endpoint API endpoint
     * @param int    $status_code HTTP status code
     * @param string $body Response body
     * @since 1.0.0
     */
    private function log_response($endpoint, $status_code, $body)
    {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        // Truncate long responses for logging
        $log_body = strlen($body) > 500 ? substr($body, 0, 500) . '...' : $body;

        chatshop_log("Paystack API Response: {$endpoint} - Status: {$status_code} - Body: {$log_body}", 'debug');
    }

    /**
     * Get client IP address
     *
     * @return string Client IP
     * @since 1.0.0
     */
    private function get_client_ip()
    {
        $ip_headers = array(
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
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

        return $_SERVER['REMOTE_ADDR'] ?? '';
    }

    /**
     * Create error response
     *
     * @param string $message Error message
     * @return array Error response
     * @since 1.0.0
     */
    private function error_response($message)
    {
        return array(
            'success' => false,
            'message' => $message,
            'data' => null,
        );
    }

    /**
     * Get supported currencies with minimum amounts
     *
     * @return array Currency information
     * @since 1.0.0
     */
    public function get_supported_currencies()
    {
        return array(
            'NGN' => array(
                'name' => 'Nigerian Naira',
                'symbol' => '₦',
                'minimum' => 50.00,
                'subunit' => 'kobo',
            ),
            'USD' => array(
                'name' => 'US Dollar',
                'symbol' => '$',
                'minimum' => 2.00,
                'subunit' => 'cent',
            ),
            'GHS' => array(
                'name' => 'Ghanaian Cedi',
                'symbol' => '₵',
                'minimum' => 0.10,
                'subunit' => 'pesewa',
            ),
            'ZAR' => array(
                'name' => 'South African Rand',
                'symbol' => 'R',
                'minimum' => 1.00,
                'subunit' => 'cent',
            ),
            'KES' => array(
                'name' => 'Kenyan Shilling',
                'symbol' => 'Ksh.',
                'minimum' => 3.00,
                'subunit' => 'cent',
            ),
            'XOF' => array(
                'name' => 'West African CFA Franc',
                'symbol' => 'XOF',
                'minimum' => 1.00,
                'subunit' => '',
            ),
        );
    }

    /**
     * Format amount for display
     *
     * @param int    $amount Amount in subunits
     * @param string $currency Currency code
     * @return string Formatted amount
     * @since 1.0.0
     */
    public function format_amount($amount, $currency)
    {
        $currencies = $this->get_supported_currencies();

        if (!isset($currencies[$currency])) {
            return $amount;
        }

        $symbol = $currencies[$currency]['symbol'];
        $formatted_amount = number_format($amount / 100, 2);

        return $symbol . $formatted_amount;
    }

    /**
     * Get API status
     *
     * @return array API status information
     * @since 1.0.0
     */
    public function get_api_status()
    {
        $response = $this->make_request('GET', '/bank');

        return array(
            'connected' => $response['success'],
            'message' => $response['success'] ? __('API connection successful', 'chatshop') : $response['message'],
            'timestamp' => current_time('mysql'),
        );
    }
}
