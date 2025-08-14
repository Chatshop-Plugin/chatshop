<?php

/**
 * ChatShop WhatsApp Webhook Handler
 *
 * Handles incoming WhatsApp webhook events
 *
 * @package ChatShop
 * @subpackage WhatsApp
 * @since 1.0.0
 */

namespace ChatShop\WhatsApp;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WhatsApp Webhook Handler class
 *
 * Processes incoming webhook events from WhatsApp Business API
 */
class ChatShop_WhatsApp_Webhook
{

    /**
     * Webhook verify token
     *
     * @var string
     */
    private $verify_token;

    /**
     * App secret for signature verification
     *
     * @var string
     */
    private $app_secret;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->verify_token = get_option('chatshop_whatsapp_verify_token', '');
        $this->app_secret = get_option('chatshop_whatsapp_app_secret', '');

        add_action('init', [$this, 'init_webhook_endpoints']);
    }

    /**
     * Initialize webhook endpoints
     */
    public function init_webhook_endpoints()
    {
        add_action('wp_ajax_nopriv_chatshop_whatsapp_webhook', [$this, 'handle_webhook']);
        add_action('wp_ajax_chatshop_whatsapp_webhook', [$this, 'handle_webhook']);

        // Add rewrite rule for cleaner webhook URL
        add_rewrite_rule(
            '^chatshop/webhook/whatsapp/?$',
            'index.php?chatshop_webhook=whatsapp',
            'top'
        );

        add_action('template_redirect', [$this, 'handle_webhook_route']);
    }

    /**
     * Handle webhook route
     */
    public function handle_webhook_route()
    {
        if (get_query_var('chatshop_webhook') === 'whatsapp') {
            $this->handle_webhook();
            exit;
        }
    }

    /**
     * Handle incoming webhook
     */
    public function handle_webhook()
    {
        // Verify request method
        $method = $_SERVER['REQUEST_METHOD'];

        if ($method === 'GET') {
            $this->handle_verification();
        } elseif ($method === 'POST') {
            $this->handle_webhook_event();
        } else {
            http_response_code(405);
            echo 'Method not allowed';
        }

        exit;
    }

    /**
     * Handle webhook verification (GET request)
     */
    private function handle_verification()
    {
        $mode = sanitize_text_field($_GET['hub_mode'] ?? '');
        $token = sanitize_text_field($_GET['hub_verify_token'] ?? '');
        $challenge = sanitize_text_field($_GET['hub_challenge'] ?? '');

        if ($mode === 'subscribe' && $token === $this->verify_token) {
            http_response_code(200);
            echo $challenge;
        } else {
            http_response_code(403);
            echo 'Forbidden';
        }
    }

    /**
     * Handle webhook event (POST request)
     */
    private function handle_webhook_event()
    {
        // Get raw input
        $input = file_get_contents('php://input');

        if (empty($input)) {
            http_response_code(400);
            echo 'Bad Request';
            return;
        }

        // Verify signature if app secret is configured
        if (!empty($this->app_secret) && !$this->verify_signature($input)) {
            http_response_code(403);
            echo 'Invalid signature';
            return;
        }

        // Parse webhook data
        $webhook_data = json_decode($input, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo 'Invalid JSON';
            return;
        }

        // Process webhook data
        $this->process_webhook_data($webhook_data);

        // Respond with 200 OK
        http_response_code(200);
        echo 'OK';
    }

    /**
     * Verify webhook signature
     *
     * @param string $payload Raw payload
     * @return bool True if signature is valid
     */
    private function verify_signature($payload)
    {
        $signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';

        if (empty($signature)) {
            return false;
        }

        $expected_signature = 'sha256=' . hash_hmac('sha256', $payload, $this->app_secret);

        return hash_equals($expected_signature, $signature);
    }

    /**
     * Process webhook data
     *
     * @param array $webhook_data Webhook data
     */
    private function process_webhook_data($webhook_data)
    {
        if (!isset($webhook_data['entry']) || !is_array($webhook_data['entry'])) {
            return;
        }

        foreach ($webhook_data['entry'] as $entry) {
            if (!isset($entry['changes']) || !is_array($entry['changes'])) {
                continue;
            }

            foreach ($entry['changes'] as $change) {
                $this->process_change($change);
            }
        }
    }

    /**
     * Process individual change
     *
     * @param array $change Change data
     */
    private function process_change($change)
    {
        $field = $change['field'] ?? '';
        $value = $change['value'] ?? [];

        switch ($field) {
            case 'messages':
                $this->process_messages($value);
                break;
            case 'message_template_status_update':
                $this->process_template_status_update($value);
                break;
            default:
                $this->log_webhook_event('unknown_field', $field, $value);
        }
    }

    /**
     * Process incoming messages
     *
     * @param array $value Message data
     */
    private function process_messages($value)
    {
        // Process message statuses
        if (isset($value['statuses']) && is_array($value['statuses'])) {
            foreach ($value['statuses'] as $status) {
                $this->process_message_status($status);
            }
        }

        // Process incoming messages
        if (isset($value['messages']) && is_array($value['messages'])) {
            foreach ($value['messages'] as $message) {
                $this->process_incoming_message($message);
            }
        }
    }

    /**
     * Process message status update
     *
     * @param array $status Status data
     */
    private function process_message_status($status)
    {
        $message_id = sanitize_text_field($status['id'] ?? '');
        $status_type = sanitize_text_field($status['status'] ?? '');
        $recipient_id = sanitize_text_field($status['recipient_id'] ?? '');
        $timestamp = intval($status['timestamp'] ?? 0);

        if (empty($message_id) || empty($status_type)) {
            return;
        }

        // Update message status in database
        global $wpdb;

        $table_name = $wpdb->prefix . 'chatshop_messages';

        $wpdb->update(
            $table_name,
            [
                'status' => $status_type,
                'status_updated_at' => date('Y-m-d H:i:s', $timestamp)
            ],
            ['whatsapp_message_id' => $message_id],
            ['%s', '%s'],
            ['%s']
        );

        // Handle specific status types
        switch ($status_type) {
            case 'delivered':
                $this->handle_message_delivered($message_id, $recipient_id);
                break;
            case 'read':
                $this->handle_message_read($message_id, $recipient_id);
                break;
            case 'failed':
                $error = $status['errors'][0] ?? [];
                $this->handle_message_failed($message_id, $recipient_id, $error);
                break;
        }

        do_action('chatshop_message_status_updated', $message_id, $status_type, $recipient_id, $status);
    }

    /**
     * Process incoming message
     *
     * @param array $message Message data
     */
    private function process_incoming_message($message)
    {
        $message_id = sanitize_text_field($message['id'] ?? '');
        $from = sanitize_text_field($message['from'] ?? '');
        $timestamp = intval($message['timestamp'] ?? 0);
        $type = sanitize_text_field($message['type'] ?? '');

        if (empty($message_id) || empty($from)) {
            return;
        }

        // Check if message already processed
        if ($this->is_message_processed($message_id)) {
            return;
        }

        // Extract message content based on type
        $content = $this->extract_message_content($message, $type);

        // Store incoming message
        $this->store_incoming_message($message_id, $from, $type, $content, $timestamp);

        // Process message based on content
        $this->handle_incoming_message_content($from, $type, $content, $message);

        do_action('chatshop_incoming_message', $message_id, $from, $type, $content, $message);
    }

    /**
     * Extract message content based on type
     *
     * @param array  $message Message data
     * @param string $type Message type
     * @return array Extracted content
     */
    private function extract_message_content($message, $type)
    {
        $content = ['type' => $type];

        switch ($type) {
            case 'text':
                $content['text'] = sanitize_textarea_field($message['text']['body'] ?? '');
                break;
            case 'image':
                $content['media_id'] = sanitize_text_field($message['image']['id'] ?? '');
                $content['caption'] = sanitize_textarea_field($message['image']['caption'] ?? '');
                break;
            case 'document':
                $content['media_id'] = sanitize_text_field($message['document']['id'] ?? '');
                $content['filename'] = sanitize_text_field($message['document']['filename'] ?? '');
                $content['caption'] = sanitize_textarea_field($message['document']['caption'] ?? '');
                break;
            case 'button':
                $content['button_id'] = sanitize_text_field($message['button']['payload'] ?? '');
                $content['button_text'] = sanitize_text_field($message['button']['text'] ?? '');
                break;
            case 'interactive':
                $interactive = $message['interactive'] ?? [];
                $content['interactive_type'] = sanitize_text_field($interactive['type'] ?? '');

                if ($interactive['type'] === 'button_reply') {
                    $content['button_id'] = sanitize_text_field($interactive['button_reply']['id'] ?? '');
                    $content['button_title'] = sanitize_text_field($interactive['button_reply']['title'] ?? '');
                } elseif ($interactive['type'] === 'list_reply') {
                    $content['list_id'] = sanitize_text_field($interactive['list_reply']['id'] ?? '');
                    $content['list_title'] = sanitize_text_field($interactive['list_reply']['title'] ?? '');
                }
                break;
        }

        return $content;
    }

    /**
     * Handle incoming message content
     *
     * @param string $from Sender phone number
     * @param string $type Message type
     * @param array  $content Message content
     * @param array  $message Full message data
     */
    private function handle_incoming_message_content($from, $type, $content, $message)
    {
        // Handle text messages for potential commands or orders
        if ($type === 'text' && !empty($content['text'])) {
            $this->process_text_message($from, $content['text']);
        }

        // Handle button interactions
        if (in_array($type, ['button', 'interactive'])) {
            $this->process_interactive_message($from, $content);
        }

        // Update contact last interaction
        $this->update_contact_last_interaction($from);
    }

    /**
     * Process text message for commands or orders
     *
     * @param string $from Sender phone number
     * @param string $text Message text
     */
    private function process_text_message($from, $text)
    {
        $text_lower = strtolower(trim($text));

        // Check for common commands
        if (in_array($text_lower, ['hi', 'hello', 'start', 'help'])) {
            $this->send_welcome_message($from);
        } elseif (strpos($text_lower, 'order') !== false) {
            $this->handle_order_inquiry($from, $text);
        } elseif (strpos($text_lower, 'catalog') !== false || strpos($text_lower, 'products') !== false) {
            $this->send_product_catalog($from);
        }

        do_action('chatshop_process_text_message', $from, $text);
    }

    /**
     * Process interactive message (buttons, lists)
     *
     * @param string $from Sender phone number
     * @param array  $content Interaction content
     */
    private function process_interactive_message($from, $content)
    {
        $button_id = $content['button_id'] ?? $content['list_id'] ?? '';

        if (empty($button_id)) {
            return;
        }

        // Handle specific button actions
        if (strpos($button_id, 'product_') === 0) {
            $product_id = str_replace('product_', '', $button_id);
            $this->handle_product_inquiry($from, intval($product_id));
        } elseif (strpos($button_id, 'order_') === 0) {
            $order_id = str_replace('order_', '', $button_id);
            $this->handle_order_status_inquiry($from, intval($order_id));
        }

        do_action('chatshop_process_interactive_message', $from, $button_id, $content);
    }

    /**
     * Handle message delivered status
     *
     * @param string $message_id Message ID
     * @param string $recipient_id Recipient ID
     */
    private function handle_message_delivered($message_id, $recipient_id)
    {
        do_action('chatshop_message_delivered', $message_id, $recipient_id);
    }

    /**
     * Handle message read status
     *
     * @param string $message_id Message ID
     * @param string $recipient_id Recipient ID
     */
    private function handle_message_read($message_id, $recipient_id)
    {
        do_action('chatshop_message_read', $message_id, $recipient_id);
    }

    /**
     * Handle message failed status
     *
     * @param string $message_id Message ID
     * @param string $recipient_id Recipient ID
     * @param array  $error Error details
     */
    private function handle_message_failed($message_id, $recipient_id, $error)
    {
        $error_code = $error['code'] ?? '';
        $error_title = $error['title'] ?? '';

        $this->log_webhook_event('message_failed', $message_id, [
            'recipient' => $recipient_id,
            'error_code' => $error_code,
            'error_title' => $error_title
        ]);

        do_action('chatshop_message_failed', $message_id, $recipient_id, $error);
    }

    /**
     * Process template status update
     *
     * @param array $value Template status data
     */
    private function process_template_status_update($value)
    {
        $template_name = sanitize_text_field($value['name'] ?? '');
        $status = sanitize_text_field($value['status'] ?? '');
        $event = sanitize_text_field($value['event'] ?? '');

        if (empty($template_name)) {
            return;
        }

        $this->log_webhook_event('template_status_update', $template_name, [
            'status' => $status,
            'event' => $event
        ]);

        do_action('chatshop_template_status_updated', $template_name, $status, $event, $value);
    }

    /**
     * Check if message is already processed
     *
     * @param string $message_id Message ID
     * @return bool True if already processed
     */
    private function is_message_processed($message_id)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'chatshop_messages';

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE whatsapp_message_id = %s",
            $message_id
        ));

        return $count > 0;
    }

    /**
     * Store incoming message
     *
     * @param string $message_id Message ID
     * @param string $from Sender phone number
     * @param string $type Message type
     * @param array  $content Message content
     * @param int    $timestamp Message timestamp
     */
    private function store_incoming_message($message_id, $from, $type, $content, $timestamp)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'chatshop_messages';

        $wpdb->insert(
            $table_name,
            [
                'whatsapp_message_id' => $message_id,
                'phone_number' => $from,
                'direction' => 'incoming',
                'message_type' => $type,
                'content' => json_encode($content),
                'status' => 'received',
                'created_at' => date('Y-m-d H:i:s', $timestamp)
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );
    }

    /**
     * Update contact last interaction
     *
     * @param string $phone_number Phone number
     */
    private function update_contact_last_interaction($phone_number)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'chatshop_contacts';

        $wpdb->update(
            $table_name,
            ['last_interaction' => current_time('mysql')],
            ['phone_number' => $phone_number],
            ['%s'],
            ['%s']
        );
    }

    /**
     * Send welcome message
     *
     * @param string $phone_number Phone number
     */
    private function send_welcome_message($phone_number)
    {
        $message_sender = new ChatShop_Message_Sender();
        $welcome_message = get_option(
            'chatshop_welcome_message',
            __('Hello! Welcome to our store. How can we help you today?', 'chatshop')
        );

        $message_sender->send_text_message($phone_number, $welcome_message);
    }

    /**
     * Handle order inquiry
     *
     * @param string $phone_number Phone number
     * @param string $text Original message text
     */
    private function handle_order_inquiry($phone_number, $text)
    {
        // Implementation would depend on order tracking system
        do_action('chatshop_handle_order_inquiry', $phone_number, $text);
    }

    /**
     * Send product catalog
     *
     * @param string $phone_number Phone number
     */
    private function send_product_catalog($phone_number)
    {
        // Implementation would depend on product catalog system
        do_action('chatshop_send_product_catalog', $phone_number);
    }

    /**
     * Handle product inquiry
     *
     * @param string $phone_number Phone number
     * @param int    $product_id Product ID
     */
    private function handle_product_inquiry($phone_number, $product_id)
    {
        do_action('chatshop_handle_product_inquiry', $phone_number, $product_id);
    }

    /**
     * Handle order status inquiry
     *
     * @param string $phone_number Phone number
     * @param int    $order_id Order ID
     */
    private function handle_order_status_inquiry($phone_number, $order_id)
    {
        do_action('chatshop_handle_order_status_inquiry', $phone_number, $order_id);
    }

    /**
     * Log webhook event
     *
     * @param string $event_type Event type
     * @param string $identifier Event identifier
     * @param array  $data Additional data
     */
    private function log_webhook_event($event_type, $identifier, $data = [])
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'ChatShop Webhook [%s]: %s - %s',
                $event_type,
                $identifier,
                json_encode($data)
            ));
        }

        do_action('chatshop_webhook_event', $event_type, $identifier, $data);
    }
}
