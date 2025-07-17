<?php

/**
 * Provide a admin area view for the plugin dashboard
 *
 * @link       https://modewebhost.com.ng
 * @since      1.0.0
 *
 * @package    ChatShop
 * @subpackage ChatShop/admin/partials
 */

// If this file is called directly, abort.
if (! defined('WPINC')) {
    die;
}

// Get summary data
$total_messages = get_option('chatshop_total_messages', 0);
$active_campaigns = get_option('chatshop_active_campaigns', 0);
$total_revenue = get_option('chatshop_total_revenue', 0);
$conversion_rate = get_option('chatshop_conversion_rate', 0);
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <div class="chatshop-dashboard">
        <!-- Welcome Section -->
        <div class="chatshop-welcome-panel">
            <h2><?php _e('Welcome to ChatShop', 'chatshop'); ?></h2>
            <p class="about-description">
                <?php _e('Convert WhatsApp engagement into sales revenue with automated marketing and seamless payment processing.', 'chatshop'); ?>
            </p>

            <div class="chatshop-quick-actions">
                <a href="<?php echo admin_url('admin.php?page=chatshop-settings'); ?>" class="button button-primary button-hero">
                    <?php _e('Configure Settings', 'chatshop'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=chatshop-campaigns'); ?>" class="button button-secondary button-hero">
                    <?php _e('Create Campaign', 'chatshop'); ?>
                </a>
            </div>
        </div>

        <!-- Stats Overview -->
        <div class="chatshop-stats-grid">
            <div class="chatshop-stat-box">
                <h3><?php _e('Total Messages', 'chatshop'); ?></h3>
                <p class="chatshop-stat-number"><?php echo number_format($total_messages); ?></p>
                <p class="chatshop-stat-label"><?php _e('WhatsApp messages sent', 'chatshop'); ?></p>
            </div>

            <div class="chatshop-stat-box">
                <h3><?php _e('Active Campaigns', 'chatshop'); ?></h3>
                <p class="chatshop-stat-number"><?php echo number_format($active_campaigns); ?></p>
                <p class="chatshop-stat-label"><?php _e('Running campaigns', 'chatshop'); ?></p>
            </div>

            <div class="chatshop-stat-box">
                <h3><?php _e('Total Revenue', 'chatshop'); ?></h3>
                <p class="chatshop-stat-number"><?php echo wc_price($total_revenue); ?></p>
                <p class="chatshop-stat-label"><?php _e('From WhatsApp sales', 'chatshop'); ?></p>
            </div>

            <div class="chatshop-stat-box">
                <h3><?php _e('Conversion Rate', 'chatshop'); ?></h3>
                <p class="chatshop-stat-number"><?php echo number_format($conversion_rate, 1); ?>%</p>
                <p class="chatshop-stat-label"><?php _e('Message to sale', 'chatshop'); ?></p>
            </div>
        </div>

        <!-- Quick Setup Status -->
        <div class="chatshop-setup-status">
            <h2><?php _e('Setup Status', 'chatshop'); ?></h2>

            <ul class="chatshop-setup-list">
                <li class="<?php echo get_option('chatshop_whatsapp_phone') ? 'completed' : 'pending'; ?>">
                    <span class="dashicons <?php echo get_option('chatshop_whatsapp_phone') ? 'dashicons-yes' : 'dashicons-no'; ?>"></span>
                    <?php _e('WhatsApp Business API configured', 'chatshop'); ?>
                    <?php if (! get_option('chatshop_whatsapp_phone')) : ?>
                        <a href="<?php echo admin_url('admin.php?page=chatshop-settings&tab=whatsapp'); ?>"><?php _e('Configure', 'chatshop'); ?></a>
                    <?php endif; ?>
                </li>

                <li class="<?php echo get_option('chatshop_paystack_public_key') ? 'completed' : 'pending'; ?>">
                    <span class="dashicons <?php echo get_option('chatshop_paystack_public_key') ? 'dashicons-yes' : 'dashicons-no'; ?>"></span>
                    <?php _e('Payment gateway configured', 'chatshop'); ?>
                    <?php if (! get_option('chatshop_paystack_public_key')) : ?>
                        <a href="<?php echo admin_url('admin.php?page=chatshop-settings&tab=payments'); ?>"><?php _e('Configure', 'chatshop'); ?></a>
                    <?php endif; ?>
                </li>

                <li class="<?php echo class_exists('WooCommerce') ? 'completed' : 'pending'; ?>">
                    <span class="dashicons <?php echo class_exists('WooCommerce') ? 'dashicons-yes' : 'dashicons-no'; ?>"></span>
                    <?php _e('WooCommerce installed and active', 'chatshop'); ?>
                    <?php if (! class_exists('WooCommerce')) : ?>
                        <a href="<?php echo admin_url('plugin-install.php?s=woocommerce&tab=search&type=term'); ?>"><?php _e('Install', 'chatshop'); ?></a>
                    <?php endif; ?>
                </li>
            </ul>
        </div>

        <!-- Recent Activity -->
        <div class="chatshop-recent-activity">
            <h2><?php _e('Recent Activity', 'chatshop'); ?></h2>
            <p class="description"><?php _e('Recent WhatsApp interactions and payment activities will appear here.', 'chatshop'); ?></p>
        </div>
    </div>
</div>