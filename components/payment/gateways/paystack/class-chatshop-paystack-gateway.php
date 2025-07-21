<?php

/**
 * Paystack Payment Gateway
 *
 * Main gateway class that implements the abstract payment gateway
 * with complete Paystack integration including payment processing,
 * verification, and link generation.
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
 * Paystack Payment Gateway Class
 *
 * @since 1.0.0
 */
class ChatShop_Paystack_Gateway extends ChatShop_Abstract_Payment_Gateway
{
    /**
     * Gateway ID
     *
     * @since 1.0.0
     * @var string
     */
    protected $id = 'paystack';

    /**
     * Gateway name
     *
     * @since 1.0.0
     * @var string
     */
    protected $name = 'Paystack';

    /**
     * Gateway description
     *
     * @since 1.0.0
     * @var string
     */
    protected $description = 'Accept payments via Paystack - Cards, Bank Transfer, USSD, QR Code, Mobile Money';

    /**
     * Supported currencies
     *
     * @since 1.0.0
     * @var array
     */
    protected $supported_currencies = array('NGN', 'USD', 'GHS', 'ZAR', 'KES', 'XOF');

    /**
     * Supported countries
     *
     * @since 1.0.0
     * @var array
     */
    protected $supported_countries = array('NG', 'GH', 'ZA', 'KE');

    /**
     * API client instance
     *
     * @since 1.0.0
     * @var ChatShop_Paystack_API
     */
    private $api_client;

    /**
     * Webhook handler instance
     *
     * @since 1.0.0
     * @var ChatShop_Paystack_Webhook
     */
    private $webhook_handler;

    /**
     * Gateway settings
     *
     * @since 1.0.0
     * @var array
     */
    private $settings;

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        parent::__construct();

        $this->settings = chatshop_get_option('paystack', '', array());
        $this->enabled = $this->get_setting('enabled', false);

