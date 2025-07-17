<?php

/**
 * Fired during plugin activation
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
 * Fired during plugin activation
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since 1.0.0
 */
class ChatShop_Activator
{
    /**
     * Activation errors
     *
     * @since 1.0.0
     * @var array
     */
    private static $activation_errors = array();

    /**
     * Activate the plugin
     *
     * @since 1.0.0
     */
    public static function activate()
    {
        // Check system requirements
        self::check_requirements();

        // If there are errors, don't proceed
        if (!empty(self::$activation_errors)) {
            return;
        }

        // Create database tables
        self::create_database_tables();

        // Set default options
        self::set_default_options();

        // Create necessary directories
        self::create_directories();

        // Schedule cron events
        self::schedule_cron_events();

        // Set activation flag
        update_option('chatshop_activated', true);
        update_option('chatshop_activation_time', current_time('timestamp'));

        // Flush rewrite rules
        flush_rewrite_rules();

        // Hook for extensions
        do_action('chatshop_plugin_activated');
    }

    /**
     * Check system requirements
     *
     * @since 1.0.0
     */
    private static function check_requirements()
    {
        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            self::$activation_errors[] = sprintf(
                __('ChatShop requires PHP version 7.4 or higher. You are running version %s.', 'chatshop'),
                PHP_VERSION
            );
        }

        // Check WordPress version
        global $wp_version;
        if (version_compare($wp_version, '6.3', '<')) {
            self::$activation_errors[] = sprintf(
                __('ChatShop requires WordPress version 6.3 or higher. You are running version %s.', 'chatshop'),
                $wp_version
            );
        }

        // Check for WooCommerce
        if (!class_exists('WooCommerce')) {
            self::$activation_errors[] = __('ChatShop requires WooCommerce to be installed and activated.', 'chatshop');
        }

        // Check required PHP extensions
        $required_extensions = array('curl', 'json', 'mbstring');
        foreach ($required_extensions as $extension) {
            if (!extension_loaded($extension)) {
                self::$activation_errors[] = sprintf(
                    __('ChatShop requires the %s PHP extension.', 'chatshop'),
                    $extension
                );
            }
        }

