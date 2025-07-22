<?php

/**
 * WhatsApp Admin Settings Class
 *
 * Handles WhatsApp admin settings for the ChatShop plugin.
 * Manages configuration, templates, contacts, and campaigns.
 *
 * @package ChatShop
 * @subpackage Admin\WhatsApp
 * @since 1.0.0
 */

namespace ChatShop;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ChatShop WhatsApp Admin Settings
 *
 * Manages WhatsApp admin interface including settings,
 * contact management, campaign creation, and analytics.
 *
 * @since 1.0.0
 */
class ChatShop_WhatsApp_Admin_Settings
{
    /**
     * WhatsApp Manager instance
     *
     * @var ChatShop_WhatsApp_Manager
     * @since 1.0.0
     */
    private $whatsapp_manager;

    /**
     * Contact Manager instance
     *
     * @var ChatShop_Contact_Manager
     * @since 1.0.0
     */
    private $contact_manager;

    /**
     * Campaign Manager instance
     *
     * @var ChatShop_Campaign_Manager
     * @since 1.0.0
     */
    private $campaign_manager;

    /**
     * Message Templates instance
     *
     * @var ChatShop_Message_Templates
     * @since 1.0.0
     */
    private $templates;

    /**
     * Settings sections
     *
     * @var array
     * @since 1.0.0
     */
    private $settings_sections = array();

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        $this->init_components();
        $this->init_hooks();
        $this->register_settings_sections();
    }

    /**
     * Initialize component instances
     *
     * @since 1.0.0
     */
    private function init_components()
    {
        if (class_exists('ChatShop\ChatShop_WhatsApp_Manager')) {
            $this->whatsapp_manager = new ChatShop_WhatsApp_Manager();
        }

        if (class_exists('ChatShop\ChatShop_Contact_Manager')) {
            $this->contact_manager = new ChatShop_Contact_Manager();
        }

        if (class_exists('ChatShop\ChatShop_Campaign_Manager')) {
            $this->campaign_manager = new ChatShop_Campaign_Manager();
        }

        if (class_exists('ChatShop\ChatShop_Message_Templates')) {
            $this->templates = new ChatShop_Message_Templates();
        }
    }

    /**
     * Initialize WordPress hooks
     *
     * @since 1.0.0
     */
    private function init_hooks()
    {
        // Admin menu hooks
        add_action('admin_menu', array($this, 'add_whatsapp_menu'));
        add_action('admin_init', array($this, 'register_settings'));

        // AJAX hooks
        add_action('wp_ajax_chatshop_test_whatsapp_connection', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_chatshop_save_whatsapp_settings', array($this, 'ajax_save_settings'));
        add_action('wp_ajax_chatshop_get_contact_stats', array($this, 'ajax_get_contact_stats'));
        add_action('wp_ajax_chatshop_get_campaign_analytics', array($this, 'ajax_get_campaign_analytics'));

        // Scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    /**
     * Add WhatsApp menu to admin
     *
     * @since 1.0.0
     */
    public function add_whatsapp_menu()
    {
        // Main WhatsApp menu
        add_submenu_page(
            'chatshop',
            __('WhatsApp Settings', 'chatshop'),
            __('WhatsApp', 'chatshop'),
            'manage_options',
            'chatshop-whatsapp',
            array($this, 'render_whatsapp_dashboard')
        );

        // Settings submenu
        add_submenu_page(
            'chatshop',
            __('WhatsApp Configuration', 'chatshop'),
            __('Configuration', 'chatshop'),
            'manage_options',
            'chatshop-whatsapp-settings',
            array($this, 'render_settings_page')
        );

        // Contacts submenu
        add_submenu_page(
            'chatshop',
            __('WhatsApp Contacts', 'chatshop'),
            __('Contacts', 'chatshop'),
            'manage_options',
            'chatshop-whatsapp-contacts',
            array($this, 'render_contacts_page')
        );

        // Campaigns submenu
        add_submenu_page(
            'chatshop',
            __('WhatsApp Campaigns', 'chatshop'),
            __('Campaigns', 'chatshop'),
            'manage_options',
            'chatshop-whatsapp-campaigns',
            array($this, 'render_campaigns_page')
        );

        // Templates submenu (premium)
        if (chatshop_is_premium_feature_available('whatsapp_templates')) {
            add_submenu_page(
                'chatshop',
                __('Message Templates', 'chatshop'),
                __('Templates', 'chatshop'),
                'manage_options',
                'chatshop-whatsapp-templates',
                array($this, 'render_templates_page')
            );
        }

        // Analytics submenu (premium)
        if (chatshop_is_premium_feature_available('advanced_analytics')) {
            add_submenu_page(
                'chatshop',
                __('WhatsApp Analytics', 'chatshop'),
                __('Analytics', 'chatshop'),
                'manage_options',
                'chatshop-whatsapp-analytics',
                array($this, 'render_analytics_page')
            );
        }
    }

    /**
     * Register settings sections
     *
     * @since 1.0.0
     */
    private function register_settings_sections()
    {
        $this->settings_sections = array(
            'general' => array(
                'title' => __('General Settings', 'chatshop'),
                'callback' => array($this, 'render_general_section'),
            ),
            'api' => array(
                'title' => __('WhatsApp Business API', 'chatshop'),
                'callback' => array($this, 'render_api_section'),
            ),
            'notifications' => array(
                'title' => __('Notifications', 'chatshop'),
                'callback' => array($this, 'render_notifications_section'),
            ),
            'automation' => array(
                'title' => __('Automation (Premium)', 'chatshop'),
                'callback' => array($this, 'render_automation_section'),
            ),
        );
    }

    /**
     * Register settings
     *
     * @since 1.0.0
     */
    public function register_settings()
    {
        // Register WhatsApp options
        register_setting('chatshop_whatsapp_options', 'chatshop_whatsapp_options', array(
            'sanitize_callback' => array($this, 'sanitize_whatsapp_settings'),
        ));

        // Add settings sections
        foreach ($this->settings_sections as $section_id => $section) {
            add_settings_section(
                "chatshop_whatsapp_{$section_id}",
                $section['title'],
                $section['callback'],
                'chatshop_whatsapp_settings'
            );
        }

        // General settings fields
        $this->register_general_fields();

        // API settings fields
        $this->register_api_fields();

        // Notification settings fields
        $this->register_notification_fields();

        // Automation settings fields (premium)
        if (chatshop_is_premium_feature_available('whatsapp_automation')) {
            $this->register_automation_fields();
        }
    }

    /**
     * Register general settings fields
     *
     * @since 1.0.0
     */
    private function register_general_fields()
    {
        add_settings_field(
            'whatsapp_enabled',
            __('Enable WhatsApp Integration', 'chatshop'),
            array($this, 'render_checkbox_field'),
            'chatshop_whatsapp_settings',
            'chatshop_whatsapp_general',
            array(
                'field_name' => 'enabled',
                'description' => __('Enable WhatsApp messaging for your store', 'chatshop'),
            )
        );

        add_settings_field(
            'whatsapp_api_type',
            __('API Type', 'chatshop'),
            array($this, 'render_select_field'),
            'chatshop_whatsapp_settings',
            'chatshop_whatsapp_general',
            array(
                'field_name' => 'api_type',
                'options' => array(
                    'business' => __('WhatsApp Business API (Recommended)', 'chatshop'),
                    'web' => __('WhatsApp Web (Fallback)', 'chatshop'),
                ),
                'description' => __('Choose your preferred WhatsApp integration method', 'chatshop'),
            )
        );

        add_settings_field(
            'whatsapp_fallback_enabled',
            __('Enable Fallback', 'chatshop'),
            array($this, 'render_checkbox_field'),
            'chatshop_whatsapp_settings',
            'chatshop_whatsapp_general',
            array(
                'field_name' => 'fallback_enabled',
                'description' => __('Automatically fallback to Web API if Business API fails', 'chatshop'),
            )
        );
    }

    /**
     * Register API settings fields
     *
     * @since 1.0.0
     */
    private function register_api_fields()
    {
        add_settings_field(
            'whatsapp_phone_number_id',
            __('Phone Number ID', 'chatshop'),
            array($this, 'render_text_field'),
            'chatshop_whatsapp_settings',
            'chatshop_whatsapp_api',
            array(
                'field_name' => 'phone_number_id',
                'description' => __('Your WhatsApp Business phone number ID from Meta Business Manager', 'chatshop'),
                'required' => true,
            )
        );

        add_settings_field(
            'whatsapp_access_token',
            __('Access Token', 'chatshop'),
            array($this, 'render_password_field'),
            'chatshop_whatsapp_settings',
            'chatshop_whatsapp_api',
            array(
                'field_name' => 'access_token',
                'description' => __('Your WhatsApp Business API access token', 'chatshop'),
                'required' => true,
            )
        );

        add_settings_field(
            'whatsapp_verify_token',
            __('Verify Token', 'chatshop'),
            array($this, 'render_text_field'),
            'chatshop_whatsapp_settings',
            'chatshop_whatsapp_api',
            array(
                'field_name' => 'verify_token',
                'description' => __('Webhook verification token', 'chatshop'),
            )
        );

        add_settings_field(
            'whatsapp_app_secret',
            __('App Secret', 'chatshop'),
            array($this, 'render_password_field'),
            'chatshop_whatsapp_settings',
            'chatshop_whatsapp_api',
            array(
                'field_name' => 'app_secret',
                'description' => __('Your Facebook App secret for webhook signature verification', 'chatshop'),
            )
        );

        add_settings_field(
            'whatsapp_webhook_url',
            __('Webhook URL', 'chatshop'),
            array($this, 'render_webhook_url_field'),
            'chatshop_whatsapp_settings',
            'chatshop_whatsapp_api',
            array(
                'field_name' => 'webhook_url',
                'description' => __('Copy this URL to your WhatsApp Business webhook configuration', 'chatshop'),
            )
        );
    }

    /**
     * Register notification settings fields
     *
     * @since 1.0.0
     */
    private function register_notification_fields()
    {
        add_settings_field(
            'order_notifications',
            __('Order Notifications', 'chatshop'),
            array($this, 'render_checkbox_group_field'),
            'chatshop_whatsapp_settings',
            'chatshop_whatsapp_notifications',
            array(
                'field_name' => 'order_notifications',
                'options' => array(
                    'processing' => __('Order Processing', 'chatshop'),
                    'completed' => __('Order Completed', 'chatshop'),
                    'cancelled' => __('Order Cancelled', 'chatshop'),
                    'refunded' => __('Order Refunded', 'chatshop'),
                ),
                'description' => __('Send WhatsApp notifications for these order status changes', 'chatshop'),
            )
        );

        add_settings_field(
            'product_notifications',
            __('Product Notifications', 'chatshop'),
            array($this, 'render_checkbox_group_field'),
            'chatshop_whatsapp_settings',
            'chatshop_whatsapp_notifications',
            array(
                'field_name' => 'product_notifications',
                'options' => array(
                    'new_product' => __('New Products', 'chatshop'),
                    'price_drop' => __('Price Drops', 'chatshop'),
                    'back_in_stock' => __('Back in Stock', 'chatshop'),
                ),
                'description' => __('Send automated notifications for product events', 'chatshop'),
            )
        );
    }

    /**
     * Register automation settings fields
     *
     * @since 1.0.0
     */
    private function register_automation_fields()
    {
        add_settings_field(
            'cart_abandonment',
            __('Cart Abandonment Recovery', 'chatshop'),
            array($this, 'render_checkbox_field'),
            'chatshop_whatsapp_settings',
            'chatshop_whatsapp_automation',
            array(
                'field_name' => 'cart_abandonment',
                'description' => __('Send automated messages to recover abandoned carts', 'chatshop'),
                'premium' => true,
            )
        );

        add_settings_field(
            'automation_delay',
            __('Automation Delay (hours)', 'chatshop'),
            array($this, 'render_number_field'),
            'chatshop_whatsapp_settings',
            'chatshop_whatsapp_automation',
            array(
                'field_name' => 'automation_delay',
                'min' => 1,
                'max' => 72,
                'default' => 1,
                'description' => __('Delay before sending automated messages', 'chatshop'),
                'premium' => true,
            )
        );

        add_settings_field(
            'win_back_campaigns',
            __('Win-back Campaigns', 'chatshop'),
            array($this, 'render_checkbox_field'),
            'chatshop_whatsapp_settings',
            'chatshop_whatsapp_automation',
            array(
                'field_name' => 'win_back_campaigns',
                'description' => __('Automatically re-engage inactive customers', 'chatshop'),
                'premium' => true,
            )
        );
    }

    /**
     * Render WhatsApp dashboard
     *
     * @since 1.0.0
     */
    public function render_whatsapp_dashboard()
    {
        $contact_stats = $this->get_contact_statistics();
        $campaign_stats = $this->get_campaign_statistics();
        $premium_features = $this->get_premium_features_status();

?>
        <div class="wrap">
            <h1><?php esc_html_e('WhatsApp Dashboard', 'chatshop'); ?></h1>

            <!-- Status Cards -->
            <div class="chatshop-dashboard-cards">
                <div class="chatshop-card">
                    <h3><?php esc_html_e('Total Contacts', 'chatshop'); ?></h3>
                    <div class="chatshop-stat-number"><?php echo esc_html($contact_stats['total']); ?></div>
                    <p><?php echo esc_html($contact_stats['opted_in']); ?> <?php esc_html_e('opted in', 'chatshop'); ?></p>
                </div>

                <div class="chatshop-card">
                    <h3><?php esc_html_e('Active Campaigns', 'chatshop'); ?></h3>
                    <div class="chatshop-stat-number"><?php echo esc_html($campaign_stats['active']); ?></div>
                    <p><?php echo esc_html($campaign_stats['completed_this_month']); ?> <?php esc_html_e('this month', 'chatshop'); ?></p>
                </div>

                <div class="chatshop-card">
                    <h3><?php esc_html_e('Messages Sent', 'chatshop'); ?></h3>
                    <div class="chatshop-stat-number"><?php echo esc_html($campaign_stats['messages_sent']); ?></div>
                    <p><?php echo esc_html($campaign_stats['delivery_rate']); ?>% <?php esc_html_e('delivery rate', 'chatshop'); ?></p>
                </div>

                <div class="chatshop-card">
                    <h3><?php esc_html_e('Revenue Generated', 'chatshop'); ?></h3>
                    <div class="chatshop-stat-number"><?php echo esc_html(wc_price($campaign_stats['revenue_generated'])); ?></div>
                    <p><?php esc_html_e('from WhatsApp campaigns', 'chatshop'); ?></p>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="chatshop-quick-actions">
                <h2><?php esc_html_e('Quick Actions', 'chatshop'); ?></h2>
                <div class="chatshop-actions-grid">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=chatshop-whatsapp-campaigns&action=new')); ?>" class="button button-primary">
                        <?php esc_html_e('Create Campaign', 'chatshop'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=chatshop-whatsapp-contacts&action=import')); ?>" class="button">
                        <?php esc_html_e('Import Contacts', 'chatshop'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=chatshop-whatsapp-settings')); ?>" class="button">
                        <?php esc_html_e('Configure Settings', 'chatshop'); ?>
                    </a>
                    <button id="test-whatsapp-connection" class="button">
                        <?php esc_html_e('Test Connection', 'chatshop'); ?>
                    </button>
                </div>
            </div>

            <!-- Premium Features -->
            <?php if (!$premium_features['has_premium']): ?>
                <div class="chatshop-premium-notice">
                    <h3><?php esc_html_e('Unlock Premium Features', 'chatshop'); ?></h3>
                    <p><?php esc_html_e('Upgrade to access advanced WhatsApp features:', 'chatshop'); ?></p>
                    <ul>
                        <li><?php esc_html_e('Unlimited contacts and campaigns', 'chatshop'); ?></li>
                        <li><?php esc_html_e('Advanced automation and templates', 'chatshop'); ?></li>
                        <li><?php esc_html_e('Detailed analytics and reporting', 'chatshop'); ?></li>
                        <li><?php esc_html_e('A/B testing and segmentation', 'chatshop'); ?></li>
                    </ul>
                    <a href="#" class="button button-primary"><?php esc_html_e('Upgrade Now', 'chatshop'); ?></a>
                </div>
            <?php endif; ?>

            <!-- Recent Activity -->
            <div class="chatshop-recent-activity">
                <h2><?php esc_html_e('Recent Activity', 'chatshop'); ?></h2>
                <div id="chatshop-activity-feed">
                    <?php $this->render_activity_feed(); ?>
                </div>
            </div>
        </div>

        <style>
            .chatshop-dashboard-cards {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 20px;
                margin: 20px 0;
            }

            .chatshop-card {
                background: #fff;
                border: 1px solid #ddd;
                border-radius: 8px;
                padding: 20px;
                text-align: center;
            }

            .chatshop-stat-number {
                font-size: 2em;
                font-weight: bold;
                color: #0073aa;
                margin: 10px 0;
            }

            .chatshop-actions-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 10px;
                margin: 15px 0;
            }

            .chatshop-premium-notice {
                background: #f0f8ff;
                border: 1px solid #0073aa;
                border-radius: 8px;
                padding: 20px;
                margin: 20px 0;
            }

            .chatshop-premium-notice ul {
                list-style: none;
                padding-left: 0;
            }

            .chatshop-premium-notice li:before {
                content: "✓ ";
                color: #00a32a;
                font-weight: bold;
            }
        </style>
    <?php
    }

    /**
     * Render settings page
     *
     * @since 1.0.0
     */
    public function render_settings_page()
    {
    ?>
        <div class="wrap">
            <h1><?php esc_html_e('WhatsApp Settings', 'chatshop'); ?></h1>

            <form method="post" action="options.php">
                <?php
                settings_fields('chatshop_whatsapp_options');
                do_settings_sections('chatshop_whatsapp_settings');
                submit_button();
                ?>
            </form>
        </div>
    <?php
    }

    /**
     * Render contacts page
     *
     * @since 1.0.0
     */
    public function render_contacts_page()
    {
        $action = $_GET['action'] ?? 'list';

        switch ($action) {
            case 'new':
                $this->render_add_contact_form();
                break;
            case 'import':
                $this->render_import_contacts_form();
                break;
            case 'groups':
                $this->render_contact_groups_page();
                break;
            default:
                $this->render_contacts_list();
                break;
        }
    }

    /**
     * Render campaigns page
     *
     * @since 1.0.0
     */
    public function render_campaigns_page()
    {
        $action = $_GET['action'] ?? 'list';

        switch ($action) {
            case 'new':
                $this->render_create_campaign_form();
                break;
            case 'edit':
                $this->render_edit_campaign_form();
                break;
            case 'view':
                $this->render_campaign_details();
                break;
            default:
                $this->render_campaigns_list();
                break;
        }
    }

    /**
     * Render templates page
     *
     * @since 1.0.0
     */
    public function render_templates_page()
    {
        if (!chatshop_is_premium_feature_available('whatsapp_templates')) {
            $this->render_premium_required_notice('Message Templates');
            return;
        }

        $action = $_GET['action'] ?? 'list';

        switch ($action) {
            case 'new':
                $this->render_create_template_form();
                break;
            case 'edit':
                $this->render_edit_template_form();
                break;
            default:
                $this->render_templates_list();
                break;
        }
    }

    /**
     * Render analytics page
     *
     * @since 1.0.0
     */
    public function render_analytics_page()
    {
        if (!chatshop_is_premium_feature_available('advanced_analytics')) {
            $this->render_premium_required_notice('Advanced Analytics');
            return;
        }

    ?>
        <div class="wrap">
            <h1><?php esc_html_e('WhatsApp Analytics', 'chatshop'); ?></h1>

            <!-- Analytics dashboard content -->
            <div id="chatshop-analytics-dashboard">
                <?php $this->render_analytics_dashboard(); ?>
            </div>
        </div>
    <?php
    }

    /**
     * Sanitize WhatsApp settings
     *
     * @since 1.0.0
     * @param array $input Input settings
     * @return array Sanitized settings
     */
    public function sanitize_whatsapp_settings($input)
    {
        $sanitized = array();

        // General settings
        $sanitized['enabled'] = !empty($input['enabled']);
        $sanitized['api_type'] = sanitize_key($input['api_type'] ?? 'business');
        $sanitized['fallback_enabled'] = !empty($input['fallback_enabled']);

        // API settings
        $sanitized['phone_number_id'] = sanitize_text_field($input['phone_number_id'] ?? '');
        $sanitized['access_token'] = $this->encrypt_setting($input['access_token'] ?? '');
        $sanitized['verify_token'] = sanitize_text_field($input['verify_token'] ?? '');
        $sanitized['app_secret'] = $this->encrypt_setting($input['app_secret'] ?? '');
        $sanitized['webhook_url'] = esc_url_raw($input['webhook_url'] ?? '');

        // Notification settings
        $sanitized['order_notifications'] = array_map('sanitize_key', $input['order_notifications'] ?? array());
        $sanitized['product_notifications'] = array_map('sanitize_key', $input['product_notifications'] ?? array());

        // Automation settings (premium)
        if (chatshop_is_premium_feature_available('whatsapp_automation')) {
            $sanitized['cart_abandonment'] = !empty($input['cart_abandonment']);
            $sanitized['automation_delay'] = intval($input['automation_delay'] ?? 1);
            $sanitized['win_back_campaigns'] = !empty($input['win_back_campaigns']);
        }

        return $sanitized;
    }

    /**
     * Encrypt sensitive setting
     *
     * @since 1.0.0
     * @param string $value Setting value
     * @return string Encrypted value
     */
    private function encrypt_setting($value)
    {
        if (empty($value)) {
            return '';
        }

        $encryption_key = wp_salt('auth');
        return openssl_encrypt($value, 'AES-256-CBC', $encryption_key, 0, substr($encryption_key, 0, 16));
    }

    /**
     * Enqueue admin assets
     *
     * @since 1.0.0
     * @param string $hook Page hook
     */
    public function enqueue_admin_assets($hook)
    {
        // Only load on ChatShop WhatsApp pages
        if (strpos($hook, 'chatshop-whatsapp') === false) {
            return;
        }

        wp_enqueue_script(
            'chatshop-whatsapp-admin',
            CHATSHOP_PLUGIN_URL . 'admin/js/whatsapp-admin.js',
            array('jquery', 'wp-util'),
            CHATSHOP_VERSION,
            true
        );

        wp_enqueue_style(
            'chatshop-whatsapp-admin',
            CHATSHOP_PLUGIN_URL . 'admin/css/whatsapp-admin.css',
            array(),
            CHATSHOP_VERSION
        );

        // Localize script
        wp_localize_script('chatshop-whatsapp-admin', 'chatshopWhatsApp', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('chatshop_whatsapp_nonce'),
            'strings' => array(
                'confirm_delete' => __('Are you sure you want to delete this item?', 'chatshop'),
                'connection_testing' => __('Testing connection...', 'chatshop'),
                'connection_success' => __('Connection successful!', 'chatshop'),
                'connection_failed' => __('Connection failed. Please check your settings.', 'chatshop'),
            ),
        ));
    }

    /**
     * AJAX test WhatsApp connection
     *
     * @since 1.0.0
     */
    public function ajax_test_connection()
    {
        // Verify nonce and permissions
        if (!wp_verify_nonce($_POST['nonce'], 'chatshop_whatsapp_nonce') || !current_user_can('manage_options')) {
            wp_die(__('Security check failed', 'chatshop'));
        }

        if ($this->whatsapp_manager) {
            $api_client = $this->whatsapp_manager->get_api_client();
            $result = $api_client->test_connection();
            wp_send_json($result);
        }

        wp_send_json_error(__('WhatsApp manager not available', 'chatshop'));
    }

    /**
     * AJAX save WhatsApp settings
     *
     * @since 1.0.0
     */
    public function ajax_save_settings()
    {
        // Verify nonce and permissions
        if (!wp_verify_nonce($_POST['nonce'], 'chatshop_whatsapp_nonce') || !current_user_can('manage_options')) {
            wp_die(__('Security check failed', 'chatshop'));
        }

        $settings = $_POST['settings'] ?? array();
        $sanitized_settings = $this->sanitize_whatsapp_settings($settings);

        $result = update_option('chatshop_whatsapp_options', $sanitized_settings);

        if ($result) {
            wp_send_json_success(__('Settings saved successfully', 'chatshop'));
        } else {
            wp_send_json_error(__('Failed to save settings', 'chatshop'));
        }
    }

    /**
     * AJAX get contact statistics
     *
     * @since 1.0.0
     */
    public function ajax_get_contact_stats()
    {
        // Verify nonce and permissions
        if (!wp_verify_nonce($_POST['nonce'], 'chatshop_whatsapp_nonce') || !current_user_can('manage_options')) {
            wp_die(__('Security check failed', 'chatshop'));
        }

        if ($this->contact_manager) {
            $stats = $this->contact_manager->get_contact_statistics();
            wp_send_json_success($stats);
        }

        wp_send_json_error(__('Contact manager not available', 'chatshop'));
    }

    /**
     * AJAX get campaign analytics
     *
     * @since 1.0.0
     */
    public function ajax_get_campaign_analytics()
    {
        // Verify nonce and permissions
        if (!wp_verify_nonce($_POST['nonce'], 'chatshop_whatsapp_nonce') || !current_user_can('manage_options')) {
            wp_die(__('Security check failed', 'chatshop'));
        }

        $period = sanitize_key($_POST['period'] ?? '30days');
        $analytics = $this->get_campaign_analytics($period);

        wp_send_json_success($analytics);
    }

    /**
     * Render field helpers
     */

    /**
     * Render checkbox field
     *
     * @since 1.0.0
     * @param array $args Field arguments
     */
    public function render_checkbox_field($args)
    {
        $options = get_option('chatshop_whatsapp_options', array());
        $value = isset($options[$args['field_name']]) ? $options[$args['field_name']] : false;
        $premium = $args['premium'] ?? false;

    ?>
        <label>
            <input type="checkbox"
                name="chatshop_whatsapp_options[<?php echo esc_attr($args['field_name']); ?>]"
                value="1"
                <?php checked($value); ?>
                <?php echo $premium && !chatshop_is_premium_feature_available('whatsapp_automation') ? 'disabled' : ''; ?>>
            <?php echo esc_html($args['description']); ?>
            <?php if ($premium && !chatshop_is_premium_feature_available('whatsapp_automation')): ?>
                <span class="chatshop-premium-badge"><?php esc_html_e('Premium', 'chatshop'); ?></span>
            <?php endif; ?>
        </label>
    <?php
    }

    /**
     * Render text field
     *
     * @since 1.0.0
     * @param array $args Field arguments
     */
    public function render_text_field($args)
    {
        $options = get_option('chatshop_whatsapp_options', array());
        $value = isset($options[$args['field_name']]) ? $options[$args['field_name']] : '';
        $required = $args['required'] ?? false;

    ?>
        <input type="text"
            id="<?php echo esc_attr($args['field_name']); ?>"
            name="chatshop_whatsapp_options[<?php echo esc_attr($args['field_name']); ?>]"
            value="<?php echo esc_attr($value); ?>"
            class="regular-text"
            <?php echo $required ? 'required' : ''; ?>>
        <?php if (!empty($args['description'])): ?>
            <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php endif; ?>
    <?php
    }

    /**
     * Render password field
     *
     * @since 1.0.0
     * @param array $args Field arguments
     */
    public function render_password_field($args)
    {
        $options = get_option('chatshop_whatsapp_options', array());
        $value = isset($options[$args['field_name']]) ? $options[$args['field_name']] : '';
        $required = $args['required'] ?? false;

    ?>
        <input type="password"
            id="<?php echo esc_attr($args['field_name']); ?>"
            name="chatshop_whatsapp_options[<?php echo esc_attr($args['field_name']); ?>]"
            value="<?php echo esc_attr($value); ?>"
            class="regular-text"
            <?php echo $required ? 'required' : ''; ?>>
        <?php if (!empty($args['description'])): ?>
            <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php endif; ?>
    <?php
    }

    /**
     * Render select field
     *
     * @since 1.0.0
     * @param array $args Field arguments
     */
    public function render_select_field($args)
    {
        $options = get_option('chatshop_whatsapp_options', array());
        $value = isset($options[$args['field_name']]) ? $options[$args['field_name']] : '';

    ?>
        <select name="chatshop_whatsapp_options[<?php echo esc_attr($args['field_name']); ?>]">
            <?php foreach ($args['options'] as $option_value => $option_label): ?>
                <option value="<?php echo esc_attr($option_value); ?>" <?php selected($value, $option_value); ?>>
                    <?php echo esc_html($option_label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if (!empty($args['description'])): ?>
            <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php endif; ?>
    <?php
    }

    /**
     * Render checkbox group field
     *
     * @since 1.0.0
     * @param array $args Field arguments
     */
    public function render_checkbox_group_field($args)
    {
        $options = get_option('chatshop_whatsapp_options', array());
        $values = isset($options[$args['field_name']]) ? (array) $options[$args['field_name']] : array();

    ?>
        <fieldset>
            <?php foreach ($args['options'] as $option_value => $option_label): ?>
                <label>
                    <input type="checkbox"
                        name="chatshop_whatsapp_options[<?php echo esc_attr($args['field_name']); ?>][]"
                        value="<?php echo esc_attr($option_value); ?>"
                        <?php checked(in_array($option_value, $values)); ?>>
                    <?php echo esc_html($option_label); ?>
                </label><br>
            <?php endforeach; ?>
            <?php if (!empty($args['description'])): ?>
                <p class="description"><?php echo esc_html($args['description']); ?></p>
            <?php endif; ?>
        </fieldset>
    <?php
    }

    /**
     * Render number field
     *
     * @since 1.0.0
     * @param array $args Field arguments
     */
    public function render_number_field($args)
    {
        $options = get_option('chatshop_whatsapp_options', array());
        $value = isset($options[$args['field_name']]) ? $options[$args['field_name']] : ($args['default'] ?? '');
        $premium = $args['premium'] ?? false;

    ?>
        <input type="number"
            name="chatshop_whatsapp_options[<?php echo esc_attr($args['field_name']); ?>]"
            value="<?php echo esc_attr($value); ?>"
            min="<?php echo esc_attr($args['min'] ?? 0); ?>"
            max="<?php echo esc_attr($args['max'] ?? 100); ?>"
            class="small-text"
            <?php echo $premium && !chatshop_is_premium_feature_available('whatsapp_automation') ? 'disabled' : ''; ?>>
        <?php if (!empty($args['description'])): ?>
            <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php endif; ?>
    <?php
    }

    /**
     * Render webhook URL field
     *
     * @since 1.0.0
     * @param array $args Field arguments
     */
    public function render_webhook_url_field($args)
    {
        $webhook_url = site_url('/wp-json/chatshop/v1/whatsapp/webhook');

    ?>
        <input type="text"
            value="<?php echo esc_attr($webhook_url); ?>"
            class="large-text"
            readonly>
        <button type="button" class="button" onclick="navigator.clipboard.writeText('<?php echo esc_js($webhook_url); ?>')">
            <?php esc_html_e('Copy', 'chatshop'); ?>
        </button>
        <?php if (!empty($args['description'])): ?>
            <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php endif; ?>
    <?php
    }

    /**
     * Render settings sections
     */

    /**
     * Render general section
     *
     * @since 1.0.0
     */
    public function render_general_section()
    {
        echo '<p>' . esc_html__('Configure basic WhatsApp integration settings.', 'chatshop') . '</p>';
    }

    /**
     * Render API section
     *
     * @since 1.0.0
     */
    public function render_api_section()
    {
        echo '<p>' . esc_html__('Configure your WhatsApp Business API credentials.', 'chatshop') . '</p>';
        echo '<div class="notice notice-info"><p>';
        echo esc_html__('You need a WhatsApp Business Account and Facebook Business Manager to use the Business API.', 'chatshop');
        echo ' <a href="https://business.whatsapp.com/" target="_blank">' . esc_html__('Learn more', 'chatshop') . '</a>';
        echo '</p></div>';
    }

    /**
     * Render notifications section
     *
     * @since 1.0.0
     */
    public function render_notifications_section()
    {
        echo '<p>' . esc_html__('Configure automatic notifications for various events.', 'chatshop') . '</p>';
    }

    /**
     * Render automation section
     *
     * @since 1.0.0
     */
    public function render_automation_section()
    {
        if (!chatshop_is_premium_feature_available('whatsapp_automation')) {
            echo '<div class="notice notice-warning"><p>';
            echo esc_html__('Automation features are available in the premium version.', 'chatshop');
            echo ' <a href="#upgrade">' . esc_html__('Upgrade now', 'chatshop') . '</a>';
            echo '</p></div>';
        } else {
            echo '<p>' . esc_html__('Configure advanced automation features.', 'chatshop') . '</p>';
        }
    }

    /**
     * Page renderers
     */

    /**
     * Render contacts list
     *
     * @since 1.0.0
     */
    private function render_contacts_list()
    {
        if (!$this->contact_manager) {
            return;
        }

        $contacts = $this->contact_manager->get_contacts_for_campaign();
        $premium_features = $this->contact_manager->get_premium_features();

    ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('WhatsApp Contacts', 'chatshop'); ?></h1>
            <a href="<?php echo esc_url(admin_url('admin.php?page=chatshop-whatsapp-contacts&action=new')); ?>" class="page-title-action">
                <?php esc_html_e('Add New', 'chatshop'); ?>
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=chatshop-whatsapp-contacts&action=import')); ?>" class="page-title-action">
                <?php esc_html_e('Import', 'chatshop'); ?>
            </a>

            <div class="tablenav top">
                <div class="alignleft actions bulkactions">
                    <select name="action" id="bulk-action-selector-top">
                        <option value="-1"><?php esc_html_e('Bulk Actions', 'chatshop'); ?></option>
                        <option value="delete"><?php esc_html_e('Delete', 'chatshop'); ?></option>
                        <option value="opt-out"><?php esc_html_e('Opt Out', 'chatshop'); ?></option>
                        <?php if ($premium_features['contact_groups']): ?>
                            <option value="add-to-group"><?php esc_html_e('Add to Group', 'chatshop'); ?></option>
                        <?php endif; ?>
                    </select>
                    <input type="submit" id="doaction" class="button action" value="<?php esc_attr_e('Apply', 'chatshop'); ?>">
                </div>
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <td class="manage-column column-cb check-column">
                            <input type="checkbox">
                        </td>
                        <th><?php esc_html_e('Name', 'chatshop'); ?></th>
                        <th><?php esc_html_e('Phone', 'chatshop'); ?></th>
                        <th><?php esc_html_e('Email', 'chatshop'); ?></th>
                        <th><?php esc_html_e('Opt-in Status', 'chatshop'); ?></th>
                        <th><?php esc_html_e('Source', 'chatshop'); ?></th>
                        <th><?php esc_html_e('Added', 'chatshop'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($contacts as $contact): ?>
                        <tr>
                            <th class="check-column">
                                <input type="checkbox" name="contact[]" value="<?php echo esc_attr($contact['id']); ?>">
                            </th>
                            <td>
                                <strong><?php echo esc_html($contact['first_name'] . ' ' . $contact['last_name']); ?></strong>
                            </td>
                            <td><?php echo esc_html($contact['phone']); ?></td>
                            <td><?php echo esc_html($contact['email']); ?></td>
                            <td>
                                <span class="status-<?php echo esc_attr($contact['opt_in_status']); ?>">
                                    <?php echo esc_html(ucfirst($contact['opt_in_status'])); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html(ucfirst($contact['source'])); ?></td>
                            <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($contact['created_at']))); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <style>
            .status-opted_in {
                color: #00a32a;
            }

            .status-opted_out {
                color: #d63638;
            }

            .status-pending {
                color: #dba617;
            }
        </style>
    <?php
    }

    /**
     * Render campaigns list
     *
     * @since 1.0.0
     */
    private function render_campaigns_list()
    {
        if (!$this->campaign_manager) {
            return;
        }

        global $wpdb;
        $campaigns = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}chatshop_campaigns ORDER BY created_at DESC LIMIT 50"
        );

    ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('WhatsApp Campaigns', 'chatshop'); ?></h1>
            <a href="<?php echo esc_url(admin_url('admin.php?page=chatshop-whatsapp-campaigns&action=new')); ?>" class="page-title-action">
                <?php esc_html_e('Create Campaign', 'chatshop'); ?>
            </a>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Campaign Name', 'chatshop'); ?></th>
                        <th><?php esc_html_e('Type', 'chatshop'); ?></th>
                        <th><?php esc_html_e('Status', 'chatshop'); ?></th>
                        <th><?php esc_html_e('Recipients', 'chatshop'); ?></th>
                        <th><?php esc_html_e('Sent', 'chatshop'); ?></th>
                        <th><?php esc_html_e('Success Rate', 'chatshop'); ?></th>
                        <th><?php esc_html_e('Created', 'chatshop'); ?></th>
                        <th><?php esc_html_e('Actions', 'chatshop'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($campaigns as $campaign): ?>
                        <?php
                        $stats = $this->campaign_manager->get_campaign_statistics($campaign->id);
                        $target_contacts = maybe_unserialize($campaign->target_contacts);
                        $recipient_count = is_array($target_contacts) ? count($target_contacts) : 0;
                        ?>
                        <tr>
                            <td>
                                <strong>
                                    <a href="<?php echo esc_url(admin_url("admin.php?page=chatshop-whatsapp-campaigns&action=view&campaign_id={$campaign->id}")); ?>">
                                        <?php echo esc_html($campaign->name); ?>
                                    </a>
                                </strong>
                            </td>
                            <td><?php echo esc_html(ucfirst($campaign->type)); ?></td>
                            <td>
                                <span class="campaign-status status-<?php echo esc_attr($campaign->status); ?>">
                                    <?php echo esc_html(ucfirst($campaign->status)); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($recipient_count); ?></td>
                            <td><?php echo esc_html($stats['total_sent'] ?? 0); ?></td>
                            <td><?php echo esc_html(($stats['delivery_rate'] ?? 0) . '%'); ?></td>
                            <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($campaign->created_at))); ?></td>
                            <td>
                                <?php if ($campaign->status === 'draft'): ?>
                                    <button class="button button-small send-campaign" data-campaign-id="<?php echo esc_attr($campaign->id); ?>">
                                        <?php esc_html_e('Send', 'chatshop'); ?>
                                    </button>
                                <?php endif; ?>
                                <a href="<?php echo esc_url(admin_url("admin.php?page=chatshop-whatsapp-campaigns&action=edit&campaign_id={$campaign->id}")); ?>" class="button button-small">
                                    <?php esc_html_e('Edit', 'chatshop'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <style>
            .campaign-status {
                padding: 3px 8px;
                border-radius: 3px;
                font-size: 12px;
            }

            .status-draft {
                background: #f0f0f1;
                color: #646970;
            }

            .status-sending {
                background: #dba617;
                color: white;
            }

            .status-completed {
                background: #00a32a;
                color: white;
            }

            .status-failed {
                background: #d63638;
                color: white;
            }

            .status-scheduled {
                background: #0073aa;
                color: white;
            }
        </style>
    <?php
    }

    /**
     * Render premium required notice
     *
     * @since 1.0.0
     * @param string $feature Feature name
     */
    private function render_premium_required_notice($feature)
    {
    ?>
        <div class="wrap">
            <h1><?php echo esc_html($feature); ?></h1>

            <div class="notice notice-info notice-large">
                <h2><?php esc_html_e('Premium Feature', 'chatshop'); ?></h2>
                <p><?php echo esc_html(sprintf(__('%s is available in the premium version of ChatShop.', 'chatshop'), $feature)); ?></p>
                <p>
                    <a href="#upgrade" class="button button-primary">
                        <?php esc_html_e('Upgrade to Premium', 'chatshop'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=chatshop')); ?>" class="button">
                        <?php esc_html_e('Back to Dashboard', 'chatshop'); ?>
                    </a>
                </p>
            </div>
        </div>
<?php
    }

    /**
     * Get contact statistics
     *
     * @since 1.0.0
     * @return array Contact statistics
     */
    private function get_contact_statistics()
    {
        if ($this->contact_manager) {
            return $this->contact_manager->get_contact_statistics();
        }

        return array(
            'total' => 0,
            'opted_in' => 0,
            'new_this_month' => 0,
            'segments' => array(),
        );
    }

    /**
     * Get campaign statistics
     *
     * @since 1.0.0
     * @return array Campaign statistics
     */
    private function get_campaign_statistics()
    {
        global $wpdb;

        $stats = array(
            'active' => 0,
            'completed_this_month' => 0,
            'messages_sent' => 0,
            'delivery_rate' => 0,
            'revenue_generated' => 0,
        );

        // Active campaigns
        $stats['active'] = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}chatshop_campaigns WHERE status IN ('draft', 'scheduled', 'sending')"
        );

        // Completed this month
        $stats['completed_this_month'] = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}chatshop_campaigns 
            WHERE status = 'completed' AND DATE_FORMAT(updated_at, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')"
        );

        // Messages sent
        $stats['messages_sent'] = (int) $wpdb->get_var(
            "SELECT SUM(total_sent) FROM {$wpdb->prefix}chatshop_campaign_stats"
        );

        // Delivery rate
        $delivery_data = $wpdb->get_row(
            "SELECT SUM(total_sent) as total, SUM(successful_sends) as successful 
            FROM {$wpdb->prefix}chatshop_campaign_stats"
        );

        if ($delivery_data && $delivery_data->total > 0) {
            $stats['delivery_rate'] = round(($delivery_data->successful / $delivery_data->total) * 100, 1);
        }

        return $stats;
    }

    /**
     * Get premium features status
     *
     * @since 1.0.0
     * @return array Premium features status
     */
    private function get_premium_features_status()
    {
        return array(
            'has_premium' => get_option('chatshop_license_status', 'free') !== 'free',
            'features' => array(
                'unlimited_contacts' => chatshop_is_premium_feature_available('unlimited_contacts'),
                'unlimited_campaigns' => chatshop_is_premium_feature_available('unlimited_campaigns'),
                'automation' => chatshop_is_premium_feature_available('whatsapp_automation'),
                'analytics' => chatshop_is_premium_feature_available('advanced_analytics'),
                'templates' => chatshop_is_premium_feature_available('whatsapp_templates'),
            ),
        );
    }

    /**
     * Render activity feed
     *
     * @since 1.0.0
     */
    private function render_activity_feed()
    {
        global $wpdb;

        $activities = $wpdb->get_results(
            "SELECT 'campaign' as type, name as title, status, created_at 
            FROM {$wpdb->prefix}chatshop_campaigns 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            UNION ALL
            SELECT 'contact' as type, CONCAT(first_name, ' ', last_name) as title, opt_in_status as status, created_at
            FROM {$wpdb->prefix}chatshop_contacts 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY created_at DESC 
            LIMIT 10"
        );

        if (empty($activities)) {
            echo '<p>' . esc_html__('No recent activity.', 'chatshop') . '</p>';
            return;
        }

        echo '<ul class="chatshop-activity-list">';
        foreach ($activities as $activity) {
            $icon = $activity->type === 'campaign' ? '📢' : '👤';
            $time = human_time_diff(strtotime($activity->created_at), current_time('timestamp')) . ' ' . __('ago', 'chatshop');

            echo '<li>';
            echo '<span class="activity-icon">' . $icon . '</span>';
            echo '<span class="activity-text">';

            if ($activity->type === 'campaign') {
                echo esc_html(sprintf(__('Campaign "%s" was %s', 'chatshop'), $activity->title, $activity->status));
            } else {
                echo esc_html(sprintf(__('Contact "%s" was added (%s)', 'chatshop'), $activity->title, $activity->status));
            }

            echo '</span>';
            echo '<span class="activity-time">' . esc_html($time) . '</span>';
            echo '</li>';
        }
        echo '</ul>';
    }

    /**
     * Get campaign analytics for a specific period
     *
     * @since 1.0.0
     * @param string $period Analytics period
     * @return array Campaign analytics
     */
    private function get_campaign_analytics($period)
    {
        global $wpdb;

        $date_condition = '';
        switch ($period) {
            case '7days':
                $date_condition = "AND cs.send_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                break;
            case '30days':
            default:
                $date_condition = "AND cs.send_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                break;
            case '90days':
                $date_condition = "AND cs.send_date >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
                break;
        }

        $analytics = array(
            'total_campaigns' => 0,
            'total_messages' => 0,
            'success_rate' => 0,
            'engagement_rate' => 0,
            'conversion_rate' => 0,
            'revenue_generated' => 0,
            'daily_breakdown' => array(),
        );

        // Get basic campaign stats
        $stats = $wpdb->get_row(
            "SELECT 
                COUNT(DISTINCT c.id) as total_campaigns,
                SUM(cs.total_sent) as total_messages,
                SUM(cs.successful_sends) as successful_messages
            FROM {$wpdb->prefix}chatshop_campaigns c
            LEFT JOIN {$wpdb->prefix}chatshop_campaign_stats cs ON c.id = cs.campaign_id
            WHERE c.status != 'draft' {$date_condition}"
        );

        if ($stats) {
            $analytics['total_campaigns'] = (int) $stats->total_campaigns;
            $analytics['total_messages'] = (int) $stats->total_messages;

            if ($stats->total_messages > 0) {
                $analytics['success_rate'] = round(($stats->successful_messages / $stats->total_messages) * 100, 2);
            }
        }

        return $analytics;
    }
}
