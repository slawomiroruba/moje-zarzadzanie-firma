<?php
/**
 * Test systemu e-mail - skrypt do przetestowania czy wszystko działa
 * 
 * Uruchom ten plik przez przeglądarkę po zalogowaniu jako admin:
 * /wp-content/plugins/moje-zarzadzanie-firma/test-email-system.php
 */

// Bezpieczeństwo - ładujemy WordPress
require_once('../../../wp-load.php');

// Sprawdź czy użytkownik jest zalogowany jako admin
if (!current_user_can('administrator')) {
    die('Brak uprawnień administratora');
}

echo '<h1>Test systemu e-mail WPMZF</h1>';

// Test 0: Napraw tabele jeśli są problemy
echo '<h2>Test 0: Naprawa tabel</h2>';

// Dodaj formularz do resetowania tabel
echo '<div style="background: #fff3cd; padding: 10px; margin: 10px 0; border-radius: 5px; border-left: 4px solid #ffc107;">';
echo '<strong>⚠️ Opcje naprawy:</strong><br>';
echo '<form method="post" action="" style="display: inline;">';
echo '<input type="submit" name="repair_tables" value="Napraw tabele" class="button-secondary" onclick="return confirm(\'Czy na pewno chcesz naprawić tabele? To może usunąć istniejące dane.\')">';
echo '</form> ';
echo '<form method="post" action="" style="display: inline;">';
echo '<input type="submit" name="reset_tables" value="Resetuj wszystkie tabele" class="button-secondary" onclick="return confirm(\'UWAGA: To usunie WSZYSTKIE dane e-mail! Czy na pewno chcesz kontynuować?\')">';
echo '</form>';
echo '</div>';

if (isset($_POST['repair_tables'])) {
    if (class_exists('WPMZF_Email_Database')) {
        WPMZF_Email_Database::repair_tables();
        echo "✅ Tabele zostały naprawione<br>";
    }
} elseif (isset($_POST['reset_tables'])) {
    if (class_exists('WPMZF_Email_Database')) {
        global $wpdb;
        // Usuń wszystkie tabele email
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}wpmzf_email_queue");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}wpmzf_email_threads");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}wpmzf_email_received");
        
        // Utwórz na nowo
        WPMZF_Email_Database::create_tables();
        echo "✅ Wszystkie tabele zostały zresetowane<br>";
    }
} else {
    if (class_exists('WPMZF_Email_Database')) {
        WPMZF_Email_Database::repair_tables();
        echo "✅ Tabele zostały sprawdzone i naprawione<br>";
    } else {
        echo "❌ Klasa WPMZF_Email_Database nie istnieje<br>";
    }
}

// Test 1: Sprawdź czy klasy istnieją
echo '<h2>Test 1: Sprawdzenie klas</h2>';
$classes_to_check = [
    'WPMZF_Email_Database',
    'WPMZF_User_Email_Settings', 
    'WPMZF_Email_Service',
    'WPMZF_Cron_Manager'
];

foreach ($classes_to_check as $class) {
    if (class_exists($class)) {
        echo "✅ Klasa $class istnieje<br>";
    } else {
        echo "❌ Klasa $class nie istnieje<br>";
    }
}

// Test 2: Sprawdź tabele bazy danych
echo '<h2>Test 2: Sprawdzenie tabel bazy danych</h2>';
global $wpdb;

$tables_to_check = [
    $wpdb->prefix . 'wpmzf_email_queue',
    $wpdb->prefix . 'wpmzf_email_received'
];

foreach ($tables_to_check as $table) {
    $result = $wpdb->get_var("SHOW TABLES LIKE '$table'");
    if ($result) {
        echo "✅ Tabela $table istnieje<br>";
        
        // Pokaż strukturę tabeli
        $columns = $wpdb->get_results("SHOW COLUMNS FROM $table");
        echo "Kolumny: ";
        foreach ($columns as $column) {
            echo $column->Field . ' ';
        }
        echo "<br>";
    } else {
        echo "❌ Tabela $table nie istnieje<br>";
    }
}

// Test 3: Sprawdź zadania cron
echo '<h2>Test 3: Sprawdzenie zadań cron</h2>';
$cron_jobs = _get_cron_array();
$email_cron_found = false;

foreach ($cron_jobs as $timestamp => $jobs) {
    foreach ($jobs as $hook => $job_array) {
        if ($hook === 'wpmzf_process_email_queue_hook') {
            echo "✅ Zadanie cron wpmzf_process_email_queue_hook znalezione (następne uruchomienie: " . date('Y-m-d H:i:s', $timestamp) . ")<br>";
            $email_cron_found = true;
        }
    }
}

if (!$email_cron_found) {
    echo "❌ Zadanie cron wpmzf_process_email_queue_hook nie znalezione<br>";
}

