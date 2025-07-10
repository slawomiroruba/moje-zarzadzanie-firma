``` markdown
# Propozycja Struktury Luna CRM (PoC)

## Struktura katalogów

luna-crm/
├── assets/
│   ├── css/
│   │   └── admin-styles.css
│   │   └── ui
               card.cs
               
│   ├── js/
│   │   └── admin/
│   │       ├── dashboard.js
│   │       ├── time-tracking.js
│   │       └── contacts.js
│   └── images/
│
├── includes/
│   ├── abstracts/
│   ├── admin/
│   │   ├── class-wpmzf-admin.php
│   │   └── views/
│   │       ├── dashboard/
│   │       ├── companies/
│   │       ├── persons/
│   │       └── projects/
│   │   └── components/
│   │       └── card/
│   │           └──simple-card.php
│   │       └── canban/
│   │       └── calendar/
│   │       └── timeline/
│   │       └── table/
│   │       └── stats/
│   │
│   ├── core/
│   │   ├── class-wpmzf-loader.php
│   │   └── class-wpmzf-activator.php
│   │
│   ├── data/
│   │   ├── class-wpmzf-post-types.php
│   │   └── class-wpmzf-taxonomies.php
│   │
│   ├── models/
│   │   ├── class-wpmzf-company.php
│   │   ├── class-wpmzf-person.php
│   │   ├── class-wpmzf-project.php
│   │   ├── class-wpmzf-time-entry.php
│   │   └── class-wpmzf-activity.php
│   │
│   ├── integrations/
│   │   └── email/
│   │
│   └── services/
│       ├── class-wpmzf-time-tracking.php
│       └── class-wpmzf-reports.php
│
├── moje-zarzadzanie-firma.php


## Opis komponentów

### 1. Główne komponenty (`/includes`)

#### 1.1. Models
- Reprezentacja głównych encji systemu
- Każdy model zawiera logikę biznesową związaną z daną encją
- Obsługa relacji między encjami

#### 1.2. Services
- `time-tracking.php`: Usługa śledzenia czasu pracy
- `reports.php`: Generowanie raportów i zestawień

#### 1.3. Integrations
- `email/`: Integracja ze skrzynkami mailowymi
- W przyszłości możliwość dodania innych integracji

### 2. Panel administracyjny (`/includes/admin`)

#### 2.1. Widoki (`/views`)
- Dashboardy i raporty
- Zarządzanie firmami i kontaktami
- Śledzenie czasu pracy
- Zarządzanie projektami

### 3. Zasoby (`/assets`)

#### 3.1. JavaScript (`/js/admin`)
- Interaktywne komponenty dashboardu
- System śledzenia czasu
- Zarządzanie kontaktami

#### 3.2. Style (`/css`)
- Style panelu administracyjnego
- Komponenty UI

## Główne funkcjonalności PoC

1. **Zarządzanie kontaktami**
   - Firmy
   - Osoby
   - Wielokrotne dane kontaktowe

2. **Śledzenie czasu pracy**
   - Rejestracja czasu
   - Planowanie pracy
   - Raporty wykorzystania czasu

3. **Raporty i analizy**
   - Przegląd projektów
   - Wykorzystanie budżetu
   - Zestawienia czasowe

4. **Integracje mailowe**
   - Podłączenie skrzynek
   - Szablony wiadomości
   - Automatyzacja komunikacji

## Rozbudowa

Struktura pozwala na łatwe dodawanie:
- Nowych typów encji
- Dodatkowych integracji
- Rozszerzeń raportowania
- Własnych widoków

## Uwagi techniczne

- Wykorzystanie WordPress Custom Post Types
- ACF Pro do zarządzania polami
- Hooks i Filters do rozszerzania funkcjonalności
```
## Opis komponentów

### 1. Główne komponenty (`/includes`)

#### 1.1. Models
- Reprezentacja głównych encji systemu
- Każdy model zawiera logikę biznesową związaną z daną encją
- Obsługa relacji między encjami

#### 1.2. Services
- `time-tracking.php`: Usługa śledzenia czasu pracy
- `reports.php`: Generowanie raportów i zestawień

#### 1.3. Integrations
- `email/`: Integracja ze skrzynkami mailowymi
- W przyszłości możliwość dodania innych integracji

### 2. Panel administracyjny (`/includes/admin`)

#### 2.1. Widoki (`/views`)
- Dashboardy i raporty
- Zarządzanie firmami i kontaktami
- Śledzenie czasu pracy
- Zarządzanie projektami

### 3. Zasoby (`/assets`)

#### 3.1. JavaScript (`/js/admin`)
- Interaktywne komponenty dashboardu
- System śledzenia czasu
- Zarządzanie kontaktami

#### 3.2. Style (`/css`)
- Style panelu administracyjnego
- Komponenty UI

## Główne funkcjonalności PoC

1. **Zarządzanie kontaktami**
    - Firmy
    - Osoby
    - Wielokrotne dane kontaktowe

2. **Śledzenie czasu pracy**
    - Rejestracja czasu
    - Planowanie pracy
    - Raporty wykorzystania czasu

3. **Raporty i analizy**
    - Przegląd projektów
    - Wykorzystanie budżetu
    - Zestawienia czasowe

4. **Integracje mailowe**
    - Podłączenie skrzynek
    - Szablony wiadomości
    - Automatyzacja komunikacji

## Rozbudowa

Struktura pozwala na łatwe dodawanie:
- Nowych typów encji
- Dodatkowych integracji
- Rozszerzeń raportowania
- Własnych widoków

## Uwagi techniczne

- Wykorzystanie WordPress Custom Post Types
- ACF Pro do zarządzania polami
- Hooks i Filters do rozszerzania funkcjonalności