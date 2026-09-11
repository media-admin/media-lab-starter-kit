<?php
defined( 'ABSPATH' ) || exit;

/**
 * MLBKP_Restore_Session
 *
 * Verwaltet den State einer laufenden Wiederherstellung in wp_options,
 * analog zu MLBKP_Session für Backups. Eine Restore-Session besteht aus
 * einer Liste von Items (DB komplett/selektiv, Chunk komplett/selektiv),
 * die nacheinander per WP-Cron abgearbeitet werden.
 */
class MLBKP_Restore_Session {

    const OPTION_PREFIX = 'mlbkp_restore_';
    const INDEX_OPTION  = 'mlbkp_restore_index';

    public static function create( string $source_session_dir, array $items ): array {
        $id = 'restore_' . uniqid( '', true );

        $session = [
            'id'                 => $id,
            'source_session_dir' => $source_session_dir,
            'status'             => 'running', // running | success | error | cancelled
            'started_at'         => current_time( 'mysql' ),
            'finished_at'        => null,
            'error_message'      => '',
            'safety_dir'         => WP_CONTENT_DIR . '/mlbkp-restore-safety/' . $id,
            'items'              => array_values( $items ),
            'items_done'         => 0,
        ];

        self::save( $session );
        self::add_to_index( $id );

        return $session;
    }

    public static function load( string $id ): ?array {
        $data = get_option( self::OPTION_PREFIX . $id, null );
        return $data ?: null;
    }

    public static function save( array $session ): void {
        update_option( self::OPTION_PREFIX . $session['id'], $session, false );
    }

    public static function delete( string $id ): void {
        delete_option( self::OPTION_PREFIX . $id );
        self::remove_from_index( $id );
    }

    public static function get_next_item( array $session ): ?array {
        foreach ( $session['items'] as $item ) {
            if ( $item['status'] === 'pending' || $item['status'] === 'running' ) return $item;
        }
        return null;
    }

    public static function update_item( array &$session, int $item_id, array $data ): void {
        foreach ( $session['items'] as &$item ) {
            if ( $item['id'] === $item_id ) {
                $item = array_merge( $item, $data );
                break;
            }
        }
        unset( $item );

        $session['items_done'] = count( array_filter(
            $session['items'],
            static fn( $i ) => in_array( $i['status'], [ 'done', 'error', 'skipped' ], true )
        ) );
    }

    public static function finish( array &$session, string $status, string $error = '' ): void {
        $session['status']      = $status;
        $session['finished_at'] = current_time( 'mysql' );
        if ( $error ) $session['error_message'] = $error;
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public static function get_index(): array {
        return get_option( self::INDEX_OPTION, [] );
    }

    private static function add_to_index( string $id ): void {
        $index   = self::get_index();
        $index[] = $id;
        update_option( self::INDEX_OPTION, $index, false );
    }

    private static function remove_from_index( string $id ): void {
        $index = array_values( array_filter( self::get_index(), static fn( $i ) => $i !== $id ) );
        update_option( self::INDEX_OPTION, $index, false );
    }

    public static function cleanup_old_sessions( int $keep = 10 ): void {
        $index = self::get_index();
        if ( count( $index ) <= $keep ) return;

        $to_delete = array_slice( $index, 0, count( $index ) - $keep );
        foreach ( $to_delete as $id ) {
            self::delete( $id );
        }
    }

    /**
     * Baut ein einzelnes Item für die Item-Liste einer Restore-Session.
     * $extra für typspezifische Zusatzfelder:
     *   db_full/db_tables:     remote_path, [tables[]]
     *   chunk_full/chunk_files: remote_path, target_path, is_archive, [files[]]
     */
    public static function make_item( int $id, string $type, string $label, array $extra = [] ): array {
        return array_merge( [
            'id'     => $id,
            'type'   => $type, // db_full | db_tables | chunk_full | chunk_files
            'label'  => $label,
            'status' => 'pending', // pending | running | done | error | skipped
            'error'  => null,
            'stats'  => [],
        ], $extra );
    }
}
