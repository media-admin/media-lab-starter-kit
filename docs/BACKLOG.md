# Backlog

Ursprünglich: Offene Punkte, gefunden beim WooCommerce-Sync `at.janecka-2026`
↔ Starter Kit (August 2026). Nicht alle Punkte waren neu entstanden — einige
waren vorbestehende Bugs im Starter Kit selbst, die beim Merge erstmals
sichtbar wurden.

**Update 13.08.2026:** Große Aufräum-Session — praktisch der gesamte
ursprüngliche Backlog wurde abgearbeitet (Pakete A–E, siehe unten). Dabei
wurden zusätzlich mehrere **neue, vom ursprünglichen Backlog unabhängige**
Bugs und Doku-Fehler gefunden und behoben (Versions-Drift in
`media-lab-bookings` und `media-lab-seo`, ein nie wirksam gewordener Fix aus
2026-04, mehrere erfundene Funktions-/Filter-Namen in der SEO-Doku). Details
in den jeweiligen Abschnitten und den CHANGELOG.md-Dateien der Plugins.

---

## ✅ Erledigt — media-lab-woocommerce (Paket A, v2.3.0)

- ~~Theme-Alias-Hook (`ajax_filter_posts`) fehlt in `ajax-handlers.php`~~ → **Paket B, v2.4.0**
- ~~Duale Nonce-Prüfung (`mlwf_filter_nonce` + `ajax_filters_nonce`) fehlt~~ → **Paket B, v2.4.0**
- ~~`templates/inquiry-checkout.php`: hartcodierter Steuersatz~~ → **v2.3.0**
- ~~`templates/inquiry-form.php`: totes Template~~ → **v2.3.0**, aktiviert über neuen Shortcode `[mlw_inquiry_form]`
- ~~`templates/configurator/wizard.php`: Wishlist-Button ohne Guard~~ → **v2.3.0**
- ~~`inc/configurator/class-acf-fields.php`: `step_type` Choice `contact_form` fehlt~~ → **v2.3.0**
- ~~`templates/configurator/fields/number.php`: `isset()` reicht nicht~~ → **v2.3.0**
- ~~`inc/inquiry/class-mail.php`: Preisaufschlüsselung unvollständig~~ → **v2.3.0**
- ~~Sortier-Layout (`wc-filter-bar__groups-sort`) fehlt~~ → **Paket B, v2.4.0**
- ~~Konfigurator: doppelte MwSt.-Berechnung in der Staffelpreis-Tabelle~~ → **Paket C, v2.5.0** (`get_tiers_with_prices()`, wie skizziert umgesetzt)
- ~~fehlgeleiteter Kommentarverweis in `catalog-mode.php`~~ → **v2.3.0 Follow-up**

### 🆕 Zusätzlich gefunden (nicht im ursprünglichen Backlog)

- ~~**Kritisch: Konfigurator-Typ "Textilien" zeigte 0 Steps.**~~ → **Hotfix v2.5.1.**
  `get_configuration_steps()` verzweigte bei `config_type === 'textile'` in
  einen toten Code-Pfad (`get_textile_steps()`, erwartete ein CPT-System,
  das im Plugin nirgends registriert ist). Roher `get_post_meta()`-Aufruf
  auf ein ACF-Repeater-Feld lieferte nur die Zeilenanzahl als String statt
  eines Arrays → `is_array()`-Guard schlug immer fehl. Alle Konfigurator-
  Typen laden jetzt konsistent aus dem ACF-Repeater.

---

## ✅ Erledigt — media-lab-agency-core (Paket D, v1.20.1)

- ~~`assets/js/block-logo-slider.js` WCAG-2.2.2-Fokus-Pause nicht portiert~~ →
  **v1.20.1**, umgesetzt in `ml-logo-slider.js` (Theme, nicht Plugin — Feature
  lebt seit der Migration im Theme)
- ~~`assets/css/block-slider.css` Skeleton-Kommentar verweist auf gelöschte Datei~~ → **v1.20.1**

---

## ✅ Erledigt — Struktur/Prozess (Paket E)

- ~~`.gitignore`-Lücken: `cms/wp-content/languages/**` und
  `cms/wp-content/plugins/woocommerce-services/**` fälschlich getrackt~~ →
  **E1.** Root-Ursache war eine korrupte, ohne Zeilenumbruch zusammengeklebte
  Zeile am Dateiende. Zusätzlich `git rm -r --cached` für ~400 bereits
  getrackte Dateien in beiden Pfaden.

**Plugin-Struktur-Standardisierung** (Referenz: `media-lab-backup` — README +
CHANGELOG.md + composer.json/lock + uninstall.php):

- ~~`media-lab-woocommerce`: hatte nichts~~ → **README.md + CHANGELOG.md ergänzt** (E2). Weiterhin kein composer.json (nicht benötigt, keine Composer-Dependencies).
- ~~`media-lab-bookings`: nur CHANGELOG.md, kein README~~ → **README.md ergänzt** (E2b). Siehe auch 🆕 unten — dabei wurde eine unabhängige Versions-Drift entdeckt und bereinigt.
- ~~`media-lab-seo`: nutzte `CHANGES.md` statt `CHANGELOG.md`~~ → **komplett neu aufgebaut** (E2c). Siehe 🆕 unten — deutlich größerer Umfang als ursprünglich angenommen.

