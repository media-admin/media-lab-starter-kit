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

### Schema.org-Umbau (media-lab-seo 1.10.0) – offene Punkte

- **Praxistest steht aus:** Auf einem echten Projekt (z. B. stadtwirt-berndorf.at) Startseite, Beitrag,
  FAQ-Seite, Preisseite und Booking-Seite im Rich Results Test prüfen.
- **Doku-Widerspruch `gmap`:** `08_CUSTOM-POST-TYPES.md`/`09_ACF-FIELDS.md` beschreiben `location`, `phone`,
  `email`, `hours`; der Code (`acf-fields-gmap.php`) hat `embed_src`, `address`, `marker_title`, `map_height`;
  das Theme-Backup `acf-json-backup/group_gmap.json` hat `latitude`, `longitude`, `zoom`. Die Feldgruppe
  `group_gmap` ist doppelt definiert (Theme-JSON und PHP). CPT registriert nur `supports: title`, die Doku sagt „Title, Editor".
- **Doku-Widerspruch `team`:** Doku sagt `position` + `social_links` (Repeater), Theme-Backup sagt `role` +
  `social_media` (Gruppe). Der `acf-json/`-Ordner von `media-lab-project-starter` wurde im Repo nicht gefunden –
  Ist-Zustand klären und Doku angleichen. Das Schema liest vorerst beide Varianten.
- **Nativer Block `medialab/accordion`:** Items liegen nicht im Block-Inhalt, daher keine automatische FAQPage.
  Entweder Items als InnerBlocks speichern oder Filter `mlt_schema_faq_items` pro Projekt nutzen.
- **Booking-Standorte nur auf Seiten mit Shortcode:** Wer eine Filiale sitewide (z. B. Startseite) ausgezeichnet
  haben will, braucht dafür einen eigenen Weg.
- **Autoren-Archive:** pro Projekt prüfen, ob sie gesperrt/umgeleitet sind (dann `mlt_schema_author_url` setzen).

### media-lab-seo 1.15.0 – offene Punkte (Stand 08.10.2026)

**Bis Montag 12.10.2026 (erster automatischer Report auf Stadtwirt, Mo 08:00)**

- **Empfänger pro Site prüfen, bevor 1.15.0 ausgerollt wird.** Der Cron-Fix (Hook-Name
  `mlt_weekly_report`) bewirkt, dass **jede Site mit aktiviertem Report erstmals automatisch
  sendet**. Ist keine Empfänger-Adresse eingetragen, geht der Report an `admin_email`.
  Prüfung per WP-CLI: `wp eval 'echo get_option("mlt_report_enabled") ? "AN" : "aus", " → ", implode(", ", mlt_get_report_recipients()), PHP_EOL;'`.
  Wo der Report (noch) nicht an den Kunden gehen soll: Schalter ausschalten.
- **Rollout in Wellen** (mit Backup des Plugin-Ordners). Stadtwirt läuft auf 1.14.1 → auf 1.15.0
  heben. Danach pro Site `wp cron event list | grep mlt` → muss `mlt_weekly_report` zeigen
  (nicht `mlt_send_weekly_report`), „Nächster geplanter Versand" auf der Einstellungsseite
  zeigt einen Termin.
- **Report-Mail in weiteren Mail-Programmen ansehen:** bisher nur Apple Mail (Dunkelmodus) und
  der Browser. Offen: Outlook (Desktop – das Tabellen-HTML ist dafür gebaut, aber ungeprüft),
  Gmail (Web + App; Mail ca. 50 KB, Kürzung erst ab ca. 102 KB), Apple Mail im Hellmodus.

**Google-Verbindung (GA4 / OAuth)**

