<?php

/**
 * ChatShop Loader Class
 *
 * Registers all actions and filters for the plugin.
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
 * ChatShop Loader Class
 *
 * @since 1.0.0
 */
class ChatShop_Loader
{
    /**
     * Array of actions registered with WordPress
     *
     * @var array
     * @since 1.0.0
     */
    protected $actions = array();

    /**
     * Array of filters registered with WordPress
     *
     * @var array
     * @since 1.0.0
     */
    protected $filters = array();

    /**
     * Array of shortcodes registered with WordPress
     *
     * @var array
     * @since 1.0.0
     */
    protected $shortcodes = array();

    /**
     * Add a new action to the collection to be registered with WordPress
     *
     * @since 1.0.0
     * @param string $hook The name of the WordPress action that is being registered
     * @param object $component A reference to the instance of the object on which the action is defined
     * @param string $callback The name of the function definition on the $component
     * @param int $priority Optional. The priority at which the function should be fired. Default is 10
     * @param int $accepted_args Optional. The number of arguments that should be passed to the $callback. Default is 1
     */
    public function add_action($hook, $component, $callback, $priority = 10, $accepted_args = 1)
    {
        $this->actions = $this->add($this->actions, $hook, $component, $callback, $priority, $accepted_args);
    }

    /**
     * Add a new filter to the collection to be registered with WordPress
     *
     * @since 1.0.0
     * @param string $hook The name of the WordPress filter that is being registered
     * @param object $component A reference to the instance of the object on which the filter is defined
     * @param string $callback The name of the function definition on the $component
     * @param int $priority Optional. The priority at which the function should be fired. Default is 10
     * @param int $accepted_args Optional. The number of arguments that should be passed to the $callback. Default is 1
     */
    public function add_filter($hook, $component, $callback, $priority = 10, $accepted_args = 1)
    {
        $this->filters = $this->add($this->filters, $hook, $component, $callback, $priority, $accepted_args);
    }

    /**
     * Add a new shortcode to the collection to be registered with WordPress
     *
     * @since 1.0.0
     * @param string $tag The name of the new shortcode
     * @param object $component A reference to the instance of the object on which the shortcode is defined
     * @param string $callback The name of the function that defines the shortcode
     */
    public function add_shortcode($tag, $component, $callback)
    {
        $this->shortcodes = $this->add($this->shortcodes, $tag, $component, $callback);
    }

    /**
     * A utility function that is used to register the actions and hooks into a single
     * collection
     *
     * @since 1.0.0
     * @param array $hooks The collection of hooks that is being registered (that is, actions or filters)
     * @param string $hook The name of the WordPress filter that is being registered
     * @param object $component A reference to the instance of the object on which the filter is defined
     * @param string $callback The name of the function definition on the $component
     * @param int $priority The priority at which the function should be fired
     * @param int $accepted_args The number of arguments that should be passed to the $callback
     * @return array The collection of actions and filters registered with WordPress
     */
    private function add($hooks, $hook, $component, $callback, $priority = 10, $accepted_args = 1)
    {
        $hooks[] = array(
            'hook' => $hook,
            'component' => $component,
            'callback' => $callback,
            'priority' => $priority,
            'accepted_args' => $accepted_args
        );

        return $hooks;
    }

    /**
     * Register the filters and actions with WordPress
     *
     * @since 1.0.0
     */
    public function run()
    {
        // Register actions
        foreach ($this->actions as $hook) {
            add_action(
                $hook['hook'],
                array($hook['component'], $hook['callback']),
                $hook['priority'],
                $hook['accepted_args']
            );
        }

        // Register filters
        foreach ($this->filters as $hook) {
            add_filter(
                $hook['hook'],
                array($hook['component'], $hook['callback']),
                $hook['priority'],
                $hook['accepted_args']
            );
        }

        // Register shortcodes
        foreach ($this->shortcodes as $hook) {
            add_shortcode(
                $hook['hook'],
                array($hook['component'], $hook['callback'])
            );
        }
    }

    /**
     * Get all registered actions
     *
     * @since 1.0.0
     * @return array Registered actions
     */
    public function get_actions()
    {
        return $this->actions;
    }

    /**
     * Get all registered filters
     *
     * @since 1.0.0
     * @return array Registered filters
     */
    public function get_filters()
    {
        return $this->filters;
    }

