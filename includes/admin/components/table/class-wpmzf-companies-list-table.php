<?php
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class WPMZF_companies_List_Table extends WP_List_Table {

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
            'singular' => 'Firma',
            'plural'   => 'Firmy',
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
            'name'     => 'Nazwa (NIP)',
            'status'   => 'Status',
            'email'    => 'E-mail',
            'phone'    => 'Telefon',
            'persons'  => 'Osoby',
            'projects' => 'Projekty',
            'date'     => 'Data dodania'
        ];
    }
    
    // Definicja kolumn sortowalnych
    protected function get_sortable_columns() {
        return [
            'name' => ['title', false],
            'date' => ['date', true]
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
            case 'status':
                $status = get_field('company_status', $item->ID) ?: 'Aktywny';
                $status_class = 'status-' . strtolower(str_replace(' ', '-', $status));
                return '<span class="company-status-badge ' . $status_class . '">' . esc_html($status) . '</span>';
            case 'email':
                return WPMZF_Contact_Helper::get_primary_company_email($item->ID) ?? 'Brak';
            case 'phone':
                return WPMZF_Contact_Helper::get_primary_company_phone($item->ID) ?? 'Brak';
            case 'persons':
                $persons_count = count(WPMZF_Person::get_persons(array(
                    'meta_query' => array(
                        array(
                            'key' => 'person_company',
                            'value' => '"' . $item->ID . '"',
                            'compare' => 'LIKE'
                        )
                    )
                )));
                return $persons_count;
            case 'projects':
                $projects_count = count(WPMZF_Project::get_projects(array(
                    'meta_query' => array(
                        array(
                            'key' => 'project_company',
                            'value' => '"' . $item->ID . '"',
                            'compare' => 'LIKE'
                        )
                    )
                )));
                return $projects_count;
            case 'date':
                return get_the_date('Y-m-d H:i', $item->ID);
            default:
                return print_r($item, true);
        }
    }
    
    // Specjalna metoda dla kolumny z nazwą firmy
    function column_name($item) {
        $nip = get_field('company_nip', $item->ID);
        
        $name = '<strong>' . $item->post_title . '</strong>';
        if ($nip) {
            $name .= ' <span style="color:#777;">(NIP: ' . esc_html($nip) . ')</span>';
        }

        // Linki akcji (View, Edit, Archive...)
        $view_link = add_query_arg(
            [
                'page' => 'wpmzf_view_company',
                'company_id' => $item->ID
            ],
            admin_url('admin.php')
        );
        $edit_link = sprintf('post.php?post=%s&action=edit', $item->ID);
        $actions = [
            'view'    => '<a href="' . esc_url($view_link) . '">Zobacz szczegóły</a>',
            'edit'    => '<a href="' . $edit_link . '">Edytuj</a>',
            'archive' => '<a href="?page=' . $_REQUEST['page'] . '&action=archive&company=' . $item->ID . '">Archiwizuj</a>',
            'delete'  => '<a href="?page=' . $_REQUEST['page'] . '&action=delete&company=' . $item->ID . '" class="submitdelete">Usuń</a>'
        ];

        return $name . $this->row_actions($actions);
    }
    
    function column_cb($item) {
        return sprintf('<input type="checkbox" name="bulk-action[]" value="%s" />', $item->ID);
    }

    /**
     * Dodaje dodatkowe kontrolki nawigacji (np. filtry).
     */
    protected function extra_tablenav($which) {
        if ($which == "top") {
            echo '<div class="alignleft actions">';
            
            // Filtr statusu
            $current_status = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';
            echo '<select name="status_filter">';
            echo '<option value="">Wszystkie statusy</option>';
            echo '<option value="Aktywny"' . selected($current_status, 'Aktywny', false) . '>Aktywny</option>';
            echo '<option value="Nieaktywny"' . selected($current_status, 'Nieaktywny', false) . '>Nieaktywny</option>';
            echo '<option value="Zarchiwizowany"' . selected($current_status, 'Zarchiwizowany', false) . '>Zarchiwizowany</option>';
            echo '</select>';
            
            submit_button('Filtruj', 'secondary', 'filter', false);
            echo '</div>';
        }
    }

    /**
     * Przetwarza masowe akcje.
     */
    public function process_bulk_action() {
        // Sprawdzenie nonce dla bezpieczeństwa
        if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'bulk-' . $this->_args['plural'])) {
            return;
        }
        
        // Sprawdzamy, czy akcja została wykonana
        if ('delete' === $this->current_action()) {
            $post_ids = isset($_POST['bulk-action']) ? array_map('intval', $_POST['bulk-action']) : [];
            
            foreach ($post_ids as $post_id) {
                // Sprawdź uprawnienia
                if (current_user_can('delete_post', $post_id)) {
                    wp_delete_post($post_id, true);
                    WPMZF_Logger::info('Company deleted via bulk action', ['company_id' => $post_id]);
                } else {
                    WPMZF_Logger::log_security_violation('Attempt to delete company without permissions', null, ['company_id' => $post_id]);
                }
            }
        }

        if ('archive' === $this->current_action()) {
            $post_ids = isset($_POST['bulk-action']) ? array_map('intval', $_POST['bulk-action']) : [];
            
            foreach ($post_ids as $post_id) {
                // Sprawdź uprawnienia
                if (current_user_can('edit_post', $post_id)) {
                    update_field('company_status', 'Zarchiwizowany', $post_id);
                    WPMZF_Logger::info('Company archived via bulk action', ['company_id' => $post_id]);
                } else {
                    WPMZF_Logger::log_security_violation('Attempt to archive company without permissions', null, ['company_id' => $post_id]);
                }
            }
        }
    }

    /**
     * Przygotowuje dane do wyświetlenia w tabeli.
     */
    public function prepare_items() {
        $this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns()];
        
        $this->process_bulk_action();

        $per_page = 20;
        $current_page = $this->get_pagenum();
        
        $args = [
            'post_type'      => 'company',
            'posts_per_page' => $per_page,
            'paged'          => $current_page,
            'orderby'        => isset($_GET['orderby']) ? sanitize_key($_GET['orderby']) : 'date',
            'order'          => isset($_GET['order']) ? sanitize_key($_GET['order']) : 'DESC',
            // **Kluczowe: logika archiwizacji!**
            'meta_query'     => [
                'relation' => 'OR',
                [
                    'key'     => 'company_status',
                    'value'   => 'Zarchiwizowany',
                    'compare' => '!='
                ],
                [
                    'key'     => 'company_status',
                    'compare' => 'NOT EXISTS'
                ]
            ]
        ];

        // Dodajemy filtr statusu, jeśli został wybrany
        if (!empty($_GET['status_filter']) && $_GET['status_filter'] !== 'Zarchiwizowany') {
            // Jeśli wybrano konkretny status (nie "Zarchiwizowany"), nadpisujemy meta_query
            $args['meta_query'] = [
                [
                    'key' => 'company_status',
                    'value' => sanitize_text_field($_GET['status_filter']),
                    'compare' => '='
                ]
            ];
        } elseif (!empty($_GET['status_filter']) && $_GET['status_filter'] === 'Zarchiwizowany') {
            // Jeśli wybrano "Zarchiwizowany", pokazujemy tylko zarchiwizowane
            $args['meta_query'] = [
                [
                    'key' => 'company_status',
                    'value' => 'Zarchiwizowany',
                    'compare' => '='
                ]
            ];
        }

        // Wyszukiwanie
        if (!empty($_GET['s'])) {
            $args['s'] = sanitize_text_field($_GET['s']);
        }

        $query = new WP_Query($args);
        $this->items = $query->posts;
        
        $this->set_pagination_args([
            'total_items' => $query->found_posts,
            'per_page'    => $per_page
        ]);
    }
}
