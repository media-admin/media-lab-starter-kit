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
        $data = $this->collect_data();
        $html = MLT_Report_Template::build( $data );
        /**
         * Filter: Report-HTML vor dem Versand anpassen.
         *
         * @param string   $html  Fertiges HTML
         * @param array    $data  Rohdaten (gsc_overview, gsc_queries, gsc_pages, analytics, analytics_sources)
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
        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];
        $sent = wp_mail( $to, $subject, $html, $headers );
        // Versandzeitpunkt und Status speichern
        update_option( 'mlt_last_report_sent', current_time( 'mysql' ) );
        update_option( 'mlt_last_report_status', $sent ? 'success' : 'failed' );
        return $sent;
    }
    private function collect_data() : array {
        $data = [
            'range'             => [],
            'gsc_overview'      => [],
            'gsc_queries'       => [],
            'gsc_pages'         => [],
            'analytics'         => [],
            'analytics_sources' => [],
        ];

        // Einheitlicher Zeitraum für GSC UND Analytics, aus den Einstellungen
        [ 'start' => $start, 'end' => $end ] = MLT_GSC_API::get_active_range();
        $data['range'] = [ 'start' => $start, 'end' => $end ];

        // GSC-Daten
        $gsc = MLT_GSC_API::instance();
        if ( $gsc->is_connected() && $gsc->is_configured() ) {
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

        return $data;
    }
}
