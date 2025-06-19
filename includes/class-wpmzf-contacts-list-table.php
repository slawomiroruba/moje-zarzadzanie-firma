<?php
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class WPMZF_Contacts_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct([
            'singular' => 'Kontakt',
            'plural'   => 'Kontakty',
            'ajax'     => false
        ]);
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
                return get_field('contact_email', $item->ID);
            case 'phone':
                 return get_field('contact_phone', $item->ID);
            case 'date':
                return get_the_date('Y-m-d H:i', $item->ID);
            default:
                return print_r($item, true);
        }
    }
    
    // Specjalna metoda dla kolumny z imieniem i nazwiskiem
    function column_full_name($item) {
        $company_id = get_field('contact_company', $item->ID);
        $company_name = $company_id ? get_the_title($company_id[0]) : '';
        
        $full_name = '<strong>' . $item->post_title . '</strong>';
        if ($company_name) {
            $full_name .= ' <span style="color:#777;">(' . esc_html($company_name) . ')</span>';
        }

        // Linki akcji (View, Edit, Archive...)
        $view_link = sprintf('?page=wpmzf_contact_view&contact_id=%s', $item->ID);
        $actions = [
            'view'    => '<a href="' . $view_link . '">Zobacz teczkę</a>',
            'archive' => '<a href="?page=' . $_REQUEST['page'] . '&action=archive&contact=' . $item->ID . '">Archiwizuj</a>',
            'delete'  => '<a href="?page=' . $_REQUEST['page'] . '&action=delete&contact=' . $item->ID . '" class="submitdelete">Usuń</a>'
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
        // Sprawdzamy, czy akcja została wykonana
        if ('delete' === $this->current_action()) {
            $post_ids = esc_sql($_POST['bulk-action']);
            foreach ($post_ids as $post_id) {
                wp_delete_post($post_id, true); // true = usuń trwale
            }
        }

        if ('archive' === $this->current_action()) {
            $post_ids = esc_sql($_POST['bulk-action']);
            foreach ($post_ids as $post_id) {
                // Używamy funkcji ACF do aktualizacji pola
                update_field('contact_status', 'Zarchiwizowany', $post_id);
            }
        }
    }

    // Główna metoda pobierająca dane
    public function prepare_items() {
        $this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns()];
        
        $this->process_bulk_action();

        $per_page = 20;
        $current_page = $this->get_pagenum();
        
        $args = [
            'post_type'      => 'contact',
            'posts_per_page' => $per_page,
            'paged'          => $current_page,
            'orderby'        => isset($_GET['orderby']) ? sanitize_key($_GET['orderby']) : 'date',
            'order'          => isset($_GET['order']) ? sanitize_key($_GET['order']) : 'DESC',
            // **Kluczowe: logika archiwizacji!**
            'meta_query'     => [
                'relation' => 'OR',
                [
                    'key'     => 'contact_status',
                    'value'   => 'zarchiwizowany',
                    'compare' => '!='
                ],
                [
                    'key'     => 'contact_status',
                    'compare' => 'NOT EXISTS'
                ]
            ]
        ];

        // Dodajemy filtr firmy, jeśli został wybrany
        if (!empty($_GET['company_filter'])) {
            $args['meta_query'][] = [
                'key' => 'contact_company', // Nazwa pola relacji ACF
                'value' => '"' . intval($_GET['company_filter']) . '"',
                'compare' => 'LIKE'
            ];
        }

        $query = new WP_Query($args);
        $this->items = $query->posts;
        
        $this->set_pagination_args([
            'total_items' => $query->found_posts,
            'per_page'    => $per_page
        ]);
    }
}