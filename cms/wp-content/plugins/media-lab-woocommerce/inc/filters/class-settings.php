<?php
/**
 * Einstellungen für das Produktfilter-System (Wording, Attribut-Labels & Mehrsprachigkeit).
 *
 * EIGENE, von den Inquiry-Settings entkoppelte ACF-Optionsseite - Filter-
 * System und Inquiry-Engine sollen unabhängig voneinander funktionieren
 * (siehe Architektur-Hinweis in README.md).
 *
 * Verwendung im Code:
 *   MediaLab_Filter_Settings::label( 'price' | 'category' | 'brand' | 'reset' | 'loading' | 'no_products' )
 *   MediaLab_Filter_Settings::attribute_label( $attr_slug )   // '' wenn nicht konfiguriert
 *
 * @package MedialabWooFilters
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MediaLab_Filter_Settings {

	const OPTION_PAGE_SLUG = 'mlwf-filter-settings';

	public static function init(): void {
		add_action( 'acf/include_fields', [ __CLASS__, 'register_options_page' ] );
	}

	// ── ACF Options Page ────────────────────────────────────────────────────

	public static function register_options_page(): void {
		if ( ! function_exists( 'acf_add_options_page' ) ) return;

		acf_add_options_sub_page( [
			'page_title'  => 'Filter-Einstellungen',
			'menu_title'  => 'Filter-Einstellungen',
			'parent_slug' => 'edit.php?post_type=product',
			'capability'  => 'manage_woocommerce',
			'menu_slug'   => self::OPTION_PAGE_SLUG,
			'autoload'    => true,
		] );

		if ( ! function_exists( 'acf_add_local_field_group' ) ) return;

		acf_add_local_field_group( [
			'key'      => 'group_mlwf_filter_settings',
			'title'    => 'Filter-Einstellungen',
			'location' => [ [ [ 'param' => 'options_page', 'operator' => '==', 'value' => self::OPTION_PAGE_SLUG ] ] ],
			'fields'   => array_merge(
				self::fields_wording(),
				self::fields_attribute_labels(),
				self::fields_languages()
			),
		] );
	}

	// ── Tab: Wording (einsprachiger Fallback) ───────────────────────────────

	private static function fields_wording(): array {
		$hide_if_multilang = [ [ [ 'field' => 'field_mlwf_multilang_enabled', 'operator' => '!=', 'value' => '1' ] ] ];

		return [
			[ 'key' => 'field_mlwf_tab_wording', 'label' => 'Wording', 'name' => '', 'type' => 'tab' ],
			[
				'key'     => 'field_mlwf_wording_flat_info',
				'label'   => '',
				'name'    => '',
				'type'    => 'message',
				'message' => '<p><em>Einsprachige Fallback-Texte. Nur relevant, wenn Mehrsprachigkeit im Tab "Sprachen" deaktiviert ist.</em></p>',
			],
			[ 'key' => 'field_mlwf_label_price',       'label' => 'Preis-Label',        'name' => 'mlwf_label_price',       'type' => 'text', 'default_value' => 'Preis',                     'conditional_logic' => $hide_if_multilang, 'wrapper' => [ 'width' => '50' ] ],
			[ 'key' => 'field_mlwf_label_category',    'label' => 'Kategorie-Label',    'name' => 'mlwf_label_category',    'type' => 'text', 'default_value' => 'Kategorie',                'instructions' => 'Für den Unterkategorie-Filter.', 'conditional_logic' => $hide_if_multilang, 'wrapper' => [ 'width' => '50' ] ],
			[ 'key' => 'field_mlwf_label_brand',       'label' => 'Marke-Label',        'name' => 'mlwf_label_brand',       'type' => 'text', 'default_value' => 'Marke',                    'instructions' => 'Fallback, falls im Tab "Attribut-Labels" kein eigenes Label für product_brand gesetzt ist.', 'conditional_logic' => $hide_if_multilang, 'wrapper' => [ 'width' => '50' ] ],
			[ 'key' => 'field_mlwf_label_reset',       'label' => 'Zurücksetzen-Label', 'name' => 'mlwf_label_reset',       'type' => 'text', 'default_value' => 'Zurücksetzen',             'conditional_logic' => $hide_if_multilang, 'wrapper' => [ 'width' => '50' ] ],
			[ 'key' => 'field_mlwf_label_loading',     'label' => 'Lade-Text (JS)',     'name' => 'mlwf_label_loading',     'type' => 'text', 'default_value' => 'Produkte werden geladen …', 'conditional_logic' => $hide_if_multilang, 'wrapper' => [ 'width' => '50' ] ],
			[ 'key' => 'field_mlwf_label_no_products', 'label' => 'Leer-Text (JS)',     'name' => 'mlwf_label_no_products', 'type' => 'text', 'default_value' => 'Keine Produkte gefunden.', 'conditional_logic' => $hide_if_multilang, 'wrapper' => [ 'width' => '50' ] ],
		];
	}

	// ── Tab: Attribut-Labels (einsprachiger Fallback) ───────────────────────

	private static function fields_attribute_labels(): array {
		$hide_if_multilang = [ [ [ 'field' => 'field_mlwf_multilang_enabled', 'operator' => '!=', 'value' => '1' ] ] ];

		return [
			[ 'key' => 'field_mlwf_tab_attr_labels', 'label' => 'Attribut-Labels', 'name' => '', 'type' => 'tab' ],
			[
				'key'     => 'field_mlwf_attr_labels_info',
				'label'   => '',
				'name'    => '',
				'type'    => 'message',
				'message' => '<p>Überschreibt die Anzeige-Bezeichnung eines Attributs nur in der Filter-Bar (z.B. ein kürzeres Storefront-Label) - ohne das WooCommerce-Attribut selbst umzubenennen. Zeile leer/weglassen → Standard-Label des Attributs wird verwendet.</p>',
			],
			[
				'key'               => 'field_mlwf_attribute_labels',
				'label'             => 'Attribut-Labels',
				'name'              => 'mlwf_attribute_labels',
				'type'              => 'repeater',
				'min'               => 0,
				'layout'            => 'table',
				'button_label'      => 'Label hinzufügen',
				'conditional_logic' => $hide_if_multilang,
				'sub_fields'        => [
					[ 'key' => 'field_mlwf_attr_slug',         'label' => 'Attribut',      'name' => 'attr_slug',  'type' => 'select', 'choices' => self::attribute_slug_choices(), 'wrapper' => [ 'width' => '40' ] ],
					[ 'key' => 'field_mlwf_attr_custom_label', 'label' => 'Anzeige-Label', 'name' => 'attr_label', 'type' => 'text',   'wrapper' => [ 'width' => '60' ] ],
				],
			],
		];
	}

	// ── Tab: Sprachen ────────────────────────────────────────────────────────

	private static function fields_languages(): array {
		return [
			[ 'key' => 'field_mlwf_tab_languages', 'label' => 'Sprachen', 'name' => '', 'type' => 'tab' ],
			[
				'key'     => 'field_mlwf_multilang_info',
				'label'   => '',
				'name'    => '',
				'type'    => 'message',
				'message' => '<p>Aktiviere Mehrsprachigkeit, um Wording und Attribut-Labels je Sprache zu pflegen. Spracherkennung: Polylang → WPML → WP-Locale-Fallback - funktioniert unabhängig davon, welches (oder ob überhaupt ein) Mehrsprachigkeits-Plugin installiert ist. Die erste Zeile gilt als Fallback-Sprache.</p>',
			],
			[
				'key'           => 'field_mlwf_multilang_enabled',
				'label'         => 'Mehrsprachigkeit aktivieren',
				'name'          => 'mlwf_multilang_enabled',
				'type'          => 'true_false',
				'ui'            => 1,
				'default_value' => 0,
			],
			[
				'key'               => 'field_mlwf_languages',
				'label'             => 'Sprachen',
				'name'              => 'mlwf_languages',
				'type'              => 'repeater',
				'min'               => 0,
				'layout'            => 'block',
				'button_label'      => 'Sprache hinzufügen',
				'instructions'      => 'Sprachcodes: de, en, fr, it, es, …',
				'conditional_logic' => [ [ [ 'field' => 'field_mlwf_multilang_enabled', 'operator' => '==', 'value' => '1' ] ] ],
				'sub_fields'        => [
					[ 'key' => 'field_mlwf_lang_code', 'label' => 'Sprachcode',  'name' => 'lang_code', 'type' => 'text', 'required' => 1, 'placeholder' => 'de', 'wrapper' => [ 'width' => '20' ] ],
					[ 'key' => 'field_mlwf_lang_name', 'label' => 'Bezeichnung', 'name' => 'lang_name', 'type' => 'text', 'placeholder' => 'Deutsch', 'instructions' => 'Nur zur internen Orientierung.', 'wrapper' => [ 'width' => '20' ] ],

					[ 'key' => 'field_mlwf_lang_sep_wording', 'label' => ' ', 'name' => '', 'type' => 'message', 'message' => '<strong style="font-size:12px;color:#555;">Wording</strong>' ],
					[ 'key' => 'field_mlwf_lang_label_price',       'label' => 'Preis-Label',        'name' => 'label_price',       'type' => 'text', 'placeholder' => 'Preis',                     'wrapper' => [ 'width' => '33' ] ],
					[ 'key' => 'field_mlwf_lang_label_category',    'label' => 'Kategorie-Label',    'name' => 'label_category',    'type' => 'text', 'placeholder' => 'Kategorie',                 'wrapper' => [ 'width' => '33' ] ],
					[ 'key' => 'field_mlwf_lang_label_brand',       'label' => 'Marke-Label',        'name' => 'label_brand',       'type' => 'text', 'placeholder' => 'Marke',                     'wrapper' => [ 'width' => '33' ] ],
					[ 'key' => 'field_mlwf_lang_label_reset',       'label' => 'Zurücksetzen-Label', 'name' => 'label_reset',       'type' => 'text', 'placeholder' => 'Zurücksetzen',              'wrapper' => [ 'width' => '33' ] ],
					[ 'key' => 'field_mlwf_lang_label_loading',     'label' => 'Lade-Text (JS)',     'name' => 'label_loading',     'type' => 'text', 'placeholder' => 'Produkte werden geladen …', 'wrapper' => [ 'width' => '33' ] ],
					[ 'key' => 'field_mlwf_lang_label_no_products', 'label' => 'Leer-Text (JS)',     'name' => 'label_no_products', 'type' => 'text', 'placeholder' => 'Keine Produkte gefunden.',  'wrapper' => [ 'width' => '33' ] ],

					[ 'key' => 'field_mlwf_lang_sep_attrs', 'label' => ' ', 'name' => '', 'type' => 'message', 'message' => '<strong style="font-size:12px;color:#555;">Attribut-Labels</strong>' ],
					[
						'key'          => 'field_mlwf_lang_attribute_labels',
						'label'        => 'Attribut-Labels',
						'name'         => 'attribute_labels',
						'type'         => 'repeater',
						'min'          => 0,
						'layout'       => 'table',
						'button_label' => 'Label hinzufügen',
						'sub_fields'   => [
							[ 'key' => 'field_mlwf_lang_attr_slug',  'label' => 'Attribut',      'name' => 'attr_slug',  'type' => 'select', 'choices' => self::attribute_slug_choices(), 'wrapper' => [ 'width' => '40' ] ],
							[ 'key' => 'field_mlwf_lang_attr_label', 'label' => 'Anzeige-Label', 'name' => 'attr_label', 'type' => 'text',   'wrapper' => [ 'width' => '60' ] ],
						],
					],
				],
			],
		];
	}

	/**
	 * Alle verfügbaren WooCommerce-Produktattribute + product_brand als Choices.
	 * Gleiche Quelle wie mlwf_get_acf_attribute_choices() in acf-fields.php,
	 * bewusst separat gehalten (dort ohne product_brand, hier mit - andere
	 * Verwendungszwecke: dort Filter-Auswahl pro Kategorie, hier Label-Overrides).
	 */
	private static function attribute_slug_choices(): array {
		$choices = [];

		if ( function_exists( 'wc_get_attribute_taxonomies' ) ) {
			foreach ( wc_get_attribute_taxonomies() as $attr ) {
				$slug             = wc_attribute_taxonomy_name( $attr->attribute_name );
				$choices[ $slug ] = $attr->attribute_label . ' (' . $slug . ')';
			}
		}

		if ( taxonomy_exists( 'product_brand' ) ) {
			$choices['product_brand'] = 'Marke (product_brand)';
		}

		return $choices;
	}

	// ── Getter für Verwendung im Code ───────────────────────────────────────

	/**
	 * Liefert ein UI-Label, sprachabhängig (falls Mehrsprachigkeit aktiv).
	 *
	 * @param string $key price|category|brand|reset|loading|no_products
	 */
	public static function label( string $key ): string {
		$defaults = [
			'price'       => 'Preis',
			'category'    => 'Kategorie',
			'brand'       => 'Marke',
			'reset'       => 'Zurücksetzen',
			'loading'     => 'Produkte werden geladen …',
			'no_products' => 'Keine Produkte gefunden.',
		];
		$default = $defaults[ $key ] ?? '';

		if ( ! function_exists( 'get_field' ) ) return $default;

		if ( MediaLab_Filter_I18n::multilang_enabled() ) {
			$rows = get_field( 'mlwf_languages', 'option' );
			$row  = MediaLab_Filter_I18n::resolve_row( is_array( $rows ) ? $rows : [], 'lang_code' );
			$val  = $row[ 'label_' . $key ] ?? '';
			return $val !== '' ? $val : $default;
		}

		$val = get_field( 'mlwf_label_' . $key, 'option' );
		return ( $val !== '' && $val !== null ) ? $val : $default;
	}

	/**
	 * Eigenes Anzeige-Label für ein Attribut/eine Taxonomie (z.B. 'pa_farbe',
	 * 'product_brand'), sprachabhängig. Gibt '' zurück, wenn nichts
	 * konfiguriert ist - der Aufrufer verwendet dann sein eigenes Fallback
	 * (z.B. das native WooCommerce-Attribut-Label).
	 */
	public static function attribute_label( string $slug ): string {
		if ( ! function_exists( 'get_field' ) ) return '';

		if ( MediaLab_Filter_I18n::multilang_enabled() ) {
			$rows = get_field( 'mlwf_languages', 'option' );
			$row  = MediaLab_Filter_I18n::resolve_row( is_array( $rows ) ? $rows : [], 'lang_code' );
			$list = $row['attribute_labels'] ?? [];
		} else {
			$list = get_field( 'mlwf_attribute_labels', 'option' );
		}

		if ( ! is_array( $list ) ) return '';

		foreach ( $list as $row ) {
			if ( ( $row['attr_slug'] ?? '' ) === $slug ) {
				return trim( (string) ( $row['attr_label'] ?? '' ) );
			}
		}

		return '';
	}
}
