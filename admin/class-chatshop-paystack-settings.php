<?php

/**
 * Paystack Admin Settings for ChatShop
 *
 * @package ChatShop
 * @subpackage Admin\Settings
 * @since 1.0.0
 */

namespace ChatShop;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ChatShop Paystack Settings Class
 *
 * Handles Paystack gateway configuration in the admin interface.
 *
 * @since 1.0.0
 */
class ChatShop_Paystack_Settings
{
    /**
     * Settings page slug
     *
     * @var string
     * @since 1.0.0
     */
    private $page_slug = 'chatshop-paystack-settings';

    /**
     * Option group name
     *
     * @var string
     * @since 1.0.0
     */
    private $option_group = 'chatshop_paystack_options';

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /**
     * Register Paystack settings
     *
     * @since 1.0.0
     */
    public function register_settings()
    {
        // Register setting group
        register_setting(
            $this->option_group,
            $this->option_group,
            array(
                'sanitize_callback' => array($this, 'sanitize_settings'),
                'default' => $this->get_default_settings(),
            )
        );

        // Add settings section
        add_settings_section(
            'paystack_general',
            __('General Settings', 'chatshop'),
            array($this, 'render_general_section'),
            $this->page_slug
        );

        // Add settings fields
        $this->add_settings_fields();
    }

    /**
     * Add settings fields
     *
     * @since 1.0.0
     */
    private function add_settings_fields()
    {
        $fields = array(
            array(
                'id' => 'enabled',
                'title' => __('Enable Paystack', 'chatshop'),
                'callback' => 'render_checkbox_field',
                'description' => __('Enable Paystack payment gateway', 'chatshop'),
            ),
            array(
                'id' => 'test_mode',
                'title' => __('Test Mode', 'chatshop'),
                'callback' => 'render_checkbox_field',
                'description' => __('Enable test mode for development', 'chatshop'),
            ),
            array(
                'id' => 'test_public_key',
                'title' => __('Test Public Key', 'chatshop'),
                'callback' => 'render_text_field',
                'description' => __('Your Paystack test public key', 'chatshop'),
                'type' => 'password',
            ),
            array(
                'id' => 'test_secret_key',
                'title' => __('Test Secret Key', 'chatshop'),
                'callback' => 'render_text_field',
                'description' => __('Your Paystack test secret key', 'chatshop'),
                'type' => 'password',
            ),
            array(
                'id' => 'live_public_key',
                'title' => __('Live Public Key', 'chatshop'),
                'callback' => 'render_text_field',
                'description' => __('Your Paystack live public key', 'chatshop'),
                'type' => 'password',
            ),
            array(
                'id' => 'live_secret_key',
                'title' => __('Live Secret Key', 'chatshop'),
                'callback' => 'render_text_field',
                'description' => __('Your Paystack live secret key', 'chatshop'),
                'type' => 'password',
            ),
            array(
                'id' => 'supported_currencies',
                'title' => __('Supported Currencies', 'chatshop'),
                'callback' => 'render_multiselect_field',
                'description' => __('Select currencies to accept', 'chatshop'),
                'options' => $this->get_currency_options(),
            ),
            array(
                'id' => 'payment_methods',
                'title' => __('Payment Methods', 'chatshop'),
                'callback' => 'render_multiselect_field',
                'description' => __('Select payment methods to enable', 'chatshop'),
                'options' => $this->get_payment_method_options(),
            ),
            array(
                'id' => 'webhook_url',
                'title' => __('Webhook URL', 'chatshop'),
                'callback' => 'render_readonly_field',
                'description' => __('Copy this URL to your Paystack dashboard', 'chatshop'),
                'value' => $this->get_webhook_url(),
            ),
        );

        foreach ($fields as $field) {
            add_settings_field(
                $field['id'],
                $field['title'],
                array($this, $field['callback']),
                $this->page_slug,
                'paystack_general',
                $field
            );
        }
    }

    /**
     * Render general section description
     *
     * @since 1.0.0
     */
    public function render_general_section()
    {
        echo '<p>' . esc_html__('Configure your Paystack payment gateway settings.', 'chatshop') . '</p>';

        // Display connection status
        $this->render_connection_status();
    }

