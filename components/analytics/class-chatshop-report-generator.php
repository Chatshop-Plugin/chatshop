<?php

/**
 * Analytics Report Generator Class
 *
 * File: components/analytics/class-chatshop-report-generator.php
 * 
 * Generates comprehensive analytics reports including PDF and CSV exports.
 * Provides automated report scheduling and customizable report templates.
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
 * ChatShop Report Generator Class
 *
 * Handles generation of various analytics reports including
 * performance summaries, conversion reports, and revenue analysis.
 *
 * @since 1.0.0
 */
class ChatShop_Report_Generator
{
    /**
     * Metrics collector instance
     *
     * @var ChatShop_Metrics_Collector
     * @since 1.0.0
     */
    private $metrics_collector;

    /**
     * Conversion tracker instance
     *
     * @var ChatShop_Conversion_Tracker
     * @since 1.0.0
     */
    private $conversion_tracker;

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        $this->metrics_collector = new ChatShop_Metrics_Collector();
        $this->conversion_tracker = new ChatShop_Conversion_Tracker();
    }

    /**
     * Generate performance summary report
     *
     * @since 1.0.0
     * @param string $date_range Date range for the report
     * @param string $format Output format (array, html, csv)
     * @return array|string Report data
     */
    public function generate_performance_summary($date_range, $format = 'array')
    {
        if (!chatshop_is_premium()) {
            return array('error' => __('Premium feature required', 'chatshop'));
        }

        $end_date = current_time('Y-m-d');
        $start_date = $this->get_start_date($date_range, $end_date);

        // Collect key metrics
        $analytics_data = $this->metrics_collector->get_analytics_data($start_date, $end_date);
        $conversion_data = $this->metrics_collector->get_conversion_data($start_date, $end_date);
        $funnel_data = $this->conversion_tracker->get_conversion_funnel($date_range);
        $attribution_data = $this->conversion_tracker->get_attribution_analysis($date_range);

        // Calculate summary metrics
        $summary = $this->calculate_summary_metrics($analytics_data, $conversion_data);

        $report_data = array(
            'report_info' => array(
                'title' => __('Performance Summary Report', 'chatshop'),
                'date_range' => $date_range,
                'start_date' => $start_date,
                'end_date' => $end_date,
                'generated_at' => current_time('mysql')
            ),
            'summary_metrics' => $summary,
            'conversion_funnel' => $funnel_data,
            'attribution_analysis' => $attribution_data,
            'detailed_metrics' => array(
                'analytics' => $analytics_data,
                'conversions' => $conversion_data
            )
        );

        return $this->format_report($report_data, $format);
    }

    /**
     * Generate revenue report
     *
     * @since 1.0.0
     * @param string $date_range Date range for the report
     * @param string $group_by Grouping (day, week, month)
     * @param string $format Output format
     * @return array|string Report data
     */
    public function generate_revenue_report($date_range, $group_by = 'day', $format = 'array')
    {
        if (!chatshop_is_premium()) {
            return array('error' => __('Premium feature required', 'chatshop'));
        }

        $trends = $this->conversion_tracker->get_conversion_trends($date_range, $group_by);
        $attribution = $this->conversion_tracker->get_attribution_analysis($date_range);
        $clv_data = $this->conversion_tracker->get_customer_lifetime_value($date_range);

        $report_data = array(
            'report_info' => array(
                'title' => __('Revenue Analysis Report', 'chatshop'),
                'date_range' => $date_range,
                'group_by' => $group_by,
                'generated_at' => current_time('mysql')
            ),
            'revenue_trends' => $trends,
            'revenue_attribution' => $attribution,
            'customer_lifetime_value' => $clv_data,
            'revenue_summary' => $this->calculate_revenue_summary($trends)
        );

        return $this->format_report($report_data, $format);
    }

    /**
     * Generate campaign performance report
     *
     * @since 1.0.0
     * @param string $date_range Date range for the report
     * @param string $format Output format
     * @return array|string Report data
     */
    public function generate_campaign_report($date_range, $format = 'array')
    {
        if (!chatshop_is_premium()) {
            return array('error' => __('Premium feature required', 'chatshop'));
        }

        $campaigns = $this->conversion_tracker->get_top_campaigns($date_range, 50);
        $funnel_data = $this->conversion_tracker->get_conversion_funnel($date_range, 'whatsapp');

        $report_data = array(
            'report_info' => array(
                'title' => __('Campaign Performance Report', 'chatshop'),
                'date_range' => $date_range,
                'generated_at' => current_time('mysql')
            ),
            'campaign_performance' => $campaigns,
            'whatsapp_funnel' => $funnel_data,
            'campaign_summary' => $this->calculate_campaign_summary($campaigns)
        );

        return $this->format_report($report_data, $format);
    }

    /**
     * Generate custom report
     *
     * @since 1.0.0
     * @param array $report_config Report configuration
     * @param string $format Output format
     * @return array|string Report data
     */
    public function generate_custom_report($report_config, $format = 'array')
    {
        if (!chatshop_is_premium()) {
            return array('error' => __('Premium feature required', 'chatshop'));
        }

        $date_range = $report_config['date_range'] ?? '30days';
        $metrics = $report_config['metrics'] ?? array();
        $filters = $report_config['filters'] ?? array();

        $report_data = array(
            'report_info' => array(
                'title' => $report_config['title'] ?? __('Custom Analytics Report', 'chatshop'),
                'date_range' => $date_range,
                'generated_at' => current_time('mysql')
            )
        );

        // Add requested metrics
        foreach ($metrics as $metric) {
            switch ($metric) {
                case 'conversion_funnel':
                    $report_data['conversion_funnel'] = $this->conversion_tracker->get_conversion_funnel($date_range);
                    break;
                case 'revenue_trends':
                    $group_by = $filters['group_by'] ?? 'day';
                    $report_data['revenue_trends'] = $this->conversion_tracker->get_conversion_trends($date_range, $group_by);
                    break;
                case 'attribution':
                    $report_data['attribution'] = $this->conversion_tracker->get_attribution_analysis($date_range);
                    break;
                case 'campaigns':
                    $limit = $filters['campaign_limit'] ?? 20;
                    $report_data['campaigns'] = $this->conversion_tracker->get_top_campaigns($date_range, $limit);
                    break;
                case 'customer_ltv':
                    $report_data['customer_ltv'] = $this->conversion_tracker->get_customer_lifetime_value($date_range);
                    break;
            }
        }

        return $this->format_report($report_data, $format);
    }

    /**
     * Export report to CSV
     *
     * @since 1.0.0
     * @param array $report_data Report data
     * @param string $filename Filename for export
     * @return array Export result
     */
    public function export_to_csv($report_data, $filename = '')
    {
        if (!chatshop_is_premium()) {
            return array('error' => __('Premium feature required', 'chatshop'));
        }

        if (empty($filename)) {
            $filename = 'chatshop-analytics-' . date('Y-m-d-H-i-s') . '.csv';
        }

        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['path'] . '/' . $filename;

        $fp = fopen($file_path, 'w');
        if (!$fp) {
            return array('error' => __('Failed to create export file', 'chatshop'));
        }

        // Write CSV headers and data
        $this->write_csv_data($fp, $report_data);
        fclose($fp);

        return array(
            'success' => true,
            'file_path' => $file_path,
            'file_url' => $upload_dir['url'] . '/' . $filename,
            'filename' => $filename
        );
    }

    /**
     * Schedule automated report
     *
     * @since 1.0.0
     * @param array $schedule_config Schedule configuration
     * @return array Schedule result
     */
    public function schedule_report($schedule_config)
    {
        if (!chatshop_is_premium()) {
            return array('error' => __('Premium feature required', 'chatshop'));
        }

        $schedule_id = uniqid('chatshop_report_');
        $schedules = get_option('chatshop_scheduled_reports', array());

        $schedule_config['id'] = $schedule_id;
        $schedule_config['created_at'] = current_time('mysql');
        $schedule_config['status'] = 'active';

        $schedules[$schedule_id] = $schedule_config;
        update_option('chatshop_scheduled_reports', $schedules);

        // Schedule the cron event
        $recurrence = $schedule_config['frequency'] ?? 'weekly';
        wp_schedule_event(time(), $recurrence, 'chatshop_generate_scheduled_report', array($schedule_id));

        return array(
            'success' => true,
            'schedule_id' => $schedule_id,
            'message' => __('Report scheduled successfully', 'chatshop')
        );
    }

    /**
     * Calculate summary metrics
     *
     * @since 1.0.0
     * @param array $analytics_data Analytics data
     * @param array $conversion_data Conversion data
     * @return array Summary metrics
     */
    private function calculate_summary_metrics($analytics_data, $conversion_data)
    {
        $total_messages = 0;
        $total_interactions = 0;
        $total_conversions = 0;
        $total_revenue = 0;

        foreach ($analytics_data as $data) {
            if ($data['metric_type'] === 'whatsapp' && $data['metric_name'] === 'message_sent') {
                $total_messages += $data['total_value'];
            }
            if ($data['metric_type'] === 'interaction') {
                $total_interactions += $data['total_value'];
            }
        }

        foreach ($conversion_data as $data) {
            $total_conversions += $data['total_conversions'];
            $total_revenue += $data['total_revenue'];
        }

        $conversion_rate = $total_messages > 0 ? round(($total_conversions / $total_messages) * 100, 4) : 0;
        $avg_order_value = $total_conversions > 0 ? round($total_revenue / $total_conversions, 2) : 0;

        return array(
            'total_messages' => $total_messages,
            'total_interactions' => $total_interactions,
            'total_conversions' => $total_conversions,
            'total_revenue' => $total_revenue,
            'conversion_rate' => $conversion_rate,
            'avg_order_value' => $avg_order_value,
            'interaction_rate' => $total_messages > 0 ? round(($total_interactions / $total_messages) * 100, 2) : 0
        );
    }

    /**
     * Calculate revenue summary
     *
     * @since 1.0.0
     * @param array $trends Revenue trends data
     * @return array Revenue summary
     */
    private function calculate_revenue_summary($trends)
    {
        if (empty($trends)) {
            return array(
                'total_revenue' => 0,
                'total_conversions' => 0,
                'avg_daily_revenue' => 0,
                'growth_rate' => 0
            );
        }

        $total_revenue = array_sum(array_column($trends, 'revenue'));
        $total_conversions = array_sum(array_column($trends, 'conversions'));
        $avg_daily_revenue = count($trends) > 0 ? $total_revenue / count($trends) : 0;

        // Calculate growth rate (compare first and last periods)
        $growth_rate = 0;
        if (count($trends) >= 2) {
            $first_revenue = floatval($trends[0]['revenue']);
            $last_revenue = floatval($trends[count($trends) - 1]['revenue']);
            if ($first_revenue > 0) {
                $growth_rate = round((($last_revenue - $first_revenue) / $first_revenue) * 100, 2);
            }
        }

        return array(
            'total_revenue' => $total_revenue,
            'total_conversions' => $total_conversions,
            'avg_daily_revenue' => round($avg_daily_revenue, 2),
            'growth_rate' => $growth_rate
        );
    }

    /**
     * Calculate campaign summary
     *
     * @since 1.0.0
     * @param array $campaigns Campaign data
     * @return array Campaign summary
     */
    private function calculate_campaign_summary($campaigns)
    {
        if (empty($campaigns)) {
            return array(
                'total_campaigns' => 0,
                'total_messages_sent' => 0,
                'avg_click_rate' => 0,
                'avg_conversion_rate' => 0,
                'total_campaign_revenue' => 0
            );
        }

        $total_campaigns = count($campaigns);
        $total_messages = array_sum(array_column($campaigns, 'messages_sent'));
        $total_clicks = array_sum(array_column($campaigns, 'clicks'));
        $total_conversions = array_sum(array_column($campaigns, 'conversions'));
        $total_revenue = array_sum(array_column($campaigns, 'revenue'));

        $avg_click_rate = $total_messages > 0 ? round(($total_clicks / $total_messages) * 100, 2) : 0;
        $avg_conversion_rate = $total_clicks > 0 ? round(($total_conversions / $total_clicks) * 100, 2) : 0;

        return array(
            'total_campaigns' => $total_campaigns,
            'total_messages_sent' => $total_messages,
            'avg_click_rate' => $avg_click_rate,
            'avg_conversion_rate' => $avg_conversion_rate,
            'total_campaign_revenue' => $total_revenue
        );
    }

    /**
     * Format report based on requested format
     *
     * @since 1.0.0
     * @param array $report_data Report data
     * @param string $format Format type
     * @return array|string Formatted report
     */
    private function format_report($report_data, $format)
    {
        switch ($format) {
            case 'html':
                return $this->format_html_report($report_data);
            case 'csv':
                return $this->export_to_csv($report_data);
            default:
                return $report_data;
        }
    }

    /**
     * Format report as HTML
     *
     * @since 1.0.0
     * @param array $report_data Report data
     * @return string HTML formatted report
     */
    private function format_html_report($report_data)
    {
        $html = '<div class="chatshop-report">';
        $html .= '<h2>' . esc_html($report_data['report_info']['title']) . '</h2>';
        $html .= '<p><strong>' . __('Date Range:', 'chatshop') . '</strong> ' . esc_html($report_data['report_info']['date_range']) . '</p>';
        $html .= '<p><strong>' . __('Generated:', 'chatshop') . '</strong> ' . esc_html($report_data['report_info']['generated_at']) . '</p>';

        // Add summary metrics if available
        if (isset($report_data['summary_metrics'])) {
            $html .= '<h3>' . __('Summary Metrics', 'chatshop') . '</h3>';
            $html .= '<table class="widefat">';
            foreach ($report_data['summary_metrics'] as $key => $value) {
                $label = str_replace('_', ' ', ucwords($key));
                $html .= '<tr><td>' . esc_html($label) . '</td><td>' . esc_html($value) . '</td></tr>';
            }
            $html .= '</table>';
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Write CSV data to file
     *
     * @since 1.0.0
     * @param resource $fp File pointer
     * @param array $report_data Report data
     */
    private function write_csv_data($fp, $report_data)
    {
        // Write report header
        fputcsv($fp, array('ChatShop Analytics Report'));
        fputcsv($fp, array('Generated: ' . $report_data['report_info']['generated_at']));
        fputcsv($fp, array('Date Range: ' . $report_data['report_info']['date_range']));
        fputcsv($fp, array(''));

        // Write summary metrics
        if (isset($report_data['summary_metrics'])) {
            fputcsv($fp, array('Summary Metrics'));
            fputcsv($fp, array('Metric', 'Value'));
            foreach ($report_data['summary_metrics'] as $key => $value) {
                fputcsv($fp, array(str_replace('_', ' ', ucwords($key)), $value));
            }
            fputcsv($fp, array(''));
        }

        // Write detailed data sections
        foreach ($report_data as $section => $data) {
            if ($section === 'report_info' || $section === 'summary_metrics') {
                continue;
            }

            if (is_array($data) && !empty($data)) {
                fputcsv($fp, array(str_replace('_', ' ', ucwords($section))));
                if (isset($data[0]) && is_array($data[0])) {
                    // Write headers
                    fputcsv($fp, array_keys($data[0]));
                    // Write data rows
                    foreach ($data as $row) {
                        fputcsv($fp, array_values($row));
                    }
                }
                fputcsv($fp, array(''));
            }
        }
    }

    /**
     * Get start date from date range
     *
     * @since 1.0.0
     * @param string $date_range Date range
     * @param string $end_date End date
     * @return string Start date
     */
    private function get_start_date($date_range, $end_date)
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

        return date('Y-m-d', strtotime("-{$days} days", strtotime($end_date)));
    }
}
