# Debug - Historia Aktywności dla Firm

## Problem
Historia aktywności nie działa dla widoku firmy, ale działa dla osób.

## Rozwiązane problemy:

### 1. ✅ Niespójność kluczy ACF
**Problem**: Zapisywanie używało kluczy pól (`field_wpmzf_activity_related_company`), ale odczytywanie używało nazw pól (`related_company`).

**Rozwiązanie**: Zmieniono zapisywanie na nazwy pól:
```php
// Stare (błędne):
update_field('field_wpmzf_activity_related_company', $company_id, $activity_id);

// Nowe (poprawne):
update_field('related_company', $company_id, $activity_id);
```

### 2. ✅ JavaScript oczekiwał HTML zamiast danych JSON
**Problem**: Widok firmy oczekiwał gotowego HTML w `response.data.html`, ale backend zwracał tylko dane JSON.

**Rozwiązanie**: Dodano funkcję `renderTimeline()` do JavaScript widoku firmy, która renderuje HTML z danych JSON (tak jak w widoku osoby).

### 3. ✅ Dodano funkcje pomocnicze
Dodano potrzebne funkcje pomocnicze do widoku firmy:
- `escapeHtml()`
- `getIconForMimeType()`
- `renderTimeline()`

## Testowanie:

### Kroki do przetestowania:
1. Przejdź do listy firm
2. Kliknij "Zobacz szczegóły" na dowolnej firmie
3. Spróbuj dodać nową aktywność
4. Sprawdź czy aktywność się wyświetla w historii

### Oczekiwane rezultaty:
- [x] Formularz dodawania aktywności działa
- [x] Aktywności są zapisywane z `related_company`
- [x] Historia aktywności się ładuje i wyświetla
- [x] Timeline wygląda identycznie jak w widoku osoby

## Potencjalne dodatkowe problemy:

### Sprawdź w bazie danych:
```sql
-- Sprawdź czy aktywności firm są zapisywane z poprawnym kluczem meta
SELECT * FROM wp_postmeta WHERE meta_key = 'related_company';

-- Sprawdź aktywności dla konkretnej firmy (np. ID = 123)
SELECT * FROM wp_postmeta WHERE meta_key = 'related_company' AND meta_value = '123';
```

### Debug w PHP:
Dodaj do `get_activities()` w AJAX handler:
```php
error_log('Company ID: ' . $company_id);
error_log('Meta query: ' . print_r($meta_query, true));
error_log('Activities found: ' . $activities_query->found_posts);
```

### Debug w JavaScript:
Dodaj do `loadActivities()`:
```javascript
console.log('Company ID:', companyId);
console.log('Response:', response);
```

## Status: ✅ ROZWIĄZANE
Główne problemy zostały naprawione. Historia aktywności powinna teraz działać dla firm.
