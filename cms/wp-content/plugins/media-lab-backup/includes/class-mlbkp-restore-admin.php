<?php
defined( 'ABSPATH' ) || exit;

/**
 * MLBKP_Restore_Admin
 *
 * Admin-UI + AJAX-Endpunkte für das Restore-Feature: Sessions durchsuchen,
 * Chunks/Dateien/DB-Tabellen selektiv auswählen, Wiederherstellung starten
 * und deren Fortschritt pollen.
 */
class MLBKP_Restore_Admin {

    const MENU_SLUG    = 'media-lab-backup-restore';
    const NONCE_ACTION = MLBKP_Admin::NONCE_ACTION;

    public static function init(): void {
        // Muss UNABHÄNGIG von is_admin() registriert werden: WP-Cron-Requests
        // (wp-cron.php) laufen nicht im Admin-Kontext — sonst würde der
        // Restore-Job nie verarbeitet (gleiches Prinzip wie
        // 'mlbkp_process_chunk' in MLBKP_Scheduler::init()).
        add_action( MLBKP_Restore_Runner::CRON_HOOK, [ 'MLBKP_Restore_Runner', 'process' ] );

        if ( ! is_admin() ) return;

        add_action( 'admin_menu', [ self::class, 'register_menu' ] );
        add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_assets' ] );

        add_action( 'wp_ajax_mlbkp_restore_list_sessions',    [ self::class, 'ajax_list_sessions' ] );
        add_action( 'wp_ajax_mlbkp_restore_load_session',     [ self::class, 'ajax_load_session' ] );
        add_action( 'wp_ajax_mlbkp_restore_list_chunk_files', [ self::class, 'ajax_list_chunk_files' ] );
        add_action( 'wp_ajax_mlbkp_restore_list_db_tables',   [ self::class, 'ajax_list_db_tables' ] );
        add_action( 'wp_ajax_mlbkp_restore_start',            [ self::class, 'ajax_start' ] );
        add_action( 'wp_ajax_mlbkp_restore_check_status',     [ self::class, 'ajax_check_status' ] );
        add_action( 'wp_ajax_mlbkp_restore_cancel',           [ self::class, 'ajax_cancel' ] );
    }

    public static function register_menu(): void {
        add_submenu_page(
            MLBKP_Admin::MENU_SLUG,
            'Wiederherstellen',
            '⏪ Wiederherstellen',
            'manage_options',
            self::MENU_SLUG,
            [ self::class, 'render_page' ]
        );
    }

