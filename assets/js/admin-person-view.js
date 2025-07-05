jQuery(document).ready(function ($) {
	// Sprawdzamy, czy na stronie istnieje element #company_search_select
	if ($('#company_search_select').length) {

		$('#company_search_select').select2({
			width: '100%',
			placeholder: 'Wyszukaj lub dodaj firmę',
			allowClear: true,
			// Opcja 'tags' pozwala na dodawanie nowych, nieistniejących pozycji
			tags: true,

			// Konfiguracja AJAX
			ajax: {
				url: ajaxurl, // Standardowa zmienna globalna WordPressa w panelu admina
				dataType: 'json',
				delay: 250, // Opóźnienie po wpisaniu tekstu przed wysłaniem zapytania

				// Przygotowanie danych do wysłania
				data: function (params) {
					return {
						action: 'wpmzf_search_companies', // Nazwa naszej akcji w PHP
						security: $('#wpmzf_security').val(), // Nonce dla bezpieczeństwa
						term: params.term // Wpisany przez użytkownika tekst
					};
				},

				// Przetwarzanie otrzymanej odpowiedzi
				processResults: function (data, params) {
					// Sprawdzamy, czy serwer zwrócił sukces i dane
					if (data.success && Array.isArray(data.data)) {
						return {
							results: data.data
						};
					}
					return {
						results: []
					};
				},
				cache: true
			},

			// Ustawia minimalną długość tekstu, po której rozpocznie się wyszukiwanie
			minimumInputLength: 2,

			// Tłumaczenia interfejsu Select2 na polski
			language: {
				inputTooShort: function (args) {
					var remainingChars = args.minimum - args.input.length;
					return 'Wpisz jeszcze ' + remainingChars + ' znaki';
				},
				loadingMore: function () {
					return 'Wczytywanie wyników…';
				},
				noResults: function () {
					return 'Nie znaleziono firmy. Wpisz pełną nazwę, aby ją dodać.';
				},
				searching: function () {
					return 'Szukanie…';
				}
			},

			// Ta funkcja pozwala na ładne sformatowanie nowo dodawanej etykiety
			createTag: function (params) {
				return {
					id: params.term,
					text: params.term + " (nowa firma)",
					newTag: true
				}
			}
		});
	}
});

