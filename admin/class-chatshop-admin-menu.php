<?php

/**
 * ChatShop Admin Menu Handler
 *
 * @package ChatShop
 * @since   1.0.0
 */

namespace ChatShop;

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Admin menu management class
 */
class ChatShop_Admin_Menu
{
    /**
     * Menu pages
     *
     * @var array
     */
    private $menu_pages = array();

    /**
     * Initialize admin menu
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    /**
     * Add admin menu pages
     *
     * @since 1.0.0
     */
    public function add_admin_menu()
    {
        $capability = 'manage_options';

        // Main menu page
        $this->menu_pages['main'] = add_menu_page(
            __('ChatShop', 'chatshop'),
            __('ChatShop', 'chatshop'),
            $capability,
            'chatshop',
            array($this, 'display_dashboard_page'),
            $this->get_menu_icon(),
            30
        );

        // Dashboard submenu (same as main)
        $this->menu_pages['dashboard'] = add_submenu_page(
            'chatshop',
            __('Dashboard', 'chatshop'),
            __('Dashboard', 'chatshop'),
            $capability,
            'chatshop',
            array($this, 'display_dashboard_page')
        );

        // General Settings
        $this->menu_pages['general'] = add_submenu_page(
            'chatshop',
            __('General Settings', 'chatshop'),
            __('General', 'chatshop'),
            $capability,
            'chatshop-general',
            array($this, 'display_general_settings_page')
        );

        // Payment Settings
        $this->menu_pages['payments'] = add_submenu_page(
            'chatshop',
            __('Payment Settings', 'chatshop'),
            __('Payments', 'chatshop'),
            $capability,
            'chatshop-payments',
            array($this, 'display_payment_settings_page')
        );

        // WhatsApp Settings
        $this->menu_pages['whatsapp'] = add_submenu_page(
            'chatshop',
            __('WhatsApp Settings', 'chatshop'),
            __('WhatsApp', 'chatshop'),
            $capability,
            'chatshop-whatsapp',
            array($this, 'display_whatsapp_settings_page')
        );

        // Analytics
        $this->menu_pages['analytics'] = add_submenu_page(
            'chatshop',
            __('Analytics', 'chatshop'),
            __('Analytics', 'chatshop'),
            $capability,
            'chatshop-analytics',
            array($this, 'display_analytics_page')
        );

        // Premium Features (if not available)
        if (!chatshop_is_premium_feature_available('multiple_gateways')) {
            $this->menu_pages['premium'] = add_submenu_page(
                'chatshop',
                __('Premium Features', 'chatshop'),
                __('Premium', 'chatshop') . ' <span class="awaiting-mod">★</span>',
                $capability,
                'chatshop-premium',
                array($this, 'display_premium_page')
            );
        }

        // Support
        $this->menu_pages['support'] = add_submenu_page(
            'chatshop',
            __('Support', 'chatshop'),
            __('Support', 'chatshop'),
            $capability,
            'chatshop-support',
            array($this, 'display_support_page')
        );
    }

