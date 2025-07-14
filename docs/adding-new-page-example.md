# Przykład: Dodawanie nowej strony administracyjnej

## Krok 1: Stwórz klasę strony

Utwórz plik `includes/admin/pages/class-reports-page.php`:

```php
<?php

/**
 * Strona raportów
 */
class WPMZF_Reports_Page extends WPMZF_Admin_Page_Base
{
    /**
     * Inicjalizacja strony raportów
     */
    protected function init()
    {
        $this->page_slug = 'wpmzf_reports';
        $this->page_title = 'Raporty';
        $this->menu_title = 'Raporty';
        $this->capability = 'manage_options';
    }

    /**
     * Renderowanie strony raportów
     */
    public function render()
    {
        echo '<div class="wrap wpmzf-reports-page">';
        echo '<h1>' . esc_html(get_admin_page_title()) . '</h1>';
        
        $this->render_filters();
        $this->render_charts();
        $this->render_tables();
        
        echo '</div>';
    }

    /**
     * Renderuje filtry
     */
    private function render_filters()
    {
        echo '<div class="reports-filters">';
        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="' . $this->page_slug . '">';
        
        // Filtr daty
        echo '<label>Od: <input type="date" name="date_from" value="' . esc_attr($_GET['date_from'] ?? '') . '"></label>';
        echo '<label>Do: <input type="date" name="date_to" value="' . esc_attr($_GET['date_to'] ?? '') . '"></label>';
        
        // Filtr typu raportu
        echo '<select name="report_type">';
        echo '<option value="">Wszystkie raporty</option>';
        echo '<option value="projects"' . selected($_GET['report_type'] ?? '', 'projects', false) . '>Projekty</option>';
        echo '<option value="time"' . selected($_GET['report_type'] ?? '', 'time', false) . '>Czas pracy</option>';
        echo '</select>';
        
        echo '<button type="submit" class="button">Filtruj</button>';
        echo '</form>';
        echo '</div>';
    }

    /**
     * Renderuje wykresy
     */
    private function render_charts()
    {
        echo '<div class="reports-charts">';
        echo '<canvas id="reportsChart" width="400" height="200"></canvas>';
        echo '</div>';
    }

    /**
     * Renderuje tabele danych
     */
    private function render_tables()
    {
        echo '<div class="reports-tables">';
        // Tutaj logika generowania tabel
        echo '</div>';
    }

    /**
     * Ładowanie stylów specyficznych dla raportów
     */
    protected function enqueue_styles()
    {
        parent::enqueue_styles();
        
        wp_enqueue_style(
            'wpmzf-reports',
            $this->get_asset_url('css/admin/reports.css'),
            array('wpmzf-admin-base'),
            '1.0.0'
        );
    }

    /**
     * Ładowanie skryptów specyficznych dla raportów
     */
    protected function enqueue_scripts()
    {
        parent::enqueue_scripts();
        
        // Chart.js dla wykresów
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js',
            array(),
            '3.9.1',
            true
        );
        
        wp_enqueue_script(
            'wpmzf-reports',
            $this->get_asset_url('js/admin/reports.js'),
            array('jquery', 'chartjs'),
            '1.0.0',
            true
        );
        
        // Zmienne dla JavaScript
        wp_localize_script('wpmzf-reports', 'wpmzfReports', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpmzf_reports_nonce'),
            'dateFrom' => $_GET['date_from'] ?? '',
            'dateTo' => $_GET['date_to'] ?? '',
            'reportType' => $_GET['report_type'] ?? ''
        ));
    }
}
```

## Krok 2: Utwórz pliki zasobów

### CSS - `assets/css/admin/reports.css`:

```css
.wpmzf-reports-page {
    .reports-filters {
        background: #fff;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
        padding: 15px;
        margin-bottom: 20px;
        
        form {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        label {
            display: flex;
            flex-direction: column;
            gap: 5px;
            font-weight: 600;
            
            input, select {
                min-width: 150px;
            }
        }
    }
    
    .reports-charts {
        background: #fff;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
        padding: 20px;
        margin-bottom: 20px;
        text-align: center;
    }
    
    .reports-tables {
        background: #fff;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
        padding: 20px;
        
        table {
            width: 100%;
            border-collapse: collapse;
            
            th, td {
                padding: 10px;
                text-align: left;
                border-bottom: 1px solid #f0f0f1;
            }
            
            th {
                background: #f9f9f9;
                font-weight: 600;
            }
        }
    }
}

@media (max-width: 768px) {
    .wpmzf-reports-page {
        .reports-filters form {
            flex-direction: column;
            align-items: flex-start;
        }
    }
}
```

### JavaScript - `assets/js/admin/reports.js`:

