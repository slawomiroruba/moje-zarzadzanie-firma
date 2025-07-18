<?php
/**
 * Widok pojedynczej firmy
 *
 * @package WPMZF
 * @subpackage Admin/Views
 */

if (!defined('ABSPATH')) {
    exit;
}

global $title;

// Sprawdzenie czy ID firmy zostało przekazane
$company_id = isset($_GET['company_id']) ? intval($_GET['company_id']) : 0;

if (!$company_id) {
    wp_die('Nieprawidłowe ID firmy.');
}

// Sprawdzenie czy firma istnieje
$company = get_post($company_id);
if (!$company || $company->post_type !== 'company') {
    wp_die('Firma nie została znaleziona.');
}

$company_title = get_the_title($company_id);
$title = 'Widok Firmy: ' . $company_title; // Ustawiamy globalny tytuł strony
$company_fields = get_fields($company_id);

// Renderuj nawigację i header
WPMZF_View_Helper::render_complete_header(array(
    'title' => $company_title,
    'subtitle' => 'Szczegółowe informacje o firmie',
    'breadcrumbs' => array(
        array('label' => 'Dashboard', 'url' => admin_url('admin.php?page=wpmzf_dashboard')),
        array('label' => 'Firmy', 'url' => admin_url('admin.php?page=wpmzf_companies')),
        array('label' => $company_title, 'url' => '')
    ),
    'actions' => array(
        array(
            'label' => 'Edytuj firmę',
            'url' => admin_url('post.php?post=' . $company_id . '&action=edit'),
            'icon' => '✏️',
            'class' => 'button button-primary'
        ),
        array(
            'label' => 'Dodaj projekt',
            'url' => admin_url('post-new.php?post_type=project'),
            'icon' => '📁',
            'class' => 'button'
        ),
        array(
            'label' => 'Dodaj osobę',
            'url' => admin_url('post-new.php?post_type=person'),
            'icon' => '👤',
            'class' => 'button'
        )
    )
));

// Pobranie projektów firmy
$active_projects = WPMZF_Project::get_active_projects_by_company($company_id);
$completed_projects = WPMZF_Project::get_completed_projects_by_company($company_id);

