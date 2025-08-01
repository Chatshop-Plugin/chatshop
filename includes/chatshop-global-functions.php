<?php

/**
 * ChatShop Global Functions - ENHANCED VERSION
 *
 * File: includes/chatshop-global-functions.php
 * 
 * Global utility functions used throughout the ChatShop plugin with enhanced
 * error handling, component access, and debugging capabilities.
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
 * Prevent multiple plugin activations and function conflicts
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
        'chatshop_get_option',
        'chatshop_get_component'
    );

    foreach ($conflicting_functions as $function_name) {
        if (function_exists($function_name)) {
            // If this function file is being reloaded, it's okay
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
            $calling_file = isset($backtrace[0]['file']) ?
                basename($backtrace[0]['file']) : '';

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
// CORE HELPER FUNCTIONS
// ================================

if (!function_exists('chatshop')) {
    /**
     * Get ChatShop main instance with error handling
     *
     * @since 1.0.0
     * @return ChatShop|null Main plugin instance
     */
    function chatshop()
    {
        if (class_exists('ChatShop\\ChatShop')) {
            return ChatShop\ChatShop::instance();
        }

        // Log error if plugin class doesn't exist
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('ChatShop: Main plugin class not found');
        }

        return null;
    }
}

if (!function_exists('chatshop_is_loaded')) {
    /**
     * Check if ChatShop is properly loaded with validation
     *
     * @since 1.0.0
     * @return bool Plugin loaded status
     */
    function chatshop_is_loaded()
    {
        return class_exists('ChatShop\\ChatShop') &&
            function_exists('chatshop') &&
            chatshop() !== null;
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
        return chatshop_is_loaded() &&
            chatshop_get_option('general', 'plugin_enabled', true);
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
        // For development/testing, enable premium features by default
        $premium_enabled = chatshop_get_option('general', 'premium_enabled', true);

        // In production, this should check for a valid license key
        // $premium_enabled = chatshop_validate_license_key();

        return (bool) $premium_enabled;
    }
}

if (!function_exists('chatshop_get_component')) {
    /**
     * Get component instance with enhanced error handling
     *
     * @since 1.0.0
     * @param string $component_id Component identifier
     * @return object|null Component instance or null if not found
     */
    function chatshop_get_component($component_id)
    {
        if (!chatshop_is_loaded()) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("ChatShop: Cannot get component '{$component_id}' - plugin not loaded");
            }
            return null;
        }

        $plugin = chatshop();
        if (!$plugin || !method_exists($plugin, 'get_component_loader')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("ChatShop: Cannot get component '{$component_id}' - component loader not available");
            }
            return null;
        }

        $component_loader = $plugin->get_component_loader();
        if (!$component_loader) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("ChatShop: Cannot get component '{$component_id}' - component loader instance is null");
            }
            return null;
        }

        $component = $component_loader->get_component_instance($component_id);

        if (!$component && defined('WP_DEBUG') && WP_DEBUG) {
            $errors = $component_loader->get_loading_errors();
            $error_msg = isset($errors[$component_id]) ? $errors[$component_id] : 'Component not loaded';
            error_log("ChatShop: Component '{$component_id}' not available - {$error_msg}");
        }

        return $component;
    }
}

if (!function_exists('chatshop_get_component_status')) {
    /**
     * Get detailed component status information
     *
     * @since 1.0.0
     * @param string $component_id Component identifier
     * @return array Component status information
     */
    function chatshop_get_component_status($component_id)
    {
        $status = array(
            'id' => $component_id,
            'loaded' => false,
            'available' => false,
            'error' => null,
            'instance' => null
        );

        if (!chatshop_is_loaded()) {
            $status['error'] = 'Plugin not loaded';
            return $status;
        }

        $plugin = chatshop();
        $component_loader = $plugin ? $plugin->get_component_loader() : null;

        if (!$component_loader) {
            $status['error'] = 'Component loader not available';
            return $status;
        }

        $status['loaded'] = $component_loader->is_component_loaded($component_id);
        $status['instance'] = $component_loader->get_component_instance($component_id);
        $status['available'] = $status['instance'] !== null;

        if (!$status['available']) {
            $errors = $component_loader->get_loading_errors();
            $status['error'] = isset($errors[$component_id]) ? $errors[$component_id] : 'Unknown error';
        }

        return $status;
    }
}

