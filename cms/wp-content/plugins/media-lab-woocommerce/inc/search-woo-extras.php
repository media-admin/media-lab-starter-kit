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
 *   mlw_search_taxonomies                ( string[] )  Taxonomien, die als Treffer zählen (Standard: product_cat, product_brand)
 *   mlw_search_match_terms_max            ( int )       Wie viele passende Kategorien/Marken je Taxonomie ausgewertet werden (Standard 25, die größten zuerst)
 *
 * Kategorien nicht als Treffer zählen lassen (z. B. im Theme): Dann findet "ring" nur Titel-Treffer und Marken,
 * nicht mehr alle Produkte der Kategorien Ringe, Eheringe ... - dafür findet "Eheringe" die Trauringe nicht,
 * wenn das Wort weder im Titel noch im Text steht:
 *   add_filter( 'mlw_search_taxonomies', function ( array $taxonomies ): array {
 *       return array_diff( $taxonomies, [ 'product_cat' ] );
 *   } );
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
	$per_term = $limit; // die Obergrenze legt der Aufrufer fest (Live-Suche klein, Ergebnisseite und Filter bis 5000)

	foreach ( (array) apply_filters( 'mlw_search_taxonomies', [ 'product_cat', 'product_brand' ] ) as $taxonomy ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			continue;
		}

		$terms = get_terms( [
			'taxonomy'   => $taxonomy,
			'name__like' => $search,
			'hide_empty' => true,
			'number'     => max( 1, (int) apply_filters( 'mlw_search_match_terms_max', 25 ) ),
                       'orderby'    => 'count',
                       'order'      => 'DESC',
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


// ---------------------------------------------------------------------------
// Kategorien und Marken als eigene Einträge (Live-Suche und Ergebnisseite)
// ---------------------------------------------------------------------------
//
// Zusätzlich zu den Produkten, die über eine Kategorie oder Marke gefunden werden (siehe oben), erscheint die
// Kategorie bzw. Marke selbst als Eintrag mit Link auf ihre Archivseite:
//   - Live-Suche: bis zu 3 Einträge ganz oben (Filter media_lab_ajax_search_term_results des Cores, ab 1.36.0)
//   - Ergebnisseite: Zeile "Passende Kategorien und Marken" unter dem Suchfeld (mlw_get_search_term_links(),
//     aufgerufen von search.php des Themes)
//
// Reihenfolge: exakter Name, Wortanfang, "enthält"; innerhalb davon Kategorien vor Marken, dann nach Produktanzahl.
//
// Hooks:
//   mlw_search_term_taxonomies ( string[] )  Taxonomien für die Einträge, Standard product_cat und product_brand.
//                                            Keine Einträge: add_filter( 'mlw_search_term_taxonomies', '__return_empty_array' );
//   mlw_search_term_results_max ( int )      Anzahl der Einträge, Standard 3

/**
 * Passende Kategorien und Marken zu einem Suchbegriff.
 *
 * @return WP_Term[] nach Relevanz sortiert, höchstens $max Einträge (Standard: Filter mlw_search_term_results_max)
 */
function mlw_search_matching_terms( string $search, int $max = 0 ): array {
	$search = trim( $search );
	if ( mb_strlen( $search ) < 2 || ! function_exists( 'WC' ) ) {
		return [];
	}

	$max = $max > 0 ? $max : max( 1, (int) apply_filters( 'mlw_search_term_results_max', 3 ) );

	$taxonomies = array_values( array_filter(
		(array) apply_filters( 'mlw_search_term_taxonomies', [ 'product_cat', 'product_brand' ] ),
		'taxonomy_exists'
	) );
	if ( empty( $taxonomies ) ) {
		return [];
	}

	$word_start = '/(?<![\p{L}\p{N}])' . preg_quote( $search, '/' ) . '/iu';
	$default_cat = (int) get_option( 'default_product_cat' );
	$candidates  = [];

	foreach ( $taxonomies as $order => $taxonomy ) {
		$terms = get_terms( [
			'taxonomy'   => $taxonomy,
			'name__like' => $search,
			'hide_empty' => true,
			'number'     => 20,
		] );
		if ( is_wp_error( $terms ) ) {
			continue;
		}

		foreach ( $terms as $term ) {
			if ( $taxonomy === 'product_cat' && (int) $term->term_id === $default_cat ) {
				continue; // Standardkategorie ("Unkategorisiert")
			}

			if ( mb_strtolower( $term->name ) === mb_strtolower( $search ) ) {
				$rank = 0;
			} elseif ( preg_match( $word_start, $term->name ) === 1 ) {
				$rank = 1;
			} else {
				$rank = 2;
			}

			$candidates[] = [
				'rank'  => $rank,
				'order' => $order,
				'count' => (int) $term->count,
				'term'  => $term,
			];
		}
	}

	// aufsteigend nach Relevanz und Taxonomie, absteigend nach Produktanzahl
	usort( $candidates, static function ( array $a, array $b ): int {
		return [ $a['rank'], $a['order'], -$a['count'] ] <=> [ $b['rank'], $b['order'], -$b['count'] ];
	} );

	return array_slice( array_column( $candidates, 'term' ), 0, $max );
}

/**
 * Produktanzahl eines Terms für die Anzeige. WooCommerce rechnet die Anzahl auf normalen Seiten
 * um (Katalog-Sichtbarkeit, wc_change_term_counts()), überspringt das aber bei AJAX-Anfragen:
 * die Live-Suche bekäme sonst den Rohwert inklusive versteckter Produkte. Wir lesen deshalb
 * dasselbe Term-Meta, das WooCommerce pflegt, und fallen auf den Rohwert zurück.
 */
function mlw_search_term_display_count( WP_Term $term ): int {
	$meta = get_term_meta( $term->term_id, 'product_count_' . $term->taxonomy, true );
	return ( '' !== $meta && false !== $meta ) ? absint( $meta ) : (int) $term->count;
}

// Live-Suche: Kategorien und Marken ganz oben (Felder wie die übrigen Treffer)
add_filter( 'media_lab_ajax_search_term_results', function ( array $results, string $search, array $post_types ): array {
	if ( ! in_array( 'product', $post_types, true ) ) {
		return $results;
	}

	$highlight = ! class_exists( 'MediaLab_Search_Settings' ) || ! empty( MediaLab_Search_Settings::get()['highlight'] );

	foreach ( mlw_search_matching_terms( $search ) as $term ) {
		$link = get_term_link( $term );
		if ( is_wp_error( $link ) ) {
			continue;
		}

		$name  = ( $highlight && function_exists( 'agency_core_highlight_search_term' ) )
			? agency_core_highlight_search_term( $term->name, $search )
			: esc_html( $term->name );
		$count = mlw_search_term_display_count( $term );

		$results[] = [
			'id'        => (int) $term->term_id,
			'title'     => $name . ' <span class="ajax-search__count">(' . esc_html( number_format_i18n( $count ) ) . ')</span>',
			'permalink' => $link,
			/* translators: %s: Anzahl der Produkte */
			'excerpt'   => sprintf( _n( '%s Produkt', '%s Produkte', $count, 'medialab-woo-filters' ), number_format_i18n( $count ) ),
			'date'      => '',
			'post_type' => $term->taxonomy,
			'thumbnail' => false,
		];
	}

	return $results;
}, 10, 3 );

/**
 * Zeile "Passende Kategorien und Marken" für die Ergebnisseite (search.php des Themes).
 * Leerer String, wenn nichts passt. Alles Markup ist escaped.
 */
function mlw_get_search_term_links( string $search ): string {
	$has_category = false;
	$has_other    = false;
	$items        = '';

	foreach ( mlw_search_matching_terms( $search ) as $term ) {
		$link = get_term_link( $term );
		if ( is_wp_error( $link ) ) {
			continue;
		}

		if ( $term->taxonomy === 'product_cat' ) {
			$has_category = true;
		} else {
			$has_other = true;
		}

		$items .= '<li class="search-terms__item"><a class="search-terms__link" href="' . esc_url( $link ) . '">'
			. esc_html( $term->name )
			. ' <span class="search-terms__count">(' . esc_html( number_format_i18n( (int) $term->count ) ) . ')</span></a></li>';
	}

	if ( $items === '' ) {
		return '';
	}

	if ( $has_category && $has_other ) {
		$label = __( 'Passende Kategorien und Marken:', 'medialab-woo-filters' );
	} elseif ( $has_other ) {
		$label = __( 'Passende Marken:', 'medialab-woo-filters' );
	} else {
		$label = __( 'Passende Kategorien:', 'medialab-woo-filters' );
	}

	return '<nav class="search-terms" aria-label="' . esc_attr( rtrim( $label, ':' ) ) . '">'
		. '<span class="search-terms__label">' . esc_html( $label ) . '</span>'
		. '<ul class="search-terms__list">' . $items . '</ul>'
		. '</nav>';
}
