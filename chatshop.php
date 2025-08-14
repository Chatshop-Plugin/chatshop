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
 * @package ChatShop
 */

// Namespace MUST be immediately after the plugin header comment
namespace ChatShop;

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Prevent multiple plugin loads
if (defined('CHATSHOP_VERSION')) {
    add_action('admin_notices', 'ChatShop\\chatshop_duplicate_plugin_notice');
    return;
}

/**
 * Display duplicate plugin notice
 *
 * @since 1.0.0
 */
function chatshop_duplicate_plugin_notice()
{
    echo '<div class="notice notice-error"><p>';
    echo __('ChatShop: Plugin appears to be loaded multiple times. Please check for duplicate installations.', 'chatshop');
    echo '</p></div>';
}

// Define plugin constants
define('CHATSHOP_VERSION', '1.0.0');
define('CHATSHOP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CHATSHOP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CHATSHOP_PLUGIN_FILE', __FILE__);
define('CHATSHOP_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('CHATSHOP_INCLUDES_DIR', CHATSHOP_PLUGIN_DIR . 'includes/');
define('CHATSHOP_ADMIN_DIR', CHATSHOP_PLUGIN_DIR . 'admin/');
define('CHATSHOP_PUBLIC_DIR', CHATSHOP_PLUGIN_DIR . 'public/');
define('CHATSHOP_COMPONENTS_DIR', CHATSHOP_PLUGIN_DIR . 'components/');

/**
 * Check critical requirements before loading anything else
 */
function chatshop_check_requirements()
{
    global $chatshop_requirement_errors;
    $chatshop_requirement_errors = array();

    // PHP version check
    if (version_compare(PHP_VERSION, '7.4', '<')) {
        $chatshop_requirement_errors[] = sprintf('ChatShop requires PHP 7.4 or higher. Current version: %s', PHP_VERSION);
    }

    // Required PHP extensions
    $required_extensions = array('json', 'curl', 'mbstring', 'openssl');
    foreach ($required_extensions as $ext) {
        if (!extension_loaded($ext)) {
            $chatshop_requirement_errors[] = sprintf('ChatShop requires PHP %s extension.', $ext);
        }
    }

    // WordPress version check  
    if (function_exists('get_bloginfo') && version_compare(get_bloginfo('version'), '5.0', '<')) {
        $chatshop_requirement_errors[] = sprintf('ChatShop requires WordPress 5.0 or higher. Current version: %s', get_bloginfo('version'));
    }

    if (!empty($chatshop_requirement_errors)) {
        if (function_exists('deactivate_plugins')) {
            deactivate_plugins(plugin_basename(__FILE__));
        }

        add_action('admin_notices', 'ChatShop\\chatshop_requirements_notice');

        return false;
    }

    return true;
}

/**
 * Display requirements failure notice
 *
 * @since 1.0.0
 */
function chatshop_requirements_notice()
{
    global $chatshop_requirement_errors;
    if (!empty($chatshop_requirement_errors)) {
        echo '<div class="notice notice-error"><p><strong>ChatShop Activation Failed:</strong></p><ul>';
        foreach ($chatshop_requirement_errors as $error) {
            echo '<li>' . esc_html($error) . '</li>';
        }
        echo '</ul></div>';
    }
}

// Run requirements check
if (!chatshop_check_requirements()) {
    return;
}

/**
 * Ensure required directories exist
 */
function chatshop_create_directories()
{
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
}

chatshop_create_directories();

/**
 * Simple logging function
 */
function chatshop_log($message, $level = 'info', $context = array())
{
    if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        $formatted_message = '[ChatShop] ' . $level . ': ' . $message;
        if (!empty($context)) {
            $formatted_message .= ' | Context: ' . wp_json_encode($context);
        }
        error_log($formatted_message);
    }
}

/**
 * Load core classes
 */
