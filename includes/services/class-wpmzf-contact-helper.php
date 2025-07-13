<?php

/**
 * Klasa pomocnicza do obsługi wielu adresów e-mail i telefonów
 */
class WPMZF_Contact_Helper
{
    /**
     * Cache manager
     */
    private static $cache_manager;

    /**
     * Performance monitor
     */
    private static $performance_monitor;

    /**
     * Inicjalizacja serwisów
     */
    private static function init() {
        if (!self::$cache_manager) {
            self::$cache_manager = new WPMZF_Cache_Manager();
        }
        if (!self::$performance_monitor) {
            self::$performance_monitor = new WPMZF_Performance_Monitor();
        }
    }
    
    /**
     * Pobiera wszystkie adresy e-mail dla osoby z oznaczeniem głównego
     * 
     * @param int $post_id ID postu osoby
     * @return array
     */
    public static function get_person_emails($post_id)
    {
        self::init();
        $timer_id = self::$performance_monitor->start_timer('contact_helper_get_person_emails');
        
        try {
            // Walidacja parametru
            $post_id = intval($post_id);
            if ($post_id <= 0) {
                throw new InvalidArgumentException('Invalid post ID');
            }

            // Sprawdź cache
            $cache_key = "person_emails_{$post_id}";
            $cached_result = self::$cache_manager->get($cache_key);
            if ($cached_result !== false) {
                self::$performance_monitor->end_timer($timer_id);
                return $cached_result;
            }

            $emails = [];
            
            // Pobierz emaile z repeatera
            $person_emails = get_field('person_emails', $post_id);
            if (!empty($person_emails) && is_array($person_emails)) {
                foreach ($person_emails as $email) {
                    if (!empty($email['email_address']) && is_email($email['email_address'])) {
                        $emails[] = [
                            'email_address' => sanitize_email($email['email_address']),
                            'email_type' => sanitize_text_field($email['email_type'] ?? ''),
                            'is_primary' => (bool)($email['is_primary'] ?? false),
                        ];
                    }
                }
            }

            // Cache wynik na 10 minut
            self::$cache_manager->set($cache_key, $emails, 600);
            
            self::$performance_monitor->end_timer($timer_id);
            
            return $emails;
            
        } catch (Exception $e) {
            self::$performance_monitor->end_timer($timer_id);
            WPMZF_Logger::error('Error in get_person_emails', ['error' => $e->getMessage(), 'post_id' => $post_id]);
            return [];
        }
    }
    
    /**
     * Pobiera wszystkie numery telefonów dla osoby z oznaczeniem głównego
     * 
     * @param int $post_id ID postu osoby
     * @return array
     */
    public static function get_person_phones($post_id)
    {
        self::init();
        $timer_id = self::$performance_monitor->start_timer('contact_helper_get_person_phones');
        
        try {
            // Walidacja parametru
            $post_id = intval($post_id);
            if ($post_id <= 0) {
                throw new InvalidArgumentException('Invalid post ID');
            }

            // Sprawdź cache
            $cache_key = "person_phones_{$post_id}";
            $cached_result = self::$cache_manager->get($cache_key);
            if ($cached_result !== false) {
                self::$performance_monitor->end_timer($timer_id);
                return $cached_result;
            }

            $phones = [];
            
            // Pobierz telefony z repeatera
            $person_phones = get_field('person_phones', $post_id);
            if (!empty($person_phones) && is_array($person_phones)) {
                foreach ($person_phones as $phone) {
                    if (!empty($phone['phone_number'])) {
                        // Podstawowa walidacja numeru telefonu
                        $phone_number = preg_replace('/[^\d\+\-\(\)\s]/', '', $phone['phone_number']);
                        if (strlen($phone_number) >= 7) { // Minimum 7 cyfr dla numeru telefonu
                            $phones[] = [
                                'phone_number' => sanitize_text_field($phone_number),
                                'phone_type' => sanitize_text_field($phone['phone_type'] ?? ''),
                                'is_primary' => (bool)($phone['is_primary'] ?? false),
                            ];
                        }
                    }
                }
            }

            // Cache wynik na 10 minut
            self::$cache_manager->set($cache_key, $phones, 600);
            
            self::$performance_monitor->end_timer($timer_id);
            
            return $phones;
            
        } catch (Exception $e) {
            self::$performance_monitor->end_timer($timer_id);
            WPMZF_Logger::error('Error in get_person_phones', ['error' => $e->getMessage(), 'post_id' => $post_id]);
            return [];
        }
    }
    
