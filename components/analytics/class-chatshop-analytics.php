<?php

/**
 * ChatShop Analytics Component
 *
 * File: components/analytics/class-chatshop-analytics.php
 * 
 * Handles analytics data collection, processing, and reporting for
 * WhatsApp campaigns, payment conversions, and revenue tracking.
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
 * Manages analytics data collection and reporting functionality
 * with premium features for advanced metrics and custom reports.
 *
 * @since 1.0.0
 */
class ChatShop_Analytics extends ChatShop_Abstract_Component
{
    /**
     * Component identifier
     *
     * @var string
     * @since 1.0.0
     */
    protected $id = 'analytics';

    /**
     * Component name
     *
     * @var string
     * @since 1.0.0
     */
    protected $name = 'Analytics & Reporting';

    /**
     * Component description
     *
     * @var string
     * @since 1.0.0
     */
    protected $description = 'Track conversions and generate reports';

    /**
     * Database table names
     *
     * @var array
     * @since 1.0.0
     */
    private $tables = array();

    /**
     * Cached analytics data
     *
     * @var array
     * @since 1.0.0
     */
    private $cache = array();

    /**
     * Initialize component
     *
     * @since 1.0.0
     */
    protected function init()
    {
        $this->setup_database_tables();
        $this->init_hooks();

        chatshop_log('Analytics component initialized', 'info');
    }

    /**
     * Setup database table names
     *
     * @since 1.0.0
     */
    private function setup_database_tables()
    {
        global $wpdb;

        $this->tables = array(
            'events' => $wpdb->prefix . 'chatshop_analytics_events',
            'conversions' => $wpdb->prefix . 'chatshop_analytics_conversions',
            'campaigns' => $wpdb->prefix . 'chatshop_analytics_campaigns'
        );
    }

    /**
     * Initialize WordPress hooks
     *
     * @since 1.0.0
     */
    private function init_hooks()
    {
        // Track events
        add_action('chatshop_whatsapp_message_sent', array($this, 'track_message_sent'), 10, 2);
        add_action('chatshop_payment_completed', array($this, 'track_payment_completed'), 10, 2);
        add_action('chatshop_campaign_started', array($this, 'track_campaign_started'), 10, 2);

        // AJAX handlers
        add_action('wp_ajax_chatshop_get_analytics_data', array($this, 'ajax_get_analytics_data'));
        add_action('wp_ajax_chatshop_export_analytics_report', array($this, 'ajax_export_report'));

        // Scheduled tasks
        add_action('chatshop_daily_analytics_cleanup', array($this, 'cleanup_old_data'));

        // Schedule cleanup if not already scheduled
        if (!wp_next_scheduled('chatshop_daily_analytics_cleanup')) {
            wp_schedule_event(time(), 'daily', 'chatshop_daily_analytics_cleanup');
        }
    }

    /**
     * Component activation handler
     *
     * @since 1.0.0
     * @return bool True on successful activation
     */
    protected function do_activation()
    {
        return $this->create_database_tables();
    }

    /**
     * Component deactivation handler
     *
     * @since 1.0.0
     * @return bool True on successful deactivation
     */
    protected function do_deactivation()
    {
        // Clear scheduled events
        wp_clear_scheduled_hook('chatshop_daily_analytics_cleanup');

        return true;
    }