// Test 4: Sprawdź szyfrowanie haseł
echo '<h2>Test 4: Test szyfrowania haseł</h2>';
if (class_exists('WPMZF_User_Email_Settings')) {
    $email_settings = new WPMZF_User_Email_Settings();
    
    // Używamy reflection do testowania prywatnych metod
    $reflection = new ReflectionClass($email_settings);
    $encrypt_method = $reflection->getMethod('encrypt_password');
    $encrypt_method->setAccessible(true);
    $decrypt_method = $reflection->getMethod('decrypt_password');
    $decrypt_method->setAccessible(true);
    
    $test_password = 'test_password_123';
    $encrypted = $encrypt_method->invoke($email_settings, $test_password);
    $decrypted = $decrypt_method->invoke($email_settings, $encrypted);
    
    if ($decrypted === $test_password) {
        echo "✅ Szyfrowanie/odszyfrowanie działa poprawnie<br>";
        echo "Oryginał: $test_password<br>";
        echo "Zaszyfrowane: $encrypted<br>";
        echo "Odszyfrowane: $decrypted<br>";
    } else {
        echo "❌ Błąd szyfrowania/odszyfrowania<br>";
        echo "Oryginał: $test_password<br>";
        echo "Odszyfrowane: $decrypted<br>";
    }
} else {
    echo "❌ Nie można przetestować szyfrowania - klasa WPMZF_User_Email_Settings nie istnieje<br>";
}

// Test 5: Test email service
echo '<h2>Test 5: Test kolejkowania e-maila</h2>';

// Sprawdź czy użytkownik ma ustawienia SMTP
$current_user_id = get_current_user_id();
$smtp_settings = get_user_meta($current_user_id, 'wpmzf_smtp_settings', true);

if (empty($smtp_settings['host']) || empty($smtp_settings['user'])) {
    echo "⚠️ Brak ustawień SMTP dla użytkownika. <a href='#setup-smtp'>Skonfiguruj poniżej</a><br>";
    
    // Formularz do szybkiej konfiguracji testowej
    echo '<div id="setup-smtp" style="background: #f0f0f0; padding: 15px; margin: 10px 0; border-radius: 5px;">';
    echo '<h3>Szybka konfiguracja SMTP (tylko do testów)</h3>';
    echo '<form method="post" action="">';
    echo '<p><strong>Host SMTP:</strong> <input type="text" name="smtp_host" placeholder="smtp.gmail.com" style="width: 200px;"></p>';
    echo '<p><strong>Port:</strong> <input type="text" name="smtp_port" value="587" style="width: 80px;"></p>';
    echo '<p><strong>E-mail:</strong> <input type="email" name="smtp_user" placeholder="twoj-email@gmail.com" style="width: 250px;"></p>';
    echo '<p><strong>Hasło:</strong> <input type="password" name="smtp_pass" placeholder="hasło lub app password" style="width: 200px;"></p>';
    echo '<p><strong>Szyfrowanie:</strong> 
           <select name="smtp_encryption">
               <option value="tls">TLS</option>
               <option value="ssl">SSL</option>
           </select></p>';
    echo '<p><input type="submit" name="setup_smtp" value="Zapisz ustawienia testowe" class="button-primary"></p>';
    echo '</form>';
    echo '</div>';
    
    // Obsługa formularza
    if (isset($_POST['setup_smtp'])) {
        if (class_exists('WPMZF_User_Email_Settings')) {
            $email_settings = new WPMZF_User_Email_Settings();
            
            // Używamy reflection do uzyskania dostępu do metody encrypt_password
            $reflection = new ReflectionClass($email_settings);
            $encrypt_method = $reflection->getMethod('encrypt_password');
            $encrypt_method->setAccessible(true);
            
            $test_smtp_settings = [
                'host' => sanitize_text_field($_POST['smtp_host']),
                'port' => sanitize_text_field($_POST['smtp_port']),
                'user' => sanitize_email($_POST['smtp_user']),
                'pass' => $encrypt_method->invoke($email_settings, $_POST['smtp_pass']),
                'encryption' => sanitize_text_field($_POST['smtp_encryption'])
            ];
            
            update_user_meta($current_user_id, 'wpmzf_smtp_settings', $test_smtp_settings);
            echo "<p style='color: green;'>✅ Ustawienia SMTP zapisane! Odśwież stronę aby kontynuować test.</p>";
        }
    }
}

