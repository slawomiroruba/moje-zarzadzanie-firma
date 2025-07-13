<?php
/**
 * Helper do renderowania nawigacji w widokach
 *
 * @package WPMZF
 * @subpackage Admin/Components
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPMZF_View_Helper {

    private static $navbar_instance = null;
    private static $navbar_rendered = false;
    private static $assets_loaded = false;
    private static $ajax_hooks_registered = false;

    /**
     * Inicjalizuje klasę - wywołane jeden raz
     */
    public static function init() {
        // Rejestruj hooki AJAX
        self::init_ajax_hooks();
        
        // Rejestruj hook do ładowania assetów
        add_action('admin_enqueue_scripts', array('WPMZF_View_Helper', 'enqueue_assets_hook'));
    }

    /**
     * Hook dla admin_enqueue_scripts
     */
    public static function enqueue_assets_hook($hook) {
        // Lista hooków stron wtyczki
        $wpmzf_hooks = array(
            'toplevel_page_wpmzf_dashboard',
            'wpmzf_page_wpmzf_dashboard',
            'admin_page_wpmzf_companies', 
            'wpmzf_page_wpmzf_companies',
            'admin_page_wpmzf_persons',
            'wpmzf_page_wpmzf_persons', 
            'admin_page_wpmzf_projects',
            'wpmzf_page_wpmzf_projects',
            'admin_page_wpmzf_view_company',
            'admin_page_wpmzf_view_person',
            'admin_page_wpmzf_view_project',
            'admin_page_luna-crm-person-view'
        );
        
        // Sprawdź czy jesteśmy na stronie wtyczki
        if (in_array($hook, $wpmzf_hooks)) {
            self::enqueue_navbar_assets();
        }
    }

    /**
     * Inicjalizuje hooki AJAX (wywoływane raz)
     */
    public static function init_ajax_hooks() {
        if (self::$ajax_hooks_registered) {
            return;
        }
        
        add_action('wp_ajax_wpmzf_global_search', array('WPMZF_View_Helper', 'handle_global_search'));
        self::$ajax_hooks_registered = true;
    }

    /**
     * Obsługuje globalne wyszukiwanie AJAX
     */
    public static function handle_global_search() {
        error_log('WPMZF: handle_global_search wywołane');
        
        // Sprawdź nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wpmzf_navbar_nonce')) {
            error_log('WPMZF: Nieprawidłowy nonce');
            wp_die('Nieprawidłowy nonce');
        }

        $search_term = sanitize_text_field($_POST['search_term']);
        error_log('WPMZF: Wyszukiwanie dla: ' . $search_term);
        
        if (strlen($search_term) < 2) {
            wp_send_json_success([]);
            return;
        }

        // Definicja przeszukiwanych typów postów
        $post_types = [
            'company'     => 'Firmy',
            'person'      => 'Osoby',
            'project'     => 'Projekty',
            'task'        => 'Zadania',
            'employee'    => 'Pracownicy',
            'opportunity' => 'Szanse Sprzedaży',
            'activity'    => 'Aktywności'
        ];

        $grouped_results = [];

        foreach ($post_types as $post_type => $label) {
            $query = new WP_Query([
                'post_type'      => $post_type,
                'post_status'    => 'publish',
                's'              => $search_term,
                'posts_per_page' => 5,
                'orderby'        => 'relevance'
            ]);

            if ($query->have_posts()) {
                $items = [];
                while ($query->have_posts()) {
                    $query->the_post();
                    $items[] = [
                        'id'      => get_the_ID(),
                        'title'   => get_the_title(),
                        'url'     => self::get_entity_url($post_type, get_the_ID()),
                        'excerpt' => wp_trim_words(get_the_excerpt(), 15, '...')
                    ];
                }
                wp_reset_postdata();

                $grouped_results[] = [
                    'label' => $label,
                    'items' => $items,
                    'count' => $query->found_posts
                ];
            }
        }

        error_log('WPMZF: Znaleziono grup wyników: ' . count($grouped_results));
        wp_send_json_success($grouped_results);
    }

    /**
     * Pomocnicza funkcja do generowania URL-i dla wyników wyszukiwania
     */
    private static function get_entity_url($post_type, $post_id) {
        switch ($post_type) {
            case 'company':
                return admin_url('admin.php?page=wpmzf_view_company&company_id=' . $post_id);
            case 'person':
                return admin_url('admin.php?page=wpmzf_view_person&person_id=' . $post_id);
            case 'project':
                return admin_url('admin.php?page=wpmzf_view_project&project_id=' . $post_id);
            default:
                return admin_url('post.php?post=' . $post_id . '&action=edit');
        }
    }

    /**
     * Ładuje assety navbara (CSS i JS)
     */
    private static function enqueue_navbar_assets() {
        if (self::$assets_loaded) {
            return;
        }

        $plugin_url = plugin_dir_url(dirname(dirname(dirname(__FILE__))));
        $plugin_path = plugin_dir_path(dirname(dirname(dirname(__FILE__))));

        wp_enqueue_style(
            'wpmzf-navbar',
            $plugin_url . 'assets/css/navbar.css',
            array(),
            filemtime($plugin_path . 'assets/css/navbar.css') . '_v2'
        );

        wp_enqueue_script(
            'wpmzf-navbar',
            $plugin_url . 'assets/js/admin/navbar.js',
            array('jquery'),
            filemtime($plugin_path . 'assets/js/admin/navbar.js') . '_v2',
            true
        );

        wp_localize_script('wpmzf-navbar', 'wpmzfNavbar', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'adminUrl' => admin_url(),
            'nonce' => wp_create_nonce('wpmzf_navbar_nonce'),
            'searchPlaceholder' => 'Wyszukaj firmy, osoby, projekty...',
            'noResults' => 'Brak wyników',
            'searching' => 'Wyszukiwanie...'
        ));

        self::$assets_loaded = true;
    }

    /**
     * Renderuje nawigację górną
     */
    public static function render_navbar() {
        // Zabezpieczenie przed podwójnym renderowaniem
        if (self::$navbar_rendered) {
            return;
        }
        
        if (self::$navbar_instance === null) {
            self::$navbar_instance = new WPMZF_Navbar();
        }
        
        self::$navbar_instance->render();
        self::$navbar_rendered = true;
    }

    /**
     * Renderuje header widoku z nawigacją
     */
    public static function render_view_header($title = '', $subtitle = '', $actions = array()) {
        // Nie renderuj navbar tutaj - jest renderowana w render_complete_header
        
        if (!empty($title) || !empty($subtitle) || !empty($actions)) {
            ?>
            <div class="wpmzf-view-header">
                <div class="wpmzf-view-header-content">
                    <?php if (!empty($title)): ?>
                        <div class="wpmzf-view-title-section">
                            <h1 class="wpmzf-view-title"><?php echo esc_html($title); ?></h1>
                            <?php if (!empty($subtitle)): ?>
                                <p class="wpmzf-view-subtitle"><?php echo esc_html($subtitle); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($actions)): ?>
                        <div class="wpmzf-view-actions">
                            <?php foreach ($actions as $action): ?>
                                <a href="<?php echo esc_url($action['url']); ?>" 
                                   class="<?php echo esc_attr($action['class'] ?? 'button button-primary'); ?>">
                                    <?php if (!empty($action['icon'])): ?>
                                        <span class="wpmzf-action-icon"><?php echo $action['icon']; ?></span>
                                    <?php endif; ?>
                                    <?php echo esc_html($action['label']); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php
        }
    }

    /**
     * Renderuje breadcrumbs
     */
    public static function render_breadcrumbs($items = array()) {
        if (empty($items)) {
            return;
        }
        
        ?>
        <div class="wpmzf-breadcrumbs">
            <nav class="wpmzf-breadcrumbs-nav">
                <?php foreach ($items as $index => $item): ?>
                    <?php if ($index > 0): ?>
                        <span class="wpmzf-breadcrumbs-separator">></span>
                    <?php endif; ?>
                    
                    <?php if (!empty($item['url']) && $index < count($items) - 1): ?>
                        <a href="<?php echo esc_url($item['url']); ?>" class="wpmzf-breadcrumb-link">
                            <?php echo esc_html($item['label']); ?>
                        </a>
                    <?php else: ?>
                        <span class="wpmzf-breadcrumb-current">
                            <?php echo esc_html($item['label']); ?>
                        </span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>
        </div>
        <?php
    }

    /**
     * Renderuje kompletny header widoku z nawigacją i breadcrumbs
     */
    public static function render_complete_header($config = array()) {
        $defaults = array(
            'title' => '',
            'subtitle' => '',
            'actions' => array(),
            'breadcrumbs' => array()
        );
        
        $config = array_merge($defaults, $config);
        
        // Renderuj nawigację
        self::render_navbar();
        
        // Renderuj breadcrumbs jeśli są dostępne
        if (!empty($config['breadcrumbs'])) {
            self::render_breadcrumbs($config['breadcrumbs']);
        }
        
        // Renderuj header
        self::render_view_header($config['title'], $config['subtitle'], $config['actions']);
    }
}
