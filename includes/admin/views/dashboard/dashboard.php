<?php
/**
 * Widok Dashboard
 *
 * @package WPMZF
 * @subpackage Admin/Views
 */

if (!defined('ABSPATH')) {
    exit;
}

// Include komponentu Timeline
require_once WPMZF_PLUGIN_PATH . 'includes/admin/components/timeline/class-wpmzf-timeline.php';

// Renderuj nawigację i header
WPMZF_View_Helper::render_complete_header(array(
    'title' => 'Dashboard',
    'subtitle' => 'Dzisiaj: ' . date_i18n('j F Y', current_time('timestamp')),
    'breadcrumbs' => array(
        array('label' => 'Dashboard', 'url' => '')
    ),
    'actions' => array(
        array(
            'label' => 'Dodaj firmę',
            'url' => admin_url('post-new.php?post_type=company'),
            'icon' => '🏢',
            'class' => 'button button-primary'
        ),
        array(
            'label' => 'Dodaj osobę',
            'url' => admin_url('post-new.php?post_type=person'),
            'icon' => '👤',
            'class' => 'button'
        )
    )
));

// Pobieramy dane dla dashboardu
$current_user_id = get_current_user_id();
$today = current_time('Y-m-d');
$now = current_time('Y-m-d H:i:s');

// Pobieranie danych do dashboardu
$companies_count = wp_count_posts('company')->publish;
$persons_count = wp_count_posts('person')->publish;
$projects_count = wp_count_posts('project')->publish;
$time_entries_count = wp_count_posts('time_entry')->publish;

// Statystyki użytkowników - dodane dzisiaj
$today_users = get_users([
    'date_query' => [
        [
            'year' => date('Y'),
            'month' => date('m'),
            'day' => date('d')
        ]
    ]
]);

// Ostatnie firmy
$recent_companies_query = new WP_Query([
    'post_type' => 'company',
    'posts_per_page' => 5,
    'post_status' => 'publish',
    'orderby' => 'date',
    'order' => 'DESC'
]);

// Statystyki szans sprzedaży 
$opportunity_stats = [
    'by_status' => [],
    'total_value' => 0,
    'count' => 0
];

$opportunities_due_soon = [];

// Pobierz szanse sprzedaży jeśli klasa istnieje
if (class_exists('WPMZF_Opportunity')) {
    $opportunities_query = new WP_Query([
        'post_type' => 'opportunity',
        'posts_per_page' => -1,
        'post_status' => 'publish'
    ]);
    
    if ($opportunities_query->have_posts()) {
        $status_counts = [];
        $status_values = [];
        
        while ($opportunities_query->have_posts()) {
            $opportunities_query->the_post();
            $opportunity_id = get_the_ID();
            $status = get_field('opportunity_status', $opportunity_id) ?: 'Nowy';
            $value = (float) get_field('opportunity_value', $opportunity_id);
            $expected_close = get_field('opportunity_expected_close_date', $opportunity_id);
            
            // Statystyki po statusach
            if (!isset($status_counts[$status])) {
                $status_counts[$status] = 0;
                $status_values[$status] = 0;
            }
            $status_counts[$status]++;
            $status_values[$status] += $value;
            
            // Szanse kończące się wkrótce (w ciągu 7 dni)
            if ($expected_close && strtotime($expected_close) <= strtotime('+7 days')) {
                $opportunities_due_soon[] = new WPMZF_Opportunity($opportunity_id);
            }
        }
        wp_reset_postdata();
        
        foreach ($status_counts as $status => $count) {
            $opportunity_stats['by_status'][$status] = [
                'count' => $count,
                'value' => $status_values[$status]
            ];
            $opportunity_stats['total_value'] += $status_values[$status];
            $opportunity_stats['count'] += $count;
        }
    }
}
?>

<style>
/* Dashboard Layout */
.dashboard-container {
    display: grid;
    grid-template-columns: 300px 1fr 320px;
    gap: 24px;
    margin-top: 20px;
}

.dashboard-left-column,
.dashboard-center-column,
.dashboard-right-column {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.dashboard-box {
    background: #fff;
    border: 1px solid #e1e5e9;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
    overflow: hidden;
    transition: all 0.3s ease;
}

.dashboard-box:hover {
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
    transform: translateY(-2px);
}

.dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
    border-bottom: 1px solid #e1e5e9;
}

.dashboard-title {
    font-size: 16px;
    font-weight: 600;
    margin: 0;
    color: #1d2327;
    display: flex;
    align-items: center;
    gap: 8px;
}

.dashboard-title .dashicons {
    color: #2271b1;
    font-size: 18px;
}

.dashboard-content {
    padding: 20px;
}

