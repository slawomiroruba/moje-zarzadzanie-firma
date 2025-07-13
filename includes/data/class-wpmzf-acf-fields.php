<?php

/**
 * Plik odpowiedzialny za rejestrację wszystkich grup pól ACF w kodzie PHP.
 */
class WPMZF_ACF_Fields
{

    public function __construct()
    {
        // Używamy hooka 'acf/include_fields', który jest dedykowany do rejestracji pól w kodzie.
        add_action('acf/include_fields', array($this, 'register_all_field_groups'));
        add_filter('acf/fields/relationship/query/key=field_wpmzf_person_company_relation', array($this, 'extend_company_relationship_search'), 10, 3);
        
        // Dodajemy filtry dla pól user aby zapewnić ich poprawne działanie
        add_filter('acf/fields/user/query', array($this, 'acf_user_query'), 10, 3);
        add_filter('acf/fields/user/result', array($this, 'acf_user_result'), 10, 4);
        
        // Dodajemy specjalny filtr dla pola employee_user
        add_filter('acf/fields/user/query/name=employee_user', array($this, 'filter_employee_user_query'), 10, 3);
        
        // Dodajemy walidację dla pól kontaktowych
        add_filter('acf/validate_value/name=person_emails', array($this, 'validate_person_emails'), 10, 4);
        add_filter('acf/validate_value/name=person_phones', array($this, 'validate_person_phones'), 10, 4);
        add_filter('acf/validate_value/name=company_emails', array($this, 'validate_company_emails'), 10, 4);
        add_filter('acf/validate_value/name=company_phones', array($this, 'validate_company_phones'), 10, 4);
        
        // Dodajemy walidację zadań - każde zadanie musi mieć przypisanego pracownika
        add_filter('acf/validate_value/name=task_employee', array($this, 'validate_task_employee'), 10, 4);
        
        // Dodajemy auto-przypisanie osoby do nowego projektu
        add_filter('acf/load_value/name=project_person', array($this, 'auto_assign_person_to_project'), 10, 3);
        
        // Synchronizacja pól pracownika i użytkownika w zadaniach
        add_action('acf/save_post', array($this, 'sync_task_employee_user_fields'), 20);
        add_filter('acf/load_value/name=task_assigned_user', array($this, 'load_task_user_from_employee'), 10, 3);
        add_filter('acf/load_value/name=task_employee', array($this, 'load_task_employee_from_user'), 10, 3);
    }
    
    /**
     * Automatyczne przypisanie osoby do nowego projektu na podstawie URL parametru
     */
    public function auto_assign_person_to_project($value, $post_id, $field) {
        // Tylko dla nowych projektów (post_id jest stringiem 'new_post' lub numerem dla istniejących)
        if (is_admin() && isset($_GET['person_id']) && $_GET['post_type'] === 'project' && (strpos($_SERVER['REQUEST_URI'], 'post-new.php') !== false)) {
            $person_id = intval($_GET['person_id']);
            if ($person_id > 0 && get_post_type($person_id) === 'person') {
                return array($person_id); // ACF relationship oczekuje tablicy
            }
        }
        return $value;
    }

    /**
     * Rozszerza wyszukiwanie w polu relacji o pole NIP.
     */
    public function extend_company_relationship_search($args, $field, $post_id) {
        if ( ! empty($args['s']) ) {
            $search_term = $args['s'];

            // Poniższe rozwiązanie dodaje wyszukiwanie po NIP.
            // Zostanie ono połączone z domyślnym wyszukiwaniem po tytule ('s') za pomocą AND.
            $args['meta_query'] = array(
                'relation' => 'OR',
                array(
                    'key' => 'company_nip',
                    'value' => $search_term,
                    'compare' => 'LIKE'
                )
            );
        }
        return $args;
    }

    public function register_all_field_groups()
    {
        // Wywołujemy po kolei metody definiujące pola dla każdego CPT
        $this->define_company_fields();
        $this->define_person_fields();
        $this->define_opportunity_fields();
        $this->define_quote_fields();
        $this->define_project_fields();
        $this->define_task_fields();
        $this->define_time_entry_fields();
        $this->define_invoice_fields();
        $this->define_payment_fields();
        $this->define_contract_fields();
        $this->define_expense_fields();
        $this->define_employee_fields();
        $this->define_activity_fields(); // <-- DODAJ TĘ LINIĘ
    }

    // --- Prywatne metody dla każdego CPT ---

