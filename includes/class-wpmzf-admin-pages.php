<?php

class WPMZF_Admin_Pages
{

    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_plugin_admin_menu'));
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
        // Stwórz instancję i przygotuj dane
        $contacts_table = new WPMZF_Contacts_List_Table();
        $contacts_table->prepare_items();
    ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Kontakty</h1>
            <a href="#" class="page-title-action">Dodaj nowy kontakt</a>

            <div id="wpmzf-stats-panel" style="margin: 20px 0; display: flex; gap: 15px;">
                <div class="stat-box"><strong>Wszystkie:</strong> 150</div>
                <div class="stat-box"><strong>Dziś:</strong> 5</div>
                <div class="stat-box"><strong>Ten tydzień:</strong> 25</div>
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
        // Pobierz wszystkie pola ACF dla tego kontaktu
        $contact_fields = get_fields($contact_id);
        // Pobierz powiązaną firmę
        $company_id = isset($contact_fields['contact_company']) && !empty($contact_fields['contact_company']) ? $contact_fields['contact_company'][0] : null;

    ?>
        <div class="wrap">
            <h1><?php echo esc_html($contact_title); ?></h1>

            <div class="nav-tab-wrapper">
                <a href="#" class="nav-tab nav-tab-active">Dane i Aktywności</a>
                <a href="#" class="nav-tab">Zadania</a>
                <a href="#" class="nav-tab">Dokumenty</a>
                <a href="#" class="nav-tab">Płatności</a>
            </div>

            <div id="contact-dossier-content" style="display: flex; gap: 20px; margin-top: 20px;">
                <div class="dossier-left-column" style="flex: 1;">
                    <h2>Dane podstawowe</h2>
                    <p><strong>Stanowisko:</strong> <?php echo esc_html($contact_fields['contact_position']); ?></p>
                    <p><strong>Email:</strong> <a href="mailto:<?php echo esc_attr($contact_fields['contact_email']); ?>"><?php echo esc_html($contact_fields['contact_email']); ?></a></p>
                    <p><strong>Telefon:</strong> <?php echo esc_html($contact_fields['contact_phone']); ?></p>
                    <p><strong>Status:</strong> <?php echo esc_html($contact_fields['contact_status']); ?></p>

                    <h2>Powiązana firma</h2>
                    <?php
                    if ($company_id) {
                        $company_title = get_the_title($company_id);
                        $company_link = get_edit_post_link($company_id);
                        echo '<p><a href="' . esc_url($company_link) . '">' . esc_html($company_title) . '</a></p>';
                    } else {
                        echo '<p><em>Brak powiązanej firmy.</em></p>';
                    }
                    ?>

                    <h2>Dokumenty firmy</h2>
                    <?php
                    if ($company_id) {
                        $args = [
                            'post_type' => ['quote', 'contract'], // Oferty i umowy
                            'posts_per_page' => -1,
                            'meta_query' => [
                                'relation' => 'OR',
                                [
                                    'key' => 'quote_company', // Pole relacji w ofertach
                                    'value' => $company_id,
                                    'compare' => '=',
                                ],
                                [
                                    'key' => 'project_company', // Pole relacji w projektach (jeśli umowy są z nimi powiązane)
                                    'value' => $company_id,
                                    'compare' => '=',
                                ]
                                // Można dodać kolejne warunki dla innych typów dokumentów
                            ]
                        ];
                        $documents = new WP_Query($args);
                        if ($documents->have_posts()) {
                            echo '<ul>';
                            while ($documents->have_posts()) {
                                $documents->the_post();
                                echo '<li><a href="' . get_edit_post_link() . '">' . get_the_title() . '</a> (' . get_post_type_object(get_post_type())->labels->singular_name . ')</li>';
                            }
                            echo '</ul>';
                            wp_reset_postdata();
                        } else {
                            echo '<p><em>Brak dokumentów dla tej firmy.</em></p>';
                        }
                    }
                    ?>

                </div>
                <div class="dossier-right-column">
                    <h2>Historia Aktywności</h2>

                    <div id="add-activity-form">
                        <textarea placeholder="Dodaj notatkę, mail, opisz spotkanie..."></textarea>
                        <button>Dodaj aktywność</button>
                    </div>

                    <ul id="activity-timeline">
                        <li><strong>20.06.2025:</strong> Odbyto spotkanie, ustalono warunki.</li>
                        <li><strong>18.06.2025:</strong> Wysłano e-mail z podsumowaniem rozmowy.</li>
                    </ul>
                </div>
            </div>
        </div>
<?php
    }
}
