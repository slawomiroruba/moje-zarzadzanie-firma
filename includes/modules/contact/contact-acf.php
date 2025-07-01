<?php



private function define_contact_fields()
    {
        acf_add_local_field_group(array(
            'key'      => 'group_wpmzf_contact',
            'title'    => 'Dane Kontaktu',
            'fields'   => array(
                array(
                    'key'           => 'field_wpmzf_contact_status',
                    'label'         => 'Status',
                    'name'          => 'contact_status',
                    'type'          => 'select',
                    'choices'       => array(
                        'Aktywny'   => 'Aktywny',
                        'Nieaktywny' => 'Nieaktywny',
                        'Zarchiwizowany' => 'Zarchiwizowany',
                    ),
                    'default_value' => 'Aktywny',
                ),
                array(
                    'key'   => 'field_wpmzf_contact_position',
                    'label' => 'Stanowisko',
                    'name'  => 'contact_position',
                    'type'  => 'text',
                ),
                array(
                    'key'   => 'field_wpmzf_contact_email',
                    'label' => 'Adres e-mail',
                    'name'  => 'contact_email',
                    'type'  => 'email',
                ),
                array(
                    'key'   => 'field_wpmzf_contact_phone',
                    'label' => 'Numer telefonu',
                    'name'  => 'contact_phone',
                    'type'  => 'text',
                ),
                array(
                    'key'        => 'field_wpmzf_contact_address_group',
                    'label'      => 'Adres',
                    'name'       => 'contact_address',
                    'type'       => 'group',
                    'sub_fields' => array(
                        array('key' => 'field_wpmzf_contact_street', 'label' => 'Ulica i numer', 'name' => 'street', 'type' => 'text'),
                        array('key' => 'field_wpmzf_contact_zip', 'label' => 'Kod pocztowy', 'name' => 'zip_code', 'type' => 'text'),
                        array('key' => 'field_wpmzf_contact_city', 'label' => 'Miasto', 'name' => 'city', 'type' => 'text'),
                    ),
                ),
                array(
                    'key'       => 'field_wpmzf_contact_company_relation',
                    'label'     => 'Powiązana Firma',
                    'name'      => 'contact_company',
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
                        'value'    => 'contact',
                    ),
                ),
            ),
        ));
    }