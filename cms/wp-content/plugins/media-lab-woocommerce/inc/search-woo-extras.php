<?php
/**
 * Such-Erweiterungen für WooCommerce-Produkte
 *
 * Hängt sich über Filter in die Suche des Agency Cores ein (Live-Suche, Suchergebnisseite
 * und Treffermenge der Produktfilter - alle drei laufen über dieselben Hooks):
 *
 *   1. Sichtbarkeit: Produkte mit der Katalog-Sichtbarkeit "Nur Shop" oder "Versteckt"
 *      (Term "exclude-from-search") erscheinen nicht in der Suche - die Produktkarte
 *      könnte sie ohnehin nicht darstellen (WC_Product::is_visible()). Optional werden
 *      ausverkaufte Produkte ausgeblendet, wenn WooCommerce das so eingestellt hat
 *      (Einstellungen -> Produkte -> Lagerbestand -> "Nicht vorrätige Artikel ausblenden").
 *   2. SKU: Teilstring-Suche (nicht nur Treffer bei null Ergebnissen), Varianten führen
 *      zum Elternprodukt.
 *   3. Kategorien und Marken: Produkte, deren Kategorie bzw. Marke zum Suchbegriff passt
 *      ("Eheringe", "One More").
 *
 * Hooks (Core):
 *   media_lab_ajax_search_query_args  ( array $args, string $term )
 *   media_lab_search_woo_matches      ( array $matches, string $search, int $limit )
 *   media_lab_search_result_ids       ( array $ids, string $search, string $context )
 * Hooks (hier, zum Anpassen):
 *   mlw_search_excluded_visibility_terms ( string[] )  Sichtbarkeits-Terms, die ausgeschlossen werden
 *   mlw_search_taxonomies                ( string[] )  Taxonomien, die als Treffer zählen
 *
 * Datei ablegen unter: inc/search-woo-extras.php
 * Einbindung: require_once am Ende von inc/ajax-search-wc.php.
 *
 * @package MedialabWooCommerce
 */

defined( 'ABSPATH' ) || exit;

// ---------------------------------------------------------------------------
// Sichtbarkeit
// ---------------------------------------------------------------------------

/**
 * Terms der Taxonomie product_visibility, die ein Produkt aus der Suche ausschließen.
 *
 * @return string[]
 */
function mlw_search_excluded_visibility_terms(): array {
	$terms = [ 'exclude-from-search' ];

	if ( 'yes' === get_option( 'woocommerce_hide_out_of_stock_items' ) ) {
		$terms[] = 'outofstock';
	}

	return array_values( (array) apply_filters( 'mlw_search_excluded_visibility_terms', $terms ) );
}

/**
 * tax_query-Klausel für WP_Query. Beiträge und Seiten haben diese Terms nie,
 * NOT IN lässt sie also unberührt.
 */
function mlw_search_visibility_clause(): array {
	return [
		'taxonomy' => 'product_visibility',
		'field'    => 'slug',
		'terms'    => mlw_search_excluded_visibility_terms(),
		'operator' => 'NOT IN',
	];
}

/**
 * Welche der IDs sind ausgeschlossen? Eine einzige Abfrage, unabhängig von der Menge
 * (get_post_type() je ID würde bei Tausenden Treffern Tausende Abfragen auslösen).
 *
 * @param int[] $ids
 * @return int[]
 */
function mlw_search_excluded_ids( array $ids ): array {
	global $wpdb;

	$ids   = array_values( array_filter( array_map( 'intval', $ids ) ) );
	$slugs = mlw_search_excluded_visibility_terms();
	if ( empty( $ids ) || empty( $slugs ) ) {
		return [];
	}

	$slug_sql = "'" . implode( "','", array_map( 'esc_sql', $slugs ) ) . "'";
	$excluded = [];

	foreach ( array_chunk( $ids, 2000 ) as $chunk ) {
		$id_sql = implode( ',', $chunk );

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- IDs per intval(), Slugs per esc_sql()
		$found = $wpdb->get_col(
			"SELECT DISTINCT tr.object_id
			   FROM {$wpdb->term_relationships} tr
			   JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
			   JOIN {$wpdb->terms} t          ON t.term_id = tt.term_id
			  WHERE tt.taxonomy = 'product_visibility'
			    AND t.slug IN ({$slug_sql})
			    AND tr.object_id IN ({$id_sql})"
		);
		// phpcs:enable

		$excluded = array_merge( $excluded, array_map( 'intval', (array) $found ) );
	}

	return $excluded;
}

