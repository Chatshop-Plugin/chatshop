<?php

/**
 * ChatShop Rate Limiter
 *
 * Handles API rate limiting for WhatsApp messaging
 *
 * @package ChatShop
 * @subpackage Core
 * @since 1.0.0
 */

namespace ChatShop\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Rate Limiter class
 *
 * Implements rate limiting for WhatsApp API calls and message sending
 */
class ChatShop_Rate_Limiter
{

    /**
     * Rate limit configurations
     *
     * @var array
     */
    private $rate_limits = [
        'messaging' => [
            'per_contact_per_hour' => 10,
            'per_contact_per_day' => 100,
            'global_per_minute' => 80,
            'global_per_hour' => 1000
        ],
        'api_calls' => [
            'per_minute' => 240,
            'per_hour' => 10000
        ],
        'media_upload' => [
            'per_hour' => 100,
            'per_day' => 1000
        ]
    ];

    /**
     * Cache expiry times in seconds
     *
     * @var array
     */
    private $cache_expiry = [
        'minute' => 60,
        'hour' => 3600,
        'day' => 86400
    ];

    /**
     * Constructor
     */
    public function __construct()
    {
        // Load custom rate limits from options
        $custom_limits = get_option('chatshop_rate_limits', []);
        if (!empty($custom_limits)) {
            $this->rate_limits = array_merge($this->rate_limits, $custom_limits);
        }
    }

    /**
     * Check if message can be sent to contact
     *
     * @param string $phone_number Phone number
     * @return bool True if can send
     */
    public function can_send($phone_number)
    {
        // Check per-contact hourly limit
        if (!$this->check_contact_hourly_limit($phone_number)) {
            return false;
        }

        // Check per-contact daily limit
        if (!$this->check_contact_daily_limit($phone_number)) {
            return false;
        }

        // Check global minute limit
        if (!$this->check_global_minute_limit()) {
            return false;
        }

        // Check global hourly limit
        if (!$this->check_global_hourly_limit()) {
            return false;
        }

        return true;
    }

    /**
     * Record message sent
     *
     * @param string $phone_number Phone number
     * @return bool Success status
     */
    public function record_send($phone_number)
    {
        $current_time = time();

        // Record per-contact sends
        $this->increment_counter("contact_hour_{$phone_number}", $this->cache_expiry['hour']);
        $this->increment_counter("contact_day_{$phone_number}", $this->cache_expiry['day']);

        // Record global sends
        $this->increment_counter('global_minute', $this->cache_expiry['minute']);
        $this->increment_counter('global_hour', $this->cache_expiry['hour']);

        // Store last send time
        $this->set_cache_value("last_send_{$phone_number}", $current_time, $this->cache_expiry['hour']);

        return true;
    }

    /**
     * Check if API call can be made
     *
     * @param string $endpoint Optional specific endpoint
     * @return bool True if can make call
     */
    public function can_make_api_call($endpoint = 'general')
    {
        // Check general API limits
        if (!$this->check_api_minute_limit()) {
            return false;
        }

        if (!$this->check_api_hourly_limit()) {
            return false;
        }

        // Check endpoint-specific limits if applicable
        if ($endpoint === 'media' && !$this->check_media_upload_limits()) {
            return false;
        }

        return true;
    }

    /**
     * Record API call
     *
     * @param string $endpoint Optional specific endpoint
     * @return bool Success status
     */
    public function record_api_call($endpoint = 'general')
    {
        // Record general API calls
        $this->increment_counter('api_minute', $this->cache_expiry['minute']);
        $this->increment_counter('api_hour', $this->cache_expiry['hour']);

        // Record endpoint-specific calls
        if ($endpoint === 'media') {
            $this->increment_counter('media_hour', $this->cache_expiry['hour']);
            $this->increment_counter('media_day', $this->cache_expiry['day']);
        }

        return true;
    }

