<?php

/**
 * The admin-specific functionality of the plugin
 *
 * @package ChatShop
 * @subpackage ChatShop/admin
 * @since 1.0.0
 */

namespace ChatShop;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * The admin-specific functionality of the plugin
 *
 * Defines the plugin name, version, and handles admin-side functionality
 * including admin pages, settings, and admin-specific hooks
 *
 * @since 1.0.0
 */
class ChatShop_Admin
{
    /**
     * The version of this plugin
     *
     * @since 1.0.0
     * @var string
     */
    private $version;

    /**
     * Admin menu pages
     *
     * @since 1.0.0
     * @var array
     */
    private $menu_pages = array();

    /**
     * Initialize the class and set its properties
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        $this->version = defined('CHATSHOP_VERSION') ? CHATSHOP_VERSION : '1.0.0';
    }

    /**
     * Register the stylesheets for the admin area
     *
     * @since 1.0.0
     * @param string $hook_suffix The current admin page
     */
    public function enqueue_styles($hook_suffix)
    {
        // Only load on ChatShop admin pages
        if (!$this->is_chatshop_admin_page($hook_suffix)) {
            return;
        }

        wp_enqueue_style(
            'chatshop-admin',
            CHATSHOP_PLUGIN_URL . 'admin/css/chatshop-admin.css',
            array(),
            $this->version,
            'all'
        );

        // Enqueue WordPress admin styles we depend on
        wp_enqueue_style('wp-color-picker');
    }

    /**
     * Register the JavaScript for the admin area
     *
     * @since 1.0.0
     * @param string $hook_suffix The current admin page
     */
    public function enqueue_scripts($hook_suffix)
    {
        // Only load on ChatShop admin pages
        if (!$this->is_chatshop_admin_page($hook_suffix)) {
            return;
        }

        wp_enqueue_script(
            'chatshop-admin',
            CHATSHOP_PLUGIN_URL . 'admin/js/chatshop-admin.js',
            array('jquery', 'wp-color-picker'),
            $this->version,
            true
        );

        // Localize script with admin data
        wp_localize_script('chatshop-admin', 'chatshop_admin', array(
            'ajax_url'    => admin_url('admin-ajax.php'),
            'nonce'       => wp_create_nonce('chatshop_admin_nonce'),
            'plugin_url'  => CHATSHOP_PLUGIN_URL,
            'strings'     => array(
                'confirm_delete' => __('Are you sure you want to delete this item?', 'chatshop'),
                'save_success'   => __('Settings saved successfully!', 'chatshop'),
                'save_error'     => __('Error saving settings. Please try again.', 'chatshop'),
                'loading'        => __('Loading...', 'chatshop')
            )
        ));
    }

    /**
     * Add admin menu pages
     *
     * @since 1.0.0
     */
    public function add_admin_menu()
    {
        // Main menu page
        $main_page = add_menu_page(
            __('ChatShop', 'chatshop'),
            __('ChatShop', 'chatshop'),
            'manage_options',
            'chatshop',
            array($this, 'display_dashboard_page'),
            'dashicons-whatsapp',
            30
        );

        $this->menu_pages['main'] = $main_page;

        // Dashboard submenu
        $dashboard_page = add_submenu_page(
            'chatshop',
            __('Dashboard', 'chatshop'),
            __('Dashboard', 'chatshop'),
            'manage_options',
            'chatshop',
            array($this, 'display_dashboard_page')
        );

        $this->menu_pages['dashboard'] = $dashboard_page;

        // Settings submenu
        $settings_page = add_submenu_page(
            'chatshop',
            __('Settings', 'chatshop'),
            __('Settings', 'chatshop'),
            'manage_options',
            'chatshop-settings',
            array($this, 'display_settings_page')
        );

        $this->menu_pages['settings'] = $settings_page;

        // WhatsApp submenu
        $whatsapp_page = add_submenu_page(
            'chatshop',
            __('WhatsApp', 'chatshop'),
            __('WhatsApp', 'chatshop'),
            'manage_options',
            'chatshop-whatsapp',
            array($this, 'display_whatsapp_page')
        );

        $this->menu_pages['whatsapp'] = $whatsapp_page;

        // Payments submenu
        $payments_page = add_submenu_page(
            'chatshop',
            __('Payments', 'chatshop'),
            __('Payments', 'chatshop'),
            'manage_options',
            'chatshop-payments',
            array($this, 'display_payments_page')
        );

        $this->menu_pages['payments'] = $payments_page;

        // Analytics submenu
        $analytics_page = add_submenu_page(
            'chatshop',
            __('Analytics', 'chatshop'),
            __('Analytics', 'chatshop'),
            'manage_options',
            'chatshop-analytics',
            array($this, 'display_analytics_page')
        );

        $this->menu_pages['analytics'] = $analytics_page;
    }

