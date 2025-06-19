<?php
/**
 * Plik odpowiedzialny za rejestrację wszystkich grup pól ACF w kodzie PHP.
 */
class WPMZF_ACF_Fields {

    public function __construct() {
        // Używamy hooka 'acf/include_fields', który jest dedykowany do rejestracji pól w kodzie.
        add_action('acf/include_fields', array($this, 'register_all_field_groups'));
    }

    public function register_all_field_groups() {
        // Wywołujemy po kolei metody definiujące pola dla każdego CPT
        $this->define_company_fields();
        $this->define_contact_fields();
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
    }

    // --- Prywatne metody dla każdego CPT ---

    private function define_company_fields() {
        acf_add_local_field_group(array(
            'key' => 'group_wpmzf_company',
            'title' => 'Dane Firmy',
            'fields' => array(
                array('key' => 'field_wpmzf_company_type', 'label' => 'Typ Klienta', 'name' => 'company_type', 'type' => 'button_group', 'choices' => array('Firma' => 'Firma', 'Osoba fizyczna' => 'Osoba fizyczna'), 'default_value' => 'Firma'),
                array('key' => 'field_wpmzf_company_nip', 'label' => 'NIP', 'name' => 'company_nip', 'type' => 'text', 'conditional_logic' => array(array(array('field' => 'field_wpmzf_company_type', 'operator' => '==', 'value' => 'Firma')))),
                array('key' => 'field_wpmzf_company_address_group', 'label' => 'Adres', 'name' => 'company_address', 'type' => 'group', 'sub_fields' => array(
                    array('key' => 'field_wpmzf_company_street', 'label' => 'Ulica i numer', 'name' => 'street', 'type' => 'text'),
                    array('key' => 'field_wpmzf_company_zip', 'label' => 'Kod pocztowy', 'name' => 'zip_code', 'type' => 'text'),
                    array('key' => 'field_wpmzf_company_city', 'label' => 'Miasto', 'name' => 'city', 'type' => 'text'),
                )),
            ),
            'location' => array(array(array('param' => 'post_type', 'operator' => '==', 'value' => 'company'))),
        ));
    }

    private function define_contact_fields() {
        acf_add_local_field_group(array(
            'key' => 'group_wpmzf_contact',
            'title' => 'Dane Kontaktu',
            'fields' => array(
                array('key' => 'field_wpmzf_contact_position', 'label' => 'Stanowisko', 'name' => 'contact_position', 'type' => 'text'),
                array('key' => 'field_wpmzf_contact_email', 'label' => 'Adres e-mail', 'name' => 'contact_email', 'type' => 'email'),
                array('key' => 'field_wpmzf_contact_phone', 'label' => 'Numer telefonu', 'name' => 'contact_phone', 'type' => 'text'),
                array('key' => 'field_wpmzf_contact_company_relation', 'label' => 'Powiązana Firma', 'name' => 'contact_company', 'type' => 'relationship', 'post_type' => array('company'), 'filters' => array('search'), 'min' => 1, 'max' => 1),
            ),
            'location' => array(array(array('param' => 'post_type', 'operator' => '==', 'value' => 'contact'))),
        ));
    }

    private function define_opportunity_fields() {
        // ... (Analogicznie dla Szans Sprzedaży)
    }

    private function define_quote_fields() {
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

    private function define_project_fields() {
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
    
    private function define_task_fields() {
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

    private function define_time_entry_fields() {
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
    
    private function define_invoice_fields() {
        // ... (Analogicznie dla Faktur, z polem Repeater na pozycje)
    }

    private function define_payment_fields() {
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
    
    private function define_contract_fields() {
        // ... (Analogicznie dla Umów)
    }

    private function define_expense_fields() {
        // ... (Analogicznie dla Kosztów)
    }
    
    private function define_employee_fields() {
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
}