    /**
     * Get remaining quota for contact
     *
     * @param string $phone_number Phone number
     * @return array Remaining quotas
     */
    public function get_contact_quota($phone_number)
    {
        $hourly_count = $this->get_counter_value("contact_hour_{$phone_number}");
        $daily_count = $this->get_counter_value("contact_day_{$phone_number}");

        return [
            'hourly_remaining' => max(0, $this->rate_limits['messaging']['per_contact_per_hour'] - $hourly_count),
            'daily_remaining' => max(0, $this->rate_limits['messaging']['per_contact_per_day'] - $daily_count),
            'hourly_used' => $hourly_count,
            'daily_used' => $daily_count
        ];
    }

    /**
     * Get global messaging quota
     *
     * @return array Global quotas
     */
    public function get_global_quota()
    {
        $minute_count = $this->get_counter_value('global_minute');
        $hour_count = $this->get_counter_value('global_hour');

        return [
            'minute_remaining' => max(0, $this->rate_limits['messaging']['global_per_minute'] - $minute_count),
            'hourly_remaining' => max(0, $this->rate_limits['messaging']['global_per_hour'] - $hour_count),
            'minute_used' => $minute_count,
            'hourly_used' => $hour_count
        ];
    }

    /**
     * Get API quota
     *
     * @return array API quotas
     */
    public function get_api_quota()
    {
        $minute_count = $this->get_counter_value('api_minute');
        $hour_count = $this->get_counter_value('api_hour');

        return [
            'minute_remaining' => max(0, $this->rate_limits['api_calls']['per_minute'] - $minute_count),
            'hourly_remaining' => max(0, $this->rate_limits['api_calls']['per_hour'] - $hour_count),
            'minute_used' => $minute_count,
            'hourly_used' => $hour_count
        ];
    }

    /**
     * Calculate delay needed before next send
     *
     * @param string $phone_number Phone number
     * @return int Delay in seconds (0 if can send immediately)
     */
    public function get_send_delay($phone_number)
    {
        $delays = [];

        // Check contact hourly limit
        if (!$this->check_contact_hourly_limit($phone_number)) {
            $last_hour_key = "contact_hour_{$phone_number}";
            $delays[] = $this->get_cache_ttl($last_hour_key);
        }

        // Check global minute limit
        if (!$this->check_global_minute_limit()) {
            $delays[] = $this->get_cache_ttl('global_minute');
        }

        return empty($delays) ? 0 : max($delays);
    }

    /**
     * Reset rate limits for contact (admin function)
     *
     * @param string $phone_number Phone number
     * @return bool Success status
     */
    public function reset_contact_limits($phone_number)
    {
        $this->delete_cache_value("contact_hour_{$phone_number}");
        $this->delete_cache_value("contact_day_{$phone_number}");
        $this->delete_cache_value("last_send_{$phone_number}");

        return true;
    }

    /**
     * Reset global rate limits (admin function)
     *
     * @return bool Success status
     */
    public function reset_global_limits()
    {
        $this->delete_cache_value('global_minute');
        $this->delete_cache_value('global_hour');
        $this->delete_cache_value('api_minute');
        $this->delete_cache_value('api_hour');
        $this->delete_cache_value('media_hour');
        $this->delete_cache_value('media_day');

        return true;
    }

    /**
     * Get rate limit statistics
     *
     * @return array Statistics
     */
    public function get_statistics()
    {
        return [
            'global' => $this->get_global_quota(),
            'api' => $this->get_api_quota(),
            'active_contacts' => $this->count_active_contacts(),
            'top_contacts' => $this->get_top_message_contacts()
        ];
    }

    /**
     * Update rate limit configuration
     *
     * @param array $new_limits New rate limits
     * @return bool Success status
     */
    public function update_rate_limits($new_limits)
    {
        $this->rate_limits = array_merge($this->rate_limits, $new_limits);
        return update_option('chatshop_rate_limits', $this->rate_limits);
    }

