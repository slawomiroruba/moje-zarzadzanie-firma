<?php

class WPMZF_Ajax_Handler
{

    public function __construct()
    {
        // Hook do dodawania nowej aktywności
        add_action('wp_ajax_add_wpmzf_activity', array($this, 'add_activity'));
        // Hook do pobierania listy aktywności dla osoby
        add_action('wp_ajax_get_wpmzf_activities', array($this, 'get_activities'));
        // Hook do uploadu załączników
        add_action('wp_ajax_wpmzf_upload_attachment', array($this, 'upload_attachment'));
        // Hook do usuwania aktywności
        add_action('wp_ajax_delete_wpmzf_activity', array($this, 'delete_activity'));
        // Hook do aktualizacji aktywności
        add_action('wp_ajax_update_wpmzf_activity', array($this, 'update_activity'));
        // Hook do usuwania pojedynczego załącznika
        add_action('wp_ajax_delete_wpmzf_attachment', array($this, 'delete_attachment'));
        // Hook do aktualizacji danych osoby
        add_action('wp_ajax_wpmzf_update_person_details', array($this, 'update_person_details'));
        // Hook do archiwizacji osoby
        add_action('wp_ajax_wpmzf_toggle_person_archive', array($this, 'toggle_person_archive'));
        // Rejestracja punktu końcowego dla zalogowanych użytkowników
        add_action('wp_ajax_wpmzf_search_companies',  array($this, 'wpmzf_search_companies_ajax_handler'));
        // Rejestracja punktu końcowego dla niezalogowanych użytkowników
        add_action('wp_ajax_nopriv_wpmzf_search_companies', array($this, 'wpmzf_search_companies_ajax_handler'));
        // Hook do pobierania metadanych linków dla bogatych kart
        add_action('wp_ajax_wpmzf_get_link_metadata', array($this, 'get_link_metadata'));
        // Hooks dla zadań
        add_action('wp_ajax_add_wpmzf_task', array($this, 'add_task'));
        add_action('wp_ajax_get_wpmzf_tasks', array($this, 'get_tasks'));
        add_action('wp_ajax_get_wpmzf_task_date', array($this, 'get_task_date'));
        add_action('wp_ajax_update_wpmzf_task_status', array($this, 'update_task_status'));
        add_action('wp_ajax_delete_wpmzf_task', array($this, 'delete_task'));
        // Hooks dla projektów/zleceń
        add_action('wp_ajax_add_wpmzf_project', array($this, 'add_project'));
        add_action('wp_ajax_get_wpmzf_projects', array($this, 'get_projects_for_person'));
    }

