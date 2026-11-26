<?php
/**
 * Heartbeat-Runner: zentraler Dispatcher-Cronjob für Better-Stack-Push-
 * Monitoring mehrerer Client-Sites (siehe media-lab-agency-core, Modul
 * "Heartbeat Monitoring" / inc/heartbeat.php).
 *
 * Seit dem Sicherheitsvorfall vom 25.11.2026 prüft der Runner zusätzlich,
 * ob bei einer Site der WordPress-Installer von außen erreichbar ist
 * ("Installer-Exposure-Check", siehe MONITORING.md). Fehlt eine
 * wp-config.php, zeigt WordPress jedem Besucher das Installationsformular -
 * ein Angreifer kann dann eine eigene Datenbank eintragen, WordPress dort
 * installieren und über das Backend Plugins (Webshells) hochladen.
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
 *      Backend: Agency Core → Heartbeat Monitoring) und eine
 *      Alarm-Mailadresse (`alert_email`) eintragen.
 *   3. Cronjob alle 10 Minuten einrichten, z.B.:
 *      php /pfad/zu/scripts/heartbeat-runner.php
 *   4. Log-Datei (heartbeat-runner.log, selbes Verzeichnis) regelmäßig
 *      prüfen bzw. Rotation einrichten - wächst unbegrenzt.
 *
 * Aufrufe von Hand:
 *   php heartbeat-runner.php                       # Heartbeats + Installer-Check
 *   php heartbeat-runner.php --installer-only      # nur Installer-Check
 *   php heartbeat-runner.php --check-url=https://beispiel.at [--check-url=...] [--insecure]
 *                                                  # Einmal-Check beliebiger Adressen,
 *                                                  # ohne Config, ohne Mail (Exit-Code 2 = offen).
 *                                                  # --insecure: TLS-Prüfung aus, nur für lokale Valet-Zertifikate
 *
 * Hinweis zur Zuverlässigkeit: Better Stack erwartet pro Site einen
 * Heartbeat alle 10 Minuten mit 15 Minuten Kulanzzeit (Grace Period) -
 * bewusst großzügiger als der reine 10-Minuten-Takt, um einen einzelnen
 * ausgelassenen Cron-Tick (auf Shared Hosting nicht 100% ausgeschlossen)
 * abzufedern, ohne bei einem echten Ausfall zu spät zu warnen.
 *
 * Kompatibel mit PHP 7.4+ (Cron-Hosts laufen nicht immer auf 8.x).
 */

if ( ! defined( 'ML_RUNNER_DIR' ) ) {
    define( 'ML_RUNNER_DIR', __DIR__ );
}

/** Wie oft ein noch offener Befund erneut gemeldet wird (Sekunden). */
if ( ! defined( 'ML_REALERT_INTERVAL' ) ) {
    define( 'ML_REALERT_INTERVAL', 6 * 3600 );
}

/** Standard-Pfade des WordPress-Admin-Verzeichnisses, relativ zur Site-URL. */
const ML_DEFAULT_ADMIN_PATHS = [ '/cms/wp-admin', '/wp-admin' ];

// ── Logging, State, Alarm ────────────────────────────────────────────────────

function ml_log( string $message ): void {
    file_put_contents(
        ML_RUNNER_DIR . '/heartbeat-runner.log',
        '[' . date( 'Y-m-d H:i:s' ) . '] ' . $message . PHP_EOL,
        FILE_APPEND
    );
}

function ml_state_load(): array {
    $file = ML_RUNNER_DIR . '/heartbeat-runner.state.json';
    if ( ! is_file( $file ) ) {
        return [];
    }
    $data = json_decode( (string) file_get_contents( $file ), true );
    return is_array( $data ) ? $data : [];
}

function ml_state_save( array $state ): void {
    file_put_contents(
        ML_RUNNER_DIR . '/heartbeat-runner.state.json',
        json_encode( $state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ),
        LOCK_EX
    );
}

/**
 * Verschickt eine Alarm-Mail (falls `alert_email` konfiguriert ist) und
 * schreibt in jedem Fall ins Log. Gibt true zurück, wenn mail() die Nachricht
 * angenommen hat.
 */
function ml_alert( ?string $to, string $subject, string $body ): bool {
    ml_log( 'ALARM: ' . $subject . ' | ' . str_replace( [ "\r", "\n" ], ' ', $body ) );

    if ( ! $to ) {
        return false;
    }

    $host    = gethostname() ?: 'cron-host';
    $headers = "From: heartbeat-runner@{$host}\r\nContent-Type: text/plain; charset=UTF-8\r\nX-Priority: 1";
    $subject = '=?UTF-8?B?' . base64_encode( $subject ) . '?=';

    return (bool) @mail( $to, $subject, $body, $headers );
}

