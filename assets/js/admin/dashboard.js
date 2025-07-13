/**
 * Dashboard JavaScript
 *
 * @package WPMZF
 */

// Utility functions - vanilla JS helpers
function ready(fn) {
    if (document.readyState !== 'loading') {
        fn();
    } else {
        document.addEventListener('DOMContentLoaded', fn);
    }
}

function $(selector, context = document) {
    return context.querySelector(selector);
}

function $$(selector, context = document) {
    return context.querySelectorAll(selector);
}

function ajax(options) {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        
        xhr.open(options.type || 'GET', options.url, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
        
        xhr.onload = function() {
            if (xhr.status >= 200 && xhr.status < 300) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    resolve(response);
                } catch (e) {
                    resolve(xhr.responseText);
                }
            } else {
                reject(new Error('Request failed'));
            }
        };
        
        xhr.onerror = function() {
            reject(new Error('Request failed'));
        };
        
        if (options.data) {
            const formData = new URLSearchParams(options.data).toString();
            xhr.send(formData);
        } else {
            xhr.send();
        }
    });
}

function slideToggle(element, duration = 300) {
    if (element.style.display === 'none' || !element.style.display) {
        element.style.display = 'block';
        element.style.overflow = 'hidden';
        element.style.height = '0px';
        element.style.transition = `height ${duration}ms ease`;
        
        const height = element.scrollHeight;
        element.style.height = height + 'px';
        
        setTimeout(() => {
            element.style.height = '';
            element.style.overflow = '';
            element.style.transition = '';
        }, duration);
    } else {
        element.style.overflow = 'hidden';
        element.style.height = element.scrollHeight + 'px';
        element.style.transition = `height ${duration}ms ease`;
        
        setTimeout(() => {
            element.style.height = '0px';
        }, 10);
        
        setTimeout(() => {
            element.style.display = 'none';
            element.style.height = '';
            element.style.overflow = '';
            element.style.transition = '';
        }, duration);
    }
}

function animateValue(element, start, end, duration) {
    const range = end - start;
    const startTime = Date.now();
    
    function update() {
        const elapsed = Date.now() - startTime;
        const progress = Math.min(elapsed / duration, 1);
        const current = start + (range * progress);
        
        element.textContent = Math.ceil(current);
        
        if (progress < 1) {
            requestAnimationFrame(update);
        }
    }
    
    update();
}

(function() {
    'use strict';

    var Dashboard = {
        init: function() {
            this.bindEvents();
            this.initCharts();
            this.initWidgets();
        },

        bindEvents: function() {
            document.addEventListener('click', function(e) {
                if (e.target.matches('.refresh-stats') || e.target.closest('.refresh-stats')) {
                    Dashboard.refreshStats(e);
                }
                if (e.target.matches('.dashboard-widget-toggle') || e.target.closest('.dashboard-widget-toggle')) {
                    Dashboard.toggleWidget(e);
                }
            });
        },

        refreshStats: function(e) {
            e.preventDefault();
            
            ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpmzf_refresh_dashboard_stats',
                    nonce: wpmzf_dashboard.nonce
                }
            }).then(function(response) {
                if (response.success) {
                    location.reload();
                }
            }).catch(function(error) {
                console.error('Error refreshing stats:', error);
            });
        },

        toggleWidget: function(e) {
            e.preventDefault();
            
            const trigger = e.target.closest('.dashboard-widget-toggle');
            const widget = trigger.closest('.luna-crm-card');
            const content = widget.querySelector('.card-content');
            const icon = trigger.querySelector('.dashicons');
            
            slideToggle(content);
            
            if (icon) {
                if (icon.classList.contains('dashicons-arrow-up')) {
                    icon.classList.remove('dashicons-arrow-up');
                    icon.classList.add('dashicons-arrow-down');
                } else {
                    icon.classList.remove('dashicons-arrow-down');
                    icon.classList.add('dashicons-arrow-up');
                }
            }
        },

        initCharts: function() {
            // Inicjalizacja wykresów - można dodać Chart.js lub inną bibliotekę
            this.initTimeChart();
            this.initProjectChart();
        },

        initTimeChart: function() {
            var chart = $('#time-chart');
            if (!chart) return;

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
            var chart = $('#project-chart');
            if (!chart) return;

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
            var activity = $('.recent-activity');
            if (!activity) return;

            // Auto-refresh co 5 minut
            setInterval(function() {
                ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wpmzf_get_recent_activity',
                        nonce: wpmzf_dashboard.nonce
                    }
                }).then(function(response) {
                    if (response.success) {
                        activity.innerHTML = response.data;
                    }
                }).catch(function(error) {
                    console.error('Error loading recent activity:', error);
                });
            }, 300000); // 5 minut
        },

        initQuickStats: function() {
            // Animacja liczników
            const statElements = $$('.stat-content h3');
            statElements.forEach(function(element) {
                const value = parseFloat(element.textContent);
                
                if (!isNaN(value)) {
                    element.textContent = '0';
                    animateValue(element, 0, value, 1000);
                }
            });
        }
    };

    // Inicjalizacja po załadowaniu DOM
    ready(function() {
        Dashboard.init();
    });

    // === FUNKCJONALNOŚĆ TIMELINE'U AKTYWNOŚCI ===
    // Dodajemy funkcje dla obsługi timeline'u identycznego z person-view
    
    // Funkcja do wyświetlania szczegółów aktywności
    window.viewActivityDetails = function(activityId) {
        const activityElement = $(`[data-activity-id="${activityId}"]`);
        
        if (!activityElement) {
            console.error('Nie znaleziono aktywności o ID:', activityId);
            return;
        }
        
        // Pobierz informacje z elementu
        const activityContent = activityElement.querySelector('.activity-content-display').innerHTML;
        const activityHeader = activityElement.querySelector('.timeline-header-meta span:last-child').textContent;
        const activityDate = activityElement.querySelector('.timeline-header-date').textContent;
        const relatedElement = activityElement.querySelector('.activity-related');
        const attachmentsElement = activityElement.querySelector('.timeline-attachments');
        
        const relatedInfo = relatedElement ? relatedElement.innerHTML : '';
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
                            margin-bottom: ${relatedInfo || attachments ? '24px' : '0'};
                        ">
                            ${activityContent}
                        </div>
                        ${relatedInfo ? `
                            <div style="
                                padding: 16px;
                                background: #f8f9fa;
                                border-radius: 6px;
                                border-left: 4px solid #2271b1;
                                margin-bottom: ${attachments ? '20px' : '0'};
                            ">
                                ${relatedInfo}
                            </div>
                        ` : ''}
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
        const modal = $('#activity-details-modal');
        const closeBtn = $('#close-activity-modal');
        
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
    
    // Inicjalizacja timeline effects po załadowaniu
    ready(function() {
        // Dodaj hover effects dla timeline actions
        const timelineActions = $$('.timeline-actions .dashicons');
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
    });

})();
