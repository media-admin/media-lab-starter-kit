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

        if ( apply_filters( 'mlw_loop_product_info', false ) ) {
            add_action( 'woocommerce_shop_loop_item_title', [ __CLASS__, 'render_loop_brand' ], 9 );
            add_action( 'woocommerce_after_shop_loop_item_title', [ __CLASS__, 'render_loop_availability' ], 11 );
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
}

MediaLab_Single_Product_Layout::init();