    /**
     * Initialize admin settings
     *
     * @since 1.0.0
     */
    public function init_settings()
    {
        // Register settings
        register_setting('chatshop_general', 'chatshop_general_options');
        register_setting('chatshop_whatsapp', 'chatshop_whatsapp_options');
        register_setting('chatshop_payments', 'chatshop_payments_options');

        // General settings section
        add_settings_section(
            'chatshop_general_section',
            __('General Settings', 'chatshop'),
            array($this, 'general_section_callback'),
            'chatshop_general'
        );

        // WhatsApp settings section
        add_settings_section(
            'chatshop_whatsapp_section',
            __('WhatsApp Configuration', 'chatshop'),
            array($this, 'whatsapp_section_callback'),
            'chatshop_whatsapp'
        );

        // Payment settings section
        add_settings_section(
            'chatshop_payments_section',
            __('Payment Settings', 'chatshop'),
            array($this, 'payments_section_callback'),
            'chatshop_payments'
        );

        // Add settings fields
        $this->add_settings_fields();
    }

    /**
     * Add settings fields
     *
     * @since 1.0.0
     */
    private function add_settings_fields()
    {
        // General settings fields
        add_settings_field(
            'plugin_enabled',
            __('Enable ChatShop', 'chatshop'),
            array($this, 'checkbox_field_callback'),
            'chatshop_general',
            'chatshop_general_section',
            array(
                'option_name' => 'chatshop_general_options',
                'field_name'  => 'plugin_enabled',
                'description' => __('Enable or disable ChatShop functionality', 'chatshop')
            )
        );

        // WhatsApp fields
        add_settings_field(
            'whatsapp_api_token',
            __('WhatsApp API Token', 'chatshop'),
            array($this, 'text_field_callback'),
            'chatshop_whatsapp',
            'chatshop_whatsapp_section',
            array(
                'option_name' => 'chatshop_whatsapp_options',
                'field_name'  => 'api_token',
                'description' => __('Your WhatsApp Business API token', 'chatshop'),
                'type'        => 'password'
            )
        );

        add_settings_field(
            'whatsapp_phone_number',
            __('WhatsApp Phone Number', 'chatshop'),
            array($this, 'text_field_callback'),
            'chatshop_whatsapp',
            'chatshop_whatsapp_section',
            array(
                'option_name' => 'chatshop_whatsapp_options',
                'field_name'  => 'phone_number',
                'description' => __('Your WhatsApp Business phone number', 'chatshop')
            )
        );

        // Payment fields
        add_settings_field(
            'paystack_secret_key',
            __('Paystack Secret Key', 'chatshop'),
            array($this, 'text_field_callback'),
            'chatshop_payments',
            'chatshop_payments_section',
            array(
                'option_name' => 'chatshop_payments_options',
                'field_name'  => 'paystack_secret_key',
                'description' => __('Your Paystack secret key', 'chatshop'),
                'type'        => 'password'
            )
        );

        add_settings_field(
            'paystack_public_key',
            __('Paystack Public Key', 'chatshop'),
            array($this, 'text_field_callback'),
            'chatshop_payments',
            'chatshop_payments_section',
            array(
                'option_name' => 'chatshop_payments_options',
                'field_name'  => 'paystack_public_key',
                'description' => __('Your Paystack public key', 'chatshop')
            )
        );
    }

