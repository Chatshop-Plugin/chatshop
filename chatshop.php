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
 * File: chatshop.php - COMPLETE CLEAN VERSION
 * 
 * CRITICAL FIXES:
 * - Added initialization state tracking to prevent recursion
 * - Fixed singleton pattern with proper re-entry protection
 * - Delayed admin initialization until after core setup
 * - Improved error handling to prevent logging recursion
 * - All functions have existence checks to prevent redeclaration
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
    CHATSHOP_ADMIN_DIR,
    CHATSHOP_PUBLIC_DIR,
    CHATSHOP_COMPONENTS_DIR,
    CHATSHOP_COMPONENTS_DIR . 'payment/',
    CHATSHOP_COMPONENTS_DIR . 'payment/abstracts/',
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
 * Load payment component classes with proper dependency order
 */
$payment_files = array(
    // Load ALL abstract classes first (in dependency order)
    'abstracts/abstract-chatshop-component.php',
    'abstracts/abstract-chatshop-api-client.php',
    'abstracts/abstract-chatshop-payment-gateway.php',
    // Then load concrete implementation classes
    'class-chatshop-payment-manager.php',
    'class-chatshop-payment-factory.php',
    'class-chatshop-payment-link-generator.php',
    // Finally load gateway implementations (which depend on abstracts)
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
            chatshop_log("Analytics component file missing: {$analytics_file}", 'info');
        }
    }
}

// ================================
// MAIN PLUGIN CLASS
// ================================

