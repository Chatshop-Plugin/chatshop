<?php

/**
 * The public-facing functionality of the plugin
 *
 * @package ChatShop
 * @subpackage ChatShop/public
 * @since 1.0.0
 */

namespace ChatShop;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * The public-facing functionality of the plugin
 *
 * Defines the plugin name, version, and hooks for the public-facing side of the site.
 *
 * @since 1.0.0
 */
class ChatShop_Public
{
    /**
     * The version of this plugin
     *
     * @since 1.0.0
     * @var string
     */
    private $version;

    /**
     * Initialize the class and set its properties
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        $this->version = defined('CHATSHOP_VERSION') ? CHATSHOP_VERSION : '1.0.0';
    }

    /**
     * Register the stylesheets for the public-facing side of the site
     *
     * @since 1.0.0
     */
    public function enqueue_styles()
    {
        wp_enqueue_style(
            'chatshop-public',
            CHATSHOP_PLUGIN_URL . 'public/css/chatshop-public.css',
            array(),
            $this->version,
            'all'
        );
    }

    /**
     * Register the JavaScript for the public-facing side of the site
     *
     * @since 1.0.0
     */
    public function enqueue_scripts()
    {
        wp_enqueue_script(
            'chatshop-public',
            CHATSHOP_PLUGIN_URL . 'public/js/chatshop-public.js',
            array('jquery'),
            $this->version,
            true
        );

        // Localize script with public data
        wp_localize_script('chatshop-public', 'chatshop_public', array(
            'ajax_url'   => admin_url('admin-ajax.php'),
            'nonce'      => wp_create_nonce('chatshop_public_nonce'),
            'plugin_url' => CHATSHOP_PLUGIN_URL,
            'strings'    => array(
                'loading'        => __('Loading...', 'chatshop'),
                'error'          => __('An error occurred. Please try again.', 'chatshop'),
                'success'        => __('Success!', 'chatshop'),
                'whatsapp_text'  => __('Chat with us on WhatsApp', 'chatshop')
            )
        ));
    }

    /**
     * Register shortcodes
     *
     * @since 1.0.0
     */
    public function register_shortcodes()
    {
        add_shortcode('chatshop_whatsapp_button', array($this, 'whatsapp_button_shortcode'));
        add_shortcode('chatshop_payment_link', array($this, 'payment_link_shortcode'));
        add_shortcode('chatshop_contact_form', array($this, 'contact_form_shortcode'));
    }

    /**
     * WhatsApp button shortcode
     *
     * @since 1.0.0
     * @param array $atts Shortcode attributes
     * @return string
     */
    public function whatsapp_button_shortcode($atts)
    {
        $atts = shortcode_atts(array(
            'phone'   => '',
            'message' => '',
            'text'    => __('Chat on WhatsApp', 'chatshop'),
            'style'   => 'button'
        ), $atts, 'chatshop_whatsapp_button');

        // Get phone number from settings if not provided
        if (empty($atts['phone'])) {
            $whatsapp_options = get_option('chatshop_whatsapp_options', array());
            $atts['phone'] = isset($whatsapp_options['phone_number']) ? $whatsapp_options['phone_number'] : '';
        }

        if (empty($atts['phone'])) {
            return '<p class="chatshop-error">' . __('WhatsApp phone number not configured.', 'chatshop') . '</p>';
        }

        // Clean phone number
        $phone = preg_replace('/[^0-9]/', '', $atts['phone']);

        // Build WhatsApp URL
        $whatsapp_url = 'https://wa.me/' . $phone;
        if (!empty($atts['message'])) {
            $whatsapp_url .= '?text=' . urlencode($atts['message']);
        }

        // Generate button HTML
        $classes = array('chatshop-whatsapp-button');
        if ($atts['style'] === 'floating') {
            $classes[] = 'chatshop-floating';
        }

        return sprintf(
            '<a href="%s" target="_blank" rel="noopener" class="%s">%s</a>',
            esc_url($whatsapp_url),
            esc_attr(implode(' ', $classes)),
            esc_html($atts['text'])
        );
    }

    /**
     * Payment link shortcode
     *
     * @since 1.0.0
     * @param array $atts Shortcode attributes
     * @return string
     */
    public function payment_link_shortcode($atts)
    {
        $atts = shortcode_atts(array(
            'amount'      => '',
            'description' => '',
            'button_text' => __('Pay Now', 'chatshop'),
            'currency'    => 'NGN'
        ), $atts, 'chatshop_payment_link');

        if (empty($atts['amount'])) {
            return '<p class="chatshop-error">' . __('Payment amount is required.', 'chatshop') . '</p>';
        }

        // Generate unique payment link ID
        $link_id = 'pl_' . wp_generate_uuid4();

        // Create payment link (this will be handled by payment component when available)
        $payment_url = add_query_arg(array(
            'chatshop_action' => 'payment',
            'link_id'         => $link_id,
            'amount'          => $atts['amount'],
            'currency'        => $atts['currency']
        ), home_url());

        return sprintf(
            '<a href="%s" class="chatshop-payment-button" data-amount="%s" data-currency="%s">%s</a>',
            esc_url($payment_url),
            esc_attr($atts['amount']),
            esc_attr($atts['currency']),
            esc_html($atts['button_text'])
        );
    }

