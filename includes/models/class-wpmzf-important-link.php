<?php

/**
 * Model ważnych linków
 *
 * @package WPMZF
 * @subpackage Models
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPMZF_Important_Link {

    /**
     * ID linku
     */
    public $id;

    /**
     * URL linku
     */
    public $url;

    /**
     * Niestandardowy opis linku
     */
    public $custom_title;

    /**
     * Automatycznie pobrany tytuł strony
     */
    public $fetched_title;

    /**
     * URL ikony strony (favicon)
     */
    public $favicon_url;

    /**
     * ID powiązanego obiektu
     */
    public $object_id;

    /**
     * Typ powiązanego obiektu (person/company)
     */
    public $object_type;

    /**
     * ID użytkownika który dodał link
     */
    public $user_id;

    /**
     * Data utworzenia
     */
    public $created_at;

    /**
     * Konstruktor
     *
     * @param int $id ID linku
     */
    public function __construct($id = 0) {
        if ($id > 0) {
            $this->load_link($id);
        }
    }

    /**
     * Ładuje dane linku
     *
     * @param int $id ID linku
     */
    private function load_link($id) {
        $post = get_post($id);
        if ($post && $post->post_type === 'important_link') {
            $this->id = $post->ID;
            $this->url = get_post_meta($id, 'url', true);
            $this->custom_title = get_post_meta($id, 'custom_title', true);
            $this->fetched_title = get_post_meta($id, 'fetched_title', true);
            $this->favicon_url = get_post_meta($id, 'favicon_url', true);
            $this->object_id = get_post_meta($id, 'object_id', true);
            $this->object_type = get_post_meta($id, 'object_type', true);
            $this->user_id = get_post_meta($id, 'user_id', true);
            $this->created_at = $post->post_date;
        }
    }

    /**
     * Zapisuje link
     *
     * @return int|WP_Error ID linku lub błąd
     */
    public function save() {
        // Sprawdzenie czy URL jest prawidłowy
        if (empty($this->url) || !filter_var($this->url, FILTER_VALIDATE_URL)) {
            return new WP_Error('invalid_url', 'Nieprawidłowy URL');
        }

        // Pobieranie metadanych strony jeśli nie ma niestandardowego tytułu
        if (empty($this->custom_title) && empty($this->fetched_title)) {
            $this->fetch_page_metadata();
        }

        $post_data = array(
            'post_type' => 'important_link',
            'post_title' => $this->get_display_title(),
            'post_content' => $this->url,
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
     * Zapisuje meta dane linku
     */
    private function save_meta() {
        if ($this->id > 0) {
            update_post_meta($this->id, 'url', $this->url);
            update_post_meta($this->id, 'custom_title', $this->custom_title);
            update_post_meta($this->id, 'fetched_title', $this->fetched_title);
            update_post_meta($this->id, 'favicon_url', $this->favicon_url);
            update_post_meta($this->id, 'object_id', $this->object_id);
            update_post_meta($this->id, 'object_type', $this->object_type);
            update_post_meta($this->id, 'user_id', $this->user_id);
        }
    }

    /**
     * Usuwa link
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
     * Pobiera wyświetlany tytuł
     *
     * @return string
     */
    public function get_display_title() {
        if (!empty($this->custom_title)) {
            return $this->custom_title;
        }
        
        if (!empty($this->fetched_title)) {
            return $this->fetched_title;
        }
        
        return $this->url;
    }

    /**
     * Pobiera favicon URL
     *
     * @return string
     */
    public function get_favicon_url() {
        if (!empty($this->favicon_url)) {
            return $this->favicon_url;
        }
        
        // Fallback do Google Favicon API
        $parsed_url = parse_url($this->url);
        if ($parsed_url && isset($parsed_url['host'])) {
            return 'https://www.google.com/s2/favicons?domain=' . $parsed_url['host'] . '&sz=32';
        }
        
        return '';
    }

    /**
     * Pobiera metadane strony (tytuł i favicon)
     */
    private function fetch_page_metadata() {
        if (empty($this->url)) {
            return;
        }

        // Pobieranie zawartości strony
        $response = wp_remote_get($this->url, array(
            'timeout' => 10,
            'user-agent' => 'Mozilla/5.0 (compatible; WordPress/important-links-plugin)'
        ));

        if (is_wp_error($response)) {
            return;
        }

        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            return;
        }

        // Parsowanie tytułu strony
        if (preg_match('/<title[^>]*>(.*?)<\/title>/i', $body, $matches)) {
            $this->fetched_title = html_entity_decode(trim($matches[1]), ENT_QUOTES, 'UTF-8');
        }

        // Pobieranie URL ikony
        $this->fetch_favicon_url($body);
    }

    /**
     * Pobiera URL favicon ze strony
     *
     * @param string $html_content Zawartość HTML strony
     */
    private function fetch_favicon_url($html_content) {
        $parsed_url = parse_url($this->url);
        if (!$parsed_url || !isset($parsed_url['host'])) {
            return;
        }

        $base_url = $parsed_url['scheme'] . '://' . $parsed_url['host'];

        // Szukanie różnych typów ikon
        $icon_patterns = array(
            '/<link[^>]+rel=["\'](?:shortcut )?icon["\'][^>]+href=["\']([^"\']+)["\'][^>]*>/i',
            '/<link[^>]+href=["\']([^"\']+)["\'][^>]+rel=["\'](?:shortcut )?icon["\'][^>]*>/i',
            '/<link[^>]+rel=["\']apple-touch-icon["\'][^>]+href=["\']([^"\']+)["\'][^>]*>/i'
        );

        foreach ($icon_patterns as $pattern) {
            if (preg_match($pattern, $html_content, $matches)) {
                $favicon_url = $matches[1];
                
                // Konwersja względnych URL na bezwzględne
                if (strpos($favicon_url, '//') === 0) {
                    $favicon_url = $parsed_url['scheme'] . ':' . $favicon_url;
                } elseif (strpos($favicon_url, '/') === 0) {
                    $favicon_url = $base_url . $favicon_url;
                } elseif (!filter_var($favicon_url, FILTER_VALIDATE_URL)) {
                    $favicon_url = $base_url . '/' . $favicon_url;
                }

                $this->favicon_url = $favicon_url;
                return;
            }
        }

        // Fallback - sprawdzenie standardowego /favicon.ico
        $default_favicon = $base_url . '/favicon.ico';
        $response = wp_remote_head($default_favicon);
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $this->favicon_url = $default_favicon;
        }
    }

    /**
     * Pobiera linki dla danego obiektu
     *
     * @param int $object_id ID obiektu
     * @param string $object_type Typ obiektu (person/company)
     * @return array
     */
    public static function get_links_for_object($object_id, $object_type) {
        $args = array(
            'post_type' => 'important_link',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'object_id',
                    'value' => $object_id,
                    'compare' => '='
                ),
                array(
                    'key' => 'object_type',
                    'value' => $object_type,
                    'compare' => '='
                )
            ),
            'orderby' => 'date',
            'order' => 'DESC'
        );

        $query = new WP_Query($args);
        $links = array();

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $links[] = new self(get_the_ID());
            }
        }

        wp_reset_postdata();
        return $links;
    }

    /**
     * Tworzy nowy link
     *
     * @param array $data Dane linku
     * @return WPMZF_Important_Link|WP_Error
     */
    public static function create($data) {
        $link = new self();
        
        // Walidacja wymaganych pól
        if (empty($data['url'])) {
            return new WP_Error('missing_url', 'URL jest wymagany');
        }

        if (empty($data['object_id']) || empty($data['object_type'])) {
            return new WP_Error('missing_object', 'ID i typ obiektu są wymagane');
        }

        $link->url = sanitize_url($data['url']);
        $link->custom_title = !empty($data['custom_title']) ? sanitize_text_field($data['custom_title']) : '';
        $link->object_id = intval($data['object_id']);
        $link->object_type = sanitize_text_field($data['object_type']);
        $link->user_id = get_current_user_id();

        $result = $link->save();
        
        if (is_wp_error($result)) {
            return $result;
        }

        return $link;
    }
}
