<?php

/**
 * The admin-specific functionality of the plugin
 *
 * @link       https://github.com/Chatshop-Plugin/chatshop
 * @since      1.0.0
 *
 * @package    ChatShop
 * @subpackage ChatShop/admin
 */

namespace ChatShop\Admin;

/**
 * The admin-specific functionality of the plugin
 *
 * Defines the plugin name, version, and hooks for the admin area
 *
 * @package    ChatShop
 * @subpackage ChatShop/admin
 * @author     ChatShop Team
 */
class ChatShop_Admin
{

    /**
     * The ID of this plugin
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $plugin_name    The ID of this plugin
     */
    private $plugin_name;

    /**
     * The version of this plugin
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $version    The current version of this plugin
     */
    private $version;

    /**
     * Initialize the class and set its properties
     *
     * @since    1.0.0
     * @param    string    $plugin_name       The name of this plugin
     * @param    string    $version    The version of this plugin
     */
    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Register the stylesheets for the admin area
     *
     * @since    1.0.0
     */
    public function enqueue_styles()
    {
        // Only load on ChatShop admin pages
        $screen = get_current_screen();
        if (strpos($screen->id, 'chatshop') !== false) {
            wp_enqueue_style(
                $this->plugin_name,
                CHATSHOP_PLUGIN_URL . 'admin/css/chatshop-admin.css',
                array(),
                $this->version,
                'all'
            );
        }
    }

    /**
     * Register the JavaScript for the admin area
     *
     * @since    1.0.0
     */
    public function enqueue_scripts()
    {
        // Only load on ChatShop admin pages
        $screen = get_current_screen();
        if (strpos($screen->id, 'chatshop') !== false) {
            wp_enqueue_script(
                $this->plugin_name,
                CHATSHOP_PLUGIN_URL . 'admin/js/chatshop-admin.js',
                array('jquery'),
                $this->version,
                false
            );

            // Localize script for AJAX
            wp_localize_script(
                $this->plugin_name,
                'chatshop_ajax',
                array(
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce'    => wp_create_nonce('chatshop_ajax_nonce'),
                )
            );
        }
    }

    /**
     * Add admin menu pages
     *
     * @since    1.0.0
     */
    public function add_admin_menu()
    {
        // Main menu
        add_menu_page(
            __('ChatShop', 'chatshop'),
            __('ChatShop', 'chatshop'),
            'manage_options',
            'chatshop',
            array($this, 'display_dashboard_page'),
            $this->get_menu_icon(),
            55 // Position after WooCommerce
        );

        // Dashboard submenu (rename the first item)
        add_submenu_page(
            'chatshop',
            __('Dashboard', 'chatshop'),
            __('Dashboard', 'chatshop'),
            'manage_options',
            'chatshop',
            array($this, 'display_dashboard_page')
        );

        // Settings submenu
        add_submenu_page(
            'chatshop',
            __('Settings', 'chatshop'),
            __('Settings', 'chatshop'),
            'manage_options',
            'chatshop-settings',
            array($this, 'display_settings_page')
        );

        // Campaigns submenu
        add_submenu_page(
            'chatshop',
            __('Campaigns', 'chatshop'),
            __('Campaigns', 'chatshop'),
            'manage_options',
            'chatshop-campaigns',
            array($this, 'display_campaigns_page')
        );

        // Analytics submenu (premium only)
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
     * Display the dashboard page
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
        include_once CHATSHOP_PLUGIN_DIR . 'admin/partials/dashboard.php';
    }

    /**
     * Display the settings page
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

        // Include the appropriate settings partial
        switch ($active_tab) {
            case 'whatsapp':
                include_once CHATSHOP_PLUGIN_DIR . 'admin/partials/settings-whatsapp.php';
                break;
            case 'payments':
                include_once CHATSHOP_PLUGIN_DIR . 'admin/partials/settings-payments.php';
                break;
            default:
                include_once CHATSHOP_PLUGIN_DIR . 'admin/partials/settings-general.php';
                break;
        }
    }

    /**
     * Display the campaigns page
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
        include_once CHATSHOP_PLUGIN_DIR . 'admin/partials/campaigns.php';
    }

    /**
     * Display the analytics page
     *
     * @since    1.0.0
     */
    public function display_analytics_page()
    {
        // Check user capabilities
        if (! current_user_can('manage_options')) {
            return;
        }

        // Check if premium is active
        if (! $this->is_premium_active()) {
            wp_die(__('This feature requires ChatShop Premium.', 'chatshop'));
        }

        // Include the analytics partial
        include_once CHATSHOP_PLUGIN_DIR . 'admin/partials/analytics.php';
    }

    /**
     * Add action links to plugins page
     *
     * @since    1.0.0
     * @param    array    $links    Existing links
     * @return   array              Modified links
     */
    public function add_action_links($links)
    {
        $action_links = array(
            '<a href="' . admin_url('admin.php?page=chatshop-settings') . '">' . __('Settings', 'chatshop') . '</a>',
        );

        if (! $this->is_premium_active()) {
            $action_links[] = '<a href="https://chatshop.com/premium" target="_blank" style="color: #00a32a; font-weight: bold;">' . __('Go Premium', 'chatshop') . '</a>';
        }

        return array_merge($action_links, $links);
    }

