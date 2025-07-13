<?php

/**
 * Manager zadań automatycznych (cron jobs)
 *
 * @package WPMZF
 * @subpackage Core
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPMZF_Cron_Manager {

    /**
     * Performance monitor
     */
    private $performance_monitor;

    /**
     * Backup manager
     */
    private $backup_manager;

    /**
     * Cache manager
     */
    private $cache_manager;

    /**
     * Database optimizer
     */
    private $database_optimizer;

    /**
     * Konstruktor
     */
    public function __construct() {
        $this->performance_monitor = new WPMZF_Performance_Monitor();
        $this->backup_manager = new WPMZF_Backup_Manager();
        $this->cache_manager = new WPMZF_Cache_Manager();
        $this->database_optimizer = new WPMZF_Database_Optimizer();
        
        // Rejestruj zadania cron
        add_action('init', array($this, 'register_cron_jobs'));
        
        // Rejestruj hooks dla zadań
        add_action('wpmzf_daily_maintenance', array($this, 'daily_maintenance'));
        add_action('wpmzf_weekly_cleanup', array($this, 'weekly_cleanup'));
        add_action('wpmzf_hourly_cache_cleanup', array($this, 'hourly_cache_cleanup'));
        add_action('wpmzf_backup_database', array($this, 'backup_database'));
        add_action('wpmzf_optimize_database', array($this, 'optimize_database'));
        add_action('wpmzf_performance_check', array($this, 'performance_check'));
    }

    /**
     * Rejestruje zadania cron
     */
    public function register_cron_jobs() {
        // Codzienne zadania konserwacyjne
        if (!wp_next_scheduled('wpmzf_daily_maintenance')) {
            wp_schedule_event(strtotime('02:00'), 'daily', 'wpmzf_daily_maintenance');
        }
        
        // Cotygodniowe czyszczenie
        if (!wp_next_scheduled('wpmzf_weekly_cleanup')) {
            wp_schedule_event(strtotime('Sunday 03:00'), 'weekly', 'wpmzf_weekly_cleanup');
        }
        
        // Godzinowe czyszczenie cache
        if (!wp_next_scheduled('wpmzf_hourly_cache_cleanup')) {
            wp_schedule_event(time(), 'hourly', 'wpmzf_hourly_cache_cleanup');
        }
        
        // Backup bazy danych (codziennie)
        if (!wp_next_scheduled('wpmzf_backup_database')) {
            wp_schedule_event(strtotime('01:00'), 'daily', 'wpmzf_backup_database');
        }
        
        // Optymalizacja bazy danych (co tydzień)
        if (!wp_next_scheduled('wpmzf_optimize_database')) {
            wp_schedule_event(strtotime('Sunday 04:00'), 'weekly', 'wpmzf_optimize_database');
        }
        
        // Sprawdzenie wydajności (co godzinę)
        if (!wp_next_scheduled('wpmzf_performance_check')) {
            wp_schedule_event(time(), 'hourly', 'wpmzf_performance_check');
        }
    }

    /**
     * Codzienne zadania konserwacyjne
     */
    public function daily_maintenance() {
        $timer_id = $this->performance_monitor->start_timer('cron_daily_maintenance');
        
        try {
            WPMZF_Logger::info('Starting daily maintenance');
            
            $maintenance_tasks = [];
            
            // 1. Wyczyść stare logi (starsze niż 30 dni)
            $old_logs_deleted = $this->cleanup_old_logs();
            $maintenance_tasks['old_logs_deleted'] = $old_logs_deleted;
            
            // 2. Wyczyść stare dane sesji
            $this->cleanup_old_sessions();
            $maintenance_tasks['sessions_cleaned'] = true;
            
            // 3. Wyczyść stare transients
            $this->cleanup_old_transients();
            $maintenance_tasks['transients_cleaned'] = true;
            
            // 4. Sprawdź rozmiar cache i wyczyść jeśli potrzeba
            $cache_size = $this->cache_manager->get_cache_size();
            if ($cache_size > 100) { // Więcej niż 100MB
                $this->cache_manager->clear_all();
                $maintenance_tasks['cache_cleared'] = true;
                WPMZF_Logger::info('Cache cleared due to size limit', ['size_mb' => $cache_size]);
            }
            
            // 5. Sprawdź dostępne miejsce na dysku
            $disk_space = $this->check_disk_space();
            $maintenance_tasks['disk_space_mb'] = $disk_space;
            
            if ($disk_space < 1000) { // Mniej niż 1GB
                WPMZF_Logger::warning('Low disk space detected', ['available_mb' => $disk_space]);
                $this->send_admin_notification('Low disk space warning', "Available disk space: {$disk_space}MB");
            }
            
            // 6. Sprawdź integrację zewnętrznych serwisów
            $this->check_external_services();
            $maintenance_tasks['external_services_checked'] = true;
            
            $this->performance_monitor->end_timer($timer_id);
            
            WPMZF_Logger::info('Daily maintenance completed', $maintenance_tasks);
            
        } catch (Exception $e) {
            $this->performance_monitor->end_timer($timer_id);
            WPMZF_Logger::error('Error in daily maintenance', ['error' => $e->getMessage()]);
            $this->send_admin_notification('Daily maintenance error', $e->getMessage());
        }
    }

    /**
     * Cotygodniowe zadania czyszczenia
     */
    public function weekly_cleanup() {
        $timer_id = $this->performance_monitor->start_timer('cron_weekly_cleanup');
        
        try {
            WPMZF_Logger::info('Starting weekly cleanup');
            
            $cleanup_tasks = [];
            
            // 1. Wyczyść stare backupy (starsze niż 30 dni)
            $old_backups_deleted = $this->backup_manager->cleanup_old_backups(30);
            $cleanup_tasks['old_backups_deleted'] = $old_backups_deleted;
            
            // 2. Wyczyść stare dane z bazy
            $database_cleanup = $this->database_optimizer->cleanup_old_data();
            $cleanup_tasks['database_cleanup'] = $database_cleanup;
            
            // 3. Wyczyść stare pliki tymczasowe
            $temp_files_deleted = $this->cleanup_temp_files();
            $cleanup_tasks['temp_files_deleted'] = $temp_files_deleted;
            
            // 4. Sprawdź i wyczyść nieużywane załączniki
            $unused_attachments = $this->cleanup_unused_attachments();
            $cleanup_tasks['unused_attachments_deleted'] = $unused_attachments;
            
            // 5. Kompresuj stare logi
            $this->compress_old_logs();
            $cleanup_tasks['logs_compressed'] = true;
            
            // 6. Sprawdź wydajność systemu
            $performance_report = $this->generate_performance_report();
            $cleanup_tasks['performance_report'] = $performance_report;
            
            $this->performance_monitor->end_timer($timer_id);
            
            WPMZF_Logger::info('Weekly cleanup completed', $cleanup_tasks);
            
            // Wyślij raport administratorowi
            $this->send_weekly_report($cleanup_tasks);
            
        } catch (Exception $e) {
            $this->performance_monitor->end_timer($timer_id);
            WPMZF_Logger::error('Error in weekly cleanup', ['error' => $e->getMessage()]);
            $this->send_admin_notification('Weekly cleanup error', $e->getMessage());
        }
    }

    /**
     * Godzinowe czyszczenie cache
     */
    public function hourly_cache_cleanup() {
        $timer_id = $this->performance_monitor->start_timer('cron_hourly_cache_cleanup');
        
        try {
            // Wyczyść wygasłe wpisy cache
            $expired_count = $this->cache_manager->cleanup_expired();
            
            // Sprawdź czy cache nie jest za duży
            $cache_size = $this->cache_manager->get_cache_size();
            
            if ($cache_size > 50) { // Więcej niż 50MB
                $this->cache_manager->cleanup_old_entries(3600); // Starsze niż godzina
                WPMZF_Logger::info('Cache cleaned due to size', ['size_mb' => $cache_size]);
            }
            
            $this->performance_monitor->end_timer($timer_id);
            
            WPMZF_Logger::debug('Hourly cache cleanup completed', [
                'expired_count' => $expired_count,
                'cache_size_mb' => $cache_size
            ]);
            
        } catch (Exception $e) {
            $this->performance_monitor->end_timer($timer_id);
            WPMZF_Logger::error('Error in hourly cache cleanup', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Backup bazy danych
     */
    public function backup_database() {
        $timer_id = $this->performance_monitor->start_timer('cron_backup_database');
        
        try {
            WPMZF_Logger::info('Starting automated database backup');
            
            $backup_result = $this->backup_manager->create_backup('automated_daily');
            
            if ($backup_result['success']) {
                WPMZF_Logger::info('Automated backup completed successfully', [
                    'backup_file' => $backup_result['file'],
                    'size_mb' => round($backup_result['size'] / 1024 / 1024, 2)
                ]);
            } else {
                WPMZF_Logger::error('Automated backup failed', ['error' => $backup_result['message']]);
                $this->send_admin_notification('Backup failed', $backup_result['message']);
            }
            
            $this->performance_monitor->end_timer($timer_id);
            
        } catch (Exception $e) {
            $this->performance_monitor->end_timer($timer_id);
            WPMZF_Logger::error('Error in automated backup', ['error' => $e->getMessage()]);
            $this->send_admin_notification('Backup error', $e->getMessage());
        }
    }

    /**
     * Optymalizacja bazy danych
     */
    public function optimize_database() {
        $timer_id = $this->performance_monitor->start_timer('cron_optimize_database');
        
        try {
            WPMZF_Logger::info('Starting automated database optimization');
            
            // Utwórz indeksy
            $indexes_result = $this->database_optimizer->create_database_indexes();
            
            // Optymalizuj tabele
            $optimize_result = $this->database_optimizer->optimize_tables();
            
            // Przeanalizuj wydajność
            $analysis = $this->database_optimizer->analyze_query_performance();
            
            $this->performance_monitor->end_timer($timer_id);
            
            WPMZF_Logger::info('Database optimization completed', [
                'indexes_created' => $indexes_result['success_count'],
                'tables_optimized' => count($optimize_result['optimized']),
                'analysis_completed' => !empty($analysis)
            ]);
            
        } catch (Exception $e) {
            $this->performance_monitor->end_timer($timer_id);
            WPMZF_Logger::error('Error in database optimization', ['error' => $e->getMessage()]);
            $this->send_admin_notification('Database optimization error', $e->getMessage());
        }
    }

    /**
     * Sprawdzenie wydajności
     */
    public function performance_check() {
        $timer_id = $this->performance_monitor->start_timer('cron_performance_check');
        
        try {
            $performance_data = $this->performance_monitor->get_performance_stats();
            
            // Sprawdź czy są długie operacje
            if (!empty($performance_data['slow_operations'])) {
                $slow_count = count($performance_data['slow_operations']);
                if ($slow_count > 10) { // Więcej niż 10 wolnych operacji w ostatniej godzinie
                    WPMZF_Logger::warning('High number of slow operations detected', [
                        'slow_operations_count' => $slow_count
                    ]);
                }
            }
            
            // Sprawdź zużycie pamięci
            if (!empty($performance_data['memory_usage']['peak_mb'])) {
                if ($performance_data['memory_usage']['peak_mb'] > 128) { // Więcej niż 128MB
                    WPMZF_Logger::warning('High memory usage detected', [
                        'peak_memory_mb' => $performance_data['memory_usage']['peak_mb']
                    ]);
                }
            }
            
            $this->performance_monitor->end_timer($timer_id);
            
        } catch (Exception $e) {
            $this->performance_monitor->end_timer($timer_id);
            WPMZF_Logger::error('Error in performance check', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Czyści stare logi
     */
    private function cleanup_old_logs() {
        $log_dir = WP_CONTENT_DIR . '/wpmzf-logs/';
        if (!is_dir($log_dir)) {
            return 0;
        }
        
        $deleted_count = 0;
        $cutoff_date = strtotime('-30 days');
        
        $files = glob($log_dir . '*.log');
        foreach ($files as $file) {
            if (filemtime($file) < $cutoff_date) {
                if (unlink($file)) {
                    $deleted_count++;
                }
            }
        }
        
        return $deleted_count;
    }

    /**
     * Czyści stare dane sesji
     */
    private function cleanup_old_sessions() {
        global $wpdb;
        
        // Usuń stare sesje (starsze niż 24 godziny)
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_timeout_%' 
             AND option_value < UNIX_TIMESTAMP()"
        );
        
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_%' 
             AND option_name NOT LIKE '_transient_timeout_%' 
             AND NOT EXISTS (
                 SELECT 1 FROM {$wpdb->options} o2 
                 WHERE o2.option_name = CONCAT('_transient_timeout_', SUBSTRING(o.option_name, 12))
             )"
        );
    }

    /**
     * Czyści stare transients
     */
    private function cleanup_old_transients() {
        global $wpdb;
        
        $wpdb->query(
            "DELETE a, b FROM {$wpdb->options} a, {$wpdb->options} b 
             WHERE a.option_name LIKE '_transient_%' 
             AND a.option_name NOT LIKE '_transient_timeout_%' 
             AND b.option_name = CONCAT('_transient_timeout_', SUBSTRING(a.option_name, 12)) 
             AND b.option_value < UNIX_TIMESTAMP()"
        );
    }

    /**
     * Sprawdza dostępne miejsce na dysku
     */
    private function check_disk_space() {
        $bytes = disk_free_space(ABSPATH);
        return $bytes ? round($bytes / 1024 / 1024) : 0; // Zwróć w MB
    }

    /**
     * Sprawdza zewnętrzne serwisy
     */
    private function check_external_services() {
        // Tu można dodać sprawdzanie dostępności zewnętrznych API
        // Na przykład sprawdzenie czy ACF jest dostępne
        if (!function_exists('get_field')) {
            WPMZF_Logger::warning('ACF plugin not available');
        }
    }

    /**
     * Czyści pliki tymczasowe
     */
    private function cleanup_temp_files() {
        $temp_dir = sys_get_temp_dir();
        $deleted_count = 0;
        $cutoff_date = strtotime('-1 day');
        
        $files = glob($temp_dir . '/wpmzf_*');
        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $cutoff_date) {
                if (unlink($file)) {
                    $deleted_count++;
                }
            }
        }
        
        return $deleted_count;
    }

    /**
     * Czyści nieużywane załączniki
     */
    private function cleanup_unused_attachments() {
        global $wpdb;
        
        // Znajdź załączniki które nie są używane w żadnym poście
        $unused_attachments = $wpdb->get_col(
            "SELECT ID FROM {$wpdb->posts} 
             WHERE post_type = 'attachment' 
             AND post_parent = 0 
             AND post_date < DATE_SUB(NOW(), INTERVAL 30 DAY)
             AND ID NOT IN (
                 SELECT DISTINCT meta_value 
                 FROM {$wpdb->postmeta} 
                 WHERE meta_key LIKE '%_thumbnail_id' 
                 OR meta_key LIKE '%image%'
                 OR meta_key LIKE '%file%'
             )"
        );
        
        $deleted_count = 0;
        foreach ($unused_attachments as $attachment_id) {
            if (wp_delete_attachment($attachment_id, true)) {
                $deleted_count++;
            }
        }
        
        return $deleted_count;
    }

    /**
     * Kompresuje stare logi
     */
    private function compress_old_logs() {
        $log_dir = WP_CONTENT_DIR . '/wpmzf-logs/';
        if (!is_dir($log_dir)) {
            return;
        }
        
        $cutoff_date = strtotime('-7 days');
        $files = glob($log_dir . '*.log');
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoff_date && !file_exists($file . '.gz')) {
                $data = file_get_contents($file);
                $compressed = gzencode($data, 9);
                
                if (file_put_contents($file . '.gz', $compressed)) {
                    unlink($file);
                }
            }
        }
    }

    /**
     * Generuje raport wydajności
     */
    private function generate_performance_report() {
        return [
            'timestamp' => current_time('mysql'),
            'memory_usage' => $this->performance_monitor->get_memory_usage(),
            'cache_stats' => $this->cache_manager->get_cache_stats(),
            'database_size' => $this->database_optimizer->get_table_sizes()
        ];
    }

    /**
     * Wysyła powiadomienie administratorowi
     */
    private function send_admin_notification($subject, $message) {
        $admin_email = get_option('admin_email');
        if ($admin_email) {
            wp_mail(
                $admin_email,
                '[WPMZF] ' . $subject,
                "Plugin WPMZF - Powiadomienie:\n\n" . $message . "\n\nData: " . current_time('mysql'),
                ['Content-Type: text/plain; charset=UTF-8']
            );
        }
    }

    /**
     * Wysyła cotygodniowy raport
     */
    private function send_weekly_report($cleanup_tasks) {
        $admin_email = get_option('admin_email');
        if (!$admin_email) {
            return;
        }
        
        $message = "Cotygodniowy raport WPMZF:\n\n";
        
        foreach ($cleanup_tasks as $task => $result) {
            $message .= "- " . ucfirst(str_replace('_', ' ', $task)) . ": " . 
                       (is_array($result) ? json_encode($result) : $result) . "\n";
        }
        
        $message .= "\nData raportu: " . current_time('mysql');
        
        wp_mail(
            $admin_email,
            '[WPMZF] Cotygodniowy raport systemu',
            $message,
            ['Content-Type: text/plain; charset=UTF-8']
        );
    }

    /**
     * Wyrejestrowuje zadania cron
     */
    public function unregister_cron_jobs() {
        $cron_jobs = [
            'wpmzf_daily_maintenance',
            'wpmzf_weekly_cleanup',
            'wpmzf_hourly_cache_cleanup',
            'wpmzf_backup_database',
            'wpmzf_optimize_database',
            'wpmzf_performance_check'
        ];
        
        foreach ($cron_jobs as $job) {
            $timestamp = wp_next_scheduled($job);
            if ($timestamp) {
                wp_unschedule_event($timestamp, $job);
            }
        }
        
        WPMZF_Logger::info('All cron jobs unregistered');
    }
}