        $this->init_api_client();
        $this->init_webhook_handler();
        $this->init_hooks();
    }

    /**
     * Initialize API client
     *
     * @since 1.0.0
     */
    private function init_api_client()
    {
        if (!class_exists('ChatShop\ChatShop_Paystack_API')) {
            return;
        }

        $config = array(
            'test_mode' => $this->get_setting('test_mode', true)
        );

        $this->api_client = new ChatShop_Paystack_API($config);
    }

    /**
     * Initialize webhook handler
     *
     * @since 1.0.0
     */
    private function init_webhook_handler()
    {
        if (!class_exists('ChatShop\ChatShop_Paystack_Webhook')) {
            return;
        }

        $this->webhook_handler = new ChatShop_Paystack_Webhook();
    }

    /**
     * Initialize hooks
     *
     * @since 1.0.0
     */
    private function init_hooks()
    {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('chatshop_payment_completed', array($this, 'handle_successful_payment'), 10, 2);
    }

    /**
     * Process payment
     *
     * @since 1.0.0
     * @param float  $amount Payment amount
     * @param string $currency Currency code
     * @param array  $customer_data Customer information
     * @param array  $options Additional options
     * @return array Payment result
     */
    public function process_payment($amount, $currency, $customer_data, $options = array())
    {
        if (!$this->is_enabled()) {
            return $this->error_response(__('Paystack gateway is not enabled', 'chatshop'));
        }

        if (!$this->api_client) {
            return $this->error_response(__('Paystack API client not initialized', 'chatshop'));
        }

        // Validate input
        $validation = $this->validate_payment_data($amount, $currency, $customer_data);
        if (is_wp_error($validation)) {
            return $this->error_response($validation->get_error_message());
        }

        // Prepare transaction data
        $transaction_data = $this->prepare_transaction_data($amount, $currency, $customer_data, $options);

        // Initialize transaction
        $response = $this->api_client->initialize_transaction($transaction_data);

        if (is_wp_error($response)) {
            chatshop_log('Paystack transaction initialization failed: ' . $response->get_error_message(), 'error');
            return $this->error_response($response->get_error_message());
        }

        if (!isset($response['status']) || !$response['status']) {
            $error_msg = isset($response['message']) ? $response['message'] : __('Transaction initialization failed', 'chatshop');
            return $this->error_response($error_msg);
        }

        // Store transaction for tracking
        $this->store_transaction($response['data'], $customer_data);

        return $this->success_response(array(
            'reference' => $response['data']['reference'],
            'authorization_url' => $response['data']['authorization_url'],
            'access_code' => $response['data']['access_code'],
            'payment_url' => $response['data']['authorization_url']
        ));
    }

    /**
     * Verify transaction
     *
     * @since 1.0.0
     * @param string $reference Transaction reference
     * @return array Verification result
     */
    public function verify_transaction($reference)
    {
        if (empty($reference)) {
            return $this->error_response(__('Transaction reference is required', 'chatshop'));
        }

        if (!$this->api_client) {
            return $this->error_response(__('Paystack API client not initialized', 'chatshop'));
        }

        $response = $this->api_client->verify_transaction($reference);

        if (is_wp_error($response)) {
            chatshop_log('Paystack transaction verification failed: ' . $response->get_error_message(), 'error');
            return $this->error_response($response->get_error_message());
        }

        if (!isset($response['status']) || !$response['status']) {
            $error_msg = isset($response['message']) ? $response['message'] : __('Transaction verification failed', 'chatshop');
            return $this->error_response($error_msg);
        }

        $transaction_data = $response['data'];

        return $this->success_response(array(
            'reference' => $transaction_data['reference'],
            'status' => $transaction_data['status'],
            'amount' => $transaction_data['amount'],
            'currency' => $transaction_data['currency'],
            'paid_at' => isset($transaction_data['paid_at']) ? $transaction_data['paid_at'] : '',
            'channel' => isset($transaction_data['channel']) ? $transaction_data['channel'] : '',
            'customer' => isset($transaction_data['customer']) ? $transaction_data['customer'] : array(),
            'authorization' => isset($transaction_data['authorization']) ? $transaction_data['authorization'] : array()
        ));
    }

    /**
     * Generate payment link
     *
     * @since 1.0.0
     * @param float  $amount Payment amount
     * @param string $currency Currency code
     * @param array  $customer_data Customer information
     * @param array  $options Additional options
     * @return array Payment link result
     */
    public function generate_payment_link($amount, $currency, $customer_data, $options = array())
    {
        if (!$this->api_client) {
            return $this->error_response(__('Paystack API client not initialized', 'chatshop'));
        }

        // Prepare payment request data
        $payment_request_data = $this->prepare_payment_request_data($amount, $currency, $customer_data, $options);

        $response = $this->api_client->create_payment_request($payment_request_data);

        if (is_wp_error($response)) {
            chatshop_log('Paystack payment request creation failed: ' . $response->get_error_message(), 'error');
            return $this->error_response($response->get_error_message());
        }

        if (!isset($response['status']) || !$response['status']) {
            $error_msg = isset($response['message']) ? $response['message'] : __('Payment link generation failed', 'chatshop');
            return $this->error_response($error_msg);
        }

        $payment_data = $response['data'];

        // Store payment request for tracking
        $this->store_payment_request($payment_data, $customer_data);

        return $this->success_response(array(
            'payment_url' => "https://paystack.com/pay/{$payment_data['request_code']}",
            'request_code' => $payment_data['request_code'],
            'reference' => isset($payment_data['reference']) ? $payment_data['reference'] : '',
            'expires_at' => isset($payment_data['due_date']) ? $payment_data['due_date'] : ''
        ));
    }

    /**
     * Handle webhook
     *
     * @since 1.0.0
     * @param array $payload Webhook payload
     * @return bool Processing result
     */
    public function handle_webhook($payload)
    {
        if (!$this->webhook_handler) {
            return false;
        }

        // The webhook handler will process the payload
        return true; // Webhook handler manages its own processing
    }

    /**
     * Test connection
     *
     * @since 1.0.0
     * @return array Test result
     */
    public function test_connection()
    {
        if (!$this->api_client) {
            return array(
                'success' => false,
                'message' => __('API client not initialized', 'chatshop')
            );
        }

        return $this->api_client->test_connection();
    }

    /**
     * Get supported payment methods
     *
     * @since 1.0.0
     * @return array Payment methods
     */
    public function get_payment_methods()
    {
        return array(
            'card' => __('Card (Visa, Mastercard, Verve)', 'chatshop'),
            'bank' => __('Bank Transfer', 'chatshop'),
            'ussd' => __('USSD', 'chatshop'),
            'qr' => __('QR Code', 'chatshop'),
            'mobile_money' => __('Mobile Money', 'chatshop'),
            'bank_transfer' => __('Direct Bank Transfer', 'chatshop')
        );
    }

    /**
     * Validate payment data
     *
     * @since 1.0.0
     * @param float  $amount Payment amount
     * @param string $currency Currency code
     * @param array  $customer_data Customer data
     * @return true|WP_Error Validation result
     */
    private function validate_payment_data($amount, $currency, $customer_data)
    {
        // Validate amount
        if (!is_numeric($amount) || $amount <= 0) {
            return new \WP_Error('invalid_amount', __('Invalid payment amount', 'chatshop'));
        }

        // Validate currency
        if (!in_array(strtoupper($currency), $this->supported_currencies, true)) {
            return new \WP_Error('unsupported_currency', __('Currency not supported', 'chatshop'));
        }

        // Validate customer email
        if (empty($customer_data['email']) || !is_email($customer_data['email'])) {
            return new \WP_Error('invalid_email', __('Valid customer email is required', 'chatshop'));
        }

        // Check minimum amount
        $min_amount = $this->get_minimum_amount($currency);
        if ($amount < $min_amount) {
            return new \WP_Error(
                'amount_too_low',
                sprintf(__('Minimum amount is %s %s', 'chatshop'), $min_amount / 100, $currency)
            );
        }

        return true;
    }

    /**
     * Get minimum amount for currency
     *
     * @since 1.0.0
     * @param string $currency Currency code
     * @return int Minimum amount in subunits
     */
    private function get_minimum_amount($currency)
    {
        $minimums = array(
            'NGN' => 5000,   // ₦50.00
            'USD' => 200,    // $2.00
            'GHS' => 10,     // ₵0.10
            'ZAR' => 100,    // R1.00
            'KES' => 300,    // Ksh. 3.00
            'XOF' => 100     // XOF 1.00
        );

        return isset($minimums[strtoupper($currency)]) ? $minimums[strtoupper($currency)] : 100;
    }

    /**
     * Prepare transaction data
     *
     * @since 1.0.0
     * @param float  $amount Payment amount
     * @param string $currency Currency code
     * @param array  $customer_data Customer data
     * @param array  $options Additional options
     * @return array Transaction data
     */
    private function prepare_transaction_data($amount, $currency, $customer_data, $options)
    {
        // Convert amount to subunits (kobo/cents)
        $amount_in_subunits = intval($amount * 100);

        $data = array(
            'email' => sanitize_email($customer_data['email']),
            'amount' => $amount_in_subunits,
            'currency' => strtoupper($currency),
            'reference' => $this->generate_reference($options),
            'callback_url' => $this->get_callback_url(),
            'metadata' => $this->prepare_metadata($customer_data, $options)
        );

        // Add optional fields
        if (!empty($customer_data['first_name'])) {
            $data['metadata']['first_name'] = sanitize_text_field($customer_data['first_name']);
        }

        if (!empty($customer_data['last_name'])) {
            $data['metadata']['last_name'] = sanitize_text_field($customer_data['last_name']);
        }

        if (!empty($customer_data['phone'])) {
            $data['metadata']['phone'] = sanitize_text_field($customer_data['phone']);
        }

        // Add channels if specified
        if (!empty($options['channels']) && is_array($options['channels'])) {
            $data['channels'] = array_map('sanitize_text_field', $options['channels']);
        }

        return apply_filters('chatshop_paystack_transaction_data', $data, $customer_data, $options);
    }

    /**
     * Prepare payment request data
     *
     * @since 1.0.0
     * @param float  $amount Payment amount
     * @param string $currency Currency code
     * @param array  $customer_data Customer data
     * @param array  $options Additional options
     * @return array Payment request data
     */
    private function prepare_payment_request_data($amount, $currency, $customer_data, $options)
    {
        $amount_in_subunits = intval($amount * 100);

        $data = array(
            'amount' => $amount_in_subunits,
            'currency' => strtoupper($currency),
            'customer' => sanitize_email($customer_data['email']),
            'description' => isset($options['description']) ? sanitize_textarea_field($options['description']) :
                sprintf(__('Payment request for %s', 'chatshop'), get_bloginfo('name')),
            'due_date' => isset($options['due_date']) ? sanitize_text_field($options['due_date']) :
                date('Y-m-d', strtotime('+7 days')) // Default 7 days from now
        );

        // Add line items if provided
        if (!empty($options['line_items']) && is_array($options['line_items'])) {
            $data['line_items'] = $this->sanitize_line_items($options['line_items']);
        }

        // Add tax if provided
        if (!empty($options['tax']) && is_array($options['tax'])) {
            $data['tax'] = $this->sanitize_tax_items($options['tax']);
        }

        return apply_filters('chatshop_paystack_payment_request_data', $data, $customer_data, $options);
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
            if (!is_array($item) || empty($item['name']) || empty($item['amount'])) {
                continue;
            }

            $clean_item = array(
                'name' => sanitize_text_field($item['name']),
                'amount' => absint($item['amount'])
            );

            if (!empty($item['quantity'])) {
                $clean_item['quantity'] = absint($item['quantity']);
            }

            $sanitized[] = $clean_item;
        }

        return $sanitized;
    }

    /**
     * Sanitize tax items
     *
     * @since 1.0.0
     * @param array $tax_items Raw tax items
     * @return array Sanitized tax items
     */
    private function sanitize_tax_items($tax_items)
    {
        $sanitized = array();

        foreach ($tax_items as $tax) {
            if (!is_array($tax) || empty($tax['name']) || empty($tax['amount'])) {
                continue;
            }

            $sanitized[] = array(
                'name' => sanitize_text_field($tax['name']),
                'amount' => absint($tax['amount'])
            );
        }

        return $sanitized;
    }

    /**
     * Generate transaction reference
     *
     * @since 1.0.0
     * @param array $options Options array
     * @return string Transaction reference
     */
    private function generate_reference($options)
    {
        if (!empty($options['reference'])) {
            return sanitize_text_field($options['reference']);
        }

        $prefix = 'chatshop_';
        $timestamp = time();
        $random = wp_generate_password(8, false);

        return $prefix . $timestamp . '_' . $random;
    }

    /**
     * Prepare metadata
     *
     * @since 1.0.0
     * @param array $customer_data Customer data
     * @param array $options Additional options
     * @return array Metadata
     */
    private function prepare_metadata($customer_data, $options)
    {
        $metadata = array(
            'plugin' => 'chatshop',
            'version' => CHATSHOP_VERSION,
            'site_url' => get_site_url()
        );

        // Add custom metadata from options
        if (!empty($options['metadata']) && is_array($options['metadata'])) {
            $metadata = array_merge($metadata, $options['metadata']);
        }

        // Add customer ID if available
        if (!empty($customer_data['customer_id'])) {
            $metadata['customer_id'] = sanitize_text_field($customer_data['customer_id']);
        }

        // Add order ID if available
        if (!empty($options['order_id'])) {
            $metadata['order_id'] = sanitize_text_field($options['order_id']);
        }

        return $metadata;
    }

    /**
     * Get callback URL
     *
     * @since 1.0.0
     * @return string Callback URL
     */
    private function get_callback_url()
    {
        $callback_url = $this->get_setting('callback_url', '');

        if (empty($callback_url)) {
            $callback_url = home_url('/chatshop/payment/callback/paystack/');
        }

        return esc_url($callback_url);
    }

    /**
     * Store transaction
     *
     * @since 1.0.0
     * @param array $transaction_data Transaction data from API
     * @param array $customer_data Customer data
     */
    private function store_transaction($transaction_data, $customer_data)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'chatshop_transactions';

        $data = array(
            'reference' => sanitize_text_field($transaction_data['reference']),
            'gateway' => 'paystack',
            'status' => 'pending',
            'amount' => isset($transaction_data['amount']) ? absint($transaction_data['amount']) : 0,
            'currency' => isset($transaction_data['currency']) ? sanitize_text_field($transaction_data['currency']) : '',
            'customer_email' => sanitize_email($customer_data['email']),
            'access_code' => sanitize_text_field($transaction_data['access_code']),
            'authorization_url' => esc_url_raw($transaction_data['authorization_url']),
            'created_at' => current_time('mysql')
        );

        $wpdb->insert($table_name, $data);

        if ($wpdb->last_error) {
            chatshop_log('Database error storing transaction: ' . $wpdb->last_error, 'error');
        }
    }

    /**
     * Store payment request
     *
     * @since 1.0.0
     * @param array $payment_data Payment request data from API
     * @param array $customer_data Customer data
     */
    private function store_payment_request($payment_data, $customer_data)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'chatshop_payment_requests';

        $data = array(
            'request_code' => sanitize_text_field($payment_data['request_code']),
            'gateway' => 'paystack',
            'status' => 'pending',
            'amount' => isset($payment_data['amount']) ? absint($payment_data['amount']) : 0,
            'currency' => isset($payment_data['currency']) ? sanitize_text_field($payment_data['currency']) : '',
            'customer_email' => sanitize_email($customer_data['email']),
            'description' => isset($payment_data['description']) ? sanitize_textarea_field($payment_data['description']) : '',
            'due_date' => isset($payment_data['due_date']) ? sanitize_text_field($payment_data['due_date']) : null,
            'created_at' => current_time('mysql')
        );

        $wpdb->insert($table_name, $data);

        if ($wpdb->last_error) {
            chatshop_log('Database error storing payment request: ' . $wpdb->last_error, 'error');
        }
    }

    /**
     * Handle successful payment
     *
     * @since 1.0.0
     * @param array  $payment_data Payment data
     * @param string $gateway Gateway ID
     */
    public function handle_successful_payment($payment_data, $gateway)
    {
        if ($gateway !== 'paystack') {
            return;
        }

        // Update analytics
        $this->update_payment_analytics($payment_data);

        // Send confirmation if WhatsApp is enabled
        $this->send_whatsapp_confirmation($payment_data);

        // Trigger custom action
        do_action('chatshop_paystack_payment_success', $payment_data);
    }

    /**
     * Update payment analytics
     *
     * @since 1.0.0
     * @param array $payment_data Payment data
     */
    private function update_payment_analytics($payment_data)
    {
        if (!chatshop_is_premium_feature_available('advanced_analytics')) {
            return;
        }

        $analytics_manager = chatshop_get_component('analytics_manager');

        if ($analytics_manager) {
            $analytics_manager->record_payment($payment_data, 'paystack');
        }
    }

    /**
     * Send WhatsApp confirmation
     *
     * @since 1.0.0
     * @param array $payment_data Payment data
     */
    private function send_whatsapp_confirmation($payment_data)
    {
        if (!chatshop_is_premium_feature_available('whatsapp_automation')) {
            return;
        }

        // Implementation depends on WhatsApp component
        // This will be handled by the webhook notification system
    }

    /**
     * Enqueue scripts
     *
     * @since 1.0.0
     */
    public function enqueue_scripts()
    {
        if (!$this->is_enabled()) {
            return;
        }

        // Enqueue Paystack Popup script for inline payments
        wp_enqueue_script(
            'paystack-popup',
            'https://js.paystack.co/v2/inline.js',
            array(),
            '2.0',
            true
        );

        // Enqueue our custom script
        wp_enqueue_script(
            'chatshop-paystack',
            CHATSHOP_PLUGIN_URL . 'assets/js/components/payment/paystack.js',
            array('jquery', 'paystack-popup'),
            CHATSHOP_VERSION,
            true
        );

        // Localize script with settings
        wp_localize_script('chatshop-paystack', 'chatshop_paystack', array(
            'public_key' => $this->api_client ? $this->api_client->get_public_key_for_frontend() : '',
            'test_mode' => $this->get_setting('test_mode', true),
            'currency' => get_option('woocommerce_currency', 'NGN'),
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('chatshop_paystack_nonce')
        ));
    }

    /**
     * Get gateway setting
     *
     * @since 1.0.0
     * @param string $key Setting key
     * @param mixed  $default Default value
     * @return mixed Setting value
     */
    private function get_setting($key, $default = null)
    {
        return isset($this->settings[$key]) ? $this->settings[$key] : $default;
    }

    /**
     * Check if gateway is enabled
     *
     * @since 1.0.0
     * @return bool Enabled status
     */
    public function is_enabled()
    {
        return $this->enabled && $this->is_properly_configured();
    }

    /**
     * Check if gateway is properly configured
     *
     * @since 1.0.0
     * @return bool Configuration status
     */
    private function is_properly_configured()
    {
        $test_mode = $this->get_setting('test_mode', true);

        if ($test_mode) {
            $secret_key = $this->get_setting('test_secret_key', '');
            $public_key = $this->get_setting('test_public_key', '');
        } else {
            $secret_key = $this->get_setting('live_secret_key', '');
            $public_key = $this->get_setting('live_public_key', '');
        }

        return !empty($secret_key) && !empty($public_key);
    }

    /**
     * Get gateway info for admin display
     *
     * @since 1.0.0
     * @return array Gateway information
     */
    public function get_gateway_info()
    {
        return array(
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'enabled' => $this->is_enabled(),
            'configured' => $this->is_properly_configured(),
            'test_mode' => $this->get_setting('test_mode', true),
            'supported_currencies' => $this->supported_currencies,
            'supported_countries' => $this->supported_countries,
            'webhook_url' => ChatShop_Paystack_Webhook::get_webhook_url()
        );
    }

    /**
     * Get configuration fields for admin
     *
     * @since 1.0.0
     * @return array Configuration fields
     */
    public function get_config_fields()
    {
        return array(
            'enabled' => array(
                'type' => 'checkbox',
                'label' => __('Enable Paystack', 'chatshop'),
                'description' => __('Enable Paystack payment gateway', 'chatshop'),
                'default' => false
            ),
            'test_mode' => array(
                'type' => 'checkbox',
                'label' => __('Test Mode', 'chatshop'),
                'description' => __('Enable test mode for development and testing', 'chatshop'),
                'default' => true
            ),
            'test_public_key' => array(
                'type' => 'text',
                'label' => __('Test Public Key', 'chatshop'),
                'description' => __('Your Paystack test public key', 'chatshop'),
                'placeholder' => 'pk_test_...'
            ),
            'test_secret_key' => array(
                'type' => 'password',
                'label' => __('Test Secret Key', 'chatshop'),
                'description' => __('Your Paystack test secret key', 'chatshop'),
                'placeholder' => 'sk_test_...'
            ),
            'live_public_key' => array(
                'type' => 'text',
                'label' => __('Live Public Key', 'chatshop'),
                'description' => __('Your Paystack live public key', 'chatshop'),
                'placeholder' => 'pk_live_...'
            ),
            'live_secret_key' => array(
                'type' => 'password',
                'label' => __('Live Secret Key', 'chatshop'),
                'description' => __('Your Paystack live secret key', 'chatshop'),
                'placeholder' => 'sk_live_...'
            ),
            'callback_url' => array(
                'type' => 'url',
                'label' => __('Callback URL', 'chatshop'),
                'description' => __('URL to redirect users after payment (optional)', 'chatshop'),
                'placeholder' => home_url('/thank-you/')
            )
        );
    }

    /**
     * Get webhook URL for configuration
     *
     * @since 1.0.0
     * @return string Webhook URL
     */
    public function get_webhook_url()
    {
        return ChatShop_Paystack_Webhook::get_webhook_url();
    }
}
