<?php

/**
 * Klasa obsługująca branding aplikacji (favicon, stylowanie logowania)
 */
class WPMZF_Branding_Service
{
    /**
     * Inicjalizuje serwis brandingu
     */
    public static function init()
    {
        // Dodaj favicon i meta tagi do wszystkich stron
        add_action('wp_head', array(__CLASS__, 'add_favicon_and_meta_tags'));
        add_action('admin_head', array(__CLASS__, 'add_favicon_and_meta_tags'));
        add_action('login_head', array(__CLASS__, 'add_favicon_and_meta_tags'));

        // Stylowanie strony logowania
        add_action('login_enqueue_scripts', array(__CLASS__, 'login_styles'));
        add_filter('login_headerurl', array(__CLASS__, 'login_logo_url'));
        add_filter('login_headertext', array(__CLASS__, 'login_logo_title'));
        
        // Dodaj meta tag viewport do logowania
        add_action('login_head', array(__CLASS__, 'add_login_viewport'));
    }

    /**
     * Dodaje favicon i meta tagi
     */
    public static function add_favicon_and_meta_tags()
    {
        $site_url = get_site_url();
        
        // Standard favicon
        echo '<link rel="icon" type="image/png" href="' . $site_url . '/favicon-96x96.png" sizes="96x96" />' . "\n";
        echo '<link rel="icon" type="image/svg+xml" href="' . $site_url . '/favicon.svg" />' . "\n";
        echo '<link rel="shortcut icon" href="' . $site_url . '/favicon.ico" />' . "\n";
        
        // Apple specific
        echo '<link rel="apple-touch-icon" sizes="180x180" href="' . $site_url . '/apple-touch-icon.png" />' . "\n";
        echo '<meta name="apple-mobile-web-app-title" content="LunaApp" />' . "\n";
        echo '<meta name="apple-mobile-web-app-capable" content="yes" />' . "\n";
        echo '<meta name="apple-mobile-web-app-status-bar-style" content="default" />' . "\n";
        
        // PWA manifest
        echo '<link rel="manifest" href="' . $site_url . '/site.webmanifest" />' . "\n";
        
        // Theme colors
        echo '<meta name="theme-color" content="#ffffff" />' . "\n";
        echo '<meta name="msapplication-TileColor" content="#ffffff" />' . "\n";
        
        // Additional meta tags for better app experience
        echo '<meta name="mobile-web-app-capable" content="yes" />' . "\n";
        echo '<meta name="application-name" content="LunaApp" />' . "\n";
    }

    /**
     * Dodaje style do strony logowania
     */
    public static function login_styles()
    {
        $plugin_url = plugin_dir_url(dirname(dirname(__FILE__)));
        
        // Załącz główne style wtyczki
        wp_enqueue_style('wpmzf-login-styles', $plugin_url . 'assets/css/login-styles.css', array(), '1.0.0');
    }

    /**
     * Zmienia URL logo na stronie logowania
     */
    public static function login_logo_url()
    {
        return home_url();
    }

    /**
     * Zmienia tytuł logo na stronie logowania
     */
    public static function login_logo_title()
    {
        return get_bloginfo('name') . ' - LunaApp';
    }

    /**
     * Dodaje viewport meta tag do strony logowania
     */
    public static function add_login_viewport()
    {
        echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">' . "\n";
    }
}
