<?php

/**
 * Payment Factory Class
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
 * Payment factory class
 *
 * @since 1.0.0
 */
class ChatShop_Payment_Factory
{
    /**
     * Registered gateway classes
     *
     * @var array
     * @since 1.0.0
     */
    private static $gateway_classes = array();

    /**
     * Gateway instances cache
     *
     * @var array
     * @since 1.0.0
     */
    private static $gateway_instances = array();

    /**
     * Initialize factory
     *
     * @since 1.0.0
     */
    public static function init()
    {
        self::register_default_gateways();

        // Hook for additional gateway registration
        do_action('chatshop_payment_factory_init');
    }

    /**
     * Register a gateway class
     *
     * @param string $gateway_id Gateway identifier
     * @param string $class_name Gateway class name
     * @return bool Success status
     * @since 1.0.0
     */
    public static function register_gateway_class($gateway_id, $class_name)
    {
        if (empty($gateway_id) || empty($class_name)) {
            return false;
        }

        if (!class_exists($class_name)) {
            return false;
        }

        self::$gateway_classes[$gateway_id] = $class_name;

        return true;
    }

    /**
     * Unregister a gateway class
     *
     * @param string $gateway_id Gateway identifier
     * @return bool Success status
     * @since 1.0.0
     */
    public static function unregister_gateway_class($gateway_id)
    {
        if (isset(self::$gateway_classes[$gateway_id])) {
            unset(self::$gateway_classes[$gateway_id]);

            // Also clear instance cache
            if (isset(self::$gateway_instances[$gateway_id])) {
                unset(self::$gateway_instances[$gateway_id]);
            }

            return true;
        }

        return false;
    }

    /**
     * Create gateway instance
     *
     * @param string $gateway_id Gateway identifier
     * @param array  $config Gateway configuration
     * @return ChatShop_Abstract_Payment_Gateway|null Gateway instance or null
     * @since 1.0.0
     */
    public static function create_gateway($gateway_id, $config = array())
    {
        if (!isset(self::$gateway_classes[$gateway_id])) {
            return null;
        }

        $class_name = self::$gateway_classes[$gateway_id];

        if (!class_exists($class_name)) {
            return null;
        }

        try {
            $gateway = new $class_name($config);

            if (!$gateway instanceof ChatShop_Abstract_Payment_Gateway) {
                return null;
            }

            return $gateway;
        } catch (Exception $e) {
            if (function_exists('chatshop_log')) {
                chatshop_log("Failed to create gateway {$gateway_id}: " . $e->getMessage(), 'error');
            }
            return null;
        }
    }

    /**
     * Get gateway instance (singleton per gateway)
     *
     * @param string $gateway_id Gateway identifier
     * @param array  $config Gateway configuration
     * @return ChatShop_Abstract_Payment_Gateway|null Gateway instance or null
     * @since 1.0.0
     */
    public static function get_gateway_instance($gateway_id, $config = array())
    {
        if (!isset(self::$gateway_instances[$gateway_id])) {
            self::$gateway_instances[$gateway_id] = self::create_gateway($gateway_id, $config);
        }

        return self::$gateway_instances[$gateway_id];
    }

    /**
     * Get all registered gateway classes
     *
     * @return array Registered gateway classes
     * @since 1.0.0
     */
    public static function get_registered_classes()
    {
        return self::$gateway_classes;
    }

    /**
     * Check if gateway class is registered
     *
     * @param string $gateway_id Gateway identifier
     * @return bool Whether gateway class is registered
     * @since 1.0.0
     */
    public static function is_gateway_registered($gateway_id)
    {
        return isset(self::$gateway_classes[$gateway_id]);
    }

    /**
     * Register default gateway classes
     *
     * @since 1.0.0
     */
    private static function register_default_gateways()
    {
        // Register Paystack gateway
        self::register_gateway_class('paystack', 'ChatShop\ChatShop_Paystack_Gateway');

        // Register additional gateways based on premium features
        if (function_exists('chatshop_is_premium_feature_available')) {
            if (chatshop_is_premium_feature_available('multiple_gateways')) {
                // Register premium gateways
                self::register_gateway_class('paypal', 'ChatShop\ChatShop_PayPal_Gateway');
                self::register_gateway_class('flutterwave', 'ChatShop\ChatShop_Flutterwave_Gateway');
                self::register_gateway_class('razorpay', 'ChatShop\ChatShop_Razorpay_Gateway');
            }
        }
    }

