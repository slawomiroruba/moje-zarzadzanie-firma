# WPMZF Navbar Component - Dokumentacja

## Przegląd

`WPMZF_Navbar_Component` to samodzielny komponent nawigacji dla wtyczki Moje Zarządzanie Firmą. Zawiera wszystko w jednej klasie:
- HTML struktura navbar
- CSS style (inline)
- JavaScript funkcjonalność (inline)
- AJAX obsługa wyszukiwania

## Kluczowe cechy

### ✅ Samodzielność
- **Jedna klasa** - wszystko w jednym miejscu
- **Inline CSS/JS** - nie wymaga zewnętrznych plików
- **Singleton pattern** - jedna instancja na całą aplikację
- **Zabezpieczenia** -防止podwójne renderowanie i inicjalizację

### ✅ Funkcjonalności
- **Responsywny design** - działa na desktop, tablet, mobile
- **Dropdown menu** - rozwijane menu z animacjami
- **Globalne wyszukiwanie** - AJAX search z wynikami na żywo
- **Keyboard navigation** - obsługa klawiatury (strzałki, Enter, Escape)
- **Dark mode support** - automatyczne dostosowanie do ciemnego motywu

### ✅ Integracja
- **WordPress hooki** - prawidłowa integracja z WordPress
- **Lokalizacja** - obsługa tłumaczeń
- **Universal views** - kompatybilność z nowym systemem widoków
- **Cache busting** - automatyczna wersjonowanie assetów

## Użycie

### Podstawowe użycie
```php
// Inicjalizacja (wywoływane raz w głównym pliku wtyczki)
WPMZF_Navbar_Component::init();

// Renderowanie (wywoływane gdzie potrzeba navbar)
WPMZF_Navbar_Component::render_navbar();
```

### Integracja z WPMZF_View_Helper
```php
// W klasie WPMZF_View_Helper
public static function render_navbar() {
    WPMZF_Navbar_Component::render_navbar();
}
```

### Automatyczne ładowanie na stronach wtyczki
Komponent automatycznie rozpoznaje strony wtyczki na podstawie hooków:
- `toplevel_page_wpmzf_dashboard`
- `admin_page_wpmzf_view_*`
- `wpmzf_page_*`
- Wszystkie strony zawierające 'wpmzf' w nazwie

## Struktura CSS

### Główny kontener
```css
.wpmzf-navbar {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    height: 60px;
    /* Full responsive design */
}
```

### Komponenty
- **Brand** (`.wpmzf-navbar-brand`) - logo i tytuł
- **Navigation** (`.wpmzf-navbar-nav`) - główne menu
- **Search** (`.wpmzf-navbar-search`) - wyszukiwarka

### Responsive breakpoints
- **Desktop**: Pełne menu + search
- **Tablet** (< 1200px): Ukryte labele, mniejszy search
- **Mobile** (< 768px): Vertical layout, full-width search

## JavaScript funkcjonalności

### Wyszukiwanie
```javascript
// Automatyczne wyszukiwanie po 2+ znakach
// Debouncing 300ms
// Live results z grupowaniem
```

### Keyboard navigation
- **Escape** - zamyka dropdown/search
- **Arrow Down/Up** - nawigacja w wynikach
- **Enter** - przechodzi do wybranego elementu

### Dropdown menu
- **Hover activation** - pokazuje po najechaniu
- **Delay hiding** - ukrywa z opóźnieniem 100ms
- **Click outside** - zamyka przy kliknięciu poza

## AJAX endpoints

### wpmzf_global_search
**Endpoint**: `wp_ajax_wpmzf_global_search`
**Nonce**: `wpmzf_navbar_nonce`
**Parametry**:
- `search_term` (string) - fraza do wyszukania
**Odpowiedź**:
```json
{
  "success": true,
  "data": [
    {
      "type": "company",
      "label": "Firmy",
      "count": 5,
      "items": [
        {
          "id": 123,
          "title": "Nazwa firmy",
          "url": "admin.php?page=wpmzf_view_company&company_id=123",
          "excerpt": "Opis firmy..."
        }
      ]
    }
  ]
}
```

## Obsługiwane typy postów

### CRM
- `company` → Universal view
- `person` → Universal view  
- `opportunity` → WordPress editor
- `quote` → WordPress editor

### Projekty
- `project` → Universal view
- `task` → Universal view
- `time_entry` → WordPress editor

### Finanse
- `invoice` → Universal view
- `payment` → Universal view
- `contract` → Universal view
- `expense` → Universal view

### Zespół
- `employee` → Universal view
- `activity` → Universal view

### Narzędzia
- `important_link` → Universal view

## Konfiguracja menu

Menu jest definiowane w metodzie `get_menu_items()`:

