/**
 * Variantenbilder bei der Farbwahl (siehe inc/variation-color-images.php).
 *
 * Verfügbare Daten aus wp_localize_script: mlwColorImages.ajaxUrl, mlwColorImages.attributes (z. B. ["attribute_pa_color"])
 *
 * Ist nur die Farbe gewählt (keine vollständige Variante), werden Hauptbild und Galerie der ersten Variante dieser Farbe
 * angezeigt. Mit vollständiger Variante bestimmt WooCommerce das Bild wie gewohnt, ohne Farbe setzt WooCommerce zurück.
 */
( function ( $ ) {
    'use strict';
    if ( typeof mlwColorImages === 'undefined' || typeof $.fn.wc_variations_image_update !== 'function' ) return;

    var cache   = {};   // Schlüssel -> Bilddaten (oder null: keine Variante)
    var pending = {};   // Schlüssel -> wartende Funktionen, solange der Aufruf läuft

    function setup( $form ) {
        var attributes = mlwColorImages.attributes || [];
        var productId  = $form.data( 'product_id' );
        var token      = 0;
        if ( ! attributes.length || ! productId ) return;

        /** Gewählte Farbe: { attr, value } des ersten Farbfelds mit Auswahl, sonst null. */
        function selectedColor() {
            for ( var i = 0; i < attributes.length; i++ ) {
                var field = $form.find( '[name="' + attributes[ i ] + '"]' ).first();
                if ( field.length && field.val() ) return { attr: attributes[ i ], value: String( field.val() ) };
            }
            return null;
        }

        /** Ist eine vollständige Variante gewählt? Dann bestimmt WooCommerce das Bild. */
        function variationChosen() {
            return ( parseInt( $form.find( 'input.variation_id, input[name="variation_id"]' ).first().val(), 10 ) || 0 ) > 0;
        }

        /** Variantendaten in der Seite (WooCommerce legt sie bis zur Schwelle von 30 Varianten dort ab). */
        function fromInline( color ) {
            var list = $form.data( 'product_variations' );
            if ( ! $.isArray( list ) ) return null;
            for ( var i = 0; i < list.length; i++ ) {
                var v = list[ i ];
                if ( v && v.attributes && v.attributes[ color.attr ] === color.value && ( ( v.image && v.image.src ) || v.gallery_images_html ) ) return v;
            }
            return null;
        }

        function fetchColor( color, done ) {
            var key = productId + '|' + color.attr + '|' + color.value;
            if ( key in cache ) { done( cache[ key ] ); return; }
            // Läuft für diese Farbe schon ein Aufruf (WooCommerce löst bei einer Farbwahl mehrere Ereignisse aus),
            // nur die Antwort abwarten statt einen weiteren Aufruf zu starten.
            if ( pending[ key ] ) { pending[ key ].push( done ); return; }
            pending[ key ] = [ done ];
            var finish = function ( data ) {
                var waiting = pending[ key ] || [];
                delete pending[ key ];
                for ( var i = 0; i < waiting.length; i++ ) waiting[ i ]( data );
            };
            $.ajax( {
                url: mlwColorImages.ajaxUrl,
                data: { action: 'mlw_variation_color_image', product_id: productId, attribute: color.attr, value: color.value },
                dataType: 'json',
            } ).done( function ( res ) {
                cache[ key ] = res && res.success ? res.data : null;
                finish( cache[ key ] );
            } ).fail( function () { finish( null ); } );   // nicht merken: ein späterer Versuch darf es erneut probieren
        }

        function sync() {
            var color = selectedColor();
            if ( ! color || variationChosen() ) return;
            var mine  = ++token;
            var apply = function ( data ) {
                // veraltete Antwort (inzwischen andere Farbe) oder inzwischen vollständige Variante: nichts tun
                if ( mine !== token || ! data || variationChosen() ) return;
                $form.wc_variations_image_update( data );
            };
            var inline = fromInline( color );
            if ( inline ) { apply( inline ); return; }
            fetchColor( color, apply );
        }

        // Nach den Handlern von WooCommerce ausführen (setzt WooCommerce zurück, gilt danach wieder die Farbe).
        // Mehrere Ereignisse einer Farbwahl ergeben nur einen Abgleich.
        var timer = null;
        function schedule() { clearTimeout( timer ); timer = setTimeout( sync, 0 ); }

        $form.on( 'change', 'select', schedule );
        $form.on( 'found_variation hide_variation reset_data reset_image', schedule );
        schedule();   // Vorauswahl der Seite
    }

    $( function () {
        $( 'form.variations_form' ).each( function () { setup( $( this ) ); } );
    } );
} )( jQuery );