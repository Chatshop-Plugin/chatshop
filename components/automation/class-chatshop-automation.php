<?php

/**
 * ChatShop Automation
 *
 * Handles automated WhatsApp messaging rules and triggers
 *
 * @package ChatShop
 * @subpackage Automation
 * @since 1.0.0
 */

namespace ChatShop\Automation;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Automation class
 *
 * Manages automated messaging rules, triggers, and execution
 */
class ChatShop_Automation
{

    /**
     * Message sender instance
     *
     * @var \ChatShop\WhatsApp\ChatShop_Message_Sender
     */
    private $message_sender;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->message_sender = new \ChatShop\WhatsApp\ChatShop_Message_Sender();

        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks()
    {
        // WooCommerce hooks
        add_action('woocommerce_order_status_processing', [$this, 'trigger_order_confirmed']);
        add_action('woocommerce_order_status_completed', [$this, 'trigger_order_completed']);
        add_action('woocommerce_order_status_cancelled', [$this, 'trigger_order_cancelled']);
        add_action('woocommerce_order_status_refunded', [$this, 'trigger_order_refunded']);

        // Cart abandonment
        add_action('woocommerce_add_to_cart', [$this, 'track_cart_activity']);
        add_action('chatshop_check_abandoned_carts', [$this, 'process_abandoned_carts']);

        // Customer lifecycle
        add_action('user_register', [$this, 'trigger_welcome_new_customer']);
        add_action('chatshop_customer_birthday', [$this, 'trigger_birthday_message']);
        add_action('chatshop_win_back_customers', [$this, 'process_win_back_campaigns']);

        // Product events
        add_action('woocommerce_product_set_stock_status', [$this, 'trigger_stock_alert'], 10, 3);
        add_action('woocommerce_reduce_order_stock', [$this, 'trigger_low_stock_alert']);

        // Payment events
        add_action('chatshop_payment_completed', [$this, 'trigger_payment_confirmation']);
        add_action('chatshop_payment_failed', [$this, 'trigger_payment_failed']);

        // Schedule recurring automations
        if (!wp_next_scheduled('chatshop_check_abandoned_carts')) {
            wp_schedule_event(time(), 'hourly', 'chatshop_check_abandoned_carts');
        }

        if (!wp_next_scheduled('chatshop_win_back_customers')) {
            wp_schedule_event(time(), 'daily', 'chatshop_win_back_customers');
        }

        // Admin AJAX
        add_action('wp_ajax_chatshop_create_automation_rule', [$this, 'ajax_create_automation_rule']);
        add_action('wp_ajax_chatshop_toggle_automation_rule', [$this, 'ajax_toggle_automation_rule']);
    }