if (!function_exists('chatshop_log')) {
    /**
     * Enhanced logging function with multiple levels
     *
     * @since 1.0.0
     * @param string $message Log message
     * @param string $level Log level (info, warning, error, debug)
     * @param array $context Additional context data
     */
    function chatshop_log($message, $level = 'info', $context = array())
    {
        // Only log if WP_DEBUG is enabled or it's an error
        if (!((defined('WP_DEBUG') && WP_DEBUG) || $level === 'error')) {
            return;
        }

        $timestamp = current_time('Y-m-d H:i:s');
        $level_upper = strtoupper($level);

        // Format the message
        $formatted_message = "[{$timestamp}] ChatShop {$level_upper}: {$message}";

        // Add context if provided
        if (!empty($context)) {
            $formatted_message .= ' | Context: ' . wp_json_encode($context);
        }

        // Log to WordPress debug log
        error_log($formatted_message);

        // Also log to custom ChatShop log file if configured
        $log_file = chatshop_get_option('general', 'custom_log_file', '');
        if (!empty($log_file) && is_writable(dirname($log_file))) {
            error_log($formatted_message . PHP_EOL, 3, $log_file);
        }

        // Store in database for admin display if it's an error
        if ($level === 'error') {
            chatshop_store_error_log($message, $context);
        }
    }
}

if (!function_exists('chatshop_store_error_log')) {
    /**
     * Store error logs in database for admin display
     *
     * @since 1.0.0
     * @param string $message Error message
     * @param array $context Error context
     */
    function chatshop_store_error_log($message, $context = array())
    {
        $error_logs = get_option('chatshop_error_logs', array());

        // Limit to last 50 errors
        if (count($error_logs) >= 50) {
            $error_logs = array_slice($error_logs, -49);
        }

        $error_logs[] = array(
            'timestamp' => current_time('timestamp'),
            'message' => $message,
            'context' => $context,
            'url' => $_SERVER['REQUEST_URI'] ?? '',
            'user_id' => get_current_user_id()
        );

        update_option('chatshop_error_logs', $error_logs);
    }
}

if (!function_exists('chatshop_get_option')) {
    /**
     * Get plugin option with default fallback and validation
     *
     * @since 1.0.0
     * @param string $section Option section
     * @param string $key Option key
     * @param mixed $default Default value
     * @return mixed Option value
     */
    function chatshop_get_option($section, $key, $default = null)
    {
        $option_name = "chatshop_{$section}";
        $options = get_option($option_name, array());

        if (isset($options[$key])) {
            return $options[$key];
        }

        return $default;
    }
}

if (!function_exists('chatshop_update_option')) {
    /**
     * Update plugin option with validation
     *
     * @since 1.0.0
     * @param string $section Option section
     * @param string $key Option key
     * @param mixed $value Option value
     * @return bool True if updated successfully
     */
    function chatshop_update_option($section, $key, $value)
    {
        $option_name = "chatshop_{$section}";
        $options = get_option($option_name, array());

        $options[$key] = $value;

        return update_option($option_name, $options);
    }
}

if (!function_exists('chatshop_delete_option')) {
    /**
     * Delete plugin option
     *
     * @since 1.0.0
     * @param string $section Option section
     * @param string $key Option key
     * @return bool True if deleted successfully
     */
    function chatshop_delete_option($section, $key)
    {
        $option_name = "chatshop_{$section}";
        $options = get_option($option_name, array());

        if (isset($options[$key])) {
            unset($options[$key]);
            return update_option($option_name, $options);
        }

        return false;
    }
}

// ================================
// COMPONENT HELPER FUNCTIONS
// ================================

if (!function_exists('chatshop_get_payment_manager')) {
    /**
     * Get payment manager component instance
     *
     * @since 1.0.0
     * @return object|null Payment manager instance
     */
    function chatshop_get_payment_manager()
    {
        return chatshop_get_component('payment');
    }
}

if (!function_exists('chatshop_get_contact_manager')) {
    /**
     * Get contact manager component instance
     *
     * @since 1.0.0
     * @return object|null Contact manager instance
     */
    function chatshop_get_contact_manager()
    {
        return chatshop_get_component('contact_manager');
    }
}

if (!function_exists('chatshop_get_analytics')) {
    /**
     * Get analytics component instance
     *
     * @since 1.0.0
     * @return object|null Analytics instance
     */
    function chatshop_get_analytics()
    {
        return chatshop_get_component('analytics');
    }
}

if (!function_exists('chatshop_get_whatsapp_manager')) {
    /**
     * Get WhatsApp manager component instance
     *
     * @since 1.0.0
     * @return object|null WhatsApp manager instance
     */
    function chatshop_get_whatsapp_manager()
    {
        return chatshop_get_component('whatsapp');
    }
}

