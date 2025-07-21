<?php

/**
 * Paystack API Client
 *
 * Handles all Paystack API communications with proper error handling,
 * caching, and security measures.
 *
 * @package    ChatShop
 * @subpackage ChatShop/components/payment/gateways/paystack
 * @since      1.0.0
 */

namespace ChatShop;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Paystack API Client Class
 *
 * @since 1.0.0
 */
class ChatShop_Paystack_API extends ChatShop_Abstract_API_Client
{
    /**
     * Paystack API base URL
     *
     * @since 1.0.0
     * @var string
     */
    private $api_base = 'https://api.paystack.co';

    /**
     * Secret key for API authentication
     *
     * @since 1.0.0
     * @var string
     */
    private $secret_key;

    /**
     * Public key for frontend usage
     *
     * @since 1.0.0
     * @var string
     */
    private $public_key;

    /**
     * Test mode flag
     *
     * @since 1.0.0
     * @var bool
     */
    private $test_mode;

    /**
     * Cache timeout in seconds
     *
     * @since 1.0.0
     * @var int
     */
    private $cache_timeout = 300; // 5 minutes

    /**
     * Constructor
     *
     * @since 1.0.0
     * @param array $config Configuration array
     */
    public function __construct($config = array())
    {
        $options = chatshop_get_option('paystack', '', array());

        $this->test_mode = isset($config['test_mode']) ? $config['test_mode'] : (isset($options['test_mode']) ? $options['test_mode'] : true);

        $this->secret_key = $this->get_secret_key($options);
        $this->public_key = $this->get_public_key($options);

        if (empty($this->secret_key)) {
            chatshop_log('Paystack API initialized without secret key', 'warning');
        }
    }

