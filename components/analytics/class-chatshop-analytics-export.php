<?php

/**
 * Analytics Export AJAX Handler
 *
 * File: components/analytics/class-chatshop-analytics-export.php
 * 
 * Handles export functionality for analytics data with proper security
 * and premium feature restrictions.
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
 * ChatShop Analytics Export Class
 *
 * Handles export of analytics data in various formats with proper
 * security checks and premium feature validation.
 *
 * @since 1.0.0
 */
class ChatShop_Analytics_Export
{
    /**
     * Analytics instance
     *
     * @var ChatShop_Analytics
     * @since 1.0.0
     */
    private $analytics;

    /**
     * Supported export formats
     *
     * @var array
     * @since 1.0.0
     */
    private $supported_formats = array('csv', 'json');

    /**
     * Constructor
     *
     * @since 1.0.0
     * @param ChatShop_Analytics $analytics Analytics instance
     */
    public function __construct($analytics)
    {
        $this->analytics = $analytics;
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     *
     * @since 1.0.0
     */
    private function init_hooks()
    {
        add_action('wp_ajax_chatshop_export_analytics', array($this, 'handle_export_request'));
    }

    /**
     * Handle analytics export AJAX request
     *
     * @since 1.0.0
     */
    public function handle_export_request()
    {
        // Security checks
        check_ajax_referer('chatshop_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('You do not have sufficient permissions.', 'chatshop')
            ));
        }

        if (!chatshop_is_premium()) {
            wp_send_json_error(array(
                'message' => __('Analytics export is a premium feature.', 'chatshop')
            ));
        }

        // Get export parameters
        $date_range = sanitize_text_field($_POST['date_range'] ?? '7days');
        $format = sanitize_text_field($_POST['format'] ?? 'csv');
        $export_type = sanitize_text_field($_POST['export_type'] ?? 'overview');

        // Validate parameters
        if (!in_array($format, $this->supported_formats)) {
            wp_send_json_error(array(
                'message' => __('Unsupported export format.', 'chatshop')
            ));
        }

