<?php
// Plik: includes/abstracts/class-wpmzf-abstract-cpt.php

if ( ! defined( 'WPINC' ) ) {
	die;
}

abstract class WPMZF_Abstract_CPT {

    /**
     * Unikalny identyfikator CPT (np. 'contact', 'company').
     * @var string
     */
    protected $cpt_slug;

    /**
     * Argumenty do rejestracji CPT.
     * @var array
     */
    protected $cpt_args = [];

    /**
     * Definicje metaboxów.
     * @var array
     */
    protected $meta_boxes = [];

    /**
     * Konstruktor uruchamia całą magię.
     */
    public function __construct() {
        if ( empty( $this->cpt_slug ) ) {
            wp_die( 'Slug CPT nie został zdefiniowany w klasie potomnej.' );
        }

        // Rejestruje hooki WordPressa
        $this->add_hooks();
    }

    /**
     * Rejestruje wszystkie potrzebne akcje i filtry.
     */
    private function add_hooks() {
        add_action( 'init', array( $this, 'register_cpt' ) );
        add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ) );
        add_action( 'save_post_' . $this->cpt_slug, array( $this, 'save_meta_data' ) );
    }

    /**
     * Metoda rejestrująca Custom Post Type.
     * Logika jest generyczna i używa właściwości zdefiniowanych w klasie-dziecku.
     */
    public function register_cpt() {
        if ( post_type_exists( $this->cpt_slug ) ) {
            return;
        }
        register_post_type( $this->cpt_slug, $this->cpt_args );
    }

    /**
     * Metoda rejestrująca meta boxy.
     * Przechodzi przez tablicę $meta_boxes i dodaje każdy z nich.
     */
    public function register_meta_boxes() {
        foreach ( $this->meta_boxes as $meta_box ) {
            add_meta_box(
                $meta_box['id'],
                $meta_box['title'],
                array( $this, 'render_meta_box_callback' ),
                $this->cpt_slug,
                $meta_box['context'] ?? 'advanced',
                $meta_box['priority'] ?? 'default',
                array( 'fields' => $meta_box['fields'] ) // Przekazuje pola do callbacku
            );
        }
    }

    /**
     * Generyczny callback do renderowania zawartości meta boxu.
     * Używa szablonu do wyświetlania pól.
     *
     * @param WP_Post $post Obiekt posta.
     * @param array   $callback_args Argumenty przekazane z add_meta_box.
     */
    public function render_meta_box_callback( $post, $callback_args ) {
        // Dodaje nonce dla bezpieczeństwa
        wp_nonce_field( $this->cpt_slug . '_meta_box', $this->cpt_slug . '_meta_box_nonce' );

        // Zmienne przekazywane do szablonu
        $fields = $callback_args['args']['fields'];
        $post_id = $post->ID;

        // Dołącza szablon, który renderuje pola
        // Daje to elastyczność i oddziela logikę od widoku.
        include WPMZF_PLUGIN_PATH . 'includes/templates/meta-box-fields.php';
    }


    /**
     * Generyczna metoda do zapisu danych z meta boxów.
     *
     * @param int $post_id ID zapisywanego posta.
     */
    public function save_meta_data( $post_id ) {
        // Weryfikacja nonce
        if ( ! isset( $_POST[ $this->cpt_slug . '_meta_box_nonce' ] ) || ! wp_verify_nonce( $_POST[ $this->cpt_slug . '_meta_box_nonce' ], $this->cpt_slug . '_meta_box' ) ) {
            return;
        }
        // Ignoruje autozapis
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        // Sprawdzenie uprawnień
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Zapisuje dane z każdego pola zdefiniowanego w meta boxach
        foreach ( $this->meta_boxes as $meta_box ) {
            foreach ( $meta_box['fields'] as $field ) {
                $field_id = $field['id'];
                if ( isset( $_POST[ $field_id ] ) ) {
                    $new_value = sanitize_text_field( $_POST[ $field_id ] );
                    update_post_meta( $post_id, $field_id, $new_value );
                } else {
                    delete_post_meta( $post_id, $field_id );
                }
            }
        }
    }
}