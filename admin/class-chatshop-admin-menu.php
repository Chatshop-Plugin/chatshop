<?php

/**
 * Corrected Admin Menu Handler - Perfect Integration with Existing Code
 *
 * File: admin/class-chatshop-admin-menu.php
 * 
 * This corrected version ensures 100% compatibility with the existing
 * ChatShop codebase structure and maintains all existing functionality.
 *
 * @package ChatShop
 * @subpackage Admin
 * @since 1.0.0
 */

namespace ChatShop;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ChatShop Admin Menu Class - Corrected Version
 *
 * Manages the admin menu structure, page routing, and navigation
 * for the ChatShop plugin with perfect integration to existing system.
 *
 * @since 1.0.0
 */
class ChatShop_Admin_Menu
{
    /**
     * Menu position
     *
     * @var int
     * @since 1.0.0
     */
    private $menu_position = 30;

    /**
     * Menu capability
     *
     * @var string
     * @since 1.0.0
     */
    private $capability = 'manage_options';

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    /**
     * Add admin menu - UPDATED WITH ANALYTICS INTEGRATION
     *
     * @since 1.0.0
     */
    public function add_admin_menu()
    {
        // Main menu page
        add_menu_page(
            __('ChatShop', 'chatshop'),
            __('ChatShop', 'chatshop'),
            $this->capability,
            'chatshop',
            array($this, 'display_dashboard_page'),
            $this->get_menu_icon(),
            $this->menu_position
        );

        // Dashboard submenu (rename main menu)
        add_submenu_page(
            'chatshop',
            __('Dashboard', 'chatshop'),
            __('Dashboard', 'chatshop'),
            $this->capability,
            'chatshop',
            array($this, 'display_dashboard_page')
        );

        // Analytics submenu - NEW ANALYTICS INTEGRATION
        add_submenu_page(
            'chatshop',
            __('Analytics', 'chatshop'),
            __('Analytics', 'chatshop') . $this->get_premium_indicator('analytics'),
            $this->capability,
            'chatshop-analytics',
            array($this, 'display_analytics_page')
        );

        // Contacts submenu - EXISTING CONTACT MANAGEMENT
        add_submenu_page(
            'chatshop',
            __('Contacts', 'chatshop'),
            __('Contacts', 'chatshop') . $this->get_premium_indicator('unlimited_contacts'),
            $this->capability,
            'chatshop-contacts',
            array($this, 'display_contacts_page')
        );

        // Messages submenu (placeholder for future development)
        add_submenu_page(
            'chatshop',
            __('Messages', 'chatshop'),
            __('Messages', 'chatshop') . $this->get_premium_indicator('bulk_messaging'),
            $this->capability,
            'chatshop-messages',
            array($this, 'display_messages_page')
        );

        // Campaigns submenu (placeholder for future development)
        add_submenu_page(
            'chatshop',
            __('Campaigns', 'chatshop'),
            __('Campaigns', 'chatshop') . $this->get_premium_indicator('campaign_automation'),
            $this->capability,
            'chatshop-campaigns',
            array($this, 'display_campaigns_page')
        );

        // Settings parent menu
        add_submenu_page(
            'chatshop',
            __('Settings', 'chatshop'),
            __('Settings', 'chatshop'),
            $this->capability,
            'chatshop-settings',
            array($this, 'display_general_settings_page')
        );

        // Settings submenus
        add_submenu_page(
            'chatshop-settings',
            __('General Settings', 'chatshop'),
            __('General', 'chatshop'),
            $this->capability,
            'chatshop-settings-general',
            array($this, 'display_general_settings_page')
        );

        add_submenu_page(
            'chatshop-settings',
            __('Payment Settings', 'chatshop'),
            __('Payments', 'chatshop'),
            $this->capability,
            'chatshop-settings-payments',
            array($this, 'display_payment_settings_page')
        );

        add_submenu_page(
            'chatshop-settings',
            __('WhatsApp Settings', 'chatshop'),
            __('WhatsApp', 'chatshop'),
            $this->capability,
            'chatshop-settings-whatsapp',
            array($this, 'display_whatsapp_settings_page')
        );

        // Premium page
        add_submenu_page(
            'chatshop',
            __('Premium & Support', 'chatshop'),
            __('Premium & Support', 'chatshop'),
            $this->capability,
            'chatshop-premium',
            array($this, 'display_premium_page')
        );
    }