    /**
     * Get menu icon
     *
     * @return string SVG icon data URL
     * @since 1.0.0
     */
    private function get_menu_icon()
    {
        // WhatsApp-style icon SVG
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893A11.821 11.821 0 0020.465 3.516"/>
        </svg>';

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    /**
     * Display dashboard page
     *
     * @since 1.0.0
     */
    public function display_dashboard_page()
    {
        $this->render_admin_page('dashboard');
    }

    /**
     * Display general settings page
     *
     * @since 1.0.0
     */
    public function display_general_settings_page()
    {
        $this->render_admin_page('settings-general');
    }

    /**
     * Display payment settings page
     *
     * @since 1.0.0
     */
    public function display_payment_settings_page()
    {
        $this->render_admin_page('settings-payments');
    }

    /**
     * Display WhatsApp settings page
     *
     * @since 1.0.0
     */
    public function display_whatsapp_settings_page()
    {
        $this->render_admin_page('settings-whatsapp');
    }

    /**
     * Display analytics page
     *
     * @since 1.0.0
     */
    public function display_analytics_page()
    {
        $this->render_admin_page('analytics');
    }

    /**
     * Display premium features page
     *
     * @since 1.0.0
     */
    public function display_premium_page()
    {
        $this->render_admin_page('premium');
    }

    /**
     * Display support page
     *
     * @since 1.0.0
     */
    public function display_support_page()
    {
        $this->render_admin_page('support');
    }

    /**
     * Render admin page template
     *
     * @param string $template Template name
     * @since 1.0.0
     */
    private function render_admin_page($template)
    {
        $template_path = CHATSHOP_PLUGIN_DIR . "admin/partials/{$template}.php";

        if (file_exists($template_path)) {
            include $template_path;
        } else {
            echo '<div class="wrap">';
            echo '<h1>' . esc_html__('Page Not Found', 'chatshop') . '</h1>';
            echo '<p>' . esc_html__('The requested page template could not be found.', 'chatshop') . '</p>';
            echo '</div>';

            chatshop_log("Admin template not found: {$template_path}", 'error');
        }
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook Current admin page hook
     * @since 1.0.0
     */
    public function enqueue_admin_assets($hook)
    {
        // Only load on our admin pages
        if (strpos($hook, 'chatshop') === false) {
            return;
        }

        // Admin CSS
        wp_enqueue_style(
            'chatshop-admin',
            CHATSHOP_PLUGIN_URL . 'admin/css/chatshop-admin.css',
            array(),
            CHATSHOP_VERSION
        );

        // Admin JS
        wp_enqueue_script(
            'chatshop-admin',
            CHATSHOP_PLUGIN_URL . 'admin/js/chatshop-admin.js',
            array('jquery'),
            CHATSHOP_VERSION,
            true
        );

        // Localize script
        wp_localize_script('chatshop-admin', 'chatshopAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('chatshop_admin_nonce'),
            'strings' => array(
                'saving' => __('Saving...', 'chatshop'),
                'saved' => __('Saved!', 'chatshop'),
                'error' => __('Error occurred', 'chatshop'),
                'testing' => __('Testing...', 'chatshop'),
                'testSuccess' => __('Test successful!', 'chatshop'),
                'testFailed' => __('Test failed!', 'chatshop'),
                'confirmReset' => __('Are you sure you want to reset these settings?', 'chatshop'),
                'copied' => __('Copied!', 'chatshop')
            )
        ));

        // Enqueue WordPress media library if needed
        if (in_array($hook, array('toplevel_page_chatshop', 'chatshop_page_chatshop-general'), true)) {
            wp_enqueue_media();
        }
    }

    /**
     * Get current admin page
     *
     * @return string Current page slug
     * @since 1.0.0
     */
    public function get_current_page()
    {
        $page = $_GET['page'] ?? '';

        switch ($page) {
            case 'chatshop':
                return 'dashboard';
            case 'chatshop-general':
                return 'general';
            case 'chatshop-payments':
                return 'payments';
            case 'chatshop-whatsapp':
                return 'whatsapp';
            case 'chatshop-analytics':
                return 'analytics';
            case 'chatshop-premium':
                return 'premium';
            case 'chatshop-support':
                return 'support';
            default:
                return 'dashboard';
        }
    }

    /**
     * Add admin notices
     *
     * @since 1.0.0
     */
    public function add_admin_notices()
    {
        $screen = get_current_screen();

        if (!$screen || strpos($screen->id, 'chatshop') === false) {
            return;
        }

        // Check if Paystack is configured
        if (!$this->is_paystack_configured()) {
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p>';
            printf(
                __('ChatShop is not fully configured. Please %sconfigure your payment settings%s to start accepting payments.', 'chatshop'),
                '<a href="' . esc_url(admin_url('admin.php?page=chatshop-payments')) . '">',
                '</a>'
            );
            echo '</p>';
            echo '</div>';
        }

        // Check for plugin updates
        $this->check_plugin_updates();
    }

    /**
     * Check if Paystack is configured
     *
     * @return bool Configuration status
     * @since 1.0.0
     */
    private function is_paystack_configured()
    {
        $options = chatshop_get_option('paystack', '', array());

        if (empty($options['enabled'])) {
            return false;
        }

        $test_mode = $options['test_mode'] ?? true;

        if ($test_mode) {
            return !empty($options['test_public_key']) && !empty($options['test_secret_key']);
        }

        return !empty($options['live_public_key']) && !empty($options['live_secret_key']);
    }

    /**
     * Check for plugin updates
     *
     * @since 1.0.0
     */
    private function check_plugin_updates()
    {
        // This can be implemented to check for plugin updates
        // For now, it's a placeholder for future functionality
    }

    /**
     * Get menu pages
     *
     * @return array Menu pages
     * @since 1.0.0
     */
    public function get_menu_pages()
    {
        return $this->menu_pages;
    }
}
