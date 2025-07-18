<?php

/**
 * Zarządca menu administracyjnego
 */
class WPMZF_Admin_Menu_Manager
{
    /**
     * Zarejestrowane strony
     */
    private $pages = array();

    /**
     * Konstruktor
     */
    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        $this->register_pages();
    }

    /**
     * Rejestruje wszystkie strony
     */
    private function register_pages()
    {
        // Załaduj klasy stron
        $this->load_page_classes();

        // Sprawdź czy klasy zostały załadowane
        $required_classes = array(
            'WPMZF_Dashboard_Page',
            'WPMZF_Persons_Page', 
            'WPMZF_Companies_Page',
            'WPMZF_Projects_Page',
            'WPMZF_Documents_Page'
        );

        foreach ($required_classes as $class_name) {
            if (!class_exists($class_name)) {
                error_log("WPMZF: Nie można załadować klasy: {$class_name}");
                return;
            }
        }

        // Zarejestruj strony
        $this->pages = array(
            'dashboard' => new WPMZF_Dashboard_Page(),
            'persons' => new WPMZF_Persons_Page(),
            'companies' => new WPMZF_Companies_Page(),
            'projects' => new WPMZF_Projects_Page(),
            'documents' => new WPMZF_Documents_Page()
        );
    }

    /**
     * Ładuje klasy stron
     */
    private function load_page_classes()
    {
        $page_files = array(
            'class-admin-page-base.php',
            'class-dashboard-page.php',
            'class-persons-page.php',
            'class-companies-page.php',
            'class-projects-page.php',
            'class-documents-page.php'
        );

        foreach ($page_files as $file) {
            $file_path = dirname(__FILE__) . '/../admin/pages/' . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }
    }

    /**
     * Dodaje menu administracyjne
     */
    public function add_admin_menu()
    {
        // Główne menu
        $main_page = $this->pages['dashboard'];
        $hook_suffix = add_menu_page(
            $main_page->get_page_title(),
            $main_page->get_menu_title(),
            $main_page->get_capability(),
            $main_page->get_page_slug(),
            array($main_page, 'render'),
            'dashicons-businessman',
            30
        );
        $main_page->set_hook_suffix($hook_suffix);

        // Podmenu - Dashboard (duplikat dla zachowania logiki)
        $hook_suffix = add_submenu_page(
            $main_page->get_page_slug(),
            $main_page->get_page_title(),
            $main_page->get_menu_title(),
            $main_page->get_capability(),
            $main_page->get_page_slug(),
            array($main_page, 'render')
        );

        // Podmenu - Osoby
        $persons_page = $this->pages['persons'];
        $hook_suffix = add_submenu_page(
            $main_page->get_page_slug(),
            $persons_page->get_page_title(),
            $persons_page->get_menu_title(),
            $persons_page->get_capability(),
            $persons_page->get_page_slug(),
            array($persons_page, 'render')
        );
        $persons_page->set_hook_suffix($hook_suffix);

        // Podmenu - Firmy
        $companies_page = $this->pages['companies'];
        $hook_suffix = add_submenu_page(
            $main_page->get_page_slug(),
            $companies_page->get_page_title(),
            $companies_page->get_menu_title(),
            $companies_page->get_capability(),
            $companies_page->get_page_slug(),
            array($companies_page, 'render')
        );
        $companies_page->set_hook_suffix($hook_suffix);

        // Podmenu - Projekty
        $projects_page = $this->pages['projects'];
        $hook_suffix = add_submenu_page(
            $main_page->get_page_slug(),
            $projects_page->get_page_title(),
            $projects_page->get_menu_title(),
            $projects_page->get_capability(),
            $projects_page->get_page_slug(),
            array($projects_page, 'render')
        );
        $projects_page->set_hook_suffix($hook_suffix);

        // Podmenu - Dokumenty
        $documents_page = $this->pages['documents'];
        $hook_suffix = add_submenu_page(
            $main_page->get_page_slug(),
            $documents_page->get_page_title(),
            $documents_page->get_menu_title(),
            $documents_page->get_capability(),
            $documents_page->get_page_slug(),
            array($documents_page, 'render')
        );
        $documents_page->set_hook_suffix($hook_suffix);

        // Dodatkowe strony specjalne (bez widoczności w menu)
        $this->add_special_pages($main_page->get_page_slug());
    }

    /**
     * Dodaje specjalne strony (ukryte w menu)
     */
    private function add_special_pages($parent_slug)
    {
        // Sprawdź czy strony są poprawnie zainicjalizowane
        if (!isset($this->pages['persons']) || !isset($this->pages['companies']) || !isset($this->pages['projects'])) {
            return;
        }

        // Strona widoku pojedynczej osoby (dla kompatybilności z istniejącymi linkami)
        add_submenu_page(
            '', // pusta strona = ukryta strona (zamiast null dla PHP 8.3+)
            'Widok osoby',
            'Widok osoby',
            'manage_options',
            'wpmzf_view_person',
            array($this->pages['persons'], 'render')
        );

        // Strona widoku pojedynczej firmy
        add_submenu_page(
            '',
            'Widok firmy',
            'Widok firmy',
            'manage_options',
            'wpmzf_view_company',
            array($this->pages['companies'], 'render')
        );

        // Strona widoku pojedynczego projektu
        add_submenu_page(
            '',
            'Widok projektu',
            'Widok projektu',
            'manage_options',
            'wpmzf_view_project',
            array($this->pages['projects'], 'render')
        );
    }

    /**
     * Pobiera zarejestrowane strony
     */
    public function get_pages()
    {
        return $this->pages;
    }

    /**
     * Pobiera konkretną stronę
     */
    public function get_page($key)
    {
        return isset($this->pages[$key]) ? $this->pages[$key] : null;
    }
}
