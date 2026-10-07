/**
 * AJAX Live Search Component
 *
 * Konfiguration (Texte, Limit, Post-Types, Mindestzeichen, Debounce,
 * Anzeige-Optionen, Sprache) kommt aus dem data-config-Attribut, das
 * MediaLab_Search_Settings::container_attrs() (Agency Core) rendert.
 * Fehlt das Attribut (Altmarkup), greifen die Defaults unten - das
 * Verhalten entspricht dann dem bisherigen Stand.
 */

const DEFAULT_CONFIG = {
    limit: 5,
    postTypes: ['post', 'page'],
    minChars: 2,
    debounce: 300,
    lang: '',
    show: {
        thumbnail: true,
        date: true,
        type: true,
        excerpt: true,
        price: true,
        allLink: false,
    },
    i18n: {
        intro: '',
        minHint: '',
        noResults: 'Keine Ergebnisse gefunden.',
        error: 'Ein Fehler ist aufgetreten. Bitte versuchen Sie es erneut.',
        showAll: 'Alle Ergebnisse anzeigen',
        typeLabels: {
            post: 'Beitrag',
            page: 'Seite',
            product: 'Produkt',
            project: 'Projekt',
            service: 'Leistung',
            job: 'Job',
        },
    },
};

const HTML_ESCAPES = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };

(function () { // Direkte Ausführung - Dynamic Import läuft nach DOMContentLoaded
    const searchContainers = document.querySelectorAll('.ajax-search');

    if (searchContainers.length === 0) return;

    searchContainers.forEach(initSearch);
})();

/**
 * Konfiguration aus data-Attributen lesen und mit Defaults mergen.
 */
function readConfig(container) {
    let parsed = {};

    try {
        parsed = JSON.parse(container.dataset.config || '{}') || {};
    } catch (e) {
        console.warn('ajax-search: data-config ist kein gültiges JSON, nutze Defaults.', e);
    }

    const cfg = {
        ...DEFAULT_CONFIG,
        ...parsed,
        show: { ...DEFAULT_CONFIG.show, ...(parsed.show || {}) },
        i18n: {
            ...DEFAULT_CONFIG.i18n,
            ...(parsed.i18n || {}),
            typeLabels: {
                ...DEFAULT_CONFIG.i18n.typeLabels,
                ...((parsed.i18n && parsed.i18n.typeLabels) || {}),
            },
        },
    };

    // Altmarkup ohne data-config: einzelne data-Attribute weiterhin unterstützen
    if (!container.dataset.config) {
        const limit = parseInt(container.dataset.limit, 10);
        if (limit > 0) cfg.limit = limit;

        if (container.dataset.postTypes) {
            cfg.postTypes = container.dataset.postTypes.split(',').map(type => type.trim()).filter(Boolean);
        }
    }

    return cfg;
}

