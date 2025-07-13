<?php
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class WPMZF_persons_List_Table extends WP_List_Table {

    /**
     * Cache manager
     */
    private $cache_manager;

    /**
     * Rate limiter
     */
    private $rate_limiter;

    /**
     * Performance monitor
     */
    private $performance_monitor;

    public function __construct() {
        parent::__construct([
            'singular' => 'Osoba',
            'plural'   => 'Osoby',
            'ajax'     => false
        ]);
        
        $this->cache_manager = new WPMZF_Cache_Manager();
        $this->rate_limiter = new WPMZF_Rate_Limiter();
        $this->performance_monitor = new WPMZF_Performance_Monitor();
    }

    // Definicja kolumn
    public function get_columns() {
        return [
            'cb'       => '<input type="checkbox" />',
            'full_name'=> 'Imię i Nazwisko (Firma)',
            'email'    => 'E-mail',
            'phone'    => 'Telefon',
            'date'     => 'Data dodania'
        ];
    }
    
    // Definicja kolumn sortowalnych
    protected function get_sortable_columns() {
        return [
            'full_name' => ['title', false],
            'date'      => ['date', true]
        ];
    }
    
    // Definicja akcji masowych (bulk actions)
    protected function get_bulk_actions() {
        return [
            'archive' => 'Archiwizuj',
            'delete'  => 'Usuń'
        ];
    }

    // Renderowanie zawartości komórek
    function column_default($item, $column_name) {
        switch ($column_name) {
            case 'email':
                return WPMZF_Contact_Helper::get_primary_person_email($item->ID) ?? 'Brak';
            case 'phone':
                return WPMZF_Contact_Helper::get_primary_person_phone($item->ID) ?? 'Brak';
            case 'date':
                return get_the_date('Y-m-d H:i', $item->ID);
            default:
                return print_r($item, true);
        }
    }
    
    // Specjalna metoda dla kolumny z imieniem i nazwiskiem
    function column_full_name($item) {
        $company_id = get_field('person_company', $item->ID);
        $company_name = $company_id ? get_the_title($company_id[0]) : '';
        
        $full_name = '<strong>' . $item->post_title . '</strong>';
        if ($company_name) {
            $full_name .= ' <span style="color:#777;">(' . esc_html($company_name) . ')</span>';
        }

        // Linki akcji (View, Edit, Archive...)
        $view_link = sprintf('?page=luna-crm-person-view&person_id=%s', $item->ID);
        $actions = [
            'view'    => '<a href="' . $view_link . '">Zobacz teczkę</a>',
            'archive' => '<a href="?page=' . $_REQUEST['page'] . '&action=archive&person=' . $item->ID . '">Archiwizuj</a>',
            'delete'  => '<a href="?page=' . $_REQUEST['page'] . '&action=delete&person=' . $item->ID . '" class="submitdelete">Usuń</a>'
        ];

        return $full_name . $this->row_actions($actions);
    }
    
    function column_cb($item) {
        return sprintf('<input type="checkbox" name="bulk-action[]" value="%s" />', $item->ID);
    }

    /**
     * Dodaje dodatkowe kontrolki nawigacji (np. filtry).
     */
    protected function extra_tablenav($which) {
        if ($which == "top") {
            // Pobieramy wszystkie firmy, aby stworzyć dropdown
            $companies = get_posts(['post_type' => 'company', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC']);
            if ($companies) {
                echo '<div class="alignleft actions">';
                $current_company = isset($_GET['company_filter']) ? $_GET['company_filter'] : '';
                echo '<select name="company_filter">';
                echo '<option value="">Wszystkie firmy</option>';
                foreach ($companies as $company) {
                    printf(
                        '<option value="%d" %s>%s</option>',
                        $company->ID,
                        selected($current_company, $company->ID, false),
                        esc_html($company->post_title)
                    );
                }
                echo '</select>';
                submit_button('Filtruj', 'button', 'filter_action', false);
                echo '</div>';
            }
        }
    }

    /**
     * Przetwarza masowe akcje.
     */
    public function process_bulk_action() {
        $timer_id = $this->performance_monitor->start_timer('persons_table_bulk_action');
        
        try {
            // Rate limiting dla masowych akcji
            if (!$this->rate_limiter->check_rate_limit('bulk_action_persons', 5, 60)) {
                WPMZF_Logger::log_security_violation('Bulk action rate limit exceeded', get_current_user_id());
                wp_die(__('Too many bulk actions. Please wait a moment.', 'wpmzf'));
            }

            // Sprawdzenie nonce dla bezpieczeństwa
            if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'bulk-' . $this->_args['plural'])) {
                WPMZF_Logger::log_security_violation('Invalid nonce for bulk action', get_current_user_id());
                $this->performance_monitor->end_timer($timer_id);
                return;
            }
            
            $action = $this->current_action();
            if (!$action) {
                $this->performance_monitor->end_timer($timer_id);
                return;
            }

            $post_ids = isset($_POST['bulk-action']) ? array_map('intval', $_POST['bulk-action']) : [];
            
            if (empty($post_ids)) {
                $this->performance_monitor->end_timer($timer_id);
                return;
            }

            // Walidacja liczby elementów - maksymalnie 100 na raz
            if (count($post_ids) > 100) {
                WPMZF_Logger::log_security_violation('Too many items in bulk action', get_current_user_id(), ['count' => count($post_ids)]);
                wp_die(__('Too many items selected. Maximum 100 items allowed.', 'wpmzf'));
            }
            
            // Sprawdzamy, czy akcja została wykonana
            if ('delete' === $action) {
                $deleted_count = 0;
                foreach ($post_ids as $post_id) {
                    // Sprawdź uprawnienia
                    if (current_user_can('delete_post', $post_id)) {
                        if (wp_delete_post($post_id, true)) {
                            $deleted_count++;
                            WPMZF_Logger::info('Person deleted via bulk action', ['person_id' => $post_id, 'user_id' => get_current_user_id()]);
                        }
                    } else {
                        WPMZF_Logger::log_security_violation('Attempt to delete person without permissions', get_current_user_id(), ['person_id' => $post_id]);
                    }
                }
                
                // Wyczyść cache po masowych operacjach
                $this->cache_manager->delete_pattern('persons_*');
                
                add_settings_error('wpmzf_bulk_action', 'deleted', sprintf(__('%d persons deleted successfully.', 'wpmzf'), $deleted_count), 'success');
            }

            if ('archive' === $action) {
                $archived_count = 0;
                foreach ($post_ids as $post_id) {
                    // Sprawdź uprawnienia
                    if (current_user_can('edit_post', $post_id)) {
                        update_field('person_status', 'Zarchiwizowany', $post_id);
                        $archived_count++;
                        WPMZF_Logger::info('Person archived via bulk action', ['person_id' => $post_id, 'user_id' => get_current_user_id()]);
                    } else {
                        WPMZF_Logger::log_security_violation('Attempt to archive person without permissions', get_current_user_id(), ['person_id' => $post_id]);
                    }
                }
                
                // Wyczyść cache po masowych operacjach
                $this->cache_manager->delete_pattern('persons_*');
                
                add_settings_error('wpmzf_bulk_action', 'archived', sprintf(__('%d persons archived successfully.', 'wpmzf'), $archived_count), 'success');
            }
            
            $this->performance_monitor->end_timer($timer_id);
            
        } catch (Exception $e) {
            $this->performance_monitor->end_timer($timer_id);
            WPMZF_Logger::error('Error in bulk action', ['error' => $e->getMessage(), 'action' => $action ?? 'unknown']);
            add_settings_error('wpmzf_bulk_action', 'error', __('An error occurred during bulk action.', 'wpmzf'), 'error');
        }
    }

    // Główna metoda pobierająca dane
    public function prepare_items() {
        $timer_id = $this->performance_monitor->start_timer('persons_table_prepare_items');
        
        try {
            $this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns()];
            
            $this->process_bulk_action();

            $per_page = 20;
            $current_page = $this->get_pagenum();
            
            // Parametry dla cache key
            $orderby = isset($_GET['orderby']) ? sanitize_key($_GET['orderby']) : 'date';
            $order = isset($_GET['order']) ? sanitize_key($_GET['order']) : 'DESC';
            $company_filter = isset($_GET['company_filter']) ? intval($_GET['company_filter']) : 0;
            
            // Cache key
            $cache_key = "persons_table_{$per_page}_{$current_page}_{$orderby}_{$order}_{$company_filter}";
            $cached_result = $this->cache_manager->get($cache_key);
            
            if ($cached_result !== false) {
                $this->items = $cached_result['items'];
                $this->set_pagination_args($cached_result['pagination']);
                $this->performance_monitor->end_timer($timer_id);
                return;
            }
            
            $args = [
                'post_type'      => 'person',
                'posts_per_page' => $per_page,
                'paged'          => $current_page,
                'orderby'        => $orderby,
                'order'          => $order,
                // **Kluczowe: logika archiwizacji!**
                'meta_query'     => [
                    'relation' => 'OR',
                    [
                        'key'     => 'person_status',
                        'value'   => 'Zarchiwizowany',
                        'compare' => '!='
                    ],
                    [
                        'key'     => 'person_status',
                        'compare' => 'NOT EXISTS'
                    ]
                ]
            ];

            // Dodajemy filtr firmy, jeśli został wybrany
            if (!empty($company_filter)) {
                $args['meta_query'][] = [
                    'key' => 'person_company', // Nazwa pola relacji ACF
                    'value' => '"' . intval($company_filter) . '"',
                    'compare' => 'LIKE'
                ];
            }

            $query = new WP_Query($args);
            $this->items = $query->posts;
            
            $pagination_args = [
                'total_items' => $query->found_posts,
                'per_page'    => $per_page
            ];
            
            $this->set_pagination_args($pagination_args);
            
            // Cache wynik na 5 minut
            $cache_data = [
                'items' => $this->items,
                'pagination' => $pagination_args
            ];
            $this->cache_manager->set($cache_key, $cache_data, 300);
            
            $this->performance_monitor->end_timer($timer_id);
            
        } catch (Exception $e) {
            $this->performance_monitor->end_timer($timer_id);
            WPMZF_Logger::error('Error in prepare_items for persons table', ['error' => $e->getMessage()]);
            $this->items = [];
            $this->set_pagination_args(['total_items' => 0, 'per_page' => $per_page]);
        }
    }
}