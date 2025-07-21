<?php

/**
 * Admin functionality for ChatShop
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
 * Admin class
 *
 * @since 1.0.0
 */
class ChatShop_Admin
{
    /**
     * Current tab
     *
     * @var string
     * @since 1.0.0
     */
    private $current_tab;

    /**
     * Admin menu pages
     *
     * @var array
     * @since 1.0.0
     */
    private $admin_pages = array();

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        $this->current_tab = $this->get_current_tab();
        $this->init_admin_pages();
    }

    /**
     * Initialize admin pages
     *
     * @since 1.0.0
     */
    private function init_admin_pages()
    {
        $this->admin_pages = array(
            'dashboard' => array(
                'title' => __('Dashboard', 'chatshop'),
                'capability' => 'manage_options',
                'callback' => array($this, 'render_dashboard_page'),
                'icon' => 'dashicons-dashboard'
            ),
            'payments' => array(
                'title' => __('Payment Settings', 'chatshop'),
                'capability' => 'manage_options',
                'callback' => array($this, 'render_payments_page'),
                'icon' => 'dashicons-money-alt'
            ),
            'whatsapp' => array(
                'title' => __('WhatsApp Settings', 'chatshop'),
                'capability' => 'manage_options',
                'callback' => array($this, 'render_whatsapp_page'),
                'icon' => 'dashicons-phone'
            ),
            'analytics' => array(
                'title' => __('Analytics', 'chatshop'),
                'capability' => 'manage_options',
                'callback' => array($this, 'render_analytics_page'),
                'icon' => 'dashicons-chart-bar'
            ),
            'settings' => array(
                'title' => __('General Settings', 'chatshop'),
                'capability' => 'manage_options',
                'callback' => array($this, 'render_general_settings_page'),
                'icon' => 'dashicons-admin-settings'
            )
        );
    }

    /**
     * Enqueue admin styles
     *
     * @param string $hook_suffix Current admin page hook suffix
     * @since 1.0.0
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
            CHATSHOP_VERSION,
            'all'
        );

        // Additional styles for specific pages
        $current_page = $this->get_current_page();
        if ($current_page && file_exists(CHATSHOP_PLUGIN_DIR . "admin/css/chatshop-{$current_page}.css")) {
            wp_enqueue_style(
                "chatshop-admin-{$current_page}",
                CHATSHOP_PLUGIN_URL . "admin/css/chatshop-{$current_page}.css",
                array('chatshop-admin'),
                CHATSHOP_VERSION,
                'all'
            );
        }
    }

    /**
     * Enqueue admin scripts
     *
     * @param string $hook_suffix Current admin page hook suffix
     * @since 1.0.0
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
            array('jquery'),
            CHATSHOP_VERSION,
            true
        );

        // Localize script with admin data
        wp_localize_script('chatshop-admin', 'chatshop_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('chatshop_admin_nonce'),
            'strings' => array(
                'confirm_delete' => __('Are you sure you want to delete this item?', 'chatshop'),
                'save_success' => __('Settings saved successfully.', 'chatshop'),
                'save_error' => __('Error saving settings. Please try again.', 'chatshop'),
                'test_connection' => __('Testing connection...', 'chatshop'),
                'connection_success' => __('Connection successful!', 'chatshop'),
                'connection_failed' => __('Connection failed.', 'chatshop')
            )
        ));

        // Additional scripts for specific pages
        $current_page = $this->get_current_page();
        if ($current_page && file_exists(CHATSHOP_PLUGIN_DIR . "admin/js/chatshop-{$current_page}.js")) {
            wp_enqueue_script(
                "chatshop-admin-{$current_page}",
                CHATSHOP_PLUGIN_URL . "admin/js/chatshop-{$current_page}.js",
                array('chatshop-admin'),
                CHATSHOP_VERSION,
                true
            );
        }
    }

    /**
     * Add admin menu
     *
     * @since 1.0.0
     */
    public function add_admin_menu()
    {
        // Main menu page
        add_menu_page(
            __('ChatShop', 'chatshop'),
            __('ChatShop', 'chatshop'),
            'manage_options',
            'chatshop',
            array($this, 'render_dashboard_page'),
            'dashicons-whatsapp',
            30
        );

        // Sub-menu pages
        foreach ($this->admin_pages as $page_slug => $page_config) {
            add_submenu_page(
                'chatshop',
                $page_config['title'],
                $page_config['title'],
                $page_config['capability'],
                'chatshop-' . $page_slug,
                $page_config['callback']
            );
        }

        // Remove duplicate main page from submenu
        remove_submenu_page('chatshop', 'chatshop');
    }

    /**
     * Initialize settings
     *
     * @since 1.0.0
     */
    public function init_settings()
    {
        // Register settings for each gateway
        $gateways = chatshop_get_payment_gateways();
        foreach ($gateways as $gateway_id => $gateway) {
            register_setting(
                "chatshop_{$gateway_id}_options",
                "chatshop_{$gateway_id}_options",
                array(
                    'sanitize_callback' => array($this, 'sanitize_gateway_settings'),
                    'default' => array()
                )
            );
        }

        // Register general settings
        register_setting(
            'chatshop_general_options',
            'chatshop_general_options',
            array(
                'sanitize_callback' => array($this, 'sanitize_general_settings'),
                'default' => array()
            )
        );

        // Register WhatsApp settings
        register_setting(
            'chatshop_whatsapp_options',
            'chatshop_whatsapp_options',
            array(
                'sanitize_callback' => array($this, 'sanitize_whatsapp_settings'),
                'default' => array()
            )
        );
    }

    /**
     * Handle AJAX requests
     *
     * @since 1.0.0
     */
    public function handle_ajax_request()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'chatshop_admin_nonce')) {
            wp_die(__('Security check failed', 'chatshop'));
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'chatshop'));
        }

        $action = sanitize_text_field($_POST['action'] ?? '');

        switch ($action) {
            case 'chatshop_test_gateway':
                $this->ajax_test_gateway();
                break;

            case 'chatshop_save_settings':
                $this->ajax_save_settings();
                break;

            default:
                wp_send_json_error(__('Invalid action', 'chatshop'));
        }
    }

    /**
     * Render dashboard page
     *
     * @since 1.0.0
     */
    public function render_dashboard_page()
    {
        $this->render_admin_page('dashboard');
    }

    /**
     * Render payments page
     *
     * @since 1.0.0
     */
    public function render_payments_page()
    {
        $this->render_admin_page('payments');
    }

    /**
     * Render WhatsApp page
     *
     * @since 1.0.0
     */
    public function render_whatsapp_page()
    {
        $this->render_admin_page('whatsapp');
    }

    /**
     * Render analytics page
     *
     * @since 1.0.0
     */
    public function render_analytics_page()
    {
        $this->render_admin_page('analytics');
    }

    /**
     * Render general settings page
     *
     * @since 1.0.0
     */
    public function render_general_settings_page()
    {
        $this->render_admin_page('settings');
    }

    /**
     * Render admin page
     *
     * @param string $page Page slug
     * @since 1.0.0
     */
    private function render_admin_page($page)
    {
        // Set page title
        if (isset($this->admin_pages[$page])) {
            $page_title = $this->admin_pages[$page]['title'];
        } else {
            $page_title = __('ChatShop', 'chatshop');
        }

        // Include page template
        $template_file = CHATSHOP_PLUGIN_DIR . "admin/partials/{$page}.php";
        $settings_template_file = CHATSHOP_PLUGIN_DIR . "admin/partials/settings-{$page}.php";

        if (file_exists($settings_template_file)) {
            include_once $settings_template_file;
        } elseif (file_exists($template_file)) {
            include_once $template_file;
        } else {
            // Fallback content
            echo '<div class="wrap">';
            echo '<h1>' . esc_html($page_title) . '</h1>';
            echo '<div class="notice notice-warning"><p>';
            printf(
                /* translators: %s: page name */
                esc_html__('The %s page template is not available yet.', 'chatshop'),
                esc_html($page_title)
            );
            echo '</p></div>';
            echo '</div>';
        }
    }

    /**
     * Get current tab
     *
     * @return string Current tab
     * @since 1.0.0
     */
    private function get_current_tab()
    {
        return sanitize_text_field($_GET['tab'] ?? 'general');
    }

    /**
     * Get current page
     *
     * @return string|null Current page slug
     * @since 1.0.0
     */
    private function get_current_page()
    {
        $current_screen = get_current_screen();
        if (!$current_screen) {
            return null;
        }

        $page_id = $current_screen->id;

        // Extract page slug from screen ID
        if (strpos($page_id, 'chatshop-') === 0) {
            return str_replace('chatshop-', '', $page_id);
        }

        if ($page_id === 'toplevel_page_chatshop') {
            return 'dashboard';
        }

        return null;
    }

    /**
     * Check if current page is a ChatShop admin page
     *
     * @param string $hook_suffix Current admin page hook suffix
     * @return bool Whether current page is ChatShop admin page
     * @since 1.0.0
     */
    private function is_chatshop_admin_page($hook_suffix)
    {
        $chatshop_pages = array(
            'toplevel_page_chatshop',
            'chatshop_page_chatshop-dashboard',
            'chatshop_page_chatshop-payments',
            'chatshop_page_chatshop-whatsapp',
            'chatshop_page_chatshop-analytics',
            'chatshop_page_chatshop-settings'
        );

        return in_array($hook_suffix, $chatshop_pages, true);
    }

    /**
     * Sanitize gateway settings
     *
     * @param array $input Raw input data
     * @return array Sanitized settings
     * @since 1.0.0
     */
    public function sanitize_gateway_settings($input)
    {
        if (!is_array($input)) {
            return array();
        }

        $sanitized = array();

        foreach ($input as $key => $value) {
            $clean_key = sanitize_key($key);

            switch ($clean_key) {
                case 'enabled':
                case 'test_mode':
                    $sanitized[$clean_key] = ($value === 'yes') ? 'yes' : 'no';
                    break;

                case 'test_secret_key':
                case 'live_secret_key':
                    // Encrypt sensitive keys
                    $sanitized[$clean_key] = $this->encrypt_api_key(sanitize_text_field($value));
                    break;

                case 'test_public_key':
                case 'live_public_key':
                case 'client_id':
                case 'key_id':
                    $sanitized[$clean_key] = sanitize_text_field($value);
                    break;

                default:
                    $sanitized[$clean_key] = sanitize_text_field($value);
                    break;
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize general settings
     *
     * @param array $input Raw input data
     * @return array Sanitized settings
     * @since 1.0.0
     */
    public function sanitize_general_settings($input)
    {
        if (!is_array($input)) {
            return array();
        }

        $sanitized = array();

        foreach ($input as $key => $value) {
            $clean_key = sanitize_key($key);

            switch ($clean_key) {
                case 'plugin_enabled':
                case 'debug_mode':
                case 'enable_logging':
                    $sanitized[$clean_key] = ($value === 'yes') ? 'yes' : 'no';
                    break;

                case 'currency':
                    $sanitized[$clean_key] = strtoupper(sanitize_text_field($value));
                    break;

                case 'company_name':
                case 'support_email':
                    $sanitized[$clean_key] = sanitize_text_field($value);
                    break;

                case 'callback_url':
                case 'return_url':
                    $sanitized[$clean_key] = esc_url_raw($value);
                    break;

                default:
                    $sanitized[$clean_key] = sanitize_text_field($value);
                    break;
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize WhatsApp settings
     *
     * @param array $input Raw input data
     * @return array Sanitized settings
     * @since 1.0.0
     */
    public function sanitize_whatsapp_settings($input)
    {
        if (!is_array($input)) {
            return array();
        }

        $sanitized = array();

        foreach ($input as $key => $value) {
            $clean_key = sanitize_key($key);

            switch ($clean_key) {
                case 'enabled':
                case 'auto_send_receipts':
                case 'enable_notifications':
                    $sanitized[$clean_key] = ($value === 'yes') ? 'yes' : 'no';
                    break;

                case 'phone_number':
                    $sanitized[$clean_key] = sanitize_text_field($value);
                    break;

                case 'api_token':
                case 'webhook_secret':
                    $sanitized[$clean_key] = $this->encrypt_api_key(sanitize_text_field($value));
                    break;

                case 'business_name':
                case 'welcome_message':
                    $sanitized[$clean_key] = sanitize_textarea_field($value);
                    break;

                default:
                    $sanitized[$clean_key] = sanitize_text_field($value);
                    break;
            }
        }

        return $sanitized;
    }

    /**
     * Encrypt API key
     *
     * @param string $key API key to encrypt
     * @return string Encrypted key
     * @since 1.0.0
     */
    private function encrypt_api_key($key)
    {
        if (empty($key)) {
            return '';
        }

        $encryption_key = wp_salt('auth');

        try {
            return openssl_encrypt(
                $key,
                'AES-256-CBC',
                $encryption_key,
                0,
                substr($encryption_key, 0, 16)
            );
        } catch (Exception $e) {
            error_log('ChatShop: Failed to encrypt API key - ' . $e->getMessage());
            return $key; // Return unencrypted as fallback
        }
    }

    /**
     * AJAX test gateway connection
     *
     * @since 1.0.0
     */
    private function ajax_test_gateway()
    {
        $gateway_id = sanitize_key($_POST['gateway_id'] ?? '');

        if (empty($gateway_id)) {
            wp_send_json_error(__('Gateway ID is required', 'chatshop'));
        }

        $gateways = chatshop_get_payment_gateways();

        if (!isset($gateways[$gateway_id])) {
            wp_send_json_error(__('Gateway not found', 'chatshop'));
        }

        $gateway = $gateways[$gateway_id];

        if (!method_exists($gateway, 'test_connection')) {
            wp_send_json_error(__('Gateway does not support connection testing', 'chatshop'));
        }

        $result = $gateway->test_connection();

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX save settings
     *
     * @since 1.0.0
     */
    private function ajax_save_settings()
    {
        $settings_group = sanitize_text_field($_POST['settings_group'] ?? '');
        $settings_data = $_POST['settings_data'] ?? array();

        if (empty($settings_group)) {
            wp_send_json_error(__('Settings group is required', 'chatshop'));
        }

        // Sanitize based on settings group
        switch ($settings_group) {
            case 'general':
                $sanitized_data = $this->sanitize_general_settings($settings_data);
                break;

            case 'whatsapp':
                $sanitized_data = $this->sanitize_whatsapp_settings($settings_data);
                break;

            default:
                // Assume it's a gateway settings group
                $sanitized_data = $this->sanitize_gateway_settings($settings_data);
                break;
        }

        $option_name = "chatshop_{$settings_group}_options";
        $result = update_option($option_name, $sanitized_data);

        if ($result) {
            wp_send_json_success(array(
                'message' => __('Settings saved successfully', 'chatshop')
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Failed to save settings', 'chatshop')
            ));
        }
    }

    /**
     * Add admin notices
     *
     * @since 1.0.0
     */
    public function add_admin_notices()
    {
        // Check if plugin is properly configured
        if (!$this->is_plugin_configured()) {
            $this->show_configuration_notice();
        }

        // Check for missing dependencies
        $missing_deps = $this->check_dependencies();
        if (!empty($missing_deps)) {
            $this->show_dependency_notice($missing_deps);
        }

        // Show premium upgrade notice
        if (!chatshop_is_premium_feature_available('multiple_gateways')) {
            $this->show_premium_notice();
        }
    }

    /**
     * Check if plugin is properly configured
     *
     * @return bool Configuration status
     * @since 1.0.0
     */
    private function is_plugin_configured()
    {
        $gateways = chatshop_get_payment_gateways();

        if (empty($gateways)) {
            return false;
        }

        foreach ($gateways as $gateway) {
            if ($gateway->is_enabled() && method_exists($gateway, 'is_configured')) {
                if ($gateway->is_configured()) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check plugin dependencies
     *
     * @return array Missing dependencies
     * @since 1.0.0
     */
    private function check_dependencies()
    {
        $missing = array();

        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            $missing[] = sprintf(__('PHP 7.4 or higher (current: %s)', 'chatshop'), PHP_VERSION);
        }

        // Check WordPress version
        if (version_compare(get_bloginfo('version'), '5.0', '<')) {
            $missing[] = sprintf(__('WordPress 5.0 or higher (current: %s)', 'chatshop'), get_bloginfo('version'));
        }

        // Check WooCommerce if required
        if (!class_exists('WooCommerce')) {
            $missing[] = __('WooCommerce plugin', 'chatshop');
        }

        return $missing;
    }

    /**
     * Show configuration notice
     *
     * @since 1.0.0
     */
    private function show_configuration_notice()
    {
?>
        <div class="notice notice-warning is-dismissible">
            <p>
                <strong><?php esc_html_e('ChatShop Configuration Required', 'chatshop'); ?></strong>
            </p>
            <p>
                <?php esc_html_e('Please configure at least one payment gateway to start accepting payments.', 'chatshop'); ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=chatshop-payments')); ?>" class="button button-primary">
                    <?php esc_html_e('Configure Now', 'chatshop'); ?>
                </a>
            </p>
        </div>
    <?php
    }

    /**
     * Show dependency notice
     *
     * @param array $missing_deps Missing dependencies
     * @since 1.0.0
     */
    private function show_dependency_notice($missing_deps)
    {
    ?>
        <div class="notice notice-error">
            <p>
                <strong><?php esc_html_e('ChatShop Requirements Not Met', 'chatshop'); ?></strong>
            </p>
            <p><?php esc_html_e('The following requirements are missing:', 'chatshop'); ?></p>
            <ul>
                <?php foreach ($missing_deps as $dep) : ?>
                    <li><?php echo esc_html($dep); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php
    }

    /**
     * Show premium notice
     *
     * @since 1.0.0
     */
    private function show_premium_notice()
    {
        // Only show on ChatShop pages
        $current_screen = get_current_screen();
        if (!$current_screen || strpos($current_screen->id, 'chatshop') === false) {
            return;
        }

        // Don't show if user has dismissed it recently
        $dismissed = get_user_meta(get_current_user_id(), 'chatshop_premium_notice_dismissed', true);
        if ($dismissed && (time() - $dismissed) < WEEK_IN_SECONDS) {
            return;
        }

    ?>
        <div class="notice notice-info is-dismissible chatshop-premium-notice">
            <p>
                <strong><?php esc_html_e('Unlock ChatShop Premium Features', 'chatshop'); ?></strong>
            </p>
            <p>
                <?php esc_html_e('Get access to multiple payment gateways, advanced analytics, and premium support.', 'chatshop'); ?>
                <a href="#" class="button button-primary"><?php esc_html_e('Upgrade Now', 'chatshop'); ?></a>
                <a href="#" class="button button-secondary chatshop-dismiss-notice"><?php esc_html_e('Maybe Later', 'chatshop'); ?></a>
            </p>
        </div>

        <script type="text/javascript">
            jQuery(document).ready(function($) {
                $('.chatshop-dismiss-notice').on('click', function(e) {
                    e.preventDefault();

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'chatshop_dismiss_premium_notice',
                            nonce: '<?php echo wp_create_nonce('chatshop_admin_nonce'); ?>'
                        }
                    });

                    $('.chatshop-premium-notice').fadeOut();
                });
            });
        </script>
<?php
    }

    /**
     * Handle dismiss premium notice AJAX
     *
     * @since 1.0.0
     */
    public function ajax_dismiss_premium_notice()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'chatshop_admin_nonce')) {
            wp_die(__('Security check failed', 'chatshop'));
        }

        update_user_meta(get_current_user_id(), 'chatshop_premium_notice_dismissed', time());
        wp_send_json_success();
    }
}
