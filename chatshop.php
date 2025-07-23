<?php

/**
 * Plugin Name:       ChatShop
 * Plugin URI:        https://modewebhost.com.ng
 * Description:       Social commerce plugin for WhatsApp and payments with contact management
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
 * Load contact management component classes - NEW CONTACT SYSTEM
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
     * Contact manager instance - NEW
     *
     * @var ChatShop_Contact_Manager
     * @since 1.0.0
     */
    private $contact_manager;

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
     * ChatShop Constructor - UPDATED TO MATCH EXISTING PATTERN
     *
     * @since 1.0.0
     */
    private function __construct()
    {
        $this->load_dependencies();
        $this->init_loader();
        $this->init_logger();
        $this->set_locale();
        $this->check_requirements();
        $this->init_premium_features();
        $this->init_component_system(); // NEW - Initialize component system
        $this->init_payment_system();
        $this->init_contact_system(); // NEW - Initialize contact system
        $this->register_payment_gateways();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->init_hooks();
    }

    /**
     * Prevent cloning
     *
     * @since 1.0.0
     */
    public function __clone()
    {
        _doing_it_wrong(__FUNCTION__, esc_html__('Cloning is forbidden.', 'chatshop'), '1.0.0');
    }

    /**
     * Prevent unserializing
     *
     * @since 1.0.0
     */
    public function __wakeup()
    {
        _doing_it_wrong(__FUNCTION__, esc_html__('Unserializing instances of this class is forbidden.', 'chatshop'), '1.0.0');
    }

    /**
     * Load the required dependencies for this plugin - EXISTING METHOD
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
     * Initialize the loader - EXISTING METHOD
     *
     * @since 1.0.0
     */
    private function init_loader()
    {
        $this->loader = new ChatShop_Loader();
    }

    /**
     * Initialize logger - EXISTING METHOD
     *
     * @since 1.0.0
     */
    private function init_logger()
    {
        ChatShop_Logger::init();
    }

    /**
     * Set the plugin locale - EXISTING METHOD
     *
     * @since 1.0.0
     */
    private function set_locale()
    {
        $plugin_i18n = new ChatShop_i18n();
        $this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
    }

    /**
     * Check plugin requirements - EXISTING METHOD
     *
     * @since 1.0.0
     */
    private function check_requirements()
    {
        // Check WordPress version
        if (version_compare(get_bloginfo('version'), '5.0', '<')) {
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
     * Initialize premium features - EXISTING METHOD WITH CONTACT FEATURES
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
            'multiple_gateways' => isset($premium_options['multiple_gateways']) ? (bool) $premium_options['multiple_gateways'] : false,
        );
    }

    /**
     * Initialize component system - NEW METHOD
     *
     * @since 1.0.0
     */
    private function init_component_system()
    {
        $this->component_loader = new ChatShop_Component_Loader();

        // Register contact management component - NEW
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

        chatshop_log('Component system initialized successfully', 'info');
    }

    /**
     * Initialize payment system - EXISTING METHOD
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
     * Initialize contact management system - NEW METHOD
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
            $contact_component = $this->component_loader->load_component('contact_manager');

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
     * Load payment gateway implementations - EXISTING METHOD
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
     * Register payment gateways - EXISTING METHOD
     *
     * @since 1.0.0
     */
    private function register_payment_gateways()
    {
        if (!$this->payment_manager) {
            return;
        }

        // Register Paystack gateway
        $this->register_paystack_gateway();

        // Register additional gateways if premium feature is enabled
        if ($this->premium_features['multiple_gateways']) {
            $this->register_additional_gateways();
        }

        // Hook for custom gateway registration
        do_action('chatshop_register_payment_gateways', $this->payment_manager);
    }

    /**
     * Register Paystack gateway - EXISTING METHOD
     *
     * @since 1.0.0
     */
    private function register_paystack_gateway()
    {
        if (!class_exists('ChatShop\ChatShop_Paystack_Gateway')) {
            return;
        }

        try {
            $paystack_gateway = new ChatShop_Paystack_Gateway();

            // Check if gateway is properly configured
            if ($this->is_gateway_configured('paystack')) {
                $this->payment_manager->register_gateway($paystack_gateway);
                $this->registered_gateways['paystack'] = $paystack_gateway;

                chatshop_log('Paystack gateway registered successfully', 'info');
            } else {
                chatshop_log('Paystack gateway not registered - configuration incomplete', 'warning');
            }
        } catch (\Exception $e) {
            chatshop_log('Failed to register Paystack gateway: ' . $e->getMessage(), 'error');
        }
    }

    /**
     * Register additional premium gateways - EXISTING METHOD
     *
     * @since 1.0.0
     */
    private function register_additional_gateways()
    {
        // PayPal gateway
        if (class_exists('ChatShop\ChatShop_PayPal_Gateway')) {
            try {
                $paypal_gateway = new ChatShop_PayPal_Gateway();
                if ($this->is_gateway_configured('paypal')) {
                    $this->payment_manager->register_gateway($paypal_gateway);
                    $this->registered_gateways['paypal'] = $paypal_gateway;
                    chatshop_log('PayPal gateway registered successfully', 'info');
                }
            } catch (\Exception $e) {
                chatshop_log('Failed to register PayPal gateway: ' . $e->getMessage(), 'error');
            }
        }

        // Flutterwave gateway
        if (class_exists('ChatShop\ChatShop_Flutterwave_Gateway')) {
            try {
                $flutterwave_gateway = new ChatShop_Flutterwave_Gateway();
                if ($this->is_gateway_configured('flutterwave')) {
                    $this->payment_manager->register_gateway($flutterwave_gateway);
                    $this->registered_gateways['flutterwave'] = $flutterwave_gateway;
                    chatshop_log('Flutterwave gateway registered successfully', 'info');
                }
            } catch (\Exception $e) {
                chatshop_log('Failed to register Flutterwave gateway: ' . $e->getMessage(), 'error');
            }
        }
    }

    /**
     * Define admin hooks - EXISTING METHOD WITH CONTACT ADDITIONS
     *
     * @since 1.0.0
     */
    private function define_admin_hooks()
    {
        if (is_admin()) {
            if (!$this->admin && class_exists('ChatShop\ChatShop_Admin')) {
                $this->admin = new ChatShop_Admin();
            }

            if ($this->admin) {
                $this->loader->add_action('admin_enqueue_scripts', $this->admin, 'enqueue_styles');
                $this->loader->add_action('admin_enqueue_scripts', $this->admin, 'enqueue_scripts');
            }

            // Contact management AJAX hooks - NEW
            if ($this->contact_manager) {
                $this->loader->add_action('wp_ajax_chatshop_add_contact', $this->contact_manager, 'ajax_add_contact');
                $this->loader->add_action('wp_ajax_chatshop_update_contact', $this->contact_manager, 'ajax_update_contact');
                $this->loader->add_action('wp_ajax_chatshop_delete_contact', $this->contact_manager, 'ajax_delete_contact');
                $this->loader->add_action('wp_ajax_chatshop_bulk_delete_contacts', $this->contact_manager, 'ajax_bulk_delete_contacts');
                $this->loader->add_action('wp_ajax_chatshop_import_contacts', $this->contact_manager, 'ajax_import_contacts');
                $this->loader->add_action('wp_ajax_chatshop_export_contacts', $this->contact_manager, 'ajax_export_contacts');
                $this->loader->add_action('wp_ajax_chatshop_get_contact_stats', $this->contact_manager, 'ajax_get_contact_stats');
            }
        }
    }

    /**
     * Define public hooks - EXISTING METHOD
     *
     * @since 1.0.0
     */
    private function define_public_hooks()
    {
        if (!$this->public && class_exists('ChatShop\ChatShop_Public')) {
            $this->public = new ChatShop_Public();
        }

        if ($this->public) {
            $this->loader->add_action('wp_enqueue_scripts', $this->public, 'enqueue_styles');
            $this->loader->add_action('wp_enqueue_scripts', $this->public, 'enqueue_scripts');
        }
    }

    /**
     * Initialize hooks - EXISTING METHOD
     *
     * @since 1.0.0
     */
    private function init_hooks()
    {
        // Plugin activation/deactivation hooks
        register_activation_hook(__FILE__, array('ChatShop\ChatShop_Activator', 'activate'));
        register_deactivation_hook(__FILE__, array('ChatShop\ChatShop_Deactivator', 'deactivate'));
    }

    /**
     * Check if gateway is configured - EXISTING METHOD
     *
     * @since 1.0.0
     * @param string $gateway_id Gateway identifier
     * @return bool Configuration status
     */
    private function is_gateway_configured($gateway_id)
    {
        $options = get_option("chatshop_{$gateway_id}_options", array());

        switch ($gateway_id) {
            case 'paystack':
                return !empty($options['enabled']) &&
                    (!empty($options['test_secret_key']) || !empty($options['live_secret_key']));
            case 'paypal':
                return !empty($options['enabled']) &&
                    (!empty($options['client_id']) && !empty($options['client_secret']));
            case 'flutterwave':
                return !empty($options['enabled']) &&
                    (!empty($options['public_key']) && !empty($options['secret_key']));
            default:
                return false;
        }
    }

    /**
     * Check if premium feature is available - NEW METHOD
     *
     * @since 1.0.0
     * @param string $feature Feature name
     * @return bool Feature availability
     */
    public function is_premium_feature_available($feature)
    {
        return isset($this->premium_features[$feature]) ? (bool) $this->premium_features[$feature] : false;
    }

    /**
     * Run the loader to execute all of the hooks with WordPress - EXISTING METHOD
     *
     * @since 1.0.0
     */
    public function run()
    {
        $this->loader->run();
    }

    /**
     * WordPress version notice - EXISTING METHOD
     *
     * @since 1.0.0
     */
    public function wordpress_version_notice()
    {
        echo '<div class="notice notice-error"><p>';
        echo sprintf(__('ChatShop requires WordPress 5.0 or higher. Please update WordPress to use this plugin.', 'chatshop'));
        echo '</p></div>';
    }

    /**
     * PHP version notice - EXISTING METHOD
     *
     * @since 1.0.0
     */
    public function php_version_notice()
    {
        echo '<div class="notice notice-error"><p>';
        echo sprintf(__('ChatShop requires PHP 7.4 or higher. Your current PHP version is %s.', 'chatshop'), PHP_VERSION);
        echo '</p></div>';
    }

    /**
     * WooCommerce notice - EXISTING METHOD
     *
     * @since 1.0.0
     */
    public function woocommerce_notice()
    {
        echo '<div class="notice notice-warning"><p>';
        echo __('ChatShop works best with WooCommerce. Please install and activate WooCommerce for the full experience.', 'chatshop');
        echo '</p></div>';
    }

    /**
     * Get payment manager instance - EXISTING METHOD
     *
     * @since 1.0.0
     * @return ChatShop_Payment_Manager|null
     */
    public function get_payment_manager()
    {
        return $this->payment_manager;
    }

    /**
     * Get contact manager instance - NEW METHOD
     *
     * @since 1.0.0
     * @return ChatShop_Contact_Manager|null
     */
    public function get_contact_manager()
    {
        return $this->contact_manager;
    }

    /**
     * Get component loader instance - NEW METHOD
     *
     * @since 1.0.0
     * @return ChatShop_Component_Loader
     */
    public function get_component_loader()
    {
        return $this->component_loader;
    }

    /**
     * Get registered gateways - EXISTING METHOD
     *
     * @since 1.0.0
     * @return array
     */
    public function get_registered_gateways()
    {
        return $this->registered_gateways;
    }

    /**
     * Get premium features status - NEW METHOD
     *
     * @since 1.0.0
     * @return array
     */
    public function get_premium_features()
    {
        return $this->premium_features;
    }
}