jQuery(document).ready(function ($) {
	// --- Zmienne ---
	const personId = $('input[name="person_id"]').val();
	const securityNonce = $('#wpmzf_security').val();
	const form = $('#wpmzf-add-activity-form');
	const timelineContainer = $('#wpmzf-activity-timeline');
	const submitButton = $('#wpmzf-submit-activity-btn');
	const dateField = $('#wpmzf-activity-date');
	const attachFileBtn = $('#wpmzf-attach-file-btn');
	const attachmentInput = $('#wpmzf-activity-files-input'); // Upewnij się, że ID jest poprawne
	const attachmentsPreviewContainer = $('#wpmzf-attachments-preview'); // Upewnij się, że ID jest poprawne

	let filesToUpload = [];
	let linkMetadataCache = new Map();

	// Dodanie zmiennych dla drag & drop i clipboard
	let dragDropEnabled = false;
	let pendingUploads = new Set(); // Tracking pending uploads for cleanup

	// --- Inicjalizacja ---
	function setDefaultDateTime() {
		const now = new Date();
		now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
		dateField.val(now.toISOString().slice(0, 16));
	}

	setDefaultDateTime();
	loadActivities();

	// --- Podgląd na żywo dla textarea ---
	let previewTimeout;
	const activityContentTextarea = $('#wpmzf-activity-content');
	let previewContainer = null;

	// Utwórz kontener podglądu jeśli nie istnieje
	function createPreviewContainer() {
		if (!previewContainer) {
			previewContainer = $('<div id="wpmzf-activity-preview" style="margin-top: 10px; padding: 10px; border: 1px solid #dcdcde; border-radius: 4px; background: #f9f9f9; min-height: 50px; display: none;"><div class="preview-label" style="font-size: 12px; color: #646970; margin-bottom: 8px;">Podgląd:</div><div class="preview-content"></div></div>');
			activityContentTextarea.after(previewContainer);
		}
	}

	// Obsługa wpisywania w textarea
	activityContentTextarea.on('input', function () {
		const content = $(this).val().trim();

		clearTimeout(previewTimeout);

		if (content === '') {
			if (previewContainer) {
				previewContainer.hide();
			}
			return;
		}

		// Sprawdź czy tekst zawiera linki
		const urlRegex = /(https?:\/\/[^\s<>"]+)/gi;
		const hasLinks = urlRegex.test(content);

		if (!hasLinks) {
			if (previewContainer) {
				previewContainer.hide();
			}
			return;
		}

		createPreviewContainer();
		previewContainer.show();
		previewContainer.find('.preview-content').html('<p style="color: #646970; font-style: italic;">Generowanie podglądu...</p>');

		// Debounce - czekaj 1 sekundę po zakończeniu pisania
		previewTimeout = setTimeout(async function () {
			try {
				const processedContent = await processRichLinks(content);
				previewContainer.find('.preview-content').html(processedContent);
			} catch (error) {
				console.error('Błąd podczas generowania podglądu:', error);
				previewContainer.find('.preview-content').html('<p style="color: #d63638;">Błąd podczas generowania podglądu linków.</p>');
			}
		}, 1000);
	});

	// --- Funkcje dla bogatych kart z linkami ---
	/**
	 * Wykrywa linki w tekście i zamienia je na bogate karty
	 */
	async function processRichLinks(content) {
		// Regex do wykrywania URL-i
		const urlRegex = /(https?:\/\/[^\s<>"]+)/gi;
		const urls = content.match(urlRegex);

		if (!urls) {
			return content.replace(/\n/g, '<br>');
		}

		let processedContent = content;
		const linkCards = new Map();

		// Pobierz metadane dla wszystkich unikalnych URL-i
		const uniqueUrls = [...new Set(urls)];
		for (const url of uniqueUrls) {
			try {
				const metadata = await getLinkMetadata(url);
				if (metadata) {
					const cardHtml = createRichLinkCard(metadata);
					linkCards.set(url, cardHtml);
				}
			} catch (error) {
				console.log('Błąd pobierania metadanych dla:', url, error);
				// W przypadku błędu zostaw zwykły link
				linkCards.set(url, `<a href="${url}" target="_blank">${url}</a>`);
			}
		}

		// Zamień wszystkie URL-e na bogate karty lub zwykłe linki
		processedContent = processedContent.replace(urlRegex, (match) => {
			return linkCards.get(match) || `<a href="${match}" target="_blank">${match}</a>`;
		});

		return processedContent.replace(/\n/g, '<br>');
	}

	/**
	 * Pobiera metadane linku z cache lub serwera
	 */
	async function getLinkMetadata(url) {
		// Sprawdź cache
		if (linkMetadataCache.has(url)) {
			return linkMetadataCache.get(url);
		}

		return new Promise((resolve, reject) => {
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'wpmzf_get_link_metadata',
					security: securityNonce,
					url: url
				},
				success: function (response) {
					if (response.success) {
						linkMetadataCache.set(url, response.data);
						resolve(response.data);
					} else {
						reject(new Error(response.data.message));
					}
				},
				error: function () {
					reject(new Error('Błąd serwera'));
				}
			});
		});
	}

	/**
	 * Tworzy HTML dla bogatej karty linku - wersja inline
	 */
	function createRichLinkCard(metadata) {
		const faviconHtml = metadata.favicon ?
			`<img src="${metadata.favicon}" alt="" class="rich-link-inline-favicon" onerror="this.style.display='none'">` :
			'<span class="dashicons dashicons-admin-links rich-link-inline-favicon"></span>';

		// Skróć tytuł jeśli jest za długi
		const shortTitle = metadata.title.length > 50 ?
			metadata.title.substring(0, 47) + '...' :
			metadata.title;

		return `<a href="${metadata.url}" target="_blank" class="rich-link-inline" title="${escapeHtml(metadata.description || metadata.title)}">${faviconHtml}<span class="rich-link-inline-title">${escapeHtml(shortTitle)}</span></a>`;
	}

	/**
	 * Escape HTML do bezpiecznego wyświetlania
	 */
	function escapeHtml(text) {
		const div = document.createElement('div');
		div.textContent = text;
		return div.innerHTML;
	}

	// --- Obsługa plików ---
	attachFileBtn.on('click', function () {
		attachmentInput.click();
	});

	attachmentInput.on('change', function (e) {
		for (const file of e.target.files) {
			filesToUpload.push(file);
		}
		renderAttachmentsPreview();
		// Resetowanie wartości inputu, aby umożliwić ponowne dodanie tego samego pliku
		$(this).val('');
	});

	// Obsługa usuwania załączników z podglądu
	attachmentsPreviewContainer.on('click', '.remove-attachment', function () {
		const index = $(this).closest('.attachment-item').data('file-index');
		filesToUpload.splice(index, 1);
		renderAttachmentsPreview();
	});

	// === FUNKCJONALNOŚĆ DRAG & DROP ===

	// Inicjalizacja drag & drop na całej stronie
	function initializeDragAndDrop() {
		const $body = $('body');
		const $addActivityForm = $('#wpmzf-add-activity-form');

		// Dodaj overlay dla drag & drop
		if (!$('#wpmzf-drag-overlay').length) {
			$body.append(`
				<div id="wpmzf-drag-overlay" class="wpmzf-drag-overlay">
					<div class="wpmzf-drag-message">
						<div class="wpmzf-drag-icon">📁</div>
						<div class="wpmzf-drag-text">Upuść pliki tutaj, aby dodać do aktywności</div>
					</div>
				</div>
			`);
		}

		// Zmienne dla śledzenia drag & drop
		let dragCounter = 0;

		// Obsługa dragenter na całej stronie
		$body.on('dragenter', function (e) {
			e.preventDefault();
			dragCounter++;

			// Sprawdź czy przeciągane są pliki
			if (e.originalEvent.dataTransfer.types.includes('Files')) {
				$('#wpmzf-drag-overlay').addClass('active');
				$addActivityForm.addClass('drag-target');
			}
		});

		// Obsługa dragleave
		$body.on('dragleave', function (e) {
			e.preventDefault();
			dragCounter--;

			if (dragCounter === 0) {
				$('#wpmzf-drag-overlay').removeClass('active');
				$addActivityForm.removeClass('drag-target');
			}
		});

		// Obsługa dragover
		$body.on('dragover', function (e) {
			e.preventDefault();
		});

		// Obsługa drop
		$body.on('drop', function (e) {
			e.preventDefault();
			dragCounter = 0;

			$('#wpmzf-drag-overlay').removeClass('active');
			$addActivityForm.removeClass('drag-target');

			const files = e.originalEvent.dataTransfer.files;
			if (files.length > 0) {
				// Dodaj pliki do listy i pokaż podgląd
				for (const file of files) {
					// Sprawdź czy to plik graficzny lub inny dozwolony typ
					if (isAllowedFileType(file)) {
						filesToUpload.push(file);
					}
				}
				renderAttachmentsPreview();

				// Przewiń do formularza aktywności
				$addActivityForm[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
			}
		});
	}

	// === FUNKCJONALNOŚĆ CLIPBOARD (CTRL+V) ===

	function initializeClipboardPaste() {
		$(document).on('paste', function (e) {
			// Sprawdź czy jesteśmy na stronie z formularzem aktywności
			if (!$('#wpmzf-add-activity-form').length) {
				return;
			}

			const clipboardData = e.originalEvent.clipboardData;
			if (!clipboardData || !clipboardData.items) {
				return;
			}

			// Sprawdź czy w schowku są pliki
			for (let i = 0; i < clipboardData.items.length; i++) {
				const item = clipboardData.items[i];

				if (item.type.indexOf('image/') === 0) {
					e.preventDefault();

					const file = item.getAsFile();
					if (file) {
						// Utwórz lepszą nazwę dla screenshot'u
						const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
						const newFile = new File([file], `screenshot-${timestamp}.png`, {
							type: file.type,
							lastModified: Date.now()
						});

						filesToUpload.push(newFile);
						renderAttachmentsPreview();

						// Przewiń do formularza aktywności
						$('#wpmzf-add-activity-form')[0].scrollIntoView({
							behavior: 'smooth',
							block: 'center'
						});

						// Pokaż powiadomienie
						showNotification('Zdjęcie zostało dodane ze schowka', 'success');
					}
				}
			}
		});
	}

	// Funkcja sprawdzająca dozwolone typy plików
	function isAllowedFileType(file) {
		const allowedTypes = [
			'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp',
			'application/pdf', 'text/plain', 'application/msword',
			'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'application/vnd.ms-excel',
			'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
		];

		const maxSize = 10 * 1024 * 1024; // 10MB

		if (!allowedTypes.includes(file.type)) {
			showNotification(`Typ pliku ${file.type} nie jest dozwolony`, 'error');
			return false;
		}

		if (file.size > maxSize) {
			showNotification(`Plik ${file.name} jest za duży (maksymalnie 10MB)`, 'error');
			return false;
		}

		return true;
	}

	// === SYSTEM OCZYSZCZANIA NIEOPRISWANYCH ZAŁĄCZNIKÓW ===

	function initializeCleanupSystem() {
		// Oczyszczanie przy wyjściu ze strony
		$(window).on('beforeunload', function () {
			if (pendingUploads.size > 0) {
				// Wyślij żądanie oczyszczenia (nie czekaj na odpowiedź)
				navigator.sendBeacon(ajaxurl, new URLSearchParams({
					action: 'cleanup_wpmzf_orphaned_attachments',
					security: securityNonce,
					attachment_ids: Array.from(pendingUploads).join(',')
				}));
			}
		});

		// Oczyszczanie co 5 minut dla długich sesji
		setInterval(function () {
			if (pendingUploads.size > 0) {
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'cleanup_wpmzf_orphaned_attachments',
						security: securityNonce,
						attachment_ids: Array.from(pendingUploads).join(',')
					}
				});
				pendingUploads.clear();
			}
		}, 5 * 60 * 1000); // 5 minut
	}

	// Ulepszona funkcja renderowania podglądu załączników z paskiem postępu
	function renderAttachmentsPreview() {
		attachmentsPreviewContainer.html('');
		if (filesToUpload.length === 0) {
			attachmentsPreviewContainer.hide();
			return;
		}

		attachmentsPreviewContainer.show();
		filesToUpload.forEach((file, index) => {
			const isImage = file.type.startsWith('image/');
			const fileSize = formatFileSize(file.size);

			let thumbnailHtml = '';
			if (isImage) {
				const reader = new FileReader();
				reader.onload = function (e) {
					$(`.attachment-item[data-file-index="${index}"] .attachment-thumbnail`)
						.html(`<img src="${e.target.result}" alt="${file.name}">`);
				};
				reader.readAsDataURL(file);
				thumbnailHtml = '<div class="attachment-thumbnail"><div class="thumbnail-placeholder">📷</div></div>';
			} else {
				thumbnailHtml = '<div class="attachment-thumbnail"><div class="file-icon">📄</div></div>';
			}

			const filePreviewHtml = `
                <div class="attachment-item" data-file-index="${index}">
                    ${thumbnailHtml}
                    <div class="attachment-info">
                        <div class="attachment-name" title="${file.name}">${file.name}</div>
                        <div class="attachment-size">${fileSize}</div>
                        <div class="attachment-progress" style="display: none;">
                            <div class="attachment-progress-bar"></div>
                            <div class="attachment-progress-text">0%</div>
                        </div>
                    </div>
                    <div class="attachment-actions">
                         <span class="dashicons dashicons-no-alt remove-attachment" title="Usuń plik"></span>
                    </div>
                </div>
            `;
			attachmentsPreviewContainer.append(filePreviewHtml);
		});
	}

	// Funkcja formatowania rozmiaru pliku
	function formatFileSize(bytes) {
		if (bytes === 0) return '0 Bytes';
		const k = 1024;
		const sizes = ['Bytes', 'KB', 'MB', 'GB'];
		const i = Math.floor(Math.log(bytes) / Math.log(k));
		return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
	}

	// Funkcja pokazywania powiadomień
	function showNotification(message, type = 'info') {
		const notificationClass = type === 'error' ? 'notice-error' :
			type === 'success' ? 'notice-success' : 'notice-info';

		const notification = $(`
			<div class="notice ${notificationClass} is-dismissible wpmzf-notification">
				<p>${message}</p>
				<button type="button" class="notice-dismiss">
					<span class="screen-reader-text">Dismiss this notice.</span>
				</button>
			</div>
		`);

		// Dodaj powiadomienie na górze strony
		$('.wrap h1').after(notification);

		// Auto-usuń po 5 sekundach
		setTimeout(function () {
			notification.fadeOut(function () {
				$(this).remove();
			});
		}, 5000);

		// Obsługa przycisku X
		notification.on('click', '.notice-dismiss', function () {
			notification.fadeOut(function () {
				$(this).remove();
			});
		});
	}

	// Inicjalizacja wszystkich funkcji
	initializeDragAndDrop();
	initializeClipboardPaste();
	initializeCleanupSystem();

	// --- Główna funkcja do ładowania aktywności ---
	function loadActivities() {
		timelineContainer.html('<p><em>Ładowanie aktywności...</em></p>');

		$.ajax({
			url: ajaxurl,
			type: 'GET',
			data: {
				action: 'get_wpmzf_activities',
				security: securityNonce,
				person_id: personId
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

	// Funkcja pomocnicza do wybierania ikony Dashicon na podstawie typu MIME
	function getIconForMimeType(mimeType) {
		if (!mimeType) return 'dashicons-media-default';
		if (mimeType.startsWith('image/')) return 'dashicons-format-image';
		if (mimeType === 'application/pdf') return 'dashicons-pdf';
		if (mimeType.startsWith('video/')) return 'dashicons-format-video';
		if (mimeType.startsWith('audio/')) return 'dashicons-format-audio';
		if (mimeType.includes('spreadsheet') || mimeType.includes('excel')) return 'dashicons-media-spreadsheet';
		if (mimeType.includes('document') || mimeType.includes('word')) return 'dashicons-media-document';
		if (mimeType.includes('presentation') || mimeType.includes('powerpoint')) return 'dashicons-media-interactive';
		if (mimeType.includes('zip') || mimeType.includes('archive')) return 'dashicons-media-archive';
		return 'dashicons-media-default';
	}


	// --- Renderowanie osi czasu ---
	async function renderTimeline(activities) {
		if (activities.length === 0) {
			timelineContainer.html('<p><em>Brak zarejestrowanych aktywności. Dodaj pierwszą!</em></p>');
			return;
		}

		let html = '';

		// Przetwarzaj aktywności asynchronicznie
		for (const activity of activities) {
			const iconMap = { 'Notatka': 'dashicons-admin-comments', 'E-mail': 'dashicons-email-alt', 'Telefon': 'dashicons-phone', 'Spotkanie': 'dashicons-groups', 'Spotkanie online': 'dashicons-video-alt3' };
			const iconClass = iconMap[activity.type] || 'dashicons-marker';

			const date = new Date(activity.date);
			const months = ['stycznia', 'lutego', 'marca', 'kwietnia', 'maja', 'czerwca', 'lipca', 'sierpnia', 'września', 'października', 'listopada', 'grudnia'];
			const formattedDate = `${date.getDate()} ${months[date.getMonth()]} ${date.getFullYear()} ${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`;
			activity.date = formattedDate;

			let attachmentsHtml = '';
			if (activity.attachments && activity.attachments.length > 0) {
				attachmentsHtml += '<div class="timeline-attachments"><ul>';
				activity.attachments.forEach(att => {
					const attachmentIcon = getIconForMimeType(att.mime_type);
					let previewHtml = '';

					if (att.thumbnail_url) {
						previewHtml = `<img src="${att.thumbnail_url}" alt="Podgląd załącznika" style="width: 50px; height: 50px; object-fit: cover; margin-right: 10px; vertical-align: middle; border-radius: 3px;">`;
					} else {
						previewHtml = `<span class="dashicons ${attachmentIcon}" title="${att.mime_type}" style="font-size: 32px; vertical-align: middle; margin-right: 10px; width: 50px; text-align: center; display: inline-block;"></span>`;
					}

					attachmentsHtml += `
						<li data-attachment-id="${att.id}">
							<a href="${att.url}" target="_blank" style="display: inline-flex; align-items: center; text-decoration: none; color: inherit;">
							   ${previewHtml}
							   <span>${att.filename}</span>
							</a>
							<span class="dashicons dashicons-trash delete-attachment" title="Usuń załącznik"></span>
						</li>
					`;
				});
				attachmentsHtml += '</ul></div>';
			}

			// Przetwórz zawartość z bogatymi kartami linków
			const processedContent = await processRichLinks(activity.content);

			html += `
				<div class="timeline-item" data-activity-id="${activity.id}">
					<div class="timeline-avatar">
						<img src="${activity.avatar}" alt="${activity.author}">
					</div>
					<div class="timeline-content">
						<div class="timeline-header">
							<div class="timeline-header-left">
								<div class="timeline-header-meta">
									<span class="dashicons ${iconClass}"></span>
									<span><strong>${activity.author}</strong> dodał(a) <strong>${activity.type}</strong></span>
								</div>
								<span class="timeline-header-date">${activity.date}</span>
							</div>
							<div class="timeline-actions">
								<span class="dashicons dashicons-edit edit-activity" title="Edytuj"></span>
								<span class="dashicons dashicons-trash delete-activity" title="Usuń"></span>
							</div>
						</div>
						<div class="timeline-body">
							<div class="activity-content-display">
								${processedContent}
							</div>
							<div class="activity-content-edit" style="display: none;">
								<textarea class="activity-edit-textarea">${activity.content}</textarea>
								<div class="timeline-edit-actions">
									<button class="button button-primary save-activity-edit">Zapisz</button>
									<button class="button cancel-activity-edit">Anuluj</button>
								</div>
							</div>
							${attachmentsHtml}
						</div>
					</div>
				</div>
			`;
		}
		timelineContainer.html(html);
	}

	// --- Obsługa formularza dodawania aktywności ---
	form.on('submit', async function (e) {
		e.preventDefault();

		const originalButtonText = submitButton.text();
		submitButton.text('Przetwarzanie...').prop('disabled', true);
		attachFileBtn.prop('disabled', true);

		let uploadedAttachmentIds = [];

		if (filesToUpload.length > 0) {
			submitButton.text('Wysyłanie plików...');
			const uploadPromises = filesToUpload.map((file, index) => {
				const formData = new FormData();
				formData.append('file', file);
				formData.append('action', 'wpmzf_upload_attachment');
				formData.append('security', securityNonce);

				const previewItem = $(`.attachment-item[data-file-index="${index}"]`);
				const progressBar = previewItem.find('.attachment-progress-bar');
				const progressText = previewItem.find('.attachment-progress-text');

				previewItem.find('.attachment-progress').show();
				previewItem.find('.remove-attachment').hide();

				return $.ajax({
					url: ajaxurl,
					type: 'POST',
					data: formData,
					processData: false,
					contentType: false,
					xhr: function () {
						const xhr = new window.XMLHttpRequest();
						xhr.upload.addEventListener('progress', function (evt) {
							if (evt.lengthComputable) {
								const percentComplete = Math.round((evt.loaded / evt.total) * 100);
								progressBar.css('width', percentComplete + '%');
								progressText.text(percentComplete + '%');
							}
						}, false);
						return xhr;
					}
				}).done(function (response) {
					if (response.success) {
						// Dodaj do śledzonych uploadów
						pendingUploads.add(response.data.id);
						progressText.text('Gotowe!');
						previewItem.addClass('upload-success');
					}
				}).fail(function () {
					progressText.text('Błąd!');
					previewItem.addClass('upload-error');
				});
			});

			try {
				const results = await Promise.all(uploadPromises);
				results.forEach(response => {
					if (response.success) {
						uploadedAttachmentIds.push(response.data.id);
					} else {
						throw new Error('Błąd wysyłania pliku: ' + response.data.message);
					}
				});
			} catch (error) {
				showNotification(error.message || 'Wystąpił błąd podczas wysyłania plików.', 'error');
				submitButton.text(originalButtonText).prop('disabled', false);
				attachFileBtn.prop('disabled', false);
				// Przywróć wygląd preview
				renderAttachmentsPreview();
				return;
			}
		}

		submitButton.text('Dodawanie aktywności...');
		const activityData = {
			action: 'add_wpmzf_activity',
			security: securityNonce,
			person_id: personId,
			content: $('#wpmzf-activity-content').val(),
			activity_type: $('#wpmzf-activity-type').val(),
			activity_date: dateField.val(),
			attachment_ids: uploadedAttachmentIds
		};

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: activityData,
			success: function (response) {
				if (response.success) {
					// Usuń przesłane pliki z pending uploads (zostały przypisane)
					uploadedAttachmentIds.forEach(id => {
						pendingUploads.delete(id);
					});

					form[0].reset();
					filesToUpload = [];
					renderAttachmentsPreview();
					setDefaultDateTime();
					loadActivities();
					showNotification('Aktywność została dodana pomyślnie!', 'success');
				} else {
					showNotification('Błąd: ' + response.data.message, 'error');
				}
			},
			error: function () {
				showNotification('Wystąpił krytyczny błąd serwera przy dodawaniu aktywności.', 'error');
			},
			complete: function () {
				submitButton.text(originalButtonText).prop('disabled', false);
				attachFileBtn.prop('disabled', false);
			}
		});
	});

	// --- Akcje na osi czasu (Delegacja zdarzeń) ---

	timelineContainer.on('click', '.delete-activity', function () {
		if (!confirm('Czy na pewno chcesz usunąć tę aktywność i wszystkie jej załączniki?')) {
			return;
		}
		const activityItem = $(this).closest('.timeline-item');
		const activityId = activityItem.data('activity-id');
		activityItem.css('opacity', '0.5');

		$.post(ajaxurl, {
			action: 'delete_wpmzf_activity',
			security: securityNonce,
			activity_id: activityId
		}).done(response => {
			if (response.success) {
				activityItem.slideUp(function () { $(this).remove(); });
			} else {
				alert('Błąd usuwania: ' + response.data.message);
				activityItem.css('opacity', '1');
			}
		}).fail(() => {
			alert('Błąd serwera podczas usuwania.');
			activityItem.css('opacity', '1');
		});
	});

	timelineContainer.on('click', '.delete-attachment', function (e) {
		e.preventDefault(); // Zapobiegaj przejściu do linku
		if (!confirm('Czy na pewno chcesz usunąć ten załącznik?')) {
			return;
		}
		const attachmentLi = $(this).closest('li');
		const activityId = $(this).closest('.timeline-item').data('activity-id');
		const attachmentId = attachmentLi.data('attachment-id');
		attachmentLi.css('opacity', '0.5');

		$.post(ajaxurl, {
			action: 'delete_wpmzf_attachment',
			security: securityNonce,
			activity_id: activityId,
			attachment_id: attachmentId
		}).done(response => {
			if (response.success) {
				attachmentLi.fadeOut(function () {
					// Sprawdź czy to był ostatni załącznik
					const ul = $(this).parent('ul');
					$(this).remove();
					if (ul.children().length === 0) {
						ul.parent('.timeline-attachments').remove();
					}
				});
			} else {
				alert('Błąd usuwania załącznika: ' + response.data.message);
				attachmentLi.css('opacity', '1');
			}
		}).fail(() => {
			alert('Błąd serwera podczas usuwania załącznika.');
			attachmentLi.css('opacity', '1');
		});
	});

	timelineContainer.on('click', '.edit-activity', function () {
		const contentDiv = $(this).closest('.timeline-content');
		const textareaElement = contentDiv.find('.activity-edit-textarea');

		contentDiv.find('.activity-content-display').hide();
		contentDiv.find('.activity-content-edit').show();
		textareaElement.trigger('focus');

		// Dodaj podgląd dla edycji jeśli nie istnieje
		let editPreviewContainer = contentDiv.find('.edit-preview-container');
		if (editPreviewContainer.length === 0) {
			editPreviewContainer = $('<div class="edit-preview-container" style="margin-top: 10px; padding: 8px; border: 1px solid #dcdcde; border-radius: 4px; background: #f9f9f9; display: none;"><div class="preview-label" style="font-size: 12px; color: #646970; margin-bottom: 8px;">Podgląd:</div><div class="preview-content"></div></div>');
			textareaElement.after(editPreviewContainer);
		}

		// Obsługa wpisywania w textarea edycji
		let editPreviewTimeout;
		textareaElement.off('input.richPreview').on('input.richPreview', function () {
			const content = $(this).val().trim();

			clearTimeout(editPreviewTimeout);

			if (content === '') {
				editPreviewContainer.hide();
				return;
			}

			// Sprawdź czy tekst zawiera linki
			const urlRegex = /(https?:\/\/[^\s<>"]+)/gi;
			const hasLinks = urlRegex.test(content);

			if (!hasLinks) {
				editPreviewContainer.hide();
				return;
			}

			editPreviewContainer.show();
			editPreviewContainer.find('.preview-content').html('<p style="color: #646970; font-style: italic;">Generowanie podglądu...</p>');

			// Debounce - czekaj 1 sekundę po zakończeniu pisania
			editPreviewTimeout = setTimeout(async function () {
				try {
					const processedContent = await processRichLinks(content);
					editPreviewContainer.find('.preview-content').html(processedContent);
				} catch (error) {
					console.error('Błąd podczas generowania podglądu:', error);
					editPreviewContainer.find('.preview-content').html('<p style="color: #d63638;">Błąd podczas generowania podglądu linków.</p>');
				}
			}, 1000);
		});
	});

	timelineContainer.on('click', '.cancel-activity-edit', function () {
		const contentDiv = $(this).closest('.timeline-content');
		contentDiv.find('.activity-content-edit').hide();
		contentDiv.find('.activity-content-display').show();

		// Ukryj podgląd edycji
		contentDiv.find('.edit-preview-container').hide();

		// Pobierz oryginalną zawartość z textarea (bez konwersji HTML)
		const textareaElement = contentDiv.find('.activity-edit-textarea');
		// Nie robimy konwersji z HTML z powrotem na tekst, bo textarea zawiera oryginalny tekst
		// Resetujemy tylko jeśli to konieczne
	});

	timelineContainer.on('click', '.save-activity-edit', async function () {
		const button = $(this);
		const contentDiv = button.closest('.timeline-content');
		const activityId = button.closest('.timeline-item').data('activity-id');
		const newContent = contentDiv.find('.activity-edit-textarea').val();

		button.text('Zapisywanie...').prop('disabled', true);

		$.post(ajaxurl, {
			action: 'update_wpmzf_activity',
			security: securityNonce,
			activity_id: activityId,
			content: newContent
		}).done(async function (response) {
			if (response.success) {
				// Przetwórz zawartość z bogatymi kartami linków
				const processedContent = await processRichLinks(newContent);
				contentDiv.find('.activity-content-display').html(processedContent);
				contentDiv.find('.activity-content-edit').hide();
				contentDiv.find('.activity-content-display').show();
			} else {
				alert('Błąd zapisu: ' + response.data.message);
			}
		}).fail(() => {
			alert('Błąd serwera podczas zapisu.');
		}).always(() => {
			button.text('Zapisz').prop('disabled', false);
		});

	});
	// --- Edycja danych podstawowych osoby ---
	const basicDataBox = $('#dossier-basic-data');
	const viewMode = basicDataBox.find('.view-mode');
	const editMode = basicDataBox.find('.edit-form');

	basicDataBox.on('click', '#edit-basic-data', function (e) {
		e.preventDefault();
		const isEditing = editMode.is(':visible');
		if (isEditing) {
			// Zamknij tryb edycji
			editMode.hide();
			viewMode.show();
		} else {
			// Otwórz tryb edycji
			viewMode.hide();
			editMode.show();
		}
	});

	basicDataBox.on('click', '#cancel-edit-basic-data', function (e) {
		e.preventDefault();
		// Zamknij tryb edycji i przełącz na widok
		editMode.hide();
		viewMode.show();
	});

	editMode.on('submit', 'form', function (e) {
		e.preventDefault();
		const form = $(this);
		const spinner = form.find('.spinner');
		const submitButton = form.find('button[type="submit"]');

		spinner.addClass('is-active');
		submitButton.prop('disabled', true);

		const formData = {
			action: 'wpmzf_update_person_details',
			security: securityNonce,
			person_id: personId,
			person_name: form.find('#person_name').val(),
			person_position: form.find('#person_position').val(),
			person_email: form.find('#person_email').val(),
			person_phone: form.find('#person_phone').val(),
			person_company: $('#company_search_select').val(),
			person_street: form.find('#person_street').val(),
			person_postal_code: form.find('#person_postal_code').val(),
			person_city: form.find('#person_city').val(),
			person_status: form.find('#person_status').val(),
		};

		console.log('Form data to submit:', formData);

		$.post(ajaxurl, formData)
			.done(function (response) {
				if (response.success) {
					// Aktualizacja danych w trybie widoku
					viewMode.find('span[data-field="person_name"]').text(formData.person_name);
					$('h1.wp-heading-inline').text(formData.person_name); // Aktualizacja głównego nagłówka
					viewMode.find('span[data-field="person_position"]').text(formData.person_position || 'Brak');

					// Aktualizacja firmy
					const companySpan = viewMode.find('span[data-field="person_company"]');
					if (response.data.company_html) {
						companySpan.html(response.data.company_html);
					} else {
						companySpan.text('Brak');
					}

					const emailLink = viewMode.find('a[data-field="person_email"]');
					emailLink.text(formData.person_email || 'Brak');
					emailLink.attr('href', 'mailto:' + formData.person_email);

					viewMode.find('span[data-field="person_phone"]').text(formData.person_phone || 'Brak');

					const addressParts = [formData.person_street, formData.person_postal_code, formData.person_city];
					const address = addressParts.filter(Boolean).join(', ');
					viewMode.find('span[data-field="person_address"]').text(address || 'Brak');

					const statusText = form.find('#person_status option:selected').text();
					viewMode.find('span[data-field="person_status"]').text(statusText || 'Brak');

					// Przełączenie widoku
					editMode.hide();
					viewMode.show();
				} else {
					alert('Błąd zapisu: ' + response.data.message);
				}
			})
			.fail(function () {
				alert('Wystąpił błąd serwera.');
			})
			.always(function () {
				spinner.removeClass('is-active');
				submitButton.prop('disabled', false);
			});

	});
});

// === FUNKCJONALNOŚĆ ZADAŃ ===
jQuery(document).ready(function ($) {
	// Zmienne dla zadań
	const personId = $('input[name="person_id"]').val();
	const taskForm = $('#wpmzf-add-task-form');
	const taskTitleInput = $('#wpmzf-task-title');
	const openTasksList = $('#wpmzf-open-tasks-list');
	const closedTasksList = $('#wpmzf-closed-tasks-list');
	const toggleClosedTasks = $('#wpmzf-toggle-closed-tasks');
	const taskSecurityNonce = $('#wpmzf_task_security').val();

	// Inicjalizacja
	if (personId) {
		loadTasks();
	}

	// === DODAWANIE ZADANIA ===
	taskForm.on('submit', function (e) {
		e.preventDefault();

		const taskTitle = taskTitleInput.val().trim();
		if (!taskTitle) {
			alert('Proszę wpisać treść zadania.');
			return;
		}

		const submitButton = taskForm.find('button[type="submit"]');
		submitButton.prop('disabled', true).text('Dodawanie...');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'add_wpmzf_task',
				wpmzf_task_security: taskSecurityNonce,
				person_id: personId,
				task_title: taskTitle
			},
			dataType: 'json'
		})
			.done(function (response) {
				if (response.success) {
					taskTitleInput.val(''); // Wyczyść pole
					loadTasks(); // Odśwież listę zadań

					// Pokaż komunikat sukcesu
					showTaskMessage('Zadanie zostało dodane pomyślnie.', 'success');
				} else {
					showTaskMessage(response.data || 'Wystąpił błąd podczas dodawania zadania.', 'error');
				}
			})
			.fail(function () {
				showTaskMessage('Wystąpił błąd serwera.', 'error');
			})
			.always(function () {
				submitButton.prop('disabled', false).text('Dodaj');
			});
	});

	// Dodawanie zadania przez Enter
	taskTitleInput.on('keypress', function (e) {
		if (e.which === 13) { // Enter
			e.preventDefault();
			taskForm.submit();
		}
	});

	// === ŁADOWANIE ZADAŃ ===
	function loadTasks() {
		// Wyświetl komunikat ładowania
		openTasksList.html('<p><em>Ładowanie zadań...</em></p>');
		closedTasksList.html('<p><em>Ładowanie zakończonych zadań...</em></p>');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'get_wpmzf_tasks',
				wpmzf_task_security: taskSecurityNonce,
				person_id: personId
			},
			dataType: 'json'
		})
			.done(function (response) {
				if (response.success && response.data) {
					renderTasks(response.data.open_tasks || [], response.data.closed_tasks || []);
				} else {
					openTasksList.html('<p><em>Brak otwartych zadań.</em></p>');
					closedTasksList.html('<p><em>Brak zakończonych zadań.</em></p>');
				}
			})
			.fail(function () {
				openTasksList.html('<p><em>Błąd podczas ładowania zadań.</em></p>');
				closedTasksList.html('<p><em>Błąd podczas ładowania zadań.</em></p>');
			});
	}

	// === RENDEROWANIE ZADAŃ ===
	function renderTasks(openTasks, closedTasks) {
		// Renderuj otwarte zadania
		if (openTasks.length === 0) {
			openTasksList.html('<p><em>Brak otwartych zadań.</em></p>');
		} else {
			let openTasksHtml = '';
			openTasks.forEach(function (task) {
				openTasksHtml += renderTaskItem(task);
			});
			openTasksList.html(openTasksHtml);
		}

		// Renderuj zamknięte zadania
		if (closedTasks.length === 0) {
			closedTasksList.html('<p><em>Brak zakończonych zadań.</em></p>');
		} else {
			let closedTasksHtml = '';
			closedTasks.forEach(function (task) {
				closedTasksHtml += renderTaskItem(task);
			});
			closedTasksList.html(closedTasksHtml);
		}
	}

	// === RENDEROWANIE POJEDYNCZEGO ZADANIA ===
	function renderTaskItem(task) {
		const taskClass = getTaskClass(task);
		const taskDate = formatTaskDate(task.due_date);
		const statusLabel = getStatusLabel(task.status);

		return `
			<div class="task-item ${taskClass}" data-task-id="${task.id}">
				<div class="task-header">
					<div class="task-title">${escapeHtml(task.title)}</div>
					<div class="task-actions">
						${task.status !== 'Zrobione' ?
				`<span class="dashicons dashicons-yes-alt" title="Oznacz jako zrobione" data-action="complete"></span>` :
				`<span class="dashicons dashicons-undo" title="Oznacz jako do zrobienia" data-action="reopen"></span>`
			}
						<span class="dashicons dashicons-trash" title="Usuń zadanie" data-action="delete"></span>
						<span class="dashicons dashicons-edit" title="Edytuj zadanie" data-action="edit"></span>
					</div>
				</div>
				<div class="task-meta">
					<span class="task-status ${task.status.toLowerCase().replace(/\s+/g, '-')}">${statusLabel}</span>
					${taskDate ? `<span class="task-date">${taskDate}</span>` : ''}
				</div>
			</div>
		`;
	}

	// === POMOCNICZE FUNKCJE ===
	function getTaskClass(task) {
		if (task.status === 'Zrobione') return 'completed';

		if (task.due_date) {
			const today = new Date();
			const dueDate = new Date(task.due_date);

			// Ustaw godzinę na początek dnia dla porównania
			today.setHours(0, 0, 0, 0);
			dueDate.setHours(0, 0, 0, 0);

			if (dueDate < today) return 'overdue';
			if (dueDate.getTime() === today.getTime()) return 'today';
			return 'upcoming';
		}

		return '';
	}

	function formatTaskDate(dateString) {
		if (!dateString) return null;

		const date = new Date(dateString);
		return date.toLocaleDateString('pl-PL', {
			year: 'numeric',
			month: '2-digit',
			day: '2-digit'
		});
	}

	function getStatusLabel(status) {
		const statusLabels = {
			'Do zrobienia': 'Do zrobienia',
			'W toku': 'W toku',
			'Zrobione': 'Zrobione'
		};
		return statusLabels[status] || status;
	}

	function escapeHtml(text) {
		const map = {
			'&': '&amp;',
			'<': '&lt;',
			'>': '&gt;',
			'"': '&quot;',
			"'": '&#039;'
		};
		return text.replace(/[&<>"']/g, function (m) { return map[m]; });
	}

	function showTaskMessage(message, type) {
		// Usuń poprzednie komunikaty
		$('.task-message').remove();

		const messageClass = type === 'success' ? 'notice-success' : 'notice-error';
		const messageHtml = `<div class="task-message notice ${messageClass} is-dismissible" style="margin: 10px 0;"><p>${message}</p></div>`;

		taskForm.after(messageHtml);

		// Usuń komunikat po 5 sekundach
		setTimeout(function () {
			$('.task-message').fadeOut(function () {
				$(this).remove();
			});
		}, 5000);
	}

	// === OBSŁUGA AKCJI ZADAŃ ===
	$(document).on('click', '.task-actions .dashicons', function (e) {
		e.preventDefault();

		const $this = $(this);
		const action = $this.data('action');
		const taskId = $this.closest('.task-item').data('task-id');

		if (!taskId) return;

		switch (action) {
			case 'complete':
				updateTaskStatus(taskId, 'Zrobione');
				break;
			case 'reopen':
				updateTaskStatus(taskId, 'Do zrobienia');
				break;
			case 'delete':
				if (confirm('Czy na pewno chcesz usunąć to zadanie?')) {
					deleteTask(taskId);
				}
				break;
			case 'edit':
				editTask(taskId);
				break;
		}
	});

	// === AKTUALIZACJA STATUSU ZADANIA ===
	function updateTaskStatus(taskId, newStatus) {
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'update_wpmzf_task_status',
				wpmzf_task_security: taskSecurityNonce,
				task_id: taskId,
				status: newStatus
			},
			dataType: 'json'
		})
			.done(function (response) {
				if (response.success) {
					loadTasks(); // Odśwież listę zadań
					showTaskMessage('Status zadania został zaktualizowany.', 'success');
				} else {
					showTaskMessage(response.data || 'Wystąpił błąd podczas aktualizacji statusu.', 'error');
				}
			})
			.fail(function () {
				showTaskMessage('Wystąpił błąd serwera.', 'error');
			});
	}

	// === USUWANIE ZADANIA ===
	function deleteTask(taskId) {
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'delete_wpmzf_task',
				wpmzf_task_security: taskSecurityNonce,
				task_id: taskId
			},
			dataType: 'json'
		})
			.done(function (response) {
				if (response.success) {
					loadTasks(); // Odśwież listę zadań
					showTaskMessage('Zadanie zostało usunięte.', 'success');
				} else {
					showTaskMessage(response.data || 'Wystąpił błąd podczas usuwania zadania.', 'error');
				}
			})
			.fail(function () {
				showTaskMessage('Wystąpił błąd serwera.', 'error');
			});
	}

	// === EDYCJA ZADANIA ===
	function editTask(taskId) {
		// Znajdź zadanie w DOM
		const taskItem = $(`.task-item[data-task-id="${taskId}"]`);
		const taskTitle = taskItem.find('.task-title');
		const currentTitle = taskTitle.text();

		// Zamień tytuł na pole edycji
		const editInput = `<input type="text" class="task-edit-input" value="${escapeHtml(currentTitle)}" style="width: 100%; padding: 5px; border: 1px solid #ddd; border-radius: 3px;">`;
		taskTitle.html(editInput);

		// Fokus na polu
		const input = taskTitle.find('.task-edit-input');
		input.focus().select();

		// Obsługa zapisywania (Enter) i anulowania (Escape)
		input.on('keydown', function (e) {
			if (e.which === 13) { // Enter - zapisz
				e.preventDefault();
				saveTaskTitle(taskId, input.val().trim(), taskTitle, currentTitle);
			} else if (e.which === 27) { // Escape - anuluj
				e.preventDefault();
				taskTitle.text(currentTitle);
			}
		});

		// Obsługa utraty fokusa
		input.on('blur', function () {
			const newTitle = input.val().trim();
			if (newTitle && newTitle !== currentTitle) {
				saveTaskTitle(taskId, newTitle, taskTitle, currentTitle);
			} else {
				taskTitle.text(currentTitle);
			}
		});
	}

	// === ZAPISYWANIE TYTUŁU ZADANIA ===
	function saveTaskTitle(taskId, newTitle, titleElement, originalTitle) {
		if (!newTitle) {
			titleElement.text(originalTitle);
			return;
		}

		// Pokazuj stan ładowania
		titleElement.text('Zapisywanie...');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'update_wpmzf_task_status', // Używamy tego samego endpointu
				wpmzf_task_security: taskSecurityNonce,
				task_id: taskId,
				title: newTitle
			},
			dataType: 'json'
		})
			.done(function (response) {
				if (response.success) {
					titleElement.text(newTitle);
					showTaskMessage('Tytuł zadania został zaktualizowany.', 'success');
				} else {
					titleElement.text(originalTitle);
					showTaskMessage(response.data || 'Wystąpił błąd podczas aktualizacji tytułu.', 'error');
				}
			})
			.fail(function () {
				titleElement.text(originalTitle);
				showTaskMessage('Wystąpił błąd serwera.', 'error');
			});
	}

	// === ROZWIJANIE/ZWIJANIE ZAKOŃCZONYCH ZADAŃ ===
	toggleClosedTasks.on('click', function () {
		const closedTasksContainer = closedTasksList.parent();
		const arrow = toggleClosedTasks.find('.dashicons');

		if (closedTasksList.is(':visible')) {
			closedTasksList.slideUp();
			arrow.removeClass('dashicons-arrow-down').addClass('dashicons-arrow-right');
		} else {
			closedTasksList.slideDown();
			arrow.removeClass('dashicons-arrow-right').addClass('dashicons-arrow-down');
		}
	});
});