// ================================
// UTILITY FUNCTIONS
// ================================

if (!function_exists('chatshop_is_premium_feature_available')) {
    /**
     * Check if a specific premium feature is available
     *
     * @since 1.0.0
     * @param string $feature Feature name
     * @return bool True if feature is available
     */
    function chatshop_is_premium_feature_available($feature)
    {
        if (!chatshop_is_premium()) {
            return false;
        }

        $premium_features = chatshop_get_option('premium', 'enabled_features', array());

        // Default all features to enabled for development
        $default_features = array(
            'analytics' => true,
            'advanced_analytics' => true,
            'unlimited_contacts' => true,
            'bulk_messaging' => true,
            'campaign_automation' => true,
            'whatsapp_business_api' => true,
            'advanced_payments' => true,
            'custom_reports' => true,
            'priority_support' => true
        );

        return isset($premium_features[$feature]) ?
            $premium_features[$feature] : ($default_features[$feature] ?? false);
    }
}

if (!function_exists('chatshop_format_price')) {
    /**
     * Format price according to WooCommerce settings or fallback
     *
     * @since 1.0.0
     * @param float $price Price to format
     * @param string $currency Currency code
     * @return string Formatted price
     */
    function chatshop_format_price($price, $currency = null)
    {
        if (function_exists('wc_price') && function_exists('get_woocommerce_currency')) {
            return wc_price($price);
        }

        // Fallback formatting
        $currency = $currency ?: chatshop_get_option('general', 'default_currency', 'USD');
        $decimal_places = chatshop_get_option('general', 'decimal_places', 2);

        return $currency . ' ' . number_format($price, $decimal_places);
    }
}

if (!function_exists('chatshop_sanitize_phone')) {
    /**
     * Sanitize phone number for WhatsApp format
     *
     * @since 1.0.0
     * @param string $phone Phone number
     * @return string Sanitized phone number
     */
    function chatshop_sanitize_phone($phone)
    {
        // Remove all non-numeric characters except +
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        // Ensure it starts with +
        if (!empty($phone) && $phone[0] !== '+') {
            $phone = '+' . $phone;
        }

        return $phone;
    }
}

if (!function_exists('chatshop_validate_phone')) {
    /**
     * Validate phone number format
     *
     * @since 1.0.0
     * @param string $phone Phone number
     * @return bool True if valid
     */
    function chatshop_validate_phone($phone)
    {
        $phone = chatshop_sanitize_phone($phone);

        // Basic validation: + followed by 7-15 digits
        return preg_match('/^\+[1-9]\d{6,14}$/', $phone);
    }
}

if (!function_exists('chatshop_get_system_info')) {
    /**
     * Get system information for debugging
     *
     * @since 1.0.0
     * @return array System information
     */
    function chatshop_get_system_info()
    {
        global $wp_version;

        $info = array(
            'wordpress_version' => $wp_version,
            'php_version' => phpversion(),
            'mysql_version' => $GLOBALS['wpdb']->db_version(),
            'plugin_version' => defined('CHATSHOP_VERSION') ? CHATSHOP_VERSION : 'Unknown',
            'wp_debug' => defined('WP_DEBUG') && WP_DEBUG,
            'wp_debug_log' => defined('WP_DEBUG_LOG') && WP_DEBUG_LOG,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size')
        );

        // Add component information
        if (chatshop_is_loaded()) {
            $plugin = chatshop();
            $component_loader = $plugin ? $plugin->get_component_loader() : null;

            if ($component_loader) {
                $info['loaded_components'] = array_keys($component_loader->get_all_instances());
                $info['component_errors'] = $component_loader->get_loading_errors();
                $info['loading_order'] = $component_loader->get_loading_order();
            }
        }

        return $info;
    }
}