    /**
     * Check contact hourly limit
     *
     * @param string $phone_number Phone number
     * @return bool True if within limit
     */
    private function check_contact_hourly_limit($phone_number)
    {
        $count = $this->get_counter_value("contact_hour_{$phone_number}");
        return $count < $this->rate_limits['messaging']['per_contact_per_hour'];
    }

    /**
     * Check contact daily limit
     *
     * @param string $phone_number Phone number
     * @return bool True if within limit
     */
    private function check_contact_daily_limit($phone_number)
    {
        $count = $this->get_counter_value("contact_day_{$phone_number}");
        return $count < $this->rate_limits['messaging']['per_contact_per_day'];
    }

    /**
     * Check global minute limit
     *
     * @return bool True if within limit
     */
    private function check_global_minute_limit()
    {
        $count = $this->get_counter_value('global_minute');
        return $count < $this->rate_limits['messaging']['global_per_minute'];
    }

    /**
     * Check global hourly limit
     *
     * @return bool True if within limit
     */
    private function check_global_hourly_limit()
    {
        $count = $this->get_counter_value('global_hour');
        return $count < $this->rate_limits['messaging']['global_per_hour'];
    }

    /**
     * Check API minute limit
     *
     * @return bool True if within limit
     */
    private function check_api_minute_limit()
    {
        $count = $this->get_counter_value('api_minute');
        return $count < $this->rate_limits['api_calls']['per_minute'];
    }

    /**
     * Check API hourly limit
     *
     * @return bool True if within limit
     */
    private function check_api_hourly_limit()
    {
        $count = $this->get_counter_value('api_hour');
        return $count < $this->rate_limits['api_calls']['per_hour'];
    }

    /**
     * Check media upload limits
     *
     * @return bool True if within limit
     */
    private function check_media_upload_limits()
    {
        $hour_count = $this->get_counter_value('media_hour');
        $day_count = $this->get_counter_value('media_day');

        return $hour_count < $this->rate_limits['media_upload']['per_hour'] &&
            $day_count < $this->rate_limits['media_upload']['per_day'];
    }

    /**
     * Increment counter with expiry
     *
     * @param string $key Cache key
     * @param int    $expiry Expiry time in seconds
     * @return int New counter value
     */
    private function increment_counter($key, $expiry)
    {
        $cache_key = $this->get_cache_key($key);
        $current_value = get_transient($cache_key);

        if ($current_value === false) {
            $new_value = 1;
        } else {
            $new_value = intval($current_value) + 1;
        }

        set_transient($cache_key, $new_value, $expiry);

        return $new_value;
    }

    /**
     * Get counter value
     *
     * @param string $key Cache key
     * @return int Counter value
     */
    private function get_counter_value($key)
    {
        $cache_key = $this->get_cache_key($key);
        $value = get_transient($cache_key);

        return $value === false ? 0 : intval($value);
    }

    /**
     * Set cache value
     *
     * @param string $key Cache key
     * @param mixed  $value Value
     * @param int    $expiry Expiry time in seconds
     * @return bool Success status
     */
    private function set_cache_value($key, $value, $expiry)
    {
        $cache_key = $this->get_cache_key($key);
        return set_transient($cache_key, $value, $expiry);
    }

    /**
     * Get cache value
     *
     * @param string $key Cache key
     * @return mixed Cache value or false if not found
     */
    private function get_cache_value($key)
    {
        $cache_key = $this->get_cache_key($key);
        return get_transient($cache_key);
    }

    /**
     * Delete cache value
     *
     * @param string $key Cache key
     * @return bool Success status
     */
    private function delete_cache_value($key)
    {
        $cache_key = $this->get_cache_key($key);
        return delete_transient($cache_key);
    }

    /**
     * Get cache TTL (time to live)
     *
     * @param string $key Cache key
     * @return int TTL in seconds
     */
    private function get_cache_ttl($key)
    {
        $cache_key = $this->get_cache_key($key);

        // WordPress doesn't provide a direct way to get transient TTL
        // We'll use the timeout option that WordPress creates
        $timeout_key = '_transient_timeout_' . $cache_key;
        $timeout = get_option($timeout_key);

        if ($timeout === false) {
            return 0;
        }

        return max(0, $timeout - time());
    }

