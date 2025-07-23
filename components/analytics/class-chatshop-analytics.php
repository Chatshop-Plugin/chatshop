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
     * Initialize component
     *
     * @since 1.0.0
     */
    protected function init()
    {
        global $wpdb;

        $this->name = __('Analytics Dashboard', 'chatshop');
        $this->description = __('Premium analytics with WhatsApp-to-payment conversion tracking and revenue attribution', 'chatshop');
        $this->version = '1.0.0';
        $this->table_name = $wpdb->prefix . 'chatshop_analytics';

        $this->init_hooks();
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
            chatshop_log('Analytics database table created successfully', 'info');
            return true;
        } else {
            chatshop_log('Failed to create analytics database table', 'error');
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

        set_transient($cache_key, $data, $this->cache_duration);

        return $data;
    }

    /**
     * Get overview metrics
     *
     * @since 1.0.0
     * @param array $date_filter Date filter array
     * @return array Overview metrics
     */
    private function get_overview_metrics($date_filter)
    {
        global $wpdb;

        $start_date = $date_filter['start'];
        $end_date = $date_filter['end'];

        // Total revenue
        $total_revenue = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(revenue) FROM {$this->table_name} 
             WHERE metric_type = 'payment' AND metric_date BETWEEN %s AND %s",
            $start_date,
            $end_date
        ));

        // Total conversions
        $total_conversions = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} 
             WHERE metric_type = 'conversion' AND metric_date BETWEEN %s AND %s",
            $start_date,
            $end_date
        ));

        // WhatsApp interactions
        $whatsapp_interactions = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(metric_value) FROM {$this->table_name} 
             WHERE metric_type = 'interaction' AND source_type = 'whatsapp' 
             AND metric_date BETWEEN %s AND %s",
            $start_date,
            $end_date
        ));

        // Active contacts
        $active_contacts = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT contact_id) FROM {$this->table_name} 
             WHERE metric_type = 'interaction' AND metric_date BETWEEN %s AND %s",
            $start_date,
            $end_date
        ));

        // Daily breakdown for charts
        $daily_data = $wpdb->get_results($wpdb->prepare(
            "SELECT metric_date, 
                    SUM(CASE WHEN metric_type = 'payment' THEN revenue ELSE 0 END) as daily_revenue,
                    COUNT(CASE WHEN metric_type = 'conversion' THEN 1 END) as daily_conversions,
                    SUM(CASE WHEN metric_type = 'interaction' THEN metric_value ELSE 0 END) as daily_interactions
             FROM {$this->table_name} 
             WHERE metric_date BETWEEN %s AND %s 
             GROUP BY metric_date 
             ORDER BY metric_date",
            $start_date,
            $end_date
        ), ARRAY_A);

        return array(
            'totals' => array(
                'revenue' => floatval($total_revenue ?? 0),
                'conversions' => intval($total_conversions ?? 0),
                'interactions' => intval($whatsapp_interactions ?? 0),
                'active_contacts' => intval($active_contacts ?? 0)
            ),
            'daily_breakdown' => $daily_data,
            'conversion_rate' => $whatsapp_interactions > 0 ? round(($total_conversions / $whatsapp_interactions) * 100, 2) : 0
        );
    }

    /**
     * Get conversion statistics
     *
     * @since 1.0.0
     * @param string $date_range Date range filter
     * @return array Conversion statistics
     */
    public function get_conversion_statistics($date_range)
    {
        global $wpdb;

        $date_filter = $this->get_date_filter($date_range);
        $start_date = $date_filter['start'];
        $end_date = $date_filter['end'];

        // Conversion funnel data
        $funnel_data = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                COUNT(CASE WHEN metric_name = 'message_sent' THEN 1 END) as messages_sent,
                COUNT(CASE WHEN metric_name = 'message_opened' THEN 1 END) as messages_opened,
                COUNT(CASE WHEN metric_name = 'link_clicked' THEN 1 END) as links_clicked,
                COUNT(CASE WHEN metric_name = 'payment_initiated' THEN 1 END) as payments_initiated,
                COUNT(CASE WHEN metric_name = 'payment_completed' THEN 1 END) as payments_completed
             FROM {$this->table_name} 
             WHERE metric_type = 'interaction' AND metric_date BETWEEN %s AND %s",
            $start_date,
            $end_date
        ), ARRAY_A);

        // Gateway conversion rates
        $gateway_conversions = $wpdb->get_results($wpdb->prepare(
            "SELECT gateway,
                    COUNT(*) as total_attempts,
                    COUNT(CASE WHEN metric_name = 'payment_completed' THEN 1 END) as successful_payments,
                    AVG(revenue) as avg_revenue
             FROM {$this->table_name} 
             WHERE metric_type = 'payment' AND metric_date BETWEEN %s AND %s 
             GROUP BY gateway",
            $start_date,
            $end_date
        ), ARRAY_A);

        return array(
            'funnel' => $funnel_data[0] ?? array(),
            'gateway_performance' => $gateway_conversions
        );
    }

    /**
     * Get revenue attribution data
     *
     * @since 1.0.0
     * @param string $date_range Date range filter
     * @return array Revenue attribution data
     */
    public function get_revenue_attribution($date_range)
    {
        global $wpdb;

        $date_filter = $this->get_date_filter($date_range);
        $start_date = $date_filter['start'];
        $end_date = $date_filter['end'];

        // Revenue by source
        $revenue_by_source = $wpdb->get_results($wpdb->prepare(
            "SELECT source_type,
                    SUM(revenue) as total_revenue,
                    COUNT(*) as transaction_count,
                    AVG(revenue) as avg_transaction_value
             FROM {$this->table_name} 
             WHERE metric_type = 'payment' AND metric_date BETWEEN %s AND %s 
             GROUP BY source_type 
             ORDER BY total_revenue DESC",
            $start_date,
            $end_date
        ), ARRAY_A);

        // Revenue by gateway
        $revenue_by_gateway = $wpdb->get_results($wpdb->prepare(
            "SELECT gateway,
                    SUM(revenue) as total_revenue,
                    COUNT(*) as transaction_count
             FROM {$this->table_name} 
             WHERE metric_type = 'payment' AND metric_date BETWEEN %s AND %s 
             GROUP BY gateway 
             ORDER BY total_revenue DESC",
            $start_date,
            $end_date
        ), ARRAY_A);

        return array(
            'by_source' => $revenue_by_source,
            'by_gateway' => $revenue_by_gateway
        );
    }

    /**
     * Get performance metrics
     *
     * @since 1.0.0
     * @return array Performance metrics
     */
    public function get_performance_metrics()
    {
        global $wpdb;

        // Get current vs previous period comparison
        $current_week = $this->get_date_filter('7days');
        $previous_week = array(
            'start' => date('Y-m-d', strtotime('-14 days')),
            'end' => date('Y-m-d', strtotime('-8 days'))
        );

        $current_metrics = $this->get_period_metrics($current_week);
        $previous_metrics = $this->get_period_metrics($previous_week);

        return array(
            'current_period' => $current_metrics,
            'previous_period' => $previous_metrics,
            'growth_rates' => $this->calculate_growth_rates($current_metrics, $previous_metrics)
        );
    }

    /**
     * Track payment conversion
     *
     * @since 1.0.0
     * @param array  $payment_data Payment data
     * @param string $gateway Gateway used
     */
    public function track_payment_conversion($payment_data, $gateway)
    {
        global $wpdb;

        $data = array(
            'metric_type' => 'payment',
            'metric_name' => 'payment_completed',
            'metric_value' => 1,
            'metric_date' => current_time('Y-m-d'),
            'source_type' => 'whatsapp',
            'contact_id' => $payment_data['contact_id'] ?? null,
            'payment_id' => $payment_data['reference'] ?? '',
            'gateway' => $gateway,
            'revenue' => ($payment_data['amount'] ?? 0) / 100, // Convert from kobo to naira
            'currency' => $payment_data['currency'] ?? 'NGN',
            'meta_data' => wp_json_encode($payment_data)
        );

        $wpdb->insert($this->table_name, $data);

        // Also track as conversion
        $conversion_data = $data;
        $conversion_data['metric_type'] = 'conversion';
        $conversion_data['metric_name'] = 'whatsapp_to_payment';

        $wpdb->insert($this->table_name, $conversion_data);

        chatshop_log('Payment conversion tracked', 'info', $data);
    }

    /**
     * Track contact interaction
     *
     * @since 1.0.0
     * @param int    $contact_id Contact ID
     * @param string $interaction_type Type of interaction
     * @param array  $meta Additional data
     */
    public function track_contact_interaction($contact_id, $interaction_type, $meta = array())
    {
        global $wpdb;

        $data = array(
            'metric_type' => 'interaction',
            'metric_name' => $interaction_type,
            'metric_value' => 1,
            'metric_date' => current_time('Y-m-d'),
            'source_type' => 'whatsapp',
            'contact_id' => $contact_id,
            'meta_data' => wp_json_encode($meta)
        );

        $wpdb->insert($this->table_name, $data);
    }

    /**
     * Get date filter array
     *
     * @since 1.0.0
     * @param string $range Date range string
     * @return array Date filter with start and end dates
     */
    private function get_date_filter($range)
    {
        $end_date = current_time('Y-m-d');

        switch ($range) {
            case '7days':
                $start_date = date('Y-m-d', strtotime('-7 days'));
                break;
            case '30days':
                $start_date = date('Y-m-d', strtotime('-30 days'));
                break;
            case '90days':
                $start_date = date('Y-m-d', strtotime('-90 days'));
                break;
            case '365days':
                $start_date = date('Y-m-d', strtotime('-365 days'));
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
     * Get metrics for a specific period
     *
     * @since 1.0.0
     * @param array $date_filter Date filter
     * @return array Period metrics
     */
    private function get_period_metrics($date_filter)
    {
        global $wpdb;

        $revenue = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(revenue) FROM {$this->table_name} 
             WHERE metric_type = 'payment' AND metric_date BETWEEN %s AND %s",
            $date_filter['start'],
            $date_filter['end']
        ));

        $conversions = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} 
             WHERE metric_type = 'conversion' AND metric_date BETWEEN %s AND %s",
            $date_filter['start'],
            $date_filter['end']
        ));

        return array(
            'revenue' => floatval($revenue ?? 0),
            'conversions' => intval($conversions ?? 0)
        );
    }

    /**
     * Calculate growth rates between periods
     *
     * @since 1.0.0
     * @param array $current Current period metrics
     * @param array $previous Previous period metrics
     * @return array Growth rates
     */
    private function calculate_growth_rates($current, $previous)
    {
        $revenue_growth = $previous['revenue'] > 0
            ? round((($current['revenue'] - $previous['revenue']) / $previous['revenue']) * 100, 2)
            : 0;

        $conversion_growth = $previous['conversions'] > 0
            ? round((($current['conversions'] - $previous['conversions']) / $previous['conversions']) * 100, 2)
            : 0;

        return array(
            'revenue_growth' => $revenue_growth,
            'conversion_growth' => $conversion_growth
        );
    }

    /**
     * Aggregate daily analytics (cron job)
     *
     * @since 1.0.0
     */
    public function aggregate_daily_analytics()
    {
        // Clean up old analytics data (older than 2 years)
        global $wpdb;

        $cutoff_date = date('Y-m-d', strtotime('-2 years'));

        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table_name} WHERE metric_date < %s",
            $cutoff_date
        ));

        if ($deleted) {
            chatshop_log("Cleaned up {$deleted} old analytics records", 'info');
        }
    }

    /**
     * Get table name
     *
     * @since 1.0.0
     * @return string Table name
     */
    public function get_table_name()
    {
        return $this->table_name;
    }
}
