<?php
/**
 * Suchsyntax: mehrere Begriffe, UND / ODER, Ausschluss, Phrasen - plus Teilwort-Regel
 * und Rangfolge der Treffer.
 *
 * Reiner Parser + Mengen-Logik ohne WordPress-Abhängigkeit (bis auf die Filter
 * 'media_lab_search_match_options' und 'media_lab_search_operators_enabled'), damit er
 * einzeln testbar ist.
 *
 * Syntax (Groß-/Kleinschreibung egal, Deutsch und Englisch):
 *
 *   rot shirt              beide Begriffe müssen vorkommen (UND ist der Standard)
 *   rot und shirt          wie oben - "und" / "and" / "&" / "+" sind optional
 *   rot oder blau          mindestens einer der Begriffe  ("oder" / "or" / "|")
 *   shirt rot oder blau    shirt UND (rot ODER blau)  - ODER bindet nur die Nachbar-Begriffe
 *   shirt -kinder          Treffer ohne "kinder"      ("-wort" / "ohne wort" / "not wort")
 *   "t-shirt basic"        exakte Wortfolge
 *
 * Wirkung: Jeder Begriff wird einzeln über ALLE Quellen der Suche aufgelöst (Text,
 * Attribute, SKU, Kategorien, Marken ...). Anschließend gilt
 *
 *   Treffer = (Klausel 1) ∩ (Klausel 2) ∩ ...  \  Ausschlüsse
 *   Klausel = Begriff A ∪ Begriff B ∪ ...      (bei "oder")
 *
 * Teilwort-Regel (Einstellung "Teilwort-Treffer"): WordPress sucht standardmäßig im Wortinneren
 * ("rot" findet auch "Trotter" und "Brotkorb"). Je nach Einstellung gilt das nur für lange
 * Begriffe; kurze Begriffe müssen dann am Wortanfang stehen ("rot" findet "Rotwein", "Rot-Weiß",
 * aber nicht "Trotter"). Betroffene Begriffe tragen im Parser-Ergebnis 'word' => true. Das gilt
 * für den Text (Titel, Kurztext, Beschreibung); Attribute, SKU, Kategorien und Marken bleiben
 * Teilstring-Treffer, weil Farbnamen zusammengesetzt sind (dunkelrot).
 *
 * Ein einzelnes, normales Wort ist KEINE erweiterte Abfrage ($parsed['advanced'] = false) -
 * dafür bleibt das bisherige Verhalten der Suche unverändert. Ausnahme: ein kurzer Begriff, für den
 * die Teilwort-Regel gilt.
 *
 * Optionen (Agency Core -> Suche / Live-Suche -> Verhalten, geliefert von MediaLab_Search_Settings):
 *   operators  bool                      Operatoren erkennen (oder, -wort, "Phrase"); aus = alle Wörter per UND
 *   partial    'always'|'long'|'word'    Teilwort-Treffer: immer | nur bei langen Begriffen | nie (nur Wortanfang)
 *   min_len    int                       ab dieser Länge gilt ein Begriff bei 'long' als lang
 *   rank       bool                      ganze Wörter und Merkmale zuerst sortieren
 *
 * Filter:
 *   media_lab_search_match_options ( array $options )     Optionen (siehe oben)
 *   media_lab_search_operators_enabled ( bool $enabled )  Syntax global abschalten (Überschreibung)
 *
 * Datei ablegen unter: inc/search/class-search-query.php
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MediaLab_Search_Query {

    /** Schutz vor absurden Eingaben. */
    const MAX_CLAUSES      = 8;
    const MAX_ALTERNATIVES = 6;
    const MAX_EXCLUDES     = 8;

    /** Mindestlänge eines einzelnen Begriffs (1-Zeichen-Treffer wären Rauschen). */
    const MIN_TERM_LENGTH = 2;

    /** Standard der Teilwort-Regel: bis zu dieser Länge (exklusiv) nur Wortanfang. */
    const DEFAULT_MIN_LEN = 4;

    /** Punkte der Rangfolge (Titel / Merkmal). */
    const RANK_WHOLE_WORD = 4;
    const RANK_ATTRIBUTE  = 3;
    const RANK_WORD_START = 2;
    const RANK_SUBSTRING  = 1;

    private const OPS_OR  = [ 'or', 'oder', '|' ];
    private const OPS_AND = [ 'and', 'und', '&', '+' ];
    private const OPS_NOT = [ 'not', 'ohne' ];

    /**
     * Wirksame Optionen (Standardwerte + Filter, bereinigt).
     *
     * @return array{operators:bool, partial:string, min_len:int, rank:bool}
     */
    public static function options(): array {
        $defaults = [
            'operators' => true,
            'partial'   => 'long',
            'min_len'   => self::DEFAULT_MIN_LEN,
            'rank'      => true,
        ];

        $opts = function_exists( 'apply_filters' )
            ? (array) apply_filters( 'media_lab_search_match_options', $defaults )
            : $defaults;
        $opts = array_merge( $defaults, $opts );

        if ( function_exists( 'apply_filters' ) ) {
            $opts['operators'] = (bool) apply_filters( 'media_lab_search_operators_enabled', (bool) $opts['operators'] );
        }

        return [
            'operators' => (bool) $opts['operators'],
            'partial'   => in_array( $opts['partial'], [ 'always', 'long', 'word' ], true ) ? (string) $opts['partial'] : 'long',
            'min_len'   => max( 2, min( 12, (int) $opts['min_len'] ) ),
            'rank'      => (bool) $opts['rank'],
        ];
    }

    /**
     * Zerlegt die Eingabe.
     *
     * @param array|null $options Optionen (Standard: options())
     * @return array{
     *   raw: string,
     *   clauses: array<int, array<int, array{text:string, phrase:bool, word:bool}>>,
     *   exclude: array<int, array{text:string, phrase:bool, word:bool}>,
     *   advanced: bool,
     *   rank: bool
     * }
     */
    public static function parse( string $raw, ?array $options = null ): array {
        $opts   = $options ?? self::options();
        $raw    = trim( preg_replace( '/\s+/u', ' ', $raw ) ?? $raw );
        $result = [ 'raw' => $raw, 'clauses' => [], 'exclude' => [], 'advanced' => false, 'rank' => (bool) $opts['rank'] ];

        if ( $raw === '' ) return $result;

        $ops = (bool) $opts['operators'];

        if ( $ops ) {
            // Gruppe 1: "-" vor Phrase, 2: Phrase (auch unvollständig), 3: normales Wort
            preg_match_all( '/(-?)"([^"]*)(?:"|$)|(\S+)/u', $raw, $m, PREG_SET_ORDER );
        } else {
            // Ohne Syntax: jedes Wort ist ein normaler Begriff (alle per UND), Anführungszeichen entfallen.
            $m = [];
            foreach ( explode( ' ', str_replace( '"', ' ', $raw ) ) as $w ) {
                if ( $w !== '' ) $m[] = [ $w, '', '', $w ];
            }
        }

        $clauses   = [];
        $exclude   = [];
        $or_next   = false;   // nächster Begriff gehört per ODER zur letzten Klausel
        $not_next  = false;   // nächster Begriff wird ausgeschlossen
        $has_phrase = false;

        foreach ( $m as $tok ) {
            $is_phrase = isset( $tok[3] ) ? false : true;

            if ( $is_phrase ) {
                $text   = trim( $tok[2] );
                $negate = $tok[1] === '-';
                $has_phrase = $has_phrase || $text !== '';
            } else {
                $word = $tok[3];
                $negate = false;

                if ( $ops ) {
                    $low = mb_strtolower( $word );

                    if ( in_array( $low, self::OPS_OR, true ) )  { $or_next  = ! empty( $clauses ); continue; }
                    if ( in_array( $low, self::OPS_AND, true ) ) { $or_next  = false; continue; }
                    if ( in_array( $low, self::OPS_NOT, true ) ) { $not_next = true;  continue; }

                    if ( mb_strlen( $word ) > 1 && $word[0] === '-' && $word[1] !== '-' ) {
                        $negate = true;
                        $word   = mb_substr( $word, 1 );
                    }
                }
                $text = trim( $word, ",;:" );
            }

            if ( $not_next ) { $negate = true; $not_next = false; }

            if ( $text === '' || mb_strlen( $text ) < self::MIN_TERM_LENGTH ) {
                $or_next = false;
                continue;
            }

            $term = [
                'text'   => $text,
                'phrase' => $is_phrase,
                'word'   => ! $is_phrase && self::is_word_start_only( $text, $opts ),
            ];

            if ( $negate ) {
                if ( count( $exclude ) < self::MAX_EXCLUDES ) $exclude[] = $term;
                $or_next = false;
                continue;
            }

            if ( $or_next && ! empty( $clauses ) ) {
                $last = count( $clauses ) - 1;
                if ( count( $clauses[ $last ] ) < self::MAX_ALTERNATIVES ) $clauses[ $last ][] = $term;
            } elseif ( count( $clauses ) < self::MAX_CLAUSES ) {
                $clauses[] = [ $term ];
            }
            $or_next = false;
        }

        $result['clauses'] = $clauses;
        $result['exclude'] = $exclude;

        if ( empty( $clauses ) ) return $result; // nur Operatoren / nur Ausschlüsse -> normales Verhalten

        $multi_alt = false;
        $any_word  = false;
        foreach ( $clauses as $alts ) {
            if ( count( $alts ) > 1 ) $multi_alt = true;
            foreach ( $alts as $t ) { if ( ! empty( $t['word'] ) ) $any_word = true; }
        }
        foreach ( $exclude as $t ) { if ( ! empty( $t['word'] ) ) $any_word = true; }

        $result['advanced'] = count( $clauses ) > 1 || $multi_alt || ! empty( $exclude ) || $has_phrase || $any_word;
        return $result;
    }

    /**
     * Gilt für diesen Begriff die Teilwort-Regel (nur Wortanfang statt Wortinneres)?
     * Nur bei einem einzelnen Wort ohne Leerzeichen.
     */
    public static function is_word_start_only( string $text, array $opts ): bool {
        if ( $opts['partial'] === 'always' ) return false;
        if ( preg_match( '/\s/u', $text ) ) return false;
        if ( $opts['partial'] === 'word' ) return true;
        return mb_strlen( $text ) < (int) $opts['min_len'];
    }

    /**
     * Berechnet die Treffermenge.
     *
     * @param array    $parsed  Ergebnis von parse()
     * @param callable $matcher function( array{text:string,phrase:bool,word:bool} $term ): int[]
     *                          liefert alle IDs, die EINEN Begriff treffen (Reihenfolge = Relevanz)
     * @return int[]
     */
    public static function resolve( array $parsed, callable $matcher ): array {
        $result = null;

        foreach ( $parsed['clauses'] as $alts ) {
            $set = [];
            foreach ( $alts as $term ) {
                foreach ( (array) $matcher( $term ) as $id ) $set[ (int) $id ] = true;
            }
            $ids    = array_keys( $set );
            $result = ( $result === null ) ? $ids : array_values( array_intersect( $result, $ids ) );
            if ( empty( $result ) ) break;
        }

        if ( $result === null ) return [];

        // Reine UND-Abfrage: zusätzlich die Treffer der kompletten Zeichenfolge
        // (z. B. ein Produktname mit Leerzeichen, der nur als Ganzes in einer Quelle steht).
        $whole = self::whole_term( $parsed );
        if ( $whole !== null ) {
            $result = array_values( array_unique( array_merge( $result, array_map( 'intval', (array) $matcher( $whole ) ) ) ) );
        }

        if ( ! empty( $parsed['exclude'] ) && ! empty( $result ) ) {
            $ex = [];
            foreach ( $parsed['exclude'] as $term ) {
                foreach ( (array) $matcher( $term ) as $id ) $ex[ (int) $id ] = true;
            }
            $result = array_values( array_diff( $result, array_keys( $ex ) ) );
        }

        return $result;
    }

    /**
     * Die Positiv-Begriffe als flache Liste (fürs Hervorheben von Treffern).
     *
     * @return string[]
     */
    public static function highlight_terms( array $parsed ): array {
        $out = [];
        foreach ( $parsed['clauses'] as $alts ) {
            foreach ( $alts as $term ) $out[] = $term['text'];
        }
        return array_values( array_unique( $out ) );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Teilwort-Prüfung und Rangfolge
    // ─────────────────────────────────────────────────────────────────────────

    /** Schlüssel eines Begriffs für Merkmal-Mengen (Groß-/Kleinschreibung egal). */
    public static function term_key( array $term ): string {
        return mb_strtolower( (string) $term['text'] ) . '|' . ( ! empty( $term['phrase'] ) ? '1' : '0' );
    }

    /** Steht $needle am Anfang eines Wortes in $text? (HTML wird vorher entfernt) */
    public static function word_start_in( string $needle, string $text ): bool {
        if ( $needle === '' || $text === '' ) return false;
        $text = html_entity_decode( strip_tags( $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        $re   = '~(?<![\p{L}\p{N}])' . preg_quote( $needle, '~' ) . '~iu';
        $hit  = @preg_match( $re, $text );
        if ( $hit === false ) {
            // ungültiges UTF-8 o. ä.: lieber behalten als fälschlich aussortieren
            return mb_stripos( $text, $needle ) !== false;
        }
        return $hit === 1;
    }

    /** Steht $needle als ganzes Wort (bzw. ganze Wortfolge) in $text? */
    public static function whole_word_in( string $needle, string $text ): bool {
        if ( $needle === '' || $text === '' ) return false;
        $re  = '~(?<![\p{L}\p{N}])' . preg_quote( $needle, '~' ) . '(?![\p{L}\p{N}])~iu';
        $hit = @preg_match( $re, $text );
        return $hit === 1;
    }

    /**
     * Punkte eines Begriffs für einen Titel (ohne Merkmal-Treffer).
     * 4 ganzes Wort, 2 Wortanfang, 1 Teilstring, 0 nicht enthalten.
     */
    public static function title_score( array $term, string $title ): int {
        $needle = (string) $term['text'];
        if ( $needle === '' || $title === '' ) return 0;

        if ( self::whole_word_in( $needle, $title ) ) return self::RANK_WHOLE_WORD;
        if ( empty( $term['phrase'] ) && self::word_start_in( $needle, $title ) ) return self::RANK_WORD_START;
        if ( mb_stripos( $title, $needle ) !== false ) return self::RANK_SUBSTRING;
        return 0;
    }

    /**
     * Sortiert IDs nach Rangfolge: ganze Wörter im Titel zuerst, dann Merkmal-Treffer
     * (Farbe, Größe, SKU, Kategorie, Marke), dann Wortanfang im Titel, dann Teilstring,
     * zuletzt Treffer nur in Kurztext/Beschreibung. Pro Klausel zählt die beste Alternative,
     * die Punkte der Klauseln werden addiert. Bei gleicher Punktzahl bleibt die Reihenfolge erhalten.
     *
     * @param int[]                        $ids
     * @param array<int,string>            $titles    post_id => Titel
     * @param array<string,array<int,bool>> $attr_sets term_key() => [ post_id => true ] (über Merkmale gefunden)
     * @return int[]
     */
    public static function rank( array $ids, array $parsed, array $titles, array $attr_sets ): array {
        if ( count( $ids ) < 2 || empty( $parsed['clauses'] ) ) return $ids;

        $scores = [];
        foreach ( $ids as $pos => $id ) {
            $total = 0;
            foreach ( $parsed['clauses'] as $alts ) {
                $best = 0;
                foreach ( $alts as $term ) {
                    $s = self::title_score( $term, (string) ( $titles[ $id ] ?? '' ) );
                    if ( isset( $attr_sets[ self::term_key( $term ) ][ $id ] ) ) $s = max( $s, self::RANK_ATTRIBUTE );
                    if ( $s > $best ) $best = $s;
                }
                $total += $best;
            }
            $scores[ $id ] = [ $total, $pos ];
        }

        $order = $ids;
        usort( $order, static function ( $a, $b ) use ( $scores ) {
            return $scores[ $b ][0] <=> $scores[ $a ][0] ?: $scores[ $a ][1] <=> $scores[ $b ][1];
        } );
        return array_values( $order );
    }

    /**
     * Gesamte Zeichenfolge als ein Begriff - nur bei reiner UND-Abfrage mit mind. 2 Wörtern ohne Phrase.
     */
    private static function whole_term( array $parsed ): ?array {
        if ( count( $parsed['clauses'] ) < 2 ) return null;

        $words = [];
        foreach ( $parsed['clauses'] as $alts ) {
            if ( count( $alts ) !== 1 || $alts[0]['phrase'] ) return null;
            $words[] = $alts[0]['text'];
        }
        return [ 'text' => implode( ' ', $words ), 'phrase' => false, 'word' => false ];
    }
}
