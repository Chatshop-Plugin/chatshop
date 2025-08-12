<?php

/**
 * ChatShop Message Templates
 *
 * Manages WhatsApp message templates
 *
 * @package ChatShop
 * @subpackage Templates
 * @since 1.0.0
 */

namespace ChatShop\Templates;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Message Templates class
 *
 * Handles template management, creation, and WhatsApp Business API integration
 */
class ChatShop_Message_Templates
{

    /**
     * WhatsApp API instance
     *
     * @var \ChatShop\WhatsApp\ChatShop_WhatsApp_API
     */
    private $api;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->api = new \ChatShop\WhatsApp\ChatShop_WhatsApp_API();

        add_action('wp_ajax_chatshop_create_template', [$this, 'ajax_create_template']);
        add_action('wp_ajax_chatshop_sync_templates', [$this, 'ajax_sync_templates']);
        add_action('wp_ajax_chatshop_preview_template', [$this, 'ajax_preview_template']);
    }

    /**
     * Create message template
     *
     * @param array $template_data Template data
     * @return int|WP_Error Template ID or error
     */
    public function create_template($template_data)
    {
        $validation = $this->validate_template_data($template_data);
        if (is_wp_error($validation)) {
            return $validation;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'chatshop_message_templates';

        $sanitized_data = $this->sanitize_template_data($template_data);

        $result = $wpdb->insert(
            $table_name,
            [
                'name' => $sanitized_data['name'],
                'category' => $sanitized_data['category'],
                'language' => $sanitized_data['language'],
                'header_type' => $sanitized_data['header_type'],
                'header_content' => $sanitized_data['header_content'],
                'body_text' => $sanitized_data['body_text'],
                'footer_text' => $sanitized_data['footer_text'],
                'buttons' => json_encode($sanitized_data['buttons']),
                'status' => 'draft',
                'created_by' => get_current_user_id(),
                'created_at' => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s']
        );

        if ($result === false) {
            return new \WP_Error('db_error', __('Failed to create template', 'chatshop'));
        }

        $template_id = $wpdb->insert_id;

        do_action('chatshop_template_created', $template_id, $sanitized_data);

        return $template_id;
    }

    /**
     * Submit template to WhatsApp for approval
     *
     * @param int $template_id Template ID
     * @return bool|WP_Error True on success, error on failure
     */
    public function submit_template_for_approval($template_id)
    {
        $template = $this->get_template($template_id);
        if (!$template) {
            return new \WP_Error('not_found', __('Template not found', 'chatshop'));
        }

        if ($template['status'] !== 'draft') {
            return new \WP_Error('invalid_status', __('Only draft templates can be submitted', 'chatshop'));
        }

        // Prepare template data for WhatsApp API
        $api_data = $this->prepare_template_for_api($template);

        // Submit to WhatsApp Business API
        $response = $this->api->create_message_template($api_data);

        if (is_wp_error($response)) {
            return $response;
        }

        // Update template status and WhatsApp template ID
        $this->update_template_status($template_id, 'pending', $response['id'] ?? '');

        return true;
    }

    /**
     * Get template by ID
     *
     * @param int $template_id Template ID
     * @return array|null Template data or null if not found
     */
    public function get_template($template_id)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'chatshop_message_templates';

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %d",
            $template_id
        ), ARRAY_A);
    }

    /**
     * Get templates with filters
     *
     * @param array $args Query arguments
     * @return array Templates data
     */
    public function get_templates($args = [])
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'chatshop_message_templates';

        $defaults = [
            'status' => '',
            'category' => '',
            'language' => '',
            'limit' => 20,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC'
        ];

        $args = wp_parse_args($args, $defaults);

        $where_conditions = ['1=1'];
        $where_values = [];

        if (!empty($args['status'])) {
            $where_conditions[] = 'status = %s';
            $where_values[] = $args['status'];
        }

        if (!empty($args['category'])) {
            $where_conditions[] = 'category = %s';
            $where_values[] = $args['category'];
        }

        if (!empty($args['language'])) {
            $where_conditions[] = 'language = %s';
            $where_values[] = $args['language'];
        }

        $where_clause = implode(' AND ', $where_conditions);
        $order_clause = sprintf('ORDER BY %s %s', $args['orderby'], $args['order']);
        $limit_clause = sprintf('LIMIT %d OFFSET %d', $args['limit'], $args['offset']);

        $query = "SELECT * FROM {$table_name} WHERE {$where_clause} {$order_clause} {$limit_clause}";

        if (!empty($where_values)) {
            $query = $wpdb->prepare($query, $where_values);
        }

        return $wpdb->get_results($query, ARRAY_A);
    }

    /**
     * Get approved templates for sending
     *
     * @param string $language Optional language filter
     * @return array Approved templates
     */
    public function get_approved_templates($language = '')
    {
        $args = [
            'status' => 'approved',
            'language' => $language
        ];

        return $this->get_templates($args);
    }

    /**
     * Sync templates with WhatsApp Business API
     *
     * @return array|WP_Error Sync results or error
     */
    public function sync_templates_with_whatsapp()
    {
        // Get templates from WhatsApp API
        $api_templates = $this->api->get_message_templates();

        if (is_wp_error($api_templates)) {
            return $api_templates;
        }

        $sync_results = [
            'updated' => 0,
            'created' => 0,
            'errors' => []
        ];

        global $wpdb;
        $table_name = $wpdb->prefix . 'chatshop_message_templates';

        foreach ($api_templates as $api_template) {
            $whatsapp_id = $api_template['id'];

            // Check if template exists locally
            $local_template = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE whatsapp_template_id = %s",
                $whatsapp_id
            ), ARRAY_A);

            if ($local_template) {
                // Update existing template
                $update_result = $wpdb->update(
                    $table_name,
                    [
                        'status' => $api_template['status'],
                        'updated_at' => current_time('mysql')
                    ],
                    ['whatsapp_template_id' => $whatsapp_id],
                    ['%s', '%s'],
                    ['%s']
                );

                if ($update_result !== false) {
                    $sync_results['updated']++;
                } else {
                    $sync_results['errors'][] = sprintf(
                        __('Failed to update template: %s', 'chatshop'),
                        $api_template['name']
                    );
                }
            } else {
                // Create new template from API data
                $template_data = $this->parse_api_template($api_template);
                $template_data['whatsapp_template_id'] = $whatsapp_id;
                $template_data['status'] = $api_template['status'];

                $insert_result = $wpdb->insert(
                    $table_name,
                    $template_data,
                    $this->get_template_column_formats()
                );

                if ($insert_result !== false) {
                    $sync_results['created']++;
                } else {
                    $sync_results['errors'][] = sprintf(
                        __('Failed to create template: %s', 'chatshop'),
                        $api_template['name']
                    );
                }
            }
        }

        return $sync_results;
    }

    /**
     * Get predefined templates
     *
     * @return array Predefined templates
     */
    public function get_predefined_templates()
    {
        return [
            'order_confirmation' => [
                'name' => 'order_confirmation',
                'category' => 'transactional',
                'header_type' => 'text',
                'header_content' => __('Order Confirmed! 🎉', 'chatshop'),
                'body_text' => __('Hi {{1}}, your order #{{2}} has been confirmed! Total: {{3}}. We\'ll send you updates as we prepare your order.', 'chatshop'),
                'footer_text' => __('Thank you for shopping with us!', 'chatshop'),
                'buttons' => [
                    [
                        'type' => 'url',
                        'text' => __('Track Order', 'chatshop'),
                        'url' => 'https://yourstore.com/track/{{4}}'
                    ]
                ]
            ],
            'order_shipped' => [
                'name' => 'order_shipped',
                'category' => 'transactional',
                'header_type' => 'text',
                'header_content' => __('Your Order is On The Way! 🚚', 'chatshop'),
                'body_text' => __('Great news {{1}}! Your order #{{2}} has been shipped and is on its way to you. Tracking number: {{3}}', 'chatshop'),
                'footer_text' => __('Estimated delivery: {{4}}', 'chatshop'),
                'buttons' => [
                    [
                        'type' => 'url',
                        'text' => __('Track Package', 'chatshop'),
                        'url' => 'https://tracking.com/{{5}}'
                    ]
                ]
            ],
            'cart_abandonment' => [
                'name' => 'cart_abandonment',
                'category' => 'marketing',
                'header_type' => 'text',
                'header_content' => __('Don\'t Forget Your Items! 🛒', 'chatshop'),
                'body_text' => __('Hi {{1}}, you left some great items in your cart! Your cart total is {{2}}. Complete your purchase now and get them delivered to you.', 'chatshop'),
                'footer_text' => __('Items may sell out soon!', 'chatshop'),
                'buttons' => [
                    [
                        'type' => 'url',
                        'text' => __('Complete Purchase', 'chatshop'),
                        'url' => 'https://yourstore.com/checkout'
                    ]
                ]
            ],
            'welcome_message' => [
                'name' => 'welcome_message',
                'category' => 'utility',
                'header_type' => 'text',
                'header_content' => __('Welcome to Our Store! 👋', 'chatshop'),
                'body_text' => __('Hi {{1}}! Welcome to our WhatsApp store. Browse our products, place orders, and get instant updates right here on WhatsApp.', 'chatshop'),
                'footer_text' => __('We\'re here to help!', 'chatshop'),
                'buttons' => [
                    [
                        'type' => 'quick_reply',
                        'text' => __('View Catalog', 'chatshop')
                    ],
                    [
                        'type' => 'quick_reply',
                        'text' => __('Contact Support', 'chatshop')
                    ]
                ]
            ],
            'payment_reminder' => [
                'name' => 'payment_reminder',
                'category' => 'transactional',
                'header_type' => 'text',
                'header_content' => __('Payment Reminder 💳', 'chatshop'),
                'body_text' => __('Hi {{1}}, your order #{{2}} is waiting for payment. Amount: {{3}}. Please complete your payment to confirm your order.', 'chatshop'),
                'footer_text' => __('Payment link expires in 24 hours', 'chatshop'),
                'buttons' => [
                    [
                        'type' => 'url',
                        'text' => __('Pay Now', 'chatshop'),
                        'url' => '{{4}}'
                    ]
                ]
            ],
            'product_back_in_stock' => [
                'name' => 'product_back_in_stock',
                'category' => 'marketing',
                'header_type' => 'text',
                'header_content' => __('Back in Stock! ✨', 'chatshop'),
                'body_text' => __('Good news {{1}}! {{2}} is back in stock. Get yours now before it sells out again!', 'chatshop'),
                'footer_text' => __('Limited quantity available', 'chatshop'),
                'buttons' => [
                    [
                        'type' => 'url',
                        'text' => __('Buy Now', 'chatshop'),
                        'url' => '{{3}}'
                    ]
                ]
            ]
        ];
    }

    /**
     * Create predefined template
     *
     * @param string $template_key Template key
     * @param string $language Language code
     * @return int|WP_Error Template ID or error
     */
    public function create_predefined_template($template_key, $language = 'en')
    {
        $predefined = $this->get_predefined_templates();

        if (!isset($predefined[$template_key])) {
            return new \WP_Error('invalid_template', __('Predefined template not found', 'chatshop'));
        }

        $template_data = $predefined[$template_key];
        $template_data['language'] = $language;

        return $this->create_template($template_data);
    }

    /**
     * Preview template with sample data
     *
     * @param array $template_data Template data
     * @param array $sample_params Sample parameters
     * @return array Preview data
     */
    public function preview_template($template_data, $sample_params = [])
    {
        $preview = [
            'header' => '',
            'body' => '',
            'footer' => '',
            'buttons' => []
        ];

        // Process header
        if (!empty($template_data['header_content'])) {
            $preview['header'] = $this->replace_template_placeholders(
                $template_data['header_content'],
                $sample_params
            );
        }

        // Process body
        $preview['body'] = $this->replace_template_placeholders(
            $template_data['body_text'],
            $sample_params
        );

        // Process footer
        if (!empty($template_data['footer_text'])) {
            $preview['footer'] = $this->replace_template_placeholders(
                $template_data['footer_text'],
                $sample_params
            );
        }

        // Process buttons
        $buttons = is_string($template_data['buttons']) ?
            json_decode($template_data['buttons'], true) :
            $template_data['buttons'];

        if (!empty($buttons)) {
            foreach ($buttons as $button) {
                $preview_button = [
                    'type' => $button['type'],
                    'text' => $this->replace_template_placeholders(
                        $button['text'],
                        $sample_params
                    )
                ];

                if ($button['type'] === 'url' && !empty($button['url'])) {
                    $preview_button['url'] = $this->replace_template_placeholders(
                        $button['url'],
                        $sample_params
                    );
                }

                $preview['buttons'][] = $preview_button;
            }
        }

        return $preview;
    }

    /**
     * Get template categories
     *
     * @return array Available categories
     */
    public function get_template_categories()
    {
        return [
            'marketing' => __('Marketing', 'chatshop'),
            'utility' => __('Utility', 'chatshop'),
            'authentication' => __('Authentication', 'chatshop'),
            'transactional' => __('Transactional', 'chatshop')
        ];
    }

    /**
     * Get supported languages
     *
     * @return array Supported languages
     */
    public function get_supported_languages()
    {
        return [
            'en' => __('English', 'chatshop'),
            'es' => __('Spanish', 'chatshop'),
            'fr' => __('French', 'chatshop'),
            'pt' => __('Portuguese', 'chatshop'),
            'de' => __('German', 'chatshop'),
            'it' => __('Italian', 'chatshop'),
            'ar' => __('Arabic', 'chatshop'),
            'hi' => __('Hindi', 'chatshop'),
            'zh' => __('Chinese', 'chatshop')
        ];
    }

    /**
     * Update template status
     *
     * @param int    $template_id Template ID
     * @param string $status New status
     * @param string $whatsapp_id WhatsApp template ID
     */
    private function update_template_status($template_id, $status, $whatsapp_id = '')
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'chatshop_message_templates';

        $update_data = [
            'status' => $status,
            'updated_at' => current_time('mysql')
        ];

        if (!empty($whatsapp_id)) {
            $update_data['whatsapp_template_id'] = $whatsapp_id;
        }

        $wpdb->update(
            $table_name,
            $update_data,
            ['id' => $template_id],
            array_fill(0, count($update_data), '%s'),
            ['%d']
        );
    }

    /**
     * Prepare template for WhatsApp API
     *
     * @param array $template Template data
     * @return array API-formatted template data
     */
    private function prepare_template_for_api($template)
    {
        $components = [];

        // Header component
        if (!empty($template['header_content'])) {
            $header_component = [
                'type' => 'HEADER',
                'format' => strtoupper($template['header_type'])
            ];

            if ($template['header_type'] === 'text') {
                $header_component['text'] = $template['header_content'];
            }

            $components[] = $header_component;
        }

        // Body component
        if (!empty($template['body_text'])) {
            $components[] = [
                'type' => 'BODY',
                'text' => $template['body_text']
            ];
        }

        // Footer component
        if (!empty($template['footer_text'])) {
            $components[] = [
                'type' => 'FOOTER',
                'text' => $template['footer_text']
            ];
        }

        // Buttons component
        $buttons = json_decode($template['buttons'], true);
        if (!empty($buttons)) {
            $button_components = [];

            foreach ($buttons as $button) {
                $button_component = [
                    'type' => strtoupper($button['type']),
                    'text' => $button['text']
                ];

                if ($button['type'] === 'url') {
                    $button_component['url'] = $button['url'];
                }

                $button_components[] = $button_component;
            }

            if (!empty($button_components)) {
                $components[] = [
                    'type' => 'BUTTONS',
                    'buttons' => $button_components
                ];
            }
        }

        return [
            'name' => $template['name'],
            'category' => strtoupper($template['category']),
            'language' => $template['language'],
            'components' => $components
        ];
    }

    /**
     * Parse API template to local format
     *
     * @param array $api_template API template data
     * @return array Local template data
     */
    private function parse_api_template($api_template)
    {
        $template_data = [
            'name' => $api_template['name'],
            'category' => strtolower($api_template['category']),
            'language' => $api_template['language'],
            'header_type' => '',
            'header_content' => '',
            'body_text' => '',
            'footer_text' => '',
            'buttons' => json_encode([]),
            'created_at' => current_time('mysql')
        ];

        // Parse components
        if (!empty($api_template['components'])) {
            foreach ($api_template['components'] as $component) {
                switch ($component['type']) {
                    case 'HEADER':
                        $template_data['header_type'] = strtolower($component['format']);
                        $template_data['header_content'] = $component['text'] ?? '';
                        break;
                    case 'BODY':
                        $template_data['body_text'] = $component['text'] ?? '';
                        break;
                    case 'FOOTER':
                        $template_data['footer_text'] = $component['text'] ?? '';
                        break;
                    case 'BUTTONS':
                        $buttons = [];
                        foreach ($component['buttons'] as $button) {
                            $buttons[] = [
                                'type' => strtolower($button['type']),
                                'text' => $button['text'],
                                'url' => $button['url'] ?? ''
                            ];
                        }
                        $template_data['buttons'] = json_encode($buttons);
                        break;
                }
            }
        }

        return $template_data;
    }

    /**
     * Replace template placeholders with values
     *
     * @param string $text Text with placeholders
     * @param array  $params Parameters
     * @return string Text with replaced placeholders
     */
    private function replace_template_placeholders($text, $params)
    {
        // Replace numbered placeholders {{1}}, {{2}}, etc.
        for ($i = 1; $i <= 10; $i++) {
            $placeholder = '{{' . $i . '}}';
            $value = $params[$i - 1] ?? '[Parameter ' . $i . ']';
            $text = str_replace($placeholder, $value, $text);
        }

        return $text;
    }

    /**
     * Get template column formats for database operations
     *
     * @return array Column formats
     */
    private function get_template_column_formats()
    {
        return ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'];
    }

    /**
     * Validate template data
     *
     * @param array $data Template data
     * @return true|WP_Error True if valid, error if not
     */
    private function validate_template_data($data)
    {
        if (empty($data['name'])) {
            return new \WP_Error('missing_name', __('Template name is required', 'chatshop'));
        }

        if (empty($data['category'])) {
            return new \WP_Error('missing_category', __('Template category is required', 'chatshop'));
        }

        if (empty($data['body_text'])) {
            return new \WP_Error('missing_body', __('Template body text is required', 'chatshop'));
        }

        // Validate category
        $valid_categories = array_keys($this->get_template_categories());
        if (!in_array($data['category'], $valid_categories)) {
            return new \WP_Error('invalid_category', __('Invalid template category', 'chatshop'));
        }

        return true;
    }

    /**
     * Sanitize template data
     *
     * @param array $data Template data
     * @return array Sanitized data
     */
    private function sanitize_template_data($data)
    {
        return [
            'name' => sanitize_text_field($data['name']),
            'category' => sanitize_text_field($data['category']),
            'language' => sanitize_text_field($data['language'] ?? 'en'),
            'header_type' => sanitize_text_field($data['header_type'] ?? ''),
            'header_content' => sanitize_textarea_field($data['header_content'] ?? ''),
            'body_text' => sanitize_textarea_field($data['body_text']),
            'footer_text' => sanitize_textarea_field($data['footer_text'] ?? ''),
            'buttons' => $data['buttons'] ?? []
        ];
    }

    /**
     * AJAX handler for creating template
     */
    public function ajax_create_template()
    {
        check_ajax_referer('chatshop_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'chatshop'));
        }

        $template_data = json_decode(file_get_contents('php://input'), true);

        $result = $this->create_template($template_data);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(['template_id' => $result]);
    }

    /**
     * AJAX handler for syncing templates
     */
    public function ajax_sync_templates()
    {
        check_ajax_referer('chatshop_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'chatshop'));
        }

        $result = $this->sync_templates_with_whatsapp();

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
    }

    /**
     * AJAX handler for template preview
     */
    public function ajax_preview_template()
    {
        check_ajax_referer('chatshop_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'chatshop'));
        }

        $template_data = json_decode(file_get_contents('php://input'), true);
        $sample_params = $template_data['sample_params'] ?? [];

        $preview = $this->preview_template($template_data, $sample_params);

        wp_send_json_success($preview);
    }
}
