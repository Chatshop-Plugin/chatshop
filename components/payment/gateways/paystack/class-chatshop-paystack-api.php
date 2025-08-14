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
    protected $api_base_url = 'https://api.paystack.co';

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
     * Initialize client with Paystack-specific settings
     *
     * @since 1.0.0
     * @param array $config Configuration array
     */
    protected function init($config = array())
    {
        parent::init($config);

        $options = chatshop_get_option('paystack', '', array());

        $this->test_mode = isset($config['test_mode']) ? $config['test_mode'] : (isset($options['test_mode']) ? $options['test_mode'] : true);

        $this->secret_key = $this->get_secret_key($options);
        $this->public_key = $this->get_public_key_from_options($options);

        // Set Paystack-specific headers
        if (!empty($this->secret_key)) {
            $this->default_headers['Authorization'] = 'Bearer ' . $this->secret_key;
        }

        if (empty($this->secret_key)) {
            if (function_exists('ChatShop\\chatshop_log')) {
                chatshop_log('Paystack API initialized without secret key', 'warning');
            }
        }
    }

    /**
     * Get secret key based on mode
     *
     * @since 1.0.0
     * @param array $options Plugin options
     * @return string Secret key
     */
    private function get_secret_key($options)
    {
        if ($this->test_mode) {
            return isset($options['test_secret_key']) ? $this->decrypt_key($options['test_secret_key']) : '';
        } else {
            return isset($options['live_secret_key']) ? $this->decrypt_key($options['live_secret_key']) : '';
        }
    }

    /**
     * Get public key based on mode from options
     *
     * @since 1.0.0
     * @param array $options Plugin options
     * @return string Public key
     */
    private function get_public_key_from_options($options)
    {
        if ($this->test_mode) {
            return isset($options['test_public_key']) ? $options['test_public_key'] : '';
        } else {
            return isset($options['live_public_key']) ? $options['live_public_key'] : '';
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
        $endpoint = 'transaction/initialize';

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

        // Make API call using parent method
        $response = $this->make_request('POST', $endpoint, $sanitized_data);

        if (is_wp_error($response)) {
            if (function_exists('ChatShop\\chatshop_log')) {
                chatshop_log('Transaction initialization failed: ' . $response->get_error_message(), 'error');
            }
            return $response;
        }

        // Cache successful response
        if (isset($response['status']) && $response['status'] === true) {
            $cache_key = 'paystack_transaction_' . ($response['data']['reference'] ?? '');
            if (method_exists($this, 'cache_response')) {
                $this->cache_response($cache_key, $response, 300);
            }
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

        // Check cache first
        $cache_key = 'paystack_verify_' . md5($reference);
        if (method_exists($this, 'get_cached_response')) {
            $cached = $this->get_cached_response($cache_key);
            if ($cached !== false) {
                return $cached;
            }
        }

        $endpoint = 'transaction/verify/' . $reference;

        // Make API call
        $response = $this->make_request('GET', $endpoint);

        if (is_wp_error($response)) {
            if (function_exists('ChatShop\\chatshop_log')) {
                chatshop_log('Transaction verification failed: ' . $response->get_error_message(), 'error');
            }
            return $response;
        }

        // Cache successful response
        if (isset($response['status']) && $response['status'] === true) {
            if (method_exists($this, 'cache_response')) {
                $this->cache_response($cache_key, $response, 600); // Cache for 10 minutes
            }
        }

        return $response;
    }

    /**
     * Create a payment page
     *
     * @since 1.0.0
     * @param array $data Payment page data
     * @return array|WP_Error API response or error
     */
    public function create_payment_page($data)
    {
        $endpoint = 'page';

        // Validate required fields
        $required = array('name', 'amount');
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return new \WP_Error(
                    'missing_field',
                    sprintf(__('Required field %s is missing', 'chatshop'), $field)
                );
            }
        }

        $sanitized_data = $this->sanitize_payment_page_data($data);

        return $this->make_request('POST', $endpoint, $sanitized_data);
    }

    /**
     * List transactions
     *
     * @since 1.0.0
     * @param array $params Query parameters
     * @return array|WP_Error API response or error
     */
    public function list_transactions($params = array())
    {
        $endpoint = 'transaction';

        $default_params = array(
            'perPage' => 50,
            'page' => 1
        );

        $query_params = wp_parse_args($params, $default_params);

        // Sanitize parameters
        $query_params = array_map('sanitize_text_field', $query_params);

        return $this->make_request('GET', $endpoint, $query_params);
    }

    /**
     * Get banks list
     *
     * @since 1.0.0
     * @param string $country Country code (optional)
     * @return array|WP_Error API response or error
     */
    public function get_banks($country = 'NG')
    {
        $cache_key = 'paystack_banks_' . sanitize_text_field($country);

        if (method_exists($this, 'get_cached_response')) {
            $cached = $this->get_cached_response($cache_key);
            if ($cached !== false) {
                return $cached;
            }
        }

        $endpoint = 'bank';
        $params = array('country' => sanitize_text_field($country));

        $response = $this->make_request('GET', $endpoint, $params);

        // Cache for 24 hours as banks list doesn't change often
        if (!is_wp_error($response) && method_exists($this, 'cache_response')) {
            $this->cache_response($cache_key, $response, 86400);
        }

        return $response;
    }

    /**
     * Validate account number
     *
     * @since 1.0.0
     * @param string $account_number Account number
     * @param string $bank_code Bank code
     * @return array|WP_Error API response or error
     */
    public function validate_account($account_number, $bank_code)
    {
        if (empty($account_number) || empty($bank_code)) {
            return new \WP_Error('missing_params', __('Account number and bank code are required', 'chatshop'));
        }

        $endpoint = 'bank/resolve';
        $params = array(
            'account_number' => sanitize_text_field($account_number),
            'bank_code' => sanitize_text_field($bank_code)
        );

        return $this->make_request('GET', $endpoint, $params);
    }

    /**
     * Sanitize transaction data
     *
     * @since 1.0.0
     * @param array $data Transaction data
     * @return array Sanitized data
     */
    private function sanitize_transaction_data($data)
    {
        $sanitized = array();

        // Required fields
        $sanitized['email'] = sanitize_email($data['email']);
        $sanitized['amount'] = absint($data['amount']); // Convert to kobo for NGN

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

        if (!empty($data['metadata'])) {
            $sanitized['metadata'] = array_map('sanitize_text_field', (array) $data['metadata']);
        }

        if (!empty($data['channels'])) {
            $sanitized['channels'] = array_map('sanitize_text_field', (array) $data['channels']);
        }

        return $sanitized;
    }

    /**
     * Sanitize payment page data
     *
     * @since 1.0.0
     * @param array $data Payment page data
     * @return array Sanitized data
     */
    private function sanitize_payment_page_data($data)
    {
        $sanitized = array();

        // Required fields
        $sanitized['name'] = sanitize_text_field($data['name']);
        $sanitized['amount'] = absint($data['amount']);

        // Optional fields
        if (!empty($data['description'])) {
            $sanitized['description'] = sanitize_textarea_field($data['description']);
        }

        if (!empty($data['slug'])) {
            $sanitized['slug'] = sanitize_title($data['slug']);
        }

        if (!empty($data['metadata'])) {
            $sanitized['metadata'] = array_map('sanitize_text_field', (array) $data['metadata']);
        }

        if (!empty($data['redirect_url'])) {
            $sanitized['redirect_url'] = esc_url_raw($data['redirect_url']);
        }

        return $sanitized;
    }

    /**
     * Get supported currencies
     *
     * @since 1.0.0
     * @return array Supported currencies
     */
    public function get_supported_currencies()
    {
        return array('NGN', 'USD', 'GHS', 'ZAR', 'KES', 'XOF');
    }

    /**
     * Get supported countries
     *
     * @since 1.0.0
     * @return array Supported countries
     */
    public function get_supported_countries()
    {
        return array('NG', 'GH', 'ZA', 'KE', 'CI', 'SN', 'BF', 'ML');
    }

    /**
     * Check if currency is supported
     *
     * @since 1.0.0
     * @param string $currency Currency code
     * @return bool True if supported
     */
    public function is_currency_supported($currency)
    {
        return in_array(strtoupper($currency), $this->get_supported_currencies(), true);
    }

    /**
     * Check if country is supported
     *
     * @since 1.0.0
     * @param string $country Country code
     * @return bool True if supported
     */
    public function is_country_supported($country)
    {
        return in_array(strtoupper($country), $this->get_supported_countries(), true);
    }

    /**
     * Get API status
     *
     * @since 1.0.0
     * @return array Status information
     */
    public function get_api_status()
    {
        return array(
            'base_url' => $this->api_base_url,
            'test_mode' => $this->test_mode,
            'has_secret_key' => !empty($this->secret_key),
            'has_public_key' => !empty($this->public_key),
            'supported_currencies' => $this->get_supported_currencies(),
            'supported_countries' => $this->get_supported_countries()
        );
    }
}
