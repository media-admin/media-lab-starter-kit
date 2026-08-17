/**
 * Social Embed (Facebook/Instagram) – Cookie Consent Integration
 * Datei: assets/src/js/components/social-embed-consent.js
 *
 * Gleiches Grundmuster wie google-maps.js / fb-video-consent.js, aber mit
 * providerabhängiger Lade-Logik:
 *
 *   Facebook  – simples iframe, src wird gesetzt/geleert (wie bisher)
 *   Instagram – kein fertiges iframe verfügbar. Meta liefert stattdessen
 *               ein <blockquote class="instagram-media" data-instgrm-permalink="...">
 *               + ein extern nachgeladenes Script (instagram.com/embed.js),
 *               das beim Aufruf von window.instgrm.Embeds.process() alle
 *               .instagram-media-Elemente auf der Seite in ein iframe
 *               verwandelt.
 *
 * Wichtige Konsequenz für den Consent-Widerruf: Das Instagram-Script selbst
 * lässt sich nicht "entladen" (bleibt im Browser-Speicher), sobald es einmal
 * geladen wurde - es sendet aber KEINE weiteren Daten, bis process() erneut
 * aufgerufen wird. Beim Widerruf entfernen wir daher nur den gerenderten
 * Embed-Inhalt (blockquote/iframe) aus dem DOM; das Script selbst bleibt
 * inaktiv im Hintergrund. Das Script wird ohnehin erst NACH Consent-Klick
 * überhaupt erstmalig geladen (kein Request vor Einwilligung).
 *
 * Eigenständiges Modul – kommuniziert über window.CookieConsent (Public API
 * aus Agency Core) und das cookies:changed DOM-Event.
 */

