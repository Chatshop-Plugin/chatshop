<?php

/**
 * ChatShop Public Class
 *
 * Handles all public-facing functionality of the plugin including
 * shortcodes, frontend assets, and payment processing pages.
 *
 * @package ChatShop
 * @subpackage Public
 * @since 1.0.0
 */

namespace ChatShop;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ChatShop Public Class
 *
 * @since 1.0.0
 */
class ChatShop_Public
{
    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     *
     * @since 1.0.0
     */
    private function init_hooks()
    {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('init', array($this, 'init_shortcodes'));
        add_action('init', array($this, 'handle_payment_callbacks'));
        add_action('template_redirect', array($this, 'handle_payment_pages'));
    }

    /**
     * Enqueue public styles
     *
     * @since 1.0.0
     */
    public function enqueue_styles()
    {
        wp_enqueue_style(
            'chatshop-public',
            CHATSHOP_PLUGIN_URL . 'public/css/chatshop-public.css',
            array(),
            CHATSHOP_VERSION,
            'all'
        );
    }

    /**
     * Enqueue public scripts
     *
     * @since 1.0.0
     */
    public function enqueue_scripts()
    {
        wp_enqueue_script(
            'chatshop-public',
            CHATSHOP_PLUGIN_URL . 'public/js/chatshop-public.js',
            array('jquery'),
            CHATSHOP_VERSION,
            true
        );

        // Localize script for AJAX
        wp_localize_script('chatshop-public', 'chatshopPublic', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('chatshop_public_nonce'),
            'strings' => array(
                'processing' => __('Processing...', 'chatshop'),
                'error' => __('An error occurred. Please try again.', 'chatshop'),
                'success' => __('Success!', 'chatshop')
            )
        ));
    }

    /**
     * Initialize shortcodes
     *
     * @since 1.0.0
     */
    public function init_shortcodes()
    {
        add_shortcode('chatshop_payment_link', array($this, 'payment_link_shortcode'));
        add_shortcode('chatshop_whatsapp_button', array($this, 'whatsapp_button_shortcode'));
        add_shortcode('chatshop_contact_form', array($this, 'contact_form_shortcode'));
    }

    /**
     * Payment link shortcode
     *
     * @since 1.0.0
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function payment_link_shortcode($atts)
    {
        $atts = shortcode_atts(array(
            'amount' => '1000',
            'currency' => 'NGN',
            'description' => __('Payment', 'chatshop'),
            'button_text' => __('Pay Now', 'chatshop'),
            'gateway' => 'paystack'
        ), $atts, 'chatshop_payment_link');

        // Sanitize attributes
        $amount = absint($atts['amount']);
        $currency = sanitize_text_field($atts['currency']);
        $description = sanitize_text_field($atts['description']);
        $button_text = sanitize_text_field($atts['button_text']);
        $gateway = sanitize_text_field($atts['gateway']);

        if ($amount <= 0) {
            return '<p class="chatshop-error">' . __('Invalid payment amount.', 'chatshop') . '</p>';
        }

        // Generate payment link
        $payment_manager = chatshop_get_component('payment_manager');
        if (!$payment_manager) {
            return '<p class="chatshop-error">' . __('Payment system not available.', 'chatshop') . '</p>';
        }

        $link_id = wp_generate_password(12, false);
        $payment_url = add_query_arg(array(
            'chatshop_payment' => $link_id,
            'amount' => $amount,
            'currency' => $currency,
            'description' => urlencode($description),
            'gateway' => $gateway
        ), home_url('/'));

        ob_start();
?>
        <div class="chatshop-payment-link">
            <a href="<?php echo esc_url($payment_url); ?>" class="chatshop-payment-button" data-amount="<?php echo esc_attr($amount); ?>">
                <?php echo esc_html($button_text); ?>
            </a>
            <div class="chatshop-payment-details">
                <span class="amount"><?php echo chatshop_format_currency($amount, $currency); ?></span>
                <span class="description"><?php echo esc_html($description); ?></span>
            </div>
        </div>
    <?php
        return ob_get_clean();
    }

    /**
     * WhatsApp button shortcode
     *
     * @since 1.0.0
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function whatsapp_button_shortcode($atts)
    {
        $atts = shortcode_atts(array(
            'phone' => '',
            'message' => __('Hello, I\'m interested in your product.', 'chatshop'),
            'button_text' => __('Chat on WhatsApp', 'chatshop'),
            'style' => 'button'
        ), $atts, 'chatshop_whatsapp_button');

        $phone = sanitize_text_field($atts['phone']);
        $message = sanitize_text_field($atts['message']);
        $button_text = sanitize_text_field($atts['button_text']);
        $style = sanitize_text_field($atts['style']);

        if (empty($phone)) {
            return '<p class="chatshop-error">' . __('Phone number is required.', 'chatshop') . '</p>';
        }

        $whatsapp_url = 'https://api.whatsapp.com/send?' . http_build_query(array(
            'phone' => $phone,
            'text' => $message
        ));

        ob_start();
    ?>
        <div class="chatshop-whatsapp-button chatshop-style-<?php echo esc_attr($style); ?>">
            <a href="<?php echo esc_url($whatsapp_url); ?>" target="_blank" rel="noopener noreferrer" class="chatshop-whatsapp-link">
                <span class="chatshop-whatsapp-icon">📱</span>
                <?php echo esc_html($button_text); ?>
            </a>
        </div>
    <?php
        return ob_get_clean();
    }

    /**
     * Contact form shortcode
     *
     * @since 1.0.0
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function contact_form_shortcode($atts)
    {
        $atts = shortcode_atts(array(
            'title' => __('Contact Us', 'chatshop'),
            'submit_text' => __('Submit', 'chatshop'),
            'redirect_url' => ''
        ), $atts, 'chatshop_contact_form');

        $title = sanitize_text_field($atts['title']);
        $submit_text = sanitize_text_field($atts['submit_text']);
        $redirect_url = esc_url($atts['redirect_url']);

        ob_start();
    ?>
        <div class="chatshop-contact-form">
            <h3><?php echo esc_html($title); ?></h3>
            <form method="post" action="" class="chatshop-form">
                <?php wp_nonce_field('chatshop_contact_form', 'chatshop_nonce'); ?>
                <input type="hidden" name="action" value="chatshop_submit_contact">
                <?php if ($redirect_url): ?>
                    <input type="hidden" name="redirect_url" value="<?php echo esc_attr($redirect_url); ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label for="chatshop_name"><?php _e('Name', 'chatshop'); ?> *</label>
                    <input type="text" id="chatshop_name" name="name" required>
                </div>

                <div class="form-group">
                    <label for="chatshop_phone"><?php _e('Phone Number', 'chatshop'); ?> *</label>
                    <input type="tel" id="chatshop_phone" name="phone" required>
                </div>

                <div class="form-group">
                    <label for="chatshop_email"><?php _e('Email', 'chatshop'); ?></label>
                    <input type="email" id="chatshop_email" name="email">
                </div>

                <div class="form-group">
                    <label for="chatshop_message"><?php _e('Message', 'chatshop'); ?></label>
                    <textarea id="chatshop_message" name="message" rows="4"></textarea>
                </div>

                <div class="form-group">
                    <button type="submit" class="chatshop-submit-button">
                        <?php echo esc_html($submit_text); ?>
                    </button>
                </div>
            </form>
        </div>
    <?php
        return ob_get_clean();
    }

    /**
     * Handle payment callbacks
     *
     * @since 1.0.0
     */
    public function handle_payment_callbacks()
    {
        if (isset($_GET['chatshop_callback']) && isset($_GET['reference'])) {
            $this->process_payment_callback();
        }
    }

    /**
     * Handle payment pages
     *
     * @since 1.0.0
     */
    public function handle_payment_pages()
    {
        if (isset($_GET['chatshop_payment'])) {
            $this->display_payment_page();
        }
    }

    /**
     * Process payment callback
     *
     * @since 1.0.0
     */
    private function process_payment_callback()
    {
        $reference = sanitize_text_field($_GET['reference']);
        $gateway = isset($_GET['gateway']) ? sanitize_text_field($_GET['gateway']) : 'paystack';

        if (empty($reference)) {
            wp_die(__('Invalid payment reference.', 'chatshop'));
        }

        // Get payment manager
        $payment_manager = chatshop_get_component('payment_manager');
        if (!$payment_manager) {
            wp_die(__('Payment system not available.', 'chatshop'));
        }

        // Verify the payment
        $gateway_instance = $payment_manager->get_gateway($gateway);
        if (!$gateway_instance) {
            wp_die(__('Payment gateway not available.', 'chatshop'));
        }

        $verification_result = $gateway_instance->verify_transaction($reference);

        if (is_wp_error($verification_result)) {
            wp_die($verification_result->get_error_message());
        }

        // Display payment result
        $this->display_payment_result($verification_result);
    }

    /**
     * Display payment page
     *
     * @since 1.0.0
     */
    private function display_payment_page()
    {
        $payment_id = sanitize_text_field($_GET['chatshop_payment']);
        $amount = isset($_GET['amount']) ? absint($_GET['amount']) : 0;
        $currency = isset($_GET['currency']) ? sanitize_text_field($_GET['currency']) : 'NGN';
        $description = isset($_GET['description']) ? sanitize_text_field($_GET['description']) : '';
        $gateway = isset($_GET['gateway']) ? sanitize_text_field($_GET['gateway']) : 'paystack';

        if ($amount <= 0) {
            wp_die(__('Invalid payment amount.', 'chatshop'));
        }

        // Load payment page template
        $this->load_payment_template($payment_id, $amount, $currency, $description, $gateway);
    }

    /**
     * Load payment template
     *
     * @since 1.0.0
     * @param string $payment_id Payment ID
     * @param int    $amount Amount
     * @param string $currency Currency
     * @param string $description Description
     * @param string $gateway Gateway
     */
    private function load_payment_template($payment_id, $amount, $currency, $description, $gateway)
    {
        // Set up template variables
        $template_vars = array(
            'payment_id' => $payment_id,
            'amount' => $amount,
            'currency' => $currency,
            'description' => $description,
            'gateway' => $gateway,
            'formatted_amount' => chatshop_format_currency($amount, $currency)
        );

        // Load template
        $template_path = CHATSHOP_PLUGIN_DIR . 'public/partials/payment-page.php';

        if (file_exists($template_path)) {
            extract($template_vars);
            include $template_path;
        } else {
            $this->display_default_payment_page($template_vars);
        }

        exit;
    }

    /**
     * Display default payment page
     *
     * @since 1.0.0
     * @param array $vars Template variables
     */
    private function display_default_payment_page($vars)
    {
    ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>

        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php _e('Payment', 'chatshop'); ?> - <?php bloginfo('name'); ?></title>
            <?php wp_head(); ?>
        </head>

        <body class="chatshop-payment-page">
            <div class="chatshop-payment-container">
                <h1><?php _e('Complete Your Payment', 'chatshop'); ?></h1>
                <div class="payment-details">
                    <p><strong><?php _e('Amount:', 'chatshop'); ?></strong> <?php echo esc_html($vars['formatted_amount']); ?></p>
                    <?php if ($vars['description']): ?>
                        <p><strong><?php _e('Description:', 'chatshop'); ?></strong> <?php echo esc_html($vars['description']); ?></p>
                    <?php endif; ?>
                </div>
                <div class="payment-form">
                    <button id="chatshop-pay-button" class="chatshop-pay-button">
                        <?php _e('Pay Now', 'chatshop'); ?>
                    </button>
                </div>
            </div>
            <?php wp_footer(); ?>
        </body>

        </html>
    <?php
    }

    /**
     * Display payment result
     *
     * @since 1.0.0
     * @param array $result Payment result
     */
    private function display_payment_result($result)
    {
        $success = isset($result['success']) ? $result['success'] : false;
        $message = isset($result['message']) ? $result['message'] : '';

    ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>

        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php _e('Payment Result', 'chatshop'); ?> - <?php bloginfo('name'); ?></title>
            <?php wp_head(); ?>
        </head>

        <body class="chatshop-payment-result">
            <div class="chatshop-result-container">
                <div class="result-icon <?php echo $success ? 'success' : 'error'; ?>">
                    <?php echo $success ? '✓' : '✗'; ?>
                </div>
                <h1><?php echo $success ? __('Payment Successful!', 'chatshop') : __('Payment Failed', 'chatshop'); ?></h1>
                <?php if ($message): ?>
                    <p class="result-message"><?php echo esc_html($message); ?></p>
                <?php endif; ?>
                <a href="<?php echo esc_url(home_url('/')); ?>" class="return-home">
                    <?php _e('Return to Home', 'chatshop'); ?>
                </a>
            </div>
            <?php wp_footer(); ?>
        </body>

        </html>
<?php
        exit;
    }
}
