<?php

/**
 * ChatShop Global Functions
 *
 * File: includes/chatshop-global-functions.php
 * 
 * Global utility functions used throughout the ChatShop plugin.
 * These functions are loaded early and available everywhere.
 *
 * @package ChatShop
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// ================================
// CORE HELPER FUNCTIONS (Used by main plugin file)
// ================================

/**
 * Get ChatShop main instance
 *
 * @since 1.0.0
 * @return ChatShop|null Main plugin instance
 */
function chatshop()
{
    return ChatShop\ChatShop::instance();
}

/**
 * Check if ChatShop is properly loaded
 *
 * @since 1.0.0
 * @return bool Plugin loaded status
 */
function chatshop_is_loaded()
{
    return class_exists('ChatShop\ChatShop') && function_exists('chatshop');
}

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
    $component_loader = $plugin->get_component_loader();

    return $component_loader ? $component_loader->get_component_instance($component_id) : null;
}

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

// ================================
// PLUGIN URL/PATH FUNCTIONS
// ================================

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

// ================================
// LOGGING FUNCTIONS
// ================================

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

// ================================
// OPTIONS MANAGEMENT FUNCTIONS
// ================================

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

// ================================
// VALIDATION FUNCTIONS
// ================================

/**
 * Validate email address
 *
 * @since 1.0.0
 * @param string $email Email to validate
 * @return bool|string Sanitized email or false
 */
function chatshop_validate_email($email)
{
    $sanitized = sanitize_email($email);
    return is_email($sanitized) ? $sanitized : false;
}

/**
 * Validate phone number
 *
 * @since 1.0.0
 * @param string $phone Phone number to validate
 * @return bool|string Sanitized phone or false
 */
function chatshop_validate_phone($phone)
{
    $cleaned = preg_replace('/[^\d+]/', '', $phone);

    // Basic validation - must start with + and have at least 7 digits
    if (preg_match('/^\+\d{7,15}$/', $cleaned)) {
        return $cleaned;
    }

    return false;
}

/**
 * Validate URL
 *
 * @since 1.0.0
 * @param string $url URL to validate
 * @return bool|string Sanitized URL or false
 */
function chatshop_validate_url($url)
{
    $sanitized = esc_url_raw($url);
    return filter_var($sanitized, FILTER_VALIDATE_URL) ? $sanitized : false;
}

/**
 * Validate amount/currency value
 *
 * @since 1.0.0
 * @param mixed $amount Amount to validate
 * @return bool|float Validated amount or false
 */
function chatshop_validate_amount($amount)
{
    $cleaned = (float) $amount;
    return ($cleaned >= 0) ? $cleaned : false;
}

// ================================
// FORMATTING FUNCTIONS
// ================================

/**
 * Format currency amount
 *
 * @since 1.0.0
 * @param float $amount Amount to format
 * @param string $currency Currency code
 * @param int $decimals Number of decimal places
 * @return string Formatted amount
 */
function chatshop_format_currency($amount, $currency = 'NGN', $decimals = 2)
{
    $formatted = number_format((float) $amount, $decimals);

    $symbols = array(
        'NGN' => '₦',
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'GHS' => 'GH₵',
        'KES' => 'KSh',
        'ZAR' => 'R'
    );

    $symbol = isset($symbols[$currency]) ? $symbols[$currency] : $currency;

    return $symbol . $formatted;
}

/**
 * Format phone number for display
 *
 * @since 1.0.0
 * @param string $phone Phone number to format
 * @return string Formatted phone number
 */
function chatshop_format_phone($phone)
{
    $cleaned = preg_replace('/[^\d+]/', '', $phone);

    // Nigerian format: +234 xxx xxx xxxx
    if (strpos($cleaned, '+234') === 0) {
        $number = substr($cleaned, 4);
        if (strlen($number) === 10) {
            return '+234 ' . substr($number, 0, 3) . ' ' . substr($number, 3, 3) . ' ' . substr($number, 6);
        }
    }

    // US format: +1 xxx xxx xxxx
    if (strpos($cleaned, '+1') === 0) {
        $number = substr($cleaned, 2);
        if (strlen($number) === 10) {
            return '+1 ' . substr($number, 0, 3) . ' ' . substr($number, 3, 3) . ' ' . substr($number, 6);
        }
    }

    return $cleaned;
}

