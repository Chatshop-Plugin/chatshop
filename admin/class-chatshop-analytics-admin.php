<?php

/**
 * Analytics Admin Interface Class
 *
 * File: admin/class-chatshop-analytics-admin.php
 * 
 * Handles the analytics admin interface including dashboard, reports,
 * and premium analytics features for ChatShop.
 *
 * @package ChatShop
 * @subpackage Admin
 * @since 1.0.0
 */

namespace ChatShop;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ChatShop Analytics Admin Class
 *
 * Manages the analytics admin interface with premium restrictions
 * and comprehensive analytics dashboard functionality.
 *
 * @since 1.0.0
 */
class ChatShop_Analytics_Admin
{
    /**
     * Analytics instance
     *
     * @var ChatShop_Analytics
     * @since 1.0.0
     */
    private $analytics;

    /**
     * Metrics collector instance
     *
     * @var ChatShop_Metrics_Collector
     * @since 1.0.0
     */
    private $metrics_collector;

    /**
     * Report generator instance
     *
     * @var ChatShop_Report_Generator
     * @since 1.0.0
     */
    private $report_generator;

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        $this->analytics = chatshop_get_component('analytics');
        $this->metrics_collector = new ChatShop_Metrics_Collector();
        $this->report_generator = new ChatShop_Report_Generator();

        $this->init_hooks();
    }

    /**
     * Initialize hooks
     *
     * @since 1.0.0
     */
    private function init_hooks()
    {
        add_action('admin_menu', array($this, 'add_analytics_menu'), 25);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_analytics_scripts'));

        // AJAX handlers
        add_action('wp_ajax_chatshop_get_analytics_dashboard', array($this, 'ajax_get_dashboard_data'));
        add_action('wp_ajax_chatshop_get_conversion_funnel', array($this, 'ajax_get_conversion_funnel'));
        add_action('wp_ajax_chatshop_get_revenue_chart', array($this, 'ajax_get_revenue_chart'));
        add_action('wp_ajax_chatshop_export_analytics_report', array($this, 'ajax_export_report'));
        add_action('wp_ajax_chatshop_generate_custom_report', array($this, 'ajax_generate_custom_report'));
    }

    /**
     * Add analytics menu
     *
     * @since 1.0.0
     */
    public function add_analytics_menu()
    {
        $premium_badge = chatshop_is_premium() ? '' : ' <span class="chatshop-premium-badge">PRO</span>';

        add_submenu_page(
            'chatshop',
            __('Analytics', 'chatshop') . $premium_badge,
            __('Analytics', 'chatshop') . $premium_badge,
            'manage_options',
            'chatshop-analytics',
            array($this, 'render_analytics_page')
        );
    }

    /**
     * Enqueue analytics scripts and styles
     *
     * @since 1.0.0
     * @param string $hook Current admin page hook
     */
    public function enqueue_analytics_scripts($hook)
    {
        if (strpos($hook, 'chatshop-analytics') === false) {
            return;
        }

        wp_enqueue_script(
            'chatshop-analytics-admin',
            CHATSHOP_PLUGIN_URL . 'admin/js/analytics.js',
            array('jquery', 'wp-util'),
            CHATSHOP_VERSION,
            true
        );

        wp_enqueue_style(
            'chatshop-analytics-admin',
            CHATSHOP_PLUGIN_URL . 'admin/css/analytics.css',
            array(),
            CHATSHOP_VERSION
        );

        // Chart.js for analytics charts
        wp_enqueue_script(
            'chartjs',
            'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js',
            array(),
            '3.9.1',
            true
        );

        wp_localize_script('chatshop-analytics-admin', 'chatshopAnalytics', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('chatshop_admin_nonce'),
            'isPremium' => chatshop_is_premium(),
            'strings' => array(
                'loading' => __('Loading analytics data...', 'chatshop'),
                'error' => __('Error loading data. Please try again.', 'chatshop'),
                'noData' => __('No data available for this period.', 'chatshop'),
                'premiumRequired' => __('This feature requires premium access.', 'chatshop'),
                'exportSuccess' => __('Report exported successfully!', 'chatshop'),
                'exportError' => __('Export failed. Please try again.', 'chatshop')
            )
        ));
    }

    /**
     * Render analytics page
     *
     * @since 1.0.0
     */
    public function render_analytics_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'chatshop'));
        }

