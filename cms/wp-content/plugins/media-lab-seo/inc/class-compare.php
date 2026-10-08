<?php
/**
 * MLT_Compare + MLT_Delta
 *
 * Zentrale Stelle für den Vergleichszeitraum (seit 1.11.0).
 *
 * MLT_Compare
 *  - Setting `mlt_compare_mode`: previous_period | previous_year | off
 *  - get_range(): leitet aus dem aktiven Zeitraum den Vergleichszeitraum ab
 *  - fetch():     holt die Vergleichsdaten (GSC + Analytics-Adapter) und
 *                 berechnet die Deltas – wird von Dashboard, WP-Widget und
 *                 Report-Mailer gleichermaßen verwendet
 *
 * MLT_Delta
 *  - calc():       Veränderung berechnen (Zählwert in %, Rate in PP, Position absolut)
 *  - html_admin(): Darstellung im Backend (CSS-Klassen aus dashboard.css)
 *  - html_mail():  Darstellung im E-Mail-Report (Inline-CSS)
 *
 * Hinweis: Die Klassen sind nicht GSC-spezifisch (auch Analytics wird verglichen),
 * deshalb eigene Datei statt Erweiterung von MLT_GSC_API.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MLT_Compare {

    const OPT_MODE      = 'mlt_compare_mode';
    const MODE_PREVIOUS = 'previous_period';
    const MODE_YEAR     = 'previous_year';
    const MODE_OFF      = 'off';

    /** Search Console hält Daten nur ca. 16 Monate vor. */
    const GSC_RETENTION_MONTHS = 16;

    /** So viele Zeilen des Vergleichszeitraums werden für den Abgleich der Top-Listen geholt. */
    const ROW_COMPARE_LIMIT = 500;

    // ── Setting ───────────────────────────────────────────────────────────────

    /** @return array<string,string> Modus => Label (Settings-Select) */
    public static function modes() : array {
        return [
            self::MODE_PREVIOUS => 'Vorperiode (gleich lang, direkt davor)',
            self::MODE_YEAR     => 'Vorjahreszeitraum',
            self::MODE_OFF      => 'Kein Vergleich',
        ];
    }

    /** Sanitize-Callback für register_setting(). */
    public static function sanitize_mode( $value ) : string {
        return array_key_exists( $value, self::modes() ) ? $value : self::MODE_PREVIOUS;
    }

    public static function get_mode() : string {
        $mode = self::sanitize_mode( get_option( self::OPT_MODE, self::MODE_PREVIOUS ) );

        /**
         * Filter: Vergleichsmodus überschreiben (z. B. projektspezifisch).
         *
         * @param string $mode previous_period | previous_year | off
         */
        return self::sanitize_mode( apply_filters( 'mlt_compare_mode', $mode ) );
    }

    // ── Vergleichszeitraum ────────────────────────────────────────────────────

    /**
     * Leitet den Vergleichszeitraum aus einem aktiven Zeitraum ab.
     *
     * @param  array{start:string,end:string} $range Y-m-d (inklusive)
     * @param  string|null                    $mode  null = Einstellung verwenden
     * @return array{start:string,end:string,mode:string,label:string,gsc_available:bool}|null
     *         null bei Modus „off" oder ungültigem Zeitraum
     */
    public static function get_range( array $range, ?string $mode = null ) : ?array {
        $mode = $mode ?? self::get_mode();
        if ( $mode === self::MODE_OFF ) return null;

        try {
            $utc   = new DateTimeZone( 'UTC' );
            $start = new DateTimeImmutable( (string) ( $range['start'] ?? '' ), $utc );
            $end   = new DateTimeImmutable( (string) ( $range['end']   ?? '' ), $utc );
        } catch ( Exception $e ) {
            return null;
        }
        if ( $start > $end ) return null;

        if ( $mode === self::MODE_YEAR ) {
            $c_start = self::shift_year( $start );
            $c_end   = self::shift_year( $end );
            $label   = 'Vorjahr';
        } else {
            // Gleich lang, lückenlos direkt vor dem aktiven Zeitraum
            $days    = (int) $start->diff( $end )->days;
            $c_end   = $start->modify( '-1 day' );
            $c_start = $c_end->modify( "-{$days} days" );
            $label   = 'Vorperiode';
        }

        $limit = ( new DateTimeImmutable( 'today', $utc ) )
            ->modify( '-' . self::GSC_RETENTION_MONTHS . ' months' );

        return [
            'start'         => $c_start->format( 'Y-m-d' ),
            'end'           => $c_end->format( 'Y-m-d' ),
            'mode'          => $mode,
            'label'         => $label,
            'gsc_available' => $c_start >= $limit,
        ];
    }

    /** 29.02. → 28.02. des Vorjahres (statt Überlauf auf 01.03.). */
    private static function shift_year( DateTimeImmutable $d ) : DateTimeImmutable {
        if ( $d->format( 'm-d' ) === '02-29' ) {
            return $d->setDate( (int) $d->format( 'Y' ) - 1, 2, 28 );
        }
        return $d->modify( '-1 year' );
    }

    /** z. B. „Vorperiode (01.07.2026 – 28.07.2026)" */
    public static function describe( array $cmp ) : string {
        return sprintf(
            '%s (%s – %s)',
            $cmp['label'],
            wp_date( 'd.m.Y', strtotime( $cmp['start'] ) ),
            wp_date( 'd.m.Y', strtotime( $cmp['end'] ) )
        );
    }

    // ── Zeilen-Vergleich (Top Keywords / Top Seiten) ──────────────────────────

    /**
     * Veränderung je Zeile gegenüber dem Vergleichszeitraum.
     *
     * Abgeglichen wird über die ersten ROW_COMPARE_LIMIT Zeilen des Vergleichszeitraums. Ein
     * Eintrag, der dort fehlt, war im Vergleichszeitraum nicht unter den Top 500 – er wird als
     * „neu" gekennzeichnet (nur Klicks, keine Positionsveränderung).
     *
     * @param array<int,array<string,mixed>> $rows       aktuelle Zeilen (clicks, position, + $key)
     * @param array<int,array<string,mixed>> $prev_rows  Zeilen des Vergleichszeitraums
     * @param string                         $key        'query' oder 'url'
     * @return array<string,array{clicks:?array,position:?array,prev:?array}> Schlüssel = query bzw. url
     */
    public static function row_deltas( array $rows, array $prev_rows, string $key ) : array {
        $prev = [];
        foreach ( $prev_rows as $r ) {
            $prev[ (string) ( $r[ $key ] ?? '' ) ] = $r;
        }

        $out = [];
        foreach ( $rows as $r ) {
            $k      = (string) ( $r[ $key ] ?? '' );
            $clicks = $r['clicks'] ?? null;

            if ( isset( $prev[ $k ] ) ) {
                $p         = $prev[ $k ];
                $out[ $k ] = [
                    'clicks'   => MLT_Delta::calc( $clicks, $p['clicks'] ?? null, 'abs' ),
                    'position' => MLT_Delta::calc( $r['position'] ?? null, $p['position'] ?? null, 'position' ),
                    'prev'     => [ 'clicks' => (int) ( $p['clicks'] ?? 0 ), 'position' => (float) ( $p['position'] ?? 0 ) ],
                ];
            } else {
                $out[ $k ] = [
                    'clicks'   => ( is_numeric( $clicks ) && (float) $clicks > 0 ) ? MLT_Delta::calc( $clicks, 0, 'count' ) : null,
                    'position' => null,
                    'prev'     => null,
                ];
            }
        }
        return $out;
    }

    /**
     * Kurze, eindeutige Beschriftungen für URLs (Top Seiten).
     * Normal nur der Pfad; haben mehrere URLs denselben Pfad (z. B. `https://x.at/` und
     * `https://www.x.at/` – ein Hinweis auf fehlende Weiterleitungen/Canonicals), erscheint
     * zusätzlich der Host, im Zweifel die volle URL.
     *
     * @param  string[] $urls
     * @return array<string,string> URL → Beschriftung
     */
    public static function page_labels( array $urls, int $max = 45 ) : array {
        $urls  = array_values( array_unique( array_map( 'strval', $urls ) ) );
        $path  = [];
        foreach ( $urls as $u ) {
            $p = preg_replace( '#^https?://[^/]+#', '', $u );
            $path[ $u ] = ( $p === '' || $p === null ) ? '/' : $p;
        }
        $count1 = array_count_values( $path );

        $host = [];
        foreach ( $urls as $u ) {
            $host[ $u ] = $count1[ $path[ $u ] ] > 1 ? (string) preg_replace( '#^https?://#', '', $u ) : $path[ $u ];
        }
        $count2 = array_count_values( $host );

        $labels = [];
        foreach ( $urls as $u ) {
            $label = $count2[ $host[ $u ] ] > 1 ? $u : $host[ $u ];
            $label = self::truncate( $label, $max );
            $labels[ $u ] = $label;
        }
        return $labels;
    }

    /** Kürzt zeichensicher (UTF-8) auf $max Zeichen inkl. „…". */
    private static function truncate( string $text, int $max ) : string {
        if ( function_exists( 'mb_strlen' ) ) {
            return mb_strlen( $text ) > $max ? mb_substr( $text, 0, $max - 1 ) . '…' : $text;
        }
        // Fallback ohne mbstring (WordPress liefert normalerweise Polyfills): UTF-8-sicher per Regex
        $len = preg_match_all( '/./us', $text );
        if ( $len === false || $len <= $max ) return $text;
        return ( preg_match( '/^.{0,' . ( $max - 1 ) . '}/us', $text, $m ) ? $m[0] : substr( $text, 0, $max - 1 ) ) . '…';
    }

    // ── Hinweis zur Messtoleranz ──────────────────────────────────────────────

    /**
     * Erklärt, warum Zahlen leicht von anderen Auswertungen abweichen können und
     * kleine Veränderungen wenig aussagekräftig sind – damit Rückfragen gar nicht
     * erst entstehen. Wird im Dashboard und im E-Mail-Report unter dem Vergleich
     * angezeigt. Übersetzbar (Text Domain `media-lab-seo`).
     *
     * @return string Klartext (Ausgabe immer mit esc_html()); leer = Hinweis ausgeblendet
     */
    public static function tolerance_note() : string {
        $note = __(
            'Hinweis zu den Zahlen: Alle Werte sind Messwerte aus der Google Search Console und dem Analytics-Tool und unterliegen einer üblichen Toleranz. Abweichungen zu anderen Auswertungen sind normal – etwa durch nachträglich korrigierte Daten der letzten Tage, Datenschutz-Filterung bei Suchanfragen, fehlende Cookie-Zustimmung oder unterschiedliche Zählweisen der Tools. Kleine Veränderungen, besonders bei niedrigen Zahlen, sind nicht aussagekräftig; entscheidend ist der Trend über mehrere Zeiträume.',
            'media-lab-seo'
        );

        /**
         * Filter: Text des Messtoleranz-Hinweises anpassen (z. B. kundenspezifisch)
         * oder mit einem leeren String ausblenden.
         *
         * @param string $note Übersetzter Standardtext
         */
        return trim( (string) apply_filters( 'mlt_compare_tolerance_note', $note ) );
    }

    // ── Daten holen + Deltas berechnen ────────────────────────────────────────

    /**
     * Holt die Vergleichsdaten und berechnet die Deltas.
     * Einziger Einstiegspunkt für Dashboard, Widget und Mailer.
     *
     * @param array       $range         ['start' => Y-m-d, 'end' => Y-m-d]
     * @param array       $gsc_cur       GSC-Übersicht des aktiven Zeitraums
     * @param array       $analytics_cur Analytics-Übersicht des aktiven Zeitraums
     * @param bool        $has_gsc       GSC verbunden + konfiguriert
     * @param object|null $adapter       Analytics-Adapter (MLT_Analytics_Adapter_Interface) oder null
     * @param array       $queries       aktuelle Top-Keywords (für Zeilen-Deltas), optional
     * @param array       $pages         aktuelle Top-Seiten (für Zeilen-Deltas), optional
     * @return array{
     *     range: array|null, gsc_prev: array, analytics_prev: array,
     *     gsc_deltas: array, analytics_deltas: array, notice: string, note: string,
     *     query_deltas: array, page_deltas: array
     * }
     */
    public static function fetch( array $range, array $gsc_cur, array $analytics_cur, bool $has_gsc, $adapter = null, array $queries = [], array $pages = [] ) : array {
        $result = [
            'range'            => null,
            'gsc_prev'         => [],
            'analytics_prev'   => [],
            'gsc_deltas'       => [],
            'analytics_deltas' => [],
            'notice'           => '',
            'note'             => '',
            'query_deltas'     => [],
            'page_deltas'      => [],
        ];

        $cmp = self::get_range( $range );
        if ( ! $cmp ) return $result;
        $result['range'] = $cmp;

        if ( $has_gsc && ! empty( $gsc_cur ) ) {
            if ( $cmp['gsc_available'] ) {
                $result['gsc_prev']   = MLT_GSC_API::instance()->get_overview( $cmp['start'], $cmp['end'] );
                $result['gsc_deltas'] = MLT_Delta::build_gsc( $gsc_cur, $result['gsc_prev'] );
            } else {
                $result['notice'] = 'Die Search Console speichert Daten nur rund 16 Monate – für diesen Vergleichszeitraum liegen keine Search-Console-Daten mehr vor.';
            }
        }

        if ( $adapter && ! empty( $analytics_cur ) ) {
            $result['analytics_prev']   = $adapter->get_overview( $cmp['start'], $cmp['end'] );
            $result['analytics_deltas'] = MLT_Delta::build_analytics( $analytics_cur, $result['analytics_prev'] );
        }

        // Zeilen-Deltas für Top Keywords / Top Seiten (nur mit belastbarer Vergleichsbasis)
        if ( $result['gsc_deltas'] && ( $queries || $pages ) ) {
            $gsc_api = MLT_GSC_API::instance();
            if ( $queries ) {
                $pq = $gsc_api->get_top_queries( self::ROW_COMPARE_LIMIT, $cmp['start'], $cmp['end'] );
                if ( $pq ) $result['query_deltas'] = self::row_deltas( $queries, $pq, 'query' );
            }
            if ( $pages ) {
                $pp = $gsc_api->get_top_pages( self::ROW_COMPARE_LIMIT, $cmp['start'], $cmp['end'] );
                if ( $pp ) $result['page_deltas'] = self::row_deltas( $pages, $pp, 'url' );
            }
        }

        // Messtoleranz-Hinweis nur, wenn tatsächlich Veränderungswerte angezeigt werden
        if ( $result['gsc_deltas'] || $result['analytics_deltas'] ) {
            $result['note'] = self::tolerance_note();
        }

        return $result;
    }
}


