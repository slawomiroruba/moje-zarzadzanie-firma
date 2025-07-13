<?php

/**
 * Usługa generowania raportów
 *
 * @package WPMZF
 * @subpackage Services
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPMZF_Reports {

    /**
     * Cache manager
     */
    private $cache_manager;

    /**
     * Rate limiter
     */
    private $rate_limiter;

    /**
     * Performance monitor
     */
    private $performance_monitor;

    /**
     * Konstruktor
     */
    public function __construct() {
        $this->cache_manager = new WPMZF_Cache_Manager();
        $this->rate_limiter = new WPMZF_Rate_Limiter();
        $this->performance_monitor = new WPMZF_Performance_Monitor();
        
        add_action('wp_ajax_wpmzf_generate_report', array($this, 'generate_report'));
        add_action('wp_ajax_wpmzf_export_report', array($this, 'export_report'));
    }

    /**
     * Generuje raport
     */
    public function generate_report() {
        $timer_id = $this->performance_monitor->start_timer('reports_generate_report');
        
        try {
            // Rate limiting dla generowania raportów
            if (!$this->rate_limiter->check_rate_limit('generate_report', 5, 60)) {
                WPMZF_Logger::log_security_violation('Report generation rate limit exceeded', get_current_user_id());
                wp_send_json_error(__('Too many report requests. Please wait a moment.', 'wpmzf'));
            }

            check_ajax_referer('wpmzf_nonce', 'nonce');

            $report_type = sanitize_text_field($_POST['report_type'] ?? '');
            $date_from = sanitize_text_field($_POST['date_from'] ?? '');
            $date_to = sanitize_text_field($_POST['date_to'] ?? '');

            // Walidacja parametrów
            if (empty($report_type)) {
                wp_send_json_error(__('Report type is required', 'wpmzf'));
            }

            if (!in_array($report_type, ['time_summary', 'project_summary', 'user_summary'])) {
                WPMZF_Logger::log_security_violation('Invalid report type requested', get_current_user_id(), ['report_type' => $report_type]);
                wp_send_json_error(__('Invalid report type', 'wpmzf'));
            }

            // Walidacja dat
            if (!empty($date_from) && !$this->validate_date($date_from)) {
                wp_send_json_error(__('Invalid start date format', 'wpmzf'));
            }

            if (!empty($date_to) && !$this->validate_date($date_to)) {
                wp_send_json_error(__('Invalid end date format', 'wpmzf'));
            }

            // Domyślne daty jeśli nie podano
            if (empty($date_from)) {
                $date_from = date('Y-m-01'); // Pierwszy dzień miesiąca
            }
            if (empty($date_to)) {
                $date_to = date('Y-m-d'); // Dzisiaj
            }

            // Sprawdź cache
            $cache_key = "report_{$report_type}_{$date_from}_{$date_to}_" . get_current_user_id();
            $cached_result = $this->cache_manager->get($cache_key);
            if ($cached_result !== false) {
                $this->performance_monitor->end_timer($timer_id);
                wp_send_json_success($cached_result);
                return;
            }

            switch ($report_type) {
                case 'time_summary':
                    $data = $this->get_time_summary_report($date_from, $date_to);
                    break;
                case 'project_summary':
                    $data = $this->get_project_summary_report($date_from, $date_to);
                    break;
                case 'user_summary':
                    $data = $this->get_user_summary_report($date_from, $date_to);
                    break;
                default:
                    wp_send_json_error(__('Invalid report type', 'wpmzf'));
            }

            // Cache wynik na 30 minut
            $this->cache_manager->set($cache_key, $data, 1800);

            WPMZF_Logger::info('Report generated', ['type' => $report_type, 'date_from' => $date_from, 'date_to' => $date_to, 'user_id' => get_current_user_id()]);

            $this->performance_monitor->end_timer($timer_id);

            wp_send_json_success($data);
            
        } catch (Exception $e) {
            $this->performance_monitor->end_timer($timer_id);
            WPMZF_Logger::error('Error generating report', ['error' => $e->getMessage(), 'user_id' => get_current_user_id()]);
            wp_send_json_error(__('Error generating report', 'wpmzf'));
        }
    }

    /**
     * Eksportuje raport
     */
    public function export_report() {
        check_ajax_referer('wpmzf_nonce', 'nonce');

        $report_type = sanitize_text_field($_POST['report_type']);
        $date_from = sanitize_text_field($_POST['date_from']);
        $date_to = sanitize_text_field($_POST['date_to']);
        $format = sanitize_text_field($_POST['format']);

        switch ($report_type) {
            case 'time_summary':
                $data = $this->get_time_summary_report($date_from, $date_to);
                break;
            case 'project_summary':
                $data = $this->get_project_summary_report($date_from, $date_to);
                break;
            case 'user_summary':
                $data = $this->get_user_summary_report($date_from, $date_to);
                break;
            default:
                wp_send_json_error('Invalid report type');
        }

        if ($format === 'csv') {
            $this->export_to_csv($data, $report_type);
        } elseif ($format === 'pdf') {
            $this->export_to_pdf($data, $report_type);
        } else {
            wp_send_json_error('Invalid format');
        }
    }

    /**
     * Raport podsumowania czasu
     */
    public function get_time_summary_report($date_from, $date_to) {
        $args = array(
            'meta_query' => array(
                array(
                    'key' => 'date',
                    'value' => array($date_from, $date_to),
                    'compare' => 'BETWEEN',
                    'type' => 'DATE'
                )
            )
        );

        $time_entries = WPMZF_Time_Entry::get_time_entries($args);
        
        $summary = array();
        $total_minutes = 0;
        
        foreach ($time_entries as $entry) {
            $project = $entry->get_project();
            $user = $entry->get_user();
            
            if (!$project || !$user) continue;
            
            $key = $project->id . '_' . $user->ID;
            
            if (!isset($summary[$key])) {
                $summary[$key] = array(
                    'project_name' => $project->name,
                    'user_name' => $user->display_name,
                    'total_minutes' => 0,
                    'entries_count' => 0
                );
            }
            
            $summary[$key]['total_minutes'] += $entry->time_minutes;
            $summary[$key]['entries_count']++;
            $total_minutes += $entry->time_minutes;
        }
        
        // Konwersja minut na godziny
        foreach ($summary as &$item) {
            $item['total_hours'] = round($item['total_minutes'] / 60, 2);
        }
        
        return array(
            'summary' => array_values($summary),
            'total_hours' => round($total_minutes / 60, 2),
            'total_minutes' => $total_minutes,
            'date_from' => $date_from,
            'date_to' => $date_to
        );
    }

    /**
     * Raport podsumowania projektów
     */
    public function get_project_summary_report($date_from, $date_to) {
        $projects = WPMZF_Project::get_projects();
        $summary = array();
        
        foreach ($projects as $project) {
            $args = array(
                'meta_query' => array(
                    array(
                        'key' => 'project_id',
                        'value' => $project->id,
                        'compare' => '='
                    ),
                    array(
                        'key' => 'date',
                        'value' => array($date_from, $date_to),
                        'compare' => 'BETWEEN',
                        'type' => 'DATE'
                    )
                )
            );
            
            $time_entries = WPMZF_Time_Entry::get_time_entries($args);
            $total_minutes = 0;
            
            foreach ($time_entries as $entry) {
                $total_minutes += $entry->time_minutes;
            }
            
            if ($total_minutes > 0) {
                $summary[] = array(
                    'project_id' => $project->id,
                    'project_name' => $project->name,
                    'total_minutes' => $total_minutes,
                    'total_hours' => round($total_minutes / 60, 2),
                    'entries_count' => count($time_entries),
                    'budget' => $project->budget,
                    'status' => $project->status
                );
            }
        }
        
        return array(
            'projects' => $summary,
            'date_from' => $date_from,
            'date_to' => $date_to
        );
    }

    /**
     * Raport podsumowania użytkowników
     */
    public function get_user_summary_report($date_from, $date_to) {
        $users = get_users();
        $summary = array();
        
        foreach ($users as $user) {
            $args = array(
                'meta_query' => array(
                    array(
                        'key' => 'user_id',
                        'value' => $user->ID,
                        'compare' => '='
                    ),
                    array(
                        'key' => 'date',
                        'value' => array($date_from, $date_to),
                        'compare' => 'BETWEEN',
                        'type' => 'DATE'
                    )
                )
            );
            
            $time_entries = WPMZF_Time_Entry::get_time_entries($args);
            $total_minutes = 0;
            
            foreach ($time_entries as $entry) {
                $total_minutes += $entry->time_minutes;
            }
            
            if ($total_minutes > 0) {
                $summary[] = array(
                    'user_id' => $user->ID,
                    'user_name' => $user->display_name,
                    'total_minutes' => $total_minutes,
                    'total_hours' => round($total_minutes / 60, 2),
                    'entries_count' => count($time_entries)
                );
            }
        }
        
        return array(
            'users' => $summary,
            'date_from' => $date_from,
            'date_to' => $date_to
        );
    }

    /**
     * Eksportuje dane do CSV
     */
    private function export_to_csv($data, $report_type) {
        $filename = $report_type . '_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // Nagłówki kolumn w zależności od typu raportu
        switch ($report_type) {
            case 'time_summary':
                fputcsv($output, array('Projekt', 'Użytkownik', 'Godziny', 'Liczba wpisów'));
                foreach ($data['summary'] as $item) {
                    fputcsv($output, array(
                        $item['project_name'],
                        $item['user_name'],
                        $item['total_hours'],
                        $item['entries_count']
                    ));
                }
                break;
            case 'project_summary':
                fputcsv($output, array('Projekt', 'Godziny', 'Budżet', 'Status', 'Liczba wpisów'));
                foreach ($data['projects'] as $item) {
                    fputcsv($output, array(
                        $item['project_name'],
                        $item['total_hours'],
                        $item['budget'],
                        $item['status'],
                        $item['entries_count']
                    ));
                }
                break;
            case 'user_summary':
                fputcsv($output, array('Użytkownik', 'Godziny', 'Liczba wpisów'));
                foreach ($data['users'] as $item) {
                    fputcsv($output, array(
                        $item['user_name'],
                        $item['total_hours'],
                        $item['entries_count']
                    ));
                }
                break;
        }
        
        fclose($output);
        exit;
    }

    /**
     * Eksportuje dane do PDF
     */
    private function export_to_pdf($data, $report_type) {
        // Podstawowa implementacja PDF - można rozszerzyć o bibliotekę jak TCPDF
        $filename = $report_type . '_' . date('Y-m-d_H-i-s') . '.html';
        
        header('Content-Type: text/html');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        echo '<html><head><title>Raport ' . $report_type . '</title></head><body>';
        echo '<h1>Raport ' . $report_type . '</h1>';
        echo '<p>Data od: ' . $data['date_from'] . ' do: ' . $data['date_to'] . '</p>';
        
        // Zawartość w zależności od typu raportu
        switch ($report_type) {
            case 'time_summary':
                echo '<table border="1"><tr><th>Projekt</th><th>Użytkownik</th><th>Godziny</th><th>Liczba wpisów</th></tr>';
                foreach ($data['summary'] as $item) {
                    echo '<tr>';
                    echo '<td>' . $item['project_name'] . '</td>';
                    echo '<td>' . $item['user_name'] . '</td>';
                    echo '<td>' . $item['total_hours'] . '</td>';
                    echo '<td>' . $item['entries_count'] . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
                break;
        }
        
        echo '</body></html>';
        exit;
    }

    /**
     * Waliduje format daty
     * 
     * @param string $date Data w formacie Y-m-d
     * @return bool
     */
    private function validate_date($date) {
        if (empty($date)) {
            return false;
        }
        
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }

    /**
     * Czyści cache raportów
     */
    public function clear_reports_cache() {
        $this->cache_manager->delete_pattern('report_*');
        WPMZF_Logger::info('Reports cache cleared', ['user_id' => get_current_user_id()]);
    }

    /**
     * Pobiera statystyki raportów
     * 
     * @return array
     */
    public function get_reports_stats() {
        $timer_id = $this->performance_monitor->start_timer('reports_get_stats');
        
        try {
            $cache_key = 'reports_stats_' . get_current_user_id();
            $cached_result = $this->cache_manager->get($cache_key);
            if ($cached_result !== false) {
                $this->performance_monitor->end_timer($timer_id);
                return $cached_result;
            }

            global $wpdb;
            
            // Pobierz podstawowe statystyki
            $stats = [
                'total_time_entries' => 0,
                'total_hours' => 0,
                'total_projects' => 0,
                'total_users' => 0,
                'avg_hours_per_day' => 0,
                'most_active_project' => null,
                'most_active_user' => null
            ];

            // Liczba wpisów czasu
            $table_name = $wpdb->prefix . 'wpmzf_time_entries';
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
                $stats['total_time_entries'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
                $stats['total_hours'] = (float) $wpdb->get_var("SELECT SUM(time_minutes) / 60 FROM $table_name");
                
                // Średnia godzin na dzień (ostatnie 30 dni)
                $thirty_days_ago = date('Y-m-d', strtotime('-30 days'));
                $avg_minutes = $wpdb->get_var($wpdb->prepare(
                    "SELECT AVG(daily_minutes) FROM (
                        SELECT SUM(time_minutes) as daily_minutes 
                        FROM $table_name 
                        WHERE date >= %s 
                        GROUP BY date
                    ) as daily_totals",
                    $thirty_days_ago
                ));
                $stats['avg_hours_per_day'] = $avg_minutes ? round($avg_minutes / 60, 2) : 0;
                
                // Najbardziej aktywny projekt
                $most_active_project = $wpdb->get_row(
                    "SELECT project_id, SUM(time_minutes) as total_minutes, COUNT(*) as entries_count 
                     FROM $table_name 
                     GROUP BY project_id 
                     ORDER BY total_minutes DESC 
                     LIMIT 1"
                );
                
                if ($most_active_project) {
                    $project = get_post($most_active_project->project_id);
                    $stats['most_active_project'] = [
                        'id' => $most_active_project->project_id,
                        'name' => $project ? $project->post_title : 'Unknown',
                        'total_hours' => round($most_active_project->total_minutes / 60, 2),
                        'entries_count' => (int) $most_active_project->entries_count
                    ];
                }
                
                // Najbardziej aktywny użytkownik
                $most_active_user = $wpdb->get_row(
                    "SELECT user_id, SUM(time_minutes) as total_minutes, COUNT(*) as entries_count 
                     FROM $table_name 
                     GROUP BY user_id 
                     ORDER BY total_minutes DESC 
                     LIMIT 1"
                );
                
                if ($most_active_user) {
                    $user = get_userdata($most_active_user->user_id);
                    $stats['most_active_user'] = [
                        'id' => $most_active_user->user_id,
                        'name' => $user ? $user->display_name : 'Unknown',
                        'total_hours' => round($most_active_user->total_minutes / 60, 2),
                        'entries_count' => (int) $most_active_user->entries_count
                    ];
                }
            }

            // Liczba projektów
            $stats['total_projects'] = wp_count_posts('project')->publish;
            
            // Liczba użytkowników z wpisami czasu
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
                $stats['total_users'] = (int) $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM $table_name");
            }

            // Cache na 1 godzinę
            $this->cache_manager->set($cache_key, $stats, 3600);
            
            $this->performance_monitor->end_timer($timer_id);
            
            return $stats;
            
        } catch (Exception $e) {
            $this->performance_monitor->end_timer($timer_id);
            WPMZF_Logger::error('Error getting reports stats', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Sprawdza uprawnienia do raportów
     * 
     * @param string $report_type Typ raportu
     * @return bool
     */
    private function check_report_permissions($report_type) {
        $user_id = get_current_user_id();
        
        // Administratorzy mają dostęp do wszystkich raportów
        if (current_user_can('manage_options')) {
            return true;
        }
        
        // Sprawdź uprawnienia dla konkretnych typów raportów
        switch ($report_type) {
            case 'time_summary':
                return current_user_can('edit_posts'); // Podstawowe uprawnienie do edycji
                
            case 'project_summary':
                return current_user_can('edit_posts');
                
            case 'user_summary':
                return current_user_can('edit_users'); // Wyższe uprawnienie dla raportów użytkowników
                
            default:
                return false;
        }
    }

    /**
     * Optymalizuje zapytanie dla dużych zbiorów danych
     * 
     * @param string $date_from Data od
     * @param string $date_to Data do
     * @return array Parametry optymalizacji
     */
    private function get_query_optimization_params($date_from, $date_to) {
        $days_diff = (strtotime($date_to) - strtotime($date_from)) / (60 * 60 * 24);
        
        $params = [
            'use_index' => true,
            'limit_results' => false,
            'group_by_day' => false
        ];
        
        // Dla długich okresów, grupuj według dni
        if ($days_diff > 90) {
            $params['group_by_day'] = true;
        }
        
        // Dla bardzo długich okresów, ogranicz wyniki
        if ($days_diff > 365) {
            $params['limit_results'] = 1000;
            WPMZF_Logger::warning('Large date range in report', ['days' => $days_diff, 'user_id' => get_current_user_id()]);
        }
        
        return $params;
    }
}
