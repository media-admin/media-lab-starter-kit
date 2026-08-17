/**
 * Facebook Video – Cookie Consent Integration
 * Datei: assets/src/js/components/fb-video-consent.js
 *
 * Analog zu google-maps.js aufgebaut:
 *
 *  1. Beim Seitenload: Prüfe ob Komfort-Consent bereits vorliegt
 *     → Ja: Lade alle Facebook-Video-Embeds sofort
 *     → Nein: Zeige Placeholder (Thumbnail + Button), warte auf cookies:changed
 *
 *  2. Auf cookies:changed Event hören:
 *     → Wenn comfort=true: Video nachladen (ohne Reload)
 *     → Wenn comfort=false: Video entladen, Placeholder wieder anzeigen
 *
 *  3. Button-Interaktionen im Placeholder:
 *     → "Facebook-Video laden & abspielen": Akzeptiert Komfort-Kategorie direkt
 *     → "Cookie-Einstellungen anpassen": Öffnet das Cookie Modal
 *
 * Eigenständiges Modul – kommuniziert ausschließlich über
 * window.CookieConsent (Public API aus Agency Core) und das
 * cookies:changed DOM-Event, kein Import aus cookie-notice.js nötig.
 */

const FacebookVideoConsent = {

    /**
     * Alle .ml-block-facebook-video Wrapper auf der aktuellen Seite.
     * @type {NodeListOf<HTMLElement>}
     */
    videos: null,

    // ── Init ──────────────────────────────────────────────────────────────────

    init() {
        this.videos = document.querySelectorAll( '.ml-block-facebook-video' );

        if ( ! this.videos.length ) return;

        this._applyConsentState();

        document.addEventListener( 'cookies:changed', ( event ) => {
            this._handleConsentChange( event.detail );
        } );

        document.addEventListener( 'click', ( event ) => {
            if ( event.target.closest( '[data-fbv-accept-comfort]' ) ) {
                this._acceptComfort();
            }

            if ( event.target.closest( '[data-fbv-open-settings]' ) ) {
                this._openSettings();
            }
        } );
    },

    // ── Consent-Status auslesen ───────────────────────────────────────────────

    /**
     * Prüft ob Komfort-Cookies akzeptiert wurden.
     * Nutzt die Public API von CookieConsent (window.CookieConsent.hasConsent).
     *
     * @returns {boolean}
     */
    _hasComfortConsent() {
        if ( window.CookieConsent && typeof window.CookieConsent.hasConsent === 'function' ) {
            return window.CookieConsent.hasConsent( 'comfort' );
        }
        return false;
    },

    // ── Video laden / entladen ────────────────────────────────────────────────

    /**
     * Wendet den aktuellen Consent-Status auf alle Facebook-Video-Embeds an.
     * Wird beim Init und nach cookies:changed aufgerufen.
     */
    _applyConsentState() {
        const hasConsent = this._hasComfortConsent();

        this.videos.forEach( ( videoEl ) => {
            if ( hasConsent ) {
                this._loadVideo( videoEl );
            } else {
                this._showPlaceholder( videoEl );
            }
        } );
    },

    /**
     * Lädt das Video: Setzt das src-Attribut des iframes, blendet
     * Thumbnail/Button aus und das iframe ein.
     *
     * @param {HTMLElement} videoEl – der .ml-block-facebook-video Wrapper
     */
    _loadVideo( videoEl ) {
        const iframe      = videoEl.querySelector( '.ml-fbv__iframe' );
        const thumbnail   = videoEl.querySelector( '.ml-fbv__thumbnail' );
        const playButton  = videoEl.querySelector( '.ml-fbv__play-button' );

        if ( ! iframe ) return;

        if ( ! iframe.src ) {
            const dataSrc = iframe.dataset.src;
            if ( dataSrc ) {
                iframe.src = dataSrc;
            }
        }

        iframe.removeAttribute( 'hidden' );
        iframe.classList.add( 'is-loaded' );

        if ( thumbnail )  thumbnail.classList.add( 'is-hidden' );
        if ( playButton ) playButton.classList.add( 'is-hidden' );
    },

    /**
     * Zeigt Thumbnail + Button wieder an und entlädt das iframe.
     * Wird aufgerufen, wenn der Besucher Komfort-Cookies widerruft.
     *
     * @param {HTMLElement} videoEl – der .ml-block-facebook-video Wrapper
     */
    _showPlaceholder( videoEl ) {
        const iframe      = videoEl.querySelector( '.ml-fbv__iframe' );
        const thumbnail   = videoEl.querySelector( '.ml-fbv__thumbnail' );
        const playButton  = videoEl.querySelector( '.ml-fbv__play-button' );

        if ( iframe ) {
            // src leeren verhindert weiteres Tracking im Hintergrund
            iframe.src = '';
            iframe.setAttribute( 'hidden', '' );
            iframe.classList.remove( 'is-loaded' );
        }

        if ( thumbnail )  thumbnail.classList.remove( 'is-hidden' );
        if ( playButton ) playButton.classList.remove( 'is-hidden' );
    },

    // ── Event Handler ─────────────────────────────────────────────────────────

    /**
     * Reagiert auf cookies:changed – lädt oder entlädt alle Videos.
     *
     * @param {Object} detail – { necessary, statistics, marketing, comfort }
     */
    _handleConsentChange( detail ) {
        if ( ! detail ) return;

        this.videos.forEach( ( videoEl ) => {
            if ( detail.comfort ) {
                this._loadVideo( videoEl );
            } else {
                this._showPlaceholder( videoEl );
            }
        } );
    },

    /**
     * "Facebook-Video laden & abspielen":
     * Akzeptiert die Komfort-Kategorie direkt über die CookieConsent API.
     * Das cookies:changed Event aus cookie-notice.js übernimmt den Rest.
     */
    _acceptComfort() {
        if ( window.CookieConsent && typeof window.CookieConsent.acceptCategory === 'function' ) {
            window.CookieConsent.acceptCategory( 'comfort' );
        } else {
            console.warn( '[FacebookVideoConsent] CookieConsent.acceptCategory nicht verfügbar.' );
        }
    },

    /**
     * Öffnet das Cookie-Settings Modal über die CookieConsent Public API.
     */
    _openSettings() {
        if ( window.CookieConsent && typeof window.CookieConsent.openSettings === 'function' ) {
            window.CookieConsent.openSettings();
        }
    },
};

export default FacebookVideoConsent;