    /**
     * Create database tables for analytics
     *
     * @since 1.0.0
     * @return bool True if tables created successfully
     */
    private function create_database_tables()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Events table
        $events_table = "CREATE TABLE {$this->tables['events']} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            event_type varchar(50) NOT NULL,
            event_data longtext,
            user_id bigint(20),
            session_id varchar(100),
            source varchar(50),
            campaign_id bigint(20),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY event_type (event_type),
            KEY user_id (user_id),
            KEY campaign_id (campaign_id),
            KEY created_at (created_at)
        ) $charset_collate;";

        // Conversions table
        $conversions_table = "CREATE TABLE {$this->tables['conversions']} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            conversion_type varchar(50) NOT NULL,
            value decimal(10,2) DEFAULT 0.00,
            currency varchar(10) DEFAULT 'USD',
            order_id bigint(20),
            user_id bigint(20),
            campaign_id bigint(20),
            source_event_id bigint(20),
            conversion_data longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY conversion_type (conversion_type),
            KEY user_id (user_id),
            KEY campaign_id (campaign_id),
            KEY order_id (order_id),
            KEY created_at (created_at)
        ) $charset_collate;";

        // Campaigns table
        $campaigns_table = "CREATE TABLE {$this->tables['campaigns']} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(200) NOT NULL,
            type varchar(50) NOT NULL,
            status varchar(20) DEFAULT 'active',
            settings longtext,
            stats longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY type (type),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $results = array(
            dbDelta($events_table),
            dbDelta($conversions_table),
            dbDelta($campaigns_table)
        );

        chatshop_log('Analytics database tables created', 'info', array('results' => $results));

        return true;
    }

    /**
     * Track WhatsApp message sent event
     *
     * @since 1.0.0
     * @param array $message_data Message data
     * @param int $campaign_id Campaign ID
     */
    public function track_message_sent($message_data, $campaign_id = null)
    {
        $this->track_event('whatsapp_message_sent', array(
            'recipient' => $message_data['to'] ?? '',
            'message_type' => $message_data['type'] ?? 'text',
            'template' => $message_data['template'] ?? null,
            'status' => $message_data['status'] ?? 'sent'
        ), $campaign_id);
    }

    /**
     * Track payment completed event
     *
     * @since 1.0.0
     * @param array $payment_data Payment data
     * @param int $order_id Order ID
     */
    public function track_payment_completed($payment_data, $order_id = null)
    {
        $conversion_value = floatval($payment_data['amount'] ?? 0);
        $currency = $payment_data['currency'] ?? 'USD';

        // Track as conversion
        $this->track_conversion('payment_completed', $conversion_value, $currency, $order_id, array(
            'gateway' => $payment_data['gateway'] ?? '',
            'payment_method' => $payment_data['method'] ?? '',
            'transaction_id' => $payment_data['transaction_id'] ?? ''
        ));

        // Also track as event
        $this->track_event('payment_completed', $payment_data);
    }

    /**
     * Track campaign started event
     *
     * @since 1.0.0
     * @param array $campaign_data Campaign data
     * @param int $campaign_id Campaign ID
     */
    public function track_campaign_started($campaign_data, $campaign_id = null)
    {
        $this->track_event('campaign_started', $campaign_data, $campaign_id);
    }

    /**
     * Track a generic event
     *
     * @since 1.0.0
     * @param string $event_type Event type
     * @param array $event_data Event data
     * @param int $campaign_id Campaign ID
     * @param int $user_id User ID
     * @return int|false Event ID or false on failure
     */
    public function track_event($event_type, $event_data = array(), $campaign_id = null, $user_id = null)
    {
        global $wpdb;

        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        $session_id = $this->get_session_id();
        $source = $this->get_traffic_source();

        $result = $wpdb->insert(
            $this->tables['events'],
            array(
                'event_type' => sanitize_key($event_type),
                'event_data' => wp_json_encode($event_data),
                'user_id' => $user_id,
                'session_id' => $session_id,
                'source' => $source,
                'campaign_id' => $campaign_id,
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%d', '%s', '%s', '%d', '%s')
        );

        if ($result) {
            chatshop_log("Event tracked: {$event_type}", 'info', array(
                'event_id' => $wpdb->insert_id,
                'campaign_id' => $campaign_id
            ));

            return $wpdb->insert_id;
        }

        chatshop_log("Failed to track event: {$event_type}", 'error', array(
            'error' => $wpdb->last_error
        ));

        return false;
    }

    /**
     * Track a conversion event
     *
     * @since 1.0.0
     * @param string $conversion_type Conversion type
     * @param float $value Conversion value
     * @param string $currency Currency code
     * @param int $order_id Order ID
     * @param array $conversion_data Additional conversion data
     * @param int $campaign_id Campaign ID
     * @return int|false Conversion ID or false on failure
     */
    public function track_conversion($conversion_type, $value = 0.0, $currency = 'USD', $order_id = null, $conversion_data = array(), $campaign_id = null)
    {
        global $wpdb;

        $user_id = get_current_user_id();

        $result = $wpdb->insert(
            $this->tables['conversions'],
            array(
                'conversion_type' => sanitize_key($conversion_type),
                'value' => $value,
                'currency' => strtoupper($currency),
                'order_id' => $order_id,
                'user_id' => $user_id,
                'campaign_id' => $campaign_id,
                'conversion_data' => wp_json_encode($conversion_data),
                'created_at' => current_time('mysql')
            ),
            array('%s', '%f', '%s', '%d', '%d', '%d', '%s', '%s')
        );

        if ($result) {
            chatshop_log("Conversion tracked: {$conversion_type}", 'info', array(
                'conversion_id' => $wpdb->insert_id,
                'value' => $value,
                'currency' => $currency
            ));

            return $wpdb->insert_id;
        }

        chatshop_log("Failed to track conversion: {$conversion_type}", 'error', array(
            'error' => $wpdb->last_error
        ));

        return false;
    }

    /**
     * Get analytics dashboard data
     *
     * @since 1.0.0
     * @param array $params Query parameters
     * @return array Analytics data
     */
    public function get_dashboard_data($params = array())
    {
        $cache_key = 'dashboard_data_' . md5(serialize($params));

        if (isset($this->cache[$cache_key])) {
            return $this->cache[$cache_key];
        }

        $start_date = $params['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $end_date = $params['end_date'] ?? date('Y-m-d');

        $data = array(
            'overview' => $this->get_overview_stats($start_date, $end_date),
            'revenue' => $this->get_revenue_stats($start_date, $end_date),
            'campaigns' => $this->get_campaign_stats($start_date, $end_date),
            'top_events' => $this->get_top_events($start_date, $end_date),
            'conversion_funnel' => $this->get_conversion_funnel($start_date, $end_date)
        );

        $this->cache[$cache_key] = $data;

        return $data;
    }

    /**
     * Get overview statistics
     *
     * @since 1.0.0
     * @param string $start_date Start date
     * @param string $end_date End date
     * @return array Overview statistics
     */
    private function get_overview_stats($start_date, $end_date)
    {
        global $wpdb;

        $events_table = $this->tables['events'];
        $conversions_table = $this->tables['conversions'];

        // Total events
        $total_events = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$events_table} 
             WHERE DATE(created_at) BETWEEN %s AND %s",
            $start_date,
            $end_date
        ));

        // Total conversions
        $total_conversions = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$conversions_table} 
             WHERE DATE(created_at) BETWEEN %s AND %s",
            $start_date,
            $end_date
        ));

        // Total revenue
        $total_revenue = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(value) FROM {$conversions_table} 
             WHERE DATE(created_at) BETWEEN %s AND %s 
             AND conversion_type = 'payment_completed'",
            $start_date,
            $end_date
        ));

        // Messages sent
        $messages_sent = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$events_table} 
             WHERE event_type = 'whatsapp_message_sent' 
             AND DATE(created_at) BETWEEN %s AND %s",
            $start_date,
            $end_date
        ));

        return array(
            'total_events' => intval($total_events),
            'total_conversions' => intval($total_conversions),
            'total_revenue' => floatval($total_revenue ?: 0),
            'messages_sent' => intval($messages_sent),
            'conversion_rate' => $messages_sent > 0 ? round(($total_conversions / $messages_sent) * 100, 2) : 0
        );
    }

    /**
     * Get revenue statistics
     *
     * @since 1.0.0
     * @param string $start_date Start date
     * @param string $end_date End date
     * @return array Revenue statistics
     */
    private function get_revenue_stats($start_date, $end_date)
    {
        global $wpdb;

        $conversions_table = $this->tables['conversions'];

        // Daily revenue
        $daily_revenue = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(created_at) as date, SUM(value) as revenue, COUNT(*) as orders
             FROM {$conversions_table}
             WHERE conversion_type = 'payment_completed'
             AND DATE(created_at) BETWEEN %s AND %s
             GROUP BY DATE(created_at)
             ORDER BY date ASC",
            $start_date,
            $end_date
        ), ARRAY_A);

        // Revenue by gateway
        $revenue_by_gateway = $wpdb->get_results($wpdb->prepare(
            "SELECT JSON_EXTRACT(conversion_data, '$.gateway') as gateway, 
                    SUM(value) as revenue, COUNT(*) as orders
             FROM {$conversions_table}
             WHERE conversion_type = 'payment_completed'
             AND DATE(created_at) BETWEEN %s AND %s
             GROUP BY JSON_EXTRACT(conversion_data, '$.gateway')
             ORDER BY revenue DESC",
            $start_date,
            $end_date
        ), ARRAY_A);

        return array(
            'daily_revenue' => $daily_revenue,
            'revenue_by_gateway' => $revenue_by_gateway,
            'average_order_value' => $this->calculate_average_order_value($start_date, $end_date)
        );
    }

    /**
     * Get campaign statistics
     *
     * @since 1.0.0
     * @param string $start_date Start date
     * @param string $end_date End date
     * @return array Campaign statistics
     */
    private function get_campaign_stats($start_date, $end_date)
    {
        global $wpdb;

        $events_table = $this->tables['events'];
        $conversions_table = $this->tables['conversions'];
        $campaigns_table = $this->tables['campaigns'];

        $campaign_stats = $wpdb->get_results($wpdb->prepare(
            "SELECT c.id, c.name, c.type,
                    COUNT(DISTINCT e.id) as total_events,
                    COUNT(DISTINCT conv.id) as total_conversions,
                    SUM(conv.value) as total_revenue
             FROM {$campaigns_table} c
             LEFT JOIN {$events_table} e ON c.id = e.campaign_id 
                 AND DATE(e.created_at) BETWEEN %s AND %s
             LEFT JOIN {$conversions_table} conv ON c.id = conv.campaign_id 
                 AND DATE(conv.created_at) BETWEEN %s AND %s
             WHERE c.status = 'active'
             GROUP BY c.id
             ORDER BY total_revenue DESC",
            $start_date,
            $end_date,
            $start_date,
            $end_date
        ), ARRAY_A);

        return $campaign_stats;
    }

    /**
     * Get top events
     *
     * @since 1.0.0
     * @param string $start_date Start date
     * @param string $end_date End date
     * @return array Top events
     */
    private function get_top_events($start_date, $end_date)
    {
        global $wpdb;

        $events_table = $this->tables['events'];

        return $wpdb->get_results($wpdb->prepare(
            "SELECT event_type, COUNT(*) as count
             FROM {$events_table}
             WHERE DATE(created_at) BETWEEN %s AND %s
             GROUP BY event_type
             ORDER BY count DESC
             LIMIT 10",
            $start_date,
            $end_date
        ), ARRAY_A);
    }

    /**
     * Get conversion funnel data
     *
     * @since 1.0.0
     * @param string $start_date Start date
     * @param string $end_date End date
     * @return array Conversion funnel data
     */
    private function get_conversion_funnel($start_date, $end_date)
    {
        global $wpdb;

        $events_table = $this->tables['events'];
        $conversions_table = $this->tables['conversions'];

        $funnel_steps = array(
            'messages_sent' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$events_table} 
                 WHERE event_type = 'whatsapp_message_sent' 
                 AND DATE(created_at) BETWEEN %s AND %s",
                $start_date,
                $end_date
            )),
            'messages_delivered' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$events_table} 
                 WHERE event_type = 'whatsapp_message_delivered' 
                 AND DATE(created_at) BETWEEN %s AND %s",
                $start_date,
                $end_date
            )),
            'messages_read' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$events_table} 
                 WHERE event_type = 'whatsapp_message_read' 
                 AND DATE(created_at) BETWEEN %s AND %s",
                $start_date,
                $end_date
            )),
            'payment_initiated' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$events_table} 
                 WHERE event_type = 'payment_initiated' 
                 AND DATE(created_at) BETWEEN %s AND %s",
                $start_date,
                $end_date
            )),
            'payment_completed' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$conversions_table} 
                 WHERE conversion_type = 'payment_completed' 
                 AND DATE(created_at) BETWEEN %s AND %s",
                $start_date,
                $end_date
            ))
        );

        return $funnel_steps;
    }

    /**
     * Calculate average order value
     *
     * @since 1.0.0
     * @param string $start_date Start date
     * @param string $end_date End date
     * @return float Average order value
     */
    private function calculate_average_order_value($start_date, $end_date)
    {
        global $wpdb;

        $conversions_table = $this->tables['conversions'];

        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT AVG(value) as avg_value, COUNT(*) as order_count
             FROM {$conversions_table}
             WHERE conversion_type = 'payment_completed'
             AND DATE(created_at) BETWEEN %s AND %s",
            $start_date,
            $end_date
        ));

        return $result ? floatval($result->avg_value) : 0.0;
    }

    /**
     * Get session ID for tracking
     *
     * @since 1.0.0
     * @return string Session ID
     */
    private function get_session_id()
    {
        if (!session_id()) {
            session_start();
        }

        return session_id();
    }

    /**
     * Get traffic source
     *
     * @since 1.0.0
     * @return string Traffic source
     */
    private function get_traffic_source()
    {
        if (isset($_SERVER['HTTP_REFERER'])) {
            $referer = $_SERVER['HTTP_REFERER'];
            $parsed_url = parse_url($referer);
            return $parsed_url['host'] ?? 'direct';
        }

        return 'direct';
    }

    /**
     * AJAX handler for getting analytics data
     *
     * @since 1.0.0
     */
    public function ajax_get_analytics_data()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'chatshop_admin_nonce')) {
            wp_die(__('Security check failed', 'chatshop'));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'chatshop'));
        }

        $data_type = sanitize_key($_POST['data_type'] ?? 'dashboard');
        $start_date = sanitize_text_field($_POST['start_date'] ?? '');
        $end_date = sanitize_text_field($_POST['end_date'] ?? '');

        try {
            switch ($data_type) {
                case 'dashboard':
                    $data = $this->get_dashboard_data(array(
                        'start_date' => $start_date,
                        'end_date' => $end_date
                    ));
                    break;

                case 'campaigns':
                    $data = $this->get_campaign_stats($start_date, $end_date);
                    break;

                case 'revenue':
                    $data = $this->get_revenue_stats($start_date, $end_date);
                    break;

                default:
                    throw new Exception('Invalid data type requested');
            }

            wp_send_json_success($data);
        } catch (Exception $e) {
            chatshop_log('Analytics AJAX error: ' . $e->getMessage(), 'error');
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * AJAX handler for exporting analytics report
     *
     * @since 1.0.0
     */
    public function ajax_export_report()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'chatshop_admin_nonce')) {
            wp_die(__('Security check failed', 'chatshop'));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'chatshop'));
        }

        // Check if premium feature is available
        if (!chatshop_is_premium_feature_available('custom_reports')) {
            wp_send_json_error(array('message' => __('Export feature requires premium access', 'chatshop')));
            return;
        }

        $report_type = sanitize_key($_POST['report_type'] ?? 'overview');
        $format = sanitize_key($_POST['format'] ?? 'csv');
        $start_date = sanitize_text_field($_POST['start_date'] ?? '');
        $end_date = sanitize_text_field($_POST['end_date'] ?? '');

        try {
            $file_url = $this->export_report($report_type, $format, $start_date, $end_date);

            wp_send_json_success(array(
                'download_url' => $file_url,
                'message' => __('Report exported successfully', 'chatshop')
            ));
        } catch (Exception $e) {
            chatshop_log('Analytics export error: ' . $e->getMessage(), 'error');
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * Export analytics report to file
     *
     * @since 1.0.0
     * @param string $report_type Report type
     * @param string $format Export format (csv, pdf)
     * @param string $start_date Start date
     * @param string $end_date End date
     * @return string File URL
     */
    private function export_report($report_type, $format, $start_date, $end_date)
    {
        $data = $this->get_dashboard_data(array(
            'start_date' => $start_date,
            'end_date' => $end_date
        ));

        $upload_dir = wp_upload_dir();
        $filename = "chatshop-analytics-{$report_type}-{$start_date}-to-{$end_date}.{$format}";
        $file_path = $upload_dir['path'] . '/' . $filename;

        if ($format === 'csv') {
            $this->export_to_csv($data, $file_path);
        } elseif ($format === 'pdf') {
            $this->export_to_pdf($data, $file_path);
        } else {
            throw new Exception('Unsupported export format');
        }

        return $upload_dir['url'] . '/' . $filename;
    }

    /**
     * Export data to CSV format
     *
     * @since 1.0.0
     * @param array $data Analytics data
     * @param string $file_path File path
     */
    private function export_to_csv($data, $file_path)
    {
        $handle = fopen($file_path, 'w');

        if (!$handle) {
            throw new Exception('Cannot create CSV file');
        }

        // Write headers
        fputcsv($handle, array('Metric', 'Value'));

        // Write overview data
        foreach ($data['overview'] as $key => $value) {
            fputcsv($handle, array(ucwords(str_replace('_', ' ', $key)), $value));
        }

        // Write revenue data
        if (!empty($data['revenue']['daily_revenue'])) {
            fputcsv($handle, array('', '')); // Empty row
            fputcsv($handle, array('Date', 'Revenue', 'Orders'));

            foreach ($data['revenue']['daily_revenue'] as $day) {
                fputcsv($handle, array($day['date'], $day['revenue'], $day['orders']));
            }
        }

        fclose($handle);
    }

    /**
     * Export data to PDF format (basic implementation)
     *
     * @since 1.0.0
     * @param array $data Analytics data
     * @param string $file_path File path
     */
    private function export_to_pdf($data, $file_path)
    {
        // This is a basic implementation
        // In a production environment, you'd use a proper PDF library like TCPDF or FPDF

        $html_content = $this->generate_pdf_html($data);

        // Save as HTML for now (would convert to PDF in production)
        file_put_contents(str_replace('.pdf', '.html', $file_path), $html_content);

        // Return HTML file path for now
        return str_replace('.pdf', '.html', $file_path);
    }

    /**
     * Generate HTML content for PDF export
     *
     * @since 1.0.0
     * @param array $data Analytics data
     * @return string HTML content
     */
    private function generate_pdf_html($data)
    {
        ob_start();
?>
        <!DOCTYPE html>
        <html>

        <head>
            <title>ChatShop Analytics Report</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    margin: 20px;
                }

                table {
                    border-collapse: collapse;
                    width: 100%;
                    margin: 20px 0;
                }

                th,
                td {
                    border: 1px solid #ddd;
                    padding: 8px;
                    text-align: left;
                }

                th {
                    background-color: #f2f2f2;
                }

                h1,
                h2 {
                    color: #333;
                }
            </style>
        </head>

        <body>
            <h1>ChatShop Analytics Report</h1>

            <h2>Overview</h2>
            <table>
                <tr>
                    <th>Metric</th>
                    <th>Value</th>
                </tr>
                <?php foreach ($data['overview'] as $key => $value): ?>
                    <tr>
                        <td><?php echo esc_html(ucwords(str_replace('_', ' ', $key))); ?></td>
                        <td><?php echo esc_html($value); ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>

            <?php if (!empty($data['revenue']['daily_revenue'])): ?>
                <h2>Daily Revenue</h2>
                <table>
                    <tr>
                        <th>Date</th>
                        <th>Revenue</th>
                        <th>Orders</th>
                    </tr>
                    <?php foreach ($data['revenue']['daily_revenue'] as $day): ?>
                        <tr>
                            <td><?php echo esc_html($day['date']); ?></td>
                            <td><?php echo esc_html($day['revenue']); ?></td>
                            <td><?php echo esc_html($day['orders']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </body>

        </html>
<?php
        return ob_get_clean();
    }

    /**
     * Cleanup old analytics data
     *
     * @since 1.0.0
     */
    public function cleanup_old_data()
    {
        global $wpdb;

        $retention_days = chatshop_get_option('analytics', 'data_retention_days', 365);
        $cutoff_date = date('Y-m-d', strtotime("-{$retention_days} days"));

        // Delete old events
        $events_deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->tables['events']} WHERE DATE(created_at) < %s",
            $cutoff_date
        ));

        // Delete old conversions (keep longer for important data)
        $conversion_retention_days = $retention_days * 2; // Keep conversions longer
        $conversion_cutoff_date = date('Y-m-d', strtotime("-{$conversion_retention_days} days"));

        $conversions_deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->tables['conversions']} WHERE DATE(created_at) < %s",
            $conversion_cutoff_date
        ));

        chatshop_log('Analytics data cleanup completed', 'info', array(
            'events_deleted' => $events_deleted,
            'conversions_deleted' => $conversions_deleted,
            'cutoff_date' => $cutoff_date
        ));
    }

    /**
     * Get component status for admin display
     *
     * @since 1.0.0
     * @return array Component status
     */
    public function get_status()
    {
        global $wpdb;

        $status = array(
            'active' => true,
            'tables_exist' => true,
            'recent_events' => 0,
            'total_conversions' => 0,
            'errors' => array()
        );

        // Check if tables exist
        foreach ($this->tables as $table_name) {
            if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
                $status['tables_exist'] = false;
                $status['errors'][] = "Table {$table_name} does not exist";
            }
        }

        if ($status['tables_exist']) {
            // Get recent activity
            $status['recent_events'] = $wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->tables['events']} 
                 WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)"
            );

            $status['total_conversions'] = $wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->tables['conversions']}"
            );
        }

        return $status;
    }
}
