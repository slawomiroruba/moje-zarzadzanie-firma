jQuery(document).ready(function ($) {

	// Funkcja walidacji tylko jednego głównego kontaktu w repeaterze
	function validateSinglePrimary(repeaterFieldName) {
		$(document).on('change', `[data-name="${repeaterFieldName}"] input[data-name="is_primary"]`, function () {
			const currentRepeater = $(this).closest(`[data-name="${repeaterFieldName}"]`);

			if ($(this).is(':checked')) {
				// Jeśli ten checkbox został zaznaczony, odznacz wszystkie inne w tym repeaterze
				currentRepeater.find('input[data-name="is_primary"]').not(this).prop('checked', false);

				// Dodaj klasę CSS do rzędu z głównym kontaktem
				$(this).closest('.acf-row').addClass('has-primary-contact');
				currentRepeater.find('.acf-row').not($(this).closest('.acf-row')).removeClass('has-primary-contact');
			} else {
				// Usuń klasę jeśli checkbox został odznaczony
				$(this).closest('.acf-row').removeClass('has-primary-contact');
			}
		});

		// Inicjalna sprawdzenie przy ładowaniu strony
		setTimeout(() => {
			$(`[data-name="${repeaterFieldName}"] input[data-name="is_primary"]:checked`).each(function () {
				$(this).closest('.acf-row').addClass('has-primary-contact');
			});
		}, 500);
	}

	// Zastosuj walidację dla pól kontaktowych osób
	validateSinglePrimary('person_emails');
	validateSinglePrimary('person_phones');

	// Zastosuj walidację dla pól kontaktowych firm
	validateSinglePrimary('company_emails');
	validateSinglePrimary('company_phones');

	// Dodaj style dla lepszego wyświetlania repeaterów kontaktowych
	const contactFieldsStyle = `
        <style>
            /* Kompaktowe style dla repeaterów kontaktowych */
            .acf-repeater[data-name="person_emails"] .acf-row,
            .acf-repeater[data-name="person_phones"] .acf-row,
            .acf-repeater[data-name="company_emails"] .acf-row,
            .acf-repeater[data-name="company_phones"] .acf-row {
                background: #fff;
                border: 1px solid #e1e5e9;
                border-radius: 6px;
                margin-bottom: 8px;
                padding: 12px;
                display: grid;
                grid-template-columns: 2fr 1fr auto auto;
                gap: 8px;
                align-items: center;
                transition: all 0.2s ease;
                position: relative;
            }
            
            .acf-repeater[data-name="person_emails"] .acf-row:hover,
            .acf-repeater[data-name="person_phones"] .acf-row:hover,
            .acf-repeater[data-name="company_emails"] .acf-row:hover,
            .acf-repeater[data-name="company_phones"] .acf-row:hover {
                border-color: #d0d5dd;
                box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
            }
            
            /* Podświetl rząd z głównym kontaktem */
            .acf-repeater .acf-row.has-primary-contact {
                background: #f0f6fc !important;
                border-color: #2271b1 !important;
                box-shadow: 0 2px 8px rgba(34, 113, 177, 0.15) !important;
            }
            
            /* Kompaktowe style dla pól w rzędzie */
            .acf-repeater[data-name*="emails"] .acf-field,
            .acf-repeater[data-name*="phones"] .acf-field {
                margin-bottom: 0 !important;
            }
            
            /* Ukryj etykiety w rzędach repeatera dla kompaktowości */
            .acf-repeater[data-name*="emails"] .acf-field .acf-label,
            .acf-repeater[data-name*="phones"] .acf-field .acf-label {
                display: none;
            }
            
            /* Style dla poszczególnych pól */
            .acf-repeater[data-name*="emails"] .acf-field[data-name="email_address"],
            .acf-repeater[data-name*="phones"] .acf-field[data-name="phone_number"] {
                grid-column: 1;
            }
            
            .acf-repeater[data-name*="emails"] .acf-field[data-name="email_type"],
            .acf-repeater[data-name*="phones"] .acf-field[data-name="phone_type"] {
                grid-column: 2;
            }
            
            .acf-repeater[data-name*="emails"] .acf-field[data-name="is_primary"],
            .acf-repeater[data-name*="phones"] .acf-field[data-name="is_primary"] {
                grid-column: 3;
                justify-self: center;
                text-align: center;
                min-width: 60px;
            }
            
            .acf-repeater .acf-row-handle.remove {
                grid-column: 4;
                justify-self: end;
            }
            
            /* Style dla pól input */
            .acf-repeater[data-name*="emails"] .acf-field input,
            .acf-repeater[data-name*="phones"] .acf-field input {
                border: 1px solid #8c8f94;
                border-radius: 4px;
                padding: 6px 10px;
                font-size: 13px;
                width: 100%;
                transition: border-color 0.15s ease-in-out;
            }
            
            .acf-repeater[data-name*="emails"] .acf-field input:focus,
            .acf-repeater[data-name*="phones"] .acf-field input:focus {
                border-color: #2271b1;
                box-shadow: 0 0 0 1px #2271b1;
                outline: 2px solid transparent;
            }
            
            /* Styl dla checkbox głównego kontaktu - bardziej kompaktowy */
            .acf-repeater .acf-field[data-name="is_primary"] .acf-label {
                font-size: 10px;
                font-weight: 600;
                color: #646970;
                margin-bottom: 2px;
                display: block !important;
                text-align: center;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            
            .acf-repeater .acf-field[data-name="is_primary"] .acf-input {
                text-align: center;
            }
            
            .acf-repeater .acf-field[data-name="is_primary"] input[type="checkbox"] {
                margin: 0;
                transform: scale(1.1);
            }
            
            /* Styl dla przycisków dodawania/usuwania */
            .acf-repeater .acf-actions {
                margin-top: 8px;
                padding: 0;
            }
            
            .acf-repeater .acf-button {
                font-size: 12px;
                padding: 6px 12px;
                border-radius: 4px;
                background: #f6f7f7;
                border-color: #dcdcde;
                color: #50575e;
                transition: all 0.2s ease;
            }
            
            .acf-repeater .acf-button:hover {
                background: #f0f0f1;
                border-color: #8c8f94;
                transform: translateY(-1px);
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            }
            
            /* Handle usuwania rzędu */
            .acf-repeater .acf-row-handle.remove {
                width: 24px;
                height: 24px;
                background: #dc3232;
                border-radius: 50%;
                cursor: pointer;
                transition: all 0.2s ease;
                display: flex;
                align-items: center;
                justify-content: center;
                border: none;
                color: #fff;
                font-size: 14px;
                font-weight: bold;
            }
            
            .acf-repeater .acf-row-handle.remove:hover {
                background: #a00;
                transform: scale(1.1);
            }
            
            .acf-repeater .acf-row-handle.remove:before {
                content: "×";
            }
            
            /* Responsywność dla mniejszych ekranów */
            @media (max-width: 782px) {
                .acf-repeater[data-name*="emails"] .acf-row,
                .acf-repeater[data-name*="phones"] .acf-row {
                    grid-template-columns: 1fr;
                    grid-template-rows: auto auto auto auto;
                    gap: 8px;
                    align-items: stretch;
                }
                
                .acf-repeater[data-name*="emails"] .acf-field[data-name="email_address"],
                .acf-repeater[data-name*="phones"] .acf-field[data-name="phone_number"],
                .acf-repeater[data-name*="emails"] .acf-field[data-name="email_type"],
                .acf-repeater[data-name*="phones"] .acf-field[data-name="phone_type"],
                .acf-repeater[data-name*="emails"] .acf-field[data-name="is_primary"],
                .acf-repeater[data-name*="phones"] .acf-field[data-name="is_primary"],
                .acf-repeater .acf-row-handle.remove {
                    grid-column: 1;
                    justify-self: stretch;
                }
                
                .acf-repeater .acf-row-handle.remove {
                    justify-self: end;
                    width: 32px;
                    height: 32px;
                }
            }
            
            /* Style dla nagłówków repeaterów */
            .acf-field[data-name="person_emails"] > .acf-label,
            .acf-field[data-name="company_emails"] > .acf-label,
            .acf-field[data-name="person_phones"] > .acf-label,
            .acf-field[data-name="company_phones"] > .acf-label {
                font-weight: 600;
                margin-bottom: 8px;
                display: flex;
                align-items: center;
                gap: 6px;
                font-size: 14px;
                color: #1d2327;
            }
            
            /* Ikony dla nagłówków repeaterów */
            .acf-field[data-name="person_emails"] > .acf-label:before,
            .acf-field[data-name="company_emails"] > .acf-label:before {
                content: "✉";
                color: #2271b1;
                font-size: 14px;
            }
            
            .acf-field[data-name="person_phones"] > .acf-label:before,
            .acf-field[data-name="company_phones"] > .acf-label:before {
                content: "☎";
                color: #2271b1;
                font-size: 14px;
            }
            
            /* Ukryj sortowanie dla prostszego interfejsu */
            .acf-repeater .acf-row-handle.order {
                display: none;
            }
        </style>
    `;

	$('head').append(contactFieldsStyle);

	// Pokaż powiadomienie przy próbie zaznaczenia drugiego głównego kontaktu
	$(document).on('change', '[data-name*="emails"] input[data-name="is_primary"], [data-name*="phones"] input[data-name="is_primary"]', function () {
		if ($(this).is(':checked')) {
			const fieldType = $(this).closest('[data-name*="emails"]').length ? 'e-mail' : 'telefon';
			const otherChecked = $(this).closest('.acf-repeater').find('input[data-name="is_primary"]:checked').not(this);

			if (otherChecked.length > 0) {
				// Pokaż krótkie powiadomienie
				const notice = $(`<div class="notice notice-info is-dismissible" style="margin: 10px 0;"><p>Oznaczono nowy główny ${fieldType}. Poprzedni został automatycznie odznaczony.</p></div>`);
				$(this).closest('.acf-field').before(notice);

				// Usuń powiadomienie po 3 sekundach
				setTimeout(() => {
					notice.fadeOut(() => notice.remove());
				}, 3000);
			}
		}
	});
});