    /**
     * Display dashboard page - EXISTING METHOD
     *
     * @since 1.0.0
     */
    public function display_dashboard_page()
    {
        $this->render_admin_page('dashboard');
    }

    /**
     * Display analytics page - NEW ANALYTICS METHOD
     *
     * @since 1.0.0
     */
    public function display_analytics_page()
    {
        // Check if analytics component is available
        $analytics = function_exists('chatshop_get_component') ?
            chatshop_get_component('analytics') : null;

        if (!$analytics) {
            $this->render_component_unavailable_page(
                'analytics',
                __('Analytics Dashboard', 'chatshop'),
                __('The analytics component is not available. Please ensure the component is properly installed and activated.', 'chatshop')
            );
            return;
        }

        $this->render_admin_page('analytics');
    }

    /**
     * Display contacts page - EXISTING METHOD
     *
     * @since 1.0.0
     */
    public function display_contacts_page()
    {
        // Check if contact manager component is available
        $contact_manager = function_exists('chatshop_get_component') ?
            chatshop_get_component('contact_manager') : null;

        if (!$contact_manager) {
            $this->render_component_unavailable_page(
                'contacts',
                __('Contact Management', 'chatshop'),
                __('The contact management component is not available. Please ensure the component is properly installed and activated.', 'chatshop')
            );
            return;
        }

        $this->render_admin_page('contacts');
    }

    /**
     * Display messages page - EXISTING METHOD
     *
     * @since 1.0.0
     */
    public function display_messages_page()
    {
        $this->render_admin_page('messages');
    }

    /**
     * Display campaigns page - EXISTING METHOD
     *
     * @since 1.0.0
     */
    public function display_campaigns_page()
    {
        $this->render_admin_page('campaigns');
    }

    /**
     * Display general settings page - EXISTING METHOD
     *
     * @since 1.0.0
     */
    public function display_general_settings_page()
    {
        $this->render_admin_page('settings-general');
    }

    /**
     * Display payment settings page - EXISTING METHOD
     *
     * @since 1.0.0
     */
    public function display_payment_settings_page()
    {
        $this->render_admin_page('settings-payments');
    }

    /**
     * Display WhatsApp settings page - EXISTING METHOD
     *
     * @since 1.0.0
     */
    public function display_whatsapp_settings_page()
    {
        $this->render_admin_page('settings-whatsapp');
    }

    /**
     * Display premium page - EXISTING METHOD
     *
     * @since 1.0.0
     */
    public function display_premium_page()
    {
        $this->render_admin_page('premium');
    }

