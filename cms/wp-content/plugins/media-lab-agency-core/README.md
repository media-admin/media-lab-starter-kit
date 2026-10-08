# Media Lab Agency Core

Core functionality plugin for Media Lab agency websites.

## Features

- **Shortcodes**: Hero Slider, Accordion, Stats, Testimonials, etc.
- **Suche**: Ajax-Live-Suche mit Treffer-Highlighting, Kontext-Ausschnitt und WooCommerce-Attribut-/Konfigurator-Suche, optionales Such-Icon in der Hauptnavigation, zentral konfigurierbar (Texte mehrsprachig, Limit, Inhaltstypen, Anzeige) unter Agency Core → Suche / Live-Suche (siehe unten)
- **Heartbeat Monitoring**: Push-basierte Uptime-Überwachung (Better Stack / Healthchecks.io)
- **Admin**: Dashboard customizations
- **Helpers**: Utility functions for theme development
- **WP All Import Integration**: Timeout- und User-Agent-Blocking-Fixes für Bilder-Downloads (siehe Hinweis unten)

## Requirements

- WordPress 6.0+
- PHP 8.0+

## Dependencies

Keine Composer-Abhängigkeiten — kein `composer.json`/`composer.lock`
vorhanden. Alle Funktionalität läuft über WordPress-Core-APIs
(`wp_remote_*`, ACF-Hooks) ohne externe PHP-Bibliotheken. (Ausnahme im
Starter Kit: `media-lab-backup`, das phpseclib3 für SSH-Key-Auth benötigt.)

## Installation

1. Upload to `/wp-content/plugins/media-lab-agency-core/`
2. Activate through WordPress admin
3. Use shortcodes in your content

## Suche (Ajax Search)

Live-Suche mit Ajax-Ergebnissen. Aufbau:

| Datei | Aufgabe |
|---|---|
| `inc/search-settings.php` | Zentrale Einstellungen (ACF-Seite `agency-core-search`), Sprach-Auflösung, Whitelist/Limit-Deckel, `data-config` für das Frontend |
| `inc/ajax-search.php` | AJAX-Handler `agency_search` (Rate-Limit, Nonce, WP_Query, Attribut-Suche) |
| `inc/nav-search-icon.php` | Such-Icon in der Hauptnavigation + Such-Overlay |
| `inc/shortcodes.php` | Shortcode `[ajax_search]` |
| `inc/search/class-synonym-dictionary.php` | Synonym-Wörterbuch (Feldgruppe „Suche – Synonyme“ auf derselben Einstellungsseite) |
| `inc/search/class-serp-search.php` | Erweiterte Suche (Synonyme, Attribute, Fuzzy) auf der Ergebnisseite |
| Theme: `search.php`, `template-parts/search/result-card.php` | Suchergebnisseite, baut auf den Archiv-Bausteinen und `.post-card` auf |
| Theme: `assets/src/js/components/ajax-search.js`, `assets/src/scss/components/_ajax-search.scss` | Frontend-Komponente `.ajax-search` |

### Einstellungen

**Agency Core → Suche / Live-Suche** (`wp-admin/admin.php?page=agency-core-search`). Die Einstellungen gelten für **jedes** Suchfeld (Shortcode und Nav-Overlay). Die Seite hat fünf Tabs (Allgemein, Verhalten, Anzeige, Ergebnisseite, Texte):

**Allgemein**
- **Suche in Navigation** (`search_enabled`, Standard: an) – zeigt ein Icon im Hauptmenü (Desktop + Mobile), das ein Such-Overlay öffnet. Betrifft nur das Nav-Icon, der Shortcode funktioniert unabhängig davon.

**Verhalten**

| Einstellung | Standard | Hinweis |
|---|---|---|
| Durchsuchte Inhaltstypen | Beiträge, Seiten | Nur öffentlich durchsuchbare Typen (`public` + nicht `exclude_from_search`), keine Mediathek |
| Anzahl Ergebnisse | 5 | 1–20, serverseitig erzwungen |
| Mindestzeichen | 2 | 2–5 |
| Verzögerung | 300 ms | 100–1000 ms nach dem letzten Tastenanschlag |
| Textausschnitt | 10 Wörter | Wörter vor/nach der Fundstelle |
| WooCommerce-Attribute durchsuchen | an | Globale + lokale Attribute und Konfigurator-Optionen |
| Treffer hervorheben | an | `<mark>` in Titel und Ausschnitt |

**Anzeige** (je an/aus): Vorschaubild, Inhaltstyp-Label, Datum, Textausschnitt, Preis (WooCommerce) – Standard jeweils an; „Link zu allen Ergebnissen“ – Standard aus.

