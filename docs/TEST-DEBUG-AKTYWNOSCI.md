# Test Debug - Aktywności Firm

## Sprawdzenie 1: Dodawanie aktywności
1. Przejdź do widoku firmy: https://app.agencjaluna.pl/wp-admin/admin.php?page=wpmzf_view_company&company_id=[ID_FIRMY]
2. Spróbuj dodać aktywność
3. Sprawdź w konsoli przeglądarki (F12) czy są błędy JavaScript
4. Sprawdź w logach PHP (`/wp-content/debug.log`) czy są błędy AJAX

## Sprawdzenie 2: Baza danych
W phpMyAdmin wykonaj zapytania:

```sql
-- Sprawdź czy istnieją aktywności
SELECT COUNT(*) FROM wp_posts WHERE post_type = 'activity';

-- Sprawdź aktywności z polami meta
SELECT p.ID, p.post_title, p.post_date, pm.meta_key, pm.meta_value 
FROM wp_posts p 
LEFT JOIN wp_postmeta pm ON p.ID = pm.post_id 
WHERE p.post_type = 'activity' 
AND pm.meta_key IN ('related_person', 'related_company')
ORDER BY p.post_date DESC;

-- Sprawdź konkretne aktywności dla firm
SELECT p.ID, p.post_title, pm.meta_value as company_id
FROM wp_posts p 
JOIN wp_postmeta pm ON p.ID = pm.post_id 
WHERE p.post_type = 'activity' 
AND pm.meta_key = 'related_company';
```

## Sprawdzenie 3: ACF Fields
W WordPress Admin → Custom Fields → Field Groups sprawdź czy:
1. Grupa "Szczegóły Aktywności" istnieje
2. Pole "related_company" istnieje
3. Pole "related_person" ma `required = 0`

## Możliwe przyczyny problemu:

### A. ACF nie zapisuje poprawnie
Jeśli `update_field('related_company', $company_id, $activity_id)` nie działa, może być problem z:
- Niewłaściwym kluczem pola
- Cache ACF
- Niepoprawną konfiguracją pola

### B. Query nie znajduje aktywności
Jeśli zapytanie `WP_Query` z `meta_key = 'related_company'` nie znajduje postów, może być problem z:
- Nazwą klucza meta
- Formatem danych w meta_value
- Cache WordPress

### C. JavaScript nie renderuje timeline
Jeśli dane są poprawne, ale timeline się nie wyświetla, może być problem z:
- Funkcją `renderTimeline()`
- Formatem danych
- CSS timeline

## Fix testowy
Jeśli problem się utrzymuje, dodaj to do `get_activities()`:

```php
// Temporary fix - zwróć przykładowe dane
if ($company_id) {
    $test_data = [
        [
            'id' => 999,
            'content' => 'Test aktywności dla firmy',
            'date' => current_time('Y-m-d H:i:s'),
            'type' => 'Notatka',
            'author' => 'System',
            'avatar' => 'https://www.gravatar.com/avatar/00000000000000000000000000000000?d=mp&f=y',
            'attachments' => []
        ]
    ];
    wp_send_json_success($test_data);
    return;
}
```