    /**
     * Initialize a transaction
     *
     * @since 1.0.0
     * @param array $data Transaction data
     * @return array|WP_Error API response or error
     */
    public function initialize_transaction($data)
    {
        $endpoint = '/transaction/initialize';

        // Validate required fields
        $required = array('email', 'amount');
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return new \WP_Error(
                    'missing_field',
                    sprintf(__('Required field %s is missing', 'chatshop'), $field)
                );
            }
        }

        // Sanitize and prepare data
        $sanitized_data = $this->sanitize_transaction_data($data);

        // Make API call
        $response = $this->make_request('POST', $endpoint, $sanitized_data);

        if (is_wp_error($response)) {
            chatshop_log('Transaction initialization failed: ' . $response->get_error_message(), 'error');
            return $response;
        }

        // Cache the transaction reference for verification
        if (isset($response['data']['reference'])) {
            $this->cache_transaction_data($response['data']['reference'], $sanitized_data);
        }

        return $response;
    }

    /**
     * Verify a transaction
     *
     * @since 1.0.0
     * @param string $reference Transaction reference
     * @return array|WP_Error API response or error
     */
    public function verify_transaction($reference)
    {
        if (empty($reference)) {
            return new \WP_Error('missing_reference', __('Transaction reference is required', 'chatshop'));
        }

        $reference = sanitize_text_field($reference);
        $endpoint = '/transaction/verify/' . $reference;

        // Check cache first
        $cache_key = 'chatshop_paystack_verify_' . md5($reference);
        $cached_response = get_transient($cache_key);

        if (
            false !== $cached_response && isset($cached_response['data']['status']) &&
            in_array($cached_response['data']['status'], array('success', 'failed', 'abandoned'), true)
        ) {
            return $cached_response;
        }

        $response = $this->make_request('GET', $endpoint);

        if (is_wp_error($response)) {
            chatshop_log('Transaction verification failed for reference: ' . $reference . ' - ' . $response->get_error_message(), 'error');
            return $response;
        }

        // Cache successful responses for a short time
        if (isset($response['data']['status'])) {
            $cache_timeout = in_array($response['data']['status'], array('success', 'failed', 'abandoned'), true)
                ? $this->cache_timeout : 60; // Cache final states longer
            set_transient($cache_key, $response, $cache_timeout);
        }

        return $response;
    }

    /**
     * Create a payment request
     *
     * @since 1.0.0
     * @param array $data Payment request data
     * @return array|WP_Error API response or error
     */
    public function create_payment_request($data)
    {
        $endpoint = '/paymentrequest';

        // Validate required fields
        if (empty($data['customer']) && empty($data['description'])) {
            return new \WP_Error(
                'missing_data',
                __('Customer or description is required for payment request', 'chatshop')
            );
        }

        $sanitized_data = $this->sanitize_payment_request_data($data);

        $response = $this->make_request('POST', $endpoint, $sanitized_data);

        if (is_wp_error($response)) {
            chatshop_log('Payment request creation failed: ' . $response->get_error_message(), 'error');
            return $response;
        }

        return $response;
    }

    /**
     * Get payment request details
     *
     * @since 1.0.0
     * @param string $id_or_code Payment request ID or code
     * @return array|WP_Error API response or error
     */
    public function get_payment_request($id_or_code)
    {
        if (empty($id_or_code)) {
            return new \WP_Error('missing_id', __('Payment request ID or code is required', 'chatshop'));
        }

        $id_or_code = sanitize_text_field($id_or_code);
        $endpoint = '/paymentrequest/' . $id_or_code;

        return $this->make_request('GET', $endpoint);
    }

    /**
     * Verify payment request
     *
     * @since 1.0.0
     * @param string $code Payment request code
     * @return array|WP_Error API response or error
     */
    public function verify_payment_request($code)
    {
        if (empty($code)) {
            return new \WP_Error('missing_code', __('Payment request code is required', 'chatshop'));
        }

        $code = sanitize_text_field($code);
        $endpoint = '/paymentrequest/verify/' . $code;

        return $this->make_request('GET', $endpoint);
    }

    /**
     * Test API connection
     *
     * @since 1.0.0
     * @return array Connection test result
     */
    public function test_connection()
    {
        $response = $this->make_request('GET', '/transaction', array('perPage' => 1));

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }

        return array(
            'success' => true,
            'message' => __('Connection successful', 'chatshop'),
            'mode' => $this->test_mode ? __('Test Mode', 'chatshop') : __('Live Mode', 'chatshop')
        );
    }

    /**
     * Make HTTP request to Paystack API
     *
     * @since 1.0.0
     * @param string $method HTTP method
     * @param string $endpoint API endpoint
     * @param array  $data Request data
     * @return array|WP_Error API response or error
     */
    private function make_request($method, $endpoint, $data = array())
    {
        if (empty($this->secret_key)) {
            return new \WP_Error('no_api_key', __('Paystack API key not configured', 'chatshop'));
        }

        $url = $this->api_base . $endpoint;

        $args = array(
            'method'  => strtoupper($method),
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->secret_key,
                'Content-Type'  => 'application/json',
                'User-Agent'    => 'ChatShop/' . CHATSHOP_VERSION . ' (WordPress/' . get_bloginfo('version') . ')'
            ),
            'timeout' => 30,
            'sslverify' => true
        );

        if (!empty($data) && in_array($method, array('POST', 'PUT', 'PATCH'), true)) {
            $args['body'] = wp_json_encode($data);
        } elseif (!empty($data) && $method === 'GET') {
            $url = add_query_arg($data, $url);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return new \WP_Error('api_error', $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if (empty($response_body)) {
            return new \WP_Error('empty_response', __('Empty response from Paystack API', 'chatshop'));
        }

        $decoded_response = json_decode($response_body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new \WP_Error('invalid_json', __('Invalid JSON response from Paystack API', 'chatshop'));
        }

        // Handle API errors
        if ($response_code >= 400) {
            $error_message = isset($decoded_response['message'])
                ? $decoded_response['message']
                : __('Unknown API error', 'chatshop');

            return new \WP_Error('api_error', $error_message, array('response_code' => $response_code));
        }

        // Log successful requests in debug mode
        if (defined('WP_DEBUG') && WP_DEBUG) {
            chatshop_log("Paystack API {$method} {$endpoint} - Response code: {$response_code}", 'debug');
        }

        return $decoded_response;
    }

    /**
     * Sanitize transaction data
     *
     * @since 1.0.0
     * @param array $data Raw transaction data
     * @return array Sanitized data
     */
    private function sanitize_transaction_data($data)
    {
        $sanitized = array();

        // Required fields
        $sanitized['email'] = sanitize_email($data['email']);
        $sanitized['amount'] = absint($data['amount']); // Amount in kobo/cents

        // Optional fields
        if (!empty($data['reference'])) {
            $sanitized['reference'] = sanitize_text_field($data['reference']);
        }

        if (!empty($data['currency'])) {
            $sanitized['currency'] = sanitize_text_field($data['currency']);
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
     * @since 1.0.0
     * @param array $data Raw payment request data
     * @return array Sanitized data
     */
    private function sanitize_payment_request_data($data)
    {
        $sanitized = array();

        if (!empty($data['customer'])) {
            $sanitized['customer'] = sanitize_text_field($data['customer']);
        }

        if (!empty($data['amount'])) {
            $sanitized['amount'] = absint($data['amount']);
        }

        if (!empty($data['description'])) {
            $sanitized['description'] = sanitize_textarea_field($data['description']);
        }

        if (!empty($data['due_date'])) {
            $sanitized['due_date'] = sanitize_text_field($data['due_date']);
        }

        if (!empty($data['line_items']) && is_array($data['line_items'])) {
            $sanitized['line_items'] = $this->sanitize_line_items($data['line_items']);
        }

        return $sanitized;
    }

    /**
     * Sanitize metadata
     *
     * @since 1.0.0
     * @param array $metadata Raw metadata
     * @return array Sanitized metadata
     */
    private function sanitize_metadata($metadata)
    {
        $sanitized = array();

        foreach ($metadata as $key => $value) {
            $clean_key = sanitize_key($key);

            if (is_array($value)) {
                $sanitized[$clean_key] = $this->sanitize_metadata($value);
            } else {
                $sanitized[$clean_key] = sanitize_text_field($value);
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize line items
     *
     * @since 1.0.0
     * @param array $line_items Raw line items
     * @return array Sanitized line items
     */
    private function sanitize_line_items($line_items)
    {
        $sanitized = array();

        foreach ($line_items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $clean_item = array();

            if (!empty($item['name'])) {
                $clean_item['name'] = sanitize_text_field($item['name']);
            }

            if (!empty($item['amount'])) {
                $clean_item['amount'] = absint($item['amount']);
            }

            if (!empty($item['quantity'])) {
                $clean_item['quantity'] = absint($item['quantity']);
            }

            if (!empty($clean_item)) {
                $sanitized[] = $clean_item;
            }
        }

        return $sanitized;
    }

    /**
     * Get secret key based on mode
     *
     * @since 1.0.0
     * @param array $options Plugin options
     * @return string Decrypted secret key
     */
    private function get_secret_key($options)
    {
        $key = $this->test_mode ? 'test_secret_key' : 'live_secret_key';

        if (empty($options[$key])) {
            return '';
        }

        return $this->decrypt_api_key($options[$key]);
    }

    /**
     * Get public key based on mode
     *
     * @since 1.0.0
     * @param array $options Plugin options
     * @return string Public key
     */
    private function get_public_key($options)
    {
        $key = $this->test_mode ? 'test_public_key' : 'live_public_key';

        return isset($options[$key]) ? sanitize_text_field($options[$key]) : '';
    }

    /**
     * Decrypt API key
     *
     * @since 1.0.0
     * @param string $encrypted_key Encrypted API key
     * @return string Decrypted key
     */
    private function decrypt_api_key($encrypted_key)
    {
        if (empty($encrypted_key)) {
            return '';
        }

        $encryption_key = wp_salt('auth');
        $decrypted = openssl_decrypt($encrypted_key, 'AES-256-CBC', $encryption_key, 0, substr($encryption_key, 0, 16));

        return $decrypted !== false ? $decrypted : '';
    }

    /**
     * Cache transaction data for verification
     *
     * @since 1.0.0
     * @param string $reference Transaction reference
     * @param array  $data Transaction data
     */
    private function cache_transaction_data($reference, $data)
    {
        $cache_key = 'chatshop_paystack_init_' . md5($reference);
        set_transient($cache_key, $data, $this->cache_timeout);
    }

    /**
     * Get public key for frontend
     *
     * @since 1.0.0
     * @return string Public key
     */
    public function get_public_key_for_frontend()
    {
        return $this->public_key;
    }

    /**
     * Check if gateway is in test mode
     *
     * @since 1.0.0
     * @return bool Test mode status
     */
    public function is_test_mode()
    {
        return $this->test_mode;
    }
}
