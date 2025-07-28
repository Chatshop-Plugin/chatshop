<?php

/**
 * ChatShop Global Functions - IMPROVED VERSION
 *
 * File: includes/chatshop-global-functions.php
 * 
 * Global utility functions used throughout the ChatShop plugin.
 * These functions are loaded early and available everywhere.
 * IMPROVED with better class conflict prevention.
 *
 * @package ChatShop
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// ================================
// CLASS EXISTENCE CHECKS (Prevent redeclaration errors)
// ================================

/**
 * Check if ChatShop classes are already declared to prevent conflicts
 *
 * @since 1.0.0
 * @return bool True if main classes exist, false otherwise
 */
function chatshop_classes_exist()
{
    return class_exists('ChatShop\\ChatShop') &&
        class_exists('ChatShop\\ChatShop_Loader') &&
        class_exists('ChatShop\\ChatShop_Component_Loader');
}

/**
 * Prevent multiple plugin activations
 *
 * @since 1.0.0
 * @return bool True if safe to continue, false if conflicting
 */
function chatshop_prevent_conflicts()
{
    // Check for function conflicts
    $conflicting_functions = array(
        'chatshop',
        'chatshop_is_loaded',
        'chatshop_is_enabled',
        'chatshop_get_option'
    );

    foreach ($conflicting_functions as $function_name) {
        if (function_exists($function_name)) {
            // If this function file is being reloaded, it's okay
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
            $calling_file = isset($backtrace[0]['file']) ? basename($backtrace[0]['file']) : '';

            if ($calling_file !== 'chatshop-global-functions.php') {
                add_action('admin_notices', function () use ($function_name) {
                    echo '<div class="notice notice-error"><p>';
                    echo sprintf(__('ChatShop: Function conflict detected (%s). Another plugin may be using the same function names.', 'chatshop'), $function_name);
                    echo '</p></div>';
                });
                return false;
            }
        }
    }

    return true;
}

// Check for conflicts before defining functions
if (!chatshop_prevent_conflicts()) {
    return;
}

// ================================
// CORE HELPER FUNCTIONS (Used by main plugin file)
// ================================

if (!function_exists('chatshop')) {
    /**
     * Get ChatShop main instance
     *
     * @since 1.0.0
     * @return ChatShop|null Main plugin instance
     */
    function chatshop()
    {
        if (class_exists('ChatShop\\ChatShop')) {
            return ChatShop\ChatShop::instance();
        }
        return null;
    }
}

if (!function_exists('chatshop_is_loaded')) {
    /**
     * Check if ChatShop is properly loaded
     *
     * @since 1.0.0
     * @return bool Plugin loaded status
     */
    function chatshop_is_loaded()
    {
        return class_exists('ChatShop\\ChatShop') && function_exists('chatshop') && chatshop() !== null;
    }
}

if (!function_exists('chatshop_is_enabled')) {
    /**
     * Check if ChatShop is enabled/active
     *
     * @since 1.0.0
     * @return bool Plugin enabled status
     */
    function chatshop_is_enabled()
    {
        return chatshop_is_loaded() && chatshop_get_option('general', 'plugin_enabled', true);
    }
}

if (!function_exists('chatshop_is_premium')) {
    /**
     * Check if premium features are enabled
     *
     * @since 1.0.0
     * @return bool Premium status
     */
    function chatshop_is_premium()
    {
        // For development/testing, you can set this to true
        // In production, this should check for a valid license key
        $premium_enabled = chatshop_get_option('general', 'premium_enabled', false);

        // For development purposes, enable premium features by default
        // Remove this line in production and implement proper license checking
        $premium_enabled = true;

        return (bool) $premium_enabled;
    }
}

if (!function_exists('chatshop_get_component')) {
    /**
     * Get component instance
     *
     * @since 1.0.0
     * @param string $component_id Component identifier
     * @return object|null Component instance or null if not found
     */
    function chatshop_get_component($component_id)
    {
        if (!chatshop_is_loaded()) {
            return null;
        }

        $plugin = chatshop();
        if (!$plugin || !method_exists($plugin, 'get_component_loader')) {
            return null;
        }

        $component_loader = $plugin->get_component_loader();
        return $component_loader ? $component_loader->get_component_instance($component_id) : null;
    }
}

if (!function_exists('chatshop_is_analytics_enabled')) {
    /**
     * Check if analytics is enabled
     *
     * @since 1.0.0
     * @return bool Analytics enabled status
     */
    function chatshop_is_analytics_enabled()
    {
        return chatshop_is_premium() && chatshop_is_enabled();
    }
}

