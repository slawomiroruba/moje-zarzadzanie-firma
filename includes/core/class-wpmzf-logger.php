<?php

/**
 * Logger class for WPMZF plugin
 *
 * @package WPMZF
 * @subpackage Core
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPMZF_Logger {

    /**
     * Log levels
     */
    const INFO = 'info';
    const WARNING = 'warning';
    const ERROR = 'error';
    const DEBUG = 'debug';

    /**
     * Log message to WordPress debug log
     *
     * @param string $message Log message
     * @param string $level Log level
     * @param array $context Additional context data
     */
    public static function log($message, $level = self::INFO, $context = []) {
        // Tylko loguj jeśli WP_DEBUG jest włączone
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        $formatted_message = sprintf(
            '[WPMZF] [%s] %s',
            strtoupper($level),
            $message
        );

        if (!empty($context)) {
            $formatted_message .= ' Context: ' . wp_json_encode($context);
        }

        error_log($formatted_message);
    }

    /**
     * Log info message
     */
    public static function info($message, $context = []) {
        self::log($message, self::INFO, $context);
    }

    /**
     * Log warning message
     */
    public static function warning($message, $context = []) {
        self::log($message, self::WARNING, $context);
    }

    /**
     * Log error message
     */
    public static function error($message, $context = []) {
        self::log($message, self::ERROR, $context);
    }

    /**
     * Log debug message
     */
    public static function debug($message, $context = []) {
        self::log($message, self::DEBUG, $context);
    }

    /**
     * Log critical message
     */
    public static function critical($message, $context = []) {
        self::log($message, 'critical', $context);
    }

    /**
     * Log database query errors
     */
    public static function log_db_error($query, $error) {
        self::error("Database query failed", [
            'query' => $query,
            'error' => $error
        ]);
    }

    /**
     * Log AJAX errors
     */
    public static function log_ajax_error($action, $error, $data = []) {
        self::error("AJAX action failed: {$action}", [
            'error' => $error,
            'data' => $data
        ]);
    }

    /**
     * Log security violations
     */
    public static function log_security_violation($action, $user_id = null, $context = []) {
        self::warning("Security violation detected: {$action}", [
            'user_id' => $user_id ?? get_current_user_id(),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'context' => $context
        ]);
    }
}