/**
 * Main ChatShop class - INFINITE RECURSION FIXED
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
     * Initialization in progress flag to prevent recursion
     *
     * @var bool
     * @since 1.0.0
     */
    private $initializing = false;

    /**
     * Main ChatShop Instance
     *
     * Ensures only one instance of ChatShop is loaded or can be loaded.
     * FIXED: Added proper recursion protection.
     *
     * @since 1.0.0
     * @return ChatShop Main instance
     */
    public static function instance()
    {
        global $chatshop_initializing, $chatshop_initialization_complete;

        // If already complete, return existing instance
        if ($chatshop_initialization_complete && !is_null(self::$instance)) {
            return self::$instance;
        }

        // Prevent recursive calls during initialization
        if ($chatshop_initializing) {
            // Return a placeholder or null to break recursion
            return self::$instance;
        }

        // Mark initialization as starting
        $chatshop_initializing = true;

        try {
            if (is_null(self::$instance)) {
                self::$instance = new self();
            }
        } catch (Exception $e) {
            // Log error and reset state
            if (function_exists('error_log')) {
                error_log('ChatShop initialization error: ' . $e->getMessage());
            }
            $chatshop_initializing = false;
            return null;
        }

        // Mark initialization as complete
        $chatshop_initializing = false;
        $chatshop_initialization_complete = true;

        return self::$instance;
    }

    /**
     * ChatShop Constructor - RECURSION FIXED
     *
     * @since 1.0.0
     */
    private function __construct()
    {
        // Prevent double initialization
        if ($this->initializing || $this->initialized) {
            return;
        }

        $this->initializing = true;
        $this->init();
        $this->initializing = false;
    }

    /**
     * Initialize the plugin with enhanced error handling - RECURSION FIXED
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

            // Initialize core components in proper order (NO ADMIN YET)
            $this->init_logger();
            $this->init_loader();
            $this->init_component_loader();
            $this->set_locale();
            $this->init_premium_features();

            // Load components with error handling
            $this->load_components();

            // Define public hooks first (safe)
            $this->define_public_hooks();

            // IMPORTANT: Delay admin initialization to prevent recursion
            add_action('admin_init', array($this, 'delayed_admin_init'), 5);

            // Run the loader
            if ($this->loader) {
                $this->loader->run();
            }

            // Mark as initialized
            $this->initialized = true;

            // Plugin is now fully loaded
            do_action('chatshop_loaded', $this);

            if (function_exists('ChatShop\\chatshop_log')) {
                chatshop_log('ChatShop plugin initialized successfully', 'info');
            }
        } catch (Exception $e) {
            if (function_exists('ChatShop\\chatshop_log')) {
                chatshop_log('ChatShop initialization failed: ' . $e->getMessage(), 'error');
            }
            $this->initialized = false;
        }
    }

    /**
     * Delayed admin initialization to prevent recursion
     *
     * @since 1.0.0
     */
    public function delayed_admin_init()
    {
        // Only initialize admin if we're in admin area and not already done
        if (is_admin() && !$this->admin) {
            $this->define_admin_hooks();
        }
    }

    /**
     * Check plugin requirements
     *
     * @since 1.0.0
     * @return bool True if requirements met, false otherwise
     */
    private function check_requirements()
    {
        // Check WordPress version
        if (version_compare(get_bloginfo('version'), '5.0', '<')) {
            add_action('admin_notices', function () {
                echo '<div class="notice notice-error"><p>';
                echo __('ChatShop requires WordPress 5.0 or higher.', 'chatshop');
                echo '</p></div>';
            });
            return false;
        }

        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            add_action('admin_notices', function () {
                echo '<div class="notice notice-error"><p>';
                echo __('ChatShop requires PHP 7.4 or higher.', 'chatshop');
                echo '</p></div>';
            });
            return false;
        }

        return true;
    }

    /**
     * Initialize the logger
     *
     * @since 1.0.0
     */
    private function init_logger()
    {
        // Logger is handled through global functions
        if (function_exists('ChatShop\\chatshop_log')) {
            chatshop_log('Logger initialized', 'info');
        }
    }

    /**
     * Initialize the loader
     *
     * @since 1.0.0
     */
    private function init_loader()
    {
        if (class_exists('ChatShop\\ChatShop_Loader')) {
            $this->loader = new ChatShop_Loader();
        }
    }

    /**
     * Initialize component loader - SAFE VERSION
     *
     * @since 1.0.0
     */
    private function init_component_loader()
    {
        try {
            if (class_exists('ChatShop\\ChatShop_Component_Loader')) {
                $this->component_loader = new ChatShop_Component_Loader();
                if (function_exists('ChatShop\\chatshop_log')) {
                    chatshop_log('Component loader initialized', 'info');
                }
            } else {
                if (function_exists('ChatShop\\chatshop_log')) {
                    chatshop_log('ChatShop_Component_Loader class not found', 'error');
                }
            }
        } catch (Exception $e) {
            if (function_exists('ChatShop\\chatshop_log')) {
                chatshop_log('Component loader initialization failed: ' . $e->getMessage(), 'error');
            }
        }
    }

    /**
     * Set plugin locale
     *
     * @since 1.0.0
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
     *
     * @since 1.0.0
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
     *
     * @since 1.0.0
     */
    private function load_components()
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
     *
     * @since 1.0.0
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
     *
     * @since 1.0.0
     */
    private function define_public_hooks()
    {
        if (class_exists('ChatShop\\ChatShop_Public')) {
            try {
                $this->public = new ChatShop_Public();
                if (function_exists('ChatShop\\chatshop_log')) {
                    chatshop_log('Public hooks defined', 'info');
                }
            } catch (Exception $e) {
                if (function_exists('ChatShop\\chatshop_log')) {
                    chatshop_log('Public initialization failed: ' . $e->getMessage(), 'error');
                }
            }
        }
    }

    /**
     * Get component loader instance
     *
     * @since 1.0.0
     * @return ChatShop_Component_Loader|null
     */
    public function get_component_loader()
    {
        return $this->component_loader;
    }

    /**
     * Get admin instance
     *
     * @since 1.0.0
     * @return ChatShop_Admin|null
     */
    public function get_admin()
    {
        return $this->admin;
    }

    /**
     * Get public instance
     *
     * @since 1.0.0
     * @return ChatShop_Public|null
     */
    public function get_public()
    {
        return $this->public;
    }

    /**
     * Get payment manager
     *
     * @since 1.0.0
     * @return ChatShop_Payment_Manager|null
     */
    public function get_payment_manager()
    {
        if (!$this->payment_manager && $this->component_loader) {
            $this->payment_manager = $this->component_loader->get_component_instance('payment');
        }
        return $this->payment_manager;
    }

    /**
     * Get contact manager
     *
     * @since 1.0.0
     * @return ChatShop_Contact_Manager|null
     */
    public function get_contact_manager()
    {
        if (!$this->contact_manager && $this->component_loader) {
            $this->contact_manager = $this->component_loader->get_component_instance('contact_manager');
        }
        return $this->contact_manager;
    }

    /**
     * Get analytics instance
     *
     * @since 1.0.0
     * @return ChatShop_Analytics|null
     */
    public function get_analytics()
    {
        if (!$this->analytics && $this->component_loader) {
            $this->analytics = $this->component_loader->get_component_instance('analytics');
        }
        return $this->analytics;
    }

    /**
     * Check if premium feature is available
     *
     * @since 1.0.0
     * @param string $feature Feature name
     * @return bool True if available, false otherwise
     */
    public function is_premium_feature_available($feature)
    {
        return isset($this->premium_features[$feature]) && $this->premium_features[$feature];
    }

    /**
     * Get all premium features status
     *
     * @since 1.0.0
     * @return array Premium features status
     */
    public function get_premium_features()
    {
        return $this->premium_features;
    }

    /**
     * Check if plugin is initialized
     *
     * @since 1.0.0
     * @return bool True if initialized, false otherwise
     */
    public function is_initialized()
    {
        return $this->initialized;
    }

    /**
     * Get plugin version
     *
     * @since 1.0.0
     * @return string Plugin version
     */
    public function get_version()
    {
        return CHATSHOP_VERSION;
    }

    /**
     * Get plugin directory path
     *
     * @since 1.0.0
     * @return string Plugin directory path
     */
    public function get_plugin_dir()
    {
        return CHATSHOP_PLUGIN_DIR;
    }

    /**
     * Get plugin URL
     *
     * @since 1.0.0
     * @return string Plugin URL
     */
    public function get_plugin_url()
    {
        return CHATSHOP_PLUGIN_URL;
    }

    /**
     * Get loader instance
     *
     * @since 1.0.0
     * @return ChatShop_Loader|null Loader instance
     */
    public function get_loader()
    {
        return $this->loader;
    }

    /**
     * Run the plugin
     *
     * @since 1.0.0
     */
    public function run()
    {
        if ($this->loader) {
            $this->loader->run();
        }
    }

    /**
     * Handle AJAX requests
     *
     * @since 1.0.0
     */
    public function handle_ajax_request()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'chatshop_ajax_nonce')) {
            wp_die('Security check failed');
        }

        $action = sanitize_text_field($_POST['chatshop_action'] ?? '');

        switch ($action) {
            case 'get_system_status':
                wp_send_json_success($this->get_system_status());
                break;

            case 'reload_components':
                if (current_user_can('manage_options')) {
                    $this->reload_components();
                    wp_send_json_success('Components reloaded');
                } else {
                    wp_send_json_error('Insufficient permissions');
                }
                break;

            default:
                wp_send_json_error('Invalid action');
        }
    }

    /**
     * Get system status information
     *
     * @since 1.0.0
     * @return array System status
     */
    public function get_system_status()
    {
        $status = array(
            'plugin_version' => $this->get_version(),
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'initialized' => $this->initialized,
            'components_loaded' => false,
            'admin_loaded' => $this->admin !== null,
            'public_loaded' => $this->public !== null,
            'premium_features' => $this->premium_features
        );

        if ($this->component_loader) {
            $status['components_loaded'] = true;
            $status['loaded_components'] = array_keys($this->component_loader->get_all_instances());
            $status['component_errors'] = $this->component_loader->get_loading_errors();
            $status['component_count'] = $this->component_loader->get_loaded_count();
        }

        return $status;
    }

    /**
     * Reload components (for debugging)
     *
     * @since 1.0.0
     */
    public function reload_components()
    {
        if ($this->component_loader) {
            // Re-initialize component loader
            $this->component_loader = null;
            $this->init_component_loader();
            $this->load_components();
        }
    }

    /**
     * Emergency reset function
     *
     * @since 1.0.0
     */
    public function emergency_reset()
    {
        global $chatshop_initializing, $chatshop_initialization_complete;

        // Reset global state
        $chatshop_initializing = false;
        $chatshop_initialization_complete = false;

        // Reset instance state
        $this->initialized = false;
        $this->initializing = false;

        // Clear component instances
        $this->component_loader = null;
        $this->admin = null;
        $this->public = null;
        $this->payment_manager = null;
        $this->contact_manager = null;
        $this->analytics = null;

        // Log emergency reset
        if (function_exists('error_log')) {
            error_log('ChatShop: Emergency reset performed');
        }
    }

    /**
     * Prevent cloning
     *
     * @since 1.0.0
     */
    private function __clone()
    {
        // Prevent cloning
        throw new Exception('Cannot clone singleton instance');
    }

    /**
     * Prevent unserialization
     *
     * @since 1.0.0
     */
    public function __wakeup()
    {
        // Prevent unserialization
        throw new Exception('Cannot unserialize singleton');
    }
}

