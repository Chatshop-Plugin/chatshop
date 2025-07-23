<?php

/**
 * ChatShop Helper Functions - Complete & Consolidated
 *
 * File: includes/class-chatshop-helper.php
 * 
 * Contains all utility functions for the ChatShop plugin including
 * analytics tracking, data formatting, premium feature management,
 * and global helper functions for easy integration.
 *
 * @package ChatShop
 * @subpackage Includes
 * @since 1.0.0
 */

namespace ChatShop;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ChatShop Helper Class
 *
 * Contains utility functions for the ChatShop plugin including
 * analytics tracking, data formatting, and premium feature management.
 *
 * @since 1.0.0
 */
class ChatShop_Helper
{
    /**
     * Track analytics event
     *
     * @since 1.0.0
     * @param string $metric_type Type of metric (interaction, payment, conversion)
     * @param string $metric_name Specific metric name
     * @param mixed  $metric_value Metric value
     * @param array  $meta Additional metadata
     * @return bool Success status
     */
    public static function track_analytics_event($metric_type, $metric_name, $metric_value = 1, $meta = array())
    {
        $analytics = chatshop_get_component('analytics');

        if (!$analytics) {
            chatshop_log('Analytics component not available for tracking', 'warning');
            return false;
        }

        global $wpdb;
        $table_name = $analytics->get_table_name();

        $data = array(
            'metric_type' => sanitize_text_field($metric_type),
            'metric_name' => sanitize_text_field($metric_name),
            'metric_value' => is_numeric($metric_value) ? floatval($metric_value) : 1,
            'metric_date' => current_time('Y-m-d'),
            'source_type' => isset($meta['source_type']) ? sanitize_text_field($meta['source_type']) : 'whatsapp',
            'source_id' => isset($meta['source_id']) ? sanitize_text_field($meta['source_id']) : '',
            'contact_id' => isset($meta['contact_id']) ? absint($meta['contact_id']) : null,
            'payment_id' => isset($meta['payment_id']) ? sanitize_text_field($meta['payment_id']) : '',
            'gateway' => isset($meta['gateway']) ? sanitize_text_field($meta['gateway']) : '',
            'revenue' => isset($meta['revenue']) ? floatval($meta['revenue']) : 0,
            'currency' => isset($meta['currency']) ? sanitize_text_field($meta['currency']) : 'NGN',
            'meta_data' => wp_json_encode($meta)
        );

        $result = $wpdb->insert($table_name, $data);

        if ($result) {
            chatshop_log("Analytics event tracked: {$metric_type}.{$metric_name}", 'info', $data);
            return true;
        } else {
            chatshop_log("Failed to track analytics event: {$metric_type}.{$metric_name}", 'error');
            return false;
        }
    }

    /**
     * Format currency value
     *
     * @since 1.0.0
     * @param float  $amount Amount to format
     * @param string $currency Currency code
     * @return string Formatted currency string
     */
    public static function format_currency($amount, $currency = 'NGN')
    {
        $amount = floatval($amount);

        switch (strtoupper($currency)) {
            case 'NGN':
                return '₦' . number_format($amount, 0);
            case 'USD':
                return '$' . number_format($amount, 2);
            case 'GHS':
                return 'GH₵' . number_format($amount, 2);
            case 'KES':
                return 'KSh' . number_format($amount, 0);
            case 'ZAR':
                return 'R' . number_format($amount, 2);
            default:
                return strtoupper($currency) . ' ' . number_format($amount, 2);
        }
    }

    /**
     * Format number with thousand separators
     *
     * @since 1.0.0
     * @param mixed $number Number to format
     * @return string Formatted number
     */
    public static function format_number($number)
    {
        return number_format(floatval($number));
    }

