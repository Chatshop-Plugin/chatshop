<?php

/**
 * Component Registry Class - RECURSION AND CLASS CONFLICT FIXED
 *
 * File: includes/class-chatshop-component-registry.php
 * 
 * CRITICAL FIXES:
 * - Fixed class existence checking to prevent conflicts
 * - Added proper error handling without recursion
 * - Improved validation with fallback mechanisms
 * - Enhanced logging with recursion protection
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
if (class_exists('ChatShop\\ChatShop_Component_Registry')) {
    return;
}

/**
 * ChatShop Component Registry Class - FIXED VERSION
 *
 * Manages component registration, validation, and metadata storage
 * with enhanced error handling and recursion prevention.
 *
 * @since 1.0.0
 */
class ChatShop_Component_Registry
{
    /**
     * Registered components
     *
     * @var array
     * @since 1.0.0
     */
    private $components = array();

    /**
     * Component settings cache
     *
     * @var array
     * @since 1.0.0
     */
    private $settings_cache = array();

    /**
     * Registry initialization flag
     *
     * @var bool
     * @since 1.0.0
     */
    private $initialized = false;

    /**
     * Error tracking for components
     *
     * @var array
     * @since 1.0.0
     */
    private $component_errors = array();

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        $this->init();
    }

    /**
     * Initialize registry
     *
     * @since 1.0.0
     */
    private function init()
    {
        if ($this->initialized) {
            return;
        }

        $this->load_component_settings();
        $this->initialized = true;

        // Safe logging without recursion
        if (defined('WP_DEBUG') && WP_DEBUG && function_exists('error_log')) {
            error_log('ChatShop Component Registry initialized');
        }
    }

    /**
     * Load component settings from database
     *
     * @since 1.0.0
     */
    private function load_component_settings()
    {
        $settings = get_option('chatshop_component_settings', array());
        $this->settings_cache = is_array($settings) ? $settings : array();
    }

    /**
     * Save component settings to database
     *
     * @since 1.0.0
     */
    private function save_component_settings()
    {
        try {
            return update_option('chatshop_component_settings', $this->settings_cache, false);
        } catch (Exception $e) {
            // Fallback error logging
            if (function_exists('error_log')) {
                error_log('ChatShop: Failed to save component settings - ' . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Register a component with comprehensive validation
     *
     * @since 1.0.0
     * @param array $config Component configuration
     * @return bool True if registered successfully, false otherwise
     */
    public function register_component($config)
    {
        try {
            // Validate configuration
            if (!$this->validate_component_config($config)) {
                $this->add_component_error($config['id'] ?? 'unknown', 'Invalid component configuration');
                return false;
            }

            $component_id = $config['id'];

            // Check if already registered
            if (isset($this->components[$component_id])) {
                $this->log_info("Component already registered: {$component_id}");
                return true;
            }

            // Validate paths
            if (!$this->validate_component_paths($config)) {
                $this->add_component_error($component_id, 'Invalid component paths');
                return false;
            }

            // Validate class name - FIXED VERSION
            if (!$this->validate_class_name($config['class_name'])) {
                $this->add_component_error($component_id, 'Invalid or conflicting class name');
                return false;
            }

            // Set defaults
            $config = array_merge(array(
                'name' => ucfirst($component_id),
                'description' => '',
                'dependencies' => array(),
                'version' => '1.0.0',
                'enabled' => true,
                'priority' => 10,
                'registered_at' => current_time('mysql')
            ), $config);

            // Register component
            $this->components[$component_id] = $config;

            // Update component settings
            $this->update_component_setting($config['id'], 'enabled', $config['enabled']);
            $this->update_component_setting($config['id'], 'registered_at', $config['registered_at']);

            $this->log_info("Component registered successfully: {$config['id']}");

            // Fire action for external hooks
            do_action('chatshop_component_registered', $config['id'], $config);

            return true;
        } catch (Exception $e) {
            $this->add_component_error($config['id'] ?? 'unknown', 'Registration exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Validate component configuration
     *
     * @since 1.0.0
     * @param array $config Component configuration
     * @return bool True if valid, false otherwise
     */
    private function validate_component_config($config)
    {
        // Check required fields
        $required_fields = array('id', 'path', 'main_file', 'class_name');
        foreach ($required_fields as $field) {
            if (empty($config[$field])) {
                return false;
            }
        }

        // Validate component ID
        if (!$this->is_valid_component_id($config['id'])) {
            return false;
        }

        return true;
    }

    /**
     * Validate component ID format
     *
     * @since 1.0.0
     * @param string $component_id Component identifier
     * @return bool True if valid, false otherwise
     */
    private function is_valid_component_id($component_id)
    {
        // Check format: letters, numbers, underscores, hyphens only
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $component_id)) {
            return false;
        }

        // Check length
        if (strlen($component_id) < 2 || strlen($component_id) > 50) {
            return false;
        }

        // Check reserved names
        $reserved_names = array('core', 'admin', 'public', 'wp', 'wordpress', 'chatshop_core');
        if (in_array(strtolower($component_id), $reserved_names)) {
            return false;
        }

        return true;
    }

    /**
     * Validate component file paths
     *
     * @since 1.0.0
     * @param array $config Component configuration
     * @return bool True if valid, false otherwise
     */
    private function validate_component_paths($config)
    {
        // Check if path exists and is readable
        if (!is_dir($config['path']) || !is_readable($config['path'])) {
            return false;
        }

        // Check if main file exists
        $main_file_path = trailingslashit($config['path']) . $config['main_file'];
        if (!file_exists($main_file_path) || !is_readable($main_file_path)) {
            return false;
        }

        // Additional security check: ensure files are within plugin directory
        $plugin_dir = defined('CHATSHOP_PLUGIN_DIR') ? CHATSHOP_PLUGIN_DIR : '';
        if (!empty($plugin_dir) && strpos(realpath($config['path']), realpath($plugin_dir)) !== 0) {
            $this->log_error("Component path outside plugin directory: {$config['path']}");
            return false;
        }

        return true;
    }

    /**
     * Validate class name format - FIXED VERSION
     *
     * @since 1.0.0
     * @param string $class_name Class name to validate
     * @return bool True if valid, false otherwise
     */
    private function validate_class_name($class_name)
    {
        // Check if it's a valid PHP class name
        if (!preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff\\\\]*$/', $class_name)) {
            return false;
        }

        // Check if class already exists - BUT ALLOW OUR OWN CLASSES
        if (class_exists($class_name)) {
            // Allow ChatShop classes to be re-registered (for development/testing)
            if (strpos($class_name, 'ChatShop\\') === 0) {
                $this->log_info("ChatShop class already exists but allowing re-registration: {$class_name}");
                return true;
            } else {
                $this->log_error("Non-ChatShop class already exists: {$class_name}");
                return false;
            }
        }

        return true;
    }

    /**
     * Unregister a component
     *
     * @since 1.0.0
     * @param string $component_id Component identifier
     * @return bool True if unregistered successfully, false otherwise
     */
    public function unregister_component($component_id)
    {
        if (!isset($this->components[$component_id])) {
            return false;
        }

        // Fire action before unregistering
        do_action('chatshop_component_before_unregister', $component_id, $this->components[$component_id]);

        // Remove from components array
        unset($this->components[$component_id]);

        // Remove from settings
        $this->delete_component_settings($component_id);

        $this->log_info("Component unregistered: {$component_id}");

        // Fire action after unregistering
        do_action('chatshop_component_unregistered', $component_id);

        return true;
    }

    /**
     * Get component configuration
     *
     * @since 1.0.0
     * @param string $component_id Component identifier
     * @return array|null Component configuration or null if not found
     */
    public function get_component($component_id)
    {
        return isset($this->components[$component_id]) ? $this->components[$component_id] : null;
    }

    /**
     * Get all registered components
     *
     * @since 1.0.0
     * @return array Array of component configurations
     */
    public function get_all_components()
    {
        return $this->components;
    }

    /**
     * Check if component is registered
     *
     * @since 1.0.0
     * @param string $component_id Component identifier
     * @return bool True if registered, false otherwise
     */
    public function is_component_registered($component_id)
    {
        return isset($this->components[$component_id]);
    }

    /**
     * Get components by dependency order
     *
     * @since 1.0.0
     * @return array Components sorted by dependency order
     */
    public function get_components_by_dependency_order()
    {
        $components = $this->components;
        $sorted = array();
        $visited = array();

        foreach ($components as $component) {
            $this->resolve_dependencies($component, $components, $sorted, $visited);
        }

        return $sorted;
    }

    /**
     * Resolve component dependencies recursively
     *
     * @since 1.0.0
     * @param array $component Component configuration
     * @param array $all_components All available components
     * @param array &$sorted Sorted components array (passed by reference)
     * @param array &$visited Visited components array (passed by reference)
     */
    private function resolve_dependencies($component, $all_components, &$sorted, &$visited)
    {
        $component_id = $component['id'];

        // Skip if already processed
        if (isset($visited[$component_id])) {
            return;
        }

        $visited[$component_id] = true;

        // Process dependencies first
        if (!empty($component['dependencies'])) {
            foreach ($component['dependencies'] as $dependency_id) {
                if (isset($all_components[$dependency_id])) {
                    $this->resolve_dependencies($all_components[$dependency_id], $all_components, $sorted, $visited);
                } else {
                    $this->add_component_error($component_id, "Missing dependency: {$dependency_id}");
                }
            }
        }

        // Add current component to sorted list
        $sorted[$component_id] = $component;
    }

    // ================================
    // COMPONENT SETTINGS MANAGEMENT
    // ================================

    /**
     * Update component setting
     *
     * @since 1.0.0
     * @param string $component_id Component identifier
     * @param string $setting_name Setting name
     * @param mixed $value Setting value
     * @return bool True if updated successfully, false otherwise
     */
    public function update_component_setting($component_id, $setting_name, $value)
    {
        if (!isset($this->settings_cache[$component_id])) {
            $this->settings_cache[$component_id] = array();
        }

        $this->settings_cache[$component_id][$setting_name] = $value;
        return $this->save_component_settings();
    }

    /**
     * Get component setting
     *
     * @since 1.0.0
     * @param string $component_id Component identifier
     * @param string $setting_name Setting name
     * @param mixed $default Default value
     * @return mixed Setting value or default
     */
    public function get_component_setting($component_id, $setting_name, $default = null)
    {
        if (isset($this->settings_cache[$component_id][$setting_name])) {
            return $this->settings_cache[$component_id][$setting_name];
        }

        return $default;
    }

    /**
     * Get all component settings
     *
     * @since 1.0.0
     * @param string $component_id Component identifier
     * @return array Component settings
     */
    public function get_component_settings($component_id)
    {
        return isset($this->settings_cache[$component_id]) ? $this->settings_cache[$component_id] : array();
    }

    /**
     * Delete component settings
     *
     * @since 1.0.0
     * @param string $component_id Component identifier
     * @return bool True if deleted successfully, false otherwise
     */
    public function delete_component_settings($component_id)
    {
        if (isset($this->settings_cache[$component_id])) {
            unset($this->settings_cache[$component_id]);
            return $this->save_component_settings();
        }

        return false;
    }

    /**
     * Check if component is enabled
     *
     * @since 1.0.0
     * @param string $component_id Component identifier
     * @return bool True if enabled, false otherwise
     */
    public function is_component_enabled($component_id)
    {
        return (bool) $this->get_component_setting($component_id, 'enabled', true);
    }

    /**
     * Enable component
     *
     * @since 1.0.0
     * @param string $component_id Component identifier
     * @return bool True if enabled successfully, false otherwise
     */
    public function enable_component($component_id)
    {
        if (!$this->is_component_registered($component_id)) {
            return false;
        }

        return $this->update_component_setting($component_id, 'enabled', true);
    }

    /**
     * Disable component
     *
     * @since 1.0.0
     * @param string $component_id Component identifier
     * @return bool True if disabled successfully, false otherwise
     */
    public function disable_component($component_id)
    {
        if (!$this->is_component_registered($component_id)) {
            return false;
        }

        return $this->update_component_setting($component_id, 'enabled', false);
    }

    // ================================
    // ERROR HANDLING AND LOGGING
    // ================================

    /**
     * Add component error
     *
     * @since 1.0.0
     * @param string $component_id Component identifier
     * @param string $error_message Error message
     */
    private function add_component_error($component_id, $error_message)
    {
        $this->component_errors[$component_id] = $error_message;

        // Safe error logging without recursion
        if (function_exists('error_log') && defined('WP_DEBUG') && WP_DEBUG) {
            error_log("ChatShop Component Registry Error [{$component_id}]: {$error_message}");
        }
    }

    /**
     * Get component errors
     *
     * @since 1.0.0
     * @return array Component errors
     */
    public function get_component_errors()
    {
        return $this->component_errors;
    }

    /**
     * Clear component errors
     *
     * @since 1.0.0
     */
    public function clear_component_errors()
    {
        $this->component_errors = array();
    }

    /**
     * Log info message - SAFE VERSION
     *
     * @since 1.0.0
     * @param string $message Message to log
     */
    private function log_info($message)
    {
        // Use direct error_log to avoid recursion
        if (defined('WP_DEBUG') && WP_DEBUG && function_exists('error_log')) {
            error_log("ChatShop Registry Info: {$message}");
        }
    }

    /**
     * Log error message - SAFE VERSION
     *
     * @since 1.0.0
     * @param string $message Message to log
     */
    private function log_error($message)
    {
        // Use direct error_log to avoid recursion
        if (function_exists('error_log')) {
            error_log("ChatShop Registry Error: {$message}");
        }
    }

    // ================================
    // UTILITY METHODS
    // ================================

    /**
     * Get registry statistics
     *
     * @since 1.0.0
     * @return array Registry statistics
     */
    public function get_registry_stats()
    {
        $enabled_count = 0;
        $error_count = count($this->component_errors);

        foreach ($this->components as $component_id => $component) {
            if ($this->is_component_enabled($component_id)) {
                $enabled_count++;
            }
        }

        return array(
            'total_registered' => count($this->components),
            'enabled_components' => $enabled_count,
            'disabled_components' => count($this->components) - $enabled_count,
            'error_count' => $error_count,
            'initialized' => $this->initialized
        );
    }

    /**
     * Export registry data
     *
     * @since 1.0.0
     * @return array Registry export data
     */
    public function export_registry_data()
    {
        return array(
            'components' => $this->components,
            'settings' => $this->settings_cache,
            'errors' => $this->component_errors,
            'stats' => $this->get_registry_stats(),
            'exported_at' => current_time('mysql')
        );
    }

    /**
     * Clear all registry data
     *
     * @since 1.0.0
     * @return bool True if cleared successfully, false otherwise
     */
    public function clear_registry()
    {
        $this->components = array();
        $this->settings_cache = array();
        $this->component_errors = array();

        // Clear from database
        delete_option('chatshop_component_settings');

        $this->log_info('Registry cleared');

        return true;
    }

    /**
     * Validate registry integrity
     *
     * @since 1.0.0
     * @return array Validation results
     */
    public function validate_registry()
    {
        $validation_results = array(
            'valid' => true,
            'issues' => array(),
            'warnings' => array()
        );

        foreach ($this->components as $component_id => $component) {
            // Check if component files still exist
            if (!$this->validate_component_paths($component)) {
                $validation_results['valid'] = false;
                $validation_results['issues'][] = "Component files missing: {$component_id}";
            }

            // Check for dependency issues
            if (!empty($component['dependencies'])) {
                foreach ($component['dependencies'] as $dependency_id) {
                    if (!$this->is_component_registered($dependency_id)) {
                        $validation_results['warnings'][] = "Missing dependency for {$component_id}: {$dependency_id}";
                    }
                }
            }

            // Check class existence without causing conflicts
            $class_name = $component['class_name'];
            if (class_exists($class_name)) {
                // Check if it's a valid ChatShop component class
                if (strpos($class_name, 'ChatShop\\') !== 0) {
                    $validation_results['warnings'][] = "Non-ChatShop class conflict: {$class_name}";
                }
            }
        }

        return $validation_results;
    }

    /**
     * Get component dependency tree
     *
     * @since 1.0.0
     * @param string $component_id Component identifier
     * @return array Dependency tree
     */
    public function get_component_dependency_tree($component_id)
    {
        if (!$this->is_component_registered($component_id)) {
            return array();
        }

        $component = $this->get_component($component_id);
        $tree = array(
            'component' => $component_id,
            'dependencies' => array()
        );

        if (!empty($component['dependencies'])) {
            foreach ($component['dependencies'] as $dependency_id) {
                $tree['dependencies'][] = $this->get_component_dependency_tree($dependency_id);
            }
        }

        return $tree;
    }

    /**
     * Find components that depend on a specific component
     *
     * @since 1.0.0
     * @param string $component_id Component identifier
     * @return array Components that depend on the specified component
     */
    public function find_dependent_components($component_id)
    {
        $dependents = array();

        foreach ($this->components as $id => $component) {
            if (!empty($component['dependencies']) && in_array($component_id, $component['dependencies'])) {
                $dependents[] = $id;
            }
        }

        return $dependents;
    }

    /**
     * Check for circular dependencies
     *
     * @since 1.0.0
     * @return array Components with circular dependencies
     */
    public function check_circular_dependencies()
    {
        $circular_deps = array();

        foreach ($this->components as $component_id => $component) {
            if ($this->has_circular_dependency($component_id, array())) {
                $circular_deps[] = $component_id;
            }
        }

        return $circular_deps;
    }

    /**
     * Check if component has circular dependency
     *
     * @since 1.0.0
     * @param string $component_id Component identifier
     * @param array $visited Visited components in current path
     * @return bool True if circular dependency found, false otherwise
     */
    private function has_circular_dependency($component_id, $visited)
    {
        if (in_array($component_id, $visited)) {
            return true;
        }

        $component = $this->get_component($component_id);
        if (!$component || empty($component['dependencies'])) {
            return false;
        }

        $visited[] = $component_id;

        foreach ($component['dependencies'] as $dependency_id) {
            if ($this->has_circular_dependency($dependency_id, $visited)) {
                return true;
            }
        }

        return false;
    }
}
