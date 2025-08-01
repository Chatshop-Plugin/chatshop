<?php

/**
 * Component Loader Class - FIXED VERSION
 *
 * File: includes/class-chatshop-component-loader.php
 * 
 * Handles loading and managing plugin components with improved error handling,
 * proper file path resolution, and enhanced logging capabilities.
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
 * ChatShop Component Loader Class - FIXED VERSION
 *
 * Manages component loading, initialization, and lifecycle management
 * with enhanced error handling and debugging capabilities.
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
     * Loading errors
     *
     * @var array
     * @since 1.0.0
     */
    private $loading_errors = array();

    /**
     * Debug mode status
     *
     * @var bool
     * @since 1.0.0
     */
    private $debug_mode;

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        $this->debug_mode = defined('WP_DEBUG') && WP_DEBUG;
        $this->registry = new ChatShop_Component_Registry();
        $this->register_core_components();

        if ($this->debug_mode) {
            $this->log_info('Component Loader initialized in debug mode');
        }
    }

    /**
     * Register core components with proper configuration
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

        // Register analytics component - FIXED
        $this->registry->register_component(array(
            'id' => 'analytics',
            'name' => __('Analytics & Reporting', 'chatshop'),
            'description' => __('Track conversions and generate reports', 'chatshop'),
            'path' => CHATSHOP_PLUGIN_DIR . 'components/analytics/',
            'main_file' => 'class-chatshop-analytics.php',
            'class_name' => 'ChatShop\\ChatShop_Analytics',
            'dependencies' => array(),
            'version' => '1.0.0',
            'enabled' => true, // Enable by default for development
            'priority' => 3
        ));

        // Register contact management component - FIXED
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

        // Register WhatsApp component
        $this->registry->register_component(array(
            'id' => 'whatsapp',
            'name' => __('WhatsApp Integration', 'chatshop'),
            'description' => __('WhatsApp messaging and automation features', 'chatshop'),
            'path' => CHATSHOP_PLUGIN_DIR . 'components/whatsapp/',
            'main_file' => 'class-chatshop-whatsapp-manager.php',
            'class_name' => 'ChatShop\\ChatShop_WhatsApp_Manager',
            'dependencies' => array('contact_manager'),
            'version' => '1.0.0',
            'enabled' => true, // Enable for development
            'priority' => 4
        ));

        // Hook for external component registration
        do_action('chatshop_register_components', $this->registry);

        $this->log_info('Core components registered successfully');
    }

    /**
     * Load all enabled components with enhanced error handling
     *
     * @since 1.0.0
     * @return bool True if all components loaded successfully
     */
    public function load_components()
    {
        $this->loading_errors = array(); // Reset errors
        $components = $this->registry->get_enabled_components();

        if (empty($components)) {
            $this->log_info('No enabled components to load');
            return true;
        }

        $this->log_info('Starting component loading process for ' . count($components) . ' components');

        // Sort components by priority and dependencies
        $sorted_components = $this->sort_components_by_priority($components);

        $loaded_count = 0;
        $total_count = count($sorted_components);

        foreach ($sorted_components as $component) {
            if ($this->load_component($component)) {
                $loaded_count++;
                $this->loading_order[] = $component['id'];
                $this->log_info("✓ Component loaded successfully: {$component['id']}");
            } else {
                $this->log_error("✗ Failed to load component: {$component['id']}");
            }
        }

        $success_rate = $total_count > 0 ? ($loaded_count / $total_count) * 100 : 100;
        $this->log_info("Component loading completed: {$loaded_count}/{$total_count} components loaded ({$success_rate}%)");

        // Fire action after all components are loaded
        do_action('chatshop_components_loaded', $this->component_instances);

        return $loaded_count === $total_count;
    }

    /**
     * Load a single component with comprehensive error handling
     *
     * @since 1.0.0
     * @param array $component Component configuration
     * @return bool True if loaded successfully, false otherwise
     */
    private function load_component($component)
    {
        $component_id = $component['id'];

        try {
            // Skip if already loaded
            if (isset($this->component_instances[$component_id])) {
                $this->log_info("Component already loaded: {$component_id}");
                return true;
            }

            // Check dependencies first
            if (!$this->check_dependencies($component)) {
                $this->add_loading_error($component_id, 'Dependencies not met');
                return false;
            }

            // Validate component file path
            $file_path = $this->get_component_file_path($component);
            if (!$file_path) {
                $this->add_loading_error($component_id, 'Invalid file path configuration');
                return false;
            }

            // Check if file exists and is readable
            if (!file_exists($file_path)) {
                $this->add_loading_error($component_id, "Component file not found: {$file_path}");
                return false;
            }

            if (!is_readable($file_path)) {
                $this->add_loading_error($component_id, "Component file not readable: {$file_path}");
                return false;
            }

            // Load the component file
            require_once $file_path;

            // Check if class exists
            $class_name = $component['class_name'];
            if (!class_exists($class_name)) {
                $this->add_loading_error($component_id, "Component class not found: {$class_name}");
                return false;
            }

            // Instantiate the component
            $instance = new $class_name();

            // Validate instance
            if (!is_object($instance)) {
                $this->add_loading_error($component_id, 'Failed to create component instance');
                return false;
            }

            // Store the instance
            $this->component_instances[$component_id] = $instance;

            // Call activation if component supports it
            if (method_exists($instance, 'activate')) {
                $activation_result = $instance->activate();
                if ($activation_result === false) {
                    $this->add_loading_error($component_id, 'Component activation failed');
                    unset($this->component_instances[$component_id]);
                    return false;
                }
            }

            // Initialize component if method exists
            if (method_exists($instance, 'init')) {
                $instance->init();
            }

            return true;
        } catch (Exception $e) {
            $this->add_loading_error($component_id, "Exception during loading: " . $e->getMessage());
            return false;
        } catch (Error $e) {
            $this->add_loading_error($component_id, "Fatal error during loading: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get proper file path for component
     *
     * @since 1.0.0
     * @param array $component Component configuration
     * @return string|false File path or false on error
     */
    private function get_component_file_path($component)
    {
        if (empty($component['path']) || empty($component['main_file'])) {
            return false;
        }

        // Ensure path has trailing slash
        $path = trailingslashit($component['path']);

        // Construct full file path
        $file_path = $path . $component['main_file'];

        // Security check: ensure file is within plugin directory
        $plugin_dir = defined('CHATSHOP_PLUGIN_DIR') ? CHATSHOP_PLUGIN_DIR : '';
        if (!empty($plugin_dir)) {
            $real_file_path = realpath($file_path);
            $real_plugin_dir = realpath($plugin_dir);

            if ($real_file_path === false || $real_plugin_dir === false) {
                return false;
            }

            if (strpos($real_file_path, $real_plugin_dir) !== 0) {
                $this->log_error("Security violation: Component file outside plugin directory: {$file_path}");
                return false;
            }
        }

        return $file_path;
    }

    /**
     * Check component dependencies
     *
     * @since 1.0.0
     * @param array $component Component configuration
     * @return bool True if dependencies are met
     */
    private function check_dependencies($component)
    {
        if (empty($component['dependencies'])) {
            return true;
        }

        foreach ($component['dependencies'] as $dependency) {
            if (!isset($this->component_instances[$dependency])) {
                $this->log_error("Missing dependency '{$dependency}' for component '{$component['id']}'");
                return false;
            }
        }

        return true;
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

        // Add to unresolved list
        $unresolved[] = $component;

        // Resolve dependencies first
        if (!empty($component['dependencies'])) {
            foreach ($component['dependencies'] as $dependency_id) {
                // Find dependency component
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
                    $this->log_error("Dependency '{$dependency_id}' not found for component '{$component['id']}'");
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
     * Get component instance
     *
     * @since 1.0.0
     * @param string $component_id Component identifier
     * @return object|null Component instance or null if not found
     */
    public function get_component_instance($component_id)
    {
        return isset($this->component_instances[$component_id]) ?
            $this->component_instances[$component_id] : null;
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
     * Get component loading errors
     *
     * @since 1.0.0
     * @return array Array of loading errors
     */
    public function get_loading_errors()
    {
        return $this->loading_errors;
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
     * Add loading error
     *
     * @since 1.0.0
     * @param string $component_id Component identifier
     * @param string $error Error message
     */
    private function add_loading_error($component_id, $error)
    {
        $this->loading_errors[$component_id] = $error;
        $this->log_error("Component '{$component_id}': {$error}");
    }

    /**
     * Log error message with enhanced formatting
     *
     * @since 1.0.0
     * @param string $message Error message
     */
    private function log_error($message)
    {
        $formatted_message = "[ChatShop Component Loader] ERROR: {$message}";

        if (function_exists('chatshop_log')) {
            chatshop_log($formatted_message, 'error');
        } else {
            error_log($formatted_message);
        }

        // Add to WordPress debug log if enabled
        if ($this->debug_mode) {
            error_log($formatted_message);
        }
    }

    /**
     * Log info message with enhanced formatting
     *
     * @since 1.0.0
     * @param string $message Info message
     */
    private function log_info($message)
    {
        $formatted_message = "[ChatShop Component Loader] INFO: {$message}";

        if (function_exists('chatshop_log')) {
            chatshop_log($formatted_message, 'info');
        }

        // Add to WordPress debug log if in debug mode
        if ($this->debug_mode) {
            error_log($formatted_message);
        }
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
            $this->log_error("Cannot enable non-existent component: {$component_id}");
            return false;
        }

        // Update registry
        $this->registry->enable_component($component_id);

        // Load the component if not already loaded
        if (!$this->is_component_loaded($component_id)) {
            return $this->load_component($component);
        }

        return true;
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
            $this->log_info("Component instance unloaded: {$component_id}");
        }

        // Update registry
        return $this->registry->disable_component($component_id);
    }
}
