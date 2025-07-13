<?php

/**
 * Strona admina dla zarządzania wydajnością i debugowania
 *
 * @package WPMZF
 * @subpackage Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPMZF_Debug_Admin_Page {

    /**
     * Inicjalizuje stronę admina
     */
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_admin_page']);
        add_action('wp_ajax_wpmzf_clear_cache', [__CLASS__, 'clear_cache']);
        add_action('wp_ajax_wpmzf_reset_performance', [__CLASS__, 'reset_performance']);
        add_action('wp_ajax_wpmzf_export_debug_data', [__CLASS__, 'export_debug_data']);
    }

    /**
     * Dodaje stronę do menu admina
     */
    public static function add_admin_page() {
        add_submenu_page(
            'wpmzf_dashboard',
            'Debug & Wydajność',
            'Debug',
            'manage_options',
            'wpmzf_debug',
            [__CLASS__, 'render_debug_page']
        );
    }

    /**
     * Renderuje stronę debug
     */
    public static function render_debug_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Brak uprawnień');
        }

        // Pobierz dane wydajności
        $performance_stats = WPMZF_Performance_Monitor::get_performance_stats();
        $cache_info = WPMZF_Cache_Manager::get_cache_info();
        $error_stats = WPMZF_Error_Handler::get_error_stats();
        $recent_errors = array_slice(WPMZF_Error_Handler::get_errors(), 0, 10);

        ?>
        <div class="wrap">
            <h1>🔧 WPMZF - Debug & Wydajność</h1>
            
            <div class="wpmzf-debug-dashboard">
                <!-- Status Cards -->
                <div class="wpmzf-status-cards">
                    <div class="wpmzf-status-card performance">
                        <h3>📈 Wydajność</h3>
                        <div class="stats">
                            <p><strong>Operacje:</strong> <?php echo $performance_stats['total_operations']; ?></p>
                            <p><strong>Średni czas:</strong> <?php echo round($performance_stats['average_duration'], 3); ?>s</p>
                            <p><strong>Pamięć:</strong> <?php echo $performance_stats['current_memory']; ?></p>
                        </div>
                    </div>

                    <div class="wpmzf-status-card cache">
                        <h3>💾 Cache</h3>
                        <div class="stats">
                            <p><strong>Status:</strong> <?php echo $cache_info['cache_enabled'] ? '✅ Włączony' : '❌ Wyłączony'; ?></p>
                            <p><strong>Typ:</strong> <?php echo ucfirst($cache_info['cache_type']); ?></p>
                            <p><strong>Grupy:</strong> <?php echo count($cache_info['groups']); ?></p>
                        </div>
                    </div>

                    <div class="wpmzf-status-card errors">
                        <h3>⚠️ Błędy</h3>
                        <div class="stats">
                            <p><strong>Łącznie:</strong> <?php echo $error_stats['total_errors']; ?></p>
                            <p><strong>Ostatnia godzina:</strong> <?php echo $error_stats['recent_errors']; ?></p>
                            <p><strong>Tryb błędu:</strong> <?php echo WPMZF_Error_Handler::is_error_mode() ? '🚨 Aktywny' : '✅ Nieaktywny'; ?></p>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="wpmzf-actions">
                    <button type="button" class="button button-secondary" onclick="clearCache()">
                        🗑️ Wyczyść cache
                    </button>
                    <button type="button" class="button button-secondary" onclick="resetPerformance()">
                        🔄 Resetuj pomiary wydajności
                    </button>
                    <button type="button" class="button button-primary" onclick="exportDebugData()">
                        📥 Eksportuj dane debug
                    </button>
                    <?php if (WPMZF_Error_Handler::is_error_mode()): ?>
                    <button type="button" class="button button-primary" onclick="disableErrorMode()">
                        ✅ Wyłącz tryb błędu
                    </button>
                    <?php endif; ?>
                </div>

                <!-- Performance Details -->
                <div class="wpmzf-performance-details">
                    <h2>📊 Szczegóły wydajności</h2>
                    
                    <?php if ($performance_stats['total_operations'] > 0): ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Metric</th>
                                <th>Wartość</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Całkowity czas operacji</td>
                                <td><?php echo round($performance_stats['average_duration'] * $performance_stats['total_operations'], 3); ?>s</td>
                            </tr>
                            <tr>
                                <td>Najszybsza operacja</td>
                                <td><?php echo round($performance_stats['min_duration'], 3); ?>s</td>
                            </tr>
                            <tr>
                                <td>Najwolniejsza operacja</td>
                                <td><?php echo round($performance_stats['max_duration'], 3); ?>s</td>
                            </tr>
                            <tr>
                                <td>Peak memory</td>
                                <td><?php echo $performance_stats['peak_memory']; ?></td>
                            </tr>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <p>Brak danych wydajności do wyświetlenia.</p>
                    <?php endif; ?>
                </div>

                <!-- Recent Errors -->
                <?php if (!empty($recent_errors)): ?>
                <div class="wpmzf-recent-errors">
                    <h2>🚨 Ostatnie błędy</h2>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Czas</th>
                                <th>Typ</th>
                                <th>Wiadomość</th>
                                <th>Plik</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_errors as $error): ?>
                            <tr>
                                <td><?php echo date('Y-m-d H:i:s', $error['timestamp']); ?></td>
                                <td><span class="error-type"><?php echo esc_html($error['type']); ?></span></td>
                                <td><?php echo esc_html(substr($error['message'], 0, 100)); ?>...</td>
                                <td><?php echo esc_html(basename($error['file'])); ?>:<?php echo $error['line']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

                <!-- Cache Groups -->
                <div class="wpmzf-cache-groups">
                    <h2>💾 Grupy cache</h2>
                    <div class="cache-groups-grid">
                        <?php foreach ($cache_info['groups'] as $group_name => $group_key): ?>
                        <div class="cache-group">
                            <h4><?php echo ucfirst($group_name); ?></h4>
                            <p><code><?php echo $group_key; ?></code></p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- System Info -->
                <div class="wpmzf-system-info">
                    <h2>ℹ️ Informacje systemowe</h2>
                    <table class="wp-list-table widefat fixed striped">
                        <tbody>
                            <tr>
                                <td><strong>WordPress Version</strong></td>
                                <td><?php echo get_bloginfo('version'); ?></td>
                            </tr>
                            <tr>
                                <td><strong>PHP Version</strong></td>
                                <td><?php echo PHP_VERSION; ?></td>
                            </tr>
                            <tr>
                                <td><strong>Memory Limit</strong></td>
                                <td><?php echo ini_get('memory_limit'); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Max Execution Time</strong></td>
                                <td><?php echo ini_get('max_execution_time'); ?>s</td>
                            </tr>
                            <tr>
                                <td><strong>Upload Max Filesize</strong></td>
                                <td><?php echo ini_get('upload_max_filesize'); ?></td>
                            </tr>
                            <tr>
                                <td><strong>WP Debug</strong></td>
                                <td><?php echo WP_DEBUG ? '✅ Włączony' : '❌ Wyłączony'; ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <style>
        .wpmzf-debug-dashboard {
            max-width: 1200px;
        }
        
        .wpmzf-status-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .wpmzf-status-card {
            background: white;
            border: 1px solid #ccd0d4;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .wpmzf-status-card h3 {
            margin: 0 0 15px 0;
            font-size: 16px;
        }
        
        .wpmzf-status-card .stats p {
            margin: 8px 0;
            font-size: 14px;
        }
        
        .wpmzf-actions {
            margin-bottom: 30px;
        }
        
        .wpmzf-actions .button {
            margin-right: 10px;
            margin-bottom: 10px;
        }
        
        .wpmzf-performance-details,
        .wpmzf-recent-errors,
        .wpmzf-cache-groups,
        .wpmzf-system-info {
            background: white;
            border: 1px solid #ccd0d4;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .cache-groups-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .cache-group {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 6px;
            border-left: 4px solid #0073aa;
        }
        
        .cache-group h4 {
            margin: 0 0 8px 0;
        }
        
        .error-type {
            background: #dc3232;
            color: white;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 12px;
        }
        </style>

        <script>
        function clearCache() {
            if (confirm('Czy na pewno chcesz wyczyścić cache?')) {
                jQuery.post(ajaxurl, {
                    action: 'wpmzf_clear_cache',
                    nonce: '<?php echo wp_create_nonce('wpmzf_debug_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        alert('Cache został wyczyszczony!');
                        location.reload();
                    } else {
                        alert('Błąd: ' + response.data);
                    }
                });
            }
        }

        function resetPerformance() {
            if (confirm('Czy na pewno chcesz zresetować pomiary wydajności?')) {
                jQuery.post(ajaxurl, {
                    action: 'wpmzf_reset_performance',
                    nonce: '<?php echo wp_create_nonce('wpmzf_debug_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        alert('Pomiary wydajności zostały zresetowane!');
                        location.reload();
                    } else {
                        alert('Błąd: ' + response.data);
                    }
                });
            }
        }

        function exportDebugData() {
            window.open(ajaxurl + '?action=wpmzf_export_debug_data&nonce=<?php echo wp_create_nonce('wpmzf_debug_nonce'); ?>', '_blank');
        }

        function disableErrorMode() {
            if (confirm('Czy na pewno chcesz wyłączyć tryb błędu?')) {
                jQuery.post(ajaxurl, {
                    action: 'wpmzf_disable_error_mode',
                    nonce: '<?php echo wp_create_nonce('wpmzf_debug_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        alert('Tryb błędu został wyłączony!');
                        location.reload();
                    } else {
                        alert('Błąd: ' + response.data);
                    }
                });
            }
        }
        </script>
        <?php
    }

    /**
     * Czyści cache
     */
    public static function clear_cache() {
        if (!wp_verify_nonce($_POST['nonce'], 'wpmzf_debug_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error('Brak uprawnień');
            return;
        }

        WPMZF_Cache_Manager::flush_all();
        wp_send_json_success('Cache wyczyszczony');
    }

    /**
     * Resetuje pomiary wydajności
     */
    public static function reset_performance() {
        if (!wp_verify_nonce($_POST['nonce'], 'wpmzf_debug_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error('Brak uprawnień');
            return;
        }

        WPMZF_Performance_Monitor::reset_measurements();
        wp_send_json_success('Pomiary wydajności zresetowane');
    }

    /**
     * Eksportuje dane debug
     */
    public static function export_debug_data() {
        if (!wp_verify_nonce($_GET['nonce'], 'wpmzf_debug_nonce') || !current_user_can('manage_options')) {
            wp_die('Brak uprawnień');
        }

        $debug_data = [
            'timestamp' => time(),
            'site_url' => get_site_url(),
            'wp_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'plugin_version' => '1.0.0', // TODO: dodać wersję pluginu
            'performance_stats' => WPMZF_Performance_Monitor::get_performance_stats(),
            'cache_info' => WPMZF_Cache_Manager::get_cache_info(),
            'error_stats' => WPMZF_Error_Handler::get_error_stats(),
            'recent_errors' => array_slice(WPMZF_Error_Handler::get_errors(), 0, 50),
            'system_info' => [
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'wp_debug' => WP_DEBUG,
                'wp_debug_log' => WP_DEBUG_LOG,
            ]
        ];

        $filename = 'wpmzf-debug-' . date('Y-m-d-H-i-s') . '.json';
        
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
        
        echo json_encode($debug_data, JSON_PRETTY_PRINT);
        exit;
    }
}
