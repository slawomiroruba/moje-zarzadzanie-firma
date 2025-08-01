<?php
/**
 * Widok pojedynczego projektu/zlecenia
 * 
 * @package WPMZF
 * @subpackage Admin/Views
 */

if (!defined('ABSPATH')) {
    exit;
}

// Sprawdź czy projekt istnieje
if (!$project || $project->post_type !== 'project') {
    echo '<div class="wrap"><h1>Błąd</h1><p>Projekt nie został znaleziony.</p></div>';
    return;
}

// Pobierz dane projektu
$project_id = $project->ID;
$project_title = $project->post_title;
$project_content = $project->post_content;
$project_date = get_the_date('j F Y', $project);

// Pobierz pola ACF
$project_fields = get_fields($project_id) ?: [];
$project_status = $project_fields['project_status'] ?? 'Planowanie';
$start_date = $project_fields['start_date'] ?? '';
$end_date = $project_fields['end_date'] ?? '';
$project_company = $project_fields['project_company'] ?? null;
$project_person = $project_fields['project_person'] ?? null;

// Renderuj nawigację i header
WPMZF_View_Helper::render_complete_header(array(
    'title' => $project_title,
    'subtitle' => 'Szczegółowe informacje o projekcie',
    'breadcrumbs' => array(
        array('label' => 'Dashboard', 'url' => admin_url('admin.php?page=wpmzf_dashboard')),
        array('label' => 'Projekty', 'url' => admin_url('admin.php?page=wpmzf_projects')),
        array('label' => $project_title, 'url' => '')
    ),
    'actions' => array(
        array(
            'label' => 'Edytuj projekt',
            'url' => admin_url('post.php?post=' . $project_id . '&action=edit'),
            'icon' => '✏️',
            'class' => 'button button-primary'
        ),
        array(
            'label' => 'Dodaj zadanie',
            'url' => admin_url('post-new.php?post_type=task'),
            'icon' => '✅',
            'class' => 'button'
        ),
        array(
            'label' => 'Dodaj wpis czasu',
            'url' => admin_url('post-new.php?post_type=time_entry'),
            'icon' => '⏱️',
            'class' => 'button'
        )
    )
));

// Sprawdź czy istnieje pole budżetu
$budget = get_field('project_budget', $project_id) ?: get_post_meta($project_id, 'budget', true);
$budget = $budget ?: 0; // Zapewni że budget ma zawsze wartość liczbową

// Pobierz przypisane firmy
$assigned_companies = [];
if ($project_company) {
    if (is_array($project_company)) {
        $assigned_companies = $project_company;
    } else {
        $assigned_companies = [$project_company];
    }
}

// Pobierz przypisane osoby
$assigned_persons = [];
if ($project_person) {
    if (is_array($project_person)) {
        $assigned_persons = $project_person;
    } else {
        $assigned_persons = [$project_person];
    }
}

// Pobierz zadania przypisane do projektu
$tasks_args = [
    'post_type' => 'task',
    'posts_per_page' => -1,
    'meta_query' => [
        [
            'key' => 'task_project',
            'value' => '"' . $project_id . '"',
            'compare' => 'LIKE'
        ]
    ],
    'orderby' => 'date',
    'order' => 'DESC'
];

$tasks_query = new WP_Query($tasks_args);
$open_tasks = [];
$closed_tasks = [];

if ($tasks_query->have_posts()) {
    while ($tasks_query->have_posts()) {
        $tasks_query->the_post();
        $task_id = get_the_ID();
        $task_status = get_field('task_status', $task_id) ?: 'Do zrobienia';
        $task_data = [
            'id' => $task_id,
            'title' => get_the_title(),
            'content' => get_the_content(),
            'status' => $task_status,
            'start_date' => get_field('task_start_date', $task_id),
            'end_date' => get_field('task_end_date', $task_id),
            'assigned_person' => get_field('task_assigned_person', $task_id),
            'assigned_company' => get_field('task_assigned_company', $task_id),
            'employee' => get_field('task_employee', $task_id),
        ];
        
        if ($task_status === 'Zrobione') {
            $closed_tasks[] = $task_data;
        } else {
            $open_tasks[] = $task_data;
        }
    }
    wp_reset_postdata();
}

// Pobierz aktywności związane z projektem
$activities_args = [
    'post_type' => 'activity',
    'posts_per_page' => -1,
    'meta_query' => [
        [
            'key' => 'related_project',
            'value' => $project_id,
            'compare' => '='
        ]
    ],
    'orderby' => 'date',
    'order' => 'DESC'
];

$activities_query = new WP_Query($activities_args);
?>