function chatshop_load_core_classes()
{
    $core_classes = array(
        'chatshop-global-functions.php' => null, // No class check needed
        'class-chatshop-loader.php' => 'ChatShop\\ChatShop_Loader',
        'class-chatshop-activator.php' => 'ChatShop\\ChatShop_Activator',
        'class-chatshop-deactivator.php' => 'ChatShop\\ChatShop_Deactivator',
        'class-chatshop-component-registry.php' => 'ChatShop\\ChatShop_Component_Registry',
        'class-chatshop-component-loader.php' => 'ChatShop\\ChatShop_Component_Loader',
        'class-chatshop-logger.php' => 'ChatShop\\ChatShop_Logger',
        'class-chatshop-i18n.php' => 'ChatShop\\ChatShop_I18n',
        'class-chatshop-helper.php' => 'ChatShop\\ChatShop_Helper'
    );

    foreach ($core_classes as $class_file => $class_name) {
        // Only check class existence if class_name is provided
        if ($class_name === null || !class_exists($class_name)) {
            $file_path = CHATSHOP_INCLUDES_DIR . $class_file;
            if (file_exists($file_path)) {
                require_once $file_path;
                chatshop_log("Loaded core class: {$class_file}", 'debug');
            } else {
                chatshop_log("Core class file missing: {$class_file}", 'warning');
            }
        } else {
            chatshop_log("Core class already exists: {$class_name}", 'debug');
        }
    }

    // Load missing classes fallback if needed
    $missing_classes_file = CHATSHOP_INCLUDES_DIR . 'missing-core-classes.php';
    if (file_exists($missing_classes_file)) {
        require_once $missing_classes_file;
    }
}

/**
 * Load admin classes
 */
function chatshop_load_admin_classes()
{
    if (!is_admin()) {
        return;
    }

    $admin_classes = array(
        'class-chatshop-admin.php' => 'ChatShop\\ChatShop_Admin',
        'class-chatshop-admin-menu.php' => 'ChatShop\\ChatShop_Admin_Menu',
        'class-chatshop-settings.php' => 'ChatShop\\ChatShop_Settings'
    );

    foreach ($admin_classes as $admin_file => $class_name) {
        // Only load if class doesn't exist
        if (!class_exists($class_name)) {
            $file_path = CHATSHOP_ADMIN_DIR . $admin_file;
            if (file_exists($file_path)) {
                require_once $file_path;
                chatshop_log("Loaded admin class: {$admin_file}", 'debug');
            }
        } else {
            chatshop_log("Admin class already exists: {$class_name}", 'debug');
        }
    }
}

/**
 * Load public classes
 */
function chatshop_load_public_classes()
{
    $public_classes = array(
        'class-chatshop-public.php' => 'ChatShop\\ChatShop_Public'
    );

    foreach ($public_classes as $public_file => $class_name) {
        // Only load if class doesn't exist
        if (!class_exists($class_name)) {
            $file_path = CHATSHOP_PUBLIC_DIR . $public_file;
            if (file_exists($file_path)) {
                require_once $file_path;
                chatshop_log("Loaded public class: {$public_file}", 'debug');
            }
        } else {
            chatshop_log("Public class already exists: {$class_name}", 'debug');
        }
    }
}

/**
 * Load component classes
 */
function chatshop_load_component_classes()
{
    // Load abstract classes first
    $abstract_files = array(
        'includes/abstracts/abstract-chatshop-component.php',
        'components/payment/abstracts/abstract-chatshop-api-client.php',
        'components/payment/abstracts/abstract-chatshop-payment-gateway.php'
    );

    foreach ($abstract_files as $abstract_file) {
        $file_path = CHATSHOP_PLUGIN_DIR . $abstract_file;
        if (file_exists($file_path)) {
            require_once $file_path;
        }
    }

    // Load payment components
    $payment_files = array(
        'class-chatshop-payment-manager.php',
        'class-chatshop-payment-factory.php',
        'class-chatshop-payment-link-generator.php',
        'gateways/paystack/class-chatshop-paystack-api.php',
        'gateways/paystack/class-chatshop-paystack-gateway.php',
        'gateways/paystack/class-chatshop-paystack-webhook.php'
    );

    foreach ($payment_files as $payment_file) {
        $file_path = CHATSHOP_COMPONENTS_DIR . 'payment/' . $payment_file;
        if (file_exists($file_path)) {
            require_once $file_path;
        }
    }

    // Load other components
    $component_files = array(
        'whatsapp/class-chatshop-whatsapp-api.php',
        'whatsapp/class-chatshop-contact-manager.php',
        'analytics/class-chatshop-analytics.php',
        'analytics/class-chatshop-analytics-export.php'
    );

    foreach ($component_files as $component_file) {
        $file_path = CHATSHOP_COMPONENTS_DIR . $component_file;
        if (file_exists($file_path)) {
            require_once $file_path;
        }
    }
}

