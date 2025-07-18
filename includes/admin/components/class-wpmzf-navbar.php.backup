<?php
/**
 * Klasa odpowiedzialna za nawigację górną w panelu administracyjnym
 *
 * @package WPMZF
 * @subpackage Admin/Components
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPMZF_Navbar {

    /**
     * Konstruktor klasy
     */
    public function __construct() {
        // Hooki są rejestrowane w WPMZF_View_Helper
        // add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        // add_action('wp_ajax_wpmzf_global_search', array($this, 'handle_global_search'));
    }

    /**
     * Ładuje skrypty i style dla nawigacji
     */
    public function enqueue_scripts($hook) {
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
            'admin_page_wpmzf_view_project'
        );
        
        // Sprawdź czy jesteśmy na stronie wtyczki
        if (!in_array($hook, $wpmzf_hooks)) {
            return;
        }

        wp_enqueue_style(
            'wpmzf-navbar',
            plugin_dir_url(dirname(dirname(dirname(__FILE__)))) . 'assets/css/navbar.css',
            array(),
            '1.2.0' // Aktualizacja wersji po naprawie wszystkich problemów
        );

        wp_enqueue_script(
            'wpmzf-navbar',
            plugin_dir_url(dirname(dirname(dirname(__FILE__)))) . 'assets/js/admin/navbar.js',
            array('jquery'),
            '1.0.2', // Zwiększona wersja dla cache busting
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
    }

    /**
     * Obsługa AJAX dla globalnego wyszukiwania
     */
    public function handle_global_search() {
        // Sprawdzenie nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wpmzf_navbar_nonce')) {
            wp_die('Błąd bezpieczeństwa');
        }

        $search_term = sanitize_text_field($_POST['search_term']);
        
        if (empty($search_term) || strlen($search_term) < 2) {
            wp_send_json_error('Wpisz co najmniej 2 znaki');
        }

        $results = $this->search_all_post_types($search_term);
        
        wp_send_json_success($results);
    }

    /**
     * Wyszukuje we wszystkich typach wpisów
     */
    private function search_all_post_types($search_term) {
        $post_types = array(
            'company' => 'Firmy',
            'person' => 'Osoby',
            'project' => 'Projekty',
            'task' => 'Zadania',
            'employee' => 'Pracownicy',
            'opportunity' => 'Szanse Sprzedaży',
            'quote' => 'Oferty',
            'invoice' => 'Faktury',
            'payment' => 'Płatności',
            'contract' => 'Umowy',
            'expense' => 'Koszty',
            'activity' => 'Aktywności',
            'time_entry' => 'Wpisy Czasu',
            'important_link' => 'Ważne Linki'
        );

        $grouped_results = array();

        foreach ($post_types as $post_type => $label) {
            $query = new WP_Query(array(
                'post_type' => $post_type,
                'post_status' => 'publish',
                's' => $search_term,
                'posts_per_page' => 5,
                'orderby' => 'relevance'
            ));

            if ($query->have_posts()) {
                $items = array();
                while ($query->have_posts()) {
                    $query->the_post();
                    $items[] = array(
                        'id' => get_the_ID(),
                        'title' => get_the_title(),
                        'url' => $this->get_edit_url($post_type, get_the_ID()),
                        'excerpt' => get_the_excerpt()
                    );
                }
                wp_reset_postdata();

                $grouped_results[] = array(
                    'type' => $post_type,
                    'label' => $label,
                    'items' => $items,
                    'count' => $query->found_posts
                );
            }
        }

        return $grouped_results;
    }

    /**
     * Zwraca URL do edycji dla danego typu postu
     */
    private function get_edit_url($post_type, $post_id) {
        switch ($post_type) {
            case 'company':
                return admin_url('admin.php?page=wpmzf_view_company&company_id=' . $post_id);
            case 'person':
                return admin_url('admin.php?page=wpmzf_view_person&person_id=' . $post_id);
            case 'project':
                return admin_url('admin.php?page=wpmzf_view_project&project_id=' . $post_id);
            case 'task':
            case 'employee':
            case 'opportunity':
            case 'quote':
            case 'invoice':
            case 'payment':
            case 'contract':
            case 'expense':
            case 'activity':
            case 'time_entry':
            case 'important_link':
                return admin_url('post.php?post=' . $post_id . '&action=edit');
            default:
                return admin_url('post.php?post=' . $post_id . '&action=edit');
        }
    }

    /**
     * Renderuje nawigację górną
     */
    public function render() {
        $menu_items = $this->get_menu_items();
        ?>
        <div class="wpmzf-navbar">
            <div class="wpmzf-navbar-container">
                <!-- Logo i Dashboard -->
                <div class="wpmzf-navbar-brand">
                    <a href="<?php echo admin_url('admin.php?page=wpmzf_dashboard'); ?>">
                        <span class="wpmzf-navbar-logo">🏢</span>
                        <span class="wpmzf-navbar-title">Zarządzanie Firmą</span>
                    </a>
                </div>

                <!-- Menu główne -->
                <nav class="wpmzf-navbar-nav">
                    <?php foreach ($menu_items as $item): ?>
                        <div class="wpmzf-navbar-item">
                            <a href="<?php echo esc_url($item['url']); ?>" class="wpmzf-navbar-link">
                                <span class="wpmzf-navbar-icon"><?php echo $item['icon']; ?></span>
                                <span class="wpmzf-navbar-label"><?php echo esc_html($item['label']); ?></span>
                                <?php if (!empty($item['dropdown'])): ?>
                                    <span class="wpmzf-navbar-dropdown-arrow">▼</span>
                                <?php endif; ?>
                            </a>
                            
                            <?php if (!empty($item['dropdown'])): ?>
                                <div class="wpmzf-navbar-dropdown">
                                    <?php foreach ($item['dropdown'] as $dropdown_item): ?>
                                        <a href="<?php echo esc_url($dropdown_item['url']); ?>" class="wpmzf-navbar-dropdown-item">
                                            <span class="wpmzf-navbar-dropdown-icon"><?php echo $dropdown_item['icon']; ?></span>
                                            <span><?php echo esc_html($dropdown_item['label']); ?></span>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </nav>

                <!-- Wyszukiwarka -->
                <div class="wpmzf-navbar-search">
                    <div class="wpmzf-search-container">
                        <input type="text" id="wpmzf-global-search" placeholder="Wyszukaj..." class="wpmzf-search-input" autocomplete="off">
                        <button class="wpmzf-search-button" type="button">
                            <span class="dashicons dashicons-search"></span>
                        </button>
                        <div id="wpmzf-search-results" class="wpmzf-search-results">
                            <div class="wpmzf-search-loading" style="display: none;">
                                <span class="dashicons dashicons-update spin"></span>
                                Wyszukiwanie...
                            </div>
                            <div class="wpmzf-search-content"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Zwraca elementy menu
     */
    private function get_menu_items() {
        return array(
            array(
                'label' => 'CRM',
                'icon' => '👥',
                'url' => admin_url('admin.php?page=wpmzf_companies'),
                'dropdown' => array(
                    array(
                        'label' => 'Wszystkie firmy',
                        'icon' => '🏢',
                        'url' => admin_url('admin.php?page=wpmzf_companies')
                    ),
                    array(
                        'label' => 'Dodaj firmę',
                        'icon' => '➕',
                        'url' => admin_url('post-new.php?post_type=company')
                    ),
                    array(
                        'label' => 'Wszystkie osoby',
                        'icon' => '👤',
                        'url' => admin_url('admin.php?page=wpmzf_persons')
                    ),
                    array(
                        'label' => 'Dodaj osobę',
                        'icon' => '➕',
                        'url' => admin_url('post-new.php?post_type=person')
                    ),
                    array(
                        'label' => 'Szanse sprzedaży',
                        'icon' => '📈',
                        'url' => admin_url('edit.php?post_type=opportunity')
                    ),
                    array(
                        'label' => 'Oferty',
                        'icon' => '📄',
                        'url' => admin_url('edit.php?post_type=quote')
                    )
                )
            ),
            array(
                'label' => 'Projekty',
                'icon' => '📁',
                'url' => admin_url('admin.php?page=wpmzf_projects'),
                'dropdown' => array(
                    array(
                        'label' => 'Wszystkie projekty',
                        'icon' => '📁',
                        'url' => admin_url('admin.php?page=wpmzf_projects')
                    ),
                    array(
                        'label' => 'Dodaj projekt',
                        'icon' => '➕',
                        'url' => admin_url('post-new.php?post_type=project')
                    ),
                    array(
                        'label' => 'Zadania',
                        'icon' => '✅',
                        'url' => admin_url('edit.php?post_type=task')
                    ),
                    array(
                        'label' => 'Dodaj zadanie',
                        'icon' => '➕',
                        'url' => admin_url('post-new.php?post_type=task')
                    ),
                    array(
                        'label' => 'Czas pracy',
                        'icon' => '⏱️',
                        'url' => admin_url('edit.php?post_type=time_entry')
                    )
                )
            ),
            array(
                'label' => 'Finanse',
                'icon' => '💰',
                'url' => admin_url('edit.php?post_type=invoice'),
                'dropdown' => array(
                    array(
                        'label' => 'Faktury',
                        'icon' => '🧾',
                        'url' => admin_url('edit.php?post_type=invoice')
                    ),
                    array(
                        'label' => 'Dodaj fakturę',
                        'icon' => '➕',
                        'url' => admin_url('post-new.php?post_type=invoice')
                    ),
                    array(
                        'label' => 'Płatności',
                        'icon' => '💳',
                        'url' => admin_url('edit.php?post_type=payment')
                    ),
                    array(
                        'label' => 'Koszty',
                        'icon' => '💸',
                        'url' => admin_url('edit.php?post_type=expense')
                    ),
                    array(
                        'label' => 'Umowy',
                        'icon' => '📜',
                        'url' => admin_url('edit.php?post_type=contract')
                    )
                )
            ),
            array(
                'label' => 'Zespół',
                'icon' => '👨‍💼',
                'url' => admin_url('edit.php?post_type=employee'),
                'dropdown' => array(
                    array(
                        'label' => 'Pracownicy',
                        'icon' => '👨‍💼',
                        'url' => admin_url('edit.php?post_type=employee')
                    ),
                    array(
                        'label' => 'Dodaj pracownika',
                        'icon' => '➕',
                        'url' => admin_url('post-new.php?post_type=employee')
                    ),
                    array(
                        'label' => 'Aktywności',
                        'icon' => '📝',
                        'url' => admin_url('edit.php?post_type=activity')
                    )
                )
            ),
            array(
                'label' => 'Narzędzia',
                'icon' => '🔧',
                'url' => admin_url('edit.php?post_type=important_link'),
                'dropdown' => array(
                    array(
                        'label' => 'Ważne linki',
                        'icon' => '🔗',
                        'url' => admin_url('edit.php?post_type=important_link')
                    ),
                    array(
                        'label' => 'Dodaj link',
                        'icon' => '➕',
                        'url' => admin_url('post-new.php?post_type=important_link')
                    )
                )
            )
        );
    }
}
