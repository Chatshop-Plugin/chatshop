<?php

/**
 * Complete Admin Menu Handler - Corrected & Compatible
 *
 * File: admin/class-chatshop-admin-menu.php
 * 
 * Handles WordPress admin menu structure and navigation for ChatShop plugin.
 * Fully compatible with existing codebase and includes contact management integration.
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
 * ChatShop Admin Menu Class
 *
 * Manages the admin menu structure, page routing, and navigation
 * for the ChatShop plugin with integrated contact management.
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

        // Contacts submenu - NEW CONTACT MANAGEMENT
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
            __('Campaigns', 'chatshop') . $this->get_premium_indicator('bulk_messaging'),
            $this->capability,
            'chatshop-campaigns',
            array($this, 'display_campaigns_page')
        );

        // General Settings submenu
        add_submenu_page(
            'chatshop',
            __('General Settings', 'chatshop'),
            __('General Settings', 'chatshop'),
            $this->capability,
            'chatshop-general-settings',
            array($this, 'display_general_settings_page')
        );

        // Payment Settings submenu
        add_submenu_page(
            'chatshop',
            __('Payment Settings', 'chatshop'),
            __('Payment Settings', 'chatshop'),
            $this->capability,
            'chatshop-payments',
            array($this, 'display_payment_settings_page')
        );

        // WhatsApp Settings submenu
        add_submenu_page(
            'chatshop',
            __('WhatsApp Settings', 'chatshop'),
            __('WhatsApp Settings', 'chatshop'),
            $this->capability,
            'chatshop-whatsapp',
            array($this, 'display_whatsapp_settings_page')
        );

        // Analytics submenu
        add_submenu_page(
            'chatshop',
            __('Analytics', 'chatshop'),
            __('Analytics', 'chatshop') . $this->get_premium_indicator('advanced_analytics'),
            $this->capability,
            'chatshop-analytics',
            array($this, 'display_analytics_page')
        );

        // Premium/Support submenu
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
     * Get premium feature indicator
     *
     * @since 1.0.0
     * @param string $feature Feature name
     * @return string Premium indicator HTML
     */
    private function get_premium_indicator($feature)
    {
        // Check if feature is available
        $is_available = function_exists('chatshop_is_premium_feature_available') ?
            chatshop_is_premium_feature_available($feature) : false;

        if ($is_available) {
            return '';
        }

        return ' <span class="chatshop-premium-badge" title="' . esc_attr__('Premium Feature', 'chatshop') . '">PRO</span>';
    }

    /**
     * Get menu icon (SVG)
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
     * Display dashboard page
     *
     * @since 1.0.0
     */
    public function display_dashboard_page()
    {
        $this->render_admin_page('dashboard');
    }

    /**
     * Display contacts page - NEW CONTACT MANAGEMENT
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
     * Display messages page
     *
     * @since 1.0.0
     */
    public function display_messages_page()
    {
        $this->render_admin_page('messages');
    }

    /**
     * Display campaigns page
     *
     * @since 1.0.0
     */
    public function display_campaigns_page()
    {
        $this->render_admin_page('campaigns');
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
     * Display premium page
     *
     * @since 1.0.0
     */
    public function display_premium_page()
    {
        $this->render_admin_page('premium');
    }

    /**
     * Render admin page template
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
     * Render component unavailable page
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
     * Render placeholder page for unimplemented features
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
        $is_premium = in_array($template, array('messages', 'campaigns', 'analytics'));
        $premium_available = function_exists('chatshop_is_premium_feature_available') ?
            chatshop_is_premium_feature_available('advanced_analytics') : false;

    ?>
        <div class="wrap">
            <h1><?php echo esc_html($title); ?></h1>

            <div class="chatshop-placeholder-page">
                <?php if ($is_premium && !$premium_available): ?>
                    <div class="chatshop-premium-feature-placeholder">
                        <div class="premium-feature-icon">
                            <span class="dashicons dashicons-lock"></span>
                        </div>
                        <h2><?php _e('Premium Feature', 'chatshop'); ?></h2>
                        <p><?php _e('This feature is available in the premium version of ChatShop.', 'chatshop'); ?></p>

                        <div class="premium-benefits">
                            <h3><?php _e('What you get with Premium:', 'chatshop'); ?></h3>
                            <ul>
                                <li><span class="dashicons dashicons-yes"></span> <?php _e('Unlimited contacts', 'chatshop'); ?></li>
                                <li><span class="dashicons dashicons-yes"></span> <?php _e('Bulk messaging campaigns', 'chatshop'); ?></li>
                                <li><span class="dashicons dashicons-yes"></span> <?php _e('Advanced analytics and reports', 'chatshop'); ?></li>
                                <li><span class="dashicons dashicons-yes"></span> <?php _e('Import/Export contacts', 'chatshop'); ?></li>
                                <li><span class="dashicons dashicons-yes"></span> <?php _e('Multiple payment gateways', 'chatshop'); ?></li>
                                <li><span class="dashicons dashicons-yes"></span> <?php _e('Priority support', 'chatshop'); ?></li>
                            </ul>
                        </div>

                        <a href="#" class="button button-primary button-hero chatshop-upgrade-btn">
                            <?php _e('Upgrade to Premium', 'chatshop'); ?>
                        </a>
                    </div>
                <?php elseif ($template === 'premium'): ?>
                    <div class="chatshop-premium-info-page">
                        <div class="premium-header">
                            <h2><?php _e('ChatShop Premium', 'chatshop'); ?></h2>
                            <p class="lead"><?php _e('Unlock the full potential of your WhatsApp marketing with advanced features and unlimited capabilities.', 'chatshop'); ?></p>
                        </div>

                        <div class="premium-features-grid">
                            <div class="feature-card">
                                <div class="feature-icon">
                                    <span class="dashicons dashicons-groups"></span>
                                </div>
                                <h3><?php _e('Unlimited Contacts', 'chatshop'); ?></h3>
                                <p><?php _e('Add unlimited contacts to your WhatsApp marketing campaigns without monthly restrictions.', 'chatshop'); ?></p>
                            </div>

                            <div class="feature-card">
                                <div class="feature-icon">
                                    <span class="dashicons dashicons-email-alt"></span>
                                </div>
                                <h3><?php _e('Bulk Messaging', 'chatshop'); ?></h3>
                                <p><?php _e('Send personalized messages to multiple contacts simultaneously with advanced targeting options.', 'chatshop'); ?></p>
                            </div>

                            <div class="feature-card">
                                <div class="feature-icon">
                                    <span class="dashicons dashicons-chart-bar"></span>
                                </div>
                                <h3><?php _e('Advanced Analytics', 'chatshop'); ?></h3>
                                <p><?php _e('Get detailed insights into your campaign performance, conversion rates, and revenue attribution.', 'chatshop'); ?></p>
                            </div>

                            <div class="feature-card">
                                <div class="feature-icon">
                                    <span class="dashicons dashicons-upload"></span>
                                </div>
                                <h3><?php _e('Import/Export', 'chatshop'); ?></h3>
                                <p><?php _e('Easily import contacts from CSV/Excel files and export your data for backup or analysis.', 'chatshop'); ?></p>
                            </div>

                            <div class="feature-card">
                                <div class="feature-icon">
                                    <span class="dashicons dashicons-money-alt"></span>
                                </div>
                                <h3><?php _e('Multiple Gateways', 'chatshop'); ?></h3>
                                <p><?php _e('Accept payments through multiple gateways including PayPal, Flutterwave, and Razorpay.', 'chatshop'); ?></p>
                            </div>

                            <div class="feature-card">
                                <div class="feature-icon">
                                    <span class="dashicons dashicons-sos"></span>
                                </div>
                                <h3><?php _e('Priority Support', 'chatshop'); ?></h3>
                                <p><?php _e('Get priority email and chat support to help you maximize your WhatsApp marketing success.', 'chatshop'); ?></p>
                            </div>
                        </div>

                        <div class="premium-pricing">
                            <div class="pricing-card">
                                <h3><?php _e('ChatShop Premium', 'chatshop'); ?></h3>
                                <div class="price">
                                    <span class="currency">$</span>
                                    <span class="amount">49</span>
                                    <span class="period">/year</span>
                                </div>
                                <ul class="pricing-features">
                                    <li><?php _e('All premium features included', 'chatshop'); ?></li>
                                    <li><?php _e('Unlimited contacts and messages', 'chatshop'); ?></li>
                                    <li><?php _e('Advanced analytics and reporting', 'chatshop'); ?></li>
                                    <li><?php _e('Priority customer support', 'chatshop'); ?></li>
                                    <li><?php _e('Regular updates and new features', 'chatshop'); ?></li>
                                </ul>
                                <a href="#" class="button button-primary button-hero chatshop-upgrade-btn">
                                    <?php _e('Upgrade Now', 'chatshop'); ?>
                                </a>
                            </div>
                        </div>

                        <div class="support-section">
                            <h3><?php _e('Need Help?', 'chatshop'); ?></h3>
                            <p><?php _e('Our support team is here to help you get the most out of ChatShop.', 'chatshop'); ?></p>

                            <div class="support-options">
                                <a href="#" class="button">
                                    <span class="dashicons dashicons-book"></span>
                                    <?php _e('Documentation', 'chatshop'); ?>
                                </a>
                                <a href="#" class="button">
                                    <span class="dashicons dashicons-video-alt3"></span>
                                    <?php _e('Video Tutorials', 'chatshop'); ?>
                                </a>
                                <a href="#" class="button">
                                    <span class="dashicons dashicons-email"></span>
                                    <?php _e('Contact Support', 'chatshop'); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="chatshop-coming-soon">
                        <div class="coming-soon-icon">
                            <span class="dashicons dashicons-hammer"></span>
                        </div>
                        <h2><?php _e('Coming Soon', 'chatshop'); ?></h2>
                        <p><?php _e('This feature is currently under development and will be available in a future update.', 'chatshop'); ?></p>

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
     * Add inline styles for placeholder pages
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

            .premium-benefits ul {
                text-align: left;
                max-width: 400px;
                margin: 20px auto;
            }

            .premium-benefits li {
                margin-bottom: 10px;
            }

            .premium-benefits .dashicons-yes {
                color: #27ae60;
            }

            .chatshop-premium-info-page {
                max-width: 1200px;
                margin: 0 auto;
            }

            .premium-header {
                text-align: center;
                margin-bottom: 50px;
            }

            .premium-header .lead {
                font-size: 18px;
                color: #666;
                margin-top: 10px;
            }

            .premium-features-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 30px;
                margin-bottom: 50px;
            }

            .feature-card {
                background: #fff;
                padding: 30px;
                border: 1px solid #ddd;
                border-radius: 8px;
                text-align: center;
                transition: transform 0.2s, box-shadow 0.2s;
            }

            .feature-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            }

            .feature-icon {
                font-size: 48px;
                color: #0073aa;
                margin-bottom: 20px;
            }

            .feature-card h3 {
                margin-bottom: 15px;
                color: #333;
            }

            .feature-card p {
                color: #666;
                line-height: 1.6;
            }

            .premium-pricing {
                text-align: center;
                margin-bottom: 50px;
            }

            .pricing-card {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 40px;
                border-radius: 12px;
                max-width: 400px;
                margin: 0 auto;
            }

            .price {
                font-size: 48px;
                font-weight: bold;
                margin: 20px 0;
            }

            .price .currency,
            .price .period {
                font-size: 24px;
                opacity: 0.8;
            }

            .pricing-features {
                list-style: none;
                padding: 0;
                margin: 30px 0;
            }

            .pricing-features li {
                padding: 8px 0;
                border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            }

            .pricing-features li:last-child {
                border-bottom: none;
            }

            .support-section {
                background: #f8f9fa;
                padding: 40px;
                border-radius: 8px;
                text-align: center;
            }

            .support-options {
                margin-top: 20px;
            }

            .support-options .button {
                margin: 0 10px;
            }

            .chatshop-coming-soon {
                max-width: 500px;
                margin: 0 auto;
            }

            .coming-soon-icon {
                font-size: 64px;
                color: #0073aa;
                margin-bottom: 20px;
            }

            .development-status {
                margin-top: 40px;
                padding: 20px;
                background: #f0f8ff;
                border-radius: 8px;
            }

            .progress-bar {
                background: #e0e0e0;
                border-radius: 10px;
                height: 20px;
                margin: 15px 0;
                overflow: hidden;
            }

            .progress-fill {
                background: linear-gradient(90deg, #4CAF50, #45a049);
                height: 100%;
                transition: width 0.3s ease;
            }

            .chatshop-upgrade-btn {
                position: relative;
                overflow: hidden;
            }

            .chatshop-upgrade-btn:before {
                content: '';
                position: absolute;
                top: 0;
                left: -100%;
                width: 100%;
                height: 100%;
                background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
                transition: left 0.5s;
            }

            .chatshop-upgrade-btn:hover:before {
                left: 100%;
            }

            @media (max-width: 768px) {
                .premium-features-grid {
                    grid-template-columns: 1fr;
                }

                .support-options .button {
                    display: block;
                    margin: 10px 0;
                }

                .chatshop-placeholder-page {
                    padding: 40px 15px;
                }

                .chatshop-premium-feature-placeholder {
                    padding: 30px 20px;
                }
            }
        </style>
<?php
    }

    /**
     * Enqueue admin assets
     *
     * @since 1.0.0
     * @param string $hook Current admin page hook
     */
    public function enqueue_admin_assets($hook)
    {
        // Only load on our admin pages
        if (strpos($hook, 'chatshop') === false) {
            return;
        }

        // Enqueue contact management assets if on contacts page
        if (strpos($hook, 'chatshop-contacts') !== false) {
            $this->enqueue_contacts_assets($hook);
        }

        // Add menu-specific styles
        wp_add_inline_style('wp-admin', '
            .chatshop-premium-badge {
                background: linear-gradient(135deg, #f39c12, #e67e22);
                color: white;
                font-size: 9px;
                font-weight: bold;
                padding: 2px 6px;
                border-radius: 10px;
                margin-left: 5px;
                display: inline-block;
                vertical-align: top;
                line-height: 1.2;
                animation: chatshop-pulse 2s infinite;
            }
            
            @keyframes chatshop-pulse {
                0%, 100% { opacity: 1; }
                50% { opacity: 0.7; }
            }
            
            .wp-submenu a:hover .chatshop-premium-badge {
                background: linear-gradient(135deg, #e67e22, #d35400);
            }
            
            #adminmenu .wp-submenu .chatshop-premium-badge {
                margin-left: 8px;
            }
        ');
    }

    /**
     * Enqueue contact management assets
     *
     * @since 1.0.0
     * @param string $hook Current admin page hook
     */
    private function enqueue_contacts_assets($hook)
    {
        // Enqueue contact management JavaScript
        wp_enqueue_script(
            'chatshop-contacts',
            CHATSHOP_PLUGIN_URL . 'admin/js/chatshop-contacts.js',
            array('jquery', 'wp-util'),
            CHATSHOP_VERSION,
            true
        );

        // Get contact manager for stats
        $contact_manager = function_exists('chatshop_get_component') ?
            chatshop_get_component('contact_manager') : null;

        $monthly_usage = 0;
        $monthly_limit = 20;
        $premium_features = array();

        if ($contact_manager) {
            $monthly_usage = method_exists($contact_manager, 'get_monthly_contact_count') ?
                $contact_manager->get_monthly_contact_count() : 0;
            $monthly_limit = method_exists($contact_manager, 'get_free_contact_limit') ?
                $contact_manager->get_free_contact_limit() : 20;
        }

        if (function_exists('chatshop') && method_exists(chatshop(), 'get_premium_features')) {
            $premium_features = chatshop()->get_premium_features();
        }

        // Localize script for contact management
        wp_localize_script('chatshop-contacts', 'chatshopContactsL10n', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('chatshop_admin_nonce'),
            'monthlyUsage' => $monthly_usage,
            'monthlyLimit' => $monthly_limit,
            'premiumFeatures' => $premium_features,
            'strings' => array(
                'addContact' => __('Add Contact', 'chatshop'),
                'editContact' => __('Edit Contact', 'chatshop'),
                'updateContact' => __('Update Contact', 'chatshop'),
                'saving' => __('Saving...', 'chatshop'),
                'confirmDelete' => __('Are you sure you want to delete "%s"?', 'chatshop'),
                'confirmBulkDelete' => __('Are you sure you want to delete %d contacts?', 'chatshop'),
                'confirmBulkActivate' => __('Are you sure you want to activate %d contacts?', 'chatshop'),
                'confirmBulkDeactivate' => __('Are you sure you want to deactivate %d contacts?', 'chatshop'),
                'selectBulkAction' => __('Please select a bulk action.', 'chatshop'),
                'selectContacts' => __('Please select contacts to perform bulk action.', 'chatshop'),
                'selectFile' => __('Please select a file to import.', 'chatshop'),
                'phoneRequired' => __('Phone number is required.', 'chatshop'),
                'nameRequired' => __('Name is required.', 'chatshop'),
                'invalidPhone' => __('Please enter a valid phone number.', 'chatshop'),
                'invalidEmail' => __('Please enter a valid email address.', 'chatshop'),
                'errorGeneric' => __('An error occurred. Please try again.', 'chatshop'),
                'importedCount' => __('%d contacts imported successfully.', 'chatshop'),
                'skippedCount' => __('%d contacts skipped (already exist).', 'chatshop'),
                'failedCount' => __('%d contacts failed to import.', 'chatshop'),
                'showErrors' => __('Show import errors', 'chatshop'),
                'refreshPage' => __('Refresh Page', 'chatshop'),
                'unlimitedContactsTitle' => __('Unlimited Contacts', 'chatshop'),
                'unlimitedContactsDesc' => __('Upgrade to premium to add unlimited contacts to your WhatsApp marketing campaigns.', 'chatshop'),
                'importExportTitle' => __('Import/Export Contacts', 'chatshop'),
                'importExportDesc' => __('Import contacts from CSV/Excel files and export your contact list for backup or analysis.', 'chatshop'),
                'premiumFeatureTitle' => __('Premium Feature', 'chatshop'),
                'premiumFeatureDesc' => __('This feature is available in the premium version of ChatShop.', 'chatshop')
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
     * Get current page slug for menu highlighting
     *
     * @since 1.0.0
     * @return string Current page slug
     */
    private function get_current_page_slug()
    {
        global $pagenow;

        if ($pagenow === 'admin.php' && isset($_GET['page'])) {
            return sanitize_text_field($_GET['page']);
        }

        return '';
    }

    /**
     * Check if current user can access ChatShop admin
     *
     * @since 1.0.0
     * @return bool Access permission
     */
    private function current_user_can_access_chatshop()
    {
        return current_user_can($this->capability);
    }

    /**
     * Add admin body classes for ChatShop pages
     *
     * @since 1.0.0
     * @param string $classes Existing body classes
     * @return string Modified body classes
     */
    public function add_admin_body_classes($classes)
    {
        $current_page = $this->get_current_page_slug();

        if (strpos($current_page, 'chatshop') === 0) {
            $classes .= ' chatshop-admin-page';

            // Add specific page class
            $page_class = str_replace('chatshop-', '', $current_page);
            $classes .= ' chatshop-page-' . $page_class;
        }

        return $classes;
    }

    /**
     * Add admin notices for ChatShop pages
     *
     * @since 1.0.0
     */
    public function add_admin_notices()
    {
        $current_page = $this->get_current_page_slug();

        if (strpos($current_page, 'chatshop') !== 0) {
            return;
        }

        // Check if contact manager is available for contact-related notices
        if ($current_page === 'chatshop-contacts') {
            $contact_manager = function_exists('chatshop_get_component') ?
                chatshop_get_component('contact_manager') : null;

            if (!$contact_manager) {
                echo '<div class="notice notice-warning is-dismissible">';
                echo '<p>' . __('Contact management component is not properly initialized. Some features may not work correctly.', 'chatshop') . '</p>';
                echo '</div>';
                return;
            }

            // Check contact limit warning
            if (
                method_exists($contact_manager, 'get_monthly_contact_count') &&
                method_exists($contact_manager, 'get_free_contact_limit')
            ) {

                $usage = $contact_manager->get_monthly_contact_count();
                $limit = $contact_manager->get_free_contact_limit();
                $is_premium = function_exists('chatshop_is_premium_feature_available') ?
                    chatshop_is_premium_feature_available('unlimited_contacts') : false;

                if (!$is_premium && $usage >= $limit * 0.8) {
                    $percentage = round(($usage / $limit) * 100);
                    echo '<div class="notice notice-warning is-dismissible">';
                    echo '<p>' . sprintf(
                        __('You have used %d%% of your monthly contact limit (%d/%d). <a href="%s">Upgrade to premium</a> for unlimited contacts.', 'chatshop'),
                        $percentage,
                        $usage,
                        $limit,
                        admin_url('admin.php?page=chatshop-premium')
                    ) . '</p>';
                    echo '</div>';
                }
            }
        }

        // General ChatShop notices
        $this->add_general_chatshop_notices();
    }

    /**
     * Add general ChatShop admin notices
     *
     * @since 1.0.0
     */
    private function add_general_chatshop_notices()
    {
        // Welcome message for new installations
        if (get_transient('chatshop_show_welcome_notice')) {
            echo '<div class="notice notice-info is-dismissible chatshop-welcome-notice">';
            echo '<h3>' . __('Welcome to ChatShop!', 'chatshop') . '</h3>';
            echo '<p>' . __('Thank you for installing ChatShop. Get started by configuring your payment gateways and WhatsApp settings.', 'chatshop') . '</p>';
            echo '<p>';
            echo '<a href="' . admin_url('admin.php?page=chatshop-payments') . '" class="button button-primary">' . __('Setup Payments', 'chatshop') . '</a> ';
            echo '<a href="' . admin_url('admin.php?page=chatshop-whatsapp') . '" class="button">' . __('Configure WhatsApp', 'chatshop') . '</a>';
            echo '</p>';
            echo '</div>';

            // Clear the transient after showing
            delete_transient('chatshop_show_welcome_notice');
        }
    }

    /**
     * Handle AJAX request to dismiss admin notices
     *
     * @since 1.0.0
     */
    public function handle_dismiss_notice()
    {
        check_ajax_referer('chatshop_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions.', 'chatshop'));
        }

        $notice_id = sanitize_text_field($_POST['notice_id'] ?? '');

        if ($notice_id) {
            update_user_meta(get_current_user_id(), "chatshop_dismissed_notice_{$notice_id}", true);
        }

        wp_send_json_success();
    }

    /**
     * Initialize admin menu hooks
     *
     * @since 1.0.0
     */
    public function init_admin_hooks()
    {
        // Add body classes for ChatShop pages
        add_filter('admin_body_class', array($this, 'add_admin_body_classes'));

        // Add admin notices
        add_action('admin_notices', array($this, 'add_admin_notices'));

        // Handle AJAX notice dismissal
        add_action('wp_ajax_chatshop_dismiss_notice', array($this, 'handle_dismiss_notice'));
    }
}

// Initialize admin hooks when the class is instantiated
add_action('admin_init', function () {
    if (class_exists('ChatShop\ChatShop_Admin_Menu')) {
        $admin_menu = new ChatShop_Admin_Menu();
        $admin_menu->init_admin_hooks();
    }
});
