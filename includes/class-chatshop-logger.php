<?php

/**
 * ChatShop Logger Class
 *
 * Simple logging utility for the ChatShop plugin.
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
     * Log levels
     *
     * @var array
     * @since 1.0.0
     */
    const LEVELS = array(
        'debug' => 0,
        'info' => 1,
        'warning' => 2,
        'error' => 3,
        'critical' => 4
    );

    /**
     * Log file path
     *
     * @var string
     * @since 1.0.0
     */
    private static $log_file;

    /**
     * Maximum log file size in bytes
     *
     * @var int
     * @since 1.0.0
     */
    private static $max_file_size = 5242880; // 5MB

    /**
     * Initialize logger
     *
     * @since 1.0.0
     */
    public static function init()
    {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/chatshop-logs';

        // Create log directory if it doesn't exist
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
            // Add .htaccess to protect log files
            file_put_contents($log_dir . '/.htaccess', 'Deny from all');
        }

        self::$log_file = $log_dir . '/chatshop.log';
    }

    /**
     * Log a message
     *
     * @since 1.0.0
     * @param string $message Log message
     * @param string $level Log level (debug, info, warning, error, critical)
     * @param array $context Additional context
     */
    public static function log($message, $level = 'info', $context = array())
    {
        // Only log if WP_DEBUG is enabled or level is warning or higher
        if (!WP_DEBUG && self::LEVELS[$level] < self::LEVELS['warning']) {
            return;
        }

        // Initialize if not done
        if (empty(self::$log_file)) {
            self::init();
        }

        // Rotate log if it's too large
        self::rotate_log_if_needed();

        // Format message
        $formatted_message = self::format_message($message, $level, $context);

        // Write to file
        error_log($formatted_message, 3, self::$log_file);

        // Also log to WordPress debug.log if enabled
        if (WP_DEBUG_LOG) {
            error_log("ChatShop [{$level}]: {$message}");
        }
    }

    /**
     * Log debug message
     *
     * @since 1.0.0
     * @param string $message Log message
     * @param array $context Additional context
     */
    public static function debug($message, $context = array())
    {
        self::log($message, 'debug', $context);
    }

    /**
     * Log info message
     *
     * @since 1.0.0
     * @param string $message Log message
     * @param array $context Additional context
     */
    public static function info($message, $context = array())
    {
        self::log($message, 'info', $context);
    }

    /**
     * Log warning message
     *
     * @since 1.0.0
     * @param string $message Log message
     * @param array $context Additional context
     */
    public static function warning($message, $context = array())
    {
        self::log($message, 'warning', $context);
    }

    /**
     * Log error message
     *
     * @since 1.0.0
     * @param string $message Log message
     * @param array $context Additional context
     */
    public static function error($message, $context = array())
    {
        self::log($message, 'error', $context);
    }

    /**
     * Log critical message
     *
     * @since 1.0.0
     * @param string $message Log message
     * @param array $context Additional context
     */
    public static function critical($message, $context = array())
    {
        self::log($message, 'critical', $context);
    }

    /**
     * Format log message
     *
     * @since 1.0.0
     * @param string $message Log message
     * @param string $level Log level
     * @param array $context Additional context
     * @return string Formatted message
     */
    private static function format_message($message, $level, $context)
    {
        $timestamp = current_time('Y-m-d H:i:s');
        $level = strtoupper($level);

        $formatted = "[{$timestamp}] {$level}: {$message}";

        // Add context if provided
        if (!empty($context)) {
            $formatted .= ' | Context: ' . wp_json_encode($context);
        }

        return $formatted . PHP_EOL;
    }

    /**
     * Rotate log file if it exceeds size limit
     *
     * @since 1.0.0
     */
    private static function rotate_log_if_needed()
    {
        if (!file_exists(self::$log_file)) {
            return;
        }

        if (filesize(self::$log_file) > self::$max_file_size) {
            $backup_file = self::$log_file . '.backup';

            // Remove old backup
            if (file_exists($backup_file)) {
                unlink($backup_file);
            }

            // Move current log to backup
            rename(self::$log_file, $backup_file);
        }
    }

    /**
     * Get log file contents
     *
     * @since 1.0.0
     * @param int $lines Number of lines to retrieve (0 for all)
     * @return string Log contents
     */
    public static function get_log_contents($lines = 100)
    {
        if (!file_exists(self::$log_file)) {
            return '';
        }

        if ($lines === 0) {
            return file_get_contents(self::$log_file);
        }

        // Get last N lines
        $file = file(self::$log_file);
        $total_lines = count($file);
        $start = max(0, $total_lines - $lines);

        return implode('', array_slice($file, $start));
    }

    /**
     * Clear log file
     *
     * @since 1.0.0
     * @return bool True on success, false on failure
     */
    public static function clear_log()
    {
        if (file_exists(self::$log_file)) {
            return unlink(self::$log_file);
        }

        return true;
    }

    /**
     * Get log file path
     *
     * @since 1.0.0
     * @return string Log file path
     */
    public static function get_log_file_path()
    {
        if (empty(self::$log_file)) {
            self::init();
        }

        return self::$log_file;
    }

    /**
     * Get log file URL (if accessible)
     *
     * @since 1.0.0
     * @return string|false Log file URL or false if not accessible
     */
    public static function get_log_file_url()
    {
        $upload_dir = wp_upload_dir();
        $log_dir_url = $upload_dir['baseurl'] . '/chatshop-logs';

        return $log_dir_url . '/chatshop.log';
    }

    /**
     * Check if logging is enabled
     *
     * @since 1.0.0
     * @return bool True if enabled, false otherwise
     */
    public static function is_logging_enabled()
    {
        return WP_DEBUG || get_option('chatshop_enable_logging', false);
    }

    /**
     * Get log statistics
     *
     * @since 1.0.0
     * @return array Log statistics
     */
    public static function get_log_stats()
    {
        $stats = array(
            'file_exists' => false,
            'file_size' => 0,
            'line_count' => 0,
            'last_modified' => null
        );

        if (file_exists(self::$log_file)) {
            $stats['file_exists'] = true;
            $stats['file_size'] = filesize(self::$log_file);
            $stats['line_count'] = count(file(self::$log_file));
            $stats['last_modified'] = filemtime(self::$log_file);
        }

        return $stats;
    }
}

/**
 * Convenience function for logging
 *
 * @since 1.0.0
 * @param string $message Log message
 * @param string $level Log level
 * @param array $context Additional context
 */
function chatshop_log($message, $level = 'info', $context = array())
{
    ChatShop_Logger::log($message, $level, $context);
}
