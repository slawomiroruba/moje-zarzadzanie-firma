<?php

/**
 * Serwis do obsługi szans sprzedaży
 *
 * @package WPMZF
 * @subpackage Services
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPMZF_Opportunity_Service {

    /**
     * Konstruktor
     */
    public function __construct() {
        // Obsługa AJAX do aktualizacji statusu
        add_action('wp_ajax_wpmzf_update_opportunity_status', array($this, 'ajax_update_opportunity_status'));
        
        // Obsługa konwersji wygranej szansy na projekt
        add_action('save_post', array($this, 'handle_opportunity_conversion'), 10, 2);
        
        // Dodanie custom meta box do szans
        add_action('add_meta_boxes', array($this, 'add_opportunity_meta_boxes'));
    }

    /**
     * Obsługa AJAX do aktualizacji statusu szansy
     */
    public function ajax_update_opportunity_status() {
        check_ajax_referer('wpmzf_kanban_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Brak uprawnień.');
        }

        $post_id = intval($_POST['post_id']);
        $status_id = intval($_POST['status_id']);

        if (!$post_id || !$status_id) {
            wp_send_json_error('Nieprawidłowe parametry.');
        }

        if (get_post_type($post_id) !== 'opportunity') {
            wp_send_json_error('To nie jest szansa sprzedaży.');
        }

        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error('Brak uprawnień do edycji tej szansy.');
        }

        $result = wp_set_object_terms($post_id, $status_id, 'opportunity_status', false);
        
        if (is_wp_error($result)) {
            wp_send_json_error('Błąd podczas aktualizacji statusu.');
        }

        // Sprawdź czy to status "Wygrana" i czy można skonwertować
        $status_term = get_term($status_id);
        if ($status_term && $status_term->name === 'Wygrana') {
            $opportunity = new WPMZF_Opportunity($post_id);
            if (!$opportunity->is_converted()) {
                $project_id = $opportunity->convert_to_project();
                if ($project_id) {
                    wp_send_json_success(array(
                        'message' => 'Status zaktualizowany i utworzono nowy projekt.',
                        'project_id' => $project_id
                    ));
                }
            }
        }

        wp_send_json_success('Status zaktualizowany pomyślnie.');
    }

    /**
     * Obsługa konwersji wygranej szansy na projekt
     */
    public function handle_opportunity_conversion($post_id, $post) {
        // Sprawdź czy to szansa sprzedaży
        if ($post->post_type !== 'opportunity') {
            return;
        }

        // Sprawdź czy to nie jest autosave lub revision
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        $status_terms = wp_get_post_terms($post_id, 'opportunity_status');
        if (is_wp_error($status_terms) || empty($status_terms)) {
            return;
        }

        $status = $status_terms[0]->name;

        // Sprawdź, czy status to "Wygrana" i czy nie zostało już skonwertowane
        if ($status === 'Wygrana') {
            $opportunity = new WPMZF_Opportunity($post_id);
            if (!$opportunity->is_converted()) {
                $project_id = $opportunity->convert_to_project();
                
                if ($project_id) {
                    // Dodaj notyfikację admina
                    add_action('admin_notices', function() use ($post_id, $project_id) {
                        if (get_current_screen()->id === 'edit-opportunity' || 
                            (get_current_screen()->id === 'opportunity' && isset($_GET['post']) && $_GET['post'] == $post_id)) {
                            echo '<div class="notice notice-success is-dismissible">';
                            echo '<p><strong>Sukces!</strong> Szansa została automatycznie skonwertowana na nowy projekt. ';
                            echo '<a href="' . get_edit_post_link($project_id) . '">Zobacz projekt</a></p>';
                            echo '</div>';
                        }
                    });
                }
            }
        }
    }

    /**
     * Dodaje custom meta boxy dla szans sprzedaży
     */
    public function add_opportunity_meta_boxes() {
        add_meta_box(
            'wpmzf_opportunity_conversion_status',
            'Status konwersji',
            array($this, 'render_conversion_status_meta_box'),
            'opportunity',
            'side',
            'high'
        );
    }

    /**
     * Renderuje meta box ze statusem konwersji
     */
    public function render_conversion_status_meta_box($post) {
        $opportunity = new WPMZF_Opportunity($post->ID);
        
        if ($opportunity->is_converted()) {
            $project_id = $opportunity->get_converted_project_id();
            $project_title = get_the_title($project_id);
            
            echo '<div class="misc-pub-section">';
            echo '<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span> ';
            echo '<strong>Skonwertowano na projekt:</strong><br>';
            echo '<a href="' . get_edit_post_link($project_id) . '" target="_blank">' . esc_html($project_title) . '</a>';
            echo '</div>';
        } else {
            $status = $opportunity->get_status();
            if ($status === 'Wygrana') {
                echo '<div class="misc-pub-section">';
                echo '<span class="dashicons dashicons-warning" style="color: #ffb900;"></span> ';
                echo '<strong>Gotowa do konwersji</strong><br>';
                echo 'Ta szansa może zostać skonwertowana na projekt.';
                echo '</div>';
            } else {
                echo '<div class="misc-pub-section">';
                echo '<span class="dashicons dashicons-info"></span> ';
                echo 'Nie skonwertowano jeszcze na projekt.';
                echo '</div>';
            }
        }
    }

    /**
     * Zwraca statystyki szans sprzedaży
     */
    public function get_opportunities_stats() {
        $statuses = get_terms(array(
            'taxonomy' => 'opportunity_status',
            'hide_empty' => false,
        ));

        $stats = array();
        $total_value = 0;
        $total_count = 0;

        foreach ($statuses as $status) {
            $opportunities = WPMZF_Opportunity::get_by_status($status->term_id);
            $count = count($opportunities);
            $value = 0;

            foreach ($opportunities as $opportunity) {
                $value += $opportunity->get_value();
            }

            $stats[$status->name] = array(
                'count' => $count,
                'value' => $value,
                'term_id' => $status->term_id,
            );

            $total_count += $count;
            $total_value += $value;
        }

        return array(
            'by_status' => $stats,
            'total_count' => $total_count,
            'total_value' => $total_value,
        );
    }

    /**
     * Zwraca szanse do zamknięcia w najbliższym czasie
     */
    public function get_opportunities_due_soon($days = 7) {
        $date_limit = date('Y-m-d', strtotime("+{$days} days"));
        
        $args = array(
            'post_type' => 'opportunity',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'opportunity_expected_close_date',
                    'value' => $date_limit,
                    'compare' => '<=',
                    'type' => 'DATE',
                ),
            ),
            'tax_query' => array(
                array(
                    'taxonomy' => 'opportunity_status',
                    'field' => 'name',
                    'terms' => array('Wygrana', 'Przegrana'),
                    'operator' => 'NOT IN',
                ),
            ),
        );

        $opportunities_posts = get_posts($args);
        $opportunities = array();

        foreach ($opportunities_posts as $post) {
            $opportunities[] = new WPMZF_Opportunity($post);
        }

        return $opportunities;
    }

    /**
     * Generuje raport konwersji szans na projekty
     */
    public function get_conversion_report($period = 'month') {
        $date_query = array();
        
        switch ($period) {
            case 'week':
                $date_query = array(
                    'after' => '1 week ago',
                );
                break;
            case 'month':
                $date_query = array(
                    'after' => '1 month ago',
                );
                break;
            case 'year':
                $date_query = array(
                    'after' => '1 year ago',
                );
                break;
        }

        // Pobierz wygrane szanse
        $won_opportunities = get_posts(array(
            'post_type' => 'opportunity',
            'posts_per_page' => -1,
            'date_query' => array($date_query),
            'tax_query' => array(
                array(
                    'taxonomy' => 'opportunity_status',
                    'field' => 'name',
                    'terms' => 'Wygrana',
                ),
            ),
        ));

        $converted_count = 0;
        $total_value = 0;
        
        foreach ($won_opportunities as $post) {
            $opportunity = new WPMZF_Opportunity($post);
            $total_value += $opportunity->get_value();
            
            if ($opportunity->is_converted()) {
                $converted_count++;
            }
        }

        $total_won = count($won_opportunities);
        $conversion_rate = $total_won > 0 ? ($converted_count / $total_won) * 100 : 0;

        return array(
            'period' => $period,
            'total_won_opportunities' => $total_won,
            'converted_to_projects' => $converted_count,
            'conversion_rate' => round($conversion_rate, 2),
            'total_value' => $total_value,
        );
    }
}
