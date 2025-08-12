<?php

/**
 * Analytics Export Component Class - COMPLETE FILE
 *
 * File: components/analytics/class-chatshop-analytics-export.php
 * 
 * Handles exporting analytics data to various formats (CSV, PDF, etc.)
 * and provides automated reporting capabilities with comprehensive
 * data processing and secure file handling.
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
if (class_exists('ChatShop\\ChatShop_Analytics_Export')) {
    return;
}

/**
 * ChatShop Analytics Export Class - COMPLETE VERSION
 *
 * Manages analytics data export and reporting functionality with
 * support for multiple formats, secure downloads, and automated cleanup.
 *
 * @since 1.0.0
 */
class ChatShop_Analytics_Export
{
    /**
     * Analytics component instance
     *
     * @var ChatShop_Analytics
     * @since 1.0.0
     */
    private $analytics;

    /**
     * Export formats supported
     *
     * @var array
     * @since 1.0.0
     */
    private $supported_formats = array('csv', 'json', 'txt');

    /**
     * Maximum records per export
     *
     * @var int
     * @since 1.0.0
     */
    private $max_records = 10000;

    /**
     * Export directory path
     *
     * @var string
     * @since 1.0.0
     */
    private $export_dir;

    /**
     * Export URL base
     *
     * @var string
     * @since 1.0.0
     */
    private $export_url;

    /**
     * Constructor
     *
     * @since 1.0.0
     * @param ChatShop_Analytics $analytics Analytics component instance
     */
    public function __construct($analytics = null)
    {
        $this->analytics = $analytics;
        $this->init_export_paths();
        $this->init_hooks();
    }

    /**
     * Initialize export directory paths
     *
     * @since 1.0.0
     */
    private function init_export_paths()
    {
        $upload_dir = wp_upload_dir();
        $this->export_dir = $upload_dir['basedir'] . '/chatshop-exports/';
        $this->export_url = $upload_dir['baseurl'] . '/chatshop-exports/';

        // Create directory if it doesn't exist
        if (!file_exists($this->export_dir)) {
            wp_mkdir_p($this->export_dir);

            // Create .htaccess for security
            $htaccess_content = "Order deny,allow\nDeny from all\n";
            file_put_contents($this->export_dir . '.htaccess', $htaccess_content);

            // Create index.php for security
            $index_content = "<?php\n// Silence is golden.\n";
            file_put_contents($this->export_dir . 'index.php', $index_content);
        }
    }

