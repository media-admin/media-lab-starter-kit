<?php
/**
 * Template Part: Such-Ergebnis (eine Karte)
 *
 * Bereitet die Daten eines Treffers auf (Inhaltstyp-Badge, Preis,
 * Kontext-Ausschnitt, Hervorhebung) und rendert ihn über die ganz normale
 * Post-Card (template-parts/components/post-card.php). Es gibt bewusst kein
 * eigenes Such-Markup: Aussehen und Pflege laufen über .post-card.
 *
 * Anzeige-Optionen (Vorschaubild, Typ, Datum, Ausschnitt, Preis,
 * Hervorhebung) kommen aus den Such-Einstellungen im Plugin
 * (Agency Core → Suche / Live-Suche) - dieselben wie bei der Live-Suche.
 *
 * Eigene Darstellung pro Inhaltstyp: Datei
 *   template-parts/search/card-{post_type}.php
 * anlegen (z. B. card-product.php) - sie ersetzt dann diese Standard-Karte.
 *
 * @package custom-theme
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$post_type = get_post_type();

// Per-Type-Override
$override = locate_template( 'template-parts/search/card-' . sanitize_key( $post_type ) . '.php' );
if ( $override ) {
    load_template( $override, false );
    return;
}

// Einstellungen (Fallback-Defaults, falls das Plugin fehlt)
$has_settings = class_exists( 'MediaLab_Search_Settings' );
$cfg          = $has_settings ? MediaLab_Search_Settings::get() : [];
$serp         = $has_settings ? MediaLab_Search_Settings::serp() : [];

$show = wp_parse_args( $cfg['show'] ?? [], [
    'thumbnail' => true,
    'date'      => true,
    'type'      => true,
    'excerpt'   => true,
    'price'     => true,
] );
$highlight    = $cfg['highlight'] ?? true;
$words_around = (int) ( $cfg['excerpt_words'] ?? 10 );
$type_labels  = $cfg['text']['type_labels'] ?? [];

$query        = get_search_query( false );
$can_highlight = $highlight && $query !== '' && function_exists( 'agency_core_highlight_search_term' );

// Inhaltstyp-Label
$pto        = get_post_type_object( $post_type );
$type_label = $type_labels[ $post_type ] ?? ( $pto ? $pto->labels->singular_name : ucfirst( $post_type ) );

// Titel (mit Hervorhebung)
$title_html = $can_highlight ? agency_core_highlight_search_term( get_the_title(), $query ) : '';

// Ausschnitt: Kontext um die Fundstelle (wie die Live-Suche), sonst Textanfang
$excerpt_html = '';
if ( $show['excerpt'] ) {
    $fallback = wp_trim_words( get_the_excerpt(), $words_around + 5, '…' );
    $excerpt  = ( $query !== '' && function_exists( 'agency_core_get_context_excerpt' ) )
        ? agency_core_get_context_excerpt( get_the_content(), $query, $fallback, $words_around )
        : $fallback;

    // Nur über ein Produktattribut / eine Konfigurator-Option gefunden (Begriff steht nicht im
    // Text)? Dann "Attribut: Wert" zeigen - wie in der Live-Suche.
    $attr_label = class_exists( 'MediaLab_Serp_Search' ) ? MediaLab_Serp_Search::attribute_label( get_the_ID() ) : '';
    if ( $attr_label !== '' && $query !== '' && mb_stripos( get_the_title() . ' ' . wp_strip_all_tags( get_the_content() ), $query ) === false ) {
        $excerpt = $attr_label;
    }

    if ( $excerpt !== '' ) {
        $excerpt_html = $can_highlight ? agency_core_highlight_search_term( $excerpt, $query ) : esc_html( $excerpt );
    }
}

// Preis (WooCommerce)
$price_html = '';
if ( $show['price'] && $post_type === 'product' && function_exists( 'wc_get_product' ) ) {
    $product = wc_get_product( get_the_ID() );
    if ( $product ) {
        $price_html = $product->get_price_html();
    }
}

set_query_var( 'post_card_variant', ( $serp['layout'] ?? 'grid' ) === 'list' ? 'horizontal' : 'default' );
set_query_var( 'post_card_type', $post_type );
set_query_var( 'post_card_badge', $show['type'] ? $type_label : '' );
set_query_var( 'post_card_price', $price_html );
set_query_var( 'post_card_title_html', $title_html );
set_query_var( 'post_card_excerpt_html', $excerpt_html );
set_query_var( 'post_card_link_label', __( 'Mehr erfahren', 'custom-theme' ) );
set_query_var( 'post_card_show', [
    'thumbnail' => (bool) $show['thumbnail'],
    'excerpt'   => (bool) $show['excerpt'],
    'date'      => (bool) $show['date'],
    'author'    => false,
    'link'      => true,
] );

get_template_part( 'template-parts/components/post-card' );
