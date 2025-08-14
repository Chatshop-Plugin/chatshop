<?php

/**
 * ChatShop Admin Class
 *
 * @package ChatShop
 * @since   1.0.0
 */

namespace ChatShop;

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Prevent class redeclaration
if (class_exists('ChatShop\\ChatShop_Admin')) {
    return;
}

/**
 * Main admin class
 */
class ChatShop_Admin
{
    /**
     * Admin menu handler
     *
     * @var ChatShop_Admin_Menu
     */
    private $menu_handler;

    /**
     * Settings handler
     *
     * @var ChatShop_Settings
     */
    private $settings_handler;

    /**
     * Initialize admin functionality
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        $this->load_dependencies();
        $this->init_handlers();
        $this->init_hooks();
    }

    /**
     * Load admin dependencies
     *
     * @since 1.0.0
     */
    private function load_dependencies()
    {
        $menu_file = CHATSHOP_ADMIN_DIR . 'class-chatshop-admin-menu.php';
        $settings_file = CHATSHOP_ADMIN_DIR . 'class-chatshop-settings.php';

        if (file_exists($menu_file)) {
            require_once $menu_file;
        }

        if (file_exists($settings_file)) {
            require_once $settings_file;
        }
    }

    /**
     * Initialize handlers
     *
     * @since 1.0.0
     */
    private function init_handlers()
    {
        if (class_exists('ChatShop\\ChatShop_Admin_Menu')) {
            $this->menu_handler = new ChatShop_Admin_Menu();
        }

        if (class_exists('ChatShop\\ChatShop_Settings')) {
            $this->settings_handler = new ChatShop_Settings();
        }
    }

    /**
     * Initialize admin hooks
     *
     * @since 1.0.0
     */
    private function init_hooks()
    {
        add_action('admin_init', array($this, 'init_settings'));
        add_action('admin_notices', array($this, 'display_admin_notices'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_styles'));
        add_action('wp_ajax_chatshop_test_gateway', array($this, 'ajax_test_gateway'));
        add_action('wp_ajax_chatshop_save_settings', array($this, 'ajax_save_settings'));
        add_action('wp_ajax_chatshop_reset_settings', array($this, 'ajax_reset_settings'));
        add_action('admin_post_chatshop_export_settings', array($this, 'export_settings'));
        add_action('admin_post_chatshop_import_settings', array($this, 'import_settings'));
    }

    /**
     * Initialize settings
     *
     * @since 1.0.0
     */
    public function init_settings()
    {
        // Settings are initialized in the ChatShop_Settings class
        // This method can be used for additional initialization if needed
    }

    /**
     * Enqueue admin styles
     *
     * @param string $hook Current admin page hook
     * @since 1.0.0
     */
    public function enqueue_styles($hook)
    {
        // Only load on our admin pages
        if (strpos($hook, 'chatshop') === false) {
            return;
        }

        $css_file = CHATSHOP_PLUGIN_URL . 'admin/css/chatshop-admin.css';
        if (file_exists(CHATSHOP_PLUGIN_DIR . 'admin/css/chatshop-admin.css')) {
            wp_enqueue_style(
                'chatshop-admin',
                $css_file,
                array(),
                CHATSHOP_VERSION,
                'all'
            );
        }
    }

    /**
     * Enqueue admin scripts
     *
     * @param string $hook Current admin page hook
     * @since 1.0.0
     */
    public function enqueue_scripts($hook)
    {
        // Only load on our admin pages
        if (strpos($hook, 'chatshop') === false) {
            return;
        }

        $js_file = CHATSHOP_PLUGIN_URL . 'admin/js/chatshop-admin.js';
        if (file_exists(CHATSHOP_PLUGIN_DIR . 'admin/js/chatshop-admin.js')) {
            wp_enqueue_script(
                'chatshop-admin',
                $js_file,
                array('jquery', 'wp-util'),
                CHATSHOP_VERSION,
                true
            );

            // Localize script with data
            wp_localize_script('chatshop-admin', 'chatshopAdmin', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('chatshop_admin_nonce'),
                'pluginUrl' => CHATSHOP_PLUGIN_URL,
                'strings' => array(
                    'saving' => __('Saving...', 'chatshop'),
                    'saved' => __('Settings saved successfully!', 'chatshop'),
                    'error' => __('An error occurred. Please try again.', 'chatshop'),
                    'testing' => __('Testing connection...', 'chatshop'),
                    'testSuccess' => __('Connection test successful!', 'chatshop'),
                    'testFailed' => __('Connection test failed. Please check your settings.', 'chatshop'),
                    'confirmReset' => __('Are you sure you want to reset these settings? This action cannot be undone.', 'chatshop'),
                    'resetting' => __('Resetting...', 'chatshop'),
                    'resetSuccess' => __('Settings reset successfully!', 'chatshop'),
                    'copied' => __('Copied to clipboard!', 'chatshop'),
                    'copyFailed' => __('Failed to copy. Please copy manually.', 'chatshop')
                )
            ));
        }
    }

    /**
     * Add admin menu
     *
     * @since 1.0.0
     */
    public function add_admin_menu()
    {
        // Menu is handled by ChatShop_Admin_Menu class
    }

    /**
     * Display admin notices
     *
     * @since 1.0.0
     */
    public function display_admin_notices()
    {
        $screen = get_current_screen();

        if (!$screen || strpos($screen->id, 'chatshop') === false) {
            return;
        }

        // Success notices
        if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p>' . esc_html__('Settings saved successfully!', 'chatshop') . '</p>';
            echo '</div>';
        }

        // Configuration warnings
        $this->display_configuration_notices();
    }