// ================================
// PLUGIN URL/PATH FUNCTIONS
// ================================

if (!function_exists('chatshop_get_plugin_url')) {
    /**
     * Get ChatShop plugin URL
     *
     * @since 1.0.0
     * @param string $path Optional path to append
     * @return string Plugin URL
     */
    function chatshop_get_plugin_url($path = '')
    {
        $url = defined('CHATSHOP_PLUGIN_URL') ? CHATSHOP_PLUGIN_URL : plugin_dir_url(__FILE__);
        return $path ? trailingslashit($url) . ltrim($path, '/') : $url;
    }
}

if (!function_exists('chatshop_get_plugin_path')) {
    /**
     * Get ChatShop plugin directory path
     *
     * @since 1.0.0
     * @param string $path Optional path to append
     * @return string Plugin path
     */
    function chatshop_get_plugin_path($path = '')
    {
        $plugin_path = defined('CHATSHOP_PLUGIN_DIR') ? CHATSHOP_PLUGIN_DIR : plugin_dir_path(__FILE__);
        return $path ? trailingslashit($plugin_path) . ltrim($path, '/') : $plugin_path;
    }
}

// ================================
// LOGGING FUNCTIONS
// ================================

if (!function_exists('chatshop_log')) {
    /**
     * Log message using ChatShop logger
     *
     * @since 1.0.0
     * @param string $message Log message
     * @param string $level Log level (debug, info, warning, error)
     * @param array $context Additional context
     */
    function chatshop_log($message, $level = 'info', $context = array())
    {
        if (!class_exists('ChatShop\ChatShop_Logger')) {
            error_log("ChatShop: {$message}");
            return;
        }

        ChatShop\ChatShop_Logger::log($level, $message, $context);
    }
}

// ================================
// OPTIONS MANAGEMENT FUNCTIONS
// ================================

if (!function_exists('chatshop_get_option')) {
    /**
     * Get ChatShop option with fallback
     *
     * @since 1.0.0
     * @param string $group Option group
     * @param string $key Option key (empty for all options in group)
     * @param mixed  $default Default value
     * @return mixed Option value
     */
    function chatshop_get_option($group, $key = '', $default = null)
    {
        $option_name = "chatshop_{$group}_options";
        $options = get_option($option_name, array());

        if (empty($key)) {
            return is_array($options) ? $options : array();
        }

        return isset($options[$key]) ? $options[$key] : $default;
    }
}

if (!function_exists('chatshop_update_option')) {
    /**
     * Update ChatShop option
     *
     * @since 1.0.0
     * @param string $group Option group
     * @param string $key Option key (empty to update entire group)
     * @param mixed  $value Option value
     * @return bool Update result
     */
    function chatshop_update_option($group, $key = '', $value = null)
    {
        $option_name = "chatshop_{$group}_options";

        if (empty($key)) {
            // Update entire group
            return update_option($option_name, $value);
        }

        // Update specific key
        $options = get_option($option_name, array());
        $options[$key] = $value;
        return update_option($option_name, $options);
    }
}

if (!function_exists('chatshop_delete_option')) {
    /**
     * Delete ChatShop option
     *
     * @since 1.0.0
     * @param string $group Option group
     * @param string $key Option key (empty to delete entire group)
     * @return bool Delete result
     */
    function chatshop_delete_option($group, $key = '')
    {
        $option_name = "chatshop_{$group}_options";

        if (empty($key)) {
            // Delete entire group
            return delete_option($option_name);
        }

        // Delete specific key
        $options = get_option($option_name, array());
        if (isset($options[$key])) {
            unset($options[$key]);
            return update_option($option_name, $options);
        }

        return false;
    }
}

// ================================
// PREMIUM FEATURE FUNCTIONS
// ================================

if (!function_exists('chatshop_is_premium_feature_available')) {
    /**
     * Check if premium feature is available
     *
     * @since 1.0.0
     * @param string $feature Feature name
     * @return bool Feature availability
     */
    function chatshop_is_premium_feature_available($feature)
    {
        if (!chatshop_is_premium()) {
            return false;
        }

        $premium_features = chatshop_get_option('premium', 'features', array());
        return isset($premium_features[$feature]) ? (bool) $premium_features[$feature] : false;
    }
}