**Texte**
- Platzhalter, **Startertext**, Hinweis „zu wenige Zeichen“ (`{min}` = Mindestzeichen), „Keine Ergebnisse“, Fehlertext, Link-Text „Alle Ergebnisse“, Screenreader-Labels (Suche öffnen / Suchen-Button) und Bezeichnungen der Inhaltstypen (eine Zeile pro Typ: `slug=Bezeichnung`).
- **Startertext:** erscheint unter dem Suchfeld, sobald es fokussiert wird und leer ist (z. B. „Wonach suchen Sie?“). Leer = kein Startertext. Reiner Text, kein HTML.
- **Mehrsprachigkeit:** Toggle „Mehrsprachigkeit aktivieren“ + Repeater `search_languages` (Sprachcode + alle Texte). Spracherkennung: Polylang → WPML → WP-Locale. Die **erste Zeile** ist der Fallback, wenn keine Sprache passt. Bei deaktivierter Mehrsprachigkeit gelten die Standardtexte. Leere Pflichttexte (Platzhalter, „Keine Ergebnisse“, Fehlertext, Link-Text, Labels) werden durch die eingebauten deutschen Standardtexte ersetzt; Startertext und Hinweis bleiben leer = werden nicht angezeigt.

> **Standardwerte entsprechen dem bisherigen Verhalten.** Ausnahme Nav-Overlay: Dort waren früher Platzhalter „Wonach suchst du?“, 6 Treffer und die Typen Beiträge/Seiten/Produkte fest verdrahtet – jetzt gelten auch hier die globalen Einstellungen.

### Suchergebnisseite

Die Seite mit allen Ergebnissen (`/?s=…`, `search.php` im Theme) ist aus denselben Bausteinen aufgebaut wie das Archiv (`.archive-header`, `.post-grid`, `.post-card`, `.archive-pagination`, `.archive-empty`) – es gibt kein eigenes Such-Design mehr. Ändert sich das Archiv-Design, zieht die Suche mit. Steuerung im Tab **Ergebnisseite**:

| Einstellung | Standard | Hinweis |
|---|---|---|
| Layout | Raster | Raster (2/3/4 Spalten) oder Liste (horizontale Karten) |
| Ergebnisse pro Seite | 0 | 0 = WordPress-Standard (Einstellungen → Lesen) |
| Standard-Sortierung | Relevanz | Relevanz, Neueste/Älteste zuerst, Titel A–Z/Z–A |
| Inhaltstypen zuerst | leer | Slugs kommagetrennt, z. B. `product, page, post`; innerhalb eines Typs gilt die gewählte Sortierung |
| Sortier-Auswahl für Besucher | aus | Dropdown über den Ergebnissen (`?sort=…`), angebotene Sortierungen wählbar |
| Erweiterte Suche | an | Synonyme, Produktattribute/Konfigurator-Optionen und Tippfehler-Toleranz für Produktcodes wie in der Live-Suche |

Karten-Inhalt (Vorschaubild, Typ, Datum, Ausschnitt, Preis) und Hervorhebung kommen aus den Tabs „Anzeige“ und „Verhalten“ – dieselben Schalter wie bei der Live-Suche. Überschriften, Anzahl-Texte, Sortier-Beschriftungen und Leer-Zustand sind im Tab „Texte“ mehrsprachig pflegbar.

**Eigene Karte pro Inhaltstyp:** Datei `template-parts/search/card-{post_type}.php` im Theme anlegen (z. B. `card-product.php`), sie ersetzt für diesen Typ die Standardkarte.

**Erweiterte Suche (seit 1.31.0):** Die Haupt-Abfrage von WordPress bleibt unverändert (Sortierung, Pagination, Sprache, Zähler). Zusätzlich werden die Treffer der Live-Suche-Bausteine (Synonym-Erweiterung, Attribut-/Konfigurator-Suche, Fuzzy-Fallback) per `OR ID IN (…)` an die Such-Bedingung gehängt (`inc/search/class-serp-search.php`). Treffer, die nur über ein Attribut gefunden wurden, zeigen „Attribut: Wert“ als Ausschnitt.

### Synonyme

Auf derselben Seite, unterhalb der Tabs: Feldgruppe **Suche – Synonyme** (seit 1.27.0, `inc/search/class-synonym-dictionary.php`). Pro Sprache Gruppen äquivalenter Begriffe, kommagetrennt (z. B. „Sticker, Aufkleber“); sucht jemand einen Begriff einer Gruppe, werden alle Begriffe der Gruppe mitgesucht. Die Seite „Suche / Live-Suche“ ersetzt die frühere eigene Seite „Suche / Synonyme“ (gleicher Slug `agency-core-search`, gespeicherte Synonyme bleiben erhalten).

### Shortcode

```
[ajax_search]
[ajax_search limit="10" post_types="post,page,product" placeholder="Produkt suchen" search_page="/suche/"]
```

Alle Attribute sind optional und überschreiben die globalen Einstellungen pro Suchfeld. Das Frontend bekommt die Konfiguration (Texte, Limit, Typen, Mindestzeichen, Verzögerung, Anzeige-Optionen, Sprache) als `data-config` (JSON) am `.ajax-search`-Container; ohne `data-config` (Altmarkup) greifen die Defaults in `ajax-search.js`.