    /**
     * Render connection status
     *
     * @since 1.0.0
     */
    private function render_connection_status()
    {
        $options = get_option($this->option_group, array());
        $is_configured = $this->is_gateway_configured($options);

        if ($is_configured) {
            $status_class = 'notice-success';
            $status_text = __('Connected', 'chatshop');
            $status_icon = '✓';
        } else {
            $status_class = 'notice-warning';
            $status_text = __('Not Configured', 'chatshop');
            $status_icon = '⚠';
        }

        echo '<div class="notice ' . esc_attr($status_class) . ' inline">';
        echo '<p><span class="dashicons-before">' . esc_html($status_icon) . '</span> ';
        echo '<strong>' . esc_html__('Status:', 'chatshop') . '</strong> ' . esc_html($status_text);

        if ($is_configured) {
            echo ' <button type="button" id="test-paystack-connection" class="button button-secondary">';
            echo esc_html__('Test Connection', 'chatshop') . '</button>';
        }

        echo '</p></div>';
    }

    /**
     * Render checkbox field
     *
     * @param array $field Field configuration
     * @since 1.0.0
     */
    public function render_checkbox_field($field)
    {
        $options = get_option($this->option_group, array());
        $value = isset($options[$field['id']]) ? $options[$field['id']] : false;
        $checked = checked(true, $value, false);

        echo '<label>';
        echo '<input type="checkbox" name="' . esc_attr($this->option_group) . '[' . esc_attr($field['id']) . ']" value="1" ' . $checked . ' />';
        echo ' ' . esc_html($field['description']);
        echo '</label>';
    }

    /**
     * Render text field
     *
     * @param array $field Field configuration
     * @since 1.0.0
     */
    public function render_text_field($field)
    {
        $options = get_option($this->option_group, array());
        $value = isset($options[$field['id']]) ? $options[$field['id']] : '';
        $type = isset($field['type']) ? $field['type'] : 'text';

        // Decrypt encrypted fields for display
        if (in_array($field['id'], array('test_secret_key', 'live_secret_key'), true)) {
            $value = $this->decrypt_field_value($value);
            $placeholder = str_repeat('*', 20);
        } else {
            $placeholder = '';
        }

        echo '<input type="' . esc_attr($type) . '" ';
        echo 'name="' . esc_attr($this->option_group) . '[' . esc_attr($field['id']) . ']" ';
        echo 'value="' . esc_attr($value) . '" ';
        echo 'placeholder="' . esc_attr($placeholder) . '" ';
        echo 'class="regular-text" />';

        if (!empty($field['description'])) {
            echo '<p class="description">' . esc_html($field['description']) . '</p>';
        }
    }

    /**
     * Render readonly field
     *
     * @param array $field Field configuration
     * @since 1.0.0
     */
    public function render_readonly_field($field)
    {
        $value = isset($field['value']) ? $field['value'] : '';

        echo '<input type="text" value="' . esc_attr($value) . '" class="regular-text" readonly />';
        echo ' <button type="button" class="button button-secondary copy-webhook-url" data-clipboard-text="' . esc_attr($value) . '">';
        echo esc_html__('Copy', 'chatshop') . '</button>';

        if (!empty($field['description'])) {
            echo '<p class="description">' . esc_html($field['description']) . '</p>';
        }
    }

    /**
     * Render multiselect field
     *
     * @param array $field Field configuration
     * @since 1.0.0
     */
    public function render_multiselect_field($field)
    {
        $options = get_option($this->option_group, array());
        $selected_values = isset($options[$field['id']]) ? (array) $options[$field['id']] : array();

        echo '<select name="' . esc_attr($this->option_group) . '[' . esc_attr($field['id']) . '][]" multiple class="regular-text">';

        foreach ($field['options'] as $value => $label) {
            $selected = in_array($value, $selected_values, true) ? 'selected' : '';
            echo '<option value="' . esc_attr($value) . '" ' . $selected . '>' . esc_html($label) . '</option>';
        }

        echo '</select>';

        if (!empty($field['description'])) {
            echo '<p class="description">' . esc_html($field['description']) . '</p>';
        }
    }

