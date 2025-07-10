<?php

/**
 * Przykłady użycia nowego systemu kontaktów
 */

// Przykład 1: Dodawanie wielu e-maili do osoby
function example_add_person_emails($person_id) {
    $emails = [
        [
            'email_address' => 'jan.kowalski@firma.pl',
            'email_type' => 'służbowy',
            'is_primary' => true
        ],
        [
            'email_address' => 'j.kowalski@gmail.com', 
            'email_type' => 'prywatny',
            'is_primary' => false
        ],
        [
            'email_address' => 'jkowalski@outlook.com',
            'email_type' => 'zapasowy',
            'is_primary' => false
        ]
    ];
    
    update_field('person_emails', $emails, $person_id);
}

// Przykład 2: Dodawanie wielu telefonów do osoby
function example_add_person_phones($person_id) {
    $phones = [
        [
            'phone_number' => '+48 123 456 789',
            'phone_type' => 'służbowy',
            'is_primary' => true
        ],
        [
            'phone_number' => '+48 987 654 321',
            'phone_type' => 'prywatny',
            'is_primary' => false
        ],
        [
            'phone_number' => '+48 555 123 456',
            'phone_type' => 'alarmowy',
            'is_primary' => false
        ]
    ];
    
    update_field('person_phones', $phones, $person_id);
}

// Przykład 3: Dodawanie kontaktów do firmy
function example_add_company_contacts($company_id) {
    $emails = [
        [
            'email_address' => 'info@firma.pl',
            'email_type' => 'główny',
            'is_primary' => true
        ],
        [
            'email_address' => 'marketing@firma.pl',
            'email_type' => 'marketing',
            'is_primary' => false
        ],
        [
            'email_address' => 'wsparcie@firma.pl',
            'email_type' => 'wsparcie techniczne',
            'is_primary' => false
        ],
        [
            'email_address' => 'fakturowanie@firma.pl',
            'email_type' => 'fakturowanie',
            'is_primary' => false
        ]
    ];
    
    $phones = [
        [
            'phone_number' => '+48 22 123 45 67',
            'phone_type' => 'centrala',
            'is_primary' => true
        ],
        [
            'phone_number' => '+48 22 123 45 99',
            'phone_type' => 'wsparcie techniczne',
            'is_primary' => false
        ],
        [
            'phone_number' => '+48 22 123 45 88',
            'phone_type' => 'dział sprzedaży',
            'is_primary' => false
        ]
    ];
    
    update_field('company_emails', $emails, $company_id);
    update_field('company_phones', $phones, $company_id);
}

// Przykład 4: Pobieranie i wyświetlanie kontaktów
function example_display_person_contacts($person_id) {
    echo "<h3>Kontakty osoby ID: $person_id</h3>";
    
    // E-maile
    $emails = WPMZF_Contact_Helper::get_person_emails($person_id);
    echo "<h4>E-maile:</h4>";
    echo WPMZF_Contact_Helper::render_emails_display($emails);
    
    // Telefony
    $phones = WPMZF_Contact_Helper::get_person_phones($person_id);
    echo "<h4>Telefony:</h4>";
    echo WPMZF_Contact_Helper::render_phones_display($phones);
    
    // Główne kontakty
    $primary_email = WPMZF_Contact_Helper::get_primary_person_email($person_id);
    $primary_phone = WPMZF_Contact_Helper::get_primary_person_phone($person_id);
    
    echo "<h4>Kontakty główne:</h4>";
    echo "<p>E-mail: " . ($primary_email ?? 'Brak') . "</p>";
    echo "<p>Telefon: " . ($primary_phone ?? 'Brak') . "</p>";
}

// Przykład 5: Prosta walidacja przed zapisem
function example_validate_contacts_before_save($person_id, $emails, $phones) {
    // Sprawdź czy e-maile są unikalne
    $email_addresses = array_column($emails, 'email_address');
    if (count($email_addresses) !== count(array_unique($email_addresses))) {
        return new WP_Error('duplicate_email', 'Znaleziono duplikaty adresów e-mail');
    }
    
    // Sprawdź czy telefony są unikalne
    $phone_numbers = array_column($phones, 'phone_number');
    if (count($phone_numbers) !== count(array_unique($phone_numbers))) {
        return new WP_Error('duplicate_phone', 'Znaleziono duplikaty numerów telefonów');
    }
    
    // Walidacja głównych kontaktów (już obsługiwana przez ACF)
    $emails = WPMZF_Contact_Helper::validate_single_primary($emails);
    $phones = WPMZF_Contact_Helper::validate_single_primary($phones);
    
    // Zapisz dane
    update_field('person_emails', $emails, $person_id);
    update_field('person_phones', $phones, $person_id);
    
    return true;
}

// Przykład 6: Wyszukiwanie osób po kontaktach
function example_find_person_by_contact($contact_value, $contact_type = 'email') {
    $field_name = $contact_type === 'email' ? 'person_emails' : 'person_phones';
    $sub_field = $contact_type === 'email' ? 'email_address' : 'phone_number';
    
    $persons = get_posts([
        'post_type' => 'person',
        'numberposts' => -1,
        'meta_query' => [
            [
                'key' => $field_name . '_' . $sub_field,
                'value' => $contact_value,
                'compare' => 'LIKE'
            ]
        ]
    ]);
    
    return $persons;
}

/*
Przykłady wywołań:

// Dodaj kontakty do osoby o ID 123
example_add_person_emails(123);
example_add_person_phones(123);

// Dodaj kontakty do firmy o ID 456
example_add_company_contacts(456);

// Wyświetl kontakty osoby
example_display_person_contacts(123);

// Znajdź osobę po e-mailu
$persons = example_find_person_by_contact('jan.kowalski@firma.pl', 'email');

// Znajdź osobę po telefonie
$persons = example_find_person_by_contact('+48 123 456 789', 'phone');
*/
