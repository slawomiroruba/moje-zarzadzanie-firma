<?php
/**
 * Widok zarządzania firmami
 *
 * @package WPMZF
 * @subpackage Admin/Views
 */

if (!defined('ABSPATH')) {
    exit;
}

// Pobieranie firm
$companies = WPMZF_Company::get_companies();
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="luna-crm-companies">
        <div class="luna-crm-header">
            <button class="button button-primary" id="add-company">
                <span class="dashicons dashicons-plus"></span> Dodaj firmę
            </button>
        </div>
        
        <div class="luna-crm-companies-grid">
            <?php if (!empty($companies)): ?>
                <?php foreach ($companies as $company): ?>
                    <div class="luna-crm-company-card" data-company-id="<?php echo $company->id; ?>">
                        <div class="company-header">
                            <h3><?php echo esc_html($company->name); ?></h3>
                            <div class="company-actions">
                                <button class="button button-small edit-company" data-company-id="<?php echo $company->id; ?>">
                                    <span class="dashicons dashicons-edit"></span>
                                </button>
                                <button class="button button-small delete-company" data-company-id="<?php echo $company->id; ?>">
                                    <span class="dashicons dashicons-trash"></span>
                                </button>
                            </div>
                        </div>
                        
                        <div class="company-details">
                            <?php if ($company->nip): ?>
                                <p><strong>NIP:</strong> <?php echo esc_html($company->nip); ?></p>
                            <?php endif; ?>
                            
                            <?php if ($company->phone): ?>
                                <p><strong>Telefon:</strong> <?php echo esc_html($company->phone); ?></p>
                            <?php endif; ?>
                            
                            <?php if ($company->email): ?>
                                <p><strong>Email:</strong> <a href="mailto:<?php echo esc_attr($company->email); ?>"><?php echo esc_html($company->email); ?></a></p>
                            <?php endif; ?>
                            
                            <?php if ($company->website): ?>
                                <p><strong>Strona:</strong> <a href="<?php echo esc_url($company->website); ?>" target="_blank"><?php echo esc_html($company->website); ?></a></p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="company-stats">
                            <?php
                            $persons_count = count(WPMZF_Person::get_persons(array(
                                'meta_query' => array(
                                    array(
                                        'key' => 'company_id',
                                        'value' => $company->id,
                                        'compare' => '='
                                    )
                                )
                            )));
                            
                            $projects_count = count(WPMZF_Project::get_projects(array(
                                'meta_query' => array(
                                    array(
                                        'key' => 'company_id',
                                        'value' => $company->id,
                                        'compare' => '='
                                    )
                                )
                            )));
                            ?>
                            
                            <span class="stat-item">
                                <span class="dashicons dashicons-groups"></span>
                                <?php echo $persons_count; ?> osób
                            </span>
                            
                            <span class="stat-item">
                                <span class="dashicons dashicons-portfolio"></span>
                                <?php echo $projects_count; ?> projektów
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="luna-crm-empty-state">
                    <span class="dashicons dashicons-building"></span>
                    <h3>Brak firm</h3>
                    <p>Dodaj pierwszą firmę do swojego CRM</p>
                    <button class="button button-primary" id="add-first-company">Dodaj firmę</button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal dodawania/edycji firmy -->
<div id="company-modal" class="luna-crm-modal" style="display: none;">
    <div class="luna-crm-modal-content">
        <div class="luna-crm-modal-header">
            <h2 id="modal-title">Dodaj firmę</h2>
            <button class="luna-crm-modal-close">&times;</button>
        </div>
        
        <form id="company-form">
            <div class="luna-crm-form-group">
                <label for="company-name">Nazwa firmy *</label>
                <input type="text" id="company-name" name="name" required>
            </div>
            
            <div class="luna-crm-form-group">
                <label for="company-nip">NIP</label>
                <input type="text" id="company-nip" name="nip">
            </div>
            
            <div class="luna-crm-form-group">
                <label for="company-address">Adres</label>
                <textarea id="company-address" name="address" rows="3"></textarea>
            </div>
            
            <div class="luna-crm-form-group">
                <label for="company-phone">Telefon</label>
                <input type="text" id="company-phone" name="phone">
            </div>
            
            <div class="luna-crm-form-group">
                <label for="company-email">Email</label>
                <input type="email" id="company-email" name="email">
            </div>
            
            <div class="luna-crm-form-group">
                <label for="company-website">Strona internetowa</label>
                <input type="url" id="company-website" name="website">
            </div>
            
            <div class="luna-crm-form-actions">
                <button type="submit" class="button button-primary">Zapisz</button>
                <button type="button" class="button cancel-company">Anuluj</button>
            </div>
            
            <input type="hidden" id="company-id" name="id">
        </form>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    var modal = $('#company-modal');
    var form = $('#company-form');
    var isEditing = false;
    
    // Otwórz modal dodawania
    $('#add-company, #add-first-company').click(function() {
        openModal('add');
    });
    
    // Otwórz modal edycji
    $(document).on('click', '.edit-company', function() {
        var companyId = $(this).data('company-id');
        openModal('edit', companyId);
    });
    
    // Usuń firmę
    $(document).on('click', '.delete-company', function() {
        var companyId = $(this).data('company-id');
        if (confirm('Czy na pewno chcesz usunąć tę firmę?')) {
            deleteCompany(companyId);
        }
    });
    
    // Zamknij modal
    $('.luna-crm-modal-close, .cancel-company').click(function() {
        closeModal();
    });
    
    // Zapisz firmę
    form.submit(function(e) {
        e.preventDefault();
        saveCompany();
    });
    
    function openModal(action, companyId = null) {
        isEditing = action === 'edit';
        
        if (isEditing) {
            $('#modal-title').text('Edytuj firmę');
            loadCompanyData(companyId);
        } else {
            $('#modal-title').text('Dodaj firmę');
            form[0].reset();
            $('#company-id').val('');
        }
        
        modal.show();
    }
    
    function closeModal() {
        modal.hide();
        form[0].reset();
        isEditing = false;
    }
    
    function loadCompanyData(companyId) {
        // W rzeczywistej implementacji pobierz dane z AJAX
        // Na razie mock data
        $('#company-id').val(companyId);
        
        // Tutaj można dodać AJAX żeby pobrać dane firmy
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wpmzf_get_company',
                company_id: companyId,
                nonce: '<?php echo wp_create_nonce("wpmzf_nonce"); ?>'
            },
            success: function(response) {
                if (response.success) {
                    var company = response.data;
                    $('#company-name').val(company.name);
                    $('#company-nip').val(company.nip);
                    $('#company-address').val(company.address);
                    $('#company-phone').val(company.phone);
                    $('#company-email').val(company.email);
                    $('#company-website').val(company.website);
                }
            }
        });
    }
    
    function saveCompany() {
        var formData = form.serialize();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData + '&action=wpmzf_save_company&nonce=' + '<?php echo wp_create_nonce("wpmzf_nonce"); ?>',
            success: function(response) {
                if (response.success) {
                    closeModal();
                    location.reload();
                } else {
                    alert('Błąd: ' + response.data);
                }
            }
        });
    }
    
    function deleteCompany(companyId) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wpmzf_delete_company',
                company_id: companyId,
                nonce: '<?php echo wp_create_nonce("wpmzf_nonce"); ?>'
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Błąd: ' + response.data);
                }
            }
        });
    }
});
</script>
