<?php

class WPMZF_Access_Control {

    public function __construct() {
        // Podpinamy naszą funkcję do hooka 'template_redirect', który uruchamia się tuż przed wyświetleniem strony.
        add_action('template_redirect', array($this, 'redirect_non_logged_in_users'));
    }

    /**
     * Sprawdza, czy użytkownik jest zalogowany. Jeśli nie, przekierowuje go do strony logowania.
     */
    public function redirect_non_logged_in_users() {
        // Jeśli użytkownik jest zalogowany, nic więcej nie robimy.
        if (is_user_logged_in()) {
            return;
        }

        // Jeśli strona, na którą próbuje wejść użytkownik, to strona logowania, rejestracji
        // lub odzyskiwania hasła, również nic nie robimy, aby uniknąć pętli przekierowań.
        if (is_login() || is_register() || is_lost_password()) {
            return;
        }
        
        // Dla wszystkich innych przypadków (niezalogowany użytkownik na dowolnej stronie frontendu),
        // przekierowujemy go bezpiecznie do strony logowania.
        wp_safe_redirect(wp_login_url(), 302); // 302 to standardowe przekierowanie tymczasowe
        
        // Zawsze kończymy wykonywanie skryptu po przekierowaniu.
        exit;
    }
}