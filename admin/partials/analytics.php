<?php

/**
 * Analytics Admin Interface
 *
 * File: admin/partials/analytics.php
 * 
 * Premium analytics dashboard with WhatsApp-to-payment conversion tracking,
 * revenue attribution, and performance metrics visualization.
 *
 * @package ChatShop
 * @subpackage Admin\Partials
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Check premium access
if (!chatshop_is_premium()) {
?>
    <div class="wrap">
        <h1><?php _e('Analytics Dashboard', 'chatshop'); ?></h1>

        <div class="chatshop-premium-notice">
            <div class="chatshop-premium-content">
                <h2><?php _e('Premium Analytics Dashboard', 'chatshop'); ?></h2>
                <p><?php _e('Unlock powerful analytics to track your WhatsApp-to-payment conversions, revenue attribution, and performance metrics.', 'chatshop'); ?></p>

                <div class="chatshop-premium-features">
                    <ul>
                        <li>✓ <?php _e('WhatsApp-to-Payment Conversion Tracking', 'chatshop'); ?></li>
                        <li>✓ <?php _e('Revenue Attribution by Source', 'chatshop'); ?></li>
                        <li>✓ <?php _e('Real-time Performance Metrics', 'chatshop'); ?></li>
                        <li>✓ <?php _e('Gateway Performance Comparison', 'chatshop'); ?></li>
                        <li>✓ <?php _e('Customer Journey Analytics', 'chatshop'); ?></li>
                        <li>✓ <?php _e('Export & Reporting Tools', 'chatshop'); ?></li>
                    </ul>
                </div>

                <a href="#" class="button button-primary button-large">
                    <?php _e('Upgrade to Premium', 'chatshop'); ?>
                </a>
            </div>
        </div>
    </div>
<?php
    return;
}

// Get analytics component instance
$analytics = chatshop_get_component('analytics');
if (!$analytics) {
?>
    <div class="wrap">
        <h1><?php _e('Analytics Dashboard', 'chatshop'); ?></h1>
        <div class="notice notice-error">
            <p><?php _e('Analytics component is not available. Please contact support.', 'chatshop'); ?></p>
        </div>
    </div>
<?php
    return;
}
?>

<div class="wrap chatshop-analytics-dashboard">
    <h1 class="wp-heading-inline"><?php _e('Analytics Dashboard', 'chatshop'); ?></h1>

    <!-- Date Range Selector -->
    <div class="chatshop-analytics-header">
        <div class="chatshop-date-selector">
            <label for="analytics-date-range"><?php _e('Date Range:', 'chatshop'); ?></label>
            <select id="analytics-date-range" class="chatshop-date-range-select">
                <option value="7days"><?php _e('Last 7 Days', 'chatshop'); ?></option>
                <option value="30days"><?php _e('Last 30 Days', 'chatshop'); ?></option>
                <option value="90days"><?php _e('Last 90 Days', 'chatshop'); ?></option>
                <option value="365days"><?php _e('Last Year', 'chatshop'); ?></option>
            </select>
        </div>

        <div class="chatshop-analytics-actions">
            <button type="button" class="button" id="refresh-analytics">
                <span class="dashicons dashicons-update"></span>
                <?php _e('Refresh', 'chatshop'); ?>
            </button>
            <button type="button" class="button" id="export-analytics">
                <span class="dashicons dashicons-download"></span>
                <?php _e('Export', 'chatshop'); ?>
            </button>
        </div>
    </div>

    <!-- Overview Cards -->
    <div class="chatshop-analytics-overview">
        <div class="analytics-card" id="total-revenue-card">
            <div class="card-icon">
                <span class="dashicons dashicons-money-alt"></span>
            </div>
            <div class="card-content">
                <h3 class="card-value" id="total-revenue">₦0.00</h3>
                <p class="card-label"><?php _e('Total Revenue', 'chatshop'); ?></p>
                <span class="card-growth" id="revenue-growth">+0%</span>
            </div>
        </div>

        <div class="analytics-card" id="total-conversions-card">
            <div class="card-icon">
                <span class="dashicons dashicons-cart"></span>
            </div>
            <div class="card-content">
                <h3 class="card-value" id="total-conversions">0</h3>
                <p class="card-label"><?php _e('Conversions', 'chatshop'); ?></p>
                <span class="card-growth" id="conversion-growth">+0%</span>
            </div>
        </div>

        <div class="analytics-card" id="whatsapp-interactions-card">
            <div class="card-icon">
                <span class="dashicons dashicons-whatsapp"></span>
            </div>
            <div class="card-content">
                <h3 class="card-value" id="whatsapp-interactions">0</h3>
                <p class="card-label"><?php _e('WhatsApp Interactions', 'chatshop'); ?></p>
                <span class="card-growth" id="interaction-growth">+0%</span>
            </div>
        </div>

        <div class="analytics-card" id="conversion-rate-card">
            <div class="card-icon">
                <span class="dashicons dashicons-chart-line"></span>
            </div>
            <div class="card-content">
                <h3 class="card-value" id="conversion-rate">0%</h3>
                <p class="card-label"><?php _e('Conversion Rate', 'chatshop'); ?></p>
                <span class="card-growth" id="rate-growth">+0%</span>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="chatshop-analytics-charts">
        <div class="chart-row">
            <!-- Revenue & Conversions Chart -->
            <div class="chart-container">
                <div class="chart-header">
                    <h3><?php _e('Revenue & Conversions Over Time', 'chatshop'); ?></h3>
                    <div class="chart-legend">
                        <span class="legend-item revenue">
                            <span class="legend-color"></span>
                            <?php _e('Revenue', 'chatshop'); ?>
                        </span>
                        <span class="legend-item conversions">
                            <span class="legend-color"></span>
                            <?php _e('Conversions', 'chatshop'); ?>
                        </span>
                    </div>
                </div>
                <div class="chart-canvas-container">
                    <canvas id="revenue-conversions-chart" width="400" height="200"></canvas>
                </div>
            </div>

            <!-- Conversion Funnel -->
            <div class="chart-container">
                <div class="chart-header">
                    <h3><?php _e('WhatsApp to Payment Funnel', 'chatshop'); ?></h3>
                </div>
                <div class="funnel-chart" id="conversion-funnel">
                    <div class="funnel-step" data-step="messages_sent">
                        <div class="funnel-bar">
                            <div class="funnel-fill" style="width: 100%"></div>
                        </div>
                        <div class="funnel-label">
                            <span class="step-name"><?php _e('Messages Sent', 'chatshop'); ?></span>
                            <span class="step-count" id="messages-sent-count">0</span>
                        </div>
                    </div>

                    <div class="funnel-step" data-step="messages_opened">
                        <div class="funnel-bar">
                            <div class="funnel-fill" style="width: 0%"></div>
                        </div>
                        <div class="funnel-label">
                            <span class="step-name"><?php _e('Messages Opened', 'chatshop'); ?></span>
                            <span class="step-count" id="messages-opened-count">0</span>
                        </div>
                    </div>

                    <div class="funnel-step" data-step="links_clicked">
                        <div class="funnel-bar">
                            <div class="funnel-fill" style="width: 0%"></div>
                        </div>
                        <div class="funnel-label">
                            <span class="step-name"><?php _e('Links Clicked', 'chatshop'); ?></span>
                            <span class="step-count" id="links-clicked-count">0</span>
                        </div>
                    </div>

                    <div class="funnel-step" data-step="payments_initiated">
                        <div class="funnel-bar">
                            <div class="funnel-fill" style="width: 0%"></div>
                        </div>
                        <div class="funnel-label">
                            <span class="step-name"><?php _e('Payments Initiated', 'chatshop'); ?></span>
                            <span class="step-count" id="payments-initiated-count">0</span>
                        </div>
                    </div>

                    <div class="funnel-step" data-step="payments_completed">
                        <div class="funnel-bar">
                            <div class="funnel-fill" style="width: 0%"></div>
                        </div>
                        <div class="funnel-label">
                            <span class="step-name"><?php _e('Payments Completed', 'chatshop'); ?></span>
                            <span class="step-count" id="payments-completed-count">0</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="chart-row">
            <!-- Revenue Attribution -->
            <div class="chart-container">
                <div class="chart-header">
                    <h3><?php _e('Revenue Attribution', 'chatshop'); ?></h3>
                </div>
                <div class="chart-canvas-container">
                    <canvas id="revenue-attribution-chart" width="300" height="300"></canvas>
                </div>
                <div class="attribution-legend" id="attribution-legend">
                    <!-- Legend items will be populated by JavaScript -->
                </div>
            </div>

            <!-- Gateway Performance -->
            <div class="chart-container">
                <div class="chart-header">
                    <h3><?php _e('Gateway Performance', 'chatshop'); ?></h3>
                </div>
                <div class="gateway-performance" id="gateway-performance">
                    <div class="performance-header">
                        <div class="header-item"><?php _e('Gateway', 'chatshop'); ?></div>
                        <div class="header-item"><?php _e('Success Rate', 'chatshop'); ?></div>
                        <div class="header-item"><?php _e('Avg. Value', 'chatshop'); ?></div>
                        <div class="header-item"><?php _e('Total Revenue', 'chatshop'); ?></div>
                    </div>
                    <div class="performance-list" id="gateway-performance-list">
                        <!-- Gateway performance items will be populated by JavaScript -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Indicator -->
    <div class="chatshop-loading" id="analytics-loading" style="display: none;">
        <div class="loading-spinner"></div>
        <p><?php _e('Loading analytics data...', 'chatshop'); ?></p>
    </div>

    <!-- Error Message -->
    <div class="chatshop-error" id="analytics-error" style="display: none;">
        <p></p>
        <button type="button" class="button" onclick="ChatShopAnalytics.loadAnalytics()">
            <?php _e('Retry', 'chatshop'); ?>
        </button>
    </div>
</div>

<style>
    .chatshop-analytics-dashboard {
        margin: 20px 0;
    }

    .chatshop-analytics-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        padding: 20px;
        background: #fff;
        border: 1px solid #c3c4c7;
        border-radius: 4px;
    }

    .chatshop-date-selector label {
        margin-right: 10px;
        font-weight: 600;
    }

    .chatshop-date-range-select {
        min-width: 150px;
    }

    .chatshop-analytics-actions {
        display: flex;
        gap: 10px;
    }

    /* Overview Cards */
    .chatshop-analytics-overview {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .analytics-card {
        background: #fff;
        border: 1px solid #c3c4c7;
        border-radius: 4px;
        padding: 20px;
        display: flex;
        align-items: center;
        transition: box-shadow 0.2s ease;
    }

    .analytics-card:hover {
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .card-icon {
        margin-right: 15px;
        padding: 15px;
        border-radius: 50%;
        background: #f0f6fc;
    }

    .card-icon .dashicons {
        font-size: 24px;
        color: #135e96;
    }

    .card-content {
        flex: 1;
    }

    .card-value {
        font-size: 28px;
        font-weight: 700;
        margin: 0 0 5px 0;
        color: #1d2327;
    }

    .card-label {
        margin: 0 0 8px 0;
        color: #646970;
        font-size: 14px;
    }

    .card-growth {
        font-size: 12px;
        padding: 2px 8px;
        border-radius: 12px;
        font-weight: 600;
    }

    .card-growth.positive {
        background: #d1e7dd;
        color: #0f5132;
    }

    .card-growth.negative {
        background: #f8d7da;
        color: #842029;
    }

    /* Charts */
    .chatshop-analytics-charts {
        display: flex;
        flex-direction: column;
        gap: 30px;
    }

    .chart-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px;
    }

    .chart-container {
        background: #fff;
        border: 1px solid #c3c4c7;
        border-radius: 4px;
        padding: 20px;
    }

    .chart-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px solid #f0f0f1;
    }

    .chart-header h3 {
        margin: 0;
        font-size: 16px;
        font-weight: 600;
    }

    .chart-legend {
        display: flex;
        gap: 20px;
    }

    .legend-item {
        display: flex;
        align-items: center;
        font-size: 12px;
    }

    .legend-color {
        width: 12px;
        height: 12px;
        border-radius: 2px;
        margin-right: 6px;
    }

    .legend-item.revenue .legend-color {
        background: #135e96;
    }

    .legend-item.conversions .legend-color {
        background: #00a32a;
    }

    .chart-canvas-container {
        position: relative;
        height: 300px;
    }

    /* Funnel Chart */
    .funnel-chart {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .funnel-step {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .funnel-bar {
        flex: 1;
        height: 30px;
        background: #f0f0f1;
        border-radius: 15px;
        overflow: hidden;
        position: relative;
    }

    .funnel-fill {
        height: 100%;
        background: linear-gradient(90deg, #135e96, #2271b1);
        border-radius: 15px;
        transition: width 0.6s ease;
    }

    .funnel-label {
        min-width: 160px;
        display: flex;
        flex-direction: column;
        gap: 2px;
    }

    .step-name {
        font-weight: 600;
        font-size: 13px;
    }

    .step-count {
        color: #646970;
        font-size: 12px;
    }

    /* Gateway Performance */
    .gateway-performance {
        font-size: 14px;
    }

    .performance-header {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr 1fr;
        gap: 15px;
        padding: 12px 0;
        border-bottom: 2px solid #f0f0f1;
        font-weight: 600;
        color: #1d2327;
    }

    .performance-list {
        display: flex;
        flex-direction: column;
    }

    .performance-item {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr 1fr;
        gap: 15px;
        padding: 12px 0;
        border-bottom: 1px solid #f6f7f7;
        align-items: center;
    }

    .performance-item:last-child {
        border-bottom: none;
    }

    .gateway-name {
        font-weight: 600;
        text-transform: capitalize;
    }

    .success-rate {
        font-weight: 600;
    }

    .success-rate.high {
        color: #00a32a;
    }

    .success-rate.medium {
        color: #dba617;
    }

    .success-rate.low {
        color: #d63638;
    }

    /* Attribution Legend */
    .attribution-legend {
        margin-top: 20px;
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        justify-content: center;
    }

    .attribution-item {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 12px;
    }

    .attribution-color {
        width: 12px;
        height: 12px;
        border-radius: 50%;
    }

    /* Premium Notice */
    .chatshop-premium-notice {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 40px;
        border-radius: 8px;
        text-align: center;
        margin: 20px 0;
    }

    .chatshop-premium-content h2 {
        color: white;
        margin-bottom: 15px;
    }

    .chatshop-premium-features {
        margin: 30px 0;
    }

    .chatshop-premium-features ul {
        list-style: none;
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 10px;
        max-width: 600px;
        margin: 0 auto;
        padding: 0;
    }

    .chatshop-premium-features li {
        text-align: left;
        padding: 5px 0;
    }

    /* Loading and Error States */
    .chatshop-loading {
        text-align: center;
        padding: 60px 20px;
        background: #fff;
        border: 1px solid #c3c4c7;
        border-radius: 4px;
    }

    .loading-spinner {
        width: 40px;
        height: 40px;
        border: 4px solid #f0f0f1;
        border-top: 4px solid #135e96;
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin: 0 auto 20px;
    }

    @keyframes spin {
        0% {
            transform: rotate(0deg);
        }

        100% {
            transform: rotate(360deg);
        }
    }

    .chatshop-error {
        text-align: center;
        padding: 40px 20px;
        background: #fff;
        border: 1px solid #c3c4c7;
        border-radius: 4px;
        color: #d63638;
    }

    /* Responsive Design */
    @media (max-width: 1200px) {
        .chart-row {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 768px) {
        .chatshop-analytics-header {
            flex-direction: column;
            gap: 20px;
            align-items: stretch;
        }

        .chatshop-analytics-overview {
            grid-template-columns: 1fr;
        }

        .performance-header,
        .performance-item {
            grid-template-columns: 1fr;
            gap: 5px;
        }

        .performance-header {
            display: none;
        }

        .performance-item {
            display: block;
            padding: 15px;
            background: #f6f7f7;
            border-radius: 4px;
            margin-bottom: 10px;
        }
    }
</style>