<?php

/**
 * Provide a admin area view for the plugin dashboard
 *
 * @package ChatShop
 * @subpackage ChatShop/admin/partials
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get current stats (temporary data until components are loaded)
$total_contacts = count(get_option('chatshop_temp_contacts', array()));
$total_payments = 0;
$total_revenue = 0;
$whatsapp_connected = false;

// Check if WhatsApp is configured
$whatsapp_options = get_option('chatshop_whatsapp_options', array());
if (!empty($whatsapp_options['api_token']) && !empty($whatsapp_options['phone_number'])) {
    $whatsapp_connected = true;
}

// Check if payments are configured
$payment_options = get_option('chatshop_payments_options', array());
$payments_configured = !empty($payment_options['paystack_secret_key']);
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <!-- Dashboard Header -->
    <div class="chatshop-dashboard-header">
        <div class="chatshop-welcome">
            <h2><?php _e('Welcome to ChatShop', 'chatshop'); ?></h2>
            <p><?php _e('Transform your WhatsApp conversations into sales with our powerful social commerce platform.', 'chatshop'); ?></p>
        </div>
    </div>

    <!-- Quick Stats Cards -->
    <div class="chatshop-stats-grid">
        <div class="chatshop-stat-card">
            <div class="stat-icon">
                <span class="dashicons dashicons-admin-users"></span>
            </div>
            <div class="stat-content">
                <h3><?php echo number_format($total_contacts); ?></h3>
                <p><?php _e('WhatsApp Contacts', 'chatshop'); ?></p>
            </div>
        </div>

        <div class="chatshop-stat-card">
            <div class="stat-icon">
                <span class="dashicons dashicons-money-alt"></span>
            </div>
            <div class="stat-content">
                <h3><?php echo number_format($total_payments); ?></h3>
                <p><?php _e('Total Payments', 'chatshop'); ?></p>
            </div>
        </div>

        <div class="chatshop-stat-card">
            <div class="stat-icon">
                <span class="dashicons dashicons-chart-line"></span>
            </div>
            <div class="stat-content">
                <h3><?php echo number_format($total_revenue, 2); ?></h3>
                <p><?php _e('Total Revenue', 'chatshop'); ?></p>
            </div>
        </div>

        <div class="chatshop-stat-card">
            <div class="stat-icon status-<?php echo $whatsapp_connected ? 'connected' : 'disconnected'; ?>">
                <span class="dashicons dashicons-whatsapp"></span>
            </div>
            <div class="stat-content">
                <h3><?php echo $whatsapp_connected ? __('Connected', 'chatshop') : __('Not Connected', 'chatshop'); ?></h3>
                <p><?php _e('WhatsApp Status', 'chatshop'); ?></p>
            </div>
        </div>
    </div>

    <!-- Setup Checklist -->
    <div class="chatshop-dashboard-section">
        <h2><?php _e('Quick Setup', 'chatshop'); ?></h2>
        <div class="chatshop-setup-checklist">
            <div class="setup-item <?php echo $whatsapp_connected ? 'completed' : 'pending'; ?>">
                <span class="setup-icon"><?php echo $whatsapp_connected ? '✓' : '○'; ?></span>
                <div class="setup-content">
                    <h4><?php _e('Configure WhatsApp', 'chatshop'); ?></h4>
                    <p><?php _e('Connect your WhatsApp Business account to start receiving messages.', 'chatshop'); ?></p>
                    <?php if (!$whatsapp_connected): ?>
                        <a href="<?php echo admin_url('admin.php?page=chatshop-whatsapp'); ?>" class="button button-primary">
                            <?php _e('Configure WhatsApp', 'chatshop'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="setup-item <?php echo $payments_configured ? 'completed' : 'pending'; ?>">
                <span class="setup-icon"><?php echo $payments_configured ? '✓' : '○'; ?></span>
                <div class="setup-content">
                    <h4><?php _e('Setup Payments', 'chatshop'); ?></h4>
                    <p><?php _e('Configure your payment gateways to start receiving payments.', 'chatshop'); ?></p>
                    <?php if (!$payments_configured): ?>
                        <a href="<?php echo admin_url('admin.php?page=chatshop-payments'); ?>" class="button button-primary">
                            <?php _e('Configure Payments', 'chatshop'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="setup-item pending">
                <span class="setup-icon">○</span>
                <div class="setup-content">
                    <h4><?php _e('Create Your First Campaign', 'chatshop'); ?></h4>
                    <p><?php _e('Start engaging with your customers through WhatsApp marketing campaigns.', 'chatshop'); ?></p>
                    <a href="#" class="button button-secondary">
                        <?php _e('Coming Soon', 'chatshop'); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="chatshop-dashboard-grid">
        <div class="chatshop-dashboard-widget">
            <h3><?php _e('Recent Contacts', 'chatshop'); ?></h3>
            <div class="widget-content">
                <?php
                $recent_contacts = array_slice(get_option('chatshop_temp_contacts', array()), -5);
                if (!empty($recent_contacts)):
                ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Name', 'chatshop'); ?></th>
                                <th><?php _e('Phone', 'chatshop'); ?></th>
                                <th><?php _e('Date', 'chatshop'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_reverse($recent_contacts) as $contact): ?>
                                <tr>
                                    <td><?php echo esc_html($contact['name']); ?></td>
                                    <td><?php echo esc_html($contact['phone']); ?></td>
                                    <td><?php echo esc_html(date('M j, Y', strtotime($contact['created']))); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="no-data"><?php _e('No contacts yet. Start by configuring WhatsApp!', 'chatshop'); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="chatshop-dashboard-widget">
            <h3><?php _e('Quick Actions', 'chatshop'); ?></h3>
            <div class="widget-content">
                <div class="quick-actions">
                    <a href="<?php echo admin_url('admin.php?page=chatshop-whatsapp'); ?>" class="quick-action">
                        <span class="dashicons dashicons-whatsapp"></span>
                        <?php _e('WhatsApp Settings', 'chatshop'); ?>
                    </a>

                    <a href="<?php echo admin_url('admin.php?page=chatshop-payments'); ?>" class="quick-action">
                        <span class="dashicons dashicons-money-alt"></span>
                        <?php _e('Payment Settings', 'chatshop'); ?>
                    </a>

                    <a href="<?php echo admin_url('admin.php?page=chatshop-analytics'); ?>" class="quick-action">
                        <span class="dashicons dashicons-chart-bar"></span>
                        <?php _e('View Analytics', 'chatshop'); ?>
                    </a>

                    <a href="<?php echo admin_url('admin.php?page=chatshop-settings'); ?>" class="quick-action">
                        <span class="dashicons dashicons-admin-generic"></span>
                        <?php _e('General Settings', 'chatshop'); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Help Section -->
    <div class="chatshop-dashboard-section">
        <h2><?php _e('Getting Started', 'chatshop'); ?></h2>
        <div class="chatshop-help-grid">
            <div class="help-card">
                <h4><?php _e('Documentation', 'chatshop'); ?></h4>
                <p><?php _e('Learn how to set up and use ChatShop effectively.', 'chatshop'); ?></p>
                <a href="#" class="button button-secondary"><?php _e('View Docs', 'chatshop'); ?></a>
            </div>

            <div class="help-card">
                <h4><?php _e('Video Tutorials', 'chatshop'); ?></h4>
                <p><?php _e('Watch step-by-step tutorials to master ChatShop.', 'chatshop'); ?></p>
                <a href="#" class="button button-secondary"><?php _e('Watch Videos', 'chatshop'); ?></a>
            </div>

            <div class="help-card">
                <h4><?php _e('Support', 'chatshop'); ?></h4>
                <p><?php _e('Get help from our support team.', 'chatshop'); ?></p>
                <a href="#" class="button button-secondary"><?php _e('Contact Support', 'chatshop'); ?></a>
            </div>
        </div>
    </div>
</div>

<style>
    .chatshop-dashboard-header {
        background: #fff;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
        padding: 20px;
        margin-bottom: 20px;
    }

    .chatshop-welcome h2 {
        margin: 0 0 10px;
        color: #1d2327;
    }

    .chatshop-welcome p {
        margin: 0;
        color: #646970;
        font-size: 14px;
    }

    .chatshop-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .chatshop-stat-card {
        background: #fff;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
        padding: 20px;
        display: flex;
        align-items: center;
    }

    .stat-icon {
        margin-right: 15px;
    }

    .stat-icon .dashicons {
        font-size: 32px;
        width: 32px;
        height: 32px;
        color: #2271b1;
    }

    .stat-icon.status-connected .dashicons {
        color: #00a32a;
    }

    .stat-icon.status-disconnected .dashicons {
        color: #d63638;
    }

    .stat-content h3 {
        margin: 0 0 5px;
        font-size: 24px;
        font-weight: 600;
    }

    .stat-content p {
        margin: 0;
        color: #646970;
        font-size: 13px;
    }

    .chatshop-dashboard-section {
        background: #fff;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
        padding: 20px;
        margin-bottom: 20px;
    }

    .chatshop-setup-checklist .setup-item {
        display: flex;
        align-items: flex-start;
        padding: 15px 0;
        border-bottom: 1px solid #f0f0f1;
    }

    .chatshop-setup-checklist .setup-item:last-child {
        border-bottom: none;
    }

    .setup-icon {
        font-size: 20px;
        width: 30px;
        margin-right: 15px;
        margin-top: 5px;
    }

    .setup-item.completed .setup-icon {
        color: #00a32a;
    }

    .setup-item.pending .setup-icon {
        color: #dcdcde;
    }

    .setup-content h4 {
        margin: 0 0 5px;
        font-size: 14px;
    }

    .setup-content p {
        margin: 0 0 10px;
        color: #646970;
        font-size: 13px;
    }

    .chatshop-dashboard-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 20px;
    }

    .chatshop-dashboard-widget {
        background: #fff;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
        overflow: hidden;
    }

    .chatshop-dashboard-widget h3 {
        margin: 0;
        padding: 15px 20px;
        background: #f6f7f7;
        border-bottom: 1px solid #ccd0d4;
        font-size: 14px;
    }

    .widget-content {
        padding: 20px;
    }

    .no-data {
        color: #646970;
        font-style: italic;
        text-align: center;
        margin: 0;
    }

    .quick-actions {
        display: grid;
        gap: 10px;
    }

    .quick-action {
        display: flex;
        align-items: center;
        padding: 10px;
        text-decoration: none;
        border: 1px solid #ddd;
        border-radius: 4px;
        transition: background-color 0.2s;
    }

    .quick-action:hover {
        background: #f6f7f7;
        text-decoration: none;
    }

    .quick-action .dashicons {
        margin-right: 10px;
        color: #2271b1;
    }

    .chatshop-help-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
    }

    .help-card {
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 20px;
    }

    .help-card h4 {
        margin: 0 0 10px;
    }

    .help-card p {
        margin: 0 0 15px;
        color: #646970;
    }

    @media (max-width: 768px) {
        .chatshop-dashboard-grid {
            grid-template-columns: 1fr;
        }

        .chatshop-stats-grid {
            grid-template-columns: 1fr;
        }
    }
</style>