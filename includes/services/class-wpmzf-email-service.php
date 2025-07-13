<?php

/**
 * Serwis do obsługi wysyłania i odbierania e-maili
 *
 * @package WPMZF
 * @subpackage Services
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPMZF_Email_Service {

    /**
     * Konstruktor
     */
    public function __construct() {
        // Dodaj hooki dla cron jobs - używamy zgodnego hooka z cron manager
        add_action('wpmzf_process_email_queue_hook', [$this, 'process_email_queue']);
        add_action('wpmzf_fetch_incoming_emails', [$this, 'fetch_incoming_emails']);
    }

    /**
     * Dodaje e-mail do kolejki do wysłania
     */
    public function queue_email($user_id, $to, $subject, $body, $cc = '', $bcc = '', $options = []) {
        global $wpdb;

        // Walidacja danych
        if (!$user_id || !$to || !$subject || !$body) {
            return new WP_Error('missing_data', 'Brak wymaganych danych do wysłania e-maila');
        }

        // Sprawdź czy użytkownik ma skonfigurowane SMTP
        $smtp_settings = get_user_meta($user_id, 'wpmzf_smtp_settings', true);
        if (empty($smtp_settings['host']) || empty($smtp_settings['user'])) {
            return new WP_Error('no_smtp_config', 'Użytkownik nie ma skonfigurowanych ustawień SMTP');
        }

        $table = $wpdb->prefix . 'wpmzf_email_queue';
        
        // Generuj Message-ID
        $message_id = $this->generate_message_id($smtp_settings['user']);
        
        // Sprawdź czy to odpowiedź na e-mail
        $in_reply_to = $options['in_reply_to'] ?? null;
        $thread_id = $options['thread_id'] ?? null;
        
        // Jeśli nie ma thread_id, ale jest in_reply_to, znajdź thread
        if (!$thread_id && $in_reply_to) {
            $thread_id = $this->find_thread_by_message_id($in_reply_to);
        }
        
        // Jeśli nadal nie ma thread_id, stwórz nowy
        if (!$thread_id) {
            $thread_id = $this->generate_thread_id($subject, $to);
        }

        $data = [
            'user_id' => $user_id,
            'status' => 'pending',
            'priority' => $options['priority'] ?? 5,
            'recipient_to' => $to,
            'recipient_cc' => $cc,
            'recipient_bcc' => $bcc,
            'subject' => $subject,
            'body' => $body,
            'message_id' => $message_id,
            'in_reply_to' => $in_reply_to,
            'thread_id' => $thread_id,
            'related_activity_id' => $options['activity_id'] ?? null,
            'scheduled_at' => $options['scheduled_at'] ?? current_time('mysql'),
            'created_at' => current_time('mysql')
        ];

        $result = $wpdb->insert($table, $data);
        
        if ($result === false) {
            return new WP_Error('db_error', 'Nie udało się dodać e-maila do kolejki');
        }

        $email_id = $wpdb->insert_id;

        // Zadanie cron automatycznie przetworzy kolejkę co 5 minut
        // Nie potrzebujemy już wp_schedule_single_event

        return $email_id;
    }

    /**
     * Przetwarza kolejkę e-maili (wywoływane przez cron)
     */
    public function process_email_queue($specific_email_id = null) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'wpmzf_email_queue';
        
        // Jeśli podano konkretny ID, przetwórz tylko ten e-mail
        if ($specific_email_id) {
            $emails = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table WHERE id = %d AND status = 'pending'",
                $specific_email_id
            ));
        } else {
            // Pobierz e-maile do wysłania (max 10 na raz)
            $emails = $wpdb->get_results(
                "SELECT * FROM $table 
                 WHERE status = 'pending' 
                 AND scheduled_at <= NOW() 
                 AND attempts < max_attempts 
                 ORDER BY priority ASC, created_at ASC 
                 LIMIT 10"
            );
        }

        foreach ($emails as $email) {
            $this->send_single_email($email);
        }
    }

    /**
     * Wysyła pojedynczy e-mail
     */
    private function send_single_email($email_data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'wpmzf_email_queue';
        
        // Pobierz ustawienia SMTP użytkownika
        $smtp_settings = get_user_meta($email_data->user_id, 'wpmzf_smtp_settings', true);
        
        if (empty($smtp_settings)) {
            $this->mark_email_failed($email_data->id, 'Brak ustawień SMTP');
            return false;
        }

        require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
        require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
        require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';

        $mail = new PHPMailer\PHPMailer\PHPMailer(true);

        try {
            // Konfiguracja SMTP
            $mail->isSMTP();
            $mail->Host = $smtp_settings['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $smtp_settings['user'];
            $mail->Password = $this->decrypt_password($smtp_settings['pass']);
            $mail->Port = $smtp_settings['port'];
            $mail->CharSet = 'UTF-8';

            // Szyfrowanie
            if ($smtp_settings['encryption'] === 'ssl') {
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($smtp_settings['encryption'] === 'tls') {
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            }

            // Nadawca
            $user_name = get_user_meta($email_data->user_id, 'first_name', true) . ' ' . 
                        get_user_meta($email_data->user_id, 'last_name', true);
            if (trim($user_name) === '') {
                $user_info = get_userdata($email_data->user_id);
                $user_name = $user_info->display_name;
            }
            
            $mail->setFrom($smtp_settings['user'], $user_name);

            // Odbiorcy
            $recipients = explode(',', $email_data->recipient_to);
            foreach ($recipients as $recipient) {
                $mail->addAddress(trim($recipient));
            }

            if (!empty($email_data->recipient_cc)) {
                $cc_recipients = explode(',', $email_data->recipient_cc);
                foreach ($cc_recipients as $cc) {
                    $mail->addCC(trim($cc));
                }
            }

            if (!empty($email_data->recipient_bcc)) {
                $bcc_recipients = explode(',', $email_data->recipient_bcc);
                foreach ($bcc_recipients as $bcc) {
                    $mail->addBCC(trim($bcc));
                }
            }

            // Nagłówki wątkowania
            $mail->MessageID = $email_data->message_id;
            if (!empty($email_data->in_reply_to)) {
                $mail->addCustomHeader('In-Reply-To', $email_data->in_reply_to);
                $mail->addCustomHeader('References', $email_data->in_reply_to);
            }

            // Treść
            $mail->isHTML(true);
            $mail->Subject = $email_data->subject;
            $mail->Body = $email_data->body;
            $mail->AltBody = strip_tags($email_data->body);

            // Wyślij
            $result = $mail->send();

            if ($result) {
                // Oznacz jako wysłane (tylko jeśli to nie test)
                if ($email_data->id !== 'test') {
                    $wpdb->update(
                        $table,
                        [
                            'status' => 'sent',
                            'sent_at' => current_time('mysql'),
                            'updated_at' => current_time('mysql')
                        ],
                        ['id' => $email_data->id]
                    );

                    // Zaktualizuj aktywność jeśli jest powiązana
                    if ($email_data->related_activity_id) {
                        $this->update_activity_email_status($email_data->related_activity_id, 'sent', $email_data->message_id);
                    }

                    // Zaktualizuj wątek
                    $this->update_email_thread($email_data->thread_id, $email_data->message_id, $email_data->recipient_to);
                }

                WPMZF_Logger::info('Email sent successfully', [
                    'email_id' => $email_data->id,
                    'to' => $email_data->recipient_to,
                    'subject' => $email_data->subject
                ]);

                return true;
            }

        } catch (Exception $e) {
            // Oznacz jako nieudane (tylko jeśli to nie test)
            if ($email_data->id !== 'test') {
                $this->mark_email_failed($email_data->id, $e->getMessage());
            }
            
            WPMZF_Logger::error('Email sending failed', [
                'email_id' => $email_data->id,
                'error' => $e->getMessage()
            ]);
            
            return new WP_Error('email_send_failed', $e->getMessage());
        }
    }

    /**
     * Oznacza e-mail jako nieudany
     */
    private function mark_email_failed($email_id, $error_message) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'wpmzf_email_queue';
        
        // Zwiększ liczbę prób
        $wpdb->query($wpdb->prepare(
            "UPDATE $table SET attempts = attempts + 1, error_message = %s, updated_at = NOW() WHERE id = %d",
            $error_message,
            $email_id
        ));

        // Sprawdź czy przekroczono limit prób
        $email = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $email_id));
        
        if ($email && $email->attempts >= $email->max_attempts) {
            $wpdb->update(
                $table,
                ['status' => 'failed'],
                ['id' => $email_id]
            );

            // Zaktualizuj aktywność
            if ($email->related_activity_id) {
                $this->update_activity_email_status($email->related_activity_id, 'failed', null, $error_message);
            }
        }
    }

    /**
     * Aktualizuje status e-maila w aktywności
     */
    private function update_activity_email_status($activity_id, $status, $message_id = null, $error = null) {
        $email_meta = get_post_meta($activity_id, 'email_data', true) ?: [];
        
        $email_meta['status'] = $status;
        $email_meta['updated_at'] = current_time('mysql');
        
        if ($message_id) {
            $email_meta['message_id'] = $message_id;
        }
        
        if ($error) {
            $email_meta['error'] = $error;
        }

        update_post_meta($activity_id, 'email_data', $email_meta);
    }

    /**
     * Generuje unikalny Message-ID
     */
    private function generate_message_id($email_address) {
        $domain = substr(strrchr($email_address, "@"), 1);
        return '<' . uniqid() . '.' . time() . '@' . $domain . '>';
    }

    /**
     * Generuje ID wątku
     */
    private function generate_thread_id($subject, $participants) {
        $clean_subject = preg_replace('/^(RE:|FW:|FWD:)\s*/i', '', $subject);
        return md5($clean_subject . $participants . date('Y-m-d'));
    }

    /**
     * Znajduje wątek na podstawie Message-ID
     */
    private function find_thread_by_message_id($message_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'wpmzf_email_queue';
        
        $thread_id = $wpdb->get_var($wpdb->prepare(
            "SELECT thread_id FROM $table WHERE message_id = %s LIMIT 1",
            $message_id
        ));

        return $thread_id;
    }

    /**
     * Aktualizuje wątek e-maili
     */
    private function update_email_thread($thread_id, $message_id, $participants) {
        global $wpdb;
        
        $table_threads = $wpdb->prefix . 'wpmzf_email_threads';
        
        // Sprawdź czy wątek istnieje
        $thread = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_threads WHERE thread_id = %s",
            $thread_id
        ));

        if ($thread) {
            // Aktualizuj istniejący wątek
            $wpdb->update(
                $table_threads,
                [
                    'last_message_id' => $message_id,
                    'message_count' => $thread->message_count + 1,
                    'updated_at' => current_time('mysql')
                ],
                ['thread_id' => $thread_id]
            );
        } else {
            // Stwórz nowy wątek
            $wpdb->insert(
                $table_threads,
                [
                    'thread_id' => $thread_id,
                    'subject' => '', // Zostanie zaktualizowane później
                    'participants' => $participants,
                    'entity_type' => 'unknown', // Zostanie zaktualizowane
                    'entity_id' => 0,
                    'first_message_id' => $message_id,
                    'last_message_id' => $message_id,
                    'message_count' => 1,
                    'created_at' => current_time('mysql')
                ]
            );
        }
    }

    /**
     * Pobiera ustawienia użytkownika z odszyfrowanym hasłem
     */
    public function get_user_smtp_settings($user_id) {
        $settings = get_user_meta($user_id, 'wpmzf_smtp_settings', true);
        
        if (!empty($settings['pass'])) {
            $settings['pass'] = $this->decrypt_password($settings['pass']);
        }
        
        return $settings;
    }

    /**
     * Odszyfruje hasło
     */
    private function decrypt_password($encrypted) {
        if (empty($encrypted)) {
            return '';
        }
        
        // Sprawdzamy czy istnieją klucze WordPress
        if (!defined('AUTH_KEY') || !defined('SECURE_AUTH_KEY')) {
            // Fallback - zwykłe base64 (niebezpieczne!)
            return base64_decode($encrypted);
        }
        
        // Bezpieczne odszyfrowanie AES-256-CBC z kluczami WordPress
        $key = substr(hash('sha256', AUTH_KEY), 0, 32);
        $iv = substr(hash('sha256', SECURE_AUTH_KEY), 0, 16);
        
        return openssl_decrypt(base64_decode($encrypted), 'aes-256-cbc', $key, 0, $iv);
    }

    /**
     * Pobiera statystyki kolejki e-maili
     */
    public function get_queue_stats() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'wpmzf_email_queue';
        
        $stats = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
             FROM $table"
        );

        return [
            'total' => intval($stats->total),
            'pending' => intval($stats->pending),
            'sent' => intval($stats->sent),
            'failed' => intval($stats->failed)
        ];
    }

    /**
     * Metoda testowa do bezpośredniego wysłania e-maila (omija kolejkę)
     */
    public function send_test_email($user_id, $to, $subject, $body) {
        // Pobierz ustawienia SMTP użytkownika
        $smtp_settings = get_user_meta($user_id, 'wpmzf_smtp_settings', true);
        
        if (empty($smtp_settings)) {
            return new WP_Error('no_smtp_config', 'Brak ustawień SMTP');
        }

        // Utwórz obiekt e-maila do testowania
        $test_email = (object) [
            'id' => 'test',
            'user_id' => $user_id,
            'recipient_to' => $to,
            'recipient_cc' => '',
            'recipient_bcc' => '',
            'subject' => $subject,
            'body' => $body,
            'message_id' => $this->generate_message_id($smtp_settings['user']),
            'in_reply_to' => null,
            'thread_id' => 'test-thread',
            'related_activity_id' => null
        ];

        return $this->send_single_email($test_email);
    }
}
