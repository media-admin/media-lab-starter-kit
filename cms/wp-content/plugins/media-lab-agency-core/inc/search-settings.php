<?php
/**
 * Such-Einstellungen (zentral + mehrsprachig).
 *
 * Eine Quelle der Wahrheit für ALLE .ajax-search-Instanzen (Shortcode
 * [ajax_search] und das Nav-Such-Overlay aus inc/nav-search-icon.php):
 *
 *  - Eigene Optionsseite "Suche / Live-Suche" (Agency Core -> Suche, Slug
 *    agency-core-search) mit der ACF-Feldgruppe "Suche". Dort liegt auch der
 *    Toggle "Suche in Navigation" (Feld search_enabled, früher unter
 *    Logo / Globale Einstellungen -> UI-Features).
 *  - Helper MediaLab_Search_Settings::get() liefert Defaults + ACF-Werte,
 *    Texte bereits auf die aktuelle Sprache aufgelöst.
 *  - container_attrs() rendert die data-Attribute für ajax-search.js.
 *  - Serverseitige Absicherung für inc/ajax-search.php (Post-Type-Whitelist,
 *    Limit-Deckel, Sprachfilter).
 *
 * Suchergebnisseite (search.php im Theme): Sortierung, Layout, Ergebnisse pro Seite
 * (Tab "Ergebnisseite"); die Query wird per pre_get_posts angepasst, die
 * Konfiguration liefert MediaLab_Search_Settings::serp().
 *
 * Mehrsprachigkeit (gleiches Muster wie inc/cookie-consent.php):
 *  - Toggle "Mehrsprachigkeit aktivieren" + Repeater search_languages.
 *  - Spracherkennung: Polylang -> WPML -> WP-Locale.
 *  - Zeile mit passendem lang_code wird verwendet, sonst die ERSTE Zeile
 *    (Fallback). Ohne Repeater-Zeilen gelten die Standardtexte.
 *  - Ist Mehrsprachigkeit AUS, gelten nur die Standardtexte (Flat-Felder).
 *
 * Alle Defaults entsprechen dem bisherigen Verhalten -> bestehende Sites
 * verhalten sich nach dem Update identisch, bis jemand etwas ändert.
 *
 * Datei ablegen unter: inc/search-settings.php
 * Einbindung: require_once in media-lab-agency-core.php VOR inc/ajax-search.php
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MediaLab_Search_Settings {

    /** Harte Obergrenze für Treffer pro Anfrage (serverseitig erzwungen). */
    const MAX_LIMIT = 20;

    /** Erlaubte Sortierungen der Suchergebnisseite (Query-Param ?sort=). */
    const SORT_KEYS = [ 'relevance', 'date_desc', 'date_asc', 'title_asc', 'title_desc' ];

    private static ?array $cache = null;
    private static ?array $serp_cache = null;

    public static function init(): void {
        add_action( 'acf/init', [ __CLASS__, 'register_options_page' ], 10 );
        add_action( 'pre_get_posts', [ __CLASS__, 'apply_serp_query' ] );
        add_filter( 'posts_orderby', [ __CLASS__, 'filter_orderby_type_priority' ], 10, 2 );
        add_action( 'acf/init', [ __CLASS__, 'register_fields' ], 25 );
        // Checkbox-Choices erst beim Laden des Feldes füllen: acf/init läuft
        // auf init:5, CPTs werden meist erst auf init:10 registriert.
        add_filter( 'acf/load_field/key=field_search_post_types', [ __CLASS__, 'load_post_type_choices' ] );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Öffentliche API
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Alle Such-Einstellungen, sprachaufgelöst. Pro Request gecacht.
     *
     * @return array{
     *   limit:int, post_types:string[], min_chars:int, debounce:int, excerpt_words:int,
     *   woo_attributes:bool, highlight:bool, lang:string,
     *   show:array<string,bool>,
     *   text:array<string,mixed>
     * }
     */
    public static function get(): array {
        if ( self::$cache !== null ) {
            return self::$cache;
        }

        $lang = self::current_lang();

        self::$cache = [
            'limit'          => self::clamp_limit( self::opt( 'search_limit', 5 ) ),
            'post_types'     => self::sanitize_post_types( self::opt( 'search_post_types', [] ) ),
            'min_chars'      => max( 2, min( 5, (int) self::opt( 'search_min_chars', 2 ) ) ),
            'debounce'       => max( 100, min( 1000, (int) self::opt( 'search_debounce', 300 ) ) ),
            'excerpt_words'  => max( 3, min( 40, (int) self::opt( 'search_excerpt_words', 10 ) ) ),
            'nav_display'    => self::nav_display(),
            'woo_attributes' => (bool) self::opt( 'search_woo_attributes', true ),
            'highlight'      => (bool) self::opt( 'search_highlight', true ),
            'lang'           => $lang,
            'show'           => [
                'thumbnail' => (bool) self::opt( 'search_show_thumbnail', true ),
                'date'      => (bool) self::opt( 'search_show_date', true ),
                'type'      => (bool) self::opt( 'search_show_type', true ),
                'excerpt'   => (bool) self::opt( 'search_show_excerpt', true ),
                'price'     => (bool) self::opt( 'search_show_price', true ),
                'allLink'   => (bool) self::opt( 'search_show_all_link', false ),
            ],
            'text'           => self::resolve_texts( $lang ),
        ];

        return self::$cache;
    }

    /**
     * data-Attribute für den .ajax-search-Container (bereits escaped).
     *
     * @param array $overrides Pro-Instanz-Overrides (Shortcode-Attribute): 'limit', 'post_types'.
     */
    public static function container_attrs( array $overrides = [] ): string {
        $cfg = self::get();

        $limit = ( isset( $overrides['limit'] ) && (int) $overrides['limit'] > 0 )
            ? self::clamp_limit( $overrides['limit'], $cfg['limit'] )
            : $cfg['limit'];

        $types = ! empty( $overrides['post_types'] )
            ? self::sanitize_post_types( $overrides['post_types'] )
            : $cfg['post_types'];

        $js = [
            'limit'     => $limit,
            'postTypes' => $types,
            'minChars'  => $cfg['min_chars'],
            'debounce'  => $cfg['debounce'],
            'lang'      => $cfg['lang'],
            'show'      => $cfg['show'],
            'i18n'      => [
                'intro'      => $cfg['text']['intro'],
                'minHint'    => $cfg['text']['min_hint'],
                'noResults'  => $cfg['text']['no_results'],
                'error'      => $cfg['text']['error'],
                'showAll'    => $cfg['text']['show_all'],
                'typeLabels' => $cfg['text']['type_labels'],
            ],
        ];

        return sprintf(
            ' data-limit="%d" data-post-types="%s" data-config="%s"',
            $limit,
            esc_attr( implode( ',', $types ) ),
            esc_attr( wp_json_encode( $js ) )
        );
    }

    /** Öffentlich durchsuchbare Post-Types (ohne Mediathek). */
    public static function searchable_post_types(): array {
        $types = get_post_types( [ 'public' => true, 'exclude_from_search' => false ], 'names' );
        unset( $types['attachment'] );
        return array_values( $types );
    }

    /**
     * Whitelist: nur durchsuchbare Post-Types, nie rohe $_POST-Werte.
     * Leeres Ergebnis -> Standard (post, page).
     *
     * @param array|string $input Array oder kommagetrennter String.
     */
    public static function sanitize_post_types( $input ): array {
        if ( is_string( $input ) ) {
            $input = explode( ',', $input );
        }
        if ( ! is_array( $input ) ) {
            $input = [];
        }

        $input = array_map( static fn( $t ) => sanitize_key( trim( (string) $t ) ), $input );
        $valid = array_values( array_unique( array_intersect( $input, self::searchable_post_types() ) ) );

        if ( $valid ) {
            return $valid;
        }

        $default = array_values( array_intersect( [ 'post', 'page' ], self::searchable_post_types() ) );
        return $default ?: [ 'post' ];
    }

    /** Limit auf 1..MAX_LIMIT begrenzen; ungültig/0 -> $default. */
    public static function clamp_limit( $value, int $default = 5 ): int {
        $n = absint( $value );
        if ( $n < 1 ) {
            $n = max( 1, $default );
        }
        return min( $n, self::MAX_LIMIT );
    }

    /**
     * Sprachfilter für die WP_Query-Args.
     *
     * Wichtig: admin-ajax.php gilt für Polylang/WPML als Admin-Kontext - ohne
     * explizite Sprache würde die Live-Suche Treffer ALLER Sprachen liefern.
     */
    public static function apply_language( array $args, string $lang ): array {
        if ( $lang === '' ) {
            return $args;
        }

        if ( function_exists( 'pll_languages_list' ) ) {
            if ( in_array( $lang, (array) pll_languages_list( [ 'fields' => 'slug' ] ), true ) ) {
                $args['lang'] = $lang;
            }
        } elseif ( defined( 'ICL_SITEPRESS_VERSION' ) ) {
            do_action( 'wpml_switch_language', $lang );
        }

        return $args;
    }

    /**
     * Filtert Post-IDs (z. B. WooCommerce-Attribut-Treffer, die nicht über
     * WP_Query laufen) auf die gewünschte Sprache. Nur Polylang; Post-Types
     * ohne Übersetzung (pll_get_post_language() === false) bleiben erhalten.
     */
    public static function filter_ids_by_language( array $ids, string $lang ): array {
        if ( $lang === '' || ! function_exists( 'pll_get_post_language' ) ) {
            return $ids;
        }

        return array_values( array_filter( $ids, static function ( $id ) use ( $lang ) {
            $post_lang = pll_get_post_language( (int) $id, 'slug' );
            return ! $post_lang || $post_lang === $lang;
        } ) );
    }

    /** 2-Zeichen-Code bzw. Polylang-Slug der aktuellen Seite. */
    public static function current_lang(): string {
        if ( function_exists( 'pll_current_language' ) ) {
            $lang = pll_current_language( 'slug' );
            if ( $lang ) return (string) $lang;
        }
        if ( defined( 'ICL_LANGUAGE_CODE' ) && ICL_LANGUAGE_CODE ) {
            return (string) ICL_LANGUAGE_CODE;
        }
        return substr( get_locale(), 0, 2 ) ?: 'de';
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Suchergebnisseite (search.php)
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Konfiguration der Suchergebnisseite. Pro Request gecacht.
     *
     * @return array{
     *   layout:string, columns:int, per_page:int, orderby:string,
     *   type_priority:string[], extended:bool, sort_ui:bool,
     *   sort_options:array<string,string>, sort_current:string
     * }
     */
    public static function serp(): array {
        if ( self::$serp_cache !== null ) {
            return self::$serp_cache;
        }

        $layout = (string) self::opt( 'search_serp_layout', 'grid' );
        $layout = in_array( $layout, [ 'grid', 'list' ], true ) ? $layout : 'grid';

        $columns = (int) self::opt( 'search_serp_columns', 3 );
        $columns = in_array( $columns, [ 2, 3, 4 ], true ) ? $columns : 3;

        $per_page = max( 0, min( 50, (int) self::opt( 'search_serp_per_page', 0 ) ) );

        $default = (string) self::opt( 'search_serp_orderby', 'relevance' );
        $default = in_array( $default, self::SORT_KEYS, true ) ? $default : 'relevance';

        $priority = array_values( array_filter( array_map(
            static fn( $slug ) => sanitize_key( trim( $slug ) ),
            explode( ',', (string) self::opt( 'search_serp_type_priority', '' ) )
        ) ) );

        $sort_ui = (bool) self::opt( 'search_serp_sort_ui', false );

        $labels = array_merge(
            self::sort_label_defaults(),
            (array) ( self::get()['text']['serp_sort_labels'] ?? [] )
        );

        // Sortier-Optionen für Besucher: gewählte + immer die Standard-Sortierung
        $options = [];
        $current = $default;

        if ( $sort_ui ) {
            $enabled = (array) self::opt( 'search_serp_sort_options', self::SORT_KEYS );
            $enabled = array_values( array_intersect( self::SORT_KEYS, array_merge( $enabled, [ $default ] ) ) );

            foreach ( $enabled as $key ) {
                $options[ $key ] = (string) ( $labels[ $key ] ?? $key );
            }

            $requested = isset( $_GET['sort'] ) ? sanitize_key( wp_unslash( $_GET['sort'] ) ) : '';
            if ( $requested !== '' && isset( $options[ $requested ] ) ) {
                $current = $requested;
            }
        }

        self::$serp_cache = [
            'layout'        => $layout,
            'columns'       => $columns,
            'per_page'      => $per_page,
            'orderby'       => $default,
            'type_priority' => $priority,
            'extended'      => (bool) self::opt( 'search_serp_extended', true ),
            'sort_ui'       => $sort_ui,
            'sort_options'  => $options,
            'sort_current'  => $current,
        ];

        return self::$serp_cache;
    }

    /** Wendet Sortierung, Ergebnisse pro Seite und Typ-Reihenfolge auf die Haupt-Suchabfrage an. */
    public static function apply_serp_query( \WP_Query $query ): void {
        if ( is_admin() || ! $query->is_main_query() || ! $query->is_search() ) {
            return;
        }

        $serp = self::serp();

        if ( $serp['per_page'] > 0 ) {
            $query->set( 'posts_per_page', $serp['per_page'] );
        }

        switch ( $serp['sort_current'] ) {
            case 'date_desc':
                $query->set( 'orderby', 'date' );
                $query->set( 'order', 'DESC' );
                break;
            case 'date_asc':
                $query->set( 'orderby', 'date' );
                $query->set( 'order', 'ASC' );
                break;
            case 'title_asc':
                $query->set( 'orderby', 'title' );
                $query->set( 'order', 'ASC' );
                break;
            case 'title_desc':
                $query->set( 'orderby', 'title' );
                $query->set( 'order', 'DESC' );
                break;
            default:
                $query->set( 'orderby', 'relevance' );
        }

        if ( $serp['type_priority'] ) {
            $query->set( 'mlsearch_type_priority', $serp['type_priority'] );
        }
    }

    /**
     * Inhaltstypen-Reihenfolge ("Produkte zuerst ..."): stellt der gewählten
     * Sortierung ein FIELD(post_type, ...) voran. Nicht gelistete Typen
     * kommen danach (FIELD() liefert 0, daher die Extra-Bedingung).
     */
    public static function filter_orderby_type_priority( string $orderby, \WP_Query $query ): string {
        $priority = $query->get( 'mlsearch_type_priority' );
        if ( empty( $priority ) || ! is_array( $priority ) ) {
            return $orderby;
        }

        global $wpdb;

        $list  = implode( ',', array_map( static fn( $slug ) => "'" . esc_sql( $slug ) . "'", $priority ) );
        $field = "FIELD({$wpdb->posts}.post_type, {$list})";
        $first = "({$field} = 0) ASC, {$field} ASC";

        return $orderby !== '' ? $first . ', ' . $orderby : $first;
    }

    /** Darstellung des Such-Eintrags im Hauptmenü: icon | icon_text | text. */
    private static function nav_display(): string {
        $value = (string) self::opt( 'search_nav_display', 'icon' );
        return in_array( $value, [ 'icon', 'icon_text', 'text' ], true ) ? $value : 'icon';
    }

    private static function sort_label_defaults(): array {
        return [
            'relevance'  => 'Relevanz',
            'date_desc'  => 'Neueste zuerst',
            'date_asc'   => 'Älteste zuerst',
            'title_asc'  => 'Titel A–Z',
            'title_desc' => 'Titel Z–A',
        ];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Interne Helper
    // ═════════════════════════════════════════════════════════════════════════

    /** ACF-Options-Read mit Default (null/'' -> Default; false bleibt false). */
    private static function opt( string $name, $default ) {
        if ( ! function_exists( 'get_field' ) ) return $default;
        $value = get_field( $name, 'option' );
        return ( $value === null || $value === '' ) ? $default : $value;
    }

    /** Standardtexte + Metadaten (auch Basis für die ACF-Felder). */
    private static function text_defs(): array {
        return [
            'placeholder' => [
                'label'        => 'Platzhalter im Suchfeld',
                'type'         => 'text',
                'default'      => 'Suchen...',
                'required'     => true,
                'width'        => '50',
                'instructions' => '',
            ],
            'show_all' => [
                'label'        => 'Link-Text „Alle Ergebnisse“',
                'type'         => 'text',
                'default'      => 'Alle Ergebnisse anzeigen',
                'required'     => true,
                'width'        => '50',
                'instructions' => 'Nur sichtbar, wenn „Link zu allen Ergebnissen“ unter „Anzeige“ aktiv ist.',
            ],
            'intro' => [
                'label'        => 'Startertext',
                'type'         => 'textarea',
                'rows'         => 3,
                'default'      => '',
                'required'     => false,
                'width'        => '100',
                'instructions' => 'Erscheint unter dem Suchfeld, sobald es fokussiert wird und noch nichts eingegeben ist (z. B. „Wonach suchen Sie?“). Leer = kein Startertext. Zeilenumbrüche erlaubt, kein HTML.',
            ],
            'min_hint' => [
                'label'        => 'Hinweis „zu wenige Zeichen“',
                'type'         => 'text',
                'default'      => '',
                'required'     => false,
                'width'        => '100',
                'instructions' => 'Erscheint, solange weniger Zeichen als das Minimum eingegeben sind. {min} wird durch die Mindestzeichenzahl ersetzt, z. B. „Bitte mindestens {min} Zeichen eingeben.“ Leer = kein Hinweis.',
            ],
            'no_results' => [
                'label'        => 'Text „Keine Ergebnisse“',
                'type'         => 'text',
                'default'      => 'Keine Ergebnisse gefunden.',
                'required'     => true,
                'width'        => '50',
                'instructions' => '',
            ],
            'error' => [
                'label'        => 'Fehlertext',
                'type'         => 'text',
                'default'      => 'Ein Fehler ist aufgetreten. Bitte versuchen Sie es erneut.',
                'required'     => true,
                'width'        => '50',
                'instructions' => '',
            ],
            'aria_open' => [
                'label'        => 'Screenreader-Label: Suche öffnen',
                'type'         => 'text',
                'default'      => 'Suche öffnen',
                'required'     => true,
                'width'        => '50',
                'instructions' => 'Nav-Icon.',
            ],
            'aria_submit' => [
                'label'        => 'Screenreader-Label: Suchen-Button',
                'type'         => 'text',
                'default'      => 'Suchen',
                'required'     => true,
                'width'        => '50',
                'instructions' => '',
            ],
            'nav_label' => [
                'label'        => 'Navigation: Text',
                'type'         => 'text',
                'default'      => 'Suche',
                'required'     => true,
                'width'        => '50',
                'instructions' => 'Wird im Hauptmenü angezeigt, wenn die Darstellung „Icon + Text“ oder „Nur Text“ gewählt ist (Tab „Allgemein“).',
            ],
            'type_labels' => [
                'label'        => 'Bezeichnungen der Inhaltstypen',
                'type'         => 'textarea',
                'rows'         => 4,
                'default'      => '',
                'required'     => false,
                'width'        => '100',
                'instructions' => 'Eine Zeile pro Typ im Format slug=Bezeichnung, z. B. product=Produkt. Leer = Standardbezeichnungen des jeweiligen Post-Types.',
            ],
            'serp_title' => [
                'label'        => 'Ergebnisseite: Überschrift',
                'type'         => 'text',
                'default'      => 'Suchergebnisse für: „%s“',
                'required'     => true,
                'width'        => '50',
                'instructions' => '%s wird durch den Suchbegriff ersetzt.',
            ],
            'serp_sort_label' => [
                'label'        => 'Ergebnisseite: Beschriftung „Sortieren“',
                'type'         => 'text',
                'default'      => 'Sortieren nach',
                'required'     => true,
                'width'        => '50',
                'instructions' => '',
            ],
            'serp_count_one' => [
                'label'        => 'Ergebnisseite: Anzahl (1 Treffer)',
                'type'         => 'text',
                'default'      => '%s Ergebnis',
                'required'     => true,
                'width'        => '50',
                'instructions' => '%s wird durch die Anzahl ersetzt.',
            ],
            'serp_count_many' => [
                'label'        => 'Ergebnisseite: Anzahl (mehrere Treffer)',
                'type'         => 'text',
                'default'      => '%s Ergebnisse',
                'required'     => true,
                'width'        => '50',
                'instructions' => '',
            ],
            'serp_empty_title' => [
                'label'        => 'Ergebnisseite: „Keine Ergebnisse“ – Überschrift',
                'type'         => 'text',
                'default'      => 'Keine Ergebnisse gefunden',
                'required'     => true,
                'width'        => '50',
                'instructions' => '',
            ],
            'serp_empty_text' => [
                'label'        => 'Ergebnisseite: „Keine Ergebnisse“ – Text',
                'type'         => 'text',
                'default'      => 'Für „%s“ wurden keine Inhalte gefunden. Versuche es mit anderen Suchbegriffen.',
                'required'     => true,
                'width'        => '50',
                'instructions' => '%s wird durch den Suchbegriff ersetzt.',
            ],
            'serp_sort_labels' => [
                'label'        => 'Ergebnisseite: Bezeichnungen der Sortierungen',
                'type'         => 'textarea',
                'rows'         => 5,
                'default'      => '',
                'required'     => false,
                'width'        => '100',
                'instructions' => 'Eine Zeile pro Sortierung im Format schlüssel=Bezeichnung. Schlüssel: relevance, date_desc, date_asc, title_asc, title_desc. Leer = Standardbezeichnungen (Relevanz, Neueste zuerst, Älteste zuerst, Titel A–Z, Titel Z–A).',
            ],
        ];
    }

    /** Texte der aktuellen Sprache auflösen (Zeile -> Fallback-Zeile -> Standardtexte). */
    private static function resolve_texts( string $lang ): array {
        $defs   = self::text_defs();
        $source = [];

        foreach ( $defs as $key => $def ) {
            $source[ $key ] = (string) self::opt( 'search_txt_' . $key, '' );
        }

        if ( self::opt( 'search_multilang_enabled', false ) ) {
            $row = self::match_language_row( $lang );
            if ( $row !== null ) {
                foreach ( $defs as $key => $def ) {
                    $source[ $key ] = (string) ( $row[ 'txt_' . $key ] ?? '' );
                }
            }
        }

        $out = [];
        foreach ( $defs as $key => $def ) {
            $value = trim( $source[ $key ] );
            if ( $value === '' && ! empty( $def['required'] ) ) {
                $value = $def['default'];
            }
            $out[ $key ] = $value;
        }

        $out['type_labels']      = self::type_labels( $source['type_labels'] );
        $out['serp_sort_labels'] = self::sort_labels( $source['serp_sort_labels'] );

        return $out;
    }

    private static function match_language_row( string $lang ): ?array {
        if ( ! function_exists( 'get_field' ) ) return null;

        $rows = get_field( 'search_languages', 'option' );
        if ( ! is_array( $rows ) || ! $rows ) return null;

        $lang = strtolower( $lang );
        foreach ( $rows as $row ) {
            $code = strtolower( trim( (string) ( $row['lang_code'] ?? '' ) ) );
            if ( $code !== '' && ( $code === $lang || substr( $code, 0, 2 ) === substr( $lang, 0, 2 ) ) ) {
                return $row;
            }
        }

        return $rows[0]; // erste Zeile = Fallback
    }

    /** Zeilen "schluessel=Bezeichnung" -> Array, nur gültige Sortier-Schlüssel. */
    private static function sort_labels( string $raw ): array {
        $labels = [];

        foreach ( preg_split( '/\R/', $raw ) ?: [] as $line ) {
            if ( strpos( $line, '=' ) === false ) continue;
            [ $key, $label ] = array_map( 'trim', explode( '=', $line, 2 ) );
            $key = sanitize_key( $key );
            if ( in_array( $key, self::SORT_KEYS, true ) && $label !== '' ) {
                $labels[ $key ] = sanitize_text_field( $label );
            }
        }

        return $labels;
    }

    /**
     * Prioritäten: Textarea-Override > Singular-Name des Post-Types
     * (von WP/Plugins bereits übersetzt) > deutscher Fallback.
     */
    private static function type_labels( string $raw ): array {
        $fallback = [
            'post'    => 'Beitrag',
            'page'    => 'Seite',
            'product' => 'Produkt',
            'project' => 'Projekt',
            'service' => 'Leistung',
            'job'     => 'Job',
        ];

        $labels = [];
        foreach ( self::searchable_post_types() as $slug ) {
            $obj = get_post_type_object( $slug );
            if ( $obj && ! empty( $obj->labels->singular_name ) ) {
                $labels[ $slug ] = $obj->labels->singular_name;
            } elseif ( isset( $fallback[ $slug ] ) ) {
                $labels[ $slug ] = $fallback[ $slug ];
            }
        }

        foreach ( preg_split( '/\R/', $raw ) ?: [] as $line ) {
            if ( strpos( $line, '=' ) === false ) continue;
            [ $slug, $label ] = array_map( 'trim', explode( '=', $line, 2 ) );
            $slug = sanitize_key( $slug );
            if ( $slug !== '' && $label !== '' ) {
                $labels[ $slug ] = sanitize_text_field( $label );
            }
        }

        return $labels;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // ACF
    // ═════════════════════════════════════════════════════════════════════════

    public static function load_post_type_choices( array $field ): array {
        $choices = [];
        $objects = get_post_types( [ 'public' => true, 'exclude_from_search' => false ], 'objects' );

        foreach ( $objects as $slug => $obj ) {
            if ( $slug === 'attachment' ) continue;
            $choices[ $slug ] = sprintf( '%s (%s)', $obj->labels->name, $slug );
        }

        $field['choices'] = $choices;
        return $field;
    }

    /** Textfelder einmal definieren, für Flat-Felder UND Repeater verwenden. */
    private static function build_text_fields( bool $in_repeater ): array {
        $fields = [];

        foreach ( self::text_defs() as $key => $def ) {
            $field = [
                'key'          => ( $in_repeater ? 'field_search_lang_txt_' : 'field_search_txt_' ) . $key,
                'label'        => $def['label'],
                'name'         => ( $in_repeater ? 'txt_' : 'search_txt_' ) . $key,
                'type'         => $def['type'],
                'instructions' => $def['instructions'],
                'wrapper'      => [ 'width' => $def['width'] ],
            ];

            if ( $def['type'] === 'textarea' ) {
                $field['rows']      = $def['rows'] ?? 3;
                $field['new_lines'] = '';
            }

            if ( $in_repeater ) {
                // Kein Default (wäre deutsch) - Standardtext nur als Hint.
                $field['placeholder'] = $def['default'];
            } else {
                $field['default_value'] = $def['default'];
            }

            $fields[] = $field;
        }

        return $fields;
    }

    private static function toggle( string $key, string $label, bool $default, string $instructions = '', string $width = '33' ): array {
        return [
            'key'           => 'field_' . $key,
            'label'         => $label,
            'name'          => $key,
            'type'          => 'true_false',
            'ui'            => 1,
            'default_value' => $default ? 1 : 0,
            'instructions'  => $instructions,
            'wrapper'       => [ 'width' => $width ],
        ];
    }

    private static function number( string $key, string $label, int $default, int $min, int $max, int $step = 1, string $append = '', string $instructions = '' ): array {
        return [
            'key'           => 'field_' . $key,
            'label'         => $label,
            'name'          => $key,
            'type'          => 'number',
            'default_value' => $default,
            'min'           => $min,
            'max'           => $max,
            'step'          => $step,
            'append'        => $append,
            'instructions'  => $instructions,
            'wrapper'       => [ 'width' => '33' ],
        ];
    }

    /** Eigene Unterseite unter "Agency Core" (gleiches Muster wie inc/social-share.php). */
    public static function register_options_page(): void {
        if ( ! function_exists( 'acf_add_options_sub_page' ) ) return;

        acf_add_options_sub_page( [
            'page_title'  => 'Suche / Live-Suche',
            'menu_title'  => 'Suche / Live-Suche',
            'parent_slug' => 'agency-core',
            'capability'  => 'manage_options',
            'slug'        => 'agency-core-search',
            'menu_slug'   => 'agency-core-search',
            'position'    => 4, // rein kosmetisch: direkt hinter "Logo / Globale Einstellungen"
            'redirect'    => false,
        ] );
    }

    public static function register_fields(): void {
        if ( ! function_exists( 'acf_add_local_field_group' ) ) return;

        $fields = [];

        // ── Tab: Allgemein ───────────────────────────────────────────────────
        $fields[] = [ 'key' => 'field_search_tab_general', 'label' => 'Allgemein', 'type' => 'tab', 'placement' => 'top' ];

        // Zieht von Logo / Globale Einstellungen hierher um: gleicher Key + Name,
        // gespeicherte Werte bleiben erhalten (ACF speichert nach Feld-Name).
        $fields[] = self::toggle(
            'search_enabled',
            'Suche in Navigation',
            true,
            'Zeigt ein Such-Icon in der Hauptnavigation an (Desktop + Mobile). Öffnet beim Klick das Such-Overlay. Die Einstellungen unten gelten unabhängig davon auch für den Shortcode [ajax_search].',
            '100'
        );

        $fields[] = [
            'key'               => 'field_search_nav_display',
            'label'             => 'Darstellung in der Navigation',
            'name'              => 'search_nav_display',
            'type'              => 'button_group',
            'choices'           => [ 'icon' => 'Nur Icon', 'icon_text' => 'Icon + Text', 'text' => 'Nur Text' ],
            'default_value'     => 'icon',
            'layout'            => 'horizontal',
            'instructions'      => 'Der Text (mehrsprachig) wird im Tab „Texte“ unter „Navigation: Text“ gepflegt.',
            'conditional_logic' => [ [ [ 'field' => 'field_search_enabled', 'operator' => '==', 'value' => '1' ] ] ],
        ];

        $fields[] = [
            'key'     => 'field_search_general_help',
            'label'   => ' ',
            'name'    => 'search_general_help',
            'type'    => 'message',
            'message' => '<strong style="font-size:13px;">Shortcode</strong>'
                       . '<p style="margin:.4rem 0 0;color:#666;font-size:12px;">'
                       . '<code>[ajax_search]</code> nutzt die Einstellungen dieser Seite. '
                       . 'Pro Suchfeld überschreibbar: <code>placeholder</code>, <code>limit</code>, <code>post_types</code>, '
                       . 'z. B. <code>[ajax_search limit="10" post_types="post,page,product"]</code>.</p>',
        ];

        // ── Tab: Verhalten ───────────────────────────────────────────────────
        $fields[] = [ 'key' => 'field_search_tab_behavior', 'label' => 'Verhalten', 'type' => 'tab', 'placement' => 'top' ];

        $fields[] = [
            'key'           => 'field_search_post_types',
            'label'         => 'Durchsuchte Inhaltstypen',
            'name'          => 'search_post_types',
            'type'          => 'checkbox',
            'choices'       => [],
            'default_value' => [ 'post', 'page' ],
            'layout'        => 'horizontal',
            'return_format' => 'value',
            'instructions'  => 'Standard für alle Suchfelder. Das Shortcode-Attribut post_types="…" überschreibt das pro Suchfeld. Nichts gewählt = Beiträge + Seiten.',
        ];

        $fields[] = self::number( 'search_limit', 'Anzahl Ergebnisse', 5, 1, self::MAX_LIMIT, 1, '', 'Maximal ' . self::MAX_LIMIT . ' (serverseitig erzwungen).' );
        $fields[] = self::number( 'search_min_chars', 'Mindestzeichen', 2, 2, 5, 1, '', 'Ab wie vielen Zeichen gesucht wird.' );
        $fields[] = self::number( 'search_debounce', 'Verzögerung', 300, 100, 1000, 50, 'ms', 'Wartezeit nach dem letzten Tastenanschlag.' );
        $fields[] = self::number( 'search_excerpt_words', 'Textausschnitt', 10, 3, 40, 1, 'Wörter', 'Wörter vor und nach der Fundstelle im Ausschnitt.' );

        $fields[] = self::toggle( 'search_woo_attributes', 'WooCommerce-Attribute durchsuchen', true, 'Produktattribute (global + lokal) und Konfigurator-Optionen. Nur relevant, wenn „product“ durchsucht wird.', '50' );
        $fields[] = self::toggle( 'search_highlight', 'Treffer hervorheben', true, 'Suchbegriff in Titel und Ausschnitt mit <mark> markieren.', '50' );

        // ── Tab: Anzeige ─────────────────────────────────────────────────────
        $fields[] = [ 'key' => 'field_search_tab_display', 'label' => 'Anzeige', 'type' => 'tab', 'placement' => 'top' ];

        $fields[] = self::toggle( 'search_show_thumbnail', 'Vorschaubild', true );
        $fields[] = self::toggle( 'search_show_type', 'Inhaltstyp-Label', true );
        $fields[] = self::toggle( 'search_show_date', 'Datum', true );
        $fields[] = self::toggle( 'search_show_excerpt', 'Textausschnitt', true );
        $fields[] = self::toggle( 'search_show_price', 'Preis (WooCommerce)', true );
        $fields[] = self::toggle( 'search_show_all_link', 'Link zu allen Ergebnissen', false, 'Zeigt unter den Treffern einen Link zur vollständigen Suchergebnisseite.' );

        // ── Tab: Ergebnisseite ───────────────────────────────────────────────
        $fields[] = [ 'key' => 'field_search_tab_serp', 'label' => 'Ergebnisseite', 'type' => 'tab', 'placement' => 'top' ];

        $fields[] = [
            'key'     => 'field_search_serp_help',
            'label'   => ' ',
            'name'    => 'search_serp_help',
            'type'    => 'message',
            'message' => '<strong style="font-size:13px;">Suchergebnisseite</strong>'
                       . '<p style="margin:.4rem 0 0;color:#666;font-size:12px;">'
                       . 'Gilt für die Seite mit allen Ergebnissen (<code>/?s=…</code>, <code>search.php</code>). '
                       . 'Karten-Inhalt und Hervorhebung kommen aus den Tabs „Anzeige“ und „Verhalten“, '
                       . 'Überschriften und Beschriftungen aus „Texte“.</p>',
        ];

        $fields[] = [
            'key'           => 'field_search_serp_layout',
            'label'         => 'Layout',
            'name'          => 'search_serp_layout',
            'type'          => 'button_group',
            'choices'       => [ 'grid' => 'Raster', 'list' => 'Liste' ],
            'default_value' => 'grid',
            'layout'        => 'horizontal',
            'wrapper'       => [ 'width' => '33' ],
        ];

        $fields[] = [
            'key'               => 'field_search_serp_columns',
            'label'             => 'Spalten (Raster)',
            'name'              => 'search_serp_columns',
            'type'              => 'button_group',
            'choices'           => [ '2' => '2', '3' => '3', '4' => '4' ],
            'default_value'     => '3',
            'layout'            => 'horizontal',
            'wrapper'           => [ 'width' => '33' ],
            'conditional_logic' => [ [ [ 'field' => 'field_search_serp_layout', 'operator' => '==', 'value' => 'grid' ] ] ],
        ];

        $fields[] = self::number( 'search_serp_per_page', 'Ergebnisse pro Seite', 0, 0, 50, 1, '', '0 = WordPress-Standard (Einstellungen → Lesen). Im 3-Spalten-Raster passen 12 oder 24 gut.' );

        $fields[] = [
            'key'           => 'field_search_serp_orderby',
            'label'         => 'Standard-Sortierung',
            'name'          => 'search_serp_orderby',
            'type'          => 'select',
            'choices'       => self::sort_label_defaults(),
            'default_value' => 'relevance',
            'ui'            => 1,
            'wrapper'       => [ 'width' => '50' ],
        ];

        $fields[] = [
            'key'           => 'field_search_serp_type_priority',
            'label'         => 'Inhaltstypen zuerst',
            'name'          => 'search_serp_type_priority',
            'type'          => 'text',
            'placeholder'   => 'product, page, post',
            'instructions'  => 'Optional: Reihenfolge der Inhaltstypen (Slugs, kommagetrennt). Diese Typen stehen vor allen anderen, innerhalb eines Typs gilt die gewählte Sortierung. Leer = keine Gruppierung.',
            'wrapper'       => [ 'width' => '50' ],
        ];

        $fields[] = self::toggle( 'search_serp_extended', 'Erweiterte Suche auf der Ergebnisseite', true, 'Findet wie die Live-Suche auch Synonyme, Produktattribute/Konfigurator-Optionen und Tippfehler bei Produktcodes (WooCommerce-Attribute folgen dem Schalter unter „Verhalten“).', '100' );

        $fields[] = self::toggle( 'search_serp_sort_ui', 'Sortier-Auswahl für Besucher', false, 'Zeigt über den Ergebnissen eine Auswahl (?sort=…).', '50' );

        $fields[] = [
            'key'               => 'field_search_serp_sort_options',
            'label'             => 'Angebotene Sortierungen',
            'name'              => 'search_serp_sort_options',
            'type'              => 'checkbox',
            'choices'           => self::sort_label_defaults(),
            'default_value'     => self::SORT_KEYS,
            'layout'            => 'horizontal',
            'return_format'     => 'value',
            'instructions'      => 'Die Standard-Sortierung wird immer angeboten.',
            'wrapper'           => [ 'width' => '50' ],
            'conditional_logic' => [ [ [ 'field' => 'field_search_serp_sort_ui', 'operator' => '==', 'value' => '1' ] ] ],
        ];

        // ── Tab: Texte ───────────────────────────────────────────────────────
        $fields[] = [ 'key' => 'field_search_tab_texts', 'label' => 'Texte', 'type' => 'tab', 'placement' => 'top' ];

        $fields[] = [
            'key'     => 'field_search_txt_heading',
            'label'   => ' ',
            'name'    => 'search_txt_heading',
            'type'    => 'message',
            'message' => '<strong style="font-size:13px;">Standardtexte</strong>'
                       . '<p style="margin:.4rem 0 0;color:#666;font-size:12px;">'
                       . 'Gelten für alle Suchfelder (Shortcode + Nav-Overlay). Bei aktiver Mehrsprachigkeit dienen sie als Fallback, wenn keine Sprachzeile angelegt ist.</p>',
        ];

        foreach ( self::build_text_fields( false ) as $field ) {
            $fields[] = $field;
        }

        $fields[] = [
            'key'     => 'field_search_multilang_heading',
            'label'   => ' ',
            'name'    => 'search_multilang_heading',
            'type'    => 'message',
            'message' => '<strong style="font-size:13px;">Mehrsprachigkeit</strong>'
                       . '<p style="margin:.4rem 0 0;color:#666;font-size:12px;">'
                       . 'Texte je Sprache pflegen. Spracherkennung: Polylang → WPML → WP-Locale.</p>',
        ];

        $fields[] = [
            'key'           => 'field_search_multilang_enabled',
            'label'         => 'Mehrsprachigkeit aktivieren',
            'name'          => 'search_multilang_enabled',
            'type'          => 'true_false',
            'ui'            => 1,
            'default_value' => 0,
            'instructions'  => 'Wenn aktiv, gelten die Sprachzeilen unten statt der Standardtexte.',
        ];

        $fields[] = [
            'key'               => 'field_search_languages',
            'label'             => 'Sprachen',
            'name'              => 'search_languages',
            'type'              => 'repeater',
            'layout'            => 'block',
            'min'               => 0,
            'button_label'      => 'Sprache hinzufügen',
            'instructions'      => 'Die erste Zeile ist der Fallback, wenn keine Sprache passt. Leere Pflichttexte (Platzhalter, „Keine Ergebnisse“ …) werden durch die Standardtexte der Software ersetzt; Startertext und Hinweis bleiben leer = nicht angezeigt.',
            'conditional_logic' => [ [ [ 'field' => 'field_search_multilang_enabled', 'operator' => '==', 'value' => '1' ] ] ],
            'sub_fields'        => array_merge(
                [
                    [
                        'key'          => 'field_search_lang_code',
                        'label'        => 'Sprachcode',
                        'name'         => 'lang_code',
                        'type'         => 'text',
                        'placeholder'  => 'de',
                        'instructions' => 'Wie in Polylang/WPML, z. B. de, en, it.',
                        'wrapper'      => [ 'width' => '100' ],
                    ],
                ],
                self::build_text_fields( true )
            ),
        ];

        acf_add_local_field_group( [
            'key'                   => 'group_search_settings',
            'title'                 => 'Suche',
            'fields'                => $fields,
            'location'              => [ [ [
                'param'    => 'options_page',
                'operator' => '==',
                'value'    => 'agency-core-search',
            ] ] ],
            'menu_order'            => 0,
            'position'              => 'normal',
            'style'                 => 'default',
            'label_placement'       => 'top',
            'instruction_placement' => 'label',
        ] );
    }
}

MediaLab_Search_Settings::init();
