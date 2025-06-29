<?php
/**
 * Plugin Name:       Moje Zarządzanie Firmą
 * Description:       Dedykowany plugin do zarządzania firmą. Blokuje dostęp do frontendu dla niezalogowanych.
 * Version:           1.0.0
 * Author:            Twoje Imię i Nazwisko
 */

// Bezpośrednie wywołanie pliku jest zabronione.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Definicja stałej ze ścieżką do pluginu.
define( 'WPMZF_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

/**
 * Funkcja uruchamiana podczas aktywacji pluginu.
 */
function activate_wpmzf_plugin() {
    require_once WPMZF_PLUGIN_PATH . 'includes/class-wpmzf-activator.php';
    WPMZF_Activator::activate();
}
register_activation_hook( __FILE__, 'activate_wpmzf_plugin' );

/**
 * Główna klasa pluginu.
 */
final class WPMZF_Plugin {

    private static $instance;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Konstruktor jest prywatny, aby zapobiec tworzeniu nowych instancji.
     */
    private function __construct() {
        add_action( 'plugins_loaded', array( $this, 'init' ) );
    }

    /**
     * Inicjalizacja pluginu.
     */
    public function init() {
        $this->load_dependencies();
        $this->init_components();
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
    }

    /**
     * Ładuje wszystkie pliki z klasami.
     */
    private function load_dependencies() {
        require_once WPMZF_PLUGIN_PATH . 'includes/class-wpmzf-access-control.php'; // Kontrola dostępu
        require_once WPMZF_PLUGIN_PATH . 'includes/class-wpmzf-cpts.php';             // Typy treści
        require_once WPMZF_PLUGIN_PATH . 'includes/class-wpmzf-admin-columns.php';    // Kolumny w adminie
        require_once WPMZF_PLUGIN_PATH . 'includes/class-wpmzf-meta-boxes.php';       // Meta boxy
        require_once WPMZF_PLUGIN_PATH . 'includes/class-wpmzf-admin-pages.php';      // Strony w adminie
        require_once WPMZF_PLUGIN_PATH . 'includes/class-wpmzf-documents-list-table.php'; // Tabela
        require_once WPMZF_PLUGIN_PATH . 'includes/class-wpmzf-acf-fields.php'; // Pola ACF
        require_once WPMZF_PLUGIN_PATH . 'includes/class-wpmzf-contacts-list-table.php'; // Tabela kontaktów
        require_once WPMZF_PLUGIN_PATH . 'includes/class-wpmzf-ajax-handler.php';
    }

    /**
     * Inicjalizuje wszystkie komponenty pluginu.
     */
    private function init_components() {
        new WPMZF_Access_Control();
        new WPMZF_CPTs();
        // new WPMZF_Admin_Columns();
        new WPMZF_Meta_Boxes();
        new WPMZF_Admin_Pages();
        if ( class_exists('ACF') ) { // Uruchom klasę tylko jeśli ACF jest aktywne
            new WPMZF_ACF_Fields();
        }
        new WPMZF_Ajax_Handler();
    }

    /**
     * Ładuje skrypty i style tylko na stronach wtyczki w panelu admina.
     *
     * @param string $hook Nazwa haka bieżącej strony.
     */
    public function admin_enqueue_scripts($hook) {
        // Sprawdzamy, czy jesteśmy na stronie widoku pojedynczego kontaktu
        // 'toplevel_page_wpmzf_dashboard' to główna strona, a my jesteśmy na ukrytej podstronie,
        // więc musimy sprawdzić parametr 'page'.
        if (isset($_GET['page']) && $_GET['page'] === 'wpmzf_contact_view') {
            
            // Dodaj ten styl z wersją opartą na filemtime
            $css_file = plugin_dir_path(__FILE__) . 'assets/css/admin-contact-view.css';
            wp_enqueue_style(
                'wpmzf-contact-view-css',
                plugin_dir_url(__FILE__) . 'assets/css/admin-contact-view.css',
                array(),
                filemtime( $css_file )
            );

            // Dodaj ten skrypt z wersją opartą na filemtime
            $js_file = plugin_dir_path(__FILE__) . 'assets/js/admin-contact-view.js';
            wp_enqueue_script(
                'wpmzf-contact-view-js',
                plugin_dir_url(__FILE__) . 'assets/js/admin-contact-view.js',
                array( 'jquery' ),
                filemtime( $js_file ),
                true
            );
        }
    }
}

/**
 * Inicjalizacja pluginu.
 */
function wpmzf_run_plugin() {
    return WPMZF_Plugin::get_instance();
}
wpmzf_run_plugin();