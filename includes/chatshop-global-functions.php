<?php

/**
 * Global Helper Functions - RECURSION FIXED VERSION
 *
 * File: includes/chatshop-global-functions.php
 * 
 * CRITICAL FIXES:
 * - Added recursion protection to chatshop() function
 * - Fixed logging recursion in error handling
 * - Improved initialization state checking
 * - Added emergency fallback mechanisms
 *
 * @package ChatShop
 * @since 1.0.0
 */

namespace ChatShop;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// ================================
// MAIN PLUGIN ACCESS FUNCTION - RECURSION FIXED
// ================================

if (!function_exists('chatshop')) {
    /**
     * Main function to access ChatShop instance - RECURSION PROTECTED
     *
     * @since 1.0.0
     * @return ChatShop|null Main plugin instance or null
     */
    function chatshop()
    {
        static $recursion_depth = 0;

        // Prevent infinite recursion
        $recursion_depth++;
        if ($recursion_depth > 3) {
            // Log recursion attempt if possible
            if (function_exists('error_log')) {
                error_log('ChatShop: Recursion detected in chatshop() function, depth: ' . $recursion_depth);
            }
            $recursion_depth--;
            return null;
        }

        // Check global initialization state
        global $chatshop_initializing, $chatshop_initialization_complete;

        if ($chatshop_initializing) {
            // Still initializing, return null to break recursion
            $recursion_depth--;
            return null;
        }

        try {
            $instance = ChatShop::instance();
            $recursion_depth--;
            return $instance;
        } catch (Exception $e) {
            // Handle exception and reset recursion counter
            if (function_exists('error_log')) {
                error_log('ChatShop: Exception in chatshop() function - ' . $e->getMessage());
            }
            $recursion_depth--;
            return null;
        }
    }
}

if (!function_exists('chatshop_is_loaded')) {
    /**
     * Check if ChatShop is loaded and ready
     *
     * @since 1.0.0
     * @return bool True if loaded, false otherwise
     */
    function chatshop_is_loaded()
    {
        global $chatshop_initialization_complete;

        if (!$chatshop_initialization_complete) {
            return false;
        }

        $plugin = chatshop();
        if (!$plugin) {
            return false;
        }

        if (method_exists($plugin, 'is_initialized')) {
            return $plugin->is_initialized();
        }

        return true;
    }
}

// ================================
// LOGGING FUNCTIONS - RECURSION PROTECTED
// ================================

if (!function_exists('chatshop_log')) {
    /**
     * Log messages with recursion protection
     *
     * @since 1.0.0
     * @param string $message Message to log
     * @param string $level Log level (error, warning, info, debug)
     * @param array $context Additional context data
     * @return bool True if logged successfully, false otherwise
     */
    function chatshop_log($message, $level = 'info', $context = array())
    {
        static $logging_in_progress = false;
        static $log_call_count = 0;

        // Prevent recursion in logging
        if ($logging_in_progress) {
            return false;
        }

        // Prevent excessive logging calls
        $log_call_count++;
        if ($log_call_count > 100) {
            return false;
        }

        $logging_in_progress = true;

        try {
            // Only log if WP_DEBUG is enabled or level is error
            if ((!defined('WP_DEBUG') || !WP_DEBUG) && $level !== 'error') {
                $logging_in_progress = false;
                return false;
            }

            // Format the message
            $formatted_message = sprintf(
                '[ChatShop] [%s] %s',
                strtoupper($level),
                $message
            );

            // Add context if provided
            if (!empty($context)) {
                $formatted_message .= ' | Context: ' . wp_json_encode($context);
            }

            // Use WordPress error_log if available, otherwise fallback
            if (function_exists('error_log')) {
                error_log($formatted_message);
            }

            // Store in database only for errors (avoid recursion)
            if ($level === 'error') {
                chatshop_store_error_log($message, $context);
            }

            $logging_in_progress = false;
            return true;
        } catch (Exception $e) {
            // Emergency fallback - just use error_log if possible
            if (function_exists('error_log')) {
                error_log('ChatShop logging error: ' . $e->getMessage());
            }
            $logging_in_progress = false;
            return false;
        }
    }
}

