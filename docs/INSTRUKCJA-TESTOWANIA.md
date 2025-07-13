# Instrukcja Testowania Systemu Szans Sprzedaży

## ✅ Status Implementacji

System szans sprzedaży jest **w pełni zaimplementowany i gotowy do użycia**!

## 🎯 Funkcjonalności do przetestowania

### 1. Dashboard z widgetem szans
**Lokalizacja:** `wp-admin` → Główny dashboard wtyczki

**Co sprawdzić:**
- Widget "Szanse sprzedaży" z podsumowaniem statusów
- Lista szans do zamknięcia w ciągu 7 dni
- Linki do Kanban i dodawania nowych szans

### 2. Lista szans sprzedaży  
**Lokalizacja:** `Szanse Sprzedaży` → `Wszystkie szanse`

**Co sprawdzić:**
- Lista wszystkich szans z kolumnami statusu
- Filtrowanie według statusów
- Dodawanie nowych szans

### 3. Tablica Kanban ⭐ GŁÓWNA FUNKCJONALNOŚĆ
**Lokalizacja:** `Szanse Sprzedaży` → `Kanban`

**Co sprawdzić:**
- 5 kolumn dla statusów: Nowa, W toku, Negocjacje, Wygrana, Przegrana
- Statystyki w górnej części (liczba szans, łączna wartość)
- **Drag & Drop:** Przeciągnij karty między kolumnami
- **Modal powodu:** Przy przenoszeniu do "Wygrana"/"Przegrana" pojawi się okno do wprowadzenia powodu
- **Automatyczna konwersja:** Wygrane szanse (z powodem) automatycznie stają się projektami
- Responsywność na urządzeniach mobilnych

### 4. Pola ACF w edycji szansy
**Lokalizacja:** `Szanse Sprzedaży` → Edycja konkretnej szansy

**Pola do sprawdzenia:**
- Firma (relationship z firmami)
- Wartość (PLN)
- Prawdopodobieństwo (%)
- Przewidywana data zamknięcia
- Powód wygranej/przegranej
- Osoba kontaktowa
- Źródło

### 5. Meta box konwersji
**Lokalizacja:** W edycji szansy, w prawej kolumnie

**Co sprawdzić:**
- Status konwersji na projekt
- Link do skonwertowanego projektu (jeśli istnieje)

## 🚀 Scenariusz testowy krok po kroku

### Krok 1: Dodaj nową szansę
1. Idź do `Szanse Sprzedaży` → `Dodaj nową`
2. Wypełnij podstawowe dane
3. Ustaw pola ACF (firmę, wartość, prawdopodobieństwo)
4. Publikuj

### Krok 2: Testuj Kanban
1. Idź do `Szanse Sprzedaży` → `Kanban`
2. Znajdź swoją szansę w kolumnie "Nowa"
3. Przeciągnij ją do "W toku"
4. Następnie do "Negocjacje"
5. **Kluczowy test:** Przeciągnij do "Wygrana"
   - Powinien pojawić się modal z polem tekstowym
   - Wprowadź powód (np. "Klient zaakceptował ofertę")
   - Kliknij "Zapisz"
   - Szansa powinna automatycznie stać się projektem

### Krok 3: Sprawdź konwersję
1. Po zapisaniu powodu w kroku 2, sprawdź:
   - Karta powinna mieć badge "Skonwertowano"
   - Przycisk "Zobacz projekt"
2. Idź do `Projekty` → sprawdź czy utworzył się nowy projekt
3. Wróć do edycji szansy → sprawdź meta box konwersji

### Krok 4: Test responsywności
1. Otwórz Kanban na telefonie/tablecie
2. Sprawdź czy kolumny się adaptują
3. Przetestuj przeciąganie na urządzeniu dotykowym

## 🔧 Potencjalne problemy i rozwiązania

### Problem: Brak pól ACF
**Rozwiązanie:** Upewnij się, że ACF Pro jest aktywne

### Problem: Drag & drop nie działa
**Rozwiązanie:** Sprawdź konsolę błędów w przeglądarce, wyczyść cache

### Problem: Modal się nie pojawia
**Rozwiązanie:** Sprawdź czy JavaScript jest załadowany, wyłącz inne wtyczki konfliktujące

### Problem: Konwersja nie działa
**Rozwiązanie:** Sprawdź uprawnienia użytkownika, sprawdź logi błędów

## 📊 Dane testowe

W systemie masz już 3 przykładowe szanse:
- "Przykładowa szansa sprzedaży" (status: W toku)
- "Strona www" (status: Nowa)  
- "Rozbudowa systemu CRM" (status: Negocjacje)

## 🎉 Oczekiwane rezultaty

Po prawidłowym teście powinieneś mieć:
- Działającą tablicę Kanban z płynnym drag & drop
- Modal wymagający powodu przy statusach końcowych
- Automatyczną konwersję wygranych szans na projekty
- Responsywny interfejs na wszystkich urządzeniach
- Statystyki i raporty w dashboardzie

---

**System jest w pełni funkcjonalny i gotowy do produkcyjnego użycia! 🚀**
