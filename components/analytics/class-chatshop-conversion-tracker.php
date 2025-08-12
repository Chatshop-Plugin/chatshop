<?php

/**
 * Analytics Conversion Tracker Class
 *
 * File: components/analytics/class-chatshop-conversion-tracker.php
 * 
 * Tracks conversion funnels and attribution for WhatsApp-to-payment flows.
 * Provides detailed analysis of customer journey and conversion optimization.
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
 * ChatShop Conversion Tracker Class
 *
 * Handles conversion funnel analysis, attribution modeling,
 * and customer journey tracking for analytics.
 *
 * @since 1.0.0
 */
class ChatShop_Conversion_Tracker
{
    /**
     * Conversion table name
     *
     * @var string
     * @since 1.0.0
     */
    private $conversion_table;

    /**
     * Analytics table name
     *
     * @var string
     * @since 1.0.0
     */
    private $analytics_table;

    /**
     * Contact table name
     *
     * @var string
     * @since 1.0.0
     */
    private $contact_table;

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        global $wpdb;

        $this->conversion_table = $wpdb->prefix . 'chatshop_conversions';
        $this->analytics_table = $wpdb->prefix . 'chatshop_analytics';
        $this->contact_table = $wpdb->prefix . 'chatshop_contacts';
    }

    /**
     * Get conversion funnel data
     *
     * @since 1.0.0
     * @param string $date_range Date range (7days, 30days, 90days)
     * @param string $source_type Source type filter
     * @return array Funnel data
     */
    public function get_conversion_funnel($date_range, $source_type = '')
    {
        if (!chatshop_is_premium()) {
            return array();
        }

        $date_filter = $this->get_date_filter($date_range);
        global $wpdb;

        // Step 1: Messages sent
        $messages_sent = $this->get_funnel_step_count('whatsapp', 'message_sent', $date_filter, $source_type);

        // Step 2: Messages delivered
        $messages_delivered = $this->get_funnel_step_count('whatsapp', 'message_delivered', $date_filter, $source_type);

        // Step 3: Contact interactions (clicks, replies)
        $interactions = $this->get_funnel_step_count('interaction', array('click', 'reply', 'link_click'), $date_filter, $source_type);

        // Step 4: Payment attempts
        $payment_attempts = $this->get_funnel_step_count('payment', array('conversion', 'failure'), $date_filter, $source_type);

        // Step 5: Successful conversions
        $conversions = $this->get_funnel_step_count('payment', 'conversion', $date_filter, $source_type);

        return array(
            'funnel_steps' => array(
                array(
                    'step' => 'messages_sent',
                    'label' => __('Messages Sent', 'chatshop'),
                    'count' => $messages_sent,
                    'percentage' => 100
                ),
                array(
                    'step' => 'messages_delivered',
                    'label' => __('Messages Delivered', 'chatshop'),
                    'count' => $messages_delivered,
                    'percentage' => $messages_sent > 0 ? round(($messages_delivered / $messages_sent) * 100, 2) : 0
                ),
                array(
                    'step' => 'interactions',
                    'label' => __('Customer Interactions', 'chatshop'),
                    'count' => $interactions,
                    'percentage' => $messages_delivered > 0 ? round(($interactions / $messages_delivered) * 100, 2) : 0
                ),
                array(
                    'step' => 'payment_attempts',
                    'label' => __('Payment Attempts', 'chatshop'),
                    'count' => $payment_attempts,
                    'percentage' => $interactions > 0 ? round(($payment_attempts / $interactions) * 100, 2) : 0
                ),
                array(
                    'step' => 'conversions',
                    'label' => __('Successful Payments', 'chatshop'),
                    'count' => $conversions,
                    'percentage' => $payment_attempts > 0 ? round(($conversions / $payment_attempts) * 100, 2) : 0
                )
            ),
            'conversion_rate' => $messages_sent > 0 ? round(($conversions / $messages_sent) * 100, 4) : 0,
            'success_rate' => $payment_attempts > 0 ? round(($conversions / $payment_attempts) * 100, 2) : 0
        );
    }

    /**
     * Get attribution analysis
     *
     * @since 1.0.0
     * @param string $date_range Date range
     * @return array Attribution data
     */
    public function get_attribution_analysis($date_range)
    {
        if (!chatshop_is_premium()) {
            return array();
        }

        global $wpdb;
        $date_filter = $this->get_date_filter($date_range);

        // Revenue by source type
        $revenue_by_source = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT 
                    source_type,
                    COUNT(*) as conversion_count,
                    SUM(conversion_value) as total_revenue,
                    AVG(conversion_value) as avg_revenue
                 FROM {$this->conversion_table} 
                 WHERE DATE(conversion_date) >= %s
                 GROUP BY source_type
                 ORDER BY total_revenue DESC",
                $date_filter
            ),
            ARRAY_A
        );

        // Revenue by gateway
        $revenue_by_gateway = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT 
                    gateway,
                    COUNT(*) as conversion_count,
                    SUM(conversion_value) as total_revenue,
                    AVG(conversion_value) as avg_revenue
                 FROM {$this->conversion_table} 
                 WHERE DATE(conversion_date) >= %s
                 GROUP BY gateway
                 ORDER BY total_revenue DESC",
                $date_filter
            ),
            ARRAY_A
        );

        // Time to conversion analysis
        $time_to_conversion = $this->get_time_to_conversion_analysis($date_range);

        return array(
            'revenue_by_source' => $revenue_by_source,
            'revenue_by_gateway' => $revenue_by_gateway,
            'time_to_conversion' => $time_to_conversion
        );
    }

    /**
     * Get customer lifetime value analysis
     *
     * @since 1.0.0
     * @param string $date_range Date range
     * @return array CLV data
     */
    public function get_customer_lifetime_value($date_range)
    {
        if (!chatshop_is_premium()) {
            return array();
        }

        global $wpdb;
        $date_filter = $this->get_date_filter($date_range);

        $clv_data = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT 
                    c.contact_id,
                    COUNT(c.payment_id) as total_payments,
                    SUM(c.conversion_value) as total_revenue,
                    AVG(c.conversion_value) as avg_order_value,
                    MIN(c.conversion_date) as first_purchase,
                    MAX(c.conversion_date) as last_purchase,
                    DATEDIFF(MAX(c.conversion_date), MIN(c.conversion_date)) as customer_lifespan_days
                 FROM {$this->conversion_table} c
                 WHERE DATE(c.conversion_date) >= %s AND c.contact_id IS NOT NULL
                 GROUP BY c.contact_id
                 HAVING total_payments > 0
                 ORDER BY total_revenue DESC",
                $date_filter
            ),
            ARRAY_A
        );

        // Calculate CLV metrics
        $total_customers = count($clv_data);
        $total_revenue = array_sum(array_column($clv_data, 'total_revenue'));
        $avg_clv = $total_customers > 0 ? $total_revenue / $total_customers : 0;

        return array(
            'customer_data' => $clv_data,
            'metrics' => array(
                'total_customers' => $total_customers,
                'total_revenue' => $total_revenue,
                'average_clv' => round($avg_clv, 2),
                'repeat_customer_rate' => $this->calculate_repeat_customer_rate($clv_data)
            )
        );
    }

    /**
     * Get conversion trends over time
     *
     * @since 1.0.0
     * @param string $date_range Date range
     * @param string $group_by Grouping (day, week, month)
     * @return array Trend data
     */
    public function get_conversion_trends($date_range, $group_by = 'day')
    {
        if (!chatshop_is_premium()) {
            return array();
        }

        global $wpdb;
        $date_filter = $this->get_date_filter($date_range);

        $date_format = $this->get_mysql_date_format($group_by);

        $trends = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT 
                    {$date_format} as period,
                    COUNT(*) as conversions,
                    SUM(conversion_value) as revenue,
                    AVG(conversion_value) as avg_order_value,
                    COUNT(DISTINCT contact_id) as unique_customers
                 FROM {$this->conversion_table} 
                 WHERE DATE(conversion_date) >= %s
                 GROUP BY period
                 ORDER BY period ASC",
                $date_filter
            ),
            ARRAY_A
        );

        return $trends;
    }

    /**
     * Get top performing campaigns
     *
     * @since 1.0.0
     * @param string $date_range Date range
     * @param int $limit Number of campaigns to return
     * @return array Campaign performance data
     */
    public function get_top_campaigns($date_range, $limit = 10)
    {
        if (!chatshop_is_premium()) {
            return array();
        }

        global $wpdb;
        $date_filter = $this->get_date_filter($date_range);

        // Get campaign performance from analytics and conversion data
        $campaigns = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT 
                    a.source_id as campaign_id,
                    SUM(CASE WHEN a.metric_name = 'sent' THEN a.metric_value ELSE 0 END) as messages_sent,
                    SUM(CASE WHEN a.metric_name = 'click' THEN a.metric_value ELSE 0 END) as clicks,
                    COUNT(c.payment_id) as conversions,
                    SUM(c.conversion_value) as revenue
                 FROM {$this->analytics_table} a
                 LEFT JOIN {$this->conversion_table} c ON a.source_id = c.source_id
                 WHERE a.metric_type = 'campaign' 
                   AND DATE(a.metric_date) >= %s
                   AND a.source_id IS NOT NULL
                 GROUP BY a.source_id
                 ORDER BY revenue DESC, conversions DESC
                 LIMIT %d",
                $date_filter,
                $limit
            ),
            ARRAY_A
        );

        // Calculate performance metrics
        foreach ($campaigns as &$campaign) {
            $campaign['click_rate'] = $campaign['messages_sent'] > 0
                ? round(($campaign['clicks'] / $campaign['messages_sent']) * 100, 2)
                : 0;
            $campaign['conversion_rate'] = $campaign['clicks'] > 0
                ? round(($campaign['conversions'] / $campaign['clicks']) * 100, 2)
                : 0;
            $campaign['roi'] = $campaign['conversions'] > 0
                ? round($campaign['revenue'] / $campaign['conversions'], 2)
                : 0;
        }

        return $campaigns;
    }

    /**
     * Get funnel step count
     *
     * @since 1.0.0
     * @param string $metric_type Metric type
     * @param string|array $metric_name Metric name(s)
     * @param string $date_filter Date filter
     * @param string $source_type Source type filter
     * @return int Count
     */
    private function get_funnel_step_count($metric_type, $metric_name, $date_filter, $source_type = '')
    {
        global $wpdb;

        $where_clause = "WHERE metric_type = %s AND metric_date >= %s";
        $params = array($metric_type, $date_filter);

        if (is_array($metric_name)) {
            $placeholders = str_repeat(',%s', count($metric_name) - 1);
            $where_clause .= " AND metric_name IN (%s{$placeholders})";
            $params = array_merge($params, $metric_name);
        } else {
            $where_clause .= " AND metric_name = %s";
            $params[] = $metric_name;
        }

        if (!empty($source_type)) {
            $where_clause .= " AND source_type = %s";
            $params[] = $source_type;
        }

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(metric_value) FROM {$this->analytics_table} {$where_clause}",
                $params
            )
        );

        return intval($count);
    }

    /**
     * Get date filter based on range
     *
     * @since 1.0.0
     * @param string $date_range Date range
     * @return string Date filter
     */
    private function get_date_filter($date_range)
    {
        $days = 7; // default

        switch ($date_range) {
            case '30days':
                $days = 30;
                break;
            case '90days':
                $days = 90;
                break;
            case '1year':
                $days = 365;
                break;
            default:
                $days = 7;
        }

        return date('Y-m-d', strtotime("-{$days} days"));
    }

    /**
     * Get MySQL date format for grouping
     *
     * @since 1.0.0
     * @param string $group_by Grouping type
     * @return string MySQL date format
     */
    private function get_mysql_date_format($group_by)
    {
        switch ($group_by) {
            case 'week':
                return "DATE_FORMAT(conversion_date, '%Y-%u')";
            case 'month':
                return "DATE_FORMAT(conversion_date, '%Y-%m')";
            default:
                return "DATE(conversion_date)";
        }
    }

    /**
     * Get time to conversion analysis
     *
     * @since 1.0.0
     * @param string $date_range Date range
     * @return array Time to conversion data
     */
    private function get_time_to_conversion_analysis($date_range)
    {
        global $wpdb;
        $date_filter = $this->get_date_filter($date_range);

        // Get first interaction and conversion time for each contact
        $time_data = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT 
                    c.contact_id,
                    c.conversion_date,
                    (SELECT MIN(a.created_at) 
                     FROM {$this->analytics_table} a 
                     WHERE a.contact_id = c.contact_id 
                       AND a.metric_type = 'whatsapp'
                       AND a.created_at <= c.conversion_date
                    ) as first_interaction
                 FROM {$this->conversion_table} c
                 WHERE DATE(c.conversion_date) >= %s 
                   AND c.contact_id IS NOT NULL",
                $date_filter
            ),
            ARRAY_A
        );

        $conversion_times = array();
        foreach ($time_data as $data) {
            if ($data['first_interaction']) {
                $diff = strtotime($data['conversion_date']) - strtotime($data['first_interaction']);
                $hours = round($diff / 3600, 2);
                $conversion_times[] = $hours;
            }
        }

        if (empty($conversion_times)) {
            return array(
                'avg_time_hours' => 0,
                'median_time_hours' => 0,
                'distribution' => array()
            );
        }

        sort($conversion_times);
        $count = count($conversion_times);
        $median = $count % 2 === 0
            ? ($conversion_times[$count / 2 - 1] + $conversion_times[$count / 2]) / 2
            : $conversion_times[floor($count / 2)];

        // Create distribution buckets
        $distribution = array(
            '0-1h' => 0,
            '1-6h' => 0,
            '6-24h' => 0,
            '1-3d' => 0,
            '3-7d' => 0,
            '7d+' => 0
        );

        foreach ($conversion_times as $hours) {
            if ($hours <= 1) {
                $distribution['0-1h']++;
            } elseif ($hours <= 6) {
                $distribution['1-6h']++;
            } elseif ($hours <= 24) {
                $distribution['6-24h']++;
            } elseif ($hours <= 72) {
                $distribution['1-3d']++;
            } elseif ($hours <= 168) {
                $distribution['3-7d']++;
            } else {
                $distribution['7d+']++;
            }
        }

        return array(
            'avg_time_hours' => round(array_sum($conversion_times) / $count, 2),
            'median_time_hours' => round($median, 2),
            'distribution' => $distribution
        );
    }

    /**
     * Calculate repeat customer rate
     *
     * @since 1.0.0
     * @param array $clv_data Customer lifetime value data
     * @return float Repeat customer rate
     */
    private function calculate_repeat_customer_rate($clv_data)
    {
        $total_customers = count($clv_data);
        $repeat_customers = 0;

        foreach ($clv_data as $customer) {
            if ($customer['total_payments'] > 1) {
                $repeat_customers++;
            }
        }

        return $total_customers > 0 ? round(($repeat_customers / $total_customers) * 100, 2) : 0;
    }
}
