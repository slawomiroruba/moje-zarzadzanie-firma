<?php
/**
 * Komponent zarządzania zadaniami
 * 
 * @package WPMZF
 * @subpackage Admin/Views/Universal/Components
 */

if (!defined('ABSPATH')) {
    exit;
}

// Pobierz zadania powiązane z obiektem
$tasks = get_posts(array(
    'post_type' => 'task',
    'meta_query' => array(
        array(
            'key' => $config['meta_key'],
            'value' => $object_id,
            'compare' => '='
        )
    ),
    'posts_per_page' => -1,
    'post_status' => 'any'
));
?>

<div id="wpmzf-tasks-section">
    <!-- Formularz dodawania zadania -->
    <div class="tasks-header">
        <h3>Zadania</h3>
        <button type="button" id="wpmzf-add-task-btn" class="button button-primary">+ Dodaj zadanie</button>
    </div>

    <div id="wpmzf-add-task-form" class="task-form" style="display: none;">
        <form method="post">
            <?php wp_nonce_field('wpmzf_universal_view_nonce', 'wpmzf_task_security'); ?>
            <input type="hidden" name="<?php echo $config['param_name']; ?>" value="<?php echo esc_attr($object_id); ?>">
            
            <div class="task-form-grid">
                <div class="task-form-main">
                    <input type="text" name="task_title" placeholder="Tytuł zadania" required>
                    <textarea name="task_description" placeholder="Opis zadania (opcjonalnie)" rows="3"></textarea>
                </div>
                
                <div class="task-form-meta">
                    <div class="task-field">
                        <label>Priorytet:</label>
                        <select name="task_priority">
                            <option value="low">Niski</option>
                            <option value="medium" selected>Średni</option>
                            <option value="high">Wysoki</option>
                            <option value="urgent">Pilne</option>
                        </select>
                    </div>
                    
                    <div class="task-field">
                        <label>Termin:</label>
                        <input type="datetime-local" name="task_due_date">
                    </div>
                    
                    <div class="task-field">
                        <label>Odpowiedzialny:</label>
                        <select name="task_assignee">
                            <option value="">Brak przypisania</option>
                            <?php
                            $users = get_users();
                            foreach ($users as $user) {
                                echo '<option value="' . $user->ID . '">' . esc_html($user->display_name) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="task-form-actions">
                <button type="button" class="button cancel-task-btn">Anuluj</button>
                <button type="submit" class="button button-primary">Zapisz zadanie</button>
            </div>
        </form>
    </div>

    <!-- Lista zadań -->
    <div class="tasks-list">
        <?php if (empty($tasks)): ?>
            <div class="no-tasks">
                <p>Brak zadań dla tego elementu.</p>
            </div>
        <?php else: ?>
            <?php foreach ($tasks as $task): 
                $priority = get_post_meta($task->ID, 'priority', true) ?: 'medium';
                $due_date = get_post_meta($task->ID, 'due_date', true);
                $assignee = get_post_meta($task->ID, 'assignee', true);
                $status = get_post_meta($task->ID, 'status', true) ?: 'pending';
                $assignee_user = $assignee ? get_userdata($assignee) : null;
                
                $priority_class = 'priority-' . $priority;
                $status_class = 'status-' . $status;
            ?>
                <div class="task-item <?php echo $priority_class; ?> <?php echo $status_class; ?>" data-task-id="<?php echo $task->ID; ?>">
                    <div class="task-checkbox">
                        <input type="checkbox" class="task-complete-checkbox" 
                               <?php checked($status, 'completed'); ?>
                               data-task-id="<?php echo $task->ID; ?>">
                    </div>
                    
                    <div class="task-content">
                        <div class="task-title">
                            <?php echo esc_html($task->post_title); ?>
                            <span class="task-priority priority-<?php echo $priority; ?>">
                                <?php 
                                $priority_labels = array(
                                    'low' => 'Niski',
                                    'medium' => 'Średni', 
                                    'high' => 'Wysoki',
                                    'urgent' => 'Pilne'
                                );
                                echo $priority_labels[$priority] ?? 'Średni';
                                ?>
                            </span>
                        </div>
                        
                        <?php if ($task->post_content): ?>
                            <div class="task-description">
                                <?php echo esc_html($task->post_content); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="task-meta">
                            <?php if ($assignee_user): ?>
                                <span class="task-assignee">
                                    👤 <?php echo esc_html($assignee_user->display_name); ?>
                                </span>
                            <?php endif; ?>
                            
                            <?php if ($due_date): ?>
                                <span class="task-due-date">
                                    📅 <?php echo date('d.m.Y H:i', strtotime($due_date)); ?>
                                </span>
                            <?php endif; ?>
                            
                            <span class="task-created">
                                Utworzono: <?php echo date('d.m.Y', strtotime($task->post_date)); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="task-actions">
                        <button type="button" class="button button-small edit-task-btn" data-task-id="<?php echo $task->ID; ?>">
                            ✏️ Edytuj
                        </button>
                        <button type="button" class="button button-small delete-task-btn" data-task-id="<?php echo $task->ID; ?>">
                            🗑️ Usuń
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Ukryty formularz edycji zadania -->
<div id="wpmzf-edit-task-modal" class="task-modal" style="display: none;">
    <div class="task-modal-content">
        <div class="task-modal-header">
            <h3>Edytuj zadanie</h3>
            <button type="button" class="task-modal-close">&times;</button>
        </div>
        
        <form id="wpmzf-edit-task-form" method="post">
            <?php wp_nonce_field('wpmzf_universal_view_nonce', 'wpmzf_edit_task_security'); ?>
            <input type="hidden" name="task_id" id="edit-task-id">
            
            <div class="task-form-grid">
                <div class="task-form-main">
                    <input type="text" name="task_title" id="edit-task-title" placeholder="Tytuł zadania" required>
                    <textarea name="task_description" id="edit-task-description" placeholder="Opis zadania (opcjonalnie)" rows="3"></textarea>
                </div>
                
                <div class="task-form-meta">
                    <div class="task-field">
                        <label>Priorytet:</label>
                        <select name="task_priority" id="edit-task-priority">
                            <option value="low">Niski</option>
                            <option value="medium">Średni</option>
                            <option value="high">Wysoki</option>
                            <option value="urgent">Pilne</option>
                        </select>
                    </div>
                    
                    <div class="task-field">
                        <label>Termin:</label>
                        <input type="datetime-local" name="task_due_date" id="edit-task-due-date">
                    </div>
                    
                    <div class="task-field">
                        <label>Odpowiedzialny:</label>
                        <select name="task_assignee" id="edit-task-assignee">
                            <option value="">Brak przypisania</option>
                            <?php
                            foreach ($users as $user) {
                                echo '<option value="' . $user->ID . '">' . esc_html($user->display_name) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="task-field">
                        <label>Status:</label>
                        <select name="task_status" id="edit-task-status">
                            <option value="pending">Oczekuje</option>
                            <option value="in_progress">W trakcie</option>
                            <option value="completed">Zakończone</option>
                            <option value="cancelled">Anulowane</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="task-form-actions">
                <button type="button" class="button task-modal-close">Anuluj</button>
                <button type="submit" class="button button-primary">Zapisz zmiany</button>
            </div>
        </form>
    </div>
</div>

<style>
    .tasks-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
    }

    .tasks-header h3 {
        margin: 0;
        font-size: 18px;
    }

    .task-form {
        background: #f9f9f9;
        border: 1px solid #e1e5e9;
        border-radius: 6px;
        padding: 16px;
        margin-bottom: 20px;
    }

    .task-form-grid {
        display: grid;
        grid-template-columns: 1fr 300px;
        gap: 20px;
        margin-bottom: 16px;
    }

    .task-form-main input,
    .task-form-main textarea {
        width: 100%;
        margin-bottom: 12px;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }

    .task-form-meta {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .task-field {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .task-field label {
        font-weight: 600;
        font-size: 12px;
        color: #1d2327;
    }

    .task-field select,
    .task-field input {
        padding: 6px 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 13px;
    }

    .task-form-actions {
        display: flex;
        gap: 8px;
        justify-content: flex-end;
    }

    .tasks-list {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .task-item {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 16px;
        background: white;
        border: 1px solid #e1e5e9;
        border-radius: 6px;
        border-left: 4px solid #ddd;
        transition: all 0.2s ease;
    }

    .task-item:hover {
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .task-item.priority-low {
        border-left-color: #28a745;
    }

    .task-item.priority-medium {
        border-left-color: #ffc107;
    }

    .task-item.priority-high {
        border-left-color: #fd7e14;
    }

    .task-item.priority-urgent {
        border-left-color: #dc3545;
    }

    .task-item.status-completed {
        opacity: 0.7;
        background: #f8f9fa;
    }

    .task-item.status-completed .task-title {
        text-decoration: line-through;
    }

    .task-checkbox {
        padding-top: 2px;
    }

    .task-content {
        flex: 1;
    }

    .task-title {
        font-weight: 600;
        margin-bottom: 4px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .task-priority {
        font-size: 11px;
        padding: 2px 6px;
        border-radius: 3px;
        font-weight: 500;
    }

    .task-priority.priority-low {
        background: #d4edda;
        color: #155724;
    }

    .task-priority.priority-medium {
        background: #fff3cd;
        color: #856404;
    }

    .task-priority.priority-high {
        background: #ffeaa7;
        color: #8b4513;
    }

    .task-priority.priority-urgent {
        background: #f8d7da;
        color: #721c24;
    }

    .task-description {
        color: #646970;
        margin-bottom: 8px;
        font-size: 14px;
    }

    .task-meta {
        display: flex;
        gap: 16px;
        font-size: 12px;
        color: #646970;
    }

    .task-actions {
        display: flex;
        gap: 4px;
    }

    .task-actions .button {
        font-size: 11px;
        padding: 4px 8px;
    }

    .no-tasks {
        text-align: center;
        padding: 40px;
        color: #646970;
    }

    .task-modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 10000;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .task-modal-content {
        background: white;
        border-radius: 6px;
        width: 90%;
        max-width: 600px;
        max-height: 80vh;
        overflow-y: auto;
    }

    .task-modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 16px 20px;
        border-bottom: 1px solid #e1e5e9;
    }

    .task-modal-header h3 {
        margin: 0;
    }

    .task-modal-close {
        background: none;
        border: none;
        font-size: 24px;
        cursor: pointer;
        color: #646970;
    }

    .task-modal-close:hover {
        color: #1d2327;
    }

    #wpmzf-edit-task-form {
        padding: 20px;
    }

    @media (max-width: 768px) {
        .task-form-grid {
            grid-template-columns: 1fr;
        }
        
        .task-item {
            flex-direction: column;
            gap: 8px;
        }
        
        .task-actions {
            align-self: flex-start;
        }
    }
</style>
