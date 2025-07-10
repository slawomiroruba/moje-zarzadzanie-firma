<?php

/**
 * Klasa pomocnicza do obsługi wielu adresów e-mail i telefonów
 */
class WPMZF_Contact_Helper
{
    
    /**
     * Pobiera wszystkie adresy e-mail dla osoby z oznaczeniem głównego
     * 
     * @param int $post_id ID postu osoby
     * @return array
     */
    public static function get_person_emails($post_id)
    {
        $emails = [];
        
        // Pobierz emaile z repeatera
        $person_emails = get_field('person_emails', $post_id);
        if (!empty($person_emails) && is_array($person_emails)) {
            foreach ($person_emails as $email) {
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
     * Pobiera wszystkie numery telefonów dla osoby z oznaczeniem głównego
     * 
     * @param int $post_id ID postu osoby
     * @return array
     */
    public static function get_person_phones($post_id)
    {
        $phones = [];
        
        // Pobierz telefony z repeatera
        $person_phones = get_field('person_phones', $post_id);
        if (!empty($person_phones) && is_array($person_phones)) {
            foreach ($person_phones as $phone) {
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
}
