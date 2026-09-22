# Changelog

Alle wesentlichen Änderungen werden in dieser Datei dokumentiert.
Format basiert auf [Keep a Changelog](https://keepachangelog.com/de/1.0.0/),
Versionierung nach [Semantic Versioning](https://semver.org/lang/de/).

**Hinweis zur Versionsnummerierung:** Die Versionshistorie vor 1.4.0 wurde
rekonstruiert und hier neu, durchgehend nummeriert. Grund: Die ursprünglichen
Versionsnummern in Plugin-Header/Konstante liefen zeitweise nicht monoton -
am 10. März 2026 wurde bereits `1.3.0` erreicht (SEO-Dashboard, GSC OAuth2
API, Report-Mailer, GA4+Matomo-Adapter), beim Merge mit `media-lab-toolkit`
am 25. März sprang die Nummer aber fälschlich auf `1.1.0` zurück - siehe
1.4.0 unten. Im Gegensatz zu einem ähnlichen Fund bei `media-lab-bookings`
ist hierbei **kein Feature verloren gegangen**, nur die Zählung war falsch.

---

## [1.10.1] - 2026-09-22

### media-lab-seo 1.10.1

#### Added
- **Schema.org als verknüpfter `@graph`** (`inc/class-schema.php`, komplett überarbeitet) –
  ein JSON-LD-Block statt mehrerer Einzelblöcke; Organization/LocalBusiness, WebSite,
  WebPage und BreadcrumbList sind per `@id` verknüpft.
- **Automatische Schema-Typen je Inhalt:** `post` → `BlogPosting` (+ Autor als `Person`,
  nur bei echtem Namen – sonst Organisation), `service` → `Service`, `team` → `Person`,
  `job` → `JobPosting`, `project` → `CreativeWork`; Seiten/Archive → `WebPage` /
  `CollectionPage` / `SearchResultsPage`, Autoren-Archiv → `ProfilePage`.
- **FAQPage-Erkennung:** Seiten mit `[faq_accordion]` oder `<details><summary>`
  (inkl. Core-Details-Block) werden automatisch zur `FAQPage`; Fragen werden über den
  Fragetext dedupliziert.
- **Neue Quellen** (`inc/class-schema-sources.php`, neu): `[pricing_table]` →
  `OfferCatalog`, `[google_map]` → `Place`, `[mlb_booking_form]` → `LocalBusiness`
  je Standort inkl. `OpeningHoursSpecification` und Leistungen, `[team_member]` →
  `Person` (auch verschachtelt in `[team_cards]`), CPT `event` → `Event`.
- **Neues Admin-Modul** (`inc/class-schema-admin.php`, neu): Untermenü
  „SEO Toolkit → Schema" (Organisationstyp, Telefon, E-Mail, Adresse, Öffnungszeiten,
  Einzugsgebiet, sameAs), Metabox „Schema (SEO / AEO)" pro Beitrag/Seite
  (deaktivieren, Seitentyp überschreiben, eigenes JSON-LD nur für Administratoren),
  Profilfelder am WP-Benutzer (LinkedIn, Xing, Instagram, X, Facebook, YouTube)
  für das Autoren-Schema.
- **13 neue Filter** für Erweiterung ohne Plugin-Code anzufassen: `mlt_schema_enabled`,
  `mlt_schema_graph`, `mlt_schema_organization`, `mlt_schema_org_types`,
  `mlt_schema_post_type_builders`, `mlt_schema_faq_items`, `mlt_schema_article_type`,
  `mlt_schema_description`, `mlt_schema_person_contact`, `mlt_schema_default_country`,
  `mlt_schema_author_is_person`, `mlt_schema_author_url`, `mlt_schema_location_email`.

#### Changed
- Beiträge: `Article` → `BlogPosting` (per Filter `mlt_schema_article_type` änderbar).
- Organization-Logo: `logo_desktop` (Agency Core → Logo / Globale Einstellungen) hat
  Vorrang vor dem Social-Default-Bild.
- Bei aktivem Yoast SEO, Rank Math oder SEOPress gibt das Plugin kein Schema mehr aus
  (Vermeidung von Doppel-Markup; per `mlt_schema_enabled` überschreibbar).
- Standort-E-Mail (`mlb_location_email`, interne Kopie-Adresse) wird bewusst **nicht**
  ausgegeben, außer per Filter `mlt_schema_location_email`.

#### Fixed
- **Organization-Kontaktdaten und Logo wurden nie ausgegeben.** Der bisherige Code las
  ACF-Optionen (`logo`, `phone`, `email`, `address`), die im Agency Core nicht existieren.
  Liest jetzt die tatsächlichen Felder (`logo_desktop`, `top_header_phone`,
  `top_header_email`, `top_header_address`, `top_header_social`) – nur, wenn der Top
  Header aktiv ist und der jeweilige Eintrag nicht abgeschaltet wurde; die
  Schema-Einstellungen haben Vorrang.
- **FAQ-Erkennung griff nie:** gesucht wurde nach einem nie existierenden Shortcode
  `[faq]`; der reale Name ist `[faq_accordion]`.
- **Team-Mitglieder aus `[team_member]`-Shortcodes wurden nicht erkannt** (nur der
  CPT-Fall über `[team_query]` war abgedeckt); jetzt erzeugt jeder `[team_member]`
  einen eigenen `Person`-Node, inkl. Verweis von der Organisation über `employee`.
- **`og:description`/`twitter:description` enthielten rohen Shortcode-Text**
  (`inc/class-seo.php`): `get_description()` entfernte per `wp_strip_all_tags()` nur
  HTML-Tags, keine Shortcode-Syntax (`[projects_query …]`, `[posts_load_more]`, …).
  `strip_shortcodes()` jetzt vor `wp_strip_all_tags()` ergänzt, für Excerpt- und
  Content-Zweig.
- JSON-LD-Ausgabe: Inhalte mit `</script>` konnten den Script-Block verlassen
  (`JSON_HEX_TAG`/`JSON_HEX_AMP` ergänzt).

---  

## [1.10.0] - 2026-09-20

### media-lab-seo 1.10.0

#### Added
- **Schema.org als verknüpfter `@graph`** (`inc/class-schema.php`, komplett überarbeitet) –
  ein JSON-LD-Block statt mehrerer Einzelblöcke; Organization, WebSite, WebPage und
  BreadcrumbList sind per `@id` verknüpft.
- **Automatische Schema-Typen je Inhalt:** `post` → `BlogPosting` (+ Autor als `Person`),
  `service` → `Service`, `team` → `Person`, `job` → `JobPosting`, `project` → `CreativeWork`;
  Seiten/Archive → `WebPage` / `CollectionPage` / `SearchResultsPage`, Autoren-Archiv → `ProfilePage`.
- **FAQPage-Erkennung:** Seiten mit `[faq]`, `[accordion_item]` oder `<details>/<summary>`
  (inkl. Core-Details-Block) werden automatisch zur `FAQPage`.
- **Zusätzliche Quellen** (`inc/class-schema-sources.php`, neu): `[pricing_table]` → `OfferCatalog`,
  `[google_map]` → `Place`, `[mlb_booking_form]` → `LocalBusiness` je Standort inkl.
  `OpeningHoursSpecification` und Leistungen, CPT `event` → `Event`.
- **Admin** (`inc/class-schema-admin.php`, neu): Untermenü „SEO Toolkit → Schema"
  (Organisationstyp, Telefon, E-Mail, Adresse, Öffnungszeiten, Einzugsgebiet, sameAs),
  Metabox „Schema (SEO / AEO)" pro Beitrag/Seite (Schema deaktivieren, Seitentyp überschreiben,
  eigenes JSON-LD nur für Administratoren), Profilfelder am WP-Benutzer
  (LinkedIn, Xing, Instagram, X, Facebook, YouTube) für das Autoren-Schema.
