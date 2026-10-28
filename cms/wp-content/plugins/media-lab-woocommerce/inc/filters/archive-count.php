<?php
/**
 * Trefferanzeige für Shop, Kategorien, Schlagwörter und Marken.
 *
 * Dasselbe Markup wie die Trefferanzeige der Suche (mlwf_get_search_count_html():
 * .wc-loop-header mit .js-product-count), damit Styling und das Filter-JS greifen. Das Theme
 * entfernt die Ergebnisanzahl von WooCommerce, ersetzt wurde sie bisher nur für die Suche.
 *
 * Hängt hinter der Filterleiste (Priorität 15) und vor dem Wrapper um die Produkte (Priorität 20)
 * an 'woocommerce_before_shop_loop'. Entfällt, wenn die Ergebnisanzahl von WooCommerce noch
 * eingehängt ist. Mit dem Filter 'mlwf_show_archive_count' lässt sie sich abschalten (zum
 * Beispiel, wenn ein Theme die Anzeige selbst ausgibt).
 *
 * @package MedialabWooFilters
 */

defined( 'ABSPATH' ) || exit;

/**
 * @param int $total    Gesamtzahl der Treffer
 * @param int $per_page Treffer pro Seite
 * @param int $paged    Aktuelle Seite (ab 1)
 */
function mlwf_get_archive_count_html( int $total, int $per_page, int $paged ): string {
	if ( $total < 1 ) {
		return '';
	}

	$per_page = max( 1, $per_page );
	$paged    = max( 1, $paged );
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

function mlwf_render_archive_count(): void {
	if ( ! ( is_shop() || is_product_category() || is_product_tag() || is_tax( 'product_brand' ) ) ) {
		return;
	}
	if ( has_action( 'woocommerce_before_shop_loop', 'woocommerce_result_count' ) ) {
		return;
	}
	if ( ! apply_filters( 'mlwf_show_archive_count', true ) ) {
		return;
	}

	echo mlwf_get_archive_count_html( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markup wird oben escaped.
		(int) wc_get_loop_prop( 'total' ),
		(int) wc_get_loop_prop( 'per_page' ),
		(int) wc_get_loop_prop( 'current_page' )
	);
}
add_action( 'woocommerce_before_shop_loop', 'mlwf_render_archive_count', 16 );
