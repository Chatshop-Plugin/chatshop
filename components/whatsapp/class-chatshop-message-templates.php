<?php

/**
 * WhatsApp Message Templates Class
 *
 * Handles WhatsApp message templates for the ChatShop plugin.
 * Manages pre-approved templates for notifications and campaigns.
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
 * ChatShop WhatsApp Message Templates
 *
 * Manages WhatsApp message templates including creation, validation,
 * and dynamic content replacement.
 *
 * @since 1.0.0
 */
class ChatShop_Message_Templates
{
    /**
     * Default templates for common use cases
     *
     * @var array
     * @since 1.0.0
     */
    private $default_templates = array(
        'order_confirmation' => array(
            'name' => 'order_confirmation',
            'category' => 'TRANSACTIONAL',
            'language' => 'en_US',
            'body' => 'Hi {{1}}, your order #{{2}} for {{3}} has been confirmed. Total: {{4}}. Expected delivery: {{5}}.',
            'variables' => array('customer_name', 'order_number', 'product_name', 'total', 'delivery_date'),
        ),
        'order_shipped' => array(
            'name' => 'order_shipped',
            'category' => 'TRANSACTIONAL',
            'language' => 'en_US',
            'body' => 'Great news {{1}}! Your order #{{2}} has been shipped. Track your package: {{3}}',
            'variables' => array('customer_name', 'order_number', 'tracking_url'),
        ),
        'cart_abandonment' => array(
            'name' => 'cart_abandonment',
            'category' => 'MARKETING',
            'language' => 'en_US',
            'body' => 'Hi {{1}}, you left {{2}} items in your cart. Complete your purchase now and save {{3}}! {{4}}',
            'variables' => array('customer_name', 'item_count', 'discount', 'cart_url'),
        ),
        'payment_reminder' => array(
            'name' => 'payment_reminder',
            'category' => 'TRANSACTIONAL',
            'language' => 'en_US',
            'body' => 'Hi {{1}}, your payment for order #{{2}} ({{3}}) is pending. Complete payment: {{4}}',
            'variables' => array('customer_name', 'order_number', 'amount', 'payment_url'),
        ),
        'new_product' => array(
            'name' => 'new_product',
            'category' => 'MARKETING',
            'language' => 'en_US',
            'body' => '🎉 New arrival! {{1}} is now available for {{2}}. Shop now: {{3}}',
            'variables' => array('product_name', 'price', 'product_url'),
        ),
        'price_drop' => array(
            'name' => 'price_drop',
            'category' => 'MARKETING',
            'language' => 'en_US',
            'body' => '💰 Price Drop Alert! {{1}} is now {{2}} (was {{3}}). Limited time offer: {{4}}',
            'variables' => array('product_name', 'new_price', 'old_price', 'product_url'),
        ),
    );

