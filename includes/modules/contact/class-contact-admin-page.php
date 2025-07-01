<?php

class Contact_Admin_Page {
    public function __construct() {
        add_action('admin_menu', array($this, 'add_contact_submenu_pages'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    public function add_contact_submenu_pages() {
        add_submenu_page(
            'wpmzf_dashboard', 'Zarządzanie Kontaktami', 'Kontakty',
            'manage_options', 'wpmzf_contacts', array($this, 'render_contacts_page')
        );
        add_submenu_page(
            null, 'Widok Kontaktu', 'Widok Kontaktu',
            'manage_options', 'wpmzf_contact_view', array($this, 'render_single_contact_page')
        );
    }

    // Metoda enqueue_scripts() przeniesiona tutaj...
    
    // Metoda render_contacts_page() przeniesiona tutaj...

    // Metoda render_single_contact_page() przeniesiona tutaj...
}