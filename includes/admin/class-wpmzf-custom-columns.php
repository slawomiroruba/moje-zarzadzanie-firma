<?php
// Plik: moje-zarzadzanie-firma/includes/admin/class-wpmzf-custom-columns.php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ta klasa-serwis obsługuje niestandardowe kolumny w listach administratora.
 */
class WPMZF_Custom_Columns_Service {

    /**
     * Konstruktor inicjalizuje hooks.
     */
    public function __construct() {
        $this->init_hooks();
    }

    /**
     * Inicjalizuje hooks dla custom columns.
     */
    public function init_hooks() {
        // Dodaj custom columns dla różnych post types
        add_filter('manage_company_posts_columns', array($this, 'add_company_columns'));
        add_action('manage_company_posts_custom_column', array($this, 'populate_company_columns'), 10, 2);
        
        add_filter('manage_person_posts_columns', array($this, 'add_person_columns'));
        add_action('manage_person_posts_custom_column', array($this, 'populate_person_columns'), 10, 2);
        
        add_filter('manage_project_posts_columns', array($this, 'add_project_columns'));
        add_action('manage_project_posts_custom_column', array($this, 'populate_project_columns'), 10, 2);
    }

    /**
     * Dodaje kolumny dla firm
     */
    public function add_company_columns($columns) {
        $new_columns = array();
        $new_columns['cb'] = $columns['cb'];
        $new_columns['title'] = $columns['title'];
        $new_columns['company_status'] = 'Status';
        $new_columns['company_nip'] = 'NIP';
        $new_columns['company_email'] = 'Email';
        $new_columns['company_phone'] = 'Telefon';
        $new_columns['company_referrer'] = 'Polecający';
        $new_columns['date'] = $columns['date'];
        return $new_columns;
    }

    /**
     * Wypełnia kolumny dla firm
     */
    public function populate_company_columns($column, $post_id) {
        switch ($column) {
            case 'company_status':
                $status = get_field('company_status', $post_id) ?: 'Aktywny';
                $status = $status ?? 'Aktywny'; // dodatkowa ochrona przed null
                $status_class = 'status-' . strtolower(str_replace(' ', '-', $status));
                echo "<span class='company-status-badge {$status_class}'>{$status}</span>";
                break;
            case 'company_nip':
                echo get_field('company_nip', $post_id) ?: '-';
                break;
            case 'company_email':
                $email = get_field('company_email', $post_id);
                echo $email ? "<a href='mailto:$email'>$email</a>" : '-';
                break;
            case 'company_phone':
                $phone = get_field('company_phone', $post_id);
                echo $phone ? "<a href='tel:$phone'>$phone</a>" : '-';
                break;
            case 'company_referrer':
                $referrer = get_field('company_referrer', $post_id);
                if ($referrer && is_array($referrer) && !empty($referrer)) {
                    $referrer_post = get_post($referrer[0]);
                    if ($referrer_post) {
                        $referrer_type = get_post_type($referrer_post->ID) === 'company' ? '🏢' : '👤';
                        echo $referrer_type . ' ' . esc_html($referrer_post->post_title);
                    } else {
                        echo '-';
                    }
                } else {
                    echo '-';
                }
                break;
        }
    }

    /**
     * Dodaje kolumny dla osób
     */
    public function add_person_columns($columns) {
        $new_columns = array();
        $new_columns['cb'] = $columns['cb'];
        $new_columns['title'] = $columns['title'];
        $new_columns['person_email'] = 'Email';
        $new_columns['person_phone'] = 'Telefon';
        $new_columns['person_company'] = 'Firma';
        $new_columns['person_referrer'] = 'Polecający';
        $new_columns['date'] = $columns['date'];
        return $new_columns;
    }

    /**
     * Wypełnia kolumny dla osób
     */
    public function populate_person_columns($column, $post_id) {
        switch ($column) {
            case 'person_email':
                $emails = get_field('person_emails', $post_id);
                if ($emails && is_array($emails)) {
                    foreach ($emails as $email) {
                        if ($email['is_primary']) {
                            echo "<a href='mailto:{$email['email_address']}'>{$email['email_address']}</a>";
                            return;
                        }
                    }
                }
                echo '-';
                break;
            case 'person_phone':
                $phones = get_field('person_phones', $post_id);
                if ($phones && is_array($phones)) {
                    foreach ($phones as $phone) {
                        if ($phone['is_primary']) {
                            echo "<a href='tel:{$phone['phone_number']}'>{$phone['phone_number']}</a>";
                            return;
                        }
                    }
                }
                echo '-';
                break;
            case 'person_company':
                $company = get_field('person_company', $post_id);
                if ($company) {
                    echo get_the_title($company);
                } else {
                    echo '-';
                }
                break;
            case 'person_referrer':
                $referrer = get_field('person_referrer', $post_id);
                if ($referrer && is_array($referrer) && !empty($referrer)) {
                    $referrer_post = get_post($referrer[0]);
                    if ($referrer_post) {
                        $referrer_type = get_post_type($referrer_post->ID) === 'company' ? '🏢' : '👤';
                        echo $referrer_type . ' ' . esc_html($referrer_post->post_title);
                    } else {
                        echo '-';
                    }
                } else {
                    echo '-';
                }
                break;
        }
    }

    /**
     * Dodaje kolumny dla projektów
     */
    public function add_project_columns($columns) {
        $new_columns = array();
        $new_columns['cb'] = $columns['cb'];
        $new_columns['title'] = $columns['title'];
        $new_columns['project_status'] = 'Status';
        $new_columns['project_client'] = 'Klient';
        $new_columns['project_budget'] = 'Budżet';
        $new_columns['date'] = $columns['date'];
        return $new_columns;
    }

    /**
     * Wypełnia kolumny dla projektów
     */
    public function populate_project_columns($column, $post_id) {
        switch ($column) {
            case 'project_status':
                $status = get_field('project_status', $post_id);
                echo $status ? ucfirst($status) : '-';
                break;
            case 'project_client':
                $client = get_field('project_client', $post_id);
                if ($client) {
                    echo get_the_title($client);
                } else {
                    echo '-';
                }
                break;
            case 'project_budget':
                $budget = get_field('project_budget', $post_id);
                echo $budget ? number_format($budget, 2) . ' zł' : '-';
                break;
        }
    }
}