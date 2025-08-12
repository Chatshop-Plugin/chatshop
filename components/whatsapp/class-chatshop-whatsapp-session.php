<?php

/**
 * ChatShop WhatsApp Session Management
 *
 * Manages WhatsApp conversation sessions and context
 *
 * @package ChatShop
 * @subpackage WhatsApp
 * @since 1.0.0
 */

namespace ChatShop\WhatsApp;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WhatsApp Session Manager class
 *
 * Handles session tracking, conversation context, and state management
 */
class ChatShop_WhatsApp_Session
{

    /**
     * Session timeout in seconds (24 hours)
     */
    const SESSION_TIMEOUT = 86400;

    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('chatshop_cleanup_expired_sessions', [$this, 'cleanup_expired_sessions']);

        // Schedule session cleanup
        if (!wp_next_scheduled('chatshop_cleanup_expired_sessions')) {
            wp_schedule_event(time(), 'daily', 'chatshop_cleanup_expired_sessions');
        }
    }

    /**
     * Create or update session
     *
     * @param string $phone_number Phone number
     * @param array  $context Session context
     * @return bool|WP_Error True on success, error on failure
     */
    public function create_or_update_session($phone_number, $context = [])
    {
        if (empty($phone_number)) {
            return new \WP_Error('invalid_phone', __('Phone number is required', 'chatshop'));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'chatshop_whatsapp_sessions';

        $phone_number = sanitize_text_field($phone_number);
        $existing_session = $this->get_session($phone_number);

        $session_data = [
            'phone_number' => $phone_number,
            'context' => json_encode($this->sanitize_context($context)),
            'last_activity' => current_time('mysql'),
            'expires_at' => date('Y-m-d H:i:s', time() + self::SESSION_TIMEOUT)
        ];

        if ($existing_session) {
            // Merge existing context with new context
            $existing_context = json_decode($existing_session['context'], true) ?: [];
            $merged_context = array_merge($existing_context, $context);
            $session_data['context'] = json_encode($this->sanitize_context($merged_context));

            $result = $wpdb->update(
                $table_name,
                $session_data,
                ['phone_number' => $phone_number],
                ['%s', '%s', '%s', '%s'],
                ['%s']
            );
        } else {
            $session_data['created_at'] = current_time('mysql');

            $result = $wpdb->insert(
                $table_name,
                $session_data,
                ['%s', '%s', '%s', '%s', '%s']
            );
        }

        if ($result === false) {
            return new \WP_Error('db_error', __('Failed to create/update session', 'chatshop'));
        }

        return true;
    }

    /**
     * Get session by phone number
     *
     * @param string $phone_number Phone number
     * @return array|null Session data or null if not found/expired
     */
    public function get_session($phone_number)
    {
        if (empty($phone_number)) {
            return null;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'chatshop_whatsapp_sessions';

        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE phone_number = %s AND expires_at > NOW()",
            sanitize_text_field($phone_number)
        ), ARRAY_A);

        return $session;
    }

    /**
     * Get session context
     *
     * @param string $phone_number Phone number
     * @param string $key Optional specific context key
     * @return mixed Session context or specific value
     */
    public function get_session_context($phone_number, $key = '')
    {
        $session = $this->get_session($phone_number);

        if (!$session) {
            return empty($key) ? [] : null;
        }

        $context = json_decode($session['context'], true) ?: [];

        if (empty($key)) {
            return $context;
        }

        return $context[$key] ?? null;
    }

    /**
     * Update session context
     *
     * @param string $phone_number Phone number
     * @param array  $context_updates Context updates
     * @return bool|WP_Error True on success, error on failure
     */
    public function update_session_context($phone_number, $context_updates)
    {
        return $this->create_or_update_session($phone_number, $context_updates);
    }

    /**
     * Set session state
     *
     * @param string $phone_number Phone number
     * @param string $state Session state
     * @param array  $state_data Additional state data
     * @return bool|WP_Error True on success, error on failure
     */
    public function set_session_state($phone_number, $state, $state_data = [])
    {
        $context_updates = [
            'current_state' => $state,
            'state_data' => $state_data,
            'state_timestamp' => time()
        ];

        return $this->update_session_context($phone_number, $context_updates);
    }

    /**
     * Get session state
     *
     * @param string $phone_number Phone number
     * @return array Session state data
     */
    public function get_session_state($phone_number)
    {
        $context = $this->get_session_context($phone_number);

        return [
            'current_state' => $context['current_state'] ?? 'idle',
            'state_data' => $context['state_data'] ?? [],
            'state_timestamp' => $context['state_timestamp'] ?? 0
        ];
    }

    /**
     * Clear session state
     *
     * @param string $phone_number Phone number
     * @return bool|WP_Error True on success, error on failure
     */
    public function clear_session_state($phone_number)
    {
        return $this->set_session_state($phone_number, 'idle');
    }

    /**
     * Add item to session cart
     *
     * @param string $phone_number Phone number
     * @param int    $product_id Product ID
     * @param int    $quantity Quantity
     * @param array  $variation_data Variation data
     * @return bool|WP_Error True on success, error on failure
     */
    public function add_to_session_cart($phone_number, $product_id, $quantity = 1, $variation_data = [])
    {
        $current_cart = $this->get_session_context($phone_number, 'cart') ?: [];

        $cart_item_key = $this->generate_cart_item_key($product_id, $variation_data);

        if (isset($current_cart[$cart_item_key])) {
            $current_cart[$cart_item_key]['quantity'] += $quantity;
        } else {
            $current_cart[$cart_item_key] = [
                'product_id' => $product_id,
                'quantity' => $quantity,
                'variation_data' => $variation_data,
                'added_at' => current_time('mysql')
            ];
        }

        return $this->update_session_context($phone_number, ['cart' => $current_cart]);
    }

    /**
     * Remove item from session cart
     *
     * @param string $phone_number Phone number
     * @param int    $product_id Product ID
     * @param array  $variation_data Variation data
     * @return bool|WP_Error True on success, error on failure
     */
    public function remove_from_session_cart($phone_number, $product_id, $variation_data = [])
    {
        $current_cart = $this->get_session_context($phone_number, 'cart') ?: [];

        $cart_item_key = $this->generate_cart_item_key($product_id, $variation_data);

        if (isset($current_cart[$cart_item_key])) {
            unset($current_cart[$cart_item_key]);
        }

        return $this->update_session_context($phone_number, ['cart' => $current_cart]);
    }

    /**
     * Get session cart
     *
     * @param string $phone_number Phone number
     * @return array Session cart items
     */
    public function get_session_cart($phone_number)
    {
        return $this->get_session_context($phone_number, 'cart') ?: [];
    }

    /**
     * Clear session cart
     *
     * @param string $phone_number Phone number
     * @return bool|WP_Error True on success, error on failure
     */
    public function clear_session_cart($phone_number)
    {
        return $this->update_session_context($phone_number, ['cart' => []]);
    }

    /**
     * Get session cart total
     *
     * @param string $phone_number Phone number
     * @return float Cart total
     */
    public function get_session_cart_total($phone_number)
    {
        $cart = $this->get_session_cart($phone_number);
        $total = 0;

        foreach ($cart as $item) {
            $product = wc_get_product($item['product_id']);
            if ($product) {
                $total += $product->get_price() * $item['quantity'];
            }
        }

        return $total;
    }

    /**
     * Set conversation flow
     *
     * @param string $phone_number Phone number
     * @param string $flow_name Flow name
     * @param int    $step_number Current step
     * @param array  $flow_data Flow data
     * @return bool|WP_Error True on success, error on failure
     */
    public function set_conversation_flow($phone_number, $flow_name, $step_number = 1, $flow_data = [])
    {
        $context_updates = [
            'conversation_flow' => [
                'flow_name' => $flow_name,
                'current_step' => $step_number,
                'flow_data' => $flow_data,
                'started_at' => current_time('mysql')
            ]
        ];

        return $this->update_session_context($phone_number, $context_updates);
    }

    /**
     * Get conversation flow
     *
     * @param string $phone_number Phone number
     * @return array|null Conversation flow data
     */
    public function get_conversation_flow($phone_number)
    {
        return $this->get_session_context($phone_number, 'conversation_flow');
    }

    /**
     * Update conversation flow step
     *
     * @param string $phone_number Phone number
     * @param int    $step_number New step number
     * @param array  $step_data Additional step data
     * @return bool|WP_Error True on success, error on failure
     */
    public function update_conversation_flow_step($phone_number, $step_number, $step_data = [])
    {
        $current_flow = $this->get_conversation_flow($phone_number);

        if (!$current_flow) {
            return new \WP_Error('no_flow', __('No active conversation flow', 'chatshop'));
        }

        $current_flow['current_step'] = $step_number;
        $current_flow['flow_data'] = array_merge($current_flow['flow_data'], $step_data);
        $current_flow['updated_at'] = current_time('mysql');

        return $this->update_session_context($phone_number, ['conversation_flow' => $current_flow]);
    }

    /**
     * Clear conversation flow
     *
     * @param string $phone_number Phone number
     * @return bool|WP_Error True on success, error on failure
     */
    public function clear_conversation_flow($phone_number)
    {
        return $this->update_session_context($phone_number, ['conversation_flow' => null]);
    }

    /**
     * Set user preference
     *
     * @param string $phone_number Phone number
     * @param string $key Preference key
     * @param mixed  $value Preference value
     * @return bool|WP_Error True on success, error on failure
     */
    public function set_user_preference($phone_number, $key, $value)
    {
        $current_preferences = $this->get_session_context($phone_number, 'user_preferences') ?: [];
        $current_preferences[$key] = $value;

        return $this->update_session_context($phone_number, ['user_preferences' => $current_preferences]);
    }

    /**
     * Get user preference
     *
     * @param string $phone_number Phone number
     * @param string $key Preference key
     * @param mixed  $default Default value
     * @return mixed Preference value or default
     */
    public function get_user_preference($phone_number, $key, $default = null)
    {
        $preferences = $this->get_session_context($phone_number, 'user_preferences') ?: [];
        return $preferences[$key] ?? $default;
    }

    /**
     * Track user interaction
     *
     * @param string $phone_number Phone number
     * @param string $interaction_type Interaction type
     * @param array  $interaction_data Interaction data
     * @return bool|WP_Error True on success, error on failure
     */
    public function track_user_interaction($phone_number, $interaction_type, $interaction_data = [])
    {
        $interactions = $this->get_session_context($phone_number, 'interactions') ?: [];

        $interactions[] = [
            'type' => $interaction_type,
            'data' => $interaction_data,
            'timestamp' => current_time('mysql')
        ];

        // Keep only last 50 interactions
        if (count($interactions) > 50) {
            $interactions = array_slice($interactions, -50);
        }

        return $this->update_session_context($phone_number, ['interactions' => $interactions]);
    }

    /**
     * Get user interaction history
     *
     * @param string $phone_number Phone number
     * @param string $interaction_type Optional filter by type
     * @param int    $limit Limit number of results
     * @return array Interaction history
     */
    public function get_user_interaction_history($phone_number, $interaction_type = '', $limit = 10)
    {
        $interactions = $this->get_session_context($phone_number, 'interactions') ?: [];

        if (!empty($interaction_type)) {
            $interactions = array_filter($interactions, function ($interaction) use ($interaction_type) {
                return $interaction['type'] === $interaction_type;
            });
        }

        return array_slice(array_reverse($interactions), 0, $limit);
    }

    /**
     * Set session variable
     *
     * @param string $phone_number Phone number
     * @param string $key Variable key
     * @param mixed  $value Variable value
     * @param int    $ttl Time to live in seconds (optional)
     * @return bool|WP_Error True on success, error on failure
     */
    public function set_session_variable($phone_number, $key, $value, $ttl = 0)
    {
        $variables = $this->get_session_context($phone_number, 'variables') ?: [];

        $variable_data = [
            'value' => $value,
            'set_at' => time()
        ];

        if ($ttl > 0) {
            $variable_data['expires_at'] = time() + $ttl;
        }

        $variables[$key] = $variable_data;

        return $this->update_session_context($phone_number, ['variables' => $variables]);
    }

    /**
     * Get session variable
     *
     * @param string $phone_number Phone number
     * @param string $key Variable key
     * @param mixed  $default Default value
     * @return mixed Variable value or default
     */
    public function get_session_variable($phone_number, $key, $default = null)
    {
        $variables = $this->get_session_context($phone_number, 'variables') ?: [];

        if (!isset($variables[$key])) {
            return $default;
        }

        $variable = $variables[$key];

        // Check if variable has expired
        if (isset($variable['expires_at']) && $variable['expires_at'] < time()) {
            $this->delete_session_variable($phone_number, $key);
            return $default;
        }

        return $variable['value'];
    }

    /**
     * Delete session variable
     *
     * @param string $phone_number Phone number
     * @param string $key Variable key
     * @return bool|WP_Error True on success, error on failure
     */
    public function delete_session_variable($phone_number, $key)
    {
        $variables = $this->get_session_context($phone_number, 'variables') ?: [];

        if (isset($variables[$key])) {
            unset($variables[$key]);
        }

        return $this->update_session_context($phone_number, ['variables' => $variables]);
    }

    /**
     * Delete session
     *
     * @param string $phone_number Phone number
     * @return bool|WP_Error True on success, error on failure
     */
    public function delete_session($phone_number)
    {
        if (empty($phone_number)) {
            return new \WP_Error('invalid_phone', __('Phone number is required', 'chatshop'));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'chatshop_whatsapp_sessions';

        $result = $wpdb->delete(
            $table_name,
            ['phone_number' => sanitize_text_field($phone_number)],
            ['%s']
        );

        if ($result === false) {
            return new \WP_Error('db_error', __('Failed to delete session', 'chatshop'));
        }

        return true;
    }

    /**
     * Extend session expiry
     *
     * @param string $phone_number Phone number
     * @param int    $additional_seconds Additional seconds to extend
     * @return bool|WP_Error True on success, error on failure
     */
    public function extend_session($phone_number, $additional_seconds = null)
    {
        if ($additional_seconds === null) {
            $additional_seconds = self::SESSION_TIMEOUT;
        }

        $session = $this->get_session($phone_number);
        if (!$session) {
            return new \WP_Error('session_not_found', __('Session not found', 'chatshop'));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'chatshop_whatsapp_sessions';

        $new_expiry = date('Y-m-d H:i:s', time() + $additional_seconds);

        $result = $wpdb->update(
            $table_name,
            [
                'expires_at' => $new_expiry,
                'last_activity' => current_time('mysql')
            ],
            ['phone_number' => $phone_number],
            ['%s', '%s'],
            ['%s']
        );

        if ($result === false) {
            return new \WP_Error('db_error', __('Failed to extend session', 'chatshop'));
        }

        return true;
    }

    /**
     * Get active sessions count
     *
     * @return int Number of active sessions
     */
    public function get_active_sessions_count()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'chatshop_whatsapp_sessions';

        return $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table_name} WHERE expires_at > NOW()"
        );
    }

    /**
     * Get session statistics
     *
     * @param int $days Number of days to look back
     * @return array Session statistics
     */
    public function get_session_statistics($days = 7)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'chatshop_whatsapp_sessions';

        $date_from = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $stats = [
            'total_sessions' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} WHERE created_at >= %s",
                $date_from
            )),
            'active_sessions' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$table_name} WHERE expires_at > NOW()"
            ),
            'unique_users' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT phone_number) FROM {$table_name} WHERE created_at >= %s",
                $date_from
            ))
        ];

        return $stats;
    }

    /**
     * Cleanup expired sessions
     */
    public function cleanup_expired_sessions()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'chatshop_whatsapp_sessions';

        $deleted_count = $wpdb->delete(
            $table_name,
            ['expires_at <' => current_time('mysql')],
            ['%s']
        );

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("ChatShop: Cleaned up {$deleted_count} expired WhatsApp sessions");
        }

        do_action('chatshop_sessions_cleaned_up', $deleted_count);
    }

    /**
     * Generate cart item key
     *
     * @param int   $product_id Product ID
     * @param array $variation_data Variation data
     * @return string Cart item key
     */
    private function generate_cart_item_key($product_id, $variation_data = [])
    {
        $key_parts = [$product_id];

        if (!empty($variation_data)) {
            ksort($variation_data);
            foreach ($variation_data as $key => $value) {
                $key_parts[] = $key . ':' . $value;
            }
        }

        return md5(implode('|', $key_parts));
    }

    /**
     * Sanitize context data
     *
     * @param array $context Context data
     * @return array Sanitized context
     */
    private function sanitize_context($context)
    {
        if (!is_array($context)) {
            return [];
        }

        $sanitized = [];

        foreach ($context as $key => $value) {
            $key = sanitize_key($key);

            if (is_array($value)) {
                $sanitized[$key] = $this->sanitize_context($value);
            } elseif (is_string($value)) {
                $sanitized[$key] = sanitize_textarea_field($value);
            } elseif (is_numeric($value)) {
                $sanitized[$key] = $value;
            } elseif (is_bool($value)) {
                $sanitized[$key] = $value;
            } else {
                $sanitized[$key] = sanitize_textarea_field(strval($value));
            }
        }

        return $sanitized;
    }
}