/**
 * Format number for display
 *
 * @since 1.0.0
 * @param mixed $number Number to format
 * @param int   $decimals Number of decimal places
 * @return string Formatted number
 */
function chatshop_format_number($number, $decimals = 0)
{
    return number_format((float) $number, $decimals);
}

// ================================
// INTERNAL FORMATTING FUNCTIONS (Used by main plugin file)
// ================================

/**
 * Internal number formatting function
 *
 * @since 1.0.0
 * @param mixed $number Number to format
 * @param int   $decimals Number of decimal places
 * @return string Formatted number
 */
function chatshop_internal_format_number($number, $decimals = 0)
{
    return number_format((float) $number, $decimals);
}

/**
 * Internal phone formatting function
 *
 * @since 1.0.0
 * @param string $phone Phone number to format
 * @return string Formatted phone number
 */
function chatshop_internal_format_phone($phone)
{
    $cleaned = preg_replace('/[^\d+]/', '', $phone);

    // Nigerian format: +234 xxx xxx xxxx
    if (strpos($cleaned, '+234') === 0) {
        $number = substr($cleaned, 4);
        if (strlen($number) === 10) {
            return '+234 ' . substr($number, 0, 3) . ' ' . substr($number, 3, 3) . ' ' . substr($number, 6);
        }
    }

    return $cleaned;
}

// ================================
// DATE/TIME FUNCTIONS
// ================================

/**
 * Get formatted date for display
 *
 * @since 1.0.0
 * @param string $date Date string
 * @param string $format Date format
 * @return string Formatted date
 */
function chatshop_format_date($date, $format = 'Y-m-d H:i:s')
{
    if (empty($date)) {
        return '';
    }

    $timestamp = is_numeric($date) ? $date : strtotime($date);
    return $timestamp ? date($format, $timestamp) : $date;
}

/**
 * Get time ago string
 *
 * @since 1.0.0
 * @param string $date Date string
 * @return string Time ago string
 */
function chatshop_time_ago($date)
{
    $timestamp = is_numeric($date) ? $date : strtotime($date);

    if (!$timestamp) {
        return __('Unknown', 'chatshop');
    }

    $time_diff = time() - $timestamp;

    if ($time_diff < 60) {
        return __('Just now', 'chatshop');
    } elseif ($time_diff < 3600) {
        $minutes = floor($time_diff / 60);
        return sprintf(_n('%d minute ago', '%d minutes ago', $minutes, 'chatshop'), $minutes);
    } elseif ($time_diff < 86400) {
        $hours = floor($time_diff / 3600);
        return sprintf(_n('%d hour ago', '%d hours ago', $hours, 'chatshop'), $hours);
    } elseif ($time_diff < 2592000) {
        $days = floor($time_diff / 86400);
        return sprintf(_n('%d day ago', '%d days ago', $days, 'chatshop'), $days);
    } else {
        return date(get_option('date_format'), $timestamp);
    }
}

// ================================
// SECURITY FUNCTIONS
// ================================

/**
 * Generate secure random string
 *
 * @since 1.0.0
 * @param int $length String length
 * @return string Random string
 */
function chatshop_generate_random_string($length = 32)
{
    if (function_exists('wp_generate_password')) {
        return wp_generate_password($length, false, false);
    }

    return substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, $length);
}

/**
 * Create nonce for ChatShop actions
 *
 * @since 1.0.0
 * @param string $action Action name
 * @return string Nonce value
 */
function chatshop_create_nonce($action)
{
    return wp_create_nonce("chatshop_{$action}");
}

