jQuery(document).ready(function ($) {
	// --- Zmienne ---
	const contactId = $('input[name="contact_id"]').val();
	const form = $('#wpmzf-add-activity-form');
	const timelineContainer = $('#wpmzf-activity-timeline');
	const submitButton = $('#wpmzf-submit-activity-btn');
	const dateField = $('#wpmzf-activity-date');

	// --- Inicjalizacja ---

	// Ustawienie domyślnej daty i godziny na aktualną
	function setDefaultDateTime() {
		const now = new Date();
		now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
		dateField.val(now.toISOString().slice(0, 16));
	}

	setDefaultDateTime();
	loadActivities();

	// --- Główna funkcja do ładowania aktywności ---
	function loadActivities() {
		timelineContainer.html('<p><em>Ładowanie aktywności...</em></p>');

		$.ajax({
			url: ajaxurl, // globalna zmienna WordPressa
			type: 'GET',
			data: {
				action: 'get_wpmzf_activities',
				security: $('#wpmzf_security').val(),
				contact_id: contactId
			},
			success: function (response) {
				if (response.success) {
					renderTimeline(response.data);
				} else {
					timelineContainer.html('<p style="color:red;">Błąd ładowania: ' + response.data.message + '</p>');
				}
			},
			error: function () {
				timelineContainer.html('<p style="color:red;">Wystąpił krytyczny błąd serwera.</p>');
			}
		});
	}

	// --- Renderowanie osi czasu ---
	function renderTimeline(activities) {
		console.log('Renderowanie osi czasu z danymi:', activities);
		if (activities.length === 0) {
			timelineContainer.html('<p><em>Brak zarejestrowanych aktywności. Dodaj pierwszą!</em></p>');
			return;
		}

		let html = '';
		activities.forEach(activity => {
			// Mapowanie typów aktywności na ikony Dashicons
			const iconMap = {
				'Notatka': 'dashicons-admin-comments',
				'E-mail': 'dashicons-email-alt',
				'Telefon': 'dashicons-phone',
				'Spotkanie': 'dashicons-groups',
				'Spotkanie online': 'dashicons-video-alt3'
			};
			const iconClass = iconMap[activity.type] || 'dashicons-marker';

			html += `
                <div class="timeline-item">
                    <div class="timeline-avatar">
                        <img src="${activity.avatar}" alt="${activity.author}">
                    </div>
                    <div class="timeline-content">
                        <div class="timeline-header">
                            <div class="timeline-header-meta">
                                <span class="dashicons ${iconClass}"></span>
                                <span><strong>${activity.author}</strong> dodał(a) <strong>${activity.type}</strong></span>
                            </div>
                            <span class="timeline-header-date">${activity.date}</span>
                        </div>
                        <div class="timeline-body">
                            <p>${activity.content.replace(/\n/g, '<br>')}</p>
                        </div>
                    </div>
                </div>
            `;
		});
		timelineContainer.html(html);
	}

	// --- Obsługa formularza ---
	form.on('submit', function (e) {
		e.preventDefault();

		const originalButtonText = submitButton.text();
		submitButton.text('Dodawanie...').prop('disabled', true);

		const formData = {
			action: 'add_wpmzf_activity',
			security: $('#wpmzf_security').val(),
			contact_id: contactId,
			content: $('#wpmzf-activity-content').val(),
			activity_type: $('#wpmzf-activity-type').val(),
			activity_date: dateField.val()
		};

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: formData,
			success: function (response) {
				if (response.success) {
					form[0].reset(); // Resetuj formularz
					setDefaultDateTime();
					loadActivities(); // Przeładuj oś czasu
				} else {
					alert('Błąd: ' + response.data.message);
				}
			},
			error: function () {
				alert('Wystąpił krytyczny błąd serwera przy dodawaniu aktywności.');
			},
			complete: function () {
				submitButton.text(originalButtonText).prop('disabled', false);
			}
		});
	});

	// TODO: Implementacja logiki uploadu plików po kliknięciu #wpmzf-attach-file-btn
	// i dla drag-and-drop na #wpmzf-file-drop-zone.
	// To wymaga użycia WP Media Uploader i jest bardziej złożone.
});