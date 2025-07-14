# Branding LunaApp - Instrukcja

## Co zostało dodane

Dodano nowy serwis `WPMZF_Branding_Service` do wtyczki "Moje Zarządzanie Firmą", który automatycznie:

### 1. Dodaje favicon i meta tagi na wszystkich stronach WordPress

Gdy wtyczka jest aktywna, automatycznie dodawane są następujące elementy do sekcji `<head>` na WSZYSTKICH stronach:

```html
<link rel="icon" type="image/png" href="/favicon-96x96.png" sizes="96x96" />
<link rel="icon" type="image/svg+xml" href="/favicon.svg" />
<link rel="shortcut icon" href="/favicon.ico" />
<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png" />
<meta name="apple-mobile-web-app-title" content="LunaApp" />
<meta name="apple-mobile-web-app-capable" content="yes" />
<meta name="apple-mobile-web-app-status-bar-style" content="default" />
<link rel="manifest" href="/site.webmanifest" />
<meta name="theme-color" content="#ffffff" />
<meta name="msapplication-TileColor" content="#ffffff" />
<meta name="mobile-web-app-capable" content="yes" />
<meta name="application-name" content="LunaApp" />
```

### 2. Ostylowuje strony logowania i rejestracji WordPress

Dodane zostały nowoczesne style CSS, które nadają stronom logowania i rejestracji wygląd spójny z aplikacją LunaApp:

- **Tło:** Gradient niebieski
- **Logo:** Używa favicon.svg jako logo
- **Formularz:** Zaokrąglone krawędzie, cienie, nowoczesny design
- **Przyciski:** Gradient niebieski z animacjami hover
- **Responsywność:** Dostosowane do urządzeń mobilnych

## Pliki, które muszą istnieć w głównym katalogu

W katalogu głównym WordPress muszą znajdować się następujące pliki (już wgrane):

- `favicon-96x96.png`
- `favicon.svg`
- `favicon.ico`
- `apple-touch-icon.png`
- `site.webmanifest`
- `web-app-manifest-192x192.png`
- `web-app-manifest-512x512.png`

## Jak to działa

### Automatyczne włączanie/wyłączanie

- **Wtyczka AKTYWNA** → favicon i style logowania działają
- **Wtyczka NIEAKTYWNA** → favicon i style logowania NIE działają

### Gdzie działają style

- **Strona logowania:** `/wp-login.php`
- **Strona rejestracji:** `/wp-login.php?action=register`
- **Strona resetowania hasła:** `/wp-login.php?action=lostpassword`
- **Panel admina:** favicon
- **Frontend:** favicon (jeśli dostępny)

## Pliki wtyczki

### Nowe pliki dodane:

1. `/includes/services/class-wpmzf-branding-service.php` - główna klasa obsługująca branding
2. `/assets/css/login-styles.css` - style dla stron logowania

### Zmodyfikowane pliki:

1. `/moje-zarzadzanie-firma.php` - dodano ładowanie i inicjalizację nowego serwisu

## Testowanie

Aby przetestować funkcjonalność:

1. **Test favicon:** Odwiedź dowolną stronę i sprawdź czy w zakładce przeglądarki wyświetla się logo LunaApp
2. **Test stylów logowania:** Idź na `/wp-login.php` i sprawdź czy strona ma nowoczesny wygląd z gradientowym tłem
3. **Test wyłączenia:** Deaktywuj wtyczkę i sprawdź czy favicon i style znikają

## Obsługa problemów

Jeśli favicon nie działa:
1. Sprawdź czy wszystkie pliki favicon istnieją w głównym katalogu
2. Sprawdź czy wtyczka jest aktywna
3. Wyczyść cache przeglądarki (Ctrl+F5)

Jeśli style logowania nie działają:
1. Sprawdź czy plik `/assets/css/login-styles.css` istnieje
2. Sprawdź czy wtyczka jest aktywna
3. Sprawdź developer tools przeglądarki czy CSS się ładuje

## Personalizacja

Aby zmienić style logowania, edytuj plik:
`/wp-content/plugins/moje-zarzadzanie-firma/assets/css/login-styles.css`

Aby zmienić nazwę aplikacji w meta tagach, edytuj:
`/wp-content/plugins/moje-zarzadzanie-firma/includes/services/class-wpmzf-branding-service.php`
