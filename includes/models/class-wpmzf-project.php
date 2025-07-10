<?php

/**
 * Model projektu
 *
 * @package WPMZF
 * @subpackage Models
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPMZF_Project {

    /**
     * ID projektu
     */
    public $id;

    /**
     * Nazwa projektu
     */
    public $name;

    /**
     * Opis projektu
     */
    public $description;

    /**
     * Data rozpoczęcia
     */
    public $start_date;

    /**
     * Data zakończenia
     */
    public $end_date;

    /**
     * Budżet projektu
     */
    public $budget;

    /**
     * ID firmy
     */
    public $company_id;

    /**
     * Status projektu
     */
    public $status;

    /**
     * Konstruktor
     *
     * @param int $id ID projektu
     */
    public function __construct($id = 0) {
        if ($id > 0) {
            $this->load_project($id);
        }
    }

    /**
     * Ładuje dane projektu
     *
     * @param int $id ID projektu
     */
    private function load_project($id) {
        $post = get_post($id);
        if ($post && $post->post_type === 'project') {
            $this->id = $post->ID;
            $this->name = $post->post_title;
            $this->description = $post->post_content;
            $this->start_date = get_post_meta($id, 'start_date', true);
            $this->end_date = get_post_meta($id, 'end_date', true);
            $this->budget = get_post_meta($id, 'budget', true);
            $this->company_id = get_post_meta($id, 'company_id', true);
            $this->status = get_post_meta($id, 'status', true);
        }
    }

    /**
     * Zapisuje projekt
     *
     * @return int|WP_Error ID projektu lub błąd
     */
    public function save() {
        $post_data = array(
            'post_type' => 'project',
            'post_title' => $this->name,
            'post_content' => $this->description,
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
     * Zapisuje meta dane projektu
     */
    private function save_meta() {
        if ($this->id > 0) {
            update_post_meta($this->id, 'start_date', $this->start_date);
            update_post_meta($this->id, 'end_date', $this->end_date);
            update_post_meta($this->id, 'budget', $this->budget);
            update_post_meta($this->id, 'company_id', $this->company_id);
            update_post_meta($this->id, 'status', $this->status);
        }
    }

    /**
     * Usuwa projekt
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
     * Pobiera firmę projektu
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
     * Pobiera wszystkie projekty
     *
     * @param array $args Argumenty zapytania
     * @return array
     */
    public static function get_projects($args = array()) {
        $defaults = array(
            'post_type' => 'project',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        );

        $args = wp_parse_args($args, $defaults);
        $posts = get_posts($args);

        $projects = array();
        foreach ($posts as $post) {
            $projects[] = new self($post->ID);
        }

        return $projects;
    }

    /**
     * Pobiera projekty według statusu
     *
     * @param string $status Status projektu
     * @return array
     */
    public static function get_projects_by_status($status) {
        $args = array(
            'meta_query' => array(
                array(
                    'key' => 'status',
                    'value' => $status,
                    'compare' => '='
                )
            )
        );

        return self::get_projects($args);
    }
}
