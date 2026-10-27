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

**Hinweise zur Schreibweise und zum Verzeichnisbaum**

- Ob ein Pfad mit oder ohne `wp-content/` davor steht, spielt keine Rolle (seit 2.2.5): `uploads/2026` und `wp-content/uploads/2026` wirken gleich.
- Ein Eintrag schließt den Ordner samt allem darin aus. `uploads/2026` lässt auch alle Monatsordner darunter (`uploads/2026/08`, `uploads/2026/09`, …) weg.
- Der Verzeichnisbaum listet Ordner mit mehr als 2.000 Einträgen nicht weiter auf (seit 2.2.6), der Ordner selbst bleibt aber auswählbar. Lädt der Baum nicht oder fehlt ein Pfad, schreibt man ihn einfach in die Textarea.
- Ein Medienordner mit sehr vielen Dateien (lokal getestet: 727.825 Dateien und 25 GB in einem Monatsordner) schafft die Zeitgrenze für einen Datei-Chunk nicht (siehe „Live-Protokoll und Zeitgrenzen“) und gehört dann in die Ausschlussliste oder muss anders gesichert werden.

---

## Live-Protokoll und Zeitgrenzen

Beim Start einer Sicherung zeigt das Live-Protokoll die Session und die geplanten Chunks. Danach steht je Chunk die Größe der Datei und die Dauer der einzelnen Schritte, zum Beispiel:

```
Datenbank (74.21 MB) — mysqldump, Dump und Komprimieren 84 s, Upload 17 s
wp-content/plugins/woocommerce (56.08 MB) — ZIP 5 s, Upload 14 s
```

Bei der Datenbank nennt die Zeile die verwendete Methode (`mysqldump` oder PHP-Ersatz). Mit ⏭ markierte Ordner wurden übersprungen.

### Zeitgrenzen

| Was | Grenze |
|---|---|
| Datenbank-Chunk | 6 Minuten, danach markiert der Wächter ihn als Fehler |
| Datei-Chunk | 20 Minuten, danach markiert der Wächter ihn als Fehler |
| Gesamter Lauf | 240 Minuten, danach setzt der stündliche WP-Cron-Job einen noch laufenden Protokolleintrag auf Fehler |

Der Wächter markiert nur den Chunk, er beendet den laufenden Prozess nicht. Als Anhaltspunkt: Bei einem Upload mit rund 4 MB pro Sekunde (Messwert vom lokalen Rechner, Oktober 2026) schafft ein Datei-Chunk in 20 Minuten etwa 5 GB. Die Datenbank (74 MB gepackt) brauchte 101 Sekunden.

### Größe der Dateien auf der Storage Box

Die ZIP-Dateien werden praktisch ohne Komprimierung erzeugt (Beispiel `wpallimport`: ZIP-Datei 2.230 MB bei 2.338.729.693 Byte Inhalt). Die Storage Box zeigt bei `du` den belegten Platz, und der kann kleiner sein als die Dateigröße (Beobachtung: `wpallimport` 357 MB statt 2.230 MB, vermutlich durch Komprimierung auf der Box). Die tatsächliche Dateigröße zeigt ein Download mit `scp`, die Integrität prüft `unzip -tq <Datei>`.

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

**Verzeichnisbaum lädt nicht („Verbindungsfehler“, 504)**
→ Bis 2.2.5 las der Baum alle Ordner drei Ebenen tief und lief bei Ordnern mit sehr vielen Dateien in ein Zeitlimit. Ab 2.2.6 werden Ordner mit mehr als 2.000 Einträgen nicht aufgelistet. Notfalls den Pfad direkt in die Textarea schreiben.

**Datenbank-Chunk dauert Stunden oder endet mit „Quelldatei für GZIP nicht gefunden“**
→ Ursache war ein `mysqldump` (MariaDB), der `--skip-column-statistics` und `--set-gtid-purged` nicht kennt und mit Exit-Code 7 endete. Das Plugin fiel dann auf den langsamen PHP-Dump zurück. Ab 2.2.4 werden diese Optionen nur angehängt, wenn `mysqldump --help` sie nennt. Ab 2.2.2 sucht das Plugin `mysqldump` auch an bekannten Pfaden (zum Beispiel Homebrew unter Valet), wenn `PATH` im Webprozess fehlt. Im Live-Protokoll steht in der Datenbank-Zeile, welche Methode lief.

**„du“ auf der Storage Box zeigt weniger, als das Plugin meldet**
→ Siehe „Größe der Dateien auf der Storage Box“. Mit `scp` laden und mit `unzip -tq` prüfen.

**Nach einem Chunk-Timeout liegen Chunks doppelt in der Session (bekannte Einschränkung, Stand 2.2.6)**
→ Beobachtung vom 27.10.2026: Nach einem Timeout lagen alle folgenden Chunks zweimal auf der Box. Vermutete Ursache: Der Wächter startet die nächste Chunk-Kette, während der ursprüngliche Prozess weiterläuft. Das kostet Platz und Zeit. Abhilfe bis zur Korrektur: den Chunk, der ins Zeitlimit läuft, in die Ausschlussliste aufnehmen. Siehe `docs/BACKLOG.md` im Starter-Kit.

**Website-Unterordner wird verändert**
→ Sonderzeichen außer `a-z A-Z 0-9 . - _` werden durch `-` ersetzt. Punkte bleiben erhalten.

---

## Lizenz

GPL v2 or later — https://www.gnu.org/licenses/gpl-2.0.html
