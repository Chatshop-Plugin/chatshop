<?php

/**
 * Payment Factory
 *
 * Factory class for creating and managing payment gateway instances
 * with proper configuration validation and error handling.
 *
 * @package    ChatShop
 * @subpackage ChatShop/components/payment
 * @since      1.0.0
 */

namespace ChatShop;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Payment Factory Class
 *
 * @since 1.0.0
 */
class ChatShop_Payment_Factory
{
    /**
     * Gateway class mappings
     *
     * @since 1.0.0
     * @var array
     */
    private static $gateway_classes = array(
        'paystack' => 'ChatShop_Paystack_Gateway',
        'paypal' => 'ChatShop_PayPal_Gateway',
        'flutterwave' => 'ChatShop_Flutterwave_Gateway',
        'razorpay' => 'ChatShop_Razorpay_Gateway'
    );

    /**
     * Available gateway configurations
     *
     * @since 1.0.0
     * @var array
     */
    private static $gateway_configs = array(
        'paystack' => array(
            'name' => 'Paystack',
            'description' => 'Accept payments via Paystack',
            'supported_currencies' => array('NGN', 'USD', 'GHS', 'ZAR', 'KES', 'XOF'),
            'supported_countries' => array('NG', 'GH', 'ZA', 'KE'),
            'features' => array('cards', 'bank_transfer', 'ussd', 'qr', 'mobile_money'),
            'premium' => false
        ),
        'paypal' => array(
            'name' => 'PayPal',
            'description' => 'Accept payments via PayPal',
            'supported_currencies' => array('USD', 'EUR', 'GBP', 'CAD', 'AUD'),
            'supported_countries' => array('US', 'GB', 'CA', 'AU', 'DE', 'FR'),
            'features' => array('paypal', 'cards'),
            'premium' => true
        ),
        'flutterwave' => array(
            'name' => 'Flutterwave',
            'description' => 'Accept payments via Flutterwave',
            'supported_currencies' => array('NGN', 'USD', 'GHS', 'KES', 'ZAR', 'UGX'),
            'supported_countries' => array('NG', 'GH', 'KE', 'ZA', 'UG'),
            'features' => array('cards', 'bank_transfer', 'ussd', 'mobile_money'),
            'premium' => true
        ),
        'razorpay' => array(
            'name' => 'Razorpay',
            'description' => 'Accept payments via Razorpay',
            'supported_currencies' => array('INR'),
            'supported_countries' => array('IN'),
            'features' => array('cards', 'netbanking', 'upi', 'wallet'),
            'premium' => true
        )
    );

    /**
     * Gateway instances cache
     *
     * @since 1.0.0
     * @var array
     */
    private static $instances = array();

    /**
     * Initialize factory
     *
     * @since 1.0.0
     */
    public static function init()
    {
        add_action('chatshop_load_payment_gateways', array(__CLASS__, 'register_default_gateways'));
        add_filter('chatshop_available_gateways', array(__CLASS__, 'filter_available_gateways'));
    }

    /**
     * Create gateway instance
     *
     * @since 1.0.0
     * @param string $gateway_id Gateway identifier
     * @param array  $config Optional configuration override
     * @return ChatShop_Abstract_Payment_Gateway|WP_Error Gateway instance or error
     */
    public static function create_gateway($gateway_id, $config = array())
    {
        if (empty($gateway_id)) {
            return new \WP_Error('missing_gateway_id', __('Gateway ID is required', 'chatshop'));
        }

        // Check if gateway is supported
        if (!self::is_gateway_supported($gateway_id)) {
            return new \WP_Error(
                'unsupported_gateway',
                sprintf(__('Gateway %s is not supported', 'chatshop'), $gateway_id)
            );
        }

        // Check premium requirements
        if (!self::is_gateway_available($gateway_id)) {
            return new \WP_Error(
                'gateway_not_available',
                sprintf(__('Gateway %s requires premium license', 'chatshop'), $gateway_id)
            );
        }

        // Return cached instance if available
        $cache_key = $gateway_id . '_' . md5(serialize($config));
        if (isset(self::$instances[$cache_key])) {
            return self::$instances[$cache_key];
        }

        // Get gateway class
        $gateway_class = self::get_gateway_class($gateway_id);
        if (!$gateway_class) {
            return new \WP_Error(
                'gateway_class_not_found',
                sprintf(__('Gateway class for %s not found', 'chatshop'), $gateway_id)
            );
        }

        try {
            // Create instance
            $gateway = new $gateway_class($config);

            // Validate instance
            if (!$gateway instanceof ChatShop_Abstract_Payment_Gateway) {
                throw new \Exception('Gateway does not extend abstract payment gateway');
            }

            // Cache instance
            self::$instances[$cache_key] = $gateway;

            chatshop_log("Gateway {$gateway_id} created successfully", 'debug');

            return $gateway;
        } catch (\Exception $e) {
            $error_msg = sprintf('Failed to create gateway %s: %s', $gateway_id, $e->getMessage());
            chatshop_log($error_msg, 'error');

            return new \WP_Error('gateway_creation_failed', $error_msg);
        }
    }

