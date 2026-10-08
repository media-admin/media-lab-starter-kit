/**
 * media-lab-ai-agent — Frontend Chat-Widget
 * Vanilla JS, kein Build-Step. Wird über wp_enqueue_script eingebunden.
 *
 * Erwartet ein Container-Element (wird von mlt_ai_render_widget() erzeugt):
 *
 * <div data-mlt-ai-widget
 *      data-lang="de"
 *      data-endpoint="https://.../wp-json/medialab/v1/ai-chat"
 *      data-nonce="..."
 *      data-default-state="collapsed|expanded"
 *      data-dismissible="1|0"></div>
 *
 * Zustand pro Browser-Sitzung (ausschließlich sessionStorage, nichts davon
 * wird an den Server gesendet):
 * - mlt_ai_panel_closed: Besucher hat das Chat-Fenster aktiv geschlossen →
 *   bleibt auch bei "expanded" für den Rest der Sitzung zu (auch auf
 *   Folgeseiten), bis er es selbst wieder öffnet.
 * - mlt_ai_dismissed: Besucher hat das Widget komplett ausgeblendet.
 */
(function () {
	'use strict';

	const STORAGE_KEY = 'mlt_ai_session_id';
	const PANEL_CLOSED_KEY = 'mlt_ai_panel_closed';
	const DISMISSED_KEY = 'mlt_ai_dismissed';
	const MAX_MESSAGE_LENGTH = 1000;
	// Auf kleinen Bildschirmen würde ein automatisch geöffnetes Chat-Fenster
	// große Teile der Seite verdecken — dort bleibt das Widget immer eingeklappt.
	const SMALL_VIEWPORT_QUERY = '(max-width: 640px)';

	/**
	 * sessionStorage kann in manchen Browser-Modi (z.B. strenge
	 * Datenschutzeinstellungen) eine Exception werfen — das Widget soll dann
	 * einfach ohne Zustandsspeicherung weiterlaufen, statt abzubrechen.
	 */
	function safeStorageGet(key) {
		try {
			return window.sessionStorage.getItem(key);
		} catch (e) {
			return null;
		}
	}

	function safeStorageSet(key, value) {
		try {
			window.sessionStorage.setItem(key, value);
		} catch (e) {
			// bewusst ignoriert, siehe oben
		}
	}

	function safeStorageRemove(key) {
		try {
			window.sessionStorage.removeItem(key);
		} catch (e) {
			// bewusst ignoriert, siehe oben
		}
	}

	function isSmallViewport() {
		return !!(window.matchMedia && window.matchMedia(SMALL_VIEWPORT_QUERY).matches);
	}

	function initWidget(container) {
		const lang = container.dataset.lang || 'de';
		const endpoint = container.dataset.endpoint;
		const nonce = container.dataset.nonce || '';

		if (!endpoint) {
			console.error('[mlt-ai-widget] Kein data-endpoint gesetzt, Widget wird nicht initialisiert.');
			return;
		}

		const settings = {
			defaultState: container.dataset.defaultState === 'expanded' ? 'expanded' : 'collapsed',
			dismissible: container.dataset.dismissible === '1',
		};

		// Besucher hat das Widget in dieser Sitzung bereits komplett ausgeblendet.
		if (settings.dismissible && safeStorageGet(DISMISSED_KEY) === '1') {
			container.style.display = 'none';
			return;
		}

		const i18n = getStrings(lang);
		const openInitially = resolveInitialOpenState(settings.defaultState);

		registerEscapeHandler(container);

		if (!hasConsent(container)) {
			renderConsentGate(container, i18n, settings, openInitially, function () {
				grantConsent(container);
				// Wer im Consent-Fenster auf "Zustimmen" klickt, will chatten →
				// Fenster offen lassen und direkt ins Eingabefeld springen.
				startChat(container, i18n, settings, endpoint, nonce, true);
				const chatInput = container.querySelector('.mlt-ai-input');
				if (chatInput) {
					chatInput.focus();
				}
			});
			return;
		}

		startChat(container, i18n, settings, endpoint, nonce, openInitially);
	}

	/**
	 * Soll das Chat-Fenster beim Seitenaufruf offen sein?
	 * - Hat der Besucher es in dieser Sitzung aktiv geschlossen: nein.
	 * - Sonst nur bei Startzustand "expanded" und nicht auf kleinen Bildschirmen.
	 */
	function resolveInitialOpenState(defaultState) {
		if (safeStorageGet(PANEL_CLOSED_KEY) === '1') {
			return false;
		}
		if (defaultState !== 'expanded') {
			return false;
		}
		return !isSmallViewport();
	}

	/**
	 * Öffnet/schließt das Chat-Fenster. $byUser = true bei Klick/Tastatur des
	 * Besuchers (dann wird die Entscheidung für die Sitzung gemerkt und der
	 * Fokus sinnvoll gesetzt), false beim initialen Aufbau der Seite.
	 */
	function setPanelOpen(container, open, byUser) {
		const panel = container.querySelector('.mlt-ai-panel');
		const toggleBtn = container.querySelector('.mlt-ai-toggle');
		if (!panel || !toggleBtn) {
			return;
		}

		panel.classList.toggle('mlt-ai-open', open);
		toggleBtn.setAttribute('aria-expanded', open ? 'true' : 'false');

		if (!byUser) {
			return;
		}

		if (open) {
			safeStorageRemove(PANEL_CLOSED_KEY);
			// Auf Smartphones nicht automatisch fokussieren: die Bildschirmtastatur
			// würde sofort aufspringen und die Begrüßung verdecken.
			if (!isSmallViewport()) {
				const focusTarget = container.querySelector('.mlt-ai-input') || container.querySelector('.mlt-ai-consent-accept');
				if (focusTarget) {
					focusTarget.focus();
				}
			}
		} else {
			safeStorageSet(PANEL_CLOSED_KEY, '1');
			toggleBtn.focus();
		}
	}

	/**
	 * Esc schließt das Chat-Fenster — aber nur, wenn der Fokus im Widget
	 * liegt, damit Esc nicht versehentlich das Widget schließt, während der
	 * Besucher in einem anderen Dialog der Seite arbeitet.
	 */
	function registerEscapeHandler(container) {
		document.addEventListener('keydown', function (event) {
			if (event.key !== 'Escape' || !container.contains(document.activeElement)) {
				return;
			}
			const panel = container.querySelector('.mlt-ai-panel');
			if (panel && panel.classList.contains('mlt-ai-open')) {
				setPanelOpen(container, false, true);
			}
		});
	}

	/**
	 * Bindet die Bedienelemente, die in Consent-Ansicht und Chat-Ansicht
	 * identisch sind: Toggle-Button, Schließen-Button im Fensterkopf und das
	 * kleine × zum kompletten Ausblenden des Widgets.
	 */
	function bindShell(container, onOpen) {
		const panel = container.querySelector('.mlt-ai-panel');
		const toggleBtn = container.querySelector('.mlt-ai-toggle');
		const closeBtn = container.querySelector('.mlt-ai-close');
		const dismissBtn = container.querySelector('.mlt-ai-dismiss');

		toggleBtn.addEventListener('click', function () {
			const willOpen = !panel.classList.contains('mlt-ai-open');
			setPanelOpen(container, willOpen, true);
			if (willOpen && onOpen) {
				onOpen();
			}
		});

		closeBtn.addEventListener('click', function () {
			setPanelOpen(container, false, true);
		});

		if (dismissBtn) {
			dismissBtn.addEventListener('click', function () {
				safeStorageSet(DISMISSED_KEY, '1');
				container.style.display = 'none';
			});
		}
	}

	/**
	 * Prüft, ob der Besucher der Datenübertragung an den AI-Anbieter bereits
	 * zugestimmt hat. Liest ein Cookie, dessen Name/Wert über data-Attribute
	 * konfigurierbar ist — damit lässt sich das Widget an das jeweils auf der
	 * Client-Site eingesetzte Consent-Tool ankoppeln (eigenes cookie-notice.js
	 * oder ein Drittanbieter-CMP), ohne dessen internes Format zu kennen.
	 * Default-Cookie-Name passt zum Starter-Kit-eigenen Consent-System.
	 */
	function hasConsent(container) {
		const cookieName = container.dataset.consentCookie || 'mlt_consent_ai_agent';
		const requiredValue = container.dataset.consentValue || 'granted';
		const match = document.cookie.match(new RegExp('(?:^|; )' + cookieName + '=([^;]*)'));
		return match ? decodeURIComponent(match[1]) === requiredValue : false;
	}

	function grantConsent(container) {
		const cookieName = container.dataset.consentCookie || 'mlt_consent_ai_agent';
		const value = container.dataset.consentValue || 'granted';
		const days = 180;
		const expires = new Date(Date.now() + days * 864e5).toUTCString();
		document.cookie = cookieName + '=' + encodeURIComponent(value) + '; expires=' + expires + '; path=/; SameSite=Lax';

		// Falls das Starter-Kit-eigene Consent-System (cookie-notice.js) eine
		// zentrale Logging-Funktion bereitstellt, zusätzlich darüber loggen,
		// damit die Consent-Rate-Auswertung den AI-Agent mit abdeckt.
		if (typeof window.mltLogConsent === 'function') {
			window.mltLogConsent('ai_agent', true);
		}
	}

	function dismissMarkup(i18n, settings) {
		if (!settings.dismissible) {
			return '';
		}
		return '<button type="button" class="mlt-ai-dismiss" aria-label="' + i18n.dismissLabel + '" title="' + i18n.dismissLabel + '">&times;</button>';
	}

	function renderConsentGate(container, i18n, settings, open, onAccept) {
		container.innerHTML =
			'<button type="button" class="mlt-ai-toggle" aria-expanded="false" aria-label="' + i18n.openLabel + '">' + i18n.buttonLabel + '</button>' +
			dismissMarkup(i18n, settings) +
			'<div class="mlt-ai-panel mlt-ai-consent-panel" role="dialog" aria-label="' + i18n.title + '">' +
			'  <div class="mlt-ai-header">' +
			'    <span>' + i18n.title + '</span>' +
			'    <button type="button" class="mlt-ai-close" aria-label="' + i18n.closeLabel + '">&times;</button>' +
			'  </div>' +
			'  <div class="mlt-ai-consent-body">' +
			'    <p>' + i18n.consentText + '</p>' +
			'    <button type="button" class="mlt-ai-consent-accept">' + i18n.consentAccept + '</button>' +
			'  </div>' +
			'</div>';

		bindShell(container, null);
		setPanelOpen(container, open, false);

		container.querySelector('.mlt-ai-consent-accept').addEventListener('click', onAccept);
	}

	function startChat(container, i18n, settings, endpoint, nonce, openInitially) {
		const lang = container.dataset.lang || 'de';
		const sessionId = getOrCreateSessionId();
		let messageCount = 0;
		let isLoading = false;

		container.innerHTML = renderShell(i18n, settings);

		const form = container.querySelector('.mlt-ai-form');
		const input = container.querySelector('.mlt-ai-input');
		const messagesEl = container.querySelector('.mlt-ai-messages');

		function ensureGreeting() {
			if (messagesEl.children.length === 0) {
				appendMessage(messagesEl, 'assistant', i18n.greeting);
			}
		}

		bindShell(container, ensureGreeting);
		setPanelOpen(container, openInitially, false);
		if (openInitially) {
			ensureGreeting();
		}

		form.addEventListener('submit', function (event) {
			event.preventDefault();
			handleSubmit();
		});

		function handleSubmit() {
			const text = input.value.trim();
			if (!text || isLoading) {
				return;
			}
			if (text.length > MAX_MESSAGE_LENGTH) {
				appendMessage(messagesEl, 'assistant', i18n.tooLong);
				return;
			}
			if (messageCount >= i18n.maxMessages) {
				appendMessage(messagesEl, 'assistant', i18n.sessionLimit);
				return;
			}

			appendMessage(messagesEl, 'user', text);
			input.value = '';
			messageCount += 1;
			setLoading(true);

			sendMessage(text)
				.then(function (reply) {
					appendMessage(messagesEl, 'assistant', reply);
				})
				.catch(function (error) {
					appendMessage(messagesEl, 'assistant', errorToMessage(error, i18n));
				})
				.finally(function () {
					setLoading(false);
				});
		}

		function sendMessage(text) {
			return fetch(endpoint, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': nonce,
				},
				body: JSON.stringify({
					message: text,
					session_id: sessionId,
					lang: lang,
				}),
			}).then(function (response) {
				if (!response.ok) {
					return response.json().then(function (data) {
						throw { status: response.status, code: data && data.code };
					});
				}
				return response.json();
			}).then(function (data) {
				return data.reply;
			});
		}

		function setLoading(state) {
			isLoading = state;
			form.querySelector('button[type="submit"]').disabled = state;
			input.disabled = state;
			if (state) {
				appendMessage(messagesEl, 'assistant', i18n.typing, true);
			} else {
				const typingEl = messagesEl.querySelector('.mlt-ai-typing');
				if (typingEl) {
					typingEl.remove();
				}
			}
		}
	}

	function errorToMessage(error, i18n) {
		if (error && error.code === 'budget') {
			return i18n.budgetExceeded;
		}
		return i18n.genericError;
	}

	function appendMessage(container, role, text, isTyping) {
		const bubble = document.createElement('div');
		bubble.className = 'mlt-ai-bubble mlt-ai-bubble--' + role + (isTyping ? ' mlt-ai-typing' : '');
		bubble.innerHTML = linkify(escapeHtml(text));
		container.appendChild(bubble);
		container.scrollTop = container.scrollHeight;
	}

	/**
	 * Escaped alle HTML-Sonderzeichen, bevor Text ins DOM eingesetzt wird —
	 * wichtig, weil wir ab jetzt innerHTML statt textContent nutzen (für
	 * klickbare Links), Modell-Antworten aber trotzdem nie als aktives HTML
	 * interpretiert werden dürfen.
	 */
	function escapeHtml(text) {
		const div = document.createElement('div');
		div.textContent = text;
		return div.innerHTML;
	}

	/**
	 * Wandelt einfache http(s)-URLs im (bereits escapten) Text in klickbare
	 * Links um und erhält Zeilenumbrüche. Bewusst simpel gehalten (kein
	 * vollständiger Markdown-Parser) — das Modell wird per System-Prompt
	 * angewiesen, ohnehin nur Klartext-URLs statt Markdown-Linksyntax zu liefern.
	 */
	function linkify(escapedText) {
		const urlPattern = /(https?:\/\/[^\s<]+)/g;
		const withLinks = escapedText.replace(urlPattern, function (url) {
			// Trailing Satzzeichen nicht mit in den Link ziehen (z.B. am Satzende).
			const trailingMatch = url.match(/[.,;:!?)]+$/);
			const trailing = trailingMatch ? trailingMatch[0] : '';
			const cleanUrl = trailing ? url.slice(0, -trailing.length) : url;
			return '<a href="' + cleanUrl + '" target="_blank" rel="noopener noreferrer">' + cleanUrl + '</a>' + trailing;
		});
		return withLinks.replace(/\n/g, '<br>');
	}

	function renderShell(i18n, settings) {
		return (
			'<button type="button" class="mlt-ai-toggle" aria-expanded="false" aria-label="' + i18n.openLabel + '">' + i18n.buttonLabel + '</button>' +
			dismissMarkup(i18n, settings) +
			'<div class="mlt-ai-panel" role="dialog" aria-label="' + i18n.title + '">' +
			'  <div class="mlt-ai-header">' +
			'    <span>' + i18n.title + '</span>' +
			'    <button type="button" class="mlt-ai-close" aria-label="' + i18n.closeLabel + '">&times;</button>' +
			'  </div>' +
			'  <div class="mlt-ai-messages"></div>' +
			'  <form class="mlt-ai-form">' +
			'    <input type="text" class="mlt-ai-input" maxlength="' + MAX_MESSAGE_LENGTH + '" placeholder="' + i18n.placeholder + '" autocomplete="off" />' +
			'    <button type="submit">' + i18n.sendLabel + '</button>' +
			'  </form>' +
			'  <p class="mlt-ai-disclaimer">' + i18n.disclaimer + '</p>' +
			'</div>'
		);
	}

	/**
	 * Session-ID ist ein zufälliger, nicht-personenbezogener Identifier —
	 * dient nur der Zuordnung von Nachrichten innerhalb einer Konversation
	 * für den Backend-Log (siehe wp_mlt_ai_conversations). Kein Tracking-Cookie.
	 */
	function getOrCreateSessionId() {
		let id = safeStorageGet(STORAGE_KEY);
		if (!id) {
			id = 'mlt_' + Array.from(crypto.getRandomValues(new Uint8Array(16)))
				.map((b) => b.toString(16).padStart(2, '0'))
				.join('');
			safeStorageSet(STORAGE_KEY, id);
		}
		return id;
	}

	/**
	 * EU-AI-Act Art. 50 Transparenzpflicht: Begrüßung weist explizit auf den
	 * maschinellen Charakter hin (siehe Datenschutz-Recherche im Projekt).
	 */
	function getStrings(lang) {
		const strings = {
			de: {
				buttonLabel: 'Chat',
				openLabel: 'Chat öffnen',
				closeLabel: 'Chat schließen',
				dismissLabel: 'Chat-Assistent ausblenden',
				title: 'Assistent',
				placeholder: 'Ihre Nachricht …',
				sendLabel: 'Senden',
				greeting: 'Hallo! Ich bin ein automatischer Assistent und helfe Ihnen gerne bei ersten Fragen. Bei komplexeren Anliegen vermittle ich Sie gerne an unser Team.',
				disclaimer: 'Automatisierter Chat gemäß Art. 50 EU AI Act. Bitte keine sensiblen persönlichen Daten eingeben.',
				consentText: 'Für den Chat werden Ihre Eingaben an einen KI-Dienstleister außerhalb der EU übermittelt. Mit Klick auf "Zustimmen" willigen Sie dieser Datenübertragung ein.',
				consentAccept: 'Zustimmen und Chat starten',
				typing: 'Schreibt …',
				tooLong: 'Ihre Nachricht ist zu lang. Bitte kürzer fassen.',
				sessionLimit: 'Sie haben das Nachrichtenlimit für diese Unterhaltung erreicht. Bitte kontaktieren Sie uns direkt.',
				budgetExceeded: 'Der Assistent ist aktuell nicht verfügbar. Bitte kontaktieren Sie uns direkt.',
				genericError: 'Es gab ein Problem. Bitte versuchen Sie es später erneut oder kontaktieren Sie uns direkt.',
				maxMessages: window.mltAiConfig && window.mltAiConfig.maxMessages ? window.mltAiConfig.maxMessages : 15,
			},
			en: {
				buttonLabel: 'Chat',
				openLabel: 'Open chat',
				closeLabel: 'Close chat',
				dismissLabel: 'Hide chat assistant',
				title: 'Assistant',
				placeholder: 'Your message …',
				sendLabel: 'Send',
				greeting: "Hi! I'm an automated assistant and happy to help with initial questions. For anything more complex, I'll connect you with our team.",
				disclaimer: 'Automated chat per Art. 50 EU AI Act. Please avoid entering sensitive personal data.',
				consentText: 'Using the chat sends your input to an AI provider outside the EU. By clicking "Accept" you consent to this data transfer.',
				consentAccept: 'Accept and start chat',
				typing: 'Typing …',
				tooLong: 'Your message is too long. Please shorten it.',
				sessionLimit: 'You have reached the message limit for this conversation. Please contact us directly.',
				budgetExceeded: 'The assistant is currently unavailable. Please contact us directly.',
				genericError: 'Something went wrong. Please try again later or contact us directly.',
				maxMessages: window.mltAiConfig && window.mltAiConfig.maxMessages ? window.mltAiConfig.maxMessages : 15,
			},
		};

		return strings[lang] || strings.de;
	}

	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('[data-mlt-ai-widget]').forEach(initWidget);
	});
})();
