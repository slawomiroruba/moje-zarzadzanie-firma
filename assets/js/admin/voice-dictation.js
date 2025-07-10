/**
 * Voice Dictation for WordPress Admin
 *
 * @package WPMZF
 */
jQuery(document).ready(function ($) {
    console.log('=== VOICE DICTATION START ===');
    console.log('Voice dictation: skrypt ładuje się...');
    console.log('Voice dictation: jQuery wersja:', $.fn.jquery);
    console.log('Voice dictation: URL strony:', window.location.href);
    console.log('Voice dictation: User Agent:', navigator.userAgent);
    
    // Sprawdź, czy przeglądarka wspiera Web Speech API
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SpeechRecognition) {
        console.warn('Twoja przeglądarka nie wspiera Web Speech API. Funkcja dyktowania jest niedostępna.');
        console.log('Voice dictation: brak wsparcia dla Web Speech API');
        
        // Wyświetl komunikat zgodnie z raportem o kompatybilności
        const browserName = navigator.userAgent.includes('Firefox') ? 'Firefox' : 
                           navigator.userAgent.includes('Safari') && !navigator.userAgent.includes('Chrome') ? 'Safari' : 
                           'ta przeglądarka';
        
        if (typeof wp !== 'undefined' && wp.data && wp.data.dispatch) {
            wp.data.dispatch('core/notices').createNotice(
                'warning',
                `Dyktowanie głosowe może nie działać poprawnie w przeglądarce ${browserName}. Zalecamy Chrome lub Edge dla najlepszego działania.`,
                { isDismissible: true }
            );
        }
        return;
    }
    
    console.log('Voice dictation: Web Speech API jest dostępne');
    
    // Sprawdzenie czy strona działa na HTTPS lub localhost (wymagane dla Web Speech API)
    const isSecure = location.protocol === 'https:' || 
                    location.hostname === 'localhost' || 
                    location.hostname === '127.0.0.1';
    console.log('Voice dictation: Protokół:', location.protocol, 'Hostname:', location.hostname, 'Secure:', isSecure);
    
    if (!isSecure) {
        console.warn('Voice dictation: UWAGA! Web Speech API wymaga HTTPS lub localhost.');
        console.warn('Voice dictation: Aktualna strona:', location.href);
        console.warn('Voice dictation: Mikrofon może nie działać bez HTTPS!');
        
        // Wyświetl ostrzeżenie użytkownikowi
        if (typeof wp !== 'undefined' && wp.data && wp.data.dispatch) {
            wp.data.dispatch('core/notices').createNotice(
                'error',
                'Dyktowanie głosowe wymaga bezpiecznego połączenia HTTPS. Proszę skontaktować się z administratorem.',
                { isDismissible: true }
            );
        }
    }
    
    // Sprawdź dodatkowe wymagania zgodnie z raportem
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        console.warn('Dostęp do mikrofonu nie jest dostępny w tym środowisku.');
        console.log('Voice dictation: brak dostępu do mikrofonu');
        return;
    }
    
    console.log('Voice dictation: dostęp do mikrofonu jest dostępny');

    let recognition;
    let isListening = false;
    let activeElement = null;
    let microphoneButton = null;
    let lastInsertedText = ''; // Zapobiega duplikatom
    let insertTimeout = null; // Timeout dla opóźnionego wstawiania
    let isPageChanging = false; // Zapobiega wznowieniu podczas zmiany strony

    /**
     * Tworzy i inicjalizuje przycisk mikrofonu.
     */
    function createMicrophoneButton() {
        console.log('Voice dictation: createMicrophoneButton() wywołane');
        if ($('#wpmzf-voice-dictation-btn').length) {
            microphoneButton = $('#wpmzf-voice-dictation-btn');
            return;
        }
        microphoneButton = $('<button>', {
            id: 'wpmzf-voice-dictation-btn',
            type: 'button',
            'aria-label': 'Dyktuj głosowo',
            html: '<span class="dashicons dashicons-microphone"></span>',
            css: {
                position: 'absolute',
                display: 'none',
                zIndex: 999999
            }
        });
        microphoneButton.on('click', function(e) {
            e.preventDefault();
            toggleListening();
        });
        $('body').append(microphoneButton);
        console.log('Voice dictation: przycisk dodany do DOM');

        // Zapobiegaj wszystkim rodzajom konfliktów z formularzami
        microphoneButton.on('click mousedown mouseup', function (e) {
            console.log('Voice dictation: event na przycisku:', e.type);
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            
            // Tylko obsługa click powinna uruchamiać dyktowanie
            if (e.type === 'click') {
                console.log('Voice dictation: kliknięcie przycisku mikrofonu, activeElement:', activeElement ? activeElement[0].tagName : 'BRAK');
                // Przechowaj informacje o aktywnym elemencie przed przełączeniem
                const previousActiveElement = activeElement;
                
                toggleListening();
                
                // Przywróć fokus na poprzedni element po krótkiej chwili
                setTimeout(function() {
                    if (previousActiveElement) {
                        try {
                            if (previousActiveElement.is('body.mce-content-body')) {
                                // Dla edytorów TinyMCE
                                let editor = null;
                                const elementId = previousActiveElement.attr('id');
                                
                                if (elementId && typeof tinymce !== 'undefined') {
                                    editor = tinymce.get(elementId);
                                }
                                
                                if (!editor && typeof tinymce !== 'undefined' && tinymce.editors) {
                                    for (let editorId in tinymce.editors) {
                                        const currentEditor = tinymce.editors[editorId];
                                        if (currentEditor && currentEditor.getBody && currentEditor.getBody() === previousActiveElement[0]) {
                                            editor = currentEditor;
                                            break;
                                        }
                                    }
                                }
                                
                                if (editor) {
                                    editor.focus();
                                    activeElement = previousActiveElement;
                                }
                            } else {
                                // Dla standardowych pól input/textarea
                                previousActiveElement.focus();
                                activeElement = previousActiveElement;
                            }
                        } catch (e) {
                            console.log('Nie można przywrócić fokusu:', e);
                        }
                    }
                }, 50);
            }
            
            return false;
        });
        
        // Obsługa touch events z flagą passive dla lepszej responsywności
        if (microphoneButton[0]) {
            microphoneButton[0].addEventListener('touchstart', function(e) {
                // Obsługa touch start
            }, { passive: true });
            
            microphoneButton[0].addEventListener('touchend', function(e) {
                // Obsługa touch end
            }, { passive: true });
        }

        // Dodatkowe zapobieganie interfejsom formularzy
        microphoneButton.attr('tabindex', '-1');
        console.log('Voice dictation: eventy przycisku skonfigurowane');
    }

    /**
     * Inicjalizuje instancję SpeechRecognition.
     */
    function initializeRecognition() {
        try {
            recognition = new SpeechRecognition();
            
            // Konfiguracja zgodna z raportem - wysokiej jakości ustawienia dla języka polskiego
            recognition.lang = 'pl-PL'; // Język polski zgodnie z rekomendacjami
            recognition.maxAlternatives = 1; // Jedna najlepsza alternatywa
            
            // Wykryj czy to urządzenie mobilne
            const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
            
            // Optymalizacja zgodnie z raportem - różne ustawienia dla desktop vs mobile
            if (!isMobile) {
                // Desktop: pełne możliwości dla doświadczenia "jak Google Docs"
                recognition.interimResults = true;
                recognition.continuous = true;
            } else {
                // Mobile: optymalizacja dla oszczędności baterii i wydajności
                recognition.interimResults = false;
                recognition.continuous = false;
            }
            
            // Dodatkowe ustawienia zgodnie z najlepszymi praktykami z raportu
            if ('grammars' in recognition) {
                try {
                    recognition.grammars = new (window.SpeechGrammarList || window.webkitSpeechGrammarList)();
                } catch (e) {
                    console.log('Voice dictation: nie można ustawić grammar list:', e);
                }
            }

        recognition.onstart = function () {
            isListening = true;
            microphoneButton.addClass('is-listening').attr('title', 'Zakończ dyktowanie');
            microphoneButton.find('.dashicons').addClass('pulse');
            
            // Na mobile, przewiń do pola tekstowego
            if (isMobile && activeElement) {
                setTimeout(() => {
                    activeElement[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                }, 100);
            }
        };

        recognition.onend = function () {
            if (isListening && !isMobile && !isPageChanging) {
                 // Automatyczne wznowienie tylko na desktop i gdy strona się nie zmienia
                 setTimeout(() => {
                     if (isListening && !isPageChanging) {
                         try {
                             recognition.start();
                         } catch (e) {
                             console.log('Nie można wznowić rozpoznawania:', e);
                             stopListening();
                         }
                     }
                 }, 250);
            } else {
                microphoneButton.removeClass('is-listening').attr('title', 'Rozpocznij dyktowanie');
                microphoneButton.find('.dashicons').removeClass('pulse');
                isListening = false;
            }
        };

        recognition.onerror = function (event) {
            console.error('Błąd rozpoznawania mowy:', event.error);
            
            // Szczegółowa obsługa błędów zgodnie z raportem
            let errorMessage = '';
            switch (event.error) {
                case 'no-speech':
                    errorMessage = 'Nie wykryto mowy. Spróbuj ponownie.';
                    break;
                case 'audio-capture':
                    errorMessage = 'Błąd dostępu do mikrofonu. Sprawdź uprawnienia.';
                    break;
                case 'not-allowed':
                    errorMessage = 'Dostęp do mikrofonu został zablokowany. Włącz uprawnienia.';
                    break;
                case 'network':
                    errorMessage = 'Błąd sieci. Sprawdź połączenie internetowe.';
                    break;
                case 'language-not-supported':
                    errorMessage = 'Język polski nie jest obsługiwany przez tę przeglądarkę.';
                    break;
                case 'service-not-allowed':
                    errorMessage = 'Usługa rozpoznawania mowy jest niedostępna.';
                    break;
                default:
                    errorMessage = `Nieznany błąd: ${event.error}`;
            }
            
            // Wyświetl błąd użytkownikowi
            showErrorMessage(errorMessage);
            
            // Inteligentne wznowienie zgodnie z najlepszymi praktykami
            if (event.error === 'no-speech' || event.error === 'network') {
                // Spróbuj wznowić po cichu tylko na desktop
                if(isListening && !isMobile && !isPageChanging) {
                    setTimeout(() => {
                        if (isListening && !isPageChanging) {
                            try {
                                recognition.start();
                            } catch (e) {
                                console.log('Nie można wznowić rozpoznawania po błędzie:', e);
                                stopListening();
                            }
                        }
                    }, 500); // Zwiększone opóźnienie dla stabilności
                } else {
                    stopListening();
                }
            } else {
                stopListening();
            }
        };

        recognition.onresult = function (event) {
            // Wykryj czy to urządzenie mobilne (ponownie, bo to inna funkcja)
            const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
            
            // Wyczyść poprzedni timeout
            if (insertTimeout) {
                clearTimeout(insertTimeout);
            }
            
            let finalTranscript = '';
            let interimTranscript = '';
            
            // Przetwarzanie wyników zgodnie z najlepszymi praktykami z raportu
            for (let i = event.resultIndex; i < event.results.length; ++i) {
                const transcript = event.results[i][0].transcript;
                const confidence = event.results[i][0].confidence;
                
                // Filtrowanie wyników o niskiej pewności (zgodnie z raportem o jakości)
                if (event.results[i].isFinal) {
                    // Dla finalnych wyników, sprawdź pewność (jeśli dostępna)
                    if (confidence === undefined || confidence > 0.7) {
                        finalTranscript += transcript;
                    }
                } else {
                    // Wyniki tymczasowe tylko na desktop
                    if (!isMobile) {
                        interimTranscript += transcript;
                    }
                }
            }
            
            // Wstaw tekst tylko jeśli jest ostateczny i różny od poprzedniego
            if (finalTranscript && finalTranscript.trim() !== lastInsertedText.trim()) {
                lastInsertedText = finalTranscript.trim();
                
                // Optymalizacja opóźnień zgodnie z raportem (100ms jako kompromis)
                insertTimeout = setTimeout(() => {
                    // Dodaj inteligentną interpunkcję i formatowanie
                    let formattedText = formatTranscribedText(finalTranscript.trim());
                    insertText(formattedText + ' ');
                }, 100);
            }
            
            // Wyświetl wyniki tymczasowe (jeśli włączone na desktop)
            if (interimTranscript && !isMobile) {
                showInterimResults(interimTranscript);
            }
        };
    }

    /**
     * Formatuje transkrybowany tekst zgodnie z najlepszymi praktykami z raportu
     * @param {string} text Surowy tekst z API rozpoznawania mowy
     * @returns {string} Sformatowany tekst
     */
    function formatTranscribedText(text) {
        if (!text) return '';
        
        // Podstawowe formatowanie dla języka polskiego
        let formatted = text.trim();
        
        // Wielka litera na początku
        formatted = formatted.charAt(0).toUpperCase() + formatted.slice(1);
        
        // Automatyczna interpunkcja - dodaj kropkę na końcu jeśli brakuje
        if (!/[.!?]$/.test(formatted)) {
            formatted += '.';
        }
        
        return formatted;
    }

    /**
     * Wyświetla wyniki tymczasowe (dla desktop)
     * @param {string} text Tekst tymczasowy
     */
    function showInterimResults(text) {
        if (!activeElement || !text) return;
        
        // Stwórz lub zaktualizuj overlay z wynikami tymczasowymi
        let overlay = document.getElementById('wpmzf-interim-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'wpmzf-interim-overlay';
            overlay.style.cssText = `
                position: absolute;
                background: rgba(0, 123, 255, 0.1);
                border: 1px solid rgba(0, 123, 255, 0.3);
                padding: 4px 8px;
                border-radius: 4px;
                font-style: italic;
                color: #007cba;
                z-index: 999998;
                pointer-events: none;
                font-size: 14px;
                max-width: 300px;
                word-wrap: break-word;
            `;
            document.body.appendChild(overlay);
        }
        
        overlay.textContent = text;
        
        // Pozycjonuj overlay obok aktywnego elementu
        if (activeElement) {
            const rect = activeElement[0].getBoundingClientRect();
            overlay.style.left = (rect.left + window.scrollX) + 'px';
            overlay.style.top = (rect.bottom + window.scrollY + 5) + 'px';
            overlay.style.display = 'block';
        }
        
        // Ukryj overlay po 3 sekundach
        setTimeout(() => {
            if (overlay) {
                overlay.style.display = 'none';
            }
        }, 3000);
    }
    
    /**
     * Wyświetla komunikat o błędzie użytkownikowi
     * @param {string} message Treść błędu
     */
    function showErrorMessage(message) {
        // Utwórz lub zaktualizuj komunikat o błędzie
        let errorDiv = document.getElementById('wpmzf-voice-error');
        if (!errorDiv) {
            errorDiv = document.createElement('div');
            errorDiv.id = 'wpmzf-voice-error';
            errorDiv.style.cssText = `
                position: fixed;
                top: 50px;
                right: 20px;
                background: #d63638;
                color: white;
                padding: 12px 16px;
                border-radius: 4px;
                z-index: 1000000;
                font-size: 14px;
                max-width: 350px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.2);
                animation: slideInRight 0.3s ease;
            `;
            document.body.appendChild(errorDiv);
        }
        
        errorDiv.textContent = message;
        errorDiv.style.display = 'block';
        
        // Ukryj po 5 sekundach
        setTimeout(() => {
            if (errorDiv) {
                errorDiv.style.display = 'none';
            }
        }, 5000);
    }
    
    function toggleListening() {
        console.log('Voice dictation: toggleListening(), isListening:', isListening);
        if (isListening) {
            stopListening();
        } else {
            startListening();
        }
    }

    /**
     * Rozpoczyna nasłuchiwanie z kontrolą uprawnień zgodnie z RODO
     */
    function startListening() {
        console.log('Voice dictation: startListening(), activeElement:', activeElement ? activeElement[0].tagName : 'BRAK', 'isListening:', isListening);
        if (!activeElement || isListening) {
            console.log('Voice dictation: startListening() - warunki nie spełnione');
            return;
        }
        
        // Sprawdź zgodę użytkownika zgodnie z raportem RODO
        if (!hasUserConsent()) {
            console.log('Voice dictation: brak zgody użytkownika, pokazuję modal');
            requestMicrophoneConsent().then(granted => {
                if (granted) {
                    setUserConsent(true);
                    initiateRecognition();
                } else {
                    showErrorMessage('Wymagana jest zgoda na dostęp do mikrofonu dla funkcji dyktowania głosowego.');
                }
            });
        } else {
            console.log('Voice dictation: zgoda użytkownika istnieje, uruchamiam rozpoznawanie');
            initiateRecognition();
        }
    }
    
    /**
     * Faktyczne uruchomienie rozpoznawania
     */
    function initiateRecognition() {
        try {
            recognition.start();
        } catch (e) {
            console.error("Błąd podczas uruchamiania rozpoznawania:", e);
            showErrorMessage("Nie udało się uruchomić rozpoznawania mowy. Spróbuj ponownie.");
        }
    }
    
    /**
     * Sprawdza czy użytkownik wyraził zgodę na dostęp do mikrofonu
     */
    function hasUserConsent() {
        return localStorage.getItem('wpmzf_voice_consent') === 'granted';
    }
    
    /**
     * Zapisuje zgodę użytkownika
     */
    function setUserConsent(granted) {
        localStorage.setItem('wpmzf_voice_consent', granted ? 'granted' : 'denied');
        localStorage.setItem('wpmzf_voice_consent_date', new Date().toISOString());
    }
    
    /**
     * Żąda zgody na dostęp do mikrofonu zgodnie z RODO
     */
    function requestMicrophoneConsent() {
        return new Promise((resolve) => {
            // Stwórz modal zgodny z RODO
            const modal = $(`
                <div id="wpmzf-consent-modal" style="
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0,0,0,0.7);
                    z-index: 1000001;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                ">
                    <div style="
                        background: white;
                        padding: 30px;
                        border-radius: 8px;
                        max-width: 500px;
                        margin: 20px;
                        box-shadow: 0 10px 40px rgba(0,0,0,0.2);
                    ">
                        <h3 style="margin: 0 0 20px 0; color: #1d2327;">Zgoda na dostęp do mikrofonu</h3>
                        <p style="margin: 0 0 20px 0; line-height: 1.5; color: #3c434a;">
                            Funkcja dyktowania głosowego wymaga Twojej zgody na dostęp do mikrofonu. Dane głosowe nie są zapisywane ani przesyłane na serwer. Zgoda jest wymagana zgodnie z RODO.
                        </p>
                        <div style="display: flex; gap: 20px; justify-content: flex-end;">
                            <button id="wpmzf-consent-accept" class="button button-primary">Zgadzam się</button>
                            <button id="wpmzf-consent-decline" class="button">Odmów</button>
                        </div>
                    </div>
                </div>
            `);
            $('body').append(modal);
            $('#wpmzf-consent-accept').on('click', function () {
                modal.remove();
                resolve(true);
            });
            $('#wpmzf-consent-decline').on('click', function () {
                modal.remove();
                resolve(false);
            });
        });
    }
});