// Inicjalizujemy dane firmy
$company_nip = $company_fields['company_nip'] ?? '';
$company_email = $company_fields['company_email'] ?? '';
$company_phone = $company_fields['company_phone'] ?? '';
$company_website = $company_fields['company_website'] ?? '';
$company_address = $company_fields['company_address'] ?? [];
?>
        <style>
            /* Single Company View Styles - identyczne z osobą */
            
            /* Header styles */
            .person-header {
                background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);
                border: 1px solid #e1e5e9;
                border-radius: 12px;
                padding: 24px 28px;
                margin: 20px 0 28px;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
                display: flex;
                justify-content: space-between;
                align-items: center;
                position: relative;
                overflow: hidden;
            }

            .person-header::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                height: 4px;
                background: linear-gradient(90deg, #2271b1 0%, #1e90ff 50%, #00bcd4 100%);
            }

            .person-header-left {
                display: flex;
                flex-direction: column;
                gap: 8px;
            }

            .person-header h1 {
                margin: 0;
                font-size: 28px;
                font-weight: 700;
                color: #1d2327;
                line-height: 1.2;
                padding: 0 !important;
                text-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
            }

            .person-status-badge {
                display: inline-flex;
                align-items: center;
                padding: 4px 12px;
                border-radius: 16px;
                font-size: 12px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                width: fit-content;
            }

            .person-status-badge.status-active,
            .person-status-badge.status-aktywny {
                background: #d4edda;
                color: #155724;
                border: 1px solid #c3e6cb;
            }

            .person-status-badge.status-inactive,
            .person-status-badge.status-nieaktywny {
                background: #fff3cd;
                color: #856404;
                border: 1px solid #ffeaa7;
            }

            .person-status-badge.status-archived,
            .person-status-badge.status-zarchiwizowany {
                background: #f8d7da;
                color: #721c24;
                border: 1px solid #f5c6cb;
            }

            .person-header-actions {
                display: flex;
                gap: 12px;
                align-items: center;
            }

            .person-header-actions .button {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                text-decoration: none;
                font-size: 14px;
                font-weight: 500;
                padding: 10px 18px;
                border-radius: 6px;
                transition: all 0.3s ease;
                border: 1px solid transparent;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
            }

            .person-header-actions .button:not(.button-secondary) {
                background: #f8f9fa;
                color: #50575e;
                border-color: #e1e5e9;
            }

            .person-header-actions .button:not(.button-secondary):hover {
                background: #fff;
                border-color: #2271b1;
                color: #2271b1;
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(34, 113, 177, 0.15);
            }

            .person-header-actions .button-secondary {
                background: #2271b1;
                color: #fff;
                border-color: #2271b1;
            }

            .person-header-actions .button-secondary:hover {
                background: #1e5a8a;
                border-color: #1e5a8a;
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(34, 113, 177, 0.25);
            }

            .person-header-actions .archive-company-btn {
                background: #dc3545;
                border-color: #dc3545;
                color: #fff;
            }

            .person-header-actions .archive-company-btn:hover {
                background: #c82333;
                border-color: #c82333;
                box-shadow: 0 4px 12px rgba(220, 53, 69, 0.25);
            }

            .person-header-actions .unarchive-company-btn {
                background: #28a745;
                border-color: #28a745;
                color: #fff;
            }

            .person-header-actions .unarchive-company-btn:hover {
                background: #218838;
                border-color: #218838;
                box-shadow: 0 4px 12px rgba(40, 167, 69, 0.25);
            }

            .person-header-actions .button .dashicons {
                line-height: 1;
                font-size: 16px;
            }

            .dossier-grid {
                display: grid;
                grid-template-columns: 320px 1fr 360px;
                gap: 24px;
                margin-top: 0;
            }

            .dossier-left-column,
            .dossier-center-column,
            .dossier-right-column {
                display: flex;
                flex-direction: column;
                gap: 24px;
            }

            .dossier-box {
                background: #fff;
                border: 1px solid #e1e5e9;
                border-radius: 8px;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
                transition: all 0.2s ease;
            }

            .dossier-box:hover {
                border-color: #d0d5dd;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            }

            .dossier-box h2.dossier-title {
                font-size: 14px;
                font-weight: 600;
                padding: 16px 20px;
                margin: 0;
                border-bottom: 1px solid #e1e5e9;
                background: #f8f9fa;
                border-radius: 8px 8px 0 0;
                color: #1d2327;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .edit-data-button {
                font-size: 12px;
                font-weight: 500;
                color: #646970;
                text-decoration: none;
                padding: 6px 12px;
                border: 1px solid #e1e5e9;
                border-radius: 4px;
                background: #fff;
                transition: all 0.2s ease;
            }

            .edit-data-button:hover {
                color: #2271b1;
                border-color: #2271b1;
                background: #f6f7f7;
            }

            .dossier-box .dossier-content {
                padding: 20px;
            }

            .dossier-box .dossier-content p {
                margin: 0 0 12px;
                line-height: 1.6;
                color: #3c434a;
            }

            .dossier-box .dossier-content p:last-child {
                margin-bottom: 0;
            }

            .dossier-box .dossier-content p strong {
                color: #1d2327;
                font-weight: 500;
            }
            
            /* Contact styles */
            .contacts-list {
                margin-top: 8px;
            }
            
            .contact-item {
                display: flex;
                align-items: center;
                gap: 8px;
                margin-bottom: 6px;
                padding: 6px 0;
                border-bottom: 1px solid #f0f0f1;
            }
            
            .contact-item:last-child {
                border-bottom: none;
                margin-bottom: 0;
            }
            
            .contact-item.is-primary {
                background: #f6f7f7;
                padding: 8px 12px;
                border-radius: 4px;
                border: 1px solid #c3c4c7;
                margin-bottom: 8px;
            }
            
            .contact-link {
                color: #2271b1;
                text-decoration: none;
                font-weight: 500;
            }
            
            .contact-link:hover {
                color: #135e96;
                text-decoration: underline;
            }
            
            .primary-badge {
                background: #2271b1;
                color: #fff;
                font-size: 11px;
                font-weight: 500;
                padding: 2px 6px;
                border-radius: 10px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            
            .no-contacts {
                color: #8c8f94;
                font-style: italic;
                font-size: 14px;
            }
            
            .email-item .contact-link:before {
                content: "✉";
                margin-right: 6px;
                color: #8c8f94;
            }
            
            .phone-item .contact-link:before {
                content: "☎";
                margin-right: 6px;
                color: #8c8f94;
            }
            /* End of Contact styles */
            
            /* Projects/Orders styles */
            .projects-section {
                margin-bottom: 16px;
            }
            
            .projects-list {
                list-style: none;
                padding: 0;
                margin: 0;
            }
            
            .project-item {
                background: #f8f9fa;
                border: 1px solid #e1e5e9;
                border-radius: 6px;
                margin-bottom: 8px;
                padding: 12px;
                transition: all 0.2s ease;
            }
            
            .project-item:hover {
                border-color: #2271b1;
                background: #f6f7f7;
            }
            
            .project-item.active-project {
                border-left: 4px solid #2271b1;
            }
            
            .project-item.completed-project {
                border-left: 4px solid #8c8f94;
                opacity: 0.8;
            }
            
            .project-info {
                display: flex;
                flex-direction: column;
                gap: 4px;
            }
            
            .project-link {
                color: #2271b1;
                text-decoration: none;
                font-weight: 600;
                font-size: 14px;
                transition: color 0.2s ease;
            }
            
            .project-link:hover {
                color: #135e96;
                text-decoration: underline;
            }
            
            .project-deadline {
                color: #646970;
                font-size: 12px;
                font-weight: 500;
            }
            
            #toggle-completed-projects {
                display: flex;
                align-items: center;
                gap: 5px;
                cursor: pointer;
                color: #646970;
                transition: color 0.2s;
                border: none;
                background: none;
                padding: 0;
                font-size: 13px;
                font-weight: 600;
            }
            
            #toggle-completed-projects:hover {
                color: #2271b1;
            }
            
            #toggle-completed-projects .dashicons {
                transition: transform 0.2s;
                font-size: 16px;
            }
            
            #toggle-completed-projects.expanded .dashicons {
                transform: rotate(90deg);
            }
            
            #add-new-project-btn {
                background: #2271b1;
                color: #fff;
                border: none;
                padding: 6px 12px;
                border-radius: 4px;
                text-decoration: none;
                font-size: 12px;
                font-weight: 500;
                transition: all 0.2s ease;
            }
            
            #add-new-project-btn:hover {
                background: #135e96;
                color: #fff;
                transform: translateY(-1px);
            }
            /* End of Projects/Orders styles */

            /* Timeline styles for new structure */
            .timeline-item {
                display: flex;
                margin-bottom: 20px;
                background: #fff;
                border: 1px solid #e1e5e9;
                border-radius: 8px;
                padding: 16px;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            }

            .timeline-avatar {
                flex-shrink: 0;
                margin-right: 12px;
            }

            .timeline-avatar img {
                width: 40px;
                height: 40px;
                border-radius: 50%;
                border: 2px solid #e1e5e9;
            }

            .timeline-content {
                flex: 1;
            }

            .timeline-header {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                margin-bottom: 8px;
            }

            .timeline-header-left {
                flex: 1;
            }

            .timeline-header-meta {
                display: flex;
                align-items: center;
                gap: 6px;
                margin-bottom: 4px;
            }

            .timeline-header-meta .dashicons {
                color: #2271b1;
                font-size: 16px;
            }

            .timeline-header-date {
                font-size: 12px;
                color: #646970;
            }

            .timeline-actions {
                display: flex;
                gap: 4px;
                opacity: 0;
                transition: opacity 0.2s ease;
            }

            .timeline-item:hover .timeline-actions {
                opacity: 1;
            }

            .timeline-actions .dashicons {
                cursor: pointer;
                color: #646970;
                font-size: 16px;
                padding: 4px;
                border-radius: 3px;
                transition: all 0.2s ease;
            }

            .timeline-actions .dashicons:hover {
                color: #2271b1;
                background: rgba(34, 113, 177, 0.1);
            }

            .timeline-actions .dashicons-trash:hover {
                color: #d63638;
                background: rgba(214, 54, 56, 0.1);
            }

            .timeline-body {
                color: #3c434a;
                line-height: 1.6;
            }

            .activity-content-edit {
                margin-top: 8px;
            }

            .activity-edit-textarea {
                width: 100%;
                min-height: 80px;
                padding: 8px;
                border: 1px solid #ddd;
                border-radius: 4px;
                font-family: inherit;
                font-size: 14px;
                resize: vertical;
            }

            .timeline-edit-actions {
                margin-top: 8px;
                display: flex;
                gap: 8px;
            }

            .timeline-edit-actions .button {
                padding: 6px 12px;
                font-size: 13px;
            }

            /* Timeline attachments styles */
            .timeline-attachments {
                margin-top: 10px;
                border-top: 1px dashed #e0e0e0;
                padding-top: 10px;
            }

            .timeline-attachments strong {
                display: block;
                margin-bottom: 5px;
                font-size: 13px;
            }

            .timeline-attachments ul {
                margin: 0;
                padding: 0;
                list-style: none;
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
            }

            .timeline-attachments ul li {
                display: flex;
                align-items: center;
                gap: 4px;
                background: #f0f0f1;
                border: 1px solid #dcdcde;
                border-radius: 3px;
                padding: 4px 8px;
            }

            .timeline-attachments ul li a {
                display: inline-flex;
                align-items: center;
                gap: 5px;
                text-decoration: none;
                font-size: 13px;
                color: #2271b1;
            }

            .timeline-attachments ul li a:hover {
                text-decoration: underline;
            }

            .timeline-attachments ul li a .dashicons {
                font-size: 16px;
            }

            .timeline-attachments .delete-attachment {
                cursor: pointer;
                color: #646970;
                font-size: 14px;
                padding: 2px;
                border-radius: 2px;
                transition: all 0.2s ease;
            }

            .timeline-attachments .delete-attachment:hover {
                color: #d63638;
                background: rgba(214, 54, 56, 0.1);
            }

            #activity-timeline {
                list-style: none;
                padding: 0;
                margin: 15px 0 0;
                position: relative;
            }

            #activity-timeline:before {
                content: '';
                position: absolute;
                top: 5px;
                left: 5px;
                bottom: 5px;
                width: 2px;
                background: #e0e0e0;
            }

            #activity-timeline li {
                padding-left: 25px;
                position: relative;
                margin-bottom: 15px;
            }

            #activity-timeline li:before {
                content: '';
                position: absolute;
                left: 0;
                top: 5px;
                width: 12px;
                height: 12px;
                border-radius: 50%;
                background: #fff;
                border: 2px solid #0071a1;
            }

            #activity-timeline li strong {
                display: block;
                color: #50575e;
            }

            /* Task styles */
            .task-input-wrapper {
                display: flex;
                gap: 8px;
                align-items: center;
            }

            .task-input-wrapper input[type="text"] {
                flex: 1;
                padding: 8px;
                border: 1px solid #8c8f94;
                border-radius: 3px;
            }

            .task-due-date-wrapper label {
                display: block;
                margin-bottom: 5px;
                font-weight: 600;
            }

            .task-due-date-wrapper input[type="datetime-local"] {
                width: 100%;
                padding: 8px;
                border: 1px solid #ddd;
                border-radius: 4px;
            }

            .task-submit-wrapper {
                margin-top: 15px;
            }

            .task-item {
                background: #f9f9f9;
                border: 1px solid #e0e0e0;
                border-radius: 4px;
                padding: 8px 10px;
                margin-bottom: 6px;
                position: relative;
            }

            .task-item.overdue {
                border-left: 4px solid #dc3232;
                background: #fdf2f2;
            }

            .task-item.today {
                border-left: 4px solid #ffb900;
                background: #fffbf0;
            }

            .task-item.upcoming {
                border-left: 4px solid #2271b1;
                background: #f0f6fc;
            }

            .task-item.completed {
                background: #f0f0f1;
                opacity: 0.7;
            }

            .task-content {
                width: 100%;
            }

            .task-title-row {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                margin-bottom: 4px;
            }

            .task-title {
                font-weight: 600;
                color: #23282d;
                margin: 0;
                font-size: 14px;
                flex: 1;
                line-height: 1.2;
                margin-right: 8px;
            }

            .task-meta-row {
                display: flex;
                justify-content: flex-start;
                align-items: center;
                font-size: 11px;
            }

            .task-meta-left {
                display: flex;
                align-items: center;
                gap: 6px;
                flex-wrap: wrap;
            }

            .task-meta-right {
                display: flex;
                align-items: center;
            }

            .task-meta {
                font-size: 12px;
                color: #646970;
                display: flex;
                flex-direction: column;
                align-items: flex-end;
                gap: 2px;
            }

            .task-status {
                display: inline-block;
                padding: 2px 6px;
                border-radius: 10px;
                font-size: 10px;
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
                background: #d4edda;
                color: #155724;
            }

            .task-date {
                display: inline-block;
                padding: 2px 6px;
                background: #f0f0f1;
                border-radius: 3px;
                font-size: 11px;
                font-weight: 500;
                color: #646970;
            }

            .task-date.overdue {
                background: #dc3232;
                color: white;
            }

            .task-date.today {
                background: #ffb900;
                color: white;
            }

            .task-date.upcoming {
                background: #2271b1;
                color: white;
            }

            .task-priority-indicator {
                display: inline-block;
                padding: 2px 5px;
                border-radius: 3px;
                font-size: 9px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            .task-priority-indicator.overdue {
                background: #dc3232;
                color: white;
            }

            .task-priority-indicator.today {
                background: #ffb900;
                color: white;
            }

            .task-priority-indicator.upcoming {
                background: #2271b1;
                color: white;
            }

            .task-actions {
                display: flex;
                gap: 2px;
                align-items: center;
                flex-shrink: 0;
            }

            .task-actions .dashicons {
                cursor: pointer;
                color: #787c82;
                font-size: 12px;
                padding: 1px;
                border-radius: 2px;
                transition: all 0.2s ease;
            }

            .task-actions .dashicons:hover {
                color: #2271b1;
                background: rgba(34, 113, 177, 0.1);
            }

            #wpmzf-toggle-closed-tasks {
                display: flex;
                align-items: center;
                gap: 5px;
                cursor: pointer;
                color: #646970;
                transition: color 0.2s;
            }

            #wpmzf-toggle-closed-tasks:hover {
                color: #2271b1;
            }

            #wpmzf-toggle-closed-tasks .dashicons {
                transition: transform 0.2s;
            }

            #wpmzf-toggle-closed-tasks.expanded .dashicons {
                transform: rotate(90deg);
            }

            .task-edit-input {
                width: 100%;
                padding: 5px;
                border: 1px solid #ddd;
                border-radius: 3px;
                font-size: 14px;
                font-weight: 600;
            }

            #task-date-edit-modal {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
                z-index: 10000;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            #task-date-edit-modal > div {
                background: white;
                padding: 20px;
                border-radius: 8px;
                width: 400px;
                max-width: 90%;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            }

            #task-date-edit-modal h3 {
                margin-top: 0;
                color: #23282d;
            }

            #task-date-edit-input {
                width: 100%;
                padding: 8px;
                border: 1px solid #ddd;
                border-radius: 4px;
                margin-bottom: 15px;
                font-size: 14px;
            }

            .task-message {
                margin: 10px 0;
                padding: 8px 12px;
                border-radius: 4px;
                border-left: 4px solid transparent;
            }

            .task-message.notice-success {
                background: #d4edda;
                border-left-color: #155724;
                color: #155724;
            }

            .task-message.notice-error {
                background: #f8d7da;
                border-left-color: #721c24;
                color: #721c24;
            }
            
            /* Important Links Styles */
            #important-links-container {
                min-height: 60px;
            }
            
            .important-link-item {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 12px;
                border: 1px solid #e1e5e9;
                border-radius: 6px;
                margin-bottom: 8px;
                background: #fff;
                transition: all 0.2s ease;
                position: relative;
            }
            
            .important-link-item:hover {
                border-color: #2271b1;
                box-shadow: 0 2px 8px rgba(34, 113, 177, 0.15);
            }
            
            .important-link-favicon {
                flex-shrink: 0;
                width: 20px;
                height: 20px;
                background: #f0f0f1;
                border-radius: 3px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .important-link-favicon img {
                width: 16px;
                height: 16px;
                border-radius: 2px;
            }
            
            .important-link-content {
                flex: 1;
                min-width: 0;
            }
            
            .important-link-title {
                font-weight: 500;
                color: #1d2327;
                margin: 0 0 4px 0;
                font-size: 14px;
                line-height: 1.3;
                display: block;
                text-decoration: none;
                word-break: break-word;
            }
            
            .important-link-title:hover {
                color: #2271b1;
                text-decoration: underline;
            }
            
            .important-link-url {
                font-size: 12px;
                color: #646970;
                margin: 0;
                word-break: break-all;
            }
            
            .important-link-actions {
                display: flex;
                gap: 4px;
                opacity: 0;
                transition: opacity 0.2s ease;
            }
            
            .important-link-item:hover .important-link-actions {
                opacity: 1;
            }
            
            .important-link-action {
                padding: 4px 6px;
                border: 1px solid #c3c4c7;
                background: #fff;
                color: #646970;
                text-decoration: none;
                border-radius: 3px;
                font-size: 12px;
                cursor: pointer;
                transition: all 0.2s ease;
            }
            
            .important-link-action:hover {
                border-color: #2271b1;
                color: #2271b1;
            }
            
            .important-link-action.delete:hover {
                border-color: #d63638;
                color: #d63638;
                background: #fef7f7;
            }
            
            #important-link-form {
                background: #f8f9fa;
                border: 1px solid #e1e5e9;
                border-radius: 6px;
                padding: 16px;
                margin-top: 12px;
            }
            
            #important-link-form .form-group {
                margin-bottom: 12px;
            }
            
            #important-link-form label {
                display: block;
                margin-bottom: 4px;
                font-weight: 500;
                color: #1d2327;
            }
            
            #important-link-form input[type="url"],
            #important-link-form input[type="text"] {
                width: 100%;
                padding: 8px 12px;
                border: 1px solid #c3c4c7;
                border-radius: 4px;
                font-size: 14px;
                background: #fff;
            }
            
            #important-link-form input:focus {
                outline: none;
                border-color: #2271b1;
                box-shadow: 0 0 0 1px #2271b1;
            }
            
            .no-important-links {
                text-align: center;
                color: #646970;
                font-style: italic;
                padding: 20px;
            }
            
            .important-links-loading {
                text-align: center;
                color: #646970;
                padding: 16px;
            }
        </style>

        <div class="wrap">
            <div class="person-header">
                <div class="person-header-left">
                    <h1><?php echo esc_html($company_title); ?></h1>
                    <?php 
                    // Pobieramy status firmy
                    $current_status = $company_fields['company_status'] ?? 'Aktywny';
                    
                    // Ustalamy CSS class na podstawie statusu
                    $css_class = 'status-aktywny'; // domyślna
                    if (in_array($current_status, ['Nieaktywny'])) {
                        $css_class = 'status-nieaktywny';
                    } elseif (in_array($current_status, ['Zarchiwizowany'])) {
                        $css_class = 'status-zarchiwizowany';
                    }
                    ?>
                    <div class="person-status-badge <?php echo esc_attr($css_class); ?>">
                        <?php echo esc_html($current_status); ?>
                    </div>
                </div>
                <div class="person-header-actions">
                    <?php 
                    // Sprawdzamy czy firma jest zarchiwizowana
                    $is_archived = ($current_status === 'Zarchiwizowany');
                    ?>
                    <?php if (!$is_archived): ?>
                    <button id="archive-company-btn" class="button archive-company-btn" data-company-id="<?php echo $company_id; ?>">
                        <span class="dashicons dashicons-archive"></span>
                        Archiwizuj
                    </button>
                    <?php else: ?>
                    <button id="unarchive-company-btn" class="button unarchive-company-btn" data-company-id="<?php echo $company_id; ?>">
                        <span class="dashicons dashicons-backup"></span>
                        Przywróć
                    </button>
                    <?php endif; ?>
                    <a href="<?php echo admin_url('post.php?post=' . $company_id . '&action=edit'); ?>" class="button button-secondary">
                        <span class="dashicons dashicons-edit"></span>
                        Edytuj
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=wpmzf-companies')); ?>" class="button">
                        <span class="dashicons dashicons-arrow-left-alt"></span>
                        Powrót do listy
                    </a>
                </div>
            </div>

            <div class="dossier-grid">
                <!-- Lewa kolumna - Dane podstawowe -->
                <div class="dossier-left-column">
                    <div class="dossier-box" id="dossier-basic-data">
                        <h2 class="dossier-title">
                            Dane podstawowe
                            <a href="<?php echo admin_url('post.php?post=' . $company_id . '&action=edit'); ?>" class="edit-data-button">Edytuj</a>
                        </h2>
                        <div class="dossier-content">
                            <p><strong>Nazwa firmy:</strong> <?php echo esc_html($company_title); ?></p>
                            <p><strong>NIP:</strong> <?php echo esc_html($company_nip ?: 'Brak'); ?></p>
                            <p><strong>E-maile:</strong> 
                                <div>
                                    <?php echo WPMZF_Contact_Helper::render_emails_display(WPMZF_Contact_Helper::get_company_emails($company_id)); ?>
                                </div>
                            </p>
                            <p><strong>Telefony:</strong> 
                                <div>
                                    <?php echo WPMZF_Contact_Helper::render_phones_display(WPMZF_Contact_Helper::get_company_phones($company_id)); ?>
                                </div>
                            </p>
                            <p><strong>Strona WWW:</strong> 
                                <?php if ($company_website): ?>
                                    <a href="<?php echo esc_url($company_website); ?>" target="_blank"><?php echo esc_html($company_website); ?></a>
                                <?php else: ?>
                                    Brak
                                <?php endif; ?>
                            </p>
                            <p><strong>Adres:</strong> <?php
                            $address_parts = [];
                            if (is_array($company_address)) {
                                $address_parts = [
                                    (string)($company_address['street'] ?? ''),
                                    (string)($company_address['zip_code'] ?? ''),
                                    (string)($company_address['city'] ?? '')
                                ];
                            }
                            $address = implode(', ', array_filter($address_parts));
                            echo esc_html($address ?: 'Brak');
                            ?></p>
                            <p><strong>Status:</strong> <?php echo esc_html($current_status); ?></p>
                            <p><strong>Polecający:</strong> 
                                <?php
                                $referrer = get_field('company_referrer', $company_id);
                                if ($referrer && is_array($referrer) && !empty($referrer)) {
                                    $referrer_post = get_post($referrer[0]);
                                    if ($referrer_post) {
                                        $referrer_type = get_post_type($referrer_post->ID) === 'company' ? '🏢' : '👤';
                                        echo $referrer_type . ' ' . esc_html($referrer_post->post_title);
                                    } else {
                                        echo 'Brak';
                                    }
                                } else {
                                    echo 'Brak';
                                }
                                ?>
                            </p>
                        </div>
                    </div>
                    
                    <!-- Sekcja Projektów -->
                    <div class="dossier-box">
                        <h2 class="dossier-title">
                            Projekty
                            <a href="<?php echo admin_url('post-new.php?post_type=project&company_id=' . $company_id); ?>" class="edit-data-button">Nowy projekt</a>
                        </h2>
                        <div class="dossier-content">
                            <?php if (!empty($active_projects)): ?>
                            <div class="projects-section">
                                <h4>Aktywne projekty (<?php echo count($active_projects); ?>)</h4>
                                <ul class="projects-list">
                                    <?php foreach ($active_projects as $project): ?>
                                    <li class="project-item active-project">
                                        <div class="project-info">
                                            <a href="#" class="project-link" data-project-id="<?php echo $project->ID; ?>">
                                                <?php echo esc_html($project->get_title()); ?>
                                            </a>
                                            <span class="project-deadline">
                                                Status: <?php echo esc_html($project->get_status()); ?>
                                                <?php if ($project->get_start_date()): ?>
                                                    | Rozpoczęto: <?php echo esc_html(date('d.m.Y', strtotime($project->get_start_date()))); ?>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($completed_projects)): ?>
                            <div class="projects-section">
                                <button id="toggle-completed-projects" type="button">
                                    <span class="dashicons dashicons-arrow-right"></span>
                                    Zakończone projekty (<?php echo count($completed_projects); ?>)
                                </button>
                                <ul class="projects-list" id="completed-projects-list" style="display: none;">
                                    <?php foreach ($completed_projects as $project): ?>
                                    <li class="project-item completed-project">
                                        <div class="project-info">
                                            <a href="#" class="project-link" data-project-id="<?php echo $project->ID; ?>">
                                                <?php echo esc_html($project->get_title()); ?>
                                            </a>
                                            <span class="project-deadline">
                                                Status: <?php echo esc_html($project->get_status()); ?>
                                                <?php if ($project->get_end_date()): ?>
                                                    | Zakończono: <?php echo esc_html(date('d.m.Y', strtotime($project->get_end_date()))); ?>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <?php endif; ?>

                            <?php if (empty($active_projects) && empty($completed_projects)): ?>
                            <p class="no-contacts">Brak projektów dla tej firmy.</p>
                            <a href="<?php echo admin_url('post-new.php?post_type=project&company_id=' . $company_id); ?>" id="add-new-project-btn">
                                Dodaj pierwszy projekt
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Sekcja Ważnych Linków -->
                    <div class="dossier-box" id="important-links-section">
                        <h2 class="dossier-title">
                            Ważne linki
                            <button type="button" id="add-important-link-btn" class="edit-data-button">Dodaj link</button>
                        </h2>
                        <div class="dossier-content">
                            <div id="important-links-container">
                                <p><em>Ładowanie linków...</em></p>
                            </div>
                            
                            <!-- Formularz dodawania/edycji linku -->
                            <div id="important-link-form" style="display: none;">
                                <form id="wpmzf-important-link-form">
                                    <?php wp_nonce_field('wpmzf_company_view_nonce', 'wpmzf_link_security'); ?>
                                    <input type="hidden" name="company_id" value="<?php echo esc_attr($company_id); ?>">
                                    <input type="hidden" name="object_id" value="<?php echo esc_attr($company_id); ?>">
                                    <input type="hidden" name="object_type" value="company">
                                    <input type="hidden" name="link_id" id="edit-link-id" value="">
                                    
                                    <div class="form-group">
                                        <label for="link-url">URL linku:</label>
                                        <input type="url" id="link-url" name="url" placeholder="https://example.com" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                    </div>
                                    
                                    <div class="form-group" style="margin-top: 10px;">
                                        <label for="link-custom-title">Niestandardowy opis (opcjonalnie):</label>
                                        <input type="text" id="link-custom-title" name="custom_title" placeholder="Jeśli pozostawisz puste, pobierzemy automatycznie tytuł strony" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                        <small style="color: #666;">Jeśli nie wpiszesz opisu, automatycznie pobierzemy tytuł strony</small>
                                    </div>
                                    
                                    <div class="form-actions" style="margin-top: 15px; display: flex; gap: 10px;">
                                        <button type="submit" class="button button-primary">
                                            <span id="link-submit-text">Dodaj link</span>
                                        </button>
                                        <button type="button" id="cancel-link-form" class="button">Anuluj</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Środkowa kolumna - Aktywności -->
                <div class="dossier-center-column">
                    <div class="dossier-box">
                        <h2 class="dossier-title">Nowa Aktywność</h2>
                        <div class="dossier-content">
                            <div id="wpmzf-activity-box">
                                <div class="activity-tabs">
                                    <button class="tab-link active" data-tab="note">📝 Dodaj notatkę</button>
                                    <button class="tab-link" data-tab="email">✉️ Wyślij e-mail</button>
                                </div>

                                <div id="note-tab-content" class="tab-content active">
                                    <form id="wpmzf-add-note-form" method="post" enctype="multipart/form-data">
                                        <?php wp_nonce_field('wpmzf_person_view_nonce', 'wpmzf_note_security'); ?>
                                        <input type="hidden" name="company_id" value="<?php echo esc_attr($company_id); ?>">
                                        
                                        <input type="file" id="wpmzf-note-files-input" name="activity_files[]" multiple style="display: none;">

                                        <div id="wpmzf-note-main-editor">
                                            <div id="wpmzf-note-editor-placeholder" class="wpmzf-editor-placeholder">
                                                <div class="placeholder-text">Opisz co się wydarzyło... (np. odbyłem spotkanie, wysłałem ofertę z prywatnej skrzynki, itp.)</div>
                                            </div>
                                            
                                            <div id="wpmzf-note-editor-container" style="display: none;">
                                                <?php
                                                wp_editor('', 'wpmzf-note-content', array(
                                                    'textarea_name' => 'content',
                                                    'textarea_rows' => 4,
                                                    'media_buttons' => false,
                                                    'teeny' => true,
                                                    'quicktags' => true,
                                                    'tinymce' => array(
                                                        'toolbar1' => 'bold,italic,underline,forecolor,bullist,numlist,link,unlink,removeformat,undo,redo',
                                                        'toolbar2' => '',
                                                        'height' => 120,
                                                        'plugins' => 'lists,link,paste,textcolor'
                                                    )
                                                ));
                                                ?>
                                            </div>
                                        </div>

                                        <div class="activity-meta-controls">
                                            <div class="activity-options">
                                                <div>
                                                    <label for="note-type">Typ zdarzenia:</label>
                                                    <select id="note-type" name="activity_type">
                                                        <option value="notatka">Notatka</option>
                                                        <option value="email">E-mail (wysłany poza systemem)</option>
                                                        <option value="telefon">Telefon</option>
                                                        <option value="spotkanie">Spotkanie</option>
                                                        <option value="spotkanie-online">Spotkanie online</option>
                                                    </select>
                                                </div>
                                                
                                                <div>
                                                    <label for="wpmzf-note-date">Data aktywności:</label>
                                                    <input type="datetime-local" id="wpmzf-note-date" name="activity_date" value="<?php echo date('Y-m-d\TH:i'); ?>">
                                                </div>
                                            </div>
                                            <div class="activity-actions">
                                                <button type="button" id="wpmzf-note-attach-files-btn" class="button">
                                                    <span class="dashicons dashicons-paperclip"></span> Dodaj załączniki
                                                </button>
                                                <button type="submit" class="button button-primary">Dodaj notatkę</button>
                                            </div>
                                        </div>
                                        <div id="wpmzf-note-attachments-preview-container"></div>
                                    </form>
                                </div>

                                <div id="email-tab-content" class="tab-content">
                                    <form id="wpmzf-send-email-form" method="post">
                                        <?php wp_nonce_field('wpmzf_person_view_nonce', 'wpmzf_email_security'); ?>
                                        <input type="hidden" name="company_id" value="<?php echo esc_attr($company_id); ?>">
                                        
                                        <div class="email-fields-grid">
                                            <input type="email" name="email_to" placeholder="Do:" required>
                                            <input type="email" name="email_cc" placeholder="DW:">
                                            <input type="email" name="email_bcc" placeholder="UDW:">
                                            <input type="text" name="email_subject" placeholder="Temat wiadomości" required>
                                        </div>
                                        
                                        <?php
                                        wp_editor('', 'email-content', array(
                                            'textarea_name' => 'content',
                                            'textarea_rows' => 6,
                                            'media_buttons' => true,
                                            'tinymce' => array(
                                                'toolbar1' => 'bold,italic,underline,forecolor,bullist,numlist,link,unlink,removeformat,undo,redo',
                                                'toolbar2' => '',
                                                'height' => 150,
                                                'plugins' => 'lists,link,paste,textcolor'
                                            )
                                        ));
                                        ?>
                                        
                                        <div class="activity-meta-controls">
                                            <div></div>
                                            <button type="submit" class="button button-primary">Wyślij e-mail</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="wpmzf-activity-timeline-container" class="dossier-box">
                        <h2 class="dossier-title">Historia Aktywności</h2>
                        <div id="wpmzf-activity-timeline" class="dossier-content">
                            <p><em>Ładowanie aktywności...</em></p>
                        </div>
                    </div>
                </div>

                <!-- Prawa kolumna - Zadania -->
                <div class="dossier-right-column">
                    <div class="dossier-box">
                        <h2 class="dossier-title">Nowe Zadanie</h2>
                        <div class="dossier-content">
                            <form id="wpmzf-add-task-form">
                                <?php wp_nonce_field('wpmzf_task_nonce', 'wpmzf_task_security'); ?>
                                <input type="hidden" name="company_id" value="<?php echo esc_attr($company_id); ?>">
                                
                                <div class="task-input-wrapper">
                                    <input type="text" id="wpmzf-task-title" name="task_title" placeholder="Wpisz treść zadania..." required>
                                </div>
                                <div class="task-form-row" style="margin-top: 10px; display: flex; gap: 15px;">
                                    <div class="task-assigned-user-wrapper" style="flex: 1;">
                                        <label for="wpmzf-task-assigned-user" style="display: block; margin-bottom: 5px; font-weight: 600;">Odpowiedzialny:</label>
                                        <?php
                                        echo WPMZF_Employee_Helper::render_employee_select(
                                            'assigned_user',
                                            0,
                                            [
                                                'id' => 'wpmzf-task-assigned-user',
                                                'style' => 'width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;'
                                            ]
                                        );
                                        ?>
                                    </div>
                                    <div class="task-due-date-wrapper" style="flex: 1;">
                                        <label for="wpmzf-task-due-date" style="display: block; margin-bottom: 5px; font-weight: 600;">Termin wykonania:</label>
                                        <input type="datetime-local" id="wpmzf-task-due-date" name="task_due_date" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                    </div>
                                </div>
                                <div class="task-submit-wrapper" style="margin-top: 15px;">
                                    <button type="submit" class="button button-primary">Dodaj zadanie</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div id="wpmzf-tasks-container" class="dossier-box">
                        <h2 class="dossier-title">Zadania</h2>
                        <div class="dossier-content">
                            <div id="wpmzf-open-tasks">
                                <div id="wpmzf-open-tasks-list">
                                    <p><em>Ładowanie zadań...</em></p>
                                </div>
                            </div>
                            <div id="wpmzf-closed-tasks" style="margin-top: 20px;">
                                <h4 style="cursor: pointer;" id="wpmzf-toggle-closed-tasks">
                                    <span class="dashicons dashicons-arrow-right"></span> 
                                    Zakończone zadania
                                </h4>
                                <div id="wpmzf-closed-tasks-list" style="display: none;">
                                    <p><em>Ładowanie zakończonych zadań...</em></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- JavaScript został przeniesiony do assets/js/admin/company-view.js -->
        <!-- Skrypt jest ładowany przez enqueue_person_view_scripts() w class-wpmzf-admin-pages.php -->
        <input type="hidden" name="company_id" value="<?php echo esc_attr($company_id); ?>" />
        <input type="hidden" id="wpmzf_security" value="<?php echo wp_create_nonce('wpmzf_company_view_nonce'); ?>" />
        <input type="hidden" id="wpmzf_security" value="<?php echo wp_create_nonce('wpmzf_company_view_nonce'); ?>" />
        <input type="hidden" id="wpmzf_task_security" value="<?php echo wp_create_nonce('wpmzf_task_nonce'); ?>" />

<?php
// Koniec pliku widoku firmy
