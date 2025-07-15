<?php

/**
 * Model użytkownika
 *
 * @package WPMZF
 * @subpackage Models
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPMZF_User {

    /**
     * ID użytkownika
     */
    public $id;

    /**
     * Imię użytkownika
     */
    public $name;

    /**
     * Email użytkownika
     */
    public $email;

    /**
     * Telefon użytkownika
     */
    public $phone;

    /**
     * Pozycja/stanowisko
     */
    public $position;

    /**
     * Data utworzenia
     */
    public $created_at;

    /**
     * Data modyfikacji
     */
    public $updated_at;

    /**
     * Konstruktor
     *
     * @param array $data Dane użytkownika
     */
    public function __construct($data = []) {
        $this->id = $data['id'] ?? null;
        $this->name = $data['name'] ?? '';
        $this->email = $data['email'] ?? '';
        $this->phone = $data['phone'] ?? '';
        $this->position = $data['position'] ?? '';
        $this->created_at = $data['created_at'] ?? current_time('mysql');
        $this->updated_at = $data['updated_at'] ?? current_time('mysql');
    }
    //mateusz: Przykład prawidłowego użycia classy i funkcji gdyby tak inne wyglądały to byłoby bardzo dobrze

    /**
     * Konwertuje model do tablicy
     *
     * @return array
     */
    public function to_array() {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'position' => $this->position,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Waliduje dane użytkownika
     *
     * @return array|bool true jeśli dane są poprawne, array z błędami w przeciwnym razie
     */
    public function validate() {
        $errors = [];

        if (empty($this->name) || strlen(trim($this->name)) < 2) {
            $errors['name'] = 'Imię musi mieć co najmniej 2 znaki';
        }

        if (empty($this->email)) {
            $errors['email'] = 'Email jest wymagany';
        } elseif (!is_email($this->email)) {
            $errors['email'] = 'Niepoprawny format email';
        } else {
            // Sprawdź czy email jest unikalny
            global $wpdb;
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}wpmzf_users WHERE email = %s AND id != %d",
                $this->email,
                $this->id ?? 0
            ));
            
            if ($existing) {
                $errors['email'] = 'Ten adres email jest już używany';
            }
        }

        // Walidacja telefonu
        if (!empty($this->phone)) {
            $phone_clean = preg_replace('/[^0-9+\-\s]/', '', $this->phone);
            if (strlen($phone_clean) < 9) {
                $errors['phone'] = 'Niepoprawny numer telefonu';
            }
        }

        return empty($errors) ? true : $errors;
    }

    /**
     * Sanitizuje dane przed zapisem
     */
    public function sanitize() {
        $this->name = sanitize_text_field($this->name);
        $this->email = sanitize_email($this->email);
        $this->phone = sanitize_text_field($this->phone);
        $this->position = sanitize_text_field($this->position);
    }
}
