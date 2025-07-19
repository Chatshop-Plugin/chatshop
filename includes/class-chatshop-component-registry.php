<?php

/**
 * Component Registry Class
 *
 * Manages component registration and configuration.
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
 * ChatShop Component Registry Class
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
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        $this->load_settings();
    }

    /**
     * Register a component
     *
     * @since 1.0.0
     * @param array $config Component configuration
     * @return bool True if registered successfully, false otherwise
     */
    public function register_component($config)
    {
        // Validate required fields
        $required_fields = array('id', 'name', 'path', 'main_file', 'class_name');
        foreach ($required_fields as $field) {
            if (empty($config[$field])) {
                $this->log_error("Component registration failed: Missing required field '{$field}'");
                return false;
            }
        }

        // Set defaults
        $defaults = array(
            'description' => '',
            'dependencies' => array(),
            'version' => '1.0.0',
            'enabled' => false,
            'auto_load' => true,
            'priority' => 10
        );

        $config = wp_parse_args($config, $defaults);

        // Validate component ID
        if (!$this->is_valid_component_id($config['id'])) {
            $this->log_error("Invalid component ID: {$config['id']}");
            return false;
        }

        // Check if already registered
        if (isset($this->components[$config['id']])) {
            $this->log_error("Component already registered: {$config['id']}");
            return false;
        }

        // Store component configuration
        $this->components[$config['id']] = $config;

        $this->log_info("Component registered: {$config['id']}");
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

        unset($this->components[$component_id]);
        $this->log_info("Component unregistered: {$component_id}");
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
     * Get enabled components
     *
     * @since 1.0.0
     * @return array Array of enabled component configurations
     */
    public function get_enabled_components()
    {
        $enabled = array();

        foreach ($this->components as $component_id => $component) {
            if ($this->is_component_enabled($component_id)) {
                $enabled[$component_id] = $component;
            }
        }

        // Sort by priority
        uasort($enabled, function ($a, $b) {
            return $a['priority'] - $b['priority'];
        });

        return $enabled;
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

        $settings = get_option($this->settings_option, array());

        // Check user settings first
        if (isset($settings[$component_id]['enabled'])) {
            return (bool) $settings[$component_id]['enabled'];
        }

        // Fall back to default
        return (bool) $this->components[$component_id]['enabled'];
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

        $settings = get_option($this->settings_option, array());
        $settings[$component_id]['enabled'] = true;

        update_option($this->settings_option, $settings);
        $this->log_info("Component enabled: {$component_id}");

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

        $settings = get_option($this->settings_option, array());
        $settings[$component_id]['enabled'] = false;

        update_option($this->settings_option, $settings);
        $this->log_info("Component disabled: {$component_id}");

        return true;
    }

    /**
     * Get component dependencies
     *
     * @since 1.0.0
     * @param string $component_id Component identifier
     * @return array Array of dependency IDs
     */
    public function get_dependencies($component_id)
    {
        if (!isset($this->components[$component_id])) {
            return array();
        }

        return $this->components[$component_id]['dependencies'];
    }

    /**
     * Get components that depend on the given component
     *
     * @since 1.0.0
     * @param string $component_id Component identifier
     * @return array Array of dependent component IDs
     */
    public function get_dependents($component_id)
    {
        $dependents = array();

        foreach ($this->components as $id => $component) {
            if (in_array($component_id, $component['dependencies'])) {
                $dependents[] = $id;
            }
        }

        return $dependents;
    }

    /**
     * Validate component dependencies
     *
     * @since 1.0.0
     * @param string $component_id Component identifier
     * @return bool True if dependencies are valid, false otherwise
     */
    public function validate_dependencies($component_id)
    {
        $dependencies = $this->get_dependencies($component_id);

        foreach ($dependencies as $dependency) {
            if (!isset($this->components[$dependency])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Update component configuration
     *
     * @since 1.0.0
     * @param string $component_id Component identifier
     * @param array $config New configuration
     * @return bool True if updated successfully, false otherwise
     */
    public function update_component($component_id, $config)
    {
        if (!isset($this->components[$component_id])) {
            return false;
        }

        $this->components[$component_id] = array_merge($this->components[$component_id], $config);
        return true;
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
    public function get_component_setting($component_id, $setting_key, $default = null)
    {
        $settings = get_option($this->settings_option, array());

        if (isset($settings[$component_id][$setting_key])) {
            return $settings[$component_id][$setting_key];
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
     * @return bool True if updated successfully, false otherwise
     */
    public function update_component_setting($component_id, $setting_key, $value)
    {
        $settings = get_option($this->settings_option, array());
        $settings[$component_id][$setting_key] = $value;

        return update_option($this->settings_option, $settings);
    }

    /**
     * Load component settings from database
     *
     * @since 1.0.0
     */
    private function load_settings()
    {
        // Settings are loaded when needed
        // This method is for future use if needed
    }

    /**
     * Validate component ID
     *
     * @since 1.0.0
     * @param string $component_id Component identifier
     * @return bool True if valid, false otherwise
     */
    private function is_valid_component_id($component_id)
    {
        // Must be alphanumeric with underscores/hyphens only
        return preg_match('/^[a-zA-Z0-9_-]+$/', $component_id);
    }

    /**
     * Export component registry for debugging
     *
     * @since 1.0.0
     * @return array Registry data
     */
    public function export_registry()
    {
        return array(
            'components' => $this->components,
            'settings' => get_option($this->settings_option, array())
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
