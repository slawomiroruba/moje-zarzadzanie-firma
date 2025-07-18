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
                        <span class="stat-label">Firmy123</span>
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
