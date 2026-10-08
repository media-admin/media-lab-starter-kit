# SEO Dokumentation

**Version:** 1.15.0 | **Letzte Aktualisierung:** 2026-10-05
**Plugin:** `media-lab-seo` v1.15.0

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
>
> **Nachtrag 2026-09-23 (v1.10.2):** `/llms.txt`-Ausgabe ergänzt
> (`inc/class-llms-txt.php`, neu), siehe [llms.txt](#llmstxt) weiter
> unten.
>
> **Nachtrag 2026-10-05 (v1.11.0):** Neuer zentraler **Vergleichszeitraum**
> (Vorperiode / Vorjahr) für Dashboard, WP-Widget und E-Mail-Report, siehe
> [Vergleichszeitraum](#vergleichszeitraum). Außerdem gegen den Quellcode
> korrigiert: die GSC-Redirect-URI (`mlt_gsc_callback=1`), der Breadcrumbs-
> Funktionsname (`mlt_breadcrumbs()` statt des nie existierenden
> `medialab_seo_breadcrumbs()`), der Menüpunkt „Weiterleitungen" (eigener
> Untermenüpunkt) und der tatsächliche Aufbau des Report-Mails. Die Angabe
> „KPI-Kacheln … vs. Vorperiode" stand schon in älteren Ständen, war aber
> bis v1.10.x nicht implementiert (es wurden nur Absolutwerte gezeigt). Seit
> v1.11.0 stimmt sie. Ebenfalls in v1.11.0: `/llms.txt` wird jetzt tatsächlich
> ausgeliefert (die Klasse wurde in v1.10.2 zwar angelegt, aber nicht geladen),
> und fehlgeschlagene API-Abrufe werden nicht mehr als „0" gecacht. Neu im Vergleich außerdem ein
> übersetzbarer Hinweis zur Messtoleranz.
>
> **Nachtrag 2026-10-05 (v1.12.0):** **Verlaufs-Chart** im SEO-Dashboard (siehe
> [Verlaufs-Chart](#verlaufs-chart)), der Test-Button sendet den **echten Report**, und zwei
> Fehler sind behoben: der wöchentliche Report wurde bei neu aktivierter Einstellung nie
> automatisch versendet (Cron-Hook-Name), und der Test-Mail-Button tat gar nichts
> (JavaScript-Fehler). Außerdem waren die Karten auf der Dashboard-Seite unformatiert und die
> Consent-Balken unsichtbar.
>
> **Nachtrag 2026-10-05 (v1.13.0):** Der E-Mail-Report enthält jetzt **Säulendiagramme und
> Balken** (siehe [Report-Inhalt](#report-inhalt)). Technisch reines Tabellen-HTML, weil
> E-Mail-Programme weder JavaScript noch SVG zuverlässig darstellen.
>
> **Nachtrag 2026-10-05 (v1.14.0):** **Veränderung pro Zeile** in Top Keywords und Top Seiten
> (Dashboard und Mail), siehe [Vergleichszeitraum](#vergleichszeitraum). Gleichzeitig zeigt die
> Seitenliste bei gleichem Pfad auf verschiedenen Hosts jetzt den Host mit an.
>
> **Nachtrag 2026-10-05 (v1.14.1):** Der Verlaufs-Chart zeichnet Tage ohne Daten am Ende des
> Zeitraums nicht mehr als „0" (Datenverzögerung, siehe [Verlaufs-Chart](#verlaufs-chart));
> CTR/Position erscheinen mit Dezimalkomma; Titel des WP-Dashboard-Widgets korrigiert.
>
> **Nachtrag 2026-10-05 (v1.15.0):** Die Felder für die **Verifizierungs-Codes** (Search Console,
> Bing) werden geprüft – eine URL im Feld wird abgelehnt und nie als Meta-Tag ausgegeben, ein
> eingefügter ganzer Tag wird auf den Code gekürzt (siehe
> [Verifizierung per Meta-Tag](#verifizierung-per-meta-tag)). Der E-Mail-Report nennt außerdem
> unter dem Säulendiagramm, wenn die jüngsten Tage wegen Datenverzögerung fehlen.

---

## Inhaltsverzeichnis

1. [Übersicht](#übersicht)
2. [Installation & Menü](#installation--menü)
3. [SEO Dashboard](#seo-dashboard) (inkl. [Vergleichszeitraum](#vergleichszeitraum))
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
14. [llms.txt](#llmstxt)
15. [Troubleshooting](#troubleshooting)

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
| llms.txt | Kuratierte `/llms.txt`-Ausgabe für KI-Systeme (Seiten, Leistungen, Blog) | v1.10.2 (ausgeliefert ab v1.11.0) |
| Vergleichszeitraum | Veränderung ▲/▼ gegenüber Vorperiode oder Vorjahr in Dashboard, Widget und Report | v1.11.0 |
| Verlaufs-Chart | Tageswerte (Klicks, Impressionen, Seitenaufrufe, Sessions) mit Vergleichslinie im Dashboard | v1.12.0 |
| Mail-Grafiken | Säulendiagramme und Balken im E-Mail-Report (Tabellen-HTML, ohne Bilder) | v1.13.0 |
| Zeilen-Deltas | Veränderung pro Keyword/Seite gegenüber dem Vergleichszeitraum (Dashboard + Mail) | v1.14.0 |
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
├── Einstellungen    (Slug: media-lab-seo)
├── Schema           (Slug: mlt-schema)
├── Dashboard        (Slug: mlt-dashboard)
└── Weiterleitungen  (Slug: mlt-redirects)
```

**Einstellungen, Schema, Dashboard und Weiterleitungen sind gleichrangige
Untermenüpunkte**, keine Verschachtelung - jeweils über eigene
`add_submenu_page()`-Aufrufe registriert (`class-settings.php`,
`class-schema-admin.php`, `class-seo-dashboard.php` bzw.
`class-redirects.php`).

Die Einstellungen-Seite ist als Grid aus mehreren Karten aufgebaut, u.a.:
„SEO" (Meta-Description, Bing-Tag, Standard-Zeitraum, Vergleichszeitraum,
Fallback-Bild), „Google Search Console" (OAuth), „Google Analytics 4"
(Property-ID), „Matomo", „Wöchentlicher Report".

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
- Standard-Zeitraum in den Einstellungen konfigurierbar (`mlt_default_range`), gilt für Dashboard, Widget **und** Report
- Zeitraum wird als URL-Parameter übergeben (`?mlt_range=90` oder `?mlt_start=...&mlt_end=...`); URL-Parameter gelten nur im Backend, der Report nutzt immer den Standard-Zeitraum
- Zentrale Funktion: `MLT_GSC_API::get_active_range()` (Dashboard, Widget und Report verwenden sie einheitlich)

> **Wichtig – Zeitraum endet bei „heute − 2 Tage":** Search-Console-Daten
> liegen mit einigen Tagen Verzögerung vor, deshalb endet jeder Zeitraum
> zwei Tage vor heute. Ein „28-Tage"-Zeitraum umfasst dadurch **27
> Kalendertage** (Start- und Endtag inklusive). Der Report-Mail nennt die
> tatsächliche Anzahl („Zeitraum: 27 Tage").

### Was angezeigt wird

**KPI-Kacheln** mit Veränderung gegenüber dem [Vergleichszeitraum](#vergleichszeitraum):
- Search Console: Klicks, Impressionen, Ø CTR, Ø Position
- Analytics (nur wenn GA4 oder Matomo verbunden ist): Seitenaufrufe, Nutzer

**Verlaufs-Chart** (seit v1.12.0): Karte „Verlauf" mit Reitern, siehe [Verlaufs-Chart](#verlaufs-chart).

**Tabellen:** Top-Keywords (10), Top-Seiten (10), Traffic-Quellen (5). Top-Keywords und Top-Seiten zeigen die [Veränderung pro Zeile](#veränderung-pro-zeile-top-keywords--top-seiten).

**Consent-Rate-Card** (seit v1.9.0): eigener Umschalter „Letzte 30 Tage" / „Woche vs. Vorwoche", siehe [Consent-Rate-Tracking](#consent-rate-tracking). Diese Card hat ihre **eigene** Vergleichslogik und folgt dem Vergleichszeitraum der Einstellungen nicht.

**WP-Dashboard-Widget:** vier Mini-Kacheln (Klicks, Impressionen, CTR, Ø Position) für den Standard-Zeitraum, ebenfalls mit Veränderung.

### Vergleichszeitraum

Seit v1.11.0. **Eine** Einstellung, die überall gilt: **SEO Toolkit → Einstellungen → Karte „SEO" → „Vergleichszeitraum"** (Option `mlt_compare_mode`).

| Modus | Vergleichszeitraum |
|---|---|
| **Vorperiode** (Standard) | gleich lang, lückenlos direkt vor dem aktuellen Zeitraum |
| **Vorjahreszeitraum** | dieselben Kalendertage ein Jahr früher (29.02. → 28.02.) |
| **Kein Vergleich** | keine Deltas, keine Zusatzabfragen |

Wirkt auf **SEO-Dashboard**, **WP-Dashboard-Widget** und den **wöchentlichen E-Mail-Report**. Im Dashboard folgt der Vergleich dem gewählten Zeitraum (Shortcut oder Datepicker), im Report dem Standard-Zeitraum. Die Seite zeigt unter dem Zeitraum „Vergleich mit Vorperiode (01.08.2026 – 27.08.2026)"; der Tooltip einer Kachel zeigt den Vergleichswert.

**Darstellung der Veränderung:**

| Kennzahl | Berechnung | Farbe |
|---|---|---|
| Klicks, Impressionen, Seitenaufrufe, Sessions, Nutzer | relative Veränderung in % („neu", wenn der Vergleichswert 0 war) | steigend = grün |
| Ø CTR | Differenz in **Prozentpunkten** (PP) | steigend = grün |
| Ø Position | absolute Differenz | **sinkend = grün** (niedrigere Position ist besser) |

Grau (▬) bedeutet unverändert.

**Wann kein Vergleich angezeigt wird (bewusst, statt falscher Zahlen):**

- Die Search Console speichert nur rund **16 Monate**. Reicht der Vergleichszeitraum weiter zurück (z. B. bei „365 Tage" oder bei Vorjahr mit sehr langem Zeitraum), gibt es für GSC keinen Vergleich; stattdessen erscheint ein Hinweis. Analytics wird davon nicht beeinflusst.
- Hat der aktuelle **oder** der Vergleichszeitraum keine Daten (junge Property, leerer oder fehlgeschlagener API-Abruf), werden keine Deltas angezeigt. Das verhindert Werte wie „−100 %" nach einem fehlgeschlagenen Abruf.

#### Veränderung pro Zeile (Top Keywords / Top Seiten)

Seit v1.14.0. Unter den Klicks jeder Zeile steht die **absolute** Veränderung gegenüber dem Vergleichszeitraum (z. B. `▲ +30`), unter der Position die Veränderung der Platzierung (`▼ −1,4` = besser, grün; `▲ +0,6` = schlechter, rot). Absolut statt in Prozent, weil Prozentwerte bei kleinen Zahlen („2 → 4 Klicks = +100 %") irreführen. Gilt für Dashboard und E-Mail-Report; die Kartenüberschrift nennt „Δ vs. Vorperiode".

- **„neu":** Der Eintrag war im Vergleichszeitraum nicht unter den **Top 500**. Für Klicks erscheint „▲ neu", eine Positionsveränderung gibt es dann nicht.
- **Abfragen:** Pro Tabelle eine zusätzliche, 6 Stunden gecachte Abfrage der ersten 500 Zeilen des Vergleichszeitraums (Konstante `MLT_Compare::ROW_COMPARE_LIMIT`). Der Abgleich läuft über den Suchbegriff bzw. die vollständige URL.
- **Wann keine Zeilen-Deltas erscheinen:** „Kein Vergleich", Vergleichszeitraum jenseits der 16-Monats-Grenze, Vergleichszeitraum ohne Daten oder fehlgeschlagene Abfrage – ohne belastbare Basis wird nichts angezeigt statt falscher Zahlen.
- **Seiten-Beschriftung:** `MLT_Compare::page_labels()` zeigt normalerweise nur den Pfad. Erscheint derselbe Pfad unter mehreren Adressen (z. B. `https://x.at/` und `https://www.x.at/`), steht zusätzlich der Host, im Zweifel die volle URL. Tritt das auf, ist das ein SEO-Hinweis: Die Seite ist unter mehreren Adressen indexiert, es fehlen Weiterleitungen (www ↔ ohne www, http → https) oder ein Canonical. Mit dem Tooltip (Dashboard) bzw. in der Search Console lässt sich die Variante prüfen.

**Hinweis zur Messtoleranz:** Unter den Kacheln (Dashboard) bzw. unter den Kennzahlen (E-Mail-Report) steht ein kurzer Text, der erklärt, warum Zahlen leicht von anderen Auswertungen abweichen können (nachträglich korrigierte Daten der letzten Tage, Datenschutz-Filterung bei Suchanfragen, fehlende Cookie-Zustimmung, unterschiedliche Zählweisen der Tools) und dass kleine Veränderungen bei niedrigen Zahlen wenig aussagekräftig sind. Damit sollen Rückfragen („warum weicht das von … ab?") gar nicht erst entstehen. Der Text erscheint nur, wenn tatsächlich Veränderungswerte angezeigt werden. Er ist **übersetzbar** (Text Domain `media-lab-seo`, Quelle in `MLT_Compare::tolerance_note()`) und lässt sich per Filter `mlt_compare_tolerance_note` pro Projekt anpassen oder mit einem leeren String ausblenden.

```php
// Hinweis kundenspezifisch formulieren
add_filter( 'mlt_compare_tolerance_note', function ( $note ) {
    return 'Alle Zahlen stammen aus Google; geringe Abweichungen sind normal.';
} );

// … oder ganz ausblenden
add_filter( 'mlt_compare_tolerance_note', '__return_empty_string' );
```

**Filter:** `mlt_compare_mode` überschreibt den Modus (`previous_period`, `previous_year`, `off`), z. B. projektspezifisch.

**Entwickler:** Zentrale Klassen in `inc/class-compare.php` – `MLT_Compare::get_range()` (Vergleichszeitraum ableiten), `MLT_Compare::fetch()` (Vergleichsdaten holen + Deltas berechnen, von Dashboard, Widget und Mailer gemeinsam genutzt) und `MLT_Delta` (Berechnung und Darstellung). Der Analytics-Adapter muss dafür nichts Neues können: Es wird einfach dieselbe `get_overview()`-Methode ein zweites Mal mit dem Vergleichszeitraum aufgerufen.

> **Mehr API-Abfragen:** Mit aktivem Vergleich verdoppeln sich die Abfragen
> (GSC und Analytics je einmal zusätzlich). Ergebnisse werden wie bisher 6
> Stunden gecacht.

### Verlaufs-Chart

Seit v1.12.0. Die Karte **„Verlauf"** steht unter den KPI-Kacheln und zeigt Tageswerte für den gewählten Zeitraum:

| Reiter | Quelle |
|---|---|
| Klicks, Impressionen | Search Console |
| Seitenaufrufe, Sessions | GA4 oder Matomo |

- **Durchgezogene Linie:** aktueller Zeitraum. **Gestrichelte graue Linie:** [Vergleichszeitraum](#vergleichszeitraum), tageweise übereinandergelegt (1. Tag mit 1. Tag usw.). Bei „Kein Vergleich" oder jenseits der 16-Monats-Grenze der Search Console fehlt nur die gestrichelte Linie.
- **Hover** (bzw. Antippen) zeigt pro Tag den Wert, den Vergleichstag samt Datum und die Veränderung in %.
- **Ohne Daten:** Hat eine Quelle keine Daten oder schlägt der Abruf fehl, fehlen die zugehörigen Reiter; ohne jede Datenquelle erscheint die Karte nicht.
- **Datenverzögerung (seit v1.14.1):** Google liefert Tageswerte mit Verzögerung, für die jüngsten Tage fehlen die Zeilen. Fehlen am Ende eines Zeitraums, der bis kurz vor heute reicht, **1–3 Tage komplett**, werden sie nicht gezeichnet (statt als „0" einen Einbruch vorzutäuschen); im Vergleichszeitraum entfallen gleich viele Tage, damit Tag für Tag verglichen wird. Die Legende nennt den tatsächlich dargestellten Zeitraum, darüber steht ein Hinweis („Die letzten 2 Tage werden noch nicht dargestellt"). Fehlen mehr als 3 Tage, bleibt die Linie unverändert – das ist wahrscheinlich echt. Gilt auch für die Säulendiagramme in der Mail; dort steht seit v1.15.0 unter dem Diagramm derselbe Hinweis (kleine graue Zeile).
- **Technik:** Die Charts sind serverseitig gerenderte Inline-SVGs (`MLT_Chart::line_svg()` in `inc/class-chart.php`) – keine Chart-Bibliothek, kein CDN, nichts muss ausgeliefert oder geladen werden. Nur das Umschalten der Reiter braucht JavaScript (`assets/dashboard.js`). Tageswerte holen `MLT_GSC_API::get_timeseries()` und `MLT_GA4_API::get_timeseries()` (je 6 Stunden gecacht, Fehlerabrufe nicht).
- **Mehr API-Abfragen:** Pro Seitenaufruf kommen bis zu vier Zeitreihen-Abfragen dazu (GSC und Analytics, jeweils aktueller Zeitraum und Vergleich), danach aus dem Cache.

**Eigener Analytics-Adapter:** Damit auch Analytics-Charts erscheinen, muss der Adapter zusätzlich `MLT_Analytics_Timeseries_Interface` implementieren:

```php
class Mein_Adapter implements MLT_Analytics_Adapter_Interface, MLT_Analytics_Timeseries_Interface {
    // … is_available(), get_overview(), get_sources(), get_top_pages() wie gehabt …

    /** @return array<string,array{pageviews:int,sessions:int}>|null  Datum (Y-m-d) → Werte; null bei Fehler */
    public function get_timeseries( string $start, string $end ): ?array {
        return [
            '2026-09-07' => [ 'pageviews' => 120, 'sessions' => 45 ],
            '2026-09-08' => [ 'pageviews' => 98,  'sessions' => 40 ],
        ];
    }
}
```

Adapter ohne dieses Interface funktionieren unverändert; das Dashboard zeigt dann nur die Search-Console-Charts.

### Daten aktualisieren (Cache)

Der Button **„🔄 Daten aktualisieren"** im Dashboard (Endpunkt `wp_ajax_mlt_refresh_gsc`) löscht **alle** GSC- und GA4-Transients (Vergleichsdaten eingeschlossen) und lädt die Seite neu.

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
   GSC: https://deine-domain.at/wp-admin/admin.php?page=media-lab-seo&mlt_gsc_callback=1
   GA4: https://deine-domain.at/wp-admin/admin.php?page=media-lab-seo&mlt_ga4_callback=1
→ Client-ID und Client-Secret kopieren
```

> Die beiden URIs sind gegen `class-gsc-api.php` (`get_redirect_uri()`)
> und `class-ga4-api.php` verifiziert. Sie werden zusätzlich in den
> Einstellungen unter „Redirect URI (in Google Cloud eintragen)"
> angezeigt – bei Abweichungen (z. B. Subdirectory-Setup wie `/cms`) gilt
> immer der dort angezeigte Wert.
>
> **Korrektur (v1.11.0):** Frühere Stände dieser Doku und die README nannten
> hier teils einen nicht verifizierten bzw. falschen GSC-Parameter
> (`gsc_oauth=callback`). Der echte Parameter ist `mlt_gsc_callback=1`.

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

### Verifizierung per Meta-Tag

Optional und **unabhängig von der API-Verbindung** (die läuft über OAuth). Nur nötig, wenn die Property per „HTML-Tag" bestätigt werden soll: Search Console → *Einstellungen → Inhaberbestätigung → HTML-Tag* liefert einen Tag der Form `<meta name="google-site-verification" content="CODE">`. In **SEO Toolkit → Einstellungen → SEO → „Google Search Console – Verification Code"** gehört nur der **CODE** (der `content`-Wert).

- **Domain-Property** (`sc-domain:…`) lässt sich nur per **DNS** bestätigen – das Feld bleibt **leer**.
- **Akzeptiert** wird der reine Code, das TXT-Format `google-site-verification=CODE` (auch mit Anführungszeichen) und ein ganz eingefügter `<meta …>`-Tag (der `content`-Wert wird übernommen, eine Info-Meldung weist darauf hin).
- **Abgelehnt** (nicht gespeichert, rote Meldung oben auf der Seite): URLs, Domains, `sc-domain:…`, Leer- oder Sonderzeichen, unplausible Längen und Meta-Tags eines anderen Dienstes (z. B. der Bing-Tag im Google-Feld). Ein vorheriger gültiger Wert bleibt dabei erhalten.
- **Ausgabe im `<head>`:** Der gespeicherte Wert wird bei jeder Ausgabe erneut geprüft (`MLT_SEO::verification_tags()`). Ein ungültiger Altwert – z. B. eine früher eingetragene Property-URL – erscheint deshalb **nie** als Meta-Tag; auf der Einstellungsseite steht dann ein Warnhinweis.
- Für **Bing** (`msvalidate.01`) gilt dasselbe.

> **Korrektur (v1.15.0):** Bis v1.14.x wurde der Feldinhalt ungeprüft ausgegeben. Eine
> eingetragene Property-URL erzeugte so `<meta name="google-site-verification" content="https://…">`,
> das nichts verifiziert.

### Verbindung trennen

GSC und GA4 sind unabhängig voneinander trennbar (GA4: `admin_post_mlt_ga4_disconnect`-Handler, löscht Tokens + Caches gezielt für GA4).

### GA4 Legacy-Fallback (Service Account)

Für Projekte, die noch mit dem älteren Verfahren laufen: Service-Account-JSON + Property-ID in den Options `mlt_ga4_service_account_json`/`mlt_ga4_property_id`. Greift automatisch, wenn keine OAuth-Verbindung aktiv ist (`MLT_GA4_Data_Adapter`-Konstruktor prüft OAuth zuerst, fällt sonst auf Service Account zurück). Für neue Projekte nicht mehr der vorgesehene Weg.

### Technische Details

```
GSC-Authentifizierung: OAuth2 Authorization Code Flow
GA4-Authentifizierung: OAuth2 Authorization Code Flow (geteilte Credentials mit GSC)
GA4-Token-Speicherung: wp_options, AES-256-CBC verschlüsselt (mlt_ga4_oauth_access_token etc.)
GSC-Cache:             WordPress Transients, 6 Stunden TTL
GA4-Cache:             WordPress Transients, 6 Stunden TTL
GSC-Verzögerung:       ~3 Tage (Zeiträume enden deshalb bei „heute − 2 Tage")
GSC-Datenspeicher:     ca. 16 Monate (begrenzt den Vergleichszeitraum)
```

**Fehlerbehandlung beim Abruf (seit v1.11.0):** `MLT_GSC_API::query_api()` und
`MLT_GA4_API::run_report()` unterscheiden zwischen

- **gültiger Antwort ohne Treffer** (z. B. keine Impressionen im Zeitraum) → wird 6 Stunden gecacht, und
- **Fehler** (kein/abgelaufener Token, Netzwerkfehler, HTTP-Fehler, ungültige Antwort) → liefert `null`, wird **nicht** gecacht; der nächste Aufruf fragt Google erneut.

Nach einem Netzwerk- oder Serverfehler (Timeout, 5xx, 429) stoppt das Plugin weitere Abrufe **innerhalb desselben Requests**, damit sich bei einer Google-Störung keine mehrfachen 15-Sekunden-Timeouts aufaddieren. Bei einem Fehler zeigt das Dashboard weiterhin Nullwerte (ohne Hinweis); dank fehlendem Caching erscheinen die richtigen Zahlen aber sofort wieder, sobald der Abruf klappt. Der Vergleichszeitraum blendet Deltas in so einem Fall aus.

> **Historisch (bis v1.10.x):** Fehlgeschlagene Abrufe wurden wie leere
> Ergebnisse 6 Stunden gecacht – Dashboard und Report zeigten dann bis zum
> Ablauf Nullwerte, obwohl die Verbindung längst wieder funktionierte.

---

## Bing Webmaster Tools

Seit v1.7.0. Einfacher Verifizierungs-Meta-Tag, kein OAuth.

1. [bing.com/webmasters](https://www.bing.com/webmasters) aufrufen, mit Microsoft-Konto anmelden
2. „Meine Website hinzufügen" → URL eintragen
3. Verifizierungsmethode „Meta-Tag" wählen, Wert aus dem `content`-Attribut kopieren
4. **SEO Toolkit → Einstellungen**, Karte „SEO" → Feld „Bing Webmaster Tools – Verification Code" eintragen (nur der `content`-Wert; ein ganzer Tag wird automatisch gekürzt, siehe [Verifizierung per Meta-Tag](#verifizierung-per-meta-tag))
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

Der [Vergleichszeitraum](#vergleichszeitraum) funktioniert mit jedem Adapter, der `get_overview()` für beliebige Zeiträume korrekt beantwortet – es ist keine Anpassung nötig.

---

## Wöchentlicher Report-Mailer

### Konfiguration

```
SEO Toolkit → Einstellungen, Karte „Wöchentlicher Report"

Empfänger:  beliebig viele (seit v1.6.0, vorher nur Admin-E-Mail als Fallback)
Versandtag: konfigurierbar (seit v1.6.0, vorher fix Montag)
Uhrzeit:    konfigurierbar (seit v1.6.0)

SEO Toolkit → Einstellungen, Karte „SEO"

Standard-Zeitraum:   bestimmt den Zeitraum des Reports (7 / 28 / 90 / 365 Tage)
Vergleichszeitraum:  Vorperiode / Vorjahr / kein Vergleich (seit v1.11.0)
```

### Report-Inhalt

HTML-Mail, Inline-CSS (`inc/class-report-template.php`). Abschnitte erscheinen nur, wenn Daten vorliegen:

| Abschnitt | Inhalt |
|---|---|
| Header | Sitename, KW/Jahr, „Zeitraum: *N* Tage (von – bis)", Zeile „Vergleich: …" (bei aktivem Vergleich) |
| Google Search Console | Kacheln Klicks, Impressionen, Ø CTR, Ø Position – jeweils mit Veränderung ▲/▼; darunter **Säulendiagramm „Klicks"** |
| Analytics | Kacheln Seitenaufrufe, Sessions, Nutzer – jeweils mit Veränderung; darunter **Säulendiagramm „Seitenaufrufe"** |
| Hinweis | kleiner Text zur Messtoleranz (nur bei angezeigtem Vergleich), siehe [Vergleichszeitraum](#vergleichszeitraum) |
| Top Keywords | die ersten 8 der abgerufenen 10, mit **Balken** (Anteil am besten Keyword, nach Klicks) |
| Top Seiten | die ersten 8 der abgerufenen 10, mit **Balken** (nach Klicks) |
| Traffic-Quellen | bis zu 5 Quellen nach Sessions, mit **Balken** und Prozentanteil an allen Sessions |
| Footer | Hinweis auf automatischen Versand, Link zum Deaktivieren |

Die Veränderung steht als kleine Zeile unter jeder Kachel (▲ grün = besser, ▼ rot = schlechter, ▬ grau = unverändert). Ist der Vergleich nicht möglich (siehe [Vergleichszeitraum](#vergleichszeitraum)), entfällt sie; liegt es an der 16-Monats-Grenze der Search Console, steht ein Hinweis im Header.

#### Grafiken in der Mail (seit v1.13.0)

E-Mail-Programme führen **kein JavaScript** aus, Gmail und Outlook stellen **kein Inline-SVG** dar und **blockieren externe Bilder** standardmäßig. Deshalb bestehen die Grafiken ausschließlich aus verschachtelten **HTML-Tabellen** mit festen Zellhöhen und Hintergrundfarben (`MLT_Chart::mail_columns()`, `mail_bar()`). Das funktioniert auch im Outlook-Desktop-Renderer. Es gibt keine Bilder, keine CSS-Klassen und keinen `<style>`-Block; alles ist inline.

- **Säulendiagramm:** aktueller Zeitraum farbig, Vergleichszeitraum grau daneben. Bis 31 Tage pro Tag, bis 120 Tage pro Woche, darüber je 4 Wochen. Die Blöcke werden vom Ende her gebildet; ein unvollständiger Rest am Anfang entfällt (die Legende nennt den ganzen Zeitraum, der erste Balken beginnt dann etwas später). Darunter stehen erstes/letztes Datum und der Spitzenwert.
- **Ohne Daten:** Liefert der Tageswerte-Abruf nichts (Ausfall, leerer Zeitraum), erscheint der Report ohne Säulendiagramm. Ohne Vergleich entfallen nur die grauen Balken.
- **Größe:** Gmail kürzt Mails ab ca. 102 KB mit „Nachricht abgeschnitten". Typisch sind ca. 50 KB (28 Tage mit Vergleich). Überschreitet das HTML **80 KB** (z. B. durch sehr lange Keywords), wird der Report **ohne Säulendiagramme** gesendet. Das Limit lässt sich per Filter `mlt_report_max_html_bytes` ändern.
- **Mail-Programme:** Ausführlich geprüft ist das HTML im Browser; die Darstellung in Outlook (Desktop), Gmail und Apple Mail sollte pro Rollout kurz mit dem Test-Report kontrolliert werden. Dunkelmodus kann Farben der Balken verändern.
- **Kein PNG-Chart:** Bewusst nicht umgesetzt. Ein Bild setzt die GD-Erweiterung auf jedem Host voraus, muss als Anhang (CID) eingebettet werden und wird von vielen Clients standardmäßig nicht angezeigt oder als Anhang geführt.

**Betreff-Format:** `[Sitename] SEO Report KW {Woche}/{Jahr}` (aus `class-report-mailer.php`, per Filter `mlt_weekly_report_subject` anpassbar).

**Filter `mlt_weekly_report_html`:** Der Parameter `$data` enthält `range`, `gsc_overview`, `gsc_queries`, `gsc_pages`, `analytics`, `analytics_sources`, seit v1.11.0 `compare` und seit v1.13.0 `charts` (Tageswerte) (`range`, `gsc_prev`, `analytics_prev`, `gsc_deltas`, `analytics_deltas`, `notice`, `note`).

### Wichtig: nur ein Handler auf dem Cron-Hook

`mlt_weekly_report` darf **ausschließlich** von `MLT_Report_Mailer::send()` behandelt werden. Ein früherer, inzwischen entfernter Legacy-Handler in `class-settings.php` führte zu doppelt versendeten Reports (siehe [Troubleshooting](#troubleshooting) und Plugin-CHANGELOG 1.9.0).

### Cron-Planung

Der Report läuft über den WP-Cron-Event `mlt_weekly_report` (Konstante `MLT_REPORT_CRON_HOOK`, geplant in `inc/report-schedule.php`, verarbeitet von `MLT_Report_Mailer::send()`). Die Planung folgt dem Schalter „Wöchentlichen Report aktivieren": Ist er an und kein Termin geplant, wird beim nächsten Seitenaufruf automatisch geplant; ist er aus, werden vorhandene Termine entfernt. Änderungen an Wochentag, Uhrzeit oder Zeitzone planen neu. Die Einstellungsseite zeigt den nächsten Termin („Nächster geplanter Versand").

> **Korrektur (v1.12.0):** Bis v1.11.x wurde der Event unter dem Namen `mlt_send_weekly_report` geplant, den kein Handler verarbeitete. Neu aktivierte Reports wurden dadurch nie automatisch versendet, und die Einstellungsseite zeigte „Nächster geplanter Versand: —". Der frühere Name wird beim Planen automatisch aufgeräumt.

WP-Cron läuft nur bei Seitenaufrufen. Bei Sites mit wenig Traffic oder deaktiviertem WP-Cron (`DISABLE_WP_CRON`) sollte ein echter Server-Cron `wp-cron.php` regelmäßig aufrufen, sonst verschiebt sich der Versand.

### Test-Mail senden

Über die Einstellungsseite, Karte „Wöchentlicher Report" (Button **„Test-Report senden"**, seit v1.12.0). Der Button sendet den **echten Report mit aktuellen Zahlen** (Betreff `[TEST] [Sitename] SEO Report KW …`) nur an die **erste ausgefüllte Adresse** der Empfänger-Liste (auch ungespeichert) – nicht an alle Empfänger. Er ändert den Versandstatus (`mlt_last_report_sent`/`_status`) nicht und prüft nebenbei den SMTP-Versand. Ist weder Search Console noch Analytics verbunden, erscheint ein Hinweis und der Report enthält nur den Rahmen. Den Versand an *alle* Empfänger löst `wp cron event run mlt_weekly_report` aus.

> **Hinweis:** Bis v1.11.x sendete der Button nur eine kurze SMTP-Test-Mail ohne Zahlen – und tat wegen eines JavaScript-Fehlers (Feld `#mlt_report_email` existiert seit v1.6.0 nicht mehr) in der Praxis gar nichts.

### WP-CLI

```bash
wp cron event run mlt_weekly_report   # Report sofort auslösen (geht an die konfigurierten Empfänger!)
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

Drei Wege (`inc/class-breadcrumbs.php`), alle geben denselben Breadcrumb-HTML-Block mit Microdata aus (erst ab 2 Ebenen):

```php
// 1. Template-Tag (gibt direkt aus)
if ( function_exists( 'mlt_breadcrumbs' ) ) {
    mlt_breadcrumbs( ' › ', 'breadcrumbs' );   // ( $separator, $class )
}

// 2. Als String (z. B. für eigenes Markup)
echo MLT_Breadcrumbs::render( ' › ', 'breadcrumbs' );
```

```
// 3. Shortcode im Editor
[mlt_breadcrumbs separator="›" class="breadcrumbs"]
```

Die Einträge lassen sich per Filter `mlt_breadcrumb_items` anpassen (Array aus `['name' => …, 'url' => …]`). Dieselben Einträge speisen die `BreadcrumbList` im Schema-Graph.

> **Korrektur (v1.11.0):** Frühere Stände nannten hier die Funktion
> `medialab_seo_breadcrumbs()` mit einem Options-Array (`separator`,
> `home_title`, `wrapper_class`). Diese Funktion existiert im Plugin nicht.
> Die tatsächliche Template-Funktion heißt `mlt_breadcrumbs( $separator, $class )`.

---

## Weiterleitungen

**SEO Toolkit → Weiterleitungen** (eigener Untermenüpunkt, Slug `mlt-redirects`)

- 301 (permanent) und 302 (temporär)
- Wildcard-Pfade unterstützt
- Import/Export als CSV
- 404-Log, aus dem sich Weiterleitungen direkt anlegen lassen

> **Korrektur (v1.11.0):** Frühere Stände nannten den Pfad „Einstellungen →
> Redirects". Die Weiterleitungen haben einen eigenen Menüpunkt.

---

## Consent-Rate-Tracking

Seit v1.9.0 (`inc/class-consent-stats.php`). DSGVO-Auswertung, wie viele
Besucher Analytics-Consent geben - liest **read-only** aus der
Agency-Core-Tabelle `wp_mlt_consent_log`, kein eigener Schreibzugriff
und kein eigener Tracking-Code in diesem Modul. Erscheint als eigene
Card im SEO-Dashboard mit Umschalter „Letzte 30 Tage" / „Woche vs.
Vorwoche".

---

## llms.txt

Gibt unter `<Startseite>/llms.txt` eine automatisch generierte, kuratierte
Markdown-Übersicht der Website aus (`inc/class-llms-txt.php`). Format nach dem
Community-Vorschlag von [llmstxt.org](https://llmstxt.org) (Jeremy Howard,
Answer.AI, 2024) – **kein offizieller Web-Standard**. Läuft über den normalen
WordPress-Frontcontroller, keine eigene Rewrite-Rule nötig, funktioniert auch
im `/cms`-Subdirectory-Setup.

> **Hinweis (v1.11.0):** In v1.10.2 wurde die Klasse zwar angelegt und der
> Schalter unter **SEO Toolkit → Schema** ergänzt, aber nicht in
> `media-lab-seo.php` geladen – `/llms.txt` wurde dadurch nie ausgeliefert.
> Seit v1.11.0 ist die Klasse eingebunden und die Ausgabe **standardmäßig
> aktiv** (Schalter „Aktiviert" = an). Auf Bestandsprojekten ist die Datei
> nach dem Update also sofort erreichbar; wer das nicht möchte, schaltet sie
> vorher ab oder setzt `mlt_llms_txt_enabled` auf `false`.

> **Einordnung (Stand September 2026):** Google und OpenAI haben offizielle
> Unterstützung nicht bestätigt (Google: Gary Illyes, Juli 2025, „unterstützen
> wir nicht"; John Mueller verglich es mit dem längst wirkungslosen
> „keywords"-Meta-Tag). Eine Ahrefs-Auswertung über 137.000 Domains (Juni 2026)
> fand, dass 97 % aller `llms.txt`-Dateien null Anfragen erhalten; ClaudeBot
> machte nur 0,8 % der wenigen tatsächlichen Zugriffe aus. Der Aufwand für die
> automatisierte Ausgabe ist gering, ein belegter Nutzen aktuell aber auch.

### Was wird ausgegeben

Aus veröffentlichten Inhalten, automatisch, ohne redaktionelle Kuratierung:

| Abschnitt | Quelle |
|---|---|
| H1 + Blockquote | Website-Titel + -Untertitel (**Einstellungen → Allgemein**) |
| `## Seiten` | alle veröffentlichten Seiten |
| `## Leistungen` | CPT `service`, nur wenn das Post-Type existiert |
| `## Blog` | die neuesten Beiträge (Standard 20, einstellbar) |

Je Eintrag: `- [Titel](URL): Beschreibung` – Beschreibung aus dem Excerpt oder
den ersten 20 Wörtern des Inhalts, Shortcodes und HTML entfernt. WooCommerce-
Systemseiten (Warenkorb, Kasse, Mein Konto, Shop, AGB) werden automatisch
ausgeblendet.

Schaltet sich automatisch ab, wenn **Einstellungen → Lesen → „Sichtbarkeit für
Suchmaschinen blockieren"** aktiv ist – wie `robots.txt`.

### Einstellungen

**SEO Toolkit → Schema** (eigener Abschnitt unterhalb der Organisations-Felder):

| Feld | Standard | Beschreibung |
|---|---|---|
| Aktiviert | an | `/llms.txt`-Ausgabe komplett an/aus |
| Blogbeiträge | 20 | Anzahl im Abschnitt „Blog"; `0` blendet den Abschnitt aus |

### Erweiterung per Filter

| Filter | Parameter | Zweck |
|---|---|---|
| `mlt_llms_txt_enabled` | `$enabled` | Ausgabe an/aus überschreiben (z. B. projektspezifisch immer aus) |
| `mlt_llms_txt_description` | `$text` | Ein-Satz-Beschreibung unter dem H1 anpassen |
| `mlt_llms_txt_post_limit` | `$limit` | Anzahl Blogbeiträge überschreiben |
| `mlt_llms_txt_sections` | `$sections` | Abschnitte ergänzen/überschreiben (Titel => Zeilen-Array) |
| `mlt_llms_txt_content` | `$content` | fertigen Markdown-Text vor der Ausgabe nachbearbeiten |

```php
// Eigenen Abschnitt ergänzen
add_filter( 'mlt_llms_txt_sections', function ( $sections ) {
    $sections['Über uns'] = [
        '- [Team](https://example.at/team/): Wer bei uns arbeitet.',
    ];
    return $sections;
} );
```

### Bekannte Grenzen

- Keine redaktionelle Kuratierungs-UI: keine Möglichkeit, einzelne Seiten im
  Backend gezielt ein-/auszuschließen oder abweichende Beschreibungen zu
  pflegen, außer über die Filter oben.
- Produkte (WooCommerce) werden nicht aufgenommen – bei größeren Katalogen
  würde das den Umfang sprengen; bei Bedarf über `mlt_llms_txt_sections`
  ergänzbar.

---

## Troubleshooting

### Dashboard zeigt keine Daten

1. Verbindung prüfen: `SEO Toolkit → Dashboard` – zeigt es „Mit Google verbinden"?
2. Property-URL exakt prüfen (mit trailing slash, z.B. `https://example.at/`)
3. GSC-Verzögerung: Neue Websites haben ~3 Tage Verzögerung
4. Cache leeren: Button **„🔄 Daten aktualisieren"** im Dashboard

### Dashboard oder Report zeigen plötzlich überall 0

Seit v1.11.0 werden fehlgeschlagene Abrufe nicht mehr gecacht (siehe [Technische Details](#technische-details)); Nullwerte bedeuten daher meist, dass der Abruf **gerade jetzt** fehlschlägt – oder, bei Plugin-Versionen bis 1.10.x, dass ein früherer Fehler noch bis zu 6 Stunden im Cache steckt.
1. Verbindung prüfen / ggf. neu mit Google verbinden („Mit Google verbinden" im Dashboard)
2. Im Dashboard **„🔄 Daten aktualisieren"** klicken
3. Bei GA4: API-Fehler meldet das Plugin über die Action `medialab_log_event` (Event `ga4_api_error`); der Handler dafür liegt außerhalb dieses Plugins

### Verlaufs-Chart fehlt oder zeigt nur eine Linie

- **Karte „Verlauf" fehlt komplett:** Weder Search Console noch ein Analytics-Adapter mit Zeitreihen liefert Daten (nicht verbunden, Abruf fehlgeschlagen oder im Zeitraum keine Werte). Zuerst die Kacheln prüfen – zeigen sie 0, liegt es an der Verbindung (siehe „Dashboard oder Report zeigen plötzlich überall 0").
- **Reiter „Seitenaufrufe"/„Sessions" fehlen:** Analytics nicht verbunden, oder ein eigener Adapter implementiert `MLT_Analytics_Timeseries_Interface` nicht.
- **Keine gestrichelte Linie:** „Kein Vergleich" eingestellt, Vergleichszeitraum ohne Daten oder jenseits der 16-Monats-Grenze der Search Console (bei GSC-Charts).
- **Karten auf der Dashboard-Seite unformatiert / Consent-Balken leer:** Plugin vor v1.12.0 (Styles fehlten bzw. fehlendes `display:block`). Nach dem Update ggf. Browser-Cache leeren.

### Kein Vergleich / keine Veränderungswerte sichtbar

- Einstellung **SEO Toolkit → Einstellungen → „Vergleichszeitraum"** auf „Kein Vergleich"?
- Hinweis zur 16-Monats-Grenze? Der Vergleichszeitraum liegt weiter zurück, als die Search Console Daten speichert (typisch bei „365 Tage"). Kürzeren Zeitraum wählen oder „Vorperiode" statt „Vorjahr".
- Hat der aktuelle oder der Vergleichszeitraum keine Daten (junge Property, fehlgeschlagener Abruf)? Dann werden bewusst keine Deltas gezeigt – siehe vorheriger Punkt und [Vergleichszeitraum](#vergleichszeitraum).
- Analytics-Kacheln ohne Veränderung: Nur mit verbundenem GA4/Matomo und Daten im Vergleichszeitraum.

### Report-Mail kommt nicht an

```bash
wp eval "wp_mail('test@example.at', 'Test', 'Test');"
wp cron event list | grep mlt
wp option get mlt_last_report_status
```

- Steht unter „Wöchentlicher Report" **„Nächster geplanter Versand: —"** trotz aktivem Schalter, ist kein Termin geplant. Ab v1.12.0 plant sich der Event beim nächsten Seitenaufruf selbst; bis v1.11.x fehlte der Termin, weil unter einem falschen Hook-Namen geplant wurde (siehe [Cron-Planung](#cron-planung)).
- `wp cron event list | grep mlt` muss `mlt_weekly_report` zeigen. Taucht dort `mlt_send_weekly_report` auf, läuft noch eine Version vor 1.12.0.
- **Test-Report-Button reagiert nicht** (kein „Sende …", keine Meldung): Plugin vor v1.12.0 – der Button las ein nicht mehr vorhandenes Feld.
- Test-Report kommt an, Wochen-Report nicht: Cron-Planung prüfen (s. o.), Empfänger-Liste gespeichert?, WP-Cron aktiv?
- Mail wird gesendet, kommt aber nicht an: Spam-Ordner, SPF/DKIM der Absenderdomain, SMTP-Zugangsdaten (Agency Core → E-Mail / SMTP). „SMTP aktiv" in der Einstellungskarte bedeutet nur, dass SMTP konfiguriert ist, nicht dass die Zugangsdaten stimmen.

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
- GA4-Redirect-URI (`&mlt_ga4_callback=1`) zusätzlich zur GSC-URI (`&mlt_gsc_callback=1`) eingetragen?
- Property-ID korrekt (numerisch, nicht `G-XXXXXXXX`)?
- Falls Legacy-Service-Account genutzt wird: JSON-Key vollständig (inkl. `private_key`)? Service-Account-E-Mail in GA4 als Betrachter hinzugefügt?

### Matomo: „Site nicht gefunden"

- Site-ID korrekt (Zahl aus Matomo → Websites)?
- API-Token hat Lesezugriff auf diese Site?

### Verification-Code wird nicht gespeichert / Meta-Tag fehlt

- **Rote Meldung „… nicht gespeichert":** Das Feld enthielt eine URL/Domain, ungültige Zeichen oder den Tag eines anderen Dienstes. Nur den **Code** (`content`-Wert) eintragen – bei einer Domain-Property (DNS-Bestätigung) das Feld leer lassen. Siehe [Verifizierung per Meta-Tag](#verifizierung-per-meta-tag).
- **Gelber Hinweis „Der gespeicherte Wert ist kein gültiger Verifizierungscode":** Ein Altwert (z. B. eine Property-URL) steckt noch in der Datenbank. Er wird nicht ausgegeben und kann geleert werden.
- **Prüfen, was die Seite ausgibt:** `curl -sL https://example.at/ | grep -io '<meta name="\(google-site-verification\|msvalidate.01\)"[^>]*>' || echo "kein Tag"`. Zeigt ein Cache noch den alten Tag: Cache leeren.

### Menüpunkt nicht sichtbar

```bash
wp plugin deactivate media-lab-seo && wp plugin activate media-lab-seo
```

### /llms.txt liefert 404

- Plugin auf v1.11.0 oder neuer? In v1.10.2 wurde die Datei nicht ausgeliefert (siehe [llms.txt](#llmstxt)).
- Schalter **SEO Toolkit → Schema → „llms.txt"** aktiv? Und **Einstellungen → Lesen → „Sichtbarkeit für Suchmaschinen blockieren"** ausgeschaltet?
- Filter `mlt_llms_txt_enabled` im Projekt-Code gesetzt?

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
