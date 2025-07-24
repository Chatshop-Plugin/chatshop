<?php

/**
 * WhatsApp Configuration Page
 *
 * @package ChatShop
 * @subpackage Admin\Partials
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap chatshop-whatsapp-config">
    <h1><?php _e('WhatsApp Configuration', 'chatshop'); ?></h1>

    <div class="chatshop-config-container">
        <div class="chatshop-config-main">
            <form method="post" action="options.php">
                <?php
                settings_fields('chatshop_whatsapp_settings_group');
                do_settings_sections('chatshop_whatsapp_settings');
                ?>

                <div class="chatshop-settings-section">
                    <div class="section-header">
                        <h2><?php _e('General Settings', 'chatshop'); ?></h2>
                        <p class="description">
                            <?php _e('Configure basic WhatsApp integration settings for your store.', 'chatshop'); ?>
                        </p>
                    </div>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="whatsapp_enabled"><?php _e('Enable WhatsApp Integration', 'chatshop'); ?></label>
                            </th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="checkbox" name="<?php echo $this->option_name; ?>[enabled]"
                                            id="whatsapp_enabled" value="1"
                                            <?php checked(1, $settings['enabled'] ?? false); ?> />
                                        <?php _e('Enable WhatsApp messaging for your store', 'chatshop'); ?>
                                    </label>
                                </fieldset>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="default_country_code"><?php _e('Default Country Code', 'chatshop'); ?></label>
                            </th>
                            <td>
                                <input type="text" name="<?php echo $this->option_name; ?>[default_country_code]"
                                    id="default_country_code" value="<?php echo esc_attr($settings['default_country_code'] ?? '234'); ?>"
                                    class="regular-text" placeholder="234" />
                                <p class="description">
                                    <?php _e('Default country code for phone numbers (without + sign). Example: 234 for Nigeria', 'chatshop'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="chatshop-settings-section">
                    <div class="section-header">
                        <h2><?php _e('WhatsApp Business API Configuration', 'chatshop'); ?></h2>
                        <p class="description">
                            <?php _e('Enter your WhatsApp Business API credentials. You can get these from your Facebook Business Manager.', 'chatshop'); ?>
                            <a href="https://developers.facebook.com/docs/whatsapp/business-management-api/get-started" target="_blank" class="button button-small">
                                <?php _e('Get API Credentials', 'chatshop'); ?>
                            </a>
                        </p>
                    </div>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="business_account_id"><?php _e('Business Account ID', 'chatshop'); ?> <span class="required">*</span></label>
                            </th>
                            <td>
                                <input type="text" name="<?php echo $this->option_name; ?>[business_account_id]"
                                    id="business_account_id" value="<?php echo esc_attr($settings['business_account_id'] ?? ''); ?>"
                                    class="regular-text" required />
                                <p class="description">
                                    <?php _e('Your WhatsApp Business Account ID from Facebook Business Manager', 'chatshop'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="phone_number_id"><?php _e('Phone Number ID', 'chatshop'); ?> <span class="required">*</span></label>
                            </th>
                            <td>
                                <input type="text" name="<?php echo $this->option_name; ?>[phone_number_id]"
                                    id="phone_number_id" value="<?php echo esc_attr($settings['phone_number_id'] ?? ''); ?>"
                                    class="regular-text" required />
                                <p class="description">
                                    <?php _e('Your WhatsApp Phone Number ID for sending messages', 'chatshop'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="access_token"><?php _e('Access Token', 'chatshop'); ?> <span class="required">*</span></label>
                            </th>
                            <td>
                                <input type="password" name="<?php echo $this->option_name; ?>[access_token]"
                                    id="access_token" value="<?php echo esc_attr($settings['access_token'] ?? ''); ?>"
                                    class="regular-text" required />
                                <p class="description">
                                    <?php _e('Your WhatsApp Business API permanent access token', 'chatshop'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="webhook_verify_token"><?php _e('Webhook Verify Token', 'chatshop'); ?></label>
                            </th>
                            <td>
                                <input type="text" name="<?php echo $this->option_name; ?>[webhook_verify_token]"
                                    id="webhook_verify_token" value="<?php echo esc_attr($settings['webhook_verify_token'] ?? wp_generate_password(32, false)); ?>"
                                    class="regular-text" />
                                <p class="description">
                                    <?php _e('Token for webhook verification. Use this URL for webhooks:', 'chatshop'); ?>
                                    <br><code><?php echo home_url('/chatshop/webhook/whatsapp'); ?></code>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="app_secret"><?php _e('App Secret', 'chatshop'); ?></label>
                            </th>
                            <td>
                                <input type="password" name="<?php echo $this->option_name; ?>[app_secret]"
                                    id="app_secret" value="<?php echo esc_attr($settings['app_secret'] ?? ''); ?>"
                                    class="regular-text" />
                                <p class="description">
                                    <?php _e('App secret for webhook signature verification (recommended for security)', 'chatshop'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="chatshop-settings-section">
                    <div class="section-header">
                        <h2><?php _e('Automation Settings', 'chatshop'); ?></h2>
                        <p class="description">
                            <?php _e('Configure automated messaging features to improve customer engagement.', 'chatshop'); ?>
                        </p>
                    </div>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="welcome_message_enabled"><?php _e('Welcome Messages', 'chatshop'); ?></label>
                            </th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="checkbox" name="<?php echo $this->option_name; ?>[welcome_message_enabled]"
                                            id="welcome_message_enabled" value="1"
                                            <?php checked(1, $settings['welcome_message_enabled'] ?? false); ?> />
                                        <?php _e('Send welcome message to new contacts', 'chatshop'); ?>
                                    </label>
                                </fieldset>
                            </td>
                        </tr>

                        <tr id="welcome_message_text_row" style="<?php echo empty($settings['welcome_message_enabled']) ? 'display: none;' : ''; ?>">
                            <th scope="row">
                                <label for="welcome_message_text"><?php _e('Welcome Message Text', 'chatshop'); ?></label>
                            </th>
                            <td>
                                <textarea name="<?php echo $this->option_name; ?>[welcome_message_text]"
                                    id="welcome_message_text" rows="5" class="large-text"><?php
                                                                                            echo esc_textarea($settings['welcome_message_text'] ?? __('Hello {name}! 👋 Welcome to our store. How can we help you today?', 'chatshop'));
                                                                                            ?></textarea>
                                <p class="description">
                                    <?php _e('Message sent to new contacts. Use {name} for personalization.', 'chatshop'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="order_notifications_enabled"><?php _e('Order Notifications', 'chatshop'); ?></label>
                            </th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="checkbox" name="<?php echo $this->option_name; ?>[order_notifications_enabled]"
                                            id="order_notifications_enabled" value="1"
                                            <?php checked(1, $settings['order_notifications_enabled'] ?? false); ?> />
                                        <?php _e('Send automated order status notifications via WhatsApp', 'chatshop'); ?>
                                    </label>
                                </fieldset>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="cart_abandonment_enabled"><?php _e('Cart Abandonment Recovery', 'chatshop'); ?></label>
                            </th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="checkbox" name="<?php echo $this->option_name; ?>[cart_abandonment_enabled]"
                                            id="cart_abandonment_enabled" value="1"
                                            <?php checked(1, $settings['cart_abandonment_enabled'] ?? false); ?> />
                                        <?php _e('Send WhatsApp messages for abandoned carts', 'chatshop'); ?>
                                    </label>
                                </fieldset>
                            </td>
                        </tr>

                        <tr id="cart_abandonment_delay_row" style="<?php echo empty($settings['cart_abandonment_enabled']) ? 'display: none;' : ''; ?>">
                            <th scope="row">
                                <label for="cart_abandonment_delay"><?php _e('Abandonment Delay', 'chatshop'); ?></label>
                            </th>
                            <td>
                                <input type="number" name="<?php echo $this->option_name; ?>[cart_abandonment_delay]"
                                    id="cart_abandonment_delay" value="<?php echo esc_attr($settings['cart_abandonment_delay'] ?? 24); ?>"
                                    min="1" max="48" class="small-text" />
                                <span><?php _e('hours', 'chatshop'); ?></span>
                                <p class="description">
                                    <?php _e('Time to wait before sending cart abandonment message (1-48 hours)', 'chatshop'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>

                <?php submit_button(__('Save Configuration', 'chatshop'), 'primary', 'submit', true, ['id' => 'save-config']); ?>
            </form>
        </div>

        <div class="chatshop-config-sidebar">
            <!-- Connection Test -->
            <div class="chatshop-sidebar-widget">
                <h3><?php _e('Test Connection', 'chatshop'); ?></h3>
                <p><?php _e('Test your WhatsApp API connection to ensure everything is working correctly.', 'chatshop'); ?></p>
                <button type="button" class="button button-secondary" id="test-api-connection">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <?php _e('Test Connection', 'chatshop'); ?>
                </button>
                <div id="connection-test-result"></div>
            </div>

            <!-- Setup Help -->
            <div class="chatshop-sidebar-widget">
                <h3><?php _e('Setup Help', 'chatshop'); ?></h3>
                <div class="help-steps">
                    <div class="help-step">
                        <strong><?php _e('Step 1:', 'chatshop'); ?></strong>
                        <p><?php _e('Create a WhatsApp Business account and verify your phone number.', 'chatshop'); ?></p>
                    </div>
                    <div class="help-step">
                        <strong><?php _e('Step 2:', 'chatshop'); ?></strong>
                        <p><?php _e('Set up a Facebook Business Manager account and link your WhatsApp Business account.', 'chatshop'); ?></p>
                    </div>
                    <div class="help-step">
                        <strong><?php _e('Step 3:', 'chatshop'); ?></strong>
                        <p><?php _e('Generate API credentials from Facebook Developer Console.', 'chatshop'); ?></p>
                    </div>
                    <div class="help-step">
                        <strong><?php _e('Step 4:', 'chatshop'); ?></strong>
                        <p><?php _e('Configure webhook URL in your WhatsApp Business API settings.', 'chatshop'); ?></p>
                    </div>
                </div>
                <a href="https://developers.facebook.com/docs/whatsapp/business-management-api/get-started"
                    target="_blank" class="button button-small">
                    <?php _e('Full Setup Guide', 'chatshop'); ?>
                </a>
            </div>

            <!-- Rate Limits Info -->
            <div class="chatshop-sidebar-widget">
                <h3><?php _e('Rate Limits', 'chatshop'); ?></h3>
                <p><?php _e('WhatsApp has message rate limits to prevent spam:', 'chatshop'); ?></p>
                <ul>
                    <li><?php _e('1,000 messages per day (new businesses)', 'chatshop'); ?></li>
                    <li><?php _e('10,000+ messages per day (verified businesses)', 'chatshop'); ?></li>
                    <li><?php _e('80 messages per minute', 'chatshop'); ?></li>
                </ul>
                <p class="description">
                    <?php _e('ChatShop automatically manages these limits for you.', 'chatshop'); ?>
                </p>
            </div>

            <!-- Webhook Info -->
            <div class="chatshop-sidebar-widget">
                <h3><?php _e('Webhook Configuration', 'chatshop'); ?></h3>
                <p><?php _e('Use this webhook URL in your WhatsApp Business API configuration:', 'chatshop'); ?></p>
                <div class="webhook-url">
                    <code><?php echo home_url('/chatshop/webhook/whatsapp'); ?></code>
                    <button type="button" class="button button-small copy-webhook-url" title="<?php esc_attr_e('Copy to clipboard', 'chatshop'); ?>">
                        <span class="dashicons dashicons-admin-page"></span>
                    </button>
                </div>
                <p class="description">
                    <?php _e('Make sure to set the verify token in your Facebook Developer Console.', 'chatshop'); ?>
                </p>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    jQuery(document).ready(function($) {
        // Toggle welcome message text field
        $('#welcome_message_enabled').on('change', function() {
            $('#welcome_message_text_row').toggle(this.checked);
        });

        // Toggle cart abandonment delay field
        $('#cart_abandonment_enabled').on('change', function() {
            $('#cart_abandonment_delay_row').toggle(this.checked);
        });

        // Test API connection
        $('#test-api-connection').on('click', function() {
            const button = $(this);
            const result = $('#connection-test-result');
            const originalText = button.html();

            button.html('<span class="spinner is-active"></span> ' + chatshopWhatsAppAdmin.strings.testing_connection)
                .prop('disabled', true);
            result.empty();

            // Get current form values
            const formData = {
                business_account_id: $('#business_account_id').val(),
                phone_number_id: $('#phone_number_id').val(),
                access_token: $('#access_token').val()
            };

            // Validate required fields
            if (!formData.business_account_id || !formData.phone_number_id || !formData.access_token) {
                result.html('<div class="notice notice-error inline"><p>' +
                    '<?php _e('Please fill in all required API credentials first.', 'chatshop'); ?>' +
                    '</p></div>');
                button.html(originalText).prop('disabled', false);
                return;
            }

            $.post(chatshopWhatsAppAdmin.ajax_url, {
                    action: 'chatshop_test_whatsapp_connection',
                    nonce: chatshopWhatsAppAdmin.nonce,
                    ...formData
                })
                .done(function(response) {
                    if (response.success) {
                        result.html('<div class="notice notice-success inline"><p>' +
                            '<span class="dashicons dashicons-yes-alt"></span> ' +
                            response.data + '</p></div>');
                    } else {
                        result.html('<div class="notice notice-error inline"><p>' +
                            '<span class="dashicons dashicons-dismiss"></span> ' +
                            (response.data || chatshopWhatsAppAdmin.strings.connection_failed) +
                            '</p></div>');
                    }
                })
                .fail(function() {
                    result.html('<div class="notice notice-error inline"><p>' +
                        '<span class="dashicons dashicons-dismiss"></span> ' +
                        chatshopWhatsAppAdmin.strings.connection_failed + '</p></div>');
                })
                .always(function() {
                    button.html(originalText).prop('disabled', false);
                });
        });

        // Copy webhook URL to clipboard
        $('.copy-webhook-url').on('click', function() {
            const webhookUrl = '<?php echo home_url('/chatshop/webhook/whatsapp'); ?>';

            if (navigator.clipboard) {
                navigator.clipboard.writeText(webhookUrl).then(function() {
                    showTempMessage('<?php _e('Webhook URL copied to clipboard!', 'chatshop'); ?>');
                });
            } else {
                // Fallback for older browsers
                const tempInput = $('<input>');
                $('body').append(tempInput);
                tempInput.val(webhookUrl).select();
                document.execCommand('copy');
                tempInput.remove();
                showTempMessage('<?php _e('Webhook URL copied to clipboard!', 'chatshop'); ?>');
            }
        });

        // Form submission with validation
        $('#save-config').on('click', function(e) {
            const requiredFields = ['#business_account_id', '#phone_number_id', '#access_token'];
            let isValid = true;

            requiredFields.forEach(function(field) {
                const $field = $(field);
                if (!$field.val().trim()) {
                    $field.addClass('error');
                    isValid = false;
                } else {
                    $field.removeClass('error');
                }
            });

            if (!isValid) {
                e.preventDefault();
                showNotice('<?php _e('Please fill in all required fields.', 'chatshop'); ?>', 'error');
                $('html, body').animate({
                    scrollTop: $('.error').first().offset().top - 100
                }, 500);
            }
        });

        // Clear field errors on input
        $('input, textarea').on('input', function() {
            $(this).removeClass('error');
        });

        function showNotice(message, type) {
            const notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            $('.wrap h1').after(notice);

            setTimeout(function() {
                notice.fadeOut();
            }, 5000);
        }

        function showTempMessage(message) {
            const tempMsg = $('<div class="temp-message">' + message + '</div>');
            $('body').append(tempMsg);

            setTimeout(function() {
                tempMsg.fadeOut(function() {
                    tempMsg.remove();
                });
            }, 2000);
        }
    });
</script>

<style>
    .chatshop-config-container {
        display: flex;
        gap: 20px;
        margin-top: 20px;
    }

    .chatshop-config-main {
        flex: 2;
    }

    .chatshop-config-sidebar {
        flex: 1;
        max-width: 300px;
    }

    .chatshop-settings-section {
        background: #fff;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
        margin-bottom: 20px;
    }

    .section-header {
        padding: 15px 20px;
        border-bottom: 1px solid #eee;
        background: #f9f9f9;
    }

    .section-header h2 {
        margin: 0 0 5px 0;
        font-size: 18px;
    }

    .section-header .description {
        margin: 0;
        color: #666;
    }

    .chatshop-settings-section .form-table {
        margin: 0;
        padding: 20px;
    }

    .chatshop-sidebar-widget {
        background: #fff;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
        padding: 15px;
        margin-bottom: 20px;
    }

    .chatshop-sidebar-widget h3 {
        margin-top: 0;
        margin-bottom: 10px;
    }

    .help-steps .help-step {
        margin-bottom: 15px;
        padding-bottom: 15px;
        border-bottom: 1px solid #eee;
    }

    .help-steps .help-step:last-child {
        border-bottom: none;
        margin-bottom: 0;
        padding-bottom: 0;
    }

    .webhook-url {
        display: flex;
        align-items: center;
        gap: 10px;
        background: #f0f0f1;
        padding: 10px;
        border-radius: 4px;
        margin: 10px 0;
    }

    .webhook-url code {
        flex: 1;
        background: none;
        padding: 0;
    }

    .copy-webhook-url {
        padding: 2px 6px !important;
        height: auto !important;
        min-height: auto !important;
    }

    .required {
        color: #d63638;
    }

    .error {
        border-color: #d63638 !important;
        box-shadow: 0 0 2px rgba(214, 54, 56, 0.8);
    }

    .notice.inline {
        margin: 10px 0;
        padding: 8px 12px;
    }

    .temp-message {
        position: fixed;
        top: 50px;
        right: 20px;
        background: #00a32a;
        color: white;
        padding: 10px 15px;
        border-radius: 4px;
        z-index: 9999;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
    }

    #connection-test-result .notice {
        margin-top: 10px;
    }

    @media (max-width: 768px) {
        .chatshop-config-container {
            flex-direction: column;
        }

        .chatshop-config-sidebar {
            max-width: none;
        }
    }
</style>