- **Stadtwirt: GA4 per OAuth verbinden.** Symptom bis dahin: Seitenaufrufe, Sessions und Nutzer
  = 0, die Analytics-Reiter im Verlauf-Chart fehlen. Schritte: Redirect-URI
  (`…/cms/wp-admin/admin.php?page=media-lab-seo&mlt_ga4_callback=1`) im OAuth-Client eintragen,
  „Google Analytics Data API" im Cloud-Projekt aktivieren, dem verbindenden Google-Konto die
  Rolle „Betrachter" auf der Property geben, dann „Mit Google verbinden". Anschließend den
  **Legacy-Service-Account (JSON-Key in der Datenbank) entfernen**.
- **Alle Sites mit Legacy-Service-Account prüfen:** Zeigt das Dashboard dort 0, liefert der
  Service-Account keine Daten (vermutlich fehlt der Zugriff auf die Property) – auf OAuth
  umstellen.
- **Veröffentlichungsstatus der Google-OAuth-App prüfen** (Google Auth Platform → Zielgruppe).
  Steht er auf „Testing", **laufen Refresh-Tokens nach 7 Tagen ab** – GSC und GA4 würden
  wöchentlich die Verbindung verlieren und der Report Nullwerte zeigen. Auf „In Produktion"
  stellen.
- **Redirect-URIs im gemeinsamen OAuth-Client:** pro Site zwei (GSC + GA4). Laut einer Quelle
  gilt ein Limit von 100 URIs pro Client (nicht in der Google-Doku verifiziert) – bei ~50 Sites
  wäre das erreicht. Bei Bedarf einen zweiten Client anlegen.

**Stadtwirt**

- **www / ohne www:** In „Top Seiten" erscheint die Startseite unter zwei Adressen
  (`www.stadtwirt-berndorf.at/` 238 Klicks, Position 2,8; `stadtwirt-berndorf.at/` 103 Klicks,
  Position 5,7). Hauptadresse festlegen, die andere per 301 auf Serverebene umleiten
  (`.htaccess`/Hosting), Canonical und Sitemap angleichen. **Auf den anderen Sites prüfen**,
  ob im Dashboard dieselbe Seite doppelt (mit/ohne Host) auftaucht – das Plugin zeigt den Host
  seit 1.14.0 bei gleichem Pfad an.

**Technische Restpunkte (Plugin)**

- **Stille Nullwerte bei Abruffehlern:** Fehlgeschlagene Abrufe werden seit 1.11.0 nicht mehr
  gecacht, das Dashboard zeigt aber weiterhin Nullwerte ohne Hinweis. Wünschenswert: Hinweis
  „Abruf fehlgeschlagen" im Dashboard, Fehler ins Aktivitätslog (`medialab_log_event`).
- **Report bei Google-Ausfall zum Versandzeitpunkt:** Die Mail geht dann mit Nullwerten raus.
  Idee: Versand verschieben (z. B. 1 h später, max. 3 Versuche) oder überspringen, wenn weder GSC
  noch Analytics Daten liefern.
- **WP-Cron ist besuchsabhängig:** Für pünktlichen Versand ein echter Server-Cron auf
  `wp-cron.php` (und `DISABLE_WP_CRON`) als Standard für alle Sites einführen.
- **Chart-Tooltips am Handy:** Die Tooltips im Verlauf-Chart sind native SVG-`<title>`-Elemente
  (Hover) – auf Touch-Geräten nicht zuverlässig. Optional ein kleiner JS-Tooltip.
- **Mail im Dunkelmodus:** Apple Mail kehrt den dunklen Kopfbereich ins Helle um (lesbar,
  aber uneinheitlich). Optional `color-scheme`-Meta; Gmail/Outlook-Apps verhalten sich anders.
- **Zeilen-Deltas nur für GSC-Listen:** Traffic-Quellen (Analytics) haben keinen Vergleich.
- **„28 Tage" = 27 Kalendertage:** Der Zeitraum endet wegen der GSC-Verzögerung bei heute − 2
  Tage. Falls exakt 28 Tage gewünscht sind, `MLT_GSC_API::get_active_range()` ändern (betrifft
  auch die Dashboard-Schnellwahl).