if (!function_exists('chatshop_debug_component_status')) {
    /**
     * Debug function to display component status (only in debug mode)
     *
     * @since 1.0.0
     */
    function chatshop_debug_component_status()
    {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        echo '<div class="chatshop-debug-info" style="background: #f0f0f0; padding: 10px; margin: 10px 0; border-left: 4px solid #007cba;">';
        echo '<h4>ChatShop Component Debug Information</h4>';

        if (!chatshop_is_loaded()) {
            echo '<p style="color: red;">ChatShop plugin not loaded</p>';
            echo '</div>';
            return;
        }

        $plugin = chatshop();
        $component_loader = $plugin ? $plugin->get_component_loader() : null;

        if (!$component_loader) {
            echo '<p style="color: red;">Component loader not available</p>';
            echo '</div>';
            return;
        }

        $loaded_components = $component_loader->get_all_instances();
        $errors = $component_loader->get_loading_errors();
        $loading_order = $component_loader->get_loading_order();

        echo '<p><strong>Loaded Components:</strong> ' . count($loaded_components) . '</p>';

        if (!empty($loaded_components)) {
            echo '<ul>';
            foreach (array_keys($loaded_components) as $component_id) {
                echo '<li style="color: green;">✓ ' . esc_html($component_id) . '</li>';
            }
            echo '</ul>';
        }

        if (!empty($errors)) {
            echo '<p><strong>Component Errors:</strong></p>';
            echo '<ul>';
            foreach ($errors as $component_id => $error) {
                echo '<li style="color: red;">✗ ' . esc_html($component_id) . ': ' . esc_html($error) . '</li>';
            }
            echo '</ul>';
        }

        echo '<p><strong>Loading Order:</strong> ' . implode(' → ', $loading_order) . '</p>';
        echo '</div>';
    }
}

// ================================
// CONDITIONAL FUNCTION LOADING
// ================================

if (!function_exists('chatshop_maybe_load_woocommerce_integration')) {
    /**
     * Load WooCommerce integration if WooCommerce is active
     *
     * @since 1.0.0
     */
    function chatshop_maybe_load_woocommerce_integration()
    {
        if (class_exists('WooCommerce') && chatshop_is_loaded()) {
            $integration_file = CHATSHOP_PLUGIN_DIR . 'includes/integrations/class-chatshop-woocommerce-integration.php';

            if (file_exists($integration_file)) {
                require_once $integration_file;
            }
        }
    }
}

// Auto-load WooCommerce integration
add_action('plugins_loaded', 'chatshop_maybe_load_woocommerce_integration', 20);

// ================================
// ERROR HANDLING AND NOTICES
// ================================

if (!function_exists('chatshop_display_admin_errors')) {
    /**
     * Display stored error logs in admin
     *
     * @since 1.0.0
     */
    function chatshop_display_admin_errors()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $error_logs = get_option('chatshop_error_logs', array());

        if (empty($error_logs)) {
            return;
        }

        // Only show recent errors (last 24 hours)
        $recent_errors = array_filter($error_logs, function ($error) {
            return ($error['timestamp'] > (current_time('timestamp') - DAY_IN_SECONDS));
        });

        if (empty($recent_errors)) {
            return;
        }

        echo '<div class="notice notice-error">';
        echo '<p><strong>' . __('ChatShop Errors Detected:', 'chatshop') . '</strong></p>';
        echo '<ul>';

        foreach (array_slice($recent_errors, -5) as $error) {
            $time = date('H:i:s', $error['timestamp']);
            echo '<li><code>' . $time . '</code> - ' . esc_html($error['message']) . '</li>';
        }

        echo '</ul>';
        echo '<p><a href="' . admin_url('admin.php?page=chatshop-settings&tab=debug') . '">' .
            __('View All Errors', 'chatshop') . '</a></p>';
        echo '</div>';
    }
}

// Show admin errors
add_action('admin_notices', 'chatshop_display_admin_errors');

// ================================
// ACTIVATION/DEACTIVATION HELPERS
// ================================

if (!function_exists('chatshop_clear_error_logs')) {
    /**
     * Clear stored error logs
     *
     * @since 1.0.0
     */
    function chatshop_clear_error_logs()
    {
        delete_option('chatshop_error_logs');
    }
}

// ================================
// ADVANCED UTILITY FUNCTIONS
// ================================

if (!function_exists('chatshop_generate_uuid')) {
    /**
     * Generate a UUID v4
     *
     * @since 1.0.0
     * @return string UUID v4
     */
    function chatshop_generate_uuid()
    {
        if (function_exists('wp_generate_uuid4')) {
            return wp_generate_uuid4();
        }

        // Fallback UUID generation
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }
}

