/**
 * ML Logo-Slider – Theme-Komponente
 *
 * Initialisiert alle .ml-logo-slider__swiper Elemente.
 * Import-Pattern identisch zu anderen Theme-Slidern.
 *
 * @since 1.11.0
 * @updated 1.11.4  Korrektes Import-Pattern
 * @updated 1.19.x  WCAG-2.2.2-Fokus-Pause ergänzt (siehe BACKLOG.md,
 *                  media-lab-agency-core): die entfernte
 *                  assets/js/block-logo-slider.js aus dem Plugin enthielt
 *                  eine Autoplay-Pause bei Tastaturfokus, die bei der
 *                  Migration hierher (Theme-seitige Implementierung) nie
 *                  übernommen wurde. War laut damaligem Code-Kommentar
 *                  durch einen Lade-Bug ohnehin nie aktiv (totes JS wurde
 *                  nie ausgeführt) - keine neue Regression, aber bis jetzt
 *                  ein fehlendes Feature.
 */

import Swiper from 'swiper';
import { Autoplay } from 'swiper/modules';

export default class MLLogoSlider {
    constructor() {
        this.sliders = document.querySelectorAll( '.ml-logo-slider__swiper' );
        if ( ! this.sliders.length ) return;
        this.init();
    }

    init() {
        this.sliders.forEach( el => {
            if ( el.swiper ) return;

            let config = {};
            try {
                config = JSON.parse( el.dataset.swiper || '{}' );
            } catch ( e ) {
                console.warn( '[ml-logo-slider] Ungültige Config:', e );
                return;
            }

            config.modules = [ Autoplay ];

            try {
                const swiper = new Swiper( el, config );
                this.bindFocusPause( el, swiper );
            } catch ( err ) {
                console.error( '[ml-logo-slider] Init-Fehler:', err );
            }
        } );
    }

    /**
     * WCAG 2.2.2 (Pause, Stop, Hide): Inhalte, die länger als 5 Sekunden
     * automatisch bewegt/wechseln, brauchen einen Mechanismus zum
     * Pausieren. Für Tastatur- und Screenreader-Nutzer ist der übliche
     * Mechanismus: Autoplay pausiert automatisch, sobald ein Element
     * INNERHALB des Sliders (z.B. ein Logo-Link) den Fokus erhält - sonst
     * scrollt der Slider während der Tastatur-Navigation unbemerkt weiter
     * und reißt den fokussierten Inhalt aus dem sichtbaren Bereich.
     *
     * Nutzt bewusst focusin/focusout auf dem Slider-Root (beide Events
     * bubbeln) statt eines Listeners pro Slide/Link - ein Eventpaar pro
     * Slider reicht aus.
     */
    bindFocusPause( el, swiper ) {
        if ( ! swiper.autoplay ) return; // Autoplay nicht konfiguriert/aktiv

        el.addEventListener( 'focusin', () => {
            swiper.autoplay.stop();
        } );

        el.addEventListener( 'focusout', ( event ) => {
            // relatedTarget ist das Element, das den Fokus NEU erhält -
            // null bei Klick auf eine nicht-fokussierbare Fläche oder beim
            // Verlassen des Browser-Fensters/-Tabs. Nur neu starten, wenn
            // der Fokus den Slider komplett verlassen hat - nicht bei
            // jedem Wechsel ZWISCHEN zwei Logo-Links innerhalb desselben
            // Sliders (sonst würde Autoplay während der gesamten
            // Tab-Navigation durch die Slides ständig an-/abspringen).
            if ( ! event.relatedTarget || ! el.contains( event.relatedTarget ) ) {
                swiper.autoplay.start();
            }
        } );
    }
}
