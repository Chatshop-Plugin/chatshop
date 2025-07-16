<?php

/**
 * Fired during plugin activation
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @package ChatShop
 * @since   1.0.0
 */

namespace ChatShop;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ChatShop Activator Class
 *
 * Handles plugin activation tasks including database table creation and initial setup.
 *
 * @since 1.0.0
 */
class ChatShop_Activator
{
    /**
     * Plugin activation method
     *
     * Runs when the plugin is activated.
     *
     * @since 1.0.0
     */
    public static function activate()
    {
        self::check_requirements();
        self::create_tables();
        self::insert_default_settings();
        self::set_activation_flag();
        self::schedule_events();

        // Flush rewrite rules
        flush_rewrite_rules();

        // Hook for extensions
        do_action('chatshop_activated');
    }

    /**
     * Check plugin requirements
     *
     * @since 1.0.0
     * @throws \Exception If requirements are not met
     */
    private static function check_requirements()
    {
        $errors = self::get_activation_errors();

        if (!empty($errors)) {
            wp_die(
                implode('<br>', array_map('esc_html', $errors)),
                esc_html__('ChatShop Activation Error', 'chatshop'),
                array('back_link' => true)
            );
        }
    }

    /**
     * Get activation errors
     *
     * @since 1.0.0
     * @return array
     */
    public static function get_activation_errors()
    {
        $errors = array();

        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            $errors[] = sprintf(
                __('ChatShop requires PHP 7.4 or higher. Your current version is %s.', 'chatshop'),
                PHP_VERSION
            );
        }

