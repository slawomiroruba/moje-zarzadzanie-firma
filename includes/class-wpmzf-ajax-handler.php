<?php

class WPMZF_Ajax_Handler
{

    public function __construct()
    {
        // Hook do dodawania nowej aktywności
        add_action('wp_ajax_add_wpmzf_activity', array($this, 'add_activity'));
        // Hook do pobierania listy aktywności dla kontaktu
        add_action('wp_ajax_get_wpmzf_activities', array($this, 'get_activities'));
        // Hook do uploadu załączników
        add_action('wp_ajax_wpmzf_upload_attachment', array($this, 'upload_attachment'));
        // Hook do usuwania aktywności
        add_action('wp_ajax_delete_wpmzf_activity', array($this, 'delete_activity'));
        // Hook do aktualizacji aktywności
        add_action('wp_ajax_update_wpmzf_activity', array($this, 'update_activity'));
        // Hook do usuwania pojedynczego załącznika
        add_action('wp_ajax_delete_wpmzf_attachment', array($this, 'delete_attachment'));
        // Hook do aktualizacji danych kontaktu
        add_action('wp_ajax_wpmzf_update_contact_details', array($this, 'update_contact_details'));
        // Rejestracja punktu końcowego dla zalogowanych użytkowników
        add_action('wp_ajax_wpmzf_search_companies',  array($this, 'wpmzf_search_companies_ajax_handler'));
        // Rejestracja punktu końcowego dla niezalogowanych użytkowników
        add_action('wp_ajax_nopriv_wpmzf_search_companies', array($this, 'wpmzf_search_companies_ajax_handler'));
    }

    /**
     * Obsługuje zapytanie AJAX do wyszukiwania firm na podstawie nazwy lub NIP-u.
     */
    public function wpmzf_search_companies_ajax_handler()
    {
        // Bezpieczeństwo: sprawdzanie nonca
        // check_ajax_referer('wpmzf_contact_view_nonce', 'security');
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log(print_r($_POST, true)); // Debugowanie danych POST
        } else {
            error_log('Debugowanie danych POST: ' . json_encode($_POST)); // Alternatywne logowanie
        }
        // Pobranie i zwalidowanie terminu wyszukiwania
        $search_term = isset($_POST['term']) ? sanitize_text_field(wp_unslash($_POST['term'])) : '';
        error_log("Wyszukiwanie firm: $search_term"); // Debugowanie

        if (empty($search_term)) {  
            wp_send_json_error(['message' => 'Brak terminu wyszukiwania.']);
        }

        $results = [];

        // Zapytanie do bazy danych
        $args = [
            'post_type'      => 'company',
            'posts_per_page' => 20, // Ograniczamy liczbę wyników dla wydajności
            'post_status'    => 'publish',
            's'              => $search_term, // Wyszukiwanie w tytule i treści
        ];

        $query_by_title = new WP_Query($args);

        if ($query_by_title->have_posts()) {
            while ($query_by_title->have_posts()) {
                $query_by_title->the_post();
                $results[get_the_ID()] = [
                    'id'   => get_the_ID(),
                    'text' => get_the_title(),
                ];
            }
        }
        wp_reset_postdata();

        // Wyszukiwanie po NIP (zakładając, że NIP jest w polu meta o kluczu 'company_nip')
        // Dostosuj 'company_nip' jeśli klucz pola jest inny!
        $args_nip = [
            'post_type'      => 'company',
            'posts_per_page' => 20,
            'post_status'    => 'publish',
            'meta_query'     => [
                [
                    'key'     => 'company_nip', // <-- WAŻNE: ZMIEŃ, JEŚLI POTRZEBA
                    'value'   => $search_term,
                    'compare' => 'LIKE',
                ],
            ],
        ];

        $query_by_nip = new WP_Query($args_nip);
        error_log(print_r($query_by_nip->request, true)); // Debugowanie zapytania SQL

        if ($query_by_nip->have_posts()) {
            while ($query_by_nip->have_posts()) {
                $query_by_nip->the_post();
                // Unikamy duplikatów, jeśli NIP jest też w tytule
                if (!isset($results[get_the_ID()])) {
                    $results[get_the_ID()] = [
                        'id'   => get_the_ID(),
                        'text' => get_the_title(),
                    ];
                }
            }
        }
        wp_reset_postdata();
        
