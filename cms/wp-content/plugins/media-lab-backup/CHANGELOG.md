# Changelog — Media Lab Backup

Alle wesentlichen Änderungen werden in dieser Datei dokumentiert.
Format: [Keep a Changelog](https://keepachangelog.com/de/1.0.0/)
Versionierung: [Semantic Versioning](https://semver.org/)

## [2.1.0] - 2026-08-21

### Fixed
- **Race Condition: `cleanup()` löschte Dateien paralleler Backup-Jobs**
  (`includes/class-mlb-backup-runner.php`, `includes/class-mlbkp-chunk-runner.php`)
  – `MLBKP_Backup_Runner` (Legacy) und `MLBKP_Chunk_Runner`
  (Session/Chunk-Architektur) teilten sich dasselbe physische
  Temp-Verzeichnis. `cleanup()` im Legacy-Runner löschte per globalem
  `glob()` alle `*.zip`/`*.sql`-Dateien darin, unabhängig vom erzeugenden
  Job — ein paralleler Job konnte dadurch mitten im SFTP-Upload seine
  eigene Datei verlieren. Live reproduziert (siehe BACKLOG.md).
  Fix: Pro-Job- bzw. Pro-Session-Unterverzeichnis (`job-{log_id}/` bzw.
  `session-{session_id}/`) statt gemeinsamem Ordner; `cleanup()` entfernt
  jeweils nur noch das eigene Unterverzeichnis. `MLBKP_Chunk_Runner` hatte
  bisher zudem kein Verzeichnis-Cleanup — ergänzt (`cleanup_temp_dir()`),
  da Session-Unterordner sonst dauerhaft verwaist wären.
- **`ajax_cleanup_stuck()` synchronisierte `MLBKP_Session` nicht**
  (`includes/class-mlb-admin.php`) – „Hängende Jobs bereinigen" setzte nur
  die `status`-Spalte in `wp_mlb_logs` auf `error`, ließ die zugehörige
  `MLBKP_Session`-Option (separat in `wp_options`) aber unverändert auf
  `running` stehen. Da die neue Live-Log-Resume-Funktion (s.u.)
  `MLBKP_Session::status` direkt prüft, blieb ein bereinigter Job für die
  UI dauerhaft "aktiv". Fix: alle noch laufenden Sessions werden jetzt
  zusätzlich über `MLBKP_Session::finish()` sauber abgeschlossen.

### Added
- **Live-Log-Persistenz beim Tab-Wechsel** (`includes/class-mlbkp-session.php`,
  `includes/class-mlb-admin.php`, `admin/js/admin.js`) – wechselte man
  während eines laufenden Backups vom "Backup starten"-Tab weg (echte
  Seiten-Navigation, kein SPA-Tab) und wieder zurück, ging der komplette
  Live-Log-Status verloren; der "Backup starten"-Button war wieder aktiv,
  ein versehentlicher Doppelstart einer zweiten Session war möglich.
  Neue Methode `MLBKP_Session::find_running()` erkennt eine aktuell
  laufende Session beim Seitenaufruf; das Frontend setzt das Polling in
  diesem Fall automatisch fort (Polling-Logik dafür in
  `startStatusPolling()` ausgelagert, von Klick-Handler und Resume-Block
  gemeinsam genutzt).

---

## [1.3.4] - 2026-08-04

### Fixed
- **Verwaister `caffeinate`-Prozess nach Backup-Abschluss** (`includes/class-mlb-backup-runner.php`)
  – `maybe_stop_caffeinate()` killte die per `$!` erfasste PID, die aber zu
  `launchctl` gehört, nicht zu `caffeinate` selbst (`launchctl asuser`
  reparented `caffeinate` als eigenständigen Prozess in der GUI-Session,
  siehe 1.3.3). Der `kill`-Aufruf lief dadurch ins Leere, `caffeinate` blieb
  nach jedem Backup dauerhaft aktiv (verifiziert: über 2 Stunden verwaister
  Prozess beobachtet, verhinderte unnötig weiterhin System-/Display-Sleep).
  Fix: `pkill -f 'caffeinate -d -i -s'` beendet gezielt anhand des
  Kommandostrings statt einer unzuverlässigen PID-Referenz. Verifiziert:
  `ps aux | grep caffeinate` liefert nach Backup-Abschluss keine Treffer mehr.

---

## [1.3.3] - 2026-08-03

### Fixed
- **caffeinate hält keine Power-Management-Assertion im WP-Cron-Loopback-
  Kontext** (`includes/class-mlb-backup-runner.php`) – der bisherige Fix
  (`nohup caffeinate -d -i -s`, siehe 1.3.2) startete den Prozess zwar
  korrekt (verifiziert via PPID 1), hielt aber im asynchronen
  WP-Cron-Kontext (php-fpm LaunchDaemon-Kontext, nicht die aktive
  GUI-Session) keine `IOPMAssertionCreate`-Assertion — der Mac schlief
  trotz laufendem `caffeinate`-Prozess ein. Fix: `launchctl asuser $(id -u)`
  reicht den Aufruf explizit in die GUI-Session des Users durch. Verifiziert
  via `pmset -g assertions` über zwei unabhängige Testläufe: alle drei
  Assertions (`PreventUserIdleSystemSleep`, `PreventUserIdleDisplaySleep`,
  `PreventSystemSleep`) aktiv.

---

## [1.3.2] — 2026-07-31

### Fixed
- **macOS-Sleep unterbricht lokale Backups (Laravel Valet)** — größere
  `wp-content`-Backups (mehrere GB, zehntausende Dateien) können 30–90+
  Minuten dauern. macOS legt den Rechner nach der konfigurierten Sleep-Zeit
  (oft 15 Min. Inaktivität) automatisch schlafen und unterbricht den
  PHP-Prozess mitten im Backup ("Unable to write X bytes" bzw.
  240-Minuten-Job-Timeout). Auf Production (Linux) tritt das nicht auf, da
  dort kein Sleep-Modus existiert.
  `MLBKP_Backup_Runner::execute()` startet jetzt via `maybe_start_caffeinate()`
  einen `caffeinate -d -i -s`-Prozess über `nohup … & echo $!` (nohup ist
  zwingend nötig, da sonst SIGHUP den Prozess sofort mit der Subshell
  beendet). `cleanup()` — welches bei Erfolg, Abbruch und Fehler garantiert
  läuft — beendet den Prozess wieder über `maybe_stop_caffeinate()`.
  Auf Production (Linux) ist die Methode ein reines No-Op (`PHP_OS_FAMILY`-Check).

---

## [1.3.0] — 2026-05-20

### Added
- **Abbrechen-Button** — erscheint während ein Backup läuft, setzt ein DB-Flag (`mlbkp_cancel_{id}` in wp_options), Runner prüft es an 6 Checkpoints (vor SFTP-Connect, vor/nach DB-Dump, vor/nach jedem Upload)
- **Neuer Status `cancelled`** — eigenes Badge in der Protokoll-Tabelle (lila), sauberer Cleanup von Temp-Dateien
- **Automatischer Job-Timeout** — Polling erkennt Jobs die länger als 60 Minuten laufen und markiert sie automatisch als Fehler; stündlicher WP-Cron (`mlbkp_cron_cleanup`) für serverseitige Bereinigung
- **`MLBKP_CancelledException`** — eigene Exception-Klasse für saubere Abbruch-Behandlung im Runner, getrennt von echten Fehlern

### Fixed
- Stündlicher Cleanup-Cron wird jetzt korrekt bei Plugin-Aktivierung eingetragen und bei Deaktivierung entfernt

---



### Changed
- **Asynchrones Backup via WP-Cron** — AJAX-Handler startet das Backup als sofortigen Einzel-Cron-Job und gibt umgehend zurück (kein 504 Gateway Timeout mehr auf Shared Hosting wie IONOS). Die Admin-UI pollt alle 4 Sekunden den Status und zeigt Ergebnis und Größe sobald der Job abgeschlossen ist.

### Added
- Neuer AJAX-Endpoint `mlbkp_check_status` — gibt Status, Fehlermeldung, Dateigröße und Dauer eines laufenden oder abgeschlossenen Backup-Jobs zurück
- `MLBKP_Backup_Runner::run_from_log_id()` — führt Backup mit bereits existierendem Log-Eintrag fort (für asynchronen Aufruf via Cron)
- `MLBKP_Scheduler::run_async_backup()` — Cron-Hook-Handler für manuelle Backups

---



### Fixed
- **mysqldump-Fallback greift jetzt automatisch** — wenn `mysqldump` zwar verfügbar ist aber beim Dump-Aufruf scheitert (Exit-Code 7, falscher Socket, fehlende Rechte etc.), wird automatisch auf den PHP-Fallback umgeschaltet statt das Backup abzubrechen
- **Verbessertes stderr-Logging** — `dump_via_mysqldump()` verwendet jetzt `proc_open()` statt `exec()`, stdout geht direkt in die Dump-Datei, stderr wird sauber getrennt erfasst und mit maskiertem Passwort ins Backup-Log geschrieben
- **Unix-Socket-Unterstützung** — `DB_HOST` wird korrekt behandelt wenn er ein Socket-Pfad ist (z.B. `/var/run/mysqld/mysqld.sock`)
- **`--skip-column-statistics` Flag** ergänzt (verhindert Fehler auf älteren MySQL/MariaDB-Versionen)
- `fallback_reason` wird im Live-Log des Backup-Runners ausgegeben

---



### Added
- **SSH-Key-Authentifizierung** als Alternative zu Passwort (phpseclib3 `PublicKeyLoader`, unterstützt RSA, Ed25519, ECDSA)
- **Konfigurierbarer Website-Unterordner** (`sftp_site_folder`) — frei wählbar mit automatisch generiertem Domain-Vorschlag als Placeholder
- **Interaktiver Verzeichnisbaum** für Ausschlüsse — AJAX-geladen, Expand/Collapse, Checkboxen bidirektional mit Textarea synchronisiert, Badge für Anzahl aktiver Ausschlüsse
- **WP-CLI-Integration** mit vier Befehlen:
  - `wp mlbkp backup [--type=<type>]` — Backup ausführen
  - `wp mlbkp status` — Konfiguration und letzten Backup-Status anzeigen
  - `wp mlbkp test` — SFTP-Verbindung testen
  - `wp mlbkp logs [--limit=<n>] [--format=<format>]` — Protokoll anzeigen

### Fixed
- **Klassenprefix `MLB_` → `MLBKP_`** — Kollision mit `media-lab-bookings` behoben
- **Doppeltes Plugin-Loading beim ZIP-Upload** verhindert (`defined('MLBKP_VERSION')`-Guard + `class_exists()`-Checks)
- **JS-Fehler auf „Backup starten"-Tab** — `getExcludeLines()` warf `TypeError` wenn `#exclude_paths` nicht im DOM war, was alle nachfolgenden Event-Handler (Backup-Button, Typ-Auswahl) deaktivierte
- **Website-Unterordner behält Punkte** — `sanitize_title()` durch eigene Sanitierung ersetzt, `stadtwirt-berndorf.at` bleibt `stadtwirt-berndorf.at`

### Changed
- Standard-Remote-Basispfad von `/backups` auf `/` geändert (passend für Sub-Account mit eingeschränktem Root-Verzeichnis)
- Settings-UI: Auth-Tabs (Passwort / SSH-Key) mit Ein-/Ausblenden der jeweiligen Felder
- Ausschluss-Textarea durch zweigeteiltes Layout (Baum + Textarea) ersetzt

---

## [1.0.0] — 2026-05-13

### Added
- Initiale Veröffentlichung
- Datenbank-Backup via `mysqldump` mit PHP-Fallback (chunk-weise INSERT-Generierung)
- GZIP-Komprimierung des SQL-Dumps
- Datei-Backup (`wp-content` / vollständiges WP-Verzeichnis) via PHP `ZipArchive`
- SFTP-Upload zur Hetzner Storage Box via phpseclib3 (Port 22)
- Automatische Verzeichnis-Erstellung auf dem Remote-Server (pro Website-Domain)
- Konfigurierbarer Backup-Scope: Datenbank, wp-content, WP-Core (einzeln oder kombiniert)
- WP-Cron-Integration: täglich oder wöchentlich, konfigurierbare Uhrzeit und Wochentag
- Manuelles Backup über Admin-UI mit Live-Log-Ausgabe
- SFTP-Verbindungstest direkt aus den Einstellungen
- Retention-Management: konfigurierbares Beibehalten der letzten N Backups
- E-Mail-Benachrichtigung: bei Fehler, immer oder nie
- Backup-Protokoll-Tabelle (`wp_mlb_logs`) mit Status, Größe, Dauer, Dateiname
- Admin-UI mit 3 Tabs: Einstellungen / Backup starten / Protokoll
- Ausschlüsse: konfigurierbare Liste auszuschließender Pfade (ein Pfad pro Zeile)
- Temp-Dateien in `wp-content/uploads/media-lab-backup/temp/` mit .htaccess-Schutz
- Sauberer Uninstall (Einstellungen, Tabelle, Temp-Verzeichnis, Cron-Jobs)
