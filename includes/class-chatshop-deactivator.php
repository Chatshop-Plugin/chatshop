<?php

/**
 * Fired during plugin deactivation
 *
 * @package ChatShop
 * @subpackage ChatShop/includes
 * @since 1.0.0
 */

namespace ChatShop;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Fired during plugin deactivation
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since 1.0.0
 */
class ChatShop_Deactivator
{
    /**
     * Deactivate the plugin
     *
     * @since 1.0.0
     */
    public static function deactivate()
    {
        // Clear scheduled cron events
        self::clear_scheduled_events();

        // Clear transients
        self::clear_transients();

        // Flush rewrite rules
        flush_rewrite_rules();

        // Update deactivation flag
        update_option('chatshop_activated', false);
        update_option('chatshop_deactivation_time', current_time('timestamp'));

        // Hook for extensions
        do_action('chatshop_plugin_deactivated');
    }

    /**
     * Clear scheduled cron events
     *
     * @since 1.0.0
     */
    private static function clear_scheduled_events()
    {
        $cron_events = array(
            'chatshop_daily_cleanup',
            'chatshop_analytics_aggregation',
            'chatshop_process_campaigns'
        );

        foreach ($cron_events as $event) {
            $timestamp = wp_next_scheduled($event);
            if ($timestamp) {
                wp_unschedule_event($timestamp, $event);
            }
        }
    }

    /**
     * Clear plugin transients
     *
     * @since 1.0.0
     */
    private static function clear_transients()
    {
        global $wpdb;

        // Delete all transients with chatshop prefix
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_chatshop_%' 
             OR option_name LIKE '_transient_timeout_chatshop_%'"
        );
    }

    /**
     * Cleanup temporary files
     *
     * @since 1.0.0
     */
    private static function cleanup_temp_files()
    {
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/chatshop/temp';

        if (is_dir($temp_dir)) {
            $files = glob($temp_dir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }
}
