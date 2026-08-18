<?php
defined( 'ABSPATH' ) || exit;

/** Wird geworfen wenn der Benutzer ein Backup abbricht. */
class MLBKP_CancelledException extends RuntimeException {}

/**
 * MLBKP_Backup_Runner
 *
 * Orchestriert den gesamten Backup-Prozess:
 * Temp-Verzeichnis → Dump/ZIP → SFTP-Upload → Retention → Cleanup → Log
 */
class MLBKP_Backup_Runner {

    private array   $settings;
    private string  $temp_dir;
    private array   $log = [];
    private ?int $caffeinate_pid = null;

    public function __construct() {
        $this->settings = mlbkp_get_settings();
        $this->temp_dir = $this->prepare_temp_dir();
    }

    // ── Öffentliche API ───────────────────────────────────────────────────────

    /**
     * Startet ein Backup und erstellt einen neuen Log-Eintrag.
     * Für geplante Cron-Jobs.
     */
    public function run( string $type = 'full', string $triggered_by = 'manual' ): array {
        $log_id = MLBKP_Logger::start( $type, $triggered_by );
        return $this->execute( $log_id, $type );
    }

    /**
     * Führt ein Backup mit einem bereits existierenden Log-Eintrag fort.
     * Für asynchrone manuelle Backups (AJAX → Cron).
     */
    public function run_from_log_id( int $log_id, string $type ): array {
        return $this->execute( $log_id, $type );
    }

    // ── Interner Backup-Ablauf ────────────────────────────────────────────────

