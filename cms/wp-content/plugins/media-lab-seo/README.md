# Media Lab SEO Toolkit

SEO- und Analytics-Plugin für Media Lab Kundenprojekte. Kombiniert Google
Search Console (OAuth2), Google Analytics 4 (OAuth2) / Matomo, Schema.org,
Breadcrumbs, einen Redirect-Manager, Consent-aware Tracking und einen
wöchentlichen HTML-Report-Mailer in einem Plugin.

Entwickelt von [Media Lab Tritremmel GmbH](https://media-lab.at).

---

## Features

- **SEO-Grundlagen**: Open Graph Tags, Twitter Cards, Canonical URLs (WordPress-eigene Canonical-Ausgabe wird deaktiviert, um Dopplung zu vermeiden)
- **Schema.org JSON-LD**: WebSite (inkl. SearchAction), Organization, Article (bei Posts), BreadcrumbList — fest im Code hinterlegt, aktuell nicht per Filter erweiterbar
- **Breadcrumbs**: PHP-Funktion für Templates + Schema.org-Markup
- **Redirect-Manager**: 301/302, Wildcard-Pfade, Import/Export als CSV
- **Google Search Console** — vollständige OAuth2-Anbindung: automatischer Datenabruf, Token-Erneuerung, Cache
- **Google Analytics 4** — OAuth2-Anbindung, nutzt **dieselben Zugangsdaten wie GSC** (ein Client-ID/Secret-Paar für beide); Service-Account-JSON nur als Legacy-Fallback für bestehende Setups
- **Bing Webmaster Tools**: Verifizierungs-Meta-Tag (`msvalidate.01`)
- **SEO-Dashboard** im WP-Backend + Dashboard-Widget: KPI-Kacheln (Klicks, Impressionen, Ø CTR, Ø Position) vs. Vorperiode, Top-Keywords, Top-Seiten, konfigurierbarer Datumsbereich
- **Analytics-Adapter** (pluggbar): GA4 oder Matomo, austauschbar per Filter, eigene Adapter über ein PHP-Interface möglich
- **Consent-aware Tracking**: GA4/GTM über Google Consent Mode v2, Tracking startet erst nach Cookie-Consent (Bridge zu Agency-Core Cookie Consent)
- **Consent-Rate-Tracking**: DSGVO-Auswertung, wie viele Besucher Analytics-Consent geben
- **Wöchentlicher Report-Mailer**: HTML-Report per E-Mail, dynamische Empfänger-Liste (nicht nur Admin-E-Mail), konfigurierbarer Versandtag/-uhrzeit, Test-Mail-Button

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
   - GSC: `{deine-domain}/wp-admin/admin.php?page=media-lab-seo&gsc_oauth=callback` *(genauer Parametername: im Dashboard/den Einstellungen nachsehen)*
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

Der Adapter-Filter heißt `mlt_analytics_adapter` und erwartet ein Objekt, das `MLT_Analytics_Adapter_Interface` implementiert:

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

**SEO Toolkit → Einstellungen**, Karte „Wöchentlicher Report": Empfänger (beliebig viele), Versandtag, Uhrzeit konfigurieren. Test-Mail-Button für sofortiges Feedback.

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

Nur tatsächlich im Code vorhandene Hooks (Stand 1.9.2, verifiziert gegen den Quellcode):

### Actions
| Hook | Beschreibung |
|---|---|
| `mlt_weekly_report` | Cron-Hook für den wöchentlichen Report-Versand — genau **ein** Handler (`MLT_Report_Mailer::send()`) sollte hier registriert sein, siehe CHANGELOG 1.9.0 zu einem früher aufgetretenen Duplikat-Mail-Bug |

### Filter
| Filter | Parameter | Beschreibung |
|---|---|---|
| `mlt_weekly_report_html` | `$html`, `$data`, `$to` | Report-HTML vor dem Versand anpassen |
| `mlt_weekly_report_subject` | `$subject`, `$week`, `$year` | Betreff anpassen |
| `mlt_analytics_adapter` | `$adapter` | Eigenen Analytics-Adapter einstecken (muss `MLT_Analytics_Adapter_Interface` implementieren) |

> **Hinweis:** Frühere Versionen dieser README nannten zusätzlich
> `medialab_seo_schema_types` (Schema.org-Erweiterung) und
> `medialab_matomo_sslverify` (SSL-Verify für Matomo) — beide existieren
> im aktuellen Code **nicht**. Schema.org-Typen sind in `class-schema.php`
> fest hinterlegt (WebSite, Organization, Article, BreadcrumbList), ohne
> Erweiterungs-Hook. Falls diese Funktionen gebraucht werden, müssten sie
> neu gebaut werden - hier bewusst nicht als vorhanden dokumentiert.

---

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