    /**
     * Get available gateways
     *
     * @since 1.0.0
     * @param bool $include_disabled Include disabled gateways
     * @return array Available gateways
     */
    public static function get_available_gateways($include_disabled = false)
    {
        $available = array();

        foreach (self::$gateway_configs as $id => $config) {
            // Check if gateway is available (premium check)
            if (!self::is_gateway_available($id) && !$include_disabled) {
                continue;
            }

            // Check if gateway class exists
            $gateway_class = self::get_gateway_class($id);
            if (!class_exists($gateway_class)) {
                continue;
            }

            $gateway_info = $config;
            $gateway_info['id'] = $id;
            $gateway_info['available'] = self::is_gateway_available($id);
            $gateway_info['enabled'] = self::is_gateway_enabled($id);
            $gateway_info['configured'] = self::is_gateway_configured($id);

            $available[$id] = $gateway_info;
        }

        return apply_filters('chatshop_available_gateways', $available);
    }

    /**
     * Get gateway configuration
     *
     * @since 1.0.0
     * @param string $gateway_id Gateway ID
     * @return array|null Gateway configuration
     */
    public static function get_gateway_config($gateway_id)
    {
        return isset(self::$gateway_configs[$gateway_id]) ? self::$gateway_configs[$gateway_id] : null;
    }

    /**
     * Register gateway class
     *
     * @since 1.0.0
     * @param string $gateway_id Gateway identifier
     * @param string $class_name Gateway class name
     * @param array  $config Gateway configuration
     * @return bool Registration result
     */
    public static function register_gateway($gateway_id, $class_name, $config = array())
    {
        if (empty($gateway_id) || empty($class_name)) {
            return false;
        }

        // Validate class exists
        if (!class_exists($class_name)) {
            chatshop_log("Gateway class {$class_name} does not exist", 'error');
            return false;
        }

        // Register class mapping
        self::$gateway_classes[$gateway_id] = $class_name;

        // Register configuration if provided
        if (!empty($config)) {
            self::$gateway_configs[$gateway_id] = $config;
        }

        chatshop_log("Gateway {$gateway_id} registered with class {$class_name}", 'info');

        do_action('chatshop_gateway_registered', $gateway_id, $class_name, $config);

        return true;
    }

    /**
     * Unregister gateway
     *
     * @since 1.0.0
     * @param string $gateway_id Gateway identifier
     * @return bool Unregistration result
     */
    public static function unregister_gateway($gateway_id)
    {
        if (!isset(self::$gateway_classes[$gateway_id])) {
            return false;
        }

        unset(self::$gateway_classes[$gateway_id]);
        unset(self::$gateway_configs[$gateway_id]);

        // Clear cached instances
        foreach (self::$instances as $key => $instance) {
            if (strpos($key, $gateway_id . '_') === 0) {
                unset(self::$instances[$key]);
            }
        }

        chatshop_log("Gateway {$gateway_id} unregistered", 'info');

        do_action('chatshop_gateway_unregistered', $gateway_id);

        return true;
    }

