<?php

/**
 * Paystack Payment Gateway
 *
 * @package ChatShop
 * @since 1.0.0
 */

namespace ChatShop;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Paystack payment gateway implementation
 *
 * @since 1.0.0
 */
class ChatShop_Paystack_Gateway extends ChatShop_Abstract_Payment_Gateway
{
    /**
     * Paystack API base URL
     *
     * @var string
     * @since 1.0.0
     */
    private $api_base_url = 'https://api.paystack.co';

    /**
     * API secret key
     *
     * @var string
     * @since 1.0.0
     */
    private $secret_key;

    /**
     * API public key
     *
     * @var string
     * @since 1.0.0
     */
    private $public_key;

    /**
     * Paystack API instance
     *
     * @var ChatShop_Paystack_API
     * @since 1.0.0
     */
    private $api;

    /**
     * Initialize gateway
     *
     * @since 1.0.0
     */
    protected function init()
    {
        $this->id = 'paystack';
        $this->title = __('Paystack', 'chatshop');
        $this->description = __('Pay securely using your card or bank account via Paystack', 'chatshop');

        // Set supported currencies and countries
        $this->supported_currencies = array('NGN', 'USD', 'GHS', 'ZAR', 'KES', 'XOF');
        $this->supported_countries = array('NG', 'GH', 'ZA', 'KE', 'CI', 'SN', 'BF', 'ML');

        // Set gateway fees (Paystack standard rates)
        $this->fees = array(
            'NGN' => array('percentage' => 1.5, 'cap' => 200000), // 1.5% capped at ₦2,000
            'USD' => array('percentage' => 3.9, 'fixed' => 0.3), // 3.9% + $0.30
            'GHS' => array('percentage' => 3.5), // 3.5%
            'ZAR' => array('percentage' => 3.5), // 3.5%
            'KES' => array('percentage' => 3.5), // 3.5%
            'XOF' => array('percentage' => 3.8), // 3.8%
        );

        // Load API keys
        $this->load_api_keys();

        // Initialize API client
        $this->init_api_client();
    }

    /**
     * Load API keys from settings
     *
     * @since 1.0.0
     */
    private function load_api_keys()
    {
        if ($this->is_test_mode()) {
            $this->secret_key = $this->get_decrypted_setting('test_secret_key', '');
            $this->public_key = $this->get_setting('test_public_key', '');
        } else {
            $this->secret_key = $this->get_decrypted_setting('live_secret_key', '');
            $this->public_key = $this->get_setting('live_public_key', '');
        }
    }

    /**
     * Get decrypted setting value
     *
     * @param string $key Setting key
     * @param mixed $default Default value
     * @return string Decrypted value
     * @since 1.0.0
     */
    private function get_decrypted_setting($key, $default = '')
    {
        $encrypted_value = $this->get_setting($key, $default);
        return $this->decrypt_api_key($encrypted_value);
    }