- **Neue Filter:** `mlt_schema_enabled`, `mlt_schema_graph`, `mlt_schema_organization`,
  `mlt_schema_org_types`, `mlt_schema_post_type_builders`, `mlt_schema_faq_items`,
  `mlt_schema_article_type`, `mlt_schema_description`, `mlt_schema_person_contact`,
  `mlt_schema_default_country`, `mlt_schema_author_is_person`, `mlt_schema_author_url`,
  `mlt_schema_location_email`.

#### Changed
- Beiträge: `Article` → `BlogPosting` (per Filter `mlt_schema_article_type` rückgängig zu machen).
- Autor wird nur bei echtem Namen als `Person` ausgegeben; bei Login-Namen wie „admin"
  (oder Anzeigename = Login) ist die Organisation der Autor.
- Organization-Logo: echtes ACF-Logo (`logo_desktop`) hat Vorrang vor dem Social-Default-Bild
  (`mlt_og_default_image`).
- Bei aktivem Yoast SEO, Rank Math oder SEOPress gibt das Plugin kein Schema mehr aus
  (Vermeidung von Doppel-Markup; per `mlt_schema_enabled` überschreibbar).
- Standort-E-Mail (`mlb_location_email`, interne Kopie-Adresse) wird bewusst **nicht**
  ausgegeben, außer per Filter `mlt_schema_location_email`.
