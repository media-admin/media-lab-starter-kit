# SEO Dokumentation

**Version:** 1.9.1 | **Letzte Aktualisierung:** 2026-08-13
**Plugin:** `media-lab-seo` v1.9.1

> Diese Doku wurde am 13.08.2026 komplett überarbeitet. Der vorherige
> Stand (Version 1.13.0 / 2026-03-10, Plugin v1.3.0) enthielt mehrere
> nicht mehr zutreffende bzw. nie existierende Angaben - u.a. ein falsches
> Funktions-Präfix (`medialab_gsc_*` statt der tatsächlichen `MLT_GSC_API`-
> Klasse), einen falschen Analytics-Adapter-Filter-Namen, einen erfundenen
> Schema.org-Erweiterungs-Hook und einen erfundenen Matomo-SSL-Filter.
> Dieser Stand ist gegen den Quellcode verifiziert (`inc/class-gsc-api.php`,
> `inc/class-ga4-api.php`, `inc/class-analytics-adapter.php`,
> `inc/class-schema.php`, `inc/class-settings.php`,
> `inc/class-seo-dashboard.php`, `inc/class-report-mailer.php`).

---

## Inhaltsverzeichnis

1. [Übersicht](#übersicht)
2. [Installation & Menü](#installation--menü)
3. [SEO Dashboard](#seo-dashboard)
4. [Google Search Console & Google Analytics 4](#google-search-console--google-analytics-4)
5. [Bing Webmaster Tools](#bing-webmaster-tools)
6. [Matomo (Alternative zu GA4)](#matomo-alternative-zu-ga4)
7. [Wöchentlicher Report-Mailer](#wöchentlicher-report-mailer)
8. [Schema.org Markup](#schemaorg-markup)
9. [Open Graph Tags](#open-graph-tags)
10. [Twitter Cards](#twitter-cards)
11. [Breadcrumbs](#breadcrumbs)
12. [Weiterleitungen](#weiterleitungen)
13. [Consent-Rate-Tracking](#consent-rate-tracking)
14. [Troubleshooting](#troubleshooting)

---

## Übersicht

`media-lab-seo` ist das zentrale SEO- und Analytics-Plugin des Starter
Kits. Benötigt zwingend `media-lab-agency-core` als aktives Plugin.

| Modul | Beschreibung | Seit |
|---|---|---|
| Schema.org | WebSite, Organization, Article, BreadcrumbList (fest im Code, kein Erweiterungs-Hook) | v1.0.0 |
| Open Graph | Social Sharing (Facebook, LinkedIn) | v1.0.0 |
| Twitter Cards | Rich Previews auf Twitter/X | v1.0.0 |
| Breadcrumbs | Navigation + Schema.org BreadcrumbList | v1.0.0 |
| Canonical URLs | Duplicate Content Prevention | v1.0.0 |
| Weiterleitungen | 301/302-Manager im Backend | v1.0.0 |
| SEO Dashboard | GSC-KPIs, eigenständiger Menüpunkt | v1.2.0 |
| GSC API | Google Search Console OAuth2-Anbindung | v1.2.0 |
| Report-Mailer | Wöchentlicher HTML-Report per E-Mail | v1.2.0 |
| Matomo-Adapter | Matomo Reporting API | v1.3.0 |
| Dynamische Report-Empfänger/Zeitplan | Mehrere Empfänger, konfigurierbarer Versandtag/-uhrzeit | v1.6.0 |
| Bing Webmaster Tools | Verifizierungs-Meta-Tag | v1.7.0 |
| Konfigurierbarer Dashboard-Datumsbereich | Shortcuts (7/28/90/365 Tage) + freier Picker | v1.8.0 |
| Consent-Rate-Tracking | DSGVO Consent-Auswertung | v1.9.0 |
| GA4-OAuth-Adapter | Primärer GA4-Datenweg, teilt Zugangsdaten mit GSC | siehe Hinweis unten |

> **GA4-Historie unklar:** Aus welcher Version genau der OAuth-Umbau für
> GA4 (statt Service-Account-JSON) stammt, ist aus der verfügbaren
> Commit-Historie nicht eindeutig rekonstruierbar (mutmaßlich zwischen
> 1.5.0 und 1.9.0). Sicher ist: im aktuellen Code (`class-ga4-api.php`)
> ist OAuth der primäre, vorgesehene Weg; Service-Account-JSON ist nur
> Fallback für ältere Projekte. Für ein exaktes Datum müsste die
> Commit-Historie von `inc/class-ga4-api.php` gezielt ausgewertet werden.

---

## Installation & Menü

### Plugin aktivieren

```bash
wp plugin activate media-lab-seo
```

### Menü-Struktur

```
SEO Toolkit (Top-Level-Menüpunkt)
├── Einstellungen   (Slug: media-lab-seo)
└── Dashboard       (Slug: mlt-dashboard)
```

**Einstellungen und Dashboard sind zwei gleichrangige Untermenüpunkte**,
keine Verschachtelung - beide werden über eigene `add_submenu_page()`-
Aufrufe registriert (`class-settings.php` bzw. `class-seo-dashboard.php`).

Die Einstellungen-Seite ist als Grid aus mehreren Karten aufgebaut, u.a.:
„SEO" (Meta-Description, Bing-Tag), „Google Search Console" (OAuth),
„Google Analytics 4" (Property-ID), „Matomo", „Wöchentlicher Report".

> Der Top-Level-Menüpunkt heißt **„SEO Toolkit"** (`add_menu_page()` in
> `class-settings.php`) - vorherige Doku-Stände nannten hier fälschlich
> „Media Lab SEO".

---

## SEO Dashboard

### Wo zu finden

**WordPress Admin → SEO Toolkit → Dashboard**

Zusätzlich ein WP-Dashboard-Widget auf der Übersichtsseite (`wp_dashboard_setup`-Hook).

### Datumsbereich (seit v1.8.0)

- Shortcuts: 7 / 28 / 90 / 365 Tage
- Freier Datepicker (von/bis)
- Standard-Zeitraum in den Einstellungen konfigurierbar (`mlt_default_range`)
- Zeitraum wird als URL-Parameter übergeben (`?mlt_range=90` oder `?mlt_start=...&mlt_end=...`)

### Was angezeigt wird

**KPI-Kacheln** (GSC-Daten, Zeitraum vs. Vorperiode):
- Klicks, Impressionen, Ø CTR, Ø Position

**Tabellen:** Top-Keywords, Top-Seiten

**Consent-Rate-Card** (seit v1.9.0): eigener Umschalter „Letzte 30 Tage" / „Woche vs. Vorwoche", siehe [Consent-Rate-Tracking](#consent-rate-tracking).

### AJAX-Refresh

Eigener Endpunkt `wp_ajax_mlt_refresh_gsc` zum manuellen Neuladen der GSC-Daten aus dem Dashboard heraus.

---

## Google Search Console & Google Analytics 4

**Wichtig: GSC und GA4 teilen sich ein OAuth-Zugangsdaten-Paar** (Client
ID + Secret, Options-Keys `mlt_gsc_client_id`/`mlt_gsc_client_secret`,
in `class-ga4-api.php` als "shared OAuth credentials" referenziert).
Einmal einrichten, deckt beide Dienste ab.

### Voraussetzungen

1. Projekt in der [Google Cloud Console](https://console.cloud.google.com/)
2. Zwei APIs aktivieren: **Search Console API** und **Google Analytics Data API**
3. OAuth2-Zugangsdaten erstellen (Typ: Webanwendung)
4. **Beide** Redirect-URIs eintragen (GSC und GA4 haben unterschiedliche Callback-Parameter)

### Einrichtung Schritt für Schritt

**1. Google Cloud Console**
```
Neues Projekt → APIs & Dienste → Bibliothek
→ „Google Search Console API" aktivieren
→ „Google Analytics Data API" aktivieren

APIs & Dienste → Anmeldedaten → + Anmeldedaten erstellen
→ OAuth-Client-ID → Webanwendung
→ Autorisierte Weiterleitungs-URIs (beide eintragen):
   GSC: siehe Hinweis unten - exakten Parameter im Dashboard prüfen
   GA4: https://deine-domain.at/wp-admin/admin.php?page=media-lab-seo&mlt_ga4_callback=1
→ Client-ID und Client-Secret kopieren
```

> Der exakte GSC-Redirect-Parameter war zum Zeitpunkt dieser Überarbeitung
> nicht am Code verifiziert (`class-gsc-api.php` lag nicht vor). Vor dem
> ersten Einrichten die tatsächliche URI direkt aus der Plugin-
> Einstellungsseite kopieren, statt sich auf diese Doku zu verlassen.

**2. WordPress Backend**
```
SEO Toolkit → Einstellungen, Karte „Google Search Console"
→ Client ID, Client Secret, Property-URL eintragen
   (exakt wie in GSC, z.B. https://example.at/ oder sc-domain:example.at)

SEO Toolkit → Einstellungen, Karte „Google Analytics 4"
→ Property-ID eintragen (numerisch, z.B. 123456789 - NICHT G-XXXXXXXX)
   GA4 → Verwaltung → Property-Einstellungen
→ Nutzt automatisch dieselbe Client ID/Secret wie GSC
```

**3. Verbinden**
```
SEO Toolkit → Dashboard
→ „Mit Google verbinden" klicken
→ Google-Konto auswählen + Zugriff erlauben
→ Autorisiert GSC UND GA4 in einem Schritt
```

### Verbindung trennen

GSC und GA4 sind unabhängig voneinander trennbar (GA4: `admin_post_mlt_ga4_disconnect`-Handler, löscht Tokens + Caches gezielt für GA4).

### GA4 Legacy-Fallback (Service Account)

Für Projekte, die noch mit dem älteren Verfahren laufen: Service-Account-JSON + Property-ID in den Options `mlt_ga4_service_account_json`/`mlt_ga4_property_id`. Greift automatisch, wenn keine OAuth-Verbindung aktiv ist (`MLT_GA4_Data_Adapter`-Konstruktor prüft OAuth zuerst, fällt sonst auf Service Account zurück). Für neue Projekte nicht mehr der vorgesehene Weg.

### Technische Details

```
GSC-Authentifizierung: OAuth2 Authorization Code Flow
GA4-Authentifizierung: OAuth2 Authorization Code Flow (geteilte Credentials mit GSC)
GA4-Token-Speicherung: wp_options, AES-256-CBC verschlüsselt (mlt_ga4_oauth_access_token etc.)
GA4-Cache: WordPress Transients, 6 Stunden TTL
GSC-Verzögerung: ~3 Tage (marktüblich für Search-Console-Daten)
```

---

## Bing Webmaster Tools

Seit v1.7.0. Einfacher Verifizierungs-Meta-Tag, kein OAuth.

1. [bing.com/webmasters](https://www.bing.com/webmasters) aufrufen, mit Microsoft-Konto anmelden
2. „Meine Website hinzufügen" → URL eintragen
3. Verifizierungsmethode „Meta-Tag" wählen, Wert aus dem `content`-Attribut kopieren
4. **SEO Toolkit → Einstellungen**, Karte „SEO" → Feld „Bing Webmaster Tools – Verification Code" eintragen
5. In Bing Webmaster Tools auf „Überprüfen" klicken

Tipp: GSC-Property lässt sich in Bing direkt importieren, dann entfällt der manuelle Sitemap-Upload.

---

## Matomo (Alternative zu GA4)

Nur **ein** Analytics-Adapter ist gleichzeitig aktiv (Einstellung „Provider": `ga4` oder `matomo`).

```
SEO Toolkit → Einstellungen, Karte „Matomo"

Matomo URL:    https://matomo.example.at/
Site ID:       1   (Matomo → Verwaltung → Websites)
API-Token:     ••••  (Matomo → Persönliche Einstellungen → API-Token)
```

### Eigenen Adapter implementieren

Filter: `mlt_analytics_adapter`. Erwartet ein Objekt, das `MLT_Analytics_Adapter_Interface` implementiert:

```php
add_filter( 'mlt_analytics_adapter', function( $adapter ) {
    return new class implements MLT_Analytics_Adapter_Interface {
        public function is_available(): bool { return true; }
        public function get_overview( string $start, string $end ): array {
            return [ 'pageviews' => 0, 'sessions' => 0, 'users' => 0 ];
        }
        public function get_sources( string $start, string $end, int $limit = 5 ): array {
            return [];
        }
        public function get_top_pages( string $start, string $end, int $limit = 10 ): array {
            return [];
        }
    };
} );
```

---

## Wöchentlicher Report-Mailer

### Konfiguration

```
SEO Toolkit → Einstellungen, Karte „Wöchentlicher Report"

Empfänger:  beliebig viele (seit v1.6.0, vorher nur Admin-E-Mail als Fallback)
Versandtag: konfigurierbar (seit v1.6.0, vorher fix Montag)
Uhrzeit:    konfigurierbar (seit v1.6.0)
```

### Report-Inhalt

HTML-Mail, Inline-CSS. Genauer Aufbau (KPI-Kacheln, Anzahl Top-Keywords/-Seiten) nicht am Code verifiziert für diese Überarbeitung - siehe `inc/class-report-template.php` im Zweifel direkt.

**Betreff-Format:** `[Sitename] SEO Report KW {Woche}/{Jahr}` (aus `class-report-mailer.php`, per Filter `mlt_weekly_report_subject` anpassbar).

### Wichtig: nur ein Handler auf dem Cron-Hook

`mlt_weekly_report` darf **ausschließlich** von `MLT_Report_Mailer::send()` behandelt werden. Ein früherer, inzwischen entfernter Legacy-Handler in `class-settings.php` führte zu doppelt versendeten Reports (siehe [Troubleshooting](#troubleshooting) und Plugin-CHANGELOG 1.9.0).

### Test-Mail senden

Über die Einstellungsseite, Karte „Wöchentlicher Report" (Test-Mail-Button).

### WP-CLI

```bash
wp cron event run mlt_weekly_report   # Report sofort auslösen
wp cron event list | grep mlt         # Nächsten geplanten Versand anzeigen
```

---

## Schema.org Markup

Fest im Code hinterlegt (`class-schema.php`), **kein Erweiterungs-Filter** vorhanden:

| Typ | Wann |
|---|---|
| `WebSite` | Immer (inkl. `SearchAction` für die Sitesuche) |
| `Organization` | Immer (Logo aus ACF-Feld `logo` bzw. `mlt_og_default_image`, optional Telefon/E-Mail/Adresse aus ACF) |
| `Article` | Bei Einzelposts (`post_type === 'post'`) |
| `BreadcrumbList` | Wenn Breadcrumbs für die Seite vorhanden sind |

> **Korrektur:** Frühere Doku-Stände nannten zusätzlich `Product`
> (WooCommerce) und einen Erweiterungs-Filter
> (`medialab_seo_schema_types`) - beides existiert im aktuellen Code
> nicht. Wer zusätzliche Schema-Typen braucht, muss `class-schema.php`
> direkt erweitern.

---

## Open Graph Tags

Automatisch auf allen Seiten – Bild: Featured Image → Default Social Image (`mlt_og_default_image`).

```html
<meta property="og:title"       content="Seitentitel">
<meta property="og:description" content="Beschreibung">
<meta property="og:image"       content="https://.../bild.jpg">
<meta property="og:url"         content="https://...">
```

**Testen:** https://developers.facebook.com/tools/debug/

---

## Twitter Cards

```html
<meta name="twitter:card"  content="summary_large_image">
<meta name="twitter:title" content="Seitentitel">
<meta name="twitter:image" content="https://.../bild.jpg">
```

**Testen:** https://cards-dev.twitter.com/validator

---

## Breadcrumbs

```php
if ( function_exists( 'medialab_seo_breadcrumbs' ) ) {
    medialab_seo_breadcrumbs( [
        'separator'     => ' › ',
        'home_title'    => 'Home',
        'wrapper_class' => 'breadcrumbs',
    ] );
}
```

---

## Weiterleitungen

**SEO Toolkit → Einstellungen → Redirects**

- 301 (permanent) und 302 (temporär)
- Wildcard-Pfade unterstützt
- Import/Export als CSV

---

## Consent-Rate-Tracking

Seit v1.9.0 (`inc/class-consent-stats.php`). DSGVO-Auswertung, wie viele
Besucher Analytics-Consent geben - liest **read-only** aus der
Agency-Core-Tabelle `wp_mlt_consent_log`, kein eigener Schreibzugriff
und kein eigener Tracking-Code in diesem Modul. Erscheint als eigene
Card im SEO-Dashboard mit Umschalter „Letzte 30 Tage" / „Woche vs.
Vorwoche".

---

## Troubleshooting

### Dashboard zeigt keine Daten

1. Verbindung prüfen: `SEO Toolkit → Dashboard` – zeigt es „Mit Google verbinden"?
2. Property-URL exakt prüfen (mit trailing slash, z.B. `https://example.at/`)
3. GSC-Verzögerung: Neue Websites haben ~3 Tage Verzögerung
4. Cache leeren (Dashboard-Button, falls vorhanden)

### Report-Mail kommt nicht an

```bash
wp eval "wp_mail('test@example.at', 'Test', 'Test');"
wp cron event list | grep mlt
wp option get mlt_last_report_status
```

### Report-Mail kommt doppelt an

**Historisch aufgetreten, seit v1.9.0 behoben:** Zwei Handler waren auf
denselben Cron-Hook (`mlt_weekly_report`) registriert - ein alter
Legacy-Handler in `class-settings.php` und der eigentliche
`MLT_Report_Mailer::send()`. Legacy-Handler wurde entfernt. Falls das
Problem erneut auftritt: prüfen, ob irgendwo außerhalb von
`class-report-mailer.php` ein `add_action( 'mlt_weekly_report', ...)`
registriert wird.

### GA4: Verbindung schlägt fehl

- „Google Analytics Data API" in der Cloud Console aktiviert? (separat von der Search Console API)
- GA4-Redirect-URI (`&mlt_ga4_callback=1`) zusätzlich zur GSC-URI eingetragen?
- Property-ID korrekt (numerisch, nicht `G-XXXXXXXX`)?
- Falls Legacy-Service-Account genutzt wird: JSON-Key vollständig (inkl. `private_key`)? Service-Account-E-Mail in GA4 als Betrachter hinzugefügt?

### Matomo: „Site nicht gefunden"

- Site-ID korrekt (Zahl aus Matomo → Websites)?
- API-Token hat Lesezugriff auf diese Site?

### Menüpunkt nicht sichtbar

```bash
wp plugin deactivate media-lab-seo && wp plugin activate media-lab-seo
```

---

## Weiterführende Docs

- [Analytics-Dokumentation](12_ANALYTICS.md)
- [Plugin-Übersicht](03_PLUGINS.md)
- [ACF-Felder](09_ACF-FIELDS.md)
- [Deployment](10_DEPLOYMENT.md)
