<?php

/**
 * Component Loader Class
 *
 * Handles loading and managing plugin components.
 *
 * @package ChatShop
 * @since 1.0.0
 */

namespace ChatShop;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ChatShop Component Loader Class
 *
 * @since 1.0.0
 */
class ChatShop_Component_Loader
{
    /**
     * Component registry instance
     *
     * @var ChatShop_Component_Registry
     * @since 1.0.0
     */
    private $registry;

    /**
     * Loaded component instances
     *
     * @var array
     * @since 1.0.0
     */
    private $component_instances = array();

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        $this->registry = new ChatShop_Component_Registry();
        $this->register_core_components();
    }

    /**
     * Register core components
     *
     * @since 1.0.0
     */
    private function register_core_components()
    {
        // Register payment component
        $this->registry->register_component(array(
            'id' => 'payment',
            'name' => __('Payment System', 'chatshop'),
            'description' => __('Handles payment processing and gateway management', 'chatshop'),
            'path' => CHATSHOP_PLUGIN_DIR . 'components/payment/',
            'main_file' => 'class-chatshop-payment-manager.php',
            'class_name' => 'ChatShop_Payment_Manager',
            'dependencies' => array(),
            'version' => '1.0.0',
            'enabled' => true
        ));

        // Register WhatsApp component (when created)
        $this->registry->register_component(array(
            'id' => 'whatsapp',
            'name' => __('WhatsApp Integration', 'chatshop'),
            'description' => __('WhatsApp messaging and automation features', 'chatshop'),
            'path' => CHATSHOP_PLUGIN_DIR . 'components/whatsapp/',
            'main_file' => 'class-chatshop-whatsapp-manager.php',
            'class_name' => 'ChatShop_WhatsApp_Manager',
            'dependencies' => array(),
            'version' => '1.0.0',
            'enabled' => false // Disabled until implemented
        ));

        // Register analytics component (when created)
        $this->registry->register_component(array(
            'id' => 'analytics',
            'name' => __('Analytics & Reporting', 'chatshop'),
            'description' => __('Track conversions and generate reports', 'chatshop'),
            'path' => CHATSHOP_PLUGIN_DIR . 'components/analytics/',
            'main_file' => 'class-chatshop-analytics-manager.php',
            'class_name' => 'ChatShop_Analytics_Manager',
            'dependencies' => array('payment'),
            'version' => '1.0.0',
            'enabled' => false // Disabled until implemented
        ));

        // Allow other plugins/themes to register components
        do_action('chatshop_register_components', $this->registry);
    }

    /**
     * Load all enabled components
     *
     * @since 1.0.0
     */
    public function load_components()
    {
        $components = $this->registry->get_enabled_components();

        foreach ($components as $component) {
            $this->load_component($component);
        }

        // Hook for post-component loading
        do_action('chatshop_components_loaded', $this->component_instances);
    }

    /**
     * Load a specific component
     *
     * @since 1.0.0
     * @param array $component Component configuration
     * @return bool True if loaded successfully, false otherwise
     */
    public function load_component($component)
    {
        $component_id = $component['id'];

        // Check if already loaded
        if (isset($this->component_instances[$component_id])) {
            return true;
        }

        // Check dependencies first
        if (!$this->check_dependencies($component)) {
            $this->log_error("Component '{$component_id}' dependencies not met");
            return false;
        }

        // Build file path
        $file_path = trailingslashit($component['path']) . $component['main_file'];

        // Check if file exists
        if (!file_exists($file_path)) {
            $this->log_error("Component file not found: {$file_path}");
            return false;
        }

        // Include the file
        require_once $file_path;

        // Check if class exists
        $class_name = "\\ChatShop\\{$component['class_name']}";
        if (!class_exists($class_name)) {
            $this->log_error("Component class not found: {$class_name}");
            return false;
        }

        // Instantiate the component
        try {
            $this->component_instances[$component_id] = new $class_name();
            $this->log_info("Component '{$component_id}' loaded successfully");

            // Initialize component if it has an init method
            if (method_exists($this->component_instances[$component_id], 'init')) {
                $this->component_instances[$component_id]->init();
            }

            return true;
        } catch (\Exception $e) {
            $this->log_error("Failed to instantiate component '{$component_id}': " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check component dependencies
     *
     * @since 1.0.0
     * @param array $component Component configuration
     * @return bool True if dependencies are met, false otherwise
     */
    private function check_dependencies($component)
    {
        if (empty($component['dependencies'])) {
            return true;
        }

        foreach ($component['dependencies'] as $dependency) {
            if (!isset($this->component_instances[$dependency])) {
                // Try to load the dependency
                $dependency_component = $this->registry->get_component($dependency);
                if (!$dependency_component || !$this->load_component($dependency_component)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Get component instance
     *
     * @since 1.0.0
     * @param string $component_id Component identifier
     * @return object|null Component instance or null if not found
     */
    public function get_component_instance($component_id)
    {
        return isset($this->component_instances[$component_id]) ? $this->component_instances[$component_id] : null;
    }

    /**
     * Get all component instances
     *
     * @since 1.0.0
     * @return array Array of component instances
     */
    public function get_all_instances()
    {
        return $this->component_instances;
    }

    /**
     * Enable a component
     *
     * @since 1.0.0
     * @param string $component_id Component identifier
     * @return bool True if enabled successfully, false otherwise
     */
    public function enable_component($component_id)
    {
        $component = $this->registry->get_component($component_id);
        if (!$component) {
            return false;
        }

        // Update registry
        $this->registry->enable_component($component_id);

        // Load the component
        return $this->load_component($component);
    }

    /**
     * Disable a component
     *
     * @since 1.0.0
     * @param string $component_id Component identifier
     * @return bool True if disabled successfully, false otherwise
     */
    public function disable_component($component_id)
    {
        // Unload component instance
        if (isset($this->component_instances[$component_id])) {
            // Call cleanup method if it exists
            if (method_exists($this->component_instances[$component_id], 'cleanup')) {
                $this->component_instances[$component_id]->cleanup();
            }
            unset($this->component_instances[$component_id]);
        }

        // Update registry
        return $this->registry->disable_component($component_id);
    }

    /**
     * Get component registry
     *
     * @since 1.0.0
     * @return ChatShop_Component_Registry Registry instance
     */
    public function get_registry()
    {
        return $this->registry;
    }

    /**
     * Log error message
     *
     * @since 1.0.0
     * @param string $message Error message
     */
    private function log_error($message)
    {
        if (function_exists('chatshop_log')) {
            chatshop_log($message, 'error');
        } else {
            error_log("ChatShop Component Loader: {$message}");
        }
    }

    /**
     * Log info message
     *
     * @since 1.0.0
     * @param string $message Info message
     */
    private function log_info($message)
    {
        if (function_exists('chatshop_log')) {
            chatshop_log($message, 'info');
        } else {
            error_log("ChatShop Component Loader: {$message}");
        }
    }
}
