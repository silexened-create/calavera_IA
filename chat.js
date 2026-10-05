/**
 * chat.js
 * Maneja la lógica de comunicación entre la interfaz web y el backend PHP de la Calavera.
 * Compatible con Hostinger shared hosting.
 */

document.addEventListener('DOMContentLoaded', () => {
    const chatMessages = document.getElementById('chat-messages');
    const userInput = document.getElementById('user-input');
    const sendBtn = document.getElementById('send-btn');
    const btnText = sendBtn ? sendBtn.querySelector('span') : null;
    const versoToggle = document.getElementById('verso-toggle');
    const firstBotMessage = document.querySelector('.bot-message');

    const micBtn = document.getElementById('mic-btn');
    let recognition = null;
    let isListening = false;
    let isCapturing = false;
    let capturedMessage = '';
    let ttsSpeaking = false;

    // Manejar el cambio del switch de Modo Poesía
    if (versoToggle && firstBotMessage) {
        versoToggle.addEventListener('change', (e) => {
            if (e.target.checked) {
                firstBotMessage.innerHTML = '¡Hola! Soy la Calavera, del Mictlán he regresado.<br>Hazme una pregunta y te responderé en verso rimado.';
            } else {
                firstBotMessage.innerHTML = 'Saludos, mortal. Soy La Catrina, guardiana de memorias.<br>Hazme una pregunta y te ilustraré con la sabiduría del Mictlán.';
            }
        });
    }

    // URL del backend PHP (ruta relativa, funciona en Hostinger)
    const SERVER_URL = 'backend/api.php';

    /**
     * Lee el texto en voz alta usando la Web Speech API del navegador.
     * @param {string} text - El texto a leer.
     */
    function speakText(text) {
        ttsSpeaking = true;
        if (isListening && recognition) {
            try { recognition.abort(); } catch (e) { }
        }

        const utter = new SpeechSynthesisUtterance(text);
        utter.lang = "es-MX";
        utter.rate = 1;

        utter.onend = () => {
            ttsSpeaking = false;
            if (isListening && recognition) {
                try { recognition.start(); } catch (e) { }
            }
        };

        utter.onerror = () => {
            ttsSpeaking = false;
            if (isListening && recognition) {
                try { recognition.start(); } catch (e) { }
            }
        };

        speechSynthesis.speak(utter);
    }

    /**
     * Añade un mensaje a la interfaz del chat.
     */
    function appendMessage(text, isBot) {
        if (!chatMessages) return;

        const msgDiv = document.createElement('div');
        msgDiv.className = `message ${isBot ? 'bot-message' : 'user-message'}`;

        // Formatear versos con saltos de línea
        const formattedText = text.replace(/\n/g, '<br>');
        msgDiv.innerHTML = formattedText;

        chatMessages.appendChild(msgDiv);

        // Scroll automático al final
        chatMessages.scrollTo({
            top: chatMessages.scrollHeight,
            behavior: 'smooth'
        });
    }

    /**
     * Procesa el envío de mensajes.
     */
    async function hablarConCalavera() {
        const pregunta = userInput.value.trim();
        if (!pregunta) return;

        // 1. Interfaz: Mostrar mensaje de usuario
        appendMessage(pregunta, false);
        userInput.value = '';

        // 2. Interfaz: Estado de carga
        const originalBtnText = btnText ? btnText.innerText : "Enviar";
        if (btnText) btnText.innerText = "...";
        userInput.disabled = true;
        sendBtn.disabled = true;

        const modoVerso = versoToggle ? versoToggle.checked : true;

        try {
            // Conectar con el backend PHP
            const response = await fetch(SERVER_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ pregunta: pregunta, modo_verso: modoVerso })
            });

            if (!response.ok) {
                const errorData = await response.json().catch(() => null);
                throw new Error(errorData?.error || 'Servidor no disponible');
            }

            const data = await response.json();

            if (data.respuesta) {
                appendMessage(data.respuesta, true);
                speakText(data.respuesta);
            }

            if (data.error) {
                appendMessage("💀 " + data.error, true);
            }

        } catch (error) {
            console.error("Error de conexión con el backend:", error);

            // Mensaje de error poético
            const errorPoetico =
                "¡Ay, viajero! Mis huesos están tiesos\n" +
                "y el servidor no responde.\n" +
                "Verifica que el backend esté encendido,\n" +
                "o mi voz del Mictlán se esconde.";

            appendMessage(errorPoetico, true);

            // Lectura del error con síntesis de voz
            speakText(errorPoetico);

        } finally {
            if (btnText) btnText.innerText = originalBtnText;
            userInput.disabled = false;
            sendBtn.disabled = false;
            userInput.focus();
        }
    }

    // Configuración de reconocimiento de voz
    if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        recognition = new SpeechRecognition();
        recognition.continuous = true;
        recognition.interimResults = false;
        recognition.lang = 'es-MX';

        recognition.onstart = () => {
            isListening = true;
            // No visual indicator here, wait for wake word
        };

        recognition.onresult = (event) => {
            if (ttsSpeaking) return;

            let finalTranscript = '';
            for (let i = event.resultIndex; i < event.results.length; ++i) {
                if (event.results[i].isFinal) {
                    finalTranscript += event.results[i][0].transcript.toLowerCase() + ' ';
                }
            }

            if (!finalTranscript) return;

            if (!isCapturing && finalTranscript.includes('oye calaca')) {
                isCapturing = true;
                capturedMessage = '';
                userInput.placeholder = "Escuchando...";
                if (micBtn) {
                    micBtn.classList.add('listening');
                    micBtn.innerHTML = '🔴';
                }

                const startIndex = finalTranscript.indexOf('oye calaca') + 'oye calaca'.length;
                let textAfterWake = finalTranscript.substring(startIndex).trim();

                if (textAfterWake.includes('dime calaca')) {
                    const endIndex = textAfterWake.indexOf('dime calaca');
                    capturedMessage = textAfterWake.substring(0, endIndex).trim();
                    isCapturing = false;
                    userInput.placeholder = "Escribe tu pregunta aquí...";
                    if (micBtn) {
                        micBtn.classList.remove('listening');
                        micBtn.innerHTML = '🎙️';
                    }

                    if (capturedMessage) {
                        userInput.value = capturedMessage;
                        hablarConCalavera();
                    }
                } else {
                    capturedMessage += textAfterWake + ' ';
                    userInput.value = capturedMessage;
                }
            } else if (isCapturing) {
                if (finalTranscript.includes('dime calaca')) {
                    const endIndex = finalTranscript.indexOf('dime calaca');
                    capturedMessage += finalTranscript.substring(0, endIndex).trim();

                    isCapturing = false;
                    userInput.placeholder = "Escribe tu pregunta aquí...";
                    if (micBtn) {
                        micBtn.classList.remove('listening');
                        micBtn.innerHTML = '🎙️';
                    }

                    if (capturedMessage.trim()) {
                        userInput.value = capturedMessage.trim();
                        hablarConCalavera();
                    }
                } else {
                    capturedMessage += finalTranscript + ' ';
                    userInput.value = capturedMessage;
                }
            }
        };

        recognition.onerror = (event) => {
            console.error('Speech recognition error', event.error);
        };

        recognition.onend = () => {
            if (isListening && !ttsSpeaking) {
                try { recognition.start(); } catch (e) { }
            } else if (!isListening) {
                isCapturing = false;
                if (micBtn) {
                    micBtn.classList.remove('listening');
                    micBtn.innerHTML = '🎙️';
                }
                userInput.placeholder = "Escribe tu pregunta aquí...";
            }
        };

        if (micBtn) {
            micBtn.addEventListener('click', () => {
                if (isListening) {
                    isListening = false;
                    try { recognition.stop(); } catch (e) { }
                    isCapturing = false;
                    micBtn.classList.remove('listening');
                    micBtn.innerHTML = '🎙️';
                    userInput.placeholder = "Escribe tu pregunta aquí...";
                } else {
                    try { recognition.start(); } catch (e) { }
                }
            });
        }
    } else {
        if (micBtn) micBtn.style.display = 'none';
    }

    // Configuración de eventos
    if (sendBtn) sendBtn.addEventListener('click', hablarConCalavera);
    if (userInput) {
        userInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') hablarConCalavera();
        });
    }
});