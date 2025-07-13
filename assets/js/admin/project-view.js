/**
 * Project View JavaScript
 * Handles project view interactions, tasks, activities, and assignments
 *
 * @package WPMZF
 */

(function($) {
    'use strict';

    // Globalne zmienne
    var projectId = window.projectId || 0;
    var ajaxUrl = window.ajaxurl || '';
    var securityNonce = window.securityNonce || '';
    var taskSecurityNonce = window.taskSecurityNonce || '';

    // Inicjalizacja po załadowaniu DOM
    $(document).ready(function() {
        initProjectView();
        bindEvents();
        loadTasks();
    });

    /**
     * Inicjalizacja widoku projektu
     */
    function initProjectView() {
        console.log('Inicjalizacja widoku projektu - ID:', projectId);
        
        // Sprawdź czy mamy wymagane dane
        if (!projectId || !ajaxUrl || !securityNonce) {
            console.error('Brak wymaganych danych dla widoku projektu');
            return;
        }
    }

    /**
     * Przypisywanie event handlers
     */
    function bindEvents() {
        // === EDYCJA PODSTAWOWYCH DANYCH ===
        $(document).on('click', '#edit-basic-data', function(e) {
            e.preventDefault();
            toggleBasicDataEdit(true);
        });

        $(document).on('click', '#cancel-edit-basic-data', function(e) {
            e.preventDefault();
            toggleBasicDataEdit(false);
        });

        $(document).on('submit', '#edit-project-form', function(e) {
            e.preventDefault();
            saveBasicData();
        });

        // === EDYCJA PRZYPISANYCH FIRM ===
        $(document).on('click', '#edit-companies', function(e) {
            e.preventDefault();
            toggleCompaniesEdit(true);
        });

        $(document).on('click', '#cancel-edit-companies', function(e) {
            e.preventDefault();
            toggleCompaniesEdit(false);
        });

        $(document).on('submit', '#edit-companies-form', function(e) {
            e.preventDefault();
            saveCompanies();
        });

        // === EDYCJA PRZYPISANYCH OSÓB ===
        $(document).on('click', '#edit-persons', function(e) {
            e.preventDefault();
            togglePersonsEdit(true);
        });

        $(document).on('click', '#cancel-edit-persons', function(e) {
            e.preventDefault();
            togglePersonsEdit(false);
        });

        $(document).on('submit', '#edit-persons-form', function(e) {
            e.preventDefault();
            savePersons();
        });

        // === ZADANIA ===
        $(document).on('click', '#add-new-task-btn', function(e) {
            e.preventDefault();
            openTaskModal();
        });

        $(document).on('click', '#toggle-closed-tasks', function(e) {
            e.preventDefault();
            toggleClosedTasks();
        });

        // Akcje na zadaniach
        $(document).on('click', '.task-actions .dashicons', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            var action = $(this).data('action');
            var taskId = $(this).closest('.task-item').data('task-id');
            
            switch(action) {
                case 'complete':
                    updateTaskStatus(taskId, 'Zrobione');
                    break;
                case 'reopen':
                    updateTaskStatus(taskId, 'Do zrobienia');
                    break;
                case 'edit':
                    editTask(taskId);
                    break;
                case 'delete':
                    deleteTask(taskId);
                    break;
            }
        });

        // === AKTYWNOŚCI ===
        $(document).on('click', '#add-new-activity-btn', function(e) {
            e.preventDefault();
            openActivityModal();
        });

        // Akcje na aktywnościach
        $(document).on('click', '.edit-activity', function(e) {
            e.preventDefault();
            var activityId = $(this).data('activity-id');
            editActivity(activityId);
        });

        $(document).on('click', '.delete-activity', function(e) {
            e.preventDefault();
            var activityId = $(this).data('activity-id');
            deleteActivity(activityId);
        });
    }

    // === FUNKCJE EDYCJI PODSTAWOWYCH DANYCH ===

    function toggleBasicDataEdit(edit) {
        if (edit) {
            $('#basic-data-display').hide();
            $('#basic-data-edit').show();
        } else {
            $('#basic-data-display').show();
            $('#basic-data-edit').hide();
        }
    }

    function saveBasicData() {
        var form = $('#edit-project-form');
        var spinner = form.find('.spinner');
        var submitBtn = form.find('button[type="submit"]');
        
        spinner.addClass('is-active');
        submitBtn.prop('disabled', true);
        
        var formData = {
            action: 'wpmzf_update_project',
            project_id: projectId,
            security: securityNonce,
            project_title: $('#project-title').val(),
            project_description: $('#project-description').val(),
            project_status: $('#project-status').val(),
            project_budget: $('#project-budget').val(),
            start_date: $('#project-start-date').val(),
            end_date: $('#project-end-date').val()
        };

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: formData,
            success: function(response) {
                spinner.removeClass('is-active');
                submitBtn.prop('disabled', false);
                
                if (response.success) {
                    showNotification('Dane projektu zostały zaktualizowane.', 'success');
                    toggleBasicDataEdit(false);
                    
                    // Odśwież wyświetlane dane
                    location.reload();
                } else {
                    showNotification('Błąd: ' + (response.data.message || 'Nieznany błąd'), 'error');
                }
            },
            error: function() {
                spinner.removeClass('is-active');
                submitBtn.prop('disabled', false);
                showNotification('Wystąpił błąd serwera.', 'error');
            }
        });
    }

    // === FUNKCJE EDYCJI PRZYPISANYCH FIRM ===

    function toggleCompaniesEdit(edit) {
        if (edit) {
            $('#companies-display').hide();
            $('#companies-edit').show();
            // Tu można dodać inicjalizację pola ACF relationship
        } else {
            $('#companies-display').show();
            $('#companies-edit').hide();
        }
    }

    function saveCompanies() {
        var form = $('#edit-companies-form');
        var spinner = form.find('.spinner');
        var submitBtn = form.find('button[type="submit"]');
        
        spinner.addClass('is-active');
        submitBtn.prop('disabled', true);
        
        // Tu będzie logika zapisywania przypisanych firm
        // Implementacja zostanie dodana gdy pole ACF będzie gotowe
        
        showNotification('Funkcja zostanie wkrótce dodana.', 'info');
        
        spinner.removeClass('is-active');
        submitBtn.prop('disabled', false);
        toggleCompaniesEdit(false);
    }

    // === FUNKCJE EDYCJI PRZYPISANYCH OSÓB ===

    function togglePersonsEdit(edit) {
        if (edit) {
            $('#persons-display').hide();
            $('#persons-edit').show();
            // Tu można dodać inicjalizację pola ACF relationship
        } else {
            $('#persons-display').show();
            $('#persons-edit').hide();
        }
    }

    function savePersons() {
        var form = $('#edit-persons-form');
        var spinner = form.find('.spinner');
        var submitBtn = form.find('button[type="submit"]');
        
        spinner.addClass('is-active');
        submitBtn.prop('disabled', true);
        
        // Tu będzie logika zapisywania przypisanych osób
        // Implementacja zostanie dodana gdy pole ACF będzie gotowe
        
        showNotification('Funkcja zostanie wkrótce dodana.', 'info');
        
        spinner.removeClass('is-active');
        submitBtn.prop('disabled', false);
        togglePersonsEdit(false);
    }

    // === FUNKCJE ZADAŃ ===

    function loadTasks() {
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpmzf_get_project_tasks',
                project_id: projectId,
                security: taskSecurityNonce
            },
            success: function(response) {
                if (response.success) {
                    renderTasks(response.data.open_tasks, response.data.closed_tasks);
                }
            },
            error: function() {
                console.error('Błąd podczas ładowania zadań');
            }
        });
    }

    function renderTasks(openTasks, closedTasks) {
        // Renderowanie otwartych zadań
        var openTasksHtml = '';
        if (openTasks && openTasks.length > 0) {
            openTasks.forEach(function(task) {
                openTasksHtml += renderTaskItem(task);
            });
        } else {
            openTasksHtml = '<p class="no-items">Brak otwartych zadań.</p>';
        }
        $('#open-tasks-list').html(openTasksHtml);
        
        // Aktualizacja licznika
        $('.tasks-section-title').first().text('Otwarte zadania (' + (openTasks ? openTasks.length : 0) + ')');
        
        // Renderowanie zakończonych zadań
        if (closedTasks && closedTasks.length > 0) {
            var closedTasksHtml = '';
            closedTasks.forEach(function(task) {
                closedTasksHtml += renderTaskItem(task);
            });
            $('#closed-tasks-list').html(closedTasksHtml);
            $('#toggle-closed-tasks').text('Zakończone zadania (' + closedTasks.length + ')');
            $('#closed-tasks-container').show();
        } else {
            $('#closed-tasks-container').hide();
        }
    }

    function renderTaskItem(task) {
        var taskClass = getTaskClass(task);
        var statusLabel = getStatusLabel(task.status);
        var taskDateTime = task.start_date ? formatTaskDateTime(task.start_date) : '';

        return `
            <div class="task-item ${taskClass}" data-task-id="${task.id}">
                <div class="task-content">
                    <div class="task-main">
                        <div class="task-title-row">
                            <div class="task-title">${escapeHtml(task.title)}</div>
                            <div class="task-actions">
                                ${task.status !== 'Zrobione' ?
                                    `<span class="dashicons dashicons-yes-alt" title="Oznacz jako zrobione" data-action="complete"></span>` :
                                    `<span class="dashicons dashicons-undo" title="Oznacz jako do zrobienia" data-action="reopen"></span>`
                                }
                                <span class="dashicons dashicons-edit" title="Edytuj zadanie" data-action="edit"></span>
                                <span class="dashicons dashicons-trash" title="Usuń zadanie" data-action="delete"></span>
                            </div>
                        </div>
                        <div class="task-meta-row">
                            <div class="task-meta-left">
                                <span class="task-status ${task.status.toLowerCase().replace(/\\s+/g, '-')}">${statusLabel}</span>
                                ${taskDateTime ? `<span class="task-date">${taskDateTime}</span>` : ''}
                            </div>
                        </div>
                    </div>
                    ${task.content ? `<div class="task-description">${task.content}</div>` : ''}
                </div>
            </div>
        `;
    }

    function getTaskClass(task) {
        var classes = ['task-status-' + task.status.toLowerCase().replace(/\s+/g, '-')];
        
        if (task.start_date && new Date(task.start_date) < new Date() && task.status !== 'Zrobione') {
            classes.push('task-overdue');
        }
        
        return classes.join(' ');
    }

    function getStatusLabel(status) {
        var labels = {
            'Do zrobienia': 'Do zrobienia',
            'W toku': 'W toku',
            'Zrobione': 'Zrobione'
        };
        return labels[status] || status;
    }

    function formatTaskDateTime(dateString) {
        if (!dateString) return '';
        
        var date = new Date(dateString);
        var now = new Date();
        var today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        var taskDate = new Date(date.getFullYear(), date.getMonth(), date.getDate());
        
        var options = {
            day: 'numeric',
            month: 'numeric',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        };
        
        if (taskDate.getTime() === today.getTime()) {
            return 'Dziś o ' + date.toLocaleTimeString('pl-PL', {hour: '2-digit', minute: '2-digit'});
        }
        
        return date.toLocaleDateString('pl-PL', options);
    }

    function toggleClosedTasks() {
        var closedTasksList = $('#closed-tasks-list');
        var arrow = $('#toggle-closed-tasks .dashicons');
        
        if (closedTasksList.is(':visible')) {
            closedTasksList.slideUp();
            arrow.removeClass('dashicons-arrow-down').addClass('dashicons-arrow-right');
        } else {
            closedTasksList.slideDown();
            arrow.removeClass('dashicons-arrow-right').addClass('dashicons-arrow-down');
        }
    }

    function updateTaskStatus(taskId, newStatus) {
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpmzf_update_task_status',
                task_id: taskId,
                status: newStatus,
                security: taskSecurityNonce
            },
            success: function(response) {
                if (response.success) {
                    showNotification('Status zadania został zaktualizowany.', 'success');
                    loadTasks(); // Odśwież listę zadań
                } else {
                    showNotification('Błąd: ' + (response.data.message || 'Nieznany błąd'), 'error');
                }
            },
            error: function() {
                showNotification('Wystąpił błąd podczas aktualizacji statusu zadania.', 'error');
            }
        });
    }

    function editTask(taskId) {
        // Przekieruj do edycji zadania
        window.open(wpmzfProjectView.adminUrl + 'post.php?post=' + taskId + '&action=edit', '_blank');
    }

    function deleteTask(taskId) {
        if (!confirm('Czy na pewno chcesz usunąć to zadanie?')) {
            return;
        }
        
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpmzf_delete_task',
                task_id: taskId,
                security: taskSecurityNonce
            },
            success: function(response) {
                if (response.success) {
                    showNotification('Zadanie zostało usunięte.', 'success');
                    loadTasks(); // Odśwież listę zadań
                } else {
                    showNotification('Błąd: ' + (response.data.message || 'Nieznany błąd'), 'error');
                }
            },
            error: function() {
                showNotification('Wystąpił błąd podczas usuwania zadania.', 'error');
            }
        });
    }

    function openTaskModal() {
        // Prosta implementacja - można rozbudować
        var taskTitle = prompt('Podaj tytuł zadania:');
        if (taskTitle && taskTitle.trim()) {
            addTask(taskTitle.trim());
        }
    }

    function addTask(taskTitle) {
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpmzf_add_project_task',
                project_id: projectId,
                task_title: taskTitle,
                security: taskSecurityNonce
            },
            success: function(response) {
                if (response.success) {
                    showNotification('Zadanie zostało dodane.', 'success');
                    loadTasks(); // Odśwież listę zadań
                } else {
                    showNotification('Błąd: ' + (response.data.message || 'Nieznany błąd'), 'error');
                }
            },
            error: function() {
                showNotification('Wystąpił błąd podczas dodawania zadania.', 'error');
            }
        });
    }

    // === FUNKCJE AKTYWNOŚCI ===

    function openActivityModal() {
        // Prosta implementacja - można rozbudować
        var activityContent = prompt('Podaj treść aktywności:');
        if (activityContent && activityContent.trim()) {
            addActivity(activityContent.trim());
        }
    }

    function addActivity(content) {
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpmzf_add_project_activity',
                project_id: projectId,
                activity_content: content,
                activity_type: 'note',
                security: securityNonce
            },
            success: function(response) {
                if (response.success) {
                    showNotification('Aktywność została dodana.', 'success');
                    location.reload(); // Odśwież stronę aby pokazać nową aktywność
                } else {
                    showNotification('Błąd: ' + (response.data.message || 'Nieznany błąd'), 'error');
                }
            },
            error: function() {
                showNotification('Wystąpił błąd podczas dodawania aktywności.', 'error');
            }
        });
    }

    function editActivity(activityId) {
        // Przekieruj do edycji aktywności
        window.open(wpmzfProjectView.adminUrl + 'post.php?post=' + activityId + '&action=edit', '_blank');
    }

    function deleteActivity(activityId) {
        if (!confirm('Czy na pewno chcesz usunąć tę aktywność?')) {
            return;
        }
        
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpmzf_delete_activity',
                activity_id: activityId,
                security: securityNonce
            },
            success: function(response) {
                if (response.success) {
                    showNotification('Aktywność została usunięta.', 'success');
                    location.reload(); // Odśwież stronę
                } else {
                    showNotification('Błąd: ' + (response.data.message || 'Nieznany błąd'), 'error');
                }
            },
            error: function() {
                showNotification('Wystąpił błąd podczas usuwania aktywności.', 'error');
            }
        });
    }

    // === FUNKCJE POMOCNICZE ===

    function showNotification(message, type) {
        type = type || 'info';
        
        // Utwórz element powiadomienia
        var notification = $('<div class="wpmzf-notification wpmzf-notification-' + type + '">' + escapeHtml(message) + '</div>');
        
        // Dodaj do strony
        $('body').append(notification);
        
        // Pokaż z animacją
        notification.slideDown();
        
        // Automatycznie ukryj po 5 sekundach
        setTimeout(function() {
            notification.slideUp(function() {
                notification.remove();
            });
        }, 5000);
        
        // Pozwól na ręczne zamknięcie
        notification.click(function() {
            $(this).slideUp(function() {
                $(this).remove();
            });
        });
    }

    function escapeHtml(text) {
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    // Export funkcji do window dla dostępu z innych skryptów
    window.wpmzfProjectView = {
        loadTasks: loadTasks,
        showNotification: showNotification,
        escapeHtml: escapeHtml
    };

})(jQuery);
