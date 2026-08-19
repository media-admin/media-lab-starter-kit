<?php
defined( 'ABSPATH' ) || exit;

/**
 * MLBKP_Database_Backup
 *
 * Erstellt einen SQL-Dump der WordPress-Datenbank.
 * Verwendet mysqldump wenn verfügbar, fällt sonst auf reines PHP zurück.
 */
class MLBKP_Database_Backup {

    private string $temp_dir;

    public function __construct( string $temp_dir ) {
        $this->temp_dir = $temp_dir;
    }

    /**
     * Erstellt einen SQL-Dump und gibt den Pfad zur .sql.gz Datei zurück.
     * Versucht zuerst mysqldump, fällt bei Fehler automatisch auf PHP-Fallback zurück.
     *
     * @return array{path: string, size: int, method: string, fallback_reason: string}
     * @throws RuntimeException
     */
    public function create(): array {
        $filename  = 'db-backup-' . gmdate( 'Y-m-d_H-i-s' ) . '.sql';
        $filepath  = $this->temp_dir . $filename;
        $gzip_path = $filepath . '.gz';

        $method          = 'php';
        $fallback_reason = '';

        if ( $this->is_mysqldump_available() ) {
            try {
                $this->dump_via_mysqldump( $filepath );
                $method = 'mysqldump';
            } catch ( RuntimeException $e ) {
                // mysqldump gescheitert → PHP-Fallback
                $fallback_reason = $e->getMessage();
                @unlink( $filepath ); // ggf. leere/unvollständige Datei löschen
                $this->dump_via_php( $filepath );
            }
        } else {
            $fallback_reason = 'mysqldump nicht verfügbar (exec/shell_exec deaktiviert oder Binary fehlt)';
            $this->dump_via_php( $filepath );
        }

        // Komprimieren
        $this->gzip_file( $filepath, $gzip_path );
        @unlink( $filepath );

        if ( ! file_exists( $gzip_path ) ) {
            throw new RuntimeException( 'DB-Dump-Datei konnte nicht erstellt werden.' );
        }

        return [
            'path'            => $gzip_path,
            'filename'        => basename( $gzip_path ),
            'size'            => filesize( $gzip_path ),
            'method'          => $method,
            'fallback_reason' => $fallback_reason,
        ];
    }

    // ── mysqldump ─────────────────────────────────────────────────────────────

    private function is_mysqldump_available(): bool {
        if ( ! function_exists( 'exec' ) && ! function_exists( 'shell_exec' ) ) {
            return false;
        }

        $output = [];
        @exec( 'mysqldump --version 2>&1', $output, $code );
        return $code === 0;
    }

    /**
     * @throws RuntimeException
     */
    private function dump_via_mysqldump( string $filepath ): void {
        $host     = DB_HOST;
        $dbname   = DB_NAME;
        $username = DB_USER;
        $password = DB_PASSWORD;

        // Host und Port / Socket trennen
        $port   = '3306';
        $socket = '';

        if ( str_starts_with( $host, '/' ) ) {
            // Unix-Socket (z.B. /var/run/mysqld/mysqld.sock)
            $socket = $host;
            $host   = '127.0.0.1';
        } elseif ( str_contains( $host, ':' ) ) {
            [ $host, $port ] = explode( ':', $host, 2 );
        }

        // Command zusammenbauen
        $args = [
            'mysqldump',
            '--host=' . escapeshellarg( $host ),
            '--port=' . escapeshellarg( $port ),
            '--user=' . escapeshellarg( $username ),
            '--password=' . escapeshellarg( $password ),
            '--single-transaction',
            '--routines',
            '--triggers',
            '--skip-column-statistics',
            '--set-gtid-purged=OFF',
        ];

        if ( $socket !== '' ) {
            $args[] = '--socket=' . escapeshellarg( $socket );
        }

        $args[] = escapeshellarg( $dbname );

        // `timeout`-Kommando vorschalten falls verfügbar (verhindert ewig hängenden mysqldump)
        $timeout_cmd = '';
        $which = @exec( 'which timeout 2>/dev/null' );
        if ( $which ) {
            $timeout_cmd = 'timeout 300 '; // max 5 Minuten für mysqldump
        }

        $cmd = $timeout_cmd . implode( ' ', $args );

        // proc_open für sauberes stdout/stderr-Handling
        if ( ! function_exists( 'proc_open' ) ) {
            throw new RuntimeException( 'proc_open ist auf diesem Server deaktiviert.' );
        }

        $spec = [
            0 => [ 'pipe', 'r' ],
            1 => [ 'file', $filepath, 'w' ],
            2 => [ 'pipe', 'w' ],
        ];

        $proc = proc_open( $cmd, $spec, $pipes );

        if ( ! is_resource( $proc ) ) {
            throw new RuntimeException( 'proc_open: mysqldump konnte nicht gestartet werden.' );
        }

        fclose( $pipes[0] );

        // stderr non-blocking lesen mit eigenem Timeout (300s)
        stream_set_blocking( $pipes[2], false );
        $stderr    = '';
        $start     = time();
        $max_wait  = 300;

        while ( time() - $start < $max_wait ) {
            $status = proc_get_status( $proc );
            if ( ! $status['running'] ) break;

            $chunk = fread( $pipes[2], 4096 );
            if ( $chunk !== false ) $stderr .= $chunk;
            usleep( 200000 ); // 200ms warten
        }

        // Falls Prozess noch läuft → zwangsbeenden
        $status = proc_get_status( $proc );
        if ( $status['running'] ) {
            proc_terminate( $proc, 9 );
            fclose( $pipes[2] );
            proc_close( $proc );
            throw new RuntimeException(
                'mysqldump Timeout nach 300 Sekunden — Prozess abgebrochen. PHP-Fallback wird verwendet.'
            );
        }

        // Restlichen stderr lesen
        stream_set_blocking( $pipes[2], true );
        $stderr   .= stream_get_contents( $pipes[2] );
        fclose( $pipes[2] );
        $exit_code = proc_close( $proc );

        // Passwort aus Log-Ausgabe entfernen
        $cmd_log = preg_replace( '/--password=\S+/', '--password=***', $cmd );

        if ( $exit_code !== 0 ) {
            $stderr_clean = trim( $stderr );
            throw new RuntimeException(
                "mysqldump fehlgeschlagen (Exit-Code {$exit_code}):" .
                ( $stderr_clean !== '' ? "\n   stderr: {$stderr_clean}" : '' ) .
                "\n   cmd: {$cmd_log}"
            );
        }

        if ( ! file_exists( $filepath ) || filesize( $filepath ) === 0 ) {
            throw new RuntimeException( 'mysqldump hat eine leere Datei erzeugt.' );
        }
    }

