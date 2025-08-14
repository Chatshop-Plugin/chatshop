<?php

/**
 * Admin Menu Management - RECURSION FIXED VERSION
 *
 * File: admin/class-chatshop-admin-menu.php
 * 
 * CRITICAL FIXES:
 * - Removed premature component loader access from constructor
 * - Added lazy loading of component loader
 * - Implemented proper null checking and fallbacks
 * - Added recursion protection mechanisms
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
 * ChatShop Admin Menu Class - RECURSION FIXED
 *
 * Manages all admin menu registration, page rendering, and component validation
 * with enhanced error handling and recursion prevention.
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
     * Component loader access attempts counter
     *
     * @var int
     * @since 1.0.0
     */
    private $loader_access_attempts = 0;

    /**
     * Constructor - RECURSION FIXED
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        $this->init_hooks();
        // DO NOT access component loader here - causes recursion
        // Component loader will be accessed lazily when needed
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
     * Get component loader instance - LAZY LOADING WITH RECURSION PROTECTION
     *
     * @since 1.0.0
     * @return ChatShop_Component_Loader|null
     */
    private function get_component_loader()
    {
        // Prevent infinite recursion
        $this->loader_access_attempts++;
        if ($this->loader_access_attempts > 5) {
            error_log('ChatShop: Component loader access recursion detected in admin menu');
            return null;
        }

        // Return cached instance if available
        if ($this->component_loader) {
            return $this->component_loader;
        }

        // Check if main plugin instance is available and initialized
        if (!function_exists('chatshop')) {
            return null;
        }

        // Use global state to check if initialization is safe
        global $chatshop_initializing, $chatshop_initialization_complete;

        if ($chatshop_initializing || !$chatshop_initialization_complete) {
            // Plugin is still initializing, return null to avoid recursion
            return null;
        }

        $plugin_instance = chatshop();
        if (!$plugin_instance) {
            return null;
        }

        // Check if plugin is fully initialized
        if (!method_exists($plugin_instance, 'is_initialized') || !$plugin_instance->is_initialized()) {
            return null;
        }

        // Now safe to get component loader
        if (method_exists($plugin_instance, 'get_component_loader')) {
            $this->component_loader = $plugin_instance->get_component_loader();

            if ($this->component_loader && method_exists($this->component_loader, 'get_loading_errors')) {
                $this->component_errors = $this->component_loader->get_loading_errors();
            }
        }

        return $this->component_loader;
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

        // Contacts submenu
        add_submenu_page(
            'chatshop',
            __('Contacts', 'chatshop'),
            __('Contacts', 'chatshop'),
            $this->capability,
            'chatshop-contacts',
            array($this, 'display_contacts_page')
        );

        // WhatsApp submenu
        add_submenu_page(
            'chatshop',
            __('WhatsApp', 'chatshop'),
            __('WhatsApp', 'chatshop'),
            $this->capability,
            'chatshop-whatsapp',
            array($this, 'display_whatsapp_page')
        );

        // Payments submenu
        add_submenu_page(
            'chatshop',
            __('Payments', 'chatshop'),
            __('Payments', 'chatshop'),
            $this->capability,
            'chatshop-payments',
            array($this, 'display_payments_page')
        );

        // Messages submenu
        add_submenu_page(
            'chatshop',
            __('Messages', 'chatshop'),
            __('Messages', 'chatshop'),
            $this->capability,
            'chatshop-messages',
            array($this, 'display_messages_page')
        );

        // Campaigns submenu
        add_submenu_page(
            'chatshop',
            __('Campaigns', 'chatshop'),
            __('Campaigns', 'chatshop') . $this->get_premium_indicator('campaigns'),
            $this->capability,
            'chatshop-campaigns',
            array($this, 'display_campaigns_page')
        );

        // Settings submenu with sub-items
        add_submenu_page(
            'chatshop',
            __('Settings', 'chatshop'),
            __('Settings', 'chatshop'),
            $this->capability,
            'chatshop-settings-general',
            array($this, 'display_general_settings_page')
        );

        add_submenu_page(
            'chatshop',
            __('Payment Settings', 'chatshop'),
            __('Payment Settings', 'chatshop'),
            $this->capability,
            'chatshop-settings-payments',
            array($this, 'display_payment_settings_page')
        );

        add_submenu_page(
            'chatshop',
            __('WhatsApp Settings', 'chatshop'),
            __('WhatsApp Settings', 'chatshop'),
            $this->capability,
            'chatshop-settings-whatsapp',
            array($this, 'display_whatsapp_settings_page')
        );

        // Premium submenu
        add_submenu_page(
            'chatshop',
            __('Premium', 'chatshop'),
            __('Premium', 'chatshop') . ' ★',
            $this->capability,
            'chatshop-premium',
            array($this, 'display_premium_page')
        );
    }

    // ================================
    // PAGE DISPLAY METHODS - SAFE VERSIONS
    // ================================

    /**
     * Display dashboard page - SAFE VERSION
     *
     * @since 1.0.0
     */
    public function display_dashboard_page()
    {
        $this->render_admin_page('dashboard');
    }

    /**
     * Display analytics page - SAFE VERSION
     *
     * @since 1.0.0
     */
    public function display_analytics_page()
    {
        $component_loader = $this->get_component_loader();
        $analytics_component = null;

        if ($component_loader) {
            $analytics_component = $component_loader->get_component_instance('analytics');
        }

        if (!$analytics_component) {
            $this->render_component_unavailable_page(
                'analytics',
                __('Analytics', 'chatshop'),
                __('The Analytics component is not available. Please check the component status below.', 'chatshop')
            );
            return;
        }

        $this->render_admin_page('analytics');
    }

    /**
     * Display contacts page - SAFE VERSION
     *
     * @since 1.0.0
     */
    public function display_contacts_page()
    {
        $component_loader = $this->get_component_loader();
        $contact_component = null;

        if ($component_loader) {
            $contact_component = $component_loader->get_component_instance('contact_manager');
        }

        if (!$contact_component) {
            $this->render_component_unavailable_page(
                'contact_manager',
                __('Contact Management', 'chatshop'),
                __('The Contact Management component is not available. Please check the component status below.', 'chatshop')
            );
            return;
        }

        $this->render_admin_page('contacts');
    }

    /**
     * Display WhatsApp page - SAFE VERSION
     *
     * @since 1.0.0
     */
    public function display_whatsapp_page()
    {
        $this->render_admin_page('whatsapp');
    }

    /**
     * Display payments page - SAFE VERSION
     *
     * @since 1.0.0
     */
    public function display_payments_page()
    {
        $component_loader = $this->get_component_loader();
        $payment_component = null;

        if ($component_loader) {
            $payment_component = $component_loader->get_component_instance('payment');
        }

        if (!$payment_component) {
            $this->render_component_unavailable_page(
                'payment',
                __('Payment Management', 'chatshop'),
                __('The Payment component is not available. Please check the component status below.', 'chatshop')
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

    // ================================
    // RENDERING METHODS - ENHANCED ERROR HANDLING
    // ================================

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
            $component_loader = $this->get_component_loader();
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

        $component_loader = $this->get_component_loader();
        $loading_order = array();
        $loaded_components = array();

        if ($component_loader) {
            $loading_order = $component_loader->get_loading_order();
            $loaded_components = array_keys($component_loader->get_all_instances());
        }
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
                    <div class="component-debug-info" style="background: #f9f9f9; padding: 15px; border: 1px solid #ddd; margin: 20px 0;">
                        <h3><?php _e('Debug Information', 'chatshop'); ?></h3>

                        <h4><?php _e('Component Loading Status:', 'chatshop'); ?></h4>
                        <ul>
                            <li><strong><?php _e('Requested Component:', 'chatshop'); ?></strong> <?php echo esc_html($component); ?></li>
                            <li><strong><?php _e('Component Loader Available:', 'chatshop'); ?></strong>
                                <?php echo $component_loader ? '✓ Yes' : '✗ No'; ?></li>
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

                <div class="troubleshooting-steps">
                    <h3><?php _e('Troubleshooting Steps:', 'chatshop'); ?></h3>
                    <ol>
                        <li><?php _e('Check if all plugin files are properly uploaded', 'chatshop'); ?></li>
                        <li><?php _e('Verify file permissions are correct', 'chatshop'); ?></li>
                        <li><?php _e('Try deactivating and reactivating the plugin', 'chatshop'); ?></li>
                        <li><?php _e('Check for plugin conflicts by testing with default theme', 'chatshop'); ?></li>
                        <li><?php _e('Review error logs for additional details', 'chatshop'); ?></li>
                    </ol>
                </div>
            </div>
        </div>
<?php
    }

    /**
     * Render placeholder page for missing templates
     *
     * @since 1.0.0
     * @param string $template Template name
     */
    private function render_placeholder_page($template)
    {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html(ucfirst(str_replace('-', ' ', $template))) . '</h1>';
        echo '<div class="notice notice-info">';
        echo '<p>' . sprintf(__('The %s page template is under development.', 'chatshop'), esc_html($template)) . '</p>';
        echo '</div>';
        echo '</div>';
    }

    // ================================
    // UTILITY METHODS
    // ================================

    /**
     * Get component instance with proper error handling
     *
     * @since 1.0.0
     * @param string $component_id Component identifier
     * @return object|null Component instance or null if not found
     */
    private function get_component($component_id)
    {
        $component_loader = $this->get_component_loader();
        if (!$component_loader) {
            return null;
        }

        return $component_loader->get_component_instance($component_id);
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

    // ================================
    // ASSET MANAGEMENT
    // ================================

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

        // Localize script for AJAX
        wp_localize_script('chatshop-admin', 'chatshopAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('chatshop_admin_nonce'),
            'strings' => array(
                'saving' => __('Saving...', 'chatshop'),
                'saved' => __('Settings saved successfully', 'chatshop'),
                'error' => __('An error occurred while saving settings', 'chatshop'),
                'confirm' => __('Are you sure?', 'chatshop'),
                'processing' => __('Processing...', 'chatshop')
            )
        ));

        // Page-specific assets
        $current_page = $this->get_current_page_slug($hook_suffix);
        switch ($current_page) {
            case 'analytics':
                $this->enqueue_analytics_assets();
                break;
            case 'contacts':
                $this->enqueue_contacts_assets();
                break;
            case 'whatsapp':
                $this->enqueue_whatsapp_assets();
                break;
            case 'payments':
                $this->enqueue_payment_assets();
                break;
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
    }

    /**
     * Enqueue contacts-specific assets
     *
     * @since 1.0.0
     */
    private function enqueue_contacts_assets()
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

    // ================================
    // ADMIN NOTICES
    // ================================

    /**
     * Display component-related admin notices
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

        // Check component errors - but safely
        $component_loader = $this->get_component_loader();
        $component_errors = array();

        if ($component_loader) {
            $component_errors = $component_loader->get_loading_errors();
        }

        if (!empty($component_errors)) {
            $error_count = count($component_errors);
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
            foreach ($component_errors as $component => $error) {
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
        if ($component_loader) {
            $loaded_count = $component_loader->get_loaded_count();
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
}