    /**
     * Register default gateways
     *
     * @since 1.0.0
     */
    public static function register_default_gateways()
    {
        // Register Paystack (always available)
        if (class_exists('ChatShop\ChatShop_Paystack_Gateway')) {
            self::register_gateway('paystack', 'ChatShop\ChatShop_Paystack_Gateway', self::$gateway_configs['paystack']);
        }

        // Register premium gateways if available
        if (chatshop_is_premium_feature_available('multiple_gateways')) {

            // PayPal
            if (class_exists('ChatShop\ChatShop_PayPal_Gateway')) {
                self::register_gateway('paypal', 'ChatShop\ChatShop_PayPal_Gateway', self::$gateway_configs['paypal']);
            }

            // Flutterwave
            if (class_exists('ChatShop\ChatShop_Flutterwave_Gateway')) {
                self::register_gateway('flutterwave', 'ChatShop\ChatShop_Flutterwave_Gateway', self::$gateway_configs['flutterwave']);
            }

            // Razorpay
            if (class_exists('ChatShop\ChatShop_Razorpay_Gateway')) {
                self::register_gateway('razorpay', 'ChatShop\ChatShop_Razorpay_Gateway', self::$gateway_configs['razorpay']);
            }
        }

        do_action('chatshop_register_custom_gateways');
    }

    /**
     * Filter available gateways based on license
     *
     * @since 1.0.0
     * @param array $gateways Available gateways
     * @return array Filtered gateways
     */
    public static function filter_available_gateways($gateways)
    {
        // If multiple gateways feature is not available, only show Paystack
        if (!chatshop_is_premium_feature_available('multiple_gateways')) {
            $filtered = array();
            if (isset($gateways['paystack'])) {
                $filtered['paystack'] = $gateways['paystack'];
            }
            return $filtered;
        }

        return $gateways;
    }

    /**
     * Check if gateway is supported
     *
     * @since 1.0.0
     * @param string $gateway_id Gateway ID
     * @return bool Support status
     */
    public static function is_gateway_supported($gateway_id)
    {
        return isset(self::$gateway_configs[$gateway_id]);
    }

    /**
     * Check if gateway is available (license check)
     *
     * @since 1.0.0
     * @param string $gateway_id Gateway ID
     * @return bool Availability status
     */
    public static function is_gateway_available($gateway_id)
    {
        $config = self::get_gateway_config($gateway_id);

        if (!$config) {
            return false;
        }

        // Paystack is always available
        if ($gateway_id === 'paystack') {
            return true;
        }

        // Premium gateways require premium license
        if (isset($config['premium']) && $config['premium']) {
            return chatshop_is_premium_feature_available('multiple_gateways');
        }

        return true;
    }

    /**
     * Check if gateway is enabled
     *
     * @since 1.0.0
     * @param string $gateway_id Gateway ID
     * @return bool Enabled status
     */
    public static function is_gateway_enabled($gateway_id)
    {
        $options = chatshop_get_option($gateway_id, '', array());
        return isset($options['enabled']) ? (bool) $options['enabled'] : false;
    }

    /**
     * Check if gateway is configured
     *
     * @since 1.0.0
     * @param string $gateway_id Gateway ID
     * @return bool Configuration status
     */
    public static function is_gateway_configured($gateway_id)
    {
        $options = chatshop_get_option($gateway_id, '', array());

        switch ($gateway_id) {
            case 'paystack':
                $test_key = !empty($options['test_secret_key']);
                $live_key = !empty($options['live_secret_key']);
                return $test_key || $live_key;

            case 'paypal':
                return !empty($options['client_id']) && !empty($options['client_secret']);

            case 'flutterwave':
                return !empty($options['secret_key']) && !empty($options['public_key']);

            case 'razorpay':
                return !empty($options['key_id']) && !empty($options['key_secret']);

            default:
                // For custom gateways, check if any configuration exists
                return !empty($options);
        }
    }

    /**
     * Get gateway class name
     *
     * @since 1.0.0
     * @param string $gateway_id Gateway ID
     * @return string|null Gateway class name
     */
    private static function get_gateway_class($gateway_id)
    {
        return isset(self::$gateway_classes[$gateway_id]) ? self::$gateway_classes[$gateway_id] : null;
    }

