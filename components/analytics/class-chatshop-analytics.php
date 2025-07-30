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

        // Set premium requirement
        $this->premium_only = true;

        // Initialize hooks
        $this->init_hooks();

        // Log initialization
        $this->log_info('Analytics component initialized');
    }

    /**
     * Initialize hooks
     *
     * @since 1.0.0
     */
    private function init_hooks()
    {
        // Track payment events
        add_action('chatshop_payment_completed', array($this, 'track_payment_conversion'), 10, 3);
        add_action('chatshop_payment_failed', array($this, 'track_payment_failure'), 10, 2);

        // Track WhatsApp interactions
        add_action('chatshop_whatsapp_message_sent', array($this, 'track_whatsapp_interaction'), 10, 2);
        add_action('chatshop_whatsapp_link_clicked', array($this, 'track_link_click'), 10, 2);

        // Admin hooks
        if (is_admin()) {
            add_action('chatshop_dashboard_widget', array($this, 'render_dashboard_widget'));
            add_filter('chatshop_admin_reports', array($this, 'add_analytics_reports'));
        }

        // AJAX handlers
        add_action('wp_ajax_chatshop_get_analytics_data', array($this, 'ajax_get_analytics_data'));
        add_action('wp_ajax_chatshop_export_analytics', array($this, 'ajax_export_analytics'));

        // Cron for data cleanup
        add_action('chatshop_analytics_cleanup', array($this, 'cleanup_old_data'));
    }

    /**
     * Check if component should be loaded
     *
     * @since 1.0.0
     * @return bool
     */
    public function should_load()
    {
        // Check if analytics is enabled in settings
        $enabled = chatshop_get_option('analytics', 'enabled', true);

        // Check premium status
        $is_premium = chatshop_is_premium();

        return $enabled && $is_premium;
    }

    /**
     * Get table name
     *
     * @since 1.0.0
     * @return string
     */
    public function get_table_name()
    {
        return $this->table_name;
    }

    /**
     * Get analytics data for specified period
     *
     * @since 1.0.0
     * @param string $period Time period (7_days, 30_days, 3_months, 1_year)
     * @param string $type Data type (overview, conversions, revenue, etc)
     * @return array Analytics data
     */
    public function get_analytics_data($period = '30_days', $type = 'overview')
    {
        // Check cache first
        $cache_key = "chatshop_analytics_{$period}_{$type}";
        $cached_data = get_transient($cache_key);

        if ($cached_data !== false) {
            return $cached_data;
        }

        global $wpdb;

        // Calculate date range
        $date_range = $this->get_date_range($period);
        $start_date = $date_range['start'];
        $end_date = $date_range['end'];

        $data = array();

        switch ($type) {
            case 'overview':
                $data = $this->get_overview_data($start_date, $end_date);
                break;

            case 'conversions':
                $data = $this->get_conversion_data($start_date, $end_date);
                break;

            case 'revenue':
                $data = $this->get_revenue_data($start_date, $end_date);
                break;

            case 'gateway_performance':
                $data = $this->get_gateway_performance($start_date, $end_date);
                break;

            default:
                $data = $this->get_overview_data($start_date, $end_date);
        }

        // Cache the data
        set_transient($cache_key, $data, $this->cache_duration);

        return $data;
    }

    /**
     * Get overview analytics data
     *
     * @since 1.0.0
     * @param string $start_date Start date
     * @param string $end_date End date
     * @return array Overview data
     */
    private function get_overview_data($start_date, $end_date)
    {
        global $wpdb;

        // Get total interactions
        $interactions = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} 
             WHERE metric_type = 'interaction' 
             AND metric_date BETWEEN %s AND %s",
            $start_date,
            $end_date
        ));

        // Get total payments
        $payments = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} 
             WHERE metric_type = 'payment' 
             AND metric_name = 'completed'
             AND metric_date BETWEEN %s AND %s",
            $start_date,
            $end_date
        ));

        // Get total revenue
        $revenue = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(revenue) FROM {$this->table_name} 
             WHERE metric_type = 'payment' 
             AND metric_name = 'completed'
             AND metric_date BETWEEN %s AND %s",
            $start_date,
            $end_date
        ));

        // Get conversion rate
        $conversion_rate = $interactions > 0 ? ($payments / $interactions) * 100 : 0;

        // Get daily trends
        $daily_trends = $wpdb->get_results($wpdb->prepare(
            "SELECT metric_date, 
                    COUNT(CASE WHEN metric_type = 'interaction' THEN 1 END) as interactions,
                    COUNT(CASE WHEN metric_type = 'payment' AND metric_name = 'completed' THEN 1 END) as payments,
                    SUM(CASE WHEN metric_type = 'payment' AND metric_name = 'completed' THEN revenue ELSE 0 END) as revenue
             FROM {$this->table_name}
             WHERE metric_date BETWEEN %s AND %s
             GROUP BY metric_date
             ORDER BY metric_date ASC",
            $start_date,
            $end_date
        ), ARRAY_A);

        return array(
            'totals' => array(
                'interactions' => intval($interactions),
                'payments' => intval($payments),
                'revenue' => floatval($revenue),
                'conversion_rate' => round($conversion_rate, 2)
            ),
            'trends' => $daily_trends,
            'period' => array(
                'start' => $start_date,
                'end' => $end_date
            )
        );
    }

    /**
     * Get conversion funnel data
     *
     * @since 1.0.0
     * @param string $start_date Start date
     * @param string $end_date End date
     * @return array Conversion funnel data
     */
    private function get_conversion_data($start_date, $end_date)
    {
        global $wpdb;

        $funnel_data = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                COUNT(DISTINCT CASE WHEN metric_type = 'interaction' THEN contact_id END) as total_interactions,
                COUNT(DISTINCT CASE WHEN metric_name = 'link_clicked' THEN contact_id END) as link_clicks,
                COUNT(DISTINCT CASE WHEN metric_name = 'payment_initiated' THEN contact_id END) as payment_initiated,
                COUNT(DISTINCT CASE WHEN metric_name = 'completed' THEN contact_id END) as completed
             FROM {$this->table_name}
             WHERE metric_date BETWEEN %s AND %s",
            $start_date,
            $end_date
        ), ARRAY_A);

        return array(
            'funnel' => $funnel_data[0],
            'conversion_rates' => $this->calculate_conversion_rates($funnel_data[0])
        );
    }

    /**
     * Track payment conversion
     *
     * @since 1.0.0
     * @param string $payment_id Payment ID
     * @param float $amount Payment amount
     * @param array $payment_data Payment details
     */
    public function track_payment_conversion($payment_id, $amount, $payment_data)
    {
        $this->track_metric('payment', 'completed', 1, array(
            'payment_id' => $payment_id,
            'revenue' => $amount,
            'currency' => isset($payment_data['currency']) ? $payment_data['currency'] : 'NGN',
            'gateway' => isset($payment_data['gateway']) ? $payment_data['gateway'] : '',
            'contact_id' => isset($payment_data['contact_id']) ? $payment_data['contact_id'] : null,
            'source_type' => 'whatsapp'
        ));
    }

    /**
     * Track metric
     *
     * @since 1.0.0
     * @param string $type Metric type
     * @param string $name Metric name
     * @param mixed $value Metric value
     * @param array $meta Additional metadata
     * @return bool Success status
     */
    public function track_metric($type, $name, $value = 1, $meta = array())
    {
        global $wpdb;

        $data = array(
            'metric_type' => sanitize_text_field($type),
            'metric_name' => sanitize_text_field($name),
            'metric_value' => floatval($value),
            'metric_date' => current_time('Y-m-d'),
            'source_type' => isset($meta['source_type']) ? sanitize_text_field($meta['source_type']) : 'whatsapp',
            'source_id' => isset($meta['source_id']) ? sanitize_text_field($meta['source_id']) : '',
            'contact_id' => isset($meta['contact_id']) ? absint($meta['contact_id']) : null,
            'payment_id' => isset($meta['payment_id']) ? sanitize_text_field($meta['payment_id']) : '',
            'gateway' => isset($meta['gateway']) ? sanitize_text_field($meta['gateway']) : '',
            'revenue' => isset($meta['revenue']) ? floatval($meta['revenue']) : 0,
            'currency' => isset($meta['currency']) ? sanitize_text_field($meta['currency']) : 'NGN',
            'meta_data' => wp_json_encode($meta),
            'created_at' => current_time('mysql')
        );

        $result = $wpdb->insert($this->table_name, $data);

        if ($result === false) {
            $this->log_error('Failed to track metric: ' . $wpdb->last_error);
            return false;
        }

        // Clear related caches
        $this->clear_analytics_cache();

        return true;
    }

    /**
     * Get date range for period
     *
     * @since 1.0.0
     * @param string $period Period identifier
     * @return array Start and end dates
     */
    private function get_date_range($period)
    {
        $end_date = current_time('Y-m-d');

        switch ($period) {
            case '7_days':
                $start_date = date('Y-m-d', strtotime('-7 days'));
                break;

            case '30_days':
                $start_date = date('Y-m-d', strtotime('-30 days'));
                break;

            case '3_months':
                $start_date = date('Y-m-d', strtotime('-3 months'));
                break;

            case '1_year':
                $start_date = date('Y-m-d', strtotime('-1 year'));
                break;

            default:
                $start_date = date('Y-m-d', strtotime('-30 days'));
        }

        return array(
            'start' => $start_date,
            'end' => $end_date
        );
    }

    /**
     * Clear analytics cache
     *
     * @since 1.0.0
     */
    private function clear_analytics_cache()
    {
        global $wpdb;

        // Delete all analytics transients
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_chatshop_analytics_%' 
             OR option_name LIKE '_transient_timeout_chatshop_analytics_%'"
        );
    }

    /**
     * Get component status
     *
     * @since 1.0.0
     * @return string Component status
     */
    public function get_status()
    {
        return 'active';
    }

    /**
     * Activate component
     *
     * @since 1.0.0
     */
    public function activate()
    {
        $this->create_tables();
        $this->schedule_cron_jobs();
        parent::activate();
    }

    /**
     * Deactivate component
     *
     * @since 1.0.0
     */
    public function deactivate()
    {
        $this->unschedule_cron_jobs();
        parent::deactivate();
    }

    /**
     * Create database tables
     *
     * @since 1.0.0
     */
    private function create_tables()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            metric_type varchar(50) NOT NULL,
            metric_name varchar(100) NOT NULL,
            metric_value float DEFAULT 1,
            metric_date date NOT NULL,
            source_type varchar(50) DEFAULT 'whatsapp',
            source_id varchar(100) DEFAULT '',
            contact_id bigint(20) DEFAULT NULL,
            payment_id varchar(100) DEFAULT '',
            gateway varchar(50) DEFAULT '',
            revenue decimal(10,2) DEFAULT 0,
            currency varchar(10) DEFAULT 'NGN',
            meta_data longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_metric_type (metric_type),
            KEY idx_metric_date (metric_date),
            KEY idx_contact_id (contact_id),
            KEY idx_payment_id (payment_id),
            KEY idx_date_type (metric_date, metric_type)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Schedule cron jobs
     *
     * @since 1.0.0
     */
    private function schedule_cron_jobs()
    {
        if (!wp_next_scheduled('chatshop_analytics_cleanup')) {
            wp_schedule_event(time(), 'daily', 'chatshop_analytics_cleanup');
        }
    }

    /**
     * Unschedule cron jobs
     *
     * @since 1.0.0
     */
    private function unschedule_cron_jobs()
    {
        wp_clear_scheduled_hook('chatshop_analytics_cleanup');
    }

    /**
     * Cleanup old analytics data
     *
     * @since 1.0.0
     */
    public function cleanup_old_data()
    {
        global $wpdb;

        // Keep data for 1 year by default
        $retention_days = apply_filters('chatshop_analytics_retention_days', 365);
        $cutoff_date = date('Y-m-d', strtotime("-{$retention_days} days"));

        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table_name} WHERE metric_date < %s",
            $cutoff_date
        ));

        if ($deleted > 0) {
            $this->log_info("Cleaned up {$deleted} old analytics records");
        }
    }
}