    /**
     * Pobiera wszystkie adresy e-mail dla firmy z oznaczeniem głównego
     * 
     * @param int $post_id ID postu firmy
     * @return array
     */
    public static function get_company_emails($post_id)
    {
        $emails = [];
        
        // Pobierz emaile z repeatera
        $company_emails = get_field('company_emails', $post_id);
        if (!empty($company_emails) && is_array($company_emails)) {
            foreach ($company_emails as $email) {
                if (!empty($email['email_address'])) {
                    $emails[] = [
                        'email_address' => $email['email_address'],
                        'email_type' => $email['email_type'] ?? '',
                        'is_primary' => (bool)($email['is_primary'] ?? false),
                    ];
                }
            }
        }
        
        return $emails;
    }
    
    /**
     * Pobiera wszystkie numery telefonów dla firmy z oznaczeniem głównego
     * 
     * @param int $post_id ID postu firmy
     * @return array
     */
    public static function get_company_phones($post_id)
    {
        $phones = [];
        
        // Pobierz telefony z repeatera
        $company_phones = get_field('company_phones', $post_id);
        if (!empty($company_phones) && is_array($company_phones)) {
            foreach ($company_phones as $phone) {
                if (!empty($phone['phone_number'])) {
                    $phones[] = [
                        'phone_number' => $phone['phone_number'],
                        'phone_type' => $phone['phone_type'] ?? '',
                        'is_primary' => (bool)($phone['is_primary'] ?? false),
                    ];
                }
            }
        }
        
        return $phones;
    }
    
    /**
     * Zwraca główny adres e-mail dla osoby
     * 
     * @param int $post_id ID postu osoby
     * @return string|null
     */
    public static function get_primary_person_email($post_id)
    {
        $emails = self::get_person_emails($post_id);
        
        // Znajdź pierwszy oznaczony jako główny
        foreach ($emails as $email) {
            if ($email['is_primary']) {
                return $email['email_address'];
            }
        }
        
        // Jeśli nie ma głównego, zwróć pierwszy dostępny
        if (!empty($emails)) {
            return $emails[0]['email_address'];
        }
        
        return null;
    }
    
    /**
     * Zwraca główny numer telefonu dla osoby
     * 
     * @param int $post_id ID postu osoby
     * @return string|null
     */
    public static function get_primary_person_phone($post_id)
    {
        $phones = self::get_person_phones($post_id);
        
        // Znajdź pierwszy oznaczony jako główny
        foreach ($phones as $phone) {
            if ($phone['is_primary']) {
                return $phone['phone_number'];
            }
        }
        
        // Jeśli nie ma głównego, zwróć pierwszy dostępny
        if (!empty($phones)) {
            return $phones[0]['phone_number'];
        }
        
        return null;
    }
    
    /**
     * Zwraca główny adres e-mail dla firmy
     * 
     * @param int $post_id ID postu firmy
     * @return string|null
     */
    public static function get_primary_company_email($post_id)
    {
        $emails = self::get_company_emails($post_id);
        
        // Znajdź pierwszy oznaczony jako główny
        foreach ($emails as $email) {
            if ($email['is_primary']) {
                return $email['email_address'];
            }
        }
        
        // Jeśli nie ma głównego, zwróć pierwszy dostępny
        if (!empty($emails)) {
            return $emails[0]['email_address'];
        }
        
        return null;
    }
    