    /**
     * Create multiple gateways
     *
     * @since 1.0.0
     * @param array $gateway_ids Array of gateway IDs
     * @param array $config Common configuration
     * @return array Array of gateway instances or errors
     */
    public static function create_multiple_gateways($gateway_ids, $config = array())
    {
        $gateways = array();

        foreach ($gateway_ids as $gateway_id) {
            $gateway = self::create_gateway($gateway_id, $config);
            $gateways[$gateway_id] = $gateway;
        }

        return $gateways;
    }

    /**
     * Get enabled gateway instances
     *
     * @since 1.0.0
     * @return array Enabled gateway instances
     */
    public static function get_enabled_gateway_instances()
    {
        $instances = array();
        $available_gateways = self::get_available_gateways();

        foreach ($available_gateways as $id => $config) {
            if ($config['enabled'] && $config['configured']) {
                $gateway = self::create_gateway($id);
                if (!is_wp_error($gateway)) {
                    $instances[$id] = $gateway;
                }
            }
        }

        return $instances;
    }

    /**
     * Get gateway by currency support
     *
     * @since 1.0.0
     * @param string $currency Currency code
     * @return array Gateways supporting the currency
     */
    public static function get_gateways_by_currency($currency)
    {
        $supporting_gateways = array();
        $available_gateways = self::get_available_gateways();

        foreach ($available_gateways as $id => $config) {
            if (in_array(strtoupper($currency), $config['supported_currencies'], true)) {
                $supporting_gateways[$id] = $config;
            }
        }

        return $supporting_gateways;
    }

    /**
     * Get gateway by country support
     *
     * @since 1.0.0
     * @param string $country Country code
     * @return array Gateways supporting the country
     */
    public static function get_gateways_by_country($country)
    {
        $supporting_gateways = array();
        $available_gateways = self::get_available_gateways();

        foreach ($available_gateways as $id => $config) {
            if (in_array(strtoupper($country), $config['supported_countries'], true)) {
                $supporting_gateways[$id] = $config;
            }
        }

        return $supporting_gateways;
    }

    /**
     * Get best gateway for payment
     *
     * @since 1.0.0
     * @param float  $amount Payment amount
     * @param string $currency Currency code
     * @param string $country Country code
     * @return string|null Best gateway ID
     */
    public static function get_best_gateway($amount, $currency, $country = '')
    {
        $available_gateways = self::get_available_gateways();
        $suitable_gateways = array();

        foreach ($available_gateways as $id => $config) {
            // Check if gateway is enabled and configured
            if (!$config['enabled'] || !$config['configured']) {
                continue;
            }

            // Check currency support
            if (!in_array(strtoupper($currency), $config['supported_currencies'], true)) {
                continue;
            }

            // Check country support if provided
            if (!empty($country) && !in_array(strtoupper($country), $config['supported_countries'], true)) {
                continue;
            }

            $suitable_gateways[$id] = $config;
        }

        if (empty($suitable_gateways)) {
            return null;
        }

        // Priority logic: Prefer Paystack for supported currencies, then others
        if (isset($suitable_gateways['paystack'])) {
            return 'paystack';
        }

        // Return first suitable gateway
        return array_key_first($suitable_gateways);
    }

    /**
     * Validate gateway configuration
     *
     * @since 1.0.0
     * @param string $gateway_id Gateway ID
     * @param array  $config Configuration to validate
     * @return array Validation result
     */
    public static function validate_gateway_config($gateway_id, $config)
    {
        $errors = array();
        $gateway_config = self::get_gateway_config($gateway_id);

        if (!$gateway_config) {
            $errors[] = __('Unknown gateway', 'chatshop');
            return array('valid' => false, 'errors' => $errors);
        }

        // Gateway-specific validation
        switch ($gateway_id) {
            case 'paystack':
                $errors = array_merge($errors, self::validate_paystack_config($config));
                break;

            case 'paypal':
                $errors = array_merge($errors, self::validate_paypal_config($config));
                break;

            case 'flutterwave':
                $errors = array_merge($errors, self::validate_flutterwave_config($config));
                break;

            case 'razorpay':
                $errors = array_merge($errors, self::validate_razorpay_config($config));
                break;
        }

        return array(
            'valid' => empty($errors),
            'errors' => $errors
        );
    }

