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
    }

    /**
     * Load API keys from settings
     *
     * @since 1.0.0
     */
    private function load_api_keys()
    {
        if ($this->is_test_mode()) {
            $this->secret_key = $this->decrypt_api_key($this->get_setting('test_secret_key', ''));
            $this->public_key = $this->get_setting('test_public_key', '');
        } else {
            $this->secret_key = $this->decrypt_api_key($this->get_setting('live_secret_key', ''));
            $this->public_key = $this->get_setting('live_public_key', '');
        }
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

            return $decrypted !== false ? $decrypted : '';
        } catch (Exception $e) {
            $this->log('Failed to decrypt API key: ' . $e->getMessage(), 'error');
            return '';
        }
    }

    /**
     * Process payment
     *
     * @param float  $amount Payment amount
     * @param string $currency Currency code
     * @param array  $customer_data Customer information
     * @param array  $options Additional options
     * @return array Payment result
     * @since 1.0.0
     */
    public function process_payment($amount, $currency, $customer_data, $options = array())
    {
        // Validate payment data
        $validation = $this->validate_payment_data($amount, $currency, $customer_data);
        if (is_wp_error($validation)) {
            return array(
                'success' => false,
                'message' => $validation->get_error_message(),
                'error_code' => $validation->get_error_code()
            );
        }

        // Check API keys
        if (empty($this->secret_key)) {
            return array(
                'success' => false,
                'message' => __('Paystack API keys not configured', 'chatshop'),
                'error_code' => 'missing_api_keys'
            );
        }

        try {
            // Generate unique reference
            $reference = $this->generate_reference('PS');

            // Format amount for Paystack (in kobo/cents)
            $formatted_amount = $this->format_amount($amount, $currency);

            // Prepare transaction data
            $transaction_data = array(
                'email' => sanitize_email($customer_data['email']),
                'amount' => $formatted_amount,
                'currency' => strtoupper($currency),
                'reference' => $reference,
                'callback_url' => isset($options['callback_url']) ? esc_url_raw($options['callback_url']) : '',
                'metadata' => array(
                    'custom_fields' => array(
                        array(
                            'display_name' => 'Plugin',
                            'variable_name' => 'plugin',
                            'value' => 'ChatShop'
                        )
                    )
                )
            );

            // Add customer name if provided
            if (!empty($customer_data['first_name'])) {
                $transaction_data['first_name'] = sanitize_text_field($customer_data['first_name']);
            }
            if (!empty($customer_data['last_name'])) {
                $transaction_data['last_name'] = sanitize_text_field($customer_data['last_name']);
            }

            // Add phone if provided
            if (!empty($customer_data['phone'])) {
                $transaction_data['phone'] = sanitize_text_field($customer_data['phone']);
            }

            // Add additional metadata
            if (!empty($options['metadata'])) {
                $transaction_data['metadata'] = array_merge($transaction_data['metadata'], $options['metadata']);
            }

            // Initialize transaction with Paystack
            $response = $this->make_api_request('transaction/initialize', 'POST', $transaction_data);

            if ($response['success']) {
                $data = $response['data'];

                $this->log("Payment initialized successfully for reference: {$reference}", 'info');

                return array(
                    'success' => true,
                    'reference' => $reference,
                    'authorization_url' => $data['authorization_url'],
                    'access_code' => $data['access_code'],
                    'message' => __('Payment initialized successfully', 'chatshop')
                );
            } else {
                $this->log("Payment initialization failed: {$response['message']}", 'error');

                return array(
                    'success' => false,
                    'message' => $response['message'],
                    'error_code' => 'initialization_failed'
                );
            }
        } catch (Exception $e) {
            $this->log('Payment processing exception: ' . $e->getMessage(), 'error');

            return array(
                'success' => false,
                'message' => __('Payment processing failed. Please try again.', 'chatshop'),
                'error_code' => 'processing_exception'
            );
        }
    }

    /**
     * Verify transaction
     *
     * @param string $reference Transaction reference
     * @return array Verification result
     * @since 1.0.0
     */
    public function verify_transaction($reference)
    {
        if (empty($reference)) {
            return array(
                'success' => false,
                'message' => __('Transaction reference is required', 'chatshop'),
                'error_code' => 'missing_reference'
            );
        }

        if (empty($this->secret_key)) {
            return array(
                'success' => false,
                'message' => __('Paystack API keys not configured', 'chatshop'),
                'error_code' => 'missing_api_keys'
            );
        }

        try {
            $response = $this->make_api_request("transaction/verify/{$reference}", 'GET');

            if ($response['success']) {
                $transaction = $response['data'];

                $result = array(
                    'success' => true,
                    'reference' => $transaction['reference'],
                    'status' => $transaction['status'],
                    'amount' => $transaction['amount'] / 100, // Convert from kobo/cents
                    'currency' => $transaction['currency'],
                    'customer' => array(
                        'email' => $transaction['customer']['email'],
                        'first_name' => isset($transaction['customer']['first_name']) ? $transaction['customer']['first_name'] : '',
                        'last_name' => isset($transaction['customer']['last_name']) ? $transaction['customer']['last_name'] : '',
                        'phone' => isset($transaction['customer']['phone']) ? $transaction['customer']['phone'] : ''
                    ),
                    'paid_at' => $transaction['paid_at'],
                    'channel' => $transaction['channel'],
                    'fees' => isset($transaction['fees']) ? $transaction['fees'] / 100 : 0,
                    'authorization' => isset($transaction['authorization']) ? $transaction['authorization'] : null,
                    'gateway_response' => $transaction['gateway_response'],
                    'message' => __('Transaction verified successfully', 'chatshop')
                );

                $this->log("Transaction verified: {$reference} - Status: {$transaction['status']}", 'info');

                return $result;
            } else {
                $this->log("Transaction verification failed for {$reference}: {$response['message']}", 'error');

                return array(
                    'success' => false,
                    'reference' => $reference,
                    'message' => $response['message'],
                    'error_code' => 'verification_failed'
                );
            }
        } catch (Exception $e) {
            $this->log("Transaction verification exception for {$reference}: " . $e->getMessage(), 'error');

            return array(
                'success' => false,
                'reference' => $reference,
                'message' => __('Transaction verification failed. Please try again.', 'chatshop'),
                'error_code' => 'verification_exception'
            );
        }
    }

    /**
     * Handle webhook
     *
     * @param array $payload Webhook payload
     * @return bool Whether webhook was processed successfully
     * @since 1.0.0
     */
    public function handle_webhook($payload)
    {
        try {
            // Verify webhook signature
            if (!$this->verify_webhook_signature($payload)) {
                $this->log('Webhook signature verification failed', 'error');
                return false;
            }

            $event = $payload['event'] ?? '';
            $data = $payload['data'] ?? array();

            $this->log("Processing webhook event: {$event}", 'info');

            switch ($event) {
                case 'charge.success':
                    return $this->handle_successful_payment($data);

                case 'charge.failed':
                    return $this->handle_failed_payment($data);

                case 'transfer.success':
                    return $this->handle_successful_transfer($data);

                case 'transfer.failed':
                    return $this->handle_failed_transfer($data);

                default:
                    $this->log("Unhandled webhook event: {$event}", 'warning');
                    return true; // Return true for unhandled events to prevent retries
            }
        } catch (Exception $e) {
            $this->log('Webhook processing exception: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Verify webhook signature
     *
     * @param array $payload Webhook payload
     * @return bool Whether signature is valid
     * @since 1.0.0
     */
    private function verify_webhook_signature($payload)
    {
        $signature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';

        if (empty($signature)) {
            return false;
        }

        $body = file_get_contents('php://input');
        $computed_signature = hash_hmac('sha512', $body, $this->secret_key);

        return hash_equals($signature, $computed_signature);
    }

    /**
     * Handle successful payment webhook
     *
     * @param array $data Payment data
     * @return bool Success status
     * @since 1.0.0
     */
    private function handle_successful_payment($data)
    {
        $reference = $data['reference'] ?? '';

        if (empty($reference)) {
            $this->log('Successful payment webhook missing reference', 'error');
            return false;
        }

        $this->log("Processing successful payment: {$reference}", 'info');

        // Trigger action for successful payment
        do_action('chatshop_payment_success', array(
            'gateway' => $this->id,
            'reference' => $reference,
            'amount' => ($data['amount'] ?? 0) / 100,
            'currency' => $data['currency'] ?? '',
            'customer' => $data['customer'] ?? array(),
            'data' => $data
        ));

        return true;
    }

    /**
     * Handle failed payment webhook
     *
     * @param array $data Payment data
     * @return bool Success status
     * @since 1.0.0
     */
    private function handle_failed_payment($data)
    {
        $reference = $data['reference'] ?? '';

        if (empty($reference)) {
            $this->log('Failed payment webhook missing reference', 'error');
            return false;
        }

        $this->log("Processing failed payment: {$reference}", 'info');

        // Trigger action for failed payment
        do_action('chatshop_payment_failed', array(
            'gateway' => $this->id,
            'reference' => $reference,
            'amount' => ($data['amount'] ?? 0) / 100,
            'currency' => $data['currency'] ?? '',
            'customer' => $data['customer'] ?? array(),
            'message' => $data['gateway_response'] ?? '',
            'data' => $data
        ));

        return true;
    }

    /**
     * Handle successful transfer webhook
     *
     * @param array $data Transfer data
     * @return bool Success status
     * @since 1.0.0
     */
    private function handle_successful_transfer($data)
    {
        $reference = $data['reference'] ?? '';

        $this->log("Processing successful transfer: {$reference}", 'info');

        // Trigger action for successful transfer
        do_action('chatshop_transfer_success', array(
            'gateway' => $this->id,
            'reference' => $reference,
            'data' => $data
        ));

        return true;
    }

    /**
     * Handle failed transfer webhook
     *
     * @param array $data Transfer data
     * @return bool Success status
     * @since 1.0.0
     */
    private function handle_failed_transfer($data)
    {
        $reference = $data['reference'] ?? '';

        $this->log("Processing failed transfer: {$reference}", 'info');

        // Trigger action for failed transfer
        do_action('chatshop_transfer_failed', array(
            'gateway' => $this->id,
            'reference' => $reference,
            'data' => $data
        ));

        return true;
    }

    /**
     * Make API request to Paystack
     *
     * @param string $endpoint API endpoint
     * @param string $method HTTP method
     * @param array  $data Request data
     * @return array Response data
     * @since 1.0.0
     */
    private function make_api_request($endpoint, $method = 'GET', $data = array())
    {
        $url = rtrim($this->api_base_url, '/') . '/' . ltrim($endpoint, '/');

        $args = array(
            'method' => strtoupper($method),
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->secret_key,
                'Content-Type' => 'application/json',
                'Cache-Control' => 'no-cache'
            ),
            'timeout' => 30,
            'user-agent' => 'ChatShop/1.0.0 WordPress/' . get_bloginfo('version')
        );

        if (!empty($data) && in_array($method, array('POST', 'PUT', 'PATCH'))) {
            $args['body'] = wp_json_encode($data);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            $this->log("API request failed: {$response->get_error_message()}", 'error');

            return array(
                'success' => false,
                'message' => $response->get_error_message(),
                'error_code' => 'request_failed'
            );
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $decoded_body = json_decode($body, true);

        if ($status_code >= 200 && $status_code < 300) {
            return array(
                'success' => true,
                'data' => $decoded_body['data'] ?? $decoded_body,
                'message' => $decoded_body['message'] ?? 'Success'
            );
        } else {
            $error_message = $decoded_body['message'] ?? "HTTP {$status_code} error";

            $this->log("API request failed with status {$status_code}: {$error_message}", 'error');

            return array(
                'success' => false,
                'message' => $error_message,
                'error_code' => "http_{$status_code}"
            );
        }
    }

    /**
     * Test gateway connection
     *
     * @return array Test result
     * @since 1.0.0
     */
    public function test_connection()
    {
        if (empty($this->secret_key)) {
            return array(
                'success' => false,
                'message' => __('API keys not configured', 'chatshop')
            );
        }

        try {
            $response = $this->make_api_request('bank', 'GET');

            if ($response['success']) {
                return array(
                    'success' => true,
                    'message' => __('Connection successful', 'chatshop')
                );
            } else {
                return array(
                    'success' => false,
                    'message' => $response['message']
                );
            }
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }

    /**
     * Get gateway configuration fields for admin
     *
     * @return array Configuration fields
     * @since 1.0.0
     */
    public function get_config_fields()
    {
        return array(
            'enabled' => array(
                'title' => __('Enable Paystack', 'chatshop'),
                'type' => 'checkbox',
                'description' => __('Enable Paystack payment gateway', 'chatshop'),
                'default' => 'no'
            ),
            'test_mode' => array(
                'title' => __('Test Mode', 'chatshop'),
                'type' => 'checkbox',
                'description' => __('Enable test mode for Paystack payments', 'chatshop'),
                'default' => 'yes'
            ),
            'test_public_key' => array(
                'title' => __('Test Public Key', 'chatshop'),
                'type' => 'text',
                'description' => __('Your Paystack test public key', 'chatshop'),
                'default' => ''
            ),
            'test_secret_key' => array(
                'title' => __('Test Secret Key', 'chatshop'),
                'type' => 'password',
                'description' => __('Your Paystack test secret key', 'chatshop'),
                'default' => ''
            ),
            'live_public_key' => array(
                'title' => __('Live Public Key', 'chatshop'),
                'type' => 'text',
                'description' => __('Your Paystack live public key', 'chatshop'),
                'default' => ''
            ),
            'live_secret_key' => array(
                'title' => __('Live Secret Key', 'chatshop'),
                'type' => 'password',
                'description' => __('Your Paystack live secret key', 'chatshop'),
                'default' => ''
            )
        );
    }

    /**
     * Generate payment link
     *
     * @param float  $amount Payment amount
     * @param string $currency Currency code
     * @param array  $customer_data Customer information
     * @param array  $options Additional options
     * @return array Payment link result
     * @since 1.0.0
     */
    public function generate_payment_link($amount, $currency, $customer_data, $options = array())
    {
        // Use the existing process_payment method which returns authorization_url
        $payment_result = $this->process_payment($amount, $currency, $customer_data, $options);

        if ($payment_result['success']) {
            return array(
                'success' => true,
                'payment_url' => $payment_result['authorization_url'],
                'reference' => $payment_result['reference'],
                'access_code' => $payment_result['access_code'],
                'message' => __('Payment link generated successfully', 'chatshop')
            );
        }

        return $payment_result;
    }

    /**
     * Create payment request
     *
     * @param array $request_data Payment request data
     * @return array Payment request result
     * @since 1.0.0
     */
    public function create_payment_request($request_data)
    {
        if (empty($this->secret_key)) {
            return array(
                'success' => false,
                'message' => __('Paystack API keys not configured', 'chatshop'),
                'error_code' => 'missing_api_keys'
            );
        }

        try {
            $response = $this->make_api_request('paymentrequest', 'POST', $request_data);

            if ($response['success']) {
                $this->log("Payment request created successfully", 'info');

                return array(
                    'success' => true,
                    'data' => $response['data'],
                    'message' => __('Payment request created successfully', 'chatshop')
                );
            } else {
                $this->log("Payment request creation failed: {$response['message']}", 'error');

                return array(
                    'success' => false,
                    'message' => $response['message'],
                    'error_code' => 'creation_failed'
                );
            }
        } catch (Exception $e) {
            $this->log('Payment request creation exception: ' . $e->getMessage(), 'error');

            return array(
                'success' => false,
                'message' => __('Payment request creation failed. Please try again.', 'chatshop'),
                'error_code' => 'creation_exception'
            );
        }
    }

    /**
     * Get supported banks
     *
     * @param string $currency Currency code
     * @return array Banks list
     * @since 1.0.0
     */
    public function get_supported_banks($currency = 'NGN')
    {
        if (empty($this->secret_key)) {
            return array(
                'success' => false,
                'message' => __('Paystack API keys not configured', 'chatshop'),
                'banks' => array()
            );
        }

        try {
            $endpoint = 'bank';
            if (!empty($currency)) {
                $endpoint .= '?currency=' . strtoupper($currency);
            }

            $response = $this->make_api_request($endpoint, 'GET');

            if ($response['success']) {
                return array(
                    'success' => true,
                    'banks' => $response['data'],
                    'message' => __('Banks retrieved successfully', 'chatshop')
                );
            } else {
                return array(
                    'success' => false,
                    'message' => $response['message'],
                    'banks' => array()
                );
            }
        } catch (Exception $e) {
            $this->log('Get banks exception: ' . $e->getMessage(), 'error');

            return array(
                'success' => false,
                'message' => __('Failed to retrieve banks. Please try again.', 'chatshop'),
                'banks' => array()
            );
        }
    }

    /**
     * Process refund
     *
     * @param string $reference Transaction reference
     * @param float  $amount Refund amount (optional, full refund if not specified)
     * @param string $reason Refund reason
     * @return array Refund result
     * @since 1.0.0
     */
    public function process_refund($reference, $amount = null, $reason = '')
    {
        if (empty($this->secret_key)) {
            return array(
                'success' => false,
                'message' => __('Paystack API keys not configured', 'chatshop'),
                'error_code' => 'missing_api_keys'
            );
        }

        try {
            $refund_data = array(
                'transaction' => $reference
            );

            if (!is_null($amount)) {
                $refund_data['amount'] = $this->format_amount($amount, 'NGN'); // Convert to kobo
            }

            if (!empty($reason)) {
                $refund_data['merchant_note'] = sanitize_text_field($reason);
            }

            $response = $this->make_api_request('refund', 'POST', $refund_data);

            if ($response['success']) {
                $this->log("Refund processed successfully for {$reference}", 'info');

                return array(
                    'success' => true,
                    'data' => $response['data'],
                    'message' => __('Refund processed successfully', 'chatshop')
                );
            } else {
                $this->log("Refund failed for {$reference}: {$response['message']}", 'error');

                return array(
                    'success' => false,
                    'message' => $response['message'],
                    'error_code' => 'refund_failed'
                );
            }
        } catch (Exception $e) {
            $this->log("Refund exception for {$reference}: " . $e->getMessage(), 'error');

            return array(
                'success' => false,
                'message' => __('Refund processing failed. Please try again.', 'chatshop'),
                'error_code' => 'refund_exception'
            );
        }
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
     * Get minimum transaction amount for currency
     *
     * @param string $currency Currency code
     * @return float Minimum amount
     * @since 1.0.0
     */
    public function get_minimum_amount($currency)
    {
        $minimums = array(
            'NGN' => 50,     // ₦50
            'USD' => 2,      // $2
            'GHS' => 0.10,   // ₵0.10
            'ZAR' => 1,      // R1
            'KES' => 3,      // KSh3
            'XOF' => 1       // XOF1
        );

        return isset($minimums[strtoupper($currency)]) ? $minimums[strtoupper($currency)] : 1;
    }

    /**
     * Check if gateway is properly configured
     *
     * @return bool Configuration status
     * @since 1.0.0
     */
    public function is_configured()
    {
        return !empty($this->secret_key) && !empty($this->public_key);
    }
}
