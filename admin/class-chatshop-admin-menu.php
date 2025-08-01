<?php

/**
 * Admin Menu Management - CONSOLIDATED VERSION
 *
 * File: admin/class-chatshop-admin-menu.php
 * 
 * Consolidated admin menu registration system that handles all ChatShop
 * admin pages with proper component checking and error handling.
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
 * ChatShop Admin Menu Class - CONSOLIDATED VERSION
 *
 * Manages all admin menu registration, page rendering, and component validation
 * in a single, consistent system with enhanced error handling.
 *
 * @since 1.0.0
 */
class ChatShop_Admin_Menu
{
    /**
     * Menu capability requirement
     *
     * @var string
     * @since 1.0.0
     */
    private $capability = 'manage_options';

    /**
     * Component loader instance
     *
     * @var ChatShop_Component_Loader
     * @since 1.0.0
     */
    private $component_loader;

    /**
     * Loading errors for display
     *
     * @var array
     * @since 1.0.0
     */
    private $component_errors = array();

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        $this->init_hooks();
        $this->get_component_loader();
    }

    /**
     * Initialize WordPress hooks
     *
     * @since 1.0.0
     */
    private function init_hooks()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('admin_notices', array($this, 'display_component_notices'));
    }

    /**
     * Get component loader instance
     *
     * @since 1.0.0
     */
    private function get_component_loader()
    {
        if (function_exists('chatshop') && chatshop()) {
            $this->component_loader = chatshop()->get_component_loader();

            if ($this->component_loader) {
                $this->component_errors = $this->component_loader->get_loading_errors();
            }
        }
    }

    /**
     * Add admin menu with consolidated structure
     *
     * @since 1.0.0
     */
    public function add_admin_menu()
    {
        // Main ChatShop menu
        add_menu_page(
            __('ChatShop', 'chatshop'),
            __('ChatShop', 'chatshop'),
            $this->capability,
            'chatshop',
            array($this, 'display_dashboard_page'),
            'dashicons-whatsapp',
            30
        );

        // Dashboard submenu (duplicate for proper highlighting)
        add_submenu_page(
            'chatshop',
            __('Dashboard', 'chatshop'),
            __('Dashboard', 'chatshop'),
            $this->capability,
            'chatshop',
            array($this, 'display_dashboard_page')
        );

        // Analytics submenu
        add_submenu_page(
            'chatshop',
            __('Analytics', 'chatshop'),
            __('Analytics', 'chatshop') . $this->get_premium_indicator('analytics'),
            $this->capability,
            'chatshop-analytics',
            array($this, 'display_analytics_page')
        );

        // Contact Management submenu
        add_submenu_page(
            'chatshop',
            __('Contact Management', 'chatshop'),
            __('Contacts', 'chatshop') . $this->get_premium_indicator('unlimited_contacts'),
            $this->capability,
            'chatshop-contacts',
            array($this, 'display_contacts_page')
        );

        // WhatsApp submenu - ADDED
        add_submenu_page(
            'chatshop',
            __('WhatsApp', 'chatshop'),
            __('WhatsApp', 'chatshop') . $this->get_premium_indicator('whatsapp_business_api'),
            $this->capability,
            'chatshop-whatsapp',
            array($this, 'display_whatsapp_page')
        );

        // Payments submenu - ADDED AS TOP-LEVEL
        add_submenu_page(
            'chatshop',
            __('Payments', 'chatshop'),
            __('Payments', 'chatshop') . $this->get_premium_indicator('advanced_payments'),
            $this->capability,
            'chatshop-payments',
            array($this, 'display_payments_page')
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
            __('Payment Config', 'chatshop'),
            $this->capability,
            'chatshop-settings-payments',
            array($this, 'display_payment_settings_page')
        );

        add_submenu_page(
            'chatshop-settings',
            __('WhatsApp Settings', 'chatshop'),
            __('WhatsApp Config', 'chatshop'),
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
     * Display dashboard page
     *
     * @since 1.0.0
     */
    public function display_dashboard_page()
    {
        $this->render_admin_page('dashboard');
    }

    /**
     * Display analytics page with component validation
     *
     * @since 1.0.0
     */
    public function display_analytics_page()
    {
        // Check if analytics component is available
        $analytics = $this->get_component('analytics');

        if (!$analytics) {
            $this->render_component_unavailable_page(
                'analytics',
                __('Analytics Dashboard', 'chatshop'),
                __('The analytics component is not available. Please check the component status below.', 'chatshop')
            );
            return;
        }

        $this->render_admin_page('analytics');
    }

    /**
     * Display contacts page with component validation
     *
     * @since 1.0.0
     */
    public function display_contacts_page()
    {
        // Check if contact manager component is available
        $contact_manager = $this->get_component('contact_manager');

        if (!$contact_manager) {
            $this->render_component_unavailable_page(
                'contacts',
                __('Contact Management', 'chatshop'),
                __('The contact management component is not available. Please check the component status below.', 'chatshop')
            );
            return;
        }

        $this->render_admin_page('contacts');
    }

    /**
     * Display WhatsApp page with component validation - NEW
     *
     * @since 1.0.0
     */
    public function display_whatsapp_page()
    {
        // Check if WhatsApp component is available
        $whatsapp = $this->get_component('whatsapp');

        if (!$whatsapp) {
            $this->render_component_unavailable_page(
                'whatsapp',
                __('WhatsApp Integration', 'chatshop'),
                __('The WhatsApp component is not available. Please check the component status below.', 'chatshop')
            );
            return;
        }

        $this->render_admin_page('whatsapp');
    }

    /**
     * Display payments page with component validation - NEW
     *
     * @since 1.0.0
     */
    public function display_payments_page()
    {
        // Check if payment component is available
        $payment = $this->get_component('payment');

        if (!$payment) {
            $this->render_component_unavailable_page(
                'payment',
                __('Payment Management', 'chatshop'),
                __('The payment component is not available. Please check the component status below.', 'chatshop')
            );
            return;
        }

        $this->render_admin_page('payments');
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
     * Display premium page
     *
     * @since 1.0.0
     */
    public function display_premium_page()
    {
        $this->render_admin_page('premium');
    }

    /**
     * Get component instance with proper error handling
     *
     * @since 1.0.0
     * @param string $component_id Component identifier
     * @return object|null Component instance or null if not found
     */
    private function get_component($component_id)
    {
        if (!$this->component_loader) {
            return null;
        }

        return $this->component_loader->get_component_instance($component_id);
    }

    /**
     * Get premium indicator for menu items
     *
     * @since 1.0.0
     * @param string $feature Feature name
     * @return string Premium indicator HTML
     */
    private function get_premium_indicator($feature)
    {
        if (!function_exists('chatshop_is_premium') || chatshop_is_premium()) {
            return '';
        }

        return ' <span class="chatshop-premium-indicator" title="' .
            esc_attr__('Premium Feature', 'chatshop') . '">★</span>';
    }

    /**
     * Enqueue admin assets with proper page detection
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

        // Common admin JavaScript
        wp_enqueue_script(
            'chatshop-admin',
            CHATSHOP_PLUGIN_URL . 'admin/js/chatshop-admin.js',
            array('jquery', 'wp-util'),
            CHATSHOP_VERSION,
            true
        );

        // Localize script with common data
        wp_localize_script('chatshop-admin', 'chatshopAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('chatshop_admin_nonce'),
            'isPremium' => function_exists('chatshop_is_premium') ? chatshop_is_premium() : false,
            'currentPage' => $this->get_current_page_slug($hook_suffix),
            'strings' => array(
                'loading' => __('Loading...', 'chatshop'),
                'error' => __('An error occurred. Please try again.', 'chatshop'),
                'success' => __('Operation completed successfully.', 'chatshop'),
                'confirm' => __('Are you sure?', 'chatshop'),
                'componentError' => __('Component not loaded. Please check system status.', 'chatshop'),
                'premiumRequired' => __('This feature requires premium access.', 'chatshop')
            )
        ));

        // Page-specific assets
        $this->enqueue_page_specific_assets($hook_suffix);
    }

    /**
     * Enqueue page-specific assets
     *
     * @since 1.0.0
     * @param string $hook_suffix Current admin page hook suffix
     */
    private function enqueue_page_specific_assets($hook_suffix)
    {
        // Analytics page assets
        if (strpos($hook_suffix, 'chatshop-analytics') !== false) {
            $this->enqueue_analytics_assets();
        }

        // Contacts page assets
        if (strpos($hook_suffix, 'chatshop-contacts') !== false) {
            $this->enqueue_contact_assets();
        }

        // WhatsApp page assets
        if (strpos($hook_suffix, 'chatshop-whatsapp') !== false) {
            $this->enqueue_whatsapp_assets();
        }

        // Payments page assets
        if (strpos($hook_suffix, 'chatshop-payments') !== false) {
            $this->enqueue_payment_assets();
        }
    }

    /**
     * Enqueue analytics-specific assets
     *
     * @since 1.0.0
     */
    private function enqueue_analytics_assets()
    {
        wp_enqueue_script(
            'chatshop-analytics',
            CHATSHOP_PLUGIN_URL . 'admin/js/analytics.js',
            array('jquery', 'wp-util'),
            CHATSHOP_VERSION,
            true
        );

        wp_enqueue_style(
            'chatshop-analytics',
            CHATSHOP_PLUGIN_URL . 'admin/css/analytics.css',
            array('chatshop-admin'),
            CHATSHOP_VERSION
        );

        // Chart.js for analytics charts
        wp_enqueue_script(
            'chartjs',
            'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js',
            array(),
            '3.9.1',
            true
        );
    }

    /**
     * Enqueue contact-specific assets
     *
     * @since 1.0.0
     */
    private function enqueue_contact_assets()
    {
        wp_enqueue_script(
            'chatshop-contacts',
            CHATSHOP_PLUGIN_URL . 'admin/js/contacts.js',
            array('jquery', 'wp-util'),
            CHATSHOP_VERSION,
            true
        );

        wp_enqueue_style(
            'chatshop-contacts',
            CHATSHOP_PLUGIN_URL . 'admin/css/contacts.css',
            array('chatshop-admin'),
            CHATSHOP_VERSION
        );
    }

    /**
     * Enqueue WhatsApp-specific assets
     *
     * @since 1.0.0
     */
    private function enqueue_whatsapp_assets()
    {
        wp_enqueue_script(
            'chatshop-whatsapp',
            CHATSHOP_PLUGIN_URL . 'admin/js/whatsapp.js',
            array('jquery', 'wp-util'),
            CHATSHOP_VERSION,
            true
        );

        wp_enqueue_style(
            'chatshop-whatsapp',
            CHATSHOP_PLUGIN_URL . 'admin/css/whatsapp.css',
            array('chatshop-admin'),
            CHATSHOP_VERSION
        );
    }

    /**
     * Enqueue payment-specific assets
     *
     * @since 1.0.0
     */
    private function enqueue_payment_assets()
    {
        wp_enqueue_script(
            'chatshop-payments',
            CHATSHOP_PLUGIN_URL . 'admin/js/payments.js',
            array('jquery', 'wp-util'),
            CHATSHOP_VERSION,
            true
        );

        wp_enqueue_style(
            'chatshop-payments',
            CHATSHOP_PLUGIN_URL . 'admin/css/payments.css',
            array('chatshop-admin'),
            CHATSHOP_VERSION
        );
    }

    /**
     * Get current page slug from hook suffix
     *
     * @since 1.0.0
     * @param string $hook_suffix Hook suffix
     * @return string Page slug
     */
    private function get_current_page_slug($hook_suffix)
    {
        if (strpos($hook_suffix, 'chatshop-analytics') !== false) return 'analytics';
        if (strpos($hook_suffix, 'chatshop-contacts') !== false) return 'contacts';
        if (strpos($hook_suffix, 'chatshop-whatsapp') !== false) return 'whatsapp';
        if (strpos($hook_suffix, 'chatshop-payments') !== false) return 'payments';
        if (strpos($hook_suffix, 'chatshop-messages') !== false) return 'messages';
        if (strpos($hook_suffix, 'chatshop-campaigns') !== false) return 'campaigns';
        if (strpos($hook_suffix, 'chatshop-settings') !== false) return 'settings';
        if (strpos($hook_suffix, 'chatshop-premium') !== false) return 'premium';

        return 'dashboard';
    }

    /**
     * Render admin page template with error handling
     *
     * @since 1.0.0
     * @param string $template Template name
     */
    private function render_admin_page($template)
    {
        $template_path = CHATSHOP_PLUGIN_DIR . "admin/partials/{$template}.php";

        if (file_exists($template_path)) {
            // Pass component loader and errors to template
            $component_loader = $this->component_loader;
            $component_errors = $this->component_errors;

            include $template_path;
        } else {
            $this->render_placeholder_page($template);
        }
    }

    /**
     * Render component unavailable page with enhanced diagnostics
     *
     * @since 1.0.0
     * @param string $component Component name
     * @param string $title Page title
     * @param string $message Error message
     */
    private function render_component_unavailable_page($component, $title, $message)
    {
        $component_error = isset($this->component_errors[$component]) ?
            $this->component_errors[$component] : null;

        $loading_order = $this->component_loader ?
            $this->component_loader->get_loading_order() : array();

        $loaded_components = $this->component_loader ?
            array_keys($this->component_loader->get_all_instances()) : array();
?>
        <div class="wrap">
            <h1><?php echo esc_html($title); ?></h1>

            <div class="chatshop-component-unavailable">
                <div class="notice notice-error">
                    <p><strong><?php _e('Component Unavailable', 'chatshop'); ?></strong></p>
                    <p><?php echo esc_html($message); ?></p>

                    <?php if ($component_error): ?>
                        <p><strong><?php _e('Error Details:', 'chatshop'); ?></strong>
                            <code><?php echo esc_html($component_error); ?></code>
                        </p>
                    <?php endif; ?>
                </div>

                <?php if (defined('WP_DEBUG') && WP_DEBUG): ?>
                    <div class="component-debug-info">
                        <h3><?php _e('Debug Information', 'chatshop'); ?></h3>

                        <h4><?php _e('Component Loading Status:', 'chatshop'); ?></h4>
                        <ul>
                            <li><strong><?php _e('Requested Component:', 'chatshop'); ?></strong> <?php echo esc_html($component); ?></li>
                            <li><strong><?php _e('Component Loader Available:', 'chatshop'); ?></strong>
                                <?php echo $this->component_loader ? '✓ Yes' : '✗ No'; ?></li>
                            <li><strong><?php _e('Total Loaded Components:', 'chatshop'); ?></strong>
                                <?php echo count($loaded_components); ?></li>
                        </ul>

                        <?php if (!empty($loaded_components)): ?>
                            <h4><?php _e('Successfully Loaded Components:', 'chatshop'); ?></h4>
                            <ul>
                                <?php foreach ($loaded_components as $loaded_component): ?>
                                    <li>✓ <?php echo esc_html($loaded_component); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>

                        <?php if (!empty($this->component_errors)): ?>
                            <h4><?php _e('Component Loading Errors:', 'chatshop'); ?></h4>
                            <ul>
                                <?php foreach ($this->component_errors as $error_component => $error_message): ?>
                                    <li>✗ <strong><?php echo esc_html($error_component); ?>:</strong>
                                        <?php echo esc_html($error_message); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>

                        <h4><?php _e('Component Loading Order:', 'chatshop'); ?></h4>
                        <ol>
                            <?php foreach ($loading_order as $loaded_component): ?>
                                <li><?php echo esc_html($loaded_component); ?></li>
                            <?php endforeach; ?>
                        </ol>
                    </div>
                <?php endif; ?>

                <div class="component-troubleshooting">
                    <h3><?php _e('Troubleshooting Steps:', 'chatshop'); ?></h3>
                    <ol>
                        <li><?php _e('Enable WP_DEBUG in wp-config.php to see detailed error information', 'chatshop'); ?></li>
                        <li><?php _e('Deactivate and reactivate the ChatShop plugin', 'chatshop'); ?></li>
                        <li><?php _e('Check that all plugin files are properly uploaded', 'chatshop'); ?></li>
                        <li><?php _e('Verify file permissions are correctly set (644 for files, 755 for directories)', 'chatshop'); ?></li>
                        <li><?php _e('Clear any object caches or optimization plugins', 'chatshop'); ?></li>
                        <li><?php _e('Contact support if the issue persists', 'chatshop'); ?></li>
                    </ol>
                </div>

                <p>
                    <a href="<?php echo admin_url('admin.php?page=chatshop'); ?>" class="button button-primary">
                        <?php _e('Return to Dashboard', 'chatshop'); ?>
                    </a>

                    <?php if (defined('WP_DEBUG') && WP_DEBUG): ?>
                        <a href="<?php echo admin_url('admin.php?page=chatshop&debug_components=1'); ?>"
                            class="button button-secondary">
                            <?php _e('Reload Components', 'chatshop'); ?>
                        </a>
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <style>
            .chatshop-component-unavailable {
                max-width: 800px;
                margin: 20px 0;
            }

            .component-troubleshooting,
            .component-debug-info {
                background: #f8f9fa;
                padding: 20px;
                border-radius: 4px;
                margin: 20px 0;
            }

            .component-troubleshooting h3,
            .component-debug-info h3 {
                margin-top: 0;
            }

            .component-debug-info {
                border-left: 4px solid #007cba;
            }

            .component-debug-info ul,
            .component-debug-info ol {
                margin: 10px 0;
                padding-left: 20px;
            }

            .component-debug-info li {
                margin-bottom: 5px;
                font-family: monospace;
                font-size: 13px;
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
            'premium' => __('Premium & Support', 'chatshop'),
            'whatsapp' => __('WhatsApp Integration', 'chatshop'),
            'payments' => __('Payment Management', 'chatshop')
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
                            <div class="feature-card">
                                <h3><?php _e('Advanced Analytics', 'chatshop'); ?></h3>
                                <p><?php _e('Track conversion rates, revenue attribution, and detailed campaign performance.', 'chatshop'); ?></p>
                            </div>
                            <div class="feature-card">
                                <h3><?php _e('Unlimited Contacts', 'chatshop'); ?></h3>
                                <p><?php _e('Import and manage unlimited WhatsApp contacts with advanced segmentation.', 'chatshop'); ?></p>
                            </div>
                            <div class="feature-card">
                                <h3><?php _e('Campaign Automation', 'chatshop'); ?></h3>
                                <p><?php _e('Set up automated WhatsApp campaigns with triggers and sequences.', 'chatshop'); ?></p>
                            </div>
                            <div class="feature-card">
                                <h3><?php _e('WhatsApp Business API', 'chatshop'); ?></h3>
                                <p><?php _e('Direct integration with WhatsApp Business API for enhanced messaging capabilities.', 'chatshop'); ?></p>
                            </div>
                            <div class="feature-card">
                                <h3><?php _e('Advanced Payment Gateways', 'chatshop'); ?></h3>
                                <p><?php _e('Support for multiple payment gateways including PayPal, Stripe, and more.', 'chatshop'); ?></p>
                            </div>
                            <div class="feature-card">
                                <h3><?php _e('Priority Support', 'chatshop'); ?></h3>
                                <p><?php _e('Get priority email and chat support from our expert team.', 'chatshop'); ?></p>
                            </div>
                        </div>

                        <div class="premium-cta">
                            <a href="#" class="button button-primary button-hero">
                                <?php _e('Upgrade to Premium', 'chatshop'); ?>
                            </a>
                            <p class="pricing-note">
                                <?php _e('Starting at $29/month. 14-day money-back guarantee.', 'chatshop'); ?>
                            </p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="chatshop-feature-placeholder">
                        <div class="feature-icon">
                            <span class="dashicons dashicons-<?php echo $this->get_page_icon($template); ?>"></span>
                        </div>

                        <h2><?php echo esc_html($title); ?></h2>
                        <p><?php printf(__('The %s feature is currently under development and will be available in a future update.', 'chatshop'), strtolower($title)); ?></p>

                        <div class="feature-preview">
                            <?php $this->render_feature_preview($template); ?>
                        </div>

                        <div class="placeholder-actions">
                            <a href="<?php echo admin_url('admin.php?page=chatshop'); ?>" class="button button-primary">
                                <?php _e('Return to Dashboard', 'chatshop'); ?>
                            </a>
                            <a href="<?php echo admin_url('admin.php?page=chatshop-premium'); ?>" class="button button-secondary">
                                <?php _e('Learn About Premium', 'chatshop'); ?>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <style>
            .chatshop-placeholder-page {
                text-align: center;
                max-width: 800px;
                margin: 40px auto;
            }

            .chatshop-premium-info-page {
                max-width: 1000px;
                margin: 0 auto;
            }

            .feature-icon .dashicons {
                font-size: 64px;
                color: #666;
                margin-bottom: 20px;
            }

            .premium-features-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 20px;
                margin: 40px 0;
            }

            .feature-card {
                background: #fff;
                border: 1px solid #ddd;
                border-radius: 8px;
                padding: 20px;
                text-align: left;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
                transition: transform 0.2s ease;
            }

            .feature-card:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
            }

            .feature-card h3 {
                margin-top: 0;
                color: #2c3e50;
                font-size: 18px;
            }

            .feature-card p {
                color: #666;
                margin-bottom: 0;
            }

            .premium-cta {
                text-align: center;
                margin: 40px 0;
                padding: 30px;
                background: #f8f9fa;
                border-radius: 8px;
            }

            .premium-cta .button-hero {
                font-size: 18px;
                padding: 12px 30px;
                height: auto;
            }

            .pricing-note {
                margin-top: 15px;
                color: #666;
                font-style: italic;
            }

            .feature-preview {
                background: #f8f9fa;
                border-radius: 8px;
                padding: 20px;
                margin: 20px 0;
                text-align: left;
            }

            .feature-preview h4 {
                margin-top: 0;
                color: #2c3e50;
            }

            .feature-preview ul {
                margin: 0;
                padding-left: 20px;
            }

            .feature-preview li {
                margin-bottom: 8px;
            }

            .placeholder-actions {
                margin-top: 30px;
            }

            .placeholder-actions .button {
                margin: 0 10px;
            }

            .lead {
                font-size: 18px;
                color: #666;
                margin-bottom: 30px;
            }
        </style>
        <?php
    }

    /**
     * Render feature preview for placeholder pages
     *
     * @since 1.0.0
     * @param string $template Template name
     */
    private function render_feature_preview($template)
    {
        switch ($template) {
            case 'messages':
        ?>
                <h4><?php _e('Coming Soon: Message Management', 'chatshop'); ?></h4>
                <ul>
                    <li><?php _e('Send bulk WhatsApp messages to contact groups', 'chatshop'); ?></li>
                    <li><?php _e('Message templates and personalization', 'chatshop'); ?></li>
                    <li><?php _e('Scheduled message delivery', 'chatshop'); ?></li>
                    <li><?php _e('Message delivery reports and analytics', 'chatshop'); ?></li>
                </ul>
            <?php
                break;

            case 'campaigns':
            ?>
                <h4><?php _e('Coming Soon: Campaign Automation', 'chatshop'); ?></h4>
                <ul>
                    <li><?php _e('Automated drip campaigns via WhatsApp', 'chatshop'); ?></li>
                    <li><?php _e('Trigger-based messaging (new orders, abandoned carts)', 'chatshop'); ?></li>
                    <li><?php _e('A/B testing for message templates', 'chatshop'); ?></li>
                    <li><?php _e('Campaign performance tracking', 'chatshop'); ?></li>
                </ul>
            <?php
                break;

            default:
            ?>
                <h4><?php _e('Feature in Development', 'chatshop'); ?></h4>
                <p><?php _e('This feature is being actively developed and will be available soon.', 'chatshop'); ?></p>
<?php
                break;
        }
    }

    /**
     * Get appropriate dashicon for page
     *
     * @since 1.0.0
     * @param string $template Template name
     * @return string Dashicon name
     */
    private function get_page_icon($template)
    {
        $icons = array(
            'messages' => 'email-alt',
            'campaigns' => 'megaphone',
            'whatsapp' => 'whatsapp',
            'payments' => 'money-alt',
            'analytics' => 'chart-bar',
            'contacts' => 'groups'
        );

        return isset($icons[$template]) ? $icons[$template] : 'admin-generic';
    }

    /**
     * Display component notices in admin
     *
     * @since 1.0.0
     */
    public function display_component_notices()
    {
        // Only show on ChatShop pages
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'chatshop') === false) {
            return;
        }

        // Show component loading errors if any
        if (!empty($this->component_errors)) {
            $error_count = count($this->component_errors);

            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p><strong>' . __('ChatShop Component Warning:', 'chatshop') . '</strong> ';
            printf(
                _n(
                    '%d component failed to load properly.',
                    '%d components failed to load properly.',
                    $error_count,
                    'chatshop'
                ),
                $error_count
            );
            echo ' <a href="#" onclick="jQuery(\'.component-errors-details\').toggle(); return false;">' .
                __('Show Details', 'chatshop') . '</a></p>';

            echo '<div class="component-errors-details" style="display: none; margin-top: 10px;">';
            echo '<ul>';
            foreach ($this->component_errors as $component => $error) {
                echo '<li><strong>' . esc_html($component) . ':</strong> ' . esc_html($error) . '</li>';
            }
            echo '</ul>';
            echo '</div>';
            echo '</div>';
        }

        // Show component reload success if triggered
        if (isset($_GET['debug_components']) && $_GET['debug_components'] === '1') {
            echo '<div class="notice notice-info is-dismissible">';
            echo '<p>' . __('Component reload triggered. Check the debug information below for details.', 'chatshop') . '</p>';
            echo '</div>';
        }

        // Show system status notice if components are missing
        if ($this->component_loader) {
            $loaded_count = $this->component_loader->get_loaded_count();
            $expected_components = array('payment', 'analytics', 'contact_manager'); // Core components

            if ($loaded_count < count($expected_components)) {
                echo '<div class="notice notice-error">';
                echo '<p><strong>' . __('ChatShop System Alert:', 'chatshop') . '</strong> ';
                echo __('Some core components are not loaded. This may affect plugin functionality.', 'chatshop');
                echo ' <a href="' . admin_url('admin.php?page=chatshop&debug_components=1') . '">' .
                    __('Diagnose Issues', 'chatshop') . '</a></p>';
                echo '</div>';
            }
        }
    }

    /**
     * Handle component reload debug action
     *
     * @since 1.0.0
     */
    public function handle_debug_actions()
    {
        // Check if debug component reload is requested
        if (isset($_GET['debug_components']) && $_GET['debug_components'] === '1' && current_user_can('manage_options')) {
            // Force component loader to reload
            if ($this->component_loader) {
                // Re-initialize component loader
                $this->component_loader = null;
                $this->get_component_loader();

                // Add admin notice
                add_action('admin_notices', function () {
                    echo '<div class="notice notice-success is-dismissible">';
                    echo '<p>' . __('Component reload completed. Check component status below.', 'chatshop') . '</p>';
                    echo '</div>';
                });
            }
        }
    }

    /**
     * Add debug information to admin footer
     *
     * @since 1.0.0
     */
    public function add_debug_info_to_footer()
    {
        // Only show on ChatShop pages and if WP_DEBUG is enabled
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'chatshop') === false || !defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        echo '<div id="chatshop-debug-info" style="margin-top: 20px; padding: 15px; background: #f1f1f1; border-left: 4px solid #0073aa;">';
        echo '<h4>' . __('ChatShop Debug Information', 'chatshop') . '</h4>';

        if ($this->component_loader) {
            $loaded_components = array_keys($this->component_loader->get_all_instances());
            $loading_order = $this->component_loader->get_loading_order();

            echo '<p><strong>' . __('Loaded Components:', 'chatshop') . '</strong> ' .
                (empty($loaded_components) ? __('None', 'chatshop') : implode(', ', $loaded_components)) . '</p>';

            echo '<p><strong>' . __('Loading Order:', 'chatshop') . '</strong> ' .
                (empty($loading_order) ? __('None', 'chatshop') : implode(' → ', $loading_order)) . '</p>';

            if (!empty($this->component_errors)) {
                echo '<p><strong>' . __('Component Errors:', 'chatshop') . '</strong></p>';
                echo '<ul>';
                foreach ($this->component_errors as $component => $error) {
                    echo '<li>' . esc_html($component) . ': ' . esc_html($error) . '</li>';
                }
                echo '</ul>';
            }
        } else {
            echo '<p style="color: red;"><strong>' . __('Component Loader Not Available', 'chatshop') . '</strong></p>';
        }

        echo '</div>';
    }

    /**
     * Initialize debug actions
     *
     * @since 1.0.0
     */
    public function init_debug_actions()
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            add_action('admin_init', array($this, 'handle_debug_actions'));
            add_action('admin_footer', array($this, 'add_debug_info_to_footer'));
        }
    }
}
