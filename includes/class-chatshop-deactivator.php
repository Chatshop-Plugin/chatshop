<?php

/**
 * Fired during plugin deactivation
 *
 * @package    ChatShop
 * @subpackage ChatShop/includes
 * @since      1.0.0
 */

namespace ChatShop;

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    ChatShop
 * @subpackage ChatShop/includes
 * @author     Plugin Developer
 */
class ChatShop_Deactivator
{

    /**
     * Deactivate the plugin.
     *
     * Cleans up temporary data, transients, and scheduled events.
     * Preserves user data and settings for potential reactivation.
     *
     * @since 1.0.0
     */
    public static function deactivate()
    {
        self::clear_scheduled_events();
        self::clear_transients();
        self::clear_cache();
        self::remove_capabilities();

        // Flush rewrite rules
        flush_rewrite_rules();

        // Log deactivation
        self::log_deactivation();
    }

    /**
     * Clear all scheduled events.
     *
     * @since 1.0.0
     */
    private static function clear_scheduled_events()
    {
        // Clear any scheduled hooks
        $hooks = array(
            'chatshop_daily_cleanup',
            'chatshop_process_queue',
            'chatshop_sync_data',
            'chatshop_send_notifications',
        );

        foreach ($hooks as $hook) {
            $timestamp = wp_next_scheduled($hook);
            if ($timestamp) {
                wp_unschedule_event($timestamp, $hook);
            }
            // Clear all occurrences
            wp_clear_scheduled_hook($hook);
        }
    }

    /**
     * Clear plugin transients.
     *
     * @since 1.0.0
     */
    private static function clear_transients()
    {
        global $wpdb;

        // Delete plugin-specific transients
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
			WHERE option_name LIKE '_transient_chatshop_%' 
			OR option_name LIKE '_transient_timeout_chatshop_%'"
        );

        // Delete specific transients
        delete_transient('chatshop_activated');
        delete_transient('chatshop_admin_notice');
        delete_transient('chatshop_api_cache');
    }

    /**
     * Clear plugin cache.
     *
     * @since 1.0.0
     */
    private static function clear_cache()
    {
        // Clear any object cache
        wp_cache_delete_group('chatshop');

        // Clear plugin-specific cache options
        delete_option('chatshop_cache_version');
        delete_option('chatshop_last_sync');
    }

    /**
     * Remove plugin capabilities.
     *
     * @since 1.0.0
     */
    private static function remove_capabilities()
    {
        $capabilities = array(
            'manage_chatshop',
            'manage_chatshop_settings',
            'manage_chatshop_campaigns',
            'manage_chatshop_payments',
            'view_chatshop_reports',
        );

        // Remove from all roles
        foreach (wp_roles()->roles as $role_name => $role_info) {
            $role = get_role($role_name);
            if ($role) {
                foreach ($capabilities as $cap) {
                    $role->remove_cap($cap);
                }
            }
        }
    }

    /**
     * Log deactivation for debugging.
     *
     * @since 1.0.0
     */
    private static function log_deactivation()
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'ChatShop deactivated at %s',
                current_time('mysql')
            ));
        }
    }
}
