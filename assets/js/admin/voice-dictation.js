/**
 * Voice Dictation for WordPress Admin
 *
 * @package WPMZF
 * @version 2.0 (Corrected and improved)
 */
jQuery(document).ready(function ($) {
    console.log('=== VOICE DICTATION START ===');
    console.log('Voice dictation: skrypt ładuje się...');
    
    // --- BROWSER AND ENVIRONMENT CHECKS ---
    
    // Check for Web Speech API support
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SpeechRecognition) {
        console.warn('Twoja przeglądarka nie wspiera Web Speech API.');
        if (typeof wp !== 'undefined' && wp.data && wp.data.dispatch) {
            wp.data.dispatch('core/notices').createNotice(
                'warning',
                'Dyktowanie głosowe nie jest wspierane przez Twoją przeglądarkę. Zalecamy Chrome lub Edge.',
                { isDismissible: true }
            );
        }
        return;
    }
    
    // Check for HTTPS connection (required for microphone access)
    const isSecure = location.protocol === 'https:' || location.hostname === 'localhost' || location.hostname === '127.0.0.1';
    if (!isSecure) {
        console.warn('Web Speech API wymaga połączenia HTTPS. Mikrofon może nie działać.');
        if (typeof wp !== 'undefined' && wp.data && wp.data.dispatch) {
            wp.data.dispatch('core/notices').createNotice(
                'error',
                'Dyktowanie głosowe wymaga bezpiecznego połączenia HTTPS.',
                { isDismissible: true }
            );
        }
    }
    
    // Check for microphone access API
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        console.warn('Dostęp do mikrofonu nie jest dostępny w tym środowisku.');
        return;
    }
    
    console.log('Voice dictation: Środowisko jest gotowe.');

    // --- GLOBAL VARIABLES ---
    
    let recognition;
    let isListening = false;
    let activeElement = null; // Currently focused text field (jQuery object)
    let microphoneButton = null;
    let lastInsertedText = ''; 
    let insertTimeout = null;
    let isPageChanging = false; // Flag to prevent errors on page unload
    let restartTimeout = null; // Timeout for restarting recognition
    let silenceTimeout = null; // Timeout for handling silence
    const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);

    // --- CORE FUNCTIONS ---

    /**
     * Creates and initializes the microphone button.
     */
    function createMicrophoneButton() {
        if ($('#wpmzf-voice-dictation-btn').length) return;

        microphoneButton = $('<button>', {
            id: 'wpmzf-voice-dictation-btn',
            type: 'button',
            'aria-label': 'Dyktuj głosowo',
            html: '<span class="dashicons dashicons-microphone"></span>',
            css: {
                position: 'absolute',
                width: '32px',
                height: '32px',
                borderRadius: '50%',
                backgroundColor: '#2271b1',
                color: 'white',
                border: 'none',
                boxShadow: '0 2px 8px rgba(0,0,0,0.2)',
                zIndex: 999999,
                display: 'none',
                cursor: 'pointer',
                transition: 'all 0.3s ease'
            }
        });

        // Style the icon
        microphoneButton.find('.dashicons').css({
            fontSize: '16px',
            lineHeight: '32px'
        });

        microphoneButton.on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            toggleListening();
        });

        $('body').append(microphoneButton);
        
        // Add CSS for pulse animation
        if (!$('#wpmzf-voice-styles').length) {
            $('<style id="wpmzf-voice-styles">')
                .text(`
                    #wpmzf-voice-dictation-btn.is-listening {
                        background-color: #d63638 !important;
                        animation: pulse 2s infinite;
                    }
                    #wpmzf-voice-dictation-btn:hover {
                        background-color: #135e96;
                        transform: scale(1.05);
                    }
                    #wpmzf-voice-dictation-btn.is-listening:hover {
                        background-color: #b32d2e !important;
                    }
                    @keyframes pulse {
                        0% { transform: scale(1); box-shadow: 0 2px 8px rgba(0,0,0,0.2); }
                        50% { transform: scale(1.1); box-shadow: 0 4px 16px rgba(214,54,56,0.4); }
                        100% { transform: scale(1); box-shadow: 0 2px 8px rgba(0,0,0,0.2); }
                    }
                `)
                .appendTo('head');
        }
        
        console.log('Voice dictation: Przycisk mikrofonu został utworzony.');
    }

    /**
     * Initializes the SpeechRecognition instance with appropriate settings.
     */
    function initializeRecognition() {
        recognition = new SpeechRecognition();
        recognition.lang = 'pl-PL';
        recognition.maxAlternatives = 1;

        // Enhanced settings for better recognition
        recognition.interimResults = true;
        recognition.continuous = true;
        
        // Mobile-specific adjustments
        if (isMobile) {
            recognition.continuous = false;
        }

        recognition.onstart = function () {
            isListening = true;
            microphoneButton.addClass('is-listening').attr('title', 'Zakończ dyktowanie');
            console.log('Voice dictation: Rozpoczęto nasłuchiwanie');
        };

        recognition.onend = function () {
            console.log('Voice dictation: Nasłuchiwanie zakończone');
            
            // Clear any existing timeouts
            clearTimeout(restartTimeout);
            clearTimeout(silenceTimeout);
            
            // Only restart if we're still supposed to be listening and not changing pages
            if (isListening && !isPageChanging) {
                restartTimeout = setTimeout(() => {
                    if (isListening && !isPageChanging) {
                        try { 
                            recognition.start(); 
                            console.log('Voice dictation: Wznowiono nasłuchiwanie');
                        } catch (e) {
                            console.error('Nie można wznowić nasłuchiwania:', e);
                            stopListening();
                        }
                    }
                }, 100);
            } else {
                stopListening();
            }
        };

        recognition.onerror = function (event) {
            console.error('Błąd rozpoznawania mowy:', event.error);
            let errorMessage = 'Wystąpił błąd dyktowania.';
            if (event.error === 'no-speech') {
                errorMessage = 'Nie wykryto mowy. Kontynuuj mówienie...';
                // Don't stop listening on no-speech, just show a brief message
                showErrorMessage(errorMessage, 2000);
                return;
            }
            if (event.error === 'not-allowed') errorMessage = 'Dostęp do mikrofonu został zablokowany.';
            if (event.error === 'network') errorMessage = 'Błąd sieci. Sprawdź połączenie.';
            if (event.error === 'aborted') return; // Ignore aborted errors
            
            showErrorMessage(errorMessage);
            stopListening();
        };

        recognition.onresult = function (event) {
            clearTimeout(insertTimeout);
            clearTimeout(silenceTimeout);
            
            let finalTranscript = '';
            let interimTranscript = '';

            for (let i = event.resultIndex; i < event.results.length; ++i) {
                const transcript = event.results[i][0].transcript;
                if (event.results[i].isFinal) {
                    finalTranscript += transcript;
                } else {
                    interimTranscript += transcript;
                }
            }
            
            // Insert final transcript
            if (finalTranscript && finalTranscript.trim() !== lastInsertedText.trim()) {
                lastInsertedText = finalTranscript.trim();
                insertTimeout = setTimeout(() => {
                    let formattedText = formatTranscribedText(finalTranscript);
                    insertText(formattedText + ' ');
                }, 300);
            }
            
            // Reset silence timeout when we get any result
            if (finalTranscript || interimTranscript) {
                silenceTimeout = setTimeout(() => {
                    if (isListening) {
                        console.log('Voice dictation: Wykryto ciszę, ale kontynuuj nasłuchiwanie');
                    }
                }, 8000); // 8 seconds of silence before any action
            }
        };
        
        console.log('Voice dictation: Inicjalizacja SpeechRecognition zakończona.');
    }
    
    /**
     * Starts the listening process after checking for consent.
     */
    function startListening() {
        if (!activeElement || isListening) return;

        // Check for user consent (GDPR)
        if (localStorage.getItem('wpmzf_voice_consent') !== 'granted') {
            requestMicrophoneConsent().then(granted => {
                if (granted) {
                    localStorage.setItem('wpmzf_voice_consent', 'granted');
                    // Initialize only after getting consent
                    initializeRecognition(); 
                    initiateRecognition();
                } else {
                    showErrorMessage('Zgoda na dostęp do mikrofonu jest wymagana.');
                }
            });
        } else {
             // If consent already exists, initialize and start
            if(!recognition) initializeRecognition();
            initiateRecognition();
        }
    }
    
    /**
     * [FIXED] Stops the listening process and resets the UI.
     */
    function stopListening() {
        if (recognition && isListening) {
            recognition.stop();
        }
        isListening = false;
        
        // Clear all timeouts
        clearTimeout(restartTimeout);
        clearTimeout(silenceTimeout);
        clearTimeout(insertTimeout);
        
        if (microphoneButton) {
            microphoneButton.removeClass('is-listening').attr('title', 'Rozpocznij dyktowanie');
        }
        console.log('Voice dictation: Nasłuchiwanie zatrzymane.');
    }
    
    /**
     * Toggles the listening state.
     */
    function toggleListening() {
        if (isListening) {
            stopListening();
        } else {
            startListening();
        }
    }
    
    /**
     * Actually starts the recognition engine.
     */
    function initiateRecognition() {
        try {
            recognition.start();
        } catch (e) {
            console.error("Błąd podczas uruchamiania rozpoznawania:", e);
            showErrorMessage("Nie udało się uruchomić dyktowania.");
            stopListening();
        }
    }

    /**
     * [FIXED] Inserts transcribed text into the active element.
     * Handles standard inputs, textareas, TinyMCE, and Gutenberg.
     * @param {string} text The text to insert.
     */
    function insertText(text) {
        if (!activeElement || !text) return;
        
        const el = activeElement[0];
        console.log('Voice dictation: Próba wstawienia tekstu do:', el.tagName, el.className, el.id);
        
        // Gutenberg Block Editor
        if (activeElement.is('[data-block]') && typeof wp !== 'undefined' && wp.data && wp.data.dispatch('core/block-editor')) {
            try {
                wp.data.dispatch('core/block-editor').insertContent(text);
                console.log('Voice dictation: Wstawiono tekst do Gutenberga.');
                return;
            } catch (e) {
                console.error('Błąd Gutenberg:', e);
            }
        }

        // TinyMCE (Classic Editor) - Enhanced detection
        let editor = null;
        
        // Method 1: Direct TinyMCE editor reference
        if (typeof tinymce !== 'undefined') {
            // Check if element is TinyMCE editor
            if (el.id && tinymce.get(el.id)) {
                editor = tinymce.get(el.id);
            }
            // Check for active editor
            else if (tinymce.activeEditor && tinymce.activeEditor.getBody) {
                editor = tinymce.activeEditor;
            }
            // Find editor by searching all instances
            else {
                const editors = tinymce.editors || [];
                for (let i = 0; i < editors.length; i++) {
                    if (editors[i].getBody && editors[i].getBody() === el) {
                        editor = editors[i];
                        break;
                    }
                }
            }
        }
        
        if (editor && editor.insertContent) {
            try {
                editor.insertContent(text);
                console.log('Voice dictation: Wstawiono tekst do TinyMCE editor.');
                return;
            } catch (e) {
                console.error('Błąd TinyMCE:', e);
            }
        }

        // WordPress Classic Editor - iframe content
        if (activeElement.is('iframe') || el.tagName === 'IFRAME') {
            try {
                const iframeDoc = el.contentDocument || el.contentWindow.document;
                const body = iframeDoc.body;
                if (body && body.isContentEditable) {
                    const selection = iframeDoc.getSelection();
                    if (selection.rangeCount > 0) {
                        const range = selection.getRangeAt(0);
                        const textNode = iframeDoc.createTextNode(text);
                        range.insertNode(textNode);
                        range.setStartAfter(textNode);
                        range.collapse(true);
                        selection.removeAllRanges();
                        selection.addRange(range);
                        console.log('Voice dictation: Wstawiono tekst do iframe editora.');
                        return;
                    } else {
                        // If no selection, append to end
                        body.appendChild(iframeDoc.createTextNode(text));
                        console.log('Voice dictation: Dodano tekst na koniec iframe editora.');
                        return;
                    }
                }
            } catch (e) {
                console.error('Błąd podczas wstawiania tekstu do iframe:', e);
            }
        }

        // ContentEditable elements
        if (el.contentEditable === 'true' || el.isContentEditable) {
            try {
                const selection = window.getSelection();
                if (selection.rangeCount > 0) {
                    const range = selection.getRangeAt(0);
                    const textNode = document.createTextNode(text);
                    range.insertNode(textNode);
                    range.setStartAfter(textNode);
                    range.collapse(true);
                    selection.removeAllRanges();
                    selection.addRange(range);
                    console.log('Voice dictation: Wstawiono tekst do contentEditable.');
                    return;
                } else {
                    // If no selection, append to end
                    el.appendChild(document.createTextNode(text));
                    console.log('Voice dictation: Dodano tekst na koniec contentEditable.');
                    return;
                }
            } catch (e) {
                console.error('Błąd podczas wstawiania tekstu do contentEditable:', e);
            }
        }
        
        // Standard <input> and <textarea>
        if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') {
            try {
                const start = el.selectionStart || 0;
                const end = el.selectionEnd || 0;
                const currentValue = el.value || '';
                el.value = currentValue.substring(0, start) + text + currentValue.substring(end);
                el.selectionStart = el.selectionEnd = start + text.length;
                
                // Trigger events for frameworks
                $(el).trigger('input').trigger('change').trigger('keyup');
                console.log('Voice dictation: Wstawiono tekst do standardowego pola.');
                return;
            } catch (e) {
                console.error('Błąd podczas wstawiania tekstu do input/textarea:', e);
            }
        }
        
        console.warn('Voice dictation: Nie udało się wstawić tekstu - nierozpoznany typ elementu:', el);
    }
    
    // --- UTILITY AND UI FUNCTIONS ---
    
    /**
     * Positions the microphone button next to the active element.
     */
    function positionMicrophoneButton() {
        if (!activeElement || !microphoneButton) return;
        
        try {
            let targetElement = activeElement;
            let offset, width, height;
            
            // Special handling for different element types
            const el = activeElement[0];
            
            // Handle TinyMCE WYSIWYG editors
            if (typeof tinymce !== 'undefined') {
                let editor = null;
                
                // Find TinyMCE editor
                if (el.id && tinymce.get(el.id)) {
                    editor = tinymce.get(el.id);
                } else if (tinymce.activeEditor && tinymce.activeEditor.getBody) {
                    editor = tinymce.activeEditor;
                }
                
                if (editor && editor.getContainer) {
                    // Position relative to TinyMCE container/toolbar
                    const container = $(editor.getContainer());
                    const toolbar = container.find('.mce-toolbar, .wp-editor-tools');
                    
                    if (toolbar.length) {
                        targetElement = toolbar;
                    } else {
                        targetElement = container;
                    }
                }
            }
            
            // Handle WordPress Classic Editor wrapper
            if (activeElement.closest('.wp-editor-wrap').length) {
                const editorWrap = activeElement.closest('.wp-editor-wrap');
                const toolbar = editorWrap.find('.wp-editor-tools, .quicktags-toolbar');
                
                if (toolbar.length) {
                    targetElement = toolbar;
                } else {
                    targetElement = editorWrap;
                }
            }
            
            // Handle iframe editors
            if (el.tagName === 'IFRAME') {
                // Look for parent editor container
                const editorContainer = activeElement.closest('.wp-editor-wrap, .mce-tinymce, .wp-core-ui');
                if (editorContainer.length) {
                    targetElement = editorContainer;
                }
            }
            
            // Get positioning data
            offset = targetElement.offset();
            width = targetElement.outerWidth();
            height = targetElement.outerHeight();
            
            if (!offset || !width || !height) {
                console.warn('Voice dictation: Nie można uzyskać wymiarów elementu');
                return;
            }
            
            // Calculate optimal position based on element type
            let top, left;
            
            if (el.tagName === 'TEXTAREA') {
                // For textarea - position in top-right corner
                top = offset.top + 8;
                left = offset.left + width - 40;
            } else if (el.tagName === 'INPUT') {
                // For input fields - center vertically on the right
                top = offset.top + (height / 2) - 16;
                left = offset.left + width - 40;
            } else {
                // For WYSIWYG and other complex editors
                // Position in top-right corner of the container
                top = offset.top + 8;
                left = offset.left + width - 40;
            }
            
            // Ensure button doesn't go outside viewport
            const viewportWidth = $(window).width();
            const viewportHeight = $(window).height();
            const scrollTop = $(window).scrollTop();
            
            // Adjust horizontal position if needed
            if (left + 32 > viewportWidth) {
                left = viewportWidth - 50;
            }
            if (left < 10) {
                left = 10;
            }
            
            // Adjust vertical position if needed
            if (top - scrollTop < 10) {
                top = scrollTop + 10;
            }
            if (top + 32 - scrollTop > viewportHeight) {
                top = scrollTop + viewportHeight - 50;
            }
            
            microphoneButton.css({
                top: top,
                left: left,
                display: 'block'
            });
            
            console.log('Voice dictation: Przycisk ustawiony na pozycji:', top, left, 'dla elementu:', el.tagName, el.className);
            
        } catch (e) {
            console.error('Błąd pozycjonowania przycisku:', e);
            // Fallback positioning
            if (activeElement.offset()) {
                microphoneButton.css({
                    top: activeElement.offset().top + 5,
                    left: activeElement.offset().left + activeElement.outerWidth() - 40,
                    display: 'block'
                });
            }
        }
    }
    
    /**
     * Applies basic formatting to the transcribed text.
     * @param {string} text Raw text from speech API.
     * @returns {string} Formatted text.
     */
    function formatTranscribedText(text) {
        if (!text) return '';
        let formatted = text.trim();
        // Capitalize the first letter
        return formatted.charAt(0).toUpperCase() + formatted.slice(1);
    }
    
    /**
     * Displays a non-intrusive error message to the user.
     * @param {string} message The error message to display.
     * @param {number} duration Duration in milliseconds (default 5000).
     */
    function showErrorMessage(message, duration = 5000) {
        let errorDiv = $('#wpmzf-voice-error');
        if (!errorDiv.length) {
            errorDiv = $('<div id="wpmzf-voice-error"></div>').css({
                position: 'fixed', top: '50px', right: '20px', background: '#d63638',
                color: 'white', padding: '12px 16px', borderRadius: '4px',
                zIndex: 1000000, maxWidth: '350px'
            }).appendTo('body');
        }
        errorDiv.text(message).fadeIn();
        setTimeout(() => errorDiv.fadeOut(), duration);
    }
    
    /**
     * Requests user consent via a GDPR-friendly modal.
     * @returns {Promise<boolean>} A promise that resolves with true (granted) or false (denied).
     */
    function requestMicrophoneConsent() {
        return new Promise((resolve) => {
            const modalHTML = `
                <div id="wpmzf-consent-modal" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.7);z-index:1000001;display:flex;align-items:center;justify-content:center;">
                    <div style="background:white;padding:30px;border-radius:8px;max-width:500px;margin:20px;">
                        <h3 style="margin:0 0 20px 0;">Zgoda na dostęp do mikrofonu</h3>
                        <p style="margin:0 0 20px 0;line-height:1.5;">Funkcja dyktowania głosowego wymaga zgody na dostęp do mikrofonu. Dane głosowe nie są nigdzie zapisywane. Zgoda jest jednorazowa.</p>
                        <div style="display:flex;gap:10px;justify-content:flex-end;">
                            <button id="wpmzf-consent-accept" class="button button-primary">Zgadzam się</button>
                            <button id="wpmzf-consent-decline" class="button">Odmów</button>
                        </div>
                    </div>
                </div>`;
            const modal = $(modalHTML).appendTo('body');
            modal.find('#wpmzf-consent-accept').on('click', () => { modal.remove(); resolve(true); });
            modal.find('#wpmzf-consent-decline').on('click', () => { modal.remove(); resolve(false); });
        });
    }

    // --- EVENT LISTENERS ---

    const editableSelectors = 'textarea, input[type="text"], input[type="search"], input[type="email"], input[type="url"], input[type="password"], .wp-editor-area, [contenteditable="true"], iframe[id*="content"], #content_ifr, .mce-content-body';

    // Show microphone button when focusing editable elements
    $(document).on('focusin', editableSelectors, function() {
        activeElement = $(this);
        setTimeout(() => {
            positionMicrophoneButton();
        }, 100); // Small delay to ensure element is fully rendered
        console.log('Voice dictation: Element focused:', this.tagName, this.className, this.id);
    });

    // Also handle click events for better detection
    $(document).on('click', editableSelectors, function() {
        if (!activeElement || activeElement[0] !== this) {
            activeElement = $(this);
            setTimeout(() => {
                positionMicrophoneButton();
            }, 100);
            console.log('Voice dictation: Element clicked:', this.tagName, this.className, this.id);
        }
    });

    // Hide microphone button on blur, unless focus moves to the button itself
    $(document).on('focusout', editableSelectors, function(e) {
        setTimeout(() => {
            const focusedElement = document.activeElement;
            if (focusedElement !== microphoneButton[0] && !isListening) {
                // Check if new focus is also an editable element
                if (!$(focusedElement).is(editableSelectors)) {
                    microphoneButton.hide();
                    activeElement = null;
                    console.log('Voice dictation: Przycisk ukryty - brak focus na editable element');
                }
            }
        }, 300); // Increased delay for better stability
    });

    // Handle scroll to reposition button with throttling
    let scrollTimeout;
    $(window).on('scroll resize', function() {
        if (activeElement && microphoneButton.is(':visible')) {
            clearTimeout(scrollTimeout);
            scrollTimeout = setTimeout(() => {
                positionMicrophoneButton();
            }, 50); // Throttle repositioning
        }
    });

    // Mobile-specific handlers
    if (isMobile) {
        // Listen for viewport changes that indicate virtual keyboard
        let initialViewportHeight = window.innerHeight;
        
        $(window).on('resize orientationchange', function() {
            setTimeout(() => {
                const currentHeight = window.innerHeight;
                const heightDifference = initialViewportHeight - currentHeight;
                
                // If height decreased significantly, virtual keyboard is probably open
                if (heightDifference > 150) {
                    if (activeElement) {
                        positionMicrophoneButton();
                        console.log('Voice dictation: Wykryto klawiaturę wirtualną');
                    }
                } else if (heightDifference < 50) {
                    // Keyboard closed
                    if (!isListening && !$(document.activeElement).is(editableSelectors)) {
                        microphoneButton.hide();
                        activeElement = null;
                    } else if (activeElement) {
                        positionMicrophoneButton();
                    }
                    initialViewportHeight = currentHeight;
                }
            }, 200); // Increased delay for mobile
        });

        // Additional mobile focus detection
        $(document).on('touchstart', editableSelectors, function() {
            setTimeout(() => {
                activeElement = $(this);
                positionMicrophoneButton();
            }, 400); // Longer delay for mobile
        });
    }

    // Enhanced TinyMCE editor handling
    if (typeof tinymce !== 'undefined') {
        // Hook into existing TinyMCE editors
        $(document).ready(function() {
            setTimeout(() => {
                if (tinymce.editors) {
                    tinymce.editors.forEach(editor => {
                        if (editor.on) {
                            editor.on('focus', function() {
                                activeElement = $(editor.getElement());
                                setTimeout(() => {
                                    positionMicrophoneButton();
                                }, 150);
                                console.log('Voice dictation: TinyMCE editor focused:', editor.id);
                            });
                            
                            editor.on('blur', function() {
                                setTimeout(() => {
                                    if (!isListening && document.activeElement !== microphoneButton[0]) {
                                        microphoneButton.hide();
                                        activeElement = null;
                                    }
                                }, 300);
                            });

                            // Handle editor content area clicks
                            editor.on('click', function() {
                                if (activeElement && activeElement[0] === editor.getElement()) {
                                    setTimeout(() => {
                                        positionMicrophoneButton();
                                    }, 100);
                                }
                            });
                        }
                    });
                }
            }, 1000);
        });
    }

    // Global TinyMCE event listener for new editors
    $(document).on('tinymce-editor-init', function(event, editor) {
        console.log('Voice dictation: Nowy TinyMCE editor zainicjalizowany:', editor.id);
        
        editor.on('focus', function() {
            activeElement = $(editor.getElement());
            setTimeout(() => {
                positionMicrophoneButton();
            }, 150);
            console.log('Voice dictation: TinyMCE editor focused via event:', editor.id);
        });
        
        editor.on('blur', function() {
            setTimeout(() => {
                if (!isListening && document.activeElement !== microphoneButton[0]) {
                    microphoneButton.hide();
                    activeElement = null;
                }
            }, 300);
        });

        editor.on('click', function() {
            if (activeElement && activeElement[0] === editor.getElement()) {
                setTimeout(() => {
                    positionMicrophoneButton();
                }, 100);
            }
        });
    });

    // Handle iframe editors (like WordPress visual editor)
    $(document).on('load', 'iframe[id*="content"]', function() {
        const iframe = this;
        console.log('Voice dictation: Iframe loaded:', iframe.id);
        
        try {
            const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
            $(iframeDoc).on('focus click', 'body', function() {
                activeElement = $(iframe);
                setTimeout(() => {
                    positionMicrophoneButton();
                }, 150);
                console.log('Voice dictation: Iframe editor focused');
            });
            
            $(iframeDoc).on('blur', 'body', function() {
                setTimeout(() => {
                    if (!isListening && document.activeElement !== microphoneButton[0]) {
                        microphoneButton.hide();
                        activeElement = null;
                    }
                }, 300);
            });
        } catch (e) {
            console.warn('Nie można uzyskać dostępu do iframe editora:', e);
        }
    });

    // Handle dynamic content changes (for editors that load asynchronously)
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.type === 'childList') {
                // Check if new editor elements were added
                $(mutation.addedNodes).find(editableSelectors).each(function() {
                    const $this = $(this);
                    if ($this.is(':focus') && (!activeElement || activeElement[0] !== this)) {
                        activeElement = $this;
                        setTimeout(() => {
                            positionMicrophoneButton();
                        }, 200);
                    }
                });
            }
        });
    });

    // Start observing
    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
    
    // Set flag on page unload to prevent restart errors
    $(window).on('beforeunload', function() {
        isPageChanging = true;
        stopListening();
        observer.disconnect();
    });

    // --- INITIALIZATION ---
    
    createMicrophoneButton();
});