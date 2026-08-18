<?php
defined( 'ABSPATH' ) || exit;

/**
 * MLBKP_Logger
 *
 * Erstellt und verwaltet die Backup-Log-Tabelle ({prefix}mlb_logs).
 */
class MLBKP_Logger {

    const TABLE_SUFFIX = 'mlb_logs';
    const OPTION_DB_VERSION = 'mlbkp_db_version';
    const DB_VERSION = '1.1';

    // Job-Timeout in Minuten — Jobs die länger laufen werden als Fehler markiert.
    // 240 min (4 h) — großzügig gewählt, da File-Backups auf Shared Hosting bei
    // großen wp-content-Verzeichnissen (1–3 GB) deutlich länger als 60 min dauern können.
    const JOB_TIMEOUT_MINUTES = 240;

    // ── Tabellen-Management ──────────────────────────────────────────────────

    public static function get_table(): string {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_SUFFIX;
    }

    public static function create_table(): void {
        global $wpdb;
        $table   = self::get_table();
        $collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            started_at    DATETIME            NOT NULL,
            finished_at   DATETIME                     DEFAULT NULL,
            status        VARCHAR(20)         NOT NULL DEFAULT 'running'  COMMENT 'running | success | error',
            backup_type   VARCHAR(50)         NOT NULL                    COMMENT 'database | files | full',
            file_name     VARCHAR(255)                 DEFAULT NULL,
            file_size     BIGINT(20) UNSIGNED          DEFAULT 0,
            remote_path   VARCHAR(500)                 DEFAULT NULL,
            duration_sec  INT(10) UNSIGNED             DEFAULT NULL,
            error_message TEXT                         DEFAULT NULL,
            triggered_by  VARCHAR(30)                  DEFAULT 'manual'  COMMENT 'manual | cron',
            PRIMARY KEY   (id)
        ) {$collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        update_option( self::OPTION_DB_VERSION, self::DB_VERSION );
    }

    public static function drop_table(): void {
        global $wpdb;
        $wpdb->query( 'DROP TABLE IF EXISTS ' . self::get_table() );
        delete_option( self::OPTION_DB_VERSION );
    }

    // ── Log-Einträge ─────────────────────────────────────────────────────────

    /**
     * Startet einen neuen Log-Eintrag und gibt die ID zurück.
     */
    public static function start( string $backup_type, string $triggered_by = 'manual' ): int {
        global $wpdb;
        $wpdb->insert( self::get_table(), [
            'started_at'   => current_time( 'mysql' ),
            'status'       => 'running',
            'backup_type'  => $backup_type,
            'triggered_by' => $triggered_by,
        ] );
        return (int) $wpdb->insert_id;
    }

    /**
     * Schließt einen Log-Eintrag ab.
     *
     * @param int    $id
     * @param string $status   'success' | 'error'
     * @param array  $data     Optionale Zusatzdaten (file_name, file_size, remote_path, error_message)
     */
    public static function finish( int $id, string $status, array $data = [] ): void {
        global $wpdb;

        $started = $wpdb->get_var(
            $wpdb->prepare( 'SELECT started_at FROM ' . self::get_table() . ' WHERE id = %d', $id )
        );

        $duration = $started
            ? ( current_time( 'timestamp' ) - strtotime( $started ) )
            : null;

        $wpdb->update(
            self::get_table(),
            array_merge( [
                'finished_at'  => current_time( 'mysql' ),
                'status'       => $status,
                'duration_sec' => $duration,
            ], $data ),
            [ 'id' => $id ]
        );
    }

    // ── Abbruch-Flag ─────────────────────────────────────────────────────────

    public static function set_cancel_flag( int $id ): void {
        update_option( "mlbkp_cancel_{$id}", '1', false );
    }

    public static function is_cancelled( int $id ): bool {
        return (bool) get_option( "mlbkp_cancel_{$id}", false );
    }

    public static function clear_cancel_flag( int $id ): void {
        delete_option( "mlbkp_cancel_{$id}" );
    }

    // ── Timeout-Cleanup ───────────────────────────────────────────────────────

    /**
     * Markiert alle Jobs als Fehler, die länger als JOB_TIMEOUT_MINUTES laufen.
     * Wird stündlich via WP-Cron aufgerufen.
     */
    public static function cleanup_timed_out_jobs(): int {
        global $wpdb;
        $table   = self::get_table();
        $timeout = self::JOB_TIMEOUT_MINUTES;

        $affected = $wpdb->query(
            "UPDATE {$table}
             SET status        = 'error',
                 finished_at   = NOW(),
                 error_message = 'Job-Timeout: Prozess nach {$timeout} Minuten automatisch beendet.'
             WHERE status = 'running'
               AND started_at < DATE_SUB(NOW(), INTERVAL {$timeout} MINUTE)"
        );

        return (int) $affected;
    }

    /**
     * Prüft ob ein einzelner Job das Timeout überschritten hat.
     */
    public static function is_timed_out( int $id ): bool {
        global $wpdb;
        $started = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT started_at FROM ' . self::get_table() . ' WHERE id = %d AND status = %s',
                $id, 'running'
            )
        );

        if ( ! $started ) return false;

        $running_minutes = ( time() - strtotime( $started ) ) / 60;
        return $running_minutes > self::JOB_TIMEOUT_MINUTES;
    }

    // ── Abfragen ─────────────────────────────────────────────────────────────

    public static function get_logs( int $limit = 50 ): array {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . self::get_table() . ' ORDER BY started_at DESC LIMIT %d',
                $limit
            ),
            ARRAY_A
        ) ?: [];
    }

    public static function get_last_successful(): ?array {
        global $wpdb;
        $row = $wpdb->get_row(
            "SELECT * FROM " . self::get_table() . " WHERE status = 'success' ORDER BY finished_at DESC LIMIT 1",
            ARRAY_A
        );
        return $row ?: null;
    }

    /**
     * Hält die Tabelle schlank — behält nur die letzten $keep Einträge.
     */
    public static function prune( int $keep = 200 ): void {
        global $wpdb;
        $table = self::get_table();
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE id NOT IN (
                    SELECT id FROM (
                        SELECT id FROM {$table} ORDER BY id DESC LIMIT %d
                    ) AS tmp
                )",
                $keep
            )
        );
    }

    // ── Hilfsfunktionen ──────────────────────────────────────────────────────

    public static function format_bytes( int $bytes ): string {
        if ( $bytes >= 1073741824 ) return round( $bytes / 1073741824, 2 ) . ' GB';
        if ( $bytes >= 1048576 )    return round( $bytes / 1048576,    2 ) . ' MB';
        if ( $bytes >= 1024 )       return round( $bytes / 1024,       2 ) . ' KB';
        return $bytes . ' B';
    }

    public static function format_duration( ?int $seconds ): string {
        if ( $seconds === null ) return '—';
        if ( $seconds < 60 )    return "{$seconds}s";
        $m = intdiv( $seconds, 60 );
        $s = $seconds % 60;
        return "{$m}m {$s}s";
    }
}
