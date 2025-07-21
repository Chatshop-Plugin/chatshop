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
     * Override this method in child classes for activation logic
     *
     * @since 1.0.0
     * @return bool Activation result
     */
    public function activate()
    {
        if ($this->initialized) {
            return true;
        }

        // Check dependencies
        if (!$this->check_dependencies()) {
            return false;
        }

        // Check premium requirements
        if ($this->premium && !$this->check_premium_license()) {
            return false;
        }

        // Component-specific activation
        $result = $this->do_activation();

        if ($result) {
            $this->initialized = true;
            $this->enabled = true;
            $this->save_settings();

            do_action('chatshop_component_activated', $this->id, $this);
        }

        return $result;
    }

    /**
     * Deactivate component
     *
     * Override this method in child classes for deactivation logic
     *
     * @since 1.0.0
     * @return bool Deactivation result
     */
    public function deactivate()
    {
        if (!$this->initialized) {
            return true;
        }

        // Component-specific deactivation
        $result = $this->do_deactivation();

        if ($result) {
            $this->initialized = false;
            $this->enabled = false;
            $this->save_settings();

            do_action('chatshop_component_deactivated', $this->id, $this);
        }

        return $result;
    }

    /**
     * Component activation logic
     *
     * Override in child classes
     *
     * @since 1.0.0
     * @return bool Activation result
     */
    protected function do_activation()
    {
        return true;
    }

    /**
     * Component deactivation logic
     *
     * Override in child classes
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
                $this->log("Dependency not met: {$dependency}", 'error');
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
        // Check for class existence
        if (strpos($dependency, 'class:') === 0) {
            $class_name = substr($dependency, 6);
            return class_exists($class_name);
        }

        // Check for function existence
        if (strpos($dependency, 'function:') === 0) {
            $function_name = substr($dependency, 9);
            return function_exists($function_name);
        }

        // Check for plugin
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
        unset($this->settings[$key]);
        return $this->save_settings();
    }

    /**
     * Register hooks
     *
     * Override in child classes to register WordPress hooks
     *
     * @since 1.0.0
     */
    protected function register_hooks()
    {
        // Default implementation - override in child classes
    }

    /**
     * Unregister hooks
     *
     * Override in child classes to unregister WordPress hooks
     *
     * @since 1.0.0
     */
    protected function unregister_hooks()
    {
        // Default implementation - override in child classes
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
        $log_message = sprintf('[%s] %s', strtoupper($this->id), $message);

        if (!empty($context)) {
            $log_message .= ' Context: ' . wp_json_encode($context);
        }

        chatshop_log($log_message, $level);
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
     * Check if component is initialized
     *
     * @since 1.0.0
     * @return bool Initialized status
     */
    public function is_initialized()
    {
        return $this->initialized;
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
     * Get component dependencies
     *
     * @since 1.0.0
     * @return array Dependencies
     */
    public function get_dependencies()
    {
        return $this->dependencies;
    }

    /**
     * Get component settings
     *
     * @since 1.0.0
     * @return array Settings
     */
    public function get_settings()
    {
        return $this->settings;
    }

    /**
     * Get component info
     *
     * @since 1.0.0
     * @return array Component information
     */
    public function get_info()
    {
        return array(
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'version' => $this->version,
            'enabled' => $this->enabled,
            'initialized' => $this->initialized,
            'premium' => $this->premium,
            'dependencies' => $this->dependencies,
            'dependencies_met' => $this->check_dependencies(),
            'license_valid' => !$this->premium || $this->check_premium_license()
        );
    }

    /**
     * Enable component
     *
     * @since 1.0.0
     * @return bool Enable result
     */
    public function enable()
    {
        if ($this->enabled) {
            return true;
        }

        return $this->activate();
    }

    /**
     * Disable component
     *
     * @since 1.0.0
     * @return bool Disable result
     */
    public function disable()
    {
        if (!$this->enabled) {
            return true;
        }

        return $this->deactivate();
    }

    /**
     * Reset component to defaults
     *
     * @since 1.0.0
     * @return bool Reset result
     */
    public function reset()
    {
        $this->settings = array();
        $this->enabled = false;
        $this->initialized = false;

        return $this->save_settings();
    }

    /**
     * Get configuration fields for admin
     *
     * Override in child classes to provide admin configuration
     *
     * @since 1.0.0
     * @return array Configuration fields
     */
    public function get_config_fields()
    {
        return array(
            'enabled' => array(
                'type' => 'checkbox',
                'label' => sprintf(__('Enable %s', 'chatshop'), $this->name),
                'description' => $this->description,
                'default' => false
            )
        );
    }

    /**
     * Validate configuration
     *
     * Override in child classes for custom validation
     *
     * @since 1.0.0
     * @param array $config Configuration to validate
     * @return array Validation result
     */
    public function validate_config($config)
    {
        $errors = array();

        // Basic validation - override in child classes
        if (isset($config['enabled']) && $config['enabled'] && !$this->check_dependencies()) {
            $errors[] = __('Component dependencies are not met', 'chatshop');
        }

        if ($this->premium && isset($config['enabled']) && $config['enabled'] && !$this->check_premium_license()) {
            $errors[] = __('Premium license required for this component', 'chatshop');
        }

        return array(
            'valid' => empty($errors),
            'errors' => $errors
        );
    }

    /**
     * Handle AJAX requests
     *
     * Override in child classes for AJAX handling
     *
     * @since 1.0.0
     * @param string $action AJAX action
     * @param array  $data Request data
     * @return array Response data
     */
    public function handle_ajax($action, $data)
    {
        return array(
            'success' => false,
            'message' => __('AJAX action not implemented', 'chatshop')
        );
    }

    /**
     * Component cleanup
     *
     * Override in child classes for cleanup logic
     *
     * @since 1.0.0
     */
    public function cleanup()
    {
        // Default implementation - override in child classes
    }

    /**
     * Get component status
     *
     * @since 1.0.0
     * @return string Component status
     */
    public function get_status()
    {
        if (!$this->check_dependencies()) {
            return 'dependencies_missing';
        }

        if ($this->premium && !$this->check_premium_license()) {
            return 'license_required';
        }

        if (!$this->enabled) {
            return 'disabled';
        }

        if (!$this->initialized) {
            return 'not_initialized';
        }

        return 'active';
    }
}
