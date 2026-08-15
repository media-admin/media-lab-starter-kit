<?php
/**
 * AJAX Search Handler
 * 
 * @package Agency_Core
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX Search Handler
 */
add_action('wp_ajax_agency_search', 'agency_core_ajax_search');
add_action('wp_ajax_nopriv_agency_search', 'agency_core_ajax_search');

/**
 * Hebt alle Treffer des Suchbegriffs (wortweise) in einem Text per <mark>
 * hervor. Escaped den Text zuerst selbst (esc_html) und markiert danach -
 * das <mark>-Tag ist also gezielt das einzige erlaubte HTML im Ergebnis,
 * kein durchgereichtes User-Input-HTML.
 *
 * Das Frontend (ajax-search.js) rendert 'title'/'excerpt' bereits jetzt
 * ungeschützt als HTML (siehe displayResults() im Theme) - das war schon
 * vor diesem Patch so, hier wird also kein neues Risiko eingeführt, nur
 * die Escaping-Disziplin an dieser einen Stelle nachgezogen.
 */
function agency_core_highlight_search_term( string $text, string $query ): string {
    $escaped = esc_html( $text );

    $terms = array_filter( array_map( 'trim', explode( ' ', $query ) ), function( $t ) {
        return $t !== '' && mb_strlen( $t ) >= 2; // einzelne Buchstaben nicht markieren (zu viel Rauschen)
    } );
    if ( empty( $terms ) ) return $escaped;

    $pattern = '/(' . implode( '|', array_map( function( $t ) {
        return preg_quote( esc_html( $t ), '/' );
    }, $terms ) ) . ')/iu';

    $highlighted = preg_replace( $pattern, '<mark>$1</mark>', $escaped );

    // preg_replace gibt bei internem Fehler (z.B. ungültige UTF-8-Sequenz) null zurück -
    // dann lieber den unmarkierten, aber garantiert escapten Text zeigen als nichts.
    return $highlighted !== null ? $highlighted : $escaped;
}

/**
 * Baut einen Kontext-Ausschnitt um die erste Fundstelle des Suchbegriffs im
 * vollen Content herum (statt wie bisher immer nur den Anfang zu zeigen -
 * wp_trim_words() kennt keine Fundstelle, nimmt einfach die ersten N Wörter).
 * Fällt auf den übergebenen Fallback-Text zurück, wenn der Begriff im
 * Content gar nicht wörtlich vorkommt - z.B. wenn nur der Titel gematcht
 * hat, oder WordPress' Relevanz-Suche über Stemming/Teilwörter gefunden hat,
 * was hier nicht nachgebildet wird.
 *
 * WICHTIG: durchsucht nur post_content (wie WP_Query's 's'-Parameter das
 * auch tut) - KEINE Custom Fields, KEINE WooCommerce-Attribute (pa_*-
 * Taxonomien). Ein Treffer auf "Größe: XL" als Produktattribut würde hier
 * z.B. nicht gefunden, wenn "XL" nicht auch im Beschreibungstext steht.
 */
function agency_core_get_context_excerpt( string $content, string $query, string $fallback, int $words_around = 10 ): string {
    $content = wp_strip_all_tags( strip_shortcodes( $content ) );
    $content = preg_replace( '/\s+/u', ' ', trim( $content ) );

    if ( $content === '' ) return $fallback;

    $terms = array_filter( array_map( 'trim', explode( ' ', $query ) ), function( $t ) {
        return mb_strlen( $t ) >= 2;
    } );
    if ( empty( $terms ) ) return $fallback;

    $words = preg_split( '/\s+/u', $content );
    $match_index = null;

    foreach ( $words as $i => $word ) {
        foreach ( $terms as $term ) {
            if ( mb_stripos( $word, $term ) !== false ) {
                $match_index = $i;
                break 2;
            }
        }
    }

    if ( $match_index === null ) {
        return $fallback;
    }

    $start = max( 0, $match_index - $words_around );
    $end   = min( count( $words ) - 1, $match_index + $words_around );

    $snippet = implode( ' ', array_slice( $words, $start, $end - $start + 1 ) );

    if ( $start > 0 )                 $snippet = '… ' . $snippet;
    if ( $end < count( $words ) - 1 ) $snippet = $snippet . ' …';

    return $snippet;
}

