# SEO Dokumentation

**Version:** 1.10.1 | **Letzte Aktualisierung:** 2026-09-22
**Plugin:** `media-lab-seo` v1.10.1

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

> **Update 2026-09-22 (v1.10.0):** Der Abschnitt „Schema.org Markup" wurde
> komplett neu geschrieben. Vorher gab es kein verknüpftes Markup und
> keinen Erweiterungs-Filter; jetzt ist die Ausgabe ein verknüpfter
> `@graph` mit 13 Filtern, die praktisch jeden Baustein erweiterbar
> machen. Verifiziert gegen `inc/class-schema.php`,
> `inc/class-schema-sources.php`, `inc/class-schema-admin.php`.
>
> **Nachtrag 2026-09-22 (v1.10.1):** Beim Praxistest auf
> `media-lab-starter-kit.localdev` fiel auf, dass FAQ und Team auf der
> Beispiel-Seite trotz sichtbarem Inhalt nicht als Schema erschienen.
> Ursache: Die FAQ-Erkennung suchte nach einem nie existierenden
> Shortcode `[faq]` statt dem echten `[faq_accordion]`
> (`inc/shortcodes.php`), und der Team-Shortcode `[team_member]`
> (Daten als Attribute direkt im Content, kein CPT-Post) wurde
> überhaupt nicht ausgewertet. Beides korrigiert, mit dem realen
> Seitenquelltext gegengetestet.

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
| Schema.org | Verknüpfter `@graph`: Organization/LocalBusiness, WebSite, WebPage, Breadcrumbs, Inhaltstypen, FAQPage – erweiterbar per Filter | v1.0.0 (Graph ab v1.10.0) |
| Schema-Quellen | Preistabellen, Karten, Booking-Standorte, Events, Team-Mitglieder | v1.10.0 (Team seit v1.10.1) |
| Schema-Admin | Einstellungen, Metabox pro Seite, Autoren-Profilfelder | v1.10.0 |
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
├── Schema          (Slug: mlt-schema)
└── Dashboard       (Slug: mlt-dashboard)
```

**Einstellungen, Schema und Dashboard sind drei gleichrangige
Untermenüpunkte**, keine Verschachtelung - jeweils über eigene
`add_submenu_page()`-Aufrufe registriert (`class-settings.php`,
`class-schema-admin.php` bzw. `class-seo-dashboard.php`).

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

Ausgabe im `<head>` (Priorität 5) als **ein** JSON-LD-Block mit `@graph`. Alle Knoten sind per
`@id` verknüpft (Organization ← WebSite ← WebPage ← Hauptentität). Standard: **alles automatisch** –
manuelles Eingreifen ist nur die Ausnahme.

### Was wird ausgegeben

| Knoten | Wann | Hinweise |
|---|---|---|
| `Organization` (bzw. `LocalBusiness`-Untertyp) | immer | Typ, Adresse, Öffnungszeiten, sameAs unter **SEO Toolkit → Schema** |
| `WebSite` | immer | inkl. `SearchAction` |
| `WebPage` / `CollectionPage` / `SearchResultsPage` / `ProfilePage` | jede Ansicht außer 404 | Autoren-Archiv = `ProfilePage`; Archive und Beitragsseite = `CollectionPage`; Seitentyp per Metabox überschreibbar |
| `BreadcrumbList` | ab 2 Ebenen (nicht bei Suche/404) | aus `MLT_Breadcrumbs::get_items()` |
| `BlogPosting` + `Person` | Einzelbeitrag (`post`) | Autor nur bei echtem Namen, sonst Organisation |
| `Service` | CPT `service` | `serviceType` aus Taxonomie `service_category`; **kein** `Offer` (Feld `price` ist Freitext) |
| `Person` | CPT `team` (`[team_query]`) **oder** `[team_member]`-Shortcode (auch verschachtelt in `[team_cards]`) | CPT: Titel aus `position` bzw. `role`, Profile aus `social_links`/`social_media`. Shortcode: Attribute `name`, `role`, `image`, `linkedin`/`twitter`/`facebook`/`instagram`, Bio aus dem Shortcode-Inhalt. Beide: E-Mail/Telefon nur per Filter |
| `JobPosting` | CPT `job` | `employment_type`, `application_deadline` (→ `validThrough`), `location`, `remote` |
| `CreativeWork` | CPT `project` | `project_date` → `dateCreated` |
| `FAQPage` | Seiten mit FAQ-Inhalt | siehe unten |
| `OfferCatalog` | Seiten mit `[pricing_table]` | nur Tabellen mit eindeutiger Zahl als Preis |
| `Place` | Seiten mit `[google_map id="…"]` | benötigt das Feld `address`; `geo` nur, wenn `latitude`/`longitude` vorhanden |
| `LocalBusiness` je Standort | Seiten mit `[mlb_booking_form]` | Adresse, Telefon, Öffnungszeiten, Leistungen (`mlb_services`), `ReserveAction` |
| `Event` | CPT `event` | nur mit gültigem `event_date_start` |

Nicht enthalten (bewusst): `Product` (macht WooCommerce selbst), Testimonials/Bewertungen
(Firmen-Selbstbewertungen sind für Google-Rich-Results nicht zulässig), projektspezifische Typen
außerhalb des Starter Kits (Erweiterung über `mlt_schema_post_type_builders` bzw. `mlt_schema_graph`
im jeweiligen Projekt).

### FAQ-Erkennung

Eine Seite wird automatisch zur `FAQPage`, wenn ihr Inhalt enthält:

- `[faq_accordion category="…" limit="…"]` – Fragen/Antworten aus dem CPT `faq` (Frage = Titel,
  Antwort = `post_content`; das ACF-Feld `answer` ist laut Code-Kommentar in `inc/shortcodes.php`
  veraltet und wird **nicht** mehr gelesen)
- `<details><summary>Frage</summary>Antwort</details>` (inkl. Core-Details-Block)

Der native Block `medialab/accordion` wird **nicht** erkannt (seine Items liegen nicht im Block-Inhalt) –
dafür den Filter `mlt_schema_faq_items` nutzen. Fragen werden dedupliziert (über den Fragetext), maximal
50 pro Seite; bei doppelten Fragen zählt die erste gefundene Antwort.

> **Korrektur (v1.10.1):** Vorherige Stände dieser Doku und `08_CUSTOM-POST-TYPES.md` nannten
> `[faq]` bzw. `[faq style="accordion"]` als Shortcode-Namen – dieser Tag existiert im Code nicht
> und wurde nie erkannt. Der tatsächliche Name ist `[faq_accordion]`.

> Google zeigt FAQ-Rich-Results seit 2023 nur noch für ausgewählte Seiten (v. a. Behörden und
> Gesundheit). Das Markup ist für KI-/Antwortsysteme (AEO) trotzdem sinnvoll, ein Rich Snippet ist
> aber nicht garantiert.

### Stammdaten (Organisation)

Quellen in dieser Reihenfolge:

1. **SEO Toolkit → Schema** (Telefon, E-Mail, Straße, PLZ, Ort, Land, Öffnungszeiten, Einzugsgebiet, sameAs)
2. **Agency Core → Top Header / Kontaktdaten** – nur wenn der Top Header aktiv ist und der jeweilige
   Eintrag nicht abgeschaltet wurde: `top_header_phone`, `top_header_email`, `top_header_address`
   (Feld „PLZ & Stadt" wird als `2620 Neunkirchen` in PLZ + Ort zerlegt), `top_header_social` → `sameAs`
3. Logo: `logo_desktop` (Agency Core → Logo / Globale Einstellungen), sonst das Social-Default-Bild

Öffnungszeiten werden nur bei einem `LocalBusiness`-Untertyp ausgegeben. Zeilenformat z. B.
`Mo-Fr 08:00-17:00`. Land: Standard `AT`.

### Autoren

- Autor wird als `Person` (mit Bio und `sameAs`) ausgegeben, wenn der Anzeigename ein echter Name ist.
  Bei „admin", Anzeigename = Login oder leer ist die **Organisation** der Autor.
- `sameAs` = Website aus dem Profil + die Felder **LinkedIn / Xing / Instagram / X / Facebook /
  YouTube (URL)** im WP-Benutzerprofil.
- Sind Autoren-Archive gesperrt oder umgeleitet, die Person-`url` per Filter `mlt_schema_author_url`
  auf eine sinnvolle Seite (Team/Über uns) setzen.

### Manuelle Steuerung (Ausnahmefall)

Metabox **„Schema (SEO / AEO)"** in der Seitenleiste jedes öffentlichen Inhaltstyps:

| Feld | Wirkung |
|---|---|
| Schema für diese Seite deaktivieren | keinerlei Schema-Ausgabe auf dieser Seite |
| Seitentyp | überschreibt `WebPage` (AboutPage, ContactPage, CollectionPage, ProfilePage, FAQPage, ItemPage) |
| Eigenes JSON-LD | nur Administratoren; ein Node oder eine Liste von Nodes, ohne `<script>` und ohne `@context`; wird in den Graph aufgenommen; ungültiges JSON wird nicht gespeichert |

Meta-Keys: `_mlt_schema_disable`, `_mlt_schema_webpage_type`, `_mlt_schema_custom`.

### Erweiterung per Filter

| Filter | Parameter | Zweck |
|---|---|---|
| `mlt_schema_enabled` | `$enabled` | Schema komplett an/aus (Standard: aus bei Yoast/Rank Math/SEOPress) |
| `mlt_schema_graph` | `$graph`, `MLT_Schema $schema` | fertigen Graph nachbearbeiten (Array nach `@id` indiziert) |
| `mlt_schema_organization` | `$node` | Organization-Node anpassen |
| `mlt_schema_org_types` | `$types` | erlaubte Organisationstypen |
| `mlt_schema_post_type_builders` | `$map` | Post Type → Callback `( WP_Post, string $page_id, MLT_Schema )`, liefert Node, Node-Liste oder `null` |
| `mlt_schema_faq_items` | `$items`, `WP_Post` | FAQ-Quellen ergänzen (`[ 'question' => …, 'answer' => … ]`) |
| `mlt_schema_article_type` | `$type`, `WP_Post` | z. B. `NewsArticle` statt `BlogPosting` |
| `mlt_schema_description` | `$text`, `WP_Post` | Beschreibung anpassen |
| `mlt_schema_person_contact` | `false`, `WP_Post` | E-Mail/Telefon bei Team-Personen ausgeben (Standard: aus, DSGVO) |
| `mlt_schema_default_country` | `'AT'` | Standard-Ländercode |
| `mlt_schema_author_is_person` | `$bool`, `WP_User` | Person-Erkennung übersteuern |
| `mlt_schema_author_url` | `$url`, `WP_User` | URL der Autor-Person |
| `mlt_schema_location_email` | `false`, `$location_id` | Standort-E-Mail ausgeben (Standard: aus, interne Kopie-Adresse) |

```php
// FAQ aus eigenem ACF-Repeater ergänzen (z. B. für den Block medialab/accordion)
add_filter( 'mlt_schema_faq_items', function ( $items, $post ) {
    foreach ( (array) get_field( 'faq_items', $post->ID ) as $row ) {
        $items[] = [ 'question' => $row['frage'], 'answer' => $row['antwort'] ];
    }
    return $items;
}, 10, 2 );

