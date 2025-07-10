<?php
/**
 * Komponent prostej karty
 *
 * @package WPMZF
 * @subpackage Admin/Components
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Renderuje prostą kartę
 *
 * @param string $title Tytuł karty
 * @param string $content Treść karty
 * @param array $args Dodatkowe argumenty
 */
function wpmzf_render_simple_card($title, $content, $args = array()) {
    $defaults = array(
        'class' => 'luna-crm-simple-card',
        'icon' => '',
        'actions' => array(),
        'footer' => ''
    );
    
    $args = wp_parse_args($args, $defaults);
    
    $class = esc_attr($args['class']);
    $icon = $args['icon'] ? '<span class="dashicons dashicons-' . esc_attr($args['icon']) . '"></span>' : '';
    
    echo '<div class="' . $class . '">';
    
    if ($title) {
        echo '<div class="card-header">';
        echo '<h3>' . $icon . ' ' . esc_html($title) . '</h3>';
        
        if (!empty($args['actions'])) {
            echo '<div class="card-actions">';
            foreach ($args['actions'] as $action) {
                $button_class = isset($action['class']) ? esc_attr($action['class']) : 'button';
                $button_icon = isset($action['icon']) ? '<span class="dashicons dashicons-' . esc_attr($action['icon']) . '"></span>' : '';
                $button_text = isset($action['text']) ? esc_html($action['text']) : '';
                $button_attrs = isset($action['attrs']) ? $action['attrs'] : array();
                
                $attrs_string = '';
                foreach ($button_attrs as $attr => $value) {
                    $attrs_string .= ' ' . esc_attr($attr) . '="' . esc_attr($value) . '"';
                }
                
                echo '<button class="' . $button_class . '"' . $attrs_string . '>';
                echo $button_icon . ' ' . $button_text;
                echo '</button>';
            }
            echo '</div>';
        }
        
        echo '</div>';
    }
    
    echo '<div class="card-content">';
    echo $content;
    echo '</div>';
    
    if ($args['footer']) {
        echo '<div class="card-footer">';
        echo $args['footer'];
        echo '</div>';
    }
    
    echo '</div>';
}

/**
 * Renderuje kartę ze statystykami
 *
 * @param string $title Tytuł karty
 * @param string $value Wartość statystyki
 * @param array $args Dodatkowe argumenty
 */
function wpmzf_render_stat_card($title, $value, $args = array()) {
    $defaults = array(
        'class' => 'luna-crm-stat-card',
        'icon' => '',
        'trend' => '',
        'trend_value' => '',
        'color' => ''
    );
    
    $args = wp_parse_args($args, $defaults);
    
    $class = esc_attr($args['class']);
    if ($args['color']) {
        $class .= ' color-' . esc_attr($args['color']);
    }
    
    $icon = $args['icon'] ? '<span class="dashicons dashicons-' . esc_attr($args['icon']) . '"></span>' : '';
    
    echo '<div class="' . $class . '">';
    
    if ($icon) {
        echo '<div class="stat-icon">' . $icon . '</div>';
    }
    
    echo '<div class="stat-content">';
    echo '<h3>' . esc_html($value) . '</h3>';
    echo '<p>' . esc_html($title) . '</p>';
    
    if ($args['trend'] && $args['trend_value']) {
        $trend_class = $args['trend'] === 'up' ? 'trend-up' : 'trend-down';
        $trend_icon = $args['trend'] === 'up' ? 'arrow-up-alt' : 'arrow-down-alt';
        
        echo '<div class="stat-trend ' . $trend_class . '">';
        echo '<span class="dashicons dashicons-' . $trend_icon . '"></span>';
        echo '<span>' . esc_html($args['trend_value']) . '</span>';
        echo '</div>';
    }
    
    echo '</div>';
    echo '</div>';
}

/**
 * Renderuje listę w karcie
 *
 * @param string $title Tytuł karty
 * @param array $items Elementy listy
 * @param array $args Dodatkowe argumenty
 */
function wpmzf_render_list_card($title, $items, $args = array()) {
    $defaults = array(
        'class' => 'luna-crm-list-card',
        'icon' => '',
        'empty_message' => 'Brak elementów',
        'item_template' => null
    );
    
    $args = wp_parse_args($args, $defaults);
    
    $content = '';
    
    if (!empty($items)) {
        $content .= '<ul class="card-list">';
        
        foreach ($items as $item) {
            $content .= '<li class="card-list-item">';
            
            if ($args['item_template'] && is_callable($args['item_template'])) {
                $content .= call_user_func($args['item_template'], $item);
            } else {
                $content .= esc_html($item);
            }
            
            $content .= '</li>';
        }
        
        $content .= '</ul>';
    } else {
        $content .= '<p class="empty-message">' . esc_html($args['empty_message']) . '</p>';
    }
    
    wpmzf_render_simple_card($title, $content, $args);
}

/**
 * Renderuje kartę z postępem
 *
 * @param string $title Tytuł karty
 * @param int $progress Postęp w procentach (0-100)
 * @param array $args Dodatkowe argumenty
 */
function wpmzf_render_progress_card($title, $progress, $args = array()) {
    $defaults = array(
        'class' => 'luna-crm-progress-card',
        'icon' => '',
        'description' => '',
        'color' => 'primary'
    );
    
    $args = wp_parse_args($args, $defaults);
    
    $progress = max(0, min(100, intval($progress)));
    
    $content = '';
    
    if ($args['description']) {
        $content .= '<p>' . esc_html($args['description']) . '</p>';
    }
    
    $content .= '<div class="progress-wrapper">';
    $content .= '<div class="progress-bar">';
    $content .= '<div class="progress-fill color-' . esc_attr($args['color']) . '" style="width: ' . $progress . '%"></div>';
    $content .= '</div>';
    $content .= '<div class="progress-text">' . $progress . '%</div>';
    $content .= '</div>';
    
    wpmzf_render_simple_card($title, $content, $args);
}
