<?php

/**
 * Analytics Debug Script
 *
 * File: admin/debug-analytics.php
 * 
 * This file helps debug why the analytics component is not loading.
 * Add this temporarily to identify the issue.
 *
 * @package ChatShop
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Debug analytics component loading
 * Add this function call somewhere in your admin area to see debug info
 */
function chatshop_debug_analytics()
{
    echo '<div class="wrap">';
    echo '<h1>ChatShop Analytics Debug Information</h1>';

    // 1. Check if ChatShop is loaded
    echo '<h2>1. ChatShop Plugin Status</h2>';
    echo '<pre>';
    echo 'ChatShop Loaded: ' . (chatshop_is_loaded() ? 'YES' : 'NO') . "\n";
    echo 'ChatShop Instance: ' . (chatshop() ? 'Available' : 'Not Available') . "\n";
    echo '</pre>';

    // 2. Check Premium Status
    echo '<h2>2. Premium Status</h2>';
    echo '<pre>';
    echo 'Premium Enabled: ' . (chatshop_is_premium() ? 'YES' : 'NO') . "\n";
    $premium_options = get_option('chatshop_premium_features', array());
    echo 'Premium Options: ' . print_r($premium_options, true) . "\n";
    echo '</pre>';

    // 3. Check Component Loader
    echo '<h2>3. Component Loader</h2>';
    echo '<pre>';
    $plugin = chatshop();
    if ($plugin) {
        $component_loader = $plugin->get_component_loader();
        echo 'Component Loader: ' . ($component_loader ? 'Available' : 'Not Available') . "\n";

        if ($component_loader) {
            echo 'Loaded Components Count: ' . $component_loader->get_loaded_count() . "\n";
            echo 'Loading Order: ' . print_r($component_loader->get_loading_order(), true) . "\n";

            // Check if analytics is loaded
            echo 'Analytics Loaded: ' . ($component_loader->is_component_loaded('analytics') ? 'YES' : 'NO') . "\n";

            // Get all loaded components
            $all_components = $component_loader->get_all_instances();
            echo 'All Loaded Components: ' . print_r(array_keys($all_components), true) . "\n";
        }
    }
    echo '</pre>';

    // 4. Check Analytics Component
    echo '<h2>4. Analytics Component</h2>';
    echo '<pre>';
    $analytics = chatshop_get_component('analytics');
    echo 'Analytics Component: ' . ($analytics ? 'Available' : 'Not Available') . "\n";

    if ($analytics) {
        echo 'Analytics Class: ' . get_class($analytics) . "\n";
        echo 'Analytics ID: ' . $analytics->get_id() . "\n";
        echo 'Analytics Name: ' . $analytics->get_name() . "\n";
        echo 'Analytics Enabled: ' . ($analytics->is_enabled() ? 'YES' : 'NO') . "\n";
        echo 'Analytics Premium Only: ' . ($analytics->is_premium_only() ? 'YES' : 'NO') . "\n";
        echo 'Analytics Initialized: ' . ($analytics->is_initialized() ? 'YES' : 'NO') . "\n";

        if (method_exists($analytics, 'get_status')) {
            echo 'Analytics Status: ' . print_r($analytics->get_status(), true) . "\n";
        }
    }
    echo '</pre>';

    // 5. Check Component Registry
    echo '<h2>5. Component Registry</h2>';
    echo '<pre>';
    if ($component_loader) {
        $registry = $component_loader->get_registry();
        if ($registry) {
            $analytics_config = $registry->get_component('analytics');
            echo 'Analytics Registration: ' . print_r($analytics_config, true) . "\n";

            // Check all registered components
            $all_registered = $registry->get_all_components();
            echo 'All Registered Components: ' . print_r(array_keys($all_registered), true) . "\n";
        }
    }
    echo '</pre>';

    // 6. Check File System
    echo '<h2>6. File System Check</h2>';
    echo '<pre>';
    $analytics_file = CHATSHOP_PLUGIN_DIR . 'components/analytics/class-chatshop-analytics.php';
    echo 'Analytics File Path: ' . $analytics_file . "\n";
    echo 'Analytics File Exists: ' . (file_exists($analytics_file) ? 'YES' : 'NO') . "\n";
    echo 'Analytics File Readable: ' . (is_readable($analytics_file) ? 'YES' : 'NO') . "\n";

    $abstract_file = CHATSHOP_PLUGIN_DIR . 'includes/abstracts/abstract-chatshop-component.php';
    echo 'Abstract Component File: ' . $abstract_file . "\n";
    echo 'Abstract File Exists: ' . (file_exists($abstract_file) ? 'YES' : 'NO') . "\n";
    echo '</pre>';

    // 7. Check Class Loading
    echo '<h2>7. Class Loading</h2>';
    echo '<pre>';
    echo 'ChatShop_Analytics class exists: ' . (class_exists('ChatShop\\ChatShop_Analytics') ? 'YES' : 'NO') . "\n";
    echo 'ChatShop_Abstract_Component class exists: ' . (class_exists('ChatShop\\ChatShop_Abstract_Component') ? 'YES' : 'NO') . "\n";
    echo '</pre>';

    // 8. Check Database Tables
    echo '<h2>8. Database Tables</h2>';
    echo '<pre>';
    global $wpdb;
    $analytics_table = $wpdb->prefix . 'chatshop_analytics';
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$analytics_table'");
    echo 'Analytics Table: ' . $analytics_table . "\n";
    echo 'Table Exists: ' . ($table_exists ? 'YES' : 'NO') . "\n";
    echo '</pre>';

    // 9. Check Options
    echo '<h2>9. Analytics Options</h2>';
    echo '<pre>';
    $analytics_settings = get_option('chatshop_analytics_settings', array());
    echo 'Analytics Settings: ' . print_r($analytics_settings, true) . "\n";

    $analytics_enabled = chatshop_get_option('analytics', 'enabled', true);
    echo 'Analytics Enabled in Options: ' . ($analytics_enabled ? 'YES' : 'NO') . "\n";
    echo '</pre>';

    // 10. Recent Logs
    echo '<h2>10. Recent ChatShop Logs</h2>';
    echo '<pre>';
    $log_file = WP_CONTENT_DIR . '/chatshop-logs/chatshop-' . date('Y-m-d') . '.log';
    if (file_exists($log_file)) {
        $logs = file_get_contents($log_file);
        $log_lines = explode("\n", $logs);
        $analytics_logs = array_filter($log_lines, function ($line) {
            return stripos($line, 'analytics') !== false || stripos($line, 'component') !== false;
        });
        echo 'Analytics-related logs:' . "\n";
        echo implode("\n", array_slice($analytics_logs, -20)); // Last 20 relevant log entries
    } else {
        echo 'Log file not found: ' . $log_file;
    }
    echo '</pre>';

    echo '</div>';
}

// Add debug page to admin menu
add_action('admin_menu', function () {
    add_submenu_page(
        'chatshop',
        'Analytics Debug',
        'Analytics Debug',
        'manage_options',
        'chatshop-analytics-debug',
        'chatshop_debug_analytics'
    );
}, 99);
