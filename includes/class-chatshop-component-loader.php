<?php

/**
 * Component Loader for ChatShop Plugin
 *
 * Manages loading and initialization of modular components with enable/disable functionality.
 *
 * @package ChatShop
 * @since   1.0.0
 */

namespace ChatShop;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ChatShop Component Loader Class
 *
 * Loads modular components with dependency checking and enable/disable functionality.
 *
 * @since 1.0.0
 */
class ChatShop_Component_Loader
{
    /**
     * Registry instance
     *
     * @var ChatShop_Component_Registry
     * @since 1.0.0
     */
    private $registry;

    /**
     * Loaded components
     *
     * @var array
     * @since 1.0.0
     */
    private $loaded_components = array();

    /**
     * Component settings
     *
     * @var array
     * @since 1.0.0
     */
    private $component_settings = array();

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        $this->registry = new ChatShop_Component_Registry();
        $this->load_component_settings();
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     *
     * @since 1.0.0
     */
    private function init_hooks()
    {
        add_action('chatshop_init', array($this, 'load_components'));
        add_action('init', array($this, 'register_default_components'), 1);
    }

    /**
     * Load component settings from database
     *
     * @since 1.0.0
     */
    private function load_component_settings()
    {
        $this->component_settings = get_option('chatshop_component_settings', array(
            'payment' => true,
            'whatsapp' => true,
            'analytics' => true,
        ));
    }

    /**
     * Register default components
     *
     * @since 1.0.0
     */
    public function register_default_components()
    {
        $components = array(
            'payment' => array(
                'name' => __('Payment System', 'chatshop'),
                'class' => 'ChatShop\\Components\\Payment\\ChatShop_Payment_Manager',
                'file' => CHATSHOP_PLUGIN_DIR . 'components/payment/class-chatshop-payment-manager.php',
                'dependencies' => array(),
                'version' => '1.0.0'
            ),
            'whatsapp' => array(
                'name' => __('WhatsApp Integration', 'chatshop'),
                'class' => 'ChatShop\\Components\\WhatsApp\\ChatShop_WhatsApp_Manager',
                'file' => CHATSHOP_PLUGIN_DIR . 'components/whatsapp/class-chatshop-whatsapp-manager.php',
                'dependencies' => array(),
                'version' => '1.0.0'
            ),
            'analytics' => array(
                'name' => __('Analytics', 'chatshop'),
                'class' => 'ChatShop\\Components\\Analytics\\ChatShop_Analytics_Manager',
                'file' => CHATSHOP_PLUGIN_DIR . 'components/analytics/class-chatshop-analytics-manager.php',
                'dependencies' => array('payment', 'whatsapp'),
                'version' => '1.0.0'
            )
        );

        foreach ($components as $id => $component) {
            $this->registry->register($id, $component);
        }
    }

    /**
     * Load enabled components
     *
     * @since 1.0.0
     */
    public function load_components()
    {
        $components = $this->registry->get_all();

        foreach ($components as $id => $component) {
            if ($this->is_component_enabled($id) && $this->check_dependencies($id)) {
                $this->load_component($id, $component);
            }
        }

        do_action('chatshop_components_loaded', $this->loaded_components);
    }

    /**
     * Load a single component
     *
     * @param string $id Component ID
     * @param array  $component Component data
     * @since 1.0.0
     */
    private function load_component($id, $component)
    {
        if (isset($this->loaded_components[$id])) {
            return;
        }

        if (!file_exists($component['file'])) {
            error_log("ChatShop: Component file not found - {$component['file']}");
            return;
        }

        require_once $component['file'];

        if (!class_exists($component['class'])) {
            error_log("ChatShop: Component class not found - {$component['class']}");
            return;
        }

        try {
            $instance = new $component['class']();
            $this->loaded_components[$id] = $instance;

            do_action("chatshop_component_loaded_{$id}", $instance);
        } catch (Exception $e) {
            error_log("ChatShop: Error loading component {$id} - " . $e->getMessage());
        }
    }

    /**
     * Check if component is enabled
     *
     * @param string $id Component ID
     * @return bool
     * @since 1.0.0
     */
    public function is_component_enabled($id)
    {
        return isset($this->component_settings[$id]) && $this->component_settings[$id];
    }

    /**
     * Enable a component
     *
     * @param string $id Component ID
     * @return bool
     * @since 1.0.0
     */
    public function enable_component($id)
    {
        if (!$this->registry->is_registered($id)) {
            return false;
        }

        $this->component_settings[$id] = true;
        return update_option('chatshop_component_settings', $this->component_settings);
    }

    /**
     * Disable a component
     *
     * @param string $id Component ID
     * @return bool
     * @since 1.0.0
     */
    public function disable_component($id)
    {
        $this->component_settings[$id] = false;
        unset($this->loaded_components[$id]);
        return update_option('chatshop_component_settings', $this->component_settings);
    }

    /**
     * Check component dependencies
     *
     * @param string $id Component ID
     * @return bool
     * @since 1.0.0
     */
    private function check_dependencies($id)
    {
        $component = $this->registry->get($id);

        if (empty($component['dependencies'])) {
            return true;
        }

        foreach ($component['dependencies'] as $dependency) {
            if (!$this->is_component_enabled($dependency)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get loaded component instance
     *
     * @param string $id Component ID
     * @return mixed|null
     * @since 1.0.0
     */
    public function get_component($id)
    {
        return isset($this->loaded_components[$id]) ? $this->loaded_components[$id] : null;
    }

    /**
     * Get all loaded components
     *
     * @return array
     * @since 1.0.0
     */
    public function get_loaded_components()
    {
        return $this->loaded_components;
    }
}
