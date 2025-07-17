<?php

/**
 * Component Registry for ChatShop Plugin
 *
 * @package ChatShop
 * @subpackage ChatShop/includes
 * @since 1.0.0
 */

namespace ChatShop;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Component Registry Class
 *
 * Manages registration and loading of plugin components
 *
 * @since 1.0.0
 */
class ChatShop_Component_Registry
{
    /**
     * Registered components
     *
     * @since 1.0.0
     * @var array
     */
    private static $components = array();

    /**
     * Loaded components
     *
     * @since 1.0.0
     * @var array
     */
    private static $loaded_components = array();

    /**
     * Component dependencies
     *
     * @since 1.0.0
     * @var array
     */
    private static $dependencies = array();

    /**
     * Register a component
     *
     * @since 1.0.0
     * @param string $component_id   Unique component identifier
     * @param array  $args          Component arguments
     */
    public static function register_component($component_id, $args = array())
    {
        $defaults = array(
            'name'         => '',
            'description'  => '',
            'version'      => '1.0.0',
            'file'         => '',
            'class'        => '',
            'dependencies' => array(),
            'enabled'      => true,
            'priority'     => 10
        );

        $args = wp_parse_args($args, $defaults);

        // Validate required fields
        if (empty($args['file']) || empty($args['class'])) {
            return false;
        }

        self::$components[$component_id] = $args;

        // Store dependencies
        if (!empty($args['dependencies'])) {
            self::$dependencies[$component_id] = $args['dependencies'];
        }

        return true;
    }

    /**
     * Get all registered components
     *
     * @since 1.0.0
     * @return array
     */
    public static function get_components()
    {
        return self::$components;
    }

    /**
     * Get a specific component
     *
     * @since 1.0.0
     * @param string $component_id Component identifier
     * @return array|false
     */
    public static function get_component($component_id)
    {
        return isset(self::$components[$component_id]) ? self::$components[$component_id] : false;
    }

    /**
     * Check if component is registered
     *
     * @since 1.0.0
     * @param string $component_id Component identifier
     * @return bool
     */
    public static function is_registered($component_id)
    {
        return isset(self::$components[$component_id]);
    }

    /**
     * Check if component is loaded
     *
     * @since 1.0.0
     * @param string $component_id Component identifier
     * @return bool
     */
    public static function is_loaded($component_id)
    {
        return isset(self::$loaded_components[$component_id]);
    }

    /**
     * Mark component as loaded
     *
     * @since 1.0.0
     * @param string $component_id Component identifier
     * @param object $instance     Component instance
     */
    public static function mark_loaded($component_id, $instance = null)
    {
        self::$loaded_components[$component_id] = $instance;
    }

    /**
     * Get loaded component instance
     *
     * @since 1.0.0
     * @param string $component_id Component identifier
     * @return object|null
     */
    public static function get_loaded_component($component_id)
    {
        return isset(self::$loaded_components[$component_id]) ? self::$loaded_components[$component_id] : null;
    }

    /**
     * Get component dependencies
     *
     * @since 1.0.0
     * @param string $component_id Component identifier
     * @return array
     */
    public static function get_dependencies($component_id)
    {
        return isset(self::$dependencies[$component_id]) ? self::$dependencies[$component_id] : array();
    }

    /**
     * Check if component dependencies are met
     *
     * @since 1.0.0
     * @param string $component_id Component identifier
     * @return bool
     */
    public static function dependencies_met($component_id)
    {
        $dependencies = self::get_dependencies($component_id);

        if (empty($dependencies)) {
            return true;
        }

        foreach ($dependencies as $dependency) {
            if (!self::is_loaded($dependency)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get components ordered by priority and dependencies
     *
     * @since 1.0.0
     * @return array
     */
    public static function get_load_order()
    {
        $components = self::$components;
        $loaded = array();
        $order = array();

        // Sort by priority first
        uasort($components, function ($a, $b) {
            return $a['priority'] - $b['priority'];
        });

        // Resolve dependencies
        while (!empty($components)) {
            $progress = false;

            foreach ($components as $id => $component) {
                // Skip disabled components
                if (!$component['enabled']) {
                    unset($components[$id]);
                    $progress = true;
                    continue;
                }

                // Check if dependencies are loaded
                $dependencies = self::get_dependencies($id);
                $can_load = true;

                foreach ($dependencies as $dependency) {
                    if (!in_array($dependency, $loaded)) {
                        $can_load = false;
                        break;
                    }
                }

                if ($can_load) {
                    $order[] = $id;
                    $loaded[] = $id;
                    unset($components[$id]);
                    $progress = true;
                }
            }

            // Prevent infinite loop if dependencies can't be resolved
            if (!$progress) {
                break;
            }
        }

        return $order;
    }

    /**
     * Enable component
     *
     * @since 1.0.0
     * @param string $component_id Component identifier
     * @return bool
     */
    public static function enable_component($component_id)
    {
        if (isset(self::$components[$component_id])) {
            self::$components[$component_id]['enabled'] = true;
            return true;
        }
        return false;
    }

    /**
     * Disable component
     *
     * @since 1.0.0
     * @param string $component_id Component identifier
     * @return bool
     */
    public static function disable_component($component_id)
    {
        if (isset(self::$components[$component_id])) {
            self::$components[$component_id]['enabled'] = false;
            return true;
        }
        return false;
    }

    /**
     * Unregister component
     *
     * @since 1.0.0
     * @param string $component_id Component identifier
     * @return bool
     */
    public static function unregister_component($component_id)
    {
        if (isset(self::$components[$component_id])) {
            unset(self::$components[$component_id]);
            unset(self::$loaded_components[$component_id]);
            unset(self::$dependencies[$component_id]);
            return true;
        }
        return false;
    }

    /**
     * Get component status
     *
     * @since 1.0.0
     * @param string $component_id Component identifier
     * @return array
     */
    public static function get_component_status($component_id)
    {
        if (!self::is_registered($component_id)) {
            return array(
                'registered' => false,
                'loaded'     => false,
                'enabled'    => false
            );
        }

        $component = self::get_component($component_id);

        return array(
            'registered' => true,
            'loaded'     => self::is_loaded($component_id),
            'enabled'    => $component['enabled'],
            'dependencies_met' => self::dependencies_met($component_id)
        );
    }
}
