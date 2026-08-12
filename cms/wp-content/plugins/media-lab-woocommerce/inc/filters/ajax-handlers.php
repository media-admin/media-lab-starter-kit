<?php
/**
 * AJAX Handler: Produktfilter
 *
 * @package MedialabWooFilters
 */

defined( 'ABSPATH' ) || exit;

// ---------------------------------------------------------------------------
// Nonce-Prüfung mit Fallback auf abweichenden Nonce-Action-Namen
// ---------------------------------------------------------------------------

/**
 * Prüft den Filter-Nonce gegen die eigenen ('mlwf_filter_nonce') UND eine
 * bei manchen Theme-Konventionen abweichende ('ajax_filters_nonce')
 * Nonce-Action - z.B. Janeckas Theme erzeugt den Nonce unter letzterem
 * Namen. Ohne diesen Fallback schlägt jeder Filter-Request bei solchen
 * Projekten mit 403 fehl, obwohl die Anfrage selbst legitim ist.
 *
 * Bricht die Anfrage mit einer JSON-Fehlerantwort ab, falls keiner der
 * beiden Nonces gültig ist (Verhalten bewusst analog zu check_ajax_referer()
 * mit $die = true, nur eben mit zwei akzeptierten Action-Namen statt einem).
 */
function mlwf_verify_filter_nonce(): void {
	$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );

	if ( wp_verify_nonce( $nonce, 'mlwf_filter_nonce' ) || wp_verify_nonce( $nonce, 'ajax_filters_nonce' ) ) {
		return;
	}

	wp_send_json_error( __( 'Sicherheitsprüfung fehlgeschlagen. Bitte lade die Seite neu und versuche es erneut.', 'medialab-woo-filters' ), 403 );
}

// ---------------------------------------------------------------------------
// Produkte filtern
// ---------------------------------------------------------------------------

add_action( 'wp_ajax_mlwf_filter_products',        'mlwf_ajax_filter_products' );
add_action( 'wp_ajax_nopriv_mlwf_filter_products', 'mlwf_ajax_filter_products' );

// Rückwärtskompatibilität: alter Action-Name
add_action( 'wp_ajax_janecka_filter_products',        'mlwf_ajax_filter_products' );
add_action( 'wp_ajax_nopriv_janecka_filter_products', 'mlwf_ajax_filter_products' );

// Theme-Alias: manche Theme-Konventionen (z.B. Janeckas Theme) senden den
// AJAX-Filter-Request unter der Action 'ajax_filter_posts' statt
// 'janecka_filter_products'/'mlwf_filter_products'. Zusätzlicher, rein
// additiver Alias - wirkungslos für Projekte, die den Namen nicht nutzen,
// schützt aber vor einem stillen Totalausfall der Filterfunktion bei
// Projekten mit ähnlicher Theme-Konvention wie Janecka.
add_action( 'wp_ajax_ajax_filter_posts',        'mlwf_ajax_filter_products' );
add_action( 'wp_ajax_nopriv_ajax_filter_posts', 'mlwf_ajax_filter_products' );

