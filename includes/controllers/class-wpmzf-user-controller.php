<?php

/**
 * Kontroler REST API dla użytkowników
 *
 * @package WPMZF
 * @subpackage Controllers
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPMZF_User_Controller extends WP_REST_Controller {

    /**
     * Namespace dla REST API
     */
    protected $namespace = 'wpmzf/v1';

    /**
     * Resource name
     */
    protected $rest_base = 'users';

    /**
     * Serwis użytkowników
     */
    private $service;

    /**
     * Konstruktor
     */
    public function __construct() {
        $this->service = new WPMZF_User_Service();
    }

    /**
     * Rejestruje trasy REST API
     */
    public function register_routes() {
        // GET /wp-json/wpmzf/v1/users
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_items'],
                'permission_callback' => [$this, 'get_items_permissions_check'],
                'args' => $this->get_collection_params(),
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create_item'],
                'permission_callback' => [$this, 'create_item_permissions_check'],
                'args' => $this->get_endpoint_args_for_item_schema(WP_REST_Server::CREATABLE),
            ],
            'schema' => [$this, 'get_public_item_schema'],
        ]);

        // GET /wp-json/wpmzf/v1/users/{id}
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_item'],
                'permission_callback' => [$this, 'get_item_permissions_check'],
                'args' => [
                    'context' => $this->get_context_param(['default' => 'view']),
                ],
            ],
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'update_item'],
                'permission_callback' => [$this, 'update_item_permissions_check'],
                'args' => $this->get_endpoint_args_for_item_schema(WP_REST_Server::EDITABLE),
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'delete_item'],
                'permission_callback' => [$this, 'delete_item_permissions_check'],
            ],
            'schema' => [$this, 'get_public_item_schema'],
        ]);

        // GET /wp-json/wpmzf/v1/users/search
        register_rest_route($this->namespace, '/' . $this->rest_base . '/search', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'search_items'],
            'permission_callback' => [$this, 'get_items_permissions_check'],
            'args' => [
                'q' => [
                    'description' => 'Fraza wyszukiwania',
                    'type' => 'string',
                    'required' => true,
                ],
            ],
        ]);
    }

    /**
     * Pobiera listę użytkowników
     *
     * @param WP_REST_Request $request Żądanie
     * @return WP_REST_Response|WP_Error
     */
    public function get_items($request) {
        $args = [
            'limit' => $request->get_param('per_page') ?: 50,
            'offset' => (($request->get_param('page') ?: 1) - 1) * ($request->get_param('per_page') ?: 50)
        ];

        $users = $this->service->get_users($args);
        $data = [];

        foreach ($users as $user) {
            $data[] = $this->prepare_item_for_response($user, $request);
        }

        return rest_ensure_response($data);
    }

    /**
     * Pobiera pojedynczego użytkownika
     *
     * @param WP_REST_Request $request Żądanie
     * @return WP_REST_Response|WP_Error
     */
    public function get_item($request) {
        $user = $this->service->get_user($request['id']);

        if (!$user) {
            return new WP_Error('rest_user_invalid_id', 'Użytkownik nie istnieje.', ['status' => 404]);
        }

        $data = $this->prepare_item_for_response($user, $request);

        return rest_ensure_response($data);
    }

    /**
     * Tworzy nowego użytkownika
     *
     * @param WP_REST_Request $request Żądanie
     * @return WP_REST_Response|WP_Error
     */
    public function create_item($request) {
        $data = [
            'name' => $request->get_param('name'),
            'email' => $request->get_param('email'),
            'phone' => $request->get_param('phone'),
            'position' => $request->get_param('position'),
        ];

        $result = $this->service->create_user($data);

        if (!$result['success']) {
            return new WP_Error('rest_user_create_failed', 'Nie udało się utworzyć użytkownika.', [
                'status' => 400,
                'errors' => $result['errors']
            ]);
        }

        $user = $this->service->get_user($result['user_id']);
        $response = $this->prepare_item_for_response($user, $request);

        return rest_ensure_response($response);
    }

    /**
     * Aktualizuje użytkownika
     *
     * @param WP_REST_Request $request Żądanie
     * @return WP_REST_Response|WP_Error
     */
    public function update_item($request) {
        $data = [];
        
        if ($request->has_param('name')) {
            $data['name'] = $request->get_param('name');
        }
        if ($request->has_param('email')) {
            $data['email'] = $request->get_param('email');
        }
        if ($request->has_param('phone')) {
            $data['phone'] = $request->get_param('phone');
        }
        if ($request->has_param('position')) {
            $data['position'] = $request->get_param('position');
        }

        $result = $this->service->update_user($request['id'], $data);

        if (!$result['success']) {
            return new WP_Error('rest_user_update_failed', 'Nie udało się zaktualizować użytkownika.', [
                'status' => 400,
                'errors' => $result['errors']
            ]);
        }

        $user = $this->service->get_user($request['id']);
        $response = $this->prepare_item_for_response($user, $request);

        return rest_ensure_response($response);
    }

    /**
     * Usuwa użytkownika
     *
     * @param WP_REST_Request $request Żądanie
     * @return WP_REST_Response|WP_Error
     */
    public function delete_item($request) {
        $user = $this->service->get_user($request['id']);

        if (!$user) {
            return new WP_Error('rest_user_invalid_id', 'Użytkownik nie istnieje.', ['status' => 404]);
        }

        $result = $this->service->delete_user($request['id']);

        if (!$result['success']) {
            return new WP_Error('rest_user_delete_failed', 'Nie udało się usunąć użytkownika.', [
                'status' => 400,
                'errors' => $result['errors']
            ]);
        }

        return rest_ensure_response([
            'deleted' => true,
            'previous' => $this->prepare_item_for_response($user, $request)
        ]);
    }

    /**
     * Wyszukuje użytkowników
     *
     * @param WP_REST_Request $request Żądanie
     * @return WP_REST_Response|WP_Error
     */
    public function search_items($request) {
        $search = $request->get_param('q');
        $users = $this->service->search_users($search);
        $data = [];

        foreach ($users as $user) {
            $data[] = $this->prepare_item_for_response($user, $request);
        }

        return rest_ensure_response($data);
    }

    /**
     * Przygotowuje dane użytkownika do odpowiedzi
     *
     * @param WPMZF_User $user Użytkownik
     * @param WP_REST_Request $request Żądanie
     * @return array
     */
    public function prepare_item_for_response($user, $request) {
        $data = [
            'id' => (int) $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'position' => $user->position,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];

        return $data;
    }

    /**
     * Sprawdza uprawnienia do pobierania listy
     */
    public function get_items_permissions_check($request) {
        return current_user_can('manage_options');
    }

    /**
     * Sprawdza uprawnienia do pobierania elementu
     */
    public function get_item_permissions_check($request) {
        return current_user_can('manage_options');
    }

    /**
     * Sprawdza uprawnienia do tworzenia
     */
    public function create_item_permissions_check($request) {
        return current_user_can('manage_options');
    }

    /**
     * Sprawdza uprawnienia do aktualizacji
     */
    public function update_item_permissions_check($request) {
        return current_user_can('manage_options');
    }

    /**
     * Sprawdza uprawnienia do usuwania
     */
    public function delete_item_permissions_check($request) {
        return current_user_can('manage_options');
    }

    /**
     * Pobiera parametry kolekcji
     */
    public function get_collection_params() {
        return [
            'page' => [
                'description' => 'Numer strony',
                'type' => 'integer',
                'default' => 1,
                'minimum' => 1,
            ],
            'per_page' => [
                'description' => 'Liczba elementów na stronę',
                'type' => 'integer',
                'default' => 50,
                'minimum' => 1,
                'maximum' => 100,
            ],
        ];
    }
}
