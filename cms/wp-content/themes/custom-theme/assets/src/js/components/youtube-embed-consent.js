/**
 * YouTube Embed – Cookie Consent Integration
 * Datei: assets/src/js/components/youtube-embed-consent.js
 *
 * Gleiches Grundmuster wie google-maps.js: Placeholder (Thumbnail + Button)
 * bis Komfort-Consent vorliegt, dann iframe nachladen. Die Platzhalter-
 * Markup kommt server-seitig aus dem render_block-Filter in
 * inc/youtube-embed-consent.php (Plugin) - dieses Modul kümmert sich nur
 * ums Laden/Entladen des iframes.
 *
 * Eigenständiges Modul - kommuniziert über window.CookieConsent (Public
 * API aus Agency Core) und das cookies:changed DOM-Event.
 */

const YoutubeEmbedConsent = {

    /** @type {NodeListOf<HTMLElement>} */
    embeds: null,

    // ── Init ──────────────────────────────────────────────────────────────────

    init() {
        this.embeds = document.querySelectorAll( '.ml-block-youtube-embed' );

        if ( ! this.embeds.length ) return;

        this._applyConsentState();

        document.addEventListener( 'cookies:changed', ( event ) => {
            this._handleConsentChange( event.detail );
        } );

        document.addEventListener( 'click', ( event ) => {
            if ( event.target.closest( '[data-yt-accept-comfort]' ) ) {
                this._acceptComfort();
            }
            if ( event.target.closest( '[data-yt-open-settings]' ) ) {
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

    // ── Laden / Entladen ──────────────────────────────────────────────────────

    _loadEmbed( el ) {
        const container = el.querySelector( '.ml-youtube-embed__embed' );
        if ( ! container || container.dataset.loaded === 'true' ) return;

        const url = el.dataset.url;

        const iframe = document.createElement( 'iframe' );
        iframe.src = url;
        iframe.loading = 'lazy';
        iframe.allowFullscreen = true;
        iframe.setAttribute( 'frameborder', '0' );
        iframe.setAttribute( 'allow', 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share' );
        iframe.title = 'YouTube Video';

        container.innerHTML = '';
        container.appendChild( iframe );
        container.removeAttribute( 'hidden' );
        container.dataset.loaded = 'true';

        const thumbnail  = el.querySelector( '.ml-youtube-embed__thumbnail' );
        const playButton = el.querySelector( '.ml-youtube-embed__play-button' );
        if ( thumbnail )  thumbnail.classList.add( 'is-hidden' );
        if ( playButton ) playButton.classList.add( 'is-hidden' );
    },

    _showPlaceholder( el ) {
        const container  = el.querySelector( '.ml-youtube-embed__embed' );
        const thumbnail  = el.querySelector( '.ml-youtube-embed__thumbnail' );
        const playButton = el.querySelector( '.ml-youtube-embed__play-button' );

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
            console.warn( '[YoutubeEmbedConsent] CookieConsent.acceptCategory nicht verfügbar.' );
        }
    },

    _openSettings() {
        if ( window.CookieConsent && typeof window.CookieConsent.openSettings === 'function' ) {
            window.CookieConsent.openSettings();
        }
    },
};

export default YoutubeEmbedConsent;
