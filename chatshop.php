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
 * File: chatshop.php - FIXED VERSION
 * 
 * FIXES APPLIED:
 * - Fixed class redeclaration conflicts
 * - Added proper class existence checks
 * - Improved error handling and logging
 * - Enhanced component loading safety
 * 
 * @package ChatShop
 */

namespace ChatShop;

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// ================================
// PREVENT MULTIPLE PLUGIN LOADS
// ================================

// Check if ChatShop is already loaded to prevent conflicts
if (defined('CHATSHOP_VERSION')) {
    // Plugin already loaded, show admin notice
    add_action('admin_notices', function () {
        echo '<div class="notice notice-error"><p>';
        echo __('ChatShop: Plugin appears to be loaded multiple times. Please check for duplicate installations.', 'chatshop');
        echo '</p></div>';
    });
    return;
}

// ================================
// DEFINE PLUGIN CONSTANTS
// ================================

define('CHATSHOP_VERSION', '1.0.0');
define('CHATSHOP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CHATSHOP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CHATSHOP_PLUGIN_FILE', __FILE__);
define('CHATSHOP_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Define additional constants for better organization
define('CHATSHOP_INCLUDES_DIR', CHATSHOP_PLUGIN_DIR . 'includes/');
define('CHATSHOP_ADMIN_DIR', CHATSHOP_PLUGIN_DIR . 'admin/');
define('CHATSHOP_PUBLIC_DIR', CHATSHOP_PLUGIN_DIR . 'public/');
define('CHATSHOP_COMPONENTS_DIR', CHATSHOP_PLUGIN_DIR . 'components/');

// ================================
// LOAD CORE FILES WITH SAFETY CHECKS
// ================================

/**
 * Load global helper functions FIRST (required by other classes)
 */
$global_functions_path = CHATSHOP_INCLUDES_DIR . 'chatshop-global-functions.php';
if (file_exists($global_functions_path)) {
    require_once $global_functions_path;
} else {
    wp_die(__('ChatShop: Critical file missing - chatshop-global-functions.php', 'chatshop'));
}

/**
 * Load core classes with existence checks
 */
$core_classes = array(
    'class-chatshop-loader.php',
    'class-chatshop-logger.php',
    'class-chatshop-i18n.php',
    'class-chatshop-activator.php',
    'class-chatshop-deactivator.php',
    'class-chatshop-component-registry.php',
    'class-chatshop-component-loader.php',
    'class-chatshop-helper.php'
);

foreach ($core_classes as $class_file) {
    $file_path = CHATSHOP_INCLUDES_DIR . $class_file;
    if (file_exists($file_path)) {
        require_once $file_path;
    } else {
        wp_die(sprintf(__('ChatShop: Critical file missing - %s', 'chatshop'), $class_file));
    }
}

/**
 * Load abstract classes
 */
$abstract_classes = array(
    'abstract-chatshop-component.php',
    'abstract-chatshop-payment-gateway.php',
    'abstract-chatshop-api-client.php'
);

foreach ($abstract_classes as $abstract_file) {
    $file_path = CHATSHOP_INCLUDES_DIR . 'abstracts/' . $abstract_file;
    if (file_exists($file_path)) {
        require_once $file_path;
    } else {
        chatshop_log("Abstract class file missing: {$abstract_file}", 'warning');
    }
}

/**
 * Load payment component classes with safe checking
 */
$payment_files = array(
    'class-chatshop-payment-factory.php',
    'class-chatshop-payment-manager.php'
);

foreach ($payment_files as $payment_file) {
    $file_path = CHATSHOP_COMPONENTS_DIR . 'payment/' . $payment_file;
    if (file_exists($file_path)) {
        require_once $file_path;
    } else {
        chatshop_log("Payment component file missing: {$payment_file}", 'warning');
    }
}

/**
 * Load contact management component classes
 */
$contact_files = array(
    'class-chatshop-contact-manager.php',
    'class-chatshop-contact-import-export.php'
);

foreach ($contact_files as $contact_file) {
    $file_path = CHATSHOP_COMPONENTS_DIR . 'whatsapp/' . $contact_file;
    if (file_exists($file_path)) {
        require_once $file_path;
    } else {
        chatshop_log("Contact management file missing: {$contact_file}", 'info');
    }
}

/**
 * Load analytics component classes
 */
$analytics_files = array(
    'class-chatshop-analytics.php',
    'class-chatshop-analytics-export.php'
);

foreach ($analytics_files as $analytics_file) {
    $file_path = CHATSHOP_COMPONENTS_DIR . 'analytics/' . $analytics_file;
    if (file_exists($file_path)) {
        require_once $file_path;
    } else {
        chatshop_log("Analytics component file missing: {$analytics_file}", 'info');
    }
}

// ================================
// MAIN PLUGIN CLASS
// ================================

/**
 * Main ChatShop class - FIXED VERSION
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
     * Analytics instance
     *
     * @var ChatShop_Analytics
     * @since 1.0.0
     */
    private $analytics;

    /**
     * Premium features status
     *
     * @var array
     * @since 1.0.0
     */
    private $premium_features = array();

    /**
     * Plugin initialization status
     *
     * @var bool
     * @since 1.0.0
     */
    private $initialized = false;

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
        $this->init();
    }

    /**
     * Initialize the plugin with enhanced error handling
     *
     * @since 1.0.0
     */
    private function init()
    {
        // Prevent double initialization
        if ($this->initialized) {
            return;
        }

        try {
            // Check requirements first
            if (!$this->check_requirements()) {
                return;
            }

            // Initialize core components in proper order
            $this->init_logger();
            $this->init_loader();
            $this->init_component_loader();
            $this->set_locale();
            $this->init_premium_features();

            // Load components with error handling
            $this->load_components();

            // Define hooks
            $this->define_admin_hooks();
            $this->define_public_hooks();

            // Run the loader
            if ($this->loader) {
                $this->loader->run();
            }

            // Mark as initialized
            $this->initialized = true;

            // Plugin is now fully loaded
            do_action('chatshop_loaded', $this);

            chatshop_log('ChatShop plugin initialized successfully', 'info');
        } catch (Exception $e) {
            chatshop_log('ChatShop initialization failed: ' . $e->getMessage(), 'error');

            // Show admin notice on error
            add_action('admin_notices', function () use ($e) {
                echo '<div class="notice notice-error"><p>';
                echo sprintf(__('ChatShop initialization failed: %s', 'chatshop'), esc_html($e->getMessage()));
                echo '</p></div>';
            });
        }
    }

    /**
     * Check plugin requirements with detailed validation
     *
     * @since 1.0.0
     * @return bool True if requirements met, false otherwise
     */
    private function check_requirements()
    {
        $requirements_met = true;

        // Check WordPress version
        if (version_compare(get_bloginfo('version'), '6.3', '<')) {
            add_action('admin_notices', array($this, 'wordpress_version_notice'));
            $requirements_met = false;
        }

        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            add_action('admin_notices', array($this, 'php_version_notice'));
            $requirements_met = false;
        }

        // Check if required PHP extensions are loaded
        $required_extensions = array('json', 'curl', 'mbstring');
        foreach ($required_extensions as $extension) {
            if (!extension_loaded($extension)) {
                add_action('admin_notices', function () use ($extension) {
                    echo '<div class="notice notice-error"><p>';
                    echo sprintf(__('ChatShop requires PHP %s extension to be installed.', 'chatshop'), $extension);
                    echo '</p></div>';
                });
                $requirements_met = false;
            }
        }

        // Check if WooCommerce is active (recommended, not required)
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_notice'));
            // Don't fail requirements, just show notice
        }

        // Check write permissions for logs
        $log_dir = WP_CONTENT_DIR . '/chatshop-logs/';
        if (!is_dir($log_dir) && !wp_mkdir_p($log_dir)) {
            chatshop_log('Cannot create log directory: ' . $log_dir, 'warning');
        }

        return $requirements_met;
    }

    /**
     * Initialize logger with error handling
     *
     * @since 1.0.0
     */
    private function init_logger()
    {
        if (class_exists('ChatShop\\ChatShop_Logger')) {
            ChatShop_Logger::init();
        } else {
            error_log('ChatShop: Logger class not found');
        }
    }

    /**
     * Initialize the loader with validation
     *
     * @since 1.0.0
     */
    private function init_loader()
    {
        if (class_exists('ChatShop\\ChatShop_Loader')) {
            $this->loader = new ChatShop_Loader();
        } else {
            throw new Exception('ChatShop_Loader class not found');
        }
    }

    /**
     * Initialize component loader with validation
     *
     * @since 1.0.0
     */
    private function init_component_loader()
    {
        if (class_exists('ChatShop\\ChatShop_Component_Loader')) {
            $this->component_loader = new ChatShop_Component_Loader();
        } else {
            throw new Exception('ChatShop_Component_Loader class not found');
        }
    }

    /**
     * Set the plugin locale
     *
     * @since 1.0.0
     */
    private function set_locale()
    {
        if (class_exists('ChatShop\\ChatShop_i18n')) {
            $plugin_i18n = new ChatShop_i18n();
            $this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
        }
    }

    /**
     * Initialize premium features
     *
     * @since 1.0.0
     */
    private function init_premium_features()
    {
        $premium_options = get_option('chatshop_premium_features', array());

        $this->premium_features = array(
            'unlimited_contacts' => isset($premium_options['unlimited_contacts']) ? (bool) $premium_options['unlimited_contacts'] : false,
            'contact_import_export' => isset($premium_options['contact_import_export']) ? (bool) $premium_options['contact_import_export'] : false,
            'bulk_messaging' => isset($premium_options['bulk_messaging']) ? (bool) $premium_options['bulk_messaging'] : false,
            'advanced_analytics' => isset($premium_options['advanced_analytics']) ? (bool) $premium_options['advanced_analytics'] : false,
            'analytics' => isset($premium_options['analytics']) ? (bool) $premium_options['analytics'] : true, // Default enabled for development
            'whatsapp_business_api' => isset($premium_options['whatsapp_business_api']) ? (bool) $premium_options['whatsapp_business_api'] : false,
            'campaign_automation' => isset($premium_options['campaign_automation']) ? (bool) $premium_options['campaign_automation'] : false,
            'custom_reports' => isset($premium_options['custom_reports']) ? (bool) $premium_options['custom_reports'] : false
        );
    }

    /**
     * Load components with enhanced error handling
     *
     * @since 1.0.0
     */
    private function load_components()
    {
        if (!$this->component_loader) {
            chatshop_log('Component loader not initialized', 'error');
            return;
        }

        try {
            // Load all enabled components
            $this->component_loader->load_components();

            // Get component instances for easy access
            $this->payment_manager = $this->component_loader->get_component_instance('payment');
            $this->contact_manager = $this->component_loader->get_component_instance('contact_manager');
            $this->analytics = $this->component_loader->get_component_instance('analytics');

            // Validate critical components
            if (!$this->payment_manager) {
                chatshop_log('Payment manager component failed to load', 'warning');
            }

            // Log analytics component status specifically
            if ($this->analytics) {
                chatshop_log('Analytics component loaded successfully', 'info');
            } else {
                chatshop_log('Analytics component not loaded - checking premium status', 'info');

                // Check why analytics didn't load
                $is_premium = chatshop_is_premium();
                $analytics_enabled = chatshop_get_option('analytics', 'enabled', true);

                chatshop_log('Premium status: ' . ($is_premium ? 'true' : 'false'), 'info');
                chatshop_log('Analytics enabled: ' . ($analytics_enabled ? 'true' : 'false'), 'info');
            }

            chatshop_log('Components loaded successfully', 'info');
        } catch (Exception $e) {
            chatshop_log('Component loading failed: ' . $e->getMessage(), 'error');
        }
    }

    /**
     * Define admin hooks with proper loading
     *
     * @since 1.0.0
     */
    private function define_admin_hooks()
    {
        if (!is_admin()) {
            return;
        }

        // Load admin class
        $admin_path = CHATSHOP_ADMIN_DIR . 'class-chatshop-admin.php';
        if (file_exists($admin_path)) {
            require_once $admin_path;

            if (class_exists('ChatShop\\ChatShop_Admin')) {
                $this->admin = new ChatShop_Admin();
                $this->loader->add_action('admin_enqueue_scripts', $this->admin, 'enqueue_styles');
                $this->loader->add_action('admin_enqueue_scripts', $this->admin, 'enqueue_scripts');
            }
        }
    }

    /**
     * Define public hooks with proper loading
     *
     * @since 1.0.0
     */
    private function define_public_hooks()
    {
        // Load public class
        $public_path = CHATSHOP_PUBLIC_DIR . 'class-chatshop-public.php';
        if (file_exists($public_path)) {
            require_once $public_path;

            if (class_exists('ChatShop\\ChatShop_Public')) {
                $this->public = new ChatShop_Public();
                $this->loader->add_action('wp_enqueue_scripts', $this->public, 'enqueue_styles');
                $this->loader->add_action('wp_enqueue_scripts', $this->public, 'enqueue_scripts');
            }
        }
    }

    /**
     * Get component loader instance
     *
     * @since 1.0.0
     * @return ChatShop_Component_Loader|null Component loader instance
     */
    public function get_component_loader()
    {
        return $this->component_loader;
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
     * Get analytics instance
     *
     * @since 1.0.0
     * @return ChatShop_Analytics|null Analytics instance
     */
    public function get_analytics()
    {
        // Try to get from component loader if not already loaded
        if (!$this->analytics && $this->component_loader) {
            $this->analytics = $this->component_loader->get_component_instance('analytics');
        }

        return $this->analytics;
    }

    /**
     * Check if premium feature is enabled
     *
     * @since 1.0.0
     * @param string $feature Feature name
     * @return bool Feature status
     */
    public function is_premium_feature_enabled($feature)
    {
        return isset($this->premium_features[$feature]) ? (bool) $this->premium_features[$feature] : false;
    }

    /**
     * Get all premium features
     *
     * @since 1.0.0
     * @return array Premium features array
     */
    public function get_premium_features()
    {
        return $this->premium_features;
    }

    /**
     * Check if plugin is fully initialized
     *
     * @since 1.0.0
     * @return bool Initialization status
     */
    public function is_initialized()
    {
        return $this->initialized;
    }

    /**
     * WordPress version notice
     *
     * @since 1.0.0
     */
    public function wordpress_version_notice()
    {
        echo '<div class="notice notice-error"><p>';
        echo __('ChatShop requires WordPress 6.3 or higher. Please update WordPress.', 'chatshop');
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
        echo sprintf(__('ChatShop requires PHP 7.4 or higher. Your current version is %s.', 'chatshop'), PHP_VERSION);
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
        echo __('ChatShop works best with WooCommerce installed. Some features may be limited without it.', 'chatshop');
        echo '</p></div>';
    }

    /**
     * Prevent cloning
     *
     * @since 1.0.0
     */
    private function __clone()
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
        _doing_it_wrong(__FUNCTION__, __('Unserializing instances of this class is forbidden.', 'chatshop'), '1.0.0');
    }
}

