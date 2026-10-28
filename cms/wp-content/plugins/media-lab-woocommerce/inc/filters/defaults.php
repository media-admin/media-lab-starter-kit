<?php
/**
 * Standardwerte für die Produktfilter.
 *
 * ACF liefert für Felder, die noch nie gespeichert wurden, null (nicht false). Der Code in
 * filter-config.php hat null wie „aus" behandelt: Die Vererbung auf Eltern-Kategorie und
 * Standard griff nie, der Preisfilter blieb weg, und die Standard-Konfiguration war immer leer,
 * weil ihre globalen Felder keine Oberfläche haben. Hier stehen die Ersatzwerte für „nicht
 * gespeichert".
 *
 * @package MedialabWooFilters
 */

defined( 'ABSPATH' ) || exit;

/**
 * Alle WooCommerce-Attribute als Taxonomie-Namen (pa_color, pa_size, …).
 *
 * Wird verwendet, wenn weder am Term noch global Attribute eingetragen sind („automatische Filter").
 * Werte ohne Treffer im aktuellen Kontext blendet die Filterleiste selbst aus.
 *
 * @return string[]
 */
function mlwf_auto_filter_attributes(): array {
	if ( ! function_exists( 'wc_get_attribute_taxonomies' ) || ! function_exists( 'wc_attribute_taxonomy_name' ) ) {
		return [];
	}

	$slugs = [];
	foreach ( (array) wc_get_attribute_taxonomies() as $attribute ) {
		$taxonomy = wc_attribute_taxonomy_name( $attribute->attribute_name );
		if ( taxonomy_exists( $taxonomy ) ) {
			$slugs[] = $taxonomy;
		}
	}

	return array_values( array_unique( (array) apply_filters( 'mlwf_auto_filter_attributes', $slugs ) ) );
}

/**
 * Preisfilter: nicht gespeichert (null), leer oder false gilt als „an". Gespeicherte Werte
 * verhalten sich wie vorher (0 und '0' schalten den Preisfilter aus).
 *
 * @param mixed $value Rückgabe von get_field( 'mlwf_show_price', … )
 */
function mlwf_show_price_value( $value ): bool {
	if ( $value === null || $value === false || $value === '' ) {
		return true;
	}
	return (bool) $value;
}
