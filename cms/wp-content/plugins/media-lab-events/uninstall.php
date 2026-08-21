<?php
/**
 * Uninstall-Skript für Media Lab Events.
 * Wird ausgeführt wenn das Plugin aus WordPress gelöscht wird.
 *
 * HINWEIS: Dieses Plugin hat keine eigenen Optionen, Tabellen oder
 * Cron-Jobs (verifiziert gegen inc/cpt.php, inc/acf.php,
 * inc/shortcodes.php — Stand 21.08.2026). Der einzige State ist der
 * CPT "event" + Taxonomie "event_category".
 *
 * Bewusste Entscheidung: Event-Beiträge werden hier NICHT gelöscht.
 * Das sind reguläre Inhaltsdaten (Business-Content), kein Plugin-eigener
 * "Datenmüll" — ein Uninstall soll nicht ohne explizite Rückfrage
 * echte Inhalte vernichten. Falls doch gewünscht, siehe auskommentierten
 * Block unten.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Absichtlich keine Aktion nötig — siehe Hinweis oben.

/*
// Nur bei explizitem Wunsch aktivieren: löscht ALLE Event-Beiträge
// unwiderruflich (auch aus dem Papierkorb).
$events = get_posts( [
    'post_type'      => 'event',
    'posts_per_page' => -1,
    'post_status'    => 'any',
    'fields'         => 'ids',
] );
foreach ( $events as $event_id ) {
    wp_delete_post( $event_id, true );
}
*/