    /**
     * Zwraca główny numer telefonu dla firmy
     * 
     * @param int $post_id ID postu firmy
     * @return string|null
     */
    public static function get_primary_company_phone($post_id)
    {
        $phones = self::get_company_phones($post_id);
        
        // Znajdź pierwszy oznaczony jako główny
        foreach ($phones as $phone) {
            if ($phone['is_primary']) {
                return $phone['phone_number'];
            }
        }
        
        // Jeśli nie ma głównego, zwróć pierwszy dostępny
        if (!empty($phones)) {
            return $phones[0]['phone_number'];
        }
        
        return null;
    }
    
    /**
     * Renderuje HTML dla wyświetlania adresów e-mail
     * 
     * @param array $emails Tablica adresów e-mail
     * @return string
     */
    public static function render_emails_display($emails)
    {
        if (empty($emails)) {
            return '<span class="no-contacts">Brak adresów e-mail</span>';
        }
        
        $html = '<div class="contacts-list emails-list">';
        
        foreach ($emails as $email) {
            $is_primary_class = $email['is_primary'] ? ' is-primary' : '';
            $primary_badge = $email['is_primary'] ? ' <span class="primary-badge">główny</span>' : '';
            $type_display = !empty($email['email_type']) ? ' (' . esc_html($email['email_type']) . ')' : '';
            
            $html .= sprintf(
                '<div class="contact-item email-item%s">
                    <a href="mailto:%s" class="contact-link">%s</a>%s%s
                </div>',
                $is_primary_class,
                esc_attr($email['email_address']),
                esc_html($email['email_address']),
                esc_html($type_display),
                $primary_badge
            );
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Renderuje HTML dla wyświetlania numerów telefonów
     * 
     * @param array $phones Tablica numerów telefonów
     * @return string
     */
    public static function render_phones_display($phones)
    {
        if (empty($phones)) {
            return '<span class="no-contacts">Brak numerów telefonów</span>';
        }
        
        $html = '<div class="contacts-list phones-list">';
        
        foreach ($phones as $phone) {
            $is_primary_class = $phone['is_primary'] ? ' is-primary' : '';
            $primary_badge = $phone['is_primary'] ? ' <span class="primary-badge">główny</span>' : '';
            $type_display = !empty($phone['phone_type']) ? ' (' . esc_html($phone['phone_type']) . ')' : '';
            
            $html .= sprintf(
                '<div class="contact-item phone-item%s">
                    <a href="tel:%s" class="contact-link">%s</a>%s%s
                </div>',
                $is_primary_class,
                esc_attr($phone['phone_number']),
                esc_html($phone['phone_number']),
                esc_html($type_display),
                $primary_badge
            );
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Waliduje czy tylko jeden kontakt jest oznaczony jako główny
     * 
     * @param array $contacts Tablica kontaktów z kluczem 'is_primary'
     * @return array Poprawiona tablica z tylko jednym głównym kontaktem
     */
    public static function validate_single_primary($contacts)
    {
        if (empty($contacts) || !is_array($contacts)) {
            return $contacts;
        }
        
        $primary_found = false;
        
        foreach ($contacts as $index => $contact) {
            if (!empty($contact['is_primary']) && !$primary_found) {
                $primary_found = true;
                $contacts[$index]['is_primary'] = true;
            } else {
                $contacts[$index]['is_primary'] = false;
            }
        }
        
        return $contacts;
    }

    /**
     * Wyczyść cache dla kontaktów osoby
     * 
     * @param int $post_id ID postu
     */
    public static function clear_person_cache($post_id) {
        self::init();
        self::$cache_manager->delete("person_emails_{$post_id}");
        self::$cache_manager->delete("person_phones_{$post_id}");
        self::$cache_manager->delete("person_primary_email_{$post_id}");
        self::$cache_manager->delete("person_primary_phone_{$post_id}");
    }

    /**
     * Wyczyść cache dla kontaktów firmy
     * 
     * @param int $post_id ID postu
     */
    public static function clear_company_cache($post_id) {
        self::init();
        self::$cache_manager->delete("company_emails_{$post_id}");
        self::$cache_manager->delete("company_phones_{$post_id}");
        self::$cache_manager->delete("company_primary_email_{$post_id}");
        self::$cache_manager->delete("company_primary_phone_{$post_id}");
    }

    /**
     * Waliduje format adresu email
     * 
     * @param string $email Email do walidacji
     * @return bool
     */
    public static function validate_email($email) {
        return !empty($email) && is_email($email);
    }

    /**
     * Waliduje format numeru telefonu
     * 
     * @param string $phone Numer telefonu do walidacji
     * @return bool
     */
    public static function validate_phone($phone) {
        if (empty($phone)) {
            return false;
        }
        
        // Usuń wszystkie znaki oprócz cyfr, +, -, (, ), spacje
        $cleaned = preg_replace('/[^\d\+\-\(\)\s]/', '', $phone);
        
        // Sprawdź czy ma co najmniej 7 cyfr
        $digits_only = preg_replace('/\D/', '', $cleaned);
        
        return strlen($digits_only) >= 7 && strlen($digits_only) <= 15;
    }

    /**
     * Formatuje numer telefonu do standardowego formatu
     * 
     * @param string $phone Numer telefonu
     * @return string Sformatowany numer
     */
    public static function format_phone($phone) {
        if (!self::validate_phone($phone)) {
            return $phone;
        }
        
        // Usuń wszystkie znaki oprócz cyfr i +
        $cleaned = preg_replace('/[^\d\+]/', '', $phone);
        
        // Jeśli zaczyna się od 0, zamień na +48
        if (substr($cleaned, 0, 1) === '0') {
            $cleaned = '+48' . substr($cleaned, 1);
        }
        
        return $cleaned;
    }

    /**
     * Pobiera statystyki kontaktów dla danej osoby/firmy
     * 
     * @param int $post_id ID postu
     * @param string $type 'person' lub 'company'
     * @return array
     */
    public static function get_contact_stats($post_id, $type = 'person') {
        self::init();
        $timer_id = self::$performance_monitor->start_timer('contact_helper_get_stats');
        
        try {
            $cache_key = "{$type}_contact_stats_{$post_id}";
            $cached_result = self::$cache_manager->get($cache_key);
            if ($cached_result !== false) {
                self::$performance_monitor->end_timer($timer_id);
                return $cached_result;
            }

            $emails = $type === 'person' ? self::get_person_emails($post_id) : self::get_company_emails($post_id);
            $phones = $type === 'person' ? self::get_person_phones($post_id) : self::get_company_phones($post_id);
            
            $stats = [
                'total_emails' => count($emails),
                'total_phones' => count($phones),
                'has_primary_email' => false,
                'has_primary_phone' => false,
                'email_types' => [],
                'phone_types' => []
            ];
            
            foreach ($emails as $email) {
                if ($email['is_primary']) {
                    $stats['has_primary_email'] = true;
                }
                if (!empty($email['email_type']) && !in_array($email['email_type'], $stats['email_types'])) {
                    $stats['email_types'][] = $email['email_type'];
                }
            }
            
            foreach ($phones as $phone) {
                if ($phone['is_primary']) {
                    $stats['has_primary_phone'] = true;
                }
                if (!empty($phone['phone_type']) && !in_array($phone['phone_type'], $stats['phone_types'])) {
                    $stats['phone_types'][] = $phone['phone_type'];
                }
            }

            // Cache na 15 minut
            self::$cache_manager->set($cache_key, $stats, 900);
            
            self::$performance_monitor->end_timer($timer_id);
            
            return $stats;
            
        } catch (Exception $e) {
            self::$performance_monitor->end_timer($timer_id);
            WPMZF_Logger::error('Error in get_contact_stats', ['error' => $e->getMessage(), 'post_id' => $post_id, 'type' => $type]);
            return [
                'total_emails' => 0,
                'total_phones' => 0,
                'has_primary_email' => false,
                'has_primary_phone' => false,
                'email_types' => [],
                'phone_types' => []
            ];
        }
    }
}
