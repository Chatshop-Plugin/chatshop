<?php

/**
 * Payment Settings Admin Template
 *
 * @package ChatShop
 * @since   1.0.0
 */

namespace ChatShop;

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Get current payment settings
$paystack_options = chatshop_get_option('paystack', '', array());
$general_options = chatshop_get_option('general', '', array());

// Default values
$paystack_enabled = isset($paystack_options['enabled']) ? $paystack_options['enabled'] : false;
$test_mode = isset($paystack_options['test_mode']) ? $paystack_options['test_mode'] : true;
$test_public_key = isset($paystack_options['test_public_key']) ? $paystack_options['test_public_key'] : '';
$test_secret_key = isset($paystack_options['test_secret_key']) ? $paystack_options['test_secret_key'] : '';
$live_public_key = isset($paystack_options['live_public_key']) ? $paystack_options['live_public_key'] : '';
$live_secret_key = isset($paystack_options['live_secret_key']) ? $paystack_options['live_secret_key'] : '';
$webhook_url = home_url('/wp-admin/admin-ajax.php?action=chatshop_webhook&gateway=paystack');

// Check if premium features are available
$premium_available = chatshop_is_premium_feature_available('multiple_gateways');
?>

<div class="wrap">
    <h1><?php esc_html_e('Payment Settings', 'chatshop'); ?></h1>

    <form method="post" action="options.php" id="chatshop-payment-settings">
        <?php
        settings_fields('chatshop_payment_settings');
        do_settings_sections('chatshop_payment_settings');
        ?>

        <!-- Paystack Configuration -->
        <div class="chatshop-payment-gateway" id="paystack-settings">
            <h2 class="title">
                <span class="gateway-logo">
                    <img src="<?php echo esc_url(CHATSHOP_PLUGIN_URL . 'assets/icons/paystack.svg'); ?>" alt="Paystack" width="24" height="24">
                </span>
                <?php esc_html_e('Paystack Payment Gateway', 'chatshop'); ?>
                <span class="gateway-status <?php echo $paystack_enabled ? 'enabled' : 'disabled'; ?>">
                    <?php echo $paystack_enabled ? esc_html__('Enabled', 'chatshop') : esc_html__('Disabled', 'chatshop'); ?>
                </span>
            </h2>

            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="paystack_enabled"><?php esc_html_e('Enable Paystack', 'chatshop'); ?></label>
                        </th>
                        <td>
                            <fieldset>
                                <label for="paystack_enabled">
                                    <input type="checkbox"
                                        id="paystack_enabled"
                                        name="chatshop_paystack_options[enabled]"
                                        value="1"
                                        <?php checked($paystack_enabled, true); ?>>
                                    <?php esc_html_e('Enable Paystack payment gateway', 'chatshop'); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="paystack_test_mode"><?php esc_html_e('Test Mode', 'chatshop'); ?></label>
                        </th>
                        <td>
                            <fieldset>
                                <label for="paystack_test_mode">
                                    <input type="checkbox"
                                        id="paystack_test_mode"
                                        name="chatshop_paystack_options[test_mode]"
                                        value="1"
                                        <?php checked($test_mode, true); ?>>
                                    <?php esc_html_e('Enable test mode for development', 'chatshop'); ?>
                                </label>
                                <p class="description">
                                    <?php esc_html_e('When enabled, transactions will be processed using test API keys.', 'chatshop'); ?>
                                </p>
                            </fieldset>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Test Mode Keys -->
            <div id="test-keys-section" class="api-keys-section" <?php echo !$test_mode ? 'style="display:none;"' : ''; ?>>
                <h3><?php esc_html_e('Test API Keys', 'chatshop'); ?></h3>
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="test_public_key"><?php esc_html_e('Test Public Key', 'chatshop'); ?></label>
                            </th>
                            <td>
                                <input type="text"
                                    id="test_public_key"
                                    name="chatshop_paystack_options[test_public_key]"
                                    value="<?php echo esc_attr($test_public_key); ?>"
                                    class="regular-text"
                                    placeholder="pk_test_xxxxxxxxxx">
                                <p class="description">
                                    <?php esc_html_e('Your Paystack test public key (starts with pk_test_)', 'chatshop'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="test_secret_key"><?php esc_html_e('Test Secret Key', 'chatshop'); ?></label>
                            </th>
                            <td>
                                <input type="password"
                                    id="test_secret_key"
                                    name="chatshop_paystack_options[test_secret_key]"
                                    value="<?php echo esc_attr($test_secret_key); ?>"
                                    class="regular-text"
                                    placeholder="sk_test_xxxxxxxxxx">
                                <p class="description">
                                    <?php esc_html_e('Your Paystack test secret key (starts with sk_test_)', 'chatshop'); ?>
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Live Mode Keys -->
            <div id="live-keys-section" class="api-keys-section" <?php echo $test_mode ? 'style="display:none;"' : ''; ?>>
                <h3><?php esc_html_e('Live API Keys', 'chatshop'); ?></h3>
                <div class="notice notice-warning inline">
                    <p>
                        <?php esc_html_e('⚠️ Live mode will process real transactions. Ensure you have thoroughly tested in test mode first.', 'chatshop'); ?>
                    </p>
                </div>
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="live_public_key"><?php esc_html_e('Live Public Key', 'chatshop'); ?></label>
                            </th>
                            <td>
                                <input type="text"
                                    id="live_public_key"
                                    name="chatshop_paystack_options[live_public_key]"
                                    value="<?php echo esc_attr($live_public_key); ?>"
                                    class="regular-text"
                                    placeholder="pk_live_xxxxxxxxxx">
                                <p class="description">
                                    <?php esc_html_e('Your Paystack live public key (starts with pk_live_)', 'chatshop'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="live_secret_key"><?php esc_html_e('Live Secret Key', 'chatshop'); ?></label>
                            </th>
                            <td>
                                <input type="password"
                                    id="live_secret_key"
                                    name="chatshop_paystack_options[live_secret_key]"
                                    value="<?php echo esc_attr($live_secret_key); ?>"
                                    class="regular-text"
                                    placeholder="sk_live_xxxxxxxxxx">
                                <p class="description">
                                    <?php esc_html_e('Your Paystack live secret key (starts with sk_live_)', 'chatshop'); ?>
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Webhook Configuration -->
            <h3><?php esc_html_e('Webhook Configuration', 'chatshop'); ?></h3>
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="webhook_url"><?php esc_html_e('Webhook URL', 'chatshop'); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                id="webhook_url"
                                value="<?php echo esc_url($webhook_url); ?>"
                                class="regular-text"
                                readonly>
                            <button type="button" class="button button-small" id="copy-webhook-url">
                                <?php esc_html_e('Copy', 'chatshop'); ?>
                            </button>
                            <p class="description">
                                <?php
                                printf(
                                    esc_html__('Copy this URL and add it to your %sPaystack Dashboard%s under Settings > Webhooks.', 'chatshop'),
                                    '<a href="https://dashboard.paystack.com/#/settings/developer" target="_blank">',
                                    '</a>'
                                );
                                ?>
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Test Connection -->
            <div class="test-connection-section">
                <h3><?php esc_html_e('Test Connection', 'chatshop'); ?></h3>
                <p class="description">
                    <?php esc_html_e('Test your API connection to ensure everything is configured correctly.', 'chatshop'); ?>
                </p>
                <button type="button" class="button" id="test-paystack-connection">
                    <?php esc_html_e('Test Connection', 'chatshop'); ?>
                </button>
                <div id="test-result" class="test-result" style="display: none;"></div>
            </div>
        </div>

        <?php if (!$premium_available) : ?>
            <div class="chatshop-premium-notice">
                <h2><?php esc_html_e('Additional Payment Gateways', 'chatshop'); ?></h2>
                <div class="notice notice-info inline">
                    <p>
                        <?php esc_html_e('🚀 Unlock PayPal, Flutterwave, Razorpay, and more payment gateways with ChatShop Premium.', 'chatshop'); ?>
                        <a href="#" class="button button-primary"><?php esc_html_e('Upgrade Now', 'chatshop'); ?></a>
                    </p>
                </div>
            </div>
        <?php endif; ?>

        <?php submit_button(); ?>
    </form>
</div>

<style>
    .chatshop-payment-gateway {
        background: #fff;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
        padding: 20px;
        margin-bottom: 20px;
    }

    .chatshop-payment-gateway h2.title {
        margin-top: 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .gateway-logo img {
        vertical-align: middle;
    }

    .gateway-status {
        padding: 4px 8px;
        border-radius: 3px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        margin-left: auto;
    }

    .gateway-status.enabled {
        background: #d1e7dd;
        color: #0a3622;
    }

    .gateway-status.disabled {
        background: #f8d7da;
        color: #58151c;
    }

    .api-keys-section {
        background: #f9f9f9;
        border: 1px solid #e1e1e1;
        border-radius: 4px;
        padding: 15px;
        margin: 15px 0;
    }

    .test-connection-section {
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid #e1e1e1;
    }

    .test-result {
        margin-top: 10px;
        padding: 10px;
        border-radius: 4px;
    }

    .test-result.success {
        background: #d1e7dd;
        color: #0a3622;
        border: 1px solid #a3cfbb;
    }

    .test-result.error {
        background: #f8d7da;
        color: #58151c;
        border: 1px solid #f1aeb5;
    }

    .chatshop-premium-notice {
        background: #fff;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
        padding: 20px;
        margin-bottom: 20px;
    }
</style>

<script>
    jQuery(document).ready(function($) {
        // Toggle API keys sections based on test mode
        $('#paystack_test_mode').on('change', function() {
            if ($(this).is(':checked')) {
                $('#test-keys-section').show();
                $('#live-keys-section').hide();
            } else {
                $('#test-keys-section').hide();
                $('#live-keys-section').show();
            }
        });

        // Copy webhook URL
        $('#copy-webhook-url').on('click', function() {
            var webhookUrl = $('#webhook_url');
            webhookUrl.select();
            document.execCommand('copy');

            var button = $(this);
            var originalText = button.text();
            button.text('<?php esc_html_e('Copied!', 'chatshop'); ?>');

            setTimeout(function() {
                button.text(originalText);
            }, 2000);
        });

        // Test connection
        $('#test-paystack-connection').on('click', function() {
            var button = $(this);
            var resultDiv = $('#test-result');

            button.prop('disabled', true).text('<?php esc_html_e('Testing...', 'chatshop'); ?>');
            resultDiv.hide();

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'chatshop_test_gateway',
                    gateway_id: 'paystack',
                    nonce: '<?php echo wp_create_nonce('chatshop_admin_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        resultDiv.removeClass('error').addClass('success')
                            .html('<strong><?php esc_html_e('Success:', 'chatshop'); ?></strong> ' + response.data.message)
                            .show();
                    } else {
                        resultDiv.removeClass('success').addClass('error')
                            .html('<strong><?php esc_html_e('Error:', 'chatshop'); ?></strong> ' + response.data.message)
                            .show();
                    }
                },
                error: function() {
                    resultDiv.removeClass('success').addClass('error')
                        .html('<strong><?php esc_html_e('Error:', 'chatshop'); ?></strong> <?php esc_html_e('Failed to test connection. Please try again.', 'chatshop'); ?>')
                        .show();
                },
                complete: function() {
                    button.prop('disabled', false).text('<?php esc_html_e('Test Connection', 'chatshop'); ?>');
                }
            });
        });
    });
</script>