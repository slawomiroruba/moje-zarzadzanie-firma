<?php

/**
 * Klasa do zarządzania ustawieniami e-mail w profilu użytkownika
 *
 * @package WPMZF
 * @subpackage Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPMZF_User_Email_Settings {

    /**
     * Konstruktor
     */
    public function __construct() {
        add_action('show_user_profile', [$this, 'display_email_fields']);
        add_action('edit_user_profile', [$this, 'display_email_fields']);
        add_action('personal_options_update', [$this, 'save_email_fields']);
        add_action('edit_user_profile_update', [$this, 'save_email_fields']);
        
        // AJAX dla testowania połączenia
        add_action('wp_ajax_wpmzf_test_email_connection', [$this, 'test_email_connection']);
        
        // Dodaj style CSS
        add_action('admin_head-profile.php', [$this, 'add_profile_styles']);
        add_action('admin_head-user-edit.php', [$this, 'add_profile_styles']);
    }

    /**
     * Wyświetla pola ustawień e-mail w profilu użytkownika
     */
    public function display_email_fields($user) {
        if (!current_user_can('edit_user', $user->ID)) {
            return;
        }

        $smtp_settings = get_user_meta($user->ID, 'wpmzf_smtp_settings', true);
        $imap_settings = get_user_meta($user->ID, 'wpmzf_imap_settings', true);

        // Domyślne wartości
        $smtp_settings = wp_parse_args($smtp_settings, [
            'host' => '',
            'port' => '587',
            'user' => '',
            'encryption' => 'tls'
        ]);

        $imap_settings = wp_parse_args($imap_settings, [
            'host' => '',
            'port' => '993',
            'user' => '',
            'encryption' => 'ssl'
        ]);

        wp_nonce_field('wpmzf_email_settings_save', 'wpmzf_email_nonce');
        ?>
        <h2 id="wpmzf-email-settings">Ustawienia skrzynek e-mail</h2>
        <div class="wpmzf-email-settings-container">
            
            <!-- SMTP Settings -->
            <div class="wpmzf-email-section">
                <h3>📤 Ustawienia SMTP (wysyłanie e-maili)</h3>
                <table class="form-table" role="presentation">
                    <tr>
                        <th><label for="smtp_host">Host SMTP</label></th>
                        <td>
                            <input type="text" name="smtp_host" id="smtp_host" 
                                   value="<?php echo esc_attr($smtp_settings['host']); ?>" 
                                   class="regular-text" placeholder="np. smtp.gmail.com" />
                            <p class="description">Adres serwera SMTP Twojego dostawcy poczty</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="smtp_port">Port SMTP</label></th>
                        <td>
                            <input type="number" name="smtp_port" id="smtp_port" 
                                   value="<?php echo esc_attr($smtp_settings['port']); ?>" 
                                   class="small-text" min="1" max="65535" />
                            <p class="description">Zwykle 587 (TLS) lub 465 (SSL)</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="smtp_user">Użytkownik SMTP</label></th>
                        <td>
                            <input type="email" name="smtp_user" id="smtp_user" 
                                   value="<?php echo esc_attr($smtp_settings['user']); ?>" 
                                   class="regular-text" placeholder="twoj-email@example.com" />
                            <p class="description">Twój adres e-mail</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="smtp_pass">Hasło SMTP</label></th>
                        <td>
                            <input type="password" name="smtp_pass" id="smtp_pass" 
                                   value="" class="regular-text" 
                                   placeholder="<?php echo !empty($smtp_settings['user']) ? 'Hasło jest zapisane' : 'Wprowadź hasło'; ?>" />
                            <p class="description">Hasło do Twojego konta e-mail lub hasło aplikacji</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="smtp_encryption">Szyfrowanie SMTP</label></th>
                        <td>
                            <select name="smtp_encryption" id="smtp_encryption">
                                <option value="tls" <?php selected($smtp_settings['encryption'], 'tls'); ?>>TLS (zalecane)</option>
                                <option value="ssl" <?php selected($smtp_settings['encryption'], 'ssl'); ?>>SSL</option>
                                <option value="none" <?php selected($smtp_settings['encryption'], 'none'); ?>>Brak</option>
                            </select>
                        </td>
                    </tr>
                </table>
                <button type="button" class="button" id="test-smtp-connection">🧪 Testuj połączenie SMTP</button>
                <div id="smtp-test-result" class="wpmzf-test-result"></div>
            </div>

            <!-- IMAP Settings -->
            <div class="wpmzf-email-section">
                <h3>📥 Ustawienia IMAP (odbieranie e-maili)</h3>
                <table class="form-table" role="presentation">
                    <tr>
                        <th><label for="imap_host">Host IMAP</label></th>
                        <td>
                            <input type="text" name="imap_host" id="imap_host" 
                                   value="<?php echo esc_attr($imap_settings['host']); ?>" 
                                   class="regular-text" placeholder="np. imap.gmail.com" />
                            <p class="description">Adres serwera IMAP Twojego dostawcy poczty</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="imap_port">Port IMAP</label></th>
                        <td>
                            <input type="number" name="imap_port" id="imap_port" 
                                   value="<?php echo esc_attr($imap_settings['port']); ?>" 
                                   class="small-text" min="1" max="65535" />
                            <p class="description">Zwykle 993 (SSL) lub 143 (bez szyfrowania)</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="imap_user">Użytkownik IMAP</label></th>
                        <td>
                            <input type="email" name="imap_user" id="imap_user" 
                                   value="<?php echo esc_attr($imap_settings['user']); ?>" 
                                   class="regular-text" placeholder="twoj-email@example.com" />
                            <p class="description">Zwykle ten sam co SMTP</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="imap_pass">Hasło IMAP</label></th>
                        <td>
                            <input type="password" name="imap_pass" id="imap_pass" 
                                   value="" class="regular-text" 
                                   placeholder="<?php echo !empty($imap_settings['user']) ? 'Hasło jest zapisane' : 'Wprowadź hasło'; ?>" />
                            <p class="description">Hasło do Twojego konta e-mail lub hasło aplikacji</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="imap_encryption">Szyfrowanie IMAP</label></th>
                        <td>
                            <select name="imap_encryption" id="imap_encryption">
                                <option value="ssl" <?php selected($imap_settings['encryption'], 'ssl'); ?>>SSL (zalecane)</option>
                                <option value="tls" <?php selected($imap_settings['encryption'], 'tls'); ?>>TLS</option>
                                <option value="none" <?php selected($imap_settings['encryption'], 'none'); ?>>Brak</option>
                            </select>
                        </td>
                    </tr>
                </table>
                <button type="button" class="button" id="test-imap-connection">🧪 Testuj połączenie IMAP</button>
                <div id="imap-test-result" class="wpmzf-test-result"></div>
            </div>

            <div class="wpmzf-email-help">
                <h4>💡 Popularne ustawienia dostawców:</h4>
                <div class="wpmzf-provider-examples">
                    <div><strong>Gmail:</strong> SMTP: smtp.gmail.com:587 (TLS), IMAP: imap.gmail.com:993 (SSL)</div>
                    <div><strong>Outlook/Hotmail:</strong> SMTP: smtp-mail.outlook.com:587 (TLS), IMAP: outlook.office365.com:993 (SSL)</div>
                    <div><strong>Yahoo:</strong> SMTP: smtp.mail.yahoo.com:587 (TLS), IMAP: imap.mail.yahoo.com:993 (SSL)</div>
                </div>
                <p><em>⚠️ Pamiętaj: Dla Gmail i innych dostawców może być wymagane włączenie dostępu dla aplikacji mniej bezpiecznych lub utworzenie hasła aplikacji.</em></p>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Test SMTP connection
            $('#test-smtp-connection').on('click', function() {
                var button = $(this);
                var result = $('#smtp-test-result');
                
                button.prop('disabled', true).text('Testowanie...');
                result.removeClass('success error').text('');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wpmzf_test_email_connection',
                        type: 'smtp',
                        host: $('#smtp_host').val(),
                        port: $('#smtp_port').val(),
                        user: $('#smtp_user').val(),
                        pass: $('#smtp_pass').val(),
                        encryption: $('#smtp_encryption').val(),
                        nonce: $('[name="wpmzf_email_nonce"]').val()
                    },
                    success: function(response) {
                        if (response.success) {
                            result.addClass('success').text('✅ ' + response.data.message);
                        } else {
                            result.addClass('error').text('❌ ' + response.data.message);
                        }
                    },
                    error: function() {
                        result.addClass('error').text('❌ Błąd połączenia z serwerem');
                    },
                    complete: function() {
                        button.prop('disabled', false).text('🧪 Testuj połączenie SMTP');
                    }
                });
            });

            // Test IMAP connection
            $('#test-imap-connection').on('click', function() {
                var button = $(this);
                var result = $('#imap-test-result');
                
                button.prop('disabled', true).text('Testowanie...');
                result.removeClass('success error').text('');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wpmzf_test_email_connection',
                        type: 'imap',
                        host: $('#imap_host').val(),
                        port: $('#imap_port').val(),
                        user: $('#imap_user').val(),
                        pass: $('#imap_pass').val(),
                        encryption: $('#imap_encryption').val(),
                        nonce: $('[name="wpmzf_email_nonce"]').val()
                    },
                    success: function(response) {
                        if (response.success) {
                            result.addClass('success').text('✅ ' + response.data.message);
                        } else {
                            result.addClass('error').text('❌ ' + response.data.message);
                        }
                    },
                    error: function() {
                        result.addClass('error').text('❌ Błąd połączenia z serwerem');
                    },
                    complete: function() {
                        button.prop('disabled', false).text('🧪 Testuj połączenie IMAP');
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Zapisuje ustawienia e-mail użytkownika
     */
    public function save_email_fields($user_id) {
        if (!current_user_can('edit_user', $user_id) || 
            !isset($_POST['wpmzf_email_nonce']) || 
            !wp_verify_nonce($_POST['wpmzf_email_nonce'], 'wpmzf_email_settings_save')) {
            return;
        }

        // Zapisywanie ustawień SMTP
        $smtp_settings = [
            'host' => sanitize_text_field($_POST['smtp_host'] ?? ''),
            'port' => intval($_POST['smtp_port'] ?? 587),
            'user' => sanitize_email($_POST['smtp_user'] ?? ''),
            'encryption' => sanitize_text_field($_POST['smtp_encryption'] ?? 'tls')
        ];

        // Zapisz hasło SMTP tylko jeśli zostało wprowadzone
        if (!empty($_POST['smtp_pass'])) {
            $smtp_settings['pass'] = $this->encrypt_password($_POST['smtp_pass']);
        } else {
            // Zachowaj istniejące hasło
            $existing_smtp = get_user_meta($user_id, 'wpmzf_smtp_settings', true);
            if (!empty($existing_smtp['pass'])) {
                $smtp_settings['pass'] = $existing_smtp['pass'];
            }
        }

        update_user_meta($user_id, 'wpmzf_smtp_settings', $smtp_settings);

        // Zapisywanie ustawień IMAP
        $imap_settings = [
            'host' => sanitize_text_field($_POST['imap_host'] ?? ''),
            'port' => intval($_POST['imap_port'] ?? 993),
            'user' => sanitize_email($_POST['imap_user'] ?? ''),
            'encryption' => sanitize_text_field($_POST['imap_encryption'] ?? 'ssl')
        ];

        // Zapisz hasło IMAP tylko jeśli zostało wprowadzone
        if (!empty($_POST['imap_pass'])) {
            $imap_settings['pass'] = $this->encrypt_password($_POST['imap_pass']);
        } else {
            // Zachowaj istniejące hasło
            $existing_imap = get_user_meta($user_id, 'wpmzf_imap_settings', true);
            if (!empty($existing_imap['pass'])) {
                $imap_settings['pass'] = $existing_imap['pass'];
            }
        }

        update_user_meta($user_id, 'wpmzf_imap_settings', $imap_settings);
    }

    /**
     * Testuje połączenie e-mail przez AJAX
     */
    public function test_email_connection() {
        if (!wp_verify_nonce($_POST['nonce'], 'wpmzf_email_settings_save')) {
            wp_send_json_error(['message' => 'Nieprawidłowy token bezpieczeństwa']);
            return;
        }

        $type = sanitize_text_field($_POST['type']);
        $host = sanitize_text_field($_POST['host']);
        $port = intval($_POST['port']);
        $user = sanitize_email($_POST['user']);
        $pass = $_POST['pass'];
        $encryption = sanitize_text_field($_POST['encryption']);

        if (empty($host) || empty($user) || empty($pass)) {
            wp_send_json_error(['message' => 'Wszystkie pola są wymagane']);
            return;
        }

        if ($type === 'smtp') {
            $result = $this->test_smtp_connection($host, $port, $user, $pass, $encryption);
        } elseif ($type === 'imap') {
            $result = $this->test_imap_connection($host, $port, $user, $pass, $encryption);
        } else {
            wp_send_json_error(['message' => 'Nieprawidłowy typ połączenia']);
            return;
        }

        if ($result['success']) {
            wp_send_json_success(['message' => $result['message']]);
        } else {
            wp_send_json_error(['message' => $result['message']]);
        }
    }

    /**
     * Testuje połączenie SMTP
     */
    private function test_smtp_connection($host, $port, $user, $pass, $encryption) {
        require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
        require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
        require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';

        $mail = new PHPMailer\PHPMailer\PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = $host;
            $mail->SMTPAuth = true;
            $mail->Username = $user;
            $mail->Password = $pass;
            $mail->Port = $port;

            if ($encryption === 'ssl') {
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($encryption === 'tls') {
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            }

            // Test połączenia
            $mail->smtpConnect();
            $mail->smtpClose();

            return ['success' => true, 'message' => 'Połączenie SMTP udane'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Błąd SMTP: ' . $e->getMessage()];
        }
    }

    /**
     * Testuje połączenie IMAP
     */
    private function test_imap_connection($host, $port, $user, $pass, $encryption) {
        $protocol = ($encryption === 'ssl') ? 'ssl' : (($encryption === 'tls') ? 'tls' : '');
        $connection_string = '{' . $host . ':' . $port . '/imap';
        
        if ($protocol) {
            $connection_string .= '/' . $protocol;
        }
        
        $connection_string .= '}INBOX';

        try {
            $mailbox = @imap_open($connection_string, $user, $pass, OP_READONLY);
            
            if ($mailbox) {
                imap_close($mailbox);
                return ['success' => true, 'message' => 'Połączenie IMAP udane'];
            } else {
                $error = imap_last_error();
                return ['success' => false, 'message' => 'Błąd IMAP: ' . ($error ?: 'Nieznany błąd')];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Błąd IMAP: ' . $e->getMessage()];
        }
    }

    /**
     * Szyfruje hasło przed zapisem do bazy danych.
     * Używa bezpiecznych funkcji WordPress z kluczami z wp-config.php
     */
    private function encrypt_password($password) {
        if (empty($password)) {
            return '';
        }
        
        // Sprawdzamy czy istnieją klucze WordPress
        if (!defined('AUTH_KEY') || !defined('SECURE_AUTH_KEY')) {
            // Fallback - zwykłe base64 (niebezpieczne!)
            error_log('WPMZF Warning: Brak kluczy WordPress - używam niebezpiecznego szyfrowania!');
            return base64_encode($password);
        }
        
        // Bezpieczne szyfrowanie AES-256-CBC z kluczami WordPress
        $key = substr(hash('sha256', AUTH_KEY), 0, 32);
        $iv = substr(hash('sha256', SECURE_AUTH_KEY), 0, 16);
        
        return base64_encode(openssl_encrypt($password, 'aes-256-cbc', $key, 0, $iv));
    }

    /**
     * Odszyfrowuje hasło pobrane z bazy danych.
     */
    private function decrypt_password($encrypted_password) {
        if (empty($encrypted_password)) {
            return '';
        }
        
        // Sprawdzamy czy istnieją klucze WordPress
        if (!defined('AUTH_KEY') || !defined('SECURE_AUTH_KEY')) {
            // Fallback - zwykłe base64 (niebezpieczne!)
            return base64_decode($encrypted_password);
        }
        
        // Bezpieczne odszyfrowanie AES-256-CBC z kluczami WordPress
        $key = substr(hash('sha256', AUTH_KEY), 0, 32);
        $iv = substr(hash('sha256', SECURE_AUTH_KEY), 0, 16);
        
        return openssl_decrypt(base64_decode($encrypted_password), 'aes-256-cbc', $key, 0, $iv);
    }

    /**
     * Dodaje style CSS dla formularza
     */
    public function add_profile_styles() {
        ?>
        <style>
        .wpmzf-email-settings-container {
            max-width: 800px;
            margin: 20px 0;
        }
        .wpmzf-email-section {
            background: #fff;
            border: 1px solid #ddd;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .wpmzf-email-section h3 {
            margin-top: 0;
            color: #23282d;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        .wpmzf-test-result {
            margin-top: 10px;
            padding: 10px;
            border-radius: 3px;
            display: none;
        }
        .wpmzf-test-result.success {
            display: block;
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .wpmzf-test-result.error {
            display: block;
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .wpmzf-email-help {
            background: #f0f6fc;
            border: 1px solid #c6e2ff;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
        }
        .wpmzf-provider-examples div {
            margin: 5px 0;
            font-family: monospace;
        }
        </style>
        <?php
    }
}

// Inicjalizacja
new WPMZF_User_Email_Settings();
