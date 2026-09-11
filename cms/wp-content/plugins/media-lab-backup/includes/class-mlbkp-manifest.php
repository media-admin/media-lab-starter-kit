<?php
defined( 'ABSPATH' ) || exit;

/**
 * MLBKP_Manifest
 *
 * Schreibt und liest die manifest.json einer Backup-Session.
 * Die Manifest-Datei macht Chunks für das Restore-Feature maschinenlesbar
 * (Label, Typ, Originalpfad, Dateiname) statt sie aus sanitized Dateinamen
 * zurückrechnen zu müssen.
 *
 * Integration: write_for_session() wird am Ende von
 * MLBKP_Chunk_Runner::finish_session() aufgerufen — siehe PATCHES.md.
 */
class MLBKP_Manifest {

    const FILENAME = 'manifest.json';
    const VERSION  = 1;

    /**
     * Baut das Manifest aus einer abgeschlossenen Backup-Session und lädt es
     * in das Session-Verzeichnis hoch. Fehler werden bewusst verschluckt —
     * ein fehlgeschlagener Manifest-Upload darf niemals das eigentliche
     * Backup als "error" markieren.
     */
    public static function write_for_session( array $session, MLBKP_SFTP $sftp ): void {
        if ( empty( $session['remote_session_dir'] ) || empty( $session['chunks'] ) ) return;

        $manifest = [
            'manifest_version' => self::VERSION,
            'session_id'        => $session['id'] ?? '',
            'backup_type'       => $session['backup_type'] ?? '',
            'created_at'        => $session['started_at'] ?? current_time( 'mysql' ),
            'site_url'          => get_site_url(),
            'file_method'       => $session['file_method'] ?? 'zip',
            'items'             => [],
        ];

        foreach ( $session['chunks'] as $chunk ) {
            if ( $chunk['status'] !== 'done' || empty( $chunk['filename'] ) ) continue;

            $manifest['items'][] = [
                'id'          => $chunk['id'],
                'type'        => $chunk['type'],   // database | dir | dir_files_only
                'label'       => $chunk['label'],   // z.B. "wp-content/plugins/media-lab-seo"
                'source_path' => $chunk['path'],    // ursprünglicher lokaler Pfad zum Zeitpunkt des Backups
                'filename'    => $chunk['filename'],
                'remote_path' => $chunk['remote_path'],
                'size'        => $chunk['size'],
                'is_archive'  => ( $session['file_method'] ?? 'zip' ) === 'zip' && $chunk['type'] !== 'database',
            ];
        }

        if ( empty( $manifest['items'] ) ) return;

        try {
            $json = wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
            if ( $json === false ) return;
            $sftp->upload_string( $json, self::FILENAME, $session['remote_session_dir'] );
        } catch ( \Throwable $e ) {
            // Manifest ist nice-to-have — kein Backup-Abbruch bei Fehler.
        }
    }

    /**
     * Lädt und parst das Manifest einer Session.
     * @return array|null null wenn keine manifest.json vorhanden ist (z.B. Backups vor Einführung des Features).
     */
    public static function load( string $session_dir, MLBKP_SFTP $sftp ): ?array {
        try {
            $json = $sftp->download_string( $session_dir . '/' . self::FILENAME );
        } catch ( \Throwable $e ) {
            return null;
        }
        if ( $json === null ) return null;

        $data = json_decode( $json, true );
        if ( ! is_array( $data ) || empty( $data['items'] ) ) return null;

        return $data;
    }

    /**
     * Fallback für Sessions ohne manifest.json: rekonstruiert eine grobe
     * Item-Liste rein aus den Dateinamen im Session-Verzeichnis. Labels sind
     * dann sanitized statt lesbar, source_path ist unbekannt (null) — der
     * Ziel-Restore-Pfad muss im UI vom Benutzer bestätigt werden.
     */
    public static function build_fallback( array $remote_entries ): array {
        $items = [];
        $id    = 0;

        foreach ( $remote_entries as $entry ) {
            $name = $entry['name'];
            if ( $name === self::FILENAME || $name === '.' || $name === '..' ) continue;

            if ( str_starts_with( $name, 'db-backup-' ) ) {
                $items[] = [
                    'id' => $id++, 'type' => 'database',
                    'label' => 'Datenbank (kein Manifest vorhanden)',
                    'source_path' => null, 'filename' => $name, 'remote_path' => null,
                    'size' => $entry['size'] ?? 0, 'is_archive' => false,
                ];
                continue;
            }

            $label = str_starts_with( $name, 'chunk-' )
                ? preg_replace( '/^chunk-|-\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.zip$/', '', $name )
                : $name;

            $items[] = [
                'id' => $id++, 'type' => $entry['is_dir'] ? 'dir' : 'dir_files_only',
                'label' => $label . ' (aus Dateiname rekonstruiert)',
                'source_path' => null, 'filename' => $name, 'remote_path' => null,
                'size' => $entry['size'] ?? 0, 'is_archive' => ! $entry['is_dir'],
            ];
        }

        return [
            'manifest_version' => 0,
            'items'            => $items,
        ];
    }
}
