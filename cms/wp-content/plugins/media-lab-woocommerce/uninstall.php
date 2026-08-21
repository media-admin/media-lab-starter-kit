<?php
/**
 * Uninstall-Skript für Media Lab WooCommerce.
 * Wird ausgeführt wenn das Plugin aus WordPress gelöscht wird.
 *
 * Verifizierter Fußabdruck (Stand 21.08.2026, aus inc/inquiry/class-settings.php
 * und inc/wishlist/class-storage.php):
 *   - Optionen: eine ACF-Options-Page ('mlw-inquiry-settings'), alle Felder
 *     konsequent 'mlw_'-präfixt (Wording, Sprachen-Repeater, Formularfelder-
 *     Repeater, Kanäle E-Mail/WhatsApp/Webhook, Mail-Templates, Navigation).
 *     ACF speichert Options-Page-Felder zusätzlich mit 'options_'-Präfix
 *     (siehe docs/06_DEVELOPMENT.md-Learnings, gleiches Muster wie bei
 *     agency-core) — Wildcard deckt beide Formen ab.
 *   - Keine eigenen Custom-Tabellen (Wunschliste läuft über WC()->session
 *     für Gäste + User-Meta für eingeloggte Kunden, kein dbDelta()/CREATE
 *     TABLE im Plugin gefunden).
 *   - User-Meta: mlw_wishlist_items (persistente Wunschliste),
 *     mlw_wishlist_last_contact (vorausgefüllte Kontaktdaten fürs
 *     Anfrage-Formular) — beides Plugin-eigener State, kein Business-
 *     Content im engeren Sinn, daher hier gelöscht.
 *   - CPT: mlw_inquiry (tatsächliche Kundenanfragen aus Cart-Anfrage,
 *     Konfigurator-Anfrage und Wunschliste) — siehe Hinweis unten.
 *   - Keine Cron-Jobs gefunden.
 *
 * Bewusste Entscheidung: mlw_inquiry-Beiträge (eingegangene Kundenanfragen)
 * werden hier NICHT gelöscht. Das sind reguläre Geschäftsdaten (Anfragen
 * echter Kunden), kein Plugin-eigener "Datenmüll" — ein Uninstall soll
 * nicht ohne explizite Rückfrage echte Kundenkontakte vernichten.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// ── Optionen: Wildcard über bekannte Präfixe ──────────────────────────────────
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'mlw_%'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'options_mlw_%'" );

// ── User-Meta (Wunschliste + vorausgefüllte Kontaktdaten) ────────────────────
delete_metadata( 'user', 0, 'mlw_wishlist_items', '', true );
delete_metadata( 'user', 0, 'mlw_wishlist_last_contact', '', true );

/*
// Nur bei explizitem Wunsch aktivieren: löscht ALLE eingegangenen
// Kundenanfragen unwiderruflich (auch aus dem Papierkorb).
$inquiries = get_posts( [
    'post_type'      => 'mlw_inquiry',
    'posts_per_page' => -1,
    'post_status'    => 'any',
    'fields'         => 'ids',
] );
foreach ( $inquiries as $inquiry_id ) {
    wp_delete_post( $inquiry_id, true );
}
*/