if (!function_exists('chatshop_store_error_log')) {
    /**
     * Store error log in database with recursion protection
     *
     * @since 1.0.0
     * @param string $message Error message
     * @param array $context Error context
     * @return bool True if stored successfully, false otherwise
     */
    function chatshop_store_error_log($message, $context = array())
    {
        static $storing_in_progress = false;

        // Prevent recursion in error storage
        if ($storing_in_progress) {
            return false;
        }

        $storing_in_progress = true;

        try {
            // Get existing error log (with limit to prevent memory issues)
            $error_log = get_option('chatshop_error_log', array());

            // Ensure it's an array and limit size
            if (!is_array($error_log)) {
                $error_log = array();
            }

            // Keep only last 50 errors to prevent database bloat
            if (count($error_log) >= 50) {
                $error_log = array_slice($error_log, -49, 49, true);
            }

            // Add new error
            $error_entry = array(
                'timestamp' => current_time('mysql'),
                'message' => sanitize_text_field($message),
                'context' => is_array($context) ? $context : array(),
                'user_id' => get_current_user_id(),
                'ip_address' => chatshop_get_user_ip(),
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : ''
            );

            $error_log[] = $error_entry;

            // Update option (this could potentially cause recursion if it fails)
            $result = update_option('chatshop_error_log', $error_log, false);

            $storing_in_progress = false;
            return $result;
        } catch (Exception $e) {
            // Emergency fallback - just log to error_log
            if (function_exists('error_log')) {
                error_log('ChatShop: Failed to store error log - ' . $e->getMessage());
            }
            $storing_in_progress = false;
            return false;
        }
    }
}

if (!function_exists('chatshop_get_user_ip')) {
    /**
     * Get user IP address safely
     *
     * @since 1.0.0
     * @return string User IP address
     */
    function chatshop_get_user_ip()
    {
        // Check for various proxy headers
        $ip_headers = array(
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );

        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // Handle comma-separated IPs
                if (strpos($ip, ',') !== false) {
                    $ip_list = explode(',', $ip);
                    $ip = trim($ip_list[0]);
                }
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return 'unknown';
    }
}

// ================================
// COMPONENT ACCESS FUNCTIONS - SAFE VERSIONS
// ================================

if (!function_exists('chatshop_get_component')) {
    /**
     * Get a specific component instance safely
     *
     * @since 1.0.0
     * @param string $component_id Component identifier
     * @return object|null Component instance or null if not found
     */
    function chatshop_get_component($component_id)
    {
        static $access_depth = 0;

        // Prevent recursion
        $access_depth++;
        if ($access_depth > 3) {
            $access_depth--;
            return null;
        }

        if (!chatshop_is_loaded()) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("ChatShop: Cannot get component '{$component_id}' - plugin not loaded");
            }
            $access_depth--;
            return null;
        }

        $plugin = chatshop();
        if (!$plugin || !method_exists($plugin, 'get_component_loader')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("ChatShop: Cannot get component '{$component_id}' - component loader not available");
            }
            $access_depth--;
            return null;
        }

        $component_loader = $plugin->get_component_loader();
        if (!$component_loader) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("ChatShop: Cannot get component '{$component_id}' - component loader instance is null");
            }
            $access_depth--;
            return null;
        }

        $component = $component_loader->get_component_instance($component_id);

        if (!$component && defined('WP_DEBUG') && WP_DEBUG) {
            $errors = $component_loader->get_loading_errors();
            $error_msg = isset($errors[$component_id]) ? $errors[$component_id] : 'Component not loaded';
            error_log("ChatShop: Component '{$component_id}' not available - {$error_msg}");
        }

        $access_depth--;
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

// ================================
// UTILITY FUNCTIONS
// ================================