    private function execute( int $log_id, string $type ): array {
        @set_time_limit( 0 );
        @ini_set( 'memory_limit', '512M' );

        $this->maybe_start_caffeinate();

        $this->log( "▶ Backup gestartet [Typ: {$type}]" );
        $this->log( '🖥  Site: ' . get_site_url() );
        $this->log( '📁 Temp: ' . $this->temp_dir );

        $uploaded_files = [];

        try {
            // ── Abbruch-Check ─────────────────────────────────────────────────
            $this->check_cancelled( $log_id );

            // ── SFTP verbinden ────────────────────────────────────────────────
            $this->log( '🔌 SFTP-Verbindung aufbauen …' );
            $sftp = new MLBKP_SFTP( $this->settings );
            $this->log( '✅ SFTP verbunden.' );

            $retention = (int) ( $this->settings['retention_count'] ?? 7 );

            // ── Datenbank-Backup ──────────────────────────────────────────────
            if ( in_array( $type, [ 'database', 'full' ], true ) ) {
                $this->check_cancelled( $log_id );
                $this->log( '🗄  Datenbank-Dump erstellen …' );
                $db_backup = new MLBKP_Database_Backup( $this->temp_dir );
                $result    = $db_backup->create();

                $this->log( "   Methode: {$result['method']}" );
                if ( ! empty( $result['fallback_reason'] ) ) {
                    $this->log( "   ⚠ mysqldump fehlgeschlagen, PHP-Fallback verwendet." );
                    $this->log( "   Grund: {$result['fallback_reason']}" );
                }
                $this->log( '   Größe:   ' . MLBKP_Logger::format_bytes( $result['size'] ) );

                $this->check_cancelled( $log_id );
                $this->log( '📤 DB-Dump hochladen …' );
                $remote_path = $sftp->upload( $result['path'], $result['filename'] );
                $uploaded_files[] = $result;

                $this->log( "✅ DB-Dump hochgeladen: {$remote_path}" );
                $this->log( '🔄 Retention für DB-Backups anwenden …' );
                $sftp->apply_retention( 'db-backup-', $retention );
            }

            // ── wp-content Backup ─────────────────────────────────────────────
            if ( in_array( $type, [ 'wpcontent', 'full' ], true ) ) {
                $this->check_cancelled( $log_id );
                $this->log( '📦 wp-content ZIP erstellen …' );
                $extra_excludes = $this->parse_excludes();
                $file_backup    = new MLBKP_File_Backup( $this->temp_dir );
                $result         = $file_backup->create( 'wpcontent', $extra_excludes );

                $this->log( '   Größe: ' . MLBKP_Logger::format_bytes( $result['size'] ) );

                $this->check_cancelled( $log_id );
                $this->log( '📤 wp-content ZIP hochladen …' );
                $remote_path = $sftp->upload( $result['path'], $result['filename'] );
                $uploaded_files[] = $result;

                $this->log( "✅ wp-content hochgeladen: {$remote_path}" );
                $sftp->apply_retention( 'files-wpcontent-', $retention );
            }

            // ── WP-Core Backup ────────────────────────────────────────────────
            if ( $type === 'wpcore' ) {
                $this->check_cancelled( $log_id );
                $this->log( '📦 WordPress-Core ZIP erstellen …' );
                $file_backup = new MLBKP_File_Backup( $this->temp_dir );
                $result      = $file_backup->create( 'wpcore', $this->parse_excludes() );

                $this->log( '   Größe: ' . MLBKP_Logger::format_bytes( $result['size'] ) );

                $this->check_cancelled( $log_id );
                $this->log( '📤 WP-Core ZIP hochladen …' );
                $remote_path = $sftp->upload( $result['path'], $result['filename'] );
                $uploaded_files[] = $result;

                $this->log( "✅ WP-Core hochgeladen: {$remote_path}" );
                $sftp->apply_retention( 'files-wpcore-', $retention );
            }

            // ── Gesamt-Größe ──────────────────────────────────────────────────
            $total_size = array_sum( array_column( $uploaded_files, 'size' ) );
            $this->log( '📊 Gesamt-Größe: ' . MLBKP_Logger::format_bytes( $total_size ) );

            // ── Log abschließen ───────────────────────────────────────────────
            MLBKP_Logger::finish( $log_id, 'success', [
                'file_name'   => implode( ', ', array_column( $uploaded_files, 'filename' ) ),
                'file_size'   => $total_size,
                'remote_path' => $remote_path ?? '',
            ] );

            $this->log( '🎉 Backup erfolgreich abgeschlossen.' );
            $this->release_lock();
            $this->maybe_send_notification( true, '' );

        } catch ( MLBKP_CancelledException $e ) {
            $this->log( '🛑 Backup wurde abgebrochen.' );
            MLBKP_Logger::finish( $log_id, 'cancelled', [
                'error_message' => 'Manuell abgebrochen.',
            ] );
            MLBKP_Logger::clear_cancel_flag( $log_id );
            $this->release_lock();
            $this->cleanup();

            return [
                'success' => false,
                'message' => 'Backup abgebrochen.',
                'log'     => $this->log,
            ];

        } catch ( \Throwable $e ) {
            $error = $e->getMessage();
            $this->log( "❌ Fehler: {$error}" );

            MLBKP_Logger::finish( $log_id, 'error', [
                'error_message' => $error,
            ] );

            $this->maybe_send_notification( false, $error );
            $this->release_lock();
            $this->cleanup();

            return [
                'success' => false,
                'message' => $error,
                'log'     => $this->log,
            ];
        }

        $this->cleanup();

        return [
            'success' => true,
            'message' => 'Backup erfolgreich abgeschlossen.',
            'log'     => $this->log,
        ];
    }

    // ── Private Hilfsmethoden ─────────────────────────────────────────────────

    private function prepare_temp_dir(): string {
        $dir = WP_CONTENT_DIR . '/uploads/media-lab-backup/temp/';

        if ( ! is_dir( $dir ) ) {
            wp_mkdir_p( $dir );
        }

        // Vor direktem Zugriff schützen
        $htaccess = $dir . '.htaccess';
        if ( ! file_exists( $htaccess ) ) {
            file_put_contents( $htaccess, "Order deny,allow\nDeny from all\n" );
        }

        $index = $dir . 'index.php';
        if ( ! file_exists( $index ) ) {
            file_put_contents( $index, '<?php // Silence is golden.' );
        }

        return $dir;
    }

