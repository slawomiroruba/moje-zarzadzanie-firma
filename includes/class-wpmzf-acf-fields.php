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
                    'key'   => 'field_wpmzf_company_nip',
                    'label' => 'NIP',
                    'name'  => 'company_nip',
                    'type'  => 'text',
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
                array(
                    'key'   => 'field_wpmzf_person_email',
                    'label' => 'Adres e-mail',
                    'name'  => 'person_email',
                    'type'  => 'email',
                ),
                array(
                    'key'   => 'field_wpmzf_person_phone',
                    'label' => 'Numer telefonu',
                    'name'  => 'person_phone',
                    'type'  => 'text',
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
                    'max'       => 1,    // max pozostaje, usuń 'min' żeby nie było wymagane
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
        // ... (Analogicznie dla Szans Sprzedaży)
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
                array('key' => 'field_wpmzf_project_company_relation', 'label' => 'Realizowane dla Firmy', 'name' => 'project_company', 'type' => 'relationship', 'post_type' => array('company'), 'min' => 1, 'max' => 1),
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
                array('key' => 'field_wpmzf_task_status', 'label' => 'Status', 'name' => 'task_status', 'type' => 'select', 'choices' => array('Do zrobienia' => 'Do zrobienia', 'W toku' => 'W toku', 'Zrobione' => 'Zrobione')),
                array('key' => 'field_wpmzf_task_project_relation', 'label' => 'Część Projektu', 'name' => 'task_project', 'type' => 'relationship', 'post_type' => array('project'), 'min' => 1, 'max' => 1),
                array('key' => 'field_wpmzf_task_employee_relation', 'label' => 'Przypisane do', 'name' => 'task_employee', 'type' => 'relationship', 'post_type' => array('employee'), 'min' => 1, 'max' => 1),
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
                array('key' => 'field_wpmzf_employee_user_relation', 'label' => 'Powiązany Użytkownik WP', 'name' => 'employee_user', 'type' => 'user', 'role' => 'all', 'min' => 1, 'max' => 1, 'instructions' => 'Połącz ten wpis z kontem użytkownika WordPress, aby mógł się logować i rejestrować czas pracy.'),
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
                    'required' => 1,
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
}
