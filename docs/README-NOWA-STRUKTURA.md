# Luna CRM - Struktura Wtyczki

## Opis

Wtyczka została przeorganizowana zgodnie z architekturą Luna CRM, która zapewnia modularność, skalowalność i łatwość rozwijania.

## Struktura Katalogów

```
moje-zarzadzanie-firma/
├── assets/
│   ├── css/
│   │   ├── admin-styles.css      # Główne style administracyjne
│   │   └── ui/
│   │       └── card.css          # Style komponentów UI
│   ├── js/
│   │   └── admin/
│   │       ├── contacts.js       # Zarządzanie kontaktami
│   │       ├── dashboard.js      # Funkcjonalność dashboardu
│   │       ├── person-view.js    # Widok pojedynczej osoby
│   │       └── time-tracking.js  # Śledzenie czasu pracy
│   └── images/                   # Zasoby graficzne
│
├── includes/
│   ├── abstracts/
│   │   └── class-wpmzf-abstract-cpt.php
│   │
│   ├── admin/
│   │   ├── class-wpmzf-admin.php          # Główna klasa administracyjna
│   │   ├── class-wpmzf-admin-columns.php
│   │   ├── class-wpmzf-admin-pages.php
│   │   ├── views/
│   │   │   ├── dashboard/
│   │   │   │   └── dashboard.php          # Widok głównego dashboardu
│   │   │   ├── companies/
│   │   │   │   └── companies.php          # Widok zarządzania firmami
│   │   │   ├── persons/
│   │   │   │   └── persons.php            # Widok zarządzania osobami
│   │   │   └── projects/
│   │   │       └── projects.php           # Widok zarządzania projektami
│   │   └── components/
│   │       ├── card/
│   │       │   └── simple-card.php        # Komponenty kart
│   │       ├── kanban/
│   │       │   └── kanban-board.php       # Tablica Kanban
│   │       ├── calendar/                  # Komponenty kalendarza
│   │       ├── timeline/                  # Komponenty osi czasu
│   │       ├── table/                     # Komponenty tabel
│   │       └── stats/                     # Komponenty statystyk
│   │
│   ├── core/
│   │   ├── class-wpmzf-loader.php         # Loader hooków i filtrów
│   │   └── class-wpmzf-activator.php      # Aktywator wtyczki
│   │
│   ├── data/
│   │   ├── class-wpmzf-post-types.php     # Typy treści (CPT)
│   │   └── class-wpmzf-taxonomies.php     # Taksonomie
│   │
│   ├── models/
│   │   ├── class-wpmzf-activity.php       # Model aktywności
│   │   ├── class-wpmzf-company.php        # Model firmy
│   │   ├── class-wpmzf-contact.php        # Model kontaktu
│   │   ├── class-wpmzf-person.php         # Model osoby
│   │   ├── class-wpmzf-project.php        # Model projektu
│   │   └── class-wpmzf-time-entry.php     # Model wpisu czasu
│   │
│   ├── integrations/
│   │   └── email/                         # Integracje email
│   │
│   ├── services/
│   │   ├── class-wpmzf-time-tracking.php  # Usługa śledzenia czasu
│   │   └── class-wpmzf-reports.php        # Usługa raportów
│   │
│   └── [legacy files]                     # Starsze pliki zachowane dla kompatybilności
│
└── moje-zarzadzanie-firma.php            # Główny plik wtyczki
```

## Główne Komponenty

### 1. Models (Modele)

Reprezentują główne encje systemu:

- **WPMZF_Company** - Zarządzanie danymi firm
- **WPMZF_Person** - Zarządzanie danymi osób
- **WPMZF_Project** - Zarządzanie projektami
- **WPMZF_Time_Entry** - Wpisy czasu pracy
- **WPMZF_Activity** - Aktywności i logi

### 2. Services (Usługi)

Logika biznesowa:

- **WPMZF_Time_Tracking** - Śledzenie czasu pracy, timery
- **WPMZF_Reports** - Generowanie raportów i eksport

### 3. Admin (Panel Administracyjny)

- **WPMZF_Admin** - Główna klasa zarządzająca panelem
- **Views** - Widoki stron administracyjnych
- **Components** - Komponenty interfejsu użytkownika

### 4. Core (Rdzeń)

- **WPMZF_Loader** - Zarządza rejestrację hooków i filtrów
- **WPMZF_Activator** - Obsługuje aktywację wtyczki

### 5. Data (Dane)

- **WPMZF_Post_Types** - Rejestracja Custom Post Types
- **WPMZF_Taxonomies** - Rejestracja taksonomii

## Funkcjonalności

### Dashboard
- Statystyki systemu
- Tracker czasu pracy
- Ostatnie aktywności
- Szybkie akcje

### Zarządzanie Kontaktami
- Firmy z pełnymi danymi
- Osoby powiązane z firmami
- Wielokrotne dane kontaktowe

### Śledzenie Czasu
- Timer w czasie rzeczywistym
- Rejestracja czasu pracy
- Raporty czasowe

### Projekty
- Zarządzanie projektami
- Powiązanie z firmami
- Statusy projektów

### Raporty
- Raporty czasu pracy
- Raporty projektów
- Eksport do CSV/PDF

## Instalacja i Konfiguracja

1. Wtyczka wymaga WordPress 5.0+
2. Zalecane jest zainstalowanie ACF Pro
3. Po aktywacji wtyczki, w menu pojawi się "Luna CRM"

## Rozszerzanie

Struktura pozwala na łatwe dodawanie:

- Nowych modeli w `includes/models/`
- Nowych usług w `includes/services/`
- Nowych widoków w `includes/admin/views/`
- Nowych komponentów w `includes/admin/components/`

## Uwagi Techniczne

- Wykorzystuje WordPress Custom Post Types
- Kompatybilne z ACF Pro
- Responsive design
- AJAX dla interaktywnych funkcji
- Hooks i filtry dla rozszerzeń

## Wsparcie

Wtyczka jest w fazie rozwoju. Funkcjonalności legacy są zachowane dla kompatybilności wstecznej.
