<?php
// WP_List_Table musi być załadowane ręcznie na stronach niestandardowych
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class WPMZF_Documents_List_Table extends WP_List_Table {

    /**
     * Konstruktor - ustawia podstawowe informacje o tabeli.
     */
    public function __construct() {
        parent::__construct([
            'singular' => 'Dokument', // Nazwa pojedynczego elementu
            'plural'   => 'Dokumenty', // Nazwa w liczbie mnogiej
            'ajax'     => false // Czy tabela ma wspierać AJAX
        ]);
    }

    /**
     * Definiuje kolumny tabeli. To jest kluczowa metoda.
     * @return array
     */
public function get_columns() {
        return [
            'cb'          => '<input type="checkbox" />', // Checkbox do masowych akcji
            'title'       => 'Tytuł Dokumentu',
            'doc_type'    => 'Typ', // Pole własne z ACF
            'related_client' => 'Klient', // Pole relacji z ACF
            'status'      => 'Status', // Pole własne z ACF
            'date'        => 'Data Utworzenia'
        ];
    }

    /**
     * Definiuje, które kolumny są sortowalne.
     * @return array
     */
    protected function get_sortable_columns() {
        return [
            'title'    => ['title', false],
            'doc_type' => ['doc_type', false],
            'status'   => ['status', false],
            'date'     => ['date', true] // true oznacza, że domyślnie sortujemy malejąco
        ];
    }

    /**
     * Definiuje domyślną zawartość komórki, jeśli nie ma dedykowanej metody.
     * @param object $item
     * @param string $column_name
     * @return mixed
     */
    function column_default($item, $column_name) {
        switch ($column_name) {
            case 'doc_type':
                // Zwracamy przyjazną nazwę CPT
                $post_type_obj = get_post_type_object($item->post_type);
                return $post_type_obj ? $post_type_obj->labels->singular_name : $item->post_type;
            case 'status':
                // Klucze pól statusu dla różnych CPT
                $status_field_key = '';
                if ($item->post_type === 'quote') $status_field_key = 'quote_status';
                // Dodaj inne CPT jeśli mają status, np. 'contract_status'
                
                return $status_field_key ? get_field($status_field_key, $item->ID) : '—';
            case 'date':
                return $item->post_date;
            default:
                return print_r($item, true); // Do debugowania
        }
    }

    /**
     * Dedykowana metoda do renderowania kolumny 'title'. Dodaje akcje (Edytuj, Usuń).
     */
    function column_title($item) {
        $edit_link = get_edit_post_link($item->ID);
        $delete_link = get_delete_post_link($item->ID);

        $actions = [
            'edit' => sprintf('<a href="%s">Edytuj</a>', esc_url($edit_link)),
            'delete' => sprintf('<a href="%s" class="submitdelete">Usuń</a>', esc_url($delete_link))
        ];

        return sprintf('<strong><a class="row-title" href="%s">%s</a></strong>%s', $edit_link, $item->post_title, $this->row_actions($actions));
    }

    /**
     * Dedykowana metoda dla kolumny powiązanego klienta.
     */
    function column_related_client($item) {
        // Klucze pól relacji dla różnych CPT
        $relation_field_key = '';
        if ($item->post_type === 'quote') $relation_field_key = 'quote_company';
        if ($item->post_type === 'contract') $relation_field_key = 'contract_company'; // Założenie nazwy pola
        
        $client_id = get_field($relation_field_key, $item->ID); 
        if ($client_id) {
            // W ACF pole relacji może być pojedynczym ID lub tablicą ID
            $client_id = is_array($client_id) ? $client_id[0] : $client_id;
            $client_title = get_the_title($client_id);
            $client_link = get_edit_post_link($client_id);
            return sprintf('<a href="%s">%s</a>', esc_url($client_link), esc_html($client_title));
        }
        return '<em>Brak</em>';
    }

    /**
     * Metoda dla checkboxów.
     */
    function column_cb($item) {
        return sprintf('<input type="checkbox" name="bulk-delete[]" value="%s" />', $item->ID);
    }
    
    /**
     * Pobiera i przygotowuje dane do wyświetlenia. To jest serce tej klasy.
     */
    public function prepare_items() {
        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = [$columns, $hidden, $sortable];

        // Pobieramy dane z bazy
        $args = [
            'post_type' => ['quote', 'contract'], // Używamy poprawnych slugów CPT
            'posts_per_page' => 20, // Paginacja
            'orderby' => isset($_GET['orderby']) ? sanitize_key($_GET['orderby']) : 'date',
            'order' => isset($_GET['order']) ? strtoupper(sanitize_key($_GET['order'])) : 'DESC',
        ];

        // Sortowanie po polach meta (status, typ)
        if (in_array($args['orderby'], ['doc_type', 'status'])) {
            $args['meta_key'] = $args['orderby'] === 'doc_type' ? 'post_type' : ($args['orderby'] === 'status' ? 'quote_status' : ''); // Uproszczenie, wymaga rozbudowy dla wielu statusów
            $args['orderby'] = 'meta_value';
        }


        $query = new WP_Query($args);
        $this->items = $query->posts;

        // Ustawienia paginacji
        $this->set_pagination_args([
            'total_items' => $query->found_posts,
            'per_page'    => 20
        ]);
    }
}