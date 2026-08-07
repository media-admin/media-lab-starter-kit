<?php
/**
 * Legt Testprodukte für die Konfigurator-Tests an: 3 Kategorien
 * (Drucksorten, Textilien, Give-aways), Mischung aus einfachen und
 * konfigurierbaren Produkten mit realistischen Step-Kombinationen.
 *
 * Verwendung:
 *   wp eval-file create-category-test-products.php
 *
 * Vorhandene Testprodukte mit demselben Titel-Präfix "Test –" werden
 * vorher gelöscht, damit das Skript mehrfach ausführbar ist.
 */

if ( ! defined( 'WP_CLI' ) ) {
    echo "Bitte über 'wp eval-file create-category-test-products.php' ausführen.\n";
    exit;
}

// ── Vorherige Testprodukte aufräumen ────────────────────────────────────────

$existing = new WP_Query( [
    'post_type'      => 'product',
    'title'          => 'Test –',
    'post_status'    => 'any',
    'posts_per_page' => -1,
    'fields'         => 'ids',
] );
// title-Parameter ist ein Exact-Match, daher zusätzlich per LIKE über die DB suchen.
global $wpdb;
$ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' AND post_title LIKE 'Test – %'" );
foreach ( $ids as $id ) {
    wp_delete_post( (int) $id, true );
}
if ( $ids ) WP_CLI::log( count( $ids ) . ' bestehende Testprodukte gelöscht.' );

// ── Kategorien anlegen ───────────────────────────────────────────────────────

$category_ids = [];
foreach ( [ 'Drucksorten', 'Textilien', 'Give-aways' ] as $cat_name ) {
    $term = term_exists( $cat_name, 'product_cat' );
    if ( ! $term ) {
        $term = wp_insert_term( $cat_name, 'product_cat' );
    }
    $category_ids[ $cat_name ] = is_wp_error( $term ) ? null : (int) ( is_array( $term ) ? $term['term_id'] : $term );
}
WP_CLI::success( 'Kategorien angelegt/gefunden: ' . implode( ', ', array_keys( $category_ids ) ) );

// ── Wiederverwendbare Config-Step-Bausteine ──────────────────────────────────

$step_contact = [
    'step_id' => 'contact', 'step_label' => 'Ihre Kontaktdaten', 'step_type' => 'contact_form',
    'required' => true, 'show_in_summary' => false, 'description' => '',
    'options' => false, 'conditions' => false, 'min_value' => '', 'max_value' => '',
    'allowed_file_types' => 'pdf,jpg,png', 'max_file_size' => 10,
];

function mlw_test_file_step( string $id, string $label, bool $required, string $types = 'pdf,ai,eps,jpg,png' ): array {
    return [
        'step_id' => $id, 'step_label' => $label, 'step_type' => 'file_upload',
        'required' => $required, 'show_in_summary' => true,
        'description' => $required ? 'Bitte Druckdaten hochladen.' : 'Optional.',
        'options' => false, 'conditions' => false, 'min_value' => '', 'max_value' => '',
        'allowed_file_types' => $types, 'max_file_size' => 20,
    ];
}

function mlw_test_select_step( string $id, string $label, array $options, bool $required = true ): array {
    return [
        'step_id' => $id, 'step_label' => $label, 'step_type' => 'select',
        'required' => $required, 'show_in_summary' => true, 'description' => '',
        'options' => array_map( fn( $o ) => array_merge( [ 'image' => false, 'available' => true ], $o ), $options ),
        'conditions' => false, 'min_value' => '', 'max_value' => '',
        'allowed_file_types' => 'pdf,jpg,png', 'max_file_size' => 10,
    ];
}

$step_papierart = mlw_test_select_step( 'papierart', 'Papierart', [
    [ 'value' => 'standard', 'label' => 'Standard 90g', 'price_modifier' => 0 ],
    [ 'value' => 'premium',  'label' => 'Premium 170g', 'price_modifier' => 8 ],
    [ 'value' => 'recycling','label' => 'Recycling',    'price_modifier' => 3 ],
] );

$step_bedruckung = mlw_test_select_step( 'bedruckung', 'Bedruckung', [
    [ 'value' => 'einseitig', 'label' => 'Einseitig', 'price_modifier' => 0 ],
    [ 'value' => 'beidseitig', 'label' => 'Beidseitig', 'price_modifier' => 6 ],
] );

$step_farbe_textil = mlw_test_select_step( 'farbe', 'Farbe', [
    [ 'value' => 'weiss',  'label' => 'Weiß',  'price_modifier' => 0 ],
    [ 'value' => 'schwarz','label' => 'Schwarz', 'price_modifier' => 0 ],
    [ 'value' => 'navy',   'label' => 'Navy',   'price_modifier' => 2 ],
    [ 'value' => 'rot',    'label' => 'Rot',    'price_modifier' => 2 ],
    [ 'value' => 'grau',   'label' => 'Grau meliert', 'price_modifier' => 3 ],
] );

