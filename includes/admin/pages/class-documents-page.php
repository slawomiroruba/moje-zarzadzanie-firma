<?php

/**
 * Strona dokumentów
 */
class WPMZF_Documents_Page extends WPMZF_Admin_Page_Base
{
    /**
     * Inicjalizacja strony dokumentów
     */
    protected function init()
    {
        $this->page_slug = 'wpmzf_documents';
        $this->page_title = 'Dokumenty';
        $this->menu_title = 'Dokumenty';
        $this->capability = 'manage_options';
    }

    /**
     * Renderowanie strony dokumentów
     */
    public function render()
    {
        // Użyj istniejącej klasy tabeli jeśli istnieje
        if (class_exists('WPMZF_Documents_List_Table')) {
            $list_table = new WPMZF_Documents_List_Table();
            $list_table->prepare_items();
            
            echo '<div class="wrap">';
            echo '<h1>' . esc_html(get_admin_page_title()) . '</h1>';
            echo '<form method="post">';
            $list_table->display();
            echo '</form>';
            echo '</div>';
        } else {
            // Fallback - prosta lista
            $this->render_simple_documents_list();
        }
    }

    /**
     * Prosta lista dokumentów (fallback)
     */
    private function render_simple_documents_list()
    {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html(get_admin_page_title()) . '</h1>';
        
        $documents = get_posts(array(
            'post_type' => 'document',
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'date',
            'order' => 'DESC'
        ));

        if (empty($documents)) {
            echo '<p>Brak dokumentów do wyświetlenia.</p>';
        } else {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead>';
            echo '<tr>';
            echo '<th>Nazwa dokumentu</th>';
            echo '<th>Typ</th>';
            echo '<th>Data utworzenia</th>';
            echo '<th>Akcje</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';

            foreach ($documents as $document) {
                $edit_url = get_edit_post_link($document->ID);
                $document_type = get_field('typ_dokumentu', $document->ID);
                
                echo '<tr>';
                echo '<td><strong>' . esc_html($document->post_title) . '</strong></td>';
                echo '<td>' . esc_html($document_type) . '</td>';
                echo '<td>' . esc_html($document->post_date) . '</td>';
                echo '<td>';
                if ($edit_url) {
                    echo '<a href="' . esc_url($edit_url) . '" class="button">Edytuj</a>';
                }
                echo '</td>';
                echo '</tr>';
            }

            echo '</tbody>';
            echo '</table>';
        }

        echo '</div>';
    }

    /**
     * Ładowanie stylów specyficznych dla dokumentów
     */
    protected function enqueue_styles()
    {
        parent::enqueue_styles();
        
        wp_enqueue_style(
            'wpmzf-documents',
            $this->get_asset_url('css/admin/documents.css'),
            array('wpmzf-admin-base'),
            '1.0.0'
        );
    }

    /**
     * Ładowanie skryptów specyficznych dla dokumentów
     */
    protected function enqueue_scripts()
    {
        parent::enqueue_scripts();
        
        wp_enqueue_script(
            'wpmzf-documents',
            $this->get_asset_url('js/admin/documents.js'),
            array('jquery'),
            '1.0.0',
            true
        );
        
        // Zmienne dla JavaScript
        wp_localize_script('wpmzf-documents', 'wpmzfDocuments', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'adminUrl' => admin_url(),
            'nonce' => wp_create_nonce('wpmzf_documents_nonce')
        ));
    }
}