<style>
    /* Project View Styles - Professional and consistent with person/company views */
    .project-view-wrap {
        max-width: 1400px;
        margin: 20px auto;
        padding: 0 20px;
    }

    /* Project Header */
    .project-header {
        background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);
        border: 1px solid #e1e5e9;
        border-radius: 12px;
        padding: 28px 32px;
        margin-bottom: 28px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        position: relative;
        overflow: hidden;
    }

    .project-header::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #2271b1 0%, #1e90ff 50%, #00bcd4 100%);
    }

    .project-header-content {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 24px;
    }

    .project-title-section h1 {
        margin: 0 0 12px 0;
        font-size: 32px;
        font-weight: 700;
        color: #1d2327;
        line-height: 1.2;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
    }

    .project-status-badge {
        display: inline-block;
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 8px;
    }

    .project-status-badge.planowanie {
        background: #fff2cc;
        color: #996f00;
        border: 1px solid #f0d000;
    }

    .project-status-badge.w-toku {
        background: #cce5ff;
        color: #0073aa;
        border: 1px solid #0085ba;
    }

    .project-status-badge.zakonczony {
        background: #d7eddb;
        color: #2d7d3a;
        border: 1px solid #68de7c;
    }

    .project-status-badge.wstrzymany {
        background: #fce2e6;
        color: #d63638;
        border: 1px solid #dc3232;
    }

    .project-meta {
        font-size: 14px;
        color: #646970;
        line-height: 1.5;
    }

    .project-actions {
        display: flex;
        gap: 12px;
        align-items: flex-start;
    }

    .btn-edit-project {
        background: #2271b1;
        color: #fff;
        border: none;
        padding: 10px 20px;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .btn-edit-project:hover {
        background: #135e96;
        transform: translateY(-1px);
        box-shadow: 0 3px 8px rgba(34, 113, 177, 0.3);
        color: #fff;
        text-decoration: none;
    }

    /* Project Content Grid */
    .project-content-grid {
        display: grid;
        grid-template-columns: 400px 1fr;
        gap: 28px;
        margin-bottom: 28px;
    }

    /* Sidebar */
    .project-sidebar {
        display: flex;
        flex-direction: column;
        gap: 24px;
    }

    .sidebar-section {
        background: #fff;
        border: 1px solid #e1e5e9;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        overflow: hidden;
    }

    .sidebar-section-header {
        background: #f8f9fa;
        padding: 16px 20px;
        border-bottom: 1px solid #e1e5e9;
        font-size: 14px;
        font-weight: 600;
        color: #1d2327;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .sidebar-section-content {
        padding: 20px;
    }

    .detail-item {
        margin-bottom: 16px;
    }

    .detail-item:last-child {
        margin-bottom: 0;
    }

    .detail-item strong {
        color: #1d2327;
        font-weight: 600;
        display: block;
        margin-bottom: 4px;
        font-size: 13px;
    }

    .budget-amount {
        font-size: 18px;
        font-weight: 700;
        color: #2d7d3a;
    }

    /* Entity Lists (Companies/Persons) */
    .entity-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .entity-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 0;
        border-bottom: 1px solid #f0f0f1;
    }

    .entity-item:last-child {
        border-bottom: none;
    }

    .entity-link {
        color: #2271b1;
        text-decoration: none;
        font-weight: 500;
        flex: 1;
    }

    .entity-link:hover {
        color: #135e96;
        text-decoration: underline;
    }

    .no-entities {
        color: #8c8f94;
        font-style: italic;
        text-align: center;
        padding: 20px;
        margin: 0;
    }

    /* Main Content Area */
    .project-main-content {
        display: flex;
        flex-direction: column;
        gap: 24px;
    }

    .content-section {
        background: #fff;
        border: 1px solid #e1e5e9;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        overflow: hidden;
    }

    .content-section-header {
        background: #f8f9fa;
        padding: 16px 20px;
        border-bottom: 1px solid #e1e5e9;
        font-size: 14px;
        font-weight: 600;
        color: #1d2327;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .content-section-body {
        padding: 20px;
    }

    /* Project Description */
    .project-description {
        color: #1d2327;
        line-height: 1.6;
        font-size: 15px;
    }

    .project-description p {
        margin-bottom: 16px;
    }

    .project-description p:last-child {
        margin-bottom: 0;
    }

    /* Tasks Section */
    .tasks-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }

    .tasks-tabs {
        display: flex;
        gap: 2px;
        background: #f0f0f1;
        border-radius: 6px;
        padding: 2px;
    }

    .tasks-tab {
        padding: 8px 16px;
        border: none;
        background: transparent;
        color: #646970;
        border-radius: 4px;
        cursor: pointer;
        font-size: 13px;
        font-weight: 500;
        transition: all 0.2s ease;
    }

    .tasks-tab.active {
        background: #fff;
        color: #1d2327;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .btn-add-task {
        background: #00a32a;
        color: #fff;
        border: none;
        padding: 8px 16px;
        border-radius: 4px;
        font-size: 13px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .btn-add-task:hover {
        background: #008a00;
        transform: translateY(-1px);
        box-shadow: 0 2px 6px rgba(0, 163, 42, 0.3);
    }

    .task-item {
        border: 1px solid #e1e5e9;
        border-radius: 6px;
        padding: 16px;
        margin-bottom: 12px;
        background: #fff;
        transition: all 0.2s ease;
    }

    .task-item:hover {
        border-color: #d0d5dd;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
    }

    .task-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 8px;
    }

    .task-title {
        font-weight: 600;
        color: #1d2327;
        margin: 0;
        font-size: 15px;
    }

    .task-status {
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    .task-status.do-zrobienia {
        background: #fff2cc;
        color: #996f00;
    }

    .task-status.w-toku {
        background: #cce5ff;
        color: #0073aa;
    }

    .task-status.zrobione {
        background: #d7eddb;
        color: #2d7d3a;
    }

    .task-meta {
        color: #646970;
        font-size: 13px;
        margin-top: 8px;
    }

    .no-items {
        color: #8c8f94;
        font-style: italic;
        text-align: center;
        padding: 40px 20px;
        margin: 0;
    }

    /* Activity Timeline */
    .timeline-container {
        padding: 0;
    }

    .timeline-item {
        display: flex;
        gap: 16px;
        margin-bottom: 24px;
        padding-bottom: 24px;
        border-bottom: 1px solid #e1e5e9;
        position: relative;
    }

    .timeline-item:last-child {
        border-bottom: none;
        margin-bottom: 0;
        padding-bottom: 0;
    }

    .timeline-avatar {
        flex-shrink: 0;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        overflow: hidden;
        border: 2px solid #e1e5e9;
    }

    .timeline-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .timeline-content {
        flex: 1;
        min-width: 0;
    }

    .timeline-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 12px;
    }

    .timeline-header-left {
        flex: 1;
    }

    .timeline-header-meta {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 4px;
    }

    .timeline-header-meta .dashicons {
        color: #646970;
        font-size: 16px;
    }

    .timeline-header-meta span {
        color: #1d2327;
        font-size: 14px;
    }

    .timeline-header-date {
        color: #646970;
        font-size: 12px;
    }

    .timeline-actions {
        display: flex;
        gap: 8px;
    }

    .timeline-actions .dashicons {
        color: #646970;
        cursor: pointer;
        padding: 4px;
        border-radius: 3px;
        transition: all 0.2s ease;
    }

    .timeline-actions .dashicons:hover {
        color: #2271b1;
        background: #f0f6fc;
    }

    .timeline-body {
        color: #1d2327;
        line-height: 1.5;
        font-size: 14px;
    }

    .activity-related {
        margin-top: 12px;
        padding-top: 12px;
        border-top: 1px solid #e3e5e8;
        font-size: 13px;
        color: #646970;
    }

    .activity-related a {
        color: #2271b1;
        text-decoration: none;
    }

    .activity-related a:hover {
        text-decoration: underline;
    }

    /* Edit Forms */
    .edit-form {
        display: none;
        background: #f8f9fa;
        border: 1px solid #e1e5e9;
        border-radius: 6px;
        padding: 20px;
        margin-top: 16px;
    }

    .edit-form.active {
        display: block;
    }

    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
        margin-bottom: 20px;
    }

    .form-field {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .form-field.full-width {
        grid-column: 1 / -1;
    }

    .form-field label {
        font-weight: 600;
        color: #1d2327;
        font-size: 13px;
    }

    .form-field input,
    .form-field select,
    .form-field textarea {
        padding: 8px 12px;
        border: 1px solid #d0d5dd;
        border-radius: 4px;
        font-size: 14px;
        transition: border-color 0.2s ease;
    }

    .form-field input:focus,
    .form-field select:focus,
    .form-field textarea:focus {
        outline: none;
        border-color: #2271b1;
        box-shadow: 0 0 0 1px #2271b1;
    }

    .form-field textarea {
        min-height: 100px;
        resize: vertical;
    }

    .form-actions {
        display: flex;
        gap: 12px;
        justify-content: flex-end;
    }

    .btn {
        padding: 8px 16px;
        border-radius: 4px;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border: 1px solid transparent;
    }

    .btn-primary {
        background: #2271b1;
        color: #fff;
        border-color: #2271b1;
    }

    .btn-primary:hover {
        background: #135e96;
        border-color: #135e96;
        color: #fff;
        text-decoration: none;
    }

    .btn-secondary {
        background: #f6f7f7;
        color: #646970;
        border-color: #d0d5dd;
    }

    .btn-secondary:hover {
        background: #e9ecef;
        color: #1d2327;
        text-decoration: none;
    }

    .btn-success {
        background: #00a32a;
        color: #fff;
        border-color: #00a32a;
    }

    .btn-success:hover {
        background: #008a00;
        border-color: #008a00;
        color: #fff;
        text-decoration: none;
    }

    .btn-sm {
        padding: 4px 8px;
        font-size: 12px;
    }

    /* Responsive Design */
    @media screen and (max-width: 1200px) {
        .project-content-grid {
            grid-template-columns: 1fr;
            gap: 24px;
        }
        
        .project-header-content {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .form-grid {
            grid-template-columns: 1fr;
        }
    }

    @media screen and (max-width: 768px) {
        .project-view-wrap {
            padding: 0 16px;
        }
        
        .project-header {
            padding: 20px 24px;
        }
        
        .project-title-section h1 {
            font-size: 24px;
        }
        
        .tasks-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 12px;
        }
    }

    /* Loading and States */
    .loading {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: #646970;
        font-size: 14px;
    }

    .loading::before {
        content: '';
        width: 16px;
        height: 16px;
        border: 2px solid #e1e5e9;
        border-top-color: #2271b1;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }

    /* Success/Error Messages */
    .notice {
        padding: 12px 16px;
        border-radius: 6px;
        margin-bottom: 16px;
        font-size: 14px;
    }

    .notice-success {
        background: #d7eddb;
        color: #2d7d3a;
        border-left: 4px solid #00a32a;
    }

    .notice-error {
        background: #fce2e6;
        color: #d63638;
        border-left: 4px solid #dc3232;
    }

    .notice-warning {
        background: #fff2cc;
        color: #996f00;
        border-left: 4px solid #f0d000;
    }
</style>

<div class="wrap project-view-wrap">
    <!-- Project Header -->
    <div class="project-header">
        <div class="project-header-content">
            <div class="project-title-section">
                <div class="project-status-badge <?php echo sanitize_html_class(strtolower(str_replace(' ', '-', $project_status))); ?>">
                    <?php echo esc_html($project_status); ?>
                </div>
                <h1><?php echo esc_html($project_title); ?></h1>
                <div class="project-meta">
                    Utworzono: <?php echo esc_html($project_date); ?>
                    <?php if ($start_date): ?>
                        | Start: <?php echo esc_html(date('j F Y', strtotime($start_date))); ?>
                    <?php endif; ?>
                    <?php if ($end_date): ?>
                        | Koniec: <?php echo esc_html(date('j F Y', strtotime($end_date))); ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="project-actions">
                <button id="edit-basic-data" class="btn-edit-project">
                    <span class="dashicons dashicons-edit"></span>
                    Edytuj projekt
                </button>
            </div>
        </div>
    </div>

    <!-- Project Content Grid -->
    <div class="project-content-grid">
        <!-- Sidebar -->
        <div class="project-sidebar">
            <!-- Project Details -->
            <div class="sidebar-section">
                <div class="sidebar-section-header">
                    <span>Szczegóły projektu</span>
                </div>
                <div class="sidebar-section-content">
                    <div class="detail-item">
                        <strong>Status:</strong>
                        <span id="status-display"><?php echo esc_html($project_status); ?></span>
                    </div>
                    
                    <div class="detail-item">
                        <strong>Budżet:</strong>
                        <span class="budget-amount"><?php echo $budget > 0 ? esc_html(number_format((float)$budget, 2, ',', ' ')) . ' zł' : 'Nie określono'; ?></span>
                    </div>
                    
                    <?php if ($start_date): ?>
                        <div class="detail-item">
                            <strong>Data rozpoczęcia:</strong>
                            <span><?php echo esc_html(date('j F Y', strtotime($start_date))); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($end_date): ?>
                        <div class="detail-item">
                            <strong>Data zakończenia:</strong>
                            <span><?php echo esc_html(date('j F Y', strtotime($end_date))); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Assigned Companies -->
            <div class="sidebar-section">
                <div class="sidebar-section-header">
                    <span>Przypisane firmy</span>
                    <button class="btn btn-secondary btn-sm" id="edit-companies">
                        <span class="dashicons dashicons-edit"></span>
                    </button>
                </div>
                <div class="sidebar-section-content">
                    <div id="companies-display">
                        <?php if (!empty($assigned_companies)): ?>
                            <ul class="entity-list">
                                <?php foreach ($assigned_companies as $company_id): ?>
                                    <?php
                                    $company = get_post($company_id);
                                    if ($company):
                                    ?>
                                        <li class="entity-item">
                                            <a href="<?php echo esc_url(add_query_arg(['page' => 'wpmzf_view_company', 'company_id' => $company_id], admin_url('admin.php'))); ?>" class="entity-link">
                                                <?php echo esc_html($company->post_title); ?>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="no-entities">Brak przypisanych firm.</p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Company Edit Form -->
                    <div id="companies-edit-form" class="edit-form">
                        <?php
                        $field = get_field_object('project_company', $project_id);
                        if ($field):
                        ?>
                            <?php acf_form_data(); ?>
                            <div class="form-field">
                                <label for="project_companies">Przypisane firmy:</label>
                                <?php
                                acf_render_field([
                                    'key' => $field['key'],
                                    'name' => 'project_companies',
                                    'type' => 'relationship',
                                    'post_type' => ['company'],
                                    'return_format' => 'id',
                                    'multiple' => 1,
                                    'value' => $assigned_companies
                                ]);
                                ?>
                            </div>
                            <div class="form-actions">
                                <button type="button" class="btn btn-primary" id="save-companies">Zapisz</button>
                                <button type="button" class="btn btn-secondary" id="cancel-edit-companies">Anuluj</button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Assigned Persons -->
            <div class="sidebar-section">
                <div class="sidebar-section-header">
                    <span>Przypisane osoby</span>
                    <button class="btn btn-secondary btn-sm" id="edit-persons">
                        <span class="dashicons dashicons-edit"></span>
                    </button>
                </div>
                <div class="sidebar-section-content">
                    <div id="persons-display">
                        <?php if (!empty($assigned_persons)): ?>
                            <ul class="entity-list">
                                <?php foreach ($assigned_persons as $person_id): ?>
                                    <?php
                                    $person = get_post($person_id);
                                    if ($person):
                                    ?>
                                        <li class="entity-item">
                                            <a href="<?php echo esc_url(add_query_arg(['page' => 'wpmzf_view_person', 'person_id' => $person_id], admin_url('admin.php'))); ?>" class="entity-link">
                                                <?php echo esc_html($person->post_title); ?>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="no-entities">Brak przypisanych osób.</p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Person Edit Form -->
                    <div id="persons-edit-form" class="edit-form">
                        <?php
                        $field = get_field_object('project_person', $project_id);
                        if ($field):
                        ?>
                            <?php acf_form_data(); ?>
                            <div class="form-field">
                                <label for="project_persons">Przypisane osoby:</label>
                                <?php
                                acf_render_field([
                                    'key' => $field['key'],
                                    'name' => 'project_persons',
                                    'type' => 'relationship',
                                    'post_type' => ['person'],
                                    'return_format' => 'id',
                                    'multiple' => 1,
                                    'value' => $assigned_persons
                                ]);
                                ?>
                            </div>
                            <div class="form-actions">
                                <button type="button" class="btn btn-primary" id="save-persons">Zapisz</button>
                                <button type="button" class="btn btn-secondary" id="cancel-edit-persons">Anuluj</button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="project-main-content">
            <!-- Project Description -->
            <?php if ($project_content): ?>
                <div class="content-section">
                    <div class="content-section-header">
                        <span>Opis projektu</span>
                    </div>
                    <div class="content-section-body">
                        <div class="project-description">
                            <?php echo wp_kses_post($project_content); ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Tasks Section -->
            <div class="content-section">
                <div class="content-section-header">
                    <span>Zadania projektu</span>
                </div>
                <div class="content-section-body">
                    <div class="tasks-header">
                        <div class="tasks-tabs">
                            <button class="tasks-tab active" data-tab="open">
                                Otwarte (<?php echo count($open_tasks); ?>)
                            </button>
                            <button class="tasks-tab" data-tab="closed">
                                Zamknięte (<?php echo count($closed_tasks); ?>)
                            </button>
                        </div>
                        <button class="btn-add-task" id="add-task-btn">
                            <span class="dashicons dashicons-plus"></span>
                            Dodaj zadanie
                        </button>
                    </div>

                    <!-- Open Tasks -->
                    <div id="open-tasks" class="tasks-content active">
                        <?php if (!empty($open_tasks)): ?>
                            <?php foreach ($open_tasks as $task): ?>
                                <div class="task-item" data-task-id="<?php echo esc_attr($task['id']); ?>">
                                    <div class="task-header">
                                        <h4 class="task-title"><?php echo esc_html($task['title']); ?></h4>
                                        <span class="task-status <?php echo sanitize_html_class(strtolower(str_replace(' ', '-', $task['status']))); ?>">
                                            <?php echo esc_html($task['status']); ?>
                                        </span>
                                    </div>
                                    <?php if ($task['content']): ?>
                                        <div class="task-content">
                                            <?php echo wp_kses_post(wp_trim_words($task['content'], 20)); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="task-meta">
                                        <?php if ($task['start_date']): ?>
                                            Start: <?php echo date_i18n('j.m.Y H:i', strtotime($task['start_date'])); ?>
                                        <?php endif; ?>
                                        <?php if ($task['end_date']): ?>
                                            | Deadline: <?php echo date_i18n('j.m.Y H:i', strtotime($task['end_date'])); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="no-items">Brak otwartych zadań.</p>
                        <?php endif; ?>
                    </div>

                    <!-- Closed Tasks -->
                    <div id="closed-tasks" class="tasks-content" style="display: none;">
                        <?php if (!empty($closed_tasks)): ?>
                            <?php foreach ($closed_tasks as $task): ?>
                                <div class="task-item" data-task-id="<?php echo esc_attr($task['id']); ?>">
                                    <div class="task-header">
                                        <h4 class="task-title"><?php echo esc_html($task['title']); ?></h4>
                                        <span class="task-status <?php echo sanitize_html_class(strtolower(str_replace(' ', '-', $task['status']))); ?>">
                                            <?php echo esc_html($task['status']); ?>
                                        </span>
                                    </div>
                                    <?php if ($task['content']): ?>
                                        <div class="task-content">
                                            <?php echo wp_kses_post(wp_trim_words($task['content'], 20)); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="task-meta">
                                        <?php if ($task['start_date']): ?>
                                            Start: <?php echo date_i18n('j.m.Y H:i', strtotime($task['start_date'])); ?>
                                        <?php endif; ?>
                                        <?php if ($task['end_date']): ?>
                                            | Deadline: <?php echo date_i18n('j.m.Y H:i', strtotime($task['end_date'])); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="no-items">Brak zamkniętych zadań.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Activity Timeline -->
            <div class="content-section">
                <div class="content-section-header">
                    <span>Historia aktywności</span>
                    <button class="btn btn-success btn-sm" id="add-activity-btn">
                        <span class="dashicons dashicons-plus"></span>
                        Dodaj aktywność
                    </button>
                </div>
                <div class="content-section-body">
                    <div class="timeline-container" id="project-timeline">
                        <?php if ($activities_query->have_posts()): ?>
                            <?php while ($activities_query->have_posts()): $activities_query->the_post(); ?>
                                <?php 
                                $activity_id = get_the_ID();
                                $activity_post = get_post($activity_id);
                                $activity_type = get_field('activity_type', $activity_id) ?: 'note';
                                $activity_date = get_field('activity_date', $activity_id);
                                $related_person = get_field('related_person', $activity_id);
                                $related_company = get_field('related_company', $activity_id);
                                $related_project = get_field('related_project', $activity_id);
                                $activity_content = $activity_post->post_content;
                                $activity_author = get_the_author_meta('display_name', $activity_post->post_author);
                                $activity_author_id = $activity_post->post_author;
                                $activity_avatar = get_avatar_url($activity_author_id, ['size' => 50]);

                                // Pobierz załączniki
                                $attachments = get_field('activity_attachments', $activity_id) ?: [];
                                
                                // Ikony dla typów aktywności
                                $activity_icons = [
                                    'note' => 'dashicons-edit',
                                    'email' => 'dashicons-email-alt',
                                    'phone' => 'dashicons-phone',
                                    'meeting' => 'dashicons-groups',
                                    'meeting_online' => 'dashicons-video-alt3'
                                ];
                                $icon_class = $activity_icons[$activity_type] ?? 'dashicons-edit';
                                
                                // Etykiety typów
                                $type_labels = [
                                    'note' => 'Notatka',
                                    'email' => 'E-mail',
                                    'phone' => 'Telefon',
                                    'meeting' => 'Spotkanie',
                                    'meeting_online' => 'Spotkanie online'
                                ];
                                $type_label = $type_labels[$activity_type] ?? $activity_type;
                                
                                // Formatuj datę
                                $formatted_date = $activity_date ? date_i18n('j.m.Y o H:i', strtotime($activity_date)) : get_the_date('j.m.Y o H:i', $activity_id);
                                ?>
                                <div class="timeline-item" data-activity-id="<?php echo esc_attr($activity_id); ?>">
                                    <div class="timeline-avatar">
                                        <img src="<?php echo esc_url($activity_avatar); ?>" alt="<?php echo esc_attr($activity_author); ?>">
                                    </div>
                                    <div class="timeline-content">
                                        <div class="timeline-header">
                                            <div class="timeline-header-left">
                                                <div class="timeline-header-meta">
                                                    <span class="dashicons <?php echo esc_attr($icon_class); ?>"></span>
                                                    <span><strong><?php echo esc_html($activity_author); ?></strong> dodał(a) <strong><?php echo esc_html($type_label); ?></strong></span>
                                                </div>
                                                <span class="timeline-header-date"><?php echo esc_html($formatted_date); ?></span>
                                            </div>
                                            <div class="timeline-actions">
                                                <span class="dashicons dashicons-visibility view-activity" title="Zobacz szczegóły" onclick="viewActivityDetails(<?php echo esc_attr($activity_id); ?>)"></span>
                                            </div>
                                        </div>
                                        <div class="timeline-body">
                                            <div class="activity-content-display">
                                                <?php echo wp_kses_post(wp_trim_words($activity_content, 30)); ?>
                                            </div>
                                            
                                            <?php 
                                            // Funkcja pomocnicza do normalizacji ID z pól ACF relationship
                                            $normalize_related_id = function($related_value) {
                                                if (empty($related_value)) return null;
                                                
                                                // Jeśli to tablica
                                                if (is_array($related_value)) {
                                                    if (empty($related_value)) return null;
                                                    $first_item = $related_value[0];
                                                    
                                                    // Jeśli element tablicy to obiekt WP_Post
                                                    if (is_object($first_item) && isset($first_item->ID)) {
                                                        return $first_item->ID;
                                                    }
                                                    // Jeśli element tablicy to ID
                                                    if (is_numeric($first_item)) {
                                                        return (int)$first_item;
                                                    }
                                                    return null;
                                                }
                                                
                                                // Jeśli to obiekt WP_Post
                                                if (is_object($related_value) && isset($related_value->ID)) {
                                                    return $related_value->ID;
                                                }
                                                
                                                // Jeśli to bezpośrednio ID
                                                if (is_numeric($related_value)) {
                                                    return (int)$related_value;
                                                }
                                                
                                                return null;
                                            };
                                            
                                            // Funkcja pomocnicza do generowania linku dla danego post type
                                            $get_related_link = function($related_id, $post_type) {
                                                if (!$related_id) return null;
                                                
                                                $title = get_the_title($related_id);
                                                if (!$title) return null;
                                                
                                                $page_map = [
                                                    'person' => ['page' => 'wpmzf_view_person', 'person_id' => $related_id],
                                                    'company' => ['page' => 'wpmzf_view_company', 'company_id' => $related_id], 
                                                    'project' => ['page' => 'wpmzf_view_project', 'project_id' => $related_id]
                                                ];
                                                
                                                if (!isset($page_map[$post_type])) return null;
                                                
                                                $url = add_query_arg($page_map[$post_type], admin_url('admin.php'));
                                                return [
                                                    'title' => $title,
                                                    'url' => $url,
                                                    'post_type' => $post_type
                                                ];
                                            };
                                            
                                            // Normalizuj ID z pól ACF
                                            $normalized_person_id = $normalize_related_id($related_person);
                                            $normalized_company_id = $normalize_related_id($related_company);
                                            $normalized_project_id = $normalize_related_id($related_project);
                                            
                                            $related_links = [];
                                            if ($normalized_person_id) {
                                                $link = $get_related_link($normalized_person_id, 'person');
                                                if ($link) $related_links[] = $link;
                                            }
                                            if ($normalized_company_id) {
                                                $link = $get_related_link($normalized_company_id, 'company');
                                                if ($link) $related_links[] = $link;
                                            }
                                            if ($normalized_project_id && $normalized_project_id != $project_id) { // Nie pokazuj tego samego projektu
                                                $link = $get_related_link($normalized_project_id, 'project');
                                                if ($link) $related_links[] = $link;
                                            }
                                            ?>
                                            
                                            <?php if (!empty($related_links)): ?>
                                                <div class="activity-related">
                                                    <strong>Dotycząca:</strong>
                                                    <?php foreach ($related_links as $i => $link): ?>
                                                        <?php if ($i > 0) echo ', '; ?>
                                                        <a href="<?php echo esc_url($link['url']); ?>">
                                                            <?php echo esc_html($link['title']); ?>
                                                        </a>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; wp_reset_postdata(); ?>
                        <?php else: ?>
                            <p class="no-items">Brak aktywności dla tego projektu.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Basic Data Form -->
    <div id="basic-data-edit-form" class="edit-form">
        <div class="content-section">
            <div class="content-section-header">
                <span>Edytuj dane projektu</span>
            </div>
            <div class="content-section-body">
                <form id="project-edit-form">
                    <div class="form-grid">
                        <div class="form-field">
                            <label for="project-title">Nazwa projektu:</label>
                            <input type="text" id="project-title" name="project_title" value="<?php echo esc_attr($project_title); ?>" required>
                        </div>
                        
                        <div class="form-field">
                            <label for="project-status">Status:</label>
                            <select id="project-status" name="project_status">
                                <option value="Planowanie" <?php selected($project_status, 'Planowanie'); ?>>Planowanie</option>
                                <option value="W toku" <?php selected($project_status, 'W toku'); ?>>W toku</option>
                                <option value="Zakończony" <?php selected($project_status, 'Zakończony'); ?>>Zakończony</option>
                                <option value="Wstrzymany" <?php selected($project_status, 'Wstrzymany'); ?>>Wstrzymany</option>
                            </select>
                        </div>
                        
                        <div class="form-field">
                            <label for="project-budget">Budżet (PLN):</label>
                            <input type="number" id="project-budget" name="project_budget" value="<?php echo esc_attr($budget); ?>" step="0.01">
                        </div>
                        
                        <div class="form-field">
                            <label for="start-date">Data rozpoczęcia:</label>
                            <input type="date" id="start-date" name="start_date" value="<?php echo esc_attr($start_date); ?>">
                        </div>
                        
                        <div class="form-field">
                            <label for="end-date">Data zakończenia:</label>
                            <input type="date" id="end-date" name="end_date" value="<?php echo esc_attr($end_date); ?>">
                        </div>
                        
                        <div class="form-field full-width">
                            <label for="project-description">Opis projektu:</label>
                            <textarea id="project-description" name="project_description"><?php echo esc_textarea($project_content); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <span class="dashicons dashicons-yes"></span>
                            Zapisz zmiany
                        </button>
                        <button type="button" id="cancel-edit-basic-data" class="btn btn-secondary">
                            <span class="dashicons dashicons-no"></span>
                            Anuluj
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript Variables -->
<script>
var projectId = <?php echo json_encode($project_id); ?>;
var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
var securityNonce = '<?php echo wp_create_nonce('wpmzf_project_view_nonce'); ?>';
var taskSecurityNonce = '<?php echo wp_create_nonce('wpmzf_task_nonce'); ?>';

// Basic functionality for tabs and forms
document.addEventListener('DOMContentLoaded', function() {
    // Tasks tabs
    const tasksTabs = document.querySelectorAll('.tasks-tab');
    const tasksContents = document.querySelectorAll('.tasks-content');
    
    tasksTabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const targetTab = this.dataset.tab;
            
            // Update active tab
            tasksTabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            
            // Show corresponding content
            tasksContents.forEach(content => {
                if (content.id === targetTab + '-tasks') {
                    content.style.display = 'block';
                    content.classList.add('active');
                } else {
                    content.style.display = 'none';
                    content.classList.remove('active');
                }
            });
        });
    });
    
    // Edit forms toggle
    const editBasicBtn = document.getElementById('edit-basic-data');
    const editForm = document.getElementById('basic-data-edit-form');
    const cancelBtn = document.getElementById('cancel-edit-basic-data');
    
    if (editBasicBtn && editForm) {
        editBasicBtn.addEventListener('click', function() {
            editForm.classList.add('active');
            editForm.style.display = 'block';
        });
    }
    
    if (cancelBtn && editForm) {
        cancelBtn.addEventListener('click', function() {
            editForm.classList.remove('active');
            editForm.style.display = 'none';
        });
    }
    
    // Companies edit
    const editCompaniesBtn = document.getElementById('edit-companies');
    const companiesForm = document.getElementById('companies-edit-form');
    const cancelCompaniesBtn = document.getElementById('cancel-edit-companies');
    
    if (editCompaniesBtn && companiesForm) {
        editCompaniesBtn.addEventListener('click', function() {
            companiesForm.classList.add('active');
        });
    }
    
    if (cancelCompaniesBtn && companiesForm) {
        cancelCompaniesBtn.addEventListener('click', function() {
            companiesForm.classList.remove('active');
        });
    }
    
    // Persons edit
    const editPersonsBtn = document.getElementById('edit-persons');
    const personsForm = document.getElementById('persons-edit-form');
    const cancelPersonsBtn = document.getElementById('cancel-edit-persons');
    
    if (editPersonsBtn && personsForm) {
        editPersonsBtn.addEventListener('click', function() {
            personsForm.classList.add('active');
        });
    }
    
    if (cancelPersonsBtn && personsForm) {
        cancelPersonsBtn.addEventListener('click', function() {
            personsForm.classList.remove('active');
        });
    }
    
    // Timeline functionality
    if (typeof window.viewActivityDetails === 'undefined') {
        window.viewActivityDetails = function(activityId) {
            const activityElement = document.querySelector(`[data-activity-id="${activityId}"]`);
            
            if (!activityElement) {
                console.error('Nie znaleziono aktywności o ID:', activityId);
                return;
            }
            
            // Pobierz informacje z elementu
            const activityContentElement = activityElement.querySelector('.activity-content-display');
            const activityHeaderElement = activityElement.querySelector('.timeline-header-meta span:last-child');
            const activityDateElement = activityElement.querySelector('.timeline-header-date');
            const relatedElement = activityElement.querySelector('.activity-related');
            
            const activityContent = activityContentElement ? activityContentElement.innerHTML : '';
            const activityHeader = activityHeaderElement ? activityHeaderElement.textContent : '';
            const activityDate = activityDateElement ? activityDateElement.textContent : '';
            const relatedInfo = relatedElement ? relatedElement.innerHTML : '';
            
            // Stwórz modal z szczegółami
            const modalHtml = `
                <div id="activity-details-modal" style="
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0,0,0,0.7);
                    z-index: 100000;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                ">
                    <div style="
                        background: #fff;
                        border-radius: 8px;
                        max-width: 800px;
                        width: 90%;
                        max-height: 80%;
                        overflow-y: auto;
                        box-shadow: 0 4px 20px rgba(0,0,0,0.3);
                    ">
                        <div style="
                            padding: 24px;
                            border-bottom: 1px solid #e1e5e9;
                            background: #f8f9fa;
                            border-radius: 8px 8px 0 0;
                            display: flex;
                            justify-content: space-between;
                            align-items: center;
                        ">
                            <div>
                                <h2 style="margin: 0; font-size: 18px; color: #1d2327;">${activityHeader}</h2>
                                <p style="margin: 4px 0 0; color: #646970; font-size: 14px;">${activityDate}</p>
                            </div>
                            <button id="close-activity-modal" style="
                                background: none;
                                border: none;
                                font-size: 24px;
                                cursor: pointer;
                                color: #646970;
                                padding: 4px;
                                border-radius: 4px;
                                transition: all 0.2s ease;
                            ">&times;</button>
                        </div>
                        <div style="padding: 24px;">
                            <div style="
                                color: #1d2327;
                                line-height: 1.6;
                                font-size: 15px;
                                margin-bottom: ${relatedInfo ? '24px' : '0'};
                            ">
                                ${activityContent}
                            </div>
                            ${relatedInfo ? `
                                <div style="
                                    padding: 16px;
                                    background: #f8f9fa;
                                    border-radius: 6px;
                                    border-left: 4px solid #2271b1;
                                ">
                                    ${relatedInfo}
                                </div>
                            ` : ''}
                        </div>
                    </div>
                </div>
            `;
            
            // Dodaj modal do strony
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            
            // Obsługa zamykania modala
            const modal = document.getElementById('activity-details-modal');
            const closeBtn = document.getElementById('close-activity-modal');
            
            function closeModal() {
                if (modal) {
                    modal.remove();
                }
            }
            
            // Kliknięcie na tło zamyka modal
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    closeModal();
                }
            });
            
            // Przycisk zamknij
            closeBtn.addEventListener('click', closeModal);
            
            // Hover effects dla przycisku zamknij
            closeBtn.addEventListener('mouseenter', function() {
                this.style.background = '#f0f0f1';
                this.style.color = '#d63638';
            });
            
            closeBtn.addEventListener('mouseleave', function() {
                this.style.background = 'none';
                this.style.color = '#646970';
            });
            
            // Zapobiegaj zamykaniu modala przy kliknięciu na zawartość
            const modalContent = modal.querySelector('div');
            modalContent.addEventListener('click', function(e) {
                e.stopPropagation();
            });
            
            // Escape key zamyka modal
            function handleEscape(e) {
                if (e.key === 'Escape') {
                    closeModal();
                    document.removeEventListener('keydown', handleEscape);
                }
            }
            document.addEventListener('keydown', handleEscape);
        };
    }
});
</script>
        border-radius: 6px;
        margin-bottom: 16px;
        font-size: 14px;
    }

    .notice-success {
        background: #d7eddb;
        color: #2d7d3a;
        border-left: 4px solid #00a32a;
    }

    .notice-error {
        background: #fce2e6;
        color: #d63638;
        border-left: 4px solid #dc3232;
    }

    .notice-warning {
        background: #fff2cc;
        color: #996f00;
        border-left: 4px solid #f0d000;
    }
