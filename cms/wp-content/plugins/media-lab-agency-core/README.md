# Media Lab Agency Core

Core functionality plugin for Media Lab agency websites.

## Features

- **Shortcodes**: Hero Slider, Accordion, Stats, Testimonials, etc.
- **Suche**: Ajax-Live-Suche mit Treffer-Highlighting, Kontext-Ausschnitt und WooCommerce-Attribut-/Konfigurator-Suche, optionales Such-Icon in der Hauptnavigation (siehe unten)
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

Live-Suche mit Ajax-Ergebnissen (`inc/ajax-search.php`), Frontend-Komponente im Theme (`.ajax-search`, siehe `assets/src/scss/components/_ajax-search.scss`).

**Such-Icon in der Navigation** (optional, Standard: an):
Agency Core → Logo / Globale Einstellungen → UI-Features → "Suche in Navigation". Zeigt ein Icon im Hauptmenü (Desktop + Mobile), das ein Such-Overlay mit derselben `.ajax-search`-Komponente öffnet (`inc/nav-search-icon.php`).

**Was die Suche findet:**
- Titel, Excerpt und Content (WordPress-Standard-Suche, `WP_Query` mit `s`)
- WooCommerce-Produktattribute: sowohl globale (`pa_*`-Taxonomien) als auch lokale/benutzerdefinierte Attribute
- Konfigurator-Optionen konfigurierbarer Produkte (`config_steps` → `options`, aus media-lab-woocommerce)

Attribut-/Konfigurator-Treffer, bei denen der Suchbegriff nicht im Beschreibungstext steht, zeigen "Attribut: Wert" statt eines Text-Ausschnitts (z. B. "Farbe: Rot").

**Ergebnis-Darstellung:**
- Treffer werden per `<mark>` in Titel und Excerpt hervorgehoben
- Excerpt zeigt einen Ausschnitt um die tatsächliche Fundstelle im Content, nicht immer nur den Textanfang

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