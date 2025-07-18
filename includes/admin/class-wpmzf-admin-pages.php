<?php

class WPMZF_Admin_Pages
{

    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_plugin_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_person_view_scripts'));

        //add_action('admin_init', array($this, 'handle_actions'));
        //mateusz: tu jest błąd, bo linia odwóluje się do "handle_actions" które nie istnieje, stąd jest błąd,
        //dlatego po zakomentowaniu już działa prawidłowo :)
    }
    public function enqueue_person_view_scripts($hook)
    {
        // Hook dla strony dodanej przez add_submenu_page z parent_slug='' to 'admin_page_{page_slug}'.
        
        // Widoki osób
        if ('admin_page_wpmzf_view_person' === $hook || 'admin_page_luna-crm-person-view' === $hook) {
            
            // Włączamy skrypty i style ACF, aby pole 'relationship' działało poprawnie.
            if (function_exists('acf_enqueue_scripts')) {
                acf_enqueue_scripts();
            }
            
            // Dodajemy skrypty edytora WYSIWYG
            wp_enqueue_editor();
            wp_enqueue_media();
            
            // Dodajemy nasze style i skrypty dla widoku osoby
            wp_enqueue_style(
                'wpmzf-person-view',
                plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/css/admin-styles.css',
                array(),
                '1.3.0'
            );
            
            wp_enqueue_script(
                'wpmzf-person-view',
                plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/js/admin/person-view.js',
                array('jquery', 'editor'),
                '1.0.4',
                true
            );
            
            // Przekaż zmienne do JavaScript dla widoku osoby
            wp_localize_script('wpmzf-person-view', 'wpmzfPersonView', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'adminUrl' => admin_url(),
                'nonce' => wp_create_nonce('wpmzf_person_view_nonce'),
                'taskNonce' => wp_create_nonce('wpmzf_task_nonce')
            ));
        }
        
        // Widoki firm
        if ('admin_page_wpmzf_view_company' === $hook) {
            
            // Włączamy skrypty i style ACF, aby pole 'relationship' działało poprawnie.
            if (function_exists('acf_enqueue_scripts')) {
                acf_enqueue_scripts();
            }
            
            // Dodajemy skrypty edytora WYSIWYG
            wp_enqueue_editor();
            wp_enqueue_media();
            
            // Dodajemy nasze style i skrypty dla widoku firmy
            wp_enqueue_style(
                'wpmzf-company-view',
                plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/css/admin-styles.css',
                array(),
                '1.3.0'
            );
            
            wp_enqueue_script(
                'wpmzf-company-view',
                plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/js/admin/company-view.js',
                array('jquery', 'editor'),
                '1.0.4',
                true
            );
            
            // Przekaż zmienne do JavaScript dla widoku firmy
            wp_localize_script('wpmzf-company-view', 'wpmzfCompanyView', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'adminUrl' => admin_url(),
                'nonce' => wp_create_nonce('wpmzf_company_view_nonce'),
                'taskNonce' => wp_create_nonce('wpmzf_task_nonce')
            ));
        }
        
        // Widoki projektów
        if ('admin_page_wpmzf_view_project' === $hook) {
            
            // Włączamy skrypty i style ACF, aby pole 'relationship' działało poprawnie.
            if (function_exists('acf_enqueue_scripts')) {
                acf_enqueue_scripts();
            }
            
            // Dodajemy skrypty edytora WYSIWYG
            wp_enqueue_editor();
            wp_enqueue_media();
            
            // Dodajemy nasze style i skrypty dla widoku projektu
            wp_enqueue_style(
                'wpmzf-project-view',
                plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/css/admin-styles.css',
                array(),
                '1.3.0'
            );
            
            wp_enqueue_script(
                'wpmzf-project-view',
                plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/js/admin/project-view.js',
                array('jquery'),
                '1.0.1',
                true
            );
            
            // Przekaż zmienne do JavaScript dla widoku projektu
            wp_localize_script('wpmzf-project-view', 'wpmzfProjectView', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'adminUrl' => admin_url(),
                'nonce' => wp_create_nonce('wpmzf_project_view_nonce'),
                'taskNonce' => wp_create_nonce('wpmzf_task_nonce')
            ));
        }
        
        // Lista firm
        if ('wpmzf_page_wpmzf-companies' === $hook) {
            
            // Dodajemy nasze style
            wp_enqueue_style(
                'wpmzf-companies-list',
                plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/css/admin-styles.css',
                array(),
                '1.3.0'
            );
        }
        
        // Dashboard - dodajemy style i podstawowe skrypty
        if ('wpmzf_page_wpmzf_dashboard' === $hook || 'toplevel_page_wpmzf_dashboard' === $hook) {
            
            // Dodajemy style timeline'u
            wp_enqueue_style(
                'wpmzf-dashboard',
                plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/css/admin-styles.css',
                array(),
                '1.3.0'
            );
            
            // Dodajemy skrypt dla dashboardu
            wp_enqueue_script(
                'wpmzf-dashboard',
                plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/js/admin/dashboard.js',
                array('jquery'),
                '1.0.1',
                true
            );
            
            // Przekaż zmienne do JavaScript dla dashboardu
            wp_localize_script('wpmzf-dashboard', 'wpmzfDashboard', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wpmzf_dashboard_nonce')
            ));
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

        // Rejestrujemy "ukrytą" stronę do widoku pojedynczej firmy.
        // `parent_slug` jako '' ukrywa ją z menu.
        add_submenu_page(
            '',                          // Brak rodzica w menu (ukryta)
            'Widok Firmy',               // page_title – tytuł w <title> i nagłówku
            'Widok Firmy',               // menu_title – nazwa w menu (choć tu niewidoczna)
            'manage_options',            // wymagane uprawnienia
            'wpmzf_view_company',        // slug dla widoku firmy
            array($this, 'render_single_company_page') // callback renderujący
        );

        // Rejestrujemy "ukrytą" stronę do widoku pojedynczego zlecenia/projektu.
        // `parent_slug` jako '' ukrywa ją z menu.
        add_submenu_page(
            '',                          // Brak rodzica w menu (ukryta)
            'Widok Zlecenia',            // page_title – tytuł w <title> i nagłówku
            'Widok Zlecenia',            // menu_title – nazwa w menu (choć tu niewidoczna)
            'manage_options',            // wymagane uprawnienia
            'wpmzf_view_project',        // slug dla widoku zlecenia
            array($this, 'render_single_project_page') // callback renderujący
        );

        // Dodajemy stronę migracji aktywności
        add_submenu_page(
            'wpmzf_dashboard',           // rodzic - dashboard
            'Migracja Aktywności',       // page_title
            'Migracja Aktywności',       // menu_title
            'manage_options',            // wymagane uprawnienia
            'wpmzf_migration_activities', // slug
            array($this, 'render_migration_activities_page') // callback
        );
    }

    /**
     * Renderuje zawartość głównego kokpitu.
     */
    public function render_dashboard_page()
    {
        // Renderuj nawigację i header
        WPMZF_View_Helper::render_complete_header(array(
            'title' => 'Dashboard',
            'subtitle' => 'Dzisiaj: ' . date_i18n('j F Y', current_time('timestamp')),
            'breadcrumbs' => array(
                array('label' => 'Dashboard', 'url' => '')
            ),
            'actions' => array(
                array(
                    'label' => 'Dodaj firmę',
                    'url' => admin_url('post-new.php?post_type=company'),
                    'icon' => '🏢',
                    'class' => 'button button-primary'
                    ),
                    array(
                        'label' => 'Dodaj osobę',
                        'url' => admin_url('post-new.php?post_type=person'),
                        'icon' => '👤',
                        'class' => 'button'
                    )
                )
            ));

        // Pobieramy dane dla dashboardu
        $current_user_id = get_current_user_id();
        $today = current_time('Y-m-d');
        $now = current_time('Y-m-d H:i:s');
        
        // Najpierw znajdujemy ID employee dla aktualnego użytkownika
        $employee_query = new WP_Query([
            'post_type' => 'employee',
            'posts_per_page' => 1,
            'meta_query' => [
                [
                    'key' => 'employee_user',
                    'value' => $current_user_id,
                    'compare' => '='
                ]
            ]
        ]);
        
        $employee_id = null;
        if ($employee_query->have_posts()) {
            $employee_query->the_post();
            $employee_id = get_the_ID();
            wp_reset_postdata();
        }
        
        // Pobieramy zadania przypisane do aktualnego employee
        $my_tasks_query = null;
        if ($employee_id) {
            $my_tasks_query = new WP_Query([
                'post_type' => 'task',
                'posts_per_page' => -1,
                'meta_query' => [
                    [
                        'key' => 'task_employee',
                        'value' => '"' . $employee_id . '"',
                        'compare' => 'LIKE'
                    ]
                ],
                'meta_key' => 'task_start_date',
                'orderby' => 'meta_value',
                'order' => 'ASC'
            ]);
        }
        
        // Zliczamy spóźnione zadania
        $overdue_tasks = 0;
        $my_open_tasks = [];
        
        if ($my_tasks_query && $my_tasks_query->have_posts()) {
            while ($my_tasks_query->have_posts()) {
                $my_tasks_query->the_post();
                $task_id = get_the_ID();
                $task_status = get_field('task_status', $task_id) ?: 'Do zrobienia';
                $start_date = get_field('task_start_date', $task_id);
                
                if ($task_status !== 'Zrobione') {
                    $my_open_tasks[] = [
                        'id' => $task_id,
                        'title' => get_the_title(),
                        'status' => $task_status,
                        'start_date' => $start_date,
                        'is_overdue' => $start_date && $start_date < $now
                    ];
                    
                    if ($start_date && $start_date < $now) {
                        $overdue_tasks++;
                    }
                }
            }
            wp_reset_postdata();
        }
        
        // 10 ostatnio dodanych osób
        $recent_persons_query = new WP_Query([
            'post_type' => 'person',
            'posts_per_page' => 5,
            'orderby' => 'date',
            'order' => 'DESC',
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
        ]);
        
        // 10 ostatnio dodanych firm
        $recent_companies_query = new WP_Query([
            'post_type' => 'company',
            'posts_per_page' => 5,
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => 'company_status',
                    'value' => ['archived', 'Zarchiwizowany'],
                    'compare' => 'NOT IN'
                ],
                [
                    'key' => 'company_status',
                    'compare' => 'NOT EXISTS'
                ]
            ]
        ]);
        
        // Otwarte projekty
        $open_projects_query = new WP_Query([
            'post_type' => 'project',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => 'project_status',
                    'value' => ['Planowanie', 'W toku'],
                    'compare' => 'IN'
                ]
            ]
        ]);
        $open_projects_count = $open_projects_query->found_posts;
        
        // Wszystkie aktywności (nie przyszłe)
        $activities_query = new WP_Query([
            'post_type' => 'activity',
            'post_status' => 'publish',
            'posts_per_page' => 20,
            'orderby' => 'date',
            'order' => 'DESC'
        ]);
        
        // Statystyki szans sprzedaży
        $opportunity_service = new WPMZF_Opportunity_Service();
        $opportunity_stats = $opportunity_service->get_opportunities_stats();
        $opportunities_due_soon = $opportunity_service->get_opportunities_due_soon(7);
        
        ?>
        <div class="wrap">
            <!-- Szybkie statystyki -->
            <div class="quick-stats">
                <div class="stat-tile <?php echo $overdue_tasks > 0 ? 'overdue' : ''; ?>">
                    <div class="stat-number"><?php echo $overdue_tasks; ?></div>
                    <div class="stat-label">Spóźnione zadania</div>
                </div>
                <div class="stat-tile">
                    <div class="stat-number"><?php echo count($my_open_tasks); ?></div>
                    <div class="stat-label">Moje zadania</div>
                </div>
                <div class="stat-tile">
                    <div class="stat-number"><?php echo $recent_persons_query->found_posts; ?></div>
                    <div class="stat-label">Nowe osoby (10)</div>
                </div>
                <div class="stat-tile">
                    <div class="stat-number"><?php echo $recent_companies_query->found_posts; ?></div>
                    <div class="stat-label">Nowe firmy (10)</div>
                </div>
                <div class="stat-tile">
                    <div class="stat-number"><?php echo $open_projects_count; ?></div>
                    <div class="stat-label">Otwarte projekty</div>
                </div>
                <div class="stat-tile">
                    <div class="stat-number"><?php echo $opportunity_stats['total_count']; ?></div>
                    <div class="stat-label">Szanse sprzedaży</div>
                </div>
                <div class="stat-tile">
                    <div class="stat-number"><?php echo number_format($opportunity_stats['total_value'], 0); ?> PLN</div>
                    <div class="stat-label">Wartość szans</div>
                </div>
            </div>

            <div class="dashboard-grid">
                <!-- Lewa kolumna - Moje zadania -->
                <div class="dashboard-left-column">
                    <div class="dashboard-box">
                        <h2 class="dashboard-title">Moje zadania</h2>
                        <div class="dashboard-content">
                            <?php if (!$employee_id) : ?>
                                <div style="padding: 20px; text-align: center; color: #8c8f94; background: #f9f9f9; border-radius: 6px; border-left: 4px solid #ffb900;">
                                    <p style="margin: 0; font-weight: 600;">Brak powiązania z pracownikiem</p>
                                    <p style="margin: 8px 0 0; font-size: 13px;">
                                        Aby widzieć swoje zadania, administrator musi utworzyć dla Ciebie wpis w sekcji "Pracownicy" 
                                        i połączyć go z Twoim kontem użytkownika.
                                    </p>
                                </div>
                            <?php elseif (!empty($my_open_tasks)) : ?>
                                <?php foreach (array_slice($my_open_tasks, 0, 10) as $task) : ?>
                                    <div class="task-item-simple <?php echo $task['is_overdue'] ? 'overdue' : ''; ?>">
                                        <div>
                                            <div class="task-title-simple"><?php echo esc_html($task['title']); ?></div>
                                            <div class="task-meta-simple">
                                                <span class="task-status-simple <?php echo sanitize_html_class(strtolower(str_replace(' ', '-', $task['status']))); ?>">
                                                    <?php echo esc_html($task['status']); ?>
                                                </span>
                                                <?php if ($task['start_date']) : ?>
                                                    | <?php echo date_i18n('j.m.Y H:i', strtotime($task['start_date'])); ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <p style="color: #8c8f94; font-style: italic;">Brak otwartych zadań.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="dashboard-box">
                        <h2 class="dashboard-title">Ostatnie osoby</h2>
                        <div class="dashboard-content">
                            <?php if ($recent_persons_query->have_posts()) : ?>
                                <?php while ($recent_persons_query->have_posts()) : $recent_persons_query->the_post(); ?>
                                    <div class="recent-item">
                                        <div>
                                            <div>
                                                <a href="<?php echo esc_url(add_query_arg(['page' => 'wpmzf_view_person', 'person_id' => get_the_ID()], admin_url('admin.php'))); ?>" class="item-title">
                                                    <?php echo esc_html(get_the_title()); ?>
                                                </a>
                                            </div>
                                            <div class="item-meta">
                                                Dodano: <?php echo get_the_date('j.m.Y H:i'); ?>
                                                <?php 
                                                $person_company = get_field('person_company', get_the_ID());
                                                if ($person_company && is_array($person_company) && !empty($person_company)) :
                                                    $company_title = get_the_title($person_company[0]);
                                                ?>
                                                    | <?php echo esc_html($company_title); ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; wp_reset_postdata(); ?>
                            <?php else : ?>
                                <p style="color: #8c8f94; font-style: italic;">Brak nowych osób.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="dashboard-box">
                        <h2 class="dashboard-title">Ostatnie firmy</h2>
                        <div class="dashboard-content">
                            <?php if ($recent_companies_query->have_posts()) : ?>
                                <?php while ($recent_companies_query->have_posts()) : $recent_companies_query->the_post(); ?>
                                    <div class="recent-item">
                                        <div>
                                            <div>
                                                <a href="<?php echo esc_url(add_query_arg(['page' => 'wpmzf_view_company', 'company_id' => get_the_ID()], admin_url('admin.php'))); ?>" class="item-title">
                                                    <?php echo esc_html(get_the_title()); ?>
                                                </a>
                                            </div>
                                            <div class="item-meta">
                                                Dodano: <?php echo get_the_date('j.m.Y H:i'); ?>
                                                <?php 
                                                $company_nip = get_field('company_nip', get_the_ID());
                                                if ($company_nip) :
                                                ?>
                                                    | NIP: <?php echo esc_html($company_nip); ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; wp_reset_postdata(); ?>
                            <?php else : ?>
                                <p style="color: #8c8f94; font-style: italic;">Brak nowych firm.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Środkowa kolumna - Aktywności -->
                <div class="dashboard-center-column">
                    <div class="dashboard-box">
                        <h2 class="dashboard-title">Ostatnie aktywności</h2>
                        <div class="dashboard-content">
                            <?php
                            // Include komponentu Timeline jeśli nie jest już załadowany
                            if (!class_exists('WPMZF_Timeline')) {
                                require_once WPMZF_PLUGIN_PATH . 'includes/admin/components/timeline/class-wpmzf-timeline.php';
                            }
                            
                            // Renderuj uniwersalny komponent Timeline dla dashboardu
                            $dashboard_timeline = new WPMZF_Timeline([
                                'context' => 'dashboard',
                                'id' => 0,
                                'limit' => 10,
                                'show_add_button' => false
                            ]);
                            
                            $dashboard_timeline->render();
                            ?>
                        </div>
                    </div>
                </div>

                <!-- Widget Szans Sprzedaży -->
                <div class="dashboard-center-column">
                    <div class="dashboard-box">
                        <div class="dashboard-header">
                            <h2 class="dashboard-title">
                                <span class="dashicons dashicons-chart-line"></span>
                                Szanse sprzedaży
                            </h2>
                            <a href="<?php echo esc_url(admin_url('edit.php?post_type=opportunity&page=wpmzf_kanban_view')); ?>" class="button button-primary button-small">
                                Kanban
                            </a>
                        </div>
                        <div class="dashboard-content">
                            <?php if (!empty($opportunity_stats['by_status'])): ?>
                                <div class="opportunities-stats">
                                    <?php foreach ($opportunity_stats['by_status'] as $status_name => $status_data): ?>
                                        <div class="opportunity-status-item status-<?php echo sanitize_title($status_name); ?>">
                                            <div class="status-count"><?php echo $status_data['count']; ?></div>
                                            <div class="status-name"><?php echo esc_html($status_name); ?></div>
                                            <div class="status-value"><?php echo number_format($status_data['value'], 0); ?> PLN</div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <?php if (!empty($opportunities_due_soon)): ?>
                                    <div class="opportunities-due-soon">
                                        <h4 style="margin: 20px 0 10px; color: #1d2327; font-size: 14px;">
                                            <span class="dashicons dashicons-clock" style="color: #f39c12;"></span>
                                            Do zamknięcia w ciągu 7 dni
                                        </h4>
                                        <?php foreach (array_slice($opportunities_due_soon, 0, 5) as $opportunity): ?>
                                            <?php
                                            $company = $opportunity->get_company();
                                            $expected_close = get_field('opportunity_expected_close_date', $opportunity->get_id());
                                            $days_left = $expected_close ? floor((strtotime($expected_close) - time()) / (60 * 60 * 24)) : null;
                                            ?>
                                            <div class="opportunity-due-item <?php echo $days_left !== null && $days_left <= 0 ? 'overdue' : ''; ?>">
                                                <div class="opportunity-info">
                                                    <a href="<?php echo get_edit_post_link($opportunity->get_id()); ?>" class="opportunity-title">
                                                        <?php echo esc_html($opportunity->get_title()); ?>
                                                    </a>
                                                    <div class="opportunity-meta">
                                                        <?php if ($company): ?>
                                                            <?php echo esc_html($company->get_name()); ?> • 
                                                        <?php endif; ?>
                                                        <?php echo number_format($opportunity->get_value(), 0); ?> PLN
                                                    </div>
                                                </div>
                                                <div class="opportunity-due-date">
                                                    <?php if ($days_left !== null): ?>
                                                        <?php if ($days_left > 0): ?>
                                                            <span class="days-left"><?php echo $days_left; ?> dni</span>
                                                        <?php elseif ($days_left == 0): ?>
                                                            <span class="days-left today">Dziś</span>
                                                        <?php else: ?>
                                                            <span class="days-left overdue"><?php echo abs($days_left); ?> dni po terminie</span>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div style="text-align: center; color: #8c8f94; padding: 20px;">
                                    <p style="margin: 0;">Brak szans sprzedaży.</p>
                                    <a href="<?php echo admin_url('post-new.php?post_type=opportunity'); ?>" class="button button-secondary" style="margin-top: 10px;">
                                        Dodaj pierwszą szansę
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                
            </div>
        </div>
        
        <script>
        // Dashboard timeline functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Jeśli funkcja viewActivityDetails nie jest jeszcze dostępna (skrypt nie załadowany)
            // utworzmy prostą implementację
            if (typeof window.viewActivityDetails === 'undefined') {
                window.viewActivityDetails = function(activityId) {
                    const activityElement = document.querySelector(`[data-activity-id="${activityId}"]`);
                    
                    if (!activityElement) {
                        console.error('Nie znaleziono aktywności o ID:', activityId);
                        return;
                    }
                    
                    // Pobierz informacje z elementu
                    const activityContentElement = activityElement.querySelector('.activity-content-display');
                    const activityHeaderElement = activityElement.querySelector('.timeline-header-meta span:last-child');
                    const activityDateElement = activityElement.querySelector('.timeline-header-date');
                    const relatedElement = activityElement.querySelector('.activity-related');
                    const attachmentsElement = activityElement.querySelector('.timeline-attachments');
                    
                    const activityContent = activityContentElement ? activityContentElement.innerHTML : '';
                    const activityHeader = activityHeaderElement ? activityHeaderElement.textContent : '';
                    const activityDate = activityDateElement ? activityDateElement.textContent : '';
                    const relatedInfo = relatedElement ? relatedElement.innerHTML : '';
                    const attachments = attachmentsElement ? attachmentsElement.innerHTML : '';
                    
                    // Stwórz modal z szczegółami
                    const modalHtml = `
                        <div id="activity-details-modal" style="
                            position: fixed;
                            top: 0;
                            left: 0;
                            width: 100%;
                            height: 100%;
                            background: rgba(0,0,0,0.7);
                            z-index: 100000;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                        ">
                            <div style="
                                background: #fff;
                                border-radius: 8px;
                                max-width: 800px;
                                width: 90%;
                                max-height: 80%;
                                overflow-y: auto;
                                box-shadow: 0 4px 20px rgba(0,0,0,0.3);
                            ">
                                <div style="
                                    padding: 24px;
                                    border-bottom: 1px solid #e1e5e9;
                                    background: #f8f9fa;
                                    border-radius: 8px 8px 0 0;
                                    display: flex;
                                    justify-content: space-between;
                                    align-items: center;
                                ">
                                    <div>
                                        <h2 style="margin: 0; font-size: 18px; color: #1d2327;">${activityHeader}</h2>
                                        <p style="margin: 4px 0 0; color: #646970; font-size: 14px;">${activityDate}</p>
                                    </div>
                                    <button id="close-activity-modal" style="
                                        background: none;
                                        border: none;
                                        font-size: 24px;
                                        cursor: pointer;
                                        color: #646970;
                                        padding: 4px;
                                        border-radius: 4px;
                                        transition: all 0.2s ease;
                                    ">&times;</button>
                                </div>
                                <div style="padding: 24px;">
                                    <div style="
                                        color: #1d2327;
                                        line-height: 1.6;
                                        font-size: 15px;
                                        margin-bottom: ${relatedInfo || attachments ? '24px' : '0'};
                                    ">
                                        ${activityContent}
                                    </div>
                                    ${relatedInfo ? `
                                        <div style="
                                            padding: 16px;
                                            background: #f8f9fa;
                                            border-radius: 6px;
                                            border-left: 4px solid #2271b1;
                                            margin-bottom: ${attachments ? '20px' : '0'};
                                        ">
                                            ${relatedInfo}
                                        </div>
                                    ` : ''}
                                    ${attachments ? `
                                        <div style="
                                            border-top: 1px solid #e1e5e9;
                                            padding-top: 20px;
                                        ">
                                            <h4 style="margin: 0 0 12px; color: #1d2327; font-size: 14px; font-weight: 600;">Załączniki:</h4>
                                            ${attachments}
                                        </div>
                                    ` : ''}
                                </div>
                            </div>
                        </div>
                    `;
                    
                    // Dodaj modal do strony
                    document.body.insertAdjacentHTML('beforeend', modalHtml);
                    
                    // Obsługa zamykania modala
                    const modal = document.getElementById('activity-details-modal');
                    const closeBtn = document.getElementById('close-activity-modal');
                    
                    function closeModal() {
                        if (modal) {
                            modal.remove();
                        }
                    }
                    
                    // Kliknięcie na tło zamyka modal
                    modal.addEventListener('click', function(e) {
                        if (e.target === modal) {
                            closeModal();
                        }
                    });
                    
                    // Przycisk zamknij
                    closeBtn.addEventListener('click', closeModal);
                    
                    // Hover effects dla przycisku zamknij
                    closeBtn.addEventListener('mouseenter', function() {
                        this.style.background = '#f0f0f1';
                        this.style.color = '#d63638';
                    });
                    
                    closeBtn.addEventListener('mouseleave', function() {
                        this.style.background = 'none';
                        this.style.color = '#646970';
                    });
                    
                    // Zapobiegaj zamykaniu modala przy kliknięciu na zawartość
                    const modalContent = modal.querySelector('div');
                    modalContent.addEventListener('click', function(e) {
                        e.stopPropagation();
                    });
                    
                    // Escape key zamyka modal
                    function handleEscape(e) {
                        if (e.key === 'Escape') {
                            closeModal();
                            document.removeEventListener('keydown', handleEscape);
                        }
                    }
                    document.addEventListener('keydown', handleEscape);
                };
            }
            
            // Dodaj hover effects dla timeline actions
            const timelineActions = document.querySelectorAll('.timeline-actions .dashicons');
            timelineActions.forEach(function(action) {
                action.addEventListener('mouseenter', function() {
                    this.style.color = '#2271b1';
                    this.style.background = '#f0f6fc';
                });
                
                action.addEventListener('mouseleave', function() {
                    this.style.color = '#646970';
                    this.style.background = 'transparent';
                });
            });
        });
        </script>
        <?php
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
        // Renderuj nawigację i header
        WPMZF_View_Helper::render_complete_header(array(
            'title' => 'Osoby',
            'subtitle' => 'Zarządzaj kontaktami osobowymi w swoim systemie CRM',
            'breadcrumbs' => array(
                array('label' => 'Dashboard', 'url' => admin_url('admin.php?page=wpmzf_dashboard')),
                array('label' => 'Osoby', 'url' => '')
            ),
            'actions' => array(
                array(
                    'label' => 'Dodaj osobę',
                    'url' => admin_url('post-new.php?post_type=person'),
                    'icon' => '➕',
                    'class' => 'button button-primary'
                ),
                array(
                    'label' => 'Import osób',
                    'url' => '#',
                    'icon' => '📥',
                    'class' => 'button'
                )
            )
        ));

        // Stwórz instancję i przygotuj dane tabeli
        $persons_table = new WPMZF_persons_List_Table();
        $persons_table->prepare_items();

        ?>
        <div class="wrap">
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

        // Include komponentu Timeline
        require_once WPMZF_PLUGIN_PATH . 'includes/admin/components/timeline/class-wpmzf-timeline.php';

        $person_title = get_the_title($person_id);
        $title = 'Widok Osoby: ' . $person_title; // Ustawiamy globalny tytuł strony
        $person_fields = get_fields($person_id);

        // Renderuj nawigację i header
        WPMZF_View_Helper::render_complete_header(array(
            'title' => $person_title,
            'subtitle' => 'Szczegółowe informacje o osobie',
            'breadcrumbs' => array(
                array('label' => 'Dashboard', 'url' => admin_url('admin.php?page=wpmzf_dashboard')),
                array('label' => 'Osoby', 'url' => admin_url('admin.php?page=wpmzf_persons')),
                array('label' => $person_title, 'url' => '')
            ),
            'actions' => array(
                array(
                    'label' => 'Edytuj osobę',
                    'url' => admin_url('post.php?post=' . $person_id . '&action=edit'),
                    'icon' => '✏️',
                    'class' => 'button button-primary'
                ),
                array(
                    'label' => 'Dodaj aktywność',
                    'url' => admin_url('post-new.php?post_type=activity'),
                    'icon' => '📝',
                    'class' => 'button'
                )
            )
        ));

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

            /* Drag & Drop Styles */
            .wpmzf-drag-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 113, 161, 0.9);
                z-index: 999999;
                display: none;
                justify-content: center;
                align-items: center;
                pointer-events: none;
            }

            .wpmzf-drag-overlay.active {
                display: flex;
            }

            .wpmzf-drag-message {
                text-align: center;
                color: white;
                font-size: 24px;
                font-weight: 500;
            }

            .wpmzf-drag-icon {
                font-size: 48px;
                margin-bottom: 20px;
                opacity: 0.9;
            }

            .wpmzf-drag-text {
                font-size: 18px;
                opacity: 0.9;
            }

            #wpmzf-activity-box.drag-target {
                background: rgba(0, 113, 161, 0.05);
                border: 2px dashed #0071a1;
                border-radius: 8px;
                transform: scale(1.02);
                transition: all 0.2s ease;
            }

            /* Attachment Preview Styles */
            #wpmzf-note-attachments-preview-container {
                margin-top: 15px;
                padding: 15px;
                background: #f8f9fa;
                border: 1px solid #e0e0e0;
                border-radius: 6px;
                display: none;
            }

            #wpmzf-note-attachments-preview-container.has-files {
                display: block;
            }

            .wpmzf-attachment-preview-item {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 10px;
                background: white;
                border: 1px solid #ddd;
                border-radius: 4px;
                margin-bottom: 10px;
                transition: all 0.2s ease;
            }

            .wpmzf-attachment-preview-item:hover {
                border-color: #0071a1;
                box-shadow: 0 2px 4px rgba(0, 113, 161, 0.1);
            }

            .wpmzf-attachment-preview-item .file-info {
                display: flex;
                align-items: center;
                gap: 12px;
                flex-grow: 1;
            }

            .wpmzf-attachment-preview-item .attachment-thumbnail {
                width: 40px;
                height: 40px;
                display: flex;
                align-items: center;
                justify-content: center;
                background: #f0f0f0;
                border-radius: 4px;
                overflow: hidden;
            }

            .wpmzf-attachment-preview-item .attachment-thumbnail img {
                max-width: 100%;
                max-height: 100%;
                object-fit: cover;
            }

            .wpmzf-attachment-preview-item .attachment-thumbnail .file-icon {
                font-size: 20px;
                color: #666;
            }

            .wpmzf-attachment-preview-item .attachment-info {
                display: flex;
                flex-direction: column;
                gap: 2px;
            }

            .wpmzf-attachment-preview-item .attachment-name {
                font-weight: 500;
                color: #1d2327;
                word-break: break-word;
            }

            .wpmzf-attachment-preview-item .attachment-size {
                font-size: 12px;
                color: #646970;
            }

            .wpmzf-attachment-preview-item .file-actions {
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .wpmzf-attachment-preview-item .remove-attachment {
                cursor: pointer;
                color: #a00;
                padding: 4px;
                border-radius: 2px;
                transition: all 0.2s ease;
            }

            .wpmzf-attachment-preview-item .remove-attachment:hover {
                background: #ff6b6b;
                color: white;
            }

            .wpmzf-attachment-preview-item .attachment-progress {
                display: flex;
                align-items: center;
                gap: 8px;
                min-width: 100px;
            }

            .wpmzf-attachment-preview-item .attachment-progress-bar {
                flex: 1;
                height: 4px;
                background: #e0e0e0;
                border-radius: 2px;
                overflow: hidden;
            }

            .wpmzf-attachment-preview-item .attachment-progress-bar::after {
                content: '';
                display: block;
                height: 100%;
                background: #0071a1;
                width: 0%;
                transition: width 0.3s ease;
            }

            .wpmzf-attachment-preview-item .attachment-progress-text {
                font-size: 12px;
                color: #646970;
                min-width: 30px;
            }

            .transcribe-option {
                font-size: 12px;
                color: #646970;
            }

            .transcribe-option label {
                display: flex;
                align-items: center;
                gap: 5px;
                cursor: pointer;
            }

            .transcribe-option input[type="checkbox"] {
                margin: 0;
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
            
            }
            
            /* Important Links Styles */
            #important-links-container {
                min-height: 60px;
            }
            
            .important-link-item {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 12px;
                border: 1px solid #e1e5e9;
                border-radius: 6px;
                margin-bottom: 8px;
                background: #fff;
                transition: all 0.2s ease;
                position: relative;
            }
            
            .important-link-item:hover {
                border-color: #2271b1;
                box-shadow: 0 2px 8px rgba(34, 113, 177, 0.15);
            }
            
            .important-link-favicon {
                flex-shrink: 0;
                width: 20px;
                height: 20px;
                background: #f0f0f1;
                border-radius: 3px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .important-link-favicon img {
                width: 16px;
                height: 16px;
                border-radius: 2px;
            }
            
            .important-link-content {
                flex: 1;
                min-width: 0;
            }
            
            .important-link-title {
                font-weight: 500;
                color: #1d2327;
                margin: 0 0 4px 0;
                font-size: 14px;
                line-height: 1.3;
                display: block;
                text-decoration: none;
                word-break: break-word;
            }
            
            .important-link-title:hover {
                color: #2271b1;
                text-decoration: underline;
            }
            
            .important-link-url {
                font-size: 12px;
                color: #646970;
                margin: 0;
                word-break: break-all;
            }
            
            .important-link-actions {
                display: flex;
                gap: 4px;
                opacity: 0;
                transition: opacity 0.2s ease;
            }
            
            .important-link-item:hover .important-link-actions {
                opacity: 1;
            }
            
            .important-link-action {
                padding: 4px 6px;
                border: 1px solid #c3c4c7;
                background: #fff;
                color: #646970;
                text-decoration: none;
                border-radius: 3px;
                font-size: 12px;
                cursor: pointer;
                transition: all 0.2s ease;
            }
            
            .important-link-action:hover {
                border-color: #2271b1;
                color: #2271b1;
            }
            
            .important-link-action.delete:hover {
                border-color: #d63638;
                color: #d63638;
                background: #fef7f7;
            }
            
            #important-link-form {
                background: #f8f9fa;
                border: 1px solid #e1e5e9;
                border-radius: 6px;
                padding: 16px;
                margin-top: 12px;
            }
            
            #important-link-form .form-group {
                margin-bottom: 12px;
            }
            
            #important-link-form label {
                display: block;
                margin-bottom: 4px;
                font-weight: 500;
                color: #1d2327;
            }
            
            #important-link-form input[type="url"],
            #important-link-form input[type="text"] {
                width: 100%;
                padding: 8px 12px;
                border: 1px solid #c3c4c7;
                border-radius: 4px;
                font-size: 14px;
                background: #fff;
            }
            
            #important-link-form input:focus {
                outline: none;
                border-color: #2271b1;
                box-shadow: 0 0 0 1px #2271b1;
            }
            
            .no-important-links {
                text-align: center;
                color: #646970;
                font-style: italic;
                padding: 20px;
            }
            
            .important-links-loading {
                text-align: center;
                color: #646970;
                padding: 16px;
            }
        </style>

        <div class="wrap">
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
                                <p><strong>Polecający:</strong> <span data-field="person_referrer">
                                    <?php
                                    $referrer = get_field('person_referrer', $person_id);
                                    if ($referrer && is_array($referrer) && !empty($referrer)) {
                                        $referrer_post = get_post($referrer[0]);
                                        if ($referrer_post) {
                                            $referrer_type = get_post_type($referrer_post->ID) === 'company' ? '🏢' : '👤';
                                            echo $referrer_type . ' ' . esc_html($referrer_post->post_title);
                                        } else {
                                            echo 'Brak';
                                        }
                                    } else {
                                        echo 'Brak';
                                    }
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

                                    <label for="person_referrer_select">Polecający:</label>
                                    <select id="person_referrer_select" name="person_referrer" style="width: 100%;">
                                        <?php 
                                        $current_referrer = get_field('person_referrer', $person_id);
                                        $referrer_id = '';
                                        $referrer_title = '';
                                        if ($current_referrer && is_array($current_referrer) && !empty($current_referrer)) {
                                            $referrer_post = get_post($current_referrer[0]);
                                            if ($referrer_post) {
                                                $referrer_id = $referrer_post->ID;
                                                $referrer_type = get_post_type($referrer_post->ID) === 'company' ? '🏢' : '👤';
                                                $referrer_title = $referrer_type . ' ' . $referrer_post->post_title;
                                            }
                                        }
                                        ?>
                                        <option value="">Brak polecającego</option>
                                        <?php if ($referrer_id && $referrer_title) : ?>
                                            <option value="<?php echo esc_attr($referrer_id); ?>" selected="selected"><?php echo esc_html($referrer_title); ?></option>
                                        <?php endif; ?>
                                    </select>

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
                    
                    <!-- Sekcja Ważnych Linków -->
                    <div class="dossier-box" id="important-links-section">
                        <h2 class="dossier-title">
                            Ważne linki
                            <button type="button" id="add-important-link-btn" class="edit-data-button">Dodaj link</button>
                        </h2>
                        <div class="dossier-content">
                            <div id="important-links-container">
                                <p><em>Ładowanie linków...</em></p>
                            </div>
                            
                            <!-- Formularz dodawania/edycji linku -->
                            <div id="important-link-form" style="display: none;">
                                <form id="wpmzf-important-link-form">
                                    <?php wp_nonce_field('wpmzf_person_view_nonce', 'wpmzf_link_security'); ?>
                                    <input type="hidden" name="person_id" value="<?php echo esc_attr($person_id); ?>">
                                    <input type="hidden" name="object_id" value="<?php echo esc_attr($person_id); ?>">
                                    <input type="hidden" name="object_type" value="person">
                                    <input type="hidden" name="link_id" id="edit-link-id" value="">
                                    
                                    <div class="form-group">
                                        <label for="link-url">URL linku:</label>
                                        <input type="url" id="link-url" name="url" placeholder="https://example.com" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                    </div>
                                    
                                    <div class="form-group" style="margin-top: 10px;">
                                        <label for="link-custom-title">Niestandardowy opis (opcjonalnie):</label>
                                        <input type="text" id="link-custom-title" name="custom_title" placeholder="Jeśli pozostawisz puste, pobierzemy automatycznie tytuł strony" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                        <small style="color: #666;">Jeśli nie wpiszesz opisu, automatycznie pobierzemy tytuł strony</small>
                                    </div>
                                    
                                    <div class="form-actions" style="margin-top: 15px; display: flex; gap: 10px;">
                                        <button type="submit" class="button button-primary">
                                            <span id="link-submit-text">Dodaj link</span>
                                        </button>
                                        <button type="button" id="cancel-link-form" class="button">Anuluj</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Środkowa kolumna - Aktywności -->
                <div class="dossier-center-column">
                    <div class="dossier-box">
                        <h2 class="dossier-title">Nowa Aktywność</h2>
                        <div class="dossier-content">
                            <div id="wpmzf-activity-box">
                                <div class="activity-tabs">
                                    <button class="tab-link active" data-tab="note">📝 Dodaj notatkę</button>
                                    <button class="tab-link" data-tab="email">✉️ Wyślij e-mail</button>
                                </div>

                                <div id="note-tab-content" class="tab-content active">
                                    <form id="wpmzf-add-note-form" method="post" enctype="multipart/form-data">
                                        <?php wp_nonce_field('wpmzf_person_view_nonce', 'wpmzf_note_security'); ?>
                                        <input type="hidden" name="person_id" value="<?php echo esc_attr($person_id); ?>">
                                        
                                        <input type="file" id="wpmzf-note-files-input" name="activity_files[]" multiple style="display: none;">

                                        <div id="wpmzf-note-main-editor">
                                            <div id="wpmzf-note-editor-placeholder" class="wpmzf-editor-placeholder">
                                                <div class="placeholder-text">Opisz co się wydarzyło... (np. odbyłem spotkanie, wysłałem ofertę z prywatnej skrzynki, itp.)</div>
                                            </div>
                                            
                                            <div id="wpmzf-note-editor-container" style="display: none;">
                                                <?php
                                                wp_editor('', 'wpmzf-note-content', array(
                                                    'textarea_name' => 'content',
                                                    'textarea_rows' => 4,
                                                    'media_buttons' => false,
                                                    'teeny' => true,
                                                    'quicktags' => true,
                                                    'tinymce' => array(
                                                        'toolbar1' => 'bold,italic,underline,forecolor,bullist,numlist,link,unlink,removeformat,undo,redo',
                                                        'toolbar2' => '',
                                                        'height' => 120,
                                                        'plugins' => 'lists,link,paste,textcolor'
                                                    )
                                                ));
                                                ?>
                                            </div>
                                        </div>

                                        <div class="activity-meta-controls">
                                            <div class="activity-options">
                                                <div>
                                                    <label for="note-type">Typ zdarzenia:</label>
                                                    <select id="note-type" name="activity_type">
                                                        <option value="notatka">Notatka</option>
                                                        <option value="email">E-mail (wysłany poza systemem)</option>
                                                        <option value="telefon">Telefon</option>
                                                        <option value="spotkanie">Spotkanie</option>
                                                        <option value="spotkanie-online">Spotkanie online</option>
                                                    </select>
                                                </div>
                                                
                                                <div>
                                                    <label for="wpmzf-note-date">Data aktywności:</label>
                                                    <input type="datetime-local" id="wpmzf-note-date" name="activity_date" value="<?php echo date('Y-m-d\TH:i'); ?>">
                                                </div>
                                            </div>
                                            <div class="activity-actions">
                                                <button type="button" id="wpmzf-note-attach-files-btn" class="button">
                                                    <span class="dashicons dashicons-paperclip"></span> Dodaj załączniki
                                                </button>
                                                <button type="submit" class="button button-primary">Dodaj notatkę</button>
                                            </div>
                                        </div>
                                        <div id="wpmzf-note-attachments-preview-container"></div>
                                    </form>
                                </div>

                                <div id="email-tab-content" class="tab-content">
                                    <form id="wpmzf-send-email-form" method="post">
                                        <?php wp_nonce_field('wpmzf_person_view_nonce', 'wpmzf_email_security'); ?>
                                        <input type="hidden" name="person_id" value="<?php echo esc_attr($person_id); ?>">
                                        
                                        <div class="email-fields-grid">
                                            <input type="email" name="email_to" placeholder="Do:" required>
                                            <input type="email" name="email_cc" placeholder="DW:">
                                            <input type="email" name="email_bcc" placeholder="UDW:">
                                            <input type="text" name="email_subject" placeholder="Temat wiadomości" required>
                                        </div>
                                        
                                        <?php
                                        wp_editor('', 'email-content', array(
                                            'textarea_name' => 'content',
                                            'textarea_rows' => 6,
                                            'media_buttons' => true,
                                            'tinymce' => array(
                                                'toolbar1' => 'bold,italic,underline,forecolor,bullist,numlist,link,unlink,removeformat,undo,redo',
                                                'toolbar2' => '',
                                                'height' => 150,
                                                'plugins' => 'lists,link,paste,textcolor'
                                            )
                                        ));
                                        ?>
                                        
                                        <div class="activity-meta-controls">
                                            <div></div>
                                            <button type="submit" class="button button-primary">Wyślij e-mail</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="wpmzf-activity-timeline-container" class="dossier-box">
                        <h2 class="dossier-title">Historia Aktywności</h2>
                        <div id="wpmzf-activity-timeline" class="dossier-content">
                            <?php
                            // Renderuj uniwersalny komponent Timeline dla osoby
                            $person_timeline = new WPMZF_Timeline([
                                'context' => 'person',
                                'id' => $person_id,
                                'limit' => 30, // Więcej aktywności dla widoku osoby
                                'show_add_button' => true // Pokazujemy przycisk dodaj aktywność
                            ]);
                            
                            $person_timeline->render();
                            ?>
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
                                <div class="task-form-row" style="margin-top: 10px; display: flex; gap: 15px;">
                                    <div class="task-assigned-user-wrapper" style="flex: 1;">
                                        <label for="wpmzf-task-assigned-user" style="display: block; margin-bottom: 5px; font-weight: 600;">Odpowiedzialny:</label>
                                        <?php
                                        echo WPMZF_Employee_Helper::render_employee_select(
                                            'assigned_user',
                                            0,
                                            [
                                                'id' => 'wpmzf-task-assigned-user',
                                                'style' => 'width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;'
                                            ]
                                        );
                                        ?>
                                    </div>
                                    <div class="task-due-date-wrapper" style="flex: 1;">
                                        <label for="wpmzf-task-due-date" style="display: block; margin-bottom: 5px; font-weight: 600;">Termin wykonania:</label>
                                        <input type="datetime-local" id="wpmzf-task-due-date" name="task_due_date" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                    </div>
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

        <!-- JavaScript został przeniesiony do assets/js/admin/person-view.js -->
        <!-- Skrypt jest ładowany przez enqueue_person_view_scripts() w class-wpmzf-admin-pages.php -->
        <input type="hidden" name="person_id" value="<?php echo esc_attr($person_id); ?>" />
        <input type="hidden" id="wpmzf_security" value="<?php echo wp_create_nonce('wpmzf_person_view_nonce'); ?>" />
        <input type="hidden" id="wpmzf_task_security" value="<?php echo wp_create_nonce('wpmzf_task_nonce'); ?>" />

