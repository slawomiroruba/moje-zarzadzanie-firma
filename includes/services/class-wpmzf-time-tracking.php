<?php

/**
 * Usługa śledzenia czasu pracy
 *
 * @package WPMZF
 * @subpackage Services
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPMZF_Time_Tracking {

    /**
     * Konstruktor
     */
    public function __construct() {
        add_action('wp_ajax_wpmzf_start_timer', array($this, 'start_timer'));
        add_action('wp_ajax_wpmzf_stop_timer', array($this, 'stop_timer'));
        add_action('wp_ajax_wpmzf_get_timer_status', array($this, 'get_timer_status'));
        add_action('wp_ajax_wpmzf_save_time_entry', array($this, 'save_time_entry'));
    }

    /**
     * Rozpoczyna licznik czasu
     */
    public function start_timer() {
        check_ajax_referer('wpmzf_nonce', 'nonce');

        $project_id = intval($_POST['project_id']);
        $user_id = get_current_user_id();

        if (!$project_id || !$user_id) {
            wp_send_json_error('Invalid parameters');
        }

        // Zatrzymaj wszystkie aktywne timery dla tego użytkownika
        $this->stop_all_timers($user_id);

        // Rozpocznij nowy timer
        update_user_meta($user_id, 'wpmzf_active_timer', array(
            'project_id' => $project_id,
            'start_time' => current_time('timestamp'),
            'description' => sanitize_text_field($_POST['description'])
        ));

        wp_send_json_success(array(
            'message' => 'Timer started',
            'start_time' => current_time('timestamp')
        ));
    }

    /**
     * Zatrzymuje licznik czasu
     */
    public function stop_timer() {
        check_ajax_referer('wpmzf_nonce', 'nonce');

        $user_id = get_current_user_id();
        $timer_data = get_user_meta($user_id, 'wpmzf_active_timer', true);

        if (!$timer_data) {
            wp_send_json_error('No active timer');
        }

        $end_time = current_time('timestamp');
        $duration = $end_time - $timer_data['start_time'];
        $duration_minutes = round($duration / 60);

        // Zapisz wpis czasu
        $time_entry = new WPMZF_Time_Entry();
        $time_entry->project_id = $timer_data['project_id'];
        $time_entry->user_id = $user_id;
        $time_entry->description = $timer_data['description'];
        $time_entry->time_minutes = $duration_minutes;
        $time_entry->date = current_time('Y-m-d');
        $time_entry->save();

        // Usuń aktywny timer
        delete_user_meta($user_id, 'wpmzf_active_timer');

        wp_send_json_success(array(
            'message' => 'Timer stopped',
            'duration' => $duration_minutes,
            'entry_id' => $time_entry->id
        ));
    }

    /**
     * Pobiera status timera
     */
    public function get_timer_status() {
        check_ajax_referer('wpmzf_nonce', 'nonce');

        $user_id = get_current_user_id();
        $timer_data = get_user_meta($user_id, 'wpmzf_active_timer', true);

        if (!$timer_data) {
            wp_send_json_success(array('active' => false));
        }

        $current_time = current_time('timestamp');
        $elapsed = $current_time - $timer_data['start_time'];

        wp_send_json_success(array(
            'active' => true,
            'project_id' => $timer_data['project_id'],
            'start_time' => $timer_data['start_time'],
            'elapsed' => $elapsed,
            'description' => $timer_data['description']
        ));
    }

    /**
     * Zapisuje wpis czasu
     */
    public function save_time_entry() {
        check_ajax_referer('wpmzf_nonce', 'nonce');

        $project_id = intval($_POST['project_id']);
        $time_minutes = intval($_POST['time_minutes']);
        $description = sanitize_text_field($_POST['description']);
        $date = sanitize_text_field($_POST['date']);

        if (!$project_id || !$time_minutes) {
            wp_send_json_error('Invalid parameters');
        }

        $time_entry = new WPMZF_Time_Entry();
        $time_entry->project_id = $project_id;
        $time_entry->user_id = get_current_user_id();
        $time_entry->description = $description;
        $time_entry->time_minutes = $time_minutes;
        $time_entry->date = $date;
        $result = $time_entry->save();

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(array(
            'message' => 'Time entry saved',
            'entry_id' => $time_entry->id
        ));
    }

    /**
     * Zatrzymuje wszystkie timery dla użytkownika
     */
    private function stop_all_timers($user_id) {
        delete_user_meta($user_id, 'wpmzf_active_timer');
    }

    /**
     * Pobiera statystyki czasu dla projektu
     */
    public function get_project_time_stats($project_id) {
        $time_entries = WPMZF_Time_Entry::get_entries_by_project($project_id);
        
        $total_minutes = 0;
        $entries_count = 0;
        
        foreach ($time_entries as $entry) {
            $total_minutes += $entry->time_minutes;
            $entries_count++;
        }
        
        return array(
            'total_hours' => round($total_minutes / 60, 2),
            'total_minutes' => $total_minutes,
            'entries_count' => $entries_count
        );
    }

    /**
     * Pobiera statystyki czasu dla użytkownika
     */
    public function get_user_time_stats($user_id, $date_from = null, $date_to = null) {
        $args = array(
            'meta_query' => array(
                array(
                    'key' => 'user_id',
                    'value' => $user_id,
                    'compare' => '='
                )
            )
        );

        if ($date_from && $date_to) {
            $args['meta_query'][] = array(
                'key' => 'date',
                'value' => array($date_from, $date_to),
                'compare' => 'BETWEEN',
                'type' => 'DATE'
            );
        }

        $time_entries = WPMZF_Time_Entry::get_time_entries($args);
        
        $total_minutes = 0;
        $entries_count = 0;
        
        foreach ($time_entries as $entry) {
            $total_minutes += $entry->time_minutes;
            $entries_count++;
        }
        
        return array(
            'total_hours' => round($total_minutes / 60, 2),
            'total_minutes' => $total_minutes,
            'entries_count' => $entries_count
        );
    }
}
