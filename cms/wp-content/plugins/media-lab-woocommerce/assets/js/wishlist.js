/**
 * Wunschlisten-Frontend-Logik.
 *
 * Verfügbare Daten aus wp_localize_script (siehe inc/wishlist/class-enqueue.php):
 *   mlwWishlist.ajaxUrl, mlwWishlist.nonce, mlwWishlist.count, mlwWishlist.i18n
 */
(function () {
    'use strict';
    if ( typeof mlwWishlist === 'undefined' ) return;

    document.addEventListener( 'DOMContentLoaded', init );

    function init() {
        updateCountBadges( mlwWishlist.count );

        // Add-to-Wishlist-Buttons (Shop-Loop + Einzelproduktseite) + Entfernen-Buttons
        document.addEventListener( 'click', function ( e ) {
            const addBtn = e.target.closest( '.mlw-add-to-wishlist' );
            if ( addBtn ) {
                e.preventDefault();
                handleAdd( addBtn );
                return;
            }

            const removeBtn = e.target.closest( '.mlw-wishlist-item__remove-btn' );
            if ( removeBtn ) {
                e.preventDefault();
                handleRemove( removeBtn.dataset.itemId );
                return;
            }
        } );

        // Mengen-Änderung auf der Wunschlisten-Seite
        document.addEventListener( 'change', function ( e ) {
            const qtyInput = e.target.closest( '.mlw-wishlist-item__qty-input' );
            if ( qtyInput ) {
                handleQtyChange( qtyInput.dataset.itemId, qtyInput.value );
            }
        } );

        // Absende-Formular
        const form = document.getElementById( 'mlw-wishlist-form' );
        if ( form ) {
            form.addEventListener( 'submit', function ( e ) {
                e.preventDefault();
                handleSubmit( form );
            } );
        }
    }

    // ── Add ──────────────────────────────────────────────────────────────────

    function handleAdd( btn ) {
        const productId = btn.dataset.productId;
        if ( ! productId ) return;

        const originalText = btn.textContent;
        btn.disabled = true;

        postAjax( 'mlw_wishlist_add', { product_id: productId, quantity: 1 } )
            .then( function ( res ) {
                if ( res.success ) {
                    updateCountBadges( res.data.count );
                    btn.textContent = '✓';
                    setTimeout( function () {
                        btn.textContent = originalText;
                        btn.disabled = false;
                    }, 1500 );
                    refreshItemsIfOnWishlistPage( res.data.items );
                } else {
                    alert( ( res.data && res.data.message ) || mlwWishlist.i18n.genericError );
                    btn.disabled = false;
                }
            } )
            .catch( function () {
                alert( mlwWishlist.i18n.genericError );
                btn.disabled = false;
            } );
    }

    // ── Remove ───────────────────────────────────────────────────────────────

    function handleRemove( itemId ) {
        if ( ! itemId ) return;
        if ( ! window.confirm( mlwWishlist.i18n.confirmRemove ) ) return;

        postAjax( 'mlw_wishlist_remove', { item_id: itemId } )
            .then( function ( res ) {
                if ( res.success ) {
                    updateCountBadges( res.data.count );
                    renderItems( res.data.items );
                } else {
                    alert( ( res.data && res.data.message ) || mlwWishlist.i18n.genericError );
                }
            } )
            .catch( function () {
                alert( mlwWishlist.i18n.genericError );
            } );
    }

    // ── Menge ändern ─────────────────────────────────────────────────────────

    let qtyDebounce = null;
    function handleQtyChange( itemId, quantity ) {
        if ( ! itemId ) return;
        clearTimeout( qtyDebounce );
        qtyDebounce = setTimeout( function () {
            postAjax( 'mlw_wishlist_update_qty', { item_id: itemId, quantity: quantity } )
                .then( function ( res ) {
                    if ( res.success ) {
                        updateCountBadges( res.data.count );
                        renderItems( res.data.items );
                    } else {
                        alert( ( res.data && res.data.message ) || mlwWishlist.i18n.genericError );
                    }
                } )
                .catch( function () {
                    alert( mlwWishlist.i18n.genericError );
                } );
        }, 400 );
    }

    // ── Absenden ─────────────────────────────────────────────────────────────

    function handleSubmit( form ) {
        const btn = form.querySelector( '.mlw-wishlist-submit' );
        const msg = form.querySelector( '.mlw-wishlist-message' );
        const originalLabel = btn ? btn.textContent : '';

        if ( btn ) btn.disabled = true;
        if ( msg ) { msg.style.display = 'none'; msg.className = 'mlw-wishlist-message'; }

        // Alle Formularfelder generisch einsammeln (Basisfelder + konfigurierte
        // Zusatzfelder + Datenschutz-Checkbox + Honeypot-Felder) statt einzeln
        // aufzuzählen - damit neue, im Backend hinzugefügte Felder automatisch
        // mitgeschickt werden.
        const data = {};
        form.querySelectorAll( 'input[name], textarea[name], select[name]' ).forEach( function ( el ) {
            if ( el.type === 'checkbox' ) {
                data[ el.name ] = el.checked ? '1' : '';
            } else {
                data[ el.name ] = el.value;
            }
        } );

        postAjax( 'mlw_wishlist_submit', data )
            .then( function ( res ) {
                if ( res.success ) {
                    if ( msg ) {
                        msg.textContent = res.data.message || mlwWishlist.i18n.successMessage;
                        msg.className = 'mlw-wishlist-message mlw-wishlist-message--success';
                        msg.style.display = 'block';
                    }
                    form.reset();
                    form.style.display = 'none';
                    updateCountBadges( 0 );
                    renderItems( [] );
                } else {
                    if ( msg ) {
                        msg.textContent = ( res.data && res.data.message ) || mlwWishlist.i18n.genericError;
                        msg.className = 'mlw-wishlist-message mlw-wishlist-message--error';
                        msg.style.display = 'block';
                    }
                    if ( btn ) { btn.disabled = false; btn.textContent = originalLabel; }
                }
            } )
            .catch( function () {
                if ( msg ) {
                    msg.textContent = mlwWishlist.i18n.genericError;
                    msg.className = 'mlw-wishlist-message mlw-wishlist-message--error';
                    msg.style.display = 'block';
                }
                if ( btn ) { btn.disabled = false; btn.textContent = originalLabel; }
            } );
    }

    // ── Rendering ────────────────────────────────────────────────────────────

    function refreshItemsIfOnWishlistPage( items ) {
        if ( document.querySelector( '.mlw-wishlist-items' ) ) {
            renderItems( items );
        }
    }

    function renderItems( items ) {
        const container = document.querySelector( '.mlw-wishlist-items' );
        if ( ! container ) return;

        const totalEl = document.querySelector( '.mlw-wishlist-grand-total' );
        const totalAmountEl = document.querySelector( '.mlw-wishlist-grand-total__amount' );

        if ( ! items || items.length === 0 ) {
            container.innerHTML = '<p class="mlw-wishlist-empty">' + escapeHtml( mlwWishlist.i18n.emptyWishlist ) + '</p>';
            const submitBtn = document.querySelector( '.mlw-wishlist-submit' );
            if ( submitBtn ) submitBtn.disabled = true;
            if ( totalEl ) totalEl.style.display = 'none';
            return;
        }

        container.innerHTML = items.map( renderItemRow ).join( '' );
        const submitBtn = document.querySelector( '.mlw-wishlist-submit' );
        if ( submitBtn ) submitBtn.disabled = false;

        // Gesamtsumme aus den Zeilensummen neu berechnen (keine separate Server-
        // Anfrage nötig, die Werte liegen bereits in den Item-Daten vor).
        if ( totalEl && totalAmountEl ) {
            const grandTotal = items.reduce( function ( sum, item ) {
                return sum + ( item.line_total !== null && item.line_total !== undefined ? parseFloat( item.line_total ) : 0 );
            }, 0 );
            totalAmountEl.textContent = formatPrice( grandTotal );
            totalEl.style.display = '';
        }
    }

    /**
     * Spiegelt templates/wishlist/item-row.php als JS-Template, damit die
     * Liste nach Ajax-Änderungen ohne Reload neu gerendert werden kann.
     * Bei strukturellen Änderungen am PHP-Template bitte hier mitziehen.
     */
    function renderItemRow( item ) {
        const name = escapeHtml( item.name || '' );
        const image = item.image ? item.image : '';

        let configHtml = '';
        if ( item.config_display && typeof item.config_display === 'object' ) {
            const rows = Object.keys( item.config_display ).map( function ( label ) {
                return '<li><span>' + escapeHtml( label ) + ':</span> ' + escapeHtml( item.config_display[ label ] ) + '</li>';
            } ).join( '' );
            if ( rows ) configHtml = '<ul class="mlw-wishlist-item__config">' + rows + '</ul>';
        }

        let attachmentsHtml = '';
        if ( item.attachment_urls && item.attachment_urls.length ) {
            attachmentsHtml = '<div class="mlw-wishlist-item__attachments">' + item.attachment_urls.map( function ( att ) {
                return '<a href="' + escapeAttr( att.url ) + '" target="_blank">📎 ' + escapeHtml( att.filename ) + '</a>';
            } ).join( '' ) + '</div>';
        }

        let priceHtml = '';
        if ( item.unit_price !== null && item.unit_price !== undefined ) {
            priceHtml = '<div class="mlw-wishlist-item__price">' + formatPrice( item.unit_price ) + ' ' + escapeHtml( mlwWishlist.i18n.perUnit || 'pro Stück' );
            if ( item.line_total !== null && item.line_total !== undefined ) {
                priceHtml += ' · <strong>' + formatPrice( item.line_total ) + '</strong> ' + escapeHtml( mlwWishlist.i18n.total || 'gesamt' );
            }
            priceHtml += '</div>';
        }

        const nameHtml = item.exists && item.permalink
            ? '<a class="mlw-wishlist-item__name" href="' + escapeAttr( item.permalink ) + '">' + name + '</a>'
            : '<span class="mlw-wishlist-item__name mlw-wishlist-item__name--gone">' + name + '</span>';

        return (
            '<div class="mlw-wishlist-item" data-item-id="' + escapeAttr( item.item_id ) + '">' +
                '<div class="mlw-wishlist-item__image"><img src="' + escapeAttr( image ) + '" alt="' + escapeAttr( item.name || '' ) + '"></div>' +
                '<div class="mlw-wishlist-item__details">' + nameHtml + configHtml + attachmentsHtml + priceHtml + '</div>' +
                '<div class="mlw-wishlist-item__quantity"><input type="number" class="mlw-wishlist-item__qty-input" min="1" value="' + escapeAttr( item.quantity ) + '" data-item-id="' + escapeAttr( item.item_id ) + '"></div>' +
                '<div class="mlw-wishlist-item__remove"><button type="button" class="mlw-wishlist-item__remove-btn" data-item-id="' + escapeAttr( item.item_id ) + '" aria-label="Entfernen">✕</button></div>' +
            '</div>'
        );
    }

    // ── Helper ───────────────────────────────────────────────────────────────

    function updateCountBadges( count ) {
        mlwWishlist.count = count;
        document.querySelectorAll( '.mlw-wishlist-count' ).forEach( function ( el ) {
            el.textContent = count;
            el.style.display = count > 0 ? '' : 'none';
        } );
    }

    function postAjax( action, data ) {
        const body = new URLSearchParams( Object.assign( { action: action, nonce: mlwWishlist.nonce }, data ) );
        return fetch( mlwWishlist.ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body,
        } ).then( function ( r ) { return r.json(); } );
    }

    function formatPrice( amount ) {
        const num = parseFloat( amount ) || 0;
        return num.toLocaleString( undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 } ) + ' €';
    }

    function escapeHtml( str ) {
        const div = document.createElement( 'div' );
        div.textContent = str == null ? '' : String( str );
        return div.innerHTML;
    }

    function escapeAttr( str ) {
        return escapeHtml( str ).replace( /"/g, '&quot;' );
    }
})();
