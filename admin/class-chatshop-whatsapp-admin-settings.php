<?php

/**
 * ChatShop WhatsApp Admin Settings
 *
 * Handles WhatsApp admin interface and settings management
 *
 * @package ChatShop
 * @subpackage Admin
 * @since 1.0.0
 */

namespace ChatShop\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WhatsApp Admin Settings class
 *
 * Manages WhatsApp configuration, interface, and settings
 */
class ChatShop_WhatsApp_Admin_Settings
{

    /**
     * WhatsApp API instance
     *
     * @var \ChatShop\WhatsApp\ChatShop_WhatsApp_API
     */
    private $whatsapp_api;

    /**
     * Message sender instance
     *
     * @var \ChatShop\WhatsApp\ChatShop_Message_Sender
     */
    private $message_sender;

    /**
     * Campaign manager instance
     *
     * @var \ChatShop\Marketing\ChatShop_Campaign_Manager
     */
    private $campaign_manager;

    /**
     * Message templates instance
     *
     * @var \ChatShop\Templates\ChatShop_Message_Templates
     */
    private $message_templates;

    /**
     * Settings option name
     *
     * @var string
     */
    private $option_name = 'chatshop_whatsapp_settings';

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->init_components();
        $this->init_hooks();
    }

    /**
     * Initialize component instances
     */
    private function init_components()
    {
        $this->whatsapp_api = new \ChatShop\WhatsApp\ChatShop_WhatsApp_API();
        $this->message_sender = new \ChatShop\WhatsApp\ChatShop_Message_Sender();
        $this->campaign_manager = new \ChatShop\Marketing\ChatShop_Campaign_Manager();
        $this->message_templates = new \ChatShop\Templates\ChatShop_Message_Templates();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks()
    {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);

        // AJAX hooks
        add_action('wp_ajax_chatshop_test_whatsapp_connection', [$this, 'ajax_test_connection']);
        add_action('wp_ajax_chatshop_save_whatsapp_settings', [$this, 'ajax_save_settings']);
        add_action('wp_ajax_chatshop_send_test_message', [$this, 'ajax_send_test_message']);
        add_action('wp_ajax_chatshop_sync_templates', [$this, 'ajax_sync_templates']);
        add_action('wp_ajax_chatshop_get_contact_stats', [$this, 'ajax_get_contact_stats']);
        add_action('wp_ajax_chatshop_export_contacts', [$this, 'ajax_export_contacts']);
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu()
    {
        // Main WhatsApp settings page
        add_submenu_page(
            'chatshop',
            __('WhatsApp Settings', 'chatshop'),
            __('WhatsApp', 'chatshop'),
            'manage_options',
            'chatshop-whatsapp',
            [$this, 'render_main_page']
        );

        // Configuration submenu
        add_submenu_page(
            'chatshop-whatsapp',
            __('WhatsApp Configuration', 'chatshop'),
            __('Configuration', 'chatshop'),
            'manage_options',
            'chatshop-whatsapp-config',
            [$this, 'render_configuration_page']
        );

        // Contacts management
        add_submenu_page(
            'chatshop-whatsapp',
            __('WhatsApp Contacts', 'chatshop'),
            __('Contacts', 'chatshop'),
            'manage_options',
            'chatshop-whatsapp-contacts',
            [$this, 'render_contacts_page']
        );

        // Campaigns management
        add_submenu_page(
            'chatshop-whatsapp',
            __('WhatsApp Campaigns', 'chatshop'),
            __('Campaigns', 'chatshop'),
            'manage_options',
            'chatshop-whatsapp-campaigns',
            [$this, 'render_campaigns_page']
        );

        // Message templates
        add_submenu_page(
            'chatshop-whatsapp',
            __('Message Templates', 'chatshop'),
            __('Templates', 'chatshop'),
            'manage_options',
            'chatshop-whatsapp-templates',
            [$this, 'render_templates_page']
        );

        // Analytics
        add_submenu_page(
            'chatshop-whatsapp',
            __('WhatsApp Analytics', 'chatshop'),
            __('Analytics', 'chatshop'),
            'manage_options',
            'chatshop-whatsapp-analytics',
            [$this, 'render_analytics_page']
        );
    }

    /**
     * Register settings
     */
    public function register_settings()
    {
        register_setting(
            'chatshop_whatsapp_settings_group',
            $this->option_name,
            [$this, 'sanitize_settings']
        );

        // General settings section
        add_settings_section(
            'chatshop_whatsapp_general',
            __('General Settings', 'chatshop'),
            [$this, 'render_general_section_info'],
            'chatshop_whatsapp_settings'
        );

        // API settings section
        add_settings_section(
            'chatshop_whatsapp_api',
            __('WhatsApp Business API', 'chatshop'),
            [$this, 'render_api_section_info'],
            'chatshop_whatsapp_settings'
        );

        // Automation settings section
        add_settings_section(
            'chatshop_whatsapp_automation',
            __('Automation Settings', 'chatshop'),
            [$this, 'render_automation_section_info'],
            'chatshop_whatsapp_settings'
        );

        $this->add_settings_fields();
    }

    /**
     * Add settings fields
     */
    private function add_settings_fields()
    {
        // General settings fields
        add_settings_field(
            'whatsapp_enabled',
            __('Enable WhatsApp', 'chatshop'),
            [$this, 'render_checkbox_field'],
            'chatshop_whatsapp_settings',
            'chatshop_whatsapp_general',
            ['name' => 'enabled', 'description' => __('Enable WhatsApp integration for your store', 'chatshop')]
        );

        add_settings_field(
            'default_country_code',
            __('Default Country Code', 'chatshop'),
            [$this, 'render_text_field'],
            'chatshop_whatsapp_settings',
            'chatshop_whatsapp_general',
            ['name' => 'default_country_code', 'placeholder' => '234', 'description' => __('Default country code for phone numbers (without +)', 'chatshop')]
        );

        // API settings fields
        add_settings_field(
            'business_account_id',
            __('Business Account ID', 'chatshop'),
            [$this, 'render_text_field'],
            'chatshop_whatsapp_settings',
            'chatshop_whatsapp_api',
            ['name' => 'business_account_id', 'required' => true, 'description' => __('Your WhatsApp Business Account ID', 'chatshop')]
        );

        add_settings_field(
            'phone_number_id',
            __('Phone Number ID', 'chatshop'),
            [$this, 'render_text_field'],
            'chatshop_whatsapp_settings',
            'chatshop_whatsapp_api',
            ['name' => 'phone_number_id', 'required' => true, 'description' => __('Your WhatsApp Phone Number ID', 'chatshop')]
        );

        add_settings_field(
            'access_token',
            __('Access Token', 'chatshop'),
            [$this, 'render_password_field'],
            'chatshop_whatsapp_settings',
            'chatshop_whatsapp_api',
            ['name' => 'access_token', 'required' => true, 'description' => __('Your WhatsApp Business API access token', 'chatshop')]
        );

        add_settings_field(
            'webhook_verify_token',
            __('Webhook Verify Token', 'chatshop'),
            [$this, 'render_text_field'],
            'chatshop_whatsapp_settings',
            'chatshop_whatsapp_api',
            ['name' => 'webhook_verify_token', 'description' => __('Webhook verification token for incoming messages', 'chatshop')]
        );

        add_settings_field(
            'app_secret',
            __('App Secret', 'chatshop'),
            [$this, 'render_password_field'],
            'chatshop_whatsapp_settings',
            'chatshop_whatsapp_api',
            ['name' => 'app_secret', 'description' => __('App secret for webhook signature verification', 'chatshop')]
        );

        // Automation settings fields
        add_settings_field(
            'welcome_message_enabled',
            __('Welcome Message', 'chatshop'),
            [$this, 'render_checkbox_field'],
            'chatshop_whatsapp_settings',
            'chatshop_whatsapp_automation',
            ['name' => 'welcome_message_enabled', 'description' => __('Send welcome message to new contacts', 'chatshop')]
        );

        add_settings_field(
            'welcome_message_text',
            __('Welcome Message Text', 'chatshop'),
            [$this, 'render_textarea_field'],
            'chatshop_whatsapp_settings',
            'chatshop_whatsapp_automation',
            ['name' => 'welcome_message_text', 'description' => __('Message sent to new contacts. Use {name} for personalization.', 'chatshop')]
        );

        add_settings_field(
            'order_notifications_enabled',
            __('Order Notifications', 'chatshop'),
            [$this, 'render_checkbox_field'],
            'chatshop_whatsapp_settings',
            'chatshop_whatsapp_automation',
            ['name' => 'order_notifications_enabled', 'description' => __('Send automated order status notifications', 'chatshop')]
        );

        add_settings_field(
            'cart_abandonment_enabled',
            __('Cart Abandonment Recovery', 'chatshop'),
            [$this, 'render_checkbox_field'],
            'chatshop_whatsapp_settings',
            'chatshop_whatsapp_automation',
            ['name' => 'cart_abandonment_enabled', 'description' => __('Send messages for abandoned carts', 'chatshop')]
        );

        add_settings_field(
            'cart_abandonment_delay',
            __('Cart Abandonment Delay (hours)', 'chatshop'),
            [$this, 'render_number_field'],
            'chatshop_whatsapp_settings',
            'chatshop_whatsapp_automation',
            ['name' => 'cart_abandonment_delay', 'min' => 1, 'max' => 48, 'default' => 24, 'description' => __('Hours to wait before sending abandonment message', 'chatshop')]
        );
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook)
    {
        if (strpos($hook, 'chatshop-whatsapp') === false) {
            return;
        }

        wp_enqueue_script(
            'chatshop-whatsapp-admin',
            CHATSHOP_PLUGIN_URL . 'admin/js/whatsapp-admin.js',
            ['jquery', 'wp-util'],
            CHATSHOP_VERSION,
            true
        );

        wp_enqueue_style(
            'chatshop-whatsapp-admin',
            CHATSHOP_PLUGIN_URL . 'admin/css/whatsapp-admin.css',
            [],
            CHATSHOP_VERSION
        );

        wp_localize_script('chatshop-whatsapp-admin', 'chatshopWhatsAppAdmin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('chatshop_whatsapp_admin'),
            'strings' => [
                'testing_connection' => __('Testing connection...', 'chatshop'),
                'connection_successful' => __('Connection successful!', 'chatshop'),
                'connection_failed' => __('Connection failed!', 'chatshop'),
                'sending_test_message' => __('Sending test message...', 'chatshop'),
                'test_message_sent' => __('Test message sent successfully!', 'chatshop'),
                'test_message_failed' => __('Test message failed to send!', 'chatshop'),
                'settings_saved' => __('Settings saved successfully!', 'chatshop'),
                'settings_save_failed' => __('Failed to save settings!', 'chatshop'),
                'confirm_delete' => __('Are you sure you want to delete this item?', 'chatshop'),
                'syncing_templates' => __('Syncing templates...', 'chatshop'),
                'templates_synced' => __('Templates synced successfully!', 'chatshop'),
                'sync_failed' => __('Template sync failed!', 'chatshop')
            ]
        ]);
    }

    /**
     * Render main WhatsApp page
     */
    public function render_main_page()
    {
        $settings = $this->get_settings();
        $is_configured = $this->is_whatsapp_configured();

        include CHATSHOP_PLUGIN_PATH . 'admin/partials/whatsapp-main.php';
    }

    /**
     * Render configuration page
     */
    public function render_configuration_page()
    {
        $settings = $this->get_settings();

        include CHATSHOP_PLUGIN_PATH . 'admin/partials/whatsapp-configuration.php';
    }

    /**
     * Render contacts page
     */
    public function render_contacts_page()
    {
        global $wpdb;

        $contacts_table = $wpdb->prefix . 'chatshop_contacts';
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 20;
        $offset = ($page - 1) * $per_page;

        // Get contacts with pagination
        $contacts = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$contacts_table} ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ), ARRAY_A);

        $total_contacts = $wpdb->get_var("SELECT COUNT(*) FROM {$contacts_table}");
        $total_pages = ceil($total_contacts / $per_page);

        include CHATSHOP_PLUGIN_PATH . 'admin/partials/whatsapp-contacts.php';
    }

    /**
     * Render campaigns page
     */
    public function render_campaigns_page()
    {
        $campaigns = $this->campaign_manager->get_campaigns();

        include CHATSHOP_PLUGIN_PATH . 'admin/partials/whatsapp-campaigns.php';
    }

    /**
     * Render templates page
     */
    public function render_templates_page()
    {
        $templates = $this->message_templates->get_templates();
        $predefined_templates = $this->message_templates->get_predefined_templates();

        include CHATSHOP_PLUGIN_PATH . 'admin/partials/whatsapp-templates.php';
    }

    /**
     * Render analytics page
     */
    public function render_analytics_page()
    {
        global $wpdb;

        // Get analytics data
        $analytics_data = $this->get_analytics_data();

        include CHATSHOP_PLUGIN_PATH . 'admin/partials/whatsapp-analytics.php';
    }

    /**
     * Render field types
     */
    public function render_checkbox_field($args)
    {
        $settings = $this->get_settings();
        $value = isset($settings[$args['name']]) ? $settings[$args['name']] : false;

        echo '<input type="checkbox" id="' . esc_attr($args['name']) . '" name="' . $this->option_name . '[' . esc_attr($args['name']) . ']" value="1" ' . checked(1, $value, false) . '/>';

        if (!empty($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }

    public function render_text_field($args)
    {
        $settings = $this->get_settings();
        $value = isset($settings[$args['name']]) ? $settings[$args['name']] : '';
        $required = isset($args['required']) && $args['required'] ? 'required' : '';
        $placeholder = isset($args['placeholder']) ? $args['placeholder'] : '';

        echo '<input type="text" id="' . esc_attr($args['name']) . '" name="' . $this->option_name . '[' . esc_attr($args['name']) . ']" value="' . esc_attr($value) . '" placeholder="' . esc_attr($placeholder) . '" ' . $required . ' class="regular-text"/>';

        if (!empty($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }

    public function render_password_field($args)
    {
        $settings = $this->get_settings();
        $value = isset($settings[$args['name']]) ? $settings[$args['name']] : '';
        $required = isset($args['required']) && $args['required'] ? 'required' : '';

        echo '<input type="password" id="' . esc_attr($args['name']) . '" name="' . $this->option_name . '[' . esc_attr($args['name']) . ']" value="' . esc_attr($value) . '" ' . $required . ' class="regular-text"/>';

        if (!empty($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }

    public function render_textarea_field($args)
    {
        $settings = $this->get_settings();
        $value = isset($settings[$args['name']]) ? $settings[$args['name']] : '';
        $default = isset($args['default']) ? $args['default'] : '';
        $display_value = !empty($value) ? $value : $default;

        echo '<textarea id="' . esc_attr($args['name']) . '" name="' . $this->option_name . '[' . esc_attr($args['name']) . ']" rows="5" class="large-text">' . esc_textarea($display_value) . '</textarea>';

        if (!empty($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }

    public function render_number_field($args)
    {
        $settings = $this->get_settings();
        $value = isset($settings[$args['name']]) ? $settings[$args['name']] : (isset($args['default']) ? $args['default'] : '');
        $min = isset($args['min']) ? $args['min'] : '';
        $max = isset($args['max']) ? $args['max'] : '';

        echo '<input type="number" id="' . esc_attr($args['name']) . '" name="' . $this->option_name . '[' . esc_attr($args['name']) . ']" value="' . esc_attr($value) . '" min="' . esc_attr($min) . '" max="' . esc_attr($max) . '" class="small-text"/>';

        if (!empty($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }

    /**
     * Section info callbacks
     */
    public function render_general_section_info()
    {
        echo '<p>' . __('Configure general WhatsApp integration settings for your store.', 'chatshop') . '</p>';
    }

    public function render_api_section_info()
    {
        echo '<p>' . __('Configure your WhatsApp Business API credentials. You can get these from your Facebook Business Manager.', 'chatshop') . '</p>';
        echo '<p><a href="https://developers.facebook.com/docs/whatsapp/business-management-api/get-started" target="_blank" class="button-secondary">' . __('Get API Credentials', 'chatshop') . '</a></p>';
    }

    public function render_automation_section_info()
    {
        echo '<p>' . __('Configure automated messaging features for better customer engagement.', 'chatshop') . '</p>';
    }

    /**
     * AJAX handlers
     */
    public function ajax_test_connection()
    {
        check_ajax_referer('chatshop_whatsapp_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'chatshop'));
        }

        $settings = $this->get_settings();

        if (empty($settings['access_token']) || empty($settings['phone_number_id'])) {
            wp_send_json_error(__('Please configure your API credentials first', 'chatshop'));
        }

        // Test API connection
        $result = $this->whatsapp_api->test_connection();

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(__('WhatsApp API connection successful!', 'chatshop'));
    }

    public function ajax_save_settings()
    {
        check_ajax_referer('chatshop_whatsapp_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'chatshop'));
        }

        $settings_data = json_decode(file_get_contents('php://input'), true);

        if (!$settings_data) {
            wp_send_json_error(__('Invalid settings data', 'chatshop'));
        }

        $sanitized_settings = $this->sanitize_settings($settings_data);

        if (update_option($this->option_name, $sanitized_settings)) {
            wp_send_json_success(__('Settings saved successfully!', 'chatshop'));
        } else {
            wp_send_json_error(__('Failed to save settings', 'chatshop'));
        }
    }

    public function ajax_send_test_message()
    {
        check_ajax_referer('chatshop_whatsapp_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'chatshop'));
        }

        $phone_number = sanitize_text_field($_POST['phone_number'] ?? '');
        $message = sanitize_textarea_field($_POST['message'] ?? '');

        if (empty($phone_number) || empty($message)) {
            wp_send_json_error(__('Phone number and message are required', 'chatshop'));
        }

        $result = $this->message_sender->send_text_message($phone_number, $message);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(__('Test message sent successfully!', 'chatshop'));
    }

    public function ajax_sync_templates()
    {
        check_ajax_referer('chatshop_whatsapp_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'chatshop'));
        }

        $result = $this->message_templates->sync_templates_with_whatsapp();

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
    }

    public function ajax_get_contact_stats()
    {
        check_ajax_referer('chatshop_whatsapp_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'chatshop'));
        }

        global $wpdb;
        $contacts_table = $wpdb->prefix . 'chatshop_contacts';
        $messages_table = $wpdb->prefix . 'chatshop_messages';

        $stats = [
            'total_contacts' => $wpdb->get_var("SELECT COUNT(*) FROM {$contacts_table}"),
            'opted_in_contacts' => $wpdb->get_var("SELECT COUNT(*) FROM {$contacts_table} WHERE opt_in = 1"),
            'messages_sent_today' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$messages_table} WHERE direction = 'outgoing' AND DATE(created_at) = %s",
                current_time('Y-m-d')
            )),
            'messages_received_today' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$messages_table} WHERE direction = 'incoming' AND DATE(created_at) = %s",
                current_time('Y-m-d')
            ))
        ];

        wp_send_json_success($stats);
    }

    public function ajax_export_contacts()
    {
        check_ajax_referer('chatshop_whatsapp_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'chatshop'));
        }

        global $wpdb;
        $contacts_table = $wpdb->prefix . 'chatshop_contacts';

        $contacts = $wpdb->get_results(
            "SELECT phone_number, first_name, last_name, email, opt_in, created_at FROM {$contacts_table} ORDER BY created_at DESC",
            ARRAY_A
        );

        // Generate CSV
        $csv_data = "Phone Number,First Name,Last Name,Email,Opted In,Created At\n";

        foreach ($contacts as $contact) {
            $csv_data .= sprintf(
                "%s,%s,%s,%s,%s,%s\n",
                $contact['phone_number'],
                $contact['first_name'],
                $contact['last_name'],
                $contact['email'],
                $contact['opt_in'] ? 'Yes' : 'No',
                $contact['created_at']
            );
        }

        wp_send_json_success([
            'csv_data' => $csv_data,
            'filename' => 'chatshop-contacts-' . date('Y-m-d') . '.csv'
        ]);
    }

    /**
     * Helper methods
     */
    private function get_settings()
    {
        return get_option($this->option_name, []);
    }

    private function is_whatsapp_configured()
    {
        $settings = $this->get_settings();
        return !empty($settings['access_token']) &&
            !empty($settings['phone_number_id']) &&
            !empty($settings['business_account_id']);
    }

    private function get_analytics_data()
    {
        global $wpdb;

        $messages_table = $wpdb->prefix . 'chatshop_messages';
        $contacts_table = $wpdb->prefix . 'chatshop_contacts';
        $campaigns_table = $wpdb->prefix . 'chatshop_campaigns';

        $date_30_days_ago = date('Y-m-d H:i:s', strtotime('-30 days'));

        return [
            'total_messages' => $wpdb->get_var("SELECT COUNT(*) FROM {$messages_table}"),
            'messages_last_30_days' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$messages_table} WHERE created_at >= %s",
                $date_30_days_ago
            )),
            'total_contacts' => $wpdb->get_var("SELECT COUNT(*) FROM {$contacts_table}"),
            'active_contacts' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT phone_number) FROM {$messages_table} WHERE created_at >= %s",
                $date_30_days_ago
            )),
            'total_campaigns' => $wpdb->get_var("SELECT COUNT(*) FROM {$campaigns_table}"),
            'successful_campaigns' => $wpdb->get_var("SELECT COUNT(*) FROM {$campaigns_table} WHERE status = 'sent'"),
            'daily_stats' => $wpdb->get_results($wpdb->prepare(
                "SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as message_count,
                    COUNT(DISTINCT phone_number) as unique_contacts
                 FROM {$messages_table} 
                 WHERE created_at >= %s 
                 GROUP BY DATE(created_at) 
                 ORDER BY date ASC",
                $date_30_days_ago
            ), ARRAY_A)
        ];
    }

    public function sanitize_settings($input)
    {
        $sanitized = [];

        // Boolean fields
        $boolean_fields = ['enabled', 'welcome_message_enabled', 'order_notifications_enabled', 'cart_abandonment_enabled'];
        foreach ($boolean_fields as $field) {
            $sanitized[$field] = isset($input[$field]) ? 1 : 0;
        }

        // Text fields
        $text_fields = ['default_country_code', 'business_account_id', 'phone_number_id', 'webhook_verify_token'];
        foreach ($text_fields as $field) {
            if (isset($input[$field])) {
                $sanitized[$field] = sanitize_text_field($input[$field]);
            }
        }

        // Password fields (encrypted storage)
        $password_fields = ['access_token', 'app_secret'];
        foreach ($password_fields as $field) {
            if (isset($input[$field]) && !empty($input[$field])) {
                $sanitized[$field] = $this->encrypt_sensitive_data($input[$field]);
            }
        }

        // Textarea fields
        if (isset($input['welcome_message_text'])) {
            $sanitized['welcome_message_text'] = sanitize_textarea_field($input['welcome_message_text']);
        }

        // Number fields
        if (isset($input['cart_abandonment_delay'])) {
            $sanitized['cart_abandonment_delay'] = max(1, min(48, intval($input['cart_abandonment_delay'])));
        }

        return $sanitized;
    }

    /**
     * Encrypt sensitive data
     *
     * @param string $data Data to encrypt
     * @return string Encrypted data
     */
    private function encrypt_sensitive_data($data)
    {
        if (function_exists('openssl_encrypt')) {
            $key = wp_salt('secure_auth');
            $iv = openssl_random_pseudo_bytes(16);
            $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
            return base64_encode($iv . $encrypted);
        }

        // Fallback to base64 (not secure, but better than plain text)
        return base64_encode($data);
    }

    /**
     * Decrypt sensitive data
     *
     * @param string $encrypted_data Encrypted data
     * @return string Decrypted data
     */
    public function decrypt_sensitive_data($encrypted_data)
    {
        if (function_exists('openssl_decrypt')) {
            $key = wp_salt('secure_auth');
            $data = base64_decode($encrypted_data);
            $iv = substr($data, 0, 16);
            $encrypted = substr($data, 16);
            return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
        }

        // Fallback from base64
        return base64_decode($encrypted_data);
    }
}
