<?php

/**
 * Logger Class
 *
 * Handles logging functionality for the ChatShop plugin.
 *
 * @package ChatShop
 * @since 1.0.0
 */

namespace ChatShop;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ChatShop Logger Class
 *
 * @since 1.0.0
 */
class ChatShop_Logger
{
    /**
     * Log levels mapping
     *
     * @var array
     * @since 1.0.0
     */
    private static $log_levels = array(
        'emergency' => 0,
        'alert'     => 1,
        'critical'  => 2,
        'error'     => 3,
        'warning'   => 4,
        'notice'    => 5,
        'info'      => 6,
        'debug'     => 7
    );

    /**
     * Current log level
     *
     * @var int
     * @since 1.0.0
     */
    private static $current_level = 6; // INFO level by default

    /**
     * Log directory
     *
     * @var string
     * @since 1.0.0
     */
    private static $log_dir;

    /**
     * File logging enabled
     *
     * @var bool
     * @since 1.0.0
     */
    private static $file_logging = true;

    /**
     * Database logging enabled
     *
     * @var bool
     * @since 1.0.0
     */
    private static $db_logging = false;

    /**
     * Maximum file size in bytes (10MB)
     *
     * @var int
     * @since 1.0.0
     */
    private static $max_file_size = 10485760;

    /**
     * Maximum number of log files to keep
     *
     * @var int
     * @since 1.0.0
     */
    private static $max_files = 5;

    /**
     * Initialize logger
     *
     * @since 1.0.0
     */
    public static function init()
    {
        // Set log directory
        self::$log_dir = WP_CONTENT_DIR . '/chatshop-logs/';

        // Load settings
        $options = chatshop_get_option('general', '', array());
        self::$current_level = isset($options['log_level']) ? intval($options['log_level']) : 6;
        self::$file_logging = isset($options['file_logging']) ? (bool) $options['file_logging'] : true;
        self::$db_logging = isset($options['db_logging']) ? (bool) $options['db_logging'] : false;

        // Create log directory
        self::create_log_directory();

        // Set up cleanup cron
        if (!wp_next_scheduled('chatshop_cleanup_logs')) {
            wp_schedule_event(time(), 'daily', 'chatshop_cleanup_logs');
        }

        add_action('chatshop_cleanup_logs', array(__CLASS__, 'cleanup_old_logs'));
    }

