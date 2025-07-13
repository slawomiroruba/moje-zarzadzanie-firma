# Debug: Problem z dodawaniem aktywności dla firm

## Problem
URL: `https://app.agencjaluna.pl/wp-admin/admin.php?page=wpmzf_view_company&company_id=`
- URL kończy się pustym `company_id=` - to jest główny problem
- Błąd: "Brak wyragnych danych (ID osoby lub firmy)"
- Aktywności dodają się sporadycznie

## Rozwiązania wprowadzone

### 1. ✅ NAPRAWIONO: Link do widoku firmy
**Plik:** `class-wpmzf-companies-list-table.php`

**PRZED:**
```php
$view_link = sprintf('admin.php?page=wpmzf_view_company&company_id=%s', $item->ID);
```

**PO:**
```php
$view_link = add_query_arg([
    'page' => 'wpmzf_view_company',
    'company_id' => $item->ID
], admin_url('admin.php'));
```

### 2. ✅ DODANO: Szczegółowe debugowanie AJAX
**Plik:** `class-wpmzf-ajax-handler.php`

```php
// Debugowanie - sprawdźmy wszystkie dane które przychodzą
error_log('WPMZF add_activity: POST data: ' . print_r($_POST, true));
error_log('WPMZF add_activity: FILES data: ' . print_r($_FILES, true));

// Debugowanie wartości
error_log('WPMZF add_activity: person_id=' . $person_id . ', company_id=' . $company_id . ', content_length=' . strlen($content));

if (!$person_id && !$company_id) {
    error_log('WPMZF add_activity: ERROR - No person_id or company_id provided');
    wp_send_json_error(array('message' => 'Brak wyragnych danych (ID osoby lub firmy).'));
    return;
}
```

### 3. ✅ DODANO: Debugowanie JavaScript
**Plik:** `company-view.php`

```javascript
// Debugowanie przed wysłaniem
console.log('Form data before modification:');
for (let [key, value] of formData.entries()) {
    console.log(key, value);
}

// Debugowanie po modyfikacji
console.log('Form data after modification:');
for (let [key, value] of formData.entries()) {
    console.log(key, value);
}

// Sprawdzenie czy treść nie jest pusta
const content = formData.get('content');
if (!content || content.trim().length === 0) {
    showMessage('Błąd: Proszę wpisać treść aktywności.', 'error');
    return;
}
```

## 🧪 Instrukcje testowania

### Krok 1: Sprawdź poprawny link
1. Idź do listy firm: `/wp-admin/admin.php?page=wpmzf_companies`
2. Kliknij **"Zobacz szczegóły"** przy dowolnej firmie
3. **Sprawdź URL** - powinien być: `...page=wpmzf_view_company&company_id=XXX` (gdzie XXX to liczba)
4. **Jeśli URL kończy się pustym `company_id=`** - link jest nadal niepoprawny

### Krok 2: Sprawdź debugowanie
1. **Otwórz Developer Tools** (F12)
2. Idź do zakładki **Console**
3. Odśwież stronę widoku firmy
4. **Sprawdź czy są logi:**
   ```
   Company view initialized with ID: XXX
   Delayed initialization - loading data...
   Loading activities for company ID: XXX
   ```

### Krok 3: Test dodawania aktywności
1. **Wpisz tekst** w pole "Nowa Aktywność"
2. Kliknij **"Dodaj aktywność"**
3. **W Console sprawdź logi:**
   ```
   Form data before modification:
   content "twoja treść"
   activity_type "notatka"
   [etc...]
   
   Form data after modification:
   company_id "XXX"
   action "add_wpmzf_activity"
   security "hash..."
   [etc...]
   
   Submitting activity for company ID: XXX
   ```

### Krok 4: Sprawdź logi serwera
1. **Sprawdź logi PHP** (wp-content/debug.log lub error_log serwera)
2. **Szukaj wpisów:**
   ```
   WPMZF add_activity: POST data: Array([company_id] => XXX [content] => ...)
   WPMZF add_activity: person_id=0, company_id=XXX, content_length=YY
   ```

### Krok 5: Test różnych scenariuszy
1. **Test z pustą treścią** - powinien pokazać błąd "Proszę wpisać treść"
2. **Test z długą treścią** - powinien działać
3. **Test z załącznikami** - sprawdź czy działają
4. **Test z różnymi typami aktywności** - zmień dropdown

## 🔍 Możliwe przyczyny problemów

### Jeśli URL jest nadal pusty:
- Cache może być aktywny - wyczyść cache przeglądarki i WordPressa
- Sprawdź czy zmiany w `class-wpmzf-companies-list-table.php` zostały zapisane

### Jeśli JavaScript nie pokazuje company_id:
- Problem z race condition - sprawdź opóźnienie inicjalizacji
- Cache JavaScript - wyczyść cache przeglądarki
- Sprawdź czy nie ma błędów JS w Console

### Jeśli AJAX nie otrzymuje company_id:
- Problem z FormData - sprawdź czy `formData.set('company_id', companyId)` działa
- Problem z nonce - sprawdź czy security token jest prawidłowy
- Problem z routingiem AJAX - sprawdź czy action `add_wpmzf_activity` jest zarejestrowany

### Jeśli backend nadal pokazuje błąd:
- Sprawdź logi czy `$_POST['company_id']` zawiera wartość
- Sprawdź czy `intval()` nie zwraca 0
- Sprawdź czy nie ma konfliktów z innymi pluginami

## 🚨 Błędy do sprawdzenia

1. **URL z pustym company_id** → Sprawdź cache i czy zmiany zostały zapisane
2. **JavaScript errors** → Sprawdź Console i czy wszystkie skrypty się ładują
3. **AJAX 500 errors** → Sprawdź error_log serwera
4. **Nonce errors** → Sprawdź czy nonce są prawidłowe i nie wygasłe
5. **FormData nie zawiera danych** → Problem z HTML formularza lub JS

## 📋 Następne kroki po testach

Po przeprowadzeniu testów:
1. **Zbierz logi** z Console i error_log
2. **Określ na którym etapie** dokładnie problem występuje
3. **Usuń debugowanie** po znalezieniu rozwiązania
4. **Przetestuj na różnych przeglądarkach** i urządzeniach
