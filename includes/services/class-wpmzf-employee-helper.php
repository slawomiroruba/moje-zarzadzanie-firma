<?php

/**
 * Klasa pomocnicza do zarządzania pracownikami
 */
class WPMZF_Employee_Helper {

    /**
     * Pobiera wszystkich aktywnych pracowników (użytkowników z powiązanymi profilami pracownika)
     *
     * @return array Lista pracowników z danymi do wyświetlenia w formularzu
     */
    public static function get_employees_for_select() {
        $args = [
            'post_type' => 'employee',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'meta_query' => [
                [
                    'key' => 'employee_user',
                    'compare' => 'EXISTS'
                ]
            ]
        ];

        $employees_query = new WP_Query($args);
        $employees = [];

        if ($employees_query->have_posts()) {
            while ($employees_query->have_posts()) {
                $employees_query->the_post();
                $employee_id = get_the_ID();
                $user_id = get_field('employee_user', $employee_id);
                $position = get_field('employee_position', $employee_id);
                $rate = get_field('employee_rate', $employee_id);

                if ($user_id) {
                    $user = get_user_by('ID', $user_id);
                    if ($user) {
                        $display_name = get_the_title();
                        if ($position) {
                            $display_name .= ' (' . $position . ')';
                        }

                        $employees[] = [
                            'employee_id' => $employee_id,
                            'user_id' => $user_id,
                            'name' => get_the_title(),
                            'display_name' => $display_name,
                            'username' => $user->user_login,
                            'email' => $user->user_email,
                            'position' => $position,
                            'rate' => $rate
                        ];
                    }
                }
            }
        }
        wp_reset_postdata();

        return $employees;
    }

    /**
     * Pobiera profil pracownika na podstawie ID użytkownika
     *
     * @param int $user_id ID użytkownika WordPress
     * @return WP_Post|null Obiekt posta pracownika lub null
     */
    public static function get_employee_by_user_id($user_id) {
        $args = [
            'post_type' => 'employee',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'meta_query' => [
                [
                    'key' => 'employee_user',
                    'value' => $user_id,
                    'compare' => '='
                ]
            ]
        ];

        $employees_query = new WP_Query($args);
        $employee = null;

        if ($employees_query->have_posts()) {
            $employee = $employees_query->posts[0];
        }
        wp_reset_postdata();

        return $employee;
    }

    /**
     * Pobiera użytkownika WordPress na podstawie ID pracownika
     *
     * @param int $employee_id ID pracownika (post)
     * @return WP_User|null Obiekt użytkownika lub null
     */
    public static function get_user_by_employee_id($employee_id) {
        $user_id = get_field('employee_user', $employee_id);
        if ($user_id) {
            return get_user_by('ID', $user_id);
        }
        return null;
    }

    /**
     * Sprawdza czy użytkownik jest pracownikiem
     *
     * @param int $user_id ID użytkownika
     * @return bool True jeśli użytkownik ma profil pracownika
     */
    public static function is_employee($user_id) {
        return self::get_employee_by_user_id($user_id) !== null;
    }

    /**
     * Pobiera zadania przypisane do pracownika
     *
     * @param int $user_id ID użytkownika
     * @param string $status Status zadań ('all', 'open', 'closed')
     * @return array Lista zadań
     */
    public static function get_user_tasks($user_id, $status = 'all') {
        $args = [
            'post_type' => 'task',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => 'task_assigned_user',
                    'value' => $user_id,
                    'compare' => '='
                ]
            ],
            'orderby' => 'date',
            'order' => 'DESC'
        ];

        if ($status === 'open') {
            $args['meta_query'][] = [
                'key' => 'task_status',
                'value' => 'Zrobione',
                'compare' => '!='
            ];
        } elseif ($status === 'closed') {
            $args['meta_query'][] = [
                'key' => 'task_status',
                'value' => 'Zrobione',
                'compare' => '='
            ];
        }

        $tasks_query = new WP_Query($args);
        $tasks = [];

        if ($tasks_query->have_posts()) {
            while ($tasks_query->have_posts()) {
                $tasks_query->the_post();
                $task_id = get_the_ID();

                $tasks[] = [
                    'id' => $task_id,
                    'title' => get_the_title(),
                    'status' => get_field('task_status', $task_id) ?: 'Do zrobienia',
                    'start_date' => get_field('task_start_date', $task_id),
                    'end_date' => get_field('task_end_date', $task_id),
                    'description' => get_field('task_description', $task_id),
                    'edit_link' => get_edit_post_link($task_id)
                ];
            }
        }
        wp_reset_postdata();

        return $tasks;
    }

    /**
     * Tworzy nowy profil pracownika dla użytkownika
     *
     * @param int $user_id ID użytkownika
     * @param array $employee_data Dane pracownika (position, rate, etc.)
     * @return int|WP_Error ID nowego pracownika lub błąd
     */
    public static function create_employee_for_user($user_id, $employee_data = []) {
        $user = get_user_by('ID', $user_id);
        if (!$user) {
            return new WP_Error('invalid_user', 'Użytkownik nie istnieje.');
        }

        // Sprawdź czy użytkownik już ma profil pracownika
        if (self::is_employee($user_id)) {
            return new WP_Error('employee_exists', 'Użytkownik już ma profil pracownika.');
        }

        $employee_post = [
            'post_type' => 'employee',
            'post_title' => $user->display_name ?: $user->user_login,
            'post_status' => 'publish',
            'post_author' => get_current_user_id()
        ];

        $employee_id = wp_insert_post($employee_post);

        if (!is_wp_error($employee_id)) {
            // Powiąż z użytkownikiem
            update_field('employee_user', $user_id, $employee_id);

            // Zapisz dodatkowe dane pracownika
            if (isset($employee_data['position'])) {
                update_field('employee_position', $employee_data['position'], $employee_id);
            }
            if (isset($employee_data['rate'])) {
                update_field('employee_rate', $employee_data['rate'], $employee_id);
            }
        }

        return $employee_id;
    }

    /**
     * Renderuje select HTML z pracownikami
     *
     * @param string $name Nazwa pola select
     * @param int $selected_user_id ID aktualnie wybranego użytkownika
     * @param array $attributes Dodatkowe atrybuty HTML
     * @return string HTML select
     */
    public static function render_employee_select($name, $selected_user_id = 0, $attributes = []) {
        $employees = self::get_employees_for_select();
        
        $attr_string = '';
        foreach ($attributes as $key => $value) {
            $attr_string .= sprintf(' %s="%s"', esc_attr($key), esc_attr($value));
        }

        $html = sprintf('<select name="%s"%s>', esc_attr($name), $attr_string);
        $html .= '<option value="">Pracownik</option>';

        foreach ($employees as $employee) {
            $selected = selected($selected_user_id, $employee['user_id'], false);
            $html .= sprintf(
                '<option value="%d"%s>%s</option>',
                $employee['user_id'],
                $selected,
                esc_html($employee['display_name'])
            );
        }

        $html .= '</select>';

        return $html;
    }
}
