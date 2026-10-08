<?php
/**
 * MLT_Report_Mailer
 *
 * Wöchentlicher SEO-Report-Versand.
 * Holt Daten aus GSC API + Analytics Adapter, baut das HTML-Template
 * und sendet via wp_mail() (SMTP über Agency Core).
 *
 * Cron-Hook: mlt_weekly_report (registriert im eigenen Konstruktor - der
 * Zeitplan selbst, also wp_schedule_event()/wp_next_scheduled(), wird in
 * class-settings.php verwaltet, der eigentliche Mail-Versand aber
 * ausschließlich hier. Vorher stand hier fälschlich "registriert in
 * class-settings.php" - Überbleibsel aus einer früheren Version, in der
 * ein inzwischen entfernter Legacy-Handler dort einen zweiten Hook auf
 * denselben Cron-Event registriert hatte. Das führte projektweit zu
 * doppelt versendeten Wochenreports (zwei unterschiedliche Mail-Templates
 * zur selben Minute), bis der Legacy-Handler entfernt wurde. Kommentar
 * jetzt korrigiert, damit der veraltete Verweis nicht wieder verwirrt.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
class MLT_Report_Mailer {
    /** Obergrenze für das Report-HTML in Bytes (Gmail kürzt ab ca. 102 KB). */
    const MAX_HTML_BYTES = 80000;
    public function __construct() {
        // Cron-Hook übernehmen
        add_action( 'mlt_weekly_report', [ $this, 'send' ] );
    }
    public function send() {
        // Mehrere Empfänger aus dynamischer Liste holen (inc/report-recipients.php)
        $to = mlt_get_report_recipients();
        // Fallback: Admin-E-Mail wenn noch keine Empfänger konfiguriert
        if ( empty( $to ) ) {
            $admin = get_option( 'admin_email' );
            if ( ! is_email( $admin ) ) return;
            $to = [ $admin ];
        }
        $report  = self::render( $to );
        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];
        $sent = wp_mail( $to, $report['subject'], $report['html'], $headers );
        // Versandzeitpunkt und Status speichern
        update_option( 'mlt_last_report_sent', current_time( 'mysql' ) );
        update_option( 'mlt_last_report_status', $sent ? 'success' : 'failed' );
        return $sent;
    }

    /**
     * Sendet den ECHTEN Report (mit aktuellen Zahlen) als Test an genau eine Adresse.
     * Wird vom Button „Test-Report senden" in den Einstellungen verwendet. Ändert weder
     * `mlt_last_report_sent` noch `mlt_last_report_status`.
     *
     * @return array{0:bool,1:string} [ Erfolg, Meldung für die Anzeige ]
     */
    public static function send_test( string $to ) : array {
        if ( ! is_email( $to ) ) return [ false, 'Ungültige E-Mail-Adresse.' ];

        $report = self::render( [ $to ] );

        $error = null;
        $catch = static function ( $e ) use ( &$error ) {
            $error = $e->get_error_message();
        };
        add_action( 'wp_mail_failed', $catch );
        $sent = wp_mail( $to, '[TEST] ' . $report['subject'], $report['html'], [ 'Content-Type: text/html; charset=UTF-8' ] );
        remove_action( 'wp_mail_failed', $catch );

        if ( ! $sent ) return [ false, $error ?: 'Unbekannter Fehler beim Senden.' ];

        $msg = 'Test-Report gesendet an ' . $to . '.';
        $d   = $report['data'];
        if ( empty( $d['gsc_overview'] ) && empty( $d['analytics'] ) ) {
            $msg .= ' Hinweis: Es lagen keine Daten vor (weder Search Console noch Analytics verbunden) – der Report enthält nur den Rahmen.';
        }
        return [ true, $msg ];
    }

    /**
     * Baut Report-HTML und Betreff (gemeinsam für Cron-Versand und Test-Report).
     *
     * @param  string[] $to Empfänger (für den Filter)
     * @return array{html:string,subject:string,data:array}
     */
    private static function render( array $to ) : array {
        $data = self::collect_data();
        $html = MLT_Report_Template::build( $data );

        // Gmail kürzt Mails ab ca. 102 KB („Nachricht abgeschnitten"). Die Grafiken sind nur Zugabe:
        // Wird die Mail zu groß (z. B. sehr lange Keywords/URLs), erscheint sie ohne Säulendiagramme.
        /**
         * Filter: Größenlimit (Bytes) für das Report-HTML, ab dem die Säulendiagramme entfallen.
         *
         * @param int $max Standard 80000
         */
        $max_bytes = (int) apply_filters( 'mlt_report_max_html_bytes', self::MAX_HTML_BYTES );
        if ( strlen( $html ) > $max_bytes && ! empty( $data['charts'] ) ) {
            $data['charts'] = [];
            $html           = MLT_Report_Template::build( $data );
        }
        /**
         * Filter: Report-HTML vor dem Versand anpassen.
         *
         * @param string   $html  Fertiges HTML
         * @param array    $data  Rohdaten (gsc_overview, gsc_queries, gsc_pages, analytics, analytics_sources, compare, charts)
         * @param string[] $to    Empfänger-Array
         */
        $html = apply_filters( 'mlt_weekly_report_html', $html, $data, $to );
        $week    = wp_date( 'W' );
        $year    = wp_date( 'Y' );
        $subject = sprintf( '[%s] SEO Report KW %s/%s', get_bloginfo( 'name' ), $week, $year );
        /**
         * Filter: Subject anpassen.
         */
        $subject = apply_filters( 'mlt_weekly_report_subject', $subject, $week, $year );
        return [ 'html' => $html, 'subject' => $subject, 'data' => $data ];
    }
    private static function collect_data() : array {
        $data = [
            'range'             => [],
            'gsc_overview'      => [],
            'gsc_queries'       => [],
            'gsc_pages'         => [],
            'analytics'         => [],
            'analytics_sources' => [],
            'compare'           => [],
            'charts'            => [],
        ];

        // Einheitlicher Zeitraum für GSC UND Analytics, aus den Einstellungen
        [ 'start' => $start, 'end' => $end ] = MLT_GSC_API::get_active_range();
        $data['range'] = [ 'start' => $start, 'end' => $end ];

        // GSC-Daten
        $gsc     = MLT_GSC_API::instance();
        $has_gsc = $gsc->is_connected() && $gsc->is_configured();
        if ( $has_gsc ) {
            $data['gsc_overview'] = $gsc->get_overview( $start, $end );
            $data['gsc_queries']  = $gsc->get_top_queries( 10, $start, $end );
            $data['gsc_pages']    = $gsc->get_top_pages( 10, $start, $end );
        }

        // Analytics-Daten
        $adapter = MLT_Analytics_Adapter_Factory::get();
        if ( $adapter ) {
            $data['analytics']         = $adapter->get_overview( $start, $end );
            $data['analytics_sources'] = $adapter->get_sources( $start, $end, 5 );
        }

        // Vergleichszeitraum + Deltas (Einstellung mlt_compare_mode)
        $data['compare'] = MLT_Compare::fetch( $data['range'], $data['gsc_overview'], $data['analytics'], $has_gsc, $adapter, $data['gsc_queries'], $data['gsc_pages'] );

        // Tageswerte für die Säulendiagramme in der Mail (gleiche Quelle wie der Dashboard-Chart)
        $data['charts'] = MLT_Timeseries::collect( $data['range'], $data['compare']['range'] ?? null, $has_gsc, $adapter );

        return $data;
    }
}