    /**
     * Generate cache key with prefix
     *
     * @param string $key Base key
     * @return string Prefixed cache key
     */
    private function get_cache_key($key)
    {
        return 'chatshop_rate_limit_' . md5($key);
    }

    /**
     * Count active contacts (with recent messages)
     *
     * @return int Number of active contacts
     */
    private function count_active_contacts()
    {
        global $wpdb;

        // Count contacts with messages in the last hour
        $hour_ago = date('Y-m-d H:i:s', strtotime('-1 hour'));

        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT phone_number) 
             FROM {$wpdb->prefix}chatshop_messages 
             WHERE created_at >= %s AND direction = 'outgoing'",
            $hour_ago
        ));
    }

    /**
     * Get top message contacts by volume
     *
     * @param int $limit Number of contacts to return
     * @return array Top contacts
     */
    private function get_top_message_contacts($limit = 10)
    {
        global $wpdb;

        // Get contacts with most messages in the last 24 hours
        $day_ago = date('Y-m-d H:i:s', strtotime('-24 hours'));

        return $wpdb->get_results($wpdb->prepare(
            "SELECT phone_number, COUNT(*) as message_count
             FROM {$wpdb->prefix}chatshop_messages 
             WHERE created_at >= %s AND direction = 'outgoing'
             GROUP BY phone_number 
             ORDER BY message_count DESC 
             LIMIT %d",
            $day_ago,
            $limit
        ), ARRAY_A);
    }

    /**
     * Check if contact is in cooldown period
     *
     * @param string $phone_number Phone number
     * @param int    $cooldown_seconds Cooldown period in seconds
     * @return bool True if in cooldown
     */
    public function is_in_cooldown($phone_number, $cooldown_seconds = 60)
    {
        $last_send = $this->get_cache_value("last_send_{$phone_number}");

        if ($last_send === false) {
            return false;
        }

        return (time() - intval($last_send)) < $cooldown_seconds;
    }

    /**
     * Get time until cooldown expires
     *
     * @param string $phone_number Phone number
     * @param int    $cooldown_seconds Cooldown period in seconds
     * @return int Seconds until cooldown expires (0 if not in cooldown)
     */
    public function get_cooldown_remaining($phone_number, $cooldown_seconds = 60)
    {
        $last_send = $this->get_cache_value("last_send_{$phone_number}");

        if ($last_send === false) {
            return 0;
        }

        $elapsed = time() - intval($last_send);
        return max(0, $cooldown_seconds - $elapsed);
    }

    /**
     * Temporarily block contact (emergency brake)
     *
     * @param string $phone_number Phone number
     * @param int    $duration_seconds Block duration in seconds
     * @param string $reason Block reason
     * @return bool Success status
     */
    public function block_contact($phone_number, $duration_seconds = 3600, $reason = '')
    {
        $block_data = [
            'blocked_at' => time(),
            'reason' => sanitize_text_field($reason),
            'expires_at' => time() + $duration_seconds
        ];

        return $this->set_cache_value("blocked_{$phone_number}", $block_data, $duration_seconds);
    }

    /**
     * Check if contact is blocked
     *
     * @param string $phone_number Phone number
     * @return bool True if blocked
     */
    public function is_contact_blocked($phone_number)
    {
        $block_data = $this->get_cache_value("blocked_{$phone_number}");
        return $block_data !== false;
    }

    /**
     * Unblock contact
     *
     * @param string $phone_number Phone number
     * @return bool Success status
     */
    public function unblock_contact($phone_number)
    {
        return $this->delete_cache_value("blocked_{$phone_number}");
    }

    /**
     * Get block info for contact
     *
     * @param string $phone_number Phone number
     * @return array|null Block info or null if not blocked
     */
    public function get_block_info($phone_number)
    {
        return $this->get_cache_value("blocked_{$phone_number}");
    }

    /**
     * Apply exponential backoff for failed sends
     *
     * @param string $phone_number Phone number
     * @param int    $attempt_number Current attempt number
     * @return int Delay in seconds
     */
    public function calculate_backoff_delay($phone_number, $attempt_number)
    {
        // Base delay of 1 second, exponentially increasing
        $base_delay = 1;
        $max_delay = 300; // 5 minutes maximum

        $delay = min($base_delay * pow(2, $attempt_number - 1), $max_delay);

        // Add some jitter to prevent thundering herd
        $jitter = rand(0, intval($delay * 0.1));

        return $delay + $jitter;
    }

    /**
     * Record failed send attempt
     *
     * @param string $phone_number Phone number
     * @param string $error_reason Error reason
     * @return bool Success status
     */
    public function record_failed_send($phone_number, $error_reason = '')
    {
        $failure_key = "failures_{$phone_number}";
        $current_failures = $this->get_cache_value($failure_key) ?: [];

        $current_failures[] = [
            'timestamp' => time(),
            'reason' => sanitize_text_field($error_reason)
        ];

        // Keep only last 10 failures
        if (count($current_failures) > 10) {
            $current_failures = array_slice($current_failures, -10);
        }

        // Store for 24 hours
        return $this->set_cache_value($failure_key, $current_failures, $this->cache_expiry['day']);
    }

    /**
     * Get failure count for contact
     *
     * @param string $phone_number Phone number
     * @param int    $time_window Time window in seconds (default: 1 hour)
     * @return int Number of failures in time window
     */
    public function get_failure_count($phone_number, $time_window = 3600)
    {
        $failures = $this->get_cache_value("failures_{$phone_number}") ?: [];
        $cutoff_time = time() - $time_window;

        $recent_failures = array_filter($failures, function ($failure) use ($cutoff_time) {
            return $failure['timestamp'] > $cutoff_time;
        });

        return count($recent_failures);
    }

    /**
     * Clear failure history for contact
     *
     * @param string $phone_number Phone number
     * @return bool Success status
     */
    public function clear_failures($phone_number)
    {
        return $this->delete_cache_value("failures_{$phone_number}");
    }

    /**
     * Check if contact should be automatically blocked due to failures
     *
     * @param string $phone_number Phone number
     * @return bool True if should be blocked
     */
    public function should_auto_block($phone_number)
    {
        $failure_threshold = 5; // Block after 5 failures in an hour
        $recent_failures = $this->get_failure_count($phone_number, 3600);

        return $recent_failures >= $failure_threshold;
    }

    /**
     * Get comprehensive rate limit status
     *
     * @param string $phone_number Optional specific contact
     * @return array Complete status
     */
    public function get_comprehensive_status($phone_number = '')
    {
        $status = [
            'global' => $this->get_global_quota(),
            'api' => $this->get_api_quota(),
            'timestamp' => time()
        ];

        if (!empty($phone_number)) {
            $status['contact'] = [
                'quota' => $this->get_contact_quota($phone_number),
                'blocked' => $this->is_contact_blocked($phone_number),
                'block_info' => $this->get_block_info($phone_number),
                'failure_count' => $this->get_failure_count($phone_number),
                'in_cooldown' => $this->is_in_cooldown($phone_number),
                'cooldown_remaining' => $this->get_cooldown_remaining($phone_number),
                'can_send' => $this->can_send($phone_number)
            ];
        }

        return $status;
    }

    /**
     * Export rate limit data for analysis
     *
     * @param int $days Number of days to export
     * @return array Export data
     */
    public function export_usage_data($days = 7)
    {
        global $wpdb;

        $date_from = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        // Get message counts by hour
        $hourly_data = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE_FORMAT(created_at, '%%Y-%%m-%%d %%H:00:00') as hour,
                COUNT(*) as message_count,
                COUNT(DISTINCT phone_number) as unique_contacts
             FROM {$wpdb->prefix}chatshop_messages 
             WHERE created_at >= %s AND direction = 'outgoing'
             GROUP BY DATE_FORMAT(created_at, '%%Y-%%m-%%d %%H:00:00')
             ORDER BY hour ASC",
            $date_from
        ), ARRAY_A);

        return [
            'period' => [
                'from' => $date_from,
                'to' => current_time('mysql'),
                'days' => $days
            ],
            'hourly_usage' => $hourly_data,
            'current_limits' => $this->rate_limits,
            'current_status' => $this->get_statistics()
        ];
    }

    /**
     * Optimize rate limits based on usage patterns
     *
     * @return array Optimization suggestions
     */
    public function get_optimization_suggestions()
    {
        $usage_data = $this->export_usage_data(7);
        $suggestions = [];

        // Analyze peak usage hours
        $peak_usage = 0;
        $peak_hour = '';

        foreach ($usage_data['hourly_usage'] as $hour_data) {
            if ($hour_data['message_count'] > $peak_usage) {
                $peak_usage = $hour_data['message_count'];
                $peak_hour = $hour_data['hour'];
            }
        }

        // Check if current limits are too restrictive
        $current_global_limit = $this->rate_limits['messaging']['global_per_hour'];
        if ($peak_usage > $current_global_limit * 0.8) {
            $suggestions[] = [
                'type' => 'increase_global_limit',
                'message' => sprintf(
                    __('Consider increasing global hourly limit from %d to %d based on peak usage of %d', 'chatshop'),
                    $current_global_limit,
                    ceil($peak_usage * 1.2),
                    $peak_usage
                ),
                'current_value' => $current_global_limit,
                'suggested_value' => ceil($peak_usage * 1.2)
            ];
        }

        // Check average contacts per hour
        $avg_contacts = array_sum(array_column($usage_data['hourly_usage'], 'unique_contacts')) / count($usage_data['hourly_usage']);
        $current_contact_limit = $this->rate_limits['messaging']['per_contact_per_hour'];

        if ($avg_contacts < 10 && $current_contact_limit > 5) {
            $suggestions[] = [
                'type' => 'decrease_contact_limit',
                'message' => sprintf(
                    __('Consider decreasing per-contact hourly limit from %d to %d based on low contact engagement', 'chatshop'),
                    $current_contact_limit,
                    5
                ),
                'current_value' => $current_contact_limit,
                'suggested_value' => 5
            ];
        }

        return $suggestions;
    }

    /**
     * Health check for rate limiting system
     *
     * @return array Health status
     */
    public function health_check()
    {
        $health = [
            'status' => 'healthy',
            'issues' => [],
            'metrics' => []
        ];

        // Check if any limits are frequently hit
        $global_quota = $this->get_global_quota();
        if ($global_quota['minute_remaining'] == 0) {
            $health['issues'][] = __('Global minute limit frequently reached', 'chatshop');
            $health['status'] = 'warning';
        }

        if ($global_quota['hourly_remaining'] < ($this->rate_limits['messaging']['global_per_hour'] * 0.1)) {
            $health['issues'][] = __('Global hourly limit nearly exhausted', 'chatshop');
            $health['status'] = 'warning';
        }

        // Check API limits
        $api_quota = $this->get_api_quota();
        if ($api_quota['minute_remaining'] == 0) {
            $health['issues'][] = __('API minute limit reached', 'chatshop');
            $health['status'] = 'critical';
        }

        // Add metrics
        $health['metrics'] = [
            'active_contacts' => $this->count_active_contacts(),
            'global_usage_percentage' => (($this->rate_limits['messaging']['global_per_hour'] - $global_quota['hourly_remaining']) / $this->rate_limits['messaging']['global_per_hour']) * 100,
            'api_usage_percentage' => (($this->rate_limits['api_calls']['per_hour'] - $api_quota['hourly_remaining']) / $this->rate_limits['api_calls']['per_hour']) * 100
        ];

        return $health;
    }
}
