<?php

/**
 * Component Initialization Fix
 *
 * File: includes/chatshop-init-fix.php
 * 
 * This file contains the fix for proper component initialization.
 * Add this to your main plugin file after the component loader is initialized.
 *
 * @package ChatShop
 * @since 1.0.0
 */

namespace ChatShop;

/**
 * Fix component initialization timing
 * 
 * Add this function to ensure components are loaded at the right time
 */
function chatshop_fix_component_initialization()
{
    // Get the main plugin instance
    $chatshop = chatshop();

    if (!$chatshop) {
        chatshop_log('ChatShop instance not available for component fix', 'error');
        return;
    }

    // Get the component loader
    $component_loader = $chatshop->get_component_loader();

    if (!$component_loader) {
        chatshop_log('Component loader not available for initialization fix', 'error');
        return;
    }

    // Force load analytics component if premium is enabled
    if (chatshop_is_premium()) {
        // Check if analytics is already loaded
        if (!$component_loader->is_component_loaded('analytics')) {
            chatshop_log('Attempting to force-load analytics component', 'info');

            // Get component configuration from registry
            $registry = $component_loader->get_registry();
            $analytics_config = $registry->get_component('analytics');

            if ($analytics_config) {
                // Manually load the analytics component
                $analytics_file = $analytics_config['path'] . $analytics_config['main_file'];

                if (file_exists($analytics_file)) {
                    // Include the file
                    require_once $analytics_file;

                    // Check if class exists
                    if (class_exists($analytics_config['class_name'])) {
                        try {
                            // Create instance
                            $analytics = new $analytics_config['class_name']();

                            // Initialize it
                            if (method_exists($analytics, 'initialize')) {
                                $analytics->initialize();
                            }

                            // Store in component instances
                            $instances = $component_loader->get_all_instances();
                            $instances['analytics'] = $analytics;

                            chatshop_log('Analytics component force-loaded successfully', 'info');
                        } catch (\Exception $e) {
                            chatshop_log('Failed to force-load analytics: ' . $e->getMessage(), 'error');
                        }
                    } else {
                        chatshop_log('Analytics class not found after including file', 'error');
                    }
                } else {
                    chatshop_log('Analytics file not found: ' . $analytics_file, 'error');
                }
            } else {
                chatshop_log('Analytics component not registered', 'error');
            }
        }
    }
}

// Hook this fix to run after plugins are loaded
add_action('plugins_loaded', __NAMESPACE__ . '\\chatshop_fix_component_initialization', 20);

/**
 * Alternative helper function to get analytics component
 */
function chatshop_get_analytics_component()
{
    // Try standard method first
    $analytics = chatshop_get_component('analytics');

    if ($analytics) {
        return $analytics;
    }

    // If not found, try direct approach
    $chatshop = chatshop();
    if ($chatshop && method_exists($chatshop, 'get_analytics')) {
        return $chatshop->get_analytics();
    }

    return null;
}
