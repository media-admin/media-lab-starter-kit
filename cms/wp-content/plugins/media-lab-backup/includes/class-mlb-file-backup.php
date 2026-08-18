<?php
defined( 'ABSPATH' ) || exit;

/**
 * MLBKP_File_Backup
 *
 * Erstellt ZIP-Archive von WordPress-Verzeichnissen.
 * Überspringt unlesbare Dateien (Imunify360-Quarantäne, open_basedir etc.)
 * und prüft zwischen Dateien auf Abbruch-Signal.
 */
class MLBKP_File_Backup {

    private string $temp_dir;
    private int    $log_id;
    private array  $skipped = [];

    /** Standardmäßig ausgeschlossene Pfade */
    private array $default_excludes = [
        'cache',
        'wpo-cache',
        'litespeed',
        'uploads/media-lab-backup',
        '.quarantine',         // Imunify360 Quarantäne-Verzeichnis
        '_imunify',           // Imunify360 interne Dateien
        'upgrade',
        '.git',
        '.DS_Store',
        'node_modules',
        '.sass-cache',
    ];

    public function __construct( string $temp_dir, int $log_id = 0 ) {
        $this->temp_dir = $temp_dir;
        $this->log_id   = $log_id;
    }

    /**
     * Erstellt ein ZIP-Archiv des angegebenen Verzeichnisses.
     *
     * @param string $type            'wpcontent' | 'wpcore'
     * @param array  $extra_excludes  Zusätzliche auszuschließende Pfade
     * @return array{path: string, filename: string, size: int, skipped: int}
     * @throws RuntimeException
     */
    public function create( string $type, array $extra_excludes = [] ): array {
        if ( ! class_exists( 'ZipArchive' ) ) {
            throw new RuntimeException( 'ZipArchive PHP-Extension ist nicht verfügbar.' );
        }

        [ $source_dir, $label ] = match ( $type ) {
            'wpcontent' => [ WP_CONTENT_DIR, 'wpcontent' ],
            'wpcore'    => [ ABSPATH,         'wpcore' ],
            default     => throw new RuntimeException( "Unbekannter Backup-Typ: {$type}" ),
        };

        $source_dir = rtrim( $source_dir, '/' );
        $filename   = "files-{$label}-" . gmdate( 'Y-m-d_H-i-s' ) . '.zip';
        $zip_path   = $this->temp_dir . $filename;

        $excludes = array_merge(
            $this->default_excludes,
            array_map( static fn( $e ) => rtrim( $e, '/' ), $extra_excludes )
        );

        $this->create_zip( $source_dir, $zip_path, $excludes );

        if ( ! file_exists( $zip_path ) ) {
            throw new RuntimeException( "ZIP-Datei konnte nicht erstellt werden: {$zip_path}" );
        }

        return [
            'path'     => $zip_path,
            'filename' => $filename,
            'size'     => filesize( $zip_path ),
            'skipped'  => count( $this->skipped ),
        ];
    }

    public function get_skipped(): array {
        return $this->skipped;
    }

    // ── ZIP-Erstellung ────────────────────────────────────────────────────────

    private function create_zip( string $source, string $zip_path, array $excludes ): void {
        $zip = new ZipArchive();

        if ( $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) !== true ) {
            throw new RuntimeException( "Konnte ZIP-Datei nicht öffnen: {$zip_path}" );
        }

        $base_name   = basename( $source );
        $file_count  = 0;
        $cancel_check_every = 500; // Alle 500 Dateien auf Abbruch prüfen

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $source,
                FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS
            ),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ( $iterator as $file ) {
            $file_path = $file->getPathname();
            $relative  = substr( $file_path, strlen( $source ) + 1 );
            $zip_entry = $base_name . '/' . $relative;

            // Ausschlüsse prüfen
            if ( $this->should_exclude( $relative, $excludes ) ) {
                continue;
            }

            if ( $file->isDir() ) {
                $zip->addEmptyDir( $zip_entry );
                continue;
            }

            if ( ! $file->isFile() ) continue;

            // ── Sicherheitschecks vor addFile() ──────────────────────────────

            // 1. Lesbarkeit prüfen (Imunify-Quarantäne, chmod 000)
            if ( ! $file->isReadable() ) {
                $this->skipped[] = $relative . ' (nicht lesbar)';
                continue;
            }

            // 2. Dateigröße prüfen
            $size = $file->getSize();
            if ( $size === false ) {
                $this->skipped[] = $relative . ' (Größe nicht ermittelbar)';
                continue;
            }

            // 3. Sehr große Einzeldateien überspringen (>500MB)
            if ( $size > 524288000 ) {
                $this->skipped[] = $relative . ' (>500MB, übersprungen)';
                $zip->addFromString( $zip_entry . '.skipped.txt', "Datei übersprungen (>500MB): {$file_path}" );
                continue;
            }

            // Datei hinzufügen
            $zip->addFile( $file_path, $zip_entry );
            $file_count++;

            // ── Abbruch-Check alle N Dateien ──────────────────────────────────
            if ( $this->log_id > 0 && $file_count % $cancel_check_every === 0 ) {
                if ( MLBKP_Logger::is_cancelled( $this->log_id ) ) {
                    $zip->close();
                    @unlink( $zip_path );
                    throw new MLBKP_CancelledException();
                }
            }
        }

        $zip->close();

        // Sicherstellen dass ZIP nicht leer/korrupt ist
        if ( ! file_exists( $zip_path ) || filesize( $zip_path ) < 22 ) {
            throw new RuntimeException( "ZIP-Datei ist leer oder korrupt: {$zip_path}" );
        }
    }

    // ── Hilfsmethoden ─────────────────────────────────────────────────────────

    private function should_exclude( string $relative_path, array $excludes ): bool {
        foreach ( $excludes as $exclude ) {
            if ( $relative_path === $exclude ) return true;
            if ( str_starts_with( $relative_path, $exclude . '/' ) ) return true;
            if ( ! str_contains( $exclude, '/' ) && basename( $relative_path ) === $exclude ) return true;
        }
        return false;
    }

    public static function estimate_size( string $dir ): int {
        $size = 0;
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS )
            );
            foreach ( $iterator as $file ) {
                if ( $file->isFile() && $file->isReadable() ) {
                    $size += $file->getSize();
                }
            }
        } catch ( \Throwable ) {}
        return $size;
    }
}
