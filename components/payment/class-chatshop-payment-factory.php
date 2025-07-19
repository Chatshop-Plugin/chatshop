<?php

/**
 * Payment Factory Class
 *
 * Factory pattern implementation for creating payment gateway instances.
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
 * ChatShop Payment Factory Class
 *
 * @since 1.0.0
 */
class ChatShop_Payment_Factory
{
    /**
     * Registered payment gateways
     *
     * @var array
     * @since 1.0.0
     */
    private static $gateways = array();

    /**
     * Gateway instances cache
     *
     * @var array
     * @since 1.0.0
     */
    private static $instances = array();

    /**
     * Initialize the payment factory
     *
     * @since 1.0.0
     */
    public static function init()
    {
        // Register built-in gateways when they're available
        add_action('chatshop_load_payment_gateways', array(__CLASS__, 'register_core_gateways'));

        // Allow third-party gateway registration
        do_action('chatshop_register_payment_gateways', __CLASS__);

        self::log_info('Payment factory initialized');
    }

    /**
     * Register core payment gateways
     *
     * @since 1.0.0
     */
    public static function register_core_gateways()
    {
        // This will be called when gateway files are loaded
        // For now, we just log that we're ready to register gateways
        self::log_info('Ready to register core payment gateways');
    }

    /**
     * Register a payment gateway
     *
     * @since 1.0.0
     * @param string $gateway_id Gateway identifier
     * @param array $config Gateway configuration
     * @return bool True if registered successfully, false otherwise
     */
    public static function register_gateway($gateway_id, $config)
    {
        // Validate required fields
        $required_fields = array('class_name', 'name');
        foreach ($required_fields as $field) {
            if (empty($config[$field])) {
                self::log_error("Gateway registration failed: Missing required field '{$field}' for gateway '{$gateway_id}'");
                return false;
            }
        }

        // Set defaults
        $defaults = array(
            'description' => '',
            'supports' => array(),
            'version' => '1.0.0',
            'enabled' => false,
            'priority' => 10,
            'countries' => array(),
            'currencies' => array()
        );

        $config = array_merge($defaults, $config);

        // Validate gateway ID
        if (!self::is_valid_gateway_id($gateway_id)) {
            self::log_error("Invalid gateway ID: {$gateway_id}");
            return false;
        }

        // Check if already registered
        if (isset(self::$gateways[$gateway_id])) {
            self::log_error("Gateway already registered: {$gateway_id}");
            return false;
        }

        // Store gateway configuration
        self::$gateways[$gateway_id] = $config;

        self::log_info("Payment gateway registered: {$gateway_id}");
        return true;
    }

    /**
     * Unregister a payment gateway
     *
     * @since 1.0.0
     * @param string $gateway_id Gateway identifier
     * @return bool True if unregistered successfully, false otherwise
     */
    public static function unregister_gateway($gateway_id)
    {
        if (!isset(self::$gateways[$gateway_id])) {
            return false;
        }

        // Remove from instances cache
        if (isset(self::$instances[$gateway_id])) {
            unset(self::$instances[$gateway_id]);
        }

        unset(self::$gateways[$gateway_id]);
        self::log_info("Payment gateway unregistered: {$gateway_id}");
        return true;
    }

