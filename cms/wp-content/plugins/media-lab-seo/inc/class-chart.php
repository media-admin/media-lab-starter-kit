<?php
/**
 * MLT_Timeseries + MLT_Chart
 *
 * Verlaufs-Chart im SEO-Dashboard (seit 1.12.0).
 *
 * MLT_Timeseries
 *  - holt Tageswerte (GSC: Klicks, Impressionen; Analytics: Seitenaufrufe, Sessions)
 *    für den aktiven Zeitraum und – falls aktiv – den Vergleichszeitraum
 *  - fehlgeschlagene Abrufe (null) führen nur dazu, dass der jeweilige Chart fehlt
 *
 * MLT_Chart
 *  - rendert die Linien-Charts als Inline-SVG direkt auf dem Server (keine Bibliothek,
 *    kein CDN, kein JavaScript für die Darstellung). Tooltips = native <title>-Elemente,
 *    Hover-Hilfslinie per CSS. Nur das Umschalten der Reiter braucht ein paar Zeilen JS
 *    (assets/dashboard.js).
 *  - Der Chart bleibt bewusst unabhängig von WordPress-Funktionen, die nicht in der
 *    Admin-Umgebung stehen (außer esc_*, wp_date, number_format_i18n).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MLT_Timeseries {

    /** Chart-Definitionen: Schlüssel → [ Quelle, Metrik, Beschriftung, Farbe ] */
    const CHARTS = [
        'clicks'      => [ 'gsc',       'clicks',      'Klicks',        '#2563eb' ],
        'impressions' => [ 'gsc',       'impressions', 'Impressionen',  '#7c3aed' ],
        'pageviews'   => [ 'analytics', 'pageviews',   'Seitenaufrufe', '#0891b2' ],
        'sessions'    => [ 'analytics', 'sessions',    'Sessions',      '#0284c7' ],
    ];

    /**
     * Alle Kalendertage von $start bis $end (inklusive) als Y-m-d-Liste.
     *
     * @return string[]
     */
    public static function days( string $start, string $end ) : array {
        try {
            $utc = new DateTimeZone( 'UTC' );
            $d   = new DateTimeImmutable( $start, $utc );
            $e   = new DateTimeImmutable( $end, $utc );
        } catch ( Exception $ex ) {
            return [];
        }
        $out = [];
        // Obergrenze als Schutz gegen versehentlich riesige Zeiträume
        for ( $i = 0; $d <= $e && $i < 800; $i++, $d = $d->modify( '+1 day' ) ) {
            $out[] = $d->format( 'Y-m-d' );
        }
        return $out;
    }

    /**
     * Baut aus einer Datum→Werte-Map eine lückenlose Liste für eine Metrik.
     *
     * @param array<string,array<string,int>> $map
     * @param string[]                        $days
     * @return int[]
     */
    public static function column( array $map, array $days, string $metric ) : array {
        $out = [];
        foreach ( $days as $day ) {
            $out[] = (int) ( $map[ $day ][ $metric ] ?? 0 );
        }
        return $out;
    }

    /** So viele Tage am Ende ohne Daten gelten als Datenverzögerung (und werden nicht gezeichnet). */
    const MAX_LAG_DAYS = 3;

    /**
     * Anzahl der Tage am Ende des Zeitraums, für die der Dienst noch keine Daten liefert.
     *
     * Google liefert Tageswerte mit Verzögerung; für die jüngsten Tage fehlen die Zeilen
     * einfach. Als „0" gezeichnet täuschte das einen Einbruch vor. Nur bei Zeiträumen, die
     * bis kurz vor heute reichen, und höchstens MAX_LAG_DAYS Tage – ein längerer Leerlauf
     * ist wahrscheinlich echt.
     *
     * @param array<string,mixed> $map Datum → Werte
     */
    public static function trailing_lag( array $map, string $end ) : int {
        if ( ! $map || $end < gmdate( 'Y-m-d', strtotime( '-' . ( self::MAX_LAG_DAYS + 1 ) . ' days' ) ) ) return 0;

        $last = (string) max( array_keys( $map ) );
        if ( $last >= $end ) return 0;

        $lag = (int) round( ( strtotime( $end . ' UTC' ) - strtotime( $last . ' UTC' ) ) / DAY_IN_SECONDS );
        return ( $lag >= 1 && $lag <= self::MAX_LAG_DAYS ) ? $lag : 0;
    }

    /**
     * Sammelt alle verfügbaren Charts.
     *
     * @param array{start:string,end:string}      $range
     * @param array|null                          $cmp     Rückgabe von MLT_Compare::get_range() oder null
     * @param bool                                $has_gsc
     * @param object|null                         $adapter Analytics-Adapter
     * @return array<int,array<string,mixed>> Liste von Charts (key,label,color,dates,cur,prev_dates,prev)
     */
    public static function collect( array $range, ?array $cmp, bool $has_gsc, $adapter = null ) : array {
        $days      = self::days( $range['start'], $range['end'] );
        $prev_days = $cmp ? self::days( $cmp['start'], $cmp['end'] ) : [];
        if ( count( $days ) < 2 ) return [];

        $sources = [];

        if ( $has_gsc ) {
            $gsc = MLT_GSC_API::instance();
            $cur = $gsc->get_timeseries( $range['start'], $range['end'] );
            if ( is_array( $cur ) ) {
                $prev = ( $cmp && $cmp['gsc_available'] ) ? $gsc->get_timeseries( $cmp['start'], $cmp['end'] ) : null;
                $sources['gsc'] = [ 'cur' => $cur, 'prev' => is_array( $prev ) ? $prev : null, 'lag' => self::trailing_lag( $cur, $range['end'] ) ];
            }
        }

        if ( $adapter instanceof MLT_Analytics_Timeseries_Interface ) {
            $cur = $adapter->get_timeseries( $range['start'], $range['end'] );
            if ( is_array( $cur ) ) {
                $prev = $cmp ? $adapter->get_timeseries( $cmp['start'], $cmp['end'] ) : null;
                $sources['analytics'] = [ 'cur' => $cur, 'prev' => is_array( $prev ) ? $prev : null, 'lag' => self::trailing_lag( $cur, $range['end'] ) ];
            }
        }

        $charts = [];
        foreach ( self::CHARTS as $key => [ $source, $metric, $label, $color ] ) {
            if ( ! isset( $sources[ $source ] ) ) continue;

            // Tage ohne Daten am Ende weglassen – im Vergleichszeitraum dieselbe Anzahl, damit
            // Tag für Tag gleich viele Tage verglichen werden
            $lag        = (int) $sources[ $source ]['lag'];
            $use_days   = $lag ? array_slice( $days, 0, count( $days ) - $lag ) : $days;
            $use_prev   = $lag && $prev_days ? array_slice( $prev_days, 0, count( $prev_days ) - $lag ) : $prev_days;
            if ( count( $use_days ) < 2 ) continue;

            $cur_vals = self::column( $sources[ $source ]['cur'], $use_days, $metric );
            if ( array_sum( $cur_vals ) === 0 ) continue; // keine Daten → kein Chart

            $prev_vals = null;
            if ( $sources[ $source ]['prev'] !== null && $use_prev ) {
                $p = self::column( $sources[ $source ]['prev'], $use_prev, $metric );
                if ( array_sum( $p ) > 0 ) $prev_vals = $p;
            }

            $charts[] = [
                'key'        => $key,
                'label'      => $label,
                'color'      => $color,
                'dates'      => $use_days,
                'cur'        => $cur_vals,
                'prev_dates' => $prev_vals !== null ? $use_prev : [],
                'prev'       => $prev_vals,
                'lag'        => $lag,
            ];
        }
        return $charts;
    }
}


