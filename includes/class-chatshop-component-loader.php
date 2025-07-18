<?php

/**
 * Component Loader
 *
 * Responsible for loading and managing plugin components.
 *
 * @link       https://modewebhost.com.ng
 * @since      1.0.0
 *
 * @package    ChatShop
 * @subpackage ChatShop/includes
 */

namespace ChatShop;

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Component Loader Class
 *
 * This class defines all code necessary to load and manage plugin components.
 *
 * @since      1.0.0
 * @package    ChatShop
 * @subpackage ChatShop/includes
 * @author     Modewebhost <info@modewebhost.com.ng>
 */
class ChatShop_Component_Loader
{
    /**
     * Registered components
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $components    Array of registered components.
     */
    private $components = array();

    /**
     * Component instances
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $component_instances    Array of component instances.
     */
    private $component_instances = array();

    /**
     * Component directory path
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $component_path    Path to components directory.
     */
    private $component_path;

    /**
     * Initialize the component loader
     *
     * @since    1.0.0
     */
    public function __construct()
    {
        $this->component_path = CHATSHOP_PLUGIN_DIR . 'components/';
        $this->register_core_components();
    }

    /**
     * Register core components
     *
     * @since    1.0.0
     */
    private function register_core_components()
    {
        // Payment component
        $this->register_component('payment', array(
            'name' => __('Payment System', 'chatshop'),
            'description' => __('Multi-gateway payment processing system', 'chatshop'),
            'version' => '1.0.0',
            'path' => $this->component_path . 'payment/',
            'main_class' => 'ChatShop_Payment_Manager',
            'dependencies' => array(),
            'enabled' => true,
            'priority' => 10
        ));

        // WhatsApp component
        $this->register_component('whatsapp', array(
            'name' => __('WhatsApp Integration', 'chatshop'),
            'description' => __('WhatsApp Business API integration', 'chatshop'),
            'version' => '1.0.0',
            'path' => $this->component_path . 'whatsapp/',
            'main_class' => 'ChatShop_WhatsApp_Manager',
            'dependencies' => array(),
            'enabled' => true,
            'priority' => 10
        ));

        // Analytics component
        $this->register_component('analytics', array(
            'name' => __('Analytics & Reporting', 'chatshop'),
            'description' => __('Revenue tracking and analytics system', 'chatshop'),
            'version' => '1.0.0',
            'path' => $this->component_path . 'analytics/',
            'main_class' => 'ChatShop_Analytics_Manager',
            'dependencies' => array('payment'),
            'enabled' => true,
            'priority' => 20
        ));

        // Campaign component
        $this->register_component('campaigns', array(
            'name' => __('Campaign Management', 'chatshop'),
            'description' => __('WhatsApp marketing campaigns', 'chatshop'),
            'version' => '1.0.0',
            'path' => $this->component_path . 'campaigns/',
            'main_class' => 'ChatShop_Campaign_Manager',
            'dependencies' => array('whatsapp'),
            'enabled' => true,
            'priority' => 15
        ));

        // Social Commerce component
        $this->register_component('social-commerce', array(
            'name' => __('Social Commerce', 'chatshop'),
            'description' => __('Social media commerce integration', 'chatshop'),
            'version' => '1.0.0',
            'path' => $this->component_path . 'social-commerce/',
            'main_class' => 'ChatShop_Social_Commerce_Manager',
            'dependencies' => array('payment', 'whatsapp'),
            'enabled' => true,
            'priority' => 25
        ));

        // Allow other plugins to register components
        do_action('chatshop_register_components', $this);
    }