    /**
     * Contact form shortcode
     *
     * @since 1.0.0
     * @param array $atts Shortcode attributes
     * @return string
     */
    public function contact_form_shortcode($atts)
    {
        $atts = shortcode_atts(array(
            'title'       => __('Contact Us', 'chatshop'),
            'redirect'    => 'whatsapp',
            'button_text' => __('Send Message', 'chatshop')
        ), $atts, 'chatshop_contact_form');

        ob_start();
?>
        <div class="chatshop-contact-form">
            <h3><?php echo esc_html($atts['title']); ?></h3>
            <form class="chatshop-form" data-redirect="<?php echo esc_attr($atts['redirect']); ?>">
                <?php wp_nonce_field('chatshop_contact_form', 'chatshop_nonce'); ?>

                <div class="chatshop-field">
                    <label for="chatshop_name"><?php _e('Name', 'chatshop'); ?> *</label>
                    <input type="text" id="chatshop_name" name="name" required>
                </div>

                <div class="chatshop-field">
                    <label for="chatshop_email"><?php _e('Email', 'chatshop'); ?></label>
                    <input type="email" id="chatshop_email" name="email">
                </div>

                <div class="chatshop-field">
                    <label for="chatshop_phone"><?php _e('Phone', 'chatshop'); ?> *</label>
                    <input type="tel" id="chatshop_phone" name="phone" required>
                </div>

                <div class="chatshop-field">
                    <label for="chatshop_message"><?php _e('Message', 'chatshop'); ?> *</label>
                    <textarea id="chatshop_message" name="message" rows="4" required></textarea>
                </div>

                <div class="chatshop-field">
                    <button type="submit" class="chatshop-submit-button">
                        <?php echo esc_html($atts['button_text']); ?>
                    </button>
                </div>
            </form>
        </div>
<?php
        return ob_get_clean();
    }

    /**
     * Handle AJAX requests
     *
     * @since 1.0.0
     */
    public function handle_ajax_request()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'chatshop_public_nonce')) {
            wp_die(__('Security check failed', 'chatshop'));
        }

        $action = sanitize_text_field($_POST['chatshop_action']);

        switch ($action) {
            case 'submit_contact_form':
                $this->handle_contact_form_submission();
                break;

            case 'generate_payment_link':
                $this->handle_payment_link_generation();
                break;

            default:
                wp_send_json_error(__('Invalid action', 'chatshop'));
        }
    }

    /**
     * Handle contact form submission
     *
     * @since 1.0.0
     */
    private function handle_contact_form_submission()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['chatshop_nonce'], 'chatshop_contact_form')) {
            wp_send_json_error(__('Security check failed', 'chatshop'));
        }

        // Sanitize input data
        $name = sanitize_text_field($_POST['name']);
        $email = sanitize_email($_POST['email']);
        $phone = sanitize_text_field($_POST['phone']);
        $message = sanitize_textarea_field($_POST['message']);
        $redirect = sanitize_text_field($_POST['redirect']);

        // Validate required fields
        if (empty($name) || empty($phone) || empty($message)) {
            wp_send_json_error(__('Please fill in all required fields.', 'chatshop'));
        }

        // Store contact information (this will be handled by WhatsApp component when available)
        $contact_data = array(
            'name'    => $name,
            'email'   => $email,
            'phone'   => $phone,
            'message' => $message,
            'source'  => 'contact_form',
            'created' => current_time('mysql')
        );

        // For now, just store as option (temporary until database component is ready)
        $contacts = get_option('chatshop_temp_contacts', array());
        $contacts[] = $contact_data;
        update_option('chatshop_temp_contacts', $contacts);

        // Generate response based on redirect type
        if ($redirect === 'whatsapp') {
            $whatsapp_options = get_option('chatshop_whatsapp_options', array());
            $phone_number = isset($whatsapp_options['phone_number']) ? $whatsapp_options['phone_number'] : '';

            if (!empty($phone_number)) {
                $whatsapp_message = sprintf(
                    __('Hi, I\'m %s. %s', 'chatshop'),
                    $name,
                    $message
                );

                $whatsapp_url = 'https://wa.me/' . preg_replace('/[^0-9]/', '', $phone_number) .
                    '?text=' . urlencode($whatsapp_message);

                wp_send_json_success(array(
                    'redirect_url' => $whatsapp_url,
                    'message'      => __('Redirecting to WhatsApp...', 'chatshop')
                ));
            }
        }

        wp_send_json_success(array(
            'message' => __('Thank you for your message. We will get back to you soon!', 'chatshop')
        ));
    }

    /**
     * Handle payment link generation
     *
     * @since 1.0.0
     */
    private function handle_payment_link_generation()
    {
        // This will be implemented when payment component is available
        wp_send_json_success(array(
            'payment_url' => '#',
            'message'     => __('Payment link generated successfully', 'chatshop')
        ));
    }

    /**
     * Add floating WhatsApp button
     *
     * @since 1.0.0
     */
    public function add_floating_whatsapp_button()
    {
        $general_options = get_option('chatshop_general_options', array());

        if (!isset($general_options['show_floating_button']) || !$general_options['show_floating_button']) {
            return;
        }

        $whatsapp_options = get_option('chatshop_whatsapp_options', array());
        $phone_number = isset($whatsapp_options['phone_number']) ? $whatsapp_options['phone_number'] : '';

        if (empty($phone_number)) {
            return;
        }

        echo do_shortcode('[chatshop_whatsapp_button phone="' . esc_attr($phone_number) . '" style="floating"]');
    }
}
