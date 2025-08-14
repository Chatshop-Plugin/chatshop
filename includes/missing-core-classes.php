<?php

/**
 * Missing Core Classes Bundle - CONFLICT-FREE VERSION
 *
 * This file provides essential classes that may be missing from the plugin
 * to prevent fatal activation errors. These are minimal fallback implementations
 * that only load if the actual class files are missing.
 *
 * @package ChatShop
 * @since 1.0.0
 */

namespace ChatShop;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// ================================
// FALLBACK LOGGER CLASS
// ================================

if (!class_exists('ChatShop\\ChatShop_Logger')) {
    /**
     * Simple logger fallback implementation
     *
     * @since 1.0.0
     */
    class ChatShop_Logger
    {
        /**
         * Log a message
         *
         * @param string $message Message to log
         * @param string $level Log level
         * @param array $context Additional context
         * @since 1.0.0
         */
        public static function log($message, $level = 'info', $context = array())
        {
            if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                $formatted_message = '[ChatShop-Fallback] ' . $level . ': ' . $message;
                if (!empty($context)) {
                    $formatted_message .= ' | Context: ' . wp_json_encode($context);
                }
                error_log($formatted_message);
            }
        }

        /**
         * Log info message
         *
         * @param string $message Message
         * @param array $context Context
         * @since 1.0.0
         */
        public static function info($message, $context = array())
        {
            self::log($message, 'info', $context);
        }

        /**
         * Log warning message
         *
         * @param string $message Message
         * @param array $context Context
         * @since 1.0.0
         */
        public static function warning($message, $context = array())
        {
            self::log($message, 'warning', $context);
        }

        /**
         * Log error message
         *
         * @param string $message Message
         * @param array $context Context
         * @since 1.0.0
         */
        public static function error($message, $context = array())
        {
            self::log($message, 'error', $context);
        }
    }
}

// ================================
// FALLBACK I18N CLASS
// ================================

if (!class_exists('ChatShop\\ChatShop_I18n')) {
    /**
     * Internationalization fallback class
     *
     * @since 1.0.0
     */
    class ChatShop_I18n
    {
        /**
         * Load plugin text domain
         *
         * @since 1.0.0
         */
        public function load_plugin_textdomain()
        {
            load_plugin_textdomain(
                'chatshop',
                false,
                dirname(dirname(plugin_basename(__FILE__))) . '/languages/'
            );
        }
    }
}

// ================================
// FALLBACK DEACTIVATOR CLASS - ONLY IF MISSING
// ================================

if (!class_exists('ChatShop\\ChatShop_Deactivator')) {
    /**
     * Plugin deactivator - FALLBACK IMPLEMENTATION ONLY
     *
     * @since 1.0.0
     */
    class ChatShop_Deactivator
    {
        /**
         * Deactivate the plugin
         *
         * @since 1.0.0
         */
        public static function deactivate()
        {
            // Clear scheduled events
            wp_clear_scheduled_hook('chatshop_daily_cleanup');
            wp_clear_scheduled_hook('chatshop_analytics_aggregation');
            wp_clear_scheduled_hook('chatshop_process_campaigns');

            // Clear transients
            global $wpdb;
            $wpdb->query(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_chatshop_%' OR option_name LIKE '_transient_timeout_chatshop_%'"
            );

            // Remove activation flag
            delete_option('chatshop_activated');

            if (function_exists('ChatShop\\chatshop_log')) {
                chatshop_log('ChatShop plugin deactivated (using fallback deactivator)', 'info');
            }
        }
    }
}

// ================================
// FALLBACK COMPONENT REGISTRY CLASS
// ================================

if (!class_exists('ChatShop\\ChatShop_Component_Registry')) {
    /**
     * Component registry fallback
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
         * Register a component
         *
         * @param array $component Component configuration
         * @return bool Success status
         * @since 1.0.0
         */
        public function register_component($component)
        {
            if (empty($component['id'])) {
                return false;
            }

            $defaults = array(
                'name' => '',
                'description' => '',
                'path' => '',
                'main_file' => '',
                'class_name' => '',
                'dependencies' => array(),
                'version' => '1.0.0',
                'enabled' => true,
                'priority' => 10
            );

            $this->components[$component['id']] = wp_parse_args($component, $defaults);
            return true;
        }

        /**
         * Get all components
         *
         * @return array All registered components
         * @since 1.0.0
         */
        public function get_all_components()
        {
            return $this->components;
        }

        /**
         * Get a specific component
         *
         * @param string $component_id Component ID
         * @return array|false Component data or false
         * @since 1.0.0
         */
        public function get_component($component_id)
        {
            return isset($this->components[$component_id]) ? $this->components[$component_id] : false;
        }

        /**
         * Check if component is registered
         *
         * @param string $component_id Component ID
         * @return bool True if registered
         * @since 1.0.0
         */
        public function is_component_registered($component_id)
        {
            return isset($this->components[$component_id]);
        }
    }
}

