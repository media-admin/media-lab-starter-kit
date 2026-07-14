# Deployment Checklist

> ⚠️ **Hinweis zum CI/CD-Flow:** Die Schritte "GitHub Action deployt automatisch" /
> "GitHub Action erstellt Backup" sind aktuell **Zieldokument, nicht Realität** —
> es existiert noch keine GitHub Action. Bis dahin: manuelle Schritte (siehe
> jeweils eingerückt unter "→ Manuell:") ausführen. Wenn die Action gebaut wird,
> können diese manuellen Unterpunkte wieder raus.

## 🔄 Staging Deployment

- [ ] Merge feature branch to `develop`
- [ ] Run `npm run build` locally (test build)
- [ ] Commit and push to `develop`
- [ ] ~~GitHub Action deploys automatically~~
      → **Manuell:** Plugin-Ordner (außer `vendor/`) per SFTP auf Staging hochladen
- [ ] Test on https://staging.your-domain.com
- [ ] Check console for errors
- [ ] Test all shortcodes
- [ ] Test responsive layouts
- [ ] Verify database works

### 🍪 Feature-spezifisch: Consent-Rate (nur bei diesem Deploy relevant)

- [ ] Reihenfolge beim Datei-Upload: `media-lab-agency-core` **vor** `media-lab-seo`
      (technisch nicht zwingend, da `MLT_Consent_Stats::is_available()` die
      Dashboard-Card ausblendet solange die Tabelle fehlt — aber sauberer für
      die Fehlersuche, falls doch was schiefgeht)
- [ ] Beliebige Frontend-Seite aufrufen, damit `medialab_core_maybe_upgrade()`
      (Hook `plugins_loaded`, Prio 6) die Tabelle anlegt
- [ ] Verifikation:
  ```bash
  wp db query "SHOW TABLES LIKE 'wp_mlt_consent_log';"
  wp option get medialab_core_db_version   # → 1.15.0
  wp plugin get media-lab-seo --field=version   # → 1.3.0
  ```
- [ ] Cookie-Banner im Frontend durchklicken ("Alle akzeptieren")
- [ ] DevTools → Network: POST an `admin-ajax.php?action=medialab_log_consent` → Status 200
- [ ] `wp db query "SELECT * FROM wp_mlt_consent_log ORDER BY id DESC LIMIT 10;"` → 4 neue Zeilen
- [ ] SEO Toolkit Dashboard → Card "Consent-Rate" sichtbar, Toggle "Letzte 30 Tage" / "Woche vs. Vorwoche" funktioniert ohne Reload

---

## 🚀 Production Deployment

- [ ] All tests passed on staging
- [ ] Merge `develop` to `main`
- [ ] Create git tag: `git tag v1.0.0`
- [ ] Push tag: `git push origin v1.0.0`
- [ ] ~~GitHub Action creates backup~~
      → **Manuell:**
      ```bash
      wp db export backup-pre-consent-rate-$(date +%Y%m%d-%H%M%S).sql
      ```
      (zusätzlich zum automatischen `media-lab-backup`-Cron-Lauf — kurz
      gegenprüfen, dass der letzte Lauf zur Hetzner Storage Box erfolgreich war)
- [ ] ~~GitHub Action deploys to production~~
      → **Manuell:** Plugin-Ordner (außer `vendor/`) per SFTP/FTPS hochladen
      **Magenta-Hosts:** Port 22/23 blockiert → FTPS über Kundencenter-Zugang, kein SSH/SFTP-Standardport
- [ ] Verify https://your-domain.com
- [ ] Monitor for 15 minutes
- [ ] Check error logs
- [ ] Better Stack Uptime: Monitor zeigt grün
- [ ] Better Stack Logs: Keine auffälligen Errors in den ersten 15 Minuten

### 🍪 Feature-spezifisch: Consent-Rate

- [ ] Gleiche Verifikationsschritte wie im Staging-Abschnitt oben, auf Production wiederholen
- [ ] Sentry kurz auf neue Fehlermeldungen im Zeitraum nach Deploy prüfen

---

## 🔙 Rollback Procedure

If deployment fails:
```bash
# SSH to server
ssh user@your-domain.com

# Restore backup
cd /var/www/production
tar -xzf backup-YYYYMMDD-HHMMSS.tar.gz

# Clear cache
wp cache flush
```

**Bei Consent-Rate-Deploy speziell:**
- [ ] Falls nur der DB-Teil Probleme macht (Plugin-Dateien aber ok): Tabelle
      gezielt zurückrollen statt komplettes Backup einzuspielen:
  ```bash
  wp db query "DROP TABLE IF EXISTS wp_mlt_consent_log;"
  wp option delete medialab_core_db_version
  ```
- [ ] Reihenfolge beim Zurückrollen der Plugin-Dateien: SEO Toolkit zuerst
      zurückrollen, dann Agency Core (umgekehrte Abhängigkeit)

---

## 📊 Post-Deployment

- [ ] Update changelog
- [ ] Notify team
- [ ] Better Stack Logs auf Warns/Errors prüfen
- [ ] Better Stack Uptime: Verfügbarkeit bestätigt
- [ ] Check performance metrics

---

## 📌 Offener Punkt (Backlog)

- [ ] GitHub Actions für Staging-/Production-Deploy + automatisches Backup
      tatsächlich implementieren (aktuell nur in dieser Doku beschrieben,
      aber nicht gebaut) — würde alle "→ Manuell:"-Schritte oben überflüssig machen
