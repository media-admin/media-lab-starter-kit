<?php
/**
 * Produktfilter auf der Suchergebnisseite
 *
 * Die Facetten (Kategorie, Marke, alle Produktattribute) kommen NICHT aus der
 * globalen Filter-Konfiguration, sondern werden aus der Treffermenge der Suche
 * berechnet: angezeigt werden nur Werte, die in mindestens einem gefundenen
 * Produkt vorkommen - mit Anzahl. Dazu ein Preis-Filter (Slider) mit der
 * Preisspanne der gefundenen Produkte, eine Sortierung und die Trefferanzeige
 * ("Ergebnisse 1–24 von 139 werden angezeigt").
 *
 * Bausteine:
 *   - mlwf_search_product_ids()        Treffer-IDs (Core: MediaLab_Serp_Search::matching_ids)
 *   - mlwf_search_facets()             eine gruppierte SQL-Abfrage -> Werte + Anzahlen
 *   - mlwf_get_search_filter_bar()     Markup, identisch zur Shop-Filterleiste
 *                                      (gleiche Klassen -> gleiches CSS und JS), inklusive
 *                                      Trefferanzeige in .wc-loop-header wie im Shop
 *   - mlwf_search_apply_request_filters()  Filter aus der URL (?f_product_cat=a,b&f_pa_x=y&price_min=...)
 *                                      auf die Haupt-Abfrage der Suche anwenden:
 *                                      geteilte Links, Neuladen und Pagination funktionieren.
 *                                      Das Präfix f_ verhindert, dass WordPress die Anfrage als
 *                                      Kategorie-/Marken-Archiv interpretiert (siehe
 *                                      mlwf_search_filter_param())
 *   - AJAX: ajax-handlers.php akzeptiert den Parameter "search" (post__in = Treffer-IDs,
 *     Preisspanne der Treffer)
 *
 * Trefferanzeige: Text aus den Such-Einstellungen (Core, Feld "serp_range"), sonst Standardtext.
 * Platzhalter: %1$s = erstes, %2$s = letztes angezeigtes Ergebnis, %3$s = Gesamtzahl. Das
 * Filter-JS aktualisiert den Text nach dem Filtern (.js-product-count, data-range).
 *
 * Hooks zum Anpassen:
 *   mlwf_search_facet_taxonomies   (array)  Taxonomien, die als Filter erscheinen
 *   mlwf_search_facet_min_terms    (int)    Mindestanzahl Werte je Filter (Standard 2)
 *   mlwf_search_facets             (array)  fertige Facetten (z. B. Reihenfolge, Ausblenden)
 *   mlwf_search_filter_min_products(int)    ab wie vielen Treffern die Leiste erscheint (2)
 *   mlwf_search_sort_options       (array)  Sortier-Auswahl
 *
 * Datei ablegen unter: inc/filters/search-filters.php
 * Einbindung: require_once am Ende von inc/filters/ajax-handlers.php.
 *
 * @package MedialabWooFilters
 */

defined( 'ABSPATH' ) || exit;

// ---------------------------------------------------------------------------
// Treffermenge
// ---------------------------------------------------------------------------

/**
 * Produkt-IDs der Suche (Relevanz-Reihenfolge). Leer, wenn der Core fehlt.
 *
 * @return int[]
 */
function mlwf_search_product_ids( string $search ): array {
	if ( ! class_exists( 'MediaLab_Serp_Search' ) || ! method_exists( 'MediaLab_Serp_Search', 'matching_ids' ) ) {
		return [];
	}

	return MediaLab_Serp_Search::matching_ids( $search, [ 'product' ] );
}

// ---------------------------------------------------------------------------
// Facetten
// ---------------------------------------------------------------------------

/**
 * Taxonomien, die als Filter in Frage kommen: Kategorie, Marke, alle Attribute.
 *
 * @return string[]
 */
