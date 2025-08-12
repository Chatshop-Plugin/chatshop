<?php

/**
 * ChatShop WhatsApp Manager Component
 *
 * File: components/whatsapp/class-chatshop-whatsapp-manager.php
 * 
 * Manages WhatsApp Business API integration, message sending,
 * template management, and webhook handling.
 *
 * @package ChatShop
 * @subpackage Components\WhatsApp
 * @since 1.0.0
 */

namespace ChatShop;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ChatShop WhatsApp Manager Class
 *
 * Handles WhatsApp Business API integration with comprehensive
 * message management, template support, and webhook processing.
 *
 * @since 1.0.0
 */
class ChatShop_WhatsApp_Manager extends ChatShop_Abstract_Component
{
    /**
     * Component identifier
     *
     * @var string
     * @since 1.0.0
     */
    protected $id = 'whatsapp';

    /**
     * Component name
     *
     * @var string
     * @since 1.0.0
     */
    protected $name = 'WhatsApp Integration';

    /**
     * Component description
     *
     * @var string
     * @since 1.0.0
     */
    protected $description = 'WhatsApp messaging and automation features';

    /**
     * WhatsApp Business API base URL
     *
     * @var string
     * @since 1.0.0
     */
    private $api_base_url = 'https://graph.facebook.com/v18.0/';

    /**
     * API credentials
     *
     * @var array
     * @since 1.0.0
     */
    private $credentials = array();

    /**
     * Database table names
     *
     * @var array
     * @since 1.0.0
     */
    private $tables = array();

    /**
     * Message templates cache
     *
     * @var array
     * @since 1.0.0
     */
    private $templates_cache = array();

    /**
     * Connection status
     *
     * @var bool
     * @since 1.0.0
     */
    private $is_connected = false;

    /**
     * Initialize component
     *
     * @since 1.0.0
     */
    protected function init()
    {
        $this->setup_database_tables();
        $this->load_credentials();
        $this->init_hooks();
        $this->verify_connection();

        chatshop_log('WhatsApp Manager component initialized', 'info');
    }

    /**
     * Setup database table names
     *
     * @since 1.0.0
     */
    private function setup_database_tables()
    {
        global $wpdb;

        $this->tables = array(
            'messages' => $wpdb->prefix . 'chatshop_whatsapp_messages',
            'templates' => $wpdb->prefix . 'chatshop_whatsapp_templates',
            'webhooks' => $wpdb->prefix . 'chatshop_whatsapp_webhooks',
            'campaigns' => $wpdb->prefix . 'chatshop_whatsapp_campaigns'
        );
    }

    /**
     * Load API credentials from settings
     *
     * @since 1.0.0
     */
    private function load_credentials()
    {
        $this->credentials = array(
            'access_token' => chatshop_get_option('whatsapp', 'access_token', ''),
            'phone_number_id' => chatshop_get_option('whatsapp', 'phone_number_id', ''),
            'webhook_verify_token' => chatshop_get_option('whatsapp', 'webhook_verify_token', ''),
            'business_account_id' => chatshop_get_option('whatsapp', 'business_account_id', '')
        );
    }