```php
private function get_menu_items() {
    return array(
        array(
            'label' => __('CRM', 'wpmzf'),
            'icon' => '👥',
            'url' => admin_url('admin.php?page=wpmzf_companies'),
            'dropdown' => array(
                // Submenu items...
            )
        ),
        // More menu items...
    );
}
```

### Dodawanie nowego elementu menu
```php
array(
    'label' => __('Nazwa', 'wpmzf'),
    'icon' => '🎯', // Emoji lub dashicon
    'url' => admin_url('admin.php?page=custom_page'),
    'dropdown' => array( // Opcjonalne
        array(
            'label' => __('Podstrona', 'wpmzf'),
            'icon' => '📄',
            'url' => admin_url('admin.php?page=subpage')
        )
    )
)
```

## Bezpieczeństwo

### Nonce verification
```php
if (!wp_verify_nonce($_POST['nonce'], 'wpmzf_navbar_nonce')) {
    wp_send_json_error(__('Błąd bezpieczeństwa', 'wpmzf'));
}
```

### Input sanitization
```php
$search_term = sanitize_text_field($_POST['search_term']);
```

### Output escaping
```php
<?php echo esc_html($item['label']); ?>
<?php echo esc_url($item['url']); ?>
```

## Performance

### Optimalizacje
- **Singleton pattern** - jedna instancja
- **Lazy loading** - assety ładowane tylko na stronach wtyczki
- **Debouncing** - ograniczone żądania AJAX
- **Results limiting** - max 5 wyników na typ postu
- **Cache busting** - automatyczne versioning

### Memory usage
- **Inline CSS/JS** - eliminuje dodatkowe HTTP requests
- **Static methods** - mniejsze zużycie pamięci
- **Conditional loading** - ładowanie tylko gdy potrzeba

## Debugging

### JavaScript console
```javascript
// Check if navbar object exists
console.log(window.wpmzfNavbar);

// Monitor AJAX requests
// Network tab in DevTools
```

### PHP debugging
```php
// Check if component is initialized
var_dump(WPMZF_Navbar_Component::get_instance());

// Debug AJAX search
error_log('Search term: ' . $search_term);
```

## Migracja ze starego systemu

### Przed migracją
- Stary `class-wpmzf-navbar.php` 
- Osobne pliki `navbar.css` i `navbar.js`
- Duplikacja kodu w `WPMZF_View_Helper`
- Problemy z ładowaniem na niektórych stronach

### Po migracji
- ✅ Jeden plik `class-wpmzf-navbar-component.php`
- ✅ Wszystko inline - CSS i JS w klasie
- ✅ Eliminacja duplikacji kodu
- ✅ Konsystentne działanie na wszystkich stronach

### Kroki migracji
1. **Backup** starego systemu
2. **Utworzenie** nowego komponentu
3. **Aktualizacja** głównego pliku wtyczki
4. **Aktualizacja** WPMZF_View_Helper
5. **Testing** na wszystkich stronach

## Kompatybilność

### WordPress versions
- **Minimum**: WordPress 5.0+
- **Tested**: WordPress 6.0+
- **PHP**: 7.4+

### Browser support
- **Modern browsers**: Chrome 70+, Firefox 65+, Safari 12+
- **Mobile**: iOS Safari, Chrome Mobile
- **Fallbacks**: Graceful degradation dla starych przeglądarek

### Plugin compatibility
- **ACF**: Używa ACF fields w wyszukiwaniu
- **WPML**: Obsługa multilingual przez `__()` functions
- **Caching plugins**: Kompatybilny z cache'owaniem

## Troubleshooting

### Navbar nie pojawia się
1. Sprawdź czy `WPMZF_Navbar_Component::init()` jest wywołane
2. Sprawdź czy jesteś na stronie wtyczki (hook name)
3. Sprawdź JavaScript console na błędy

### Wyszukiwanie nie działa
1. Sprawdź AJAX endpoint w Network tab
2. Sprawdź nonce verification
3. Sprawdź czy post types istnieją

### Styl nie wygląda dobrze
1. Sprawdź czy CSS jest załadowany (inline w head)
2. Sprawdź konflikty z innymi stylami
3. Sprawdź responsive breakpoints

### Performance issues
1. Sprawdź ilość żądań AJAX (debouncing)
2. Sprawdź cache settings
3. Sprawdź czy assets nie są ładowane podwójnie

## Rozwój

### Dodawanie nowych funkcji
1. **Menu items**: Edytuj `get_menu_items()`
2. **Post types**: Dodaj do `search_all_post_types()`  
3. **Styles**: Rozszerz `get_css()`
4. **JavaScript**: Rozszerz `get_javascript()`

### Best practices
- **Testuj** na wszystkich breakpointach
- **Sprawdzaj** accessibility (klawiaura, screen readers)
- **Waliduj** kod (ESLint, PHP_CodeSniffer)
- **Dokumentuj** zmiany w tym pliku
