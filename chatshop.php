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
 * CRITICAL FIXES:
 * - Fixed abstract class loading paths (includes/abstracts/ instead of components/payment/abstracts/)
 * - Corrected file loading order to prevent class not found errors
 * - Added proper error handling and fallback paths
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

// Check if this file has already been loaded
if (defined('CHATSHOP_MAIN_LOADED')) {
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
// GLOBAL INITIALIZATION STATE TRACKING
// ================================

global $chatshop_initializing, $chatshop_initialization_complete;
$chatshop_initializing = false;
$chatshop_initialization_complete = false;

// ================================
// LOAD CORE FILES WITH SAFETY CHECKS
// ================================

/**
 * Ensure required directories exist
 */
$required_dirs = array(
    CHATSHOP_INCLUDES_DIR,
    CHATSHOP_INCLUDES_DIR . 'abstracts/',
    CHATSHOP_ADMIN_DIR,
    CHATSHOP_PUBLIC_DIR,
    CHATSHOP_COMPONENTS_DIR,
    CHATSHOP_COMPONENTS_DIR . 'payment/',
    CHATSHOP_COMPONENTS_DIR . 'payment/gateways/',
    CHATSHOP_COMPONENTS_DIR . 'payment/gateways/paystack/',
    CHATSHOP_COMPONENTS_DIR . 'whatsapp/',
    CHATSHOP_COMPONENTS_DIR . 'analytics/'
);

foreach ($required_dirs as $dir) {
    if (!is_dir($dir)) {
        wp_mkdir_p($dir);
    }
}

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
        if (function_exists('ChatShop\\chatshop_log')) {
            chatshop_log("Core class file missing: {$class_file}", 'error');
        }
    }
}

// Load missing core classes bundle as fallback
$missing_classes_file = CHATSHOP_INCLUDES_DIR . 'missing-core-classes.php';
if (file_exists($missing_classes_file)) {
    require_once $missing_classes_file;
}

/**
 * CRITICAL FIX: Load abstract classes from includes/abstracts/ directory
 * These MUST be loaded before any concrete implementations
 */
$abstract_classes = array(
    'abstract-chatshop-component.php',
    'abstract-chatshop-api-client.php',
    'abstract-chatshop-payment-gateway.php'
);

foreach ($abstract_classes as $abstract_file) {
    $file_path = CHATSHOP_INCLUDES_DIR . 'abstracts/' . $abstract_file;
    if (file_exists($file_path)) {
        require_once $file_path;
    } else {
        // Try alternate location in components/payment/abstracts/ as fallback
        $alt_path = CHATSHOP_COMPONENTS_DIR . 'payment/abstracts/' . $abstract_file;
        if (file_exists($alt_path)) {
            require_once $alt_path;
        } else {
            if (function_exists('ChatShop\\chatshop_log')) {
                chatshop_log("Abstract class file missing: {$abstract_file}", 'error');
            }
        }
    }
}

/**
 * Load admin classes
 */
$admin_classes = array(
    'class-chatshop-admin.php',
    'class-chatshop-admin-menu.php',
    'class-chatshop-settings.php'
);

foreach ($admin_classes as $admin_file) {
    $file_path = CHATSHOP_ADMIN_DIR . $admin_file;
    if (file_exists($file_path)) {
        require_once $file_path;
    } else {
        if (function_exists('ChatShop\\chatshop_log')) {
            chatshop_log("Admin class file missing: {$admin_file}", 'info');
        }
    }
}

/**
 * Load public classes
 */
$public_classes = array(
    'class-chatshop-public.php'
);

foreach ($public_classes as $public_file) {
    $file_path = CHATSHOP_PUBLIC_DIR . $public_file;
    if (file_exists($file_path)) {
        require_once $file_path;
    } else {
        if (function_exists('ChatShop\\chatshop_log')) {
            chatshop_log("Public class file missing: {$public_file}", 'info');
        }
    }
}

/**
 * Load payment component classes
 * NOTE: Abstract classes have already been loaded from includes/abstracts/
 */
$payment_files = array(
    // Load concrete implementation classes
    'class-chatshop-payment-manager.php',
    'class-chatshop-payment-factory.php',
    'class-chatshop-payment-link-generator.php',
    // Load gateway implementations (which depend on abstracts)
    'gateways/paystack/class-chatshop-paystack-api.php',
    'gateways/paystack/class-chatshop-paystack-gateway.php',
    'gateways/paystack/class-chatshop-paystack-webhook.php'
);

