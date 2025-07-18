<?php
/**
 * Helper do renderowania nawigacji w widokach
 *
 * @package WPMZF
 * @subpackage Admin/Components
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPMZF_View_Helper {

    /**
     * Inicjalizuje klasę - wywołane jeden raz
     */
    public static function init() {
        // Navbar jest teraz obsługiwany przez WPMZF_Navbar_Component
        // Usunięto duplikację funkcjonalności
    }

    /**
     * Renderuje nawigację górną
     */
    public static function render_navbar() {
        error_log('WPMZF View Helper: render_navbar wywołane');
        // Używamy nowego komponentu navbar
        WPMZF_Navbar_Component::render_navbar();
        error_log('WPMZF View Helper: render_navbar zakończone');
    }

    /**
     * Renderuje kompletny header z navbarem
     */
    public static function render_complete_header($args = array()) {
        error_log('WPMZF View Helper: render_complete_header wywołane z parametrami: ' . print_r($args, true));
        ?>
        <div class="wpmzf-admin-wrapper">
            <?php self::render_navbar(); ?>
            <div class="wpmzf-main-content">
        <?php
    }

    /**
     * Renderuje footer
     */
    public static function render_footer() {
        ?>
            </div><!-- .wpmzf-main-content -->
        </div><!-- .wpmzf-admin-wrapper -->
        <?php
    }
}
