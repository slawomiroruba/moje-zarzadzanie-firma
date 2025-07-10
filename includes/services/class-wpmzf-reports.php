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
     * Konstruktor
     */
    public function __construct() {
        add_action('wp_ajax_wpmzf_generate_report', array($this, 'generate_report'));
        add_action('wp_ajax_wpmzf_export_report', array($this, 'export_report'));
    }

    /**
     * Generuje raport
     */
    public function generate_report() {
        check_ajax_referer('wpmzf_nonce', 'nonce');

        $report_type = sanitize_text_field($_POST['report_type']);
        $date_from = sanitize_text_field($_POST['date_from']);
        $date_to = sanitize_text_field($_POST['date_to']);

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

        wp_send_json_success($data);
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
}
