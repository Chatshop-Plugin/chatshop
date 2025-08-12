<?php

/**
 * Abstract Component Class
 *
 * File: includes/abstracts/abstract-chatshop-component.php
 * 
 * Base class for all ChatShop components providing common functionality
 * and standardized interface for component management.
 *
 * @package ChatShop
 * @subpackage Abstracts
 * @since 1.0.0
 */

namespace ChatShop;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Abstract ChatShop Component Class
 *
 * Provides base functionality for all plugin components including
 * activation, deactivation, initialization, and common utilities.
 *
 * @since 1.0.0
 */
abstract class ChatShop_Abstract_Component
{
    /**
     * Component unique identifier
     *
     * @var string
     * @since 1.0.0
     */
    protected $id;

    /**
     * Component name
     *
     * @var string
     * @since 1.0.0
     */
    protected $name;

    /**
     * Component description
     *
     * @var string
     * @since 1.0.0
     */
    protected $description;

    /**
     * Component version
     *
     * @var string
     * @since 1.0.0
     */
    protected $version = '1.0.0';

    /**
     * Component enabled status
     *
     * @var bool
     * @since 1.0.0
     */
    protected $enabled = true;

    /**
     * Component dependencies
     *
     * @var array
     * @since 1.0.0
     */
    protected $dependencies = array();

    /**
     * Component settings
     *
     * @var array
     * @since 1.0.0
     */
    protected $settings = array();

    /**
     * Component initialization flag
     *
     * @var bool
     * @since 1.0.0
     */
    private $initialized = false;

    /**
     * Initialize component
     *
     * This method should be implemented by child classes to set up
     * component-specific functionality, hooks, and properties.
     *
     * @since 1.0.0
     */
    abstract protected function init();

    /**
     * Component activation handler
     *
     * Override this method to perform component-specific activation tasks
     * such as creating database tables, setting default options, etc.
     *
     * @since 1.0.0
     * @return bool True on successful activation, false on failure
     */
    protected function do_activation()
    {
        // Default implementation - override in child classes
        return true;
    }

    /**
     * Component deactivation handler
     *
     * Override this method to perform component-specific deactivation tasks
     * such as cleanup, removing scheduled events, etc.
     *
     * @since 1.0.0
     * @return bool True on successful deactivation, false on failure
     */
    protected function do_deactivation()
    {
        // Default implementation - override in child classes
        return true;
    }

    /**
     * Get component ID
     *
     * @since 1.0.0
     * @return string Component identifier
     */
    public function get_id()
    {
        return $this->id;
    }

    /**
     * Get component name
     *
     * @since 1.0.0
     * @return string Component name
     */
    public function get_name()
    {
        return $this->name;
    }

    /**
     * Get component description
     *
     * @since 1.0.0
     * @return string Component description
     */
    public function get_description()
    {
        return $this->description;
    }

    /**
     * Get component version
     *
     * @since 1.0.0
     * @return string Component version
     */
    public function get_version()
    {
        return $this->version;
    }

    /**
     * Check if component is enabled
     *
     * @since 1.0.0
     * @return bool True if enabled, false otherwise
     */
    public function is_enabled()
    {
        return $this->enabled;
    }

    /**
     * Enable component
     *
     * @since 1.0.0
     * @return bool True on success, false on failure
     */
    public function enable()
    {
        if ($this->enabled) {
            return true;
        }

        $this->enabled = true;

        // Trigger activation if not already initialized
        if (!$this->initialized) {
            $this->activate();
        }

        return true;
    }

    /**
     * Disable component
     *
     * @since 1.0.0
     * @return bool True on success, false on failure
     */
    public function disable()
    {
        if (!$this->enabled) {
            return true;
        }

        $result = $this->deactivate();

        if ($result) {
            $this->enabled = false;
        }

        return $result;
    }

    /**
     * Get component dependencies
     *
     * @since 1.0.0
     * @return array Array of component IDs this component depends on
     */
    public function get_dependencies()
    {
        return $this->dependencies;
    }

