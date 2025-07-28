<?php

/**
 * Component Registry Class - SAFE VERSION
 *
 * File: includes/class-chatshop-component-registry.php
 * 
 * Manages component registration and configuration with enhanced
 * class conflict prevention and error handling.
 *
 * @package ChatShop
 * @since 1.0.0
 */

namespace ChatShop;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Prevent class redeclaration with detailed check
if (class_exists('ChatShop\\ChatShop_Component_Registry')) {
    // Log the duplicate attempt if logging is available
    if (function_exists('error_log')) {
        error_log('ChatShop: Attempted to redeclare ChatShop_Component_Registry class');
    }
    return;
}

/**
 * ChatShop Component Registry Class - SAFE VERSION
 *
 * Enhanced with better error handling and conflict prevention.
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
     * Component settings option name
     *
     * @var string
     * @since 1.0.0
     */
    private $settings_option = 'chatshop_component_settings';

    /**
     * Component settings cache
     *
     * @var array
     * @since 1.0.0
     */
    private $settings_cache = null;

    /**
     * Registry lock to prevent race conditions
     *
     * @var bool
     * @since 1.0.0
     */
    private $registry_locked = false;

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        $this->load_settings();
        add_action('shutdown', array($this, 'save_settings'));
    }

    /**
     * Register a component with enhanced validation
     *
     * @since 1.0.0
     * @param array $config Component configuration
     * @return bool True if registered successfully, false otherwise
     */
    public function register_component($config)
    {
        // Check if registry is locked
        if ($this->registry_locked) {
            $this->log_error('Cannot register component: Registry is locked');
            return false;
        }

        // Validate required fields
        $required_fields = array('id', 'name', 'path', 'main_file', 'class_name');
        foreach ($required_fields as $field) {
            if (empty($config[$field])) {
                $this->log_error("Component registration failed: Missing required field '{$field}'");
                return false;
            }
        }

        // Set defaults with better structure
        $defaults = array(
            'description' => '',
            'dependencies' => array(),
            'version' => '1.0.0',
            'enabled' => false,
            'auto_load' => true,
            'priority' => 10,
            'supports' => array(),
            'requires' => array(),
            'namespace' => 'ChatShop\\',
            'status' => 'inactive'
        );

        $config = wp_parse_args($config, $defaults);

        // Validate component ID format
        if (!$this->is_valid_component_id($config['id'])) {
            $this->log_error("Invalid component ID format: {$config['id']}");
            return false;
        }

        // Check if already registered
        if (isset($this->components[$config['id']])) {
            $this->log_error("Component already registered: {$config['id']}");
            return false;
        }

        // Validate file paths
        if (!$this->validate_component_paths($config)) {
            $this->log_error("Component file validation failed: {$config['id']}");
            return false;
        }

        // Validate class name format
        if (!$this->validate_class_name($config['class_name'])) {
            $this->log_error("Invalid class name format: {$config['class_name']}");
            return false;
        }

        // Add timestamp and unique hash
        $config['registered_at'] = current_time('mysql');
        $config['hash'] = md5(serialize($config));
        $config['status'] = 'registered';

        // Store component configuration
        $this->components[$config['id']] = $config;

        // Update persistent settings
        $this->update_component_setting($config['id'], 'enabled', $config['enabled']);
        $this->update_component_setting($config['id'], 'registered_at', $config['registered_at']);

        $this->log_info("Component registered successfully: {$config['id']}");

        // Fire action for external hooks
        do_action('chatshop_component_registered', $config['id'], $config);

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
     * Validate class name format
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

        // Check if class already exists (to prevent conflicts)
        if (class_exists($class_name)) {
            $this->log_error("Class already exists: {$class_name}");
            return false;
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
        if (!isset($this->components[$component_id])) {
            return null;
        }

        $component = $this->components[$component_id];

        // Merge with persistent settings
        $enabled = $this->get_component_setting($component_id, 'enabled', $component['enabled']);
        $component['enabled'] = $enabled;

        return $component;
    }

    /**
     * Get all registered components
     *
     * @since 1.0.0
     * @return array Array of component configurations
     */
    public function get_all_components()
    {
        $components = array();

        foreach ($this->components as $id => $component) {
            $components[$id] = $this->get_component($id);
        }

        return $components;
    }

    /**
     * Get enabled components only
     *
     * @since 1.0.0
     * @return array Array of enabled component configurations
     */
    public function get_enabled_components()
    {
        $enabled_components = array();

        foreach ($this->components as $id => $component) {
            $component_data = $this->get_component($id);
            if ($component_data && $component_data['enabled']) {
                $enabled_components[$id] = $component_data;
            }
        }

        return $enabled_components;
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
        if (!isset($this->components[$component_id])) {
            return false;
        }

        $this->update_component_setting($component_id, 'enabled', true);

        $this->log_info("Component enabled: {$component_id}");

        // Fire action
        do_action('chatshop_component_enabled', $component_id);

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
        if (!isset($this->components[$component_id])) {
            return false;
        }

        $this->update_component_setting($component_id, 'enabled', false);

        $this->log_info("Component disabled: {$component_id}");

        // Fire action
        do_action('chatshop_component_disabled', $component_id);

        return true;
    }

    /**
     * Check if component exists
     *
     * @since 1.0.0
     * @param string $component_id Component identifier
     * @return bool True if exists, false otherwise
     */
    public function component_exists($component_id)
    {
        return isset($this->components[$component_id]);
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
        if (!isset($this->components[$component_id])) {
            return false;
        }

        return $this->get_component_setting($component_id, 'enabled', false);
    }

    /**
     * Get component dependencies
     *
     * @since 1.0.0
     * @param string $component_id Component identifier
     * @return array Array of dependency IDs
     */
    public function get_component_dependencies($component_id)
    {
        if (!isset($this->components[$component_id])) {
            return array();
        }

        return $this->components[$component_id]['dependencies'];
    }

    /**
     * Lock the registry to prevent further registrations
     *
     * @since 1.0.0
     */
    public function lock_registry()
    {
        $this->registry_locked = true;
        $this->log_info('Component registry locked');
    }

    /**
     * Unlock the registry
     *
     * @since 1.0.0
     */
    public function unlock_registry()
    {
        $this->registry_locked = false;
        $this->log_info('Component registry unlocked');
    }

    /**
     * Check if registry is locked
     *
     * @since 1.0.0
     * @return bool True if locked, false otherwise
     */
    public function is_registry_locked()
    {
        return $this->registry_locked;
    }

    /**
     * Load component settings from database
     *
     * @since 1.0.0
     */
    private function load_settings()
    {
        if ($this->settings_cache === null) {
            $this->settings_cache = get_option($this->settings_option, array());
            if (!is_array($this->settings_cache)) {
                $this->settings_cache = array();
            }
        }
    }

    /**
     * Save component settings to database
     *
     * @since 1.0.0
     */
    public function save_settings()
    {
        if ($this->settings_cache !== null) {
            update_option($this->settings_option, $this->settings_cache);
        }
    }

    /**
     * Get component setting
     *
     * @since 1.0.0
     * @param string $component_id Component identifier
     * @param string $setting_key Setting key
     * @param mixed $default Default value
     * @return mixed Setting value
     */
    private function get_component_setting($component_id, $setting_key, $default = null)
    {
        $this->load_settings();

        if (isset($this->settings_cache[$component_id][$setting_key])) {
            return $this->settings_cache[$component_id][$setting_key];
        }

        return $default;
    }

    /**
     * Update component setting
     *
     * @since 1.0.0
     * @param string $component_id Component identifier
     * @param string $setting_key Setting key
     * @param mixed $value Setting value
     */
    private function update_component_setting($component_id, $setting_key, $value)
    {
        $this->load_settings();

        if (!isset($this->settings_cache[$component_id])) {
            $this->settings_cache[$component_id] = array();
        }

        $this->settings_cache[$component_id][$setting_key] = $value;
    }

    /**
     * Delete all settings for a component
     *
     * @since 1.0.0
     * @param string $component_id Component identifier
     */
    private function delete_component_settings($component_id)
    {
        $this->load_settings();

        if (isset($this->settings_cache[$component_id])) {
            unset($this->settings_cache[$component_id]);
        }
    }

    /**
     * Get registry statistics
     *
     * @since 1.0.0
     * @return array Registry statistics
     */
    public function get_registry_stats()
    {
        $enabled_count = count($this->get_enabled_components());
        $total_count = count($this->components);

        return array(
            'total_components' => $total_count,
            'enabled_components' => $enabled_count,
            'disabled_components' => $total_count - $enabled_count,
            'registry_locked' => $this->registry_locked,
            'memory_usage' => memory_get_usage(true),
            'last_updated' => current_time('mysql')
        );
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
            error_log("ChatShop Component Registry: {$message}");
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
            error_log("ChatShop Component Registry: {$message}");
        }
    }
}