function mlwf_ajax_filter_products(): void {
	mlwf_verify_filter_nonce();

	// ── GZD Loop-Hooks bei AJAX-Pagination entfernen ────────────────────────
	//
	// Projekte, die die GZD-Tax-/Shipping-/Delivery-Info manuell im
	// Product-Card-Template rendern, deaktivieren die GZD-Standard-Loop-Hooks
	// üblicherweise über den 'wp'-Action-Hook im Theme (da 'woocommerce_init'
	// zu früh ist). Der 'wp'-Hook feuert aber NICHT bei admin-ajax.php-
	// Requests, wodurch die GZD-Hooks hier weiterhin aktiv wären und die
	// Info bei AJAX-paginierten Seiten (paged >= 2) doppelt ausgegeben würde.
	// Projekte, die die GZD-Standard-Loop-Ausgabe ausdrücklich behalten
	// wollen, können dies per
	// add_filter( 'mlwf_remove_gzd_loop_hooks_on_ajax', '__return_false' )
	// deaktivieren.
	if ( function_exists( 'WC_germanized' ) && apply_filters( 'mlwf_remove_gzd_loop_hooks_on_ajax', true ) ) {
		remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_gzd_template_loop_tax_info', 6 );
		remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_gzd_template_loop_shipping_costs_info', 7 );
		remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_gzd_template_loop_delivery_time_info', 8 );
		remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_gzd_template_loop_product_units', 9 );
		remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_gzd_template_loop_product_units', 9 );
	}

	$category_slug = sanitize_text_field( $_POST['category']      ?? '' );
	$brand_slug    = sanitize_text_field( $_POST['brand']         ?? '' );
	$subcat_slug   = sanitize_text_field( $_POST['subcat']        ?? '' );
	$attributes    = $_POST['attributes'] ?? [];
	$price_min     = ( isset( $_POST['price_min'] ) && $_POST['price_min'] !== '' ) ? (float) $_POST['price_min'] : null;
	$price_max     = ( isset( $_POST['price_max'] ) && $_POST['price_max'] !== '' ) ? (float) $_POST['price_max'] : null;
	$orderby       = sanitize_text_field( $_POST['orderby']       ?? 'menu_order' );
	$paged         = (int) ( $_POST['paged']                      ?? 1 );

	// ── Tax Query ────────────────────────────────────────────────────────────

	$tax_query = [ 'relation' => 'AND' ];

	if ( $category_slug ) {
		$tax_query[] = [
			'taxonomy' => 'product_cat',
			'field'    => 'slug',
			'terms'    => $category_slug,
		];
	}

	if ( $brand_slug ) {
		$tax_query[] = [
			'taxonomy' => 'product_brand',
			'field'    => 'slug',
			'terms'    => $brand_slug,
		];
	}

	if ( $subcat_slug ) {
		$tax_query[] = [
			'taxonomy' => 'product_cat',
			'field'    => 'slug',
			'terms'    => $subcat_slug,
		];
	}

	if ( is_array( $attributes ) ) {
		foreach ( $attributes as $taxonomy => $terms ) {
			$taxonomy = preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $taxonomy ) );
			$terms    = array_map( 'sanitize_text_field', (array) $terms );
			if ( empty( $terms ) ) continue;

			$tax_query[] = [
				'taxonomy' => $taxonomy,
				'field'    => 'slug',
				'terms'    => $terms,
				'operator' => 'IN',
			];
		}
	}

	// ── Meta Query (Preis) ───────────────────────────────────────────────────

	$meta_query = [ 'relation' => 'AND' ];

	if ( $price_min !== null || $price_max !== null ) {
		$meta_query[] = [
			'key'     => '_price',
			'value'   => [ $price_min ?? 0, $price_max ?? 999999 ],
			'compare' => 'BETWEEN',
			'type'    => 'NUMERIC',
		];
	}

	// ── Sortierung ───────────────────────────────────────────────────────────

	$orderby_map = [
		'menu_order' => [ 'orderby' => 'menu_order', 'order' => 'ASC' ],
		'price'      => [ 'orderby' => 'meta_value_num', 'meta_key' => '_price', 'order' => 'ASC' ],
		'price-desc' => [ 'orderby' => 'meta_value_num', 'meta_key' => '_price', 'order' => 'DESC' ],
		'popularity' => [ 'orderby' => 'meta_value_num', 'meta_key' => 'total_sales', 'order' => 'DESC' ],
		'rating'     => [ 'orderby' => 'meta_value_num', 'meta_key' => '_wc_average_rating', 'order' => 'DESC' ],
		'date'       => [ 'orderby' => 'date', 'order' => 'DESC' ],
	];

	$order_args = $orderby_map[ $orderby ] ?? $orderby_map['menu_order'];

	// ── Query ────────────────────────────────────────────────────────────────

	$args = array_merge( [
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'posts_per_page' => (int) get_option( 'posts_per_page_shop', 12 ),
		'paged'          => $paged,
		'tax_query'      => $tax_query,
		'meta_query'     => $meta_query,
	], $order_args );

	$query = new WP_Query( $args );

	ob_start();

	if ( $query->have_posts() ) {
		woocommerce_product_loop_start();
		while ( $query->have_posts() ) {
			$query->the_post();
			wc_get_template_part( 'content', 'product' );
		}
		woocommerce_product_loop_end();

		// Pagination
		if ( $query->max_num_pages > 1 ) {
			// Basis-URL ermitteln
			if ( $brand_slug ) {
				$brand_term = get_term_by( 'slug', $brand_slug, 'product_brand' );
				$base_url   = $brand_term ? get_term_link( $brand_term ) : home_url( '/' );
			} elseif ( $category_slug ) {
				$cat_term = get_term_by( 'slug', $category_slug, 'product_cat' );
				$base_url = $cat_term ? get_term_link( $cat_term ) : home_url( '/' );
			} else {
				$base_url = get_permalink( wc_get_page_id( 'shop' ) );
			}
			$base_url = trailingslashit( $base_url );

			// Filter-Params
			$filter_params = [];
			if ( $subcat_slug ) $filter_params['subcat'] = $subcat_slug;
			if ( is_array( $attributes ) ) {
				foreach ( $attributes as $tax => $vals ) {
					$tax  = preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $tax ) );
					$vals = array_map( 'sanitize_text_field', (array) $vals );
					if ( ! empty( $vals ) ) $filter_params[ $tax ] = implode( ',', $vals );
				}
			}
			if ( $price_min !== null ) $filter_params['price_min'] = $price_min;
			if ( $price_max !== null ) $filter_params['price_max'] = $price_max;
			if ( $orderby && $orderby !== 'menu_order' ) $filter_params['orderby'] = $orderby;

			$qs    = ! empty( $filter_params ) ? '?' . http_build_query( $filter_params ) : '';
			$links = paginate_links( [
				'base'      => $base_url . '%_%' . $qs,
				'format'    => 'page/%#%/',
				'current'   => max( 1, $paged ),
				'total'     => $query->max_num_pages,
				'prev_text' => '&#8592;',
				'next_text' => '&#8594;',
				'type'      => 'list',
			] );

			if ( $links ) {
				echo '<nav class="woocommerce-pagination">' . $links . '</nav>';
			}
		}
	} else {
		echo '<div class="wc-empty"><p class="wc-empty__text">'
			. esc_html__( 'Keine Produkte gefunden. Bitte versuche andere Filter.', 'medialab-woo-filters' )
			. '</p></div>';
	}

	$html = ob_get_clean();
	wp_reset_postdata();

	wp_send_json_success( [
		'html'        => $html,
		'found_posts' => $query->found_posts,
		'max_pages'   => $query->max_num_pages,
		'current'     => $paged,
		'per_page'    => (int) get_option( 'posts_per_page_shop', 12 ),
	] );
}

