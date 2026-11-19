/**
 * Varianten-Vorschaubilder in der Galerie (siehe inc/variation-color-thumbs.php).
 *
 * Verfügbare Daten aus wp_localize_script: mlwColorThumbs.attribute (z. B. "attribute_pa_color"), .groupLabel,
 * .items (Liste aus value, label, src, parent).
 *
 * Die Vorschaubilder der Farben stehen als weitere Einträge in der Vorschaubild-Leiste der WooCommerce-Galerie
 * (.flex-control-thumbs), also im selben Container wie die Bilder des Produkts. Gezeigt werden die ANDEREN Farben:
 * die gewählte Farbe entfällt, weil die Galerie ihre Bilder schon zeigt. Ist keine Farbe gewählt, entfällt die Farbe,
 * deren Bild das Produkt ohnehin als Hauptbild zeigt (parent).
 *
 * Ein Klick wählt die Farbe im Variantenformular und löst das change-Ereignis aus. Alles Weitere (Hauptbild und Galerie
 * der Farbe, Preise, Zurücksetzen) übernehmen WooCommerce und die Funktion "Variantenbilder bei der Farbwahl".
 *
 * WooCommerce und FlexSlider bauen die Leiste teils erst nach dem Laden auf oder neu auf. Deshalb beobachtet das Skript die
 * Galerie und setzt fehlende Einträge wieder ein. Der Klick hängt am Galerie-Container und übersteht das.
 */
( function ( $ ) {
    'use strict';
    if ( typeof mlwColorThumbs === 'undefined' || ! mlwColorThumbs.items || ! mlwColorThumbs.items.length ) return;

    function setup( $form ) {
        var attribute = mlwColorThumbs.attribute;
        var items     = mlwColorThumbs.items;
        var $gallery  = $( '.woocommerce-product-gallery' ).first();
        if ( ! $gallery.length ) return;

        function field() { return $form.find( '[name="' + attribute + '"]' ).first(); }

        /** Frische Listeneinträge (immer neu gebaut, nie wiederverwendet: die Leiste kann ersetzt worden sein). */
        function build() {
            return $.map( items, function ( item ) {
                var $img = $( '<img alt="" loading="lazy" decoding="async" width="100" height="100">' ).attr( 'src', item.src );
                var $btn = $( '<button type="button" class="mlw-vthumb__btn"></button>' )
                    .attr( { 'aria-label': ( mlwColorThumbs.groupLabel ? mlwColorThumbs.groupLabel + ': ' : '' ) + item.label, title: item.label } )
                    .append( $img );
                return $( '<li class="mlw-vthumb"></li>' )
                    .attr( { 'data-value': item.value, 'data-parent': item.parent ? '1' : '0' } )
                    .append( $btn )[ 0 ];
            } );
        }

        // ── Einsetzen ───────────────────────────────────────────────────────
        /**
         * Einträge in die Vorschaubild-Leiste setzen, wenn sie dort fehlen. Gibt es nach dem Laden gar keine Leiste
         * (zum Beispiel bei nur einem Bild), legt force eine an, mit denselben Klassen wie die von FlexSlider.
         */
        function place( force ) {
            var $nav = $gallery.find( '.flex-control-nav' ).first();
            if ( ! $nav.length && force ) {
                $nav = $( '<ol class="flex-control-nav flex-control-thumbs"></ol>' ).appendTo( $gallery );
            }
            if ( ! $nav.length ) return;
            if ( $nav.children( '.mlw-vthumb' ).length === items.length ) return;
            $gallery.find( '.mlw-vthumb' ).remove();
            $nav.append( build() );
            sync();
        }

        // ── Zustand ─────────────────────────────────────────────────────────
        /** Die gewählte Farbe (oder bei leerer Auswahl die Farbe des Hauptbilds) ausblenden, nicht vorhandene Farben sperren. */
        function sync() {
            var $field  = field();
            var current = $field.length ? String( $field.val() || '' ) : '';
            var options = {};
            $field.find( 'option' ).each( function () { options[ this.value ] = ! this.disabled; } );
            var checkOptions = $field.is( 'select' );
            $gallery.find( '.mlw-vthumb' ).each( function () {
                var $li    = $( this );
                var value  = String( $li.attr( 'data-value' ) );
                var hide   = current !== '' ? value === current : $li.attr( 'data-parent' ) === '1';
                var usable = ! checkOptions || options[ value ] === true;
                $li.toggleClass( 'is-hidden', hide ).toggleClass( 'is-unavailable', ! usable );
                $li.children( 'button' ).prop( 'disabled', ! usable );
            } );
        }

        $gallery.on( 'click', '.mlw-vthumb__btn', function () {
            var $field = field();
            if ( ! $field.length ) return;
            $field.val( String( $( this ).closest( '.mlw-vthumb' ).attr( 'data-value' ) ) ).trigger( 'change' );
            sync();
        } );

        var timer = null;
        function schedule() { clearTimeout( timer ); timer = setTimeout( sync, 0 ); }
        $form.on( 'change', 'select', schedule );
        $form.on( 'found_variation hide_variation reset_data woocommerce_update_variation_values', schedule );

        // Leiste kommt später oder wird ersetzt: Einträge wieder einsetzen
        if ( typeof MutationObserver === 'function' ) {
            var frame = null;
            new MutationObserver( function () {
                if ( frame ) return;
                frame = window.requestAnimationFrame( function () { frame = null; place( false ); } );
            } ).observe( $gallery[ 0 ], { childList: true, subtree: true } );
        }

        place( false );
        sync();
        $( window ).on( 'load', function () { place( true ); sync(); } );
    }

    $( function () {
        var $form = $( 'form.variations_form' ).first();
        if ( $form.length ) setup( $form );
    } );
} )( jQuery );