        // Zwracamy unikalne wyniki w formacie JSON
        wp_send_json_success(array_values($results));
    }



    /**
     * Logika dodawania nowej aktywności.
     */
    public function add_activity()
    {
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

        // 4. Zapisywanie pól ACF i obsługa załączników
        if ($activity_id && !is_wp_error($activity_id)) {
            update_field('field_wpmzf_activity_type', $activity_type, $activity_id);
            update_field('field_wpmzf_activity_date', $activity_date, $activity_id);
            update_field('field_wpmzf_activity_related_contact', $contact_id, $activity_id);

            // Pobieramy ID załączników przesłane przez AJAX z `$_POST['attachment_ids']`
            $attachment_ids = isset($_POST['attachment_ids']) && is_array($_POST['attachment_ids']) ? array_map('intval', $_POST['attachment_ids']) : [];

            // Jeśli mamy ID załączników, zapisujemy je w polu Repeater
            if (!empty($attachment_ids)) {
                $rows = [];
                foreach ($attachment_ids as $att_id) {
                    $rows[] = [
                        'attachment_file' => $att_id, // 'attachment_file' to nazwa sub-pola
                    ];
                }
                // Używamy klucza pola, co jest najlepszą praktyką
                update_field('field_wpmzf_activity_attachments', $rows, $activity_id);
            }

            wp_send_json_success(array('message' => 'Aktywność dodana pomyślnie.'));
        } else {
            wp_send_json_error(array('message' => 'Wystąpił błąd podczas dodawania aktywności.'));
        }
    }

    /**
     * Logika pobierania aktywności dla danego kontaktu.
     */
    public function get_activities()
    {
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
                    'value' => $contact_id,
                    'compare' => '='
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

                // Pobieranie załączników z pola repeater
                $attachments_repeater = get_field('activity_attachments', $activity_id);
                $attachments_data = [];
                if ($attachments_repeater) {
                    foreach ($attachments_repeater as $row) {
                        // Upewnij się, że sub-pole istnieje i ma wartość
                        if (isset($row['attachment_file']) && $row['attachment_file']) {
                            $attachment_id = $row['attachment_file'];

                            $attachment_data = [
                                'id'        => $attachment_id,
                                'url'       => wp_get_attachment_url($attachment_id),
                                'filename'  => basename(get_attached_file($attachment_id)),
                                'mime_type' => get_post_mime_type($attachment_id)
                            ];

                            if (wp_attachment_is_image($attachment_id)) {
                                $thumbnail_src = wp_get_attachment_image_src($attachment_id, 'thumbnail');
                                if ($thumbnail_src) {
                                    $attachment_data['thumbnail_url'] = $thumbnail_src[0];
                                }
                            }

                            $attachments_data[] = $attachment_data;
                        }
                    }
                }

                $activity_type_value = get_field('activity_type', $activity_id);
                $activity_type_field = get_field_object('field_wpmzf_activity_type');
                $activity_type_label = $activity_type_value;
                if ($activity_type_field && isset($activity_type_field['choices'][$activity_type_value])) {
                    $activity_type_label = $activity_type_field['choices'][$activity_type_value];
                }

                $activities_data[] = [
                    'id' => $activity_id,
                    'content' => get_the_content(),
                    'date' => get_field('activity_date', $activity_id),
                    'type' => $activity_type_label,
                    'author' => get_the_author_meta('display_name', $author_id),
                    'avatar' => get_avatar_url($author_id),
                    'attachments' => $attachments_data
                ];
            }
        }
        wp_reset_postdata();

        wp_send_json_success($activities_data);
    }

    /**
     * Obsługuje upload pojedynczego pliku do biblioteki mediów.
     */
    public function upload_attachment()
    {
        check_ajax_referer('wpmzf_contact_view_nonce', 'security');

        if (empty($_FILES['file'])) {
            wp_send_json_error(['message' => 'Brak pliku do przesłania.']);
            return;
        }

        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        $attachment_id = media_handle_upload('file', 0);

        if (is_wp_error($attachment_id)) {
            wp_send_json_error(['message' => $attachment_id->get_error_message()]);
        } else {
            wp_send_json_success(['id' => $attachment_id]);
        }
    }

    /**
     * Usuwa całą aktywność wraz z załącznikami.
     */
    public function delete_activity()
    {
        check_ajax_referer('wpmzf_contact_view_nonce', 'security');

        $activity_id = isset($_POST['activity_id']) ? intval($_POST['activity_id']) : 0;
        if (!$activity_id || get_post_type($activity_id) !== 'activity') {
            wp_send_json_error(['message' => 'Nieprawidłowe ID aktywności.']);
            return;
        }

        if (!current_user_can('delete_post', $activity_id)) {
            wp_send_json_error(['message' => 'Brak uprawnień.']);
            return;
        }

        $attachments_repeater = get_field('activity_attachments', $activity_id);
        if (is_array($attachments_repeater)) {
            foreach ($attachments_repeater as $row) {
                if (isset($row['attachment_file']) && $row['attachment_file']) {
                    // Zakładając, że 'attachment_file' to nazwa sub-pola
                    $att_id = $row['attachment_file'];
                    wp_delete_attachment($att_id, true); // true oznacza trwałe usunięcie
                }
            }
        }

        $result = wp_delete_post($activity_id, true);

        if ($result) {
            wp_send_json_success(['message' => 'Aktywność usunięta.']);
        } else {
            wp_send_json_error(['message' => 'Nie udało się usunąć aktywności.']);
        }
    }

    /**
     * Aktualizuje treść aktywności.
     */
    public function update_activity()
    {
        check_ajax_referer('wpmzf_contact_view_nonce', 'security');

        $activity_id = isset($_POST['activity_id']) ? intval($_POST['activity_id']) : 0;
        $content = isset($_POST['content']) ? wp_kses_post($_POST['content']) : '';

        if (!$activity_id || empty($content) || get_post_type($activity_id) !== 'activity') {
            wp_send_json_error(['message' => 'Nieprawidłowe dane.']);
            return;
        }

        if (!current_user_can('edit_post', $activity_id)) {
            wp_send_json_error(['message' => 'Brak uprawnień.']);
            return;
        }

        $post_data = [
            'ID' => $activity_id,
            'post_content' => $content,
        ];

        $result = wp_update_post($post_data, true);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        } else {
            wp_send_json_success(['message' => 'Aktywność zaktualizowana.']);
        }
    }

    /**
     * Usuwa pojedynczy załącznik z aktywności.
     */
    public function delete_attachment()
    {
        check_ajax_referer('wpmzf_contact_view_nonce', 'security');

        $activity_id = isset($_POST['activity_id']) ? intval($_POST['activity_id']) : 0;
        $attachment_id = isset($_POST['attachment_id']) ? intval($_POST['attachment_id']) : 0;

        if (!$activity_id || !$attachment_id || get_post_type($activity_id) !== 'activity') {
            wp_send_json_error(['message' => 'Nieprawidłowe dane.']);
            return;
        }

        if (!current_user_can('edit_post', $activity_id)) {
            wp_send_json_error(['message' => 'Brak uprawnień.']);
            return;
        }

        $rows = get_field('activity_attachments', $activity_id);
        $new_rows = [];
        $deleted = false;

        if (is_array($rows)) {
            foreach ($rows as $row) {
                // Znajdź wiersz z pasującym ID załącznika i go pomiń
                if (!$deleted && isset($row['attachment_file']) && $row['attachment_file'] == $attachment_id) {
                    $deleted = true;
                } else {
                    $new_rows[] = $row;
                }
            }
        }

        if ($deleted) {
            // Zaktualizuj pole repeater nową tablicą wierszy
            update_field('field_wpmzf_activity_attachments', $new_rows, $activity_id);
        }

        wp_delete_attachment($attachment_id, true);

        wp_send_json_success(['message' => 'Załącznik usunięty.']);
    }



    /**
     * Aktualizuje podstawowe dane kontaktu.
     */
    public function update_contact_details()
    {
        check_ajax_referer('wpmzf_contact_view_nonce', 'security');

        $contact_id = isset($_POST['contact_id']) ? intval($_POST['contact_id']) : 0;

        if (!$contact_id || get_post_type($contact_id) !== 'contact' || !current_user_can('edit_post', $contact_id)) {
            wp_send_json_error(['message' => 'Brak uprawnień lub nieprawidłowe ID kontaktu.']);
            return;
        }

        // Aktualizacja tytułu (Imię i Nazwisko)
        if (isset($_POST['contact_name'])) {
            $contact_name = sanitize_text_field($_POST['contact_name']);
            if (!empty($contact_name)) {
                wp_update_post(['ID' => $contact_id, 'post_title' => $contact_name]);
            }
        }

        // 1. Aktualizacja prostych pól tekstowych i select
        $simple_fields = [
            'contact_position' => 'sanitize_text_field',
            'contact_email'    => 'sanitize_email',
            'contact_phone'    => 'sanitize_text_field',
            'contact_status'   => 'sanitize_text_field',
        ];

        foreach ($simple_fields as $field_name => $sanitize_callback) {
            if (isset($_POST[$field_name])) {
                $value = call_user_func($sanitize_callback, $_POST[$field_name]);
                update_field($field_name, $value, $contact_id);
            }
        }

        // Obsługa pola relacji z firmą
        $company_data = isset($_POST['contact_company']) ? sanitize_text_field(wp_unslash($_POST['contact_company'])) : null;
        $company_id_to_save = null;

        if (!empty($company_data)) {
            // Scenariusz 1: Otrzymaliśmy ID istniejącej firmy (jest to liczba)
            if (is_numeric($company_data)) {
                $company_id_to_save = intval($company_data);
            } 
            // Scenariusz 2: Otrzymaliśmy nazwę nowej firmy (nie jest to liczba)
            else {
                // Sprawdźmy na wszelki wypadek, czy firma o takiej nazwie już nie istnieje
                $existing_company = get_page_by_title($company_data, OBJECT, 'company');
                
                if ($existing_company) {
                    $company_id_to_save = $existing_company->ID;
                } else {
                    // Firma nie istnieje, więc ją tworzymy
                    $new_company_args = [
                        'post_title'  => $company_data,
                        'post_type'   => 'company',
                        'post_status' => 'publish',
                    ];
                    $new_company_id = wp_insert_post($new_company_args);
                    
                    if (!is_wp_error($new_company_id)) {
                        $company_id_to_save = $new_company_id;
                        // Opcjonalnie: można tu dodać domyślne pola dla nowej firmy, np. NIP
                        // update_field('company_nip', 'BRAK DANYCH', $new_company_id);
                    }
                }
            }
        }

        // Teraz zapisujemy relację do kontaktu używając $company_id_to_save
        // Zakładając, że pole relacji w ACF dla kontaktu ma klucz 'contact_company'
        if ($company_id_to_save) {
            update_field('field_wpmzf_contact_company_relation', $company_id_to_save, $contact_id);
        } else {
            // Jeśli firma została usunięta z pola, czyścimy wartość
            update_field('field_wpmzf_contact_company_relation', null, $contact_id);
        }

        // 3. Specjalna obsługa grupy pól "Adres"
        $address_data = [];
        // Zbieramy dane adresu z POST i mapujemy na nazwy sub-pól z definicji ACF
        if (isset($_POST['contact_street'])) {
            $address_data['street'] = sanitize_text_field($_POST['contact_street']);
        }
        if (isset($_POST['contact_postal_code'])) {
            $address_data['zip_code'] = sanitize_text_field($_POST['contact_postal_code']);
        }
        if (isset($_POST['contact_city'])) {
            $address_data['city'] = sanitize_text_field($_POST['contact_city']);
        }
        // Aktualizujemy całą grupę na raz, przekazując tablicę z danymi.
        update_field('contact_address', $address_data, $contact_id);

        // Przygotuj dane zwrotne dla firmy
        $company_html = '';
        if ($company_id_to_save) {
            $company_html = sprintf('<a href="%s">%s</a>', esc_url(get_edit_post_link($company_id_to_save)), esc_html(get_the_title($company_id_to_save)));
        }

        wp_send_json_success([
            'message' => 'Dane kontaktu zaktualizowane.',
            'company_html' => $company_html
        ]);
    }
}