foreach ($payment_files as $payment_file) {
    $file_path = CHATSHOP_COMPONENTS_DIR . 'payment/' . $payment_file;
    if (file_exists($file_path)) {
        require_once $file_path;
    } else {
        if (function_exists('ChatShop\\chatshop_log')) {
            chatshop_log("Payment component file missing: {$payment_file}", 'info');
        }
    }
}

/**
 * Load contact management component classes
 */
$contact_files = array(
    'class-chatshop-contact-manager.php',
    'class-chatshop-contact-handler.php'
);

foreach ($contact_files as $contact_file) {
    $file_path = CHATSHOP_COMPONENTS_DIR . 'whatsapp/' . $contact_file;
    if (file_exists($file_path)) {
        require_once $file_path;
    } else {
        if (function_exists('ChatShop\\chatshop_log')) {
            chatshop_log("Contact management file missing: {$contact_file}", 'info');
        }
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
        if (function_exists('ChatShop\\chatshop_log')) {
            chatshop_log("Analytics file missing: {$analytics_file}", 'info');
        }
    }
}

// ================================
// MAIN PLUGIN CLASS
// ================================

/**
 * Main ChatShop Plugin Class - FIXED VERSION
 *
 * @since 1.0.0
 */
class ChatShop
{
    /**
     * Single instance of the class
     */
    private static $instance = null;

    /**
     * Plugin components registry
     */
    private $component_registry;

    /**
     * Plugin component loader
     */
    private $component_loader;

    /**
     * Plugin loader for hooks
     */
    private $loader;

    /**
     * Plugin version
     */
    private $version;

    /**
     * Admin class instance
     */
    private $admin;

    /**
     * Public class instance
     */
    private $public;

    /**
     * Components instances
     */
    private $components = array();

    /**
     * Premium features
     */
    private $premium_features = array();

    /**
     * Initialization status
     */
    private $initialized = false;

    /**
     * Protected constructor - singleton pattern
     */
    protected function __construct()
    {
        $this->version = CHATSHOP_VERSION;
        $this->initialize();
    }

    /**
     * Get singleton instance - RECURSION SAFE
     * This is now called by the chatshop() function in chatshop-global-functions.php
     */
    public static function get_instance()
    {
        global $chatshop_initializing;

        // Prevent recursion during initialization
        if ($chatshop_initializing) {
            return null;
        }

        if (null === self::$instance) {
            $chatshop_initializing = true;
            self::$instance = new self();
            $chatshop_initializing = false;
        }

        return self::$instance;
    }

    /**
     * Alias for get_instance() to maintain compatibility with global function
     * The chatshop() function in chatshop-global-functions.php calls this method
     */
    public static function instance()
    {
        return self::get_instance();
    }

    /**
     * Initialize the plugin
     */
    private function initialize()
    {
        if ($this->initialized) {
            return;
        }

        try {
            $this->initialized = true;

            // Initialize loader
            $this->loader = new ChatShop_Loader();

            // Initialize components infrastructure
            $this->init_components_infrastructure();

            // Set plugin locale
            $this->set_locale();

            // Initialize premium features
            $this->init_premium_features();

            // Define admin hooks
            if (is_admin()) {
                $this->define_admin_hooks();
            }

            // Define public hooks
            $this->define_public_hooks();

            // Schedule cron events
            $this->schedule_events();

            // Initialize REST API endpoints
            $this->init_rest_api();

            // Load components
            add_action('init', array($this, 'load_components'), 5);

            if (function_exists('ChatShop\\chatshop_log')) {
                chatshop_log('ChatShop plugin initialized successfully', 'info');
            }
        } catch (Exception $e) {
            if (function_exists('ChatShop\\chatshop_log')) {
                chatshop_log('Plugin initialization failed: ' . $e->getMessage(), 'error');
            }
        }
    }

    /**
     * Initialize components infrastructure
     */
    private function init_components_infrastructure()
    {
        try {
            // Initialize component registry
            if (class_exists('ChatShop\\ChatShop_Component_Registry')) {
                $this->component_registry = ChatShop_Component_Registry::get_instance();
            }

            // Initialize component loader
            if (class_exists('ChatShop\\ChatShop_Component_Loader')) {
                $this->component_loader = new ChatShop_Component_Loader($this->component_registry);
            }

            // Register default components
            $this->register_default_components();
        } catch (Exception $e) {
            if (function_exists('ChatShop\\chatshop_log')) {
                chatshop_log('Components infrastructure initialization failed: ' . $e->getMessage(), 'error');
            }
        }
    }

