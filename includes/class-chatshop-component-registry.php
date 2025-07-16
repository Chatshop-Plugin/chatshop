<?php

/**
 * Component Registry for ChatShop Plugin
 *
 * Manages registration and metadata for modular components.
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
 * ChatShop Component Registry Class
 *
 * Registers components with metadata including name, version, and dependencies.
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
     * Required component fields
     *
     * @var array
     * @since 1.0.0
     */
    private $required_fields = array('name', 'class', 'file', 'version');

    /**
     * Register a component
     *
     * @param string $id Component unique identifier
     * @param array  $metadata Component metadata
     * @return bool
     * @since 1.0.0
     */
    public function register($id, $metadata)
    {
        if (empty($id) || !is_string($id)) {
            return false;
        }

        if (!$this->validate_metadata($metadata)) {
            return false;
        }

        $metadata = $this->sanitize_metadata($metadata);

        $this->components[$id] = wp_parse_args($metadata, array(
            'dependencies' => array(),
            'description' => '',
            'author' => '',
            'priority' => 10,
        ));

        do_action('chatshop_component_registered', $id, $this->components[$id]);

        return true;
    }

    /**
     * Unregister a component
     *
     * @param string $id Component ID
     * @return bool
     * @since 1.0.0
     */
    public function unregister($id)
    {
        if (!$this->is_registered($id)) {
            return false;
        }

        unset($this->components[$id]);

        do_action('chatshop_component_unregistered', $id);

        return true;
    }

    /**
     * Check if a component is registered
     *
     * @param string $id Component ID
     * @return bool
     * @since 1.0.0
     */
    public function is_registered($id)
    {
        return isset($this->components[$id]);
    }

    /**
     * Get a registered component
     *
     * @param string $id Component ID
     * @return array|null
     * @since 1.0.0
     */
    public function get($id)
    {
        return $this->is_registered($id) ? $this->components[$id] : null;
    }

    /**
     * Get all registered components
     *
     * @return array
     * @since 1.0.0
     */
    public function get_all()
    {
        return $this->components;
    }

    /**
     * Get components sorted by priority
     *
     * @return array
     * @since 1.0.0
     */
    public function get_sorted()
    {
        $components = $this->components;

        uasort($components, function ($a, $b) {
            return $a['priority'] - $b['priority'];
        });

        return $components;
    }

    /**
     * Get component count
     *
     * @return int
     * @since 1.0.0
     */
    public function count()
    {
        return count($this->components);
    }

    /**
     * Get components by dependency
     *
     * @param string $dependency Dependency component ID
     * @return array
     * @since 1.0.0
     */
    public function get_dependents($dependency)
    {
        $dependents = array();

        foreach ($this->components as $id => $component) {
            if (in_array($dependency, $component['dependencies'], true)) {
                $dependents[$id] = $component;
            }
        }

        return $dependents;
    }

    /**
     * Check for circular dependencies
     *
     * @param string $id Component ID
     * @param array  $chain Dependency chain
     * @return bool
     * @since 1.0.0
     */
    public function has_circular_dependency($id, $chain = array())
    {
        if (in_array($id, $chain, true)) {
            return true;
        }

        $component = $this->get($id);
        if (!$component || empty($component['dependencies'])) {
            return false;
        }

        $chain[] = $id;

        foreach ($component['dependencies'] as $dependency) {
            if ($this->has_circular_dependency($dependency, $chain)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate component metadata
     *
     * @param array $metadata Component metadata
     * @return bool
     * @since 1.0.0
     */
    private function validate_metadata($metadata)
    {
        if (!is_array($metadata)) {
            return false;
        }

        foreach ($this->required_fields as $field) {
            if (!isset($metadata[$field]) || empty($metadata[$field])) {
                return false;
            }
        }

        // Validate dependencies format
        if (isset($metadata['dependencies']) && !is_array($metadata['dependencies'])) {
            return false;
        }

        // Validate class exists or file path
        if (!class_exists($metadata['class']) && !file_exists($metadata['file'])) {
            return false;
        }

        return true;
    }

    /**
     * Sanitize component metadata
     *
     * @param array $metadata Component metadata
     * @return array
     * @since 1.0.0
     */
    private function sanitize_metadata($metadata)
    {
        $sanitized = array();

        $sanitized['name'] = sanitize_text_field($metadata['name']);
        $sanitized['class'] = sanitize_text_field($metadata['class']);
        $sanitized['file'] = sanitize_text_field($metadata['file']);
        $sanitized['version'] = sanitize_text_field($metadata['version']);

        if (isset($metadata['description'])) {
            $sanitized['description'] = sanitize_textarea_field($metadata['description']);
        }

        if (isset($metadata['author'])) {
            $sanitized['author'] = sanitize_text_field($metadata['author']);
        }

        if (isset($metadata['priority'])) {
            $sanitized['priority'] = intval($metadata['priority']);
        }

        if (isset($metadata['dependencies']) && is_array($metadata['dependencies'])) {
            $sanitized['dependencies'] = array_map('sanitize_text_field', $metadata['dependencies']);
        }

        return $sanitized;
    }

    /**
     * Clear all registered components
     *
     * @since 1.0.0
     */
    public function clear()
    {
        $this->components = array();
        do_action('chatshop_components_cleared');
    }
}
