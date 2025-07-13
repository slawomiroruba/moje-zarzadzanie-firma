/**
 * JavaScript dla nawigacji górnej WPMZF
 */

(function($) {
    'use strict';

    let searchTimeout;
    const searchDelay = 300; // ms

    $(document).ready(function() {
        initNavbar();
        showNavbar(); // Pokaż nawigację po załadowaniu
    });
    
    /**
     * Pokazuje nawigację na stronach wtyczki
     */
    function showNavbar() {
        $('.wpmzf-navbar').show();
        // Dodaj padding do body jeśli nawigacja jest widoczna
        $('body').addClass('wpmzf-navbar-active');
    }

    function initNavbar() {
        initGlobalSearch();
        initDropdowns();
        initResponsive();
    }

    /**
     * Inicjalizuje globalną wyszukiwarkę
     */
    function initGlobalSearch() {
        const searchInput = $('#wpmzf-global-search');
        const searchResults = $('#wpmzf-search-results');
        const searchButton = $('.wpmzf-search-button');

        // Wyszukiwanie podczas pisania
        searchInput.on('input', function() {
            const searchTerm = $(this).val().trim();
            
            clearTimeout(searchTimeout);
            
            if (searchTerm.length < 2) {
                hideSearchResults();
                return;
            }

            searchTimeout = setTimeout(function() {
                performSearch(searchTerm);
            }, searchDelay);
        });

        // Wyszukiwanie po kliknięciu przycisku
        searchButton.on('click', function() {
            const searchTerm = searchInput.val().trim();
            if (searchTerm.length >= 2) {
                performSearch(searchTerm);
            }
        });

        // Enter key
        searchInput.on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                const searchTerm = $(this).val().trim();
                if (searchTerm.length >= 2) {
                    performSearch(searchTerm);
                }
            }
        });

        // Escape key
        searchInput.on('keyup', function(e) {
            if (e.which === 27) {
                hideSearchResults();
                $(this).blur();
            }
        });

        // Ukryj wyniki po kliknięciu poza wyszukiwarką
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.wpmzf-search-container').length) {
                hideSearchResults();
            }
        });

        // Focus na wyszukiwarkę z Ctrl+K
        $(document).on('keydown', function(e) {
            if (e.ctrlKey && e.which === 75) {
                e.preventDefault();
                searchInput.focus();
            }
        });
    }

    /**
     * Wykonuje wyszukiwanie AJAX
     */
    function performSearch(searchTerm) {
        const searchResults = $('#wpmzf-search-results');
        const searchContent = searchResults.find('.wpmzf-search-content');
        const searchLoading = searchResults.find('.wpmzf-search-loading');

        // Pokaż loading
        showSearchResults();
        searchLoading.show();
        searchContent.hide();

        $.ajax({
            url: wpmzfNavbar.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpmzf_global_search',
                search_term: searchTerm,
                nonce: wpmzfNavbar.nonce
            },
            success: function(response) {
                searchLoading.hide();
                
                if (response.success && response.data.length > 0) {
                    displaySearchResults(response.data);
                } else {
                    displayNoResults();
                }
                
                searchContent.show();
            },
            error: function() {
                searchLoading.hide();
                displayError();
                searchContent.show();
            }
        });
    }

    /**
     * Wyświetla wyniki wyszukiwania
     */
    function displaySearchResults(results) {
        const searchContent = $('.wpmzf-search-content');
        let html = '';

        results.forEach(function(group) {
            html += '<div class="wpmzf-search-group">';
            html += '<div class="wpmzf-search-group-header">';
            html += escapeHtml(group.label);
            if (group.count > group.items.length) {
                html += ' (' + group.count + ')';
            }
            html += '</div>';

            group.items.forEach(function(item) {
                html += '<a href="' + escapeHtml(item.url) + '" class="wpmzf-search-item">';
                html += '<div class="wpmzf-search-item-title">' + escapeHtml(item.title) + '</div>';
                if (item.excerpt) {
                    html += '<div class="wpmzf-search-item-excerpt">' + escapeHtml(item.excerpt) + '</div>';
                }
                html += '</a>';
            });

            html += '</div>';
        });

        searchContent.html(html);
    }

    /**
     * Wyświetla komunikat o braku wyników
     */
    function displayNoResults() {
        const searchContent = $('.wpmzf-search-content');
        searchContent.html('<div class="wpmzf-search-no-results">' + wpmzfNavbar.noResults + '</div>');
    }

    /**
     * Wyświetla komunikat o błędzie
     */
    function displayError() {
        const searchContent = $('.wpmzf-search-content');
        searchContent.html('<div class="wpmzf-search-no-results">Wystąpił błąd podczas wyszukiwania</div>');
    }

    /**
     * Pokazuje wyniki wyszukiwania
     */
    function showSearchResults() {
        $('#wpmzf-search-results').show();
    }

    /**
     * Ukrywa wyniki wyszukiwania
     */
    function hideSearchResults() {
        $('#wpmzf-search-results').hide();
    }

    /**
     * Inicjalizuje dropdowny menu
     */
    function initDropdowns() {
        let dropdownTimeout;

        $('.wpmzf-navbar-item').on('mouseenter', function() {
            clearTimeout(dropdownTimeout);
            const dropdown = $(this).find('.wpmzf-navbar-dropdown');
            if (dropdown.length) {
                dropdown.stop(true, true).show();
            }
        });

        $('.wpmzf-navbar-item').on('mouseleave', function() {
            const dropdown = $(this).find('.wpmzf-navbar-dropdown');
            if (dropdown.length) {
                dropdownTimeout = setTimeout(function() {
                    dropdown.stop(true, true).hide();
                }, 300);
            }
        });

        // Keyboard navigation dla dropdownów
        $('.wpmzf-navbar-link').on('keydown', function(e) {
            if (e.which === 40) { // Arrow down
                e.preventDefault();
                const dropdown = $(this).siblings('.wpmzf-navbar-dropdown');
                if (dropdown.length) {
                    dropdown.find('.wpmzf-navbar-dropdown-item:first').focus();
                }
            }
        });

        $('.wpmzf-navbar-dropdown-item').on('keydown', function(e) {
            if (e.which === 38) { // Arrow up
                e.preventDefault();
                const prev = $(this).prev('.wpmzf-navbar-dropdown-item');
                if (prev.length) {
                    prev.focus();
                } else {
                    $(this).closest('.wpmzf-navbar-item').find('.wpmzf-navbar-link').focus();
                }
            } else if (e.which === 40) { // Arrow down
                e.preventDefault();
                const next = $(this).next('.wpmzf-navbar-dropdown-item');
                if (next.length) {
                    next.focus();
                }
            } else if (e.which === 27) { // Escape
                $(this).closest('.wpmzf-navbar-item').find('.wpmzf-navbar-link').focus();
            }
        });
    }

    /**
     * Obsługa responsywności
     */
    function initResponsive() {
        // Dostosuj pozycję nawigacji do collapsed sidebar
        function adjustNavbarPosition() {
            const navbar = $('.wpmzf-navbar');
            const wpMenuWrap = $('#adminmenuback');
            
            // if (wpMenuWrap.length) {
            //     const isCollapsed = $('body').hasClass('folded');
            //     const sidebarWidth = isCollapsed ? 36 : 160;
            //     navbar.css('left', sidebarWidth + 'px');
            // }
        }

        // Sprawdź na początku
        adjustNavbarPosition();

        // Obserwuj zmiany w sidebar
        $(window).on('resize', adjustNavbarPosition);
        
        // Obserwuj klasę folded na body
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                    adjustNavbarPosition();
                }
            });
        });

        observer.observe(document.body, {
            attributes: true,
            attributeFilter: ['class']
        });
    }

    /**
     * Escape HTML
     */
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    // Dodaj placeholder dla wyszukiwarki z tipem
    $('#wpmzf-global-search').attr('title', 'Wyszukaj w systemie (Ctrl+K)');

})(jQuery);