function mlwf_search_facet_taxonomies(): array {
	$taxonomies = [ 'product_cat' ];

	if ( taxonomy_exists( 'product_brand' ) ) {
		$taxonomies[] = 'product_brand';
	}

	if ( function_exists( 'wc_get_attribute_taxonomies' ) ) {
		foreach ( wc_get_attribute_taxonomies() as $attribute ) {
			$taxonomies[] = wc_attribute_taxonomy_name( $attribute->attribute_name );
		}
	}

	return array_values( array_unique( (array) apply_filters( 'mlwf_search_facet_taxonomies', $taxonomies ) ) );
}

/**
 * Anzeigename eines Filters.
 */
function mlwf_search_facet_label( string $taxonomy, array $labels ): string {
	if ( ! empty( $labels[ $taxonomy ] ) ) {
		return (string) $labels[ $taxonomy ];
	}

	if ( $taxonomy === 'product_cat' ) {
		$label = class_exists( 'MediaLab_Filter_Settings' ) ? (string) MediaLab_Filter_Settings::label( 'category' ) : '';
		return $label !== '' ? $label : __( 'Kategorie', 'medialab-woo-filters' );
	}

	$object = get_taxonomy( $taxonomy );
	return $object ? (string) $object->labels->singular_name : $taxonomy;
}

/**
 * Filter-Werte mit Anzahl für eine Treffermenge - eine einzige gruppierte Abfrage.
 *
 * @param int[] $ids Produkt-IDs
 * @return array<string,array{label:string,terms:array<int,array{slug:string,name:string,count:int}>}>
 */
function mlwf_search_facets( array $ids ): array {
	global $wpdb;

	$ids = array_values( array_filter( array_map( 'intval', $ids ) ) );
	if ( empty( $ids ) ) {
		return [];
	}

	$taxonomies = array_values( array_filter( mlwf_search_facet_taxonomies(), 'taxonomy_exists' ) );
	if ( empty( $taxonomies ) ) {
		return [];
	}

	$id_sql  = implode( ',', $ids );
	$tax_sql = "'" . implode( "','", array_map( 'esc_sql', $taxonomies ) ) . "'";

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- IDs per intval(), Taxonomien aus taxonomy_exists() + esc_sql()
	$rows = $wpdb->get_results(
		"SELECT tt.taxonomy, t.term_id, t.name, t.slug, COUNT(DISTINCT tr.object_id) AS cnt
		   FROM {$wpdb->term_relationships} tr
		   JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
		   JOIN {$wpdb->terms} t          ON t.term_id = tt.term_id
		  WHERE tr.object_id IN ({$id_sql})
		    AND tt.taxonomy IN ({$tax_sql})
		  GROUP BY tt.taxonomy, t.term_id, t.name, t.slug
		  ORDER BY tt.taxonomy, t.name",
		ARRAY_A
	);
	// phpcs:enable

	if ( empty( $rows ) ) {
		return [];
	}

	$labels      = function_exists( 'mlwf_get_attribute_labels' ) ? mlwf_get_attribute_labels() : [];
	$default_cat = (int) get_option( 'default_product_cat' );
	$facets      = [];

	foreach ( $rows as $row ) {
		$taxonomy = $row['taxonomy'];

		// "Uncategorized" ist kein sinnvoller Filter
		if ( $taxonomy === 'product_cat' && (int) $row['term_id'] === $default_cat ) {
			continue;
		}

		if ( ! isset( $facets[ $taxonomy ] ) ) {
			$facets[ $taxonomy ] = [
				'label' => mlwf_search_facet_label( $taxonomy, $labels ),
				'terms' => [],
			];
		}

		$facets[ $taxonomy ]['terms'][] = [
			'slug'  => $row['slug'],
			'name'  => $row['name'],
			'count' => (int) $row['cnt'],
		];
	}

	// Filter mit nur einem Wert filtern nichts
	$min_terms = (int) apply_filters( 'mlwf_search_facet_min_terms', 2 );
	$facets    = array_filter( $facets, static fn( $facet ) => count( $facet['terms'] ) >= $min_terms );

	// Reihenfolge: Kategorie, Marke, Attribute (wie in mlwf_search_facet_taxonomies())
	$ordered = [];
	foreach ( $taxonomies as $taxonomy ) {
		if ( isset( $facets[ $taxonomy ] ) ) {
			$ordered[ $taxonomy ] = $facets[ $taxonomy ];
		}
	}

	return (array) apply_filters( 'mlwf_search_facets', $ordered, $ids );
}

