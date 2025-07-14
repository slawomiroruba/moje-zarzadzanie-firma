<?php
/**
 * Uniwersalny Komponent Timeline
 *
 * Renderuje dynamiczną i edytowalną oś czasu dla różnych kontekstów (firmy, projekty, osoby).
 * Zawiera w sobie logikę, strukturę HTML, style CSS i skrypty JavaScript.
 *
 * @package     WPMZF
 * @subpackage  Admin/Components
 * @version     1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPMZF_Timeline
{
    /**
     * Konfiguracja komponentu.
     * @var array
     */
    private $config;

    /**
     * Wczytane aktywności.
     * @var array
     */
    private $activities = [];

    /**
     * Unikalne ID komponentu dla separacji na stronie.
     * @var string
     */
    private $component_id;

    /**
     * Konstruktor komponentu.
     *
     * @param array $config Konfiguracja, np. ['context' => 'company', 'id' => 123]
     * Dozwolone konteksty: 'company', 'project', 'person'.
     */
    public function __construct($config = [])
    {
        $this->config = wp_parse_args($config, [
            'context' => 'company',
            'id' => 0,
            'limit' => 50,
            'show_add_button' => true,
        ]);

        $this->component_id = 'wpmzf-timeline-' . $this->config['context'] . '-' . $this->config['id'];

        // Zawsze pobieraj aktywności, dla dashboardu ID może być 0
        if ($this->config['context'] === 'dashboard' || $this->config['id'] > 0) {
            $this->fetch_activities();
        }
    }

    /**
     * Główna metoda pobierająca aktywności w zależności od kontekstu.
     * Jest to serce komponentu, które decyduje, co zostanie wyświetlone.
     */
    private function fetch_activities()
    {
        $context = $this->config['context'];
        $id = $this->config['id'];
        
        // Dla dashboardu pobieramy wszystkie aktywności
        if ($context === 'dashboard') {
            $this->fetch_dashboard_activities();
            return;
        }
        
        $related_ids = [$id];

        // Zbierz ID powiązanych obiektów w zależności od kontekstu
        if ($context === 'company') {
            // Dla firmy, pobierz ID powiązanych osób i projektów
            $this->collect_company_related_ids($id, $related_ids);
        } elseif ($context === 'project') {
            // Dla projektu, pobierz ID powiązanej firmy i osób
            $this->collect_project_related_ids($id, $related_ids);
        } elseif ($context === 'person') {
            // Dla osoby, pobierz ID powiązanych firm i projektów
            $this->collect_person_related_ids($id, $related_ids);
        }
        
        $related_ids = array_unique(array_filter($related_ids));

        if (empty($related_ids)) {
            $this->activities = [];
            return;
        }

        // Główne zapytanie o aktywności - UJEDNOLICONE
        $args = [
            'post_type' => 'activity',
            'posts_per_page' => $this->config['limit'],
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => [
                [
                    'key' => 'related_objects',
                    'value' => $related_ids,
                    'compare' => 'IN',
                ],
            ],
        ];

        $query = new WP_Query($args);
        $this->activities = $query->posts;
        wp_reset_postdata();
    }

    /**
     * Pobiera aktywności dla dashboardu (wszystkie najnowsze).
     */
    private function fetch_dashboard_activities()
    {
        $args = [
            'post_type' => 'activity',
            'posts_per_page' => $this->config['limit'],
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC',
        ];

        $query = new WP_Query($args);
        $this->activities = $query->posts;
        wp_reset_postdata();
        
        // Debug: jeśli potrzeba, możemy sprawdzić czy znaleźliśmy aktywności
        // error_log('Dashboard activities found: ' . count($this->activities));
    }

    /**
     * Zbiera ID powiązane z firmą (osoby i projekty).
     */
    private function collect_company_related_ids($company_id, &$related_ids)
    {
        // Pobierz osoby przypisane do firmy
        $persons_query_args = [
            'post_type' => 'person',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => 'person_company',
                    'value' => '"' . $company_id . '"',
                    'compare' => 'LIKE',
                ],
            ],
        ];
        $persons_ids = get_posts($persons_query_args);
        if (!empty($persons_ids)) {
            $related_ids = array_merge($related_ids, $persons_ids);
        }

        // Pobierz projekty przypisane do firmy
        $projects_query_args = [
            'post_type' => 'project',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => 'project_company',
                    'value' => '"' . $company_id . '"',
                    'compare' => 'LIKE',
                ],
            ],
        ];
        $projects_ids = get_posts($projects_query_args);
        if (!empty($projects_ids)) {
            $related_ids = array_merge($related_ids, $projects_ids);
        }
    }

    /**
     * Zbiera ID powiązane z projektem (firmy i osoby).
     */
    private function collect_project_related_ids($project_id, &$related_ids)
    {
        // Pobierz firmy przypisane do projektu
        $project_companies = get_field('project_company', $project_id);
        if (!empty($project_companies)) {
            if (is_array($project_companies)) {
                $related_ids = array_merge($related_ids, $project_companies);
            } else {
                $related_ids[] = $project_companies;
            }
        }

        // Pobierz osoby przypisane do projektu
        $project_persons = get_field('project_person', $project_id);
        if (!empty($project_persons)) {
            if (is_array($project_persons)) {
                $related_ids = array_merge($related_ids, $project_persons);
            } else {
                $related_ids[] = $project_persons;
            }
        }
    }

    /**
     * Zbiera ID powiązane z osobą (firmy i projekty).
     */
    private function collect_person_related_ids($person_id, &$related_ids)
    {
        // Pobierz firmy przypisane do osoby
        $person_companies = get_field('person_company', $person_id);
        if (!empty($person_companies)) {
            if (is_array($person_companies)) {
                $related_ids = array_merge($related_ids, $person_companies);
            } else {
                $related_ids[] = $person_companies;
            }
        }

        // Pobierz projekty, w których uczestniczy osoba (bezpośrednio lub przez firmę)
        $projects_by_person = WPMZF_Project::get_projects_by_person($person_id);
        if (!empty($projects_by_person)) {
            foreach ($projects_by_person as $project) {
                $related_ids[] = $project->get_id();
            }
        }
    }

    /**
     * Renderuje cały komponent.
     */
    public function render()
    {
        $this->render_css();
        $this->render_html();
        $this->render_js();
    }

    /**
     * Renderuje strukturę HTML osi czasu.
     */
    private function render_html()
    {
        ?>
        <div class="wpmzf-timeline-container" id="<?php echo esc_attr($this->component_id); ?>">
            <?php if ($this->config['show_add_button']): ?>
                <div class="timeline-add-header">
                    <button class="button button-primary add-activity-btn" data-context="<?php echo esc_attr($this->config['context']); ?>" data-context-id="<?php echo esc_attr($this->config['id']); ?>">
                        <span class="dashicons dashicons-plus"></span>
                        Dodaj aktywność
                    </button>
                </div>
            <?php endif; ?>

            <div class="timeline-content">
                <?php if (empty($this->activities)): ?>
                    <div class="timeline-empty">
                        <span class="dashicons dashicons-clipboard"></span>
                        <p>Brak aktywności do wyświetlenia.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($this->activities as $post): ?>
                        <?php $this->render_timeline_item($post); ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Renderuje pojedynczy element osi czasu.
     * Metoda jest publiczna, aby umożliwić renderowanie pojedynczego elementu przez AJAX.
     *
     * @param WP_Post $post Obiekt posta aktywności.
     */
    public function render_timeline_item($post)
    {
        $activity_id = $post->ID;
        $author_id = $post->post_author;
        $author_name = get_the_author_meta('display_name', $author_id);
        $avatar_url = get_avatar_url($author_id, ['size' => 40]);
        $activity_date = get_field('activity_date', $activity_id) ?: $post->post_date;
        $type = get_field('activity_type', $activity_id) ?: 'note';
        $content = $post->post_content;

        $icon_map = [
            'notatka' => 'dashicons-edit-page',
            'email' => 'dashicons-email-alt',
            'telefon' => 'dashicons-phone',
            'spotkanie' => 'dashicons-groups',
            'spotkanie-online' => 'dashicons-video-alt3',
        ];
        $type_map = [
            'notatka' => 'Notatka',
            'email' => 'E-mail',
            'telefon' => 'Rozmowa tel.',
            'spotkanie' => 'Spotkanie',
            'spotkanie-online' => 'Spotkanie online',
        ];

        $icon_class = $icon_map[$type] ?? 'dashicons-marker';
        $type_label = $type_map[$type] ?? 'Aktywność';
        
        // Formatowanie daty
        $formatted_date = human_time_diff(strtotime($activity_date), current_time('timestamp')) . ' temu';
        
        // Pobierz wszystkie powiązane obiekty z ujednoliconego pola - UNIWERSALNIE
        $related_objects = get_field('related_objects', $activity_id);
        
        // Zbierz informacje o powiązanych obiektach - uniwersalnie
        $related_info = [];
        
        if (!empty($related_objects)) {
            // ACF może zwracać tablicę obiektów WP_Post lub same ID
            foreach ($related_objects as $related_value) {
                // Pobierz ID z obiektu lub bezpośrednio jeśli to już ID
                $related_id = is_object($related_value) ? $related_value->ID : $related_value;
                
                // Sprawdź czy ID jest poprawne
                if (!$related_id || !is_numeric($related_id) || $related_id <= 0) continue;
                
                // Pobierz post
                $related_post = get_post($related_id);
                if (!$related_post || $related_post->post_status !== 'publish') continue;
                
                // Sprawdź czy tytuł jest poprawny
                $related_title = $related_post->post_title;
                if (!$related_title || $related_title === 'Auto Draft' || $related_title === 'Hello world!') continue;
                
                // Mapowanie ikon i URLi na podstawie typu postu
                $post_type = $related_post->post_type;
                $type_config = $this->get_post_type_config($post_type);
                
                if ($type_config) {
                    // Utwórz URL na podstawie konfiguracji
                    $url = '#';
                    if ($type_config['url']) {
                        $params = $type_config['url']['param'];
                        // Zastąp wartość ID w odpowiednim parametrze
                        foreach ($params as $key => $value) {
                            if (empty($value)) {
                                $params[$key] = $related_id;
                            }
                        }
                        $url = add_query_arg($params, admin_url($type_config['url']['base']));
                    }
                    
                    $related_info[] = [
                        'type' => $post_type,
                        'id' => $related_id,
                        'title' => $related_title,
                        'icon' => $type_config['icon'],
                        'url' => $url
                    ];
                }
            }
        }
        
        ?>
        <div class="timeline-item" data-activity-id="<?php echo esc_attr($activity_id); ?>">
            <div class="timeline-item-header">
                <div class="timeline-meta">
                    <a href="<?php echo esc_url(get_edit_user_link($author_id)); ?>" class="author-link">
                        <img src="<?php echo esc_url($avatar_url); ?>" alt="<?php echo esc_attr($author_name); ?>">
                        <strong><?php echo esc_html($author_name); ?></strong>
                    </a>
                    <span class="timeline-activity-type">
                        <span class="dashicons <?php echo esc_attr($icon_class); ?>"></span>
                        <?php echo esc_html($type_label); ?>
                    </span>
                    <?php if (!empty($related_info)): ?>
                        <span class="timeline-relates-to">
                            • Dotyczy: 
                            <?php foreach ($related_info as $i => $info): ?>
                                <?php if ($i > 0) echo ', '; ?>
                                <a href="<?php echo esc_url($info['url']); ?>" class="relation-link">
                                    <span class="dashicons <?php echo esc_attr($info['icon']); ?>"></span>
                                    <?php echo esc_html($info['title']); ?>
                                </a>
                            <?php endforeach; ?>
                        </span>
                    <?php endif; ?>
                </div>
                <div class="timeline-date"><?php echo esc_html($formatted_date); ?></div>
                <div class="timeline-actions">
                    <button class="button-icon timeline-edit-btn" title="Edytuj">
                        <span class="dashicons dashicons-edit"></span>
                    </button>
                    <button class="button-icon timeline-delete-btn" title="Usuń">
                        <span class="dashicons dashicons-trash"></span>
                    </button>
                </div>
            </div>
            <div class="timeline-body">
                <div class="activity-content-display">
                    <?php echo wp_kses_post($content); ?>
                </div>
                <div class="activity-content-edit" style="display: none;">
                    <textarea class="activity-edit-textarea" rows="5"><?php echo esc_textarea($content); ?></textarea>
                    <div class="timeline-edit-actions">
                        <button class="button button-primary save-activity-edit">Zapisz</button>
                        <button class="button cancel-activity-edit">Anuluj</button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Renderuje style CSS komponentu.
     */
    private function render_css()
    {
        // Sprawdź czy CSS już został załadowany
        static $css_loaded = false;
        if ($css_loaded) {
            return;
        }
        $css_loaded = true;
        
        // Używamy wp_enqueue_style dla lepszego zarządzania stylami
        $css_url = WPMZF_PLUGIN_URL . 'assets/css/timeline.css';
        $css_path = WPMZF_PLUGIN_PATH . 'assets/css/timeline.css';
        
        if (file_exists($css_path)) {
            wp_enqueue_style(
                'wpmzf-timeline-css',
                $css_url,
                [],
                filemtime($css_path) // Wersjonowanie na podstawie czasu modyfikacji pliku
            );
        }
    }

    /**
     * Renderuje skrypty JavaScript dla komponentu.
     */
    private function render_js()
    {
        $nonce = wp_create_nonce('wpmzf_timeline_nonce');
        ?>
        <script>
            jQuery(document).ready(function($) {
                const container = $('#<?php echo esc_js($this->component_id); ?>');
                const ajaxUrl = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
                const nonce = '<?php echo esc_js($nonce); ?>';

                // Edycja wpisu
                container.on('click', '.timeline-edit-btn', function(e) {
                    e.preventDefault();
                    const item = $(this).closest('.timeline-item');
                    item.find('.activity-content-display').hide();
                    item.find('.activity-content-edit').show();
                    item.find('.activity-edit-textarea').focus();
                });

                // Anulowanie edycji
                container.on('click', '.cancel-activity-edit', function(e) {
                    e.preventDefault();
                    const item = $(this).closest('.timeline-item');
                    item.find('.activity-content-edit').hide();
                    item.find('.activity-content-display').show();
                });

                // Zapisywanie edycji (AJAX)
                container.on('click', '.save-activity-edit', function(e) {
                    e.preventDefault();
                    const button = $(this);
                    const item = button.closest('.timeline-item');
                    const activityId = item.data('activity-id');
                    const newContent = item.find('.activity-edit-textarea').val();

                    if (!newContent.trim()) {
                        alert('Treść aktywności nie może być pusta.');
                        return;
                    }

                    button.text('Zapisywanie...').prop('disabled', true);

                    $.ajax({
                        url: ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'wpmzf_update_activity_content',
                            security: nonce,
                            activity_id: activityId,
                            content: newContent
                        },
                        success: function(response) {
                            if (response.success && response.data.html) {
                                // Otrzymujemy nowy HTML i podmieniamy go
                                item.replaceWith(response.data.html);
                            } else {
                                alert('Błąd: ' + (response.data?.message || 'Nieznany błąd'));
                                button.text('Zapisz').prop('disabled', false);
                            }
                        },
                        error: function() {
                            alert('Błąd serwera. Spróbuj ponownie.');
                            button.text('Zapisz').prop('disabled', false);
                        }
                    });
                });

                // Usuwanie aktywności
                container.on('click', '.timeline-delete-btn', function(e) {
                    e.preventDefault();
                    const item = $(this).closest('.timeline-item');
                    const activityId = item.data('activity-id');

                    if (!confirm('Czy na pewno chcesz usunąć tę aktywność?')) {
                        return;
                    }

                    $.ajax({
                        url: ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'wpmzf_delete_activity_timeline',
                            security: nonce,
                            activity_id: activityId
                        },
                        success: function(response) {
                            if (response.success) {
                                item.fadeOut(300, function() {
                                    $(this).remove();
                                    // Sprawdź czy zostały jakieś aktywności
                                    if (container.find('.timeline-item').length === 0) {
                                        container.find('.timeline-content').html(
                                            '<div class="timeline-empty">' +
                                            '<span class="dashicons dashicons-clipboard"></span>' +
                                            '<p>Brak aktywności do wyświetlenia.</p>' +
                                            '</div>'
                                        );
                                    }
                                });
                            } else {
                                alert('Błąd: ' + (response.data?.message || 'Nie udało się usunąć aktywności'));
                            }
                        },
                        error: function() {
                            alert('Błąd serwera. Spróbuj ponownie.');
                        }
                    });
                });
            });
        </script>
        <?php
    }

    /**
     * Metoda statyczna do renderowania pojedynczego elementu - używana przez AJAX.
     *
     * @param int $activity_id ID aktywności
     * @return string HTML elementu
     */
    public static function render_single_item($activity_id)
    {
        $post = get_post($activity_id);
        if (!$post || $post->post_type !== 'activity') {
            return '';
        }

        // Tworzymy tymczasowy komponent tylko do renderowania elementu
        $timeline = new self(['context' => 'none']);
        
        ob_start();
        $timeline->render_timeline_item($post);
        return ob_get_clean();
    }

    /**
     * Pobiera konfigurację dla danego typu postu (ikona, URL).
     */
    private function get_post_type_config($post_type)
    {
        $configs = [
            'person' => [
                'icon' => 'dashicons-admin-users',
                'url' => [
                    'base' => 'admin.php',
                    'param' => ['page' => 'wpmzf_view_person', 'person_id' => '']
                ]
            ],
            'company' => [
                'icon' => 'dashicons-building',
                'url' => [
                    'base' => 'admin.php',
                    'param' => ['page' => 'wpmzf_view_company', 'company_id' => '']
                ]
            ],
            'project' => [
                'icon' => 'dashicons-portfolio',
                'url' => [
                    'base' => 'admin.php',
                    'param' => ['page' => 'wpmzf_view_project', 'project_id' => '']
                ]
            ],
            'task' => [
                'icon' => 'dashicons-list-view',
                'url' => [
                    'base' => 'admin.php',
                    'param' => ['page' => 'wpmzf_view_task', 'task_id' => '']
                ]
            ]
        ];

        // Domyślna konfiguracja dla nieznanych typów
        if (!isset($configs[$post_type])) {
            return [
                'icon' => 'dashicons-admin-post',
                'url' => [
                    'base' => 'post.php',
                    'param' => ['action' => 'edit', 'post' => '']
                ]
            ];
        }

        return $configs[$post_type];
    }
}

