<?php

/**
 * Model dla Szans Sprzedaży
 *
 * @package WPMZF
 * @subpackage Models
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPMZF_Opportunity {

    /**
     * ID szansy
     * @var int
     */
    private $id;

    /**
     * Post object
     * @var WP_Post
     */
    private $post;

    /**
     * Konstruktor
     *
     * @param int|WP_Post $opportunity ID szansy lub obiekt WP_Post
     */
    public function __construct($opportunity = null) {
        if ($opportunity instanceof WP_Post) {
            $this->post = $opportunity;
            $this->id = $opportunity->ID;
        } elseif (is_numeric($opportunity)) {
            $this->id = $opportunity;
            $this->post = get_post($opportunity);
        }
    }

    /**
     * Zwraca ID szansy
     *
     * @return int
     */
    public function get_id() {
        return $this->id;
    }

    /**
     * Zwraca tytuł szansy
     *
     * @return string
     */
    public function get_title() {
        return $this->post ? $this->post->post_title : '';
    }

    /**
     * Zwraca opis szansy
     *
     * @return string
     */
    public function get_description() {
        return $this->post ? $this->post->post_content : '';
    }

    /**
     * Zwraca ID powiązanej firmy
     *
     * @return int|null
     */
    public function get_company_id() {
        return get_field('opportunity_company', $this->id);
    }

    /**
     * Zwraca obiekt powiązanej firmy
     *
     * @return WPMZF_Company|null
     */
    public function get_company() {
        $company_id = $this->get_company_id();
        if ($company_id) {
            return new WPMZF_Company($company_id);
        }
        return null;
    }

    /**
     * Zwraca wartość szansy
     *
     * @return float
     */
    public function get_value() {
        $value = get_field('opportunity_value', $this->id);
        return $value ? (float)$value : 0;
    }

    /**
     * Zwraca status szansy
     *
     * @return string
     */
    public function get_status() {
        $terms = wp_get_post_terms($this->id, 'opportunity_status');
        return !empty($terms) && !is_wp_error($terms) ? $terms[0]->name : 'Nowa';
    }

    /**
     * Zwraca ID statusu szansy
     *
     * @return int|null
     */
    public function get_status_id() {
        $terms = wp_get_post_terms($this->id, 'opportunity_status');
        return !empty($terms) && !is_wp_error($terms) ? $terms[0]->term_id : null;
    }

    /**
     * Zwraca powód wygranej/przegranej
     *
     * @return string
     */
    public function get_reason() {
        return get_field('opportunity_reason', $this->id) ?: '';
    }

    /**
     * Sprawdza czy szansa została już skonwertowana na projekt
     *
     * @return bool
     */
    public function is_converted() {
        return (bool)get_post_meta($this->id, '_converted_to_project', true);
    }

    /**
     * Zwraca ID projektu, na który została skonwertowana szansa
     *
     * @return int|null
     */
    public function get_converted_project_id() {
        $project_id = get_post_meta($this->id, '_converted_to_project', true);
        return $project_id ? (int)$project_id : null;
    }

    /**
     * Ustawia status szansy
     *
     * @param int $status_id ID statusu
     * @return bool
     */
    public function set_status($status_id) {
        $result = wp_set_object_terms($this->id, $status_id, 'opportunity_status', false);
        return !is_wp_error($result);
    }

    /**
     * Ustawia wartość szansy
     *
     * @param float $value Wartość
     * @return bool
     */
    public function set_value($value) {
        return update_field('opportunity_value', $value, $this->id);
    }

    /**
     * Ustawia powiązaną firmę
     *
     * @param int $company_id ID firmy
     * @return bool
     */
    public function set_company($company_id) {
        return update_field('opportunity_company', $company_id, $this->id);
    }

    /**
     * Ustawia powód wygranej/przegranej
     *
     * @param string $reason Powód
     * @return bool
     */
    public function set_reason($reason) {
        return update_field('opportunity_reason', $reason, $this->id);
    }

    /**
     * Konwertuje szansę na projekt
     *
     * @return int|false ID nowego projektu lub false w przypadku błędu
     */
    public function convert_to_project() {
        if ($this->is_converted()) {
            return false;
        }

        $company_id = $this->get_company_id();
        $company = $this->get_company();
        $company_name = $company ? $company->get_name() : 'Nieznana firma';

        $project_args = [
            'post_type' => 'project',
            'post_title' => 'Projekt dla: ' . $company_name,
            'post_content' => $this->get_description(),
            'post_status' => 'publish',
            'post_author' => get_current_user_id(),
        ];

        $project_id = wp_insert_post($project_args);

        if ($project_id && !is_wp_error($project_id)) {
            // Przypisz firmę do projektu
            if ($company_id) {
                update_field('project_company', $company_id, $project_id);
            }

            // Zaznacz, że szansa została skonwertowana
            update_post_meta($this->id, '_converted_to_project', $project_id);
            
            // Dodaj notatkę o konwersji
            add_post_meta($this->id, 'conversion_note', 'Skonwertowano na projekt ID: ' . $project_id);

            return $project_id;
        }

        return false;
    }

    /**
     * Zwraca wszystkie szanse sprzedaży
     *
     * @param array $args Argumenty zapytania
     * @return array
     */
    public static function get_all($args = []) {
        $default_args = [
            'post_type' => 'opportunity',
            'posts_per_page' => -1,
            'post_status' => 'publish',
        ];

        $args = wp_parse_args($args, $default_args);
        $posts = get_posts($args);

        $opportunities = [];
        foreach ($posts as $post) {
            $opportunities[] = new self($post);
        }

        return $opportunities;
    }

    /**
     * Zwraca szanse według statusu
     *
     * @param int $status_id ID statusu
     * @return array
     */
    public static function get_by_status($status_id) {
        $args = [
            'tax_query' => [
                [
                    'taxonomy' => 'opportunity_status',
                    'field' => 'term_id',
                    'terms' => $status_id,
                ],
            ],
        ];

        return self::get_all($args);
    }

    /**
     * Zwraca szanse powiązane z firmą
     *
     * @param int $company_id ID firmy
     * @return array
     */
    public static function get_by_company($company_id) {
        $args = [
            'meta_query' => [
                [
                    'key' => 'opportunity_company',
                    'value' => $company_id,
                    'compare' => '=',
                ],
            ],
        ];

        return self::get_all($args);
    }
}
