<?php

/**
 * Plugin Name:       Moje Zarządzanie Firmą
 * Description:       Dedykowany plugin do zarządzania firmą. Blokuje dostęp do frontendu dla niezalogowanych.
 * Version:           1.0.0
 * Author:            Twoje Imię i Nazwisko
 */

// Bezpośrednie wywołanie pliku jest zabronione.
if (! defined('WPINC')) {
    die;
}

// Definicja stałej ze ścieżką do pluginu.
define('WPMZF_PLUGIN_PATH', plugin_dir_path(__FILE__));

/**
 * Funkcja uruchamiana podczas aktywacji pluginu.
 */
function activate_wpmzf_plugin()
{
    require_once WPMZF_PLUGIN_PATH . 'includes/class-wpmzf-activator.php';
    WPMZF_Activator::activate();
}
register_activation_hook(__FILE__, 'activate_wpmzf_plugin');

/**
 * Główna klasa pluginu.
 */
final class WPMZF_Plugin
{

    private static $instance;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Konstruktor jest prywatny, aby zapobiec tworzeniu nowych instancji.
     */
    private function __construct()
    {
        add_action('plugins_loaded', array($this, 'init'));
    }

    /**
     * Inicjalizacja pluginu.
     */
    public function init()
    {
        $this->load_dependencies();

        $this->init_components();
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));

        // Integracja z Admin Columns Pro zostanie obsłużona przez hook 'ac/services' na końcu pliku
        // Dodajemy filtry tylko wtedy, gdy jesteśmy w panelu admina
        if (is_admin()) {
            add_action('admin_menu', array($this, 'change_comments_meta_box_title'));
            add_filter('comment_form_defaults', array($this, 'custom_comment_form_defaults'));
            add_filter('gettext', array($this, 'change_comment_strings'), 20, 3);
        }
    }

    /**
     * Ładuje wszystkie pliki z klasami.
     */
    private function load_dependencies()
    {

        require_once WPMZF_PLUGIN_PATH . 'includes/abstracts/class-wpmzf-abstract-cpt.php';
        require_once WPMZF_PLUGIN_PATH . 'includes/objects/class-wpmzf-contact.php';


        // ---

        require_once WPMZF_PLUGIN_PATH . 'includes/class-wpmzf-access-control.php'; // Kontrola dostępu
        require_once WPMZF_PLUGIN_PATH . 'includes/class-wpmzf-cpts.php';             // Typy treści
        require_once WPMZF_PLUGIN_PATH . 'includes/class-wpmzf-admin-columns.php';    // Kolumny w adminie
        require_once WPMZF_PLUGIN_PATH . 'includes/class-wpmzf-meta-boxes.php';       // Meta boxy
        require_once WPMZF_PLUGIN_PATH . 'includes/class-wpmzf-admin-pages.php';      // Strony w adminie
        require_once WPMZF_PLUGIN_PATH . 'includes/class-wpmzf-documents-list-table.php'; // Tabela
        require_once WPMZF_PLUGIN_PATH . 'includes/class-wpmzf-acf-fields.php'; // Pola ACF
        require_once WPMZF_PLUGIN_PATH . 'includes/class-wpmzf-persons-list-table.php';
        require_once WPMZF_PLUGIN_PATH . 'includes/class-wpmzf-ajax-handler.php';
    }

    /**
     * Inicjalizuje wszystkie komponenty pluginu.
     */
    private function init_components()
    {
        new WPMZF_Access_Control();
        new WPMZF_CPTs();
        new WPMZF_Contact();
        // new WPMZF_Admin_Columns();
        new WPMZF_Meta_Boxes();
        new WPMZF_Admin_Pages();
        if (class_exists('ACF')) { // Uruchom klasę tylko jeśli ACF jest aktywne
            new WPMZF_ACF_Fields();
        }
        new WPMZF_Ajax_Handler();
    }

    /**
     * Ładuje skrypty i style tylko na stronach wtyczki w panelu admina.
     *
     * @param string $hook Nazwa haka bieżącej strony.
     */
    public function admin_enqueue_scripts($hook)
    {
        // Sprawdzamy, czy jesteśmy na stronie widoku pojedynczej osoby
        // 'toplevel_page_wpmzf_dashboard' to główna strona, a my jesteśmy na ukrytej podstronie,
        // więc musimy sprawdzić parametr 'page'.
        if (isset($_GET['page']) && $_GET['page'] === 'wpmzf_person_view') {

            // Dodaj ten styl z wersją opartą na filemtime
            $css_file = plugin_dir_path(__FILE__) . 'assets/css/admin-person-view.css';
            wp_enqueue_style(
                'wpmzf-person-view-css',
                plugin_dir_url(__FILE__) . 'assets/css/admin-person-view.css',
                array(),
                filemtime($css_file)
            );

            // Dodaj ten skrypt z wersją opartą na filemtime
            $js_file = plugin_dir_path(__FILE__) . 'assets/js/admin-person-view.js';
            wp_enqueue_script(
                'wpmzf-person-view-js',
                plugin_dir_url(__FILE__) . 'assets/js/admin-person-view.js',
                array('jquery'),
                filemtime($js_file),
                true
            );
        }
    }

    /**
     * Zmienia tytuł głównego okna (meta boxa) z komentarzami.
     */
    public function change_comments_meta_box_title()
    {
        // Sprawdzamy, czy działamy na typie posta 'pracownik'
        $current_screen = get_current_screen();
        if ($current_screen && $current_screen->post_type === 'employee') {
            remove_meta_box('commentstatusdiv', 'employee', 'normal');
            remove_meta_box('commentsdiv', 'employee', 'normal');
            add_meta_box('commentsdiv', 'Historia kontaktu / Notatki', 'post_comment_meta_box', 'employee', 'normal', 'high');
        }
    }

    /**

     * Modyfikuje domyślne pola i etykiety formularza dodawania notatki.
     *
     * @param array $defaults Domyślne ustawienia formularza.
     * @return array Zmodyfikowane ustawienia.
     */
    public function custom_comment_form_defaults($defaults)
    {
        // Działamy tylko na ekranie edycji pracownika
        if (get_current_screen()->post_type === 'employee') {
            $defaults['title_reply'] = 'Dodaj nową notatkę';
            $defaults['label_submit'] = 'Dodaj notatkę';
            $defaults['comment_field'] = '<p class="comment-form-comment"><textarea id="comment" name="comment" cols="45" rows="5" aria-required="true" placeholder="Wpisz treść notatki, transkrypcję rozmowy, treść maila..."></textarea></p>';
            $defaults['comment_notes_before'] = ''; // Usuwa informację o dozwolonych tagach HTML
        }
        return $defaults;
    }

    /**
     * Zmienia pozostałe, trudniej dostępne teksty systemowe.
     *
     * @param string $translated_text Przetłumaczony tekst.
     * @param string $text Oryginalny tekst.
     * @param string $domain Domena tłumaczenia.
     * @return string Zmodyfikowany tekst.
     */
    public function change_comment_strings($translated_text, $text)
    {
        // Działamy tylko w panelu admina na stronie edycji pracownika
        $current_screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (is_admin() && $current_screen && $current_screen->post_type === 'employee') {
            switch ($text) {
                case 'Comments': // Angielska wersja, na wszelki wypadek
                case 'Komentarze':
                    $translated_text = 'Notatki';
                    break;
                case 'Comment': // Angielska wersja
                case 'Komentarz':
                    $translated_text = 'Notatka';
                    break;
                case 'No comments yet.': // Angielska wersja
                case 'Brak komentarzy.':
                    $translated_text = 'Brak notatek. Dodaj pierwszą!';
                    break;
            }
        }
        return $translated_text;
    }
}

