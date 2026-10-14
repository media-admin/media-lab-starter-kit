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
 * Such-Kontext (Produktfilter auf der Suchergebnisseite, search-filters.php):
 *   - die Leiste trägt data-search="<Suchbegriff>" und ein hidden-Feld "search";
 *     der Suchbegriff geht damit bei jedem Request mit (collectFormData)
 *   - Preisspanne gilt für die gefundenen Produkte (loadPriceRange)
 *   - der Suchbegriff bleibt in der URL (?s=...) (updateUrl); Filter-Werte stehen dort
 *     mit Präfix f_ (?s=ring&f_product_cat=...), sonst lädt WordPress ein Kategorie-Archiv
 *   - Filter-Werte aus der URL (Kategorie, Marke, Attribute) werden auf die
 *     Checkboxen übertragen (syncFromUrl)
 *   - Zurücksetzen lädt die ungefilterte Ergebnisseite (resetFilters)
 *   - ein Element mit .js-search-count zeigt die Trefferzahl (data-one / data-many)
 *   - .js-product-count mit data-range: Text der Trefferanzeige aus den
 *     Such-Einstellungen ("%1$s" = erstes, "%2$s" = letztes, "%3$s" = Gesamtzahl)
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
	const SEARCH_COUNT      = '.js-search-count';
	const RESET_BTN         = '.js-filter-reset';
	const MOBILE_BTN        = '.js-filter-toggle-mobile';
	const ACTIVE_COUNT      = '.js-active-filter-count';

	// URL-Parameter, zu denen es keine Filter-Checkboxen gibt (syncFromUrl überspringt sie)
	const NON_FILTER_PARAMS = [ 's', 'price_min', 'price_max', 'orderby', 'paged', 'subcat' ];

	// Such-Kontext: Filter-Parameter mit Präfix (?s=ring&f_product_cat=...). Ohne Präfix würde
	// WordPress ?s=ring&product_cat=... als Kategorie-Archiv laden statt der Suchergebnisseite
	// (siehe mlwf_search_filter_param() in search-filters.php).
	const SEARCH_PARAM_PREFIX = 'f_';

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
		const search   = sidebar.dataset.search   || ''; // Such-Kontext: Spanne der gefundenen Produkte
		try {
			const action = window.mlwf?.actionPriceRange || 'mlwf_get_price_range';
			const res    = await ajaxPost( action, { category, search } );
			priceMin     = Math.floor( res.data.min );
			priceMax     = Math.ceil(  res.data.max );

			// noUiSlider verträgt kein min === max (z. B. alle Treffer gleich teuer)
			if ( priceMax <= priceMin ) priceMax = priceMin + 1;
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
				const fmt     = v => v.toLocaleString( document.documentElement.lang || undefined );

				// Such-Kontext: Text aus den Such-Einstellungen (data-range, "%1$s" "%2$s" "%3$s")
				const template = countEl.dataset.range;
				let text;
				if ( n === 1 ) {
					text = '1 Ergebnis wird angezeigt';
				} else if ( template ) {
					text = template.replace( '%1$s', fmt( start ) ).replace( '%2$s', fmt( end ) ).replace( '%3$s', fmt( n ) );
				} else {
					text = `${ start }–${ end } von ${ n } Ergebnissen werden angezeigt`;
				}

				const p       = document.createElement( 'p' );
				p.className   = 'woocommerce-result-count';
				p.textContent = text;
				countEl.replaceChildren( p );
			}

			// Such-Kontext: Trefferzahl im Seitenkopf ("%s Ergebnisse")
			const searchCount = document.querySelector( SEARCH_COUNT );
			if ( searchCount ) {
				const n   = res.data.found_posts;
				const tpl = n === 1 ? searchCount.dataset.one : searchCount.dataset.many;
				searchCount.textContent = ( tpl || '%s' ).replace( '%s', n.toLocaleString( document.documentElement.lang || undefined ) );
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

			if ( key === 'subcat[]' ) {
				( data.subcat = data.subcat || [] ).push( value );
				continue;
			}

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

	/** Pfad ohne /page/N/ und mit abschließendem Slash. */
	function basePath() {
		return window.location.pathname.replace( /\/page\/\d+\/?$/, '' ).replace( /\/?$/, '/' );
	}

	function updateUrl( data ) {
		const params = new URLSearchParams();

		// Such-Kontext: ohne den Suchbegriff in der URL ginge er beim Neuladen verloren
		if ( data.search ) params.set( 's', data.search );

		if ( data.price_min && data.price_min > priceMin ) params.set( 'price_min', data.price_min );
		if ( data.price_max && data.price_max < priceMax ) params.set( 'price_max', data.price_max );
		if ( data.orderby && data.orderby !== 'menu_order' ) params.set( 'orderby', data.orderby );
		if ( data.subcat && data.subcat.length ) params.set( 'subcat', data.subcat.join( ',' ) );

		const prefix = data.search ? SEARCH_PARAM_PREFIX : '';
		for ( const [ attr, vals ] of Object.entries( data.attributes || {} ) ) {
			if ( vals.length ) params.set( prefix + attr, vals.join( ',' ) );
		}

		// /page/N/ nur bei paged > 1 - sonst stünde nach einem Filterwechsel weiter
		// die alte Seitenzahl in der URL, während Seite 1 angezeigt wird
		const base = basePath();
		const path = data.paged > 1 ? `${ base }page/${ data.paged }/` : base;

		const newUrl = params.toString() ? `${ path }?${ params.toString() }` : path;

		window.history.replaceState( {}, '', newUrl );
	}

	/**
	 * Überträgt Filter-Werte aus der URL auf die Checkboxen (Attribute pa_*,
	 * Kategorie, Marke ...). Läuft über die vorhandenen Checkboxen statt über
	 * Selektoren aus URL-Werten.
	 */
	function syncFromUrl() {
		const params = new URLSearchParams( window.location.search );
		const prefix = document.querySelector( SIDEBAR )?.dataset.search ? SEARCH_PARAM_PREFIX : '';

		// Unterkategorien (Checkboxen name="subcat[]"): Parameter "subcat" ohne Präfix, kommagetrennt
		const subcats = ( params.get( 'subcat' ) || '' ).split( ',' ).filter( Boolean );
		document.querySelectorAll( '.wc-filter-option__checkbox[name="subcat[]"]' ).forEach( cb => {
			cb.checked = subcats.includes( cb.value );
			updateGroupCount( cb.closest( '.wc-filter-group' ) );
		} );

		document.querySelectorAll( '.wc-filter-option__checkbox' ).forEach( cb => {
			const match = cb.name.match( /^attributes\[(.+?)\]\[\]$/ );
			if ( ! match ) return;

			const key = prefix + match[1];
			if ( NON_FILTER_PARAMS.includes( key ) || ! params.has( key ) ) return;

			if ( params.get( key ).split( ',' ).includes( cb.value ) ) {
				cb.checked = true;
				updateGroupCount( cb.closest( '.wc-filter-group' ) );
				// Dropdown geschlossen lassen — nur Button-Zustand aktualisieren
				const toggle = cb.closest( '.wc-filter-group' )?.querySelector( '.wc-filter-group__toggle' );
				if ( toggle ) toggle.setAttribute( 'aria-expanded', 'false' );
			}
		} );
	}

	function updateActiveCount( data ) {
		let count = 0;
		for ( const vals of Object.values( data.attributes || {} ) ) count += vals.length;
		count += ( data.subcat || [] ).length;
		if ( data.price_min > priceMin || data.price_max < priceMax ) count++;

		const el = document.querySelector( ACTIVE_COUNT );
		if ( ! el ) return;
		el.textContent = count;
		el.hidden      = count === 0;
	}

	function hasActiveFilters( data ) {
		const hasAttr  = Object.values( data.attributes || {} ).some( v => v.length > 0 );
		const hasPrice = ( data.price_min > priceMin ) || ( data.price_max < priceMax );
		return hasAttr || hasPrice || ( data.subcat || [] ).length > 0;
	}

	// ── Filter zurücksetzen ───────────────────────────────────────────────────

	function resetFilters() {
		// Such-Kontext: zurück zur ungefilterten Ergebnisseite (gemischte Treffer,
		// Zähler und Pagination stimmen wieder)
		const searchTerm = document.querySelector( SIDEBAR )?.dataset.search;
		if ( searchTerm ) {
			window.location.href = `${ basePath() }?s=${ encodeURIComponent( searchTerm ) }`;
			return;
		}

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
