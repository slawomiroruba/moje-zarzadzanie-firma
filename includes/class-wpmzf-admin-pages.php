<?php

class WPMZF_Admin_Pages
{

    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_plugin_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_contact_view_scripts'));
    }
    public function enqueue_contact_view_scripts($hook)
    {
        // Hook dla strony dodanej przez add_submenu_page z parent_slug=null to 'admin_page_{page_slug}'.
        if ('admin_page_wpmzf_contact_view' === $hook) {
            // Włączamy skrypty i style ACF, aby pole 'relationship' działało poprawnie.
            acf_enqueue_scripts();
        }
    }

    /**
     * Dodaje strony pluginu do menu w panelu admina.
     */
    public function add_plugin_admin_menu()
    {
        // Dodajemy główną stronę "Kokpit Firmy"
        add_menu_page(
            'Kokpit Firmy',                   // Tytuł strony (w tagu <title>)
            'Kokpit Firmy',                   // Nazwa w menu
            'manage_options',                 // Wymagane uprawnienia
            'wpmzf_dashboard',                // Slug strony
            array($this, 'render_dashboard_page'), // Funkcja renderująca zawartość
            'dashicons-dashboard',            // Ikona
            6                                 // Pozycja w menu
        );

        // Dodajemy pod-stronę do zarządzania dokumentami
        add_submenu_page(
            'wpmzf_dashboard',                // Slug strony nadrzędnej
            'Zarządzanie Dokumentami',        // Tytuł strony
            'Dokumenty',                      // Nazwa w menu
            'manage_options',                 // Uprawnienia
            'wpmzf_documents',                // Slug tej pod-strony
            array($this, 'render_documents_page') // Funkcja renderująca
        );
        // Dodajemy pod-stronę do zarządzania kontaktami
        add_submenu_page(
            'wpmzf_dashboard',                // Slug strony nadrzędnej
            'Zarządzanie Kontaktami',         // Tytuł strony
            'Kontakty',                       // Nazwa w menu
            'manage_options',                 // Uprawnienia
            'wpmzf_contacts',                 // Slug tej pod-strony
            array($this, 'render_contacts_page') // Funkcja renderująca
        );

        // Rejestrujemy "ukrytą" stronę do widoku pojedynczego kontaktu.
        // `parent_slug` jako null ukrywa ją z menu.
        add_submenu_page(
            null,                             // Brak rodzica w menu
            'Widok Kontaktu',                 // Tytuł strony
            'Widok Kontaktu',                 // Nazwa w menu
            'manage_options',
            'wpmzf_contact_view',             // Slug
            array($this, 'render_single_contact_page') // Funkcja renderująca
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
        // Zbuduj URL do strony widoku pojedynczego kontaktu (teczki)
        $view_link = add_query_arg(
            [
                'page'       => 'wpmzf_contact_view',
                'contact_id' => $item['id'],
            ],
            admin_url('admin.php')
        );

        // Zbuduj link do archiwizacji z zabezpieczeniem nonce
        $archive_nonce = wp_create_nonce('wpmzf_archive_contact_' . $item['id']);
        $archive_link = add_query_arg(
            [
                'page'    => 'wpmzf_contacts',
                'action'  => 'archive',
                'contact' => $item['id'],
                '_wpnonce' => $archive_nonce,
            ],
            admin_url('admin.php')
        );

        // Zdefiniuj akcje dla wiersza
        $actions = [
            'view'    => sprintf('<a href="%s">Otwórz teczkę</a>', esc_url($view_link)),
            'edit'    => sprintf('<a href="%s">Edytuj (WP)</a>', get_edit_post_link($item['id'])),
            'archive' => sprintf('<a href="%s" style="color:#a00;" onclick="return confirm(\'Czy na pewno chcesz zarchiwizować ten kontakt?\')">Archiwizuj</a>', esc_url($archive_link)),
        ];

        // Stwórz główny link dla nazwy kontaktu
        $title = sprintf(
            '<a class="row-title" href="%s"><strong>%s</strong></a>',
            esc_url($view_link),
            esc_html($item['name'])
        );

        // Zwróć tytuł (link) wraz z akcjami
        return $title . $this->row_actions($actions);
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

    public function render_contacts_page()
    {
        // Stwórz instancję i przygotuj dane tabeli
        $contacts_table = new WPMZF_Contacts_List_Table();
        $contacts_table->prepare_items();

        // --- Logika statystyk ---

        $base_url = admin_url('admin.php?page=wpmzf_contacts');

        // --- Statystyka "Wszystkie" ---
        $all_contacts_query = new WP_Query([
            'post_type'      => 'contact',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [
                'relation' => 'OR',
                ['key' => 'contact_status', 'value' => 'Zarchiwizowany', 'compare' => '!='],
                ['key' => 'contact_status', 'compare' => 'NOT EXISTS']
            ]
        ]);
        $all_count = $all_contacts_query->found_posts;

        // --- Statystyka dzienna ---
        $current_day_str = $_GET['stat_day'] ?? current_time('Y-m-d');
        $current_day_dt = new DateTime($current_day_str);
        $prev_day_url = add_query_arg('stat_day', (clone $current_day_dt)->modify('-1 day')->format('Y-m-d'), $base_url);
        $next_day_url = add_query_arg('stat_day', (clone $current_day_dt)->modify('+1 day')->format('Y-m-d'), $base_url);

        $daily_query = new WP_Query([
            'post_type' => 'contact',
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
            'post_type' => 'contact',
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
            'post_type' => 'contact',
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
            <h1 class="wp-heading-inline">Kontakty</h1>
            <a href="#" class="page-title-action">Dodaj nowy kontakt</a>

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
                $contacts_table->display();
                ?>
            </form>
        </div>
    <?php
    }
    public function render_single_contact_page()
    {
        $contact_id = isset($_GET['contact_id']) ? intval($_GET['contact_id']) : 0;
        if (!$contact_id || get_post_type($contact_id) !== 'contact') {
            wp_die('Nieprawidłowy kontakt.');
        }

        $contact_title = get_the_title($contact_id);
        $contact_fields = get_fields($contact_id);

        // Inicjalizujemy zmienne firmy, aby uniknąć błędów
        $company_id = null;
        $company_title = '';

        if (!empty($contact_fields['contact_company'])) {
            // Zakładając, że contact_company przechowuje ID postu firmy
            $company_post = get_post($contact_fields['contact_company']);
            if ($company_post) {
                $company_id = $company_post->ID;
                $company_title = $company_post->post_title;
            }
        }
    ?>
        <style>
            /* Single Contact View Styles */
            .dossier-grid {
                display: grid;
                grid-template-columns: 1fr 400px;
                gap: 20px;
                margin-top: 20px;
            }

            .dossier-main-column,
            .dossier-side-column {
                display: flex;
                flex-direction: column;
                gap: 20px;
            }

            .dossier-box {
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
            }

            .dossier-box h2.dossier-title {
                font-size: 14px;
                padding: 8px 12px;
                margin: 0;
                border-bottom: 1px solid #ccd0d4;
            }

            .dossier-box .dossier-content {
                padding: 12px;
            }

            .dossier-box .dossier-content p {
                margin: 0 0 8px;
                line-height: 1.6;
            }

            .dossier-box .dossier-content p:last-child {
                margin-bottom: 0;
            }

            .dossier-box .dossier-content ul {
                margin: 0;
                padding-left: 20px;
            }

            .dossier-box .dossier-content ul li {
                margin-bottom: 5px;
            }

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

            @media screen and (max-w: 960px) {
                .dossier-grid {
                    grid-template-columns: 1fr;
                }
            }
        </style>

        <div class="wrap">
            <h1><?php echo esc_html($contact_title); ?></h1>

            <div class="nav-tab-wrapper">
                <a href="#" class="nav-tab nav-tab-active">Dane i Aktywności</a>
                <a href="#" class="nav-tab">Zadania</a>
                <a href="#" class="nav-tab">Dokumenty</a>
                <a href="#" class="nav-tab">Płatności</a>
            </div>

            <div class="dossier-grid">
                <div class="dossier-main-column">
                    <div class="dossier-box" id="dossier-basic-data">
                        <h2 class="dossier-title">
                            Dane podstawowe
                            <a href="#" id="edit-basic-data" class="page-title-action" style="float:right; margin-right: 5px; padding: 0px 12px; background-color: #0073aa; color: #fff; border-radius: 4px; text-decoration: none; font-size: 10px; font-weight: 600; border: 1px solid #006799; box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1); min-height: auto;">Edytuj</a>
                        </h2>
                        <div class="dossier-content">
                            <div class="view-mode">
                                <p><strong>Imię i nazwisko:</strong> <span data-field="contact_name"><?php echo esc_html($contact_title); ?></span></p>
                                <p><strong>Stanowisko:</strong> <span data-field="contact_position"><?php echo esc_html($contact_fields['contact_position'] ?? 'Brak'); ?></span></p>
                                <p><strong>Firma:</strong> <span data-field="contact_company">
                                        <?php
                                        if ($company_id) {
                                            printf('<a href="%s">%s</a>', esc_url(get_edit_post_link($company_id)), esc_html(get_the_title($company_id)));
                                        } else {
                                            echo 'Brak';
                                        }
                                        ?>
                                    </span></p>
                                <p><strong>Email:</strong> <a data-field="contact_email" href="mailto:<?php echo esc_attr($contact_fields['contact_email'] ?? ''); ?>"><?php echo esc_html($contact_fields['contact_email'] ?? 'Brak'); ?></a></p>
                                <p><strong>Telefon:</strong> <span data-field="contact_phone"><?php echo esc_html($contact_fields['contact_phone'] ?? 'Brak'); ?></span></p>
                                <p><strong>Adres:</strong> <span data-field="contact_address"><?php
                                $address_group = $contact_fields['contact_address'] ?? [];
                                $address_parts = [
                                    $address_group['street'] ?? '',
                                    $address_group['zip_code'] ?? '',
                                    $address_group['city'] ?? ''
                                ];
                                $address = implode(', ', array_filter($address_parts));
                                echo esc_html($address ?: 'Brak');
                                ?></span></p>
                                <p><strong>Status:</strong> <span data-field="contact_status"><?php echo esc_html($contact_fields['contact_status'] ?? 'Brak'); ?></span></p>
                            </div>
                            <div class="edit-form">
                                <form>
                                    <label for="contact_name">Imię i nazwisko:</label>
                                    <input type="text" id="contact_name" name="contact_name" value="<?php echo esc_attr($contact_title); ?>" required>

                                    <label for="contact_position">Stanowisko:</label>
                                    <input type="text" id="contact_position" name="contact_position" value="<?php echo esc_attr($contact_fields['contact_position'] ?? ''); ?>">

                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="contact_email">Email:</label>
                                            <input type="email" id="contact_email" name="contact_email" value="<?php echo esc_attr($contact_fields['contact_email'] ?? ''); ?>">
                                        </div>
                                        <div class="form-group">
                                            <label for="contact_phone">Telefon:</label>
                                            <input type="text" id="contact_phone" name="contact_phone" value="<?php echo esc_attr($contact_fields['contact_phone'] ?? ''); ?>">
                                        </div>
                                    </div>

                                    <label for="company_search_select">Firma:</label>
                                    <select id="company_search_select" name="contact_company" style="width: 100%;">
                                        <?php if ($company_id && $company_title) : ?>
                                            <option value="<?php echo esc_attr($company_id); ?>" selected="selected"><?php echo esc_html($company_title); ?></option>
                                        <?php endif; ?>
                                    </select>

                                    <label for="contact_street">Ulica i nr:</label>
                                    <input type="text" id="contact_street" name="contact_street" value="<?php echo esc_attr($contact_fields['contact_street'] ?? ''); ?>">

                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="contact_postal_code">Kod pocztowy:</label>
                                            <input type="text" id="contact_postal_code" name="contact_postal_code" value="<?php echo esc_attr($contact_fields['contact_postal_code'] ?? ''); ?>">
                                        </div>
                                        <div class="form-group">
                                            <label for="contact_city">Miasto:</label>
                                            <input type="text" id="contact_city" name="contact_city" value="<?php echo esc_attr($contact_fields['contact_city'] ?? ''); ?>">
                                        </div>
                                    </div>

                                    <label for="contact_status">Status:</label>
                                    <select id="contact_status" name="contact_status">
                                        <option value="active" <?php selected($contact_fields['contact_status'], 'active'); ?>>Aktywny</option>
                                        <option value="inactive" <?php selected($contact_fields['contact_status'], 'inactive'); ?>>Nieaktywny</option>
                                        <option value="archived" <?php selected($contact_fields['contact_status'], 'archived'); ?>>Zarchiwizowany</option>
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
                    <div class="dossier-box">
                        <h2 class="dossier-title">Dokumenty firmy</h2>
                        <div class="dossier-content">
                            <?php
                            if ($company_id) {
                                $docs_query = new WP_Query(['post_type' => ['quote', 'contract'], 'posts_per_page' => -1, 'meta_query' => [['key' => 'quote_company', 'value' => $company_id]]]);
                                if ($docs_query->have_posts()) {
                                    echo '<ul>';
                                    while ($docs_query->have_posts()) {
                                        $docs_query->the_post();
                                        echo '<li><a href="' . get_edit_post_link() . '">' . get_the_title() . '</a> (' . get_post_type_object(get_post_type())->labels->singular_name . ')</li>';
                                    }
                                    echo '</ul>';
                                    wp_reset_postdata();
                                } else {
                                    echo '<p><em>Brak dokumentów dla tej firmy.</em></p>';
                                }
                            } else {
                                echo '<p><em>Brak powiązanej firmy, aby wyświetlić dokumenty.</em></p>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
                <div class="dossier-side-column">
                    <div class="dossier-box">
                        <h2 class="dossier-title">Nowa Aktywność</h2>
                        <div class="dossier-content">
                            <form id="wpmzf-add-activity-form" method="post" enctype="multipart/form-data">
                                <?php wp_nonce_field('wpmzf_contact_view_nonce', 'wpmzf_security'); ?>
                                <input type="hidden" name="contact_id" value="<?php echo esc_attr($contact_id); ?>">

                                <input type="file" id="wpmzf-activity-files-input" name="activity_files[]" multiple style="display: none;">

                                <div id="wpmzf-activity-main-editor">
                                    <textarea name="content" id="wpmzf-activity-content" placeholder="Dodaj notatkę, opisz spotkanie..." required></textarea>
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
                                <div id="wpmzf-attachments-preview"></div>
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
            </div>
        </div>

<?php
    }
}
