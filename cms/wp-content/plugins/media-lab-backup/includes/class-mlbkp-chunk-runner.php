<?php
defined( 'ABSPATH' ) || exit;

/**
 * MLBKP_Chunk_Runner
 *
 * Verarbeitet einen einzelnen Backup-Chunk.
 * Wird von WP-Cron als separater Job aufgerufen.
 */
class MLBKP_Chunk_Runner {

    private array  $session;
    private array  $settings;
    private string $temp_dir;
    private array  $log = [];

    public function __construct( array $session ) {
        $this->session  = $session;
        $this->settings = mlbkp_get_settings();
        $this->temp_dir = $this->prepare_temp_dir();
    }

    // ── Öffentliche API ───────────────────────────────────────────────────────

    /**
     * Haupteinstiegspunkt — wird von WP-Cron aufgerufen.
     */
    public static function process( string $session_id ): void {
        $session = MLBKP_Session::load( $session_id );

        if ( ! $session ) {
            return; // Session gelöscht oder abgelaufen
        }

        if ( $session['status'] !== 'running' ) {
            return; // Bereits abgeschlossen
        }

        // Abbruch prüfen
        if ( MLBKP_Logger::is_cancelled( $session['log_id'] ) ) {
            MLBKP_Session::finish( $session, 'cancelled' );
            MLBKP_Logger::clear_cancel_flag( $session['log_id'] );
            MLBKP_Logger::finish( $session['log_id'], 'cancelled', [ 'error_message' => 'Manuell abgebrochen.' ] );
            return;
        }

        $runner = new self( $session );
        $runner->run_next_chunk();
    }

    // ── Chunk-Verarbeitung ────────────────────────────────────────────────────

    private function run_next_chunk(): void {
        @set_time_limit( 0 );
        @ini_set( 'memory_limit', '512M' );

        $chunk = MLBKP_Session::get_next_chunk( $this->session );

        if ( ! $chunk ) {
            // Alle Chunks erledigt
            $this->finish_session();
            return;
        }

        $this->log( "▶ Chunk {$chunk['id']}: {$chunk['label']}" );

        // Chunk als laufend markieren + Startzeit speichern
        MLBKP_Session::update_chunk( $this->session, $chunk['id'], [ 'status' => 'running' ] );
        update_option( 'mlbkp_chunk_started_' . $this->session['id'] . '_' . $chunk['id'], time(), false );
        MLBKP_Session::save( $this->session );

        try {
            // SFTP verbinden
            $sftp = new MLBKP_SFTP( $this->settings );

            // Remote-Session-Verzeichnis beim ersten Chunk erstellen
            if ( empty( $this->session['remote_session_dir'] ) ) {
                $remote_dir = $sftp->create_session_dir( gmdate( 'Y-m-d_H-i-s' ) );
                $this->session['remote_session_dir'] = $remote_dir;
                MLBKP_Session::save( $this->session );
            }

            // Chunk verarbeiten
            $result = match ( $chunk['type'] ) {
                'database'      => $this->process_database( $sftp ),
                'dir'           => $this->process_directory( $chunk, $sftp ),
                'dir_files_only' => $this->process_directory_files_only( $chunk, $sftp ),
                default         => throw new RuntimeException( "Unbekannter Chunk-Typ: {$chunk['type']}" ),
            };

            // Chunk als erledigt markieren
            MLBKP_Session::update_chunk( $this->session, $chunk['id'], [
                'status'      => 'done',
                'size'        => $result['size'],
                'filename'    => $result['filename'],
                'remote_path' => $result['remote_path'],
            ] );

            $this->log( "✅ {$chunk['label']}: " . MLBKP_Logger::format_bytes( $result['size'] ) );

        } catch ( MLBKP_CancelledException $e ) {
            MLBKP_Session::finish( $this->session, 'cancelled' );
            $this->cleanup_temp_dir();
            return;

        } catch ( \Throwable $e ) {
            $error = $e->getMessage();

            // Leeres Verzeichnis → als 'skipped' markieren statt 'error'
            $is_empty_dir = str_contains( $error, 'ist leer' );
            $chunk_status = $is_empty_dir ? 'skipped' : 'error';

            if ( $is_empty_dir ) {
                $this->log( "⏭ Übersprungen: {$chunk['label']} (leeres Verzeichnis)" );
            } else {
                $this->log( "❌ Fehler: {$error}" );
            }

            MLBKP_Session::update_chunk( $this->session, $chunk['id'], [
                'status' => $chunk_status,
                'error'  => $is_empty_dir ? '' : $error,
            ] );

            // Bei DB-Fehler oder kritischem Fehler: Session abbrechen
            if ( $chunk['type'] === 'database' && ! $is_empty_dir ) {
                MLBKP_Session::finish( $this->session, 'error', $error );
                $this->maybe_send_notification( false, $error );
                $this->cleanup_temp_dir();
                return;
            }

            // Bei Datei-Chunk: Warnung aber weitermachen
            if ( ! $is_empty_dir ) {
                $this->log( "⚠ Chunk übersprungen, nächster Chunk wird gestartet." );
            }
        }

        MLBKP_Session::save( $this->session );

        // Nächsten Chunk planen
        $next = MLBKP_Session::get_next_chunk( $this->session );
        if ( $next ) {
            MLBKP_Scheduler::schedule_chunk( $this->session['id'] );
        } else {
            $this->finish_session();
        }
    }

