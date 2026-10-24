<?php
/**
 * Einheitliche Rückmeldung nach dem Absenden einer Anfrage.
 *
 * Gilt für alle Wege: Konfigurator, "Zur Wunschliste hinzufügen" im Wizard, Wunschzettel, inquiry-form.php, inquiry-checkout.php.
 * Einstellung unter Anfragen → Einstellungen: "Nach dem Absenden" (Meldung auf der Seite oder Weiterleitung) und
 * "Weiterleitungsseite". Das JS dazu liegt in assets/js/inquiry-feedback.js.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MediaLab_Inquiry_Feedback {

    public static function init(): void {
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue' ] );
    }

    /** @return array{mode:string,url:string} mode ist "message" oder "redirect". */
    public static function settings(): array {
        $mode = function_exists( 'get_field' ) ? (string) get_field( 'mlw_after_submit', 'option' ) : '';
        $page = function_exists( 'get_field' ) ? get_field( 'mlw_after_submit_page', 'option' ) : '';

        $url = is_string( $page ) ? esc_url_raw( $page ) : '';
        // Nur Ziele auf der eigenen Domain
        if ( $url !== '' && wp_parse_url( $url, PHP_URL_HOST ) !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
            $url = '';
        }

        return [
            'mode' => ( $mode === 'redirect' && $url !== '' ) ? 'redirect' : 'message',
            'url'  => $url,
        ];
    }

    private static function wishlist_url(): string {
        $page = function_exists( 'get_field' ) ? get_field( 'mlw_nav_wishlist_page', 'option' ) : 0;
        if ( $page instanceof WP_Post ) {
            return (string) get_permalink( $page );
        }
        if ( is_numeric( $page ) && (int) $page > 0 ) {
            return (string) get_permalink( (int) $page );
        }
        return is_string( $page ) ? esc_url_raw( $page ) : '';
    }

    public static function enqueue(): void {
        $s = self::settings();

        wp_enqueue_script(
            'mlw-inquiry-feedback',
            MEDIA_LAB_WC_URL . 'assets/js/inquiry-feedback.js',
            [],
            MEDIA_LAB_WC_VERSION,
            true
        );
        wp_enqueue_style(
            'mlw-inquiry-feedback',
            MEDIA_LAB_WC_URL . 'assets/css/inquiry-feedback.css',
            [],
            MEDIA_LAB_WC_VERSION
        );

        wp_localize_script( 'mlw-inquiry-feedback', 'mlwFeedback', [
            'mode'        => $s['mode'],
            'redirectUrl' => $s['url'],
            'shopUrl'     => function_exists( 'wc_get_page_permalink' ) ? (string) wc_get_page_permalink( 'shop' ) : home_url( '/' ),
            'wishlistUrl' => self::wishlist_url(),
            'messages'    => [
                'inquiry'       => MediaLab_Inquiry_Settings::wording( 'success' ),
                'wishlist'      => MediaLab_Inquiry_Settings::wording( 'wishlist_success' ),
                'wishlistAdded' => __( 'Zur Wunschliste hinzugefügt.', 'media-lab-woocommerce' ),
            ],
            'i18n'        => [
                'continueShopping' => __( 'Weiter einkaufen', 'media-lab-woocommerce' ),
                'toWishlist'       => __( 'Zur Wunschliste', 'media-lab-woocommerce' ),
            ],
        ] );
    }
}

MediaLab_Inquiry_Feedback::init();