/* Statystyki */
.stats-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.stat-item {
    text-align: center;
    padding: 16px;
    background: linear-gradient(135deg, #f0f6fc 0%, #ffffff 100%);
    border-radius: 8px;
    border: 1px solid #e3f2fd;
}

.stat-number {
    font-size: 24px;
    font-weight: 700;
    color: #2271b1;
    display: block;
    margin-bottom: 4px;
}

.stat-label {
    font-size: 12px;
    color: #646970;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 500;
}

/* Ostatnie firmy */
.company-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 0;
    border-bottom: 1px solid #f0f0f1;
}

.company-item:last-child {
    border-bottom: none;
}

.company-avatar {
    width: 36px;
    height: 36px;
    background: linear-gradient(135deg, #2271b1, #72aee6);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-weight: 600;
    font-size: 14px;
}

.company-info {
    flex: 1;
}

.company-name {
    font-weight: 500;
    color: #1d2327;
    text-decoration: none;
    display: block;
    margin-bottom: 2px;
}

.company-name:hover {
    color: #2271b1;
}

.company-date {
    font-size: 12px;
    color: #646970;
}

/* Szanse sprzedaży */
.opportunities-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 12px;
    margin-bottom: 20px;
}

.opportunity-status-item {
    text-align: center;
    padding: 16px 12px;
    border-radius: 8px;
    border: 1px solid #e1e5e9;
    background: #fff;
}

.opportunity-status-item.status-nowy {
    background: linear-gradient(135deg, #fff9c4 0%, #ffffff 100%);
    border-color: #fbc02d;
}

.opportunity-status-item.status-w-toku {
    background: linear-gradient(135deg, #e3f2fd 0%, #ffffff 100%);
    border-color: #2196f3;
}

.opportunity-status-item.status-wygrany {
    background: linear-gradient(135deg, #e8f5e8 0%, #ffffff 100%);
    border-color: #4caf50;
}

.opportunity-status-item.status-przegrany {
    background: linear-gradient(135deg, #ffebee 0%, #ffffff 100%);
    border-color: #f44336;
}

.status-count {
    font-size: 20px;
    font-weight: 700;
    color: #1d2327;
    display: block;
    margin-bottom: 4px;
}

.status-name {
    font-size: 11px;
    color: #646970;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 4px;
}

.status-value {
    font-size: 12px;
    color: #2271b1;
    font-weight: 500;
}

.opportunities-due-soon {
    border-top: 1px solid #f0f0f1;
    padding-top: 16px;
}

.opportunity-due-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #f8f9fa;
}

.opportunity-due-item:last-child {
    border-bottom: none;
}

.opportunity-due-item.overdue {
    background: linear-gradient(90deg, rgba(244, 67, 54, 0.05) 0%, transparent 100%);
    border-left: 3px solid #f44336;
    padding-left: 8px;
    margin-left: -8px;
}

.opportunity-title {
    font-weight: 500;
    color: #1d2327;
    text-decoration: none;
    display: block;
    margin-bottom: 2px;
}

.opportunity-title:hover {
    color: #2271b1;
}

.opportunity-meta {
    font-size: 12px;
    color: #646970;
}

.days-left {
    font-size: 11px;
    padding: 2px 6px;
    border-radius: 8px;
    font-weight: 500;
}

.days-left.today {
    background: #fff3cd;
    color: #856404;
}

.days-left.overdue {
    background: #f8d7da;
    color: #721c24;
}

.days-left:not(.today):not(.overdue) {
    background: #d1ecf1;
    color: #0c5460;
}

/* Timeline w dashboard */
#dashboard-timeline-section .wpmzf-timeline-container {
    margin: 0;
}

#dashboard-timeline-section .timeline-header {
    display: none; /* Ukrywamy przycisk "Dodaj aktywność" w dashboard */
}

#dashboard-timeline-section .timeline-content {
    padding-left: 20px;
}

#dashboard-timeline-section .timeline-content::before {
    left: 10px;
    background: linear-gradient(to bottom, #2271b1, #e9ecef);
}

#dashboard-timeline-section .timeline-item {
    margin-bottom: 16px;
}

#dashboard-timeline-section .timeline-marker {
    margin-left: -20px;
}

#dashboard-timeline-section .timeline-avatar {
    width: 28px;
    height: 28px;
    border-width: 2px;
}

#dashboard-timeline-section .timeline-icon {
    width: 14px;
    height: 14px;
    bottom: -1px;
    right: -1px;
}

#dashboard-timeline-section .timeline-icon .dashicons {
    font-size: 7px;
}

#dashboard-timeline-section .timeline-content-item {
    border-radius: 6px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.05);
    border: 1px solid #f0f0f1;
}

