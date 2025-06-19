<?php

class WPMZF_Admin_Columns {

    public function __construct() {
        // Filtr do dodania nowej kolumny
        add_filter('manage_klienci_posts_columns', array($this, 'add_custom_column'));
        // Akcja do wypełnienia tej kolumny treścią
        add_action('manage_klienci_posts_custom_column', array($this, 'display_column_content'), 10, 2);
    }

    /**
     * Dodaje nagłówek nowej kolumny do listy klientów.
     */
    public function add_custom_column($columns) {
        $columns['przypisane_zlecenia'] = 'Przypisane Zlecenia';
        return $columns;
    }

    /**
     * Wyświetla zawartość komórki w niestandardowej kolumnie.
     */
    public function display_column_content($column_name, $post_id) {
        if ($column_name == 'przypisane_zlecenia') {
            $args = array(
                'post_type'      => 'zlecenia',
                'posts_per_page' => -1,
                'meta_query'     => array(
                    array(
                        'key'     => 'klient', // WAŻNE: Nazwa pola relacji z ACF!
                        'value'   => '"' . $post_id . '"',
                        'compare' => 'LIKE',
                    ),
                ),
            );

            $zlecenia = new WP_Query($args);

            if ($zlecenia->have_posts()) {
                echo '<ul>';
                while ($zlecenia->have_posts()) {
                    $zlecenia->the_post();
                    $edit_link = get_edit_post_link(get_the_ID());
                    echo '<li><a href="' . esc_url($edit_link) . '">' . get_the_title() . '</a></li>';
                }
                echo '</ul>';
                wp_reset_postdata();
            } else {
                echo '<em>Brak zleceń</em>';
            }
        }
    }
}