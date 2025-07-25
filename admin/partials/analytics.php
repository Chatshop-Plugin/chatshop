<?php

/**
 * Analytics Admin Interface - FIXED VERSION
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

// Check premium access first
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

// Get analytics component instance - FIXED APPROACH
$analytics = chatshop_get_component('analytics');

// Debug information for troubleshooting
$debug_info = array(
    'chatshop_loaded' => chatshop_is_loaded(),
    'premium_enabled' => chatshop_is_premium(),
    'analytics_component' => $analytics ? 'Available' : 'Not Available',
    'component_class' => $analytics ? get_class($analytics) : 'N/A',
    'component_status' => $analytics && method_exists($analytics, 'get_status') ? $analytics->get_status() : 'N/A'
);

if (!$analytics) {
?>
    <div class="wrap">
        <h1><?php _e('Analytics Dashboard', 'chatshop'); ?></h1>

        <div class="notice notice-error">
            <p><strong><?php _e('Analytics Component Issue', 'chatshop'); ?></strong></p>
            <p><?php _e('The analytics component is not available. This could be due to:', 'chatshop'); ?></p>
            <ul>
                <li><?php _e('Component not properly loaded', 'chatshop'); ?></li>
                <li><?php _e('Missing component files', 'chatshop'); ?></li>
                <li><?php _e('Component dependencies not met', 'chatshop'); ?></li>
            </ul>
        </div>

        <?php if (defined('WP_DEBUG') && WP_DEBUG): ?>
            <div class="notice notice-info">
                <h3><?php _e('Debug Information', 'chatshop'); ?></h3>
                <pre><?php echo esc_html(print_r($debug_info, true)); ?></pre>
            </div>
        <?php endif; ?>

        <div class="card">
            <h2><?php _e('Troubleshooting Steps', 'chatshop'); ?></h2>
            <ol>
                <li><?php _e('Check if premium features are enabled', 'chatshop'); ?></li>
                <li><?php _e('Verify analytics component is enabled in settings', 'chatshop'); ?></li>
                <li><?php _e('Check server logs for component loading errors', 'chatshop'); ?></li>
                <li><?php _e('Deactivate and reactivate the ChatShop plugin', 'chatshop'); ?></li>
            </ol>
        </div>
    </div>
<?php
    return;
}

// Component is available - Display analytics dashboard
?>

<div class="wrap">
    <h1><?php _e('Analytics Dashboard', 'chatshop'); ?></h1>

    <!-- Success Notice -->
    <div class="notice notice-success">
        <p><?php _e('Analytics component is loaded and ready!', 'chatshop'); ?></p>
    </div>

    <!-- Analytics Header -->
    <div class="chatshop-analytics-header">
        <div class="chatshop-date-selector">
            <label for="analytics-date-range"><?php _e('Date Range:', 'chatshop'); ?></label>
            <select id="analytics-date-range" class="chatshop-date-range-select">
                <option value="today"><?php _e('Today', 'chatshop'); ?></option>
                <option value="7days" selected><?php _e('Last 7 Days', 'chatshop'); ?></option>
                <option value="30days"><?php _e('Last 30 Days', 'chatshop'); ?></option>
                <option value="90days"><?php _e('Last 90 Days', 'chatshop'); ?></option>
                <option value="this_month"><?php _e('This Month', 'chatshop'); ?></option>
                <option value="last_month"><?php _e('Last Month', 'chatshop'); ?></option>
            </select>
        </div>

        <div class="chatshop-analytics-actions">
            <button type="button" class="button" id="refresh-analytics">
                <?php _e('Refresh Data', 'chatshop'); ?>
            </button>
            <button type="button" class="button button-primary" id="export-analytics">
                <?php _e('Export Report', 'chatshop'); ?>
            </button>
        </div>
    </div>

    <!-- Overview Cards -->
    <div class="chatshop-analytics-overview">
        <div class="analytics-card">
            <div class="card-icon">💰</div>
            <div class="card-content">
                <div class="card-value" id="total-revenue">₦0.00</div>
                <div class="card-label"><?php _e('Total Revenue', 'chatshop'); ?></div>
            </div>
        </div>

        <div class="analytics-card">
            <div class="card-icon">🔄</div>
            <div class="card-content">
                <div class="card-value" id="total-conversions">0</div>
                <div class="card-label"><?php _e('WhatsApp Conversions', 'chatshop'); ?></div>
            </div>
        </div>

        <div class="analytics-card">
            <div class="card-icon">📊</div>
            <div class="card-content">
                <div class="card-value" id="conversion-rate">0%</div>
                <div class="card-label"><?php _e('Conversion Rate', 'chatshop'); ?></div>
            </div>
        </div>

        <div class="analytics-card">
            <div class="card-icon">💳</div>
            <div class="card-content">
                <div class="card-value" id="avg-order-value">₦0.00</div>
                <div class="card-label"><?php _e('Avg Order Value', 'chatshop'); ?></div>
            </div>
        </div>
    </div>

    <!-- Charts and Detailed Analytics -->
    <div class="chatshop-analytics-content">
        <div class="analytics-row">
            <!-- Revenue Chart -->
            <div class="analytics-section">
                <div class="section-header">
                    <h2><?php _e('Revenue Trend', 'chatshop'); ?></h2>
                </div>
                <div class="section-content">
                    <div id="revenue-chart" class="chart-container">
                        <p class="chart-placeholder"><?php _e('Loading revenue chart...', 'chatshop'); ?></p>
                    </div>
                </div>
            </div>

            <!-- Conversion Funnel -->
            <div class="analytics-section">
                <div class="section-header">
                    <h2><?php _e('Conversion Funnel', 'chatshop'); ?></h2>
                </div>
                <div class="section-content">
                    <div id="conversion-funnel" class="funnel-container">
                        <p class="chart-placeholder"><?php _e('Loading conversion funnel...', 'chatshop'); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="analytics-row">
            <!-- Top Gateways -->
            <div class="analytics-section">
                <div class="section-header">
                    <h2><?php _e('Top Performing Gateways', 'chatshop'); ?></h2>
                </div>
                <div class="section-content">
                    <div class="performance-table">
                        <div class="table-header">
                            <div class="header-item"><?php _e('Gateway', 'chatshop'); ?></div>
                            <div class="header-item"><?php _e('Transactions', 'chatshop'); ?></div>
                            <div class="header-item"><?php _e('Revenue', 'chatshop'); ?></div>
                        </div>
                        <div class="performance-list" id="gateway-performance-list">
                            <p class="loading-text"><?php _e('Loading gateway performance...', 'chatshop'); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Revenue Attribution -->
            <div class="analytics-section">
                <div class="section-header">
                    <h2><?php _e('Revenue by Source', 'chatshop'); ?></h2>
                </div>
                <div class="section-content">
                    <div id="revenue-attribution" class="attribution-container">
                        <p class="chart-placeholder"><?php _e('Loading revenue attribution...', 'chatshop'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Component Status (for debugging) -->
    <?php if (defined('WP_DEBUG') && WP_DEBUG): ?>
        <div class="analytics-section">
            <div class="section-header">
                <h2><?php _e('Component Status (Debug)', 'chatshop'); ?></h2>
            </div>
            <div class="section-content">
                <pre><?php echo esc_html(print_r($debug_info, true)); ?></pre>
                <?php if (method_exists($analytics, 'get_status')): ?>
                    <h4><?php _e('Analytics Component Status:', 'chatshop'); ?></h4>
                    <pre><?php echo esc_html(print_r($analytics->get_status(), true)); ?></pre>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Loading Indicator -->
    <div class="chatshop-loading" id="analytics-loading" style="display: none;">
        <div class="loading-spinner"></div>
        <p><?php _e('Loading analytics data...', 'chatshop'); ?></p>
    </div>

    <!-- Error Message -->
    <div class="chatshop-error" id="analytics-error" style="display: none;">
        <p></p>
        <button type="button" class="button" onclick="loadAnalyticsData()">
            <?php _e('Retry', 'chatshop'); ?>
        </button>
    </div>
</div>

<!-- Analytics Styles -->
<style>
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
        font-size: 32px;
        padding: 15px;
        border-radius: 50%;
        background: #f0f0f1;
    }

    .card-value {
        font-size: 24px;
        font-weight: bold;
        color: #1d2327;
    }

    .card-label {
        font-size: 14px;
        color: #646970;
        margin-top: 5px;
    }

    /* Analytics Content */
    .chatshop-analytics-content {
        display: flex;
        flex-direction: column;
        gap: 30px;
    }

    .analytics-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }

    .analytics-section {
        background: #fff;
        border: 1px solid #c3c4c7;
        border-radius: 4px;
    }

    .section-header {
        padding: 15px 20px;
        border-bottom: 1px solid #c3c4c7;
        background: #f6f7f7;
    }

    .section-header h2 {
        margin: 0;
        font-size: 16px;
        color: #1d2327;
    }

    .section-content {
        padding: 20px;
    }

    .chart-container,
    .funnel-container,
    .attribution-container {
        min-height: 300px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .chart-placeholder,
    .loading-text {
        color: #646970;
        font-style: italic;
    }

    .performance-table {
        width: 100%;
    }

    .table-header {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 10px;
        padding: 10px 0;
        border-bottom: 1px solid #c3c4c7;
        font-weight: 600;
    }

    .performance-list {
        padding: 10px 0;
    }

    .chatshop-loading {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: rgba(255, 255, 255, 0.9);
        padding: 20px;
        border-radius: 4px;
        text-align: center;
        z-index: 9999;
    }

    .loading-spinner {
        width: 20px;
        height: 20px;
        border: 2px solid #f3f3f3;
        border-top: 2px solid #0073aa;
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin: 0 auto 10px;
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
        background: #fff;
        border-left: 4px solid #dc3232;
        padding: 15px;
        margin: 20px 0;
    }

    .chatshop-premium-notice {
        background: #fff;
        border: 1px solid #c3c4c7;
        border-radius: 4px;
        padding: 40px;
        text-align: center;
        margin: 20px 0;
    }

    .chatshop-premium-content h2 {
        color: #f39c12;
        margin-bottom: 15px;
    }

    .chatshop-premium-features ul {
        text-align: left;
        max-width: 400px;
        margin: 20px auto;
    }

    .chatshop-premium-features li {
        margin-bottom: 8px;
        color: #646970;
    }

    @media (max-width: 768px) {
        .analytics-row {
            grid-template-columns: 1fr;
        }

        .chatshop-analytics-overview {
            grid-template-columns: 1fr;
        }

        .chatshop-analytics-header {
            flex-direction: column;
            gap: 15px;
        }
    }
</style>

<!-- Basic Analytics JavaScript -->
<script>
    jQuery(document).ready(function($) {
        // Initialize analytics dashboard
        loadAnalyticsData();

        // Date range change handler
        $('#analytics-date-range').on('change', function() {
            loadAnalyticsData();
        });

        // Refresh button handler
        $('#refresh-analytics').on('click', function() {
            loadAnalyticsData();
        });

        // Export button handler
        $('#export-analytics').on('click', function() {
            alert('<?php _e('Export functionality will be implemented in the next update.', 'chatshop'); ?>');
        });
    });

    function loadAnalyticsData() {
        const dateRange = jQuery('#analytics-date-range').val();

        // Show loading
        jQuery('#analytics-loading').show();

        // Simulate loading with demo data for now
        setTimeout(function() {
            // Hide loading
            jQuery('#analytics-loading').hide();

            // Update overview cards with demo data
            jQuery('#total-revenue').text('₦45,280.00');
            jQuery('#total-conversions').text('127');
            jQuery('#conversion-rate').text('15.3%');
            jQuery('#avg-order-value').text('₦356.50');

            // Update gateway performance
            const gatewayHtml = `
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
                <div>Paystack</div>
                <div>89</div>
                <div>₦31,780.00</div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
                <div>PayPal</div>
                <div>24</div>
                <div>₦8,960.00</div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; padding: 8px 0;">
                <div>Flutterwave</div>
                <div>14</div>
                <div>₦4,540.00</div>
            </div>
        `;
            jQuery('#gateway-performance-list').html(gatewayHtml);

            // Update chart placeholders
            jQuery('#revenue-chart .chart-placeholder').text('<?php _e('Demo revenue chart data loaded successfully!', 'chatshop'); ?>');
            jQuery('#conversion-funnel .chart-placeholder').text('<?php _e('Demo conversion funnel loaded successfully!', 'chatshop'); ?>');
            jQuery('#revenue-attribution .chart-placeholder').text('<?php _e('Demo attribution data loaded successfully!', 'chatshop'); ?>');

        }, 1000);
    }
</script>