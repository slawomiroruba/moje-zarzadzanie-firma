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
     * Konstruktor
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'wpmzf_users';
    }

    /**
     * Pobiera wszystkich użytkowników
     *
     * @param int $limit Limit wyników
     * @param int $offset Offset
     * @return array
     */
    public function get_all($limit = 50, $offset = 0) {
        global $wpdb;
        
        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->table_name} ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $limit,
            $offset
        );
        
        $results = $wpdb->get_results($sql, ARRAY_A);
        
        return array_map(function($row) {
            return new WPMZF_User($row);
        }, $results);
    }

    /**
     * Pobiera użytkownika po ID
     *
     * @param int $id ID użytkownika
     * @return WPMZF_User|null
     */
    public function get_by_id($id) {
        global $wpdb;
        
        $sql = $wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $id);
        $row = $wpdb->get_row($sql, ARRAY_A);
        
        return $row ? new WPMZF_User($row) : null;
    }

    /**
     * Pobiera użytkownika po email
     *
     * @param string $email Email użytkownika
     * @return WPMZF_User|null
     */
    public function get_by_email($email) {
        global $wpdb;
        
        $sql = $wpdb->prepare("SELECT * FROM {$this->table_name} WHERE email = %s", $email);
        $row = $wpdb->get_row($sql, ARRAY_A);
        
        return $row ? new WPMZF_User($row) : null;
    }

    /**
     * Tworzy nowego użytkownika
     *
     * @param WPMZF_User $user Użytkownik do utworzenia
     * @return int|false ID nowego użytkownika lub false przy błędzie
     */
    public function create(WPMZF_User $user) {
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
        
        return $result !== false ? $wpdb->insert_id : false;
    }

    /**
     * Aktualizuje użytkownika
     *
     * @param int $id ID użytkownika
     * @param WPMZF_User $user Dane użytkownika
     * @return bool
     */
    public function update($id, WPMZF_User $user) {
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
        
        return $result !== false;
    }

    /**
     * Usuwa użytkownika
     *
     * @param int $id ID użytkownika
     * @return bool
     */
    public function delete($id) {
        global $wpdb;
        
        $result = $wpdb->delete(
            $this->table_name,
            ['id' => $id],
            ['%d']
        );
        
        return $result !== false;
    }

    /**
     * Liczy wszystkich użytkowników
     *
     * @return int
     */
    public function count() {
        global $wpdb;
        
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
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