// ---------------------------------------------------------------------------
// Filter aus der URL (Haupt-Abfrage)
// ---------------------------------------------------------------------------

/**
 * URL-Parameter eines Filters auf der Suche: Taxonomie mit Präfix "f_".
 *
 * Ohne Präfix wäre ?s=ring&product_cat=armschmuck ein öffentlicher WordPress-Query-Var:
 * WordPress/WooCommerce behandeln die Anfrage dann als Kategorie-Archiv und laden das
 * Shop-Template statt search.php (Seitenkopf der Kategorie, Woo-Sortierung, kein
 * Produktfilter). Das gilt für product_cat und product_brand, bei öffentlichen
 * Attributen auch für pa_*. Mit Präfix bleibt es eine Suche. Das Filter-JS verwendet
 * dasselbe Präfix (mlwf-filters.js, SEARCH_PARAM_PREFIX).
 */
function mlwf_search_filter_param( string $taxonomy ): string {
	return 'f_' . $taxonomy;
}

/**
 * Aktive Filter aus der URL. Format wie das JS (updateUrl): Werte kommagetrennt,
 * z. B. ?s=ring&f_product_cat=eheringe,damenringe&f_pa_material=gold&price_min=100&orderby=price
 */
function mlwf_search_request_filters(): array {
	static $cache = null;
	if ( $cache !== null ) {
		return $cache;
	}

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- reine Anzeige-Filter (GET)
	$taxonomies = [];
	foreach ( mlwf_search_facet_taxonomies() as $taxonomy ) {
		$param = mlwf_search_filter_param( $taxonomy );

		if ( empty( $_GET[ $param ] ) || ! is_string( $_GET[ $param ] ) ) {
			continue;
		}
		$slugs = array_values( array_filter( array_map( 'sanitize_title', explode( ',', wp_unslash( $_GET[ $param ] ) ) ) ) );
		if ( $slugs ) {
			$taxonomies[ $taxonomy ] = $slugs;
		}
	}

	$price = static function ( string $key ): ?float {
		return ( isset( $_GET[ $key ] ) && is_string( $_GET[ $key ] ) && is_numeric( $_GET[ $key ] ) ) ? (float) $_GET[ $key ] : null;
	};

	$cache = [
		'taxonomies' => $taxonomies,
		'price_min'  => $price( 'price_min' ),
		'price_max'  => $price( 'price_max' ),
		'orderby'    => ( isset( $_GET['orderby'] ) && is_string( $_GET['orderby'] ) ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : '',
	];
	// phpcs:enable

	return $cache;
}

/**
 * Query-Args für eine WooCommerce-Sortierung ([] = Relevanz/Standard).
 */
function mlwf_search_orderby_args( string $orderby ): array {
	$map = [
		'price'      => [ 'orderby' => 'meta_value_num', 'meta_key' => '_price',              'order' => 'ASC' ],
		'price-desc' => [ 'orderby' => 'meta_value_num', 'meta_key' => '_price',              'order' => 'DESC' ],
		'popularity' => [ 'orderby' => 'meta_value_num', 'meta_key' => 'total_sales',         'order' => 'DESC' ],
		'rating'     => [ 'orderby' => 'meta_value_num', 'meta_key' => '_wc_average_rating', 'order' => 'DESC' ],
		'date'       => [ 'orderby' => 'date',                                                'order' => 'DESC' ],
	];

	return $map[ $orderby ] ?? [];
}

/**
 * Wendet die URL-Filter auf die Haupt-Abfrage der Suche an. Sobald ein Filter
 * oder eine Preis-Sortierung aktiv ist, zeigt die Suche nur Produkte (Preis und
 * Attribute würden Seiten und Beiträge sonst alle herausfiltern).
 *
 * Priorität 12: VOR dem Primen des Produkttyp-Caches (Theme, Priorität 20), das
 * die Query-Vars kopiert - sonst würde die falsche Treffermenge geprimt.
 */
add_action( 'pre_get_posts', 'mlwf_search_apply_request_filters', 12 );

function mlwf_search_apply_request_filters( WP_Query $query ): void {
	if ( is_admin() || ! $query->is_main_query() || ! $query->is_search() ) {
		return;
	}

	$filters   = mlwf_search_request_filters();
	$order     = mlwf_search_orderby_args( $filters['orderby'] );
	$has_price = $filters['price_min'] !== null || $filters['price_max'] !== null;

	if ( empty( $filters['taxonomies'] ) && ! $has_price && empty( $order ) ) {
		return;
	}

	$query->set( 'post_type', 'product' );

	$tax_query = $query->get( 'tax_query' );
	$tax_query = is_array( $tax_query ) ? $tax_query : [];
	foreach ( $filters['taxonomies'] as $taxonomy => $slugs ) {
		$tax_query[] = [
			'taxonomy' => $taxonomy,
			'field'    => 'slug',
			'terms'    => $slugs,
			'operator' => 'IN',
		];
	}
	if ( $tax_query ) {
		$query->set( 'tax_query', $tax_query );
	}

	if ( $has_price ) {
		$meta_query   = $query->get( 'meta_query' );
		$meta_query   = is_array( $meta_query ) ? $meta_query : [];
		$meta_query[] = [
			'key'     => '_price',
			'value'   => [ $filters['price_min'] ?? 0, $filters['price_max'] ?? 999999 ],
			'compare' => 'BETWEEN',
			'type'    => 'NUMERIC',
		];
		$query->set( 'meta_query', $meta_query );
	}

	foreach ( $order as $key => $value ) {
		$query->set( $key, $value );
	}
}

// ---------------------------------------------------------------------------
// Trefferanzeige (wie die Loop-Kopfzeile des Shops)
// ---------------------------------------------------------------------------

/**
 * "Ergebnisse 1–24 von 139 werden angezeigt" - Markup wie der Shop (.wc-loop-header
 * mit .js-product-count), damit Styling und das Filter-JS greifen. Basis ist die
 * Haupt-Abfrage der Seite.
 */
function mlwf_get_search_count_html(): string {
	global $wp_query;

	$total = (int) $wp_query->found_posts;
	if ( $total < 1 ) {
		return '';
	}

	$per_page = max( 1, (int) $wp_query->get( 'posts_per_page' ) );
	$paged    = max( 1, (int) $wp_query->get( 'paged' ) );
	$first    = ( $paged - 1 ) * $per_page + 1;
	$last     = min( $total, $paged * $per_page );

	$template = class_exists( 'MediaLab_Search_Settings' )
		? (string) ( MediaLab_Search_Settings::get()['text']['serp_range'] ?? '' )
		: '';
	if ( $template === '' ) {
		$template = __( 'Ergebnisse %1$s–%2$s von %3$s werden angezeigt', 'medialab-woo-filters' );
	}

	// str_replace statt sprintf: ein Tippfehler im Text (z. B. ein einzelnes %) darf nichts abbrechen
	$text = $total === 1
		? __( '1 Ergebnis wird angezeigt', 'medialab-woo-filters' )
		: str_replace(
			[ '%1$s', '%2$s', '%3$s' ],
			[ number_format_i18n( $first ), number_format_i18n( $last ), number_format_i18n( $total ) ],
			$template
		);

	return '<div class="wc-loop-header">'
		. '<div class="wc-loop-header__count js-product-count" data-range="' . esc_attr( $template ) . '">'
		. '<p class="woocommerce-result-count">' . esc_html( $text ) . '</p>'
		. '</div></div>';
}

// ---------------------------------------------------------------------------
// Filterleiste (Markup wie mlwf_render_filter_bar(), damit CSS + JS greifen)
// ---------------------------------------------------------------------------

/**
 * Filterleiste + Trefferanzeige für eine Suche. Leerer String, wenn es nichts zu filtern gibt.
 *
 * @param string $search Roher Suchbegriff (NICHT bereits escaped)
 */
function mlwf_get_search_filter_bar( string $search ): string {
	if ( ! function_exists( 'WC' ) || mb_strlen( trim( $search ) ) < 2 ) {
		return '';
	}

	$ids = mlwf_search_product_ids( $search );
	if ( count( $ids ) < (int) apply_filters( 'mlwf_search_filter_min_products', 2 ) ) {
		return '';
	}

	$facets = mlwf_search_facets( $ids );
	$active = mlwf_search_request_filters();

	$sort_options = (array) apply_filters( 'mlwf_search_sort_options', [
		'menu_order' => __( 'Relevanz', 'medialab-woo-filters' ),
		'price'      => __( 'Preis aufsteigend', 'medialab-woo-filters' ),
		'price-desc' => __( 'Preis absteigend', 'medialab-woo-filters' ),
		'date'       => __( 'Neueste zuerst', 'medialab-woo-filters' ),
	] );
	$current_sort = isset( $sort_options[ $active['orderby'] ] ) ? $active['orderby'] : 'menu_order';

	$has_price  = $active['price_min'] !== null || $active['price_max'] !== null;
	$has_active = ! empty( $active['taxonomies'] ) || $has_price || $current_sort !== 'menu_order';

	$reset_label = class_exists( 'MediaLab_Filter_Settings' ) ? (string) MediaLab_Filter_Settings::label( 'reset' ) : '';
	if ( $reset_label === '' ) {
		$reset_label = __( 'Zurücksetzen', 'medialab-woo-filters' );
	}

	$price_label = class_exists( 'MediaLab_Filter_Settings' ) ? (string) MediaLab_Filter_Settings::label( 'price' ) : '';
	if ( $price_label === '' ) {
		$price_label = __( 'Preis', 'medialab-woo-filters' );
	}

	// Schalter "Filter" (nur unterhalb von Desktop sichtbar, per CSS): Zähler wie im JS
	// (updateActiveCount) = ausgewählte Werte + Preisfilter
	$filter_label = __( 'Filter', 'medialab-woo-filters' );
	$active_count = array_sum( array_map( 'count', $active['taxonomies'] ) ) + ( $has_price ? 1 : 0 );

	ob_start();
	?>
	<div class="wc-filter-bar wc-filter-bar--search"
		id="wc-filter-sidebar"
		data-category=""
		data-brand=""
		data-search="<?php echo esc_attr( $search ); ?>">

		<?php
		// Mobil/Tablet: Schalter statt zwei Dutzend Pills. Das Filter-JS (initMobileToggle) schaltet
		// die Klasse is-open an der Leiste um und pflegt aria-expanded; den Zähler aktualisiert
		// updateActiveCount() (.js-active-filter-count). Ab Desktop blendet das CSS den Schalter aus.
		?>
		<button class="wc-filter-bar__toggle js-filter-toggle-mobile" type="button"
			aria-expanded="false" aria-controls="wc-filter-groups">
			<span class="wc-filter-bar__toggle-label"><?php echo esc_html( $filter_label ); ?></span>
			<span class="wc-filter-bar__toggle-count js-active-filter-count"<?php echo $active_count ? '' : ' hidden'; ?>><?php echo (int) $active_count; ?></span>
			<svg class="wc-filter-bar__toggle-icon" width="14" height="14" viewBox="0 0 24 24" fill="none"
				stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
		</button>

		<form class="wc-filter-bar__form js-filter-form" novalidate>
			<input type="hidden" name="category" value="">
			<input type="hidden" name="brand"    value="">
			<input type="hidden" name="search"   value="<?php echo esc_attr( $search ); ?>">

			<div class="wc-filter-bar__groups" id="wc-filter-groups">

				<div class="wc-filter-bar__groups-filters">

				<?php
				// ── Preis: Slider wird beim Öffnen initialisiert (JS), Spanne = gefundene Produkte.
				// Die Felder sind aus der URL vorbelegt, damit ein Preisfilter beim Neuladen
				// nicht verloren geht, bevor der Slider geöffnet wurde.
				?>
				<div class="wc-filter-group wc-filter-group--price js-filter-group" data-filter-type="price">
					<button class="wc-filter-group__toggle<?php echo $has_price ? ' has-active' : ''; ?>" type="button" aria-expanded="false">
						<?php echo esc_html( $price_label ); ?>
						<span class="wc-filter-group__icon" aria-hidden="true"></span>
					</button>
					<div class="wc-filter-group__dropdown" hidden>
						<div class="wc-price-slider js-price-slider"></div>
						<div class="wc-price-inputs">
							<input class="wc-price-input js-price-min" type="number" name="price_min" min="0" step="1"
								value="<?php echo $active['price_min'] !== null ? esc_attr( (string) (int) $active['price_min'] ) : ''; ?>">
							<span class="wc-price-separator">–</span>
							<input class="wc-price-input js-price-max" type="number" name="price_max" min="0" step="1"
								value="<?php echo $active['price_max'] !== null ? esc_attr( (string) (int) $active['price_max'] ) : ''; ?>">
						</div>
					</div>
				</div>

				<?php foreach ( $facets as $taxonomy => $facet ) :
					$active_vals = $active['taxonomies'][ $taxonomy ] ?? [];
					$group_id    = 'filter-drop-' . $taxonomy;
				?>
				<div class="wc-filter-group js-filter-group" data-filter-type="attribute" data-attribute="<?php echo esc_attr( $taxonomy ); ?>">
					<button class="wc-filter-group__toggle<?php echo $active_vals ? ' has-active' : ''; ?>"
						type="button"
						aria-expanded="false"
						aria-controls="<?php echo esc_attr( $group_id ); ?>">
						<?php echo esc_html( $facet['label'] ); ?>
						<?php if ( $active_vals ) : ?>
							<span class="wc-filter-group__count"><?php echo count( $active_vals ); ?></span>
						<?php endif; ?>
						<span class="wc-filter-group__icon" aria-hidden="true"></span>
					</button>
					<div class="wc-filter-group__dropdown" id="<?php echo esc_attr( $group_id ); ?>" hidden>
						<ul class="wc-filter-checklist" role="group">
							<?php foreach ( $facet['terms'] as $term ) :
								$input_id = 'filter-' . $taxonomy . '-' . $term['slug'];
							?>
							<li class="wc-filter-checklist__item">
								<label class="wc-filter-option" for="<?php echo esc_attr( $input_id ); ?>">
									<input class="wc-filter-option__checkbox"
										id="<?php echo esc_attr( $input_id ); ?>"
										type="checkbox"
										name="attributes[<?php echo esc_attr( $taxonomy ); ?>][]"
										value="<?php echo esc_attr( $term['slug'] ); ?>"
										<?php checked( in_array( $term['slug'], $active_vals, true ) ); ?>>
									<span class="wc-filter-option__label"><?php echo esc_html( $term['name'] ); ?></span>
									<span class="wc-filter-option__count">(<?php echo absint( $term['count'] ); ?>)</span>
								</label>
							</li>
							<?php endforeach; ?>
						</ul>
					</div>
				</div>
				<?php endforeach; ?>

				</div><!-- .wc-filter-bar__groups-filters -->

				<?php /* Sortierung: kein verschachteltes <form> (HTML erlaubt das nicht), nur das Select */ ?>
				<div class="wc-filter-bar__groups-sort">
					<div class="wc-filter-group wc-filter-group--sort" data-filter-type="sort">
						<div class="woocommerce-ordering">
							<select class="orderby" name="orderby" aria-label="<?php esc_attr_e( 'Sortieren nach', 'medialab-woo-filters' ); ?>">
								<?php foreach ( $sort_options as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current_sort, $value ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>
				</div>

			</div><!-- .wc-filter-bar__groups -->

			<button class="wc-filter-bar__reset js-filter-reset" type="button" <?php echo $has_active ? '' : 'hidden'; ?>>
				<?php echo esc_html( $reset_label ); ?>
			</button>

		</form>

		<div class="wc-active-filters js-active-filters"></div>

	</div><!-- .wc-filter-bar -->
	<?php

	// Trefferanzeige direkt unter der Leiste, wie im Shop
	return (string) ob_get_clean() . mlwf_get_search_count_html();
}