- **Zeilen-Abgleich über die Top 500:** Bei sehr großen Sites kann ein Eintrag als „neu"
  erscheinen, der im Vergleichszeitraum nur außerhalb der Top 500 lag (bewusst so gelöst;
  ggf. Limit erhöhen).

**Doku / Prozess**

- **`docs/12_ANALYTICS.md` nicht geprüft** – verweist aus `13_SEO.md`. Ggf. veraltet bei
  Adapter-Interface (neu: `MLT_Analytics_Timeseries_Interface`), Report-Aufbau und Cron-Hook.
- **Root-README und `docs/03_PLUGINS.md`:** Versionsangabe zu `media-lab-seo` prüfen (1.15.0);
  die Versionsangaben der Root-README wurden zuletzt am 13.08.2026 verifiziert.
- **Release-Commit:** Die Versionen 1.11.0 bis 1.15.0 stecken in **einem** Commit (`90685e12`),
  ohne Git-Tag. Falls Tags gewünscht: `media-lab-seo-v1.15.0` (die CHANGELOG-Einträge sind pro
  Version getrennt).
- **Lokales Test-Werkzeug `mlt-test-fixtures.php`** (simuliert Search Console/GA4 und fängt
  den Report ab) liegt **nicht** im Repo. Bei Bedarf unter `tools/` ablegen – nie in
  `mu-plugins` einer Live-Site.

---

### ✅ Erledigt — media-lab-seo (1.11.0 – 1.15.0, 05.–08.10.2026)

- ~~Kein Vergleichszeitraum in Dashboard und Report~~ → **1.11.0**: zentrale Einstellung
  (Vorperiode/Vorjahr/aus) für Dashboard, WP-Widget und E-Mail-Report, Hinweis zur
  Messtoleranz (übersetzbar). Die in der Doku seit 1.2.0 behauptete „vs. Vorperiode"-Anzeige war
  nie implementiert.
- ~~Veränderung pro Zeile fehlt~~ → **1.14.0**: Top Keywords/Top Seiten mit absoluter
  Veränderung von Klicks und Position, „neu"-Kennzeichnung, Host-Anzeige bei gleichem Pfad.
- ~~Keine Diagramme~~ → **1.12.0** Verlauf-Chart im Dashboard (serverseitiges SVG, ohne
  Bibliothek), **1.13.0** Säulendiagramme und Balken im E-Mail-Report (reines Tabellen-HTML,
  Größen-Sicherung gegen die Gmail-Kürzung).
- ~~**Wöchentlicher Report wurde bei neu aktivierter Einstellung nie automatisch versendet**~~
  → **1.12.0**: Cron-Event wurde als `mlt_send_weekly_report` geplant, der Mailer lauscht auf
  `mlt_weekly_report`; die Einstellungsseite zeigte deshalb dauerhaft „—". Nur Sites mit einem
  älteren, zufällig noch existierenden Event hatten weitergesendet.
- ~~Test-Mail-Button tat nichts~~ → **1.12.0**: las das seit 1.6.0 nicht mehr vorhandene Feld
  `#mlt_report_email` (JS-Fehler). Der Button sendet jetzt den echten Report.
- ~~Fehlgeschlagene GSC-/GA4-Abrufe wurden 6 h als „0" gecacht~~ → **1.11.0**
  (`query_api()`/`run_report()` unterscheiden gültig-leer und Fehler).
- ~~`/llms.txt` wurde nicht ausgeliefert~~ → **1.11.0**: `class-llms-txt.php` (1.10.2) war nie
  eingebunden. Außerdem Header/Konstante (1.10.1 vs. CHANGELOG 1.10.2) synchronisiert und das
  doppelte `require_once` von `class-schema-admin.php` entfernt.
- ~~Dashboard-Karten unformatiert, Consent-Balken unsichtbar~~ → **1.12.0**
  (`.mlt-card`/`.mlt-grid` nur in `admin.css`; Consent-Füllung war ein `<span>` ohne
  `display:block`).
