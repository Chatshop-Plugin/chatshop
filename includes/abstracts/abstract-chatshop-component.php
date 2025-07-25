<?php

/**
 * Abstract Component
 *
 * Base class for all ChatShop components providing common functionality
 * for initialization, configuration, and lifecycle management.
 *
 * @package    ChatShop
 * @subpackage ChatShop/includes/abstracts
 * @since      1.0.0
 */

namespace ChatShop;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Abstract Component Class
 *
 * @since 1.0.0
 */
abstract class ChatShop_Abstract_Component
{
    /**
     * Component ID
     *
     * @since 1.0.0
     * @var string
     */
    protected $id;

    /**
     * Component name
     *
     * @since 1.0.0
     * @var string
     */
    protected $name;

    /**
     * Component description
     *
     * @since 1.0.0
     * @var string
     */
    protected $description;

    /**
     * Component version
     *
     * @since 1.0.0
     * @var string
     */
    protected $version = '1.0.0';

    /**
     * Component enabled status
     *
     * @since 1.0.0
     * @var bool
     */
    protected $enabled = false;

    /**
     * Component dependencies
     *
     * @since 1.0.0
     * @var array
     */
    protected $dependencies = array();

    /**
     * Component settings
     *
     * @since 1.0.0
     * @var array
     */
    protected $settings = array();

    /**
     * Component initialized status
     *
     * @since 1.0.0
     * @var bool
     */
    protected $initialized = false;

