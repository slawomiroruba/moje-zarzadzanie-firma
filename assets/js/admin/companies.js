/**
 * Companies Management JavaScript
 *
 * @package WPMZF
 */

(function($) {
    'use strict';

    var Companies = {
        init: function() {
            this.bindEvents();
            this.initDataTable();
        },

        bindEvents: function() {
            $(document).on('click', '.add-company', this.showAddForm);
            $(document).on('click', '.edit-company', this.showEditForm);
            $(document).on('click', '.delete-company', this.deleteCompany);
            $(document).on('submit', '#company-form', this.saveCompany);
            $(document).on('click', '.company-card', this.showCompanyDetails);
        },

        initDataTable: function() {
            if ($('#companies-table').length > 0) {
                $('#companies-table').DataTable({
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
            Companies.openModal('add');
        },

        showEditForm: function() {
            var companyId = $(this).data('company-id');
            Companies.openModal('edit', companyId);
        },

        openModal: function(action, companyId = null) {
            var modal = $('#company-modal');
            var form = $('#company-form');
            
            if (action === 'edit' && companyId) {
                Companies.loadCompanyData(companyId);
                modal.find('.modal-title').text('Edytuj firmę');
            } else {
                form[0].reset();
                modal.find('.modal-title').text('Dodaj firmę');
                $('#company-id').val('');
            }
            
            modal.show();
        },

        loadCompanyData: function(companyId) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpmzf_get_company',
                    company_id: companyId,
                    nonce: wpmzf_companies.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var company = response.data;
                        $('#company-id').val(company.id);
                        $('#company-name').val(company.name);
                        $('#company-nip').val(company.nip);
                        $('#company-address').val(company.address);
                        $('#company-phone').val(company.phone);
                        $('#company-email').val(company.email);
                        $('#company-website').val(company.website);
                    }
                }
            });
        },

        saveCompany: function(e) {
            e.preventDefault();
            
            var form = $(this);
            var formData = form.serialize();
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData + '&action=wpmzf_save_company&nonce=' + wpmzf_companies.nonce,
                beforeSend: function() {
                    form.find('button[type="submit"]').prop('disabled', true);
                },
                success: function(response) {
                    if (response.success) {
                        $('#company-modal').hide();
                        Companies.showNotification('Firma została zapisana', 'success');
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        Companies.showNotification('Błąd: ' + response.data, 'error');
                    }
                },
                complete: function() {
                    form.find('button[type="submit"]').prop('disabled', false);
                }
            });
        },

        deleteCompany: function() {
            var companyId = $(this).data('company-id');
            var companyName = $(this).closest('.company-card').find('h3').text();
            
            if (confirm('Czy na pewno chcesz usunąć firmę "' + companyName + '"?')) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wpmzf_delete_company',
                        company_id: companyId,
                        nonce: wpmzf_companies.nonce
                    },
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

        showCompanyDetails: function() {
            var companyId = $(this).data('company-id');
            window.location.href = wpmzf_companies.company_view_url + '&company_id=' + companyId;
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
        if (typeof wpmzf_companies !== 'undefined') {
            Companies.init();
        }
    });

    // Modal events
    $(document).on('click', '.modal-close, .cancel-company', function() {
        $('#company-modal').hide();
    });

    // Click outside modal to close
    $(document).on('click', '.luna-crm-modal', function(e) {
        if (e.target === this) {
            $(this).hide();
        }
    });

})(jQuery);
