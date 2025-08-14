<?php

/**
 * Abstract Payment Gateway Class
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
 * Abstract payment gateway class
 *
 * @since 1.0.0
 */
abstract class ChatShop_Abstract_Payment_Gateway
{
    /**
     * Gateway ID
     *
     * @var string
     * @since 1.0.0
     */
    protected $id;

    /**
     * Gateway title
     *
     * @var string
     * @since 1.0.0
     */
    protected $title;

    /**
     * Gateway description
     *
     * @var string
     * @since 1.0.0
     */
    protected $description;

    /**
     * Gateway enabled status
     *
     * @var bool
     * @since 1.0.0
     */
    protected $enabled = false;

    /**
     * Test mode status
     *
     * @var bool
     * @since 1.0.0
     */
    protected $test_mode = true;

    /**
     * Gateway settings
     *
     * @var array
     * @since 1.0.0
     */
    protected $settings = array();

    /**
     * Supported currencies
     *
     * @var array
     * @since 1.0.0
     */
    protected $supported_currencies = array();

    /**
     * Supported countries
     *
     * @var array
     * @since 1.0.0
     */
    protected $supported_countries = array();

    /**
     * Gateway fees
     *
     * @var array
     * @since 1.0.0
     */
    protected $fees = array();

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        $this->init();
        $this->load_settings();
        $this->init_hooks();
    }

    /**
     * Initialize gateway
     *
     * @since 1.0.0
     */
    abstract protected function init();

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
    abstract public function process_payment($amount, $currency, $customer_data, $options = array());

    /**
     * Verify transaction
     *
     * @param string $reference Transaction reference
     * @return array Verification result
     * @since 1.0.0
     */
    abstract public function verify_transaction($reference);

    /**
     * Handle webhook
     *
     * @param array $payload Webhook payload
     * @return bool Whether webhook was processed successfully
     * @since 1.0.0
     */
    abstract public function handle_webhook($payload);

    /**
     * Generate unique transaction reference
     *
     * @param string $prefix Optional prefix
     * @return string Transaction reference
     * @since 1.0.0
     */
    protected function generate_reference($prefix = '')
    {
        $prefix = !empty($prefix) ? $prefix . '_' : 'CS_';
        return $prefix . strtoupper(uniqid() . '_' . wp_generate_password(6, false));
    }

    /**
     * Get gateway ID
     *
     * @return string
     * @since 1.0.0
     */
    public function get_id()
    {
        return $this->id;
    }

    /**
     * Get gateway title
     *
     * @return string
     * @since 1.0.0
     */
    public function get_title()
    {
        return $this->title;
    }

    /**
     * Get gateway description
     *
     * @return string
     * @since 1.0.0
     */
    public function get_description()
    {
        return $this->description;
    }

    /**
     * Check if gateway is enabled
     *
     * @return bool
     * @since 1.0.0
     */
    public function is_enabled()
    {
        return $this->enabled;
    }

    /**
     * Check if gateway is in test mode
     *
     * @return bool
     * @since 1.0.0
     */
    public function is_test_mode()
    {
        return $this->test_mode;
    }

    /**
     * Get supported currencies
     *
     * @return array
     * @since 1.0.0
     */
    public function get_supported_currencies()
    {
        return $this->supported_currencies;
    }

    /**
     * Get supported countries
     *
     * @return array
     * @since 1.0.0
     */
    public function get_supported_countries()
    {
        return $this->supported_countries;
    }

    /**
     * Check if currency is supported
     *
     * @param string $currency Currency code
     * @return bool
     * @since 1.0.0
     */
    public function supports_currency($currency)
    {
        return in_array(strtoupper($currency), $this->supported_currencies, true);
    }

    /**
     * Check if country is supported
     *
     * @param string $country Country code
     * @return bool
     * @since 1.0.0
     */
    public function supports_country($country)
    {
        return in_array(strtoupper($country), $this->supported_countries, true);
    }

    /**
     * Load gateway settings
     *
     * @since 1.0.0
     */
    protected function load_settings()
    {
        $this->settings = get_option("chatshop_{$this->id}_options", array());

        // Set common properties from settings
        $this->enabled = isset($this->settings['enabled']) ? (bool) $this->settings['enabled'] : false;
        $this->test_mode = isset($this->settings['test_mode']) ? (bool) $this->settings['test_mode'] : true;
    }

    /**
     * Get setting value
     *
     * @param string $key Setting key
     * @param mixed  $default Default value
     * @return mixed
     * @since 1.0.0
     */
    protected function get_setting($key, $default = null)
    {
        return isset($this->settings[$key]) ? $this->settings[$key] : $default;
    }

    /**
     * Update setting value
     *
     * @param string $key Setting key
     * @param mixed  $value Setting value
     * @since 1.0.0
     */
    protected function update_setting($key, $value)
    {
        $this->settings[$key] = $value;
        update_option("chatshop_{$this->id}_options", $this->settings);
    }

    /**
     * Initialize hooks
     *
     * @since 1.0.0
     */
    protected function init_hooks()
    {
        // Gateway-specific hooks can be added by child classes
        do_action("chatshop_{$this->id}_gateway_init", $this);
    }

    /**
     * Format amount for gateway
     *
     * @param float  $amount Amount to format
     * @param string $currency Currency code
     * @return int Amount in smallest currency unit
     * @since 1.0.0
     */
    protected function format_amount($amount, $currency)
    {
        $currency = strtoupper($currency);

        // XOF doesn't have subunits but still multiply by 100
        if ($currency === 'XOF') {
            return intval($amount * 100);
        }

        // Most currencies use 100 subunits (cents, kobo, etc.)
        return intval($amount * 100);
    }

    /**
     * Validate payment data
     *
     * @param float  $amount Payment amount
     * @param string $currency Currency code
     * @param array  $customer_data Customer information
     * @return bool|WP_Error True if valid, WP_Error if invalid
     * @since 1.0.0
     */
    protected function validate_payment_data($amount, $currency, $customer_data)
    {
        // Check if gateway is enabled
        if (!$this->is_enabled()) {
            return new \WP_Error('gateway_disabled', __('This payment gateway is currently disabled.', 'chatshop'));
        }

        // Validate amount
        if (!is_numeric($amount) || $amount <= 0) {
            return new \WP_Error('invalid_amount', __('Invalid payment amount.', 'chatshop'));
        }

        // Validate currency
        if (!$this->supports_currency($currency)) {
            return new \WP_Error('unsupported_currency', __('This currency is not supported by this payment gateway.', 'chatshop'));
        }

        // Validate customer email
        if (empty($customer_data['email']) || !is_email($customer_data['email'])) {
            return new \WP_Error('invalid_email', __('Valid customer email is required.', 'chatshop'));
        }

        return true;
    }

    /**
     * Log gateway activity
     *
     * @param string $message Log message
     * @param string $level Log level (info, warning, error)
     * @param array  $context Additional context
     * @since 1.0.0
     */
    protected function log($message, $level = 'info', $context = array())
    {
        if (function_exists('chatshop_log')) {
            $context['gateway'] = $this->id;
            chatshop_log($message, $level, $context);
        }
    }

    /**
     * Get gateway fees for amount
     *
     * @param float  $amount Payment amount
     * @param string $currency Currency code
     * @return array Fee breakdown
     * @since 1.0.0
     */
    public function calculate_fees($amount, $currency)
    {
        $fees = array(
            'gateway_fee' => 0,
            'total_amount' => $amount,
            'currency' => $currency
        );

        if (isset($this->fees[$currency])) {
            $fee_config = $this->fees[$currency];

            // Calculate percentage fee
            if (isset($fee_config['percentage'])) {
                $fees['gateway_fee'] += ($amount * $fee_config['percentage']) / 100;
            }

            // Add fixed fee
            if (isset($fee_config['fixed'])) {
                $fees['gateway_fee'] += $fee_config['fixed'];
            }

            // Apply fee cap if set
            if (isset($fee_config['cap']) && $fees['gateway_fee'] > $fee_config['cap']) {
                $fees['gateway_fee'] = $fee_config['cap'];
            }
        }

        $fees['total_amount'] = $amount + $fees['gateway_fee'];

        return $fees;
    }

    /**
     * Test gateway connection
     *
     * @return array Test result
     * @since 1.0.0
     */
    public function test_connection()
    {
        return array(
            'success' => false,
            'message' => __('Connection test not implemented for this gateway.', 'chatshop')
        );
    }

    /**
     * Get webhook URL for this gateway
     *
     * @return string Webhook URL
     * @since 1.0.0
     */
    public function get_webhook_url()
    {
        return add_query_arg(
            array(
                'action' => 'chatshop_webhook',
                'gateway' => $this->id
            ),
            admin_url('admin-ajax.php')
        );
    }

    /**
     * Get gateway icon URL
     *
     * @return string Icon URL
     * @since 1.0.0
     */
    public function get_icon_url()
    {
        $icon_path = CHATSHOP_PLUGIN_URL . "assets/icons/{$this->id}.svg";

        if (file_exists(CHATSHOP_PLUGIN_DIR . "assets/icons/{$this->id}.svg")) {
            return $icon_path;
        }

        return CHATSHOP_PLUGIN_URL . "assets/icons/default.svg";
    }

    /**
     * Get payment form fields
     *
     * @return array Form fields configuration
     * @since 1.0.0
     */
    public function get_payment_form_fields()
    {
        return array();
    }

    /**
     * Render payment form
     *
     * @param array $args Form arguments
     * @return string HTML form
     * @since 1.0.0
     */
    public function render_payment_form($args = array())
    {
        return '';
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
                'title' => __('Enable Gateway', 'chatshop'),
                'type' => 'checkbox',
                'description' => __('Enable this payment gateway', 'chatshop'),
                'default' => 'no'
            ),
            'test_mode' => array(
                'title' => __('Test Mode', 'chatshop'),
                'type' => 'checkbox',
                'description' => __('Enable test mode for this gateway', 'chatshop'),
                'default' => 'yes'
            )
        );
    }
}
