<?php
/**
 * Komponent osi czasu aktywności
 * 
 * @package WPMZF
 * @subpackage Admin/Views/Universal/Components
 */

if (!defined('ABSPATH')) {
    exit;
}

// Pobierz aktywności powiązane z obiektem
$activities = get_posts(array(
    'post_type' => 'activity',
    'meta_query' => array(
        array(
            'key' => $config['meta_key'],
            'value' => $object_id,
            'compare' => '='
        )
    ),
    'posts_per_page' => 50,
    'orderby' => 'date',
    'order' => 'DESC'
));
?>

<div id="wpmzf-timeline-section">
    <div class="timeline-header">
        <h3>Historia aktywności</h3>
        <div class="timeline-filters">
            <select id="timeline-filter-type">
                <option value="">Wszystkie typy</option>
                <option value="notatka">Notatki</option>
                <option value="rozmowa">Rozmowy</option>
                <option value="spotkanie">Spotkania</option>
                <option value="email">E-maile</option>
                <option value="inne">Inne</option>
            </select>
            
            <select id="timeline-filter-period">
                <option value="">Cały okres</option>
                <option value="today">Dzisiaj</option>
                <option value="week">Ostatni tydzień</option>
                <option value="month">Ostatni miesiąc</option>
                <option value="quarter">Ostatnie 3 miesiące</option>
            </select>
        </div>
    </div>

    <div class="timeline-container">
        <?php if (empty($activities)): ?>
            <div class="timeline-empty">
                <div class="timeline-empty-icon">📝</div>
                <h4>Brak aktywności</h4>
                <p>Nie ma jeszcze żadnych aktywności dla tego elementu. Dodaj pierwszą notatkę lub wyślij e-mail, aby rozpocząć historię.</p>
            </div>
        <?php else: ?>
            <div class="timeline-items">
                <?php 
                $current_date = '';
                foreach ($activities as $activity): 
                    $activity_date = date('Y-m-d', strtotime($activity->post_date));
                    $activity_type = get_post_meta($activity->ID, 'activity_type', true) ?: 'notatka';
                    $activity_date_custom = get_post_meta($activity->ID, 'activity_date', true);
                    
                    // Użyj niestandardowej daty jeśli jest ustawiona
                    if ($activity_date_custom) {
                        $display_date = $activity_date_custom;
                        $activity_date = date('Y-m-d', strtotime($activity_date_custom));
                    } else {
                        $display_date = $activity->post_date;
                    }
                    
                    // Sprawdź czy nowy dzień
                    $show_date_separator = false;
                    if ($current_date !== $activity_date) {
                        $current_date = $activity_date;
                        $show_date_separator = true;
                    }
                    
                    // Ikony dla różnych typów aktywności
                    $icons = array(
                        'notatka' => '📝',
                        'rozmowa' => '📞',
                        'spotkanie' => '🤝',
                        'email' => '✉️',
                        'inne' => '📋'
                    );
                    
                    $icon = $icons[$activity_type] ?? '📋';
                    
                    // Sprawdź czy ma załączniki
                    $attachments = get_post_meta($activity->ID, 'attachments', true);
                    $has_attachments = !empty($attachments);
                    
                    // Pobierz autora
                    $author = get_userdata($activity->post_author);
                ?>
                
                <?php if ($show_date_separator): ?>
                    <div class="timeline-date-separator">
                        <span class="timeline-date-text">
                            <?php echo date('d.m.Y', strtotime($activity_date)); ?>
                        </span>
                    </div>
                <?php endif; ?>
                
                <div class="timeline-item" data-activity-type="<?php echo esc_attr($activity_type); ?>" data-activity-date="<?php echo esc_attr($activity_date); ?>">
                    <div class="timeline-marker">
                        <span class="timeline-icon"><?php echo $icon; ?></span>
                    </div>
                    
                    <div class="timeline-content">
                        <div class="timeline-content-header">
                            <div class="timeline-meta">
                                <span class="timeline-type timeline-type-<?php echo $activity_type; ?>">
                                    <?php 
                                    $type_labels = array(
                                        'notatka' => 'Notatka',
                                        'rozmowa' => 'Rozmowa telefoniczna',
                                        'spotkanie' => 'Spotkanie',
                                        'email' => 'E-mail',
                                        'inne' => 'Inne'
                                    );
                                    echo $type_labels[$activity_type] ?? 'Aktywność';
                                    ?>
                                </span>
                                
                                <span class="timeline-time">
                                    <?php echo date('H:i', strtotime($display_date)); ?>
                                </span>
                                
                                <?php if ($author): ?>
                                    <span class="timeline-author">
                                        👤 <?php echo esc_html($author->display_name); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="timeline-actions">
                                <button type="button" class="timeline-action-btn edit-activity-btn" data-activity-id="<?php echo $activity->ID; ?>" title="Edytuj">
                                    ✏️
                                </button>
                                <button type="button" class="timeline-action-btn delete-activity-btn" data-activity-id="<?php echo $activity->ID; ?>" title="Usuń">
                                    🗑️
                                </button>
                            </div>
                        </div>
                        
                        <div class="timeline-body">
                            <?php if ($activity->post_title && $activity->post_title !== 'Auto Draft'): ?>
                                <h4 class="timeline-title"><?php echo esc_html($activity->post_title); ?></h4>
                            <?php endif; ?>
                            
                            <div class="timeline-description">
                                <?php echo wpautop($activity->post_content); ?>
                            </div>
                            
                            <?php if ($has_attachments): ?>
                                <div class="timeline-attachments">
                                    <h5>📎 Załączniki:</h5>
                                    <div class="attachment-list">
                                        <?php foreach ($attachments as $attachment): ?>
                                            <a href="<?php echo esc_url($attachment['url']); ?>" 
                                               class="attachment-link" 
                                               target="_blank">
                                                <span class="attachment-icon">📄</span>
                                                <span class="attachment-name"><?php echo esc_html($attachment['name']); ?></span>
                                                <span class="attachment-size">(<?php echo size_format($attachment['size']); ?>)</span>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php
                            // Informacje specyficzne dla typu aktywności
                            switch ($activity_type) {
                                case 'email':
                                    $email_to = get_post_meta($activity->ID, 'email_to', true);
                                    $email_subject = get_post_meta($activity->ID, 'email_subject', true);
                                    if ($email_to || $email_subject):
                            ?>
                                        <div class="timeline-email-meta">
                                            <?php if ($email_to): ?>
                                                <div><strong>Do:</strong> <?php echo esc_html($email_to); ?></div>
                                            <?php endif; ?>
                                            <?php if ($email_subject): ?>
                                                <div><strong>Temat:</strong> <?php echo esc_html($email_subject); ?></div>
                                            <?php endif; ?>
                                        </div>
                            <?php
                                    endif;
                                    break;
                                    
                                case 'rozmowa':
                                    $call_duration = get_post_meta($activity->ID, 'call_duration', true);
                                    if ($call_duration):
                            ?>
                                        <div class="timeline-call-meta">
                                            <div><strong>Czas trwania:</strong> <?php echo esc_html($call_duration); ?> min</div>
                                        </div>
                            <?php
                                    endif;
                                    break;
                            }
                            ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (count($activities) >= 50): ?>
                <div class="timeline-load-more">
                    <button type="button" class="button" id="load-more-activities">Załaduj więcej</button>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<style>
    .timeline-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
        padding-bottom: 16px;
        border-bottom: 1px solid #e1e5e9;
    }

    .timeline-header h3 {
        margin: 0;
        font-size: 18px;
    }

    .timeline-filters {
        display: flex;
        gap: 12px;
    }

    .timeline-filters select {
        padding: 6px 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 13px;
        background: white;
    }

    .timeline-container {
        position: relative;
    }

    .timeline-empty {
        text-align: center;
        padding: 60px 20px;
        color: #646970;
    }

    .timeline-empty-icon {
        font-size: 48px;
        margin-bottom: 16px;
    }

    .timeline-empty h4 {
        margin: 0 0 8px 0;
        color: #1d2327;
    }

    .timeline-items {
        position: relative;
    }

    .timeline-items::before {
        content: '';
        position: absolute;
        left: 20px;
        top: 0;
        bottom: 0;
        width: 2px;
        background: linear-gradient(to bottom, #2271b1, #e1e5e9);
    }

    .timeline-date-separator {
        position: relative;
        margin: 32px 0 24px 0;
        text-align: center;
    }

    .timeline-date-text {
        background: #f6f7f7;
        padding: 8px 16px;
        border: 1px solid #e1e5e9;
        border-radius: 20px;
        font-weight: 600;
        font-size: 13px;
        color: #1d2327;
        position: relative;
        z-index: 2;
    }

    .timeline-item {
        position: relative;
        margin-bottom: 24px;
        display: flex;
        align-items: flex-start;
        opacity: 0;
        animation: fadeInUp 0.5s ease forwards;
    }

    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .timeline-marker {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: white;
        border: 3px solid #2271b1;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 16px;
        flex-shrink: 0;
        position: relative;
        z-index: 2;
    }

    .timeline-icon {
        font-size: 16px;
    }

    .timeline-content {
        flex: 1;
        background: white;
        border: 1px solid #e1e5e9;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        transition: all 0.2s ease;
    }

    .timeline-content:hover {
        box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    }

    .timeline-content-header {
        background: #f6f7f7;
        padding: 12px 16px;
        border-bottom: 1px solid #e1e5e9;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .timeline-meta {
        display: flex;
        align-items: center;
        gap: 12px;
        font-size: 12px;
    }

    .timeline-type {
        padding: 4px 8px;
        border-radius: 4px;
        font-weight: 600;
        font-size: 11px;
        text-transform: uppercase;
    }

    .timeline-type-notatka {
        background: #e3f2fd;
        color: #1565c0;
    }

    .timeline-type-rozmowa {
        background: #e8f5e8;
        color: #2e7d32;
    }

    .timeline-type-spotkanie {
        background: #fff3e0;
        color: #ef6c00;
    }

    .timeline-type-email {
        background: #f3e5f5;
        color: #7b1fa2;
    }

    .timeline-type-inne {
        background: #f5f5f5;
        color: #424242;
    }

    .timeline-time {
        color: #646970;
        font-weight: 600;
    }

    .timeline-author {
        color: #646970;
    }

    .timeline-actions {
        display: flex;
        gap: 4px;
    }

    .timeline-action-btn {
        background: none;
        border: none;
        padding: 4px;
        cursor: pointer;
        border-radius: 3px;
        font-size: 12px;
        opacity: 0.7;
        transition: all 0.2s ease;
    }

    .timeline-action-btn:hover {
        opacity: 1;
        background: rgba(0,0,0,0.1);
    }

    .timeline-body {
        padding: 16px;
    }

    .timeline-title {
        margin: 0 0 8px 0;
        font-size: 16px;
        color: #1d2327;
    }

    .timeline-description {
        color: #1d2327;
        line-height: 1.6;
    }

    .timeline-description p:last-child {
        margin-bottom: 0;
    }

    .timeline-attachments {
        margin-top: 16px;
        padding-top: 16px;
        border-top: 1px solid #f0f0f1;
    }

    .timeline-attachments h5 {
        margin: 0 0 8px 0;
        font-size: 13px;
        color: #646970;
    }

    .attachment-list {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .attachment-link {
        display: flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
        color: #2271b1;
        font-size: 13px;
        padding: 6px 8px;
        border-radius: 4px;
        transition: background 0.2s ease;
    }

    .attachment-link:hover {
        background: #f6f7f7;
    }

    .attachment-size {
        color: #646970;
        font-size: 11px;
    }

    .timeline-email-meta,
    .timeline-call-meta {
        margin-top: 12px;
        padding: 12px;
        background: #f9f9f9;
        border-radius: 4px;
        font-size: 13px;
    }

    .timeline-email-meta div,
    .timeline-call-meta div {
        margin-bottom: 4px;
    }

    .timeline-email-meta div:last-child,
    .timeline-call-meta div:last-child {
        margin-bottom: 0;
    }

    .timeline-load-more {
        text-align: center;
        margin-top: 32px;
        padding-top: 24px;
        border-top: 1px solid #e1e5e9;
    }

    /* Filtrowanie */
    .timeline-item[data-hidden="true"] {
        display: none;
    }

    @media (max-width: 768px) {
        .timeline-header {
            flex-direction: column;
            gap: 16px;
            align-items: flex-start;
        }

        .timeline-filters {
            width: 100%;
            justify-content: flex-end;
        }

        .timeline-items::before {
            left: 15px;
        }

        .timeline-marker {
            width: 30px;
            height: 30px;
            margin-right: 12px;
        }

        .timeline-icon {
            font-size: 14px;
        }

        .timeline-content-header {
            flex-direction: column;
            gap: 8px;
            align-items: flex-start;
        }

        .timeline-meta {
            flex-wrap: wrap;
        }
    }
</style>