/**
 * Entscheidet, ob für einen Befund (Schlüssel) jetzt alarmiert werden soll:
 * beim ersten Auftreten und danach alle ML_REALERT_INTERVAL Sekunden, solange
 * der Befund bestehen bleibt. Aktualisiert den State.
 */
function ml_should_alert( array &$state, string $key, int $now ): bool {
    $entry = $state['open'][ $key ] ?? null;

    if ( $entry === null ) {
        $state['open'][ $key ] = [ 'since' => $now, 'last_alert' => $now ];
        return true;
    }

    if ( $now - (int) ( $entry['last_alert'] ?? 0 ) >= ML_REALERT_INTERVAL ) {
        $state['open'][ $key ]['last_alert'] = $now;
        return true;
    }

    return false;
}

// ── HTTP ─────────────────────────────────────────────────────────────────────

/**
 * Einfacher GET, folgt Weiterleitungen (http→https, www, Slash-Umleitungen).
 * Ohne Redirect-Folgen würde eine 301-Antwort fälschlich als "geschlossen"
 * gewertet.
 *
 * @return array{code:int, body:string, error:string}
 */
function ml_http_get( string $url, bool $follow = true, int $timeout = 10 ): array {
    $ch = curl_init( $url );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $ch, CURLOPT_TIMEOUT, $timeout );
    // Nur für lokale Entwicklungs-Zertifikate (Valet): --insecure bei --check-url.
    $verify = ! ( defined( 'ML_INSECURE_TLS' ) && ML_INSECURE_TLS );
    curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, $verify );
    curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, $verify ? 2 : 0 );
    curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, $follow );
    curl_setopt( $ch, CURLOPT_MAXREDIRS, 5 );
    curl_setopt( $ch, CURLOPT_USERAGENT, 'MediaLab-HeartbeatRunner/2' );

    $body = curl_exec( $ch );
    $code = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
    $err  = curl_error( $ch );
    curl_close( $ch );

    return [
        'code'  => $code,
        'body'  => $body === false ? '' : (string) $body,
        'error' => $err,
    ];
}

// ── Installer-Exposure-Check ─────────────────────────────────────────────────

/**
 * Erkennt anhand von Statuscode und Seiteninhalt, ob ein WordPress-Installer
 * erreichbar ist.
 *
 * Geschlossen (= gut) sind: 403/401 (Server-Sperre, Verzeichnisschutz), 404,
 * 409 ("wp-config.php already exists") und die Seite "Already Installed".
 * Offen ist nur eine 200-Antwort, die das Konfigurationsformular
 * (setup-config.php) bzw. das Installationsformular (install.php) enthält.
 *
 * @return string|null 'setup-config' | 'install' | null (nicht offen)
 */
function ml_classify_installer_response( int $code, string $body ): ?string {
    if ( $code !== 200 || $body === '' ) {
        return null;
    }

    // setup-config.php: Formularfeld für den Datenbanknamen bzw. Weiter-Link.
    if ( strpos( $body, 'name="dbname"' ) !== false
        || strpos( $body, 'setup-config.php?step=1' ) !== false ) {
        return 'setup-config';
    }

    // install.php: Formularfeld für den Site-Titel (nur wenn noch nicht installiert).
    if ( strpos( $body, 'name="weblog_title"' ) !== false ) {
        return 'install';
    }

    return null;
}

/**
 * Prüft eine Basis-URL (z.B. https://beispiel.at oder https://media-lab.dev/dev/kunde)
 * auf erreichbare WordPress-Installer unter den angegebenen Admin-Pfaden.
 *
 * @param string   $base_url    Ohne abschließenden Slash.
 * @param string[] $admin_paths Z.B. ['/cms/wp-admin', '/wp-admin'].
 * @param callable|null $fetch  Für Tests: fn(string $url): array{code,body,error}
 * @return array{exposed: array<int,array{url:string,type:string}>, unreachable: string[], probes: array<int,array{url:string,code:int,error:string}>}
 */
function ml_check_installer_exposure( string $base_url, array $admin_paths, ?callable $fetch = null ): array {
    $fetch   = $fetch ?? static function ( string $url ): array {
        return ml_http_get( $url, true );
    };
    $base    = rtrim( $base_url, '/' );
    $exposed = [];
    $failed  = [];
    $probes  = [];

    foreach ( $admin_paths as $path ) {
        $path = '/' . trim( (string) $path, '/' );

        foreach ( [ 'setup-config.php?step=1', 'install.php?step=1' ] as $file ) {
            $url = $base . $path . '/' . $file;
            $res = $fetch( $url );

            $probes[] = [ 'url' => $url, 'code' => (int) $res['code'], 'error' => (string) $res['error'] ];

            if ( $res['error'] !== '' && $res['code'] === 0 ) {
                $failed[] = $url;
                continue;
            }

            $type = ml_classify_installer_response( (int) $res['code'], (string) $res['body'] );
            if ( $type !== null ) {
                $exposed[] = [ 'url' => $url, 'type' => $type ];
            }
        }
    }

    return [ 'exposed' => $exposed, 'unreachable' => $failed, 'probes' => $probes ];
}