    /**
     * Register default components
     */
    private function register_default_components()
    {
        if (!$this->component_registry) {
            return;
        }

        $default_components = array(
            // Payment components
            array(
                'id' => 'payment_manager',
                'path' => CHATSHOP_COMPONENTS_DIR . 'payment/',
                'main_file' => 'class-chatshop-payment-manager.php',
                'class_name' => 'ChatShop\\ChatShop_Payment_Manager',
                'version' => '1.0.0',
                'dependencies' => array(),
                'autoload' => true,
                'priority' => 10
            ),
            // WhatsApp components
            array(
                'id' => 'contact_manager',
                'path' => CHATSHOP_COMPONENTS_DIR . 'whatsapp/',
                'main_file' => 'class-chatshop-contact-manager.php',
                'class_name' => 'ChatShop\\ChatShop_Contact_Manager',
                'version' => '1.0.0',
                'dependencies' => array(),
                'autoload' => true,
                'priority' => 20
            ),
            // Analytics components
            array(
                'id' => 'analytics',
                'path' => CHATSHOP_COMPONENTS_DIR . 'analytics/',
                'main_file' => 'class-chatshop-analytics.php',
                'class_name' => 'ChatShop\\ChatShop_Analytics',
                'version' => '1.0.0',
                'dependencies' => array(),
                'autoload' => true,
                'priority' => 30
            )
        );

        foreach ($default_components as $component) {
            $this->component_registry->register_component($component);
        }
    }

    /**
     * Set plugin locale
     */
    private function set_locale()
    {
        if (class_exists('ChatShop\\ChatShop_I18n')) {
            $plugin_i18n = new ChatShop_I18n();
            if ($this->loader) {
                $this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
            }
        }
    }

    /**
     * Initialize premium features
     */
    private function init_premium_features()
    {
        $this->premium_features = array(
            'advanced_analytics' => false,
            'multiple_gateways' => false,
            'custom_branding' => false,
            'priority_support' => false
        );

        // Check license status here
        if (function_exists('ChatShop\\chatshop_get_license_info')) {
            $license_info = chatshop_get_license_info();
            if ($license_info['status'] === 'active') {
                $this->premium_features = array_map('__return_true', $this->premium_features);
            }
        }
    }

    /**
     * Load plugin components
     */
    public function load_components()
    {
        if (!$this->component_loader) {
            if (function_exists('ChatShop\\chatshop_log')) {
                chatshop_log('Cannot load components: component loader not available', 'error');
            }
            return;
        }

        try {
            $loaded = $this->component_loader->load_all_components();
            if ($loaded) {
                if (function_exists('ChatShop\\chatshop_log')) {
                    chatshop_log('All components loaded successfully', 'info');
                }
            } else {
                if (function_exists('ChatShop\\chatshop_log')) {
                    chatshop_log('Some components failed to load', 'warning');
                }
            }
        } catch (Exception $e) {
            if (function_exists('ChatShop\\chatshop_log')) {
                chatshop_log('Component loading failed: ' . $e->getMessage(), 'error');
            }
        }
    }

    /**
     * Define admin hooks - RECURSION SAFE
     */
    private function define_admin_hooks()
    {
        // Prevent recursion by checking if already done
        if ($this->admin) {
            return;
        }

        if (class_exists('ChatShop\\ChatShop_Admin')) {
            try {
                $this->admin = new ChatShop_Admin();
                if (function_exists('ChatShop\\chatshop_log')) {
                    chatshop_log('Admin hooks defined', 'info');
                }
            } catch (Exception $e) {
                if (function_exists('ChatShop\\chatshop_log')) {
                    chatshop_log('Admin initialization failed: ' . $e->getMessage(), 'error');
                }
            }
        }
    }

    /**
     * Define public hooks
     */
    private function define_public_hooks()
    {
        if ($this->public) {
            return;
        }

        if (class_exists('ChatShop\\ChatShop_Public')) {
            try {
                $this->public = new ChatShop_Public();
            } catch (Exception $e) {
                if (function_exists('ChatShop\\chatshop_log')) {
                    chatshop_log('Public initialization failed: ' . $e->getMessage(), 'error');
                }
            }
        }
    }

