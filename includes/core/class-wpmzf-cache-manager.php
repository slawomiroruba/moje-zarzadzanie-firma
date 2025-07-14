<?php

/**
 * Manager cache dla optymalizacji zapytań bazodanowych
 *
 * @package WPMZF
 * @subpackage Core
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPMZF_Cache_Manager {

    /**
     * Prefix dla kluczy cache
     */
    const CACHE_PREFIX = 'wpmzf_';

    /**
     * Domyślny czas życia cache (w sekundach)
     */
    const DEFAULT_EXPIRATION = 3600; // 1 godzina

    /**
     * Grupy cache
     */
    const CACHE_GROUPS = [
        'persons' => 'wpmzf_persons',
        'companies' => 'wpmzf_companies',
        'projects' => 'wpmzf_projects',
        'tasks' => 'wpmzf_tasks',
        'activities' => 'wpmzf_activities',
        'stats' => 'wpmzf_stats'
    ];

    /**
     * Inicjalizuje cache manager
     */
    public static function init() {
        // Rejestruj grupy cache w WordPress
        foreach (self::CACHE_GROUPS as $group) {
            wp_cache_add_global_groups($group);
        }

        // Dodaj hook do czyszczenia cache przy zapisie postów
        add_action('save_post', [__CLASS__, 'clear_related_cache'], 10, 2);
        add_action('delete_post', [__CLASS__, 'clear_related_cache'], 10, 2);
        add_action('wp_trash_post', [__CLASS__, 'clear_related_cache'], 10, 2);
        
        // Dodaj hook do czyszczenia cache przy aktualizacji meta pól
        add_action('updated_post_meta', [__CLASS__, 'clear_post_cache'], 10, 4);
        add_action('added_post_meta', [__CLASS__, 'clear_post_cache'], 10, 4);
        add_action('deleted_post_meta', [__CLASS__, 'clear_post_cache'], 10, 4);
    }

    /**
     * Pobiera wartość z cache
     *
     * @param string $key Klucz cache
     * @param string $group Grupa cache
     * @return mixed|false Wartość z cache lub false jeśli nie istnieje
     */
    public static function get($key, $group = 'default') {
        $cache_key = self::CACHE_PREFIX . $key;
        $cache_group = self::CACHE_GROUPS[$group] ?? 'default';
        
        $value = wp_cache_get($cache_key, $cache_group);
        
        if ($value !== false) {
            WPMZF_Logger::debug('Cache hit', ['key' => $cache_key, 'group' => $cache_group]);
        } else {
            WPMZF_Logger::debug('Cache miss', ['key' => $cache_key, 'group' => $cache_group]);
        }
        
        return $value;
    }

    /**
     * Zapisuje wartość do cache
     *
     * @param string $key Klucz cache
     * @param mixed $value Wartość do zapisania
     * @param string $group Grupa cache
     * @param int $expiration Czas życia cache w sekundach
     * @return bool
     */
    public static function set($key, $value, $group = 'default', $expiration = self::DEFAULT_EXPIRATION) {
        $cache_key = self::CACHE_PREFIX . $key;
        $cache_group = self::CACHE_GROUPS[$group] ?? 'default';
        
        $result = wp_cache_set($cache_key, $value, $cache_group, $expiration);
        
        WPMZF_Logger::debug('Cache set', [
            'key' => $cache_key,
            'group' => $cache_group,
            'expiration' => $expiration,
            'success' => $result
        ]);
        
        return $result;
    }

    /**
     * Usuwa wartość z cache
     *
     * @param string $key Klucz cache
     * @param string $group Grupa cache
     * @return bool
     */
    public static function delete($key, $group = 'default') {
        $cache_key = self::CACHE_PREFIX . $key;
        $cache_group = self::CACHE_GROUPS[$group] ?? 'default';
        
        $result = wp_cache_delete($cache_key, $cache_group);
        
        WPMZF_Logger::debug('Cache delete', [
            'key' => $cache_key,
            'group' => $cache_group,
            'success' => $result
        ]);
        
        return $result;
    }

    /**
     * Czyści całą grupę cache
     *
     * @param string $group Grupa cache
     * @return bool
     */
    public static function flush_group($group) {
        $cache_group = self::CACHE_GROUPS[$group] ?? 'default';
        
        // WordPress nie ma natywnej funkcji do czyszczenia grup,
        // więc używamy workaround poprzez increment salt
        $salt_key = $cache_group . '_salt';
        $current_salt = wp_cache_get($salt_key, 'wpmzf_salts') ?: 0;
        wp_cache_set($salt_key, $current_salt + 1, 'wpmzf_salts');
        
        WPMZF_Logger::info('Cache group flushed', ['group' => $cache_group]);
        
        return true;
    }

    /**
     * Pobiera dane osoby z cache lub z bazy danych
     *
     * @param int $person_id ID osoby
     * @return array|null
     */
    public static function get_person_data($person_id) {
        $cache_key = "person_data_{$person_id}";
        $cached_data = self::get($cache_key, 'persons');
        
        if ($cached_data !== false) {
            return $cached_data;
        }

        // Pobierz z bazy danych
        $person = get_post($person_id);
        if (!$person || $person->post_type !== 'person') {
            return null;
        }

        $person_data = [
            'id' => $person->ID,
            'title' => $person->post_title,
            'first_name' => get_field('person_first_name', $person_id),
            'last_name' => get_field('person_last_name', $person_id),
            'email' => get_field('person_email', $person_id),
            'phone' => get_field('person_phone', $person_id),
            'position' => get_field('person_position', $person_id),
            'company' => get_field('person_company', $person_id),
            'status' => get_field('person_status', $person_id),
            'date_created' => $person->post_date,
            'date_modified' => $person->post_modified
        ];

        // Zapisz do cache
        self::set($cache_key, $person_data, 'persons');
        
        return $person_data;
    }

    /**
     * Pobiera dane firmy z cache lub z bazy danych
     *
     * @param int $company_id ID firmy
     * @return array|null
     */
    public static function get_company_data($company_id) {
        $cache_key = "company_data_{$company_id}";
        $cached_data = self::get($cache_key, 'companies');
        
        if ($cached_data !== false) {
            return $cached_data;
        }

        // Pobierz z bazy danych
        $company = get_post($company_id);
        if (!$company || $company->post_type !== 'company') {
            return null;
        }

        $company_data = [
            'id' => $company->ID,
            'title' => $company->post_title,
            'nip' => get_field('company_nip', $company_id),
            'address' => get_field('company_address', $company_id),
            'phone' => get_field('company_phone', $company_id),
            'email' => get_field('company_email', $company_id),
            'website' => get_field('company_website', $company_id),
            'status' => get_field('company_status', $company_id),
            'date_created' => $company->post_date,
            'date_modified' => $company->post_modified
        ];

        // Zapisz do cache
        self::set($cache_key, $company_data, 'companies');
        
        return $company_data;
    }

    /**
     * Pobiera statystyki z cache lub oblicza je
     *
     * @param string $stat_type Typ statystyki
     * @param array $params Parametry statystyki
     * @return array
     */
    public static function get_stats($stat_type, $params = []) {
        $cache_key = "stats_{$stat_type}_" . md5(serialize($params));
        $cached_stats = self::get($cache_key, 'stats');
        
        if ($cached_stats !== false) {
            return $cached_stats;
        }

        $stats = [];
        
        switch ($stat_type) {
            case 'dashboard_counts':
                $stats = [
                    'persons_count' => self::count_posts('person'),
                    'companies_count' => self::count_posts('company'),
                    'projects_count' => self::count_posts('project'),
                    'tasks_count' => self::count_posts('task'),
                    'activities_count' => self::count_posts('activity')
                ];
                break;
                
            case 'recent_activities':
                $stats = self::get_recent_activities($params['limit'] ?? 10);
                break;
                
            default:
                $stats = [];
        }

        // Zapisz do cache na krótszy czas dla statystyk
        self::set($cache_key, $stats, 'stats', 300); // 5 minut
        
        return $stats;
    }

    /**
     * Czyści cache związany z danym postem
     *
     * @param int $post_id ID posta
     * @param WP_Post $post Obiekt posta
     */
    public static function clear_related_cache($post_id, $post = null) {
        if (!$post) {
            $post = get_post($post_id);
        }
        
        if (!$post) {
            return;
        }

        $post_type = $post->post_type;
        
        // Wyczyść cache specyficzny dla typu posta
        switch ($post_type) {
            case 'person':
                self::delete("person_data_{$post_id}", 'persons');
                self::flush_group('stats'); // Statystyki mogą być nieaktualne
                break;
                
            case 'company':
                self::delete("company_data_{$post_id}", 'companies');
                self::flush_group('stats');
                break;
                
            case 'project':
                self::delete("project_data_{$post_id}", 'projects');
                self::flush_group('stats');
                break;
                
            case 'task':
                self::delete("task_data_{$post_id}", 'tasks');
                self::flush_group('stats');
                break;
                
            case 'activity':
                self::delete("activity_data_{$post_id}", 'activities');
                self::flush_group('stats');
                break;
        }
        
        WPMZF_Logger::debug('Cache cleared for post', ['post_id' => $post_id, 'post_type' => $post_type]);
    }

    /**
     * Czyści cache posta po aktualizacji meta pól
     *
     * @param int $meta_id ID meta pola
     * @param int $post_id ID posta
     * @param string $meta_key Klucz meta pola
     * @param mixed $meta_value Wartość meta pola
     */
    public static function clear_post_cache($meta_id, $post_id, $meta_key, $meta_value) {
        $post = get_post($post_id);
        if ($post) {
            self::clear_related_cache($post_id, $post);
        }
    }

    /**
     * Oblicza liczbę postów danego typu
     *
     * @param string $post_type Typ posta
     * @return int
     */
    private static function count_posts($post_type) {
        $counts = wp_count_posts($post_type);
        return $counts->publish ?? 0;
    }

    /**
     * Pobiera ostatnie aktywności
     *
     * @param int $limit Liczba aktywności
     * @return array
     */
    private static function get_recent_activities($limit = 10) {
        $activities = get_posts([
            'post_type' => 'activity',
            'posts_per_page' => $limit,
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC'
        ]);

        $activities_data = [];
        foreach ($activities as $activity) {
            $activities_data[] = [
                'id' => $activity->ID,
                'title' => $activity->post_title,
                'content' => wp_trim_words($activity->post_content, 20),
                'date' => $activity->post_date,
                'related_objects' => get_field('related_objects', $activity->ID) ?: []
            ];
        }

        return $activities_data;
    }

    /**
     * Wyczyść wszystkie cache WPMZF
     */
    public static function flush_all() {
        foreach (array_keys(self::CACHE_GROUPS) as $group) {
            self::flush_group($group);
        }
        
        WPMZF_Logger::info('All WPMZF cache flushed');
    }

    /**
     * Pobiera informacje o wykorzystaniu cache
     *
     * @return array
     */
    public static function get_cache_info() {
        $info = [
            'cache_enabled' => wp_using_ext_object_cache(),
            'cache_type' => wp_using_ext_object_cache() ? 'external' : 'internal',
            'groups' => self::CACHE_GROUPS,
            'hits' => 0,
            'misses' => 0
        ];

        // Dodatkowe informacje jeśli dostępne
        if (function_exists('wp_cache_get_cache_info')) {
            $cache_stats = wp_cache_get_cache_info();
            $info = array_merge($info, $cache_stats);
        }

        return $info;
    }
}
