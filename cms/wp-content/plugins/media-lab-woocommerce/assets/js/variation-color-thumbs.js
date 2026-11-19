/**
 * Varianten-Vorschaubilder in der Galerie (siehe inc/variation-color-thumbs.php).
 *
 * Verfügbare Daten aus wp_localize_script: mlwColorThumbs.attribute (z. B. "attribute_pa_color"), .groupLabel,
 * .items (Liste aus value, label, src).
 *
 * Ein Klick wählt die Farbe im Variantenformular und löst das change-Ereignis aus. Alles Weitere (Hauptbild und Galerie
 * der Farbe, Preise, Zurücksetzen) übernehmen WooCommerce und die Funktion "Variantenbilder bei der Farbwahl".
 * Die Vorschaubilder liegen außerhalb der WooCommerce-Galerie (FlexSlider), damit ein Austausch der Galerie sie nicht berührt.
 */
( function ( $ ) {
    'use strict';
    if ( typeof mlwColorThumbs === 'undefined' || ! mlwColorThumbs.items || ! mlwColorThumbs.items.length ) return;

    function setup( $form ) {
        var attribute = mlwColorThumbs.attribute;
        var $gallery  = $( '.woocommerce-product-gallery' ).first();
        if ( ! $gallery.length ) return;

        function field() { return $form.find( '[name="' + attribute + '"]' ).first(); }

        // ── Aufbau ──────────────────────────────────────────────────────────
        var $list = $( '<div class="mlw-vthumbs" role="group"></div>' ).attr( 'aria-label', mlwColorThumbs.groupLabel || '' );
        $.each( mlwColorThumbs.items, function ( i, item ) {
            var $img = $( '<img alt="" loading="lazy" decoding="async" width="100" height="100">' ).attr( 'src', item.src );
            $( '<button type="button" class="mlw-vthumbs__btn" aria-pressed="false"></button>' )
                .attr( { 'data-value': item.value, 'aria-label': item.label, title: item.label } )
                .append( $img )
                .appendTo( $list );
        } );

        /** Unter die Vorschaubilder von WooCommerce setzen (sobald FlexSlider sie aufgebaut hat), sonst ans Ende der Galerie. */
        function place() {
            var $nav = $gallery.find( '.flex-control-nav' ).first();
            if ( $nav.length ) {
                if ( ! $nav.next().is( $list ) ) $nav.after( $list );
            } else if ( ! $list.parent().is( $gallery ) ) {
                $gallery.append( $list );
            }
        }

        // ── Zustand ─────────────────────────────────────────────────────────
        /** Aktive Farbe markieren; Farben, die in der Auswahl nicht (mehr) vorkommen, sperren. */
        function sync() {
            var $field  = field();
            var current = $field.length ? String( $field.val() || '' ) : '';
            var options = {};
            $field.find( 'option' ).each( function () { options[ this.value ] = ! this.disabled; } );
            var checkOptions = $field.is( 'select' );
            $list.children().each( function () {
                var $btn  = $( this );
                var value = String( $btn.attr( 'data-value' ) );
                var usable = ! checkOptions || options[ value ] === true;
                $btn.attr( 'aria-pressed', value === current ? 'true' : 'false' )
                    .prop( 'disabled', ! usable )
                    .toggleClass( 'is-unavailable', ! usable );
            } );
        }

        $list.on( 'click', '.mlw-vthumbs__btn', function () {
            var $field = field();
            if ( ! $field.length ) return;
            $field.val( String( $( this ).attr( 'data-value' ) ) ).trigger( 'change' );
            sync();
        } );

        var timer = null;
        function schedule() { clearTimeout( timer ); timer = setTimeout( sync, 0 ); }
        $form.on( 'change', 'select', schedule );
        $form.on( 'found_variation hide_variation reset_data woocommerce_update_variation_values', schedule );

        place();
        sync();
        $( window ).on( 'load', function () { place(); sync(); } );   // FlexSlider baut seine Vorschaubilder erst danach auf
    }

    $( function () {
        var $form = $( 'form.variations_form' ).first();
        if ( $form.length ) setup( $form );
    } );
} )( jQuery );
