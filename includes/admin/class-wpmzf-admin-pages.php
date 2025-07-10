<?php

class WPMZF_Admin_Pages
{

    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_plugin_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_person_view_scripts'));
        add_action('admin_init', array($this, 'handle_actions'));
    }
    public function enqueue_person_view_scripts($hook)
    {
        // Hook dla strony dodanej przez add_submenu_page z parent_slug='' to 'admin_page_{page_slug}'.
        if ('admin_page_wpmzf_view_person' === $hook || 'admin_page_luna-crm-person-view' === $hook) {
            // Włączamy skrypty i style ACF, aby pole 'relationship' działało poprawnie.
            if (function_exists('acf_enqueue_scripts')) {
                acf_enqueue_scripts();
            }
            
            // Dodajemy skrypty edytora WYSIWYG
            wp_enqueue_editor();
            wp_enqueue_media();
            
            // Dodajemy nasze style i skrypty
            wp_enqueue_style(
                'wpmzf-person-view',
                plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/css/admin-styles.css',
                array(),
                '1.0.0'
            );
            
            wp_enqueue_script(
                'wpmzf-person-view',
                plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/js/admin/person-view.js',
                array('jquery'),
                '1.0.0',
                true
            );
        }
    }

    /**
     * Dodaje strony pluginu do menu w panelu admina.
     */
    public function add_plugin_admin_menu()
    {
        // Główne menu pluginu
        add_menu_page(
            'WPMZF',
            'WPMZF',
            'manage_options',
            'wpmzf_dashboard',
            array($this, 'render_dashboard_page'),
            'dashicons-businessman',
            30
        );

        // Submenu Dashboard
        add_submenu_page(
            'wpmzf_dashboard',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'wpmzf_dashboard',
            array($this, 'render_dashboard_page')
        );

        // Submenu Osoby
        add_submenu_page(
            'wpmzf_dashboard',
            'Osoby',
            'Osoby',
            'manage_options',
            'wpmzf_persons',
            array($this, 'render_persons_page')
        );

        // Submenu Firmy
        add_submenu_page(
            'wpmzf_dashboard',
            'Firmy',
            'Firmy',
            'manage_options',
            'wpmzf_companies',
            array($this, 'render_companies_page')
        );

        // Submenu Projekty
        add_submenu_page(
            'wpmzf_dashboard',
            'Projekty',
            'Projekty',
            'manage_options',
            'wpmzf_projects',
            array($this, 'render_projects_page')
        );

        // Rejestrujemy "ukrytą" stronę do widoku pojedynczej osoby.
        // `parent_slug` jako '' ukrywa ją z menu.
        add_submenu_page(
            '',                          // Brak rodzica w menu (ukryta)
            'Widok Osoby',               // page_title – tytuł w <title> i nagłówku
            'Widok Osoby',               // menu_title – nazwa w menu (choć tu niewidoczna)
            'manage_options',            // wymagane uprawnienia
            'wpmzf_view_person',         // slug - zmieniony z wpmzf_person_view na wpmzf_view_person
            array($this, 'render_single_person_page') // callback renderujący
        );

        // Dodajemy też starą ścieżkę dla kompatybilności z linkami w kodzie
        add_submenu_page(
            '',                          // Brak rodzica w menu (ukryta)
            'Widok Osoby (Legacy)',      // page_title – tytuł w <title> i nagłówku
            'Widok Osoby (Legacy)',      // menu_title – nazwa w menu (choć tu niewidoczna)
            'manage_options',            // wymagane uprawnienia
            'luna-crm-person-view',      // slug używany w linkach
            array($this, 'render_single_person_page') // callback renderujący
        );
    }

    /**
     * Renderuje zawartość głównego kokpitu.
     */
    public function render_dashboard_page()
    {
        echo '<div class="wrap"><h1>Witaj w kokpicie Twojej firmy!</h1><p>Wybierz jedną z opcji z menu po lewej stronie.</p></div>';
    }

    /**
     * Renderuje kolumnę "Imię i nazwisko" (lub 'name').
     * To jest główna kolumna, więc będzie zawierać link do teczki oraz akcje.
     *
     * @param array $item Dane wiersza.
     * @return string HTML kolumny.
     */
    function column_name($item)
    {
        // Zbuduj URL do strony widoku pojedynczej osoby (teczki)
        $view_link = add_query_arg(
            [
                'page'       => 'wpmzf_person_view',
                'person_id' => $item['id'],
            ],
            admin_url('admin.php')
        );

        // Zbuduj link do archiwizacji z zabezpieczeniem nonce
        $archive_nonce = wp_create_nonce('wpmzf_archive_person_' . $item['id']);
        $archive_link = add_query_arg(
            [
                'page'    => 'wpmzf_persons',
                'action'  => 'archive',
                'person' => $item['id'],
                '_wpnonce' => $archive_nonce,
            ],
            admin_url('admin.php')
        );

        // Zdefiniuj akcje dla wiersza
        $actions = [
            'view'    => sprintf('<a href="%s">Otwórz teczkę</a>', esc_url($view_link)),
            'edit'    => sprintf('<a href="%s">Edytuj (WP)</a>', get_edit_post_link($item['id'])),
            'archive' => sprintf('<a href="%s" style="color:#a00;" onclick="return confirm(\'Czy na pewno chcesz zarchiwizować tą osobę?\')">Archiwizuj</a>', esc_url($archive_link)),
        ];

        // Stwórz główny link dla nazwy osoby
        $title = sprintf(
            '<a class="row-title" href="%s"><strong>%s</strong></a>',
            esc_url($view_link),
            esc_html($item['name'])
        );

        // Zwróć tytuł (link) wraz z akcjami
        return $title . $this->row_actions($actions);
    }

    /**
     * Renderuje akcje wiersza w stylu WordPressa.
     *
     * @param array $actions Tablica akcji (slug => HTML link).
     * @return string HTML z akcjami.
     */
    protected function row_actions($actions)
    {
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

    /**
     * Renderuje zawartość strony do zarządzania dokumentami.
     */
    public function render_documents_page()
    {
        // 1. Przygotowanie i wyświetlenie tabeli
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
                <?php
                // 2. Wyświetlenie tabeli
                $documents_table->display();
                ?>
            </form>
        </div>
    <?php
    }

    // W klasie WPMZF_Admin_Pages

    public function render_persons_page()
    {
        // Stwórz instancję i przygotuj dane tabeli
        $persons_table = new WPMZF_persons_List_Table();
        $persons_table->prepare_items();

        // --- Logika statystyk ---

        $base_url = admin_url('admin.php?page=wpmzf_persons');

        // --- Statystyka "Wszystkie" ---
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

        // --- Statystyka dzienna ---
        $current_day_str = $_GET['stat_day'] ?? current_time('Y-m-d');
        $current_day_dt = new DateTime($current_day_str);
        $prev_day_url = add_query_arg('stat_day', (clone $current_day_dt)->modify('-1 day')->format('Y-m-d'), $base_url);
        $next_day_url = add_query_arg('stat_day', (clone $current_day_dt)->modify('+1 day')->format('Y-m-d'), $base_url);

        $daily_query = new WP_Query([
            'post_type' => 'person',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'date_query' => [['year'  => $current_day_dt->format('Y'), 'month' => $current_day_dt->format('m'), 'day'   => $current_day_dt->format('d')]]
        ]);
        $daily_count = $daily_query->found_posts;

        // --- Statystyka tygodniowa ---
        $current_year_w = $_GET['stat_year_w'] ?? current_time('Y');
        $current_week = $_GET['stat_week'] ?? current_time('W');
        $week_dt = new DateTime();
        $week_dt->setISODate($current_year_w, $current_week);
        $prev_week_dt = (clone $week_dt)->modify('-1 week');
        $next_week_dt = (clone $week_dt)->modify('+1 week');
        $prev_week_url = add_query_arg(['stat_week' => $prev_week_dt->format('W'), 'stat_year_w' => $prev_week_dt->format('Y')], $base_url);
        $next_week_url = add_query_arg(['stat_week' => $next_week_dt->format('W'), 'stat_year_w' => $next_week_dt->format('Y')], $base_url);

        $weekly_query = new WP_Query([
            'post_type' => 'person',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'date_query' => [['year' => $current_year_w, 'week' => $current_week]]
        ]);
        $weekly_count = $weekly_query->found_posts;

        // --- Statystyka miesięczna ---
        $current_year_m = $_GET['stat_year_m'] ?? current_time('Y');
        $current_month = $_GET['stat_month'] ?? current_time('m');
        $month_dt = new DateTime("$current_year_m-$current_month-01");
        $prev_month_dt = (clone $month_dt)->modify('first day of last month');
        $next_month_dt = (clone $month_dt)->modify('first day of next month');
        $prev_month_url = add_query_arg(['stat_month' => $prev_month_dt->format('m'), 'stat_year_m' => $prev_month_dt->format('Y')], $base_url);
        $next_month_url = add_query_arg(['stat_month' => $next_month_dt->format('m'), 'stat_year_m' => $next_month_dt->format('Y')], $base_url);

        $monthly_query = new WP_Query([
            'post_type' => 'person',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'date_query' => [['year' => $current_year_m, 'month' => $current_month]]
        ]);
        $monthly_count = $monthly_query->found_posts;

        // Helper do polskich nazw miesięcy
        $polish_months = ['Stycznia', 'Lutego', 'Marca', 'Kwietnia', 'Maja', 'Czerwca', 'Lipca', 'Sierpnia', 'Września', 'Października', 'Listopada', 'Grudnia'];
        $polish_months_mianownik = ['Styczeń', 'Luty', 'Marzec', 'Kwiecień', 'Maj', 'Czerwiec', 'Lipiec', 'Sierpień', 'Wrzesień', 'Październik', 'Listopad', 'Grudzień'];
    ?>
        <style>
            /* General page styles */
            .wrap #wpmzf-stats-panel+form {
                margin-top: 20px;
            }

            /* Stats Panel Styles */
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

            .stat-box .stat-navigator {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 10px;
            }

            .stat-box .stat-navigator a {
                text-decoration: none;
                font-size: 20px;
                line-height: 1;
                padding: 0 5px;
                color: #0071a1;
            }

            .stat-box .stat-navigator a:hover {
                color: #135e96;
            }

            .stat-box .stat-navigator .period {
                font-weight: 600;
                color: #1d2327;
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

            /* Task styles */
            .task-input-wrapper {
                margin-bottom: 10px;
            }

            .task-input-wrapper input[type="text"] {
                width: 100%;
                padding: 8px;
                border: 1px solid #8c8f94;
                border-radius: 3px;
            }

            .task-due-date-wrapper label {
                display: block;
                margin-bottom: 5px;
                font-weight: 600;
            }

            .task-due-date-wrapper input[type="datetime-local"] {
                width: 100%;
                padding: 8px;
                border: 1px solid #8c8f94;
                border-radius: 3px;
            }

            .task-submit-wrapper {
                margin-top: 15px;
            }

            .task-item {
                background: #f9f9f9;
                border: 1px solid #e0e0e0;
                border-radius: 4px;
                padding: 8px 10px;
                margin-bottom: 6px;
                position: relative;
            }

            .task-item.overdue {
                border-left: 4px solid #dc3232;
                background: #fdf2f2;
            }

            .task-item.today {
                border-left: 4px solid #ffb900;
                background: #fffbf0;
            }

            .task-item.upcoming {
                border-left: 4px solid #2271b1;
                background: #f0f6fc;
            }

            .task-item.completed {
                background: #f0f0f1;
                opacity: 0.7;
            }

            .task-content {
                width: 100%;
            }

            .task-title-row {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                margin-bottom: 4px;
            }

            .task-title {
                font-weight: 600;
                color: #23282d;
                margin: 0;
                font-size: 14px;
                flex: 1;
                line-height: 1.2;
                margin-right: 8px;
            }

            .task-meta-row {
                display: flex;
                justify-content: flex-start;
                align-items: center;
                font-size: 11px;
            }

            .task-meta-left {
                display: flex;
                align-items: center;
                gap: 6px;
                flex-wrap: wrap;
            }

            .task-meta-right {
                display: flex;
                align-items: center;
            }

            .task-meta {
                font-size: 12px;
                color: #646970;
                display: flex;
                flex-direction: column;
                align-items: flex-end;
                gap: 2px;
            }

            .task-status {
                display: inline-block;
                padding: 2px 6px;
                border-radius: 10px;
                font-size: 10px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.3px;
            }

            .task-status.do-zrobienia {
                background: #fff2cc;
                color: #996f00;
            }

            .task-status.w-toku {
                background: #cce5ff;
                color: #0073aa;
            }

            .task-status.zrobione {
                background: #d4edda;
                color: #155724;
            }

            .task-date {
                display: inline-block;
                padding: 2px 6px;
                background: #f0f0f1;
                border-radius: 3px;
                font-size: 11px;
                font-weight: 500;
                color: #646970;
            }

            .task-date.overdue {
                background: #dc3232;
                color: white;
            }

            .task-date.today {
                background: #ffb900;
                color: white;
            }

            .task-date.upcoming {
                background: #2271b1;
                color: white;
            }

            .task-priority-indicator {
                display: inline-block;
                padding: 2px 5px;
                border-radius: 3px;
                font-size: 9px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            .task-priority-indicator.overdue {
                background: #dc3232;
                color: white;
            }

            .task-priority-indicator.today {
                background: #ffb900;
                color: white;
            }

            .task-priority-indicator.upcoming {
                background: #2271b1;
                color: white;
            }

            .task-actions {
                display: flex;
                gap: 2px;
                align-items: center;
                flex-shrink: 0;
            }

            .task-actions .dashicons {
                cursor: pointer;
                color: #787c82;
                font-size: 12px;
                padding: 1px;
                border-radius: 2px;
                transition: all 0.2s ease;
            }

            .task-actions .dashicons:hover {
                color: #2271b1;
                background: rgba(34, 113, 177, 0.1);
            }

            #wpmzf-toggle-closed-tasks {
                display: flex;
                align-items: center;
                gap: 5px;
                cursor: pointer;
                color: #646970;
                transition: color 0.2s;
            }

            #wpmzf-toggle-closed-tasks:hover {
                color: #2271b1;
            }

            #wpmzf-toggle-closed-tasks .dashicons {
                transition: transform 0.2s;
            }

            #wpmzf-toggle-closed-tasks.expanded .dashicons {
                transform: rotate(90deg);
            }

            .task-edit-input {
                width: 100%;
                padding: 5px;
                border: 1px solid #ddd;
                border-radius: 3px;
                font-size: 14px;
                font-weight: 600;
            }

            #task-date-edit-modal {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
                z-index: 10000;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            #task-date-edit-modal > div {
                background: white;
                padding: 20px;
                border-radius: 8px;
                width: 400px;
                max-width: 90%;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            }

            #task-date-edit-modal h3 {
                margin-top: 0;
                color: #23282d;
            }

            #task-date-edit-input {
                width: 100%;
                padding: 8px;
                border: 1px solid #ddd;
                border-radius: 4px;
                margin-bottom: 15px;
                font-size: 14px;
            }

            .task-message {
                margin: 10px 0;
                padding: 8px 12px;
                border-radius: 4px;
                border-left: 4px solid transparent;
            }

            .task-message.notice-success {
                background: #d4edda;
                border-left-color: #155724;
                color: #155724;
            }

            .task-message.notice-error {
                background: #f8d7da;
                border-left-color: #721c24;
                color: #721c24;
            }

            @media screen and (max-width: 1200px) {
                .dossier-grid {
                    grid-template-columns: 1fr;
                }
            }

            /* Projects/Orders styles */
            .projects-section {
                margin-bottom: 16px;
            }
            
            .projects-list {
                list-style: none;
                padding: 0;
                margin: 0;
            }
            
            .project-item {
                background: #f8f9fa;
                border: 1px solid #e1e5e9;
                border-radius: 6px;
                margin-bottom: 8px;
                padding: 12px;
                transition: all 0.2s ease;
            }
            
            .project-item:hover {
                border-color: #2271b1;
                background: #f6f7f7;
            }
            
            .project-item.active-project {
                border-left: 4px solid #2271b1;
            }
            
            .project-item.completed-project {
                border-left: 4px solid #8c8f94;
                opacity: 0.8;
            }
            
            .project-info {
                display: flex;
                flex-direction: column;
                gap: 4px;
            }
            
            .project-link {
                color: #2271b1;
                text-decoration: none;
                font-weight: 600;
                font-size: 14px;
                transition: color 0.2s ease;
            }
            
            .project-link:hover {
                color: #135e96;
                text-decoration: underline;
            }
            
            .project-deadline {
                color: #646970;
                font-size: 12px;
                font-weight: 500;
            }
            
            #toggle-completed-projects {
                display: flex;
                align-items: center;
                gap: 5px;
                cursor: pointer;
                color: #646970;
                transition: color 0.2s;
                border: none;
                background: none;
                padding: 0;
                font-size: 13px;
                font-weight: 600;
            }
            
            #toggle-completed-projects:hover {
                color: #2271b1;
            }
            
            #toggle-completed-projects .dashicons {
                transition: transform 0.2s;
                font-size: 16px;
            }
            
            #toggle-completed-projects.expanded .dashicons {
                transform: rotate(90deg);
            }
            
            #add-new-project-btn {
                background: #2271b1;
                color: #fff;
                border: none;
                padding: 6px 12px;
                border-radius: 4px;
                text-decoration: none;
                font-size: 12px;
                font-weight: 500;
                transition: all 0.2s ease;
            }
            
            #add-new-project-btn:hover {
                background: #135e96;
                color: #fff;
                transform: translateY(-1px);
            }
            /* End of Projects/Orders styles */
            /* Task styles */
            .task-input-wrapper {
                display: flex;
                gap: 8px;
                align-items: center;
            }

            .task-input-wrapper input[type="text"] {
                flex: 1;
                padding: 8px;
                border: 1px solid #8c8f94;
                border-radius: 3px;
            }

            .task-item {
                background: #f9f9f9;
                border: 1px solid #e0e0e0;
                border-radius: 4px;
                padding: 8px 10px;
                margin-bottom: 6px;
                position: relative;
            }

            .task-item.overdue {
                border-left: 4px solid #dc3232;
                background: #fdf2f2;
            }

            .task-item.today {
                border-left: 4px solid #ffb900;
                background: #fffbf0;
            }

            .task-item.upcoming {
                border-left: 4px solid #2271b1;
                background: #f0f6fc;
            }

            .task-item.completed {
                background: #f0f0f1;
                opacity: 0.7;
            }

            .task-content {
                width: 100%;
            }

            .task-title-row {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                margin-bottom: 4px;
            }

            .task-title {
                font-weight: 600;
                color: #23282d;
                margin: 0;
                font-size: 14px;
                flex: 1;
                line-height: 1.2;
                margin-right: 8px;
            }

            .task-meta-row {
                display: flex;
                justify-content: flex-start;
                align-items: center;
                font-size: 11px;
            }

            .task-meta-left {
                display: flex;
                align-items: center;
                gap: 6px;
                flex-wrap: wrap;
            }

            .task-meta-right {
                display: flex;
                align-items: center;
            }

            .task-meta {
                font-size: 12px;
                color: #646970;
                display: flex;
                flex-direction: column;
                align-items: flex-end;
                gap: 2px;
            }

            .task-status {
                display: inline-block;
                padding: 2px 6px;
                border-radius: 10px;
                font-size: 10px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.3px;
            }

            .task-status.do-zrobienia {
                background: #fff2cc;
                color: #996f00;
            }

            .task-status.w-toku {
                background: #cce5ff;
                color: #0073aa;
            }

            .task-status.zrobione {
                background: #d4edda;
                color: #155724;
            }

            .task-date {
                display: inline-block;
                padding: 2px 6px;
                background: #f0f0f1;
                border-radius: 3px;
                font-size: 11px;
                font-weight: 500;
                color: #646970;
            }

            .task-date.overdue {
                background: #dc3232;
                color: white;
            }

            .task-date.today {
                background: #ffb900;
                color: white;
            }

            .task-date.upcoming {
                background: #2271b1;
                color: white;
            }

            .task-priority-indicator {
                display: inline-block;
                padding: 2px 5px;
                border-radius: 3px;
                font-size: 9px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            .task-priority-indicator.overdue {
                background: #dc3232;
                color: white;
            }

            .task-priority-indicator.today {
                background: #ffb900;
                color: white;
            }

            .task-priority-indicator.upcoming {
                background: #2271b1;
                color: white;
            }

            .task-actions {
                display: flex;
                gap: 2px;
                align-items: center;
                flex-shrink: 0;
            }

            .task-actions .dashicons {
                cursor: pointer;
                color: #787c82;
                font-size: 12px;
                padding: 1px;
                border-radius: 2px;
                transition: all 0.2s ease;
            }

            .task-actions .dashicons:hover {
                color: #2271b1;
                background: rgba(34, 113, 177, 0.1);
            }

            #wpmzf-toggle-closed-tasks {
                display: flex;
                align-items: center;
                gap: 5px;
                cursor: pointer;
                color: #646970;
                transition: color 0.2s;
            }

            #wpmzf-toggle-closed-tasks:hover {
                color: #2271b1;
            }

            #wpmzf-toggle-closed-tasks .dashicons {
                transition: transform 0.2s;
            }

            #wpmzf-toggle-closed-tasks.expanded .dashicons {
                transform: rotate(90deg);
            }

            .task-edit-input {
                width: 100%;
                padding: 5px;
                border: 1px solid #ddd;
                border-radius: 3px;
                font-size: 14px;
                font-weight: 600;
            }

            #task-date-edit-modal {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
                z-index: 10000;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            #task-date-edit-modal > div {
                background: white;
                padding: 20px;
                border-radius: 8px;
                width: 400px;
                max-width: 90%;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            }

            #task-date-edit-modal h3 {
                margin-top: 0;
                color: #23282d;
            }

            #task-date-edit-input {
                width: 100%;
                padding: 8px;
                border: 1px solid #ddd;
                border-radius: 4px;
                margin-bottom: 15px;
                font-size: 14px;
            }

            .task-message {
                margin: 10px 0;
                padding: 8px 12px;
                border-radius: 4px;
                border-left: 4px solid transparent;
            }

            .task-message.notice-success {
                background: #d4edda;
                border-left-color: #155724;
                color: #155724;
            }

            .task-message.notice-error {
                background: #f8d7da;
                border-left-color: #721c24;
                color: #721c24;
            }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Obsługa rozwijania/zwijania zakończonych projektów
            $('#toggle-completed-projects').on('click', function() {
                const $this = $(this);
                const $list = $('#completed-projects-list');
                
                if ($list.is(':visible')) {
                    $list.slideUp(200);
                    $this.removeClass('expanded');
                } else {
                    $list.slideDown(200);
                    $this.addClass('expanded');
                }
            });
            
            // Obsługa przycisku "Nowe zlecenie"
            $('#add-new-project-btn').on('click', function(e) {
                e.preventDefault();
                
                // Przekierowanie do strony dodawania nowego projektu z przypisaniem do osoby
                const personId = <?php echo json_encode($person_id); ?>;
                const newProjectUrl = '<?php echo admin_url('post-new.php?post_type=project'); ?>' + '&person_id=' + personId;
                
                window.location.href = newProjectUrl;
            });
            
            // Obsługa linków do projektów (przyszły widok szczegółowy)
            $('.project-link').on('click', function(e) {
                e.preventDefault();
                
                const projectId = $(this).data('project-id');
                // TODO: Implementacja widoku szczegółowego projektu
                alert('Widok szczegółowy projektu #' + projectId + ' zostanie wkrótce zaimplementowany.');
            });
        });
        </script>

        <div class="wrap">
            <h1 class="wp-heading-inline">Osoby</h1>
            <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=person' ) ); ?>"
            class="page-title-action">
                Dodaj nową osobę
            </a>
            <div id="wpmzf-stats-panel">
                <div class="stat-box total">
                    <h3>Wszystkie aktywne</h3>
                    <div class="stat-count"><?php echo esc_html($all_count); ?></div>
                </div>
                <div class="stat-box">
                    <h3>Dziennie</h3>
                    <div class="stat-navigator">
                        <a href="<?php echo esc_url($prev_day_url); ?>">&larr;</a>
                        <span class="period"><?php echo esc_html($current_day_dt->format('j') . ' ' . $polish_months[$current_day_dt->format('n') - 1] . ' ' . $current_day_dt->format('Y')); ?></span>
                        <a href="<?php echo esc_url($next_day_url); ?>">&rarr;</a>
                    </div>
                    <div class="stat-count"><?php echo esc_html($daily_count); ?></div>
                </div>
                <div class="stat-box">
                    <h3>Tygodniowo</h3>
                    <div class="stat-navigator">
                        <a href="<?php echo esc_url($prev_week_url); ?>">&larr;</a>
                        <span class="period">Tydzień <?php echo esc_html($current_week); ?></span>
                        <a href="<?php echo esc_url($next_week_url); ?>">&rarr;</a>
                    </div>
                    <div class="stat-count"><?php echo esc_html($weekly_count); ?></div>
                </div>
                <div class="stat-box">
                    <h3>Miesięcznie</h3>
                    <div class="stat-navigator">
                        <a href="<?php echo esc_url($prev_month_url); ?>">&larr;</a>
                        <span class="period"><?php echo esc_html($polish_months_mianownik[$month_dt->format('n') - 1] . ' ' . $month_dt->format('Y')); ?></span>
                        <a href="<?php echo esc_url($next_month_url); ?>">&rarr;</a>
                    </div>
                    <div class="stat-count"><?php echo esc_html($monthly_count); ?></div>
                </div>
            </div>

            <form method="post">
                <?php
                // Wyświetl tabelę
                $persons_table->display();
                ?>
            </form>
        </div>
    <?php
    }
    public function render_single_person_page()
    {
        global $title;
        
        $person_id = isset($_GET['person_id']) ? intval($_GET['person_id']) : 0;
        if (!$person_id || get_post_type($person_id) !== 'person') {
            wp_die('Nieprawidłowa osoba.');
        }

        $person_title = get_the_title($person_id);
        $title = 'Widok Osoby: ' . $person_title; // Ustawiamy globalny tytuł strony
        $person_fields = get_fields($person_id);

        // Inicjalizujemy zmienne firmy, aby uniknąć błędów
        $company_id = null;
        $company_title = '';

        // error_log('person fields: ' . print_r($person_fields, true)); // Debug - usunięte

        if (!empty($person_fields['person_company']) && is_array($person_fields['person_company'])) {
            // Zakładając, że person_company przechowuje tablicę ID postów firm
            foreach ($person_fields['person_company'] as $company_id_item) {
            $company_post = get_post($company_id_item);
            if ($company_post) {
                $company_id = $company_post->ID;
                $company_title = $company_post->post_title;
                // Możesz dodać logikę do obsługi wielu firm, np. przechowywanie ich w tablicy
                break; // Jeśli chcesz obsłużyć tylko pierwszą firmę
            }
            }
        }
    ?>
        <style>
            /* Single person View Styles */
            
            /* Header styles */
            .person-header {
                background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);
                border: 1px solid #e1e5e9;
                border-radius: 12px;
                padding: 24px 28px;
                margin: 20px 0 28px;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
                display: flex;
                justify-content: space-between;
                align-items: center;
                position: relative;
                overflow: hidden;
            }

            .person-header::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                height: 4px;
                background: linear-gradient(90deg, #2271b1 0%, #1e90ff 50%, #00bcd4 100%);
            }

            .person-header-left {
                display: flex;
                flex-direction: column;
                gap: 8px;
            }

            .person-header h1 {
                margin: 0;
                font-size: 28px;
                font-weight: 700;
                color: #1d2327;
                line-height: 1.2;
                padding: 0 !important;
                text-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
            }

            .person-status-badge {
                display: inline-flex;
                align-items: center;
                padding: 4px 12px;
                border-radius: 16px;
                font-size: 12px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                width: fit-content;
            }

            .person-status-badge.status-active {
                background: #d4edda;
                color: #155724;
                border: 1px solid #c3e6cb;
            }

            .person-status-badge.status-inactive {
                background: #fff3cd;
                color: #856404;
                border: 1px solid #ffeaa7;
            }

            .person-status-badge.status-archived {
                background: #f8d7da;
                color: #721c24;
                border: 1px solid #f5c6cb;
            }

            .person-header-actions {
                display: flex;
                gap: 12px;
                align-items: center;
            }

            .person-header-actions .button {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                text-decoration: none;
                font-size: 14px;
                font-weight: 500;
                padding: 10px 18px;
                border-radius: 6px;
                transition: all 0.3s ease;
                border: 1px solid transparent;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
            }

            .person-header-actions .button:not(.button-secondary) {
                background: #f8f9fa;
                color: #50575e;
                border-color: #e1e5e9;
            }

            .person-header-actions .button:not(.button-secondary):hover {
                background: #fff;
                border-color: #2271b1;
                color: #2271b1;
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(34, 113, 177, 0.15);
            }

            .person-header-actions .button-secondary {
                background: #2271b1;
                color: #fff;
                border-color: #2271b1;
            }

            .person-header-actions .button-secondary:hover {
                background: #1e5a8a;
                border-color: #1e5a8a;
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(34, 113, 177, 0.25);
            }

            .person-header-actions .archive-person-btn {
                background: #dc3545;
                border-color: #dc3545;
            }

            .person-header-actions .archive-person-btn:hover {
                background: #c82333;
                border-color: #c82333;
                box-shadow: 0 4px 12px rgba(220, 53, 69, 0.25);
            }

            .person-header-actions .unarchive-person-btn {
                background: #28a745;
                border-color: #28a745;
            }

            .person-header-actions .unarchive-person-btn:hover {
                background: #218838;
                border-color: #218838;
                box-shadow: 0 4px 12px rgba(40, 167, 69, 0.25);
            }

            .person-header-actions .button .dashicons {
                line-height: 1;
                font-size: 16px;
            }

            .dossier-grid {
                display: grid;
                grid-template-columns: 320px 1fr 360px;
                gap: 24px;
                margin-top: 0;
            }

            .dossier-left-column,
            .dossier-center-column,
            .dossier-right-column {
                display: flex;
                flex-direction: column;
                gap: 24px;
            }

            .dossier-box {
                background: #fff;
                border: 1px solid #e1e5e9;
                border-radius: 8px;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
                transition: all 0.2s ease;
            }

            .dossier-box:hover {
                border-color: #d0d5dd;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            }

            .dossier-box h2.dossier-title {
                font-size: 14px;
                font-weight: 600;
                padding: 16px 20px;
                margin: 0;
                border-bottom: 1px solid #e1e5e9;
                background: #f8f9fa;
                border-radius: 8px 8px 0 0;
                color: #1d2327;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .edit-data-button {
                font-size: 12px;
                font-weight: 500;
                color: #646970;
                text-decoration: none;
                padding: 6px 12px;
                border: 1px solid #e1e5e9;
                border-radius: 4px;
                background: #fff;
                transition: all 0.2s ease;
            }

            .edit-data-button:hover {
                color: #2271b1;
                border-color: #2271b1;
                background: #f6f7f7;
            }

            .dossier-box .dossier-content {
                padding: 20px;
            }

            .dossier-box .dossier-content p {
                margin: 0 0 12px;
                line-height: 1.6;
                color: #3c434a;
            }

            .dossier-box .dossier-content p:last-child {
                margin-bottom: 0;
            }

            .dossier-box .dossier-content p strong {
                color: #1d2327;
                font-weight: 500;
            }
            
            /* Contact styles */
            .contacts-list {
                margin-top: 8px;
            }
            
            .contact-item {
                display: flex;
                align-items: center;
                gap: 8px;
                margin-bottom: 6px;
                padding: 6px 0;
                border-bottom: 1px solid #f0f0f1;
            }
            
            .contact-item:last-child {
                border-bottom: none;
                margin-bottom: 0;
            }
            
            .contact-item.is-primary {
                background: #f6f7f7;
                padding: 8px 12px;
                border-radius: 4px;
                border: 1px solid #c3c4c7;
                margin-bottom: 8px;
            }
            
            .contact-link {
                color: #2271b1;
                text-decoration: none;
                font-weight: 500;
            }
            
            .contact-link:hover {
                color: #135e96;
                text-decoration: underline;
            }
            
            .primary-badge {
                background: #2271b1;
                color: #fff;
                font-size: 11px;
                font-weight: 500;
                padding: 2px 6px;
                border-radius: 10px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            
            .no-contacts {
                color: #8c8f94;
                font-style: italic;
                font-size: 14px;
            }
            
            .email-item .contact-link:before {
                content: "✉";
                margin-right: 6px;
                color: #8c8f94;
            }
            
            .phone-item .contact-link:before {
                content: "☎";
                margin-right: 6px;
                color: #8c8f94;
            }
            /* End of Contact styles */
            
            /* Projects/Orders styles */
            .projects-section {
                margin-bottom: 16px;
            }
            
            .projects-list {
                list-style: none;
                padding: 0;
                margin: 0;
            }
            
            .project-item {
                background: #f8f9fa;
                border: 1px solid #e1e5e9;
                border-radius: 6px;
                margin-bottom: 8px;
                padding: 12px;
                transition: all 0.2s ease;
            }
            
            .project-item:hover {
                border-color: #2271b1;
                background: #f6f7f7;
            }
            
            .project-item.active-project {
                border-left: 4px solid #2271b1;
            }
            
            .project-item.completed-project {
                border-left: 4px solid #8c8f94;
                opacity: 0.8;
            }
            
            .project-info {
                display: flex;
                flex-direction: column;
                gap: 4px;
            }
            
            .project-link {
                color: #2271b1;
                text-decoration: none;
                font-weight: 600;
                font-size: 14px;
                transition: color 0.2s ease;
            }
            
            .project-link:hover {
                color: #135e96;
                text-decoration: underline;
            }
            
            .project-deadline {
                color: #646970;
                font-size: 12px;
                font-weight: 500;
            }
            
            #toggle-completed-projects {
                display: flex;
                align-items: center;
                gap: 5px;
                cursor: pointer;
                color: #646970;
                transition: color 0.2s;
                border: none;
                background: none;
                padding: 0;
                font-size: 13px;
                font-weight: 600;
            }
            
            #toggle-completed-projects:hover {
                color: #2271b1;
            }
            
            #toggle-completed-projects .dashicons {
                transition: transform 0.2s;
                font-size: 16px;
            }
            
            #toggle-completed-projects.expanded .dashicons {
                transform: rotate(90deg);
            }
            
            #add-new-project-btn {
                background: #2271b1;
                color: #fff;
                border: none;
                padding: 6px 12px;
                border-radius: 4px;
                text-decoration: none;
                font-size: 12px;
                font-weight: 500;
                transition: all 0.2s ease;
            }
            
            #add-new-project-btn:hover {
                background: #135e96;
                color: #fff;
                transform: translateY(-1px);
            }
            /* End of Projects/Orders styles */

            .dossier-content .timeline-attachments {
                margin-top: 10px;
                border-top: 1px dashed #e0e0e0;
                padding-top: 10px;
            }

            .dossier-content .timeline-attachments strong {
                display: block;
                margin-bottom: 5px;
                font-size: 13px;
            }

            .dossier-content .timeline-attachments ul {
                margin: 0;
                padding: 0;
                list-style: none;
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
            }

            .dossier-content .timeline-attachments ul li a {
                display: inline-flex;
                align-items: center;
                gap: 5px;
                text-decoration: none;
                font-size: 13px;
                padding: 4px 4px;
                background: #f0f0f1;
                border-radius: 3px;
                border: 1px solid #dcdcde;
            }

            .dossier-content .timeline-attachments ul li a .dashicons {
                font-size: 16px;
            }

            #wpmzf-attachments-preview {
                margin-top: 10px;
                padding-top: 10px;
                border-top: 1px solid #dcdcde;
                display: none; /* Ukryty domyślnie - pokazywany przez JS gdy są załączniki */
            }

            #wpmzf-attachments-preview .attachment-item {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 5px;
                background: #f9f9f9;
                border-radius: 3px;
                margin-bottom: 5px;
            }

            #wpmzf-attachments-preview .remove-attachment {
                cursor: pointer;
                color: #a00;
            }

            #activity-timeline {
                list-style: none;
                padding: 0;
                margin: 15px 0 0;
                position: relative;
            }

            #activity-timeline:before {
                content: '';
                position: absolute;
                top: 5px;
                left: 5px;
                bottom: 5px;
                width: 2px;
                background: #e0e0e0;
            }

            #activity-timeline li {
                padding-left: 25px;
                position: relative;
                margin-bottom: 15px;
            }

            #activity-timeline li:before {
                content: '';
                position: absolute;
                left: 0;
                top: 5px;
                width: 12px;
                height: 12px;
                border-radius: 50%;
                background: #fff;
                border: 2px solid #0071a1;
            }

            #activity-timeline li strong {
                display: block;
                color: #50575e;
            }

            /* Task styles */
            .task-input-wrapper {
                display: flex;
                gap: 8px;
                align-items: center;
            }

            .task-input-wrapper input[type="text"] {
                flex: 1;
                padding: 8px;
                border: 1px solid #8c8f94;
                border-radius: 3px;
            }

            .task-due-date-wrapper label {
                display: block;
                margin-bottom: 5px;
                font-weight: 600;
            }

            .task-due-date-wrapper input[type="datetime-local"] {
                width: 100%;
                padding: 8px;
                border: 1px solid #ddd;
                border-radius: 4px;
            }

            .task-submit-wrapper {
                margin-top: 15px;
            }

            .task-item {
                background: #f9f9f9;
                border: 1px solid #e0e0e0;
                border-radius: 4px;
                padding: 8px 10px;
                margin-bottom: 6px;
                position: relative;
            }

            .task-item.overdue {
                border-left: 4px solid #dc3232;
                background: #fdf2f2;
            }

            .task-item.today {
                border-left: 4px solid #ffb900;
                background: #fffbf0;
            }

            .task-item.upcoming {
                border-left: 4px solid #2271b1;
                background: #f0f6fc;
            }

            .task-item.completed {
                background: #f0f0f1;
                opacity: 0.7;
            }

            .task-content {
                width: 100%;
            }

            .task-title-row {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                margin-bottom: 4px;
            }

            .task-title {
                font-weight: 600;
                color: #23282d;
                margin: 0;
                font-size: 14px;
                flex: 1;
                line-height: 1.2;
                margin-right: 8px;
            }

            .task-meta-row {
                display: flex;
                justify-content: flex-start;
                align-items: center;
                font-size: 11px;
            }

            .task-meta-left {
                display: flex;
                align-items: center;
                gap: 6px;
                flex-wrap: wrap;
            }

            .task-meta-right {
                display: flex;
                align-items: center;
            }

            .task-meta {
                font-size: 12px;
                color: #646970;
                display: flex;
                flex-direction: column;
                align-items: flex-end;
                gap: 2px;
            }

            .task-status {
                display: inline-block;
                padding: 2px 6px;
                border-radius: 10px;
                font-size: 10px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.3px;
            }

            .task-status.do-zrobienia {
                background: #fff2cc;
                color: #996f00;
            }

            .task-status.w-toku {
                background: #cce5ff;
                color: #0073aa;
            }

            .task-status.zrobione {
                background: #d4edda;
                color: #155724;
            }

            .task-date {
                display: inline-block;
                padding: 2px 6px;
                background: #f0f0f1;
                border-radius: 3px;
                font-size: 11px;
                font-weight: 500;
                color: #646970;
            }

            .task-date.overdue {
                background: #dc3232;
                color: white;
            }

            .task-date.today {
                background: #ffb900;
                color: white;
            }

            .task-date.upcoming {
                background: #2271b1;
                color: white;
            }

            .task-priority-indicator {
                display: inline-block;
                padding: 2px 5px;
                border-radius: 3px;
                font-size: 9px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            .task-priority-indicator.overdue {
                background: #dc3232;
                color: white;
            }

            .task-priority-indicator.today {
                background: #ffb900;
                color: white;
            }

            .task-priority-indicator.upcoming {
                background: #2271b1;
                color: white;
            }

            .task-actions {
                display: flex;
                gap: 2px;
                align-items: center;
                flex-shrink: 0;
            }

            .task-actions .dashicons {
                cursor: pointer;
                color: #787c82;
                font-size: 12px;
                padding: 1px;
                border-radius: 2px;
                transition: all 0.2s ease;
            }

            .task-actions .dashicons:hover {
                color: #2271b1;
                background: rgba(34, 113, 177, 0.1);
            }

            #wpmzf-toggle-closed-tasks {
                display: flex;
                align-items: center;
                gap: 5px;
                cursor: pointer;
                color: #646970;
                transition: color 0.2s;
            }

            #wpmzf-toggle-closed-tasks:hover {
                color: #2271b1;
            }

            #wpmzf-toggle-closed-tasks .dashicons {
                transition: transform 0.2s;
            }

            #wpmzf-toggle-closed-tasks.expanded .dashicons {
                transform: rotate(90deg);
            }

            .task-edit-input {
                width: 100%;
                padding: 5px;
                border: 1px solid #ddd;
                border-radius: 3px;
                font-size: 14px;
                font-weight: 600;
            }

            #task-date-edit-modal {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
                z-index: 10000;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            #task-date-edit-modal > div {
                background: white;
                padding: 20px;
                border-radius: 8px;
                width: 400px;
                max-width: 90%;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            }

            #task-date-edit-modal h3 {
                margin-top: 0;
                color: #23282d;
            }

            #task-date-edit-input {
                width: 100%;
                padding: 8px;
                border: 1px solid #ddd;
                border-radius: 4px;
                margin-bottom: 15px;
                font-size: 14px;
            }

            .task-message {
                margin: 10px 0;
                padding: 8px 12px;
                border-radius: 4px;
                border-left: 4px solid transparent;
            }

            .task-message.notice-success {
                background: #d4edda;
                border-left-color: #155724;
                color: #155724;
            }

            .task-message.notice-error {
                background: #f8d7da;
                border-left-color: #721c24;
                color: #721c24;
            }

            @media screen and (max-width: 1200px) {
                .dossier-grid {
                    grid-template-columns: 1fr;
                }
                
                .dossier-left-column,
                .dossier-center-column,
                .dossier-right-column {
                    gap: 16px;
                }
                
                .person-header {
                    margin: 16px 0 20px;
                    padding: 20px 24px;
                    flex-direction: column;
                    gap: 16px;
                    align-items: flex-start;
                }

                .person-header-left {
                    width: 100%;
                }
                
                .person-header h1 {
                    font-size: 24px;
                }

                .person-header-actions {
                    width: 100%;
                    justify-content: flex-end;
                }
            }

            @media screen and (max-width: 768px) {
                .dossier-box .dossier-content {
                    padding: 16px;
                }
                
                .dossier-box h2.dossier-title {
                    padding: 12px 16px;
                    font-size: 13px;
                }
                
                .edit-data-button {
                    font-size: 11px;
                    padding: 4px 8px;
                }
                
                .person-header {
                    flex-direction: column;
                    align-items: flex-start;
                    gap: 16px;
                    padding: 16px;
                }

                .person-header h1 {
                    font-size: 20px;
                }

                .person-header-actions {
                    width: 100%;
                    justify-content: stretch;
                    flex-wrap: wrap;
                }

                .person-header-actions .button {
                    flex: 1;
                    justify-content: center;
                    min-width: 120px;
                }
                
                .person-header h1 {
                    font-size: 20px;
                }
                
                .person-header-actions {
                    width: 100%;
                    justify-content: flex-end;
                }
                
                .person-header-actions .button {
                    padding: 6px 12px;
                    display: flex;
                    align-items: center;
                    gap: 4px;
                    font-size: 12px;
                }
            }
        </style>

        <div class="wrap">
            <div class="person-header">
                <div class="person-header-left">
                    <h1><?php echo esc_html($person_title); ?></h1>
                    <?php 
                    // Pobieramy status osoby - może być po polsku lub angielsku
                    $current_status = $person_fields['person_status'] ?? 'active';
                    
                    // Mapowanie statusów - obsługujemy polskie i angielskie wartości
                    $status_labels = [
                        'active' => 'Aktywny',
                        'inactive' => 'Nieaktywny', 
                        'archived' => 'Zarchiwizowany',
                        'Aktywny' => 'Aktywny',
                        'Nieaktywny' => 'Nieaktywny',
                        'Zarchiwizowany' => 'Zarchiwizowany'
                    ];
                    
                    // Ustalamy CSS class na podstawie statusu
                    $css_class = 'status-active'; // domyślna
                    if (in_array($current_status, ['inactive', 'Nieaktywny'])) {
                        $css_class = 'status-inactive';
                    } elseif (in_array($current_status, ['archived', 'Zarchiwizowany'])) {
                        $css_class = 'status-archived';
                    }
                    
                    // Pobieramy etykietę do wyświetlenia
                    $display_status = $status_labels[$current_status] ?? $current_status;
                    ?>
                    <div class="person-status-badge <?php echo esc_attr($css_class); ?>">
                        <?php echo esc_html($display_status ?: 'Aktywny'); ?>
                    </div>
                </div>
                <div class="person-header-actions">
                    <?php 
                    // Sprawdzamy czy osoba jest zarchiwizowana
                    $is_archived = in_array($current_status, ['archived', 'Zarchiwizowany']);
                    ?>
                    <?php if (!$is_archived): ?>
                        <button type="button" class="button button-secondary archive-person-btn" data-person-id="<?php echo esc_attr($person_id); ?>">
                            <span class="dashicons dashicons-archive"></span>
                            Archiwizuj
                        </button>
                    <?php else: ?>
                        <button type="button" class="button button-secondary unarchive-person-btn" data-person-id="<?php echo esc_attr($person_id); ?>">
                            <span class="dashicons dashicons-undo"></span>
                            Przywróć z archiwum
                        </button>
                    <?php endif; ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=wpmzf_persons')); ?>" class="button">
                        <span class="dashicons dashicons-arrow-left-alt"></span>
                        Powrót do listy
                    </a>
                </div>
            </div>

            <div class="dossier-grid">
                <!-- Lewa kolumna - Dane podstawowe -->
                <div class="dossier-left-column">
                    <div class="dossier-box" id="dossier-basic-data">
                        <h2 class="dossier-title">
                            Dane podstawowe
                            <a href="#" id="edit-basic-data" class="edit-data-button">Edytuj</a>
                        </h2>
                        <div class="dossier-content">
                            <div class="view-mode">
                                <p><strong>Imię i nazwisko:</strong> <span data-field="person_name"><?php echo esc_html($person_title); ?></span></p>
                                <p><strong>Stanowisko:</strong> <span data-field="person_position"><?php echo esc_html((string)($person_fields['person_position'] ?? 'Brak')); ?></span></p>
                                <p><strong>Firma:</strong> <span data-field="person_company">
                                        <?php
                                        if ($company_id) {
                                            printf('<a href="%s">%s</a>', esc_url(get_edit_post_link($company_id)), esc_html(get_the_title($company_id)));
                                        } else {
                                            echo 'Brak';
                                        }
                                        ?>
                                    </span></p>
                                <p><strong>E-maile:</strong> 
                                    <div data-field="person_emails">
                                        <?php echo WPMZF_Contact_Helper::render_emails_display(WPMZF_Contact_Helper::get_person_emails($person_id)); ?>
                                    </div>
                                </p>
                                <p><strong>Telefony:</strong> 
                                    <div data-field="person_phones">
                                        <?php echo WPMZF_Contact_Helper::render_phones_display(WPMZF_Contact_Helper::get_person_phones($person_id)); ?>
                                    </div>
                                </p>
                                <p><strong>Adres:</strong> <span data-field="person_address"><?php
                                $address_group = is_array($person_fields['person_address'] ?? null) ? $person_fields['person_address'] : [];
                                $address_parts = [
                                    (string)($address_group['street'] ?? ''),
                                    (string)($address_group['zip_code'] ?? ''),
                                    (string)($address_group['city'] ?? '')
                                ];
                                $address = implode(', ', array_filter($address_parts));
                                echo esc_html($address ?: 'Brak');
                                ?></span></p>
                                <p><strong>Status:</strong> <span data-field="person_status">
                                    <?php
                                    $status_labels = [
                                        'active' => 'Aktywny',
                                        'inactive' => 'Nieaktywny',
                                        'archived' => 'Zarchiwizowany',
                                        'Aktywny' => 'Aktywny',
                                        'Nieaktywny' => 'Nieaktywny',
                                        'Zarchiwizowany' => 'Zarchiwizowany'
                                    ];
                                    $current_status = $person_fields['person_status'] ?? 'active';
                                    $display_status = isset($status_labels[$current_status]) ? $status_labels[$current_status] : $current_status;
                                    echo esc_html($display_status ?: 'Aktywny');
                                    ?>
                                </span></p>
                            </div>
                            <div class="edit-form">
                                <form>
                                    <label for="person_name">Imię i nazwisko:</label>
                                    <input type="text" id="person_name" name="person_name" value="<?php echo esc_attr($person_title); ?>" required>

                                    <label for="person_position">Stanowisko:</label>
                                    <input type="text" id="person_position" name="person_position" value="<?php echo esc_attr((string)($person_fields['person_position'] ?? '')); ?>">

                                    <label for="company_search_select">Firma:</label>
                                    <select id="company_search_select" name="person_company" style="width: 100%;">
                                        <?php if ($company_id && $company_title) : ?>
                                            <option value="<?php echo esc_attr($company_id); ?>" selected="selected"><?php echo esc_html($company_title); ?></option>
                                        <?php endif; ?>
                                    </select>

                                    <label for="person_street">Ulica i nr:</label>
                                    <input type="text" id="person_street" name="person_street" value="<?php echo esc_attr((string)($person_fields['person_street'] ?? '')); ?>">

                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="person_postal_code">Kod pocztowy:</label>
                                            <input type="text" id="person_postal_code" name="person_postal_code" value="<?php echo esc_attr((string)($person_fields['person_postal_code'] ?? '')); ?>">
                                        </div>
                                        <div class="form-group">
                                            <label for="person_city">Miasto:</label>
                                            <input type="text" id="person_city" name="person_city" value="<?php echo esc_attr((string)($person_fields['person_city'] ?? '')); ?>">
                                        </div>
                                    </div>

                                    <label for="person_status">Status:</label>
                                    <select id="person_status" name="person_status">
                                        <option value="active" <?php selected((string)($person_fields['person_status'] ?? ''), 'active'); ?>>Aktywny</option>
                                        <option value="inactive" <?php selected((string)($person_fields['person_status'] ?? ''), 'inactive'); ?>>Nieaktywny</option>
                                        <option value="archived" <?php selected((string)($person_fields['person_status'] ?? ''), 'archived'); ?>>Zarchiwizowany</option>
                                    </select>

                                    <div class="edit-actions">
                                        <button type="submit" class="button button-primary">Zapisz zmiany</button>
                                        <button type="button" id="cancel-edit-basic-data" class="button">Anuluj</button>
                                        <span class="spinner"></span>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sekcja Zleceń -->
                    <div class="dossier-box">
                        <h2 class="dossier-title">
                            Zlecenia
                            <a href="#" class="edit-data-button" id="add-new-project-btn">+ Nowe zlecenie</a>
                        </h2>
                        <div class="dossier-content">
                            <?php
                            // Pobieramy aktywne projekty
                            $active_projects = WPMZF_Project::get_active_projects_by_person($person_id);
                            
                            // Pobieramy zamknięte projekty
                            $completed_projects = WPMZF_Project::get_completed_projects_by_person($person_id);
                            
                            if (!empty($active_projects)) {
                                echo '<div class="projects-section">';
                                echo '<h4 style="margin: 0 0 10px 0; color: #1d2327; font-size: 13px; font-weight: 600;">Aktywne zlecenia:</h4>';
                                echo '<ul class="projects-list">';
                                foreach ($active_projects as $project) {
                                    $deadline = get_field('end_date', $project->id);
                                    $deadline_text = $deadline ? date('d.m.Y', strtotime($deadline)) : 'Brak terminu';
                                    
                                    echo '<li class="project-item active-project">';
                                    echo '<div class="project-info">';
                                    echo '<a href="#" class="project-link" data-project-id="' . esc_attr($project->id) . '">' . esc_html($project->name) . '</a>';
                                    echo '<span class="project-deadline">Termin: ' . esc_html($deadline_text) . '</span>';
                                    echo '</div>';
                                    echo '</li>';
                                }
                                echo '</ul>';
                                echo '</div>';
                            }
                            
                            if (!empty($completed_projects)) {
                                echo '<div class="projects-section" style="margin-top: 20px;">';
                                echo '<h4 style="cursor: pointer; margin: 0 0 10px 0; color: #646970; font-size: 13px; font-weight: 600;" id="toggle-completed-projects">';
                                echo '<span class="dashicons dashicons-arrow-right"></span> Zakończone zlecenia (' . count($completed_projects) . ')';
                                echo '</h4>';
                                echo '<ul class="projects-list" id="completed-projects-list" style="display: none;">';
                                foreach ($completed_projects as $project) {
                                    $deadline = get_field('end_date', $project->id);
                                    $deadline_text = $deadline ? date('d.m.Y', strtotime($deadline)) : 'Brak terminu';
                                    
                                    echo '<li class="project-item completed-project">';
                                    echo '<div class="project-info">';
                                    echo '<a href="#" class="project-link" data-project-id="' . esc_attr($project->id) . '">' . esc_html($project->name) . '</a>';
                                    echo '<span class="project-deadline">Termin: ' . esc_html($deadline_text) . '</span>';
                                    echo '</div>';
                                    echo '</li>';
                                }
                                echo '</ul>';
                                echo '</div>';
                            }
                            
                            if (empty($active_projects) && empty($completed_projects)) {
                                echo '<p><em>Brak zleceń dla tej osoby.</em></p>';
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <!-- Środkowa kolumna - Aktywności -->
                <div class="dossier-center-column">
                    <div class="dossier-box">
                        <h2 class="dossier-title">Nowa Aktywność</h2>
                        <div class="dossier-content">
                            <form id="wpmzf-add-activity-form" method="post" enctype="multipart/form-data">
                                <?php wp_nonce_field('wpmzf_person_view_nonce', 'wpmzf_security'); ?>
                                <input type="hidden" name="person_id" value="<?php echo esc_attr($person_id); ?>">

                                <input type="file" id="wpmzf-activity-files-input" name="activity_files[]" multiple style="display: none;">

                                <div id="wpmzf-activity-main-editor">
                                    <?php
                                    // Wstawiamy placeholder zamiast edytora
                                    echo '<div id="wpmzf-editor-placeholder" class="wpmzf-editor-placeholder">';
                                    echo '<div class="placeholder-text">Wpisz treść notatki...</div>';
                                    echo '</div>';
                                    
                                    // Kontener na edytor TinyMCE (początkowo ukryty)
                                    echo '<div id="wpmzf-editor-container" style="display: none;">';
                                    
                                    // Generujemy tylko textarea - TinyMCE zostanie zainicjalizowany przez JavaScript
                                    echo '<textarea id="wpmzf-activity-content" name="content" rows="6" style="width: 100%; min-height: 120px; padding: 12px; border: 1px solid #ddd; border-radius: 4px; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, sans-serif; font-size: 14px; line-height: 1.5; resize: vertical;"></textarea>';
                                    
                                    echo '</div>';
                                    ?>
                                </div>

                                <div id="wpmzf-activity-meta-controls">
                                    <div class="activity-options">
                                        <select name="activity_type" id="wpmzf-activity-type">
                                            <option value="note">Notatka</option>
                                            <option value="email">E-mail</option>
                                            <option value="phone">Telefon</option>
                                            <option value="meeting">Spotkanie</option>
                                            <option value="meeting_online">Spotkanie online</option>
                                        </select>
                                        <input type="datetime-local" name="activity_date" id="wpmzf-activity-date" required>
                                    </div>
                                    <div class="activity-actions">
                                        <button type="button" id="wpmzf-attach-file-btn" class="button"><span class="dashicons dashicons-paperclip"></span> Dodaj załącznik</button>
                                        <button type="submit" id="wpmzf-submit-activity-btn" class="button button-primary">Dodaj aktywność</button>
                                    </div>
                                </div>
                                <div id="wpmzf-attachments-preview-container"></div>
                            </form>
                        </div>
                    </div>

                    <div id="wpmzf-activity-timeline-container" class="dossier-box">
                        <h2 class="dossier-title">Historia Aktywności</h2>
                        <div id="wpmzf-activity-timeline" class="dossier-content">
                            <p><em>Ładowanie aktywności...</em></p>
                        </div>
                    </div>
                </div>

                <!-- Prawa kolumna - Zadania -->
                <div class="dossier-right-column">
                    <div class="dossier-box">
                        <h2 class="dossier-title">Nowe Zadanie</h2>
                        <div class="dossier-content">
                            <form id="wpmzf-add-task-form">
                                <?php wp_nonce_field('wpmzf_task_nonce', 'wpmzf_task_security'); ?>
                                <input type="hidden" name="person_id" value="<?php echo esc_attr($person_id); ?>">
                                
                                <div class="task-input-wrapper">
                                    <input type="text" id="wpmzf-task-title" name="task_title" placeholder="Wpisz treść zadania..." required>
                                </div>
                                <div class="task-due-date-wrapper" style="margin-top: 10px;">
                                    <label for="wpmzf-task-due-date" style="display: block; margin-bottom: 5px; font-weight: 600;">Termin wykonania:</label>
                                    <input type="datetime-local" id="wpmzf-task-due-date" name="task_due_date" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                </div>
                                <div class="task-submit-wrapper" style="margin-top: 15px;">
                                    <button type="submit" class="button button-primary">Dodaj zadanie</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div id="wpmzf-tasks-container" class="dossier-box">
                        <h2 class="dossier-title">Zadania</h2>
                        <div class="dossier-content">
                            <div id="wpmzf-open-tasks">
                                <div id="wpmzf-open-tasks-list">
                                    <p><em>Ładowanie zadań...</em></p>
                                </div>
                            </div>
                            <div id="wpmzf-closed-tasks" style="margin-top: 20px;">
                                <h4 style="cursor: pointer;" id="wpmzf-toggle-closed-tasks">
                                    <span class="dashicons dashicons-arrow-right"></span> 
                                    Zakończone zadania
                                </h4>
                                <div id="wpmzf-closed-tasks-list" style="display: none;">
                                    <p><em>Ładowanie zakończonych zadań...</em></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

<?php
    }

    /**
     * Renderuje stronę firm
     */
    public function render_companies_page()
    {
        echo '<div class="wrap">';
        echo '<h1>Firmy</h1>';
        echo '<p>Zarządzanie firmami - strona w budowie.</p>';
        echo '</div>';
    }

    /**
     * Renderuje stronę projektów
     */
    public function render_projects_page()
    {
        echo '<div class="wrap">';
        echo '<h1>Projekty</h1>';
        echo '<p>Zarządzanie projektami - strona w budowie.</p>';
        echo '</div>';
    }

    /**
     * Obsługuje akcje admin (AJAX, itp.)
     */
    public function handle_actions()
    {
        // Tutaj będą obsługiwane akcje admin
    }
}
