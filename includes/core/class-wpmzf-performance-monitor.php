<?php

/**
 * Monitor wydajności pluginu
 *
 * @package WPMZF
 * @subpackage Core
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPMZF_Performance_Monitor {

    /**
     * Dane pomiarów wydajności
     */
    private static $measurements = [];

    /**
     * Aktywne pomiary
     */
    private static $active_timers = [];

    /**
     * Konfiguracja alertów
     */
    private static $alert_thresholds = [
        'slow_query' => 2.0,     // 2 sekundy
        'memory_usage' => 128,   // 128 MB
        'ajax_timeout' => 10.0,  // 10 sekund
        'file_upload' => 30.0    // 30 sekund
    ];

    /**
     * Inicjalizuje monitor wydajności
     */
    public static function init() {
        // Hook do monitorowania zapytań AJAX
        add_action('wp_ajax_nopriv_wpmzf_*', [__CLASS__, 'start_ajax_monitoring'], 1);
        add_action('wp_ajax_wpmzf_*', [__CLASS__, 'start_ajax_monitoring'], 1);
        
        // Hook do zakończenia monitorowania
        add_action('wp_die', [__CLASS__, 'end_monitoring']);
        add_action('shutdown', [__CLASS__, 'log_performance_summary']);
        
        // Hook do monitorowania zapytań SQL
        add_filter('query', [__CLASS__, 'monitor_sql_query']);
        
        // Hook do monitorowania wykorzystania pamięci
        add_action('init', [__CLASS__, 'log_memory_usage']);
    }

    /**
     * Rozpoczyna pomiar wydajności
     *
     * @param string $operation_name Nazwa operacji
     * @param array $context Kontekst operacji
     */
    public static function start_timer($operation_name, $context = []) {
        $timer_id = uniqid($operation_name . '_');
        
        self::$active_timers[$timer_id] = [
            'operation' => $operation_name,
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage(true),
            'context' => $context
        ];
        
        return $timer_id;
    }

    /**
     * Kończy pomiar wydajności
     *
     * @param string $timer_id ID timera
     * @param array $additional_context Dodatkowy kontekst
     */
    public static function end_timer($timer_id, $additional_context = []) {
        if (!isset(self::$active_timers[$timer_id])) {
            return;
        }
        
        $timer = self::$active_timers[$timer_id];
        $end_time = microtime(true);
        $end_memory = memory_get_usage(true);
        
        $measurement = [
            'operation' => $timer['operation'],
            'duration' => $end_time - $timer['start_time'],
            'memory_used' => $end_memory - $timer['start_memory'],
            'peak_memory' => memory_get_peak_usage(true),
            'context' => array_merge($timer['context'], $additional_context),
            'timestamp' => time()
        ];
        
        self::$measurements[] = $measurement;
        unset(self::$active_timers[$timer_id]);
        
        // Sprawdź czy nie ma problemów wydajnościowych
        self::check_performance_alerts($measurement);
        
        return $measurement;
    }

    /**
     * Rozpoczyna monitorowanie AJAX
     */
    public static function start_ajax_monitoring() {
        $action = $_POST['action'] ?? $_GET['action'] ?? 'unknown';
        
        self::start_timer('ajax_request', [
            'action' => $action,
            'method' => $_SERVER['REQUEST_METHOD'],
            'user_id' => get_current_user_id(),
            'ip' => self::get_client_ip()
        ]);
    }

    /**
     * Kończy monitorowanie
     */
    public static function end_monitoring() {
        // Zakończ wszystkie aktywne pomiary
        foreach (array_keys(self::$active_timers) as $timer_id) {
            self::end_timer($timer_id);
        }
    }

    /**
     * Monitoruje zapytania SQL
     *
     * @param string $query Zapytanie SQL
     * @return string
     */
    public static function monitor_sql_query($query) {
        static $query_count = 0;
        static $start_time = null;
        
        if ($start_time === null) {
            $start_time = microtime(true);
        }
        
        $query_count++;
        
        // Loguj długie zapytania
        $query_start = microtime(true);
        
        // Hook po wykonaniu zapytania (to nie jest idealne, ale lepsze niż nic)
        add_action('shutdown', function() use ($query, $query_start) {
            $duration = microtime(true) - $query_start;
            
            if ($duration > self::$alert_thresholds['slow_query']) {
                WPMZF_Logger::warning('Slow SQL query detected', [
                    'query' => substr($query, 0, 200) . (strlen($query) > 200 ? '...' : ''),
                    'duration' => $duration,
                    'backtrace' => self::get_relevant_backtrace()
                ]);
            }
        });
        
        return $query;
    }

    /**
     * Loguje wykorzystanie pamięci
     */
    public static function log_memory_usage() {
        $memory_usage = memory_get_usage(true);
        $memory_limit = self::get_memory_limit();
        
        if ($memory_usage > ($memory_limit * 0.8)) { // 80% limitu
            WPMZF_Logger::warning('High memory usage detected', [
                'memory_used' => self::format_bytes($memory_usage),
                'memory_limit' => self::format_bytes($memory_limit),
                'percentage' => round(($memory_usage / $memory_limit) * 100, 2)
            ]);
        }
    }

    /**
     * Sprawdza alerty wydajnościowe
     *
     * @param array $measurement Pomiar wydajności
     */
    private static function check_performance_alerts($measurement) {
        $operation = $measurement['operation'];
        $duration = $measurement['duration'];
        
        // Alert dla długich operacji AJAX
        if ($operation === 'ajax_request' && $duration > self::$alert_thresholds['ajax_timeout']) {
            WPMZF_Logger::warning('Slow AJAX request detected', [
                'action' => $measurement['context']['action'] ?? 'unknown',
                'duration' => $duration,
                'memory_used' => self::format_bytes($measurement['memory_used'])
            ]);
        }
        
        // Alert dla dużego zużycia pamięci
        if ($measurement['memory_used'] > (self::$alert_thresholds['memory_usage'] * 1024 * 1024)) {
            WPMZF_Logger::warning('High memory usage in operation', [
                'operation' => $operation,
                'memory_used' => self::format_bytes($measurement['memory_used']),
                'context' => $measurement['context']
            ]);
        }
    }

    /**
     * Loguje podsumowanie wydajności
     */
    public static function log_performance_summary() {
        if (empty(self::$measurements)) {
            return;
        }
        
        $total_time = 0;
        $total_memory = 0;
        $operations_count = [];
        
        foreach (self::$measurements as $measurement) {
            $total_time += $measurement['duration'];
            $total_memory += $measurement['memory_used'];
            
            $operation = $measurement['operation'];
            if (!isset($operations_count[$operation])) {
                $operations_count[$operation] = 0;
            }
            $operations_count[$operation]++;
        }
        
        $summary = [
            'total_operations' => count(self::$measurements),
            'total_time' => $total_time,
            'average_time' => $total_time / count(self::$measurements),
            'total_memory' => self::format_bytes($total_memory),
            'peak_memory' => self::format_bytes(memory_get_peak_usage(true)),
            'operations_breakdown' => $operations_count
        ];
        
        // Loguj tylko jeśli są jakieś problemy wydajnościowe
        if ($total_time > 5.0 || count(self::$measurements) > 50) {
            WPMZF_Logger::info('Performance summary', $summary);
        }
    }

    /**
     * Pobiera dane wydajności
     *
     * @param string $operation Filtr operacji
     * @param int $limit Limit wyników
     * @return array
     */
    public static function get_performance_data($operation = null, $limit = 100) {
        $measurements = self::$measurements;
        
        if ($operation) {
            $measurements = array_filter($measurements, function($m) use ($operation) {
                return $m['operation'] === $operation;
            });
        }
        
        // Sortuj po czasie (najnowsze pierwsze)
        usort($measurements, function($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });
        
        return array_slice($measurements, 0, $limit);
    }

    /**
     * Pobiera statystyki wydajności
     *
     * @return array
     */
    public static function get_performance_stats() {
        if (empty(self::$measurements)) {
            return [
                'total_operations' => 0,
                'average_duration' => 0,
                'slowest_operation' => null,
                'current_memory' => self::format_bytes(memory_get_usage(true)),
                'peak_memory' => self::format_bytes(memory_get_peak_usage(true))
            ];
        }
        
        $durations = array_column(self::$measurements, 'duration');
        $slowest = array_reduce(self::$measurements, function($carry, $item) {
            return (!$carry || $item['duration'] > $carry['duration']) ? $item : $carry;
        });
        
        return [
            'total_operations' => count(self::$measurements),
            'average_duration' => array_sum($durations) / count($durations),
            'min_duration' => min($durations),
            'max_duration' => max($durations),
            'slowest_operation' => $slowest,
            'current_memory' => self::format_bytes(memory_get_usage(true)),
            'peak_memory' => self::format_bytes(memory_get_peak_usage(true))
        ];
    }

    /**
     * Eksportuje dane wydajności do analizy
     *
     * @return string JSON z danymi
     */
    public static function export_performance_data() {
        $data = [
            'export_timestamp' => time(),
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'memory_limit' => self::format_bytes(self::get_memory_limit()),
            'measurements' => self::$measurements,
            'stats' => self::get_performance_stats()
        ];
        
        return json_encode($data, JSON_PRETTY_PRINT);
    }

    /**
     * Resetuje dane pomiarów
     */
    public static function reset_measurements() {
        self::$measurements = [];
        self::$active_timers = [];
        
        WPMZF_Logger::info('Performance measurements reset');
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
     * Pobiera istotne części backtrace
     *
     * @return array
     */
    private static function get_relevant_backtrace() {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        $relevant = [];
        
        foreach ($backtrace as $trace) {
            if (isset($trace['file']) && strpos($trace['file'], 'moje-zarzadzanie-firma') !== false) {
                $relevant[] = [
                    'file' => basename($trace['file']),
                    'line' => $trace['line'] ?? 'unknown',
                    'function' => $trace['function'] ?? 'unknown'
                ];
            }
        }
        
        return $relevant;
    }

    /**
     * Pobiera konfigurację alertów
     *
     * @return array
     */
    public static function get_alert_thresholds() {
        return self::$alert_thresholds;
    }

    /**
     * Ustawia próg alertu
     *
     * @param string $type Typ alertu
     * @param float $threshold Próg
     */
    public static function set_alert_threshold($type, $threshold) {
        if (isset(self::$alert_thresholds[$type])) {
            self::$alert_thresholds[$type] = $threshold;
            WPMZF_Logger::info('Alert threshold updated', ['type' => $type, 'threshold' => $threshold]);
        }
    }
}
