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
            chatshop_log('Paystack API initialized without secret key', 'warning');
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
     * Decrypt API key
     * 
     * FIXED: Changed from 'private' to 'protected' to match parent class access level
     * This resolves the fatal error on plugin activation
     *
     * @since 1.0.0
     * @param string $encrypted_key Encrypted key
     * @return string Decrypted key
     */
    protected function decrypt_key($encrypted_key)  // CHANGED FROM 'private' TO 'protected'
    {
        if (empty($encrypted_key)) {
            return '';
        }

        // Use WordPress salt for encryption key (matching parent implementation)
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
            chatshop_log('Transaction initialization failed: ' . $response->get_error_message(), 'error');
            return $response;
        }

        // Cache successful response
        if (isset($response['status']) && $response['status'] === true) {
            $cache_key = 'paystack_transaction_' . ($response['data']['reference'] ?? '');
            $this->cache_response($cache_key, $response, 300);
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
        $cache_key = 'paystack_verify_' . $reference;
        $cached = $this->get_cached_response($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        $endpoint = 'transaction/verify/' . $reference;

        // Make API call
        $response = $this->make_request('GET', $endpoint);

        if (is_wp_error($response)) {
            chatshop_log('Transaction verification failed: ' . $response->get_error_message(), 'error');
            return $response;
        }

        // Cache successful response
        if (isset($response['status']) && $response['status'] === true) {
            $this->cache_response($cache_key, $response, 600); // Cache for 10 minutes
        }

        return $response;
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
        if (isset($data['email'])) {
            $sanitized['email'] = sanitize_email($data['email']);
        }

        if (isset($data['amount'])) {
            // Convert to kobo/cents
            $sanitized['amount'] = intval($data['amount'] * 100);
        }

        // Optional fields
        $optional_fields = array(
            'reference',
            'callback_url',
            'plan',
            'invoice_limit',
            'metadata',
            'channels',
            'split_code',
            'subaccount',
            'transaction_charge',
            'bearer',
            'currency'
        );

        foreach ($optional_fields as $field) {
            if (isset($data[$field])) {
                if ($field === 'metadata' && is_array($data[$field])) {
                    $sanitized[$field] = $this->sanitize_metadata($data[$field]);
                } elseif ($field === 'channels' && is_array($data[$field])) {
                    $sanitized[$field] = array_map('sanitize_text_field', $data[$field]);
                } else {
                    $sanitized[$field] = sanitize_text_field($data[$field]);
                }
            }
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
            $sanitized_key = sanitize_key($key);
            if (is_array($value)) {
                $sanitized[$sanitized_key] = $this->sanitize_metadata($value);
            } else {
                $sanitized[$sanitized_key] = sanitize_text_field($value);
            }
        }

        return $sanitized;
    }

    /**
     * Get public key for frontend use
     *
     * @since 1.0.0
     * @return string Public key
     */
    public function get_public_key()
    {
        return $this->public_key;
    }

    /**
     * Check if in test mode
     *
     * @since 1.0.0
     * @return bool Test mode status
     */
    public function is_test_mode()
    {
        return $this->test_mode;
    }

    /**
     * List banks
     *
     * @since 1.0.0
     * @param string $country Country code
     * @return array|WP_Error Banks list or error
     */
    public function list_banks($country = 'NG')
    {
        $cache_key = 'paystack_banks_' . $country;
        $cached = $this->get_cached_response($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        $endpoint = 'bank';
        $params = array('country' => $country);

        $response = $this->make_request('GET', $endpoint, null, $params);

        if (is_wp_error($response)) {
            return $response;
        }

        // Cache for 24 hours
        $this->cache_response($cache_key, $response, 86400);

        return $response;
    }

    /**
     * Create customer
     *
     * @since 1.0.0
     * @param array $data Customer data
     * @return array|WP_Error API response or error
     */
    public function create_customer($data)
    {
        $endpoint = 'customer';

        // Validate required fields
        if (empty($data['email'])) {
            return new \WP_Error('missing_email', __('Customer email is required', 'chatshop'));
        }

        $sanitized_data = array(
            'email' => sanitize_email($data['email'])
        );

        // Optional fields
        $optional = array('first_name', 'last_name', 'phone', 'metadata');
        foreach ($optional as $field) {
            if (!empty($data[$field])) {
                if ($field === 'metadata' && is_array($data[$field])) {
                    $sanitized_data[$field] = $this->sanitize_metadata($data[$field]);
                } else {
                    $sanitized_data[$field] = sanitize_text_field($data[$field]);
                }
            }
        }

        return $this->make_request('POST', $endpoint, $sanitized_data);
    }

    /**
     * Get health check endpoint
     *
     * @since 1.0.0
     * @return string Health endpoint
     */
    protected function get_health_endpoint()
    {
        return 'transaction/totals';
    }
}