- ~~Chart/Mail fielen am letzten Tag auf „0"~~ → **1.14.1** (Datenverzögerung wird nicht mehr als
  Nullwert gezeichnet, Hinweis im Dashboard und seit 1.15.0 in der Mail).
- ~~CTR/Position mit Punkt bzw. ohne Nachkommastelle, WP-Widget-Titel auseinandergezogen~~ →
  **1.14.1**.
- ~~Verification-Feld (GSC/Bing) speicherte und gab beliebigen Inhalt aus~~ → **1.15.0**:
  URLs/Domains werden abgelehnt, ein eingefügter Meta-Tag wird auf den `content`-Wert gekürzt,
  bestehende Fehleinträge (z. B. Property-URL) werden nicht mehr im `<head>` ausgegeben.
  Stadtwirt: Feld geleert, im Quelltext geprüft.
- ~~Doku-Fehler in `13_SEO.md`/README~~ → falscher GSC-Redirect-Parameter (`mlt_gsc_callback=1`
  ist richtig), nicht existierende Funktion `medialab_seo_breadcrumbs()` (richtig:
  `mlt_breadcrumbs()`), Menüpfad „Weiterleitungen", Report-Inhalt jetzt am Code verifiziert,
  Hook-Tabelle (Schema-/llms-/Report-Filter) vervollständigt.

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
- ~~`media-lab-events`: hatte weder README noch CHANGELOG.md~~ → **beides neu angelegt** (v1.0.1). Siehe 🆕 unten — dabei wurde ein unabhängiger kritischer Sortier-/Filter-Bug entdeckt und behoben.

**Weiterhin offen:**

- `media-lab-agency-core`: hat weiterhin nur README, kein eigenes
  `CHANGELOG.md` (Changelog steht inline in der README) — nicht angefasst,
  da außerhalb des Scopes von Paket D
- ~~`composer.json`/`composer.lock`/`uninstall.php` bei allen Plugins außer
  `media-lab-backup` nachziehen~~ → **composer.json/lock: geprüft
  (21.08.2026), bei keinem der Plugins tatsächlich benötigt** — kein
  Plugin außer `media-lab-backup` ruft `vendor/autoload.php` auf;
  insbesondere `media-lab-seo`s GA4-Service-Account-JWT (RS256) läuft
  komplett über PHP-native `openssl_sign()`, keine externe
  JWT-Bibliothek. Statt leerer Attrappen-Dateien: explizite
  "Dependencies"-Sektion in jeder README ergänzt (`media-lab-agency-core`,
  `media-lab-seo`, `media-lab-woocommerce`, `media-lab-bookings`,
  `media-lab-events`, `media-lab-project-starter`) — inklusive
  `media-lab-woocommerce`, wo trotz der hier in Paket E bereits
  vermerkten Entscheidung nie ein entsprechender Hinweis in die README
  selbst übertragen worden war.
  **`uninstall.php`: ergänzt (21.08.2026)** für alle sechs Plugins, Fußabdruck
  jeweils gegen den echten Code verifiziert (keine geratenen Options-/
  Tabellen-Namen):
  - `media-lab-events`, `media-lab-project-starter`: kein eigener State
    (nur CPTs/Taxonomien/ACF-JSON) — reine Stub-Datei mit auskommentiertem
    Opt-in-Block für den Fall, dass die Inhalte doch mitgelöscht werden
    sollen.
  - `media-lab-bookings`: zwei Optionen, ein Single-Event-Cron
    (`wp_unschedule_hook`), Buchungen/Standorte bewusst nicht gelöscht.
  - `media-lab-agency-core`: pragmatischer Wildcard-Ansatz über die drei
    im Plugin verwendeten Options-Präfixe (`medialab_`, `options_medialab_`
    für ACF-Options-Page-Felder, `mla_` für den Security-Scanner —
    abweichender Präfix, beim Durchsuchen entdeckt). Activity-Log-Tabelle
    wird gelöscht, die DSGVO-Consent-Log-Tabelle (`wp_mlt_consent_log`)
    **bewusst nicht** — sie ist Nachweis, wer wann welchem Cookie-Consent
    zugestimmt hat.
  - `media-lab-seo`: Wildcard über `mlt_` sowie das davon abweichende
    `medialab_seo_`-Präfix der Report-Zeitplan-Optionen (ebenfalls beim
    Durchsuchen entdeckt). Eigene `mlt_redirects`/`mlt_404_log`-Tabellen
    werden gelöscht (operative Daten, kein DSGVO-Nachweis).
  - `media-lab-woocommerce`: Wildcard über `mlw_`, zwei Wunschlisten-
    User-Meta-Keys gelöscht, `mlw_inquiry`-Kundenanfragen bewusst nicht
    gelöscht (echte Geschäftsdaten, wie bei den Bookings).

  Durchgängiges Prinzip über alle sechs: Plugin-eigener technischer State
  (Optionen, Transients, Cron, Cache-/Log-Tabellen ohne Nachweis-Charakter)
  wird entfernt; echte Geschäfts-/Kundendaten (CPT-Content, DSGVO-Nachweise)
  bleiben stehen, mit auskommentiertem Opt-in-Block falls doch gewünscht.


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