        // Check WordPress version
        if (version_compare($GLOBALS['wp_version'], '5.0', '<')) {
            $errors[] = sprintf(
                __('ChatShop requires WordPress 5.0 or higher. Your current version is %s.', 'chatshop'),
                $GLOBALS['wp_version']
            );
        }

        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            $errors[] = __('ChatShop requires WooCommerce to be installed and activated.', 'chatshop');
        }

        // Check database requirements
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        if (empty($charset_collate)) {
            $errors[] = __('Database charset configuration is required for ChatShop.', 'chatshop');
        }

        return $errors;
    }

    /**
     * Create database tables
     *
     * @since 1.0.0
     */
    private static function create_tables()
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Create settings table
        $settings_table = $wpdb->prefix . 'chatshop_settings';

        $settings_sql = "CREATE TABLE $settings_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            option_name varchar(191) NOT NULL,
            option_value longtext,
            is_premium tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY option_name (option_name),
            KEY is_premium (is_premium),
            KEY created_at (created_at)
        ) " . $wpdb->get_charset_collate() . ";";

        dbDelta($settings_sql);

        // Verify table creation
        if ($wpdb->get_var("SHOW TABLES LIKE '$settings_table'") !== $settings_table) {
            error_log('ChatShop: Failed to create settings table');
        }
    }

    /**
     * Insert default settings
     *
     * @since 1.0.0
     */
    private static function insert_default_settings()
    {
        global $wpdb;

        $settings_table = $wpdb->prefix . 'chatshop_settings';

        // Default free settings
        $default_settings = array(
            array(
                'option_name' => 'plugin_version',
                'option_value' => CHATSHOP_VERSION,
                'is_premium' => 0
            ),
            array(
                'option_name' => 'activation_date',
                'option_value' => current_time('mysql'),
                'is_premium' => 0
            ),
            array(
                'option_name' => 'whatsapp_enabled',
                'option_value' => '1',
                'is_premium' => 0
            ),
            array(
                'option_name' => 'payment_enabled',
                'option_value' => '1',
                'is_premium' => 0
            ),
            array(
                'option_name' => 'analytics_enabled',
                'option_value' => '1',
                'is_premium' => 0
            ),
            array(
                'option_name' => 'license_key',
                'option_value' => '',
                'is_premium' => 1
            ),
            array(
                'option_name' => 'bulk_messaging_enabled',
                'option_value' => '0',
                'is_premium' => 1
            ),
            array(
                'option_name' => 'advanced_analytics_enabled',
                'option_value' => '0',
                'is_premium' => 1
            ),
            array(
                'option_name' => 'white_label_enabled',
                'option_value' => '0',
                'is_premium' => 1
            ),
            array(
                'option_name' => 'api_access_enabled',
                'option_value' => '0',
                'is_premium' => 1
            )
        );

        foreach ($default_settings as $setting) {
            $exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM $settings_table WHERE option_name = %s",
                    $setting['option_name']
                )
            );

            if (!$exists) {
                $wpdb->insert(
                    $settings_table,
                    array(
                        'option_name' => sanitize_key($setting['option_name']),
                        'option_value' => sanitize_text_field($setting['option_value']),
                        'is_premium' => absint($setting['is_premium'])
                    ),
                    array('%s', '%s', '%d')
                );
            }
        }
    }

    /**
     * Set activation flag
     *
     * @since 1.0.0
     */
    private static function set_activation_flag()
    {
        update_option('chatshop_activation_redirect', true);
        update_option('chatshop_installed', true);
    }

    /**
     * Schedule WordPress events
     *
     * @since 1.0.0
     */
    private static function schedule_events()
    {
        // Schedule license validation check (daily)
        if (!wp_next_scheduled('chatshop_license_check')) {
            wp_schedule_event(time(), 'daily', 'chatshop_license_check');
        }

        // Schedule cleanup task (weekly)
        if (!wp_next_scheduled('chatshop_cleanup')) {
            wp_schedule_event(time(), 'weekly', 'chatshop_cleanup');
        }
    }

    /**
     * Get setting value from custom table
     *
     * @param string $option_name Setting name
     * @param mixed  $default     Default value if not found
     * @return mixed
     * @since 1.0.0
     */
    public static function get_setting($option_name, $default = false)
    {
        global $wpdb;

        $settings_table = $wpdb->prefix . 'chatshop_settings';

        $value = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT option_value FROM $settings_table WHERE option_name = %s",
                sanitize_key($option_name)
            )
        );

        return $value !== null ? maybe_unserialize($value) : $default;
    }

    /**
     * Update setting value in custom table
     *
     * @param string $option_name  Setting name
     * @param mixed  $option_value Setting value
     * @param bool   $is_premium   Whether this is a premium setting
     * @return bool
     * @since 1.0.0
     */
    public static function update_setting($option_name, $option_value, $is_premium = false)
    {
        global $wpdb;

        $settings_table = $wpdb->prefix . 'chatshop_settings';

        $serialized_value = maybe_serialize($option_value);

        $result = $wpdb->replace(
            $settings_table,
            array(
                'option_name' => sanitize_key($option_name),
                'option_value' => $serialized_value,
                'is_premium' => $is_premium ? 1 : 0
            ),
            array('%s', '%s', '%d')
        );

        return $result !== false;
    }

    /**
     * Delete setting from custom table
     *
     * @param string $option_name Setting name
     * @return bool
     * @since 1.0.0
     */
    public static function delete_setting($option_name)
    {
        global $wpdb;

        $settings_table = $wpdb->prefix . 'chatshop_settings';

        $result = $wpdb->delete(
            $settings_table,
            array('option_name' => sanitize_key($option_name)),
            array('%s')
        );

        return $result !== false;
    }

    /**
     * Get all premium settings
     *
     * @return array
     * @since 1.0.0
     */
    public static function get_premium_settings()
    {
        global $wpdb;

        $settings_table = $wpdb->prefix . 'chatshop_settings';

        $results = $wpdb->get_results(
            "SELECT option_name, option_value FROM $settings_table WHERE is_premium = 1",
            ARRAY_A
        );

        $premium_settings = array();
        foreach ($results as $row) {
            $premium_settings[$row['option_name']] = maybe_unserialize($row['option_value']);
        }

        return $premium_settings;
    }
}