if (!function_exists('chatshop_is_ajax_request')) {
    /**
     * Check if current request is AJAX
     *
     * @since 1.0.0
     * @return bool True if AJAX request
     */
    function chatshop_is_ajax_request()
    {
        return (defined('DOING_AJAX') && DOING_AJAX) ||
            (isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
                strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
    }
}

if (!function_exists('chatshop_is_rest_request')) {
    /**
     * Check if current request is REST API
     *
     * @since 1.0.0
     * @return bool True if REST request
     */
    function chatshop_is_rest_request()
    {
        return defined('REST_REQUEST') && REST_REQUEST;
    }
}

if (!function_exists('chatshop_get_current_url')) {
    /**
     * Get current page URL
     *
     * @since 1.0.0
     * @return string Current URL
     */
    function chatshop_get_current_url()
    {
        $protocol = is_ssl() ? 'https://' : 'http://';
        return $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    }
}

if (!function_exists('chatshop_array_get')) {
    /**
     * Get array value with dot notation support
     *
     * @since 1.0.0
     * @param array $array Array to search
     * @param string $key Key (supports dot notation)
     * @param mixed $default Default value
     * @return mixed Array value or default
     */
    function chatshop_array_get($array, $key, $default = null)
    {
        if (is_null($key)) {
            return $array;
        }

        if (isset($array[$key])) {
            return $array[$key];
        }

        foreach (explode('.', $key) as $segment) {
            if (is_array($array) && array_key_exists($segment, $array)) {
                $array = $array[$segment];
            } else {
                return $default;
            }
        }

        return $array;
    }
}

if (!function_exists('chatshop_array_set')) {
    /**
     * Set array value with dot notation support
     *
     * @since 1.0.0
     * @param array &$array Array to modify (by reference)
     * @param string $key Key (supports dot notation)
     * @param mixed $value Value to set
     * @return array Modified array
     */
    function chatshop_array_set(&$array, $key, $value)
    {
        if (is_null($key)) {
            return $array = $value;
        }

        $keys = explode('.', $key);

        while (count($keys) > 1) {
            $key = array_shift($keys);

            if (!isset($array[$key]) || !is_array($array[$key])) {
                $array[$key] = array();
            }

            $array = &$array[$key];
        }

        $array[array_shift($keys)] = $value;

        return $array;
    }
}

if (!function_exists('chatshop_clean_string')) {
    /**
     * Clean string for safe display
     *
     * @since 1.0.0
     * @param string $string String to clean
     * @param bool $strip_tags Strip HTML tags
     * @return string Cleaned string
     */
    function chatshop_clean_string($string, $strip_tags = true)
    {
        $string = trim($string);

        if ($strip_tags) {
            $string = strip_tags($string);
        }

        return sanitize_text_field($string);
    }
}

if (!function_exists('chatshop_time_ago')) {
    /**
     * Get human readable time difference
     *
     * @since 1.0.0
     * @param string|int $time Time string or timestamp
     * @return string Human readable time difference
     */
    function chatshop_time_ago($time)
    {
        $time = is_numeric($time) ? $time : strtotime($time);
        $time_difference = current_time('timestamp') - $time;

        if ($time_difference < 1) {
            return __('less than 1 second ago', 'chatshop');
        }

        $condition = array(
            12 * 30 * 24 * 60 * 60 => __('year', 'chatshop'),
            30 * 24 * 60 * 60      => __('month', 'chatshop'),
            24 * 60 * 60           => __('day', 'chatshop'),
            60 * 60                => __('hour', 'chatshop'),
            60                     => __('minute', 'chatshop'),
            1                      => __('second', 'chatshop')
        );

        foreach ($condition as $secs => $str) {
            $d = $time_difference / $secs;

            if ($d >= 1) {
                $t = round($d);
                return sprintf(
                    _n('%s %s ago', '%s %s ago', $t, 'chatshop'),
                    $t,
                    $str . ($t > 1 ? 's' : '')
                );
            }
        }
    }
}

if (!function_exists('chatshop_bytes_to_human')) {
    /**
     * Convert bytes to human readable format
     *
     * @since 1.0.0
     * @param int $bytes Bytes
     * @param int $precision Decimal precision
     * @return string Human readable size
     */
    function chatshop_bytes_to_human($bytes, $precision = 2)
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB', 'PB');

        for ($i = 0; $bytes > 1024; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
}

// ================================
// SECURITY FUNCTIONS
// ================================

if (!function_exists('chatshop_verify_nonce')) {
    /**
     * Verify nonce with enhanced security
     *
     * @since 1.0.0
     * @param string $nonce Nonce to verify
     * @param string $action Nonce action
     * @return bool True if valid
     */
    function chatshop_verify_nonce($nonce, $action = 'chatshop_admin_nonce')
    {
        if (!wp_verify_nonce($nonce, $action)) {
            chatshop_log("Nonce verification failed for action: {$action}", 'warning', array(
                'user_id' => get_current_user_id(),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ));
            return false;
        }

        return true;
    }
}

if (!function_exists('chatshop_check_permissions')) {
    /**
     * Check user permissions with logging
     *
     * @since 1.0.0
     * @param string $capability Required capability
     * @param bool $die_on_failure Die if permission check fails
     * @return bool True if user has permission
     */
    function chatshop_check_permissions($capability = 'manage_options', $die_on_failure = true)
    {
        if (!current_user_can($capability)) {
            chatshop_log("Permission denied for capability: {$capability}", 'warning', array(
                'user_id' => get_current_user_id(),
                'user_login' => wp_get_current_user()->user_login ?? 'guest',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? ''
            ));

            if ($die_on_failure) {
                wp_die(__('You do not have sufficient permissions to access this page.', 'chatshop'));
            }

            return false;
        }

        return true;
    }
}

if (!function_exists('chatshop_sanitize_array')) {
    /**
     * Recursively sanitize array data
     *
     * @since 1.0.0
     * @param array $array Array to sanitize
     * @return array Sanitized array
     */
    function chatshop_sanitize_array($array)
    {
        if (!is_array($array)) {
            return sanitize_text_field($array);
        }

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = chatshop_sanitize_array($value);
            } else {
                $array[$key] = sanitize_text_field($value);
            }
        }

        return $array;
    }
}

