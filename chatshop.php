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
     * @return ChatShop Main instance
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
    private function __construct()
    {
        $this->check_requirements();
        $this->init_premium_features();
        $this->load_dependencies();
        $this->init_loader();
        $this->init_logger();
        $this->set_locale();
        $this->init_component_system();
        $this->init_payment_system();
        $this->init_contact_system();
        $this->init_analytics_system(); // ANALYTICS INITIALIZATION
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->run();

        chatshop_log('ChatShop plugin initialized successfully', 'info');
    }

    /**
     * Prevent cloning
     *
     * @since 1.0.0
     */
    public function __clone()
    {
        _doing_it_wrong(__FUNCTION__, __('Cloning is forbidden.', 'chatshop'), '1.0.0');
    }

    /**
     * Prevent unserializing
     *
     * @since 1.0.0
     */
    public function __wakeup()
    {
        _doing_it_wrong(__FUNCTION__, __('Unserializing instances is forbidden.', 'chatshop'), '1.0.0');
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

        // Load payment gateway implementations
        $this->load_payment_gateways();
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
            'analytics' => isset($premium_options['analytics']) ? (bool) $premium_options['analytics'] : false, // ANALYTICS FEATURE
            'multiple_gateways' => isset($premium_options['multiple_gateways']) ? (bool) $premium_options['multiple_gateways'] : false,
        );
    }

    /**
     * Initialize component system
     *
     * @since 1.0.0
     */
    private function init_component_system()
    {
        $this->component_loader = new ChatShop_Component_Loader();

        // Register contact management component
        $this->component_loader->get_registry()->register_component(array(
            'id' => 'contact_manager',
            'name' => __('Contact Manager', 'chatshop'),
            'description' => __('Manage WhatsApp contacts with import/export capabilities', 'chatshop'),
            'path' => CHATSHOP_PLUGIN_DIR . 'components/whatsapp/',
            'main_file' => 'class-chatshop-contact-manager.php',
            'class_name' => 'ChatShop_Contact_Manager',
            'dependencies' => array(),
            'version' => '1.0.0',
            'enabled' => true
        ));

        // Register analytics component - ANALYTICS COMPONENT
        $this->component_loader->get_registry()->register_component(array(
            'id' => 'analytics',
            'name' => __('Analytics Dashboard', 'chatshop'),
            'description' => __('Premium analytics with WhatsApp-to-payment conversion tracking and revenue attribution', 'chatshop'),
            'path' => CHATSHOP_PLUGIN_DIR . 'components/analytics/',
            'main_file' => 'class-chatshop-analytics.php',
            'class_name' => 'ChatShop_Analytics',
            'dependencies' => array(),
            'version' => '1.0.0',
            'enabled' => true
        ));

        chatshop_log('Component system initialized successfully', 'info');
    }

    /**
     * Initialize payment system
     *
     * @since 1.0.0
     */
    private function init_payment_system()
    {
        // Check if payment classes are available
        if (!class_exists('ChatShop\ChatShop_Payment_Factory')) {
            chatshop_log('Payment Factory class not found, skipping payment system initialization', 'warning');
            return;
        }

        if (!class_exists('ChatShop\ChatShop_Payment_Manager')) {
            chatshop_log('Payment Manager class not found, skipping payment system initialization', 'warning');
            return;
        }

        try {
            // Initialize payment factory
            ChatShop_Payment_Factory::init();

            // Initialize payment manager
            $this->payment_manager = new ChatShop_Payment_Manager();

            chatshop_log('Payment system initialized successfully', 'info');
        } catch (\Exception $e) {
            chatshop_log('Payment system initialization failed: ' . $e->getMessage(), 'error');
            $this->payment_manager = null;
        }
    }

    /**
     * Initialize contact management system
     *
     * @since 1.0.0
     */
    private function init_contact_system()
    {
        // Check if contact manager class is available
        if (!class_exists('ChatShop\ChatShop_Contact_Manager')) {
            chatshop_log('Contact Manager class not found, skipping contact system initialization', 'warning');
            return;
        }

        try {
            // Load contact manager component
            $contact_component = $this->component_loader->get_component_instance('contact_manager');

            if ($contact_component) {
                $this->contact_manager = $contact_component;
                chatshop_log('Contact management system initialized successfully', 'info');
            } else {
                chatshop_log('Failed to load contact manager component', 'error');
            }
        } catch (\Exception $e) {
            chatshop_log('Contact system initialization failed: ' . $e->getMessage(), 'error');
            $this->contact_manager = null;
        }
    }

    /**
     * Initialize analytics system - ANALYTICS INITIALIZATION
     *
     * @since 1.0.0
     */
    private function init_analytics_system()
    {
        // Check if analytics class is available
        if (!class_exists('ChatShop\ChatShop_Analytics')) {
            chatshop_log('Analytics class not found, skipping analytics system initialization', 'warning');
            return;
        }

        try {
            // Load analytics component
            $analytics_component = $this->component_loader->get_component_instance('analytics');

            if ($analytics_component) {
                $this->analytics = $analytics_component;
                chatshop_log('Analytics system initialized successfully', 'info');
            } else {
                chatshop_log('Failed to load analytics component', 'error');
            }
        } catch (\Exception $e) {
            chatshop_log('Analytics system initialization failed: ' . $e->getMessage(), 'error');
            $this->analytics = null;
        }
    }

    /**
     * Load payment gateway implementations
     *
     * @since 1.0.0
     */
    private function load_payment_gateways()
    {
        $gateways_dir = CHATSHOP_PLUGIN_DIR . 'components/payment/gateways/';

        if (is_dir($gateways_dir)) {
            $gateway_dirs = glob($gateways_dir . '*', GLOB_ONLYDIR);

            foreach ($gateway_dirs as $gateway_dir) {
                $gateway_id = basename($gateway_dir);
                $gateway_files = array(
                    $gateway_dir . '/class-chatshop-' . $gateway_id . '-gateway.php',
                    $gateway_dir . '/class-' . $gateway_id . '-gateway.php'
                );

                foreach ($gateway_files as $gateway_file) {
                    if (file_exists($gateway_file)) {
                        require_once $gateway_file;
                        break;
                    }
                }
            }
        }
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
            $plugin_admin = new ChatShop_Admin($this->get_plugin_name(), $this->get_version());

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

/**
 * Global helper functions
 */

/**
 * Get ChatShop instance
 *
 * @since 1.0.0
 * @return ChatShop Main instance
 */
function chatshop()
{
    return ChatShop::instance();
}

/**
 * Log message using ChatShop logger
 *
 * @since 1.0.0
 * @param string $message Log message
 * @param string $level Log level
 * @param array  $context Additional context
 */
function chatshop_log($message, $level = 'info', $context = array())
{
    ChatShop_Logger::log($message, $level, $context);
}

/**
 * Check if user has premium access
 *
 * @since 1.0.0
 * @return bool Whether user has premium access
 */
function chatshop_is_premium()
{
    return chatshop()->is_premium_feature_available('analytics') ||
        chatshop()->is_premium_feature_available('advanced_analytics');
}

/**
 * Check if specific premium feature is available
 *
 * @since 1.0.0
 * @param string $feature Feature name
 * @return bool Whether feature is available
 */
function chatshop_is_premium_feature_available($feature)
{
    return chatshop()->is_premium_feature_available($feature);
}

/**
 * Get component instance
 *
 * @since 1.0.0
 * @param string $component_id Component ID
 * @return object|null Component instance
 */
function chatshop_get_component($component_id)
{
    $component_loader = chatshop()->get_component_loader();
    return $component_loader ? $component_loader->get_component_instance($component_id) : null;
}
