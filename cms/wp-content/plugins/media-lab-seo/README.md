# Media Lab SEO Toolkit

SEO- und Analytics-Plugin für Media Lab Kundenprojekte. Kombiniert Google
Search Console (OAuth2), Google Analytics 4 (OAuth2) / Matomo, Schema.org,
Breadcrumbs, einen Redirect-Manager, Consent-aware Tracking und einen
wöchentlichen HTML-Report-Mailer in einem Plugin.

Entwickelt von [Media Lab Tritremmel GmbH](https://media-lab.at).

---

## Features

- **SEO-Grundlagen**: Open Graph Tags, Twitter Cards, Canonical URLs (WordPress-eigene Canonical-Ausgabe wird deaktiviert, um Dopplung zu vermeiden)
- **Schema.org JSON-LD** als verknüpfter `@graph`: Organization/LocalBusiness, WebSite, WebPage, BreadcrumbList, BlogPosting (+ Autor), Service, Person (Team), JobPosting, CreativeWork, FAQPage (automatisch erkannt), OfferCatalog (`[pricing_table]`), Place (`[google_map]`), LocalBusiness je Standort (`[mlb_booking_form]`), Event. Stammdaten unter **SEO Toolkit → Schema**, Ausnahmen per Metabox pro Seite, Erweiterung über `mlt_schema_*`-Filter
- **llms.txt**: automatisch generierte Seitenübersicht unter `/llms.txt` (Community-Format, kein offizieller Standard), Schalter unter **SEO Toolkit → Schema**
- **Breadcrumbs**: PHP-Funktion für Templates + Schema.org-Markup
- **Redirect-Manager**: 301/302, Wildcard-Pfade, Import/Export als CSV
- **Google Search Console** — vollständige OAuth2-Anbindung: automatischer Datenabruf, Token-Erneuerung, Cache
- **Google Analytics 4** — OAuth2-Anbindung, nutzt **dieselben Zugangsdaten wie GSC** (ein Client-ID/Secret-Paar für beide); Service-Account-JSON nur als Legacy-Fallback für bestehende Setups
- **Bing Webmaster Tools**: Verifizierungs-Meta-Tag (`msvalidate.01`). Die Verifizierungs-Codes für Search Console und Bing werden geprüft: URLs/Domains werden abgelehnt, ein eingefügter ganzer Meta-Tag wird auf den Code gekürzt
- **SEO-Dashboard** im WP-Backend + Dashboard-Widget: KPI-Kacheln (Klicks, Impressionen, Ø CTR, Ø Position, Seitenaufrufe, Nutzer) mit Veränderung gegenüber einem **Vergleichszeitraum** (Vorperiode oder Vorjahr, einmal unter **SEO Toolkit → Einstellungen** gesetzt, gilt auch für den E-Mail-Report), Top-Keywords und Top-Seiten (mit Veränderung pro Zeile), konfigurierbarer Datumsbereich, **Verlaufs-Chart** (Tageswerte mit gestrichelter Vergleichslinie, serverseitig gerendertes SVG ohne JS-Bibliothek)
- **Analytics-Adapter** (pluggbar): GA4 oder Matomo, austauschbar per Filter, eigene Adapter über ein PHP-Interface möglich
- **Consent-aware Tracking**: GA4/GTM über Google Consent Mode v2, Tracking startet erst nach Cookie-Consent (Bridge zu Agency-Core Cookie Consent)
- **Consent-Rate-Tracking**: DSGVO-Auswertung, wie viele Besucher Analytics-Consent geben
- **Wöchentlicher Report-Mailer**: HTML-Report per E-Mail mit Veränderung gegenüber dem Vergleichszeitraum, Säulendiagrammen (Tageswerte, Vergleich grau) und Balken in den Listen (reines Tabellen-HTML, ohne Bilder/JS), dynamische Empfänger-Liste (nicht nur Admin-E-Mail), konfigurierbarer Versandtag/-uhrzeit, Test-Mail-Button

---

## Voraussetzungen

- WordPress 6.0+
- PHP 8.0+
- `media-lab-agency-core` muss aktiv sein — ohne Agency Core deaktiviert sich das Plugin beim Aktivierungsversuch automatisch und zeigt eine Admin-Notice
- SMTP-Versand (für den Report-Mailer) wird ausschließlich über Agency Core konfiguriert: **Agency Core → E-Mail / SMTP**


## Dependencies

Keine Composer-Abhängigkeiten — kein `composer.json`/`composer.lock`
vorhanden. Auch die GA4-Service-Account-JWT-Signierung (RS256) läuft
komplett über PHP-native `openssl_sign()`/`openssl_pkey_get_private()`,
keine externe JWT-Bibliothek nötig. (Ausnahme im Starter Kit:
`media-lab-backup`, das phpseclib3 für SSH-Key-Auth benötigt.)

---

## Installation

1. Upload nach `/wp-content/plugins/media-lab-seo/`
2. Sicherstellen, dass **Media Lab Agency Core** aktiv ist
3. Im WordPress-Backend aktivieren
4. Einstellungen unter **SEO Toolkit** konfigurieren

---

## Google Search Console & Google Analytics 4 einrichten

GSC und GA4 teilen sich **ein** OAuth-Zugangsdaten-Paar (Client ID + Secret)
— einmal in der Google Cloud Console einrichten, deckt beide Dienste ab.

1. Projekt in der [Google Cloud Console](https://console.cloud.google.com/) anlegen
2. Beide APIs aktivieren: **Search Console API** und **Google Analytics Data API**
3. OAuth2-Zugangsdaten erstellen (Typ: Webanwendung), **beide** Redirect-URIs eintragen:
   - GSC: `{deine-domain}/wp-admin/admin.php?page=media-lab-seo&mlt_gsc_callback=1`
   - GA4: `{deine-domain}/wp-admin/admin.php?page=media-lab-seo&mlt_ga4_callback=1`
4. **SEO Toolkit → Einstellungen**, Karte „Google Search Console": Client ID, Client Secret, Property-URL eintragen (z.B. `https://example.at/` oder `sc-domain:example.at`)
5. **SEO Toolkit → Einstellungen**, Karte „Google Analytics 4": GA4 Property-ID eintragen (numerisch, z.B. `123456789` — **nicht** `G-XXXXXXXX`; zu finden unter GA4 → Verwaltung → Property-Einstellungen). Nutzt automatisch dieselbe Client ID/Secret wie GSC.
6. **SEO Toolkit → Dashboard → „Mit Google verbinden"** klicken — autorisiert GSC **und** GA4 in einem Schritt

Verbindung trennen: jeweils eigener „Verbindung trennen"-Link/Button (GSC und GA4 unabhängig voneinander trennbar).

> **Legacy-Fallback GA4:** Falls ein Projekt noch mit dem älteren Service-Account-Verfahren läuft (JSON-Key statt OAuth), greift das automatisch als Fallback, sobald keine GA4-OAuth-Verbindung aktiv ist. Für neue Projekte ist der OAuth-Weg oben der vorgesehene.

---

## Bing Webmaster Tools einrichten

1. [bing.com/webmasters](https://www.bing.com/webmasters) aufrufen, mit Microsoft-Konto anmelden
2. „Meine Website hinzufügen" → URL eintragen
3. Verifizierungsmethode „Meta-Tag" wählen, den Wert aus dem `content`-Attribut kopieren
4. Wert in **SEO Toolkit → Einstellungen**, Karte „SEO" → Feld „Bing Webmaster Tools – Verification Code" eintragen, speichern
5. In Bing Webmaster Tools auf „Überprüfen" klicken

Tipp: Die GSC-Property lässt sich in Bing direkt importieren ("Aus GSC importieren") — dann entfällt der manuelle Sitemap-Upload.

---

## Matomo als Alternative zu GA4

Nur **ein** Analytics-Adapter ist gleichzeitig aktiv, gesteuert über die Einstellung „Provider" (`ga4` oder `matomo`).

- **SEO Toolkit → Einstellungen**, Karte „Matomo": URL, Site-ID, API-Token eintragen

### Eigenen Adapter implementieren

Der Adapter-Filter heißt `mlt_analytics_adapter` und erwartet ein Objekt, das `MLT_Analytics_Adapter_Interface` implementiert. Optional (seit 1.12.0) kann es zusätzlich `MLT_Analytics_Timeseries_Interface` mit `get_timeseries( $start, $end ): ?array` (Datum `Y-m-d` → `['pageviews' => int, 'sessions' => int]`, `null` bei Fehler) implementieren – dann erscheinen auch Analytics-Charts im Dashboard:

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

## Wöchentlicher Report

**SEO Toolkit → Einstellungen**, Karte „Wöchentlicher Report": Report aktivieren, Empfänger (beliebig viele), Versandtag, Uhrzeit konfigurieren. Der Button „Test-Report senden" schickt den echten Report mit aktuellen Zahlen nur an die erste ausgefüllte Adresse. Die Einstellungsseite zeigt den „Nächsten geplanten Versand"; steht dort „—", ist kein Termin geplant (ab 1.12.0 plant sich der Event bei aktivem Schalter selbst).

Zeitraum und Vergleich des Reports kommen aus der Karte „SEO": **Standard-Zeitraum** und **Vergleichszeitraum** (Vorperiode / Vorjahreszeitraum / kein Vergleich) – dieselben Einstellungen wie im Dashboard. Die Search Console speichert nur rund 16 Monate; liegt der Vergleichszeitraum weiter zurück (z. B. bei 365 Tagen), entfällt der Vergleich.

Report-Inhalt per Filter erweiterbar:

```php
add_filter( 'mlt_weekly_report_html', function( $html, $data, $to ) {
    return $html . '<p>Zusätzliche Infos...</p>';
}, 10, 3 );
```

WP-CLI:
```bash
wp cron event run mlt_weekly_report   # Report sofort auslösen
wp cron event list | grep mlt         # Nächsten geplanten Versand anzeigen
```

---

## Hooks

Nur tatsächlich im Code vorhandene Hooks (Stand 1.14.0, verifiziert gegen den Quellcode):

### Actions
| Hook | Beschreibung |
|---|---|
| `mlt_weekly_report` | Cron-Hook für den wöchentlichen Report-Versand — genau **ein** Handler (`MLT_Report_Mailer::send()`) sollte hier registriert sein, siehe CHANGELOG 1.9.0 zu einem früher aufgetretenen Duplikat-Mail-Bug |
| `medialab_log_event` | Wird vom Plugin ausgelöst (Redirect angelegt/gelöscht/umgeschaltet, GSC-/GA4-Verbindung hergestellt/getrennt, GA4-API-Fehler). Der Handler liegt außerhalb dieses Plugins |

### Filter – Report, Dashboard, Analytics
| Filter | Parameter | Beschreibung |
|---|---|---|
| `mlt_weekly_report_html` | `$html`, `$data`, `$to` | Report-HTML vor dem Versand anpassen (`$data` enthält u. a. `compare`) |
| `mlt_weekly_report_subject` | `$subject`, `$week`, `$year` | Betreff anpassen |
| `mlt_report_max_html_bytes` | `$bytes` | Größenlimit des Report-HTML (Standard 80000); darüber entfallen die Säulendiagramme (Gmail kürzt ab ca. 102 KB) |
| `mlt_compare_mode` | `$mode` | Vergleichsmodus überschreiben: `previous_period`, `previous_year`, `off` |
| `mlt_compare_tolerance_note` | `$note` | Text des Messtoleranz-Hinweises unter dem Vergleich anpassen oder mit leerem String ausblenden |
| `mlt_analytics_adapter` | `$adapter` | Eigenen Analytics-Adapter einstecken (muss `MLT_Analytics_Adapter_Interface` implementieren) |
| `mlt_breadcrumb_items` | `$items` | Breadcrumb-Einträge anpassen |

### Filter – Schema.org
| Filter | Parameter | Beschreibung |
|---|---|---|
| `mlt_schema_enabled` | `$enabled` | Schema-Ausgabe komplett an/aus (bei Yoast/Rank Math/SEOPress standardmäßig aus) |
| `mlt_schema_graph` | `$graph`, `$schema` | Fertigen `@graph` vor der Ausgabe ändern |
| `mlt_schema_organization` | `$node` | Organization-/LocalBusiness-Knoten anpassen |
| `mlt_schema_org_types` | `$types` | Erlaubte Organisationstypen |
| `mlt_schema_post_type_builders` | `$builders` | Schema-Builder je Post-Type ergänzen/ändern |
| `mlt_schema_article_type` | `$type`, `$post` | Standard `BlogPosting` ändern |
| `mlt_schema_faq_items` | `$items`, `$post` | Erkannte FAQ-Einträge ändern |
| `mlt_schema_description` | `$text`, `$post` | Beschreibungstext |
| `mlt_schema_author_is_person` | `$is_real`, `$author` | Autor als `Person` statt Organisation ausgeben |
| `mlt_schema_author_url` | `$url`, `$author` | Autoren-URL |
| `mlt_schema_person_contact` | `$allow`, `$post` | Kontaktdaten bei `Person` ausgeben |
| `mlt_schema_location_email` | `$allow`, `$id` | Standort-E-Mail ausgeben (standardmäßig nie) |
| `mlt_schema_default_country` | `$country` | Standard-Land (`AT`) |

### Filter – llms.txt
| Filter | Parameter | Beschreibung |
|---|---|---|
| `mlt_llms_txt_enabled` | `$enabled` | Ausgabe an/aus |
| `mlt_llms_txt_description` | `$summary` | Ein-Satz-Beschreibung unter dem H1 |
| `mlt_llms_txt_sections` | `$sections` | Abschnitte ergänzen/ändern |
| `mlt_llms_txt_post_limit` | `$limit` | Anzahl Blogbeiträge |
| `mlt_llms_txt_content` | `$content` | Fertigen Text nachbearbeiten |

## Troubleshooting

**Dashboard zeigt keine Daten** — Verbindung prüfen (zeigt „Mit Google verbinden"?), Property-URL exakt mit trailing slash prüfen, GSC hat ~3 Tage Verzögerung bei neuen Websites, Cache leeren.

**Report-Mail kommt nicht an** — `wp cron event list | grep mlt`, `wp option get mlt_last_report_status` prüfen, SMTP-Konfiguration in Agency Core checken.

**Report-Mail kommt doppelt an** — sollte seit 1.9.0 behoben sein (Duplikat-Cron-Hook entfernt, siehe CHANGELOG). Falls es erneut auftritt: prüfen, ob `add_action( 'mlt_weekly_report', ...)` irgendwo außerhalb von `class-report-mailer.php` registriert wird.

**GA4: Verbindung schlägt fehl** — Ist die GA4-Redirect-URI (`&mlt_ga4_callback=1`) zusätzlich zur GSC-URI in der Google Cloud Console eingetragen? Ist die „Google Analytics Data API" aktiviert (separat von der Search Console API)? Property-ID korrekt (numerisch, nicht `G-XXXXXXXX`)?

**Matomo: „Site nicht gefunden"** — Site-ID korrekt? API-Token hat Lesezugriff auf diese Site?

---

## Changelog

Siehe [CHANGELOG.md](./CHANGELOG.md) für die vollständige Versionshistorie.
Aktuelle Version: siehe `Version:`-Header in `media-lab-seo.php`.

---

## Lizenz

GPL v2 or later — https://www.gnu.org/licenses/gpl-2.0.html