    /**
     * Create multiple gateway instances
     *
     * @param array $gateway_configs Array of gateway configurations
     * @return array Array of gateway instances
     * @since 1.0.0
     */
    public static function create_multiple_gateways($gateway_configs)
    {
        $gateways = array();

        foreach ($gateway_configs as $gateway_id => $config) {
            $gateway = self::create_gateway($gateway_id, $config);

            if ($gateway) {
                $gateways[$gateway_id] = $gateway;
            }
        }

        return $gateways;
    }

    /**
     * Get available gateway types
     *
     * @return array Available gateway types with metadata
     * @since 1.0.0
     */
    public static function get_available_gateway_types()
    {
        $types = array();

        foreach (self::$gateway_classes as $gateway_id => $class_name) {
            if (class_exists($class_name)) {
                // Try to get metadata from the class
                try {
                    $reflection = new ReflectionClass($class_name);
                    $doc_comment = $reflection->getDocComment();

                    $types[$gateway_id] = array(
                        'id' => $gateway_id,
                        'class' => $class_name,
                        'available' => true,
                        'description' => self::extract_description_from_doc($doc_comment)
                    );
                } catch (Exception $e) {
                    $types[$gateway_id] = array(
                        'id' => $gateway_id,
                        'class' => $class_name,
                        'available' => true,
                        'description' => ''
                    );
                }
            } else {
                $types[$gateway_id] = array(
                    'id' => $gateway_id,
                    'class' => $class_name,
                    'available' => false,
                    'description' => 'Class not found'
                );
            }
        }

        return $types;
    }

    /**
     * Extract description from doc comment
     *
     * @param string $doc_comment Doc comment
     * @return string Description
     * @since 1.0.0
     */
    private static function extract_description_from_doc($doc_comment)
    {
        if (empty($doc_comment)) {
            return '';
        }

        // Simple extraction of first line after /**
        $lines = explode("\n", $doc_comment);
        foreach ($lines as $line) {
            $line = trim($line, " \t\n\r\0\x0B/*");
            if (!empty($line) && !str_starts_with($line, '@')) {
                return $line;
            }
        }

        return '';
    }

    /**
     * Validate gateway configuration
     *
     * @param string $gateway_id Gateway identifier
     * @param array  $config Gateway configuration
     * @return array Validation result
     * @since 1.0.0
     */
    public static function validate_gateway_config($gateway_id, $config)
    {
        $result = array(
            'valid' => false,
            'errors' => array()
        );

        if (!self::is_gateway_registered($gateway_id)) {
            $result['errors'][] = "Gateway '{$gateway_id}' is not registered";
            return $result;
        }

        $gateway = self::create_gateway($gateway_id, $config);

        if (!$gateway) {
            $result['errors'][] = "Failed to create gateway instance";
            return $result;
        }

        // Check if gateway has required configuration
        if (method_exists($gateway, 'is_configured')) {
            if (!$gateway->is_configured()) {
                $result['errors'][] = "Gateway configuration is incomplete";
                return $result;
            }
        }

        // Test gateway connection if method exists
        if (method_exists($gateway, 'test_connection')) {
            $test_result = $gateway->test_connection();
            if (!$test_result['success']) {
                $result['errors'][] = "Gateway connection test failed: " . $test_result['message'];
                return $result;
            }
        }

        $result['valid'] = true;
        return $result;
    }

    /**
     * Get gateway configuration schema
     *
     * @param string $gateway_id Gateway identifier
     * @return array Configuration schema
     * @since 1.0.0
     */
    public static function get_gateway_config_schema($gateway_id)
    {
        if (!self::is_gateway_registered($gateway_id)) {
            return array();
        }

        $gateway = self::create_gateway($gateway_id);

        if (!$gateway || !method_exists($gateway, 'get_config_fields')) {
            return array();
        }

        return $gateway->get_config_fields();
    }

    /**
     * Clear gateway instances cache
     *
     * @since 1.0.0
     */
    public static function clear_instances_cache()
    {
        self::$gateway_instances = array();
    }

    /**
     * Reset factory
     *
     * @since 1.0.0
     */
    public static function reset()
    {
        self::$gateway_classes = array();
        self::$gateway_instances = array();
    }

    /**
     * Get factory statistics
     *
     * @return array Factory statistics
     * @since 1.0.0
     */
    public static function get_stats()
    {
        $available_classes = 0;
        $cached_instances = count(self::$gateway_instances);

        foreach (self::$gateway_classes as $class_name) {
            if (class_exists($class_name)) {
                $available_classes++;
            }
        }

        return array(
            'registered_classes' => count(self::$gateway_classes),
            'available_classes' => $available_classes,
            'cached_instances' => $cached_instances
        );
    }
}