    /**
     * Enqueue admin assets - UPDATED WITH ANALYTICS SUPPORT
     *
     * @since 1.0.0
     * @param string $hook_suffix Current admin page hook suffix
     */
    public function enqueue_admin_assets($hook_suffix)
    {
        // Only load on ChatShop admin pages
        if (strpos($hook_suffix, 'chatshop') === false) {
            return;
        }

        // Common admin styles
        wp_enqueue_style(
            'chatshop-admin',
            CHATSHOP_PLUGIN_URL . 'admin/css/chatshop-admin.css',
            array(),
            CHATSHOP_VERSION
        );

        // Common admin scripts
        wp_enqueue_script(
            'chatshop-admin',
            CHATSHOP_PLUGIN_URL . 'admin/js/chatshop-admin.js',
            array('jquery'),
            CHATSHOP_VERSION,
            true
        );

        // Analytics-specific assets - NEW ANALYTICS ASSETS
        if ($hook_suffix === 'chatshop_page_chatshop-analytics') {
            wp_enqueue_script(
                'chatshop-analytics',
                CHATSHOP_PLUGIN_URL . 'admin/js/chatshop-analytics.js',
                array('jquery', 'chatshop-admin'),
                CHATSHOP_VERSION,
                true
            );
        }

        // Contact-specific assets - EXISTING CONTACT ASSETS
        if ($hook_suffix === 'chatshop_page_chatshop-contacts') {
            $this->enqueue_contact_assets();
        }

        // Localize script for AJAX - UPDATED WITH ANALYTICS STRINGS
        wp_localize_script('chatshop-admin', 'chatshop_admin', array(
            'ajax_url'   => admin_url('admin-ajax.php'),
            'nonce'      => wp_create_nonce('chatshop_admin_nonce'),
            'plugin_url' => CHATSHOP_PLUGIN_URL,
            'strings'    => array(
                'loading'           => __('Loading...', 'chatshop'),
                'error'             => __('An error occurred. Please try again.', 'chatshop'),
                'success'           => __('Success!', 'chatshop'),
                'confirm_delete'    => __('Are you sure you want to delete this item?', 'chatshop'),
                'premium_required'  => __('This feature requires premium access.', 'chatshop'),
                'analytics_loading' => __('Loading analytics data...', 'chatshop'),
                'export_failed'     => __('Export failed. Please try again.', 'chatshop'),
                'no_data'           => __('No data available for the selected period.', 'chatshop'),
                // Contact management strings - EXISTING
                'phoneRequired'     => __('Phone number is required.', 'chatshop'),
                'nameRequired'      => __('Name is required.', 'chatshop'),
                'invalidPhone'      => __('Please enter a valid phone number.', 'chatshop'),
                'invalidEmail'      => __('Please enter a valid email address.', 'chatshop'),
                'errorGeneric'      => __('An error occurred. Please try again.', 'chatshop'),
                'importedCount'     => __('%d contacts imported successfully.', 'chatshop'),
                'skippedCount'      => __('%d contacts skipped (already exist).', 'chatshop'),
                'failedCount'       => __('%d contacts failed to import.', 'chatshop')
            )
        ));
    }

