<?php

/**
 * Klasa do migracji aktywności ze starych pól na nowe
 *
 * @package WPMZF
 * @subpackage Utils
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPMZF_Activity_Migration {

    /**
     * Migruje aktywności ze starych pól (related_person, related_company, related_project, related_task)
     * na nowe ujednolicone pole (related_objects)
     *
     * @return array Raport z migracji
     */
    public static function migrate_activities() {
        $report = [
            'total_activities' => 0,
            'migrated_activities' => 0,
            'errors' => [],
            'details' => []
        ];

        // Pobierz wszystkie aktywności
        $activities = get_posts([
            'post_type' => 'activity',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => 'related_person',
                    'compare' => 'EXISTS'
                ],
                [
                    'key' => 'related_company',
                    'compare' => 'EXISTS'
                ],
                [
                    'key' => 'related_project',
                    'compare' => 'EXISTS'
                ],
                [
                    'key' => 'related_task',
                    'compare' => 'EXISTS'
                ]
            ]
        ]);

        $report['total_activities'] = count($activities);

        foreach ($activities as $activity) {
            $activity_id = $activity->ID;
            $related_objects = [];

            // Sprawdź istniejące pola i dodaj do tablicy
            $related_person = get_field('related_person', $activity_id);
            $related_company = get_field('related_company', $activity_id);
            $related_project = get_field('related_project', $activity_id);
            $related_task = get_field('related_task', $activity_id);

            if ($related_person) {
                $related_objects[] = $related_person;
            }
            if ($related_company) {
                $related_objects[] = $related_company;
            }
            if ($related_project) {
                $related_objects[] = $related_project;
            }
            if ($related_task) {
                $related_objects[] = $related_task;
            }

            // Jeśli znaleźliśmy jakiekolwiek powiązania, migruj je
            if (!empty($related_objects)) {
                try {
                    // Ustaw nowe pole
                    update_field('related_objects', $related_objects, $activity_id);

                    // Opcjonalnie: usuń stare pola (odkomentuj jeśli chcesz je usunąć)
                    // delete_field('related_person', $activity_id);
                    // delete_field('related_company', $activity_id);
                    // delete_field('related_project', $activity_id);
                    // delete_field('related_task', $activity_id);

                    $report['migrated_activities']++;
                    $report['details'][] = [
                        'activity_id' => $activity_id,
                        'activity_title' => get_the_title($activity_id),
                        'migrated_objects' => $related_objects,
                        'status' => 'success'
                    ];

                } catch (Exception $e) {
                    $error = "Błąd migracji aktywności ID {$activity_id}: " . $e->getMessage();
                    $report['errors'][] = $error;
                    $report['details'][] = [
                        'activity_id' => $activity_id,
                        'activity_title' => get_the_title($activity_id),
                        'status' => 'error',
                        'error' => $error
                    ];
                }
            }
        }

        return $report;
    }

    /**
     * Sprawdza czy migracja jest potrzebna
     *
     * @return bool
     */
    public static function is_migration_needed() {
        $activities_with_old_fields = get_posts([
            'post_type' => 'activity',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => 'related_person',
                    'compare' => 'EXISTS'
                ],
                [
                    'key' => 'related_company',
                    'compare' => 'EXISTS'
                ],
                [
                    'key' => 'related_project',
                    'compare' => 'EXISTS'
                ],
                [
                    'key' => 'related_task',
                    'compare' => 'EXISTS'
                ]
            ]
        ]);

        return !empty($activities_with_old_fields);
    }

    /**
     * Zlicza aktywności wymagające migracji
     *
     * @return int
     */
    public static function count_activities_to_migrate() {
        $activities_with_old_fields = get_posts([
            'post_type' => 'activity',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => 'related_person',
                    'compare' => 'EXISTS'
                ],
                [
                    'key' => 'related_company',
                    'compare' => 'EXISTS'
                ],
                [
                    'key' => 'related_project',
                    'compare' => 'EXISTS'
                ],
                [
                    'key' => 'related_task',
                    'compare' => 'EXISTS'
                ]
            ]
        ]);

        return count($activities_with_old_fields);
    }

    /**
     * Przywraca aktywności ze starych pól (rollback migracji)
     * UWAGA: Działa tylko jeśli stare pola nie zostały usunięte
     *
     * @return array Raport z rollbacku
     */
    public static function rollback_migration() {
        $report = [
            'total_activities' => 0,
            'rolled_back_activities' => 0,
            'errors' => [],
            'details' => []
        ];

        // Pobierz wszystkie aktywności z nowym polem
        $activities = get_posts([
            'post_type' => 'activity',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => 'related_objects',
                    'compare' => 'EXISTS'
                ]
            ]
        ]);

        $report['total_activities'] = count($activities);

        foreach ($activities as $activity) {
            $activity_id = $activity->ID;

            try {
                // Usuń nowe pole
                delete_field('related_objects', $activity_id);

                $report['rolled_back_activities']++;
                $report['details'][] = [
                    'activity_id' => $activity_id,
                    'activity_title' => get_the_title($activity_id),
                    'status' => 'success'
                ];

            } catch (Exception $e) {
                $error = "Błąd rollbacku aktywności ID {$activity_id}: " . $e->getMessage();
                $report['errors'][] = $error;
                $report['details'][] = [
                    'activity_id' => $activity_id,
                    'activity_title' => get_the_title($activity_id),
                    'status' => 'error',
                    'error' => $error
                ];
            }
        }

        return $report;
    }
}