// ================================
// PLUGIN ACTIVATION/DEACTIVATION HOOKS
// ================================

if (!function_exists('ChatShop\\activate_chatshop')) {
    /**
     * Plugin activation hook
     */
    function activate_chatshop()
    {
        try {
            if (class_exists('ChatShop\\ChatShop_Activator')) {
                ChatShop_Activator::activate();
            }

            // Create activation flag
            update_option('chatshop_activation_time', current_time('mysql'));
            update_option('chatshop_version', CHATSHOP_VERSION);

            // Schedule cleanup
            if (function_exists('ChatShop\\chatshop_schedule_cleanup')) {
                chatshop_schedule_cleanup();
            }
        } catch (Exception $e) {
            if (function_exists('error_log')) {
                error_log('ChatShop activation error: ' . $e->getMessage());
            }

            // Deactivate plugin if activation fails
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die('ChatShop activation failed: ' . $e->getMessage());
        }
    }
}

if (!function_exists('ChatShop\\deactivate_chatshop')) {
    /**
     * Plugin deactivation hook
     */
    function deactivate_chatshop()
    {
        try {
            if (class_exists('ChatShop\\ChatShop_Deactivator')) {
                ChatShop_Deactivator::deactivate();
            }

            // Clear scheduled hooks
            if (function_exists('ChatShop\\chatshop_unschedule_cleanup')) {
                chatshop_unschedule_cleanup();
            }

            // Clear transients
            global $wpdb;
            $wpdb->query(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_chatshop_%' OR option_name LIKE '_transient_timeout_chatshop_%'"
            );

            // Log deactivation
            if (function_exists('error_log')) {
                error_log('ChatShop plugin deactivated');
            }
        } catch (Exception $e) {
            if (function_exists('error_log')) {
                error_log('ChatShop deactivation error: ' . $e->getMessage());
            }
        }
    }
}