class MLT_Chart {

    const W = 900;   // viewBox-Breite
    const H = 280;   // viewBox-Höhe
    const L = 52;    // Rand links (Y-Beschriftung)
    const R = 16;
    const T = 14;
    const B = 30;    // Rand unten (X-Beschriftung)

    // ── Hilfsfunktionen ───────────────────────────────────────────────────────

    /**
     * Y-Achse: schöne Schrittweite (1, 2, 5 × 10^n) mit höchstens 6 Intervallen.
     *
     * @return array{0:float,1:float,2:int} [ Achsen-Maximum, Schrittweite, Anzahl Intervalle ]
     */
    public static function nice_scale( float $max ) : array {
        if ( $max <= 0 ) return [ 1.0, 1.0, 1 ];

        $exp = (int) floor( log10( $max ) ) - 1;
        for ( $e = $exp; $e <= $exp + 2; $e++ ) {
            foreach ( [ 1, 2, 5 ] as $m ) {
                $step = (float) ( $m * 10 ** $e );
                $n    = (int) ceil( $max / $step );
                if ( $n <= 6 && $step >= 1 ) return [ $n * $step, $step, $n ];
            }
        }
        $step = (float) ceil( $max );
        return [ $step, $step, 1 ];
    }

    private static function fmt( $n ) : string {
        return number_format_i18n( (float) $n, 0 );
    }

