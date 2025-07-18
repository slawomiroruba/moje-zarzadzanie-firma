<?php

/**
 * Główna klasa administracji pluginu
 */
class WPMZF_Admin_Manager
{
    /**
     * Zarządca menu
     */
    private $menu_manager;

    /**
     * Konstruktor
     */
    public function __construct()
    {
        $this->load_dependencies();
        $this->init_admin();
    }

    /**
     * Ładuje zależności
     */
    private function load_dependencies()
    {
        // Załaduj zarządcę menu
        require_once dirname(__FILE__) . '/class-admin-menu-manager.php';

        // Załaduj inne istniejące klasy admin jeśli są potrzebne
        $admin_classes = array(
            'class-wpmzf-admin-columns.php',
            'class-wpmzf-meta-boxes.php',
            'class-wpmzf-kanban-page.php',
            'class-wpmzf-user-email-settings.php',
            'class-wpmzf-global-search-handler.php',
            'class-wpmzf-debug-admin-page.php'
        );

        foreach ($admin_classes as $class_file) {
            $file_path = dirname(__FILE__) . '/../admin/' . $class_file;
            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }

        // Załaduj komponenty tabeli
        $table_components = array(
            'components/table/class-wpmzf-persons-list-table.php',
            'components/table/class-wpmzf-companies-list-table.php',
            'components/table/class-wpmzf-documents-list-table.php'
        );

        foreach ($table_components as $component_file) {
            $file_path = dirname(__FILE__) . '/../admin/' . $component_file;
            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }
    }

    /**
     * Inicjalizuje administrację
     */
    private function init_admin()
    {
        // Inicjalizuj zarządcę menu
        $this->menu_manager = new WPMZF_Admin_Menu_Manager();

        // Inicjalizuj inne komponenty admin
        $this->init_admin_components();

        // Dodaj hooki
        add_action('admin_init', array($this, 'handle_admin_actions'));
        add_action('admin_notices', array($this, 'show_admin_notices'));
    }

    /**
     * Inicjalizuje komponenty administracyjne
     */
    private function init_admin_components()
    {
        // Inicjalizuj istniejące klasy jeśli istnieją
        if (class_exists('WPMZF_Admin_Columns')) {
            new WPMZF_Admin_Columns();
        }

        if (class_exists('WPMZF_Meta_Boxes')) {
            new WPMZF_Meta_Boxes();
        }

        if (class_exists('WPMZF_Kanban_Page')) {
            new WPMZF_Kanban_Page();
        }

        if (class_exists('WPMZF_User_Email_Settings')) {
            new WPMZF_User_Email_Settings();
        }

        if (class_exists('WPMZF_Global_Search_Handler')) {
            new WPMZF_Global_Search_Handler();
        }

        if (class_exists('WPMZF_Debug_Admin_Page')) {
            WPMZF_Debug_Admin_Page::init();
        }

        if (class_exists('WPMZF_Custom_Columns_Service')) {
            new WPMZF_Custom_Columns_Service();
        }
    }

    /**
     * Obsługuje akcje administracyjne
     */
    public function handle_admin_actions()
    {
        // Tutaj można dodać ogólne akcje administracyjne
        // Poszczególne strony mają swoje własne obsługi akcji
    }

    /**
     * Wyświetla powiadomienia administracyjne
     */
    public function show_admin_notices()
    {
        // Sprawdź czy jest komunikat do wyświetlenia
        if (isset($_GET['message'])) {
            $message = sanitize_text_field($_GET['message']);
            
            switch ($message) {
                case 'updated':
                    echo '<div class="notice notice-success is-dismissible"><p>Dane zostały zaktualizowane.</p></div>';
                    break;
                case 'error':
                    echo '<div class="notice notice-error is-dismissible"><p>Wystąpił błąd podczas przetwarzania.</p></div>';
                    break;
                case 'deleted':
                    echo '<div class="notice notice-success is-dismissible"><p>Element został usunięty.</p></div>';
                    break;
            }
        }
    }

    /**
     * Pobiera zarządcę menu
     */
    public function get_menu_manager()
    {
        return $this->menu_manager;
    }

    /**
     * Pobiera stronę po kluczu
     */
    public function get_page($key)
    {
        return $this->menu_manager ? $this->menu_manager->get_page($key) : null;
    }
}
