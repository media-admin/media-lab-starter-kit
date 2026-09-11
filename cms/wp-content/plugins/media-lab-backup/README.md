# Media Lab Backup

WordPress-Backup-Plugin für automatische Sicherungen zur **Hetzner Storage Box** via SFTP.

Entwickelt von [Media Lab Tritremmel GmbH](https://media-lab.at).

---

## Features

- ✅ **Datenbank-Backup** — SQL-Dump via `mysqldump` (mit reinem PHP-Fallback + GZIP)
- ✅ **Datei-Backup** — `wp-content/` oder vollständiges WP-Verzeichnis als ZIP
- ✅ **SFTP-Upload** — via phpseclib3, Port 22, keine Server-Extension nötig
- ✅ **Passwort- & SSH-Key-Authentifizierung** — RSA, Ed25519, ECDSA
- ✅ **Konfigurierbarer Website-Unterordner** — frei wählbar, mit Domain-Vorschlag
- ✅ **Retention** — automatisches Löschen alter Backups auf der Storage Box
- ✅ **WP-Cron** — täglich oder wöchentlich, konfigurierbare Uhrzeit & Wochentag
- ✅ **Manuelles Backup** — jederzeit aus dem Admin-Bereich mit Live-Log
- ✅ **Interaktiver Ausschluss-Picker** — Verzeichnisbaum mit Checkboxen, Textarea-Sync
- ✅ **E-Mail-Benachrichtigung** — bei Fehler, immer oder nie
- ✅ **WP-CLI-Integration** — 4 Befehle für Automatisierung & Monitoring
- ✅ **Backup-Protokoll** — alle Runs mit Status, Größe, Dauer und Fehlermeldung
- ✅ **Wiederherstellung (Restore)** — vollständig oder selektiv (Dateien, Ordner, DB-Tabellen), mit automatischer Sicherheitskopie vor jedem Überschreiben

---

## Voraussetzungen

- WordPress 6.0+
- PHP 8.0+
- Composer (für lokale Entwicklung)
- PHP-Extensions: `zip`, `zlib`
- Hetzner Storage Box mit SFTP-Zugang (Sub-Account empfohlen)

---

## Installation

### 1. Plugin-Verzeichnis in wp-content/plugins/ ablegen

```bash
# Via Git (Starter Kit)
git pull

# Oder ZIP manuell hochladen via WP-Admin → Plugins → Plugin hochladen
```

### 2. Composer-Abhängigkeiten installieren

Nur nötig wenn `vendor/` nicht im Repo liegt:

```bash
cd wp-content/plugins/media-lab-backup
composer install --no-dev --optimize-autoloader
```

### 3. Plugin aktivieren

WP-Admin → Plugins → **Media Lab Backup** aktivieren.

### 4. Konfigurieren

**WP-Admin → ML Backup → Einstellungen**

---

## Hetzner Storage Box einrichten

### Empfohlen: Sub-Account

1. Hetzner Robot → Storage Box → **Sub-Account erstellen**
2. Basisverzeichnis auf gewünschten Backup-Ordner setzen (z.B. `website-backups`)
3. Folgende Optionen aktivieren:
   - ✅ **SSH erlauben**
   - ✅ **Externe Erreichbarkeit**
4. Speichern und 2–3 Minuten warten (Propagation)

### Verbindungsdaten im Plugin

| Feld | Wert |
|---|---|
| Hostname | `uXXXXXX.your-storagebox.de` |
| Port | `22` |
| Benutzername | `uXXXXXX-sub1` (Sub-Account) |
| Remote-Basispfad | `/` (wenn Sub-Account mit eingeschränktem Root) |
| Website-Unterordner | z.B. `meinekunde.at` |

> **Wichtig:** Wenn der Sub-Account ein Basisverzeichnis hat (z.B. `website-backups`), ist sein Root bereits dieses Verzeichnis → Basispfad im Plugin auf `/` setzen.

---

## SSH-Key-Authentifizierung

### Key generieren

```bash
ssh-keygen -t ed25519 -C "media-lab-backup" -f ~/.ssh/hetzner_backup
```

### Public Key auf der Storage Box hinterlegen

```bash
# Via SFTP (Beispiel mit sftp-Kommando)
sftp uXXXXXX-sub1@uXXXXXX.your-storagebox.de
sftp> mkdir .ssh
sftp> put ~/.ssh/hetzner_backup.pub .ssh/authorized_keys
sftp> chmod 600 .ssh/authorized_keys
```

### Im Plugin konfigurieren

1. Einstellungen → Authentifizierung → **SSH-Key** auswählen
2. Inhalt von `~/.ssh/hetzner_backup` (Private Key, **nicht** `.pub`) in das Textarea einfügen
3. Passphrase eintragen falls vorhanden
4. Verbindung testen

---

## Ausschlüsse konfigurieren

Im Tab **Einstellungen → Was soll gesichert werden?** den Verzeichnisbaum laden und gewünschte Ordner per Checkbox ausschließen. Alternativ direkt in die Textarea eintragen.

Pfade sind **relativ zu `wp-content/`**, ein Pfad pro Zeile:

```
cache
uploads/2020
uploads/2021
plugins/unused-plugin
themes/old-theme
```

---

## Verzeichnisstruktur auf der Storage Box

```
/                                    ← Sub-Account Root (= Basisverzeichnis)
└── stadtwirt-berndorf.at/           ← Website-Unterordner (frei konfigurierbar)
    ├── db-backup-2026-05-13_02-00-00.sql.gz
    ├── db-backup-2026-05-14_02-00-00.sql.gz
    ├── files-wpcontent-2026-05-13_02-00-00.zip
    └── files-wpcontent-2026-05-14_02-00-00.zip
```

---

## Wiederherstellung (Restore)

**WP-Admin → ML Backup → ⏪ Wiederherstellen**

Dreistufiger Assistent:

1. **Sicherung wählen** — Liste aller Session-Ordner auf der Storage Box, neueste zuerst
2. **Was wiederherstellen?** — pro Chunk (Datenbank, Plugin-/Theme-Ordner, Uploads-Monat, …)
   wahlweise:
   - **Vollständig** — der ganze Chunk wird zurückgespielt
   - **Nur bestimmte Dateien …** — Datei-/Ordner-Baum innerhalb des Chunks, einzelne
     Einträge gezielt auswählbar
   - Bei der Datenbank zusätzlich **Nur bestimmte Tabellen …** — einzelne Tabellen
     statt des kompletten Dumps
3. **Bestätigen** — Übersicht der Auswahl, danach läuft die Wiederherstellung
   chunk-/tabellenweise über WP-Cron mit Live-Fortschritt

**Sicherheit:** Vor jedem Überschreiben einer bestehenden Datei wird automatisch
eine Kopie nach `wp-content/mlbkp-restore-safety/<restore_id>/` angelegt. Diese
Kopien werden **nicht automatisch gelöscht** — nach erfolgreicher Prüfung des
Ergebnisses manuell aufräumen.

**Manifest:** Jede ab Version 2.2.0 erstellte Backup-Session enthält eine
`manifest.json`, die Chunks maschinenlesbar macht (Label, Original-Pfad,
Dateiname). Ältere Sessions ohne Manifest funktionieren weiterhin über einen
Fallback — Zielpfade sind dann geschätzt und müssen im UI bestätigt werden.

**Grenzen:**
- Ein laufender Restore lässt sich nur zwischen Items/Cron-Ticks abbrechen,
  nicht mitten in einem laufenden DB-Batch-Import oder einer ZIP-Extraktion
- Der Datei-Browser eines ZIP-Chunks lädt den Chunk temporär vollständig
  herunter, um den Inhalt aufzulisten
- Selektiver DB-Import überspringt `LOCK`/`UNLOCK TABLES`-Statements

---

## WP-CLI

```bash
# Vollständiges Backup ausführen
wp mlbkp backup

# Nur Datenbank sichern
wp mlbkp backup --type=database

# Nur wp-content sichern
wp mlbkp backup --type=wpcontent

# Nur WP-Core sichern
wp mlbkp backup --type=wpcore

# SFTP-Verbindung testen
wp mlbkp test

# Status & Konfiguration anzeigen
wp mlbkp status

# Backup-Protokoll anzeigen (Standard: 20 Einträge)
wp mlbkp logs
wp mlbkp logs --limit=50
wp mlbkp logs --format=json
wp mlbkp logs --format=csv
```

### Automatisierung via System-Cron (Alternative zu WP-Cron)

```bash
# Täglich um 02:00 Uhr (in crontab -e eintragen)
0 2 * * * cd /var/www/html && wp mlbkp backup --type=full --path=/var/www/html/cms --quiet
```

---

## Temp-Dateien

Backup-Dateien werden temporär gespeichert in:

```
wp-content/uploads/media-lab-backup/temp/
```

Das Verzeichnis ist durch `.htaccess` gegen direkten Webzugriff geschützt. Temp-Dateien werden nach erfolgreichem Upload automatisch gelöscht.

---

## Troubleshooting

**„phpseclib ist nicht installiert"**
→ `composer install --no-dev` im Plugin-Verzeichnis ausführen.

**„SFTP-Login fehlgeschlagen"**
→ Verbindungstest nutzen. Zugangsdaten, Port (22) und „SSH erlauben" im Hetzner Robot prüfen.

**„Connection closed by server"**
→ „Externe Erreichbarkeit" im Sub-Account aktivieren. Danach 2–3 Minuten warten.

**Backup timeout im Browser**
→ Für sehr große Sites Backup via WP-CLI oder WP-Cron starten.

**mysqldump nicht verfügbar**
→ Plugin verwendet automatisch den PHP-Fallback-Dump. Auf Shared Hosting normal.

**Website-Unterordner wird verändert**
→ Sonderzeichen außer `a-z A-Z 0-9 . - _` werden durch `-` ersetzt. Punkte bleiben erhalten.

---

## Lizenz

GPL v2 or later — https://www.gnu.org/licenses/gpl-2.0.html