// ================================
// CACHE FUNCTIONS
// ================================

if (!function_exists('chatshop_cache_get')) {
    /**
     * Get cached data with fallback
     *
     * @since 1.0.0
     * @param string $key Cache key
     * @param string $group Cache group
     * @param mixed $default Default value
     * @return mixed Cached data or default
     */
    function chatshop_cache_get($key, $group = 'chatshop', $default = null)
    {
        $cached = wp_cache_get($key, $group);

        if (false === $cached) {
            return $default;
        }

        return $cached;
    }
}

if (!function_exists('chatshop_cache_set')) {
    /**
     * Set cached data
     *
     * @since 1.0.0
     * @param string $key Cache key
     * @param mixed $data Data to cache
     * @param string $group Cache group
     * @param int $expiration Expiration time in seconds
     * @return bool True if cached successfully
     */
    function chatshop_cache_set($key, $data, $group = 'chatshop', $expiration = 3600)
    {
        return wp_cache_set($key, $data, $group, $expiration);
    }
}

if (!function_exists('chatshop_cache_delete')) {
    /**
     * Delete cached data
     *
     * @since 1.0.0
     * @param string $key Cache key
     * @param string $group Cache group
     * @return bool True if deleted successfully
     */
    function chatshop_cache_delete($key, $group = 'chatshop')
    {
        return wp_cache_delete($key, $group);
    }
}

if (!function_exists('chatshop_cache_flush_group')) {
    /**
     * Flush entire cache group
     *
     * @since 1.0.0
     * @param string $group Cache group
     * @return bool True if flushed successfully
     */
    function chatshop_cache_flush_group($group = 'chatshop')
    {
        if (function_exists('wp_cache_flush_group')) {
            return wp_cache_flush_group($group);
        }

        // Fallback: increment cache key version
        $version_key = "{$group}_cache_version";
        $version = get_option($version_key, 1);
        return update_option($version_key, $version + 1);
    }
}

// ================================
// API HELPER FUNCTIONS
// ================================

if (!function_exists('chatshop_make_request')) {
    /**
     * Make HTTP request with proper error handling
     *
     * @since 1.0.0
     * @param string $url Request URL
     * @param array $args Request arguments
     * @return array|WP_Error Response or error
     */
    function chatshop_make_request($url, $args = array())
    {
        $defaults = array(
            'timeout' => 30,
            'headers' => array(
                'User-Agent' => 'ChatShop/' . (defined('CHATSHOP_VERSION') ? CHATSHOP_VERSION : '1.0.0')
            )
        );

        $args = wp_parse_args($args, $defaults);

        chatshop_log("Making HTTP request to: {$url}", 'debug', array(
            'method' => $args['method'] ?? 'GET',
            'timeout' => $args['timeout']
        ));

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            chatshop_log("HTTP request failed: " . $response->get_error_message(), 'error', array(
                'url' => $url,
                'error_code' => $response->get_error_code()
            ));
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($status_code >= 400) {
            $error_message = "HTTP request failed with status {$status_code}";
            chatshop_log($error_message, 'error', array(
                'url' => $url,
                'status_code' => $status_code,
                'response_body' => substr($body, 0, 500)
            ));

            return new \WP_Error('http_request_failed', $error_message, array(
                'status_code' => $status_code,
                'response' => $body
            ));
        }

        return array(
            'status_code' => $status_code,
            'body' => $body,
            'headers' => wp_remote_retrieve_headers($response)
        );
    }
}

