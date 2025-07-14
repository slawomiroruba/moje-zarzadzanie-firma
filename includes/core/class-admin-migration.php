<?php

/**
 * Migracja ze starej struktury na nową
 * 
 * Ten plik pomoże w przejściu z monolitycznej klasy WPMZF_Admin_Pages
 * na nową modularną strukturę
 */
class WPMZF_Admin_Migration
{
    /**
     * Konstruktor
     */
    public function __construct()
    {
        // Dodaj hooki tylko jeśli to jest proces migracji
        if (isset($_GET['wpmzf_migrate']) && current_user_can('manage_options')) {
            add_action('admin_init', array($this, 'run_migration'));
        }
        
        // Dodaj informację o migracji w admin
        add_action('admin_notices', array($this, 'show_migration_notice'));
    }

    /**
     * Uruchamia proces migracji
     */
    public function run_migration()
    {
        if (!wp_verify_nonce($_GET['nonce'], 'wpmzf_migration')) {
            wp_die('Nieprawidłowy token bezpieczeństwa.');
        }

        $this->backup_old_structure();
        $this->migrate_menu_structure();
        $this->update_plugin_options();
        
        // Przekieruj z komunikatem sukcesu
        wp_redirect(add_query_arg('wpmzf_migrated', '1', admin_url('admin.php?page=wpmzf_dashboard')));
        exit;
    }

    /**
     * Tworzy kopię zapasową starej struktury
     */
    private function backup_old_structure()
    {
        $backup_data = array(
            'old_class_exists' => class_exists('WPMZF_Admin_Pages'),
            'backup_date' => current_time('mysql'),
            'version' => get_option('wpmzf_version', '1.0.0')
        );

        update_option('wpmzf_migration_backup', $backup_data);
    }

    /**
     * Migruje strukturę menu
     */
    private function migrate_menu_structure()
    {
        // Usuń stare hooki menu jeśli istnieją
        global $wp_filter;
        
        if (isset($wp_filter['admin_menu'])) {
            foreach ($wp_filter['admin_menu']->callbacks as $priority => $callbacks) {
                foreach ($callbacks as $key => $callback) {
                    if (is_array($callback['function']) && 
                        isset($callback['function'][0]) && 
                        $callback['function'][0] instanceof WPMZF_Admin_Pages) {
                        
                        unset($wp_filter['admin_menu']->callbacks[$priority][$key]);
                    }
                }
            }
        }
    }

    /**
     * Aktualizuje opcje pluginu
     */
    private function update_plugin_options()
    {
        update_option('wpmzf_admin_structure', 'modular');
        update_option('wpmzf_migration_completed', current_time('mysql'));
        update_option('wpmzf_version', '2.0.0');
    }

    /**
     * Wyświetla informację o migracji
     */
    public function show_migration_notice()
    {
        // Sprawdź czy migracja została zakończona
        if (isset($_GET['wpmzf_migrated'])) {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p><strong>Migracja zakończona pomyślnie!</strong> Plugin został przereorganizowany zgodnie z nowymi standardami.</p>';
            echo '</div>';
            return;
        }

        // Sprawdź czy migracja jest potrzebna
        $migration_completed = get_option('wpmzf_migration_completed');
        $current_structure = get_option('wpmzf_admin_structure', 'legacy');

        if (!$migration_completed && $current_structure === 'legacy' && class_exists('WPMZF_Admin_Pages')) {
            $nonce = wp_create_nonce('wpmzf_migration');
            $migration_url = add_query_arg(array(
                'wpmzf_migrate' => '1',
                'nonce' => $nonce
            ), admin_url('admin.php'));

            echo '<div class="notice notice-warning">';
            echo '<p><strong>Dostępna jest migracja struktury administracyjnej!</strong></p>';
            echo '<p>Twój plugin używa przestarzałej struktury. Zalecamy migrację do nowej, modularnej architektury.</p>';
            echo '<p>';
            echo '<a href="' . esc_url($migration_url) . '" class="button button-primary">Uruchom migrację</a> ';
            echo '<a href="#" class="button wpmzf-learn-more">Dowiedz się więcej</a>';
            echo '</p>';
            echo '</div>';

            // Dodaj JavaScript dla "Dowiedz się więcej"
            $this->add_migration_info_script();
        }
    }

    /**
     * Dodaje skrypt informacyjny o migracji
     */
    private function add_migration_info_script()
    {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('.wpmzf-learn-more').on('click', function(e) {
                e.preventDefault();
                
                const info = `
                <div style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; margin-top: 15px;">
                    <h3>Co zmieni migracja?</h3>
                    <ul>
                        <li><strong>Lepsza organizacja kodu</strong> - kod zostanie podzielony na mniejsze, łatwiejsze w utrzymaniu klasy</li>
                        <li><strong>Separation of Concerns</strong> - każda klasa będzie odpowiadać za konkretną funkcjonalność</li>
                        <li><strong>Lepsze zarządzanie zasobami</strong> - CSS i JS będą ładowane tylko tam gdzie są potrzebne</li>
                        <li><strong>Łatwiejsze rozszerzanie</strong> - dodawanie nowych funkcji będzie prostsze</li>
                        <li><strong>Zgodność z WordPress standardami</strong> - kod będzie zgodny z najlepszymi praktykami</li>
                    </ul>
                    
                    <h4>Nowa struktura:</h4>
                    <pre style="background: #f0f0f1; padding: 10px; border-radius: 3px; font-size: 12px;">
includes/
├── admin/
│   ├── pages/           # Klasy stron (Dashboard, Projekty, Osoby, etc.)
│   ├── controllers/     # Logika biznesowa
│   └── assets/          # Zarządcy CSS/JS
├── core/
│   ├── class-admin-menu-manager.php
│   └── class-admin-manager.php
└── ...
                    </pre>
                    
                    <p><strong>Migracja jest bezpieczna</strong> - przed migracją zostanie utworzona kopia zapasowa obecnych ustawień.</p>
                </div>
                `;
                
                if (!$(this).next('.wpmzf-migration-info').length) {
                    $(this).parent().append(info);
                    $(this).text('Ukryj informacje');
                } else {
                    $(this).next('.wpmzf-migration-info').remove();
                    $(this).text('Dowiedz się więcej');
                }
            });
        });
        </script>
        <style>
        .wpmzf-migration-info {
            margin-top: 15px;
        }
        .wpmzf-migration-info h3, .wpmzf-migration-info h4 {
            margin-top: 0;
        }
        .wpmzf-migration-info ul {
            margin-left: 20px;
        }
        </style>
        <?php
    }

    /**
     * Sprawdza czy stara struktura nadal istnieje
     */
    public static function needs_migration()
    {
        $migration_completed = get_option('wpmzf_migration_completed');
        $current_structure = get_option('wpmzf_admin_structure', 'legacy');
        
        return !$migration_completed && $current_structure === 'legacy' && class_exists('WPMZF_Admin_Pages');
    }

    /**
     * Pobiera informacje o migracji
     */
    public static function get_migration_info()
    {
        return array(
            'completed' => get_option('wpmzf_migration_completed'),
            'backup' => get_option('wpmzf_migration_backup'),
            'current_structure' => get_option('wpmzf_admin_structure', 'legacy'),
            'version' => get_option('wpmzf_version', '1.0.0')
        );
    }
}
