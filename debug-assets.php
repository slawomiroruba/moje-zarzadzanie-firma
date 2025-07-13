<?php
// Debug test dla ścieżek assetów
echo "=== DEBUG ŚCIEŻEK ASSETÓW NAVBARA ===\n";

// Symulujemy ścieżki
$current_file = __FILE__;
echo "Aktualny plik: " . $current_file . "\n";

$plugin_path = dirname($current_file);
echo "Plugin path: " . $plugin_path . "\n";

$css_path = $plugin_path . '/assets/css/navbar.css';
$js_path = $plugin_path . '/assets/js/admin/navbar.js';

echo "CSS path: " . $css_path . "\n";
echo "JS path: " . $js_path . "\n";

echo "CSS istnieje: " . (file_exists($css_path) ? 'TAK' : 'NIE') . "\n";
echo "JS istnieje: " . (file_exists($js_path) ? 'TAK' : 'NIE') . "\n";

if (file_exists($css_path)) {
    echo "CSS timestamp: " . filemtime($css_path) . "\n";
}

if (file_exists($js_path)) {
    echo "JS timestamp: " . filemtime($js_path) . "\n";
}

// Sprawdź jak by wyglądały URL-e
if (function_exists('plugin_dir_url')) {
    $plugin_url = plugin_dir_url($current_file);
    echo "Plugin URL: " . $plugin_url . "\n";
    echo "CSS URL: " . $plugin_url . 'assets/css/navbar.css' . "\n";
    echo "JS URL: " . $plugin_url . 'assets/js/admin/navbar.js' . "\n";
} else {
    echo "Funkcja plugin_dir_url nie jest dostępna (nie w środowisku WordPress)\n";
}
?>