**Weiterhin offen:**

- `media-lab-agency-core`: hat weiterhin nur README, kein eigenes
  `CHANGELOG.md` (Changelog steht inline in der README) — nicht angefasst,
  da außerhalb des Scopes von Paket D
- `media-lab-events`: hat weiterhin **weder** README **noch** CHANGELOG.md —
  nicht begonnen
- `composer.json`/`composer.lock`/`uninstall.php` fehlen weiterhin bei den
  meisten Plugins (nur `media-lab-backup` hat sie vollständig) — dieser Teil
  der Struktur-Standardisierung wurde nicht angegangen, nur README/CHANGELOG

### 🆕 Zusätzlich gefunden (nicht im ursprünglichen Backlog)

**`media-lab-bookings` — Versions-Chaos, unabhängig vom eigentlichen
README-Auftrag entdeckt (v1.7.0):**

- Plugin-Header (`Version:`) und Konstante (`MLB_VERSION`) liefen seit dem
  allerersten Feature-Release (14. April) auseinander — nie synchron
  gepflegt. Konstante sprang am 8. Mai sogar auf `1.8.0`, während der letzte
  dokumentierte CHANGELOG-Stand `1.6.0` war (21. April) → eine ganze Version
  (Blocked-Dates/Feiertags-Import, 8. Mai) war nirgends dokumentiert.
  Bereinigt: Header + Konstante synchronisiert auf `1.7.0`, CHANGELOG-Eintrag
  nachgetragen.
- **Der in CHANGELOG 1.6.0 dokumentierte Wording-Fix ("Button-Label Fallback
  auf `mlb_term('verb')`") war nie tatsächlich wirksam.** Ein automatisiertes
  Such-/Ersetzen-Skript aus der damaligen Session zielte auf die falsche
  Datei (`inc/shortcode.php` statt `templates/booking-form.php`, wo der
  `$labels`-Array tatsächlich liegt) — Python `str.replace()` findet dort
  keine Übereinstimmung und ändert stillschweigend nichts, ohne Fehler zu
  werfen. Der Button zeigte seit 1.6.0 immer noch "Buchung anfragen",
  unabhängig von der Wording-Konfiguration. Jetzt am korrekten Ort behoben.

**`media-lab-seo` — deutlich größerer Umfang als "CHANGES.md umbenennen"
(mehrere Funde, v1.4.0–1.9.1):**

- `CHANGES.md` war gar keine Changelog-Datei, sondern eine
  Implementierungs-Notiz für das (inzwischen fertige) Bing-Feature —
  entfernt, Inhalt in CHANGELOG (Feature) und README (Anleitung) überführt.
- Versionsnummer lief zeitweise **nicht monoton**: bereits am 10. März bei
  `1.3.0` (SEO-Dashboard, GSC OAuth2, Report-Mailer, GA4+Matomo-Adapter),
  beim Merge mit dem separat entwickelten `media-lab-toolkit` am 25. März
  fälschlich zurückgesetzt auf `1.1.0` — kein Feature-Verlust, aber zwei
  unterschiedliche Feature-Stände beanspruchten zu verschiedenen Zeitpunkten
  dieselbe Versionsnummer. Komplette Versionshistorie rekonstruiert und
  neu durchnummeriert, Header/Konstante synchronisiert auf `1.9.1`.
- **Mehrere erfundene Funktions-/Filter-Namen** in `docs/03_PLUGINS.md`,
  `docs/13_SEO.md`, `docs/12_ANALYTICS.md` und (bevor korrigiert) auch in
  der von uns neu geschriebenen README — alle aus derselben veralteten
  Quelle stammend, falsches `medialab_`-Präfix statt `MLT_`/`mlt_`:
  - `medialab_gsc_is_configured()`, `medialab_gsc_get_dashboard_data()` etc. existieren nicht (echt: `MLT_GSC_API`-Klasse)
  - Filter `medialab_analytics_adapter` existiert nicht (echt: `mlt_analytics_adapter`)
  - Filter `medialab_seo_schema_types` existiert nicht — Schema.org-Typen sind fest im Code, kein Erweiterungs-Hook
  - Filter `medialab_matomo_sslverify` existiert nicht
  - `do_action('medialab_track_event', ...)` existiert nicht — kein einziger `do_action()` in `class-analytics.php`
  - Alle vier Doku-Dateien entsprechend korrigiert
- **Menü-Struktur war falsch dokumentiert.** Menüpunkt heißt tatsächlich
  „SEO Toolkit" (nicht „Media Lab SEO"); „Einstellungen" und „Dashboard"
  sind zwei **gleichrangige** Untermenüpunkte, keine Verschachtelung wie
  überall behauptet ("Dashboard → Einstellungen").
