<?php
/**
 * Einzelprodukt-Layout und Produktkarten-Infos (Opt-in).
 *
 * Alles ist standardmäßig AUS, bestehende Projekte ändern sich beim Plugin-Update nicht.
 * Einschalten im Theme (functions.php) oder Projekt-Plugin:
 *
 *   add_filter( 'mlw_single_product_layout', '__return_true' );
 *       Summary-Reihenfolge: Meta, Badge, Titel, Beschreibung, Lagerstatus, Mengenfeld/Anfrage-Button,
 *       Preis. Der Tab "Beschreibung" entfällt, body-Klasse mlw-single-layout.
 *       Konfigurierbare Produkte (Wizard): Beschreibung und Lagerstatus stehen direkt unter dem Titel,
 *       vor der Konfigurator-Karte; die Karte selbst bleibt unverändert.
 *   add_filter( 'mlw_loop_product_info', '__return_true' );
 *       Produktkarten: Marke über dem Titel, Verfügbarkeit neben dem Preis.
 *   add_filter( 'mlw_loop_brand', '__return_true' );
 *       Produktkarten: nur die Marke über dem Titel, ohne Verfügbarkeit (für Projekte, die die Verfügbarkeit selbst
 *       ausgeben). Standard: der Wert von mlw_loop_product_info, dieser Schalter allein ändert also nichts.
 *       Mit mlw_loop_product_info an und mlw_loop_brand auf false: nur die Verfügbarkeit.
 *   add_filter( 'mlw_single_brand', '__return_true' );
 *       Produktseite: Marke als Link auf das Marken-Archiv, vor dem Titel (Priorität 4).
 *   add_filter( 'mlw_search_card_brand', '__return_true' );
 *       Karten der Suchergebnisseite (Produkte): „Marke: …“ als erste Meta-Angabe der Karte. Läuft über den Filter
 *       ml_search_card_meta des Themes (Priorität 20, also nach den Meta-Angaben eines Projekts). Das Theme escaped den Wert.
 *   add_filter( 'mlw_loop_sku', '__return_true' );
 *       Produktkarten (Shop, Kategorie, Marke): Artikelnummer („Art.-Nr.: …“) in der Zeile unter dem Preis, links. Der Wert ist
 *       über den Filter mlw_loop_sku_value anpassbar (Standard: SKU des Produkts, ohne SKU keine Ausgabe).
 *   add_filter( 'mlw_loop_category', '__return_true' );
 *       Produktkarten: alle Kategorien des Produkts als Text, durch Komma getrennt, in einer eigenen Zeile unter der Artikelnummer.
 *       Gleiche Namen erscheinen nur einmal, die WooCommerce-Standardkategorie wird übersprungen. Die Liste lässt sich über den
 *       Filter mlw_loop_category_terms ändern (Array von WP_Term). Beide Schalter sind unabhängig von mlw_loop_product_info, Standard aus.
 *   add_filter( 'mlw_loop_availability', function ( $data, $product ) { ... return [ 'label' => '', 'status' => '' ]; }, 10, 2 );
 *       Verfügbarkeits-Text und -Status der Karte überschreiben (leeres Label = keine Ausgabe).
 *   add_filter( 'mlw_reviews_always_open', '__return_true' );
 *       Bewertungen für alle Produkte offen (Importe legen Produkte oft mit geschlossenen Kommentaren an).
 *       Achtung: Moderation prüfen (Einstellungen > Diskussion).
 *
 * Wunschliste (inc/wishlist/class-frontend.php):
 *   add_filter( 'mlw_wishlist_single_quantity', '__return_true' );        // Mengenfeld vor dem Button
 *   add_filter( 'mlw_wishlist_single_button_style', fn() => 'text' );     // icon | text | icon_text
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MediaLab_Single_Product_Layout {

    /** Produkte, deren Lagerstatus bereits oben (vor der Konfigurator-Karte) ausgegeben wurde. */
    private static array $stock_done = [];

    public static function init(): void {
        add_action( 'init', [ __CLASS__, 'register' ], 20 );
        add_filter( 'body_class', [ __CLASS__, 'body_class' ] );
        add_filter( 'comments_open', [ __CLASS__, 'reviews_open' ], 10, 2 );
    }

    private static function layout_enabled(): bool {
        return (bool) apply_filters( 'mlw_single_product_layout', false );
    }

    public static function register(): void {
        // Einheit hinter der Zahl im Lagertext ("250 Stück vorrätig"), Produktseite und Karte
        add_filter( 'woocommerce_get_availability_text', [ __CLASS__, 'append_stock_unit' ], 20, 2 );

        if ( self::layout_enabled() ) {
            // Meta (WooCommerce-Standard: Prio 40) ganz nach oben
            remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40 );
            add_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_meta', 1 );
            add_action( 'woocommerce_single_product_summary', [ __CLASS__, 'render_description' ], 6 );
            add_action( 'woocommerce_single_product_summary', [ __CLASS__, 'render_configurable_stock' ], 7 );
            add_filter( 'woocommerce_get_stock_html', [ __CLASS__, 'dedupe_stock' ], 10, 2 );
            add_action( 'woocommerce_single_product_summary', [ __CLASS__, 'move_price' ], 9 );
            add_filter( 'woocommerce_product_tabs', [ __CLASS__, 'filter_tabs' ], 98 );
        }

        $loop_info  = (bool) apply_filters( 'mlw_loop_product_info', false );
        $loop_brand = (bool) apply_filters( 'mlw_loop_brand', $loop_info );   // nur die Marke, Standard wie mlw_loop_product_info
        if ( $loop_brand ) {
            add_action( 'woocommerce_shop_loop_item_title', [ __CLASS__, 'render_loop_brand' ], 9 );
        }
        if ( $loop_info ) {
            add_action( 'woocommerce_after_shop_loop_item_title', [ __CLASS__, 'render_loop_availability' ], 11 );
        }

        // Artikelnummer und Kategorie unter Preis und Verfügbarkeit (Opt-in, unabhängig voneinander)
        if ( apply_filters( 'mlw_loop_sku', false ) ) {
            add_action( 'woocommerce_after_shop_loop_item_title', [ __CLASS__, 'render_loop_sku' ], 12 );
        }
        if ( apply_filters( 'mlw_loop_category', false ) ) {
            add_action( 'woocommerce_after_shop_loop_item_title', [ __CLASS__, 'render_loop_category' ], 13 );
        }

        if ( apply_filters( 'mlw_single_brand', false ) ) {
            add_action( 'woocommerce_single_product_summary', [ __CLASS__, 'render_single_brand' ], 4 );
        }

        if ( apply_filters( 'mlw_search_card_brand', false ) ) {
            add_filter( 'ml_search_card_meta', [ __CLASS__, 'search_card_meta' ], 20, 3 );
        }
    }

    public static function body_class( array $classes ): array {
        if ( function_exists( 'is_product' ) && is_product() && self::layout_enabled() ) {
            $classes[] = 'mlw-single-layout';
        }
        return $classes;
    }

    private static function is_configurable( int $product_id ): bool {
        return function_exists( 'get_field' ) && (bool) get_field( 'is_configurable', $product_id );
    }

    /** Beschreibung im Summary (unter dem Titel), auch bei konfigurierbaren Produkten (vor der Karte). */
    public static function render_description(): void {
        global $product;
        if ( ! $product instanceof WC_Product ) return;

        $desc = $product->get_description();
        if ( $desc === '' ) return;

        echo '<div class="mlw-product-description">' . wp_kses_post( wpautop( wptexturize( do_shortcode( $desc ) ) ) ) . '</div>';
    }

    /**
     * Preis hinter Mengenfeld/Anfrage-Button (Prio 31) schieben. Verschoben wird erst bei Prio 9
     * und nur, wenn er noch bei 10 hängt: Catalog Mode und Konfigurator entfernen ihn selbst bei 10.
     */
    public static function move_price(): void {
        if ( has_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price' ) === 10 ) {
            remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 10 );
            add_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 32 );
        }
    }

    /**
     * Konfigurierbare Produkte: Der Wizard schliesst das Summary-Element vor den spaeteren Hooks, der
     * Lagerstatus (Prio 30) landet dadurch unter der Galerie statt neben dem Titel. Hier wird er direkt
     * unter dem Titel ausgegeben, die spaetere Ausgabe unterdrueckt dedupe_stock().
     */
    public static function render_configurable_stock(): void {
        global $product;
        if ( ! $product instanceof WC_Product || ! self::is_configurable( $product->get_id() ) ) return;

        $html = wc_get_stock_html( $product );
        if ( $html === '' ) return;

        self::$stock_done[ $product->get_id() ] = true;
        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput -- WooCommerce-Template, bereits escaped
    }

    /** Verhindert die zweite Ausgabe des Lagerstatus, wenn er oben schon steht. */
    public static function dedupe_stock( $html, $product ) {
        if ( $product instanceof WC_Product && ! empty( self::$stock_done[ $product->get_id() ] ) ) return '';
        return $html;
    }

    /**
     * Mengeneinheit hinter der Zahl im Lagertext: "250 vorrätig" -> "250 Stück vorrätig".
     * Quelle: Germanized-Einheit am Produkt (Variationen erben vom Produkt), sonst Filter
     * mlw_stock_unit( $einheit, $produkt ), damit Projekte ohne Germanized die Einheit selbst liefern koennen.
     * Abschalten: add_filter( 'mlw_stock_unit_enabled', '__return_false' ).
     *
     * @param mixed $text    Lagertext aus WC_Product::get_availability_text()
     * @param mixed $product WC_Product
     */
    public static function append_stock_unit( $text, $product ) {
        if ( ! is_string( $text ) || $text === '' || ! $product instanceof WC_Product ) return $text;
        if ( ! apply_filters( 'mlw_stock_unit_enabled', true ) ) return $text;
        if ( ! preg_match( '/\d/', $text ) ) return $text; // "Auf Lager", "Nicht vorrätig": keine Zahl, keine Einheit

        $unit = '';
        if ( function_exists( 'wc_gzd_get_product' ) ) {
            $gzd = wc_gzd_get_product( $product );
            if ( $gzd && method_exists( $gzd, 'get_unit_name' ) ) {
                $unit = (string) $gzd->get_unit_name();
            }
            if ( $unit === '' && $gzd && method_exists( $gzd, 'get_unit' ) ) {
                $slug = (string) $gzd->get_unit();
                if ( $slug !== '' ) {
                    $term = get_term_by( 'slug', $slug, 'product_unit' );
                    $unit = ( $term && ! is_wp_error( $term ) ) ? $term->name : $slug;
                }
            }
        }

        $unit = trim( (string) apply_filters( 'mlw_stock_unit', $unit, $product ) );
        if ( $unit === '' ) return $text;

        // Einheit direkt hinter die erste Zahl (auch 1.250 oder 1,5)
        return preg_replace_callback( '/\d+(?:[.,]\d+)*/u', static function ( $m ) use ( $unit ) {
            return $m[0] . ' ' . $unit;
        }, $text, 1 );
    }

    /** Tab "Beschreibung" entfaellt, die Beschreibung steht im Summary (gilt auch fuer konfigurierbare Produkte). */
    public static function filter_tabs( array $tabs ): array {
        unset( $tabs['description'] );
        return $tabs;
    }

    public static function reviews_open( $open, $post_id ) {
        if ( ! apply_filters( 'mlw_reviews_always_open', false ) ) return $open;
        return get_post_type( $post_id ) === 'product' ? true : $open;
    }

    /** Marke als Text ohne Link (die Karte ist selbst ein Link). */
    public static function render_loop_brand(): void {
        global $product;
        if ( ! $product instanceof WC_Product ) return;

        $terms = get_the_terms( $product->get_id(), 'product_brand' );
        if ( ! $terms || is_wp_error( $terms ) ) return;

        echo '<span class="mlw-loop-brand">' . esc_html( $terms[0]->name ) . '</span>';
    }

    /** Marke auf der Produktseite: Link auf das Marken-Archiv, vor dem Titel (Filter mlw_single_brand). */
    public static function render_single_brand(): void {
        global $product;
        if ( ! $product instanceof WC_Product ) return;

        $terms = get_the_terms( $product->get_id(), 'product_brand' );
        if ( ! $terms || is_wp_error( $terms ) ) return;

        $term = $terms[0];
        $link = get_term_link( $term );
        $name = esc_html( $term->name );

        echo '<p class="mlw-single-brand">' . ( is_wp_error( $link ) ? $name : '<a href="' . esc_url( $link ) . '">' . $name . '</a>' ) . '</p>';
    }

    /**
     * Marke in den Karten der Suchergebnisseite: erste Meta-Angabe (Filter ml_search_card_meta des Themes, Opt-in über
     * mlw_search_card_brand). Der Wert geht unescaped an das Theme, post-card.php escaped Label und Wert selbst.
     *
     * @param mixed $meta      Bisherige Meta-Angaben
     * @param mixed $post_type Inhaltstyp der Karte
     * @param mixed $post_id   Beitrags-ID der Karte
     */
    public static function search_card_meta( $meta, $post_type = '', $post_id = 0 ) {
        $meta = is_array( $meta ) ? $meta : [];
        if ( $post_type !== 'product' || ! $post_id ) return $meta;

        $terms = get_the_terms( (int) $post_id, 'product_brand' );
        if ( ! $terms || is_wp_error( $terms ) ) return $meta;

        // Beschriftung wie in der Filterleiste (Einstellung "Marke-Label"), sonst "Marke"
        $labels = function_exists( 'mlwf_get_attribute_labels' ) ? mlwf_get_attribute_labels() : [];
        $label  = ! empty( $labels['product_brand'] ) ? (string) $labels['product_brand'] : __( 'Marke', 'medialab-woo-filters' );

        array_unshift( $meta, [ 'label' => $label, 'value' => $terms[0]->name ] );
        return $meta;
    }

    public static function render_loop_availability(): void {
        global $product;
        if ( ! $product instanceof WC_Product ) return;

        $wc    = $product->get_availability();
        $in    = $product->is_in_stock();
        $label = ! empty( $wc['availability'] ) ? $wc['availability']
               : ( $in ? __( 'In stock', 'woocommerce' ) : __( 'Out of stock', 'woocommerce' ) );
        $data  = apply_filters( 'mlw_loop_availability', [ 'label' => $label, 'status' => $in ? 'in_stock' : 'out' ], $product );

        if ( ! is_array( $data ) || empty( $data['label'] ) ) return;

        echo '<span class="mlw-loop-availability mlw-loop-availability--' . esc_attr( sanitize_html_class( $data['status'] ?? 'default' ) ) . '">'
           . esc_html( $data['label'] ) . '</span>';
    }

    /**
     * Artikelnummer auf der Produktkarte, unter dem Preis (Filter mlw_loop_sku). Der Wert lässt sich über mlw_loop_sku_value
     * ersetzen, ohne Wert keine Ausgabe.
     */
    public static function render_loop_sku(): void {
        global $product;
        if ( ! $product instanceof WC_Product ) return;

        $sku = (string) apply_filters( 'mlw_loop_sku_value', $product->get_sku(), $product );
        if ( $sku === '' ) return;

        echo '<span class="mlw-loop-sku"><span class="mlw-loop-sku__label">' . esc_html__( 'Art.-Nr.:', 'media-lab-woocommerce' ) . '</span> '
           . esc_html( $sku ) . '</span>';
    }

    /**
     * Kategorien auf der Produktkarte, in einer eigenen Zeile unter der Artikelnummer (Filter mlw_loop_category): alle Kategorien des Produkts als Text
     * ohne Link (die Karte ist selbst ein Link), durch Komma getrennt. Gleiche Namen erscheinen nur einmal, die
     * WooCommerce-Standardkategorie wird übersprungen. Über mlw_loop_category_terms lässt sich die Liste der Begriffe ändern.
     */
    public static function render_loop_category(): void {
        global $product;
        if ( ! $product instanceof WC_Product ) return;

        $terms = get_the_terms( $product->get_id(), 'product_cat' );
        if ( ! $terms || is_wp_error( $terms ) ) return;

        $default = (int) get_option( 'default_product_cat' );
        $terms   = array_values( array_filter( (array) $terms, function ( $term ) use ( $default ) {
            return (int) $term->term_id !== $default;
        } ) );
        $terms = apply_filters( 'mlw_loop_category_terms', $terms, $product );
        if ( ! is_array( $terms ) ) return;

        $names = [];
        foreach ( $terms as $term ) {
            if ( is_object( $term ) && ! empty( $term->name ) && ! in_array( $term->name, $names, true ) ) {
                $names[] = $term->name;
            }
        }
        if ( ! $names ) return;

        echo '<span class="mlw-loop-category">' . esc_html( implode( ', ', $names ) ) . '</span>';
    }
}

MediaLab_Single_Product_Layout::init();