// ================================
// PLUGIN EXECUTION
// ================================

/**
 * Begins execution of the plugin with error handling
 *
 * @since 1.0.0
 */
function chatshop_run()
{
    try {
        $plugin = ChatShop::instance();
        return $plugin;
    } catch (Exception $e) {
        // Log the error
        error_log('ChatShop startup failed: ' . $e->getMessage());

        // Show admin notice
        add_action('admin_notices', function () use ($e) {
            echo '<div class="notice notice-error"><p>';
            echo sprintf(__('ChatShop failed to start: %s', 'chatshop'), esc_html($e->getMessage()));
            echo '</p></div>';
        });

        return null;
    }
}

// Start the plugin
chatshop_run();

/**
 * Plugin activation hook with error handling
 */
register_activation_hook(__FILE__, function () {
    try {
        if (class_exists('ChatShop\\ChatShop_Activator')) {
            ChatShop_Activator::activate();
        }
    } catch (Exception $e) {
        wp_die(sprintf(__('ChatShop activation failed: %s', 'chatshop'), $e->getMessage()));
    }
});

/**
 * Plugin deactivation hook with error handling
 */
register_deactivation_hook(__FILE__, function () {
    try {
        if (class_exists('ChatShop\\ChatShop_Deactivator')) {
            ChatShop_Deactivator::deactivate();
        }
    } catch (Exception $e) {
        error_log('ChatShop deactivation error: ' . $e->getMessage());
    }
});

// ================================
// FINAL SAFETY CHECKS
// ================================

// Register shutdown function to catch any remaining errors
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && strpos($error['file'], 'chatshop') !== false) {
        error_log('ChatShop shutdown error: ' . print_r($error, true));
    }
});