    private static function num( float $n ) : string {
        return rtrim( rtrim( number_format( $n, 2, '.', '' ), '0' ), '.' );
    }

    private static function date_label( string $ymd, string $format = 'd.m.' ) : string {
        return wp_date( $format, strtotime( $ymd . ' 12:00:00 UTC' ) );
    }

    // ── SVG ───────────────────────────────────────────────────────────────────

    /**
     * Linien-Chart als SVG-String.
     *
     * @param array{
     *   label:string, color:string, dates:string[], cur:int[],
     *   prev_dates?:string[], prev?:int[]|null, prev_label?:string
     * } $c
     */
    public static function line_svg( array $c ) : string {
        $dates = array_values( $c['dates'] ?? [] );
        $cur   = array_values( $c['cur'] ?? [] );
        $n     = min( count( $dates ), count( $cur ) );
        if ( $n < 2 ) return '';

        $prev       = isset( $c['prev'] ) && is_array( $c['prev'] ) ? array_values( $c['prev'] ) : null;
        $prev_dates = array_values( $c['prev_dates'] ?? [] );
        $color      = preg_match( '/^#[0-9a-fA-F]{3,8}$/', (string) ( $c['color'] ?? '' ) ) ? $c['color'] : '#2563eb';
        $label      = (string) ( $c['label'] ?? '' );
        $prev_label = (string) ( $c['prev_label'] ?? __( 'Vergleich', 'media-lab-seo' ) );

        $pw = self::W - self::L - self::R;
        $ph = self::H - self::T - self::B;

        $max = max( $cur );
        if ( $prev ) $max = max( $max, max( $prev ) );
        [ $ymax, $ystep, $yn ] = self::nice_scale( (float) $max );

        $x = static function ( int $i ) use ( $n, $pw ) : float {
            return self::L + ( $n > 1 ? $i / ( $n - 1 ) : 0 ) * $pw;
        };
        $y = static function ( float $v ) use ( $ymax, $ph ) : float {
            return self::T + $ph - ( $v / $ymax ) * $ph;
        };

        $uid = 'mltc' . substr( md5( $label . $dates[0] . $n ), 0, 6 );

        $svg  = '<svg class="mlt-chart" viewBox="0 0 ' . self::W . ' ' . self::H . '" xmlns="http://www.w3.org/2000/svg" role="img" '
              . 'aria-labelledby="' . esc_attr( $uid ) . '-t" preserveAspectRatio="xMidYMid meet">';
        $svg .= '<title id="' . esc_attr( $uid ) . '-t">' . esc_html( sprintf(
            /* translators: 1: metric, 2: first day, 3: last day, 4: total */
            __( 'Verlauf %1$s, %2$s bis %3$s, gesamt %4$s', 'media-lab-seo' ),
            $label,
            self::date_label( $dates[0], 'd.m.Y' ),
            self::date_label( $dates[ $n - 1 ], 'd.m.Y' ),
            self::fmt( array_sum( array_slice( $cur, 0, $n ) ) )
        ) ) . '</title>';

        // Gitterlinien + Y-Beschriftung
        for ( $g = 0; $g <= $yn; $g++ ) {
            $val = $ystep * $g;
            $gy  = self::num( $y( $val ) );
            $svg .= '<line class="mlt-chart__grid" x1="' . self::L . '" x2="' . ( self::W - self::R ) . '" y1="' . $gy . '" y2="' . $gy . '"/>';
            $svg .= '<text class="mlt-chart__ytick" x="' . ( self::L - 8 ) . '" y="' . self::num( $y( $val ) + 4 ) . '" text-anchor="end">' . esc_html( self::fmt( $val ) ) . '</text>';
        }

        // X-Beschriftung: höchstens 7 Marken, gleichmäßig verteilt
        $ticks = min( 7, $n );
        $seen  = [];
        for ( $k = 0; $k < $ticks; $k++ ) {
            $i = (int) round( $k * ( $n - 1 ) / max( 1, $ticks - 1 ) );
            if ( isset( $seen[ $i ] ) ) continue;
            $seen[ $i ] = true;
            $anchor = $i === 0 ? 'start' : ( $i === $n - 1 ? 'end' : 'middle' );
            $svg .= '<text class="mlt-chart__xtick" x="' . self::num( $x( $i ) ) . '" y="' . ( self::H - 8 ) . '" text-anchor="' . $anchor . '">'
                  . esc_html( self::date_label( $dates[ $i ] ) ) . '</text>';
        }

        // Fläche + Linie (aktueller Zeitraum)
        $pts = [];
        for ( $i = 0; $i < $n; $i++ ) {
            $pts[] = self::num( $x( $i ) ) . ',' . self::num( $y( (float) $cur[ $i ] ) );
        }
        $base = self::num( $y( 0 ) );
        $svg .= '<path class="mlt-chart__area" fill="' . esc_attr( $color ) . '" d="M' . self::num( $x( 0 ) ) . ',' . $base
              . ' L' . implode( ' L', $pts ) . ' L' . self::num( $x( $n - 1 ) ) . ',' . $base . ' Z"/>';

        // Vergleichslinie (gestrichelt, unter der aktuellen Linie)
        if ( $prev ) {
            $pp = [];
            $pn = min( count( $prev ), $n );
            for ( $i = 0; $i < $pn; $i++ ) {
                $pp[] = self::num( $x( $i ) ) . ',' . self::num( $y( (float) $prev[ $i ] ) );
            }
            if ( count( $pp ) >= 2 ) {
                $svg .= '<polyline class="mlt-chart__prev" fill="none" points="' . implode( ' ', $pp ) . '"/>';
            }
        }

        $svg .= '<polyline class="mlt-chart__line" fill="none" stroke="' . esc_attr( $color ) . '" points="' . implode( ' ', $pts ) . '"/>';

        // Punkte nur bei kurzen Zeiträumen
        if ( $n <= 31 ) {
            for ( $i = 0; $i < $n; $i++ ) {
                $svg .= '<circle class="mlt-chart__pt" fill="' . esc_attr( $color ) . '" cx="' . self::num( $x( $i ) ) . '" cy="' . self::num( $y( (float) $cur[ $i ] ) ) . '" r="2.5"/>';
            }
        }

        // Hover-Spalten mit Tooltip
        $colw = $pw / max( 1, $n - 1 );
        for ( $i = 0; $i < $n; $i++ ) {
            $cx   = $x( $i );
            $rx   = max( (float) self::L, $cx - $colw / 2 );
            $rw   = min( $colw, self::W - self::R - $rx );

            $tip  = self::date_label( $dates[ $i ], 'D, d.m.Y' ) . "\n" . $label . ': ' . self::fmt( $cur[ $i ] );
            if ( $prev && isset( $prev[ $i ] ) ) {
                $pd   = $prev_dates[ $i ] ?? null;
                $tip .= "\n" . $prev_label . ( $pd ? ' (' . self::date_label( $pd, 'D, d.m.Y' ) . ')' : '' ) . ': ' . self::fmt( $prev[ $i ] );
                $d    = MLT_Delta::calc( $cur[ $i ], $prev[ $i ], 'count' );
                if ( $d && $d['text'] !== 'neu' ) $tip .= ' (' . $d['arrow'] . ' ' . $d['text'] . ')';
            }

            $svg .= '<g class="mlt-chart__hit">'
                  . '<rect x="' . self::num( $rx ) . '" y="' . self::T . '" width="' . self::num( $rw ) . '" height="' . $ph . '" fill="transparent"/>'
                  . '<line class="mlt-chart__guide" x1="' . self::num( $cx ) . '" x2="' . self::num( $cx ) . '" y1="' . self::T . '" y2="' . ( self::T + $ph ) . '"/>'
                  . '<circle class="mlt-chart__dot" fill="' . esc_attr( $color ) . '" cx="' . self::num( $cx ) . '" cy="' . self::num( $y( (float) $cur[ $i ] ) ) . '" r="4"/>'
                  . '<title>' . esc_html( $tip ) . '</title>'
                  . '</g>';
        }

        return $svg . '</svg>';
    }