</style>

<div class="wrap project-view-wrap">
    <!-- Project Header -->
    <div class="project-header">
        <div class="project-header-content">
            <div class="project-title-section">
                <div class="project-status-badge <?php echo sanitize_html_class(strtolower(str_replace(' ', '-', $project_status))); ?>">
                    <?php echo esc_html($project_status); ?>
                </div>
                <h1><?php echo esc_html($project_title); ?></h1>
                <div class="project-meta">
                    Utworzono: <?php echo esc_html($project_date); ?>
                    <?php if ($start_date): ?>
                        | Start: <?php echo esc_html(date('j F Y', strtotime($start_date))); ?>
                    <?php endif; ?>
                    <?php if ($end_date): ?>
                        | Koniec: <?php echo esc_html(date('j F Y', strtotime($end_date))); ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="project-actions">
                <button id="edit-basic-data" class="btn-edit-project">
                    <span class="dashicons dashicons-edit"></span>
                    Edytuj projekt
                </button>
            </div>
        </div>
    </div>
            'date_created' => get_the_date('Y-m-d H:i:s')
        ];
        
        if ($task_status === 'Zrobione') {
            $closed_tasks[] = $task_data;
        } else {
            $open_tasks[] = $task_data;
        }
    }
    wp_reset_postdata();
}