    // ── PHP-Fallback ──────────────────────────────────────────────────────────

    /**
     * Reiner PHP-Datenbankdump ohne externe Abhängigkeiten.
     *
     * @throws RuntimeException
     */
    private function dump_via_php( string $filepath ): void {
        global $wpdb;

        $handle = @fopen( $filepath, 'w' );
        if ( ! $handle ) {
            throw new RuntimeException( "Konnte Dump-Datei nicht öffnen: {$filepath}" );
        }

        $this->write_header( $handle );

        $tables = $wpdb->get_col( 'SHOW TABLES' );

        foreach ( $tables as $table ) {
            $this->dump_table( $handle, $table );
        }

        $this->write_footer( $handle );
        fclose( $handle );
    }

    private function write_header( $handle ): void {
        $header = "-- ============================================================\n";
        $header .= "-- Media Lab Backup — WordPress Database Dump\n";
        $header .= "-- Erstellt: " . gmdate( 'Y-m-d H:i:s' ) . " UTC\n";
        $header .= "-- Datenbank: " . DB_NAME . "\n";
        $header .= "-- WordPress: " . get_bloginfo( 'version' ) . "\n";
        $header .= "-- ============================================================\n\n";
        $header .= "SET FOREIGN_KEY_CHECKS=0;\n";
        $header .= "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n";
        $header .= "SET NAMES utf8mb4;\n\n";
        fwrite( $handle, $header );
    }

    private function write_footer( $handle ): void {
        fwrite( $handle, "\nSET FOREIGN_KEY_CHECKS=1;\n" );
        fwrite( $handle, "-- Dump abgeschlossen: " . gmdate( 'Y-m-d H:i:s' ) . " UTC\n" );
    }

    private function dump_table( $handle, string $table ): void {
        global $wpdb;

        fwrite( $handle, "\n-- ─────────────────────────────────────────────\n" );
        fwrite( $handle, "-- Tabelle: `{$table}`\n" );
        fwrite( $handle, "-- ─────────────────────────────────────────────\n\n" );
        fwrite( $handle, "DROP TABLE IF EXISTS `{$table}`;\n" );

        // CREATE TABLE
        $create = $wpdb->get_row( "SHOW CREATE TABLE `{$table}`", ARRAY_N );
        if ( $create ) {
            fwrite( $handle, $create[1] . ";\n\n" );
        }

        // Daten in Chunks (Speicherschonend)
        $chunk_size = 500;
        $offset     = 0;

        do {
            $rows = $wpdb->get_results(
                $wpdb->prepare( "SELECT * FROM `{$table}` LIMIT %d OFFSET %d", $chunk_size, $offset ),
                ARRAY_N
            );

            if ( empty( $rows ) ) break;

            $columns_raw = $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`" );
            $columns     = '`' . implode( '`, `', $columns_raw ) . '`';

            $values_list = [];
            foreach ( $rows as $row ) {
                $values = array_map( static function ( $value ) {
                    if ( $value === null ) return 'NULL';
                    return "'" . addslashes( $value ) . "'";
                }, $row );
                $values_list[] = '(' . implode( ', ', $values ) . ')';
            }

            fwrite( $handle,
                "INSERT INTO `{$table}` ({$columns}) VALUES\n" .
                implode( ",\n", $values_list ) . ";\n"
            );

            $offset += $chunk_size;
        } while ( count( $rows ) === $chunk_size );

        fwrite( $handle, "\n" );
    }

    // ── GZIP ─────────────────────────────────────────────────────────────────

    private function gzip_file( string $source, string $destination ): void {
        if ( ! file_exists( $source ) ) {
            throw new RuntimeException( "Quelldatei für GZIP nicht gefunden: {$source}" );
        }

        $in  = fopen( $source, 'rb' );
        $out = gzopen( $destination, 'wb9' ); // Level 9 = beste Kompression

        if ( ! $in || ! $out ) {
            throw new RuntimeException( 'Konnte GZIP-Datei nicht erstellen.' );
        }

        while ( ! feof( $in ) ) {
            gzwrite( $out, fread( $in, 524288 ) ); // 512KB Chunks
        }

        fclose( $in );
        gzclose( $out );
    }
}