    /**
     * Display configuration notices
     *
     * @since 1.0.0
     */
    private function display_configuration_notices()
    {
        $paystack_options = $this->get_option('paystack', array());

        // Check if Paystack is enabled but not configured
        if (!empty($paystack_options['enabled']) && !$this->is_paystack_configured()) {
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p>';
            printf(
                __('Paystack is enabled but not properly configured. Please %sconfigure your API keys%s to start processing payments.', 'chatshop'),
                '<a href="' . esc_url(admin_url('admin.php?page=chatshop-payments')) . '">',
                '</a>'
            );
            echo '</p>';
            echo '</div>';
        }

        // Check SSL for live mode
        if (!empty($paystack_options['enabled']) && empty($paystack_options['test_mode']) && !is_ssl()) {
            echo '<div class="notice notice-error is-dismissible">';
            echo '<p>';
            echo esc_html__('⚠️ SSL certificate is required for live payment processing. Please install an SSL certificate.', 'chatshop');
            echo '</p>';
            echo '</div>';
        }
    }

    /**
     * Check if Paystack is properly configured
     *
     * @return bool Configuration status
     * @since 1.0.0
     */
    private function is_paystack_configured()
    {
        if (!$this->settings_handler || !method_exists($this->settings_handler, 'validate_paystack_config')) {
            return false;
        }

        $validation = $this->settings_handler->validate_paystack_config();
        return isset($validation['valid']) && $validation['valid'];
    }