    // ── E-Mail-Grafiken (seit 1.13.0) ─────────────────────────────────────────
    //
    // E-Mail-Programme führen kein JavaScript aus, Gmail/Outlook zeigen kein Inline-SVG und
    // blockieren externe Bilder standardmäßig. Deshalb bestehen die Grafiken im Report
    // ausschließlich aus verschachtelten HTML-Tabellen mit festen Zellhöhen und Hintergrund-
    // farben (funktioniert auch im Outlook-Desktop-Renderer). Nur Inline-Styles, keine Klassen.

    /** Gemeinsamer Stil für „leere" Zellen, die nur Fläche sind (Outlook-sicher). */
    const MAIL_PX = 'font-size:1px;line-height:1px';

    /**
     * Fasst die Tageswerte eines Charts für die Mail zu Balken zusammen:
     * bis 31 Tage täglich, bis 120 Tage wöchentlich, darüber in 4-Wochen-Blöcken.
     * Die Blöcke werden vom Ende her gebildet (ein unvollständiger Rest am Anfang entfällt),
     * damit kein Balken nur aus einem Teilzeitraum besteht.
     *
     * @param array<string,mixed> $chart Eintrag aus MLT_Timeseries::collect()
     * @return array{size:int,buckets:array<int,array{date:string,cur:int,prev:?int}>,has_prev:bool}
     */
    public static function bucketize( array $chart ) : array {
        $dates = array_values( $chart['dates'] ?? [] );
        $cur   = array_values( $chart['cur'] ?? [] );
        $prev  = isset( $chart['prev'] ) && is_array( $chart['prev'] ) ? array_values( $chart['prev'] ) : null;
        $len   = min( count( $dates ), count( $cur ) );

        $size = $len <= 31 ? 1 : ( $len <= 120 ? 7 : 28 );

        $blocks = static function ( array $values, int $len, int $size ) : array {
            $count = intdiv( $len, $size );
            $out   = [];
            $first = $len - $count * $size; // übersprungene Tage am Anfang
            for ( $b = 0; $b < $count; $b++ ) {
                $from = $first + $b * $size;
                $out[] = [ $from, array_sum( array_slice( $values, $from, $size ) ) ];
            }
            return $out;
        };

        $cb = $blocks( $cur, $len, $size );
        $pb = $prev !== null ? $blocks( $prev, count( $prev ), $size ) : [];

        $buckets = [];
        $offset  = count( $pb ) - count( $cb ); // rechtsbündig ausrichten
        foreach ( $cb as $i => [ $from, $sum ] ) {
            $pi        = $i + $offset;
            $buckets[] = [
                'date' => $dates[ $from ],
                'cur'  => (int) $sum,
                'prev' => ( $prev !== null && $pi >= 0 && isset( $pb[ $pi ] ) ) ? (int) $pb[ $pi ][1] : null,
            ];
        }

        return [
            'size'     => $size,
            'buckets'  => $buckets,
            'has_prev' => $prev !== null && $pb !== [],
        ];
    }

