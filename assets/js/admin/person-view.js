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

// === FUNKCJONALNOŚĆ COMPANY SELECT ===
jQuery(document).ready(function ($) {
	// --- Zmienne ---
	const personId = $('input[name="person_id"]').val();
	console.log('Person ID found:', personId); // Debug
	
	// Używamy zmiennych z wp_localize_script jeśli są dostępne, w przeciwnym razie fallback
	const securityNonce = (typeof wpmzfPersonView !== 'undefined' && wpmzfPersonView.nonce) ?
		wpmzfPersonView.nonce : $('#wpmzf_security').val();
	const taskSecurityNonce = (typeof wpmzfPersonView !== 'undefined' && wpmzfPersonView.taskNonce) ?
		wpmzfPersonView.taskNonce : $('#wpmzf_task_security').val();

	console.log('Security nonce:', securityNonce); // Debug
	console.log('Task security nonce:', taskSecurityNonce); // Debug

	// Sprawdź czy personId jest prawidłowe
	if (!personId || personId === '' || personId === 'undefined') {
		console.error('Person ID not found or invalid!');
		return;
	}

	const form = $('#wpmzf-add-activity-form');
	const timelineContainer = $('#wpmzf-activity-timeline');
	const submitButton = $('#wpmzf-submit-activity-btn');
	const dateField = $('#wpmzf-activity-date');
	const attachFileBtn = $('#wpmzf-attach-file-btn');
	const attachmentInput = $('#wpmzf-activity-files-input'); // Upewnij się, że ID jest poprawne
	const attachmentsPreviewContainer = $('#wpmzf-attachments-preview-container'); // Poprawiony selektor

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

	// === INICJALIZACJA COMPANY SELECT ===
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

	// === INICJALIZACJA PERSON REFERRER SELECT ===
	// Sprawdzamy, czy na stronie istnieje element #person_referrer_select
	if ($('#person_referrer_select').length) {

		$('#person_referrer_select').select2({
			width: '100%',
			placeholder: 'Wybierz polecającego',
			allowClear: true,
			// Używamy wyszukiwania mieszanego - osoby i firmy
			ajax: {
				url: ajaxurl,
				dataType: 'json',
				delay: 250,

				data: function (params) {
					return {
						action: 'wpmzf_search_referrers', // Nowa akcja dla polecających
						security: $('#wpmzf_security').val(),
						term: params.term
					};
				},

				processResults: function (data, params) {
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

			minimumInputLength: 2,

			language: {
				inputTooShort: function (args) {
					var remainingChars = args.minimum - args.input.length;
					return 'Wpisz jeszcze ' + remainingChars + ' znaki';
				},
				loadingMore: function () {
					return 'Wczytywanie wyników…';
				},
				noResults: function () {
					return 'Nie znaleziono osoby lub firmy.';
				},
				searching: function () {
					return 'Szukanie…';
				}
			}
		});
	}

	// === INICJALIZACJA FUNKCJONALNOŚCI AKTYWNOŚCI ===

	// --- Inicjalizacja ---
	function setDefaultActivityDateTime() {
		const now = new Date();
		now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
		dateField.val(now.toISOString().slice(0, 16));
	}

	setDefaultActivityDateTime();
	loadActivities();

	// --- Dynamiczne pola dla typu aktywności "E-mail" ---
	const activityTypeSelect = $('#wpmzf-activity-type');
	const activityEditorContainer = $('#wpmzf-activity-main-editor');

	// Stwórz kontener na pola e-mail (początkowo ukryty)
	const emailFieldsContainer = $(`
		<div id="wpmzf-email-fields" style="display: none; margin-top: 15px; display: flex; flex-direction: column; gap: 10px;">
			<input type="text" name="email_to" placeholder="Do:" class="large-text" required>
			<input type="text" name="email_cc" placeholder="DW:" class="large-text">
			<input type="text" name="email_bcc" placeholder="UDW:" class="large-text">
			<input type="text" name="email_subject" placeholder="Temat wiadomości" class="large-text" required>
		</div>
	`).insertAfter(activityEditorContainer);

	activityTypeSelect.on('change', function() {
		if ($(this).val() === 'email') {
			emailFieldsContainer.slideDown(200);
			// Spróbuj automatycznie wypełnić pole "Do" adresem e-mail osoby
			const primaryEmail = $('[data-field="person_emails"] .contact-item.is-primary a').attr('href')?.replace('mailto:', '') || '';
			if(primaryEmail) {
				emailFieldsContainer.find('input[name="email_to"]').val(primaryEmail);
			}
		} else {
			emailFieldsContainer.slideUp(200);
		}
	});

	// Funkcja renderowania podglądu załączników (musi być zdefiniowana wcześnie)
	function renderAttachmentsPreview() {
		// Jeśli nie ma plików, ukryj cały kontener podglądu
		if (filesToUpload.length === 0) {
			attachmentsPreviewContainer.hide().empty();
			return;
		}

		// Pokaż kontener i wyrenderuj załączniki
		attachmentsPreviewContainer.show().css('display', 'flex');

		let html = '';
		filesToUpload.forEach((file, index) => {
			const fileSize = formatFileSize(file.size);
			const fileName = file.name;

			let thumbnailHtml = '';
			if (file.type.startsWith('image/')) {
				const objectURL = URL.createObjectURL(file);
				thumbnailHtml = `<div class="attachment-thumbnail"><img src="${objectURL}" alt="Podgląd"></div>`;
			} else {
				const iconClass = getIconForMimeType(file.type);
				thumbnailHtml = `<div class="attachment-thumbnail"><span class="dashicons ${iconClass} file-icon"></span></div>`;
			}

			html += `
				<div class="wpmzf-attachment-preview-item attachment-item" data-file-index="${index}">
					<div class="file-info">
						${thumbnailHtml}
						<div class="attachment-info">
							<div class="attachment-name">${window.escapeHtml(fileName)}</div>
							<div class="attachment-size">${fileSize}</div>
						</div>
					</div>
					<div class="file-actions">
						<div class="attachment-progress" style="display: none;">
							<div class="attachment-progress-bar"></div>
							<span class="attachment-progress-text">0%</span>
						</div>
						${file.type.startsWith('audio/') ? `
							<div class="transcribe-option">
								<label>
									<input type="checkbox" class="transcribe-checkbox" data-file-index="${index}" checked> 
									Transkrybuj
								</label>
							</div>
						` : ''}
						<span class="dashicons dashicons-no-alt remove-attachment" title="Usuń załącznik"></span>
					</div>
				</div>
			`;
		});

		attachmentsPreviewContainer.html(html);
	}

	// Ukryj podgląd załączników przy inicjalizacji
	renderAttachmentsPreview();

	// Inicjalizuj pozostałe funkcjonalności
	initializeClipboardPaste();
	initializeDragAndDrop();

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
							paste_postprocess: function (plugin, args) {
								// Przetwarzanie Markdown po wklejeniu
								let content = args.node.innerHTML;

								// Konwersja podstawowych znaczników Markdown na HTML
								content = content
									// Bold **text** lub __text__
									.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
									.replace(/__([^_]+)__/g, '<strong>$1</strong>')
									// Italic *text* lub _text_
									.replace(/(?<!\*)\*([^*]+)\*(?!\*)/g, '<em>$1</em>')
									.replace(/(?<!_)_([^_]+)_(?!_)/g, '<em>$1</em>')
									// Code `text`
									.replace(/`([^`]+)`/g, '<code>$1</code>')
									// Links [text](url)
									.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2">$1</a>');

								args.node.innerHTML = content;
								console.log('Paste postprocess - converted content:', content);
							},
							valid_elements: 'p,br,strong,em,u,b,i,a[href|title|target],ul,ol,li,blockquote,code,span[style|class],h1,h2,h3,h4,h5,h6,div[style|class]',
							forced_root_block: 'p',
							force_br_newlines: false,
							force_p_newlines: true,
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

								// Dodatkowa obsługa wklejania z zachowaniem formatowania
								editor.on('paste', function (e) {
									setTimeout(function () {
										// Pobierz zawartość i przetwórz dodatkowe formatowanie
										let content = editor.getContent();

										// Dodatkowe przetwarzanie dla lepszego zachowania formatowania
										content = content
											// Napraw pogrubione tekst, który mógł zostać źle skonwertowany
											.replace(/<b\s*>/gi, '<strong>')
											.replace(/<\/b>/gi, '</strong>')
											// Napraw kursywę
											.replace(/<i\s*>/gi, '<em>')
											.replace(/<\/i>/gi, '</em>')
											// Usuń zbędne atrybuty style, które mogą powodować problemy
											.replace(/style\s*=\s*["'][^"']*["']/gi, '');

										editor.setContent(content);
									}, 10);
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
		}, 300); // Zwiększony timeout żeby dać więcej czasu na rendering kontenera

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

	// --- Podgląd na żywo dla edytora WYSIWYG ---
	let previewTimeout;

	// Inicjalizacja TinyMCE event listeners
	function initializeWysiwygListeners() {
		// Nasłuchuj na inicjalizację TinyMCE
		$(document).on('tinymce-editor-init', function (event, editor) {
			if (editor.id === 'wpmzf-activity-content') {
				// Słuchaj zmian w edytorze
				editor.on('input keyup paste', function () {
					const content = editor.getContent();
					clearTimeout(previewTimeout);

					if (content.trim() === '') {
						if (previewContainer) {
							previewContainer.hide();
						}
						return;
					}

					// Sprawdź czy tekst zawiera linki
					const urlRegex = /(https?:\/\/[^\s<>"]+)/gi;
					const textContent = editor.getContent({ format: 'text' });
					const hasLinks = urlRegex.test(textContent);

					if (!hasLinks) {
						if (previewContainer) {
							previewContainer.hide();
						}
						return;
					}

					createPreviewContainer();
					previewContainer.show();
					previewContainer.find('.preview-content').html('<p style="color: #646970; font-style: italic;">Generowanie podglądu...</p>');

					previewTimeout = setTimeout(async function () {
						try {
							const processedContent = await processRichLinks(textContent);
							previewContainer.find('.preview-content').html(processedContent);
						} catch (error) {
							previewContainer.find('.preview-content').html('<p style="color: #d63638;">Błąd podczas generowania podglądu linków.</p>');
						}
					}, 1000);
				});

				// Obsługa wklejania Markdown
				editor.on('paste', function (e) {
					console.log('Paste event triggered in TinyMCE');

					// Pobierz tekst ze schowka
					const clipboardData = e.clipboardData || e.originalEvent?.clipboardData || window.clipboardData;
					if (clipboardData) {
						const pastedText = clipboardData.getData('text/plain');
						const pastedHtml = clipboardData.getData('text/html');

						console.log('Pasted text:', pastedText);
						console.log('Pasted HTML:', pastedHtml);

						// Jeśli wklejony tekst wygląda jak Markdown i nie ma znaczącego HTML
						if (pastedText && (!pastedHtml || pastedHtml.trim() === '' || pastedHtml === pastedText) && looksLikeMarkdown(pastedText)) {
							console.log('Detected Markdown content, converting...');
							e.preventDefault();

							// Konwertuj Markdown do HTML
							const htmlContent = markdownToHtml(pastedText);
							console.log('Converted HTML:', htmlContent);

							// Wstaw skonwertowany HTML
							editor.insertContent(htmlContent);

							// Pokaż powiadomienie o konwersji
							showNotification('Kod Markdown został automatycznie skonwertowany', 'success');
						}
					}
				});
			}
		});
	}

	// Inicjalizuj listeners dla WYSIWYG
	initializeWysiwygListeners();

	// === OBSŁUGA WKLEJANIA MARKDOWN NA POZIOMIE DOKUMENTU ===
	// Dodatkowy fallback dla przypadków gdy TinyMCE nie jest aktywny
	$(document).on('paste', '#wpmzf-activity-content', function (e) {
		// Sprawdź czy to jest textarea (nie TinyMCE)
		if (e.target.tagName === 'TEXTAREA') {
			console.log('Paste event on textarea');

			const clipboardData = e.originalEvent.clipboardData || window.clipboardData;
			if (clipboardData) {
				const pastedText = clipboardData.getData('text/plain');
				const pastedHtml = clipboardData.getData('text/html');

				console.log('Textarea paste - text:', pastedText);
				console.log('Textarea paste - html:', pastedHtml);

				// Jeśli wklejony tekst wygląda jak Markdown
				if (pastedText && (!pastedHtml || pastedHtml.trim() === '' || pastedHtml === pastedText) && looksLikeMarkdown(pastedText)) {
					console.log('Detected Markdown in textarea, converting...');
					e.preventDefault();

					// Konwertuj Markdown do HTML i wstaw do textarea
					const htmlContent = markdownToHtml(pastedText);
					$(this).val(htmlContent);

					// Pokaż powiadomienie o konwersji
					showNotification('Kod Markdown został automatycznie skonwertowany', 'success');
				}
			}
		}
	});

	let previewContainer = null;

	// Utwórz kontener podglądu jeśli nie istnieje
	function createPreviewContainer() {
		if (!previewContainer) {
			previewContainer = $('<div id="wpmzf-activity-preview" style="margin-top: 10px; padding: 10px; border: 1px solid #dcdcde; border-radius: 4px; background: #f9f9f9; min-height: 50px; display: none;"><div class="preview-label" style="font-size: 12px; color: #646970; margin-bottom: 8px;">Podgląd:</div><div class="preview-content"></div></div>');
			$('#wpmzf-activity-main-editor').after(previewContainer);
		}
	}

	// Obsługa wpisywania w edytorze została przeniesiona do initializeWysiwygListeners()

	// --- Funkcje dla bogatych kart z linkami ---
	/**
	 * Wykrywa linki w tekście i zamienia je na bogate karty
	 */
	async function processRichLinks(content) {
		// Sprawdź czy treść zawiera HTML
		const hasHtml = /<[a-z][\s\S]*>/i.test(content);

		// Regex do wykrywania URL-i
		const urlRegex = /(https?:\/\/[^\s<>"]+)/gi;
		const urls = content.match(urlRegex);

		if (!urls) {
			return hasHtml ? content : content.replace(/\n/g, '<br>');
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

		return hasHtml ? processedContent : processedContent.replace(/\n/g, '<br>');
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

		return `<a href="${metadata.url}" target="_blank" class="rich-link-inline" title="${window.escapeHtml(metadata.description || metadata.title)}">${faviconHtml}<span class="rich-link-inline-title">${window.escapeHtml(shortTitle)}</span></a>`;
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

	/**
	 * Prosta konwersja Markdown do HTML
	 */
	function markdownToHtml(text) {
		let html = text
			// Nagłówki
			.replace(/^### (.*$)/gim, '<h3>$1</h3>')
			.replace(/^## (.*$)/gim, '<h2>$1</h2>')
			.replace(/^# (.*$)/gim, '<h1>$1</h1>')
			// Pogrubienie i kursywa
			.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
			.replace(/\*(.*?)\*/g, '<em>$1</em>')
			// Kod inline
			.replace(/`(.*?)`/g, '<code>$1</code>')
			// Linki [text](url)
			.replace(/\[([^\]]*)\]\(([^)]*)\)/g, '<a href="$2">$1</a>')
			// Cytaty
			.replace(/^>\s+(.*$)/gim, '<blockquote>$1</blockquote>');

		// Obsługa list numerowanych
		html = html.replace(/^\d+\.\s+(.*$)/gim, '<li-num>$1</li-num>');
		html = html.replace(/(<li-num>.*<\/li-num>)/gs, function (match) {
			return '<ol>' + match.replace(/li-num/g, 'li') + '</ol>';
		});

		// Obsługa list punktowanych
		html = html.replace(/^[-*+]\s+(.*$)/gim, '<li-bullet>$1</li-bullet>');
		html = html.replace(/(<li-bullet>.*<\/li-bullet>)/gs, function (match) {
			return '<ul>' + match.replace(/li-bullet/g, 'li') + '</ul>';
		});

		// Nowe linie
		return html.replace(/\n/g, '<br>');
	}

	// Funkcja wykrywająca czy tekst wygląda jak Markdown
	function looksLikeMarkdown(text) {
		const markdownPatterns = [
			/^#{1,6}\s/m,              // Nagłówki
			/\*\*.*?\*\*/,             // Bold
			/\*[^*].*?\*/,             // Italic  
			/^\d+\.\s/m,               // Listy numerowane
			/^[-*+]\s/m,               // Listy punktowane
			/\[.*?\]\(.*?\)/,          // Linki
			/^>\s/m,                   // Cytaty
			/`.*?`/                    // Kod inline
		];

		return markdownPatterns.some(pattern => pattern.test(text));
	}



	// --- Główna funkcja do ładowania aktywności ---
	function loadActivities() {
		timelineContainer.html('<p><em>Ładowanie aktywności...</em></p>');

		console.log('person Loading activities for person ID:', personId);
		console.log('Data being sent:', {
			action: 'get_wpmzf_activities',
			person_id: personId,
			security: securityNonce
		});

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'get_wpmzf_activities',
				security: securityNonce,
				person_id: personId
			},
			success: function (response) {
				console.log('Person activities response:', response);
				if (response.success) {
					renderTimeline(response.data.activities);
				} else {
					console.error('Error loading person activities:', response.data);
					const errorMsg = response.data && response.data.message ? response.data.message : 'Nieznany błąd';
					timelineContainer.html('<p style="color:red;">Błąd ładowania: ' + errorMsg + '</p>');
				}
			},
			error: function (xhr, status, error) {
				console.error('Person activities AJAX error:', status, error, xhr);
				timelineContainer.html('<p style="color:red;">Wystąpił krytyczny błąd serwera.</p>');
			}
		});
	}

	// --- Renderowanie osi czasu ---
	async function renderTimeline(activities) {
		// Sprawdź czy activities jest tablicą
		if (!Array.isArray(activities)) {
			console.error('Activities is not an array:', activities);
			timelineContainer.html('<p style="color:red;">Błąd: nieprawidłowy format danych aktywności.</p>');
			return;
		}
		
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

			// Przetwórz zawartość z bogatymi kartami linków
			const processedContent = await processRichLinks(activity.content);

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
								<span class="timeline-header-date">${activity.date}</span>
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
						xhr.upload.addEventListener('progress', function (evt) {
							if (evt.lengthComputable) {
								const percentComplete = Math.round((evt.loaded / evt.total) * 100);
								progressBar.css('width', percentComplete + '%');
								progressText.text(percentComplete + '%');
							}
						}, false);
						return xhr;
					}
				}).then(response => {
					if (response.success) {
						// Dodaj do śledzonych uploadów
						pendingUploads.add(response.data.id);
						progressText.text('Gotowe!');
						previewItem.addClass('upload-success');
						const attId = response.data.id;
						const shouldTranscribe = previewItem.find('.transcribe-checkbox').is(':checked');
						return { id: attId, transcribe: shouldTranscribe };
					} else {
						throw new Error(response.data.message || 'Upload failed');
					}
				}).catch(error => {
					console.error('Upload error for file:', file.name, error);
					previewItem.addClass('upload-error');
					progressText.text('Błąd');
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
				// Przywróć wygląd preview
				renderAttachmentsPreview();
				return;
			}
		}

		// Pobierz treść z TinyMCE edytora
		let editorContent = '';
		try {
			if (window.tinyMCE && window.tinyMCE.get('wpmzf-activity-content')) {
				const editor = window.tinyMCE.get('wpmzf-activity-content');
				editorContent = editor.getContent();
			} else {
				// Fallback do textarea jeśli TinyMCE nie jest dostępny
				editorContent = $('#wpmzf-activity-content').val();
			}
		} catch (error) {
			console.warn('Błąd podczas pobierania zawartości edytora:', error);
			// Fallback do textarea
			editorContent = $('#wpmzf-activity-content').val();
		}

		// Sprawdź czy mamy jakąkolwiek treść
		if (!editorContent || editorContent.trim() === '') {
			showNotification('Proszę wprowadzić treść aktywności.', 'error');
			submitButton.text(originalButtonText).prop('disabled', false);
			attachFileBtn.prop('disabled', false);
			isSubmitting = false; // Reset flagi
			return;
		}

		submitButton.text('Dodawanie aktywności...');
		// Build activityData with transcription_ids
		const activityData = {
			action: 'add_wpmzf_activity',
			security: securityNonce,
			person_id: personId,
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

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: activityData,
			success: function (response) {
				if (response.success) {
					// Usuń przesłane pliki z pending uploads (zostały przypisane)
					uploadedAttachmentIds.forEach(item => {
						pendingUploads.delete(item.id);
					});

					// Reset formularza z obsługą TinyMCE
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
				showNotification('Brak zarejestrowanych aktywności. Dodaj pierwszą!', 'error');
			},
			complete: function () {
				submitButton.text(originalButtonText).prop('disabled', false);
				attachFileBtn.prop('disabled', false);
				isSubmitting = false; // Reset flagi
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
		const activityId = $(this).closest('.timeline-item').data('activity-id');
		const editorId = 'activity-edit-' + activityId;
		
		contentDiv.find('.activity-content-display').hide();
		contentDiv.find('.activity-content-edit').show();
		
		// Inicjalizuj TinyMCE dla edycji
		setTimeout(function() {
			initActivityEditTinyMCE(editorId);
		}, 200);

	});

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
				// Przetwórz zawartość z bogatymi kartami linków
				const processedContent = await processRichLinks(newContent);

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
			person_referrer: $('#person_referrer_select').val(),
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

					// Aktualizacja polecającego
					const referrerSpan = viewMode.find('span[data-field="person_referrer"]');
					if (response.data.referrer_html) {
						referrerSpan.html(response.data.referrer_html);
					} else {
						referrerSpan.text('Brak');
					}

					const addressParts = [formData.person_street, formData.person_postal_code, formData.person_city];
					const address = addressParts.filter(Boolean).join(', ');
					viewMode.find('span[data-field="person_address"]').text(address || 'Brak');

					const statusText = form.find('#person_status option:selected').text();
					viewMode.find('span[data-field="person_status"]').text(statusText || 'Brak');

					// Aktualizacja kontaktów - odświeżenie z serwera
					if (response.data.contacts_html) {
						if (response.data.contacts_html.emails) {
							viewMode.find('div[data-field="person_emails"]').html(response.data.contacts_html.emails);
						}
						if (response.data.contacts_html.phones) {
							viewMode.find('div[data-field="person_phones"]').html(response.data.contacts_html.phones);
						}
					}

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

	// === FUNKCJONALNOŚĆ ZADAŃ ===
	// Inicjalizacja zadań
	if (personId) {
		loadTasks();
	}

	// Ustaw domyślną datę i godzinę na bieżącą
	function setDefaultTaskDateTime() {
		const now = new Date();
		// Formatuj datę do formatu datetime-local (YYYY-MM-DDTHH:MM)
		const year = now.getFullYear();
		const month = String(now.getMonth() + 1).padStart(2, '0');
		const day = String(now.getDate()).padStart(2, '0');
		const hours = String(now.getHours()).padStart(2, '0');
		const minutes = String(now.getMinutes()).padStart(2, '0');

		const dateTimeString = `${year}-${month}-${day}T${hours}:${minutes}`;
		taskDueDateInput.val(dateTimeString);
	}

	// Ustaw domyślną datę przy inicjalizacji
	setDefaultTaskDateTime();
	// UWAGA: setDefaultDateTime() została usunięta - nie istnieje i powodowała błędy

	// === DODAWANIE ZADANIA ===
	taskForm.on('submit', function (e) {
		e.preventDefault();

		// Oznacz, że handler AJAX jest przypisany
		$(this).data('ajax-handler-attached', true);

		const taskTitle = taskTitleInput.val().trim();
		const taskDueDate = taskDueDateInput.val();
		const assignedUser = $('#wpmzf-task-assigned-user').val();

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
				task_title: taskTitle,
				task_due_date: taskDueDate,
				assigned_user: assignedUser
			},
			dataType: 'json'
		})
			.done(function (response) {
				if (response.success) {
					taskTitleInput.val(''); // Wyczyść pole tytułu
					$('#wpmzf-task-assigned-user').val(''); // Wyczyść pole przypisanego użytkownika
					setDefaultTaskDateTime(); // Ustaw nową bieżącą datę zamiast czyścić
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
				submitButton.prop('disabled', false).text('Dodaj zadanie');
			});
	});

	// Dodawanie zadania przez Enter
	taskTitleInput.on('keypress', function (e) {
		if (e.which === 13) { // Enter
			e.preventDefault();
			taskForm.submit();
		}
	});

	// Dodawanie zadania przez Enter także z pola daty
	taskDueDateInput.on('keypress', function (e) {
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
		const taskDateTime = formatTaskDateTime(task.due_date);
		const statusLabel = getStatusLabel(task.status);
		const priorityInfo = getPriorityInfo(task.priority, task.due_date);

		return `
			<div class="task-item ${taskClass}" data-task-id="${task.id}">
				<div class="task-content">
					<div class="task-main">
						<div class="task-title-row">
							<div class="task-title">${window.escapeHtml(task.title)}</div>
							<div class="task-actions">
								${task.status !== 'Zrobione' ?
				`<span class="dashicons dashicons-yes-alt" title="Oznacz jako zrobione" data-action="complete"></span>` :
				`<span class="dashicons dashicons-undo" title="Oznacz jako do zrobienia" data-action="reopen"></span>`
			}
								<span class="dashicons dashicons-calendar-alt" title="Edytuj termin" data-action="edit-date"></span>
								<span class="dashicons dashicons-admin-users" title="Zmień osobę odpowiedzialną" data-action="edit-assignee"></span>
								<span class="dashicons dashicons-trash" title="Usuń zadanie" data-action="delete"></span>
								<span class="dashicons dashicons-edit" title="Edytuj zadanie" data-action="edit"></span>
							</div>
						</div>
						<div class="task-meta-row">
							<div class="task-meta-left">
								<span class="task-status ${task.status.toLowerCase().replace(/\s+/g, '-')}">${statusLabel}</span>
								${priorityInfo ? `<span class="task-priority-indicator ${task.priority}">${priorityInfo}</span>` : ''}
								${taskDateTime ? `<span class="task-date ${taskClass}">${taskDateTime}</span>` : ''}
								${task.assigned_user_name ? `<span class="task-assigned-user">👤 ${window.escapeHtml(task.assigned_user_name)}</span>` : ''}
							</div>
						</div>
					</div>
				</div>
			</div>
		`;
	}

	// === POMOCNICZE FUNKCJE ===
	function getTaskClass(task) {
		if (task.status === 'Zrobione') return 'completed';

		// Używamy priorytetu z serwera, który jest już poprawnie obliczony
		return task.priority || '';
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

	function formatTaskDateTime(dateString) {
		if (!dateString) return null;

		const date = new Date(dateString);
		const today = new Date();

		// Jeśli to dzisiaj, pokaż tylko godzinę
		if (date.toDateString() === today.toDateString()) {
			return date.toLocaleTimeString('pl-PL', {
				hour: '2-digit',
				minute: '2-digit'
			});
		}

		// W przeciwnym razie pokaż datę i godzinę
		return date.toLocaleDateString('pl-PL', {
			day: '2-digit',
			month: '2-digit',
			year: 'numeric'
		}) + ' ' + date.toLocaleTimeString('pl-PL', {
			hour: '2-digit',
			minute: '2-digit'
		});
	}

	function getPriorityInfo(priority, dueDate) {
		if (!dueDate) return null;

		const priorityLabels = {
			'overdue': 'SPÓŹNIONE',
			'today': 'DZISIAJ',
			'upcoming': 'ZAPLANOWANE'
		};

		return priorityLabels[priority] || null;
	}

	function getStatusLabel(status) {
		const statusLabels = {
			'Do zrobienia': 'Do zrobienia',
			'W toku': 'W toku',
			'Zrobione': 'Zrobione'
		};
		return statusLabels[status] || status;
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
			case 'edit-date':
				editTaskDate(taskId);
				break;
			case 'edit-assignee':
				editTaskAssignee(taskId);
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
		const editInput = `<input type="text" class="task-edit-input" value="${window.escapeHtml(currentTitle)}" style="width: 100%; padding: 5px; border: 1px solid #ddd; border-radius: 3px;">`;
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

	// === EDYCJA DATY ZADANIA ===
	function editTaskDate(taskId) {
		// Znajdź zadanie w DOM
		const taskItem = $(`.task-item[data-task-id="${taskId}"]`);
		const taskDateElement = taskItem.find('.task-date');
		let currentDate = '';

		// Pobierz aktualną datę z atrybutu data lub z serwera
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'get_wpmzf_task_date',
				wpmzf_task_security: taskSecurityNonce,
				task_id: taskId
			},
			dataType: 'json'
		})
			.done(function (response) {
				if (response.success && response.data.due_date) {
					// Konwertuj datę z formatu MySQL do datetime-local
					const date = new Date(response.data.due_date);
					const year = date.getFullYear();
					const month = String(date.getMonth() + 1).padStart(2, '0');
					const day = String(date.getDate()).padStart(2, '0');
					const hours = String(date.getHours()).padStart(2, '0');
					const minutes = String(date.getMinutes()).padStart(2, '0');
					currentDate = `${year}-${month}-${day}T${hours}:${minutes}`;
				}

				showDateEditDialog(taskId, currentDate);
			})
			.fail(function () {
				showDateEditDialog(taskId, '');
			});
	}

	function showDateEditDialog(taskId, currentDate) {
		// Utwórz modal dialog
		const dialogHtml = `
			<div id="task-date-edit-modal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000; display: flex; align-items: center; justify-content: center;">
				<div style="background: white; padding: 20px; border-radius: 8px; width: 400px; max-width: 90%;">
					<h3 style="margin-top: 0;">Edytuj termin zadania</h3>
					<label for="task-date-edit-input" style="display: block; margin-bottom: 5px; font-weight: 600;">Termin wykonania:</label>
					<input type="datetime-local" id="task-date-edit-input" value="${currentDate}" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 15px;">
					<div style="display: flex; gap: 10px; justify-content: flex-end;">
						<button type="button" id="task-date-edit-cancel" class="button">Anuluj</button>
						<button type="button" id="task-date-edit-save" class="button button-primary">Zapisz</button>
					</div>
				</div>
			</div>
		`;

		$('body').append(dialogHtml);
		$('#task-date-edit-input').focus();

		// Obsługa zapisywania
		$('#task-date-edit-save').on('click', function () {
			const newDate = $('#task-date-edit-input').val();
			saveTaskDate(taskId, newDate);
			$('#task-date-edit-modal').remove();
		});

		// Obsługa anulowania
		$('#task-date-edit-cancel').on('click', function () {
			$('#task-date-edit-modal').remove();
		});

		// Obsługa klawisza Escape
		$(document).on('keydown.task-date-edit', function (e) {
			if (e.which === 27) { // Escape
				$('#task-date-edit-modal').remove();
				$(document).off('keydown.task-date-edit');
			}
		});

		// Obsługa klawisza Enter
		$('#task-date-edit-input').on('keydown', function (e) {
			if (e.which === 13) { // Enter
				e.preventDefault();
				$('#task-date-edit-save').click();
			}
		});
	}

	// === ZAPISYWANIE DATY ZADANIA ===
	function saveTaskDate(taskId, newDate) {
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'update_wpmzf_task_status',
				wpmzf_task_security: taskSecurityNonce,
				task_id: taskId,
				due_date: newDate
			},
			dataType: 'json'
		})
			.done(function (response) {
				if (response.success) {
					loadTasks(); // Odśwież listę zadań
					showTaskMessage('Termin zadania został zaktualizowany.', 'success');
				} else {
					showTaskMessage(response.data || 'Wystąpił błąd podczas aktualizacji terminu.', 'error');
				}
			})
			.fail(function () {
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

	// === OBSŁUGA PROJEKTÓW/ZLECEŃ ===

	// Obsługa linków do projektów - przekierowanie do widoku projektu
	$(document).on('click', '.project-link', function (e) {
		e.preventDefault();

		const projectId = $(this).data('project-id');
		if (projectId) {
			const projectUrl = wpmzfPersonView.adminUrl + 'admin.php?page=wpmzf_view_project&project_id=' + projectId;
			window.location.href = projectUrl;
		}
	});

	// Obsługa przycisku "Nowe zlecenie"
	$(document).on('click', '#add-new-project-btn', function (e) {
		e.preventDefault();
		openProjectModal();
	});

	// Obsługa rozwijania zakończonych projektów
	$(document).on('click', '#toggle-completed-projects', function (e) {
		e.preventDefault();
		const arrow = $(this).find('.dashicons');
		const completedList = $('#completed-projects-list');

		if (completedList.is(':visible')) {
			completedList.slideUp();
			arrow.removeClass('dashicons-arrow-down').addClass('dashicons-arrow-right');
			$(this).removeClass('expanded');
		} else {
			completedList.slideDown();
			arrow.removeClass('dashicons-arrow-right').addClass('dashicons-arrow-down');
			$(this).addClass('expanded');
		}
	});

	// Funkcja otwierania modala dodawania projektu
	function openProjectModal() {
		// Sprawdź czy modal już istnieje
		if ($('#project-modal').length === 0) {
			createProjectModal();
		}

		// Wyczyść formularz i pokaż modal
		$('#project-form')[0].reset();
		$('#project-modal').show();

		// Ustaw domyślną datę rozpoczęcia na dzisiaj
		const today = new Date().toISOString().slice(0, 10);
		$('#project-start-date').val(today);

		// Fokus na pierwszym polu
		$('#project-name').focus();
	}

	// Funkcja tworzenia modala
	function createProjectModal() {
		const modalHtml = `
			<div id="project-modal" class="luna-crm-modal" style="display: none;">
				<div class="luna-crm-modal-content">
					<div class="luna-crm-modal-header">
						<h2>Dodaj nowe zlecenie</h2>
						<button class="luna-crm-modal-close">&times;</button>
					</div>
					
					<form id="project-form">
						<div class="luna-crm-form-group">
							<label for="project-name">Nazwa zlecenia *</label>
							<input type="text" id="project-name" name="project_name" required>
						</div>
						
						<div class="luna-crm-form-group">
							<label for="project-description">Opis</label>
							<textarea id="project-description" name="project_description" rows="4"></textarea>
						</div>
						
						<div class="luna-crm-form-row">
							<div class="luna-crm-form-group">
								<label for="project-start-date">Data rozpoczęcia</label>
								<input type="date" id="project-start-date" name="start_date">
							</div>
							
							<div class="luna-crm-form-group">
								<label for="project-end-date">Termin zakończenia</label>
								<input type="date" id="project-end-date" name="end_date">
							</div>
						</div>
						
						<div class="luna-crm-form-group">
							<label for="project-budget">Budżet</label>
							<input type="text" id="project-budget" name="budget" placeholder="np. 5000 PLN">
						</div>
						
						<div class="luna-crm-form-group">
							<label for="project-company">Firma</label>
							<select id="project-company" name="company_id" style="width: 100%;">
								<option value="">Wybierz firmę (opcjonalnie)</option>
							</select>
						</div>
						
						<div class="luna-crm-form-actions">
							<button type="submit" class="button button-primary">Dodaj zlecenie</button>
							<button type="button" class="button cancel-project">Anuluj</button>
							<span class="spinner" style="float: none; margin: 0 10px;"></span>
						</div>
					</form>
				</div>
			</div>
		`;

		$('body').append(modalHtml);

		// Inicjalizuj Select2 dla firm
		$('#project-company').select2({
			width: '100%',
			placeholder: 'Wybierz firmę (opcjonalnie)',
			allowClear: true,
			ajax: {
				url: ajaxurl,
				dataType: 'json',
				delay: 250,
				data: function (params) {
					return {
						action: 'wpmzf_search_companies',
						security: securityNonce,
						term: params.term
					};
				},
				processResults: function (data) {
					if (data.success && Array.isArray(data.data)) {
						return { results: data.data };
					}
					return { results: [] };
				},
				cache: true
			},
			minimumInputLength: 2,
			language: {
				inputTooShort: function () {
					return 'Wpisz przynajmniej 2 znaki';
				},
				loadingMore: function () {
					return 'Wczytywanie wyników…';
				},
				noResults: function () {
					return 'Nie znaleziono firmy';
				},
				searching: function () {
					return 'Szukanie…';
				}
			}
		});

		// Obsługa zamykania modala
		$(document).on('click', '.luna-crm-modal-close, .cancel-project', function () {
			$('#project-modal').hide();
		});

		// Obsługa wysyłania formularza
		$(document).on('submit', '#project-form', function (e) {
			e.preventDefault();
			submitProject();
		});
	}

	// Funkcja wysyłania formularza projektu
	function submitProject() {
		const form = $('#project-form');
		const spinner = form.find('.spinner');
		const submitBtn = form.find('button[type="submit"]');

		// Pokaż spinner i zablokuj przycisk
		spinner.addClass('is-active');
		submitBtn.prop('disabled', true);

		const formData = {
			action: 'add_wpmzf_project',
			security: securityNonce,
			person_id: personId,
			project_name: $('#project-name').val(),
			project_description: $('#project-description').val(),
			start_date: $('#project-start-date').val(),
			end_date: $('#project-end-date').val(),
			budget: $('#project-budget').val(),
			company_id: $('#project-company').val()
		};

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: formData,
			success: function (response) {
				spinner.removeClass('is-active');
				submitBtn.prop('disabled', false);

				if (response.success) {
					$('#project-modal').hide();
					showNotification('Zlecenie zostało dodane pomyślnie!', 'success');

					// Odśwież listę projektów
					refreshProjectsList();
				} else {
					showNotification('Błąd: ' + (response.data.message || 'Nieznany błąd'), 'error');
				}
			},
			error: function () {
				spinner.removeClass('is-active');
				submitBtn.prop('disabled', false);
				showNotification('Wystąpił błąd serwera.', 'error');
			}
		});
	}

	// Funkcja odświeżania listy projektów
	function refreshProjectsList() {
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'get_wpmzf_projects',
				security: securityNonce,
				person_id: personId
			},
			success: function (response) {
				if (response.success) {
					updateProjectsDisplay(response.data);
				}
			}
		});
	}

	// Funkcja aktualizacji wyświetlania projektów
	function updateProjectsDisplay(data) {
		const projectsContainer = $('.dossier-box').filter(function () {
			return $(this).find('h2.dossier-title').text().trim() === 'Zlecenia';
		}).find('.dossier-content');

		let html = '';

		// Aktywne projekty
		if (data.active_projects && data.active_projects.length > 0) {
			html += '<div class="projects-section">';
			html += '<h4 style="margin: 0 0 10px 0; color: #1d2327; font-size: 13px; font-weight: 600;">Aktywne zlecenia:</h4>';
			html += '<ul class="projects-list">';

			data.active_projects.forEach(function (project) {
				html += '<li class="project-item active-project">';
				html += '<div class="project-info">';
				html += '<a href="#" class="project-link" data-project-id="' + project.id + '">' + escapeHtml(project.name) + '</a>';
				html += '<span class="project-deadline">Termin: ' + escapeHtml(project.deadline) + '</span>';
				html += '</div>';
				html += '</li>';
			});

			html += '</ul>';
			html += '</div>';
		}

		// Zakończone projekty
		if (data.completed_projects && data.completed_projects.length > 0) {
			html += '<div class="projects-section" style="margin-top: 20px;">';
			html += '<h4 style="cursor: pointer; margin: 0 0 10px 0; color: #646970; font-size: 13px; font-weight: 600;" id="toggle-completed-projects">';
			html += '<span class="dashicons dashicons-arrow-right"></span> Zakończone zlecenia (' + data.completed_projects.length + ')';
			html += '</h4>';
			html += '<ul class="projects-list" id="completed-projects-list" style="display: none;">';

			data.completed_projects.forEach(function (project) {
				html += '<li class="project-item completed-project">';
				html += '<div class="project-info">';
				html += '<a href="#" class="project-link" data-project-id="' + project.id + '">' + escapeHtml(project.name) + '</a>';
				html += '<span class="project-deadline">Termin: ' + escapeHtml(project.deadline) + '</span>';
				html += '</div>';
				html += '</li>';
			});

			html += '</ul>';
			html += '</div>';
		}

		// Jeśli brak projektów
		if ((!data.active_projects || data.active_projects.length === 0) &&
			(!data.completed_projects || data.completed_projects.length === 0)) {
			html = '<p><em>Brak zleceń dla tej osoby.</em></p>';
		}

		projectsContainer.html(html);
	}

	// === WAŻNE LINKI ===

	// Debug - sprawdź czy personId jest poprawne
	console.log('Person ID found:', personId);
	console.log('Security nonce:', securityNonce);

	if (!personId || personId === '' || personId === 'undefined') {
		console.error('Person ID not found or invalid!');
		return;
	}

	// Ładowanie linków przy inicjalizacji
	loadImportantLinks();

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
			object_id: personId,
			object_type: 'person'
		};

		if (isEdit) {
			formData.link_id = linkId;
		}

		// Blokowanie formularza
		const submitBtn = linkForm.find('button[type="submit"]');
		const originalText = linkSubmitText.text();
		submitBtn.prop('disabled', true);
		linkSubmitText.text(isEdit ? 'Aktualizuję...' : 'Dodaję...');

		const ajaxUrl = (typeof wpmzfPersonView !== 'undefined' && wpmzfPersonView.ajaxUrl) ? 
			wpmzfPersonView.ajaxUrl : ajaxurl;

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

		const ajaxUrl = (typeof wpmzfPersonView !== 'undefined' && wpmzfPersonView.ajaxUrl) ? 
			wpmzfPersonView.ajaxUrl : ajaxurl;

		$.ajax({
			url: ajaxUrl,
			type: 'POST',
			data: {
				action: 'wpmzf_delete_important_link',
				security: securityNonce,
				link_id: linkId
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
		console.log('Loading important links for person:', personId); // Debug
		
		if (!personId || personId === '' || personId === 'undefined') {
			console.error('Cannot load links - invalid person ID');
			$('#important-links-container').html('<p class="important-links-loading" style="color: #d63638;">Błąd: Nieprawidłowe ID osoby</p>');
			return;
		}

		const ajaxUrl = (typeof wpmzfPersonView !== 'undefined' && wpmzfPersonView.ajaxUrl) ? 
			wpmzfPersonView.ajaxUrl : ajaxurl;

		$.ajax({
			url: ajaxUrl,
			type: 'POST',
			data: {
				action: 'wpmzf_get_important_links',
				security: securityNonce,
				object_id: personId,
				object_type: 'person'
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

	// === EDYCJA OSOBY ODPOWIEDZIALNEJ ZA ZADANIE ===
	function editTaskAssignee(taskId) {
		// Znajdź zadanie w DOM
		const taskItem = $(`.task-item[data-task-id="${taskId}"]`);
		const assignedUserSpan = taskItem.find('.task-assigned-user');
		const currentAssignee = assignedUserSpan.text().replace('👤 ', ''); // Usuń ikonę

		// Stwórz select z użytkownikami
		const selectHtml = `
			<select class="task-assignee-select" style="width: 200px; padding: 3px; border: 1px solid #ddd; border-radius: 3px;">
				<option value="">Brak przypisania</option>
			</select>
		`;

		// Zamień span na select
		assignedUserSpan.html(selectHtml);
		const select = assignedUserSpan.find('.task-assignee-select');

		// Załaduj użytkowników przez AJAX
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'wpmzf_get_users_for_task',
				wpmzf_task_security: taskSecurityNonce
			},
			dataType: 'json'
		})
		.done(function (response) {
			if (response.success && response.data) {
				// Dodaj opcje użytkowników
				response.data.forEach(function(user) {
					const selected = user.display_name === currentAssignee ? 'selected' : '';
					select.append(`<option value="${user.ID}" ${selected}>${user.display_name}</option>`);
				});
			}
		})
		.fail(function() {
			showTaskMessage('Błąd podczas ładowania listy użytkowników.', 'error');
			// Przywróć oryginalny tekst
			assignedUserSpan.html(currentAssignee ? `👤 ${currentAssignee}` : '');
		});

		// Obsługa zmiany wartości
		select.on('change', function() {
			const newAssigneeId = $(this).val();
			const newAssigneeName = $(this).find('option:selected').text();
			saveTaskAssignee(taskId, newAssigneeId, newAssigneeName, assignedUserSpan, currentAssignee);
		});

		// Obsługa utraty fokusa
		select.on('blur', function() {
			// Jeśli użytkownik nie wybrał nic, przywróć oryginalny tekst
			setTimeout(function() {
				if (assignedUserSpan.find('.task-assignee-select').length > 0) {
					assignedUserSpan.html(currentAssignee ? `👤 ${currentAssignee}` : '');
				}
			}, 100);
		});
	}

	// === ZAPISYWANIE OSOBY ODPOWIEDZIALNEJ ===
	function saveTaskAssignee(taskId, newAssigneeId, newAssigneeName, assignedUserSpan, originalAssignee) {
		// Pokazuj stan ładowania
		assignedUserSpan.text('Zapisywanie...');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'update_wpmzf_task_assignee',
				wpmzf_task_security: taskSecurityNonce,
				task_id: taskId,
				assigned_user_id: newAssigneeId
			},
			dataType: 'json'
		})
		.done(function (response) {
			if (response.success) {
				// Aktualizuj wyświetlany tekst
				if (newAssigneeId && newAssigneeName !== 'Brak przypisania') {
					assignedUserSpan.html(`👤 ${newAssigneeName}`);
					showTaskMessage('Osoba odpowiedzialna została zaktualizowana.', 'success');
				} else {
					assignedUserSpan.html('');
					showTaskMessage('Usunięto przypisanie osoby odpowiedzialnej.', 'success');
				}
			} else {
				assignedUserSpan.html(originalAssignee ? `👤 ${originalAssignee}` : '');
				showTaskMessage(response.data || 'Wystąpił błąd podczas aktualizacji osoby odpowiedzialnej.', 'error');
			}
		})
		.fail(function () {
			assignedUserSpan.html(originalAssignee ? `👤 ${originalAssignee}` : '');
			showTaskMessage('Wystąpił błąd serwera.', 'error');
		});
	}
});