    private function define_company_fields()
    {
        acf_add_local_field_group(array(
            'key' => 'group_wpmzf_company',
            'title' => 'Dane Firmy',
            'fields' => array(
                array(
                    'key'           => 'field_wpmzf_company_status',
                    'label'         => 'Status',
                    'name'          => 'company_status',
                    'type'          => 'select',
                    'choices'       => array(
                        'Aktywny'   => 'Aktywny',
                        'Nieaktywny' => 'Nieaktywny',
                        'Zarchiwizowany' => 'Zarchiwizowany',
                    ),
                    'default_value' => 'Aktywny',
                ),
                array(
                    'key'   => 'field_wpmzf_company_nip',
                    'label' => 'NIP',
                    'name'  => 'company_nip',
                    'type'  => 'text',
                ),
                // Pole dla adresów e-mail firmy
                array(
                    'key' => 'field_wpmzf_company_emails',
                    'label' => 'Adresy e-mail',
                    'name' => 'company_emails',
                    'type' => 'repeater',
                    'button_label' => 'Dodaj adres e-mail',
                    'min' => 0,
                    'max' => 10,
                    'sub_fields' => array(
                        array(
                            'key' => 'field_wpmzf_company_email_address',
                            'label' => 'Adres e-mail',
                            'name' => 'email_address',
                            'type' => 'email',
                            'required' => 1,
                            'placeholder' => 'np. kontakt@firma.pl',
                            'wrapper' => array('width' => '40'),
                        ),
                        array(
                            'key' => 'field_wpmzf_company_email_type',
                            'label' => 'Typ/Opis',
                            'name' => 'email_type',
                            'type' => 'text',
                            'placeholder' => 'np. główny, marketing, wsparcie, fakturowanie...',
                            'wrapper' => array('width' => '40'),
                        ),
                        array(
                            'key' => 'field_wpmzf_company_email_is_primary',
                            'label' => 'Główny',
                            'name' => 'is_primary',
                            'type' => 'true_false',
                            'message' => 'To jest główny adres e-mail',
                            'default_value' => 0,
                            'wrapper' => array('width' => '20'),
                        ),
                    ),
                ),
                // Pole dla numerów telefonów firmy
                array(
                    'key' => 'field_wpmzf_company_phones',
                    'label' => 'Numery telefonów',
                    'name' => 'company_phones',
                    'type' => 'repeater',
                    'button_label' => 'Dodaj numer telefonu',
                    'min' => 0,
                    'max' => 10,
                    'sub_fields' => array(
                        array(
                            'key' => 'field_wpmzf_company_phone_number',
                            'label' => 'Numer telefonu',
                            'name' => 'phone_number',
                            'type' => 'text',
                            'required' => 1,
                            'placeholder' => 'np. +48 12 345 67 89',
                            'wrapper' => array('width' => '40'),
                        ),
                        array(
                            'key' => 'field_wpmzf_company_phone_type',
                            'label' => 'Typ/Opis',
                            'name' => 'phone_type',
                            'type' => 'text',
                            'placeholder' => 'np. centrala, dział sprzedaży, wsparcie techniczne...',
                            'wrapper' => array('width' => '40'),
                        ),
                        array(
                            'key' => 'field_wpmzf_company_phone_is_primary',
                            'label' => 'Główny',
                            'name' => 'is_primary',
                            'type' => 'true_false',
                            'message' => 'To jest główny numer telefonu',
                            'default_value' => 0,
                            'wrapper' => array('width' => '20'),
                        ),
                    ),
                ),
                array(
                    'key'        => 'field_wpmzf_company_address_group',
                    'label'      => 'Adres',
                    'name'       => 'company_address',
                    'type'       => 'group',
                    'sub_fields' => array(
                        array('key' => 'field_wpmzf_company_street', 'label' => 'Ulica i numer', 'name' => 'street', 'type' => 'text'),
                        array('key' => 'field_wpmzf_company_zip', 'label' => 'Kod pocztowy', 'name' => 'zip_code', 'type' => 'text'),
                        array('key' => 'field_wpmzf_company_city', 'label' => 'Miasto', 'name' => 'city', 'type' => 'text'),
                    ),
                ),
                // Pole polecającego dla firm
                array(
                    'key'       => 'field_wpmzf_company_referrer',
                    'label'     => 'Polecający',
                    'name'      => 'company_referrer',
                    'type'      => 'relationship',
                    'post_type' => array('company', 'person'),
                    'filters'   => array('search', 'post_type'),
                    'min'       => 0,
                    'max'       => 1,
                    'instructions' => 'Wybierz osobę lub firmę, która poleciła tę firmę',
                ),
            ),
            'location' => array(array(array('param' => 'post_type', 'operator' => '==', 'value' => 'company'))),
        ));
    }

