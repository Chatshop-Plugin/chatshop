// Validate and update contact opt-in status
if ($result['success'] && $this->contact_manager) {
$this->contact_manager->update_opt_in_status($phone, true);
}<?php

    /**
     * WhatsApp API Client Class
     *
     * Handles direct WhatsApp Business API integration with Web API fallback.
     * Supports message templates, product notifications, and premium features.
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
     * ChatShop WhatsApp API Client
     *
     * Direct WhatsApp Business API integration with fallback to Web API.
     * Handles message sending, templates, and session management.
     *
     * @since 1.0.0
     */
    class ChatShop_WhatsApp_API
    {
        /**
         * WhatsApp Business API base URL
         *
         * @var string
         * @since 1.0.0
         */
        private $business_api_url = 'https://graph.facebook.com/v18.0/';

        /**
         * WhatsApp Web API base URL (fallback)
         *
         * @var string
         * @since 1.0.0
         */
        private $web_api_url = 'https://api.whatsapp.com/send';

        /**
         * API configuration
         *
         * @var array
         * @since 1.0.0
         */
        private $config;

        /**
         * Rate limiting settings
         *
         * @var array
         * @since 1.0.0
         */
        private $rate_limits = array(
            'messages_per_hour' => 1000,
            'messages_per_day' => 10000,
            'delay_between_messages' => 1, // seconds
        );

        /**
         * Session data
         *
         * @var array
         * @since 1.0.0
         */
        private $session;

        /**
         * Premium features status
         *
         * @var array
         * @since 1.0.0
         */
        private $premium_features;

        /**
         * Message templates instance
         *
         * @var ChatShop_Message_Templates
         * @since 1.0.0
         */
        private $templates;

        /**
         * Contact manager instance
         *
         * @var ChatShop_Contact_Manager
         * @since 1.0.0
         */
        private $contact_manager;

        /**
         * Constructor
         *
         * @since 1.0.0
         */
        public function __construct()
        {
            $this->load_config();
            $this->init_session();
            $this->init_premium_features();
            $this->init_components();
        }

        /**
         * Initialize component dependencies
         *
         * @since 1.0.0
         */
        private function init_components()
        {
            if (class_exists('ChatShop\ChatShop_Message_Templates')) {
                $this->templates = new ChatShop_Message_Templates();
            }

            if (class_exists('ChatShop\ChatShop_Contact_Manager')) {
                $this->contact_manager = new ChatShop_Contact_Manager();
            }
        }

        /**
         * Load API configuration
         *
         * @since 1.0.0
         */
        private function load_config()
        {
            $options = get_option('chatshop_whatsapp_options', array());

            $this->config = array(
                'enabled' => isset($options['enabled']) ? (bool) $options['enabled'] : false,
                'api_type' => $options['api_type'] ?? 'business', // 'business' or 'web'
                'phone_number_id' => $options['phone_number_id'] ?? '',
                'access_token' => $this->decrypt_token($options['access_token'] ?? ''),
                'verify_token' => $options['verify_token'] ?? '',
                'app_id' => $options['app_id'] ?? '',
                'app_secret' => $this->decrypt_token($options['app_secret'] ?? ''),
                'webhook_url' => $options['webhook_url'] ?? '',
                'fallback_enabled' => isset($options['fallback_enabled']) ? (bool) $options['fallback_enabled'] : true,
            );
        }

        /**
         * Initialize session management
         *
         * @since 1.0.0
         */
        private function init_session()
        {
            $this->session = array(
                'active' => false,
                'last_activity' => 0,
                'message_count_hour' => 0,
                'message_count_day' => 0,
                'last_reset_hour' => 0,
                'last_reset_day' => 0,
            );

            // Load session from transients
            $stored_session = get_transient('chatshop_whatsapp_session');
            if ($stored_session) {
                $this->session = array_merge($this->session, $stored_session);
            }
        }

        /**
         * Initialize premium features
         *
         * @since 1.0.0
         */
        private function init_premium_features()
        {
            $license_status = get_option('chatshop_license_status', 'free');
            $premium_options = get_option('chatshop_premium_options', array());

            $this->premium_features = array(
                'bulk_messaging' => $license_status !== 'free' &&
                    isset($premium_options['whatsapp_bulk_messaging']) &&
                    $premium_options['whatsapp_bulk_messaging'],
                'templates' => $license_status !== 'free',
                'media_messages' => $license_status !== 'free',
                'automation' => $license_status !== 'free' &&
                    isset($premium_options['whatsapp_automation']) &&
                    $premium_options['whatsapp_automation'],
            );
        }

        /**
         * Send message via WhatsApp
         *
         * @since 1.0.0
         * @param string $phone Phone number (with country code)
         * @param string $message Message content
         * @param string $type Message type ('text', 'template', 'media')
         * @param array  $options Additional options
         * @return array Result array with success status and message
         */
        public function send_message($phone, $message, $type = 'text', $options = array())
        {
            if (!$this->config['enabled']) {
                return array(
                    'success' => false,
                    'message' => __('WhatsApp integration is disabled', 'chatshop'),
                );
            }

            // Validate phone number
            $phone = $this->format_phone_number($phone);
            if (!$phone) {
                return array(
                    'success' => false,
                    'message' => __('Invalid phone number format', 'chatshop'),
                );
            }

            // Check rate limits
            $rate_check = $this->check_rate_limits();
            if (!$rate_check['allowed']) {
                return array(
                    'success' => false,
                    'message' => $rate_check['message'],
                );
            }

            // Send via Business API first, fallback to Web API if needed
            $result = $this->send_via_business_api($phone, $message, $type, $options);

            if (!$result['success'] && $this->config['fallback_enabled']) {
                chatshop_log('Business API failed, trying Web API fallback', 'warning');
                $result = $this->send_via_web_api($phone, $message, $options);
            }

            // Update rate limiting counters
            if ($result['success']) {
                $this->update_rate_counters();

                // Update contact opt-in status if contact manager is available
                if ($this->contact_manager) {
                    $this->contact_manager->update_opt_in_status($phone, true);
                }
            }

            // Log the attempt
            $this->log_message_attempt($phone, $message, $type, $result);

            return $result;
        }

        /**
         * Send product notification
         *
         * @since 1.0.0
         * @param string $phone Phone number
         * @param int    $product_id WooCommerce product ID
         * @param string $event Event type ('new_product', 'price_drop', 'back_in_stock')
         * @return array Result array
         */
        public function send_product_notification($phone, $product_id, $event = 'new_product')
        {
            $product = wc_get_product($product_id);
            if (!$product) {
                return array(
                    'success' => false,
                    'message' => __('Product not found', 'chatshop'),
                );
            }

            $message = $this->format_product_message($product, $event);
            $options = array(
                'product_id' => $product_id,
                'event' => $event,
            );

            return $this->send_message($phone, $message, 'text', $options);
        }

        /**
         * Send template message (premium feature)
         *
         * @since 1.0.0
         * @param string $phone Phone number
         * @param string $template_name Template name
         * @param array  $parameters Template parameters
         * @return array Result array
         */
        public function send_template_message($phone, $template_name, $parameters = array())
        {
            if (!$this->premium_features['templates']) {
                return array(
                    'success' => false,
                    'message' => __('Template messages are a premium feature', 'chatshop'),
                );
            }

            $template_data = array(
                'name' => $template_name,
                'language' => array('code' => 'en'),
                'components' => $this->build_template_components($parameters),
            );

            return $this->send_message($phone, '', 'template', array('template' => $template_data));
        }

        /**
         * Send bulk messages (premium feature)
         *
         * @since 1.0.0
         * @param array  $contacts Array of phone numbers
         * @param string $message Message content
         * @param string $type Message type
         * @return array Result array with individual results
         */
        public function send_bulk_messages($contacts, $message, $type = 'text')
        {
            if (!$this->premium_features['bulk_messaging']) {
                return array(
                    'success' => false,
                    'message' => __('Bulk messaging is a premium feature', 'chatshop'),
                );
            }

            $results = array();
            $success_count = 0;
            $failed_count = 0;

            foreach ($contacts as $phone) {
                $result = $this->send_message($phone, $message, $type);

                $results[] = array(
                    'phone' => $phone,
                    'success' => $result['success'],
                    'message' => $result['message'],
                );

                if ($result['success']) {
                    $success_count++;
                } else {
                    $failed_count++;
                }

                // Rate limiting delay
                sleep($this->rate_limits['delay_between_messages']);
            }

            return array(
                'success' => $success_count > 0,
                'total' => count($contacts),
                'success_count' => $success_count,
                'failed_count' => $failed_count,
                'results' => $results,
            );
        }

        /**
         * Send cart abandonment recovery message (premium feature)
         *
         * @since 1.0.0
         * @param string $phone Phone number
         * @param string $cart_token Cart token
         * @param array  $cart_data Cart data
         * @return array Result array
         */
        public function send_cart_recovery_message($phone, $cart_token, $cart_data)
        {
            if (!$this->premium_features['automation']) {
                return array(
                    'success' => false,
                    'message' => __('Cart recovery is a premium feature', 'chatshop'),
                );
            }

            $message = $this->format_cart_recovery_message($cart_data, $cart_token);

            return $this->send_message($phone, $message, 'text', array(
                'cart_token' => $cart_token,
                'type' => 'cart_recovery',
            ));
        }

        /**
         * Send media message (premium feature)
         *
         * @since 1.0.0
         * @param string $phone Phone number
         * @param string $media_url Media URL
         * @param string $media_type Media type ('image', 'video', 'document')
         * @param string $caption Optional caption
         * @return array Result array
         */
        public function send_media_message($phone, $media_url, $media_type, $caption = '')
        {
            if (!$this->premium_features['media_messages']) {
                return array(
                    'success' => false,
                    'message' => __('Media messages are a premium feature', 'chatshop'),
                );
            }

            $options = array(
                'media_url' => $media_url,
                'media_type' => $media_type,
                'caption' => $caption,
            );

            return $this->send_message($phone, $caption, 'media', $options);
        }

        /**
         * Send message via WhatsApp Business API
         *
         * @since 1.0.0
         * @param string $phone Phone number
         * @param string $message Message content
         * @param string $type Message type
         * @param array  $options Additional options
         * @return array Result array
         */
        private function send_via_business_api($phone, $message, $type, $options)
        {
            if (empty($this->config['access_token']) || empty($this->config['phone_number_id'])) {
                return array(
                    'success' => false,
                    'message' => __('WhatsApp Business API not configured', 'chatshop'),
                );
            }

            $url = $this->business_api_url . $this->config['phone_number_id'] . '/messages';
            $headers = array(
                'Authorization' => 'Bearer ' . $this->config['access_token'],
                'Content-Type' => 'application/json',
            );

            $body = $this->build_message_payload($phone, $message, $type, $options);

            $response = wp_remote_post($url, array(
                'headers' => $headers,
                'body' => wp_json_encode($body),
                'timeout' => 30,
            ));

            return $this->process_api_response($response, 'business');
        }

        /**
         * Send message via WhatsApp Web API (fallback)
         *
         * @since 1.0.0
         * @param string $phone Phone number
         * @param string $message Message content
         * @param array  $options Additional options
         * @return array Result array
         */
        private function send_via_web_api($phone, $message, $options)
        {
            $web_url = add_query_arg(array(
                'phone' => $phone,
                'text' => urlencode($message),
            ), $this->web_api_url);

            // For Web API, we can only generate the URL and log it
            // Actual sending would require user interaction
            chatshop_log("Web API URL generated: {$web_url}", 'info');

            return array(
                'success' => true,
                'message' => __('WhatsApp Web URL generated', 'chatshop'),
                'web_url' => $web_url,
            );
        }

        /**
         * Build message payload for Business API
         *
         * @since 1.0.0
         * @param string $phone Phone number
         * @param string $message Message content
         * @param string $type Message type
         * @param array  $options Additional options
         * @return array Message payload
         */
        private function build_message_payload($phone, $message, $type, $options)
        {
            $payload = array(
                'messaging_product' => 'whatsapp',
                'to' => $phone,
            );

            switch ($type) {
                case 'template':
                    $payload['type'] = 'template';
                    $payload['template'] = $options['template'];
                    break;

                case 'media':
                    $media_type = $options['media_type'] ?? 'image';
                    $payload['type'] = $media_type;
                    $payload[$media_type] = array(
                        'link' => $options['media_url'],
                    );

                    if (!empty($options['caption'])) {
                        $payload[$media_type]['caption'] = $options['caption'];
                    }
                    break;

                case 'text':
                default:
                    $payload['type'] = 'text';
                    $payload['text'] = array('body' => $message);
                    break;
            }

            return $payload;
        }

        /**
         * Build template components
         *
         * @since 1.0.0
         * @param array $parameters Template parameters
         * @return array Template components
         */
        private function build_template_components($parameters)
        {
            if (empty($parameters)) {
                return array();
            }

            $components = array();

            // Header parameters
            if (isset($parameters['header'])) {
                $components[] = array(
                    'type' => 'header',
                    'parameters' => $parameters['header'],
                );
            }

            // Body parameters
            if (isset($parameters['body'])) {
                $components[] = array(
                    'type' => 'body',
                    'parameters' => $parameters['body'],
                );
            }

            // Button parameters
            if (isset($parameters['buttons'])) {
                $components[] = array(
                    'type' => 'button',
                    'sub_type' => 'quick_reply',
                    'index' => '0',
                    'parameters' => $parameters['buttons'],
                );
            }

            return $components;
        }

        /**
         * Process API response
         *
         * @since 1.0.0
         * @param array|WP_Error $response API response
         * @param string         $api_type API type ('business' or 'web')
         * @return array Result array
         */
        private function process_api_response($response, $api_type)
        {
            if (is_wp_error($response)) {
                return array(
                    'success' => false,
                    'message' => sprintf(__('API request failed: %s', 'chatshop'), $response->get_error_message()),
                );
            }

            $code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if ($code >= 200 && $code < 300) {
                $message_id = '';

                if ($api_type === 'business' && isset($data['messages'][0]['id'])) {
                    $message_id = $data['messages'][0]['id'];
                }

                return array(
                    'success' => true,
                    'message' => __('Message sent successfully', 'chatshop'),
                    'message_id' => $message_id,
                    'api_type' => $api_type,
                );
            }

            $error_message = __('Failed to send message', 'chatshop');

            if (isset($data['error']['message'])) {
                $error_message = $data['error']['message'];
            }

            return array(
                'success' => false,
                'message' => $error_message,
                'error_code' => $code,
                'api_type' => $api_type,
            );
        }

        /**
         * Check rate limits
         *
         * @since 1.0.0
         * @return array Rate limit check result
         */
        private function check_rate_limits()
        {
            $current_time = time();
            $current_hour = gmdate('H', $current_time);
            $current_day = gmdate('Y-m-d', $current_time);

            // Reset hourly counter
            if ($this->session['last_reset_hour'] !== $current_hour) {
                $this->session['message_count_hour'] = 0;
                $this->session['last_reset_hour'] = $current_hour;
            }

            // Reset daily counter
            if ($this->session['last_reset_day'] !== $current_day) {
                $this->session['message_count_day'] = 0;
                $this->session['last_reset_day'] = $current_day;
            }

            // Check hourly limit
            if ($this->session['message_count_hour'] >= $this->rate_limits['messages_per_hour']) {
                return array(
                    'allowed' => false,
                    'message' => __('Hourly message limit exceeded', 'chatshop'),
                );
            }

            // Check daily limit
            if ($this->session['message_count_day'] >= $this->rate_limits['messages_per_day']) {
                return array(
                    'allowed' => false,
                    'message' => __('Daily message limit exceeded', 'chatshop'),
                );
            }

            return array('allowed' => true);
        }

        /**
         * Update rate limiting counters
         *
         * @since 1.0.0
         */
        private function update_rate_counters()
        {
            $this->session['message_count_hour']++;
            $this->session['message_count_day']++;
            $this->session['last_activity'] = time();

            // Save session to transients
            set_transient('chatshop_whatsapp_session', $this->session, DAY_IN_SECONDS);
        }

        /**
         * Format phone number for WhatsApp
         *
         * @since 1.0.0
         * @param string $phone Raw phone number
         * @return string|false Formatted phone number or false if invalid
         */
        private function format_phone_number($phone)
        {
            if (empty($phone)) {
                return false;
            }

            // Remove all non-numeric characters
            $phone = preg_replace('/[^0-9]/', '', $phone);

            // Remove leading zeros
            $phone = ltrim($phone, '0');

            // Add country code if not present (assuming Nigeria +234 as default)
            if (strlen($phone) === 10) {
                $phone = '234' . $phone;
            }

            // Validate phone number length
            if (strlen($phone) < 10 || strlen($phone) > 15) {
                return false;
            }

            return $phone;
        }

        /**
         * Format product message
         *
         * @since 1.0.0
         * @param WC_Product $product WooCommerce product
         * @param string     $event Event type
         * @return string Formatted message
         */
        private function format_product_message($product, $event)
        {
            $product_name = $product->get_name();
            $product_price = $product->get_price_html();
            $product_url = $product->get_permalink();

            switch ($event) {
                case 'new_product':
                    return sprintf(
                        __("🎉 New Product Alert!\n\n%s\nPrice: %s\n\nCheck it out: %s", 'chatshop'),
                        $product_name,
                        $product_price,
                        $product_url
                    );

                case 'price_drop':
                    return sprintf(
                        __("💰 Price Drop Alert!\n\n%s\nNew Price: %s\n\nDon't miss out: %s", 'chatshop'),
                        $product_name,
                        $product_price,
                        $product_url
                    );

                case 'back_in_stock':
                    return sprintf(
                        __("📦 Back in Stock!\n\n%s\nPrice: %s\n\nOrder now: %s", 'chatshop'),
                        $product_name,
                        $product_price,
                        $product_url
                    );

                default:
                    return sprintf(
                        __("📢 Product Update!\n\n%s\nPrice: %s\n\nView details: %s", 'chatshop'),
                        $product_name,
                        $product_price,
                        $product_url
                    );
            }
        }

        /**
         * Format cart recovery message
         *
         * @since 1.0.0
         * @param array  $cart_data Cart data
         * @param string $cart_token Cart token
         * @return string Formatted message
         */
        private function format_cart_recovery_message($cart_data, $cart_token)
        {
            $recovery_url = add_query_arg(array(
                'chatshop_recover_cart' => $cart_token,
            ), wc_get_cart_url());

            $item_count = count($cart_data['items'] ?? array());
            $total = $cart_data['total'] ?? '';

            return sprintf(
                __("🛒 You left %d item(s) in your cart!\n\nTotal: %s\n\nComplete your purchase:\n%s\n\n⏰ Limited time - don't miss out!", 'chatshop'),
                $item_count,
                $total,
                $recovery_url
            );
        }

        /**
         * Log message attempt
         *
         * @since 1.0.0
         * @param string $phone Phone number
         * @param string $message Message content
         * @param string $type Message type
         * @param array  $result Send result
         */
        private function log_message_attempt($phone, $message, $type, $result)
        {
            $log_data = array(
                'phone' => $phone,
                'type' => $type,
                'success' => $result['success'],
                'api_type' => $result['api_type'] ?? 'unknown',
                'message_id' => $result['message_id'] ?? '',
                'error' => $result['success'] ? '' : $result['message'],
            );

            chatshop_log("WhatsApp message attempt: " . wp_json_encode($log_data), 'info');
        }

        /**
         * Decrypt token/secret
         *
         * @since 1.0.0
         * @param string $encrypted_token Encrypted token
         * @return string Decrypted token
         */
        private function decrypt_token($encrypted_token)
        {
            if (empty($encrypted_token)) {
                return '';
            }

            $encryption_key = wp_salt('auth');
            $decrypted = openssl_decrypt(
                $encrypted_token,
                'AES-256-CBC',
                $encryption_key,
                0,
                substr($encryption_key, 0, 16)
            );

            return $decrypted !== false ? $decrypted : '';
        }

        /**
         * Verify webhook signature
         *
         * @since 1.0.0
         * @param string $payload Webhook payload
         * @param string $signature Webhook signature
         * @return bool Whether signature is valid
         */
        public function verify_webhook_signature($payload, $signature)
        {
            if (empty($this->config['app_secret'])) {
                return false;
            }

            $expected_signature = hash_hmac('sha256', $payload, $this->config['app_secret']);

            return hash_equals('sha256=' . $expected_signature, $signature);
        }

        /**
         * Process webhook payload
         *
         * @since 1.0.0
         * @param array $payload Webhook payload
         * @return array Processing result
         */
        public function process_webhook($payload)
        {
            if (!isset($payload['entry']) || !is_array($payload['entry'])) {
                return array(
                    'success' => false,
                    'message' => __('Invalid webhook payload', 'chatshop'),
                );
            }

            $processed = 0;

            foreach ($payload['entry'] as $entry) {
                if (isset($entry['changes'])) {
                    foreach ($entry['changes'] as $change) {
                        if ($change['field'] === 'messages') {
                            $this->process_message_status_change($change['value']);
                            $processed++;
                        }
                    }
                }
            }

            return array(
                'success' => true,
                'message' => sprintf(__('Processed %d webhook events', 'chatshop'), $processed),
            );
        }

        /**
         * Process message status change from webhook
         *
         * @since 1.0.0
         * @param array $data Message status data
         */
        private function process_message_status_change($data)
        {
            if (!isset($data['statuses'])) {
                return;
            }

            foreach ($data['statuses'] as $status) {
                $message_id = $status['id'] ?? '';
                $status_type = $status['status'] ?? '';
                $timestamp = $status['timestamp'] ?? time();

                // Update message status in database
                global $wpdb;

                $wpdb->update(
                    $wpdb->prefix . 'chatshop_whatsapp_logs',
                    array(
                        'status' => $status_type,
                        'updated_at' => gmdate('Y-m-d H:i:s', $timestamp),
                    ),
                    array('message_id' => $message_id),
                    array('%s', '%s'),
                    array('%s')
                );

                chatshop_log("Message status updated: {$message_id} -> {$status_type}", 'info');
            }
        }

        /**
         * Get API configuration
         *
         * @since 1.0.0
         * @return array API configuration
         */
        public function get_config()
        {
            // Return config without sensitive data
            $safe_config = $this->config;
            unset($safe_config['access_token'], $safe_config['app_secret']);

            return $safe_config;
        }

        /**
         * Get rate limit status
         *
         * @since 1.0.0
         * @return array Rate limit status
         */
        public function get_rate_limit_status()
        {
            return array(
                'hourly_used' => $this->session['message_count_hour'],
                'hourly_limit' => $this->rate_limits['messages_per_hour'],
                'daily_used' => $this->session['message_count_day'],
                'daily_limit' => $this->rate_limits['messages_per_day'],
                'last_activity' => $this->session['last_activity'],
            );
        }

        /**
         * Test API connection
         *
         * @since 1.0.0
         * @return array Test result
         */
        public function test_connection()
        {
            if (!$this->config['enabled']) {
                return array(
                    'success' => false,
                    'message' => __('WhatsApp integration is disabled', 'chatshop'),
                );
            }

            if ($this->config['api_type'] === 'business') {
                return $this->test_business_api();
            }

            return array(
                'success' => true,
                'message' => __('Web API configuration looks good', 'chatshop'),
            );
        }

        /**
         * Test Business API connection
         *
         * @since 1.0.0
         * @return array Test result
         */
        private function test_business_api()
        {
            if (empty($this->config['access_token']) || empty($this->config['phone_number_id'])) {
                return array(
                    'success' => false,
                    'message' => __('Missing Business API credentials', 'chatshop'),
                );
            }

            $url = $this->business_api_url . $this->config['phone_number_id'];
            $headers = array(
                'Authorization' => 'Bearer ' . $this->config['access_token'],
            );

            $response = wp_remote_get($url, array(
                'headers' => $headers,
                'timeout' => 15,
            ));

            if (is_wp_error($response)) {
                return array(
                    'success' => false,
                    'message' => sprintf(__('Connection failed: %s', 'chatshop'), $response->get_error_message()),
                );
            }

            $code = wp_remote_retrieve_response_code($response);

            if ($code === 200) {
                return array(
                    'success' => true,
                    'message' => __('Business API connection successful', 'chatshop'),
                );
            }

            return array(
                'success' => false,
                'message' => sprintf(__('API returned error code: %d', 'chatshop'), $code),
            );
        }
    }
