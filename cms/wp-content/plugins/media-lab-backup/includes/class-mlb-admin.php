<?php
defined( 'ABSPATH' ) || exit;

/**
 * MLBKP_Admin
 *
 * Registriert Admin-Menü, Einstellungsseiten und AJAX-Handler.
 */
class MLBKP_Admin {

    const MENU_SLUG     = 'media-lab-backup';
    const SETTINGS_KEY  = 'mlbkp_settings';
    const NONCE_ACTION  = 'mlbkp_admin_nonce';

    public static function init(): void {
        if ( ! is_admin() ) return;

        add_action( 'admin_menu',           [ self::class, 'register_menu' ] );
        add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_assets' ] );

        // AJAX: Backup starten
        add_action( 'wp_ajax_mlbkp_run_backup',        [ self::class, 'ajax_run_backup' ] );
        add_action( 'wp_ajax_mlbkp_check_status',      [ self::class, 'ajax_backup_status' ] );
        add_action( 'wp_ajax_mlbkp_cancel_backup',     [ self::class, 'ajax_cancel_backup' ] );
        add_action( 'wp_ajax_mlbkp_cleanup_stuck',     [ self::class, 'ajax_cleanup_stuck' ] );
        add_action( 'wp_ajax_mlbkp_finalize_session',  [ self::class, 'ajax_finalize_session' ] );
        // AJAX: SFTP-Verbindung testen
        add_action( 'wp_ajax_mlbkp_test_connection',   [ self::class, 'ajax_test_connection' ] );
        // AJAX: Einstellungen speichern
        add_action( 'wp_ajax_mlbkp_save_settings',     [ self::class, 'ajax_save_settings' ] );
        // AJAX: Verzeichnisbaum laden
        add_action( 'wp_ajax_mlbkp_get_file_tree',    [ self::class, 'ajax_get_file_tree' ] );
    }

    // ── Menü ─────────────────────────────────────────────────────────────────

    public static function register_menu(): void {
        add_menu_page(
            'Media Lab Backup',
            'ML Backup',
            'manage_options',
            self::MENU_SLUG,
            [ self::class, 'render_page' ],
            'dashicons-backup',
            81
        );
    }

    // ── Assets ───────────────────────────────────────────────────────────────
    public static function enqueue_assets( string $hook ): void {
        if ( ! str_contains( $hook, self::MENU_SLUG ) ) return;

        wp_enqueue_style(
            'mlbkp-admin',
            MLBKP_PLUGIN_URL . 'admin/css/admin.css',
            [],
            MLBKP_VERSION
        );

        wp_enqueue_script(
            'mlbkp-admin',
            MLBKP_PLUGIN_URL . 'admin/js/admin.js',
            [ 'jquery' ],
            MLBKP_VERSION,
            true
        );

        // Läuft aktuell eine Session? (z.B. nach Tab-Wechsel/Reload während
        // eines Backups) — für das Live-Log-Resume im Frontend.
        $running = MLBKP_Session::find_running();

        wp_localize_script( 'mlbkp-admin', 'mlbkpData', [
            'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
            'nonce'     => wp_create_nonce( self::NONCE_ACTION ),
            'running'   => $running ? [
                'logId'       => $running['log_id'],
                'sessionId'   => $running['id'],
                'chunksTotal' => $running['chunks_total'],
                'chunkLabels' => array_column( $running['chunks'], 'label' ),
            ] : null,
            'strings'   => [
                'running'       => 'Backup läuft …',
                'success'       => '✅ Backup erfolgreich abgeschlossen.',
                'error'         => '❌ Fehler beim Backup.',
                'testing'       => 'Verbindung wird getestet …',
                'conn_success'  => '✅ SFTP-Verbindung erfolgreich.',
                'conn_error'    => '❌ Verbindungsfehler: ',
                'saving'        => 'Einstellungen werden gespeichert …',
                'saved'         => '✅ Einstellungen gespeichert.',
            ],
        ] );
    }

    // ── Seiten-Rendering ─────────────────────────────────────────────────────

