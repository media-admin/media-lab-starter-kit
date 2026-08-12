<?php
/**
 * Shortcode zur Einbindung des Catalog-Mode-Anfrageformulars
 * (templates/inquiry-form.php) außerhalb des automatischen Checkout-Flows -
 * z.B. auf einer eigenen Landingpage oder in einem Content-Block.
 *
 * Nutzung: [mlw_inquiry_form]
 *
 * Vorher war templates/inquiry-form.php an keiner Stelle in inc/
 * eingebunden (totes Template, nur ein vermutlich fehlgeleiteter
 * Kommentarverweis in catalog-mode.php). Dieser Wrapper macht es über
 * einen Shortcode nutzbar, ohne den bestehenden inquiry-checkout.php-Flow
 * zu verändern.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MediaLab_Inquiry_Shortcode {

    public static function init(): void {
        add_shortcode( 'mlw_inquiry_form', [ __CLASS__, 'render' ] );
    }

    public static function render( $atts = [] ): string {
        if ( ! class_exists( 'WooCommerce' ) || ! WC()->cart ) {
            return '';
        }

        // Theme-Override erlauben (analog zur WooCommerce-Template-Konvention:
        // yourtheme/media-lab-woocommerce/inquiry-form.php gewinnt, falls vorhanden).
        $template = locate_template( 'media-lab-woocommerce/inquiry-form.php' );
        if ( ! $template ) {
            $template = MEDIA_LAB_WC_PATH . 'templates/inquiry-form.php';
        }

        if ( ! file_exists( $template ) ) {
            return '';
        }

        ob_start();
        include $template;
        return ob_get_clean();
    }
}

MediaLab_Inquiry_Shortcode::init();