**`media-lab-events` — kritischer Sortier-/Filter-Bug, unabhängig vom
README-Auftrag entdeckt (v1.0.1):**

- ACF-Feld `event_date_start` nutzte `return_format => 'd.m.Y H:i'` (Tag
  zuerst). `[events_grid]` sortierte und filterte dieses Feld als reinen
  String ohne Typ-Angabe — bei diesem Format ist String-Vergleich **nicht**
  chronologisch korrekt (`"01.03.2026"` gilt string-sortiert als "kleiner"
  als `"15.01.2026"`, obwohl der 15. Januar chronologisch früher liegt).
  Betraf jedes Projekt, das das Plugin nutzt, abhängig von der zufälligen
  Tag/Monat-Konstellation der jeweiligen Events. Fix: `return_format` auf
  `Y-m-d H:i:s` geändert (identisch zum internen ACF-Speicherformat, daher
  **keine Datenmigration nötig**), `meta_type => 'DATETIME'` für Sortierung
  und Filter ergänzt, Frontend-Anzeige über `date_i18n()` zurück ins
  deutsche Format konvertiert.
- Beim README-Schreiben zusätzlich dokumentiert (nicht behoben, da
  Verhaltensänderung nötig gewesen wäre): `orderby`-Shortcode-Parameter
  wird entgegengenommen, aber im `WP_Query`-Aufbau nie ausgewertet —
  Sortierung ist fest auf `event_date_start` verdrahtet.

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
- `composer.json`/`composer.lock`/`uninstall.php` bei allen Plugins außer `media-lab-backup` nachziehen
- ~~`cms/wp-content/themes/custom-theme/README.md` (die **interne**
  Theme-README) steht seit dem allerersten Release auf Version `1.0.0`
  und wurde nie wieder gepflegt~~ → **erledigt (22.08.2026).**
  Komplett überarbeitet, alle Angaben gegen echten Code verifiziert
  (Requirements, Design-Tokens, Component-Liste, Projektstruktur).
  Zwei Zusatzfunde dabei behoben:
  - **`CUSTOM_THEME_VERSION`** in `functions.php` war seit Langem
    eingefroren (`'1.4.0'`) während `style.css` bei `1.15.3` stand —
    jetzt dynamisch über `wp_get_theme()->get('Version')` synchronisiert.
  - Die alte README behauptete fälschlich "Configure in Customizer" —
    das Theme hat keinen einzigen `customize_register()`-Hook.
    Richtiggestellt: Anpassung läuft über SCSS-Tokens (Build-Zeit) bzw.
    `media-lab-agency-core`s ACF-Options-Seiten (Laufzeit).

  Neues eigenständiges `CHANGELOG.md` fürs Theme angelegt (vorher gab es
  keins) — startet bei `1.15.3`, ältere Historie bewusst nicht
  rekonstruiert (anders als bei `media-lab-agency-core` keine
  Git-Log-Auswertung gemacht, siehe dortiger Eintrag zum Vergleich).
