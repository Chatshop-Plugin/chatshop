<?php

/**
 * Analytics Component Class - COMPLETE IMPLEMENTATION
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

// Prevent class redeclaration
if (class_exists('ChatShop\\ChatShop_Analytics')) {
    return;
}

/**
 * ChatShop Analytics Class - COMPLETE VERSION
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
     * Component status
     *
     * @var string
     * @since 1.0.0
     */
    private $status = 'inactive';

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

        // Set component as enabled
        $this->enabled = true;
        $this->status = 'active';

        // Initialize hooks and database
        $this->init_hooks();
        $this->init_database();

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
        add_action('chatshop_contact_interaction', array($this, 'track_contact_interaction'), 10, 2);

        // Hook into WhatsApp message sending
        add_action('chatshop_whatsapp_message_sent', array($this, 'track_whatsapp_message'), 10, 3);

        // Admin hooks
        if (is_admin()) {
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        }
    }

    /**
     * Initialize database table
     *
     * @since 1.0.0
     */
    private function init_database()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_type varchar(50) NOT NULL,
            event_subtype varchar(50) DEFAULT '',
            user_id bigint(20) unsigned DEFAULT NULL,
            contact_phone varchar(20) DEFAULT '',
            amount decimal(10,2) DEFAULT 0.00,
            currency varchar(10) DEFAULT 'NGN',
            gateway varchar(50) DEFAULT '',
            source varchar(50) DEFAULT 'whatsapp',
            reference varchar(100) DEFAULT '',
            metadata text DEFAULT '',
            status varchar(20) DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY event_type (event_type),
            KEY event_subtype (event_subtype),
            KEY contact_phone (contact_phone),
            KEY gateway (gateway),
            KEY source (source),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $result = dbDelta($sql);

        if (!empty($wpdb->last_error)) {
            chatshop_log('Analytics database table creation error: ' . $wpdb->last_error, 'error');
        } else {
            chatshop_log('Analytics database table created/updated successfully', 'info');
        }
    }

    /**
     * Component activation
     *
     * @since 1.0.0
     * @return bool Activation result
     */
    protected function do_activation()
    {
        $this->init_database();
        return true;
    }

    /**
     * Component deactivation
     *
     * @since 1.0.0
     * @return bool Deactivation result
     */
    protected function do_deactivation()
    {
        // Keep data on deactivation, only remove on uninstall
        return true;
    }

    /**
     * Get component status
     *
     * @since 1.0.0
     * @return string Component status
     */
    public function get_status()
    {
        return $this->status;
    }

    /**
     * Check if component is enabled
     *
     * @since 1.0.0
     * @return bool Enabled status
     */
    public function is_enabled()
    {
        return $this->enabled && $this->status === 'active';
    }

    /**
     * Track payment conversion
     *
     * @since 1.0.0
     * @param array $payment_data Payment information
     * @param string $gateway Gateway used
     */
    public function track_payment_conversion($payment_data, $gateway = '')
    {
        global $wpdb;

        $data = array(
            'event_type' => 'payment',
            'event_subtype' => 'completed',
            'amount' => floatval($payment_data['amount'] ?? 0),
            'currency' => sanitize_text_field($payment_data['currency'] ?? 'NGN'),
            'gateway' => sanitize_text_field($gateway),
            'source' => sanitize_text_field($payment_data['source'] ?? 'whatsapp'),
            'reference' => sanitize_text_field($payment_data['reference'] ?? ''),
            'contact_phone' => sanitize_text_field($payment_data['phone'] ?? ''),
            'metadata' => wp_json_encode($payment_data),
            'status' => 'completed',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );

        $result = $wpdb->insert($this->table_name, $data);

        if ($result === false) {
            chatshop_log('Failed to track payment conversion: ' . $wpdb->last_error, 'error');
        } else {
            chatshop_log('Payment conversion tracked successfully', 'info');
        }
    }

    /**
     * Track contact interaction
     *
     * @since 1.0.0
     * @param string $phone Contact phone
     * @param array $interaction_data Interaction information
     */
    public function track_contact_interaction($phone, $interaction_data)
    {
        global $wpdb;

        $data = array(
            'event_type' => 'contact_interaction',
            'event_subtype' => sanitize_text_field($interaction_data['type'] ?? 'general'),
            'contact_phone' => sanitize_text_field($phone),
            'source' => 'whatsapp',
            'metadata' => wp_json_encode($interaction_data),
            'status' => 'completed',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );

        $wpdb->insert($this->table_name, $data);
    }

    /**
     * Track WhatsApp message
     *
     * @since 1.0.0
     * @param string $phone Recipient phone
     * @param string $message Message content
     * @param array $result Send result
     */
    public function track_whatsapp_message($phone, $message, $result)
    {
        global $wpdb;

        $data = array(
            'event_type' => 'whatsapp_message',
            'event_subtype' => $result['success'] ? 'sent' : 'failed',
            'contact_phone' => sanitize_text_field($phone),
            'source' => 'whatsapp',
            'metadata' => wp_json_encode(array(
                'message' => substr($message, 0, 100), // Store first 100 chars
                'result' => $result
            )),
            'status' => $result['success'] ? 'completed' : 'failed',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );

        $wpdb->insert($this->table_name, $data);
    }

    /**
     * Get analytics data for dashboard
     *
     * @since 1.0.0
     * @param string $date_range Date range for analytics
     * @param string $type Type of analytics data
     * @return array Analytics data
     */
    public function get_analytics_data($date_range = '30_days', $type = 'overview')
    {
        global $wpdb;

        $cache_key = "chatshop_analytics_{$date_range}_{$type}";
        $cached_data = get_transient($cache_key);

        if ($cached_data !== false) {
            return $cached_data;
        }

        // Calculate date range
        $dates = $this->get_date_range($date_range);

        $data = array();

        switch ($type) {
            case 'overview':
                $data = $this->get_overview_data($dates);
                break;
            case 'conversions':
                $data = $this->get_conversion_data($dates);
                break;
            case 'revenue':
                $data = $this->get_revenue_data($dates);
                break;
            case 'performance':
                $data = $this->get_performance_data($dates);
                break;
            default:
                $data = $this->get_overview_data($dates);
        }

        // Cache the results
        set_transient($cache_key, $data, $this->cache_duration);

        return $data;
    }

    /**
     * Get overview analytics data
     *
     * @since 1.0.0
     * @param array $dates Date range array
     * @return array Overview data
     */
    private function get_overview_data($dates)
    {
        global $wpdb;

        // Total revenue
        $revenue_query = $wpdb->prepare("
            SELECT SUM(amount) as total_revenue, COUNT(*) as total_payments
            FROM {$this->table_name}
            WHERE event_type = 'payment' 
            AND status = 'completed'
            AND created_at BETWEEN %s AND %s
        ", $dates['start'], $dates['end']);

        $revenue_result = $wpdb->get_row($revenue_query);

        // WhatsApp interactions
        $interactions_query = $wpdb->prepare("
            SELECT COUNT(*) as total_interactions
            FROM {$this->table_name}
            WHERE event_type = 'contact_interaction'
            AND created_at BETWEEN %s AND %s
        ", $dates['start'], $dates['end']);

        $interactions_result = $wpdb->get_row($interactions_query);

        // Messages sent
        $messages_query = $wpdb->prepare("
            SELECT COUNT(*) as total_messages, 
                   SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as successful_messages
            FROM {$this->table_name}
            WHERE event_type = 'whatsapp_message'
            AND created_at BETWEEN %s AND %s
        ", $dates['start'], $dates['end']);

        $messages_result = $wpdb->get_row($messages_query);

        // Calculate conversion rate
        $conversion_rate = 0;
        if ($interactions_result->total_interactions > 0) {
            $conversion_rate = ($revenue_result->total_payments / $interactions_result->total_interactions) * 100;
        }

        return array(
            'totals' => array(
                'revenue' => floatval($revenue_result->total_revenue ?? 0),
                'payments' => intval($revenue_result->total_payments ?? 0),
                'interactions' => intval($interactions_result->total_interactions ?? 0),
                'messages_sent' => intval($messages_result->total_messages ?? 0),
                'successful_messages' => intval($messages_result->successful_messages ?? 0)
            ),
            'conversion_rate' => round($conversion_rate, 2),
            'message_success_rate' => $messages_result->total_messages > 0 ?
                round(($messages_result->successful_messages / $messages_result->total_messages) * 100, 2) : 0,
            'average_order_value' => $revenue_result->total_payments > 0 ?
                round($revenue_result->total_revenue / $revenue_result->total_payments, 2) : 0,
            'date_range' => $dates
        );
    }

    /**
     * Get conversion analytics data
     *
     * @since 1.0.0
     * @param array $dates Date range array
     * @return array Conversion data
     */
    private function get_conversion_data($dates)
    {
        global $wpdb;

        // Daily conversions
        $daily_query = $wpdb->prepare("
            SELECT DATE(created_at) as date, 
                   COUNT(*) as conversions,
                   SUM(amount) as revenue
            FROM {$this->table_name}
            WHERE event_type = 'payment' 
            AND status = 'completed'
            AND created_at BETWEEN %s AND %s
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ", $dates['start'], $dates['end']);

        $daily_conversions = $wpdb->get_results($daily_query);

        // Gateway performance
        $gateway_query = $wpdb->prepare("
            SELECT gateway,
                   COUNT(*) as conversions,
                   SUM(amount) as revenue,
                   AVG(amount) as avg_order_value
            FROM {$this->table_name}
            WHERE event_type = 'payment' 
            AND status = 'completed'
            AND created_at BETWEEN %s AND %s
            GROUP BY gateway
            ORDER BY revenue DESC
        ", $dates['start'], $dates['end']);

        $gateway_performance = $wpdb->get_results($gateway_query);

        return array(
            'daily_conversions' => $daily_conversions,
            'gateway_performance' => $gateway_performance,
            'date_range' => $dates
        );
    }

    /**
     * Get revenue analytics data
     *
     * @since 1.0.0
     * @param array $dates Date range array
     * @return array Revenue data
     */
    private function get_revenue_data($dates)
    {
        global $wpdb;

        // Revenue by source
        $source_query = $wpdb->prepare("
            SELECT source,
                   COUNT(*) as transactions,
                   SUM(amount) as revenue
            FROM {$this->table_name}
            WHERE event_type = 'payment' 
            AND status = 'completed'
            AND created_at BETWEEN %s AND %s
            GROUP BY source
            ORDER BY revenue DESC
        ", $dates['start'], $dates['end']);

        $revenue_by_source = $wpdb->get_results($source_query);

        // Monthly revenue trend
        $monthly_query = $wpdb->prepare("
            SELECT DATE_FORMAT(created_at, '%%Y-%%m') as month,
                   COUNT(*) as transactions,
                   SUM(amount) as revenue
            FROM {$this->table_name}
            WHERE event_type = 'payment' 
            AND status = 'completed'
            AND created_at BETWEEN %s AND %s
            GROUP BY DATE_FORMAT(created_at, '%%Y-%%m')
            ORDER BY month ASC
        ", $dates['start'], $dates['end']);

        $monthly_revenue = $wpdb->get_results($monthly_query);

        return array(
            'revenue_by_source' => $revenue_by_source,
            'monthly_revenue' => $monthly_revenue,
            'date_range' => $dates
        );
    }

    /**
     * Get performance analytics data
     *
     * @since 1.0.0
     * @param array $dates Date range array
     * @return array Performance data
     */
    private function get_performance_data($dates)
    {
        global $wpdb;

        // Top performing contacts
        $top_contacts_query = $wpdb->prepare("
            SELECT contact_phone,
                   COUNT(CASE WHEN event_type = 'payment' THEN 1 END) as purchases,
                   SUM(CASE WHEN event_type = 'payment' THEN amount ELSE 0 END) as total_spent,
                   COUNT(CASE WHEN event_type = 'contact_interaction' THEN 1 END) as interactions
            FROM {$this->table_name}
            WHERE contact_phone != ''
            AND created_at BETWEEN %s AND %s
            GROUP BY contact_phone
            HAVING purchases > 0
            ORDER BY total_spent DESC
            LIMIT 10
        ", $dates['start'], $dates['end']);

        $top_contacts = $wpdb->get_results($top_contacts_query);

        return array(
            'top_contacts' => $top_contacts,
            'date_range' => $dates
        );
    }

    /**
     * Calculate date range based on period
     *
     * @since 1.0.0
     * @param string $period Date period
     * @return array Start and end dates
     */
    private function get_date_range($period)
    {
        $end_date = current_time('Y-m-d 23:59:59');

        switch ($period) {
            case '7_days':
                $start_date = date('Y-m-d 00:00:00', strtotime('-7 days'));
                break;
            case '30_days':
                $start_date = date('Y-m-d 00:00:00', strtotime('-30 days'));
                break;
            case '90_days':
                $start_date = date('Y-m-d 00:00:00', strtotime('-90 days'));
                break;
            case 'this_month':
                $start_date = date('Y-m-01 00:00:00');
                break;
            case 'last_month':
                $start_date = date('Y-m-01 00:00:00', strtotime('first day of last month'));
                $end_date = date('Y-m-t 23:59:59', strtotime('last day of last month'));
                break;
            default:
                $start_date = date('Y-m-d 00:00:00', strtotime('-30 days'));
        }

        return array(
            'start' => $start_date,
            'end' => $end_date,
            'period' => $period
        );
    }

    /**
     * AJAX handler for analytics data
     *
     * @since 1.0.0
     */
    public function ajax_get_analytics_data()
    {
        check_ajax_referer('chatshop_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'chatshop'));
        }

        $date_range = sanitize_text_field($_POST['date_range'] ?? '30_days');
        $type = sanitize_text_field($_POST['type'] ?? 'overview');

        $data = $this->get_analytics_data($date_range, $type);

        wp_send_json_success($data);
    }

    /**
     * AJAX handler for conversion stats
     *
     * @since 1.0.0
     */
    public function ajax_get_conversion_stats()
    {
        check_ajax_referer('chatshop_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'chatshop'));
        }

        $date_range = sanitize_text_field($_POST['date_range'] ?? '30_days');
        $data = $this->get_analytics_data($date_range, 'conversions');

        wp_send_json_success($data);
    }

    /**
     * AJAX handler for revenue attribution
     *
     * @since 1.0.0
     */
    public function ajax_get_revenue_attribution()
    {
        check_ajax_referer('chatshop_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'chatshop'));
        }

        $date_range = sanitize_text_field($_POST['date_range'] ?? '30_days');
        $data = $this->get_analytics_data($date_range, 'revenue');

        wp_send_json_success($data);
    }

    /**
     * AJAX handler for performance metrics
     *
     * @since 1.0.0
     */
    public function ajax_get_performance_metrics()
    {
        check_ajax_referer('chatshop_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'chatshop'));
        }

        $date_range = sanitize_text_field($_POST['date_range'] ?? '30_days');
        $data = $this->get_analytics_data($date_range, 'performance');

        wp_send_json_success($data);
    }

    /**
     * Enqueue admin scripts
     *
     * @since 1.0.0
     * @param string $hook_suffix Current admin page
     */
    public function enqueue_admin_scripts($hook_suffix)
    {
        // Only load on ChatShop analytics pages
        if (strpos($hook_suffix, 'chatshop') === false || strpos($hook_suffix, 'analytics') === false) {
            return;
        }

        wp_enqueue_script('jquery');
        wp_enqueue_script('chart-js', 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js', array(), '3.9.1', true);
    }

    /**
     * Clear analytics cache
     *
     * @since 1.0.0
     */
    public function clear_cache()
    {
        global $wpdb;

        // Delete all analytics transients
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_chatshop_analytics_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_chatshop_analytics_%'");

        chatshop_log('Analytics cache cleared', 'info');
    }
}
