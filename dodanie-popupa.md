# Implementacja systemu popupów - plan działania

## 1. Przygotowanie struktury
- [ ] Stwórz katalog `includes/admin/components/popup/`
- [ ] Utwórz podkatalogi `assets/` dla CSS i JS
- [ ] Przygotuj podstawowe pliki:
    - `class-wpmzf-popup.php`
    - `assets/popup.css`
    - `assets/popup.js`

## 2. Implementacja klasy PHP
- [ ] Stwórz klasę `WPMZF_Popup`
- [ ] Dodaj metody:
    - inicjalizacji skryptów i styli
    - renderowania kontenera popupu
    - obsługi zdarzeń AJAX
- [ ] Zaimplementuj wzorzec Singleton
- [ ] Dodaj filtr WordPressa do modyfikacji opcji popupu

## 3. Style CSS
- [ ] Zaprojektuj podstawowe style:
    - kontener overlay
    - okno popupu
    - nagłówek
    - treść
    - przyciski

## 4. Skrypt JavaScript
- [ ] Napisz klasę zarządzającą popupami
- [ ] Zaimplementuj metody:
    - pokazywania popupu
    - ukrywania popupu
    - zarządzania zawartością
    - obsługi zdarzeń
- [ ] Dodaj obsługę klawiatury (ESC)
- [ ] Zapewnij wsparcie dla wielu instancji
- [ ] Zaimplementuj system callbacków

## 5. Integracja
- [ ] Dodaj rejestrację komponentu w głównym pliku pluginu
- [ ] Zintegruj z istniejącym systemem enqueue skryptów

## 6. Testy
- [ ] Przetestuj:
    - różne przeglądarki

## Klasy i interfejsy

### 1. Interfejsy
- `PopupInterface` - kontrakt dla popupów
- `RendererInterface` - kontrakt dla rendererów

### 2. Główne klasy
- `WPMZF_Popup` - abstrakcyjna klasa bazowa
- `WPMZF_Popup_Factory` - tworzenie popupów
- `WPMZF_Popup_Manager` - zarządzanie popupami
- `WPMZF_Popup_Renderer` - renderowanie HTML

### 3. Typy popupów
- `WPMZF_Basic_Popup` - prosty popup
- `WPMZF_Modal_Popup` - modal z przyciskami
- `WPMZF_Alert_Popup` - alert systemowy

### 4. Traity
- `Popup_Validator` - metody walidacji

## Skrypty i style

### 1. JavaScript
- Moduły ES6
- System eventów
- Manager animacji
- Obsługa klawiatury

### 2. CSS
- Style

## Konfiguracja
- Opcje domyślne
- Filtry WordPressa
- Customizacja przez użytkownika

```
includes/admin/components/popup/
├── class-wpmzf-popup.php           # Główna klasa popupu
├── class-wpmzf-popup-factory.php   # Fabryka popupów
├── class-wpmzf-popup-renderer.php  # Renderer HTML
├── class-wpmzf-popup-manager.php   # Manager popupów (Singleton)
├── interfaces/
│   ├── popup-interface.php         # Interfejs popupu
│   └── renderer-interface.php      # Interfejs renderera
├── types/
│   ├── class-wpmzf-basic-popup.php # Podstawowy popup
│   ├── class-wpmzf-modal-popup.php # Modal popup
│   └── class-wpmzf-alert-popup.php # Alert popup
├── traits/
│   ├── popup-validator.php         # Walidacja danych
├── assets/
│   ├── css/
│   │   ├── popup.css               # Główne style
│   └── js/
│       ├── popup.js                # Główny skrypt
│       ├── popup-manager.js        # Manager popupów
│       └── modules/                # Moduły JS
│           ├── events.js
│           └── keyboard.js
├── templates/                      # Szablony HTML
│   ├── basic.php
│   ├── modal.php
│   └── alert.php
└── config/                         # Konfiguracja
└── popup-config.php            # Domyślne ustawienia
```