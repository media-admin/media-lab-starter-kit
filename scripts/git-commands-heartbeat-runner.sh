#!/bin/bash
# heartbeat-runner.php + Setup-Script ins Repo aufnehmen (Struktur-Standardisierung)
# Vom Repo-Root ausfuehren: bash git-commands-heartbeat-runner.sh

set -e

git add scripts/heartbeat-runner.php scripts/heartbeat-runner.config.example.php scripts/setup-heartbeat-cron.sh
git commit -m "docs: heartbeat-runner.php als Template ins Repo aufgenommen

War bisher nur lokal vorhanden, obwohl media-lab-agency-core/README.md
bereits darauf verweist ('siehe heartbeat-runner.php-Beispiel im
Starter-Kit-Root'). Nach scripts/ statt Root platziert - konsistent mit
bestehender Konvention (vite-dev.mjs, Deploy-Scripts, setup-cron.sh
liegen ebenfalls dort).

Bewusst token-frei: liest echte Zugangsdaten aus
scripts/heartbeat-runner.config.php (gitignored, siehe naechster Commit),
Kopiervorlage scripts/heartbeat-runner.config.example.php liegt im Repo.
Verhindert, dass echte Heartbeat-Tokens in die Git-Historie gelangen.

setup-heartbeat-cron.sh ergaenzt (analog zu setup-cron.sh, aber fuer den
separaten Heartbeat-Dispatcher-Host - backup.sh/setup-cron.sh laufen auf
einem anderen Host, daher bewusst kein gemeinsames Script)."

git add .gitignore
git commit -m "chore: .gitignore um heartbeat-runner.config.php und .log ergaenzt

Verhindert versehentliches Committen der echten Tokens-Config bzw. der
unbegrenzt wachsenden Log-Datei."
