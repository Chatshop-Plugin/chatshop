<?php

/**
 * Payment Factory Class
 *
 * Factory class for creating and managing payment gateway instances.
 * Implements factory pattern with dynamic gateway registration.
 *
 * @package    ChatShop
 * @subpackage Components\Payment
 * @since      1.0.0
 * @author     Modewebhost
 */

namespace ChatShop;

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Payment Factory Class
 *
 * Handles creation, registration, and management of payment gateway instances
 * using the factory pattern with metadata support.
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
     * Gateway metadata
     *
     * @var array
     * @since 1.0.0
     */
    private static $gateway_metadata = array();

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
        add_action('init', array(__CLASS__, 'load_default_gateways'), 5);
        add_action('chatshop_load_components', array(__CLASS__, 'discover_gateways'), 10);
    }

    /**
     * Register a payment gateway
     *
     * @param string $gateway_id Gateway identifier.
     * @param string $class_name Gateway class name.
     * @param array  $metadata   Gateway metadata.
     * @return bool True on success, false on failure.
     * @since 1.0.0
     */
    public static function register_gateway($gateway_id, $class_name, $metadata = array())
    {
        // Validate inputs
        if (empty($gateway_id) || empty($class_name)) {
            chatshop_log('Invalid gateway registration: missing gateway_id or class_name', 'error');
            return false;
        }

        // Sanitize gateway ID
        $gateway_id = sanitize_key($gateway_id);

        // Validate class exists
        if (!class_exists($class_name)) {
            chatshop_log("Gateway class {$class_name} does not exist", 'error');
            return false;
        }

        // Check if class extends abstract gateway
        if (!is_subclass_of($class_name, 'ChatShop\ChatShop_Payment_Gateway')) {
            chatshop_log("Gateway class {$class_name} must extend ChatShop_Payment_Gateway", 'error');
            return false;
        }

        // Default metadata
        $default_metadata = array(
            'name'        => ucfirst($gateway_id),
            'description' => '',
            'version'     => '1.0.0',
            'author'      => '',
            'is_premium'  => false,
            'priority'    => 10,
            'currencies'  => array(),
            'features'    => array(),
        );

        $metadata = wp_parse_args($metadata, $default_metadata);

        // Store registration data
        self::$gateway_classes[$gateway_id] = $class_name;
        self::$gateway_metadata[$gateway_id] = $metadata;

        chatshop_log("Payment gateway '{$gateway_id}' registered successfully", 'info');

        // Trigger registration event
        do_action('chatshop_payment_gateway_registered', $gateway_id, $class_name, $metadata);

        return true;
    }

    /**
     * Unregister a payment gateway
     *
     * @param string $gateway_id Gateway identifier.
     * @return bool True on success, false if gateway not found.
     * @since 1.0.0
     */
    public static function unregister_gateway($gateway_id)
    {
        $gateway_id = sanitize_key($gateway_id);

        if (!isset(self::$gateway_classes[$gateway_id])) {
            return false;
        }

        // Remove from cache
        unset(self::$gateway_instances[$gateway_id]);
        unset(self::$gateway_classes[$gateway_id]);
        unset(self::$gateway_metadata[$gateway_id]);

        do_action('chatshop_payment_gateway_unregistered', $gateway_id);

        return true;
    }

    /**
     * Create gateway instance
     *
     * @param string $gateway_id Gateway identifier.
     * @param bool   $use_cache  Whether to use cached instance.
     * @return ChatShop_Payment_Gateway|null Gateway instance or null on failure.
     * @since 1.0.0
     */
    public static function create_gateway($gateway_id, $use_cache = true)
    {
        $gateway_id = sanitize_key($gateway_id);

        // Return cached instance if available and requested
        if ($use_cache && isset(self::$gateway_instances[$gateway_id])) {
            return self::$gateway_instances[$gateway_id];
        }

        // Check if gateway is registered
        if (!isset(self::$gateway_classes[$gateway_id])) {
            chatshop_log("Gateway '{$gateway_id}' is not registered", 'warning');
            return null;
        }

        $class_name = self::$gateway_classes[$gateway_id];

        try {
            // Create instance
            $instance = new $class_name();

            // Validate instance
            if (!$instance instanceof ChatShop_Payment_Gateway) {
                throw new \Exception("Gateway instance must be of type ChatShop_Payment_Gateway");
            }

            // Cache instance if requested
            if ($use_cache) {
                self::$gateway_instances[$gateway_id] = $instance;
            }

            chatshop_log("Gateway '{$gateway_id}' instantiated successfully", 'debug');

            return $instance;
        } catch (\Exception $e) {
            chatshop_log("Failed to create gateway '{$gateway_id}': " . $e->getMessage(), 'error');
            return null;
        }
    }

    /**
     * Get all available gateways
     *
     * @param bool $include_disabled Whether to include disabled gateways.
     * @return array Array of gateway instances.
     * @since 1.0.0
     */
    public static function get_available_gateways($include_disabled = false)
    {
        $gateways = array();

        foreach (array_keys(self::$gateway_classes) as $gateway_id) {
            $gateway = self::create_gateway($gateway_id);

            if (!$gateway) {
                continue;
            }

            // Skip disabled gateways if not requested
            if (!$include_disabled && !$gateway->is_enabled()) {
                continue;
            }

            $gateways[$gateway_id] = $gateway;
        }

        return $gateways;
    }

    /**
     * Get enabled gateways only
     *
     * @return array Array of enabled gateway instances.
     * @since 1.0.0
     */
    public static function get_enabled_gateways()
    {
        return self::get_available_gateways(false);
    }

    /**
     * Get gateway by ID
     *
     * @param string $gateway_id Gateway identifier.
     * @return ChatShop_Payment_Gateway|null Gateway instance or null.
     * @since 1.0.0
     */
    public static function get_gateway($gateway_id)
    {
        return self::create_gateway($gateway_id);
    }

    /**
     * Check if gateway is registered
     *
     * @param string $gateway_id Gateway identifier.
     * @return bool True if registered, false otherwise.
     * @since 1.0.0
     */
    public static function is_gateway_registered($gateway_id)
    {
        return isset(self::$gateway_classes[sanitize_key($gateway_id)]);
    }

    /**
     * Get gateway metadata
     *
     * @param string $gateway_id Gateway identifier.
     * @return array|null Gateway metadata or null if not found.
     * @since 1.0.0
     */
    public static function get_gateway_metadata($gateway_id)
    {
        $gateway_id = sanitize_key($gateway_id);
        return isset(self::$gateway_metadata[$gateway_id]) ? self::$gateway_metadata[$gateway_id] : null;
    }

    /**
     * Get all registered gateway metadata
     *
     * @return array Array of all gateway metadata.
     * @since 1.0.0
     */
    public static function get_all_gateway_metadata()
    {
        return self::$gateway_metadata;
    }

    /**
     * Get gateways by criteria
     *
     * @param array $criteria Search criteria (currency, features, premium, etc.).
     * @return array Filtered gateway instances.
     * @since 1.0.0
     */
    public static function get_gateways_by_criteria($criteria = array())
    {
        $filtered_gateways = array();

        foreach (self::$gateway_metadata as $gateway_id => $metadata) {
            $match = true;

            // Check currency support
            if (isset($criteria['currency']) && !empty($metadata['currencies'])) {
                $currency = strtoupper($criteria['currency']);
                if (!in_array($currency, $metadata['currencies'], true)) {
                    $match = false;
                }
            }

            // Check premium status
            if (isset($criteria['is_premium'])) {
                if ((bool) $criteria['is_premium'] !== (bool) $metadata['is_premium']) {
                    $match = false;
                }
            }

            // Check features
            if (isset($criteria['features']) && is_array($criteria['features'])) {
                foreach ($criteria['features'] as $feature) {
                    if (!in_array($feature, $metadata['features'], true)) {
                        $match = false;
                        break;
                    }
                }
            }

            // Check enabled status
            if (isset($criteria['enabled'])) {
                $gateway = self::get_gateway($gateway_id);
                if ($gateway && (bool) $criteria['enabled'] !== $gateway->is_enabled()) {
                    $match = false;
                }
            }

            if ($match) {
                $gateway = self::get_gateway($gateway_id);
                if ($gateway) {
                    $filtered_gateways[$gateway_id] = $gateway;
                }
            }
        }

        return $filtered_gateways;
    }

    /**
     * Get best gateway for currency
     *
     * Returns the highest priority enabled gateway that supports the currency.
     *
     * @param string $currency Currency code.
     * @return ChatShop_Payment_Gateway|null Best gateway or null.
     * @since 1.0.0
     */
    public static function get_best_gateway_for_currency($currency)
    {
        $currency = strtoupper(sanitize_text_field($currency));
        $best_gateway = null;
        $highest_priority = -1;

        foreach (self::$gateway_metadata as $gateway_id => $metadata) {
            // Skip if currency not supported
            if (!empty($metadata['currencies']) && !in_array($currency, $metadata['currencies'], true)) {
                continue;
            }

            $gateway = self::get_gateway($gateway_id);
            if (!$gateway || !$gateway->is_enabled()) {
                continue;
            }

            // Check if this gateway has higher priority
            $priority = isset($metadata['priority']) ? (int) $metadata['priority'] : 10;
            if ($priority > $highest_priority) {
                $highest_priority = $priority;
                $best_gateway = $gateway;
            }
        }

        return $best_gateway;
    }

    /**
     * Load default gateways
     *
     * @since 1.0.0
     */
    public static function load_default_gateways()
    {
        // Register built-in gateways
        $default_gateways = array(
            'paystack' => array(
                'class'    => 'ChatShop\ChatShop_Paystack_Gateway',
                'metadata' => array(
                    'name'        => __('Paystack', 'chatshop'),
                    'description' => __('Accept payments via Paystack', 'chatshop'),
                    'currencies'  => array('NGN', 'USD', 'GHS', 'ZAR', 'KES'),
                    'features'    => array('cards', 'bank_transfer', 'mobile_money', 'webhooks'),
                    'priority'    => 20,
                ),
            ),
        );

        foreach ($default_gateways as $gateway_id => $config) {
            if (class_exists($config['class'])) {
                self::register_gateway($gateway_id, $config['class'], $config['metadata']);
            }
        }

        // Allow plugins to register additional gateways
        do_action('chatshop_register_payment_gateways');
    }

    /**
     * Discover gateways from components
     *
     * Automatically discover and register gateway classes from component directories.
     *
     * @since 1.0.0
     */
    public static function discover_gateways()
    {
        $gateways_dir = CHATSHOP_PLUGIN_DIR . 'components/payment/gateways/';

        if (!is_dir($gateways_dir)) {
            return;
        }

        $gateway_dirs = glob($gateways_dir . '*', GLOB_ONLYDIR);

        foreach ($gateway_dirs as $gateway_dir) {
            $gateway_id = basename($gateway_dir);
            $gateway_file = $gateway_dir . '/class-' . $gateway_id . '-gateway.php';

            if (file_exists($gateway_file)) {
                require_once $gateway_file;

                // Try to auto-register based on naming convention
                $class_name = 'ChatShop\\ChatShop_' . ucfirst($gateway_id) . '_Gateway';

                if (class_exists($class_name) && !self::is_gateway_registered($gateway_id)) {
                    self::register_gateway($gateway_id, $class_name);
                }
            }
        }
    }

    /**
     * Clear gateway cache
     *
     * @since 1.0.0
     */
    public static function clear_cache()
    {
        self::$gateway_instances = array();
        chatshop_log('Payment gateway cache cleared', 'debug');
    }

    /**
     * Get factory statistics
     *
     * @return array Factory statistics.
     * @since 1.0.0
     */
    public static function get_stats()
    {
        $enabled_count = 0;
        $premium_count = 0;

        foreach (self::$gateway_metadata as $metadata) {
            if ($metadata['is_premium']) {
                $premium_count++;
            }
        }

        foreach (self::get_available_gateways() as $gateway) {
            if ($gateway->is_enabled()) {
                $enabled_count++;
            }
        }

        return array(
            'total_registered' => count(self::$gateway_classes),
            'total_enabled'    => $enabled_count,
            'premium_gateways' => $premium_count,
            'cached_instances' => count(self::$gateway_instances),
        );
    }

    /**
     * Validate gateway configuration
     *
     * @param string $gateway_id Gateway identifier.
     * @return array Validation result.
     * @since 1.0.0
     */
    public static function validate_gateway_config($gateway_id)
    {
        $gateway = self::get_gateway($gateway_id);
        $errors = array();

        if (!$gateway) {
            $errors[] = __('Gateway not found or failed to instantiate.', 'chatshop');
            return array('valid' => false, 'errors' => $errors);
        }

        // Test connection if gateway supports it
        $connection_test = $gateway->test_connection();
        if (!$connection_test['success']) {
            $errors[] = $connection_test['message'];
        }

        return array(
            'valid'  => empty($errors),
            'errors' => $errors,
        );
    }
}