    /**
     * Handle AJAX request for testing gateway connection
     *
     * @since 1.0.0
     */
    public function ajax_test_gateway()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'chatshop_admin_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'chatshop')));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'chatshop')));
        }

        $gateway_id = sanitize_key($_POST['gateway_id'] ?? '');

        if ($gateway_id !== 'paystack') {
            wp_send_json_error(array('message' => __('Unsupported gateway.', 'chatshop')));
        }

        // Test gateway connection
        $result = $this->test_paystack_connection();

        if ($result['success']) {
            wp_send_json_success(array('message' => $result['message']));
        } else {
            wp_send_json_error(array('message' => $result['message']));
        }
    }

    /**
     * Test Paystack connection
     *
     * @return array Test result
     * @since 1.0.0
     */
    private function test_paystack_connection()
    {
        $paystack_options = $this->get_option('paystack', array());
        $test_mode = $paystack_options['test_mode'] ?? true;

        // Validate configuration first
        if (!$this->settings_handler || !method_exists($this->settings_handler, 'validate_paystack_config')) {
            return array(
                'success' => false,
                'message' => __('Settings handler not available.', 'chatshop')
            );
        }

        $validation = $this->settings_handler->validate_paystack_config($test_mode);

        if (!$validation['valid']) {
            return array(
                'success' => false,
                'message' => $validation['message']
            );
        }

        // Get API keys
        $keys = $this->settings_handler->get_paystack_keys($test_mode);

        if (empty($keys['secret_key'])) {
            return array(
                'success' => false,
                'message' => __('Secret key not found.', 'chatshop')
            );
        }

        // Test API call to Paystack
        $api_url = 'https://api.paystack.co/transaction/verify/invalid_reference';

        $response = wp_remote_get($api_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $keys['secret_key'],
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => sprintf(__('Connection failed: %s', 'chatshop'), $response->get_error_message())
            );
        }

        $response_code = wp_remote_retrieve_response_code($response);

        // We expect a 404 for invalid reference, which means API is reachable
        if ($response_code === 404) {
            return array(
                'success' => true,
                'message' => sprintf(__('Connection successful! %s mode is working correctly.', 'chatshop'), $test_mode ? 'Test' : 'Live')
            );
        } elseif ($response_code === 401) {
            return array(
                'success' => false,
                'message' => __('Authentication failed. Please check your API keys.', 'chatshop')
            );
        } else {
            return array(
                'success' => false,
                'message' => sprintf(__('Unexpected response code: %d', 'chatshop'), $response_code)
            );
        }
    }

    /**
     * Handle AJAX request for saving settings
     *
     * @since 1.0.0
     */
    public function ajax_save_settings()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'chatshop_admin_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'chatshop')));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'chatshop')));
        }

        $settings_group = sanitize_key($_POST['group'] ?? '');
        $settings_data = $_POST['data'] ?? array();

        if (empty($settings_group)) {
            wp_send_json_error(array('message' => __('Invalid settings group.', 'chatshop')));
        }

        // Save settings based on group
        $option_name = "chatshop_{$settings_group}";

        // Sanitize based on group
        if ($this->settings_handler) {
            switch ($settings_group) {
                case 'paystack':
                    if (method_exists($this->settings_handler, 'sanitize_paystack_settings')) {
                        $settings_data = $this->settings_handler->sanitize_paystack_settings($settings_data);
                    }
                    break;
                case 'general':
                    if (method_exists($this->settings_handler, 'sanitize_general_settings')) {
                        $settings_data = $this->settings_handler->sanitize_general_settings($settings_data);
                    }
                    break;
                case 'whatsapp':
                    if (method_exists($this->settings_handler, 'sanitize_whatsapp_settings')) {
                        $settings_data = $this->settings_handler->sanitize_whatsapp_settings($settings_data);
                    }
                    break;
                case 'analytics':
                    if (method_exists($this->settings_handler, 'sanitize_analytics_settings')) {
                        $settings_data = $this->settings_handler->sanitize_analytics_settings($settings_data);
                    }
                    break;
                default:
                    wp_send_json_error(array('message' => __('Invalid settings group.', 'chatshop')));
            }
        }

        // Update option
        $result = update_option($option_name, $settings_data);

        if ($result) {
            if (function_exists('ChatShop\\chatshop_log')) {
                chatshop_log("Settings saved for group: {$settings_group}", 'info');
            }
            wp_send_json_success(array('message' => __('Settings saved successfully!', 'chatshop')));
        } else {
            wp_send_json_error(array('message' => __('Failed to save settings.', 'chatshop')));
        }
    }

    /**
     * Handle AJAX request for resetting settings
     *
     * @since 1.0.0
     */
    public function ajax_reset_settings()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'chatshop_admin_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'chatshop')));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'chatshop')));
        }

        $settings_group = sanitize_key($_POST['group'] ?? '');

        if (empty($settings_group)) {
            wp_send_json_error(array('message' => __('Invalid settings group.', 'chatshop')));
        }

        // Reset settings
        if ($this->settings_handler && method_exists($this->settings_handler, 'reset_settings')) {
            $result = $this->settings_handler->reset_settings($settings_group);
        } else {
            // Fallback reset
            $result = delete_option("chatshop_{$settings_group}");
        }

        if ($result) {
            wp_send_json_success(array('message' => __('Settings reset successfully!', 'chatshop')));
        } else {
            wp_send_json_error(array('message' => __('Failed to reset settings.', 'chatshop')));
        }
    }

    /**
     * Export settings
     *
     * @since 1.0.0
     */
    public function export_settings()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'chatshop_export_settings')) {
            wp_die(__('Security check failed.', 'chatshop'));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'chatshop'));
        }

        if ($this->settings_handler && method_exists($this->settings_handler, 'export_settings')) {
            $settings = $this->settings_handler->export_settings();
        } else {
            // Fallback export
            $settings = array(
                'general' => get_option('chatshop_general', array()),
                'paystack' => get_option('chatshop_paystack', array()),
                'whatsapp' => get_option('chatshop_whatsapp', array()),
                'analytics' => get_option('chatshop_analytics', array())
            );
        }

        // Set headers for download
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="chatshop-settings-' . date('Y-m-d') . '.json"');
        header('Content-Length: ' . strlen(json_encode($settings)));

        echo json_encode($settings, JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Import settings
     *
     * @since 1.0.0
     */
    public function import_settings()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'chatshop_import_settings')) {
            wp_die(__('Security check failed.', 'chatshop'));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'chatshop'));
        }

        // Check if file was uploaded
        if (!isset($_FILES['settings_file']) || $_FILES['settings_file']['error'] !== UPLOAD_ERR_OK) {
            wp_redirect(add_query_arg(array(
                'page' => 'chatshop-general',
                'import' => 'error',
                'message' => urlencode(__('File upload failed.', 'chatshop'))
            ), admin_url('admin.php')));
            exit;
        }

        // Read and decode file
        $file_content = file_get_contents($_FILES['settings_file']['tmp_name']);
        $settings = json_decode($file_content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_redirect(add_query_arg(array(
                'page' => 'chatshop-general',
                'import' => 'error',
                'message' => urlencode(__('Invalid JSON file.', 'chatshop'))
            ), admin_url('admin.php')));
            exit;
        }

        // Import settings
        if ($this->settings_handler && method_exists($this->settings_handler, 'import_settings')) {
            $result = $this->settings_handler->import_settings($settings);
        } else {
            // Fallback import
            $result = true;
            foreach ($settings as $key => $value) {
                if (!update_option("chatshop_{$key}", $value)) {
                    $result = false;
                }
            }
        }

        if ($result) {
            wp_redirect(add_query_arg(array(
                'page' => 'chatshop-general',
                'import' => 'success'
            ), admin_url('admin.php')));
        } else {
            wp_redirect(add_query_arg(array(
                'page' => 'chatshop-general',
                'import' => 'error',
                'message' => urlencode(__('Failed to import settings.', 'chatshop'))
            ), admin_url('admin.php')));
        }
        exit;
    }

    /**
     * Handle general AJAX requests
     *
     * @since 1.0.0
     */
    public function handle_ajax_request()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'chatshop_admin_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'chatshop')));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'chatshop')));
        }

        $action = sanitize_key($_POST['chatshop_action'] ?? '');

        switch ($action) {
            case 'get_stats':
                $this->ajax_get_dashboard_stats();
                break;

            case 'refresh_webhook_url':
                $this->ajax_refresh_webhook_url();
                break;

            default:
                wp_send_json_error(array('message' => __('Unknown action.', 'chatshop')));
        }
    }

    /**
     * Get dashboard statistics
     *
     * @since 1.0.0
     */
    private function ajax_get_dashboard_stats()
    {
        // This would typically fetch real statistics
        // For now, return placeholder data
        $stats = array(
            'total_transactions' => 0,
            'total_revenue' => 0,
            'whatsapp_messages' => 0,
            'conversion_rate' => 0
        );

        wp_send_json_success($stats);
    }

    /**
     * Refresh webhook URL
     *
     * @since 1.0.0
     */
    private function ajax_refresh_webhook_url()
    {
        $webhook_url = home_url('/wp-admin/admin-ajax.php?action=chatshop_webhook&gateway=paystack');
        wp_send_json_success(array('webhook_url' => $webhook_url));
    }

    /**
     * Get ChatShop option safely
     *
     * @param string $option_name Option name
     * @param mixed $default Default value
     * @return mixed Option value
     * @since 1.0.0
     */
    private function get_option($option_name, $default = array())
    {
        if (function_exists('ChatShop\\chatshop_get_option')) {
            return chatshop_get_option($option_name, $default);
        }
        return get_option("chatshop_{$option_name}", $default);
    }

    /**
     * Get settings handler
     *
     * @return ChatShop_Settings|null
     * @since 1.0.0
     */
    public function get_settings_handler()
    {
        return $this->settings_handler;
    }

    /**
     * Get menu handler
     *
     * @return ChatShop_Admin_Menu|null
     * @since 1.0.0
     */
    public function get_menu_handler()
    {
        return $this->menu_handler;
    }
}
