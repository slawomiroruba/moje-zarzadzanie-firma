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

// Funkcja inicjalizacji TinyMCE dla edycji aktywności
function initActivityEditTinyMCE(editorId) {
	if (typeof tinymce !== 'undefined') {
		// Usuń poprzedni edytor jeśli istnieje
		tinymce.remove('#' + editorId);
		
		// Inicjalizuj nowy edytor
		tinymce.init({
			selector: '#' + editorId,
			menubar: false,
			toolbar: 'bold italic underline forecolor | bullist numlist | link unlink | removeformat undo redo',
			plugins: 'lists link paste textcolor',
			height: 120,
			branding: false,
			statusbar: false,
			resize: false,
			content_style: 'body { font-family: Arial, sans-serif; font-size: 14px; margin: 8px; }',
			init_instance_callback: function(editor) {
				// Edytor jest gotowy do użycia
				editor.focus();
			},
			setup: function(editor) {
				// Obsługa błędów inicjalizacji
				editor.on('LoadContent', function() {
					console.log('TinyMCE załadowany dla', editorId);
				});
			}
		});
	} else {
		// Fallback - TinyMCE nie jest dostępny
		console.warn('TinyMCE nie jest dostępny, używam textarea dla', editorId);
		const textarea = jQuery('#' + editorId);
		if (textarea.length) {
			textarea.show().focus();
		}
	}
}

// Funkcja pobierania treści z TinyMCE lub textarea
function getActivityEditContent(editorId) {
	if (typeof tinymce !== 'undefined') {
		const editor = tinymce.get(editorId);
		if (editor && editor.initialized) {
			return editor.getContent();
		}
	}
	// Fallback do textarea
	const textareaContent = jQuery('#' + editorId).val();
	return textareaContent || '';
}

