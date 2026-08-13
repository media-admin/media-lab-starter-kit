<?php
/**
 * Heartbeat-Runner: zentraler Dispatcher-Cronjob für Better-Stack-Push-
 * Monitoring mehrerer Client-Sites (siehe media-lab-agency-core, Modul
 * "Heartbeat Monitoring" / inc/heartbeat.php).
 *
 * WICHTIG: Diese Datei ist ein TEMPLATE und enthält bewusst KEINE echten
 * Tokens. Tokens liegen in einer separaten Datei
 * `heartbeat-runner.config.php` neben diesem Script, die NICHT ins Git-
 * Repo eingecheckt wird (siehe .gitignore) - Kopiervorlage:
 * `heartbeat-runner.config.example.php`.
 *
 * Setup auf dem Cron-Host:
 *   1. Dieses Verzeichnis (scripts/) auf den Server kopieren, der den
 *      zentralen Cronjob ausführt (z.B. Hetzner Webhosting-Account von
 *      Media Lab, NICHT einer der Kunden-Hosting-Accounts selbst).
 *   2. `heartbeat-runner.config.example.php` zu
 *      `heartbeat-runner.config.php` kopieren und mit den echten
 *      Domain => Token-Paaren befüllen (Tokens aus dem jeweiligen
 *      Backend: Agency Core → Heartbeat Monitoring).
 *   3. Cronjob alle 10 Minuten einrichten, z.B.:
 *      php /pfad/zu/scripts/heartbeat-runner.php
 *   4. Log-Datei (heartbeat-runner.log, selbes Verzeichnis) regelmäßig
 *      prüfen bzw. Rotation einrichten - wächst unbegrenzt.
 *
 * Hinweis zur Zuverlässigkeit: Better Stack erwartet pro Site einen
 * Heartbeat alle 10 Minuten mit 15 Minuten Kulanzzeit (Grace Period) -
 * bewusst großzügiger als der reine 10-Minuten-Takt, um einen einzelnen
 * ausgelassenen Cron-Tick (auf Shared Hosting nicht 100% ausgeschlossen)
 * abzufedern, ohne bei einem echten Ausfall zu spät zu warnen.
 */

$config_file = __DIR__ . '/heartbeat-runner.config.php';

if ( ! file_exists( $config_file ) ) {
    fwrite( STDERR, "Fehlt: {$config_file}\n" );
    fwrite( STDERR, "Bitte heartbeat-runner.config.example.php kopieren, umbenennen und mit echten Tokens befuellen.\n" );
    exit( 1 );
}

$clients = require $config_file;

if ( ! is_array( $clients ) || empty( $clients ) ) {
    fwrite( STDERR, "heartbeat-runner.config.php muss ein Array 'domain => token' zurueckgeben.\n" );
    exit( 1 );
}

$log_file = __DIR__ . '/heartbeat-runner.log';

function ml_log( string $message ): void {
    global $log_file;
    file_put_contents( $log_file, '[' . date( 'Y-m-d H:i:s' ) . '] ' . $message . PHP_EOL, FILE_APPEND );
}

foreach ( $clients as $domain => $token ) {
    $url = "https://{$domain}/wp-json/medialab/v1/heartbeat?token={$token}";

    $ch = curl_init( $url );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $ch, CURLOPT_TIMEOUT, 10 );
    curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, true );
    curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );

    $result    = curl_exec( $ch );
    $http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
    $error     = curl_error( $ch );
    curl_close( $ch );

    if ( $result === false ) {
        ml_log( "cURL-FEHLER bei {$domain}: {$error}" );
    } else {
        ml_log( "{$domain} (HTTP {$http_code}): {$result}" );
    }
}
