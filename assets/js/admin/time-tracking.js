/**
 * Time Tracking JavaScript
 *
 * @package WPMZF
 */

(function($) {
    'use strict';

    var TimeTracking = {
        timer: null,
        startTime: null,
        elapsed: 0,
        isRunning: false,

        init: function() {
            this.bindEvents();
            this.checkTimerStatus();
        },

        bindEvents: function() {
            $(document).on('click', '#timer-start', this.startTimer.bind(this));
            $(document).on('click', '#timer-stop', this.stopTimer.bind(this));
            $(document).on('click', '#timer-pause', this.pauseTimer.bind(this));
            $(document).on('click', '#timer-resume', this.resumeTimer.bind(this));
            $(document).on('click', '.add-time-entry', this.showTimeEntryForm);
            $(document).on('submit', '#time-entry-form', this.saveTimeEntry);
        },

        startTimer: function() {
            var projectId = $('#timer-project').val();
            var description = $('#timer-description').val();

            if (!projectId) {
                alert('Wybierz projekt');
                return;
            }

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpmzf_start_timer',
                    project_id: projectId,
                    description: description,
                    nonce: wpmzf_time.nonce
                },
                success: function(response) {
                    if (response.success) {
                        this.isRunning = true;
                        this.startTime = response.data.start_time;
                        this.elapsed = 0;
                        this.updateUI();
                        this.startTimerDisplay();
                    } else {
                        alert('Błąd: ' + response.data);
                    }
                }.bind(this)
            });
        },

        stopTimer: function() {
            if (!this.isRunning) return;

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpmzf_stop_timer',
                    nonce: wpmzf_time.nonce
                },
                success: function(response) {
                    if (response.success) {
                        this.isRunning = false;
                        this.stopTimerDisplay();
                        this.updateUI();
                        this.showTimeEntryResult(response.data);
                    } else {
                        alert('Błąd: ' + response.data);
                    }
                }.bind(this)
            });
        },

        pauseTimer: function() {
            if (!this.isRunning) return;

            this.stopTimerDisplay();
            this.isRunning = false;
            this.updateUI();
        },

        resumeTimer: function() {
            if (this.isRunning) return;

            this.isRunning = true;
            this.startTimerDisplay();
            this.updateUI();
        },

        checkTimerStatus: function() {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpmzf_get_timer_status',
                    nonce: wpmzf_time.nonce
                },
                success: function(response) {
                    if (response.success && response.data.active) {
                        this.isRunning = true;
                        this.startTime = response.data.start_time;
                        this.elapsed = response.data.elapsed;
                        $('#timer-project').val(response.data.project_id);
                        $('#timer-description').val(response.data.description);
                        this.updateUI();
                        this.startTimerDisplay();
                    }
                }.bind(this)
            });
        },

        startTimerDisplay: function() {
            if (this.timer) {
                clearInterval(this.timer);
            }

            this.timer = setInterval(function() {
                this.elapsed++;
                this.updateTimerDisplay();
            }.bind(this), 1000);
        },

        stopTimerDisplay: function() {
            if (this.timer) {
                clearInterval(this.timer);
                this.timer = null;
            }
        },

        updateTimerDisplay: function() {
            var hours = Math.floor(this.elapsed / 3600);
            var minutes = Math.floor((this.elapsed % 3600) / 60);
            var seconds = this.elapsed % 60;

            var display = this.pad(hours) + ':' + this.pad(minutes) + ':' + this.pad(seconds);
            $('#timer-time').text(display);
        },

        updateUI: function() {
            if (this.isRunning) {
                $('#timer-start').hide();
                $('#timer-stop, #timer-pause').show();
                $('#timer-resume').hide();
                $('#timer-project, #timer-description').prop('disabled', true);
            } else {
                $('#timer-start').show();
                $('#timer-stop, #timer-pause').hide();
                $('#timer-resume').show();
                $('#timer-project, #timer-description').prop('disabled', false);
            }
        },

        showTimeEntryForm: function() {
            var modal = `
                <div id="time-entry-modal" class="luna-crm-modal">
                    <div class="luna-crm-modal-content">
                        <div class="luna-crm-modal-header">
                            <h2>Dodaj wpis czasu</h2>
                            <button class="luna-crm-modal-close">&times;</button>
                        </div>
                        <form id="time-entry-form">
                            <div class="luna-crm-form-group">
                                <label for="te-project">Projekt *</label>
                                <select id="te-project" name="project_id" required>
                                    <option value="">Wybierz projekt</option>
                                    ${wpmzf_time.projects.map(p => `<option value="${p.id}">${p.name}</option>`).join('')}
                                </select>
                            </div>
                            <div class="luna-crm-form-group">
                                <label for="te-time">Czas (minuty) *</label>
                                <input type="number" id="te-time" name="time_minutes" min="1" required>
                            </div>
                            <div class="luna-crm-form-group">
                                <label for="te-description">Opis</label>
                                <textarea id="te-description" name="description" rows="3"></textarea>
                            </div>
                            <div class="luna-crm-form-group">
                                <label for="te-date">Data</label>
                                <input type="date" id="te-date" name="date" value="${new Date().toISOString().split('T')[0]}">
                            </div>
                            <div class="luna-crm-form-actions">
                                <button type="submit" class="button button-primary">Zapisz</button>
                                <button type="button" class="button cancel-time-entry">Anuluj</button>
                            </div>
                        </form>
                    </div>
                </div>
            `;

            $('body').append(modal);
            $('#time-entry-modal').show();
        },

        saveTimeEntry: function(e) {
            e.preventDefault();

            var formData = $(this).serialize();

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData + '&action=wpmzf_save_time_entry&nonce=' + wpmzf_time.nonce,
                success: function(response) {
                    if (response.success) {
                        $('#time-entry-modal').remove();
                        if (typeof location !== 'undefined') {
                            location.reload();
                        }
                    } else {
                        alert('Błąd: ' + response.data);
                    }
                }
            });
        },

        showTimeEntryResult: function(data) {
            var hours = Math.floor(data.duration / 60);
            var minutes = data.duration % 60;
            var message = `Zapisano ${hours}h ${minutes}m`;

            // Można dodać lepsze powiadomienie
            alert(message);
        },

        pad: function(num) {
            return num < 10 ? '0' + num : num;
        }
    };

    // Event handlers for modal
    $(document).on('click', '.luna-crm-modal-close, .cancel-time-entry', function() {
        $('#time-entry-modal').remove();
    });

    // Inicjalizacja po załadowaniu DOM
    $(document).ready(function() {
        if (typeof wpmzf_time !== 'undefined') {
            TimeTracking.init();
        }
    });

})(jQuery);
