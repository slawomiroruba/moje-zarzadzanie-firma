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
        
        add_action('wp_ajax_wpmzf_start_timer', array($this, 'start_timer'));
        add_action('wp_ajax_wpmzf_stop_timer', array($this, 'stop_timer'));
        add_action('wp_ajax_wpmzf_get_timer_status', array($this, 'get_timer_status'));
        add_action('wp_ajax_wpmzf_save_time_entry', array($this, 'save_time_entry'));
    }

    /**
     * Rozpoczyna licznik czasu
     */
    public function start_timer() {
        $timer_id = $this->performance_monitor->start_timer('time_tracking_start_timer');
        
        try {
            // // Rate limiting
            // if (!$this->rate_limiter->check_rate_limit('start_timer', 10, 60)) {
            //     WPMZF_Logger::log_security_violation('Timer start rate limit exceeded', get_current_user_id());
            //     wp_send_json_error(__('Too many timer starts. Please wait a moment.', 'wpmzf'));
            // }

            check_ajax_referer('wpmzf_nonce', 'nonce');

            $project_id = intval($_POST['project_id'] ?? 0);
            $description = sanitize_textarea_field($_POST['description'] ?? '');
            $user_id = get_current_user_id();

            // Walidacja danych
            if (!$project_id || !$user_id) {
                wp_send_json_error(__('Invalid parameters', 'wpmzf'));
            }

            // Sprawdź czy projekt istnieje i użytkownik ma do niego dostęp
            if (!get_post($project_id) || get_post_type($project_id) !== 'project') {
                WPMZF_Logger::log_security_violation('Attempt to start timer for invalid project', $user_id, ['project_id' => $project_id]);
                wp_send_json_error(__('Invalid project', 'wpmzf'));
            }

            // Sprawdź uprawnienia do projektu
            if (!current_user_can('edit_post', $project_id)) {
                WPMZF_Logger::log_security_violation('Attempt to start timer without project permissions', $user_id, ['project_id' => $project_id]);
                wp_send_json_error(__('No permission to track time for this project', 'wpmzf'));
            }

            // Zatrzymaj wszystkie aktywne timery dla tego użytkownika
            $this->stop_all_timers($user_id);

            // Rozpocznij nowy timer
            $timer_data = array(
                'project_id' => $project_id,
                'start_time' => current_time('timestamp'),
                'description' => $description
            );

            update_user_meta($user_id, 'wpmzf_active_timer', $timer_data);

            // Wyczyść cache
            $this->cache_manager->delete("user_timer_status_{$user_id}");
            $this->cache_manager->delete_pattern("time_entries_*");

            WPMZF_Logger::info('Timer started', ['user_id' => $user_id, 'project_id' => $project_id]);

            $this->performance_monitor->end_timer($timer_id);

            wp_send_json_success(array(
                'message' => __('Timer started', 'wpmzf'),
                'timer_data' => $timer_data
            ));
            
        } catch (Exception $e) {
            $this->performance_monitor->end_timer($timer_id);
            WPMZF_Logger::error('Error starting timer', ['error' => $e->getMessage(), 'user_id' => get_current_user_id()]);
            wp_send_json_error(__('Error starting timer', 'wpmzf'));
        }
    }

    /**
     * Zatrzymuje licznik czasu
     */
    public function stop_timer() {
        $timer_id = $this->performance_monitor->start_timer('time_tracking_stop_timer');
        
        try {
            // Rate limiting
            // if (!$this->rate_limiter->check_rate_limit('stop_timer', 10, 60)) {
            //     WPMZF_Logger::log_security_violation('Timer stop rate limit exceeded', get_current_user_id());
            //     wp_send_json_error(__('Too many timer stops. Please wait a moment.', 'wpmzf'));
            // }

            check_ajax_referer('wpmzf_nonce', 'nonce');

            $user_id = get_current_user_id();
            $timer_data = get_user_meta($user_id, 'wpmzf_active_timer', true);

            if (!$timer_data || !is_array($timer_data)) {
                wp_send_json_error(__('No active timer', 'wpmzf'));
            }

            // Walidacja danych timera
            if (empty($timer_data['project_id']) || empty($timer_data['start_time'])) {
                WPMZF_Logger::error('Invalid timer data', ['user_id' => $user_id, 'timer_data' => $timer_data]);
                wp_send_json_error(__('Invalid timer data', 'wpmzf'));
            }

            $end_time = current_time('timestamp');
            $duration = $end_time - intval($timer_data['start_time']);
            
            // Walidacja czasu - minimum 1 minuta, maksimum 24 godziny
            if ($duration < 60) {
                wp_send_json_error(__('Timer must run for at least 1 minute', 'wpmzf'));
            }
            
            if ($duration > 86400) { // 24 godziny
                WPMZF_Logger::warning('Very long timer duration', ['user_id' => $user_id, 'duration' => $duration]);
            }
            
            $duration_minutes = round($duration / 60);

            // Zapisz wpis czasu
            $time_entry = new WPMZF_Time_Entry();
            $time_entry->project_id = intval($timer_data['project_id']);
            $time_entry->user_id = $user_id;
            $time_entry->description = sanitize_textarea_field($timer_data['description'] ?? '');
            $time_entry->time_minutes = $duration_minutes;
            $time_entry->date = current_time('Y-m-d');
            
            if (!$time_entry->save()) {
                WPMZF_Logger::error('Failed to save time entry', ['user_id' => $user_id, 'timer_data' => $timer_data]);
                wp_send_json_error(__('Failed to save time entry', 'wpmzf'));
            }

            // Usuń aktywny timer
            delete_user_meta($user_id, 'wpmzf_active_timer');

            // Wyczyść cache
            $this->cache_manager->delete("user_timer_status_{$user_id}");
            $this->cache_manager->delete_pattern("time_entries_*");

            WPMZF_Logger::info('Timer stopped', ['user_id' => $user_id, 'project_id' => $timer_data['project_id'], 'duration_minutes' => $duration_minutes]);

            $this->performance_monitor->end_timer($timer_id);

            wp_send_json_success(array(
                'message' => __('Timer stopped', 'wpmzf'),
                'duration' => $duration_minutes,
                'entry_id' => $time_entry->id
            ));
            
        } catch (Exception $e) {
            $this->performance_monitor->end_timer($timer_id);
            WPMZF_Logger::error('Error stopping timer', ['error' => $e->getMessage(), 'user_id' => get_current_user_id()]);
            wp_send_json_error(__('Error stopping timer', 'wpmzf'));
        }
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
