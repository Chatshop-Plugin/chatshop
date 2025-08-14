<?php

/**
 * Provide a admin area view for general settings
 *
 * @package ChatShop
 * @subpackage ChatShop/admin/partials
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Handle form submission
if (isset($_POST['submit']) && wp_verify_nonce($_POST['_wpnonce'], 'chatshop_general_settings')) {
    $options = array(
        'plugin_enabled'        => isset($_POST['chatshop_general_options']['plugin_enabled']) ? 1 : 0,
        'debug_mode'           => isset($_POST['chatshop_general_options']['debug_mode']) ? 1 : 0,
        'log_level'            => sanitize_text_field($_POST['chatshop_general_options']['log_level']),
        'show_floating_button' => isset($_POST['chatshop_general_options']['show_floating_button']) ? 1 : 0,
        'auto_archive_days'    => absint($_POST['chatshop_general_options']['auto_archive_days']),
        'currency'             => sanitize_text_field($_POST['chatshop_general_options']['currency']),
        'timezone'             => sanitize_text_field($_POST['chatshop_general_options']['timezone'])
    );

    update_option('chatshop_general_options', $options);

    echo '<div class="notice notice-success"><p>' . __('Settings saved successfully!', 'chatshop') . '</p></div>';
}

// Get current options
$options = wp_parse_args(get_option('chatshop_general_options', array()), array(
    'plugin_enabled'        => true,
    'debug_mode'           => false,
    'log_level'            => 'error',
    'show_floating_button' => false,
    'auto_archive_days'    => 365,
    'currency'             => 'NGN',
    'timezone'             => get_option('timezone_string', 'UTC')
));
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <form method="post" action="">
        <?php wp_nonce_field('chatshop_general_settings'); ?>

        <table class="form-table" role="presentation">
            <tbody>
                <!-- Plugin Status -->
                <tr>
                    <th scope="row">
                        <label for="plugin_enabled"><?php _e('Plugin Status', 'chatshop'); ?></label>
                    </th>
                    <td>
                        <fieldset>
                            <label for="plugin_enabled">
                                <input type="checkbox"
                                    id="plugin_enabled"
                                    name="chatshop_general_options[plugin_enabled]"
                                    value="1"
                                    <?php checked(1, $options['plugin_enabled']); ?> />
                                <?php _e('Enable ChatShop functionality', 'chatshop'); ?>
                            </label>
                            <p class="description">
                                <?php _e('Disable this to temporarily turn off all ChatShop features without deactivating the plugin.', 'chatshop'); ?>
                            </p>
                        </fieldset>
                    </td>
                </tr>

                <!-- Floating WhatsApp Button -->
                <tr>
                    <th scope="row">
                        <label for="show_floating_button"><?php _e('Floating WhatsApp Button', 'chatshop'); ?></label>
                    </th>
                    <td>
                        <fieldset>
                            <label for="show_floating_button">
                                <input type="checkbox"
                                    id="show_floating_button"
                                    name="chatshop_general_options[show_floating_button]"
                                    value="1"
                                    <?php checked(1, $options['show_floating_button']); ?> />
                                <?php _e('Show floating WhatsApp button on all pages', 'chatshop'); ?>
                            </label>
                            <p class="description">
                                <?php _e('Display a floating WhatsApp button in the bottom-right corner of your website.', 'chatshop'); ?>
                            </p>
                        </fieldset>
                    </td>
                </tr>

                <!-- Default Currency -->
                <tr>
                    <th scope="row">
                        <label for="currency"><?php _e('Default Currency', 'chatshop'); ?></label>
                    </th>
                    <td>
                        <select id="currency" name="chatshop_general_options[currency]" class="regular-text">
                            <option value="NGN" <?php selected($options['currency'], 'NGN'); ?>>Nigerian Naira (NGN)</option>
                            <option value="USD" <?php selected($options['currency'], 'USD'); ?>>US Dollar (USD)</option>
                            <option value="EUR" <?php selected($options['currency'], 'EUR'); ?>>Euro (EUR)</option>
                            <option value="GBP" <?php selected($options['currency'], 'GBP'); ?>>British Pound (GBP)</option>
                            <option value="ZAR" <?php selected($options['currency'], 'ZAR'); ?>>South African Rand (ZAR)</option>
                            <option value="KES" <?php selected($options['currency'], 'KES'); ?>>Kenyan Shilling (KES)</option>
                            <option value="GHS" <?php selected($options['currency'], 'GHS'); ?>>Ghanaian Cedi (GHS)</option>
                        </select>
                        <p class="description">
                            <?php _e('Default currency for payment links and transactions.', 'chatshop'); ?>
                        </p>
                    </td>
                </tr>

                <!-- Timezone -->
                <tr>
                    <th scope="row">
                        <label for="timezone"><?php _e('Timezone', 'chatshop'); ?></label>
                    </th>
                    <td>
                        <select id="timezone" name="chatshop_general_options[timezone]" class="regular-text">
                            <?php
                            $selected_timezone = $options['timezone'];
                            $timezones = timezone_identifiers_list();

                            foreach ($timezones as $timezone) {
                                $selected = selected($selected_timezone, $timezone, false);
                                echo "<option value=\"{$timezone}\" {$selected}>{$timezone}</option>";
                            }
                            ?>
                        </select>
                        <p class="description">
                            <?php _e('Timezone for displaying dates and scheduling campaigns.', 'chatshop'); ?>
                        </p>
                    </td>
                </tr>

                <!-- Auto Archive -->
                <tr>
                    <th scope="row">
                        <label for="auto_archive_days"><?php _e('Auto Archive Data', 'chatshop'); ?></label>
                    </th>
                    <td>
                        <input type="number"
                            id="auto_archive_days"
                            name="chatshop_general_options[auto_archive_days]"
                            value="<?php echo esc_attr($options['auto_archive_days']); ?>"
                            min="30"
                            max="3650"
                            class="small-text" />
                        <span><?php _e('days', 'chatshop'); ?></span>
                        <p class="description">
                            <?php _e('Automatically archive old data after specified number of days to keep database clean.', 'chatshop'); ?>
                        </p>
                    </td>
                </tr>

                <!-- Debug Mode -->
                <tr>
                    <th scope="row">
                        <label for="debug_mode"><?php _e('Debug Mode', 'chatshop'); ?></label>
                    </th>
                    <td>
                        <fieldset>
                            <label for="debug_mode">
                                <input type="checkbox"
                                    id="debug_mode"
                                    name="chatshop_general_options[debug_mode]"
                                    value="1"
                                    <?php checked(1, $options['debug_mode']); ?> />
                                <?php _e('Enable debug mode', 'chatshop'); ?>
                            </label>
                            <p class="description">
                                <?php _e('Enable detailed logging for troubleshooting. Only enable when needed as it may affect performance.', 'chatshop'); ?>
                            </p>
                        </fieldset>
                    </td>
                </tr>

                <!-- Log Level -->
                <tr>
                    <th scope="row">
                        <label for="log_level"><?php _e('Log Level', 'chatshop'); ?></label>
                    </th>
                    <td>
                        <select id="log_level" name="chatshop_general_options[log_level]">
                            <option value="error" <?php selected($options['log_level'], 'error'); ?>><?php _e('Error Only', 'chatshop'); ?></option>
                            <option value="warning" <?php selected($options['log_level'], 'warning'); ?>><?php _e('Warning and Error', 'chatshop'); ?></option>
                            <option value="info" <?php selected($options['log_level'], 'info'); ?>><?php _e('Info, Warning and Error', 'chatshop'); ?></option>
                            <option value="debug" <?php selected($options['log_level'], 'debug'); ?>><?php _e('All (Debug Mode)', 'chatshop'); ?></option>
                        </select>
                        <p class="description">
                            <?php _e('Choose what level of information to log.', 'chatshop'); ?>
                        </p>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- System Information -->
        <h2><?php _e('System Information', 'chatshop'); ?></h2>
        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row"><?php _e('Plugin Version', 'chatshop'); ?></th>
                    <td><?php echo esc_html(CHATSHOP_VERSION); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('WordPress Version', 'chatshop'); ?></th>
                    <td><?php echo esc_html(get_bloginfo('version')); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('PHP Version', 'chatshop'); ?></th>
                    <td><?php echo esc_html(PHP_VERSION); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('WooCommerce', 'chatshop'); ?></th>
                    <td>
                        <?php
                        if (class_exists('WooCommerce')) {
                            echo '<span style="color: green;">✓ ' . __('Active', 'chatshop') . '</span>';
                            if (defined('WC_VERSION')) {
                                echo ' (v' . esc_html(WC_VERSION) . ')';
                            }
                        } else {
                            echo '<span style="color: red;">✗ ' . __('Not Active', 'chatshop') . '</span>';
                        }
                        ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Database Version', 'chatshop'); ?></th>
                    <td><?php echo esc_html(get_option('chatshop_db_version', 'Not installed')); ?></td>
                </tr>
            </tbody>
        </table>

        <!-- Tools Section -->
        <h2><?php _e('Tools', 'chatshop'); ?></h2>
        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row"><?php _e('Clear Cache', 'chatshop'); ?></th>
                    <td>
                        <button type="button" id="clear-cache" class="button button-secondary">
                            <?php _e('Clear All Cache', 'chatshop'); ?>
                        </button>
                        <p class="description">
                            <?php _e('Clear all cached data including transients and temporary files.', 'chatshop'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Export Settings', 'chatshop'); ?></th>
                    <td>
                        <button type="button" id="export-settings" class="button button-secondary">
                            <?php _e('Export Settings', 'chatshop'); ?>
                        </button>
                        <p class="description">
                            <?php _e('Export your ChatShop settings as a JSON file for backup or migration.', 'chatshop'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Import Settings', 'chatshop'); ?></th>
                    <td>
                        <input type="file" id="import-file" accept=".json" style="margin-bottom: 10px;">
                        <br>
                        <button type="button" id="import-settings" class="button button-secondary">
                            <?php _e('Import Settings', 'chatshop'); ?>
                        </button>
                        <p class="description">
                            <?php _e('Import ChatShop settings from a previously exported JSON file.', 'chatshop'); ?>
                        </p>
                    </td>
                </tr>
            </tbody>
        </table>

        <?php submit_button(); ?>
    </form>
</div>

<script>
    jQuery(document).ready(function($) {
        // Clear cache functionality
        $('#clear-cache').on('click', function() {
            var button = $(this);
            button.prop('disabled', true).text('<?php _e('Clearing...', 'chatshop'); ?>');

            $.post(ajaxurl, {
                action: 'chatshop_admin_action',
                chatshop_action: 'clear_cache',
                nonce: chatshop_admin.nonce
            }, function(response) {
                if (response.success) {
                    alert('<?php _e('Cache cleared successfully!', 'chatshop'); ?>');
                } else {
                    alert('<?php _e('Error clearing cache.', 'chatshop'); ?>');
                }
            }).always(function() {
                button.prop('disabled', false).text('<?php _e('Clear All Cache', 'chatshop'); ?>');
            });
        });

        // Export settings functionality
        $('#export-settings').on('click', function() {
            var button = $(this);
            button.prop('disabled', true).text('<?php _e('Exporting...', 'chatshop'); ?>');

            $.post(ajaxurl, {
                action: 'chatshop_admin_action',
                chatshop_action: 'export_settings',
                nonce: chatshop_admin.nonce
            }, function(response) {
                if (response.success) {
                    // Create download link
                    var dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(response.data, null, 2));
                    var downloadAnchorNode = document.createElement('a');
                    downloadAnchorNode.setAttribute("href", dataStr);
                    downloadAnchorNode.setAttribute("download", "chatshop-settings-" + new Date().toISOString().slice(0, 10) + ".json");
                    document.body.appendChild(downloadAnchorNode);
                    downloadAnchorNode.click();
                    downloadAnchorNode.remove();
                } else {
                    alert('<?php _e('Error exporting settings.', 'chatshop'); ?>');
                }
            }).always(function() {
                button.prop('disabled', false).text('<?php _e('Export Settings', 'chatshop'); ?>');
            });
        });

        // Import settings functionality
        $('#import-settings').on('click', function() {
            var fileInput = $('#import-file')[0];
            if (!fileInput.files.length) {
                alert('<?php _e('Please select a file to import.', 'chatshop'); ?>');
                return;
            }

            var file = fileInput.files[0];
            var reader = new FileReader();

            reader.onload = function(e) {
                try {
                    var settings = JSON.parse(e.target.result);

                    $.post(ajaxurl, {
                        action: 'chatshop_admin_action',
                        chatshop_action: 'import_settings',
                        settings: settings,
                        nonce: chatshop_admin.nonce
                    }, function(response) {
                        if (response.success) {
                            alert('<?php _e('Settings imported successfully! Please refresh the page.', 'chatshop'); ?>');
                            location.reload();
                        } else {
                            alert('<?php _e('Error importing settings.', 'chatshop'); ?>');
                        }
                    });
                } catch (error) {
                    alert('<?php _e('Invalid JSON file.', 'chatshop'); ?>');
                }
            };

            reader.readAsText(file);
        });
    });
</script>