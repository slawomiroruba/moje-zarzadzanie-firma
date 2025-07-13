<?php

/**
 * Klasa odpowiedzialna za blokowanie frontendu WordPressa i przekierowania w panelu administratora
 */
class WPMZF_Frontend_Blocker {

    public function __construct() {
        // Blokowanie frontendu dla wszystkich użytkowników
        add_action('template_redirect', array($this, 'block_frontend_access'));
        
        // Przekierowanie z domyślnego kokpitu na nasz dashboard
        add_action('admin_init', array($this, 'redirect_admin_dashboard'));
        
        // Ukryj domyślne elementy menu WordPressa
        add_action('admin_menu', array($this, 'remove_default_admin_menus'), 999);
        
        // Usuń niepotrzebne widgety z kokpitu
        add_action('wp_dashboard_setup', array($this, 'remove_dashboard_widgets'));
        
        // Przekieruj po zalogowaniu na nasz dashboard
        add_filter('login_redirect', array($this, 'custom_login_redirect'), 10, 3);
        
        // Blokuj dostęp do niektórych stron administracyjnych
        add_action('admin_init', array($this, 'block_admin_pages'));
        
        // Zmień tytuł strony administratora
        add_filter('admin_title', array($this, 'custom_admin_title'), 10, 2);
    }

    /**
     * Blokuje dostęp do wybranych stron administracyjnych WordPressa
     */
    public function block_admin_pages() {
        global $pagenow;
        
        // Lista stron do zablokowania dla wszystkich użytkowników
        $blocked_pages = array();
        
        // Lista stron do zablokowania dla nie-administratorów
        $blocked_for_non_admins = array(
            'themes.php',
            'plugins.php', 
            'options-general.php',
            'tools.php'
        );
        
        // Sprawdź czy strona powinna być zablokowana
        $should_block = false;
        
        if (in_array($pagenow, $blocked_pages)) {
            $should_block = true;
        }
        
        if (!current_user_can('manage_options') && in_array($pagenow, $blocked_for_non_admins)) {
            $should_block = true;
        }
        
        if ($should_block) {
            wp_safe_redirect(admin_url('admin.php?page=wpmzf_dashboard'));
            exit;
        }
    }

    /**
     * Blokuje dostęp do frontendu dla wszystkich użytkowników
     */
    public function block_frontend_access() {
        // Sprawdź czy to nie jest panel administratora, login, czy AJAX
        if (is_admin() || 
            (defined('DOING_AJAX') && DOING_AJAX) || 
            (defined('DOING_CRON') && DOING_CRON) ||
            (defined('WP_CLI') && WP_CLI)) {
            return;
        }

        // Sprawdź czy to nie jest strona logowania WordPressa
        if (isset($GLOBALS['pagenow']) && $GLOBALS['pagenow'] === 'wp-login.php') {
            return;
        }

        // Sprawdź czy to nie są akcje związane z logowaniem
        if (isset($_GET['action']) && in_array($_GET['action'], ['register', 'lostpassword', 'rp', 'resetpass'])) {
            return;
        }

        // Jeśli użytkownik jest zalogowany, przekieruj na panel administratora
        if (is_user_logged_in()) {
            wp_safe_redirect(admin_url('admin.php?page=wpmzf_dashboard'));
            exit;
        }

        // Jeśli użytkownik nie jest zalogowany, przekieruj na stronę logowania
        wp_safe_redirect(wp_login_url());
        exit;
    }

    /**
     * Przekierowuje z domyślnego kokpitu WordPressa na nasz dashboard
     */
    public function redirect_admin_dashboard() {
        // Sprawdź czy jesteśmy na stronie głównej panelu administracyjnego
        global $pagenow;
        
        if ($pagenow === 'index.php' && !isset($_GET['page'])) {
            // Przekieruj na nasz dashboard
            wp_safe_redirect(admin_url('admin.php?page=wpmzf_dashboard'));
            exit;
        }
        
        // Sprawdź czy użytkownik próbuje wejść na admin bez konkretnej strony
        if ($pagenow === 'admin.php' && !isset($_GET['page'])) {
            // Przekieruj na nasz dashboard
            wp_safe_redirect(admin_url('admin.php?page=wpmzf_dashboard'));
            exit;
        }
    }

