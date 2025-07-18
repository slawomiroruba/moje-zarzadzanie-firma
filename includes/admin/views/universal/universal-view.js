/**
 * JavaScript dla uniwersalnego systemu widoków
 * 
 * @package WPMZF
 * @subpackage Admin/Views/Universal
 */

(function($) {
    'use strict';

    // Główny obiekt obsługujący uniwersalne widoki
    const WPMZFUniversalView = {
        
        init: function() {
            this.initActivityTabs();
            this.initTaskManagement();
            this.initTimeline();
            this.initFileUploads();
            this.bindEvents();
        },

        /**
         * Inicjalizacja zakładek aktywności
         */
        initActivityTabs: function() {
            // Przełączanie zakładek
            $(document).on('click', '.tab-link', function(e) {
                e.preventDefault();
                
                const tabId = $(this).data('tab');
                const $tabContainer = $(this).closest('#wpmzf-activity-box');
                
                // Usunięcie aktywnych klas
                $tabContainer.find('.tab-link').removeClass('active');
                $tabContainer.find('.tab-content').removeClass('active');
                
                // Dodanie aktywnych klas
                $(this).addClass('active');
                $tabContainer.find('#' + tabId + '-tab-content').addClass('active');
            });

            // Placeholder dla edytora notatek
            $(document).on('click', '.wpmzf-editor-placeholder', function() {
                const $container = $(this).closest('#wpmzf-note-main-editor');
                $(this).hide();
                $container.find('#wpmzf-note-editor-container').show();
                
                // Focus na edytorze TinyMCE jeśli dostępny
                if (typeof tinymce !== 'undefined') {
                    const editor = tinymce.get('wpmzf-note-content');
                    if (editor) {
                        editor.focus();
                    }
                }
            });

            // Obsługa formularza notatek
            $(document).on('submit', '#wpmzf-add-note-form', this.handleNoteSubmit.bind(this));
            
            // Obsługa formularza e-mail
            $(document).on('submit', '#wpmzf-send-email-form', this.handleEmailSubmit.bind(this));
            
            // Przełączanie zaawansowanych opcji e-mail
            $(document).on('click', '#toggle-email-advanced', function(e) {
                e.preventDefault();
                const $advanced = $('.email-advanced-fields');
                if ($advanced.is(':visible')) {
                    $advanced.hide();
                    $(this).text('Pokaż zaawansowane');
                } else {
                    $advanced.show();
                    $(this).text('Ukryj zaawansowane');
                }
            });
        },

        /**
         * Inicjalizacja zarządzania zadaniami
         */
        initTaskManagement: function() {
            // Pokazywanie/ukrywanie formularza dodawania zadania
            $(document).on('click', '#wpmzf-add-task-btn', function() {
                $('#wpmzf-add-task-form').slideDown();
                $('#wpmzf-add-task-form input[name="task_title"]').focus();
            });

            $(document).on('click', '.cancel-task-btn', function() {
                $('#wpmzf-add-task-form').slideUp();
                this.resetTaskForm($('#wpmzf-add-task-form form')[0]);
            }.bind(this));

            // Obsługa dodawania zadania
            $(document).on('submit', '#wpmzf-add-task-form form', this.handleTaskSubmit.bind(this));

            // Oznaczanie zadań jako ukończone
            $(document).on('change', '.task-complete-checkbox', this.handleTaskComplete.bind(this));

            // Edycja zadań
            $(document).on('click', '.edit-task-btn', this.handleTaskEdit.bind(this));

            // Usuwanie zadań
            $(document).on('click', '.delete-task-btn', this.handleTaskDelete.bind(this));

            // Modal edycji zadania
            $(document).on('click', '.task-modal-close', function() {
                $('#wpmzf-edit-task-modal').hide();
            });

            $(document).on('submit', '#wpmzf-edit-task-form', this.handleTaskUpdate.bind(this));
        },

        /**
         * Inicjalizacja osi czasu
         */
        initTimeline: function() {
            // Filtrowanie osi czasu
            $(document).on('change', '#timeline-filter-type, #timeline-filter-period', this.filterTimeline.bind(this));

            // Edycja aktywności
            $(document).on('click', '.edit-activity-btn', this.handleActivityEdit.bind(this));

            // Usuwanie aktywności
            $(document).on('click', '.delete-activity-btn', this.handleActivityDelete.bind(this));

            // Ładowanie więcej aktywności
            $(document).on('click', '#load-more-activities', this.loadMoreActivities.bind(this));
        },

        /**
         * Inicjalizacja obsługi plików
         */
        initFileUploads: function() {
            // Przycisk załączania plików
            $(document).on('click', '#wpmzf-note-attach-files-btn', function() {
                $('#wpmzf-note-files-input').click();
            });

            // Podgląd wybranych plików
            $(document).on('change', '#wpmzf-note-files-input', this.handleFilePreview.bind(this));
        },

        /**
         * Bindowanie globalnych eventów
         */
        bindEvents: function() {
            // Automatyczne zapisywanie draftu co 30 sekund
            setInterval(this.autosaveDraft.bind(this), 30000);

            // Ostrzeżenie przed opuszczeniem strony z niezapisanymi zmianami
            window.addEventListener('beforeunload', function(e) {
                if ($('.has-unsaved-changes').length > 0) {
                    e.preventDefault();
                    e.returnValue = '';
                }
            });
        },

        /**
         * Obsługa wysyłania notatki
         */
        handleNoteSubmit: function(e) {
            e.preventDefault();
            
            const $form = $(e.target);
            const $submitBtn = $form.find('button[type="submit"]');
            
            // Pobierz zawartość z TinyMCE
            let content = '';
            if (typeof tinymce !== 'undefined') {
                const editor = tinymce.get('wpmzf-note-content');
                if (editor) {
                    content = editor.getContent();
                }
            }
            
            if (!content.trim()) {
                alert('Proszę wprowadzić treść notatki.');
                return;
            }

            // Kopiuj dane do głównego formularza
            this.copyToMainForm($form, 'notatka');
            
            // Wyślij główny formularz
            this.submitMainForm($submitBtn);
        },

        /**
         * Obsługa wysyłania e-maila
         */
        handleEmailSubmit: function(e) {
            e.preventDefault();
            
            const $form = $(e.target);
            const $submitBtn = $form.find('button[type="submit"]');
            
            // Walidacja
            const emailTo = $form.find('#email-to').val();
            const subject = $form.find('#email-subject').val();
            
            if (!emailTo || !subject) {
                alert('Proszę wypełnić adres e-mail i temat.');
                return;
            }

            // Pobierz zawartość z TinyMCE
            let content = '';
            if (typeof tinymce !== 'undefined') {
                const editor = tinymce.get('email-content');
                if (editor) {
                    content = editor.getContent();
                }
            }

            // Kopiuj dane do głównego formularza
            this.copyToMainForm($form, 'email', {
                email_to: emailTo,
                email_subject: subject,
                email_cc: $form.find('#email-cc').val(),
                email_bcc: $form.find('#email-bcc').val()
            });
            
            // Wyślij główny formularz
            this.submitMainForm($submitBtn);
        },

        /**
         * Kopiowanie danych do głównego formularza aktywności
         */
        copyToMainForm: function($sourceForm, activityType, extraData = {}) {
            const $mainForm = $('#wpmzf-add-activity-form');
            
            // Ustaw typ aktywności
            $mainForm.find('#wpmzf-activity-type').val(activityType);
            
            // Kopiuj zawartość
            let content = '';
            if (activityType === 'email') {
                const editor = tinymce.get('email-content');
                content = editor ? editor.getContent() : '';
            } else {
                const editor = tinymce.get('wpmzf-note-content');
                content = editor ? editor.getContent() : '';
            }
            $mainForm.find('#wpmzf-activity-content').val(content);
            
            // Kopiuj datę
            const dateField = activityType === 'email' ? 'activity_date' : 'activity_date';
            const dateValue = $sourceForm.find(`[name="${dateField}"]`).val() || new Date().toISOString().slice(0, 16);
            $mainForm.find('#wpmzf-activity-date').val(dateValue);
            
            // Kopiuj dodatkowe dane (dla e-maili)
            Object.keys(extraData).forEach(key => {
                $mainForm.find(`[name="${key}"]`).val(extraData[key]);
            });
            
            // Kopiuj pliki
            const files = $('#wpmzf-note-files-input')[0].files;
            if (files.length > 0) {
                $('#wpmzf-note-files-input').clone().appendTo($mainForm);
            }
        },

        /**
         * Wysyłanie głównego formularza AJAX
         */
        submitMainForm: function($submitBtn) {
            const $form = $('#wpmzf-add-activity-form');
            const originalText = $submitBtn.text();
            
            $submitBtn.prop('disabled', true).text('Zapisywanie...');
            
            // Przygotuj FormData dla plików
            const formData = new FormData();
            formData.append('action', 'wpmzf_add_activity');
            
            // Dodaj wszystkie pola formularza
            $form.find('input, select, textarea').each(function() {
                if (this.type === 'file') {
                    for (let i = 0; i < this.files.length; i++) {
                        formData.append(this.name, this.files[i]);
                    }
                } else {
                    formData.append(this.name, this.value);
                }
            });

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        // Odśwież stronę lub zaktualizuj timeline
                        location.reload();
                    } else {
                        alert('Błąd: ' + (response.data || 'Nieznany błąd'));
                    }
                },
                error: function() {
                    alert('Błąd komunikacji z serwerem.');
                },
                complete: function() {
                    $submitBtn.prop('disabled', false).text(originalText);
                }
            });
        },

        /**
         * Obsługa dodawania zadania
         */
        handleTaskSubmit: function(e) {
            e.preventDefault();
            
            const $form = $(e.target);
            const $submitBtn = $form.find('button[type="submit"]');
            const originalText = $submitBtn.text();
            
            $submitBtn.prop('disabled', true).text('Zapisywanie...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: $form.serialize() + '&action=wpmzf_add_task',
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Błąd: ' + (response.data || 'Nieznany błąd'));
                    }
                },
                error: function() {
                    alert('Błąd komunikacji z serwerem.');
                },
                complete: function() {
                    $submitBtn.prop('disabled', false).text(originalText);
                }
            });
        },

        /**
         * Oznaczanie zadania jako ukończone
         */
        handleTaskComplete: function(e) {
            const $checkbox = $(e.target);
            const taskId = $checkbox.data('task-id');
            const isCompleted = $checkbox.is(':checked');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpmzf_toggle_task_status',
                    task_id: taskId,
                    completed: isCompleted ? 1 : 0,
                    security: $('#wpmzf-add-task-form [name="wpmzf_task_security"]').val()
                },
                success: function(response) {
                    if (response.success) {
                        // Aktualizuj wizualnie zadanie
                        const $taskItem = $checkbox.closest('.task-item');
                        if (isCompleted) {
                            $taskItem.addClass('status-completed');
                        } else {
                            $taskItem.removeClass('status-completed');
                        }
                    } else {
                        // Przywróć poprzedni stan checkbox
                        $checkbox.prop('checked', !isCompleted);
                        alert('Błąd podczas aktualizacji zadania.');
                    }
                },
                error: function() {
                    // Przywróć poprzedni stan checkbox
                    $checkbox.prop('checked', !isCompleted);
                    alert('Błąd komunikacji z serwerem.');
                }
            });
        },

        /**
         * Filtrowanie osi czasu
         */
        filterTimeline: function() {
            const typeFilter = $('#timeline-filter-type').val();
            const periodFilter = $('#timeline-filter-period').val();
            
            $('.timeline-item').each(function() {
                const $item = $(this);
                let show = true;
                
                // Filtr typu
                if (typeFilter && $item.data('activity-type') !== typeFilter) {
                    show = false;
                }
                
                // Filtr okresu
                if (periodFilter && show) {
                    const itemDate = new Date($item.data('activity-date'));
                    const now = new Date();
                    let cutoffDate = null;
                    
                    switch (periodFilter) {
                        case 'today':
                            cutoffDate = new Date(now.getFullYear(), now.getMonth(), now.getDate());
                            break;
                        case 'week':
                            cutoffDate = new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000);
                            break;
                        case 'month':
                            cutoffDate = new Date(now.getFullYear(), now.getMonth() - 1, now.getDate());
                            break;
                        case 'quarter':
                            cutoffDate = new Date(now.getFullYear(), now.getMonth() - 3, now.getDate());
                            break;
                    }
                    
                    if (cutoffDate && itemDate < cutoffDate) {
                        show = false;
                    }
                }
                
                $item.attr('data-hidden', !show);
            });
            
            // Ukryj/pokaż separatory dat jeśli wszystkie elementy danego dnia są ukryte
            $('.timeline-date-separator').each(function() {
                const $separator = $(this);
                const $nextItems = $separator.nextUntil('.timeline-date-separator', '.timeline-item');
                const hasVisibleItems = $nextItems.filter('[data-hidden="false"]').length > 0;
                $separator.toggle(hasVisibleItems);
            });
        },

        /**
         * Podgląd wybranych plików
         */
        handleFilePreview: function(e) {
            const files = e.target.files;
            const $container = $('#wpmzf-note-attachments-preview-container');
            
            if (files.length === 0) {
                $container.hide().empty();
                return;
            }
            
            $container.empty().show();
            
            const $header = $('<h5>').text('Wybrane pliki:');
            $container.append($header);
            
            const $fileList = $('<div>').addClass('file-preview-list');
            
            Array.from(files).forEach(file => {
                const $fileItem = $('<div>').addClass('file-preview-item');
                $fileItem.html(`
                    <span class="file-icon">📄</span>
                    <span class="file-name">${file.name}</span>
                    <span class="file-size">(${this.formatFileSize(file.size)})</span>
                `);
                $fileList.append($fileItem);
            });
            
            $container.append($fileList);
        },

        /**
         * Formatowanie rozmiaru pliku
         */
        formatFileSize: function(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        },

        /**
         * Automatyczne zapisywanie draftu
         */
        autosaveDraft: function() {
            // Implementacja automatycznego zapisywania draftu
            // Na razie pusta - można rozszerzyć w przyszłości
        },

        /**
         * Reset formularza zadania
         */
        resetTaskForm: function(form) {
            form.reset();
        },

        /**
         * Pozostałe metody dla edycji/usuwania zadań i aktywności
         */
        handleTaskEdit: function(e) {
            // TODO: Implementacja edycji zadania
            console.log('Edit task:', $(e.target).data('task-id'));
        },

        handleTaskDelete: function(e) {
            // TODO: Implementacja usuwania zadania
            if (confirm('Czy na pewno chcesz usunąć to zadanie?')) {
                console.log('Delete task:', $(e.target).data('task-id'));
            }
        },

        handleTaskUpdate: function(e) {
            // TODO: Implementacja aktualizacji zadania
            e.preventDefault();
            console.log('Update task form submitted');
        },

        handleActivityEdit: function(e) {
            // TODO: Implementacja edycji aktywności
            console.log('Edit activity:', $(e.target).data('activity-id'));
        },

        handleActivityDelete: function(e) {
            // TODO: Implementacja usuwania aktywności
            if (confirm('Czy na pewno chcesz usunąć tę aktywność?')) {
                console.log('Delete activity:', $(e.target).data('activity-id'));
            }
        },

        loadMoreActivities: function(e) {
            // TODO: Implementacja ładowania kolejnych aktywności
            console.log('Load more activities');
        }
    };

    // Inicjalizacja po załadowaniu dokumentu
    $(document).ready(function() {
        WPMZFUniversalView.init();
    });

    // Eksport do globalnego scope dla dostępu z innych skryptów
    window.WPMZFUniversalView = WPMZFUniversalView;

})(jQuery);