// Dodanie obsługi AJAX
if (!has_action('wp_ajax_wpmzf_update_activity_content')) {
    add_action('wp_ajax_wpmzf_update_activity_content', function() {
        check_ajax_referer('wpmzf_timeline_nonce', 'security');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Brak uprawnień.']);
        }

        $activity_id = isset($_POST['activity_id']) ? intval($_POST['activity_id']) : 0;
        $content = isset($_POST['content']) ? wp_kses_post($_POST['content']) : '';

        if (!$activity_id || get_post_type($activity_id) !== 'activity') {
            wp_send_json_error(['message' => 'Nieprawidłowe ID aktywności.']);
        }

        $result = wp_update_post([
            'ID' => $activity_id,
            'post_content' => $content,
        ]);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => 'Nie udało się zaktualizować aktywności.']);
        }
        
        // Wyrenderuj nowy HTML elementu
        $html = WPMZF_Timeline::render_single_item($activity_id);

        wp_send_json_success(['html' => $html]);
    });
}

if (!has_action('wp_ajax_wpmzf_delete_activity_timeline')) {
    add_action('wp_ajax_wpmzf_delete_activity_timeline', function() {
        check_ajax_referer('wpmzf_timeline_nonce', 'security');

        if (!current_user_can('delete_posts')) {
            wp_send_json_error(['message' => 'Brak uprawnień.']);
        }

        $activity_id = isset($_POST['activity_id']) ? intval($_POST['activity_id']) : 0;

        if (!$activity_id || get_post_type($activity_id) !== 'activity') {
            wp_send_json_error(['message' => 'Nieprawidłowe ID aktywności.']);
        }

        $result = wp_delete_post($activity_id, true);

        if (!$result) {
            wp_send_json_error(['message' => 'Nie udało się usunąć aktywności.']);
        }

        wp_send_json_success(['message' => 'Aktywność została usunięta.']);
    });
}
