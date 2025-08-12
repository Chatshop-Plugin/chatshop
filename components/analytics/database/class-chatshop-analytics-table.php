<?php

/**
 * Analytics Database Tables Class
 *
 * File: components/analytics/database/class-chatshop-analytics-table.php
 * 
 * Handles database table creation and management for analytics data.
 * Creates optimized tables for analytics metrics and conversion tracking.
 *
 * @package ChatShop
 * @subpackage Components\Analytics\Database
 * @since 1.0.0
 */

namespace ChatShop;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ChatShop Analytics Table Class
 *
 * Manages database schema for analytics and conversion tracking.
 * Handles table creation, updates, and optimization.
 *
 * @since 1.0.0
 */
class ChatShop_Analytics_Table
{
    /**
     * Analytics table name
     *
     * @var string
     * @since 1.0.0
     */
    private $analytics_table;

    /**
     * Conversion table name
     *
     * @var string
     * @since 1.0.0
     */
    private $conversion_table;

    /**
     * Database version option name
     *
     * @var string
     * @since 1.0.0
     */
    private $version_option = 'chatshop_analytics_db_version';

    /**
     * Current database version
     *
     * @var string
     * @since 1.0.0
     */
    private $current_version = '1.0.0';

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        global $wpdb;

        $this->analytics_table = $wpdb->prefix . 'chatshop_analytics';
        $this->conversion_table = $wpdb->prefix . 'chatshop_conversions';
    }

    /**
     * Create or update analytics tables
     *
     * @since 1.0.0
     */
    public function create_tables()
    {
        $installed_version = get_option($this->version_option, '0.0.0');

        if (version_compare($installed_version, $this->current_version, '<')) {
            $this->create_analytics_table();
            $this->create_conversion_table();
            $this->create_indexes();

            update_option($this->version_option, $this->current_version);

            chatshop_log('Analytics database tables created/updated successfully', 'info');
        }
    }

    /**
     * Create analytics table
     *
     * @since 1.0.0
     */
    private function create_analytics_table()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->analytics_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            metric_type varchar(50) NOT NULL,
            metric_name varchar(100) NOT NULL,
            metric_value decimal(15,4) NOT NULL DEFAULT 0,
            metric_date date NOT NULL,
            source_type varchar(50) DEFAULT NULL,
            source_id varchar(100) DEFAULT NULL,
            contact_id bigint(20) UNSIGNED DEFAULT NULL,
            payment_id varchar(100) DEFAULT NULL,
            gateway varchar(50) DEFAULT NULL,
            revenue decimal(15,2) NOT NULL DEFAULT 0,
            currency varchar(10) DEFAULT 'NGN',
            meta_data longtext DEFAULT NULL,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_metric_type_name (metric_type, metric_name),
            KEY idx_metric_date (metric_date),
            KEY idx_source_type (source_type),
            KEY idx_contact_id (contact_id),
            KEY idx_payment_id (payment_id),
            KEY idx_gateway (gateway),
            KEY idx_created_at (created_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Create conversion table
     *
     * @since 1.0.0
     */
    private function create_conversion_table()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->conversion_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            payment_id varchar(100) NOT NULL,
            contact_id bigint(20) UNSIGNED DEFAULT NULL,
            source_type varchar(50) NOT NULL DEFAULT 'direct',
            source_id varchar(100) DEFAULT NULL,
            conversion_value decimal(15,2) NOT NULL DEFAULT 0,
            currency varchar(10) NOT NULL DEFAULT 'NGN',
            gateway varchar(50) NOT NULL,
            conversion_date timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            customer_journey longtext DEFAULT NULL,
            attribution_data longtext DEFAULT NULL,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_payment_id (payment_id),
            KEY idx_contact_id (contact_id),
            KEY idx_source_type (source_type),
            KEY idx_gateway (gateway),
            KEY idx_conversion_date (conversion_date),
            KEY idx_conversion_value (conversion_value),
            KEY idx_created_at (created_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Create additional indexes for performance
     *
     * @since 1.0.0
     */
    private function create_indexes()
    {
        global $wpdb;

        // Composite indexes for common queries
        $wpdb->query("CREATE INDEX IF NOT EXISTS idx_analytics_date_type ON {$this->analytics_table} (metric_date, metric_type)");
        $wpdb->query("CREATE INDEX IF NOT EXISTS idx_analytics_source_date ON {$this->analytics_table} (source_type, metric_date)");
        $wpdb->query("CREATE INDEX IF NOT EXISTS idx_analytics_contact_date ON {$this->analytics_table} (contact_id, metric_date)");

        $wpdb->query("CREATE INDEX IF NOT EXISTS idx_conversion_source_date ON {$this->conversion_table} (source_type, conversion_date)");
        $wpdb->query("CREATE INDEX IF NOT EXISTS idx_conversion_gateway_date ON {$this->conversion_table} (gateway, conversion_date)");
    }

    /**
     * Drop analytics tables
     *
     * @since 1.0.0
     */
    public function drop_tables()
    {
        global $wpdb;

        $wpdb->query("DROP TABLE IF EXISTS {$this->analytics_table}");
        $wpdb->query("DROP TABLE IF EXISTS {$this->conversion_table}");

        delete_option($this->version_option);

        chatshop_log('Analytics database tables dropped', 'info');
    }

    /**
     * Clean old analytics data
     *
     * @since 1.0.0
     * @param int $days_to_keep Number of days to keep data
     */
    public function cleanup_old_data($days_to_keep = 365)
    {
        if (!chatshop_is_premium()) {
            return;
        }

        global $wpdb;

        $cutoff_date = date('Y-m-d', strtotime("-{$days_to_keep} days"));

        // Clean analytics data
        $analytics_deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->analytics_table} WHERE metric_date < %s",
                $cutoff_date
            )
        );

        // Clean conversion data (keep longer for historical analysis)
        $conversion_cutoff = date('Y-m-d', strtotime('-2 years'));
        $conversion_deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->conversion_table} WHERE DATE(conversion_date) < %s",
                $conversion_cutoff
            )
        );

        chatshop_log("Analytics cleanup: {$analytics_deleted} analytics records, {$conversion_deleted} conversion records deleted", 'info');
    }

    /**
     * Get table statistics
     *
     * @since 1.0.0
     * @return array Table statistics
     */
    public function get_table_stats()
    {
        global $wpdb;

        $analytics_count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->analytics_table}");
        $conversion_count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->conversion_table}");

        $analytics_size = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT ROUND(((data_length + index_length) / 1024 / 1024), 2) as size_mb 
                 FROM information_schema.TABLES 
                 WHERE table_schema = %s AND table_name = %s",
                DB_NAME,
                $this->analytics_table
            )
        );

        $conversion_size = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT ROUND(((data_length + index_length) / 1024 / 1024), 2) as size_mb 
                 FROM information_schema.TABLES 
                 WHERE table_schema = %s AND table_name = %s",
                DB_NAME,
                $this->conversion_table
            )
        );

        return array(
            'analytics' => array(
                'table_name' => $this->analytics_table,
                'row_count' => intval($analytics_count),
                'size_mb' => floatval($analytics_size)
            ),
            'conversions' => array(
                'table_name' => $this->conversion_table,
                'row_count' => intval($conversion_count),
                'size_mb' => floatval($conversion_size)
            ),
            'total_size_mb' => floatval($analytics_size) + floatval($conversion_size)
        );
    }

    /**
     * Optimize tables
     *
     * @since 1.0.0
     */
    public function optimize_tables()
    {
        global $wpdb;

        $wpdb->query("OPTIMIZE TABLE {$this->analytics_table}");
        $wpdb->query("OPTIMIZE TABLE {$this->conversion_table}");

        chatshop_log('Analytics database tables optimized', 'info');
    }

    /**
     * Check if tables exist
     *
     * @since 1.0.0
     * @return bool True if tables exist
     */
    public function tables_exist()
    {
        global $wpdb;

        $analytics_exists = $wpdb->get_var(
            $wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $this->analytics_table
            )
        );

        $conversion_exists = $wpdb->get_var(
            $wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $this->conversion_table
            )
        );

        return !empty($analytics_exists) && !empty($conversion_exists);
    }

    /**
     * Get analytics table name
     *
     * @since 1.0.0
     * @return string Table name
     */
    public function get_analytics_table_name()
    {
        return $this->analytics_table;
    }

    /**
     * Get conversion table name
     *
     * @since 1.0.0
     * @return string Table name
     */
    public function get_conversion_table_name()
    {
        return $this->conversion_table;
    }
}