```javascript
(function($) {
    'use strict';

    if (typeof wpmzfReports === 'undefined') {
        return;
    }

    const Reports = {
        
        init: function() {
            this.initChart();
            this.bindEvents();
            this.loadData();
        },

        initChart: function() {
            const ctx = document.getElementById('reportsChart');
            if (!ctx) return;

            this.chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Statystyki',
                        data: [],
                        borderColor: 'rgb(75, 192, 192)',
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Raport aktywności'
                        }
                    }
                }
            });
        },

        bindEvents: function() {
            // Auto-refresh przy zmianie filtrów
            $('.reports-filters select, .reports-filters input').on('change', function() {
                Reports.loadData();
            });
            
            // Export do PDF
            $(document).on('click', '.export-pdf', this.exportToPDF);
            
            // Export do Excel
            $(document).on('click', '.export-excel', this.exportToExcel);
        },

        loadData: function() {
            $.ajax({
                url: wpmzfReports.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpmzf_get_reports_data',
                    nonce: wpmzfReports.nonce,
                    date_from: wpmzfReports.dateFrom,
                    date_to: wpmzfReports.dateTo,
                    report_type: wpmzfReports.reportType
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Reports.updateChart(response.data.chart);
                        Reports.updateTables(response.data.tables);
                    }
                }
            });
        },

        updateChart: function(data) {
            if (this.chart && data) {
                this.chart.data.labels = data.labels;
                this.chart.data.datasets[0].data = data.values;
                this.chart.update();
            }
        },

        updateTables: function(tables) {
            $('.reports-tables').html(tables);
        },

        exportToPDF: function(e) {
            e.preventDefault();
            
            $.ajax({
                url: wpmzfReports.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpmzf_export_reports_pdf',
                    nonce: wpmzfReports.nonce,
                    filters: Reports.getFilters()
                },
                success: function(response) {
                    if (response.success) {
                        window.open(response.data.url, '_blank');
                    }
                }
            });
        },

        exportToExcel: function(e) {
            e.preventDefault();
            
            $.ajax({
                url: wpmzfReports.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpmzf_export_reports_excel',
                    nonce: wpmzfReports.nonce,
                    filters: Reports.getFilters()
                },
                success: function(response) {
                    if (response.success) {
                        window.open(response.data.url, '_blank');
                    }
                }
            });
        },

        getFilters: function() {
            return {
                date_from: wpmzfReports.dateFrom,
                date_to: wpmzfReports.dateTo,
                report_type: wpmzfReports.reportType
            };
        }
    };

    $(document).ready(function() {
        if ($('.wpmzf-reports-page').length) {
            Reports.init();
        }
    });

})(jQuery);
```

## Krok 3: Zarejestruj stronę

Dodaj do `class-admin-menu-manager.php`:

```php
private function register_pages()
{
    // ...existing pages...
    
    // Dodaj nową stronę raportów
    $this->pages['reports'] = new WPMZF_Reports_Page();
}

public function add_admin_menu()
{
    // ...existing menu items...
    
    // Dodaj podmenu - Raporty
    $reports_page = $this->pages['reports'];
    $hook_suffix = add_submenu_page(
        $main_page->get_page_slug(),
        $reports_page->get_page_title(),
        $reports_page->get_menu_title(),
        $reports_page->get_capability(),
        $reports_page->get_page_slug(),
        array($reports_page, 'render')
    );
    $reports_page->set_hook_suffix($hook_suffix);
}
```

## Krok 4: Dodaj ładowanie klasy

W `class-admin-menu-manager.php`:

```php
private function load_page_classes()
{
    $page_files = array(
        'class-admin-page-base.php',
        'class-dashboard-page.php',
        'class-persons-page.php',
        'class-companies-page.php',
        'class-projects-page.php',
        'class-documents-page.php',
        'class-reports-page.php'  // Dodaj nową klasę
    );

    // ...rest of method...
}
```

## Krok 5: Dodaj obsługę AJAX (opcjonalne)

W `includes/services/class-wpmzf-ajax-handler.php`:

```php
public function __construct()
{
    // ...existing actions...
    
    add_action('wp_ajax_wpmzf_get_reports_data', array($this, 'get_reports_data'));
    add_action('wp_ajax_wpmzf_export_reports_pdf', array($this, 'export_reports_pdf'));
    add_action('wp_ajax_wpmzf_export_reports_excel', array($this, 'export_reports_excel'));
}

public function get_reports_data()
{
    check_ajax_referer('wpmzf_reports_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    $date_from = sanitize_text_field($_POST['date_from']);
    $date_to = sanitize_text_field($_POST['date_to']);
    $report_type = sanitize_text_field($_POST['report_type']);
    
    // Logika generowania danych raportu
    $data = array(
        'chart' => array(
            'labels' => array('Styczeń', 'Luty', 'Marzec'),
            'values' => array(10, 20, 30)
        ),
        'tables' => '<table>...</table>'
    );
    
    wp_send_json_success($data);
}
```

## Korzyści tej struktury

1. **Modularity** - Każda strona w osobnym pliku
2. **Reusability** - Wspólna funkcjonalność w klasie bazowej
3. **Performance** - Zasoby ładowane tylko gdzie potrzebne
4. **Maintainability** - Łatwe w utrzymaniu i rozszerzaniu
5. **Testing** - Każda klasa może być testowana osobno

## Dodawanie kolejnych stron

Aby dodać kolejną stronę, wystarczy:

1. Utworzyć klasę dziedziczącą po `WPMZF_Admin_Page_Base`
2. Dodać pliki CSS/JS w odpowiednich katalogach
3. Zarejestrować w `WPMZF_Admin_Menu_Manager`

To wszystko! Nowa strona automatycznie działa z całym ekosystemem.
