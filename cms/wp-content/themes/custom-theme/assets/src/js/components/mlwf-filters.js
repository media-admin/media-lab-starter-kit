import noUiSlider from 'nouislider';

/**
 * WooCommerce Produktfilter
 *
 * - Preis-Slider (noUISlider)
 * - Checkbox-Filter per Attribut
 * - AJAX-Produktabfrage
 * - URL-State (querystring) für Sharing/Navigation
 * - Mobile Filter-Toggle
 * - Collapsible Filter-Gruppen
 *
 * Portiert & generalisiert aus dem Janecka-Projekt (at.janecka-2026) für den
 * Media Lab Starter Kit - Action-Namen und Nonce werden jetzt dynamisch aus
 * window.mlwf gelesen (siehe inc/filters/setup.php), statt fest auf
 * 'janecka_*' zu verweisen, damit das Script auch in Projekten funktioniert,
 * die die Janecka-Rückwärtskompatibilitäts-Aliase nicht (mehr) haben.
 *
 * @package MedialabWooFilters
 */

( function () {
	'use strict';

	// ── Selektoren ────────────────────────────────────────────────────────────

	const SIDEBAR           = '#wc-filter-sidebar';
	const FORM              = '.js-filter-form';
	const PRODUCTS_CONTAINER = '.wc-products-container'; // Wrapper um ul.products + Pagination
	const COUNT_LABEL       = '.js-product-count';
	const RESET_BTN         = '.js-filter-reset';
	const MOBILE_BTN        = '.js-filter-toggle-mobile';
	const ACTIVE_COUNT      = '.js-active-filter-count';

	// ── State ─────────────────────────────────────────────────────────────────

	let priceSlider   = null;
	let priceMin      = 0;
	let priceMax      = 10000;
	let debounceTimer = null;

	// ── Init ──────────────────────────────────────────────────────────────────

	function init() {
		const sidebar = document.querySelector( SIDEBAR );
		if ( ! sidebar ) return;

		initCollapsibleGroups();
		initMobileToggle( sidebar );
		syncFromUrl();
		bindFilterEvents( sidebar );
		loadPriceRange( sidebar );
		bindPaginationEvents();
	}

	// ── Collapsible Filter-Gruppen ────────────────────────────────────────────

	function initCollapsibleGroups() {
		document.querySelectorAll( '.wc-filter-group__toggle' ).forEach( btn => {
			btn.addEventListener( 'click', () => {
				const expanded = btn.getAttribute( 'aria-expanded' ) === 'true';
				const body     = document.getElementById( btn.getAttribute( 'aria-controls' ) )
							  || btn.closest( '.wc-filter-group' )?.querySelector( '.wc-filter-group__dropdown' );

				btn.setAttribute( 'aria-expanded', String( ! expanded ) );

				if ( body ) {
					if ( expanded ) {
						body.classList.remove( 'is-open' );
						setTimeout( () => { body.hidden = true; }, 200 );
					} else {
						body.hidden = false;
						requestAnimationFrame( () => body.classList.add( 'is-open' ) );

						// Preis-Slider lazy initialisieren
						if ( ! priceSlider ) {
							const group = btn.closest( '.wc-filter-group--price' );
							if ( group ) {
								const sidebar = document.querySelector( SIDEBAR );
								loadPriceRange( sidebar ).then( () => {
									initPriceSlider( sidebar );
								} );
							}
						}
					}
				}

				// Andere offene Dropdowns schließen
				document.querySelectorAll( '.wc-filter-group__dropdown.is-open' ).forEach( open => {
					if ( open !== body ) {
						open.classList.remove( 'is-open' );
						setTimeout( () => { open.hidden = true; }, 200 );
						open.closest( '.wc-filter-group' )
							?.querySelector( '.wc-filter-group__toggle' )
							?.setAttribute( 'aria-expanded', 'false' );
					}
				} );
			} );
		} );

		// Klick außerhalb schließt alle Dropdowns
		document.addEventListener( 'click', e => {
			if ( ! e.target.closest( '.wc-filter-group' ) ) {
				document.querySelectorAll( '.wc-filter-group__dropdown.is-open' ).forEach( open => {
					open.classList.remove( 'is-open' );
					setTimeout( () => { open.hidden = true; }, 200 );
					open.closest( '.wc-filter-group' )
						?.querySelector( '.wc-filter-group__toggle' )
						?.setAttribute( 'aria-expanded', 'false' );
				} );
			}
		} );
	}

	// ── Mobile Toggle ─────────────────────────────────────────────────────────

	function initMobileToggle( sidebar ) {
		const btn = document.querySelector( MOBILE_BTN );
		if ( ! btn ) return;

		btn.addEventListener( 'click', () => {
			const open = sidebar.classList.toggle( 'is-open' );
			btn.setAttribute( 'aria-expanded', String( open ) );
		} );

		document.addEventListener( 'click', e => {
			if ( ! sidebar.contains( e.target ) && ! btn.contains( e.target ) ) {
				sidebar.classList.remove( 'is-open' );
				btn.setAttribute( 'aria-expanded', 'false' );
			}
		} );
	}

	// ── Preis-Bereich laden ───────────────────────────────────────────────────

	async function loadPriceRange( sidebar ) {
		const category = sidebar.dataset.category || '';
		try {
			const action = window.mlwf?.actionPriceRange || 'mlwf_get_price_range';
			const res    = await ajaxPost( action, { category } );
			priceMin     = Math.floor( res.data.min );
			priceMax     = Math.ceil(  res.data.max );
		} catch ( e ) {
			console.warn( '[MLWF] Preis-Bereich konnte nicht geladen werden.', e );
		}
	}

	// ── Preis-Slider ─────────────────────────────────────────────────────────

	function initPriceSlider( sidebar ) {
		const sliderEl = sidebar.querySelector( '.js-price-slider' );
		const inputMin = sidebar.querySelector( '.js-price-min' );
		const inputMax = sidebar.querySelector( '.js-price-max' );

		if ( ! sliderEl || typeof noUiSlider === 'undefined' ) return;

		const urlParams = new URLSearchParams( window.location.search );
		const startMin  = parseInt( urlParams.get( 'price_min' ) ) || priceMin;
		const startMax  = parseInt( urlParams.get( 'price_max' ) ) || priceMax;

		priceSlider = noUiSlider.create( sliderEl, {
			start:   [ startMin, startMax ],
			connect: true,
			range:   { min: priceMin, max: priceMax },
			step:    1,
			format:  {
				to:   v => Math.round( v ),
				from: v => parseInt( v ),
			},
		} );

		priceSlider.on( 'update', ( values ) => {
			if ( inputMin ) inputMin.value = values[0];
			if ( inputMax ) inputMax.value = values[1];
		} );

		priceSlider.on( 'change', () => triggerFilter() );

		[ inputMin, inputMax ].forEach( ( input, idx ) => {
			if ( ! input ) return;
			input.addEventListener( 'change', () => {
				const vals = priceSlider.get();
				vals[ idx ] = parseInt( input.value );
				priceSlider.set( vals );
				triggerFilter();
			} );
		} );

		if ( inputMin ) inputMin.min = priceMin;
		if ( inputMax ) inputMax.max = priceMax;
	}

	// ── Filter-Events ─────────────────────────────────────────────────────────

	function bindFilterEvents( sidebar ) {
		const form = sidebar.querySelector( FORM );
		if ( ! form ) return;

		form.querySelectorAll( '.wc-filter-option__checkbox' ).forEach( cb => {
			cb.addEventListener( 'change', () => {
				updateGroupCount( cb.closest( '.wc-filter-group' ) );
				triggerFilter();
			} );
		} );

		const resetBtn = sidebar.querySelector( RESET_BTN );
		if ( resetBtn ) {
			resetBtn.addEventListener( 'click', resetFilters );
		}

		const orderby = document.querySelector( 'select.orderby' );
		if ( orderby ) {
			orderby.addEventListener( 'change', triggerFilter );
		}

		sidebar.querySelector( '.js-filter-apply-mobile' )?.addEventListener( 'click', () => {
			sidebar.classList.remove( 'is-open' );
			triggerFilter();
		} );
	}

	function updateGroupCount( group ) {
		if ( ! group ) return;
		const checked = group.querySelectorAll( '.wc-filter-option__checkbox:checked' ).length;
		const toggle  = group.querySelector( '.wc-filter-group__toggle' );
		let countEl   = group.querySelector( '.wc-filter-group__count' );

		if ( checked > 0 ) {
			if ( ! countEl ) {
				countEl = document.createElement( 'span' );
				countEl.className = 'wc-filter-group__count';
				toggle?.insertBefore( countEl, group.querySelector( '.wc-filter-group__icon' ) );
			}
			countEl.textContent = checked;
		} else {
			countEl?.remove();
		}
	}

	// ── Filter auslösen (debounced) ───────────────────────────────────────────

	function triggerFilter() {
		clearTimeout( debounceTimer );
		debounceTimer = setTimeout( fetchProducts, 350 );
	}

	// ── Produkte via AJAX laden ───────────────────────────────────────────────

	async function fetchProducts( paged = 1 ) {
		const sidebar  = document.querySelector( SIDEBAR );
		const form     = sidebar?.querySelector( FORM );
		if ( ! form ) return;

		const container = document.querySelector( PRODUCTS_CONTAINER );
		const countEl   = document.querySelector( COUNT_LABEL );

		// Lade-Zustand
		if ( container ) container.classList.add( 'wc-products-container--loading' );

		const formData = collectFormData( form, paged );
		updateUrl( formData );
		updateActiveCount( formData );

		// Reset-Button zeigen/verbergen
		const resetBtn = sidebar.querySelector( RESET_BTN );
		if ( resetBtn ) resetBtn.hidden = ! hasActiveFilters( formData );

		try {
			const action = window.mlwf?.actionFilter || 'mlwf_filter_products';
			const res    = await ajaxPost( action, formData );

			// Container direkt ersetzen — einfachste und zuverlässigste Methode
			if ( container ) {
				container.innerHTML = res.data.html;
				container.classList.remove( 'wc-products-container--loading' );
			}

			// Ergebnis-Anzahl aktualisieren
			if ( countEl ) {
				const n       = res.data.found_posts;
				const current = res.data.current  || 1;
				const perPage = res.data.per_page || 12;
				const start   = n === 0 ? 0 : ( current - 1 ) * perPage + 1;
				const end     = Math.min( current * perPage, n );
				const text    = n === 1
					? '1 Ergebnis wird angezeigt'
					: `${ start }–${ end } von ${ n } Ergebnissen werden angezeigt`;
				countEl.innerHTML = `<p class="woocommerce-result-count">${ text }</p>`;
			}

		} catch ( e ) {
			console.error( '[MLWF] Filter-Fehler:', e );
			if ( container ) container.classList.remove( 'wc-products-container--loading' );
		}
	}

	// ── Formulardaten sammeln ─────────────────────────────────────────────────

	function collectFormData( form, paged = 1 ) {
		const data       = { paged };
		const formData   = new FormData( form );
		const attributes = {};

		for ( const [ key, value ] of formData.entries() ) {
			// WooCommerce's woocommerce_catalog_ordering() rendert ein eigenes,
			// verschachteltes <form> mit einem hidden "paged"-Feld (Wert immer "1").
			// Da HTML kein Nesting von <form>-Elementen erlaubt, "flacht" der Browser
			// dieses verschachtelte Formular in unser äußeres .js-filter-form ab,
			// wodurch new FormData(form) dieses Fremd-Feld mit einliest und unseren
			// korrekten paged-Parameter überschreiben würde. Daher hier ignorieren —
			// der übergebene paged-Parameter hat immer Vorrang.
			if ( key === 'paged' ) continue;

			const attrMatch = key.match( /^attributes\[(.+?)\]\[\]$/ );
			if ( attrMatch ) {
				const attr = attrMatch[1];
				if ( ! attributes[ attr ] ) attributes[ attr ] = [];
				attributes[ attr ].push( value );
			} else {
				data[ key ] = value;
			}
		}

		if ( priceSlider ) {
			const [ min, max ] = priceSlider.get();
			data.price_min = min;
			data.price_max = max;
		}

		data.attributes = attributes;

		const orderby = document.querySelector( 'select.orderby' );
		if ( orderby ) data.orderby = orderby.value;

		return data;
	}

	// ── URL-State ────────────────────────────────────────────────────────────

	function updateUrl( data ) {
		const params = new URLSearchParams();

		if ( data.price_min && data.price_min > priceMin ) params.set( 'price_min', data.price_min );
		if ( data.price_max && data.price_max < priceMax ) params.set( 'price_max', data.price_max );
		if ( data.orderby && data.orderby !== 'menu_order' ) params.set( 'orderby', data.orderby );

		for ( const [ attr, vals ] of Object.entries( data.attributes || {} ) ) {
			if ( vals.length ) params.set( attr, vals.join( ',' ) );
		}

		const newUrl = params.toString()
			? `${ window.location.pathname }?${ params.toString() }`
			: window.location.pathname;

		window.history.replaceState( {}, '', newUrl );
	}

	function syncFromUrl() {
		const params = new URLSearchParams( window.location.search );

		params.forEach( ( val, key ) => {
			if ( ! key.startsWith( 'pa_' ) ) return;
			val.split( ',' ).forEach( slug => {
				const cb = document.querySelector( `input[name="attributes[${ key }][]"][value="${ slug }"]` );
				if ( cb ) {
					cb.checked = true;
					updateGroupCount( cb.closest( '.wc-filter-group' ) );
					// Dropdown geschlossen lassen — nur Button-Zustand aktualisieren
					const toggle = cb.closest( '.wc-filter-group' )?.querySelector( '.wc-filter-group__toggle' );
					if ( toggle ) toggle.setAttribute( 'aria-expanded', 'false' );
				}
			} );
		} );
	}

	function updateActiveCount( data ) {
		let count = 0;
		for ( const vals of Object.values( data.attributes || {} ) ) count += vals.length;
		if ( data.price_min > priceMin || data.price_max < priceMax ) count++;

		const el = document.querySelector( ACTIVE_COUNT );
		if ( ! el ) return;
		el.textContent = count;
		el.hidden      = count === 0;
	}

	function hasActiveFilters( data ) {
		const hasAttr  = Object.values( data.attributes || {} ).some( v => v.length > 0 );
		const hasPrice = ( data.price_min > priceMin ) || ( data.price_max < priceMax );
		return hasAttr || hasPrice;
	}

	// ── Filter zurücksetzen ───────────────────────────────────────────────────

	function resetFilters() {
		document.querySelectorAll( '.wc-filter-option__checkbox:checked' ).forEach( cb => {
			cb.checked = false;
			updateGroupCount( cb.closest( '.wc-filter-group' ) );
		} );

		if ( priceSlider ) priceSlider.set( [ priceMin, priceMax ] );

		triggerFilter();
	}

	// ── AJAX Helfer ───────────────────────────────────────────────────────────

	function ajaxPost( action, data ) {
		const body = new URLSearchParams();
		body.set( 'action', action );
		body.set( 'nonce',  window.mlwf?.nonce || '' );

		flattenToUrlParams( data, body );

		return fetch( window.mlwf?.ajaxUrl || '/wp-admin/admin-ajax.php', {
			method:  'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body:    body.toString(),
		} )
			.then( r => r.json() )
			.then( json => {
				if ( ! json.success ) throw new Error( json.data || 'Unknown error' );
				return json;
			} );
	}

	function flattenToUrlParams( obj, params, prefix = '' ) {
		for ( const [ key, val ] of Object.entries( obj ) ) {
			const fullKey = prefix ? `${ prefix }[${ key }]` : key;
			if ( Array.isArray( val ) ) {
				val.forEach( v => params.append( `${ fullKey }[]`, v ) );
			} else if ( val !== null && typeof val === 'object' ) {
				flattenToUrlParams( val, params, fullKey );
			} else {
				params.set( fullKey, val );
			}
		}
	}


	// ── Pagination-Klicks abfangen ────────────────────────────────────────────
	function bindPaginationEvents() {
		document.addEventListener( 'click', async e => {
			const link = e.target.closest( '.woocommerce-pagination a' );
			if ( ! link ) return;
			e.preventDefault();
			// Seitennummer aus URL extrahieren
			const url   = new URL( link.href );
			const match = url.pathname.match( /\/page\/(\d+)\// );
			const paged = match ? parseInt( match[1] ) : 1;
			// Aktuelle Filter-Parameter in URL beibehalten
			const currentParams = new URLSearchParams( window.location.search );
			const newParams     = new URLSearchParams( url.search );
			// Query-Parameter aus aktueller URL übernehmen
			currentParams.forEach( ( val, key ) => {
				if ( ! newParams.has( key ) ) newParams.set( key, val );
			} );
			const newUrl = url.pathname + ( newParams.toString() ? '?' + newParams.toString() : '' );
			window.history.replaceState( {}, '', newUrl );

			await fetchProducts( paged );

			// Zum Grid scrollen statt zur Seitenoberkante
			const container = document.querySelector( PRODUCTS_CONTAINER );
			if ( container ) {
				const headerHeight = document.querySelector( '.site-header' )?.offsetHeight || 100;
				const targetTop    = container.getBoundingClientRect().top + window.scrollY - headerHeight - 16;
				window.scrollTo( { top: targetTop, behavior: 'smooth' } );
			}
		} );
	}


	// ── Start ─────────────────────────────────────────────────────────────────

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

} )();