const SocialEmbedConsent = {

    /** @type {NodeListOf<HTMLElement>} */
    embeds: null,

    // ── Init ──────────────────────────────────────────────────────────────────

    init() {
        this.embeds = document.querySelectorAll( '.ml-block-social-embed' );

        if ( ! this.embeds.length ) return;

        this._applyConsentState();

        document.addEventListener( 'cookies:changed', ( event ) => {
            this._handleConsentChange( event.detail );
        } );

        document.addEventListener( 'click', ( event ) => {
            if ( event.target.closest( '[data-se-accept-comfort]' ) ) {
                this._acceptComfort();
            }
            if ( event.target.closest( '[data-se-open-settings]' ) ) {
                this._openSettings();
            }
        } );
    },

    // ── Consent-Status auslesen ───────────────────────────────────────────────

    _hasComfortConsent() {
        if ( window.CookieConsent && typeof window.CookieConsent.hasConsent === 'function' ) {
            return window.CookieConsent.hasConsent( 'comfort' );
        }
        return false;
    },

    _applyConsentState() {
        const hasConsent = this._hasComfortConsent();

        this.embeds.forEach( ( el ) => {
            if ( hasConsent ) {
                this._loadEmbed( el );
            } else {
                this._showPlaceholder( el );
            }
        } );
    },

    // ── Laden (providerabhängig) ──────────────────────────────────────────────

    _loadEmbed( el ) {
        const provider = el.dataset.provider;

        if ( provider === 'instagram' ) {
            this._loadInstagram( el );
        } else {
            this._loadFacebook( el );
        }

        const thumbnail  = el.querySelector( '.ml-social-embed__thumbnail' );
        const playButton = el.querySelector( '.ml-social-embed__play-button' );

        if ( thumbnail )  thumbnail.classList.add( 'is-hidden' );
        if ( playButton ) playButton.classList.add( 'is-hidden' );
    },

    /**
     * Facebook: iframe, wie beim ursprünglichen fb-video-consent.js.
     */
    _loadFacebook( el ) {
        const container = el.querySelector( '.ml-social-embed__embed' );
        if ( ! container || container.dataset.loaded === 'true' ) return;

        const url      = el.dataset.url;
        const showText = el.dataset.showText === 'true';
        const src = `https://www.facebook.com/plugins/video.php?href=${ encodeURIComponent( url ) }&show_text=${ showText }&width=734`;

        const iframe = document.createElement( 'iframe' );
        iframe.src = src;
        iframe.loading = 'lazy';
        iframe.allowFullscreen = true;
        iframe.setAttribute( 'scrolling', 'no' );
        iframe.setAttribute( 'frameborder', '0' );
        iframe.setAttribute( 'allow', 'autoplay; clipboard-write; encrypted-media; picture-in-picture; web-share' );
        iframe.title = 'Facebook Video';

        container.innerHTML = '';
        container.appendChild( iframe );
        container.removeAttribute( 'hidden' );
        container.dataset.loaded = 'true';
    },

    /**
     * Instagram: blockquote + embed.js. Script wird lazy nachgeladen (nur
     * einmal pro Seite, auch bei mehreren Instagram-Embeds), danach
     * window.instgrm.Embeds.process() aufgerufen, das alle .instagram-media
     * Elemente im DOM verarbeitet.
     */
    _loadInstagram( el ) {
        const container = el.querySelector( '.ml-social-embed__embed' );
        if ( ! container || container.dataset.loaded === 'true' ) return;

        const url = el.dataset.url;

        const blockquote = document.createElement( 'blockquote' );
        blockquote.className = 'instagram-media';
        blockquote.setAttribute( 'data-instgrm-permalink', url );
        blockquote.setAttribute( 'data-instgrm-version', '14' );
        blockquote.style.margin = '0';

        container.innerHTML = '';
        container.appendChild( blockquote );
        container.removeAttribute( 'hidden' );
        container.dataset.loaded = 'true';

        this._ensureInstagramScript( () => {
            if ( window.instgrm?.Embeds?.process ) {
                window.instgrm.Embeds.process();
            }
        } );
    },

    /**
     * Lädt instagram.com/embed.js genau einmal pro Seite (nicht pro Embed).
     * Bewusst erst hier aufgerufen - vor dem ersten Consent-Klick gibt es
     * keinerlei Request an instagram.com.
     */
    _ensureInstagramScript( callback ) {
        if ( window.instgrm?.Embeds ) {
            callback();
            return;
        }

        const existing = document.querySelector( 'script[data-se-instagram-embed]' );
        if ( existing ) {
            existing.addEventListener( 'load', callback, { once: true } );
            return;
        }

        const script = document.createElement( 'script' );
        script.src = 'https://www.instagram.com/embed.js';
        script.async = true;
        script.setAttribute( 'data-se-instagram-embed', 'true' );
        script.addEventListener( 'load', callback, { once: true } );
        document.body.appendChild( script );
    },

    // ── Entladen / Placeholder ─────────────────────────────────────────────────

    /**
     * Entfernt den gerenderten Embed-Inhalt (iframe bzw. Instagram-Blockquote/
     * -iframe) und zeigt Thumbnail + Button wieder an. Bei Instagram bleibt
     * embed.js selbst im Speicher (siehe Datei-Kommentar oben), sendet aber
     * ohne erneuten process()-Aufruf keine weiteren Daten.
     */
    _showPlaceholder( el ) {
        const container   = el.querySelector( '.ml-social-embed__embed' );
        const thumbnail   = el.querySelector( '.ml-social-embed__thumbnail' );
        const playButton  = el.querySelector( '.ml-social-embed__play-button' );

        if ( container ) {
            container.innerHTML = '';
            container.setAttribute( 'hidden', '' );
            delete container.dataset.loaded;
        }

        if ( thumbnail )  thumbnail.classList.remove( 'is-hidden' );
        if ( playButton ) playButton.classList.remove( 'is-hidden' );
    },

    // ── Event Handler ─────────────────────────────────────────────────────────

    _handleConsentChange( detail ) {
        if ( ! detail ) return;

        this.embeds.forEach( ( el ) => {
            if ( detail.comfort ) {
                this._loadEmbed( el );
            } else {
                this._showPlaceholder( el );
            }
        } );
    },

    _acceptComfort() {
        if ( window.CookieConsent && typeof window.CookieConsent.acceptCategory === 'function' ) {
            window.CookieConsent.acceptCategory( 'comfort' );
        } else {
            console.warn( '[SocialEmbedConsent] CookieConsent.acceptCategory nicht verfügbar.' );
        }
    },

    _openSettings() {
        if ( window.CookieConsent && typeof window.CookieConsent.openSettings === 'function' ) {
            window.CookieConsent.openSettings();
        }
    },
};

export default SocialEmbedConsent;