/**
 * Returns the main instance of ChatShop - EXISTING FUNCTION
 *
 * @since 1.0.0
 * @return ChatShop
 */
function chatshop()
{
    return ChatShop::instance();
}

/**
 * Helper function to check premium feature availability - NEW FUNCTION
 *
 * @since 1.0.0
 * @param string $feature Feature name
 * @return bool Feature availability
 */
function chatshop_is_premium_feature_available($feature)
{
    return chatshop()->is_premium_feature_available($feature);
}

/**
 * Helper function to get component instance - NEW FUNCTION
 *
 * @since 1.0.0
 * @param string $component_id Component ID
 * @return mixed|null Component instance or null
 */
function chatshop_get_component($component_id)
{
    $component_loader = chatshop()->get_component_loader();
    return $component_loader ? $component_loader->get_component_instance($component_id) : null;
}

/**
 * Helper function to get option with namespace - NEW FUNCTION
 *
 * @since 1.0.0
 * @param string $group Option group
 * @param string $key Option key
 * @param mixed  $default Default value
 * @return mixed Option value
 */
function chatshop_get_option($group, $key = '', $default = null)
{
    $option_name = "chatshop_{$group}_options";
    $options = get_option($option_name, array());

    if (empty($key)) {
        return $options;
    }

    return isset($options[$key]) ? $options[$key] : $default;
}

/**
 * Helper function to update option with namespace - NEW FUNCTION
 *
 * @since 1.0.0
 * @param string $group Option group
 * @param string $key Option key
 * @param mixed  $value Option value
 * @return bool Update result
 */
function chatshop_update_option($group, $key, $value)
{
    $option_name = "chatshop_{$group}_options";

    if (empty($key)) {
        return update_option($option_name, $value);
    }

    $options = get_option($option_name, array());
    $options[$key] = $value;

    return update_option($option_name, $options);
}

// Initialize the plugin
chatshop();