/**
 * Baut die Liste der zu prüfenden Basis-URLs aus der Config:
 * alle Clients (https://{domain}) plus `installer_check.extra_urls`.
 *
 * @return array<string,string> Label => Basis-URL
 */
function ml_installer_targets( array $clients, array $settings ): array {
    $targets = [];
    foreach ( array_keys( $clients ) as $domain ) {
        $targets[ (string) $domain ] = 'https://' . $domain;
    }
    foreach ( (array) ( $settings['extra_urls'] ?? [] ) as $url ) {
        $url = rtrim( (string) $url, '/' );
        if ( $url !== '' ) {
            $targets[ preg_replace( '#^https?://#', '', $url ) ] = $url;
        }
    }
    return $targets;
}

/**
 * Führt den Installer-Check für alle Ziele aus, alarmiert bei neuen/anhaltenden
 * Befunden und meldet Entwarnung, wenn ein Befund verschwunden ist.
 */
function ml_run_installer_checks( array $targets, array $admin_paths, ?string $alert_to, ?callable $fetch = null ): void {
    $state = ml_state_load();
    $now   = time();

    foreach ( $targets as $label => $base_url ) {
        $result = ml_check_installer_exposure( $base_url, $admin_paths, $fetch );
        $key    = 'installer:' . $label;

        if ( $result['exposed'] ) {
            $lines = [];
            foreach ( $result['exposed'] as $hit ) {
                $lines[] = "  - {$hit['url']}  ({$hit['type']})";
            }
            ml_log( "INSTALLER OFFEN bei {$label}: " . implode( ', ', array_column( $result['exposed'], 'url' ) ) );

            if ( ml_should_alert( $state, $key, $now ) ) {
                ml_alert(
                    $alert_to,
                    "[Media Lab] WordPress-Installer OFFEN: {$label}",
                    "Bei {$label} ist der WordPress-Installer von außen erreichbar:\n\n"
                    . implode( "\n", $lines ) . "\n\n"
                    . "Wahrscheinliche Ursache: wp-config.php fehlt (gelöscht, nicht hochgeladen, "
                    . "durch Deployment überschrieben) oder die Datenbank ist leer.\n"
                    . "Ein Angreifer kann hier eine eigene Datenbank eintragen und die Seite übernehmen.\n\n"
                    . "SOFORT: wp-config.php wiederherstellen bzw. die Installation sperren/löschen. "
                    . "Danach prüfen, ob wp-config.php verändert wurde (DB_HOST, DB_NAME) und ob "
                    . "unbekannte Plugins unter wp-content/plugins/ liegen.\n"
                    . "Dieser Alarm wird alle " . ( ML_REALERT_INTERVAL / 3600 ) . " Stunden wiederholt, solange der Befund besteht."
                );
            }
        } else {
            if ( isset( $state['open'][ $key ] ) ) {
                unset( $state['open'][ $key ] );
                ml_alert(
                    $alert_to,
                    "[Media Lab] Installer wieder geschlossen: {$label}",
                    "Der WordPress-Installer von {$label} ist nicht mehr erreichbar.\n"
                    . "Bitte trotzdem prüfen, ob die Seite in der Zwischenzeit übernommen wurde "
                    . "(wp-config.php, wp-content/plugins/, Admin-Benutzer)."
                );
            }
            if ( $result['unreachable'] ) {
                ml_log( "Installer-Check bei {$label}: nicht erreichbar (" . count( $result['unreachable'] ) . " Anfrage(n) ohne Antwort)" );
            } else {
                ml_log( "Installer-Check {$label}: geschlossen" );
            }
        }
    }

    ml_state_save( $state );
}

// ── Heartbeat ────────────────────────────────────────────────────────────────

/**
 * Hinweis auf eine ausgetauschte Installation: Antwortet WordPress zwar, kennt
 * aber die Heartbeat-Route des Agency-Core-Plugins nicht, läuft entweder das
 * Plugin nicht mehr oder die Seite wurde gegen eine fremde Installation
 * (andere Datenbank) getauscht.
 */
function ml_heartbeat_route_missing( int $code, string $body ): bool {
    return $code === 404 && strpos( $body, 'rest_no_route' ) !== false;
}

