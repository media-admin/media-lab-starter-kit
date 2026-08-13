#!/bin/bash
# Setup heartbeat-runner cron job (alle 10 Minuten)
# Auf dem zentralen Heartbeat-Dispatcher-Host ausfuehren (NICHT auf den
# einzelnen Kunden-Hosting-Accounts) - der Server, der alle Client-Domains
# erreichen und ihre Heartbeat-Endpunkte anpingen kann.
#
# Voraussetzung: heartbeat-runner.config.php muss bereits mit den echten
# Tokens befuellt in diesem Verzeichnis liegen (siehe
# heartbeat-runner.config.example.php als Vorlage) - das Script prueft
# das vor dem Eintragen, um keinen von vornherein garantiert
# fehlschlagenden Cronjob einzurichten.

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
HEARTBEAT_SCRIPT="$SCRIPT_DIR/heartbeat-runner.php"
CONFIG_FILE="$SCRIPT_DIR/heartbeat-runner.config.php"

if [ ! -f "$CONFIG_FILE" ]; then
    echo "✗ Fehlt: $CONFIG_FILE"
    echo "Bitte zuerst heartbeat-runner.config.example.php kopieren, umbenennen"
    echo "und mit den echten Tokens befuellen (siehe Agency Core -> Heartbeat"
    echo "Monitoring im jeweiligen Kunden-Backend)."
    exit 1
fi

PHP_BIN="$(command -v php)"
if [ -z "$PHP_BIN" ]; then
    echo "✗ PHP-Binary nicht gefunden (command -v php liefert nichts)."
    echo "Pfad ggf. manuell in diesem Script setzen (PHP_BIN=...)."
    exit 1
fi

# Add cron job (alle 10 Minuten)
(crontab -l 2>/dev/null; echo "*/10 * * * * $PHP_BIN $HEARTBEAT_SCRIPT >> $SCRIPT_DIR/heartbeat-runner.log 2>&1") | crontab -

echo "✓ Cron job setup complete"
echo "Heartbeat-Runner läuft ab sofort alle 10 Minuten."
echo "Log-Datei: $SCRIPT_DIR/heartbeat-runner.log (waechst unbegrenzt, Rotation selbst einrichten)"
