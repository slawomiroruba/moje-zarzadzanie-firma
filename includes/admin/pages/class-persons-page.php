<?php

/**
 * Strona osób
 */
class WPMZF_Persons_Page extends WPMZF_Admin_Page_Base
{
    /**
     * Inicjalizacja strony osób
     */
    protected function init()
    {
        $this->page_slug = 'wpmzf_persons';
        $this->page_title = 'Osoby';
        $this->menu_title = 'Osoby';
        $this->capability = 'manage_options';
    }

    /**
     * Renderowanie strony osób
     */
    public function render()
    {
        // Sprawdź czy to widok pojedynczej osoby
        if (isset($_GET['person_id']) && intval($_GET['person_id']) > 0) {
            $this->render_single_person();
            return;
        }

        // Renderuj listę osób
        $this->render_persons_list();
    }

    /**
     * Renderuje listę osób
     */
    private function render_persons_list()
    {
        // Użyj istniejącej klasy tabeli jeśli istnieje
        if (class_exists('WPMZF_Persons_List_Table')) {
            $list_table = new WPMZF_Persons_List_Table();
            $list_table->prepare_items();
            
            echo '<div class="wrap">';
            echo '<h1>' . esc_html(get_admin_page_title()) . '</h1>';
            echo '<form method="post">';
            $list_table->display();
            echo '</form>';
            echo '</div>';
        } else {
            // Fallback - prosta lista
            $this->render_simple_persons_list();
        }
    }

    /**
     * Prosta lista osób (fallback)
     */
    private function render_simple_persons_list()
    {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html(get_admin_page_title()) . '</h1>';
        
        $persons = get_posts(array(
            'post_type' => 'person',
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ));

        if (empty($persons)) {
            echo '<p>Brak osób do wyświetlenia.</p>';
        } else {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead>';
            echo '<tr>';
            echo '<th>Imię i nazwisko</th>';
            echo '<th>Email</th>';
            echo '<th>Telefon</th>';
            echo '<th>Akcje</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';

            foreach ($persons as $person) {
                $person_url = add_query_arg('person_id', $person->ID, admin_url('admin.php?page=' . $this->page_slug));
                $email = get_field('email', $person->ID);
                $phone = get_field('telefon', $person->ID);
                
                echo '<tr>';
                echo '<td><strong>' . esc_html($person->post_title) . '</strong></td>';
                echo '<td>' . esc_html($email) . '</td>';
                echo '<td>' . esc_html($phone) . '</td>';
                echo '<td><a href="' . esc_url($person_url) . '" class="button">Zobacz</a></td>';
                echo '</tr>';
            }

            echo '</tbody>';
            echo '</table>';
        }

        echo '</div>';
    }

    /**
     * Renderuje widok pojedynczej osoby
     */
    private function render_single_person()
    {
        $person_id = intval($_GET['person_id']);
        
        if ($person_id <= 0) {
            $this->render_error('Nieprawidłowe ID osoby.');
            return;
        }
        
        // Sprawdź czy osoba istnieje
        $person = get_post($person_id);
        if (!$person || $person->post_type !== 'person') {
            $this->render_error('Osoba nie została znaleziona.');
            return;
        }
        
        // Renderuj widok pojedynczej osoby
        $view_path = $this->get_view_path('persons/person-view.php');
        if (file_exists($view_path)) {
            include $view_path;
        } else {
            $this->render_error('Szablon widoku osoby nie został znaleziony.');
        }
    }

    /**
     * Renderuje komunikat błędu
     */
    private function render_error($message)
    {
        echo '<div class="wrap">';
        echo '<h1>Błąd</h1>';
        echo '<p>' . esc_html($message) . '</p>';
        echo '<a href="' . esc_url(admin_url('admin.php?page=' . $this->page_slug)) . '" class="button">Powrót do listy osób</a>';
        echo '</div>';
    }

    /**
     * Ładowanie stylów specyficznych dla osób
     */
    protected function enqueue_styles()
    {
        parent::enqueue_styles();
        
        wp_enqueue_style(
            'wpmzf-persons',
            $this->get_asset_url('css/admin/persons.css'),
            array('wpmzf-admin-base'),
            '1.0.0'
        );
    }

    /**
     * Ładowanie skryptów specyficznych dla osób
     */
    protected function enqueue_scripts()
    {
        parent::enqueue_scripts();
        
        // Ładuj skrypty tylko dla widoku pojedynczej osoby
        if (isset($_GET['person_id']) && intval($_GET['person_id']) > 0) {
            // Skrypty ACF dla pól relacji
            if (function_exists('acf_enqueue_scripts')) {
                acf_enqueue_scripts();
            }
            
            // Edytor WYSIWYG
            wp_enqueue_editor();
            wp_enqueue_media();
            
            wp_enqueue_script(
                'wpmzf-person-view',
                $this->get_asset_url('js/admin/person-view.js'),
                array('jquery', 'editor'),
                '1.0.4',
                true
            );
            
            // Zmienne dla JavaScript
            wp_localize_script('wpmzf-person-view', 'wpmzfPersonView', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'adminUrl' => admin_url(),
                'nonce' => wp_create_nonce('wpmzf_person_view_nonce'),
                'taskNonce' => wp_create_nonce('wpmzf_task_nonce'),
                'personId' => intval($_GET['person_id'])
            ));
        }
    }
}
