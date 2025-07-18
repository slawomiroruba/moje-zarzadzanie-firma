<?php

/**
 * PHP Compatibility Helper
 * 
 * Zapewnia kompatybilność z PHP 8.3+ i obsługę błędów związanych z null values
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPMZF_PHP_Compat {

    /**
     * Bezpieczny strpos() - zapobiega błędom deprecated w PHP 8.3+
     */
    public static function safe_strpos($haystack, $needle, $offset = 0) {
        $haystack = $haystack ?? '';
        $needle = $needle ?? '';
        
        if (empty($haystack) && empty($needle)) {
            return 0;
        }
        
        return strpos($haystack, $needle, $offset);
    }

    /**
     * Bezpieczny str_replace() - zapobiega błędom deprecated w PHP 8.3+
     */
    public static function safe_str_replace($search, $replace, $subject, &$count = null) {
        $search = $search ?? '';
        $replace = $replace ?? '';
        $subject = $subject ?? '';
        
        return str_replace($search, $replace, $subject, $count);
    }

    /**
     * Bezpieczny strtolower() 
     */
    public static function safe_strtolower($string) {
        return strtolower($string ?? '');
    }

    /**
     * Bezpieczny trim()
     */
    public static function safe_trim($string, $characters = " \t\n\r\0\x0B") {
        return trim($string ?? '', $characters);
    }

    /**
     * Bezpieczne tworzenie CSS klasy ze statusu
     */
    public static function status_to_css_class($status, $prefix = 'status') {
        $status = $status ?? 'undefined';
        $status = self::safe_strtolower($status);
        $status = self::safe_str_replace(' ', '-', $status);
        $status = self::safe_str_replace('_', '-', $status);
        $status = preg_replace('/[^a-z0-9-]/', '', $status);
        
        return $prefix . '-' . $status;
    }

    /**
     * Sprawdza czy string zawiera określoną wartość (case insensitive)
     */
    public static function contains($haystack, $needle) {
        $haystack = self::safe_strtolower($haystack);
        $needle = self::safe_strtolower($needle);
        
        return self::safe_strpos($haystack, $needle) !== false;
    }

    /**
     * Sanitizuje wartość field dla WordPress
     */
    public static function sanitize_field_value($value, $default = '') {
        if (is_null($value) || $value === false) {
            return $default;
        }
        
        if (is_string($value)) {
            return sanitize_text_field($value);
        }
        
        return $value;
    }

    /**
     * Bezpieczne pobieranie field z ACF
     */
    public static function get_field_safe($field_key, $post_id = false, $default = '') {
        if (!function_exists('get_field')) {
            return $default;
        }
        
        $value = get_field($field_key, $post_id);
        return self::sanitize_field_value($value, $default);
    }

    /**
     * Sprawdza czy jesteśmy w odpowiedniej wersji PHP
     */
    public static function check_php_version($min_version = '7.4') {
        return version_compare(PHP_VERSION, $min_version, '>=');
    }

    /**
     * Loguje błędy kompatybilności
     */
    public static function log_compatibility_warning($message, $context = []) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("WPMZF PHP Compat Warning: {$message}");
            if (!empty($context)) {
                error_log("WPMZF Context: " . print_r($context, true));
            }
        }
    }
}