// ---------------------------------------------------------------------------
// Preis-Bereich ermitteln
// ---------------------------------------------------------------------------

add_action( 'wp_ajax_mlwf_get_price_range',        'mlwf_ajax_get_price_range' );
add_action( 'wp_ajax_nopriv_mlwf_get_price_range', 'mlwf_ajax_get_price_range' );

// Rückwärtskompatibilität
add_action( 'wp_ajax_janecka_get_price_range',        'mlwf_ajax_get_price_range' );
add_action( 'wp_ajax_nopriv_janecka_get_price_range', 'mlwf_ajax_get_price_range' );

function mlwf_ajax_get_price_range(): void {
	// Selber Nonce-Fallback wie mlwf_ajax_filter_products() - wird vom
	// selben Filter-Bar-JS beim Öffnen des Preis-Sliders angefragt und
	// wäre ohne den Fallback der gleichen 403-Falle ausgesetzt.
	mlwf_verify_filter_nonce();

	$category_slug = sanitize_text_field( $_POST['category'] ?? '' );
	$brand_slug    = sanitize_text_field( $_POST['brand']    ?? '' );

	global $wpdb;
	$where = '';

	if ( $brand_slug ) {
		$term = get_term_by( 'slug', $brand_slug, 'product_brand' );
		if ( $term ) {
			$where = $wpdb->prepare(
				"AND p.ID IN (
					SELECT tr.object_id FROM {$wpdb->term_relationships} tr
					JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
					WHERE tt.term_id = %d
				)",
				$term->term_id
			);
		}
	} elseif ( $category_slug ) {
		$term = get_term_by( 'slug', $category_slug, 'product_cat' );
		if ( $term ) {
			$term_ids     = get_term_children( $term->term_id, 'product_cat' );
			$term_ids[]   = $term->term_id;
			$term_ids_str = implode( ',', array_map( 'intval', $term_ids ) );
			$where        = "AND p.ID IN (
				SELECT object_id FROM {$wpdb->term_relationships} tr
				JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
				WHERE tt.term_id IN ({$term_ids_str})
			)";
		}
	}

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
	$row = $wpdb->get_row( "
		SELECT MIN(CAST(pm.meta_value AS DECIMAL(10,2))) AS min_price,
		       MAX(CAST(pm.meta_value AS DECIMAL(10,2))) AS max_price
		FROM {$wpdb->postmeta} pm
		JOIN {$wpdb->posts} p ON pm.post_id = p.ID
		WHERE pm.meta_key = '_price'
		  AND p.post_type   = 'product'
		  AND p.post_status = 'publish'
		  {$where}
	" );
	// phpcs:enable

	wp_send_json_success( [
		'min' => (float) ( $row->min_price ?? 0 ),
		'max' => (float) ( $row->max_price ?? 10000 ),
	] );
}

// ---------------------------------------------------------------------------
// Hilfsfunktion: Term-Count innerhalb einer Taxonomie
// ---------------------------------------------------------------------------

function mlwf_count_term_in_context( int $term_id, string $taxonomy, array $context_product_ids ): int {
	if ( empty( $context_product_ids ) ) return 0;

	$term_product_ids = get_objects_in_term( $term_id, $taxonomy );
	if ( is_wp_error( $term_product_ids ) ) return 0;

	$intersect = array_intersect( $context_product_ids, $term_product_ids );
	if ( empty( $intersect ) ) return 0;

	return (int) ( new WP_Query( [
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'post__in'       => array_map( 'intval', $intersect ),
		'posts_per_page' => -1,
		'fields'         => 'ids',
	] ) )->found_posts;
}

// Alias für Abwärtskompatibilität
function janecka_count_term_in_category( int $term_id, string $taxonomy, array $cat_product_ids ): int {
	return mlwf_count_term_in_context( $term_id, $taxonomy, $cat_product_ids );
}
