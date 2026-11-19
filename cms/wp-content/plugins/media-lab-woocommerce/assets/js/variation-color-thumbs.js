/**
 * Varianten-Vorschaubilder in der Galerie (siehe inc/variation-color-thumbs.php).
 *
 * Verfügbare Daten aus wp_localize_script: mlwColorThumbs.attribute (z. B. "attribute_pa_color"), .groupLabel,
 * .items (Liste aus value, label, src, parent).
 *
 * Die Vorschaubilder der Farben stehen als weitere Einträge in der Vorschaubild-Leiste der WooCommerce-Galerie
 * (.flex-control-thumbs), also im selben Container wie die Bilder des Produkts. Gezeigt werden die ANDEREN Farben:
 * die gewählte Farbe entfällt, weil die Galerie ihre Bilder schon zeigt. Ist keine Farbe gewählt, entfällt die Farbe,
 * deren Bild das Produkt ohnehin als Hauptbild zeigt (parent). Das gilt für die Galerie des Produkts ebenso wie für die
 * Galerie einer gewählten Farbe (Variante).
 *
 * Ein Klick wählt die Farbe im Variantenformular und löst das change-Ereignis aus. Alles Weitere (Hauptbild und Galerie
 * der Farbe, Preise, Zurücksetzen) übernehmen WooCommerce und die Funktion "Variantenbilder bei der Farbwahl".
 *
 * Bei der Wahl einer Farbe tauscht WooCommerce die Galerie aus (ab 10.9 mit eigener Galerie je Variante). Ob dabei nur der
 * Inhalt oder das ganze Galerie-Element ersetzt wird, hängt von Version und Theme ab. Deshalb hängt hier nichts an der
 * Galerie selbst: Beobachtung und Klick laufen über das umgebende Produkt (div.product), die Galerie wird bei jedem Zugriff
 * neu gesucht, die Einträge werden bei Bedarf frisch gebaut und wieder eingesetzt.
 */
( function ( $ ) {
    'use strict';
    if ( typeof mlwColorThumbs === 'undefined' || ! mlwColorThumbs.items || ! mlwColorThumbs.items.length ) return;

    var GALLERY = '.woocommerce-product-gallery';
    var OWN_NAV = 'mlw-vthumbs-nav';   // Leiste, die dieses Skript selbst anlegt, wenn WooCommerce keine aufbaut

    function setup( $form ) {
        var attribute = mlwColorThumbs.attribute;
        var items     = mlwColorThumbs.items;
        var $root     = $form.closest( 'div.product' );
        if ( ! $root.length ) $root = $( document.body );
        if ( ! $root.find( GALLERY ).length ) return;

        function field() { return $form.find( '[name="' + attribute + '"]' ).first(); }
        function $gallery() { return $root.find( GALLERY ).first(); }

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
         * Einträge in die Vorschaubild-Leiste der aktuellen Galerie setzen, wenn sie dort fehlen. Gibt es keine Leiste
         * (zum Beispiel bei nur einem Bild) und ist force gesetzt, legt das Skript eine an, mit den Klassen von FlexSlider.
         * Baut WooCommerce später doch eine auf, räumt das Skript die eigene wieder weg.
         */
        function place( force ) {
            var $g = $gallery();
            if ( ! $g.length ) return;
            var $own = $g.find( '.' + OWN_NAV );
            var $nav = $g.find( '.flex-control-nav' ).not( $own ).first();
            if ( $nav.length ) {
                $own.remove();
            } else if ( $own.length ) {
                $nav = $own.first();
            } else if ( force ) {
                $nav = $( '<ol class="flex-control-nav flex-control-thumbs ' + OWN_NAV + '"></ol>' ).appendTo( $g );
            } else {
                return;
            }
            if ( $nav.children( '.mlw-vthumb' ).length === items.length ) return;
            $g.find( '.mlw-vthumb' ).remove();
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
            $gallery().find( '.mlw-vthumb' ).each( function () {
                var $li    = $( this );
                var value  = String( $li.attr( 'data-value' ) );
                var hide   = current !== '' ? value === current : $li.attr( 'data-parent' ) === '1';
                var usable = ! checkOptions || options[ value ] === true;
                $li.toggleClass( 'is-hidden', hide ).toggleClass( 'is-unavailable', ! usable );
                $li.children( 'button' ).prop( 'disabled', ! usable );
            } );
        }

        $root.on( 'click', '.mlw-vthumb__btn', function () {
            var $field = field();
            if ( ! $field.length ) return;
            $field.val( String( $( this ).closest( '.mlw-vthumb' ).attr( 'data-value' ) ) ).trigger( 'change' );
            sync();
        } );

        // Nach einem Austausch der Galerie: Einträge prüfen, danach (nach dem Aufbau durch FlexSlider) notfalls Leiste anlegen
        var timer = null;
        var late  = null;
        function schedule() {
            clearTimeout( timer );
            timer = setTimeout( function () { place( false ); sync(); }, 0 );
            clearTimeout( late );
            late = setTimeout( function () { place( true ); sync(); }, 400 );
        }
        $form.on( 'change', 'select', schedule );
        $form.on( 'found_variation show_variation hide_variation reset_data reset_image woocommerce_update_variation_values', schedule );

        // Leiste kommt später oder wird ersetzt: Einträge wieder einsetzen (beobachtet das Produkt, nicht die Galerie)
        if ( typeof MutationObserver === 'function' ) {
            var frame = null;
            new MutationObserver( function () {
                if ( frame ) return;
                frame = window.requestAnimationFrame( function () { frame = null; place( false ); } );
            } ).observe( $root[ 0 ], { childList: true, subtree: true } );
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