    /**
     * Schedule cron events
     */
    private function schedule_events()
    {
        add_action('chatshop_daily_cleanup', array($this, 'perform_daily_cleanup'));

        if (!wp_next_scheduled('chatshop_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'chatshop_daily_cleanup');
        }
    }

    /**
     * Initialize REST API endpoints
     */
    private function init_rest_api()
    {
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }

    /**
     * Register REST routes
     */
    public function register_rest_routes()
    {
        // Register API endpoints here
    }

    /**
     * Perform daily cleanup
     */
    public function perform_daily_cleanup()
    {
        // Cleanup old logs
        if (function_exists('ChatShop\\chatshop_cleanup_old_logs')) {
            chatshop_cleanup_old_logs();
        }

        // Cleanup old analytics data
        if (isset($this->components['analytics'])) {
            $this->components['analytics']->cleanup_old_data();
        }
    }

    /**
     * Run the plugin
     */
    public function run()
    {
        if ($this->loader) {
            $this->loader->run();
        }
    }

    /**
     * Get plugin version
     */
    public function get_version()
    {
        return $this->version;
    }

    /**
     * Get component instance
     */
    public function get_component($component_name)
    {
        if ($this->component_loader) {
            return $this->component_loader->get_component_instance($component_name);
        }
        return null;
    }

    /**
     * Check if premium feature is available
     */
    public function is_premium_feature_available($feature)
    {
        return isset($this->premium_features[$feature]) && $this->premium_features[$feature];
    }

    /**
     * Prevent cloning - singleton pattern
     */
    private function __clone()
    {
        _doing_it_wrong(__FUNCTION__, __('Cloning is forbidden.', 'chatshop'), '1.0.0');
    }

    /**
     * Prevent unserializing - singleton pattern
     */
    public function __wakeup()
    {
        _doing_it_wrong(__FUNCTION__, __('Unserializing is forbidden.', 'chatshop'), '1.0.0');
    }
}

// ================================
// PLUGIN INITIALIZATION
// ================================

// The chatshop() function is already defined in includes/chatshop-global-functions.php
// We don't need to declare it here to avoid redeclaration errors

// ================================
// ACTIVATION/DEACTIVATION HOOKS
// ================================

/**
 * Activation hook
 */
function chatshop_activate()
{
    define('CHATSHOP_ACTIVATING', true);

    if (class_exists('ChatShop\\ChatShop_Activator')) {
        ChatShop_Activator::activate();
    }
}

register_activation_hook(__FILE__, 'ChatShop\\chatshop_activate');

/**
 * Deactivation hook
 */
function chatshop_deactivate()
{
    define('CHATSHOP_DEACTIVATING', true);

    if (class_exists('ChatShop\\ChatShop_Deactivator')) {
        ChatShop_Deactivator::deactivate();
    }
}

register_deactivation_hook(__FILE__, 'ChatShop\\chatshop_deactivate');

// ================================
// INITIALIZE PLUGIN
// ================================

/**
 * Initialize plugin on plugins_loaded
 */
add_action('plugins_loaded', function () {
    $plugin = chatshop();
    if ($plugin) {
        $plugin->run();
    }
}, 5);

// ================================
// MAINTENANCE MODE CHECK
// ================================

if (!function_exists('ChatShop\\chatshop_maintenance_mode')) {
    function chatshop_maintenance_mode()
    {
        $maintenance_mode = get_option('chatshop_maintenance_mode', false);

        if ($maintenance_mode && !current_user_can('manage_options')) {
            add_action('admin_notices', function () {
                echo '<div class="notice notice-warning"><p>';
                echo __('ChatShop is currently in maintenance mode. Some features may be unavailable.', 'chatshop');
                echo '</p></div>';
            });
        }
    }
}

add_action('admin_init', 'ChatShop\\chatshop_maintenance_mode');

// ================================
// FINAL SAFETY CHECKS
// ================================

// Ensure critical constants are defined
if (!defined('CHATSHOP_PLUGIN_DIR')) {
    define('CHATSHOP_PLUGIN_DIR', plugin_dir_path(__FILE__));
}

if (!defined('CHATSHOP_PLUGIN_URL')) {
    define('CHATSHOP_PLUGIN_URL', plugin_dir_url(__FILE__));
}

if (!defined('CHATSHOP_VERSION')) {
    define('CHATSHOP_VERSION', '1.0.0');
}

// ================================
// MARK FILE AS LOADED
// ================================

// Mark file as completely loaded
if (!defined('CHATSHOP_MAIN_LOADED')) {
    define('CHATSHOP_MAIN_LOADED', true);
}

// Log successful file load
if (defined('WP_DEBUG') && WP_DEBUG && function_exists('error_log')) {
    error_log('ChatShop main plugin file loaded successfully with correct abstract class paths');
}
