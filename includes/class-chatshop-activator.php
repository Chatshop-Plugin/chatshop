<?php

/**
 * Fired during plugin activation
 *
 * @package    ChatShop
 * @subpackage ChatShop/includes
 * @since      1.0.0
 */

namespace ChatShop;

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    ChatShop
 * @subpackage ChatShop/includes
 * @author     Plugin Developer
 */
class ChatShop_Activator
{

    /**
     * Minimum WordPress version required
     *
     * @since 1.0.0
     * @var string
     */
    const MIN_WP_VERSION = '5.0';

    /**
     * Minimum WooCommerce version required
     *
     * @since 1.0.0
     * @var string
     */
    const MIN_WC_VERSION = '4.0';

    /**
     * Minimum PHP version required
     *
     * @since 1.0.0
     * @var string
     */
    const MIN_PHP_VERSION = '7.4';

    /**
     * Activate the plugin.
     *
     * @since 1.0.0
     */
    public static function activate()
    {
        self::check_dependencies();
        self::create_tables();
        self::set_default_options();
        self::create_capabilities();

        // Flush rewrite rules
        flush_rewrite_rules();

        // Set activation flag
        set_transient('chatshop_activated', true, 30);
    }

    /**
     * Check plugin dependencies.
     *
     * @since 1.0.0
     * @throws \Exception If dependencies are not met.
     */
    private static function check_dependencies()
    {
        // Check PHP version
        if (version_compare(PHP_VERSION, self::MIN_PHP_VERSION, '<')) {
            deactivate_plugins(CHATSHOP_PLUGIN_BASENAME);
            wp_die(
                sprintf(
                    /* translators: %s: PHP version */
                    esc_html__('ChatShop requires PHP %s or higher. Please upgrade PHP.', 'chatshop'),
                    self::MIN_PHP_VERSION
                ),
                esc_html__('Plugin Activation Error', 'chatshop'),
                array('back_link' => true)
            );
        }

        // Check WordPress version
        if (version_compare(get_bloginfo('version'), self::MIN_WP_VERSION, '<')) {
            deactivate_plugins(CHATSHOP_PLUGIN_BASENAME);
            wp_die(
                sprintf(
                    /* translators: %s: WordPress version */
                    esc_html__('ChatShop requires WordPress %s or higher. Please upgrade WordPress.', 'chatshop'),
                    self::MIN_WP_VERSION
                ),
                esc_html__('Plugin Activation Error', 'chatshop'),
                array('back_link' => true)
            );
        }

        // Check if WooCommerce is active
        if (! class_exists('WooCommerce')) {
            deactivate_plugins(CHATSHOP_PLUGIN_BASENAME);
            wp_die(
                esc_html__('ChatShop requires WooCommerce to be installed and active.', 'chatshop'),
                esc_html__('Plugin Activation Error', 'chatshop'),
                array('back_link' => true)
            );
        }

        // Check WooCommerce version
        $wc_version = defined('WC_VERSION') ? WC_VERSION : '0';
        if (version_compare($wc_version, self::MIN_WC_VERSION, '<')) {
            deactivate_plugins(CHATSHOP_PLUGIN_BASENAME);
            wp_die(
                sprintf(
                    /* translators: %s: WooCommerce version */
                    esc_html__('ChatShop requires WooCommerce %s or higher. Please upgrade WooCommerce.', 'chatshop'),
                    self::MIN_WC_VERSION
                ),
                esc_html__('Plugin Activation Error', 'chatshop'),
                array('back_link' => true)
            );
        }
    }

    /**
     * Create database tables.
     *
     * @since 1.0.0
     */
    private static function create_tables()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Settings table
        $table_name = $wpdb->prefix . 'chatshop_settings';

        $sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
			`id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			`option_name` varchar(191) NOT NULL,
			`option_value` longtext,
			`created_at` datetime DEFAULT CURRENT_TIMESTAMP,
			`updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (`id`),
			UNIQUE KEY `option_name` (`option_name`),
			KEY `idx_option_name` (`option_name`)
		) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        // Store table version for future migrations
        add_option('chatshop_db_version', '1.0.0');
    }

    /**
     * Set default plugin options.
     *
     * @since 1.0.0
     */
    private static function set_default_options()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'chatshop_settings';

        // Default settings
        $defaults = array(
            'whatsapp_enabled'     => 'yes',
            'payment_enabled'      => 'yes',
            'default_gateway'      => 'paystack',
            'api_version'          => '1.0',
            'debug_mode'           => 'no',
            'currency'             => get_woocommerce_currency(),
            'enable_analytics'     => 'yes',
            'enable_notifications' => 'yes',
        );

        foreach ($defaults as $option_name => $option_value) {
            $wpdb->insert(
                $table_name,
                array(
                    'option_name'  => sanitize_key($option_name),
                    'option_value' => sanitize_text_field($option_value),
                ),
                array('%s', '%s')
            );
        }

        // Set plugin version
        update_option('chatshop_version', CHATSHOP_VERSION);
        update_option('chatshop_activated_time', current_time('mysql'));
    }

    /**
     * Create plugin capabilities.
     *
     * @since 1.0.0
     */
    private static function create_capabilities()
    {
        $role = get_role('administrator');

        if ($role) {
            $capabilities = array(
                'manage_chatshop',
                'manage_chatshop_settings',
                'manage_chatshop_campaigns',
                'manage_chatshop_payments',
                'view_chatshop_reports',
            );

            foreach ($capabilities as $cap) {
                $role->add_cap($cap);
            }
        }

        // Add shop manager capabilities
        $shop_manager = get_role('shop_manager');

        if ($shop_manager) {
            $shop_manager_caps = array(
                'manage_chatshop_campaigns',
                'view_chatshop_reports',
            );

            foreach ($shop_manager_caps as $cap) {
                $shop_manager->add_cap($cap);
            }
        }
    }

    /**
     * Get activation errors if any.
     *
     * @since 1.0.0
     * @return array Array of error messages.
     */
    public static function get_activation_errors()
    {
        $errors = array();

        // Check PHP version
        if (version_compare(PHP_VERSION, self::MIN_PHP_VERSION, '<')) {
            $errors[] = sprintf(
                /* translators: %s: PHP version */
                esc_html__('PHP %s or higher required', 'chatshop'),
                self::MIN_PHP_VERSION
            );
        }

        // Check WordPress version
        if (version_compare(get_bloginfo('version'), self::MIN_WP_VERSION, '<')) {
            $errors[] = sprintf(
                /* translators: %s: WordPress version */
                esc_html__('WordPress %s or higher required', 'chatshop'),
                self::MIN_WP_VERSION
            );
        }

        // Check WooCommerce
        if (! class_exists('WooCommerce')) {
            $errors[] = esc_html__('WooCommerce must be installed and active', 'chatshop');
        } elseif (defined('WC_VERSION') && version_compare(WC_VERSION, self::MIN_WC_VERSION, '<')) {
            $errors[] = sprintf(
                /* translators: %s: WooCommerce version */
                esc_html__('WooCommerce %s or higher required', 'chatshop'),
                self::MIN_WC_VERSION
            );
        }

        return $errors;
    }
}
