<?php
/**
 * Überschrift (H1) und SEO-Titel je Produktkategorie
 *
 * Zwei optionale Felder an jeder Produktkategorie (ACF am Term):
 *   - „Überschrift (H1)“ (Meta-Schlüssel `mlw_category_h1`): sichtbare Seitenüberschrift.
 *   - „SEO-Titel (Titel-Tag)“ (Meta-Schlüssel `mlw_category_seo_title`): Titel-Tag (<title>).
 * Leer = Name der Kategorie, wie bisher.
 *
 * Die H1 wirkt nur auf die sichtbare Seitenüberschrift der Kategorieseite
 * (WooCommerce gibt sie über woocommerce_page_title() aus). Der SEO-Titel ersetzt den
 * Titelteil des Titel-Tags (Filter document_title_parts); Trennzeichen und Seitenname
 * hängt WordPress wie gewohnt an. Weil das SEO-Plugin den Titel für og:title und
 * twitter:title über wp_get_document_title() liest, folgen diese dem SEO-Titel.
 * Unverändert bleiben:
 *   - der Kategoriename (Menü, Breadcrumbs, Filter, Suche, Produktkarten),
 *   - die Meta-Beschreibung,
 *   - Shop-Seite, Schlagwörter und Marken.
 *
 * Die Einleitung gehört in die Beschreibung der Kategorie: WooCommerce gibt sie
 * auf der ersten Seite des Archivs unter der Überschrift aus
 * (woocommerce_taxonomy_archive_description), das Feld dafür bringt WooCommerce
 * selbst mit. Sie dient außerdem als Meta-Beschreibung (og:description).
 *
 * Lesen funktioniert auch ohne ACF (reiner Term-Meta-Wert); zum Bearbeiten
 * wird ACF Pro gebraucht, wie bei den Filter-Feldern an derselben Kategorie.
 *
 * Filter:
 *   mlw_category_heading( string $ueberschrift, WP_Term $term )
 *     Überschrift anpassen oder ersetzen. Leerer Rückgabewert = Kategoriename.
 *   mlw_category_seo_title( string $titel, WP_Term $term )
 *     SEO-Titel anpassen oder ersetzen. Leerer Rückgabewert = Standardtitel.
 *
 * @package MediaLabWooCommerce
 * @since   2.38.0 (SEO-Titel ebenfalls 2.38.0)
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'MLW_CATEGORY_H1_META' ) ) {
	define( 'MLW_CATEGORY_H1_META', 'mlw_category_h1' );
}

if ( ! defined( 'MLW_CATEGORY_SEO_TITLE_META' ) ) {
	define( 'MLW_CATEGORY_SEO_TITLE_META', 'mlw_category_seo_title' );
}

/**
 * ACF-Feldgruppe an product_cat.
 */
function mlw_register_category_heading_field(): void {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group( [
		'key'    => 'group_mlw_category_heading',
		'title'  => 'Seitenüberschrift (H1)',
		'fields' => [
			[
				'key'          => 'field_mlw_category_h1',
				'label'        => 'Überschrift (H1)',
				'name'         => MLW_CATEGORY_H1_META,
				'type'         => 'text',
				'instructions' => 'Sichtbare Überschrift der Kategorieseite. Leer lassen, um den Namen der Kategorie zu verwenden. '
					. 'Der Name bleibt für Menü, Breadcrumbs, Filter und Suche unverändert. '
					. 'Die Einleitung gehört in die Beschreibung der Kategorie.',
				'wrapper'      => [ 'width' => '100' ],
			],
		],
		'location'              => [
			[ [ 'param' => 'taxonomy', 'operator' => '==', 'value' => 'product_cat' ] ],
		],
		'menu_order'            => 5, // vor der Filter-Konfiguration (10)
		'position'              => 'normal',
		'active'                => true,
		'instruction_placement' => 'label',
	] );
}
add_action( 'acf/init', 'mlw_register_category_heading_field' );

/**
 * Überschrift einer Produktkategorie (leer, wenn keine eigene gepflegt ist).
 *
 * @param WP_Term|mixed $term
 */
function mlw_get_category_heading( $term ): string {
	if ( ! ( $term instanceof WP_Term ) || $term->taxonomy !== 'product_cat' ) {
		return '';
	}

	// Nur Text: Tags entfernen, Leerraum kürzen (die Ausgabe wird zusätzlich escaped).
	$heading = trim( wp_strip_all_tags( (string) get_term_meta( $term->term_id, MLW_CATEGORY_H1_META, true ) ) );

	return trim( (string) apply_filters( 'mlw_category_heading', $heading, $term ) );
}

/**
 * Überschrift der Kategorieseite ersetzen.
 *
 * woocommerce_page_title() gibt den Wert ohne Escaping aus, daher hier esc_html().
 * Der Standardtitel (Kategoriename) bleibt unverändert, solange kein Feld gepflegt ist.
 *
 * @param string $title Standardtitel von WooCommerce (Kategoriename).
 */
function mlw_filter_category_page_title( $title ) {
	if ( ! is_product_category() ) {
		return $title;
	}

	$heading = mlw_get_category_heading( get_queried_object() );

	return $heading === '' ? $title : esc_html( $heading );
}
add_filter( 'woocommerce_page_title', 'mlw_filter_category_page_title', 20 );

/**
 * SEO-Titel einer Produktkategorie (leer, wenn keiner gepflegt ist).
 *
 * @param WP_Term|mixed $term
 */
function mlw_get_category_seo_title( $term ): string {
	if ( ! ( $term instanceof WP_Term ) || $term->taxonomy !== 'product_cat' ) {
		return '';
	}

	// Nur Text: Tags entfernen, Leerraum kürzen (WordPress escaped den Titel bei der Ausgabe selbst).
	$title = trim( wp_strip_all_tags( (string) get_term_meta( $term->term_id, MLW_CATEGORY_SEO_TITLE_META, true ) ) );

	return trim( (string) apply_filters( 'mlw_category_seo_title', $title, $term ) );
}

/**
 * Titelteil des Titel-Tags ersetzen (nur Produktkategorie-Seiten).
 *
 * Trennzeichen, „Seite 2“ und Seitenname bleiben Sache von WordPress.
 *
 * @param array $parts Teile des Titels (title, page, tagline, site).
 */
function mlw_filter_category_document_title( $parts ) {
	if ( ! is_array( $parts ) || ! function_exists( 'is_product_category' ) || ! is_product_category() ) {
		return $parts;
	}

	$title = mlw_get_category_seo_title( get_queried_object() );

	if ( $title !== '' ) {
		$parts['title'] = $title;
	}

	return $parts;
}
add_filter( 'document_title_parts', 'mlw_filter_category_document_title', 20 );