        // Check database capabilities
        global $wpdb;
        $required_tables = array('posts', 'postmeta', 'options', 'users', 'usermeta');
        foreach ($required_tables as $table) {
            $table_name = $wpdb->prefix . $table;
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
                self::$activation_errors[] = sprintf(
                    __('Required database table %s is missing.', 'chatshop'),
                    $table_name
                );
            }
        }

        // Check file permissions
        $upload_dir = wp_upload_dir();
        if (!wp_is_writable($upload_dir['basedir'])) {
            self::$activation_errors[] = __('The uploads directory is not writable. Please check file permissions.', 'chatshop');
        }
    }

    /**
     * Create database tables
     *
     * @since 1.0.0
     */
    private static function create_database_tables()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // WhatsApp contacts table
        $table_name = $wpdb->prefix . 'chatshop_whatsapp_contacts';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            phone_number varchar(20) NOT NULL,
            display_name varchar(255) DEFAULT '',
            first_name varchar(100) DEFAULT '',
            last_name varchar(100) DEFAULT '',
            email varchar(100) DEFAULT '',
            tags text,
            status varchar(20) DEFAULT 'active',
            last_interaction datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY phone_number (phone_number),
            KEY status (status),
            KEY last_interaction (last_interaction)
        ) $charset_collate;";

        // WhatsApp messages table
        $table_name = $wpdb->prefix . 'chatshop_whatsapp_messages';
        $sql .= "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            contact_id bigint(20) NOT NULL,
            message_id varchar(255) NOT NULL,
            direction enum('inbound','outbound') NOT NULL,
            message_type varchar(20) DEFAULT 'text',
            content text,
            media_url varchar(500) DEFAULT '',
            status varchar(20) DEFAULT 'sent',
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY message_id (message_id),
            KEY contact_id (contact_id),
            KEY direction (direction),
            KEY status (status),
            KEY timestamp (timestamp)
        ) $charset_collate;";

        // Payment links table
        $table_name = $wpdb->prefix . 'chatshop_payment_links';
        $sql .= "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            link_id varchar(50) NOT NULL,
            contact_id bigint(20) DEFAULT NULL,
            order_id bigint(20) DEFAULT NULL,
            amount decimal(10,2) NOT NULL,
            currency varchar(3) DEFAULT 'NGN',
            description text,
            status varchar(20) DEFAULT 'pending',
            gateway varchar(20) DEFAULT 'paystack',
            gateway_reference varchar(255) DEFAULT '',
            expires_at datetime DEFAULT NULL,
            paid_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY link_id (link_id),
            KEY contact_id (contact_id),
            KEY order_id (order_id),
            KEY status (status),
            KEY gateway (gateway),
            KEY expires_at (expires_at)
        ) $charset_collate;";

        // Campaigns table
        $table_name = $wpdb->prefix . 'chatshop_campaigns';
        $sql .= "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            message_template text NOT NULL,
            target_audience text,
            status varchar(20) DEFAULT 'draft',
            scheduled_at datetime DEFAULT NULL,
            sent_at datetime DEFAULT NULL,
            total_recipients int(11) DEFAULT 0,
            total_sent int(11) DEFAULT 0,
            total_delivered int(11) DEFAULT 0,
            total_read int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY scheduled_at (scheduled_at)
        ) $charset_collate;";

        // Analytics table
        $table_name = $wpdb->prefix . 'chatshop_analytics';
        $sql .= "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            metric_name varchar(100) NOT NULL,
            metric_value decimal(15,4) DEFAULT 0,
            metric_date date NOT NULL,
            meta_data text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY metric_date_name (metric_date, metric_name),
            KEY metric_name (metric_name),
            KEY metric_date (metric_date)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Update database version
        update_option('chatshop_db_version', '1.0.0');
    }

    /**
     * Set default options
     *
     * @since 1.0.0
     */
    private static function set_default_options()
    {
        // General options
        $general_defaults = array(
            'plugin_enabled' => true,
            'debug_mode'     => false,
            'log_level'      => 'error'
        );
        add_option('chatshop_general_options', $general_defaults);

        // WhatsApp options
        $whatsapp_defaults = array(
            'api_token'     => '',
            'phone_number'  => '',
            'webhook_url'   => '',
            'auto_reply'    => false
        );
        add_option('chatshop_whatsapp_options', $whatsapp_defaults);

        // Payment options
        $payment_defaults = array(
            'default_gateway'      => 'paystack',
            'paystack_secret_key'  => '',
            'paystack_public_key'  => '',
            'test_mode'            => true,
            'currency'             => 'NGN'
        );
        add_option('chatshop_payments_options', $payment_defaults);

        // Analytics options
        $analytics_defaults = array(
            'enable_tracking' => true,
            'retention_days'  => 365
        );
        add_option('chatshop_analytics_options', $analytics_defaults);
    }

    /**
     * Create necessary directories
     *
     * @since 1.0.0
     */
    private static function create_directories()
    {
        $upload_dir = wp_upload_dir();
        $chatshop_dir = $upload_dir['basedir'] . '/chatshop';

        $directories = array(
            $chatshop_dir,
            $chatshop_dir . '/logs',
            $chatshop_dir . '/exports',
            $chatshop_dir . '/temp'
        );

        foreach ($directories as $dir) {
            if (!file_exists($dir)) {
                wp_mkdir_p($dir);

                // Create .htaccess for security
                $htaccess_content = "Order deny,allow\nDeny from all\n";
                file_put_contents($dir . '/.htaccess', $htaccess_content);

                // Create index.php for security
                $index_content = "<?php\n// Silence is golden.\n";
                file_put_contents($dir . '/index.php', $index_content);
            }
        }
    }

    /**
     * Schedule cron events
     *
     * @since 1.0.0
     */
    private static function schedule_cron_events()
    {
        // Schedule daily cleanup
        if (!wp_next_scheduled('chatshop_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'chatshop_daily_cleanup');
        }

        // Schedule analytics aggregation
        if (!wp_next_scheduled('chatshop_analytics_aggregation')) {
            wp_schedule_event(time(), 'hourly', 'chatshop_analytics_aggregation');
        }

        // Schedule campaign processing
        if (!wp_next_scheduled('chatshop_process_campaigns')) {
            wp_schedule_event(time(), 'chatshop_five_minutes', 'chatshop_process_campaigns');
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
        return self::$activation_errors;
    }

    /**
     * Check if plugin was properly activated
     *
     * @since 1.0.0
     * @return bool
     */
    public static function is_activated()
    {
        return get_option('chatshop_activated', false);
    }
}