/**
 * Sucht Produkte, deren WooCommerce-Attribute (pa_*-Taxonomien, z.B. Farbe,
 * Größe, Material) auf den Suchbegriff passen - unabhängig davon, ob der
 * Begriff auch im Beschreibungstext steht. WP_Query's 's'-Parameter kann
 * das von Haus aus nicht (durchsucht nur Titel/Excerpt/Content, keine
 * Taxonomie-Terms) - deshalb eine separate Such-Runde über get_terms()
 * + get_objects_in_term() pro Attribut-Taxonomie.
 *
 * @return array<int,string> post_id => "Attribut-Label: Wert" (z.B. "Farbe: Rot"),
 *                            als fertiger Anzeige-Text fürs Excerpt-Feld.
 */
function agency_core_search_product_attributes( string $query ): array {
    if ( ! function_exists( 'wc_get_attribute_taxonomy_names' ) ) return array();

    $matches = array();

    foreach ( wc_get_attribute_taxonomy_names() as $taxonomy ) {
        if ( ! taxonomy_exists( $taxonomy ) ) continue;

        $terms = get_terms( array(
            'taxonomy'   => $taxonomy,
            'name__like' => $query,
            'hide_empty' => true,
        ) );
        if ( is_wp_error( $terms ) || empty( $terms ) ) continue;

        // Menschlich lesbarer Attribut-Name statt technischem "pa_farbe" -
        // WooCommerce speichert den Klartext-Namen in wc_attribute_taxonomies.
        $attribute_label = wc_attribute_label( $taxonomy );

        foreach ( $terms as $term ) {
            $product_ids = get_objects_in_term( $term->term_id, $taxonomy );
            if ( is_wp_error( $product_ids ) ) continue;

            foreach ( $product_ids as $product_id ) {
                if ( get_post_status( $product_id ) !== 'publish' ) continue;

                // Erster passender Attribut-Wert gewinnt, falls ein Produkt
                // zufällig mehrere Treffer hätte - selten genug, dass das
                // als Regel reicht statt alle zu sammeln und zu verketten.
                if ( ! isset( $matches[ $product_id ] ) ) {
                    $matches[ $product_id ] = sprintf( '%s: %s', $attribute_label, $term->name );
                }
            }
        }
    }

    return $matches;
}

/**
 * Sucht Produkte mit LOKALEN/benutzerdefinierten Attributen (direkt am
 * Produkt eingetragen, KEINE eigene pa_*-Taxonomie dahinter - z.B. schnell
 * angelegte "Farbe: Rot | Blau" ohne globales Attribut-System).
 * agency_core_search_product_attributes() oben findet nur Taxonomie-basierte
 * (globale) Attribute - lokale stecken stattdessen serialisiert im
 * '_product_attributes'-Post-Meta, mit 'is_taxonomy' => 0 pro Eintrag.
 *
 * Grober LIKE-Vorfilter auf den rohen serialisierten Meta-String verhindert
 * einen Vollscan/unserialize() aller Produkte - LIKE funktioniert trotz
 * Serialisierung, weil der Wert als Klartext-Substring im Blob steht
 * (z.B. s:11:"Rot | Blau";).
 *
 * @return array<int,string> post_id => "Attribut-Label: Wert"
 */
function agency_core_search_local_product_attributes( string $query, int $limit ): array {
    $matches = array();

    $candidate_ids = get_posts( array(
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => max( $limit * 10, 50 ), // grobe Obergrenze gegen Vollscan bei sehr großen Katalogen
        'fields'         => 'ids',
        'meta_query'     => array(
            array(
                'key'     => '_product_attributes',
                'value'   => $query,
                'compare' => 'LIKE',
            ),
        ),
    ) );

    foreach ( $candidate_ids as $product_id ) {
        $attributes = get_post_meta( $product_id, '_product_attributes', true );
        if ( ! is_array( $attributes ) ) continue;

        foreach ( $attributes as $attribute ) {
            if ( ! empty( $attribute['is_taxonomy'] ) ) continue; // Taxonomie-Attribute laufen über die andere Funktion

            $raw_values = explode( '|', (string) ( $attribute['value'] ?? '' ) );

            foreach ( $raw_values as $raw_value ) {
                $value = trim( $raw_value );
                if ( $value !== '' && mb_stripos( $value, $query ) !== false ) {
                    $label = ! empty( $attribute['name'] ) ? $attribute['name'] : __( 'Attribut', 'media-lab-core' );
                    $matches[ $product_id ] = sprintf( '%s: %s', $label, $value );
                    break 2; // ein Treffer pro Produkt reicht
                }
            }
        }
    }

    return $matches;
}

