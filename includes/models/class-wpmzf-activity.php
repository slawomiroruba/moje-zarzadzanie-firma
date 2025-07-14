<?php

/**
 * Model aktywności
 *
 * @package WPMZF
 * @subpackage Models
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPMZF_Activity {

    /**
     * ID aktywności
     */
    public $id;

    /**
     * Typ aktywności
     */
    public $type;

    /**
     * Opis aktywności
     */
    public $description;

    /**
     * Tablica ID powiązanych obiektów (osoby, firmy, projekty, zadania, etc.)
     * @var array
     */
    public $related_objects;

    /**
     * ID użytkownika
     */
    public $user_id;

    /**
     * Data aktywności
     */
    public $date;

    /**
     * Meta dane
     */
    public $meta;

    /**
     * Konstruktor
     *
     * @param int $id ID aktywności
     */
    public function __construct($id = 0) {
        if ($id > 0) {
            $this->load_activity($id);
        }
    }

    /**
     * Ładuje dane aktywności
     *
     * @param int $id ID aktywności
     */
    private function load_activity($id) {
        $post = get_post($id);
        if ($post && $post->post_type === 'activity') {
            $this->id = $post->ID;
            $this->description = $post->post_content;
            $this->type = get_post_meta($id, 'type', true);
            
            // NOWA LOGIKA: Pobieramy pole relacji z ACF
            $this->related_objects = get_field('related_objects', $id) ?: [];
            
            $this->user_id = get_post_meta($id, 'user_id', true);
            $this->date = get_post_meta($id, 'date', true);
            $this->meta = get_post_meta($id, 'meta', true);
        }
    }

    /**
     * Zapisuje aktywność
     *
     * @return int|WP_Error ID aktywności lub błąd
     */
    public function save() {
        $post_data = array(
            'post_type' => 'activity',
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
     * Zapisuje meta dane aktywności
     */
    private function save_meta() {
        if ($this->id > 0) {
            update_post_meta($this->id, 'type', $this->type);
            
            // NOWA LOGIKA: Zapisujemy pole relacji
            update_field('related_objects', $this->related_objects, $this->id);
            
            update_post_meta($this->id, 'user_id', $this->user_id);
            update_post_meta($this->id, 'date', $this->date);
            update_post_meta($this->id, 'meta', $this->meta);
        }
    }

    /**
     * Usuwa aktywność
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
     * Pobiera tytuł aktywności
     *
     * @return string
     */
    public function get_title() {
        return sprintf('%s - %s', $this->type, $this->date);
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
     * Pobiera wszystkie aktywności
     *
     * @param array $args Argumenty zapytania
     * @return array
     */
    public static function get_activities($args = array()) {
        $defaults = array(
            'post_type' => 'activity',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
        );

        $args = wp_parse_args($args, $defaults);
        $posts = get_posts($args);

        $activities = array();
        foreach ($posts as $post) {
            $activities[] = new self($post->ID);
        }

        return $activities;
    }

    /**
     * Pobiera aktywności dla obiektu
     *
     * @param int $object_id ID obiektu
     * @param string $object_type Typ obiektu (opcjonalny, dla kompatybilności wstecznej)
     * @return array
     */
    public static function get_activities_by_object($object_id, $object_type = null) {
        $args = array(
            'meta_query' => array(
                array(
                    'key' => 'related_objects',
                    'value' => '"' . $object_id . '"', // ACF przechowuje ID w serializowanej tablicy
                    'compare' => 'LIKE'
                )
            )
        );

        return self::get_activities($args);
    }

    /**
     * Loguje aktywność
     *
     * @param string $type Typ aktywności
     * @param string $description Opis aktywności
     * @param array  $related_ids Tablica ID powiązanych obiektów
     * @param array  $meta Meta dane
     * @return int|WP_Error
     */
    public static function log($type, $description, $related_ids = [], $meta = array()) {
        $activity = new self();
        $activity->type = $type;
        $activity->description = $description;
        $activity->related_objects = is_array($related_ids) ? $related_ids : [$related_ids]; // Zapewniamy tablicę
        $activity->user_id = get_current_user_id();
        $activity->date = current_time('mysql');
        $activity->meta = $meta;

        return $activity->save();
    }
}