?>
        <div class="wrap chatshop-analytics-page">
            <h1><?php esc_html_e('ChatShop Analytics', 'chatshop'); ?>
                <?php if (!chatshop_is_premium()): ?>
                    <span class="chatshop-premium-badge">PRO</span>
                <?php endif; ?>
            </h1>

            <?php if (!chatshop_is_premium()): ?>
                <div class="notice notice-info">
                    <p>
                        <?php esc_html_e('Analytics features are available in ChatShop Pro. Upgrade to unlock detailed conversion tracking, revenue attribution, and comprehensive reporting.', 'chatshop'); ?>
                        <a href="#" class="button button-primary" style="margin-left: 10px;">
                            <?php esc_html_e('Upgrade to Pro', 'chatshop'); ?>
                        </a>
                    </p>
                </div>
            <?php endif; ?>

            <div class="chatshop-analytics-dashboard">
                <!-- Date Range Selector -->
                <div class="analytics-header">
                    <div class="date-range-selector">
                        <label for="analytics-date-range"><?php esc_html_e('Date Range:', 'chatshop'); ?></label>
                        <select id="analytics-date-range" <?php echo !chatshop_is_premium() ? 'disabled' : ''; ?>>
                            <option value="7days"><?php esc_html_e('Last 7 Days', 'chatshop'); ?></option>
                            <option value="30days" selected><?php esc_html_e('Last 30 Days', 'chatshop'); ?></option>
                            <option value="90days"><?php esc_html_e('Last 90 Days', 'chatshop'); ?></option>
                            <option value="1year"><?php esc_html_e('Last Year', 'chatshop'); ?></option>
                        </select>
                    </div>
                    <div class="analytics-actions">
                        <button type="button" class="button" id="refresh-analytics" <?php echo !chatshop_is_premium() ? 'disabled' : ''; ?>>
                            <?php esc_html_e('Refresh', 'chatshop'); ?>
                        </button>
                        <button type="button" class="button button-secondary" id="export-report" <?php echo !chatshop_is_premium() ? 'disabled' : ''; ?>>
                            <?php esc_html_e('Export Report', 'chatshop'); ?>
                        </button>
                    </div>
                </div>

                <!-- Summary Cards -->
                <div class="analytics-summary-cards">
                    <div class="summary-card">
                        <div class="card-icon">📊</div>
                        <div class="card-content">
                            <h3 id="total-conversions">-</h3>
                            <p><?php esc_html_e('Total Conversions', 'chatshop'); ?></p>
                        </div>
                    </div>
                    <div class="summary-card">
                        <div class="card-icon">💰</div>
                        <div class="card-content">
                            <h3 id="total-revenue">-</h3>
                            <p><?php esc_html_e('Total Revenue', 'chatshop'); ?></p>
                        </div>
                    </div>
                    <div class="summary-card">
                        <div class="card-icon">📈</div>
                        <div class="card-content">
                            <h3 id="conversion-rate">-</h3>
                            <p><?php esc_html_e('Conversion Rate', 'chatshop'); ?></p>
                        </div>
                    </div>
                    <div class="summary-card">
                        <div class="card-icon">🛒</div>
                        <div class="card-content">
                            <h3 id="avg-order-value">-</h3>
                            <p><?php esc_html_e('Avg Order Value', 'chatshop'); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Charts Section -->
                <div class="analytics-charts-grid">
                    <div class="chart-container">
                        <div class="chart-header">
                            <h3><?php esc_html_e('Revenue Trends', 'chatshop'); ?></h3>
                        </div>
                        <div class="chart-wrapper">
                            <canvas id="revenue-chart"></canvas>
                        </div>
                    </div>

                    <div class="chart-container">
                        <div class="chart-header">
                            <h3><?php esc_html_e('Conversion Funnel', 'chatshop'); ?></h3>
                        </div>
                        <div class="chart-wrapper">
                            <canvas id="funnel-chart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Analytics Tables -->
                <div class="analytics-tables">
                    <div class="table-container">
                        <h3><?php esc_html_e('Revenue by Source', 'chatshop'); ?></h3>
                        <div class="table-wrapper">
                            <table class="wp-list-table widefat fixed striped" id="revenue-by-source-table">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e('Source', 'chatshop'); ?></th>
                                        <th><?php esc_html_e('Conversions', 'chatshop'); ?></th>
                                        <th><?php esc_html_e('Revenue', 'chatshop'); ?></th>
                                        <th><?php esc_html_e('Avg Value', 'chatshop'); ?></th>
                                    </tr>
                                </thead>
                                <tbody id="revenue-source-tbody">
                                    <tr>
                                        <td colspan="4" class="no-data">
                                            <?php esc_html_e('Loading data...', 'chatshop'); ?>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="table-container">
                        <h3><?php esc_html_e('Top Campaigns', 'chatshop'); ?></h3>
                        <div class="table-wrapper">
                            <table class="wp-list-table widefat fixed striped" id="top-campaigns-table">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e('Campaign', 'chatshop'); ?></th>
                                        <th><?php esc_html_e('Messages', 'chatshop'); ?></th>
                                        <th><?php esc_html_e('Clicks', 'chatshop'); ?></th>
                                        <th><?php esc_html_e('Conversions', 'chatshop'); ?></th>
                                        <th><?php esc_html_e('Revenue', 'chatshop'); ?></th>
                                        <th><?php esc_html_e('ROI', 'chatshop'); ?></th>
                                    </tr>
                                </thead>
                                <tbody id="campaigns-tbody">
                                    <tr>
                                        <td colspan="6" class="no-data">
                                            <?php esc_html_e('Loading data...', 'chatshop'); ?>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Custom Reports Section -->
                <div class="custom-reports-section">
                    <h3><?php esc_html_e('Custom Reports', 'chatshop'); ?></h3>
                    <div class="report-builder">
                        <div class="report-options">
                            <div class="option-group">
                                <label><?php esc_html_e('Report Type:', 'chatshop'); ?></label>
                                <select id="report-type" <?php echo !chatshop_is_premium() ? 'disabled' : ''; ?>>
                                    <option value="performance"><?php esc_html_e('Performance Summary', 'chatshop'); ?></option>
                                    <option value="revenue"><?php esc_html_e('Revenue Analysis', 'chatshop'); ?></option>
                                    <option value="campaign"><?php esc_html_e('Campaign Performance', 'chatshop'); ?></option>
                                    <option value="custom"><?php esc_html_e('Custom Report', 'chatshop'); ?></option>
                                </select>
                            </div>
                            <div class="option-group">
                                <label><?php esc_html_e('Format:', 'chatshop'); ?></label>
                                <select id="report-format" <?php echo !chatshop_is_premium() ? 'disabled' : ''; ?>>
                                    <option value="html"><?php esc_html_e('HTML', 'chatshop'); ?></option>
                                    <option value="csv"><?php esc_html_e('CSV', 'chatshop'); ?></option>
                                </select>
                            </div>
                            <button type="button" class="button button-primary" id="generate-custom-report" <?php echo !chatshop_is_premium() ? 'disabled' : ''; ?>>
                                <?php esc_html_e('Generate Report', 'chatshop'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Loading Overlay -->
            <div id="analytics-loading" class="analytics-loading" style="display: none;">
                <div class="loading-spinner"></div>
                <p><?php esc_html_e('Loading analytics data...', 'chatshop'); ?></p>
            </div>
        </div>

        <style>
            .chatshop-analytics-page {
                max-width: 1200px;
            }

            .chatshop-premium-badge {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white !important;
                font-size: 9px;
                padding: 2px 6px;
                border-radius: 10px;
                margin-left: 5px;
                font-weight: bold;
                text-shadow: none;
                display: inline-block;
                vertical-align: top;
                line-height: 1.2;
            }

            .analytics-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 20px;
                padding: 15px;
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
            }

            .date-range-selector label {
                margin-right: 10px;
                font-weight: 600;
            }

            .analytics-actions {
                display: flex;
                gap: 10px;
            }

            .analytics-summary-cards {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 20px;
                margin-bottom: 30px;
            }

            .summary-card {
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 8px;
                padding: 20px;
                display: flex;
                align-items: center;
                transition: box-shadow 0.2s;
            }

            .summary-card:hover {
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            }

            .card-icon {
                font-size: 2.5em;
                margin-right: 15px;
                opacity: 0.8;
            }

            .card-content h3 {
                margin: 0 0 5px;
                font-size: 1.8em;
                font-weight: 700;
                color: #1d2327;
            }

            .card-content p {
                margin: 0;
                color: #646970;
                font-size: 0.9em;
            }

            .analytics-charts-grid {
                display: grid;
                grid-template-columns: 2fr 1fr;
                gap: 20px;
                margin-bottom: 30px;
            }

            .chart-container {
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 8px;
                padding: 20px;
            }

            .chart-header {
                margin-bottom: 15px;
                border-bottom: 1px solid #f0f0f1;
                padding-bottom: 10px;
            }

            .chart-header h3 {
                margin: 0;
                font-size: 1.1em;
                color: #1d2327;
            }

            .chart-wrapper {
                position: relative;
                height: 300px;
            }

            .analytics-tables {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 20px;
                margin-bottom: 30px;
            }

            .table-container {
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 8px;
                padding: 20px;
            }

            .table-container h3 {
                margin: 0 0 15px;
                font-size: 1.1em;
                color: #1d2327;
            }

            .table-wrapper {
                overflow-x: auto;
            }

            .no-data {
                text-align: center;
                color: #646970;
                font-style: italic;
            }

            .custom-reports-section {
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 8px;
                padding: 20px;
                margin-bottom: 20px;
            }

            .custom-reports-section h3 {
                margin: 0 0 15px;
                font-size: 1.1em;
                color: #1d2327;
            }

            .report-options {
                display: flex;
                gap: 20px;
                align-items: center;
            }

            .option-group {
                display: flex;
                flex-direction: column;
                gap: 5px;
            }

            .option-group label {
                font-weight: 600;
                font-size: 0.9em;
            }

            .analytics-loading {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(255, 255, 255, 0.9);
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: center;
                z-index: 9999;
            }

            .loading-spinner {
                width: 40px;
                height: 40px;
                border: 4px solid #f3f3f3;
                border-top: 4px solid #2271b1;
                border-radius: 50%;
                animation: spin 1s linear infinite;
                margin-bottom: 10px;
            }

            @keyframes spin {
                0% {
                    transform: rotate(0deg);
                }

                100% {
                    transform: rotate(360deg);
                }
            }

            @media (max-width: 768px) {

                .analytics-charts-grid,
                .analytics-tables {
                    grid-template-columns: 1fr;
                }

                .analytics-header {
                    flex-direction: column;
                    gap: 15px;
                }

                .report-options {
                    flex-direction: column;
                    align-items: stretch;
                }
            }
        </style>
