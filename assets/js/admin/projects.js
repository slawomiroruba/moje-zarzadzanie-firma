/**
 * Projects Management JavaScript
 *
 * @package WPMZF
 */

(function($) {
    'use strict';

    var Projects = {
        init: function() {
            this.bindEvents();
            this.initKanban();
            this.initFilters();
        },

        bindEvents: function() {
            $(document).on('click', '.add-project', this.showAddForm);
            $(document).on('click', '.edit-project', this.showEditForm);
            $(document).on('click', '.delete-project', this.deleteProject);
            $(document).on('submit', '#project-form', this.saveProject);
            $(document).on('click', '.project-card', this.showProjectDetails);
            $(document).on('change', '#project-status-filter', this.filterProjects);
            $(document).on('change', '#project-company-filter', this.filterProjects);
        },

        initKanban: function() {
            if ($('.projects-kanban').length > 0) {
                this.setupKanbanDragDrop();
            }
        },

        setupKanbanDragDrop: function() {
            $('.kanban-item').draggable({
                revert: 'invalid',
                helper: 'clone',
                cursor: 'move',
                opacity: 0.7,
                start: function(event, ui) {
                    ui.helper.addClass('dragging');
                }
            });

            $('.kanban-column-body').droppable({
                accept: '.kanban-item',
                hoverClass: 'drop-hover',
                drop: function(event, ui) {
                    var projectId = ui.draggable.data('project-id');
                    var newStatus = $(this).parent().data('status');
                    var oldStatus = ui.draggable.closest('.kanban-column').data('status');

                    if (newStatus !== oldStatus) {
                        Projects.updateProjectStatus(projectId, newStatus, ui.draggable);
                    }
                }
            });
        },

        updateProjectStatus: function(projectId, newStatus, element) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpmzf_update_project_status',
                    project_id: projectId,
                    status: newStatus,
                    nonce: wpmzf_projects.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Move element to new column
                        var targetColumn = $('.kanban-column[data-status="' + newStatus + '"] .kanban-column-body');
                        element.detach().appendTo(targetColumn);
                        
                        // Update counters
                        Projects.updateKanbanCounters();
                        
                        Projects.showNotification('Status projektu został zaktualizowany', 'success');
                    } else {
                        Projects.showNotification('Błąd aktualizacji statusu: ' + response.data, 'error');
                        // Revert position
                        element.css({top: 0, left: 0});
                    }
                }
            });
        },

        updateKanbanCounters: function() {
            $('.kanban-column').each(function() {
                var count = $(this).find('.kanban-item').length;
                $(this).find('.kanban-column-count').text(count);
            });
        },

        initFilters: function() {
            // Initialize filter dropdowns
            if ($('#project-status-filter').length > 0) {
                this.populateStatusFilter();
            }
            
            if ($('#project-company-filter').length > 0) {
                this.populateCompanyFilter();
            }
        },

        populateStatusFilter: function() {
            var statuses = ['planning', 'active', 'on-hold', 'completed', 'cancelled'];
            var select = $('#project-status-filter');
            
            statuses.forEach(function(status) {
                var option = $('<option value="' + status + '">' + Projects.getStatusLabel(status) + '</option>');
                select.append(option);
            });
        },

        populateCompanyFilter: function() {
            // This would be populated from server data
            // For now, keeping it empty to be filled by AJAX if needed
        },

        getStatusLabel: function(status) {
            var labels = {
                'planning': 'Planowanie',
                'active': 'Aktywny',
                'on-hold': 'Wstrzymany',
                'completed': 'Zakończony',
                'cancelled': 'Anulowany'
            };
            return labels[status] || status;
        },

        filterProjects: function() {
            var status = $('#project-status-filter').val();
            var company = $('#project-company-filter').val();
            
            $('.project-card').each(function() {
                var projectStatus = $(this).data('status');
                var projectCompany = $(this).data('company-id');
                var showProject = true;
                
                if (status && projectStatus !== status) {
                    showProject = false;
                }
                
                if (company && projectCompany != company) {
                    showProject = false;
                }
                
                $(this).toggle(showProject);
            });
        },

        showAddForm: function() {
            Projects.openModal('add');
        },

        showEditForm: function() {
            var projectId = $(this).data('project-id');
            Projects.openModal('edit', projectId);
        },

        openModal: function(action, projectId = null) {
            var modal = $('#project-modal');
            var form = $('#project-form');
            
            if (action === 'edit' && projectId) {
                Projects.loadProjectData(projectId);
                modal.find('.modal-title').text('Edytuj projekt');
            } else {
                form[0].reset();
                modal.find('.modal-title').text('Dodaj projekt');
                $('#project-id').val('');
            }
            
            modal.show();
        },

        loadProjectData: function(projectId) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpmzf_get_project',
                    project_id: projectId,
                    nonce: wpmzf_projects.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var project = response.data;
                        $('#project-id').val(project.id);
                        $('#project-name').val(project.name);
                        $('#project-description').val(project.description);
                        $('#project-company').val(project.company_id);
                        $('#project-budget').val(project.budget);
                        $('#project-start-date').val(project.start_date);
                        $('#project-end-date').val(project.end_date);
                        $('#project-status').val(project.status);
                    }
                }
            });
        },

        saveProject: function(e) {
            e.preventDefault();
            
            var form = $(this);
            var formData = form.serialize();
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData + '&action=wpmzf_save_project&nonce=' + wpmzf_projects.nonce,
                beforeSend: function() {
                    form.find('button[type="submit"]').prop('disabled', true);
                },
                success: function(response) {
                    if (response.success) {
                        $('#project-modal').hide();
                        Projects.showNotification('Projekt został zapisany', 'success');
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        Projects.showNotification('Błąd: ' + response.data, 'error');
                    }
                },
                complete: function() {
                    form.find('button[type="submit"]').prop('disabled', false);
                }
            });
        },

        deleteProject: function() {
            var projectId = $(this).data('project-id');
            var projectName = $(this).closest('.project-card').find('h3').text();
            
            if (confirm('Czy na pewno chcesz usunąć projekt "' + projectName + '"?')) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wpmzf_delete_project',
                        project_id: projectId,
                        nonce: wpmzf_projects.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            Projects.showNotification('Projekt został usunięty', 'success');
                            setTimeout(function() {
                                location.reload();
                            }, 1000);
                        } else {
                            Projects.showNotification('Błąd: ' + response.data, 'error');
                        }
                    }
                });
            }
        },

        showProjectDetails: function() {
            var projectId = $(this).data('project-id');
            window.location.href = wpmzf_projects.project_view_url + '&project_id=' + projectId;
        },

        showNotification: function(message, type) {
            var notification = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            $('.wrap h1').after(notification);
            
            setTimeout(function() {
                notification.fadeOut();
            }, 3000);
        }
    };

    // Inicjalizacja
    $(document).ready(function() {
        if (typeof wpmzf_projects !== 'undefined') {
            Projects.init();
        }
    });

    // Modal events
    $(document).on('click', '.modal-close, .cancel-project', function() {
        $('#project-modal').hide();
    });

    // Click outside modal to close
    $(document).on('click', '.luna-crm-modal', function(e) {
        if (e.target === this) {
            $(this).hide();
        }
    });

})(jQuery);
