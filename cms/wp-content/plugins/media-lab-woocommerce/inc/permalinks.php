<?php
/**
 * URL-Basen (Permalinks) für Produkte, Produktkategorien und Marken.
 *
 * Eigene, von Filter- und Inquiry-Einstellungen entkoppelte ACF-Optionsseite
 * (Produkte → URL-Basen). Die Standardwerte (Deutsch) gelten auch dann, wenn auf
 * der Seite noch nie gespeichert wurde: ACF-Standardwerte greifen ohne gespeicherte
 * Option nicht, deshalb werden sie hier selbst angewendet.
 *
 *   Produkt-Basis    produkte           (WooCommerce-Standard: product)
 *   Kategorie-Basis  produkt-kategorie  (WooCommerce-Standard: product-category)
 *   Marken-Basis     marke              (WooCommerce-Standard: brand, in der deutschen
 *                                        Übersetzung mit großem Anfangsbuchstaben: Marke)
 *
 * Mehrsprachigkeit: Der Filter 'mlwc_permalink_bases' erhält das Array
 * [ 'product' => …, 'category' => …, 'brand' => … ] und kann es ändern. Achtung: Ändert
 * sich das Ergebnis von Anfrage zu Anfrage (zum Beispiel je Sprache), würde die Signatur
 * unten jedes Mal neu geschrieben und die Rewrite-Regeln ständig neu aufgebaut. Eine
 * sprachabhängige Lösung braucht deshalb eine eigene Signatur je Sprache.
 *
 * Wirkung:
 *   - Produkt- und Kategorie-Basis überschreiben beim Lesen die WooCommerce-Option
 *     'woocommerce_permalinks'.
 *   - Die Marken-Basis wird über 'register_taxonomy_args' für 'product_brand' gesetzt.
 *   - Ändert sich eine Basis, werden die Rewrite-Regeln beim nächsten Seitenaufruf einmal
 *     neu geschrieben. Bestehende Adressen ändern sich, es gibt keine Weiterleitungen.
 *
 * @package MediaLabWooCommerce
 */

defined( 'ABSPATH' ) || exit;

class MediaLab_Permalinks {

	const OPTION_PAGE_SLUG = 'mlwc-permalinks';
	const SIGNATURE_OPTION = 'mlwc_permalinks_signature';

	/** Standardwerte. Gelten auch, wenn nichts gespeichert ist. */
	const DEFAULTS = [
		'product'  => 'produkte',
		'category' => 'produkt-kategorie',
		'brand'    => 'marke',
	];

	public static function init(): void {
		add_action( 'acf/include_fields', [ __CLASS__, 'register_options_page' ] );
		add_filter( 'option_woocommerce_permalinks', [ __CLASS__, 'filter_wc_permalinks' ] );
		add_filter( 'default_option_woocommerce_permalinks', [ __CLASS__, 'filter_wc_permalinks' ] );
		add_filter( 'register_taxonomy_args', [ __CLASS__, 'filter_brand_taxonomy' ], 10, 2 );
		add_action( 'init', [ __CLASS__, 'maybe_flush_rewrite_rules' ], 99 );
	}

	// ── Werte ───────────────────────────────────────────────────────────────

	/**
	 * Wirksame Basen: gespeicherter Wert, sonst Standard.
	 *
	 * @return array{product: string, category: string, brand: string}
	 */
	public static function bases(): array {
		$bases = [];
		foreach ( self::DEFAULTS as $key => $default ) {
			// ACF speichert Werte von Optionsseiten als 'options_<name>'.
			$stored        = get_option( 'options_mlwc_pl_' . $key . '_base', '' );
			$slug          = self::clean( $stored );
			$bases[ $key ] = $slug !== '' ? $slug : $default;
		}
		return (array) apply_filters( 'mlwc_permalink_bases', $bases );
	}

