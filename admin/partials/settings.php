<?php

/**
 * Provide a admin area view for the plugin settings
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

// Get active tab
$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <nav class="nav-tab-wrapper chatshop-settings-tabs">
        <a href="?page=chatshop-settings&tab=general" class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">
            <?php _e('General', 'chatshop'); ?>
        </a>
        <a href="?page=chatshop-settings&tab=whatsapp" class="nav-tab <?php echo $active_tab === 'whatsapp' ? 'nav-tab-active' : ''; ?>">
            <?php _e('WhatsApp', 'chatshop'); ?>
        </a>
        <a href="?page=chatshop-settings&tab=payments" class="nav-tab <?php echo $active_tab === 'payments' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Payments', 'chatshop'); ?>
        </a>
    </nav>

    <form method="post" action="options.php">
        <?php
        switch ($active_tab) {
            case 'general':
                settings_fields('chatshop_general');
                do_settings_sections('chatshop_general');
                break;

            case 'whatsapp':
                settings_fields('chatshop_whatsapp');
                do_settings_sections('chatshop_whatsapp');
        ?>
                <p>
                    <button type="button" class="button" id="chatshop-test-whatsapp">
                        <?php _e('Test WhatsApp Connection', 'chatshop'); ?>
                    </button>
                </p>
            <?php
                break;

            case 'payments':
                settings_fields('chatshop_payments');
                do_settings_sections('chatshop_payments');
            ?>
                <p>
                    <button type="button" class="button" id="chatshop-test-paystack">
                        <?php _e('Test Paystack Connection', 'chatshop'); ?>
                    </button>
                </p>
        <?php
                break;
        }

        submit_button();
        ?>
    </form>
</div>