<?php
    }

    /**
     * Renderuje stronę firm
     */
    public function render_companies_page()
    {
        // Renderuj nawigację i header
        WPMZF_View_Helper::render_complete_header(array(
            'title' => 'Firmy',
            'subtitle' => 'Zarządzaj firmami w swoim systemie CRM',
            'breadcrumbs' => array(
                array('label' => 'Dashboard', 'url' => admin_url('admin.php?page=wpmzf_dashboard')),
                array('label' => 'Firmy', 'url' => '')
            ),
            'actions' => array(
                array(
                    'label' => 'Dodaj firmę',
                    'url' => admin_url('post-new.php?post_type=company'),
                    'icon' => '➕',
                    'class' => 'button button-primary'
                ),
                array(
                    'label' => 'Import firm',
                    'url' => '#',
                    'icon' => '📥',
                    'class' => 'button'
                )
            )
        ));

        // Usunięto obsługę widoku pojedynczej firmy - teraz jest obsługiwane przez dedykowane submenu
        
        // Stwórz instancję i przygotuj dane tabeli
        $companies_table = new WPMZF_companies_List_Table();
        $companies_table->prepare_items();

        // --- Logika statystyk ---

        $base_url = admin_url('admin.php?page=wpmzf-companies');

        // --- Statystyka "Wszystkie" ---
        $all_companies_query = new WP_Query([
            'post_type'      => 'company',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [
                'relation' => 'OR',
                [
                    'relation' => 'AND',
                    ['key' => 'company_status', 'value' => ['archived', 'Zarchiwizowany'], 'compare' => 'NOT IN']
                ],
                ['key' => 'company_status', 'compare' => 'NOT EXISTS']
            ]
        ]);
        $all_count = $all_companies_query->found_posts;

        // --- Statystyka dzienna ---
        $current_day_str = $_GET['stat_day'] ?? current_time('Y-m-d');
        $current_day_dt = new DateTime($current_day_str);
        $prev_day_url = add_query_arg('stat_day', (clone $current_day_dt)->modify('-1 day')->format('Y-m-d'), $base_url);
        $next_day_url = add_query_arg('stat_day', (clone $current_day_dt)->modify('+1 day')->format('Y-m-d'), $base_url);

        $daily_query = new WP_Query([
            'post_type' => 'company',
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
            'post_type' => 'company',
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
            'post_type' => 'company',
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
        </style>

        <div class="wrap">
            <h1>
                Firmy 
                <a href="<?php echo admin_url('post-new.php?post_type=company'); ?>" class="page-title-action">Dodaj nową firmę</a>
            </h1>

            <!-- Panel statystyk -->
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
                $companies_table->display();
                ?>
            </form>
        </div>
    <?php
    }

    /**
     * Renderuje stronę projektów
     */
    public function render_projects_page()
    {
        // Renderuj nawigację i header
        WPMZF_View_Helper::render_complete_header(array(
            'title' => 'Projekty',
            'subtitle' => 'Zarządzaj projektami i śledź ich postęp',
            'breadcrumbs' => array(
                array('label' => 'Dashboard', 'url' => admin_url('admin.php?page=wpmzf_dashboard')),
                array('label' => 'Projekty', 'url' => '')
            ),
            'actions' => array(
                array(
                    'label' => 'Dodaj projekt',
                    'url' => admin_url('post-new.php?post_type=project'),
                    'icon' => '➕',
                    'class' => 'button button-primary'
                ),
                array(
                    'label' => 'Zarządzaj zadaniami',
                    'url' => admin_url('edit.php?post_type=task'),
                    'icon' => '✅',
                    'class' => 'button'
                )
            )
        ));

        echo '<div class="wrap">';
        echo '<p>Zarządzanie projektami - strona w budowie.</p>';
        echo '</div>';
    }

    /**
     * Renderuje widok pojedynczej firmy
     */
    public function render_single_company_page()
    {
        $company_id = isset($_GET['company_id']) ? intval($_GET['company_id']) : 0;
        
        if ($company_id <= 0) {
            echo '<div class="wrap">';
            echo '<h1>Błąd</h1>';
            echo '<p>Nieprawidłowe ID firmy.</p>';
            echo '</div>';
            return;
        }
        
        // Sprawdź czy firma istnieje
        $company = get_post($company_id);
        if (!$company || $company->post_type !== 'company') {
            echo '<div class="wrap">';
            echo '<h1>Błąd</h1>';
            echo '<p>Firma nie została znaleziona.</p>';
            echo '</div>';
            return;
        }
        
        // Renderuj widok pojedynczej firmy
        include_once plugin_dir_path(__FILE__) . 'views/companies/company-view.php';
    }

    /**
     * Renderuje widok pojedynczego zlecenia/projektu
     */
    public function render_single_project_page()
    {
        $project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
        
        if ($project_id <= 0) {
            echo '<div class="wrap">';
            echo '<h1>Błąd</h1>';
            echo '<p>Nieprawidłowe ID projektu.</p>';
            echo '</div>';
            return;
        }
        
        // Sprawdź czy projekt istnieje
        $project = get_post($project_id);
        if (!$project || $project->post_type !== 'project') {
            echo '<div class="wrap">';
            echo '<h1>Błąd</h1>';
            echo '<p>Projekt nie został znaleziony.</p>';
            echo '</div>';
            return;
        }
        
        // Renderuj widok pojedynczego projektu
        include_once plugin_dir_path(__FILE__) . 'views/projects/project-view.php';
    }
}
