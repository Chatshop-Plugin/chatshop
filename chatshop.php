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
     * The component loader instance
     *
     * @var ChatShop_Component_Loader
     * @since 1.0.0
     */
    private $component_loader;

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
        $this->init_component_system();
        $this->set_locale();
        $this->check_requirements();
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
     * Load required dependencies
     *
     * @since 1.0.0
     */
    private function load_dependencies()
    {
        // Load component registry first
        require_once CHATSHOP_PLUGIN_DIR . 'includes/class-chatshop-component-registry.php';

        // Load component loader
        require_once CHATSHOP_PLUGIN_DIR . 'includes/class-chatshop-component-loader.php';
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
     * Initialize the component system
     *
     * @since 1.0.0
     */
    private function init_component_system()
    {
        $this->component_loader = new ChatShop_Component_Loader();
    }

    /**
     * Define the locale for internationalization
     *
     * @since 1.0.0
     */
    private function set_locale()
    {
        $this->loader->add_action('plugins_loaded', $this, 'load_textdomain');
    }

    /**
     * Load the plugin text domain for translation
     *
     * @since 1.0.0
     */
    public function load_textdomain()
    {
        load_plugin_textdomain(
            'chatshop',
            false,
            dirname(CHATSHOP_PLUGIN_BASENAME) . '/languages/'
        );
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
        require_once CHATSHOP_PLUGIN_DIR . 'includes/class-chatshop-activator.php';
        $errors = ChatShop_Activator::get_activation_errors();

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
     * Initialize WordPress hooks
     *
     * @since 1.0.0
     */
    private function init_hooks()
    {
        $this->loader->add_action('init', $this, 'init');
    }

    /**
     * Init ChatShop when WordPress initializes
     *
     * @since 1.0.0
     */
    public function init()
    {
        // Hook for extensions
        do_action('chatshop_init');
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
     * Get the component loader
     *
     * @since 1.0.0
     * @return ChatShop_Component_Loader
     */
    public function get_component_loader()
    {
        return $this->component_loader;
    }

    /**
     * Get a specific component instance
     *
     * @param string $component_id Component ID
     * @return mixed|null
     * @since 1.0.0
     */
    public function get_component($component_id)
    {
        return $this->component_loader ? $this->component_loader->get_component($component_id) : null;
    }

    /**
     * Check if premium features are available
     *
     * @return bool
     * @since 1.0.0
     */
    public function is_premium()
    {
        return chatshop_is_premium();
    }
}

/**
 * ChatShop Loader Class
 *
 * Manages all hooks and filters for the plugin
 *
 * @since 1.0.0
 */
class ChatShop_Loader
{
    /**
     * The array of actions registered with WordPress
     *
     * @var array
     * @since 1.0.0
     */
    protected $actions = array();

    /**
     * The array of filters registered with WordPress
     *
     * @var array
     * @since 1.0.0
     */
    protected $filters = array();

    /**
     * Add a new action to the collection
     *
     * @since 1.0.0
     * @param string $hook          The name of the WordPress action
     * @param object $component     A reference to the instance of the object on which the action is defined
     * @param string $callback      The name of the function definition on the $component
     * @param int    $priority      Optional. The priority at which the function should be fired
     * @param int    $accepted_args Optional. The number of arguments that should be passed to the $callback
     */
    public function add_action($hook, $component, $callback, $priority = 10, $accepted_args = 1)
    {
        $this->actions = $this->add($this->actions, $hook, $component, $callback, $priority, $accepted_args);
    }

    /**
     * Add a new filter to the collection
     *
     * @since 1.0.0
     * @param string $hook          The name of the WordPress filter
     * @param object $component     A reference to the instance of the object on which the filter is defined
     * @param string $callback      The name of the function definition on the $component
     * @param int    $priority      Optional. The priority at which the function should be fired
     * @param int    $accepted_args Optional. The number of arguments that should be passed to the $callback
     */
    public function add_filter($hook, $component, $callback, $priority = 10, $accepted_args = 1)
    {
        $this->filters = $this->add($this->filters, $hook, $component, $callback, $priority, $accepted_args);
    }

    /**
     * Add hook to the appropriate collection
     *
     * @since 1.0.0
     * @param array  $hooks         The collection of hooks that is being registered
     * @param string $hook          The name of the WordPress hook
     * @param object $component     A reference to the instance of the object on which the hook is defined
     * @param string $callback      The name of the function definition on the $component
     * @param int    $priority      The priority at which the function should be fired
     * @param int    $accepted_args The number of arguments that should be passed to the $callback
     * @return array
     */
    private function add($hooks, $hook, $component, $callback, $priority, $accepted_args)
    {
        $hooks[] = array(
            'hook'          => $hook,
            'component'     => $component,
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $accepted_args
        );

        return $hooks;
    }

    /**
     * Register hooks with WordPress
     *
     * @since 1.0.0
     */
    public function run()
    {
        foreach ($this->filters as $hook) {
            add_filter($hook['hook'], array($hook['component'], $hook['callback']), $hook['priority'], $hook['accepted_args']);
        }

        foreach ($this->actions as $hook) {
            add_action($hook['hook'], array($hook['component'], $hook['callback']), $hook['priority'], $hook['accepted_args']);
        }
    }
}

/**
 * Plugin activation hook
 *
 * @since 1.0.0
 */
function chatshop_activate()
{
    require_once CHATSHOP_PLUGIN_DIR . 'includes/class-chatshop-activator.php';
    ChatShop_Activator::activate();

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
    require_once CHATSHOP_PLUGIN_DIR . 'includes/class-chatshop-deactivator.php';
    ChatShop_Deactivator::deactivate();

    // Hook for extensions
    do_action('chatshop_deactivated');
}

// Register activation hook
register_activation_hook(__FILE__, __NAMESPACE__ . '\chatshop_activate');

// Register deactivation hook
register_deactivation_hook(__FILE__, __NAMESPACE__ . '\chatshop_deactivate');

/**
 * Initialize the plugin
 *
 * @since 1.0.0
 * @return ChatShop
 */
function chatshop()
{
    return ChatShop::instance();
}

/**
 * Check if premium features are available
 *
 * Determines if the user has access to premium features through license validation.
 *
 * @since 1.0.0
 * @return bool True if premium features are available, false otherwise
 */
function chatshop_is_premium()
{
    static $is_premium = null;

    // Cache the result to avoid multiple checks
    if ($is_premium !== null) {
        return $is_premium;
    }

    // Allow override via filter for development/testing
    $override = apply_filters('chatshop_premium_override', null);
    if ($override !== null) {
        $is_premium = (bool) $override;
        return $is_premium;
    }

    // Check for premium license key
    $license_key = get_option('chatshop_license_key', '');
    if (empty($license_key)) {
        $is_premium = false;
        return $is_premium;
    }

    // Validate license key format (basic check)
    if (!chatshop_validate_license_format($license_key)) {
        $is_premium = false;
        return $is_premium;
    }

    // Check license status from transient cache
    $license_status = get_transient('chatshop_license_status');
    if ($license_status !== false) {
        $is_premium = ($license_status === 'valid');
        return $is_premium;
    }

    // Validate license with remote server
    $is_premium = chatshop_validate_license_remote($license_key);

    // Cache the result for 24 hours
    set_transient('chatshop_license_status', $is_premium ? 'valid' : 'invalid', DAY_IN_SECONDS);

    return $is_premium;
}

/**
 * Validate license key format
 *
 * @param string $license_key License key to validate
 * @return bool
 * @since 1.0.0
 */
function chatshop_validate_license_format($license_key)
{
    // License format: CHATSHOP-XXXX-XXXX-XXXX-XXXX (32 chars + hyphens)
    $pattern = '/^CHATSHOP-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/';
    return preg_match($pattern, strtoupper($license_key));
}

/**
 * Validate license with remote server
 *
 * @param string $license_key License key to validate
 * @return bool
 * @since 1.0.0
 */
function chatshop_validate_license_remote($license_key)
{
    // For now, return false (free version)
    // In premium version, this would validate against license server
    $validation_url = apply_filters('chatshop_license_validation_url', '');

    if (empty($validation_url)) {
        return false;
    }

    $response = wp_remote_post($validation_url, array(
        'timeout' => 15,
        'body' => array(
            'license_key' => sanitize_text_field($license_key),
            'domain' => esc_url_raw(home_url()),
            'version' => CHATSHOP_VERSION,
        ),
        'headers' => array(
            'User-Agent' => 'ChatShop/' . CHATSHOP_VERSION . '; ' . home_url(),
        ),
    ));

    if (is_wp_error($response)) {
        error_log('ChatShop: License validation failed - ' . $response->get_error_message());
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    return isset($data['valid']) && $data['valid'] === true;
}

/**
 * Get premium features list
 *
 * @return array
 * @since 1.0.0
 */
function chatshop_get_premium_features()
{
    return apply_filters('chatshop_premium_features', array(
        'advanced_analytics',
        'bulk_messaging',
        'custom_payment_links',
        'priority_support',
        'white_label',
        'api_access',
        'advanced_automation',
    ));
}

/**
 * Check if a specific premium feature is available
 *
 * @param string $feature Feature name to check
 * @return bool
 * @since 1.0.0
 */
function chatshop_has_premium_feature($feature)
{
    if (!chatshop_is_premium()) {
        return false;
    }

    $premium_features = chatshop_get_premium_features();
    return in_array($feature, $premium_features, true);
}

// Initialize and run the plugin
add_action('plugins_loaded', function () {
    $chatshop = chatshop();
    $chatshop->run();
}, 0);
