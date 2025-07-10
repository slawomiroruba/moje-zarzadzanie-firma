<?php
/**
 * Komponent Kanban Board
 *
 * @package WPMZF
 * @subpackage Admin/Components
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Renderuje tablicę Kanban
 *
 * @param array $columns Kolumny tablicy
 * @param array $items Elementy do wyświetlenia
 * @param array $args Dodatkowe argumenty
 */
function wpmzf_render_kanban_board($columns, $items, $args = array()) {
    $defaults = array(
        'class' => 'luna-crm-kanban',
        'item_template' => null,
        'draggable' => true,
        'on_move' => null
    );
    
    $args = wp_parse_args($args, $defaults);
    
    $class = esc_attr($args['class']);
    $draggable = $args['draggable'] ? ' data-draggable="true"' : '';
    
    echo '<div class="' . $class . '"' . $draggable . '>';
    
    foreach ($columns as $column_id => $column_data) {
        $column_title = isset($column_data['title']) ? $column_data['title'] : $column_id;
        $column_items = isset($items[$column_id]) ? $items[$column_id] : array();
        
        echo '<div class="kanban-column" data-column="' . esc_attr($column_id) . '">';
        echo '<div class="kanban-column-header">';
        echo '<h3>' . esc_html($column_title) . '</h3>';
        echo '<span class="kanban-column-count">' . count($column_items) . '</span>';
        echo '</div>';
        
        echo '<div class="kanban-column-body">';
        
        foreach ($column_items as $item) {
            $item_class = 'kanban-item';
            if ($args['draggable']) {
                $item_class .= ' draggable';
            }
            
            echo '<div class="' . $item_class . '" data-item-id="' . esc_attr($item['id']) . '">';
            
            if ($args['item_template'] && is_callable($args['item_template'])) {
                echo call_user_func($args['item_template'], $item);
            } else {
                echo '<div class="kanban-item-title">' . esc_html($item['title']) . '</div>';
                if (isset($item['description'])) {
                    echo '<div class="kanban-item-description">' . esc_html($item['description']) . '</div>';
                }
            }
            
            echo '</div>';
        }
        
        echo '</div>';
        echo '</div>';
    }
    
    echo '</div>';
    
    if ($args['draggable']) {
        echo '<script>
        jQuery(document).ready(function($) {
            // Implementacja drag & drop
            $(".kanban-item.draggable").draggable({
                revert: "invalid",
                helper: "clone",
                cursor: "move",
                opacity: 0.7
            });
            
            $(".kanban-column-body").droppable({
                accept: ".kanban-item",
                drop: function(event, ui) {
                    var itemId = ui.draggable.data("item-id");
                    var newColumn = $(this).parent().data("column");
                    var oldColumn = ui.draggable.parent().parent().data("column");
                    
                    if (newColumn !== oldColumn) {
                        ui.draggable.detach().appendTo($(this));
                        
                        // Wywołaj callback jeśli istnieje
                        if (typeof ' . json_encode($args['on_move']) . ' === "function") {
                            ' . $args['on_move'] . '(itemId, newColumn, oldColumn);
                        }
                        
                        // Aktualizuj liczniki
                        updateKanbanCounts();
                    }
                }
            });
            
            function updateKanbanCounts() {
                $(".kanban-column").each(function() {
                    var count = $(this).find(".kanban-item").length;
                    $(this).find(".kanban-column-count").text(count);
                });
            }
        });
        </script>';
    }
}

/**
 * Renderuje projekt jako element Kanban
 *
 * @param array $project Dane projektu
 * @return string
 */
function wpmzf_render_project_kanban_item($project) {
    $output = '';
    
    $output .= '<div class="kanban-item-title">' . esc_html($project['name']) . '</div>';
    
    if (isset($project['company'])) {
        $output .= '<div class="kanban-item-meta">' . esc_html($project['company']) . '</div>';
    }
    
    if (isset($project['budget'])) {
        $output .= '<div class="kanban-item-budget">' . esc_html($project['budget']) . '</div>';
    }
    
    if (isset($project['progress'])) {
        $output .= '<div class="kanban-item-progress">';
        $output .= '<div class="progress-bar">';
        $output .= '<div class="progress-fill" style="width: ' . intval($project['progress']) . '%"></div>';
        $output .= '</div>';
        $output .= '<span class="progress-text">' . intval($project['progress']) . '%</span>';
        $output .= '</div>';
    }
    
    return $output;
}