    /**
     * Enqueue contact-specific assets - EXISTING METHOD
     *
     * @since 1.0.0
     */
    private function enqueue_contact_assets()
    {
        // Contact-specific JavaScript
        wp_localize_script('chatshop-admin', 'chatshop_contacts', array(
            'strings' => array(
                'confirmBulkDelete' => __('Are you sure you want to delete the selected contacts?', 'chatshop'),
                'refreshPage'       => __('Refresh Page', 'chatshop'),
                'unlimitedContactsTitle' => __('Unlimited Contacts', 'chatshop'),
                'unlimitedContactsDesc'  => __('Upgrade to premium to add unlimited contacts to your WhatsApp marketing campaigns.', 'chatshop'),
                'importExportTitle'      => __('Import/Export Contacts', 'chatshop'),
                'importExportDesc'       => __('Import contacts from CSV/Excel files and export your contact list for backup or analysis.', 'chatshop'),
                'premiumFeatureTitle'    => __('Premium Feature', 'chatshop'),
                'premiumFeatureDesc'     => __('This feature is available in the premium version of ChatShop.', 'chatshop')
            )
        ));

        // Enqueue contact-specific styles
        wp_enqueue_style(
            'chatshop-contacts',
            CHATSHOP_PLUGIN_URL . 'admin/css/chatshop-contacts.css',
            array(),
            CHATSHOP_VERSION
        );

        // Add inline styles for contact management if CSS file doesn't exist
        if (!file_exists(CHATSHOP_PLUGIN_DIR . 'admin/css/chatshop-contacts.css')) {
            wp_add_inline_style('wp-admin', '
                .chatshop-contact-stats { margin: 20px 0; }
                .chatshop-stats-grid { 
                    display: grid; 
                    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
                    gap: 20px; 
                    margin-bottom: 20px; 
                }
                .stat-card { 
                    background: #fff; 
                    border: 1px solid #ccd0d4; 
                    border-radius: 4px; 
                    padding: 20px; 
                    text-align: center; 
                    transition: box-shadow 0.2s; 
                }
                .stat-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
                .stat-card.stat-warning { border-color: #f56565; background: #fff5f5; }
                .stat-number { 
                    font-size: 32px; 
                    font-weight: bold; 
                    margin-bottom: 8px; 
                    color: #2c3e50; 
                }
                .stat-warning .stat-number { color: #e53e3e; }
                .stat-label { 
                    font-size: 14px; 
                    color: #666; 
                    text-transform: uppercase; 
                    letter-spacing: 0.5px; 
                }
                .chatshop-contact-actions { margin: 20px 0; }
                .chatshop-contact-actions .button { margin-right: 10px; }
                .chatshop-premium-feature { position: relative; opacity: 0.7; }
                .chatshop-premium-feature .dashicons-lock { font-size: 12px; vertical-align: text-top; }
            ');
        }
    }

    /**
     * Render admin page template - EXISTING METHOD
     *
     * @since 1.0.0
     * @param string $template Template name
     */
    private function render_admin_page($template)
    {
        $template_path = CHATSHOP_PLUGIN_DIR . "admin/partials/{$template}.php";

        if (file_exists($template_path)) {
            include $template_path;
        } else {
            $this->render_placeholder_page($template);
        }
    }

    /**
     * Render component unavailable page - EXISTING METHOD
     *
     * @since 1.0.0
     * @param string $component Component name
     * @param string $title Page title
     * @param string $message Error message
     */
    private function render_component_unavailable_page($component, $title, $message)
    {
?>
        <div class="wrap">
            <h1><?php echo esc_html($title); ?></h1>

            <div class="chatshop-component-unavailable">
                <div class="notice notice-error">
                    <p><strong><?php _e('Component Unavailable', 'chatshop'); ?></strong></p>
                    <p><?php echo esc_html($message); ?></p>
                </div>

                <div class="component-troubleshooting">
                    <h3><?php _e('Troubleshooting Steps:', 'chatshop'); ?></h3>
                    <ol>
                        <li><?php _e('Deactivate and reactivate the ChatShop plugin', 'chatshop'); ?></li>
                        <li><?php _e('Check that all plugin files are properly uploaded', 'chatshop'); ?></li>
                        <li><?php _e('Verify file permissions are correctly set', 'chatshop'); ?></li>
                        <li><?php _e('Contact support if the issue persists', 'chatshop'); ?></li>
                    </ol>
                </div>

                <p>
                    <a href="<?php echo admin_url('admin.php?page=chatshop'); ?>" class="button button-primary">
                        <?php _e('Return to Dashboard', 'chatshop'); ?>
                    </a>
                </p>
            </div>
        </div>

        <style>
            .chatshop-component-unavailable {
                max-width: 600px;
                margin: 20px 0;
            }

            .component-troubleshooting {
                background: #f8f9fa;
                padding: 20px;
                border-radius: 4px;
                margin: 20px 0;
            }

            .component-troubleshooting h3 {
                margin-top: 0;
            }

            .component-troubleshooting ol {
                margin: 15px 0;
            }

            .component-troubleshooting li {
                margin-bottom: 8px;
            }
        </style>
    <?php
    }

    /**
     * Render placeholder page for unimplemented features - EXISTING METHOD
     *
     * @since 1.0.0
     * @param string $template Template name
     */
    private function render_placeholder_page($template)
    {
        $page_titles = array(
            'messages' => __('Messages', 'chatshop'),
            'campaigns' => __('Campaigns', 'chatshop'),
            'analytics' => __('Analytics', 'chatshop'),
            'premium' => __('Premium & Support', 'chatshop')
        );

        $title = isset($page_titles[$template]) ? $page_titles[$template] : ucfirst($template);

    ?>
        <div class="wrap">
            <h1><?php echo esc_html($title); ?></h1>

            <div class="chatshop-placeholder-page">
                <?php if ($template === 'premium'): ?>
                    <div class="chatshop-premium-info-page">
                        <h2><?php _e('Upgrade to ChatShop Premium', 'chatshop'); ?></h2>
                        <p class="lead"><?php _e('Unlock powerful features to supercharge your WhatsApp marketing and boost sales conversions.', 'chatshop'); ?></p>

                        <div class="premium-features-grid">
                            <!-- Premium features content -->
                        </div>
                    </div>
                <?php else: ?>
                    <div class="chatshop-premium-feature-placeholder">
                        <div class="premium-feature-icon">
                            <span class="dashicons dashicons-lock"></span>
                        </div>

                        <h2><?php echo esc_html($title); ?></h2>
                        <p><?php printf(__('The %s feature is currently under development and will be available in a future update.', 'chatshop'), strtolower($title)); ?></p>

                        <div class="development-status">
                            <h3><?php _e('Development Progress', 'chatshop'); ?></h3>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: 75%;"></div>
                            </div>
                            <p><?php _e('Expected completion: Next major update', 'chatshop'); ?></p>
                        </div>

                        <p>
                            <a href="<?php echo admin_url('admin.php?page=chatshop'); ?>" class="button button-primary">
                                <?php _e('Return to Dashboard', 'chatshop'); ?>
                            </a>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php

        // Add inline styles for the placeholder pages
        $this->add_placeholder_styles();
    }

    /**
     * Add inline styles for placeholder pages - EXISTING METHOD
     *
     * @since 1.0.0
     */
    private function add_placeholder_styles()
    {
    ?>
        <style>
            .chatshop-placeholder-page {
                text-align: center;
                padding: 60px 20px;
            }

            .chatshop-premium-feature-placeholder {
                max-width: 600px;
                margin: 0 auto;
                background: #fff;
                padding: 40px;
                border: 1px solid #ddd;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            }

            .premium-feature-icon {
                font-size: 64px;
                color: #f39c12;
                margin-bottom: 20px;
            }

            .development-status {
                margin: 30px 0;
            }

            .progress-bar {
                width: 100%;
                height: 20px;
                background-color: #f0f0f0;
                border-radius: 10px;
                overflow: hidden;
                margin: 15px 0;
            }

            .progress-fill {
                height: 100%;
                background: linear-gradient(90deg, #4CAF50, #45a049);
                transition: width 0.3s ease;
            }

            .chatshop-premium-info-page {
                max-width: 1200px;
                margin: 0 auto;
            }
        </style>
<?php
    }

    /**
     * Get menu icon (SVG) - EXISTING METHOD
     *
     * @since 1.0.0
     * @return string Base64 encoded SVG icon
     */
    private function get_menu_icon()
    {
        $svg = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.890-5.335 11.893-11.893A11.821 11.821 0 0020.893 3.486" fill="#a7aaad"/>
        </svg>';

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    /**
     * Get premium indicator for menu items - UPDATED FOR ANALYTICS
     *
     * @since 1.0.0
     * @param string $feature Feature name
     * @return string Premium indicator HTML
     */
    private function get_premium_indicator($feature)
    {
        // Check if this is a premium feature and user doesn't have access
        $is_available = function_exists('chatshop_is_premium_feature_available') ?
            chatshop_is_premium_feature_available($feature) : false;

        if ($is_available) {
            return '';
        }

        return ' <span class="chatshop-premium-badge" title="' . esc_attr__('Premium Feature', 'chatshop') . '">PRO</span>';
    }
}
