<?php

/**
 * Klasa zarządzająca panelem administracyjnym
 *
 * @package WPMZF
 * @subpackage Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPMZF_Admin {

    /**
     * Konstruktor klasy
     */
    public function __construct() {
        // Tylko enqueue scripts i styles - menu jest obsługiwane przez WPMZF_Admin_Pages
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_person_view_scripts'));
        add_action('admin_init', array($this, 'admin_init'));
    }

    /**
     * Dodaje menu administracyjne
     */
    public function add_admin_menu() {
        // Ta metoda jest nieużywana - menu jest teraz zarządzane przez WPMZF_Admin_Pages
        // Zostawione dla kompatybilności
    }

    /**
     * Ładuje skrypty i style
     */
    public function enqueue_scripts($hook) {
        if (strpos($hook, 'wpmzf') !== false) {
            wp_enqueue_style(
                'wpmzf-admin',
                plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/css/admin-styles.css',
                array(),
                '1.0.0'
            );

            wp_enqueue_script(
                'wpmzf-dashboard',
                plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/js/admin/dashboard.js',
                array('jquery'),
                '1.0.0',
                true
            );
        }
    }

    /**
     * Ładuje skrypty dla widoku osoby
     */
    public function enqueue_person_view_scripts($hook) {
        if ('admin_page_wpmzf_view_person' === $hook || 'admin_page_luna-crm-person-view' === $hook) {
            // Włączamy skrypty ACF dla pola 'relationship'
            if (function_exists('acf_enqueue_scripts')) {
                acf_enqueue_scripts();
            }
            
            // Dodajemy skrypty edytora WYSIWYG
            wp_enqueue_editor();
            wp_enqueue_media();
            
            // Style i skrypty są już ładowane przez WPMZF_Admin_Pages
            // Usunięto duplikację
        }
    }

    /**
     * Inicjalizacja panelu administracyjnego
     */
    public function admin_init() {
        // Inicjalizacja ustawień, opcji itp.
    }

    /**
     * Strona Dashboard
     */
    public function dashboard_page() {
        include_once WPMZF_PLUGIN_PATH . 'includes/admin/views/dashboard/dashboard.php';
    }

    /**
     * Strona Firmy
     */
    public function companies_page() {
        include_once WPMZF_PLUGIN_PATH . 'includes/admin/views/companies/companies.php';
    }

    /**
     * Strona Osoby
     */
    public function persons_page() {
        // Stwórz instancję i przygotuj dane tabeli
        $persons_table = new WPMZF_persons_List_Table();
        $persons_table->prepare_items();

        // Podstawowe statystyki
        $base_url = admin_url('admin.php?page=luna-crm-persons');
        
        $all_persons_query = new WP_Query([
            'post_type'      => 'person',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [
                'relation' => 'OR',
                [
                    'relation' => 'AND',
                    ['key' => 'person_status', 'value' => ['archived', 'Zarchiwizowany'], 'compare' => 'NOT IN']
                ],
                ['key' => 'person_status', 'compare' => 'NOT EXISTS']
            ]
        ]);
        $all_count = $all_persons_query->found_posts;
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Zarządzanie Osobami</h1>
            
            <div id="wpmzf-stats-panel">
                <div class="stat-box total">
                    <h3>Wszystkie osoby</h3>
                    <div class="stat-count"><?php echo $all_count; ?></div>
                </div>
            </div>

            <form id="persons-filter" method="get">
                <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>" />
                <?php $persons_table->display(); ?>
            </form>
        </div>
        
        <style>

            #wpmzf-stats-panel {
                margin: 20px 0;
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
                gap: 15px;
            }
            .stat-box {
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
                padding: 16px;
                box-shadow: 0 1px 1px rgba(0, 0, 0, .04);
            }
            .stat-box h3 {
                margin: 0 0 10px;
                padding: 0;
                font-size: 14px;
                color: #50575e;
            }
            .stat-box .stat-count {
                font-size: 2.5em;
                font-weight: 400;
                text-align: center;
                color: #1d2327;
                line-height: 1.2;
            }
            .stat-box.total .stat-count {
                font-size: 2em;
                padding-top: 15px;
            }
        </style>
        <?php
    }

    /**
     * Strona Projekty
     */
    public function projects_page() {
        include_once WPMZF_PLUGIN_PATH . 'includes/admin/views/projects/projects.php';
    }

    /**
     * Strona Dokumenty
     */
    public function documents_page() {
        // Przygotowanie i wyświetlenie tabeli
        $documents_table = new WPMZF_Documents_List_Table();
        $documents_table->prepare_items();
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Zarządzanie Dokumentami</h1>
            <div id="wpmzf-stats">
                <p>Statystyki wkrótce...</p>
            </div>
            <form id="documents-filter" method="get">
                <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>" />
                <?php $documents_table->display(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Strona widoku pojedynczej osoby
     */
    public function person_view_page() {
        // Delegujemy do funkcji render_single_person_page z WPMZF_Admin_Pages
        $admin_pages = new WPMZF_Admin_Pages();
        $admin_pages->render_single_person_page();
    }

    /**
     * Renderuje kolumnę "Imię i nazwisko" z akcjami
     */
    protected function column_name($item) {
        $view_link = add_query_arg(
            [
                'page' => 'luna-crm-person-view',
                'person_id' => $item['id'],
            ],
            admin_url('admin.php')
        );

        $archive_nonce = wp_create_nonce('wpmzf_archive_person_' . $item['id']);
        $archive_link = add_query_arg(
            [
                'page' => 'luna-crm-persons',
                'action' => 'archive',
                'person' => $item['id'],
                '_wpnonce' => $archive_nonce,
            ],
            admin_url('admin.php')
        );

        $actions = [
            'view' => sprintf('<a href="%s">Otwórz teczkę</a>', esc_url($view_link)),
            'edit' => sprintf('<a href="%s">Edytuj (WP)</a>', get_edit_post_link($item['id'])),
            'archive' => sprintf('<a href="%s" style="color:#a00;" onclick="return confirm(\'Czy na pewno chcesz zarchiwizować tą osobę?\')">Archiwizuj</a>', esc_url($archive_link)),
        ];

        $title = sprintf(
            '<a class="row-title" href="%s"><strong>%s</strong></a>',
            esc_url($view_link),
            esc_html($item['name'])
        );

        return $title . $this->row_actions($actions);
    }

    /**
     * Renderuje akcje wiersza
     */
    protected function row_actions($actions) {
        if (empty($actions)) {
            return '';
        }
        $out = '<div class="row-actions">';
        $action_links = [];
        foreach ($actions as $action => $link) {
            $action_links[] = sprintf('<span class="%s">%s</span>', esc_attr($action), $link);
        }
        $out .= implode(' | ', $action_links);
        $out .= '</div>';
        return $out;
    }
}