- `media-lab-project-starter` fehlte bisher in **jeder** Doku (Root-README, `03_PLUGINS.md`, hier im Backlog) — beim Root-README-Cleanup entdeckt und dort in einem eigenen Abschnitt ergänzt (Scaffold-Charakter, wird pro Projekt dupliziert statt identisch deployt). `docs/03_PLUGINS.md` erwähnt es weiterhin nicht — sollte dort ergänzt werden

### Root-README (`README.md`) — erledigt (13.08.2026)

~~Komponenten-Tabelle unvollständig (nur Agency Core + SEO gelistet,
`media-lab-woocommerce`/`-bookings`/`-events`/`-backup`/`-project-starter`
fehlten komplett); Theme-Version falsch (1.14.0 statt tatsächlich 1.15.2);
duplizierte "Versionshistorie"-Tabelle nutzte dasselbe Starter-Kit-weite
Tag-Schema, das schon bei `media-lab-seo`s Git-Historie für Verwirrung
gesorgt hatte (getrennt von den Plugin-eigenen Versionsnummern, garantiert
irgendwann divergierend); WCAG-"11 Fixes"-Behauptung nicht verifizierbar;
Gutenberg-Blocks implizit dem Theme statt `media-lab-agency-core`
zugeordnet.~~ → **Komplett überarbeitet.** Versionshistorie-Tabelle
ersatzlos gestrichen (Verweis auf die jeweiligen `CHANGELOG.md`-Dateien
stattdessen), `media-lab-project-starter` als eigener Scaffold-Abschnitt
ergänzt, alle Versionsangaben gegen den echten Code verifiziert.

### Heartbeat-Monitoring — erledigt (13.08.2026)

- ~~`heartbeat-runner.php` lag nur lokal vor, obwohl
  `media-lab-agency-core/README.md` bereits darauf verweist~~ →
  **ins Repo aufgenommen**, unter `scripts/heartbeat-runner.php` (Template,
  token-frei) + `scripts/heartbeat-runner.config.example.php`
  (Kopiervorlage) + `scripts/setup-heartbeat-cron.sh` (automatisiertes
  Cronjob-Setup, analog zu `setup-cron.sh`). Echte Tokens liegen in
  `scripts/heartbeat-runner.config.php`, per `.gitignore` vom Commit
  ausgeschlossen.
- **Gefunden, nicht im ursprünglichen Backlog:** Sporadische
  "Missed heartbeat"-Alarme (auto-resolved nach 4–14 Min.) bei
  `churum-meru.org` und `stadtwirt-berndorf.at` am 12./13.08.2026. Ursache
  vermutlich ein einzelner ausgelassener Cron-Tick auf Hetzner Webhosting
  (nicht 100% exakt getaktet) in Kombination mit einer zu knapp bemessenen
  Better-Stack-Grace-Period (5 Min. bei 10-Min.-Intervall = nur 15 Min.
  Toleranz, ein ausgelassener Tick erzeugt aber eine 20-Min.-Lücke). ~~Fix:
  Grace Period auf 15 Min. erhöht (Period bleibt bei 10 Min.) für
  `churum-meru.org` — noch zu prüfen/nachziehen für
  `stadtwirt-berndorf.at`, `ib-mosbacher.at` und `womac.at`~~ → **für alle
  vier Heartbeats nachgezogen** (manuell im Better-Stack-Dashboard, 13.08.2026).
  Falls die Alarme trotz angepasster Grace Period häufiger als "vereinzelt"
  auftreten, deutet das auf ein tieferliegendes Cron-Zuverlässigkeits-
  Problem auf Hetzner Webhosting hin, das eine genauere Untersuchung
  wert wäre (z.B. externer Cron-Trigger-Dienst statt Hosting-internem Cron).
