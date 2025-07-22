<?php

/**
 * ChatShop Settings Handler
 *
 * @package ChatShop
 * @since   1.0.0
 */

namespace ChatShop;

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Settings management class
 */
class ChatShop_Settings
{
    /**
     * Settings groups
     *
     * @var array
     */
    private $settings_groups = array();

    /**
     * Initialize settings
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_init', array($this, 'register_settings_fields'));
    }

    /**
     * Register plugin settings
     *
     * @since 1.0.0
     */
    public function register_settings()
    {
        // General settings
        register_setting(
            'chatshop_general_settings',
            'chatshop_general_options',
            array(
                'type' => 'array',
                'sanitize_callback' => array($this, 'sanitize_general_settings'),
                'default' => array()
            )
        );

        // Payment settings
        register_setting(
            'chatshop_payment_settings',
            'chatshop_paystack_options',
            array(
                'type' => 'array',
                'sanitize_callback' => array($this, 'sanitize_paystack_settings'),
                'default' => array()
            )
        );

        // WhatsApp settings
        register_setting(
            'chatshop_whatsapp_settings',
            'chatshop_whatsapp_options',
            array(
                'type' => 'array',
                'sanitize_callback' => array($this, 'sanitize_whatsapp_settings'),
                'default' => array()
            )
        );

        // Analytics settings
        register_setting(
            'chatshop_analytics_settings',
            'chatshop_analytics_options',
            array(
                'type' => 'array',
                'sanitize_callback' => array($this, 'sanitize_analytics_settings'),
                'default' => array()
            )
        );
    }

    /**
     * Register settings fields and sections
     *
     * @since 1.0.0
     */
    public function register_settings_fields()
    {
        // This method can be extended to add settings sections and fields
        // For now, we handle settings directly in the template files
    }

    /**
     * Sanitize general settings
     *
     * @param array $input Raw input data
     * @return array Sanitized data
     * @since 1.0.0
     */
    public function sanitize_general_settings($input)
    {
        $sanitized = array();

        if (!is_array($input)) {
            return $sanitized;
        }

        // Plugin enabled
        $sanitized['plugin_enabled'] = isset($input['plugin_enabled']) ? (bool) $input['plugin_enabled'] : false;

        // Default currency
        if (isset($input['default_currency'])) {
            $allowed_currencies = array('NGN', 'USD', 'GHS', 'ZAR', 'KES', 'XOF');
            $sanitized['default_currency'] = in_array($input['default_currency'], $allowed_currencies, true)
                ? $input['default_currency']
                : 'NGN';
        }

        // Debug mode
        $sanitized['debug_mode'] = isset($input['debug_mode']) ? (bool) $input['debug_mode'] : false;

        return $sanitized;
    }

    /**
     * Sanitize Paystack settings
     *
     * @param array $input Raw input data
     * @return array Sanitized data
     * @since 1.0.0
     */
    public function sanitize_paystack_settings($input)
    {
        $sanitized = array();

        if (!is_array($input)) {
            return $sanitized;
        }

        // Enabled status
        $sanitized['enabled'] = isset($input['enabled']) ? (bool) $input['enabled'] : false;

        // Test mode
        $sanitized['test_mode'] = isset($input['test_mode']) ? (bool) $input['test_mode'] : true;

        // API Keys - sanitize and validate format
        $sanitized['test_public_key'] = $this->sanitize_api_key($input['test_public_key'] ?? '', 'pk_test_');
        $sanitized['test_secret_key'] = $this->sanitize_and_encrypt_secret_key($input['test_secret_key'] ?? '', 'sk_test_');
        $sanitized['live_public_key'] = $this->sanitize_api_key($input['live_public_key'] ?? '', 'pk_live_');
        $sanitized['live_secret_key'] = $this->sanitize_and_encrypt_secret_key($input['live_secret_key'] ?? '', 'sk_live_');

        // Webhook events
        if (isset($input['webhook_events']) && is_array($input['webhook_events'])) {
            $allowed_events = array(
                'charge.success',
                'charge.dispute.create',
                'charge.dispute.remind',
                'charge.dispute.resolve',
                'transfer.success',
                'transfer.failed',
                'transfer.reversed'
            );
            $sanitized['webhook_events'] = array_intersect($input['webhook_events'], $allowed_events);
        } else {
            $sanitized['webhook_events'] = array('charge.success');
        }

        return $sanitized;
    }

