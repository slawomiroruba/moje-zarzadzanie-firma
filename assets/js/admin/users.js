/**
 * Users Management JavaScript
 * Przykład użycia REST API dla zarządzania użytkownikami
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

function fadeOut(element, duration = 300) {
    element.style.transition = `opacity ${duration}ms`;
    element.style.opacity = '0';
    setTimeout(() => {
        if (element.parentNode) {
            element.parentNode.removeChild(element);
        }
    }, duration);
}

(function() {
    'use strict';

    var Users = {
        apiUrl: (typeof wpApiSettings !== 'undefined') ? wpApiSettings.root + 'wpmzf/v1/users' : '',
        nonce: (typeof wpApiSettings !== 'undefined') ? wpApiSettings.nonce : '',

        init: function() {
            this.bindEvents();
            this.loadUsers();
        },

        bindEvents: function() {
            document.addEventListener('click', function(e) {
                if (e.target.matches('.add-user') || e.target.closest('.add-user')) {
                    e.preventDefault();
                    Users.showAddForm();
                }
                if (e.target.matches('.edit-user') || e.target.closest('.edit-user')) {
                    e.preventDefault();
                    Users.showEditForm.call(e.target.closest('.edit-user'));
                }
                if (e.target.matches('.delete-user') || e.target.closest('.delete-user')) {
                    e.preventDefault();
                    Users.deleteUser.call(e.target.closest('.delete-user'));
                }
                if (e.target.matches('.search-users') || e.target.closest('.search-users')) {
                    e.preventDefault();
                    Users.searchUsers();
                }
                if (e.target.matches('.modal-close, .cancel-user') || e.target.closest('.modal-close, .cancel-user')) {
                    e.preventDefault();
                    const modal = $('#user-modal');
                    if (modal) modal.style.display = 'none';
                }
                if (e.target.matches('.luna-crm-modal')) {
                    if (e.target === e.currentTarget) {
                        e.target.style.display = 'none';
                    }
                }
            });

            document.addEventListener('submit', function(e) {
                if (e.target.matches('#user-form')) {
                    e.preventDefault();
                    Users.saveUser.call(e.target, e);
                }
            });
        },

        /**
         * Ładuje listę użytkowników
         */
        loadUsers: function() {
            if (!Users.apiUrl) {
                console.error('API URL not available');
                return;
            }

            fetch(Users.apiUrl, {
                method: 'GET',
                headers: {
                    'X-WP-Nonce': Users.nonce
                }
            })
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(function(data) {
                Users.renderUsers(data);
            })
            .catch(function(error) {
                console.error('Błąd ładowania użytkowników:', error);
                Users.showNotification('Błąd ładowania użytkowników', 'error');
            });
        },

        /**
         * Renderuje listę użytkowników
         */
        renderUsers: function(users) {
            var html = '<table class="wp-list-table widefat fixed striped">';
            html += '<thead><tr><th>ID</th><th>Imię</th><th>Email</th><th>Telefon</th><th>Stanowisko</th><th>Akcje</th></tr></thead>';
            html += '<tbody>';

            users.forEach(function(user) {
                html += '<tr>';
                html += '<td>' + user.id + '</td>';
                html += '<td>' + user.name + '</td>';
                html += '<td><a href="mailto:' + user.email + '">' + user.email + '</a></td>';
                html += '<td>' + (user.phone || '-') + '</td>';
                html += '<td>' + (user.position || '-') + '</td>';
                html += '<td>';
                html += '<button class="button edit-user" data-user-id="' + user.id + '">Edytuj</button> ';
                html += '<button class="button delete-user" data-user-id="' + user.id + '">Usuń</button>';
                html += '</td>';
                html += '</tr>';
            });

            html += '</tbody></table>';
            const usersList = $('#users-list');
            if (usersList) {
                usersList.innerHTML = html;
            }
        },

        /**
         * Pokazuje formularz dodawania użytkownika
         */
        showAddForm: function() {
            const form = $('#user-form');
            const modal = $('#user-modal');
            const title = modal ? modal.querySelector('.modal-title') : null;
            const idField = $('#user-id');

            if (form) form.reset();
            if (idField) idField.value = '';
            if (title) title.textContent = 'Dodaj użytkownika';
            if (modal) modal.style.display = 'block';
        },

        /**
         * Pokazuje formularz edycji użytkownika
         */
        showEditForm: function() {
            var userId = this.getAttribute('data-user-id');
            
            if (!Users.apiUrl) {
                console.error('API URL not available');
                return;
            }

            fetch(Users.apiUrl + '/' + userId, {
                method: 'GET',
                headers: {
                    'X-WP-Nonce': Users.nonce
                }
            })
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(function(user) {
                const fields = {
                    'user-id': user.id,
                    'user-name': user.name,
                    'user-email': user.email,
                    'user-phone': user.phone,
                    'user-position': user.position
                };

                Object.keys(fields).forEach(function(fieldId) {
                    const field = $('#' + fieldId);
                    if (field) {
                        field.value = fields[fieldId] || '';
                    }
                });

                const modal = $('#user-modal');
                const title = modal ? modal.querySelector('.modal-title') : null;
                if (title) title.textContent = 'Edytuj użytkownika';
                if (modal) modal.style.display = 'block';
            })
            .catch(function(error) {
                console.error('Błąd ładowania użytkownika:', error);
                Users.showNotification('Błąd ładowania użytkownika', 'error');
            });
        },

        /**
         * Zapisuje użytkownika (dodaje lub edytuje)
         */
        saveUser: function(e) {
            e.preventDefault();
            
            var form = this;
            var userIdField = $('#user-id');
            var userId = userIdField ? userIdField.value : '';
            var isEdit = userId !== '';
            
            var userData = {
                name: $('#user-name').value || '',
                email: $('#user-email').value || '',
                phone: $('#user-phone').value || '',
                position: $('#user-position').value || ''
            };
            
            var url = isEdit ? Users.apiUrl + '/' + userId : Users.apiUrl;
            var method = isEdit ? 'PUT' : 'POST';
            
            const submitButton = form.querySelector('button[type="submit"]');
            if (submitButton) submitButton.disabled = true;

            fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': Users.nonce
                },
                body: JSON.stringify(userData)
            })
            .then(function(response) {
                if (!response.ok) {
                    return response.json().then(function(errorData) {
                        throw new Error(errorData.message || 'Request failed');
                    });
                }
                return response.json();
            })
            .then(function(data) {
                const modal = $('#user-modal');
                if (modal) modal.style.display = 'none';
                Users.showNotification(isEdit ? 'Użytkownik zaktualizowany' : 'Użytkownik dodany', 'success');
                Users.loadUsers();
            })
            .catch(function(error) {
                console.error('Błąd zapisywania użytkownika:', error);
                Users.showNotification(error.message || 'Błąd zapisywania użytkownika', 'error');
            })
            .finally(function() {
                if (submitButton) submitButton.disabled = false;
            });
        },

        /**
         * Usuwa użytkownika
         */
        deleteUser: function() {
            var userId = this.getAttribute('data-user-id');
            
            if (!confirm('Czy na pewno chcesz usunąć tego użytkownika?')) {
                return;
            }
            
            fetch(Users.apiUrl + '/' + userId, {
                method: 'DELETE',
                headers: {
                    'X-WP-Nonce': Users.nonce
                }
            })
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(function(data) {
                Users.showNotification('Użytkownik usunięty', 'success');
                Users.loadUsers();
            })
            .catch(function(error) {
                console.error('Błąd usuwania użytkownika:', error);
                Users.showNotification('Błąd usuwania użytkownika', 'error');
            });
        },

        /**
         * Wyszukuje użytkowników
         */
        searchUsers: function() {
            var searchField = $('#user-search');
            var searchTerm = searchField ? searchField.value.trim() : '';
            
            if (searchTerm.length < 2) {
                Users.showNotification('Wprowadź co najmniej 2 znaki', 'error');
                return;
            }
            
            fetch(Users.apiUrl + '/search?q=' + encodeURIComponent(searchTerm), {
                method: 'GET',
                headers: {
                    'X-WP-Nonce': Users.nonce
                }
            })
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(function(data) {
                Users.renderUsers(data);
            })
            .catch(function(error) {
                console.error('Błąd wyszukiwania:', error);
                Users.showNotification('Błąd wyszukiwania', 'error');
            });
        },

        /**
         * Pokazuje powiadomienie
         */
        showNotification: function(message, type) {
            // Remove existing notifications
            const existingNotifications = $$('.notice');
            existingNotifications.forEach(function(notification) {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            });

            var className = type === 'success' ? 'notice-success' : 'notice-error';
            var notification = document.createElement('div');
            notification.className = 'notice ' + className + ' is-dismissible';
            notification.innerHTML = '<p>' + message + '</p>';
            
            const wrap = $('.wrap h1');
            if (wrap && wrap.parentNode) {
                wrap.parentNode.insertBefore(notification, wrap.nextSibling);
            } else {
                document.body.appendChild(notification);
            }
            
            setTimeout(function() {
                fadeOut(notification);
            }, 3000);
        }
    };

    // Inicjalizacja gdy DOM jest gotowy
    ready(function() {
        if (typeof wpApiSettings !== 'undefined' && $('#users-management')) {
            Users.init();
        }
    });

    // Eksport do globalnego zasięgu dla innych skryptów
    window.WPMZFUsers = Users;

})();