// ================================
// FALLBACK COMPONENT LOADER CLASS
// ================================

if (!class_exists('ChatShop\\ChatShop_Component_Loader')) {
    /**
     * Component loader fallback
     *
     * @since 1.0.0
     */
    class ChatShop_Component_Loader
    {
        /**
         * Component instances
         *
         * @var array
         * @since 1.0.0
         */
        private $component_instances = array();

        /**
         * Loading errors
         *
         * @var array
         * @since 1.0.0
         */
        private $loading_errors = array();

        /**
         * Load all components
         *
         * @return bool Success status
         * @since 1.0.0
         */
        public function load_all_components()
        {
            // Fallback implementation - just return true
            return true;
        }

        /**
         * Get component instances
         *
         * @return array Component instances
         * @since 1.0.0
         */
        public function get_component_instances()
        {
            return $this->component_instances;
        }

        /**
         * Get loading errors
         *
         * @return array Loading errors
         * @since 1.0.0
         */
        public function get_loading_errors()
        {
            return $this->loading_errors;
        }

        /**
         * Get component instance
         *
         * @param string $component_id Component ID
         * @return mixed|null Component instance or null
         * @since 1.0.0
         */
        public function get_component_instance($component_id)
        {
            return isset($this->component_instances[$component_id]) ? $this->component_instances[$component_id] : null;
        }
    }
}

// ================================
// FALLBACK HELPER CLASS
// ================================

if (!class_exists('ChatShop\\ChatShop_Helper')) {
    /**
     * Helper utilities fallback
     *
     * @since 1.0.0
     */
    class ChatShop_Helper
    {
        /**
         * Sanitize array recursively
         *
         * @param array $array Array to sanitize
         * @return array Sanitized array
         * @since 1.0.0
         */
        public static function sanitize_array($array)
        {
            $sanitized = array();

            foreach ($array as $key => $value) {
                $key = sanitize_key($key);

                if (is_array($value)) {
                    $sanitized[$key] = self::sanitize_array($value);
                } else {
                    $sanitized[$key] = sanitize_text_field($value);
                }
            }

            return $sanitized;
        }

        /**
         * Format currency amount
         *
         * @param float $amount Amount
         * @param string $currency Currency code
         * @return string Formatted amount
         * @since 1.0.0
         */
        public static function format_currency($amount, $currency = 'USD')
        {
            $symbols = array(
                'USD' => '$',
                'NGN' => '₦',
                'GHS' => '₵',
                'ZAR' => 'R',
                'KES' => 'KSh',
                'XOF' => 'CFA'
            );

            $symbol = isset($symbols[$currency]) ? $symbols[$currency] : $currency . ' ';
            return $symbol . number_format($amount, 2);
        }

        /**
         * Generate unique reference
         *
         * @param string $prefix Optional prefix
         * @return string Unique reference
         * @since 1.0.0
         */
        public static function generate_reference($prefix = 'chatshop')
        {
            return $prefix . '_' . uniqid() . '_' . time();
        }
    }
}

// ================================
// FALLBACK ADMIN CLASSES
// ================================

if (!class_exists('ChatShop\\ChatShop_Admin')) {
    /**
     * Admin functionality fallback
     *
     * @since 1.0.0
     */
    class ChatShop_Admin
    {
        /**
         * Constructor
         *
         * @since 1.0.0
         */
        public function __construct()
        {
            add_action('admin_menu', array($this, 'add_admin_menu'));
            add_action('admin_init', array($this, 'admin_init'));
        }

        /**
         * Add admin menu
         *
         * @since 1.0.0
         */
        public function add_admin_menu()
        {
            add_menu_page(
                __('ChatShop', 'chatshop'),
                __('ChatShop', 'chatshop'),
                'manage_options',
                'chatshop',
                array($this, 'admin_page'),
                'dashicons-whatsapp',
                30
            );
        }

        /**
         * Admin initialization
         *
         * @since 1.0.0
         */
        public function admin_init()
        {
            register_setting('chatshop_settings', 'chatshop_settings');
        }

        /**
         * Admin page
         *
         * @since 1.0.0
         */
        public function admin_page()
        {
            echo '<div class="wrap">';
            echo '<h1>' . __('ChatShop Settings', 'chatshop') . '</h1>';
            echo '<div class="notice notice-info"><p>';
            echo __('ChatShop is loading components... If this message persists, some core files may be missing.', 'chatshop');
            echo '</p></div>';
            echo '</div>';
        }
    }
}

