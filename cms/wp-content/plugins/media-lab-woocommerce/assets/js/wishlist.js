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
                    refreshItemsIfOnWishlistPage( res.data.items, res.data.grand_total_html );
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
                    renderItems( res.data.items, res.data.grand_total_html );
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
                        renderItems( res.data.items, res.data.grand_total_html );
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

        if ( msg ) { msg.style.display = 'none'; msg.className = 'mlw-wishlist-message'; }

        // Eigene Pflichtfeld-Validierung statt der nativen Browser-Sprechblase
        // (Formular hat "novalidate" - die Browser-eigene Meldung ist weder
        // stylbar noch mehrsprachig pflegbar, da sie aus der Browser-UI-Sprache
        // kommt, nicht aus unserem Projekt-Wording).
        const firstInvalid = Array.from( form.querySelectorAll( '[required]' ) ).find( function ( el ) {
            return el.type === 'checkbox' ? ! el.checked : ! el.value.trim();
        } );
        if ( firstInvalid ) {
            if ( msg ) {
                msg.textContent = mlwWishlist.i18n.formIncomplete || mlwWishlist.i18n.genericError;
                msg.className = 'mlw-wishlist-message mlw-wishlist-message--error';
                msg.style.display = 'block';
            }
            firstInvalid.focus();
            return;
        }

        if ( btn ) btn.disabled = true;

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
                    renderItems( [], '' );
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

    function refreshItemsIfOnWishlistPage( items, grandTotalHtml ) {
        if ( document.querySelector( '.mlw-wishlist-items' ) ) {
            renderItems( items, grandTotalHtml );
        }
    }

    function renderItems( items, grandTotalHtml ) {
        const container = document.querySelector( '.mlw-wishlist-items' );
        if ( ! container ) return;

        const totalEl        = document.querySelector( '.mlw-wishlist-grand-total' );
        const totalAmountEl  = document.querySelector( '.mlw-wishlist-grand-total__amount' );
        const noticeEl       = document.querySelector( '.mlw-wishlist-notice--empty' );
        const backToShopEl   = document.querySelector( '.mlw-wishlist-back-to-shop' );
        const formEl         = document.getElementById( 'mlw-wishlist-form' );
        const submitBtn      = document.querySelector( '.mlw-wishlist-submit' );

        if ( ! items || items.length === 0 ) {
            container.innerHTML = '';
            if ( submitBtn )    submitBtn.disabled = true;
            if ( totalEl )      totalEl.style.display = 'none';
            if ( noticeEl )     noticeEl.style.display = 'block';
            if ( backToShopEl ) backToShopEl.style.display = 'inline-block';
            if ( formEl )       formEl.style.display = 'none';
            return;
        }

        if ( noticeEl )     noticeEl.style.display = 'none';
        if ( backToShopEl ) backToShopEl.style.display = 'none';
        if ( formEl )        formEl.style.display = '';

        container.innerHTML = items.map( renderItemRow ).join( '' );
        if ( submitBtn ) submitBtn.disabled = false;

        // Serverseitig fertig formatierte Summe übernehmen (wc_price()) statt
        // selbst zu berechnen/formatieren - garantiert identische Darstellung
        // zum initialen PHP-Render, unabhängig von WooCommerce-Währungs-
        // einstellungen (Symbol-Position, Dezimaltrennzeichen etc.).
        if ( totalEl && totalAmountEl && grandTotalHtml ) {
            totalAmountEl.innerHTML = grandTotalHtml;
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
        const image = item.image || mlwWishlist.placeholderImage || '';

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

        // unit_price_html/line_total_html kommen bereits fertig formatiert
        // vom Server (wc_price()) - hier NICHT selbst neu formatieren, sonst
        // driftet die Darstellung von der initialen PHP-Ausgabe ab.
        const priceHtml      = ( item.unit_price !== null && item.unit_price !== undefined ) ? item.unit_price_html : '';
        const lineTotalHtml  = ( item.line_total !== null && item.line_total !== undefined ) ? item.line_total_html : '';

        const nameHtml = item.exists && item.permalink
            ? '<a class="mlw-wishlist-item__name" href="' + escapeAttr( item.permalink ) + '">' + name + '</a>'
            : '<span class="mlw-wishlist-item__name mlw-wishlist-item__name--gone">' + name + '</span>';

        const skuHtml = item.sku
            ? '<span class="mlw-wishlist-item__sku">' + escapeHtml( mlwWishlist.i18n.skuLabel || 'Art.-Nr.:' ) + ' ' + escapeHtml( item.sku ) + '</span>'
            : '';

        return (
            '<div class="mlw-wishlist-item" data-item-id="' + escapeAttr( item.item_id ) + '">' +
                '<div class="mlw-wishlist-item__image"><img src="' + escapeAttr( image ) + '" alt="' + escapeAttr( item.name || '' ) + '"></div>' +
                '<div class="mlw-wishlist-item__details">' + nameHtml + skuHtml + configHtml + attachmentsHtml + '</div>' +
                '<div class="mlw-wishlist-item__price">' + priceHtml + '</div>' +
                '<div class="mlw-wishlist-item__quantity"><input type="number" class="mlw-wishlist-item__qty-input" min="1" value="' + escapeAttr( item.quantity ) + '" data-item-id="' + escapeAttr( item.item_id ) + '"></div>' +
                '<div class="mlw-wishlist-item__line-total">' + lineTotalHtml + '</div>' +
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

    function escapeHtml( str ) {
        const div = document.createElement( 'div' );
        div.textContent = str == null ? '' : String( str );
        return div.innerHTML;
    }

    function escapeAttr( str ) {
        return escapeHtml( str ).replace( /"/g, '&quot;' );
    }
})();
