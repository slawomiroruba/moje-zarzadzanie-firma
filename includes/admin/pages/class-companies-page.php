<?php

/**
 * Strona firm
 */
class WPMZF_Companies_Page extends WPMZF_Admin_Page_Base
{
    /**
     * Inicjalizacja strony firm
     */
    protected function init()
    {
        $this->page_slug = 'wpmzf_companies';
        $this->page_title = 'Firmy';
        $this->menu_title = 'Firmy';
        $this->capability = 'manage_options';
    }

    /**
     * Renderowanie strony firm
     */
    public function render()
    {
        // Sprawdź czy to widok pojedynczej firmy
        if (isset($_GET['company_id']) && intval($_GET['company_id']) > 0) {
            $this->render_single_company();
            return;
        }

        // Renderuj listę firm
        $this->render_companies_list();
    }

    /**
     * Renderuje listę firm
     */
    private function render_companies_list()
    {
        // Użyj istniejącej klasy tabeli jeśli istnieje
        if (class_exists('WPMZF_Companies_List_Table')) {
            $list_table = new WPMZF_Companies_List_Table();
            $list_table->prepare_items();
            
            echo '<div class="wrap">';
            echo '<h1>' . esc_html(get_admin_page_title()) . '</h1>';
            echo '<form method="post">';
            $list_table->display();
            echo '</form>';
            echo '</div>';
        } else {
            // Fallback - prosta lista
            $this->render_simple_companies_list();
        }
    }

    /**
     * Prosta lista firm (fallback)
     */
    private function render_simple_companies_list()
    {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html(get_admin_page_title()) . '</h1>';
        
        $companies = get_posts(array(
            'post_type' => 'company',
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ));

        if (empty($companies)) {
            echo '<p>Brak firm do wyświetlenia.</p>';
        } else {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead>';
            echo '<tr>';
            echo '<th>Nazwa firmy</th>';
            echo '<th>NIP</th>';
            echo '<th>Email</th>';
            echo '<th>Akcje</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';

            foreach ($companies as $company) {
                $company_url = add_query_arg('company_id', $company->ID, admin_url('admin.php?page=' . $this->page_slug));
                $nip = get_field('nip', $company->ID);
                $email = get_field('email', $company->ID);
                
                echo '<tr>';
                echo '<td><strong>' . esc_html($company->post_title) . '</strong></td>';
                echo '<td>' . esc_html($nip) . '</td>';
                echo '<td>' . esc_html($email) . '</td>';
                echo '<td><a href="' . esc_url($company_url) . '" class="button">Zobacz</a></td>';
                echo '</tr>';
            }

            echo '</tbody>';
            echo '</table>';
        }

        echo '</div>';
    }

    /**
     * Renderuje widok pojedynczej firmy
     */
    private function render_single_company()
    {
        $company_id = intval($_GET['company_id']);
        
        if ($company_id <= 0) {
            $this->render_error('Nieprawidłowe ID firmy.');
            return;
        }
        
        // Sprawdź czy firma istnieje
        $company = get_post($company_id);
        if (!$company || $company->post_type !== 'company') {
            $this->render_error('Firma nie została znaleziona.');
            return;
        }
        
        // Renderuj widok pojedynczej firmy
        $view_path = $this->get_view_path('companies/company-view.php');
        if (file_exists($view_path)) {
            include $view_path;
        } else {
            $this->render_error('Szablon widoku firmy nie został znaleziony.');
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
        echo '<a href="' . esc_url(admin_url('admin.php?page=' . $this->page_slug)) . '" class="button">Powrót do listy firm</a>';
        echo '</div>';
    }

    /**
     * Ładowanie stylów specyficznych dla firm
     */
    protected function enqueue_styles()
    {
        parent::enqueue_styles();
        
        wp_enqueue_style(
            'wpmzf-companies',
            $this->get_asset_url('css/admin/companies.css'),
            array('wpmzf-admin-base'),
            '1.0.0'
        );
    }

    /**
     * Ładowanie skryptów specyficznych dla firm
     */
    protected function enqueue_scripts()
    {
        parent::enqueue_scripts();
        
        // Ładuj skrypty tylko dla widoku pojedynczej firmy
        if (isset($_GET['company_id']) && intval($_GET['company_id']) > 0) {
            // Skrypty ACF dla pól relacji
            if (function_exists('acf_enqueue_scripts')) {
                acf_enqueue_scripts();
            }
            
            // Edytor WYSIWYG
            wp_enqueue_editor();
            wp_enqueue_media();
            
            wp_enqueue_script(
                'wpmzf-company-view',
                $this->get_asset_url('js/admin/company-view.js'),
                array('jquery', 'editor'),
                '1.0.0',
                true
            );
            
            // Zmienne dla JavaScript
            wp_localize_script('wpmzf-company-view', 'wpmzfCompanyView', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'adminUrl' => admin_url(),
                'nonce' => wp_create_nonce('wpmzf_company_view_nonce'),
                'companyId' => intval($_GET['company_id'])
            ));
        }
    }
}
