<?php
/**
 * Uninstall-Skript für Media Lab Agency Core.
 * Wird ausgeführt wenn das Plugin aus WordPress gelöscht wird.
 *
 * WICHTIGER HINWEIS ZUM UMFANG (21.08.2026):
 * agency-core hat mit Abstand den größten Options-Fußabdruck aller
 * Starter-Kit-Plugins (~15+ Dateien: Heartbeat, SMTP/OAuth für MS365 +
 * Google Workspace, Security-Scanner, Cookie-Consent, Activity-Log,
 * ~10 ACF-Options-Pages). Statt jede einzelne Option händisch
 * aufzulisten (hohes Risiko, welche zu übersehen), wird hier bewusst
 * pragmatisch per Wildcard über die bekannten Options-Präfixe gelöscht:
 *   - 'medialab_%'          – direkte Optionen (Heartbeat-Token,
 *                             SMTP-Mode, MS365/GWS-OAuth-Tokens, DB-
 *                             Versions-Flags, etc.)
 *   - 'options_medialab_%'  – ACF-Options-Page-Felder (ACF speichert
 *                             diese mit 'options_'-Präfix, siehe
 *                             docs/06_DEVELOPMENT.md-Learnings)
 *   - 'mla_%'               – Security-Scanner (eigener Präfix,
 *                             abweichend vom Rest des Plugins:
 *                             mla_security_scan_results,
 *                             mla_security_scan_whitelist,
 *                             mla_security_notify_email)
 *
 * BEWUSSTE AUSNAHME (DSGVO): Die Consent-Log-Tabelle wp_mlt_consent_log
 * (von agency-core angelegt, aber mit 'mlt_'-Präfix — wird auch vom
 * SEO Toolkit lesend genutzt, siehe docs/03_PLUGINS.md) wird NICHT
 * gelöscht. Sie ist der Nachweis, wer wann welchem Cookie-Consent
 * zugestimmt hat. Ein Plugin-Uninstall soll diesen Nachweis nicht
 * automatisch vernichten. Siehe auskommentierter Opt-in-Block unten,
 * falls doch ausdrücklich gewünscht.
 *
 * Activity-Log-Tabelle (Backend-Audit-Trail, wp_medialab_activity_log)
 * WIRD gelöscht — nicht als vergleichbar schützenswert eingestuft wie
 * der DSGVO-Consent-Nachweis. Bei Bedarf gerne Rückmeldung, falls das
 * ebenfalls bewahrt werden soll.
 *
 * NICHT durchsucht (zu großer Umfang für diese Session, siehe
 * docs/BACKLOG.md): eine vollständige Einzel-Optionen-Liste aller
 * ACF-Options-Page-Felder. Der Wildcard-Ansatz deckt sie ab, solange
 * ihre Feldnamen konsistent mit 'medialab_' beginnen (bei allen bisher
 * gesichteten Feldern der Fall).
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// ── Optionen: Wildcard über bekannte Präfixe ──────────────────────────────────
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'medialab_%'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'options_medialab_%'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'mla_%'" );

// ── Geplante Cron-Jobs ─────────────────────────────────────────────────────
wp_clear_scheduled_hook( 'mla_security_weekly_scan' ); // Security-Scanner, wöchentlich

// ── Custom-Tabellen ──────────────────────────────────────────────────────────

// Activity-Log (Backend-Audit-Trail) — wird gelöscht.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}medialab_activity_log" );

// Consent-Log (DSGVO-Nachweis) — BEWUSST NICHT gelöscht, siehe Hinweis oben.
// $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}mlt_consent_log" );

/*
// Nur bei explizitem Wunsch aktivieren: löscht auch den DSGVO-Consent-Log.
// Vorsicht: vernichtet unwiderruflich den Nachweis, wer wann welchem
// Cookie-Consent zugestimmt hat.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}mlt_consent_log" );
*/
