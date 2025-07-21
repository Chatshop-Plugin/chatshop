<?php

/**
 * Plugin Name:       ChatShop
 * Plugin URI:        https://modewebhost.com.ng
 * Description:       Social commerce plugin for WhatsApp and payments
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
    private function __construct()
    {
        $this->load_dependencies();
        $this->init_loader();
        $this->init_logger();
        $this->set_locale();
        $this->check_requirements();
        $this->init_premium_features();
        $this->init_payment_system();
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
                    $gateway_dir . '/class-' . $gateway_id . '-gateway.php',
                );

                foreach ($gateway_files as $gateway_file) {
                    if (file_exists($gateway_file)) {
                        require_once $gateway_file;
                        chatshop_log("Loaded gateway: {$gateway_id}", 'debug');
                        break;
                    }
                }
            }
        }

        // Allow plugins/themes to load additional gateways
        do_action('chatshop_load_payment_gateways');
    }

    /**
     * Initialize premium features
     *
     * @since 1.0.0
     */
    private function init_premium_features()
    {
        $this->premium_features = array(
            'multiple_gateways' => $this->is_feature_enabled('multiple_gateways'),
            'advanced_analytics' => $this->is_feature_enabled('advanced_analytics'),
            'abandoned_payment_recovery' => $this->is_feature_enabled('abandoned_payment_recovery'),
            'multi_currency_support' => $this->is_feature_enabled('multi_currency_support'),
            'whatsapp_automation' => $this->is_feature_enabled('whatsapp_automation'),
            'custom_branding' => $this->is_feature_enabled('custom_branding'),
        );

        // Hook for premium feature initialization
        do_action('chatshop_premium_features_init', $this->premium_features);
    }

    /**
     * Check if a premium feature is enabled
     *
     * @param string $feature Feature name
     * @return bool Whether feature is enabled
     * @since 1.0.0
     */
    private function is_feature_enabled($feature)
    {
        $premium_options = get_option('chatshop_premium_options', array());
        $license_status = get_option('chatshop_license_status', 'free');

        // Free tier limitations
        if ($license_status === 'free') {
            $free_features = array('multiple_gateways');
            return in_array($feature, $free_features, true);
        }

        // Premium tier - check individual feature toggles
        return isset($premium_options[$feature]) ? (bool) $premium_options[$feature] : false;
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
     * Register payment gateways
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
     * Register Paystack gateway
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
     * Register additional premium gateways
     *
     * @since 1.0.0
     */
    private function register_additional_gateways()
    {
        $additional_gateways = array('paypal', 'flutterwave', 'razorpay');

        foreach ($additional_gateways as $gateway_id) {
            $this->register_gateway_by_id($gateway_id);
        }
    }

    /**
     * Register gateway by ID
     *
     * @param string $gateway_id Gateway identifier
     * @since 1.0.0
     */
    private function register_gateway_by_id($gateway_id)
    {
        $class_name = 'ChatShop\ChatShop_' . ucfirst($gateway_id) . '_Gateway';

        if (!class_exists($class_name)) {
            return;
        }

        try {
            $gateway = new $class_name();

            if ($this->is_gateway_configured($gateway_id)) {
                $this->payment_manager->register_gateway($gateway);
                $this->registered_gateways[$gateway_id] = $gateway;

                chatshop_log("Gateway {$gateway_id} registered successfully", 'info');
            }
        } catch (\Exception $e) {
            chatshop_log("Failed to register {$gateway_id} gateway: " . $e->getMessage(), 'error');
        }
    }

    /**
     * Check if gateway is properly configured
     *
     * @param string $gateway_id Gateway identifier
     * @return bool Whether gateway is configured
     * @since 1.0.0
     */
    private function is_gateway_configured($gateway_id)
    {
        $options = chatshop_get_option($gateway_id, '', array());

        // Check for required configuration
        switch ($gateway_id) {
            case 'paystack':
                $test_key = isset($options['test_secret_key']) && !empty($options['test_secret_key']);
                $live_key = isset($options['live_secret_key']) && !empty($options['live_secret_key']);
                return $test_key || $live_key;

            case 'paypal':
                return isset($options['client_id']) && !empty($options['client_id']);

            case 'flutterwave':
                return isset($options['secret_key']) && !empty($options['secret_key']);

            case 'razorpay':
                return isset($options['key_id']) && !empty($options['key_id']);

            default:
                return false;
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
     * Initialize the logger
     *
     * @since 1.0.0
     */
    private function init_logger()
    {
        ChatShop_Logger::init();
    }

    /**
     * Define the locale for internationalization
     *
     * @since 1.0.0
     */
    private function set_locale()
    {
        if (class_exists('ChatShop\ChatShop_i18n')) {
            $plugin_i18n = new ChatShop_i18n();
            $this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
        }
    }

    /**
     * Check plugin requirements
     *
     * @since 1.0.0
     */
    private function check_requirements()
    {
        $this->loader->add_action('admin_init', $this, 'check_dependencies');
    }

    /**
     * Check if dependencies are met
     *
     * @since 1.0.0
     */
    public function check_dependencies()
    {
        // Basic dependency checks
        $errors = array();

        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            $errors[] = sprintf(__('ChatShop requires PHP 7.4 or higher. You are running PHP %s.', 'chatshop'), PHP_VERSION);
        }

        // Check WordPress version
        if (version_compare(get_bloginfo('version'), '5.0', '<')) {
            $errors[] = sprintf(__('ChatShop requires WordPress 5.0 or higher. You are running WordPress %s.', 'chatshop'), get_bloginfo('version'));
        }

        // Display errors if any
        if (!empty($errors)) {
            add_action('admin_notices', function () use ($errors) {
?>
                <div class="notice notice-error">
                    <p><strong><?php esc_html_e('ChatShop Error:', 'chatshop'); ?></strong></p>
                    <ul>
                        <?php foreach ($errors as $error) : ?>
                            <li><?php echo esc_html($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
<?php
            });
        }
    }

    /**
     * Register all of the hooks related to the admin area functionality
     *
     * @since 1.0.0
     */
    private function define_admin_hooks()
    {
        if (is_admin() && class_exists('ChatShop\ChatShop_Admin')) {
            $this->admin = new ChatShop_Admin();

            // Admin initialization
            $this->loader->add_action('admin_enqueue_scripts', $this->admin, 'enqueue_styles');
            $this->loader->add_action('admin_enqueue_scripts', $this->admin, 'enqueue_scripts');

            // Admin menu hooks
            $this->loader->add_action('admin_menu', $this->admin, 'add_admin_menu');
            $this->loader->add_action('admin_init', $this->admin, 'init_settings');

            // AJAX hooks for admin
            $this->loader->add_action('wp_ajax_chatshop_admin_action', $this->admin, 'handle_ajax_request');

            // Gateway configuration hooks
            $this->loader->add_action('wp_ajax_chatshop_test_gateway', $this, 'ajax_test_gateway');
            $this->loader->add_action('wp_ajax_chatshop_save_gateway_settings', $this, 'ajax_save_gateway_settings');
        }
    }

    /**
     * Register all of the hooks related to the public-facing functionality
     *
     * @since 1.0.0
     */
    private function define_public_hooks()
    {
        if (class_exists('ChatShop\ChatShop_Public')) {
            $this->public = new ChatShop_Public();

            // Public scripts and styles
            $this->loader->add_action('wp_enqueue_scripts', $this->public, 'enqueue_styles');
            $this->loader->add_action('wp_enqueue_scripts', $this->public, 'enqueue_scripts');

            // Shortcode registration
            $this->loader->add_action('init', $this->public, 'register_shortcodes');

            // Public AJAX hooks (for non-logged in users)
            $this->loader->add_action('wp_ajax_nopriv_chatshop_public_action', $this->public, 'handle_ajax_request');
            $this->loader->add_action('wp_ajax_chatshop_public_action', $this->public, 'handle_ajax_request');

            // Add floating WhatsApp button
            $this->loader->add_action('wp_footer', $this->public, 'add_floating_whatsapp_button');
        }
    }

    /**
     * Initialize WordPress hooks
     *
     * @since 1.0.0
     */
    private function init_hooks()
    {
        $this->loader->add_action('init', $this, 'init');
        $this->loader->add_action('rest_api_init', $this, 'init_rest_api');

        // Payment system hooks - only if payment manager exists
        if ($this->payment_manager) {
            $this->loader->add_action('wp_loaded', $this, 'init_payment_hooks');
        }

        // Premium feature hooks
        $this->loader->add_action('chatshop_daily_cleanup', $this, 'process_premium_features');
    }

    /**
     * Init ChatShop when WordPress initializes
     *
     * @since 1.0.0
     */
    public function init()
    {
        // Initialize components
        $this->init_components();

        // Hook for extensions
        do_action('chatshop_init', $this);
    }

    /**
     * Initialize payment hooks after WordPress is loaded
     *
     * @since 1.0.0
     */
    public function init_payment_hooks()
    {
        // Register webhook endpoints for payment gateways
        add_action('wp_ajax_nopriv_chatshop_webhook', array($this, 'handle_payment_webhook'));
        add_action('wp_ajax_chatshop_webhook', array($this, 'handle_payment_webhook'));

        // Allow gateways to register their own hooks
        do_action('chatshop_payment_hooks_loaded', $this->payment_manager);
    }

    /**
     * Handle payment webhook requests
     *
     * @since 1.0.0
     */
    public function handle_payment_webhook()
    {
        $gateway_id = sanitize_key($_GET['gateway'] ?? '');

        if (empty($gateway_id)) {
            wp_die(__('Invalid webhook request', 'chatshop'), 400);
        }

        $payload = file_get_contents('php://input');
        $decoded_payload = json_decode($payload, true);

        if ($this->payment_manager) {
            $result = $this->payment_manager->process_webhook($decoded_payload, $gateway_id);

            if ($result) {
                wp_die('OK', 200);
            } else {
                wp_die(__('Webhook processing failed', 'chatshop'), 400);
            }
        }

        wp_die(__('Payment system not initialized', 'chatshop'), 500);
    }

    /**
     * Initialize REST API endpoints
     *
     * @since 1.0.0
     */
    public function init_rest_api()
    {
        // Register REST API endpoints
        $api_path = CHATSHOP_PLUGIN_DIR . 'api/class-chatshop-api.php';
        if (file_exists($api_path)) {
            require_once $api_path;
            if (class_exists('\ChatShop\ChatShop_API')) {
                $api = new ChatShop_API();
                $api->register_routes();
            }
        }

        // Payment manager will register its own endpoints - only if it exists
        if ($this->payment_manager && method_exists($this->payment_manager, 'register_api_endpoints')) {
            $this->payment_manager->register_api_endpoints();
        }
    }

    /**
     * Initialize plugin components
     *
     * @since 1.0.0
     */
    private function init_components()
    {
        // Initialize component loader
        $this->component_loader = new ChatShop_Component_Loader();
        $this->component_loader->load_components();

        // Hook for manual component loading if automatic loading fails
        do_action('chatshop_load_components');
    }

    /**
     * Process premium features
     *
     * @since 1.0.0
     */
    public function process_premium_features()
    {
        // Process abandoned payment recovery
        if ($this->premium_features['abandoned_payment_recovery']) {
            foreach ($this->registered_gateways as $gateway) {
                if (method_exists($gateway, 'process_abandoned_payments')) {
                    $gateway->process_abandoned_payments();
                }
            }
        }

        // Process advanced analytics
        if ($this->premium_features['advanced_analytics']) {
            do_action('chatshop_process_analytics');
        }
    }

    /**
     * AJAX test gateway connection
     *
     * @since 1.0.0
     */
    public function ajax_test_gateway()
    {
        // Verify nonce and permissions
        if (!wp_verify_nonce($_POST['nonce'], 'chatshop_admin_nonce') || !current_user_can('manage_options')) {
            wp_die(__('Security check failed', 'chatshop'));
        }

        $gateway_id = sanitize_key($_POST['gateway_id']);

        if (!isset($this->registered_gateways[$gateway_id])) {
            wp_send_json_error(__('Gateway not found', 'chatshop'));
        }

        $gateway = $this->registered_gateways[$gateway_id];

        if (method_exists($gateway, 'test_connection')) {
            $result = $gateway->test_connection();
            wp_send_json($result);
        }

        wp_send_json_error(__('Test not supported for this gateway', 'chatshop'));
    }

    /**
     * AJAX save gateway settings
     *
     * @since 1.0.0
     */
    public function ajax_save_gateway_settings()
    {
        // Verify nonce and permissions
        if (!wp_verify_nonce($_POST['nonce'], 'chatshop_admin_nonce') || !current_user_can('manage_options')) {
            wp_die(__('Security check failed', 'chatshop'));
        }

        $gateway_id = sanitize_key($_POST['gateway_id']);
        $settings = $_POST['settings'] ?? array();

        // Sanitize and encrypt sensitive data
        $sanitized_settings = $this->sanitize_gateway_settings($gateway_id, $settings);

        // Save settings
        $result = update_option("chatshop_{$gateway_id}_options", $sanitized_settings);

        if ($result) {
            wp_send_json_success(__('Settings saved successfully', 'chatshop'));
        } else {
            wp_send_json_error(__('Failed to save settings', 'chatshop'));
        }
    }

    /**
     * Sanitize gateway settings
     *
     * @param string $gateway_id Gateway ID
     * @param array  $settings Settings array
     * @return array Sanitized settings
     * @since 1.0.0
     */
    private function sanitize_gateway_settings($gateway_id, $settings)
    {
        $sanitized = array();

        foreach ($settings as $key => $value) {
            $clean_key = sanitize_key($key);

            // Encrypt sensitive keys
            if (in_array($clean_key, array('secret_key', 'live_secret_key', 'test_secret_key'), true)) {
                $sanitized[$clean_key] = $this->encrypt_api_key($value);
            } else {
                $sanitized[$clean_key] = sanitize_text_field($value);
            }
        }

        return $sanitized;
    }

    /**
     * Encrypt API key
     *
     * @param string $key API key to encrypt
     * @return string Encrypted key
     * @since 1.0.0
     */
    private function encrypt_api_key($key)
    {
        if (empty($key)) {
            return '';
        }

        $encryption_key = wp_salt('auth');
        return openssl_encrypt($key, 'AES-256-CBC', $encryption_key, 0, substr($encryption_key, 0, 16));
    }

    /**
     * Run the loader to execute all hooks
     *
     * @since 1.0.0
     */
    public function run()
    {
        $this->loader->run();
    }

    /**
     * Get the loader
     *
     * @since 1.0.0
     * @return ChatShop_Loader
     */
    public function get_loader()
    {
        return $this->loader;
    }

    /**
     * Get the admin instance
     *
     * @since 1.0.0
     * @return ChatShop_Admin|null
     */
    public function get_admin()
    {
        return $this->admin;
    }

    /**
     * Get the public instance
     *
     * @since 1.0.0
     * @return ChatShop_Public|null
     */
    public function get_public()
    {
        return $this->public;
    }

    /**
     * Get the component loader
     *
     * @since 1.0.0
     * @return ChatShop_Component_Loader|null
     */
    public function get_component_loader()
    {
        return $this->component_loader;
    }

    /**
     * Get the payment manager
     *
     * @since 1.0.0
     * @return ChatShop_Payment_Manager|null
     */
    public function get_payment_manager()
    {
        return $this->payment_manager;
    }

    /**
     * Get registered gateways
     *
     * @since 1.0.0
     * @return array
     */
    public function get_registered_gateways()
    {
        return $this->registered_gateways;
    }

    /**
     * Get premium features status
     *
     * @since 1.0.0
     * @return array
     */
    public function get_premium_features()
    {
        return $this->premium_features;
    }

    /**
     * Check if premium feature is available
     *
     * @param string $feature Feature name
     * @return bool Whether feature is available
     * @since 1.0.0
     */
    public function is_premium_feature_available($feature)
    {
        return isset($this->premium_features[$feature]) && $this->premium_features[$feature];
    }
}

/**
 * Plugin activation hook
 *
 * @since 1.0.0
 */
function chatshop_activate()
{
    if (class_exists('ChatShop\ChatShop_Activator')) {
        ChatShop_Activator::activate();
    }

    // Hook for extensions
    do_action('chatshop_activated');
}

/**
 * Plugin deactivation hook
 *
 * @since 1.0.0
 */
function chatshop_deactivate()
{
    if (class_exists('ChatShop\ChatShop_Deactivator')) {
        ChatShop_Deactivator::deactivate();
    }

    // Hook for extensions
    do_action('chatshop_deactivated');
}

// Register activation and deactivation hooks
register_activation_hook(__FILE__, '\ChatShop\chatshop_activate');
register_deactivation_hook(__FILE__, '\ChatShop\chatshop_deactivate');

/**
 * Begin execution of the plugin
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since 1.0.0
 */
function run_chatshop()
{
    $plugin = ChatShop::instance();
    $plugin->run();
}

// Start the plugin
run_chatshop();

/**
 * Helper function to check if plugin is enabled
 *
 * @since 1.0.0
 * @return bool True if enabled, false otherwise
 */
function chatshop_is_enabled()
{
    $general_options = get_option('chatshop_general_options', array());
    return isset($general_options['plugin_enabled']) ? (bool) $general_options['plugin_enabled'] : true;
}

/**
 * Helper function to get plugin option
 *
 * @since 1.0.0
 * @param string $option_group Option group name
 * @param string $option_name  Option name
 * @param mixed  $default      Default value
 * @return mixed
 */
function chatshop_get_option($option_group, $option_name = '', $default = null)
{
    $options = get_option("chatshop_{$option_group}_options", array());

    if (empty($option_name)) {
        return $options;
    }

    return isset($options[$option_name]) ? $options[$option_name] : $default;
}

/**
 * Helper function to update plugin option
 *
 * @since 1.0.0
 * @param string $option_group Option group name
 * @param string $option_name  Option name
 * @param mixed  $value        Option value
 * @return bool
 */
function chatshop_update_option($option_group, $option_name, $value)
{
    $options = get_option("chatshop_{$option_group}_options", array());
    $options[$option_name] = $value;

    return update_option("chatshop_{$option_group}_options", $options);
}

/**
 * Helper function to get component instance
 *
 * @since 1.0.0
 * @param string $component_id Component identifier
 * @return object|null
 */
function chatshop_get_component($component_id)
{
    $plugin = ChatShop::instance();
    $component_loader = $plugin->get_component_loader();

    if ($component_loader) {
        return $component_loader->get_component_instance($component_id);
    }

    return null;
}

/**
 * Helper function to get payment manager instance
 *
 * @since 1.0.0
 * @return ChatShop_Payment_Manager|null
 */
function chatshop_get_payment_manager()
{
    $plugin = ChatShop::instance();
    $payment_manager = $plugin->get_payment_manager();

    if (!$payment_manager) {
        chatshop_log('Payment manager not available', 'warning');
    }

    return $payment_manager;
}

/**
 * Helper function to get registered payment gateways
 *
 * @since 1.0.0
 * @return array
 */
function chatshop_get_payment_gateways()
{
    $plugin = ChatShop::instance();
    return $plugin->get_registered_gateways();
}

/**
 * Helper function to check if premium feature is available
 *
 * @since 1.0.0
 * @param string $feature Feature name
 * @return bool
 */
function chatshop_is_premium_feature_available($feature)
{
    $plugin = ChatShop::instance();
    return $plugin->is_premium_feature_available($feature);
}

/**
 * Helper function to process payment
 *
 * @since 1.0.0
 * @param string $gateway_id Gateway ID
 * @param float  $amount Payment amount
 * @param string $currency Currency code
 * @param array  $customer_data Customer information
 * @param array  $options Additional options
 * @return array Payment result
 */
function chatshop_process_payment($gateway_id, $amount, $currency, $customer_data, $options = array())
{
    $gateways = chatshop_get_payment_gateways();

    if (!isset($gateways[$gateway_id])) {
        return array(
            'success' => false,
            'message' => __('Payment gateway not found', 'chatshop'),
        );
    }

    return $gateways[$gateway_id]->process_payment($amount, $currency, $customer_data, $options);
}

/**
 * Helper function to verify payment
 *
 * @since 1.0.0
 * @param string $gateway_id Gateway ID
 * @param string $reference Transaction reference
 * @return array Verification result
 */
function chatshop_verify_payment($gateway_id, $reference)
{
    $gateways = chatshop_get_payment_gateways();

    if (!isset($gateways[$gateway_id])) {
        return array(
            'success' => false,
            'message' => __('Payment gateway not found', 'chatshop'),
        );
    }

    return $gateways[$gateway_id]->verify_transaction($reference);
}