// Dodaj funkcje do obiektu window
window.initActivityEditTinyMCE = initActivityEditTinyMCE;
window.getActivityEditContent = getActivityEditContent;

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
	console.log('Company ID found:', companyId); // Debug
	
	// Używamy zmiennych z wp_localize_script jeśli są dostępne, w przeciwnym razie fallback
	const securityNonce = (typeof wpmzfCompanyView !== 'undefined' && wpmzfCompanyView.nonce) ?
		wpmzfCompanyView.nonce : $('#wpmzf_security').val();
	const taskSecurityNonce = (typeof wpmzfCompanyView !== 'undefined' && wpmzfCompanyView.taskNonce) ?
		wpmzfCompanyView.taskNonce : $('#wpmzf_task_security').val();

	console.log('Security nonce:', securityNonce); // Debug
	console.log('Task security nonce:', taskSecurityNonce); // Debug

	// Sprawdź czy companyId jest prawidłowe - ale nie przerywaj wykonania całego kodu
	if (!companyId || companyId === '' || companyId === 'undefined') {
		console.error('Company ID not found or invalid - some features may not work!');
		// Nie używamy return - pozwalamy na działanie pozostałych funkcji
	}

	// === OBSŁUGA ZAKŁADEK AKTYWNOŚCI ===
	
	// Przełączanie zakładek
	$('.activity-tabs .tab-link').on('click', function() {
		const tabId = $(this).data('tab');
		
		// Zaktualizuj przyciski
		$('.activity-tabs .tab-link').removeClass('active');
		$(this).addClass('active');
		
		// Pokaż odpowiednią treść
		$('.tab-content').removeClass('active');
		$('#' + tabId + '-tab-content').addClass('active');

		// Automatyczne wypełnienie pola "Do" po przełączeniu na e-mail
		if (tabId === 'email') {
			const primaryEmail = $('[data-field="company_emails"] .contact-item.is-primary a').attr('href')?.replace('mailto:', '') || '';
			if (primaryEmail) {
				$('#email-tab-content input[name="email_to"]').val(primaryEmail);
			}
		}
	});

	// === OBSŁUGA FORMULARZA NOTATKI ===
	const noteForm = $('#wpmzf-add-note-form');
	const noteTimelineContainer = $('#wpmzf-activity-timeline');
	const noteSubmitButton = noteForm.find('button[type="submit"]');
	const noteDateField = $('#wpmzf-note-date');
	const noteAttachFileBtn = $('#wpmzf-note-attach-files-btn');
	const noteAttachmentInput = $('#wpmzf-note-files-input');
	const noteAttachmentsPreviewContainer = $('#wpmzf-note-attachments-preview-container');

	// === OBSŁUGA FORMULARZA E-MAIL ===
	const emailForm = $('#wpmzf-send-email-form');
	const emailSubmitButton = emailForm.find('button[type="submit"]');

	// Stare zmienne dla kompatybilności (będą stopniowo usuwane)
	const form = noteForm; // Backwards compatibility
	const timelineContainer = noteTimelineContainer;
	const submitButton = noteSubmitButton;
	const dateField = noteDateField;
	const attachFileBtn = noteAttachFileBtn;
	const attachmentInput = noteAttachmentInput;
	const attachmentsPreviewContainer = noteAttachmentsPreviewContainer;

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

	// Debug - sprawdź wartości na początku
	console.log('Company view debug:');
	console.log('- companyId:', companyId);
	console.log('- securityNonce:', securityNonce);
	console.log('- taskSecurityNonce:', taskSecurityNonce);
	console.log('- ajaxurl:', typeof ajaxurl !== 'undefined' ? ajaxurl : 'UNDEFINED');

	// === INICJALIZACJA FUNKCJONALNOŚCI AKTYWNOŚCI ===

	// --- Inicjalizacja ---
	function setDefaultActivityDateTime() {
		const now = new Date();
		now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
		noteDateField.val(now.toISOString().slice(0, 16));
	}

	setDefaultActivityDateTime();
	loadActivities();

	// --- Obsługa edytora z placeholderem ---
	const editorPlaceholder = $('#wpmzf-note-editor-placeholder');
	const editorContainer = $('#wpmzf-note-editor-container');
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
						// Sprawdź czy wp_editor już utworzył edytor TinyMCE
						editor = window.tinyMCE.get('wpmzf-activity-content');
						if (editor) {
							// WordPress już utworzył edytor - tylko go pokaż
							$(editor.getContainer()).show();
							editor.show();
							setTimeout(() => editor.focus(), 100);
							return;
						}
						
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
			// Reset edytora notatki
			if (window.tinyMCE && window.tinyMCE.get('wpmzf-note-content')) {
				const noteEditor = window.tinyMCE.get('wpmzf-note-content');
				noteEditor.setContent('');
				noteEditor.hide();
			}
			
			// Reset edytora e-mail
			if (window.tinyMCE && window.tinyMCE.get('email-content')) {
				const emailEditor = window.tinyMCE.get('email-content');
				emailEditor.setContent('');
			}
		} catch (error) {
			console.warn('Błąd podczas resetowania edytora:', error);
			// Fallback - reset textarey
			$('#wpmzf-note-content').val('');
			$('#email-content').val('');
		}

		// Reset stanu edytora notatki
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
               	${file.type.startsWith('audio/') ? `<div class="transcribe-option"><label><input type="checkbox" class="transcribe-checkbox" checked> Transkrybuj</label></div>` : ''}
				</div>
			`;
		});

		attachmentsPreviewContainer.html(previewHtml);
	}

	// --- Obsługa załączników ---
	attachFileBtn.on('click', function () {
		attachmentInput.trigger('click');
	});

	attachmentInput.on('change', function (e) {
		for (const file of e.target.files) {
			// Sprawdź walidację pliku
			if (!isAllowedFileType(file)) {
				continue; // Pomiń nieodpowiednie pliki
			}

			// Sprawdź czy plik już nie został dodany
			const alreadyExists = filesToUpload.some(existingFile =>
				existingFile.name === file.name && existingFile.size === file.size
			);

			if (!alreadyExists) {
				filesToUpload.push(file);
			}
		}

		renderAttachmentsPreview();
		// Resetowanie wartości inputu, aby umożliwić ponowne dodanie tego samego pliku
		$(this).val('');
	});

	// Obsługa usuwania załączników z podglądu
	attachmentsPreviewContainer.on('click', '.remove-attachment', function () {
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
			success: function (response) {
				console.log('Activities response:', response);
				if (response.success) {
					const activities = response.data.activities || response.data;
					if (activities && activities.length > 0) {
						renderTimeline(activities);
					} else {
						timelineContainer.html('<p><em>Brak zarejestrowanych aktywności. Dodaj pierwszą!</em></p>');
					}
				} else {
					console.error('Error loading activities:', response.data);
					const errorMsg = response.data && response.data.message ? response.data.message : 'Nieznany błąd';
					timelineContainer.html('<p><em>Błąd: ' + errorMsg + '</em></p>');
				}
			},
			error: function (xhr, status, error) {
				console.error('AJAX error:', status, error, xhr);
				timelineContainer.html('<p><em>Błąd podczas ładowania aktywności.</em></p>');
			}
		});
	}

	// Renderowanie timeline aktywności
	async function renderTimeline(activities) {
		console.log('Rendering timeline with activities:', activities);

		// Sprawdź czy activities jest tablicą
		if (!Array.isArray(activities)) {
			console.error('Activities is not an array:', activities);
			timelineContainer.html('<p style="color:red;">Błąd: nieprawidłowy format danych aktywności.</p>');
			return;
		}

		if (!activities || activities.length === 0) {
			timelineContainer.html('<p><em>Brak zarejestrowanych aktywności. Dodaj pierwszą!</em></p>');
			return;
		}

		// Sortowanie aktywności - najnowsze na górze
		activities.sort(function (a, b) {
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
				activity.attachments.forEach(function (att) {
					const attachmentIcon = getIconForMimeType(att.mime_type);
					let previewHtml = '';

					if (att.thumbnail_url) {
						previewHtml = `<img src="${att.thumbnail_url}" alt="Podgląd załącznika">`;
					} else {
						previewHtml = `<span class="dashicons ${attachmentIcon}" title="${att.mime_type}"></span>`;
					}

					// Generuj HTML dla transkrypcji
					let transcriptionHtml = '';
					if (att.transcription) {
						switch(att.transcription.status) {
							case 'pending':
							case 'processing':
								transcriptionHtml = `<div class="transcription-status pending">⌛ Oczekuje na transkrypcję...</div>`;
								break;
							case 'completed':
								transcriptionHtml = `
									<div class="transcription-result">
										<strong>Transkrypcja:</strong>
										<p>${window.escapeHtml(att.transcription.text_preview)}</p>
										<a href="#" class="view-full-transcription" data-attachment-id="${att.id}">Zobacz całość</a>
									</div>
								`;
								break;
							case 'failed':
								transcriptionHtml = `<div class="transcription-status failed">❌ Transkrypcja nie powiodła się.</div>`;
								break;
						}
					}

					attachmentsHtml += `
						<li data-attachment-id="${att.id}">
							<a href="${att.url}" target="_blank">
							   ${previewHtml}
							   <span>${att.filename}</span>
							</a>
							<span class="dashicons dashicons-trash delete-attachment" title="Usuń załącznik"></span>
							${transcriptionHtml}
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

			// Zabezpieczenie na wypadek braku avatara
			const avatarUrl = activity.avatar || '/wp-includes/images/media/default.png';

			html += `
				<div class="timeline-item" data-activity-id="${activity.id}">
					<div class="timeline-avatar">
						<img src="${avatarUrl}" alt="${activity.author}" onerror="this.style.display='none';">
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
								<div id="activity-edit-${activity.id}-container">
									<textarea id="activity-edit-${activity.id}" class="activity-edit-textarea">${activity.content}</textarea>
								</div>
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
			// Update uploadPromises to return objects with id and transcribe flag
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
						xhr.upload.addEventListener('progress', function (e) {
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
						const attId = response.data.attachment_id;
						const shouldTranscribe = previewItem.find('.transcribe-checkbox').is(':checked');
						return { id: attId, transcribe: shouldTranscribe };
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

		if (!editorContent || editorContent.trim() === '') {
			showNotification('Proszę wprowadzić treść aktywności.', 'error');
			submitButton.text(originalButtonText).prop('disabled', false);
			attachFileBtn.prop('disabled', false);
			isSubmitting = false;
			return;
		}

		submitButton.text('Dodawanie aktywności...');
		// Build activityData with transcription_ids
		const activityData = {
			action: 'add_wpmzf_activity',
			security: securityNonce,
			company_id: companyId,
			content: editorContent,
			activity_type: $('#wpmzf-activity-type').val(),
			activity_date: dateField.val(),
			attachment_ids: uploadedAttachmentIds.map(item => item.id),
			transcription_ids: uploadedAttachmentIds.filter(item => item.transcribe).map(item => item.id)
		};

		// Dodaj pola email jeśli typ aktywności to email
		if ($('#wpmzf-activity-type').val() === 'email') {
			activityData.email_to = $('input[name="email_to"]').val();
			activityData.email_cc = $('input[name="email_cc"]').val();
			activityData.email_bcc = $('input[name="email_bcc"]').val();
			activityData.email_subject = $('input[name="email_subject"]').val();
		}

		console.log('Company activity data to submit:', activityData);

		// AJAX request
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: activityData,
			success: function (response) {
				console.log('Company activity response:', response);
				if (response.success) {
					// Usuń przesłane pliki z pending uploads (zostały przypisane)
					uploadedAttachmentIds.forEach(id => {
						pendingUploads.delete(id);
					});

					// Reset edytora przy użyciu dedykowanej funkcji
					resetEditor();

					form[0].reset();
					filesToUpload = [];
					renderAttachmentsPreview();
					setDefaultActivityDateTime();
					loadActivities();
					showNotification('Aktywność została dodana pomyślnie!', 'success');
				} else {
					showNotification('Błąd: ' + (response.data ? response.data.message : 'Nieznany błąd'), 'error');
				}
			},
			error: function (xhr, status, error) {
				console.error('Company activity AJAX error:', status, error, xhr);
				showNotification('Wystąpił krytyczny błąd serwera przy dodawaniu aktywności.', 'error');
			},
			complete: function () {
				submitButton.text(originalButtonText).prop('disabled', false);
				attachFileBtn.prop('disabled', false);
				isSubmitting = false; // Reset flagi
			}
		});
	});

	// === NOWE HANDLERY DLA FORMULARZY ZAKŁADEK ===
	
	// Handler dla formularza "Dodaj notatkę"
	noteForm.on('submit', function(e) {
		e.preventDefault();
		
		// Pobierz treść z edytora TinyMCE
		let content = '';
		if (window.tinyMCE && window.tinyMCE.get('wpmzf-note-content')) {
			content = window.tinyMCE.get('wpmzf-note-content').getContent();
		} else {
			content = $('#wpmzf-note-content').val();
		}
		
		if (!content.trim()) {
			showNotification('Proszę wpisać treść notatki.', 'error');
			return;
		}
		
		const formData = new FormData(this);
		formData.append('action', 'add_wpmzf_activity');
		formData.append('security', securityNonce);
		formData.append('content', content);
		
		noteSubmitButton.prop('disabled', true).text('Dodawanie...');
		
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: formData,
			processData: false,
			contentType: false,
			success: function(response) {
				if (response.success) {
					noteForm[0].reset();
					resetEditor();
					setDefaultActivityDateTime();
					loadActivities();
					showNotification('Notatka została dodana pomyślnie!', 'success');
				} else {
					showNotification('Błąd: ' + (response.data ? response.data.message : 'Nieznany błąd'), 'error');
				}
			},
			error: function(xhr, status, error) {
				console.error('Note AJAX error:', status, error, xhr);
				showNotification('Wystąpił błąd przy dodawaniu notatki.', 'error');
			},
			complete: function() {
				noteSubmitButton.prop('disabled', false).text('Dodaj notatkę');
			}
		});
	});

	// Handler dla formularza "Wyślij e-mail"
	emailForm.on('submit', function(e) {
		e.preventDefault();
		
		// Pobierz treść z edytora TinyMCE
		let emailContent = '';
		if (window.tinyMCE && window.tinyMCE.get('email-content')) {
			emailContent = window.tinyMCE.get('email-content').getContent();
		} else {
			emailContent = $('#email-content').val();
		}
		
		const emailTo = $('input[name="email_to"]', this).val();
		const emailSubject = $('input[name="email_subject"]', this).val();
		
		if (!emailTo || !emailSubject) {
			showNotification('Pola "Do" i "Temat" są wymagane.', 'error');
			return;
		}
		
		const formData = new FormData(this);
		formData.append('action', 'add_wpmzf_activity');
		formData.append('security', securityNonce);
		formData.append('content', emailContent);
		formData.append('activity_type', 'email');
		
		emailSubmitButton.prop('disabled', true).text('Wysyłanie...');
		
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: formData,
			processData: false,
			contentType: false,
			success: function(response) {
				if (response.success) {
					emailForm[0].reset();
					// Reset edytora e-mail
					if (window.tinyMCE && window.tinyMCE.get('email-content')) {
						window.tinyMCE.get('email-content').setContent('');
					}
					loadActivities();
					showNotification('E-mail został dodany do kolejki wysyłania!', 'success');
				} else {
					showNotification('Błąd: ' + (response.data ? response.data.message : 'Nieznany błąd'), 'error');
				}
			},
			error: function(xhr, status, error) {
				console.error('Email AJAX error:', status, error, xhr);
				showNotification('Wystąpił błąd przy wysyłaniu e-maila.', 'error');
			},
			complete: function() {
				emailSubmitButton.prop('disabled', false).text('Wyślij e-mail');
			}
		});
	});

	// === INICJALIZACJA DRAG & DROP I CLIPBOARD ===

	// Funkcja sprawdzająca czy typ pliku jest dozwolony
	function isAllowedFileType(file) {
		const allowedTypes = [
			'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp',
			'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
			'text/plain', 'text/csv', 'application/zip', 'application/x-rar-compressed'
		];

		const maxSize = 50 * 1024 * 1024; // 50MB

		if (!allowedTypes.includes(file.type)) {
			showNotification('Niedozwolony typ pliku: ' + file.type, 'error');
			return false;
		}

		if (file.size > maxSize) {
			showNotification('Plik jest za duży. Maksymalny rozmiar to 50MB.', 'error');
			return false;
		}

		return true;
	}

	// Funkcja inicjalizacji drag & drop
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

		let dragCounter = 0;

		// Obsługa dragenter
		$body.on('dragenter', function (e) {
			e.preventDefault();
			dragCounter++;

			if (dragCounter === 1) {
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

	// Funkcja inicjalizacji wklejania ze schowka
	function initializeClipboardPaste() {
		console.log('Company: Inicjalizacja clipboard paste...');

		// Test - sprawdź czy funkcja w ogóle się wywołuje
		$(document).on('keydown', function (e) {
			if (e.ctrlKey && e.key === 'v') {
				console.log('Company: CTRL+V wykryte!');
			}
		});

		$(document).on('paste', function (e) {
			console.log('Company: Wykryto zdarzenie paste');

			// Sprawdź czy jesteśmy na stronie z formularzem aktywności
			if (!$('#wpmzf-add-activity-form').length) {
				console.log('Company: Brak formularza aktywności na stronie');
				return;
			}

			console.log('Company: Formularz aktywności znaleziony');

			const clipboardData = e.originalEvent.clipboardData;
			if (!clipboardData || !clipboardData.items) {
				console.log('Company: Brak danych w schowku');
				return;
			}

			console.log('Company: Dane schowka dostępne, items:', clipboardData.items.length);

			// Sprawdź czy w schowku są pliki
			for (let i = 0; i < clipboardData.items.length; i++) {
				const item = clipboardData.items[i];
				console.log('Company: Item', i, 'type:', item.type);

				if (item.type.indexOf('image/') === 0) {
					console.log('Company: Znaleziono obraz w schowku');
					e.preventDefault();

					const file = item.getAsFile();
					if (file) {
						console.log('Company: Plik pobrany ze schowka:', file.name, file.size);

						// Utwórz lepszą nazwę dla screenshot'u
						const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
						const newFile = new File([file], `screenshot-${timestamp}.png`, {
							type: file.type,
							lastModified: Date.now()
						});

						console.log('Company: Nowy plik utworzony:', newFile.name);

						// Sprawdź walidację pliku
						if (isAllowedFileType(newFile)) {
							console.log('Company: Plik przeszedł walidację, dodawanie do listy');
							filesToUpload.push(newFile);
							renderAttachmentsPreview();

							// Przewiń do formularza aktywności
							$('#wpmzf-add-activity-form')[0].scrollIntoView({
								behavior: 'smooth',
								block: 'center'
							});

							// Pokaż powiadomienie
							showNotification('Zdjęcie zostało dodane ze schowka', 'success');
							console.log('Company: Screenshot dodany pomyślnie');
						} else {
							console.log('Company: Plik nie przeszedł walidacji');
						}
					} else {
						console.log('Company: Nie udało się pobrać pliku ze schowka');
					}
				}
			}
		});
	}

	// Inicjalizacja funkcji
	console.log('Company: Inicjalizacja drag & drop i clipboard paste');
	initializeDragAndDrop();
	initializeClipboardPaste();
	console.log('Company: Drag & drop i clipboard paste zainicjalizowane');

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

	// Wyświetlanie pełnej transkrypcji
	timelineContainer.on('click', '.view-full-transcription', function (e) {
		e.preventDefault();
		const attachmentId = $(this).data('attachment-id');
		
		// Pobierz pełną transkrypcję
		$.post(ajaxurl, {
			action: 'get_wpmzf_full_transcription',
			security: securityNonce,
			attachment_id: attachmentId
		}).done(response => {
			if (response.success && response.data.transcription_text) {
				// Wyświetl modal z pełną transkrypcją
				const modal = $(`
					<div class="wpmzf-modal-overlay">
						<div class="wpmzf-modal">
							<div class="wpmzf-modal-header">
								<h3>Pełna transkrypcja</h3>
								<span class="wpmzf-modal-close">&times;</span>
							</div>
							<div class="wpmzf-modal-body">
								<div class="transcription-full-text">
									${window.escapeHtml(response.data.transcription_text).replace(/\n/g, '<br>')}
								</div>
							</div>
							<div class="wpmzf-modal-footer">
								<button class="button" onclick="$(this).closest('.wpmzf-modal-overlay').remove()">Zamknij</button>
							</div>
						</div>
					</div>
				`);
				
				$('body').append(modal);
				
				// Obsługa zamykania modala
				modal.on('click', '.wpmzf-modal-close, .wpmzf-modal-overlay', function(e) {
					if (e.target === this) {
						modal.remove();
					}
				});
			} else {
				alert('Nie udało się pobrać transkrypcji: ' + (response.data?.message || 'Nieznany błąd'));
			}
		}).fail(() => {
			alert('Błąd serwera podczas pobierania transkrypcji.');
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
		const activityId = $(this).closest('.timeline-item').data('activity-id');
		const editorId = 'activity-edit-' + activityId;
		
		contentDiv.find('.activity-content-display').hide();
		contentDiv.find('.activity-content-edit').show();
		
		// Inicjalizuj TinyMCE dla edycji
		setTimeout(function() {
			initActivityEditTinyMCE(editorId);
		}, 200);
	});

	// Anulowanie edycji aktywności
	timelineContainer.on('click', '.cancel-activity-edit', function () {
		const contentDiv = $(this).closest('.timeline-content');
		const activityId = $(this).closest('.timeline-item').data('activity-id');
		const editorId = 'activity-edit-' + activityId;
		
		// Usuń TinyMCE
		if (typeof tinymce !== 'undefined') {
			tinymce.remove('#' + editorId);
		}
		
		contentDiv.find('.activity-content-edit').hide();
		contentDiv.find('.activity-content-display').show();
	});

	// Zapisywanie edycji aktywności
	timelineContainer.on('click', '.save-activity-edit', async function () {
		const button = $(this);
		const contentDiv = button.closest('.timeline-content');
		const activityId = button.closest('.timeline-item').data('activity-id');
		const editorId = 'activity-edit-' + activityId;
		
		// Sprawdź czy TinyMCE jest aktywny i zainicjalizowany
		let newContent = '';
		if (typeof tinymce !== 'undefined') {
			const editor = tinymce.get(editorId);
			if (editor && editor.initialized) {
				// Upewnij się, że zawartość została zsynchronizowana
				editor.save();
				newContent = editor.getContent();
			} else {
				// Fallback do textarea
				newContent = jQuery('#' + editorId).val() || '';
			}
		} else {
			// TinyMCE nie jest dostępny
			newContent = jQuery('#' + editorId).val() || '';
		}

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
				
				// Usuń TinyMCE po pomyślnym zapisie
				if (typeof tinymce !== 'undefined') {
					tinymce.remove('#' + editorId);
				}
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

	// Inicjalizacja zadań - tylko jeśli companyId jest dostępne
	if (companyId && companyId !== '' && companyId !== 'undefined') {
		loadTasks();
	}

	// === DODAWANIE ZADANIA ===
	if (taskForm.length > 0) {
		taskForm.on('submit', function (e) {
			e.preventDefault();

			const taskTitle = taskTitleInput.val().trim();
			const taskDueDate = taskDueDateInput.val();
			const assignedUser = $('#wpmzf-task-assigned-user').val();

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
					assigned_user: assignedUser,
					wpmzf_task_security: taskSecurityNonce
				},
				success: function (response) {
					if (response.success) {
						taskTitleInput.val('');
						taskDueDateInput.val('');
						$('#wpmzf-task-assigned-user').val('');
						loadTasks();
						showTaskMessage('Zadanie zostało dodane.', 'success');
					} else {
						showTaskMessage('Błąd: ' + (response.data ? response.data.message : 'Nieznany błąd'), 'error');
					}
				},
				error: function () {
					showTaskMessage('Błąd podczas dodawania zadania.', 'error');
				},
				complete: function () {
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
			success: function (response) {
				if (response.success) {
					openTasksList.html(response.data.open_tasks || '<p><em>Brak otwartych zadań.</em></p>');
					closedTasksList.html(response.data.closed_tasks || '<p><em>Brak zakończonych zadań.</em></p>');
				} else {
					openTasksList.html('<p><em>Brak zadań dla tej firmy.</em></p>');
					closedTasksList.html('<p><em>Brak zakończonych zadań.</em></p>');
				}
			},
			error: function (xhr, status, error) {
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
		setTimeout(function () {
			$('.task-message').fadeOut(500, function () {
				$(this).remove();
			});
		}, 3000);
	}

	// Obsługa przełączania zakończonych zadań
	toggleClosedTasks.on('click', function () {
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
	$(document).on('click', '.task-actions .dashicons', function () {
		const $this = $(this);
		const action = $this.data('action');
		const taskId = $this.closest('.task-item').data('task-id');

		switch (action) {
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
			success: function (response) {
				if (response.success) {
					loadTasks();
					showTaskMessage('Status zadania został zmieniony.', 'success');
				} else {
					showTaskMessage('Błąd: ' + (response.data ? response.data.message : 'Nieznany błąd'), 'error');
				}
			},
			error: function () {
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
			success: function (response) {
				if (response.success) {
					loadTasks();
					showTaskMessage('Zadanie zostało usunięte.', 'success');
				} else {
					showTaskMessage('Błąd: ' + (response.data ? response.data.message : 'Nieznany błąd'), 'error');
				}
			},
			error: function () {
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
		inputField.on('keydown', function (e) {
			if (e.which === 13) { // Enter
				saveTaskTitle(taskId, $(this).val());
			} else if (e.which === 27) { // Escape
				$(this).replaceWith('<span class="task-title">' + escapeHtml(currentTitle) + '</span>');
			}
		});

		// Obsługa utraty fokusa
		inputField.on('blur', function () {
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
			success: function (response) {
				if (response.success) {
					loadTasks();
					showTaskMessage('Tytuł zadania został zaktualizowany.', 'success');
				} else {
					loadTasks();
					showTaskMessage('Błąd: ' + (response.data ? response.data.message : 'Nieznany błąd'), 'error');
				}
			},
			error: function () {
				loadTasks();
				showTaskMessage('Błąd podczas aktualizacji tytułu zadania.', 'error');
			}
		});
	}

	// === OBSŁUGA LINKÓW DO ZLECEŃ ===
	$(document).on('click', '.project-link', function (e) {
		e.preventDefault();

		const projectId = $(this).data('project-id');
		if (projectId) {
			const projectUrl = wpmzfCompanyView.adminUrl + 'admin.php?page=wpmzf_view_project&project_id=' + projectId;
			window.location.href = projectUrl;
		}
	});

	console.log('Company view JS initialized for company ID:', companyId);

	// === WAŻNE LINKI ===

	// Ładowanie linków przy inicjalizacji - tylko jeśli companyId jest dostępne
	if (companyId && companyId !== '' && companyId !== 'undefined') {
		loadImportantLinks();
	}

	// Obsługa formularza dodawania/edycji linku
	const linkForm = $('#wpmzf-important-link-form');
	const linkFormContainer = $('#important-link-form');
	const addLinkBtn = $('#add-important-link-btn');
	const cancelLinkBtn = $('#cancel-link-form');
	const linkSubmitText = $('#link-submit-text');
	const editLinkId = $('#edit-link-id');

	// Debug - sprawdź czy elementy istnieją
	console.log('Link form elements check:');
	console.log('linkForm:', linkForm.length);
	console.log('linkFormContainer:', linkFormContainer.length);
	console.log('addLinkBtn:', addLinkBtn.length);
	console.log('cancelLinkBtn:', cancelLinkBtn.length);

	// Pokazywanie formularza dodawania linku
	addLinkBtn.on('click', function() {
		console.log('Add link button clicked'); // Debug
		resetLinkForm();
		linkFormContainer.slideDown();
		$('#link-url').focus();
	});

	// Anulowanie formularza
	cancelLinkBtn.on('click', function() {
		console.log('Cancel link button clicked'); // Debug
		linkFormContainer.slideUp();
		resetLinkForm();
	});

	// Obsługa wysyłania formularza linku
	linkForm.on('submit', function(e) {
		e.preventDefault();

		const linkId = editLinkId.val();
		const url = $('#link-url').val().trim();
		const customTitle = $('#link-custom-title').val().trim();

		if (!url) {
			alert('Proszę podać URL linku');
			return;
		}

		// Sprawdzenie czy URL jest prawidłowy
		try {
			new URL(url);
		} catch(e) {
			alert('Proszę podać prawidłowy URL (np. https://example.com)');
			return;
		}

		const isEdit = linkId && linkId !== '';
		const action = isEdit ? 'wpmzf_update_important_link' : 'wpmzf_add_important_link';

		const formData = {
			action: action,
			security: securityNonce,
			url: url,
			custom_title: customTitle,
			object_id: companyId,
			object_type: 'company'
		};

		if (isEdit) {
			formData.link_id = linkId;
		}

		// Blokowanie formularza
		const submitBtn = linkForm.find('button[type="submit"]');
		const originalText = linkSubmitText.text();
		submitBtn.prop('disabled', true);
		linkSubmitText.text(isEdit ? 'Aktualizuję...' : 'Dodaję...');

		const ajaxUrl = (typeof wpmzfCompanyView !== 'undefined' && wpmzfCompanyView.ajaxUrl) ? 
			wpmzfCompanyView.ajaxUrl : ajaxurl;

		$.ajax({
			url: ajaxUrl,
			type: 'POST',
			data: formData,
			success: function(response) {
				if (response.success) {
					showNotification(response.data.message, 'success');
					loadImportantLinks();
					linkFormContainer.slideUp();
					resetLinkForm();
				} else {
					showNotification(response.data.message || 'Wystąpił błąd', 'error');
				}
			},
			error: function() {
				showNotification('Wystąpił błąd podczas komunikacji z serwerem', 'error');
			},
			complete: function() {
				submitBtn.prop('disabled', false);
				linkSubmitText.text(originalText);
			}
		});
	});

	// Delegowane obsługa akcji na linkach
	$(document).on('click', '.edit-important-link', function(e) {
		e.preventDefault();
		
		const linkItem = $(this).closest('.important-link-item');
		const linkId = linkItem.data('link-id');
		const url = linkItem.data('url');
		const customTitle = linkItem.data('custom-title') || '';

		// Wypełnienie formularza danymi do edycji
		editLinkId.val(linkId);
		$('#link-url').val(url);
		$('#link-custom-title').val(customTitle);
		linkSubmitText.text('Aktualizuj link');

		// Pokazanie formularza
		linkFormContainer.slideDown();
		$('#link-url').focus();
	});

	$(document).on('click', '.delete-important-link', function(e) {
		e.preventDefault();
		
		if (!confirm('Czy na pewno chcesz usunąć ten link?')) {
			return;
		}

		const linkItem = $(this).closest('.important-link-item');
		const linkId = linkItem.data('link-id');

		const ajaxUrl = (typeof wpmzfCompanyView !== 'undefined' && wpmzfCompanyView.ajaxUrl) ? 
			wpmzfCompanyView.ajaxUrl : ajaxurl;

		$.ajax({
			url: ajaxUrl,
			type: 'POST',
			data: {
				action: 'wpmzf_delete_important_link',
				security: securityNonce,
				link_id: linkId,
				object_type: 'company'
			},
			success: function(response) {
				if (response.success) {
					linkItem.fadeOut(300, function() {
						$(this).remove();
						// Sprawdź czy jest to ostatni link
						if ($('.important-link-item').length === 0) {
							$('#important-links-container').html('<p class="no-important-links">Brak ważnych linków. Kliknij "Dodaj link" aby dodać pierwszy.</p>');
						}
					});
					showNotification(response.data.message, 'success');
				} else {
					showNotification(response.data.message || 'Wystąpił błąd', 'error');
				}
			},
			error: function() {
				showNotification('Wystąpił błąd podczas komunikacji z serwerem', 'error');
			}
		});
	});

	function loadImportantLinks() {
		console.log('Loading important links for company:', companyId); // Debug
		
		if (!companyId || companyId === '' || companyId === 'undefined') {
			console.error('Cannot load links - invalid company ID');
			$('#important-links-container').html('<p class="important-links-loading" style="color: #d63638;">Błąd: Nieprawidłowe ID firmy</p>');
			return;
		}

		const ajaxUrl = (typeof wpmzfCompanyView !== 'undefined' && wpmzfCompanyView.ajaxUrl) ? 
			wpmzfCompanyView.ajaxUrl : ajaxurl;

		$.ajax({
			url: ajaxUrl,
			type: 'POST',
			data: {
				action: 'wpmzf_get_important_links',
				security: securityNonce,
				object_id: companyId,
				object_type: 'company'
			},
			success: function(response) {
				console.log('Links AJAX response:', response); // Debug
				if (response.success) {
					renderImportantLinks(response.data.links);
				} else {
					$('#important-links-container').html('<p class="important-links-loading" style="color: #d63638;">Błąd ładowania linków: ' + (response.data.message || 'Nieznany błąd') + '</p>');
				}
			},
			error: function(xhr, status, error) {
				console.error('Links AJAX error:', status, error); // Debug
				$('#important-links-container').html('<p class="important-links-loading" style="color: #d63638;">Błąd podczas ładowania linków: ' + error + '</p>');
			}
		});
	}

	function renderImportantLinks(links) {
		const container = $('#important-links-container');
		
		if (!links || links.length === 0) {
			container.html('<p class="no-important-links">Brak ważnych linków. Kliknij "Dodaj link" aby dodać pierwszy.</p>');
			return;
		}

		let html = '';
		links.forEach(function(link) {
			const faviconHtml = link.favicon ? 
				`<img src="${escapeHtml(link.favicon)}" alt="Ikona" onerror="this.style.display='none'">` :
				'🔗';
			
			html += `
				<div class="important-link-item" data-link-id="${link.id}" data-url="${escapeHtml(link.url)}" data-custom-title="${escapeHtml(link.custom_title || '')}">
					<div class="important-link-favicon">
						${faviconHtml}
					</div>
					<div class="important-link-content">
						<a href="${escapeHtml(link.url)}" target="_blank" class="important-link-title" rel="noopener noreferrer">
							${escapeHtml(link.title)}
						</a>
						<p class="important-link-url">${escapeHtml(link.url)}</p>
					</div>
					<div class="important-link-actions">
						<button type="button" class="important-link-action edit-important-link" title="Edytuj link">
							✏️
						</button>
						<button type="button" class="important-link-action delete-important-link delete" title="Usuń link">
							🗑️
						</button>
					</div>
				</div>
			`;
		});
		
		container.html(html);
	}

	function resetLinkForm() {
		editLinkId.val('');
		$('#link-url').val('');
		$('#link-custom-title').val('');
		linkSubmitText.text('Dodaj link');
	}
});
