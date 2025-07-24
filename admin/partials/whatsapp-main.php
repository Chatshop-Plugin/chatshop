<?php

/**
 * WhatsApp Main Dashboard Page
 *
 * @package ChatShop
 * @subpackage Admin\Partials
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap chatshop-whatsapp-main">
    <h1><?php _e('WhatsApp Integration', 'chatshop'); ?></h1>

    <?php if (!$is_configured): ?>
        <div class="notice notice-warning">
            <p>
                <strong><?php _e('WhatsApp not configured!', 'chatshop'); ?></strong>
                <?php _e('Please configure your WhatsApp Business API credentials to get started.', 'chatshop'); ?>
                <a href="<?php echo admin_url('admin.php?page=chatshop-whatsapp-config'); ?>" class="button button-primary">
                    <?php _e('Configure Now', 'chatshop'); ?>
                </a>
            </p>
        </div>
    <?php else: ?>
        <div class="notice notice-success">
            <p>
                <strong><?php _e('WhatsApp is configured and ready!', 'chatshop'); ?></strong>
                <?php _e('Your store is connected to WhatsApp Business API.', 'chatshop'); ?>
            </p>
        </div>
    <?php endif; ?>

    <div class="chatshop-dashboard-grid">
        <!-- Quick Stats -->
        <div class="chatshop-widget chatshop-stats-widget">
            <h3><?php _e('Quick Stats', 'chatshop'); ?></h3>
            <div class="chatshop-stats-grid" id="whatsapp-stats">
                <div class="stat-item">
                    <div class="stat-number" id="total-contacts">-</div>
                    <div class="stat-label"><?php _e('Total Contacts', 'chatshop'); ?></div>
                </div>
                <div class="stat-item">
                    <div class="stat-number" id="messages-today">-</div>
                    <div class="stat-label"><?php _e('Messages Today', 'chatshop'); ?></div>
                </div>
                <div class="stat-item">
                    <div class="stat-number" id="active-campaigns">-</div>
                    <div class="stat-label"><?php _e('Active Campaigns', 'chatshop'); ?></div>
                </div>
                <div class="stat-item">
                    <div class="stat-number" id="response-rate">-</div>
                    <div class="stat-label"><?php _e('Response Rate', 'chatshop'); ?></div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="chatshop-widget chatshop-actions-widget">
            <h3><?php _e('Quick Actions', 'chatshop'); ?></h3>
            <div class="chatshop-actions">
                <a href="<?php echo admin_url('admin.php?page=chatshop-whatsapp-contacts'); ?>" class="button button-primary">
                    <span class="dashicons dashicons-groups"></span>
                    <?php _e('Manage Contacts', 'chatshop'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=chatshop-whatsapp-campaigns'); ?>" class="button button-secondary">
                    <span class="dashicons dashicons-megaphone"></span>
                    <?php _e('Create Campaign', 'chatshop'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=chatshop-whatsapp-templates'); ?>" class="button button-secondary">
                    <span class="dashicons dashicons-format-chat"></span>
                    <?php _e('Message Templates', 'chatshop'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=chatshop-whatsapp-analytics'); ?>" class="button button-secondary">
                    <span class="dashicons dashicons-chart-area"></span>
                    <?php _e('View Analytics', 'chatshop'); ?>
                </a>
            </div>
        </div>

        <!-- Connection Status -->
        <div class="chatshop-widget chatshop-status-widget">
            <h3><?php _e('Connection Status', 'chatshop'); ?></h3>
            <div class="connection-status" id="connection-status">
                <div class="status-indicator">
                    <span class="status-dot <?php echo $is_configured ? 'status-connected' : 'status-disconnected'; ?>"></span>
                    <span class="status-text">
                        <?php echo $is_configured ? __('Connected', 'chatshop') : __('Not Connected', 'chatshop'); ?>
                    </span>
                </div>

                <?php if ($is_configured): ?>
                    <div class="connection-details">
                        <p><strong><?php _e('Phone Number ID:', 'chatshop'); ?></strong>
                            <code><?php echo esc_html($settings['phone_number_id'] ?? 'Not set'); ?></code>
                        </p>
                        <p><strong><?php _e('Business Account:', 'chatshop'); ?></strong>
                            <code><?php echo esc_html($settings['business_account_id'] ?? 'Not set'); ?></code>
                        </p>
                    </div>

                    <button type="button" class="button button-secondary" id="test-connection">
                        <?php _e('Test Connection', 'chatshop'); ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Test Message -->
        <?php if ($is_configured): ?>
            <div class="chatshop-widget chatshop-test-widget">
                <h3><?php _e('Send Test Message', 'chatshop'); ?></h3>
                <form id="test-message-form">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="test-phone"><?php _e('Phone Number', 'chatshop'); ?></label>
                            </th>
                            <td>
                                <input type="tel" id="test-phone" name="phone_number" class="regular-text"
                                    placeholder="+1234567890" required />
                                <p class="description"><?php _e('Enter phone number with country code', 'chatshop'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="test-message"><?php _e('Message', 'chatshop'); ?></label>
                            </th>
                            <td>
                                <textarea id="test-message" name="message" rows="4" class="large-text"
                                    placeholder="<?php esc_attr_e('Enter your test message...', 'chatshop'); ?>" required></textarea>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <button type="submit" class="button button-primary">
                            <?php _e('Send Test Message', 'chatshop'); ?>
                        </button>
                    </p>
                </form>
            </div>
        <?php endif; ?>

        <!-- Recent Activity -->
        <div class="chatshop-widget chatshop-activity-widget">
            <h3><?php _e('Recent Activity', 'chatshop'); ?></h3>
            <div class="activity-list" id="recent-activity">
                <div class="activity-loading">
                    <span class="spinner is-active"></span>
                    <?php _e('Loading recent activity...', 'chatshop'); ?>
                </div>
            </div>
        </div>

        <!-- Quick Setup Guide -->
        <?php if (!$is_configured): ?>
            <div class="chatshop-widget chatshop-setup-widget">
                <h3><?php _e('Quick Setup Guide', 'chatshop'); ?></h3>
                <div class="setup-steps">
                    <div class="setup-step">
                        <span class="step-number">1</span>
                        <div class="step-content">
                            <h4><?php _e('Create WhatsApp Business Account', 'chatshop'); ?></h4>
                            <p><?php _e('Set up your WhatsApp Business account and get verified.', 'chatshop'); ?></p>
                            <a href="https://business.whatsapp.com/" target="_blank" class="button button-small">
                                <?php _e('Get Started', 'chatshop'); ?>
                            </a>
                        </div>
                    </div>

                    <div class="setup-step">
                        <span class="step-number">2</span>
                        <div class="step-content">
                            <h4><?php _e('Get API Credentials', 'chatshop'); ?></h4>
                            <p><?php _e('Obtain your Business Account ID, Phone Number ID, and Access Token.', 'chatshop'); ?></p>
                            <a href="https://developers.facebook.com/docs/whatsapp/business-management-api/get-started" target="_blank" class="button button-small">
                                <?php _e('View Guide', 'chatshop'); ?>
                            </a>
                        </div>
                    </div>

                    <div class="setup-step">
                        <span class="step-number">3</span>
                        <div class="step-content">
                            <h4><?php _e('Configure ChatShop', 'chatshop'); ?></h4>
                            <p><?php _e('Enter your API credentials in the ChatShop configuration page.', 'chatshop'); ?></p>
                            <a href="<?php echo admin_url('admin.php?page=chatshop-whatsapp-config'); ?>" class="button button-small button-primary">
                                <?php _e('Configure Now', 'chatshop'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Help & Support -->
        <div class="chatshop-widget chatshop-help-widget">
            <h3><?php _e('Help & Support', 'chatshop'); ?></h3>
            <div class="help-links">
                <a href="#" class="help-link">
                    <span class="dashicons dashicons-book"></span>
                    <?php _e('Documentation', 'chatshop'); ?>
                </a>
                <a href="#" class="help-link">
                    <span class="dashicons dashicons-video-alt3"></span>
                    <?php _e('Video Tutorials', 'chatshop'); ?>
                </a>
                <a href="#" class="help-link">
                    <span class="dashicons dashicons-sos"></span>
                    <?php _e('Get Support', 'chatshop'); ?>
                </a>
                <a href="#" class="help-link">
                    <span class="dashicons dashicons-groups"></span>
                    <?php _e('Community Forum', 'chatshop'); ?>
                </a>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    jQuery(document).ready(function($) {
        // Load stats on page load
        loadQuickStats();
        loadRecentActivity();

        // Test connection
        $('#test-connection').on('click', function() {
            const button = $(this);
            const originalText = button.text();

            button.text(chatshopWhatsAppAdmin.strings.testing_connection).prop('disabled', true);

            $.post(chatshopWhatsAppAdmin.ajax_url, {
                    action: 'chatshop_test_whatsapp_connection',
                    nonce: chatshopWhatsAppAdmin.nonce
                })
                .done(function(response) {
                    if (response.success) {
                        showNotice(chatshopWhatsAppAdmin.strings.connection_successful, 'success');
                        $('.status-dot').removeClass('status-disconnected').addClass('status-connected');
                        $('.status-text').text('<?php _e('Connected', 'chatshop'); ?>');
                    } else {
                        showNotice(response.data || chatshopWhatsAppAdmin.strings.connection_failed, 'error');
                    }
                })
                .fail(function() {
                    showNotice(chatshopWhatsAppAdmin.strings.connection_failed, 'error');
                })
                .always(function() {
                    button.text(originalText).prop('disabled', false);
                });
        });

        // Test message form
        $('#test-message-form').on('submit', function(e) {
            e.preventDefault();

            const form = $(this);
            const submitButton = form.find('button[type="submit"]');
            const originalText = submitButton.text();

            submitButton.text(chatshopWhatsAppAdmin.strings.sending_test_message).prop('disabled', true);

            $.post(chatshopWhatsAppAdmin.ajax_url, {
                    action: 'chatshop_send_test_message',
                    nonce: chatshopWhatsAppAdmin.nonce,
                    phone_number: $('#test-phone').val(),
                    message: $('#test-message').val()
                })
                .done(function(response) {
                    if (response.success) {
                        showNotice(chatshopWhatsAppAdmin.strings.test_message_sent, 'success');
                        form[0].reset();
                    } else {
                        showNotice(response.data || chatshopWhatsAppAdmin.strings.test_message_failed, 'error');
                    }
                })
                .fail(function() {
                    showNotice(chatshopWhatsAppAdmin.strings.test_message_failed, 'error');
                })
                .always(function() {
                    submitButton.text(originalText).prop('disabled', false);
                });
        });

        function loadQuickStats() {
            $.post(chatshopWhatsAppAdmin.ajax_url, {
                    action: 'chatshop_get_contact_stats',
                    nonce: chatshopWhatsAppAdmin.nonce
                })
                .done(function(response) {
                    if (response.success) {
                        const stats = response.data;
                        $('#total-contacts').text(stats.total_contacts || 0);
                        $('#messages-today').text((stats.messages_sent_today || 0) + (stats.messages_received_today || 0));
                        $('#active-campaigns').text(stats.active_campaigns || 0);
                        $('#response-rate').text((stats.response_rate || 0) + '%');
                    }
                });
        }

        function loadRecentActivity() {
            // Placeholder for recent activity loading
            setTimeout(function() {
                $('#recent-activity').html('<p><?php _e('No recent activity to display.', 'chatshop'); ?></p>');
            }, 1000);
        }

        function showNotice(message, type) {
            const notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            $('.wrap h1').after(notice);

            setTimeout(function() {
                notice.fadeOut();
            }, 5000);
        }
    });
</script>