    // ── Chunk-Typen ───────────────────────────────────────────────────────────

    private function process_database( MLBKP_SFTP $sftp ): array {
        $this->log( '🗄  Datenbank-Dump erstellen …' );
        $db_backup = new MLBKP_Database_Backup( $this->temp_dir );
        $result    = $db_backup->create();

        $this->log( "   Methode: {$result['method']}" );
        if ( ! empty( $result['fallback_reason'] ) ) {
            $this->log( "   ⚠ Fallback: {$result['fallback_reason']}" );
        }

        $remote_path = $sftp->upload_to_session( $result['path'], $result['filename'], $this->session['remote_session_dir'] );
        @unlink( $result['path'] );

        return [
            'size'        => $result['size'],
            'filename'    => $result['filename'],
            'remote_path' => $remote_path,
        ];
    }

    private function process_directory( array $chunk, MLBKP_SFTP $sftp ): array {
        $method    = $this->session['file_method'] ?? 'zip';
        $label     = $chunk['label'];
        $source    = $chunk['path'];
        $excludes  = $this->session['excludes'] ?? [];

        if ( $method === 'stream' ) {
            $this->log( "📂 Streame {$label} …" );
            $sub_dir = $sftp->get_session_subdir( $this->session['remote_session_dir'], $this->sanitize_label( $label ) );
            $result  = $sftp->stream_single_directory( $source, $sub_dir, $excludes, $this->session['log_id'] );
            $this->log( "   {$result['file_count']} Dateien, " . MLBKP_Logger::format_bytes( $result['total_size'] ) );
            if ( $result['skipped'] > 0 ) $this->log( "   ⚠ {$result['skipped']} übersprungen." );

            return [
                'size'        => $result['total_size'],
                'filename'    => basename( $sub_dir ),
                'remote_path' => $sub_dir,
            ];
        }

        // ZIP-Methode
        $this->log( "📦 Erstelle ZIP für {$label} …" );
        $filename  = 'chunk-' . $this->sanitize_label( $label ) . '-' . gmdate( 'Y-m-d_H-i-s' ) . '.zip';
        $zip_path  = $this->temp_dir . $filename;

        $file_backup = new MLBKP_File_Backup( $this->temp_dir, $this->session['log_id'] );
        $actual_path = $file_backup->create_single_dir_zip( $source, $zip_path, $excludes );
        $size        = filesize( $actual_path );
        $filename    = basename( $actual_path );

        $this->log( '   ZIP: ' . MLBKP_Logger::format_bytes( $size ) );
        if ( $file_backup->get_skipped() ) {
            $this->log( '   ⚠ ' . count( $file_backup->get_skipped() ) . ' Datei(en) übersprungen.' );
        }

        $remote_path = $sftp->upload_to_session( $actual_path, $filename, $this->session['remote_session_dir'] );
        @unlink( $actual_path );

        return [
            'size'        => $size,
            'filename'    => $filename,
            'remote_path' => $remote_path,
        ];
    }

    private function process_directory_files_only( array $chunk, MLBKP_SFTP $sftp ): array {
        // Nur Dateien direkt im Verzeichnis (keine Unterverzeichnisse)
        $source   = $chunk['path'];
        $label    = $chunk['label'];
        $files    = glob( rtrim( $source, '/' ) . '/*' );
        $files    = array_filter( (array) $files, 'is_file' );

        if ( empty( $files ) ) {
            return [ 'size' => 0, 'filename' => null, 'remote_path' => null ];
        }

        $filename    = 'chunk-' . $this->sanitize_label( $label ) . '-' . gmdate( 'Y-m-d_H-i-s' ) . '.zip';
        $zip_path    = $this->temp_dir . $filename;

        $zip = new ZipArchive();
        $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE );

        $total_size = 0;
        foreach ( $files as $file ) {
            $fh = @fopen( $file, 'rb' );
            if ( ! $fh ) continue;
            fclose( $fh );
            $zip->addFile( $file, basename( $file ) );
            $total_size += filesize( $file );
        }

        $zip->close();

        // Imunify-Check
        if ( ! file_exists( $zip_path ) ) {
            $matches = glob( $zip_path . '.*' );
            if ( ! empty( $matches ) ) {
                @rename( $matches[0], $zip_path );
            }
        }

        if ( ! file_exists( $zip_path ) || filesize( $zip_path ) < 22 ) {
            return [ 'size' => 0, 'filename' => null, 'remote_path' => null ];
        }

        $remote_path = $sftp->upload_to_session( $zip_path, basename( $zip_path ), $this->session['remote_session_dir'] );
        @unlink( $zip_path );

