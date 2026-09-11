<?php
defined( 'ABSPATH' ) || exit;

/**
 * MLBKP_Restore_Runner
 *
 * Arbeitet eine Restore-Session Item für Item ab, analog zu
 * MLBKP_Chunk_Runner beim Backup. Läuft über WP-Cron in mehreren Ticks,
 * damit große Wiederherstellungen (v.a. DB-Import, viele Einzeldateien)
 * nicht an PHP-Timeouts auf Shared Hosting scheitern.
 *
 * SICHERHEIT: Vor jedem Überschreiben einer bestehenden Datei wird
 * automatisch eine Kopie nach wp-content/mlbkp-restore-safety/<restore_id>/
 * angelegt. Diese Kopien werden NICHT automatisch gelöscht — das obliegt
 * dem Benutzer, nachdem er sich vom Ergebnis überzeugt hat.
 */
class MLBKP_Restore_Runner {

    const CRON_HOOK             = 'mlbkp_restore_process';
    const MAX_SECONDS_PER_TICK  = 180; // 3 Min Zeitbudget pro Cron-Tick

    private array  $session;
    private array  $settings;
    private string $temp_dir;
    private string $safety_dir;

    public function __construct( array $session ) {
        $this->session    = $session;
        $this->settings   = mlbkp_get_settings();
        $this->temp_dir   = $this->prepare_temp_dir();
        $this->safety_dir = $session['safety_dir'] ?? ( WP_CONTENT_DIR . '/mlbkp-restore-safety/' . $session['id'] );
    }

    // ── Öffentliche API ───────────────────────────────────────────────────────

    public static function process( string $session_id ): void {
        $session = MLBKP_Restore_Session::load( $session_id );
        if ( ! $session || $session['status'] !== 'running' ) return;

        if ( get_option( 'mlbkp_restore_cancel_' . $session_id ) ) {
            delete_option( 'mlbkp_restore_cancel_' . $session_id );
            MLBKP_Restore_Session::finish( $session, 'cancelled' );
            MLBKP_Restore_Session::save( $session );
            return;
        }

        $runner = new self( $session );
        $runner->run_next_step();
    }

    /**
     * Plant den nächsten Tick und triggert WP-Cron aktiv — gleiches Muster
     * wie MLBKP_Scheduler::schedule_chunk(), notwendig auf Shared Hosting
     * ohne echten System-Cron (spawn_cron() stößt die Ausführung sofort an,
     * statt auf den nächsten Seitenaufruf zu warten).
     */
    public static function schedule( string $session_id ): void {
        wp_schedule_single_event( time() - 1, self::CRON_HOOK, [ $session_id ] );
        spawn_cron();
    }

    // ── Ablauf ────────────────────────────────────────────────────────────────

    private function run_next_step(): void {
        @set_time_limit( 0 );
        @ini_set( 'memory_limit', '512M' );

        $item = MLBKP_Restore_Session::get_next_item( $this->session );

        if ( ! $item ) {
            $this->finish_session();
            return;
        }

        if ( $item['status'] === 'pending' ) {
            MLBKP_Restore_Session::update_item( $this->session, $item['id'], [ 'status' => 'running' ] );
            $item = $this->find_item( $item['id'] );
        }

        $item_type_for_error_handling = $item['type'];

        try {
            $sftp = new MLBKP_SFTP( $this->settings );

            $finished = match ( $item['type'] ) {
                'db_full'     => $this->process_db( $item, $sftp, null ),
                'db_tables'   => $this->process_db( $item, $sftp, $item['tables'] ?? [] ),
                'chunk_full'  => $this->process_chunk( $item, $sftp, null ),
                'chunk_files' => $this->process_chunk( $item, $sftp, $item['files'] ?? [] ),
                default       => throw new RuntimeException( "Unbekannter Item-Typ: {$item['type']}" ),
            };

            if ( $finished ) {
                MLBKP_Restore_Session::update_item( $this->session, $item['id'], [ 'status' => 'done' ] );
            }
            // Wenn nicht fertig, bleibt Item 'running' — der Fortschritt
            // (Offset/Index) wurde in process_db()/process_chunk() bereits
            // per update_item() im Session-Array vermerkt.

        } catch ( \Throwable $e ) {
            MLBKP_Restore_Session::update_item( $this->session, $item['id'], [
                'status' => 'error',
                'error'  => $e->getMessage(),
            ] );

            // DB-Fehler sind kritisch — Session abbrechen, um keinen
            // halb-importierten Zustand unbemerkt als "läuft noch" zu zeigen.
            if ( str_starts_with( $item_type_for_error_handling, 'db_' ) ) {
                MLBKP_Restore_Session::finish( $this->session, 'error', $e->getMessage() );
                MLBKP_Restore_Session::save( $this->session );
                return;
            }
            // Datei-Items: Fehler bleibt am Item vermerkt, mit dem nächsten
            // Item wird trotzdem weitergemacht.
        }

        MLBKP_Restore_Session::save( $this->session );

        $next = MLBKP_Restore_Session::get_next_item( $this->session );
        if ( $next ) {
            self::schedule( $this->session['id'] );
        } else {
            $this->finish_session();
        }
    }

