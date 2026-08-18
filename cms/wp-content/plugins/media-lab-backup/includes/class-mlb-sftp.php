<?php
defined( 'ABSPATH' ) || exit;

use phpseclib3\Net\SFTP;
use phpseclib3\Crypt\PublicKeyLoader;

/**
 * MLBKP_SFTP
 *
 * Wrapper um phpseclib3 SFTP für Uploads zur Hetzner Storage Box.
 * Unterstützt Passwort- und SSH-Key-Authentifizierung.
 */
class MLBKP_SFTP {

    private SFTP $sftp;
    private string $remote_base;
    private string $site_slug;

    public function __construct( array $settings ) {
        if ( ! class_exists( 'phpseclib3\Net\SFTP' ) ) {
            throw new RuntimeException(
                'phpseclib ist nicht installiert. Bitte "composer install" im Plugin-Verzeichnis ausführen: ' . MLBKP_PLUGIN_DIR
            );
        }

        $host     = trim( $settings['sftp_host'] ?? '' );
        $port     = (int) ( $settings['sftp_port'] ?? 22 );
        $username = trim( $settings['sftp_username'] ?? '' );

        $this->remote_base = rtrim( $settings['sftp_path'] ?? '/', '/' ) ?: '/';
        $this->site_slug   = $this->resolve_site_folder( $settings );

        if ( empty( $host ) || empty( $username ) ) {
            throw new RuntimeException( 'SFTP-Host und Benutzername dürfen nicht leer sein.' );
        }

        $this->sftp = new SFTP( $host, $port, 300 ); // 300s Timeout (ZIP-Erstellung kann lange dauern)
        $this->sftp->setKeepAlive( 60 ); // Keep-alive alle 60 Sekunden

        $auth_method = $settings['sftp_auth_method'] ?? 'password';

        if ( $auth_method === 'key' ) {
            $this->login_with_key( $username, $settings );
        } else {
            $this->login_with_password( $username, $settings['sftp_password'] ?? '', $host, $port );
        }
    }

    // ── Upload ───────────────────────────────────────────────────────────────

    /**
     * Lädt eine lokale Datei via SFTP hoch.
     *
     * Chunked-Transfer: Die Datei wird in 8-MB-Blöcken gelesen und gesendet,
     * statt die gesamte Datei auf einmal in den RAM zu laden.
     * Das verhindert Timeouts und Speicher-Erschöpfung bei großen ZIP-Archiven
     * (typisch: wp-content 500 MB – 3 GB) auf Shared-Hosting-Umgebungen.
     *
     * Chunk-Größe 8 MB: gutes Gleichgewicht zwischen Durchsatz und Speicherbedarf.
     * phpseclib3 puffert intern ca. 1 weiteres Paket — effektiver Peak ~16 MB.
     *
     * @throws RuntimeException
     */
    public function upload( string $local_path, string $remote_filename ): string {
        if ( ! file_exists( $local_path ) ) {
            throw new RuntimeException( "Lokale Datei nicht gefunden: {$local_path}" );
        }

        $remote_dir  = $this->get_remote_site_dir();
        $remote_path = $remote_dir . '/' . $remote_filename;

        $this->ensure_remote_dir( $remote_dir );

        $chunk_size = 8 * 1024 * 1024; // 8 MB
        $fh = @fopen( $local_path, 'rb' );

        if ( $fh === false ) {
            throw new RuntimeException( "Lokale Datei konnte nicht geöffnet werden: {$local_path}" );
        }

        try {
            // Datei auf dem Remote-Server anlegen / überschreiben
            if ( ! $this->sftp->put( $remote_path, '', SFTP::SOURCE_STRING ) ) {
                throw new RuntimeException( "SFTP: Remote-Datei konnte nicht angelegt werden: {$remote_path}" );
            }

            $offset = 0;
            while ( ! feof( $fh ) ) {
                $chunk = fread( $fh, $chunk_size );
                if ( $chunk === false ) {
                    throw new RuntimeException( "Lesefehler bei Datei: {$local_path}" );
                }

                if ( ! $this->sftp->put( $remote_path, $chunk, SFTP::SOURCE_STRING, $offset ) ) {
                    throw new RuntimeException(
                        "SFTP-Upload fehlgeschlagen bei Offset {$offset}: {$remote_path}"
                    );
                }

                $offset += strlen( $chunk );

                // Speicher nach jedem Chunk freigeben
                unset( $chunk );
                if ( $offset % ( 64 * 1024 * 1024 ) === 0 ) {
                    gc_collect_cycles();
                }
            }
        } finally {
            fclose( $fh );
        }

        return $remote_path;
    }