if (class_exists('WPMZF_Email_Service')) {
    $email_service = new WPMZF_Email_Service();
    
    // Sprawdź ponownie po ewentualnym zapisie
    $smtp_settings = get_user_meta($current_user_id, 'wpmzf_smtp_settings', true);
    
    if (!empty($smtp_settings['host']) && !empty($smtp_settings['user'])) {
        // Próba dodania testowego e-maila do kolejki
        $result = $email_service->queue_email(
            $current_user_id,
            'test@example.com',
            'Test e-mail z systemu WPMZF',
            'To jest testowa wiadomość z systemu zarządzania firmą.',
            '',
            '',
            ['test' => true]
        );
        
        if (is_wp_error($result)) {
            echo "❌ Błąd kolejkowania e-maila: " . $result->get_error_message() . "<br>";
        } else {
            echo "✅ E-mail dodany do kolejki (ID: $result)<br>";
            
            // Sprawdź czy e-mail jest w bazie
            $email_data = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}wpmzf_email_queue WHERE id = $result");
            if ($email_data) {
                echo "✅ E-mail znaleziony w bazie danych<br>";
                echo "Status: {$email_data->status}<br>";
                echo "Odbiorca: {$email_data->recipient_to}<br>";
                echo "Temat: {$email_data->subject}<br>";
            }
        }
    }
} else {
    echo "❌ Klasa WPMZF_Email_Service nie istnieje<br>";
}

// Test 6: Sprawdź statystyki
echo '<h2>Test 6: Statystyki kolejki e-maili</h2>';
if (class_exists('WPMZF_Email_Service')) {
    $email_service = new WPMZF_Email_Service();
    $stats = $email_service->get_queue_stats();
    
    echo "📊 Statystyki kolejki:<br>";
    echo "Wszystkich: {$stats['total']}<br>";
    echo "Oczekujących: {$stats['pending']}<br>";
    echo "Wysłanych: {$stats['sent']}<br>";
    echo "Nieudanych: {$stats['failed']}<br>";
}

echo '<h2>Podsumowanie</h2>';

// Test 7: Test bezpośredniego wysłania e-maila
echo '<h2>Test 7: Test bezpośredniego wysłania e-maila</h2>';
$smtp_settings = get_user_meta($current_user_id, 'wpmzf_smtp_settings', true);

if (!empty($smtp_settings['host']) && !empty($smtp_settings['user'])) {
    echo '<div style="background: #e7f3ff; padding: 15px; margin: 10px 0; border-radius: 5px;">';
    echo '<h3>Test wysłania rzeczywistego e-maila</h3>';
    echo '<form method="post" action="">';
    echo '<p><strong>Do:</strong> <input type="email" name="test_email_to" placeholder="twoj-email@test.com" style="width: 250px;" required></p>';
    echo '<p><strong>Temat:</strong> <input type="text" name="test_email_subject" value="Test z systemu WPMZF" style="width: 300px;" required></p>';
    echo '<p><strong>Treść:</strong><br><textarea name="test_email_body" rows="4" cols="50">To jest testowa wiadomość wysłana z systemu WPMZF.\n\nJeśli otrzymałeś tę wiadomość, oznacza to, że system e-mail działa poprawnie!</textarea></p>';
    echo '<p><input type="submit" name="send_test_email" value="Wyślij testowy e-mail" class="button-secondary" onclick="return confirm(\'Czy na pewno chcesz wysłać testowy e-mail?\')"></p>';
    echo '</form>';
    echo '</div>';
    
    // Obsługa formularza wysłania
    if (isset($_POST['send_test_email'])) {
        if (class_exists('WPMZF_Email_Service')) {
            $email_service = new WPMZF_Email_Service();
            
            $test_result = $email_service->send_test_email(
                $current_user_id,
                sanitize_email($_POST['test_email_to']),
                sanitize_text_field($_POST['test_email_subject']),
                wp_kses_post($_POST['test_email_body'])
            );
            
            if (is_wp_error($test_result)) {
                echo "<p style='color: red;'>❌ Błąd wysłania: " . $test_result->get_error_message() . "</p>";
            } elseif ($test_result === true) {
                echo "<p style='color: green;'>✅ E-mail został pomyślnie wysłany!</p>";
            } else {
                echo "<p style='color: orange;'>⚠️ Nieoczekiwany wynik: " . print_r($test_result, true) . "</p>";
            }
        }
    }
} else {
    echo "⚠️ Skonfiguruj najpierw ustawienia SMTP powyżej<br>";
}

echo '<h2>Podsumowanie</h2>';
echo '<p>Test zakończony. Sprawdź wyniki powyżej, aby upewnić się, że wszystkie komponenty działają poprawnie.</p>';
echo '<p><strong>Następne kroki:</strong></p>';
echo '<ol>';
echo '<li>Skonfiguruj ustawienia SMTP w profilu użytkownika</li>';
echo '<li>Przetestuj wysyłanie e-maila z formularza aktywności</li>';
echo '<li>Sprawdź logi WordPress w przypadku błędów</li>';
echo '</ol>';
?>