    /**
     * Log a message
     *
     * @since 1.0.0
     * @param string $message Log message
     * @param string $level Log level
     * @param array  $context Additional context
     */
    public static function log($message, $level = 'info', $context = array())
    {
        $level = strtolower($level);

        // Check if we should log this level
        if (!self::should_log($level)) {
            return;
        }

        $log_entry = self::format_log_entry($message, $level, $context);

        // File logging
        if (self::$file_logging) {
            self::write_to_file($log_entry, $level);
        }

        // Database logging
        if (self::$db_logging) {
            self::write_to_database($message, $level, $context);
        }

        // WordPress debug log
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log($log_entry);
        }
    }

    /**
     * Log emergency message
     *
     * @since 1.0.0
     * @param string $message Log message
     * @param array  $context Additional context
     */
    public static function emergency($message, $context = array())
    {
        self::log($message, 'emergency', $context);
    }

    /**
     * Log alert message
     *
     * @since 1.0.0
     * @param string $message Log message
     * @param array  $context Additional context
     */
    public static function alert($message, $context = array())
    {
        self::log($message, 'alert', $context);
    }

    /**
     * Log critical message
     *
     * @since 1.0.0
     * @param string $message Log message
     * @param array  $context Additional context
     */
    public static function critical($message, $context = array())
    {
        self::log($message, 'critical', $context);
    }

    /**
     * Log error message
     *
     * @since 1.0.0
     * @param string $message Log message
     * @param array  $context Additional context
     */
    public static function error($message, $context = array())
    {
        self::log($message, 'error', $context);
    }

    /**
     * Log warning message
     *
     * @since 1.0.0
     * @param string $message Log message
     * @param array  $context Additional context
     */
    public static function warning($message, $context = array())
    {
        self::log($message, 'warning', $context);
    }

    /**
     * Log notice message
     *
     * @since 1.0.0
     * @param string $message Log message
     * @param array  $context Additional context
     */
    public static function notice($message, $context = array())
    {
        self::log($message, 'notice', $context);
    }

    /**
     * Log info message
     *
     * @since 1.0.0
     * @param string $message Log message
     * @param array  $context Additional context
     */
    public static function info($message, $context = array())
    {
        self::log($message, 'info', $context);
    }

    /**
     * Log debug message
     *
     * @since 1.0.0
     * @param string $message Log message
     * @param array  $context Additional context
     */
    public static function debug($message, $context = array())
    {
        self::log($message, 'debug', $context);
    }

    /**
     * Check if we should log this level
     *
     * @since 1.0.0
     * @param string $level Log level
     * @return bool Whether to log
     */
    private static function should_log($level)
    {
        if (!isset(self::$log_levels[$level])) {
            return false;
        }

        return self::$log_levels[$level] <= self::$current_level;
    }

    /**
     * Format log entry
     *
     * @since 1.0.0
     * @param string $message Log message
     * @param string $level Log level
     * @param array  $context Additional context
     * @return string Formatted log entry
     */
    private static function format_log_entry($message, $level, $context)
    {
        $timestamp = current_time('Y-m-d H:i:s');
        $level_upper = strtoupper($level);

        $entry = "[{$timestamp}] ChatShop.{$level_upper}: {$message}";

        // Add context if provided
        if (!empty($context)) {
            $entry .= ' ' . wp_json_encode($context);
        }

        // Add memory usage for debug level
        if ($level === 'debug') {
            $memory = number_format(memory_get_usage(true) / 1024 / 1024, 2);
            $entry .= " [Memory: {$memory}MB]";
        }

        return $entry;
    }

    /**
     * Write log entry to file
     *
     * @since 1.0.0
     * @param string $log_entry Formatted log entry
     * @param string $level Log level
     */
    private static function write_to_file($log_entry, $level)
    {
        $log_file = self::get_log_file($level);

        // Check if file rotation is needed
        if (file_exists($log_file) && filesize($log_file) > self::$max_file_size) {
            self::rotate_log_file($log_file);
        }

        // Write to file
        $result = file_put_contents($log_file, $log_entry . PHP_EOL, FILE_APPEND | LOCK_EX);

        if ($result === false) {
            error_log('ChatShop: Failed to write to log file: ' . $log_file);
        }
    }

    /**
     * Write log entry to database
     *
     * @since 1.0.0
     * @param string $message Log message
     * @param string $level Log level
     * @param array  $context Additional context
     */
    private static function write_to_database($message, $level, $context)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'chatshop_logs';

        $data = array(
            'level' => $level,
            'message' => $message,
            'context' => wp_json_encode($context),
            'user_id' => get_current_user_id(),
            'ip_address' => self::get_client_ip(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '',
            'created_at' => current_time('mysql')
        );

        $wpdb->insert($table_name, $data);
    }

    /**
     * Get log file path
     *
     * @since 1.0.0
     * @param string $level Log level
     * @return string Log file path
     */
    private static function get_log_file($level)
    {
        $date = current_time('Y-m-d');
        return self::$log_dir . "chatshop-{$level}-{$date}.log";
    }

    /**
     * Create log directory
     *
     * @since 1.0.0
     */
    private static function create_log_directory()
    {
        if (!file_exists(self::$log_dir)) {
            wp_mkdir_p(self::$log_dir);

            // Create .htaccess to protect log files
            $htaccess_content = "Order deny,allow\nDeny from all\n";
            file_put_contents(self::$log_dir . '.htaccess', $htaccess_content);

            // Create index.php to prevent directory listing
            file_put_contents(self::$log_dir . 'index.php', '<?php // Silence is golden');
        }
    }

    /**
     * Rotate log file
     *
     * @since 1.0.0
     * @param string $log_file Log file path
     */
    private static function rotate_log_file($log_file)
    {
        $base_name = pathinfo($log_file, PATHINFO_FILENAME);
        $extension = pathinfo($log_file, PATHINFO_EXTENSION);
        $directory = dirname($log_file);

        // Move existing numbered files
        for ($i = self::$max_files - 1; $i >= 1; $i--) {
            $old_file = "{$directory}/{$base_name}.{$i}.{$extension}";
            $new_file = "{$directory}/{$base_name}." . ($i + 1) . ".{$extension}";

            if (file_exists($old_file)) {
                if ($i + 1 > self::$max_files) {
                    unlink($old_file); // Delete oldest file
                } else {
                    rename($old_file, $new_file);
                }
            }
        }

        // Move current file to .1
        $rotated_file = "{$directory}/{$base_name}.1.{$extension}";
        rename($log_file, $rotated_file);
    }

    /**
     * Cleanup old log files
     *
     * @since 1.0.0
     */
    public static function cleanup_old_logs()
    {
        if (!is_dir(self::$log_dir)) {
            return;
        }

        $files = glob(self::$log_dir . '*.log*');
        $cutoff_time = time() - (30 * DAY_IN_SECONDS); // 30 days

        foreach ($files as $file) {
            if (filemtime($file) < $cutoff_time) {
                unlink($file);
            }
        }

        // Cleanup database logs older than 30 days
        if (self::$db_logging) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'chatshop_logs';
            $cutoff_date = date('Y-m-d H:i:s', $cutoff_time);

            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$table_name} WHERE created_at < %s",
                $cutoff_date
            ));
        }
    }

    /**
     * Get recent logs
     *
     * @since 1.0.0
     * @param int    $limit Number of logs to retrieve
     * @param string $level Specific level to filter (optional)
     * @return array Recent logs
     */
    public static function get_recent_logs($limit = 100, $level = '')
    {
        if (!self::$db_logging) {
            return array();
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'chatshop_logs';

        $where_clause = '';
        $prepare_values = array();

        if (!empty($level)) {
            $where_clause = 'WHERE level = %s';
            $prepare_values[] = $level;
        }

        $prepare_values[] = intval($limit);

        $query = "SELECT * FROM {$table_name} {$where_clause} ORDER BY created_at DESC LIMIT %d";

        if (!empty($prepare_values)) {
            $results = $wpdb->get_results($wpdb->prepare($query, $prepare_values));
        } else {
            $results = $wpdb->get_results($query);
        }

        return $results ? $results : array();
    }

    /**
     * Clear all logs
     *
     * @since 1.0.0
     * @param bool $files Clear log files
     * @param bool $database Clear database logs
     * @return bool Success status
     */
    public static function clear_logs($files = true, $database = true)
    {
        $success = true;

        // Clear log files
        if ($files && self::$file_logging) {
            $log_files = glob(self::$log_dir . '*.log*');
            foreach ($log_files as $file) {
                if (!unlink($file)) {
                    $success = false;
                }
            }
        }

        // Clear database logs
        if ($database && self::$db_logging) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'chatshop_logs';

            $result = $wpdb->query("TRUNCATE TABLE {$table_name}");
            if ($result === false) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Get client IP address
     *
     * @since 1.0.0
     * @return string Client IP
     */
    private static function get_client_ip()
    {
        $ip_headers = array(
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );

        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = sanitize_text_field($_SERVER[$header]);

                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }

                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return '';
    }

    /**
     * Set log level
     *
     * @since 1.0.0
     * @param string $level Log level
     * @return bool Update result
     */
    public static function set_log_level($level)
    {
        if (!isset(self::$log_levels[$level])) {
            return false;
        }

        self::$current_level = self::$log_levels[$level];

        // Update settings
        $options = chatshop_get_option('general', '', array());
        $options['log_level'] = self::$current_level;

        return chatshop_update_option('general', '', $options);
    }

    /**
     * Enable file logging
     *
     * @since 1.0.0
     * @return bool Update result
     */
    public static function enable_file_logging()
    {
        self::$file_logging = true;
        self::create_log_directory();

        $options = chatshop_get_option('general', '', array());
        $options['file_logging'] = true;

        return chatshop_update_option('general', '', $options);
    }

    /**
     * Disable file logging
     *
     * @since 1.0.0
     * @return bool Update result
     */
    public static function disable_file_logging()
    {
        self::$file_logging = false;

        $options = chatshop_get_option('general', '', array());
        $options['file_logging'] = false;

        return chatshop_update_option('general', '', $options);
    }

    /**
     * Enable database logging
     *
     * @since 1.0.0
     * @return bool Update result
     */
    public static function enable_database_logging()
    {
        self::$db_logging = true;

        $options = chatshop_get_option('general', '', array());
        $options['db_logging'] = true;

        return chatshop_update_option('general', '', $options);
    }

    /**
     * Disable database logging
     *
     * @since 1.0.0
     * @return bool Update result
     */
    public static function disable_database_logging()
    {
        self::$db_logging = false;

        $options = chatshop_get_option('general', '', array());
        $options['db_logging'] = false;

        return chatshop_update_option('general', '', $options);
    }

    /**
     * Get log levels
     *
     * @since 1.0.0
     * @return array Log levels
     */
    public static function get_log_levels()
    {
        return self::$log_levels;
    }

    /**
     * Get current log level
     *
     * @since 1.0.0
     * @return int Current log level
     */
    public static function get_current_level()
    {
        return self::$current_level;
    }

    /**
     * Check if file logging is enabled
     *
     * @since 1.0.0
     * @return bool File logging status
     */
    public static function is_file_logging_enabled()
    {
        return self::$file_logging;
    }

    /**
     * Check if database logging is enabled
     *
     * @since 1.0.0
     * @return bool Database logging status
     */
    public static function is_database_logging_enabled()
    {
        return self::$db_logging;
    }

    /**
     * Get log directory
     *
     * @since 1.0.0
     * @return string Log directory path
     */
    public static function get_log_directory()
    {
        return self::$log_dir;
    }
}
