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
 * IMPORTANT: This class contains ONLY class methods. All global functions
 * are declared in includes/chatshop-global-functions.php to prevent duplicates.
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
            chatshop_log("Failed to track analytics event: {$metric_type}.{$metric_name}", 'error', array(
                'data' => $data,
                'db_error' => $wpdb->last_error
            ));
            return false;
        }
    }

    /**
     * Format currency for display
     *
     * @since 1.0.0
     * @param float  $amount Amount to format
     * @param string $currency Currency code
     * @return string Formatted currency
     */
    public static function format_currency($amount, $currency = 'NGN')
    {
        $symbols = array(
            'NGN' => '₦',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'ZAR' => 'R',
            'GHS' => '₵',
            'KES' => 'KSh'
        );

        $symbol = isset($symbols[$currency]) ? $symbols[$currency] : $currency;
        return $symbol . number_format((float) $amount, 2);
    }

    /**
     * Format number with separators
     *
     * @since 1.0.0
     * @param mixed $number Number to format
     * @param int   $decimals Number of decimal places
     * @return string Formatted number
     */
    public static function format_number($number, $decimals = 0)
    {
        return number_format((float) $number, $decimals);
    }

    /**
     * Format phone number for display
     *
     * @since 1.0.0
     * @param string $phone Phone number to format
     * @return string Formatted phone number
     */
    public static function format_phone($phone)
    {
        $cleaned = preg_replace('/[^\d+]/', '', $phone);

        // Nigerian format: +234 xxx xxx xxxx
        if (strpos($cleaned, '+234') === 0) {
            $number = substr($cleaned, 4);
            if (strlen($number) === 10) {
                return '+234 ' . substr($number, 0, 3) . ' ' . substr($number, 3, 3) . ' ' . substr($number, 6);
            }
        }

        return $cleaned;
    }

    /**
     * Get analytics URL
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
        return current_user_can('manage_options') && chatshop_is_analytics_enabled();
    }

    /**
     * Get payment gateway display name
     *
     * @since 1.0.0
     * @param string $gateway_id Gateway identifier
     * @return string Gateway display name
     */
    public static function get_gateway_display_name($gateway_id)
    {
        $gateways = array(
            'paystack' => __('Paystack', 'chatshop'),
            'paypal' => __('PayPal', 'chatshop'),
            'flutterwave' => __('Flutterwave', 'chatshop'),
            'razorpay' => __('Razorpay', 'chatshop')
        );

        return isset($gateways[$gateway_id]) ? $gateways[$gateway_id] : ucfirst($gateway_id);
    }

    /**
     * Get contact source display name
     *
     * @since 1.0.0
     * @param string $source_type Source type
     * @return string Source display name
     */
    public static function get_source_display_name($source_type)
    {
        $sources = array(
            'whatsapp' => __('WhatsApp', 'chatshop'),
            'manual' => __('Manual Entry', 'chatshop'),
            'import' => __('Import', 'chatshop'),
            'woocommerce' => __('WooCommerce', 'chatshop'),
            'form' => __('Contact Form', 'chatshop')
        );

        return isset($sources[$source_type]) ? $sources[$source_type] : ucfirst($source_type);
    }

    /**
     * Get time period display name
     *
     * @since 1.0.0
     * @param string $period Period identifier
     * @return string Period display name
     */
    public static function get_period_display_name($period)
    {
        $periods = array(
            '7days' => __('Last 7 Days', 'chatshop'),
            '30days' => __('Last 30 Days', 'chatshop'),
            '90days' => __('Last 90 Days', 'chatshop'),
            '365days' => __('Last 365 Days', 'chatshop'),
            'this_month' => __('This Month', 'chatshop'),
            'last_month' => __('Last Month', 'chatshop'),
            'this_year' => __('This Year', 'chatshop'),
            'last_year' => __('Last Year', 'chatshop')
        );

        return isset($periods[$period]) ? $periods[$period] : ucfirst($period);
    }

    /**
     * Validate date range for analytics
     *
     * @since 1.0.0
     * @param string $start_date Start date (Y-m-d format)
     * @param string $end_date End date (Y-m-d format)
     * @return array Validation result
     */
    public static function validate_date_range($start_date, $end_date)
    {
        $start = DateTime::createFromFormat('Y-m-d', $start_date);
        $end = DateTime::createFromFormat('Y-m-d', $end_date);
        $now = new DateTime();

        if (!$start || !$end) {
            return array(
                'valid' => false,
                'message' => __('Invalid date format. Please use YYYY-MM-DD format.', 'chatshop')
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

    /**
     * Generate secure payment link
     *
     * @since 1.0.0
     * @param array $payment_data Payment data
     * @return string Payment link
     */
    public static function generate_payment_link($payment_data)
    {
        $link_id = wp_generate_password(12, false);

        // Store payment link data
        $link_data = array(
            'link_id' => $link_id,
            'amount' => $payment_data['amount'],
            'currency' => $payment_data['currency'] ?? 'NGN',
            'description' => $payment_data['description'] ?? '',
            'contact_id' => $payment_data['contact_id'] ?? null,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+24 hours'))
        );

        // Save to database
        global $wpdb;
        $table_name = $wpdb->prefix . 'chatshop_payment_links';
        $wpdb->insert($table_name, $link_data);

        // Generate URL
        return add_query_arg(array(
            'chatshop_payment' => $link_id
        ), home_url('/'));
    }

    /**
     * Sanitize contact data
     *
     * @since 1.0.0
     * @param array $contact_data Raw contact data
     * @return array Sanitized contact data
     */
    public static function sanitize_contact_data($contact_data)
    {
        $sanitized = array();

        // Required fields
        if (isset($contact_data['phone'])) {
            $sanitized['phone'] = chatshop_validate_phone($contact_data['phone']);
        }

        if (isset($contact_data['name'])) {
            $sanitized['name'] = sanitize_text_field($contact_data['name']);
        }

        // Optional fields
        if (isset($contact_data['email'])) {
            $sanitized['email'] = sanitize_email($contact_data['email']);
        }

        if (isset($contact_data['tags'])) {
            $sanitized['tags'] = sanitize_text_field($contact_data['tags']);
        }

        if (isset($contact_data['notes'])) {
            $sanitized['notes'] = sanitize_textarea_field($contact_data['notes']);
        }

        if (isset($contact_data['status'])) {
            $allowed_statuses = array('active', 'inactive', 'blocked');
            $sanitized['status'] = in_array($contact_data['status'], $allowed_statuses) ?
                $contact_data['status'] : 'active';
        }

        if (isset($contact_data['opt_in_status'])) {
            $allowed_opt_statuses = array('opted_in', 'opted_out', 'pending');
            $sanitized['opt_in_status'] = in_array($contact_data['opt_in_status'], $allowed_opt_statuses) ?
                $contact_data['opt_in_status'] : 'pending';
        }

        return $sanitized;
    }

    /**
     * Get contact statistics
     *
     * @since 1.0.0
     * @return array Contact statistics
     */
    public static function get_contact_stats()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'chatshop_contacts';

        $stats = array(
            'total' => 0,
            'active' => 0,
            'opted_in' => 0,
            'recent' => 0
        );

        // Get total contacts
        $stats['total'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");

        // Get active contacts
        $stats['active'] = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table_name} WHERE status = 'active'"
        );

        // Get opted-in contacts
        $stats['opted_in'] = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table_name} WHERE opt_in_status = 'opted_in'"
        );

        // Get contacts added in last 30 days
        $stats['recent'] = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} WHERE created_at >= %s",
                date('Y-m-d H:i:s', strtotime('-30 days'))
            )
        );

        return $stats;
    }

    /**
     * Export contacts to CSV
     *
     * @since 1.0.0
     * @param array $filters Export filters
     * @return string|false CSV content or false on error
     */
    public static function export_contacts_csv($filters = array())
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'chatshop_contacts';

        // Build query with filters
        $where_clauses = array('1=1');
        $params = array();

        if (!empty($filters['status'])) {
            $where_clauses[] = 'status = %s';
            $params[] = $filters['status'];
        }

        if (!empty($filters['opt_in_status'])) {
            $where_clauses[] = 'opt_in_status = %s';
            $params[] = $filters['opt_in_status'];
        }

        if (!empty($filters['date_from'])) {
            $where_clauses[] = 'DATE(created_at) >= %s';
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where_clauses[] = 'DATE(created_at) <= %s';
            $params[] = $filters['date_to'];
        }

        $where_clause = implode(' AND ', $where_clauses);
        $query = "SELECT * FROM {$table_name} WHERE {$where_clause} ORDER BY created_at DESC";

        if (!empty($params)) {
            $query = $wpdb->prepare($query, $params);
        }

        $contacts = $wpdb->get_results($query, ARRAY_A);

        if (empty($contacts)) {
            return false;
        }

        // Generate CSV
        $csv_output = '';
        $headers = array(
            'ID',
            'Name',
            'Phone',
            'Email',
            'Status',
            'Opt-in Status',
            'Tags',
            'Notes',
            'Created Date',
            'Last Contacted'
        );

        $csv_output .= implode(',', $headers) . "\n";

        foreach ($contacts as $contact) {
            $row = array(
                $contact['id'],
                '"' . str_replace('"', '""', $contact['name']) . '"',
                $contact['phone'],
                $contact['email'],
                $contact['status'],
                $contact['opt_in_status'],
                '"' . str_replace('"', '""', $contact['tags']) . '"',
                '"' . str_replace('"', '""', $contact['notes']) . '"',
                $contact['created_at'],
                $contact['last_contacted'] ?? ''
            );

            $csv_output .= implode(',', $row) . "\n";
        }

        return $csv_output;
    }

    /**
     * Import contacts from CSV
     *
     * @since 1.0.0
     * @param string $csv_content CSV content
     * @return array Import result
     */
    public static function import_contacts_csv($csv_content)
    {
        $lines = explode("\n", $csv_content);
        $headers = str_getcsv(array_shift($lines));

        $imported = 0;
        $skipped = 0;
        $errors = array();

        foreach ($lines as $line_num => $line) {
            if (empty(trim($line))) continue;

            $data = str_getcsv($line);

            if (count($data) !== count($headers)) {
                $errors[] = sprintf(__('Line %d: Invalid number of columns', 'chatshop'), $line_num + 2);
                $skipped++;
                continue;
            }

            $contact_data = array_combine($headers, $data);

            // Validate required fields
            if (empty($contact_data['Name']) || empty($contact_data['Phone'])) {
                $errors[] = sprintf(__('Line %d: Missing required fields (Name, Phone)', 'chatshop'), $line_num + 2);
                $skipped++;
                continue;
            }

            // Sanitize and prepare data
            $sanitized_data = array(
                'name' => sanitize_text_field($contact_data['Name']),
                'phone' => chatshop_validate_phone($contact_data['Phone']),
                'email' => sanitize_email($contact_data['Email'] ?? ''),
                'tags' => sanitize_text_field($contact_data['Tags'] ?? ''),
                'notes' => sanitize_textarea_field($contact_data['Notes'] ?? ''),
                'status' => 'active',
                'opt_in_status' => 'pending'
            );

            if (!$sanitized_data['phone']) {
                $errors[] = sprintf(__('Line %d: Invalid phone number format', 'chatshop'), $line_num + 2);
                $skipped++;
                continue;
            }

            // Check if contact already exists
            global $wpdb;
            $table_name = $wpdb->prefix . 'chatshop_contacts';
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table_name} WHERE phone = %s",
                $sanitized_data['phone']
            ));

            if ($existing) {
                $skipped++;
                continue;
            }

            // Insert contact
            $result = $wpdb->insert($table_name, array_merge($sanitized_data, array(
                'created_by' => get_current_user_id(),
                'created_at' => current_time('mysql')
            )));

            if ($result) {
                $imported++;
            } else {
                $errors[] = sprintf(__('Line %d: Database error', 'chatshop'), $line_num + 2);
                $skipped++;
            }
        }

        return array(
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors
        );
    }
}