    /**
     * Premium component flag
     *
     * @since 1.0.0
     * @var bool
     */
    protected $premium = false;

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        $this->load_settings();
        $this->init();
    }

    /**
     * Initialize component
     *
     * Override this method in child classes
     *
     * @since 1.0.0
     */
    abstract protected function init();

    /**
     * Activate component
     *
     * @since 1.0.0
     * @return bool Activation result
     */
    public function activate()
    {
        if (!$this->check_dependencies()) {
            return false;
        }

        if ($this->premium && !$this->check_premium_license()) {
            return false;
        }

        $result = $this->do_activation();

        if ($result) {
            $this->enabled = true;
            $this->save_settings();

            do_action('chatshop_component_activated', $this->id);
            chatshop_log("Component activated: {$this->id}", 'info');
        }

        return $result;
    }

    /**
     * Deactivate component
     *
     * @since 1.0.0
     * @return bool Deactivation result
     */
    public function deactivate()
    {
        $result = $this->do_deactivation();

        if ($result) {
            $this->enabled = false;
            $this->save_settings();

            do_action('chatshop_component_deactivated', $this->id);
            chatshop_log("Component deactivated: {$this->id}", 'info');
        }

        return $result;
    }

    /**
     * Enable component
     *
     * @since 1.0.0
     * @return bool Enable result
     */
    public function enable()
    {
        if (!$this->is_active()) {
            return $this->activate();
        }

        $this->enabled = true;
        return $this->save_settings();
    }

    /**
     * Disable component
     *
     * @since 1.0.0
     * @return bool Disable result
     */
    public function disable()
    {
        $this->enabled = false;
        return $this->save_settings();
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
     * Check if component is active (enabled and dependencies met)
     *
     * @since 1.0.0
     * @return bool Active status
     */
    public function is_active()
    {
        return $this->enabled && $this->check_dependencies();
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
     * Get component dependencies
     *
     * @since 1.0.0
     * @return array Component dependencies
     */
    public function get_dependencies()
    {
        return $this->dependencies;
    }

    /**
     * Check if component is premium
     *
     * @since 1.0.0
     * @return bool Premium status
     */
    public function is_premium()
    {
        return $this->premium;
    }

    /**
     * Component activation hook
     *
     * Override this method in child classes
     *
     * @since 1.0.0
     * @return bool Activation result
     */
    protected function do_activation()
    {
        return true;
    }

    /**
     * Component deactivation hook
     *
     * Override this method in child classes
     *
     * @since 1.0.0
     * @return bool Deactivation result
     */
    protected function do_deactivation()
    {
        return true;
    }

    /**
     * Check component dependencies
     *
     * @since 1.0.0
     * @return bool Dependencies status
     */
    protected function check_dependencies()
    {
        if (empty($this->dependencies)) {
            return true;
        }

        foreach ($this->dependencies as $dependency) {
            if (!$this->is_dependency_met($dependency)) {
                chatshop_log("Component dependency not met: {$dependency} for {$this->id}", 'warning');
                return false;
            }
        }

        return true;
    }

    /**
     * Check if specific dependency is met
     *
     * @since 1.0.0
     * @param string $dependency Dependency identifier
     * @return bool Dependency status
     */
    protected function is_dependency_met($dependency)
    {
        // Check for WordPress plugins
        if (strpos($dependency, 'plugin:') === 0) {
            $plugin_file = substr($dependency, 7);
            return is_plugin_active($plugin_file);
        }

        // Check for WordPress feature
        if (strpos($dependency, 'wp:') === 0) {
            $feature = substr($dependency, 3);
            return $this->check_wp_feature($feature);
        }

        // Check for ChatShop component
        if (strpos($dependency, 'component:') === 0) {
            $component_id = substr($dependency, 10);
            $component = chatshop_get_component($component_id);
            return $component && $component->is_enabled();
        }

        return false;
    }

    /**
     * Check WordPress feature
     *
     * @since 1.0.0
     * @param string $feature Feature name
     * @return bool Feature availability
     */
    protected function check_wp_feature($feature)
    {
        switch ($feature) {
            case 'rest_api':
                return function_exists('rest_url');
            case 'ajax':
                return defined('DOING_AJAX');
            case 'cron':
                return !defined('DISABLE_WP_CRON') || !DISABLE_WP_CRON;
            case 'multisite':
                return is_multisite();
            default:
                return false;
        }
    }

    /**
     * Check premium license
     *
     * @since 1.0.0
     * @return bool License status
     */
    protected function check_premium_license()
    {
        return chatshop_is_premium_feature_available($this->id);
    }

    /**
     * Load component settings
     *
     * @since 1.0.0
     */
    protected function load_settings()
    {
        $this->settings = chatshop_get_option($this->id, '', array());
        $this->enabled = isset($this->settings['enabled']) ? (bool) $this->settings['enabled'] : false;
    }

    /**
     * Save component settings
     *
     * @since 1.0.0
     * @return bool Save result
     */
    protected function save_settings()
    {
        $this->settings['enabled'] = $this->enabled;
        return chatshop_update_option($this->id, '', $this->settings);
    }

    /**
     * Get setting value
     *
     * @since 1.0.0
     * @param string $key Setting key
     * @param mixed  $default Default value
     * @return mixed Setting value
     */
    protected function get_setting($key, $default = null)
    {
        return isset($this->settings[$key]) ? $this->settings[$key] : $default;
    }

    /**
     * Update setting value
     *
     * @since 1.0.0
     * @param string $key Setting key
     * @param mixed  $value Setting value
     * @return bool Update result
     */
    protected function update_setting($key, $value)
    {
        $this->settings[$key] = $value;
        return $this->save_settings();
    }

    /**
     * Delete setting
     *
     * @since 1.0.0
     * @param string $key Setting key
     * @return bool Delete result
     */
    protected function delete_setting($key)
    {
        if (isset($this->settings[$key])) {
            unset($this->settings[$key]);
            return $this->save_settings();
        }

        return false;
    }

    /**
     * Get all settings
     *
     * @since 1.0.0
     * @return array All settings
     */
    public function get_settings()
    {
        return $this->settings;
    }

    /**
     * Update all settings
     *
     * @since 1.0.0
     * @param array $settings New settings
     * @return bool Update result
     */
    public function update_settings($settings)
    {
        $this->settings = array_merge($this->settings, $settings);
        return $this->save_settings();
    }

    /**
     * Reset settings to defaults
     *
     * @since 1.0.0
     * @return bool Reset result
     */
    public function reset_settings()
    {
        $this->settings = array('enabled' => false);
        return $this->save_settings();
    }

    /**
     * Component cleanup
     *
     * Override this method in child classes for cleanup operations
     *
     * @since 1.0.0
     */
    public function cleanup()
    {
        // Default implementation does nothing
        // Child classes should override this for specific cleanup
    }

    /**
     * Get component status info
     *
     * @since 1.0.0
     * @return array Status information
     */
    public function get_status()
    {
        return array(
            'id' => $this->id,
            'name' => $this->name,
            'version' => $this->version,
            'enabled' => $this->enabled,
            'active' => $this->is_active(),
            'premium' => $this->premium,
            'dependencies' => $this->dependencies,
            'dependencies_met' => $this->check_dependencies()
        );
    }

    /**
     * Log component message
     *
     * @since 1.0.0
     * @param string $message Log message
     * @param string $level Log level
     * @param array  $context Additional context
     */
    protected function log($message, $level = 'info', $context = array())
    {
        $context['component'] = $this->id;
        chatshop_log($message, $level, $context);
    }
}
