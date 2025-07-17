<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://modewebhost.com.ng
 * @since      1.0.0
 *
 * @package    ChatShop
 * @subpackage ChatShop/admin
 */

namespace ChatShop\Admin;

// If this file is called directly, abort.
if (! defined('WPINC')) {
    die;
}

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and handles the admin dashboard
 * menu structure and settings pages.
 *
 * @package    ChatShop
 * @subpackage ChatShop/admin
 * @author     Modewebhost
 */
class ChatShop_Admin
{

    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $plugin_name    The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $version    The current version of this plugin.
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param    string    $plugin_name    The name of this plugin.
     * @param    string    $version        The version of this plugin.
     */
    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_styles()
    {
        wp_enqueue_style(
            $this->plugin_name,
            plugin_dir_url(__FILE__) . 'css/chatshop-admin.css',
            array(),
            $this->version,
            'all'
        );
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts()
    {
        wp_enqueue_script(
            $this->plugin_name,
            plugin_dir_url(__FILE__) . 'js/chatshop-admin.js',
            array('jquery'),
            $this->version,
            false
        );

        // Localize script for AJAX calls
        wp_localize_script(
            $this->plugin_name,
            'chatshop_ajax',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('chatshop_ajax_nonce'),
            )
        );
    }

    /**
     * Add admin menu and submenus.
     *
     * @since    1.0.0
     */
    public function add_admin_menu()
    {
        // Add top-level menu
        add_menu_page(
            __('ChatShop', 'chatshop'),
            __('ChatShop', 'chatshop'),
            'manage_options',
            'chatshop',
            array($this, 'display_dashboard_page'),
            'dashicons-format-chat',
            30
        );

        // Add Dashboard submenu (duplicate of main menu)
        add_submenu_page(
            'chatshop',
            __('Dashboard', 'chatshop'),
            __('Dashboard', 'chatshop'),
            'manage_options',
            'chatshop',
            array($this, 'display_dashboard_page')
        );

        // Add Settings submenu
        add_submenu_page(
            'chatshop',
            __('Settings', 'chatshop'),
            __('Settings', 'chatshop'),
            'manage_options',
            'chatshop-settings',
            array($this, 'display_settings_page')
        );

        // Add Campaigns submenu
        add_submenu_page(
            'chatshop',
            __('Campaigns', 'chatshop'),
            __('Campaigns', 'chatshop'),
            'manage_options',
            'chatshop-campaigns',
            array($this, 'display_campaigns_page')
        );

        // Add Analytics submenu (Premium only)
        if ($this->is_premium_active()) {
            add_submenu_page(
                'chatshop',
                __('Analytics', 'chatshop'),
                __('Analytics', 'chatshop'),
                'manage_options',
                'chatshop-analytics',
                array($this, 'display_analytics_page')
            );
        }
    }

    /**
     * Display the dashboard page.
     *
     * @since    1.0.0
     */
    public function display_dashboard_page()
    {
        // Check user capabilities
        if (! current_user_can('manage_options')) {
            return;
        }

        // Include the dashboard partial
        require_once plugin_dir_path(__FILE__) . 'partials/dashboard.php';
    }

    /**
     * Display the settings page.
     *
     * @since    1.0.0
     */
    public function display_settings_page()
    {
        // Check user capabilities
        if (! current_user_can('manage_options')) {
            return;
        }

        // Get active tab
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';

        // Include the settings partial
        require_once plugin_dir_path(__FILE__) . 'partials/settings.php';
    }

    /**
     * Display the campaigns page.
     *
     * @since    1.0.0
     */
    public function display_campaigns_page()
    {
        // Check user capabilities
        if (! current_user_can('manage_options')) {
            return;
        }

        // Include the campaigns partial
        require_once plugin_dir_path(__FILE__) . 'partials/campaigns.php';
    }

    /**
     * Display the analytics page (Premium only).
     *
     * @since    1.0.0
     */
    public function display_analytics_page()
    {
        // Check user capabilities
        if (! current_user_can('manage_options')) {
            return;
        }

        // Check premium status
        if (! $this->is_premium_active()) {
            wp_die(__('This feature requires ChatShop Premium.', 'chatshop'));
        }

        // Include the analytics partial
        require_once plugin_dir_path(__FILE__) . 'partials/analytics.php';
    }

