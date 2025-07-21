<?php

/**
 * Admin Payment Settings Page - Fixed
 *
 * @package ChatShop
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get the main ChatShop instance
$chatshop_instance = \ChatShop\ChatShop::instance();

// Get available payment gateways safely
$gateways = array();
if ($chatshop_instance && method_exists($chatshop_instance, 'get_registered_gateways')) {
    $gateways = $chatshop_instance->get_registered_gateways();
}

// Check premium features
$premium_features = false;
if (function_exists('chatshop_is_premium_feature_available')) {
    $premium_features = chatshop_is_premium_feature_available('multiple_gateways');
}

?>

<div class="wrap chatshop-admin">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <div class="chatshop-admin-header">
        <p class="description">
            <?php esc_html_e('Configure payment gateways for processing transactions through WhatsApp.', 'chatshop'); ?>
        </p>
    </div>

    <?php settings_errors('chatshop_payment_settings'); ?>

    <div class="chatshop-payment-settings">

        <!-- Payment Gateway Status -->
        <div class="chatshop-admin-section">
            <h2><?php esc_html_e('Gateway Status', 'chatshop'); ?></h2>

            <div class="chatshop-gateway-status">
                <?php if (empty($gateways)) : ?>
                    <div class="notice notice-warning inline">
                        <p>
                            <?php esc_html_e('No payment gateways are currently available.', 'chatshop'); ?>
                            <br>
                            <small><?php esc_html_e('This may be because the payment system is not fully initialized yet.', 'chatshop'); ?></small>
                        </p>
                        <p>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=chatshop-settings')); ?>" class="button button-secondary">
                                <?php esc_html_e('Check System Status', 'chatshop'); ?>
                            </a>
                        </p>
                    </div>
                <?php else : ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Gateway', 'chatshop'); ?></th>
                                <th><?php esc_html_e('Status', 'chatshop'); ?></th>
                                <th><?php esc_html_e('Test Mode', 'chatshop'); ?></th>
                                <th><?php esc_html_e('Actions', 'chatshop'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($gateways as $gateway_id => $gateway) : ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html($gateway->get_title()); ?></strong>
                                        <br>
                                        <small class="description"><?php echo esc_html($gateway->get_description()); ?></small>
                                    </td>
                                    <td>
                                        <?php if ($gateway->is_enabled()) : ?>
                                            <span class="chatshop-status chatshop-status-enabled">
                                                <?php esc_html_e('Enabled', 'chatshop'); ?>
                                            </span>
                                        <?php else : ?>
                                            <span class="chatshop-status chatshop-status-disabled">
                                                <?php esc_html_e('Disabled', 'chatshop'); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($gateway->is_test_mode()) : ?>
                                            <span class="chatshop-status chatshop-status-test">
                                                <?php esc_html_e('Test Mode', 'chatshop'); ?>
                                            </span>
                                        <?php else : ?>
                                            <span class="chatshop-status chatshop-status-live">
                                                <?php esc_html_e('Live Mode', 'chatshop'); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="#gateway-<?php echo esc_attr($gateway_id); ?>"
                                            class="button button-secondary chatshop-configure-gateway">
                                            <?php esc_html_e('Configure', 'chatshop'); ?>
                                        </a>

                                        <?php if (method_exists($gateway, 'test_connection')) : ?>
                                            <button type="button"
                                                class="button button-secondary chatshop-test-gateway"
                                                data-gateway="<?php echo esc_attr($gateway_id); ?>">
                                                <?php esc_html_e('Test Connection', 'chatshop'); ?>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Gateway Configuration -->
        <?php foreach ($gateways as $gateway_id => $gateway) : ?>
            <div id="gateway-<?php echo esc_attr($gateway_id); ?>" class="chatshop-admin-section chatshop-gateway-config">
                <h2>
                    <?php echo esc_html($gateway->get_title()); ?>
                    <?php esc_html_e('Configuration', 'chatshop'); ?>
                </h2>

                <form method="post" action="options.php" class="chatshop-gateway-form">
                    <?php
                    settings_fields("chatshop_{$gateway_id}_options");
                    $config_fields = method_exists($gateway, 'get_config_fields') ? $gateway->get_config_fields() : array();
                    $current_settings = get_option("chatshop_{$gateway_id}_options", array());
                    ?>

                    <?php if (!empty($config_fields)) : ?>
                        <table class="form-table" role="presentation">
                            <?php foreach ($config_fields as $field_id => $field) : ?>
                                <?php
                                $field_name = "chatshop_{$gateway_id}_options[{$field_id}]";
                                $field_value = isset($current_settings[$field_id]) ? $current_settings[$field_id] : ($field['default'] ?? '');
                                ?>
                                <tr>
                                    <th scope="row">
                                        <label for="<?php echo esc_attr($field_name); ?>">
                                            <?php echo esc_html($field['title']); ?>
                                        </label>
                                    </th>
                                    <td>
                                        <?php switch ($field['type']):
                                            case 'checkbox': ?>
                                                <fieldset>
                                                    <legend class="screen-reader-text">
                                                        <span><?php echo esc_html($field['title']); ?></span>
                                                    </legend>
                                                    <label for="<?php echo esc_attr($field_name); ?>">
                                                        <input type="checkbox"
                                                            id="<?php echo esc_attr($field_name); ?>"
                                                            name="<?php echo esc_attr($field_name); ?>"
                                                            value="yes"
                                                            <?php checked($field_value, 'yes'); ?> />
                                                        <?php echo esc_html($field['description'] ?? ''); ?>
                                                    </label>
                                                </fieldset>
                                            <?php break;

                                            case 'password': ?>
                                                <input type="password"
                                                    id="<?php echo esc_attr($field_name); ?>"
                                                    name="<?php echo esc_attr($field_name); ?>"
                                                    value="<?php echo esc_attr($field_value); ?>"
                                                    class="regular-text" />
                                                <?php if (!empty($field['description'])) : ?>
                                                    <p class="description"><?php echo esc_html($field['description']); ?></p>
                                                <?php endif; ?>
                                            <?php break;

                                            case 'textarea': ?>
                                                <textarea id="<?php echo esc_attr($field_name); ?>"
                                                    name="<?php echo esc_attr($field_name); ?>"
                                                    rows="3"
                                                    class="large-text"><?php echo esc_textarea($field_value); ?></textarea>
                                                <?php if (!empty($field['description'])) : ?>
                                                    <p class="description"><?php echo esc_html($field['description']); ?></p>
                                                <?php endif; ?>
                                            <?php break;

                                            case 'select': ?>
                                                <select id="<?php echo esc_attr($field_name); ?>"
                                                    name="<?php echo esc_attr($field_name); ?>">
                                                    <?php if (!empty($field['options'])) : ?>
                                                        <?php foreach ($field['options'] as $option_value => $option_label) : ?>
                                                            <option value="<?php echo esc_attr($option_value); ?>"
                                                                <?php selected($field_value, $option_value); ?>>
                                                                <?php echo esc_html($option_label); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </select>
                                                <?php if (!empty($field['description'])) : ?>
                                                    <p class="description"><?php echo esc_html($field['description']); ?></p>
                                                <?php endif; ?>
                                            <?php break;

                                            default: // text 
                                            ?>
                                                <input type="text"
                                                    id="<?php echo esc_attr($field_name); ?>"
                                                    name="<?php echo esc_attr($field_name); ?>"
                                                    value="<?php echo esc_attr($field_value); ?>"
                                                    class="regular-text" />
                                                <?php if (!empty($field['description'])) : ?>
                                                    <p class="description"><?php echo esc_html($field['description']); ?></p>
                                                <?php endif; ?>
                                        <?php break;
                                        endswitch; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    <?php else : ?>
                        <p class="description">
                            <?php esc_html_e('No configuration options available for this gateway.', 'chatshop'); ?>
                        </p>
                    <?php endif; ?>

                    <?php if (method_exists($gateway, 'get_webhook_url')) : ?>
                        <div class="chatshop-gateway-webhook-info">
                            <h4><?php esc_html_e('Webhook Information', 'chatshop'); ?></h4>
                            <p class="description">
                                <?php esc_html_e('Configure this webhook URL in your payment gateway dashboard:', 'chatshop'); ?>
                            </p>
                            <code class="chatshop-webhook-url">
                                <?php echo esc_url($gateway->get_webhook_url()); ?>
                            </code>
                            <button type="button" class="button button-small chatshop-copy-webhook"
                                data-webhook="<?php echo esc_attr($gateway->get_webhook_url()); ?>">
                                <?php esc_html_e('Copy URL', 'chatshop'); ?>
                            </button>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($config_fields)) : ?>
                        <?php submit_button(sprintf(__('Save %s Settings', 'chatshop'), $gateway->get_title())); ?>
                    <?php endif; ?>
                </form>
            </div>
        <?php endforeach; ?>

        <!-- Quick Setup for New Installations -->
        <?php if (empty($gateways)) : ?>
            <div class="chatshop-admin-section">
                <h2><?php esc_html_e('Quick Setup', 'chatshop'); ?></h2>
                <p><?php esc_html_e('It looks like ChatShop is not fully set up yet. Let\'s get you started!', 'chatshop'); ?></p>

                <div class="chatshop-setup-steps">
                    <div class="chatshop-setup-step">
                        <h3><?php esc_html_e('Step 1: Enable the Plugin', 'chatshop'); ?></h3>
                        <p><?php esc_html_e('Make sure ChatShop is enabled in your general settings.', 'chatshop'); ?></p>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=chatshop-settings')); ?>" class="button button-secondary">
                            <?php esc_html_e('General Settings', 'chatshop'); ?>
                        </a>
                    </div>

                    <div class="chatshop-setup-step">
                        <h3><?php esc_html_e('Step 2: Check System Requirements', 'chatshop'); ?></h3>
                        <p><?php esc_html_e('Ensure all system requirements are met for proper functionality.', 'chatshop'); ?></p>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=chatshop-settings')); ?>" class="button button-secondary">
                            <?php esc_html_e('System Info', 'chatshop'); ?>
                        </a>
                    </div>

                    <div class="chatshop-setup-step">
                        <h3><?php esc_html_e('Step 3: Contact Support', 'chatshop'); ?></h3>
                        <p><?php esc_html_e('If you continue to have issues, please contact our support team.', 'chatshop'); ?></p>
                        <a href="#" class="button button-secondary">
                            <?php esc_html_e('Get Support', 'chatshop'); ?>
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Premium Features -->
        <?php if (!$premium_features) : ?>
            <div class="chatshop-admin-section chatshop-premium-notice">
                <h2><?php esc_html_e('Premium Payment Features', 'chatshop'); ?></h2>
                <div class="notice notice-info inline">
                    <p>
                        <strong><?php esc_html_e('Upgrade to Premium for:', 'chatshop'); ?></strong>
                    </p>
                    <ul>
                        <li><?php esc_html_e('Multiple Payment Gateways (PayPal, Flutterwave, Razorpay)', 'chatshop'); ?></li>
                        <li><?php esc_html_e('Advanced Payment Analytics', 'chatshop'); ?></li>
                        <li><?php esc_html_e('Abandoned Payment Recovery', 'chatshop'); ?></li>
                        <li><?php esc_html_e('Multi-currency Support', 'chatshop'); ?></li>
                        <li><?php esc_html_e('Custom Payment Branding', 'chatshop'); ?></li>
                    </ul>
                    <p>
                        <a href="#" class="button button-primary"><?php esc_html_e('Upgrade Now', 'chatshop'); ?></a>
                    </p>
                </div>
            </div>
        <?php endif; ?>

        <!-- System Debug Information -->
        <?php if (defined('WP_DEBUG') && WP_DEBUG) : ?>
            <div class="chatshop-admin-section">
                <h2><?php esc_html_e('Debug Information', 'chatshop'); ?></h2>
                <p class="description"><?php esc_html_e('This debug information is only visible when WordPress debug mode is enabled.', 'chatshop'); ?></p>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e('ChatShop Instance', 'chatshop'); ?></th>
                        <td><code><?php echo $chatshop_instance ? 'Available' : 'Not Available'; ?></code></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Payment Manager', 'chatshop'); ?></th>
                        <td><code><?php echo ($chatshop_instance && method_exists($chatshop_instance, 'get_payment_manager')) ? 'Available' : 'Not Available'; ?></code></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Gateway Count', 'chatshop'); ?></th>
                        <td><code><?php echo count($gateways); ?></code></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Gateway Classes', 'chatshop'); ?></th>
                        <td>
                            <code>
                                <?php
                                if (class_exists('ChatShop\ChatShop_Paystack_Gateway')) {
                                    echo 'Paystack: Available, ';
                                } else {
                                    echo 'Paystack: Missing, ';
                                }

                                if (class_exists('ChatShop\ChatShop_Payment_Manager')) {
                                    echo 'Manager: Available, ';
                                } else {
                                    echo 'Manager: Missing, ';
                                }

                                if (class_exists('ChatShop\ChatShop_Payment_Factory')) {
                                    echo 'Factory: Available';
                                } else {
                                    echo 'Factory: Missing';
                                }
                                ?>
                            </code>
                        </td>
                    </tr>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Test Gateway Modal -->
<div id="chatshop-test-modal" class="chatshop-modal" style="display: none;">
    <div class="chatshop-modal-content">
        <div class="chatshop-modal-header">
            <h3><?php esc_html_e('Test Gateway Connection', 'chatshop'); ?></h3>
            <button type="button" class="chatshop-modal-close">&times;</button>
        </div>
        <div class="chatshop-modal-body">
            <div class="chatshop-test-loading">
                <p><?php esc_html_e('Testing connection...', 'chatshop'); ?></p>
            </div>
            <div class="chatshop-test-result" style="display: none;">
                <!-- Results will be populated via JavaScript -->
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    jQuery(document).ready(function($) {
        // Test Gateway Connection
        $('.chatshop-test-gateway').on('click', function(e) {
            e.preventDefault();

            var gateway = $(this).data('gateway');
            var modal = $('#chatshop-test-modal');

            modal.show();
            $('.chatshop-test-loading').show();
            $('.chatshop-test-result').hide();

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'chatshop_test_gateway',
                    gateway_id: gateway,
                    nonce: '<?php echo wp_create_nonce('chatshop_admin_nonce'); ?>'
                },
                success: function(response) {
                    $('.chatshop-test-loading').hide();
                    $('.chatshop-test-result').show();

                    if (response.success) {
                        $('.chatshop-test-result').html(
                            '<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>'
                        );
                    } else {
                        $('.chatshop-test-result').html(
                            '<div class="notice notice-error inline"><p>' + (response.data ? response.data.message : response.message || '<?php esc_html_e('Connection test failed.', 'chatshop'); ?>') + '</p></div>'
                        );
                    }
                },
                error: function() {
                    $('.chatshop-test-loading').hide();
                    $('.chatshop-test-result').show().html(
                        '<div class="notice notice-error inline"><p><?php esc_html_e('Connection test failed.', 'chatshop'); ?></p></div>'
                    );
                }
            });
        });

        // Close Modal
        $('.chatshop-modal-close, .chatshop-modal').on('click', function(e) {
            if (e.target === this) {
                $('#chatshop-test-modal').hide();
            }
        });

        // Copy Webhook URL
        $('.chatshop-copy-webhook').on('click', function(e) {
            e.preventDefault();

            var webhook = $(this).data('webhook');
            var button = $(this);

            if (navigator.clipboard) {
                navigator.clipboard.writeText(webhook).then(function() {
                    var originalText = button.text();
                    button.text('<?php esc_html_e('Copied!', 'chatshop'); ?>');

                    setTimeout(function() {
                        button.text(originalText);
                    }, 2000);
                });
            } else {
                // Fallback for older browsers
                var textArea = document.createElement('textarea');
                textArea.value = webhook;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);

                var originalText = button.text();
                button.text('<?php esc_html_e('Copied!', 'chatshop'); ?>');

                setTimeout(function() {
                    button.text(originalText);
                }, 2000);
            }
        });

        // Smooth scroll to gateway configuration
        $('.chatshop-configure-gateway').on('click', function(e) {
            e.preventDefault();

            var target = $(this).attr('href');
            if ($(target).length) {
                $('html, body').animate({
                    scrollTop: $(target).offset().top - 50
                }, 500);
            }
        });
    });
</script>