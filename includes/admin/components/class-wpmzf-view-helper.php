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
        // Sprawdź nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wpmzf_navbar_nonce')) {
            wp_die('Nieprawidłowy nonce');
        }

        $query = sanitize_text_field($_POST['query']);
        $results = array();

        if (strlen($query) < 2) {
            wp_send_json_success($results);
            return;
        }

        // Wyszukaj firmy
        $companies = get_posts(array(
            'post_type' => 'company',
            'posts_per_page' => 5,
            's' => $query,
            'post_status' => 'publish'
        ));

        foreach ($companies as $company) {
            $results['companies'][] = array(
                'id' => $company->ID,
                'title' => $company->post_title,
                'url' => admin_url('admin.php?page=wpmzf_view_company&company_id=' . $company->ID),
                'excerpt' => wp_trim_words(get_post_field('post_content', $company->ID), 15)
            );
        }

        // Wyszukaj osoby
        $persons = get_posts(array(
            'post_type' => 'person',
            'posts_per_page' => 5,
            's' => $query,
            'post_status' => 'publish'
        ));

        foreach ($persons as $person) {
            $results['persons'][] = array(
                'id' => $person->ID,
                'title' => $person->post_title,
                'url' => admin_url('admin.php?page=luna-crm-person-view&person_id=' . $person->ID),
                'excerpt' => wp_trim_words(get_post_field('post_content', $person->ID), 15)
            );
        }

        // Wyszukaj projekty
        $projects = get_posts(array(
            'post_type' => 'project',
            'posts_per_page' => 5,
            's' => $query,
            'post_status' => 'publish'
        ));

        foreach ($projects as $project) {
            $results['projects'][] = array(
                'id' => $project->ID,
                'title' => $project->post_title,
                'url' => admin_url('admin.php?page=wpmzf_view_project&project_id=' . $project->ID),
                'excerpt' => wp_trim_words(get_post_field('post_content', $project->ID), 15)
            );
        }

        wp_send_json_success($results);
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
            filemtime($plugin_path . 'assets/css/navbar.css')
        );

        wp_enqueue_script(
            'wpmzf-navbar',
            $plugin_url . 'assets/js/admin/navbar.js',
            array('jquery'),
            filemtime($plugin_path . 'assets/js/admin/navbar.js'),
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
