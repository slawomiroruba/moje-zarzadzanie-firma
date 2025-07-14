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
        // Hook do archiwizacji firmy
        add_action('wp_ajax_wpmzf_toggle_company_archive', array($this, 'toggle_company_archive'));
        // Hooks dla firm
        add_action('wp_ajax_wpmzf_save_company', array($this, 'save_company'));
        add_action('wp_ajax_wpmzf_get_company', array($this, 'get_company'));
        add_action('wp_ajax_wpmzf_delete_company', array($this, 'delete_company'));
        // Hooks dla osób
        add_action('wp_ajax_wpmzf_save_person', array($this, 'save_person'));
        add_action('wp_ajax_wpmzf_get_person', array($this, 'get_person'));
        add_action('wp_ajax_wpmzf_delete_person', array($this, 'delete_person'));
        // Rejestracja punktu końcowego dla zalogowanych użytkowników
        add_action('wp_ajax_wpmzf_search_companies',  array($this, 'wpmzf_search_companies_ajax_handler'));
        // Usunięto 'wp_ajax_nopriv_' dla bezpieczeństwa - wyszukiwanie firm tylko dla zalogowanych
        // Hook do wyszukiwania polecających
        add_action('wp_ajax_wpmzf_search_referrers', array($this, 'wpmzf_search_referrers_ajax_handler'));
        // Hook do pobierania metadanych linków dla bogatych kart
        add_action('wp_ajax_wpmzf_get_link_metadata', array($this, 'get_link_metadata'));
        // Hooks dla ważnych linków
        add_action('wp_ajax_wpmzf_add_important_link', array($this, 'add_important_link'));
        add_action('wp_ajax_wpmzf_get_important_links', array($this, 'get_important_links'));
        add_action('wp_ajax_wpmzf_update_important_link', array($this, 'update_important_link'));
        add_action('wp_ajax_wpmzf_delete_important_link', array($this, 'delete_important_link'));
        // Hooks dla zadań
        add_action('wp_ajax_add_wpmzf_task', array($this, 'add_task'));
        add_action('wp_ajax_get_wpmzf_tasks', array($this, 'get_tasks'));
        add_action('wp_ajax_get_wpmzf_task_date', array($this, 'get_task_date'));
        add_action('wp_ajax_update_wpmzf_task_status', array($this, 'update_task_status'));
        // Hook dla transkrypcji
        add_action('wp_ajax_get_wpmzf_full_transcription', array($this, 'get_full_transcription'));
        add_action('wp_ajax_update_wpmzf_task_assignee', array($this, 'update_task_assignee'));
        add_action('wp_ajax_wpmzf_get_users_for_task', array($this, 'get_users_for_task'));
        add_action('wp_ajax_delete_wpmzf_task', array($this, 'delete_task'));
        // Hooks dla projektów/zleceń
        add_action('wp_ajax_add_wpmzf_project', array($this, 'add_project'));
        add_action('wp_ajax_get_wpmzf_projects', array($this, 'get_projects_for_person'));
        
        // Hooks dla widoku projektu
        add_action('wp_ajax_wpmzf_update_project', array($this, 'update_project'));
        add_action('wp_ajax_wpmzf_get_project_tasks', array($this, 'get_project_tasks'));
        add_action('wp_ajax_wpmzf_add_project_task', array($this, 'add_project_task'));
        add_action('wp_ajax_wpmzf_add_project_activity', array($this, 'add_project_activity'));
        add_action('wp_ajax_wpmzf_delete_activity', array($this, 'delete_activity'));
    }

    /**
     * Obsługuje zapytanie AJAX do wyszukiwania firm na podstawie nazwy lub NIP-u.
     */
    public function wpmzf_search_companies_ajax_handler()
    {
        // Rate limiting
        if (WPMZF_Rate_Limiter::is_rate_limited('search')) {
            wp_send_json_error(['message' => 'Za dużo żądań wyszukiwania. Spróbuj ponownie za chwilę.'], 429);
            return;
        }
        
        // Sprawdzenie uprawnień
        if (!current_user_can('edit_posts')) {
            WPMZF_Logger::log_security_violation('search_companies - insufficient permissions');
            wp_send_json_error(['message' => 'Brak uprawnień.']);
            return;
        }
        
        // Bezpieczeństwo: sprawdzanie nonca
        check_ajax_referer('wpmzf_person_view_nonce', 'security');

        // Increment rate limit counter
        WPMZF_Rate_Limiter::increment_counter('search');

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
     * Handler AJAX do wyszukiwania polecających (osoby i firmy)
     */
    public function wpmzf_search_referrers_ajax_handler()
    {
        // Sprawdzenie uprawnień
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Brak uprawnień.']);
            return;
        }
        
        // Weryfikacja bezpieczeństwa
        if (!check_ajax_referer('wpmzf_person_view_nonce', 'security', false)) {
            wp_send_json_error(['message' => 'Błąd weryfikacji bezpieczeństwa.']);
            return;
        }

        $search_term = sanitize_text_field($_GET['term'] ?? '');

        if (empty($search_term)) {
            wp_send_json_success([]);
        }

        $results = [];

        // Wyszukiwanie osób
        $args_persons = [
            'post_type'      => 'person',
            'post_status'    => 'publish',
            's'              => $search_term,
            'posts_per_page' => 10,
        ];

        $query_persons = new WP_Query($args_persons);

        if ($query_persons->have_posts()) {
            while ($query_persons->have_posts()) {
                $query_persons->the_post();
                $results[] = [
                    'id'   => get_the_ID(),
                    'text' => '👤 ' . get_the_title(),
                ];
            }
        }
        wp_reset_postdata();

        // Wyszukiwanie firm
        $args_companies = [
            'post_type'      => 'company',
            'post_status'    => 'publish',
            's'              => $search_term,
            'posts_per_page' => 10,
        ];

        $query_companies = new WP_Query($args_companies);

        if ($query_companies->have_posts()) {
            while ($query_companies->have_posts()) {
                $query_companies->the_post();
                $results[] = [
                    'id'   => get_the_ID(),
                    'text' => '🏢 ' . get_the_title(),
                ];
            }
        }
        wp_reset_postdata();

        // Zwracamy wyniki w formacie JSON
        wp_send_json_success($results);
    }



    /**
     * Logika dodawania nowej aktywności.
     */
    public function add_activity()
    {
        // 1. Bezpieczeństwo - sprawdź różne nonce names
        $nonce_verified = false;
        
        if (isset($_POST['wpmzf_note_security']) && wp_verify_nonce($_POST['wpmzf_note_security'], 'wpmzf_person_view_nonce')) {
            $nonce_verified = true;
        } elseif (isset($_POST['wpmzf_email_security']) && wp_verify_nonce($_POST['wpmzf_email_security'], 'wpmzf_person_view_nonce')) {
            $nonce_verified = true;
        } elseif (isset($_POST['security']) && wp_verify_nonce($_POST['security'], 'wpmzf_person_view_nonce')) {
            $nonce_verified = true;
        }
        
        if (!$nonce_verified) {
            wp_send_json_error(array('message' => 'Błąd autoryzacji.'));
            return;
        }

        // Debugowanie - sprawdźmy wszystkie dane które przychodzą
        error_log('WPMZF add_activity: POST data: ' . print_r($_POST, true));
        error_log('WPMZF add_activity: FILES data: ' . print_r($_FILES, true));

        // 2. Walidacja i sanitazyacja danych - obsługa zarówno osób jak i firm
        $person_id = isset($_POST['person_id']) ? intval($_POST['person_id']) : 0;
        $company_id = isset($_POST['company_id']) ? intval($_POST['company_id']) : 0;
        $content = isset($_POST['content']) ? wp_kses_post($_POST['content']) : '';
        $activity_type = isset($_POST['activity_type']) ? sanitize_text_field($_POST['activity_type']) : 'note';
        $activity_date = isset($_POST['activity_date']) ? sanitize_text_field($_POST['activity_date']) : current_time('mysql');

        // Debugowanie wartości
        error_log('WPMZF add_activity: person_id=' . $person_id . ', company_id=' . $company_id . ', content_length=' . strlen($content));

        // Sprawdzenie czy mamy ID osoby lub firmy
        if (!$person_id && !$company_id) {
            error_log('WPMZF add_activity: ERROR - No person_id or company_id provided');
            wp_send_json_error(array('message' => 'Brak wymaganych danych (ID osoby lub firmy).'));
            return;
        }

        // *** NOWA LOGIKA DLA E-MAILI ***
        if ($activity_type === 'email') {
            $email_to = sanitize_text_field($_POST['email_to'] ?? '');
            $email_subject = sanitize_text_field($_POST['email_subject'] ?? '');
            $email_content = wp_kses_post($_POST['content'] ?? '');
            $email_cc = sanitize_text_field($_POST['email_cc'] ?? '');
            $email_bcc = sanitize_text_field($_POST['email_bcc'] ?? '');
            
            if (empty($email_to) || empty($email_subject)) {
                wp_send_json_error(['message' => 'Pola "Do" i "Temat" są wymagane dla e-maila.']);
                return;
            }

            // Ustalenie tytułu i powiązania
            if ($person_id) {
                $entity_title = get_the_title($person_id);
                $entity_type = 'person';
                $entity_id = $person_id;
            } else {
                $entity_title = get_the_title($company_id);
                $entity_type = 'company';
                $entity_id = $company_id;
            }

            // 1. Zapisz aktywność z informacją "W kolejce"
            $activity_post = [
                'post_title'   => 'Email w kolejce: ' . $email_subject,
                'post_content' => $email_content,
                'post_status'  => 'publish',
                'post_author'  => get_current_user_id(),
                'post_type'    => 'activity',
            ];
            $activity_id = wp_insert_post($activity_post);
            
            if ($activity_id && !is_wp_error($activity_id)) {
                // Zapisz metadane aktywności
                update_field('activity_type', 'email', $activity_id);
                update_field('activity_date', $activity_date, $activity_id);
                
                // NOWA LOGIKA: Zapisujemy wszystkie powiązania w jednym polu
                $related_objects = [];
                if ($person_id) {
                    $related_objects[] = $person_id;
                }
                if ($company_id) {
                    $related_objects[] = $company_id;
                }
                
                if (!empty($related_objects)) {
                    update_field('related_objects', $related_objects, $activity_id);
                }

                // 2. Dodaj e-mail do kolejki
                $email_service = new WPMZF_Email_Service();
                $result = $email_service->queue_email(
                    get_current_user_id(),
                    $email_to,
                    $email_subject,
                    $email_content,
                    $email_cc,
                    $email_bcc,
                    ['activity_id' => $activity_id] // Powiąż z aktywnością
                );

                if (is_wp_error($result)) {
                    // Coś poszło nie tak - poinformuj użytkownika
                    wp_update_post(['ID' => $activity_id, 'post_title' => 'Błąd kolejkowania: ' . $email_subject]);
                    wp_send_json_error(['message' => $result->get_error_message()]);
                } else {
                    wp_send_json_success(['message' => 'E-mail został dodany do kolejki wysyłkowej.']);
                }
            } else {
                wp_send_json_error(['message' => 'Błąd podczas tworzenia wpisu aktywności.']);
            }
            return; // Zakończ, bo obsłużyliśmy e-mail
        }

        // *** TWOJA ISTNIEJĄCA LOGIKA DLA INNYCH TYPÓW AKTYWNOŚCI ***
        if (empty($content)) {
            wp_send_json_error(array('message' => 'Brak treści aktywności.'));
            return;
        }

        // Ustalenie tytułu i powiązania
        if ($person_id) {
            $entity_title = get_the_title($person_id);
            $entity_type = 'person';
            $entity_id = $person_id;
        } else {
            $entity_title = get_the_title($company_id);
            $entity_type = 'company';
            $entity_id = $company_id;
        }

        // 3. Tworzenie nowego posta typu 'activity'
        $activity_post = array(
            'post_title'   => 'Aktywność dla ' . $entity_title . ' - ' . $activity_date,
            'post_content' => $content,
            'post_status'  => 'publish',
            'post_author'  => get_current_user_id(),
            'post_type'    => 'activity',
        );

        $activity_id = wp_insert_post($activity_post);

        // 4. Zapisywanie pól ACF i obsługa załączników
        if ($activity_id && !is_wp_error($activity_id)) {
            update_field('activity_type', $activity_type, $activity_id);
            update_field('activity_date', $activity_date, $activity_id);
            
            // NOWA LOGIKA: Zapisujemy wszystkie powiązania w jednym polu
            $related_objects = [];
            if ($person_id) {
                $related_objects[] = $person_id;
            }
            if ($company_id) {
                $related_objects[] = $company_id;
            }
            
            if (!empty($related_objects)) {
                update_field('related_objects', $related_objects, $activity_id);
            }

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

            // Obsługa transkrypcji
            $transcription_ids = isset($_POST['transcription_ids']) && is_array($_POST['transcription_ids']) 
                ? array_map('intval', $_POST['transcription_ids']) 
                : [];

            if (!empty($transcription_ids)) {
                foreach ($transcription_ids as $att_id) {
                    // Sprawdź, czy to na pewno plik audio, dla bezpieczeństwa
                    if (strpos(get_post_mime_type($att_id), 'audio/') === 0) {
                        // Użyj WP Cron do zlecenia zadania w tle
                        // To zapobiega przekroczeniu limitu czasu wykonania skryptu
                        wp_schedule_single_event(time(), 'wpmzf_process_transcription', array($att_id));
                        
                        // Ustaw wstępny status dla załącznika
                        update_post_meta($att_id, '_wpmzf_transcription_status', 'pending');
                    }
                }
            }

            wp_send_json_success(array('message' => 'Aktywność dodana pomyślnie.'));
        } else {
            wp_send_json_error(array('message' => 'Wystąpił błąd podczas dodawania aktywności.'));
        }
    }

    /**
     * Logika pobierania aktywności dla danej osoby lub firmy.
     */
    public function get_activities()
    {
        $timer_id = WPMZF_Performance_Monitor::start_timer('get_activities');
        
        try {
            // Sprawdź najpierw jakie ID jest podane, żeby wybrać odpowiedni nonce
            $person_id = isset($_POST['person_id']) ? intval($_POST['person_id']) : 0;
            $company_id = isset($_POST['company_id']) ? intval($_POST['company_id']) : 0;
            
            // Wybierz odpowiedni nonce w zależności od typu encji
            if ($person_id) {
                check_ajax_referer('wpmzf_person_view_nonce', 'security');
            } elseif ($company_id) {
                check_ajax_referer('wpmzf_company_view_nonce', 'security');
            } else {
                wp_send_json_error(['message' => 'Nieprawidłowe ID osoby lub firmy.']);
                return;
            }

            // Sprawdź uprawnienia
            if (!current_user_can('edit_posts')) {
                WPMZF_Logger::log_security_violation('get_activities - insufficient permissions');
                wp_send_json_error(['message' => 'Brak uprawnień.']);
                return;
            }

            // Debug - sprawdź wszystkie dane POST
            WPMZF_Logger::debug('get_activities called', $_POST);
            
            // Debug logging
            error_log('WPMZF get_activities: person_id=' . $person_id . ', company_id=' . $company_id);

            // Sprawdzenie cache
            $entity_type = $person_id ? 'person' : 'company';
            $entity_id = $person_id ?: $company_id;
            $cache_key = "activities_for_{$entity_type}_{$entity_id}";
            
            $cached_activities = WPMZF_Cache_Manager::get($cache_key, 'activities');
            if ($cached_activities !== false) {
                WPMZF_Performance_Monitor::end_timer($timer_id, ['cache_hit' => true]);
                wp_send_json_success(['activities' => $cached_activities]);
                return;
            }

            // Przygotowanie zapytania w zależności od typu encji
            $meta_query = [];
            if ($person_id) {
                $meta_query[] = [
                    'key' => 'related_objects',
                    'value' => '"' . $person_id . '"', // ACF przechowuje ID w serializowanej tablicy
                    'compare' => 'LIKE'
                ];
            } else {
                $meta_query[] = [
                    'key' => 'related_objects',
                    'value' => '"' . $company_id . '"', // ACF przechowuje ID w serializowanej tablicy
                    'compare' => 'LIKE'
                ];
            }

            $args = [
                'post_type' => 'activity',
                'post_status' => 'publish',
                'posts_per_page' => 50, // Ograniczenie dla wydajności
                'orderby' => 'date',
                'order' => 'DESC',
                'meta_query' => $meta_query
            ];

            $activities_query = new WP_Query($args);
            $activities = [];

            if ($activities_query->have_posts()) {
                while ($activities_query->have_posts()) {
                    $activities_query->the_post();
                    
                    $activity_id = get_the_ID();
                    $attachments = get_field('activity_attachments', $activity_id) ?: [];
                    $author_id = get_the_author_meta('ID');
                    $avatar_url = get_avatar_url($author_id, ['size' => 64]);
                    
                    $activities[] = [
                        'id' => $activity_id,
                        'title' => get_the_title(),
                        'content' => get_the_content(),
                        'date' => get_the_date('Y-m-d H:i:s'),
                        'author' => get_the_author(),
                        'avatar' => $avatar_url,
                        'type' => get_field('activity_type', $activity_id),
                        'attachments' => $this->format_attachments($attachments)
                    ];
                }
                wp_reset_postdata();
            }

            // Zapisz w cache
            WPMZF_Cache_Manager::set($cache_key, $activities, 'activities', 1800); // 30 minut

            WPMZF_Performance_Monitor::end_timer($timer_id, [
                'activities_count' => count($activities),
                'cache_hit' => false
            ]);

            wp_send_json_success(['activities' => $activities]);

        } catch (Exception $e) {
            WPMZF_Logger::error('Failed to get activities', [
                'error' => $e->getMessage(),
                'person_id' => $person_id ?? 0,
                'company_id' => $company_id ?? 0,
                'trace' => $e->getTraceAsString()
            ]);
            
            WPMZF_Performance_Monitor::end_timer($timer_id, ['error' => $e->getMessage()]);
            
            wp_send_json_error(['message' => 'Wystąpił błąd podczas pobierania aktywności: ' . $e->getMessage()]);
        }
    }

    /**
     * Formatuje załączniki dla odpowiedzi JSON
     *
     * @param array $attachments Załączniki z ACF
     * @return array
     */
    private function format_attachments($attachments) {
        if (empty($attachments) || !is_array($attachments)) {
            return [];
        }

        $formatted = [];
        foreach ($attachments as $attachment) {
            if (isset($attachment['attachment_file']) && !empty($attachment['attachment_file'])) {
                $attachment_id = $attachment['attachment_file'];
                
                if (is_array($attachment_id)) {
                    $attachment_id = $attachment_id['ID'] ?? 0;
                }
                
                $attachment_id = intval($attachment_id);
                
                if ($attachment_id > 0) {
                    $attachment_post = get_post($attachment_id);
                    if ($attachment_post) {
                        $file_path = get_attached_file($attachment_id);
                        $mime_type = get_post_mime_type($attachment_id);
                        $thumbnail_url = null;
                        
                        // Generuj thumbnail dla obrazów
                        if (strpos($mime_type, 'image/') === 0) {
                            $thumbnail_url = wp_get_attachment_image_url($attachment_id, 'thumbnail');
                        }
                        
                        $formatted_attachment = [
                            'id' => $attachment_id,
                            'filename' => basename(get_attached_file($attachment_id)),
                            'title' => $attachment_post->post_title,
                            'url' => wp_get_attachment_url($attachment_id),
                            'mime_type' => $mime_type,
                            'thumbnail_url' => $thumbnail_url,
                            'size' => $file_path ? size_format(filesize($file_path)) : 'Nieznany'
                        ];
                        
                        // Dodaj dane transkrypcji jeśli istnieją
                        $transcription_status = get_post_meta($attachment_id, '_wpmzf_transcription_status', true);
                        if ($transcription_status) {
                            $transcription_text = get_post_meta($attachment_id, '_wpmzf_transcription_text', true);
                            $formatted_attachment['transcription'] = [
                                'status' => $transcription_status,
                                'text_preview' => $transcription_text ? mb_substr($transcription_text, 0, 150) . '...' : ''
                            ];
                        }
                        
                        $formatted[] = $formatted_attachment;
                    }
                }
            }
        }

        return $formatted;
    }

    /**
     * Pobiera pojedynczą aktywność
     */
    public function get_single_activity() {
        check_ajax_referer('wpmzf_person_view_nonce', 'security');
        
        $activity_id = intval($_POST['activity_id'] ?? 0);
        
        if (!$activity_id) {
            wp_send_json_error(['message' => 'Nieprawidłowe ID aktywności.']);
            return;
        }
        
        $activity = get_post($activity_id);
        if (!$activity || $activity->post_type !== 'activity') {
            wp_send_json_error(['message' => 'Aktywność nie została znaleziona.']);
            return;
        }
        
        $activity_data = [
            'id' => $activity->ID,
            'title' => $activity->post_title,
            'content' => $activity->post_content,
            'date' => $activity->post_date,
            'author' => get_the_author_meta('display_name', $activity->post_author),
            'type' => get_field('activity_type', $activity->ID),
            'attachments' => $this->format_attachments(get_field('activity_attachments', $activity->ID) ?: [])
        ];
        
        wp_send_json_success(['activity' => $activity_data]);
    }

    /**
     * Obsługuje upload pojedynczego pliku do biblioteki mediów.
     */
    public function upload_attachment()
    {
        try {
            // Rate limiting dla uploadów
            if (WPMZF_Rate_Limiter::is_rate_limited('upload')) {
                wp_send_json_error(['message' => 'Za dużo uploadów. Spróbuj ponownie za chwilę.'], 429);
                return;
            }
            
            check_ajax_referer('wpmzf_person_view_nonce', 'security');

            // Sprawdź uprawnienia
            if (!current_user_can('upload_files')) {
                WPMZF_Logger::log_security_violation('upload_attachment - insufficient permissions');
                wp_send_json_error(['message' => 'Brak uprawnień do przesyłania plików.']);
                return;
            }

            // Increment rate limit counter
            WPMZF_Rate_Limiter::increment_counter('upload');

            if (empty($_FILES['file'])) {
                wp_send_json_error(['message' => 'Brak pliku do przesłania.']);
                return;
            }

            // Walidacja pliku
            $validation_result = WPMZF_File_Validator::validate_file($_FILES['file']);
            if ($validation_result !== true) {
                WPMZF_Logger::warning('File validation failed', [
                    'errors' => $validation_result,
                    'file' => $_FILES['file']['name']
                ]);
                wp_send_json_error(['message' => implode(' ', $validation_result)]);
                return;
            }

            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');

            $attachment_id = media_handle_upload('file', 0);

            if (is_wp_error($attachment_id)) {
                WPMZF_Logger::error('File upload failed', [
                    'error' => $attachment_id->get_error_message(),
                    'file' => $_FILES['file']['name']
                ]);
                wp_send_json_error(['message' => $attachment_id->get_error_message()]);
            } else {
                WPMZF_Logger::info('File uploaded successfully', [
                    'attachment_id' => $attachment_id,
                    'file' => $_FILES['file']['name']
                ]);
                wp_send_json_success(['id' => $attachment_id]);
            }
            
        } catch (Exception $e) {
            WPMZF_Logger::error('Error in upload_attachment', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            wp_send_json_error(['message' => 'Wystąpił błąd podczas przesyłania pliku.']);
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

        // Obsługa pola polecającego
        $referrer_data = isset($_POST['person_referrer']) ? sanitize_text_field(wp_unslash($_POST['person_referrer'])) : null;
        if (!empty($referrer_data) && is_numeric($referrer_data)) {
            $referrer_id = intval($referrer_data);
            // Sprawdź czy to jest prawidłowa osoba lub firma
            $referrer_post = get_post($referrer_id);
            if ($referrer_post && in_array(get_post_type($referrer_id), ['person', 'company'])) {
                update_field('person_referrer', array($referrer_id), $person_id);
            }
        } else {
            // Jeśli polecający został usunięty z pola, czyścimy wartość
            update_field('person_referrer', null, $person_id);
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
        
        // Przygotuj dane zwrotne dla polecającego
        $referrer_html = '';
        $referrer = get_field('person_referrer', $person_id);
        if ($referrer && is_array($referrer) && !empty($referrer)) {
            $referrer_post = get_post($referrer[0]);
            if ($referrer_post) {
                $referrer_type = get_post_type($referrer_post->ID) === 'company' ? '🏢' : '👤';
                $referrer_html = $referrer_type . ' ' . esc_html($referrer_post->post_title);
            }
        }
        
        // Przygotuj odświeżone HTML dla kontaktów
        $contacts_html = [
            'emails' => WPMZF_Contact_Helper::render_emails_display(WPMZF_Contact_Helper::get_person_emails($person_id)),
            'phones' => WPMZF_Contact_Helper::render_phones_display(WPMZF_Contact_Helper::get_person_phones($person_id))
        ];

        wp_send_json_success([
            'message' => 'Dane osoby zaktualizowane.',
            'company_html' => $company_html,
            'referrer_html' => $referrer_html,
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
        $company_id = isset($_POST['company_id']) ? intval($_POST['company_id']) : 0;
        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
        $task_title = isset($_POST['task_title']) ? sanitize_text_field($_POST['task_title']) : '';
        $task_due_date = isset($_POST['task_due_date']) ? sanitize_text_field($_POST['task_due_date']) : '';
        $assigned_user = isset($_POST['assigned_user']) ? intval($_POST['assigned_user']) : 0;

        if ((!$person_id && !$company_id && !$project_id) || empty($task_title)) {
            wp_send_json_error(['message' => 'Brak wymaganych danych (tytuł zadania i przynajmniej jedna relacja).']);
            return;
        }

        // Walidacja typu encji
        if ($person_id && get_post_type($person_id) !== 'person') {
            wp_send_json_error(['message' => 'Nieprawidłowe ID osoby.']);
            return;
        }

        if ($company_id && get_post_type($company_id) !== 'company') {
            wp_send_json_error(['message' => 'Nieprawidłowe ID firmy.']);
            return;
        }

        if ($project_id && get_post_type($project_id) !== 'project') {
            wp_send_json_error(['message' => 'Nieprawidłowe ID projektu.']);
            return;
        }

        // Sprawdź czy przypisany użytkownik istnieje i czy ma odpowiednie uprawnienia
        if ($assigned_user > 0) {
            $user = get_user_by('ID', $assigned_user);
            if (!$user) {
                wp_send_json_error(['message' => 'Wybrany użytkownik nie istnieje.']);
                return;
            }
            
            // Opcjonalnie: sprawdź czy użytkownik ma powiązanego pracownika
            $employee_query = new WP_Query([
                'post_type' => 'employee',
                'meta_query' => [
                    [
                        'key' => 'employee_user',
                        'value' => $assigned_user,
                        'compare' => '='
                    ]
                ],
                'posts_per_page' => 1
            ]);
            
            if (!$employee_query->have_posts()) {
                wp_send_json_error(['message' => 'Wybrany użytkownik nie ma powiązanego profilu pracownika.']);
                return;
            }
            wp_reset_postdata();
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
            
            // Przypisanie do odpowiedniej encji
            if ($person_id) {
                update_field('task_assigned_person', $person_id, $task_id);
            }
            if ($company_id) {
                update_field('task_assigned_company', $company_id, $task_id);
            }
            if ($project_id) {
                update_field('task_assigned_project', $project_id, $task_id);
            }
            
            // Przypisanie do użytkownika
            if ($assigned_user > 0) {
                update_field('task_assigned_user', $assigned_user, $task_id);
            }
            
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
     * Pobiera zadania dla danej osoby lub firmy
     */
    public function get_tasks()
    {
        try {
            check_ajax_referer('wpmzf_task_nonce', 'wpmzf_task_security');

            $person_id = isset($_POST['person_id']) ? intval($_POST['person_id']) : 0;
            $company_id = isset($_POST['company_id']) ? intval($_POST['company_id']) : 0;
            
            if (!$person_id && !$company_id) {
                wp_send_json_error(['message' => 'Nieprawidłowe ID osoby lub firmy.']);
                return;
            }

        // Przygotowanie zapytania w zależności od typu encji
        $meta_query = [];
        if ($person_id) {
            $meta_query[] = [
                'key' => 'task_assigned_person',
                'value' => $person_id,
                'compare' => '='
            ];
        } else {
            $meta_query[] = [
                'key' => 'task_assigned_company',
                'value' => $company_id,
                'compare' => '='
            ];
        }

        $args = [
            'post_type' => 'task',
            'posts_per_page' => -1,
            'meta_query' => $meta_query,
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
                $assigned_user_id = get_field('task_assigned_user', $task_id);
                
                // Pobierz informacje o przypisanym użytkowniku
                $assigned_user_name = '';
                if ($assigned_user_id) {
                    $user = get_user_by('ID', $assigned_user_id);
                    if ($user) {
                        $assigned_user_name = $user->display_name;
                    }
                }

                $task_data = [
                    'id' => $task_id,
                    'title' => get_the_title(),
                    'status' => $task_status,
                    'description' => $description,
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                    'due_date' => $end_date, // Alias for JavaScript compatibility
                    'assigned_user_id' => $assigned_user_id,
                    'assigned_user_name' => $assigned_user_name,
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
        
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Błąd serwera podczas ładowania zadań.']);
        }
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
     * Aktualizuje osobę odpowiedzialną za zadanie
     */
    public function update_task_assignee()
    {
        check_ajax_referer('wpmzf_task_nonce', 'wpmzf_task_security');

        $task_id = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;
        $assigned_user_id = isset($_POST['assigned_user_id']) ? intval($_POST['assigned_user_id']) : null;

        if (!$task_id) {
            wp_send_json_error(['message' => 'Brak ID zadania.']);
            return;
        }

        if (get_post_type($task_id) !== 'task') {
            wp_send_json_error(['message' => 'Nieprawidłowe ID zadania.']);
            return;
        }

        // Sprawdź czy użytkownik istnieje (jeśli ID zostało podane)
        if ($assigned_user_id && !get_user_by('ID', $assigned_user_id)) {
            wp_send_json_error(['message' => 'Nieprawidłowy użytkownik.']);
            return;
        }

        // Aktualizuj pole assigned_user - jeśli ID jest puste, ustaw null
        $field_value = $assigned_user_id ? $assigned_user_id : '';
        update_field('task_assigned_user', $field_value, $task_id);

        $message = $assigned_user_id ? 
            'Osoba odpowiedzialna została zaktualizowana.' : 
            'Usunięto przypisanie osoby odpowiedzialnej.';

        wp_send_json_success(['message' => $message]);
    }

    /**
     * Pobiera listę użytkowników do przypisania zadań
     */
    public function get_users_for_task()
    {
        check_ajax_referer('wpmzf_task_nonce', 'wpmzf_task_security');

        // Pobierz wszystkich użytkowników którzy mogą edytować posty
        $users = get_users([
            'capability' => 'edit_posts',
            'orderby' => 'display_name',
            'order' => 'ASC'
        ]);

        $user_data = [];
        foreach ($users as $user) {
            $user_data[] = [
                'ID' => $user->ID,
                'display_name' => $user->display_name,
                'user_login' => $user->user_login
            ];
        }

        wp_send_json_success($user_data);
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

    /**
     * Przełącza status archiwizacji firmy
     */
    public function toggle_company_archive()
    {
        // Sprawdzenie nonce dla bezpieczeństwa
        if (!wp_verify_nonce($_POST['security'] ?? '', 'wpmzf_company_view_nonce')) {
            wp_send_json_error(['message' => 'Błąd bezpieczeństwa.']);
            return;
        }

        $company_id = intval($_POST['company_id'] ?? 0);
        
        if (!$company_id) {
            wp_send_json_error(['message' => 'Nieprawidłowe ID firmy.']);
            return;
        }

        // Sprawdzenie czy wpis istnieje i jest typu 'company'
        if (get_post_type($company_id) !== 'company') {
            wp_send_json_error(['message' => 'Nieprawidłowe ID firmy.']);
            return;
        }

        // Sprawdzenie uprawnień
        if (!current_user_can('edit_post', $company_id)) {
            wp_send_json_error(['message' => 'Brak uprawnień do edycji tej firmy.']);
            return;
        }

        // Pobranie obecnego statusu
        $current_status = get_field('company_status', $company_id) ?: 'Aktywny';
        
        // Przełączenie statusu
        $new_status = ($current_status === 'Zarchiwizowany') ? 'Aktywny' : 'Zarchiwizowany';
        
        // Aktualizacja statusu
        $updated = update_field('company_status', $new_status, $company_id);
        
        if ($updated !== false) {
            wp_send_json_success([
                'message' => $new_status === 'Zarchiwizowany' ? 'Firma została zarchiwizowana.' : 'Firma została przywrócona z archiwum.',
                'new_status' => $new_status,
                'status_label' => $new_status
            ]);
        } else {
            wp_send_json_error(['message' => 'Nie udało się zaktualizować statusu firmy.']);
        }
    }

    /**
     * Aktualizuje dane projektu
     */
    public function update_project()
    {
        check_ajax_referer('wpmzf_project_view_nonce', 'security');

        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
        
        if (!$project_id || get_post_type($project_id) !== 'project') {
            wp_send_json_error(['message' => 'Nieprawidłowe ID projektu.']);
            return;
        }

        // Pobierz dane z formularza
        $project_title = isset($_POST['project_title']) ? sanitize_text_field($_POST['project_title']) : '';
        $project_description = isset($_POST['project_description']) ? wp_kses_post($_POST['project_description']) : '';
        $project_status = isset($_POST['project_status']) ? sanitize_text_field($_POST['project_status']) : '';
        $project_budget = isset($_POST['project_budget']) ? floatval($_POST['project_budget']) : 0;
        $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
        $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';

        if (empty($project_title)) {
            wp_send_json_error(['message' => 'Nazwa projektu jest wymagana.']);
            return;
        }

        // Aktualizuj post
        $post_data = [
            'ID' => $project_id,
            'post_title' => $project_title,
            'post_content' => $project_description
        ];

        $updated = wp_update_post($post_data);

        if (is_wp_error($updated)) {
            wp_send_json_error(['message' => 'Błąd podczas aktualizacji projektu.']);
            return;
        }

        // Aktualizuj pola ACF
        if ($project_status) {
            update_field('project_status', $project_status, $project_id);
        }
        
        if ($project_budget > 0) {
            update_field('project_budget', $project_budget, $project_id);
        }
        
        if ($start_date) {
            update_field('start_date', $start_date, $project_id);
        }
        
        if ($end_date) {
            update_field('end_date', $end_date, $project_id);
        }

        wp_send_json_success([
            'message' => 'Projekt został zaktualizowany.',
            'project_id' => $project_id
        ]);
    }

    /**
     * Pobiera zadania dla projektu
     */
    public function get_project_tasks()
    {
        check_ajax_referer('wpmzf_task_nonce', 'security');

        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
        
        if (!$project_id || get_post_type($project_id) !== 'project') {
            wp_send_json_error(['message' => 'Nieprawidłowe ID projektu.']);
            return;
        }

        // Pobierz zadania przypisane do projektu
        $tasks_args = [
            'post_type' => 'task',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => 'task_project',
                    'value' => '"' . $project_id . '"',
                    'compare' => 'LIKE'
                ]
            ],
            'orderby' => 'date',
            'order' => 'DESC'
        ];

        $tasks_query = new WP_Query($tasks_args);
        $open_tasks = [];
        $closed_tasks = [];

        if ($tasks_query->have_posts()) {
            while ($tasks_query->have_posts()) {
                $tasks_query->the_post();
                $task_id = get_the_ID();
                $task_status = get_field('task_status', $task_id) ?: 'Do zrobienia';
                
                $task_data = [
                    'id' => $task_id,
                    'title' => get_the_title(),
                    'content' => get_the_content(),
                    'status' => $task_status,
                    'start_date' => get_field('task_start_date', $task_id),
                    'end_date' => get_field('task_end_date', $task_id),
                    'date_created' => get_the_date('Y-m-d H:i:s')
                ];
                
                if ($task_status === 'Zrobione') {
                    $closed_tasks[] = $task_data;
                } else {
                    $open_tasks[] = $task_data;
                }
            }
            wp_reset_postdata();
        }

        wp_send_json_success([
            'open_tasks' => $open_tasks,
            'closed_tasks' => $closed_tasks
        ]);
    }

    /**
     * Dodaje nowe zadanie do projektu
     */
    public function add_project_task()
    {
        check_ajax_referer('wpmzf_task_nonce', 'security');

        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
        $task_title = isset($_POST['task_title']) ? sanitize_text_field($_POST['task_title']) : '';
        $assigned_user = isset($_POST['assigned_user']) ? intval($_POST['assigned_user']) : 0;
        
        if (!$project_id || get_post_type($project_id) !== 'project') {
            wp_send_json_error(['message' => 'Nieprawidłowe ID projektu.']);
            return;
        }

        if (empty($task_title)) {
            wp_send_json_error(['message' => 'Tytuł zadania jest wymagany.']);
            return;
        }

        // Walidacja przypisanego użytkownika
        if ($assigned_user > 0) {
            $user = get_user_by('id', $assigned_user);
            if (!$user) {
                wp_send_json_error(['message' => 'Nieprawidłowy użytkownik.']);
                return;
            }
            
            // Sprawdź czy użytkownik jest powiązany z pracownikiem
            $employee_helper = new WPMZF_Employee_Helper();
            $employee = $employee_helper->get_employee_by_user_id($assigned_user);
            if (!$employee) {
                wp_send_json_error(['message' => 'Wybrany użytkownik nie jest pracownikiem.']);
                return;
            }
        }

        // Utwórz zadanie
        $task_data = [
            'post_title' => $task_title,
            'post_content' => '',
            'post_status' => 'publish',
            'post_type' => 'task',
            'post_author' => get_current_user_id(),
        ];

        $task_id = wp_insert_post($task_data);

        if ($task_id && !is_wp_error($task_id)) {
            // Przypisz zadanie do projektu
            update_field('task_project', [$project_id], $task_id);
            
            // Ustaw domyślny status
            update_field('task_status', 'Do zrobienia', $task_id);
            
            // Przypisz użytkownika jeśli podano
            if ($assigned_user > 0) {
                update_field('task_assigned_user', $assigned_user, $task_id);
            }

            wp_send_json_success([
                'message' => 'Zadanie zostało dodane.',
                'task_id' => $task_id
            ]);
        } else {
            wp_send_json_error(['message' => 'Błąd podczas tworzenia zadania.']);
        }
    }

    /**
     * Dodaje nową aktywność do projektu
     */
    public function add_project_activity()
    {
        check_ajax_referer('wpmzf_project_view_nonce', 'security');

        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
        $activity_content = isset($_POST['activity_content']) ? wp_kses_post($_POST['activity_content']) : '';
        $activity_type = isset($_POST['activity_type']) ? sanitize_text_field($_POST['activity_type']) : 'note';
        
        if (!$project_id || get_post_type($project_id) !== 'project') {
            wp_send_json_error(['message' => 'Nieprawidłowe ID projektu.']);
            return;
        }

        if (empty($activity_content)) {
            wp_send_json_error(['message' => 'Treść aktywności jest wymagana.']);
            return;
        }

        // Utwórz aktywność
        $activity_data = [
            'post_title' => 'Aktywność projektu: ' . get_the_title($project_id),
            'post_content' => $activity_content,
            'post_status' => 'publish',
            'post_type' => 'activity',
            'post_author' => get_current_user_id(),
        ];

        $activity_id = wp_insert_post($activity_data);

        if ($activity_id && !is_wp_error($activity_id)) {
            // NOWA LOGIKA: Przypisz aktywność do projektu
            update_field('related_objects', [$project_id], $activity_id);
            
            // Ustaw typ aktywności
            update_field('activity_type', $activity_type, $activity_id);
            
            // Ustaw datę aktywności na teraz
            update_field('activity_date', current_time('Y-m-d H:i:s'), $activity_id);

            wp_send_json_success([
                'message' => 'Aktywność została dodana.',
                'activity_id' => $activity_id
            ]);
        } else {
            wp_send_json_error(['message' => 'Błąd podczas tworzenia aktywności.']);
        }
    }

    /**
     * Zapisz firmę (dodaj nową lub edytuj istniejącą)
     */
    public function save_company()
    {
        if (!wp_verify_nonce($_POST['nonce'], 'wpmzf_nonce')) {
            wp_send_json_error(['message' => 'Nieprawidłowy token bezpieczeństwa.']);
            return;
        }

        $company_id = intval($_POST['id'] ?? 0);
        $name = sanitize_text_field($_POST['name'] ?? '');
        $nip = sanitize_text_field($_POST['nip'] ?? '');
        $address = sanitize_textarea_field($_POST['address'] ?? '');
        $phone = sanitize_text_field($_POST['phone'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        $website = esc_url_raw($_POST['website'] ?? '');
        $referrer_data = isset($_POST['company_referrer']) ? sanitize_text_field(wp_unslash($_POST['company_referrer'])) : null;

        if (empty($name)) {
            wp_send_json_error(['message' => 'Nazwa firmy jest wymagana.']);
            return;
        }

        $post_data = [
            'post_title' => $name,
            'post_type' => 'company',
            'post_status' => 'publish',
        ];

        if ($company_id > 0) {
            // Edycja istniejącej firmy
            $post_data['ID'] = $company_id;
            $result = wp_update_post($post_data);
        } else {
            // Dodanie nowej firmy
            $result = wp_insert_post($post_data);
        }

        if (!is_wp_error($result) && $result) {
            $company_id = ($company_id > 0) ? $company_id : $result;
            
            // Aktualizuj pola customowe
            update_field('company_nip', $nip, $company_id);
            update_field('company_address', $address, $company_id);
            update_field('company_phone', $phone, $company_id);
            update_field('company_email', $email, $company_id);
            update_field('company_website', $website, $company_id);
            
            // Obsługa polecającego
            if (!empty($referrer_data)) {
                $referrer_id = intval($referrer_data);
                update_field('company_referrer', array($referrer_id), $company_id);
            } else {
                update_field('company_referrer', null, $company_id);
            }

            wp_send_json_success([
                'message' => 'Firma została zapisana.',
                'company_id' => $company_id
            ]);
        } else {
            wp_send_json_error(['message' => 'Błąd podczas zapisywania firmy.']);
        }
    }

    /**
     * Pobierz dane firmy
     */
    public function get_company()
    {
        if (!wp_verify_nonce($_POST['nonce'], 'wpmzf_nonce')) {
            wp_send_json_error(['message' => 'Nieprawidłowy token bezpieczeństwa.']);
            return;
        }

        $company_id = intval($_POST['company_id'] ?? 0);
        
        if (!$company_id || get_post_type($company_id) !== 'company') {
            wp_send_json_error(['message' => 'Nieprawidłowe ID firmy.']);
            return;
        }

        $company = get_post($company_id);
        $fields = get_fields($company_id);
        
        $referrer = get_field('company_referrer', $company_id);
        $referrer_id = '';
        if ($referrer && is_array($referrer) && !empty($referrer)) {
            $referrer_id = $referrer[0];
        }

        wp_send_json_success([
            'name' => $company->post_title,
            'nip' => $fields['company_nip'] ?? '',
            'address' => $fields['company_address'] ?? '',
            'phone' => $fields['company_phone'] ?? '',
            'email' => $fields['company_email'] ?? '',
            'website' => $fields['company_website'] ?? '',
            'company_referrer' => $referrer_id,
        ]);
    }

    /**
     * Usuń firmę
     */
    public function delete_company()
    {
        if (!wp_verify_nonce($_POST['nonce'], 'wpmzf_nonce')) {
            wp_send_json_error(['message' => 'Nieprawidłowy token bezpieczeństwa.']);
            return;
        }

        $company_id = intval($_POST['company_id'] ?? 0);
        
        if (!$company_id || get_post_type($company_id) !== 'company') {
            wp_send_json_error(['message' => 'Nieprawidłowe ID firmy.']);
            return;
        }

        $result = wp_delete_post($company_id, true);

        if ($result) {
            wp_send_json_success(['message' => 'Firma została usunięta.']);
        } else {
            wp_send_json_error(['message' => 'Błąd podczas usuwania firmy.']);
        }
    }

    /**
     * Zapisz osobę (dodaj nową lub edytuj istniejącą)
     */
    public function save_person()
    {
        if (!wp_verify_nonce($_POST['nonce'], 'wpmzf_nonce')) {
            wp_send_json_error(['message' => 'Nieprawidłowy token bezpieczeństwa.']);
            return;
        }

        $person_id = intval($_POST['id'] ?? 0);
        $first_name = sanitize_text_field($_POST['first_name'] ?? '');
        $last_name = sanitize_text_field($_POST['last_name'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        $phone = sanitize_text_field($_POST['phone'] ?? '');
        $position = sanitize_text_field($_POST['position'] ?? '');
        $company_id = intval($_POST['company_id'] ?? 0);
        $referrer_data = isset($_POST['person_referrer']) ? sanitize_text_field(wp_unslash($_POST['person_referrer'])) : null;

        if (empty($first_name) || empty($last_name)) {
            wp_send_json_error(['message' => 'Imię i nazwisko są wymagane.']);
            return;
        }

        $full_name = trim($first_name . ' ' . $last_name);

        $post_data = [
            'post_title' => $full_name,
            'post_type' => 'person',
            'post_status' => 'publish',
        ];

        if ($person_id > 0) {
            // Edycja istniejącej osoby
            $post_data['ID'] = $person_id;
            $result = wp_update_post($post_data);
        } else {
            // Dodanie nowej osoby
            $result = wp_insert_post($post_data);
        }

        if (!is_wp_error($result) && $result) {
            $person_id = ($person_id > 0) ? $person_id : $result;
            
            // Aktualizuj pola customowe
            update_field('person_first_name', $first_name, $person_id);
            update_field('person_last_name', $last_name, $person_id);
            update_field('person_email', $email, $person_id);
            update_field('person_phone', $phone, $person_id);
            update_field('person_position', $position, $person_id);
            
            if ($company_id > 0) {
                update_field('person_company', array($company_id), $person_id);
            } else {
                update_field('person_company', null, $person_id);
            }
            
            // Obsługa polecającego
            if (!empty($referrer_data)) {
                $referrer_id = intval($referrer_data);
                update_field('person_referrer', array($referrer_id), $person_id);
            } else {
                update_field('person_referrer', null, $person_id);
            }

            wp_send_json_success([
                'message' => 'Osoba została zapisana.',
                'person_id' => $person_id
            ]);
        } else {
            wp_send_json_error(['message' => 'Błąd podczas zapisywania osoby.']);
        }
    }

    /**
     * Pobierz dane osoby
     */
    public function get_person()
    {
        if (!wp_verify_nonce($_POST['nonce'], 'wpmzf_nonce')) {
            wp_send_json_error(['message' => 'Nieprawidłowy token bezpieczeństwa.']);
            return;
        }

        $person_id = intval($_POST['person_id'] ?? 0);
        
        if (!$person_id || get_post_type($person_id) !== 'person') {
            wp_send_json_error(['message' => 'Nieprawidłowe ID osoby.']);
            return;
        }

        $person = get_post($person_id);
        $fields = get_fields($person_id);
        
        $company = get_field('person_company', $person_id);
        $company_id = '';
        if ($company && is_array($company) && !empty($company)) {
            $company_id = $company[0];
        }
        
        $referrer = get_field('person_referrer', $person_id);
        $referrer_id = '';
        if ($referrer && is_array($referrer) && !empty($referrer)) {
            $referrer_id = $referrer[0];
        }

        wp_send_json_success([
            'first_name' => $fields['person_first_name'] ?? '',
            'last_name' => $fields['person_last_name'] ?? '',
            'email' => $fields['person_email'] ?? '',
            'phone' => $fields['person_phone'] ?? '',
            'position' => $fields['person_position'] ?? '',
            'company_id' => $company_id,
            'person_referrer' => $referrer_id,
        ]);
    }

    /**
     * Usuń osobę
     */
    public function delete_person()
    {
        if (!wp_verify_nonce($_POST['nonce'], 'wpmzf_nonce')) {
            wp_send_json_error(['message' => 'Nieprawidłowy token bezpieczeństwa.']);
            return;
        }

        $person_id = intval($_POST['person_id'] ?? 0);
        
        if (!$person_id || get_post_type($person_id) !== 'person') {
            wp_send_json_error(['message' => 'Nieprawidłowe ID osoby.']);
            return;
        }

        $result = wp_delete_post($person_id, true);

        if ($result) {
            wp_send_json_success(['message' => 'Osoba została usunięta.']);
        } else {
            wp_send_json_error(['message' => 'Błąd podczas usuwania osoby.']);
        }
    }

    /**
     * Dodaje nowy ważny link
     */
    public function add_important_link() {
        // Sprawdź parametry przed walidacją nonce
        $object_type = sanitize_text_field($_POST['object_type'] ?? '');
        
        // Wybierz właściwy nonce w zależności od typu obiektu
        $nonce_action = ($object_type === 'company') ? 'wpmzf_company_view_nonce' : 'wpmzf_person_view_nonce';
        check_ajax_referer($nonce_action, 'security');

        $url = sanitize_url($_POST['url'] ?? '');
        $custom_title = sanitize_text_field($_POST['custom_title'] ?? '');
        $object_id = intval($_POST['object_id'] ?? 0);

        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            wp_send_json_error(['message' => 'Nieprawidłowy URL']);
            return;
        }

        if (empty($object_id) || empty($object_type)) {
            wp_send_json_error(['message' => 'Nieprawidłowe ID obiektu lub typ']);
            return;
        }

        $link_data = array(
            'url' => $url,
            'custom_title' => $custom_title,
            'object_id' => $object_id,
            'object_type' => $object_type
        );

        $link = WPMZF_Important_Link::create($link_data);

        if (is_wp_error($link)) {
            wp_send_json_error(['message' => $link->get_error_message()]);
            return;
        }

        wp_send_json_success([
            'message' => 'Link został dodany',
            'link' => array(
                'id' => $link->id,
                'url' => $link->url,
                'title' => $link->get_display_title(),
                'favicon' => $link->get_favicon_url(),
                'custom_title' => $link->custom_title,
                'fetched_title' => $link->fetched_title
            )
        ]);
    }

    /**
     * Pobiera ważne linki dla obiektu
     */
    public function get_important_links() {
        // Sprawdź parametry przed walidacją nonce  
        $object_type = sanitize_text_field($_POST['object_type'] ?? '');
        
        // Wybierz właściwy nonce w zależności od typu obiektu
        $nonce_action = ($object_type === 'company') ? 'wpmzf_company_view_nonce' : 'wpmzf_person_view_nonce';
        check_ajax_referer($nonce_action, 'security');

        $object_id = intval($_POST['object_id'] ?? 0);

        if (empty($object_id) || empty($object_type)) {
            wp_send_json_error(['message' => 'Nieprawidłowe ID obiektu lub typ']);
            return;
        }

        $links = WPMZF_Important_Link::get_links_for_object($object_id, $object_type);
        $links_data = array();

        foreach ($links as $link) {
            $links_data[] = array(
                'id' => $link->id,
                'url' => $link->url,
                'title' => $link->get_display_title(),
                'favicon' => $link->get_favicon_url(),
                'custom_title' => $link->custom_title,
                'fetched_title' => $link->fetched_title,
                'created_at' => $link->created_at
            );
        }

        wp_send_json_success(['links' => $links_data]);
    }

    /**
     * Aktualizuje ważny link
     */
    public function update_important_link() {
        // Sprawdź parametry przed walidacją nonce
        $object_type = sanitize_text_field($_POST['object_type'] ?? '');
        
        // Wybierz właściwy nonce w zależności od typu obiektu
        $nonce_action = ($object_type === 'company') ? 'wpmzf_company_view_nonce' : 'wpmzf_person_view_nonce';
        check_ajax_referer($nonce_action, 'security');

        $link_id = intval($_POST['link_id'] ?? 0);
        $url = sanitize_url($_POST['url'] ?? '');
        $custom_title = sanitize_text_field($_POST['custom_title'] ?? '');

        if (empty($link_id)) {
            wp_send_json_error(['message' => 'Nieprawidłowe ID linku']);
            return;
        }

        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            wp_send_json_error(['message' => 'Nieprawidłowy URL']);
            return;
        }

        $link = new WPMZF_Important_Link($link_id);
        if (empty($link->id)) {
            wp_send_json_error(['message' => 'Link nie został znaleziony']);
            return;
        }

        $link->url = $url;
        $link->custom_title = $custom_title;
        
        // Jeśli URL się zmienił, wyczyść pobrany tytuł żeby zostać pobrany ponownie
        $old_url = get_post_meta($link_id, 'url', true);
        if ($old_url !== $url) {
            $link->fetched_title = '';
            $link->favicon_url = '';
        }

        $result = $link->save();

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
            return;
        }

        wp_send_json_success([
            'message' => 'Link został zaktualizowany',
            'link' => array(
                'id' => $link->id,
                'url' => $link->url,
                'title' => $link->get_display_title(),
                'favicon' => $link->get_favicon_url(),
                'custom_title' => $link->custom_title,
                'fetched_title' => $link->fetched_title
            )
        ]);
    }

    /**
     * Usuwa ważny link
     */
    public function delete_important_link() {
        // Sprawdź parametry przed walidacją nonce
        $object_type = sanitize_text_field($_POST['object_type'] ?? '');
        
        // Wybierz właściwy nonce w zależności od typu obiektu
        $nonce_action = ($object_type === 'company') ? 'wpmzf_company_view_nonce' : 'wpmzf_person_view_nonce';
        check_ajax_referer($nonce_action, 'security');

        $link_id = intval($_POST['link_id'] ?? 0);

        if (empty($link_id)) {
            wp_send_json_error(['message' => 'Nieprawidłowe ID linku']);
            return;
        }

        $link = new WPMZF_Important_Link($link_id);
        if (empty($link->id)) {
            wp_send_json_error(['message' => 'Link nie został znaleziony']);
            return;
        }

        $result = $link->delete();

        if ($result) {
            wp_send_json_success(['message' => 'Link został usunięty']);
        } else {
            wp_send_json_error(['message' => 'Błąd podczas usuwania linku']);
        }
    }

    /**
     * Pobiera pełną transkrypcję dla załącznika
     */
    public function get_full_transcription() {
        check_ajax_referer('wpmzf_person_view_nonce', 'security');

        $attachment_id = intval($_POST['attachment_id'] ?? 0);

        if (empty($attachment_id)) {
            wp_send_json_error(['message' => 'Nieprawidłowe ID załącznika']);
            return;
        }

        // Sprawdź czy załącznik istnieje
        $attachment = get_post($attachment_id);
        if (!$attachment || $attachment->post_type !== 'attachment') {
            wp_send_json_error(['message' => 'Załącznik nie został znaleziony']);
            return;
        }

        // Pobierz pełną transkrypcję
        $transcription_text = get_post_meta($attachment_id, '_wpmzf_transcription_text', true);
        $transcription_status = get_post_meta($attachment_id, '_wpmzf_transcription_status', true);

        if (empty($transcription_text) || $transcription_status !== 'completed') {
            wp_send_json_error(['message' => 'Transkrypcja nie jest dostępna lub nie została ukończona']);
            return;
        }

        wp_send_json_success([
            'transcription_text' => $transcription_text,
            'status' => $transcription_status
        ]);
    }
}