    /**
     * Add custom admin notices
     *
     * @since    1.0.0
     */
    public function display_admin_notices()
    {
        // Check if WooCommerce is active
        if (! class_exists('WooCommerce')) {
?>
            <div class="notice notice-error">
                <p>
                    <strong><?php esc_html_e('ChatShop requires WooCommerce to be installed and active.', 'chatshop'); ?></strong>
                    <?php
                    $install_url = wp_nonce_url(
                        self_admin_url('update.php?action=install-plugin&plugin=woocommerce'),
                        'install-plugin_woocommerce'
                    );
                    ?>
                    <a href="<?php echo esc_url($install_url); ?>" class="button button-primary"><?php esc_html_e('Install WooCommerce', 'chatshop'); ?></a>
                </p>
            </div>
        <?php
        }

        // Check if setup is complete
        if (! get_option('chatshop_setup_complete') && current_user_can('manage_options')) {
        ?>
            <div class="notice notice-info is-dismissible">
                <p>
                    <strong><?php esc_html_e('Welcome to ChatShop!', 'chatshop'); ?></strong>
                    <?php esc_html_e('Get started by configuring your WhatsApp and payment settings.', 'chatshop'); ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=chatshop-settings')); ?>" class="button button-primary" style="margin-left: 10px;"><?php esc_html_e('Configure Settings', 'chatshop'); ?></a>
                </p>
            </div>
<?php
        }
    }

    /**
     * Initialize admin settings
     *
     * @since    1.0.0
     */
    public function init_settings()
    {
        // Register general settings
        register_setting('chatshop_general_settings', 'chatshop_general_options', array($this, 'sanitize_general_options'));

        // Register WhatsApp settings
        register_setting('chatshop_whatsapp_settings', 'chatshop_whatsapp_options', array($this, 'sanitize_whatsapp_options'));

        // Register payment settings
        register_setting('chatshop_payment_settings', 'chatshop_payment_options', array($this, 'sanitize_payment_options'));
    }

    /**
     * Sanitize general options
     *
     * @since    1.0.0
     * @param    array    $input    Raw input data
     * @return   array              Sanitized data
     */
    public function sanitize_general_options($input)
    {
        $sanitized = array();

        if (isset($input['enable_logging'])) {
            $sanitized['enable_logging'] = (bool) $input['enable_logging'];
        }

        if (isset($input['default_currency'])) {
            $sanitized['default_currency'] = sanitize_text_field($input['default_currency']);
        }

        return $sanitized;
    }

    /**
     * Sanitize WhatsApp options
     *
     * @since    1.0.0
     * @param    array    $input    Raw input data
     * @return   array              Sanitized data
     */
    public function sanitize_whatsapp_options($input)
    {
        $sanitized = array();

        if (isset($input['phone_number'])) {
            $sanitized['phone_number'] = sanitize_text_field($input['phone_number']);
        }

        if (isset($input['api_token'])) {
            $sanitized['api_token'] = sanitize_text_field($input['api_token']);
        }

        if (isset($input['webhook_url'])) {
            $sanitized['webhook_url'] = esc_url_raw($input['webhook_url']);
        }

        return $sanitized;
    }

    /**
     * Sanitize payment options
     *
     * @since    1.0.0
     * @param    array    $input    Raw input data
     * @return   array              Sanitized data
     */
    public function sanitize_payment_options($input)
    {
        $sanitized = array();

        $payment_gateways = array('paystack', 'paypal', 'flutterwave', 'razorpay');

        foreach ($payment_gateways as $gateway) {
            if (isset($input[$gateway]['enabled'])) {
                $sanitized[$gateway]['enabled'] = (bool) $input[$gateway]['enabled'];
            }

            if (isset($input[$gateway]['public_key'])) {
                $sanitized[$gateway]['public_key'] = sanitize_text_field($input[$gateway]['public_key']);
            }

            if (isset($input[$gateway]['secret_key'])) {
                $sanitized[$gateway]['secret_key'] = sanitize_text_field($input[$gateway]['secret_key']);
            }

            if (isset($input[$gateway]['test_mode'])) {
                $sanitized[$gateway]['test_mode'] = (bool) $input[$gateway]['test_mode'];
            }
        }

        return $sanitized;
    }

    /**
     * Get menu icon SVG
     *
     * @since    1.0.0
     * @return   string    Base64 encoded SVG icon
     */
    private function get_menu_icon()
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>';
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    /**
     * Check if premium version is active
     *
     * @since    1.0.0
     * @return   bool    True if premium is active
     */
    private function is_premium_active()
    {
        return apply_filters('chatshop_is_premium_active', false);
    }

    /**
     * AJAX handler for dashboard widgets
     *
     * @since    1.0.0
     */
    public function ajax_dashboard_widget()
    {
        // Verify nonce
        if (! wp_verify_nonce($_POST['nonce'], 'chatshop_ajax_nonce')) {
            wp_die(__('Security check failed', 'chatshop'));
        }

        // Check capabilities
        if (! current_user_can('manage_options')) {
            wp_die(__('Permission denied', 'chatshop'));
        }

        $widget = isset($_POST['widget']) ? sanitize_text_field($_POST['widget']) : '';
        $response = array();

        switch ($widget) {
            case 'stats':
                $response = $this->get_dashboard_stats();
                break;
            case 'recent_activity':
                $response = $this->get_recent_activity();
                break;
            default:
                $response = array('error' => __('Invalid widget', 'chatshop'));
        }

        wp_send_json($response);
    }

    /**
     * Get dashboard statistics
     *
     * @since    1.0.0
     * @return   array    Dashboard stats
     */
    private function get_dashboard_stats()
    {
        return array(
            'total_messages' => 0,
            'total_payments' => 0,
            'conversion_rate' => 0,
            'revenue' => 0,
        );
    }

    /**
     * Get recent activity
     *
     * @since    1.0.0
     * @return   array    Recent activity items
     */
    private function get_recent_activity()
    {
        return array(
            'items' => array(),
        );
    }
}