### Was die Suche findet

- Titel, Excerpt und Content (WordPress-Standard-Suche, `WP_Query` mit `s`)
- WooCommerce-Produktattribute: sowohl globale (`pa_*`-Taxonomien) als auch lokale/benutzerdefinierte Attribute
- Konfigurator-Optionen konfigurierbarer Produkte (`config_steps` → `options`, aus media-lab-woocommerce)

Attribut-/Konfigurator-Treffer, bei denen der Suchbegriff nicht im Beschreibungstext steht, zeigen "Attribut: Wert" statt eines Text-Ausschnitts (z. B. "Farbe: Rot").

### Ergebnis-Darstellung

- Treffer werden per `<mark>` in Titel und Excerpt hervorgehoben (abschaltbar)
- Excerpt zeigt einen Ausschnitt um die tatsächliche Fundstelle im Content, nicht immer nur den Textanfang
- Veraltete Antworten bei schnellem Tippen werden verworfen; Fehler (z. B. Rate-Limit) zeigen den Fehlertext statt „Keine Ergebnisse“

### Sicherheit & Mehrsprachigkeit (serverseitig)

- **Whitelist:** Die vom Browser gesendeten Inhaltstypen werden gegen die durchsuchbaren Post-Types geprüft; ein CPT mit `exclude_from_search => true` ist per Live-Suche nicht findbar.
- **Limit-Deckel:** maximal 20 Treffer pro Anfrage, unabhängig vom gesendeten Wert.
- **Sprachfilter:** `admin-ajax.php` gilt für Polylang/WPML als Admin-Kontext, ohne Sprache kämen Treffer aller Sprachen zurück. Das Frontend sendet deshalb die Seitensprache mit; bei Polylang wird `WP_Query` per `lang` gefiltert (inkl. der Attribut-Treffer), bei WPML wird die Sprache per `wpml_switch_language` gesetzt.
- Bestehende Schutzmaßnahmen (Nonce, Rate-Limit 20 Anfragen/60 s pro IP) bleiben unverändert.

### Erweiterungs-Hooks

| Filter | Zweck |
|---|---|
| `media_lab_ajax_search_query_expansion` | Zusätzliche Suchbegriffe (z. B. Synonyme, Fuzzy-Varianten) |
| `media_lab_ajax_search_extra_matches` | Zusätzliche Produkt-Treffer, wenn Content- und Attribut-Suche nichts finden |
| `media_lab_ajax_search_result` | Ergebnis-Daten pro Treffer erweitern (z. B. Preis durch WooCommerce) |

## Heartbeat Monitoring

Push-basiertes Monitoring statt klassischer Pull-Uptime-Checks. Konfiguration unter Agency Core → Heartbeat Monitoring:

1. Heartbeat bei Better Stack oder Healthchecks.io anlegen, Ping-URL kopieren
2. In Agency Core → Heartbeat Monitoring aktivieren, Ping-URL eintragen, speichern
3. Den dort angezeigten REST-Endpoint (inkl. Token) per Server-Cronjob alle 5–10 Min aufrufen lassen (`curl` oder `wget`)

Empfohlen: zentraler Dispatcher-Cronjob (ein Script, das mehrere Client-Sites nacheinander pingt) statt Einzel-Cronjob pro Site — siehe `scripts/heartbeat-runner.php` (Template, echte Tokens in lokaler `scripts/heartbeat-runner.config.php`, siehe `.example`-Datei).

## WP All Import – Bilder-Download-Fixes

Bei aktivem WP All Import (`PMXI_VERSION` definiert) stellt das Plugin automatisch bereit:

- **Timeout-Fix**: Erhöht den Bilder-Download-Timeout auf 30s (Filter `pmxi_image_download_timeout`, überschreibbar via `mlac_wpai_image_timeout_seconds`).
- **`custom_file_download()`**: Umgeht User-Agent-basiertes Blocking durch CDNs/WAFs, die den erkennbaren WP-All-Import-UA stillschweigend droppen (Symptom: `cURL error 28`, TCP/TLS erfolgreich, 0 bytes empfangen).

**`custom_file_download()` erfordert manuelle Einrichtung pro Projekt/Import**, da sie nicht automatisch greift:

1. Bild-Feld im Import-Template auf `[custom_file_download({Bildfeld}, "png")]` umstellen (statt reiner URL-Zuordnung)
2. In den Image Options die Checkbox "Use images currently uploaded in wp-content/uploads/wpallimport/files/" aktivieren

Nur bei Bedarf einsetzen (Verdacht auf UA-Blocking, z. B. wenn der Timeout-Fix allein nicht reicht).

## Changelog

Siehe [CHANGELOG.md](./CHANGELOG.md) für die vollständige Versionshistorie.
Aktuelle Version: siehe `Version:`-Header in `media-lab-agency-core.php`.
