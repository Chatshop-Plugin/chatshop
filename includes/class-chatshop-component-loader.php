<?php

/**
 * Component Loader Class
 *
 * File: includes/class-chatshop-component-loader.php
 * 
 * Handles loading and managing plugin components.
 * This is the CORRECT file that should contain ChatShop_Component_Loader class.
 *
 * @package ChatShop
 * @since 1.0.0
 */

namespace ChatShop;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Prevent class redeclaration
if (class_exists('ChatShop\\ChatShop_Component_Loader')) {
    return;
}

/**
 * ChatShop Component Loader Class
 *
 * Manages component loading, initialization, and lifecycle management.
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
     * Component loading order
     *
     * @var array
     * @since 1.0.0
     */
    private $loading_order = array();

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
            'class_name' => 'ChatShop\\ChatShop_Payment_Manager',
            'dependencies' => array(),
            'version' => '1.0.0',
            'enabled' => true,
            'priority' => 1
        ));

        // Register WhatsApp component
        $this->registry->register_component(array(
            'id' => 'whatsapp',
            'name' => __('WhatsApp Integration', 'chatshop'),
            'description' => __('WhatsApp messaging and automation features', 'chatshop'),
            'path' => CHATSHOP_PLUGIN_DIR . 'components/whatsapp/',
            'main_file' => 'class-chatshop-whatsapp-manager.php',
            'class_name' => 'ChatShop\\ChatShop_WhatsApp_Manager',
            'dependencies' => array(),
            'version' => '1.0.0',
            'enabled' => false, // Disabled until fully implemented
            'priority' => 2
        ));

        // Register analytics component
        $this->registry->register_component(array(
            'id' => 'analytics',
            'name' => __('Analytics & Reporting', 'chatshop'),
            'description' => __('Track conversions and generate reports', 'chatshop'),
            'path' => CHATSHOP_PLUGIN_DIR . 'components/analytics/',
            'main_file' => 'class-chatshop-analytics.php',
            'class_name' => 'ChatShop\\ChatShop_Analytics',
            'dependencies' => array('payment'),
            'version' => '1.0.0',
            'enabled' => true,
            'priority' => 3
        ));

        // Register contact management component
        $this->registry->register_component(array(
            'id' => 'contact_manager',
            'name' => __('Contact Management', 'chatshop'),
            'description' => __('Manage WhatsApp contacts with import/export capabilities', 'chatshop'),
            'path' => CHATSHOP_PLUGIN_DIR . 'components/whatsapp/',
            'main_file' => 'class-chatshop-contact-manager.php',
            'class_name' => 'ChatShop\\ChatShop_Contact_Manager',
            'dependencies' => array(),
            'version' => '1.0.0',
            'enabled' => true,
            'priority' => 2
        ));

        // Hook for external component registration
        do_action('chatshop_register_components', $this->registry);
    }

    /**
     * Load all enabled components
     *
     * @since 1.0.0
     * @return bool True if all components loaded successfully
     */
    public function load_components()
    {
        $components = $this->registry->get_enabled_components();

        if (empty($components)) {
            $this->log_info('No enabled components to load');
            return true;
        }

        // Sort components by priority and dependencies
        $sorted_components = $this->sort_components_by_priority($components);

        $loaded_count = 0;
        $total_count = count($sorted_components);

        foreach ($sorted_components as $component) {
            if ($this->load_component($component)) {
                $loaded_count++;
                $this->log_info("Component loaded successfully: {$component['id']}");
            } else {
                $this->log_error("Failed to load component: {$component['id']}");
            }
        }

        $this->log_info("Loaded {$loaded_count}/{$total_count} components");

        // Fire action after all components are loaded
        do_action('chatshop_components_loaded', $this->component_instances);

        return $loaded_count === $total_count;
    }

    /**
     * Load a single component
     *
     * @since 1.0.0
     * @param array $component Component configuration
     * @return bool True if loaded successfully, false otherwise
     */
    private function load_component($component)
    {
        // Skip if already loaded
        if (isset($this->component_instances[$component['id']])) {
            return true;
        }

        // Check dependencies first
        if (!$this->check_dependencies($component)) {
            $this->log_error("Component dependencies not met: {$component['id']}");
            return false;
        }

        // Construct full path to component file
        $file_path = trailingslashit($component['path']) . $component['main_file'];

        // Check if file exists
        if (!file_exists($file_path)) {
            $this->log_error("Component file not found: {$file_path}");
            return false;
        }

        // Load the component file
        try {
            require_once $file_path;

            // Check if class exists
            $class_name = $component['class_name'];
            if (!class_exists($class_name)) {
                $this->log_error("Component class not found: {$class_name}");
                return false;
            }

            // Instantiate the component
            $instance = new $class_name();

            // Store the instance
            $this->component_instances[$component['id']] = $instance;

            // Call activation if component supports it
            if (method_exists($instance, 'activate')) {
                $instance->activate();
            }

            return true;
        } catch (Exception $e) {
            $this->log_error("Error loading component {$component['id']}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Sort components by priority and resolve dependencies
     *
     * @since 1.0.0
     * @param array $components Array of component configurations
     * @return array Sorted array of components
     */
    private function sort_components_by_priority($components)
    {
        // First, sort by priority
        usort($components, function ($a, $b) {
            return ($a['priority'] ?? 10) - ($b['priority'] ?? 10);
        });

        // Then resolve dependencies using topological sort
        return $this->resolve_dependencies($components);
    }

    /**
     * Resolve component dependencies using topological sort
     *
     * @since 1.0.0
     * @param array $components Array of component configurations
     * @return array Sorted array respecting dependencies
     */
    private function resolve_dependencies($components)
    {
        $resolved = array();
        $unresolved = array();

        foreach ($components as $component) {
            $this->resolve_component_dependencies($component, $components, $resolved, $unresolved);
        }

        return $resolved;
    }

    /**
     * Recursively resolve a single component's dependencies
     *
     * @since 1.0.0
     * @param array $component Current component
     * @param array $all_components All available components
     * @param array &$resolved Resolved components (by reference)
     * @param array &$unresolved Currently being resolved (by reference)
     */
    private function resolve_component_dependencies($component, $all_components, &$resolved, &$unresolved)
    {
        // Check if already resolved
        foreach ($resolved as $resolved_component) {
            if ($resolved_component['id'] === $component['id']) {
                return;
            }
        }

        // Check for circular dependencies
        foreach ($unresolved as $unresolved_component) {
            if ($unresolved_component['id'] === $component['id']) {
                $this->log_error("Circular dependency detected for component: {$component['id']}");
                return;
            }
        }

        // Add to unresolved
        $unresolved[] = $component;

        // Resolve dependencies first
        if (!empty($component['dependencies'])) {
            foreach ($component['dependencies'] as $dependency_id) {
                // Find the dependency component
                $dependency_component = null;
                foreach ($all_components as $comp) {
                    if ($comp['id'] === $dependency_id) {
                        $dependency_component = $comp;
                        break;
                    }
                }

                if ($dependency_component) {
                    $this->resolve_component_dependencies($dependency_component, $all_components, $resolved, $unresolved);
                } else {
                    $this->log_error("Dependency not found: {$dependency_id} for component {$component['id']}");
                }
            }
        }

        // Remove from unresolved and add to resolved
        $unresolved = array_filter($unresolved, function ($comp) use ($component) {
            return $comp['id'] !== $component['id'];
        });

        $resolved[] = $component;
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

        foreach ($component['dependencies'] as $dependency_id) {
            if (!isset($this->component_instances[$dependency_id])) {
                // Try to load the dependency
                $dependency_component = $this->registry->get_component($dependency_id);
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
     * Check if component is loaded
     *
     * @since 1.0.0
     * @param string $component_id Component identifier
     * @return bool True if loaded, false otherwise
     */
    public function is_component_loaded($component_id)
    {
        return isset($this->component_instances[$component_id]);
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
            // Call deactivation method if it exists
            if (method_exists($this->component_instances[$component_id], 'deactivate')) {
                $this->component_instances[$component_id]->deactivate();
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
     * Get loaded components count
     *
     * @since 1.0.0
     * @return int Number of loaded components
     */
    public function get_loaded_count()
    {
        return count($this->component_instances);
    }

    /**
     * Get component loading order
     *
     * @since 1.0.0
     * @return array Component loading order
     */
    public function get_loading_order()
    {
        return $this->loading_order;
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
