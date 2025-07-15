<?php

/**
 * Serwis dla użytkowników
 *
 * @package WPMZF
 * @subpackage Services
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPMZF_User_Service {

    /**
     * Repository użytkowników
     */
    private $repository;

    /**
     * Konstruktor
     *
     * @param WPMZF_User_Repository|null $repository Repository użytkowników
     */
    public function __construct(?WPMZF_User_Repository $repository = null) {
        $this->repository = $repository ?: new WPMZF_User_Repository();
    }

    //mateusz: tak jak w przypadku controllera rowniez jest to prawidlowe uzycie, bo mamy serwis
    // ktory odpowiednio przetwarza dane i komunikuje sie z repozytorium w celu operacji na danych
    /**
     * Pobiera listę użytkowników
     *
     * @param array $args Argumenty zapytania
     * @return array
     */
    public function get_users($args = []) {
        $default_args = [
            'limit' => 50,
            'offset' => 0
        ];
        
        $args = wp_parse_args($args, $default_args);
        
        return $this->repository->get_all($args['limit'], $args['offset']);
    }

    /**
     * Pobiera użytkownika po ID
     *
     * @param int $id ID użytkownika
     * @return WPMZF_User|null
     */
    public function get_user($id) {
        if (!is_numeric($id) || $id <= 0) {
            return null;
        }
        
        return $this->repository->get_by_id($id);
    }

    /**
     * Tworzy nowego użytkownika
     *
     * @param array $data Dane użytkownika
     * @return array Wynik operacji
     */
    public function create_user($data) {
        $user = new WPMZF_User($data);
        
        // Walidacja
        $validation = $user->validate();
        if ($validation !== true) {
            return [
                'success' => false,
                'errors' => $validation
            ];
        }
        
        // Sprawdź czy email już istnieje
        if ($this->repository->get_by_email($user->email)) {
            return [
                'success' => false,
                'errors' => ['email' => 'Ten email jest już używany']
            ];
        }
        
        // Zapisz użytkownika
        $user_id = $this->repository->create($user);
        
        if ($user_id === false) {
            return [
                'success' => false,
                'errors' => ['general' => 'Nie udało się utworzyć użytkownika']
            ];
        }
        
        // Akcja po utworzeniu użytkownika
        do_action('wpmzf_user_created', $user_id, $user);
        
        return [
            'success' => true,
            'user_id' => $user_id,
            'message' => 'Użytkownik został utworzony'
        ];
    }

    /**
     * Aktualizuje użytkownika
     *
     * @param int $id ID użytkownika
     * @param array $data Nowe dane
     * @return array Wynik operacji
     */
    public function update_user($id, $data) {
        // Sprawdź czy użytkownik istnieje
        $existing_user = $this->repository->get_by_id($id);
        if (!$existing_user) {
            return [
                'success' => false,
                'errors' => ['general' => 'Użytkownik nie istnieje']
            ];
        }
        
        // Utwórz nowy obiekt użytkownika z danymi
        $user = new WPMZF_User(array_merge($existing_user->to_array(), $data));
        
        // Walidacja
        $validation = $user->validate();
        if ($validation !== true) {
            return [
                'success' => false,
                'errors' => $validation
            ];
        }
        
        // Sprawdź czy email już istnieje (dla innego użytkownika)
        $user_with_email = $this->repository->get_by_email($user->email);
        if ($user_with_email && $user_with_email->id != $id) {
            return [
                'success' => false,
                'errors' => ['email' => 'Ten email jest już używany']
            ];
        }
        
        // Aktualizuj użytkownika
        $success = $this->repository->update($id, $user);
        
        if (!$success) {
            return [
                'success' => false,
                'errors' => ['general' => 'Nie udało się zaktualizować użytkownika']
            ];
        }
        
        // Akcja po aktualizacji użytkownika
        do_action('wpmzf_user_updated', $id, $user, $existing_user);
        
        return [
            'success' => true,
            'message' => 'Użytkownik został zaktualizowany'
        ];
    }

    /**
     * Usuwa użytkownika
     *
     * @param int $id ID użytkownika
     * @return array Wynik operacji
     */
    public function delete_user($id) {
        // Sprawdź czy użytkownik istnieje
        $user = $this->repository->get_by_id($id);
        if (!$user) {
            return [
                'success' => false,
                'errors' => ['general' => 'Użytkownik nie istnieje']
            ];
        }
        
        // Akcja przed usunięciem
        do_action('wpmzf_user_before_delete', $id, $user);
        
        // Usuń użytkownika
        $success = $this->repository->delete($id);
        
        if (!$success) {
            return [
                'success' => false,
                'errors' => ['general' => 'Nie udało się usunąć użytkownika']
            ];
        }
        
        // Akcja po usunięciu
        do_action('wpmzf_user_deleted', $id, $user);
        
        return [
            'success' => true,
            'message' => 'Użytkownik został usunięty'
        ];
    }

    /**
     * Pobiera statystyki użytkowników
     *
     * @return array
     */
    public function get_stats() {
        return [
            'total_users' => $this->repository->count(),
            'recent_users' => count($this->repository->get_all(5, 0))
        ];
    }

    /**
     * Wyszukuje użytkowników
     *
     * @param string $search Fraza wyszukiwania
     * @param array $args Dodatkowe argumenty
     * @return array
     */
    public function search_users($search, $args = []) {
        // Dla uproszczenia, pobieramy wszystkich i filtrujemy w PHP
        // W prawdziwej aplikacji lepiej byłoby zrobić to w SQL
        $users = $this->repository->get_all(1000, 0);
        
        $search = strtolower($search);
        
        return array_filter($users, function($user) use ($search) {
            return strpos(strtolower($user->name), $search) !== false ||
                   strpos(strtolower($user->email), $search) !== false ||
                   strpos(strtolower($user->position), $search) !== false;
        });
    }
}