// Live-Suche, Synonym-Abfragen der Ergebnisseite und Treffermenge der Produktfilter
add_filter( 'media_lab_ajax_search_query_args', function ( array $args ): array {
	$tax_query = isset( $args['tax_query'] ) && is_array( $args['tax_query'] ) ? $args['tax_query'] : [];
	$tax_query[]       = mlw_search_visibility_clause();
	$args['tax_query'] = $tax_query;

	return $args;
}, 10, 1 );

// Treffer, die nicht über WP_Query laufen (Attribute, SKU, Kategorien, Marken, Fuzzy)
add_filter( 'media_lab_search_result_ids', function ( $ids ): array {
	$ids      = (array) $ids;
	$excluded = mlw_search_excluded_ids( $ids );

	return $excluded ? array_values( array_diff( $ids, $excluded ) ) : array_values( $ids );
}, 10, 1 );

// Haupt-Abfrage der Suchergebnisseite. Priorität 11: nach dem Core (10), vor den Produktfiltern (12)
// und vor dem Priming des Produkttyp-Caches im Theme (20), das die Query-Vars kopiert.
add_action( 'pre_get_posts', function ( WP_Query $query ): void {
	if ( is_admin() || ! $query->is_main_query() || ! $query->is_search() ) {
		return;
	}

	$tax_query = $query->get( 'tax_query' );
	$tax_query = is_array( $tax_query ) ? $tax_query : [];
	$tax_query[] = mlw_search_visibility_clause();

	$query->set( 'tax_query', $tax_query );
}, 11 );

// ---------------------------------------------------------------------------
// SKU, Kategorien, Marken als Treffer
// ---------------------------------------------------------------------------

add_filter( 'media_lab_search_woo_matches', function ( array $matches, string $search, int $limit ): array {
	global $wpdb;

	$search = trim( $search );
	if ( mb_strlen( $search ) < 2 ) {
		return $matches;
	}

	$limit = max( 1, $limit );

	// ── SKU (Teilstring). Varianten führen zum Elternprodukt; beide müssen veröffentlicht sein ──
	$like = '%' . $wpdb->esc_like( $search ) . '%';

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- Platzhalter via prepare()
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT IF(p.post_type = 'product_variation', p.post_parent, p.ID) AS pid, pm.meta_value AS sku
			   FROM {$wpdb->postmeta} pm
			   JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			   LEFT JOIN {$wpdb->posts} parent ON parent.ID = p.post_parent AND p.post_type = 'product_variation'
			  WHERE pm.meta_key = '_sku'
			    AND pm.meta_value LIKE %s
			    AND (
			         ( p.post_type = 'product' AND p.post_status = 'publish' )
			      OR ( p.post_type = 'product_variation' AND p.post_status = 'publish' AND parent.post_status = 'publish' )
			    )
			  LIMIT %d",
			$like,
			min( $limit * 3, 5000 )
		),
		ARRAY_A
	);
	// phpcs:enable

	foreach ( (array) $rows as $row ) {
		$pid = (int) $row['pid'];
		if ( $pid && ! isset( $matches[ $pid ] ) ) {
			/* translators: %s: Artikelnummer (SKU) */
			$matches[ $pid ] = sprintf( __( 'Art.-Nr.: %s', 'medialab-woo-filters' ), $row['sku'] );
		}
	}

	// ── Kategorien und Marken: Produkte, deren Term-Name zum Suchbegriff passt ──
	$per_term = min( $limit, 500 );

	foreach ( (array) apply_filters( 'mlw_search_taxonomies', [ 'product_cat', 'product_brand' ] ) as $taxonomy ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			continue;
		}

		$terms = get_terms( [
			'taxonomy'   => $taxonomy,
			'name__like' => $search,
			'hide_empty' => true,
			'number'     => 5,
		] );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			continue;
		}

		$taxonomy_label = get_taxonomy( $taxonomy )->labels->singular_name;

		foreach ( $terms as $term ) {
			// get_posts(): suppress_filters = true -> kein Eingriff der Such-Hooks, keine Rekursion
			$ids = get_posts( [
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'posts_per_page' => $per_term,
				'no_found_rows'  => true,
				'tax_query'      => [ [
					'taxonomy'         => $taxonomy,
					'field'            => 'term_id',
					'terms'            => $term->term_id,
					'include_children' => true,
				] ],
			] );

			foreach ( $ids as $id ) {
				if ( ! isset( $matches[ $id ] ) ) {
					$matches[ $id ] = sprintf( '%s: %s', $taxonomy_label, $term->name );
				}
			}
		}
	}

	return $matches;
}, 10, 3 );