#dashboard-timeline-section .timeline-content-item:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transform: translateY(-1px);
    border-color: #e1e5e9;
}

#dashboard-timeline-section .timeline-header {
    padding: 10px 14px 6px;
    border-radius: 6px 6px 0 0;
    background: #fafbfc;
    border-bottom: 1px solid #f0f0f1;
}

#dashboard-timeline-section .timeline-body {
    padding: 12px 14px;
}

#dashboard-timeline-section .timeline-meta {
    font-size: 11px;
    gap: 4px;
    line-height: 1.3;
}

#dashboard-timeline-section .timeline-date {
    background: #f0f6fc;
    color: #1565c0;
    padding: 1px 6px;
    border-radius: 8px;
    font-size: 10px;
    font-weight: 500;
}

#dashboard-timeline-section .activity-content-display {
    font-size: 12px;
    line-height: 1.4;
    margin-bottom: 8px;
}

#dashboard-timeline-section .timeline-actions {
    opacity: 0.6;
}

#dashboard-timeline-section .timeline-item:hover .timeline-actions {
    opacity: 1;
}

#dashboard-timeline-section .timeline-actions .button-icon {
    width: 24px;
    height: 24px;
}

#dashboard-timeline-section .timeline-relates-to {
    font-size: 10px !important;
}

#dashboard-timeline-section .timeline-relates-to .relation-link {
    font-size: 10px;
    gap: 2px;
}

#dashboard-timeline-section .timeline-relates-to .dashicons {
    font-size: 10px;
}

#dashboard-timeline-section .timeline-empty {
    padding: 30px 20px;
    text-align: center;
}

#dashboard-timeline-section .timeline-empty .dashicons {
    font-size: 28px;
    opacity: 0.4;
}