if (!function_exists('chatshop_json_decode')) {
    /**
     * Decode JSON with error handling
     *
     * @since 1.0.0
     * @param string $json JSON string
     * @param bool $assoc Return associative array
     * @return mixed|WP_Error Decoded data or error
     */
    function chatshop_json_decode($json, $assoc = true)
    {
        if (empty($json)) {
            return new \WP_Error('empty_json', __('Empty JSON string', 'chatshop'));
        }

        $data = json_decode($json, $assoc);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $error_message = 'JSON decode error: ' . json_last_error_msg();
            chatshop_log($error_message, 'error', array(
                'json_excerpt' => substr($json, 0, 200),
                'json_error_code' => json_last_error()
            ));

            return new \WP_Error('json_decode_error', $error_message);
        }

        return $data;
    }
}

// ================================
// DATABASE HELPER FUNCTIONS
// ================================

if (!function_exists('chatshop_prepare_in_clause')) {
    /**
     * Prepare IN clause for database queries
     *
     * @since 1.0.0
     * @param array $values Values for IN clause
     * @param string $format Format string (%s, %d, etc.)
     * @return string Prepared IN clause
     */
    function chatshop_prepare_in_clause($values, $format = '%s')
    {
        global $wpdb;

        if (empty($values)) {
            return "('')"; // Return empty result
        }

        $placeholders = array_fill(0, count($values), $format);
        $in_clause = '(' . implode(',', $placeholders) . ')';

        return $wpdb->prepare($in_clause, $values);
    }
}

if (!function_exists('chatshop_get_table_name')) {
    /**
     * Get full table name with prefix
     *
     * @since 1.0.0
     * @param string $table_suffix Table suffix
     * @return string Full table name
     */
    function chatshop_get_table_name($table_suffix)
    {
        global $wpdb;
        return $wpdb->prefix . 'chatshop_' . $table_suffix;
    }
}

// ================================
// TEMPLATE FUNCTIONS
// ================================

if (!function_exists('chatshop_get_template')) {
    /**
     * Get template file with fallback
     *
     * @since 1.0.0
     * @param string $template_name Template name
     * @param array $args Template arguments
     * @param string $template_path Template path
     * @param string $default_path Default template path
     * @return string Template content
     */
    function chatshop_get_template($template_name, $args = array(), $template_path = '', $default_path = '')
    {
        if (!empty($args) && is_array($args)) {
            extract($args);
        }

        $located = chatshop_locate_template($template_name, $template_path, $default_path);

        if (!file_exists($located)) {
            chatshop_log("Template file not found: {$template_name}", 'warning', array(
                'template_path' => $template_path,
                'default_path' => $default_path,
                'located' => $located
            ));
            return '';
        }

        ob_start();
        include $located;
        return ob_get_clean();
    }
}

if (!function_exists('chatshop_locate_template')) {
    /**
     * Locate template file
     *
     * @since 1.0.0
     * @param string $template_name Template name
     * @param string $template_path Template path
     * @param string $default_path Default template path
     * @return string Template file path
     */
    function chatshop_locate_template($template_name, $template_path = '', $default_path = '')
    {
        if (!$template_path) {
            $template_path = 'chatshop/';
        }

        if (!$default_path) {
            $default_path = CHATSHOP_PLUGIN_DIR . 'templates/';
        }

        // Look in theme first
        $template = locate_template(array(
            trailingslashit($template_path) . $template_name,
            $template_name
        ));

        // Get default template
        if (!$template) {
            $template = $default_path . $template_name;
        }

        return $template;
    }
}

// ================================
// MAINTENANCE AND CLEANUP
// ================================