    /**
     * Sanitize settings before saving
     *
     * @param array $input Raw input data
     * @return array Sanitized data
     * @since 1.0.0
     */
    public function sanitize_settings($input)
    {
        $sanitized = array();

        // Boolean fields
        $boolean_fields = array('enabled', 'test_mode');
        foreach ($boolean_fields as $field) {
            $sanitized[$field] = isset($input[$field]) ? (bool) $input[$field] : false;
        }

        // Text fields
        $text_fields = array('test_public_key', 'live_public_key');
        foreach ($text_fields as $field) {
            $sanitized[$field] = isset($input[$field]) ? sanitize_text_field($input[$field]) : '';
        }

        // Encrypted fields
        $encrypted_fields = array('test_secret_key', 'live_secret_key');
        foreach ($encrypted_fields as $field) {
            if (isset($input[$field]) && !empty($input[$field])) {
                $sanitized[$field] = $this->encrypt_field_value($input[$field]);
            } else {
                // Keep existing value if empty (don't overwrite)
                $existing_options = get_option($this->option_group, array());
                $sanitized[$field] = isset($existing_options[$field]) ? $existing_options[$field] : '';
            }
        }

        // Array fields
        $array_fields = array('supported_currencies', 'payment_methods');
        foreach ($array_fields as $field) {
            if (isset($input[$field]) && is_array($input[$field])) {
                $sanitized[$field] = array_map('sanitize_text_field', $input[$field]);
            } else {
                $sanitized[$field] = array();
            }
        }

        return $sanitized;
    }

    /**
     * Encrypt field value
     *
     * @param string $value Value to encrypt
     * @return string Encrypted value
     * @since 1.0.0
     */
    private function encrypt_field_value($value)
    {
        if (empty($value)) {
            return '';
        }

        $key = wp_salt('auth');
        return openssl_encrypt($value, 'AES-256-CBC', $key, 0, substr($key, 0, 16));
    }

    /**
     * Decrypt field value
     *
     * @param string $encrypted_value Encrypted value
     * @return string Decrypted value
     * @since 1.0.0
     */
    private function decrypt_field_value($encrypted_value)
    {
        if (empty($encrypted_value)) {
            return '';
        }

        $key = wp_salt('auth');
        return openssl_decrypt($encrypted_value, 'AES-256-CBC', $key, 0, substr($key, 0, 16));
    }

    /**
     * Check if gateway is configured
     *
     * @param array $options Gateway options
     * @return bool Whether gateway is configured
     * @since 1.0.0
     */
    private function is_gateway_configured($options)
    {
        if (empty($options['enabled'])) {
            return false;
        }

        if (!empty($options['test_mode'])) {
            return !empty($options['test_secret_key']) && !empty($options['test_public_key']);
        } else {
            return !empty($options['live_secret_key']) && !empty($options['live_public_key']);
        }
    }

    /**
     * Get webhook URL
     *
     * @return string Webhook URL
     * @since 1.0.0
     */
    private function get_webhook_url()
    {
        return add_query_arg(array(
            'action' => 'chatshop_webhook',
            'gateway' => 'paystack',
        ), admin_url('admin-ajax.php'));
    }

    /**
     * Get currency options
     *
     * @return array Currency options
     * @since 1.0.0
     */
    private function get_currency_options()
    {
        return array(
            'NGN' => __('Nigerian Naira (NGN)', 'chatshop'),
            'USD' => __('US Dollar (USD)', 'chatshop'),
            'GHS' => __('Ghanaian Cedi (GHS)', 'chatshop'),
            'ZAR' => __('South African Rand (ZAR)', 'chatshop'),
            'KES' => __('Kenyan Shilling (KES)', 'chatshop'),
            'XOF' => __('West African CFA Franc (XOF)', 'chatshop'),
        );
    }

    /**
     * Get payment method options
     *
     * @return array Payment method options
     * @since 1.0.0
     */
    private function get_payment_method_options()
    {
        return array(
            'card' => __('Cards (Visa, Mastercard, Verve)', 'chatshop'),
            'bank' => __('Bank Transfer', 'chatshop'),
            'ussd' => __('USSD', 'chatshop'),
            'mobile_money' => __('Mobile Money', 'chatshop'),
            'bank_transfer' => __('Dedicated Bank Account', 'chatshop'),
            'qr' => __('QR Code', 'chatshop'),
        );
    }

