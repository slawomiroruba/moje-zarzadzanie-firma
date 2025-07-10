<?php

class WPMZF_Meta_Boxes {

    public function __construct() {
        add_action('add_meta_boxes', array($this, 'add_client_orders_meta_box'));
    }

    /**
     * Rejestruje meta box na ekranie edycji Klienta.
     */
    public function add_client_orders_meta_box() {
        add_meta_box(
            'wpmzf_zlecenia_klienta_box',
            'Zlecenia tego Klienta',
            array($this, 'display_client_orders_meta_box'),
            'klienci', // Pokazuj na CPT 'klienci'
            'normal',
            'high'
        );
    }

    /**
     * Wyświetla zawartość meta boxa ze zleceniami.
     */
    public function display_client_orders_meta_box($post) {
        // Używamy tej samej logiki co w kolumnie
        $args = array(
            'post_type'      => 'zlecenia',
            'posts_per_page' => -1,
            'meta_query'     => array(
                array(
                    'key'     => 'klient', // WAŻNE: Nazwa pola relacji z ACF!
                    'value'   => '"' . $post->ID . '"',
                    'compare' => 'LIKE',
                ),
            ),
        );

        $zlecenia = new WP_Query($args);

        if ($zlecenia->have_posts()) {
            echo '<ul>';
            while ($zlecenia->have_posts()) {
                $zlecenia->the_post();
                echo '<li><a href="' . get_edit_post_link(get_the_ID()) . '">' . get_the_title() . '</a> (Status: ' . get_field('status') . ')</li>';
            }
            echo '</ul>';
            wp_reset_postdata();
        } else {
            echo 'Ten klient nie ma jeszcze żadnych zleceń.';
        }
    }
}