    /**
     * Register a component
     *
     * @since    1.0.0
     * @param    string    $component_id    Component identifier.
     * @param    array     $component_data  Component configuration data.
     * @return   bool      True if registered successfully, false otherwise.
     */
    public function register_component($component_id, $component_data)
    {
        if (empty($component_id) || isset($this->components[$component_id])) {
            return false;
        }

        // Set default values
        $defaults = array(
            'name' => '',
            'description' => '',
            'version' => '1.0.0',
            'path' => '',
            'main_class' => '',
            'dependencies' => array(),
            'enabled' => true,
            'priority' => 10
        );

        $component_data = wp_parse_args($component_data, $defaults);
        $component_data['id'] = $component_id;

        // Validate component data
        if (empty($component_data['name']) || empty($component_data['path'])) {
            $this->log_error("Component '{$component_id}' is missing required data");
            return false;
        }

        $this->components[$component_id] = $component_data;

        $this->log_info("Component '{$component_id}' registered successfully");
        return true;
    }

    /**
     * Load components
     *
     * @since    1.0.0
     */
    public function load_components()
    {
        if (empty($this->components)) {
            $this->log_info('No components to load');
            return;
        }

        // Sort components by priority
        uasort($this->components, function ($a, $b) {
            return $a['priority'] - $b['priority'];
        });

        foreach ($this->components as $component_id => $component_data) {
            if (!$component_data['enabled']) {
                $this->log_info("Component '{$component_id}' is disabled, skipping");
                continue;
            }

            $this->load_component($component_id);
        }

        // Hook after all components are loaded
        do_action('chatshop_components_loaded', $this->component_instances);
    }

    /**
     * Load a specific component
     *
     * @since    1.0.0
     * @param    string    $component_id    Component identifier.
     * @return   bool      True if loaded successfully, false otherwise.
     */
    public function load_component($component_id)
    {
        if (!isset($this->components[$component_id])) {
            $this->log_error("Component '{$component_id}' is not registered");
            return false;
        }

        if (isset($this->component_instances[$component_id])) {
            $this->log_info("Component '{$component_id}' is already loaded");
            return true;
        }

        $component_data = $this->components[$component_id];

        // Check dependencies
        if (!$this->check_dependencies($component_id)) {
            $this->log_error("Component '{$component_id}' dependencies not met");
            return false;
        }

        // Load component files
        if (!$this->load_component_files($component_id)) {
            $this->log_error("Failed to load files for component '{$component_id}'");
            return false;
        }

        // Initialize component
        if (!empty($component_data['main_class'])) {
            $class_name = '\\ChatShop\\' . $component_data['main_class'];

            if (class_exists($class_name)) {
                try {
                    $instance = new $class_name();
                    $this->component_instances[$component_id] = $instance;

                    // Initialize component if method exists
                    if (method_exists($instance, 'init')) {
                        $instance->init();
                    }

                    $this->log_info("Component '{$component_id}' loaded successfully");
                    return true;
                } catch (Exception $e) {
                    $this->log_error("Error initializing component '{$component_id}': " . $e->getMessage());
                    return false;
                }
            } else {
                $this->log_error("Main class '{$class_name}' not found for component '{$component_id}'");
                return false;
            }
        }

        $this->log_info("Component '{$component_id}' loaded (no main class)");
        return true;
    }