if (!class_exists('ChatShop\\ChatShop_Settings')) {
    /**
     * Settings management fallback
     *
     * @since 1.0.0
     */
    class ChatShop_Settings
    {
        /**
         * Get setting value
         *
         * @param string $key Setting key
         * @param mixed $default Default value
         * @return mixed Setting value
         * @since 1.0.0
         */
        public function get_setting($key, $default = '')
        {
            $settings = get_option('chatshop_settings', array());
            return isset($settings[$key]) ? $settings[$key] : $default;
        }

        /**
         * Update setting
         *
         * @param string $key Setting key
         * @param mixed $value Setting value
         * @return bool Success status
         * @since 1.0.0
         */
        public function update_setting($key, $value)
        {
            $settings = get_option('chatshop_settings', array());
            $settings[$key] = $value;
            return update_option('chatshop_settings', $settings);
        }
    }
}

// ================================
// FALLBACK PUBLIC CLASS
// ================================

if (!class_exists('ChatShop\\ChatShop_Public')) {
    /**
     * Public functionality fallback
     *
     * @since 1.0.0
     */
    class ChatShop_Public
    {
        /**
         * Constructor
         *
         * @since 1.0.0
         */
        public function __construct()
        {
            add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        }

        /**
         * Enqueue public scripts
         *
         * @since 1.0.0
         */
        public function enqueue_scripts()
        {
            // Only enqueue if needed and file exists
            if (!is_admin()) {
                $script_path = plugin_dir_path(__FILE__) . '../public/js/chatshop-public.js';
                if (file_exists($script_path)) {
                    wp_enqueue_script('chatshop-public', plugin_dir_url(__FILE__) . '../public/js/chatshop-public.js', array('jquery'), '1.0.0', true);
                }
            }
        }
    }
}

// ================================
// FALLBACK ABSTRACT CLASSES
// ================================

if (!class_exists('ChatShop\\ChatShop_Abstract_Payment_Gateway')) {
    /**
     * Abstract payment gateway fallback
     *
     * @since 1.0.0
     */
    abstract class ChatShop_Abstract_Payment_Gateway
    {
        /**
         * Gateway ID
         *
         * @var string
         * @since 1.0.0
         */
        protected $id;

        /**
         * Gateway title
         *
         * @var string
         * @since 1.0.0
         */
        protected $title;

        /**
         * Gateway description
         *
         * @var string
         * @since 1.0.0
         */
        protected $description;

        /**
         * Test mode
         *
         * @var bool
         * @since 1.0.0
         */
        protected $test_mode = true;

        /**
         * Constructor
         *
         * @since 1.0.0
         */
        public function __construct()
        {
            $this->init();
        }

        /**
         * Initialize gateway
         *
         * @since 1.0.0
         */
        abstract protected function init();

        /**
         * Process payment
         *
         * @param float $amount Amount
         * @param string $currency Currency
         * @param array $customer_data Customer data
         * @return array Result
         * @since 1.0.0
         */
        abstract public function process_payment($amount, $currency, $customer_data);

        /**
         * Verify transaction
         *
         * @param string $transaction_id Transaction ID
         * @return array Result
         * @since 1.0.0
         */
        abstract public function verify_transaction($transaction_id);

        /**
         * Handle webhook
         *
         * @param array $payload Webhook payload
         * @return array Result
         * @since 1.0.0
         */
        abstract public function handle_webhook($payload);

        /**
         * Check if in test mode
         *
         * @return bool True if test mode
         * @since 1.0.0
         */
        public function is_test_mode()
        {
            return $this->test_mode;
        }

        /**
         * Get setting value
         *
         * @param string $key Setting key
         * @param mixed $default Default value
         * @return mixed Setting value
         * @since 1.0.0
         */
        protected function get_setting($key, $default = '')
        {
            $settings = get_option('chatshop_' . $this->id . '_settings', array());
            return isset($settings[$key]) ? $settings[$key] : $default;
        }
    }
}

// ================================
// LOG FALLBACK LOADING
// ================================

// Log successful loading of fallbacks
if (function_exists('ChatShop\\chatshop_log')) {
    chatshop_log('Missing core classes fallback bundle loaded (no conflicts)', 'info');
} else {
    // Even more basic fallback logging
    if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        error_log('[ChatShop-Fallback] Missing core classes bundle loaded successfully');
    }
}