        return [
            'size'        => $total_size,
            'filename'    => basename( $zip_path ),
            'remote_path' => $remote_path,
        ];
    }

    // ── Session abschließen ───────────────────────────────────────────────────

    private function finish_session(): void {
        // Prüfen ob alle Chunks erfolgreich waren
        $has_errors = false;
        foreach ( $this->session['chunks'] as $chunk ) {
            if ( $chunk['status'] === 'error' && $chunk['type'] === 'database' ) {
                $has_errors = true;
            }
        }

        $status = $has_errors ? 'error' : 'success';
        MLBKP_Session::finish( $this->session, $status );

        $this->log( $status === 'success'
            ? '🎉 Backup-Session abgeschlossen.'
            : '⚠ Backup abgeschlossen mit Fehlern.' );

        // Retention: alte Sessions auf Storage Box bereinigen
        try {
            $sftp      = new MLBKP_SFTP( $this->settings );
            $retention = (int) ( $this->settings['retention_count'] ?? 7 );
            $sftp->apply_retention_sessions( $retention );
        } catch ( \Throwable ) {}

        // E-Mail
        $this->maybe_send_notification( $status === 'success', $this->session['error_message'] ?? '' );

        // Temp-Verzeichnis dieser Session entfernen
        $this->cleanup_temp_dir();

        // Alte Sessions in wp_options bereinigen
        MLBKP_Session::cleanup_old_sessions( 20 );
    }

    // ── Hilfsmethoden ─────────────────────────────────────────────────────────

    private function prepare_temp_dir(): string {
        $base = $this->find_base_temp_dir();

        // Eigenes Unterverzeichnis pro Session — verhindert, dass Dateien dieser
        // Session von einem parallel laufenden Job (anderer Chunk-Runner oder
        // Legacy-Runner) gelöscht werden. Siehe BACKLOG.md.
        $dir = $base . 'session-' . $this->session['id'] . '/';

        if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
            // Fallback: gemeinsames Basisverzeichnis (sollte praktisch nie eintreten)
            return $base;
        }

        return $dir;
    }

    private function find_base_temp_dir(): string {
        $candidates = [
            WP_CONTENT_DIR . '/mlbkp-temp/',
            sys_get_temp_dir() . '/mlbkp-' . sanitize_key( parse_url( get_site_url(), PHP_URL_HOST ) ) . '/',
            WP_CONTENT_DIR . '/uploads/media-lab-backup/temp/',
        ];

        foreach ( $candidates as $dir ) {
            if ( is_dir( $dir ) || wp_mkdir_p( $dir ) ) {
                $test = $dir . '.write-test';
                if ( @file_put_contents( $test, '1' ) !== false ) {
                    @unlink( $test );
                    if ( ! file_exists( $dir . '.htaccess' ) ) {
                        file_put_contents( $dir . '.htaccess', "Order deny,allow\nDeny from all\n" );
                    }
                    return $dir;
                }
            }
        }

        return sys_get_temp_dir() . '/';
    }

    /**
     * Entfernt das Session-Temp-Verzeichnis inkl. evtl. verbliebener Dateien.
     * Läuft am Ende der Session bzw. bei Abbruch/Fehler — nicht nach jedem
     * einzelnen Chunk, da nachfolgende Chunks dasselbe Verzeichnis weiterverwenden.
     */
    private function cleanup_temp_dir(): void {
        if ( ! is_dir( $this->temp_dir ) ) return;

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $this->temp_dir, FilesystemIterator::SKIP_DOTS ),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ( $items as $item ) {
            $item->isDir() ? @rmdir( $item->getPathname() ) : @unlink( $item->getPathname() );
        }

        @rmdir( $this->temp_dir );
    }

    private function sanitize_label( string $label ): string {
        return preg_replace( '/[^a-z0-9\-_]/', '-', strtolower( $label ) );
    }

    private function log( string $message ): void {
        $this->log[] = '[' . gmdate( 'H:i:s' ) . '] ' . $message;
    }

    private function maybe_send_notification( bool $success, string $error ): void {
        $email  = trim( $this->settings['notify_email'] ?? '' );
        $notify = $this->settings['notify_on'] ?? 'error';

        if ( empty( $email ) || $notify === 'never' ) return;
        if ( $notify === 'error' && $success ) return;

        $subject = $success
            ? '[Media Lab Backup] ✅ Backup erfolgreich — ' . get_bloginfo( 'name' )
            : '[Media Lab Backup] ❌ Backup fehlgeschlagen — ' . get_bloginfo( 'name' );

        $body  = $success
            ? "Backup für " . get_site_url() . " erfolgreich.\n"
            : "Backup für " . get_site_url() . " fehlgeschlagen.\nFehler: {$error}\n";

        $chunks_info = array_map(
            static fn( $c ) => "  {$c['label']}: {$c['status']}" . ( $c['error'] ? " ({$c['error']})" : '' ),
            $this->session['chunks']
        );
        $body .= "\nChunks:\n" . implode( "\n", $chunks_info );

        wp_mail( $email, $subject, $body );
    }
}