// Projektspezifischen Typ ergänzen (z. B. für einen Post Type außerhalb des Starter Kits)
add_filter( 'mlt_schema_post_type_builders', function ( $map ) {
    $map['mein_typ'] = function ( WP_Post $post, string $page_id, MLT_Schema $schema ) {
        return [
            '@type'            => 'CreativeWork',
            '@id'              => $schema->get_url() . '#custom',
            'name'             => get_the_title( $post ),
            'mainEntityOfPage' => [ '@id' => $page_id ],
        ];
    };
    return $map;
} );
```

> **Korrektur:** Frühere Doku-Stände nannten `Product` (WooCommerce) als
> ausgegebenen Typ und behaupteten, es gebe keinen Erweiterungs-Filter
> (`medialab_seo_schema_types` existierte nie). Beides war zutreffend für
> den Stand bis v1.9.x. Seit v1.10.0 gibt es die 13 Filter oben; `Product`
> bleibt bewusst ausgespart, weil WooCommerce es selbst ausgibt.

### Einführung pro Projekt

1. Plugin-Dateien hochladen, **SEO Toolkit → Schema** ausfüllen (Organisationstyp, Adresse, Öffnungszeiten, sameAs).
2. Autoren prüfen: sinnvoller Anzeigename, Profil-Links im Benutzerprofil.
3. Testen mit dem Google [Rich Results Test](https://search.google.com/test/rich-results) und
   [validator.schema.org](https://validator.schema.org/): Startseite, Beitrag, Seite mit FAQ, Preisseite, Booking-Seite.
4. Auf **Doppel-Markup** prüfen: Das Theme (`medialab_breadcrumbs()`, Option `schema`, Standard `true`) und
   `MLT_Breadcrumbs::render()` (Microdata) können zusätzlich zum JSON-LD eine BreadcrumbList ausgeben –
   bei Bedarf `'schema' => false` setzen bzw. nur eine Variante verwenden.

### Bekannte Grenzen

- Booking-Standorte, Preise und Team-Mitglieder (`[team_member]`) werden nur auf Seiten ausgegeben,
  die den jeweiligen Shortcode enthalten.
- `event_location` und `event_price` sind Freitext: Ort wird als Text ausgegeben, ein Preis nur bei
  eindeutiger Zahl oder „frei". Google verlangt für Event-Rich-Results eine strukturierte Adresse.
- `gmap` liefert nach wie vor kein Telefon oder Öffnungszeiten (Felder existieren in der aktuellen
  Feldgruppe nicht). `geo`-Koordinaten erscheinen **nur** dann, wenn ein Post die Legacy-Felder
  `latitude`/`longitude` befüllt hat – die Feldgruppe kennt sie zwar noch, sie sind laut Feld-
  beschreibung aber „nicht mehr für die Kartenanzeige benötigt" und werden im Adminformular nicht
  mehr aktiv beworben. Neue `gmap`-Einträge haben sie also meist nicht gesetzt.

> **Korrektur (2026-09-22):** Frühere Stände dieser Notiz behaupteten pauschal, `gmap` liefere „keine
> Koordinaten". Das war zu undifferenziert – die Felder existieren, sind aber optional und in der
> Praxis meist leer. Siehe `08_CUSTOM-POST-TYPES.md` → „Google Maps" für die vollständige Feldliste.

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

### Schema wird nicht ausgegeben

- Ist Yoast SEO, Rank Math oder SEOPress gleichzeitig aktiv? Dann schaltet sich die Ausgabe automatisch
  ab (Vermeidung von Doppel-Markup) - per Filter `mlt_schema_enabled` überschreibbar.
- Wurde „Schema für diese Seite deaktivieren" in der Metabox aktiviert?
- Im Quelltext nach `<!-- Media Lab SEO Toolkit: Schema.org -->` suchen und den Block im Google
  [Rich Results Test](https://search.google.com/test/rich-results) validieren.

---

## Weiterführende Docs

- [Analytics-Dokumentation](12_ANALYTICS.md)
- [Plugin-Übersicht](03_PLUGINS.md)
- [ACF-Felder](09_ACF-FIELDS.md)
- [Deployment](10_DEPLOYMENT.md)