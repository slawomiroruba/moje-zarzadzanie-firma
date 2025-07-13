# Blokowanie Frontendu i Przekierowania - Dokumentacja

## Opis funkcjonalności

Plugin został rozszerzony o funkcjonalność blokowania frontendu WordPressa i automatycznego przekierowywania użytkowników na dedykowany dashboard systemu zarządzania firmą.

## Zaimplementowane funkcje

### 1. Blokowanie frontendu (WPMZF_Frontend_Blocker)

#### Funkcje blokujące:
- **block_frontend_access()** - Blokuje dostęp do wszystkich stron frontendowych
  - Niezalogowani użytkownicy → przekierowanie na stronę logowania
  - Zalogowani użytkownicy → przekierowanie na dashboard administratora
  - Wyjątki: panel admina, AJAX, CRON, WP-CLI, strona logowania

#### Funkcje przekierowujące w panelu admina:
- **redirect_admin_dashboard()** - Przekierowuje z domyślnego kokpitu na nasz dashboard
  - `/wp-admin/` → `/wp-admin/admin.php?page=wpmzf_dashboard`
  - `/wp-admin/index.php` → `/wp-admin/admin.php?page=wpmzf_dashboard`

#### Funkcje zarządzające menu:
- **remove_default_admin_menus()** - Ukrywa niepotrzebne menu WordPressa
  - Dla wszystkich: Wpisy, Strony, Komentarze
  - Dla nie-administratorów dodatkowo: Media, Wygląd, Wtyczki, Narzędzia, Ustawienia

#### Funkcje zarządzające dostępem:
- **block_admin_pages()** - Blokuje bezpośredni dostęp do niektórych stron admina
- **remove_dashboard_widgets()** - Usuwa domyślne widgety kokpitu WordPressa
- **custom_login_redirect()** - Przekierowanie po zalogowaniu na nasz dashboard
- **custom_admin_title()** - Zmienia tytuły stron administratora

## Zastąpione pliki

### Stara implementacja:
- `class-wpmzf-access-control.php` - podstawowa kontrola dostępu

### Nowa implementacja:
- `class-wpmzf-frontend-blocker.php` - kompleksowe blokowanie frontendu i zarządzanie przekierowaniami

## Konfiguracja

Funkcjonalność jest automatycznie włączana po aktywacji pluginu. Nie wymaga dodatkowej konfiguracji.

### Możliwe dostosowania:

1. **Modyfikacja zablokowanych menu** - w funkcji `remove_default_admin_menus()`:
```php
$menus_to_hide = array(
    'edit.php',                     // Wpisy
    'edit.php?post_type=page',      // Strony
    // dodaj więcej według potrzeb
);
```

2. **Modyfikacja zablokowanych stron** - w funkcji `block_admin_pages()`:
```php
$blocked_for_non_admins = array(
    'themes.php',
    'plugins.php',
    // dodaj więcej według potrzeb
);
```

3. **Dodanie wyjątków dla frontendu** - w funkcji `block_frontend_access()`:
```php
// Dodaj warunki wyjątków przed główną logiką blokowania
if (is_page('specjalna-strona')) {
    return;
}
```

## Testowanie

Aby przetestować funkcjonalność:

1. **Test frontendu**:
   - Wyloguj się z WordPressa
   - Spróbuj wejść na główną stronę → powinno przekierować na logowanie
   - Zaloguj się → powinno przekierować na dashboard

2. **Test panelu admina**:
   - Wejdź na `/wp-admin/` → powinno przekierować na dashboard
   - Sprawdź czy menu WordPressa są ukryte
   - Sprawdź czy domyślny kokpit nie jest dostępny

3. **Test przekierowań**:
   - Zaloguj się przez formularz logowania → powinno przekierować na dashboard
   - Sprawdź czy próba wejścia na zablokowane strony admina przekierowuje na dashboard

## Bezpieczeństwo

- Wszystkie przekierowania używają `wp_safe_redirect()` 
- Sprawdzane są uprawnienia użytkowników
- Zachowane są wyjątki dla AJAX, CRON i WP-CLI
- Administratorzy mają większy dostęp niż zwykli użytkownicy

## Kompatybilność

- WordPress 5.0+
- PHP 7.4+
- Działa z wszystkimi standardowymi wtyczkami WordPressa
- Nie interferuje z REST API ani AJAX