    public static function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Keine Berechtigung.' );
        }

        $tab = sanitize_key( $_GET['tab'] ?? 'settings' );
        $tabs = [
            'settings' => 'Einstellungen',
            'run'      => 'Backup starten',
            'logs'     => 'Protokoll',
        ];

        echo '<div class="wrap mlb-wrap">';
        echo '<h1><span class="dashicons dashicons-backup"></span> Media Lab Backup</h1>';

        // Tab-Navigation
        echo '<nav class="nav-tab-wrapper mlb-tabs">';
        foreach ( $tabs as $key => $label ) {
            $active = $tab === $key ? ' nav-tab-active' : '';
            $url    = admin_url( 'admin.php?page=' . self::MENU_SLUG . '&tab=' . $key );
            echo "<a href=\"{$url}\" class=\"nav-tab{$active}\">{$label}</a>";
        }
        echo '</nav>';

        echo '<div class="mlb-tab-content">';

        switch ( $tab ) {
            case 'run':
                require MLBKP_PLUGIN_DIR . 'admin/views/page-run.php';
                break;
            case 'logs':
                require MLBKP_PLUGIN_DIR . 'admin/views/page-logs.php';
                break;
            default:
                require MLBKP_PLUGIN_DIR . 'admin/views/page-settings.php';
        }

        echo '</div></div>';
    }

    // ── AJAX: Backup starten (asynchron via WP-Cron) ─────────────────────────

    public static function ajax_run_backup(): void {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Keine Berechtigung.' ] );
        }

        $type = sanitize_key( $_POST['backup_type'] ?? 'full' );
        if ( ! in_array( $type, [ 'database', 'wpcontent', 'wpcore', 'full' ], true ) ) {
            wp_send_json_error( [ 'message' => 'Ungültiger Backup-Typ.' ] );
        }

        $settings = mlbkp_get_settings();
        $session  = MLBKP_Session::create( $type, 'manual', $settings );

        // Ersten Chunk sofort planen
        MLBKP_Scheduler::schedule_chunk( $session['id'] );

        wp_send_json_success( [
            'session_id'   => $session['id'],
            'log_id'       => $session['log_id'],
            'chunks_total' => $session['chunks_total'],
            'chunk_labels' => array_column( $session['chunks'], 'label' ),
            'message'      => "Backup gestartet ({$session['chunks_total']} Chunks).",
        ] );
    }

    // ── AJAX: Backup-Status pollen ────────────────────────────────────────────

    public static function ajax_backup_status(): void {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Keine Berechtigung.' ] );
        }

        $log_id     = (int) ( $_POST['log_id'] ?? 0 );
        $session_id = sanitize_text_field( $_POST['session_id'] ?? '' );

        if ( $log_id <= 0 ) {
            wp_send_json_error( [ 'message' => 'Ungültige Log-ID.' ] );
        }

        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT status, error_message, file_size, duration_sec FROM ' . MLBKP_Logger::get_table() . ' WHERE id = %d',
                $log_id
            ),
            ARRAY_A
        );

        if ( ! $row ) {
            wp_send_json_error( [ 'message' => 'Log-Eintrag nicht gefunden.' ] );
        }

        // Timeout-Check
        if ( $row['status'] === 'running' && MLBKP_Logger::is_timed_out( $log_id ) ) {
            MLBKP_Logger::finish( $log_id, 'error', [
                'error_message' => 'Job-Timeout nach ' . MLBKP_Logger::JOB_TIMEOUT_MINUTES . ' Minuten.',
            ] );
            $row['status']        = 'error';
            $row['error_message'] = 'Job-Timeout nach ' . MLBKP_Logger::JOB_TIMEOUT_MINUTES . ' Minuten.';
        }

        // Session-Chunks für Fortschrittsanzeige
        $chunks = [];
        if ( $session_id ) {
            $session = MLBKP_Session::load( $session_id );
            if ( $session ) {

                // Safety-Net: alle Chunks erledigt aber Log noch 'running' → jetzt finalisieren
                if ( $row['status'] === 'running' ) {
                    $pending = array_filter( $session['chunks'], static fn( $c ) =>
                        in_array( $c['status'], [ 'pending', 'running' ], true )
                    );

                    if ( empty( $pending ) ) {
                        $has_db_error = ! empty( array_filter( $session['chunks'], static fn( $c ) =>
                            $c['status'] === 'error' && $c['type'] === 'database'
                        ) );

                        $final_status = $has_db_error ? 'error' : 'success';
                        $filenames    = array_filter( array_column( $session['chunks'], 'filename' ) );
                        $total_size   = array_sum( array_column( $session['chunks'], 'size' ) );

                        MLBKP_Logger::finish( $session['log_id'], $final_status, [
                            'file_name'   => mb_strimwidth( implode( ', ', $filenames ), 0, 250, '…' ),
                            'file_size'   => $total_size,
                            'remote_path' => $session['remote_session_dir'] ?? '',
                        ] );

                        $session['status']     = $final_status;
                        $session['total_size'] = $total_size;
                        MLBKP_Session::save( $session );

                        $row['status'] = $final_status;
                    }
                }

                // Chunk-Timeout: DB-Chunks 6 Min, Datei-Chunks 20 Min
                foreach ( $session['chunks'] as &$chunk ) {
                    if ( $chunk['status'] === 'running' ) {
                        $started     = get_option( 'mlbkp_chunk_started_' . $session_id . '_' . $chunk['id'] );
                        $max_seconds = $chunk['type'] === 'database' ? 360 : 1200; // 6 vs 20 Minuten
                        $label       = $chunk['type'] === 'database' ? '6 Minuten' : '20 Minuten';

                        if ( $started && ( time() - (int) $started ) > $max_seconds ) {
                            MLBKP_Session::update_chunk( $session, $chunk['id'], [
                                'status' => 'error',
                                'error'  => "Chunk-Timeout nach {$label}.",
                            ] );
                            MLBKP_Session::save( $session );
                            MLBKP_Scheduler::schedule_chunk( $session_id );
                        }
                        break;
                    }
                }
                unset( $chunk );

                $chunks = array_map( static fn( $c ) => [
                    'id'     => $c['id'],
                    'label'  => $c['label'],
                    'status' => $c['status'],
                    'size'   => $c['size'] ? MLBKP_Logger::format_bytes( (int) $c['size'] ) : '',
                    'error'  => $c['error'] ?? '',
                ], $session['chunks'] );
            }
        }

        wp_send_json_success( [
            'status'        => $row['status'],
            'error_message' => $row['error_message'] ?? '',
            'file_size'     => $row['file_size'] ? MLBKP_Logger::format_bytes( (int) $row['file_size'] ) : '',
            'duration'      => MLBKP_Logger::format_duration( isset( $row['duration_sec'] ) ? (int) $row['duration_sec'] : null ),
            'chunks'        => $chunks,
        ] );
    }

    // ── AJAX: Session finalisieren ────────────────────────────────────────────

    public static function ajax_finalize_session(): void {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Keine Berechtigung.' ] );
        }

        $session_id = sanitize_text_field( $_POST['session_id'] ?? '' );
        $log_id     = (int) ( $_POST['log_id'] ?? 0 );

        if ( ! $session_id || ! $log_id ) {
            wp_send_json_error( [ 'message' => 'Fehlende Parameter.' ] );
        }

        global $wpdb;
        $table = MLBKP_Logger::get_table();

        // Session laden
        $session      = MLBKP_Session::load( $session_id );
        $final_status = 'success';
        $file_name    = '';
        $file_size    = 0;
        $remote_path  = '';
        $duration     = null;

        if ( $session ) {
            $has_db_error = ! empty( array_filter( $session['chunks'], static fn( $c ) =>
                $c['status'] === 'error' && $c['type'] === 'database'
            ) );
            $final_status = $has_db_error ? 'error' : 'success';
            $all_names    = array_filter( array_column( $session['chunks'], 'filename' ) );
            $file_name    = mb_strimwidth( implode( ', ', $all_names ), 0, 250, '…' );
            $file_size    = (int) array_sum( array_column( $session['chunks'], 'size' ) );
            $remote_path  = $session['remote_session_dir'] ?? '';
            $duration     = round( microtime( true ) - strtotime( $session['started_at'] ) );

            $session['status']     = $final_status;
            $session['total_size'] = $file_size;
            MLBKP_Session::save( $session );
        }

        $now = current_time( 'mysql' );

        // Rohes SQL — zuverlässigste Methode
        $rows_affected = $wpdb->query( $wpdb->prepare(
            "UPDATE `{$table}` SET
                status       = %s,
                finished_at  = %s,
                file_name    = %s,
                file_size    = %d,
                remote_path  = %s
            WHERE id = %d AND status = 'running'",
            $final_status,
            $now,
            $file_name,
            $file_size,
            $remote_path,
            $log_id
        ) );

        // Falls der Row bereits finalisiert wurde (rows_affected = 0), trotzdem Erfolg zurückgeben
        wp_send_json_success( [
            'status'        => $final_status,
            'file_size'     => MLBKP_Logger::format_bytes( $file_size ),
            'rows_affected' => $rows_affected,
            'db_error'      => $wpdb->last_error ?: null,
        ] );
    }

    // ── AJAX: Hängende Jobs bereinigen ────────────────────────────────────────

    public static function ajax_cleanup_stuck(): void {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Keine Berechtigung.' ] );
        }

        global $wpdb;

        // Alle running-Einträge auf error setzen
        $count = $wpdb->update(
            MLBKP_Logger::get_table(),
            [
                'status'        => 'error',
                'finished_at'   => current_time( 'mysql' ),
                'error_message' => 'Manuell bereinigt.',
            ],
            [ 'status' => 'running' ]
        );

        // Zugehörige MLBKP_Session-Einträge synchronisieren — sonst bleibt
        // find_running() (Live-Log-Resume) diese Sessions dauerhaft als
        // "running" ansehen, auch nachdem der Logger-Eintrag bereinigt wurde.
        foreach ( MLBKP_Session::get_index() as $session_id ) {
            $session = MLBKP_Session::load( $session_id );
            if ( $session && $session['status'] === 'running' ) {
                MLBKP_Session::finish( $session, 'error', 'Manuell bereinigt.' );
            }
        }

        // Lock löschen
        delete_option( 'mlbkp_backup_running' );
        delete_option( 'mlbkp_backup_running_time' );

        // Alle Cancel-Flags löschen
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'mlbkp_cancel_%'" );

        wp_send_json_success( [
            'message' => "{$count} hängende(r) Job(s) bereinigt.",
            'count'   => $count,
        ] );
    }

    // ── AJAX: Backup abbrechen ────────────────────────────────────────────────

    public static function ajax_cancel_backup(): void {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Keine Berechtigung.' ] );
        }

        $log_id = (int) ( $_POST['log_id'] ?? 0 );
        if ( $log_id <= 0 ) {
            wp_send_json_error( [ 'message' => 'Ungültige Log-ID.' ] );
        }

        MLBKP_Logger::set_cancel_flag( $log_id );

        wp_send_json_success( [ 'message' => 'Abbruch-Signal gesendet. Job wird beim nächsten Checkpoint gestoppt.' ] );
    }

    // ── AJAX: SFTP testen ────────────────────────────────────────────────────

    public static function ajax_test_connection(): void {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Keine Berechtigung.' ] );
        }

        $settings = mlbkp_get_settings();

        // Ggf. Live-Daten aus dem Formular verwenden (vor dem Speichern testen)
        if ( ! empty( $_POST['sftp_host'] ) ) {
            $settings['sftp_host']          = sanitize_text_field( $_POST['sftp_host'] );
            $settings['sftp_port']          = (int) ( $_POST['sftp_port'] ?? 22 );
            $settings['sftp_username']      = sanitize_text_field( $_POST['sftp_username'] );
            $settings['sftp_password']      = $_POST['sftp_password'] ?? $settings['sftp_password'];
            $settings['sftp_path']          = sanitize_text_field( $_POST['sftp_path'] ?? '/' );
            $settings['sftp_site_folder']   = sanitize_text_field( $_POST['sftp_site_folder'] ?? '' );
            $settings['sftp_auth_method']   = sanitize_key( $_POST['sftp_auth_method'] ?? 'password' );
            $settings['sftp_private_key']   = $_POST['sftp_private_key'] ?? $settings['sftp_private_key'];
            $settings['sftp_key_passphrase'] = $_POST['sftp_key_passphrase'] ?? $settings['sftp_key_passphrase'];
        }

        $result = MLBKP_SFTP::test_connection( $settings );

        if ( $result === true ) {
            wp_send_json_success( [ 'message' => 'SFTP-Verbindung erfolgreich.' ] );
        } else {
            wp_send_json_error( [ 'message' => $result ] );
        }
    }

    // ── AJAX: Verzeichnisbaum ────────────────────────────────────────────────

    public static function ajax_get_file_tree(): void {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Keine Berechtigung.' ] );
        }

        $root = WP_CONTENT_DIR;
        $tree = self::scan_dir_tree( $root, '', 0, 3 );

        wp_send_json_success( [ 'tree' => $tree, 'root_label' => 'wp-content' ] );
    }

    /**
     * Scannt ein Verzeichnis rekursiv und gibt einen JSON-kompatiblen Baum zurück.
     * Gibt nur Verzeichnisse zurück (keine Dateien), max. $max_depth Ebenen tief.
     *
     * @return array<int, array{name: string, path: string, type: string, children: array}>
     */
    public static function scan_dir_tree( string $base, string $relative, int $depth, int $max_depth ): array {
        $skip = [ '.', '..', '.git', '.DS_Store', 'node_modules', '.sass-cache', '__MACOSX' ];

        $scan_path = $base . ( $relative !== '' ? DIRECTORY_SEPARATOR . $relative : '' );
        $entries   = @scandir( $scan_path );

        if ( ! $entries ) return [];

        $result = [];

        foreach ( $entries as $entry ) {
            if ( in_array( $entry, $skip, true ) ) continue;
            if ( str_starts_with( $entry, '.' ) ) continue;

            $full_path     = $scan_path . DIRECTORY_SEPARATOR . $entry;
            $relative_path = $relative !== '' ? $relative . '/' . $entry : $entry;

            if ( ! is_dir( $full_path ) ) continue;

            $children = ( $depth < $max_depth )
                ? self::scan_dir_tree( $base, $relative_path, $depth + 1, $max_depth )
                : [];

            $result[] = [
                'name'     => $entry,
                'path'     => $relative_path,
                'type'     => 'dir',
                'children' => $children,
            ];
        }

        return $result;
    }

    // ── AJAX: Einstellungen speichern ────────────────────────────────────────

    public static function ajax_save_settings(): void {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Keine Berechtigung.' ] );
        }

        $current  = mlbkp_get_settings();
        $password = sanitize_text_field( $_POST['sftp_password'] ?? '' );
        $pk       = trim( $_POST['sftp_private_key'] ?? '' );
        $kp       = $_POST['sftp_key_passphrase'] ?? '';

        // Leere Felder = gespeicherte Werte beibehalten
        if ( empty( $password ) ) $password = $current['sftp_password'];
        if ( empty( $pk ) )       $pk       = $current['sftp_private_key'];
        if ( empty( $kp ) )       $kp       = $current['sftp_key_passphrase'];

        $new_settings = [
            // SFTP
            'sftp_host'          => sanitize_text_field( $_POST['sftp_host'] ?? '' ),
            'sftp_port'          => (int) ( $_POST['sftp_port'] ?? 22 ),
            'sftp_username'      => sanitize_text_field( $_POST['sftp_username'] ?? '' ),
            'sftp_password'      => $password,
            'sftp_path'          => sanitize_text_field( $_POST['sftp_path'] ?? '/' ),
            'sftp_site_folder'   => sanitize_text_field( $_POST['sftp_site_folder'] ?? '' ),
            'sftp_auth_method'   => in_array( $_POST['sftp_auth_method'] ?? '', [ 'password', 'key' ], true )
                                        ? $_POST['sftp_auth_method'] : 'password',
            'sftp_private_key'   => $pk,
            'sftp_key_passphrase' => $kp,

            // Scope
            'backup_database'    => ! empty( $_POST['backup_database'] )  ? '1' : '0',
            'backup_wpcontent'   => ! empty( $_POST['backup_wpcontent'] ) ? '1' : '0',
            'backup_wpcore'      => ! empty( $_POST['backup_wpcore'] )    ? '1' : '0',
            'backup_file_method' => in_array( $_POST['backup_file_method'] ?? 'zip', [ 'zip', 'stream' ], true )
                                        ? $_POST['backup_file_method'] : 'zip',

            // Ausschlüsse
            'exclude_paths' => sanitize_textarea_field( $_POST['exclude_paths'] ?? '' ),

            // Schedule
            'schedule'      => sanitize_key( $_POST['schedule'] ?? 'daily' ),
            'schedule_time' => sanitize_text_field( $_POST['schedule_time'] ?? '02:00' ),
            'schedule_day'  => sanitize_key( $_POST['schedule_day'] ?? 'monday' ),

            // Retention
            'retention_count' => max( 1, (int) ( $_POST['retention_count'] ?? 7 ) ),

            // Benachrichtigung
            'notify_email' => sanitize_email( $_POST['notify_email'] ?? '' ),
            'notify_on'    => sanitize_key( $_POST['notify_on'] ?? 'error' ),
        ];

        update_option( self::SETTINGS_KEY, $new_settings );

        // Cron neu planen
        MLBKP_Scheduler::reschedule();

        wp_send_json_success( [
            'message'  => 'Einstellungen gespeichert.',
            'next_run' => MLBKP_Scheduler::get_next_run() ?? '—',
        ] );
    }
}