    /**
     * Initialize WordPress hooks
     *
     * @since 1.0.0
     */
    private function init_hooks()
    {
        // REST API endpoint for webhooks
        add_action('rest_api_init', array($this, 'register_webhook_endpoint'));

        // AJAX handlers
        add_action('wp_ajax_chatshop_send_whatsapp_message', array($this, 'ajax_send_message'));
        add_action('wp_ajax_chatshop_test_whatsapp_connection', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_chatshop_sync_templates', array($this, 'ajax_sync_templates'));
        add_action('wp_ajax_chatshop_get_message_history', array($this, 'ajax_get_message_history'));

        // Scheduled tasks
        add_action('chatshop_hourly_message_status_update', array($this, 'update_message_statuses'));
        add_action('chatshop_daily_whatsapp_cleanup', array($this, 'cleanup_old_messages'));

        // Schedule tasks if not already scheduled
        if (!wp_next_scheduled('chatshop_hourly_message_status_update')) {
            wp_schedule_event(time(), 'hourly', 'chatshop_hourly_message_status_update');
        }

        if (!wp_next_scheduled('chatshop_daily_whatsapp_cleanup')) {
            wp_schedule_event(time(), 'daily', 'chatshop_daily_whatsapp_cleanup');
        }

        // Payment integration hooks
        add_action('chatshop_payment_link_generated', array($this, 'send_payment_link_message'), 10, 3);
        add_action('chatshop_payment_completed', array($this, 'send_payment_confirmation'), 10, 2);
    }

    /**
     * Component activation handler
     *
     * @since 1.0.0
     * @return bool True on successful activation
     */
    protected function do_activation()
    {
        return $this->create_database_tables();
    }

    /**
     * Component deactivation handler
     *
     * @since 1.0.0
     * @return bool True on successful deactivation
     */
    protected function do_deactivation()
    {
        // Clear scheduled events
        wp_clear_scheduled_hook('chatshop_hourly_message_status_update');
        wp_clear_scheduled_hook('chatshop_daily_whatsapp_cleanup');

        return true;
    }

    /**
     * Create database tables for WhatsApp management
     *
     * @since 1.0.0
     * @return bool True if tables created successfully
     */
    private function create_database_tables()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Messages table
        $messages_table = "CREATE TABLE {$this->tables['messages']} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            message_id varchar(100),
            contact_id bigint(20),
            phone varchar(20) NOT NULL,
            message_type varchar(20) DEFAULT 'text',
            content longtext,
            template_name varchar(100),
            template_language varchar(10),
            template_params longtext,
            direction varchar(10) DEFAULT 'outbound',
            status varchar(20) DEFAULT 'pending',
            error_message text,
            campaign_id bigint(20),
            scheduled_at datetime,
            sent_at datetime,
            delivered_at datetime,
            read_at datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY message_id (message_id),
            KEY contact_id (contact_id),
            KEY phone (phone),
            KEY status (status),
            KEY direction (direction),
            KEY campaign_id (campaign_id),
            KEY created_at (created_at)
        ) $charset_collate;";