    /**
     * Calculate percentage with proper rounding
     *
     * @since 1.0.0
     * @param float $part Part value
     * @param float $total Total value
     * @param int   $decimals Number of decimal places
     * @return float Calculated percentage
     */
    public static function calculate_percentage($part, $total, $decimals = 2)
    {
        if ($total == 0) {
            return 0;
        }

        return round(($part / $total) * 100, $decimals);
    }

    /**
     * Get date range array for analytics queries
     *
     * @since 1.0.0
     * @param string $range Range identifier (7days, 30days, 90days, 365days)
     * @return array Date filter with start and end dates
     */
    public static function get_date_range($range)
    {
        $end_date = current_time('Y-m-d');

        switch ($range) {
            case '7days':
                $start_date = date('Y-m-d', strtotime('-7 days'));
                break;
            case '30days':
                $start_date = date('Y-m-d', strtotime('-30 days'));
                break;
            case '90days':
                $start_date = date('Y-m-d', strtotime('-90 days'));
                break;
            case '365days':
                $start_date = date('Y-m-d', strtotime('-365 days'));
                break;
            case 'this_month':
                $start_date = date('Y-m-01');
                break;
            case 'last_month':
                $start_date = date('Y-m-01', strtotime('first day of last month'));
                $end_date = date('Y-m-t', strtotime('last day of last month'));
                break;
            default:
                $start_date = date('Y-m-d', strtotime('-7 days'));
        }

        return array(
            'start' => $start_date,
            'end' => $end_date
        );
    }

    /**
     * Validate phone number format
     *
     * @since 1.0.0
     * @param string $phone Phone number to validate
     * @return string|false Formatted phone number or false if invalid
     */
    public static function validate_phone_number($phone)
    {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Check if it's a valid Nigerian number
        if (preg_match('/^(?:\+?234|0)?([789][01]\d{8})$/', $phone, $matches)) {
            return '+234' . $matches[1];
        }

        // Check for international format
        if (preg_match('/^\+?(\d{10,15})$/', $phone, $matches)) {
            return '+' . $matches[1];
        }

        return false;
    }

    /**
     * Sanitize contact data
     *
     * @since 1.0.0
     * @param array $data Raw contact data
     * @return array Sanitized contact data
     */
    public static function sanitize_contact_data($data)
    {
        return array(
            'phone' => self::validate_phone_number($data['phone'] ?? ''),
            'name' => sanitize_text_field($data['name'] ?? ''),
            'email' => sanitize_email($data['email'] ?? ''),
            'tags' => sanitize_text_field($data['tags'] ?? ''),
            'notes' => sanitize_textarea_field($data['notes'] ?? ''),
            'status' => in_array($data['status'] ?? 'active', array('active', 'inactive', 'blocked'))
                ? $data['status'] : 'active',
            'opt_in_status' => in_array($data['opt_in_status'] ?? 'pending', array('opted_in', 'opted_out', 'pending'))
                ? $data['opt_in_status'] : 'pending'
        );
    }

    /**
     * Generate unique transaction reference
     *
     * @since 1.0.0
     * @param string $prefix Optional prefix
     * @return string Transaction reference
     */
    public static function generate_transaction_reference($prefix = 'CS')
    {
        return $prefix . '_' . time() . '_' . wp_generate_password(8, false);
    }

    /**
     * Check if analytics feature is enabled
     *
     * @since 1.0.0
     * @return bool Whether analytics is enabled
     */
    public static function is_analytics_enabled()
    {
        return chatshop_is_premium_feature_available('analytics') ||
            chatshop_is_premium_feature_available('advanced_analytics');
    }

