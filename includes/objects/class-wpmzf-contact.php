<?php
// Plik: includes/objects/class-wpmzf-contact.php

if ( ! defined( 'WPINC' ) ) {
	die;
}

class WPMZF_Contact extends WPMZF_Abstract_CPT {

    /**
     * Ustawia właściwości i wywołuje konstruktor rodzica.
     */
    public function __construct() {
        // KROK 1: Zdefiniuj unikalny slug dla CPT
        $this->cpt_slug = 'contact';

        // KROK 2: Zdefiniuj argumenty dla register_post_type()
        $this->cpt_args = [
            'labels' => [
                'name'               => __( 'Kontakty', 'wpmzf' ),
                'singular_name'      => __( 'Kontakt', 'wpmzf' ),
                'menu_name'          => __( 'Kontakty', 'wpmzf' ),
                'add_new_item'       => __( 'Dodaj nowy kontakt', 'wpmzf' ),
                'edit_item'          => __( 'Edytuj kontakt', 'wpmzf' ),
                'new_item'           => __( 'Nowy kontakt', 'wpmzf' ),
                'view_item'          => __( 'Zobacz kontakt', 'wpmzf' ),
                'search_items'       => __( 'Szukaj w kontaktach', 'wpmzf' ),
                'not_found'          => __( 'Nie znaleziono kontaktów', 'wpmzf' ),
                'not_found_in_trash' => __( 'Brak kontaktów w koszu', 'wpmzf' )
            ],
            'public' => true,
            'has_archive' => true,
            'rewrite' => ['slug' => 'kontakty'],
            'supports' => ['title', 'editor', 'thumbnail'],
            'menu_icon' => 'dashicons-id',
        ];

        // KROK 3: Zdefiniuj swoje meta boxy
        // Obecnie ACF obsługuje pola, więc ta tablica może być pusta
        // Ale gdybyś chciał mieć meta boxy bez ACF, wyglądałoby to tak:
        $this->meta_boxes = [
            [
                'id' => 'contact_details_metabox',
                'title' => 'Dane Kontaktowe (Meta Box)',
                'context' => 'advanced',
                'priority' => 'high',
                'fields' => [
                    [
                        'id' => 'contact_phone',
                        'label' => 'Telefon',
                        'type' => 'text'
                    ],
                    [
                        'id' => 'contact_email',
                        'label' => 'Adres E-mail',
                        'type' => 'text'
                    ]
                ]
            ]
        ];

        // KROK 4: Uruchom logikę z klasy-rodzica
        parent::__construct();
    }
}