    private function finish_session(): void {
        $has_errors = (bool) array_filter( $this->session['items'], static fn( $i ) => $i['status'] === 'error' );
        MLBKP_Restore_Session::finish( $this->session, $has_errors ? 'error' : 'success' );
        MLBKP_Restore_Session::save( $this->session );
        MLBKP_Restore_Session::cleanup_old_sessions( 10 );
    }

    private function find_item( int $item_id ): array {
        foreach ( $this->session['items'] as $item ) {
            if ( $item['id'] === $item_id ) return $item;
        }
        throw new RuntimeException( "Item {$item_id} nicht gefunden." );
    }

    // ── Datenbank-Restore (voll oder selektiv nach Tabellen) ─────────────────

    /**
     * @return bool true wenn das Item vollständig abgeschlossen ist
     */
    private function process_db( array $item, MLBKP_SFTP $sftp, ?array $only_tables ): bool {
        // Erster Tick: Dump herunterladen + entpacken
        if ( empty( $item['sql_local_path'] ) ) {
            $gz_local = $this->temp_dir . 'restore-db-' . $item['id'] . '.sql.gz';
            $sftp->download( $item['remote_path'], $gz_local );

            $sql_local = $this->temp_dir . 'restore-db-' . $item['id'] . '.sql';
            $this->gunzip( $gz_local, $sql_local );
            @unlink( $gz_local );

            MLBKP_Restore_Session::update_item( $this->session, $item['id'], [
                'sql_local_path' => $sql_local,
                'offset'         => 0,
            ] );
            $item = $this->find_item( $item['id'] );
        }

        $result = MLBKP_SQL_Parser::import_batch(
            $item['sql_local_path'],
            $only_tables,
            (int) $item['offset'],
            (float) self::MAX_SECONDS_PER_TICK
        );

        $stats = array_merge( [ 'executed' => 0, 'skipped' => 0, 'errors' => [] ], $item['stats'] ?? [] );
        $stats['executed'] += $result['executed'];
        $stats['skipped']  += $result['skipped'];
        $stats['errors']    = array_slice( array_merge( $stats['errors'], $result['errors'] ), -20 );

        if ( $result['done'] ) {
            @unlink( $item['sql_local_path'] );
            MLBKP_Restore_Session::update_item( $this->session, $item['id'], [ 'stats' => $stats ] );
            return true;
        }

        MLBKP_Restore_Session::update_item( $this->session, $item['id'], [
            'offset' => $result['next_offset'],
            'stats'  => $stats,
        ] );
        return false;
    }

    // ── Datei/Verzeichnis-Restore (voll oder selektiv nach Dateien) ──────────

    private function process_chunk( array $item, MLBKP_SFTP $sftp, ?array $only_files ): bool {
        return $item['is_archive']
            ? $this->process_zip_chunk( $item, $sftp, $only_files )
            : $this->process_stream_chunk( $item, $sftp, $only_files );
    }

    private function process_zip_chunk( array $item, MLBKP_SFTP $sftp, ?array $only_files ): bool {
        // Erster Tick: ZIP herunterladen
        if ( empty( $item['zip_local_path'] ) ) {
            $zip_local = $this->temp_dir . 'restore-chunk-' . $item['id'] . '.zip';
            $sftp->download( $item['remote_path'], $zip_local );

            MLBKP_Restore_Session::update_item( $this->session, $item['id'], [
                'zip_local_path' => $zip_local,
                'extract_index'  => 0,
            ] );
            $item = $this->find_item( $item['id'] );
        }

        $zip = new ZipArchive();
        if ( $zip->open( $item['zip_local_path'] ) !== true ) {
            throw new RuntimeException( "ZIP konnte nicht geöffnet werden: {$item['zip_local_path']}" );
        }

        if ( $only_files === null ) {
            $entries = [];
            for ( $i = 0; $i < $zip->numFiles; $i++ ) {
                $name = $zip->getNameIndex( $i );
                if ( $name !== false && ! str_ends_with( $name, '/' ) ) $entries[] = $name;
            }
        } else {
            $entries = $only_files;
        }

        $start    = (int) ( $item['extract_index'] ?? 0 );
        $deadline = microtime( true ) + self::MAX_SECONDS_PER_TICK;
        $restored = $item['stats']['files_restored'] ?? 0;
        $i        = $start;

        for ( ; $i < count( $entries ); $i++ ) {
            if ( microtime( true ) >= $deadline ) break;

            $relative = ltrim( $entries[ $i ], '/' );
            if ( $zip->locateName( $relative ) === false ) continue;

            $target = rtrim( $item['target_path'], '/' ) . '/' . $relative;
            $this->safety_backup_file( $target, $relative, $item );

            wp_mkdir_p( dirname( $target ) );
            $content = $zip->getFromName( $relative );
            if ( $content === false ) continue;

            file_put_contents( $target, $content );
            $restored++;
        }

        $zip->close();

        $done = $i >= count( $entries );

        MLBKP_Restore_Session::update_item( $this->session, $item['id'], [
            'extract_index' => $i,
            'stats'         => array_merge( $item['stats'] ?? [], [ 'files_restored' => $restored ] ),
        ] );

        if ( $done ) @unlink( $item['zip_local_path'] );

        return $done;
    }