    /**
     * Get all registered shortcodes
     *
     * @since 1.0.0
     * @return array Registered shortcodes
     */
    public function get_shortcodes()
    {
        return $this->shortcodes;
    }

    /**
     * Remove an action from the collection
     *
     * @since 1.0.0
     * @param string $hook The name of the WordPress action
     * @param object $component The component object
     * @param string $callback The callback function name
     * @return bool True if removed, false if not found
     */
    public function remove_action($hook, $component, $callback)
    {
        return $this->remove_hook($this->actions, $hook, $component, $callback);
    }

    /**
     * Remove a filter from the collection
     *
     * @since 1.0.0
     * @param string $hook The name of the WordPress filter
     * @param object $component The component object
     * @param string $callback The callback function name
     * @return bool True if removed, false if not found
     */
    public function remove_filter($hook, $component, $callback)
    {
        return $this->remove_hook($this->filters, $hook, $component, $callback);
    }

    /**
     * Remove a shortcode from the collection
     *
     * @since 1.0.0
     * @param string $tag The shortcode tag
     * @param object $component The component object
     * @param string $callback The callback function name
     * @return bool True if removed, false if not found
     */
    public function remove_shortcode($tag, $component, $callback)
    {
        return $this->remove_hook($this->shortcodes, $tag, $component, $callback);
    }

    /**
     * Remove a hook from the specified collection
     *
     * @since 1.0.0
     * @param array &$hooks Reference to the hooks collection
     * @param string $hook The hook name
     * @param object $component The component object
     * @param string $callback The callback function name
     * @return bool True if removed, false if not found
     */
    private function remove_hook(&$hooks, $hook, $component, $callback)
    {
        foreach ($hooks as $key => $registered_hook) {
            if (
                $registered_hook['hook'] === $hook &&
                $registered_hook['component'] === $component &&
                $registered_hook['callback'] === $callback
            ) {
                unset($hooks[$key]);
                return true;
            }
        }
        return false;
    }

    /**
     * Check if a specific hook is registered
     *
     * @since 1.0.0
     * @param string $type Hook type ('action', 'filter', or 'shortcode')
     * @param string $hook The hook name
     * @param object $component The component object (optional)
     * @param string $callback The callback function name (optional)
     * @return bool True if registered, false otherwise
     */
    public function is_hook_registered($type, $hook, $component = null, $callback = null)
    {
        $hooks = array();

        switch ($type) {
            case 'action':
                $hooks = $this->actions;
                break;
            case 'filter':
                $hooks = $this->filters;
                break;
            case 'shortcode':
                $hooks = $this->shortcodes;
                break;
            default:
                return false;
        }

        foreach ($hooks as $registered_hook) {
            if ($registered_hook['hook'] === $hook) {
                // If component and callback are specified, check them too
                if ($component !== null && $registered_hook['component'] !== $component) {
                    continue;
                }
                if ($callback !== null && $registered_hook['callback'] !== $callback) {
                    continue;
                }
                return true;
            }
        }

        return false;
    }

    /**
     * Get hook count by type
     *
     * @since 1.0.0
     * @param string $type Hook type ('action', 'filter', or 'shortcode')
     * @return int Number of registered hooks
     */
    public function get_hook_count($type)
    {
        switch ($type) {
            case 'action':
                return count($this->actions);
            case 'filter':
                return count($this->filters);
            case 'shortcode':
                return count($this->shortcodes);
            default:
                return 0;
        }
    }

    /**
     * Get all hooks summary
     *
     * @since 1.0.0
     * @return array Summary of all registered hooks
     */
    public function get_hooks_summary()
    {
        return array(
            'actions' => count($this->actions),
            'filters' => count($this->filters),
            'shortcodes' => count($this->shortcodes),
            'total' => count($this->actions) + count($this->filters) + count($this->shortcodes)
        );
    }

    /**
     * Clear all hooks
     *
     * @since 1.0.0
     */
    public function clear_all_hooks()
    {
        $this->actions = array();
        $this->filters = array();
        $this->shortcodes = array();
    }

    /**
     * Export hooks for debugging
     *
     * @since 1.0.0
     * @return array All registered hooks
     */
    public function export_hooks()
    {
        return array(
            'actions' => $this->actions,
            'filters' => $this->filters,
            'shortcodes' => $this->shortcodes
        );
    }
}