    private function define_person_fields()
    {
        acf_add_local_field_group(array(
            'key'      => 'group_wpmzf_person',
            'title'    => 'Dane Osoby',
            'fields'   => array(
                array(
                    'key'           => 'field_wpmzf_person_status',
                    'label'         => 'Status',
                    'name'          => 'person_status',
                    'type'          => 'select',
                    'choices'       => array(
                        'Aktywny'   => 'Aktywny',
                        'Nieaktywny' => 'Nieaktywny',
                        'Zarchiwizowany' => 'Zarchiwizowany',
                    ),
                    'default_value' => 'Aktywny',
                ),
                array(
                    'key'   => 'field_wpmzf_person_position',
                    'label' => 'Stanowisko',
                    'name'  => 'person_position',
                    'type'  => 'text',
                ),
                // Pole dla adresów e-mail
                array(
                    'key' => 'field_wpmzf_person_emails',
                    'label' => 'Adresy e-mail',
                    'name' => 'person_emails',
                    'type' => 'repeater',
                    'button_label' => 'Dodaj adres e-mail',
                    'min' => 0,
                    'max' => 10,
                    'sub_fields' => array(
                        array(
                            'key' => 'field_wpmzf_person_email_address',
                            'label' => 'Adres e-mail',
                            'name' => 'email_address',
                            'type' => 'email',
                            'required' => 1,
                            'placeholder' => 'np. jan.kowalski@example.com',
                            'wrapper' => array('width' => '40'),
                        ),
                        array(
                            'key' => 'field_wpmzf_person_email_type',
                            'label' => 'Typ/Opis',
                            'name' => 'email_type',
                            'type' => 'text',
                            'placeholder' => 'np. służbowy, prywatny, marketing...',
                            'wrapper' => array('width' => '40'),
                        ),
                        array(
                            'key' => 'field_wpmzf_person_email_is_primary',
                            'label' => 'Główny',
                            'name' => 'is_primary',
                            'type' => 'true_false',
                            'message' => 'To jest główny adres e-mail',
                            'default_value' => 0,
                            'wrapper' => array('width' => '20'),
                        ),
                    ),
                ),
                // Pole dla numerów telefonów
                array(
                    'key' => 'field_wpmzf_person_phones',
                    'label' => 'Numery telefonów',
                    'name' => 'person_phones',
                    'type' => 'repeater',
                    'button_label' => 'Dodaj numer telefonu',
                    'min' => 0,
                    'max' => 10,
                    'sub_fields' => array(
                        array(
                            'key' => 'field_wpmzf_person_phone_number',
                            'label' => 'Numer telefonu',
                            'name' => 'phone_number',
                            'type' => 'text',
                            'required' => 1,
                            'placeholder' => 'np. +48 123 456 789',
                            'wrapper' => array('width' => '40'),
                        ),
                        array(
                            'key' => 'field_wpmzf_person_phone_type',
                            'label' => 'Typ/Opis',
                            'name' => 'phone_type',
                            'type' => 'text',
                            'placeholder' => 'np. służbowy, prywatny, alarmowy...',
                            'wrapper' => array('width' => '40'),
                        ),
                        array(
                            'key' => 'field_wpmzf_person_phone_is_primary',
                            'label' => 'Główny',
                            'name' => 'is_primary',
                            'type' => 'true_false',
                            'message' => 'To jest główny numer telefonu',
                            'default_value' => 0,
                            'wrapper' => array('width' => '20'),
                        ),
                    ),
                ),
                array(
                    'key'        => 'field_wpmzf_person_address_group',
                    'label'      => 'Adres',
                    'name'       => 'person_address',
                    'type'       => 'group',
                    'sub_fields' => array(
                        array('key' => 'field_wpmzf_person_street', 'label' => 'Ulica i numer', 'name' => 'street', 'type' => 'text'),
                        array('key' => 'field_wpmzf_person_zip', 'label' => 'Kod pocztowy', 'name' => 'zip_code', 'type' => 'text'),
                        array('key' => 'field_wpmzf_person_city', 'label' => 'Miasto', 'name' => 'city', 'type' => 'text'),
                    ),
                ),
                array(
                    'key'       => 'field_wpmzf_person_company_relation',
                    'label'     => 'Powiązana Firma',
                    'name'      => 'person_company',
                    'type'      => 'relationship',
                    'post_type' => array('company'),
                    'filters'   => array('search'),
                    'max'       => 5,    // max pozostaje, usuń 'min' żeby nie było wymagane
                ),
                // Pole polecającego dla osób
                array(
                    'key'       => 'field_wpmzf_person_referrer',
                    'label'     => 'Polecający',
                    'name'      => 'person_referrer',
                    'type'      => 'relationship',
                    'post_type' => array('company', 'person'),
                    'filters'   => array('search', 'post_type'),
                    'min'       => 0,
                    'max'       => 1,
                    'instructions' => 'Wybierz osobę lub firmę, która poleciła tę osobę',
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param'    => 'post_type',
                        'operator' => '==',
                        'value'    => 'person',
                    ),
                ),
            ),
        ));
    }
    private function define_opportunity_fields()
    {
        acf_add_local_field_group(array(
            'key' => 'group_wpmzf_opportunity',
            'title' => 'Szczegóły Szansy Sprzedaży',
            'fields' => array(
                array(
                    'key' => 'field_wpmzf_opportunity_company',
                    'label' => 'Firma',
                    'name' => 'opportunity_company',
                    'type' => 'relationship',
                    'post_type' => array('company'),
                    'max' => 1,
                    'min' => 0,
                    'return_format' => 'id',
                    'ui' => 1,
                    'instructions' => 'Wybierz firmę, dla której dotyczy ta szansa sprzedaży.',
                ),
                array(
                    'key' => 'field_wpmzf_opportunity_value',
                    'label' => 'Wartość (PLN)',
                    'name' => 'opportunity_value',
                    'type' => 'number',
                    'step' => 0.01,
                    'min' => 0,
                    'instructions' => 'Szacowana wartość szansy sprzedaży w PLN.',
                ),
                array(
                    'key' => 'field_wpmzf_opportunity_probability',
                    'label' => 'Prawdopodobieństwo (%)',
                    'name' => 'opportunity_probability',
                    'type' => 'number',
                    'min' => 0,
                    'max' => 100,
                    'default_value' => 50,
                    'instructions' => 'Prawdopodobieństwo wygrania szansy (0-100%).',
                ),
                array(
                    'key' => 'field_wpmzf_opportunity_expected_close_date',
                    'label' => 'Przewidywana data zamknięcia',
                    'name' => 'opportunity_expected_close_date',
                    'type' => 'date_picker',
                    'display_format' => 'd/m/Y',
                    'return_format' => 'Y-m-d',
                    'instructions' => 'Kiedy spodziewamy się zamknięcia tej szansy?',
                ),
                array(
                    'key' => 'field_wpmzf_opportunity_reason',
                    'label' => 'Powód wygranej/przegranej',
                    'name' => 'opportunity_reason',
                    'type' => 'textarea',
                    'rows' => 3,
                    'instructions' => 'Opisz powód wygrania lub przegrania szansy. Pole opcjonalne.',
                ),
                array(
                    'key' => 'field_wpmzf_opportunity_contact_person',
                    'label' => 'Osoba kontaktowa',
                    'name' => 'opportunity_contact_person',
                    'type' => 'relationship',
                    'post_type' => array('person'),
                    'max' => 1,
                    'min' => 0,
                    'return_format' => 'id',
                    'ui' => 1,
                    'instructions' => 'Główna osoba kontaktowa w tej szansie sprzedaży.',
                ),
                array(
                    'key' => 'field_wpmzf_opportunity_source',
                    'label' => 'Źródło',
                    'name' => 'opportunity_source',
                    'type' => 'select',
                    'choices' => array(
                        'website' => 'Strona internetowa',
                        'referral' => 'Polecenie',
                        'cold_call' => 'Zimny kontakt',
                        'social_media' => 'Media społecznościowe',
                        'trade_show' => 'Targi',
                        'advertising' => 'Reklama',
                        'email_campaign' => 'Kampania email',
                        'other' => 'Inne',
                    ),
                    'default_value' => 'website',
                    'allow_null' => 1,
                    'instructions' => 'Skąd pochodzi ta szansa sprzedaży?',
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'opportunity',
                    ),
                ),
            ),
            'menu_order' => 0,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
        ));
    }

    private function define_quote_fields()
    {
        acf_add_local_field_group(array(
            'key' => 'group_wpmzf_quote',
            'title' => 'Szczegóły Oferty',
            'fields' => array(
                array('key' => 'field_wpmzf_quote_status', 'label' => 'Status', 'name' => 'quote_status', 'type' => 'select', 'choices' => array('Szkic' => 'Szkic', 'Wysłana' => 'Wysłana', 'Zaakceptowana' => 'Zaakceptowana', 'Odrzucona' => 'Odrzucona')),
                array('key' => 'field_wpmzf_quote_expiry', 'label' => 'Data ważności', 'name' => 'quote_expiry_date', 'type' => 'date_picker'),
                array('key' => 'field_wpmzf_quote_company_relation', 'label' => 'Dla Firmy', 'name' => 'quote_company', 'type' => 'relationship', 'post_type' => array('company'), 'max' => 1),
                array('key' => 'field_wpmzf_quote_items', 'label' => 'Pozycje Oferty', 'name' => 'quote_items', 'type' => 'repeater', 'button_label' => 'Dodaj pozycję', 'sub_fields' => array(
                    array('key' => 'field_wpmzf_quote_item_name', 'label' => 'Nazwa usługi', 'name' => 'name', 'type' => 'text'),
                    array('key' => 'field_wpmzf_quote_item_qty', 'label' => 'Ilość', 'name' => 'quantity', 'type' => 'number', 'default_value' => 1),
                    array('key' => 'field_wpmzf_quote_item_price', 'label' => 'Cena netto', 'name' => 'price', 'type' => 'number'),
                )),
            ),
            'location' => array(array(array('param' => 'post_type', 'operator' => '==', 'value' => 'quote'))),
        ));
    }

    private function define_project_fields()
    {
        acf_add_local_field_group(array(
            'key' => 'group_wpmzf_project',
            'title' => 'Szczegóły Projektu',
            'fields' => array(
                array('key' => 'field_wpmzf_project_status', 'label' => 'Status', 'name' => 'project_status', 'type' => 'select', 'choices' => array('Planowanie' => 'Planowanie', 'W toku' => 'W toku', 'Zakończony' => 'Zakończony')),
                array('key' => 'field_wpmzf_project_budget', 'label' => 'Budżet (PLN)', 'name' => 'project_budget', 'type' => 'number', 'step' => 0.01, 'min' => 0),
                array('key' => 'field_wpmzf_project_company_relation', 'label' => 'Realizowane dla Firmy', 'name' => 'project_company', 'type' => 'relationship', 'post_type' => array('company'), 'min' => 0, 'max' => 1),
                array('key' => 'field_wpmzf_project_person_relation', 'label' => 'Przypisane do Osoby', 'name' => 'project_person', 'type' => 'relationship', 'post_type' => array('person'), 'min' => 0, 'max' => 1),
                array('key' => 'field_wpmzf_project_start_date', 'label' => 'Data rozpoczęcia', 'name' => 'start_date', 'type' => 'date_picker', 'display_format' => 'Y-m-d', 'return_format' => 'Y-m-d'),
                array('key' => 'field_wpmzf_project_end_date', 'label' => 'Data zakończenia (deadline)', 'name' => 'end_date', 'type' => 'date_picker', 'display_format' => 'Y-m-d', 'return_format' => 'Y-m-d'),
            ),
            'location' => array(array(array('param' => 'post_type', 'operator' => '==', 'value' => 'project'))),
        ));
    }

    private function define_task_fields()
    {
        acf_add_local_field_group(array(
            'key' => 'group_wpmzf_task',
            'title' => 'Szczegóły Zadania',
            'fields' => array(
                array(
                    'key' => 'field_wpmzf_task_status',
                    'label' => 'Status',
                    'name' => 'task_status',
                    'type' => 'select',
                    'choices' => array(
                        'Do zrobienia' => 'Do zrobienia',
                        'W toku' => 'W toku',
                        'Zrobione' => 'Zrobione'
                    ),
                    'default_value' => 'Do zrobienia'
                ),
                array(
                    'key' => 'field_wpmzf_task_description',
                    'label' => 'Opis zadania',
                    'name' => 'task_description',
                    'type' => 'textarea',
                    'rows' => 4
                ),
                array(
                    'key' => 'field_wpmzf_task_start_date',
                    'label' => 'Data rozpoczęcia',
                    'name' => 'task_start_date',
                    'type' => 'date_time_picker',
                    'display_format' => 'Y-m-d H:i',
                    'return_format' => 'Y-m-d H:i:s'
                ),
                array(
                    'key' => 'field_wpmzf_task_end_date',
                    'label' => 'Data zakończenia',
                    'name' => 'task_end_date',
                    'type' => 'date_time_picker',
                    'display_format' => 'Y-m-d H:i',
                    'return_format' => 'Y-m-d H:i:s'
                ),
                array(
                    'key' => 'field_wpmzf_task_assigned_person',
                    'label' => 'Przypisane do osoby',
                    'name' => 'task_assigned_person',
                    'type' => 'relationship',
                    'post_type' => array('person'),
                    'min' => 0,
                    'max' => 1
                ),
                array(
                    'key' => 'field_wpmzf_task_assigned_company',
                    'label' => 'Przypisane do firmy',
                    'name' => 'task_assigned_company',
                    'type' => 'relationship',
                    'post_type' => array('company'),
                    'min' => 0,
                    'max' => 1
                ),
                array(
                    'key' => 'field_wpmzf_task_project_relation',
                    'label' => 'Część Projektu',
                    'name' => 'task_project',
                    'type' => 'relationship',
                    'post_type' => array('project'),
                    'min' => 1,
                    'max' => 1
                ),
                array(
                    'key' => 'field_wpmzf_task_employee_relation',
                    'label' => 'Przypisane do pracownika',
                    'name' => 'task_employee',
                    'type' => 'relationship',
                    'post_type' => array('employee'),
                    'min' => 1, // Wymagane!
                    'max' => 1,
                    'required' => 1
                ),
                array(
                    'key' => 'field_wpmzf_task_assigned_user',
                    'label' => 'Odpowiedzialny użytkownik',
                    'name' => 'task_assigned_user',
                    'type' => 'user',
                    'instructions' => 'Bezpośrednie przypisanie zadania do użytkownika WordPress. To pole jest używane zamiast relacji z pracownikiem.',
                    'required' => 0,
                    'allow_null' => 1,
                    'multiple' => 0,
                    'return_format' => 'id',
                    'role' => array('subscriber', 'contributor', 'author', 'editor', 'administrator'), // Wyraźnie określone role
                    'min' => 0,
                    'max' => 1
                ),
            ),
            'location' => array(array(array('param' => 'post_type', 'operator' => '==', 'value' => 'task'))),
        ));
    }

    private function define_time_entry_fields()
    {
        acf_add_local_field_group(array(
            'key' => 'group_wpmzf_time_entry',
            'title' => 'Szczegóły Wpisu Czasu',
            'fields' => array(
                array('key' => 'field_wpmzf_time_entry_hours', 'label' => 'Liczba godzin', 'name' => 'time_entry_hours', 'type' => 'number', 'step' => '0.25'),
                array('key' => 'field_wpmzf_time_entry_date', 'label' => 'Data pracy', 'name' => 'time_entry_date', 'type' => 'date_picker'),
                array('key' => 'field_wpmzf_time_entry_project_relation', 'label' => 'Do Projektu', 'name' => 'time_entry_project', 'type' => 'relationship', 'post_type' => array('project'), 'min' => 1, 'max' => 1),
            ),
            'location' => array(array(array('param' => 'post_type', 'operator' => '==', 'value' => 'time_entry'))),
        ));
    }

    private function define_invoice_fields()
    {
        // ... (Analogicznie dla Faktur, z polem Repeater na pozycje)
    }

    private function define_payment_fields()
    {
        acf_add_local_field_group(array(
            'key' => 'group_wpmzf_payment',
            'title' => 'Szczegóły Płatności',
            'fields' => array(
                array('key' => 'field_wpmzf_payment_amount', 'label' => 'Kwota', 'name' => 'payment_amount', 'type' => 'number'),
                array('key' => 'field_wpmzf_payment_date', 'label' => 'Data otrzymania', 'name' => 'payment_date', 'type' => 'date_picker'),
                array('key' => 'field_wpmzf_payment_invoice_relation', 'label' => 'Opłaca Fakturę/y', 'name' => 'payment_invoices', 'type' => 'relationship', 'post_type' => array('invoice')),
            ),
            'location' => array(array(array('param' => 'post_type', 'operator' => '==', 'value' => 'payment'))),
        ));
    }

    private function define_contract_fields()
    {
        // ... (Analogicznie dla Umów)
    }

    private function define_expense_fields()
    {
        // ... (Analogicznie dla Kosztów)
    }

    private function define_employee_fields()
    {
        acf_add_local_field_group(array(
            'key' => 'group_wpmzf_employee',
            'title' => 'Dane Pracownika',
            'fields' => array(
                array('key' => 'field_wpmzf_employee_position', 'label' => 'Stanowisko', 'name' => 'employee_position', 'type' => 'text'),
                array('key' => 'field_wpmzf_employee_rate', 'label' => 'Stawka godzinowa', 'name' => 'employee_rate', 'type' => 'number'),
                array(
                    'key' => 'field_wpmzf_employee_user_relation', 
                    'label' => 'Powiązany Użytkownik WP', 
                    'name' => 'employee_user', 
                    'type' => 'user', 
                    'instructions' => 'Połącz ten wpis z kontem użytkownika WordPress, aby mógł się logować i rejestrować czas pracy.',
                    'required' => 0,
                    'allow_null' => 1,
                    'multiple' => 0,
                    'return_format' => 'id',
                    'role' => array('subscriber', 'contributor', 'author', 'editor', 'administrator'), // Wyraźnie określone role
                    'min' => 0,
                    'max' => 1
                ),
            ),
            'location' => array(array(array('param' => 'post_type', 'operator' => '==', 'value' => 'employee'))),
        ));
    }

    /**
     * Nowa metoda definiująca pola dla CPT 'activity'
     */
    private function define_activity_fields()
    {
        acf_add_local_field_group(array(
            'key' => 'group_wpmzf_activity',
            'title' => 'Szczegóły Aktywności',
            'fields' => array(
                array(
                    'key' => 'field_wpmzf_activity_type',
                    'label' => 'Typ aktywności',
                    'name' => 'activity_type',
                    'type' => 'select',
                    'choices' => array(
                        'note' => 'Notatka',
                        'email' => 'E-mail',
                        'phone' => 'Telefon',
                        'meeting' => 'Spotkanie',
                        'meeting_online' => 'Spotkanie online',
                    ),
                    'default_value' => 'note',
                    'ui' => 1, // Lepszy interfejs select2
                ),
                array(
                    'key' => 'field_wpmzf_activity_date',
                    'label' => 'Data i godzina aktywności',
                    'name' => 'activity_date',
                    'type' => 'date_time_picker',
                    'display_format' => 'Y-m-d H:i:s',
                    'return_format' => 'Y-m-d H:i:s',
                    'required' => 1,
                ),
                array(
                    'key' => 'field_wpmzf_activity_attachments',
                    'label' => 'Załączniki',
                    'name' => 'activity_attachments',
                    'type' => 'repeater',
                    'button_label' => 'Dodaj załącznik',
                    'sub_fields' => array(
                        array(
                            'key' => 'field_wpmzf_activity_attachment_file',
                            'label' => 'Plik załącznika',
                            'name' => 'attachment_file',
                            'type' => 'file',
                            'return_format' => 'id', // Zapisuje ID załącznika
                            'library' => 'all',
                        ),
                    ),
                ),
                // Kluczowe pole do powiązania aktywności z osobą
                array(
                    'key' => 'field_wpmzf_activity_related_person',
                    'label' => 'Powiązana osoba',
                    'name' => 'related_person',
                    'type' => 'relationship',
                    'post_type' => array('person'),
                    'max' => 1,
                    'required' => 0,
                ),
                // Pole do powiązania aktywności z firmą
                array(
                    'key' => 'field_wpmzf_activity_related_company',
                    'label' => 'Powiązana firma',
                    'name' => 'related_company',
                    'type' => 'relationship',
                    'post_type' => array('company'),
                    'max' => 1,
                    'required' => 0,
                ),
                // Pole do powiązania aktywności z projektem
                array(
                    'key' => 'field_wpmzf_activity_related_project',
                    'label' => 'Powiązany projekt',
                    'name' => 'related_project',
                    'type' => 'relationship',
                    'post_type' => array('project'),
                    'max' => 1,
                    'required' => 0,
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'activity',
                    ),
                ),
            ),
            'position' => 'side', // Wyświetlaj te pola w kolumnie bocznej
        ));
    }
    
    /**
     * Waliduje że tylko jeden e-mail osoby może być oznaczony jako główny
     */
    public function validate_person_emails($valid, $value, $field, $input)
    {
        if (!$valid || !is_array($value)) {
            return $valid;
        }
        
        $primary_count = 0;
        foreach ($value as $email) {
            if (!empty($email['is_primary'])) {
                $primary_count++;
            }
        }
        
        if ($primary_count > 1) {
            return 'Tylko jeden adres e-mail może być oznaczony jako główny.';
        }
        
        return $valid;
    }
    
    /**
     * Waliduje że tylko jeden telefon osoby może być oznaczony jako główny
     */
    public function validate_person_phones($valid, $value, $field, $input)
    {
        if (!$valid || !is_array($value)) {
            return $valid;
        }
        
        $primary_count = 0;
        foreach ($value as $phone) {
            if (!empty($phone['is_primary'])) {
                $primary_count++;
            }
        }
        
        if ($primary_count > 1) {
            return 'Tylko jeden numer telefonu może być oznaczony jako główny.';
        }
        
        return $valid;
    }
    
    /**
     * Waliduje że tylko jeden e-mail firmy może być oznaczony jako główny
     */
    public function validate_company_emails($valid, $value, $field, $input)
    {
        if (!$valid || !is_array($value)) {
            return $valid;
        }
        
        $primary_count = 0;
        foreach ($value as $email) {
            if (!empty($email['is_primary'])) {
                $primary_count++;
            }
        }
        
        if ($primary_count > 1) {
            return 'Tylko jeden adres e-mail może być oznaczony jako główny.';
        }
        
        return $valid;
    }
    
    /**
     * Waliduje że tylko jeden telefon firmy może być oznaczony jako główny
     */
    public function validate_company_phones($valid, $value, $field, $input)
    {
        if (!$valid || !is_array($value)) {
            return $valid;
        }
        
        $primary_count = 0;
        foreach ($value as $phone) {
            if (!empty($phone['is_primary'])) {
                $primary_count++;
            }
        }
        
        if ($primary_count > 1) {
            return 'Tylko jeden numer telefonu może być oznaczony jako główny.';
        }
        
        return $valid;
    }
    
    /**
     * Filtruje query dla pól user w ACF
     */
    public function acf_user_query($args, $field, $post_id) {
        // Domyślnie zwracamy wszystkich użytkowników
        return $args;
    }
    
    /**
     * Formatuje wyniki dla pól user w ACF
     */
    public function acf_user_result($title, $user, $field, $post_id) {
        // Debug: logujemy typ danych (tylko w trybie deweloperskim)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('WPMZF Debug - acf_user_result: user type = ' . gettype($user) . 
                     ', is_object = ' . (is_object($user) ? 'true' : 'false') . 
                     ', is_array = ' . (is_array($user) ? 'true' : 'false'));
        }
        
        // Sprawdzamy czy $user jest obiektem WP_User czy tablicą
        if (is_object($user) && isset($user->ID)) {
            // $user jest już obiektem WP_User
            $user_obj = $user;
        } elseif (is_array($user) && isset($user['ID'])) {
            // $user jest tablicą, pobieramy obiekt
            $user_obj = get_user_by('ID', $user['ID']);
        } else {
            // Nieprawidłowy format, zwracamy oryginalny tytuł
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('WPMZF Warning - acf_user_result: nieznany format danych user');
            }
            return $title;
        }
        
        if ($user_obj && is_object($user_obj)) {
            $roles = implode(', ', $user_obj->roles);
            $title = $user_obj->display_name . ' (' . $user_obj->user_login . ') - ' . $roles;
        }
        return $title;
    }
    
    /**
     * Specjalny filtr dla pola employee_user
     */
    public function filter_employee_user_query($args, $field, $post_id) {
        // Pokaż wszystkich użytkowników bez ograniczeń
        $args['number'] = -1; // Bez limitu
        $args['fields'] = 'all'; // Wszystkie pola
        
        // Opcjonalnie: możemy wykluczyć użytkowników, którzy już mają profil pracownika
        // ale na początku lepiej pokazać wszystkich
        
        return $args;
    }
    
    /**
     * Synchronizuje pola pracownika i użytkownika w zadaniach po zapisaniu
     */
    public function sync_task_employee_user_fields($post_id) {
        // Sprawdź czy to jest zadanie
        if (get_post_type($post_id) !== 'task') {
            return;
        }
        
        // Pobierz wartości pól
        $assigned_user = get_field('task_assigned_user', $post_id);
        $assigned_employee = get_field('task_employee', $post_id);
        
        // Jeśli jest przypisany użytkownik, ale nie ma pracownika - znajdź pracownika dla tego użytkownika
        if ($assigned_user && !$assigned_employee) {
            $employee = WPMZF_Employee_Helper::get_employee_by_user_id($assigned_user);
            if ($employee) {
                update_field('task_employee', array($employee->ID), $post_id);
            }
        }
        
        // Jeśli jest przypisany pracownik, ale nie ma użytkownika - znajdź użytkownika dla tego pracownika
        if ($assigned_employee && !$assigned_user) {
            if (is_array($assigned_employee) && !empty($assigned_employee)) {
                $employee_id = $assigned_employee[0];
                $user_id = get_field('employee_user', $employee_id);
                if ($user_id) {
                    update_field('task_assigned_user', $user_id, $post_id);
                }
            }
        }
    }
    
    /**
     * Ładuje użytkownika na podstawie przypisanego pracownika
     */
    public function load_task_user_from_employee($value, $post_id, $field) {
        // Tylko dla zadań
        if (get_post_type($post_id) !== 'task') {
            return $value;
        }
        
        // Jeśli użytkownik już jest przypisany, nie zmieniaj
        if ($value) {
            return $value;
        }
        
        // Sprawdź czy jest przypisany pracownik
        $assigned_employee = get_field('task_employee', $post_id);
        if ($assigned_employee && is_array($assigned_employee) && !empty($assigned_employee)) {
            $employee_id = $assigned_employee[0];
            $user_id = get_field('employee_user', $employee_id);
            if ($user_id) {
                return $user_id;
            }
        }
        
        return $value;
    }
    
    /**
     * Ładuje pracownika na podstawie przypisanego użytkownika
     */
    public function load_task_employee_from_user($value, $post_id, $field) {
        // Tylko dla zadań
        if (get_post_type($post_id) !== 'task') {
            return $value;
        }
        
        // Jeśli pracownik już jest przypisany, nie zmieniaj
        if ($value) {
            return $value;
        }
        
        // Sprawdź czy jest przypisany użytkownik
        $assigned_user = get_field('task_assigned_user', $post_id);
        if ($assigned_user) {
            $employee = WPMZF_Employee_Helper::get_employee_by_user_id($assigned_user);
            if ($employee) {
                return array($employee->ID);
            }
        }
        
        return $value;
    }
    
    /**
     * Waliduje czy zadanie ma przypisanego pracownika
     */
    public function validate_task_employee($valid, $value, $field, $input) {
        // Tylko dla zadań
        $post_id = $_POST['post_ID'] ?? 0;
        if ($post_id && get_post_type($post_id) !== 'task') {
            return $valid;
        }
        
        // Sprawdź czy to jest nowe zadanie
        if (!$post_id && isset($_POST['post_type']) && $_POST['post_type'] === 'task') {
            // To jest nowe zadanie
        }
        
        // Sprawdź czy pracownik jest przypisany
        if (empty($value) || (is_array($value) && count($value) == 0)) {
            return 'Każde zadanie musi mieć przypisanego pracownika.';
        }
        
        return $valid;
    }
}
