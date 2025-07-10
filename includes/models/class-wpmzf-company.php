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
        }
    }

    /**
     * Zapisuje firmę
     *
     * @return int|WP_Error ID firmy lub błąd
     */
    public function save() {
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
        }

        return $result;
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
