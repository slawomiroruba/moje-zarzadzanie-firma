# Naprawiono błędy deprecation w PHP 8.3

## Opis problemu
Plugin "Moje Zarządzanie Firmą" wyrzucał błędy deprecation w PHP 8.3.22 związane z przekazywaniem wartości `null` do funkcji `strpos()` i `str_replace()`, które to funkcje teraz wymagają string jako parametr.

## Wprowadzone poprawki

### 1. Naprawiono Admin Menu Manager
**Plik:** `includes/core/class-admin-menu-manager.php`

**Problem:** Używanie `null` jako parent_slug w `add_submenu_page()`
```php
add_submenu_page(
    null, // ❌ To powodowało błędy deprecation
    'Widok osoby',
    // ...
);
```

**Rozwiązanie:** Zastąpiono `null` pustym stringiem `''`
```php
add_submenu_page(
    '', // ✅ Poprawne dla ukrytych stron
    'Widok osoby',
    // ...
);
```

**Dodatkowe ulepszenia:**
- Dodano sprawdzanie czy strony są poprawnie zainicjalizowane
- Dodano sprawdzanie czy klasy stron zostały załadowane
- Dodano error handling dla przypadków gdy menu nie może być utworzone

### 2. Naprawiono User Service  
**Plik:** `includes/services/class-wpmzf-user-service.php`

**Problem:** Potencjalne przekazywanie `null` do `strpos()`
```php
return strpos(strtolower($user->name), $search) !== false ||
       strpos(strtolower($user->email), $search) !== false ||
       strpos(strtolower($user->position), $search) !== false;
```

**Rozwiązanie:** Dodano zabezpieczenia przed null
```php
$name = $user->name ?? '';
$email = $user->email ?? '';
$position = $user->position ?? '';

return strpos(strtolower($name), $search) !== false ||
       strpos(strtolower($email), $search) !== false ||
       strpos(strtolower($position), $search) !== false;
```

### 3. Naprawiono komponenty tabeli
**Pliki:** 
- `includes/admin/components/table/class-wpmzf-companies-list-table.php`
- `includes/admin/class-wpmzf-custom-columns.php`

**Problem:** Potencjalne przekazywanie `null` do `str_replace()`
```php
$status_class = 'status-' . strtolower(str_replace(' ', '-', $status));
```

**Rozwiązanie:** Dodano dodatkową ochronę przed null
```php
$status = get_field('company_status', $item->ID) ?: 'Aktywny';
$status = $status ?? 'Aktywny'; // dodatkowa ochrona przed null
$status_class = 'status-' . strtolower(str_replace(' ', '-', $status));
```

### 4. Utworzono PHP Compatibility Helper
**Plik:** `includes/core/class-wpmzf-php-compat.php` (NOWY)

Utworzono nową klasę pomocniczą zawierającą bezpieczne wersje funkcji PHP:
- `safe_strpos()` - bezpieczny strpos()
- `safe_str_replace()` - bezpieczny str_replace()
- `safe_strtolower()` - bezpieczny strtolower()
- `status_to_css_class()` - bezpieczne tworzenie CSS klas ze statusów
- `get_field_safe()` - bezpieczne pobieranie ACF fields
- Funkcje do logowania i sprawdzania kompatybilności

### 5. Naprawiono Error Handler
**Plik:** `includes/core/class-wpmzf-error-handler.php`

**Problem:** E_STRICT jest deprecated w PHP 8.4+
```php
E_STRICT => 'Strict Standards',
```

**Rozwiązanie:** Zastąpiono liczbą
```php
8192 => 'Strict Standards', // E_STRICT is deprecated in PHP 8.4+
```

### 6. Dodano compatibility helper do ładowania
**Plik:** `moje-zarzadzanie-firma.php`

Dodano ładowanie PHP compatibility helper jako pierwszego z core files:
```php
require_once WPMZF_PLUGIN_PATH . 'includes/core/class-wpmzf-php-compat.php'; // PHP compatibility helper (must be first)
```

## Wynik
✅ **Błędy deprecation z WPMZF pluginu zostały wyeliminowane**

Pozostałe błędy deprecation w logu pochodzą z innych pluginów:
- admin-menu-editor-pro
- updraftplus 
- wp-cli

Te wymagają aktualizacji od ich autorów.

## Zalecenia na przyszłość
1. Używać nowej klasy `WPMZF_PHP_Compat` do bezpiecznych operacji na stringach
2. Zawsze sprawdzać czy wartości nie są null przed przekazywaniem do funkcji PHP
3. Używać null coalescing operator `??` lub null coalescing assignment `??=`
4. Testować plugin na najnowszych wersjach PHP przed wdrożeniem

## Przykład użycia nowych funkcji pomocniczych
```php
// Zamiast:
$status_class = 'status-' . strtolower(str_replace(' ', '-', $status));

// Użyj:
$status_class = WPMZF_PHP_Compat::status_to_css_class($status);

// Zamiast:
$field_value = get_field('field_name', $post_id) ?: 'default';

// Użyj:
$field_value = WPMZF_PHP_Compat::get_field_safe('field_name', $post_id, 'default');
```
