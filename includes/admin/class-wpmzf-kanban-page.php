<?php

/**
 * Klasa obsługująca stronę Kanban dla szans sprzedaży
 *
 * @package WPMZF
 * @subpackage Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPMZF_Kanban_Page {

    /**
     * Konstruktor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_kanban_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_kanban_scripts'));
    }

    /**
     * Dodaje stronę Kanban do menu administratora
     */
    public function add_kanban_page() {
        add_submenu_page(
            'edit.php?post_type=opportunity',
            'Kanban Szans Sprzedaży',
            'Kanban',
            'manage_options',
            'wpmzf_kanban_view',
            array($this, 'render_kanban_page')
        );
    }

    /**
     * Ładuje skrypty i style dla strony Kanban
     */
    public function enqueue_kanban_scripts($hook) {
        if ($hook !== 'opportunity_page_wpmzf_kanban_view') {
            return;
        }

        // Style
        wp_enqueue_style(
            'wpmzf-kanban-styles',
            plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/css/kanban.css',
            array(),
            '1.0.0'
        );

        // Skrypty
        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_script(
            'wpmzf-kanban-script',
            plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/js/admin/kanban.js',
            array('jquery', 'jquery-ui-sortable'),
            '1.0.0',
            true
        );

        // Lokalizacja skryptu
        wp_localize_script('wpmzf-kanban-script', 'wpmzf_kanban', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpmzf_kanban_nonce'),
            'strings' => array(
                'error' => 'Wystąpił błąd podczas aktualizacji.',
                'success' => 'Status zaktualizowany pomyślnie.',
                'converted' => 'Szansa została skonwertowana na projekt!',
            ),
        ));
    }

    /**
     * Renderuje stronę Kanban
     */
    public function render_kanban_page() {
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">
                <span class="dashicons dashicons-chart-line"></span>
                Kanban Szans Sprzedaży
            </h1>
            
            <a href="<?php echo admin_url('post-new.php?post_type=opportunity'); ?>" class="page-title-action">
                Dodaj nową szansę
            </a>
            
            <hr class="wp-header-end">

            <?php $this->render_stats_overview(); ?>

            <div id="kanban-board-container">
                <?php $this->render_kanban_board(); ?>
            </div>

            <?php $this->render_opportunities_due_soon(); ?>
        </div>
        <?php
    }

    /**
     * Renderuje przegląd statystyk
     */
    private function render_stats_overview() {
        $service = new WPMZF_Opportunity_Service();
        $stats = $service->get_opportunities_stats();
        ?>
        <div class="kanban-stats-overview">
            <div class="kanban-stats-grid">
                <div class="kanban-stat-item">
                    <h3><?php echo number_format($stats['total_count']); ?></h3>
                    <p>Łączna liczba szans</p>
                </div>
                <div class="kanban-stat-item">
                    <h3><?php echo number_format($stats['total_value'], 2); ?> PLN</h3>
                    <p>Łączna wartość</p>
                </div>
                <?php foreach ($stats['by_status'] as $status_name => $status_data): ?>
                    <div class="kanban-stat-item status-<?php echo sanitize_title($status_name); ?>">
                        <h3><?php echo $status_data['count']; ?></h3>
                        <p><?php echo esc_html($status_name); ?></p>
                        <small><?php echo number_format($status_data['value'], 2); ?> PLN</small>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Renderuje tablicę Kanban
     */
    private function render_kanban_board() {
        $statuses = get_terms(array(
            'taxonomy' => 'opportunity_status',
            'hide_empty' => false,
            'orderby' => 'term_order',
        ));

        if (empty($statuses) || is_wp_error($statuses)) {
            echo '<div class="notice notice-warning"><p>Nie znaleziono statusów szans sprzedaży. Sprawdź konfigurację taksonomii.</p></div>';
            return;
        }
        ?>
        <div class="kanban-board" id="kanban-board">
            <?php foreach ($statuses as $status) : ?>
                <div class="kanban-column" data-status-id="<?php echo $status->term_id; ?>" data-status-name="<?php echo esc_attr($status->name); ?>">
                    <div class="kanban-column-header">
                        <h3 class="kanban-column-title">
                            <?php echo esc_html($status->name); ?>
                            <span class="kanban-count"><?php echo $this->get_opportunities_count_by_status($status->term_id); ?></span>
                        </h3>
                    </div>
                    <div class="kanban-cards" data-status-id="<?php echo $status->term_id; ?>">
                        <?php $this->render_opportunities_for_status($status->term_id); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /**
     * Renderuje szanse dla danego statusu
     */
    private function render_opportunities_for_status($status_id) {
        $opportunities = WPMZF_Opportunity::get_by_status($status_id);

        foreach ($opportunities as $opportunity) {
            $this->render_opportunity_card($opportunity);
        }
    }

    /**
     * Renderuje kartę szansy
     */
    private function render_opportunity_card($opportunity) {
        $company = $opportunity->get_company();
        $company_name = $company ? $company->get_name() : 'Brak firmy';
        $value = $opportunity->get_value();
        $probability = get_field('opportunity_probability', $opportunity->get_id());
        $expected_close = get_field('opportunity_expected_close_date', $opportunity->get_id());
        $is_overdue = $expected_close && strtotime($expected_close) < time();
        $is_converted = $opportunity->is_converted();
        ?>
        <div class="kanban-card <?php echo $is_overdue ? 'overdue' : ''; ?> <?php echo $is_converted ? 'converted' : ''; ?>" 
             data-id="<?php echo $opportunity->get_id(); ?>" 
             draggable="true">
            
            <div class="kanban-card-header">
                <h4 class="kanban-card-title">
                    <a href="<?php echo get_edit_post_link($opportunity->get_id()); ?>" target="_blank">
                        <?php echo esc_html($opportunity->get_title()); ?>
                    </a>
                </h4>
                <?php if ($is_converted): ?>
                    <span class="kanban-badge converted">
                        <span class="dashicons dashicons-yes-alt"></span>
                        Skonwertowano
                    </span>
                <?php endif; ?>
            </div>

            <div class="kanban-card-body">
                <div class="kanban-card-field">
                    <strong>Firma:</strong>
                    <?php if ($company): ?>
                        <a href="<?php echo get_edit_post_link($company->get_id()); ?>" target="_blank">
                            <?php echo esc_html($company_name); ?>
                        </a>
                    <?php else: ?>
                        <span class="text-muted">Brak</span>
                    <?php endif; ?>
                </div>

                <?php if ($value > 0): ?>
                    <div class="kanban-card-field">
                        <strong>Wartość:</strong>
                        <span class="kanban-value"><?php echo number_format($value, 2); ?> PLN</span>
                    </div>
                <?php endif; ?>

                <?php if ($probability): ?>
                    <div class="kanban-card-field">
                        <strong>Prawdopodobieństwo:</strong>
                        <span class="kanban-probability"><?php echo $probability; ?>%</span>
                    </div>
                <?php endif; ?>

                <?php if ($expected_close): ?>
                    <div class="kanban-card-field <?php echo $is_overdue ? 'overdue' : ''; ?>">
                        <strong>Zamknięcie:</strong>
                        <span class="kanban-date">
                            <?php echo date('d.m.Y', strtotime($expected_close)); ?>
                            <?php if ($is_overdue): ?>
                                <span class="dashicons dashicons-warning" title="Przeterminowane"></span>
                            <?php endif; ?>
                        </span>
                    </div>
                <?php endif; ?>
            </div>

            <div class="kanban-card-actions">
                <a href="<?php echo get_edit_post_link($opportunity->get_id()); ?>" class="button button-small">
                    Edytuj
                </a>
                <?php if ($is_converted): ?>
                    <a href="<?php echo get_edit_post_link($opportunity->get_converted_project_id()); ?>" 
                       class="button button-small button-primary">
                        Zobacz projekt
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Zwraca liczbę szans dla danego statusu
     */
    private function get_opportunities_count_by_status($status_id) {
        $opportunities = WPMZF_Opportunity::get_by_status($status_id);
        return count($opportunities);
    }

    /**
     * Renderuje sekcję z szansami do zamknięcia wkrótce
     */
    private function render_opportunities_due_soon() {
        $service = new WPMZF_Opportunity_Service();
        $due_soon = $service->get_opportunities_due_soon(7);

        if (empty($due_soon)) {
            return;
        }
        ?>
        <div class="kanban-due-soon">
            <h2>
                <span class="dashicons dashicons-clock"></span>
                Szanse do zamknięcia w najbliższych 7 dniach
            </h2>
            <div class="kanban-due-soon-list">
                <?php foreach ($due_soon as $opportunity): ?>
                    <?php
                    $expected_close = get_field('opportunity_expected_close_date', $opportunity->get_id());
                    $days_left = floor((strtotime($expected_close) - time()) / (60 * 60 * 24));
                    $company = $opportunity->get_company();
                    ?>
                    <div class="kanban-due-item <?php echo $days_left <= 0 ? 'overdue' : ''; ?>">
                        <div class="kanban-due-info">
                            <h4>
                                <a href="<?php echo get_edit_post_link($opportunity->get_id()); ?>">
                                    <?php echo esc_html($opportunity->get_title()); ?>
                                </a>
                            </h4>
                            <p>
                                <?php if ($company): ?>
                                    <strong><?php echo esc_html($company->get_name()); ?></strong> • 
                                <?php endif; ?>
                                <?php echo number_format($opportunity->get_value(), 2); ?> PLN
                            </p>
                        </div>
                        <div class="kanban-due-date">
                            <?php if ($days_left > 0): ?>
                                <span class="days-left"><?php echo $days_left; ?> dni</span>
                            <?php elseif ($days_left == 0): ?>
                                <span class="days-left today">Dziś</span>
                            <?php else: ?>
                                <span class="days-left overdue"><?php echo abs($days_left); ?> dni po terminie</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
}
