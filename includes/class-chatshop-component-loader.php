<?php

/**
 * Component Loader for ChatShop Plugin
 *
 * @package ChatShop
 * @subpackage ChatShop/includes
 * @since 1.0.0
 */

namespace ChatShop;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Component Loader Class
 *
 * Handles automatic loading and initialization of plugin components
 *
 * @since 1.0.0
 */
class ChatShop_Component_Loader
{
    /**
     * Components directory path
     *
     * @since 1.0.0
     * @var string
     */
    private $components_dir;

    /**
     * Loaded component instances
     *
     * @since 1.0.0
     * @var array
     */
    private $instances = array();

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        $this->components_dir = CHATSHOP_PLUGIN_DIR . 'components/';
        $this->register_core_components();
    }

    /**
     * Load all registered components
     *
     * @since 1.0.0
     */
    public function load_components()
    {
        // Get components in load order (considering dependencies)
        $load_order = ChatShop_Component_Registry::get_load_order();

        foreach ($load_order as $component_id) {
            $this->load_component($component_id);
        }

        // Hook for manual component registration
        do_action('chatshop_components_loaded');
    }

    /**
     * Load a specific component
     *
     * @since 1.0.0
     * @param string $component_id Component identifier
     * @return bool|object
     */
    public function load_component($component_id)
    {
        // Check if already loaded
        if (ChatShop_Component_Registry::is_loaded($component_id)) {
            return ChatShop_Component_Registry::get_loaded_component($component_id);
        }

        // Get component configuration
        $component = ChatShop_Component_Registry::get_component($component_id);

        if (!$component) {
            chatshop_log("Component not found: {$component_id}", 'error');
            return false;
        }

        // Check if disabled
        if (!$component['enabled']) {
            chatshop_log("Component disabled: {$component_id}", 'info');
            return false;
        }

        // Check dependencies
        if (!ChatShop_Component_Registry::dependencies_met($component_id)) {
            chatshop_log("Dependencies not met for component: {$component_id}", 'error');
            return false;
        }

        // Load component file
        $file_path = $this->get_component_file_path($component['file']);

        if (!file_exists($file_path)) {
            chatshop_log("Component file not found: {$file_path}", 'error');
            return false;
        }

        require_once $file_path;

        // Check if class exists
        $class_name = $this->get_full_class_name($component['class']);

        if (!class_exists($class_name)) {
            chatshop_log("Component class not found: {$class_name}", 'error');
            return false;
        }

        // Instantiate component
        try {
            $instance = new $class_name();

            // Initialize if method exists
            if (method_exists($instance, 'init')) {
                $instance->init();
            }

            // Mark as loaded
            ChatShop_Component_Registry::mark_loaded($component_id, $instance);
            $this->instances[$component_id] = $instance;

            chatshop_log("Component loaded successfully: {$component_id}", 'info');

            // Fire action for component loaded
            do_action("chatshop_component_loaded_{$component_id}", $instance);
            do_action('chatshop_component_loaded', $component_id, $instance);

            return $instance;
        } catch (Exception $e) {
            chatshop_log("Error loading component {$component_id}: " . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Register core components
     *
     * @since 1.0.0
     */
    private function register_core_components()
    {
        // Admin component
        ChatShop_Component_Registry::register_component('admin', array(
            'name'        => 'Admin Interface',
            'description' => 'WordPress admin interface for ChatShop',
            'file'        => 'admin/class-chatshop-admin-component.php',
            'class'       => 'ChatShop_Admin_Component',
            'priority'    => 5
        ));

        // Payment component
        ChatShop_Component_Registry::register_component('payment', array(
            'name'        => 'Payment System',
            'description' => 'Payment processing and gateway management',
            'file'        => 'payment/class-chatshop-payment-component.php',
            'class'       => 'ChatShop_Payment_Component',
            'priority'    => 10
        ));

        // WhatsApp component
        ChatShop_Component_Registry::register_component('whatsapp', array(
            'name'        => 'WhatsApp Integration',
            'description' => 'WhatsApp Business API integration',
            'file'        => 'whatsapp/class-chatshop-whatsapp-component.php',
            'class'       => 'ChatShop_WhatsApp_Component',
            'priority'    => 15
        ));

        // Analytics component
        ChatShop_Component_Registry::register_component('analytics', array(
            'name'        => 'Analytics',
            'description' => 'Analytics and reporting functionality',
            'file'        => 'analytics/class-chatshop-analytics-component.php',
            'class'       => 'ChatShop_Analytics_Component',
            'dependencies' => array('payment', 'whatsapp'),
            'priority'    => 20
        ));

        // Campaign component
        ChatShop_Component_Registry::register_component('campaign', array(
            'name'        => 'Campaign Management',
            'description' => 'WhatsApp marketing campaigns',
            'file'        => 'campaign/class-chatshop-campaign-component.php',
            'class'       => 'ChatShop_Campaign_Component',
            'dependencies' => array('whatsapp'),
            'priority'    => 25
        ));
    }

    /**
     * Get component file path
     *
     * @since 1.0.0
     * @param string $file Relative file path
     * @return string
     */
    private function get_component_file_path($file)
    {
        // Handle absolute paths
        if (strpos($file, '/') === 0) {
            return CHATSHOP_PLUGIN_DIR . ltrim($file, '/');
        }

        // Handle relative paths from components directory
        return $this->components_dir . $file;
    }

    /**
     * Get full class name with namespace
     *
     * @since 1.0.0
     * @param string $class_name Class name
     * @return string
     */
    private function get_full_class_name($class_name)
    {
        // Add namespace if not present
        if (strpos($class_name, '\\') === false) {
            return '\\ChatShop\\' . $class_name;
        }

        return $class_name;
    }

    /**
     * Get component instance
     *
     * @since 1.0.0
     * @param string $component_id Component identifier
     * @return object|null
     */
    public function get_component_instance($component_id)
    {
        return isset($this->instances[$component_id]) ? $this->instances[$component_id] : null;
    }

    /**
     * Get all loaded component instances
     *
     * @since 1.0.0
     * @return array
     */
    public function get_all_instances()
    {
        return $this->instances;
    }

    /**
     * Discover components from directory
     *
     * @since 1.0.0
     * @param string $directory Directory path
     */
    public function discover_components($directory = null)
    {
        if (!$directory) {
            $directory = $this->components_dir;
        }

        if (!is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php' && strpos($file->getFilename(), 'component.php') !== false) {
                $this->try_auto_register_component($file->getPathname());
            }
        }
    }

    /**
     * Try to auto-register a component from file
     *
     * @since 1.0.0
     * @param string $file_path Full file path
     */
    private function try_auto_register_component($file_path)
    {
        // Read file content to find component header
        $content = file_get_contents($file_path);

        if (preg_match('/\/\*\*\s*\*\s*Component:\s*(.+?)\s*\*/s', $content, $matches)) {
            $header = $matches[1];

            // Parse component header
            if (preg_match_all('/\*\s*(\w+):\s*(.+)$/m', $header, $header_matches, PREG_SET_ORDER)) {
                $config = array();

                foreach ($header_matches as $match) {
                    $key = strtolower($match[1]);
                    $value = trim($match[2]);

                    if ($key === 'dependencies') {
                        $value = array_map('trim', explode(',', $value));
                    }

                    $config[$key] = $value;
                }

                // Register component if valid
                if (isset($config['id'], $config['class'])) {
                    $config['file'] = str_replace($this->components_dir, '', $file_path);
                    ChatShop_Component_Registry::register_component($config['id'], $config);
                }
            }
        }
    }
}

/**
 * Helper function for logging (fallback if logger not available)
 *
 * @since 1.0.0
 * @param string $message Log message
 * @param string $level   Log level
 */
if (!function_exists('chatshop_log')) {
    function chatshop_log($message, $level = 'info')
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("[ChatShop] [{$level}] {$message}");
        }
    }
}
