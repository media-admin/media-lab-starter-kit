<?php
defined( 'ABSPATH' ) || exit;

/**
 * MLBKP_Session
 *
 * Verwaltet Backup-Sessions und Chunk-Queues.
 * Daten werden als JSON in wp_options gespeichert.
 */
class MLBKP_Session {

    const OPTION_PREFIX   = 'mlbkp_sess_';
    const INDEX_OPTION    = 'mlbkp_session_index';

    // Verzeichnisse die immer übersprungen werden beim Chunk-Scan
    const SKIP_DIRS = [
        'cache', 'wpo-cache', 'litespeed', 'upgrade',
        'mlbkp-temp', '.quarantine', '_imunify', 'imunify-antivirus',
        '.git', 'node_modules', '.sass-cache',
    ];

    // ── Factory ───────────────────────────────────────────────────────────────

    /**
     * Erstellt eine neue Session, scannt Chunks und speichert sie.
     */
    public static function create( string $type, string $triggered_by, array $settings ): array {
        $id          = 'sess_' . gmdate( 'Ymd_His' ) . '_' . substr( uniqid(), -4 );
        $log_id      = MLBKP_Logger::start( $type, $triggered_by );
        $file_method = $settings['backup_file_method'] ?? 'zip';
        $excludes    = array_filter( array_map( 'trim', explode( "\n", $settings['exclude_paths'] ?? '' ) ) );

        $chunks = self::build_chunk_list( $type, $settings, $excludes );

        $session = [
            'id'                 => $id,
            'log_id'             => $log_id,
            'type'               => $type,
            'triggered_by'       => $triggered_by,
            'status'             => 'running',
            'file_method'        => $file_method,
            'started_at'         => current_time( 'mysql' ),
            'finished_at'        => null,
            'remote_session_dir' => '',
            'chunks'             => $chunks,
            'chunks_total'       => count( $chunks ),
            'chunks_done'        => 0,
            'total_size'         => 0,
            'error_message'      => null,
            'excludes'           => $excludes,
        ];

        self::save( $session );
        self::add_to_index( $id );

        return $session;
    }

    // ── CRUD ──────────────────────────────────────────────────────────────────

    public static function load( string $id ): ?array {
        $data = get_option( self::OPTION_PREFIX . $id );
        return $data ?: null;
    }

    public static function save( array $session ): void {
        update_option( self::OPTION_PREFIX . $session['id'], $session, false );
    }

    public static function delete( string $id ): void {
        delete_option( self::OPTION_PREFIX . $id );
        self::remove_from_index( $id );
    }

    // ── Chunk-Verwaltung ──────────────────────────────────────────────────────

    public static function get_next_chunk( array $session ): ?array {
        foreach ( $session['chunks'] as $chunk ) {
            if ( $chunk['status'] === 'pending' ) return $chunk;
        }
        return null;
    }

    public static function update_chunk( array &$session, int $chunk_id, array $data ): void {
        foreach ( $session['chunks'] as &$chunk ) {
            if ( $chunk['id'] === $chunk_id ) {
                $chunk = array_merge( $chunk, $data );
                break;
            }
        }
        unset( $chunk );

        // Gesamtgröße aktualisieren
        $session['total_size']  = array_sum( array_column( $session['chunks'], 'size' ) );
        $session['chunks_done'] = count( array_filter( $session['chunks'], static fn( $c ) => in_array( $c['status'], [ 'done', 'error', 'skipped' ], true ) ) );
    }

