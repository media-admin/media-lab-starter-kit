<?php
/**
 * Uninstall-Skript für Media Lab Bookings.
 * Wird ausgeführt wenn das Plugin aus WordPress gelöscht wird.
 *
 * Verifizierter Fußabdruck (Stand 21.08.2026):
 *   - Optionen: mlb_feed_token, mlb_feed_protected (inc/feed.php)
 *   - Cron: mlb_send_reminder — Single Events pro Buchung (inc/notifications.php),
 *     kein wiederkehrender Schedule. wp_unschedule_hook() entfernt ALLE
 *     geplanten Events für diesen Hook unabhängig von den jeweiligen
 *     Buchungs-ID-Argumenten (einzelnes wp_clear_scheduled_hook() pro
 *     Buchung wäre hier unpraktikabel, da wir zur Uninstall-Zeit die
 *     Liste der noch offenen Erinnerungen nicht kennen).
 *   - Keine eigenen Custom-Tabellen (keine dbDelta()/CREATE TABLE gefunden).
 *   - CPTs: mlb_booking, mlb_location — siehe Hinweis unten.
 *
 * Bewusste Entscheidung: Buchungen und Standorte werden hier NICHT
 * gelöscht. Das sind reguläre Business-Daten (abgeschlossene/laufende
 * Kundenbuchungen), kein Plugin-eigener "Datenmüll" — ein Uninstall soll
 * nicht ohne explizite Rückfrage echte Geschäftsdaten vernichten.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// ── Optionen ─────────────────────────────────────────────────────────────────
delete_option( 'mlb_feed_token' );
delete_option( 'mlb_feed_protected' );

// ── Geplante Erinnerungs-Mails entfernen ──────────────────────────────────────
if ( function_exists( 'wp_unschedule_hook' ) ) {
    wp_unschedule_hook( 'mlb_send_reminder' );
}

/*
// Nur bei explizitem Wunsch aktivieren: löscht ALLE Buchungen und
// Standorte unwiderruflich (auch aus dem Papierkorb).
foreach ( [ 'mlb_booking', 'mlb_location' ] as $post_type ) {
    $posts = get_posts( [
        'post_type'      => $post_type,
        'posts_per_page' => -1,
        'post_status'    => 'any',
        'fields'         => 'ids',
    ] );
    foreach ( $posts as $post_id ) {
        wp_delete_post( $post_id, true );
    }
}
*/