    private function process_stream_chunk( array $item, MLBKP_SFTP $sftp, ?array $only_files ): bool {
        // Liste der relativen Dateien einmalig ermitteln
        if ( empty( $item['file_list'] ) ) {
            $relative_list = $only_files !== null
                ? $only_files
                : array_column( $sftp->list_entries_recursive( $item['remote_path'] ), 'relative' );

            MLBKP_Restore_Session::update_item( $this->session, $item['id'], [
                'file_list'     => $relative_list,
                'restore_index' => 0,
            ] );
            $item = $this->find_item( $item['id'] );
        }

        $files    = $item['file_list'];
        $start    = (int) ( $item['restore_index'] ?? 0 );
        $deadline = microtime( true ) + self::MAX_SECONDS_PER_TICK;
        $restored = $item['stats']['files_restored'] ?? 0;
        $i        = $start;

        for ( ; $i < count( $files ); $i++ ) {
            if ( microtime( true ) >= $deadline ) break;

            $relative = ltrim( $files[ $i ], '/' );
            $remote   = rtrim( $item['remote_path'], '/' ) . '/' . $relative;
            $target   = rtrim( $item['target_path'], '/' ) . '/' . $relative;

            $this->safety_backup_file( $target, $relative, $item );
            wp_mkdir_p( dirname( $target ) );

            try {
                $sftp->download( $remote, $target );
                $restored++;
            } catch ( \Throwable $e ) {
                // einzelne Datei übersprungen, Rest läuft weiter
            }
        }

        $done = $i >= count( $files );

        MLBKP_Restore_Session::update_item( $this->session, $item['id'], [
            'restore_index' => $i,
            'stats'         => array_merge( $item['stats'] ?? [], [ 'files_restored' => $restored ] ),
        ] );

        return $done;
    }

    // ── Sicherheitskopie ──────────────────────────────────────────────────────

    /**
     * Sichert eine bestehende Datei vor dem Überschreiben nach
     * wp-content/mlbkp-restore-safety/<restore_id>/<namespace>/<relativer_pfad>.
     * Kein Fehler, wenn die Datei noch nicht existiert (Neuanlage).
     *
     * Der Namespace wird bewusst aus dem ÜBERGEORDNETEN Teil des Item-Labels
     * gebildet (nicht dem vollen Label) und mit der Item-ID kombiniert. Grund:
     * Bei ZIP-Chunks trägt der relative Pfad innerhalb des Archivs den
     * Ordnernamen selbst schon als Präfix (Label "wp-content/plugins/
     * wp-fastest-cache" + Zip-interner Pfad "wp-fastest-cache/index.html") —
     * würde man das volle Label als Namespace verwenden, entstünde eine
     * optisch verwirrende (wenn auch technisch unschädliche) Verdopplung
     * ".../wp-content-plugins-wp-fastest-cache/wp-fastest-cache/index.html".
     * Die Item-ID sorgt zusätzlich für Eindeutigkeit, falls mehrere Items
     * denselben übergeordneten Pfad teilen (z.B. mehrere Plugin-Chunks
     * landen alle unter "wp-content/plugins").
     */
    private function safety_backup_file( string $target_path, string $relative, array $item ): void {
        if ( ! file_exists( $target_path ) ) return;

        $namespace_source = dirname( $item['label'] );
        if ( $namespace_source === '.' || $namespace_source === '' ) $namespace_source = $item['label'];

        $namespace = $item['id'] . '_' . preg_replace( '/[^a-z0-9\-_]/', '-', strtolower( $namespace_source ) );
        $backup_to = rtrim( $this->safety_dir, '/' ) . '/' . $namespace . '/' . $relative;

        wp_mkdir_p( dirname( $backup_to ) );
        @copy( $target_path, $backup_to );
    }

    // ── Hilfsmethoden ─────────────────────────────────────────────────────────

    private function gunzip( string $gz_path, string $target_path ): void {
        $in  = @gzopen( $gz_path, 'rb' );
        $out = @fopen( $target_path, 'wb' );
        if ( ! $in || ! $out ) {
            throw new RuntimeException( "Entpacken fehlgeschlagen: {$gz_path}" );
        }
        while ( ! gzeof( $in ) ) {
            fwrite( $out, gzread( $in, 1048576 ) );
        }
        gzclose( $in );
        fclose( $out );
    }

    private function prepare_temp_dir(): string {
        $dir = WP_CONTENT_DIR . '/mlbkp-temp/restore/';
        if ( ! is_dir( $dir ) ) wp_mkdir_p( $dir );
        return $dir;
    }
}