    /** Waagerechter Balken (Anteil 0–1) für Listen; Breite = 100 % der Zelle. */
    public static function mail_bar( float $ratio, string $color = '#2563eb' ) : string {
        $color = preg_match( '/^#[0-9a-fA-F]{3,8}$/', $color ) ? $color : '#2563eb';
        $pct   = (int) max( 1, min( 100, round( $ratio * 100 ) ) );
        $px    = self::MAIL_PX;

        $cells = '<td width="' . $pct . '%" height="4" bgcolor="' . $color . '" style="' . $px . '">&nbsp;</td>';
        if ( $pct < 100 ) {
            $cells .= '<td height="4" bgcolor="#f3f4f6" style="' . $px . '">&nbsp;</td>';
        }

        // Abstandszeile: <div> mit fester Schriftgröße wirkt auch im Outlook-Renderer (margin/padding nicht)
        return '<div style="font-size:5px;line-height:5px">&nbsp;</div>'
             . '<table width="100%" cellpadding="0" cellspacing="0" border="0"><tr>' . $cells . '</tr></table>';
    }

    /**
     * Ein senkrechter Balken (Höhe in px) als einzeilige Tabelle. Er sitzt unten, weil die
     * umgebende Zelle `valign="bottom"` hat – ein Abstandshalter ist nicht nötig.
     * Wert 0 = dünne Grundlinie.
     */
    private static function mail_vbar( int $value, int $max, int $height, int $width, string $color ) : string {
        if ( $value > 0 ) {
            $h = (int) max( 2, min( $height, round( $value / max( 1, $max ) * $height ) ) );
        } else {
            $h     = 1;
            $color = '#e5e7eb';
        }

        return '<table width="' . $width . '" cellpadding="0" cellspacing="0" border="0"><tr>'
             . '<td height="' . $h . '" bgcolor="' . $color . '" style="' . self::MAIL_PX . '">&nbsp;</td></tr></table>';
    }

