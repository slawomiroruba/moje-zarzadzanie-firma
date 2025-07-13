<?php

/**
 * Rate limiting utility for WPMZF plugin
 *
 * @package WPMZF
 * @subpackage Core
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPMZF_Rate_Limiter {

    /**
     * Rate limits per action (requests per minute)
     */
    const RATE_LIMITS = [
        'default' => 60,      // 60 requests per minute by default
        'upload' => 10,       // 10 uploads per minute
        'search' => 30,       // 30 search requests per minute
        'save' => 20,         // 20 save operations per minute
        'delete' => 5         // 5 delete operations per minute
    ];

    /**
     * Check if user has exceeded rate limit
     *
     * @param string $action Action name
     * @param int $user_id User ID (default: current user)
     * @return bool True if rate limit exceeded
     */
    public static function is_rate_limited($action = 'default', $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        // Admins are not rate limited
        if (current_user_can('manage_options')) {
            return false;
        }

        $key = self::get_rate_limit_key($action, $user_id);
        $current_count = get_transient($key);
        $limit = self::RATE_LIMITS[$action] ?? self::RATE_LIMITS['default'];

        if ($current_count >= $limit) {
            WPMZF_Logger::log_security_violation("Rate limit exceeded for action: {$action}", $user_id, [
                'action' => $action,
                'current_count' => $current_count,
                'limit' => $limit
            ]);
            return true;
        }

        return false;
    }

    /**
     * Increment rate limit counter
     *
     * @param string $action Action name
     * @param int $user_id User ID (default: current user)
     */
    public static function increment_counter($action = 'default', $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        // Admins are not rate limited
        if (current_user_can('manage_options')) {
            return;
        }

        $key = self::get_rate_limit_key($action, $user_id);
        $current_count = get_transient($key);

        if ($current_count === false) {
            // First request in this minute
            set_transient($key, 1, 60); // 60 seconds
        } else {
            // Increment counter
            set_transient($key, $current_count + 1, 60);
        }
    }

    /**
     * Get rate limit key for transient
     *
     * @param string $action Action name
     * @param int $user_id User ID
     * @return string
     */
    private static function get_rate_limit_key($action, $user_id) {
        $minute = floor(time() / 60); // Current minute
        return "wpmzf_rate_limit_{$action}_{$user_id}_{$minute}";
    }

    /**
     * Get current rate limit status for user
     *
     * @param string $action Action name
     * @param int $user_id User ID (default: current user)
     * @return array Status information
     */
    public static function get_rate_limit_status($action = 'default', $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        $key = self::get_rate_limit_key($action, $user_id);
        $current_count = get_transient($key);
        $limit = self::RATE_LIMITS[$action] ?? self::RATE_LIMITS['default'];

        return [
            'current_count' => $current_count ?: 0,
            'limit' => $limit,
            'remaining' => max(0, $limit - ($current_count ?: 0)),
            'is_limited' => ($current_count ?: 0) >= $limit
        ];
    }

    /**
     * Clear rate limit for user (admin function)
     *
     * @param string $action Action name
     * @param int $user_id User ID
     */
    public static function clear_rate_limit($action, $user_id) {
        if (!current_user_can('manage_options')) {
            return false;
        }

        $key = self::get_rate_limit_key($action, $user_id);
        delete_transient($key);
        
        WPMZF_Logger::info("Rate limit cleared for user {$user_id}, action: {$action}");
        return true;
    }

    /**
     * Add rate limiting to AJAX handler
     *
     * @param string $action_name Action name for rate limiting
     * @param callable $callback Original callback function
     * @return callable Wrapped callback with rate limiting
     */
    public static function wrap_ajax_handler($action_name, $callback) {
        return function() use ($action_name, $callback) {
            // Check rate limit
            if (self::is_rate_limited($action_name)) {
                wp_send_json_error([
                    'message' => 'Za dużo żądań. Spróbuj ponownie za chwilę.',
                    'code' => 'rate_limit_exceeded'
                ], 429);
                return;
            }

            // Increment counter
            self::increment_counter($action_name);

            // Call original handler
            return call_user_func($callback);
        };
    }
}