    /**
     * macOS-only: verhindert System-/Display-Sleep für die Dauer des Backups.
     * Lokale Backups auf Laravel Valet (Mac) können 30–90+ Minuten dauern
     * (ZIP-Erstellung + SFTP-Upload) und wurden durch den Sleep-Modus des
     * Macs abgebrochen — sichtbar als "Unable to write X bytes"-Fehler.
     * Auf Production (Linux) ist diese Methode ein No-Op.
     */
    private function maybe_start_caffeinate(): void {
        if ( PHP_OS_FAMILY !== 'Darwin' ) return;
        if ( ! function_exists( 'shell_exec' ) ) return;

        // WICHTIG: nohup ist notwendig! Ein einfaches "caffeinate ... &" wird
        // per SIGHUP beendet, sobald die von shell_exec() gestartete Subshell
        // terminiert — der Prozess würde sofort wieder sterben, bevor das
        // eigentliche Backup überhaupt fertig ist.
        //
        // WICHTIG #2: "launchctl asuser $(id -u)" ist ebenfalls notwendig!
        // Valets php-fpm läuft als LaunchDaemon (root-Master-Prozess,
        // Worker unter dem Mac-User). Ein caffeinate, das direkt aus diesem
        // Kontext heraus gestartet wird, läuft zwar als Prozess (verifiziert
        // via ps, PPID 1 nach nohup) — hält aber KEINE Power-Management-
        // Assertion, da IOPMAssertionCreate an die aktive GUI-Session
        // gebunden ist und der LaunchDaemon-Kontext nicht als solche zählt.
        // "launchctl asuser $(id -u)" reicht den Aufruf explizit in die
        // GUI-Session des Users durch, wodurch die Assertion korrekt
        // registriert wird. Verifiziert via `pmset -g assertions` über zwei
        // unabhängige Testläufe im asynchronen WP-Cron-Loopback-Kontext:
        // PreventUserIdleSystemSleep, PreventUserIdleDisplaySleep und
        // PreventSystemSleep waren jeweils aktiv.
        //
        // WICHTIG #3: PHP-FPM-Worker haben oft eine leere PATH-Variable
        // (kein "clear_env = no" in der Pool-Config). "which"-Aufrufe zur
        // Verifikation schlagen daher fehl (leerer String), auch wenn die
        // Binaries via Shell-Default-PATH ("/usr/bin:/bin:/usr/sbin:/sbin")
        // trotzdem gefunden und ausgeführt werden — kein Grund zur Sorge,
        // wenn "which xyz" im selben Kontext leer zurückkommt.
        $cmd = 'nohup launchctl asuser $(id -u) caffeinate -d -i -s > /tmp/mlbkp-caffeinate-stdout.log 2>&1 & echo $!';
        $pid = trim( (string) @shell_exec( $cmd ) );

        if ( ctype_digit( $pid ) ) {
            $this->caffeinate_pid = (int) $pid;
            $this->log( "☕ caffeinate gestartet (PID {$this->caffeinate_pid}) — verhindert Sleep während des Backups." );
        } else {
            $this->log( "⚠ caffeinate konnte nicht gestartet werden (Rückgabe: '{$pid}')." );
        }
    }

    private function maybe_stop_caffeinate(): void {
        if ( $this->caffeinate_pid === null ) return;
        if ( function_exists( 'shell_exec' ) ) {
            // WICHTIG: Die über $! erfasste PID (in maybe_start_caffeinate())
            // gehört zu "launchctl", nicht zu "caffeinate" selbst — launchctl
            // asuser reparented caffeinate als eigenständigen Prozess in der
            // GUI-Session. Ein kill auf die getrackte PID trifft daher ins
            // Leere, caffeinate läuft für immer weiter (verifiziert: über
            // 2 Stunden verwaister Prozess beobachtet). Stattdessen gezielt
            // per pkill anhand des Kommandostrings beenden.
            @shell_exec( "pkill -f 'caffeinate -d -i -s' 2>/dev/null" );
        }
        $this->caffeinate_pid = null;
    }

