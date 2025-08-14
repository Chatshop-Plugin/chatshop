<?php

/**
 * ChatShop Campaign Manager
 *
 * Manages WhatsApp marketing campaigns
 *
 * @package ChatShop
 * @subpackage Marketing
 * @since 1.0.0
 */

namespace ChatShop\Marketing;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Campaign Manager class
 *
 * Handles creation, scheduling, and execution of WhatsApp marketing campaigns
 */
class ChatShop_Campaign_Manager
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

        add_action('chatshop_execute_scheduled_campaign', [$this, 'execute_scheduled_campaign']);
        add_action('wp_ajax_chatshop_create_campaign', [$this, 'ajax_create_campaign']);
        add_action('wp_ajax_chatshop_get_campaign_stats', [$this, 'ajax_get_campaign_stats']);
    }

    /**
     * Create a new campaign
     *
     * @param array $campaign_data Campaign data
     * @return int|WP_Error Campaign ID or error
     */
    public function create_campaign($campaign_data)
    {
        // Validate campaign data
        $validation = $this->validate_campaign_data($campaign_data);
        if (is_wp_error($validation)) {
            return $validation;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'chatshop_campaigns';

        $sanitized_data = $this->sanitize_campaign_data($campaign_data);

        $result = $wpdb->insert(
            $table_name,
            [
                'name' => $sanitized_data['name'],
                'description' => $sanitized_data['description'],
                'type' => $sanitized_data['type'],
                'status' => 'draft',
                'message_data' => json_encode($sanitized_data['message_data']),
                'target_audience' => json_encode($sanitized_data['target_audience']),
                'schedule_type' => $sanitized_data['schedule_type'],
                'scheduled_at' => $sanitized_data['scheduled_at'],
                'created_by' => get_current_user_id(),
                'created_at' => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s']
        );

        if ($result === false) {
            return new \WP_Error('db_error', __('Failed to create campaign', 'chatshop'));
        }

        $campaign_id = $wpdb->insert_id;

        // Schedule campaign if needed
        if ($sanitized_data['schedule_type'] === 'scheduled') {
            $this->schedule_campaign($campaign_id, $sanitized_data['scheduled_at']);
        }

        do_action('chatshop_campaign_created', $campaign_id, $sanitized_data);

        return $campaign_id;
    }

    /**
     * Update campaign
     *
     * @param int   $campaign_id Campaign ID
     * @param array $campaign_data Updated campaign data
     * @return bool|WP_Error True on success, error on failure
     */
    public function update_campaign($campaign_id, $campaign_data)
    {
        // Check if campaign exists and can be updated
        $campaign = $this->get_campaign($campaign_id);
        if (!$campaign) {
            return new \WP_Error('not_found', __('Campaign not found', 'chatshop'));
        }

        if (in_array($campaign['status'], ['sent', 'sending'])) {
            return new \WP_Error('invalid_status', __('Cannot update sent or sending campaign', 'chatshop'));
        }

        // Validate updated data
        $validation = $this->validate_campaign_data($campaign_data);
        if (is_wp_error($validation)) {
            return $validation;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'chatshop_campaigns';

        $sanitized_data = $this->sanitize_campaign_data($campaign_data);

        $result = $wpdb->update(
            $table_name,
            [
                'name' => $sanitized_data['name'],
                'description' => $sanitized_data['description'],
                'message_data' => json_encode($sanitized_data['message_data']),
                'target_audience' => json_encode($sanitized_data['target_audience']),
                'schedule_type' => $sanitized_data['schedule_type'],
                'scheduled_at' => $sanitized_data['scheduled_at'],
                'updated_at' => current_time('mysql')
            ],
            ['id' => $campaign_id],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s'],
            ['%d']
        );

        if ($result === false) {
            return new \WP_Error('db_error', __('Failed to update campaign', 'chatshop'));
        }

        // Reschedule if needed
        if ($sanitized_data['schedule_type'] === 'scheduled') {
            $this->reschedule_campaign($campaign_id, $sanitized_data['scheduled_at']);
        }

        do_action('chatshop_campaign_updated', $campaign_id, $sanitized_data);

        return true;
    }

    /**
     * Delete campaign
     *
     * @param int $campaign_id Campaign ID
     * @return bool|WP_Error True on success, error on failure
     */
    public function delete_campaign($campaign_id)
    {
        $campaign = $this->get_campaign($campaign_id);
        if (!$campaign) {
            return new \WP_Error('not_found', __('Campaign not found', 'chatshop'));
        }

        if ($campaign['status'] === 'sending') {
            return new \WP_Error('invalid_status', __('Cannot delete campaign that is currently sending', 'chatshop'));
        }

        global $wpdb;

        // Delete campaign recipients
        $wpdb->delete(
            $wpdb->prefix . 'chatshop_campaign_recipients',
            ['campaign_id' => $campaign_id],
            ['%d']
        );

        // Delete campaign
        $result = $wpdb->delete(
            $wpdb->prefix . 'chatshop_campaigns',
            ['id' => $campaign_id],
            ['%d']
        );

        if ($result === false) {
            return new \WP_Error('db_error', __('Failed to delete campaign', 'chatshop'));
        }

        // Unschedule if needed
        $this->unschedule_campaign($campaign_id);

        do_action('chatshop_campaign_deleted', $campaign_id);

        return true;
    }

    /**
     * Execute campaign immediately
     *
     * @param int $campaign_id Campaign ID
     * @return bool|WP_Error True on success, error on failure
     */
    public function execute_campaign($campaign_id)
    {
        $campaign = $this->get_campaign($campaign_id);
        if (!$campaign) {
            return new \WP_Error('not_found', __('Campaign not found', 'chatshop'));
        }

        if ($campaign['status'] !== 'draft' && $campaign['status'] !== 'scheduled') {
            return new \WP_Error('invalid_status', __('Campaign cannot be executed in current status', 'chatshop'));
        }

        // Update campaign status
        $this->update_campaign_status($campaign_id, 'sending');

        // Get recipients
        $recipients = $this->get_campaign_recipients($campaign_id);
        if (empty($recipients)) {
            $this->update_campaign_status($campaign_id, 'failed');
            return new \WP_Error('no_recipients', __('No recipients found for campaign', 'chatshop'));
        }

        // Send messages
        $message_data = json_decode($campaign['message_data'], true);
        $results = $this->message_sender->send_bulk_messages($recipients, $message_data);

        // Update campaign statistics
        $this->update_campaign_statistics($campaign_id, $results);

        // Update campaign status
        $this->update_campaign_status($campaign_id, 'sent');

        do_action('chatshop_campaign_executed', $campaign_id, $results);

        return true;
    }

    /**
     * Execute scheduled campaign (called by cron)
     *
     * @param int $campaign_id Campaign ID
     */
    public function execute_scheduled_campaign($campaign_id)
    {
        $campaign = $this->get_campaign($campaign_id);
        if (!$campaign || $campaign['status'] !== 'scheduled') {
            return;
        }

        $this->execute_campaign($campaign_id);
    }

    /**
     * Get campaign by ID
     *
     * @param int $campaign_id Campaign ID
     * @return array|null Campaign data or null if not found
     */
    public function get_campaign($campaign_id)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'chatshop_campaigns';

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %d",
            $campaign_id
        ), ARRAY_A);
    }

    /**
     * Get campaigns with filters
     *
     * @param array $args Query arguments
     * @return array Campaigns data
     */
    public function get_campaigns($args = [])
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'chatshop_campaigns';

        $defaults = [
            'status' => '',
            'type' => '',
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

        if (!empty($args['type'])) {
            $where_conditions[] = 'type = %s';
            $where_values[] = $args['type'];
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
     * Get campaign recipients
     *
     * @param int $campaign_id Campaign ID
     * @return array Recipients data
     */
    private function get_campaign_recipients($campaign_id)
    {
        $campaign = $this->get_campaign($campaign_id);
        if (!$campaign) {
            return [];
        }

        $target_audience = json_decode($campaign['target_audience'], true);
        $recipients = [];

        // Get recipients based on target audience criteria
        switch ($target_audience['type']) {
            case 'all_contacts':
                $recipients = $this->get_all_contacts();
                break;
            case 'customer_segments':
                $recipients = $this->get_customers_by_segments($target_audience['segments']);
                break;
            case 'custom_list':
                $recipients = $this->get_custom_list_contacts($target_audience['contact_ids']);
                break;
            case 'recent_customers':
                $recipients = $this->get_recent_customers($target_audience['days']);
                break;
        }

        return $recipients;
    }

    /**
     * Get all contacts
     *
     * @return array Contacts data
     */
    private function get_all_contacts()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'chatshop_contacts';

        return $wpdb->get_results(
            "SELECT phone_number as phone, first_name, last_name, 
                    email, CONCAT(first_name, ' ', last_name) as name 
             FROM {$table_name} 
             WHERE opt_in = 1 AND phone_number != ''",
            ARRAY_A
        );
    }

    /**
     * Get customers by segments
     *
     * @param array $segments Segment criteria
     * @return array Customers data
     */
    private function get_customers_by_segments($segments)
    {
        global $wpdb;

        $conditions = [];
        $values = [];

        foreach ($segments as $segment) {
            switch ($segment['type']) {
                case 'total_spent':
                    if ($segment['operator'] === 'greater_than') {
                        $conditions[] = 'total_spent > %f';
                        $values[] = floatval($segment['value']);
                    } elseif ($segment['operator'] === 'less_than') {
                        $conditions[] = 'total_spent < %f';
                        $values[] = floatval($segment['value']);
                    }
                    break;
                case 'order_count':
                    if ($segment['operator'] === 'greater_than') {
                        $conditions[] = 'order_count > %d';
                        $values[] = intval($segment['value']);
                    } elseif ($segment['operator'] === 'less_than') {
                        $conditions[] = 'order_count < %d';
                        $values[] = intval($segment['value']);
                    }
                    break;
                case 'last_order':
                    if ($segment['operator'] === 'within_days') {
                        $conditions[] = 'last_order_date >= DATE_SUB(NOW(), INTERVAL %d DAY)';
                        $values[] = intval($segment['value']);
                    }
                    break;
            }
        }

        if (empty($conditions)) {
            return [];
        }

        $where_clause = implode(' AND ', $conditions);
        $query = "SELECT phone_number as phone, first_name, last_name, 
                         email, CONCAT(first_name, ' ', last_name) as name 
                  FROM {$wpdb->prefix}chatshop_contacts 
                  WHERE opt_in = 1 AND phone_number != '' AND {$where_clause}";

        return $wpdb->get_results($wpdb->prepare($query, $values), ARRAY_A);
    }

    /**
     * Get custom list contacts
     *
     * @param array $contact_ids Contact IDs
     * @return array Contacts data
     */
    private function get_custom_list_contacts($contact_ids)
    {
        if (empty($contact_ids)) {
            return [];
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'chatshop_contacts';

        $placeholders = implode(',', array_fill(0, count($contact_ids), '%d'));

        return $wpdb->get_results($wpdb->prepare(
            "SELECT phone_number as phone, first_name, last_name, 
                    email, CONCAT(first_name, ' ', last_name) as name 
             FROM {$table_name} 
             WHERE opt_in = 1 AND phone_number != '' AND id IN ({$placeholders})",
            $contact_ids
        ), ARRAY_A);
    }

    /**
     * Get recent customers
     *
     * @param int $days Number of days
     * @return array Customers data
     */
    private function get_recent_customers($days)
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT c.phone_number as phone, c.first_name, c.last_name, 
                    c.email, CONCAT(c.first_name, ' ', c.last_name) as name 
             FROM {$wpdb->prefix}chatshop_contacts c
             INNER JOIN {$wpdb->prefix}woocommerce_order_items oi ON c.email = (
                 SELECT meta_value FROM {$wpdb->prefix}postmeta 
                 WHERE post_id = oi.order_id AND meta_key = '_billing_email'
             )
             INNER JOIN {$wpdb->prefix}posts p ON oi.order_id = p.ID
             WHERE c.opt_in = 1 AND c.phone_number != '' 
             AND p.post_type = 'shop_order' 
             AND p.post_date >= DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ), ARRAY_A);
    }

    /**
     * Update campaign status
     *
     * @param int    $campaign_id Campaign ID
     * @param string $status New status
     */
    private function update_campaign_status($campaign_id, $status)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'chatshop_campaigns';

        $wpdb->update(
            $table_name,
            [
                'status' => $status,
                'updated_at' => current_time('mysql')
            ],
            ['id' => $campaign_id],
            ['%s', '%s'],
            ['%d']
        );
    }

    /**
     * Update campaign statistics
     *
     * @param int   $campaign_id Campaign ID
     * @param array $results Send results
     */
    private function update_campaign_statistics($campaign_id, $results)
    {
        $total_sent = 0;
        $total_failed = 0;

        foreach ($results as $result) {
            if ($result['success']) {
                $total_sent++;
            } else {
                $total_failed++;
            }
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'chatshop_campaigns';

        $wpdb->update(
            $table_name,
            [
                'total_recipients' => count($results),
                'messages_sent' => $total_sent,
                'messages_failed' => $total_failed,
                'sent_at' => current_time('mysql')
            ],
            ['id' => $campaign_id],
            ['%d', '%d', '%d', '%s'],
            ['%d']
        );
    }

    /**
     * Schedule campaign
     *
     * @param int    $campaign_id Campaign ID
     * @param string $scheduled_at Scheduled datetime
     */
    private function schedule_campaign($campaign_id, $scheduled_at)
    {
        $timestamp = strtotime($scheduled_at);

        wp_schedule_single_event(
            $timestamp,
            'chatshop_execute_scheduled_campaign',
            [$campaign_id]
        );

        $this->update_campaign_status($campaign_id, 'scheduled');
    }

    /**
     * Reschedule campaign
     *
     * @param int    $campaign_id Campaign ID
     * @param string $scheduled_at New scheduled datetime
     */
    private function reschedule_campaign($campaign_id, $scheduled_at)
    {
        // Unschedule existing
        $this->unschedule_campaign($campaign_id);

        // Schedule new
        $this->schedule_campaign($campaign_id, $scheduled_at);
    }

    /**
     * Unschedule campaign
     *
     * @param int $campaign_id Campaign ID
     */
    private function unschedule_campaign($campaign_id)
    {
        wp_clear_scheduled_hook('chatshop_execute_scheduled_campaign', [$campaign_id]);
    }

    /**
     * Validate campaign data
     *
     * @param array $data Campaign data
     * @return true|WP_Error True if valid, error if not
     */
    private function validate_campaign_data($data)
    {
        if (empty($data['name'])) {
            return new \WP_Error('missing_name', __('Campaign name is required', 'chatshop'));
        }

        if (empty($data['type']) || !in_array($data['type'], ['broadcast', 'promotional', 'transactional'])) {
            return new \WP_Error('invalid_type', __('Invalid campaign type', 'chatshop'));
        }

        if (empty($data['message_data']) || !is_array($data['message_data'])) {
            return new \WP_Error('invalid_message', __('Invalid message data', 'chatshop'));
        }

        if (empty($data['target_audience']) || !is_array($data['target_audience'])) {
            return new \WP_Error('invalid_audience', __('Invalid target audience', 'chatshop'));
        }

        if ($data['schedule_type'] === 'scheduled' && empty($data['scheduled_at'])) {
            return new \WP_Error('missing_schedule', __('Scheduled time is required for scheduled campaigns', 'chatshop'));
        }

        return true;
    }

    /**
     * Sanitize campaign data
     *
     * @param array $data Campaign data
     * @return array Sanitized data
     */
    private function sanitize_campaign_data($data)
    {
        return [
            'name' => sanitize_text_field($data['name']),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'type' => sanitize_text_field($data['type']),
            'message_data' => $data['message_data'], // Already validated as array
            'target_audience' => $data['target_audience'], // Already validated as array
            'schedule_type' => sanitize_text_field($data['schedule_type'] ?? 'immediate'),
            'scheduled_at' => !empty($data['scheduled_at']) ? sanitize_text_field($data['scheduled_at']) : null
        ];
    }

    /**
     * AJAX handler for creating campaign
     */
    public function ajax_create_campaign()
    {
        check_ajax_referer('chatshop_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'chatshop'));
        }

        $campaign_data = json_decode(file_get_contents('php://input'), true);

        $result = $this->create_campaign($campaign_data);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(['campaign_id' => $result]);
    }

    /**
     * AJAX handler for getting campaign statistics
     */
    public function ajax_get_campaign_stats()
    {
        check_ajax_referer('chatshop_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'chatshop'));
        }

        $campaign_id = intval($_GET['campaign_id'] ?? 0);
        $campaign = $this->get_campaign($campaign_id);

        if (!$campaign) {
            wp_send_json_error(__('Campaign not found', 'chatshop'));
        }

        wp_send_json_success($campaign);
    }
}