<?php
    }

    /**
     * AJAX handler for dashboard data
     *
     * @since 1.0.0
     */
    public function ajax_get_dashboard_data()
    {
        check_ajax_referer('chatshop_admin_nonce', 'nonce');

        if (!current_user_can('manage_options') || !chatshop_is_premium()) {
            wp_send_json_error(array('message' => __('Access denied.', 'chatshop')));
        }

        $date_range = sanitize_text_field($_POST['date_range'] ?? '30days');

        try {
            $dashboard_data = $this->analytics->get_analytics_data($date_range, 'overview');
            wp_send_json_success($dashboard_data);
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * AJAX handler for conversion funnel
     *
     * @since 1.0.0
     */
    public function ajax_get_conversion_funnel()
    {
        check_ajax_referer('chatshop_admin_nonce', 'nonce');

        if (!current_user_can('manage_options') || !chatshop_is_premium()) {
            wp_send_json_error(array('message' => __('Access denied.', 'chatshop')));
        }

        $date_range = sanitize_text_field($_POST['date_range'] ?? '30days');

        try {
            $conversion_tracker = new ChatShop_Conversion_Tracker();
            $funnel_data = $conversion_tracker->get_conversion_funnel($date_range);
            wp_send_json_success($funnel_data);
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * AJAX handler for revenue chart
     *
     * @since 1.0.0
     */
    public function ajax_get_revenue_chart()
    {
        check_ajax_referer('chatshop_admin_nonce', 'nonce');

        if (!current_user_can('manage_options') || !chatshop_is_premium()) {
            wp_send_json_error(array('message' => __('Access denied.', 'chatshop')));
        }

        $date_range = sanitize_text_field($_POST['date_range'] ?? '30days');
        $group_by = sanitize_text_field($_POST['group_by'] ?? 'day');

        try {
            $conversion_tracker = new ChatShop_Conversion_Tracker();
            $revenue_data = $conversion_tracker->get_conversion_trends($date_range, $group_by);
            wp_send_json_success($revenue_data);
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * AJAX handler for report export
     *
     * @since 1.0.0
     */
    public function ajax_export_report()
    {
        check_ajax_referer('chatshop_admin_nonce', 'nonce');

        if (!current_user_can('manage_options') || !chatshop_is_premium()) {
            wp_send_json_error(array('message' => __('Access denied.', 'chatshop')));
        }

        $report_type = sanitize_text_field($_POST['report_type'] ?? 'performance');
        $date_range = sanitize_text_field($_POST['date_range'] ?? '30days');
        $format = sanitize_text_field($_POST['format'] ?? 'csv');

        try {
            switch ($report_type) {
                case 'revenue':
                    $report_data = $this->report_generator->generate_revenue_report($date_range, 'day', $format);
                    break;
                case 'campaign':
                    $report_data = $this->report_generator->generate_campaign_report($date_range, $format);
                    break;
                default:
                    $report_data = $this->report_generator->generate_performance_summary($date_range, $format);
                    break;
            }

            if (isset($report_data['error'])) {
                wp_send_json_error(array('message' => $report_data['error']));
            }

            wp_send_json_success($report_data);
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * AJAX handler for custom report generation
     *
     * @since 1.0.0
     */
    public function ajax_generate_custom_report()
    {
        check_ajax_referer('chatshop_admin_nonce', 'nonce');

        if (!current_user_can('manage_options') || !chatshop_is_premium()) {
            wp_send_json_error(array('message' => __('Access denied.', 'chatshop')));
        }

        $report_config = array(
            'title' => sanitize_text_field($_POST['title'] ?? __('Custom Analytics Report', 'chatshop')),
            'date_range' => sanitize_text_field($_POST['date_range'] ?? '30days'),
            'metrics' => array_map('sanitize_text_field', $_POST['metrics'] ?? array()),
            'filters' => array_map('sanitize_text_field', $_POST['filters'] ?? array())
        );
        $format = sanitize_text_field($_POST['format'] ?? 'array');

        try {
            $report_data = $this->report_generator->generate_custom_report($report_config, $format);

            if (isset($report_data['error'])) {
                wp_send_json_error(array('message' => $report_data['error']));
            }

            wp_send_json_success($report_data);
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
}
