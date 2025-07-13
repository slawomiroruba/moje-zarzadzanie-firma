/**
 * Projects Management JavaScript
 *
 * @package WPMZF
 */

// Utility functions - vanilla JS helpers
function ready(fn) {
    if (document.readyState !== 'loading') {
        fn();
    } else {
        document.addEventListener('DOMContentLoaded', fn);
    }
}

function $(selector, context = document) {
    return context.querySelector(selector);
}

function $$(selector, context = document) {
    return context.querySelectorAll(selector);
}

function ajax(options) {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        
        xhr.open(options.type || 'GET', options.url, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
        
        xhr.onload = function() {
            if (xhr.status >= 200 && xhr.status < 300) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    resolve(response);
                } catch (e) {
                    resolve(xhr.responseText);
                }
            } else {
                reject(new Error('Request failed'));
            }
        };
        
        xhr.onerror = function() {
            reject(new Error('Request failed'));
        };
        
        if (options.data) {
            const formData = new URLSearchParams(options.data).toString();
            xhr.send(formData);
        } else {
            xhr.send();
        }
    });
}

function serializeForm(form) {
    const formData = new FormData(form);
    const data = {};
    for (let [key, value] of formData.entries()) {
        data[key] = value;
    }
    return data;
}

function fadeOut(element, duration = 300) {
    element.style.transition = `opacity ${duration}ms`;
    element.style.opacity = '0';
    setTimeout(() => {
        if (element.parentNode) {
            element.parentNode.removeChild(element);
        }
    }, duration);
}

