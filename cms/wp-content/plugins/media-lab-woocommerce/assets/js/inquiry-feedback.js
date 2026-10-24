/**
 * Einheitliche Rückmeldung nach dem Absenden (alle Anfragewege).
 *
 * Aufruf: window.mlwInquiryFeedback.show( { type, message, anchor } )
 *   type    'inquiry' (Konfigurator, Formulare), 'wishlist' (Wunschzettel), 'wishlistAdded' (Wizard: zur Wunschliste hinzugefügt)
 *   message optional, sonst der Text aus den Einstellungen (mlwFeedback.messages[type])
 *   anchor  Element, das ausgeblendet wird; die Meldung erscheint an seiner Stelle
 *
 * Einstellung "Nach dem Absenden" (Anfragen → Einstellungen): Meldung auf der Seite oder Weiterleitung.
 * Die Weiterleitung gilt nicht für "wishlistAdded", eine Danke-Seite nach dem Hinzufügen wäre sinnlos.
 */
( function () {
    'use strict';

    var cfg = window.mlwFeedback || {};

    function link( href, text, cls ) {
        var a = document.createElement( 'a' );
        a.href = href;
        a.className = 'mlw-feedback__link' + ( cls ? ' ' + cls : '' );
        a.textContent = text;
        return a;
    }

    function hideAll( selector ) {
        document.querySelectorAll( selector ).forEach( function ( node ) {
            node.style.display = 'none';
        } );
    }

    function show( opts ) {
        opts = opts || {};
        var type = opts.type || 'inquiry';

        if ( type !== 'wishlistAdded' && cfg.mode === 'redirect' && cfg.redirectUrl ) {
            window.location.href = cfg.redirectUrl;
            return;
        }

        var message = opts.message || ( cfg.messages && cfg.messages[ type ] ) || '';
        var i18n = cfg.i18n || {};

        var box = document.createElement( 'div' );
        box.className = 'mlw-feedback mlw-feedback--' + type;
        box.setAttribute( 'role', 'status' );
        box.setAttribute( 'tabindex', '-1' );

        var text = document.createElement( 'p' );
        text.className = 'mlw-feedback__text';
        text.textContent = message;
        box.appendChild( text );

        var actions = document.createElement( 'div' );
        actions.className = 'mlw-feedback__actions';
        if ( type === 'wishlistAdded' && cfg.wishlistUrl ) {
            actions.appendChild( link( cfg.wishlistUrl, i18n.toWishlist || 'Zur Wunschliste' ) );
        }
        if ( cfg.shopUrl ) {
            actions.appendChild( link( cfg.shopUrl, i18n.continueShopping || 'Weiter einkaufen' ) );
        }
        if ( actions.children.length ) box.appendChild( actions );

        var anchor = opts.anchor || null;
        if ( anchor && anchor.parentNode ) {
            anchor.style.display = 'none';
            anchor.parentNode.insertBefore( box, anchor );
        } else {
            var host = document.querySelector( 'main' ) || document.body;
            host.insertBefore( box, host.firstChild );
        }

        // Wunschzettel: Leerzustand und "Zurück zum Shop" ersetzt unser Baustein
        if ( type === 'wishlist' ) {
            hideAll( '.mlw-wishlist-notice--empty' );
            hideAll( '.mlw-wishlist-back-to-shop' );
        }

        if ( typeof box.scrollIntoView === 'function' ) box.scrollIntoView( { behavior: 'smooth', block: 'center' } );
        if ( typeof box.focus === 'function' ) box.focus( { preventScroll: true } );
    }

    window.mlwInquiryFeedback = { show: show };
} )();
