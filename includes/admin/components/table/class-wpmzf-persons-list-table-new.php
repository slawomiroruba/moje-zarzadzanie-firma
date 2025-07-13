<?php
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class WPMZF_persons_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct([
            'singular' => 'Osoba',
            'plural'   => 'Osoby',
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

    // Checkbox dla wiersza
    protected function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="bulk-action[]" value="%s" />',
            esc_attr($item['id'])
        );
    }

    // Kolumna główna z nazwą osoby i firmą
    protected function column_full_name($item) {
        $view_url = add_query_arg([
            'page' => 'wpmzf_view_person',
            'person_id' => $item['id']
        ], admin_url('admin.php'));

        $edit_url = get_edit_post_link($item['id']);
        $delete_url = get_delete_post_link($item['id']);

        $actions = [];
        if ($view_url) {
            $actions['view'] = sprintf('<a href="%s">%s</a>', esc_url($view_url), __('Zobacz', 'wpmzf'));
        }
        if ($edit_url) {
            $actions['edit'] = sprintf('<a href="%s">%s</a>', esc_url($edit_url), __('Edytuj', 'wpmzf'));
        }
        if ($delete_url) {
            $actions['delete'] = sprintf('<a href="%s" onclick="return confirm(\'%s\')">%s</a>', 
                esc_url($delete_url), 
                __('Czy na pewno chcesz usunąć tę osobę?', 'wpmzf'), 
                __('Usuń', 'wpmzf')
            );
        }

        $person_name = sprintf('<strong><a href="%s" class="row-title">%s</a></strong>', 
            esc_url($view_url), 
            esc_html($item['full_name'])
        );

        $company_info = '';
        if (!empty($item['company'])) {
            $company_info = '<br><small style="color: #666;">' . esc_html($item['company']) . '</small>';
        }

        return $person_name . $company_info . $this->row_actions($actions);
    }

    // Kolumna email
    protected function column_email($item) {
        if (!empty($item['email'])) {
            return sprintf('<a href="mailto:%s">%s</a>', esc_attr($item['email']), esc_html($item['email']));
        }
        return '-';
    }

    // Kolumna telefon
    protected function column_phone($item) {
        if (!empty($item['phone'])) {
            return sprintf('<a href="tel:%s">%s</a>', esc_attr($item['phone']), esc_html($item['phone']));
        }
        return '-';
    }

    // Kolumna data
    protected function column_date($item) {
        return date_i18n('j.m.Y H:i', strtotime($item['date']));
    }

    // Kolumna domyślna
    protected function column_default($item, $column_name) {
        return isset($item[$column_name]) ? esc_html($item[$column_name]) : '-';
    }

    // Akcje grupowe
    protected function get_bulk_actions() {
        return [
            'delete' => __('Usuń', 'wpmzf'),
            'archive' => __('Archiwizuj', 'wpmzf')
        ];
    }

    // Przetwarzanie akcji grupowych
    public function process_bulk_action() {
        $action = $this->current_action();
        
        if (!$action) {
            return;
        }

        // Sprawdzenie nonce
        if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'bulk-' . $this->_args['plural'])) {
            wp_die(__('Błąd bezpieczeństwa. Odśwież stronę i spróbuj ponownie.', 'wpmzf'));
        }

        $post_ids = isset($_POST['bulk-action']) ? array_map('intval', $_POST['bulk-action']) : [];
        
        if (empty($post_ids)) {
            return;
        }

        switch ($action) {
            case 'delete':
                foreach ($post_ids as $post_id) {
                    if (current_user_can('delete_post', $post_id)) {
                        wp_delete_post($post_id, true);
                    }
                }
                break;
            
            case 'archive':
                foreach ($post_ids as $post_id) {
                    if (current_user_can('edit_post', $post_id)) {
                        update_post_meta($post_id, 'person_status', 'archived');
                    }
                }
                break;
        }
    }

    // Przygotowanie danych
    public function prepare_items() {
        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();
        
        $this->_column_headers = [$columns, $hidden, $sortable];
        
        // Przetwórz akcje grupowe
        $this->process_bulk_action();
        
        // Parametry paginacji
        $per_page = 20;
        $current_page = $this->get_pagenum();
        
        // Parametry sortowania
        $orderby = (!empty($_GET['orderby'])) ? sanitize_text_field($_GET['orderby']) : 'date';
        $order = (!empty($_GET['order'])) ? sanitize_text_field($_GET['order']) : 'desc';
        
        // Mapowanie kolumn na meta_key lub post field
        $orderby_map = [
            'full_name' => 'title',
            'date' => 'date'
        ];
        
        $wp_orderby = isset($orderby_map[$orderby]) ? $orderby_map[$orderby] : 'date';
        
        // Query args
        $args = [
            'post_type' => 'person',
            'posts_per_page' => $per_page,
            'paged' => $current_page,
            'orderby' => $wp_orderby,
            'order' => strtoupper($order),
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => 'person_status',
                    'value' => ['archived', 'Zarchiwizowany'],
                    'compare' => 'NOT IN'
                ],
                [
                    'key' => 'person_status',
                    'compare' => 'NOT EXISTS'
                ]
            ]
        ];
        
        // Wyszukiwanie
        if (!empty($_GET['s'])) {
            $args['s'] = sanitize_text_field($_GET['s']);
        }
        
        $query = new WP_Query($args);
        
        // Przygotuj dane
        $data = [];
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                
                // Pobierz dane firmy
                $company_id = get_field('person_company', $post_id);
                $company_name = '';
                if ($company_id && is_array($company_id) && !empty($company_id)) {
                    $company_name = get_the_title($company_id[0]);
                }
                
                $data[] = [
                    'id' => $post_id,
                    'full_name' => get_the_title(),
                    'company' => $company_name,
                    'email' => get_field('person_email', $post_id) ?: '',
                    'phone' => get_field('person_phone', $post_id) ?: '',
                    'date' => get_the_date('Y-m-d H:i:s')
                ];
            }
            wp_reset_postdata();
        }
        
        $this->items = $data;
        
        // Ustaw paginację
        $this->set_pagination_args([
            'total_items' => $query->found_posts,
            'per_page' => $per_page,
            'total_pages' => ceil($query->found_posts / $per_page)
        ]);
    }
}
