<?php

/**
 * Repository dla ważnych linków
 *
 * @package WPMZF
 * @subpackage Repositories
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPMZF_Important_Link_Repository {

    /**
     * Cache manager
     */
    private $cache_manager;

    /**
     * Performance monitor
     */
    private $performance_monitor;

    /**
     * Konstruktor
     */
    public function __construct() {
        $this->cache_manager = new WPMZF_Cache_Manager();
        $this->performance_monitor = new WPMZF_Performance_Monitor();
    }

    /**
     * Pobiera wszystkie ważne linki
     *
     * @param int $limit Limit wyników
     * @param int $offset Offset
     * @return array
     */
    public function get_all($limit = 50, $offset = 0) {
        $timer_id = $this->performance_monitor->start_timer('important_link_repository_get_all');
        
        try {
            // Sprawdź cache
            $cache_key = "important_links_all_{$limit}_{$offset}";
            $cached_result = $this->cache_manager->get($cache_key);
            if ($cached_result !== false) {
                $this->performance_monitor->end_timer($timer_id);
                return $cached_result;
            }

            // Walidacja parametrów
            $limit = max(1, min(1000, intval($limit))); 
            $offset = max(0, intval($offset));
            
            $args = [
                'post_type' => 'important_link',
                'posts_per_page' => $limit,
                'offset' => $offset,
                'post_status' => 'publish',
                'orderby' => 'date',
                'order' => 'DESC'
            ];
            
            $query = new WP_Query($args);
            
            $links = array_map(function($post) {
                return new WPMZF_Important_Link($post);
            }, $query->posts);

            // Cache wynik na 5 minut
            $this->cache_manager->set($cache_key, $links, 300);
            
            $this->performance_monitor->end_timer($timer_id);
            
            return $links;
            
        } catch (Exception $e) {
            $this->performance_monitor->end_timer($timer_id);
            WPMZF_Logger::error('Error in get_all important links', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Pobiera ważny link po ID
     *
     * @param int $id ID linku
     * @return WPMZF_Important_Link|null
     */
    public function get_by_id($id) {
        $timer_id = $this->performance_monitor->start_timer('important_link_repository_get_by_id');
        
        try {
            // Walidacja parametru
            $id = intval($id);
            if ($id <= 0) {
                throw new InvalidArgumentException('Invalid link ID');
            }

            // Sprawdź cache
            $cache_key = "important_link_{$id}";
            $cached_result = $this->cache_manager->get($cache_key);
            if ($cached_result !== false) {
                $this->performance_monitor->end_timer($timer_id);
                return $cached_result;
            }

            $post = get_post($id);
            
            if (!$post || $post->post_type !== 'important_link') {
                $this->performance_monitor->end_timer($timer_id);
                return null;
            }
            
            $link = new WPMZF_Important_Link($post);

            // Cache wynik na 10 minut
            $this->cache_manager->set($cache_key, $link, 600);
            
            $this->performance_monitor->end_timer($timer_id);
            
            return $link;
            
        } catch (Exception $e) {
            $this->performance_monitor->end_timer($timer_id);
            WPMZF_Logger::error('Error in get_by_id important link', ['error' => $e->getMessage(), 'link_id' => $id]);
            throw $e;
        }
    }

    /**
     * Tworzy nowy ważny link
     *
     * @param WPMZF_Important_Link $link Link do utworzenia
     * @return int|false ID nowego linku lub false przy błędzie
     */
    public function create(WPMZF_Important_Link $link) {
        $timer_id = $this->performance_monitor->start_timer('important_link_repository_create');
        
        try {
            // Walidacja danych linku
            if (!$link->validate()) {
                throw new InvalidArgumentException('Link validation failed');
            }

            $link->sanitize();
            
            $post_data = [
                'post_title' => $link->title,
                'post_content' => $link->description,
                'post_type' => 'important_link',
                'post_status' => 'publish',
                'meta_input' => [
                    'link_url' => $link->url,
                    'link_category' => $link->category,
                    'link_priority' => $link->priority
                ]
            ];
            
            $link_id = wp_insert_post($post_data);
            
            if (is_wp_error($link_id)) {
                WPMZF_Logger::error('Error creating important link', ['error' => $link_id->get_error_message()]);
                throw new Exception('Link creation failed: ' . $link_id->get_error_message());
            }
            
            // Wyczyść cache
            $this->cache_manager->delete_pattern('important_links_*');
            
            WPMZF_Logger::info('Important link created successfully', ['link_id' => $link_id, 'title' => $link->title]);
            
            $this->performance_monitor->end_timer($timer_id);
            
            return $link_id;
            
        } catch (Exception $e) {
            $this->performance_monitor->end_timer($timer_id);
            WPMZF_Logger::error('Error creating important link', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Aktualizuje ważny link
     *
     * @param int $id ID linku
     * @param WPMZF_Important_Link $link Dane linku
     * @return bool
     */
    public function update($id, WPMZF_Important_Link $link) {
        $timer_id = $this->performance_monitor->start_timer('important_link_repository_update');
        
        try {
            // Walidacja parametrów
            $id = intval($id);
            if ($id <= 0) {
                throw new InvalidArgumentException('Invalid link ID');
            }
            
            if (!$link->validate()) {
                throw new InvalidArgumentException('Link validation failed');
            }
            
            // Sprawdź czy link istnieje
            $existing_link = $this->get_by_id($id);
            if (!$existing_link) {
                throw new InvalidArgumentException('Link not found');
            }

            $link->sanitize();
            
            $post_data = [
                'ID' => $id,
                'post_title' => $link->title,
                'post_content' => $link->description,
                'meta_input' => [
                    'link_url' => $link->url,
                    'link_category' => $link->category,
                    'link_priority' => $link->priority
                ]
            ];
            
            $result = wp_update_post($post_data);
            
            if (is_wp_error($result)) {
                WPMZF_Logger::error('Error updating important link', ['error' => $result->get_error_message(), 'link_id' => $id]);
                throw new Exception('Link update failed: ' . $result->get_error_message());
            }
            
            // Wyczyść cache
            $this->cache_manager->delete_pattern('important_links_*');
            $this->cache_manager->delete("important_link_{$id}");
            
            WPMZF_Logger::info('Important link updated successfully', ['link_id' => $id, 'title' => $link->title]);
            
            $this->performance_monitor->end_timer($timer_id);
            
            return true;
            
        } catch (Exception $e) {
            $this->performance_monitor->end_timer($timer_id);
            WPMZF_Logger::error('Error updating important link', ['error' => $e->getMessage(), 'link_id' => $id]);
            throw $e;
        }
    }

    /**
     * Usuwa ważny link
     *
     * @param int $id ID linku
     * @return bool
     */
    public function delete($id) {
        $timer_id = $this->performance_monitor->start_timer('important_link_repository_delete');
        
        try {
            // Walidacja parametru
            $id = intval($id);
            if ($id <= 0) {
                throw new InvalidArgumentException('Invalid link ID');
            }
            
            // Sprawdź czy link istnieje
            $existing_link = $this->get_by_id($id);
            if (!$existing_link) {
                throw new InvalidArgumentException('Link not found');
            }

            $result = wp_delete_post($id, true);
            
            if (!$result) {
                WPMZF_Logger::error('Error deleting important link', ['link_id' => $id]);
                throw new Exception('Link deletion failed');
            }
            
            // Wyczyść cache
            $this->cache_manager->delete_pattern('important_links_*');
            $this->cache_manager->delete("important_link_{$id}");
            
            WPMZF_Logger::info('Important link deleted successfully', ['link_id' => $id, 'title' => $existing_link->title]);
            
            $this->performance_monitor->end_timer($timer_id);
            
            return true;
            
        } catch (Exception $e) {
            $this->performance_monitor->end_timer($timer_id);
            WPMZF_Logger::error('Error deleting important link', ['error' => $e->getMessage(), 'link_id' => $id]);
            throw $e;
        }
    }

    /**
     * Liczy wszystkie ważne linki
     *
     * @return int
     */
    public function count() {
        $timer_id = $this->performance_monitor->start_timer('important_link_repository_count');
        
        try {
            // Sprawdź cache
            $cache_key = "important_links_count";
            $cached_result = $this->cache_manager->get($cache_key);
            if ($cached_result !== false) {
                $this->performance_monitor->end_timer($timer_id);
                return $cached_result;
            }

            $count = wp_count_posts('important_link');
            $total = intval($count->publish);

            // Cache wynik na 5 minut
            $this->cache_manager->set($cache_key, $total, 300);
            
            $this->performance_monitor->end_timer($timer_id);
            
            return $total;
            
        } catch (Exception $e) {
            $this->performance_monitor->end_timer($timer_id);
            WPMZF_Logger::error('Error counting important links', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Pobiera linki według kategorii
     *
     * @param string $category Kategoria
     * @param int $limit Limit wyników
     * @return array
     */
    public function get_by_category($category, $limit = 50) {
        $timer_id = $this->performance_monitor->start_timer('important_link_repository_get_by_category');
        
        try {
            // Sprawdź cache
            $cache_key = "important_links_category_{$category}_{$limit}";
            $cached_result = $this->cache_manager->get($cache_key);
            if ($cached_result !== false) {
                $this->performance_monitor->end_timer($timer_id);
                return $cached_result;
            }

            $args = [
                'post_type' => 'important_link',
                'posts_per_page' => max(1, min(1000, intval($limit))),
                'post_status' => 'publish',
                'meta_query' => [
                    [
                        'key' => 'link_category',
                        'value' => sanitize_text_field($category),
                        'compare' => '='
                    ]
                ],
                'orderby' => 'meta_value_num',
                'meta_key' => 'link_priority',
                'order' => 'DESC'
            ];
            
            $query = new WP_Query($args);
            
            $links = array_map(function($post) {
                return new WPMZF_Important_Link($post);
            }, $query->posts);

            // Cache wynik na 10 minut
            $this->cache_manager->set($cache_key, $links, 600);
            
            $this->performance_monitor->end_timer($timer_id);
            
            return $links;
            
        } catch (Exception $e) {
            $this->performance_monitor->end_timer($timer_id);
            WPMZF_Logger::error('Error in get_by_category important links', ['error' => $e->getMessage(), 'category' => $category]);
            throw $e;
        }
    }
}