// Pobierz aktywności związane z projektem
$activities_args = [
    'post_type' => 'activity',
    'posts_per_page' => 50,
    'meta_query' => [
        'relation' => 'OR',
        [
            'key' => 'related_project',
            'value' => $project_id,
            'compare' => '='
        ]
    ],
    'orderby' => 'date',
    'order' => 'DESC'
];

$activities_query = new WP_Query($activities_args);

?>

<div class="wrap">
    <!-- Header projektu -->
    <div class="person-header">
        <div class="person-header-content">
            <div class="person-basic-info">
                <h1 class="person-name"><?php echo esc_html($project_title); ?></h1>
                <div class="person-meta">
                    <span class="person-status status-<?php echo esc_attr(strtolower(str_replace(' ', '-', $project_status))); ?>">
                        <?php echo esc_html($project_status); ?>
                    </span>
                    <span class="person-date">Utworzono: <?php echo esc_html($project_date); ?></span>
                    <?php if ($start_date): ?>
                        <span class="project-start-date">Rozpoczęto: <?php echo esc_html(date('j.m.Y', strtotime($start_date))); ?></span>
                    <?php endif; ?>
                    <?php if ($end_date): ?>
                        <span class="project-end-date">Deadline: <?php echo esc_html(date('j.m.Y', strtotime($end_date))); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="person-actions">
                <a href="<?php echo esc_url(get_edit_post_link($project_id)); ?>" class="button button-primary">
                    <span class="dashicons dashicons-edit"></span>
                    Edytuj projekt
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=wpmzf_projects')); ?>" class="button">
                    <span class="dashicons dashicons-arrow-left-alt"></span>
                    Powrót do listy projektów
                </a>
            </div>
        </div>
    </div>

    <!-- Grid układu -->
    <div class="person-grid">
        <!-- Lewa kolumna -->
        <div class="person-left-column">
            
            <!-- Szczegóły projektu -->
            <div class="dossier-box">
                <h2 class="dossier-title">
                    Szczegóły projektu
                    <a href="#" class="edit-data-button" id="edit-basic-data">Edytuj</a>
                </h2>
                <div class="dossier-content">
                    <div id="basic-data-display">
                        <?php if ($project_content): ?>
                            <div class="project-description">
                                <strong>Opis:</strong>
                                <div class="description-content"><?php echo wp_kses_post($project_content); ?></div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="project-details-grid">
                            <div class="detail-item">
                                <strong>Status:</strong>
                                <span class="status-badge status-<?php echo esc_attr(strtolower(str_replace(' ', '-', $project_status))); ?>">
                                    <?php echo esc_html($project_status); ?>
                                </span>
                            </div>
                            
                            <div class="detail-item">
                                <strong>Budżet:</strong>
                                <span class="budget-amount"><?php echo $budget > 0 ? esc_html(number_format((float)$budget, 2, ',', ' ')) . ' zł' : 'Nie określono'; ?></span>
                            </div>
                            
                            <?php if ($start_date): ?>
                                <div class="detail-item">
                                    <strong>Data rozpoczęcia:</strong>
                                    <span><?php echo esc_html(date('j F Y', strtotime($start_date))); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($end_date): ?>
                                <div class="detail-item">
                                    <strong>Deadline:</strong>
                                    <span class="<?php echo strtotime($end_date) < time() && $project_status !== 'Zakończony' ? 'deadline-overdue' : ''; ?>">
                                        <?php echo esc_html(date('j F Y', strtotime($end_date))); ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Formularz edycji -->
                    <div id="basic-data-edit" style="display: none;">
                        <form id="edit-project-form">
                            <div class="form-group">
                                <label for="project-title">Nazwa projektu:</label>
                                <input type="text" id="project-title" name="project_title" value="<?php echo esc_attr($project_title); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="project-description">Opis:</label>
                                <textarea id="project-description" name="project_description" rows="4"><?php echo esc_textarea($project_content); ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="project-status">Status:</label>
                                <select id="project-status" name="project_status">
                                    <option value="Planowanie" <?php selected($project_status, 'Planowanie'); ?>>Planowanie</option>
                                    <option value="W toku" <?php selected($project_status, 'W toku'); ?>>W toku</option>
                                    <option value="Zakończony" <?php selected($project_status, 'Zakończony'); ?>>Zakończony</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="project-budget">Budżet (zł):</label>
                                <input type="number" id="project-budget" name="project_budget" value="<?php echo esc_attr($budget); ?>" step="0.01">
                            </div>
                            
                            <div class="form-group">
                                <label for="project-start-date">Data rozpoczęcia:</label>
                                <input type="date" id="project-start-date" name="start_date" value="<?php echo esc_attr($start_date); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="project-end-date">Deadline:</label>
                                <input type="date" id="project-end-date" name="end_date" value="<?php echo esc_attr($end_date); ?>">
                            </div>

                            <div class="edit-actions">
                                <button type="submit" class="button button-primary">Zapisz zmiany</button>
                                <button type="button" id="cancel-edit-basic-data" class="button">Anuluj</button>
                                <span class="spinner"></span>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Przypisane firmy -->
            <div class="dossier-box">
                <h2 class="dossier-title">
                    Przypisane firmy
                    <a href="#" class="edit-data-button" id="edit-companies">Edytuj</a>
                </h2>
                <div class="dossier-content">
                    <div id="companies-display">
                        <?php if (!empty($assigned_companies)): ?>
                            <ul class="assigned-entities-list">
                                <?php foreach ($assigned_companies as $company_id): ?>
                                    <?php 
                                    $company_id = is_object($company_id) ? $company_id->ID : $company_id;
                                    $company_title = get_the_title($company_id);
                                    $company_nip = get_field('company_nip', $company_id);
                                    ?>
                                    <li class="entity-item">
                                        <a href="<?php echo esc_url(add_query_arg(['page' => 'wpmzf_view_company', 'company_id' => $company_id], admin_url('admin.php'))); ?>" class="entity-link">
                                            <strong><?php echo esc_html($company_title); ?></strong>
                                            <?php if ($company_nip): ?>
                                                <span class="entity-meta">NIP: <?php echo esc_html($company_nip); ?></span>
                                            <?php endif; ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="no-entities">Brak przypisanych firm.</p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Formularz edycji firm -->
                    <div id="companies-edit" style="display: none;">
                        <form id="edit-companies-form">
                            <div class="form-group">
                                <label for="project-companies">Wybierz firmy:</label>
                                <?php
                                $companies_field = [
                                    'field_key' => 'field_wpmzf_project_company_relation',
                                    'field_name' => 'project_companies',
                                    'value' => $assigned_companies
                                ];
                                
                                // Używamy ACF do renderowania pola relacji
                                acf_render_field_setting_type($companies_field);
                                ?>
                            </div>
                            
                            <div class="edit-actions">
                                <button type="submit" class="button button-primary">Zapisz zmiany</button>
                                <button type="button" id="cancel-edit-companies" class="button">Anuluj</button>
                                <span class="spinner"></span>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Przypisane osoby -->
            <div class="dossier-box">
                <h2 class="dossier-title">
                    Przypisane osoby
                    <a href="#" class="edit-data-button" id="edit-persons">Edytuj</a>
                </h2>
                <div class="dossier-content">
                    <div id="persons-display">
                        <?php if (!empty($assigned_persons)): ?>
                            <ul class="assigned-entities-list">
                                <?php foreach ($assigned_persons as $person_id): ?>
                                    <?php 
                                    $person_id = is_object($person_id) ? $person_id->ID : $person_id;
                                    $person_title = get_the_title($person_id);
                                    $person_company = get_field('person_company', $person_id);
                                    ?>
                                    <li class="entity-item">
                                        <a href="<?php echo esc_url(add_query_arg(['page' => 'wpmzf_view_person', 'person_id' => $person_id], admin_url('admin.php'))); ?>" class="entity-link">
                                            <strong><?php echo esc_html($person_title); ?></strong>
                                            <?php if ($person_company && is_array($person_company) && !empty($person_company)): ?>
                                                <span class="entity-meta"><?php echo esc_html(get_the_title($person_company[0])); ?></span>
                                            <?php endif; ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="no-entities">Brak przypisanych osób.</p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Formularz edycji osób -->
                    <div id="persons-edit" style="display: none;">
                        <form id="edit-persons-form">
                            <div class="form-group">
                                <label for="project-persons">Wybierz osoby:</label>
                                <!-- Pole relacji ACF będzie renderowane przez JS -->
                                <div id="project-persons-field"></div>
                            </div>
                            
                            <div class="edit-actions">
                                <button type="submit" class="button button-primary">Zapisz zmiany</button>
                                <button type="button" id="cancel-edit-persons" class="button">Anuluj</button>
                                <span class="spinner"></span>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Prawa kolumna -->
        <div class="person-right-column">
            
            <!-- Zadania -->
            <div class="dossier-box">
                <h2 class="dossier-title">
                    Zadania
                    <a href="#" class="edit-data-button" id="add-new-task-btn">+ Nowe zadanie</a>
                </h2>
                <div class="dossier-content">
                    <!-- Otwarte zadania -->
                    <div id="open-tasks-container">
                        <h4 class="tasks-section-title">
                            Otwarte zadania (<?php echo count($open_tasks); ?>)
                        </h4>
                        <div id="open-tasks-list">
                            <?php if (!empty($open_tasks)): ?>
                                <?php foreach ($open_tasks as $task): ?>
                                    <div class="task-item task-status-<?php echo esc_attr(strtolower(str_replace(' ', '-', $task['status']))); ?>" data-task-id="<?php echo esc_attr($task['id']); ?>">
                                        <div class="task-content">
                                            <div class="task-main">
                                                <div class="task-title-row">
                                                    <div class="task-title"><?php echo esc_html($task['title']); ?></div>
                                                    <div class="task-actions">
                                                        <span class="dashicons dashicons-yes-alt" title="Oznacz jako zrobione" data-action="complete"></span>
                                                        <span class="dashicons dashicons-edit" title="Edytuj zadanie" data-action="edit"></span>
                                                        <span class="dashicons dashicons-trash" title="Usuń zadanie" data-action="delete"></span>
                                                    </div>
                                                </div>
                                                <div class="task-meta-row">
                                                    <div class="task-meta-left">
                                                        <span class="task-status"><?php echo esc_html($task['status']); ?></span>
                                                        <?php if ($task['start_date']): ?>
                                                            <span class="task-date">Termin: <?php echo esc_html(date('j.m.Y H:i', strtotime($task['start_date']))); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php if ($task['content']): ?>
                                                <div class="task-description"><?php echo wp_kses_post($task['content']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="no-items">Brak otwartych zadań.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Zakończone zadania -->
                    <?php if (!empty($closed_tasks)): ?>
                        <div id="closed-tasks-container" style="margin-top: 20px;">
                            <h4 class="tasks-section-title collapsible" id="toggle-closed-tasks" style="cursor: pointer;">
                                <span class="dashicons dashicons-arrow-right"></span>
                                Zakończone zadania (<?php echo count($closed_tasks); ?>)
                            </h4>
                            <div id="closed-tasks-list" style="display: none;">
                                <?php foreach ($closed_tasks as $task): ?>
                                    <div class="task-item task-status-completed" data-task-id="<?php echo esc_attr($task['id']); ?>">
                                        <div class="task-content">
                                            <div class="task-main">
                                                <div class="task-title-row">
                                                    <div class="task-title"><?php echo esc_html($task['title']); ?></div>
                                                    <div class="task-actions">
                                                        <span class="dashicons dashicons-undo" title="Oznacz jako do zrobienia" data-action="reopen"></span>
                                                        <span class="dashicons dashicons-edit" title="Edytuj zadanie" data-action="edit"></span>
                                                        <span class="dashicons dashicons-trash" title="Usuń zadanie" data-action="delete"></span>
                                                    </div>
                                                </div>
                                                <div class="task-meta-row">
                                                    <div class="task-meta-left">
                                                        <span class="task-status completed">Zrobione</span>
                                                        <?php if ($task['end_date']): ?>
                                                            <span class="task-date">Zakończono: <?php echo esc_html(date('j.m.Y H:i', strtotime($task['end_date']))); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php if ($task['content']): ?>
                                                <div class="task-description"><?php echo wp_kses_post($task['content']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Historia aktywności -->
            <div class="dossier-box">
                <h2 class="dossier-title">
                    Historia aktywności
                    <a href="#" class="edit-data-button" id="add-new-activity-btn">+ Nowa aktywność</a>
                </h2>
                <div class="dossier-content">
                    <div id="project-activity-timeline">
                        <?php if ($activities_query->have_posts()): ?>
                            <?php while ($activities_query->have_posts()): $activities_query->the_post(); ?>
                                <?php 
                                $activity_id = get_the_ID();
                                $activity_post = get_post($activity_id);
                                $activity_type = get_field('activity_type', $activity_id) ?: 'note';
                                $activity_date = get_field('activity_date', $activity_id);
                                $activity_content = $activity_post->post_content;
                                $activity_author = get_the_author_meta('display_name', $activity_post->post_author);
                                $activity_author_id = $activity_post->post_author;
                                $activity_avatar = get_avatar_url($activity_author_id, ['size' => 50]);

                                // Pobierz załączniki
                                $attachments = get_field('activity_attachments', $activity_id) ?: [];
                                
                                // Ikony dla typów aktywności
                                $activity_icons = [
                                    'note' => 'dashicons-edit',
                                    'email' => 'dashicons-email-alt',
                                    'phone' => 'dashicons-phone',
                                    'meeting' => 'dashicons-groups',
                                    'meeting_online' => 'dashicons-video-alt3'
                                ];
                                $icon_class = $activity_icons[$activity_type] ?? 'dashicons-edit';
                                
                                // Etykiety typów
                                $type_labels = [
                                    'note' => 'Notatka',
                                    'email' => 'E-mail',
                                    'phone' => 'Telefon',
                                    'meeting' => 'Spotkanie',
                                    'meeting_online' => 'Spotkanie online'
                                ];
                                $type_label = $type_labels[$activity_type] ?? $activity_type;
                                
                                // Formatuj datę
                                $formatted_date = $activity_date ? date_i18n('j.m.Y o H:i', strtotime($activity_date)) : get_the_date('j.m.Y o H:i', $activity_id);
                                ?>
                                <div class="timeline-item" data-activity-id="<?php echo esc_attr($activity_id); ?>">
                                    <div class="timeline-avatar">
                                        <img src="<?php echo esc_url($activity_avatar); ?>" alt="<?php echo esc_attr($activity_author); ?>">
                                    </div>
                                    <div class="timeline-content">
                                        <div class="timeline-header">
                                            <div class="timeline-header-left">
                                                <div class="timeline-header-meta">
                                                    <span class="dashicons <?php echo esc_attr($icon_class); ?>"></span>
                                                    <span><strong><?php echo esc_html($activity_author); ?></strong> dodał(a) <strong><?php echo esc_html($type_label); ?></strong></span>
                                                </div>
                                                <span class="timeline-header-date"><?php echo esc_html($formatted_date); ?></span>
                                            </div>
                                            <div class="timeline-actions">
                                                <span class="dashicons dashicons-visibility view-activity" title="Zobacz szczegóły" onclick="viewActivityDetails(<?php echo esc_attr($activity_id); ?>)"></span>
                                                <span class="dashicons dashicons-edit edit-activity" title="Edytuj" data-activity-id="<?php echo esc_attr($activity_id); ?>"></span>
                                                <span class="dashicons dashicons-trash delete-activity" title="Usuń" data-activity-id="<?php echo esc_attr($activity_id); ?>"></span>
                                            </div>
                                        </div>
                                        <div class="timeline-body">
                                            <div class="activity-content-display">
                                                <?php echo wp_kses_post(wp_trim_words($activity_content, 30)); ?>
                                            </div>
                                            
                                            <?php if (!empty($attachments) && is_array($attachments)): ?>
                                                <div class="timeline-attachments">
                                                    <ul>
                                                        <?php foreach ($attachments as $attachment): ?>
                                                            <?php 
                                                            // Obsługa różnych struktur attachmentów
                                                            $attachment_id = '';
                                                            if (is_array($attachment) && isset($attachment['ID'])) {
                                                                $attachment_id = $attachment['ID'];
                                                            } elseif (is_object($attachment) && isset($attachment->ID)) {
                                                                $attachment_id = $attachment->ID;
                                                            } elseif (is_numeric($attachment)) {
                                                                $attachment_id = $attachment;
                                                            }
                                                            
                                                            if (!$attachment_id) continue;
                                                            
                                                            $file_url = wp_get_attachment_url($attachment_id);
                                                            if (!$file_url) continue;
                                                            
                                                            $file_name = get_the_title($attachment_id) ?: basename($file_url);
                                                            $mime_type = get_post_mime_type($attachment_id);
                                                            $is_image = strpos($mime_type, 'image/') === 0;
                                                            ?>
                                                            <li>
                                                                <a href="<?php echo esc_url($file_url); ?>" target="_blank">
                                                                    <?php if ($is_image): ?>
                                                                        <?php $thumb_url = wp_get_attachment_image_url($attachment_id, 'thumbnail'); ?>
                                                                        <img src="<?php echo esc_url($thumb_url ?: $file_url); ?>" alt="Podgląd załącznika">
                                                                    <?php else: ?>
                                                                        <?php
                                                                        $icon_map = [
                                                                            'application/pdf' => 'dashicons-pdf',
                                                                            'application/msword' => 'dashicons-media-document',
                                                                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'dashicons-media-document',
                                                                            'application/vnd.ms-excel' => 'dashicons-media-spreadsheet',
                                                                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'dashicons-media-spreadsheet',
                                                                            'text/plain' => 'dashicons-media-text'
                                                                        ];
                                                                        $icon_class = $icon_map[$mime_type] ?? 'dashicons-media-default';
                                                                        ?>
                                                                        <span class="dashicons <?php echo esc_attr($icon_class); ?>"></span>
                                                                    <?php endif; ?>
                                                                    <span><?php echo esc_html($file_name); ?></span>
                                                                </a>
                                                            </li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; wp_reset_postdata(); ?>
                        <?php else: ?>
                            <p style="color: #8c8f94; font-style: italic; text-align: center; padding: 40px 20px;">Brak aktywności dla tego projektu.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Globalne zmienne dla widoku projektu
var projectId = <?php echo json_encode($project_id); ?>;
var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
var securityNonce = '<?php echo wp_create_nonce('wpmzf_project_view_nonce'); ?>';
var taskSecurityNonce = '<?php echo wp_create_nonce('wpmzf_task_nonce'); ?>';

// Podstawowa funkcjonalność timeline - identyczna z widokami osób/firm
document.addEventListener('DOMContentLoaded', function() {
    // Toggle dla zakończonych zadań
    const toggleClosedTasks = document.getElementById('toggle-closed-tasks');
    if (toggleClosedTasks) {
        toggleClosedTasks.addEventListener('click', function() {
            const closedTasksList = document.getElementById('closed-tasks-list');
            const arrow = this.querySelector('.dashicons');
            
            if (closedTasksList.style.display === 'none') {
                closedTasksList.style.display = 'block';
                arrow.classList.remove('dashicons-arrow-right');
                arrow.classList.add('dashicons-arrow-down');
            } else {
                closedTasksList.style.display = 'none';
                arrow.classList.remove('dashicons-arrow-down');
                arrow.classList.add('dashicons-arrow-right');
            }
        });
    }
    
    // Edycja podstawowych danych
    const editBasicBtn = document.getElementById('edit-basic-data');
    const cancelEditBtn = document.getElementById('cancel-edit-basic-data');
    const basicDataDisplay = document.getElementById('basic-data-display');
    const basicDataEdit = document.getElementById('basic-data-edit');
    
    if (editBasicBtn) {
        editBasicBtn.addEventListener('click', function(e) {
            e.preventDefault();
            basicDataDisplay.style.display = 'none';
            basicDataEdit.style.display = 'block';
        });
    }
    
    if (cancelEditBtn) {
        cancelEditBtn.addEventListener('click', function(e) {
            e.preventDefault();
            basicDataDisplay.style.display = 'block';
            basicDataEdit.style.display = 'none';
        });
    }
    
    // Obsługa akcji timeline
    const timelineActions = document.querySelectorAll('.timeline-actions .dashicons');
    timelineActions.forEach(function(action) {
        action.addEventListener('mouseenter', function() {
            this.style.color = '#2271b1';
            this.style.background = '#f0f6fc';
        });
        
        action.addEventListener('mouseleave', function() {
            this.style.color = '#646970';
            this.style.background = 'transparent';
        });
    });
    
    // Jeśli funkcja viewActivityDetails nie jest jeszcze dostępna (skrypt nie załadowany)
    // utworzmy prostą implementację
    if (typeof window.viewActivityDetails === 'undefined') {
        window.viewActivityDetails = function(activityId) {
            const activityElement = document.querySelector(`[data-activity-id="${activityId}"]`);
            
            if (!activityElement) {
                console.error('Nie znaleziono aktywności o ID:', activityId);
                return;
            }
            
            // Pobierz informacje z elementu
            const activityContentElement = activityElement.querySelector('.activity-content-display');
            const activityHeaderElement = activityElement.querySelector('.timeline-header-meta span:last-child');
            const activityDateElement = activityElement.querySelector('.timeline-header-date');
            const attachmentsElement = activityElement.querySelector('.timeline-attachments');
            
            const activityContent = activityContentElement ? activityContentElement.innerHTML : '';
            const activityHeader = activityHeaderElement ? activityHeaderElement.textContent : '';
            const activityDate = activityDateElement ? activityDateElement.textContent : '';
            const attachments = attachmentsElement ? attachmentsElement.innerHTML : '';
            
            // Stwórz modal z szczegółami
            const modalHtml = `
                <div id="activity-details-modal" style="
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0,0,0,0.7);
                    z-index: 100000;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                ">
                    <div style="
                        background: #fff;
                        border-radius: 8px;
                        max-width: 800px;
                        width: 90%;
                        max-height: 80%;
                        overflow-y: auto;
                        box-shadow: 0 4px 20px rgba(0,0,0,0.3);
                    ">
                        <div style="
                            padding: 24px;
                            border-bottom: 1px solid #e1e5e9;
                            background: #f8f9fa;
                            border-radius: 8px 8px 0 0;
                            display: flex;
                            justify-content: space-between;
                            align-items: center;
                        ">
                            <div>
                                <h2 style="margin: 0; font-size: 18px; color: #1d2327;">${activityHeader}</h2>
                                <p style="margin: 4px 0 0; color: #646970; font-size: 14px;">${activityDate}</p>
                            </div>
                            <button id="close-activity-modal" style="
                                background: none;
                                border: none;
                                font-size: 24px;
                                cursor: pointer;
                                color: #646970;
                                padding: 4px;
                                border-radius: 4px;
                                transition: all 0.2s ease;
                            ">&times;</button>
                        </div>
                        <div style="padding: 24px;">
                            <div style="
                                color: #1d2327;
                                line-height: 1.6;
                                font-size: 15px;
                                margin-bottom: ${attachments ? '24px' : '0'};
                            ">
                                ${activityContent}
                            </div>
                            ${attachments ? `
                                <div style="
                                    border-top: 1px solid #e1e5e9;
                                    padding-top: 20px;
                                ">
                                    <h4 style="margin: 0 0 12px; color: #1d2327; font-size: 14px; font-weight: 600;">Załączniki:</h4>
                                    ${attachments}
                                </div>
                            ` : ''}
                        </div>
                    </div>
                </div>
            `;
            
            // Dodaj modal do strony
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            
            // Obsługa zamykania modala
            const modal = document.getElementById('activity-details-modal');
            const closeBtn = document.getElementById('close-activity-modal');
            
            function closeModal() {
                if (modal) {
                    modal.remove();
                }
            }
            
            // Kliknięcie na tło zamyka modal
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    closeModal();
                }
            });
            
            // Przycisk zamknij
            closeBtn.addEventListener('click', closeModal);
            
            // Hover effects dla przycisku zamknij
            closeBtn.addEventListener('mouseenter', function() {
                this.style.background = '#f0f0f1';
                this.style.color = '#d63638';
            });
            
            closeBtn.addEventListener('mouseleave', function() {
                this.style.background = 'none';
                this.style.color = '#646970';
            });
            
            // Zapobiegaj zamykaniu modala przy kliknięciu na zawartość
            const modalContent = modal.querySelector('div');
            modalContent.addEventListener('click', function(e) {
                e.stopPropagation();
            });
            
            // Escape key zamyka modal
            function handleEscape(e) {
                if (e.key === 'Escape') {
                    closeModal();
                    document.removeEventListener('keydown', handleEscape);
                }
            }
            document.addEventListener('keydown', handleEscape);
        };
    }
});
</script>
