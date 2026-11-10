<?php
/**
 * Variantenbilder bei der Farbwahl.
 *
 * WooCommerce tauscht Hauptbild und Galerie erst, wenn eine vollständige Variante gewählt ist (alle Felder, also auch
 * die Größe). Besucher kennen von anderen Shops das Gegenteil: Das Bild wechselt schon mit der Farbe. Diese Funktion
 * wendet bei gewählter Farbe ohne vollständige Variante die Bilddaten der ersten passenden Variante dieser Farbe an
 * (über die öffentliche WooCommerce-Funktion $.fn.wc_variations_image_update, Hauptbild und Galerie).
 *
 * Bei Produkten mit wenigen Varianten liegen die Variantendaten in der Seite, dort braucht es keinen Server.
 * Bei Produkten mit vielen Varianten (WooCommerce lädt sie erst mit der vollständigen Auswahl nach) holt das Skript die
 * Bilddaten der Farbe über den Endpunkt mlw_variation_color_image.
 *
 * Anpassen ohne Core-Code:
 *   add_filter( 'mlw_variation_color_images', '__return_false' );                    // Funktion ausschalten
 *   add_filter( 'mlw_variation_color_attributes', fn() => [ 'pa_color', 'pa_farbe' ] ); // Farbattribute (Taxonomien)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MediaLab_Variation_Color_Images {

    /** Felder der Variante, die für den Bildwechsel gebraucht werden. */
    const KEYS = [ 'image', 'image_id', 'gallery_image_ids', 'gallery_images_html' ];

    public static function init(): void {
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue' ], 20 );
        add_action( 'wp_ajax_mlw_variation_color_image',        [ __CLASS__, 'ajax' ] );
        add_action( 'wp_ajax_nopriv_mlw_variation_color_image', [ __CLASS__, 'ajax' ] );
    }

    public static function enabled(): bool {
        return (bool) apply_filters( 'mlw_variation_color_images', true );
    }

    /** Taxonomien der Farbattribute, zum Beispiel [ 'pa_color' ]. */
    public static function attributes(): array {
        $attrs = (array) apply_filters( 'mlw_variation_color_attributes', [ 'pa_color' ] );
        $attrs = array_map( static function ( $a ) { return preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $a ); }, $attrs );
        return array_values( array_unique( array_filter( $attrs ) ) );
    }

    // ── Skript ───────────────────────────────────────────────────────────────

    public static function enqueue(): void {
        if ( ! self::enabled() || ! function_exists( 'is_product' ) || ! is_product() ) return;
        $product = function_exists( 'wc_get_product' ) ? wc_get_product( get_the_ID() ) : null;
        if ( ! $product || ! $product->is_type( 'variable' ) ) return;

        $root = dirname( __DIR__ );
        $url  = defined( 'MEDIA_LAB_WC_URL' ) ? MEDIA_LAB_WC_URL : trailingslashit( plugin_dir_url( $root . '/media-lab-woocommerce.php' ) );
        $ver  = defined( 'MEDIA_LAB_WC_VERSION' ) ? MEDIA_LAB_WC_VERSION : null;

        wp_enqueue_script( 'mlw-variation-color-images', $url . 'assets/js/variation-color-images.js', [ 'jquery', 'wc-add-to-cart-variation' ], $ver, true );
        wp_localize_script( 'mlw-variation-color-images', 'mlwColorImages', [
            'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
            'attributes' => array_map( static function ( $a ) { return 'attribute_' . $a; }, self::attributes() ),
        ] );
    }

    // ── Endpunkt ─────────────────────────────────────────────────────────────

    public static function ajax(): void {
        $product_id = isset( $_REQUEST['product_id'] ) ? absint( $_REQUEST['product_id'] ) : 0;
        $attribute  = isset( $_REQUEST['attribute'] ) ? preg_replace( '/[^A-Za-z0-9_\-]/', '', wp_unslash( (string) $_REQUEST['attribute'] ) ) : '';
        $value      = isset( $_REQUEST['value'] ) ? sanitize_title( wp_unslash( (string) $_REQUEST['value'] ) ) : '';
        wp_send_json_success( self::resolve( $product_id, $attribute, $value ) );
    }

    /**
     * Bilddaten der ersten passenden Variante für Farbe $value im Attribut $attribute ("attribute_pa_color") oder null.
     * Nur für veröffentlichte variable Produkte und nur für die erlaubten Farbattribute.
     */
    public static function resolve( int $product_id, string $attribute, string $value ): ?array {
        $allowed = array_map( static function ( $a ) { return 'attribute_' . $a; }, self::attributes() );
        if ( ! self::enabled() || ! $product_id || $value === '' || ! in_array( $attribute, $allowed, true ) ) return null;
        if ( get_post_status( $product_id ) !== 'publish' ) return null;
        $product = wc_get_product( $product_id );
        if ( ! $product || ! $product->is_type( 'variable' ) ) return null;

        $version = class_exists( 'WC_Cache_Helper' ) ? \WC_Cache_Helper::get_transient_version( 'product' ) : '0';
        $key     = 'mlw_vci_' . md5( $product_id . '|' . $attribute . '|' . $value . '|' . $version );
        $cached  = get_transient( $key );
        if ( is_array( $cached ) ) return $cached ?: null;

        $found = self::variation_for( $product, $attribute, $value );
        set_transient( $key, $found ?: [], 12 * HOUR_IN_SECONDS );   // auch "keine Variante" merken
        return $found;
    }

    public static function variation_for( $product, string $attribute, string $value ): ?array {
        $children = array_map( 'intval', (array) $product->get_children() );
        if ( ! $children ) return null;

        // Eine einzige Abfrage statt je Variante eine: Welche Varianten haben diese Farbe? (Bei 300 Varianten sind
        // 300 Einzelabfragen mit Query Monitor oder Xdebug über eine Sekunde.)
        $with_value = self::variations_with_value( $children, $attribute, $value );

        foreach ( $children as $vid ) {                       // die Reihenfolge der Varianten bleibt maßgeblich
            if ( ! isset( $with_value[ $vid ] ) ) continue;
            $variation = wc_get_product( $vid );
            if ( ! $variation || ! $variation->get_image_id() || ! $variation->variation_is_visible() ) continue;
            $data = $product->get_available_variation( $variation );
            if ( ! is_array( $data ) ) continue;
            $out = array_intersect_key( $data, array_flip( self::KEYS ) );
            if ( $out ) return $out;
        }
        return null;
    }

    /**
     * IDs der Varianten aus $ids, deren Attributfeld $attribute den Wert $value hat, als [ id => true ].
     * Mit einer einzigen Datenbankabfrage, ohne $wpdb (zum Beispiel in Tests) mit dem Lesen je Variante.
     */
    private static function variations_with_value( array $ids, string $attribute, string $value ): array {
        global $wpdb;
        $found = [];
        if ( isset( $wpdb ) && is_object( $wpdb ) && ! empty( $wpdb->postmeta ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
            $rows = $wpdb->get_col( $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s AND post_id IN ($placeholders)",
                array_merge( [ $attribute, $value ], $ids )
            ) );
            foreach ( (array) $rows as $id ) $found[ (int) $id ] = true;
            return $found;
        }
        foreach ( $ids as $id ) {
            if ( (string) get_post_meta( $id, $attribute, true ) === $value ) $found[ $id ] = true;
        }
        return $found;
    }
}

MediaLab_Variation_Color_Images::init();
