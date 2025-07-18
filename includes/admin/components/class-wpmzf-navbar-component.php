<?php
/**
 * Samodzielny komponent nawigacji WPMZF
 * Zawiera HTML, CSS i JavaScript w jednej klasie
 *
 * @package WPMZF
 * @subpackage Admin/Components
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPMZF_Navbar_Component {

    /**
     * Instance singleton
     */
    private static $instance = null;
    
    /**
     * Flaga czy navbar został już zainicjalizowany
     */
    private static $initialized = false;
    
    /**
     * Flaga czy navbar został już wyrenderowany
     */
    private static $rendered = false;

    /**
     * Konstruktor prywatny dla singleton
     */
    private function __construct() {
        // Singleton pattern
    }

    /**
     * Zwraca instancję singleton
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Inicjalizuje komponent navbar
     */
    public static function init() {
        error_log('WPMZF Navbar Component: Inicjalizacja...');
        
        if (self::$initialized) {
            error_log('WPMZF Navbar Component: Już zainicjalizowany, pomijam...');
            return;
        }

        $instance = self::get_instance();
        
        error_log('WPMZF Navbar Component: Rejestruję hooki...');
        
        // Rejestruj hooki tylko raz
        add_action('admin_enqueue_scripts', array($instance, 'enqueue_assets'), 999);
        add_action('wp_ajax_wpmzf_global_search', array($instance, 'handle_global_search'));
        
        // Debug: dodaj hook do logowania wszystkich hooków admin_enqueue_scripts
        add_action('admin_enqueue_scripts', function($hook) {
            error_log('WPMZF Debug: admin_enqueue_scripts hook = ' . $hook);
        }, 1);
        
        // Debug: dodaj hook do sprawdzenia czy admin area
        add_action('admin_init', function() {
            error_log('WPMZF Debug: admin_init fired');
        });
        
        error_log('WPMZF Navbar Component: Hooki zarejestrowane!');
        
        self::$initialized = true;
    }

    /**
     * Sprawdza czy jesteśmy na stronie wtyczki
     */
    private function is_wpmzf_page($hook) {
        $wpmzf_hooks = array(
            // Dashboard hooks
            'toplevel_page_wpmzf_dashboard',
            'wpmzf_page_wpmzf_dashboard',
            'moja-firma_page_wpmzf_dashboard',
            
            // Company hooks
            'admin_page_wpmzf_companies', 
            'wpmzf_page_wpmzf_companies',
            'moja-firma_page_wpmzf_companies',
            
            // Person hooks
            'admin_page_wpmzf_persons',
            'wpmzf_page_wpmzf_persons',
            'moja-firma_page_wpmzf_persons',
            
            // Project hooks
            'admin_page_wpmzf_projects',
            'wpmzf_page_wpmzf_projects', 
            'moja-firma_page_wpmzf_projects',
            
            // View hooks
            'admin_page_wpmzf_view_company',
            'admin_page_wpmzf_view_person',
            'admin_page_wpmzf_view_project',
            'admin_page_wpmzf_view_task',
            'admin_page_wpmzf_view_employee',
            'admin_page_wpmzf_view_opportunity',
            'admin_page_wpmzf_view_quote',
            'admin_page_wpmzf_view_invoice',
            'admin_page_wpmzf_view_payment',
            'admin_page_wpmzf_view_contract',
            'admin_page_wpmzf_view_expense',
            'admin_page_wpmzf_view_activity',
            'admin_page_wpmzf_view_time_entry',
            'admin_page_wpmzf_view_important_link'
        );
        
        // Debug: loguj sprawdzenie
        $is_wpmzf = in_array($hook, $wpmzf_hooks) || strpos($hook, 'wpmzf') !== false;
        error_log('WPMZF Navbar Hook Check: ' . $hook . ' -> ' . ($is_wpmzf ? 'TRUE' : 'FALSE'));
        
        return $is_wpmzf;
    }

    /**
     * Ładuje zasoby (CSS i JS)
     */
    public function enqueue_assets($hook) {
        // Debug: loguj hook dla diagnostyki
        error_log('WPMZF Navbar Hook: ' . $hook);
        
        // Ładuj tylko na stronach wtyczki
        if (!$this->is_wpmzf_page($hook)) {
            error_log('WPMZF Navbar: Nie jest stroną wtyczki - ' . $hook);
            return;
        }

        error_log('WPMZF Navbar: Ładowanie assetów dla hook - ' . $hook);

        // Najpierw enqueue base styles aby móc dodać inline CSS
        wp_enqueue_style('admin-bar');
        
        // Dodaj nasze style inline
        $navbar_css = $this->get_css();
        wp_add_inline_style('admin-bar', $navbar_css);
        
        // Enqueue jQuery jeśli nie jest załadowane
        wp_enqueue_script('jquery');
        
        // Dodaj nasz JavaScript inline
        $navbar_js = $this->get_javascript();
        wp_add_inline_script('jquery', $navbar_js);
        
        // Dodaj lokalizację dla JavaScript
        wp_localize_script('jquery', 'wpmzfNavbar', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'adminUrl' => admin_url(),
            'nonce' => wp_create_nonce('wpmzf_navbar_nonce'),
            'searchPlaceholder' => __('Wyszukaj firmy, osoby, projekty...', 'wpmzf'),
            'noResults' => __('Brak wyników', 'wpmzf'),
            'searching' => __('Wyszukiwanie...', 'wpmzf')
        ));
    }

    /**
     * Zwraca CSS dla navbar
     */
    private function get_css() {
        return '
/* WPMZF Navbar Component Styles */
.wpmzf-navbar {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-bottom: 1px solid rgba(255,255,255,0.1);
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    position: relative;
    z-index: 9999;
    margin: 0 0 20px -20px;
    margin-right: -20px;
}

.wpmzf-navbar-container {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 20px;
    max-width: none;
    height: 60px;
}

/* Brand */
.wpmzf-navbar-brand a {
    display: flex;
    align-items: center;
    text-decoration: none;
    color: white;
    font-weight: 600;
    font-size: 18px;
    transition: all 0.3s ease;
}

.wpmzf-navbar-brand a:hover {
    color: rgba(255,255,255,0.9);
    transform: translateY(-1px);
}

.wpmzf-navbar-logo {
    font-size: 24px;
    margin-right: 10px;
}

.wpmzf-navbar-title {
    font-size: 18px;
    font-weight: 600;
}

/* Navigation */
.wpmzf-navbar-nav {
    display: flex;
    align-items: center;
    gap: 0;
    flex: 1;
    justify-content: center;
}

.wpmzf-navbar-item {
    position: relative;
}

.wpmzf-navbar-link {
    display: flex;
    align-items: center;
    padding: 15px 20px;
    color: rgba(255,255,255,0.9);
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s ease;
    border-radius: 8px;
    margin: 0 2px;
}

.wpmzf-navbar-link:hover {
    background: rgba(255,255,255,0.1);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
}

.wpmzf-navbar-icon {
    font-size: 16px;
    margin-right: 8px;
}

.wpmzf-navbar-label {
    font-size: 14px;
}

.wpmzf-navbar-dropdown-arrow {
    margin-left: 6px;
    font-size: 10px;
    transition: transform 0.3s ease;
}

.wpmzf-navbar-item:hover .wpmzf-navbar-dropdown-arrow {
    transform: rotate(180deg);
}

/* Dropdown */
.wpmzf-navbar-dropdown {
    position: absolute;
    top: 100%;
    left: 50%;
    transform: translateX(-50%);
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
    z-index: 10000;
    min-width: 220px;
    padding: 8px 0;
    margin-top: 8px;
    border: 1px solid rgba(0,0,0,0.1);
}

.wpmzf-navbar-dropdown::before {
    content: "";
    position: absolute;
    top: -8px;
    left: 50%;
    transform: translateX(-50%);
    width: 0;
    height: 0;
    border-left: 8px solid transparent;
    border-right: 8px solid transparent;
    border-bottom: 8px solid white;
}

.wpmzf-navbar-item:hover .wpmzf-navbar-dropdown {
    opacity: 1;
    visibility: visible;
    transform: translateX(-50%) translateY(0);
}

.wpmzf-navbar-dropdown-item {
    display: flex;
    align-items: center;
    padding: 12px 20px;
    color: #333;
    text-decoration: none;
    transition: all 0.2s ease;
    font-size: 14px;
}

.wpmzf-navbar-dropdown-item:hover {
    background: #f8f9fa;
    color: #667eea;
    padding-left: 24px;
}

.wpmzf-navbar-dropdown-icon {
    margin-right: 10px;
    font-size: 14px;
    width: 16px;
    text-align: center;
}

/* Search */
.wpmzf-navbar-search {
    position: relative;
}

.wpmzf-search-container {
    position: relative;
    width: 300px;
}

.wpmzf-search-input {
    width: 100%;
    padding: 10px 45px 10px 15px;
    border: 2px solid rgba(255,255,255,0.2);
    border-radius: 25px;
    background: rgba(255,255,255,0.1);
    color: white;
    font-size: 14px;
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
}

.wpmzf-search-input::placeholder {
    color: rgba(255,255,255,0.7);
}

.wpmzf-search-input:focus {
    outline: none;
    border-color: rgba(255,255,255,0.4);
    background: rgba(255,255,255,0.15);
    box-shadow: 0 0 20px rgba(255,255,255,0.1);
}

.wpmzf-search-button {
    position: absolute;
    right: 8px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: rgba(255,255,255,0.8);
    cursor: pointer;
    padding: 6px;
    border-radius: 50%;
    transition: all 0.3s ease;
}

.wpmzf-search-button:hover {
    background: rgba(255,255,255,0.1);
    color: white;
}

.wpmzf-search-results {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
    z-index: 10001;
    margin-top: 8px;
    max-height: 400px;
    overflow-y: auto;
    border: 1px solid rgba(0,0,0,0.1);
}

.wpmzf-search-results.show {
    opacity: 1;
    visibility: visible;
}

.wpmzf-search-loading {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    color: #666;
}

.wpmzf-search-loading .dashicons {
    margin-right: 8px;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.wpmzf-search-group {
    border-bottom: 1px solid #eee;
    padding: 8px 0;
}

.wpmzf-search-group:last-child {
    border-bottom: none;
}

.wpmzf-search-group-title {
    padding: 8px 20px;
    font-weight: 600;
    color: #667eea;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    background: #f8f9fa;
    margin: 0;
    border-bottom: 1px solid #eee;
}

.wpmzf-search-item {
    display: flex;
    align-items: center;
    padding: 12px 20px;
    color: #333;
    text-decoration: none;
    transition: all 0.2s ease;
    border-bottom: 1px solid #f0f0f0;
}

.wpmzf-search-item:hover {
    background: #f8f9fa;
    color: #667eea;
    padding-left: 24px;
}

.wpmzf-search-item:last-child {
    border-bottom: none;
}

.wpmzf-search-item-content {
    flex: 1;
}

.wpmzf-search-item-title {
    font-weight: 500;
    margin-bottom: 2px;
}

.wpmzf-search-item-excerpt {
    font-size: 12px;
    color: #666;
    line-height: 1.3;
}

.wpmzf-search-no-results {
    padding: 20px;
    text-align: center;
    color: #666;
    font-style: italic;
}

/* Responsive Design */
@media (max-width: 1200px) {
    .wpmzf-navbar-container {
        padding: 0 15px;
    }
    
    .wpmzf-search-container {
        width: 250px;
    }
    
    .wpmzf-navbar-label {
        display: none;
    }
    
    .wpmzf-navbar-link {
        padding: 15px 12px;
    }
}

@media (max-width: 768px) {
    .wpmzf-navbar-container {
        flex-direction: column;
        height: auto;
        padding: 10px 15px;
        gap: 10px;
    }
    
    .wpmzf-navbar-nav {
        order: 3;
        flex-wrap: wrap;
        justify-content: center;
        gap: 5px;
    }
    
    .wpmzf-navbar-search {
        order: 2;
        width: 100%;
    }
    
    .wpmzf-search-container {
        width: 100%;
        max-width: 400px;
        margin: 0 auto;
    }
    
    .wpmzf-navbar-link {
        padding: 10px 8px;
        font-size: 12px;
    }
    
    .wpmzf-navbar-dropdown {
        position: fixed;
        top: auto;
        left: 10px;
        right: 10px;
        transform: none;
        margin-top: 0;
        border-radius: 8px;
    }
    
    .wpmzf-navbar-dropdown::before {
        display: none;
    }
}

/* Accessibility */
.wpmzf-navbar a:focus,
.wpmzf-search-input:focus,
.wpmzf-search-button:focus {
    outline: 2px solid rgba(255,255,255,0.8);
    outline-offset: 2px;
}

/* Dark mode support */
@media (prefers-color-scheme: dark) {
    .wpmzf-navbar-dropdown,
    .wpmzf-search-results {
        background: #2c3e50;
        border-color: rgba(255,255,255,0.1);
    }
    
    .wpmzf-navbar-dropdown-item,
    .wpmzf-search-item {
        color: #ecf0f1;
    }
    
    .wpmzf-navbar-dropdown-item:hover,
    .wpmzf-search-item:hover {
        background: rgba(255,255,255,0.1);
        color: white;
    }
    
    .wpmzf-search-group-title {
        background: rgba(255,255,255,0.05);
        color: #bdc3c7;
    }
}

/* Animation enhancements */
.wpmzf-navbar-item {
    transition: transform 0.3s ease;
}

.wpmzf-navbar-item:hover {
    transform: translateY(-1px);
}

.wpmzf-search-results {
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
';
    }

    /**
     * Zwraca JavaScript dla navbar
     */
    private function get_javascript() {
        return '
// WPMZF Navbar Component JavaScript
(function($) {
    "use strict";
    
    let searchTimeout;
    let searchXhr;
    
    $(document).ready(function() {
        initNavbar();
    });
    
    function initNavbar() {
        // Inicjalizuj wyszukiwarkę
        initSearch();
        
        // Inicjalizuj dropdown menu
        initDropdowns();
        
        // Inicjalizuj zamykanie przy kliknięciu na zewnątrz
        initOutsideClick();
        
        // Inicjalizuj keyboard navigation
        initKeyboardNavigation();
    }
    
    function initSearch() {
        const $searchInput = $("#wpmzf-global-search");
        const $searchResults = $("#wpmzf-search-results");
        const $searchButton = $(".wpmzf-search-button");
        
        if (!$searchInput.length) return;
        
        // Obsługa wpisywania
        $searchInput.on("input", function() {
            const searchTerm = $(this).val().trim();
            
            // Wyczyść poprzedni timeout
            clearTimeout(searchTimeout);
            
            // Anuluj poprzednie żądanie
            if (searchXhr) {
                searchXhr.abort();
            }
            
            if (searchTerm.length < 2) {
                hideSearchResults();
                return;
            }
            
            // Pokaż loading
            showSearchLoading();
            
            // Opóźnij wyszukiwanie
            searchTimeout = setTimeout(function() {
                performSearch(searchTerm);
            }, 300);
        });
        
        // Obsługa przycisku wyszukiwania
        $searchButton.on("click", function() {
            const searchTerm = $searchInput.val().trim();
            if (searchTerm.length >= 2) {
                performSearch(searchTerm);
            }
        });
        
        // Obsługa Enter
        $searchInput.on("keydown", function(e) {
            if (e.key === "Enter") {
                e.preventDefault();
                const searchTerm = $(this).val().trim();
                if (searchTerm.length >= 2) {
                    performSearch(searchTerm);
                }
            } else if (e.key === "Escape") {
                hideSearchResults();
            }
        });
        
        // Focus/blur events
        $searchInput.on("focus", function() {
            if ($(this).val().trim().length >= 2) {
                $searchResults.addClass("show");
            }
        });
    }
    
    function performSearch(searchTerm) {
        if (!window.wpmzfNavbar) {
            console.error("wpmzfNavbar object not found");
            return;
        }
        
        searchXhr = $.ajax({
            url: window.wpmzfNavbar.ajaxUrl,
            type: "POST",
            data: {
                action: "wpmzf_global_search",
                search_term: searchTerm,
                nonce: window.wpmzfNavbar.nonce
            },
            success: function(response) {
                if (response.success) {
                    displaySearchResults(response.data);
                } else {
                    displaySearchError(response.data || "Błąd wyszukiwania");
                }
            },
            error: function(xhr, status, error) {
                if (status !== "abort") {
                    displaySearchError("Błąd połączenia");
                }
            },
            complete: function() {
                searchXhr = null;
            }
        });
    }
    
    function showSearchLoading() {
        const $results = $("#wpmzf-search-results");
        const $content = $results.find(".wpmzf-search-content");
        const $loading = $results.find(".wpmzf-search-loading");
        
        $content.empty();
        $loading.show();
        $results.addClass("show");
    }
    
    function displaySearchResults(results) {
        const $results = $("#wpmzf-search-results");
        const $content = $results.find(".wpmzf-search-content");
        const $loading = $results.find(".wpmzf-search-loading");
        
        $loading.hide();
        $content.empty();
        
        if (!results || results.length === 0) {
            $content.html("<div class=\"wpmzf-search-no-results\">" + (window.wpmzfNavbar.noResults || "Brak wyników") + "</div>");
        } else {
            results.forEach(function(group) {
                const $group = $("<div>", { class: "wpmzf-search-group" });
                const $title = $("<div>", { 
                    class: "wpmzf-search-group-title",
                    text: group.label + " (" + group.count + ")"
                });
                
                $group.append($title);
                
                group.items.forEach(function(item) {
                    const $item = $("<a>", {
                        href: item.url,
                        class: "wpmzf-search-item"
                    });
                    
                    const $itemContent = $("<div>", { class: "wpmzf-search-item-content" });
                    const $itemTitle = $("<div>", { 
                        class: "wpmzf-search-item-title",
                        text: item.title
                    });
                    
                    $itemContent.append($itemTitle);
                    
                    if (item.excerpt) {
                        const $itemExcerpt = $("<div>", {
                            class: "wpmzf-search-item-excerpt",
                            text: item.excerpt
                        });
                        $itemContent.append($itemExcerpt);
                    }
                    
                    $item.append($itemContent);
                    $group.append($item);
                });
                
                $content.append($group);
            });
        }
        
        $results.addClass("show");
    }
    
    function displaySearchError(message) {
        const $results = $("#wpmzf-search-results");
        const $content = $results.find(".wpmzf-search-content");
        const $loading = $results.find(".wpmzf-search-loading");
        
        $loading.hide();
        $content.html("<div class=\"wpmzf-search-no-results\">" + message + "</div>");
        $results.addClass("show");
    }
    
    function hideSearchResults() {
        $("#wpmzf-search-results").removeClass("show");
    }
    
    function initDropdowns() {
        const $dropdownItems = $(".wpmzf-navbar-item");
        
        $dropdownItems.each(function() {
            const $item = $(this);
            const $dropdown = $item.find(".wpmzf-navbar-dropdown");
            
            if ($dropdown.length) {
                let hoverTimeout;
                
                $item.on("mouseenter", function() {
                    clearTimeout(hoverTimeout);
                    $dropdown.addClass("show");
                });
                
                $item.on("mouseleave", function() {
                    hoverTimeout = setTimeout(function() {
                        $dropdown.removeClass("show");
                    }, 100);
                });
            }
        });
    }
    
    function initOutsideClick() {
        $(document).on("click", function(e) {
            // Zamknij wyniki wyszukiwania przy kliknięciu na zewnątrz
            if (!$(e.target).closest(".wpmzf-navbar-search").length) {
                hideSearchResults();
            }
            
            // Zamknij dropdown menu przy kliknięciu na zewnątrz
            if (!$(e.target).closest(".wpmzf-navbar-item").length) {
                $(".wpmzf-navbar-dropdown").removeClass("show");
            }
        });
    }
    
    function initKeyboardNavigation() {
        $(document).on("keydown", function(e) {
            if (e.key === "Escape") {
                hideSearchResults();
                $(".wpmzf-navbar-dropdown").removeClass("show");
                $("#wpmzf-global-search").blur();
            }
        });
        
        // Navigation w wynikach wyszukiwania
        let currentSearchIndex = -1;
        
        $("#wpmzf-global-search").on("keydown", function(e) {
            const $results = $("#wpmzf-search-results");
            const $items = $results.find(".wpmzf-search-item");
            
            if (!$results.hasClass("show") || !$items.length) {
                return;
            }
            
            if (e.key === "ArrowDown") {
                e.preventDefault();
                currentSearchIndex = Math.min(currentSearchIndex + 1, $items.length - 1);
                updateSearchSelection($items);
            } else if (e.key === "ArrowUp") {
                e.preventDefault();
                currentSearchIndex = Math.max(currentSearchIndex - 1, -1);
                updateSearchSelection($items);
            } else if (e.key === "Enter" && currentSearchIndex >= 0) {
                e.preventDefault();
                window.location.href = $items.eq(currentSearchIndex).attr("href");
            }
        });
        
        function updateSearchSelection($items) {
            $items.removeClass("selected");
            if (currentSearchIndex >= 0) {
                $items.eq(currentSearchIndex).addClass("selected");
            }
        }
    }
    
})(jQuery);
';
    }

    /**
     * Obsługa AJAX dla globalnego wyszukiwania
     */
    public function handle_global_search() {
        // Sprawdzenie nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wpmzf_navbar_nonce')) {
            wp_send_json_error(__('Błąd bezpieczeństwa', 'wpmzf'));
        }

        $search_term = sanitize_text_field($_POST['search_term']);
        
        if (empty($search_term) || strlen($search_term) < 2) {
            wp_send_json_error(__('Wpisz co najmniej 2 znaki', 'wpmzf'));
        }

        $results = $this->search_all_post_types($search_term);
        
        wp_send_json_success($results);
    }

    /**
     * Wyszukuje we wszystkich typach wpisów
     */
    private function search_all_post_types($search_term) {
        $post_types = array(
            'company' => __('Firmy', 'wpmzf'),
            'person' => __('Osoby', 'wpmzf'),
            'project' => __('Projekty', 'wpmzf'),
            'task' => __('Zadania', 'wpmzf'),
            'employee' => __('Pracownicy', 'wpmzf'),
            'opportunity' => __('Szanse Sprzedaży', 'wpmzf'),
            'quote' => __('Oferty', 'wpmzf'),
            'invoice' => __('Faktury', 'wpmzf'),
            'payment' => __('Płatności', 'wpmzf'),
            'contract' => __('Umowy', 'wpmzf'),
            'expense' => __('Koszty', 'wpmzf'),
            'activity' => __('Aktywności', 'wpmzf'),
            'time_entry' => __('Wpisy Czasu', 'wpmzf'),
            'important_link' => __('Ważne Linki', 'wpmzf')
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
                        'excerpt' => wp_trim_words(get_the_excerpt(), 15)
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
     * Zwraca URL do widoku dla danego typu postu
     */
    private function get_edit_url($post_type, $post_id) {
        // Używamy uniwersalnych widoków jeśli są dostępne
        $universal_views = array('company', 'person', 'project', 'task', 'employee', 'opportunity', 'quote', 'invoice', 'payment', 'contract', 'expense', 'activity', 'time_entry', 'important_link');
        
        if (in_array($post_type, $universal_views)) {
            return admin_url('admin.php?page=wpmzf_view_' . $post_type . '&' . $post_type . '_id=' . $post_id);
        }
        
        // Fallback do standardowego WordPress edytora
        return admin_url('post.php?post=' . $post_id . '&action=edit');
    }

    /**
     * Renderuje HTML navbar
     */
    public function render() {
        // Zabezpieczenie przed podwójnym renderowaniem
        if (self::$rendered) {
            error_log('WPMZF Navbar: Już wyrenderowano, pomijam...');
            return;
        }
        
        error_log('WPMZF Navbar: Rozpoczynam renderowanie...');
        
        // Force wywołanie enqueue_assets
        $this->enqueue_assets('wpmzf-render');
        error_log('WPMZF Navbar: Assety załadowane');
        
        $menu_items = $this->get_menu_items();
        error_log('WPMZF Navbar: Menu items pobrany: ' . count($menu_items));
        ?>
        <div class="wpmzf-navbar">
            <div class="wpmzf-navbar-container">
                <!-- Logo i Dashboard -->
                <div class="wpmzf-navbar-brand">
                    <a href="<?php echo admin_url('admin.php?page=wpmzf_dashboard'); ?>">
                        <span class="wpmzf-navbar-logo">🏢</span>
                        <span class="wpmzf-navbar-title"><?php _e('Zarządzanie Firmą', 'wpmzf'); ?></span>
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
                        <input type="text" 
                               id="wpmzf-global-search" 
                               placeholder="<?php _e('Wyszukaj...', 'wpmzf'); ?>" 
                               class="wpmzf-search-input" 
                               autocomplete="off">
                        <button class="wpmzf-search-button" type="button">
                            <span class="dashicons dashicons-search"></span>
                        </button>
                        <div id="wpmzf-search-results" class="wpmzf-search-results">
                            <div class="wpmzf-search-loading" style="display: none;">
                                <span class="dashicons dashicons-update"></span>
                                <?php _e('Wyszukiwanie...', 'wpmzf'); ?>
                            </div>
                            <div class="wpmzf-search-content"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        
        error_log('WPMZF Navbar: Renderowanie HTML zakończone!');
        self::$rendered = true;
        error_log('WPMZF Navbar: Flaga rendered ustawiona na true');
    }

    /**
     * Zwraca elementy menu
     */
    private function get_menu_items() {
        return array(
            array(
                'label' => __('CRM', 'wpmzf'),
                'icon' => '👥',
                'url' => admin_url('admin.php?page=wpmzf_companies'),
                'dropdown' => array(
                    array(
                        'label' => __('Wszystkie firmy', 'wpmzf'),
                        'icon' => '🏢',
                        'url' => admin_url('admin.php?page=wpmzf_companies')
                    ),
                    array(
                        'label' => __('Dodaj firmę', 'wpmzf'),
                        'icon' => '➕',
                        'url' => admin_url('post-new.php?post_type=company')
                    ),
                    array(
                        'label' => __('Wszystkie osoby', 'wpmzf'),
                        'icon' => '👤',
                        'url' => admin_url('admin.php?page=wpmzf_persons')
                    ),
                    array(
                        'label' => __('Dodaj osobę', 'wpmzf'),
                        'icon' => '➕',
                        'url' => admin_url('post-new.php?post_type=person')
                    ),
                    array(
                        'label' => __('Szanse sprzedaży', 'wpmzf'),
                        'icon' => '📈',
                        'url' => admin_url('edit.php?post_type=opportunity')
                    ),
                    array(
                        'label' => __('Oferty', 'wpmzf'),
                        'icon' => '📄',
                        'url' => admin_url('edit.php?post_type=quote')
                    )
                )
            ),
            array(
                'label' => __('Projekty', 'wpmzf'),
                'icon' => '📁',
                'url' => admin_url('admin.php?page=wpmzf_projects'),
                'dropdown' => array(
                    array(
                        'label' => __('Wszystkie projekty', 'wpmzf'),
                        'icon' => '📁',
                        'url' => admin_url('admin.php?page=wpmzf_projects')
                    ),
                    array(
                        'label' => __('Dodaj projekt', 'wpmzf'),
                        'icon' => '➕',
                        'url' => admin_url('post-new.php?post_type=project')
                    ),
                    array(
                        'label' => __('Zadania', 'wpmzf'),
                        'icon' => '✅',
                        'url' => admin_url('edit.php?post_type=task')
                    ),
                    array(
                        'label' => __('Dodaj zadanie', 'wpmzf'),
                        'icon' => '➕',
                        'url' => admin_url('post-new.php?post_type=task')
                    ),
                    array(
                        'label' => __('Czas pracy', 'wpmzf'),
                        'icon' => '⏱️',
                        'url' => admin_url('edit.php?post_type=time_entry')
                    )
                )
            ),
            array(
                'label' => __('Finanse', 'wpmzf'),
                'icon' => '💰',
                'url' => admin_url('edit.php?post_type=invoice'),
                'dropdown' => array(
                    array(
                        'label' => __('Faktury', 'wpmzf'),
                        'icon' => '🧾',
                        'url' => admin_url('edit.php?post_type=invoice')
                    ),
                    array(
                        'label' => __('Dodaj fakturę', 'wpmzf'),
                        'icon' => '➕',
                        'url' => admin_url('post-new.php?post_type=invoice')
                    ),
                    array(
                        'label' => __('Płatności', 'wpmzf'),
                        'icon' => '💳',
                        'url' => admin_url('edit.php?post_type=payment')
                    ),
                    array(
                        'label' => __('Koszty', 'wpmzf'),
                        'icon' => '💸',
                        'url' => admin_url('edit.php?post_type=expense')
                    ),
                    array(
                        'label' => __('Umowy', 'wpmzf'),
                        'icon' => '📜',
                        'url' => admin_url('edit.php?post_type=contract')
                    )
                )
            ),
            array(
                'label' => __('Zespół', 'wpmzf'),
                'icon' => '👨‍💼',
                'url' => admin_url('edit.php?post_type=employee'),
                'dropdown' => array(
                    array(
                        'label' => __('Pracownicy', 'wpmzf'),
                        'icon' => '👨‍💼',
                        'url' => admin_url('edit.php?post_type=employee')
                    ),
                    array(
                        'label' => __('Dodaj pracownika', 'wpmzf'),
                        'icon' => '➕',
                        'url' => admin_url('post-new.php?post_type=employee')
                    ),
                    array(
                        'label' => __('Aktywności', 'wpmzf'),
                        'icon' => '📝',
                        'url' => admin_url('edit.php?post_type=activity')
                    )
                )
            ),
            array(
                'label' => __('Narzędzia', 'wpmzf'),
                'icon' => '🔧',
                'url' => admin_url('edit.php?post_type=important_link'),
                'dropdown' => array(
                    array(
                        'label' => __('Ważne linki', 'wpmzf'),
                        'icon' => '🔗',
                        'url' => admin_url('edit.php?post_type=important_link')
                    ),
                    array(
                        'label' => __('Dodaj link', 'wpmzf'),
                        'icon' => '➕',
                        'url' => admin_url('post-new.php?post_type=important_link')
                    )
                )
            )
        );
    }

    /**
     * Metoda statyczna do renderowania - dla kompatybilności
     */
    public static function render_navbar() {
        error_log('WPMZF Navbar Component: render_navbar() wywołane przez View Helper');
        // Reset flagi - może zostać ustawiona z poprzednich testów
        self::$rendered = false;
        $instance = self::get_instance();
        $instance->render();
        error_log('WPMZF Navbar Component: render_navbar() zakończone');
    }

    /**
     * Resetuje flagę renderowania - dla testów
     */
    public static function reset_render_flag() {
        self::$rendered = false;
    }
}