    /**
     * Sanitize WhatsApp settings
     *
     * @param array $input Raw input data
     * @return array Sanitized data
     * @since 1.0.0
     */
    public function sanitize_whatsapp_settings($input)
    {
        $sanitized = array();

        if (!is_array($input)) {
            return $sanitized;
        }

        // Enabled status
        $sanitized['enabled'] = isset($input['enabled']) ? (bool) $input['enabled'] : false;

        // API credentials
        $sanitized['business_account_id'] = sanitize_text_field($input['business_account_id'] ?? '');
        $sanitized['access_token'] = $this->encrypt_sensitive_data($input['access_token'] ?? '');
        $sanitized['webhook_verify_token'] = sanitize_text_field($input['webhook_verify_token'] ?? '');

        // Phone number
        $sanitized['phone_number'] = sanitize_text_field($input['phone_number'] ?? '');

        return $sanitized;
    }

    /**
     * Sanitize analytics settings
     *
     * @param array $input Raw input data
     * @return array Sanitized data
     * @since 1.0.0
     */
    public function sanitize_analytics_settings($input)
    {
        $sanitized = array();

        if (!is_array($input)) {
            return $sanitized;
        }

        // Enabled status
        $sanitized['enabled'] = isset($input['enabled']) ? (bool) $input['enabled'] : false;

        // Data retention period (in days)
        $retention_period = intval($input['retention_period'] ?? 365);
        $sanitized['retention_period'] = max(30, min(1095, $retention_period)); // Between 30 days and 3 years

        // Track conversion attribution
        $sanitized['track_attribution'] = isset($input['track_attribution']) ? (bool) $input['track_attribution'] : true;

        return $sanitized;
    }

    /**
     * Sanitize API key
     *
     * @param string $key    API key
     * @param string $prefix Expected prefix
     * @return string Sanitized key
     * @since 1.0.0
     */
    private function sanitize_api_key($key, $prefix = '')
    {
        $key = sanitize_text_field($key);

        // Validate prefix if provided
        if (!empty($prefix) && !empty($key) && strpos($key, $prefix) !== 0) {
            chatshop_log("Invalid API key format. Expected prefix: {$prefix}", 'warning');
            return '';
        }

        return $key;
    }

    /**
     * Sanitize and encrypt secret key
     *
     * @param string $key    Secret key
     * @param string $prefix Expected prefix
     * @return string Encrypted key
     * @since 1.0.0
     */
    private function sanitize_and_encrypt_secret_key($key, $prefix = '')
    {
        $key = $this->sanitize_api_key($key, $prefix);

        if (empty($key)) {
            return '';
        }

        // Don't re-encrypt if already encrypted (checking if it doesn't start with expected prefix)
        if (!empty($prefix) && strpos($key, $prefix) !== 0) {
            return $key; // Already encrypted
        }

        return $this->encrypt_sensitive_data($key);
    }

    /**
     * Encrypt sensitive data
     *
     * @param string $data Data to encrypt
     * @return string Encrypted data
     * @since 1.0.0
     */
    private function encrypt_sensitive_data($data)
    {
        if (empty($data)) {
            return '';
        }

        $encryption_key = wp_salt('auth');
        $iv = substr($encryption_key, 0, 16);

        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $encryption_key, 0, $iv);

        if ($encrypted === false) {
            chatshop_log('Failed to encrypt sensitive data', 'error');
            return '';
        }

