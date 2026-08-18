<?php
defined( 'ABSPATH' ) || exit;

/**
 * MLBKP_File_Backup
 *
 * Erstellt ZIP-Archive von WordPress-Verzeichnissen.
 * Unterstützt: wp-content, vollständiges WP-Verzeichnis.
 *
 * Speicher-Optimierungen für Shared Hosting:
 *
 *  1. ZIP_COMPRESSION_METHOD = ZipArchive::CM_STORE (keine Komprimierung):
 *     Komprimierung spart bei bereits komprimierten Medien (JPEG, PNG, MP4)
 *     kaum Speicher, verbraucht aber viel CPU und Zeit. CM_STORE ist 3–10×
 *     schneller und vermeidet PHP-Speicher-Erschöpfung bei großen Verzeichnissen.
 *
 *  2. Periodisches gc_collect_cycles() + memory_get_usage()-Check:
 *     ZipArchive puffert intern — bei tausenden Dateien kann das kumulativ
 *     viel Speicher belegen. Alle MLBKP_ZIP_FLUSH_EVERY Dateien wird der
 *     Garbage Collector explizit aufgerufen.
 *
 *  3. Einzelne Dateien > 500 MB werden übersprungen (unverändert).
 */
class MLBKP_File_Backup {

    private string $temp_dir;
    private int    $log_id;
    private array  $skipped = [];

    /**
     * ZIP-Komprimierungsmethode.
     * CM_STORE (0) = unkomprimiert, deutlich schneller auf Shared Hosting.
     * CM_DEFLATE  = Standard-Komprimierung (langsamer, kleiner bei Text/PHP-Dateien).
     */
    private const ZIP_COMPRESSION_METHOD = ZipArchive::CM_STORE;

    /**
     * Alle N Dateien Garbage Collector aufrufen + Abbruch prüfen.
     */
    private const GC_EVERY_FILES = 500;

    /** Standardmäßig ausgeschlossene Pfade (relativ zum Quellverzeichnis) */
    private array $default_excludes = [
        'wp-content/cache',
        'wp-content/uploads/media-lab-backup',
        'wp-content/wpo-cache',
        'wp-content/litespeed',
        '.git',
        '.DS_Store',
        'node_modules',
        '.sass-cache',
        'wp-content/upgrade',
        // Imunify360 — gesperrte Quarantäne-Dateien (chmod 000), blockieren addFile()
        '.quarantine',
        '_imunify',
        'imunify-antivirus',
    ];

    public function __construct( string $temp_dir, int $log_id = 0 ) {
        $this->temp_dir = $temp_dir;
        $this->log_id   = $log_id;
    }

    public function get_skipped(): array {
        return $this->skipped;
    }

    /**
     * Erstellt ein ZIP-Archiv des angegebenen Verzeichnisses.
     *
     * @param string $type          'wpcontent' | 'wpcore'
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

        $excludes = array_merge( $this->default_excludes, $extra_excludes );
        $excludes = array_map( static fn( $e ) => rtrim( $e, '/' ), $excludes );

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

    // ── ZIP-Erstellung ────────────────────────────────────────────────────────

    /**
     * @throws RuntimeException
     */
    private function create_zip( string $source, string $zip_path, array $excludes ): void {
        $zip = new ZipArchive();

        if ( $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) !== true ) {
            throw new RuntimeException( "Konnte ZIP-Datei nicht erstellen: {$zip_path}" );
        }

        $base_name  = basename( $source );
        $file_count = 0;

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $source,
                    FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS
                ),
                RecursiveIteratorIterator::SELF_FIRST
            );
        } catch ( \UnexpectedValueException $e ) {
            throw new RuntimeException( "Quellverzeichnis nicht lesbar (open_basedir?): {$source} — " . $e->getMessage() );
        }

        foreach ( $iterator as $file ) {
            try {
                $file_path = $file->getPathname();
                $relative  = substr( $file_path, strlen( $source ) + 1 );
                $zip_entry = $base_name . '/' . $relative;
            } catch ( \UnexpectedValueException $e ) {
                // open_basedir oder Berechtigungsfehler beim Verzeichnis-Listing
                $this->skipped[] = '(Verzeichnis nicht lesbar: ' . $e->getMessage() . ')';
                continue;
            }

            // Ausschlüsse prüfen
            if ( $this->should_exclude( $relative, $excludes ) ) {
                if ( $file->isDir() ) {
                    $iterator->getInnerIterator()->current();
                }
                continue;
            }

            if ( $file->isDir() ) {
                $zip->addEmptyDir( $zip_entry );

            } elseif ( $file->isFile() ) {

                // ── Lesbarkeit doppelt prüfen ─────────────────────────────────
                // isReadable() allein reicht nicht — auf manchen Systemen (Plesk/
                // Imunify360) gibt es true zurück, aber addFile() hängt trotzdem.
                // fopen() schlägt sofort fehl wenn die Datei wirklich nicht lesbar ist.
                if ( ! $file->isReadable() ) {
                    $this->skipped[] = $relative . ' (nicht lesbar)';
                    continue;
                }

                $fh = @fopen( $file_path, 'rb' );
                if ( $fh === false ) {
                    $this->skipped[] = $relative . ' (fopen fehlgeschlagen)';
                    continue;
                }
                fclose( $fh );

                // Sehr große Einzeldateien (>500 MB) überspringen
                if ( $file->getSize() > 524288000 ) {
                    $zip->addFromString(
                        $zip_entry . '.skipped',
                        "Diese Datei wurde übersprungen (>500 MB): " . $file_path
                    );
                    $this->skipped[] = $relative . ' (>500 MB)';
                    continue;
                }

                $zip->addFile( $file_path, $zip_entry );

                // Komprimierungsmethode setzen: CM_STORE = kein Overhead
                $zip->setCompressionName( $zip_entry, self::ZIP_COMPRESSION_METHOD );

                $file_count++;

                // Periodisch Speicher freigeben + Abbruch prüfen
                if ( $file_count % self::GC_EVERY_FILES === 0 ) {
                    gc_collect_cycles();

                    if ( $this->log_id > 0 && MLBKP_Logger::is_cancelled( $this->log_id ) ) {
                        $zip->close();
                        @unlink( $zip_path );
                        throw new MLBKP_CancelledException();
                    }
                }
            }
        }

        $zip->close();
    }

    /**
     * Prüft ob ein relativer Pfad von der Sicherung ausgeschlossen werden soll.
     */
    private function should_exclude( string $relative_path, array $excludes ): bool {
        foreach ( $excludes as $exclude ) {
            if ( $relative_path === $exclude || str_starts_with( $relative_path, $exclude . '/' ) ) {
                return true;
            }
            if ( ! str_contains( $exclude, '/' ) && basename( $relative_path ) === $exclude ) {
                return true;
            }
        }
        return false;
    }

    // ── Utilities ─────────────────────────────────────────────────────────────

    /**
     * Schätzt die Größe eines Verzeichnisses (rekursiv, schnell).
     * Für Anzeige im Admin-Bereich.
     */
    public static function estimate_size( string $dir ): int {
        $size = 0;
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS )
            );
            foreach ( $iterator as $file ) {
                if ( $file->isFile() ) {
                    $size += $file->getSize();
                }
            }
        } catch ( \Throwable ) {
            // Ignore permission errors
        }
        return $size;
    }
}