    /**
     * Get default settings
     *
     * @return array Default settings
     * @since 1.0.0
     */
    private function get_default_settings()
    {
        return array(
            'enabled' => false,
            'test_mode' => true,
            'test_public_key' => '',
            'test_secret_key' => '',
            'live_public_key' => '',
            'live_secret_key' => '',
            'supported_currencies' => array('NGN'),
            'payment_methods' => array('card', 'bank'),
        );
    }

    /**
     * Enqueue admin scripts and styles
     *
     * @param string $hook_suffix Current admin page hook suffix
     * @since 1.0.0
     */
    public function enqueue_scripts($hook_suffix)
    {
        // Only load on our settings page
        if (strpos($hook_suffix, $this->page_slug) === false) {
            return;
        }

        wp_enqueue_script(
            'chatshop-paystack-admin',
            CHATSHOP_PLUGIN_URL . 'admin/js/paystack-settings.js',
            array('jquery'),
            CHATSHOP_VERSION,
            true
        );

        wp_localize_script(
            'chatshop-paystack-admin',
            'chatshop_paystack_admin',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('chatshop_admin_nonce'),
                'strings' => array(
                    'testing' => __('Testing connection...', 'chatshop'),
                    'success' => __('Connection successful!', 'chatshop'),
                    'error' => __('Connection failed. Please check your settings.', 'chatshop'),
                    'copied' => __('Copied to clipboard!', 'chatshop'),
                ),
            )
        );

        wp_enqueue_style(
            'chatshop-paystack-admin',
            CHATSHOP_PLUGIN_URL . 'admin/css/paystack-settings.css',
            array(),
            CHATSHOP_VERSION
        );
    }

    /**
     * Render settings page
     *
     * @since 1.0.0
     */
    public function render_settings_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'chatshop'));
        }

?>
        <div class="wrap">
            <h1><?php esc_html_e('Paystack Settings', 'chatshop'); ?></h1>

            <div class="chatshop-settings-wrapper">
                <div class="chatshop-settings-main">
                    <form method="post" action="options.php">
                        <?php
                        settings_fields($this->option_group);
                        do_settings_sections($this->page_slug);
                        submit_button(__('Save Settings', 'chatshop'));
                        ?>
                    </form>
                </div>

                <div class="chatshop-settings-sidebar">
                    <div class="postbox">
                        <h3 class="hndle"><?php esc_html_e('Quick Setup Guide', 'chatshop'); ?></h3>
                        <div class="inside">
                            <ol>
                                <li><?php esc_html_e('Create a Paystack account', 'chatshop'); ?></li>
                                <li><?php esc_html_e('Get your API keys from the Paystack dashboard', 'chatshop'); ?></li>
                                <li><?php esc_html_e('Enter your keys above and enable the gateway', 'chatshop'); ?></li>
                                <li><?php esc_html_e('Test the connection', 'chatshop'); ?></li>
                                <li><?php esc_html_e('Add the webhook URL to your Paystack account', 'chatshop'); ?></li>
                            </ol>
                        </div>
                    </div>

                    <div class="postbox">
                        <h3 class="hndle"><?php esc_html_e('Need Help?', 'chatshop'); ?></h3>
                        <div class="inside">
                            <p>
                                <a href="https://paystack.com/docs/" target="_blank">
                                    <?php esc_html_e('Paystack Documentation', 'chatshop'); ?>
                                </a>
                            </p>
                            <p>
                                <a href="https://support.paystack.com/" target="_blank">
                                    <?php esc_html_e('Paystack Support', 'chatshop'); ?>
                                </a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <style>
            .chatshop-settings-wrapper {
                display: flex;
                gap: 20px;
                margin-top: 20px;
            }

            .chatshop-settings-main {
                flex: 2;
            }

            .chatshop-settings-sidebar {
                flex: 1;
                max-width: 300px;
            }

            .postbox {
                margin-bottom: 20px;
            }

            .postbox h3 {
                padding: 8px 12px;
                margin: 0;
                line-height: 1.4;
            }

            .postbox .inside {
                padding: 0 12px 12px;
            }

            .notice.inline {
                display: inline-block;
                margin: 5px 0 15px;
                padding: 5px 10px;
            }
        </style>
<?php
    }
}
