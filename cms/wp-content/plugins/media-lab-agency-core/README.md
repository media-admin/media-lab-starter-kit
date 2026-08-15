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

### 1.22.0
- Suche: Suchbegriff wird jetzt in Titel und Excerpt der Ergebnisse per `<mark>` hervorgehoben.
- Suche: Excerpt zeigt jetzt einen Ausschnitt um die tatsächliche Fundstelle im Content, statt immer nur den Textanfang.
- Suche: findet jetzt auch Produkte über WooCommerce-Attribute (globale `pa_*`-Taxonomien und lokale/benutzerdefinierte Attribute) sowie über Konfigurator-Optionen (`config_steps` → `options`). Reine Attribut-/Options-Treffer zeigen "Attribut: Wert" statt eines Content-Ausschnitts.
- Fix: `post_type`-Filter griff bei mehreren durchsuchten Post-Types nicht - `wp_magic_quotes()` (WP-Kernverhalten) hatte den `$_POST`-Wert vor `json_decode()` escaped, der Fallback parste den kaputten String nicht sauber. `wp_unslash()` vor `json_decode()` ergänzt.

### 1.21.0
- Neue Einstellung "Suche in Navigation" (Logo / Globale Einstellungen → UI-Features, Standard: an). Zeigt ein Such-Icon im Hauptmenü, das ein Such-Overlay mit der bestehenden Ajax-Search-Komponente öffnet.

### 1.20.1
- WCAG-2.2.2-Fokus-Pause im Logo-Slider ergänzt (`ml-logo-slider.js`, Theme-seitig): Autoplay pausiert automatisch bei Tastatur-/Screenreader-Fokus auf ein Element innerhalb des Sliders. Die entfernte `assets/js/block-logo-slider.js` enthielt dieses Verhalten, die Theme-seitige Neuimplementierung hatte es nie übernommen (war laut damaligem Code-Kommentar durch einen Lade-Bug ohnehin nie aktiv, also keine neue Regression, aber bis jetzt ein fehlendes Feature)
- Veraltete Kommentarverweise in `assets/css/block-slider.css` auf die gelöschte `assets/js/block-slider.js` korrigiert - verweisen jetzt auf `ml-slider.js` (Theme). Rein kosmetisch, keine Funktionsänderung

### 1.20.0
- Heartbeat Monitoring hinzugefügt (Push-basiertes Uptime-Monitoring, Better Stack / Healthchecks.io kompatibel)
- Leere, ungenutzte `inc/security.php` entfernt

### 1.18.0
- WP All Import: Timeout-Fix (`pmxi_image_download_timeout`) und `custom_file_download()`-Helper gegen UA-basiertes Blocking ergänzt. Backport aus dem Janecka-Projekt.

## Version

1.22.0
