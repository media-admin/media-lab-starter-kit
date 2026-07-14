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