    /**
     * Register plugin settings.
     *
     * @since    1.0.0
     */
    public function register_settings()
    {
        // General settings
        register_setting('chatshop_general', 'chatshop_enable');
        register_setting('chatshop_general', 'chatshop_license_key');

        // WhatsApp settings
        register_setting('chatshop_whatsapp', 'chatshop_whatsapp_phone');
        register_setting('chatshop_whatsapp', 'chatshop_whatsapp_token');
        register_setting('chatshop_whatsapp', 'chatshop_whatsapp_webhook_secret');

        // Payment settings
        register_setting('chatshop_payments', 'chatshop_payment_gateways');
        register_setting('chatshop_payments', 'chatshop_default_gateway');

        // Paystack settings
        register_setting('chatshop_payments', 'chatshop_paystack_public_key');
        register_setting('chatshop_payments', 'chatshop_paystack_secret_key');
        register_setting('chatshop_payments', 'chatshop_paystack_test_mode');
    }

    /**
     * Add settings sections and fields.
     *
     * @since    1.0.0
     */
    public function add_settings_sections()
    {
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
            __('Payment Gateway Settings', 'chatshop'),
            array($this, 'payments_section_callback'),
            'chatshop_payments'
        );

        // Add fields
        $this->add_settings_fields();
    }

    /**
     * Add individual settings fields.
     *
     * @since    1.0.0
     */
    private function add_settings_fields()
    {
        // General fields
        add_settings_field(
            'chatshop_enable',
            __('Enable ChatShop', 'chatshop'),
            array($this, 'render_checkbox_field'),
            'chatshop_general',
            'chatshop_general_section',
            array(
                'label_for' => 'chatshop_enable',
                'description' => __('Enable or disable ChatShop functionality.', 'chatshop'),
            )
        );

        // WhatsApp fields
        add_settings_field(
            'chatshop_whatsapp_phone',
            __('WhatsApp Business Phone', 'chatshop'),
            array($this, 'render_text_field'),
            'chatshop_whatsapp',
            'chatshop_whatsapp_section',
            array(
                'label_for' => 'chatshop_whatsapp_phone',
                'description' => __('Enter your WhatsApp Business phone number with country code.', 'chatshop'),
                'placeholder' => '+1234567890',
            )
        );

        // Paystack fields
        add_settings_field(
            'chatshop_paystack_public_key',
            __('Paystack Public Key', 'chatshop'),
            array($this, 'render_text_field'),
            'chatshop_payments',
            'chatshop_payments_section',
            array(
                'label_for' => 'chatshop_paystack_public_key',
                'description' => __('Enter your Paystack public key.', 'chatshop'),
            )
        );
    }

    /**
     * Render checkbox field.
     *
     * @since    1.0.0
     * @param    array    $args    Field arguments.
     */
    public function render_checkbox_field($args)
    {
        $option = get_option($args['label_for']);
?>
        <input type="checkbox"
            id="<?php echo esc_attr($args['label_for']); ?>"
            name="<?php echo esc_attr($args['label_for']); ?>"
            value="1"
            <?php checked($option, 1); ?> />
        <?php if (! empty($args['description'])) : ?>
            <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php endif;
    }

    /**
     * Render text field.
     *
     * @since    1.0.0
     * @param    array    $args    Field arguments.
     */
    public function render_text_field($args)
    {
        $option = get_option($args['label_for']);
        ?>
        <input type="text"
            id="<?php echo esc_attr($args['label_for']); ?>"
            name="<?php echo esc_attr($args['label_for']); ?>"
            value="<?php echo esc_attr($option); ?>"
            class="regular-text"
            <?php echo isset($args['placeholder']) ? 'placeholder="' . esc_attr($args['placeholder']) . '"' : ''; ?> />
        <?php if (! empty($args['description'])) : ?>
            <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php endif;
    }

    /**
     * General section callback.
     *
     * @since    1.0.0
     */
    public function general_section_callback()
    {
        echo '<p>' . __('Configure general ChatShop settings.', 'chatshop') . '</p>';
    }

    /**
     * WhatsApp section callback.
     *
     * @since    1.0.0
     */
    public function whatsapp_section_callback()
    {
        echo '<p>' . __('Configure your WhatsApp Business API credentials.', 'chatshop') . '</p>';
    }

    /**
     * Payments section callback.
     *
     * @since    1.0.0
     */
    public function payments_section_callback()
    {
        echo '<p>' . __('Configure payment gateway settings for processing transactions.', 'chatshop') . '</p>';
    }

    /**
     * Check if premium version is active.
     *
     * @since    1.0.0
     * @return   bool    True if premium is active, false otherwise.
     */
    private function is_premium_active()
    {
        // Check for premium license or plugin
        $license_key = get_option('chatshop_license_key');
        return ! empty($license_key) && $this->validate_license($license_key);
    }

    /**
     * Validate premium license.
     *
     * @since    1.0.0
     * @param    string    $license_key    The license key to validate.
     * @return   bool                      True if valid, false otherwise.
     */
    private function validate_license($license_key)
    {
        // Implement license validation logic
        // This is a placeholder - implement actual validation
        return true;
    }

    /**
     * Add admin notices.
     *
     * @since    1.0.0
     */
    public function add_admin_notices()
    {
        // Check if plugin is configured
        if (! get_option('chatshop_whatsapp_phone') && current_user_can('manage_options')) {
        ?>
            <div class="notice notice-warning is-dismissible">
                <p>
                    <?php
                    printf(
                        /* translators: %s: Settings page URL */
                        __('ChatShop is almost ready. Please <a href="%s">configure your WhatsApp settings</a> to get started.', 'chatshop'),
                        esc_url(admin_url('admin.php?page=chatshop-settings&tab=whatsapp'))
                    );
                    ?>
                </p>
            </div>
<?php
        }
    }

    /**
     * Add plugin action links.
     *
     * @since    1.0.0
     * @param    array    $links    Existing plugin action links.
     * @return   array              Modified plugin action links.
     */
    public function add_action_links($links)
    {
        $action_links = array(
            'settings' => '<a href="' . admin_url('admin.php?page=chatshop-settings') . '">' . __('Settings', 'chatshop') . '</a>',
        );

        if (! $this->is_premium_active()) {
            $action_links['upgrade'] = '<a href="https://modewebhost.com.ng/chatshop-premium" style="color: #93003c; font-weight: bold;">' . __('Upgrade to Premium', 'chatshop') . '</a>';
        }

        return array_merge($action_links, $links);
    }

    /**
     * Initialize admin AJAX handlers.
     *
     * @since    1.0.0
     */
    public function init_ajax_handlers()
    {
        // Test WhatsApp connection
        add_action('wp_ajax_chatshop_test_whatsapp', array($this, 'ajax_test_whatsapp'));

        // Test payment gateway
        add_action('wp_ajax_chatshop_test_gateway', array($this, 'ajax_test_gateway'));
    }

    /**
     * AJAX handler for testing WhatsApp connection.
     *
     * @since    1.0.0
     */
    public function ajax_test_whatsapp()
    {
        // Verify nonce
        if (! wp_verify_nonce($_POST['nonce'], 'chatshop_ajax_nonce')) {
            wp_die(__('Security check failed', 'chatshop'));
        }

        // Check capabilities
        if (! current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'chatshop'));
        }

        // Test WhatsApp connection
        // This is a placeholder - implement actual connection test
        wp_send_json_success(array(
            'message' => __('WhatsApp connection successful!', 'chatshop'),
        ));
    }

    /**
     * AJAX handler for testing payment gateway.
     *
     * @since    1.0.0
     */
    public function ajax_test_gateway()
    {
        // Verify nonce
        if (! wp_verify_nonce($_POST['nonce'], 'chatshop_ajax_nonce')) {
            wp_die(__('Security check failed', 'chatshop'));
        }

        // Check capabilities
        if (! current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'chatshop'));
        }

        $gateway = sanitize_text_field($_POST['gateway']);

        // Test payment gateway connection
        // This is a placeholder - implement actual gateway test
        wp_send_json_success(array(
            'message' => sprintf(
                /* translators: %s: Gateway name */
                __('%s connection successful!', 'chatshop'),
                ucfirst($gateway)
            ),
        ));
    }
}