    /**
     * Get premium features list
     *
     * @since 1.0.0
     * @return array Available premium features
     */
    public static function get_premium_features()
    {
        return array(
            'unlimited_contacts' => array(
                'name' => __('Unlimited Contacts', 'chatshop'),
                'description' => __('Add unlimited contacts to your WhatsApp marketing campaigns', 'chatshop')
            ),
            'contact_import_export' => array(
                'name' => __('Contact Import/Export', 'chatshop'),
                'description' => __('Import contacts from CSV/Excel files and export for backup', 'chatshop')
            ),
            'bulk_messaging' => array(
                'name' => __('Bulk Messaging', 'chatshop'),
                'description' => __('Send messages to multiple contacts simultaneously', 'chatshop')
            ),
            'analytics' => array(
                'name' => __('Analytics Dashboard', 'chatshop'),
                'description' => __('Advanced analytics with conversion tracking and revenue attribution', 'chatshop')
            ),
            'advanced_analytics' => array(
                'name' => __('Advanced Analytics', 'chatshop'),
                'description' => __('Detailed analytics with custom reporting and data export', 'chatshop')
            ),
            'multiple_gateways' => array(
                'name' => __('Multiple Payment Gateways', 'chatshop'),
                'description' => __('Support for multiple payment providers beyond Paystack', 'chatshop')
            ),
            'campaign_automation' => array(
                'name' => __('Campaign Automation', 'chatshop'),
                'description' => __('Automated WhatsApp marketing campaigns with scheduling', 'chatshop')
            )
        );
    }

    /**
     * Get analytics metrics configuration
     *
     * @since 1.0.0
     * @return array Metrics configuration
     */
    public static function get_analytics_metrics_config()
    {
        return array(
            'interaction' => array(
                'message_sent' => __('Message Sent', 'chatshop'),
                'message_opened' => __('Message Opened', 'chatshop'),
                'message_replied' => __('Message Replied', 'chatshop'),
                'link_clicked' => __('Link Clicked', 'chatshop'),
                'contact_added' => __('Contact Added', 'chatshop'),
                'contact_updated' => __('Contact Updated', 'chatshop')
            ),
            'payment' => array(
                'payment_initiated' => __('Payment Initiated', 'chatshop'),
                'payment_completed' => __('Payment Completed', 'chatshop'),
                'payment_failed' => __('Payment Failed', 'chatshop'),
                'payment_cancelled' => __('Payment Cancelled', 'chatshop'),
                'refund_processed' => __('Refund Processed', 'chatshop')
            ),
            'conversion' => array(
                'whatsapp_to_payment' => __('WhatsApp to Payment', 'chatshop'),
                'contact_to_customer' => __('Contact to Customer', 'chatshop'),
                'campaign_conversion' => __('Campaign Conversion', 'chatshop')
            )
        );
    }

    /**
     * Export analytics data to CSV
     *
     * @since 1.0.0
     * @param array  $data Analytics data
     * @param string $filename Optional filename
     * @return array Export result with content and filename
     */
    public static function export_analytics_to_csv($data, $filename = null)
    {
        if (!$filename) {
            $filename = 'chatshop-analytics-' . date('Y-m-d-H-i-s') . '.csv';
        }

        $csv_content = '';

        if (!empty($data)) {
            // Get headers from first row
            $headers = array_keys($data[0]);
            $csv_content .= implode(',', array_map(array(self::class, 'escape_csv_field'), $headers)) . "\n";

            // Add data rows
            foreach ($data as $row) {
                $csv_content .= implode(',', array_map(array(self::class, 'escape_csv_field'), array_values($row))) . "\n";
            }
        }

        return array(
            'content' => $csv_content,
            'filename' => $filename,
            'mime_type' => 'text/csv'
        );
    }

    /**
     * Escape CSV field
     *
     * @since 1.0.0
     * @param string $field Field value
     * @return string Escaped field
     */
    private static function escape_csv_field($field)
    {
        if (strpos($field, ',') !== false || strpos($field, '"') !== false || strpos($field, "\n") !== false) {
            return '"' . str_replace('"', '""', $field) . '"';
        }
        return $field;
    }