- `MLT_Schema::get_breadcrumb_list()` bleibt öffentlich (Rückwärtskompatibilität).

#### Fixed
- **Organization-Kontaktdaten und Logo wurden nie ausgegeben.** Der Code las die ACF-Optionen
  `logo`, `phone`, `email` und `address`, die im Agency Core nicht existieren. Jetzt werden die
  tatsächlichen Felder gelesen (`logo_desktop`, `top_header_phone`, `top_header_email`,
  `top_header_address`, `top_header_social`) – nur, wenn der Top Header aktiv ist und der
  jeweilige Eintrag nicht abgeschaltet wurde. Die Schema-Einstellungen haben Vorrang.
- JSON-LD-Ausgabe: Inhalte mit `</script>` konnten den Script-Block verlassen
  (`JSON_HEX_TAG`/`JSON_HEX_AMP` ergänzt).

---

## [1.9.2] - 2026-08-19

### media-lab-seo 1.9.2

#### Fixed
- Wöchentlicher Report nutzte für Analytics-Daten und die angezeigte
  Datumsspanne einen hart codierten 28-Tage-Zeitraum statt der
  konfigurierten `mlt_default_range`-Einstellung, wodurch GSC- und
  Analytics-Zahlen bei geändertem Standard-Zeitraum aus verschiedenen
  Perioden stammten. Zeitraum wird jetzt einmalig über
  `MLT_GSC_API::get_active_range()` bestimmt und für GSC, Analytics
  und die Header-Anzeige einheitlich verwendet.
  
---

## [1.9.1] - 2026-08-13

### media-lab-seo 1.9.1

#### Fixed
- Veralteter Docblock-Kommentar in `inc/class-report-mailer.php` korrigiert
  - verwies fälschlich auf `class-settings.php` als Ort der Hook-Registrierung
  (Überbleibsel aus einer früheren Version mit einem inzwischen entfernten
  Legacy-Handler, siehe 1.9.0). Rein kosmetisch, keine Funktionsänderung.

---

## [1.9.0] - 2026-06-30

### media-lab-seo 1.9.0

#### Added
- **Consent-Rate-Tracking** (`inc/class-consent-stats.php`, neu) - DSGVO-Compliance-Auswertung, wie viele Besucher Analytics-Consent geben

#### Fixed
- **Kritisch: Wöchentlicher SEO-Report wurde doppelt versendet.** Gefunden beim Janecka-Projekt - zwei Mails zur exakt selben Minute, mit unterschiedlichen Templates. Ursache: Ein alter Legacy-Handler (`send_weekly_report()`/`build_report_html()` in `class-settings.php`) und der eigentliche `MLT_Report_Mailer::send()` waren beide auf denselben Cron-Hook `mlt_weekly_report` registriert. Legacy-Handler entfernt, nur noch `MLT_Report_Mailer` sendet.
- GA4-OAuth-Verbindungsfehler durch falsch konfigurierten SMTP-Benutzernamen (Agency-Core-Einstellung, nicht Plugin-Bug) - beim Debuggen mit aufgefallen und dokumentiert

---

## [1.8.0] - 2026-06-24

### media-lab-seo 1.8.0

#### Added
- SEO-Dashboard um dynamische Datumsbereichsoptionen und einen konfigurierbaren Standard-Zeitraum in den Einstellungen erweitert (vorher fest auf 28 Tage)

---

## [1.7.0] - 2026-06-24

### media-lab-seo 1.7.0

#### Added
- **Bing Webmaster Tools Verifizierung** - neues Einstellungsfeld `mlt_bing_verification`, gibt bei gesetztem Wert ein `<meta name="msvalidate.01">`-Tag aus, direkt nach dem GSC-Verifizierungs-Tag in `class-seo.php`. Einrichtungsanleitung siehe README.md.

---

## [1.6.0] - 2026-06-23

### media-lab-seo 1.6.0

#### Added
- **Dynamische Report-Empfänger** (`inc/report-recipients.php`, neu) - beliebig viele Empfänger-Adressen statt nur der WordPress-Admin-E-Mail
- **Konfigurierbarer Versand-Zeitplan** (`inc/report-schedule.php`, neu) - Wochentag und Uhrzeit für den wöchentlichen Report einstellbar statt fix Montag 08:00 Uhr