    /**
     * Display dashboard page
     *
     * @since 1.0.0
     */
    public function display_dashboard_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'chatshop'));
        }

        include_once CHATSHOP_PLUGIN_DIR . 'admin/partials/dashboard.php';
    }

    /**
     * Display settings page
     *
     * @since 1.0.0
     */
    public function display_settings_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'chatshop'));
        }

        include_once CHATSHOP_PLUGIN_DIR . 'admin/partials/settings-general.php';
    }

    /**
     * Display WhatsApp page
     *
     * @since 1.0.0
     */
    public function display_whatsapp_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'chatshop'));
        }

        include_once CHATSHOP_PLUGIN_DIR . 'admin/partials/settings-whatsapp.php';
    }

    /**
     * Display payments page
     *
     * @since 1.0.0
     */
    public function display_payments_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'chatshop'));
        }

        include_once CHATSHOP_PLUGIN_DIR . 'admin/partials/settings-payments.php';
    }

    /**
     * Display analytics page
     *
     * @since 1.0.0
     */
    public function display_analytics_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'chatshop'));
        }

        include_once CHATSHOP_PLUGIN_DIR . 'admin/partials/analytics.php';
    }

    /**
     * Handle AJAX requests
     *
     * @since 1.0.0
     */
    public function handle_ajax_request()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'chatshop_admin_nonce')) {
            wp_die(__('Security check failed', 'chatshop'));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'chatshop'));
        }

        $action = sanitize_text_field($_POST['chatshop_action']);

        switch ($action) {
            case 'test_whatsapp_connection':
                $this->test_whatsapp_connection();
                break;

            case 'test_payment_gateway':
                $this->test_payment_gateway();
                break;

            case 'get_analytics_data':
                $this->get_analytics_data();
                break;

            default:
                wp_send_json_error(__('Invalid action', 'chatshop'));
        }
    }

    /**
     * Test WhatsApp connection
     *
     * @since 1.0.0
     */
    private function test_whatsapp_connection()
    {
        // This will be implemented when WhatsApp component is available
        wp_send_json_success(array(
            'message' => __('WhatsApp connection test completed', 'chatshop')
        ));
    }

    /**
     * Test payment gateway
     *
     * @since 1.0.0
     */
    private function test_payment_gateway()
    {
        // This will be implemented when payment component is available
        wp_send_json_success(array(
            'message' => __('Payment gateway test completed', 'chatshop')
        ));
    }

    /**
     * Get analytics data
     *
     * @since 1.0.0
     */
    private function get_analytics_data()
    {
        // This will be implemented when analytics component is available
        wp_send_json_success(array(
            'data' => array(
                'total_sales'    => 0,
                'whatsapp_leads' => 0,
                'conversion_rate' => 0
            )
        ));
    }

    /**
     * Check if current page is a ChatShop admin page
     *
     * @since 1.0.0
     * @param string $hook_suffix Current admin page hook
     * @return bool
     */
    private function is_chatshop_admin_page($hook_suffix)
    {
        return in_array($hook_suffix, $this->menu_pages) ||
            strpos($hook_suffix, 'chatshop') !== false;
    }

    /**
     * General settings section callback
     *
     * @since 1.0.0
     */
    public function general_section_callback()
    {
        echo '<p>' . __('Configure general ChatShop settings.', 'chatshop') . '</p>';
    }

    /**
     * WhatsApp settings section callback
     *
     * @since 1.0.0
     */
    public function whatsapp_section_callback()
    {
        echo '<p>' . __('Configure WhatsApp Business API integration.', 'chatshop') . '</p>';
    }

    /**
     * Payment settings section callback
     *
     * @since 1.0.0
     */
    public function payments_section_callback()
    {
        echo '<p>' . __('Configure payment gateway settings.', 'chatshop') . '</p>';
    }

    /**
     * Text field callback
     *
     * @since 1.0.0
     * @param array $args Field arguments
     */
    public function text_field_callback($args)
    {
        $options = get_option($args['option_name'], array());
        $value = isset($options[$args['field_name']]) ? $options[$args['field_name']] : '';
        $type = isset($args['type']) ? $args['type'] : 'text';

        printf(
            '<input type="%s" id="%s" name="%s[%s]" value="%s" class="regular-text" />',
            esc_attr($type),
            esc_attr($args['field_name']),
            esc_attr($args['option_name']),
            esc_attr($args['field_name']),
            esc_attr($value)
        );

        if (isset($args['description'])) {
            printf('<p class="description">%s</p>', esc_html($args['description']));
        }
    }

    /**
     * Checkbox field callback
     *
     * @since 1.0.0
     * @param array $args Field arguments
     */
    public function checkbox_field_callback($args)
    {
        $options = get_option($args['option_name'], array());
        $value = isset($options[$args['field_name']]) ? $options[$args['field_name']] : '';

        printf(
            '<input type="checkbox" id="%s" name="%s[%s]" value="1" %s />',
            esc_attr($args['field_name']),
            esc_attr($args['option_name']),
            esc_attr($args['field_name']),
            checked(1, $value, false)
        );

        if (isset($args['description'])) {
            printf('<p class="description">%s</p>', esc_html($args['description']));
        }
    }

    /**
     * Get admin menu pages
     *
     * @since 1.0.0
     * @return array
     */
    public function get_menu_pages()
    {
        return $this->menu_pages;
    }
}