    /**
     * Initialize hooks
     *
     * @since 1.0.0
     */
    private function init_hooks()
    {
        // AJAX handlers
        add_action('wp_ajax_chatshop_export_analytics', array($this, 'ajax_export_analytics'));
        add_action('wp_ajax_chatshop_download_export', array($this, 'ajax_download_export'));
        add_action('wp_ajax_chatshop_get_export_status', array($this, 'ajax_get_export_status'));

        // Download handler
        add_action('init', array($this, 'handle_export_download'));

        // Cleanup scheduler
        add_action('chatshop_daily_cleanup', array($this, 'cleanup_old_exports'));

        // Schedule cleanup if not already scheduled
        if (!wp_next_scheduled('chatshop_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'chatshop_daily_cleanup');
        }
    }

    /**
     * AJAX handler for analytics export
     *
     * @since 1.0.0
     */
    public function ajax_export_analytics()
    {
        // Verify nonce
        check_ajax_referer('chatshop_admin_nonce', 'nonce');

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Insufficient permissions', 'chatshop')
            ));
        }

        // Check premium access
        if (!chatshop_is_premium()) {
            wp_send_json_error(array(
                'message' => __('Export functionality requires premium features.', 'chatshop')
            ));
        }

        // Get and validate parameters
        $date_range = sanitize_text_field($_POST['date_range'] ?? '30_days');
        $format = sanitize_text_field($_POST['format'] ?? 'csv');
        $export_type = sanitize_text_field($_POST['export_type'] ?? 'overview');

        if (!in_array($format, $this->supported_formats)) {
            wp_send_json_error(array(
                'message' => __('Unsupported export format.', 'chatshop')
            ));
        }

        try {
            // Prepare export data
            $export_data = $this->prepare_export_data($date_range, $export_type);

            if (empty($export_data)) {
                wp_send_json_error(array(
                    'message' => __('No data available for export.', 'chatshop')
                ));
            }

            // Limit records if too many
            if (count($export_data) > $this->max_records) {
                $export_data = array_slice($export_data, 0, $this->max_records);
            }

            // Generate export
            $export_result = $this->create_export_file($export_data, $format, $export_type, $date_range);

            wp_send_json_success($export_result);
        } catch (Exception $e) {
            chatshop_log('Analytics export error: ' . $e->getMessage(), 'error');

            wp_send_json_error(array(
                'message' => __('Export failed. Please try again.', 'chatshop'),
                'debug' => defined('WP_DEBUG') && WP_DEBUG ? $e->getMessage() : ''
            ));
        }
    }

    /**
     * AJAX handler for export download
     *
     * @since 1.0.0
     */
    public function ajax_download_export()
    {
        check_ajax_referer('chatshop_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'chatshop')));
        }

        $filename = sanitize_file_name($_POST['filename'] ?? '');

        if (empty($filename)) {
            wp_send_json_error(array('message' => __('Invalid filename', 'chatshop')));
        }

        $file_path = $this->export_dir . $filename;

        if (!file_exists($file_path)) {
            wp_send_json_error(array('message' => __('Export file not found', 'chatshop')));
        }

        // Generate secure download URL
        $download_url = add_query_arg(array(
            'chatshop_action' => 'download_export',
            'file' => $filename,
            'nonce' => wp_create_nonce('chatshop_download_export'),
            'timestamp' => time()
        ), admin_url('admin.php'));

        wp_send_json_success(array(
            'download_url' => $download_url,
            'filename' => $filename,
            'file_size' => size_format(filesize($file_path))
        ));
    }

    /**
     * AJAX handler for export status
     *
     * @since 1.0.0
     */
    public function ajax_get_export_status()
    {
        check_ajax_referer('chatshop_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'chatshop')));
        }

        $stats = $this->get_export_stats();
        wp_send_json_success($stats);
    }

    /**
     * Prepare data for export based on type
     *
     * @since 1.0.0
     * @param string $date_range Date range for export
     * @param string $export_type Type of data to export
     * @return array Prepared export data
     */
    private function prepare_export_data($date_range, $export_type)
    {
        if (!$this->analytics) {
            throw new Exception('Analytics component not available');
        }

        switch ($export_type) {
            case 'overview':
                return $this->prepare_overview_data($date_range);
            case 'conversions':
                return $this->prepare_conversion_data($date_range);
            case 'revenue':
                return $this->prepare_revenue_data($date_range);
            case 'performance':
                return $this->prepare_performance_data($date_range);
            case 'detailed':
                return $this->prepare_detailed_data($date_range);
            case 'contacts':
                return $this->prepare_contacts_data($date_range);
            case 'messages':
                return $this->prepare_messages_data($date_range);
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

        // Add summary rows
        if (isset($data['totals'])) {
            $export_data[] = array(
                'Metric' => 'Total Revenue',
                'Value' => chatshop_format_currency($data['totals']['revenue'] ?? 0),
                'Period' => $this->format_period_label($date_range),
                'Generated' => current_time('Y-m-d H:i:s')
            );

            $export_data[] = array(
                'Metric' => 'Total Payments',
                'Value' => number_format($data['totals']['payments'] ?? 0),
                'Period' => $this->format_period_label($date_range),
                'Generated' => current_time('Y-m-d H:i:s')
            );

            $export_data[] = array(
                'Metric' => 'WhatsApp Interactions',
                'Value' => number_format($data['totals']['interactions'] ?? 0),
                'Period' => $this->format_period_label($date_range),
                'Generated' => current_time('Y-m-d H:i:s')
            );

            $export_data[] = array(
                'Metric' => 'Conversion Rate',
                'Value' => ($data['conversion_rate'] ?? 0) . '%',
                'Period' => $this->format_period_label($date_range),
                'Generated' => current_time('Y-m-d H:i:s')
            );

            $export_data[] = array(
                'Metric' => 'Average Order Value',
                'Value' => chatshop_format_currency($data['average_order_value'] ?? 0),
                'Period' => $this->format_period_label($date_range),
                'Generated' => current_time('Y-m-d H:i:s')
            );

            $export_data[] = array(
                'Metric' => 'Messages Sent',
                'Value' => number_format($data['totals']['messages_sent'] ?? 0),
                'Period' => $this->format_period_label($date_range),
                'Generated' => current_time('Y-m-d H:i:s')
            );

            $export_data[] = array(
                'Metric' => 'Message Success Rate',
                'Value' => ($data['message_success_rate'] ?? 0) . '%',
                'Period' => $this->format_period_label($date_range),
                'Generated' => current_time('Y-m-d H:i:s')
            );
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
        $data = $this->analytics->get_analytics_data($date_range, 'conversions');
        $export_data = array();

        // Daily conversions
        if (isset($data['daily_conversions'])) {
            foreach ($data['daily_conversions'] as $daily) {
                $export_data[] = array(
                    'Type' => 'Daily Conversion',
                    'Date' => $daily->date,
                    'Conversions' => $daily->conversions,
                    'Revenue' => chatshop_format_currency($daily->revenue),
                    'Average per Conversion' => $daily->conversions > 0 ? chatshop_format_currency($daily->revenue / $daily->conversions) : '₦0.00'
                );
            }
        }

        // Gateway performance
        if (isset($data['gateway_performance'])) {
            foreach ($data['gateway_performance'] as $gateway) {
                $export_data[] = array(
                    'Type' => 'Gateway Performance',
                    'Gateway' => $gateway->gateway,
                    'Conversions' => $gateway->conversions,
                    'Revenue' => chatshop_format_currency($gateway->revenue),
                    'Average Order Value' => chatshop_format_currency($gateway->avg_order_value)
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
        $data = $this->analytics->get_analytics_data($date_range, 'revenue');
        $export_data = array();

        // Revenue by source
        if (isset($data['revenue_by_source'])) {
            foreach ($data['revenue_by_source'] as $source) {
                $export_data[] = array(
                    'Type' => 'Revenue by Source',
                    'Source' => ucfirst($source->source),
                    'Transactions' => $source->transactions,
                    'Revenue' => chatshop_format_currency($source->revenue),
                    'Average per Transaction' => $source->transactions > 0 ? chatshop_format_currency($source->revenue / $source->transactions) : '₦0.00'
                );
            }
        }

        // Monthly revenue
        if (isset($data['monthly_revenue'])) {
            foreach ($data['monthly_revenue'] as $monthly) {
                $export_data[] = array(
                    'Type' => 'Monthly Revenue',
                    'Month' => $monthly->month,
                    'Transactions' => $monthly->transactions,
                    'Revenue' => chatshop_format_currency($monthly->revenue),
                    'Average per Transaction' => $monthly->transactions > 0 ? chatshop_format_currency($monthly->revenue / $monthly->transactions) : '₦0.00'
                );
            }
        }

        return $export_data;
    }

    /**
     * Prepare performance data for export
     *
     * @since 1.0.0
     * @param string $date_range Date range
     * @return array Performance data
     */
    private function prepare_performance_data($date_range)
    {
        $data = $this->analytics->get_analytics_data($date_range, 'performance');
        $export_data = array();

        // Top performing contacts
        if (isset($data['top_contacts'])) {
            foreach ($data['top_contacts'] as $contact) {
                $export_data[] = array(
                    'Contact Phone' => $contact->contact_phone,
                    'Total Purchases' => $contact->purchases,
                    'Total Spent' => chatshop_format_currency($contact->total_spent),
                    'WhatsApp Interactions' => $contact->interactions,
                    'Average per Purchase' => $contact->purchases > 0 ? chatshop_format_currency($contact->total_spent / $contact->purchases) : '₦0.00',
                    'Interaction to Purchase Ratio' => $contact->interactions > 0 ? round(($contact->purchases / $contact->interactions) * 100, 2) . '%' : '0%'
                );
            }
        }

        return $export_data;
    }

    /**
     * Prepare detailed data for export
     *
     * @since 1.0.0
     * @param string $date_range Date range
     * @return array Detailed data
     */
    private function prepare_detailed_data($date_range)
    {
        global $wpdb;

        if (!$this->analytics) {
            return array();
        }

        $dates = $this->get_date_range($date_range);
        $table_name = $wpdb->prefix . 'chatshop_analytics';

        $query = $wpdb->prepare("
            SELECT event_type, event_subtype, contact_phone, amount, currency, 
                   gateway, source, reference, status, created_at, metadata
            FROM {$table_name}
            WHERE created_at BETWEEN %s AND %s
            ORDER BY created_at DESC
            LIMIT %d
        ", $dates['start'], $dates['end'], $this->max_records);

        $results = $wpdb->get_results($query);
        $export_data = array();

        foreach ($results as $row) {
            $metadata = !empty($row->metadata) ? json_decode($row->metadata, true) : array();

            $export_data[] = array(
                'Date' => $row->created_at,
                'Event Type' => ucfirst($row->event_type),
                'Event Subtype' => ucfirst($row->event_subtype),
                'Contact Phone' => $row->contact_phone ?: 'N/A',
                'Amount' => $row->amount ? chatshop_format_currency($row->amount, $row->currency) : 'N/A',
                'Gateway' => $row->gateway ?: 'N/A',
                'Source' => ucfirst($row->source),
                'Reference' => $row->reference ?: 'N/A',
                'Status' => ucfirst($row->status),
                'Additional Info' => !empty($metadata) ? wp_json_encode($metadata) : 'N/A'
            );
        }

        return $export_data;
    }

    /**
     * Prepare contacts data for export
     *
     * @since 1.0.0
     * @param string $date_range Date range
     * @return array Contacts data
     */
    private function prepare_contacts_data($date_range)
    {
        global $wpdb;

        $dates = $this->get_date_range($date_range);
        $table_name = $wpdb->prefix . 'chatshop_analytics';

        $query = $wpdb->prepare("
            SELECT contact_phone,
                   COUNT(CASE WHEN event_type = 'contact_interaction' THEN 1 END) as interactions,
                   COUNT(CASE WHEN event_type = 'payment' AND status = 'completed' THEN 1 END) as payments,
                   SUM(CASE WHEN event_type = 'payment' AND status = 'completed' THEN amount ELSE 0 END) as total_spent,
                   MAX(created_at) as last_activity
            FROM {$table_name}
            WHERE contact_phone != '' AND created_at BETWEEN %s AND %s
            GROUP BY contact_phone
            ORDER BY total_spent DESC, interactions DESC
            LIMIT %d
        ", $dates['start'], $dates['end'], $this->max_records);

        $results = $wpdb->get_results($query);
        $export_data = array();

        foreach ($results as $row) {
            $conversion_rate = $row->interactions > 0 ? round(($row->payments / $row->interactions) * 100, 2) : 0;

            $export_data[] = array(
                'Contact Phone' => $row->contact_phone,
                'Total Interactions' => $row->interactions,
                'Total Payments' => $row->payments,
                'Total Spent' => chatshop_format_currency($row->total_spent),
                'Conversion Rate' => $conversion_rate . '%',
                'Last Activity' => $row->last_activity,
                'Customer Value' => $row->payments > 0 ? 'High' : ($row->interactions > 5 ? 'Medium' : 'Low')
            );
        }

        return $export_data;
    }

    /**
     * Prepare messages data for export
     *
     * @since 1.0.0
     * @param string $date_range Date range
     * @return array Messages data
     */
    private function prepare_messages_data($date_range)
    {
        global $wpdb;

        $dates = $this->get_date_range($date_range);
        $table_name = $wpdb->prefix . 'chatshop_analytics';

        $query = $wpdb->prepare("
            SELECT contact_phone, event_subtype, status, created_at, metadata
            FROM {$table_name}
            WHERE event_type = 'whatsapp_message' 
            AND created_at BETWEEN %s AND %s
            ORDER BY created_at DESC
            LIMIT %d
        ", $dates['start'], $dates['end'], $this->max_records);

        $results = $wpdb->get_results($query);
        $export_data = array();

        foreach ($results as $row) {
            $metadata = !empty($row->metadata) ? json_decode($row->metadata, true) : array();
            $message_preview = isset($metadata['message']) ? substr($metadata['message'], 0, 50) . '...' : 'N/A';

            $export_data[] = array(
                'Date' => $row->created_at,
                'Contact Phone' => $row->contact_phone,
                'Message Type' => ucfirst($row->event_subtype),
                'Status' => ucfirst($row->status),
                'Message Preview' => $message_preview,
                'Success' => $row->status === 'completed' ? 'Yes' : 'No'
            );
        }

        return $export_data;
    }

    /**
     * Create export file
     *
     * @since 1.0.0
     * @param array $data Data to export
     * @param string $format Export format
     * @param string $export_type Export type
     * @param string $date_range Date range
     * @return array Export result
     */
    private function create_export_file($data, $format, $export_type, $date_range)
    {
        $filename = $this->generate_filename($export_type, $date_range, $format);
        $file_path = $this->export_dir . $filename;

        // Create export based on format
        switch ($format) {
            case 'csv':
                $this->export_to_csv($data, $file_path);
                break;
            case 'json':
                $this->export_to_json($data, $file_path, $export_type, $date_range);
                break;
            case 'txt':
                $this->export_to_txt($data, $file_path, $export_type);
                break;
            default:
                throw new Exception('Unsupported export format');
        }

        // Verify file was created
        if (!file_exists($file_path)) {
            throw new Exception('Failed to create export file');
        }

        // Generate download URL
        $download_url = add_query_arg(array(
            'chatshop_action' => 'download_export',
            'file' => $filename,
            'nonce' => wp_create_nonce('chatshop_download_export')
        ), admin_url('admin.php'));

        return array(
            'filename' => $filename,
            'download_url' => $download_url,
            'file_size' => size_format(filesize($file_path)),
            'record_count' => count($data),
            'format' => $format,
            'export_type' => $export_type,
            'created_at' => current_time('Y-m-d H:i:s')
        );
    }

    /**
     * Export data to CSV format
     *
     * @since 1.0.0
     * @param array $data Data to export
     * @param string $file_path File path
     */
    private function export_to_csv($data, $file_path)
    {
        $handle = fopen($file_path, 'w');

        if (!$handle) {
            throw new Exception('Cannot create CSV export file');
        }

        // Write UTF-8 BOM for proper Excel compatibility
        fwrite($handle, "\xEF\xBB\xBF");

        // Write headers and data
        if (!empty($data)) {
            // Write headers
            fputcsv($handle, array_keys($data[0]));

            // Write data rows
            foreach ($data as $row) {
                fputcsv($handle, $row);
            }
        }

        fclose($handle);
    }

    /**
     * Export data to JSON format
     *
     * @since 1.0.0
     * @param array $data Data to export
     * @param string $file_path File path
     * @param string $export_type Export type
     * @param string $date_range Date range
     */
    private function export_to_json($data, $file_path, $export_type, $date_range)
    {
        $json_data = array(
            'export_info' => array(
                'generated_at' => current_time('Y-m-d H:i:s'),
                'plugin_version' => defined('CHATSHOP_VERSION') ? CHATSHOP_VERSION : '1.0.0',
                'export_type' => $export_type,
                'date_range' => $date_range,
                'record_count' => count($data),
                'format' => 'json'
            ),
            'data' => $data
        );

        $json_string = wp_json_encode($json_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if (file_put_contents($file_path, $json_string) === false) {
            throw new Exception('Cannot write JSON export file');
        }
    }

    /**
     * Export data to TXT format
     *
     * @since 1.0.0
     * @param array $data Data to export
     * @param string $file_path File path
     * @param string $export_type Export type
     */
    private function export_to_txt($data, $file_path, $export_type)
    {
        $content = "ChatShop Analytics Export\n";
        $content .= "Export Type: " . ucfirst($export_type) . "\n";
        $content .= "Generated: " . current_time('Y-m-d H:i:s') . "\n";
        $content .= "Records: " . count($data) . "\n";
        $content .= str_repeat("=", 50) . "\n\n";

        foreach ($data as $index => $row) {
            $content .= "Record " . ($index + 1) . ":\n";
            foreach ($row as $key => $value) {
                $content .= "  " . $key . ": " . $value . "\n";
            }
            $content .= "\n";
        }

        if (file_put_contents($file_path, $content) === false) {
            throw new Exception('Cannot write TXT export file');
        }
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
        $timestamp = current_time('Y-m-d_H-i-s');
        $safe_type = sanitize_file_name($export_type);
        $safe_range = sanitize_file_name($date_range);

        return "chatshop-analytics-{$safe_type}-{$safe_range}-{$timestamp}.{$format}";
    }

    /**
     * Handle export file download
     *
     * @since 1.0.0
     */
    public function handle_export_download()
    {
        if (!isset($_GET['chatshop_action']) || $_GET['chatshop_action'] !== 'download_export') {
            return;
        }

        // Verify nonce
        if (!wp_verify_nonce($_GET['nonce'], 'chatshop_download_export')) {
            wp_die(__('Security check failed', 'chatshop'));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'chatshop'));
        }

        $filename = sanitize_file_name($_GET['file']);
        $file_path = $this->export_dir . $filename;

        // Security checks
        if (!file_exists($file_path)) {
            wp_die(__('Export file not found', 'chatshop'));
        }

        // Ensure file is in exports directory
        $real_file_path = realpath($file_path);
        $real_export_dir = realpath($this->export_dir);

        if (strpos($real_file_path, $real_export_dir) !== 0) {
            wp_die(__('Invalid file path', 'chatshop'));
        }

        // Determine content type
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $content_types = array(
            'csv' => 'text/csv',
            'json' => 'application/json',
            'txt' => 'text/plain'
        );

        $content_type = isset($content_types[$extension]) ? $content_types[$extension] : 'application/octet-stream';

        // Send file headers
        header('Content-Type: ' . $content_type);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($file_path));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Clear output buffer
        while (ob_get_level()) {
            ob_end_clean();
        }

        // Send file content
        readfile($file_path);

        // Log download
        chatshop_log("Analytics export downloaded: {$filename}", 'info');

        // Optional: Delete file after download for security
        // unlink($file_path);

        exit;
    }

    /**
     * Get date range array from period string
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
            case 'this_year':
                $start_date = date('Y-01-01 00:00:00');
                break;
            case 'last_year':
                $start_date = date('Y-01-01 00:00:00', strtotime('first day of January last year'));
                $end_date = date('Y-12-31 23:59:59', strtotime('last day of December last year'));
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
     * Format period label for display
     *
     * @since 1.0.0
     * @param string $period Period identifier
     * @return string Formatted label
     */
    private function format_period_label($period)
    {
        $labels = array(
            '7_days' => __('Last 7 Days', 'chatshop'),
            '30_days' => __('Last 30 Days', 'chatshop'),
            '90_days' => __('Last 90 Days', 'chatshop'),
            'this_month' => __('This Month', 'chatshop'),
            'last_month' => __('Last Month', 'chatshop'),
            'this_year' => __('This Year', 'chatshop'),
            'last_year' => __('Last Year', 'chatshop')
        );

        return isset($labels[$period]) ? $labels[$period] : __('Custom Period', 'chatshop');
    }

    /**
     * Clean up old export files
     *
     * @since 1.0.0
     * @param int $days_old Files older than this many days will be deleted
     */
    public function cleanup_old_exports($days_old = 7)
    {
        if (!is_dir($this->export_dir)) {
            return;
        }

        $files = glob($this->export_dir . 'chatshop-analytics-*');
        $cutoff_time = time() - ($days_old * 24 * 60 * 60);
        $cleaned_count = 0;

        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $cutoff_time) {
                if (unlink($file)) {
                    $cleaned_count++;
                }
            }
        }

        if ($cleaned_count > 0) {
            chatshop_log("Cleaned up {$cleaned_count} old export files older than {$days_old} days", 'info');
        }
    }

    /**
     * Get export statistics
     *
     * @since 1.0.0
     * @return array Export statistics
     */
    public function get_export_stats()
    {
        if (!is_dir($this->export_dir)) {
            return array(
                'total_files' => 0,
                'total_size' => 0,
                'total_size_formatted' => '0 B',
                'oldest_file' => null,
                'newest_file' => null,
                'file_types' => array()
            );
        }

        $files = glob($this->export_dir . 'chatshop-analytics-*');
        $total_size = 0;
        $oldest_time = null;
        $newest_time = null;
        $file_types = array();

        foreach ($files as $file) {
            if (is_file($file)) {
                $file_size = filesize($file);
                $total_size += $file_size;
                $file_time = filemtime($file);
                $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

                // Track file types
                if (!isset($file_types[$extension])) {
                    $file_types[$extension] = array('count' => 0, 'size' => 0);
                }
                $file_types[$extension]['count']++;
                $file_types[$extension]['size'] += $file_size;

                // Track oldest and newest files
                if ($oldest_time === null || $file_time < $oldest_time) {
                    $oldest_time = $file_time;
                }

                if ($newest_time === null || $file_time > $newest_time) {
                    $newest_time = $file_time;
                }
            }
        }

        // Format file types
        foreach ($file_types as $ext => &$info) {
            $info['size_formatted'] = size_format($info['size']);
        }

        return array(
            'total_files' => count($files),
            'total_size' => $total_size,
            'total_size_formatted' => size_format($total_size),
            'oldest_file' => $oldest_time ? date('Y-m-d H:i:s', $oldest_time) : null,
            'newest_file' => $newest_time ? date('Y-m-d H:i:s', $newest_time) : null,
            'file_types' => $file_types,
            'export_dir' => $this->export_dir,
            'disk_usage_percent' => $this->get_disk_usage_percent()
        );
    }

    /**
     * Get disk usage percentage for export directory
     *
     * @since 1.0.0
     * @return float Disk usage percentage
     */
    private function get_disk_usage_percent()
    {
        if (!function_exists('disk_free_space') || !function_exists('disk_total_space')) {
            return 0;
        }

        $free_bytes = disk_free_space($this->export_dir);
        $total_bytes = disk_total_space($this->export_dir);

        if ($free_bytes === false || $total_bytes === false || $total_bytes === 0) {
            return 0;
        }

        $used_bytes = $total_bytes - $free_bytes;
        return round(($used_bytes / $total_bytes) * 100, 2);
    }

    /**
     * Get available export formats
     *
     * @since 1.0.0
     * @return array Available formats with descriptions
     */
    public function get_available_formats()
    {
        return array(
            'csv' => array(
                'name' => __('CSV (Excel Compatible)', 'chatshop'),
                'description' => __('Comma-separated values format compatible with Excel and other spreadsheet applications', 'chatshop'),
                'mime_type' => 'text/csv',
                'extension' => 'csv'
            ),
            'json' => array(
                'name' => __('JSON', 'chatshop'),
                'description' => __('JavaScript Object Notation format for APIs and data interchange', 'chatshop'),
                'mime_type' => 'application/json',
                'extension' => 'json'
            ),
            'txt' => array(
                'name' => __('Plain Text', 'chatshop'),
                'description' => __('Human-readable plain text format', 'chatshop'),
                'mime_type' => 'text/plain',
                'extension' => 'txt'
            )
        );
    }

    /**
     * Get available export types
     *
     * @since 1.0.0
     * @return array Available export types with descriptions
     */
    public function get_available_export_types()
    {
        return array(
            'overview' => array(
                'name' => __('Overview Summary', 'chatshop'),
                'description' => __('High-level metrics and key performance indicators', 'chatshop')
            ),
            'conversions' => array(
                'name' => __('Conversion Data', 'chatshop'),
                'description' => __('Detailed conversion tracking and funnel analysis', 'chatshop')
            ),
            'revenue' => array(
                'name' => __('Revenue Analysis', 'chatshop'),
                'description' => __('Revenue attribution and financial performance metrics', 'chatshop')
            ),
            'performance' => array(
                'name' => __('Performance Metrics', 'chatshop'),
                'description' => __('Contact performance and engagement analytics', 'chatshop')
            ),
            'detailed' => array(
                'name' => __('Detailed Data', 'chatshop'),
                'description' => __('Raw analytics data with all tracked events', 'chatshop')
            ),
            'contacts' => array(
                'name' => __('Contact Analysis', 'chatshop'),
                'description' => __('Contact interaction and conversion analysis', 'chatshop')
            ),
            'messages' => array(
                'name' => __('Message Analytics', 'chatshop'),
                'description' => __('WhatsApp message performance and delivery statistics', 'chatshop')
            )
        );
    }

    /**
     * Validate export parameters
     *
     * @since 1.0.0
     * @param array $params Export parameters
     * @return array Validation result
     */
    public function validate_export_params($params)
    {
        $errors = array();
        $available_formats = array_keys($this->get_available_formats());
        $available_types = array_keys($this->get_available_export_types());

        // Validate format
        if (empty($params['format']) || !in_array($params['format'], $available_formats)) {
            $errors[] = __('Invalid export format specified', 'chatshop');
        }

        // Validate export type
        if (empty($params['export_type']) || !in_array($params['export_type'], $available_types)) {
            $errors[] = __('Invalid export type specified', 'chatshop');
        }

        // Validate date range
        $valid_ranges = array('7_days', '30_days', '90_days', 'this_month', 'last_month', 'this_year', 'last_year');
        if (empty($params['date_range']) || !in_array($params['date_range'], $valid_ranges)) {
            $errors[] = __('Invalid date range specified', 'chatshop');
        }

        return array(
            'valid' => empty($errors),
            'errors' => $errors
        );
    }

    /**
     * Schedule automatic export
     *
     * @since 1.0.0
     * @param array $schedule_params Schedule parameters
     * @return bool Success status
     */
    public function schedule_export($schedule_params)
    {
        // This would be implemented for automated exports
        // For now, return false as it's not implemented
        return false;
    }

    /**
     * Get export history
     *
     * @since 1.0.0
     * @param int $limit Number of recent exports to return
     * @return array Export history
     */
    public function get_export_history($limit = 10)
    {
        if (!is_dir($this->export_dir)) {
            return array();
        }

        $files = glob($this->export_dir . 'chatshop-analytics-*');
        $history = array();

        // Sort files by modification time (newest first)
        usort($files, function ($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        foreach (array_slice($files, 0, $limit) as $file) {
            if (is_file($file)) {
                $filename = basename($file);
                $parts = explode('-', pathinfo($filename, PATHINFO_FILENAME));

                $history[] = array(
                    'filename' => $filename,
                    'size' => size_format(filesize($file)),
                    'created' => date('Y-m-d H:i:s', filemtime($file)),
                    'type' => isset($parts[2]) ? $parts[2] : 'unknown',
                    'format' => strtolower(pathinfo($filename, PATHINFO_EXTENSION)),
                    'download_url' => add_query_arg(array(
                        'chatshop_action' => 'download_export',
                        'file' => $filename,
                        'nonce' => wp_create_nonce('chatshop_download_export')
                    ), admin_url('admin.php'))
                );
            }
        }

        return $history;
    }

    /**
     * Delete export file
     *
     * @since 1.0.0
     * @param string $filename Filename to delete
     * @return bool Success status
     */
    public function delete_export_file($filename)
    {
        $filename = sanitize_file_name($filename);
        $file_path = $this->export_dir . $filename;

        // Security check
        if (strpos($filename, '..') !== false || strpos($filename, '/') !== false) {
            return false;
        }

        // Check if file exists and is in export directory
        if (file_exists($file_path) && strpos(realpath($file_path), realpath($this->export_dir)) === 0) {
            if (unlink($file_path)) {
                chatshop_log("Export file deleted: {$filename}", 'info');
                return true;
            }
        }

        return false;
    }

    /**
     * Get export file info
     *
     * @since 1.0.0
     * @param string $filename Filename
     * @return array|false File info or false if not found
     */
    public function get_export_file_info($filename)
    {
        $filename = sanitize_file_name($filename);
        $file_path = $this->export_dir . $filename;

        if (!file_exists($file_path)) {
            return false;
        }

        return array(
            'filename' => $filename,
            'size' => filesize($file_path),
            'size_formatted' => size_format(filesize($file_path)),
            'created' => date('Y-m-d H:i:s', filemtime($file_path)),
            'modified' => date('Y-m-d H:i:s', filemtime($file_path)),
            'type' => strtolower(pathinfo($filename, PATHINFO_EXTENSION)),
            'mime_type' => $this->get_mime_type($filename),
            'is_readable' => is_readable($file_path),
            'permissions' => substr(sprintf('%o', fileperms($file_path)), -4)
        );
    }

    /**
     * Get MIME type for file
     *
     * @since 1.0.0
     * @param string $filename Filename
     * @return string MIME type
     */
    private function get_mime_type($filename)
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $mime_types = array(
            'csv' => 'text/csv',
            'json' => 'application/json',
            'txt' => 'text/plain'
        );

        return isset($mime_types[$extension]) ? $mime_types[$extension] : 'application/octet-stream';
    }

    /**
     * Destructor - cleanup on object destruction
     *
     * @since 1.0.0
     */
    public function __destruct()
    {
        // Any cleanup needed when object is destroyed
    }
}