    /**
     * Clean old analytics data
     *
     * @since 1.0.0
     * @param int $days_to_keep Number of days to keep data
     * @return int Number of records deleted
     */
    public static function clean_old_analytics_data($days_to_keep = 730)
    {
        global $wpdb;

        $analytics = chatshop_get_component('analytics');
        if (!$analytics) {
            return 0;
        }

        $table_name = $analytics->get_table_name();
        $cutoff_date = date('Y-m-d', strtotime("-{$days_to_keep} days"));

        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table_name} WHERE metric_date < %s",
            $cutoff_date
        ));

        if ($deleted) {
            chatshop_log("Cleaned up {$deleted} old analytics records older than {$cutoff_date}", 'info');
        }

        return intval($deleted);
    }

    /**
     * Get gateway colors for charts
     *
     * @since 1.0.0
     * @return array Gateway colors mapping
     */
    public static function get_gateway_colors()
    {
        return array(
            'paystack' => '#1B5E20',
            'paypal' => '#003087',
            'flutterwave' => '#F5A623',
            'razorpay' => '#528FF0',
            'stripe' => '#6772E5',
            'default' => '#666666'
        );
    }

    /**
     * Get source type colors for charts
     *
     * @since 1.0.0
     * @return array Source type colors mapping
     */
    public static function get_source_colors()
    {
        return array(
            'whatsapp' => '#25D366',
            'website' => '#135e96',
            'email' => '#EA4335',
            'sms' => '#FF9800',
            'direct' => '#9C27B0',
            'other' => '#757575'
        );
    }

    /**
     * Format analytics period label
     *
     * @since 1.0.0
     * @param string $period Period identifier
     * @return string Formatted period label
     */
    public static function format_period_label($period)
    {
        switch ($period) {
            case '7days':
                return __('Last 7 Days', 'chatshop');
            case '30days':
                return __('Last 30 Days', 'chatshop');
            case '90days':
                return __('Last 90 Days', 'chatshop');
            case '365days':
                return __('Last Year', 'chatshop');
            case 'this_month':
                return __('This Month', 'chatshop');
            case 'last_month':
                return __('Last Month', 'chatshop');
            default:
                return __('Custom Period', 'chatshop');
        }
    }

    /**
     * Get conversion rate status class
     *
     * @since 1.0.0
     * @param float $rate Conversion rate percentage
     * @return string CSS class for styling
     */
    public static function get_conversion_rate_class($rate)
    {
        if ($rate >= 10) {
            return 'excellent';
        } elseif ($rate >= 5) {
            return 'good';
        } elseif ($rate >= 2) {
            return 'average';
        } else {
            return 'poor';
        }
    }

    /**
     * Calculate growth rate between two values
     *
     * @since 1.0.0
     * @param float $current Current value
     * @param float $previous Previous value
     * @return float Growth rate percentage
     */
    public static function calculate_growth_rate($current, $previous)
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }

        return round((($current - $previous) / $previous) * 100, 2);
    }

    /**
     * Get analytics dashboard URL
     *
     * @since 1.0.0
     * @return string Analytics dashboard URL
     */
    public static function get_analytics_url()
    {
        return admin_url('admin.php?page=chatshop-analytics');
    }

    /**
     * Check if current user can view analytics
     *
     * @since 1.0.0
     * @return bool Whether user can view analytics
     */
    public static function can_view_analytics()
    {
        return current_user_can('manage_options') && self::is_analytics_enabled();
    }

    /**
     * Get formatted time period for display
     *
     * @since 1.0.0
     * @param string $start_date Start date (Y-m-d format)
     * @param string $end_date End date (Y-m-d format)
     * @return string Formatted time period
     */
    public static function format_date_period($start_date, $end_date)
    {
        $start = date_create($start_date);
        $end = date_create($end_date);

        if (!$start || !$end) {
            return '';
        }

        $start_formatted = date_format($start, 'M j, Y');
        $end_formatted = date_format($end, 'M j, Y');

        if ($start_date === $end_date) {
            return $start_formatted;
        }

        return $start_formatted . ' - ' . $end_formatted;
    }

    /**
     * Get default analytics settings
     *
     * @since 1.0.0
     * @return array Default analytics settings
     */
    public static function get_default_analytics_settings()
    {
        return array(
            'tracking_enabled' => true,
            'data_retention_days' => 730,
            'auto_cleanup' => true,
            'export_format' => 'csv',
            'dashboard_refresh_interval' => 300, // 5 minutes
            'conversion_attribution_window' => 7, // days
            'revenue_currency' => 'NGN'
        );
    }

    /**
     * Validate analytics date range
     *
     * @since 1.0.0
     * @param string $start_date Start date
     * @param string $end_date End date
     * @return array Validation result
     */
    public static function validate_date_range($start_date, $end_date)
    {
        $start = date_create($start_date);
        $end = date_create($end_date);
        $now = date_create(current_time('Y-m-d'));

        if (!$start || !$end) {
            return array(
                'valid' => false,
                'message' => __('Invalid date format.', 'chatshop')
            );
        }

        if ($start > $end) {
            return array(
                'valid' => false,
                'message' => __('Start date must be before end date.', 'chatshop')
            );
        }

        if ($end > $now) {
            return array(
                'valid' => false,
                'message' => __('End date cannot be in the future.', 'chatshop')
            );
        }

        // Check if range is too large (more than 2 years)
        $diff = date_diff($start, $end);
        if ($diff->days > 730) {
            return array(
                'valid' => false,
                'message' => __('Date range cannot exceed 2 years.', 'chatshop')
            );
        }

        return array(
            'valid' => true,
            'message' => __('Valid date range.', 'chatshop')
        );
    }

    /**
     * Get analytics cache key
     *
     * @since 1.0.0
     * @param string $type Cache type
     * @param array  $params Parameters for cache key
     * @return string Cache key
     */
    public static function get_analytics_cache_key($type, $params = array())
    {
        $key_parts = array('chatshop_analytics', $type);

        foreach ($params as $param) {
            $key_parts[] = sanitize_key($param);
        }

        return implode('_', $key_parts);
    }

    /**
     * Clear analytics cache
     *
     * @since 1.0.0
     * @param string $pattern Optional cache pattern to clear
     * @return bool Success status
     */
    public static function clear_analytics_cache($pattern = null)
    {
        // Clear WordPress transients
        $transients = array(
            'chatshop_analytics_overview_7days',
            'chatshop_analytics_overview_30days',
            'chatshop_analytics_overview_90days',
            'chatshop_analytics_overview_365days',
            'chatshop_analytics_conversions_7days',
            'chatshop_analytics_conversions_30days',
            'chatshop_analytics_conversions_90days',
            'chatshop_analytics_conversions_365days',
            'chatshop_analytics_revenue_7days',
            'chatshop_analytics_revenue_30days',
            'chatshop_analytics_revenue_90days',
            'chatshop_analytics_revenue_365days'
        );

        foreach ($transients as $transient) {
            delete_transient($transient);
        }

        chatshop_log('Analytics cache cleared', 'info');

        return true;
    }
}

