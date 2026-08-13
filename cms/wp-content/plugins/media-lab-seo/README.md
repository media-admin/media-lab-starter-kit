# Media Lab SEO Toolkit

SEO- und Analytics-Plugin für Media Lab Kundenprojekte. Kombiniert Google
Search Console (OAuth2), Google Analytics 4 / Matomo, Schema.org,
Breadcrumbs, einen Redirect-Manager, Consent-aware Tracking und einen
wöchentlichen HTML-Report-Mailer in einem Plugin.

Entwickelt von [Media Lab Tritremmel GmbH](https://media-lab.at).

---

## Features

- **SEO-Grundlagen**: Open Graph Tags, Twitter Cards, Canonical URLs (WordPress-eigene Canonical-Ausgabe wird deaktiviert, um Dopplung zu vermeiden)
- **Schema.org JSON-LD**: Organization, WebSite (inkl. SearchAction), Article, Product (WooCommerce), BreadcrumbList — erweiterbar per Filter
- **Breadcrumbs**: PHP-Funktion für Templates + Schema.org-Markup
- **Redirect-Manager**: 301/302, Wildcard-Pfade, Import/Export als CSV
- **Google Search Console** — vollständige OAuth2-Anbindung (nicht nur ein Verifizierungs-Tag): automatischer Datenabruf, Token-Erneuerung, 1h-Cache
- **Bing Webmaster Tools**: einfacher Verifizierungs-Meta-Tag (`msvalidate.01`)
- **SEO-Dashboard** im WP-Backend + Dashboard-Widget: KPI-Kacheln (Klicks, Impressionen, Ø CTR, Ø Position) vs. Vorperiode, Top-10-Keywords, Top-10-Seiten, konfigurierbarer Datumsbereich
- **Analytics-Adapter** (pluggbar): GA4 Data API (Service-Account-JWT, kein zweiter OAuth-Flow) oder Matomo Reporting API — austauschbar per Filter, auch eigene Adapter möglich
- **Consent-aware Tracking**: GA4/GTM über Google Consent Mode v2, Tracking startet erst nach Cookie-Consent (Bridge zu Agency-Core Cookie Consent)
- **Consent-Rate-Tracking**: DSGVO-Auswertung, wie viele Besucher Analytics-Consent geben
- **Wöchentlicher Report-Mailer**: HTML-Report per E-Mail, dynamische Empfänger-Liste (nicht nur Admin-E-Mail), konfigurierbarer Versandtag/-uhrzeit, Test-Mail-Button

---

## Voraussetzungen

- WordPress 6.0+
- PHP 8.0+
- `media-lab-agency-core` muss aktiv sein — ohne Agency Core deaktiviert sich das Plugin beim Aktivierungsversuch automatisch und zeigt eine Admin-Notice
- SMTP-Versand (für den Report-Mailer) wird ausschließlich über Agency Core konfiguriert: **Agency Core → E-Mail / SMTP**

---

## Installation

1. Upload nach `/wp-content/plugins/media-lab-seo/`
2. Sicherstellen, dass **Media Lab Agency Core** aktiv ist
3. Im WordPress-Backend aktivieren
4. Einstellungen unter **SEO Toolkit** konfigurieren

---

## Google Search Console einrichten

1. Projekt in der [Google Cloud Console](https://console.cloud.google.com/) anlegen, **Search Console API** aktivieren
2. OAuth2-Zugangsdaten erstellen (Typ: Webanwendung), autorisierte Redirect-URI eintragen (wird im Dashboard angezeigt)
3. **SEO Toolkit → Dashboard → Einstellungen**: Client ID, Client Secret, Property-URL eintragen (exakt wie in GSC, z.B. `https://example.at/` oder `sc-domain:example.at`)
4. **SEO Toolkit → Dashboard → „Mit Google verbinden"** klicken, Google-Konto autorisieren

Verbindung trennen: Dashboard oben rechts, löscht Tokens und Cache.

---

## Bing Webmaster Tools einrichten

1. [bing.com/webmasters](https://www.bing.com/webmasters) aufrufen, mit Microsoft-Konto anmelden
2. „Meine Website hinzufügen" → URL eintragen
3. Verifizierungsmethode „Meta-Tag" wählen, den Wert aus dem `content`-Attribut kopieren
4. Wert in **SEO Toolkit → Einstellungen → SEO → Bing Webmaster Tools** eintragen, speichern
5. In Bing Webmaster Tools auf „Überprüfen" klicken

Tipp: Die GSC-Property lässt sich in Bing direkt importieren ("Aus GSC importieren") — dann entfällt der manuelle Sitemap-Upload.

---

## Analytics-Adapter einrichten

Nur **ein** Adapter ist gleichzeitig aktiv. Priorität: externer Filter `medialab_analytics_adapter` → GA4 (falls konfiguriert) → Matomo (falls konfiguriert) → Stub (0-Werte).

### GA4
- Google Analytics Data API in der Cloud Console aktivieren
- Service Account erstellen, JSON-Key herunterladen
- Service-Account-E-Mail in GA4 → Verwaltung → Kontozugriff als **Betrachter** hinzufügen
- **SEO Toolkit → Einstellungen → GA4**: Property-ID (numerisch, nicht `G-XXXXXXXX`) + Service-Account-JSON eintragen

### Matomo
- **SEO Toolkit → Einstellungen → Matomo**: URL, Site-ID, API-Token eintragen, „Verbindung testen"
- Dev-Umgebung ohne SSL: `add_filter( 'medialab_matomo_sslverify', '__return_false' );`

### Eigenen Adapter implementieren

```php
add_filter( 'medialab_analytics_adapter', function() {
    return new class {
        public function is_configured(): bool { return true; }
        public function get_label(): string { return 'Mein Anbieter'; }
        public function get_overview( string $start, string $end ): array {
            return [ 'pageviews' => 0, 'sessions' => 0, 'users' => 0, 'bounce_rate' => 0.0 ];
        }
        public function get_top_sources( int $limit = 5 ): array { return []; }
    };
} );
```

---

## Wöchentlicher Report

**SEO Toolkit → Einstellungen → Wöchentlicher Report**: Empfänger (beliebig viele), Versandtag, Uhrzeit konfigurieren. Test-Mail-Button für sofortiges Feedback.

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

### Actions
| Hook | Beschreibung |
|---|---|
| `mlt_weekly_report` | Cron-Hook für den wöchentlichen Report-Versand — genau **ein** Handler (`MLT_Report_Mailer::send()`) sollte hier registriert sein, siehe CHANGELOG 1.9.0 zu einem früher aufgetretenen Duplikat-Mail-Bug |

### Filter
| Filter | Parameter | Beschreibung |
|---|---|---|
| `mlt_weekly_report_html` | `$html`, `$data`, `$to` | Report-HTML vor dem Versand anpassen |
| `mlt_weekly_report_subject` | `$subject`, `$week`, `$year` | Betreff anpassen |
| `medialab_analytics_adapter` | – | Eigenen Analytics-Adapter registrieren |
| `medialab_seo_schema_types` | `$types`, `$post` | Eigene Schema.org-Typen ergänzen |
| `medialab_matomo_sslverify` | – | SSL-Verifikation für Matomo deaktivieren (Dev-Umgebungen) |

---

## Troubleshooting

**Dashboard zeigt keine Daten** — Verbindung prüfen (zeigt „Mit Google verbinden"?), Property-URL exakt mit trailing slash prüfen, GSC hat ~3 Tage Verzögerung bei neuen Websites, Cache leeren.

**Report-Mail kommt nicht an** — `wp cron event list | grep mlt`, `wp option get mlt_last_report_status` prüfen, SMTP-Konfiguration in Agency Core checken.

**Report-Mail kommt doppelt an** — sollte seit 1.9.0 behoben sein (Duplikat-Cron-Hook entfernt, siehe CHANGELOG). Falls es erneut auftritt: prüfen, ob `add_action( 'mlt_weekly_report', ...)` irgendwo außerhalb von `class-report-mailer.php` registriert wird.

**GA4: „Token-Anfrage fehlgeschlagen"** — JSON-Key vollständig (inkl. `private_key`)? Service-Account-E-Mail in GA4 als Betrachter hinterlegt? Data API in der Cloud Console aktiviert?

**Matomo: „Site nicht gefunden"** — Site-ID korrekt? API-Token hat Lesezugriff auf diese Site?

---

## Changelog

Siehe [CHANGELOG.md](./CHANGELOG.md) für die vollständige Versionshistorie.
Aktuelle Version: siehe `Version:`-Header in `media-lab-seo.php`.

---

## Lizenz

GPL v2 or later — https://www.gnu.org/licenses/gpl-2.0.html
