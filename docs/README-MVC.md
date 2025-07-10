# Struktura MVC w Luna CRM

## Przegląd

Plugin wykorzystuje wzorzec Model-View-Controller (MVC) z dodatkowymi warstwami Repository i Service dla lepszej organizacji kodu.

## Struktura

### 📁 Models (`/includes/models/`)
Modele reprezentują struktury danych i logikę biznesową.

**Przykład: `WPMZF_User`**
```php
$user = new WPMZF_User([
    'name' => 'Jan Kowalski',
    'email' => 'jan@example.com'
]);

$validation = $user->validate();
if ($validation === true) {
    // Dane poprawne
}
```

### 📁 Repositories (`/includes/repositories/`)
Repositories obsługują komunikację z bazą danych.

**Przykład: `WPMZF_User_Repository`**
```php
$repository = new WPMZF_User_Repository();
$users = $repository->get_all();
$user = $repository->get_by_id(1);
$repository->create($user);
```

### 📁 Services (`/includes/services/`)
Services zawierają logikę biznesową i orchestrują działania między modelami a repositories.

**Przykład: `WPMZF_User_Service`**
```php
$service = new WPMZF_User_Service();
$result = $service->create_user([
    'name' => 'Jan Kowalski',
    'email' => 'jan@example.com'
]);
```

### 📁 Controllers (`/includes/controllers/`)
Controllers obsługują REST API i komunikację z frontendem.

**Przykład: `WPMZF_User_Controller`**
```php
// Automatycznie rejestruje trasy:
// GET /wp-json/wpmzf/v1/users
// POST /wp-json/wpmzf/v1/users
// GET /wp-json/wpmzf/v1/users/{id}
// PUT /wp-json/wpmzf/v1/users/{id}
// DELETE /wp-json/wpmzf/v1/users/{id}
```

## REST API

### Endpointy użytkowników

#### GET `/wp-json/wpmzf/v1/users`
Pobiera listę użytkowników

**Parametry:**
- `page` - numer strony (domyślnie 1)
- `per_page` - ilość na stronę (domyślnie 50)

**Odpowiedź:**
```json
[
  {
    "id": 1,
    "name": "Jan Kowalski",
    "email": "jan@example.com",
    "phone": "123456789",
    "position": "Developer",
    "created_at": "2025-01-01 12:00:00",
    "updated_at": "2025-01-01 12:00:00"
  }
]
```

#### POST `/wp-json/wpmzf/v1/users`
Tworzy nowego użytkownika

**Dane wejściowe:**
```json
{
  "name": "Jan Kowalski",
  "email": "jan@example.com",
  "phone": "123456789",
  "position": "Developer"
}
```

#### GET `/wp-json/wpmzf/v1/users/{id}`
Pobiera pojedynczego użytkownika

#### PUT `/wp-json/wpmzf/v1/users/{id}`
Aktualizuje użytkownika

#### DELETE `/wp-json/wpmzf/v1/users/{id}`
Usuwa użytkownika

#### GET `/wp-json/wpmzf/v1/users/search?q=fraza`
Wyszukuje użytkowników

## Użycie w JavaScript

```javascript
// Pobranie listy użytkowników
fetch('/wp-json/wpmzf/v1/users', {
    headers: {
        'X-WP-Nonce': wpApiSettings.nonce
    }
})
.then(response => response.json())
.then(users => console.log(users));

// Dodanie użytkownika
fetch('/wp-json/wpmzf/v1/users', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': wpApiSettings.nonce
    },
    body: JSON.stringify({
        name: 'Jan Kowalski',
        email: 'jan@example.com'
    })
})
.then(response => response.json())
.then(result => console.log(result));
```

## Hooks i Filtry

### Akcje (Actions)
- `wpmzf_user_created` - po utworzeniu użytkownika
- `wpmzf_user_updated` - po aktualizacji użytkownika
- `wpmzf_user_before_delete` - przed usunięciem użytkownika
- `wpmzf_user_deleted` - po usunięciu użytkownika

### Użycie:
```php
add_action('wpmzf_user_created', function($user_id, $user) {
    // Logika po utworzeniu użytkownika
    error_log("Utworzono użytkownika: " . $user->name);
});
```

## Bezpieczeństwo

### Uprawnienia
Wszystkie endpointy wymagają uprawnienia `manage_options`.

### Nonce
Wszystkie żądania AJAX muszą zawierać prawidłowy nonce w nagłówku `X-WP-Nonce`.

### Walidacja
Wszystkie dane wejściowe są walidowane i sanityzowane.

## Rozszerzanie

### Dodanie nowego modelu

1. **Stwórz model** w `/includes/models/`
2. **Stwórz repository** w `/includes/repositories/`
3. **Stwórz service** w `/includes/services/`
4. **Stwórz controller** w `/includes/controllers/`
5. **Zarejestruj w** `moje-zarzadzanie-firma.php`

### Przykład nowego modelu "Contact":

```php
// includes/models/class-wpmzf-contact.php
class WPMZF_Contact {
    public $id;
    public $name;
    public $email;
    // ...
}

// includes/repositories/class-wpmzf-contact-repository.php
class WPMZF_Contact_Repository {
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'wpmzf_contacts';
    }
    // ...
}

// includes/services/class-wpmzf-contact-service.php
class WPMZF_Contact_Service {
    private $repository;
    
    public function __construct() {
        $this->repository = new WPMZF_Contact_Repository();
    }
    // ...
}

// includes/controllers/class-wpmzf-contact-controller.php
class WPMZF_Contact_Controller extends WP_REST_Controller {
    protected $namespace = 'wpmzf/v1';
    protected $rest_base = 'contacts';
    // ...
}
```

## Testowanie

### Testowanie API przez przeglądarkę
1. Zainstaluj rozszerzenie REST Client
2. Ustaw nagłówki:
   - `Content-Type: application/json`
   - `X-WP-Nonce: [nonce_value]`
3. Testuj endpointy

### Testowanie przez konsolę
```javascript
// Sprawdź dostępne endpointy
console.log(wpApiSettings);

// Test połączenia
fetch('/wp-json/wpmzf/v1/users', {
    headers: { 'X-WP-Nonce': wpApiSettings.nonce }
})
.then(r => r.json())
.then(console.log);
```

## Debugowanie

### Logi
Włącz debugowanie w `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

### Sprawdzanie błędów
```php
error_log('Debug info: ' . print_r($data, true));
```

### Sprawdzanie tabeli
```sql
SELECT * FROM wp_wpmzf_users;
```

## Najlepsze praktyki

1. **Zawsze waliduj dane wejściowe**
2. **Używaj prepared statements**
3. **Sanityzuj dane przed zapisem**
4. **Sprawdzaj uprawnienia**
5. **Używaj nonce dla bezpieczeństwa**
6. **Loguj ważne operacje**
7. **Testuj wszystkie endpointy**
8. **Dokumentuj API**

## Migracja z starych struktur

Jeśli masz istniejące dane w starych strukturach, utwórz skrypty migracji:

```php
// Przykład migracji
function wpmzf_migrate_old_data() {
    // Pobierz stare dane
    $old_users = get_posts([
        'post_type' => 'old_user_type',
        'posts_per_page' => -1
    ]);
    
    $service = new WPMZF_User_Service();
    
    foreach ($old_users as $old_user) {
        $service->create_user([
            'name' => $old_user->post_title,
            'email' => get_post_meta($old_user->ID, 'email', true)
        ]);
    }
}
```