// ================================
// GLOBAL HELPER FUNCTIONS
// ================================

/**
 * Check if analytics is enabled and available
 *
 * @since 1.0.0
 * @return bool Whether analytics is available
 */
function chatshop_is_analytics_enabled()
{
    return chatshop_is_premium_feature_available('analytics') ||
        chatshop_is_premium_feature_available('advanced_analytics');
}

/**
 * Track analytics event - Global function for easy integration
 *
 * @since 1.0.0
 * @param string $metric_type Type of metric (interaction, payment, conversion)
 * @param string $metric_name Specific metric name
 * @param mixed  $metric_value Metric value (default: 1)
 * @param array  $meta Additional metadata
 * @return bool Success status
 */
function chatshop_track_analytics($metric_type, $metric_name, $metric_value = 1, $meta = array())
{
    // Check if analytics is enabled
    if (!chatshop_is_analytics_enabled()) {
        return false;
    }

    return ChatShop_Helper::track_analytics_event($metric_type, $metric_name, $metric_value, $meta);
}

/**
 * Track payment conversion - Integration with existing payment system
 *
 * @since 1.0.0
 * @param array  $payment_data Payment data from existing system
 * @param string $gateway Gateway identifier
 * @return bool Success status
 */
function chatshop_track_payment_conversion($payment_data, $gateway)
{
    if (!chatshop_is_analytics_enabled()) {
        return false;
    }

    // Prepare meta data
    $meta = array(
        'source_type' => 'whatsapp',
        'contact_id' => $payment_data['contact_id'] ?? null,
        'payment_id' => $payment_data['reference'] ?? '',
        'gateway' => $gateway,
        'revenue' => isset($payment_data['amount']) ? ($payment_data['amount'] / 100) : 0, // Convert from kobo
        'currency' => $payment_data['currency'] ?? 'NGN'
    );

    // Track payment completion
    chatshop_track_analytics('payment', 'payment_completed', 1, $meta);

    // Track conversion
    chatshop_track_analytics('conversion', 'whatsapp_to_payment', 1, $meta);

    return true;
}