/**
 * Sucht in den Konfigurator-Optionen konfigurierbarer Produkte
 * (media-lab-woocommerce/inc/configurator - ACF-Repeater config_steps →
 * options → value/label). Komplett eigene Datenstruktur, weder Taxonomie-
 * Term noch '_product_attributes'-Meta - deshalb eigene Suchfunktion.
 *
 * @return array<int,string> post_id => "Schritt-Label: Options-Label"
 */
function agency_core_search_configurator_options( string $query ): array {
    if ( ! function_exists( 'get_field' ) ) return array();

    $matches = array();

    $configurable_ids = get_posts( array(
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => 200, // grobe Obergrenze gegen Vollscan bei sehr vielen konfigurierbaren Produkten
        'fields'         => 'ids',
        'meta_query'     => array(
            array(
                'key'   => 'is_configurable',
                'value' => '1',
            ),
        ),
    ) );

    foreach ( $configurable_ids as $product_id ) {
        $steps = get_field( 'config_steps', $product_id );
        if ( ! is_array( $steps ) ) continue;

        foreach ( $steps as $step ) {
            $options = $step['options'] ?? array();
            if ( ! is_array( $options ) ) continue;

            foreach ( $options as $option ) {
                $label = (string) ( $option['label'] ?? '' );
                $value = (string) ( $option['value'] ?? '' );

                $matched_text = null;
                if ( $label !== '' && mb_stripos( $label, $query ) !== false ) {
                    $matched_text = $label;
                } elseif ( $value !== '' && mb_stripos( $value, $query ) !== false ) {
                    $matched_text = $value;
                }

                if ( $matched_text !== null ) {
                    $step_label = ( ! empty( $step['step_label'] ) ) ? $step['step_label'] : __( 'Option', 'media-lab-core' );
                    $matches[ $product_id ] = sprintf( '%s: %s', $step_label, $matched_text );
                    break 2; // ein Treffer pro Produkt reicht
                }
            }
        }
    }

    return $matches;
}

