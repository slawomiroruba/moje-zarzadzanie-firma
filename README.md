# Luna CRM - Moje Zarządzanie Firmą

## Opis

Plugin WordPress do zarządzania firmą i relacjami z klientami (CRM). Zrefaktoryzowany do modularnej struktury Luna CRM zgodnie z najlepszymi praktykami rozwoju WordPressa.

## Struktura Projektu

### Główne Katalogi

```
luna-crm/
├── assets/                     # Zasoby statyczne
│   ├── css/
│   │   ├── admin-styles.css   # Style panelu administracyjnego
│   │   └── ui/
│   │       └── card.css       # Style komponentów UI
│   ├── js/
│   │   └── admin/             # Skrypty JavaScript dla panelu admin
│   │       ├── dashboard.js
│   │       ├── time-tracking.js
│   │       ├── contacts.js
│   │       ├── companies.js
│   │       └── projects.js
│   └── images/                # Obrazy i ikony
│
├── includes/                  # Kod źródłowy PHP
│   ├── admin/                 # Panel administracyjny
│   │   ├── class-wpmzf-admin.php
│   │   ├── class-wpmzf-admin-pages.php
│   │   ├── class-wpmzf-admin-columns.php
│   │   ├── class-wpmzf-custom-columns.php
│   │   ├── class-wpmzf-meta-boxes.php
│   │   ├── views/             # Widoki panelu administracyjnego
│   │   │   ├── dashboard/
│   │   │   ├── companies/
│   │   │   ├── persons/
│   │   │   └── projects/
│   │   └── components/        # Komponenty UI
│   │       ├── card/
│   │       ├── kanban/
│   │       ├── table/
│   │       ├── calendar/      # (przygotowany)
│   │       ├── timeline/      # (przygotowany)
│   │       └── stats/         # (przygotowany)
│   │
│   ├── core/                  # Logika podstawowa
│   │   ├── class-wpmzf-loader.php
│   │   ├── class-wpmzf-activator.php
│   │   └── class-wpmzf-access-control.php
│   │
│   ├── data/                  # Warstwa danych
│   │   ├── class-wpmzf-post-types.php
│   │   ├── class-wpmzf-taxonomies.php
│   │   └── class-wpmzf-acf-fields.php
│   │
│   ├── models/                # Modele biznesowe
│   │   ├── class-wpmzf-company.php
│   │   ├── class-wpmzf-person.php
│   │   ├── class-wpmzf-project.php
│   │   ├── class-wpmzf-time-entry.php
│   │   └── class-wpmzf-activity.php
│   │
│   └── services/              # Usługi biznesowe
│       ├── class-wpmzf-time-tracking.php
│       ├── class-wpmzf-reports.php
│       ├── class-wpmzf-contact-helper.php
│       └── class-wpmzf-ajax-handler.php
│
├── docs/                      # Dokumentacja
│   ├── CHANGELOG-KONTAKTY.md
│   ├── KONTAKTY-INSTRUKCJA.md
│   ├── Plan na plugin.md
│   ├── Propozycja Struktury.md
│   └── README-NOWA-STRUKTURA.md
│
├── examples/                  # Przykłady kodu
│   └── contact-examples.php
│
├── moje-zarzadzanie-firma.php # Główny plik pluginu
└── readme.txt                # Opis pluginu dla WordPress
```

## Funkcjonalności

### 1. Zarządzanie Kontaktami
- **Firmy**: Pełne dane firm z możliwością wielokrotnych kontaktów
- **Osoby**: Zarządzanie kontaktami osobowymi z wieloma e-mailami i telefonami
- **Relacje**: Połączenia między osobami a firmami

### 2. Śledzenie Czasu Pracy
- **Rejestracja czasu**: Dokładne śledzenie czasu pracy nad projektami
- **Raporty**: Szczegółowe zestawienia wykorzystania czasu
- **Planowanie**: Możliwość planowania przyszłych zadań

### 3. Zarządzanie Projektami
- **Projekty**: Tworzenie i zarządzanie projektami
- **Zadania**: Podział projektów na mniejsze zadania
- **Śledzenie postępu**: Monitorowanie realizacji projektów

### 4. Raporty i Analizy
- **Przegląd projektów**: Kompleksowe raporty z projektów
- **Wykorzystanie budżetu**: Analizy finansowe
- **Zestawienia czasowe**: Raporty wykorzystania czasu

## Instalacja

1. Skopiuj folder pluginu do katalogu `/wp-content/plugins/`
2. Aktywuj plugin w panelu administracyjnym WordPress
3. Skonfiguruj pola ACF (Advanced Custom Fields) - plugin wymaga ACF Pro

## Wymagania

- WordPress 5.0+
- PHP 7.4+
- Advanced Custom Fields Pro

## Architektura

Plugin wykorzystuje modularną architekturę opartą na:
- **Custom Post Types** (CPT) do przechowywania danych
- **Advanced Custom Fields** (ACF) do zarządzania polami
- **Hooks i Filters** do rozszerzania funkcjonalności
- **AJAX** do interaktywnych interfejsów

## Rozwój

### Dodawanie Nowych Funkcjonalności

1. **Nowe CPT**: Dodaj w `includes/data/class-wpmzf-post-types.php`
2. **Nowe modele**: Utwórz w `includes/models/`
3. **Nowe usługi**: Dodaj w `includes/services/`
4. **Nowe widoki**: Utwórz w `includes/admin/views/`
5. **Nowe komponenty**: Dodaj w `includes/admin/components/`

### Struktura Kodu

- Wszystkie klasy używają prefiksu `WPMZF_`
- Kod jest zgodny z WordPress Coding Standards
- Używamy hooks i filters do rozszerzania funkcjonalności
- Separacja logiki biznesowej od prezentacji

## Licencja

GNU General Public License v2.0

## Autorzy

Agencja Luna - Systemy CRM i zarządzanie firmą

## Wsparcie

Dokumentacja techniczna znajduje się w katalogu `docs/`.
