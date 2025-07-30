<?php

/**
 * Abstract Component Class
 *
 * File: includes/abstracts/abstract-chatshop-component.php
 * 
 * Base class for all ChatShop components providing common functionality
 * and enforcing component interface standards.
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
 * Base class for all plugin components with common functionality.
 *
 * @since 1.0.0
 */
abstract class ChatShop_Abstract_Component
{
    /**
     * Component ID
     *
     * @var string
     * @since 1.0.0
     */
    protected $id = '';

    /**
     * Component name
     *
     * @var string
     * @since 1.0.0
     */
    protected $name = '';

    /**
     * Component description
     *
     * @var string
     * @since 1.0.0
     */
    protected $description = '';

    /**
     * Component version
     *
     * @var string
     * @since 1.0.0
     */
    protected $version = '1.0.0';

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
     * Component enabled status
     *
     * @var bool
     * @since 1.0.0
     */
    protected $enabled = true;

    /**
     * Premium only flag
     *
     * @var bool
     * @since 1.0.0
     */
    protected $premium_only = false;

    /**
     * Component initialized flag
     *
     * @var bool
     * @since 1.0.0
     */
    protected $initialized = false;

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
     * Initialize component
     *
     * @since 1.0.0
     * @return bool Initialization status
     */
    public function initialize()
    {
        if ($this->initialized) {
            return true;
        }

        // Check premium requirement
        if ($this->premium_only && !chatshop_is_premium()) {
            $this->log_info("Component requires premium: {$this->id}");
            return false;
        }

        // Initialize the component
        $this->init();

        $this->initialized = true;

        // Fire action
        do_action("chatshop_component_initialized_{$this->id}", $this);

        return true;
    }

    /**
     * Initialize component implementation
     * Must be implemented by child classes
     *
     * @since 1.0.0
     */
    abstract protected function init();

    /**
     * Check if component should be loaded
     * Can be overridden by child classes
     *
     * @since 1.0.0
     * @return bool
     */
    public function should_load()
    {
        // Check if enabled
        if (!$this->enabled) {
            return false;
        }

        // Check premium requirement
        if ($this->premium_only && !chatshop_is_premium()) {
            return false;
        }

        // Check dependencies
        if (!$this->dependencies_met()) {
            return false;
        }

        return true;
    }

    /**
     * Check if dependencies are met
     *
     * @since 1.0.0
     * @return bool True if all dependencies are met
     */
    public function dependencies_met()
    {
        if (empty($this->dependencies)) {
            return true;
        }

        foreach ($this->dependencies as $dependency) {
            // Check if dependency is a class
            if (strpos($dependency, '\\') !== false || strpos($dependency, '_') !== false) {
                if (!class_exists($dependency)) {
                    return false;
                }
            }
            // Check if dependency is a function
            else if (!function_exists($dependency)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Activate component
     *
     * @since 1.0.0
     */
    public function activate()
    {
        $this->enabled = true;
        $this->update_setting('enabled', true);

        // Fire action
        do_action("chatshop_component_activated_{$this->id}", $this);

        $this->log_info("Component activated: {$this->id}");
    }

    /**
     * Deactivate component
     *
     * @since 1.0.0
     */
    public function deactivate()
    {
        $this->enabled = false;
        $this->update_setting('enabled', false);

        // Fire action
        do_action("chatshop_component_deactivated_{$this->id}", $this);

        $this->log_info("Component deactivated: {$this->id}");
    }

    /**
     * Get component ID
     *
     * @since 1.0.0
     * @return string Component ID
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
     * @return bool Enabled status
     */
    public function is_enabled()
    {
        return $this->enabled;
    }

    /**
     * Check if component is premium only
     *
     * @since 1.0.0
     * @return bool Premium only status
     */
    public function is_premium_only()
    {
        return $this->premium_only;
    }

    /**
     * Get component setting
     *
     * @since 1.0.0
     * @param string $key Setting key
     * @param mixed $default Default value
     * @return mixed Setting value
     */
    public function get_setting($key, $default = null)
    {
        return isset($this->settings[$key]) ? $this->settings[$key] : $default;
    }

    /**
     * Update component setting
     *
     * @since 1.0.0
     * @param string $key Setting key
     * @param mixed $value Setting value
     * @return bool Update status
     */
    public function update_setting($key, $value)
    {
        $this->settings[$key] = $value;
        return $this->save_settings();
    }

    /**
     * Get all settings
     *
     * @since 1.0.0
     * @return array All settings
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

        // Merge with defaults
        $defaults = $this->get_default_settings();
        $this->settings = wp_parse_args($this->settings, $defaults);
    }

    /**
     * Get default settings
     * Can be overridden by child classes
     *
     * @since 1.0.0
     * @return array Default settings
     */
    protected function get_default_settings()
    {
        return array(
            'enabled' => true
        );
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
            'premium_only' => $this->premium_only,
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
     */
    public function cleanup()
    {
        // Delete component settings
        delete_option("chatshop_{$this->id}_settings");

        // Fire action for additional cleanup
        do_action("chatshop_component_cleanup_{$this->id}", $this);

        $this->log_info("Component cleaned up: {$this->id}");
    }
}
