<?php
defined( 'ABSPATH' ) || exit;

/**
 * MLBKP_SQL_Parser
 *
 * Quote-bewusster, streamender SQL-Statement-Splitter für mysqldump- und
 * PHP-Fallback-Dumps. Ermöglicht:
 *  - Auflisten aller enthaltenen Tabellen (list_tables)
 *  - Batch-weisen, fortsetzbaren Import mit optionalem Tabellen-Filter
 *    (import_batch) — läuft über mehrere WP-Cron-Ticks, um Timeouts auf
 *    Shared Hosting zu vermeiden.
 *
 * Arbeitet bewusst NICHT mit mysqldump-Kommentar-Markern
 * ("-- Table structure for table ..."), da der PHP-Fallback-Dump
 * (class-mlb-database-backup.php) diese nicht zwingend im selben Format
 * schreibt. Stattdessen wird die Zieltabelle direkt aus jedem einzelnen
 * Statement erkannt (DROP/CREATE/INSERT/LOCK/ALTER ... `tabelle`).
 */
class MLBKP_SQL_Parser {

    private const READ_CHUNK = 1048576; // 1 MB Lese-Blöcke

    /**
     * Scannt den kompletten Dump einmal und gibt alle über CREATE TABLE
     * gefundenen Tabellennamen zurück (sortiert).
     */
    public static function list_tables( string $sql_path ): array {
        $tables = [];
        foreach ( self::statements( $sql_path ) as $stmt ) {
            if ( $stmt['table'] !== null && preg_match( '/^\s*CREATE TABLE/i', $stmt['sql'] ) ) {
                $tables[ $stmt['table'] ] = true;
            }
        }
        $result = array_keys( $tables );
        sort( $result );
        return $result;
    }

    /**
     * Führt einen Teil des Dumps aus, ab $start_offset (Byte-Position,
     * garantiert eine Statement-Grenze), bis entweder $max_seconds
     * überschritten ist oder das Dateiende erreicht wurde.
     *
     * @param string     $sql_path     Pfad zur (bereits entpackten) .sql-Datei
     * @param array|null $only_tables  null = alles importieren, sonst nur diese Tabellen
     * @param int        $start_offset Byte-Offset, an dem fortgesetzt wird
     * @param float      $max_seconds  Zeitbudget für diesen Aufruf
     *
     * @return array{next_offset:?int, done:bool, executed:int, skipped:int, errors:array}
     */
    public static function import_batch(
        string $sql_path,
        ?array $only_tables,
        int $start_offset,
        float $max_seconds
    ): array {
        global $wpdb;

        $deadline = microtime( true ) + $max_seconds;
        $executed = 0;
        $skipped  = 0;
        $errors   = [];
        $last_end = $start_offset;
        $done     = true;

        $table_filter = $only_tables !== null ? array_flip( $only_tables ) : null;

        foreach ( self::statements( $sql_path, $start_offset ) as $stmt ) {
            if ( microtime( true ) >= $deadline ) {
                $done = false;
                break;
            }

            $sql      = trim( $stmt['sql'] );
            $table    = $stmt['table'];
            $last_end = $stmt['end_offset'];

            if ( $sql === '' ) continue;

            // LOCK/UNLOCK TABLES überspringen wir immer — der Importer
            // arbeitet Statement-für-Statement über wpdb, explizite Locks
            // sind hier nicht nötig und bei gefiltertem Import riskant
            // (Lock ohne passendes Unlock, falls die Tabelle rausgefiltert wird).
            if ( preg_match( '/^\s*(UN)?LOCK TABLES/i', $sql ) ) continue;

            // Tabellen-Filter: Statements ohne erkannte Tabelle (SET, etc.)
            // laufen immer durch, Tabellen-Statements nur wenn in der Auswahl.
            if ( $table_filter !== null && $table !== null && ! isset( $table_filter[ $table ] ) ) {
                $skipped++;
                continue;
            }

            $result = $wpdb->query( $sql );
            if ( $result === false && $wpdb->last_error ) {
                $errors[] = [
                    'table'       => $table,
                    'error'       => $wpdb->last_error,
                    'sql_excerpt' => mb_strimwidth( $sql, 0, 120, '…' ),
                ];
                // Einzelner Statement-Fehler bricht den Import nicht ab —
                // z.B. "table already exists" bei erneutem CREATE TABLE ist unkritisch.
            } else {
                $executed++;
            }
        }

        return [
            'next_offset' => $done ? null : $last_end,
            'done'        => $done,
            'executed'    => $executed,
            'skipped'     => $skipped,
            'errors'      => $errors,
        ];
    }

