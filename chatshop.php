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
if (! defined('WPINC')) {
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
        $this->set_locale();
        $this->check_requirements();
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
        // Core includes
        $includes_path = CHATSHOP_PLUGIN_DIR . 'includes/';

        // Load core classes
        if (file_exists($includes_path . 'class-chatshop-activator.php')) {
            require_once $includes_path . 'class-chatshop-activator.php';
        }

        if (file_exists($includes_path . 'class-chatshop-deactivator.php')) {
            require_once $includes_path . 'class-chatshop-deactivator.php';
        }

        if (file_exists($includes_path . 'class-chatshop-i18n.php')) {
            require_once $includes_path . 'class-chatshop-i18n.php';
        }

        // Load admin class if in admin
        if (is_admin()) {
            $admin_path = CHATSHOP_PLUGIN_DIR . 'admin/';
            if (file_exists($admin_path . 'class-chatshop-admin.php')) {
                require_once $admin_path . 'class-chatshop-admin.php';
            }
        }

        // Load public class
        $public_path = CHATSHOP_PLUGIN_DIR . 'public/';
        if (file_exists($public_path . 'class-chatshop-public.php')) {
            require_once $public_path . 'class-chatshop-public.php';
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
     * Define the locale for internationalization
     *
     * @since 1.0.0
     */
    private function set_locale()
    {
        $i18n_path = CHATSHOP_PLUGIN_DIR . 'includes/class-chatshop-i18n.php';
        if (file_exists($i18n_path)) {
            if (!class_exists('\ChatShop\ChatShop_i18n')) {
                require_once $i18n_path;
            }
            if (class_exists('\ChatShop\ChatShop_i18n')) {
                $plugin_i18n = new ChatShop_i18n();
                $this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
            }
        } else {
            // Fallback to built-in i18n class
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
        $activator_path = CHATSHOP_PLUGIN_DIR . 'includes/class-chatshop-activator.php';
        if (file_exists($activator_path)) {
            if (!class_exists('\ChatShop\ChatShop_Activator')) {
                require_once $activator_path;
            }

            if (class_exists('\ChatShop\ChatShop_Activator')) {
                $errors = ChatShop_Activator::get_activation_errors();

                if (! empty($errors)) {
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
        }
    }

    /**
     * Register all of the hooks related to the admin area functionality
     *
     * @since 1.0.0
     */
    private function define_admin_hooks()
    {
        if (is_admin()) {
            $admin_path = CHATSHOP_PLUGIN_DIR . 'admin/class-chatshop-admin.php';
            if (file_exists($admin_path) && class_exists('\ChatShop\ChatShop_Admin')) {
                $this->admin = new ChatShop_Admin();

                // Admin initialization
                $this->loader->add_action('admin_enqueue_scripts', $this->admin, 'enqueue_styles');
                $this->loader->add_action('admin_enqueue_scripts', $this->admin, 'enqueue_scripts');

                // Admin menu hooks
                $this->loader->add_action('admin_menu', $this->admin, 'add_admin_menu');
                $this->loader->add_action('admin_init', $this->admin, 'init_settings');

                // AJAX hooks for admin
                $this->loader->add_action('wp_ajax_chatshop_admin_action', $this->admin, 'handle_ajax_request');
            }
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
        if (file_exists($public_path) && class_exists('\ChatShop\ChatShop_Public')) {
            $this->public = new ChatShop_Public();

            // Public scripts and styles
            $this->loader->add_action('wp_enqueue_scripts', $this->public, 'enqueue_styles');
            $this->loader->add_action('wp_enqueue_scripts', $this->public, 'enqueue_scripts');

            // Shortcode registration
            $this->loader->add_action('init', $this->public, 'register_shortcodes');

            // Public AJAX hooks (for non-logged in users)
            $this->loader->add_action('wp_ajax_nopriv_chatshop_public_action', $this->public, 'handle_ajax_request');
            $this->loader->add_action('wp_ajax_chatshop_public_action', $this->public, 'handle_ajax_request');
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
        do_action('chatshop_init');
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
    }

    /**
     * Initialize plugin components
     *
     * @since 1.0.0
     */
    private function init_components()
    {
        // Load component loader and registry
        $includes_path = CHATSHOP_PLUGIN_DIR . 'includes/';

        // First check if component registry exists
        if (file_exists($includes_path . 'class-chatshop-component-registry.php')) {
            require_once $includes_path . 'class-chatshop-component-registry.php';
        }

        // Then load component loader if both files exist
        if (
            file_exists($includes_path . 'class-chatshop-component-loader.php') &&
            class_exists('\ChatShop\ChatShop_Component_Registry')
        ) {
            require_once $includes_path . 'class-chatshop-component-loader.php';

            if (class_exists('\ChatShop\ChatShop_Component_Loader')) {
                $component_loader = new ChatShop_Component_Loader();
                $component_loader->load_components();
            }
        }

        // Hook for manual component loading if automatic loading fails
        do_action('chatshop_load_components');
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
     * Add hook to the collection
     *
     * @since 1.0.0
     * @param array  $hooks         The collection of hooks (actions or filters)
     * @param string $hook          The name of the WordPress hook
     * @param object $component     A reference to the instance of the object on which the hook is defined
     * @param string $callback      The name of the function definition on the $component
     * @param int    $priority      The priority at which the function should be fired
     * @param int    $accepted_args The number of arguments that should be passed to the $callback
     * @return array The collection of hooks
     */
    private function add($hooks, $hook, $component, $callback, $priority, $accepted_args)
    {
        $hooks[] = array(
            'hook'          => $hook,
            'component'     => $component,
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $accepted_args,
        );

        return $hooks;
    }

    /**
     * Register the filters and actions with WordPress
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
 * ChatShop i18n Class
 *
 * Handles internationalization functionality
 *
 * @since 1.0.0
 */
class ChatShop_i18n
{

    /**
     * Load the plugin text domain for translation
     *
     * @since 1.0.0
     */
    public function load_plugin_textdomain()
    {
        load_plugin_textdomain(
            'chatshop',
            false,
            dirname(CHATSHOP_PLUGIN_BASENAME) . '/languages/'
        );
    }
}

/**
 * Plugin activation hook
 *
 * @since 1.0.0
 */
function chatshop_activate()
{
    $activator_path = CHATSHOP_PLUGIN_DIR . 'includes/class-chatshop-activator.php';
    if (file_exists($activator_path)) {
        require_once $activator_path;
        if (class_exists('\ChatShop\ChatShop_Activator')) {
            ChatShop_Activator::activate();
        }
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
    $deactivator_path = CHATSHOP_PLUGIN_DIR . 'includes/class-chatshop-deactivator.php';
    if (file_exists($deactivator_path)) {
        require_once $deactivator_path;
        if (class_exists('\ChatShop\ChatShop_Deactivator')) {
            ChatShop_Deactivator::deactivate();
        }
    }

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

// Initialize and run the plugin
add_action('plugins_loaded', function () {
    $chatshop = chatshop();
    $chatshop->run();
}, 0);