// Only register hooks if functions exist and haven't been registered yet
if (function_exists('ChatShop\\activate_chatshop')) {
    register_activation_hook(__FILE__, 'ChatShop\\activate_chatshop');
}

if (function_exists('ChatShop\\deactivate_chatshop')) {
    register_deactivation_hook(__FILE__, 'ChatShop\\deactivate_chatshop');
}

// ================================
// AJAX HOOKS
// ================================

// Only add AJAX hooks if not already added
if (!has_action('wp_ajax_chatshop_system_status')) {
    add_action('wp_ajax_chatshop_system_status', function () {
        $plugin = ChatShop::instance();
        if ($plugin) {
            $plugin->handle_ajax_request();
        }
    });
}

if (!has_action('wp_ajax_chatshop_reload_components')) {
    add_action('wp_ajax_chatshop_reload_components', function () {
        $plugin = ChatShop::instance();
        if ($plugin) {
            $plugin->handle_ajax_request();
        }
    });
}

// ================================
// START THE PLUGIN - SAFE INITIALIZATION
// ================================

if (!function_exists('ChatShop\\run_chatshop')) {
    /**
     * Start plugin execution with enhanced safety
     */
    function run_chatshop()
    {
        global $chatshop_initializing, $chatshop_initialization_complete;

        // Prevent double execution
        if ($chatshop_initialization_complete) {
            return;
        }

        // Check if we're in a safe context
        if (wp_installing() || (defined('WP_REPAIRING') && WP_REPAIRING)) {
            return;
        }

        try {
            $plugin = ChatShop::instance();
            if (!$plugin) {
                throw new Exception('Failed to initialize ChatShop plugin instance');
            }

            // Additional initialization check
            if (!$plugin->is_initialized()) {
                throw new Exception('ChatShop plugin failed to initialize properly');
            }

            // Plugin successfully started
            if (defined('WP_DEBUG') && WP_DEBUG && function_exists('error_log')) {
                error_log('ChatShop plugin started successfully');
            }
        } catch (Exception $e) {
            // Log the error
            if (function_exists('error_log')) {
                error_log('ChatShop startup error: ' . $e->getMessage());
            }

            // Reset global state on error
            $chatshop_initializing = false;
            $chatshop_initialization_complete = false;

            // Show admin notice for critical errors
            add_action('admin_notices', function () use ($e) {
                if (current_user_can('manage_options')) {
                    echo '<div class="notice notice-error"><p>';
                    echo '<strong>ChatShop Error:</strong> ' . esc_html($e->getMessage());
                    echo '</p><p>';
                    echo '<a href="' . admin_url('plugins.php') . '" class="button">Manage Plugins</a> ';
                    echo '<a href="javascript:location.reload()" class="button">Reload Page</a>';
                    echo '</p></div>';
                }
            });

            // Emergency reset if needed
            if (defined('CHATSHOP_EMERGENCY_RESET') && CHATSHOP_EMERGENCY_RESET) {
                $plugin = ChatShop::instance();
                if ($plugin) {
                    $plugin->emergency_reset();
                }
            }
        }
    }
}

