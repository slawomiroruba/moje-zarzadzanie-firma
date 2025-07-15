<?php

/**
 * Repository dla użytkowników
 *
 * @package WPMZF
 * @subpackage Repositories
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPMZF_User_Repository {

    /**
     * Nazwa tabeli
     */
    private $table_name;

    /**
     * Cache manager
     */
    private $cache_manager;

    /**
     * Performance monitor
     */
    private $performance_monitor;

    /**
     * Konstruktor
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'wpmzf_users';
        $this->cache_manager = new WPMZF_Cache_Manager();
        $this->performance_monitor = new WPMZF_Performance_Monitor();
    }
    //mateusz: Przykład prawidłowego użycia repository, proponuje też inne zrobić w taki sam sposób np: project

    /**
     * Pobiera wszystkich użytkowników
     *
     * @param int $limit Limit wyników
     * @param int $offset Offset
     * @return array
     */
    public function get_all($limit = 50, $offset = 0) {
        $timer_id = $this->performance_monitor->start_timer('user_repository_get_all');
        
        try {
            // Sprawdź cache
            $cache_key = "users_all_{$limit}_{$offset}";
            $cached_result = $this->cache_manager->get($cache_key);
            if ($cached_result !== false) {
                $this->performance_monitor->end_timer($timer_id);
                return $cached_result;
            }

            global $wpdb;
            
            // Walidacja parametrów
            $limit = max(1, min(1000, intval($limit))); // Maksymalnie 1000 rekordów
            $offset = max(0, intval($offset));
            
            $sql = $wpdb->prepare(
                "SELECT * FROM {$this->table_name} ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $limit,
                $offset
            );
            
            $results = $wpdb->get_results($sql, ARRAY_A);
            
            if ($wpdb->last_error) {
                WPMZF_Logger::error('Database error in get_all', ['error' => $wpdb->last_error, 'sql' => $sql]);
                throw new Exception('Database query failed: ' . $wpdb->last_error);
            }
            
            $users = array_map(function($row) {
                return new WPMZF_User($row);
            }, $results);

            // Cache wynik na 5 minut
            $this->cache_manager->set($cache_key, $users, 300);
            
            $this->performance_monitor->end_timer($timer_id);
            
            return $users;
            
        } catch (Exception $e) {
            $this->performance_monitor->end_timer($timer_id);
            WPMZF_Logger::error('Error in get_all users', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Pobiera użytkownika po ID
     *
     * @param int $id ID użytkownika
     * @return WPMZF_User|null
     */
    public function get_by_id($id) {
        $timer_id = $this->performance_monitor->start_timer('user_repository_get_by_id');
        
        try {
            // Walidacja parametru
            $id = intval($id);
            if ($id <= 0) {
                throw new InvalidArgumentException('Invalid user ID');
            }

            // Sprawdź cache
            $cache_key = "user_{$id}";
            $cached_result = $this->cache_manager->get($cache_key);
            if ($cached_result !== false) {
                $this->performance_monitor->end_timer($timer_id);
                return $cached_result;
            }

            global $wpdb;
            
            $sql = $wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $id);
            $row = $wpdb->get_row($sql, ARRAY_A);
            
            if ($wpdb->last_error) {
                WPMZF_Logger::error('Database error in get_by_id', ['error' => $wpdb->last_error, 'user_id' => $id]);
                throw new Exception('Database query failed: ' . $wpdb->last_error);
            }
            
            $user = $row ? new WPMZF_User($row) : null;

            // Cache wynik na 10 minut
            $this->cache_manager->set($cache_key, $user, 600);
            
            $this->performance_monitor->end_timer($timer_id);
            
            return $user;
            
        } catch (Exception $e) {
            $this->performance_monitor->end_timer($timer_id);
            WPMZF_Logger::error('Error in get_by_id user', ['error' => $e->getMessage(), 'user_id' => $id]);
            throw $e;
        }
    }

    /**
     * Pobiera użytkownika po email
     *
     * @param string $email Email użytkownika
     * @return WPMZF_User|null
     */
    public function get_by_email($email) {
        $timer_id = $this->performance_monitor->start_timer('user_repository_get_by_email');
        
        try {
            // Walidacja email
            $email = trim($email);
            if (!is_email($email)) {
                throw new InvalidArgumentException('Invalid email format');
            }

            // Sprawdź cache
            $cache_key = "user_email_" . md5($email);
            $cached_result = $this->cache_manager->get($cache_key);
            if ($cached_result !== false) {
                $this->performance_monitor->end_timer($timer_id);
                return $cached_result;
            }

            global $wpdb;
            
            $sql = $wpdb->prepare("SELECT * FROM {$this->table_name} WHERE email = %s", $email);
            $row = $wpdb->get_row($sql, ARRAY_A);
            
            if ($wpdb->last_error) {
                WPMZF_Logger::error('Database error in get_by_email', ['error' => $wpdb->last_error, 'email' => $email]);
                throw new Exception('Database query failed: ' . $wpdb->last_error);
            }
            
            $user = $row ? new WPMZF_User($row) : null;

            // Cache wynik na 10 minut
            $this->cache_manager->set($cache_key, $user, 600);
            
            $this->performance_monitor->end_timer($timer_id);
            
            return $user;
            
        } catch (Exception $e) {
            $this->performance_monitor->end_timer($timer_id);
            WPMZF_Logger::error('Error in get_by_email user', ['error' => $e->getMessage(), 'email' => $email]);
            throw $e;
        }
    }

    /**
     * Tworzy nowego użytkownika
     *
     * @param WPMZF_User $user Użytkownik do utworzenia
     * @return int|false ID nowego użytkownika lub false przy błędzie
     */
    public function create(WPMZF_User $user) {
        $timer_id = $this->performance_monitor->start_timer('user_repository_create');
        
        try {
            // Walidacja danych użytkownika
            if (!$user->validate()) {
                throw new InvalidArgumentException('User validation failed');
            }
            
            // Sprawdź czy email już istnieje
            if ($this->email_exists($user->email)) {
                throw new InvalidArgumentException('Email already exists');
            }

            global $wpdb;
            
            $user->sanitize();
            $user->created_at = current_time('mysql');
            $user->updated_at = current_time('mysql');
            
            $result = $wpdb->insert(
                $this->table_name,
                [
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'position' => $user->position,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ],
                [
                    '%s', '%s', '%s', '%s', '%s', '%s'
                ]
            );
            
            if ($result === false) {
                WPMZF_Logger::error('Database error in create user', ['error' => $wpdb->last_error, 'user' => $user]);
                throw new Exception('Database insert failed: ' . $wpdb->last_error);
            }
            
            $user_id = $wpdb->insert_id;
            
            // Wyczyść cache
            $this->cache_manager->delete_pattern('users_*');
            $this->cache_manager->delete("user_email_" . md5($user->email));
            
            WPMZF_Logger::info('User created successfully', ['user_id' => $user_id, 'email' => $user->email]);
            
            $this->performance_monitor->end_timer($timer_id);
            
            return $user_id;
            
        } catch (Exception $e) {
            $this->performance_monitor->end_timer($timer_id);
            WPMZF_Logger::error('Error creating user', ['error' => $e->getMessage(), 'user' => $user]);
            throw $e;
        }
    }

    /**
     * Aktualizuje użytkownika
     *
     * @param int $id ID użytkownika
     * @param WPMZF_User $user Dane użytkownika
     * @return bool
     */
    public function update($id, WPMZF_User $user) {
        $timer_id = $this->performance_monitor->start_timer('user_repository_update');
        
        try {
            // Walidacja parametrów
            $id = intval($id);
            if ($id <= 0) {
                throw new InvalidArgumentException('Invalid user ID');
            }
            
            if (!$user->validate()) {
                throw new InvalidArgumentException('User validation failed');
            }
            
            // Sprawdź czy użytkownik istnieje
            $existing_user = $this->get_by_id($id);
            if (!$existing_user) {
                throw new InvalidArgumentException('User not found');
            }
            
            // Sprawdź czy email już istnieje (z wykluczeniem aktualnego użytkownika)
            if ($this->email_exists($user->email, $id)) {
                throw new InvalidArgumentException('Email already exists');
            }

            global $wpdb;
            
            $user->sanitize();
            $user->updated_at = current_time('mysql');
            
            $result = $wpdb->update(
                $this->table_name,
                [
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'position' => $user->position,
                    'updated_at' => $user->updated_at,
                ],
                ['id' => $id],
                [
                    '%s', '%s', '%s', '%s', '%s'
                ],
                ['%d']
            );
            
            if ($result === false) {
                WPMZF_Logger::error('Database error in update user', ['error' => $wpdb->last_error, 'user_id' => $id]);
                throw new Exception('Database update failed: ' . $wpdb->last_error);
            }
            
            // Wyczyść cache
            $this->cache_manager->delete_pattern('users_*');
            $this->cache_manager->delete("user_{$id}");
            $this->cache_manager->delete("user_email_" . md5($user->email));
            $this->cache_manager->delete("user_email_" . md5($existing_user->email));
            
            WPMZF_Logger::info('User updated successfully', ['user_id' => $id, 'email' => $user->email]);
            
            $this->performance_monitor->end_timer($timer_id);
            
            return true;
            
        } catch (Exception $e) {
            $this->performance_monitor->end_timer($timer_id);
            WPMZF_Logger::error('Error updating user', ['error' => $e->getMessage(), 'user_id' => $id]);
            throw $e;
        }
    }

    /**
     * Usuwa użytkownika
     *
     * @param int $id ID użytkownika
     * @return bool
     */
    public function delete($id) {
        $timer_id = $this->performance_monitor->start_timer('user_repository_delete');
        
        try {
            // Walidacja parametru
            $id = intval($id);
            if ($id <= 0) {
                throw new InvalidArgumentException('Invalid user ID');
            }
            
            // Sprawdź czy użytkownik istnieje
            $existing_user = $this->get_by_id($id);
            if (!$existing_user) {
                throw new InvalidArgumentException('User not found');
            }

            global $wpdb;
            
            $result = $wpdb->delete(
                $this->table_name,
                ['id' => $id],
                ['%d']
            );
            
            if ($result === false) {
                WPMZF_Logger::error('Database error in delete user', ['error' => $wpdb->last_error, 'user_id' => $id]);
                throw new Exception('Database delete failed: ' . $wpdb->last_error);
            }
            
            // Wyczyść cache
            $this->cache_manager->delete_pattern('users_*');
            $this->cache_manager->delete("user_{$id}");
            $this->cache_manager->delete("user_email_" . md5($existing_user->email));
            
            WPMZF_Logger::info('User deleted successfully', ['user_id' => $id, 'email' => $existing_user->email]);
            
            $this->performance_monitor->end_timer($timer_id);
            
            return $result > 0;
            
        } catch (Exception $e) {
            $this->performance_monitor->end_timer($timer_id);
            WPMZF_Logger::error('Error deleting user', ['error' => $e->getMessage(), 'user_id' => $id]);
            throw $e;
        }
    }

    /**
     * Liczy wszystkich użytkowników
     *
     * @return int
     */
    public function count() {
        $timer_id = $this->performance_monitor->start_timer('user_repository_count');
        
        try {
            // Sprawdź cache
            $cache_key = "users_count";
            $cached_result = $this->cache_manager->get($cache_key);
            if ($cached_result !== false) {
                $this->performance_monitor->end_timer($timer_id);
                return $cached_result;
            }

            global $wpdb;
            
            $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
            
            if ($wpdb->last_error) {
                WPMZF_Logger::error('Database error in count users', ['error' => $wpdb->last_error]);
                throw new Exception('Database query failed: ' . $wpdb->last_error);
            }

            // Cache wynik na 5 minut
            $this->cache_manager->set($cache_key, $count, 300);
            
            $this->performance_monitor->end_timer($timer_id);
            
            return $count;
            
        } catch (Exception $e) {
            $this->performance_monitor->end_timer($timer_id);
            WPMZF_Logger::error('Error counting users', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Sprawdza czy email już istnieje
     *
     * @param string $email Email do sprawdzenia
     * @param int $exclude_id ID użytkownika do wykluczenia z sprawdzania
     * @return bool
     */
    public function email_exists($email, $exclude_id = null) {
        global $wpdb;
        
        $sql = "SELECT COUNT(*) FROM {$this->table_name} WHERE email = %s";
        $params = [$email];
        
        if ($exclude_id) {
            $sql .= " AND id != %d";
            $params[] = intval($exclude_id);
        }
        
        $sql = $wpdb->prepare($sql, $params);
        $count = (int) $wpdb->get_var($sql);
        
        return $count > 0;
    }

    /**
     * Pobiera użytkowników z paginacją i sortowaniem
     *
     * @param array $args Argumenty zapytania
     * @return array
     */
    public function get_users_paginated($args = []) {
        $timer_id = $this->performance_monitor->start_timer('user_repository_get_paginated');
        
        try {
            $defaults = [
                'limit' => 20,
                'offset' => 0,
                'orderby' => 'created_at',
                'order' => 'DESC',
                'search' => '',
            ];
            
            $args = wp_parse_args($args, $defaults);
            
            // Walidacja parametrów
            $args['limit'] = max(1, min(1000, intval($args['limit'])));
            $args['offset'] = max(0, intval($args['offset']));
            $args['orderby'] = in_array($args['orderby'], ['id', 'name', 'email', 'created_at', 'updated_at']) ? $args['orderby'] : 'created_at';
            $args['order'] = in_array(strtoupper($args['order']), ['ASC', 'DESC']) ? strtoupper($args['order']) : 'DESC';
            
            // Cache key
            $cache_key = 'users_paginated_' . md5(serialize($args));
            $cached_result = $this->cache_manager->get($cache_key);
            if ($cached_result !== false) {
                $this->performance_monitor->end_timer($timer_id);
                return $cached_result;
            }

            global $wpdb;
            
            $where = "1=1";
            $params = [];
            
            if (!empty($args['search'])) {
                $search = '%' . $wpdb->esc_like($args['search']) . '%';
                $where .= " AND (name LIKE %s OR email LIKE %s)";
                $params[] = $search;
                $params[] = $search;
            }
            
            $sql = "SELECT * FROM {$this->table_name} WHERE {$where} ORDER BY {$args['orderby']} {$args['order']} LIMIT %d OFFSET %d";
            $params[] = $args['limit'];
            $params[] = $args['offset'];
            
            $sql = $wpdb->prepare($sql, $params);
            $results = $wpdb->get_results($sql, ARRAY_A);
            
            if ($wpdb->last_error) {
                WPMZF_Logger::error('Database error in get_users_paginated', ['error' => $wpdb->last_error, 'args' => $args]);
                throw new Exception('Database query failed: ' . $wpdb->last_error);
            }
            
            $users = array_map(function($row) {
                return new WPMZF_User($row);
            }, $results);
            
            // Pobierz też liczbę wszystkich pasujących rekordów
            $count_sql = "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where}";
            if (!empty($args['search'])) {
                $count_sql = $wpdb->prepare($count_sql, $search, $search);
            }
            $total = (int) $wpdb->get_var($count_sql);
            
            $result = [
                'users' => $users,
                'total' => $total,
                'pages' => ceil($total / $args['limit']),
                'current_page' => floor($args['offset'] / $args['limit']) + 1
            ];

            // Cache wynik na 5 minut
            $this->cache_manager->set($cache_key, $result, 300);
            
            $this->performance_monitor->end_timer($timer_id);
            
            return $result;
            
        } catch (Exception $e) {
            $this->performance_monitor->end_timer($timer_id);
            WPMZF_Logger::error('Error in get_users_paginated', ['error' => $e->getMessage(), 'args' => $args]);
            throw $e;
        }
    }

    /**
     * Sprawdza czy tabela istnieje
     *
     * @return bool
     */
    public function table_exists() {
        global $wpdb;
        
        $table_name = $this->table_name;
        return $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
    }

    /**
     * Tworzy tabelę użytkowników
     *
     * @return bool
     */
    public function create_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            email varchar(255) NOT NULL,
            phone varchar(50) DEFAULT '',
            position varchar(255) DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY email (email)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        return $this->table_exists();
    }
}