    /**
     * Obsługuje zapytanie AJAX do wyszukiwania firm na podstawie nazwy lub NIP-u.
     */
    public function wpmzf_search_companies_ajax_handler()
    {
        // Bezpieczeństwo: sprawdzanie nonca
        check_ajax_referer('wpmzf_person_view_nonce', 'security');

        // Pobranie i zwalidowanie terminu wyszukiwania
        $search_term = isset($_REQUEST['term']) ? sanitize_text_field(wp_unslash($_REQUEST['term'])) : '';

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
        check_ajax_referer('wpmzf_person_view_nonce', 'security');

        // 2. Walidacja i sanitazyacja danych
        $person_id = isset($_POST['person_id']) ? intval($_POST['person_id']) : 0;
        $content = isset($_POST['content']) ? wp_kses_post($_POST['content']) : '';
        $activity_type = isset($_POST['activity_type']) ? sanitize_text_field($_POST['activity_type']) : 'note';
        $activity_date = isset($_POST['activity_date']) ? sanitize_text_field($_POST['activity_date']) : current_time('mysql');

        if (!$person_id || empty($content)) {
            wp_send_json_error(array('message' => 'Brak wymaganych danych (ID osoby, treść).'));
            return;
        }

        // 3. Tworzenie nowego posta typu 'activity'
        $activity_post = array(
            'post_title'   => 'Aktywność dla ' . get_the_title($person_id) . ' - ' . $activity_date,
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
            update_field('field_wpmzf_activity_related_person', $person_id, $activity_id);

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
     * Logika pobierania aktywności dla danej osoby.
     */
    public function get_activities()
    {
        check_ajax_referer('wpmzf_person_view_nonce', 'security');

        $person_id = isset($_GET['person_id']) ? intval($_GET['person_id']) : 0;
        if (!$person_id) {
            wp_send_json_error(['message' => 'Nieprawidłowe ID osoby.']);
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
                    'key' => 'related_person',
                    'value' => $person_id,
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
        check_ajax_referer('wpmzf_person_view_nonce', 'security');

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
        check_ajax_referer('wpmzf_person_view_nonce', 'security');

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
        check_ajax_referer('wpmzf_person_view_nonce', 'security');

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
        check_ajax_referer('wpmzf_person_view_nonce', 'security');

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
     * Aktualizuje podstawowe dane osoby.
     */
    public function update_person_details()
    {
        check_ajax_referer('wpmzf_person_view_nonce', 'security');

        $person_id = isset($_POST['person_id']) ? intval($_POST['person_id']) : 0;

        if (!$person_id || get_post_type($person_id) !== 'person' || !current_user_can('edit_post', $person_id)) {
            wp_send_json_error(['message' => 'Brak uprawnień lub nieprawidłowe ID osoby.']);
            return;
        }

        // Aktualizacja tytułu (Imię i Nazwisko)
        if (isset($_POST['person_name'])) {
            $person_name = sanitize_text_field($_POST['person_name']);
            if (!empty($person_name)) {
                wp_update_post(['ID' => $person_id, 'post_title' => $person_name]);
            }
        }

        // 1. Aktualizacja prostych pól tekstowych i select
        $simple_fields = [
            'person_position' => 'sanitize_text_field',
            'person_status'   => 'sanitize_text_field',
        ];

        foreach ($simple_fields as $field_name => $sanitize_callback) {
            if (isset($_POST[$field_name])) {
                $value = call_user_func($sanitize_callback, $_POST[$field_name]);
                update_field($field_name, $value, $person_id);
            }
        }

        // Obsługa pola relacji z firmą
        $company_data = isset($_POST['person_company']) ? sanitize_text_field(wp_unslash($_POST['person_company'])) : null;
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

        // Teraz zapisujemy relację do osoby używając $company_id_to_save
        // Zakładając, że pole relacji w ACF dla osoby ma klucz 'person_company'
        if ($company_id_to_save) {
            update_field('field_wpmzf_person_company_relation', $company_id_to_save, $person_id);
        } else {
            // Jeśli firma została usunięta z pola, czyścimy wartość
            update_field('field_wpmzf_person_company_relation', null, $person_id);
        }

        // 3. Specjalna obsługa grupy pól "Adres"
        $address_data = [];
        // Zbieramy dane adresu z POST i mapujemy na nazwy sub-pól z definicji ACF
        if (isset($_POST['person_street'])) {
            $address_data['street'] = sanitize_text_field($_POST['person_street']);
        }
        if (isset($_POST['person_postal_code'])) {
            $address_data['zip_code'] = sanitize_text_field($_POST['person_postal_code']);
        }
        if (isset($_POST['person_city'])) {
            $address_data['city'] = sanitize_text_field($_POST['person_city']);
        }
        // Aktualizujemy całą grupę na raz, przekazując tablicę z danymi.
        update_field('person_address', $address_data, $person_id);

        // Przygotuj dane zwrotne dla firmy
        $company_html = '';
        if ($company_id_to_save) {
            $company_html = sprintf('<a href="%s">%s</a>', esc_url(get_edit_post_link($company_id_to_save)), esc_html(get_the_title($company_id_to_save)));
        }
        
        // Przygotuj odświeżone HTML dla kontaktów
        $contacts_html = [
            'emails' => WPMZF_Contact_Helper::render_emails_display(WPMZF_Contact_Helper::get_person_emails($person_id)),
            'phones' => WPMZF_Contact_Helper::render_phones_display(WPMZF_Contact_Helper::get_person_phones($person_id))
        ];

        wp_send_json_success([
            'message' => 'Dane osoby zaktualizowane.',
            'company_html' => $company_html,
            'contacts_html' => $contacts_html
        ]);
    }

    /**
     * Pobiera metadane strony (tytuł, favicon) dla danego URL
     */
    public function get_link_metadata()
    {
        check_ajax_referer('wpmzf_person_view_nonce', 'security');

        $url = isset($_POST['url']) ? esc_url_raw($_POST['url']) : '';
        
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            wp_send_json_error(['message' => 'Nieprawidłowy URL.']);
            return;
        }

        // Sprawdź czy metadane już istnieją w cache (opcjonalnie)
        $cache_key = 'wpmzf_link_metadata_' . md5($url);
        $cached_data = get_transient($cache_key);
        
        if ($cached_data !== false) {
            wp_send_json_success($cached_data);
            return;
        }

        $metadata = $this->fetch_link_metadata($url);
        
        if ($metadata) {
            // Cache na 24 godziny
            set_transient($cache_key, $metadata, DAY_IN_SECONDS);
            wp_send_json_success($metadata);
        } else {
            wp_send_json_error(['message' => 'Nie udało się pobrać metadanych strony.']);
        }
    }

    /**
     * Pobiera metadane strony (tytuł, favicon, opis) z danego URL
     */
    private function fetch_link_metadata($url)
    {
        $response = wp_remote_get($url, [
            'timeout' => 10,
            'user-agent' => 'Mozilla/5.0 (compatible; WordPress Link Preview Bot)'
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            return false;
        }

        $dom = new DOMDocument();
        @$dom->loadHTML($body);
        $xpath = new DOMXPath($dom);

        $metadata = [
            'url' => $url,
            'title' => '',
            'description' => '',
            'favicon' => ''
        ];

        // Pobierz tytuł strony
        $title_nodes = $xpath->query('//title');
        if ($title_nodes->length > 0) {
            $metadata['title'] = trim($title_nodes->item(0)->textContent);
        }

        // Pobierz opis (meta description)
        $desc_nodes = $xpath->query('//meta[@name="description"]/@content');
        if ($desc_nodes->length > 0) {
            $metadata['description'] = trim($desc_nodes->item(0)->textContent);
        }

        // Pobierz Open Graph tytuł jeśli dostępny
        $og_title_nodes = $xpath->query('//meta[@property="og:title"]/@content');
        if ($og_title_nodes->length > 0 && empty($metadata['title'])) {
            $metadata['title'] = trim($og_title_nodes->item(0)->textContent);
        }

        // Pobierz Open Graph opis jeśli dostępny
        $og_desc_nodes = $xpath->query('//meta[@property="og:description"]/@content');
        if ($og_desc_nodes->length > 0 && empty($metadata['description'])) {
            $metadata['description'] = trim($og_desc_nodes->item(0)->textContent);
        }

        // Pobierz favicon
        $parsed_url = parse_url($url);
        $base_url = $parsed_url['scheme'] . '://' . $parsed_url['host'];
        
        // Szukaj favicon w różnych miejscach
        $favicon_queries = [
            '//link[@rel="icon"]/@href',
            '//link[@rel="shortcut icon"]/@href',
            '//link[@rel="apple-touch-icon"]/@href'
        ];

        foreach ($favicon_queries as $query) {
            $favicon_nodes = $xpath->query($query);
            if ($favicon_nodes->length > 0) {
                $favicon_url = $favicon_nodes->item(0)->textContent;
                // Jeśli to relatywny URL, zrób z niego absolutny
                if (strpos($favicon_url, 'http') !== 0) {
                    if (strpos($favicon_url, '/') === 0) {
                        $favicon_url = $base_url . $favicon_url;
                    } else {
                        $favicon_url = $base_url . '/' . $favicon_url;
                    }
                }
                $metadata['favicon'] = $favicon_url;
                break;
            }
        }

        // Fallback dla favicon
        if (empty($metadata['favicon'])) {
            $metadata['favicon'] = $base_url . '/favicon.ico';
        }

        // Fallback dla tytułu
        if (empty($metadata['title'])) {
            $metadata['title'] = $parsed_url['host'];
        }

        // Ogranicz długość opisu
        if (strlen($metadata['description']) > 150) {
            $metadata['description'] = substr($metadata['description'], 0, 147) . '...';
        }

        return $metadata;
    }

    /**
     * Dodaje nowe zadanie
     */
    public function add_task()
    {
        check_ajax_referer('wpmzf_task_nonce', 'wpmzf_task_security');

        $person_id = isset($_POST['person_id']) ? intval($_POST['person_id']) : 0;
        $task_title = isset($_POST['task_title']) ? sanitize_text_field($_POST['task_title']) : '';
        $task_due_date = isset($_POST['task_due_date']) ? sanitize_text_field($_POST['task_due_date']) : '';

        if (!$person_id || empty($task_title)) {
            wp_send_json_error(['message' => 'Brak wymaganych danych.']);
            return;
        }

        if (get_post_type($person_id) !== 'person') {
            wp_send_json_error(['message' => 'Nieprawidłowe ID osoby.']);
            return;
        }

        // Tworzenie zadania
        $task_data = [
            'post_title'   => $task_title,
            'post_content' => '',
            'post_status'  => 'publish',
            'post_type'    => 'task',
            'post_author'  => get_current_user_id(),
        ];

        $task_id = wp_insert_post($task_data);

        if ($task_id && !is_wp_error($task_id)) {
            // Zapisanie pól ACF
            update_field('task_status', 'Do zrobienia', $task_id);
            update_field('task_assigned_person', $person_id, $task_id);
            update_field('task_start_date', current_time('Y-m-d H:i:s'), $task_id);
            
            // Zapisanie daty zakończenia jeśli została podana
            if (!empty($task_due_date)) {
                // Konwertujemy format datetime-local do formatu MySQL
                $due_date_formatted = date('Y-m-d H:i:s', strtotime($task_due_date));
                update_field('task_end_date', $due_date_formatted, $task_id);
            }
            
            wp_send_json_success(['message' => 'Zadanie dodane pomyślnie.', 'task_id' => $task_id]);
        } else {
            wp_send_json_error(['message' => 'Błąd podczas tworzenia zadania.']);
        }
    }

    /**
     * Pobiera zadania dla danej osoby
     */
    public function get_tasks()
    {
        check_ajax_referer('wpmzf_task_nonce', 'wpmzf_task_security');

        $person_id = isset($_POST['person_id']) ? intval($_POST['person_id']) : 0;
        
        if (!$person_id) {
            wp_send_json_error(['message' => 'Nieprawidłowe ID osoby.']);
            return;
        }

        $args = [
            'post_type' => 'task',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => 'task_assigned_person',
                    'value' => $person_id,
                    'compare' => '='
                ]
            ],
            'orderby' => 'date',
            'order' => 'DESC'
        ];

        $tasks_query = new WP_Query($args);
        $open_tasks = [];
        $closed_tasks = [];

        if ($tasks_query->have_posts()) {
            while ($tasks_query->have_posts()) {
                $tasks_query->the_post();
                $task_id = get_the_ID();
                
                $task_status = get_field('task_status', $task_id) ?: 'Do zrobienia';
                $start_date = get_field('task_start_date', $task_id);
                $end_date = get_field('task_end_date', $task_id);
                $description = get_field('task_description', $task_id);

                $task_data = [
                    'id' => $task_id,
                    'title' => get_the_title(),
                    'status' => $task_status,
                    'description' => $description,
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                    'due_date' => $end_date, // Alias for JavaScript compatibility
                    'priority' => $this->get_task_priority($end_date),
                    'edit_link' => get_edit_post_link($task_id)
                ];

                if ($task_status === 'Zrobione') {
                    $closed_tasks[] = $task_data;
                } else {
                    $open_tasks[] = $task_data;
                }
            }
        }
        wp_reset_postdata();

        // Sortowanie otwartych zadań według priorytetu (spóźnione, dzisiejsze, przyszłe)
        usort($open_tasks, function($a, $b) {
            // Najpierw sortujemy według priorytetu
            $priority_order = ['overdue' => 0, 'today' => 1, 'upcoming' => 2];
            $priority_diff = $priority_order[$a['priority']] - $priority_order[$b['priority']];
            
            if ($priority_diff !== 0) {
                return $priority_diff;
            }
            
            // Jeśli priorytet jest taki sam, sortujemy według daty
            if (!empty($a['end_date']) && !empty($b['end_date'])) {
                return strtotime($a['end_date']) - strtotime($b['end_date']);
            }
            
            // Zadania bez daty na końcu
            if (empty($a['end_date']) && !empty($b['end_date'])) {
                return 1;
            }
            
            if (!empty($a['end_date']) && empty($b['end_date'])) {
                return -1;
            }
            
            return 0;
        });

        wp_send_json_success([
            'open_tasks' => $open_tasks,
            'closed_tasks' => $closed_tasks
        ]);
    }

    /**
     * Pobiera datę zadania
     */
    public function get_task_date()
    {
        check_ajax_referer('wpmzf_task_nonce', 'wpmzf_task_security');

        $task_id = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;
        
        if (!$task_id) {
            wp_send_json_error(['message' => 'Nieprawidłowe ID zadania.']);
            return;
        }

        if (get_post_type($task_id) !== 'task') {
            wp_send_json_error(['message' => 'Nieprawidłowe ID zadania.']);
            return;
        }

        $due_date = get_field('task_end_date', $task_id);
        
        wp_send_json_success([
            'due_date' => $due_date
        ]);
    }

    /**
     * Określa priorytet zadania na podstawie daty zakończenia
     */
    private function get_task_priority($end_date)
    {
        if (empty($end_date)) {
            return 'upcoming';
        }

        $now = current_time('timestamp');
        $task_timestamp = strtotime($end_date);

        // Porównujemy z dokładnością do godziny
        if ($task_timestamp < $now) {
            return 'overdue';
        } 
        
        // Sprawdzamy czy to dzisiaj (do końca dnia)
        $today_end = strtotime('today 23:59:59', $now);
        if ($task_timestamp <= $today_end) {
            return 'today';
        }
        
        return 'upcoming';
    }

    /**
     * Aktualizuje status zadania
     */
    public function update_task_status()
    {
        check_ajax_referer('wpmzf_task_nonce', 'wpmzf_task_security');

        $task_id = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;
        $new_status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
        $new_title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
        $new_due_date = isset($_POST['due_date']) ? sanitize_text_field($_POST['due_date']) : '';

        if (!$task_id) {
            wp_send_json_error(['message' => 'Brak ID zadania.']);
            return;
        }

        if (get_post_type($task_id) !== 'task') {
            wp_send_json_error(['message' => 'Nieprawidłowe ID zadania.']);
            return;
        }

        $updated_fields = [];

        // Aktualizacja statusu
        if (!empty($new_status)) {
            $allowed_statuses = ['Do zrobienia', 'W toku', 'Zrobione'];
            if (!in_array($new_status, $allowed_statuses)) {
                wp_send_json_error(['message' => 'Nieprawidłowy status.']);
                return;
            }

            update_field('task_status', $new_status, $task_id);
            $updated_fields[] = 'status';

            // Jeśli zadanie zostało zakończone, ustaw datę zakończenia
            if ($new_status === 'Zrobione' && !get_field('task_end_date', $task_id)) {
                update_field('task_end_date', current_time('Y-m-d H:i:s'), $task_id);
            }
        }

        // Aktualizacja tytułu
        if (!empty($new_title)) {
            $post_data = array(
                'ID' => $task_id,
                'post_title' => $new_title
            );
            
            $result = wp_update_post($post_data);
            if (is_wp_error($result)) {
                wp_send_json_error(['message' => 'Błąd podczas aktualizacji tytułu zadania.']);
                return;
            }
            
            $updated_fields[] = 'title';
        }

        // Aktualizacja daty zakończenia
        if (isset($_POST['due_date'])) {
            if (!empty($new_due_date)) {
                // Konwertujemy format datetime-local do formatu MySQL
                $due_date_formatted = date('Y-m-d H:i:s', strtotime($new_due_date));
                update_field('task_end_date', $due_date_formatted, $task_id);
            } else {
                // Usuń datę jeśli pole jest puste
                update_field('task_end_date', '', $task_id);
            }
            $updated_fields[] = 'due_date';
        }

        if (empty($updated_fields)) {
            wp_send_json_error(['message' => 'Brak danych do aktualizacji.']);
            return;
        }

        $message = 'Zadanie zostało zaktualizowane.';
        if (in_array('status', $updated_fields) && in_array('title', $updated_fields) && in_array('due_date', $updated_fields)) {
            $message = 'Status, tytuł i termin zadania zostały zaktualizowane.';
        } elseif (in_array('status', $updated_fields) && in_array('title', $updated_fields)) {
            $message = 'Status i tytuł zadania zostały zaktualizowane.';
        } elseif (in_array('status', $updated_fields) && in_array('due_date', $updated_fields)) {
            $message = 'Status i termin zadania zostały zaktualizowane.';
        } elseif (in_array('title', $updated_fields) && in_array('due_date', $updated_fields)) {
            $message = 'Tytuł i termin zadania zostały zaktualizowane.';
        } elseif (in_array('status', $updated_fields)) {
            $message = 'Status zadania został zaktualizowany.';
        } elseif (in_array('title', $updated_fields)) {
            $message = 'Tytuł zadania został zaktualizowany.';
        } elseif (in_array('due_date', $updated_fields)) {
            $message = 'Termin zadania został zaktualizowany.';
        }

        wp_send_json_success(['message' => $message]);
    }

    /**
     * Usuwa zadanie
     */
    public function delete_task()
    {
        check_ajax_referer('wpmzf_task_nonce', 'wpmzf_task_security');

        $task_id = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;

        if (!$task_id || get_post_type($task_id) !== 'task') {
            wp_send_json_error(['message' => 'Nieprawidłowe ID zadania.']);
            return;
        }

        if (!current_user_can('delete_post', $task_id)) {
            wp_send_json_error(['message' => 'Brak uprawnień.']);
            return;
        }

        $result = wp_delete_post($task_id, true);

        if ($result) {
            wp_send_json_success(['message' => 'Zadanie usunięte.']);
        } else {
            wp_send_json_error(['message' => 'Nie udało się usunąć zadania.']);
        }
    }

    /**
     * Przełącza status archiwizacji osoby
     */
    public function toggle_person_archive()
    {
        // Sprawdzenie nonce dla bezpieczeństwa
        if (!wp_verify_nonce($_POST['security'] ?? '', 'wpmzf_person_view_nonce')) {
            wp_send_json_error(['message' => 'Błąd bezpieczeństwa.']);
            return;
        }

        $person_id = intval($_POST['person_id'] ?? 0);
        
        if (!$person_id) {
            wp_send_json_error(['message' => 'Nieprawidłowe ID osoby.']);
            return;
        }

        // Sprawdzenie czy wpis istnieje i jest typu 'person'
        if (get_post_type($person_id) !== 'person') {
            wp_send_json_error(['message' => 'Nieprawidłowe ID osoby.']);
            return;
        }

        // Sprawdzenie uprawnień
        if (!current_user_can('edit_post', $person_id)) {
            wp_send_json_error(['message' => 'Brak uprawnień do edycji tej osoby.']);
            return;
        }

        // Pobranie obecnego statusu
        $current_status = get_field('person_status', $person_id) ?: 'active';
        
        // Przełączenie statusu
        $new_status = ($current_status === 'archived') ? 'active' : 'archived';
        
        // Aktualizacja statusu
        $updated = update_field('person_status', $new_status, $person_id);
        
        if ($updated !== false) {
            $status_labels = [
                'active' => 'Aktywny',
                'inactive' => 'Nieaktywny', 
                'archived' => 'Zarchiwizowany'
            ];
            
            wp_send_json_success([
                'message' => $new_status === 'archived' ? 'Osoba została zarchiwizowana.' : 'Osoba została przywrócona z archiwum.',
                'new_status' => $new_status,
                'status_label' => $status_labels[$new_status]
            ]);
        } else {
            wp_send_json_error(['message' => 'Nie udało się zaktualizować statusu osoby.']);
        }
    }

    /**
     * Dodaje nowy projekt/zlecenie
     */
    public function add_project()
    {
        check_ajax_referer('wpmzf_person_view_nonce', 'security');

        $person_id = isset($_POST['person_id']) ? intval($_POST['person_id']) : 0;
        $project_name = isset($_POST['project_name']) ? sanitize_text_field($_POST['project_name']) : '';
        $project_description = isset($_POST['project_description']) ? wp_kses_post($_POST['project_description']) : '';
        $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
        $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';
        $budget = isset($_POST['budget']) ? sanitize_text_field($_POST['budget']) : '';
        $company_id = isset($_POST['company_id']) ? intval($_POST['company_id']) : 0;

        if (!$person_id || empty($project_name)) {
            wp_send_json_error(['message' => 'Brak wymaganych danych (ID osoby, nazwa projektu).']);
            return;
        }

        if (get_post_type($person_id) !== 'person') {
            wp_send_json_error(['message' => 'Nieprawidłowe ID osoby.']);
            return;
        }

        // Tworzenie nowego projektu
        $project_data = [
            'post_title'   => $project_name,
            'post_content' => $project_description,
            'post_status'  => 'publish',
            'post_type'    => 'project',
            'post_author'  => get_current_user_id(),
        ];

        $project_id = wp_insert_post($project_data);

        if ($project_id && !is_wp_error($project_id)) {
            // Zapisanie pól ACF
            update_field('project_status', 'Planowanie', $project_id);
            
            // Przypisanie osoby do projektu
            update_field('project_person', array($person_id), $project_id);
            
            // Zapisanie dat jeśli zostały podane
            if (!empty($start_date)) {
                update_field('start_date', $start_date, $project_id);
            }
            
            if (!empty($end_date)) {
                update_field('end_date', $end_date, $project_id);
            }
            
            // Zapisanie budżetu jeśli został podany
            if (!empty($budget)) {
                update_field('budget', $budget, $project_id);
            }
            
            // Przypisanie firmy jeśli została wybrana
            if ($company_id) {
                update_field('project_company', array($company_id), $project_id);
            }
            
            wp_send_json_success([
                'message' => 'Projekt został dodany pomyślnie.',
                'project_id' => $project_id,
                'project_name' => $project_name
            ]);
        } else {
            wp_send_json_error(['message' => 'Błąd podczas tworzenia projektu.']);
        }
    }

    /**
     * Pobiera projekty dla danej osoby
     */
    public function get_projects_for_person()
    {
        check_ajax_referer('wpmzf_person_view_nonce', 'security');

        $person_id = isset($_POST['person_id']) ? intval($_POST['person_id']) : 0;
        
        if (!$person_id) {
            wp_send_json_error(['message' => 'Nieprawidłowe ID osoby.']);
            return;
        }

        $active_projects = WPMZF_Project::get_active_projects_by_person($person_id);
        $completed_projects = WPMZF_Project::get_completed_projects_by_person($person_id);

        $active_projects_data = [];
        $completed_projects_data = [];

        foreach ($active_projects as $project) {
            $deadline = get_field('end_date', $project->id);
            $deadline_text = $deadline ? date('d.m.Y', strtotime($deadline)) : 'Brak terminu';
            
            $active_projects_data[] = [
                'id' => $project->id,
                'name' => $project->name,
                'deadline' => $deadline_text,
                'deadline_raw' => $deadline,
                'edit_link' => get_edit_post_link($project->id)
            ];
        }

        foreach ($completed_projects as $project) {
            $deadline = get_field('end_date', $project->id);
            $deadline_text = $deadline ? date('d.m.Y', strtotime($deadline)) : 'Brak terminu';
            
            $completed_projects_data[] = [
                'id' => $project->id,
                'name' => $project->name,
                'deadline' => $deadline_text,
                'deadline_raw' => $deadline,
                'edit_link' => get_edit_post_link($project->id)
            ];
        }

        wp_send_json_success([
            'active_projects' => $active_projects_data,
            'completed_projects' => $completed_projects_data
        ]);
    }
}