---

## [1.5.0] - 2026-06-11

### media-lab-seo 1.5.0

#### Changed
- GA4-API-Integration überarbeitet/erweitert (Detail-Umfang dieses Commits nicht vollständig dokumentiert - Commit-Message erwähnt zusätzlich Übersetzungsarbeiten an media-lab-woocommerce, die nicht zu diesem Plugin gehören und hier nicht aufgeführt sind)

---

## [1.4.0] - 2026-03-25

### media-lab-seo 1.4.0

**Merge-Release.** `media-lab-toolkit` (separat entwickeltes, schlankeres
Plugin: Open Graph, Twitter Cards, Canonical URLs, einfacher
GSC-Verifizierungs-Meta-Tag, Consent-aware GA4/GTM-Tracking mit Agency-Core-
Cookie-Consent-Bridge) und das bisherige `media-lab-seo` (GSC OAuth2 API,
SEO-Dashboard, Report-Mailer, GA4+Matomo-Adapter) wurden zu einem
gemeinsamen Plugin zusammengeführt.

#### Changed
- Plugin-Ordner `media-lab-toolkit/` → `media-lab-seo/`, Haupt-Datei
  `media-lab-toolkit.php` → `media-lab-seo.php`, Plugin-Name-Header
  `Media Lab Toolkit` → `Media Lab SEO Toolkit`, Text Domain
  `media-lab-toolkit` → `media-lab-seo`. Interne Konstanten (`MLT_*`) und
  Option-Keys (`mlt_*`) bewusst unverändert gelassen, um bestehende
  DB-Einträge nicht zu invalidieren.
- Admin-Menüpunkt von „ML Toolkit" auf „SEO Toolkit" umbenannt

#### Fixed
- Versionsnummer bei diesem Merge fälschlich von `1.3.0` auf `1.1.0`
  zurückgesetzt statt fortgesetzt - siehe Hinweis am Anfang dieser Datei.
  Kein Feature-Verlust, nur die Zählung war falsch.

---

## [1.3.0] - 2026-03-10

### media-lab-seo 1.3.0

#### Added
- **Analytics-Adapter** (`inc/class-analytics-adapter.php`, neu) - pluggbare Schnittstelle für Pageview-/Traffic-Daten, austauschbar per Filter `medialab_analytics_adapter`
- **GA4 Data API Adapter** - Authentifizierung via Service-Account-JWT (RS256), kein zweiter OAuth-Flow nötig
- **Matomo Reporting API Adapter**

---

## [1.2.0] - 2026-03-10

### media-lab-seo 1.2.0

#### Added
- **SEO-Dashboard** (`inc/class-seo-dashboard.php`, neu) - KPI-Kacheln (Klicks, Impressionen, Ø CTR, Ø Position, jeweils vs. Vorperiode), Top-10-Keywords- und Top-10-Seiten-Tabellen, plus WordPress-Dashboard-Widget
- **GSC OAuth2 API** (`inc/class-gsc-api.php`, neu) - vollständige Google-Search-Console-Anbindung via OAuth2 Authorization Code Flow, Token-Speicherung in `wp_options`, automatische Token-Erneuerung, 1h-Cache via Transients
- **Wöchentlicher Report-Mailer** (`inc/class-report-mailer.php`, `inc/class-report-template.php`, neu) - HTML-Report mit GSC-KPIs, Top-Keywords, Top-Seiten, automatischer Versand via Agency-Core-SMTP

---

## [1.1.1] - 2026-03-04

### media-lab-seo 1.1.1

#### Fixed
- Patch-Release (Detail-Umfang nicht überliefert - Commit-Message enthielt keine Beschreibung)

---

## [1.1.0] - 2026-03-04

### media-lab-seo 1.1.0

#### Changed
- Release (Detail-Umfang nicht überliefert - Commit-Message enthielt keine Beschreibung über "release: v1.1.0" hinaus)

---

## [1.0.0] - 2026-02-16

### media-lab-seo 1.0.0

#### Added
- Initiales Release (13 Dateien)
- Open Graph Tags (`og:type`, `og:url`, `og:title`, `og:description`, `og:image`, `og:locale`, `og:site_name`)
- Twitter Cards (`summary_large_image`)
- Canonical-URL-Ausgabe (WordPress-eigene Canonical-Ausgabe deaktiviert, um Dopplung zu vermeiden)
- GSC-Verifizierungs-Meta-Tag (`google-site-verification`)