$step_groesse = mlw_test_select_step( 'groesse', 'Größe', [
    [ 'value' => 's',   'label' => 'S',   'price_modifier' => 0 ],
    [ 'value' => 'm',   'label' => 'M',   'price_modifier' => 0 ],
    [ 'value' => 'l',   'label' => 'L',   'price_modifier' => 0 ],
    [ 'value' => 'xl',  'label' => 'XL',  'price_modifier' => 2 ],
    [ 'value' => 'xxl', 'label' => 'XXL', 'price_modifier' => 3 ],
] );

$step_druckposition = mlw_test_select_step( 'position', 'Druckposition', [
    [ 'value' => 'brust_links',  'label' => 'Brust links',    'price_modifier' => 0 ],
    [ 'value' => 'brust_rechts', 'label' => 'Brust rechts',   'price_modifier' => 0 ],
    [ 'value' => 'ruecken',      'label' => 'Rücken mittig',  'price_modifier' => 4 ],
], false );

$step_logo_datei = mlw_test_file_step( 'logo', 'Logo-Datei', false, 'pdf,ai,eps,png,jpg,jpeg' );

// Mengen-Step: aktiviert automatisch die Mengenrabatt-Staffel-Anzeige im Wizard
// (siehe templates/configurator/wizard.php, $quantity_step_index-Erkennung).
$step_quantity = [
    'step_id' => 'quantity', 'step_label' => 'Menge', 'step_type' => 'number',
    'required' => true, 'show_in_summary' => true,
    'description' => 'Wie viele Stück möchten Sie bestellen?',
    'options' => false, 'conditions' => false,
    'min_value' => 1, 'max_value' => 5000,
    'allowed_file_types' => 'pdf,jpg,png', 'max_file_size' => 10,
];

$step_farbe_giveaway = mlw_test_select_step( 'farbe', 'Farbe', [
    [ 'value' => 'weiss',  'label' => 'Weiß',  'price_modifier' => 0 ],
    [ 'value' => 'schwarz','label' => 'Schwarz', 'price_modifier' => 0 ],
    [ 'value' => 'blau',   'label' => 'Blau',   'price_modifier' => 0 ],
    [ 'value' => 'gruen',  'label' => 'Grün',   'price_modifier' => 0 ],
], false );

// ── Produktdefinitionen ──────────────────────────────────────────────────────
// 'config' => null => einfaches Produkt. 'config' => [Steps] => konfigurierbar.

$products = [];

// --- Drucksorten (12) ---
$drucksorten = [
    [ 'Visitenkarten Standard', 24.90, [ $step_papierart, $step_bedruckung, mlw_test_file_step('druckdaten','Druckdatei',true), $step_quantity, $step_contact ] ],
    [ 'Visitenkarten Premium mit Veredelung', 39.90, [ $step_papierart, $step_bedruckung, mlw_test_file_step('druckdaten','Druckdatei',true), $step_quantity, $step_contact ] ],
    [ 'Flyer A6', 29.90, [ $step_papierart, $step_bedruckung, mlw_test_file_step('druckdaten','Druckdatei',true), $step_quantity, $step_contact ] ],
    [ 'Flyer A5', 34.90, [ $step_papierart, $step_bedruckung, mlw_test_file_step('druckdaten','Druckdatei',true), $step_quantity, $step_contact ] ],
    [ 'Falzflyer DIN lang', 44.90, [ $step_papierart, mlw_test_file_step('druckdaten','Druckdatei',true), $step_quantity, $step_contact ] ],
    [ 'Broschüre A4 (8 Seiten)', 89.90, null ],
    [ 'Plakat A1', 19.90, [ $step_papierart, mlw_test_file_step('druckdaten','Druckdatei',true), $step_quantity, $step_contact ] ],
    [ 'Plakat A2', 14.90, null ],
    [ 'Briefpapier', 49.90, [ $step_papierart, $step_quantity, $step_contact ] ],
    [ 'Kuverts C6 bedruckt', 34.90, null ],
    [ 'Aufkleber rund', 19.90, [ mlw_test_file_step('druckdaten','Druckdatei',true), $step_quantity, $step_contact ] ],
    [ 'Rollup Banner 85x200cm', 129.90, [ mlw_test_file_step('druckdaten','Druckdatei',true), $step_quantity, $step_contact ] ],
];
foreach ( $drucksorten as $p ) $products[] = [ 'name' => $p[0], 'price' => $p[1], 'cat' => 'Drucksorten', 'config' => $p[2] ];