// Przykłady użycia API z poziomu konsoli:

/*
// Pobranie listy użytkowników
fetch('/wp-json/wpmzf/v1/users', {
    headers: {
        'X-WP-Nonce': wpApiSettings.nonce
    }
})
.then(response => response.json())
.then(data => console.log(data));

// Dodanie nowego użytkownika
fetch('/wp-json/wpmzf/v1/users', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': wpApiSettings.nonce
    },
    body: JSON.stringify({
        name: 'Jan Kowalski',
        email: 'jan@example.com',
        phone: '123456789',
        position: 'Developer'
    })
})
.then(response => response.json())
.then(data => console.log(data));

// Aktualizacja użytkownika
fetch('/wp-json/wpmzf/v1/users/1', {
    method: 'PUT',
    headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': wpApiSettings.nonce
    },
    body: JSON.stringify({
        name: 'Jan Kowalski Updated',
        email: 'jan.updated@example.com'
    })
})
.then(response => response.json())
.then(data => console.log(data));

// Usunięcie użytkownika
fetch('/wp-json/wpmzf/v1/users/1', {
    method: 'DELETE',
    headers: {
        'X-WP-Nonce': wpApiSettings.nonce
    }
})
.then(response => response.json())
.then(data => console.log(data));

// Wyszukiwanie użytkowników
fetch('/wp-json/wpmzf/v1/users/search?q=jan', {
    headers: {
        'X-WP-Nonce': wpApiSettings.nonce
    }
})
.then(response => response.json())
.then(data => console.log(data));
*/
