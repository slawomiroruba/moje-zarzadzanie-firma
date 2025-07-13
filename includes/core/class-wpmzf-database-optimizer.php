<?php

/**
 * Manager indeksów bazodanowych
 *
 * @package WPMZF
 * @subpackage Core
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPMZF_Database_Optimizer {

    /**
     * Performance monitor
     */
    private $performance_monitor;

    /**
     * Konstruktor
     */
    public function __construct() {
        $this->performance_monitor = new WPMZF_Performance_Monitor();
    }

    /**
     * Tworzy indeksy bazodanowe dla lepszej wydajności
     */
    public function create_database_indexes() {
        $timer_id = $this->performance_monitor->start_timer('database_optimizer_create_indexes');
        
        try {
            global $wpdb;
            
            $errors = [];
            $success_count = 0;
            
            // Indeksy dla tabeli postów WordPress
            $post_indexes = [
                'idx_post_type_status' => "CREATE INDEX idx_post_type_status ON {$wpdb->posts} (post_type, post_status)",
                'idx_post_date_type' => "CREATE INDEX idx_post_date_type ON {$wpdb->posts} (post_date, post_type)",
                'idx_post_modified' => "CREATE INDEX idx_post_modified ON {$wpdb->posts} (post_modified)",
                'idx_post_parent_type' => "CREATE INDEX idx_post_parent_type ON {$wpdb->posts} (post_parent, post_type)"
            ];
            
            foreach ($post_indexes as $name => $sql) {
                if (!$this->index_exists($wpdb->posts, $name)) {
                    $result = $wpdb->query($sql);
                    if ($result === false) {
                        $errors[] = "Failed to create index {$name}: " . $wpdb->last_error;
                        WPMZF_Logger::error("Failed to create index {$name}", ['error' => $wpdb->last_error]);
                    } else {
                        $success_count++;
                        WPMZF_Logger::info("Created index {$name}");
                    }
                }
            }
            
            // Indeksy dla tabeli meta postów
            $postmeta_indexes = [
                'idx_meta_key_value' => "CREATE INDEX idx_meta_key_value ON {$wpdb->postmeta} (meta_key, meta_value(191))",
                'idx_post_id_key' => "CREATE INDEX idx_post_id_key ON {$wpdb->postmeta} (post_id, meta_key)"
            ];
            
            foreach ($postmeta_indexes as $name => $sql) {
                if (!$this->index_exists($wpdb->postmeta, $name)) {
                    $result = $wpdb->query($sql);
                    if ($result === false) {
                        $errors[] = "Failed to create index {$name}: " . $wpdb->last_error;
                        WPMZF_Logger::error("Failed to create index {$name}", ['error' => $wpdb->last_error]);
                    } else {
                        $success_count++;
                        WPMZF_Logger::info("Created index {$name}");
                    }
                }
            }
            
            // Indeksy dla tabeli użytkowników WPMZF (jeśli istnieje)
            $users_table = $wpdb->prefix . 'wpmzf_users';
            if ($wpdb->get_var("SHOW TABLES LIKE '$users_table'") === $users_table) {
                $user_indexes = [
                    'idx_email' => "CREATE INDEX idx_email ON {$users_table} (email)",
                    'idx_created_at' => "CREATE INDEX idx_created_at ON {$users_table} (created_at)",
                    'idx_updated_at' => "CREATE INDEX idx_updated_at ON {$users_table} (updated_at)"
                ];
                
                foreach ($user_indexes as $name => $sql) {
                    if (!$this->index_exists($users_table, $name)) {
                        $result = $wpdb->query($sql);
                        if ($result === false) {
                            $errors[] = "Failed to create index {$name}: " . $wpdb->last_error;
                            WPMZF_Logger::error("Failed to create index {$name}", ['error' => $wpdb->last_error]);
                        } else {
                            $success_count++;
                            WPMZF_Logger::info("Created index {$name}");
                        }
                    }
                }
            }
            
            // Indeksy dla tabeli time entries (jeśli istnieje)
            $time_entries_table = $wpdb->prefix . 'wpmzf_time_entries';
            if ($wpdb->get_var("SHOW TABLES LIKE '$time_entries_table'") === $time_entries_table) {
                $time_indexes = [
                    'idx_project_user' => "CREATE INDEX idx_project_user ON {$time_entries_table} (project_id, user_id)",
                    'idx_date_user' => "CREATE INDEX idx_date_user ON {$time_entries_table} (date, user_id)",
                    'idx_project_date' => "CREATE INDEX idx_project_date ON {$time_entries_table} (project_id, date)",
                    'idx_created_at' => "CREATE INDEX idx_created_at ON {$time_entries_table} (created_at)"
                ];
                
                foreach ($time_indexes as $name => $sql) {
                    if (!$this->index_exists($time_entries_table, $name)) {
                        $result = $wpdb->query($sql);
                        if ($result === false) {
                            $errors[] = "Failed to create index {$name}: " . $wpdb->last_error;
                            WPMZF_Logger::error("Failed to create index {$name}", ['error' => $wpdb->last_error]);
                        } else {
                            $success_count++;
                            WPMZF_Logger::info("Created index {$name}");
                        }
                    }
                }
            }
            
            $this->performance_monitor->end_timer($timer_id);
            
            WPMZF_Logger::info('Database indexes creation completed', [
                'success_count' => $success_count,
                'errors_count' => count($errors)
            ]);
            
            return [
                'success' => count($errors) === 0,
                'success_count' => $success_count,
                'errors' => $errors
            ];
            
        } catch (Exception $e) {
            $this->performance_monitor->end_timer($timer_id);
            WPMZF_Logger::error('Error creating database indexes', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Sprawdza czy indeks istnieje
     * 
     * @param string $table_name Nazwa tabeli
     * @param string $index_name Nazwa indeksu
     * @return bool
     */
    private function index_exists($table_name, $index_name) {
        global $wpdb;
        
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
             WHERE TABLE_SCHEMA = %s 
             AND TABLE_NAME = %s 
             AND INDEX_NAME = %s",
            DB_NAME,
            $table_name,
            $index_name
        ));
        
        return $result > 0;
    }

    /**
     * Analizuje wydajność zapytań
     */
    public function analyze_query_performance() {
        $timer_id = $this->performance_monitor->start_timer('database_optimizer_analyze_performance');
        
        try {
            global $wpdb;
            
            $analysis = [];
            
            // Sprawdź slow query log (jeśli dostępny)
            $slow_queries = $this->get_slow_queries();
            if (!empty($slow_queries)) {
                $analysis['slow_queries'] = $slow_queries;
            }
            
            // Sprawdź rozmiary tabel
            $table_sizes = $this->get_table_sizes();
            $analysis['table_sizes'] = $table_sizes;
            
            // Sprawdź wykorzystanie indeksów
            $index_usage = $this->get_index_usage();
            $analysis['index_usage'] = $index_usage;
            
            // Sprawdź fragmentację tabel
            $fragmentation = $this->get_table_fragmentation();
            $analysis['fragmentation'] = $fragmentation;
            
            $this->performance_monitor->end_timer($timer_id);
            
            WPMZF_Logger::info('Database performance analysis completed', ['tables_analyzed' => count($table_sizes)]);
            
            return $analysis;
            
        } catch (Exception $e) {
            $this->performance_monitor->end_timer($timer_id);
            WPMZF_Logger::error('Error analyzing database performance', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Pobiera wolne zapytania
     */
    private function get_slow_queries() {
        global $wpdb;
        
        try {
            // Sprawdź czy slow query log jest włączony
            $slow_query_log = $wpdb->get_var("SHOW VARIABLES LIKE 'slow_query_log'");
            if (!$slow_query_log) {
                return [];
            }
            
            // Podstawowa analiza - możemy to rozszerzyć
            return [
                'status' => 'available',
                'recommendation' => 'Check slow query log file for detailed analysis'
            ];
            
        } catch (Exception $e) {
            WPMZF_Logger::warning('Could not access slow query log', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Pobiera rozmiary tabel
     */
    private function get_table_sizes() {
        global $wpdb;
        
        $query = "
            SELECT 
                TABLE_NAME as table_name,
                ROUND(((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024), 2) as size_mb,
                TABLE_ROWS as row_count
            FROM INFORMATION_SCHEMA.TABLES 
            WHERE TABLE_SCHEMA = %s 
            ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC
        ";
        
        $results = $wpdb->get_results($wpdb->prepare($query, DB_NAME));
        
        $sizes = [];
        foreach ($results as $row) {
            $sizes[$row->table_name] = [
                'size_mb' => $row->size_mb,
                'row_count' => $row->row_count
            ];
        }
        
        return $sizes;
    }

    /**
     * Pobiera informacje o wykorzystaniu indeksów
     */
    private function get_index_usage() {
        global $wpdb;
        
        $query = "
            SELECT 
                TABLE_NAME as table_name,
                INDEX_NAME as index_name,
                CARDINALITY as cardinality,
                NON_UNIQUE as non_unique
            FROM INFORMATION_SCHEMA.STATISTICS 
            WHERE TABLE_SCHEMA = %s 
            ORDER BY TABLE_NAME, CARDINALITY DESC
        ";
        
        $results = $wpdb->get_results($wpdb->prepare($query, DB_NAME));
        
        $usage = [];
        foreach ($results as $row) {
            if (!isset($usage[$row->table_name])) {
                $usage[$row->table_name] = [];
            }
            
            $usage[$row->table_name][$row->index_name] = [
                'cardinality' => $row->cardinality,
                'non_unique' => $row->non_unique
            ];
        }
        
        return $usage;
    }

    /**
     * Pobiera informacje o fragmentacji tabel
     */
    private function get_table_fragmentation() {
        global $wpdb;
        
        $query = "
            SELECT 
                TABLE_NAME as table_name,
                DATA_FREE as data_free_bytes,
                ROUND((DATA_FREE / 1024 / 1024), 2) as data_free_mb
            FROM INFORMATION_SCHEMA.TABLES 
            WHERE TABLE_SCHEMA = %s 
            AND DATA_FREE > 0
            ORDER BY DATA_FREE DESC
        ";
        
        $results = $wpdb->get_results($wpdb->prepare($query, DB_NAME));
        
        $fragmentation = [];
        foreach ($results as $row) {
            if ($row->data_free_mb > 1) { // Tylko jeśli fragmentacja > 1MB
                $fragmentation[$row->table_name] = [
                    'free_space_mb' => $row->data_free_mb,
                    'recommendation' => $row->data_free_mb > 10 ? 'Consider OPTIMIZE TABLE' : 'Monitor'
                ];
            }
        }
        
        return $fragmentation;
    }

    /**
     * Optymalizuje tabele
     */
    public function optimize_tables() {
        $timer_id = $this->performance_monitor->start_timer('database_optimizer_optimize_tables');
        
        try {
            global $wpdb;
            
            $optimized = [];
            $errors = [];
            
            // Lista tabel do optymalizacji
            $tables_to_optimize = [
                $wpdb->posts,
                $wpdb->postmeta,
                $wpdb->prefix . 'wpmzf_users',
                $wpdb->prefix . 'wpmzf_time_entries'
            ];
            
            foreach ($tables_to_optimize as $table) {
                // Sprawdź czy tabela istnieje
                if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
                    continue;
                }
                
                $result = $wpdb->query("OPTIMIZE TABLE `$table`");
                
                if ($result === false) {
                    $errors[] = "Failed to optimize table $table: " . $wpdb->last_error;
                    WPMZF_Logger::error("Failed to optimize table $table", ['error' => $wpdb->last_error]);
                } else {
                    $optimized[] = $table;
                    WPMZF_Logger::info("Optimized table $table");
                }
            }
            
            $this->performance_monitor->end_timer($timer_id);
            
            WPMZF_Logger::info('Database optimization completed', [
                'optimized_count' => count($optimized),
                'errors_count' => count($errors)
            ]);
            
            return [
                'success' => count($errors) === 0,
                'optimized' => $optimized,
                'errors' => $errors
            ];
            
        } catch (Exception $e) {
            $this->performance_monitor->end_timer($timer_id);
            WPMZF_Logger::error('Error optimizing database tables', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Czyści stare dane
     */
    public function cleanup_old_data() {
        $timer_id = $this->performance_monitor->start_timer('database_optimizer_cleanup');
        
        try {
            global $wpdb;
            
            $cleaned = [];
            $errors = [];
            
            // Usuń stare rewizje postów (starsze niż 90 dni)
            $revisions_deleted = $wpdb->query(
                "DELETE FROM {$wpdb->posts} 
                 WHERE post_type = 'revision' 
                 AND post_modified < DATE_SUB(NOW(), INTERVAL 90 DAY)"
            );
            
            if ($revisions_deleted !== false) {
                $cleaned['old_revisions'] = $revisions_deleted;
                WPMZF_Logger::info("Deleted $revisions_deleted old post revisions");
            } else {
                $errors[] = "Failed to delete old revisions: " . $wpdb->last_error;
            }
            
            // Usuń orphaned postmeta
            $orphaned_meta = $wpdb->query(
                "DELETE pm FROM {$wpdb->postmeta} pm 
                 LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
                 WHERE p.ID IS NULL"
            );
            
            if ($orphaned_meta !== false) {
                $cleaned['orphaned_postmeta'] = $orphaned_meta;
                WPMZF_Logger::info("Deleted $orphaned_meta orphaned postmeta records");
            } else {
                $errors[] = "Failed to delete orphaned postmeta: " . $wpdb->last_error;
            }
            
            // Usuń stare wpisy z kosza (starsze niż 30 dni)
            $trash_deleted = $wpdb->query(
                "DELETE FROM {$wpdb->posts} 
                 WHERE post_status = 'trash' 
                 AND post_modified < DATE_SUB(NOW(), INTERVAL 30 DAY)"
            );
            
            if ($trash_deleted !== false) {
                $cleaned['old_trash'] = $trash_deleted;
                WPMZF_Logger::info("Deleted $trash_deleted old trash posts");
            } else {
                $errors[] = "Failed to delete old trash: " . $wpdb->last_error;
            }
            
            $this->performance_monitor->end_timer($timer_id);
            
            WPMZF_Logger::info('Database cleanup completed', [
                'operations' => count($cleaned),
                'errors_count' => count($errors)
            ]);
            
            return [
                'success' => count($errors) === 0,
                'cleaned' => $cleaned,
                'errors' => $errors
            ];
            
        } catch (Exception $e) {
            $this->performance_monitor->end_timer($timer_id);
            WPMZF_Logger::error('Error cleaning up database', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}
