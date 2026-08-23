<?php
/**
 * Filter-System Setup
 *
 * Registriert nonce und JS-Objekt für das Frontend.
 *
 * @package MedialabWooFilters
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_enqueue_scripts', 'mlwf_enqueue_frontend_data' );

function mlwf_enqueue_frontend_data(): void {
	if ( ! ( is_shop() || is_product_category() || is_product_tag() || is_tax( 'product_brand' ) ) ) {
		return;
	}

	// JS-Objekt für AJAX — kompatibel mit janeckaWC (Theme) und mlwf (generisch)
	$data = [
		'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
		'nonce'      => wp_create_nonce( 'mlwf_filter_nonce' ),
		'currency'   => get_woocommerce_currency_symbol(),
		'actionFilter'     => 'mlwf_filter_products',
		'actionPriceRange' => 'mlwf_get_price_range',
		'i18n' => [
			'loading'    => MediaLab_Filter_Settings::label( 'loading' ),
			'noProducts' => MediaLab_Filter_Settings::label( 'no_products' ),
			'reset'      => MediaLab_Filter_Settings::label( 'reset' ),
		],
	];

	// Auf das Theme-Script warten und Daten davor injizieren
	$handles = [ 'janecka-wc-filters', 'woocommerce-filters', 'mlwf-filters' ];

	foreach ( $handles as $handle ) {
		if ( wp_script_is( $handle, 'registered' ) || wp_script_is( $handle, 'enqueued' ) ) {
			wp_add_inline_script(
				$handle,
				'window.janeckaWC = ' . wp_json_encode( $data ) . ';'
				. 'window.mlwf = ' . wp_json_encode( $data ) . ';',
				'before'
			);
			return;
		}
	}

	// Fallback: direkt als Inline-Script ausgeben
	add_action( 'wp_head', function() use ( $data ) {
		echo '<script>window.janeckaWC = ' . wp_json_encode( $data ) . ';'
			. 'window.mlwf = ' . wp_json_encode( $data ) . ';</script>' . "\n";
	}, 5 );
}
