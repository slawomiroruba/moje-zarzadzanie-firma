/**
 * Users Management JavaScript
 * Przykład użycia REST API dla zarządzania użytkownikami
 *
 * @package WPMZF
 */

(function($) {
    'use strict';

    var Users = {
        apiUrl: wpApiSettings.root + 'wpmzf/v1/users',
        nonce: wpApiSettings.nonce,

        init: function() {
            this.bindEvents();
            this.loadUsers();
        },

        bindEvents: function() {
            $(document).on('click', '.add-user', this.showAddForm);
            $(document).on('click', '.edit-user', this.showEditForm);
            $(document).on('click', '.delete-user', this.deleteUser);
            $(document).on('submit', '#user-form', this.saveUser);
            $(document).on('click', '.search-users', this.searchUsers);
        },

        /**
         * Ładuje listę użytkowników
         */
        loadUsers: function() {
            $.ajax({
                url: Users.apiUrl,
                type: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', Users.nonce);
                },
                success: function(response) {
                    Users.renderUsers(response);
                },
                error: function(xhr, status, error) {
                    console.error('Błąd ładowania użytkowników:', error);
                    Users.showNotification('Błąd ładowania użytkowników', 'error');
                }
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
            $('#users-list').html(html);
        },

        /**
         * Pokazuje formularz dodawania użytkownika
         */
        showAddForm: function() {
            $('#user-form')[0].reset();
            $('#user-id').val('');
            $('#user-modal .modal-title').text('Dodaj użytkownika');
            $('#user-modal').show();
        },

        /**
         * Pokazuje formularz edycji użytkownika
         */
        showEditForm: function() {
            var userId = $(this).data('user-id');
            
            $.ajax({
                url: Users.apiUrl + '/' + userId,
                type: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', Users.nonce);
                },
                success: function(user) {
                    $('#user-id').val(user.id);
                    $('#user-name').val(user.name);
                    $('#user-email').val(user.email);
                    $('#user-phone').val(user.phone);
                    $('#user-position').val(user.position);
                    $('#user-modal .modal-title').text('Edytuj użytkownika');
                    $('#user-modal').show();
                },
                error: function(xhr, status, error) {
                    console.error('Błąd ładowania użytkownika:', error);
                    Users.showNotification('Błąd ładowania użytkownika', 'error');
                }
            });
        },

        /**
         * Zapisuje użytkownika (dodaje lub edytuje)
         */
        saveUser: function(e) {
            e.preventDefault();
            
            var form = $(this);
            var userId = $('#user-id').val();
            var isEdit = userId !== '';
            
            var userData = {
                name: $('#user-name').val(),
                email: $('#user-email').val(),
                phone: $('#user-phone').val(),
                position: $('#user-position').val()
            };
            
            var url = isEdit ? Users.apiUrl + '/' + userId : Users.apiUrl;
            var method = isEdit ? 'PUT' : 'POST';
            
            $.ajax({
                url: url,
                type: method,
                data: JSON.stringify(userData),
                contentType: 'application/json',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', Users.nonce);
                    form.find('button[type="submit"]').prop('disabled', true);
                },
                success: function(response) {
                    $('#user-modal').hide();
                    Users.showNotification(isEdit ? 'Użytkownik zaktualizowany' : 'Użytkownik dodany', 'success');
                    Users.loadUsers();
                },
                error: function(xhr, status, error) {
                    console.error('Błąd zapisywania użytkownika:', error);
                    var errorMessage = 'Błąd zapisywania użytkownika';
                    
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    }
                    
                    Users.showNotification(errorMessage, 'error');
                },
                complete: function() {
                    form.find('button[type="submit"]').prop('disabled', false);
                }
            });
        },

        /**
         * Usuwa użytkownika
         */
        deleteUser: function() {
            var userId = $(this).data('user-id');
            
            if (!confirm('Czy na pewno chcesz usunąć tego użytkownika?')) {
                return;
            }
            
            $.ajax({
                url: Users.apiUrl + '/' + userId,
                type: 'DELETE',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', Users.nonce);
                },
                success: function(response) {
                    Users.showNotification('Użytkownik usunięty', 'success');
                    Users.loadUsers();
                },
                error: function(xhr, status, error) {
                    console.error('Błąd usuwania użytkownika:', error);
                    Users.showNotification('Błąd usuwania użytkownika', 'error');
                }
            });
        },

        /**
         * Wyszukuje użytkowników
         */
        searchUsers: function() {
            var searchTerm = $('#user-search').val().trim();
            
            if (searchTerm.length < 2) {
                Users.showNotification('Wprowadź co najmniej 2 znaki', 'error');
                return;
            }
            
            $.ajax({
                url: Users.apiUrl + '/search?q=' + encodeURIComponent(searchTerm),
                type: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', Users.nonce);
                },
                success: function(response) {
                    Users.renderUsers(response);
                },
                error: function(xhr, status, error) {
                    console.error('Błąd wyszukiwania:', error);
                    Users.showNotification('Błąd wyszukiwania', 'error');
                }
            });
        },

        /**
         * Pokazuje powiadomienie
         */
        showNotification: function(message, type) {
            var className = type === 'success' ? 'notice-success' : 'notice-error';
            var notification = $('<div class="notice ' + className + ' is-dismissible"><p>' + message + '</p></div>');
            
            $('.wrap h1').after(notification);
            
            setTimeout(function() {
                notification.fadeOut();
            }, 3000);
        }
    };

    // Inicjalizacja gdy DOM jest gotowy
    $(document).ready(function() {
        if (typeof wpApiSettings !== 'undefined' && $('#users-management').length > 0) {
            Users.init();
        }
    });

    // Obsługa zamykania modalu
    $(document).on('click', '.modal-close, .cancel-user', function() {
        $('#user-modal').hide();
    });

    // Zamykanie modalu po kliknięciu w tło
    $(document).on('click', '.luna-crm-modal', function(e) {
        if (e.target === this) {
            $(this).hide();
        }
    });

    // Eksport do globalnego zasięgu dla innych skryptów
    window.WPMZFUsers = Users;

})(jQuery);

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
