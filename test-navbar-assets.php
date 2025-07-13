<?php
/**
 * Test ładowania assetów navbara
 * URL: wp-admin/admin.php?page=wpmzf_test_navbar
 */

// Symulacja strony pluginu
global $current_screen;
$current_screen = (object) array('id' => 'toplevel_page_wpmzf_dashboard');

// Załaduj klasy
require_once(dirname(__FILE__) . '/includes/admin/components/class-wpmzf-view-helper.php');
require_once(dirname(__FILE__) . '/includes/admin/components/class-wpmzf-navbar.php');

// Renderuj navbar
WPMZF_View_Helper::render_navbar();

echo '<div style="margin: 20px; padding: 20px; background: #f1f1f1;">';
echo '<h2>Test navbara</h2>';
echo '<p>Navbar powinien być widoczny powyżej.</p>';
echo '<p>Sprawdź w inspektorze czy CSS i JS są załadowane.</p>';
echo '</div>';

// Pokaż załadowane skrypty i style
echo '<div style="margin: 20px; padding: 20px; background: #fff; border: 1px solid #ccc;">';
echo '<h3>Załadowane style:</h3>';
global $wp_styles;
if ($wp_styles) {
    foreach ($wp_styles->registered as $handle => $style) {
        if (strpos($handle, 'wpmzf') !== false) {
            echo '<p><strong>' . $handle . '</strong>: ' . $style->src . '</p>';
        }
    }
}

echo '<h3>Załadowane skrypty:</h3>';
global $wp_scripts;
if ($wp_scripts) {
    foreach ($wp_scripts->registered as $handle => $script) {
        if (strpos($handle, 'wpmzf') !== false) {
            echo '<p><strong>' . $handle . '</strong>: ' . $script->src . '</p>';
        }
    }
}
echo '</div>';
?>
