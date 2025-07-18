# Test Uniwersalnego Systemu Widoków WPMZF

## Przygotowanie do testów

1. **Sprawdź, czy plugin jest aktywny**
   - Przejdź do WordPress Admin → Wtyczki
   - Upewnij się, że "Moje Zarządzanie Firmą" jest aktywna

2. **Wyczyść cache** (jeśli używasz cache'owania)
   - Wyczyść cache WordPress
   - Wyczyść cache przeglądarki (Ctrl+F5)

## Test 1: Podstawowe działanie widoków

### Widok firmy
1. Przejdź do: `/wp-admin/admin.php?page=wpmzf_view_company&company_id=273`
2. Sprawdź czy:
   - ✅ Strona się ładuje bez błędów
   - ✅ Widoczne są 3 kolumny (dane podstawowe, zadania, timeline)
   - ✅ Formularz dodawania aktywności jest widoczny
   - ✅ CSS jest prawidłowo załadowany

### Widok osoby
1. Przejdź do: `/wp-admin/admin.php?page=wpmzf_view_person&person_id=[ID_OSOBY]`
2. Sprawdź podobne elementy jak w widoku firmy

### Widok projektu
1. Przejdź do: `/wp-admin/admin.php?page=wpmzf_view_project&project_id=[ID_PROJEKTU]`
2. Sprawdź podobne elementy

## Test 2: Funkcjonalność zadań

### Dodawanie zadań
1. W sekcji "Zadania" kliknij "Dodaj zadanie"
2. Wypełnij formularz:
   - Nazwa zadania: "Test zadanie"
   - Priorytet: "Średni"
   - Data: jutrzejsza data
3. Kliknij "Dodaj zadanie"
4. Sprawdź czy:
   - ✅ Zadanie pojawiło się na liście
   - ✅ Pokazuje się komunikat sukcesu
   - ✅ Formularz został wyczyszczony

### Edycja zadań
1. Kliknij ikonę edycji przy zadaniu
2. Zmień nazwę zadania
3. Kliknij "Zapisz"
4. Sprawdź czy:
   - ✅ Zmiany zostały zapisane
   - ✅ Modal się zamknął
   - ✅ Lista zadań została odświeżona

### Oznaczanie jako wykonane
1. Kliknij checkbox przy zadaniu
2. Sprawdź czy:
   - ✅ Zadanie zostało przekreślone
   - ✅ Status się zmienił na "Zakończone"

### Usuwanie zadań
1. Kliknij ikonę kosza przy zadaniu
2. Potwierdź usunięcie
3. Sprawdź czy:
   - ✅ Zadanie zniknęło z listy
   - ✅ Pokazał się komunikat potwierdzenia

## Test 3: Funkcjonalność aktywności

### Dodawanie notatki
1. Kliknij zakładkę "Notatka"
2. Wpisz tekst w edytorze
3. Kliknij "Dodaj notatkę"
4. Sprawdź czy:
   - ✅ Notatka pojawiła się w timeline
   - ✅ Pokazuje się z prawidłową datą i autorem

### Wysyłanie emaila
1. Kliknij zakładkę "Email"
2. Wypełnij formularz emaila
3. Kliknij "Wyślij email"
4. Sprawdź czy:
   - ✅ Email został zapisany jako aktywność
   - ✅ Pojawił się w timeline

### Upload plików
1. W formularzu aktywności dodaj załącznik
2. Przeciągnij plik lub kliknij "Wybierz pliki"
3. Dodaj aktywność z załącznikiem
4. Sprawdź czy:
   - ✅ Plik został przesłany
   - ✅ Widoczny jest w timeline jako załącznik

## Test 4: Filtrowanie timeline

### Filtr po typie
1. W sekcji Timeline użyj filtra typu aktywności
2. Wybierz "Tylko notatki"
3. Sprawdź czy:
   - ✅ Pokazane są tylko notatki
   - ✅ Inne typy aktywności są ukryte

### Filtr po dacie
1. Ustaw zakres dat
2. Kliknij "Filtruj"
3. Sprawdź czy:
   - ✅ Pokazane są tylko aktywności z wybranego okresu

## Test 5: Responsywność

### Desktop
1. Przetestuj na standardowym ekranie
2. Sprawdź czy wszystkie kolumny są widoczne

### Tablet/Mobile
1. Zmień rozmiar okna na mobilny
2. Sprawdź czy:
   - ✅ Layout zmienia się na pojedynczą kolumnę
   - ✅ Wszystkie funkcje działają
   - ✅ Formularze są użyteczne na mobile

## Test 6: AJAX i błędy

### Test połączenia AJAX
1. Otwórz Developer Tools (F12)
2. Przejdź do zakładki Network
3. Wykonaj akcje (dodaj zadanie, aktywność)
4. Sprawdź czy:
   - ✅ Żądania AJAX się wykonują
   - ✅ Zwracają status 200
   - ✅ Nie ma błędów JavaScript w konsoli

### Test walidacji
1. Spróbuj dodać zadanie bez nazwy
2. Spróbuj dodać aktywność bez treści
3. Sprawdź czy:
   - ✅ Pokazują się odpowiednie błędy walidacji
   - ✅ Formularz nie zostaje wysłany

## Typowe problemy i rozwiązania

### Problem: Strona się nie ładuje
**Rozwiązanie:**
- Sprawdź logi błędów PHP
- Upewnij się, że wszystkie pliki zostały poprawnie utworzone
- Sprawdź, czy plugin został aktywowany

### Problem: Brak stylów CSS
**Rozwiązanie:**
- Sprawdź czy plik `assets/css/universal-view.css` istnieje
- Wyczyść cache
- Sprawdź logi błędów 404

### Problem: AJAX nie działa
**Rozwiązanie:**
- Sprawdź konsolę JavaScript na błędy
- Upewnij się, że `universal-view.js` się ładuje
- Sprawdź czy nonce są prawidłowe

### Problem: Formularze nie działają
**Rozwiązanie:**
- Sprawdź czy wszystkie wymagane pola są wypełnione
- Sprawdź logi błędów AJAX
- Upewnij się, że handler AJAX jest zarejestrowany

## Lokalizacja plików

- **Controller:** `/includes/admin/class-wpmzf-universal-view-controller.php`
- **Template:** `/includes/admin/views/universal/universal-view-template.php`
- **Renderer:** `/includes/admin/views/universal/class-wpmzf-universal-view-renderer.php`
- **JavaScript:** `/includes/admin/views/universal/universal-view.js`
- **CSS:** `/assets/css/universal-view.css`
- **Komponenty:** `/includes/admin/views/universal/components/`

## Raporty błędów

Jeśli znajdziesz błędy, sprawdź:
1. **Logi PHP:** `/wp-content/debug.log`
2. **Konsola JavaScript:** Developer Tools → Console
3. **Żądania AJAX:** Developer Tools → Network → XHR

## Następne kroki

Po pomyślnych testach możesz:
1. Dodać więcej typów postów do systemu
2. Dostosować konfigurację sekcji dla różnych typów
3. Dodać nowe komponenty widoków
4. Rozszerzyć funkcjonalność AJAX
