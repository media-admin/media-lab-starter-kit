<?php
/**
 * Heartbeat-Runner Config — TEMPLATE
 *
 * Diese Datei zu `heartbeat-runner.config.php` kopieren (gleiches
 * Verzeichnis) und mit den echten Tokens befüllen. Die Kopie ist per
 * .gitignore vom Commit ausgeschlossen - NICHT versehentlich umbenennen
 * und committen.
 *
 * Tokens findest du im jeweiligen Kunden-Backend unter:
 * Agency Core → Heartbeat Monitoring
 *
 * Das alte flache Format (`'domain' => 'TOKEN'`) funktioniert weiterhin,
 * kann aber keine Alarm-Mail und keine Dev-Seiten.
 */

return [
    // Empfänger für Alarme (Installer offen, Heartbeat-Route fehlt).
    // Ohne Eintrag erscheinen Alarme nur in heartbeat-runner.log.
    'alert_email' => 'it@example.at',

    // Domain => Heartbeat-Token. Jede Domain wird zusätzlich auf einen
    // offenen WordPress-Installer geprüft. Token null/'' = nur Installer-Check.
    'clients' => [
        'example-domain.at' => 'DEIN_TOKEN_HIER',
        // 'weitere-domain.org' => 'TOKEN',
    ],

    'installer_check' => [
        // 'enabled'    => true,
        // Admin-Pfade relativ zur Site-URL (Standard: /cms/wp-admin und /wp-admin).
        // 'paths'      => [ '/cms/wp-admin', '/wp-admin' ],
        // Zusätzliche Basis-URLs ohne Heartbeat, z.B. Dev-/Staging-Ordner:
        'extra_urls' => [
            // 'https://dev.example.at/kunde-a',
        ],
    ],
];
