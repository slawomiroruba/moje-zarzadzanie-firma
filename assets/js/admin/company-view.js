// === GLOBALNE FUNKCJE POMOCNICZE ===
// Te funkcje muszą być dostępne w całym pliku, poza blokami jQuery

function escapeHtml(text) {
	if (!text) return '';
	const div = document.createElement('div');
	div.textContent = text;
	return div.innerHTML;
}

function formatFileSize(bytes) {
	if (bytes === 0) return '0 Bytes';
	const k = 1024;
	const sizes = ['Bytes', 'KB', 'MB', 'GB'];
	const i = Math.floor(Math.log(bytes) / Math.log(k));
	return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function getIconForMimeType(mimeType) {
	const iconMap = {
		'application/pdf': 'dashicons-pdf',
		'application/msword': 'dashicons-media-document',
		'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'dashicons-media-document',
		'application/vnd.ms-excel': 'dashicons-media-spreadsheet',
		'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': 'dashicons-media-spreadsheet',
		'text/plain': 'dashicons-media-text',
		'image/jpeg': 'dashicons-format-image',
		'image/jpg': 'dashicons-format-image',
		'image/png': 'dashicons-format-image',
		'image/gif': 'dashicons-format-image',
		'image/svg+xml': 'dashicons-format-image'
	};
	return iconMap[mimeType] || 'dashicons-media-default';
}

// Dodaj funkcje do obiektu window, żeby były dostępne globalnie
window.escapeHtml = escapeHtml;
window.formatFileSize = formatFileSize;
window.getIconForMimeType = getIconForMimeType;

// Funkcja wyświetlania powiadomień
function showNotification(message, type = 'info') {
	// Usuń poprzednie powiadomienia
	jQuery('.wpmzf-notification').remove();

	const notificationClass = type === 'success' ? 'notice-success' :
		type === 'error' ? 'notice-error' : 'notice-info';

	const notification = jQuery(`
		<div class="wpmzf-notification notice ${notificationClass} is-dismissible" style="margin: 10px 0;">
			<p>${message}</p>
			<button type="button" class="notice-dismiss">
				<span class="screen-reader-text">Dismiss this notice.</span>
			</button>
		</div>
	`);

	// Dodaj powiadomienie na górę formularza
	jQuery('#wpmzf-add-activity-form').before(notification);

	// Obsługa zamykania
	notification.find('.notice-dismiss').on('click', function () {
		notification.fadeOut(300, function () {
			jQuery(this).remove();
		});
	});

	// Automatyczne usunięcie po 5 sekundach dla success/info
	if (type !== 'error') {
		setTimeout(function () {
			notification.fadeOut(300, function () {
				jQuery(this).remove();
			});
		}, 5000);
	}
}

// === FUNKCJONALNOŚĆ FIRMY ===
jQuery(document).ready(function ($) {
	// --- Zmienne ---
	const companyId = $('input[name="company_id"]').val();
	// Używamy zmiennych z wp_localize_script jeśli są dostępne, w przeciwnym razie fallback
	const securityNonce = (typeof wpmzfCompanyView !== 'undefined' && wpmzfCompanyView.nonce) ? 
		wpmzfCompanyView.nonce : $('#wpmzf_security').val();
	const taskSecurityNonce = (typeof wpmzfCompanyView !== 'undefined' && wpmzfCompanyView.taskNonce) ? 
		wpmzfCompanyView.taskNonce : $('#wpmzf_task_security').val();
	
	const form = $('#wpmzf-add-activity-form');
	const timelineContainer = $('#wpmzf-activity-timeline');
	const submitButton = $('#wpmzf-submit-activity-btn');
	const dateField = $('#wpmzf-activity-date');
	const attachFileBtn = $('#wpmzf-attach-file-btn');
	const attachmentInput = $('#wpmzf-activity-files-input');
	const attachmentsPreviewContainer = $('#wpmzf-attachments-preview-container');

	// Zmienne dla zadań
	const taskForm = $('#wpmzf-add-task-form');
	const taskTitleInput = $('#wpmzf-task-title');
	const taskDueDateInput = $('#wpmzf-task-due-date');
	const openTasksList = $('#wpmzf-open-tasks-list');
	const closedTasksList = $('#wpmzf-closed-tasks-list');
	const toggleClosedTasks = $('#wpmzf-toggle-closed-tasks');

	let filesToUpload = [];
	let linkMetadataCache = new Map();

	// Dodanie zmiennych dla drag & drop i clipboard
	let dragDropEnabled = false;
	let pendingUploads = new Set(); // Tracking pending uploads for cleanup

	// === INICJALIZACJA FUNKCJONALNOŚCI AKTYWNOŚCI ===

	// --- Inicjalizacja ---
	function setDefaultActivityDateTime() {
		const now = new Date();
		now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
		dateField.val(now.toISOString().slice(0, 16));
	}

	setDefaultActivityDateTime();
	loadActivities();

	// --- Obsługa edytora z placeholderem ---
	const editorPlaceholder = $('#wpmzf-editor-placeholder');
	const editorContainer = $('#wpmzf-editor-container');
	let editorInitialized = false;
	let editorVisible = false;

	// Funkcja debugowania stanu edytora
	function debugEditorState() {
		if (!editorPlaceholder.is(':visible') && !editorContainer.is(':visible')) {
			console.warn('=== Debug stanu edytora ===');
			console.log('Placeholder exists:', editorPlaceholder.length > 0);
			console.log('Container exists:', editorContainer.length > 0);
			console.log('Placeholder visible:', editorPlaceholder.is(':visible'));
			console.log('Container visible:', editorContainer.is(':visible'));
			console.log('Editor visible flag:', editorVisible);

			if (window.tinyMCE) {
				const editor = window.tinyMCE.get('wpmzf-activity-content');
				console.log('TinyMCE editor exists:', !!editor);
				if (editor) {
					console.log('Editor hidden:', editor.isHidden());
					console.log('Editor removed:', editor.removed);
				}
			}
			console.log('=========================');
		}
	}

	// Debug na początku
	debugEditorState();

	// Funkcja pokazywania prawdziwego edytora
	function showEditor() {
		if (editorVisible) return;

		editorVisible = true;
		editorPlaceholder.hide();

		// Usuń wszystkie inline style z display: none i dodaj klasę
		editorContainer.css('display', '').addClass('visible').show();

		// Daj czas na pokazanie się kontenera, potem zainicjalizuj edytor
		setTimeout(function () {
			try {
				if (window.tinyMCE) {
					// Sprawdź czy edytor już istnieje
					let editor = window.tinyMCE.get('wpmzf-activity-content');

					if (editor) {
						// Edytor istnieje - sprawdź czy jest aktywny
						if (editor.removed) {
							// Edytor był usunięty, usuń go całkowicie z pamięci
							window.tinyMCE.remove('#wpmzf-activity-content');
							editor = null;
						} else {
							// Edytor istnieje i jest aktywny - pokaż go i ustaw focus
							$(editor.getContainer()).show();
							editor.show();
							setTimeout(() => editor.focus(), 100);
							return;
						}
					}

					// Jeśli edytor nie istnieje lub był usunięty, zainicjalizuj go ponownie
					if (!editor) {
						// Usuń wszystkie poprzednie instancje
						window.tinyMCE.remove('#wpmzf-activity-content');

						// Upewnij się, że textarea jest widoczny przed inicjalizacją
						$('#wpmzf-activity-content').show();

						// Zainicjalizuj nowy edytor z rozszerzonym toolbarem
						window.tinyMCE.init({
							selector: '#wpmzf-activity-content',
							plugins: 'lists link paste textcolor',
							toolbar1: 'bold italic underline | forecolor | bullist numlist | link unlink | removeformat | undo redo',
							toolbar2: '',
							content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; font-size: 14px; line-height: 1.5; padding: 12px; }',
							height: 120,
							max_height: 300,
							resize: 'vertical',
							menubar: false,
							branding: false,
							statusbar: false,
							paste_as_text: false,
							paste_auto_cleanup_on_paste: true,
							paste_remove_styles: false,
							paste_remove_spans: false,
							paste_strip_class_attributes: 'none',
							paste_retain_style_properties: 'font-weight,font-style,text-decoration,color',
							paste_enable_default_filters: true,
							paste_webkit_styles: 'font-weight font-style text-decoration color',
							setup: function (editor) {
								editor.on('init', function () {
									console.log('TinyMCE editor initialized successfully');
									// Upewnij się, że kontener edytora jest widoczny
									$(editor.getContainer()).show();
									setTimeout(() => {
										editor.focus();
									}, 100);
									// Wywołaj event dla naszych listenerów
									$(document).trigger('tinymce-editor-init', [editor]);
								});

								editor.on('LoadContent', function () {
									console.log('TinyMCE content loaded');
								});

								// Obsługa pokazywania edytora po inicjalizacji
								editor.on('show', function () {
									$(editor.getContainer()).show();
								});
							}
						});
					}
				} else {
					// TinyMCE nie jest załadowany - pokaż textarea jako fallback
					console.warn('TinyMCE nie jest dostępny, używam textarea');
					$('#wpmzf-activity-content').show().focus();
				}
			} catch (error) {
				console.error('Błąd podczas inicjalizacji TinyMCE:', error);
				// Fallback - pokaż textarea
				$('#wpmzf-activity-content').show().focus();
			}
		}, 300);

		// Dodatkowy mechanizm sprawdzający czy edytor się rzeczywiście pokazał
		setTimeout(function () {
			debugEditorState();

			// Jeśli kontener jest widoczny ale TinyMCE nie istnieje, spróbuj ponownie
			if (editorContainer.is(':visible') && !window.tinyMCE.get('wpmzf-activity-content')) {
				console.log('Retry: TinyMCE nie został zainicjalizowany, próbuję ponownie...');
				$('#wpmzf-activity-content').show().focus();
			}
		}, 1000);
	}

	// Funkcja ukrywania edytora i pokazywania placeholdera
	function hideEditor() {
		if (!editorVisible) return;

		// Sprawdź czy edytor ma jakąkolwiek treść
		let hasContent = false;
		try {
			if (window.tinyMCE && window.tinyMCE.get('wpmzf-activity-content')) {
				const editor = window.tinyMCE.get('wpmzf-activity-content');
				const content = editor.getContent();
				hasContent = content && content.trim() !== '' && content.trim() !== '<p></p>' && content.trim() !== '<p><br></p>';
			}
		} catch (error) {
			console.warn('Błąd podczas sprawdzania zawartości edytora:', error);
			// Fallback - sprawdź textarea
			const textareaContent = $('#wpmzf-activity-content').val();
			hasContent = textareaContent && textareaContent.trim() !== '';
		}

		// Nie ukrywaj jeśli jest treść
		if (hasContent) return;

		editorVisible = false;

		// Ukryj edytor
		try {
			if (window.tinyMCE && window.tinyMCE.get('wpmzf-activity-content')) {
				const editor = window.tinyMCE.get('wpmzf-activity-content');
				editor.hide();
			}
		} catch (error) {
			console.warn('Błąd podczas ukrywania edytora TinyMCE:', error);
		}

		editorContainer.removeClass('visible').hide();
		editorPlaceholder.show();
	}

	// Kliknięcie na placeholder pokazuje edytor
	editorPlaceholder.on('click', function (e) {
		e.preventDefault();
		e.stopPropagation();
		console.log('Placeholder clicked - showing editor');
		showEditor();
	});

	// Dodaj też obsługę kliknięcia w obszar placeholder text
	editorPlaceholder.find('.placeholder-text').on('click', function (e) {
		e.preventDefault();
		e.stopPropagation();
		console.log('Placeholder text clicked - showing editor');
		showEditor();
	});

	// Kliknięcie poza edytorem (jeśli jest pusty) ukrywa go
	$(document).on('click', function (e) {
		if (editorVisible && !$(e.target).closest('#wpmzf-editor-container, .mce-panel, .mce-menu, .mce-window').length) {
			hideEditor();
		}
	});

	// Dodatkowy event handler dla przypadków gdy edytor ma problemy
	$(document).on('dblclick', '#wpmzf-editor-placeholder', function (e) {
		e.preventDefault();
		console.log('Double click on placeholder - force show editor');

		// Wymuś pokazanie kontenera
		editorContainer.css('display', 'block').addClass('visible').show();
		editorPlaceholder.hide();
		editorVisible = true;

		// Jeśli TinyMCE nie działa, pokaż przynajmniej textarea
		setTimeout(function () {
			if (!window.tinyMCE || !window.tinyMCE.get('wpmzf-activity-content')) {
				$('#wpmzf-activity-content').show().focus();
				console.log('Fallback: pokazano textarea zamiast TinyMCE');
			}
		}, 500);
	});

	// Funkcja reset edytora po dodaniu aktywności
	function resetEditor() {
		try {
			if (window.tinyMCE && window.tinyMCE.get('wpmzf-activity-content')) {
				const editor = window.tinyMCE.get('wpmzf-activity-content');
				editor.setContent('');
				// Ukryj edytor po wyczyszczeniu
				editor.hide();
			}
		} catch (error) {
			console.warn('Błąd podczas resetowania edytora:', error);
			// Fallback - reset textarey
			$('#wpmzf-activity-content').val('');
		}

		// Reset stanu edytora
		editorVisible = false;
		editorContainer.removeClass('visible').hide();
		editorPlaceholder.show();
	}

	// Watchdog - sprawdza okresowo stan edytora
	setInterval(function () {
		// Jeśli placeholder jest ukryty, ale kontener też jest ukryty, coś poszło nie tak
		if (!editorPlaceholder.is(':visible') && !editorContainer.is(':visible')) {
			console.warn('Watchdog: Ani placeholder ani kontener nie są widoczne - naprawiam...');
			editorVisible = false;
			editorContainer.removeClass('visible').hide();
			editorPlaceholder.show();
		}

		// Jeśli edytor ma być widoczny ale kontener jest ukryty
		if (editorVisible && !editorContainer.is(':visible')) {
			console.warn('Watchdog: Edytor ma być widoczny ale kontener jest ukryty - naprawiam...');
			editorContainer.addClass('visible').show();
		}
	}, 2000); // Sprawdza co 2 sekundy

	// Funkcja renderowania podglądu załączników (musi być zdefiniowana wcześnie)
	function renderAttachmentsPreview() {
		// Jeśli nie ma plików, ukryj cały kontener podglądu
		if (filesToUpload.length === 0) {
			attachmentsPreviewContainer.hide().empty();
			return;
		}

		// Pokaż kontener i wygeneruj HTML dla każdego pliku
		attachmentsPreviewContainer.show();
		let previewHtml = '<div class="attachments-preview-header"><strong>Załączniki do dodania:</strong></div>';

		filesToUpload.forEach((file, index) => {
			const fileSize = formatFileSize(file.size);
			const fileIcon = getIconForMimeType(file.type);

			previewHtml += `
				<div class="attachment-item" data-file-index="${index}">
					<div class="attachment-info">
						<span class="dashicons ${fileIcon}"></span>
						<span class="attachment-name">${escapeHtml(file.name)}</span>
						<span class="attachment-size">(${fileSize})</span>
					</div>
					<div class="attachment-progress" style="display: none;">
						<div class="attachment-progress-bar">
							<div class="attachment-progress-fill" style="width: 0%"></div>
						</div>
						<span class="attachment-progress-text">0%</span>
					</div>
					<span class="dashicons dashicons-no-alt remove-attachment" title="Usuń z listy"></span>
				</div>
			`;
		});

		attachmentsPreviewContainer.html(previewHtml);
	}

	// --- Obsługa załączników ---
	attachFileBtn.on('click', function() {
		attachmentInput.trigger('click');
	});

	attachmentInput.on('change', function() {
		const files = Array.from(this.files);
		
		files.forEach(file => {
			// Sprawdź czy plik już nie został dodany
			const alreadyExists = filesToUpload.some(existingFile => 
				existingFile.name === file.name && existingFile.size === file.size
			);
			
			if (!alreadyExists) {
				filesToUpload.push(file);
			}
		});

		// Wyczyść input, żeby można było dodać ten sam plik ponownie
		this.value = '';
		
		renderAttachmentsPreview();
	});

	// Obsługa usuwania załączników z podglądu
	attachmentsPreviewContainer.on('click', '.remove-attachment', function() {
		const fileIndex = parseInt($(this).closest('.attachment-item').data('file-index'));
		filesToUpload.splice(fileIndex, 1);
		renderAttachmentsPreview();
	});

	// --- Funkcje AJAX ---

	function loadActivities() {
		// Upewniamy się, że companyId jest dostępne
		if (!companyId || companyId <= 0) {
			console.error('Cannot load activities: invalid company ID:', companyId);
			timelineContainer.html('<p><em>Błąd: Nieprawidłowe ID firmy.</em></p>');
			return;
		}
		
		console.log('Loading activities for company ID:', companyId);
		
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'get_wpmzf_activities',
				company_id: companyId,
				security: securityNonce
			},
			success: function(response) {
				console.log('Activities response:', response);
				if (response.success) {
					if (response.data && response.data.length > 0) {
						renderTimeline(response.data);
					} else {
						timelineContainer.html('<p><em>Brak zarejestrowanych aktywności. Dodaj pierwszą!</em></p>');
					}
				} else {
					console.error('Error loading activities:', response.data);
					const errorMsg = response.data && response.data.message ? response.data.message : 'Nieznany błąd';
					timelineContainer.html('<p><em>Błąd: ' + errorMsg + '</em></p>');
				}
			},
			error: function(xhr, status, error) {
				console.error('AJAX error:', status, error, xhr);
				timelineContainer.html('<p><em>Błąd podczas ładowania aktywności.</em></p>');
			}
		});
	}

	// Renderowanie timeline aktywności
	async function renderTimeline(activities) {
		console.log('Rendering timeline with activities:', activities);
		
		if (!activities || activities.length === 0) {
			timelineContainer.html('<p><em>Brak zarejestrowanych aktywności. Dodaj pierwszą!</em></p>');
			return;
		}

		// Sortowanie aktywności - najnowsze na górze
		activities.sort(function(a, b) {
			const dateA = new Date(a.date);
			const dateB = new Date(b.date);
			return dateB - dateA; // DESC - najnowsze na górze
		});

		let html = '';

		for (const activity of activities) {
			const iconMap = { 
				'Notatka': 'dashicons-admin-comments', 
				'E-mail': 'dashicons-email-alt', 
				'Telefon': 'dashicons-phone', 
				'Spotkanie': 'dashicons-groups', 
				'Spotkanie online': 'dashicons-video-alt3',
				'notatka': 'dashicons-admin-comments',
				'rozmowa': 'dashicons-phone',
				'spotkanie': 'dashicons-groups',
				'email': 'dashicons-email-alt',
				'inne': 'dashicons-marker'
			};
			const iconClass = iconMap[activity.type] || 'dashicons-marker';

			const date = new Date(activity.date);
			const months = ['stycznia', 'lutego', 'marca', 'kwietnia', 'maja', 'czerwca', 'lipca', 'sierpnia', 'września', 'października', 'listopada', 'grudnia'];
			const formattedDate = `${date.getDate()} ${months[date.getMonth()]} ${date.getFullYear()} ${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`;

			let attachmentsHtml = '';
			if (activity.attachments && activity.attachments.length > 0) {
				attachmentsHtml += '<div class="timeline-attachments"><ul>';
				activity.attachments.forEach(function(att) {
					const attachmentIcon = getIconForMimeType(att.mime_type);
					let previewHtml = '';

					if (att.thumbnail_url) {
						previewHtml = `<img src="${att.thumbnail_url}" alt="Podgląd załącznika">`;
					} else {
						previewHtml = `<span class="dashicons ${attachmentIcon}" title="${att.mime_type}"></span>`;
					}

					attachmentsHtml += `
						<li data-attachment-id="${att.id}">
							<a href="${att.url}" target="_blank">
							   ${previewHtml}
							   <span>${att.filename}</span>
							</a>
							<span class="dashicons dashicons-trash delete-attachment" title="Usuń załącznik"></span>
						</li>
					`;
				});
				attachmentsHtml += '</ul></div>';
			}

			// Przetwórz zawartość z bogatymi kartami linków (jesli funkcja istnieje)
			let processedContent = activity.content;
			if (typeof processRichLinks === 'function') {
				processedContent = await processRichLinks(activity.content);
			}

			// Sprawdź czy treść jest długa (więcej niż 350px po wyrenderowaniu)
			const tempDiv = $('<div>').html(processedContent).css({
				'position': 'absolute',
				'visibility': 'hidden',
				'width': '600px',
				'max-width': '100%'
			});
			$('body').append(tempDiv);
			const contentHeight = tempDiv.height();
			tempDiv.remove();

			const isLongContent = contentHeight > 350;
			const contentClass = isLongContent ? 'collapsed' : '';
			const expandButton = isLongContent ? '<button class="timeline-expand-btn" data-action="expand">Rozwiń treść</button>' : '';

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
								<span class="timeline-header-date">${formattedDate}</span>
							</div>
							<div class="timeline-actions">
								<span class="dashicons dashicons-edit edit-activity" title="Edytuj"></span>
								<span class="dashicons dashicons-trash delete-activity" title="Usuń"></span>
							</div>
						</div>
						<div class="timeline-body">
							<div class="activity-content-display ${contentClass}">
								${processedContent}
							</div>
							${expandButton}
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

	// Zmienna do śledzenia czy formularz jest aktualnie przetwarzany
	let isSubmitting = false;

	// --- Obsługa formularza dodawania aktywności ---
	form.on('submit', async function (e) {
		e.preventDefault();

		// Zapobiegaj wielokrotnym submissionom
		if (isSubmitting) {
			return;
		}
		isSubmitting = true;

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
					xhr: function() {
						const xhr = new window.XMLHttpRequest();
						xhr.upload.addEventListener('progress', function(e) {
							if (e.lengthComputable) {
								const percentComplete = Math.round((e.loaded / e.total) * 100);
								progressBar.find('.attachment-progress-fill').css('width', percentComplete + '%');
								progressText.text(percentComplete + '%');
							}
						}, false);
						return xhr;
					}
				}).then(response => {
					if (response.success) {
						previewItem.find('.attachment-progress-fill').css('width', '100%');
						previewItem.find('.attachment-progress-text').text('100%');
						return response.data.attachment_id;
					} else {
						throw new Error(response.data.message || 'Upload failed');
					}
				}).catch(error => {
					console.error('Upload error for file:', file.name, error);
					previewItem.addClass('upload-error');
					previewItem.find('.attachment-progress-text').text('Błąd');
					throw error;
				});
			});

			try {
				uploadedAttachmentIds = await Promise.all(uploadPromises);
				submitButton.text('Zapisywanie aktywności...');
			} catch (error) {
				showNotification('Błąd podczas wysyłania plików: ' + error.message, 'error');
				submitButton.text(originalButtonText).prop('disabled', false);
				attachFileBtn.prop('disabled', false);
				isSubmitting = false;
				return;
			}
		}

		// Zbierz dane formularza
		let editorContent = '';
		const editor = window.tinyMCE && window.tinyMCE.get('wpmzf-activity-content');
		if (editor && !editor.isHidden()) {
			editorContent = editor.getContent();
		} else {
			editorContent = $('#wpmzf-activity-content').val();
		}

		if (!editorContent.trim()) {
			editorContent = $('#wpmzf-activity-content').val();
		}

		const formData = new FormData(this);
		formData.set('content', editorContent);
		formData.set('action', 'add_wpmzf_activity');
		formData.set('company_id', companyId);
		formData.delete('person_id'); // Usuń person_id jeśli istnieje

		// Dodaj IDs załączników
		if (uploadedAttachmentIds.length > 0) {
			uploadedAttachmentIds.forEach(id => {
				formData.append('attachment_ids[]', id);
			});
		}

		// AJAX request
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: formData,
			processData: false,
			contentType: false,
			success: function(response) {
				if (response.success) {
					// Reset edytora przy użyciu dedykowanej funkcji
					resetEditor();
					
					form[0].reset();
					filesToUpload = [];
					renderAttachmentsPreview();
					setDefaultActivityDateTime();
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
				isSubmitting = false; // Reset flagi
			}
		});
	});

	// === OBSŁUGA EDYCJI I USUWANIA AKTYWNOŚCI ===
	
	// Usuwanie aktywności
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

	// Usuwanie załączników
	timelineContainer.on('click', '.delete-attachment', function (e) {
		e.preventDefault();
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

	// Edycja aktywności - przełączanie na tryb edycji
	timelineContainer.on('click', '.edit-activity', function () {
		const contentDiv = $(this).closest('.timeline-content');
		const textareaElement = contentDiv.find('.activity-edit-textarea');

		contentDiv.find('.activity-content-display').hide();
		contentDiv.find('.activity-content-edit').show();
		textareaElement.trigger('focus');
	});

	// Anulowanie edycji aktywności
	timelineContainer.on('click', '.cancel-activity-edit', function () {
		const contentDiv = $(this).closest('.timeline-content');
		contentDiv.find('.activity-content-edit').hide();
		contentDiv.find('.activity-content-display').show();
	});

	// Zapisywanie edycji aktywności
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
				// Przetwórz zawartość z bogatymi kartami linków (jeśli funkcja istnieje)
				let processedContent = newContent;
				if (typeof processRichLinks === 'function') {
					processedContent = await processRichLinks(newContent);
				}

				// Sprawdź czy treść jest długa i potrzebuje przycisku rozwijania
				const tempDiv = $('<div>').html(processedContent).css({
					'position': 'absolute',
					'visibility': 'hidden',
					'width': '600px',
					'max-width': '100%'
				});
				$('body').append(tempDiv);
				const contentHeight = tempDiv.height();
				tempDiv.remove();

				const isLongContent = contentHeight > 350;
				const timelineBody = contentDiv.find('.timeline-body');

				// Aktualizuj treść
				const displayDiv = contentDiv.find('.activity-content-display');
				displayDiv.html(processedContent);

				// Usuń stary przycisk rozwijania
				timelineBody.find('.timeline-expand-btn').remove();

				// Dodaj nowy przycisk jeśli treść jest długa
				if (isLongContent) {
					displayDiv.addClass('collapsed');
					const expandButton = $('<button class="timeline-expand-btn" data-action="expand">Rozwiń treść</button>');
					displayDiv.after(expandButton);
				} else {
					displayDiv.removeClass('collapsed');
				}

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

	// --- Obsługa przycisku rozwijania treści ---
	timelineContainer.on('click', '.timeline-expand-btn', function () {
		const button = $(this);
		const contentDisplay = button.siblings('.activity-content-display');

		if (button.data('action') === 'expand') {
			// Rozwiń treść
			contentDisplay.removeClass('collapsed');
			button.text('Zwiń treść').data('action', 'collapse');
		} else {
			// Zwiń treść
			contentDisplay.addClass('collapsed');
			button.text('Rozwiń treść').data('action', 'expand');
		}
	});

	// === FUNKCJONALNOŚĆ ZADAŃ ===

	// Inicjalizacja zadań
	loadTasks();

	// === DODAWANIE ZADANIA ===
	if (taskForm.length > 0) {
		taskForm.on('submit', function(e) {
			e.preventDefault();
			
			const taskTitle = taskTitleInput.val().trim();
			const taskDueDate = taskDueDateInput.val();
			
			if (!taskTitle) {
				showTaskMessage('Podaj tytuł zadania.', 'error');
				return;
			}
			
			const submitBtn = $(this).find('button[type="submit"]');
			const originalText = submitBtn.text();
			submitBtn.text('Dodawanie...').prop('disabled', true);
			
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'add_wpmzf_task',
					company_id: companyId,
					task_title: taskTitle,
					task_due_date: taskDueDate,
					wpmzf_task_security: taskSecurityNonce
				},
				success: function(response) {
					if (response.success) {
						taskTitleInput.val('');
						taskDueDateInput.val('');
						loadTasks();
						showTaskMessage('Zadanie zostało dodane.', 'success');
					} else {
						showTaskMessage('Błąd: ' + (response.data ? response.data.message : 'Nieznany błąd'), 'error');
					}
				},
				error: function() {
					showTaskMessage('Błąd podczas dodawania zadania.', 'error');
				},
				complete: function() {
					submitBtn.text(originalText).prop('disabled', false);
				}
			});
		});
	}

	// === ŁADOWANIE ZADAŃ ===
	function loadTasks() {
		if (!companyId || companyId <= 0) {
			console.error('Cannot load tasks: invalid company ID:', companyId);
			openTasksList.html('<p><em>Błąd: Nieprawidłowe ID firmy.</em></p>');
			return;
		}
		
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'get_wpmzf_tasks',
				company_id: companyId,
				wpmzf_task_security: taskSecurityNonce
			},
			success: function(response) {
				if (response.success) {
					openTasksList.html(response.data.open_tasks || '<p><em>Brak otwartych zadań.</em></p>');
					closedTasksList.html(response.data.closed_tasks || '<p><em>Brak zakończonych zadań.</em></p>');
				} else {
					openTasksList.html('<p><em>Brak zadań dla tej firmy.</em></p>');
					closedTasksList.html('<p><em>Brak zakończonych zadań.</em></p>');
				}
			},
			error: function(xhr, status, error) {
				console.error('Tasks AJAX error:', status, error, xhr);
				openTasksList.html('<p><em>Błąd podczas ładowania zadań.</em></p>');
			}
		});
	}

	function showTaskMessage(message, type) {
		const messageClass = type === 'success' ? 'notice-success' : 'notice-error';
		const messageHtml = '<div class="task-message ' + messageClass + '">' + message + '</div>';
		
		// Usuń poprzednie komunikaty
		$('.task-message').remove();
		
		// Dodaj nowy komunikat
		taskForm.after(messageHtml);
		
		// Usuń komunikat po 3 sekundach
		setTimeout(function() {
			$('.task-message').fadeOut(500, function() {
				$(this).remove();
			});
		}, 3000);
	}

	// Obsługa przełączania zakończonych zadań
	toggleClosedTasks.on('click', function() {
		const $this = $(this);
		const $list = closedTasksList;
		
		if ($list.is(':visible')) {
			$list.slideUp(200);
			$this.removeClass('expanded');
		} else {
			$list.slideDown(200);
			$this.addClass('expanded');
		}
	});

	// === OBSŁUGA AKCJI ZADAŃ ===
	
	// Obsługa akcji na zadaniach (zmiana statusu, edycja, usuwanie)
	$(document).on('click', '.task-actions .dashicons', function() {
		const $this = $(this);
		const action = $this.data('action');
		const taskId = $this.closest('.task-item').data('task-id');
		
		switch(action) {
			case 'toggle-status':
				toggleTaskStatus(taskId);
				break;
			case 'edit':
				editTask(taskId);
				break;
			case 'delete':
				deleteTask(taskId);
				break;
		}
	});

	// === AKTUALIZACJA STATUSU ZADANIA ===
	function toggleTaskStatus(taskId) {
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'toggle_wpmzf_task_status',
				task_id: taskId,
				wpmzf_task_security: taskSecurityNonce
			},
			success: function(response) {
				if (response.success) {
					loadTasks();
					showTaskMessage('Status zadania został zmieniony.', 'success');
				} else {
					showTaskMessage('Błąd: ' + (response.data ? response.data.message : 'Nieznany błąd'), 'error');
				}
			},
			error: function() {
				showTaskMessage('Błąd podczas zmiany statusu zadania.', 'error');
			}
		});
	}

	// === USUWANIE ZADANIA ===
	function deleteTask(taskId) {
		if (!confirm('Czy na pewno chcesz usunąć to zadanie?')) {
			return;
		}
		
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'delete_wpmzf_task',
				task_id: taskId,
				wpmzf_task_security: taskSecurityNonce
			},
			success: function(response) {
				if (response.success) {
					loadTasks();
					showTaskMessage('Zadanie zostało usunięte.', 'success');
				} else {
					showTaskMessage('Błąd: ' + (response.data ? response.data.message : 'Nieznany błąd'), 'error');
				}
			},
			error: function() {
				showTaskMessage('Błąd podczas usuwania zadania.', 'error');
			}
		});
	}

	// === EDYCJA ZADANIA ===
	function editTask(taskId) {
		// Znajdź element zadania
		const taskItem = $(`.task-item[data-task-id="${taskId}"]`);
		const taskTitle = taskItem.find('.task-title');
		const currentTitle = taskTitle.text();
		
		// Zamień tytuł na pole input
		const inputField = $('<input type="text" class="task-edit-input" value="' + escapeHtml(currentTitle) + '">');
		taskTitle.replaceWith(inputField);
		inputField.focus().select();
		
		// Obsługa zapisu (Enter) i anulowania (Escape)
		inputField.on('keydown', function(e) {
			if (e.which === 13) { // Enter
				saveTaskTitle(taskId, $(this).val());
			} else if (e.which === 27) { // Escape
				$(this).replaceWith('<span class="task-title">' + escapeHtml(currentTitle) + '</span>');
			}
		});
		
		// Obsługa utraty fokusa
		inputField.on('blur', function() {
			saveTaskTitle(taskId, $(this).val());
		});
	}

	// === ZAPISYWANIE TYTUŁU ZADANIA ===
	function saveTaskTitle(taskId, newTitle) {
		if (!newTitle.trim()) {
			loadTasks(); // Przywróć oryginalną listę
			return;
		}
		
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'update_wpmzf_task_title',
				task_id: taskId,
				task_title: newTitle.trim(),
				wpmzf_task_security: taskSecurityNonce
			},
			success: function(response) {
				if (response.success) {
					loadTasks();
					showTaskMessage('Tytuł zadania został zaktualizowany.', 'success');
				} else {
					loadTasks();
					showTaskMessage('Błąd: ' + (response.data ? response.data.message : 'Nieznany błąd'), 'error');
				}
			},
			error: function() {
				loadTasks();
				showTaskMessage('Błąd podczas aktualizacji tytułu zadania.', 'error');
			}
		});
	}

	console.log('Company view JS initialized for company ID:', companyId);
});
