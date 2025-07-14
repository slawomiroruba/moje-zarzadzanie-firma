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
define('WPMZF_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Funkcja uruchamiana podczas aktywacji pluginu.
 */
function activate_wpmzf_plugin()
{
    require_once WPMZF_PLUGIN_PATH . 'includes/core/class-wpmzf-activator.php';
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
        // Core
        require_once WPMZF_PLUGIN_PATH . 'includes/core/class-wpmzf-loader.php';
        require_once WPMZF_PLUGIN_PATH . 'includes/core/class-wpmzf-activator.php';
        require_once WPMZF_PLUGIN_PATH . 'includes/core/class-wpmzf-logger.php';
        require_once WPMZF_PLUGIN_PATH . 'includes/core/class-wpmzf-file-validator.php';
        require_once WPMZF_PLUGIN_PATH . 'includes/core/class-wpmzf-backup-manager.php';
        require_once WPMZF_PLUGIN_PATH . 'includes/core/class-wpmzf-rate-limiter.php';
        require_once WPMZF_PLUGIN_PATH . 'includes/core/class-wpmzf-cache-manager.php';
        require_once WPMZF_PLUGIN_PATH . 'includes/core/class-wpmzf-performance-monitor.php';
        require_once WPMZF_PLUGIN_PATH . 'includes/core/class-wpmzf-error-handler.php';
        require_once WPMZF_PLUGIN_PATH . 'includes/core/class-wpmzf-database-optimizer.php';
        require_once WPMZF_PLUGIN_PATH . 'includes/core/class-wpmzf-cron-manager.php';
        require_once WPMZF_PLUGIN_PATH . 'includes/core/class-wpmzf-email-database.php';

        // Abstracts
        // require_once WPMZF_PLUGIN_PATH . 'includes/abstracts/class-wpmzf-abstract-cpt.php'; // Przestarzała klasa

        // Data
        require_once WPMZF_PLUGIN_PATH . 'includes/data/class-wpmzf-post-types.php';
        require_once WPMZF_PLUGIN_PATH . 'includes/data/class-wpmzf-taxonomies.php';

        // Models
        // require_once WPMZF_PLUGIN_PATH . 'includes/models/class-wpmzf-contact.php'; // Przestarzały model
        require_once WPMZF_PLUGIN_PATH . 'includes/models/class-wpmzf-user.php';
        require_once WPMZF_PLUGIN_PATH . 'includes/models/class-wpmzf-company.php';
        require_once WPMZF_PLUGIN_PATH . 'includes/models/class-wpmzf-person.php';
        require_once WPMZF_PLUGIN_PATH . 'includes/models/class-wpmzf-project.php';
        require_once WPMZF_PLUGIN_PATH . 'includes/models/class-wpmzf-time-entry.php';
        require_once WPMZF_PLUGIN_PATH . 'includes/models/class-wpmzf-activity.php';
        require_once WPMZF_PLUGIN_PATH . 'includes/models/class-wpmzf-important-link.php';
        require_once WPMZF_PLUGIN_PATH . 'includes/models/class-wpmzf-opportunity.php';

        // Repositories
        require_once WPMZF_PLUGIN_PATH . 'includes/repositories/class-wpmzf-user-repository.php';
        require_once WPMZF_PLUGIN_PATH . 'includes/repositories/class-wpmzf-important-link-repository.php';

        // Services
        require_once WPMZF_PLUGIN_PATH . 'includes/services/class-wpmzf-user-service.php';
        require_once WPMZF_PLUGIN_PATH . 'includes/services/class-wpmzf-time-tracking.php';
        require_once WPMZF_PLUGIN_PATH . 'includes/services/class-wpmzf-reports.php';
        require_once WPMZF_PLUGIN_PATH . 'includes/services/class-wpmzf-contact-helper.php';
        require_once WPMZF_PLUGIN_PATH . 'includes/services/class-wpmzf-ajax-handler.php';
        require_once WPMZF_PLUGIN_PATH . 'includes/services/class-wpmzf-employee-helper.php';
        require_once WPMZF_PLUGIN_PATH . 'includes/services/class-wpmzf-branding-service.php';
        require_once WPMZF_PLUGIN_PATH . 'includes/services/class-wpmzf-transcription-service.php';
        require_once WPMZF_PLUGIN_PATH . 'includes/services/class-wpmzf-audio-transcription.php';
        require_once WPMZF_PLUGIN_PATH . 'includes/services/class-wpmzf-opportunity-service.php';
        require_once WPMZF_PLUGIN_PATH . 'includes/services/class-wpmzf-email-service.php';

        // Controllers
        require_once WPMZF_PLUGIN_PATH . 'includes/controllers/class-wpmzf-user-controller.php';

        // Admin
        require_once WPMZF_PLUGIN_PATH . 'includes/admin/class-wpmzf-admin.php';
        require_once WPMZF_PLUGIN_PATH . 'includes/admin/class-wpmzf-admin-pages.php';
        require_once WPMZF_PLUGIN_PATH . 'includes/admin/class-wpmzf-debug-admin-page.php';
        require_once WPMZF_PLUGIN_PATH . 'includes/admin/class-wpmzf-admin-columns.php';
        require_once WPMZF_PLUGIN_PATH . 'includes/admin/class-wpmzf-custom-columns.php';
        require_once WPMZF_PLUGIN_PATH . 'includes/admin/class-wpmzf-meta-boxes.php';
        require_once WPMZF_PLUGIN_PATH . 'includes/admin/class-wpmzf-kanban-page.php';
        require_once WPMZF_PLUGIN_PATH . 'includes/admin/components/class-wpmzf-navbar.php';
        require_once WPMZF_PLUGIN_PATH . 'includes/admin/components/class-wpmzf-view-helper.php';
        require_once WPMZF_PLUGIN_PATH . 'includes/admin/components/card/simple-card.php';
        require_once WPMZF_PLUGIN_PATH . 'includes/admin/components/table/class-wpmzf-documents-list-table.php';
        require_once WPMZF_PLUGIN_PATH . 'includes/admin/components/table/class-wpmzf-persons-list-table.php';
        require_once WPMZF_PLUGIN_PATH . 'includes/admin/components/table/class-wpmzf-companies-list-table.php';
        require_once WPMZF_PLUGIN_PATH . 'includes/admin/class-wpmzf-user-email-settings.php';

        // Data
        require_once WPMZF_PLUGIN_PATH . 'includes/data/class-wpmzf-acf-fields.php';

        // Utils
        require_once WPMZF_PLUGIN_PATH . 'includes/utils/class-wpmzf-activity-migration.php';

        // Security and access control
        require_once WPMZF_PLUGIN_PATH . 'includes/core/class-wpmzf-access-control.php';
        require_once WPMZF_PLUGIN_PATH . 'includes/core/class-wpmzf-frontend-blocker.php';
    }

    /**
     * Inicjalizuje wszystkie komponenty pluginu.
     */
    private function init_components()
    {
        // Initialize core systems first
        WPMZF_Error_Handler::init();
        WPMZF_Cache_Manager::init();
        WPMZF_Performance_Monitor::init();
        
        // Core components
        new WPMZF_Loader();
        new WPMZF_Cron_Manager();
        new WPMZF_Post_Types();
        new WPMZF_Taxonomies();

        // Services
        new WPMZF_Time_Tracking();
        new WPMZF_Reports();
        WPMZF_Branding_Service::init();
        new WPMZF_Transcription_Service();
        new WPMZF_Opportunity_Service();

        // Email System
        new WPMZF_User_Email_Settings();
        new WPMZF_Email_Service();
        WPMZF_Email_Database::create_tables(); // Tworzymy tabele przy inicjalizacji

        // Admin
        // new WPMZF_Admin(); // WYŁĄCZONE - duplikuje funkcjonalność WPMZF_Admin_Pages
        new WPMZF_Admin_Pages(); 
        WPMZF_Debug_Admin_Page::init();
        new WPMZF_Custom_Columns_Service();
        new WPMZF_Kanban_Page();
        // new WPMZF_Navbar(); // WYŁĄCZONE - navbar jest renderowany przez WPMZF_View_Helper::render_complete_header()
        
        // Inicjalizuj WPMZF_View_Helper
        WPMZF_View_Helper::init();

        // REST API Controllers
        add_action('rest_api_init', function() {
            $user_controller = new WPMZF_User_Controller();
            $user_controller->register_routes();
        });

        // Security and access control
        new WPMZF_Frontend_Blocker();
        
        // Legacy components
        new WPMZF_Meta_Boxes();
        new WPMZF_Ajax_Handler();
        
        if (class_exists('ACF')) {
            new WPMZF_ACF_Fields();
        }

        // Schedule automatic backups
        if (!wp_next_scheduled('wpmzf_daily_backup')) {
            wp_schedule_event(time(), 'daily', 'wpmzf_daily_backup');
        }
        
        // Hook for automatic backup
        add_action('wpmzf_daily_backup', array($this, 'create_daily_backup'));
        
        // Sprawdź czy potrzeba utworzyć tabele
        if (get_option('wpmzf_need_create_tables')) {
            WPMZF_Activator::create_tables();
        }
    }

    /**
     * Ładuje skrypty i style tylko na stronach wtyczki w panelu admina.
     *
     * @param string $hook Nazwa haka bieżącej strony.
     */
    public function admin_enqueue_scripts($hook)
    {
        // Ładuj skrypt dyktowania na WSZYSTKICH stronach admina
        wp_enqueue_style(
            'wpmzf-voice-dictation-style',
            plugin_dir_url(__FILE__) . 'assets/css/voice-dictation.css',
            array(),
            filemtime(plugin_dir_path(__FILE__) . 'assets/css/voice-dictation.css')
        );

        // Ładuj skrypty walidacji na wszystkich stronach admina
        wp_enqueue_script(
            'wpmzf-validation-script',
            plugin_dir_url(__FILE__) . 'assets/js/admin/validation.js',
            array(),
            filemtime(plugin_dir_path(__FILE__) . 'assets/js/admin/validation.js'),
            true
        );

        wp_enqueue_script(
            'wpmzf-voice-dictation-script',
            plugin_dir_url(__FILE__) . 'assets/js/admin/voice-dictation.js',
            array('jquery'),
            filemtime(plugin_dir_path(__FILE__) . 'assets/js/admin/voice-dictation.js'),
            true
        );

        // Upewnij się, że TinyMCE jest załadowany, aby event `tinymce-init` działał
        wp_enqueue_editor();

        // Ładuj skrypt kontaktów na wszystkich stronach edycji postów w adminie
        if (in_array($hook, ['post.php', 'post-new.php', 'toplevel_page_wpmzf_dashboard'])) {
            $contacts_js_file = plugin_dir_path(__FILE__) . 'assets/js/admin/contacts.js';
            if (file_exists($contacts_js_file)) {
                wp_enqueue_script(
                    'wpmzf-contacts-js',
                    plugin_dir_url(__FILE__) . 'assets/js/admin/contacts.js',
                    array('jquery'),
                    filemtime($contacts_js_file),
                    true
                );
            }
        }
        
        // Ładuj skrypty Luna CRM na stronach wtyczki
        if (strpos($hook, 'luna-crm') !== false) {
            // Style
            wp_enqueue_style(
                'luna-crm-admin',
                plugin_dir_url(__FILE__) . 'assets/css/admin-styles.css',
                array(),
                filemtime(plugin_dir_path(__FILE__) . 'assets/css/admin-styles.css')
            );
            
            // Dashboard script
            wp_enqueue_script(
                'luna-crm-dashboard',
                plugin_dir_url(__FILE__) . 'assets/js/admin/dashboard.js',
                array('jquery'),
                filemtime(plugin_dir_path(__FILE__) . 'assets/js/admin/dashboard.js'),
                true
            );
            
            // Time tracking script
            wp_enqueue_script(
                'luna-crm-time-tracking',
                plugin_dir_url(__FILE__) . 'assets/js/admin/time-tracking.js',
                array('jquery'),
                filemtime(plugin_dir_path(__FILE__) . 'assets/js/admin/time-tracking.js'),
                true
            );
            
            // Localize scripts
            wp_localize_script('luna-crm-dashboard', 'wpmzf_dashboard', array(
                'nonce' => wp_create_nonce('wpmzf_nonce'),
                'ajaxurl' => admin_url('admin-ajax.php')
            ));
            
            wp_localize_script('luna-crm-time-tracking', 'wpmzf_time', array(
                'nonce' => wp_create_nonce('wpmzf_nonce'),
                'ajaxurl' => admin_url('admin-ajax.php'),
                'projects' => array_map(function($project) {
                    return array('id' => $project->id, 'name' => $project->name);
                }, WPMZF_Project::get_projects())
            ));
        }
        
        // Legacy support - sprawdzanie widoku pojedynczej osoby
        if (isset($_GET['page']) && ($_GET['page'] === 'wpmzf_person_view' || $_GET['page'] === 'luna-crm-person-view')) {
            // Style i skrypty są już ładowane przez WPMZF_Admin_Pages
            // Usunięto duplikację ładowania assets
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
            
            // Używamy wp_editor zamiast zwykłego textarea
            ob_start();
            echo '<p class="comment-form-comment">';
            wp_editor('', 'comment', array(
                'textarea_name' => 'comment',
                'textarea_rows' => 5,
                'media_buttons' => false,
                'teeny' => true,
                'quicktags' => true,
                'tinymce' => array(
                    'toolbar1' => 'bold,italic,underline,link,unlink,undo,redo',
                    'toolbar2' => '',
                    'height' => 150
                )
            ));
            echo '</p>';
            $defaults['comment_field'] = ob_get_clean();
            
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

    /**
     * Create daily backup
     */
    public function create_daily_backup() {
        $result = WPMZF_Backup_Manager::create_backup();
        
        if (!is_wp_error($result)) {
            // Clean old backups
            WPMZF_Backup_Manager::cleanup_old_backups();
            
            WPMZF_Logger::info('Daily backup completed successfully');
        } else {
            WPMZF_Logger::error('Daily backup failed', [
                'error' => $result->get_error_message()
            ]);
        }
    }

    /**
     * Plugin activation hook
     */
    public function on_plugin_activation() {
        try {
            WPMZF_Logger::info('Plugin activation started');
            
            // Initialize database optimizer and create indexes
            $database_optimizer = new WPMZF_Database_Optimizer();
            $indexes_result = $database_optimizer->create_database_indexes();
            
            if ($indexes_result['success']) {
                WPMZF_Logger::info('Database indexes created successfully', ['count' => $indexes_result['success_count']]);
            } else {
                WPMZF_Logger::warning('Some database indexes could not be created', ['errors' => $indexes_result['errors']]);
            }
            
            // Create initial backup on activation
            $backup_manager = new WPMZF_Backup_Manager();
            $backup_result = $backup_manager->create_backup('plugin_activation');
            
            if ($backup_result['success']) {
                WPMZF_Logger::info('Initial backup created on activation', ['file' => $backup_result['file']]);
            }
            
            // Initialize cache directories
            $cache_manager = new WPMZF_Cache_Manager();
            $cache_manager->init();
            
            WPMZF_Logger::info('Plugin activation completed successfully');
            
        } catch (Exception $e) {
            WPMZF_Logger::error('Error during plugin activation', ['error' => $e->getMessage()]);
        }
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