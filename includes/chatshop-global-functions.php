<?php

/**
 * ChatShop Global Helper Functions
 *
 * File: includes/chatshop-global-functions.php
 * 
 * Contains essential helper functions that are used throughout the plugin.
 * These functions need to be available before classes are loaded.
 * 
 * IMPORTANT: Only contains functions NOT declared elsewhere to prevent duplicates.
 *
 * @package ChatShop
 * @subpackage Includes
 * @since 1.0.0
 */

namespace ChatShop;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
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
    // Remove all non-numeric characters except +
    $cleaned = preg_replace('/[^\d+]/', '', $phone);

    // Basic validation - at least 10 digits
    if (strlen(str_replace('+', '', $cleaned)) >= 10) {
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

// ================================
// SECURITY FUNCTIONS
// ================================

/**
 * Generate secure nonce for ChatShop actions
 *
 * @since 1.0.0
 * @param string $action Action name
 * @return string Generated nonce
 */
function chatshop_create_nonce($action)
{
    return wp_create_nonce("chatshop_{$action}");
}

/**
 * Verify ChatShop nonce
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

/**
 * Check if current user can manage ChatShop
 *
 * @since 1.0.0
 * @return bool Whether user can manage
 */
function chatshop_current_user_can_manage()
{
    return current_user_can('manage_options');
}

// ================================
// UTILITY FUNCTIONS
// ================================

/**
 * Check if ChatShop is in debug mode
 *
 * @since 1.0.0
 * @return bool Debug mode status
 */
function chatshop_is_debug_mode()
{
    $options = chatshop_get_option('general', '', array());
    return isset($options['debug_mode']) && $options['debug_mode'];
}

/**
 * Get ChatShop version
 *
 * @since 1.0.0
 * @return string Plugin version
 */
function chatshop_get_version()
{
    return defined('CHATSHOP_VERSION') ? CHATSHOP_VERSION : '1.0.0';
}

/**
 * Get ChatShop plugin directory URL
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

    $diff = time() - $timestamp;

    if ($diff < 60) {
        return __('Just now', 'chatshop');
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return sprintf(_n('%d minute ago', '%d minutes ago', $minutes, 'chatshop'), $minutes);
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return sprintf(_n('%d hour ago', '%d hours ago', $hours, 'chatshop'), $hours);
    } elseif ($diff < 2592000) {
        $days = floor($diff / 86400);
        return sprintf(_n('%d day ago', '%d days ago', $days, 'chatshop'), $days);
    } else {
        return date('M j, Y', $timestamp);
    }
}

// ================================
// ARRAY/DATA FUNCTIONS
// ================================

/**
 * Sanitize array recursively
 *
 * @since 1.0.0
 * @param array $array Array to sanitize
 * @return array Sanitized array
 */
function chatshop_sanitize_array($array)
{
    if (!is_array($array)) {
        return array();
    }

    $sanitized = array();

    foreach ($array as $key => $value) {
        $clean_key = sanitize_key($key);

        if (is_array($value)) {
            $sanitized[$clean_key] = chatshop_sanitize_array($value);
        } else {
            $sanitized[$clean_key] = sanitize_text_field($value);
        }
    }

    return $sanitized;
}

/**
 * Check if array has required keys
 *
 * @since 1.0.0
 * @param array $array Array to check
 * @param array $required_keys Required keys
 * @return bool Whether all keys exist
 */
function chatshop_array_has_keys($array, $required_keys)
{
    if (!is_array($array) || !is_array($required_keys)) {
        return false;
    }

    foreach ($required_keys as $key) {
        if (!array_key_exists($key, $array)) {
            return false;
        }
    }

    return true;
}

// ================================
// STATUS/STATE FUNCTIONS
// ================================

/**
 * Check if plugin is enabled
 *
 * @since 1.0.0
 * @return bool Plugin enabled status
 */
function chatshop_is_enabled()
{
    $options = chatshop_get_option('general', '', array());
    return !isset($options['plugin_enabled']) || $options['plugin_enabled'];
}

/**
 * Check if WooCommerce is active
 *
 * @since 1.0.0
 * @return bool WooCommerce status
 */
function chatshop_is_woocommerce_active()
{
    return class_exists('WooCommerce') && function_exists('WC');
}

/**
 * Get supported currencies
 *
 * @since 1.0.0
 * @return array Supported currencies
 */
function chatshop_get_supported_currencies()
{
    return array(
        'NGN' => __('Nigerian Naira (₦)', 'chatshop'),
        'USD' => __('US Dollar ($)', 'chatshop'),
        'EUR' => __('Euro (€)', 'chatshop'),
        'GBP' => __('British Pound (£)', 'chatshop'),
        'ZAR' => __('South African Rand (R)', 'chatshop'),
        'GHS' => __('Ghanaian Cedi (₵)', 'chatshop'),
        'KES' => __('Kenyan Shilling (KSh)', 'chatshop')
    );
}

/**
 * Get default currency
 *
 * @since 1.0.0
 * @return string Default currency code
 */
function chatshop_get_default_currency()
{
    $options = chatshop_get_option('general', '', array());
    $currency = isset($options['currency']) ? $options['currency'] : 'NGN';

    // Fallback to WooCommerce currency if available
    if (chatshop_is_woocommerce_active() && function_exists('get_woocommerce_currency')) {
        $wc_currency = get_woocommerce_currency();
        $supported = array_keys(chatshop_get_supported_currencies());

        if (in_array($wc_currency, $supported, true)) {
            return $wc_currency;
        }
    }

    return $currency;
}

// ================================
// ERROR HANDLING FUNCTIONS
// ================================

/**
 * Get error message by code
 *
 * @since 1.0.0
 * @param string $error_code Error code
 * @return string Error message
 */
function chatshop_get_error_message($error_code)
{
    $messages = array(
        'invalid_nonce'       => __('Security check failed. Please try again.', 'chatshop'),
        'insufficient_permissions' => __('You do not have permission to perform this action.', 'chatshop'),
        'invalid_data'        => __('Invalid data provided. Please check your input.', 'chatshop'),
        'payment_failed'      => __('Payment processing failed. Please try again.', 'chatshop'),
        'gateway_error'       => __('Payment gateway error. Please try again later.', 'chatshop'),
        'network_error'       => __('Network error. Please check your connection.', 'chatshop'),
        'api_error'          => __('API communication error. Please try again.', 'chatshop'),
        'file_upload_error'   => __('File upload failed. Please try again.', 'chatshop'),
        'database_error'      => __('Database error occurred. Please contact support.', 'chatshop'),
        'feature_not_available' => __('This feature is not available in your current plan.', 'chatshop')
    );

    return isset($messages[$error_code]) ? $messages[$error_code] : __('An unknown error occurred.', 'chatshop');
}

/**
 * Log and display admin notice
 *
 * @since 1.0.0
 * @param string $message Notice message
 * @param string $type Notice type (success, error, warning, info)
 * @param bool   $dismissible Whether notice is dismissible
 */
function chatshop_admin_notice($message, $type = 'info', $dismissible = true)
{
    $class = "notice notice-{$type}";

    if ($dismissible) {
        $class .= ' is-dismissible';
    }

    add_action('admin_notices', function () use ($message, $class) {
        echo '<div class="' . esc_attr($class) . '"><p>' . esc_html($message) . '</p></div>';
    });

    // Also log the message
    if (function_exists('chatshop_log')) {
        chatshop_log("Admin notice ({$type}): {$message}", $type === 'error' ? 'error' : 'info');
    }
}

// ================================
// COMPATIBILITY FUNCTIONS
// ================================

/**
 * Get WordPress timezone
 *
 * @since 1.0.0
 * @return string Timezone string
 */
function chatshop_get_timezone()
{
    $timezone = get_option('timezone_string');

    if (empty($timezone)) {
        $offset = get_option('gmt_offset', 0);
        $timezone = timezone_name_from_abbr('', $offset * HOUR_IN_SECONDS, 0);
    }

    return $timezone ?: 'UTC';
}

/**
 * Get current time in plugin timezone
 *
 * @since 1.0.0
 * @param string $format Date format
 * @return string Formatted date
 */
function chatshop_current_time($format = 'Y-m-d H:i:s')
{
    return current_time($format);
}