    /**
     * Validate Paystack configuration
     *
     * @since 1.0.0
     * @param array $config Configuration
     * @return array Validation errors
     */
    private static function validate_paystack_config($config)
    {
        $errors = array();

        $test_mode = isset($config['test_mode']) ? $config['test_mode'] : true;

        if ($test_mode) {
            if (empty($config['test_secret_key'])) {
                $errors[] = __('Test secret key is required', 'chatshop');
            } elseif (!preg_match('/^sk_test_/', $config['test_secret_key'])) {
                $errors[] = __('Invalid test secret key format', 'chatshop');
            }

            if (empty($config['test_public_key'])) {
                $errors[] = __('Test public key is required', 'chatshop');
            } elseif (!preg_match('/^pk_test_/', $config['test_public_key'])) {
                $errors[] = __('Invalid test public key format', 'chatshop');
            }
        } else {
            if (empty($config['live_secret_key'])) {
                $errors[] = __('Live secret key is required', 'chatshop');
            } elseif (!preg_match('/^sk_live_/', $config['live_secret_key'])) {
                $errors[] = __('Invalid live secret key format', 'chatshop');
            }

            if (empty($config['live_public_key'])) {
                $errors[] = __('Live public key is required', 'chatshop');
            } elseif (!preg_match('/^pk_live_/', $config['live_public_key'])) {
                $errors[] = __('Invalid live public key format', 'chatshop');
            }
        }

        return $errors;
    }

    /**
     * Validate PayPal configuration
     *
     * @since 1.0.0
     * @param array $config Configuration
     * @return array Validation errors
     */
    private static function validate_paypal_config($config)
    {
        $errors = array();

        if (empty($config['client_id'])) {
            $errors[] = __('PayPal Client ID is required', 'chatshop');
        }

        if (empty($config['client_secret'])) {
            $errors[] = __('PayPal Client Secret is required', 'chatshop');
        }

        return $errors;
    }

    /**
     * Validate Flutterwave configuration
     *
     * @since 1.0.0
     * @param array $config Configuration
     * @return array Validation errors
     */
    private static function validate_flutterwave_config($config)
    {
        $errors = array();

        if (empty($config['secret_key'])) {
            $errors[] = __('Flutterwave Secret Key is required', 'chatshop');
        }

        if (empty($config['public_key'])) {
            $errors[] = __('Flutterwave Public Key is required', 'chatshop');
        }

        return $errors;
    }

    /**
     * Validate Razorpay configuration
     *
     * @since 1.0.0
     * @param array $config Configuration
     * @return array Validation errors
     */
    private static function validate_razorpay_config($config)
    {
        $errors = array();

        if (empty($config['key_id'])) {
            $errors[] = __('Razorpay Key ID is required', 'chatshop');
        }

        if (empty($config['key_secret'])) {
            $errors[] = __('Razorpay Key Secret is required', 'chatshop');
        }

        return $errors;
    }

    /**
     * Clear gateway cache
     *
     * @since 1.0.0
     * @param string $gateway_id Optional gateway ID to clear specific cache
     */
    public static function clear_cache($gateway_id = null)
    {
        if ($gateway_id) {
            foreach (self::$instances as $key => $instance) {
                if (strpos($key, $gateway_id . '_') === 0) {
                    unset(self::$instances[$key]);
                }
            }
        } else {
            self::$instances = array();
        }

        chatshop_log('Payment factory cache cleared', 'debug');
    }

    /**
     * Get factory statistics
     *
     * @since 1.0.0
     * @return array Factory statistics
     */
    public static function get_statistics()
    {
        $available_gateways = self::get_available_gateways(true);
        $enabled_count = 0;
        $configured_count = 0;

        foreach ($available_gateways as $gateway) {
            if ($gateway['enabled']) {
                $enabled_count++;
            }
            if ($gateway['configured']) {
                $configured_count++;
            }
        }

        return array(
            'total_gateways' => count($available_gateways),
            'enabled_gateways' => $enabled_count,
            'configured_gateways' => $configured_count,
            'cached_instances' => count(self::$instances),
            'premium_available' => chatshop_is_premium_feature_available('multiple_gateways')
        );
    }
}
