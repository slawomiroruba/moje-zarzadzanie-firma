<?php

/**
 * Model osoby
 *
 * @package WPMZF
 * @subpackage Models
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPMZF_Person {

    /**
     * ID osoby
     */
    public $id;

    /**
     * Imię osoby
     */
    public $first_name;

    /**
     * Nazwisko osoby
     */
    public $last_name;

    /**
     * Email osoby
     */
    public $email;

    /**
     * Telefon osoby
     */
    public $phone;

    /**
     * Stanowisko osoby
     */
    public $position;

    /**
     * ID firmy
     */
    public $company_id;

    /**
     * Konstruktor
     *
     * @param int $id ID osoby
     */
    public function __construct($id = 0) {
        if ($id > 0) {
            $this->load_person($id);
        }
    }

    /**
     * Ładuje dane osoby
     *
     * @param int $id ID osoby
     */
    private function load_person($id) {
        $post = get_post($id);
        if ($post && $post->post_type === 'person') {
            $this->id = $post->ID;
            $this->first_name = get_post_meta($id, 'first_name', true);
            $this->last_name = get_post_meta($id, 'last_name', true);
            $this->email = get_post_meta($id, 'email', true);
            $this->phone = get_post_meta($id, 'phone', true);
            $this->position = get_post_meta($id, 'position', true);
            $this->company_id = get_post_meta($id, 'company_id', true);
        }
    }

    /**
     * Zapisuje osobę
     *
     * @return int|WP_Error ID osoby lub błąd
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
            'post_type' => 'person',
            'post_title' => $this->get_full_name(),
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
            WPMZF_Logger::info('Person saved successfully', [
                'person_id' => $this->id, 
                'name' => $this->get_full_name()
            ]);
        } else {
            WPMZF_Logger::error('Failed to save person', ['error' => $result->get_error_message()]);
        }

        return $result;
    }

    /**
     * Waliduje dane osoby
     *
     * @return array|bool true jeśli dane są poprawne, array z błędami w przeciwnym razie
     */
    public function validate() {
        $errors = [];

        // Walidacja imienia
        if (empty($this->first_name) || strlen(trim($this->first_name)) < 2) {
            $errors['first_name'] = 'Imię musi mieć co najmniej 2 znaki';
        }

        // Walidacja nazwiska
        if (empty($this->last_name) || strlen(trim($this->last_name)) < 2) {
            $errors['last_name'] = 'Nazwisko musi mieć co najmniej 2 znaki';
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

        // Walidacja company_id
        if (!empty($this->company_id)) {
            if (!is_numeric($this->company_id) || get_post_type($this->company_id) !== 'company') {
                $errors['company_id'] = 'Niepoprawne ID firmy';
            }
        }

        return empty($errors) ? true : $errors;
    }

    /**
     * Sanityzuje dane przed zapisem
     */
    public function sanitize() {
        $this->first_name = sanitize_text_field($this->first_name);
        $this->last_name = sanitize_text_field($this->last_name);
        $this->email = sanitize_email($this->email);
        $this->phone = sanitize_text_field($this->phone);
        $this->position = sanitize_text_field($this->position);
        $this->company_id = intval($this->company_id);
    }

    /**
     * Zapisuje meta dane osoby
     */
    private function save_meta() {
        if ($this->id > 0) {
            update_post_meta($this->id, 'first_name', $this->first_name);
            update_post_meta($this->id, 'last_name', $this->last_name);
            update_post_meta($this->id, 'email', $this->email);
            update_post_meta($this->id, 'phone', $this->phone);
            update_post_meta($this->id, 'position', $this->position);
            update_post_meta($this->id, 'company_id', $this->company_id);
        }
    }

    /**
     * Usuwa osobę
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
     * Pobiera pełne imię i nazwisko
     *
     * @return string
     */
    public function get_full_name() {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    /**
     * Pobiera firmę osoby
     *
     * @return WPMZF_Company|null
     */
    public function get_company() {
        if ($this->company_id > 0) {
            return new WPMZF_Company($this->company_id);
        }
        return null;
    }

    /**
     * Pobiera wszystkie osoby
     *
     * @param array $args Argumenty zapytania
     * @return array
     */
    public static function get_persons($args = array()) {
        $defaults = array(
            'post_type' => 'person',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        );

        $args = wp_parse_args($args, $defaults);
        $posts = get_posts($args);

        $persons = array();
        foreach ($posts as $post) {
            $persons[] = new self($post->ID);
        }

        return $persons;
    }
}