function initSearch(container) {
    const form = container.querySelector('.ajax-search__form');
    const input = container.querySelector('.ajax-search__input');
    const resultsContainer = container.querySelector('.ajax-search__results');
    const loadingIndicator = container.querySelector('.ajax-search__loading');
    const submitButton = container.querySelector('.ajax-search__submit');

    if (!input || !resultsContainer || !form) return;

    const cfg = readConfig(container);

    let debounceTimer;
    let requestId = 0; // verwirft veraltete Antworten (Race Condition bei schnellem Tippen)

    // ============================================
    // UI-Helper
    // ============================================
    function setLoading(isLoading) {
        if (loadingIndicator) {
            loadingIndicator.style.display = isLoading ? 'block' : 'none';
        }
        if (submitButton) {
            submitButton.style.display = isLoading ? 'none' : 'flex';
        }
    }

    function hideResults() {
        resultsContainer.style.display = 'none';
        resultsContainer.innerHTML = '';
    }

    function renderMessage(text, className) {
        resultsContainer.innerHTML =
            `<div class="${className}">${escapeHtml(text).replace(/\n/g, '<br>')}</div>`;
        resultsContainer.style.display = 'block';
    }

    /**
     * Leerlauf-Zustand: Startertext (Feld leer) bzw. Mindestzeichen-Hinweis.
     * Ohne konfigurierten Text wird wie bisher einfach ausgeblendet.
     */
    function showIdle(length) {
        if (length === 0 && cfg.i18n.intro) {
            renderMessage(cfg.i18n.intro, 'ajax-search__intro');
        } else if (length > 0 && cfg.i18n.minHint) {
            renderMessage(cfg.i18n.minHint.replace('{min}', cfg.minChars), 'ajax-search__hint');
        } else {
            hideResults();
        }
    }

    // ============================================
    // AJAX LIVE SEARCH (on input)
    // ============================================
    input.addEventListener('input', function () {
        const query = this.value.trim();

        clearTimeout(debounceTimer);

        if (query.length < cfg.minChars) {
            requestId++; // laufende Anfrage ignorieren
            setLoading(false);
            showIdle(query.length);
            return;
        }

        setLoading(true);

        debounceTimer = setTimeout(() => performSearch(query), cfg.debounce);
    });

    // Startertext beim Fokussieren (z. B. beim Öffnen des Nav-Overlays)
    input.addEventListener('focus', function () {
        if (this.value.trim().length === 0) {
            showIdle(0);
        }
    });

    // ============================================
    // FORM SUBMIT (Enter oder Button Click)
    // ============================================
    form.addEventListener('submit', function (e) {
        if (input.value.trim().length < cfg.minChars) {
            e.preventDefault();
        }
        // sonst: Formular normal absenden -> /?s=query
    });

    // ============================================
    // CLICK ON SEARCH RESULT (navigate directly)
    // ============================================
    resultsContainer.addEventListener('click', function (e) {
        if (e.target.closest('.ajax-search__item, .ajax-search__all')) {
            resultsContainer.style.display = 'none';
        }
    });

    // ============================================
    // CLOSE RESULTS (click outside)
    // ============================================
    document.addEventListener('click', function (e) {
        if (!container.contains(e.target)) {
            resultsContainer.style.display = 'none';
        }
    });

    // ============================================
    // KEYBOARD NAVIGATION (optional)
    // ============================================
    input.addEventListener('keydown', function (e) {
        // ESC = close results
        if (e.key === 'Escape') {
            hideResults();
        }
    });

    // ============================================
    // AJAX REQUEST
    // ============================================
    function performSearch(query) {
        const myRequest = ++requestId;
        const ajaxUrl = window.customTheme?.ajaxUrl || '/wp-admin/admin-ajax.php';
        const nonce = window.customTheme?.searchNonce || '';

        fetch(ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'agency_search',
                nonce: nonce,
                query: query,
                post_types: JSON.stringify(cfg.postTypes),
                limit: String(cfg.limit),
                lang: cfg.lang || '',
            }),
        })
            .then(response => response.json())
            .then(data => {
                if (myRequest !== requestId) return; // veraltete Antwort

                setLoading(false);

                if (!data.success) {
                    // z. B. Rate-Limit (429) oder ungültiger Nonce
                    renderMessage(cfg.i18n.error, 'ajax-search__error');
                } else if (data.data.results && data.data.results.length > 0) {
                    displayResults(data.data.results, query);
                } else {
                    renderMessage(cfg.i18n.noResults, 'ajax-search__no-results');
                }
            })
            .catch(error => {
                if (myRequest !== requestId) return;

                console.error('Search error:', error);
                setLoading(false);
                renderMessage(cfg.i18n.error, 'ajax-search__error');
            });
    }

    // ============================================
    // RENDER RESULTS
    // ============================================
    function displayResults(results, query) {
        const show = cfg.show;
        let html = '<div class="ajax-search__list">';

        results.forEach(result => {
            const typeLabel = cfg.i18n.typeLabels[result.post_type] || result.post_type;
            const showMeta = show.type || (show.date && result.date);

            html += `
                <a href="${escapeAttr(result.permalink)}" class="ajax-search__item ajax-search__item--${escapeAttr(result.post_type)}">
                    ${show.thumbnail && result.thumbnail ? `
                        <div class="ajax-search__thumbnail">
                            <img src="${escapeAttr(result.thumbnail)}" alt="${escapeAttr(stripTags(result.title))}">
                        </div>
                    ` : ''}
                    <div class="ajax-search__content">
                        ${showMeta ? `
                            <div class="ajax-search__meta">
                                ${show.type ? `<span class="ajax-search__type">${escapeHtml(typeLabel)}</span>` : ''}
                                ${show.date && result.date ? `<span class="ajax-search__date">${escapeHtml(result.date)}</span>` : ''}
                            </div>
                        ` : ''}
                        <div class="ajax-search__title">${result.title}</div>
                        ${show.excerpt && result.excerpt ? `<div class="ajax-search__excerpt">${result.excerpt}</div>` : ''}
                        ${show.price && result.price ? `<div class="ajax-search__price">${result.price}</div>` : ''}
                    </div>
                </a>
            `;
        });

        html += '</div>';

        if (show.allLink) {
            const url = new URL(form.action, window.location.href);
            url.searchParams.set('s', query);
            html += `<a href="${escapeAttr(url.toString())}" class="ajax-search__all">${escapeHtml(cfg.i18n.showAll)}</a>`;
        }

        resultsContainer.innerHTML = html;
        resultsContainer.style.display = 'block';
    }
}

// ============================================
// HELPER
// ============================================
// result.title / result.excerpt / result.price kommen bereits serverseitig
// aufbereitet (inkl. <mark>-Highlighting bzw. Preis-HTML) und werden bewusst
// unverändert eingesetzt - alles andere wird hier escaped.

function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, char => HTML_ESCAPES[char]);
}

function escapeAttr(value) {
    return escapeHtml(value);
}

function stripTags(html) {
    return new DOMParser().parseFromString(String(html), 'text/html').body.textContent || '';
}
