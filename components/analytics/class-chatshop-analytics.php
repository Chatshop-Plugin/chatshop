<?php

/**
 * Analytics Component Class
 *
 * File: components/analytics/class-chatshop-analytics.php
 * 
 * Handles analytics data collection, processing, and dashboard display.
 * Premium feature with WhatsApp-to-payment conversion tracking and revenue attribution.
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
 * ChatShop Analytics Class
 *
 * Manages analytics data collection, processing, and premium dashboard features.
 * Tracks WhatsApp engagement, payment conversions, and revenue attribution.
 *
 * @since 1.0.0
 */
class ChatShop_Analytics extends ChatShop_Abstract_Component
{
    /**
     * Component ID
     *
     * @var string
     * @since 1.0.0
     */
    protected $id = 'analytics';

    /**
     * Database table name for analytics data
     *
     * @var string
     * @since 1.0.0
     */
    private $table_name;

    /**
     * Analytics cache duration (1 hour)
     *
     * @var int
     * @since 1.0.0
     */
    private $cache_duration = 3600;

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        // Initialize the component
        $this->init();
    }

    /**
     * Initialize component - MADE PUBLIC TO FIX VISIBILITY ERROR
     *
     * @since 1.0.0
     */
    public function init()
    {
        global $wpdb;

        $this->name = __('Analytics Dashboard', 'chatshop');
        $this->description = __('Premium analytics with WhatsApp-to-payment conversion tracking and revenue attribution', 'chatshop');
        $this->version = '1.0.0';
        $this->table_name = $wpdb->prefix . 'chatshop_analytics';

        $this->init_hooks();

        // Log successful initialization
        if (function_exists('chatshop_log')) {
            chatshop_log('Analytics component initialized successfully', 'info');
        }
    }

    /**
     * Initialize hooks
     *
     * @since 1.0.0
     */
    private function init_hooks()
    {
        // AJAX handlers for analytics data
        add_action('wp_ajax_chatshop_get_analytics_data', array($this, 'ajax_get_analytics_data'));
        add_action('wp_ajax_chatshop_get_conversion_stats', array($this, 'ajax_get_conversion_stats'));
        add_action('wp_ajax_chatshop_get_revenue_attribution', array($this, 'ajax_get_revenue_attribution'));
        add_action('wp_ajax_chatshop_get_performance_metrics', array($this, 'ajax_get_performance_metrics'));

        // Hook into payment completion to track conversions
        add_action('chatshop_payment_completed', array($this, 'track_payment_conversion'), 10, 2);

        // Hook into contact interactions to track engagement
        add_action('chatshop_contact_interaction', array($this, 'track_contact_interaction'), 10, 3);

        // Daily analytics aggregation
        add_action('chatshop_daily_cleanup', array($this, 'aggregate_daily_analytics'));
    }

    /**
     * Component activation
     *
     * @since 1.0.0
     * @return bool Activation result
     */
    protected function do_activation()
    {
        return $this->create_database_table();
    }

    /**
     * Component deactivation
     *
     * @since 1.0.0
     * @return bool Deactivation result
     */
    protected function do_deactivation()
    {
        // Keep analytics data on deactivation
        return true;
    }

    /**
     * Create database table for analytics
     *
     * @since 1.0.0
     * @return bool Creation result
     */
    private function create_database_table()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            metric_type varchar(50) NOT NULL,
            metric_name varchar(100) NOT NULL,
            metric_value decimal(15,4) NOT NULL DEFAULT 0,
            metric_date date NOT NULL,
            source_type varchar(50) DEFAULT '',
            source_id varchar(100) DEFAULT '',
            contact_id bigint(20) unsigned DEFAULT NULL,
            payment_id varchar(100) DEFAULT '',
            gateway varchar(50) DEFAULT '',
            revenue decimal(15,2) DEFAULT 0,
            currency varchar(10) DEFAULT 'NGN',
            meta_data longtext DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY metric_type (metric_type),
            KEY metric_date (metric_date),
            KEY contact_id (contact_id),
            KEY payment_id (payment_id),
            KEY gateway (gateway),
            KEY created_at (created_at),
            UNIQUE KEY unique_daily_metric (metric_type, metric_name, metric_date, source_type, source_id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $result = dbDelta($sql);

        if ($result) {
            if (function_exists('chatshop_log')) {
                chatshop_log('Analytics database table created successfully', 'info');
            }
            return true;
        } else {
            if (function_exists('chatshop_log')) {
                chatshop_log('Failed to create analytics database table', 'error');
            }
            return false;
        }
    }

    /**
     * AJAX handler for getting analytics data
     *
     * @since 1.0.0
     */
    public function ajax_get_analytics_data()
    {
        check_ajax_referer('chatshop_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions.', 'chatshop'));
        }

        if (!chatshop_is_premium()) {
            wp_send_json_error(array(
                'message' => __('Analytics dashboard is a premium feature.', 'chatshop')
            ));
        }

        $date_range = sanitize_text_field($_POST['date_range'] ?? '7days');
        $metric_type = sanitize_text_field($_POST['metric_type'] ?? 'overview');

        $data = $this->get_analytics_data($date_range, $metric_type);

        wp_send_json_success($data);
    }

    /**
     * AJAX handler for conversion statistics
     *
     * @since 1.0.0
     */
    public function ajax_get_conversion_stats()
    {
        check_ajax_referer('chatshop_admin_nonce', 'nonce');

        if (!current_user_can('manage_options') || !chatshop_is_premium()) {
            wp_send_json_error(array('message' => __('Access denied.', 'chatshop')));
        }

        $date_range = sanitize_text_field($_POST['date_range'] ?? '7days');
        $stats = $this->get_conversion_statistics($date_range);

        wp_send_json_success($stats);
    }

    /**
     * AJAX handler for revenue attribution
     *
     * @since 1.0.0
     */
    public function ajax_get_revenue_attribution()
    {
        check_ajax_referer('chatshop_admin_nonce', 'nonce');

        if (!current_user_can('manage_options') || !chatshop_is_premium()) {
            wp_send_json_error(array('message' => __('Access denied.', 'chatshop')));
        }

        $date_range = sanitize_text_field($_POST['date_range'] ?? '7days');
        $attribution = $this->get_revenue_attribution($date_range);

        wp_send_json_success($attribution);
    }

    /**
     * AJAX handler for performance metrics
     *
     * @since 1.0.0
     */
    public function ajax_get_performance_metrics()
    {
        check_ajax_referer('chatshop_admin_nonce', 'nonce');

        if (!current_user_can('manage_options') || !chatshop_is_premium()) {
            wp_send_json_error(array('message' => __('Access denied.', 'chatshop')));
        }

        $metrics = $this->get_performance_metrics();

        wp_send_json_success($metrics);
    }

    /**
     * Get analytics data for dashboard
     *
     * @since 1.0.0
     * @param string $date_range Date range filter
     * @param string $metric_type Type of metrics to retrieve
     * @return array Analytics data
     */
    public function get_analytics_data($date_range = '7days', $metric_type = 'overview')
    {
        $cache_key = "chatshop_analytics_{$date_range}_{$metric_type}";
        $cached_data = get_transient($cache_key);

        if ($cached_data !== false) {
            return $cached_data;
        }

        $date_filter = $this->get_date_filter($date_range);
        $data = array();

        switch ($metric_type) {
            case 'overview':
                $data = $this->get_overview_metrics($date_filter);
                break;
            case 'conversions':
                $data = $this->get_conversion_metrics($date_filter);
                break;
            case 'revenue':
                $data = $this->get_revenue_metrics($date_filter);
                break;
            case 'engagement':
                $data = $this->get_engagement_metrics($date_filter);
                break;
            default:
                $data = $this->get_overview_metrics($date_filter);
        }

        // Cache for 1 hour
        set_transient($cache_key, $data, $this->cache_duration);

        return $data;
    }

    /**
     * Get overview metrics
     *
     * @since 1.0.0
     * @param array $date_filter Date filter conditions
     * @return array Overview metrics
     */
    private function get_overview_metrics($date_filter)
    {
        global $wpdb;

        $data = array(
            'total_revenue' => 0,
            'total_transactions' => 0,
            'whatsapp_conversions' => 0,
            'conversion_rate' => 0,
            'average_order_value' => 0,
            'top_gateways' => array(),
            'revenue_trend' => array()
        );

        // Get total revenue for date range
        $revenue_query = $wpdb->prepare(
            "SELECT SUM(revenue) as total_revenue, COUNT(*) as total_transactions
             FROM {$this->table_name}
             WHERE metric_type = 'payment_completed'
             AND metric_date BETWEEN %s AND %s",
            $date_filter['start'],
            $date_filter['end']
        );

        $revenue_result = $wpdb->get_row($revenue_query);
        if ($revenue_result) {
            $data['total_revenue'] = (float) $revenue_result->total_revenue;
            $data['total_transactions'] = (int) $revenue_result->total_transactions;
        }

        // Calculate average order value
        if ($data['total_transactions'] > 0) {
            $data['average_order_value'] = $data['total_revenue'] / $data['total_transactions'];
        }

        // Get WhatsApp conversions
        $whatsapp_conversions = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name}
             WHERE metric_type = 'whatsapp_conversion'
             AND metric_date BETWEEN %s AND %s",
            $date_filter['start'],
            $date_filter['end']
        ));

        $data['whatsapp_conversions'] = (int) $whatsapp_conversions;

        // Calculate conversion rate
        $total_whatsapp_interactions = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name}
             WHERE metric_type = 'whatsapp_interaction'
             AND metric_date BETWEEN %s AND %s",
            $date_filter['start'],
            $date_filter['end']
        ));

        if ($total_whatsapp_interactions > 0) {
            $data['conversion_rate'] = ($data['whatsapp_conversions'] / $total_whatsapp_interactions) * 100;
        }

        // Get top performing gateways
        $gateway_query = $wpdb->prepare(
            "SELECT gateway, SUM(revenue) as gateway_revenue, COUNT(*) as gateway_transactions
             FROM {$this->table_name}
             WHERE metric_type = 'payment_completed'
             AND metric_date BETWEEN %s AND %s
             GROUP BY gateway
             ORDER BY gateway_revenue DESC
             LIMIT 5",
            $date_filter['start'],
            $date_filter['end']
        );

        $data['top_gateways'] = $wpdb->get_results($gateway_query);

        // Get revenue trend (daily data for chart)
        $trend_query = $wpdb->prepare(
            "SELECT metric_date, SUM(revenue) as daily_revenue
             FROM {$this->table_name}
             WHERE metric_type = 'payment_completed'
             AND metric_date BETWEEN %s AND %s
             GROUP BY metric_date
             ORDER BY metric_date ASC",
            $date_filter['start'],
            $date_filter['end']
        );

        $data['revenue_trend'] = $wpdb->get_results($trend_query);

        return $data;
    }

    /**
     * Get conversion statistics
     *
     * @since 1.0.0
     * @param string $date_range Date range filter
     * @return array Conversion statistics
     */
    private function get_conversion_statistics($date_range)
    {
        $date_filter = $this->get_date_filter($date_range);
        global $wpdb;

        $data = array(
            'funnel_data' => array(),
            'source_breakdown' => array(),
            'conversion_by_day' => array()
        );

        // Get conversion funnel data
        $funnel_steps = array(
            'whatsapp_interaction' => __('WhatsApp Interactions', 'chatshop'),
            'payment_link_click' => __('Payment Link Clicks', 'chatshop'),
            'payment_initiated' => __('Payment Initiated', 'chatshop'),
            'payment_completed' => __('Payment Completed', 'chatshop')
        );

        foreach ($funnel_steps as $metric => $label) {
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name}
                 WHERE metric_type = %s
                 AND metric_date BETWEEN %s AND %s",
                $metric,
                $date_filter['start'],
                $date_filter['end']
            ));

            $data['funnel_data'][] = array(
                'step' => $label,
                'count' => (int) $count
            );
        }

        return $data;
    }

    /**
     * Get revenue attribution
     *
     * @since 1.0.0
     * @param string $date_range Date range filter
     * @return array Revenue attribution data
     */
    private function get_revenue_attribution($date_range)
    {
        $date_filter = $this->get_date_filter($date_range);
        global $wpdb;

        $attribution_query = $wpdb->prepare(
            "SELECT source_type, SUM(revenue) as source_revenue
             FROM {$this->table_name}
             WHERE metric_type = 'payment_completed'
             AND metric_date BETWEEN %s AND %s
             GROUP BY source_type
             ORDER BY source_revenue DESC",
            $date_filter['start'],
            $date_filter['end']
        );

        return $wpdb->get_results($attribution_query);
    }

    /**
     * Get performance metrics
     *
     * @since 1.0.0
     * @return array Performance metrics
     */
    private function get_performance_metrics()
    {
        global $wpdb;

        // Get recent performance data (last 30 days)
        $date_filter = $this->get_date_filter('30days');

        $performance_query = $wpdb->prepare(
            "SELECT gateway, 
                    AVG(metric_value) as avg_processing_time,
                    SUM(CASE WHEN metric_type = 'payment_completed' THEN 1 ELSE 0 END) as successful_payments,
                    SUM(CASE WHEN metric_type = 'payment_failed' THEN 1 ELSE 0 END) as failed_payments
             FROM {$this->table_name}
             WHERE metric_date BETWEEN %s AND %s
             AND gateway != ''
             GROUP BY gateway
             ORDER BY successful_payments DESC",
            $date_filter['start'],
            $date_filter['end']
        );

        return $wpdb->get_results($performance_query);
    }

    /**
     * Get date filter conditions
     *
     * @since 1.0.0
     * @param string $range Date range identifier
     * @return array Date filter array with start and end dates
     */
    private function get_date_filter($range)
    {
        $end_date = current_time('Y-m-d');

        switch ($range) {
            case 'today':
                $start_date = $end_date;
                break;
            case '7days':
                $start_date = date('Y-m-d', strtotime('-7 days'));
                break;
            case '30days':
                $start_date = date('Y-m-d', strtotime('-30 days'));
                break;
            case '90days':
                $start_date = date('Y-m-d', strtotime('-90 days'));
                break;
            case 'this_month':
                $start_date = date('Y-m-01');
                break;
            case 'last_month':
                $start_date = date('Y-m-01', strtotime('last month'));
                $end_date = date('Y-m-t', strtotime('last month'));
                break;
            default:
                $start_date = date('Y-m-d', strtotime('-7 days'));
        }

        return array(
            'start' => $start_date,
            'end' => $end_date
        );
    }

    /**
     * Track payment conversion
     *
     * @since 1.0.0
     * @param array $payment_data Payment data
     * @param string $gateway Gateway identifier
     */
    public function track_payment_conversion($payment_data, $gateway)
    {
        if (!chatshop_is_premium()) {
            return;
        }

        global $wpdb;

        $metric_data = array(
            'metric_type' => 'payment_completed',
            'metric_name' => 'conversion',
            'metric_value' => 1,
            'metric_date' => current_time('Y-m-d'),
            'source_type' => $payment_data['source_type'] ?? 'direct',
            'source_id' => $payment_data['source_id'] ?? '',
            'contact_id' => $payment_data['contact_id'] ?? null,
            'payment_id' => $payment_data['payment_id'] ?? '',
            'gateway' => $gateway,
            'revenue' => $payment_data['amount'] ?? 0,
            'currency' => $payment_data['currency'] ?? 'NGN',
            'meta_data' => maybe_serialize($payment_data),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );

        $wpdb->insert($this->table_name, $metric_data);

        // Clear related caches
        $this->clear_analytics_cache();
    }

    /**
     * Track contact interaction
     *
     * @since 1.0.0
     * @param int $contact_id Contact ID
     * @param string $interaction_type Type of interaction
     * @param array $interaction_data Additional interaction data
     */
    public function track_contact_interaction($contact_id, $interaction_type, $interaction_data = array())
    {
        if (!chatshop_is_premium()) {
            return;
        }

        global $wpdb;

        $metric_data = array(
            'metric_type' => 'whatsapp_interaction',
            'metric_name' => $interaction_type,
            'metric_value' => 1,
            'metric_date' => current_time('Y-m-d'),
            'source_type' => 'whatsapp',
            'source_id' => $interaction_data['message_id'] ?? '',
            'contact_id' => $contact_id,
            'meta_data' => maybe_serialize($interaction_data),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );

        $wpdb->insert($this->table_name, $metric_data);

        // Clear related caches
        $this->clear_analytics_cache();
    }

    /**
     * Aggregate daily analytics
     *
     * @since 1.0.0
     */
    public function aggregate_daily_analytics()
    {
        if (!chatshop_is_premium()) {
            return;
        }

        // This method can be expanded to perform daily analytics aggregations
        // such as calculating daily summaries, cleaning up old data, etc.

        global $wpdb;

        $yesterday = date('Y-m-d', strtotime('-1 day'));

        // Example: Create daily summary records
        $summary_data = array(
            'metric_type' => 'daily_summary',
            'metric_name' => 'total_interactions',
            'metric_value' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} 
                 WHERE metric_type = 'whatsapp_interaction' 
                 AND metric_date = %s",
                $yesterday
            )),
            'metric_date' => $yesterday,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );

        // Insert summary if it doesn't exist
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table_name} 
             WHERE metric_type = 'daily_summary' 
             AND metric_name = 'total_interactions' 
             AND metric_date = %s",
            $yesterday
        ));

        if (!$existing) {
            $wpdb->insert($this->table_name, $summary_data);
        }

        // Clear old caches
        $this->clear_analytics_cache();
    }

    /**
     * Clear analytics cache
     *
     * @since 1.0.0
     */
    private function clear_analytics_cache()
    {
        $cache_keys = array(
            'chatshop_analytics_7days_overview',
            'chatshop_analytics_30days_overview',
            'chatshop_analytics_90days_overview',
            'chatshop_analytics_today_overview',
            'chatshop_analytics_this_month_overview',
            'chatshop_analytics_last_month_overview'
        );

        foreach ($cache_keys as $key) {
            delete_transient($key);
        }
    }

    /**
     * Get engagement metrics
     *
     * @since 1.0.0
     * @param array $date_filter Date filter conditions
     * @return array Engagement metrics
     */
    private function get_engagement_metrics($date_filter)
    {
        global $wpdb;

        $data = array(
            'total_interactions' => 0,
            'unique_contacts' => 0,
            'messages_sent' => 0,
            'messages_received' => 0,
            'response_rate' => 0
        );

        // Get total interactions
        $data['total_interactions'] = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name}
             WHERE metric_type = 'whatsapp_interaction'
             AND metric_date BETWEEN %s AND %s",
            $date_filter['start'],
            $date_filter['end']
        ));

        // Get unique contacts
        $data['unique_contacts'] = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT contact_id) FROM {$this->table_name}
             WHERE metric_type = 'whatsapp_interaction'
             AND metric_date BETWEEN %s AND %s
             AND contact_id IS NOT NULL",
            $date_filter['start'],
            $date_filter['end']
        ));

        // Get messages sent
        $data['messages_sent'] = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name}
             WHERE metric_type = 'whatsapp_interaction'
             AND metric_name = 'message_sent'
             AND metric_date BETWEEN %s AND %s",
            $date_filter['start'],
            $date_filter['end']
        ));

        // Get messages received
        $data['messages_received'] = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name}
             WHERE metric_type = 'whatsapp_interaction'
             AND metric_name = 'message_received'
             AND metric_date BETWEEN %s AND %s",
            $date_filter['start'],
            $date_filter['end']
        ));

        // Calculate response rate
        if ($data['messages_sent'] > 0) {
            $data['response_rate'] = ($data['messages_received'] / $data['messages_sent']) * 100;
        }

        return $data;
    }

    /**
     * Get revenue metrics
     *
     * @since 1.0.0
     * @param array $date_filter Date filter conditions
     * @return array Revenue metrics
     */
    private function get_revenue_metrics($date_filter)
    {
        global $wpdb;

        $data = array(
            'total_revenue' => 0,
            'revenue_by_gateway' => array(),
            'revenue_by_source' => array(),
            'average_transaction_value' => 0,
            'revenue_growth' => 0
        );

        // Get total revenue
        $data['total_revenue'] = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(revenue) FROM {$this->table_name}
             WHERE metric_type = 'payment_completed'
             AND metric_date BETWEEN %s AND %s",
            $date_filter['start'],
            $date_filter['end']
        ));

        // Get revenue by gateway
        $gateway_revenue = $wpdb->get_results($wpdb->prepare(
            "SELECT gateway, SUM(revenue) as total_revenue, COUNT(*) as transaction_count
             FROM {$this->table_name}
             WHERE metric_type = 'payment_completed'
             AND metric_date BETWEEN %s AND %s
             AND gateway != ''
             GROUP BY gateway
             ORDER BY total_revenue DESC",
            $date_filter['start'],
            $date_filter['end']
        ));

        $data['revenue_by_gateway'] = $gateway_revenue;

        // Get revenue by source
        $source_revenue = $wpdb->get_results($wpdb->prepare(
            "SELECT source_type, SUM(revenue) as total_revenue
             FROM {$this->table_name}
             WHERE metric_type = 'payment_completed'
             AND metric_date BETWEEN %s AND %s
             GROUP BY source_type
             ORDER BY total_revenue DESC",
            $date_filter['start'],
            $date_filter['end']
        ));

        $data['revenue_by_source'] = $source_revenue;

        // Calculate average transaction value
        $transaction_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name}
             WHERE metric_type = 'payment_completed'
             AND metric_date BETWEEN %s AND %s",
            $date_filter['start'],
            $date_filter['end']
        ));

        if ($transaction_count > 0) {
            $data['average_transaction_value'] = $data['total_revenue'] / $transaction_count;
        }

        return $data;
    }

    /**
     * Check if component is active
     *
     * @since 1.0.0
     * @return bool True if active, false otherwise
     */
    public function is_active()
    {
        return chatshop_is_premium();
    }

    /**
     * Get component status for debugging
     *
     * @since 1.0.0
     * @return array Component status information
     */
    public function get_status()
    {
        global $wpdb;

        $status = array(
            'component_id' => $this->id,
            'name' => $this->name,
            'version' => $this->version,
            'is_active' => $this->is_active(),
            'table_exists' => false,
            'record_count' => 0
        );

        // Check if table exists
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $this->table_name
        ));

        if ($table_exists) {
            $status['table_exists'] = true;
            $status['record_count'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
        }

        return $status;
    }
}