// Load all classes
chatshop_load_core_classes();
chatshop_load_admin_classes();
chatshop_load_public_classes();
chatshop_load_component_classes();

/**
 * Main ChatShop class - Simplified version
 */
final class ChatShop
{
    private static $instance = null;
    private $loader;
    private $admin;
    private $public;
    private $component_loader;
    private $initialized = false;

    /**
     * Get singleton instance
     */
    public static function instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        $this->init();
    }

    /**
     * Initialize the plugin
     */
    private function init()
    {
        if ($this->initialized) {
            return;
        }

        try {
            $this->init_loader();
            $this->init_component_loader();
            $this->set_locale();
            $this->load_components();
            $this->define_hooks();

            if ($this->loader) {
                $this->loader->run();
            }

            $this->initialized = true;
            do_action('chatshop_loaded', $this);
            chatshop_log('ChatShop plugin initialized successfully', 'info');
        } catch (Exception $e) {
            chatshop_log('ChatShop initialization failed: ' . $e->getMessage(), 'error');
            $this->initialized = false;
        }
    }

    /**
     * Initialize the loader
     */
    private function init_loader()
    {
        if (class_exists('ChatShop\\ChatShop_Loader')) {
            $this->loader = new ChatShop_Loader();
        }
    }

    /**
     * Initialize component loader
     */
    private function init_component_loader()
    {
        if (class_exists('ChatShop\\ChatShop_Component_Loader')) {
            $this->component_loader = new ChatShop_Component_Loader();
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
     * Load components
     */
    private function load_components()
    {
        if ($this->component_loader && method_exists($this->component_loader, 'load_all_components')) {
            $this->component_loader->load_all_components();
        }
    }

    /**
     * Define hooks
     */
    private function define_hooks()
    {
        // Admin hooks
        if (is_admin() && class_exists('ChatShop\\ChatShop_Admin')) {
            $this->admin = new ChatShop_Admin();
        }

        // Public hooks
        if (class_exists('ChatShop\\ChatShop_Public')) {
            $this->public = new ChatShop_Public();
        }
    }

    /**
     * Check if plugin is initialized
     */
    public function is_initialized()
    {
        return $this->initialized;
    }

    /**
     * Get plugin version
     */
    public function get_version()
    {
        return CHATSHOP_VERSION;
    }

    /**
     * Get component loader instance
     */
    public function get_component_loader()
    {
        return $this->component_loader;
    }

    /**
     * Get admin instance
     */
    public function get_admin()
    {
        return $this->admin;
    }

    /**
     * Get public instance
     */
    public function get_public()
    {
        return $this->public;
    }

    /**
     * Get loader instance
     */
    public function get_loader()
    {
        return $this->loader;
    }

    // Prevent cloning and unserialization
    private function __clone() {}
    public function __wakeup()
    {
        throw new Exception('Cannot unserialize singleton');
    }
}

/**
 * Enhanced activation function
 */
function activate_chatshop()
{
    try {
        if (!chatshop_check_requirements()) {
            throw new Exception('Requirements check failed during activation');
        }

        if (class_exists('ChatShop\\ChatShop_Activator')) {
            ChatShop_Activator::activate();
        }

        update_option('chatshop_activation_successful', true);
        update_option('chatshop_activation_time', current_time('mysql'));
        update_option('chatshop_version', CHATSHOP_VERSION);

        chatshop_log('ChatShop activated successfully', 'info');
    } catch (Exception $e) {
        chatshop_log('Activation failed: ' . $e->getMessage(), 'error');
        update_option('chatshop_activation_error', $e->getMessage());
        deactivate_plugins(plugin_basename(__FILE__));

        wp_die(
            'ChatShop activation failed: ' . esc_html($e->getMessage()) .
                '<br><br><a href="' . admin_url('plugins.php') . '">← Return to Plugins</a>',
            'Plugin Activation Error',
            array('back_link' => true)
        );
    }
}

/**
 * Deactivation function
 */
function deactivate_chatshop()
{
    try {
        if (class_exists('ChatShop\\ChatShop_Deactivator')) {
            ChatShop_Deactivator::deactivate();
        }

        // Clear scheduled events
        wp_clear_scheduled_hook('chatshop_daily_cleanup');
        wp_clear_scheduled_hook('chatshop_analytics_aggregation');
        wp_clear_scheduled_hook('chatshop_process_campaigns');

        // Clear transients
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_chatshop_%' OR option_name LIKE '_transient_timeout_chatshop_%'"
        );

        delete_option('chatshop_activation_successful');
        delete_option('chatshop_activation_error');

        chatshop_log('ChatShop deactivated successfully', 'info');
    } catch (Exception $e) {
        chatshop_log('Deactivation error: ' . $e->getMessage(), 'error');
    }
}

