<?php
/**
 * ACF Feldgruppen: Produktfilter-Konfiguration
 *
 * Registriert Felder auf product_cat und product_brand Taxonomien.
 *
 * @package MedialabWooFilters
 */

defined( 'ABSPATH' ) || exit;

add_action( 'acf/init', 'mlwf_register_acf_field_groups' );

function mlwf_register_acf_field_groups(): void {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) return;

	$attribute_choices = mlwf_get_acf_attribute_choices();

	// ── Feldgruppe für product_cat ────────────────────────────────────────────
	acf_add_local_field_group( [
		'key'    => 'group_mlwf_category_filters',
		'title'  => 'Produktfilter-Konfiguration',
		'fields' => mlwf_get_filter_fields( $attribute_choices, 'product_cat' ),
		'location' => [
			[ [ 'param' => 'taxonomy', 'operator' => '==', 'value' => 'product_cat' ] ],
		],
		'menu_order'            => 10,
		'position'              => 'normal',
		'active'                => true,
		'instruction_placement' => 'label',
	] );

	// ── Feldgruppe für product_brand ──────────────────────────────────────────
	if ( taxonomy_exists( 'product_brand' ) ) {
		acf_add_local_field_group( [
			'key'    => 'group_mlwf_brand_filters',
			'title'  => 'Produktfilter-Konfiguration',
			'fields' => mlwf_get_filter_fields( $attribute_choices, 'product_brand' ),
			'location' => [
				[ [ 'param' => 'taxonomy', 'operator' => '==', 'value' => 'product_brand' ] ],
			],
			'menu_order'            => 10,
			'position'              => 'normal',
			'active'                => true,
			'instruction_placement' => 'label',
		] );
	}
}

/**
 * Gemeinsame Felder für beide Taxonomien.
 * Feldschlüssel haben Taxonomie-Prefix um Konflikte zu vermeiden.
 */
function mlwf_get_filter_fields( array $attribute_choices, string $taxonomy ): array {
	$prefix = $taxonomy === 'product_brand' ? 'brand' : 'cat';

	return [
		// Vererbung
		[
			'key'           => 'field_mlwf_' . $prefix . '_inherit',
			'label'         => 'Filter von Elternkategorie übernehmen',
			'name'          => 'mlwf_inherit_parent',
			'type'          => 'true_false',
			'default_value' => 1,
			'ui'            => 1,
			'ui_on_text'    => 'Ja',
			'ui_off_text'   => 'Nein',
			'instructions'  => 'Wenn aktiviert, werden die Filter der übergeordneten Ebene verwendet.',
			'wrapper'       => [ 'width' => '100' ],
		],

		// Hinweis
		[
			'key'     => 'field_mlwf_' . $prefix . '_hint',
			'label'   => 'Eigene Filter-Konfiguration',
			'type'    => 'message',
			'message' => '<p style="margin:0;color:#555;">Die folgenden Einstellungen gelten nur wenn "Übernehmen" deaktiviert ist — oder wenn dies die oberste Ebene ist.</p>',
		],

		// Preis-Slider
		[
			'key'           => 'field_mlwf_' . $prefix . '_show_price',
			'label'         => 'Preis-Filter anzeigen',
			'name'          => 'mlwf_show_price',
			'type'          => 'true_false',
			'default_value' => 1,
			'ui'            => 1,
			'ui_on_text'    => 'Ja',
			'ui_off_text'   => 'Nein',
			'wrapper'       => [ 'width' => '33' ],
		],

		// Marken-Filter (nur auf product_cat sinnvoll)
		[
			'key'           => 'field_mlwf_' . $prefix . '_show_brands',
			'label'         => 'Marken-Filter anzeigen',
			'name'          => 'mlwf_show_brands',
			'type'          => 'true_false',
			'default_value' => 0,
			'ui'            => 1,
			'ui_on_text'    => 'Ja',
			'ui_off_text'   => 'Nein',
			'wrapper'       => [ 'width' => '33' ],
		],

		// Unterkategorie-Filter
		[
			'key'           => 'field_mlwf_' . $prefix . '_show_subcats',
			'label'         => 'Unterkategorie-Filter anzeigen',
			'name'          => 'mlwf_show_subcategories',
			'type'          => 'true_false',
			'default_value' => 0,
			'ui'            => 1,
			'ui_on_text'    => 'Ja',
			'ui_off_text'   => 'Nein',
			'instructions'  => 'Zeigt direkte Unterkategorien als Filter (nur sinnvoll auf Elternebenen).',
			'wrapper'       => [ 'width' => '33' ],
		],

		// Attribute
		[
			'key'          => 'field_mlwf_' . $prefix . '_attributes',
			'label'        => 'Produkt-Attribute',
			'name'         => 'mlwf_attributes',
			'type'         => 'checkbox',
			'choices'      => $attribute_choices,
			'layout'       => 'vertical',
			'toggle'       => 1,
			'instructions' => 'Welche Produktattribute sollen als Filter angezeigt werden?',
			'wrapper'      => [ 'width' => '100' ],
		],

		// Reihenfolge
		[
			'key'          => 'field_mlwf_' . $prefix . '_order',
			'label'        => 'Reihenfolge der Filter',
			'name'         => 'mlwf_filter_order',
			'type'         => 'textarea',
			'rows'         => 4,
			'instructions' => 'Optional: Einen Attribut-Slug pro Zeile, in gewünschter Reihenfolge. Nicht aufgeführte Filter werden dahinter angehängt.<br>Beispiel:<br><code>pa_filter-material</code><br><code>pa_filter-stein</code>',
			'wrapper'      => [ 'width' => '100' ],
		],
	];
}

/**
 * Alle verfügbaren WooCommerce-Produktattribute als ACF Choices.
 */
function mlwf_get_acf_attribute_choices(): array {
	$choices    = [];
	$attributes = wc_get_attribute_taxonomies();

	foreach ( $attributes as $attr ) {
		$slug             = wc_attribute_taxonomy_name( $attr->attribute_name );
		$choices[ $slug ] = $attr->attribute_label . ' (' . $slug . ')';
	}

	return $choices;
}
