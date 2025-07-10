<?php

/**
 * Model wpisu czasu
 *
 * @package WPMZF
 * @subpackage Models
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPMZF_Time_Entry {

    /**
     * ID wpisu czasu
     */
    public $id;

    /**
     * ID projektu
     */
    public $project_id;

    /**
     * ID użytkownika
     */
    public $user_id;

    /**
     * Opis aktywności
     */
    public $description;

    /**
     * Czas w minutach
     */
    public $time_minutes;

    /**
     * Data wpisu
     */
    public $date;

    /**
     * Konstruktor
     *
     * @param int $id ID wpisu czasu
     */
    public function __construct($id = 0) {
        if ($id > 0) {
            $this->load_time_entry($id);
        }
    }

    /**
     * Ładuje dane wpisu czasu
     *
     * @param int $id ID wpisu czasu
     */
    private function load_time_entry($id) {
        $post = get_post($id);
        if ($post && $post->post_type === 'time_entry') {
            $this->id = $post->ID;
            $this->description = $post->post_content;
            $this->project_id = get_post_meta($id, 'project_id', true);
            $this->user_id = get_post_meta($id, 'user_id', true);
            $this->time_minutes = get_post_meta($id, 'time_minutes', true);
            $this->date = get_post_meta($id, 'date', true);
        }
    }

    /**
     * Zapisuje wpis czasu
     *
     * @return int|WP_Error ID wpisu czasu lub błąd
     */
    public function save() {
        $post_data = array(
            'post_type' => 'time_entry',
            'post_title' => $this->get_title(),
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
     * Zapisuje meta dane wpisu czasu
     */
    private function save_meta() {
        if ($this->id > 0) {
            update_post_meta($this->id, 'project_id', $this->project_id);
            update_post_meta($this->id, 'user_id', $this->user_id);
            update_post_meta($this->id, 'time_minutes', $this->time_minutes);
            update_post_meta($this->id, 'date', $this->date);
        }
    }

    /**
     * Usuwa wpis czasu
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
     * Pobiera tytuł wpisu
     *
     * @return string
     */
    public function get_title() {
        $hours = floor($this->time_minutes / 60);
        $minutes = $this->time_minutes % 60;
        return sprintf('%dh %dm - %s', $hours, $minutes, $this->date);
    }

    /**
     * Pobiera czas w godzinach
     *
     * @return float
     */
    public function get_hours() {
        return $this->time_minutes / 60;
    }

    /**
     * Pobiera projekt
     *
     * @return WPMZF_Project|null
     */
    public function get_project() {
        if ($this->project_id > 0) {
            return new WPMZF_Project($this->project_id);
        }
        return null;
    }

    /**
     * Pobiera użytkownika
     *
     * @return WP_User|null
     */
    public function get_user() {
        if ($this->user_id > 0) {
            return get_user_by('ID', $this->user_id);
        }
        return null;
    }

    /**
     * Pobiera wszystkie wpisy czasu
     *
     * @param array $args Argumenty zapytania
     * @return array
     */
    public static function get_time_entries($args = array()) {
        $defaults = array(
            'post_type' => 'time_entry',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
        );

        $args = wp_parse_args($args, $defaults);
        $posts = get_posts($args);

        $time_entries = array();
        foreach ($posts as $post) {
            $time_entries[] = new self($post->ID);
        }

        return $time_entries;
    }

    /**
     * Pobiera wpisy czasu dla projektu
     *
     * @param int $project_id ID projektu
     * @return array
     */
    public static function get_entries_by_project($project_id) {
        $args = array(
            'meta_query' => array(
                array(
                    'key' => 'project_id',
                    'value' => $project_id,
                    'compare' => '='
                )
            )
        );

        return self::get_time_entries($args);
    }
}