function agency_core_ajax_search() {
    if ( ! medialab_check_rate_limit( 'ajax_search', 20, 60 ) ) {
        wp_send_json_error( array( 'message' => 'Too many requests. Please try again later.' ), 429 );
    }

    // Verify nonce - MUSS MIT functions.php ÜBEREINSTIMMEN!
    check_ajax_referer('agency_search_nonce', 'nonce');
    
    // Get search query
    $search_query = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';
    
    if (empty($search_query) || strlen($search_query) < 2) {
        wp_send_json_error(array(
            'message' => 'Search query too short'
        ));
    }
    
    // Get post types - handle both string and array format
    $post_types = array('post', 'page', 'product'); // Default
    
    if (isset($_POST['post_types'])) {
        // wp_unslash() ZWINGEND vor json_decode(): WordPress hängt über
        // wp_magic_quotes() automatisch addslashes() an jedes $_POST-Feld
        // (Kern-Kompatibilitätsverhalten, betrifft ausnahmslos jeden Request).
        // Ohne diesen Schritt kommt hier statt '["post","page","product"]'
        // '[\"post\",\"page\",\"product\"]' an - json_decode() scheitert daran
        // (Syntax error), der Fallback explode() zerlegt den kaputten String
        // in drei genauso kaputte Teile statt sauberer post_type-Werte.
        // Gleiches Muster wie media-lab-woocommerce/inc/wishlist/class-ajax.php
        // ::decode_json() (dort via stripslashes() gelöst - wp_unslash() ist
        // die WP-native Variante, funktional identisch für einen String).
        $raw = wp_unslash( $_POST['post_types'] );
        
        // Handle string (e.g., "post,page,product")
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $raw = $decoded;
            } else {
                $raw = array_map('trim', explode(',', $raw));
            }
        }
        
        if (is_array($raw)) {
            $post_types = array_map('sanitize_text_field', $raw);
            $post_types = array_filter($post_types);
        }
    }
    
    // Get limit
    $limit = isset($_POST['limit']) ? absint($_POST['limit']) : 5;
    
    // ── 1. Regulärer Titel/Content/Excerpt-Match (WP_Query 's') ──────────────
    $args = array(
        'post_type' => $post_types,
        'post_status' => 'publish',
        'posts_per_page' => $limit,
        's' => $search_query,
        'orderby' => 'relevance',
        'order' => 'DESC',
    );
    
    $content_query = new WP_Query($args);
    $content_ids   = wp_list_pluck( $content_query->posts, 'ID' );

    // ── 2. Zusätzlich: WooCommerce-Produktattribute (global + lokal +
    //    Konfigurator-Optionen) ──────────────────────────────────────────────
    $attribute_matches = array();
    if ( in_array( 'product', $post_types, true ) && class_exists( 'WooCommerce' ) ) {
        $attribute_matches = agency_core_search_product_attributes( $search_query );
        // += statt array_merge: bestehende Keys (frühere Treffer) bleiben
        // Vorrang, es werden nur Produkte ergänzt, die noch nicht drin sind.
        $attribute_matches += agency_core_search_local_product_attributes( $search_query, $limit );

        // Kein class_exists()-Gate hier (anders als bei den beiden Funktionen
        // oben) - agency_core_search_configurator_options() braucht nur
        // get_field(), keine Instanz der Configurator-Klasse. Ein Gate auf
        // eine Klasse, die die Funktion gar nicht nutzt, wäre nur ein
        // zusätzlicher, unnötiger Fehlerpunkt (falsches Verhalten, falls die
        // Klasse im AJAX-Kontext aus irgendeinem Grund nicht/später geladen wird).
        $attribute_matches += agency_core_search_configurator_options( $search_query );
    }

    // ── IDs zusammenführen: Content-Treffer zuerst (Relevanz-sortiert von
    //    WP_Query), danach Attribut-Treffer die noch nicht dabei sind ────────
    $all_ids = $content_ids;
    foreach ( array_keys( $attribute_matches ) as $pid ) {
        if ( ! in_array( $pid, $all_ids, true ) ) {
            $all_ids[] = $pid;
        }
    }
    $all_ids = array_slice( $all_ids, 0, $limit );

    $results = array();

    foreach ( $all_ids as $post_id ) {
        // WICHTIG: setup_postdata() setzt NICHT selbst die globale $post-
        // Variable (bekannter WP-Stolperstein bei Custom Loops) - ohne die
        // explizite Zuweisung hier liefern get_the_title()/get_the_content()/
        // get_post_type() etc. leere bzw. false-Werte zurück, weil sie intern
        // über get_post() auf $GLOBALS['post'] zugreifen, nicht auf die
        // Variablen, die setup_postdata() selbst befüllt ($id, $authordata, ...).
        global $post;
        $post = get_post( $post_id );
        if ( ! $post ) continue;

        setup_postdata( $post );

        if ( isset( $attribute_matches[ $post_id ] ) && ! in_array( $post_id, $content_ids, true ) ) {
            // Reiner Attribut-Treffer (nicht auch über Content gefunden):
            // Attribut+Wert zeigen statt Content-Ausschnitt - das IST hier
            // die relevante Fundstelle, ein Content-Ausschnitt würde den
            // Suchbegriff ja gar nicht enthalten.
            $context_excerpt = $attribute_matches[ $post_id ];
        } else {
            $fallback_excerpt = wp_trim_words( get_the_excerpt(), 15 );
            $context_excerpt  = agency_core_get_context_excerpt( get_the_content(), $search_query, $fallback_excerpt );
        }

        $result = array(
            'id' => get_the_ID(),
            'title' => agency_core_highlight_search_term( get_the_title(), $search_query ),
            'permalink' => get_permalink(),
            'excerpt' => agency_core_highlight_search_term( $context_excerpt, $search_query ),
            'date' => get_the_date('d.m.Y'),
            'post_type' => get_post_type(),
            'thumbnail' => get_the_post_thumbnail_url(get_the_ID(), 'thumbnail'),
        );
        
        // Allow plugins to extend result data (e.g. WooCommerce)
        $result = apply_filters('media_lab_ajax_search_result', $result, get_the_ID(), get_post_type());
        
        $results[] = $result;
    }
    
    wp_reset_postdata();
    
    if (!empty($results)) {
        wp_send_json_success(array(
            'results' => $results,
            'count' => count($results),
            'query' => $search_query,
        ));
    } else {
        wp_send_json_success(array(
            'results' => array(),
            'count' => 0,
            'message' => 'No results found',
        ));
    }
}