    /**
     * Usuwa domyślne menu WordPressa dla użytkowników nienależących do administratorów
     */
    public function remove_default_admin_menus() {
        // Lista menu do ukrycia dla wszystkich użytkowników (możesz dostosować według potrzeb)
        $menus_to_hide = array(
            'edit.php',                     // Wpisy
            'edit.php?post_type=page',      // Strony
            'edit-comments.php',            // Komentarze
            'upload.php',                   // Media (opcjonalnie)
            'themes.php',                   // Wygląd
            'plugins.php',                  // Wtyczki (tylko dla nie-administratorów)
            'tools.php',                    // Narzędzia
            'options-general.php',          // Ustawienia (tylko dla nie-administratorów)
        );

        // Dla administratorów ukrywamy mniej rzeczy
        if (current_user_can('manage_options')) {
            $menus_to_hide = array(
                'edit.php',                     // Wpisy
                'edit.php?post_type=page',      // Strony
                'edit-comments.php',            // Komentarze
            );
        }

        foreach ($menus_to_hide as $menu) {
            remove_menu_page($menu);
        }

        // Usuń niektóre submenu z pozostałych menu
        remove_submenu_page('index.php', 'update-core.php'); // Aktualizacje
        
        // Ukryj niektóre submenu z menu użytkowników dla nie-administratorów
        if (!current_user_can('manage_options')) {
            remove_submenu_page('users.php', 'user-new.php');
        }
    }

    /**
     * Usuwa domyślne widgety z kokpitu WordPressa
     */
    public function remove_dashboard_widgets() {
        // Usuń domyślne widgety kokpitu
        remove_meta_box('dashboard_right_now', 'dashboard', 'normal');
        remove_meta_box('dashboard_recent_comments', 'dashboard', 'normal');
        remove_meta_box('dashboard_incoming_links', 'dashboard', 'normal');
        remove_meta_box('dashboard_plugins', 'dashboard', 'normal');
        remove_meta_box('dashboard_quick_press', 'dashboard', 'side');
        remove_meta_box('dashboard_recent_drafts', 'dashboard', 'side');
        remove_meta_box('dashboard_primary', 'dashboard', 'side');
        remove_meta_box('dashboard_secondary', 'dashboard', 'side');
        remove_meta_box('dashboard_activity', 'dashboard', 'normal');
        remove_meta_box('welcome_panel', 'dashboard', 'normal');
    }

    /**
     * Przekierowuje użytkownika na nasz dashboard po zalogowaniu
     */
    public function custom_login_redirect($redirect_to, $request, $user) {
        // Sprawdź czy użytkownik został poprawnie zalogowany
        if (isset($user->user_login)) {
            // Przekieruj na nasz dashboard
            return admin_url('admin.php?page=wpmzf_dashboard');
        }

        return $redirect_to;
    }

    /**
     * Zmienia tytuł stron administratora na bardziej przyjazny
     */
    public function custom_admin_title($admin_title, $title) {
        // Sprawdź czy jesteśmy na naszym dashboardzie
        if (isset($_GET['page']) && $_GET['page'] === 'wpmzf_dashboard') {
            return 'Dashboard - Zarządzanie Firmą';
        }
        
        // Dla innych stron naszego pluginu
        if (isset($_GET['page']) && strpos($_GET['page'], 'wpmzf_') === 0) {
            $page_titles = array(
                'wpmzf_persons' => 'Osoby - Zarządzanie Firmą',
                'wpmzf_companies' => 'Firmy - Zarządzanie Firmą', 
                'wpmzf_projects' => 'Projekty - Zarządzanie Firmą',
            );
            
            if (isset($page_titles[$_GET['page']])) {
                return $page_titles[$_GET['page']];
            }
        }
        
        return $admin_title;
    }
}
