<?php

class WPMZF_Admin_Pages {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_plugin_admin_menu'));
    }

    /**
     * Dodaje strony pluginu do menu w panelu admina.
     */
    public function add_plugin_admin_menu() {
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
    }

    /**
     * Renderuje zawartość głównego kokpitu.
     */
    public function render_dashboard_page() {
        echo '<div class="wrap"><h1>Witaj w kokpicie Twojej firmy!</h1><p>Wybierz jedną z opcji z menu po lewej stronie.</p></div>';
    }

    /**
     * Renderuje zawartość strony do zarządzania dokumentami.
     */
    public function render_documents_page() {
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
}