	/** Nur Kleinbuchstaben, Ziffern und Bindestriche, ein Segment, ohne Schrägstriche am Rand. */
	private static function clean( $value ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}
		return sanitize_title( trim( $value, " \t\n\r\0\x0B/" ) );
	}

	// ── Wirkung auf WooCommerce ─────────────────────────────────────────────

	/**
	 * @param mixed $value Gelesener Wert von 'woocommerce_permalinks' (oder false, wenn es ihn noch nicht gibt).
	 */
	public static function filter_wc_permalinks( $value ) {
		$value                  = is_array( $value ) ? $value : [];
		$bases                  = self::bases();
		$value['product_base']  = $bases['product'];
		$value['category_base'] = $bases['category'];
		return $value;
	}

	public static function filter_brand_taxonomy( array $args, string $taxonomy ): array {
		if ( $taxonomy !== 'product_brand' ) {
			return $args;
		}
		$rewrite         = isset( $args['rewrite'] ) && is_array( $args['rewrite'] ) ? $args['rewrite'] : [];
		$rewrite['slug'] = self::bases()['brand'];
		$args['rewrite'] = $rewrite;
		return $args;
	}

	/** Schreibt die Rewrite-Regeln einmal neu, wenn sich die Basen gegenüber dem letzten Stand geändert haben. */
	public static function maybe_flush_rewrite_rules(): void {
		$signature = md5( (string) wp_json_encode( self::bases() ) );
		if ( get_option( self::SIGNATURE_OPTION ) === $signature ) {
			return;
		}
		flush_rewrite_rules( false );
		update_option( self::SIGNATURE_OPTION, $signature, false );
	}

	// ── ACF Options Page ────────────────────────────────────────────────────

	public static function register_options_page(): void {
		if ( ! function_exists( 'acf_add_options_sub_page' ) ) return;

		acf_add_options_sub_page( [
			'page_title'  => 'URL-Basen',
			'menu_title'  => 'URL-Basen',
			'parent_slug' => 'edit.php?post_type=product',
			'capability'  => 'manage_woocommerce',
			'menu_slug'   => self::OPTION_PAGE_SLUG,
			'autoload'    => true,
		] );

		if ( ! function_exists( 'acf_add_local_field_group' ) ) return;

		acf_add_local_field_group( [
			'key'      => 'group_mlwc_permalinks',
			'title'    => 'URL-Basen',
			'location' => [ [ [ 'param' => 'options_page', 'operator' => '==', 'value' => self::OPTION_PAGE_SLUG ] ] ],
			'fields'   => [
				[
					'key'       => 'field_mlwc_pl_info',
					'label'     => 'Hinweis',
					'name'      => '',
					'type'      => 'message',
					'message'   => 'Der erste Teil der Adresse. Nur Kleinbuchstaben, Ziffern und Bindestriche, ohne Schrägstriche. Ist ein Feld leer, gilt der Standardwert. Nach dem Speichern werden die Adressen beim nächsten Seitenaufruf neu aufgebaut. Bestehende Adressen ändern sich, es gibt keine Weiterleitungen: vor dem Livegang einstellen.',
					'new_lines' => 'wpautop',
				],
				[
					'key'           => 'field_mlwc_pl_product_base',
					'label'         => 'Produkt-Basis',
					'name'          => 'mlwc_pl_product_base',
					'type'          => 'text',
					'default_value' => self::DEFAULTS['product'],
					'placeholder'   => self::DEFAULTS['product'],
					'instructions'  => 'Ergibt zum Beispiel /produkte/mein-produkt/.',
					'wrapper'       => [ 'width' => '33' ],
				],
				[
					'key'           => 'field_mlwc_pl_category_base',
					'label'         => 'Kategorie-Basis',
					'name'          => 'mlwc_pl_category_base',
					'type'          => 'text',
					'default_value' => self::DEFAULTS['category'],
					'placeholder'   => self::DEFAULTS['category'],
					'instructions'  => 'Ergibt zum Beispiel /produkt-kategorie/shirts/.',
					'wrapper'       => [ 'width' => '33' ],
				],
				[
					'key'           => 'field_mlwc_pl_brand_base',
					'label'         => 'Marken-Basis',
					'name'          => 'mlwc_pl_brand_base',
					'type'          => 'text',
					'default_value' => self::DEFAULTS['brand'],
					'placeholder'   => self::DEFAULTS['brand'],
					'instructions'  => 'Ergibt zum Beispiel /marke/keya/.',
					'wrapper'       => [ 'width' => '33' ],
				],
			],
		] );
	}
}
