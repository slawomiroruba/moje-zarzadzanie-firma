/**
 * Dashboard JavaScript
 *
 * @package WPMZF
 */

(function($) {
    'use strict';

    var Dashboard = {
        init: function() {
            this.bindEvents();
            this.initCharts();
            this.initWidgets();
        },

        bindEvents: function() {
            $(document).on('click', '.refresh-stats', this.refreshStats);
            $(document).on('click', '.dashboard-widget-toggle', this.toggleWidget);
        },

        refreshStats: function() {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpmzf_refresh_dashboard_stats',
                    nonce: wpmzf_dashboard.nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    }
                }
            });
        },

        toggleWidget: function(e) {
            var $widget = $(this).closest('.luna-crm-card');
            var $content = $widget.find('.card-content');
            
            $content.slideToggle();
            $(this).find('.dashicons').toggleClass('dashicons-arrow-up dashicons-arrow-down');
        },

        initCharts: function() {
            // Inicjalizacja wykresów - można dodać Chart.js lub inną bibliotekę
            this.initTimeChart();
            this.initProjectChart();
        },

        initTimeChart: function() {
            var $chart = $('#time-chart');
            if ($chart.length === 0) return;

            // Przykładowe dane - w rzeczywistości pobierane z AJAX
            var data = {
                labels: ['Pon', 'Wt', 'Śr', 'Czw', 'Pt', 'Sob', 'Ndz'],
                datasets: [{
                    label: 'Godziny',
                    data: [8, 7, 6, 8, 9, 2, 0],
                    borderColor: '#007cba',
                    backgroundColor: 'rgba(0, 124, 186, 0.1)',
                    fill: true
                }]
            };

            // Tutaj można dodać Chart.js lub inną bibliotekę wykresów
            console.log('Time chart data:', data);
        },

        initProjectChart: function() {
            var $chart = $('#project-chart');
            if ($chart.length === 0) return;

            // Przykładowe dane statusów projektów
            var data = {
                labels: ['Aktywne', 'Planowane', 'Zakończone', 'Wstrzymane'],
                datasets: [{
                    data: [5, 3, 12, 1],
                    backgroundColor: [
                        '#00a32a',
                        '#ffb900',
                        '#007cba',
                        '#d63638'
                    ]
                }]
            };

            console.log('Project chart data:', data);
        },

        initWidgets: function() {
            this.initRecentActivity();
            this.initQuickStats();
        },

        initRecentActivity: function() {
            var $activity = $('.recent-activity');
            if ($activity.length === 0) return;

            // Auto-refresh co 5 minut
            setInterval(function() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wpmzf_get_recent_activity',
                        nonce: wpmzf_dashboard.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $activity.html(response.data);
                        }
                    }
                });
            }, 300000); // 5 minut
        },

        initQuickStats: function() {
            // Animacja liczników
            $('.stat-content h3').each(function() {
                var $this = $(this);
                var value = parseFloat($this.text());
                
                if (!isNaN(value)) {
                    $this.text('0');
                    $this.animate({
                        value: value
                    }, {
                        duration: 1000,
                        step: function(now) {
                            $this.text(Math.ceil(now));
                        }
                    });
                }
            });
        }
    };

    // Inicjalizacja po załadowaniu DOM
    $(document).ready(function() {
        Dashboard.init();
    });

})(jQuery);