/**
 * Inicjalizacja pluginu.
 */
function wpmzf_run_plugin()
{

    return WPMZF_Plugin::get_instance();
}
wpmzf_run_plugin();


add_shortcode('current_year', function () {
    return date('Y');
});

// Plik: moje-zarzadzanie-firma.php (na samym końcu)

add_filter( 'site_transient_update_plugins', 'wpmzf_block_acp_updates' );

function wpmzf_block_acp_updates( $transient ) {
    // Sprawdzamy, czy w ogóle są jakieś informacje o aktualizacjach
    if ( ! isset( $transient->response ) ) {
        return $transient;
    }

    // Ścieżka do głównego pliku wtyczki Admin Columns Pro
    $plugin_slug = 'admin-columns-pro/admin-columns-pro.php';

    // Jeśli wtyczka jest na liście do aktualizacji, usuwamy ją
    if ( isset( $transient->response[$plugin_slug] ) ) {
        unset( $transient->response[$plugin_slug] );
    }

    return $transient;
}

// Rejestracja kolumn Admin Columns Pro
add_action('ac/services', function ( AC\Container $container ) {
    // Sprawdzamy, czy usługa, której potrzebujemy, jest dostępna
    if ( ! method_exists($container, 'has') || ! $container->has('services.common') ) {
        return;
    }

    // Dołączamy nasz plik z definicjami kolumn
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpmzf-custom-columns.php';
    
    // Rejestrujemy naszą główną klasę-serwis, a Admin Columns zajmie się resztą
    $container->get('services.common')->add( new WPMZF_Custom_Columns_Service() );

}, 10, 1);

function my_custom_function( $post_id ) {
    // Tutaj możesz umieścić swoją logikę, która zwraca dane do wyświetlenia
    // Na przykład, pobieranie niestandardowego pola lub wykonanie zapytania
    $custom_data = get_post_meta( $post_id, 'my_custom_field', true );
    
    // Zwracamy dane lub komunikat, jeśli nie ma danych
    return get_the_ID();
}

// Dodaj kolumnę "Custom Data" do CPT "person"
add_filter( 'manage_person_posts_columns', function( $columns ) {
    // Możesz zmienić pozycję dodając przed lub po istniejących kluczach
    $columns['custom_data'] = __( 'Custom Data', 'moje-zarzadzanie-firma' );
    return $columns;
} );

// Wypełnij kolumnę danymi zwracanymi przez swoją funkcję
add_action( 'manage_person_posts_custom_column', function( $column, $post_id ) {
    if ( 'custom_data' === $column ) {
        // my_custom_function() powinna przyjmować ID wpisu i zwracać ciąg do wyświetlenia
        echo esc_html( my_custom_function( $post_id ) );
    }
}, 10, 2 );

// (Opcjonalnie) Uczyń kolumnę sortowalną
add_filter( 'manage_edit-person_sortable_columns', function( $columns ) {
    $columns['custom_data'] = 'custom_data';
    return $columns;
} );