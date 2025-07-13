/**
 * Kanban Board JavaScript
 * Obsługuje funkcjonalność drag & drop dla szans sprzedaży
 */

jQuery(document).ready(function($) {
    'use strict';

    // Inicjalizacja tablicy Kanban
    const KanbanBoard = {
        
        /**
         * Inicjalizacja
         */
        init: function() {
            this.initSortable();
            this.bindEvents();
            this.updateColumnCounts();
        },

        /**
         * Inicjalizacja sortable (drag & drop)
         */
        initSortable: function() {
            $('.kanban-cards').sortable({
                connectWith: '.kanban-cards',
                placeholder: 'kanban-card ui-sortable-placeholder',
                tolerance: 'pointer',
                cursor: 'grabbing',
                opacity: 0.8,
                zIndex: 9999,
                
                // Przed rozpoczęciem przeciągania
                start: function(event, ui) {
                    ui.placeholder.height(ui.item.height());
                    ui.item.addClass('kanban-dragging');
                },
                
                // Podczas przeciągania nad celem
                over: function(event, ui) {
                    $(this).closest('.kanban-column').addClass('kanban-drop-target');
                },
                
                // Po opuszczeniu celu
                out: function(event, ui) {
                    $(this).closest('.kanban-column').removeClass('kanban-drop-target');
                },
                
                // Po zakończeniu przeciągania
                stop: function(event, ui) {
                    ui.item.removeClass('kanban-dragging');
                    $('.kanban-column').removeClass('kanban-drop-target');
                },
                
                // Po zmianie pozycji
                update: function(event, ui) {
                    // Sprawdź czy element został przeniesiony do innej kolumny
                    if (this === ui.item.parent()[0]) {
                        KanbanBoard.updateOpportunityStatus(ui.item);
                    }
                }
            }).disableSelection();
        },

        /**
         * Bindowanie eventów
         */
        bindEvents: function() {
            // Obsługa klawiatury dla dostępności
            $(document).on('keydown', '.kanban-card', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    window.location.href = $(this).find('.kanban-card-title a').attr('href');
                }
            });

            // Obsługa kliknięcia w kartę (dla urządzeń mobilnych)
            $(document).on('click', '.kanban-card', function(e) {
                if ($(window).width() <= 768 && !$(e.target).is('a, button')) {
                    e.preventDefault();
                    window.location.href = $(this).find('.kanban-card-title a').attr('href');
                }
            });

            // Odświeżanie strony
            $(document).on('click', '.kanban-refresh', function(e) {
                e.preventDefault();
                location.reload();
            });
        },

        /**
         * Aktualizacja statusu szansy sprzedaży
         */
        updateOpportunityStatus: function($item) {
            const opportunityId = $item.data('id');
            const newStatusId = $item.closest('.kanban-cards').data('status-id');
            const $column = $item.closest('.kanban-column');
            const statusName = $column.data('status-name');

            if (!opportunityId || !newStatusId) {
                this.showMessage('Błąd: Nieprawidłowe dane szansy.', 'error');
                return;
            }

            // Sprawdź czy to status "Wygrana" lub "Przegrana" - wtedy wymagaj powodu
            if (statusName === 'Wygrana' || statusName === 'Przegrana') {
                this.showReasonModal($item, opportunityId, newStatusId, statusName);
                return;
            }

            // Standardowa aktualizacja bez powodu
            this.performStatusUpdate($item, opportunityId, newStatusId);
        },

        /**
         * Pokazuje modal do wprowadzenia powodu dla wygranej/przegranej szansy
         */
        showReasonModal: function($item, opportunityId, newStatusId, statusName) {
            const self = this;
            
            // Utwórz modal
            const modalHtml = `
                <div id="kanban-reason-modal" class="kanban-modal-overlay">
                    <div class="kanban-modal">
                        <div class="kanban-modal-header">
                            <h3>Powód - ${statusName}</h3>
                            <button class="kanban-modal-close">&times;</button>
                        </div>
                        <div class="kanban-modal-body">
                            <p>Opisz powód dla statusu "${statusName}":</p>
                            <textarea id="kanban-reason-text" rows="4" style="width: 100%; margin: 10px 0;" 
                                     placeholder="Wprowadź powód..."></textarea>
                        </div>
                        <div class="kanban-modal-footer">
                            <button class="button button-secondary kanban-modal-cancel">Anuluj</button>
                            <button class="button button-primary kanban-modal-save">Zapisz</button>
                        </div>
                    </div>
                </div>
            `;
            
            $('body').append(modalHtml);
            
            // Obsługa przycisków modala
            $('#kanban-reason-modal .kanban-modal-close, #kanban-reason-modal .kanban-modal-cancel').on('click', function() {
                self.closeReasonModal();
                self.revertCardPosition($item);
            });
            
            $('#kanban-reason-modal .kanban-modal-save').on('click', function() {
                const reason = $('#kanban-reason-text').val().trim();
                if (!reason) {
                    alert('Powód jest wymagany dla statusu ' + statusName);
                    return;
                }
                
                self.performStatusUpdate($item, opportunityId, newStatusId, reason);
                self.closeReasonModal();
            });
            
            // Obsługa ESC
            $(document).on('keydown.kanban-modal', function(e) {
                if (e.keyCode === 27) {
                    self.closeReasonModal();
                    self.revertCardPosition($item);
                }
            });
            
            // Focus na textarea
            setTimeout(() => $('#kanban-reason-text').focus(), 100);
        },

        /**
         * Zamyka modal powodu
         */
        closeReasonModal: function() {
            $('#kanban-reason-modal').remove();
            $(document).off('keydown.kanban-modal');
        },

        /**
         * Wykonuje aktualizację statusu
         */
        performStatusUpdate: function($item, opportunityId, newStatusId, reason) {
            // Pokaż loader
            $item.addClass('kanban-updating');
            
            const data = {
                action: 'wpmzf_update_opportunity_status',
                nonce: wpmzf_kanban.nonce,
                post_id: opportunityId,
                status_id: newStatusId
            };
            
            if (reason) {
                data.reason = reason;
            }
            
            // Wyślijy żądanie AJAX
            $.ajax({
                url: wpmzf_kanban.ajax_url,
                type: 'POST',
                data: data,
                success: function(response) {
                    $item.removeClass('kanban-updating');
                    
                    if (response.success) {
                        // Aktualizuj liczniki kolumn
                        KanbanBoard.updateColumnCounts();
                        
                        // Pokaż komunikat sukcesu
                        let message = wpmzf_kanban.strings.success;
                        
                        // Sprawdź czy szansa została skonwertowana
                        if (response.data && response.data.project_id) {
                            message = wpmzf_kanban.strings.converted;
                            $item.addClass('converted');
                            
                            // Dodaj badge konwersji jeśli nie istnieje
                            if (!$item.find('.kanban-badge.converted').length) {
                                $item.find('.kanban-card-header').append(
                                    '<span class="kanban-badge converted">' +
                                    '<span class="dashicons dashicons-yes-alt"></span>' +
                                    'Skonwertowano' +
                                    '</span>'
                                );
                            }
                            
                            // Dodaj przycisk do projektu
                            if (!$item.find('.kanban-card-actions .button-primary').length) {
                                $item.find('.kanban-card-actions').append(
                                    '<a href="' + wpmzf_kanban.admin_url + 'post.php?post=' + 
                                    response.data.project_id + '&action=edit" ' +
                                    'class="button button-small button-primary">Zobacz projekt</a>'
                                );
                            }
                        }
                        
                        KanbanBoard.showMessage(message, 'success');
                        
                        // Animacja potwierdzenia
                        $item.addClass('kanban-updated');
                        setTimeout(function() {
                            $item.removeClass('kanban-updated');
                        }, 1000);
                        
                    } else {
                        // Błąd - przywróć poprzednią pozycję
                        KanbanBoard.showMessage(response.data || wpmzf_kanban.strings.error, 'error');
                        KanbanBoard.revertCardPosition($item);
                    }
                },
                error: function(xhr, status, error) {
                    $item.removeClass('kanban-updating');
                    KanbanBoard.showMessage(wpmzf_kanban.strings.error, 'error');
                    KanbanBoard.revertCardPosition($item);
                    console.error('AJAX Error:', error);
                }
            });
        },

        /**
         * Przywrócenie poprzedniej pozycji karty
         */
        revertCardPosition: function($item) {
            // W praktyce jQuery UI Sortable automatycznie przywraca pozycję
            // przy błędzie, ale możemy dodać dodatkową animację
            $item.effect('shake', { distance: 5, times: 2 }, 300);
        },

        /**
         * Aktualizacja liczników w kolumnach
         */
        updateColumnCounts: function() {
            $('.kanban-column').each(function() {
                const $column = $(this);
                const $counter = $column.find('.kanban-count');
                const count = $column.find('.kanban-card').length;
                $counter.text(count);
            });
        },

        /**
         * Wyświetlenie komunikatu
         */
        showMessage: function(message, type) {
            type = type || 'success';
            
            // Usuń poprzednie komunikaty
            $('.kanban-message').remove();
            
            // Utwórz nowy komunikat
            const $message = $('<div>')
                .addClass('kanban-message ' + type)
                .text(message)
                .appendTo('body');
            
            // Usuń komunikat po 4 sekundach
            setTimeout(function() {
                $message.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 4000);
            
            // Kliknięcie usuwa komunikat
            $message.on('click', function() {
                $(this).fadeOut(300, function() {
                    $(this).remove();
                });
            });
        },

        /**
         * Obsługa błędów
         */
        handleError: function(error, context) {
            console.error('Kanban Error (' + context + '):', error);
            this.showMessage('Wystąpił nieoczekiwany błąd. Sprawdź konsolę.', 'error');
        }
    };

    // Dodatkowe style CSS dla stanów
    const additionalStyles = `
        <style>
        .kanban-card.kanban-dragging {
            transform: rotate(3deg) !important;
            z-index: 1000 !important;
        }
        
        .kanban-card.kanban-updating {
            opacity: 0.6;
            pointer-events: none;
        }
        
        .kanban-card.kanban-updating::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 20px;
            height: 20px;
            margin: -10px 0 0 -10px;
            border: 2px solid #ddd;
            border-top-color: #007cba;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        .kanban-card.kanban-updated {
            background-color: #d4edda !important;
            transition: background-color 0.3s ease;
        }
        
        .kanban-column.kanban-drop-target {
            background-color: #e3f2fd;
            transform: scale(1.02);
            transition: all 0.2s ease;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        </style>
    `;
    
    $('head').append(additionalStyles);

    // Inicjalizacja po załadowaniu DOM
    try {
        KanbanBoard.init();
    } catch (error) {
        console.error('Failed to initialize Kanban Board:', error);
    }

    // Obsługa zmiany rozmiaru okna
    $(window).on('resize', function() {
        // Odśwież sortable przy zmianie orientacji na urządzeniach mobilnych
        if ($(window).width() <= 768) {
            $('.kanban-cards').sortable('option', 'tolerance', 'touch');
        } else {
            $('.kanban-cards').sortable('option', 'tolerance', 'pointer');
        }
    });

    // Obsługa escape key
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
            $('.kanban-message').fadeOut(300, function() {
                $(this).remove();
            });
        }
    });

    // Diagnostyka (tylko w trybie debug)
    if (window.location.search.includes('debug=kanban')) {
        console.log('Kanban Board Debug Mode Enabled');
        console.log('wpmzf_kanban object:', wpmzf_kanban);
        
        // Dodaj przycisk diagnostyki
        $('<button>')
            .text('Kanban Debug Info')
            .addClass('button button-secondary')
            .css({ position: 'fixed', bottom: '20px', right: '20px', zIndex: 9999 })
            .on('click', function() {
                console.log('Kanban columns:', $('.kanban-column').length);
                console.log('Kanban cards:', $('.kanban-card').length);
                console.log('Sortable enabled:', $('.kanban-cards').hasClass('ui-sortable'));
            })
            .appendTo('body');
    }
});
