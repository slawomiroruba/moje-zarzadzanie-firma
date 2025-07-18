# Test Nowego Komponentu WPMZF Navbar

## Cel testów
Sprawdzenie czy nowy `WPMZF_Navbar_Component` działa poprawnie na wszystkich stronach wtyczki i zastępuje stary system bez problemów.

## Lista kontrolna - Podstawowe funkcjonalności

### ✅ 1. Inicjalizacja i renderowanie
- [ ] Navbar pojawia się na stronie głównej dashboardu
- [ ] Navbar pojawia się na widokach firm (company view)
- [ ] Navbar pojawia się na widokach osób (person view) 
- [ ] Navbar pojawia się na widokach projektów (project view)
- [ ] Navbar pojawia się na uniwersalnych widokach
- [ ] Navbar NIE pojawia się na stronach WordPress poza wtyczką

**Test:**
1. Przejdź do: `/wp-admin/admin.php?page=wpmzf_dashboard`
2. Sprawdź czy navbar jest widoczny na górze strony
3. Powtórz dla innych stron wtyczki

### ✅ 2. Wygląd i responsywność
- [ ] Gradient background (fioletowo-niebieski)
- [ ] Logo i tytuł "Zarządzanie Firmą" po lewej
- [ ] Menu nawigacyjne na środku
- [ ] Wyszukiwarka po prawej
- [ ] Wysokość 60px
- [ ] Responsive layout na mobile

**Test:**
1. Sprawdź navbar na desktop (> 1200px)
2. Zmień rozmiar okna do 768-1200px (tablet)
3. Zmień do < 768px (mobile)
4. Sprawdź czy layout się dostosowuje

### ✅ 3. Menu nawigacyjne
- [ ] Menu "CRM" z dropdown (Firmy, Osoby, Szanse, Oferty)
- [ ] Menu "Projekty" z dropdown (Projekty, Zadania, Czas pracy)
- [ ] Menu "Finanse" z dropdown (Faktury, Płatności, Koszty, Umowy)
- [ ] Menu "Zespół" z dropdown (Pracownicy, Aktywności)
- [ ] Menu "Narzędzia" z dropdown (Ważne linki)

**Test:**
1. Najedź na każdy element menu
2. Sprawdź czy dropdown się otwiera
3. Kliknij na elementy dropdown - sprawdź linki

### ✅ 4. Hover effects i animacje
- [ ] Hover na logo - efekt podniesienia
- [ ] Hover na menu items - zmiana koloru i podniesienie
- [ ] Hover na dropdown items - zmiana koloru i przesunięcie
- [ ] Dropdown arrow rotation przy hover
- [ ] Smooth transitions

**Test:**
1. Najedź myszką na różne elementy navbar
2. Sprawdź czy animacje są płynne
3. Sprawdź timing animacji (0.3s dla głównych, 0.2s dla dropdown)

### ✅ 5. Wyszukiwarka
- [ ] Input field z placeholder "Wyszukaj..."
- [ ] Icon search button po prawej
- [ ] Focus states działają
- [ ] Typing uruchamia wyszukiwanie po 2+ znakach
- [ ] Loading indicator podczas wyszukiwania

**Test:**
1. Kliknij w pole wyszukiwania
2. Wpisz "te" (2 znaki) - sprawdź czy pojawia się loading
3. Sprawdź czy wyniki się pojawiają

### ✅ 6. AJAX wyszukiwanie
- [ ] Wyszukiwanie działa po wpisaniu 2+ znaków
- [ ] Results pokazują się w dropdown pod search
- [ ] Grupy wyników (Firmy, Osoby, Projekty, etc.)
- [ ] Każdy wynik ma tytuł i excerpt
- [ ] Kliknięcie na wynik przenosi do właściwej strony
- [ ] "Brak wyników" gdy nic nie znaleziono

**Test:**
1. Wpisz nazwę istniejącej firmy
2. Sprawdź czy pojawia się w wynikach
3. Kliknij na wynik - sprawdź czy link działa
4. Wpisz "xyz123" - sprawdź "Brak wyników"

### ✅ 7. Keyboard navigation
- [ ] Tab navigation przez menu items
- [ ] Enter na menu item - przechodzi do strony
- [ ] Arrow down/up w search results
- [ ] Enter na search result - przechodzi do strony
- [ ] Escape zamyka dropdown/search

**Test:**
1. Użyj Tab do nawigacji
2. W search wpisz tekst, użyj strzałek
3. Sprawdź Escape w różnych kontekstach

### ✅ 8. Click outside behavior
- [ ] Kliknięcie poza search zamyka wyniki
- [ ] Kliknięcie poza dropdown zamyka menu
- [ ] Kliknięcie poza navbar nie wpływa na funkcjonalność

**Test:**
1. Otwórz dropdown menu, kliknij obok
2. Otwórz search results, kliknij obok
3. Sprawdź czy się zamykają

## Lista kontrolna - Integracja

### ✅ 9. Kompatybilność z uniwersalnymi widokami
- [ ] Navbar działa na company view
- [ ] Navbar działa na person view  
- [ ] Navbar działa na project view
- [ ] Navbar działa na task view
- [ ] CSS nie koliduje z universal-view.css

**Test:**
1. Przejdź do uniwersalnego widoku firmy
2. Sprawdź czy navbar i widok działają razem
3. Sprawdź czy nie ma konfliktów CSS

### ✅ 10. WordPress integration
- [ ] Nonce verification działa w AJAX
- [ ] Lokalizacja strings (__() functions)
- [ ] Escape functions (esc_html, esc_url)
- [ ] No PHP errors w debug.log
- [ ] No JavaScript errors w console