/**
 * Verify nonce for ChatShop actions
 *
 * @since 1.0.0
 * @param string $nonce Nonce to verify
 * @param string $action Action name
 * @return bool Verification result
 */
function chatshop_verify_nonce($nonce, $action)
{
    return wp_verify_nonce($nonce, "chatshop_{$action}");
}

// ================================
// USER CAPABILITY FUNCTIONS
// ================================

/**
 * Check if current user can manage ChatShop
 *
 * @since 1.0.0
 * @return bool User capability status
 */
function chatshop_current_user_can_manage()
{
    return current_user_can('manage_options');
}

/**
 * Check if current user can view analytics
 *
 * @since 1.0.0
 * @return bool User capability status
 */
function chatshop_current_user_can_view_analytics()
{
    return chatshop_current_user_can_manage() && chatshop_is_premium();
}

// ================================
// PLUGIN STATUS FUNCTIONS
// ================================

/**
 * Check if WooCommerce is active
 *
 * @since 1.0.0
 * @return bool WooCommerce status
 */
function chatshop_is_woocommerce_active()
{
    return class_exists('WooCommerce');
}

/**
 * Get plugin status information
 *
 * @since 1.0.0
 * @return array Plugin status array
 */
function chatshop_get_plugin_status()
{
    return array(
        'plugin_loaded' => chatshop_is_loaded(),
        'plugin_enabled' => chatshop_is_enabled(),
        'premium_enabled' => chatshop_is_premium(),
        'woocommerce_active' => chatshop_is_woocommerce_active(),
        'analytics_enabled' => chatshop_is_analytics_enabled(),
        'version' => defined('CHATSHOP_VERSION') ? CHATSHOP_VERSION : '1.0.0'
    );
}

// ================================
// COMPONENT HELPER FUNCTIONS
// ================================

/**
 * Enable a component
 *
 * @since 1.0.0
 * @param string $component_id Component identifier
 * @return bool Success status
 */
function chatshop_enable_component($component_id)
{
    if (!chatshop_is_loaded()) {
        return false;
    }

    $plugin = chatshop();
    $component_loader = $plugin->get_component_loader();

    return $component_loader ? $component_loader->enable_component($component_id) : false;
}

/**
 * Disable a component
 *
 * @since 1.0.0
 * @param string $component_id Component identifier
 * @return bool Success status
 */
function chatshop_disable_component($component_id)
{
    if (!chatshop_is_loaded()) {
        return false;
    }

    $plugin = chatshop();
    $component_loader = $plugin->get_component_loader();

    return $component_loader ? $component_loader->disable_component($component_id) : false;
}

/**
 * Check if a component is enabled
 *
 * @since 1.0.0
 * @param string $component_id Component identifier
 * @return bool Component status
 */
function chatshop_is_component_enabled($component_id)
{
    $component = chatshop_get_component($component_id);
    return $component && method_exists($component, 'is_enabled') ? $component->is_enabled() : false;
}

// ================================
// DEBUGGING FUNCTIONS
// ================================

/**
 * Debug print for development
 *
 * @since 1.0.0
 * @param mixed $data Data to debug
 * @param bool $die Whether to die after output
 */
function chatshop_debug($data, $die = false)
{
    if (!defined('WP_DEBUG') || !WP_DEBUG) {
        return;
    }

    echo '<pre>';
    print_r($data);
    echo '</pre>';

    if ($die) {
        die();
    }
}

/**
 * Get system information for debugging
 *
 * @since 1.0.0
 * @return array System information
 */
function chatshop_get_system_info()
{
    global $wpdb;

    return array(
        'wordpress_version' => get_bloginfo('version'),
        'php_version' => PHP_VERSION,
        'mysql_version' => $wpdb->db_version(),
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        'memory_limit' => ini_get('memory_limit'),
        'max_execution_time' => ini_get('max_execution_time'),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'chatshop_version' => defined('CHATSHOP_VERSION') ? CHATSHOP_VERSION : '1.0.0',
        'plugin_status' => chatshop_get_plugin_status()
    );
}
