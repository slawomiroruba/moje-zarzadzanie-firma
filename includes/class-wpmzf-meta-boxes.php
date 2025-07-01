<?php

class WPMZF_Meta_Boxes {

    public function __construct() {
        add_action('add_meta_boxes', array($this, 'add_related_items_meta_box'));
    }

    /**
     * Rejestruje meta box na ekranie edycji Firmy.
     */
    public function add_related_items_meta_box() {
        add_meta_box(
            'wpmzf_related_items_box',
            'Powiązane Elementy',
            array($this, 'display_related_items_meta_box'),
            'company', // Pokazuj na CPT 'company'
            'side',    // W bocznej kolumnie
            'high'
        );
    }

    /**
     * Wyświetla zawartość meta boxa.
     */
    public function display_related_items_meta_box($post) {
        $this->display_related_posts($post->ID, 'contact', 'contact_company', 'Kontakty');
        $this->display_related_posts($post->ID, 'project', 'project_company', 'Projekty');
        $this->display_related_posts($post->ID, 'quote', 'quote_company', 'Oferty');
    }

    /**
     * Pomocnicza funkcja do wyświetlania listy powiązanych postów.
     */
    private function display_related_posts($company_id, $post_type, $acf_field, $title) {
        $args = array(
            'post_type'      => $post_type,
            'posts_per_page' => -1,
            'meta_query'     => array(
                array(
                    'key'     => $acf_field,
                    'value'   => $company_id,
                    'compare' => '=',
                ),
            ),
            'fields' => 'ids'
        );

        $items = new WP_Query($args);

        echo '<h4>' . esc_html($title) . ' (' . $items->found_posts . ')</h4>';

        if ($items->have_posts()) {
            echo '<ul style="margin-bottom: 15px;">';
            foreach ($items->posts as $item_id) {
                echo '<li><a href="' . get_edit_post_link($item_id) . '">' . get_the_title($item_id) . '</a></li>';
            }
            echo '</ul>';
        } else {
            echo '<p><em>Brak.</em></p>';
        }
    }
}