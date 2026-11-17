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
 *   - media_lab_ajax_search_query_args        (Query-Args, z. B. Produkt-Sichtbarkeit)
 *   - agency_core_search_product_attributes() / _local_product_attributes()
 *     / _configurator_options()                (WooCommerce)
 *   - media_lab_search_woo_matches            (SKU, Kategorien, Marken ... aus dem Woo-Plugin)
 *   - media_lab_ajax_search_extra_matches     (Fuzzy-Produktcodes, nur wenn sonst nichts gefunden)
 *   - media_lab_search_result_ids              (ID-Bereinigung, Kontext 'serp' bzw. 'matching')
 *
 * Mehrere Begriffe (seit 1.39.0): Enthält die Anfrage mehrere Begriffe, UND/ODER, einen
 * Ausschluss (-wort) oder eine Phrase ("a b"), wird jeder Begriff einzeln aufgelöst und per
 * Mengenlogik verknüpft (MediaLab_Search_Query). Die native Such-Bedingung der Haupt-Abfrage wird
 * dann durch die fertige ID-Liste ersetzt. Einzelne Wörter laufen unverändert wie bisher.
 *
 * Teilwort-Regel und Rangfolge (seit 1.39.1, Einstellungen unter Suche / Live-Suche -> Verhalten):
 *   - Kurze Begriffe (Standard: unter 4 Buchstaben) zählen im Text nur am Wortanfang
 *     ("rot" findet "Rotwein", nicht "Trotter"). Merkmale, Kategorien, Marken und SKU bleiben
 *     Teilstring-Treffer. Ein solcher Begriff läuft ebenfalls über diesen Weg (term_data()).
 *   - Bei Sortierung "Relevanz" stehen ganze Wörter im Titel und Merkmal-Treffer vor Teilstring-
 *     Treffern (MediaLab_Search_Query::rank(), ranked_orderby()).
 *
 * Zusätzlich: matching_ids() liefert dieselbe Treffermenge als ID-Liste,
 * unabhängig von der Haupt-Abfrage. Gedacht für Facetten-Filter (Produktfilter
 * auf der Ergebnisseite: Werte und Anzahlen aus ALLEN gefundenen Produkten) und
 * deren AJAX-Handler - beide arbeiten so garantiert mit derselben Treffermenge.
 * extra_ids() gibt die über die Erweiterung gefundenen IDs der Haupt-Abfrage zurück
 * (z. B. fürs Priming des Produkttyp-Caches im Theme).
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

    /**
     * Obergrenze zusätzlicher Treffer-IDs der Haupt-Abfrage (Schutz vor riesigen IN()-Listen).
     * Gleich groß wie der Standard von matching_ids() (Produktfilter), damit Ergebnisseite und
     * Filter dieselbe Treffermenge haben.
     */
    const MAX_EXTRA_IDS = 5000;

    /**
     * Obergrenze pro Einzelbegriff bei Mehrbegriff-Suchen (UND / ODER / Ausschluss).
     * Bewusst größer als die Ergebnis-Obergrenze: Wird die Menge eines Begriffs vor dem
     * Schneiden abgeschnitten, fehlen Treffer im Ergebnis.
     */
    const TERM_CAP = 10000;

    /** Mehrbegriff-Suche aktiv: die Such-Bedingung der Haupt-Abfrage wird komplett ersetzt. */
    private static bool $advanced = false;

    /** Rangfolge der Treffer (ganze Wörter und Merkmale zuerst) für die Haupt-Abfrage aktiv. */
    private static bool $ranked = false;

    /** @var int[] */
    private static array $extra_ids = [];

    /** @var array<int,string> post_id => "Attribut: Wert" */
    private static array $attribute_labels = [];

    public static function init(): void {
        // Nach MediaLab_Search_Settings::apply_serp_query() (Priorität 10)
        add_action( 'pre_get_posts', [ __CLASS__, 'prepare' ], 20 );
        add_filter( 'posts_search', [ __CLASS__, 'extend_search_sql' ], 10, 2 );
        // Vor MediaLab_Search_Settings::filter_orderby_type_priority() (Priorität 10), das davorschaltet
        add_filter( 'posts_orderby', [ __CLASS__, 'ranked_orderby' ], 5, 2 );
    }

    /**
     * "Attribut: Wert"-Text, falls der Treffer nur über ein Produktattribut /
     * eine Konfigurator-Option / die SKU gefunden wurde (für den Karten-Ausschnitt).
     */
    public static function attribute_label( int $post_id ): string {
        return self::$attribute_labels[ $post_id ] ?? '';
    }

    /**
     * IDs, die die Erweiterung der Haupt-Abfrage hinzugefügt hat (nach prepare()).
     *
     * @return int[]
     */
    public static function extra_ids(): array {
        return self::$extra_ids;
    }

    public static function prepare( \WP_Query $query ): void {
        if ( is_admin() || ! $query->is_main_query() || ! $query->is_search() ) return;

        self::$advanced = false;
        self::$ranked   = false;

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

        // ── 0. Mehrere Begriffe / UND / ODER / Ausschluss / Phrase ───────────
        // Jeder Begriff wird einzeln über alle Quellen aufgelöst und per Mengenlogik
        // verknüpft (siehe MediaLab_Search_Query). Die native Such-Bedingung wird dann
        // in extend_search_sql() durch die fertige ID-Liste ersetzt.
        $parsed = self::parse_query( $search );
        if ( $parsed['advanced'] ) {
            $labels = [];
            $ids    = self::advanced_ids( $parsed, $types, $lang, self::MAX_EXTRA_IDS, 'serp', $labels );

            self::$advanced         = true;
            self::$ranked           = ! empty( $parsed['rank'] );
            self::$extra_ids        = $ids;
            self::$attribute_labels = array_intersect_key( $labels, array_flip( $ids ) );
            return;
        }

        // ── 1. Synonyme / Begriffs-Erweiterung (Filter aus der Live-Suche) ───
        $terms = apply_filters( 'media_lab_ajax_search_query_expansion', [ $search ], $search );
        $extra = [];

        foreach ( (array) $terms as $term ) {
            if ( ! is_string( $term ) || $term === '' || $term === $search ) continue; // Original deckt die Haupt-Abfrage ab

            $args = array_merge( [
                'post_type'      => $types,
                'post_status'    => 'publish',
                's'              => $term,
                'fields'         => 'ids',
                'posts_per_page' => self::MAX_EXTRA_IDS,
                'no_found_rows'  => true,
            ], $lang_args );
            $args = apply_filters( 'media_lab_ajax_search_query_args', $args, $term );

            $sub   = new \WP_Query( $args );
            $extra = array_merge( $extra, array_map( 'intval', $sub->posts ) );
        }

        // ── 2. WooCommerce: Attribute, Konfigurator-Optionen, Woo-Extras ─────
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
        if ( $has_products ) {
            // SKU (Teilstring), Kategorien, Marken ... (Woo-Plugin: search-woo-extras.php)
            $attribute_matches += (array) apply_filters( 'media_lab_search_woo_matches', [], $search, self::MAX_EXTRA_IDS );
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
        $ids = array_values( array_map( 'intval', (array) apply_filters( 'media_lab_search_result_ids', $ids, $search, 'serp' ) ) );
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
        if ( $search === '' ) return $search;
        if ( ! $query->is_main_query() || ! $query->is_search() ) return $search;

        global $wpdb;

        // Mehrbegriff-Suche: die native Bedingung kennt weder ODER noch Attribut-/SKU-Treffer
        // je Begriff - sie wird komplett durch die aufgelöste ID-Liste ersetzt.
        if ( self::$advanced ) {
            $ids  = array_map( 'intval', self::$extra_ids );
            $cond = $ids ? " AND ( {$wpdb->posts}.ID IN (" . implode( ',', $ids ) . ") ) " : ' AND ( 1 = 0 ) ';
            if ( ! is_user_logged_in() ) {
                $cond .= " AND ( {$wpdb->posts}.post_password = '' ) "; // wie WP_Query::parse_search()
            }
            return $cond;
        }

        if ( empty( self::$extra_ids ) ) return $search;

        $ids   = implode( ',', array_map( 'intval', self::$extra_ids ) );
        $inner = preg_replace( '/^\s*AND\s*/i', '', $search, 1 );

        return " AND ( ( {$inner} ) OR {$wpdb->posts}.ID IN ({$ids}) ) ";
    }

    /**
     * Mehrbegriff-Suche mit Rangfolge: Bei der Sortierung "Relevanz" gilt die Reihenfolge der
     * aufgelösten ID-Liste (ganze Wörter und Merkmale zuerst, siehe MediaLab_Search_Query::rank()).
     * Andere Sortierungen (Datum, Titel) bleiben unberührt.
     */
    public static function ranked_orderby( string $orderby, \WP_Query $query ): string {
        if ( ! self::$advanced || ! self::$ranked ) return $orderby;
        if ( ! $query->is_main_query() || ! $query->is_search() ) return $orderby;
        if ( $query->get( 'orderby' ) !== 'relevance' ) return $orderby;
        if ( count( self::$extra_ids ) < 2 ) return $orderby;

        global $wpdb;

        return "FIELD({$wpdb->posts}.ID, " . implode( ',', array_map( 'intval', self::$extra_ids ) ) . ') ASC';
    }

    /**
     * Alle Treffer-IDs der erweiterten Suche, unabhängig von der Haupt-Abfrage.
     *
     * Dieselben Bausteine wie die Live-Suche und prepare(): native Treffer
     * (inkl. Synonyme), Produktattribute / Konfigurator-Optionen, Woo-Extras
     * (Filter 'media_lab_search_woo_matches': SKU, Kategorien, Marken) und der
     * Fuzzy-Fallback für Produktcodes. Reihenfolge: native Treffer in Relevanz-
     * Reihenfolge zuerst, dann die übrigen.
     *
     * Läuft über sekundäre WP_Querys - die Haupt-Abfrage-Erweiterung
     * (extend_search_sql) greift dort bewusst nicht, es gibt keine Rekursion.
     *
     * Ergebnis wird pro Request und kurz in einem Transient gecacht, weil die
     * AJAX-Filter bei jeder Änderung dieselbe Menge brauchen.
     *   - 'media_lab_ajax_search_query_args'  Query-Args anpassen (z. B. Sichtbarkeit)
     *   - 'media_lab_search_result_ids'        ID-Liste filtern (letzter Parameter: 'matching')
     *   - 'media_lab_search_ids_cache_ttl'     Transient-Dauer in Sekunden (Standard 300, 0 = aus)
     *
     * @param string   $search Suchbegriff (Rohwert, wird bereinigt)
     * @param string[] $types  Post-Types (Standard: nur Produkte)
     * @param int      $cap    Obergrenze der IDs (1..20000)
     * @return int[]
     */
    public static function matching_ids( string $search, array $types = [ 'product' ], int $cap = 5000 ): array {
        static $memo = [];

        $search = sanitize_text_field( $search );
        if ( mb_strlen( $search ) < 2 || ! class_exists( 'MediaLab_Search_Settings' ) ) return [];

        $lang  = MediaLab_Search_Settings::current_lang();
        $types = MediaLab_Search_Settings::sanitize_post_types( $types );
        $cap   = max( 1, min( 20000, $cap ) );
        // Such-Optionen gehören in den Schlüssel: Eine Änderung im Backend soll nicht erst nach Ablauf des Zwischenspeichers wirken
        $opts  = class_exists( 'MediaLab_Search_Query' ) ? MediaLab_Search_Query::options() : [];
        $key   = md5( wp_json_encode( [ $search, $types, $lang, $cap, $opts ] ) );

        if ( isset( $memo[ $key ] ) ) return $memo[ $key ];

        $ttl = (int) apply_filters( 'media_lab_search_ids_cache_ttl', 300 );
        if ( $ttl > 0 ) {
            $cached = get_transient( 'mls_ids_' . $key );
            if ( is_array( $cached ) ) return $memo[ $key ] = $cached;
        }

        $cfg       = MediaLab_Search_Settings::get();
        $lang_args = MediaLab_Search_Settings::apply_language( [], $lang );
        $has_products = in_array( 'product', $types, true ) && class_exists( 'WooCommerce' );

        // ── 0. Mehrere Begriffe / UND / ODER / Ausschluss / Phrase ───────────
        $parsed = self::parse_query( $search );
        if ( $parsed['advanced'] ) {
            $labels = [];
            $ids    = self::advanced_ids( $parsed, $types, $lang, $cap, 'matching', $labels );

            if ( $ttl > 0 ) {
                set_transient( 'mls_ids_' . $key, $ids, $ttl );
            }
            return $memo[ $key ] = $ids;
        }

        // ── 1. Native Treffer + Synonyme (Relevanz-Reihenfolge) ──────────────
        $terms = (array) apply_filters( 'media_lab_ajax_search_query_expansion', [ $search ], $search );
        $ids   = [];

        foreach ( $terms as $term ) {
            if ( ! is_string( $term ) || $term === '' ) continue;

            $args = array_merge( [
                'post_type'      => $types,
                'post_status'    => 'publish',
                's'              => $term,
                'orderby'        => 'relevance',
                'fields'         => 'ids',
                'posts_per_page' => $cap,
                'no_found_rows'  => true,
            ], $lang_args );
            $args = apply_filters( 'media_lab_ajax_search_query_args', $args, $term );

            $q   = new \WP_Query( $args );
            $ids = array_merge( $ids, array_map( 'intval', $q->posts ) );
        }

        // ── 2. WooCommerce: Attribute, Konfigurator-Optionen, Woo-Extras ─────
        $extra = [];
        if ( $has_products && ! empty( $cfg['woo_attributes'] ) ) {
            if ( function_exists( 'agency_core_search_product_attributes' ) ) {
                $extra = agency_core_search_product_attributes( $search );
            }
            if ( function_exists( 'agency_core_search_local_product_attributes' ) ) {
                $extra += agency_core_search_local_product_attributes( $search, 50 );
            }
            if ( function_exists( 'agency_core_search_configurator_options' ) ) {
                $extra += agency_core_search_configurator_options( $search );
            }
        }
        if ( $has_products ) {
            $extra += (array) apply_filters( 'media_lab_search_woo_matches', [], $search, $cap );
        }
        $ids = array_merge( $ids, array_map( 'intval', array_keys( $extra ) ) );

        // ── 3. Fuzzy-Fallback (Produktcodes), nur wenn sonst nichts gefunden wird ──
        if ( $has_products && empty( $ids ) ) {
            $fuzzy = (array) apply_filters( 'media_lab_ajax_search_extra_matches', [], $search, 50 );
            $ids   = array_map( 'intval', array_keys( $fuzzy ) );
        }

        // ── Bereinigen ────────────────────────────────────────────────────────
        $ids = array_values( array_unique( $ids ) );
        $ids = MediaLab_Search_Settings::filter_ids_by_language( $ids, $lang );
        $ids = array_values( array_filter( $ids, static fn( $id ) => ! post_password_required( $id ) ) );
        $ids = (array) apply_filters( 'media_lab_search_result_ids', $ids, $search, 'matching' );
        $ids = array_slice( array_values( array_map( 'intval', $ids ) ), 0, $cap );

        if ( $ttl > 0 ) {
            set_transient( 'mls_ids_' . $key, $ids, $ttl );
        }

        return $memo[ $key ] = $ids;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Mehrbegriff-Suche (UND / ODER / Ausschluss / Phrase)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Zerlegt die Suchanfrage. Ohne Parser-Klasse: immer "einfach" (bisheriges Verhalten).
     */
    private static function parse_query( string $search ): array {
        if ( ! class_exists( 'MediaLab_Search_Query' ) ) {
            return [ 'raw' => $search, 'clauses' => [], 'exclude' => [], 'advanced' => false ];
        }
        return MediaLab_Search_Query::parse( $search );
    }

    /**
     * Treffer-IDs einer Mehrbegriff-Abfrage: jeder Begriff einzeln über alle Quellen,
     * danach Mengenlogik (UND = Schnitt, ODER = Vereinigung, Ausschluss = Differenz).
     *
     * @param string               $context 'serp' | 'matching' (3. Parameter von 'media_lab_search_result_ids')
     * @param array<int,string>    $labels  (Ausgabe) post_id => "Attribut: Wert"
     * @return int[]
     */
    public static function advanced_ids( array $parsed, array $types, string $lang, int $cap, string $context, array &$labels ): array {
        $attr_sets = [];

        $matcher = static function ( array $term ) use ( $types, $lang, &$labels, &$attr_sets ): array {
            $data = self::term_data( $term, $types, $lang );
            $labels += $data['labels'];
            $attr_sets[ MediaLab_Search_Query::term_key( $term ) ] = $data['attr'];
            return $data['ids'];
        };

        $ids = MediaLab_Search_Query::resolve( $parsed, $matcher );

        // Rangfolge: ganze Wörter im Titel und Merkmal-Treffer vor Teilstring-Treffern
        if ( ! empty( $parsed['rank'] ) && count( $ids ) > 1 ) {
            $ids = MediaLab_Search_Query::rank( $ids, $parsed, self::titles_for( $ids ), $attr_sets );
        }

        $ids = MediaLab_Search_Settings::filter_ids_by_language( $ids, $lang );
        $ids = array_values( array_filter( $ids, static fn( $id ) => ! post_password_required( $id ) ) );
        $ids = (array) apply_filters( 'media_lab_search_result_ids', $ids, $parsed['raw'], $context );

        return array_slice( array_values( array_map( 'intval', $ids ) ), 0, max( 1, $cap ) );
    }

    /**
     * Titel zu den IDs (für die Rangfolge). Ein Abruf in Blöcken, ohne Beschreibungstexte.
     *
     * @param int[] $ids
     * @return array<int,string> post_id => Titel
     */
    private static function titles_for( array $ids ): array {
        global $wpdb;

        $titles = [];
        foreach ( array_chunk( array_map( 'intval', $ids ), 1000 ) as $chunk ) {
            $rows = $wpdb->get_results( "SELECT ID, post_title FROM {$wpdb->posts} WHERE ID IN (" . implode( ',', $chunk ) . ')' );
            foreach ( (array) $rows as $row ) {
                $titles[ (int) $row->ID ] = html_entity_decode( (string) $row->post_title, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
            }
        }
        return $titles;
    }

    /**
     * Teilwort-Regel für Text-Treffer: behält nur IDs, bei denen $needle am Anfang eines Wortes
     * in Titel, Kurztext oder Beschreibung steht ("rot" findet "Rotwein", nicht "Trotter").
     * Die Reihenfolge der IDs bleibt erhalten.
     *
     * @param int[] $ids
     * @return int[]
     */
    private static function filter_word_start( array $ids, string $needle ): array {
        if ( empty( $ids ) ) return $ids;

        global $wpdb;

        $keep = [];
        foreach ( array_chunk( array_map( 'intval', $ids ), 300 ) as $chunk ) {
            $rows = $wpdb->get_results( "SELECT ID, post_title, post_excerpt, post_content FROM {$wpdb->posts} WHERE ID IN (" . implode( ',', $chunk ) . ')' );
            foreach ( (array) $rows as $row ) {
                if ( MediaLab_Search_Query::word_start_in( $needle, (string) $row->post_title )
                    || MediaLab_Search_Query::word_start_in( $needle, (string) $row->post_excerpt )
                    || MediaLab_Search_Query::word_start_in( $needle, (string) $row->post_content ) ) {
                    $keep[ (int) $row->ID ] = true;
                }
            }
        }

        return array_values( array_filter( $ids, static fn( $id ) => isset( $keep[ (int) $id ] ) ) );
    }

    /**
     * Alle IDs, die EINEN Begriff treffen: native Suche (inkl. Synonyme), Produktattribute,
     * Konfigurator-Optionen und Woo-Extras (SKU, Kategorien, Marken ...). Pro Request gemerkt.
     *
     * Für Begriffe mit 'word' => true (Teilwort-Regel) zählt der Text nur am Wortanfang;
     * Merkmal-Treffer (Attribute, SKU, Kategorien, Marken) bleiben Teilstring-Treffer.
     *
     * @param array{text:string,phrase:bool,word?:bool} $term
     * @return array{ids:int[], labels:array<int,string>, attr:array<int,bool>}
     *         ids = alle Treffer, labels = post_id => "Attribut: Wert", attr = Treffer über Merkmale (post_id => true)
     */
    private static function term_data( array $term, array $types, string $lang ): array {
        static $memo = [];

        $key = md5( wp_json_encode( [ $term, $types, $lang ] ) );
        if ( isset( $memo[ $key ] ) ) return $memo[ $key ];

        $text      = $term['text'];
        $phrase    = ! empty( $term['phrase'] );
        $word_only = ! empty( $term['word'] ) && ! $phrase;
        $cfg       = MediaLab_Search_Settings::get();
        $lang_args = MediaLab_Search_Settings::apply_language( [], $lang );
        $has_products = in_array( 'product', $types, true ) && class_exists( 'WooCommerce' );

        // Native Treffer + Synonyme (Relevanz-Reihenfolge)
        $ids = [];
        foreach ( (array) apply_filters( 'media_lab_ajax_search_query_expansion', [ $text ], $text ) as $exp ) {
            if ( ! is_string( $exp ) || $exp === '' ) continue;

            $args = array_merge( [
                'post_type'      => $types,
                'post_status'    => 'publish',
                's'              => $phrase ? '"' . $exp . '"' : $exp,
                'orderby'        => 'relevance',
                'fields'         => 'ids',
                'posts_per_page' => self::TERM_CAP,
                'no_found_rows'  => true,
            ], $lang_args );
            $args = apply_filters( 'media_lab_ajax_search_query_args', $args, $exp );

            $q   = new \WP_Query( $args );
            $hit = array_map( 'intval', $q->posts );

            // Teilwort-Regel: nur Wortanfänge (mehrteilige Synonyme bleiben unverändert)
            if ( $word_only && ! preg_match( '/\s/u', $exp ) ) {
                $hit = self::filter_word_start( $hit, $exp );
            }

            $ids = array_merge( $ids, $hit );
        }

        // WooCommerce: Attribute, Konfigurator-Optionen, Woo-Extras
        $extra = [];
        if ( $has_products && ! empty( $cfg['woo_attributes'] ) ) {
            if ( function_exists( 'agency_core_search_product_attributes' ) ) {
                $extra = agency_core_search_product_attributes( $text );
            }
            if ( function_exists( 'agency_core_search_local_product_attributes' ) ) {
                $extra += agency_core_search_local_product_attributes( $text, 50 );
            }
            if ( function_exists( 'agency_core_search_configurator_options' ) ) {
                $extra += agency_core_search_configurator_options( $text );
            }
        }
        if ( $has_products ) {
            $extra += (array) apply_filters( 'media_lab_search_woo_matches', [], $text, self::TERM_CAP );
        }

        $attr_ids = array_map( 'intval', array_keys( $extra ) );

        return $memo[ $key ] = [
            'ids'    => array_values( array_unique( array_merge( $ids, $attr_ids ) ) ),
            'labels' => array_map( 'strval', $extra ),
            'attr'   => array_fill_keys( $attr_ids, true ),
        ];
    }
}
