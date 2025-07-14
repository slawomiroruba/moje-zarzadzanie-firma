<?php

/**
 * Strona projektów
 */
class WPMZF_Projects_Page extends WPMZF_Admin_Page_Base
{
    /**
     * Inicjalizacja strony projektów
     */
    protected function init()
    {
        $this->page_slug = 'wpmzf_projects';
        $this->page_title = 'Projekty';
        $this->menu_title = 'Projekty';
        $this->capability = 'manage_options';
    }

    /**
     * Renderowanie strony projektów
     */
    public function render()
    {
        // Sprawdź czy to widok pojedynczego projektu
        if (isset($_GET['project_id']) && intval($_GET['project_id']) > 0) {
            $this->render_single_project();
            return;
        }

        // Renderuj listę projektów
        $this->render_projects_list();
    }

    /**
     * Renderuje listę projektów
     */
    private function render_projects_list()
    {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html(get_admin_page_title()) . '</h1>';
        
        // Pobierz projekty
        $projects = get_posts(array(
            'post_type' => 'project',
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'date',
            'order' => 'DESC'
        ));

        if (empty($projects)) {
            echo '<p>Brak projektów do wyświetlenia.</p>';
        } else {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead>';
            echo '<tr>';
            echo '<th>Nazwa projektu</th>';
            echo '<th>Data utworzenia</th>';
            echo '<th>Status</th>';
            echo '<th>Akcje</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';

            foreach ($projects as $project) {
                $project_url = add_query_arg('project_id', $project->ID, admin_url('admin.php?page=' . $this->page_slug));
                
                echo '<tr>';
                echo '<td><strong>' . esc_html($project->post_title) . '</strong></td>';
                echo '<td>' . esc_html($project->post_date) . '</td>';
                echo '<td>' . esc_html($project->post_status) . '</td>';
                echo '<td><a href="' . esc_url($project_url) . '" class="button">Zobacz</a></td>';
                echo '</tr>';
            }

            echo '</tbody>';
            echo '</table>';
        }

        echo '</div>';
    }

    /**
     * Renderuje widok pojedynczego projektu
     */
    private function render_single_project()
    {
        $project_id = intval($_GET['project_id']);
        
        if ($project_id <= 0) {
            $this->render_error('Nieprawidłowe ID projektu.');
            return;
        }
        
        // Sprawdź czy projekt istnieje
        $project = get_post($project_id);
        if (!$project || $project->post_type !== 'project') {
            $this->render_error('Projekt nie został znaleziony.');
            return;
        }
        
        // Renderuj widok pojedynczego projektu
        $view_path = $this->get_view_path('projects/project-view.php');
        if (file_exists($view_path)) {
            include $view_path;
        } else {
            $this->render_error('Szablon widoku projektu nie został znaleziony.');
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
        echo '<a href="' . esc_url(admin_url('admin.php?page=' . $this->page_slug)) . '" class="button">Powrót do listy projektów</a>';
        echo '</div>';
    }

    /**
     * Ładowanie stylów specyficznych dla projektów
     */
    protected function enqueue_styles()
    {
        parent::enqueue_styles();
        
        wp_enqueue_style(
            'wpmzf-projects',
            $this->get_asset_url('css/admin/projects.css'),
            array('wpmzf-admin-base'),
            '1.0.0'
        );
    }

    /**
     * Ładowanie skryptów specyficznych dla projektów
     */
    protected function enqueue_scripts()
    {
        parent::enqueue_scripts();
        
        // Ładuj skrypty tylko dla widoku pojedynczego projektu
        if (isset($_GET['project_id']) && intval($_GET['project_id']) > 0) {
            // Skrypty ACF dla pól relacji
            if (function_exists('acf_enqueue_scripts')) {
                acf_enqueue_scripts();
            }
            
            // Edytor WYSIWYG
            wp_enqueue_editor();
            wp_enqueue_media();
            
            wp_enqueue_script(
                'wpmzf-project-view',
                $this->get_asset_url('js/admin/project-view.js'),
                array('jquery', 'editor'),
                '1.0.0',
                true
            );
            
            // Zmienne dla JavaScript
            wp_localize_script('wpmzf-project-view', 'wpmzfProjectView', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'adminUrl' => admin_url(),
                'nonce' => wp_create_nonce('wpmzf_project_view_nonce'),
                'projectId' => intval($_GET['project_id'])
            ));
        }
    }
}
