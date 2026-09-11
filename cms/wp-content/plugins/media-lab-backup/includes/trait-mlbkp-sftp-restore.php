<?php
defined( 'ABSPATH' ) || exit;

use phpseclib3\Net\SFTP;

/**
 * MLBKP_SFTP_Restore
 *
 * Erweitert MLBKP_SFTP um Lese-Operationen (Listing, Download) für das
 * Restore-Feature. Als Trait umgesetzt statt die bestehende Klasse direkt
 * zu verändern — surgical statt invasiv.
 *
 * Integration: `use MLBKP_SFTP_Restore;` als erste Zeile im Body von
 * MLBKP_SFTP einfügen — siehe PATCHES.md. Der Trait greift auf die
 * private Property $this->sftp und die private Methode
 * get_remote_site_dir() der Hauptklasse zu; das funktioniert, weil
 * Trait-Code so behandelt wird, als wäre er direkt in der Klasse
 * geschrieben.
 */
trait MLBKP_SFTP_Restore {

    /**
     * Öffentlicher Zugriff auf das site-spezifische Remote-Basisverzeichnis.
     */
    public function get_site_dir(): string {
        return $this->get_remote_site_dir();
    }

    /**
     * Listet alle Backup-Session-Verzeichnisse, neueste zuerst.
     *
     * Bewusst OHNE Annahme über das exakte Namensschema der Session-Ordner
     * (ursprünglich fälschlich als striktes Y-m-d_H-i-s-Regex angenommen) —
     * jedes Unterverzeichnis im Site-Root gilt als Session. Lexikographische
     * Sortierung setzt voraus, dass der Ordnername mit einem sortierbaren
     * Zeitstempel beginnt; falls das nicht zutrifft, bitte Bescheid geben,
     * dann sortieren wir stattdessen nach SFTP-mtime.
     *
     * @return array<int, array{name:string, path:string}>
     */
    public function list_sessions(): array {
        $base    = $this->get_remote_site_dir();
        $entries = $this->sftp->nlist( $base );
        if ( ! is_array( $entries ) ) return [];

        $sessions = [];
        foreach ( $entries as $name ) {
            if ( $name === '.' || $name === '..' ) continue;

            $path = $base . '/' . $name;
            if ( ! $this->sftp->is_dir( $path ) ) continue; // nur Verzeichnisse sind Sessions

            $sessions[] = [ 'name' => $name, 'path' => $path ];
        }

        usort( $sessions, static fn( $a, $b ) => strcmp( $b['name'], $a['name'] ) );

        return $sessions;
    }

    /**
     * Listet Dateien/Verzeichnisse direkt innerhalb eines Remote-Verzeichnisses.
     *
     * @return array<int, array{name:string, is_dir:bool, size:int}>
     */
    public function list_entries( string $remote_dir ): array {
        $entries = $this->sftp->nlist( $remote_dir );
        if ( ! is_array( $entries ) ) return [];

        $result = [];
        foreach ( $entries as $name ) {
            if ( $name === '.' || $name === '..' ) continue;
            $path   = $remote_dir . '/' . $name;
            $is_dir = $this->sftp->is_dir( $path );

            $result[] = [
                'name'   => $name,
                'is_dir' => $is_dir,
                'size'   => $is_dir ? 0 : (int) $this->sftp->filesize( $path ),
            ];
        }

        return $result;
    }

    /**
     * Rekursives Listing eines Remote-Verzeichnisses (für Stream-Backups
     * ohne ZIP) — relative Pfade bezogen auf das übergebene Basisverzeichnis.
     *
     * @return array<int, array{relative:string, size:int}>
     */
    public function list_entries_recursive( string $remote_dir, string $relative_prefix = '' ): array {
        $files = [];
        foreach ( $this->list_entries( $remote_dir ) as $entry ) {
            $relative = ltrim( $relative_prefix . '/' . $entry['name'], '/' );
            if ( $entry['is_dir'] ) {
                $files = array_merge( $files, $this->list_entries_recursive( $remote_dir . '/' . $entry['name'], $relative ) );
            } else {
                $files[] = [ 'relative' => $relative, 'size' => $entry['size'] ];
            }
        }
        return $files;
    }

    /**
     * Lädt eine Remote-Datei in eine lokale Datei herunter (gestreamt,
     * phpseclib3 puffert intern — kein voller RAM-Load bei großen ZIPs).
     *
     * @throws RuntimeException
     */
    public function download( string $remote_path, string $local_path ): void {
        if ( ! $this->sftp->file_exists( $remote_path ) ) {
            throw new RuntimeException( "Remote-Datei nicht gefunden: {$remote_path}" );
        }

        $dir = dirname( $local_path );
        if ( ! is_dir( $dir ) ) wp_mkdir_p( $dir );

        if ( $this->sftp->get( $remote_path, $local_path ) === false ) {
            throw new RuntimeException( "SFTP-Download fehlgeschlagen: {$remote_path}" );
        }
    }

    /**
     * Lädt eine kleine Remote-Textdatei direkt als String (z.B. manifest.json).
     * Gibt null zurück statt zu werfen, wenn die Datei nicht existiert.
     */
    public function download_string( string $remote_path ): ?string {
        if ( ! $this->sftp->file_exists( $remote_path ) ) return null;
        $content = $this->sftp->get( $remote_path );
        return $content === false ? null : $content;
    }

    /**
     * Lädt einen String direkt als Remote-Datei hoch (z.B. manifest.json).
     */
    public function upload_string( string $content, string $remote_filename, string $remote_dir ): string {
        $this->ensure_remote_dir( $remote_dir );
        $remote_path = $remote_dir . '/' . $remote_filename;

        if ( ! $this->sftp->put( $remote_path, $content, SFTP::SOURCE_STRING ) ) {
            throw new RuntimeException( "SFTP: String-Upload fehlgeschlagen: {$remote_path}" );
        }

        return $remote_path;
    }
}