    private function cleanup(): void {
        $this->maybe_stop_caffeinate();

        $this->log( '🧹 Temp-Dateien aufräumen …' );
        $files = glob( $this->temp_dir . '*.{sql,sql.gz,zip}', GLOB_BRACE );
        foreach ( (array) $files as $file ) {
            @unlink( $file );
        }
    }

    // ── Lock-Mechanismus ──────────────────────────────────────────────────────

    private function acquire_lock( int $log_id ): bool {
        $existing = get_option( self::LOCK_OPTION );

        if ( $existing ) {
            // Lock vorhanden — prüfen ob er abgelaufen ist
            $lock_data = get_option( self::LOCK_OPTION . '_time' );
            if ( $lock_data && ( time() - (int) $lock_data ) < self::LOCK_TIMEOUT ) {
                return false; // Aktiver Lock
            }
            // Abgelaufener Lock — überschreiben
        }

        update_option( self::LOCK_OPTION,          $log_id, false );
        update_option( self::LOCK_OPTION . '_time', time(),  false );
        return true;
    }

    private function release_lock(): void {
        delete_option( self::LOCK_OPTION );
        delete_option( self::LOCK_OPTION . '_time' );
    }

    private function parse_excludes(): array {
        $raw = $this->settings['exclude_paths'] ?? '';
        if ( empty( $raw ) ) return [];
        return array_filter( array_map( 'trim', explode( "\n", $raw ) ) );
    }

    /**
     * Prüft ob ein Abbruch-Flag gesetzt wurde — wirft MLBKP_CancelledException wenn ja.
     */
    private function check_cancelled( int $log_id ): void {
        if ( MLBKP_Logger::is_cancelled( $log_id ) ) {
            throw new MLBKP_CancelledException();
        }
    }

    private function log( string $message ): void {
        $this->log[] = '[' . gmdate( 'H:i:s' ) . '] ' . $message;
    }

    private function maybe_send_notification( bool $success, string $error ): void {
        $email  = trim( $this->settings['notify_email'] ?? '' );
        $notify = $this->settings['notify_on'] ?? 'error';

        if ( empty( $email ) ) return;
        if ( $notify === 'never' ) return;
        if ( $notify === 'error' && $success ) return;

        $subject = $success
            ? '[Media Lab Backup] ✅ Backup erfolgreich — ' . get_bloginfo( 'name' )
            : '[Media Lab Backup] ❌ Backup fehlgeschlagen — ' . get_bloginfo( 'name' );

        $body = $success
            ? "Das Backup für " . get_site_url() . " wurde erfolgreich abgeschlossen.\n\n"
            : "Das Backup für " . get_site_url() . " ist fehlgeschlagen.\n\nFehler: {$error}\n\n";

        $body .= "Log:\n" . implode( "\n", $this->log );

        wp_mail( $email, $subject, $body );
    }
}

// ── Globale Hilfsfunktion ─────────────────────────────────────────────────────

function mlbkp_get_settings(): array {
    $defaults = [
        // SFTP
        'sftp_host'         => '',
        'sftp_port'         => 22,
        'sftp_username'     => '',
        'sftp_password'     => '',
        'sftp_path'         => '/',
        'sftp_site_folder'  => '',
        'sftp_auth_method'  => 'password',
        'sftp_private_key'  => '',
        'sftp_key_passphrase' => '',

        // Backup-Scope
        'backup_database'  => '1',
        'backup_wpcontent' => '1',
        'backup_wpcore'    => '0',

        // Ausschlüsse
        'exclude_paths' => '',

        // Schedule
        'schedule'      => 'daily',
        'schedule_time' => '02:00',
        'schedule_day'  => 'monday',

        // Retention
        'retention_count' => 7,

        // Benachrichtigung
        'notify_email' => get_option( 'admin_email' ),
        'notify_on'    => 'error',
    ];

    $saved = get_option( 'mlbkp_settings', [] );
    return array_merge( $defaults, (array) $saved );
}