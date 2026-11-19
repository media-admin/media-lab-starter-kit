<?php
/**
 * Varianten-Vorschaubilder in der Galerie variabler Produkte.
 *
 * Unter der Produktgalerie erscheint je Farbe der Varianten ein Vorschaubild (das Bild der ersten sichtbaren Variante
 * dieser Farbe). Ein Klick darauf wählt die Farbe im Variantenformular. Hauptbild und Galerie wechseln dann über die
 * Funktion "Variantenbilder bei der Farbwahl" (inc/variation-color-images.php), die dafür aktiv sein muss.
 *
 * Standardmäßig AUS, damit bestehende Projekte unverändert bleiben. Pro Projekt einschalten:
 *   add_filter( 'mlw_variation_color_thumbs', '__return_true' );
 *
 * Weitere Anpassungen ohne Core-Code:
 *   add_filter( 'mlw_variation_color_thumbs_min', fn() => 2 );              // ab so vielen Farben anzeigen (Standard 2)
 *   add_filter( 'mlw_variation_color_thumbs_max', fn() => 40 );             // höchstens so viele Vorschaubilder (Standard 40)
 *   add_filter( 'mlw_variation_color_thumbs_items', function ( $items, $product ) { return $items; }, 10, 2 );
 * Das Farbattribut kommt wie bei den Variantenbildern aus dem Filter mlw_variation_color_attributes (Standard pa_color).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MediaLab_Variation_Color_Thumbs {

    public static function init(): void {
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue' ], 25 );
    }

    public static function enabled(): bool {
        return (bool) apply_filters( 'mlw_variation_color_thumbs', false )
            && class_exists( 'MediaLab_Variation_Color_Images' )
            && MediaLab_Variation_Color_Images::enabled();
    }

    // ── Skript und Stil ──────────────────────────────────────────────────────

    public static function enqueue(): void {
        if ( ! self::enabled() || ! function_exists( 'is_product' ) || ! is_product() ) return;
        $product = function_exists( 'wc_get_product' ) ? wc_get_product( get_the_ID() ) : null;
        if ( ! $product || ! $product->is_type( 'variable' ) ) return;

        $items = self::items( $product );
        if ( count( $items ) < max( 2, (int) apply_filters( 'mlw_variation_color_thumbs_min', 2 ) ) ) return;

        $root = dirname( __DIR__ );
        $url  = defined( 'MEDIA_LAB_WC_URL' ) ? MEDIA_LAB_WC_URL : trailingslashit( plugin_dir_url( $root . '/media-lab-woocommerce.php' ) );
        $ver  = defined( 'MEDIA_LAB_WC_VERSION' ) ? MEDIA_LAB_WC_VERSION : null;

        wp_enqueue_style( 'mlw-variation-color-thumbs', $url . 'assets/css/variation-color-thumbs.css', [], $ver );
        wp_enqueue_script( 'mlw-variation-color-thumbs', $url . 'assets/js/variation-color-thumbs.js', [ 'jquery', 'wc-add-to-cart-variation' ], $ver, true );
        wp_localize_script( 'mlw-variation-color-thumbs', 'mlwColorThumbs', [
            'attribute' => $items[0]['attr'],
            'groupLabel' => self::group_label( $items[0]['attr'] ),
            'items'     => array_map( static function ( $i ) {
                return [ 'value' => $i['value'], 'label' => $i['label'], 'src' => $i['src'] ];
            }, $items ),
        ] );
    }

    private static function group_label( string $attribute ): string {
        $taxonomy = preg_replace( '/^attribute_/', '', $attribute );
        $name     = function_exists( 'wc_attribute_label' ) ? wc_attribute_label( $taxonomy ) : $taxonomy;
        /* translators: %s: Attributname, zum Beispiel "Farbe" */
        return sprintf( __( '%s wählen', 'media-lab-woocommerce' ), $name );
    }

    // ── Daten ────────────────────────────────────────────────────────────────

    /**
     * Ein Eintrag je Farbe: [ attr, value, label, image_id, src ]. Leer, wenn es keine Farbvarianten mit eigenem Bild gibt.
     */
    public static function items( $product ): array {
        $children = array_map( 'intval', (array) $product->get_children() );
        if ( ! $children ) return [];

        $version = class_exists( 'WC_Cache_Helper' ) ? \WC_Cache_Helper::get_transient_version( 'product' ) : '0';
        $key     = 'mlw_vct_' . md5( $product->get_id() . '|' . implode( ',', MediaLab_Variation_Color_Images::attributes() ) . '|' . $version );
        $colors  = get_transient( $key );
        if ( ! is_array( $colors ) ) {
            $colors = self::collect( $product, $children );
            set_transient( $key, $colors, 12 * HOUR_IN_SECONDS );   // auch "keine Farben" merken
        }

        $max   = max( 1, (int) apply_filters( 'mlw_variation_color_thumbs_max', 40 ) );
        $items = [];
        foreach ( array_slice( $colors, 0, $max ) as $c ) {
            $src = wp_get_attachment_image_url( (int) $c['image_id'], 'woocommerce_gallery_thumbnail' );
            if ( ! $src ) continue;
            $c['src'] = $src;
            $items[]  = $c;
        }
        $filtered = apply_filters( 'mlw_variation_color_thumbs_items', $items, $product );
        return is_array( $filtered ) ? array_values( $filtered ) : $items;
    }

    /**
     * Farben des Produkts in der Reihenfolge der Varianten, je Farbe das Bild der ersten sichtbaren Variante mit eigenem Bild.
     * Es zählt das erste Farbattribut (Filter mlw_variation_color_attributes), das bei den Varianten gesetzt ist.
     */
    public static function collect( $product, array $children ): array {
        foreach ( MediaLab_Variation_Color_Images::attributes() as $taxonomy ) {
            $meta   = 'attribute_' . $taxonomy;
            $values = array_filter( self::meta_values( $children, $meta ), static function ( $v ) { return $v !== ''; } );
            if ( ! $values ) continue;

            $thumbs = self::meta_values( array_keys( $values ), '_thumbnail_id' );
            $seen   = [];
            $colors = [];
            foreach ( $children as $vid ) {                    // die Reihenfolge der Varianten bleibt maßgeblich
                if ( ! isset( $values[ $vid ] ) ) continue;
                $value = $values[ $vid ];
                $image = (int) ( $thumbs[ $vid ] ?? 0 );
                if ( isset( $seen[ $value ] ) || ! $image ) continue;
                $variation = wc_get_product( $vid );
                if ( ! $variation || ! $variation->variation_is_visible() ) continue;
                $seen[ $value ] = true;
                $colors[] = [ 'attr' => $meta, 'value' => $value, 'label' => self::label( $taxonomy, $value ), 'image_id' => $image ];
            }
            if ( $colors ) return $colors;
        }
        return [];
    }

    private static function label( string $taxonomy, string $value ): string {
        if ( function_exists( 'taxonomy_exists' ) && taxonomy_exists( $taxonomy ) ) {
            $term = get_term_by( 'slug', $value, $taxonomy );
            if ( $term && ! is_wp_error( $term ) ) return (string) $term->name;
        }
        return $value;
    }

    /**
     * Metawert $meta der Beiträge $ids als [ id => Wert ]. Mit einer einzigen Datenbankabfrage, ohne $wpdb
     * (zum Beispiel in Tests) mit dem Lesen je Beitrag.
     */
    private static function meta_values( array $ids, string $meta ): array {
        global $wpdb;
        $out = [];
        if ( ! $ids ) return $out;
        if ( isset( $wpdb ) && is_object( $wpdb ) && ! empty( $wpdb->postmeta ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s AND post_id IN ($placeholders) ORDER BY meta_id ASC",
                array_merge( [ $meta ], $ids )
            ), ARRAY_A );
            foreach ( (array) $rows as $row ) {
                $id = (int) $row['post_id'];
                if ( ! isset( $out[ $id ] ) ) $out[ $id ] = (string) $row['meta_value'];
            }
            return $out;
        }
        foreach ( $ids as $id ) {
            $out[ (int) $id ] = (string) get_post_meta( $id, $meta, true );
        }
        return $out;
    }
}

MediaLab_Variation_Color_Thumbs::init();
