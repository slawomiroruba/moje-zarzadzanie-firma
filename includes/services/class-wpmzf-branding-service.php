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
        
        // Dodaj custom branding i ukryj WordPress elementy
        add_action('login_head', array(__CLASS__, 'add_custom_login_branding'));
        add_filter('login_message', array(__CLASS__, 'custom_login_message'));
        add_action('login_footer', array(__CLASS__, 'add_custom_login_footer'));
        
        // Usuń domyślne WordPress linki z logowania
        add_filter('login_headerurl', array(__CLASS__, 'login_logo_url'));
        add_action('login_head', array(__CLASS__, 'remove_wp_branding'));
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

    /**
     * Dodaje custom branding do strony logowania
     */
    public static function add_custom_login_branding()
    {
        echo '<style type="text/css">
            body.login {
                --brand-primary: #667eea;
                --brand-secondary: #764ba2;
                --brand-accent: #ffffff;
            }
            
            /* Ukryj domyślne WordPress elementy */
            .login #nav a[href*="wp-login.php?action=register"] { display: none !important; }
            .login #nav a[href*="lostpassword"] { display: none !important; }
            .login #backtoblog { display: none !important; }
            
            /* Dodaj branding watermark */
            body.login::after {
                content: "Powered by LunaApp" !important;
                position: fixed !important;
                bottom: 20px !important;
                right: 20px !important;
                color: rgba(255, 255, 255, 0.5) !important;
                font-size: 11px !important;
                font-weight: 500 !important;
                z-index: 1000 !important;
                text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2) !important;
            }
        </style>' . "\n";
    }

    /**
     * Ukrywa WordPress branding
     */
    public static function remove_wp_branding()
    {
        echo '<style type="text/css">
            .login #nav a[href$="/wp-login.php?action=lostpassword"] { display: none !important; }
            .login #nav a[href$="/wp-login.php?action=register"] { display: none !important; }
            .login .privacy-policy-page-link { display: none !important; }
        </style>' . "\n";
    }

    /**
     * Dodaje custom wiadomość powitalną
     */
    public static function custom_login_message($message)
    {
        if (empty($message)) {
            return '<div class="custom-login-welcome" style="
                background: rgba(255, 255, 255, 0.15);
                backdrop-filter: blur(15px);
                border: 1px solid rgba(255, 255, 255, 0.3);
                border-radius: 12px;
                padding: 20px;
                margin-bottom: 24px;
                text-align: center;
                color: #ffffff;
                font-weight: 500;
                text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
            ">
                <h3 style="margin: 0 0 8px 0; font-size: 18px; font-weight: 600;">Witaj w LunaApp</h3>
                <p style="margin: 0; font-size: 14px; opacity: 0.9;">Zaloguj się, aby uzyskać dostęp do swojego panelu zarządzania.</p>
            </div>';
        }
        return $message;
    }

    /**
     * Dodaje custom footer do strony logowania
     */
    public static function add_custom_login_footer()
    {
        echo '<script type="text/javascript">
            document.addEventListener("DOMContentLoaded", function() {
                // Ukryj domyślne linki WordPress
                var nav = document.getElementById("nav");
                if (nav) {
                    var links = nav.querySelectorAll("a");
                    links.forEach(function(link) {
                        if (link.href.includes("lostpassword") || link.href.includes("register")) {
                            link.style.display = "none";
                        }
                    });
                }
                
                // Dodaj focus effects
                var inputs = document.querySelectorAll(".login input[type=\"text\"], .login input[type=\"password\"], .login input[type=\"email\"]");
                inputs.forEach(function(input) {
                    input.addEventListener("focus", function() {
                        this.parentElement.style.transform = "scale(1.02)";
                        this.parentElement.style.transition = "transform 0.3s ease";
                    });
                    input.addEventListener("blur", function() {
                        this.parentElement.style.transform = "scale(1)";
                    });
                });
                
                // Dodaj loading state do przycisku
                var submitBtn = document.getElementById("wp-submit");
                if (submitBtn) {
                    submitBtn.addEventListener("click", function() {
                        var originalText = this.value;
                        this.value = "Logowanie...";
                        this.disabled = true;
                        this.style.opacity = "0.8";
                        
                        setTimeout(function() {
                            if (submitBtn) {
                                submitBtn.value = originalText;
                                submitBtn.disabled = false;
                                submitBtn.style.opacity = "1";
                            }
                        }, 3000);
                    });
                }
            });
        </script>' . "\n";
    }
}