- **GA4-Setup grundlegend falsch dokumentiert.** GA4 läuft primär über
  OAuth2 und teilt sich die Client-ID/Secret **mit GSC** (ein gemeinsames
  Zugangsdaten-Paar) — Service-Account-JSON ist nur noch Legacy-Fallback.
  Alte Doku (und unser erster README-Entwurf) beschrieb ausschließlich den
  veralteten Service-Account-Weg als wäre er die einzige Methode.
- **`docs/03_PLUGINS.md` listete `media-lab-analytics` fälschlich als
  eigenständiges, optionales Plugin** — wurde bereits am 25.03.2026
  vollständig in `media-lab-seo` integriert, existiert im Repo nicht mehr
  (verifiziert). Zeile entfernt, Korrektur-Hinweis ergänzt.
- **Wöchentlicher Report wurde zeitweise doppelt versendet** (bei Janecka
  aufgefallen) — zwei Handler auf demselben Cron-Hook `mlt_weekly_report`
  (ein Legacy-Handler in `class-settings.php` + der eigentliche
  `MLT_Report_Mailer`). **War zum Zeitpunkt dieser Session bereits gefixt**
  (Legacy-Handler entfernt), aber ein veralteter Docblock-Kommentar
  verwies noch auf den falschen Registrierungsort — korrigiert.
- **Offen geblieben** (nicht am Code verifiziert, bewusst so markiert statt geraten):
  - exakter GSC-OAuth-Redirect-Parameter (`class-gsc-api.php` lag nicht vor)
  - exaktes Datum/Commit der GA4-OAuth-Migration (Service-Account → OAuth)
  - genauer Aufbau des Report-Mail-HTML (`class-report-template.php` nicht geprüft)

---

## Weiterhin offen (nicht Teil der August-2026-Session)

Diese Punkte wurden bewusst nicht angegangen — entweder weil sie explizit
als eigene, größere Sessions markiert waren, oder weil sie außerhalb des
Struktur-Standardisierungs-Scopes lagen.

### juwelier-janecka Theme: `janecka_product_card_show_actions`-Filter global statt lokal

**Verifiziert offen** (08/2026, `inc/woocommerce/hooks-archive.php`, Zeile 385).

```php
remove_action( 'woocommerce_no_products_found', 'wc_no_products_found' );
add_action( 'woocommerce_no_products_found', 'janecka_wc_no_products_found' );
add_filter( 'janecka_product_card_show_actions', '__return_false' );   // ← global, Zeile 385

function janecka_wc_no_products_found(): void {
    // ...
}
```

Filter wird auf oberster Dateiebene registriert statt innerhalb der
Funktion — unterdrückt dadurch die Action-Buttons auf **jeder** Archivseite
dauerhaft, nicht nur im "keine Produkte gefunden"-Fall. Fix: Registrierung
in die Funktion verschieben. Niedriges Risiko, noch nicht eingespielt
(client-spezifisch, nicht Teil des Starter Kits).

### media-lab-backup: `cleanup()` löscht Dateien anderer paralleler Jobs

**Verifiziert offen** (08/2026, `includes/class-mlb-backup-runner.php`, Zeile 270–278).

`glob()` matched alle Backup-Dateien im gemeinsamen Temp-Verzeichnis,
unabhängig vom erzeugenden Job — kein Job-Präfix, kein Lock-Mechanismus.
Live reproduziert: paralleler DB-only-Job hat eine ZIP-Datei eines noch
laufenden Full-Backup-Jobs mitten im SFTP-Upload gelöscht.

Skizzierter Fix: Pro-Job-Unterverzeichnis (`temp/{log_id}/`) oder
Glob-Filter mit Job-ID-Präfix, zusätzlich genereller Lock-Mechanismus.

**Verwandt, weiterhin unklar:** Live-Log-Persistenz beim Tab-Wechsel im
"Backup starten"-Tab — Feature-Wunsch aus früherer Session,
Verifikationsstatus gegen `class-mlb-logger.php` weiterhin ungeklärt.

### TI WooCommerce Wishlist → media-lab-woocommerce Wishlist-Migration

Eigene, dedizierte Session empfohlen (vergleichbarer Umfang wie der
Inquiry-Engine-Merge). Details siehe vorheriger Backlog-Stand — unverändert
offen, nicht Teil dieser Session.

### Nächste Projekte / Sync-Ausblick

`org.churum-meru-2026` nutzt `media-lab-woocommerce` nicht mehr — beim
nächsten Sync-Durchgang prüfen, ob andere Starter-Kit-Plugins
(`media-lab-agency-core`, `media-lab-backup`, `media-lab-seo`) dort
ebenfalls divergieren. Unverändert offen.

### Struktur/Prozess — Rest

- `media-lab-agency-core`: eigenes CHANGELOG.md (aktuell inline in README)
- `media-lab-events`: README + CHANGELOG.md komplett neu
- `composer.json`/`composer.lock`/`uninstall.php` bei allen Plugins außer `media-lab-backup` nachziehen
