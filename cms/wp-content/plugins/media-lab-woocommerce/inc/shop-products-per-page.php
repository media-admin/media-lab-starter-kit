<?php
/**
 * "Produkte pro Seite" konfigurierbar machen.
 *
 * Das Theme hat den Wert bisher fest codiert (custom-theme/inc/woocommerce.php):
 *   add_filter('loop_shop_per_page', fn() => 12, 20);
 *
 * loop_shop_per_page ist der zentrale WooCommerce-Hook, der die Produktanzahl
 * pro Seite steuert - sowohl auf Shop-/Kategorie-Übersichtsseiten als auch auf
 * der nativen Produktsuche-Ergebnisseite (WC_Query wendet ihn über
 * pre_get_posts überall dort an, wo Produkte gelistet werden, inkl. is_search()).
 * NICHT betroffen ist die separate Ajax-Such-Vorschau (Dropdown), die ihr
 * eigenes, unabhängiges Limit hat.
 *
 * Diese Datei fügt ein eigenes Feld direkt in die NATIVE WooCommerce-
 * Einstellungsseite ein (Produkte → Anzeige) - dort, wo ein Shop-Betreiber
 * das erwarten würde - und überschreibt den Theme-Wert mit höherer Priorität.
 * Bewusst NICHT an die Inquiry-Engine gekoppelt, damit es auch in Projekten
 * funktioniert, die diese gar nicht nutzen.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_filter( 'woocommerce_products_general_settings', function ( $settings ) {
    // Feld nach der bestehenden "Zeilen pro Seite"-Gruppe einfügen, falls
    // vorhanden, sonst ans Ende der Sektion anhängen.
    $insert_at = count( $settings );
    foreach ( $settings as $index => $setting ) {
        if ( ( $setting['id'] ?? '' ) === 'woocommerce_catalog_columns' ) {
            $insert_at = $index + 1;
            break;
        }
    }

    $field = [
        'title'    => __( 'Produkte pro Seite', 'media-lab-woocommerce' ),
        'desc'     => __( 'Anzahl der Produkte auf Shop-/Kategorie-Übersichtsseiten und der Produktsuche-Ergebnisseite.', 'media-lab-woocommerce' ),
        'id'       => 'mlw_products_per_page',
        'default'  => '12',
        'type'     => 'number',
        'desc_tip' => true,
        'css'      => 'width:80px;',
        'custom_attributes' => [ 'min' => '1', 'step' => '1' ],
    ];

    array_splice( $settings, $insert_at, 0, [ $field ] );
    return $settings;
} );

// Theme-Wert überschreiben - höhere Priorität (30) als der Theme-Filter (20).
add_filter( 'loop_shop_per_page', function ( $per_page ) {
    $configured = get_option( 'mlw_products_per_page', '' );
    if ( $configured !== '' && is_numeric( $configured ) && (int) $configured > 0 ) {
        return (int) $configured;
    }
    return $per_page;
}, 30 );
