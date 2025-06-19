<?php

class WPMZF_CPTs {

    public function __construct() {
        // Rejestrujemy CPT przy inicjalizacji WordPressa.
        add_action( 'init', array( $this, 'register_post_types' ) );
    }

    public function register_post_types() {
        // Rejestracja CPT: Klienci
        register_post_type('klienci', array(
            'labels' => array(
                'name' => 'Klienci',
                'singular_name' => 'Klient',
            ),
            'public' => true,
            'has_archive' => true,
            'menu_icon' => 'dashicons-businessperson',
            'supports' => array('title', 'editor', 'custom-fields', 'thumbnail'),
            'rewrite' => array('slug' => 'klienci'),
        ));
        
        // Rejestracja CPT: Zlecenia
        register_post_type('zlecenia', array(
            'labels' => array(
                'name' => 'Zlecenia',
                'singular_name' => 'Zlecenie',
            ),
            'public' => true,
            'has_archive' => true,
            'menu_icon' => 'dashicons-portfolio',
            'supports' => array('title', 'editor', 'custom-fields'),
            'rewrite' => array('slug' => 'zlecenia'),
        ));

        // ... Tutaj dodaj rejestrację kolejnych CPT (Zadania, Faktury, etc.)
    }
}