if (!function_exists('chatshop_get_system_info')) {
    /**
     * Get comprehensive system information
     *
     * @since 1.0.0
     * @return array System information
     */
    function chatshop_get_system_info()
    {
        $info = array(
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'chatshop_version' => defined('CHATSHOP_VERSION') ? CHATSHOP_VERSION : 'Unknown',
            'wp_debug' => defined('WP_DEBUG') && WP_DEBUG,
            'wp_debug_log' => defined('WP_DEBUG_LOG') && WP_DEBUG_LOG,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size')
        );

        // Add component information safely
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
// SETTINGS AND OPTIONS FUNCTIONS
// ================================

if (!function_exists('chatshop_get_option')) {
    /**
     * Get ChatShop option with default value
     *
     * @since 1.0.0
     * @param string $option_name Option name
     * @param mixed $default Default value
     * @return mixed Option value or default
     */
    function chatshop_get_option($option_name, $default = false)
    {
        $full_option_name = 'chatshop_' . $option_name;
        return get_option($full_option_name, $default);
    }
}

if (!function_exists('chatshop_update_option')) {
    /**
     * Update ChatShop option
     *
     * @since 1.0.0
     * @param string $option_name Option name
     * @param mixed $value Option value
     * @return bool True if updated successfully, false otherwise
     */
    function chatshop_update_option($option_name, $value)
    {
        $full_option_name = 'chatshop_' . $option_name;
        return update_option($full_option_name, $value);
    }
}

if (!function_exists('chatshop_delete_option')) {
    /**
     * Delete ChatShop option
     *
     * @since 1.0.0
     * @param string $option_name Option name
     * @return bool True if deleted successfully, false otherwise
     */
    function chatshop_delete_option($option_name)
    {
        $full_option_name = 'chatshop_' . $option_name;
        return delete_option($full_option_name);
    }
}

// ================================
// DEPRECATED FUNCTION HANDLING
// ================================

if (!function_exists('chatshop_deprecated_function')) {
    /**
     * Handle deprecated function calls
     *
     * @since 1.0.0
     * @param string $function Function name
     * @param string $version Version when deprecated
     * @param string $replacement Replacement function
     */
    function chatshop_deprecated_function($function, $version, $replacement = '')
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $message = "ChatShop: Function {$function} is deprecated since version {$version}";

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
    }
}

if (!function_exists('chatshop_daily_cleanup')) {
    /**
     * Perform daily cleanup tasks
     *
     * @since 1.0.0
     */
    function chatshop_daily_cleanup()
    {
        // Clean old error logs
        $error_log = get_option('chatshop_error_log', array());
        if (is_array($error_log) && count($error_log) > 50) {
            $cleaned_log = array_slice($error_log, -50, 50, true);
            update_option('chatshop_error_log', $cleaned_log);
        }

        // Clean old transients
        global $wpdb;
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d",
                '_transient_timeout_chatshop_%',
                time()
            )
        );
    }
}

// Hook the cleanup function
add_action('chatshop_daily_cleanup', 'ChatShop\\chatshop_daily_cleanup');

// ================================
// PREMIUM FEATURE CHECKS
// ================================

if (!function_exists('chatshop_is_premium')) {
    /**
     * Check if premium features are available
     *
     * @since 1.0.0
     * @return bool True if premium, false otherwise
     */
    function chatshop_is_premium()
    {
        $license_info = chatshop_get_license_info();
        return $license_info['status'] === 'active' && $license_info['type'] === 'premium';
    }
}

if (!function_exists('chatshop_premium_feature_check')) {
    /**
     * Check if specific premium feature is available
     *
     * @since 1.0.0
     * @param string $feature Feature name
     * @return bool True if available, false otherwise
     */
    function chatshop_premium_feature_check($feature)
    {
        if (!chatshop_is_premium()) {
            return false;
        }

        $plugin = chatshop();
        if (!$plugin || !method_exists($plugin, 'is_premium_feature_available')) {
            return false;
        }

        return $plugin->is_premium_feature_available($feature);
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