    /**
     * Check if component dependencies are met
     *
     * @since 1.0.0
     * @return bool True if all dependencies are available, false otherwise
     */
    public function dependencies_met()
    {
        if (empty($this->dependencies)) {
            return true;
        }

        foreach ($this->dependencies as $dependency) {
            $component = chatshop_get_component($dependency);
            if (!$component || !$component->is_enabled()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Activate component
     *
     * @since 1.0.0
     * @return bool True on successful activation, false on failure
     */
    public function activate()
    {
        if ($this->initialized) {
            return true;
        }

        // Check dependencies
        if (!$this->dependencies_met()) {
            $this->log_error("Component activation failed: Dependencies not met for {$this->id}");
            return false;
        }

        // Run activation tasks
        $result = $this->do_activation();

        if ($result) {
            $this->initialized = true;
            $this->log_info("Component activated successfully: {$this->id}");

            // Fire activation hook
            do_action('chatshop_component_activated', $this->id, $this);
        } else {
            $this->log_error("Component activation failed: {$this->id}");
        }

        return $result;
    }

    /**
     * Deactivate component
     *
     * @since 1.0.0
     * @return bool True on successful deactivation, false on failure
     */
    public function deactivate()
    {
        if (!$this->initialized) {
            return true;
        }

        // Run deactivation tasks
        $result = $this->do_deactivation();

        if ($result) {
            $this->initialized = false;
            $this->log_info("Component deactivated successfully: {$this->id}");

            // Fire deactivation hook
            do_action('chatshop_component_deactivated', $this->id, $this);
        } else {
            $this->log_error("Component deactivation failed: {$this->id}");
        }

        return $result;
    }

    /**
     * Get component setting
     *
     * @since 1.0.0
     * @param string $key Setting key
     * @param mixed $default Default value if setting not found
     * @return mixed Setting value
     */
    public function get_setting($key, $default = null)
    {
        if (!empty($this->settings) && isset($this->settings[$key])) {
            return $this->settings[$key];
        }

        // Try to get from WordPress options
        $option_name = "chatshop_{$this->id}_settings";
        $settings = get_option($option_name, array());

        return isset($settings[$key]) ? $settings[$key] : $default;
    }

    /**
     * Update component setting
     *
     * @since 1.0.0
     * @param string $key Setting key
     * @param mixed $value Setting value
     * @return bool True on success, false on failure
     */
    public function update_setting($key, $value)
    {
        $option_name = "chatshop_{$this->id}_settings";
        $settings = get_option($option_name, array());

        $settings[$key] = $value;

        // Update local cache
        $this->settings[$key] = $value;

        return update_option($option_name, $settings);
    }

    /**
     * Delete component setting
     *
     * @since 1.0.0
     * @param string $key Setting key
     * @return bool True on success, false on failure
     */
    public function delete_setting($key)
    {
        $option_name = "chatshop_{$this->id}_settings";
        $settings = get_option($option_name, array());

        if (isset($settings[$key])) {
            unset($settings[$key]);

            // Update local cache
            if (isset($this->settings[$key])) {
                unset($this->settings[$key]);
            }

            return update_option($option_name, $settings);
        }

        return true;
    }

    /**
     * Get all component settings
     *
     * @since 1.0.0
     * @return array Component settings
     */
    public function get_all_settings()
    {
        $option_name = "chatshop_{$this->id}_settings";
        return get_option($option_name, array());
    }

    /**
     * Load component settings
     *
     * @since 1.0.0
     */
    protected function load_settings()
    {
        $option_name = "chatshop_{$this->id}_settings";
        $this->settings = get_option($option_name, array());
    }

    /**
     * Save component settings
     *
     * @since 1.0.0
     * @return bool True on success, false on failure
     */
    protected function save_settings()
    {
        $option_name = "chatshop_{$this->id}_settings";
        return update_option($option_name, $this->settings);
    }

    /**
     * Log informational message
     *
     * @since 1.0.0
     * @param string $message Log message
     */
    protected function log_info($message)
    {
        if (function_exists('chatshop_log')) {
            chatshop_log($message, 'info');
        } else {
            error_log("ChatShop {$this->id}: {$message}");
        }
    }

    /**
     * Log error message
     *
     * @since 1.0.0
     * @param string $message Error message
     */
    protected function log_error($message)
    {
        if (function_exists('chatshop_log')) {
            chatshop_log($message, 'error');
        } else {
            error_log("ChatShop {$this->id} ERROR: {$message}");
        }
    }

    /**
     * Log warning message
     *
     * @since 1.0.0
     * @param string $message Warning message
     */
    protected function log_warning($message)
    {
        if (function_exists('chatshop_log')) {
            chatshop_log($message, 'warning');
        } else {
            error_log("ChatShop {$this->id} WARNING: {$message}");
        }
    }

    /**
     * Check if component is initialized
     *
     * @since 1.0.0
     * @return bool True if initialized, false otherwise
     */
    public function is_initialized()
    {
        return $this->initialized;
    }

    /**
     * Get component status information
     *
     * @since 1.0.0
     * @return array Component status array
     */
    public function get_status()
    {
        return array(
            'id' => $this->id,
            'name' => $this->name,
            'version' => $this->version,
            'enabled' => $this->enabled,
            'initialized' => $this->initialized,
            'dependencies' => $this->dependencies,
            'dependencies_met' => $this->dependencies_met()
        );
    }

    /**
     * Cleanup component data
     *
     * This method should be called during uninstall to remove
     * component-specific data, settings, and database tables.
     *
     * @since 1.0.0
     * @return bool True on successful cleanup, false on failure
     */
    public function cleanup()
    {
        // Remove component settings
        $option_name = "chatshop_{$this->id}_settings";
        delete_option($option_name);

        $this->log_info("Component cleanup completed: {$this->id}");

        // Fire cleanup hook
        do_action('chatshop_component_cleanup', $this->id, $this);

        return true;
    }

    /**
     * Force component reinitialization
     *
     * @since 1.0.0
     * @return bool True on successful reinitialization, false on failure
     */
    public function reinitialize()
    {
        $this->initialized = false;
        return $this->activate();
    }
}
