<?php

/**
 * Globalny manager obsługi błędów i wyjątków
 *
 * @package WPMZF
 * @subpackage Core
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPMZF_Error_Handler {

    /**
     * Typy błędów
     */
    const ERROR_TYPES = [
        E_ERROR => 'Fatal Error',
        E_WARNING => 'Warning',
        E_PARSE => 'Parse Error',
        E_NOTICE => 'Notice',
        E_CORE_ERROR => 'Core Error',
        E_CORE_WARNING => 'Core Warning',
        E_COMPILE_ERROR => 'Compile Error',
        E_COMPILE_WARNING => 'Compile Warning',
        E_USER_ERROR => 'User Error',
        E_USER_WARNING => 'User Warning',
        E_USER_NOTICE => 'User Notice',
        8192 => 'Strict Standards', // E_STRICT is deprecated in PHP 8.4+
        E_RECOVERABLE_ERROR => 'Recoverable Error',
        E_DEPRECATED => 'Deprecated',
        E_USER_DEPRECATED => 'User Deprecated'
    ];

    /**
     * Zarejestrowane błędy
     */
    private static $errors = [];

    /**
     * Czy handler jest aktywny
     */
    private static $is_active = false;

    /**
     * Konfiguracja handlera
     */
    private static $config = [
        'log_errors' => true,
        'display_errors' => false,
        'email_admin' => false,
        'admin_email' => null,
        'error_threshold' => 10, // Maksymalna liczba błędów na godzinę
        'memory_threshold' => 90  // Procent wykorzystania pamięci
    ];

    /**
     * Inicjalizuje handler błędów
     */
    public static function init() {
        if (self::$is_active) {
            return;
        }

        // Ustaw konfigurację
        self::$config['admin_email'] = get_option('admin_email');
        self::$config['display_errors'] = WP_DEBUG;

        // Zarejestruj handlery
        set_error_handler([__CLASS__, 'handle_error']);
        set_exception_handler([__CLASS__, 'handle_exception']);
        register_shutdown_function([__CLASS__, 'handle_fatal_error']);

        self::$is_active = true;

        WPMZF_Logger::info('Error handler initialized');
    }

    /**
     * Obsługuje błędy PHP
     *
     * @param int $severity Poziom błędu
     * @param string $message Wiadomość błędu
     * @param string $file Plik gdzie wystąpił błąd
     * @param int $line Linia gdzie wystąpił błąd
     * @return bool
     */
    public static function handle_error($severity, $message, $file, $line) {
        // Ignoruj błędy jeśli error_reporting jest wyłączone
        if (!(error_reporting() & $severity)) {
            return false;
        }

        $error_type = self::ERROR_TYPES[$severity] ?? 'Unknown Error';
        
        $error_data = [
            'type' => $error_type,
            'severity' => $severity,
            'message' => $message,
            'file' => $file,
            'line' => $line,
            'timestamp' => time(),
            'backtrace' => self::get_backtrace(),
            'context' => self::get_context()
        ];

        self::$errors[] = $error_data;

        // Loguj błąd
        if (self::$config['log_errors']) {
            self::log_error($error_data);
        }

        // Sprawdź czy nie przekroczono progu błędów
        self::check_error_threshold();

        // Sprawdź wykorzystanie pamięci
        self::check_memory_usage();

        // Wyświetl błąd jeśli włączone
        if (self::$config['display_errors'] && in_array($severity, [E_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR])) {
            self::display_error($error_data);
        }

        // Powiadom administratora o krytycznych błędach
        if (in_array($severity, [E_ERROR, E_USER_ERROR, E_CORE_ERROR, E_COMPILE_ERROR])) {
            self::notify_admin($error_data);
        }

        // Nie zatrzymuj domyślnej obsługi błędów
        return false;
    }

    /**
     * Obsługuje nieobsłużone wyjątki
     *
     * @param Throwable $exception Wyjątek
     */
    public static function handle_exception($exception) {
        $error_data = [
            'type' => 'Uncaught Exception',
            'class' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'timestamp' => time(),
            'backtrace' => $exception->getTrace(),
            'context' => self::get_context()
        ];

        self::$errors[] = $error_data;

        // Loguj wyjątek
        if (self::$config['log_errors']) {
            self::log_error($error_data);
        }

        // Powiadom administratora
        self::notify_admin($error_data);

        // Wyświetl przyjazny komunikat użytkownikowi
        if (!wp_doing_ajax() && !wp_doing_cron()) {
            self::display_user_friendly_error();
        } else {
            // Dla AJAX zwróć błąd JSON
            wp_send_json_error([
                'message' => 'Wystąpił nieoczekiwany błąd. Spróbuj ponownie.',
                'error_id' => uniqid('err_')
            ]);
        }
    }

    /**
     * Obsługuje błędy fatalne
     */
    public static function handle_fatal_error() {
        $error = error_get_last();
        
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $error_data = [
                'type' => 'Fatal Error',
                'severity' => $error['type'],
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line'],
                'timestamp' => time(),
                'backtrace' => [],
                'context' => self::get_context()
            ];

            self::$errors[] = $error_data;

            // Loguj błąd
            if (self::$config['log_errors']) {
                self::log_error($error_data);
            }

            // Powiadom administratora
            self::notify_admin($error_data);

            // Próbuj wyświetlić przyjazny komunikat
            if (!headers_sent()) {
                self::display_user_friendly_error();
            }
        }
    }

    /**
     * Loguje błąd
     *
     * @param array $error_data Dane błędu
     */
    private static function log_error($error_data) {
        $log_level = 'error';
        
        // Określ poziom loga na podstawie typu błędu
        if (in_array($error_data['severity'] ?? 0, [E_NOTICE, E_USER_NOTICE, E_DEPRECATED, E_USER_DEPRECATED])) {
            $log_level = 'warning';
        }

        WPMZF_Logger::log($log_level, 'PHP Error: ' . $error_data['message'], [
            'type' => $error_data['type'],
            'file' => basename($error_data['file']),
            'line' => $error_data['line'],
            'backtrace' => array_slice($error_data['backtrace'], 0, 5), // Ograniczamy backtrace
            'context' => $error_data['context']
        ]);
    }

    /**
     * Wyświetla błąd użytkownikowi
     *
     * @param array $error_data Dane błędu
     */
    private static function display_error($error_data) {
        if (wp_doing_ajax()) {
            wp_send_json_error([
                'message' => 'Wystąpił błąd: ' . $error_data['message'],
                'type' => $error_data['type']
            ]);
        } else {
            echo '<div class="error notice">';
            echo '<p><strong>Błąd:</strong> ' . esc_html($error_data['message']) . '</p>';
            echo '<p><small>Plik: ' . esc_html(basename($error_data['file'])) . ', linia: ' . $error_data['line'] . '</small></p>';
            echo '</div>';
        }
    }

    /**
     * Wyświetla przyjazny komunikat błędu użytkownikowi
     */
    private static function display_user_friendly_error() {
        if (wp_doing_ajax()) {
            wp_send_json_error([
                'message' => 'Wystąpił nieoczekiwany błąd. Spróbuj odświeżyć stronę lub skontaktuj się z administratorem.'
            ]);
        } else {
            $error_page = '
            <!DOCTYPE html>
            <html>
            <head>
                <title>Wystąpił błąd</title>
                <style>
                    body { font-family: Arial, sans-serif; margin: 50px; text-align: center; }
                    .error-container { max-width: 500px; margin: 0 auto; }
                    .error-icon { font-size: 64px; color: #dc3232; }
                    h1 { color: #333; }
                    p { color: #666; line-height: 1.6; }
                    .button { background: #0073aa; color: white; padding: 10px 20px; text-decoration: none; border-radius: 3px; }
                </style>
            </head>
            <body>
                <div class="error-container">
                    <div class="error-icon">⚠️</div>
                    <h1>Ups! Wystąpił problem</h1>
                    <p>Przepraszamy, ale napotkaliśmy nieoczekiwany błąd. Nasz zespół został powiadomiony i pracuje nad rozwiązaniem problemu.</p>
                    <p>Spróbuj odświeżyć stronę za kilka minut.</p>
                    <a href="' . home_url() . '" class="button">Powróć do strony głównej</a>
                </div>
            </body>
            </html>';
            
            echo $error_page;
        }
    }

    /**
     * Powiadamia administratora o błędzie
     *
     * @param array $error_data Dane błędu
     */
    private static function notify_admin($error_data) {
        if (!self::$config['email_admin'] || empty(self::$config['admin_email'])) {
            return;
        }

        // Nie wysyłaj więcej niż jeden email na 5 minut
        $last_notification = get_transient('wpmzf_last_error_notification');
        if ($last_notification && (time() - $last_notification) < 300) {
            return;
        }

        $subject = 'Błąd w pluginie WPMZF - ' . get_bloginfo('name');
        $message = "Wystąpił błąd w pluginie Moje Zarządzanie Firma:\n\n";
        $message .= "Typ: " . $error_data['type'] . "\n";
        $message .= "Wiadomość: " . $error_data['message'] . "\n";
        $message .= "Plik: " . $error_data['file'] . "\n";
        $message .= "Linia: " . $error_data['line'] . "\n";
        $message .= "Czas: " . date('Y-m-d H:i:s', $error_data['timestamp']) . "\n\n";
        
        if (!empty($error_data['context'])) {
            $message .= "Kontekst:\n" . print_r($error_data['context'], true) . "\n";
        }

        wp_mail(self::$config['admin_email'], $subject, $message);
        set_transient('wpmzf_last_error_notification', time(), 300);
    }

    /**
     * Sprawdza próg błędów
     */
    private static function check_error_threshold() {
        $hour_ago = time() - 3600;
        $recent_errors = array_filter(self::$errors, function($error) use ($hour_ago) {
            return $error['timestamp'] > $hour_ago;
        });

        if (count($recent_errors) > self::$config['error_threshold']) {
            WPMZF_Logger::critical('Error threshold exceeded', [
                'error_count' => count($recent_errors),
                'threshold' => self::$config['error_threshold'],
                'time_period' => '1 hour'
            ]);

            // Wyłącz czasowo niektóre funkcje pluginu
            set_transient('wpmzf_error_mode', true, 3600);
        }
    }

    /**
     * Sprawdza wykorzystanie pamięci
     */
    private static function check_memory_usage() {
        $memory_usage = memory_get_usage(true);
        $memory_limit = self::get_memory_limit();
        $usage_percent = ($memory_usage / $memory_limit) * 100;

        if ($usage_percent > self::$config['memory_threshold']) {
            WPMZF_Logger::warning('High memory usage detected', [
                'memory_used' => self::format_bytes($memory_usage),
                'memory_limit' => self::format_bytes($memory_limit),
                'usage_percent' => round($usage_percent, 2)
            ]);
        }
    }

    /**
     * Pobiera kontekst błędu
     *
     * @return array
     */
    private static function get_context() {
        return [
            'user_id' => get_current_user_id(),
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'ip_address' => self::get_client_ip(),
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'memory_usage' => memory_get_usage(true),
            'time' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Pobiera backtrace
     *
     * @return array
     */
    private static function get_backtrace() {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        
        // Filtruj tylko istotne pliki (plugin WPMZF)
        return array_filter($backtrace, function($trace) {
            return isset($trace['file']) && strpos($trace['file'], 'moje-zarzadzanie-firma') !== false;
        });
    }

    /**
     * Pobiera IP klienta
     *
     * @return string
     */
    private static function get_client_ip() {
        $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * Pobiera limit pamięci
     *
     * @return int Limit w bajtach
     */
    private static function get_memory_limit() {
        $limit = ini_get('memory_limit');
        
        if (preg_match('/^(\d+)(.)$/', $limit, $matches)) {
            if ($matches[2] == 'M') {
                return $matches[1] * 1024 * 1024;
            } elseif ($matches[2] == 'K') {
                return $matches[1] * 1024;
            } elseif ($matches[2] == 'G') {
                return $matches[1] * 1024 * 1024 * 1024;
            }
        }
        
        return intval($limit);
    }

    /**
     * Formatuje bajty do czytelnej postaci
     *
     * @param int $size Rozmiar w bajtach
     * @return string
     */
    private static function format_bytes($size) {
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        
        return round($size, 2) . ' ' . $units[$i];
    }

    /**
     * Pobiera wszystkie zarejestrowane błędy
     *
     * @return array
     */
    public static function get_errors() {
        return self::$errors;
    }

    /**
     * Pobiera statystyki błędów
     *
     * @return array
     */
    public static function get_error_stats() {
        $stats = [
            'total_errors' => count(self::$errors),
            'recent_errors' => 0,
            'error_types' => [],
            'memory_usage' => self::format_bytes(memory_get_usage(true)),
            'peak_memory' => self::format_bytes(memory_get_peak_usage(true))
        ];

        $hour_ago = time() - 3600;
        
        foreach (self::$errors as $error) {
            if ($error['timestamp'] > $hour_ago) {
                $stats['recent_errors']++;
            }
            
            $type = $error['type'];
            if (!isset($stats['error_types'][$type])) {
                $stats['error_types'][$type] = 0;
            }
            $stats['error_types'][$type]++;
        }

        return $stats;
    }

    /**
     * Czyści stare błędy
     */
    public static function cleanup_old_errors() {
        $day_ago = time() - 86400; // 24 godziny
        
        self::$errors = array_filter(self::$errors, function($error) use ($day_ago) {
            return $error['timestamp'] > $day_ago;
        });
        
        WPMZF_Logger::info('Old errors cleaned up', ['remaining_errors' => count(self::$errors)]);
    }

    /**
     * Konfiguruje handler błędów
     *
     * @param array $config Nowa konfiguracja
     */
    public static function configure($config) {
        self::$config = array_merge(self::$config, $config);
        WPMZF_Logger::info('Error handler configuration updated', $config);
    }

    /**
     * Sprawdza czy plugin jest w trybie błędu
     *
     * @return bool
     */
    public static function is_error_mode() {
        return get_transient('wpmzf_error_mode') === true;
    }

    /**
     * Wyłącza tryb błędu
     */
    public static function disable_error_mode() {
        delete_transient('wpmzf_error_mode');
        WPMZF_Logger::info('Error mode disabled');
    }
}
