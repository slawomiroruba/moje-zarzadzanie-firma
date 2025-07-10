# System Wielu Kontaktów - Instrukcja

## Przegląd funkcjonalności

System pozwala na dodawanie wielu adresów e-mail i numerów telefonów do osób oraz firm. Każdy kontakt może mieć:
- **Opis/Typ** - np. "służbowy", "prywatny", "alarmowy", "dział marketingu"
- **Oznaczenie głównego** - tylko jeden kontakt danego typu może być oznaczony jako główny

## Dla Osób

### Pola dostępne:
- **Adresy e-mail** - repeater z polami:
  - Adres e-mail (wymagane)
  - Typ/Opis (opcjonalne) - np. "służbowy", "prywatny", "marketing"
  - Główny (checkbox) - tylko jeden może być zaznaczony

- **Numery telefonów** - repeater z polami:
  - Numer telefonu (wymagane)
  - Typ/Opis (opcjonalne) - np. "służbowy", "prywatny", "alarmowy"
  - Główny (checkbox) - tylko jeden może być zaznaczony

## Dla Firm

### Pola dostępne:
- **Adresy e-mail** - repeater z polami:
  - Adres e-mail (wymagane)
  - Typ/Opis (opcjonalne) - np. "główny", "marketing", "wsparcie", "fakturowanie"
  - Główny (checkbox) - tylko jeden może być zaznaczony

- **Numery telefonów** - repeater z polami:
  - Numer telefonu (wymagane)
  - Typ/Opis (opcjonalne) - np. "centrala", "dział sprzedaży", "wsparcie techniczne"
  - Główny (checkbox) - tylko jeden może być zaznaczony

## Jak używać

### Dodawanie kontaktów:
1. W edycji osoby/firmy znajdź sekcję "Adresy e-mail" lub "Numery telefonów"
2. Kliknij "Dodaj adres e-mail" lub "Dodaj numer telefonu"
3. Wpisz adres/numer
4. Opcjonalnie dodaj opis (typ kontaktu)
5. Jeśli to główny kontakt, zaznacz checkbox "Główny"

### Oznaczanie głównego kontaktu:
- Tylko jeden e-mail może być oznaczony jako główny
- Tylko jeden telefon może być oznaczony jako główny
- Zaznaczenie nowego głównego automatycznie odznacza poprzedni
- Główny kontakt jest wyróżniony wizualnie

### Walidacja:
- System automatycznie pilnuje, by był tylko jeden główny kontakt każdego typu
- Przy próbie zaznaczenia drugiego głównego, pierwszy zostanie automatycznie odznaczony
- Pojawi się powiadomienie o zmianie

## Wyświetlanie kontaktów

### W teczkach osób:
- Kontakty są wyświetlane w sekcji "Dane podstawowe"
- Główny kontakt jest oznaczony znaczkiem "główny"
- Typ kontaktu jest wyświetlany w nawiasach
- Kontakty są klikalne (mailto: i tel:)

### W tabelach (lista osób):
- Wyświetlany jest główny e-mail i główny telefon
- Jeśli nie ma głównego, wyświetlany jest pierwszy dostępny
- Jeśli nie ma żadnego, wyświetlane jest "Brak"

## Przykłady użycia

### Osoba:
- E-mail główny: jan.kowalski@firma.pl (służbowy) ⭐główny
- E-mail dodatkowy: j.kowalski@gmail.com (prywatny)
- Telefon główny: +48 123 456 789 (służbowy) ⭐główny
- Telefon dodatkowy: +48 987 654 321 (prywatny)

### Firma:
- E-mail główny: kontakt@firma.pl (główny) ⭐główny
- E-mail marketingu: marketing@firma.pl (marketing)
- E-mail wsparcia: pomoc@firma.pl (wsparcie techniczne)
- Telefon główny: +48 22 123 45 67 (centrala) ⭐główny
- Telefon sprzedaży: +48 22 123 45 68 (dział sprzedaży)

## Styl wizualny

- Repeater z głównym kontaktem ma niebieskie tło
- Pola głównego kontaktu mają żółte tło z obramowaniem
- Ikony rozróżniają typy kontaktów (✉ dla e-maili, ☎ dla telefonów)
- Główne kontakty mają niebieską odznakę "główny"

## Uwagi techniczne

- Usunięto stare pojedyncze pola e-mail i telefon
- System jest w pełni kompatybilny z ACF
- JavaScript automatycznie pilnuje walidacji
- Kontakty są zapisywane jako repeater ACF
- Helper klasy udostępniają API do pobierania kontaktów
