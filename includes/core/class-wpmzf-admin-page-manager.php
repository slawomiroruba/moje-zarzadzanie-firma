<?php
class WPMZF_Admin_Page_Manager {

    public function __construct() {
        // Tworzy główną pozycję w menu
        add_action('admin_menu', array($this, 'add_plugin_admin_menu'));

        // Ładuje klasy stron z modułów
        $this->load_module_pages();
    }

    public function add_plugin_admin_menu() {
        // Główna strona wtyczki - "Kokpit"
        add_menu_page(
            'Kokpit Firmy',          // Tytuł strony
            'Kokpit Firmy',          // Tytuł w menu
            'manage_options',        // Wymagane uprawnienia
            'wpmzf_dashboard',       // Slug menu
            array($this, 'render_dashboard_page'), // Funkcja renderująca
            'dashicons-business',    // Ikona
            6                        // Pozycja w menu
        );
    }

    public function render_dashboard_page() {
        // Tutaj będzie zawartość głównego kokpitu
        echo '<div class="wrap"><h1>Witaj w Kokpicie Zarządzania Firmą!</h1><p>Wybierz jedną z opcji z menu po lewej stronie.</p></div>';
    }

    private function load_module_pages() {
        // Automatyczne ładowanie wszystkich plików admin-page z modułów
        $modules_dir = WPMZF_PLUGIN_PATH . 'includes/modules/';
        foreach (glob($modules_dir . '*/class-*-admin-page.php') as $file) {
            require_once $file;
            $class_name = $this->get_class_name_from_file($file);
            if (class_exists($class_name)) {
                new $class_name(); // Inicjujemy klasę, aby podpięła swoje strony
            }
        }
    }

    private function get_class_name_from_file($file) {
        // Wyciągamy nazwę klasy na podstawie nazwy pliku
        $file_name = basename($file, '.php');
        $class_name = str_replace('-', '_', $file_name);
        return ucfirst($class_name);
    }
}