(function() {
    'use strict';

    var Projects = {
        init: function() {
            this.bindEvents();
            this.initKanban();
            this.initFilters();
        },

        bindEvents: function() {
            document.addEventListener('click', function(e) {
                if (e.target.matches('.add-project') || e.target.closest('.add-project')) {
                    e.preventDefault();
                    Projects.showAddForm();
                }
                if (e.target.matches('.edit-project') || e.target.closest('.edit-project')) {
                    e.preventDefault();
                    Projects.showEditForm.call(e.target.closest('.edit-project'));
                }
                if (e.target.matches('.delete-project') || e.target.closest('.delete-project')) {
                    e.preventDefault();
                    Projects.deleteProject.call(e.target.closest('.delete-project'));
                }
                if (e.target.matches('.project-card') || e.target.closest('.project-card')) {
                    Projects.showProjectDetails.call(e.target.closest('.project-card'));
                }
                if (e.target.matches('.modal-close, .cancel-project') || e.target.closest('.modal-close, .cancel-project')) {
                    e.preventDefault();
                    const modal = $('#project-modal');
                    if (modal) modal.style.display = 'none';
                }
                if (e.target.matches('.luna-crm-modal')) {
                    if (e.target === e.currentTarget) {
                        e.target.style.display = 'none';
                    }
                }
            });

            document.addEventListener('submit', function(e) {
                if (e.target.matches('#project-form')) {
                    e.preventDefault();
                    Projects.saveProject.call(e.target, e);
                }
            });

            document.addEventListener('change', function(e) {
                if (e.target.matches('#project-status-filter, #project-company-filter')) {
                    Projects.filterProjects();
                }
            });
        },

        initKanban: function() {
            if ($('.projects-kanban')) {
                this.setupKanbanDragDrop();
            }
        },

        setupKanbanDragDrop: function() {
            // Note: This would require a vanilla JS drag and drop library
            // For now, we'll leave a placeholder for the functionality
            console.log('Kanban drag and drop would be initialized here');
            // Consider using SortableJS or similar library for drag & drop
        },

        updateProjectStatus: function(projectId, newStatus, element) {
            ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpmzf_update_project_status',
                    project_id: projectId,
                    status: newStatus,
                    nonce: wpmzf_projects.nonce
                }
            }).then(function(response) {
                if (response.success) {
                    // Move element to new column
                    const targetColumn = $(`.kanban-column[data-status="${newStatus}"] .kanban-column-body`);
                    if (targetColumn && element.parentNode) {
                        element.parentNode.removeChild(element);
                        targetColumn.appendChild(element);
                    }
                    
                    // Update counters
                    Projects.updateKanbanCounters();
                    
                    Projects.showNotification('Status projektu został zaktualizowany', 'success');
                } else {
                    Projects.showNotification('Błąd aktualizacji statusu: ' + response.data, 'error');
                    // Reset element position
                    element.style.top = '0';
                    element.style.left = '0';
                }
            }).catch(function(error) {
                console.error('Error updating project status:', error);
                Projects.showNotification('Błąd komunikacji z serwerem', 'error');
            });
        },

        updateKanbanCounters: function() {
            const columns = $$('.kanban-column');
            columns.forEach(function(column) {
                const count = column.querySelectorAll('.kanban-item').length;
                const counter = column.querySelector('.kanban-column-count');
                if (counter) {
                    counter.textContent = count;
                }
            });
        },

        initFilters: function() {
            // Initialize filter dropdowns
            if ($('#project-status-filter')) {
                this.populateStatusFilter();
            }
            
            if ($('#project-company-filter')) {
                this.populateCompanyFilter();
            }
        },

        populateStatusFilter: function() {
            var statuses = ['planning', 'active', 'on-hold', 'completed', 'cancelled'];
            var select = $('#project-status-filter');
            
            if (select) {
                statuses.forEach(function(status) {
                    var option = document.createElement('option');
                    option.value = status;
                    option.textContent = Projects.getStatusLabel(status);
                    select.appendChild(option);
                });
            }
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
            var statusFilter = $('#project-status-filter');
            var companyFilter = $('#project-company-filter');
            var status = statusFilter ? statusFilter.value : '';
            var company = companyFilter ? companyFilter.value : '';
            
            const projectCards = $$('.project-card');
            projectCards.forEach(function(card) {
                var projectStatus = card.getAttribute('data-status');
                var projectCompany = card.getAttribute('data-company-id');
                var showProject = true;
                
                if (status && projectStatus !== status) {
                    showProject = false;
                }
                
                if (company && projectCompany != company) {
                    showProject = false;
                }
                
                card.style.display = showProject ? '' : 'none';
            });
        },

        showAddForm: function() {
            Projects.openModal('add');
        },

        showEditForm: function() {
            var projectId = this.getAttribute('data-project-id');
            Projects.openModal('edit', projectId);
        },

        openModal: function(action, projectId = null) {
            var modal = $('#project-modal');
            var form = $('#project-form');
            
            if (action === 'edit' && projectId) {
                Projects.loadProjectData(projectId);
                const title = modal.querySelector('.modal-title');
                if (title) title.textContent = 'Edytuj projekt';
            } else {
                if (form) form.reset();
                const title = modal.querySelector('.modal-title');
                if (title) title.textContent = 'Dodaj projekt';
                const idField = $('#project-id');
                if (idField) idField.value = '';
            }
            
            if (modal) modal.style.display = 'block';
        },

        loadProjectData: function(projectId) {
            ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpmzf_get_project',
                    project_id: projectId,
                    nonce: wpmzf_projects.nonce
                }
            }).then(function(response) {
                if (response.success) {
                    var project = response.data;
                    const fields = {
                        'project-id': project.id,
                        'project-name': project.name,
                        'project-description': project.description,
                        'project-company': project.company_id,
                        'project-budget': project.budget,
                        'project-start-date': project.start_date,
                        'project-end-date': project.end_date,
                        'project-status': project.status
                    };
                    
                    Object.keys(fields).forEach(function(fieldId) {
                        const field = $('#' + fieldId);
                        if (field) {
                            field.value = fields[fieldId] || '';
                        }
                    });
                }
            }).catch(function(error) {
                console.error('Error loading project data:', error);
                Projects.showNotification('Błąd ładowania danych projektu', 'error');
            });
        },

        saveProject: function(e) {
            e.preventDefault();
            
            var form = this;
            var formData = serializeForm(form);
            formData.action = 'wpmzf_save_project';
            formData.nonce = wpmzf_projects.nonce;
            
            const submitButton = form.querySelector('button[type="submit"]');
            if (submitButton) submitButton.disabled = true;
            
            ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData
            }).then(function(response) {
                if (response.success) {
                    const modal = $('#project-modal');
                    if (modal) modal.style.display = 'none';
                    Projects.showNotification('Projekt został zapisany', 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    Projects.showNotification('Błąd: ' + response.data, 'error');
                }
            }).catch(function(error) {
                console.error('Error saving project:', error);
                Projects.showNotification('Błąd komunikacji z serwerem', 'error');
            }).finally(function() {
                if (submitButton) submitButton.disabled = false;
            });
        },

        deleteProject: function() {
            var projectId = this.getAttribute('data-project-id');
            var projectCard = this.closest('.project-card');
            var projectNameEl = projectCard ? projectCard.querySelector('h3') : null;
            var projectName = projectNameEl ? projectNameEl.textContent : 'projekt';
            
            if (confirm('Czy na pewno chcesz usunąć projekt "' + projectName + '"?')) {
                ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wpmzf_delete_project',
                        project_id: projectId,
                        nonce: wpmzf_projects.nonce
                    }
                }).then(function(response) {
                    if (response.success) {
                        Projects.showNotification('Projekt został usunięty', 'success');
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        Projects.showNotification('Błąd: ' + response.data, 'error');
                    }
                }).catch(function(error) {
                    console.error('Error deleting project:', error);
                    Projects.showNotification('Błąd komunikacji z serwerem', 'error');
                });
            }
        },

        showProjectDetails: function() {
            var projectId = this.getAttribute('data-project-id');
            if (projectId && typeof wpmzf_projects !== 'undefined' && wpmzf_projects.project_view_url) {
                window.location.href = wpmzf_projects.project_view_url + '&project_id=' + projectId;
            }
        },

        showNotification: function(message, type) {
            // Remove existing notifications
            const existingNotifications = $$('.notice');
            existingNotifications.forEach(function(notification) {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            });
            
            const notification = document.createElement('div');
            notification.className = 'notice notice-' + type + ' is-dismissible';
            notification.innerHTML = '<p>' + message + '</p>';
            
            const wrap = $('.wrap h1');
            if (wrap && wrap.parentNode) {
                wrap.parentNode.insertBefore(notification, wrap.nextSibling);
            } else {
                document.body.appendChild(notification);
            }
            
            setTimeout(function() {
                fadeOut(notification);
            }, 3000);
        }
    };

    // Inicjalizacja
    ready(function() {
        if (typeof wpmzf_projects !== 'undefined') {
            Projects.init();
        }
    });

})();
