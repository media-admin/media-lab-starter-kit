# Changelog — Media Lab Backup

Alle wesentlichen Änderungen werden in dieser Datei dokumentiert.
Format: [Keep a Changelog](https://keepachangelog.com/de/1.0.0/)
Versionierung: [Semantic Versioning](https://semver.org/)

## [2.2.6] — 2026-10-27

### Fixed
- **Verzeichnisbaum in den Einstellungen lief bei Ordnern mit sehr vielen Dateien in einen 504** (`scan_dir_tree()` in
  `class-mlb-admin.php`): Der Baum las drei Ebenen tief und rief für jeden Eintrag `is_dir()` auf, lokal bei 727.825 Dateien in
  `uploads/2026/09`. Jetzt werden Ordner mit mehr als 2.000 Einträgen nicht weiter aufgelistet (der Ordner selbst bleibt im Baum
  sichtbar und auswählbar), und das Einlesen bricht nach 20 Sekunden ab, statt auf den Webserver zu warten

---

## [2.2.5] — 2026-10-27

### Fixed
- **Ausschlussliste wirkte nur an einem Teil der Stellen** (`MLBKP_Session::is_excluded()`): Jahresordner in `uploads/` wurden als
  `uploads/2026` geprüft, Monatsordner sowie Plugins und Themes dagegen als `wp-content/uploads/2026/09` beziehungsweise
  `wp-content/themes/…`. Ein Eintrag wie `themes/twentytwentyfive` oder `uploads/2026/09` blieb dadurch wirkungslos. Jetzt werden
  Pfade mit und ohne führendes `wp-content/` gleich behandelt. **Achtung:** Bisher wirkungslose Einträge in der Ausschlussliste
  greifen ab jetzt, das Voll-Backup enthält diese Ordner dann nicht mehr

### Changed
- **Live-Protokoll zeigt mehr**: Jeder Chunk speichert einen Hinweis, der in der Chunk-Zeile erscheint. Beim Datenbank-Chunk steht dort
  die Methode (`mysqldump` oder „⚠ PHP-Ersatz statt mysqldump" mit Grund) und die Dauer von Dump und Komprimieren sowie Upload, bei
  ZIP-Chunks die Dauer von ZIP und Upload und die Zahl der übersprungenen Dateien
- Die Startzeilen des Protokolls („Backup wird gestartet", „Session … geplant") bleiben stehen, nur die Chunk-Zeilen werden bei jeder
  Abfrage neu aufgebaut. Bisher löschte die erste Abfrage das ganze Fenster

---

## [2.2.4] — 2026-10-27

### Fixed
- **Datenbank-Dump mit dem `mysqldump` von MariaDB** (`class-mlb-database-backup.php`): Das Plugin hängte immer
  `--skip-column-statistics` und `--set-gtid-purged=OFF` an. Beide Optionen kennt nur der MySQL-Client, der MariaDB-Client bricht mit
  „unknown option" ab (Exit 7). Das Plugin fiel dann stillschweigend auf den PHP-Dump zurück, der bei großen Datenbanken
  (lokal 1,9 GB) Stunden braucht und im Chunk-Timeout endete (Fehler „Quelldatei für GZIP nicht gefunden"). Jetzt wird in
  `mysqldump --help` geprüft, welche der beiden Optionen das Programm kennt, und nur diese werden angehängt

---

## [2.2.3] — 2026-10-26

### Fixed
- **Ehrlicher Endstatus** (`MLBKP_Session::evaluate()`, genutzt in `class-mlb-admin.php` und `class-mlbkp-chunk-runner.php`): Ein Backup
  gilt nur als erfolgreich, wenn kein Chunk einen Fehler hat und die Gesamtgröße größer als 0 ist. Bisher zählte nur ein Fehler im
  Datenbank-Chunk, Fehler in Datei-Chunks ließen den Status auf „success". Bei Fehlern steht jetzt eine Meldung („n Chunk(s)
  fehlgeschlagen: …") im Protokoll, und die Benachrichtigungs-Mail wird auch bei Datei-Chunk-Fehlern verschickt
- **Oberfläche zeigte nach der Finalisierung immer „Backup erfolgreich abgeschlossen"** (`admin/js/admin.js`), auch bei Status
  `error` und „Gesamt: 0 B". Jetzt wird der Status der Antwort ausgewertet, bei Fehlern erscheint die rote Meldung mit der
  Fehlermeldung vom Server. `ajax_finalize_session` liefert dafür `error_message` mit und schreibt sie ins Protokoll

---

## [2.2.2] — 2026-10-26

### Fixed
- **Datenbank-Chunk fiel ohne `mysqldump` im PATH auf den PHP-Dump zurück und lief ins Timeout** (`class-mlb-database-backup.php`).
  Unter Valet (macOS) startet PHP-FPM ohne `PATH`, `mysqldump` wurde nicht gefunden. Bei großen Datenbanken (lokal 1,9 GB)
  sprengte der PHP-Dump das 6-Minuten-Limit des Datenbank-Chunks, und die Läufe brachen nach Stunden mit
  „Connection closed prematurely" ab. Jetzt sucht das Plugin `mysqldump` zuerst über den PATH und dann an bekannten Orten
  (Homebrew `mariadb*`/`mysql*`, `/usr/local/bin`, `/opt/homebrew/bin`, `/usr/bin`, MAMP) und ruft den gefundenen Pfad auf.
  Der PHP-Ersatz bleibt als letzter Ausweg erhalten

---

## [2.2.1] — 2026-10-25

### Fixed
- **Einstellungsseite brach bei sehr vielen Dateien mit einem fatalen Fehler ab** (`Maximum execution time of 30 seconds
  exceeded`, `class-mlb-file-backup.php`, `estimate_size()`). Die Größenanzeige für `wp-content/` zählte bei jedem Seitenaufruf
  alle Dateien (lokal rund 750.000 in `uploads`). Jetzt `estimate_size_cached()`: Zeitbudget von 3 Sekunden und Zwischenspeicher
  von 12 Stunden. Wurde die Zählung abgebrochen, steht „mind. …" statt „~…". `estimate_size()` selbst behält sein Verhalten,
  wenn man kein Zeitbudget übergibt. Das Backup selbst ist nicht betroffen

---

## [2.2.0] — 2026-09-11

### Added
- **Wiederherstellung (Restore)** — neuer Tab „Wiederherstellen" im Admin-Bereich
  - Neue `manifest.json` pro Backup-Session macht Chunks maschinenlesbar
    (Label, Original-Pfad, Dateiname) statt aus sanitized ZIP-Dateinamen
    zurückgerechnet zu werden. Ältere Sessions ohne Manifest funktionieren
    über einen Fallback weiter (Labels dann unzuverlässig rekonstruiert,
    Zielpfad muss im UI bestätigt werden)
  - Drei Stufen: (1) ganze Chunks zurückspielen, (2) einzelne Dateien/Ordner
    innerhalb eines Chunks auswählen, (3) einzelne DB-Tabellen selektiv
    importieren statt komplettem Dump
  - Chunk-weiser Ablauf über WP-Cron (analog zum Backup selbst), damit große
    Wiederherstellungen nicht an PHP-Timeouts auf Shared Hosting scheitern
  - **Automatische Sicherheitskopie** jeder überschriebenen Datei vor dem
    Restore nach `wp-content/mlbkp-restore-safety/<restore_id>/` (wird nicht
    automatisch gelöscht)
  - Quote-bewusster, streamender SQL-Statement-Parser (`MLBKP_SQL_Parser`)
    für selektiven Tabellen-Import aus dem SQL-Dump, batch-weise fortsetzbar
  - Neue Klassen: `MLBKP_Manifest`, `MLBKP_SQL_Parser`, `MLBKP_Restore_Session`,
    `MLBKP_Restore_Runner`, `MLBKP_Restore_Admin`; neuer Trait
    `MLBKP_SFTP_Restore` (erweitert `MLBKP_SFTP` um Lese-/Download-Operationen,
    ohne die bestehende Klasse zu verändern)

### Fixed
_(während der Restore-Entwicklung gefunden, betrifft ausschließlich den neuen Code)_
- Datei-Auswahl im Restore-Wizard zählte nicht als Element-Auswahl, wenn nur
  einzelne Dateien über „Nur bestimmte Dateien …" markiert wurden, ohne die
  übergeordnete Checkbox manuell zu setzen — wird jetzt automatisch mit angehakt
- Pfad der Sicherheitskopie enthielt bei ZIP-Chunks eine irreführende doppelte
  Ordner-Verschachtelung (Plugin-/Ordnername erschien zweimal im Pfad)

---

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
