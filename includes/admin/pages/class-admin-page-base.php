<?php

/**
 * Bazowa klasa dla wszystkich stron administracyjnych
 */
abstract class WPMZF_Admin_Page_Base
{
    /**
     * Hook suffix dla strony
     */
    protected $hook_suffix;

    /**
     * Slug strony
     */
    protected $page_slug;

    /**
     * Tytuł strony
     */
    protected $page_title;

    /**
     * Tytuł menu
     */
    protected $menu_title;

    /**
     * Wymagane uprawnienia
     */
    protected $capability = 'manage_options';

    /**
     * Konstruktor
     */
    public function __construct()
    {
        $this->init();
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    /**
     * Inicjalizacja strony
     */
    abstract protected function init();

    /**
     * Renderowanie strony
     */
    abstract public function render();

    /**
     * Ładowanie zasobów CSS/JS
     */
    public function enqueue_assets($hook)
    {
        if ($hook !== $this->hook_suffix) {
            return;
        }

        $this->enqueue_styles();
        $this->enqueue_scripts();
    }

    /**
     * Ładowanie stylów CSS
     */
    protected function enqueue_styles()
    {
        // Domyślne style - można nadpisać w klasach potomnych
        wp_enqueue_style(
            'wpmzf-admin-base',
            plugin_dir_url(dirname(dirname(dirname(__FILE__)))) . 'assets/css/admin-styles.css',
            array(),
            '1.0.0'
        );
    }

    /**
     * Ładowanie skryptów JS
     */
    protected function enqueue_scripts()
    {
        // Domyślne skrypty - można nadpisać w klasach potomnych
        wp_enqueue_script('jquery');
    }

    /**
     * Sprawdza czy bieżący hook to hook tej strony
     */
    protected function is_current_page($hook)
    {
        return $hook === $this->hook_suffix;
    }

    /**
     * Pobiera URL do pliku w katalogu assets
     */
    protected function get_asset_url($file)
    {
        return plugin_dir_url(dirname(dirname(dirname(__FILE__)))) . 'assets/' . $file;
    }

    /**
     * Pobiera ścieżkę do widoku
     */
    protected function get_view_path($view)
    {
        return dirname(__FILE__) . '/../views/' . $view;
    }

    /**
     * Ustawia hook suffix po dodaniu strony do menu
     */
    public function set_hook_suffix($hook_suffix)
    {
        $this->hook_suffix = $hook_suffix;
    }

    /**
     * Pobiera slug strony
     */
    public function get_page_slug()
    {
        return $this->page_slug;
    }

    /**
     * Pobiera tytuł strony
     */
    public function get_page_title()
    {
        return $this->page_title;
    }

    /**
     * Pobiera tytuł menu
     */
    public function get_menu_title()
    {
        return $this->menu_title;
    }

    /**
     * Pobiera wymagane uprawnienia
     */
    public function get_capability()
    {
        return $this->capability;
    }
}