// Use appropriate hooks for different contexts - with duplicate check
if (is_admin()) {
    // In admin, wait for admin_init to ensure proper initialization
    if (!has_action('admin_init', 'ChatShop\\run_chatshop')) {
        add_action('admin_init', 'ChatShop\\run_chatshop', 5);
    }
} else {
    // For frontend, use plugins_loaded
    if (!has_action('plugins_loaded', 'ChatShop\\run_chatshop')) {
        add_action('plugins_loaded', 'ChatShop\\run_chatshop', 10);
    }
}

// ================================
// EMERGENCY RECURSION PROTECTION
// ================================

if (!function_exists('ChatShop\\chatshop_emergency_recursion_check')) {
    /**
     * Emergency function to detect and break recursion
     */
    function chatshop_emergency_recursion_check()
    {
        static $call_count = 0;
        static $last_reset = 0;

        $call_count++;
        $current_time = time();

        // Reset counter every minute
        if ($current_time - $last_reset > 60) {
            $call_count = 1;
            $last_reset = $current_time;
            return;
        }

        if ($call_count > 50) {
            // Log the recursion attempt
            if (function_exists('error_log')) {
                error_log('ChatShop: Emergency recursion protection activated - call count: ' . $call_count);
            }

            // Reset global state
            global $chatshop_initializing, $chatshop_initialization_complete;
            $chatshop_initializing = false;
            $chatshop_initialization_complete = false;

            // Show emergency notice
            add_action('admin_notices', function () use ($call_count) {
                echo '<div class="notice notice-error"><p>';
                echo '<strong>ChatShop Emergency Protection:</strong> Infinite recursion detected and stopped (calls: ' . $call_count . '). ';
                echo 'Please deactivate and reactivate the plugin to reset.';
                echo '</p></div>';
            });

            // Stop further execution
            remove_all_actions('init');
            remove_all_actions('admin_init');
            remove_all_actions('plugins_loaded');

            return;
        }
    }
}

