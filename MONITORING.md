# Monitoring & Error Tracking

## 📊 Dashboards

### Sentry
- **URL:** https://sentry.io/organizations/your-org/projects/your-project/
- **Purpose:** Error tracking & performance monitoring
- **Alerts:** Email + Slack on new issues *(Slack aktuell noch nicht eingerichtet, siehe unten)*

### Better Stack – Uptime
- **URL:** https://uptime.betterstack.com
- **Purpose:** HTTP-Uptime-Monitoring + Incident Management + Status Page
- **Monitors:**
  - Production: https://your-domain.com (Interval: 3 Min.)
  - Staging: https://staging.your-domain.com (Interval: 5 Min.)
- **Alerts:** Email + SMS bei Ausfall
- **Status Page:** https://status.your-domain.com

### Better Stack – Logs (Logtail)
- **URL:** https://logs.betterstack.com
- **Purpose:** Zentrales Log-Aggregation aus WordPress `debug.log`
- **Retention:** 30 Tage
- **Config:** `LOGTAIL_SOURCE_TOKEN` in `wp-config.php` setzen
- **Wichtig:** Läuft über HTTP via `log-forwarder` mu-plugin — **kein SSH-Zugang nötig**. Das ist der primäre und in der Praxis meist einzige Weg, an Logs zu kommen, da die meisten Client-Sites auf Shared Hosting (Hetzner, IONOS, Magenta) ohne SSH laufen.

## 🛡️ Installer-Exposure-Check (Heartbeat-Runner)

**Warum:** Fehlt auf einer Site die `wp-config.php` (gelöscht, nicht hochgeladen, durch ein Deployment überschrieben), zeigt WordPress jedem Besucher das Installationsformular. Ein Angreifer trägt dann eine **eigene Datenbank** ein, legt dort einen Admin an und lädt über das Backend ein Plugin mit Webshell hoch. Genau so lief der Vorfall vom 25.11.2026 (Hetzner-Malware-Ticket). Plugins der Seite (auch `media-lab-agency-core`) können das nicht verhindern, weil WordPress ohne Config gar nicht erst lädt.

**Drei Schutzschichten:**

| Schicht | Wirkung | Wo |
|---|---|---|
| `.htaccess` „Installer Lock“ | Webserver liefert 403 für `wp-admin/setup-config.php` und `wp-admin/install.php` | `.htaccess` (Root) und `cms/.htaccess` |
| Installer-Check im Runner | Alarm per Mail, wenn ein Installer trotzdem erreichbar ist (alle 10 Min., Wiederholung alle 6 h) | `scripts/heartbeat-runner.php` |
| Heartbeat-Route-Check | Alarm, wenn die Seite antwortet, aber `medialab/v1/heartbeat` nicht kennt (`rest_no_route`) – Hinweis auf deaktiviertes Plugin oder fremde Installation | `scripts/heartbeat-runner.php` |

**Einrichtung:** `scripts/heartbeat-runner.config.example.php` zu `heartbeat-runner.config.php` kopieren und `alert_email` setzen. Jede Domain unter `clients` wird automatisch mitgeprüft. Dev-/Staging-Ordner ohne Heartbeat kommen unter `installer_check.extra_urls` (Basis-URL der Site, z.B. `https://dev.example.at/kunde-a`). Das alte flache Format `'domain' => 'TOKEN'` läuft weiter, kann aber keine Alarm-Mail.

**Von Hand prüfen** (ohne Config, ohne Mail, Exit-Code 2 = Installer offen):

```bash
php scripts/heartbeat-runner.php --check-url=https://kunde.at
php scripts/heartbeat-runner.php --check-url=https://projekt.localdev --insecure
php scripts/heartbeat-runner.php --installer-only    # nur Installer-Check für alle Config-Einträge
```

Die Ausgabe zeigt pro Anfrage den HTTP-Status. `--insecure` schaltet die TLS-Prüfung ab und ist nur für lokale Valet-Zertifikate gedacht. Weiterleitungen (http→https, www) werden verfolgt.

**Grenzen:**
- Die Sperre per `.htaccess` gilt nur auf Apache (Hetzner, IONOS, Magenta). Unter Valet/nginx hat sie keine Wirkung – dort ist der Installer ohnehin nicht öffentlich erreichbar.
- Der Check erkennt einen **offenen** Installer, keine bereits **erfolgte Übernahme**. Dafür dient der Heartbeat-Route-Check (nur bei Sites mit Token) und die Kontrolle von `wp-config.php` (`DB_HOST`, `DB_NAME`, Änderungsdatum) nach jedem Alarm.
- Gewollte Neuinstallation im Browser auf einem Server: Block „Installer Lock“ in `cms/.htaccess` kurz auskommentieren und danach sofort wieder aktivieren.

## 🔔 Notifications

### Slack Channels
> ⚠️ **Status: geplant, noch nicht eingerichtet.** Die folgenden Channels sind
> Zieldokument — es existiert aktuell keine Slack-Integration. Bis zur
> Einrichtung laufen Alerts ausschließlich per E-Mail (Better Stack) bzw.
> E-Mail/SMS (Sentry).

- `#deployments` – Deployment-Benachrichtigungen
- `#errors` – Kritische Fehler
- `#monitoring` – Uptime-Alerts

### Alert Thresholds
- **Error Rate:** > 10 Fehler/Minute
- **Page Load:** > 3 Sekunden
- **Database Query:** > 1 Sekunde
- **Memory Usage:** > 80 % des Limits
- **Uptime:** < 99,5 %

## 🔍 Debugging

### Logs prüfen — Better Stack Logs (primärer und meist einziger Weg)
1. https://logs.betterstack.com öffnen
2. Source auswählen
3. Nach Level (`error`, `warn`) oder Stichwort filtern

### Sentry prüfen
1. Sentry Dashboard öffnen
2. Nach Environment filtern (staging / production)
3. Fehlerhäufigkeit und betroffene User prüfen

### Server-Ressourcen prüfen
> ⚠️ **Nur bei den seltenen Ausnahmen mit SSH-Zugang möglich** (kein
> Shared Hosting). Für die Mehrheit der Client-Sites gibt es aktuell keine
> Möglichkeit, Server-Ressourcen direkt einzusehen — ggf. Hosting-Control-Panel
> des jeweiligen Anbieters prüfen.
```bash
ssh production "top -b -n 1 | head -20"
ssh production "df -h"
```

## 📈 Weekly Review

Jeden Montag:
- [ ] Sentry: Fehler-Trends prüfen
- [ ] Better Stack Uptime: Verfügbarkeitsstatistik der Woche
- [ ] Better Stack Logs: Auffällige Warns/Errors durchsehen
- [ ] Slow Query Logs prüfen
- [ ] Memory-Usage-Trends prüfen
- [ ] Dieses Dokument bei Bedarf aktualisieren