// --- Textilien (12) ---
$textilien = [
    [ 'T-Shirt Basic Unisex', 19.90, [ $step_farbe_textil, $step_groesse, $step_druckposition, $step_logo_datei, $step_quantity, $step_contact ] ],
    [ 'T-Shirt Premium Bio-Baumwolle', 29.90, [ $step_farbe_textil, $step_groesse, $step_druckposition, $step_logo_datei, $step_quantity, $step_contact ] ],
    [ 'Polo-Shirt', 34.90, [ $step_farbe_textil, $step_groesse, $step_druckposition, $step_logo_datei, $step_quantity, $step_contact ] ],
    [ 'Hoodie', 49.90, [ $step_farbe_textil, $step_groesse, $step_druckposition, $step_logo_datei, $step_quantity, $step_contact ] ],
    [ 'Sweatshirt', 39.90, [ $step_farbe_textil, $step_groesse, $step_druckposition, $step_logo_datei, $step_quantity, $step_contact ] ],
    [ 'Softshelljacke', 69.90, [ $step_farbe_textil, $step_groesse, $step_logo_datei, $step_quantity, $step_contact ] ],
    [ 'Cap / Kappe', 14.90, [ $step_farbe_textil, $step_logo_datei, $step_quantity, $step_contact ] ],
    [ 'Beanie / Mütze', 12.90, [ $step_farbe_textil, $step_quantity, $step_contact ] ],
    [ 'Arbeitsjacke', 79.90, null ],
    [ 'Schürze', 17.90, [ $step_farbe_textil, $step_logo_datei, $step_quantity, $step_contact ] ],
    [ 'Tank Top', 16.90, [ $step_farbe_textil, $step_groesse, $step_quantity, $step_contact ] ],
    [ 'Kinder T-Shirt', 14.90, [ $step_farbe_textil, $step_groesse, $step_quantity, $step_contact ] ],
];
foreach ( $textilien as $p ) $products[] = [ 'name' => $p[0], 'price' => $p[1], 'cat' => 'Textilien', 'config' => $p[2] ];

// --- Give-aways (12) ---
$giveaways = [
    [ 'Kugelschreiber Basic', 0.89, [ $step_farbe_giveaway, $step_quantity, $step_contact ] ],
    [ 'Kugelschreiber Premium Metall', 3.90, [ $step_farbe_giveaway, $step_logo_datei, $step_quantity, $step_contact ] ],
    [ 'Stoffbeutel / Jutebeutel', 2.90, [ $step_farbe_giveaway, $step_logo_datei, $step_quantity, $step_contact ] ],
    [ 'Lanyard / Schlüsselband', 1.49, [ $step_farbe_giveaway, $step_quantity, $step_contact ] ],
    [ 'USB-Stick 16GB', 6.90, [ $step_logo_datei, $step_quantity, $step_contact ] ],
    [ 'Trinkflasche 500ml', 5.90, [ $step_farbe_giveaway, $step_logo_datei, $step_quantity, $step_contact ] ],
    [ 'Notizbuch A5', 3.50, null ],
    [ 'Regenschirm', 8.90, [ $step_farbe_giveaway, $step_quantity, $step_contact ] ],
    [ 'Powerbank 5000mAh', 12.90, [ $step_logo_datei, $step_quantity, $step_contact ] ],
    [ 'Feuerzeug bedruckt', 0.59, null ],
    [ 'Schlüsselanhänger', 1.20, [ $step_farbe_giveaway, $step_quantity, $step_contact ] ],
    [ 'Mauspad', 2.40, [ $step_logo_datei, $step_quantity, $step_contact ] ],
];
foreach ( $giveaways as $p ) $products[] = [ 'name' => $p[0], 'price' => $p[1], 'cat' => 'Give-aways', 'config' => $p[2] ];

// ── Produkte anlegen ─────────────────────────────────────────────────────────

$created = 0;
foreach ( $products as $def ) {
    $product = new WC_Product_Simple();
    $product->set_name( 'Test – ' . $def['name'] );
    $product->set_status( 'publish' );
    $product->set_catalog_visibility( 'visible' );
    $product->set_regular_price( (string) $def['price'] );
    $product->set_manage_stock( true );
    $product->set_stock_quantity( 250 );
    $product->set_stock_status( 'instock' );
    if ( ! empty( $category_ids[ $def['cat'] ] ) ) {
        $product->set_category_ids( [ $category_ids[ $def['cat'] ] ] );
    }
    $product_id = $product->save();

    if ( $def['config'] !== null ) {
        update_field( 'is_configurable', true, $product_id );
        update_field( 'config_type', 'standard', $product_id );
        update_field( 'config_steps', $def['config'], $product_id );
        update_field( 'show_tier_table', true, $product_id ); // Staffelpreis-Tabelle beim Mengen-Step sichtbar machen
    }

    $created++;
}

WP_CLI::success( "{$created} Testprodukte angelegt (Drucksorten/Textilien/Give-aways, gemischt einfach + konfigurierbar)." );
WP_CLI::log( "Konfigurierbare Produkte zum gezielten Testen (Kontaktdaten-Step, Datei-Upload, mehrere Auswahl-Steps): siehe Shop-Kategorien." );