// Hook the emergency check early - with duplicate prevention
if (!has_action('init', 'ChatShop\\chatshop_emergency_recursion_check')) {
    add_action('init', 'ChatShop\\chatshop_emergency_recursion_check', 1);
}

if (!has_action('admin_init', 'ChatShop\\chatshop_emergency_recursion_check')) {
    add_action('admin_init', 'ChatShop\\chatshop_emergency_recursion_check', 1);
}

// ================================
// SHUTDOWN HANDLER
// ================================

if (!function_exists('ChatShop\\chatshop_shutdown_handler')) {
    /**
     * Handle plugin shutdown and cleanup
     */
    function chatshop_shutdown_handler()
    {
        $error = error_get_last();
        if ($error && strpos($error['message'], 'ChatShop') !== false) {
            // Log ChatShop-related fatal errors
            if (function_exists('error_log')) {
                error_log('ChatShop shutdown error: ' . $error['message'] . ' in ' . $error['file'] . ' on line ' . $error['line']);
            }

            // Reset global state
            global $chatshop_initializing, $chatshop_initialization_complete;
            $chatshop_initializing = false;
            $chatshop_initialization_complete = false;
        }
    }
}

// Register shutdown function only once
static $shutdown_registered = false;
if (!$shutdown_registered) {
    register_shutdown_function('ChatShop\\chatshop_shutdown_handler');
    $shutdown_registered = true;
}

// ================================
// VERSION CHECK AND UPGRADE
// ================================

if (!function_exists('ChatShop\\chatshop_version_check')) {
    /**
     * Check for plugin version changes and handle upgrades
     */
    function chatshop_version_check()
    {
        $installed_version = get_option('chatshop_version', '0.0.0');
        $current_version = CHATSHOP_VERSION;

        if (version_compare($installed_version, $current_version, '<')) {
            // Plugin has been updated
            do_action('chatshop_plugin_updated', $installed_version, $current_version);

            // Update version in database
            update_option('chatshop_version', $current_version);

            // Log the update
            if (function_exists('error_log')) {
                error_log("ChatShop updated from {$installed_version} to {$current_version}");
            }
        }
    }
}

// Add version check hook only if not already added
if (!has_action('admin_init', 'ChatShop\\chatshop_version_check')) {
    add_action('admin_init', 'ChatShop\\chatshop_version_check');
}

// ================================
// COMPATIBILITY FUNCTIONS
// ================================

if (!function_exists('ChatShop\\chatshop_check_compatibility')) {
    /**
     * Check plugin compatibility with environment
     */
    function chatshop_check_compatibility()
    {
        $compatibility = array(
            'wordpress' => version_compare(get_bloginfo('version'), '5.0', '>='),
            'php' => version_compare(PHP_VERSION, '7.4', '>='),
            'mysql' => true, // Will check later if needed
            'extensions' => array(
                'curl' => extension_loaded('curl'),
                'json' => extension_loaded('json'),
                'mbstring' => extension_loaded('mbstring')
            )
        );

        // Check for critical compatibility issues
        $critical_issues = array();

        if (!$compatibility['wordpress']) {
            $critical_issues[] = 'WordPress version 5.0 or higher required';
        }

        if (!$compatibility['php']) {
            $critical_issues[] = 'PHP version 7.4 or higher required';
        }

        if (!$compatibility['extensions']['curl']) {
            $critical_issues[] = 'PHP cURL extension required';
        }

        if (!$compatibility['extensions']['json']) {
            $critical_issues[] = 'PHP JSON extension required';
        }

        if (!empty($critical_issues)) {
            add_action('admin_notices', function () use ($critical_issues) {
                echo '<div class="notice notice-error"><p>';
                echo '<strong>ChatShop Compatibility Issues:</strong><br>';
                foreach ($critical_issues as $issue) {
                    echo '• ' . esc_html($issue) . '<br>';
                }
                echo '</p></div>';
            });

            return false;
        }

        return true;
    }
}

