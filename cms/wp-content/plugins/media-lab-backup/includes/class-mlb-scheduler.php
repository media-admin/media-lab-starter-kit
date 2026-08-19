<?php
defined( 'ABSPATH' ) || exit;

/**
 * MLBKP_Scheduler
 *
 * Verwaltet WP-Cron-Jobs für automatische Backups.
 */
class MLBKP_Scheduler {

    const CRON_HOOK_DAILY   = 'mlbkp_cron_backup_daily';
    const CRON_HOOK_WEEKLY  = 'mlbkp_cron_backup_weekly';
    const CRON_HOOK_CLEANUP = 'mlbkp_cron_cleanup';

    public static function init(): void {
        add_action( self::CRON_HOOK_DAILY,  [ self::class, 'run_scheduled_backup' ] );
        add_action( self::CRON_HOOK_WEEKLY, [ self::class, 'run_scheduled_backup' ] );
        add_action( 'mlbkp_run_async_backup', [ self::class, 'run_async_backup' ], 10, 2 );
        add_action( 'mlbkp_process_chunk', static function( string $session_id ) {
            MLBKP_Chunk_Runner::process( $session_id );
        } );
        add_action( self::CRON_HOOK_CLEANUP, [ self::class, 'run_cleanup' ] );
        add_filter( 'cron_schedules', [ self::class, 'add_cron_intervals' ] );
    }

    /**
     * Plant einen Chunk und triggert WP-Cron.
     * Falls WP_ALTERNATE_CRON gesetzt oder lokal: läuft sofort direkt.
     */
    public static function schedule_chunk( string $session_id ): void {
        wp_schedule_single_event( time() - 1, 'mlbkp_process_chunk', [ $session_id ] );
        spawn_cron();
    }

    public static function activate(): void {
        self::reschedule();
        if ( ! wp_next_scheduled( self::CRON_HOOK_CLEANUP ) ) {
            wp_schedule_event( time(), 'hourly', self::CRON_HOOK_CLEANUP );
        }
    }

    public static function deactivate(): void {
        self::clear_all();
        wp_clear_scheduled_hook( self::CRON_HOOK_CLEANUP );
    }

    public static function run_cleanup(): void {
        MLBKP_Logger::cleanup_timed_out_jobs();
        MLBKP_Logger::prune( 200 );
    }

    /**
     * Wird von der Einstellungsseite aufgerufen, nachdem Settings gespeichert wurden.
     */
    public static function reschedule(): void {
        self::clear_all();

        $settings = mlbkp_get_settings();
        $schedule = $settings['schedule'] ?? 'none';

        if ( $schedule === 'none' ) return;

        $time = self::calculate_next_run( $settings );

        if ( $schedule === 'daily' ) {
            wp_schedule_event( $time, 'mlbkp_daily', self::CRON_HOOK_DAILY );
        } elseif ( $schedule === 'weekly' ) {
            wp_schedule_event( $time, 'mlbkp_weekly', self::CRON_HOOK_WEEKLY );
        }
    }

    /**
     * Wird vom asynchronen Cron-Job aufgerufen (manuelles Backup via Admin-UI).
     * Log-Eintrag existiert bereits — Runner übernimmt ab dem SFTP-Schritt.
     */
    public static function run_async_backup( int $log_id, string $type ): void {
        $runner = new MLBKP_Backup_Runner();
        $runner->run_from_log_id( $log_id, $type );
    }

    public static function run_scheduled_backup(): void {
        $settings = mlbkp_get_settings();
        $type     = self::determine_backup_type( $settings );

        // Session + Chunk-Queue erstellen (genau wie manuelles Backup)
        $session = MLBKP_Session::create( $type, 'cron', $settings );

        // Ersten Chunk sofort starten
        self::schedule_chunk( $session['id'] );
    }

    public static function add_cron_intervals( array $schedules ): array {
        $schedules['mlbkp_daily'] = [
            'interval' => DAY_IN_SECONDS,
            'display'  => __( 'Media Lab Backup — Täglich', 'media-lab-backup' ),
        ];
        $schedules['mlbkp_weekly'] = [
            'interval' => WEEK_IN_SECONDS,
            'display'  => __( 'Media Lab Backup — Wöchentlich', 'media-lab-backup' ),
        ];
        return $schedules;
    }

    // ── Status ───────────────────────────────────────────────────────────────

    public static function get_next_run(): ?string {
        $next = wp_next_scheduled( self::CRON_HOOK_DAILY )
             ?: wp_next_scheduled( self::CRON_HOOK_WEEKLY );

        if ( ! $next ) return null;

        return wp_date( 'd.m.Y H:i', $next );
    }

    public static function is_scheduled(): bool {
        return (bool) (
            wp_next_scheduled( self::CRON_HOOK_DAILY ) ||
            wp_next_scheduled( self::CRON_HOOK_WEEKLY )
        );
    }

    // ── Private ──────────────────────────────────────────────────────────────

    private static function clear_all(): void {
        wp_clear_scheduled_hook( self::CRON_HOOK_DAILY );
        wp_clear_scheduled_hook( self::CRON_HOOK_WEEKLY );
    }

    /**
     * Berechnet den nächsten Ausführungszeitpunkt basierend auf den Einstellungen.
     */
    private static function calculate_next_run( array $settings ): int {
        $time_str = $settings['schedule_time'] ?? '02:00';
        $day      = $settings['schedule_day']  ?? 'monday';
        $schedule = $settings['schedule']      ?? 'daily';

        [ $hour, $minute ] = explode( ':', $time_str );

        $now = time();

        if ( $schedule === 'daily' ) {
            $next = mktime( (int) $hour, (int) $minute, 0 );
            if ( $next <= $now ) {
                $next += DAY_IN_SECONDS;
            }
            return $next;
        }

        // Wöchentlich: nächsten Wochentag finden
        $days_map = [
            'monday'    => 'Monday',
            'tuesday'   => 'Tuesday',
            'wednesday' => 'Wednesday',
            'thursday'  => 'Thursday',
            'friday'    => 'Friday',
            'saturday'  => 'Saturday',
            'sunday'    => 'Sunday',
        ];

        $day_name = $days_map[ $day ] ?? 'Monday';
        $next     = strtotime( "next {$day_name} {$hour}:{$minute}:00" );

        // Falls heute der richtige Tag ist und die Uhrzeit noch kommt
        if ( date( 'l' ) === $day_name ) {
            $today_run = mktime( (int) $hour, (int) $minute, 0 );
            if ( $today_run > $now ) {
                $next = $today_run;
            }
        }

        return $next;
    }

    private static function determine_backup_type( array $settings ): string {
        $db      = ! empty( $settings['backup_database'] );
        $content = ! empty( $settings['backup_wpcontent'] );
        $core    = ! empty( $settings['backup_wpcore'] );

        if ( $db && $content && $core ) return 'full';
        if ( $db && $content )          return 'full';
        if ( $db )                       return 'database';
        if ( $content )                  return 'wpcontent';
        if ( $core )                     return 'wpcore';

        return 'full';
    }
}
