<?php
/**
 * Plik odpowiedzialny za rejestrację wszystkich niestandardowych typów treści (CPT) w systemie.
 */
class WPMZF_Post_Types {

    public function __construct() {
        // Rejestrujemy CPT przy inicjalizacji WordPressa.
        add_action( 'init', array( $this, 'register_post_types' ) );
    }

    /**
     * Główna funkcja rejestrująca wszystkie CPT.
     */
    public function register_post_types() {

        // --- MODUŁ 1: CRM i SPRZEDAŻ ---

        $this->register_single_cpt('company', 'Firma', 'Firmy', 'dashicons-businessperson', 20);
        $this->register_single_cpt('person', 'Osoba', 'Osoby', 'dashicons-admin-users', 21);
        $this->register_single_cpt('opportunity', 'Szansa Sprzedaży', 'Szanse Sprzedaży', 'dashicons-chart-line', 22);
        $this->register_single_cpt('quote', 'Oferta', 'Oferty', 'dashicons-media-text', 23);

        // --- MODUŁ 2: PROJEKTY i REALIZACJA ---

        $this->register_single_cpt('project', 'Projekt', 'Projekty', 'dashicons-portfolio', 24);
        $this->register_single_cpt('task', 'Zadanie', 'Zadania', 'dashicons-list-view', 25);
        $this->register_single_cpt('time_entry', 'Wpis Czasu Pracy', 'Rejestr Czasu Pracy', 'dashicons-clock', 26, ['title', 'custom-fields', 'author']);

        // --- MODUŁ 3: FINANSE i DOKUMENTY ---

        $this->register_single_cpt('invoice', 'Faktura', 'Faktury', 'dashicons-money-alt', 27);
        $this->register_single_cpt('payment', 'Płatność', 'Płatności', 'dashicons-bank', 28);
        $this->register_single_cpt('contract', 'Umowa', 'Umowy', 'dashicons-media-document', 29);
        $this->register_single_cpt('expense', 'Koszt', 'Koszty', 'dashicons-cart', 30);

        // --- MODUŁ 4: ZASOBY WEWNĘTRZNE ---

        $this->register_single_cpt('employee', 'Pracownik', 'Pracownicy', 'dashicons-id', 31);

        $this->register_single_cpt('activity', 'Aktywność', 'Aktywności', 'dashicons-format-chat', 32, ['title', 'editor', 'custom-fields', 'author']);


    }

    /**
     * Pomocnicza, reużywalna funkcja do rejestracji pojedynczego CPT.
     * Upraszcza kod i zapewnia spójność ustawień.
     *
     * @param string $slug          Techniczna nazwa CPT (np. 'company').
     * @param string $singular      Nazwa w liczbie pojedynczej (np. 'Firma').
     * @param string $plural        Nazwa w liczbie mnogiej (np. 'Firmy').
     * @param string $icon          Ikona z Dashicons (np. 'dashicons-businessperson').
     * @param int    $position      Pozycja w menu admina.
     * @param array  $supports      Tablica wspieranych pól (np. ['title', 'editor']).
     */
private function register_single_cpt($slug, $singular, $plural, $icon, $position, $supports = ['title', 'editor', 'custom-fields', 'thumbnail', 'comments']) {
        $labels = [
            'name'                  => $plural,
            'singular_name'         => $singular,
            'menu_name'             => $plural,
            'name_admin_bar'        => $singular,
            'archives'              => 'Archiwum ' . strtolower($plural),
            'attributes'            => 'Atrybuty ' . strtolower($singular),
            'parent_item_colon'     => 'Rodzic ' . strtolower($singular) . ':',
            'all_items'             => 'Wszystkie ' . strtolower($plural),
            'add_new_item'          => 'Dodaj nowy ' . strtolower($singular),
            'add_new'               => 'Dodaj nowy',
            'new_item'              => 'Nowy ' . strtolower($singular),
            'edit_item'             => 'Edytuj ' . strtolower($singular),
            'update_item'           => 'Zaktualizuj ' . strtolower($singular),
            'view_item'             => 'Zobacz ' . strtolower($singular),
            'view_items'            => 'Zobacz ' . strtolower($plural),
            'search_items'          => 'Szukaj w ' . strtolower($plural),
            'not_found'             => 'Nie znaleziono',
            'not_found_in_trash'    => 'Nie znaleziono w koszu',
            'featured_image'        => 'Obrazek wyróżniający',
            'set_featured_image'    => 'Ustaw obrazek wyróżniający',
            'remove_featured_image' => 'Usuń obrazek wyróżniający',
            'use_featured_image'    => 'Użyj jako obrazka wyróżniającego',
            'insert_into_item'      => 'Wstaw do ' . strtolower($singular),
            'uploaded_to_this_item' => 'Wysłano do tego ' . strtolower($singular),
            'items_list'            => 'Lista ' . strtolower($plural),
            'items_list_navigation' => 'Nawigacja po liście ' . strtolower($plural),
            'filter_items_list'     => 'Filtruj listę ' . strtolower($plural),
        ];

        $args = [
            'label'                 => $singular,
            'labels'                => $labels,
            'supports'              => $supports,
            'hierarchical'          => false,
            'public'                => true, // Ustawione na true, ale poniższe opcje ograniczają dostęp
            'show_ui'               => true, // Pokazuj w panelu admina
            'show_in_menu'          => true, // Pokazuj w menu admina
            'menu_position'         => $position,
            'menu_icon'             => $icon,
            'show_in_admin_bar'     => true,
            'show_in_nav_menus'     => false, // Nie pokazuj w menu nawigacyjnym na stronie
            'can_export'            => true,
            'has_archive'           => false, // Nie twórz publicznego archiwum (np. /firmy/)
            'exclude_from_search'   => true, // Wyklucz z wyników wyszukiwania na stronie
            'publicly_queryable'    => false, // Nie pozwalaj na publiczne odpytywanie (np. /firma/nazwa-firmy/)
            'capability_type'       => 'post',
            'rewrite'               => false, // Nie twórz przyjaznych adresów URL
        ];

        register_post_type($slug, $args);
    }
}