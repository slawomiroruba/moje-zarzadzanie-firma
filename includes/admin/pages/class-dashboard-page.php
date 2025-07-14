<?php

/**
 * Strona dashboard
 */
class WPMZF_Dashboard_Page extends WPMZF_Admin_Page_Base
{
    /**
     * Inicjalizacja strony dashboard
     */
    protected function init()
    {
        $this->page_slug = 'wpmzf_dashboard';
        $this->page_title = 'Dashboard';
        $this->menu_title = 'Dashboard';
        $this->capability = 'manage_options';
    }

    /**
     * Renderowanie strony dashboard
     */
    public function render()
    {
        $view_path = $this->get_view_path('dashboard/dashboard.php');
        
        if (file_exists($view_path)) {
            include $view_path;
        } else {
            // Fallback - prosty dashboard
            $this->render_simple_dashboard();
        }
    }

    /**
     * Prosty dashboard (fallback)
     */
    private function render_simple_dashboard()
    {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html(get_admin_page_title()) . '</h1>';
        
        echo '<div class="dashboard-widgets-wrap">';
        echo '<div class="dashboard-widgets">';
        
        // Widget statystyk
        $this->render_stats_widget();
        
        // Widget ostatnich działań
        $this->render_recent_activities_widget();
        
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Widget statystyk
     */
    private function render_stats_widget()
    {
        echo '<div class="postbox">';
        echo '<h2><span>Statystyki</span></h2>';
        echo '<div class="inside">';
        
        $stats = array(
            'persons' => wp_count_posts('person'),
            'companies' => wp_count_posts('company'),
            'projects' => wp_count_posts('project'),
            'documents' => wp_count_posts('document')
        );
        
        echo '<table class="widefat">';
        echo '<tr><td>Osoby:</td><td>' . ($stats['persons']->publish ?? 0) . '</td></tr>';
        echo '<tr><td>Firmy:</td><td>' . ($stats['companies']->publish ?? 0) . '</td></tr>';
        echo '<tr><td>Projekty:</td><td>' . ($stats['projects']->publish ?? 0) . '</td></tr>';
        echo '<tr><td>Dokumenty:</td><td>' . ($stats['documents']->publish ?? 0) . '</td></tr>';
        echo '</table>';
        
        echo '</div>';
        echo '</div>';
    }

    /**
     * Widget ostatnich działań
     */
    private function render_recent_activities_widget()
    {
        echo '<div class="postbox">';
        echo '<h2><span>Ostatnie działania</span></h2>';
        echo '<div class="inside">';
        
        // Pobierz ostatnie wpisy ze wszystkich typów
        $recent_posts = get_posts(array(
            'post_type' => array('person', 'company', 'project', 'document'),
            'numberposts' => 10,
            'orderby' => 'date',
            'order' => 'DESC'
        ));
        
        if (empty($recent_posts)) {
            echo '<p>Brak ostatnich działań.</p>';
        } else {
            echo '<ul>';
            foreach ($recent_posts as $post) {
                $edit_url = get_edit_post_link($post->ID);
                echo '<li>';
                echo '<strong>' . esc_html($post->post_title) . '</strong> ';
                echo '(' . esc_html($post->post_type) . ') - ';
                echo esc_html($post->post_date);
                if ($edit_url) {
                    echo ' <a href="' . esc_url($edit_url) . '">Edytuj</a>';
                }
                echo '</li>';
            }
            echo '</ul>';
        }
        
        echo '</div>';
        echo '</div>';
    }

    /**
     * Ładowanie stylów specyficznych dla dashboard
     */
    protected function enqueue_styles()
    {
        parent::enqueue_styles();
        
        wp_enqueue_style(
            'wpmzf-dashboard',
            $this->get_asset_url('css/admin/dashboard.css'),
            array('wpmzf-admin-base'),
            '1.0.0'
        );
    }

    /**
     * Ładowanie skryptów specyficznych dla dashboard
     */
    protected function enqueue_scripts()
    {
        parent::enqueue_scripts();
        
        wp_enqueue_script(
            'wpmzf-dashboard',
            $this->get_asset_url('js/admin/dashboard.js'),
            array('jquery'),
            '1.0.0',
            true
        );
        
        // Zmienne dla JavaScript
        wp_localize_script('wpmzf-dashboard', 'wpmzfDashboard', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'adminUrl' => admin_url(),
            'nonce' => wp_create_nonce('wpmzf_dashboard_nonce')
        ));
    }
}