    /**
     * Säulendiagramm (aktueller Zeitraum + optional Vergleich) als E-Mail-taugliche Tabelle.
     * Gibt einen leeren String zurück, wenn zu wenige Daten vorliegen.
     *
     * @param array<string,mixed> $chart Eintrag aus MLT_Timeseries::collect()
     * @param array|null          $cmp   MLT_Compare::get_range() (für die Legende)
     */
    public static function mail_columns( array $chart, ?array $cmp = null, int $width = 536, int $height = 72 ) : string {
        $b = self::bucketize( $chart );
        $n = count( $b['buckets'] );
        if ( $n < 2 ) return '';

        $color = preg_match( '/^#[0-9a-fA-F]{3,8}$/', (string) ( $chart['color'] ?? '' ) ) ? $chart['color'] : '#2563eb';
        $has_p = $b['has_prev'];

        $max = 1;
        foreach ( $b['buckets'] as $k ) {
            $max = max( $max, $k['cur'], (int) ( $k['prev'] ?? 0 ) );
        }

        $cell = max( 4, (int) floor( $width / $n ) );
        $tw   = $cell * $n;
        $bw   = $has_p ? max( 1, (int) floor( ( $cell - 3 ) / 2 ) ) : max( 2, $cell - 3 );
        $gap  = max( 1, $cell - ( $has_p ? 2 * $bw : $bw ) );

        $unit = $b['size'] === 1
            ? __( 'pro Tag', 'media-lab-seo' )
            : ( $b['size'] === 7 ? __( 'pro Woche', 'media-lab-seo' ) : __( 'je 4 Wochen', 'media-lab-seo' ) );

        $first = $b['buckets'][0]['date'];
        $last  = $b['buckets'][ $n - 1 ]['date'];
        $label = (string) ( $chart['label'] ?? '' );

        $px  = self::MAIL_PX;
        $out = '<table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td height="14" style="' . $px . '">&nbsp;</td></tr><tr><td align="center">';

        // Titel + Legende (einfache Textzeile, in jedem Client lesbar)
        $out .= '<table width="' . $tw . '" cellpadding="0" cellspacing="0" border="0"><tr>'
              . '<td style="font-size:12px;font-weight:600;color:#374151;padding-bottom:6px">' . esc_html( $label . ' ' . $unit ) . '</td>'
              . '<td align="right" style="font-size:11px;color:#9ca3af;padding-bottom:6px">'
              . '<span style="color:' . esc_attr( $color ) . '">&#9632;</span> '
              . esc_html( self::date_label( $chart['dates'][0], 'd.m.' ) . ' – ' . self::date_label( $chart['dates'][ count( $chart['dates'] ) - 1 ], 'd.m.' ) );
        if ( $has_p && $cmp ) {
            $pd   = array_values( $chart['prev_dates'] ?? [] );
            $from = $pd ? $pd[0] : $cmp['start'];
            $to   = $pd ? $pd[ count( $pd ) - 1 ] : $cmp['end'];
            $out .= '&nbsp;&nbsp; <span style="color:#d1d5db">&#9632;</span> '
                  . esc_html( $cmp['label'] . ' ' . self::date_label( $from, 'd.m.' ) . ' – ' . self::date_label( $to, 'd.m.' ) );
        }
        $out .= '</td></tr></table>';

        // Balken: je Zeitabschnitt eine Zelle für den aktuellen Wert, optional eine für den Vergleich, dann Lücke
        $out .= '<table width="' . $tw . '" cellpadding="0" cellspacing="0" border="0" style="background:#f9fafb"><tr valign="bottom">';
        foreach ( $b['buckets'] as $k ) {
            $out .= '<td width="' . $bw . '" valign="bottom">' . self::mail_vbar( $k['cur'], $max, $height, $bw, $color ) . '</td>';
            if ( $has_p ) {
                $out .= '<td width="' . $bw . '" valign="bottom">' . self::mail_vbar( (int) ( $k['prev'] ?? 0 ), $max, $height, $bw, '#d1d5db' ) . '</td>';
            }
            $out .= '<td width="' . $gap . '"></td>';
        }
        $out .= '</tr></table>';

        // X-Achse: erster und letzter Balken, Spitzenwert
        $out .= '<table width="' . $tw . '" cellpadding="0" cellspacing="0" border="0"><tr>'
              . '<td style="font-size:10px;color:#9ca3af;padding-top:4px">' . esc_html( self::date_label( $first, 'd.m.' ) ) . '</td>'
              . '<td align="center" style="font-size:10px;color:#9ca3af;padding-top:4px">'
              . esc_html( sprintf( /* translators: %s: peak value */ __( 'Spitze: %s', 'media-lab-seo' ), self::fmt( $max ) ) ) . '</td>'
              . '<td align="right" style="font-size:10px;color:#9ca3af;padding-top:4px">' . esc_html( self::date_label( $last, 'd.m.' ) ) . '</td>'
              . '</tr></table>';

        // Hinweis, wenn die jüngsten Tage wegen Datenverzögerung fehlen (damit niemand nachfragen muss)
        if ( ! empty( $chart['lag'] ) ) {
            $out .= '<table width="' . $tw . '" cellpadding="0" cellspacing="0" border="0"><tr>'
                  . '<td style="font-size:10px;line-height:14px;color:#9ca3af;padding-top:2px">' . esc_html( self::lag_note( (int) $chart['lag'] ) ) . '</td>'
                  . '</tr></table>';
        }

        return $out . '</td></tr><tr><td height="10" style="' . $px . '">&nbsp;</td></tr></table>';
    }

