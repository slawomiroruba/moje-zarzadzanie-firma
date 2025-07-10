<?php
/**
 * Widok zarządzania projektami
 *
 * @package WPMZF
 * @subpackage Admin/Views
 */

if (!defined('ABSPATH')) {
    exit;
}

// Pobieranie projektów
$projects = WPMZF_Project::get_projects();
$companies = WPMZF_Company::get_companies();
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="luna-crm-projects">
        <div class="luna-crm-header">
            <button class="button button-primary" id="add-project">
                <span class="dashicons dashicons-plus"></span> Dodaj projekt
            </button>
        </div>
        
        <div class="luna-crm-projects-grid">
            <?php if (!empty($projects)): ?>
                <?php foreach ($projects as $project): ?>
                    <div class="luna-crm-project-card" data-project-id="<?php echo $project->id; ?>">
                        <div class="project-header">
                            <h3><?php echo esc_html($project->name); ?></h3>
                            <div class="project-actions">
                                <button class="button button-small edit-project" data-project-id="<?php echo $project->id; ?>">
                                    <span class="dashicons dashicons-edit"></span>
                                </button>
                                <button class="button button-small delete-project" data-project-id="<?php echo $project->id; ?>">
                                    <span class="dashicons dashicons-trash"></span>
                                </button>
                            </div>
                        </div>
                        
                        <div class="project-details">
                            <?php if ($project->description): ?>
                                <p><?php echo esc_html(wp_trim_words($project->description, 20)); ?></p>
                            <?php endif; ?>
                            
                            <?php if ($project->budget): ?>
                                <p><strong>Budżet:</strong> <?php echo esc_html($project->budget); ?></p>
                            <?php endif; ?>
                            
                            <?php if ($project->start_date): ?>
                                <p><strong>Data rozpoczęcia:</strong> <?php echo esc_html($project->start_date); ?></p>
                            <?php endif; ?>
                            
                            <?php if ($project->end_date): ?>
                                <p><strong>Data zakończenia:</strong> <?php echo esc_html($project->end_date); ?></p>
                            <?php endif; ?>
                            
                            <?php 
                            $company = $project->get_company();
                            if ($company): ?>
                                <p><strong>Firma:</strong> <?php echo esc_html($company->name); ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="project-stats">
                            <?php
                            $time_service = new WPMZF_Time_Tracking();
                            $stats = $time_service->get_project_time_stats($project->id);
                            ?>
                            <span class="stat-item">
                                <span class="dashicons dashicons-clock"></span>
                                <?php echo $stats['total_hours']; ?>h
                            </span>
                            
                            <span class="stat-item">
                                <span class="dashicons dashicons-list-view"></span>
                                <?php echo $stats['entries_count']; ?> wpisów
                            </span>
                            
                            <span class="project-status status-<?php echo esc_attr($project->status); ?>">
                                <?php echo esc_html($project->status ?: 'Nieznany'); ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="luna-crm-empty-state">
                    <span class="dashicons dashicons-portfolio"></span>
                    <h3>Brak projektów</h3>
                    <p>Dodaj pierwszy projekt do swojego CRM</p>
                    <button class="button button-primary" id="add-first-project">Dodaj projekt</button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal dodawania/edycji projektu -->
<div id="project-modal" class="luna-crm-modal" style="display: none;">
    <div class="luna-crm-modal-content">
        <div class="luna-crm-modal-header">
            <h2 id="modal-title">Dodaj projekt</h2>
            <button class="luna-crm-modal-close">&times;</button>
        </div>
        
        <form id="project-form">
            <div class="luna-crm-form-group">
                <label for="project-name">Nazwa projektu *</label>
                <input type="text" id="project-name" name="name" required>
            </div>
            
            <div class="luna-crm-form-group">
                <label for="project-description">Opis</label>
                <textarea id="project-description" name="description" rows="4"></textarea>
            </div>
            
            <div class="luna-crm-form-group">
                <label for="project-company">Firma</label>
                <select id="project-company" name="company_id">
                    <option value="">Wybierz firmę</option>
                    <?php foreach ($companies as $company): ?>
                        <option value="<?php echo $company->id; ?>"><?php echo esc_html($company->name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="luna-crm-form-group">
                <label for="project-budget">Budżet</label>
                <input type="text" id="project-budget" name="budget">
            </div>
            
            <div class="luna-crm-form-group">
                <label for="project-start-date">Data rozpoczęcia</label>
                <input type="date" id="project-start-date" name="start_date">
            </div>
            
            <div class="luna-crm-form-group">
                <label for="project-end-date">Data zakończenia</label>
                <input type="date" id="project-end-date" name="end_date">
            </div>
            
            <div class="luna-crm-form-group">
                <label for="project-status">Status</label>
                <select id="project-status" name="status">
                    <option value="planning">Planowanie</option>
                    <option value="active">Aktywny</option>
                    <option value="on-hold">Wstrzymany</option>
                    <option value="completed">Zakończony</option>
                    <option value="cancelled">Anulowany</option>
                </select>
            </div>
            
            <div class="luna-crm-form-actions">
                <button type="submit" class="button button-primary">Zapisz</button>
                <button type="button" class="button cancel-project">Anuluj</button>
            </div>
            
            <input type="hidden" id="project-id" name="id">
        </form>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    var modal = $('#project-modal');
    var form = $('#project-form');
    var isEditing = false;
    
    // Otwórz modal dodawania
    $('#add-project, #add-first-project').click(function() {
        openModal('add');
    });
    
    // Otwórz modal edycji
    $(document).on('click', '.edit-project', function() {
        var projectId = $(this).data('project-id');
        openModal('edit', projectId);
    });
    
    // Usuń projekt
    $(document).on('click', '.delete-project', function() {
        var projectId = $(this).data('project-id');
        if (confirm('Czy na pewno chcesz usunąć ten projekt?')) {
            deleteProject(projectId);
        }
    });
    
    // Zamknij modal
    $('.luna-crm-modal-close, .cancel-project').click(function() {
        closeModal();
    });
    
    // Zapisz projekt
    form.submit(function(e) {
        e.preventDefault();
        saveProject();
    });
    
    function openModal(action, projectId = null) {
        isEditing = action === 'edit';
        
        if (isEditing) {
            $('#modal-title').text('Edytuj projekt');
            loadProjectData(projectId);
        } else {
            $('#modal-title').text('Dodaj projekt');
            form[0].reset();
            $('#project-id').val('');
        }
        
        modal.show();
    }
    
    function closeModal() {
        modal.hide();
        form[0].reset();
        isEditing = false;
    }
    
    function loadProjectData(projectId) {
        $('#project-id').val(projectId);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wpmzf_get_project',
                project_id: projectId,
                nonce: '<?php echo wp_create_nonce("wpmzf_nonce"); ?>'
            },
            success: function(response) {
                if (response.success) {
                    var project = response.data;
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
    }
    
    function saveProject() {
        var formData = form.serialize();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData + '&action=wpmzf_save_project&nonce=' + '<?php echo wp_create_nonce("wpmzf_nonce"); ?>',
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
    
    function deleteProject(projectId) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wpmzf_delete_project',
                project_id: projectId,
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
