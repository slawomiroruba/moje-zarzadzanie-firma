<?php

class WPMZF_Ajax_Handler {

    public function __construct() {
        // Hook do dodawania nowej aktywności
        add_action('wp_ajax_add_wpmzf_activity', array($this, 'add_activity'));
        // Hook do pobierania listy aktywności dla kontaktu
        add_action('wp_ajax_get_wpmzf_activities', array($this, 'get_activities'));
    }

    /**
     * Logika dodawania nowej aktywności.
     */
    public function add_activity() {
        // 1. Bezpieczeństwo
        check_ajax_referer('wpmzf_contact_view_nonce', 'security');

        // 2. Walidacja i sanitazyacja danych
        $contact_id = isset($_POST['contact_id']) ? intval($_POST['contact_id']) : 0;
        $content = isset($_POST['content']) ? wp_kses_post($_POST['content']) : '';
        $activity_type = isset($_POST['activity_type']) ? sanitize_text_field($_POST['activity_type']) : 'note';
        $activity_date = isset($_POST['activity_date']) ? sanitize_text_field($_POST['activity_date']) : current_time('mysql');
        
        if (!$contact_id || empty($content)) {
            wp_send_json_error(array('message' => 'Brak wymaganych danych (ID kontaktu, treść).'));
            return;
        }

        // 3. Tworzenie nowego posta typu 'activity'
        $activity_post = array(
            'post_title'   => 'Aktywność dla ' . get_the_title($contact_id) . ' - ' . $activity_date,
            'post_content' => $content,
            'post_status'  => 'publish',
            'post_author'  => get_current_user_id(),
            'post_type'    => 'activity',
        );

        $activity_id = wp_insert_post($activity_post);

        // 4. Zapisywanie pól ACF
        if ($activity_id && !is_wp_error($activity_id)) {
            update_field('field_wpmzf_activity_type', $activity_type, $activity_id);
            update_field('field_wpmzf_activity_date', $activity_date, $activity_id);
            update_field('field_wpmzf_activity_related_contact', [$contact_id], $activity_id);

            // TODO: Obsługa załączników (to wymaga bardziej zaawansowanej logiki JS po stronie klienta)

            wp_send_json_success(array('message' => 'Aktywność dodana pomyślnie.'));
        } else {
            wp_send_json_error(array('message' => 'Wystąpił błąd podczas dodawania aktywności.'));
        }
    }

    /**
     * Logika pobierania aktywności dla danego kontaktu.
     */
    public function get_activities() {
        check_ajax_referer('wpmzf_contact_view_nonce', 'security');

        $contact_id = isset($_GET['contact_id']) ? intval($_GET['contact_id']) : 0;
        if (!$contact_id) {
            wp_send_json_error(['message' => 'Nieprawidłowe ID kontaktu.']);
            return;
        }

        $args = [
            'post_type' => 'activity',
            'posts_per_page' => -1,
            'meta_key' => 'activity_date', // sortuj po dacie aktywności
            'orderby' => 'meta_value',
            'order' => 'DESC',
            'meta_query' => [
                [
                    'key' => 'related_contact',
                    'value' => '"' . $contact_id . '"',
                    'compare' => 'LIKE'
                ]
            ]
        ];

        $activities_query = new WP_Query($args);
        $activities_data = [];

        if ($activities_query->have_posts()) {
            while ($activities_query->have_posts()) {
                $activities_query->the_post();
                $activity_id = get_the_ID();
                $author_id = get_the_author_meta('ID');

                $activities_data[] = [
                    'id' => $activity_id,
                    'content' => get_the_content(),
                    'date' => get_field('activity_date', $activity_id),
                    'type' => get_field_object('field_wpmzf_activity_type')['choices'][get_field('activity_type', $activity_id)],
                    'author' => get_the_author_meta('display_name', $author_id),
                    'avatar' => get_avatar_url($author_id)
                    // TODO: Dodać listę załączników
                ];
            }
        }
        wp_reset_postdata();

        wp_send_json_success($activities_data);
    }
}