    // ── Retention ────────────────────────────────────────────────────────────

    public function apply_retention( string $prefix, int $keep ): void {
        if ( $keep <= 0 ) return;

        $files = $this->list_site_files( $prefix );
        sort( $files );

        $to_delete = array_slice( $files, 0, max( 0, count( $files ) - $keep ) );

        foreach ( $to_delete as $filename ) {
            $this->delete_site_file( $filename );
        }
    }

    // ── Verbindungstest ──────────────────────────────────────────────────────

    public static function test_connection( array $settings ): bool|string {
        try {
            $instance = new self( $settings );
            $instance->sftp->nlist( $instance->remote_base );
            return true;
        } catch ( \Throwable $e ) {
            return $e->getMessage();
        }
    }

    /**
     * Gibt den vorgeschlagenen Site-Ordner-Namen zurück (für Admin-UI Placeholder).
     */
    public static function get_suggested_folder(): string {
        $host = parse_url( get_site_url(), PHP_URL_HOST ) ?? 'wordpress';
        return preg_replace( '/[^a-z0-9\-]/', '-', strtolower( $host ) );
    }

    // ── Authentifizierung ─────────────────────────────────────────────────────

    private function login_with_password( string $username, string $password, string $host, int $port ): void {
        if ( ! $this->sftp->login( $username, $password ) ) {
            throw new RuntimeException(
                "SFTP-Login fehlgeschlagen für {$username}@{$host}:{$port}. Bitte Zugangsdaten prüfen."
            );
        }
    }

    private function login_with_key( string $username, array $settings ): void {
        $private_key = trim( $settings['sftp_private_key'] ?? '' );
        $passphrase  = $settings['sftp_key_passphrase'] ?? '';

        if ( empty( $private_key ) ) {
            throw new RuntimeException( 'SSH-Key-Authentifizierung: Kein Private Key angegeben.' );
        }

        try {
            $key = PublicKeyLoader::load( $private_key, $passphrase ?: false );
        } catch ( \Throwable $e ) {
            throw new RuntimeException( 'SSH-Key konnte nicht geladen werden: ' . $e->getMessage() );
        }

        if ( ! $this->sftp->login( $username, $key ) ) {
            throw new RuntimeException( 'SFTP-Login mit SSH-Key fehlgeschlagen. Bitte Key und Benutzername prüfen.' );
        }
    }

    // ── Private Hilfsmethoden ─────────────────────────────────────────────────

    private function get_remote_site_dir(): string {
        if ( $this->remote_base === '/' ) {
            return '/' . $this->site_slug;
        }
        return $this->remote_base . '/' . $this->site_slug;
    }

    private function list_site_files( string $prefix = '' ): array {
        $dir   = $this->get_remote_site_dir();
        $files = $this->sftp->nlist( $dir );

        if ( ! is_array( $files ) ) return [];

        $files = array_filter( $files, static fn( $f ) => ! in_array( $f, [ '.', '..' ], true ) );

        if ( $prefix !== '' ) {
            $files = array_filter( $files, static fn( $f ) => str_starts_with( $f, $prefix ) );
        }

        return array_values( $files );
    }

    private function delete_site_file( string $filename ): void {
        $this->sftp->delete( $this->get_remote_site_dir() . '/' . $filename );
    }

    private function ensure_remote_dir( string $path ): void {
        if ( $this->sftp->is_dir( $path ) ) return;

        $parts   = array_filter( explode( '/', $path ) );
        $current = '';

        foreach ( $parts as $part ) {
            $current .= '/' . $part;
            if ( ! $this->sftp->is_dir( $current ) ) {
                $this->sftp->mkdir( $current );
            }
        }
    }

    /**
     * Unterordner: aus Einstellungen (sftp_site_folder) oder auto-generiert.
     * Erlaubt Buchstaben, Zahlen, Punkte, Bindestriche und Unterstriche.
     * Verhindert Path-Traversal (../) und Null-Bytes.
     */
    private function resolve_site_folder( array $settings ): string {
        $custom = trim( $settings['sftp_site_folder'] ?? '' );
        if ( $custom !== '' ) {
            // Path-Traversal verhindern, erlaubte Zeichen beibehalten (inkl. Punkte)
            $clean = preg_replace( '/[^a-zA-Z0-9.\-_]/', '-', $custom );
            $clean = preg_replace( '/\.{2,}/', '.', $clean ); // ".." → "."
            return trim( $clean, '-' );
        }
        return self::get_suggested_folder();
    }
}
