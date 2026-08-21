<?php
/**
 * Uninstall-Skript für Media Lab Project Starter.
 * Wird ausgeführt wenn das Plugin aus WordPress gelöscht wird.
 *
 * HINWEIS: Dieses Plugin hat keine eigenen Optionen, Tabellen oder
 * Cron-Jobs (verifiziert gegen inc/custom-post-types.php,
 * inc/taxonomies.php, inc/acf-config.php — Stand 21.08.2026). ACF-
 * Feldgruppen werden aus JSON-Dateien geladen (Local JSON), erzeugen
 * dadurch keine eigenen wp_options-Einträge für die Feldgruppen-
 * Definitionen selbst — nur tatsächliche Feldwerte landen als Postmeta
 * an den jeweiligen Beiträgen und werden bei deren Löschung automatisch
 * mit entfernt.
 *
 * Bewusste Entscheidung: Team-/Projekt-/Job-/Service-/Testimonial-/
 * FAQ-/Hero-Slide-/Carousel-/Maps-Beiträge werden hier NICHT gelöscht.
 * Das sind reguläre Inhaltsdaten (Business-Content), kein Plugin-eigener
 * "Datenmüll" — ein Uninstall soll nicht ohne explizite Rückfrage
 * echte Inhalte vernichten.
 *
 * ZUSÄTZLICHER HINWEIS: Dieses Plugin ist laut README ein Scaffold, das
 * pro Kundenprojekt dupliziert und individuell angepasst wird ("nicht
 * das Original-Scaffold direkt für ein Kundenprojekt verwenden"). Dieses
 * uninstall.php ist daher primär für das Scaffold selbst gedacht — bei
 * duplizierten/umbenannten Projekt-Plugins mit projektspezifischen CPTs
 * entsprechend anpassen.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Absichtlich keine Aktion nötig — siehe Hinweis oben.

/*
// Nur bei explizitem Wunsch aktivieren: löscht ALLE Beiträge der
// Scaffold-CPTs unwiderruflich (auch aus dem Papierkorb).
$post_types = [ 'team', 'project', 'job', 'service', 'testimonial', 'faq', 'hero_slide', 'carousel', 'gmap' ];
foreach ( $post_types as $post_type ) {
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
