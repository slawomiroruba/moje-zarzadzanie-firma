<?php

/**
 * Klasa do zarządzania tabelami bazy danych dla systemu e-mail
 *
 * @package WPMZF
 * @subpackage Core
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPMZF_Email_Database {

    /**
     * Tworzy tabele związane z e-mailami
     */
    public static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Tabela kolejki e-maili
        $table_email_queue = $wpdb->prefix . 'wpmzf_email_queue';
        
        $sql_queue = "CREATE TABLE $table_email_queue (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            priority tinyint(4) NOT NULL DEFAULT 5,
            recipient_to text NOT NULL,
            recipient_cc text DEFAULT NULL,
            recipient_bcc text DEFAULT NULL,
            subject text NOT NULL,
            body longtext NOT NULL,
            headers text DEFAULT NULL,
            message_id varchar(255) DEFAULT NULL,
            in_reply_to varchar(255) DEFAULT NULL,
            thread_id varchar(255) DEFAULT NULL,
            related_activity_id bigint(20) UNSIGNED DEFAULT NULL,
            error_message text DEFAULT NULL,
            attempts tinyint(4) NOT NULL DEFAULT 0,
            max_attempts tinyint(4) NOT NULL DEFAULT 3,
            scheduled_at datetime NOT NULL,
            sent_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY status (status),
            KEY scheduled_at (scheduled_at),
            KEY thread_id (thread_id),
            KEY related_activity_id (related_activity_id)
        ) $charset_collate;";

        // Tabela wątków e-maili
        $table_email_threads = $wpdb->prefix . 'wpmzf_email_threads';
        
        $sql_threads = "CREATE TABLE $table_email_threads (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            thread_id varchar(255) NOT NULL,
            subject varchar(500) NOT NULL,
            participants text NOT NULL,
            entity_type varchar(20) NOT NULL,
            entity_id bigint(20) UNSIGNED NOT NULL,
            first_message_id varchar(255) DEFAULT NULL,
            last_message_id varchar(255) DEFAULT NULL,
            message_count int(11) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY thread_id (thread_id),
            KEY entity_type_id (entity_type, entity_id),
            KEY updated_at (updated_at)
        ) $charset_collate;";

        // Tabela odebranych e-maili
        $table_email_received = $wpdb->prefix . 'wpmzf_email_received';
        
        $sql_received = "CREATE TABLE $table_email_received (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            message_id varchar(255) NOT NULL,
            thread_id varchar(255) DEFAULT NULL,
            in_reply_to varchar(255) DEFAULT NULL,
            from_email varchar(255) NOT NULL,
            from_name varchar(500) DEFAULT NULL,
            to_email text NOT NULL,
            cc_email text DEFAULT NULL,
            subject varchar(1000) NOT NULL,
            body longtext NOT NULL,
            body_plain longtext DEFAULT NULL,
            headers text DEFAULT NULL,
            attachments text DEFAULT NULL,
            is_read tinyint(1) NOT NULL DEFAULT 0,
            is_important tinyint(1) NOT NULL DEFAULT 0,
            related_activity_id bigint(20) UNSIGNED DEFAULT NULL,
            entity_type varchar(20) DEFAULT NULL,
            entity_id bigint(20) UNSIGNED DEFAULT NULL,
            received_at datetime NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY message_id (message_id),
            KEY user_id (user_id),
            KEY thread_id (thread_id),
            KEY from_email (from_email),
            KEY is_read (is_read),
            KEY received_at (received_at),
            KEY entity_type_id (entity_type, entity_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_queue);
        dbDelta($sql_threads);
        dbDelta($sql_received);

        // Zapisz wersję bazy danych
        update_option('wpmzf_email_db_version', '1.0.0');
    }

    /**
     * Sprawdza czy tabele istnieją
     */
    public static function tables_exist() {
        global $wpdb;
        
        $table_queue = $wpdb->prefix . 'wpmzf_email_queue';
        $table_threads = $wpdb->prefix . 'wpmzf_email_threads';
        $table_received = $wpdb->prefix . 'wpmzf_email_received';
        
        $queue_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_queue'") === $table_queue;
        $threads_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_threads'") === $table_threads;
        $received_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_received'") === $table_received;
        
        return $queue_exists && $threads_exists && $received_exists;
    }

    /**
     * Usuwa tabele (używane przy deaktywacji)
     */
    public static function drop_tables() {
        global $wpdb;
        
        $table_queue = $wpdb->prefix . 'wpmzf_email_queue';
        $table_threads = $wpdb->prefix . 'wpmzf_email_threads';
        
        $wpdb->query("DROP TABLE IF EXISTS $table_queue");
        $wpdb->query("DROP TABLE IF EXISTS $table_threads");
        
        delete_option('wpmzf_email_db_version');
    }

    /**
     * Naprawia problematyczne tabele (usuwa duplikowane klucze)
     */
    public static function repair_tables() {
        global $wpdb;
        
        $table_threads = $wpdb->prefix . 'wpmzf_email_threads';
        
        // Sprawdź czy tabela istnieje
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_threads'") === $table_threads;
        
        if ($table_exists) {
            // Sprawdź czy ma problematyczny duplikowany klucz
            $indexes = $wpdb->get_results("SHOW INDEX FROM $table_threads WHERE Key_name = 'thread_id'");
            
            if (count($indexes) > 1) {
                // Usuń tabelę i utwórz na nowo
                $wpdb->query("DROP TABLE IF EXISTS $table_threads");
                WPMZF_Logger::info('Removed problematic email_threads table with duplicate keys');
            }
        }
        
        // Teraz utwórz tabele na nowo
        self::create_tables();
    }
}