// Run compatibility check
add_action('admin_init', 'ChatShop\\chatshop_check_compatibility');

// ================================
// DEVELOPER TOOLS
// ================================

if (defined('WP_DEBUG') && WP_DEBUG) {

    if (!function_exists('ChatShop\\chatshop_debug_info')) {
        /**
         * Display debug information for developers
         */
        function chatshop_debug_info()
        {
            if (!current_user_can('manage_options')) {
                return;
            }

            $debug_info = array(
                'plugin_version' => CHATSHOP_VERSION,
                'wordpress_version' => get_bloginfo('version'),
                'php_version' => PHP_VERSION,
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'loaded_extensions' => get_loaded_extensions(),
                'active_plugins' => get_option('active_plugins', array()),
                'theme' => get_option('stylesheet')
            );

            if (isset($_GET['chatshop_debug']) && $_GET['chatshop_debug'] === '1') {
                echo '<div style="background: #f1f1f1; padding: 20px; margin: 20px 0; border-left: 4px solid #0073aa;">';
                echo '<h3>ChatShop Debug Information</h3>';
                echo '<pre>' . print_r($debug_info, true) . '</pre>';
                echo '</div>';
            }
        }
    }

    add_action('admin_footer', 'ChatShop\\chatshop_debug_info');
}

// ================================
// SECURITY FUNCTIONS
// ================================

if (!function_exists('ChatShop\\chatshop_security_headers')) {
    /**
     * Add security headers for ChatShop pages
     */
    function chatshop_security_headers()
    {
        $screen = get_current_screen();
        if ($screen && strpos($screen->id, 'chatshop') !== false) {
            // Add security headers for admin pages
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: SAMEORIGIN');
            header('X-XSS-Protection: 1; mode=block');
        }
    }
}

add_action('admin_init', 'ChatShop\\chatshop_security_headers');

// ================================
// CLEANUP AND MAINTENANCE
// ================================

if (!function_exists('ChatShop\\chatshop_maintenance_mode')) {
    /**
     * Handle maintenance mode
     */
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
// PLUGIN INFORMATION
// ================================

if (!function_exists('ChatShop\\chatshop_get_plugin_data')) {
    /**
     * Get plugin header data
     */
    function chatshop_get_plugin_data()
    {
        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return get_plugin_data(__FILE__);
    }
}

// ================================
// REGISTER UNINSTALL HOOK
// ================================

register_uninstall_hook(__FILE__, function () {
    // Clean up plugin data on uninstall
    delete_option('chatshop_version');
    delete_option('chatshop_activation_time');
    delete_option('chatshop_settings');
    delete_option('chatshop_component_settings');
    delete_option('chatshop_error_log');

    // Clear all transients
    global $wpdb;
    $wpdb->query(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_chatshop_%' OR option_name LIKE '_transient_timeout_chatshop_%'"
    );
});

// ================================
// MARK FILE AS LOADED
// ================================

// Mark file as completely loaded
if (!defined('CHATSHOP_MAIN_LOADED')) {
    define('CHATSHOP_MAIN_LOADED', true);
}

// Log successful file load
if (defined('WP_DEBUG') && WP_DEBUG && function_exists('error_log')) {
    error_log('ChatShop main plugin file loaded successfully');
}