class MLT_Delta {

    const COLOR_GOOD = '#16a34a';
    const COLOR_BAD  = '#dc2626';
    const COLOR_FLAT = '#9ca3af';

    /**
     * Veränderung zwischen aktuellem Wert und Vergleichswert.
     *
     * @param mixed  $cur
     * @param mixed  $prev
     * @param string $type 'count'    Zählwert (Klicks, Impressionen, Sessions …) → relative Veränderung in %
     *                     'abs'      Zählwert, absolute Veränderung (kleine Zahlen in Tabellenzeilen)
     *                     'rate'     Quote (CTR, in %)                           → Differenz in Prozentpunkten
     *                     'position' Ø Position (kleiner = besser)               → absolute Differenz
     * @return array{direction:string,good:?bool,arrow:string,text:string,diff:?float}|null
     *         null, wenn kein sinnvoller Vergleich möglich ist
     */
    public static function calc( $cur, $prev, string $type = 'count' ) : ?array {
        if ( ! is_numeric( $cur ) || ! is_numeric( $prev ) ) return null;
        $cur  = (float) $cur;
        $prev = (float) $prev;

        $lower_is_better = false;
        $unit            = '';
        $dec             = 1;

        switch ( $type ) {
            case 'position':
                if ( $cur <= 0 || $prev <= 0 ) return null; // 0 = keine Daten, kein echter Wert
                $diff            = round( $cur - $prev, 1 );
                $lower_is_better = true;
                break;

            case 'rate':
                $diff = round( $cur - $prev, 1 );
                $unit = ' PP';
                break;

            case 'abs': // absolute Veränderung eines Zählwerts (Zeilen in Keyword-/Seiten-Tabellen)
                $diff = round( $cur - $prev, 0 );
                $dec  = 0;
                break;

            default: // count
                if ( $prev === 0.0 ) {
                    if ( $cur === 0.0 ) return self::build( 'flat', null, '0,0 %', null );
                    return self::build( 'up', true, 'neu', null ); // vorher nichts, jetzt etwas
                }
                $diff = round( ( $cur - $prev ) / $prev * 100, 1 );
                $unit = ' %';
        }

        $direction = $diff > 0 ? 'up' : ( $diff < 0 ? 'down' : 'flat' );
        $good      = $direction === 'flat' ? null : ( $lower_is_better ? $direction === 'down' : $direction === 'up' );
        $sign      = $diff > 0 ? '+' : ( $diff < 0 ? '−' : '' );
        $text      = $sign . number_format( abs( $diff ), $dec, ',', '.' ) . $unit;

        return self::build( $direction, $good, $text, $diff );
    }