// Register activation/deactivation hooks
register_activation_hook(__FILE__, 'ChatShop\\activate_chatshop');
register_deactivation_hook(__FILE__, 'ChatShop\\deactivate_chatshop');

// Show activation errors
add_action('admin_notices', 'ChatShop\\chatshop_activation_error_notice');

/**
 * Display activation error notice
 *
 * @since 1.0.0
 */
function chatshop_activation_error_notice()
{
    $activation_error = get_option('chatshop_activation_error');
    if ($activation_error && current_user_can('manage_options')) {
        echo '<div class="notice notice-error is-dismissible">';
        echo '<p><strong>ChatShop Activation Error:</strong> ' . esc_html($activation_error) . '</p>';
        echo '</div>';
        delete_option('chatshop_activation_error');
    }
}

/**
 * Start the plugin
 */
function run_chatshop()
{
    // Prevent double execution
    static $executed = false;
    if ($executed) {
        return;
    }
    $executed = true;

    try {
        $plugin = ChatShop::instance();
        if (!$plugin || !$plugin->is_initialized()) {
            chatshop_log('ChatShop plugin initialization incomplete', 'warning');
        }
    } catch (Exception $e) {
        chatshop_log('ChatShop startup error: ' . $e->getMessage(), 'error');

        add_action('admin_notices', 'ChatShop\\chatshop_startup_error_notice');
    }
}

/**
 * Display startup error notice
 *
 * @since 1.0.0
 */
function chatshop_startup_error_notice()
{
    if (current_user_can('manage_options')) {
        echo '<div class="notice notice-error"><p>';
        echo '<strong>ChatShop Error:</strong> Plugin failed to start properly. Please check error logs.';
        echo '</p></div>';
    }
}

// Initialize the plugin
add_action('plugins_loaded', 'ChatShop\\run_chatshop', 20);

/**
 * Uninstall cleanup function
 *
 * @since 1.0.0
 */
function chatshop_uninstall_cleanup()
{
    $options_to_delete = array(
        'chatshop_version',
        'chatshop_activation_time',
        'chatshop_activation_successful',
        'chatshop_activation_error',
        'chatshop_settings',
        'chatshop_component_settings',
        'chatshop_error_log'
    );

    foreach ($options_to_delete as $option) {
        delete_option($option);
    }

    // Clear all transients
    global $wpdb;
    $wpdb->query(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_chatshop_%' OR option_name LIKE '_transient_timeout_chatshop_%'"
    );
}

// Register uninstall hook with named function
register_uninstall_hook(__FILE__, 'ChatShop\\chatshop_uninstall_cleanup');