        try {
            $export_data = $this->prepare_export_data($date_range, $export_type);

            if (empty($export_data)) {
                wp_send_json_error(array(
                    'message' => __('No data available for export.', 'chatshop')
                ));
            }

            $export_result = $this->export_data($export_data, $format, $export_type, $date_range);

            wp_send_json_success($export_result);
        } catch (Exception $e) {
            chatshop_log('Analytics export error: ' . $e->getMessage(), 'error');

            wp_send_json_error(array(
                'message' => __('Export failed. Please try again.', 'chatshop')
            ));
        }
    }

    /**
     * Prepare data for export
     *
     * @since 1.0.0
     * @param string $date_range Date range for export
     * @param string $export_type Type of data to export
     * @return array Prepared export data
     */
    private function prepare_export_data($date_range, $export_type)
    {
        switch ($export_type) {
            case 'overview':
                return $this->prepare_overview_data($date_range);
            case 'conversions':
                return $this->prepare_conversion_data($date_range);
            case 'revenue':
                return $this->prepare_revenue_data($date_range);
            case 'detailed':
                return $this->prepare_detailed_data($date_range);
            default:
                return $this->prepare_overview_data($date_range);
        }
    }

    /**
     * Prepare overview data for export
     *
     * @since 1.0.0
     * @param string $date_range Date range
     * @return array Overview data
     */
    private function prepare_overview_data($date_range)
    {
        $data = $this->analytics->get_analytics_data($date_range, 'overview');
        $export_data = array();

        // Add summary row
        if (isset($data['totals'])) {
            $export_data[] = array(
                'Metric' => 'Total Revenue',
                'Value' => ChatShop_Helper::format_currency($data['totals']['revenue'] ?? 0),
                'Period' => ChatShop_Helper::format_period_label($date_range)
            );

            $export_data[] = array(
                'Metric' => 'Total Conversions',
                'Value' => ChatShop_Helper::format_number($data['totals']['conversions'] ?? 0),
                'Period' => ChatShop_Helper::format_period_label($date_range)
            );

            $export_data[] = array(
                'Metric' => 'WhatsApp Interactions',
                'Value' => ChatShop_Helper::format_number($data['totals']['interactions'] ?? 0),
                'Period' => ChatShop_Helper::format_period_label($date_range)
            );

            $export_data[] = array(
                'Metric' => 'Conversion Rate',
                'Value' => ($data['conversion_rate'] ?? 0) . '%',
                'Period' => ChatShop_Helper::format_period_label($date_range)
            );
        }

        // Add daily breakdown
        if (isset($data['daily_breakdown'])) {
            $export_data[] = array(); // Empty row for spacing

            $export_data[] = array(
                'Date' => 'Date',
                'Revenue' => 'Revenue',
                'Conversions' => 'Conversions',
                'Interactions' => 'Interactions'
            );

            foreach ($data['daily_breakdown'] as $day) {
                $export_data[] = array(
                    'Date' => $day['metric_date'],
                    'Revenue' => ChatShop_Helper::format_currency($day['daily_revenue'] ?? 0),
                    'Conversions' => ChatShop_Helper::format_number($day['daily_conversions'] ?? 0),
                    'Interactions' => ChatShop_Helper::format_number($day['daily_interactions'] ?? 0)
                );
            }
        }

        return $export_data;
    }

    /**
     * Prepare conversion data for export
     *
     * @since 1.0.0
     * @param string $date_range Date range
     * @return array Conversion data
     */
    private function prepare_conversion_data($date_range)
    {
        $data = $this->analytics->get_conversion_statistics($date_range);
        $export_data = array();

        // Funnel data
        if (isset($data['funnel'])) {
            $funnel = $data['funnel'];

            $export_data[] = array(
                'Funnel Step' => 'Messages Sent',
                'Count' => ChatShop_Helper::format_number($funnel['messages_sent'] ?? 0),
                'Conversion Rate' => '100%'
            );

            $messages_sent = intval($funnel['messages_sent'] ?? 1);

            $export_data[] = array(
                'Funnel Step' => 'Messages Opened',
                'Count' => ChatShop_Helper::format_number($funnel['messages_opened'] ?? 0),
                'Conversion Rate' => ChatShop_Helper::calculate_percentage($funnel['messages_opened'] ?? 0, $messages_sent) . '%'
            );

            $export_data[] = array(
                'Funnel Step' => 'Links Clicked',
                'Count' => ChatShop_Helper::format_number($funnel['links_clicked'] ?? 0),
                'Conversion Rate' => ChatShop_Helper::calculate_percentage($funnel['links_clicked'] ?? 0, $messages_sent) . '%'
            );

            $export_data[] = array(
                'Funnel Step' => 'Payments Initiated',
                'Count' => ChatShop_Helper::format_number($funnel['payments_initiated'] ?? 0),
                'Conversion Rate' => ChatShop_Helper::calculate_percentage($funnel['payments_initiated'] ?? 0, $messages_sent) . '%'
            );

            $export_data[] = array(
                'Funnel Step' => 'Payments Completed',
                'Count' => ChatShop_Helper::format_number($funnel['payments_completed'] ?? 0),
                'Conversion Rate' => ChatShop_Helper::calculate_percentage($funnel['payments_completed'] ?? 0, $messages_sent) . '%'
            );
        }

        // Gateway performance
        if (isset($data['gateway_performance']) && !empty($data['gateway_performance'])) {
            $export_data[] = array(); // Empty row for spacing

            $export_data[] = array(
                'Gateway' => 'Gateway',
                'Total Attempts' => 'Total Attempts',
                'Successful Payments' => 'Successful Payments',
                'Success Rate' => 'Success Rate',
                'Average Revenue' => 'Average Revenue'
            );

            foreach ($data['gateway_performance'] as $gateway) {
                $success_rate = $gateway['total_attempts'] > 0
                    ? ChatShop_Helper::calculate_percentage($gateway['successful_payments'], $gateway['total_attempts'])
                    : 0;

                $export_data[] = array(
                    'Gateway' => ucfirst($gateway['gateway'] ?? 'Unknown'),
                    'Total Attempts' => ChatShop_Helper::format_number($gateway['total_attempts'] ?? 0),
                    'Successful Payments' => ChatShop_Helper::format_number($gateway['successful_payments'] ?? 0),
                    'Success Rate' => $success_rate . '%',
                    'Average Revenue' => ChatShop_Helper::format_currency($gateway['avg_revenue'] ?? 0)
                );
            }
        }

        return $export_data;
    }

    /**
     * Prepare revenue data for export
     *
     * @since 1.0.0
     * @param string $date_range Date range
     * @return array Revenue data
     */
    private function prepare_revenue_data($date_range)
    {
        $data = $this->analytics->get_revenue_attribution($date_range);
        $export_data = array();

        // Revenue by source
        if (isset($data['by_source']) && !empty($data['by_source'])) {
            $export_data[] = array(
                'Source Type' => 'Source Type',
                'Total Revenue' => 'Total Revenue',
                'Transaction Count' => 'Transaction Count',
                'Average Transaction Value' => 'Average Transaction Value'
            );

            foreach ($data['by_source'] as $source) {
                $export_data[] = array(
                    'Source Type' => ucfirst($source['source_type'] ?? 'Unknown'),
                    'Total Revenue' => ChatShop_Helper::format_currency($source['total_revenue'] ?? 0),
                    'Transaction Count' => ChatShop_Helper::format_number($source['transaction_count'] ?? 0),
                    'Average Transaction Value' => ChatShop_Helper::format_currency($source['avg_transaction_value'] ?? 0)
                );
            }
        }

        // Revenue by gateway
        if (isset($data['by_gateway']) && !empty($data['by_gateway'])) {
            $export_data[] = array(); // Empty row for spacing

            $export_data[] = array(
                'Gateway' => 'Gateway',
                'Total Revenue' => 'Total Revenue',
                'Transaction Count' => 'Transaction Count'
            );

            foreach ($data['by_gateway'] as $gateway) {
                $export_data[] = array(
                    'Gateway' => ucfirst($gateway['gateway'] ?? 'Unknown'),
                    'Total Revenue' => ChatShop_Helper::format_currency($gateway['total_revenue'] ?? 0),
                    'Transaction Count' => ChatShop_Helper::format_number($gateway['transaction_count'] ?? 0)
                );
            }
        }

        return $export_data;
    }

    /**
     * Prepare detailed raw data for export
     *
     * @since 1.0.0
     * @param string $date_range Date range
     * @return array Detailed data
     */
    private function prepare_detailed_data($date_range)
    {
        global $wpdb;

        $table_name = $this->analytics->get_table_name();
        $date_filter = ChatShop_Helper::get_date_range($date_range);

        $raw_data = $wpdb->get_results($wpdb->prepare(
            "SELECT metric_type, metric_name, metric_value, metric_date, 
                    source_type, gateway, revenue, currency, created_at
             FROM {$table_name} 
             WHERE metric_date BETWEEN %s AND %s 
             ORDER BY created_at DESC 
             LIMIT 10000",
            $date_filter['start'],
            $date_filter['end']
        ), ARRAY_A);

        if (empty($raw_data)) {
            return array();
        }

        // Format the data for export
        $export_data = array();

        // Add headers
        $export_data[] = array(
            'Date' => 'Date',
            'Metric Type' => 'Metric Type',
            'Metric Name' => 'Metric Name',
            'Value' => 'Value',
            'Source' => 'Source',
            'Gateway' => 'Gateway',
            'Revenue' => 'Revenue',
            'Currency' => 'Currency',
            'Timestamp' => 'Timestamp'
        );

        foreach ($raw_data as $row) {
            $export_data[] = array(
                'Date' => $row['metric_date'],
                'Metric Type' => ucfirst($row['metric_type']),
                'Metric Name' => str_replace('_', ' ', ucfirst($row['metric_name'])),
                'Value' => ChatShop_Helper::format_number($row['metric_value']),
                'Source' => ucfirst($row['source_type'] ?? 'Unknown'),
                'Gateway' => ucfirst($row['gateway'] ?? 'N/A'),
                'Revenue' => $row['revenue'] > 0 ? ChatShop_Helper::format_currency($row['revenue'], $row['currency']) : 'N/A',
                'Currency' => strtoupper($row['currency'] ?? 'NGN'),
                'Timestamp' => date('Y-m-d H:i:s', strtotime($row['created_at']))
            );
        }

        return $export_data;
    }

    /**
     * Export data in specified format
     *
     * @since 1.0.0
     * @param array  $data Export data
     * @param string $format Export format
     * @param string $export_type Export type
     * @param string $date_range Date range
     * @return array Export result
     */
    private function export_data($data, $format, $export_type, $date_range)
    {
        $filename = $this->generate_filename($export_type, $date_range, $format);

        switch ($format) {
            case 'csv':
                return $this->export_to_csv($data, $filename);
            case 'json':
                return $this->export_to_json($data, $filename);
            default:
                throw new Exception('Unsupported export format');
        }
    }

    /**
     * Export data to CSV format
     *
     * @since 1.0.0
     * @param array  $data Export data
     * @param string $filename Filename
     * @return array Export result
     */
    private function export_to_csv($data, $filename)
    {
        return ChatShop_Helper::export_analytics_to_csv($data, $filename);
    }

    /**
     * Export data to JSON format
     *
     * @since 1.0.0
     * @param array  $data Export data
     * @param string $filename Filename
     * @return array Export result
     */
    private function export_to_json($data, $filename)
    {
        $json_content = wp_json_encode($data, JSON_PRETTY_PRINT);

        return array(
            'content' => $json_content,
            'filename' => $filename,
            'mime_type' => 'application/json'
        );
    }

    /**
     * Generate export filename
     *
     * @since 1.0.0
     * @param string $export_type Export type
     * @param string $date_range Date range
     * @param string $format File format
     * @return string Generated filename
     */
    private function generate_filename($export_type, $date_range, $format)
    {
        $date_suffix = date('Y-m-d-H-i-s');
        $period_label = str_replace(' ', '-', strtolower(ChatShop_Helper::format_period_label($date_range)));

        return "chatshop-analytics-{$export_type}-{$period_label}-{$date_suffix}.{$format}";
    }
}
