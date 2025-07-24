<?php

/**
 * Analytics Metrics Collector Class
 *
 * File: components/analytics/class-chatshop-metrics-collector.php
 * 
 * Handles data collection for analytics including WhatsApp interactions,
 * payment conversions, and revenue attribution tracking.
 *
 * @package ChatShop
 * @subpackage Components\Analytics
 * @since 1.0.0
 */

namespace ChatShop;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ChatShop Metrics Collector Class
 *
 * Collects and processes analytics data from various sources including
 * WhatsApp interactions, payment gateways, and customer activities.
 *
 * @since 1.0.0
 */
class ChatShop_Metrics_Collector
{
    /**
     * Analytics table name
     *
     * @var string
     * @since 1.0.0
     */
    private $analytics_table;

    /**
     * Conversion table name
     *
     * @var string
     * @since 1.0.0
     */
    private $conversion_table;

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        global $wpdb;

        $this->analytics_table = $wpdb->prefix . 'chatshop_analytics';
        $this->conversion_table = $wpdb->prefix . 'chatshop_conversions';

        $this->init_hooks();
    }

    /**
     * Initialize hooks
     *
     * @since 1.0.0
     */
    private function init_hooks()
    {
        // Payment tracking
        add_action('chatshop_payment_completed', array($this, 'track_payment_conversion'), 10, 3);
        add_action('chatshop_payment_failed', array($this, 'track_payment_failure'), 10, 2);

        // WhatsApp interaction tracking
        add_action('chatshop_message_sent', array($this, 'track_message_sent'), 10, 3);
        add_action('chatshop_message_delivered', array($this, 'track_message_delivery'), 10, 2);
        add_action('chatshop_contact_interaction', array($this, 'track_contact_interaction'), 10, 3);

        // Campaign tracking
        add_action('chatshop_campaign_sent', array($this, 'track_campaign_sent'), 10, 2);
        add_action('chatshop_campaign_clicked', array($this, 'track_campaign_click'), 10, 2);
    }

    /**
     * Track payment conversion
     *
     * @since 1.0.0
     * @param string $payment_id Payment ID
     * @param array $payment_data Payment data
     * @param string $source_type Source type (whatsapp, direct, etc.)
     */
    public function track_payment_conversion($payment_id, $payment_data, $source_type = 'direct')
    {
        if (!chatshop_is_premium()) {
            return;
        }

        global $wpdb;

        // Track in analytics table
        $analytics_data = array(
            'metric_type' => 'payment',
            'metric_name' => 'conversion',
            'metric_value' => 1,
            'metric_date' => current_time('Y-m-d'),
            'source_type' => sanitize_text_field($source_type),
            'contact_id' => isset($payment_data['contact_id']) ? absint($payment_data['contact_id']) : null,
            'payment_id' => sanitize_text_field($payment_id),
            'gateway' => sanitize_text_field($payment_data['gateway'] ?? ''),
            'revenue' => floatval($payment_data['amount'] ?? 0),
            'currency' => sanitize_text_field($payment_data['currency'] ?? 'NGN'),
            'meta_data' => wp_json_encode($payment_data)
        );

        $wpdb->insert($this->analytics_table, $analytics_data);

        // Track detailed conversion
        $conversion_data = array(
            'payment_id' => sanitize_text_field($payment_id),
            'contact_id' => isset($payment_data['contact_id']) ? absint($payment_data['contact_id']) : null,
            'source_type' => sanitize_text_field($source_type),
            'source_id' => sanitize_text_field($payment_data['source_id'] ?? ''),
            'conversion_value' => floatval($payment_data['amount'] ?? 0),
            'currency' => sanitize_text_field($payment_data['currency'] ?? 'NGN'),
            'gateway' => sanitize_text_field($payment_data['gateway'] ?? ''),
            'conversion_date' => current_time('mysql'),
            'customer_journey' => wp_json_encode($this->get_customer_journey($payment_data['contact_id'] ?? null))
        );

        $wpdb->insert($this->conversion_table, $conversion_data);

        chatshop_log("Payment conversion tracked: {$payment_id}", 'info', $analytics_data);
    }

    /**
     * Track payment failure
     *
     * @since 1.0.0
     * @param string $payment_id Payment ID
     * @param array $payment_data Payment data
     */
    public function track_payment_failure($payment_id, $payment_data)
    {
        if (!chatshop_is_premium()) {
            return;
        }

        global $wpdb;

        $analytics_data = array(
            'metric_type' => 'payment',
            'metric_name' => 'failure',
            'metric_value' => 1,
            'metric_date' => current_time('Y-m-d'),
            'source_type' => sanitize_text_field($payment_data['source_type'] ?? 'direct'),
            'contact_id' => isset($payment_data['contact_id']) ? absint($payment_data['contact_id']) : null,
            'payment_id' => sanitize_text_field($payment_id),
            'gateway' => sanitize_text_field($payment_data['gateway'] ?? ''),
            'revenue' => 0,
            'currency' => sanitize_text_field($payment_data['currency'] ?? 'NGN'),
            'meta_data' => wp_json_encode($payment_data)
        );

        $wpdb->insert($this->analytics_table, $analytics_data);
    }

    /**
     * Track message sent
     *
     * @since 1.0.0
     * @param string $contact_phone Contact phone
     * @param string $message_type Message type
     * @param array $message_data Message data
     */
    public function track_message_sent($contact_phone, $message_type, $message_data)
    {
        if (!chatshop_is_premium()) {
            return;
        }

        global $wpdb;

        $analytics_data = array(
            'metric_type' => 'whatsapp',
            'metric_name' => 'message_sent',
            'metric_value' => 1,
            'metric_date' => current_time('Y-m-d'),
            'source_type' => 'whatsapp',
            'source_id' => sanitize_text_field($contact_phone),
            'contact_id' => $this->get_contact_id_by_phone($contact_phone),
            'meta_data' => wp_json_encode(array(
                'message_type' => $message_type,
                'template' => $message_data['template'] ?? '',
                'campaign_id' => $message_data['campaign_id'] ?? ''
            ))
        );

        $wpdb->insert($this->analytics_table, $analytics_data);
    }

    /**
     * Track message delivery
     *
     * @since 1.0.0
     * @param string $message_id Message ID
     * @param string $status Delivery status
     */
    public function track_message_delivery($message_id, $status)
    {
        if (!chatshop_is_premium()) {
            return;
        }

        global $wpdb;

        $metric_name = $status === 'delivered' ? 'message_delivered' : 'message_failed';

        $analytics_data = array(
            'metric_type' => 'whatsapp',
            'metric_name' => $metric_name,
            'metric_value' => 1,
            'metric_date' => current_time('Y-m-d'),
            'source_type' => 'whatsapp',
            'source_id' => sanitize_text_field($message_id),
            'meta_data' => wp_json_encode(array('status' => $status))
        );

        $wpdb->insert($this->analytics_table, $analytics_data);
    }

    /**
     * Track contact interaction
     *
     * @since 1.0.0
     * @param int $contact_id Contact ID
     * @param string $interaction_type Interaction type
     * @param array $interaction_data Interaction data
     */
    public function track_contact_interaction($contact_id, $interaction_type, $interaction_data)
    {
        if (!chatshop_is_premium()) {
            return;
        }

        global $wpdb;

        $analytics_data = array(
            'metric_type' => 'interaction',
            'metric_name' => sanitize_text_field($interaction_type),
            'metric_value' => 1,
            'metric_date' => current_time('Y-m-d'),
            'source_type' => 'whatsapp',
            'contact_id' => absint($contact_id),
            'meta_data' => wp_json_encode($interaction_data)
        );

        $wpdb->insert($this->analytics_table, $analytics_data);
    }

    /**
     * Track campaign sent
     *
     * @since 1.0.0
     * @param int $campaign_id Campaign ID
     * @param array $campaign_data Campaign data
     */
    public function track_campaign_sent($campaign_id, $campaign_data)
    {
        if (!chatshop_is_premium()) {
            return;
        }

        global $wpdb;

        $analytics_data = array(
            'metric_type' => 'campaign',
            'metric_name' => 'sent',
            'metric_value' => intval($campaign_data['recipients_count'] ?? 1),
            'metric_date' => current_time('Y-m-d'),
            'source_type' => 'whatsapp',
            'source_id' => sanitize_text_field($campaign_id),
            'meta_data' => wp_json_encode($campaign_data)
        );

        $wpdb->insert($this->analytics_table, $analytics_data);
    }

    /**
     * Track campaign click
     *
     * @since 1.0.0
     * @param int $campaign_id Campaign ID
     * @param array $click_data Click data
     */
    public function track_campaign_click($campaign_id, $click_data)
    {
        if (!chatshop_is_premium()) {
            return;
        }

        global $wpdb;

        $analytics_data = array(
            'metric_type' => 'campaign',
            'metric_name' => 'click',
            'metric_value' => 1,
            'metric_date' => current_time('Y-m-d'),
            'source_type' => 'whatsapp',
            'source_id' => sanitize_text_field($campaign_id),
            'contact_id' => absint($click_data['contact_id'] ?? 0),
            'meta_data' => wp_json_encode($click_data)
        );

        $wpdb->insert($this->analytics_table, $analytics_data);
    }

    /**
     * Get contact ID by phone number
     *
     * @since 1.0.0
     * @param string $phone Phone number
     * @return int|null Contact ID
     */
    private function get_contact_id_by_phone($phone)
    {
        global $wpdb;

        $contact_table = $wpdb->prefix . 'chatshop_contacts';

        $contact_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$contact_table} WHERE phone = %s",
                sanitize_text_field($phone)
            )
        );

        return $contact_id ? absint($contact_id) : null;
    }

    /**
     * Get customer journey for conversion tracking
     *
     * @since 1.0.0
     * @param int|null $contact_id Contact ID
     * @return array Customer journey data
     */
    private function get_customer_journey($contact_id)
    {
        if (!$contact_id) {
            return array();
        }

        global $wpdb;

        // Get last 10 interactions for this contact
        $interactions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT metric_type, metric_name, metric_date, meta_data 
                 FROM {$this->analytics_table} 
                 WHERE contact_id = %d 
                 ORDER BY created_at DESC 
                 LIMIT 10",
                $contact_id
            ),
            ARRAY_A
        );

        return $interactions ?: array();
    }

    /**
     * Get analytics data for date range
     *
     * @since 1.0.0
     * @param string $start_date Start date (Y-m-d)
     * @param string $end_date End date (Y-m-d)
     * @param string $metric_type Metric type filter
     * @return array Analytics data
     */
    public function get_analytics_data($start_date, $end_date, $metric_type = '')
    {
        if (!chatshop_is_premium()) {
            return array();
        }

        global $wpdb;

        $where_clause = "WHERE metric_date BETWEEN %s AND %s";
        $params = array($start_date, $end_date);

        if (!empty($metric_type)) {
            $where_clause .= " AND metric_type = %s";
            $params[] = $metric_type;
        }

        $query = "SELECT 
                    metric_type,
                    metric_name,
                    SUM(metric_value) as total_value,
                    SUM(revenue) as total_revenue,
                    COUNT(*) as total_count,
                    metric_date
                  FROM {$this->analytics_table} 
                  {$where_clause}
                  GROUP BY metric_type, metric_name, metric_date 
                  ORDER BY metric_date DESC";

        return $wpdb->get_results(
            $wpdb->prepare($query, $params),
            ARRAY_A
        );
    }

    /**
     * Get conversion data for date range
     *
     * @since 1.0.0
     * @param string $start_date Start date (Y-m-d)
     * @param string $end_date End date (Y-m-d)
     * @return array Conversion data
     */
    public function get_conversion_data($start_date, $end_date)
    {
        if (!chatshop_is_premium()) {
            return array();
        }

        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT 
                    source_type,
                    gateway,
                    SUM(conversion_value) as total_revenue,
                    COUNT(*) as total_conversions,
                    AVG(conversion_value) as avg_conversion_value,
                    DATE(conversion_date) as conversion_date
                 FROM {$this->conversion_table} 
                 WHERE DATE(conversion_date) BETWEEN %s AND %s
                 GROUP BY source_type, gateway, DATE(conversion_date)
                 ORDER BY conversion_date DESC",
                $start_date,
                $end_date
            ),
            ARRAY_A
        );
    }
}