        return $encrypted;
    }

    /**
     * Decrypt sensitive data
     *
     * @param string $encrypted_data Encrypted data
     * @return string Decrypted data
     * @since 1.0.0
     */
    public function decrypt_sensitive_data($encrypted_data)
    {
        if (empty($encrypted_data)) {
            return '';
        }

        $encryption_key = wp_salt('auth');
        $iv = substr($encryption_key, 0, 16);

        $decrypted = openssl_decrypt($encrypted_data, 'AES-256-CBC', $encryption_key, 0, $iv);

        if ($decrypted === false) {
            chatshop_log('Failed to decrypt sensitive data', 'error');
            return '';
        }

        return $decrypted;
    }

    /**
     * Get decrypted Paystack keys
     *
     * @param bool $test_mode Whether to get test or live keys
     * @return array Decrypted keys
     * @since 1.0.0
     */
    public function get_paystack_keys($test_mode = true)
    {
        $options = chatshop_get_option('paystack', '', array());

        if ($test_mode) {
            return array(
                'public_key' => $options['test_public_key'] ?? '',
                'secret_key' => $this->decrypt_sensitive_data($options['test_secret_key'] ?? '')
            );
        }

        return array(
            'public_key' => $options['live_public_key'] ?? '',
            'secret_key' => $this->decrypt_sensitive_data($options['live_secret_key'] ?? '')
        );
    }

    /**
     * Validate Paystack configuration
     *
     * @param bool $test_mode Whether to validate test or live mode
     * @return array Validation result
     * @since 1.0.0
     */
    public function validate_paystack_config($test_mode = true)
    {
        $keys = $this->get_paystack_keys($test_mode);
        $mode = $test_mode ? 'test' : 'live';

        // Check if keys are present
        if (empty($keys['public_key']) || empty($keys['secret_key'])) {
            return array(
                'valid' => false,
                'message' => sprintf(__('Missing %s API keys for Paystack', 'chatshop'), $mode)
            );
        }

        // Validate key formats
        $public_prefix = $test_mode ? 'pk_test_' : 'pk_live_';
        $secret_prefix = $test_mode ? 'sk_test_' : 'sk_live_';

        if (strpos($keys['public_key'], $public_prefix) !== 0) {
            return array(
                'valid' => false,
                'message' => sprintf(__('Invalid %s public key format', 'chatshop'), $mode)
            );
        }

        if (strpos($keys['secret_key'], $secret_prefix) !== 0) {
            return array(
                'valid' => false,
                'message' => sprintf(__('Invalid %s secret key format', 'chatshop'), $mode)
            );
        }

        return array(
            'valid' => true,
            'message' => sprintf(__('%s API keys are valid', 'chatshop'), ucfirst($mode))
        );
    }

    /**
     * Get current settings for a specific group
     *
     * @param string $group Settings group
     * @return array Settings
     * @since 1.0.0
     */
    public function get_settings($group)
    {
        switch ($group) {
            case 'general':
                return chatshop_get_option('general', '', array());

            case 'paystack':
                return chatshop_get_option('paystack', '', array());

            case 'whatsapp':
                return chatshop_get_option('whatsapp', '', array());

            case 'analytics':
                return chatshop_get_option('analytics', '', array());

            default:
                return array();
        }
    }

    /**
     * Reset settings for a specific group
     *
     * @param string $group Settings group
     * @return bool Success status
     * @since 1.0.0
     */
    public function reset_settings($group)
    {
        $option_name = "chatshop_{$group}_options";

        if (delete_option($option_name)) {
            chatshop_log("Settings reset for group: {$group}", 'info');
            return true;
        }

        return false;
    }

    /**
     * Export settings
     *
     * @return array All plugin settings
     * @since 1.0.0
     */
    public function export_settings()
    {
        return array(
            'general' => $this->get_settings('general'),
            'paystack' => $this->get_settings('paystack'),
            'whatsapp' => $this->get_settings('whatsapp'),
            'analytics' => $this->get_settings('analytics'),
            'export_date' => current_time('mysql'),
            'plugin_version' => CHATSHOP_VERSION
        );
    }

    /**
     * Import settings
     *
     * @param array $settings Settings to import
     * @return bool Success status
     * @since 1.0.0
     */
    public function import_settings($settings)
    {
        if (!is_array($settings)) {
            return false;
        }

        $success = true;

        foreach ($settings as $group => $values) {
            if (in_array($group, array('general', 'paystack', 'whatsapp', 'analytics'), true)) {
                $option_name = "chatshop_{$group}_options";

                // Sanitize before importing
                switch ($group) {
                    case 'general':
                        $values = $this->sanitize_general_settings($values);
                        break;
                    case 'paystack':
                        $values = $this->sanitize_paystack_settings($values);
                        break;
                    case 'whatsapp':
                        $values = $this->sanitize_whatsapp_settings($values);
                        break;
                    case 'analytics':
                        $values = $this->sanitize_analytics_settings($values);
                        break;
                }

                if (!update_option($option_name, $values)) {
                    $success = false;
                }
            }
        }

        if ($success) {
            chatshop_log('Settings imported successfully', 'info');
        } else {
            chatshop_log('Failed to import some settings', 'error');
        }

        return $success;
    }
}