**Test:**
1. Sprawdź browser console na błędy
2. Sprawdź `/wp-content/debug.log` na błędy PHP
3. Sprawdź Network tab na błędy AJAX

### ✅ 11. Performance
- [ ] CSS i JS są inline (nie ma dodatkowych HTTP requests)
- [ ] AJAX debouncing działa (max 1 request na 300ms)
- [ ] Search results ograniczone (5 per post type)
- [ ] No memory leaks w JavaScript

**Test:**
1. Sprawdź Network tab - czy brak external CSS/JS files
2. Wpisuj szybko w search - czy requests są ograniczone
3. Sprawdź Memory tab w DevTools

## Lista kontrolna - Edge cases

### ✅ 12. Błędy i edge cases
- [ ] Brak połączenia z AJAX - graceful failure
- [ ] Bardzo długie nazwy w menu - nie łamią layout
- [ ] Bardzo dużo wyników search - scroll działa
- [ ] Brak wyników search - komunikat wyświetla się
- [ ] Nieprawidłowe znaki w search - nie powodują błędów

**Test:**
1. Symuluj błąd AJAX (network offline)
2. Testuj długie nazwy firm/projektów
3. Testuj special characters w search

### ✅ 13. Compatibility modes
- [ ] Działa gdy ACF nie jest zainstalowane
- [ ] Działa z różnymi WordPress themes
- [ ] Działa z enabled caching plugins
- [ ] Dark mode detection działa w supported browsers

**Test:**
1. Sprawdź z różnymi pluginami aktywymi
2. Sprawdź z dark mode w systemie/browser

## Lista kontrolna - Regresja

### ✅ 14. Stary system nie wpływa
- [ ] Stary navbar nie renderuje się podwójnie
- [ ] Stare CSS/JS files nie są ładowane
- [ ] Brak konfliktów namespace/functions
- [ ] Wszystkie linki działają tak samo jak wcześniej

**Test:**
1. Sprawdź czy nie ma duplikacji navbar
2. Sprawdź Network tab na stare pliki navbar.css/navbar.js
3. Porównaj funkcjonalność z backup strony

### ✅ 15. Wszystkie strony wtyczki
- [ ] Dashboard - `/admin.php?page=wpmzf_dashboard`
- [ ] Companies list - `/admin.php?page=wpmzf_companies`
- [ ] Persons list - `/admin.php?page=wpmzf_persons`
- [ ] Projects list - `/admin.php?page=wpmzf_projects`
- [ ] Company view - `/admin.php?page=wpmzf_view_company&company_id=X`
- [ ] Person view - `/admin.php?page=wpmzf_view_person&person_id=X`
- [ ] Project view - `/admin.php?page=wpmzf_view_project&project_id=X`

**Test:**
Przejdź przez każdą stronę i sprawdź:
1. Navbar jest widoczny
2. Style są poprawne  
3. Funkcjonalności działają
4. Brak błędów w console

## Debugging checklist

### Jeśli navbar nie pojawia się:
1. Sprawdź czy `WPMZF_Navbar_Component::init()` jest wywołane w main plugin file
2. Sprawdź czy hook `admin_enqueue_scripts` jest zarejestrowany
3. Sprawdź czy current page hook jest w liście `is_wpmzf_page()`
4. Sprawdź czy `render_navbar()` jest wywołane w view

### Jeśli CSS nie działa:
1. Sprawdź czy `get_css()` method zwraca style
2. Sprawdź czy `wp_add_inline_style()` jest wywołane
3. Sprawdź browser DevTools - czy CSS jest w `<head>`
4. Sprawdź konflikty z innymi stylami

### Jeśli JavaScript nie działa:
1. Sprawdź Console na błędy JavaScript
2. Sprawdź czy `get_javascript()` zwraca kod
3. Sprawdź czy `wp_add_inline_script()` jest wywołane
4. Sprawdź czy `wpmzfNavbar` object jest dostępny

### Jeśli AJAX nie działa:
1. Sprawdź Network tab na żądania do `admin-ajax.php`
2. Sprawdź czy action `wpmzf_global_search` jest zarejestrowany
3. Sprawdź czy nonce verification przechodzi
4. Sprawdź czy response jest poprawny JSON

## Success criteria

✅ **Navbar component jest gotowy gdy:**
1. Wszystkie 15 sekcji testowych przechodzą
2. Brak błędów w PHP debug log
3. Brak błędów w JavaScript console
4. Performance nie jest gorsza niż wcześniej
5. Wszystkie funkcjonalności działają jak wcześniej lub lepiej

## Rollback plan

**Jeśli coś nie działa:**
1. Przywróć backup: `class-wpmzf-navbar.php.backup`
2. Cofnij zmiany w main plugin file
3. Cofnij zmiany w `WPMZF_View_Helper`
4. Restart plugin activation

**Files to restore:**
- `/includes/admin/components/class-wpmzf-navbar.php`
- `/moje-zarzadzanie-firma.php` (require statement + init)
- `/includes/admin/components/class-wpmzf-view-helper.php` (old methods)

## Next steps po testach

**Jeśli testy przechodzą:**
1. Usuń stary plik `class-wpmzf-navbar.php`
2. Usuń stare `navbar.css` i `navbar.js` files
3. Update dokumentacji
4. Commit changes to repository

**Jeśli testy nie przechodzą:**
1. Fix identified issues
2. Re-run failed test sections
3. Update component based on findings
4. Document any limitations or known issues
