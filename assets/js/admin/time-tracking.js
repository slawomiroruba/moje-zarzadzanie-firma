/**
 * Time Tracking JavaScript
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

function serializeForm(form) {
    const formData = new FormData(form);
    const data = {};
    for (let [key, value] of formData.entries()) {
        data[key] = value;
    }
    return data;
}

(function() {
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
            document.addEventListener('click', function(e) {
                if (e.target.matches('#timer-start')) {
                    e.preventDefault();
                    TimeTracking.startTimer();
                }
                if (e.target.matches('#timer-stop')) {
                    e.preventDefault();
                    TimeTracking.stopTimer();
                }
                if (e.target.matches('#timer-pause')) {
                    e.preventDefault();
                    TimeTracking.pauseTimer();
                }
                if (e.target.matches('#timer-resume')) {
                    e.preventDefault();
                    TimeTracking.resumeTimer();
                }
                if (e.target.matches('.add-time-entry') || e.target.closest('.add-time-entry')) {
                    e.preventDefault();
                    TimeTracking.showTimeEntryForm();
                }
                if (e.target.matches('.luna-crm-modal-close, .cancel-time-entry') || e.target.closest('.luna-crm-modal-close, .cancel-time-entry')) {
                    e.preventDefault();
                    const modal = $('#time-entry-modal');
                    if (modal && modal.parentNode) {
                        modal.parentNode.removeChild(modal);
                    }
                }
            });

            document.addEventListener('submit', function(e) {
                if (e.target.matches('#time-entry-form')) {
                    e.preventDefault();
                    TimeTracking.saveTimeEntry.call(e.target, e);
                }
            });
        },

        startTimer: function() {
            var projectField = $('#timer-project');
            var descriptionField = $('#timer-description');
            var projectId = projectField ? projectField.value : '';
            var description = descriptionField ? descriptionField.value : '';

            if (!projectId) {
                alert('Wybierz projekt');
                return;
            }

            ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpmzf_start_timer',
                    project_id: projectId,
                    description: description,
                    nonce: wpmzf_time.nonce
                }
            }).then(function(response) {
                if (response.success) {
                    TimeTracking.isRunning = true;
                    TimeTracking.startTime = response.data.start_time;
                    TimeTracking.elapsed = 0;
                    TimeTracking.updateUI();
                    TimeTracking.startTimerDisplay();
                } else {
                    alert('Błąd: ' + response.data);
                }
            }).catch(function(error) {
                console.error('Error starting timer:', error);
                alert('Błąd komunikacji z serwerem');
            });
        },

        stopTimer: function() {
            if (!this.isRunning) return;

            ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpmzf_stop_timer',
                    nonce: wpmzf_time.nonce
                }
            }).then(function(response) {
                if (response.success) {
                    TimeTracking.isRunning = false;
                    TimeTracking.stopTimerDisplay();
                    TimeTracking.updateUI();
                    TimeTracking.showTimeEntryResult(response.data);
                } else {
                    alert('Błąd: ' + response.data);
                }
            }).catch(function(error) {
                console.error('Error stopping timer:', error);
                alert('Błąd komunikacji z serwerem');
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
            ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpmzf_get_timer_status',
                    nonce: wpmzf_time.nonce
                }
            }).then(function(response) {
                if (response.success && response.data.active) {
                    TimeTracking.isRunning = true;
                    TimeTracking.startTime = response.data.start_time;
                    TimeTracking.elapsed = response.data.elapsed;
                    
                    const projectField = $('#timer-project');
                    const descriptionField = $('#timer-description');
                    
                    if (projectField) projectField.value = response.data.project_id;
                    if (descriptionField) descriptionField.value = response.data.description;
                    
                    TimeTracking.updateUI();
                    TimeTracking.startTimerDisplay();
                }
            }).catch(function(error) {
                console.error('Error checking timer status:', error);
            });
        },

        startTimerDisplay: function() {
            if (this.timer) {
                clearInterval(this.timer);
            }

            this.timer = setInterval(function() {
                TimeTracking.elapsed++;
                TimeTracking.updateTimerDisplay();
            }, 1000);
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
            const timerDisplay = $('#timer-time');
            if (timerDisplay) {
                timerDisplay.textContent = display;
            }
        },

        updateUI: function() {
            const startBtn = $('#timer-start');
            const stopBtn = $('#timer-stop');
            const pauseBtn = $('#timer-pause');
            const resumeBtn = $('#timer-resume');
            const projectField = $('#timer-project');
            const descriptionField = $('#timer-description');

            if (this.isRunning) {
                if (startBtn) startBtn.style.display = 'none';
                if (stopBtn) stopBtn.style.display = 'inline-block';
                if (pauseBtn) pauseBtn.style.display = 'inline-block';
                if (resumeBtn) resumeBtn.style.display = 'none';
                if (projectField) projectField.disabled = true;
                if (descriptionField) descriptionField.disabled = true;
            } else {
                if (startBtn) startBtn.style.display = 'inline-block';
                if (stopBtn) stopBtn.style.display = 'none';
                if (pauseBtn) pauseBtn.style.display = 'none';
                if (resumeBtn) resumeBtn.style.display = 'inline-block';
                if (projectField) projectField.disabled = false;
                if (descriptionField) descriptionField.disabled = false;
            }
        },

        showTimeEntryForm: function() {
            var projectsOptions = '';
            if (typeof wpmzf_time !== 'undefined' && wpmzf_time.projects) {
                projectsOptions = wpmzf_time.projects.map(p => `<option value="${p.id}">${p.name}</option>`).join('');
            }

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
                                    ${projectsOptions}
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

            document.body.insertAdjacentHTML('beforeend', modal);
            const modalElement = $('#time-entry-modal');
            if (modalElement) modalElement.style.display = 'block';
        },

        saveTimeEntry: function(e) {
            e.preventDefault();

            var formData = serializeForm(this);
            formData.action = 'wpmzf_save_time_entry';
            formData.nonce = wpmzf_time.nonce;

            ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData
            }).then(function(response) {
                if (response.success) {
                    const modal = $('#time-entry-modal');
                    if (modal && modal.parentNode) {
                        modal.parentNode.removeChild(modal);
                    }
                    if (typeof location !== 'undefined') {
                        location.reload();
                    }
                } else {
                    alert('Błąd: ' + response.data);
                }
            }).catch(function(error) {
                console.error('Error saving time entry:', error);
                alert('Błąd komunikacji z serwerem');
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

    // Inicjalizacja po załadowaniu DOM
    ready(function() {
        if (typeof wpmzf_time !== 'undefined') {
            TimeTracking.init();
        }
    });

})();
