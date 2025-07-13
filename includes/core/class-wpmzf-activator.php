<?php

/**
 * Klasa aktywatora wtyczki
 *
 * @package WPMZF
 * @subpackage Core
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPMZF_Activator {
    
    /**
     * Aktywuje wtyczkę
     */
    public static function activate() {
        // Dodaj domyślne opcje
        self::add_default_options();
        
        // Odśwież reguły rewrite
        flush_rewrite_rules();
        
        // Zaznacz, że tabele mają być utworzone przy pierwszym załadowaniu
        add_option('wpmzf_need_create_tables', true);
    }
    
    /**
     * Tworzy tabele w bazie danych (wywoływane po pełnym załadowaniu wtyczki)
     */
    public static function create_tables() {
        // Utwórz tabelę użytkowników
        $user_repository = new WPMZF_User_Repository();
        $user_repository->create_table();
        
        // Usuń flagę potrzeby tworzenia tabel
        delete_option('wpmzf_need_create_tables');
        
        // Tutaj można dodać inne tabele w przyszłości
        // $other_repository = new WPMZF_Other_Repository();
        // $other_repository->create_table();
    }
    
    /**
     * Dodaje domyślne opcje
     */
    private static function add_default_options() {
        add_option('wpmzf_version', '1.0.0');
        add_option('wpmzf_install_date', current_time('mysql'));
    }
}