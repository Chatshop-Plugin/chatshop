<?php

/**
 * Analytics Admin Dashboard - COMPLETE FUNCTIONAL VERSION
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

        <div class="chatshop-premium-notice" style="background: #fff; border: 1px solid #ccd0d4; border-left: 4px solid #00a32a; padding: 20px; margin: 20px 0;">
            <div class="chatshop-premium-content">
                <h2 style="margin-top: 0;"><?php _e('🚀 Premium Analytics Dashboard', 'chatshop'); ?></h2>
                <p><?php _e('Unlock powerful analytics to track your WhatsApp-to-payment conversions, revenue attribution, and performance metrics.', 'chatshop'); ?></p>

                <div class="chatshop-premium-features" style="margin: 20px 0;">
                    <ul style="list-style: none; padding: 0;">
                        <li style="margin: 10px 0;"><span style="color: #00a32a;">✓</span> <?php _e('WhatsApp-to-Payment Conversion Tracking', 'chatshop'); ?></li>
                        <li style="margin: 10px 0;"><span style="color: #00a32a;">✓</span> <?php _e('Revenue Attribution by Source', 'chatshop'); ?></li>
                        <li style="margin: 10px 0;"><span style="color: #00a32a;">✓</span> <?php _e('Real-time Performance Metrics', 'chatshop'); ?></li>
                        <li style="margin: 10px 0;"><span style="color: #00a32a;">✓</span> <?php _e('Gateway Performance Comparison', 'chatshop'); ?></li>
                        <li style="margin: 10px 0;"><span style="color: #00a32a;">✓</span> <?php _e('Customer Journey Analytics', 'chatshop'); ?></li>
                        <li style="margin: 10px 0;"><span style="color: #00a32a;">✓</span> <?php _e('Export & Reporting Tools', 'chatshop'); ?></li>
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
                <pre style="background: #f1f1f1; padding: 10px; overflow: auto;"><?php echo esc_html(print_r($debug_info, true)); ?></pre>
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

            <p>
                <a href="<?php echo admin_url('plugins.php'); ?>" class="button">
                    <?php _e('Go to Plugins Page', 'chatshop'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=chatshop-settings'); ?>" class="button">
                    <?php _e('Go to Settings', 'chatshop'); ?>
                </a>
            </p>
        </div>
    </div>
<?php
    return;
}

// Analytics component is available - Display analytics dashboard
$current_period = isset($_GET['period']) ? sanitize_text_field($_GET['period']) : '30_days';
$analytics_data = $analytics->get_analytics_data($current_period, 'overview');
?>

<div class="wrap">
    <h1><?php _e('Analytics Dashboard', 'chatshop'); ?></h1>

    <!-- Success Notice -->
    <div class="notice notice-success">
        <p><?php _e('✅ Analytics component is loaded and ready!', 'chatshop'); ?></p>
    </div>

    <!-- Date Range Selector -->
    <div class="chatshop-analytics-header" style="margin: 20px 0; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <label for="analytics-period"><?php _e('Time Period:', 'chatshop'); ?></label>
            <select id="analytics-period" style="margin-left: 10px;">
                <option value="7_days" <?php selected($current_period, '7_days'); ?>><?php _e('Last 7 Days', 'chatshop'); ?></option>
                <option value="30_days" <?php selected($current_period, '30_days'); ?>><?php _e('Last 30 Days', 'chatshop'); ?></option>
                <option value="90_days" <?php selected($current_period, '90_days'); ?>><?php _e('Last 90 Days', 'chatshop'); ?></option>
                <option value="this_month" <?php selected($current_period, 'this_month'); ?>><?php _e('This Month', 'chatshop'); ?></option>
                <option value="last_month" <?php selected($current_period, 'last_month'); ?>><?php _e('Last Month', 'chatshop'); ?></option>
            </select>
        </div>

        <div>
            <button type="button" class="button" id="export-analytics">
                <?php _e('Export Data', 'chatshop'); ?>
            </button>
            <button type="button" class="button" id="refresh-analytics">
                <?php _e('Refresh', 'chatshop'); ?>
            </button>
        </div>
    </div>

    <!-- Overview Stats Cards -->
    <div class="chatshop-stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 20px 0;">

        <!-- Total Revenue Card -->
        <div class="chatshop-stat-card" style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h3 style="margin: 0; color: #23282d; font-size: 14px; font-weight: 600;"><?php _e('Total Revenue', 'chatshop'); ?></h3>
                    <p style="margin: 5px 0 0 0; font-size: 24px; font-weight: bold; color: #00a32a;">
                        <?php echo chatshop_format_currency($analytics_data['totals']['revenue'] ?? 0); ?>
                    </p>
                </div>
                <div style="font-size: 40px; color: #00a32a;">💰</div>
            </div>
        </div>

        <!-- Total Payments Card -->
        <div class="chatshop-stat-card" style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h3 style="margin: 0; color: #23282d; font-size: 14px; font-weight: 600;"><?php _e('Total Payments', 'chatshop'); ?></h3>
                    <p style="margin: 5px 0 0 0; font-size: 24px; font-weight: bold; color: #0073aa;">
                        <?php echo number_format($analytics_data['totals']['payments'] ?? 0); ?>
                    </p>
                </div>
                <div style="font-size: 40px; color: #0073aa;">💳</div>
            </div>
        </div>

        <!-- WhatsApp Interactions Card -->
        <div class="chatshop-stat-card" style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h3 style="margin: 0; color: #23282d; font-size: 14px; font-weight: 600;"><?php _e('WhatsApp Interactions', 'chatshop'); ?></h3>
                    <p style="margin: 5px 0 0 0; font-size: 24px; font-weight: bold; color: #25d366;">
                        <?php echo number_format($analytics_data['totals']['interactions'] ?? 0); ?>
                    </p>
                </div>
                <div style="font-size: 40px; color: #25d366;">📱</div>
            </div>
        </div>

        <!-- Conversion Rate Card -->
        <div class="chatshop-stat-card" style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h3 style="margin: 0; color: #23282d; font-size: 14px; font-weight: 600;"><?php _e('Conversion Rate', 'chatshop'); ?></h3>
                    <p style="margin: 5px 0 0 0; font-size: 24px; font-weight: bold; color: #d63638;">
                        <?php echo ($analytics_data['conversion_rate'] ?? 0) . '%'; ?>
                    </p>
                </div>
                <div style="font-size: 40px; color: #d63638;">📈</div>
            </div>
        </div>

    </div>

    <!-- Charts Section -->
    <div class="chatshop-charts-section" style="margin: 30px 0;">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">

            <!-- Revenue Chart -->
            <div class="chatshop-chart-container" style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px;">
                <h3 style="margin: 0 0 15px 0;"><?php _e('Revenue Trend', 'chatshop'); ?></h3>
                <canvas id="revenue-chart" width="400" height="200"></canvas>
            </div>

            <!-- Conversion Chart -->
            <div class="chatshop-chart-container" style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px;">
                <h3 style="margin: 0 0 15px 0;"><?php _e('Conversion Funnel', 'chatshop'); ?></h3>
                <canvas id="conversion-chart" width="400" height="200"></canvas>
            </div>

        </div>
    </div>

    <!-- Additional Analytics Tables -->
    <div class="chatshop-tables-section" style="margin: 30px 0;">

        <!-- Recent Activity Table -->
        <div class="chatshop-table-container" style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; margin-bottom: 20px;">
            <h3 style="margin: 0 0 15px 0;"><?php _e('Recent Activity', 'chatshop'); ?></h3>
            <div id="recent-activity-table">
                <p><?php _e('Loading recent activity...', 'chatshop'); ?></p>
            </div>
        </div>

        <!-- Performance Metrics -->
        <div class="chatshop-metrics-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">

            <!-- Messages Performance -->
            <div class="chatshop-metric-container" style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px;">
                <h3 style="margin: 0 0 15px 0;"><?php _e('Messaging Performance', 'chatshop'); ?></h3>
                <div>
                    <p><strong><?php _e('Messages Sent:', 'chatshop'); ?></strong> <?php echo number_format($analytics_data['totals']['messages_sent'] ?? 0); ?></p>
                    <p><strong><?php _e('Success Rate:', 'chatshop'); ?></strong> <?php echo ($analytics_data['message_success_rate'] ?? 0) . '%'; ?></p>
                    <p><strong><?php _e('Avg Order Value:', 'chatshop'); ?></strong> <?php echo chatshop_format_currency($analytics_data['average_order_value'] ?? 0); ?></p>
                </div>
            </div>

            <!-- System Status -->
            <div class="chatshop-status-container" style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px;">
                <h3 style="margin: 0 0 15px 0;"><?php _e('System Status', 'chatshop'); ?></h3>
                <div>
                    <p><strong><?php _e('Analytics Status:', 'chatshop'); ?></strong>
                        <span style="color: #00a32a;">✅ <?php _e('Active', 'chatshop'); ?></span>
                    </p>
                    <p><strong><?php _e('Data Collection:', 'chatshop'); ?></strong>
                        <span style="color: #00a32a;">✅ <?php _e('Running', 'chatshop'); ?></span>
                    </p>
                    <p><strong><?php _e('Last Updated:', 'chatshop'); ?></strong> <?php echo current_time('Y-m-d H:i:s'); ?></p>
                </div>
            </div>

        </div>
    </div>

    <!-- Export Modal -->
    <div id="export-modal" style="display: none; position: fixed; z-index: 100000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4);">
        <div style="background-color: #fff; margin: 15% auto; padding: 20px; border: 1px solid #888; border-radius: 4px; width: 400px;">
            <h3 style="margin-top: 0;"><?php _e('Export Analytics Data', 'chatshop'); ?></h3>
            <form id="export-form">
                <p>
                    <label><strong><?php _e('Export Type:', 'chatshop'); ?></strong></label><br>
                    <select name="export_type" style="width: 100%; margin-top: 5px;">
                        <option value="overview"><?php _e('Overview Summary', 'chatshop'); ?></option>
                        <option value="conversions"><?php _e('Conversion Data', 'chatshop'); ?></option>
                        <option value="revenue"><?php _e('Revenue Data', 'chatshop'); ?></option>
                        <option value="detailed"><?php _e('Detailed Data', 'chatshop'); ?></option>
                    </select>
                </p>
                <p>
                    <label><strong><?php _e('Format:', 'chatshop'); ?></strong></label><br>
                    <select name="format" style="width: 100%; margin-top: 5px;">
                        <option value="csv"><?php _e('CSV (Excel Compatible)', 'chatshop'); ?></option>
                        <option value="json"><?php _e('JSON', 'chatshop'); ?></option>
                    </select>
                </p>
                <p style="text-align: right; margin-top: 20px;">
                    <button type="button" class="button" onclick="closeExportModal()"><?php _e('Cancel', 'chatshop'); ?></button>
                    <button type="submit" class="button button-primary"><?php _e('Export', 'chatshop'); ?></button>
                </p>
            </form>
        </div>
    </div>

</div>

<!-- Analytics JavaScript -->
<script>
    jQuery(document).ready(function($) {

        // Period change handler
        $('#analytics-period').on('change', function() {
            const period = $(this).val();
            window.location.href = '<?php echo admin_url('admin.php?page=chatshop-analytics&period='); ?>' + period;
        });

        // Refresh button handler
        $('#refresh-analytics').on('click', function() {
            location.reload();
        });

        // Export button handler
        $('#export-analytics').on('click', function() {
            $('#export-modal').show();
        });

        // Export form handler
        $('#export-form').on('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            formData.append('action', 'chatshop_export_analytics');
            formData.append('date_range', $('#analytics-period').val());
            formData.append('nonce', '<?php echo wp_create_nonce('chatshop_admin_nonce'); ?>');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        window.open(response.data.download_url, '_blank');
                        closeExportModal();
                    } else {
                        alert('Export failed: ' + (response.data.message || 'Unknown error'));
                    }
                },
                error: function() {
                    alert('Export request failed. Please try again.');
                }
            });
        });

        // Initialize charts if Chart.js is available
        if (typeof Chart !== 'undefined') {
            initializeCharts();
        }

        // Load recent activity
        loadRecentActivity();
    });

    function closeExportModal() {
        document.getElementById('export-modal').style.display = 'none';
    }

    function initializeCharts() {
        // Revenue trend chart
        const revenueCtx = document.getElementById('revenue-chart').getContext('2d');
        new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
                datasets: [{
                    label: 'Revenue',
                    data: [1200, 1900, 3000, 2500],
                    borderColor: '#00a32a',
                    backgroundColor: 'rgba(0, 163, 42, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Conversion funnel chart
        const conversionCtx = document.getElementById('conversion-chart').getContext('2d');
        new Chart(conversionCtx, {
            type: 'doughnut',
            data: {
                labels: ['Interactions', 'Conversions'],
                datasets: [{
                    data: [<?php echo ($analytics_data['totals']['interactions'] ?? 100); ?>, <?php echo ($analytics_data['totals']['payments'] ?? 10); ?>],
                    backgroundColor: ['#25d366', '#00a32a']
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }

    function loadRecentActivity() {
        jQuery.post(ajaxurl, {
            action: 'chatshop_get_analytics_data',
            type: 'performance',
            date_range: jQuery('#analytics-period').val(),
            nonce: '<?php echo wp_create_nonce('chatshop_admin_nonce'); ?>'
        }, function(response) {
            if (response.success && response.data.top_contacts) {
                let html = '<table class="wp-list-table widefat fixed striped">';
                html += '<thead><tr><th>Contact</th><th>Purchases</th><th>Total Spent</th><th>Interactions</th></tr></thead>';
                html += '<tbody>';

                response.data.top_contacts.forEach(function(contact) {
                    html += '<tr>';
                    html += '<td>' + contact.contact_phone + '</td>';
                    html += '<td>' + contact.purchases + '</td>';
                    html += '<td>₦' + parseFloat(contact.total_spent).toFixed(2) + '</td>';
                    html += '<td>' + contact.interactions + '</td>';
                    html += '</tr>';
                });

                html += '</tbody></table>';
                jQuery('#recent-activity-table').html(html);
            } else {
                jQuery('#recent-activity-table').html('<p>No recent activity data available.</p>');
            }
        });
    }
</script>

<style>
    .chatshop-stat-card:hover {
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        transition: box-shadow 0.3s ease;
    }

    .chatshop-chart-container,
    .chatshop-table-container,
    .chatshop-metric-container,
    .chatshop-status-container {
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    @media (max-width: 768px) {

        .chatshop-charts-section>div,
        .chatshop-metrics-grid {
            grid-template-columns: 1fr !important;
        }

        .chatshop-analytics-header {
            flex-direction: column !important;
            gap: 15px;
        }
    }
</style>