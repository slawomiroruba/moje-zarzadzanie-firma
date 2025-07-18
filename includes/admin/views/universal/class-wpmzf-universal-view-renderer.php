<?php
/**
 * Renderer sekcji dla uniwersalnego widoku
 * 
 * Ta klasa renderuje poszczególne sekcje i komponenty w uniwersalnym widoku.
 * Każdy typ sekcji ma swoją dedykowaną metodę renderowania.
 * 
 * @package WPMZF
 * @subpackage Admin/Views/Universal
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPMZF_Universal_View_Renderer 
{
    /**
     * Główna metoda renderująca sekcję
     */
    public static function render_section($section_key, $section, $object_id, $object_title, $object_fields, $post_type, $config) {
        echo '<div class="universal-section" id="section-' . esc_attr($section_key) . '">';
        
        // Nagłówek sekcji
        echo '<h2 class="universal-section-title">';
        echo esc_html($section['title']);
        
        // Opcjonalne akcje w nagłówku
        if (isset($section['header_actions'])) {
            echo '<div class="section-header-actions">';
            foreach ($section['header_actions'] as $action) {
                printf(
                    '<button type="button" class="%s" data-action="%s">%s</button>',
                    esc_attr($action['class']),
                    esc_attr($action['action']),
                    esc_html($action['label'])
                );
            }
            echo '</div>';
        }
        
        echo '</h2>';
        
        // Zawartość sekcji
        echo '<div class="universal-section-content">';
        
        if (isset($section['fields'])) {
            // Renderuj pola
            self::render_fields($section['fields'], $object_fields, $object_id, $post_type);
        } elseif (isset($section['component'])) {
            // Renderuj komponent
            self::render_component($section['component'], $section_key, $object_id, $object_title, $object_fields, $post_type, $config);
        } elseif (isset($section['custom_callback'])) {
            // Wywołaj custom callback
            call_user_func($section['custom_callback'], $object_id, $object_fields, $post_type);
        }
        
        echo '</div>';
        echo '</div>';
    }

    /**
     * Renderuje pola ACF
     */
    private static function render_fields($field_names, $object_fields, $object_id, $post_type) {
        foreach ($field_names as $field_name) {
            $field_value = isset($object_fields[$field_name]) ? $object_fields[$field_name] : '';
            $field_label = self::get_field_label($field_name, $post_type);
            
            echo '<div class="universal-field-group">';
            echo '<span class="universal-field-label">' . esc_html($field_label) . ':</span>';
            echo '<div class="universal-field-value">';
            
            // Formatuj wartość w zależności od typu pola
            echo self::format_field_value($field_name, $field_value, $object_id);
            
            echo '</div>';
            echo '</div>';
        }
    }

    /**
     * Renderuje komponenty
     */
    private static function render_component($component_type, $section_key, $object_id, $object_title, $object_fields, $post_type, $config) {
        switch ($component_type) {
            case 'related_projects':
                self::render_related_projects($object_id, $post_type);
                break;
                
            case 'important_links':
                self::render_important_links($object_id, $post_type);
                break;
                
            case 'activity_tabs':
                self::render_activity_tabs($object_id, $post_type);
                break;
                
            case 'timeline':
                self::render_timeline($object_id, $post_type);
                break;
                
            case 'tasks':
                self::render_tasks($object_id, $post_type);
                break;
                
            case 'project_tasks':
                self::render_project_tasks($object_id);
                break;
                
            case 'related_entities':
                self::render_related_entities($object_id, $post_type);
                break;
                
            case 'related_documents':
                self::render_related_documents($object_id, $post_type);
                break;
                
            case 'time_entries':
                self::render_time_entries($object_id);
                break;
                
            default:
                echo '<p><em>Nieznany komponent: ' . esc_html($component_type) . '</em></p>';
        }
    }

    /**
     * Formatuje wartość pola
     */
    private static function format_field_value($field_name, $field_value, $object_id) {
        // E-maile
        if (strpos($field_name, 'email') !== false && is_string($field_value)) {
            if (filter_var($field_value, FILTER_VALIDATE_EMAIL)) {
                return '<a href="mailto:' . esc_attr($field_value) . '">' . esc_html($field_value) . '</a>';
            }
        }
        
        // Telefony
        if (strpos($field_name, 'phone') !== false && is_string($field_value)) {
            return '<a href="tel:' . esc_attr(preg_replace('/[^0-9+]/', '', $field_value)) . '">' . esc_html($field_value) . '</a>';
        }
        
        // Strony internetowe
        if (strpos($field_name, 'website') !== false && is_string($field_value)) {
            if (filter_var($field_value, FILTER_VALIDATE_URL)) {
                return '<a href="' . esc_url($field_value) . '" target="_blank">' . esc_html($field_value) . '</a>';
            }
        }
        
        // Daty
        if (strpos($field_name, 'date') !== false && is_string($field_value)) {
            $timestamp = strtotime($field_value);
            if ($timestamp) {
                return date_i18n('j F Y', $timestamp);
            }
        }
        
        // Adresy
        if (strpos($field_name, 'address') !== false && is_array($field_value)) {
            $address_parts = [];
            if (!empty($field_value['street'])) $address_parts[] = $field_value['street'];
            if (!empty($field_value['zip_code'])) $address_parts[] = $field_value['zip_code'];
            if (!empty($field_value['city'])) $address_parts[] = $field_value['city'];
            return !empty($address_parts) ? esc_html(implode(', ', $address_parts)) : 'Brak';
        }
        
        // Relacje (relationship fields)
        if (is_array($field_value) && !empty($field_value)) {
            $links = [];
            foreach ($field_value as $related_id) {
                if (is_numeric($related_id)) {
                    $related_post = get_post($related_id);
                    if ($related_post) {
                        $view_url = self::get_view_url($related_post->post_type, $related_id);
                        if ($view_url) {
                            $links[] = '<a href="' . esc_url($view_url) . '">' . esc_html($related_post->post_title) . '</a>';
                        } else {
                            $links[] = esc_html($related_post->post_title);
                        }
                    }
                }
            }
            return !empty($links) ? implode(', ', $links) : 'Brak';
        }
        
        // Wartość domyślna
        if (empty($field_value)) {
            return 'Brak';
        }
        
        return esc_html($field_value);
    }

    /**
     * Pobiera label pola
     */
    private static function get_field_label($field_name, $post_type) {
        $labels = [
            // Firmy
            'company_nip' => 'NIP',
            'company_email' => 'E-mail',
            'company_phone' => 'Telefon',
            'company_website' => 'Strona WWW',
            'company_address' => 'Adres',
            
            // Osoby
            'person_email' => 'E-mail',
            'person_phone' => 'Telefon', 
            'person_company' => 'Firma',
            'person_position' => 'Stanowisko',
            
            // Projekty
            'project_status' => 'Status',
            'project_start_date' => 'Data rozpoczęcia',
            'project_end_date' => 'Data zakończenia',
            'project_budget' => 'Budżet',
            
            // Zadania
            'task_status' => 'Status',
            'task_priority' => 'Priorytet',
            'task_due_date' => 'Termin',
            'task_assignee' => 'Przypisany do',
            
            // Szanse sprzedaży
            'opportunity_stage' => 'Etap',
            'opportunity_value' => 'Wartość',
            'opportunity_probability' => 'Prawdopodobieństwo',
            'opportunity_close_date' => 'Przewidywane zamknięcie'
        ];
        
        return isset($labels[$field_name]) ? $labels[$field_name] : ucfirst(str_replace('_', ' ', $field_name));
    }

    /**
     * Generuje URL do widoku obiektu
     */
    private static function get_view_url($post_type, $object_id) {
        $configs = WPMZF_Universal_View_Controller::get_all_configs();
        
        if (isset($configs[$post_type])) {
            $config = $configs[$post_type];
            return admin_url('admin.php?page=' . $config['view_slug'] . '&' . $config['param_name'] . '=' . $object_id);
        }
        
        return null;
    }

    /**
     * Renderuje powiązane projekty
     */
    private static function render_related_projects($object_id, $post_type) {
        // Logika pobierania projektów w zależności od typu obiektu
        $projects = [];
        
        if ($post_type === 'company') {
            $projects = WPMZF_Project::get_projects_by_company($object_id);
        } elseif ($post_type === 'person') {
            $projects = WPMZF_Project::get_projects_by_person($object_id);
        }
        
        if (!empty($projects)) {
            echo '<div class="projects-list">';
            foreach ($projects as $project) {
                $view_url = self::get_view_url('project', $project->ID);
                echo '<div class="project-item">';
                if ($view_url) {
                    echo '<a href="' . esc_url($view_url) . '">' . esc_html($project->post_title) . '</a>';
                } else {
                    echo esc_html($project->post_title);
                }
                echo '</div>';
            }
            echo '</div>';
        } else {
            echo '<div class="universal-empty-state">';
            echo '<span class="dashicons dashicons-portfolio"></span>';
            echo '<p>Brak powiązanych projektów</p>';
            echo '</div>';
        }
    }

    /**
     * Renderuje ważne linki
     */
    private static function render_important_links($object_id, $post_type) {
        echo '<div id="important-links-container">';
        echo '<p><em>Ładowanie linków...</em></p>';
        echo '</div>';
        
        // Formularz dodawania linków
        echo '<div id="important-link-form" style="display: none;">';
        echo '<form id="wpmzf-important-link-form">';
        wp_nonce_field('wpmzf_universal_view_nonce', 'wpmzf_link_security');
        echo '<input type="hidden" name="object_id" value="' . esc_attr($object_id) . '">';
        echo '<input type="hidden" name="object_type" value="' . esc_attr($post_type) . '">';
        echo '<div class="form-group">';
        echo '<label for="link-url">URL linku:</label>';
        echo '<input type="url" id="link-url" name="url" required>';
        echo '</div>';
        echo '<button type="submit" class="button button-primary">Dodaj link</button>';
        echo '</form>';
        echo '</div>';
    }

    /**
     * Renderuje zakładki aktywności
     */
    private static function render_activity_tabs($object_id, $post_type) {
        include WPMZF_PLUGIN_PATH . 'includes/admin/views/universal/components/activity-tabs.php';
    }

    /**
     * Renderuje timeline
     */
    private static function render_timeline($object_id, $post_type) {
        echo '<div id="wpmzf-activity-timeline">';
        echo '<p><em>Ładowanie aktywności...</em></p>';
        echo '</div>';
    }

    /**
     * Renderuje zadania
     */
    private static function render_tasks($object_id, $post_type) {
        include WPMZF_PLUGIN_PATH . 'includes/admin/views/universal/components/tasks.php';
    }

    /**
     * Renderuje zadania projektu
     */
    private static function render_project_tasks($object_id) {
        echo '<div id="wpmzf-project-tasks">';
        echo '<p><em>Ładowanie zadań projektu...</em></p>';
        echo '</div>';
    }

    /**
     * Renderuje powiązane encje
     */
    private static function render_related_entities($object_id, $post_type) {
        echo '<div class="related-entities">';
        echo '<p><em>Powiązane obiekty</em></p>';
        echo '</div>';
    }

    /**
     * Renderuje powiązane dokumenty  
     */
    private static function render_related_documents($object_id, $post_type) {
        echo '<div class="related-documents">';
        echo '<p><em>Dokumenty</em></p>';
        echo '</div>';
    }

    /**
     * Renderuje wpisy czasu pracy
     */
    private static function render_time_entries($object_id) {
        echo '<div class="time-entries">';
        echo '<p><em>Rejestr czasu pracy</em></p>';
        echo '</div>';
    }
}