if (!function_exists('chatshop_get_premium_features')) {
    /**
     * Get all premium features status
     *
     * @since 1.0.0
     * @return array Premium features status
     */
    function chatshop_get_premium_features()
    {
        $default_features = array(
            'unlimited_contacts' => false,
            'contact_import_export' => false,
            'bulk_messaging' => false,
            'advanced_analytics' => false,
            'analytics' => true, // Default enabled for development
            'whatsapp_business_api' => false,
            'campaign_automation' => false,
            'custom_reports' => false
        );

        if (!chatshop_is_premium()) {
            return $default_features;
        }

        $premium_features = chatshop_get_option('premium', 'features', array());
        return wp_parse_args($premium_features, $default_features);
    }
}

// ================================
// UTILITY FUNCTIONS
// ================================

if (!function_exists('chatshop_format_currency')) {
    /**
     * Format currency value
     *
     * @since 1.0.0
     * @param float $amount Amount to format
     * @param string $currency Currency code
     * @return string Formatted currency
     */
    function chatshop_format_currency($amount, $currency = 'NGN')
    {
        $symbols = array(
            'NGN' => '₦',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£'
        );

        $symbol = isset($symbols[$currency]) ? $symbols[$currency] : $currency;
        return $symbol . number_format($amount, 2);
    }
}

if (!function_exists('chatshop_sanitize_phone')) {
    /**
     * Sanitize phone number
     *
     * @since 1.0.0
     * @param string $phone Phone number
     * @return string Sanitized phone number
     */
    function chatshop_sanitize_phone($phone)
    {
        // Remove all non-numeric characters except +
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        // Ensure it starts with + for international format
        if (!empty($phone) && substr($phone, 0, 1) !== '+') {
            $phone = '+' . $phone;
        }

        return sanitize_text_field($phone);
    }
}

if (!function_exists('chatshop_validate_email')) {
    /**
     * Validate email address
     *
     * @since 1.0.0
     * @param string $email Email address
     * @return bool|string False if invalid, sanitized email if valid
     */
    function chatshop_validate_email($email)
    {
        $email = sanitize_email($email);
        return is_email($email) ? $email : false;
    }
}

if (!function_exists('chatshop_generate_reference')) {
    /**
     * Generate unique reference code
     *
     * @since 1.0.0
     * @param string $prefix Optional prefix
     * @return string Unique reference
     */
    function chatshop_generate_reference($prefix = 'CS')
    {
        return strtoupper($prefix . '_' . uniqid() . '_' . wp_rand(1000, 9999));
    }
}

// ================================
// DEBUG AND DEVELOPMENT FUNCTIONS
// ================================

if (!function_exists('chatshop_debug')) {
    /**
     * Debug function (only works when WP_DEBUG is true)
     *
     * @since 1.0.0
     * @param mixed $data Data to debug
     * @param string $label Optional label
     */
    function chatshop_debug($data, $label = '')
    {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        $output = '';
        if (!empty($label)) {
            $output .= "ChatShop Debug - {$label}: ";
        }

        $output .= print_r($data, true);
        error_log($output);
    }
}

if (!function_exists('chatshop_get_debug_info')) {
    /**
     * Get plugin debug information
     *
     * @since 1.0.0
     * @return array Debug information
     */
    function chatshop_get_debug_info()
    {
        global $wp_version;

        return array(
            'plugin_version' => defined('CHATSHOP_VERSION') ? CHATSHOP_VERSION : 'Unknown',
            'wp_version' => $wp_version,
            'php_version' => PHP_VERSION,
            'plugin_loaded' => chatshop_is_loaded(),
            'plugin_enabled' => chatshop_is_enabled(),
            'premium_enabled' => chatshop_is_premium(),
            'components_loaded' => chatshop_is_loaded() ? count(chatshop()->get_component_loader()->get_all_instances()) : 0,
            'memory_usage' => size_format(memory_get_usage(true)),
            'peak_memory' => size_format(memory_get_peak_usage(true))
        );
    }
}

// ================================
// HOOKS AND COMPATIBILITY
// ================================

/**
 * Hook to run after all functions are loaded
 *
 * @since 1.0.0
 */
do_action('chatshop_global_functions_loaded');

/**
 * Compatibility check for older PHP versions
 *
 * @since 1.0.0
 */
if (version_compare(PHP_VERSION, '7.4', '<')) {
    add_action('admin_notices', function () {
        echo '<div class="notice notice-error"><p>';
        echo __('ChatShop requires PHP 7.4 or higher. Please update your PHP version.', 'chatshop');
        echo '</p></div>';
    });
}