    /**
     * Decrypt API key
     *
     * @param string $encrypted_key Encrypted API key
     * @return string Decrypted key
     * @since 1.0.0
     */
    private function decrypt_api_key($encrypted_key)
    {
        if (empty($encrypted_key)) {
            return '';
        }

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
            if (function_exists('ChatShop\\chatshop_log')) {
                chatshop_log('Key decryption failed: ' . $e->getMessage(), 'error');
            }
            return $encrypted_key;
        }
    }

    /**
     * Initialize API client
     *
     * @since 1.0.0
     */
    private function init_api_client()
    {
        if (class_exists('ChatShop\\ChatShop_Paystack_API')) {
            try {
                $config = array(
                    'test_mode' => $this->is_test_mode(),
                    'secret_key' => $this->secret_key,
                    'public_key' => $this->public_key
                );

                $this->api = new ChatShop_Paystack_API($config);
            } catch (Exception $e) {
                if (function_exists('ChatShop\\chatshop_log')) {
                    chatshop_log('Failed to initialize Paystack API: ' . $e->getMessage(), 'error');
                }
            }
        }
    }

    /**
     * Process payment
     *
     * @param float $amount Payment amount
     * @param string $currency Currency code
     * @param array $customer_data Customer information
     * @return array Payment result
     * @since 1.0.0
     */
    public function process_payment($amount, $currency, $customer_data)
    {
        try {
            // Validate inputs
            if (empty($amount) || $amount <= 0) {
                return $this->payment_error('Invalid payment amount');
            }

            if (!$this->is_currency_supported($currency)) {
                return $this->payment_error('Currency not supported: ' . $currency);
            }

            if (empty($customer_data['email'])) {
                return $this->payment_error('Customer email is required');
            }

            // Ensure API client is available
            if (!$this->api) {
                return $this->payment_error('Payment gateway not properly configured');
            }

            // Convert amount to kobo for NGN
            $paystack_amount = $this->convert_amount_to_paystack($amount, $currency);

            // Prepare transaction data
            $transaction_data = array(
                'email' => sanitize_email($customer_data['email']),
                'amount' => $paystack_amount,
                'currency' => strtoupper($currency),
                'reference' => $this->generate_transaction_reference(),
                'callback_url' => $this->get_callback_url(),
                'metadata' => $this->prepare_metadata($customer_data)
            );

            // Initialize transaction
            $response = $this->api->initialize_transaction($transaction_data);

            if (is_wp_error($response)) {
                return $this->payment_error($response->get_error_message());
            }

            if (!isset($response['status']) || $response['status'] !== true) {
                $error_message = isset($response['message']) ? $response['message'] : 'Transaction initialization failed';
                return $this->payment_error($error_message);
            }

            // Return success response
            return array(
                'success' => true,
                'transaction_id' => $response['data']['reference'],
                'authorization_url' => $response['data']['authorization_url'],
                'access_code' => $response['data']['access_code'],
                'gateway' => $this->id
            );
        } catch (Exception $e) {
            if (function_exists('ChatShop\\chatshop_log')) {
                chatshop_log('Paystack payment processing error: ' . $e->getMessage(), 'error');
            }
            return $this->payment_error('Payment processing failed');
        }
    }

    /**
     * Verify transaction
     *
     * @param string $transaction_id Transaction ID/reference
     * @return array Verification result
     * @since 1.0.0
     */
    public function verify_transaction($transaction_id)
    {
        try {
            if (empty($transaction_id)) {
                return $this->verification_error('Transaction ID is required');
            }

            if (!$this->api) {
                return $this->verification_error('Payment gateway not configured');
            }

            $response = $this->api->verify_transaction($transaction_id);

            if (is_wp_error($response)) {
                return $this->verification_error($response->get_error_message());
            }

            if (!isset($response['status']) || $response['status'] !== true) {
                $error_message = isset($response['message']) ? $response['message'] : 'Transaction verification failed';
                return $this->verification_error($error_message);
            }

            $transaction_data = $response['data'];

            return array(
                'success' => true,
                'status' => $transaction_data['status'],
                'amount' => $this->convert_amount_from_paystack($transaction_data['amount'], $transaction_data['currency']),
                'currency' => $transaction_data['currency'],
                'reference' => $transaction_data['reference'],
                'paid_at' => $transaction_data['paid_at'],
                'gateway' => $this->id,
                'gateway_response' => $transaction_data
            );
        } catch (Exception $e) {
            if (function_exists('ChatShop\\chatshop_log')) {
                chatshop_log('Paystack transaction verification error: ' . $e->getMessage(), 'error');
            }
            return $this->verification_error('Transaction verification failed');
        }
    }

    /**
     * Handle webhook
     *
     * @param array $payload Webhook payload
     * @return array Webhook processing result
     * @since 1.0.0
     */
    public function handle_webhook($payload)
    {
        try {
            if (empty($payload)) {
                return array('success' => false, 'message' => 'Empty payload');
            }

            // Verify webhook signature if available
            if (class_exists('ChatShop\\ChatShop_Paystack_Webhook')) {
                $webhook_handler = new ChatShop_Paystack_Webhook();
                if (!$webhook_handler->verify_signature($payload)) {
                    return array('success' => false, 'message' => 'Invalid signature');
                }
            }

            $event = isset($payload['event']) ? $payload['event'] : '';
            $data = isset($payload['data']) ? $payload['data'] : array();

            // Process different event types
            switch ($event) {
                case 'charge.success':
                    return $this->handle_successful_charge($data);

                case 'charge.failed':
                    return $this->handle_failed_charge($data);

                case 'transfer.success':
                    return $this->handle_successful_transfer($data);

                default:
                    return array('success' => true, 'message' => 'Event processed');
            }
        } catch (Exception $e) {
            if (function_exists('ChatShop\\chatshop_log')) {
                chatshop_log('Paystack webhook error: ' . $e->getMessage(), 'error');
            }
            return array('success' => false, 'message' => 'Webhook processing failed');
        }
    }

    /**
     * Handle successful charge webhook
     *
     * @param array $data Charge data
     * @return array Processing result
     * @since 1.0.0
     */
    private function handle_successful_charge($data)
    {
        $reference = isset($data['reference']) ? $data['reference'] : '';

        if (empty($reference)) {
            return array('success' => false, 'message' => 'Missing reference');
        }

        // Process the successful payment
        do_action('chatshop_paystack_charge_success', $data);

        return array('success' => true, 'message' => 'Charge processed');
    }

    /**
     * Handle failed charge webhook
     *
     * @param array $data Charge data
     * @return array Processing result
     * @since 1.0.0
     */
    private function handle_failed_charge($data)
    {
        $reference = isset($data['reference']) ? $data['reference'] : '';

        if (empty($reference)) {
            return array('success' => false, 'message' => 'Missing reference');
        }

        // Process the failed payment
        do_action('chatshop_paystack_charge_failed', $data);

        return array('success' => true, 'message' => 'Failed charge processed');
    }

    /**
     * Handle successful transfer webhook
     *
     * @param array $data Transfer data
     * @return array Processing result
     * @since 1.0.0
     */
    private function handle_successful_transfer($data)
    {
        do_action('chatshop_paystack_transfer_success', $data);
        return array('success' => true, 'message' => 'Transfer processed');
    }

    /**
     * Convert amount to Paystack format (kobo for NGN)
     *
     * @param float $amount Amount
     * @param string $currency Currency
     * @return int Converted amount
     * @since 1.0.0
     */
    private function convert_amount_to_paystack($amount, $currency)
    {
        if (strtoupper($currency) === 'NGN') {
            return intval($amount * 100); // Convert to kobo
        }

        return intval($amount * 100); // Convert to smallest unit for other currencies
    }

    /**
     * Convert amount from Paystack format
     *
     * @param int $amount Paystack amount
     * @param string $currency Currency
     * @return float Converted amount
     * @since 1.0.0
     */
    private function convert_amount_from_paystack($amount, $currency)
    {
        return floatval($amount / 100);
    }

    /**
     * Generate transaction reference
     *
     * @return string Transaction reference
     * @since 1.0.0
     */
    private function generate_transaction_reference()
    {
        return 'chatshop_' . uniqid() . '_' . time();
    }

    /**
     * Get callback URL
     *
     * @return string Callback URL
     * @since 1.0.0
     */
    private function get_callback_url()
    {
        return add_query_arg(
            array(
                'chatshop_callback' => '1',
                'gateway' => $this->id
            ),
            home_url()
        );
    }

    /**
     * Prepare metadata for transaction
     *
     * @param array $customer_data Customer data
     * @return array Metadata
     * @since 1.0.0
     */
    private function prepare_metadata($customer_data)
    {
        $metadata = array();

        if (!empty($customer_data['name'])) {
            $metadata['customer_name'] = sanitize_text_field($customer_data['name']);
        }

        if (!empty($customer_data['phone'])) {
            $metadata['customer_phone'] = sanitize_text_field($customer_data['phone']);
        }

        if (!empty($customer_data['order_id'])) {
            $metadata['order_id'] = sanitize_text_field($customer_data['order_id']);
        }

        $metadata['plugin'] = 'chatshop';
        $metadata['version'] = defined('CHATSHOP_VERSION') ? CHATSHOP_VERSION : '1.0.0';

        return $metadata;
    }

    /**
     * Create payment error response
     *
     * @param string $message Error message
     * @return array Error response
     * @since 1.0.0
     */
    private function payment_error($message)
    {
        return array(
            'success' => false,
            'message' => $message,
            'gateway' => $this->id
        );
    }

    /**
     * Create verification error response
     *
     * @param string $message Error message
     * @return array Error response
     * @since 1.0.0
     */
    private function verification_error($message)
    {
        return array(
            'success' => false,
            'message' => $message,
            'gateway' => $this->id
        );
    }

    /**
     * Check if currency is supported
     *
     * @param string $currency Currency code
     * @return bool True if supported
     * @since 1.0.0
     */
    public function is_currency_supported($currency)
    {
        return in_array(strtoupper($currency), $this->supported_currencies, true);
    }

    /**
     * Check if country is supported
     *
     * @param string $country Country code
     * @return bool True if supported
     * @since 1.0.0
     */
    public function is_country_supported($country)
    {
        return in_array(strtoupper($country), $this->supported_countries, true);
    }

    /**
     * Get gateway configuration
     *
     * @return array Gateway configuration
     * @since 1.0.0
     */
    public function get_gateway_config()
    {
        return array(
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'supported_currencies' => $this->supported_currencies,
            'supported_countries' => $this->supported_countries,
            'test_mode' => $this->is_test_mode(),
            'configured' => !empty($this->secret_key) && !empty($this->public_key),
            'fees' => $this->fees
        );
    }

    /**
     * Get public key for frontend
     *
     * @return string Public key
     * @since 1.0.0
     */
    public function get_public_key()
    {
        return $this->public_key;
    }

    /**
     * Test gateway connection
     *
     * @return array Test result
     * @since 1.0.0
     */
    public function test_connection()
    {
        try {
            if (!$this->api) {
                return array(
                    'success' => false,
                    'message' => 'API client not initialized'
                );
            }

            // Test with banks endpoint as it's a simple GET request
            $response = $this->api->get_banks();

            if (is_wp_error($response)) {
                return array(
                    'success' => false,
                    'message' => $response->get_error_message()
                );
            }

            return array(
                'success' => true,
                'message' => 'Connection successful'
            );
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }

    /**
     * Get gateway status
     *
     * @return array Gateway status
     * @since 1.0.0
     */
    public function get_status()
    {
        $status = array(
            'gateway_id' => $this->id,
            'configured' => false,
            'test_mode' => $this->is_test_mode(),
            'has_secret_key' => !empty($this->secret_key),
            'has_public_key' => !empty($this->public_key),
            'api_client' => $this->api !== null,
            'connection_test' => null
        );

        $status['configured'] = $status['has_secret_key'] && $status['has_public_key'];

        // Test connection if configured
        if ($status['configured']) {
            $connection_test = $this->test_connection();
            $status['connection_test'] = $connection_test['success'];
        }

        return $status;
    }
}