    private static function build( string $direction, ?bool $good, string $text, ?float $diff ) : array {
        $arrows = [ 'up' => '▲', 'down' => '▼', 'flat' => '▬' ];
        return [
            'direction' => $direction,
            'good'      => $good,
            'arrow'     => $arrows[ $direction ],
            'text'      => $text,
            'diff'      => $diff,
        ];
    }

    /**
     * Deltas für die GSC-Übersicht (clicks, impressions, ctr, position).
     * Leer, wenn einer der beiden Zeiträume keine Daten hat: Ein Nullwert ist hier meist
     * ein fehlgeschlagener oder leerer API-Abruf (kein echter Messwert) – ein Delta
     * dagegen wäre irreführend (z. B. „−100 %" oder CTR-Sprung gegen 0).
     */
    public static function build_gsc( array $cur, array $prev ) : array {
        if ( empty( $prev ) || (int) ( $prev['impressions'] ?? 0 ) === 0 ) return [];
        if ( empty( $cur )  || (int) ( $cur['impressions']  ?? 0 ) === 0 ) return [];

        return [
            'clicks'      => self::calc( $cur['clicks']      ?? null, $prev['clicks']      ?? null, 'count' ),
            'impressions' => self::calc( $cur['impressions'] ?? null, $prev['impressions'] ?? null, 'count' ),
            'ctr'         => self::calc( $cur['ctr']         ?? null, $prev['ctr']         ?? null, 'rate' ),
            'position'    => self::calc( $cur['position']    ?? null, $prev['position']    ?? null, 'position' ),
        ];
    }