if (!function_exists('chatshop_schedule_cleanup')) {
    /**
     * Schedule cleanup tasks
     *
     * @since 1.0.0
     */
    function chatshop_schedule_cleanup()
    {
        if (!wp_next_scheduled('chatshop_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'chatshop_daily_cleanup');
        }

        if (!wp_next_scheduled('chatshop_weekly_maintenance')) {
            wp_schedule_event(time(), 'weekly', 'chatshop_weekly_maintenance');
        }
    }
}

if (!function_exists('chatshop_unschedule_cleanup')) {
    /**
     * Unschedule cleanup tasks
     *
     * @since 1.0.0
     */
    function chatshop_unschedule_cleanup()
    {
        wp_clear_scheduled_hook('chatshop_daily_cleanup');
        wp_clear_scheduled_hook('chatshop_weekly_maintenance');
    }
}

if (!function_exists('chatshop_run_maintenance')) {
    /**
     * Run maintenance tasks
     *
     * @since 1.0.0
     */
    function chatshop_run_maintenance()
    {
        // Clear old error logs
        $error_logs = get_option('chatshop_error_logs', array());
        $week_ago = current_time('timestamp') - (7 * DAY_IN_SECONDS);

        $error_logs = array_filter($error_logs, function ($error) use ($week_ago) {
            return $error['timestamp'] > $week_ago;
        });

        update_option('chatshop_error_logs', array_values($error_logs));

        // Clear transients
        chatshop_cache_flush_group('chatshop');

        // Log maintenance completion
        chatshop_log('Weekly maintenance completed', 'info', array(
            'error_logs_count' => count($error_logs)
        ));
    }
}

// Schedule maintenance tasks
add_action('chatshop_daily_cleanup', 'chatshop_clear_error_logs');
add_action('chatshop_weekly_maintenance', 'chatshop_run_maintenance');

// ================================
// BACKWARDS COMPATIBILITY
// ================================

if (!function_exists('chatshop_deprecated_function')) {
    /**
     * Mark function as deprecated
     *
     * @since 1.0.0
     * @param string $function Function name
     * @param string $version Version when deprecated
     * @param string $replacement Replacement function
     */
    function chatshop_deprecated_function($function, $version, $replacement = null)
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $message = "Function {$function} is deprecated since ChatShop {$version}.";

            if ($replacement) {
                $message .= " Use {$replacement} instead.";
            }

            chatshop_log($message, 'warning', array(
                'function' => $function,
                'version' => $version,
                'replacement' => $replacement,
                'backtrace' => wp_debug_backtrace_summary()
            ));
        }
    }
}

// ================================
// PLUGIN INFORMATION FUNCTIONS
// ================================

if (!function_exists('chatshop_get_plugin_info')) {
    /**
     * Get plugin information
     *
     * @since 1.0.0
     * @return array Plugin information
     */
    function chatshop_get_plugin_info()
    {
        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugin_file = CHATSHOP_PLUGIN_DIR . 'chatshop.php';
        $plugin_data = get_plugin_data($plugin_file);

        return array(
            'name' => $plugin_data['Name'],
            'version' => $plugin_data['Version'],
            'description' => $plugin_data['Description'],
            'author' => $plugin_data['Author'],
            'author_uri' => $plugin_data['AuthorURI'],
            'plugin_uri' => $plugin_data['PluginURI'],
            'text_domain' => $plugin_data['TextDomain'],
            'network' => $plugin_data['Network'],
            'requires_wp' => $plugin_data['RequiresWP'],
            'requires_php' => $plugin_data['RequiresPHP']
        );
    }
}

if (!function_exists('chatshop_get_license_info')) {
    /**
     * Get license information (placeholder for future implementation)
     *
     * @since 1.0.0
     * @return array License information
     */
    function chatshop_get_license_info()
    {
        return array(
            'status' => 'active', // For development
            'type' => 'premium',
            'expires' => null,
            'key' => 'dev-license'
        );
    }
}

// ================================
// FINAL INITIALIZATION
// ================================

// Initialize maintenance scheduling on plugin activation
register_activation_hook(CHATSHOP_PLUGIN_FILE ?? __FILE__, 'chatshop_schedule_cleanup');
register_deactivation_hook(CHATSHOP_PLUGIN_FILE ?? __FILE__, 'chatshop_unschedule_cleanup');

// Add action to handle component debug display
add_action('wp_footer', 'chatshop_debug_component_status');
add_action('admin_footer', 'chatshop_debug_component_status');

/**
 * Final safety check - ensure all required constants are defined
 */
if (!defined('CHATSHOP_PLUGIN_DIR')) {
    define('CHATSHOP_PLUGIN_DIR', plugin_dir_path(__FILE__));
}

if (!defined('CHATSHOP_PLUGIN_URL')) {
    define('CHATSHOP_PLUGIN_URL', plugin_dir_url(__FILE__));
}

if (!defined('CHATSHOP_VERSION')) {
    define('CHATSHOP_VERSION', '1.0.0');
}
