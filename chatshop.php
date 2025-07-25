<?php

/**
 * Plugin Name:       ChatShop
 * Plugin URI:        https://modewebhost.com.ng
 * Description:       Social commerce plugin for WhatsApp and payments with contact management and analytics
 * Version:           1.0.0
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Author:            Modewebhost
 * Author URI:        https://modewebhost.com.ng
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       chatshop
 * Domain Path:       /languages
 *
 * File: chatshop.php
 * 
 * @package ChatShop
 */

namespace ChatShop;

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('CHATSHOP_VERSION', '1.0.0');
define('CHATSHOP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CHATSHOP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CHATSHOP_PLUGIN_FILE', __FILE__);
define('CHATSHOP_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Load global helper functions FIRST (required by other classes)
 */
require_once CHATSHOP_PLUGIN_DIR . 'includes/chatshop-global-functions.php';

/**
 * Load core classes
 */
require_once CHATSHOP_PLUGIN_DIR . 'includes/class-chatshop-loader.php';
require_once CHATSHOP_PLUGIN_DIR . 'includes/class-chatshop-logger.php';
require_once CHATSHOP_PLUGIN_DIR . 'includes/class-chatshop-i18n.php';
require_once CHATSHOP_PLUGIN_DIR . 'includes/class-chatshop-activator.php';
require_once CHATSHOP_PLUGIN_DIR . 'includes/class-chatshop-deactivator.php';
require_once CHATSHOP_PLUGIN_DIR . 'includes/class-chatshop-component-registry.php';
require_once CHATSHOP_PLUGIN_DIR . 'includes/class-chatshop-component-loader.php';

/**
 * Load helper functions - CONSOLIDATED SINGLE FILE
 */
require_once CHATSHOP_PLUGIN_DIR . 'includes/class-chatshop-helper.php';

/**
 * Load abstract classes
 */
require_once CHATSHOP_PLUGIN_DIR . 'includes/abstracts/abstract-chatshop-component.php';
require_once CHATSHOP_PLUGIN_DIR . 'includes/abstracts/abstract-chatshop-payment-gateway.php';
require_once CHATSHOP_PLUGIN_DIR . 'includes/abstracts/abstract-chatshop-api-client.php';

/**
 * Load payment component classes - Check if files exist before requiring
 */
$payment_factory_path = CHATSHOP_PLUGIN_DIR . 'components/payment/class-chatshop-payment-factory.php';
$payment_manager_path = CHATSHOP_PLUGIN_DIR . 'components/payment/class-chatshop-payment-manager.php';

if (file_exists($payment_factory_path)) {
    require_once $payment_factory_path;
}

if (file_exists($payment_manager_path)) {
    require_once $payment_manager_path;
}

/**
 * Load contact management component classes - CONTACT SYSTEM
 */
$contact_manager_path = CHATSHOP_PLUGIN_DIR . 'components/whatsapp/class-chatshop-contact-manager.php';
$contact_import_export_path = CHATSHOP_PLUGIN_DIR . 'components/whatsapp/class-chatshop-contact-import-export.php';

if (file_exists($contact_manager_path)) {
    require_once $contact_manager_path;
}

if (file_exists($contact_import_export_path)) {
    require_once $contact_import_export_path;
}

/**
 * Load analytics component classes - ANALYTICS SYSTEM
 */
$analytics_path = CHATSHOP_PLUGIN_DIR . 'components/analytics/class-chatshop-analytics.php';
$analytics_export_path = CHATSHOP_PLUGIN_DIR . 'components/analytics/class-chatshop-analytics-export.php';

if (file_exists($analytics_path)) {
    require_once $analytics_path;
}

if (file_exists($analytics_export_path)) {
    require_once $analytics_export_path;
}

/**
 * Main ChatShop class
 *
 * @since 1.0.0
 */
final class ChatShop
{
    /**
     * The single instance of the class
     *
     * @var ChatShop
     * @since 1.0.0
     */
    private static $instance = null;

    /**
     * The loader that's responsible for maintaining and registering all hooks
     *
     * @var ChatShop_Loader
     * @since 1.0.0
     */
    private $loader;

    /**
     * The admin instance
     *
     * @var ChatShop_Admin
     * @since 1.0.0
     */
    private $admin;

    /**
     * The public instance
     *
     * @var ChatShop_Public
     * @since 1.0.0
     */
    private $public;

    /**
     * Component loader instance
     *
     * @var ChatShop_Component_Loader
     * @since 1.0.0
     */
    private $component_loader;

    /**
     * Payment manager instance
     *
     * @var ChatShop_Payment_Manager
     * @since 1.0.0
     */
    private $payment_manager;

    /**
     * Contact manager instance
     *
     * @var ChatShop_Contact_Manager
     * @since 1.0.0
     */
    private $contact_manager;

    /**
     * Analytics instance - ANALYTICS INTEGRATION
     *
     * @var ChatShop_Analytics
     * @since 1.0.0
     */
    private $analytics;

    /**
     * Registered payment gateways
     *
     * @var array
     * @since 1.0.0
     */
    private $registered_gateways = array();

    /**
     * Premium features status
     *
     * @var array
     * @since 1.0.0
     */
    private $premium_features = array();

    /**
     * Main ChatShop Instance
     *
     * Ensures only one instance of ChatShop is loaded or can be loaded.
     *
     * @since 1.0.0
     * @static
     * @return ChatShop - Main instance
     */
    public static function instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * ChatShop Constructor
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        $this->check_requirements();
        $this->init_premium_features();
        $this->load_dependencies();
        $this->init_loader();
        $this->init_logger();
        $this->set_locale();
        $this->init_component_loader();
        $this->init_core_components();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->run();
    }

    /**
     * Initialize component loader
     *
     * @since 1.0.0
     */
    private function init_component_loader()
    {
        $this->component_loader = new ChatShop_Component_Loader();
        $this->component_loader->load_components();
    }

    /**
     * Initialize core components
     *
     * @since 1.0.0
     */
    private function init_core_components()
    {
        // Initialize payment manager
        if (class_exists('ChatShop\ChatShop_Payment_Manager')) {
            $this->payment_manager = new ChatShop_Payment_Manager();
        }

        // Initialize contact manager if available
        if (class_exists('ChatShop\ChatShop_Contact_Manager')) {
            $this->contact_manager = new ChatShop_Contact_Manager();
        }

        // Initialize analytics if available
        if (class_exists('ChatShop\ChatShop_Analytics')) {
            $this->analytics = new ChatShop_Analytics();
        }

        // Load payment gateways
        $this->load_payment_gateways();
    }

    /**
     * Load payment gateways
     *
     * @since 1.0.0
     */
    private function load_payment_gateways()
    {
        $gateways_dir = CHATSHOP_PLUGIN_DIR . 'components/payment/gateways/';

        // Load Paystack gateway
        $paystack_files = array(
            'paystack/class-chatshop-paystack-api.php',
            'paystack/class-chatshop-paystack-gateway.php',
            'paystack/class-chatshop-paystack-webhook.php'
        );

        foreach ($paystack_files as $file) {
            $file_path = $gateways_dir . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }

        // Load other gateways (when implemented)
        $other_gateways = array('paypal', 'flutterwave', 'razorpay');

        foreach ($other_gateways as $gateway) {
            $gateway_dir = $gateways_dir . $gateway . '/';

            if (is_dir($gateway_dir)) {
                $gateway_files = glob($gateway_dir . '*.php');
                foreach ($gateway_files as $file) {
                    require_once $file;
                }
            }
        }
    }

    /**
     * Register payment gateway
     *
     * @since 1.0.0
     * @param string $gateway_id Gateway identifier
     * @param object $gateway_instance Gateway instance
     */
    public function register_payment_gateway($gateway_id, $gateway_instance)
    {
        $this->registered_gateways[$gateway_id] = $gateway_instance;
        chatshop_log("Payment gateway registered: {$gateway_id}", 'info');
    }

    /**
     * Get registered payment gateways
     *
     * @since 1.0.0
     * @return array Registered gateways
     */
    public function get_registered_gateways()
    {
        return $this->registered_gateways;
    }

    /**
     * Initialize plugin with version check
     *
     * @since 1.0.0
     */
    public function init()
    {
        ChatShop_i18n::load_plugin_textdomain(get_plugin_data(CHATSHOP_PLUGIN_FILE, false, false)['TextDomain'] ?? 'chatshop', false, dirname(CHATSHOP_PLUGIN_BASENAME) . '/languages/');

        do_action('chatshop_loaded');
        chatshop_log('ChatShop plugin initialized successfully', 'info', array('version' => CHATSHOP_VERSION));
    }

    /**
     * Load the required dependencies for this plugin
     *
     * @since 1.0.0
     */
    private function load_dependencies()
    {
        // Load admin class if in admin
        if (is_admin()) {
            $admin_path = CHATSHOP_PLUGIN_DIR . 'admin/class-chatshop-admin.php';
            if (file_exists($admin_path)) {
                require_once $admin_path;
            }
        }

        // Load public class
        $public_path = CHATSHOP_PLUGIN_DIR . 'public/class-chatshop-public.php';
        if (file_exists($public_path)) {
            require_once $public_path;
        }
    }

    /**
     * Initialize the loader
     *
     * @since 1.0.0
     */
    private function init_loader()
    {
        $this->loader = new ChatShop_Loader();
    }

    /**
     * Initialize logger
     *
     * @since 1.0.0
     */
    private function init_logger()
    {
        ChatShop_Logger::init();
    }

    /**
     * Set the plugin locale
     *
     * @since 1.0.0
     */
    private function set_locale()
    {
        $plugin_i18n = new ChatShop_i18n();
        $this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
    }

    /**
     * Check plugin requirements
     *
     * @since 1.0.0
     */
    private function check_requirements()
    {
        // Check WordPress version
        if (version_compare(get_bloginfo('version'), '6.3', '<')) {
            add_action('admin_notices', array($this, 'wordpress_version_notice'));
            return;
        }

        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            add_action('admin_notices', array($this, 'php_version_notice'));
            return;
        }

        // Check if WooCommerce is active (recommended)
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_notice'));
        }
    }

    /**
     * Initialize premium features
     *
     * @since 1.0.0
     */
    private function init_premium_features()
    {
        // Check premium license status
        $premium_options = get_option('chatshop_premium_features', array());

        $this->premium_features = array(
            'unlimited_contacts' => isset($premium_options['unlimited_contacts']) ? (bool) $premium_options['unlimited_contacts'] : false,
            'contact_import_export' => isset($premium_options['contact_import_export']) ? (bool) $premium_options['contact_import_export'] : false,
            'bulk_messaging' => isset($premium_options['bulk_messaging']) ? (bool) $premium_options['bulk_messaging'] : false,
            'advanced_analytics' => isset($premium_options['advanced_analytics']) ? (bool) $premium_options['advanced_analytics'] : false,
            'analytics' => isset($premium_options['analytics']) ? (bool) $premium_options['analytics'] : false,
            'whatsapp_business_api' => isset($premium_options['whatsapp_business_api']) ? (bool) $premium_options['whatsapp_business_api'] : false,
            'campaign_automation' => isset($premium_options['campaign_automation']) ? (bool) $premium_options['campaign_automation'] : false,
            'custom_reports' => isset($premium_options['custom_reports']) ? (bool) $premium_options['custom_reports'] : false
        );
    }

    /**
     * Register all of the hooks related to the admin area functionality
     *
     * @since 1.0.0
     */
    private function define_admin_hooks()
    {
        if (!is_admin()) {
            return;
        }

        $admin_path = CHATSHOP_PLUGIN_DIR . 'admin/class-chatshop-admin.php';
        if (file_exists($admin_path)) {
            require_once $admin_path;

            // ChatShop_Admin constructor doesn't take parameters
            $plugin_admin = new ChatShop_Admin();

            $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
            $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
        }
    }

    /**
     * Register all of the hooks related to the public-facing functionality
     *
     * @since 1.0.0
     */
    private function define_public_hooks()
    {
        $public_path = CHATSHOP_PLUGIN_DIR . 'public/class-chatshop-public.php';
        if (file_exists($public_path)) {
            require_once $public_path;

            // ChatShop_Public constructor doesn't take parameters
            $plugin_public = new ChatShop_Public();

            $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles');
            $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts');
        }
    }

    /**
     * Run the loader to execute all of the hooks
     *
     * @since 1.0.0
     */
    public function run()
    {
        $this->loader->run();
    }

    /**
     * The name of the plugin used to uniquely identify it
     *
     * @since 1.0.0
     * @return string The name of the plugin
     */
    public function get_plugin_name()
    {
        return 'chatshop';
    }

    /**
     * The reference to the class that orchestrates the hooks with the plugin
     *
     * @since 1.0.0
     * @return ChatShop_Loader Orchestrates the hooks of the plugin
     */
    public function get_loader()
    {
        return $this->loader;
    }

    /**
     * Retrieve the version number of the plugin
     *
     * @since 1.0.0
     * @return string The version number of the plugin
     */
    public function get_version()
    {
        return CHATSHOP_VERSION;
    }

    /**
     * Get payment manager instance
     *
     * @since 1.0.0
     * @return ChatShop_Payment_Manager|null Payment manager instance
     */
    public function get_payment_manager()
    {
        return $this->payment_manager;
    }

    /**
     * Get contact manager instance
     *
     * @since 1.0.0
     * @return ChatShop_Contact_Manager|null Contact manager instance
     */
    public function get_contact_manager()
    {
        return $this->contact_manager;
    }

    /**
     * Get analytics instance - ANALYTICS GETTER
     *
     * @since 1.0.0
     * @return ChatShop_Analytics|null Analytics instance
     */
    public function get_analytics()
    {
        return $this->analytics;
    }

    /**
     * Get component loader instance
     *
     * @since 1.0.0
     * @return ChatShop_Component_Loader Component loader instance
     */
    public function get_component_loader()
    {
        return $this->component_loader;
    }

    /**
     * Check if premium feature is available
     *
     * @since 1.0.0
     * @param string $feature Feature name
     * @return bool Whether feature is available
     */
    public function is_premium_feature_available($feature)
    {
        return isset($this->premium_features[$feature]) && $this->premium_features[$feature];
    }

    /**
     * WordPress version notice
     *
     * @since 1.0.0
     */
    public function wordpress_version_notice()
    {
        echo '<div class="notice notice-error"><p>';
        printf(
            __('ChatShop requires WordPress version 6.3 or higher. You are running version %s. Please update WordPress.', 'chatshop'),
            get_bloginfo('version')
        );
        echo '</p></div>';
    }

    /**
     * PHP version notice
     *
     * @since 1.0.0
     */
    public function php_version_notice()
    {
        echo '<div class="notice notice-error"><p>';
        printf(
            __('ChatShop requires PHP version 7.4 or higher. You are running version %s. Please contact your hosting provider to upgrade PHP.', 'chatshop'),
            PHP_VERSION
        );
        echo '</p></div>';
    }

    /**
     * WooCommerce notice
     *
     * @since 1.0.0
     */
    public function woocommerce_notice()
    {
        echo '<div class="notice notice-warning"><p>';
        _e('ChatShop works best with WooCommerce. Please install and activate WooCommerce for the full experience.', 'chatshop');
        echo '</p></div>';
    }
}

/**
 * Begins execution of the plugin
 *
 * @since 1.0.0
 */
function chatshop_run()
{
    $plugin = ChatShop::instance();
    return $plugin;
}

// Start the plugin
chatshop_run();

/**
 * Plugin activation hook
 */
register_activation_hook(__FILE__, array('ChatShop\ChatShop_Activator', 'activate'));

/**
 * Plugin deactivation hook
 */
register_deactivation_hook(__FILE__, array('ChatShop\ChatShop_Deactivator', 'deactivate'));

// NOTE: All global helper functions have been moved to includes/chatshop-global-functions.php
// to prevent redeclaration errors. This file now only contains the main plugin class.