    /**
     * Premium features status
     *
     * @var array
     * @since 1.0.0
     */
    private $premium_features;

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        $this->init_premium_features();
        $this->init_default_templates();
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
            'custom_templates' => $license_status !== 'free',
            'unlimited_templates' => $license_status !== 'free',
            'template_variables' => isset($premium_options['whatsapp_automation']) ?
                (bool) $premium_options['whatsapp_automation'] : false,
        );
    }

    /**
     * Initialize default templates in database
     *
     * @since 1.0.0
     */
    private function init_default_templates()
    {
        foreach ($this->default_templates as $template_id => $template_data) {
            $this->save_template($template_data, true);
        }
    }

    /**
     * Get template by name
     *
     * @since 1.0.0
     * @param string $template_name Template name
     * @return array|false Template data or false if not found
     */
    public function get_template($template_name)
    {
        global $wpdb;

        $template = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}chatshop_message_templates WHERE name = %s AND status = 'active'",
                $template_name
            ),
            ARRAY_A
        );

        if (!$template) {
            return false;
        }

        $template['variables'] = maybe_unserialize($template['variables']);
        return $template;
    }

    /**
     * Create template message with variables
     *
     * @since 1.0.0
     * @param string $template_name Template name
     * @param array  $variables Variable values
     * @return array Result with message content
     */
    public function create_message($template_name, $variables = array())
    {
        $template = $this->get_template($template_name);
        if (!$template) {
            return array(
                'success' => false,
                'message' => __('Template not found', 'chatshop'),
            );
        }

        $message = $this->replace_variables($template['body'], $variables);

        return array(
            'success' => true,
            'message' => $message,
            'template' => $template,
        );
    }

    /**
     * Replace template variables with actual values
     *
     * @since 1.0.0
     * @param string $template Template body
     * @param array  $variables Variable values
     * @return string Message with replaced variables
     */
    private function replace_variables($template, $variables)
    {
        $message = $template;

        // Replace numbered placeholders {{1}}, {{2}}, etc.
        for ($i = 1; $i <= count($variables); $i++) {
            $placeholder = '{{' . $i . '}}';
            $value = isset($variables[$i - 1]) ? $variables[$i - 1] : '';
            $message = str_replace($placeholder, $value, $message);
        }

        // Replace named placeholders
        foreach ($variables as $key => $value) {
            if (is_string($key)) {
                $placeholder = '{{' . $key . '}}';
                $message = str_replace($placeholder, $value, $message);
            }
        }

        return $message;
    }

    /**
     * Save template to database
     *
     * @since 1.0.0
     * @param array $template_data Template data
     * @param bool  $is_default Whether this is a default template
     * @return int|false Template ID or false on failure
     */
    public function save_template($template_data, $is_default = false)
    {
        global $wpdb;

        // Check if template already exists
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}chatshop_message_templates WHERE name = %s",
                $template_data['name']
            )
        );

        $data = array(
            'name' => sanitize_key($template_data['name']),
            'category' => sanitize_text_field($template_data['category']),
            'language' => sanitize_text_field($template_data['language']),
            'body' => sanitize_textarea_field($template_data['body']),
            'variables' => maybe_serialize($template_data['variables'] ?? array()),
            'is_default' => $is_default ? 1 : 0,
            'status' => 'active',
            'updated_at' => current_time('mysql'),
        );

        if ($existing) {
            // Update existing template
            $result = $wpdb->update(
                $wpdb->prefix . 'chatshop_message_templates',
                $data,
                array('id' => $existing),
                array('%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s'),
                array('%d')
            );
            return $result ? $existing : false;
        } else {
            // Insert new template
            $data['created_at'] = current_time('mysql');
            $result = $wpdb->insert(
                $wpdb->prefix . 'chatshop_message_templates',
                $data,
                array('%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s')
            );
            return $result ? $wpdb->insert_id : false;
        }
    }

    /**
     * Get all templates
     *
     * @since 1.0.0
     * @param array $args Query arguments
     * @return array Templates
     */
    public function get_templates($args = array())
    {
        global $wpdb;

        $defaults = array(
            'status' => 'active',
            'category' => '',
            'limit' => 50,
            'offset' => 0,
        );

        $args = wp_parse_args($args, $defaults);

        $where_conditions = array("status = 'active'");

        if (!empty($args['category'])) {
            $where_conditions[] = $wpdb->prepare("category = %s", $args['category']);
        }

        $where_clause = implode(' AND ', $where_conditions);

        $query = "SELECT * FROM {$wpdb->prefix}chatshop_message_templates 
                 WHERE {$where_clause} 
                 ORDER BY is_default DESC, created_at DESC 
                 LIMIT %d OFFSET %d";

        $templates = $wpdb->get_results(
            $wpdb->prepare($query, $args['limit'], $args['offset']),
            ARRAY_A
        );

        // Unserialize variables for each template
        foreach ($templates as &$template) {
            $template['variables'] = maybe_unserialize($template['variables']);
        }

        return $templates;
    }

    /**
     * Delete template
     *
     * @since 1.0.0
     * @param string $template_name Template name
     * @return bool Success status
     */
    public function delete_template($template_name)
    {
        global $wpdb;

        // Don't allow deletion of default templates
        $is_default = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT is_default FROM {$wpdb->prefix}chatshop_message_templates WHERE name = %s",
                $template_name
            )
        );

        if ($is_default) {
            return false;
        }

        $result = $wpdb->update(
            $wpdb->prefix . 'chatshop_message_templates',
            array('status' => 'deleted'),
            array('name' => $template_name),
            array('%s'),
            array('%s')
        );

        return $result !== false;
    }

    /**
     * Build WhatsApp Business API template structure
     *
     * @since 1.0.0
     * @param string $template_name Template name
     * @param array  $variables Template variables
     * @return array WhatsApp template structure
     */
    public function build_whatsapp_template($template_name, $variables = array())
    {
        $template = $this->get_template($template_name);
        if (!$template) {
            return array(
                'success' => false,
                'message' => __('Template not found', 'chatshop'),
            );
        }

        $whatsapp_template = array(
            'name' => $template['name'],
            'language' => array(
                'code' => $template['language'],
            ),
            'components' => array(),
        );

        // Add body component if template has variables
        if (!empty($variables)) {
            $parameters = array();

            foreach ($variables as $variable) {
                $parameters[] = array(
                    'type' => 'text',
                    'text' => $variable,
                );
            }

            $whatsapp_template['components'][] = array(
                'type' => 'body',
                'parameters' => $parameters,
            );
        }

        return array(
            'success' => true,
            'template' => $whatsapp_template,
        );
    }

    /**
     * Validate template before saving
     *
     * @since 1.0.0
     * @param array $template_data Template data
     * @return array Validation result
     */
    public function validate_template($template_data)
    {
        $errors = array();

        // Required fields
        $required_fields = array('name', 'category', 'language', 'body');
        foreach ($required_fields as $field) {
            if (empty($template_data[$field])) {
                $errors[] = sprintf(__('%s is required', 'chatshop'), ucfirst($field));
            }
        }

        // Template name validation
        if (!empty($template_data['name'])) {
            if (!preg_match('/^[a-z0-9_]+$/', $template_data['name'])) {
                $errors[] = __('Template name can only contain lowercase letters, numbers, and underscores', 'chatshop');
            }
        }

        // Category validation
        $valid_categories = array('TRANSACTIONAL', 'MARKETING', 'OTP', 'AUTHENTICATION');
        if (!empty($template_data['category']) && !in_array($template_data['category'], $valid_categories, true)) {
            $errors[] = __('Invalid template category', 'chatshop');
        }

        // Check premium limits for custom templates
        if (!$this->premium_features['custom_templates'] && !isset($template_data['is_default'])) {
            $custom_count = $this->get_custom_template_count();
            if ($custom_count >= 3) { // Free limit: 3 custom templates
                $errors[] = __('Free plan limited to 3 custom templates. Upgrade for unlimited templates.', 'chatshop');
            }
        }

        return array(
            'valid' => empty($errors),
            'errors' => $errors,
        );
    }

    /**
     * Get custom template count
     *
     * @since 1.0.0
     * @return int Number of custom templates
     */
    private function get_custom_template_count()
    {
        global $wpdb;

        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}chatshop_message_templates 
            WHERE is_default = 0 AND status = 'active'"
        );
    }

    /**
     * Get template categories
     *
     * @since 1.0.0
     * @return array Available categories
     */
    public function get_template_categories()
    {
        return array(
            'TRANSACTIONAL' => __('Transactional', 'chatshop'),
            'MARKETING' => __('Marketing', 'chatshop'),
            'OTP' => __('OTP', 'chatshop'),
            'AUTHENTICATION' => __('Authentication', 'chatshop'),
        );
    }

    /**
     * Get supported languages
     *
     * @since 1.0.0
     * @return array Supported languages
     */
    public function get_supported_languages()
    {
        return array(
            'en_US' => __('English (US)', 'chatshop'),
            'en_GB' => __('English (UK)', 'chatshop'),
            'es' => __('Spanish', 'chatshop'),
            'fr' => __('French', 'chatshop'),
            'de' => __('German', 'chatshop'),
            'pt_BR' => __('Portuguese (Brazil)', 'chatshop'),
            'hi' => __('Hindi', 'chatshop'),
            'ar' => __('Arabic', 'chatshop'),
        );
    }
}
