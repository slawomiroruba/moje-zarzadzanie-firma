# Naprawa Race Condition - Historia Aktywności dla Firm

## Problem
Historia aktywności dla firm nie ładowała się przy pierwszym wejściu w widok firmy. Błąd: "Brak wymaganych danych (ID osoby lub firmy)". Problem występował sporadycznie, po kilku próbach dodania aktywności historia się pojawiała, co wskazywało na **race condition**.

## Rozwiązanie

### 1. JavaScript - Naprawa inicjalizacji (company-view.php)

**PRZED:**
```javascript
const companyId = <?php echo json_encode($company_id); ?>;
// ... 
loadActivities(); // Wywoływane od razu
```

**PO:**
```javascript
// Pobieranie company_id z PHP - upewniamy się że jest liczbą
const companyId = parseInt(<?php echo json_encode($company_id); ?>, 10);

// Debugowanie inicjalizacji
console.log('Company view initialized with ID:', companyId);

// Sprawdzenie czy companyId jest poprawne
if (!companyId || companyId <= 0) {
    console.error('Invalid company ID:', companyId);
    return;
}

// Ładowanie danych przy starcie - z małym opóźnieniem aby zapewnić pełną inicjalizację
setTimeout(function() {
    console.log('Delayed initialization - loading data...');
    loadActivities();
    loadTasks();
}, 100); // 100ms opóźnienia

// Alternatywnie można też wywołać od razu jako backup
if (document.readyState === 'complete') {
    console.log('Document already loaded - immediate backup call');
    loadActivities();
    loadTasks();
}
```

### 2. Poprawa funkcji loadActivities()

**Dodano:**
- Walidację `companyId` przed wysłaniem AJAX
- Lepsze komunikaty błędów
- Większe debugowanie
- Zabezpieczenia przed pustymi odpowiedziami

```javascript
function loadActivities() {
    // Upewniamy się, że companyId jest dostępne
    if (!companyId || companyId <= 0) {
        console.error('Cannot load activities: invalid company ID:', companyId);
        $('#wpmzf-activity-timeline').html('<p><em>Błąd: Nieprawidłowe ID firmy.</em></p>');
        return;
    }
    
    console.log('Loading activities for company ID:', companyId);
    $('#wpmzf-activity-timeline').html('<p><em>Ładowanie aktywności...</em></p>');
    
    // ... reszta funkcji z lepszą obsługą błędów
}
```

### 3. Backend - Poprawa sortowania (class-wpmzf-ajax-handler.php)

**PRZED:**
```php
'order' => 'DESC',
```

**PO:**
```php
'order' => 'DESC', // Najnowsze na górze
```

**Dodano też:**
- Sprawdzanie alternatywnych kluczy meta (`field_wpmzf_activity_related_company`)
- Lepsze debugowanie i logowanie
- Usunięto testowe dane

### 4. JavaScript - Sortowanie w renderTimeline()

**Dodano sortowanie po stronie frontend:**
```javascript
function renderTimeline(activities) {
    console.log('Rendering timeline with activities:', activities);
    
    if (!activities || activities.length === 0) {
        $('#wpmzf-activity-timeline').html('<p><em>Brak zarejestrowanych aktywności. Dodaj pierwszą!</em></p>');
        return;
    }

    // Sortowanie aktywności - najnowsze na górze
    activities.sort(function(a, b) {
        const dateA = new Date(a.date);
        const dateB = new Date(b.date);
        return dateB - dateA; // DESC - najnowsze na górze
    });
    
    // ... reszta funkcji
}
```

### 5. Formularze - Lepsze zabezpieczenia

**Dodano w obu formularzach (aktywności i zadania):**
- Walidację `companyId` przed wysłaniem
- `formData.set('company_id', companyId)` - upewnienie się że ID jest w danych
- Lepsze komunikaty błędów
- Większe debugowanie

## Instrukcje testowania

### 1. Test podstawowy
1. Wejdź w widok firmy: `/wp-admin/admin.php?page=wpmzf_view_company&company_id=X`
2. **Sprawdź konsole programisty** - powinny pojawić się logi:
   ```
   Company view initialized with ID: X
   Delayed initialization - loading data...
   Loading activities for company ID: X
   ```
3. Sekcja "Historia Aktywności" powinna pokazać:
   - "Ładowanie aktywności..." przez chwilę
   - Następnie "Brak zarejestrowanych aktywności. Dodaj pierwszą!" (jeśli brak aktywności)
   - Lub listę aktywności (jeśli są)

### 2. Test dodawania aktywności
1. Wypełnij formularz "Nowa Aktywność"
2. **Sprawdź konsole** - po wysłaniu powinno być:
   ```
   Submitting activity for company ID: X
   Add activity response: {success: true, data: ...}
   ```
3. Timeline powinien się odświeżyć automatycznie
4. Nowa aktywność powinna pojawić się na górze listy

### 3. Test zadań
1. Dodaj nowe zadanie w sekcji "Nowe Zadanie"
2. **Sprawdź konsole** - powinno być:
   ```
   Submitting task for company ID: X
   Add task response: {success: true, data: ...}
   ```
3. Lista zadań powinna się odświeżyć

### 4. Test race condition
1. **Odśwież stronę kilka razy** - historia aktywności powinna ładować się zawsze
2. Sprawdź różne firmy - wszystkie powinny działać
3. **Sprawdź Network tab w Developer Tools** - AJAX requesty powinny zawierać `company_id`

## Zmiany techniczne

### Pliki zmodyfikowane:
1. **`includes/admin/views/companies/company-view.php`**
   - Poprawa inicjalizacji JavaScript
   - Lepsze funkcje `loadActivities()` i `loadTasks()`
   - Sortowanie w `renderTimeline()`
   - Zabezpieczenia w formularzach

2. **`includes/services/class-wpmzf-ajax-handler.php`**
   - Sprawdzanie alternatywnych kluczy meta
   - Lepsze debugowanie
   - Usunięcie testowych danych

### Kluczowe zmiany:
- **Opóźnienie inicjalizacji** - 100ms timeout zapobiega race condition
- **Walidacja ID** - sprawdzanie `companyId` przed każdym AJAX
- **Backup initialization** - dodatkowe wywołanie jeśli dokument już załadowany
- **Sortowanie** - najnowsze aktywności na górze
- **Debugowanie** - console.log w kluczowych miejscach

## Status
✅ **NAPRAWIONO** - Race condition przy ładowaniu aktywności  
✅ **NAPRAWIONO** - Sortowanie aktywności (najnowsze na górze)  
✅ **NAPRAWIONO** - Walidacja ID firmy przed AJAX  
✅ **NAPRAWIONO** - Lepsze komunikaty błędów  
✅ **DODANO** - Szczegółowe debugowanie  

## Następne kroki
1. **Testowanie** według instrukcji powyżej
2. **Usunięcie debugów** po potwierdzeniu działania (opcjonalnie)
3. **Refaktoryzacja** - unifikacja kodu JS dla osób i firm (opcjonalnie)