    /** Deltas für die Analytics-Übersicht (pageviews, sessions, users). Leer, wenn ein Zeitraum nur Nullwerte hat. */
    public static function build_analytics( array $cur, array $prev ) : array {
        $keys = [ 'pageviews', 'sessions', 'users' ];

        $prev_sum = 0;
        $cur_sum  = 0;
        foreach ( $keys as $k ) {
            $prev_sum += (int) ( $prev[ $k ] ?? 0 );
            $cur_sum  += (int) ( $cur[ $k ]  ?? 0 );
        }
        if ( $prev_sum === 0 || $cur_sum === 0 ) return [];

        $out = [];
        foreach ( $keys as $k ) {
            $out[ $k ] = self::calc( $cur[ $k ] ?? null, $prev[ $k ] ?? null, 'count' );
        }
        return $out;
    }

    // ── Darstellung ───────────────────────────────────────────────────────────

    /** Backend: Klassen mlt-delta--good | --bad | --flat (dashboard.css). */
    public static function html_admin( ?array $delta, string $title = '' ) : string {
        if ( ! $delta ) return '';

        $cls = $delta['good'] === null ? 'flat' : ( $delta['good'] ? 'good' : 'bad' );

        return '<span class="mlt-delta mlt-delta--' . $cls . '"'
            . ( $title !== '' ? ' title="' . esc_attr( $title ) . '"' : '' ) . '>'
            . esc_html( $delta['arrow'] . ' ' . $delta['text'] )
            . '</span>';
    }

    /** E-Mail, Tabellenzeile: kleine Zeile unter dem Wert (`<br>` + Span, nur Inline-CSS). */
    public static function html_mail_inline( ?array $delta ) : string {
        if ( ! $delta ) return '';

        $color = $delta['good'] === null ? self::COLOR_FLAT : ( $delta['good'] ? self::COLOR_GOOD : self::COLOR_BAD );

        return '<br><span style="font-size:11px;font-weight:600;color:' . $color . '">'
            . esc_html( $delta['arrow'] . ' ' . $delta['text'] )
            . '</span>';
    }

    /** E-Mail: Inline-CSS (kein <style>-Block, kein JS). */
    public static function html_mail( ?array $delta ) : string {
        if ( ! $delta ) return '';

        $color = $delta['good'] === null ? self::COLOR_FLAT : ( $delta['good'] ? self::COLOR_GOOD : self::COLOR_BAD );

        return '<div style="margin-top:6px;font-size:11px;font-weight:600;color:' . $color . '">'
            . esc_html( $delta['arrow'] . ' ' . $delta['text'] )
            . '</div>';
    }
}