    /**
     * Create automation rule
     *
     * @param array $rule_data Rule data
     * @return int|WP_Error Rule ID or error
     */
    public function create_automation_rule($rule_data)
    {
        $validation = $this->validate_rule_data($rule_data);
        if (is_wp_error($validation)) {
            return $validation;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'chatshop_automation_rules';

        $sanitized_data = $this->sanitize_rule_data($rule_data);

        $result = $wpdb->insert(
            $table_name,
            [
                'name' => $sanitized_data['name'],
                'trigger_type' => $sanitized_data['trigger_type'],
                'trigger_conditions' => json_encode($sanitized_data['trigger_conditions']),
                'action_type' => $sanitized_data['action_type'],
                'action_data' => json_encode($sanitized_data['action_data']),
                'delay_minutes' => $sanitized_data['delay_minutes'],
                'is_active' => 1,
                'created_by' => get_current_user_id(),
                'created_at' => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s']
        );

        if ($result === false) {
            return new \WP_Error('db_error', __('Failed to create automation rule', 'chatshop'));
        }

        $rule_id = $wpdb->insert_id;

        do_action('chatshop_automation_rule_created', $rule_id, $sanitized_data);

        return $rule_id;
    }

    /**
     * Get automation rules
     *
     * @param string $trigger_type Optional trigger type filter
     * @return array Automation rules
     */
    public function get_automation_rules($trigger_type = '')
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'chatshop_automation_rules';

        $where_clause = 'WHERE is_active = 1';
        $values = [];

        if (!empty($trigger_type)) {
            $where_clause .= ' AND trigger_type = %s';
            $values[] = $trigger_type;
        }

        $query = "SELECT * FROM {$table_name} {$where_clause} ORDER BY created_at DESC";

        if (!empty($values)) {
            $query = $wpdb->prepare($query, $values);
        }

        return $wpdb->get_results($query, ARRAY_A);
    }

    /**
     * Execute automation rule
     *
     * @param int   $rule_id Rule ID
     * @param array $context Trigger context data
     * @return bool|WP_Error True on success, error on failure
     */
    public function execute_automation_rule($rule_id, $context = [])
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'chatshop_automation_rules';

        $rule = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %d AND is_active = 1",
            $rule_id
        ), ARRAY_A);

        if (!$rule) {
            return new \WP_Error('rule_not_found', __('Automation rule not found', 'chatshop'));
        }

        // Check if conditions are met
        $trigger_conditions = json_decode($rule['trigger_conditions'], true);
        if (!$this->check_trigger_conditions($trigger_conditions, $context)) {
            return true; // Conditions not met, but not an error
        }

        // Execute action based on delay
        if ($rule['delay_minutes'] > 0) {
            $this->schedule_delayed_action($rule_id, $context, $rule['delay_minutes']);
        } else {
            $this->execute_rule_action($rule, $context);
        }

        return true;
    }

    /**
     * Execute rule action immediately
     *
     * @param array $rule Rule data
     * @param array $context Context data
     */
    private function execute_rule_action($rule, $context)
    {
        $action_data = json_decode($rule['action_data'], true);

        switch ($rule['action_type']) {
            case 'send_message':
                $this->execute_send_message_action($action_data, $context);
                break;
            case 'add_to_list':
                $this->execute_add_to_list_action($action_data, $context);
                break;
            case 'tag_contact':
                $this->execute_tag_contact_action($action_data, $context);
                break;
            case 'create_order_reminder':
                $this->execute_order_reminder_action($action_data, $context);
                break;
        }

        // Log execution
        $this->log_automation_execution($rule['id'], $context);
    }

    /**
     * Trigger order confirmation automation
     *
     * @param int $order_id Order ID
     */
    public function trigger_order_confirmed($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $context = [
            'order_id' => $order_id,
            'customer_phone' => $this->get_customer_phone($order),
            'customer_email' => $order->get_billing_email(),
            'customer_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'order_total' => $order->get_total(),
            'order_items' => $this->get_order_items_summary($order)
        ];

        $this->trigger_automation('order_confirmed', $context);
    }

    /**
     * Trigger order completion automation
     *
     * @param int $order_id Order ID
     */
    public function trigger_order_completed($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $context = [
            'order_id' => $order_id,
            'customer_phone' => $this->get_customer_phone($order),
            'customer_email' => $order->get_billing_email(),
            'customer_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'order_total' => $order->get_total()
        ];

        $this->trigger_automation('order_completed', $context);
    }

    /**
     * Trigger order cancellation automation
     *
     * @param int $order_id Order ID
     */
    public function trigger_order_cancelled($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $context = [
            'order_id' => $order_id,
            'customer_phone' => $this->get_customer_phone($order),
            'customer_email' => $order->get_billing_email(),
            'customer_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name()
        ];

        $this->trigger_automation('order_cancelled', $context);
    }

    /**
     * Track cart activity for abandonment
     *
     * @param string $cart_item_key Cart item key
     */
    public function track_cart_activity($cart_item_key)
    {
        if (!is_user_logged_in()) {
            return;
        }

        $user_id = get_current_user_id();
        $cart_data = [
            'user_id' => $user_id,
            'cart_contents' => WC()->cart->get_cart_contents(),
            'cart_total' => WC()->cart->get_total('raw'),
            'last_activity' => current_time('mysql')
        ];

        update_user_meta($user_id, 'chatshop_cart_data', $cart_data);
    }

    /**
     * Process abandoned carts
     */
    public function process_abandoned_carts()
    {
        $abandonment_threshold = get_option('chatshop_cart_abandonment_hours', 24);
        $threshold_time = date('Y-m-d H:i:s', strtotime("-{$abandonment_threshold} hours"));

        global $wpdb;

        // Find users with abandoned carts
        $abandoned_carts = $wpdb->get_results($wpdb->prepare("
            SELECT u.ID, u.user_email, um.meta_value as cart_data
            FROM {$wpdb->users} u
            INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
            WHERE um.meta_key = 'chatshop_cart_data'
            AND JSON_EXTRACT(um.meta_value, '$.last_activity') < %s
            AND NOT EXISTS (
                SELECT 1 FROM {$wpdb->posts} p 
                WHERE p.post_author = u.ID 
                AND p.post_type = 'shop_order'
                AND p.post_date > JSON_EXTRACT(um.meta_value, '$.last_activity')
            )
        ", $threshold_time));

        foreach ($abandoned_carts as $cart) {
            $cart_data = json_decode($cart->cart_data, true);

            $context = [
                'user_id' => $cart->ID,
                'customer_email' => $cart->user_email,
                'cart_total' => $cart_data['cart_total'],
                'cart_items' => $cart_data['cart_contents']
            ];

            $this->trigger_automation('cart_abandoned', $context);
        }
    }

    /**
     * Trigger welcome message for new customers
     *
     * @param int $user_id User ID
     */
    public function trigger_welcome_new_customer($user_id)
    {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return;
        }

        $context = [
            'user_id' => $user_id,
            'customer_email' => $user->user_email,
            'customer_name' => $user->display_name
        ];

        $this->trigger_automation('customer_registered', $context);
    }

    /**
     * Trigger stock alert automation
     *
     * @param int    $product_id Product ID
     * @param string $stock_status Stock status
     * @param object $product Product object
     */
    public function trigger_stock_alert($product_id, $stock_status, $product)
    {
        if ($stock_status === 'instock') {
            $context = [
                'product_id' => $product_id,
                'product_name' => $product->get_name(),
                'product_url' => get_permalink($product_id)
            ];

            $this->trigger_automation('product_back_in_stock', $context);
        }
    }

    /**
     * Trigger automation for specific type
     *
     * @param string $trigger_type Trigger type
     * @param array  $context Context data
     */
    private function trigger_automation($trigger_type, $context)
    {
        $rules = $this->get_automation_rules($trigger_type);

        foreach ($rules as $rule) {
            $this->execute_automation_rule($rule['id'], $context);
        }
    }

    /**
     * Check if trigger conditions are met
     *
     * @param array $conditions Trigger conditions
     * @param array $context Context data
     * @return bool True if conditions are met
     */
    private function check_trigger_conditions($conditions, $context)
    {
        if (empty($conditions)) {
            return true;
        }

        foreach ($conditions as $condition) {
            if (!$this->evaluate_condition($condition, $context)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Evaluate individual condition
     *
     * @param array $condition Condition data
     * @param array $context Context data
     * @return bool True if condition is met
     */
    private function evaluate_condition($condition, $context)
    {
        $field = $condition['field'];
        $operator = $condition['operator'];
        $value = $condition['value'];
        $context_value = $context[$field] ?? null;

        switch ($operator) {
            case 'equals':
                return $context_value == $value;
            case 'not_equals':
                return $context_value != $value;
            case 'greater_than':
                return floatval($context_value) > floatval($value);
            case 'less_than':
                return floatval($context_value) < floatval($value);
            case 'contains':
                return strpos(strtolower($context_value), strtolower($value)) !== false;
            case 'starts_with':
                return strpos(strtolower($context_value), strtolower($value)) === 0;
            default:
                return true;
        }
    }

    /**
     * Schedule delayed action
     *
     * @param int   $rule_id Rule ID
     * @param array $context Context data
     * @param int   $delay_minutes Delay in minutes
     */
    private function schedule_delayed_action($rule_id, $context, $delay_minutes)
    {
        $timestamp = time() + ($delay_minutes * 60);

        wp_schedule_single_event(
            $timestamp,
            'chatshop_execute_delayed_automation',
            [$rule_id, $context]
        );
    }

    /**
     * Execute send message action
     *
     * @param array $action_data Action data
     * @param array $context Context data
     */
    private function execute_send_message_action($action_data, $context)
    {
        $phone_number = $this->extract_phone_from_context($context);
        if (empty($phone_number)) {
            return;
        }

        $message_type = $action_data['message_type'];
        $message_content = $this->personalize_message_content($action_data['content'], $context);

        switch ($message_type) {
            case 'text':
                $this->message_sender->send_text_message($phone_number, $message_content);
                break;
            case 'template':
                $template_name = $action_data['template_name'];
                $parameters = $this->prepare_template_parameters($action_data['parameters'], $context);
                $this->message_sender->send_template_message($phone_number, $template_name, $parameters);
                break;
        }
    }

    /**
     * Execute add to list action
     *
     * @param array $action_data Action data
     * @param array $context Context data
     */
    private function execute_add_to_list_action($action_data, $context)
    {
        $list_id = $action_data['list_id'];
        $contact_id = $this->get_contact_id_from_context($context);

        if ($contact_id && $list_id) {
            // Add contact to list implementation
            do_action('chatshop_add_contact_to_list', $contact_id, $list_id);
        }
    }

    /**
     * Execute tag contact action
     *
     * @param array $action_data Action data
     * @param array $context Context data
     */
    private function execute_tag_contact_action($action_data, $context)
    {
        $tags = $action_data['tags'];
        $contact_id = $this->get_contact_id_from_context($context);

        if ($contact_id && !empty($tags)) {
            // Tag contact implementation
            do_action('chatshop_tag_contact', $contact_id, $tags);
        }
    }

    /**
     * Get customer phone from order
     *
     * @param WC_Order $order WooCommerce order
     * @return string Phone number
     */
    private function get_customer_phone($order)
    {
        $phone = $order->get_billing_phone();

        // Try to get WhatsApp phone from custom field if available
        $whatsapp_phone = $order->get_meta('_billing_whatsapp_phone');
        if (!empty($whatsapp_phone)) {
            return $whatsapp_phone;
        }

        return $phone;
    }

    /**
     * Get order items summary
     *
     * @param WC_Order $order WooCommerce order
     * @return array Order items summary
     */
    private function get_order_items_summary($order)
    {
        $items = [];

        foreach ($order->get_items() as $item) {
            $items[] = [
                'name' => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'total' => $item->get_total()
            ];
        }

        return $items;
    }

    /**
     * Extract phone number from context
     *
     * @param array $context Context data
     * @return string Phone number
     */
    private function extract_phone_from_context($context)
    {
        if (!empty($context['customer_phone'])) {
            return $context['customer_phone'];
        }

        if (!empty($context['customer_email'])) {
            // Try to find phone by email
            global $wpdb;
            $phone = $wpdb->get_var($wpdb->prepare(
                "SELECT phone_number FROM {$wpdb->prefix}chatshop_contacts WHERE email = %s",
                $context['customer_email']
            ));

            if ($phone) {
                return $phone;
            }
        }

        return '';
    }

    /**
     * Get contact ID from context
     *
     * @param array $context Context data
     * @return int|null Contact ID
     */
    private function get_contact_id_from_context($context)
    {
        if (!empty($context['customer_email'])) {
            global $wpdb;
            return $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}chatshop_contacts WHERE email = %s",
                $context['customer_email']
            ));
        }

        return null;
    }

    /**
     * Personalize message content
     *
     * @param string $content Message content with placeholders
     * @param array  $context Context data
     * @return string Personalized content
     */
    private function personalize_message_content($content, $context)
    {
        $placeholders = [
            '{customer_name}' => $context['customer_name'] ?? '',
            '{order_id}' => $context['order_id'] ?? '',
            '{order_total}' => $context['order_total'] ?? '',
            '{product_name}' => $context['product_name'] ?? '',
            '{cart_total}' => $context['cart_total'] ?? ''
        ];

        return str_replace(array_keys($placeholders), array_values($placeholders), $content);
    }

    /**
     * Prepare template parameters
     *
     * @param array $parameters Template parameters with placeholders
     * @param array $context Context data
     * @return array Processed parameters
     */
    private function prepare_template_parameters($parameters, $context)
    {
        $processed = [];

        foreach ($parameters as $key => $value) {
            if (is_array($value)) {
                $processed[$key] = [];
                foreach ($value as $param) {
                    $processed[$key][] = $this->personalize_message_content($param, $context);
                }
            } else {
                $processed[$key] = $this->personalize_message_content($value, $context);
            }
        }

        return $processed;
    }

    /**
     * Log automation execution
     *
     * @param int   $rule_id Rule ID
     * @param array $context Context data
     */
    private function log_automation_execution($rule_id, $context)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'chatshop_automation_logs';

        $wpdb->insert(
            $table_name,
            [
                'rule_id' => $rule_id,
                'context_data' => json_encode($context),
                'executed_at' => current_time('mysql')
            ],
            ['%d', '%s', '%s']
        );
    }

    /**
     * Validate rule data
     *
     * @param array $data Rule data
     * @return true|WP_Error True if valid, error if not
     */
    private function validate_rule_data($data)
    {
        if (empty($data['name'])) {
            return new \WP_Error('missing_name', __('Rule name is required', 'chatshop'));
        }

        if (empty($data['trigger_type'])) {
            return new \WP_Error('missing_trigger', __('Trigger type is required', 'chatshop'));
        }

        if (empty($data['action_type'])) {
            return new \WP_Error('missing_action', __('Action type is required', 'chatshop'));
        }

        return true;
    }

    /**
     * Sanitize rule data
     *
     * @param array $data Rule data
     * @return array Sanitized data
     */
    private function sanitize_rule_data($data)
    {
        return [
            'name' => sanitize_text_field($data['name']),
            'trigger_type' => sanitize_text_field($data['trigger_type']),
            'trigger_conditions' => $data['trigger_conditions'] ?? [],
            'action_type' => sanitize_text_field($data['action_type']),
            'action_data' => $data['action_data'] ?? [],
            'delay_minutes' => intval($data['delay_minutes'] ?? 0)
        ];
    }

    /**
     * AJAX handler for creating automation rule
     */
    public function ajax_create_automation_rule()
    {
        check_ajax_referer('chatshop_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'chatshop'));
        }

        $rule_data = json_decode(file_get_contents('php://input'), true);

        $result = $this->create_automation_rule($rule_data);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(['rule_id' => $result]);
    }

    /**
     * AJAX handler for toggling automation rule
     */
    public function ajax_toggle_automation_rule()
    {
        check_ajax_referer('chatshop_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'chatshop'));
        }

        $rule_id = intval($_POST['rule_id'] ?? 0);
        $is_active = intval($_POST['is_active'] ?? 0);

        global $wpdb;
        $table_name = $wpdb->prefix . 'chatshop_automation_rules';

        $result = $wpdb->update(
            $table_name,
            ['is_active' => $is_active],
            ['id' => $rule_id],
            ['%d'],
            ['%d']
        );

        if ($result === false) {
            wp_send_json_error(__('Failed to update rule status', 'chatshop'));
        }

        wp_send_json_success();
    }
}