/* Responsywność */
@media screen and (max-width: 1200px) {
    .dashboard-container {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .stats-grid {
        grid-template-columns: repeat(4, 1fr);
    }
}

@media screen and (max-width: 768px) {
    .dashboard-container {
        gap: 16px;
        margin-top: 16px;
    }
    
    .dashboard-content {
        padding: 16px;
    }
    
    .dashboard-header {
        padding: 16px;
    }
    
    .stats-grid {
        grid-template-columns: 1fr 1fr;
    }
    
    .opportunities-stats {
        grid-template-columns: 1fr 1fr;
    }
}
</style>

<div class="dashboard-container">
    <!-- Lewa kolumna - Statystyki -->
    <div class="dashboard-left-column">
        <!-- Statystyki główne -->
        <div class="dashboard-box">
            <div class="dashboard-header">
                <h2 class="dashboard-title">
                    <span class="dashicons dashicons-chart-area"></span>
                    Statystyki
                </h2>
            </div>
            <div class="dashboard-content">
                <div class="stats-grid">
                    <div class="stat-item">
                        <span class="stat-number"><?php echo esc_html($companies_count); ?></span>
                        <span class="stat-label">Firmy</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?php echo esc_html($persons_count); ?></span>
                        <span class="stat-label">Osoby</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?php echo esc_html($projects_count); ?></span>
                        <span class="stat-label">Projekty</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?php echo count($today_users); ?></span>
                        <span class="stat-label">Nowi użytkownicy dzisiaj</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Ostatnie firmy -->
        <div class="dashboard-box">
            <div class="dashboard-header">
                <h2 class="dashboard-title">
                    <span class="dashicons dashicons-building"></span>
                    Ostatnie firmy
                </h2>
                <a href="<?php echo esc_url(admin_url('admin.php?page=wpmzf_companies')); ?>" class="button button-secondary button-small">
                    Zobacz wszystkie
                </a>
            </div>
            <div class="dashboard-content">
                <?php if ($recent_companies_query->have_posts()): ?>
                    <?php while ($recent_companies_query->have_posts()): $recent_companies_query->the_post(); ?>
                        <?php
                        $company_id = get_the_ID();
                        $company_name = get_the_title();
                        $company_date = get_the_date('j.m.Y');
                        $company_initial = strtoupper(substr($company_name, 0, 1));
                        ?>
                        <div class="company-item">
                            <div class="company-avatar">
                                <?php echo esc_html($company_initial); ?>
                            </div>
                            <div class="company-info">
                                <a href="<?php echo esc_url(add_query_arg(['page' => 'wpmzf_view_company', 'company_id' => $company_id], admin_url('admin.php'))); ?>" class="company-name">
                                    <?php echo esc_html($company_name); ?>
                                </a>
                                <span class="company-date">Dodano: <?php echo esc_html($company_date); ?></span>
                            </div>
                        </div>
                    <?php endwhile; wp_reset_postdata(); ?>
                <?php else: ?>
                    <p style="color: #8c8f94; font-style: italic; text-align: center;">Brak nowych firm.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Środkowa kolumna - Timeline aktywności -->
    <div class="dashboard-center-column">
        <div class="dashboard-box" id="dashboard-timeline-section">
            <div class="dashboard-header">
                <h2 class="dashboard-title">
                    <span class="dashicons dashicons-clock"></span>
                    Ostatnie aktywności
                </h2>
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=activity')); ?>" class="button button-secondary button-small">
                    Zobacz wszystkie
                </a>
            </div>
            <div class="dashboard-content">
                <?php
                // Renderuj uniwersalny komponent Timeline dla dashboardu
                $dashboard_timeline = new WPMZF_Timeline([
                    'context' => 'dashboard',
                    'id' => 0, // Dashboard nie ma konkretnego ID
                    'limit' => 10, // Ograniczmy do 10 ostatnich aktywności
                    'show_add_button' => false // Nie pokazujemy przycisku dodaj w dashboard
                ]);
                
                // Renderuj cały Timeline używając właściwej metody
                $dashboard_timeline->render();
                ?>
            </div>
        </div>
    </div>

    <!-- Prawa kolumna - Szanse sprzedaży -->
    <div class="dashboard-right-column">
        <div class="dashboard-box">
            <div class="dashboard-header">
                <h2 class="dashboard-title">
                    <span class="dashicons dashicons-chart-line"></span>
                    Szanse sprzedaży
                </h2>
                <?php if (class_exists('WPMZF_Opportunity')): ?>
                    <a href="<?php echo esc_url(admin_url('edit.php?post_type=opportunity&page=wpmzf_kanban_view')); ?>" class="button button-primary button-small">
                        Kanban
                    </a>
                <?php endif; ?>
            </div>
            <div class="dashboard-content">
                <?php if (!empty($opportunity_stats['by_status'])): ?>
                    <div class="opportunities-stats">
                        <?php foreach ($opportunity_stats['by_status'] as $status_name => $status_data): ?>
                            <div class="opportunity-status-item status-<?php echo sanitize_title($status_name); ?>">
                                <div class="status-count"><?php echo $status_data['count']; ?></div>
                                <div class="status-name"><?php echo esc_html($status_name); ?></div>
                                <div class="status-value"><?php echo number_format($status_data['value'], 0); ?> PLN</div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if (!empty($opportunities_due_soon)): ?>
                        <div class="opportunities-due-soon">
                            <h4 style="margin: 0 0 12px; color: #1d2327; font-size: 14px;">
                                <span class="dashicons dashicons-clock" style="color: #f39c12;"></span>
                                Do zamknięcia w ciągu 7 dni
                            </h4>
                            <?php foreach (array_slice($opportunities_due_soon, 0, 5) as $opportunity): ?>
                                <?php
                                $company = $opportunity->get_company();
                                $expected_close = get_field('opportunity_expected_close_date', $opportunity->get_id());
                                $days_left = $expected_close ? floor((strtotime($expected_close) - time()) / (60 * 60 * 24)) : null;
                                ?>
                                <div class="opportunity-due-item <?php echo $days_left !== null && $days_left <= 0 ? 'overdue' : ''; ?>">
                                    <div class="opportunity-info">
                                        <a href="<?php echo get_edit_post_link($opportunity->get_id()); ?>" class="opportunity-title">
                                            <?php echo esc_html($opportunity->get_title()); ?>
                                        </a>
                                        <div class="opportunity-meta">
                                            <?php if ($company): ?>
                                                <?php echo esc_html($company->get_name()); ?> • 
                                            <?php endif; ?>
                                            <?php echo number_format($opportunity->get_value(), 0); ?> PLN
                                        </div>
                                    </div>
                                    <div class="opportunity-due-date">
                                        <?php if ($days_left !== null): ?>
                                            <?php if ($days_left > 0): ?>
                                                <span class="days-left"><?php echo $days_left; ?> dni</span>
                                            <?php elseif ($days_left == 0): ?>
                                                <span class="days-left today">Dziś</span>
                                            <?php else: ?>
                                                <span class="days-left overdue"><?php echo abs($days_left); ?> dni po terminie</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div style="text-align: center; color: #8c8f94; padding: 20px;">
                        <p style="margin: 0;">Brak szans sprzedaży.</p>
                        <?php if (class_exists('WPMZF_Opportunity')): ?>
                            <a href="<?php echo admin_url('post-new.php?post_type=opportunity'); ?>" class="button button-secondary" style="margin-top: 10px;">
                                Dodaj pierwszą szansę
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
