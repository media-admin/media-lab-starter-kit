<?php
/**
 * Räumt verwaiste Datei-Uploads auf (Konfigurator-Datei-Upload-Step).
 *
 * WICHTIG: Es werden AUSSCHLIESSLICH Attachments gelöscht, die explizit mit
 * dem Meta-Feld '_mlw_pending_upload' markiert wurden (siehe class-configurator.php,
 * ajax_upload_configurator_file()). Alle anderen Medienbibliotheks-Einträge –
 * auch wenn sie keinen post_parent haben – werden NIE angefasst.
 *
 * Sobald ein Upload final einer Anfrage zugeordnet wird, entfernt die
 * Inquiry_Engine den Marker '_mlw_pending_upload' (siehe class-inquiry-engine.php) –
 * ab dem Zeitpunkt ist das Attachment für diesen Cron nicht mehr sichtbar.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MediaLab_Inquiry_Upload_Cleanup {

    const CRON_HOOK   = 'mlw_cleanup_pending_uploads';
    const MAX_AGE_DAYS = 30;

    public static function init(): void {
        add_action( 'init', [ __CLASS__, 'schedule' ] );
        add_action( self::CRON_HOOK, [ __CLASS__, 'run' ] );
    }

    public static function schedule(): void {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time(), 'daily', self::CRON_HOOK );
        }
    }

    public static function run(): void {
        $cutoff = time() - ( self::MAX_AGE_DAYS * DAY_IN_SECONDS );

        $query = new WP_Query( [
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => 200, // in Batches, um bei sehr vielen Uploads keine Timeouts zu riskieren
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'     => '_mlw_pending_upload',
                    'value'   => '1',
                    'compare' => '=',
                ],
                [
                    'key'     => '_mlw_upload_timestamp',
                    'value'   => $cutoff,
                    'compare' => '<',
                    'type'    => 'NUMERIC',
                ],
            ],
        ] );

        foreach ( $query->posts as $attachment_id ) {
            // Sicherheitsnetz: falls das Attachment inzwischen doch einer Anfrage
            // zugeordnet wurde, aber der Marker aus irgendeinem Grund noch da ist – nicht löschen.
            if ( get_post_meta( $attachment_id, '_mlw_inquiry_id', true ) ) {
                delete_post_meta( $attachment_id, '_mlw_pending_upload' );
                continue;
            }
            wp_delete_attachment( $attachment_id, true );
        }
    }
}

MediaLab_Inquiry_Upload_Cleanup::init();