        // Templates table
        $templates_table = "CREATE TABLE {$this->tables['templates']} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            template_id varchar(100) NOT NULL,
            name varchar(100) NOT NULL,
            language varchar(10) DEFAULT 'en',
            category varchar(50),
            status varchar(20) DEFAULT 'pending',
            components longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_template (template_id, language),
            KEY name (name),
            KEY status (status),
            KEY category (category)
        ) $charset_collate;";

        // Webhooks table
        $webhooks_table = "CREATE TABLE {$this->tables['webhooks']} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            webhook_id varchar(100),
            event_type varchar(50) NOT NULL,
            payload longtext,
            processed tinyint(1) DEFAULT 0,
            processed_at datetime,
            error_message text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY webhook_id (webhook_id),
            KEY event_type (event_type),
            KEY processed (processed),
            KEY created_at (created_at)
        ) $charset_collate;";

        // Campaigns table
        $campaigns_table = "CREATE TABLE {$this->tables['campaigns']} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(200) NOT NULL,
            description text,
            type varchar(50) DEFAULT 'broadcast',
            status varchar(20) DEFAULT 'draft',
            template_id varchar(100),
            target_groups longtext,
            target_contacts longtext,
            schedule_type varchar(20) DEFAULT 'immediate',
            scheduled_at datetime,
            message_content longtext,
            stats longtext,
            created_by bigint(20),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY type (type),
            KEY created_by (created_by),
            KEY scheduled_at (scheduled_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $results = array(
            dbDelta($messages_table),
            dbDelta($templates_table),
            dbDelta($webhooks_table),
            dbDelta($campaigns_table)
        );

        chatshop_log('WhatsApp Manager database tables created', 'info', array('results' => $results));

        return true;
    }

    /**
     * Verify API connection
     *
     * @since 1.0.0
     * @return bool True if connected, false otherwise
     */
    private function verify_connection()
    {
        if (empty($this->credentials['access_token']) || empty($this->credentials['phone_number_id'])) {
            $this->is_connected = false;
            return false;
        }

        try {
            $response = $this->make_api_request('GET', $this->credentials['phone_number_id']);
            $this->is_connected = !is_wp_error($response);

            if ($this->is_connected) {
                chatshop_log('WhatsApp API connection verified', 'info');
            } else {
                chatshop_log('WhatsApp API connection failed', 'warning', array(
                    'error' => $response->get_error_message()
                ));
            }

            return $this->is_connected;
        } catch (Exception $e) {
            $this->is_connected = false;
            chatshop_log('WhatsApp API connection error: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Make API request to WhatsApp Business API
     *
     * @since 1.0.0
     * @param string $method HTTP method
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @return array|WP_Error Response data or error
     */
    private function make_api_request($method, $endpoint, $data = array())
    {
        if (empty($this->credentials['access_token'])) {
            return new \WP_Error('no_access_token', __('WhatsApp access token not configured', 'chatshop'));
        }

        $url = $this->api_base_url . $endpoint;

        $args = array(
            'method' => $method,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->credentials['access_token'],
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30
        );

        if (!empty($data) && in_array($method, array('POST', 'PUT', 'PATCH'))) {
            $args['body'] = wp_json_encode($data);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $decoded_body = json_decode($body, true);

        if ($status_code >= 400) {
            $error_message = isset($decoded_body['error']['message']) ?
                $decoded_body['error']['message'] :
                __('API request failed', 'chatshop');

            return new \WP_Error('api_error', $error_message, array(
                'status_code' => $status_code,
                'response' => $decoded_body
            ));
        }

        return $decoded_body;
    }

    /**
     * Send a WhatsApp message
     *
     * @since 1.0.0
     * @param string $phone Recipient phone number
     * @param array $message_data Message data
     * @param int $contact_id Contact ID (optional)
     * @param int $campaign_id Campaign ID (optional)
     * @return array|WP_Error Message result or error
     */
    public function send_message($phone, $message_data, $contact_id = null, $campaign_id = null)
    {
        if (!$this->is_connected) {
            return new \WP_Error('not_connected', __('WhatsApp API not connected', 'chatshop'));
        }

        // Sanitize phone number
        $phone = chatshop_sanitize_phone($phone);
        if (!chatshop_validate_phone($phone)) {
            return new \WP_Error('invalid_phone', __('Invalid phone number format', 'chatshop'));
        }

        // Prepare message data
        $api_message_data = array(
            'messaging_product' => 'whatsapp',
            'to' => $phone,
            'type' => $message_data['type'] ?? 'text'
        );

        // Handle different message types
        switch ($api_message_data['type']) {
            case 'text':
                $api_message_data['text'] = array(
                    'body' => $message_data['text'] ?? ''
                );
                break;

            case 'template':
                $api_message_data['template'] = array(
                    'name' => $message_data['template']['name'] ?? '',
                    'language' => array(
                        'code' => $message_data['template']['language'] ?? 'en'
                    )
                );

                if (!empty($message_data['template']['components'])) {
                    $api_message_data['template']['components'] = $message_data['template']['components'];
                }
                break;

            case 'image':
            case 'video':
            case 'document':
                $api_message_data[$api_message_data['type']] = array(
                    'link' => $message_data['media']['url'] ?? ''
                );

                if (!empty($message_data['media']['caption'])) {
                    $api_message_data[$api_message_data['type']]['caption'] = $message_data['media']['caption'];
                }
                break;
        }

        // Store message in database first
        $message_id = $this->store_message(array(
            'phone' => $phone,
            'contact_id' => $contact_id,
            'message_type' => $api_message_data['type'],
            'content' => wp_json_encode($message_data),
            'template_name' => $message_data['template']['name'] ?? null,
            'template_language' => $message_data['template']['language'] ?? null,
            'template_params' => isset($message_data['template']['components']) ?
                wp_json_encode($message_data['template']['components']) : null,
            'direction' => 'outbound',
            'status' => 'pending',
            'campaign_id' => $campaign_id,
            'created_at' => current_time('mysql')
        ));

        if (!$message_id) {
            return new \WP_Error('storage_failed', __('Failed to store message in database', 'chatshop'));
        }

        // Send message via API
        $response = $this->make_api_request('POST', $this->credentials['phone_number_id'] . '/messages', $api_message_data);

        if (is_wp_error($response)) {
            // Update message status to failed
            $this->update_message_status($message_id, 'failed', $response->get_error_message());
            return $response;
        }

        // Update message with API response
        $whatsapp_message_id = $response['messages'][0]['id'] ?? null;
        $this->update_message(array(
            'id' => $message_id,
            'message_id' => $whatsapp_message_id,
            'status' => 'sent',
            'sent_at' => current_time('mysql')
        ));

        // Track analytics
        do_action('chatshop_whatsapp_message_sent', $message_data, $campaign_id);

        chatshop_log("WhatsApp message sent to {$phone}", 'info', array(
            'message_id' => $whatsapp_message_id,
            'type' => $api_message_data['type']
        ));

        return array(
            'success' => true,
            'message_id' => $whatsapp_message_id,
            'local_id' => $message_id
        );
    }

    /**
     * Send text message
     *
     * @since 1.0.0
     * @param string $phone Recipient phone number
     * @param string $text Message text
     * @param int $contact_id Contact ID (optional)
     * @return array|WP_Error Message result or error
     */
    public function send_text_message($phone, $text, $contact_id = null)
    {
        return $this->send_message($phone, array(
            'type' => 'text',
            'text' => $text
        ), $contact_id);
    }

    /**
     * Send template message
     *
     * @since 1.0.0
     * @param string $phone Recipient phone number
     * @param string $template_name Template name
     * @param array $parameters Template parameters
     * @param string $language Template language
     * @param int $contact_id Contact ID (optional)
     * @return array|WP_Error Message result or error
     */
    public function send_template_message($phone, $template_name, $parameters = array(), $language = 'en', $contact_id = null)
    {
        $template_data = array(
            'name' => $template_name,
            'language' => $language
        );

        if (!empty($parameters)) {
            $template_data['components'] = array(
                array(
                    'type' => 'body',
                    'parameters' => $parameters
                )
            );
        }

        return $this->send_message($phone, array(
            'type' => 'template',
            'template' => $template_data
        ), $contact_id);
    }

    /**
     * Send payment link message
     *
     * @since 1.0.0
     * @param array $payment_data Payment data
     * @param string $phone Customer phone number
     * @param string $payment_url Payment URL
     */
    public function send_payment_link_message($payment_data, $phone, $payment_url)
    {
        $message = sprintf(
            __("Hi! Your payment link is ready.\n\nAmount: %s\nDescription: %s\n\nClick here to pay: %s", 'chatshop'),
            chatshop_format_price($payment_data['amount'], $payment_data['currency']),
            $payment_data['description'] ?? __('Payment', 'chatshop'),
            $payment_url
        );

        $this->send_text_message($phone, $message);
    }

    /**
     * Send payment confirmation message
     *
     * @since 1.0.0
     * @param array $payment_data Payment data
     * @param int $order_id Order ID
     */
    public function send_payment_confirmation($payment_data, $order_id)
    {
        if (empty($payment_data['customer_phone'])) {
            return;
        }

        $message = sprintf(
            __("Thank you! Your payment has been received.\n\nAmount: %s\nTransaction ID: %s\n\nWe appreciate your business!", 'chatshop'),
            chatshop_format_price($payment_data['amount'], $payment_data['currency']),
            $payment_data['transaction_id'] ?? ''
        );

        $this->send_text_message($payment_data['customer_phone'], $message);
    }

    /**
     * Store message in database
     *
     * @since 1.0.0
     * @param array $message_data Message data
     * @return int|false Message ID or false on failure
     */
    private function store_message($message_data)
    {
        global $wpdb;

        $result = $wpdb->insert($this->tables['messages'], $message_data);

        if ($result) {
            return $wpdb->insert_id;
        }

        chatshop_log('Failed to store WhatsApp message', 'error', array(
            'error' => $wpdb->last_error,
            'data' => $message_data
        ));

        return false;
    }

    /**
     * Update message in database
     *
     * @since 1.0.0
     * @param array $message_data Message data with ID
     * @return bool True on success, false on failure
     */
    private function update_message($message_data)
    {
        global $wpdb;

        $message_id = $message_data['id'];
        unset($message_data['id']);

        $message_data['updated_at'] = current_time('mysql');

        $result = $wpdb->update(
            $this->tables['messages'],
            $message_data,
            array('id' => $message_id),
            null,
            array('%d')
        );

        return $result !== false;
    }

    /**
     * Update message status
     *
     * @since 1.0.0
     * @param int $message_id Message ID
     * @param string $status New status
     * @param string $error_message Error message (optional)
     * @return bool True on success, false on failure
     */
    private function update_message_status($message_id, $status, $error_message = null)
    {
        $update_data = array(
            'status' => $status,
            'updated_at' => current_time('mysql')
        );

        if ($error_message) {
            $update_data['error_message'] = $error_message;
        }

        // Set timestamp based on status
        switch ($status) {
            case 'delivered':
                $update_data['delivered_at'] = current_time('mysql');
                break;
            case 'read':
                $update_data['read_at'] = current_time('mysql');
                break;
        }

        return $this->update_message(array_merge($update_data, array('id' => $message_id)));
    }

    /**
     * Register webhook endpoint
     *
     * @since 1.0.0
     */
    public function register_webhook_endpoint()
    {
        register_rest_route('chatshop/v1', '/whatsapp/webhook', array(
            'methods' => array('GET', 'POST'),
            'callback' => array($this, 'handle_webhook'),
            'permission_callback' => '__return_true',
            'args' => array()
        ));
    }

    /**
     * Handle webhook requests
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response
     */
    public function handle_webhook($request)
    {
        $method = $request->get_method();

        if ($method === 'GET') {
            return $this->verify_webhook($request);
        } elseif ($method === 'POST') {
            return $this->process_webhook($request);
        }

        return new \WP_REST_Response(array('error' => 'Method not allowed'), 405);
    }

    /**
     * Verify webhook (GET request)
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response
     */
    private function verify_webhook($request)
    {
        $mode = $request->get_param('hub_mode');
        $token = $request->get_param('hub_verify_token');
        $challenge = $request->get_param('hub_challenge');

        if ($mode === 'subscribe' && $token === $this->credentials['webhook_verify_token']) {
            chatshop_log('WhatsApp webhook verified successfully', 'info');
            return new \WP_REST_Response($challenge, 200);
        }

        chatshop_log('WhatsApp webhook verification failed', 'warning', array(
            'mode' => $mode,
            'token' => $token
        ));

        return new \WP_REST_Response('Forbidden', 403);
    }

    /**
     * Process webhook (POST request)
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response
     */
    private function process_webhook($request)
    {
        $body = $request->get_body();
        $data = json_decode($body, true);

        if (!$data || !isset($data['entry'])) {
            return new \WP_REST_Response('Bad Request', 400);
        }

        // Store webhook for processing
        $this->store_webhook($data);

        // Process webhook data
        foreach ($data['entry'] as $entry) {
            if (isset($entry['changes'])) {
                foreach ($entry['changes'] as $change) {
                    $this->process_webhook_change($change);
                }
            }
        }

        return new \WP_REST_Response('OK', 200);
    }

    /**
     * Store webhook data
     *
     * @since 1.0.0
     * @param array $webhook_data Webhook data
     * @return int|false Webhook ID or false on failure
     */
    private function store_webhook($webhook_data)
    {
        global $wpdb;

        $webhook_id = $webhook_data['entry'][0]['id'] ?? uniqid();
        $event_type = $this->determine_webhook_event_type($webhook_data);

        $result = $wpdb->insert(
            $this->tables['webhooks'],
            array(
                'webhook_id' => $webhook_id,
                'event_type' => $event_type,
                'payload' => wp_json_encode($webhook_data),
                'processed' => 0,
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%d', '%s')
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Determine webhook event type
     *
     * @since 1.0.0
     * @param array $webhook_data Webhook data
     * @return string Event type
     */
    private function determine_webhook_event_type($webhook_data)
    {
        $entry = $webhook_data['entry'][0] ?? array();
        $changes = $entry['changes'][0] ?? array();
        $value = $changes['value'] ?? array();

        if (isset($value['messages'])) {
            return 'message_received';
        } elseif (isset($value['statuses'])) {
            return 'message_status';
        } elseif (isset($value['contacts'])) {
            return 'contact_update';
        }

        return 'unknown';
    }

    /**
     * Process webhook change
     *
     * @since 1.0.0
     * @param array $change Webhook change data
     */
    private function process_webhook_change($change)
    {
        $value = $change['value'] ?? array();

        // Process incoming messages
        if (isset($value['messages'])) {
            foreach ($value['messages'] as $message) {
                $this->process_incoming_message($message, $value['contacts'][0] ?? array());
            }
        }

        // Process message status updates
        if (isset($value['statuses'])) {
            foreach ($value['statuses'] as $status) {
                $this->process_message_status_update($status);
            }
        }
    }

    /**
     * Process incoming message
     *
     * @since 1.0.0
     * @param array $message Message data
     * @param array $contact Contact data
     */
    private function process_incoming_message($message, $contact)
    {
        $phone = $contact['wa_id'] ?? '';
        $sender_name = $contact['profile']['name'] ?? $phone;

        // Store incoming message
        $message_id = $this->store_message(array(
            'message_id' => $message['id'],
            'phone' => $phone,
            'message_type' => $message['type'],
            'content' => wp_json_encode($message),
            'direction' => 'inbound',
            'status' => 'received',
            'created_at' => current_time('mysql')
        ));

        // Trigger action for contact manager and other components
        do_action('chatshop_whatsapp_message_received', array_merge($message, array(
            'sender_name' => $sender_name
        )), $phone);

        chatshop_log("Incoming WhatsApp message from {$phone}", 'info', array(
            'message_id' => $message['id'],
            'type' => $message['type']
        ));
    }

    /**
     * Process message status update
     *
     * @since 1.0.0
     * @param array $status Status data
     */
    private function process_message_status_update($status)
    {
        global $wpdb;

        $message_id = $status['id'];
        $new_status = $status['status'];
        $recipient_id = $status['recipient_id'];

        // Find local message record
        $local_message = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->tables['messages']} WHERE message_id = %s",
            $message_id
        ));

        if ($local_message) {
            $this->update_message_status($local_message->id, $new_status);

            // Trigger action for analytics
            do_action('chatshop_whatsapp_status_update', $status, $recipient_id);

            chatshop_log("Message status updated: {$message_id} -> {$new_status}", 'info');
        }
    }

    /**
     * Sync message templates from WhatsApp Business API
     *
     * @since 1.0.0
     * @return array|WP_Error Sync results or error
     */
    public function sync_templates()
    {
        if (empty($this->credentials['business_account_id'])) {
            return new \WP_Error('no_business_id', __('Business account ID not configured', 'chatshop'));
        }

        $response = $this->make_api_request('GET', $this->credentials['business_account_id'] . '/message_templates');

        if (is_wp_error($response)) {
            return $response;
        }

        $templates = $response['data'] ?? array();
        $synced_count = 0;
        $errors = array();

        foreach ($templates as $template) {
            if ($this->store_template($template)) {
                $synced_count++;
            } else {
                $errors[] = "Failed to store template: {$template['name']}";
            }
        }

        chatshop_log("Template sync completed: {$synced_count} templates synced", 'info');

        return array(
            'synced' => $synced_count,
            'total' => count($templates),
            'errors' => $errors
        );
    }

    /**
     * Store template in database
     *
     * @since 1.0.0
     * @param array $template_data Template data from API
     * @return bool True on success, false on failure
     */
    private function store_template($template_data)
    {
        global $wpdb;

        $template_record = array(
            'template_id' => $template_data['id'],
            'name' => $template_data['name'],
            'language' => $template_data['language'],
            'category' => $template_data['category'],
            'status' => $template_data['status'],
            'components' => wp_json_encode($template_data['components'] ?? array()),
            'updated_at' => current_time('mysql')
        );

        // Check if template exists
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$this->tables['templates']} WHERE template_id = %s AND language = %s",
            $template_data['id'],
            $template_data['language']
        ));

        if ($existing) {
            // Update existing template
            $result = $wpdb->update(
                $this->tables['templates'],
                $template_record,
                array('id' => $existing->id),
                null,
                array('%d')
            );
        } else {
            // Insert new template
            $template_record['created_at'] = current_time('mysql');
            $result = $wpdb->insert($this->tables['templates'], $template_record);
        }

        return $result !== false;
    }

    /**
     * Get available templates
     *
     * @since 1.0.0
     * @param string $status Template status filter
     * @return array Templates
     */
    public function get_templates($status = 'approved')
    {
        global $wpdb;

        $cache_key = "templates_{$status}";
        if (isset($this->templates_cache[$cache_key])) {
            return $this->templates_cache[$cache_key];
        }

        $where_clause = '';
        $where_values = array();

        if (!empty($status)) {
            $where_clause = 'WHERE status = %s';
            $where_values[] = $status;
        }

        $query = "SELECT * FROM {$this->tables['templates']} {$where_clause} ORDER BY name ASC";
        $templates = $wpdb->get_results($wpdb->prepare($query, $where_values));

        // Decode components JSON
        foreach ($templates as $template) {
            $template->components = json_decode($template->components, true);
        }

        $this->templates_cache[$cache_key] = $templates;
        return $templates;
    }

    /**
     * Get message history
     *
     * @since 1.0.0
     * @param array $args Query arguments
     * @return array Message history
     */
    public function get_message_history($args = array())
    {
        global $wpdb;

        $defaults = array(
            'phone' => '',
            'contact_id' => 0,
            'campaign_id' => 0,
            'direction' => '', // inbound, outbound
            'status' => '',
            'limit' => 50,
            'offset' => 0
        );

        $args = wp_parse_args($args, $defaults);

        // Build WHERE clause
        $where_conditions = array();
        $where_values = array();

        if (!empty($args['phone'])) {
            $where_conditions[] = 'phone = %s';
            $where_values[] = $args['phone'];
        }

        if (!empty($args['contact_id'])) {
            $where_conditions[] = 'contact_id = %d';
            $where_values[] = intval($args['contact_id']);
        }

        if (!empty($args['campaign_id'])) {
            $where_conditions[] = 'campaign_id = %d';
            $where_values[] = intval($args['campaign_id']);
        }

        if (!empty($args['direction'])) {
            $where_conditions[] = 'direction = %s';
            $where_values[] = $args['direction'];
        }

        if (!empty($args['status'])) {
            $where_conditions[] = 'status = %s';
            $where_values[] = $args['status'];
        }

        $where_clause = '';
        if (!empty($where_conditions)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        }

        $limit = intval($args['limit']);
        $offset = intval($args['offset']);

        $query = "
            SELECT * FROM {$this->tables['messages']} 
            {$where_clause} 
            ORDER BY created_at DESC 
            LIMIT {$offset}, {$limit}
        ";

        $messages = $wpdb->get_results($wpdb->prepare($query, $where_values));

        // Decode content JSON
        foreach ($messages as $message) {
            $message->content = json_decode($message->content, true);
            $message->template_params = json_decode($message->template_params, true);
        }

        return $messages;
    }

    /**
     * Update message statuses from API
     *
     * @since 1.0.0
     */
    public function update_message_statuses()
    {
        global $wpdb;

        // Get messages that need status updates (sent but not delivered/read)
        $messages = $wpdb->get_results(
            "SELECT * FROM {$this->tables['messages']} 
             WHERE direction = 'outbound' 
             AND status IN ('sent', 'delivered') 
             AND message_id IS NOT NULL 
             AND sent_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) 
             LIMIT 100"
        );

        foreach ($messages as $message) {
            // In a real implementation, you would query the WhatsApp API for message status
            // For now, we'll just log that we would check
            chatshop_log("Would check status for message: {$message->message_id}", 'debug');
        }
    }

    /**
     * Cleanup old messages
     *
     * @since 1.0.0
     */
    public function cleanup_old_messages()
    {
        global $wpdb;

        $retention_days = chatshop_get_option('whatsapp', 'message_retention_days', 90);
        $cutoff_date = date('Y-m-d', strtotime("-{$retention_days} days"));

        // Delete old messages
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->tables['messages']} WHERE DATE(created_at) < %s",
            $cutoff_date
        ));

        // Delete old webhooks
        $webhook_deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->tables['webhooks']} WHERE DATE(created_at) < %s",
            $cutoff_date
        ));

        if ($deleted > 0 || $webhook_deleted > 0) {
            chatshop_log("WhatsApp cleanup: {$deleted} messages, {$webhook_deleted} webhooks deleted", 'info');
        }
    }

    /**
     * Test API connection
     *
     * @since 1.0.0
     * @return array Connection test results
     */
    public function test_connection()
    {
        $results = array(
            'success' => false,
            'message' => '',
            'details' => array()
        );

        // Check credentials
        if (empty($this->credentials['access_token'])) {
            $results['message'] = __('Access token not configured', 'chatshop');
            return $results;
        }

        if (empty($this->credentials['phone_number_id'])) {
            $results['message'] = __('Phone number ID not configured', 'chatshop');
            return $results;
        }

        // Test API connection
        $response = $this->make_api_request('GET', $this->credentials['phone_number_id']);

        if (is_wp_error($response)) {
            $results['message'] = $response->get_error_message();
            $results['details']['error_code'] = $response->get_error_code();
            return $results;
        }

        $results['success'] = true;
        $results['message'] = __('Connection successful', 'chatshop');
        $results['details'] = array(
            'phone_number' => $response['display_phone_number'] ?? '',
            'verified_name' => $response['verified_name'] ?? '',
            'status' => $response['account_mode'] ?? ''
        );

        return $results;
    }

    /**
     * Get component status for admin display
     *
     * @since 1.0.0
     * @return array Component status
     */
    public function get_status()
    {
        global $wpdb;

        $status = array(
            'active' => true,
            'connected' => $this->is_connected,
            'tables_exist' => true,
            'total_messages' => 0,
            'recent_messages' => 0,
            'template_count' => 0,
            'errors' => array()
        );

        // Check if tables exist
        foreach ($this->tables as $table_name) {
            if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
                $status['tables_exist'] = false;
                $status['errors'][] = "Table {$table_name} does not exist";
            }
        }

        if ($status['tables_exist']) {
            // Get message statistics
            $status['total_messages'] = intval($wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->tables['messages']}"
            ));

            $status['recent_messages'] = intval($wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->tables['messages']} 
                 WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)"
            ));

            $status['template_count'] = intval($wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->tables['templates']} WHERE status = 'approved'"
            ));
        }

        if (!$this->is_connected) {
            $status['errors'][] = 'WhatsApp API not connected';
        }

        return $status;
    }

    // AJAX Handlers

    /**
     * AJAX handler for sending message
     *
     * @since 1.0.0
     */
    public function ajax_send_message()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'chatshop_admin_nonce')) {
            wp_die(__('Security check failed', 'chatshop'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'chatshop'));
        }

        $phone = sanitize_text_field($_POST['phone'] ?? '');
        $message_type = sanitize_key($_POST['message_type'] ?? 'text');
        $contact_id = intval($_POST['contact_id'] ?? 0);

        try {
            if ($message_type === 'text') {
                $text = sanitize_textarea_field($_POST['text'] ?? '');
                $result = $this->send_text_message($phone, $text, $contact_id ?: null);
            } elseif ($message_type === 'template') {
                $template_name = sanitize_text_field($_POST['template_name'] ?? '');
                $template_params = $_POST['template_params'] ?? array();
                $language = sanitize_text_field($_POST['language'] ?? 'en');

                $result = $this->send_template_message($phone, $template_name, $template_params, $language, $contact_id ?: null);
            } else {
                throw new Exception(__('Unsupported message type', 'chatshop'));
            }

            if (is_wp_error($result)) {
                wp_send_json_error(array('message' => $result->get_error_message()));
            } else {
                wp_send_json_success(array(
                    'message' => __('Message sent successfully', 'chatshop'),
                    'message_id' => $result['message_id']
                ));
            }
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * AJAX handler for testing connection
     *
     * @since 1.0.0
     */
    public function ajax_test_connection()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'chatshop_admin_nonce')) {
            wp_die(__('Security check failed', 'chatshop'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'chatshop'));
        }

        $results = $this->test_connection();

        if ($results['success']) {
            wp_send_json_success($results);
        } else {
            wp_send_json_error($results);
        }
    }

    /**
     * AJAX handler for syncing templates
     *
     * @since 1.0.0
     */
    public function ajax_sync_templates()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'chatshop_admin_nonce')) {
            wp_die(__('Security check failed', 'chatshop'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'chatshop'));
        }

        $results = $this->sync_templates();

        if (is_wp_error($results)) {
            wp_send_json_error(array('message' => $results->get_error_message()));
        } else {
            wp_send_json_success(array(
                'message' => sprintf(__('%d templates synced successfully', 'chatshop'), $results['synced']),
                'results' => $results
            ));
        }
    }

    /**
     * AJAX handler for getting message history
     *
     * @since 1.0.0
     */
    public function ajax_get_message_history()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'chatshop_admin_nonce')) {
            wp_die(__('Security check failed', 'chatshop'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'chatshop'));
        }

        $args = array(
            'phone' => sanitize_text_field($_POST['phone'] ?? ''),
            'contact_id' => intval($_POST['contact_id'] ?? 0),
            'limit' => intval($_POST['limit'] ?? 50),
            'offset' => intval($_POST['offset'] ?? 0)
        );

        $messages = $this->get_message_history($args);
        wp_send_json_success($messages);
    }
}