    public static function enqueue_assets( string $hook ): void {
        if ( ! str_contains( $hook, self::MENU_SLUG ) ) return;

        wp_enqueue_style(
            'mlbkp-admin-restore',
            MLBKP_PLUGIN_URL . 'admin/css/admin-restore.css',
            [],
            MLBKP_VERSION
        );

        wp_enqueue_script(
            'mlbkp-admin-restore',
            MLBKP_PLUGIN_URL . 'admin/js/admin-restore.js',
            [ 'jquery' ],
            MLBKP_VERSION,
            true
        );

        wp_localize_script( 'mlbkp-admin-restore', 'mlbkpRestoreData', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
        ] );
    }

    public static function render_page(): void {
        echo '<div class="wrap"><h1>Media Lab Backup — Wiederherstellen</h1>';
        echo '<div id="mlbkp-restore-app"></div></div>';
    }

    // ── AJAX: Sessions auflisten ─────────────────────────────────────────────

    public static function ajax_list_sessions(): void {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [ 'message' => 'Keine Berechtigung.' ] );

        try {
            $sftp     = new MLBKP_SFTP( mlbkp_get_settings() );
            $sessions = $sftp->list_sessions();
        } catch ( \Throwable $e ) {
            wp_send_json_error( [ 'message' => 'SFTP-Verbindung fehlgeschlagen: ' . $e->getMessage() ] );
            return;
        }

        wp_send_json_success( [ 'sessions' => $sessions ] );
    }

    // ── AJAX: Eine Session laden (Manifest oder Fallback) ────────────────────

    public static function ajax_load_session(): void {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [ 'message' => 'Keine Berechtigung.' ] );

        $session_dir = sanitize_text_field( wp_unslash( $_POST['session_dir'] ?? '' ) );
        if ( $session_dir === '' ) wp_send_json_error( [ 'message' => 'Kein Session-Verzeichnis angegeben.' ] );

        try {
            $sftp     = new MLBKP_SFTP( mlbkp_get_settings() );
            $manifest = MLBKP_Manifest::load( $session_dir, $sftp );

            if ( $manifest === null ) {
                $entries  = $sftp->list_entries( $session_dir );
                $manifest = MLBKP_Manifest::build_fallback( $entries );
            }
        } catch ( \Throwable $e ) {
            wp_send_json_error( [ 'message' => 'Fehler beim Laden der Session: ' . $e->getMessage() ] );
            return;
        }

        foreach ( $manifest['items'] as &$item ) {
            $item['remote_path'] = $item['remote_path'] ?? ( $session_dir . '/' . $item['filename'] );
            if ( $item['type'] === 'database' ) continue;

            $item['target_path_guess']    = self::guess_target_path( $item['source_path'] ?? null, $item['label'] );
            $item['target_path_reliable'] = ! empty( $item['source_path'] );
        }
        unset( $item );

        wp_send_json_success( [ 'session_dir' => $session_dir, 'manifest' => $manifest ] );
    }

    /**
     * Bestimmt den Ziel-Restore-Pfad: exakt aus dem Manifest wenn vorhanden,
     * sonst best-effort aus dem (evtl. rekonstruierten) Label geraten.
     * Geschätzte Pfade MÜSSEN im UI vom Benutzer bestätigt/korrigiert werden
     * (target_path_reliable = false).
     */
    private static function guess_target_path( ?string $source_path, string $label ): string {
        if ( $source_path ) return $source_path;

        $clean = preg_replace( '/\s*\((?:aus Dateiname rekonstruiert|kein Manifest.*?)\)\s*$/u', '', $label );
        $clean = trim( $clean, '- ' );

        if ( str_starts_with( $clean, 'wpcore/' ) ) {
            return trailingslashit( ABSPATH ) . substr( $clean, 7 );
        }
        if ( str_starts_with( $clean, 'wp-content' ) ) {
            return trailingslashit( ABSPATH ) . $clean;
        }
        // Unsicherer Fall (sanitized Label, Slashes wurden zu Bindestrichen):
        // Best-effort unter wp-content annehmen.
        return trailingslashit( WP_CONTENT_DIR ) . str_replace( '-', '/', $clean );
    }

    // ── AJAX: Dateien innerhalb eines Chunks auflisten (Phase 2) ─────────────

    public static function ajax_list_chunk_files(): void {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [ 'message' => 'Keine Berechtigung.' ] );

        $remote_path = sanitize_text_field( wp_unslash( $_POST['remote_path'] ?? '' ) );
        $is_archive  = ! empty( $_POST['is_archive'] );
        if ( $remote_path === '' ) wp_send_json_error( [ 'message' => 'Kein Pfad angegeben.' ] );

        $temp = null;

        try {
            $sftp = new MLBKP_SFTP( mlbkp_get_settings() );

            if ( $is_archive ) {
                $temp = wp_tempnam( 'mlbkp-restore-browse' );
                $sftp->download( $remote_path, $temp );

                $zip = new ZipArchive();
                if ( $zip->open( $temp ) !== true ) throw new RuntimeException( 'ZIP konnte nicht geöffnet werden.' );

                $files = [];
                for ( $i = 0; $i < $zip->numFiles; $i++ ) {
                    $stat = $zip->statIndex( $i );
                    if ( $stat === false || str_ends_with( $stat['name'], '/' ) ) continue;
                    $files[] = [ 'relative' => $stat['name'], 'size' => $stat['size'] ];
                }
                $zip->close();
            } else {
                $files = array_map(
                    static fn( $f ) => [ 'relative' => $f['relative'], 'size' => $f['size'] ],
                    $sftp->list_entries_recursive( $remote_path )
                );
            }
        } catch ( \Throwable $e ) {
            if ( $temp ) @unlink( $temp );
            wp_send_json_error( [ 'message' => 'Konnte Dateiliste nicht laden: ' . $e->getMessage() ] );
            return;
        }

        if ( $temp ) @unlink( $temp );

        wp_send_json_success( [ 'files' => $files ] );
    }

    // ── AJAX: DB-Tabellen auflisten (Phase 3) ─────────────────────────────────

    public static function ajax_list_db_tables(): void {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [ 'message' => 'Keine Berechtigung.' ] );

        $remote_path = sanitize_text_field( wp_unslash( $_POST['remote_path'] ?? '' ) );
        if ( $remote_path === '' ) wp_send_json_error( [ 'message' => 'Kein Pfad angegeben.' ] );

        $gz_local  = null;
        $sql_local = null;

        try {
            $sftp     = new MLBKP_SFTP( mlbkp_get_settings() );
            $gz_local = wp_tempnam( 'mlbkp-restore-db' );
            $sftp->download( $remote_path, $gz_local );

            $sql_local = $gz_local . '.sql';
            $in  = gzopen( $gz_local, 'rb' );
            $out = fopen( $sql_local, 'wb' );
            while ( ! gzeof( $in ) ) fwrite( $out, gzread( $in, 1048576 ) );
            gzclose( $in );
            fclose( $out );

            $tables = MLBKP_SQL_Parser::list_tables( $sql_local );
        } catch ( \Throwable $e ) {
            if ( $gz_local ) @unlink( $gz_local );
            if ( $sql_local ) @unlink( $sql_local );
            wp_send_json_error( [ 'message' => 'Konnte Tabellenliste nicht laden: ' . $e->getMessage() ] );
            return;
        }

        @unlink( $gz_local );
        @unlink( $sql_local );

        wp_send_json_success( [ 'tables' => $tables ] );
    }

    // ── AJAX: Restore starten ─────────────────────────────────────────────────

    /**
     * Erwartet $_POST['session_dir'] und $_POST['items'] als JSON-Array:
     * [{ "type":"database"|"dir"|"dir_files_only", "label":"...",
     *    "remote_path":"...", "is_archive":true,
     *    "restore_mode":"full"|"selective",
     *    "target_path":"...",       // nur bei Datei-Items
     *    "files":["a/b.php",...],   // nur bei Datei-Items + selective
     *    "tables":["wp_posts",...]  // nur bei DB-Items + selective
     * }, ...]
     */
    public static function ajax_start(): void {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [ 'message' => 'Keine Berechtigung.' ] );

        $session_dir = sanitize_text_field( wp_unslash( $_POST['session_dir'] ?? '' ) );
        $raw_items   = json_decode( wp_unslash( $_POST['items'] ?? '[]' ), true );

        if ( $session_dir === '' || ! is_array( $raw_items ) || empty( $raw_items ) ) {
            wp_send_json_error( [ 'message' => 'Keine Elemente zur Wiederherstellung ausgewählt.' ] );
            return;
        }

        $items = [];
        $id    = 0;

        foreach ( $raw_items as $raw ) {
            $is_db     = ( $raw['type'] ?? '' ) === 'database';
            $selective = ( $raw['restore_mode'] ?? 'full' ) === 'selective';
            $label     = sanitize_text_field( $raw['label'] ?? '' );

            if ( $is_db ) {
                $type  = $selective ? 'db_tables' : 'db_full';
                $extra = [
                    'remote_path' => $raw['remote_path'],
                    'stats'       => [ 'executed' => 0, 'skipped' => 0, 'errors' => [] ],
                ];
                if ( $selective ) $extra['tables'] = array_map( 'sanitize_text_field', $raw['tables'] ?? [] );
            } else {
                $target = sanitize_text_field( $raw['target_path'] ?? '' );
                if ( $target === '' ) {
                    wp_send_json_error( [ 'message' => "Kein Zielpfad für „{$label}“ angegeben." ] );
                    return;
                }

                $type  = $selective ? 'chunk_files' : 'chunk_full';
                $extra = [
                    'remote_path' => $raw['remote_path'],
                    'target_path' => untrailingslashit( $target ),
                    'is_archive'  => (bool) ( $raw['is_archive'] ?? true ),
                    'stats'       => [],
                ];
                if ( $selective ) $extra['files'] = array_map( 'sanitize_text_field', $raw['files'] ?? [] );
            }

            $items[] = MLBKP_Restore_Session::make_item( $id++, $type, $label, $extra );
        }

        $session = MLBKP_Restore_Session::create( $session_dir, $items );
        MLBKP_Restore_Runner::schedule( $session['id'] );

        wp_send_json_success( [
            'session_id'  => $session['id'],
            'items_total' => count( $items ),
        ] );
    }

    // ── AJAX: Status pollen ────────────────────────────────────────────────────

    public static function ajax_check_status(): void {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );

        $session_id = sanitize_text_field( wp_unslash( $_POST['session_id'] ?? '' ) );
        $session    = MLBKP_Restore_Session::load( $session_id );

        if ( ! $session ) {
            wp_send_json_error( [ 'message' => 'Session nicht gefunden.' ] );
            return;
        }

        $items = array_map( static fn( $i ) => [
            'id'     => $i['id'],
            'label'  => $i['label'],
            'type'   => $i['type'],
            'status' => $i['status'],
            'error'  => $i['error'],
            'stats'  => $i['stats'] ?? [],
        ], $session['items'] );

        wp_send_json_success( [
            'status'        => $session['status'],
            'items_done'    => $session['items_done'],
            'items_total'   => count( $session['items'] ),
            'items'         => $items,
            'error_message' => $session['error_message'],
            'safety_dir'    => $session['safety_dir'],
        ] );
    }

    // ── AJAX: Abbrechen ─────────────────────────────────────────────────────────

    public static function ajax_cancel(): void {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );

        $session_id = sanitize_text_field( wp_unslash( $_POST['session_id'] ?? '' ) );
        if ( $session_id === '' ) {
            wp_send_json_error( [ 'message' => 'Keine Session-ID.' ] );
            return;
        }

        update_option( 'mlbkp_restore_cancel_' . $session_id, 1, false );
        wp_send_json_success();
    }
}
