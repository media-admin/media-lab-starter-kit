/**
 * ML Slider – Theme-Komponente
 *
 * Initialisiert alle .ml-slider__swiper Elemente.
 * Import-Pattern identisch zu hero-slider.js und carousel.js im Theme.
 *
 * Da die Folien bereits PHP-seitig in .swiper-slide gewickelt sind,
 * muss dieses Script sie NICHT mehr nachträglich wrappen.
 *
 * @since 1.11.0
 * @updated 1.11.4  Korrektes Import-Pattern, kein JS-Wrapping mehr nötig
 * @updated 1.15.2  Skeleton-Fallback ergänzt (siehe block-slider.css in
 *                  media-lab-agency-core); übernimmt jetzt auch die
 *                  Swiper-Initialisierung für medialab/slider komplett -
 *                  das duplizierte, defekte Plugin-Script block-slider.js
 *                  (window.Swiper nie verfügbar, SyntaxError beim Laden
 *                  des rohen Swiper-ESM-Chunks als classic script) wurde
 *                  entfernt.
 */

import Swiper from 'swiper';
import { Navigation, Pagination, Autoplay, EffectFade, EffectCoverflow } from 'swiper/modules';

export default class MLSlider {
    constructor() {
        this.sliders = document.querySelectorAll( '.ml-slider__swiper' );
        if ( ! this.sliders.length ) return;
        this.init();
    }

    /**
     * Beendet den CSS-Skeleton (siehe media-lab-agency-core/assets/css/
     * block-slider.css) für ein Slider-Element, das NICHT durch Swiper
     * selbst initialisiert wurde (Swiper setzt "swiper-initialized" nur
     * bei Erfolg). Verhindert, dass die Folien bei einem Fehler für immer
     * hinter dem Shimmer-Platzhalter verschwinden.
     */
    revealSkeletonFallback( el ) {
        el.classList.add( 'ml-slider__swiper--skeleton-done' );
    }

    init() {
        this.sliders.forEach( el => {
            if ( el.swiper ) return; // bereits initialisiert

            let config = {};
            try {
                config = JSON.parse( el.dataset.swiper || '{}' );
            } catch ( e ) {
                console.warn( '[ml-slider] Ungültige Config:', e );
                this.revealSkeletonFallback( el );
                return;
            }

            // Module je nach Konfiguration laden
            const modules = [ Navigation, Pagination, Autoplay ];
            if ( config.effect === 'fade' )       modules.push( EffectFade );
            if ( config.effect === 'coverflow' )  modules.push( EffectCoverflow );
            config.modules = modules;

            // Navigation: DOM-Referenzen aus PHP-Markup
            const parent = el.closest( '.ml-block-slider' );
            if ( config.navigation && parent ) {
                const prev = parent.querySelector( '.swiper-button-prev' );
                const next = parent.querySelector( '.swiper-button-next' );
                if ( prev && next ) {
                    config.navigation = { prevEl: prev, nextEl: next };
                } else {
                    config.navigation = false;
                }
            }

            // Pagination: DOM-Referenz
            if ( config.pagination && parent ) {
                const pag = parent.querySelector( '.swiper-pagination' );
                if ( pag ) {
                    config.pagination = { ...config.pagination, el: pag };
                } else {
                    delete config.pagination;
                }
            }

            try {
                new Swiper( el, config );
                // Kein manuelles revealSkeletonFallback() nötig: Swiper
                // selbst setzt bei Erfolg "swiper-initialized", das CSS
                // reagiert direkt darauf.
            } catch ( err ) {
                console.error( '[ml-slider] Init-Fehler:', err );
                this.revealSkeletonFallback( el );
            }
        } );
    }
}
