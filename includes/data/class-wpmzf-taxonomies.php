<?php

/**
 * Klasa zarządzająca taksonomiami
 *
 * @package WPMZF
 * @subpackage Data
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPMZF_Taxonomies {

    /**
     * Konstruktor klasy
     */
    public function __construct() {
        add_action('init', array($this, 'register_taxonomies'));
    }

    /**
     * Rejestruje taksonomie
     */
    public function register_taxonomies() {
        $this->register_company_categories();
        $this->register_project_statuses();
        $this->register_project_types();
    }

    /**
     * Rejestruje kategorie firm
     */
    private function register_company_categories() {
        $args = array(
            'labels' => array(
                'name'              => 'Kategorie firm',
                'singular_name'     => 'Kategoria firmy',
                'search_items'      => 'Szukaj kategorii',
                'all_items'         => 'Wszystkie kategorie',
                'parent_item'       => 'Kategoria nadrzędna',
                'parent_item_colon' => 'Kategoria nadrzędna:',
                'edit_item'         => 'Edytuj kategorię',
                'update_item'       => 'Aktualizuj kategorię',
                'add_new_item'      => 'Dodaj nową kategorię',
                'new_item_name'     => 'Nazwa nowej kategorii',
                'menu_name'         => 'Kategorie firm',
            ),
            'hierarchical'      => true,
            'public'            => false,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => false,
        );

        register_taxonomy('company_category', 'company', $args);
    }

    /**
     * Rejestruje statusy projektów
     */
    private function register_project_statuses() {
        $args = array(
            'labels' => array(
                'name'              => 'Statusy projektów',
                'singular_name'     => 'Status projektu',
                'search_items'      => 'Szukaj statusów',
                'all_items'         => 'Wszystkie statusy',
                'edit_item'         => 'Edytuj status',
                'update_item'       => 'Aktualizuj status',
                'add_new_item'      => 'Dodaj nowy status',
                'new_item_name'     => 'Nazwa nowego statusu',
                'menu_name'         => 'Statusy projektów',
            ),
            'hierarchical'      => false,
            'public'            => false,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => false,
        );

        register_taxonomy('project_status', 'project', $args);
    }

    /**
     * Rejestruje typy projektów
     */
    private function register_project_types() {
        $args = array(
            'labels' => array(
                'name'              => 'Typy projektów',
                'singular_name'     => 'Typ projektu',
                'search_items'      => 'Szukaj typów',
                'all_items'         => 'Wszystkie typy',
                'edit_item'         => 'Edytuj typ',
                'update_item'       => 'Aktualizuj typ',
                'add_new_item'      => 'Dodaj nowy typ',
                'new_item_name'     => 'Nazwa nowego typu',
                'menu_name'         => 'Typy projektów',
            ),
            'hierarchical'      => true,
            'public'            => false,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => false,
        );

        register_taxonomy('project_type', 'project', $args);
    }
}