    // ── Streaming-Statement-Splitter ─────────────────────────────────────────

    /**
     * Generator: liest die SQL-Datei ab $start_offset blockweise und liefert
     * vollständige Statements (durch ';' auf oberster Ebene getrennt),
     * Quote- und Zeilenkommentar-bewusst.
     *
     * @return \Generator<array{sql:string, end_offset:int, table:?string}>
     */
    private static function statements( string $sql_path, int $start_offset = 0 ): \Generator {
        $fh = @fopen( $sql_path, 'rb' );
        if ( ! $fh ) {
            throw new RuntimeException( "SQL-Datei konnte nicht geöffnet werden: {$sql_path}" );
        }

        if ( $start_offset > 0 ) fseek( $fh, $start_offset );

        $buffer     = '';
        $in_single  = false;
        $in_double  = false;
        $in_comment = false; // -- oder # Zeilenkommentar
        $pos        = $start_offset;

        try {
            while ( ! feof( $fh ) ) {
                $block = fread( $fh, self::READ_CHUNK );
                if ( $block === false || $block === '' ) break;

                $len = strlen( $block );
                for ( $i = 0; $i < $len; $i++ ) {
                    $char = $block[ $i ];
                    $buffer .= $char;
                    $pos++;

                    if ( $in_comment ) {
                        if ( $char === "\n" ) $in_comment = false;
                        continue;
                    }

                    if ( $in_single ) {
                        if ( $char === '\\' ) {
                            if ( $i + 1 < $len ) { $buffer .= $block[ ++$i ]; $pos++; }
                            continue;
                        }
                        if ( $char === "'" ) $in_single = false;
                        continue;
                    }

                    if ( $in_double ) {
                        if ( $char === '\\' ) {
                            if ( $i + 1 < $len ) { $buffer .= $block[ ++$i ]; $pos++; }
                            continue;
                        }
                        if ( $char === '"' ) $in_double = false;
                        continue;
                    }

                    // Außerhalb von Strings/Kommentaren
                    if ( $char === "'" ) { $in_single = true; continue; }
                    if ( $char === '"' ) { $in_double = true; continue; }

                    if ( $char === '-' && $i + 1 < $len && $block[ $i + 1 ] === '-' ) {
                        $in_comment = true;
                        continue;
                    }
                    if ( $char === '#' ) {
                        $in_comment = true;
                        continue;
                    }

                    if ( $char === ';' ) {
                        yield [
                            'sql'        => $buffer,
                            'end_offset' => $pos,
                            'table'      => self::extract_table( $buffer ),
                        ];
                        $buffer = '';
                    }
                }
            }

            // Rest nach letztem ';' (z.B. Kommentarzeile am Dateiende ohne Semikolon)
            if ( trim( $buffer ) !== '' && ! self::is_comment_only( $buffer ) ) {
                yield [
                    'sql'        => $buffer,
                    'end_offset' => $pos,
                    'table'      => self::extract_table( $buffer ),
                ];
            }
        } finally {
            fclose( $fh );
        }
    }

    private static function is_comment_only( string $sql ): bool {
        foreach ( preg_split( '/\r?\n/', trim( $sql ) ) as $line ) {
            $line = trim( $line );
            if ( $line === '' ) continue;
            if ( ! str_starts_with( $line, '--' ) && ! str_starts_with( $line, '#' ) ) return false;
        }
        return true;
    }

    private static function extract_table( string $sql ): ?string {
        if ( preg_match(
            '/^\s*(?:DROP TABLE(?:\s+IF\s+EXISTS)?|CREATE TABLE(?:\s+IF\s+NOT\s+EXISTS)?|INSERT\s+INTO|ALTER TABLE|LOCK TABLES)\s+`([^`]+)`/i',
            $sql,
            $m
        ) ) {
            return $m[1];
        }
        return null;
    }
}
