<?php
/**
 * Uniwersalny szablon widoku dla wszystkich typów wpisów
 * 
 * Ten plik jest używany przez wszystkie typy wpisów w systemie.
 * Konfiguracja każdego typu określa jakie sekcje i komponenty są wyświetlane.
 * 
 * @package WPMZF
 * @subpackage Admin/Views/Universal
 * @version 1.0.0
 * 
 * Dostępne zmienne:
 * @var int    $object_id     ID obiektu
 * @var string $object_title  Tytuł obiektu  
 * @var array  $object_fields Pola ACF obiektu
 * @var string $post_type     Typ wpisu
 * @var array  $config        Konfiguracja widoku
 */

if (!defined('ABSPATH')) {
    exit;
}

// Ustaw meta_key dla relacji na podstawie typu wpisu
$config['meta_key'] = $post_type;
?>

<style>
    /* Uniwersalne style dla wszystkich widoków */
    .universal-view-grid {
        display: grid;
        grid-template-columns: 320px 1fr 360px;
        gap: 24px;
        margin-top: 0;
    }

    .universal-left-column,
    .universal-center-column, 
    .universal-right-column {
        display: flex;
        flex-direction: column;
        gap: 24px;
    }

    .universal-section {
        background: #fff;
        border: 1px solid #e1e5e9;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        transition: all 0.2s ease;
    }

    .universal-section:hover {
        border-color: #d0d5dd;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    }

    .universal-section-title {
        font-size: 14px;
        font-weight: 600;
        padding: 16px 20px;
        margin: 0;
        border-bottom: 1px solid #e1e5e9;
        background: #f8f9fa;
        border-radius: 8px 8px 0 0;
        color: #1d2327;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .universal-section-content {
        padding: 20px;
    }

    .universal-field-group {
        margin-bottom: 16px;
    }

    .universal-field-group:last-child {
        margin-bottom: 0;
    }

    .universal-field-label {
        font-weight: 600;
        color: #1d2327;
        margin-bottom: 4px;
        display: block;
    }

    .universal-field-value {
        color: #646970;
        line-height: 1.5;
    }

    .universal-field-value a {
        color: #2271b1;
        text-decoration: none;
    }

    .universal-field-value a:hover {
        text-decoration: underline;
    }

    .universal-empty-state {
        text-align: center;
        padding: 40px 20px;
        color: #646970;
    }

    .universal-empty-state .dashicons {
        font-size: 48px;
        margin-bottom: 16px;
        color: #c3c4c7;
    }

    /* Responsywność */
    @media (max-width: 1200px) {
        .universal-view-grid {
            grid-template-columns: 280px 1fr 300px;
            gap: 16px;
        }
    }

    @media (max-width: 960px) {
        .universal-view-grid {
            grid-template-columns: 1fr;
            gap: 16px;
        }
    }
</style>

<div class="wrap">
    <div class="universal-view-grid">
        
        <!-- Lewa kolumna -->
        <div class="universal-left-column">
            <?php 
            // Renderuj sekcje lewej kolumny
            foreach ($config['sections'] as $section_key => $section) {
                if ($section['position'] === 'left') {
                    WPMZF_Universal_View_Renderer::render_section(
                        $section_key, 
                        $section, 
                        $object_id, 
                        $object_title, 
                        $object_fields, 
                        $post_type, 
                        $config
                    );
                }
            }
            ?>
        </div>

        <!-- Środkowa kolumna -->  
        <div class="universal-center-column">
            <?php
            // Renderuj sekcje środkowej kolumny
            foreach ($config['sections'] as $section_key => $section) {
                if ($section['position'] === 'center') {
                    WPMZF_Universal_View_Renderer::render_section(
                        $section_key,
                        $section,
                        $object_id,
                        $object_title, 
                        $object_fields,
                        $post_type,
                        $config
                    );
                }
            }
            ?>
        </div>

        <!-- Prawa kolumna -->
        <div class="universal-right-column">
            <?php
            // Renderuj sekcje prawej kolumny
            foreach ($config['sections'] as $section_key => $section) {
                if ($section['position'] === 'right') {
                    WPMZF_Universal_View_Renderer::render_section(
                        $section_key,
                        $section,
                        $object_id,
                        $object_title,
                        $object_fields, 
                        $post_type,
                        $config
                    );
                }
            }
            ?>
        </div>

    </div>
</div>

<!-- Uniwersalne pola ukryte dla JavaScript -->
<input type="hidden" name="<?php echo $config['param_name']; ?>" value="<?php echo esc_attr($object_id); ?>" />
<input type="hidden" id="wpmzf_universal_security" value="<?php echo wp_create_nonce('wpmzf_universal_view_nonce'); ?>" />
<input type="hidden" id="wpmzf_task_security" value="<?php echo wp_create_nonce('wpmzf_task_nonce'); ?>" />
<input type="hidden" id="wpmzf_post_type" value="<?php echo esc_attr($post_type); ?>" />
