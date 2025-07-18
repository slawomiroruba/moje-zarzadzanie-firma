<?php
/**
 * Uniwersalny Controller Widoków
 * 
 * Jeden controller do obsługi wszystkich typów wpisów w systemie.
 * Każdy typ wpisu (company, person, project, task, itp.) używa tego samego
 * szablonu z różnymi konfiguracjami.
 * 
 * @package WPMZF
 * @subpackage Admin/Controllers
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPMZF_Universal_View_Controller 
{
    /**
     * Konfiguracje dla wszystkich typów wpisów
     * @var array
     */
    private static $view_configs = [];

    /**
     * Inicjalizacja controllera
     */
    public static function init() {
        self::define_view_configs();
        self::register_admin_pages();
        self::register_ajax_handlers();
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
    }

    /**
     * Rejestruje handlery AJAX
     */
    private static function register_ajax_handlers() {
        add_action('wp_ajax_wpmzf_add_task', [__CLASS__, 'handle_add_task']);
        add_action('wp_ajax_wpmzf_toggle_task_status', [__CLASS__, 'handle_toggle_task_status']);
        add_action('wp_ajax_wpmzf_update_task', [__CLASS__, 'handle_update_task']);
        add_action('wp_ajax_wpmzf_delete_task', [__CLASS__, 'handle_delete_task']);
        add_action('wp_ajax_wpmzf_edit_activity', [__CLASS__, 'handle_edit_activity']);
        add_action('wp_ajax_wpmzf_delete_activity', [__CLASS__, 'handle_delete_activity']);
    }

    /**
     * Definiuje konfiguracje dla wszystkich typów wpisów
     */
    private static function define_view_configs() {
        self::$view_configs = [
            'company' => [
                'singular' => 'Firma',
                'plural' => 'Firmy',
                'icon' => '🏢',
                'menu_slug' => 'wpmzf_companies',
                'view_slug' => 'wpmzf_view_company',
                'param_name' => 'company_id',
                'breadcrumb_parent' => 'Firmy',
                'sections' => [
                    'basic_info' => [
                        'title' => 'Informacje podstawowe',
                        'fields' => ['company_nip', 'company_email', 'company_phone', 'company_website', 'company_address'],
                        'position' => 'left'
                    ],
                    'projects' => [
                        'title' => 'Projekty', 
                        'component' => 'related_projects',
                        'position' => 'left'
                    ],
                    'important_links' => [
                        'title' => 'Ważne linki',
                        'component' => 'important_links',
                        'position' => 'left'
                    ],
                    'activity_form' => [
                        'title' => 'Nowa Aktywność',
                        'component' => 'activity_tabs',
                        'position' => 'center'
                    ],
                    'timeline' => [
                        'title' => 'Historia Aktywności',
                        'component' => 'timeline',
                        'position' => 'center'
                    ],
                    'tasks' => [
                        'title' => 'Zadania',
                        'component' => 'tasks',
                        'position' => 'right'
                    ]
                ],
                'actions' => [
                    'edit' => [
                        'label' => 'Edytuj firmę',
                        'url_pattern' => 'post.php?post={id}&action=edit',
                        'icon' => '✏️',
                        'class' => 'button button-primary'
                    ],
                    'add_project' => [
                        'label' => 'Dodaj projekt',
                        'url_pattern' => 'post-new.php?post_type=project',
                        'icon' => '📁',
                        'class' => 'button'
                    ]
                ]
            ],

            'person' => [
                'singular' => 'Osoba',
                'plural' => 'Osoby', 
                'icon' => '👤',
                'menu_slug' => 'wpmzf_persons',
                'view_slug' => 'wpmzf_view_person',
                'param_name' => 'person_id',
                'breadcrumb_parent' => 'Osoby',
                'sections' => [
                    'basic_info' => [
                        'title' => 'Informacje podstawowe',
                        'fields' => ['person_email', 'person_phone', 'person_company', 'person_position'],
                        'position' => 'left'
                    ],
                    'projects' => [
                        'title' => 'Projekty',
                        'component' => 'related_projects', 
                        'position' => 'left'
                    ],
                    'timeline' => [
                        'title' => 'Historia Aktywności',
                        'component' => 'timeline',
                        'position' => 'center'
                    ],
                    'tasks' => [
                        'title' => 'Zadania',
                        'component' => 'tasks',
                        'position' => 'right'
                    ]
                ],
                'actions' => [
                    'edit' => [
                        'label' => 'Edytuj osobę',
                        'url_pattern' => 'post.php?post={id}&action=edit',
                        'icon' => '✏️',
                        'class' => 'button button-primary'
                    ]
                ]
            ],

            'project' => [
                'singular' => 'Projekt',
                'plural' => 'Projekty',
                'icon' => '📁',
                'menu_slug' => 'wpmzf_projects',
                'view_slug' => 'wpmzf_view_project',
                'param_name' => 'project_id',
                'breadcrumb_parent' => 'Projekty',
                'sections' => [
                    'basic_info' => [
                        'title' => 'Informacje o projekcie',
                        'fields' => ['project_status', 'project_start_date', 'project_end_date', 'project_budget'],
                        'position' => 'left'
                    ],
                    'related_entities' => [
                        'title' => 'Powiązania',
                        'component' => 'related_entities',
                        'position' => 'left'
                    ],
                    'timeline' => [
                        'title' => 'Historia Projektu',
                        'component' => 'timeline',
                        'position' => 'center'
                    ],
                    'tasks' => [
                        'title' => 'Zadania Projektu',
                        'component' => 'project_tasks',
                        'position' => 'right'
                    ]
                ],
                'actions' => [
                    'edit' => [
                        'label' => 'Edytuj projekt',
                        'url_pattern' => 'post.php?post={id}&action=edit',
                        'icon' => '✏️',
                        'class' => 'button button-primary'
                    ]
                ]
            ],

            'task' => [
                'singular' => 'Zadanie',
                'plural' => 'Zadania',
                'icon' => '✅',
                'menu_slug' => 'wpmzf_tasks',
                'view_slug' => 'wpmzf_view_task',
                'param_name' => 'task_id',
                'breadcrumb_parent' => 'Zadania',
                'sections' => [
                    'basic_info' => [
                        'title' => 'Szczegóły zadania',
                        'fields' => ['task_status', 'task_priority', 'task_due_date', 'task_assignee'],
                        'position' => 'left'
                    ],
                    'related_entities' => [
                        'title' => 'Powiązania',
                        'component' => 'related_entities',
                        'position' => 'left'
                    ],
                    'timeline' => [
                        'title' => 'Historia Zadania',
                        'component' => 'timeline',
                        'position' => 'center'
                    ],
                    'time_tracking' => [
                        'title' => 'Rejestr Czasu',
                        'component' => 'time_entries',
                        'position' => 'right'
                    ]
                ],
                'actions' => [
                    'edit' => [
                        'label' => 'Edytuj zadanie',
                        'url_pattern' => 'post.php?post={id}&action=edit',
                        'icon' => '✏️',
                        'class' => 'button button-primary'
                    ]
                ]
            ],

            'opportunity' => [
                'singular' => 'Szansa Sprzedaży',
                'plural' => 'Szanse Sprzedaży',
                'icon' => '💰',
                'menu_slug' => 'wpmzf_opportunities',
                'view_slug' => 'wpmzf_view_opportunity',
                'param_name' => 'opportunity_id',
                'breadcrumb_parent' => 'Szanse Sprzedaży',
                'sections' => [
                    'basic_info' => [
                        'title' => 'Informacje o szansie',
                        'fields' => ['opportunity_stage', 'opportunity_value', 'opportunity_probability', 'opportunity_close_date'],
                        'position' => 'left'
                    ],
                    'related_entities' => [
                        'title' => 'Powiązania',
                        'component' => 'related_entities',
                        'position' => 'left'
                    ],
                    'timeline' => [
                        'title' => 'Historia Sprzedaży',
                        'component' => 'timeline',
                        'position' => 'center'
                    ],
                    'documents' => [
                        'title' => 'Dokumenty',
                        'component' => 'related_documents',
                        'position' => 'right'
                    ]
                ],
                'actions' => [
                    'edit' => [
                        'label' => 'Edytuj szansę',
                        'url_pattern' => 'post.php?post={id}&action=edit',
                        'icon' => '✏️',
                        'class' => 'button button-primary'
                    ]
                ]
            ]

            // Można łatwo dodać więcej typów wpisów...
        ];
    }

    /**
     * Rejestruje strony administracyjne dla wszystkich typów
     */
    private static function register_admin_pages() {
        foreach (self::$view_configs as $post_type => $config) {
            add_submenu_page(
                '',  // Ukryte strony
                'Widok ' . $config['singular'],
                'Widok ' . $config['singular'],
                'manage_options',
                $config['view_slug'],
                function() use ($post_type, $config) {
                    self::render_view($post_type, $config);
                }
            );
        }
    }

    /**
     * Główna metoda renderująca widok
     */
    public static function render_view($post_type, $config) {
        global $title;

        // Pobierz ID obiektu
        $object_id = isset($_GET[$config['param_name']]) ? intval($_GET[$config['param_name']]) : 0;
        
        if (!$object_id) {
            wp_die('Nieprawidłowe ID obiektu.');
        }

        // Sprawdź czy obiekt istnieje
        $object = get_post($object_id);
        if (!$object || $object->post_type !== $post_type) {
            wp_die($config['singular'] . ' nie został znaleziony.');
        }

        $object_title = get_the_title($object_id);
        $title = 'Widok ' . $config['singular'] . ': ' . $object_title;
        $object_fields = get_fields($object_id);

        // Renderuj nagłówek
        self::render_header($object_id, $object_title, $config);

        // Renderuj główny szablon
        self::render_universal_template($object_id, $object_title, $object_fields, $post_type, $config);
    }

    /**
     * Renderuje nagłówek widoku
     */
    private static function render_header($object_id, $object_title, $config) {
        // Przygotuj breadcrumbs
        $breadcrumbs = [
            ['label' => 'Dashboard', 'url' => admin_url('admin.php?page=wpmzf_dashboard')],
            ['label' => $config['breadcrumb_parent'], 'url' => admin_url('admin.php?page=' . $config['menu_slug'])],
            ['label' => $object_title, 'url' => '']
        ];

        // Przygotuj akcje
        $actions = [];
        foreach ($config['actions'] as $action_key => $action) {
            $url = str_replace('{id}', $object_id, $action['url_pattern']);
            $actions[] = [
                'label' => $action['label'],
                'url' => admin_url($url),
                'icon' => $action['icon'],
                'class' => $action['class']
            ];
        }

        // Renderuj header używając istniejącego helpera
        WPMZF_View_Helper::render_complete_header([
            'title' => $object_title,
            'subtitle' => 'Szczegółowe informacje - ' . $config['singular'],
            'breadcrumbs' => $breadcrumbs,
            'actions' => $actions
        ]);
    }

    /**
     * Renderuje uniwersalny szablon 
     */
    private static function render_universal_template($object_id, $object_title, $object_fields, $post_type, $config) {
        include WPMZF_PLUGIN_PATH . 'includes/admin/views/universal/universal-view-template.php';
    }

    /**
     * Ładuje skrypty i style
     */
    public static function enqueue_scripts($hook) {
        // Sprawdź czy jesteśmy na stronie widoku
        $is_view_page = false;
        foreach (self::$view_configs as $config) {
            if ($hook === 'admin_page_' . $config['view_slug']) {
                $is_view_page = true;
                break;
            }
        }

        if (!$is_view_page) {
            return;
        }

        // Ładuj ACF jeśli dostępne
        if (function_exists('acf_enqueue_scripts')) {
            acf_enqueue_scripts();
        }

        // Ładuj edytor
        wp_enqueue_editor();
        wp_enqueue_media();

        // Ładuj style
        wp_enqueue_style(
            'wpmzf-universal-view',
            plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/css/universal-view.css',
            [],
            '1.0.0'
        );

        // Ładuj skrypty
        wp_enqueue_script(
            'wpmzf-universal-view',
            plugin_dir_url(__FILE__) . '../views/universal/universal-view.js',
            ['jquery', 'editor'],
            '1.0.0',
            true
        );

        // Przekaż zmienne do JavaScript
        wp_localize_script('wpmzf-universal-view', 'wpmzfUniversalView', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'adminUrl' => admin_url(),
            'nonce' => wp_create_nonce('wpmzf_universal_view_nonce'),
            'taskNonce' => wp_create_nonce('wpmzf_task_nonce')
        ]);
    }

    /**
     * Pobiera konfigurację dla danego typu wpisu
     */
    public static function get_config($post_type) {
        return isset(self::$view_configs[$post_type]) ? self::$view_configs[$post_type] : null;
    }

    /**
     * Pobiera wszystkie skonfigurowane typy wpisów
     */
    public static function get_all_configs() {
        return self::$view_configs;
    }

    /**
     * Handler AJAX - dodawanie zadania
     */
    public static function handle_add_task() {
        // Weryfikacja nonce
        if (!wp_verify_nonce($_POST['wpmzf_task_security'], 'wpmzf_universal_view_nonce')) {
            wp_die('Security check failed');
        }

        // Sprawdź uprawnienia
        if (!current_user_can('edit_posts')) {
            wp_die('Insufficient permissions');
        }

        $task_title = sanitize_text_field($_POST['task_title']);
        $task_description = sanitize_textarea_field($_POST['task_description']);
        $task_priority = sanitize_text_field($_POST['task_priority']);
        $task_due_date = sanitize_text_field($_POST['task_due_date']);
        $task_assignee = intval($_POST['task_assignee']);

        // Znajdź który typ obiektu rodzica i jego ID
        $parent_id = null;
        $parent_meta_key = null;
        
        foreach (['company_id', 'person_id', 'project_id', 'opportunity_id'] as $param) {
            if (isset($_POST[$param]) && !empty($_POST[$param])) {
                $parent_id = intval($_POST[$param]);
                $parent_meta_key = str_replace('_id', '', $param);
                break;
            }
        }

        if (!$parent_id) {
            wp_send_json_error('Nie znaleziono ID obiektu nadrzędnego');
        }

        // Utwórz zadanie
        $task_data = array(
            'post_title' => $task_title,
            'post_content' => $task_description,
            'post_type' => 'task',
            'post_status' => 'publish',
            'post_author' => get_current_user_id()
        );

        $task_id = wp_insert_post($task_data);

        if (is_wp_error($task_id)) {
            wp_send_json_error('Błąd podczas tworzenia zadania');
        }

        // Zapisz meta pola
        update_post_meta($task_id, 'priority', $task_priority);
        if ($task_due_date) {
            update_post_meta($task_id, 'due_date', $task_due_date);
        }
        if ($task_assignee) {
            update_post_meta($task_id, 'assignee', $task_assignee);
        }
        update_post_meta($task_id, 'status', 'pending');
        update_post_meta($task_id, $parent_meta_key, $parent_id);

        wp_send_json_success(['task_id' => $task_id, 'message' => 'Zadanie zostało utworzone']);
    }

    /**
     * Handler AJAX - przełączanie statusu zadania
     */
    public static function handle_toggle_task_status() {
        // Weryfikacja nonce
        if (!wp_verify_nonce($_POST['security'], 'wpmzf_universal_view_nonce')) {
            wp_die('Security check failed');
        }

        if (!current_user_can('edit_posts')) {
            wp_die('Insufficient permissions');
        }

        $task_id = intval($_POST['task_id']);
        $completed = intval($_POST['completed']);

        $new_status = $completed ? 'completed' : 'pending';
        update_post_meta($task_id, 'status', $new_status);

        wp_send_json_success(['status' => $new_status]);
    }

    /**
     * Handler AJAX - aktualizacja zadania
     */
    public static function handle_update_task() {
        // Weryfikacja nonce
        if (!wp_verify_nonce($_POST['wpmzf_edit_task_security'], 'wpmzf_universal_view_nonce')) {
            wp_die('Security check failed');
        }

        if (!current_user_can('edit_posts')) {
            wp_die('Insufficient permissions');
        }

        $task_id = intval($_POST['task_id']);
        $task_title = sanitize_text_field($_POST['task_title']);
        $task_description = sanitize_textarea_field($_POST['task_description']);
        $task_priority = sanitize_text_field($_POST['task_priority']);
        $task_due_date = sanitize_text_field($_POST['task_due_date']);
        $task_assignee = intval($_POST['task_assignee']);
        $task_status = sanitize_text_field($_POST['task_status']);

        // Aktualizuj post
        $task_data = array(
            'ID' => $task_id,
            'post_title' => $task_title,
            'post_content' => $task_description,
        );

        $result = wp_update_post($task_data);

        if (is_wp_error($result)) {
            wp_send_json_error('Błąd podczas aktualizacji zadania');
        }

        // Aktualizuj meta pola
        update_post_meta($task_id, 'priority', $task_priority);
        update_post_meta($task_id, 'status', $task_status);
        if ($task_due_date) {
            update_post_meta($task_id, 'due_date', $task_due_date);
        }
        if ($task_assignee) {
            update_post_meta($task_id, 'assignee', $task_assignee);
        }

        wp_send_json_success(['message' => 'Zadanie zostało zaktualizowane']);
    }

    /**
     * Handler AJAX - usuwanie zadania
     */
    public static function handle_delete_task() {
        // Weryfikacja nonce
        if (!wp_verify_nonce($_POST['security'], 'wpmzf_universal_view_nonce')) {
            wp_die('Security check failed');
        }

        if (!current_user_can('delete_posts')) {
            wp_die('Insufficient permissions');
        }

        $task_id = intval($_POST['task_id']);
        
        $result = wp_delete_post($task_id, true);

        if (!$result) {
            wp_send_json_error('Błąd podczas usuwania zadania');
        }

        wp_send_json_success(['message' => 'Zadanie zostało usunięte']);
    }

    /**
     * Handler AJAX - edycja aktywności
     */
    public static function handle_edit_activity() {
        // Weryfikacja nonce
        if (!wp_verify_nonce($_POST['security'], 'wpmzf_universal_view_nonce')) {
            wp_die('Security check failed');
        }

        if (!current_user_can('edit_posts')) {
            wp_die('Insufficient permissions');
        }

        $activity_id = intval($_POST['activity_id']);
        
        // Pobierz dane aktywności
        $activity = get_post($activity_id);
        if (!$activity || $activity->post_type !== 'activity') {
            wp_send_json_error('Aktywność nie została znaleziona');
        }

        $activity_data = array(
            'id' => $activity_id,
            'title' => $activity->post_title,
            'content' => $activity->post_content,
            'type' => get_post_meta($activity_id, 'activity_type', true),
            'date' => get_post_meta($activity_id, 'activity_date', true),
            'email_to' => get_post_meta($activity_id, 'email_to', true),
            'email_subject' => get_post_meta($activity_id, 'email_subject', true)
        );

        wp_send_json_success($activity_data);
    }

    /**
     * Handler AJAX - usuwanie aktywności
     */
    public static function handle_delete_activity() {
        // Weryfikacja nonce
        if (!wp_verify_nonce($_POST['security'], 'wpmzf_universal_view_nonce')) {
            wp_die('Security check failed');
        }

        if (!current_user_can('delete_posts')) {
            wp_die('Insufficient permissions');
        }

        $activity_id = intval($_POST['activity_id']);
        
        $result = wp_delete_post($activity_id, true);

        if (!$result) {
            wp_send_json_error('Błąd podczas usuwania aktywności');
        }

        wp_send_json_success(['message' => 'Aktywność została usunięta']);
    }
}