    public static function finish( array &$session, string $status, string $error = '' ): void {
        $session['status']      = $status;
        $session['finished_at'] = current_time( 'mysql' );
        if ( $error ) $session['error_message'] = $error;

        // Log-Eintrag abschließen
        $filenames = array_filter( array_column( $session['chunks'], 'filename' ) );
        MLBKP_Logger::finish( $session['log_id'], $status, [
            'file_name'   => self::truncate_filenames( $filenames ),
            'file_size'   => $session['total_size'],
            'remote_path' => $session['remote_session_dir'],
            'error_message' => $error ?: null,
        ] );

        self::save( $session );
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public static function get_index(): array {
        return get_option( self::INDEX_OPTION, [] );
    }

    /**
     * Findet die aktuell laufende Session, falls vorhanden. Wird beim
     * Seitenaufruf des "Backup starten"-Tabs genutzt, um das Live-Protokoll
     * nach einem Tab-Wechsel/Reload automatisch fortzusetzen.
     */
    public static function find_running(): ?array {
        foreach ( array_reverse( self::get_index() ) as $id ) {
            $session = self::load( $id );
            if ( $session && $session['status'] === 'running' ) {
                return $session;
            }
        }
        return null;
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

    // ── Chunk-Liste erstellen ─────────────────────────────────────────────────

    /**
     * Scannt das Dateisystem und erstellt eine optimale Chunk-Liste.
     */
    private static function build_chunk_list( string $type, array $settings, array $excludes ): array {
        $chunks = [];
        $id     = 0;

        // Datenbank
        if ( in_array( $type, [ 'database', 'full' ], true ) && ! empty( $settings['backup_database'] ) ) {
            $chunks[] = self::make_chunk( $id++, 'database', 'Datenbank', null );
        }

        // wp-content Dateien
        if ( in_array( $type, [ 'wpcontent', 'full' ], true ) && ! empty( $settings['backup_wpcontent'] ) ) {
            $chunks = array_merge( $chunks, self::scan_wpcontent_chunks( $id, $excludes ) );
            $id     = count( $chunks );
        }

        // WP-Core
        if ( $type === 'wpcore' && ! empty( $settings['backup_wpcore'] ) ) {
            $chunks = array_merge( $chunks, self::scan_wpcore_chunks( $id, $excludes ) );
        }

        return $chunks;
    }

    private static function scan_wpcontent_chunks( int &$id, array $excludes ): array {
        $chunks  = [];
        $base    = WP_CONTENT_DIR;
        $entries = @scandir( $base );
        if ( ! $entries ) return [];

        foreach ( $entries as $entry ) {
            if ( in_array( $entry, array_merge( [ '.', '..' ], self::SKIP_DIRS ), true ) ) continue;
            if ( ! is_dir( $base . '/' . $entry ) ) continue;

            // Ausschlüsse prüfen
            if ( self::is_excluded( $entry, $excludes ) ) continue;

            if ( $entry === 'uploads' ) {
                // uploads/ nach Unterverzeichnissen splitten
                $sub_chunks = self::scan_uploads_chunks( $id, $base . '/uploads', $excludes );
                $chunks     = array_merge( $chunks, $sub_chunks );
                $id        += count( $sub_chunks );
            } elseif ( $entry === 'plugins' ) {
                // plugins/ nach einzelnen Plugins splitten (können sehr groß sein)
                $sub_chunks = self::scan_subdir_chunks( $id, $base . '/plugins', 'wp-content/plugins', $excludes );
                $chunks     = array_merge( $chunks, $sub_chunks );
                $id        += count( $sub_chunks );
            } elseif ( $entry === 'themes' ) {
                // themes/ nach einzelnen Themes splitten
                $sub_chunks = self::scan_subdir_chunks( $id, $base . '/themes', 'wp-content/themes', $excludes );
                $chunks     = array_merge( $chunks, $sub_chunks );
                $id        += count( $sub_chunks );
            } else {
                // Alle anderen Top-Level-Verzeichnisse: je ein Chunk
                $size     = self::estimate_dir_size( $base . '/' . $entry );
                $chunks[] = self::make_chunk( $id++, 'dir', "wp-content/{$entry}", $base . '/' . $entry, $size );
            }
        }

        return $chunks;
    }

    /**
     * Splittet ein Verzeichnis nach seinen direkten Unterverzeichnissen.
     * Für plugins/ und themes/ um Timeouts zu vermeiden.
     */
    private static function scan_subdir_chunks( int &$id, string $dir, string $label_prefix, array $excludes ): array {
        $chunks  = [];
        $entries = @scandir( $dir );
        if ( ! $entries ) return [];

        $has_root_files = false;

        foreach ( $entries as $entry ) {
            if ( in_array( $entry, [ '.', '..' ], true ) ) continue;

            $full_path = $dir . '/' . $entry;

            if ( is_dir( $full_path ) ) {
                if ( in_array( $entry, self::SKIP_DIRS, true ) ) continue;
                if ( self::is_excluded( "{$label_prefix}/{$entry}", $excludes ) ) continue;

                $size     = self::estimate_dir_size( $full_path );
                $chunks[] = self::make_chunk( $id++, 'dir', "{$label_prefix}/{$entry}", $full_path, $size );
            } else {
                $has_root_files = true;
            }
        }

        // Dateien direkt im Verzeichnis (z.B. plugins/index.php)
        if ( $has_root_files ) {
            $chunks[] = self::make_chunk( $id++, 'dir_files_only', "{$label_prefix} (Dateien)", $dir );
        }

        return $chunks;
    }

    private static function scan_uploads_chunks( int &$id, string $uploads_dir, array $excludes ): array {
        $chunks  = [];
        $entries = @scandir( $uploads_dir );
        if ( ! $entries ) return [];

        $has_root_files = false;

        foreach ( $entries as $entry ) {
            if ( in_array( $entry, [ '.', '..' ], true ) ) continue;

            $full_path = $uploads_dir . '/' . $entry;

            if ( is_dir( $full_path ) ) {
                if ( in_array( $entry, self::SKIP_DIRS, true ) ) continue;
                if ( self::is_excluded( "uploads/{$entry}", $excludes ) ) continue;

                // Jahres-Ordner (z.B. 2021, 2022) → nach Monaten aufteilen
                if ( preg_match( '/^\d{4}$/', $entry ) ) {
                    $month_chunks = self::scan_year_by_month( $id, $full_path, "wp-content/uploads/{$entry}", $excludes );
                    if ( ! empty( $month_chunks ) ) {
                        $chunks = array_merge( $chunks, $month_chunks );
                        $id    += count( $month_chunks );
                        continue;
                    }
                }

                $size     = self::estimate_dir_size( $full_path );
                $chunks[] = self::make_chunk( $id++, 'dir', "wp-content/uploads/{$entry}", $full_path, $size );
            } else {
                $has_root_files = true;
            }
        }

        // Dateien direkt in uploads/ (nicht in Unterverzeichnissen)
        if ( $has_root_files && ! self::is_excluded( 'uploads', $excludes ) ) {
            $chunks[] = self::make_chunk( $id++, 'dir_files_only', 'wp-content/uploads (Dateien)', $uploads_dir );
        }

        return $chunks;
    }

    /**
     * Splittet einen Jahres-Ordner nach Monaten auf (WordPress-Standard: YYYY/MM/).
     * Falls keine Monats-Unterordner vorhanden, wird null zurückgegeben.
     */
    private static function scan_year_by_month( int &$id, string $year_dir, string $label_prefix, array $excludes ): array {
        $entries = @scandir( $year_dir );
        if ( ! $entries ) return [];

        $chunks         = [];
        $has_root_files = false;
        $has_month_dirs = false;

        foreach ( $entries as $entry ) {
            if ( in_array( $entry, [ '.', '..' ], true ) ) continue;
            $full_path = $year_dir . '/' . $entry;

            if ( is_dir( $full_path ) && preg_match( '/^\d{2}$/', $entry ) ) {
                // Monats-Ordner (01-12)
                $has_month_dirs = true;
                if ( self::is_excluded( "{$label_prefix}/{$entry}", $excludes ) ) continue;
                $size     = self::estimate_dir_size( $full_path );
                $chunks[] = self::make_chunk( $id++, 'dir', "{$label_prefix}/{$entry}", $full_path, $size );
            } elseif ( is_dir( $full_path ) ) {
                // Nicht-Monats-Unterordner → eigener Chunk
                $size     = self::estimate_dir_size( $full_path );
                $chunks[] = self::make_chunk( $id++, 'dir', "{$label_prefix}/{$entry}", $full_path, $size );
            } else {
                $has_root_files = true;
            }
        }

        // Wenn keine Monats-Ordner → leere Liste zurückgeben (Fallback auf Jahres-Chunk)
        if ( ! $has_month_dirs ) return [];

        // Dateien direkt im Jahres-Ordner
        if ( $has_root_files ) {
            $chunks[] = self::make_chunk( $id++, 'dir_files_only', "{$label_prefix} (Dateien)", $year_dir );
        }

        return $chunks;
    }

    private static function scan_wpcore_chunks( int &$id, array $excludes ): array {
        $chunks  = [];
        $base    = rtrim( ABSPATH, '/' );
        $entries = @scandir( $base );
        if ( ! $entries ) return [];

        foreach ( $entries as $entry ) {
            if ( in_array( $entry, [ '.', '..', 'wp-content' ], true ) ) continue;
            if ( in_array( $entry, self::SKIP_DIRS, true ) ) continue;
            if ( self::is_excluded( $entry, $excludes ) ) continue;

            $full_path = $base . '/' . $entry;

            if ( is_dir( $full_path ) ) {
                $chunks[] = self::make_chunk( $id++, 'dir', "wpcore/{$entry}", $full_path );
            } else {
                // PHP-Dateien im Root als eigener Chunk
                if ( ! isset( $root_files_chunk ) ) {
                    $root_files_chunk = self::make_chunk( $id++, 'dir_files_only', 'wpcore (Root-Dateien)', $base );
                    $chunks[]         = $root_files_chunk;
                }
            }
        }

        return $chunks;
    }

    // ── Hilfsmethoden ─────────────────────────────────────────────────────────

    private static function make_chunk( int $id, string $type, string $label, ?string $path, int $estimated_size = 0 ): array {
        return [
            'id'             => $id,
            'type'           => $type,   // database | dir | dir_files_only
            'label'          => $label,
            'path'           => $path,
            'status'         => 'pending', // pending | running | done | error | skipped
            'size'           => 0,
            'estimated_size' => $estimated_size,
            'filename'       => null,
            'remote_path'    => null,
            'error'          => null,
        ];
    }

    private static function estimate_dir_size( string $dir ): int {
        // Schnelle Schätzung via du wenn verfügbar
        if ( function_exists( 'shell_exec' ) ) {
            $output = @shell_exec( 'du -sb ' . escapeshellarg( $dir ) . ' 2>/dev/null' );
            if ( $output && preg_match( '/^(\d+)/', $output, $m ) ) {
                return (int) $m[1];
            }
        }
        return 0;
    }

    private static function is_excluded( string $path, array $excludes ): bool {
        foreach ( $excludes as $exclude ) {
            if ( $path === $exclude || str_starts_with( $path, $exclude . '/' ) ) return true;
        }
        return false;
    }

    /**
     * Kürzt die Dateinamen-Liste auf max. 250 Zeichen (VARCHAR 255 Limit).
     */
    private static function truncate_filenames( array $filenames ): string {
        $result = implode( ', ', $filenames );
        if ( strlen( $result ) <= 250 ) return $result;
        // Zu lang: ersten Dateinamen + Anzahl restliche
        $first = reset( $filenames );
        $rest  = count( $filenames ) - 1;
        return mb_strimwidth( $first, 0, 200, '…' ) . " (+{$rest} weitere)";
    }

}
