<?php
/**
 * Widok zarządzania osobami
 *
 * @package WPMZF
 * @subpackage Admin/Views
 */

if (!defined('ABSPATH')) {
    exit;
}

// Pobieranie osób
$persons = WPMZF_Person::get_persons();
$companies = WPMZF_Company::get_companies();
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="luna-crm-persons">
        <div class="luna-crm-header">
            <button class="button button-primary" id="add-person">
                <span class="dashicons dashicons-plus"></span> Dodaj osobę
            </button>
        </div>
        
        <div class="luna-crm-persons-grid">
            <?php if (!empty($persons)): ?>
                <?php foreach ($persons as $person): ?>
                    <div class="luna-crm-person-card" data-person-id="<?php echo $person->id; ?>">
                        <div class="person-header">
                            <h3><?php echo esc_html($person->get_full_name()); ?></h3>
                            <div class="person-actions">
                                <button class="button button-small edit-person" data-person-id="<?php echo $person->id; ?>">
                                    <span class="dashicons dashicons-edit"></span>
                                </button>
                                <button class="button button-small delete-person" data-person-id="<?php echo $person->id; ?>">
                                    <span class="dashicons dashicons-trash"></span>
                                </button>
                            </div>
                        </div>
                        
                        <div class="person-details">
                            <?php if ($person->position): ?>
                                <p><strong>Stanowisko:</strong> <?php echo esc_html($person->position); ?></p>
                            <?php endif; ?>
                            
                            <?php if ($person->email): ?>
                                <p><strong>Email:</strong> <a href="mailto:<?php echo esc_attr($person->email); ?>"><?php echo esc_html($person->email); ?></a></p>
                            <?php endif; ?>
                            
                            <?php if ($person->phone): ?>
                                <p><strong>Telefon:</strong> <?php echo esc_html($person->phone); ?></p>
                            <?php endif; ?>
                            
                            <?php 
                            $company = $person->get_company();
                            if ($company): ?>
                                <p><strong>Firma:</strong> <?php echo esc_html($company->name); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="luna-crm-empty-state">
                    <span class="dashicons dashicons-groups"></span>
                    <h3>Brak osób</h3>
                    <p>Dodaj pierwszą osobę do swojego CRM</p>
                    <button class="button button-primary" id="add-first-person">Dodaj osobę</button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal dodawania/edycji osoby -->
<div id="person-modal" class="luna-crm-modal" style="display: none;">
    <div class="luna-crm-modal-content">
        <div class="luna-crm-modal-header">
            <h2 id="modal-title">Dodaj osobę</h2>
            <button class="luna-crm-modal-close">&times;</button>
        </div>
        
        <form id="person-form">
            <div class="luna-crm-form-group">
                <label for="person-first-name">Imię *</label>
                <input type="text" id="person-first-name" name="first_name" required>
            </div>
            
            <div class="luna-crm-form-group">
                <label for="person-last-name">Nazwisko *</label>
                <input type="text" id="person-last-name" name="last_name" required>
            </div>
            
            <div class="luna-crm-form-group">
                <label for="person-email">Email</label>
                <input type="email" id="person-email" name="email">
            </div>
            
            <div class="luna-crm-form-group">
                <label for="person-phone">Telefon</label>
                <input type="text" id="person-phone" name="phone">
            </div>
            
            <div class="luna-crm-form-group">
                <label for="person-position">Stanowisko</label>
                <input type="text" id="person-position" name="position">
            </div>
            
            <div class="luna-crm-form-group">
                <label for="person-company">Firma</label>
                <select id="person-company" name="company_id">
                    <option value="">Wybierz firmę</option>
                    <?php foreach ($companies as $company): ?>
                        <option value="<?php echo $company->id; ?>"><?php echo esc_html($company->name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="luna-crm-form-actions">
                <button type="submit" class="button button-primary">Zapisz</button>
                <button type="button" class="button cancel-person">Anuluj</button>
            </div>
            
            <input type="hidden" id="person-id" name="id">
        </form>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    var modal = $('#person-modal');
    var form = $('#person-form');
    var isEditing = false;
    
    // Otwórz modal dodawania
    $('#add-person, #add-first-person').click(function() {
        openModal('add');
    });
    
    // Otwórz modal edycji
    $(document).on('click', '.edit-person', function() {
        var personId = $(this).data('person-id');
        openModal('edit', personId);
    });
    
    // Usuń osobę
    $(document).on('click', '.delete-person', function() {
        var personId = $(this).data('person-id');
        if (confirm('Czy na pewno chcesz usunąć tę osobę?')) {
            deletePerson(personId);
        }
    });
    
    // Zamknij modal
    $('.luna-crm-modal-close, .cancel-person').click(function() {
        closeModal();
    });
    
    // Zapisz osobę
    form.submit(function(e) {
        e.preventDefault();
        savePerson();
    });
    
    function openModal(action, personId = null) {
        isEditing = action === 'edit';
        
        if (isEditing) {
            $('#modal-title').text('Edytuj osobę');
            loadPersonData(personId);
        } else {
            $('#modal-title').text('Dodaj osobę');
            form[0].reset();
            $('#person-id').val('');
        }
        
        modal.show();
    }
    
    function closeModal() {
        modal.hide();
        form[0].reset();
        isEditing = false;
    }
    
    function loadPersonData(personId) {
        $('#person-id').val(personId);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wpmzf_get_person',
                person_id: personId,
                nonce: '<?php echo wp_create_nonce("wpmzf_nonce"); ?>'
            },
            success: function(response) {
                if (response.success) {
                    var person = response.data;
                    $('#person-first-name').val(person.first_name);
                    $('#person-last-name').val(person.last_name);
                    $('#person-email').val(person.email);
                    $('#person-phone').val(person.phone);
                    $('#person-position').val(person.position);
                    $('#person-company').val(person.company_id);
                }
            }
        });
    }
    
    function savePerson() {
        var formData = form.serialize();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData + '&action=wpmzf_save_person&nonce=' + '<?php echo wp_create_nonce("wpmzf_nonce"); ?>',
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
    
    function deletePerson(personId) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wpmzf_delete_person',
                person_id: personId,
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