/**
 * Track contact interaction - Integration with existing contact system
 *
 * @since 1.0.0
 * @param int    $contact_id Contact ID
 * @param string $interaction_type Type of interaction
 * @param array  $meta Additional metadata
 * @return bool Success status
 */
function chatshop_track_contact_interaction($contact_id, $interaction_type, $meta = array())
{
    if (!chatshop_is_analytics_enabled()) {
        return false;
    }

    // Prepare meta data
    $meta['contact_id'] = $contact_id;
    $meta['source_type'] = $meta['source_type'] ?? 'whatsapp';

    return chatshop_track_analytics('interaction', $interaction_type, 1, $meta);
}

/**
 * Format currency for display - Compatibility with existing system
 *
 * @since 1.0.0
 * @param float  $amount Amount to format
 * @param string $currency Currency code
 * @return string Formatted currency
 */
function chatshop_format_currency($amount, $currency = 'NGN')
{
    return ChatShop_Helper::format_currency($amount, $currency);
}

/**
 * Format number with separators - Compatibility function
 *
 * @since 1.0.0
 * @param mixed $number Number to format
 * @return string Formatted number
 */
function chatshop_format_number($number)
{
    return ChatShop_Helper::format_number($number);
}

/**
 * Get analytics dashboard URL
 *
 * @since 1.0.0
 * @return string Analytics dashboard URL
 */
function chatshop_get_analytics_url()
{
    return ChatShop_Helper::get_analytics_url();
}

/**
 * Check if current user can view analytics
 *
 * @since 1.0.0
 * @return bool Whether user can view analytics
 */
function chatshop_can_user_view_analytics()
{
    return ChatShop_Helper::can_view_analytics();
}

// ================================
// INTEGRATION HOOKS
// ================================

// Hook into existing payment completion action
add_action('chatshop_payment_completed', 'chatshop_track_payment_conversion', 10, 2);

// Hook into existing contact interaction action
add_action('chatshop_contact_interaction', 'chatshop_track_contact_interaction', 10, 3);

// Hook to clear analytics cache when settings change
add_action('update_option_chatshop_premium_features', function () {
    ChatShop_Helper::clear_analytics_cache();
});

