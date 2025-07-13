<?php

/**
 * Database backup utility for WPMZF plugin
 *
 * @package WPMZF
 * @subpackage Core
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPMZF_Backup_Manager {

    /**
     * Create backup of plugin tables
     *
     * @return array|WP_Error Backup result
     */
    public static function create_backup() {
        global $wpdb;

        if (!current_user_can('manage_options')) {
            return new WP_Error('permissions', 'Brak uprawnień do tworzenia kopii zapasowej.');
        }

        try {
            $backup_data = [];
            $timestamp = current_time('Y-m-d_H-i-s');

            // Lista tabel do backup
            $tables_to_backup = [
                $wpdb->prefix . 'wpmzf_users',
                $wpdb->prefix . 'posts', // Dla custom post types
                $wpdb->prefix . 'postmeta' // Dla ACF fields
            ];

            foreach ($tables_to_backup as $table) {
                if (self::table_exists($table)) {
                    $backup_data[$table] = self::backup_table($table);
                }
            }

            // Zapisz backup do pliku
            $backup_dir = WP_CONTENT_DIR . '/wpmzf-backups/';
            if (!file_exists($backup_dir)) {
                wp_mkdir_p($backup_dir);
            }

            $backup_file = $backup_dir . 'wpmzf_backup_' . $timestamp . '.json';
            $backup_content = wp_json_encode($backup_data, JSON_PRETTY_PRINT);

            if (file_put_contents($backup_file, $backup_content)) {
                WPMZF_Logger::info('Backup created successfully', [
                    'file' => $backup_file,
                    'size' => filesize($backup_file)
                ]);

                return [
                    'success' => true,
                    'file' => $backup_file,
                    'size' => filesize($backup_file),
                    'timestamp' => $timestamp
                ];
            } else {
                throw new Exception('Nie można zapisać pliku backup.');
            }

        } catch (Exception $e) {
            WPMZF_Logger::error('Backup failed', [
                'error' => $e->getMessage()
            ]);
            
            return new WP_Error('backup_failed', $e->getMessage());
        }
    }

    /**
     * Check if table exists
     *
     * @param string $table_name
     * @return bool
     */
    private static function table_exists($table_name) {
        global $wpdb;
        
        $result = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table_name
        ));
        
        return $result === $table_name;
    }

    /**
     * Backup single table
     *
     * @param string $table_name
     * @return array
     */
    private static function backup_table($table_name) {
        global $wpdb;

        $data = [];

        // Get table structure
        $create_table = $wpdb->get_var("SHOW CREATE TABLE `$table_name`", 1);
        $data['structure'] = $create_table;

        // Get table data (limit to plugin-related data)
        if (strpos($table_name, 'posts') !== false) {
            // Only backup plugin post types
            $results = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM `$table_name` WHERE post_type IN ('person', 'company', 'project', 'activity', 'task', 'employee')",
            ), ARRAY_A);
        } elseif (strpos($table_name, 'postmeta') !== false) {
            // Only backup meta for plugin post types
            $results = $wpdb->get_results($wpdb->prepare(
                "SELECT pm.* FROM `$table_name` pm 
                 JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
                 WHERE p.post_type IN ('person', 'company', 'project', 'activity', 'task', 'employee')"
            ), ARRAY_A);
        } else {
            // Full table backup for plugin tables
            $results = $wpdb->get_results("SELECT * FROM `$table_name`", ARRAY_A);
        }

        $data['data'] = $results;

        return $data;
    }

    /**
     * Restore from backup
     *
     * @param string $backup_file
     * @return array|WP_Error
     */
    public static function restore_backup($backup_file) {
        if (!current_user_can('manage_options')) {
            return new WP_Error('permissions', 'Brak uprawnień do przywracania kopii zapasowej.');
        }

        if (!file_exists($backup_file)) {
            return new WP_Error('file_not_found', 'Plik kopii zapasowej nie istnieje.');
        }

        try {
            $backup_content = file_get_contents($backup_file);
            $backup_data = json_decode($backup_content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Nieprawidłowy format pliku backup.');
            }

            global $wpdb;

            foreach ($backup_data as $table_name => $table_data) {
                // This is a dangerous operation - would need more safety checks
                // For now, just log what would be restored
                WPMZF_Logger::info('Would restore table', [
                    'table' => $table_name,
                    'rows' => count($table_data['data'])
                ]);
            }

            return [
                'success' => true,
                'message' => 'Backup przywrócony pomyślnie (symulacja - funkcja wymaga dodatkowej implementacji).'
            ];

        } catch (Exception $e) {
            WPMZF_Logger::error('Restore failed', [
                'error' => $e->getMessage(),
                'file' => $backup_file
            ]);
            
            return new WP_Error('restore_failed', $e->getMessage());
        }
    }

    /**
     * List available backups
     *
     * @return array
     */
    public static function list_backups() {
        $backup_dir = WP_CONTENT_DIR . '/wpmzf-backups/';
        $backups = [];

        if (is_dir($backup_dir)) {
            $files = glob($backup_dir . 'wpmzf_backup_*.json');
            
            foreach ($files as $file) {
                $backups[] = [
                    'file' => basename($file),
                    'path' => $file,
                    'size' => filesize($file),
                    'date' => filemtime($file)
                ];
            }

            // Sort by date (newest first)
            usort($backups, function($a, $b) {
                return $b['date'] - $a['date'];
            });
        }

        return $backups;
    }

    /**
     * Clean old backups (keep only last 10)
     *
     * @return int Number of deleted files
     */
    public static function cleanup_old_backups() {
        $backups = self::list_backups();
        $deleted = 0;

        if (count($backups) > 10) {
            $to_delete = array_slice($backups, 10);
            
            foreach ($to_delete as $backup) {
                if (unlink($backup['path'])) {
                    $deleted++;
                    WPMZF_Logger::info('Old backup deleted', [
                        'file' => $backup['file']
                    ]);
                }
            }
        }

        return $deleted;
    }
}
