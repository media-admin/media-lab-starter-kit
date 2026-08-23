<?php
/**
 * Produktfilter-Konfiguration
 *
 * Liest Filter-Einstellungen aus ACF-Feldern.
 * Unterstützt product_cat und product_brand Taxonomien mit Vererbungslogik.
 *
 * @package MedialabWooFilters
 */

defined( 'ABSPATH' ) || exit;

// ---------------------------------------------------------------------------
// Öffentliche API
// ---------------------------------------------------------------------------

/**
 * Filter-Konfiguration für eine Produktkategorie.
 *
 * @param int|null $term_id Term-ID (null = aktuelle Seite)
 */
function mlwf_get_category_filter_config( ?int $term_id = null ): array {
	if ( $term_id === null ) {
		if ( is_product_category() ) {
			$term_id = get_queried_object_id();
		} else {
			return mlwf_get_default_filter_config();
		}
	}

	return mlwf_resolve_filter_config( $term_id, 'product_cat', [] );
}

/**
 * Filter-Konfiguration für eine Marken-Seite.
 *
 * @param int|null $term_id Term-ID (null = aktuelle Seite)
 */
function mlwf_get_brand_filter_config( ?int $term_id = null ): array {
	if ( $term_id === null ) {
		if ( is_tax( 'product_brand' ) ) {
			$term_id = get_queried_object_id();
		} else {
			return mlwf_get_default_filter_config();
		}
	}

	return mlwf_resolve_filter_config( $term_id, 'product_brand', [] );
}

/**
 * Automatische Erkennung des Kontexts — gibt die passende Konfiguration zurück.
 */
function mlwf_get_current_filter_config(): array {
	if ( is_product_category() ) {
		return mlwf_get_category_filter_config();
	}

	if ( is_tax( 'product_brand' ) ) {
		return mlwf_get_brand_filter_config();
	}

	if ( is_shop() ) {
		return mlwf_get_default_filter_config();
	}

	return mlwf_get_default_filter_config();
}

/**
 * Alias für Abwärtskompatibilität mit Theme-Code.
 */
function janecka_get_current_category_filter_config(): array {
	return mlwf_get_current_filter_config();
}

function janecka_get_category_filter_config( ?int $term_id = null ): array {
	return mlwf_get_category_filter_config( $term_id );
}

// ---------------------------------------------------------------------------
// Interne Logik
// ---------------------------------------------------------------------------

/**
 * Rekursive Auflösung der Filter-Konfiguration mit Vererbung.
 *
 * @param int    $term_id  Aktuelle Term-ID
 * @param string $taxonomy Taxonomie (product_cat oder product_brand)
 * @param array  $visited  Bereits besuchte Term-IDs
 */
function mlwf_resolve_filter_config( int $term_id, string $taxonomy, array $visited ): array {
	if ( in_array( $term_id, $visited, true ) ) {
		return mlwf_get_default_filter_config();
	}
	$visited[] = $term_id;

	$acf_key = $taxonomy . '_' . $term_id;

	$inherit      = (bool) get_field( 'mlwf_inherit_parent',       $acf_key );
	$show_price   = get_field( 'mlwf_show_price',                  $acf_key );
	$attributes   = get_field( 'mlwf_attributes',                  $acf_key );
	$show_brands  = get_field( 'mlwf_show_brands',                 $acf_key );
	$show_subcats = get_field( 'mlwf_show_subcategories',          $acf_key );
	$order_raw    = get_field( 'mlwf_filter_order',                $acf_key );

	$has_own_config = ! empty( $attributes ) || $show_brands;

	// Vererbung: keine eigene Config → Elternkategorie prüfen
	if ( $inherit && ! $has_own_config ) {
		$term = get_term( $term_id, $taxonomy );
		if ( $term && ! is_wp_error( $term ) && ! empty( $term->parent ) ) {
			$parent_config                       = mlwf_resolve_filter_config( $term->parent, $taxonomy, $visited );
			$parent_config['source']             = 'parent:' . $term->parent;
			$parent_config['show_subcategories'] = (bool) $show_subcats;
			return $parent_config;
		}
		return mlwf_get_default_filter_config();
	}

	// Eigene Konfiguration aufbauen
	$attribute_slugs = is_array( $attributes ) ? $attributes : [];

	// Marken-Filter einbauen
	if ( $show_brands ) {
		foreach ( [ 'product_brand', 'pa_brand', 'pa_marke' ] as $brand_tax ) {
			if ( taxonomy_exists( $brand_tax ) && ! in_array( $brand_tax, $attribute_slugs, true ) ) {
				$attribute_slugs[] = $brand_tax;
				break;
			}
		}
	}

	// Reihenfolge anwenden
	if ( $order_raw ) {
		$ordered         = array_filter( array_map( 'trim', explode( "\n", $order_raw ) ) );
		$rest            = array_diff( $attribute_slugs, $ordered );
		$attribute_slugs = array_values( array_merge(
			array_intersect( $ordered, $attribute_slugs ),
			$rest
		) );
	}

	return [
		'attributes'         => $attribute_slugs,
		'show_price'         => $show_price !== false ? (bool) $show_price : true,
		'show_brands'        => (bool) $show_brands,
		'show_subcategories' => (bool) $show_subcats,
		'taxonomy'           => $taxonomy,
		'source'             => 'term:' . $term_id,
	];
}

/**
 * Standard-Konfiguration (Fallback).
 */
function mlwf_get_default_filter_config(): array {
	return [
		'attributes'         => [],
		'show_price'         => true,
		'show_brands'        => false,
		'show_subcategories' => false,
		'taxonomy'           => 'product_cat',
		'source'             => 'default',
	];
}

/**
 * Labels für Attribut-Slugs.
 */
function mlwf_get_attribute_labels(): array {
	$labels = [];

	$attributes = wc_get_attribute_taxonomies();
	foreach ( $attributes as $attr ) {
		$slug   = wc_attribute_taxonomy_name( $attr->attribute_name );
		$custom = class_exists( 'MediaLab_Filter_Settings' ) ? MediaLab_Filter_Settings::attribute_label( $slug ) : '';
		$labels[ $slug ] = $custom !== '' ? $custom : $attr->attribute_label;
	}

	if ( taxonomy_exists( 'product_brand' ) ) {
		$custom = class_exists( 'MediaLab_Filter_Settings' ) ? MediaLab_Filter_Settings::attribute_label( 'product_brand' ) : '';
		if ( $custom === '' && class_exists( 'MediaLab_Filter_Settings' ) ) {
			$custom = MediaLab_Filter_Settings::label( 'brand' );
		}
		$tax_obj = get_taxonomy( 'product_brand' );
		$labels['product_brand'] = $custom !== '' ? $custom : ( $tax_obj->labels->singular_name ?? 'Marke' );
	}

	return $labels;
}

/**
 * Alias für Abwärtskompatibilität.
 */
function janecka_get_attribute_labels(): array {
	return mlwf_get_attribute_labels();
}
