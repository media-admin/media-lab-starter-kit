<?php
/**
 * Erweiterte Suche auf der Suchergebnisseite (search.php).
 *
 * Problem: Die Live-Suche (inc/ajax-search.php) findet mehr als die normale
 * WordPress-Suche - Synonyme, Produktattribute (global + lokal),
 * Konfigurator-Optionen und (per Fuzzy-Fallback) Tippfehler bei Produktcodes.
 * Die Ergebnisseite nutzt aber die native Haupt-Abfrage ('s') und fand dadurch
 * weniger als das Dropdown.
 *
 * Lösung: Die Haupt-Abfrage bleibt unangetastet (Sortierung, Pagination,
 * Post-Types, Sprache, Zähler) - zusätzlich werden die Treffer der
 * erweiterten Suche als weitere IDs per OR an die Such-Bedingung gehängt
 * (Filter posts_search). Verwendet werden dieselben Bausteine und Hooks wie
 * in der Live-Suche:
 *   - media_lab_ajax_search_query_expansion  (Synonyme)
 *   - agency_core_search_product_attributes() / _local_product_attributes()
 *     / _configurator_options()                (WooCommerce)
 *   - media_lab_ajax_search_extra_matches     (Fuzzy-Produktcodes, nur wenn sonst nichts gefunden)
 *
 * Schaltbar: Agency Core -> Suche / Live-Suche -> Ergebnisseite ->
 * "Erweiterte Suche auf der Ergebnisseite" (WooCommerce-Attribute zusätzlich
 * über "Verhalten -> WooCommerce-Attribute durchsuchen").
 *
 * Datei ablegen unter: inc/search/class-serp-search.php
 * Einbindung: require_once in media-lab-agency-core.php + MediaLab_Serp_Search::init()
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MediaLab_Serp_Search {

    /** Obergrenze zusätzlicher Treffer-IDs (Schutz vor riesigen IN()-Listen). */
    const MAX_EXTRA_IDS = 300;

    /** @var int[] */
    private static array $extra_ids = [];

    /** @var array<int,string> post_id => "Attribut: Wert" */
    private static array $attribute_labels = [];

    public static function init(): void {
        // Nach MediaLab_Search_Settings::apply_serp_query() (Priorität 10)
        add_action( 'pre_get_posts', [ __CLASS__, 'prepare' ], 20 );
        add_filter( 'posts_search', [ __CLASS__, 'extend_search_sql' ], 10, 2 );
    }

    /**
     * "Attribut: Wert"-Text, falls der Treffer nur über ein Produktattribut /
     * eine Konfigurator-Option gefunden wurde (für den Karten-Ausschnitt).
     */
    public static function attribute_label( int $post_id ): string {
        return self::$attribute_labels[ $post_id ] ?? '';
    }

    public static function prepare( \WP_Query $query ): void {
        if ( is_admin() || ! $query->is_main_query() || ! $query->is_search() ) return;
        if ( ! class_exists( 'MediaLab_Search_Settings' ) ) return;

        $serp = MediaLab_Search_Settings::serp();
        if ( empty( $serp['extended'] ) ) return;

        $search = sanitize_text_field( wp_unslash( (string) $query->get( 's' ) ) );
        if ( mb_strlen( $search ) < 2 ) return;

        $cfg  = MediaLab_Search_Settings::get();
        $lang = MediaLab_Search_Settings::current_lang();

        // Post-Types: wie die Haupt-Abfrage, immer gegen durchsuchbare Typen geprüft
        $types = $query->get( 'post_type' );
        $types = ( $types && $types !== 'any' ) ? (array) $types : MediaLab_Search_Settings::searchable_post_types();
        $types = MediaLab_Search_Settings::sanitize_post_types( $types );

        $lang_args = MediaLab_Search_Settings::apply_language( [], $lang );

        // ── 1. Synonyme / Begriffs-Erweiterung (Filter aus der Live-Suche) ───
        $terms = apply_filters( 'media_lab_ajax_search_query_expansion', [ $search ], $search );
        $extra = [];

        foreach ( (array) $terms as $term ) {
            if ( ! is_string( $term ) || $term === '' || $term === $search ) continue; // Original deckt die Haupt-Abfrage ab

            $sub = new \WP_Query( array_merge( [
                'post_type'      => $types,
                'post_status'    => 'publish',
                's'              => $term,
                'fields'         => 'ids',
                'posts_per_page' => self::MAX_EXTRA_IDS,
                'no_found_rows'  => true,
            ], $lang_args ) );

            $extra = array_merge( $extra, array_map( 'intval', $sub->posts ) );
        }

        // ── 2. WooCommerce: Attribute + Konfigurator-Optionen ─────────────────
        $attribute_matches = [];
        $has_products      = in_array( 'product', $types, true ) && class_exists( 'WooCommerce' );

        if ( $has_products && ! empty( $cfg['woo_attributes'] ) ) {
            if ( function_exists( 'agency_core_search_product_attributes' ) ) {
                $attribute_matches = agency_core_search_product_attributes( $search );
            }
            if ( function_exists( 'agency_core_search_local_product_attributes' ) ) {
                $attribute_matches += agency_core_search_local_product_attributes( $search, 50 );
            }
            if ( function_exists( 'agency_core_search_configurator_options' ) ) {
                $attribute_matches += agency_core_search_configurator_options( $search );
            }
        }

        // ── 3. Fuzzy-Fallback (Produktcodes), nur wenn sonst nichts gefunden wird ──
        if ( $has_products && empty( $extra ) && empty( $attribute_matches ) ) {
            $native = new \WP_Query( array_merge( [
                'post_type'      => $types,
                'post_status'    => 'publish',
                's'              => $search,
                'fields'         => 'ids',
                'posts_per_page' => 1,
                'no_found_rows'  => true,
            ], $lang_args ) );

            if ( empty( $native->posts ) ) {
                $attribute_matches += (array) apply_filters( 'media_lab_ajax_search_extra_matches', [], $search, 50 );
            }
        }

        // ── Zusammenführen, filtern, begrenzen ───────────────────────────────
        $ids = array_values( array_unique( array_merge( $extra, array_map( 'intval', array_keys( $attribute_matches ) ) ) ) );
        $ids = MediaLab_Search_Settings::filter_ids_by_language( $ids, $lang );
        $ids = array_values( array_filter( $ids, static fn( $id ) => ! post_password_required( $id ) ) );
        $ids = array_slice( $ids, 0, self::MAX_EXTRA_IDS );

        self::$extra_ids        = $ids;
        self::$attribute_labels = array_intersect_key( array_map( 'strval', $attribute_matches ), array_flip( $ids ) );
    }

    /**
     * Hängt die zusätzlichen IDs per OR an die Such-Bedingung der Haupt-Abfrage.
     * Post-Type-, Status- und Sprach-Bedingungen der Abfrage bleiben unverändert
     * als eigene AND-Bedingungen bestehen.
     */
    public static function extend_search_sql( string $search, \WP_Query $query ): string {
        if ( $search === '' || empty( self::$extra_ids ) ) return $search;
        if ( ! $query->is_main_query() || ! $query->is_search() ) return $search;

        global $wpdb;

        $ids   = implode( ',', array_map( 'intval', self::$extra_ids ) );
        $inner = preg_replace( '/^\s*AND\s*/i', '', $search, 1 );

        return " AND ( ( {$inner} ) OR {$wpdb->posts}.ID IN ({$ids}) ) ";
    }
}