    /**
     * Create a payment gateway instance
     *
     * @since 1.0.0
     * @param string $gateway_id Gateway identifier
     * @return object|false Gateway instance or false on failure
     */
    public static function create_gateway($gateway_id)
    {
        // Check if gateway is registered
        if (!isset(self::$gateways[$gateway_id])) {
            self::log_error("Gateway not registered: {$gateway_id}");
            return false;
        }

        // Return cached instance if available
        if (isset(self::$instances[$gateway_id])) {
            return self::$instances[$gateway_id];
        }

        $config = self::$gateways[$gateway_id];
        $class_name = "\\ChatShop\\{$config['class_name']}";

        // Check if class exists
        if (!class_exists($class_name)) {
            self::log_error("Gateway class not found: {$class_name}");
            return false;
        }

        try {
            // Create instance
            $instance = new $class_name($gateway_id);

            // Validate instance
            if (!$instance instanceof \ChatShop\ChatShop_Payment_Gateway) {
                self::log_error("Gateway class must extend ChatShop_Payment_Gateway: {$class_name}");
                return false;
            }

            // Cache instance
            self::$instances[$gateway_id] = $instance;

            self::log_info("Payment gateway instance created: {$gateway_id}");
            return $instance;
        } catch (\Exception $e) {
            self::log_error("Failed to create gateway instance '{$gateway_id}': " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get available payment gateways
     *
     * @since 1.0.0
     * @param bool $enabled_only Return only enabled gateways
     * @return array Array of gateway configurations
     */
    public static function get_available_gateways($enabled_only = false)
    {
        if (!$enabled_only) {
            return self::$gateways;
        }

        $enabled_gateways = array();
        foreach (self::$gateways as $gateway_id => $config) {
            if (self::is_gateway_enabled($gateway_id)) {
                $enabled_gateways[$gateway_id] = $config;
            }
        }

        return $enabled_gateways;
    }

    /**
     * Get enabled payment gateways sorted by priority
     *
     * @since 1.0.0
     * @return array Array of enabled gateway configurations
     */
    public static function get_enabled_gateways()
    {
        $enabled = self::get_available_gateways(true);

        // Sort by priority
        uasort($enabled, function ($a, $b) {
            return $a['priority'] - $b['priority'];
        });

        return $enabled;
    }

    /**
     * Check if gateway is enabled
     *
     * @since 1.0.0
     * @param string $gateway_id Gateway identifier
     * @return bool True if enabled, false otherwise
     */
    public static function is_gateway_enabled($gateway_id)
    {
        if (!isset(self::$gateways[$gateway_id])) {
            return false;
        }

        // Check user settings
        $enabled_gateways = get_option('chatshop_enabled_gateways', array());
        if (isset($enabled_gateways[$gateway_id])) {
            return (bool) $enabled_gateways[$gateway_id];
        }

        // Fall back to default
        return (bool) self::$gateways[$gateway_id]['enabled'];
    }

    /**
     * Enable a payment gateway
     *
     * @since 1.0.0
     * @param string $gateway_id Gateway identifier
     * @return bool True if enabled successfully, false otherwise
     */
    public static function enable_gateway($gateway_id)
    {
        if (!isset(self::$gateways[$gateway_id])) {
            return false;
        }

        $enabled_gateways = get_option('chatshop_enabled_gateways', array());
        $enabled_gateways[$gateway_id] = true;

        update_option('chatshop_enabled_gateways', $enabled_gateways);
        self::log_info("Payment gateway enabled: {$gateway_id}");

        return true;
    }

    /**
     * Disable a payment gateway
     *
     * @since 1.0.0
     * @param string $gateway_id Gateway identifier
     * @return bool True if disabled successfully, false otherwise
     */
    public static function disable_gateway($gateway_id)
    {
        if (!isset(self::$gateways[$gateway_id])) {
            return false;
        }

        $enabled_gateways = get_option('chatshop_enabled_gateways', array());
        $enabled_gateways[$gateway_id] = false;

        update_option('chatshop_enabled_gateways', $enabled_gateways);
        self::log_info("Payment gateway disabled: {$gateway_id}");

        return true;
    }

    /**
     * Get gateway configuration
     *
     * @since 1.0.0
     * @param string $gateway_id Gateway identifier
     * @return array|null Gateway configuration or null if not found
     */
    public static function get_gateway_config($gateway_id)
    {
        return isset(self::$gateways[$gateway_id]) ? self::$gateways[$gateway_id] : null;
    }

    /**
     * Get gateway instance (cached)
     *
     * @since 1.0.0
     * @param string $gateway_id Gateway identifier
     * @return object|false Gateway instance or false if not found
     */
    public static function get_gateway_instance($gateway_id)
    {
        if (isset(self::$instances[$gateway_id])) {
            return self::$instances[$gateway_id];
        }

        return self::create_gateway($gateway_id);
    }

    /**
     * Get gateways supporting specific features
     *
     * @since 1.0.0
     * @param array $features Array of required features
     * @return array Array of gateway IDs that support all features
     */
    public static function get_gateways_supporting($features)
    {
        $supporting_gateways = array();

        foreach (self::$gateways as $gateway_id => $config) {
            $gateway_supports = $config['supports'];

            // Check if gateway supports all required features
            if (array_intersect($features, $gateway_supports) === $features) {
                $supporting_gateways[] = $gateway_id;
            }
        }

        return $supporting_gateways;
    }

    /**
     * Get gateways available for specific country/currency
     *
     * @since 1.0.0
     * @param string $country_code Country code (ISO 2-letter)
     * @param string $currency Currency code (ISO 3-letter)
     * @return array Array of available gateway IDs
     */
    public static function get_gateways_for_location($country_code = '', $currency = '')
    {
        $available_gateways = array();

        foreach (self::$gateways as $gateway_id => $config) {
            $available = true;

            // Check country support
            if (!empty($country_code) && !empty($config['countries'])) {
                if (!in_array($country_code, $config['countries'])) {
                    $available = false;
                }
            }

            // Check currency support
            if (!empty($currency) && !empty($config['currencies'])) {
                if (!in_array($currency, $config['currencies'])) {
                    $available = false;
                }
            }

            if ($available) {
                $available_gateways[] = $gateway_id;
            }
        }

        return $available_gateways;
    }

    /**
     * Clear gateway instances cache
     *
     * @since 1.0.0
     * @param string $gateway_id Optional gateway ID to clear specific instance
     */
    public static function clear_cache($gateway_id = '')
    {
        if (!empty($gateway_id)) {
            if (isset(self::$instances[$gateway_id])) {
                unset(self::$instances[$gateway_id]);
            }
        } else {
            self::$instances = array();
        }

        self::log_info('Payment gateway cache cleared');
    }

    /**
     * Validate gateway ID format
     *
     * @since 1.0.0
     * @param string $gateway_id Gateway identifier
     * @return bool True if valid, false otherwise
     */
    private static function is_valid_gateway_id($gateway_id)
    {
        // Must be alphanumeric with underscores/hyphens only
        return preg_match('/^[a-zA-Z0-9_-]+$/', $gateway_id);
    }

    /**
     * Get factory statistics
     *
     * @since 1.0.0
     * @return array Factory statistics
     */
    public static function get_stats()
    {
        return array(
            'total_gateways' => count(self::$gateways),
            'enabled_gateways' => count(self::get_enabled_gateways()),
            'cached_instances' => count(self::$instances),
            'registered_gateways' => array_keys(self::$gateways)
        );
    }

    /**
     * Export factory data for debugging
     *
     * @since 1.0.0
     * @return array Factory data
     */
    public static function export_data()
    {
        return array(
            'gateways' => self::$gateways,
            'instances' => array_keys(self::$instances),
            'settings' => get_option('chatshop_enabled_gateways', array())
        );
    }

    /**
     * Log error message
     *
     * @since 1.0.0
     * @param string $message Error message
     */
    private static function log_error($message)
    {
        if (function_exists('chatshop_log')) {
            chatshop_log($message, 'error');
        } else {
            error_log("ChatShop Payment Factory: {$message}");
        }
    }

    /**
     * Log info message
     *
     * @since 1.0.0
     * @param string $message Info message
     */
    private static function log_info($message)
    {
        if (function_exists('chatshop_log')) {
            chatshop_log($message, 'info');
        } else {
            error_log("ChatShop Payment Factory: {$message}");
        }
    }
}