    // ── Karte ─────────────────────────────────────────────────────────────────

    /** Übersetzbarer Hinweis, wenn die jüngsten Tage wegen Datenverzögerung nicht dargestellt werden. */
    public static function lag_note( int $lag ) : string {
        return sprintf(
            /* translators: %d: number of days */
            _n(
                'Der letzte Tag wird noch nicht dargestellt (Datenverzögerung).',
                'Die letzten %d Tage werden noch nicht dargestellt (Datenverzögerung).',
                $lag,
                'media-lab-seo'
            ),
            $lag
        );
    }

    /**
     * Gibt die Karte „Verlauf" mit Reitern aus (nichts, wenn keine Charts vorliegen).
     *
     * @param array<int,array<string,mixed>> $charts  Rückgabe von MLT_Timeseries::collect()
     * @param array|null                     $cmp     Rückgabe von MLT_Compare::get_range()
     * @param array{start:string,end:string} $range
     */
    public static function render_card( array $charts, ?array $cmp, array $range ) : void {
        if ( ! $charts ) return;

        $prev_name  = $cmp['label'] ?? __( 'Vergleich', 'media-lab-seo' );
        ?>
        <div class="mlt-card mlt-card--full mlt-chart-card" id="mlt-chart-card">
            <div class="mlt-card__header">
                <span class="mlt-card__icon">📈</span>
                <h2><?php echo esc_html__( 'Verlauf', 'media-lab-seo' ); ?></h2>
                <div class="mlt-chart-tabs" role="tablist">
                    <?php foreach ( $charts as $i => $c ) : ?>
                        <button type="button" role="tab"
                                class="mlt-chart-tab<?php echo $i === 0 ? ' is-active' : ''; ?>"
                                data-chart="<?php echo esc_attr( $c['key'] ); ?>"
                                aria-selected="<?php echo $i === 0 ? 'true' : 'false'; ?>">
                            <?php echo esc_html( $c['label'] ); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="mlt-card__body">
                <?php foreach ( $charts as $i => $c ) :
                    $svg = self::line_svg( $c + [ 'prev_label' => $prev_name ] );
                    if ( $svg === '' ) continue;

                    // Legende aus den tatsächlich gezeichneten Tagen (bei Datenverzögerung kürzer als der Zeitraum)
                    $cur_label  = self::date_label( $c['dates'][0], 'd.m.' ) . ' – ' . self::date_label( $c['dates'][ count( $c['dates'] ) - 1 ], 'd.m.Y' );
                    $prev_range = $c['prev_dates']
                        ? self::date_label( $c['prev_dates'][0], 'd.m.' ) . ' – ' . self::date_label( $c['prev_dates'][ count( $c['prev_dates'] ) - 1 ], 'd.m.Y' )
                        : '';
                    ?>
                    <div class="mlt-chart-panel" data-chart-panel="<?php echo esc_attr( $c['key'] ); ?>" <?php echo $i === 0 ? '' : 'hidden'; ?>>
                        <div class="mlt-chart-legend">
                            <span class="mlt-chart-legend__item">
                                <i class="mlt-chart-legend__swatch" style="background:<?php echo esc_attr( $c['color'] ); ?>"></i>
                                <?php echo esc_html( $cur_label ); ?>
                            </span>
                            <?php if ( $c['prev'] ) : ?>
                            <span class="mlt-chart-legend__item mlt-chart-legend__item--prev">
                                <i class="mlt-chart-legend__swatch mlt-chart-legend__swatch--dash"></i>
                                <?php echo esc_html( $prev_name . ' (' . $prev_range . ')' ); ?>
                            </span>
                            <?php endif; ?>
                            <?php if ( ! empty( $c['lag'] ) ) : ?>
                            <span class="mlt-chart-legend__item mlt-chart-legend__item--note">
                                <?php echo esc_html( self::lag_note( (int) $c['lag'] ) ); ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        <?php echo $svg; // phpcs:ignore WordPress.Security.EscapeOutput -- SVG wird in line_svg() vollständig escaped ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
}
