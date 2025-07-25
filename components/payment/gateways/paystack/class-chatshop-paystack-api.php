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
     * @since 1.0.0
     * @param string $encrypted_key Encrypted key
     * @return string Decrypted key
     */
    private function decrypt_key($encrypted_key)
    {
        if (empty($encrypted_key)) {
            return '';
        }

        // Simple decryption - in production, use proper encryption
        return base64_decode($encrypted_key);
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
        $cached_response = $this->get_cached_response($cache_key);
        if ($cached_response !== false) {
            return $cached_response;
        }

        $endpoint = 'transaction/verify/' . $reference;
        $response = $this->make_request('GET', $endpoint);

        if (is_wp_error($response)) {
            chatshop_log('Transaction verification failed: ' . $response->get_error_message(), 'error');
            return $response;
        }

        // Cache successful verification
        if (isset($response['status']) && $response['status'] === true) {
            $this->cache_response($cache_key, $response, 600); // Cache for 10 minutes
        }

        return $response;
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

        // Set default parameters
        $default_params = array(
            'perPage' => 50,
            'page' => 1
        );

        $params = array_merge($default_params, $params);

        return $this->make_request('GET', $endpoint, $params);
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
        $endpoint = 'paymentrequest';

        // Validate required fields
        $required = array('description', 'amount');
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return new \WP_Error(
                    'missing_field',
                    sprintf(__('Required field %s is missing', 'chatshop'), $field)
                );
            }
        }

        // Sanitize data
        $sanitized_data = array(
            'description' => sanitize_text_field($data['description']),
            'amount' => absint($data['amount']),
            'invoice_number' => isset($data['invoice_number']) ? sanitize_text_field($data['invoice_number']) : '',
            'due_date' => isset($data['due_date']) ? sanitize_text_field($data['due_date']) : '',
            'line_items' => isset($data['line_items']) ? $data['line_items'] : array(),
            'tax' => isset($data['tax']) ? absint($data['tax']) : 0,
            'currency' => isset($data['currency']) ? sanitize_text_field($data['currency']) : 'NGN',
            'send_notification' => isset($data['send_notification']) ? (bool) $data['send_notification'] : false,
            'draft' => isset($data['draft']) ? (bool) $data['draft'] : false
        );

        // Add customer information if provided
        if (!empty($data['customer'])) {
            $sanitized_data['customer'] = $data['customer'];
        }

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
        $endpoint = 'paymentrequest/' . $id_or_code;

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
        $endpoint = 'paymentrequest/verify/' . $code;

        return $this->make_request('GET', $endpoint);
    }

    /**
     * Get supported banks
     *
     * @since 1.0.0
     * @param string $currency Currency code
     * @return array|WP_Error API response or error
     */
    public function get_banks($currency = 'NGN')
    {
        $cache_key = 'paystack_banks_' . $currency;
        $cached_banks = $this->get_cached_response($cache_key);

        if ($cached_banks !== false) {
            return $cached_banks;
        }

        $endpoint = 'bank';
        $params = array('currency' => $currency);

        $response = $this->make_request('GET', $endpoint, $params);

        if (!is_wp_error($response)) {
            // Cache banks list for 24 hours
            $this->cache_response($cache_key, $response, 86400);
        }

        return $response;
    }

    /**
     * Test API connection
     *
     * @since 1.0.0
     * @return array Connection test result
     */
    public function test_connection()
    {
        $response = $this->make_request('GET', 'transaction', array('perPage' => 1));

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
     * Sanitize transaction data
     *
     * @since 1.0.0
     * @param array $data Raw transaction data
     * @return array Sanitized data
     */
    private function sanitize_transaction_data($data)
    {
        $sanitized = array(
            'email' => sanitize_email($data['email']),
            'amount' => absint($data['amount']),
            'currency' => isset($data['currency']) ? sanitize_text_field($data['currency']) : 'NGN',
            'reference' => isset($data['reference']) ? sanitize_text_field($data['reference']) : $this->generate_reference(),
            'callback_url' => isset($data['callback_url']) ? esc_url_raw($data['callback_url']) : '',
            'metadata' => isset($data['metadata']) ? $data['metadata'] : array()
        );

        // Add optional fields
        $optional_fields = array('first_name', 'last_name', 'phone');
        foreach ($optional_fields as $field) {
            if (!empty($data[$field])) {
                $sanitized[$field] = sanitize_text_field($data[$field]);
            }
        }

        return $sanitized;
    }

    /**
     * Generate unique transaction reference
     *
     * @since 1.0.0
     * @return string Transaction reference
     */
    private function generate_reference()
    {
        return 'CS_' . time() . '_' . wp_generate_password(8, false);
    }

    /**
     * Get health check endpoint for Paystack
     *
     * @since 1.0.0
     * @return string Health endpoint
     */
    protected function get_health_endpoint()
    {
        return 'transaction';
    }

    /**
     * Override response validation for Paystack format
     *
     * @since 1.0.0
     * @param array $data Response data
     * @return bool Whether response is valid
     */
    protected function validate_response($data)
    {
        return is_array($data) && isset($data['status']);
    }

    /**
     * Transform Paystack response to standardized format
     *
     * @since 1.0.0
     * @param array $data Raw response data
     * @return array Transformed data
     */
    protected function transform_response($data)
    {
        if (!is_array($data)) {
            return $data;
        }

        // Paystack returns status as boolean, convert to standardized format
        if (isset($data['status'])) {
            $data['success'] = (bool) $data['status'];
        }

        return $data;
    }

    /**
     * Handle webhook data
     *
     * @since 1.0.0
     * @param array $webhook_data Raw webhook data
     * @return array Processed webhook data
     */
    public function handle_webhook($webhook_data)
    {
        if (empty($webhook_data)) {
            return array(
                'success' => false,
                'message' => __('Empty webhook data', 'chatshop')
            );
        }

        // Verify webhook signature if available
        $headers = getallheaders();
        if (isset($headers['x-paystack-signature'])) {
            $signature = $headers['x-paystack-signature'];
            if (!$this->verify_webhook_signature($webhook_data, $signature)) {
                return array(
                    'success' => false,
                    'message' => __('Invalid webhook signature', 'chatshop')
                );
            }
        }

        // Process webhook based on event type
        $event = $webhook_data['event'] ?? '';

        switch ($event) {
            case 'charge.success':
                return $this->handle_charge_success($webhook_data['data'] ?? array());

            case 'charge.failed':
                return $this->handle_charge_failed($webhook_data['data'] ?? array());

            default:
                return array(
                    'success' => true,
                    'message' => sprintf(__('Webhook event %s processed', 'chatshop'), $event)
                );
        }
    }

    /**
     * Verify webhook signature
     *
     * @since 1.0.0
     * @param array  $data Webhook data
     * @param string $signature Webhook signature
     * @return bool Whether signature is valid
     */
    private function verify_webhook_signature($data, $signature)
    {
        $webhook_secret = chatshop_get_option('paystack', 'webhook_secret', '');

        if (empty($webhook_secret)) {
            return true; // Skip verification if no secret is set
        }

        $computed_signature = hash_hmac('sha512', wp_json_encode($data), $webhook_secret);

        return hash_equals($signature, $computed_signature);
    }

    /**
     * Handle successful charge webhook
     *
     * @since 1.0.0
     * @param array $data Charge data
     * @return array Processing result
     */
    private function handle_charge_success($data)
    {
        $reference = $data['reference'] ?? '';

        if (empty($reference)) {
            return array(
                'success' => false,
                'message' => __('Missing transaction reference', 'chatshop')
            );
        }

        // Trigger WordPress action for successful payment
        do_action('chatshop_payment_completed', $reference, $data);

        return array(
            'success' => true,
            'message' => __('Payment processed successfully', 'chatshop')
        );
    }

    /**
     * Handle failed charge webhook
     *
     * @since 1.0.0
     * @param array $data Charge data
     * @return array Processing result
     */
    private function handle_charge_failed($data)
    {
        $reference = $data['reference'] ?? '';

        if (empty($reference)) {
            return array(
                'success' => false,
                'message' => __('Missing transaction reference', 'chatshop')
            );
        }

        // Trigger WordPress action for failed payment
        do_action('chatshop_payment_failed', $reference, $data);

        return array(
            'success' => true,
            'message' => __('Failed payment processed', 'chatshop')
        );
    }
}
