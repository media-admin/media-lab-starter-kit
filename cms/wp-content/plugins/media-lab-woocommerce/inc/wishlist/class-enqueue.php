<?php
/**
 * Frontend-Assets der Wunschliste.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MediaLab_Wishlist_Enqueue {

    public static function init(): void {
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue' ] );
    }

    public static function enqueue(): void {
        // Wunschliste soll überall verfügbar sein (Produktkarten im Loop, Einzelseite,
        // eigene Wunschlisten-Seite) - daher global geladen, nicht nur auf WC-Seiten.
        // Das JS selbst bleibt inaktiv, wenn keine .mlw-wishlist-* Elemente im DOM sind.
        wp_enqueue_script(
            'mlw-wishlist',
            MEDIA_LAB_WC_URL . 'assets/js/wishlist.js',
            [],
            MEDIA_LAB_WC_VERSION,
            true
        );

        wp_enqueue_style(
            'mlw-wishlist',
            MEDIA_LAB_WC_URL . 'assets/css/wishlist.css',
            [],
            MEDIA_LAB_WC_VERSION
        );

        wp_localize_script( 'mlw-wishlist', 'mlwWishlist', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( MediaLab_Wishlist_Ajax::NONCE_ACTION ),
            'count'   => MediaLab_Wishlist_Storage::count(),
            'placeholderImage' => function_exists( 'wc_placeholder_img_src' ) ? wc_placeholder_img_src() : '',
            'price'   => [
                'decimals'    => function_exists( 'wc_get_price_decimals' ) ? (int) wc_get_price_decimals() : 2,
                'decimalSep'  => function_exists( 'wc_get_price_decimal_separator' ) ? wc_get_price_decimal_separator() : ',',
                'thousandSep' => function_exists( 'wc_get_price_thousand_separator' ) ? wc_get_price_thousand_separator() : '.',
                'symbol'      => function_exists( 'get_woocommerce_currency_symbol' ) ? html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ) : '€',
                'format'      => function_exists( 'get_woocommerce_price_format' ) ? get_woocommerce_price_format() : '%2$s&nbsp;%1$s',
            ],
            'i18n'    => [
                'addButton'      => MediaLab_Inquiry_Settings::wording( 'add_button' ),
                'submitButton'   => MediaLab_Inquiry_Settings::wording( 'submit_button' ),
                'successMessage' => MediaLab_Inquiry_Settings::wording( 'wishlist_success' ),
                'genericError'   => __( 'Es ist ein Fehler aufgetreten. Bitte versuchen Sie es erneut.', 'media-lab-woocommerce' ),
                'formIncomplete' => __( 'Bitte füllen Sie alle Pflichtfelder aus und akzeptieren Sie die Datenschutzerklärung.', 'media-lab-woocommerce' ),
                'skuLabel'       => __( 'Art.-Nr.:', 'media-lab-woocommerce' ),
                'confirmRemove'  => __( 'Diesen Artikel von der Wunschliste entfernen?', 'media-lab-woocommerce' ),
                'emptyWishlist'  => __( 'Ihre Wunschliste ist leer.', 'media-lab-woocommerce' ),
                'perUnit'        => __( 'pro Stück', 'media-lab-woocommerce' ),
                'total'          => __( 'gesamt', 'media-lab-woocommerce' ),
                'totalFor'       => __( 'Gesamt (%s Stück)', 'media-lab-woocommerce' ),
            ],
        ] );
    }
}

MediaLab_Wishlist_Enqueue::init();
