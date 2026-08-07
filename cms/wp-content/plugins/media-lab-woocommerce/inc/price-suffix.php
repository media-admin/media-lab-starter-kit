<?php
/**
 * Preis-Hinweistext (z.B. "zzgl. Versandkosten") über WooCommerce's eigenen
 * Suffix-Mechanismus - NICHT an die Inquiry-Engine gekoppelt, damit es auch
 * in Projekten funktioniert, die Cart-Anfrage/Konfigurator/Wunschliste gar
 * nicht nutzen.
 *
 * Greift automatisch überall dort, wo WooCommerce Preise anzeigt (Shop-Loop,
 * Einzelproduktseite, Warenkorb, Checkout) UND im Konfigurator (der
 * wc_get_price_suffix() bereits in class-price-calculator.php aufruft).
 *
 * Priorität:
 *   1. Mehrsprachiger Text aus den Inquiry-Einstellungen (Tab "Sprachen"/
 *      "Wording" → "Preis-Hinweistext"), FALLS die Inquiry-Engine genutzt wird.
 *   2. WooCommerce's eigenes natives Feld (Einstellungen → Steuern →
 *      "Preis-Anzeige-Suffix") - funktioniert auch ganz ohne dieses Plugin,
 *      bzw. ohne dass die Inquiry-Einstellungen gepflegt wurden.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_filter( 'woocommerce_get_price_suffix', function ( $suffix, $product, $price, $qty ) {
    if ( class_exists( 'MediaLab_Inquiry_Settings' ) ) {
        $custom = MediaLab_Inquiry_Settings::wording( 'price_notice' );
        if ( $custom !== '' ) {
            return $custom;
        }
    }
    // Kein eigener Text konfiguriert (oder Inquiry-Engine nicht aktiv) -
    // natives WooCommerce-Verhalten unverändert durchreichen.
    return $suffix;
}, 10, 4 );