    /**
     * Check component dependencies
     *
     * @since    1.0.0
     * @param    string    $component_id    Component identifier.
     * @return   bool      True if dependencies are met, false otherwise.
     */
    private function check_dependencies($component_id)
    {
        if (!isset($this->components[$component_id])) {
            return false;
        }

        $component_data = $this->components[$component_id];
        $dependencies = $component_data['dependencies'];

        if (empty($dependencies)) {
            return true;
        }

        foreach ($dependencies as $dependency) {
            if (!isset($this->component_instances[$dependency])) {
                // Try to load the dependency
                if (!$this->load_component($dependency)) {
                    $this->log_error("Failed to load dependency '{$dependency}' for component '{$component_id}'");
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Load component files
     *
     * @since    1.0.0
     * @param    string    $component_id    Component identifier.
     * @return   bool      True if files loaded successfully, false otherwise.
     */
    private function load_component_files($component_id)
    {
        if (!isset($this->components[$component_id])) {
            return false;
        }

        $component_data = $this->components[$component_id];
        $component_path = $component_data['path'];

        if (!is_dir($component_path)) {
            $this->log_error("Component directory '{$component_path}' does not exist");
            return false;
        }

        // Load main component file
        $main_file = $component_path . 'class-chatshop-' . str_replace('_', '-', strtolower($component_id)) . '-manager.php';

        if (file_exists($main_file)) {
            require_once $main_file;
        } else {
            // Try alternative naming convention
            $main_file = $component_path . 'class-' . str_replace('_', '-', strtolower($component_data['main_class'])) . '.php';
            if (file_exists($main_file)) {
                require_once $main_file;
            }
        }

        // Load all PHP files in the component directory
        $files = glob($component_path . '*.php');
        if ($files) {
            foreach ($files as $file) {
                if (basename($file) !== basename($main_file)) {
                    require_once $file;
                }
            }
        }

        // Load subdirectories
        $subdirs = glob($component_path . '*/', GLOB_ONLYDIR);
        if ($subdirs) {
            foreach ($subdirs as $subdir) {
                $subfiles = glob($subdir . '*.php');
                if ($subfiles) {
                    foreach ($subfiles as $subfile) {
                        require_once $subfile;
                    }
                }
            }
        }

        return true;
    }

    /**
     * Get component instance
     *
     * @since    1.0.0
     * @param    string    $component_id    Component identifier.
     * @return   object|null    Component instance or null if not found.
     */
    public function get_component_instance($component_id)
    {
        return isset($this->component_instances[$component_id]) ? $this->component_instances[$component_id] : null;
    }

    /**
     * Get all component instances
     *
     * @since    1.0.0
     * @return   array    Array of component instances.
     */
    public function get_all_component_instances()
    {
        return $this->component_instances;
    }

    /**
     * Get registered components
     *
     * @since    1.0.0
     * @return   array    Array of registered components.
     */
    public function get_registered_components()
    {
        return $this->components;
    }

    /**
     * Enable a component
     *
     * @since    1.0.0
     * @param    string    $component_id    Component identifier.
     * @return   bool      True if enabled successfully, false otherwise.
     */
    public function enable_component($component_id)
    {
        if (!isset($this->components[$component_id])) {
            return false;
        }

        $this->components[$component_id]['enabled'] = true;
        $this->log_info("Component '{$component_id}' enabled");
        return true;
    }

    /**
     * Disable a component
     *
     * @since    1.0.0
     * @param    string    $component_id    Component identifier.
     * @return   bool      True if disabled successfully, false otherwise.
     */
    public function disable_component($component_id)
    {
        if (!isset($this->components[$component_id])) {
            return false;
        }

        $this->components[$component_id]['enabled'] = false;

        // Remove instance if loaded
        if (isset($this->component_instances[$component_id])) {
            unset($this->component_instances[$component_id]);
        }

        $this->log_info("Component '{$component_id}' disabled");
        return true;
    }

    /**
     * Check if component is loaded
     *
     * @since    1.0.0
     * @param    string    $component_id    Component identifier.
     * @return   bool      True if loaded, false otherwise.
     */
    public function is_component_loaded($component_id)
    {
        return isset($this->component_instances[$component_id]);
    }

    /**
     * Check if component is enabled
     *
     * @since    1.0.0
     * @param    string    $component_id    Component identifier.
     * @return   bool      True if enabled, false otherwise.
     */
    public function is_component_enabled($component_id)
    {
        return isset($this->components[$component_id]) && $this->components[$component_id]['enabled'];
    }

    /**
     * Log an info message
     *
     * @since    1.0.0
     * @param    string    $message    Log message.
     */
    private function log_info($message)
    {
        // Use the global chatshop_log function defined in chatshop.php
        if (function_exists('\\ChatShop\\chatshop_log')) {
            \ChatShop\chatshop_log($message, 'info');
        }
    }

    /**
     * Log an error message
     *
     * @since    1.0.0
     * @param    string    $message    Log message.
     */
    private function log_error($message)
    {
        // Use the global chatshop_log function defined in chatshop.php
        if (function_exists('\\ChatShop\\chatshop_log')) {
            \ChatShop\chatshop_log($message, 'error');
        }
    }
}