function ml_run_heartbeats( array $clients, ?string $alert_to ): void {
    $state = ml_state_load();
    $now   = time();

    foreach ( $clients as $domain => $token ) {
        if ( $token === null || $token === '' ) {
            continue; // Nur Installer-Check, kein Heartbeat (z.B. Dev-Seiten).
        }

        $url = "https://{$domain}/wp-json/medialab/v1/heartbeat?token={$token}";
        $res = ml_http_get( $url );

        if ( $res['body'] === '' && $res['error'] !== '' ) {
            ml_log( "cURL-FEHLER bei {$domain}: {$res['error']}" );
            continue;
        }

        ml_log( "{$domain} (HTTP {$res['code']}): {$res['body']}" );

        $key = 'route:' . $domain;
        if ( ml_heartbeat_route_missing( $res['code'], $res['body'] ) ) {
            if ( ml_should_alert( $state, $key, $now ) ) {
                ml_alert(
                    $alert_to,
                    "[Media Lab] Heartbeat-Route fehlt: {$domain}",
                    "{$domain} antwortet mit rest_no_route. Entweder ist das Plugin media-lab-agency-core "
                    . "deaktiviert, oder die Seite läuft mit einer fremden WordPress-Installation "
                    . "(z.B. nach einem Installer-Übernahmeversuch). Bitte wp-config.php (DB_HOST/DB_NAME) "
                    . "und die Plugin-Liste prüfen."
                );
            }
        } else {
            unset( $state['open'][ $key ] );
        }
    }

    ml_state_save( $state );
}

// ── Aufruf ───────────────────────────────────────────────────────────────────

if ( defined( 'ML_RUNNER_AS_LIBRARY' ) ) {
    return; // Nur Funktionen laden (Tests).
}

$options     = getopt( '', [ 'installer-only', 'insecure', 'check-url:' ] );
$check_urls  = isset( $options['check-url'] ) ? (array) $options['check-url'] : [];

// Einmal-Check ohne Config.
if ( $check_urls ) {
    if ( isset( $options['insecure'] ) ) {
        define( 'ML_INSECURE_TLS', true ); // Nur für lokale Dev-Zertifikate.
    }

    $any_exposed = false;
    foreach ( $check_urls as $url ) {
        $result = ml_check_installer_exposure( (string) $url, ML_DEFAULT_ADMIN_PATHS );

        foreach ( $result['probes'] as $probe ) {
            $status = $probe['code'] > 0 ? 'HTTP ' . $probe['code'] : 'keine Antwort (' . $probe['error'] . ')';
            echo "  {$status}  {$probe['url']}\n";
        }

        if ( $result['exposed'] ) {
            $any_exposed = true;
            foreach ( $result['exposed'] as $hit ) {
                echo "OFFEN          {$hit['url']}  ({$hit['type']})\n";
            }
        } elseif ( count( $result['unreachable'] ) === count( $result['probes'] ) ) {
            echo "KEINE ANTWORT  {$url}\n";
        } else {
            echo "geschlossen    {$url}\n";
        }
    }
    exit( $any_exposed ? 2 : 0 );
}

$config_file = ML_RUNNER_DIR . '/heartbeat-runner.config.php';

if ( ! file_exists( $config_file ) ) {
    fwrite( STDERR, "Fehlt: {$config_file}\n" );
    fwrite( STDERR, "Bitte heartbeat-runner.config.example.php kopieren, umbenennen und mit echten Tokens befuellen.\n" );
    exit( 1 );
}

$config = require $config_file;

if ( ! is_array( $config ) || empty( $config ) ) {
    fwrite( STDERR, "heartbeat-runner.config.php muss ein Array zurueckgeben.\n" );
    exit( 1 );
}

// Zwei Formate: neu ['clients' => [...], 'alert_email' => ..., 'installer_check' => [...]]
// oder altes flaches Array 'domain => token' (weiterhin unterstützt).
if ( isset( $config['clients'] ) && is_array( $config['clients'] ) ) {
    $clients  = $config['clients'];
    $alert_to = isset( $config['alert_email'] ) ? (string) $config['alert_email'] : null;
    $settings = isset( $config['installer_check'] ) && is_array( $config['installer_check'] ) ? $config['installer_check'] : [];
} else {
    $clients  = $config;
    $alert_to = null;
    $settings = [];
}

if ( empty( $clients ) && empty( $settings['extra_urls'] ) ) {
    fwrite( STDERR, "heartbeat-runner.config.php enthaelt keine Clients.\n" );
    exit( 1 );
}

if ( $alert_to === null ) {
    ml_log( 'HINWEIS: keine alert_email konfiguriert - Alarme erscheinen nur im Log.' );
}

if ( ! isset( $options['installer-only'] ) ) {
    ml_run_heartbeats( $clients, $alert_to );
}

if ( ( $settings['enabled'] ?? true ) ) {
    $admin_paths = ! empty( $settings['paths'] ) ? (array) $settings['paths'] : ML_DEFAULT_ADMIN_PATHS;
    ml_run_installer_checks( ml_installer_targets( $clients, $settings ), $admin_paths, $alert_to );
}