// Hook for admin enqueue scripts - analytics assets
add_action('admin_enqueue_scripts', function ($hook_suffix) {
    // Only load on analytics page
    if ($hook_suffix !== 'chatshop_page_chatshop-analytics') {
        return;
    }

    // Verify analytics component is available
    $analytics = chatshop_get_component('analytics');
    if (!$analytics) {
        return;
    }

    // Add analytics-specific localization
    wp_localize_script('chatshop-admin', 'chatshop_analytics', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('chatshop_admin_nonce'),
        'is_premium' => chatshop_is_analytics_enabled(),
        'upgrade_url' => admin_url('admin.php?page=chatshop-premium'),
        'strings' => array(
            'loading_analytics' => __('Loading analytics data...', 'chatshop'),
            'export_starting' => __('Starting export...', 'chatshop'),
            'export_complete' => __('Export completed successfully.', 'chatshop'),
            'error_loading' => __('Error loading analytics data.', 'chatshop'),
            'no_data_period' => __('No data available for this period.', 'chatshop'),
            'retry_action' => __('Retry', 'chatshop'),
            'premium_required' => __('Premium access required for this feature.', 'chatshop')
        )
    ));
});

// Hook for analytics menu styling
add_action('admin_head', function () {
    if (strpos(get_current_screen()->id, 'chatshop') !== false) {
?>
        <style>
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
        </style>
    <?php
    }
});

// Hook for export functionality
add_action('wp_ajax_chatshop_export_analytics', function () {
    // Verify this is properly handled by the analytics component
    $analytics = chatshop_get_component('analytics');
    if (!$analytics) {
        wp_send_json_error(array(
            'message' => __('Analytics component not available.', 'chatshop')
        ));
    }

    // Check if export handler exists
    $export_class = 'ChatShop\ChatShop_Analytics_Export';
    if (class_exists($export_class)) {
        $exporter = new $export_class($analytics);
        $exporter->handle_export_request();
    } else {
        wp_send_json_error(array(
            'message' => __('Export functionality not available.', 'chatshop')
        ));
    }
});

// Hook for dashboard widget integration
add_action('wp_dashboard_setup', function () {
    if (chatshop_is_analytics_enabled() && current_user_can('manage_options')) {
        wp_add_dashboard_widget(
            'chatshop_analytics_summary',
            __('ChatShop Analytics Summary', 'chatshop'),
            'chatshop_render_analytics_dashboard_widget'
        );
    }
});

/**
 * Render analytics dashboard widget
 *
 * @since 1.0.0
 */
function chatshop_render_analytics_dashboard_widget()
{
    $analytics = chatshop_get_component('analytics');
    if (!$analytics) {
        echo '<p>' . __('Analytics data unavailable.', 'chatshop') . '</p>';
        return;
    }

    // Get quick stats for the widget
    $overview_data = $analytics->get_analytics_data('7days', 'overview');

    ?>
    <div class="chatshop-dashboard-widget">
        <div class="chatshop-widget-stats">
            <div class="stat-item">
                <span class="stat-label"><?php _e('7-Day Revenue', 'chatshop'); ?>:</span>
                <span class="stat-value"><?php echo chatshop_format_currency($overview_data['totals']['revenue'] ?? 0); ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-label"><?php _e('Conversions', 'chatshop'); ?>:</span>
                <span class="stat-value"><?php echo chatshop_format_number($overview_data['totals']['conversions'] ?? 0); ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-label"><?php _e('Conversion Rate', 'chatshop'); ?>:</span>
                <span class="stat-value"><?php echo ($overview_data['conversion_rate'] ?? 0) . '%'; ?></span>
            </div>
        </div>
        <p class="chatshop-widget-link">
            <a href="<?php echo chatshop_get_analytics_url(); ?>" class="button button-primary">
                <?php _e('View Full Analytics', 'chatshop'); ?>
            </a>
        </p>
    </div>

    <style>
        .chatshop-dashboard-widget .chatshop-widget-stats {
            display: grid;
            grid-template-columns: 1fr;
            gap: 10px;
            margin-bottom: 15px;
        }

        .chatshop-dashboard-widget .stat-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f1;
        }

        .chatshop-dashboard-widget .stat-label {
            font-weight: 600;
            color: #1d2327;
        }

        .chatshop-dashboard-widget .stat-value {
            font-weight: bold;
            color: #135e96;
        }

        .chatshop-widget-link {
            text-align: center;
            margin: 0;
        }
    </style>
<?php
}
