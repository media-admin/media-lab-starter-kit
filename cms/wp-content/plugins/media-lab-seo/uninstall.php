<?php
/**
 * Uninstall-Skript für Media Lab SEO Toolkit.
 * Wird ausgeführt wenn das Plugin aus WordPress gelöscht wird.
 *
 * Verifizierter Fußabdruck (Stand 21.08.2026):
 *   - Optionen: durchgängig 'mlt_'-Präfix, MIT AUSNAHME der drei
 *     Report-Zeitplan-Optionen aus inc/report-schedule.php
 *     (medialab_seo_report_weekday/_time/_timezone — abweichender
 *     Präfix, historisch gewachsen). Wildcard deckt beide Präfixe ab.
 *   - Custom-Tabellen: {prefix}mlt_redirects, {prefix}mlt_404_log
 *     (eigene drop_tables()-Methode existiert bereits in
 *     inc/class-redirects.php — hier bewusst dupliziert statt die Klasse
 *     zu laden, da uninstall.php so schlank wie möglich bleiben soll)
 *   - Transients: mlt_gsc_overview, mlt_gsc_queries, mlt_gsc_pages,
 *     mlt_ga4_sa_access_token
 *   - Cron: zwei Hook-Namen im Code gefunden (mlt_weekly_report in
 *     class-report-mailer.php, mlt_send_weekly_report als Konstante in
 *     report-schedule.php) — möglicherweise eine bestehende
 *     Inkonsistenz im Plugin selbst (nicht Teil dieser Aufgabe, hier nur
 *     vorsichtshalber beide geräumt)
 *
 * Redirects/404-Log sind reine Operational-/Analytics-Daten (URL-Regeln,
 * Fehlerseiten-Statistik), keine personenbezogenen DSGVO-Nachweise wie
 * bei agency-core's Consent-Log — daher hier ohne Sonderbehandlung
 * gelöscht.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// ── Optionen: Wildcard über bekannte Präfixe ──────────────────────────────────
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'mlt_%'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'medialab_seo_%'" );

// ── Transients (nicht vom Wildcard oben erfasst, da eigenes Präfix-Schema) ───
delete_transient( 'mlt_gsc_overview' );
delete_transient( 'mlt_gsc_queries' );
delete_transient( 'mlt_gsc_pages' );
delete_transient( 'mlt_ga4_sa_access_token' );

// ── Geplante Cron-Jobs ─────────────────────────────────────────────────────
wp_clear_scheduled_hook( 'mlt_weekly_report' );
wp_clear_scheduled_hook( 'mlt_send_weekly_report' );

// ── Custom-Tabellen ──────────────────────────────────────────────────────────
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}mlt_redirects" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}mlt_404_log" );
