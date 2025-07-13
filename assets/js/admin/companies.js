/**
 * Companies Management JavaScript - Vanilla JS
 *
 * @package WPMZF
 */

(function() {
    'use strict';

    // Utility functions for vanilla JS replacements
    function ready(fn) {
        if (document.readyState !== 'loading') {
            fn();
        } else {
            document.addEventListener('DOMContentLoaded', fn);
        }
    }

    function $(selector, context = document) {
        if (selector.startsWith('#')) {
            return context.getElementById(selector.slice(1));
        }
        return context.querySelector(selector);
    }

    function $$(selector, context = document) {
        return context.querySelectorAll(selector);
    }

    function ajax(options) {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            const method = options.type || options.method || 'GET';
            const url = options.url;
            
            xhr.open(method, url, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            
            xhr.onload = function() {
                if (xhr.status >= 200 && xhr.status < 300) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (options.success) options.success(response);
                        resolve(response);
                    } catch (e) {
                        if (options.success) options.success(xhr.responseText);
                        resolve(xhr.responseText);
                    }
                } else {
                    if (options.error) options.error(xhr);
                    reject(xhr);
                }
            };
            
            xhr.onerror = function() {
                if (options.error) options.error(xhr);
                reject(xhr);
            };
            
            if (options.beforeSend) options.beforeSend();
            
            xhr.send(options.data || null);
            
            xhr.onloadend = function() {
                if (options.complete) options.complete();
            };
        });
    }

    function serializeForm(form) {
        const formData = new FormData(form);
        const params = new URLSearchParams();
        for (const [key, value] of formData) {
            params.append(key, value);
        }
        return params.toString();
    }

    var Companies = {
        init: function() {
            this.bindEvents();
            this.initDataTable();
        },

        bindEvents: function() {
            document.addEventListener('click', this.handleClick.bind(this));
            document.addEventListener('submit', this.handleSubmit.bind(this));
        },

        handleClick: function(e) {
            if (e.target.matches('.add-company') || e.target.closest('.add-company')) {
                this.showAddForm();
            } else if (e.target.matches('.edit-company') || e.target.closest('.edit-company')) {
                this.showEditForm(e.target.closest('.edit-company'));
            } else if (e.target.matches('.delete-company') || e.target.closest('.delete-company')) {
                this.deleteCompany(e.target.closest('.delete-company'));
            } else if (e.target.matches('.company-card') || e.target.closest('.company-card')) {
                this.showCompanyDetails(e.target.closest('.company-card'));
            } else if (e.target.matches('.modal-close, .cancel-company')) {
                $('#company-modal').style.display = 'none';
            } else if (e.target.matches('.luna-crm-modal')) {
                if (e.target === e.currentTarget) {
                    e.target.style.display = 'none';
                }
            }
        },

        handleSubmit: function(e) {
            if (e.target.matches('#company-form')) {
                this.saveCompany(e);
            }
        },

        initDataTable: function() {
            const table = $('#companies-table');
            if (table && typeof jQuery !== 'undefined' && jQuery.fn.DataTable) {
                // Keep DataTable as jQuery dependency if it exists
                jQuery(table).DataTable({
                    language: {
                        url: '//cdn.datatables.net/plug-ins/1.10.24/i18n/Polish.json'
                    },
                    responsive: true,
                    order: [[1, 'asc']],
                    columnDefs: [
                        { orderable: false, targets: -1 }
                    ]
                });
            }
        },

        showAddForm: function() {
            this.openModal('add');
        },

        showEditForm: function(element) {
            const companyId = element.dataset.companyId;
            this.openModal('edit', companyId);
        },

        openModal: function(action, companyId = null) {
            const modal = $('#company-modal');
            const form = $('#company-form');
            const modalTitle = modal.querySelector('.modal-title');
            
            if (action === 'edit' && companyId) {
                this.loadCompanyData(companyId);
                modalTitle.textContent = 'Edytuj firmę';
            } else {
                form.reset();
                modalTitle.textContent = 'Dodaj firmę';
                $('#company-id').value = '';
            }
            
            modal.style.display = 'block';
        },

        loadCompanyData: function(companyId) {
            ajax({
                url: ajaxurl,
                type: 'POST',
                data: new URLSearchParams({
                    action: 'wpmzf_get_company',
                    company_id: companyId,
                    nonce: wpmzf_companies.nonce
                }).toString(),
                success: function(response) {
                    if (response.success) {
                        const company = response.data;
                        $('#company-id').value = company.id;
                        $('#company-name').value = company.name;
                        $('#company-nip').value = company.nip;
                        $('#company-address').value = company.address;
                        $('#company-phone').value = company.phone;
                        $('#company-email').value = company.email;
                        $('#company-website').value = company.website;
                    }
                }
            });
        },

        saveCompany: function(e) {
            e.preventDefault();
            
            const form = e.target;
            const formData = serializeForm(form);
            const submitBtn = form.querySelector('button[type="submit"]');
            
            ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData + '&action=wpmzf_save_company&nonce=' + wpmzf_companies.nonce,
                beforeSend: function() {
                    submitBtn.disabled = true;
                },
                success: function(response) {
                    if (response.success) {
                        $('#company-modal').style.display = 'none';
                        Companies.showNotification('Firma została zapisana', 'success');
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        Companies.showNotification('Błąd: ' + response.data, 'error');
                    }
                },
                complete: function() {
                    submitBtn.disabled = false;
                }
            });
        },

        deleteCompany: function(element) {
            const companyId = element.dataset.companyId;
            const companyCard = element.closest('.company-card');
            const companyName = companyCard.querySelector('h3').textContent;
            
            if (confirm('Czy na pewno chcesz usunąć firmę "' + companyName + '"?')) {
                ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: new URLSearchParams({
                        action: 'wpmzf_delete_company',
                        company_id: companyId,
                        nonce: wpmzf_companies.nonce
                    }).toString(),
                    success: function(response) {
                        if (response.success) {
                            Companies.showNotification('Firma została usunięta', 'success');
                            setTimeout(function() {
                                location.reload();
                            }, 1000);
                        } else {
                            Companies.showNotification('Błąd: ' + response.data, 'error');
                        }
                    }
                });
            }
        },

        showCompanyDetails: function(element) {
            const companyId = element.dataset.companyId;
            window.location.href = wpmzf_companies.company_view_url + '&company_id=' + companyId;
        },

        showNotification: function(message, type) {
            const notification = document.createElement('div');
            notification.className = 'notice notice-' + type + ' is-dismissible';
            notification.innerHTML = '<p>' + message + '</p>';
            
            const wrapH1 = document.querySelector('.wrap h1');
            if (wrapH1) {
                wrapH1.insertAdjacentElement('afterend', notification);
            }
            
            setTimeout(function() {
                notification.style.transition = 'opacity 0.3s';
                notification.style.opacity = '0';
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.remove();
                    }
                }, 300);
            }, 3000);
        }
    };

    // Inicjalizacja
    ready(function() {
        if (typeof wpmzf_companies !== 'undefined') {
            Companies.init();
        }
    });

})();
