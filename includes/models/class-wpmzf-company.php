<?php

/**
 * Model firmy
 *
 * @package WPMZF
 * @subpackage Models
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPMZF_Company {

    /**
     * ID firmy
     */
    public $id;

    /**
     * Nazwa firmy
     */
    public $name;

    /**
     * NIP firmy
     */
    public $nip;

    /**
     * Adres firmy
     */
    public $address;

    /**
     * Telefon firmy
     */
    public $phone;

    /**
     * Email firmy
     */
    public $email;

    /**
     * Strona internetowa firmy
     */
    public $website;

    /**
     * Status firmy
     */
    public $status;

    /**
     * Konstruktor
     *
     * @param int $id ID firmy
     */
    public function __construct($id = 0) {
        if ($id > 0) {
            $this->load_company($id);
        }
    }

    /**
     * Ładuje dane firmy
     *
     * @param int $id ID firmy
     */
    private function load_company($id) {
        $post = get_post($id);
        if ($post && $post->post_type === 'company') {
            $this->id = $post->ID;
            $this->name = $post->post_title;
            $this->nip = get_post_meta($id, 'nip', true);
            $this->address = get_post_meta($id, 'address', true);
            $this->phone = get_post_meta($id, 'phone', true);
            $this->email = get_post_meta($id, 'email', true);
            $this->website = get_post_meta($id, 'website', true);
            $this->status = get_post_meta($id, 'company_status', true);
            if (empty($this->status)) {
                $this->status = 'Aktywny';
            }
        }
    }

    /**
     * Zapisuje firmę
     *
     * @return int|WP_Error ID firmy lub błąd
     */
    public function save() {
        // Walidacja przed zapisem
        $validation = $this->validate();
        if (is_array($validation)) {
            return new WP_Error('validation_failed', 'Błędy walidacji: ' . implode(', ', $validation));
        }
        
        // Sanityzacja danych przed zapisem
        $this->sanitize();
        
        $post_data = array(
            'post_type' => 'company',
            'post_title' => $this->name,
            'post_status' => 'publish',
        );

        if ($this->id > 0) {
            $post_data['ID'] = $this->id;
            $result = wp_update_post($post_data);
        } else {
            $result = wp_insert_post($post_data);
        }

        if (!is_wp_error($result)) {
            $this->id = $result;
            $this->save_meta();
            
            // Log successful save
            WPMZF_Logger::info('Company saved successfully', ['company_id' => $this->id, 'name' => $this->name]);
        } else {
            WPMZF_Logger::error('Failed to save company', ['error' => $result->get_error_message()]);
        }

        return $result;
    }

    /**
     * Waliduje dane firmy
     *
     * @return array|bool true jeśli dane są poprawne, array z błędami w przeciwnym razie
     */
    public function validate() {
        $errors = [];

        // Walidacja nazwy firmy
        if (empty($this->name) || strlen(trim($this->name)) < 2) {
            $errors['name'] = 'Nazwa firmy musi mieć co najmniej 2 znaki';
        }

        // Walidacja NIP-u (opcjonalna ale jeśli podana, to musi być poprawna)
        if (!empty($this->nip)) {
            $nip_clean = preg_replace('/[^0-9]/', '', $this->nip);
            if (strlen($nip_clean) !== 10) {
                $errors['nip'] = 'NIP musi składać się z 10 cyfr';
            }
        }

        // Walidacja email (opcjonalna ale jeśli podana, to musi być poprawna)
        if (!empty($this->email) && !is_email($this->email)) {
            $errors['email'] = 'Niepoprawny format email';
        }

        // Walidacja telefonu (opcjonalna ale jeśli podana, to musi być poprawna)
        if (!empty($this->phone)) {
            $phone_clean = preg_replace('/[^0-9+\-\s]/', '', $this->phone);
            if (strlen($phone_clean) < 9) {
                $errors['phone'] = 'Niepoprawny numer telefonu';
            }
        }

        // Walidacja strony internetowej
        if (!empty($this->website) && !filter_var($this->website, FILTER_VALIDATE_URL)) {
            $errors['website'] = 'Niepoprawny format strony internetowej';
        }

        // Walidacja statusu
        $allowed_statuses = ['Aktywny', 'Nieaktywny', 'Zarchiwizowany'];
        if (!empty($this->status) && !in_array($this->status, $allowed_statuses)) {
            $errors['status'] = 'Niepoprawny status firmy';
        }

        return empty($errors) ? true : $errors;
    }

    /**
     * Sanityzuje dane przed zapisem
     */
    public function sanitize() {
        $this->name = sanitize_text_field($this->name);
        $this->nip = sanitize_text_field($this->nip);
        $this->address = sanitize_textarea_field($this->address);
        $this->phone = sanitize_text_field($this->phone);
        $this->email = sanitize_email($this->email);
        $this->website = esc_url_raw($this->website);
        $this->status = sanitize_text_field($this->status);
    }

    /**
     * Zapisuje meta dane firmy
     */
    private function save_meta() {
        if ($this->id > 0) {
            update_post_meta($this->id, 'nip', $this->nip);
            update_post_meta($this->id, 'address', $this->address);
            update_post_meta($this->id, 'phone', $this->phone);
            update_post_meta($this->id, 'email', $this->email);
            update_post_meta($this->id, 'website', $this->website);
            update_post_meta($this->id, 'company_status', $this->status);
        }
    }

    /**
     * Usuwa firmę
     *
     * @return bool|WP_Post
     */
    public function delete() {
        if ($this->id > 0) {
            return wp_delete_post($this->id, true);
        }
        return false;
    }

    /**
     * Pobiera wszystkie firmy
     *
     * @param array $args Argumenty zapytania
     * @return array
     */
    public static function get_companies($args = array()) {
        $defaults = array(
            'post_type' => 'company',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        );

        $args = wp_parse_args($args, $defaults);
        $posts = get_posts($args);

        $companies = array();
        foreach ($posts as $post) {
            $companies[] = new self($post->ID);
        }

        return $companies;
    }
}
