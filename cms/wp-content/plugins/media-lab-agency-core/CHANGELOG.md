# Changelog — Media Lab Agency Core

Alle wesentlichen Änderungen werden in dieser Datei dokumentiert.
Format: [Keep a Changelog](https://keepachangelog.com/de/1.0.0/)
Versionierung: [Semantic Versioning](https://semver.org/)

**Hinweis zur Vollständigkeit:** Diese Datei wurde am 21.08.2026 aus dem
bisherigen Inline-Changelog der README.md, dem monorepo-weiten
`CHANGELOG.md` (root) sowie einer gezielten `git log`-Auswertung zwischen
den vorhandenen Plugin-Tags (`media-lab-agency-core-v*`) rekonstruiert —
`media-lab-agency-core` hatte zuvor kein eigenes `CHANGELOG.md` (siehe
`docs/BACKLOG.md`, „Struktur/Prozess — Rest"). Für die Versionen ab
`1.17.5` liegt durchgehende, ausführliche Dokumentation vor (`1.22.0`–
`1.24.0` zusätzlich direkt aus den originalen Commit-Messages, siehe
„Offene Punkte" Punkt 1 für den Rechercheweg). Für `1.9.0`–`1.12.0` sind
nur knappe Stichpunkte aus `@since`-Kommentaren im Code verfügbar. Für
`1.0.0`–`1.8.x` liegt keinerlei Detail-Information vor — dort existieren
auch keine Git-Tags. **Bewusst nicht geraten** — siehe „Offene Punkte" am
Ende.

## [1.25.0] - 2026-08-22

### Added
- **`medialab_share()`/`[medialab_share]`: optionale `url`/`title`-Parameter**
  (`inc/social-share.php`) – bisher wurde immer die aktuelle Seiten-URL/
  der aktuelle Seitentitel geteilt. Für Fälle, in denen eine andere URL
  geteilt werden soll (z.B. `media-lab-woocommerce`s neues
  Wunschlisten-Sharing, das einen Token-Link statt der eigenen
  Seiten-URL teilt), können beide jetzt explizit übergeben werden.
  Vollständig abwärtskompatibel – kein Aufrufer ohne diese Parameter
  ändert sein Verhalten.

---

## [1.24.0] - 2026-08-17

### Added
- **YouTube Consent-Gate für `core/embed`** (`inc/youtube-embed-consent.php`)
  – `render_block`-Filter statt eigener Block: erkennt YouTube-URLs in
  nativen `core/embed`-Blöcken automatisch (kein neues Redakteur-Feld
  nötig) und ersetzt das Auto-Embed durch Placeholder + Consent-Klick.
  Thumbnail von `i.ytimg.com` (statisches Bild, kein Tracking vor
  Consent). Betrifft alle bestehenden YouTube-Embeds site-weit, nicht
  nur neue.
- **Facebook/Instagram Consent-Gate Blöcke** (`blocks/facebook-video/`,
  `blocks/social-embed/`) –
  - `medialab/facebook-video`: iframe-basiertes Zwei-Klick-Consent-Gate
  - `medialab/social-embed`: generalisiert für Facebook + Instagram
    (Instagram über `blockquote` + `embed.js`, providerabhängige
    Lade-/Entlade-Logik)
  - Mehrsprachigkeit über Polylang `pll_register_string()`
  - Breiten-Fix: `max-width` von `734px` (Facebook-Legacy-Wert) auf
    `100%`
  - Theme-seitig: `fb-video-consent.js`, `social-embed-consent.js` nach
    dem `google-maps.js`-Muster (`window.CookieConsent`-API, Kategorie
    `comfort`)

### Fixed
- **`vite.config.blocks.js` kompilierte `blocks.scss` nicht** – kein
  Build-Schritt dafür hinterlegt; `emptyOutDir` löschte die manuell
  erzeugte `blocks.css` sogar bei jedem Build wieder. Jetzt automatisiert
  über einen `writeBundle`-Hook.

---

## [1.23.0] - 2026-08-17

### Fixed
- **Project-Card-Rendering vereinheitlicht, Service-Card-Button repariert**
  – `service-card__cta` nutzte `button button--primary` (existiert
  nirgends im CSS, nur `.btn`/`.btn--primary`) – CTA-Button war komplett
  unstyled.
  - `projects_query_shortcode()`: falsche ACF-Feldnamen
    `client_name`/`project_year` (existieren nicht) → korrigiert auf
    `client`/`project_date` (echte Namen laut `group_project.json`).
    Client/Datum zeigten dadurch vorher nie etwas an.
  - `ajax-filters.php`: gleicher Feldname-Bug
    (`projekt_kunde`/`client_name` statt `client`), zusätzlich
    `categories` (jetzt Array statt nur erste Kategorie) und
    `url_external` ergänzt
  - `ajax-load-more.php`: Feldnamen waren hier bereits korrekt, `url`
    und `categories` ergänzt
  - `posts_load_more_template_project()`: Markup auf dieselbe reiche
    Struktur wie der Haupt-Shortcode umgestellt (Overlay/Link,
    Kategorien, Meta ohne doppelten Mittelpunkt-Separator)
  - Voraussetzung für den zugehörigen Theme-Commit (JS-Renderer auf
    einheitliches `.project-card`-Markup umgestellt – siehe
    `custom-theme`-Historie, nicht Teil dieses Changelogs)

*(Diese Version enthielt außerdem sieben `fix(theme):`-Commits am selben
Tag, die das Theme betreffen — Konsolidierung doppelter
`.project-card`/`.post-card`/`.notification`-CSS-Definitionen,
Entfernung toter `.service-card`-CSS, Repositionierung des
Dark-Mode-Toggles. Gehören ins Theme-Changelog, nicht hierher.)*

---

## [1.22.0] - 2026-08-15

### Added
- **Ajax-Suche: Highlighting, Kontext-Ausschnitt, Attribut-Suche**
  (`inc/ajax-search.php`) – bündelt vier zusammenhängende Verbesserungen:
  - `agency_core_highlight_search_term()`: markiert Treffer per `<mark>`
    in Titel und Excerpt (`esc_html()` zuerst, dann markieren)
  - `agency_core_get_context_excerpt()`: zeigt einen Ausschnitt um die
    tatsächliche Fundstelle im Content statt immer nur den Anfang
  - `agency_core_search_product_attributes()` /
    `_local_product_attributes()` / `_configurator_options()`: findet
    Produkte auch über globale `pa_*`-Taxonomien, lokale Attribute und
    Konfigurator-Optionen (`config_steps` → `options`), die
    `WP_Query`s `s`-Parameter nicht durchsucht. Reine Attribut-Treffer
    zeigen „Attribut: Wert" statt Content-Ausschnitt.

### Fixed
- **`post_type`-Filter griff bei mehreren Post-Types nicht** – `wp_magic_quotes()`
  hatte den Wert vor `json_decode()` escaped (WP-Kernverhalten).
  `wp_unslash()` ergänzt – gleiches Muster wie
  `media-lab-woocommerce/inc/wishlist/class-ajax.php::decode_json()`.

---

## [1.21.0] - Datum nicht exakt verifiziert (zwischen 1.20.1 und 1.22.0)

### Added
- Neue Einstellung "Suche in Navigation" (Logo / Globale Einstellungen →
  UI-Features, Standard: an). Zeigt ein Such-Icon im Hauptmenü, das ein
  Such-Overlay mit der bestehenden Ajax-Search-Komponente öffnet.
  *(Quelle: README Inline-Changelog, keine weiteren Details verfügbar)*

---

## [1.20.1] - Datum nicht exakt verifiziert (zwischen 1.20.0 und 1.21.0)

### Added
- WCAG-2.2.2-Fokus-Pause im Logo-Slider ergänzt (`ml-logo-slider.js`,
  Theme-seitig): Autoplay pausiert automatisch bei
  Tastatur-/Screenreader-Fokus auf ein Element innerhalb des Sliders.
  Portierung des in 1.19.1 deaktivierten Verhaltens aus
  `block-logo-slider.js` ins Theme.

### Fixed
- Veraltete Kommentarverweise in `assets/css/block-slider.css` auf die
  gelöschte `assets/js/block-slider.js` korrigiert – verweisen jetzt auf
  `ml-slider.js` (Theme). Rein kosmetisch, keine Funktionsänderung.

*(Quelle: README Inline-Changelog, deckt sich mit `docs/BACKLOG.md` Paket D)*

---

## [1.20.0] - 2026-08-12

### Added
- **Heartbeat Monitoring** (`inc/heartbeat.php`) – Push-basiertes Monitoring
  als Ersatz für klassische Pull-Uptime-Checks (UptimeRobot,
  Better-Stack-HTTP-Monitore). Löst wiederkehrende Fehlalarme, die durch
  externe Prober bei Shared-Hosting-typischen Effekten entstehen (langsame
  Antwortzeiten, WAF-/Ping-Blockaden, DNS-Hänger) – die Seite meldet sich
  stattdessen selbst in festem Intervall.
  - Neuer REST-Endpoint `/wp-json/medialab/v1/heartbeat` (Token-Auth via
    `hash_equals()`, Token wird bei Erstaktivierung automatisch generiert)
  - Führt vor jedem Ping einen Mini-Health-Check durch (DB-Verbindung); bei
    Fehlschlag wird `/fail` an die Provider-URL angehängt statt
    stillzuschweigen
  - Kompatibel mit Better Stack Heartbeats und Healthchecks.io
  - Neue ACF-Options-Page „Heartbeat Monitoring" (`inc/acf-settings.php`):
    Enable-Toggle, Provider-Auswahl (nur informativ), Heartbeat-URL, fertig
    zusammengesetzter REST-Endpoint inkl. Token zum Copy-Paste für den
    Server-Cronjob, Anzeige des letzten erfolgreichen Pings
  - `medialab_heartbeat_get_setting()`: liest Config primär über
    `get_field($name, 'option')`, fällt auf direktes `get_option()` zurück
  - Empfohlenes Deployment-Modell: zentraler Dispatcher-Cronjob auf eigenem
    Hetzner-Webhosting statt Einzel-Cronjobs pro Client-Site

### Removed
- **`inc/security.php` entfernt** – leerer Platzhalter seit dem ersten
  Commit des Repos, nie mit Inhalt befüllt. Tatsächliche
  Security-Funktionalität war von Anfang an auf `hcaptcha.php`,
  `honeypot.php`, `turnstile.php`, `spam-content-filter.php` und
  `class-mla-security-scanner.php` aufgeteilt.

---

## [1.19.2] - 2026-08-07

### Fixed
- **Security-Scan meldete auf nginx-Systemen dauerhaft unbehebbare Fehler**
  ("Subdirectory-Fix für wp-content/wp-includes"), inkl. wöchentlicher
  Alarm-E-Mail und rotem ❌ im Admin – obwohl die zugrunde liegende
  `.htaccess`-Prüfung auf nginx grundsätzlich nie etwas finden kann (nginx
  wertet `.htaccess` nicht aus; die Konfiguration gehört in den Vhost).
  Betraf praktisch jedes Starter-Kit-Projekt mit `/cms/`-Subdirectory-Setup
  auf nginx.

---

## [1.19.1] - Datum nicht exakt verifiziert (zwischen 1.19.0 und 1.19.2)

> ⚠️ Versionsnummer und Datum dieses Eintrags sind aus der Reihenfolge im
> root-`CHANGELOG.md` erschlossen, nicht direkt aus einer Versions-Kopfzeile
> gelesen — Inhalt selbst ist aber im Quelltext eindeutig belegt.

### Fixed
- **Doppel-Initialisierung von Swiper in `medialab/slider` und
  `medialab/logo-slider`** – `block-slider.js` (Plugin) und `ml-slider.js`
  (Theme) initialisierten beide denselben Swiper-Container; ein
  `el.swiper`-Check in `block-slider.js` sollte das verhindern, schlug aber
  durch einen Bug immer fehl, wodurch `block-slider.js` faktisch tot war.
  - `inc/blocks.php`: kaputten `swiper`-Enqueue (Script + Style) entfernt;
    Swiper-CSS kommt bereits über das Theme (`main.js` importiert
    `swiper/css/bundle`)
  - `assets/js/block-slider.js` entfernt (redundant/tot).
    `assets/css/block-slider.css` (Skeleton aus 1.19.0) bleibt bestehen –
    hängt nur an Swipers eigener `swiper-initialized`-Klasse
  - Theme-seitiges `ml-slider.js` übernimmt zusätzlich den
    Skeleton-Fallback in allen Fehlerpfaden
  - `medialab/logo-slider` (`block-logo-slider.js`) ebenfalls deaktiviert,
    gleicher Bug, gleiche Ursache

### Bekannter Trade-off (weiterhin offen)
- `medialab/logo-slider` hat aktuell keine WCAG-2.2.2-Fokus-Pause im
  Frontend aktiv – `block-logo-slider.js` enthielt diese Logik, war aber
  durch obigen Bug ohnehin nie aktiv. Funktional unverändert zum
  vorherigen Zustand. (Wurde in **1.20.1**, siehe unten, ins Theme portiert.)

---

## [1.19.0] - 2026-08-06

### Added
- **Skeleton für `medialab/slider` (Stufe 2)** – reiner CSS-Skeleton für die
  Zeitspanne zwischen erstem Paint und abgeschlossener
  Swiper-Initialisierung. Nutzt Swipers eigene `swiper-initialized`-Klasse
  als CSS-Selektor, kein zusätzliches JS zum Ein-/Ausblenden nötig – nur
  ein Sicherheitsnetz in `block-slider.js`
  (`ml-slider__swiper--skeleton-done`), das den Skeleton in allen
  Fehler-/Abbruchpfaden beendet. Shimmer-Keyframe wiederverwendet aus dem
  site-weiten `skeleton.css` (1.18.0).
- **Frontend-Verifizierung der nativen-Block-Migration abgeschlossen**
  (offen seit 1.16.0/1.17.x): `medialab/slide` setzt die Klasse
  `swiper-slide` bereits direkt am eigenen `useBlockProps.save()`-Root,
  wodurch die Wrapping-Logik in `block-slider.js` für nativ erzeugte
  Folien in der Praxis nie greift – rein defensiver Fallback für
  Alt-Content.

---

## [1.18.0] - 2026-08-05

### Added
- **Zentrale Skeleton-Loading-API** (`inc/skeleton.php`,
  `assets/css/skeleton.css`, `assets/js/skeleton.js`) – wiederverwendbares
  Feature für Skeleton-Screens statt Spinner/Opacity-Dimming bei
  AJAX-Requests und verzögerter JS-Initialisierung. Site-weit als
  `window.MediaLabSkeleton` verfügbar (JS) sowie über
  `medialab_render_skeleton()` (PHP). Vier Varianten: `card`, `list`,
  `text`, `slide`. Farben per CSS Custom Properties im Theme
  überschreibbar. Respektiert `prefers-reduced-motion`.
  - Stufe 1 einer dreistufigen Skeleton-Einführung: (1) AJAX-Content
    [erledigt], (2) JS-init-abhängige Blocks (Slider/Parallax) [siehe
    1.19.0], (3) initialer Seitenaufbau [offen]

---

## [1.17.5] - 2026-08-03

### Added
- **WP All Import Integration**
  (`inc/integrations/wp-all-import-timeout.php`,
  `inc/integrations/wp-all-import-custom-download.php`) – Zwei Fixes für
  Bilder-Downloads bei WP-All-Import-Importen:
  - Timeout-Fix: `pmxi_image_download_timeout`-Filter erhöht den
    Bilder-Download-Timeout von 5s auf 30s (überschreibbar via
    `mlac_wpai_image_timeout_seconds`)
  - `custom_file_download()`-Helper gegen UA-basiertes Blocking
  - Backport aus dem Janecka-Projekt

---

## Ältere Versionen (unvollständig dokumentiert)

Für die folgenden Versionen liegt **keine ausführliche Beschreibung** vor —
nur Versionsnummer + knapper Stichpunkt, entnommen aus README-Inline-Notizen
oder `@since`-Code-Kommentaren. Nicht geraten, nicht ausgeschmückt.

- **1.12.0** — Dark Mode Toggle (`inc/dark-mode.php`) — Frontend-Steuerung,
  liest ACF-Option `dark_mode_enabled`. *(Quelle: `@since`-Kommentar)*
- **1.11.0** — Logo CPT (`inc/cpt-logos.php`) — zentrale Verwaltung von
  Partner-/Kunden-Logos im Backend. Erweitert in **1.11.6** um CPT als
  Logo-Quelle im Logo-Grid-Block. *(Quelle: `@since`-Kommentare)*
- **1.10.0** — Table of Contents (Shortcode `[table_of_contents]`/`[toc]`,
  Gutenberg-Block, automatische Heading-IDs, Scrollspy-JS, Sticky-Modus).
  *(Quelle: `@since`-Kommentar)*
- **1.9.0** (2026-05-07) — Cookie Consent Mehrsprachigkeit (Polylang → WPML
  → WP-Locale-Fallback, Repeater-Feld `cc_languages`), Share-Buttons
  globale Konfiguration (`inc/social-share.php`, neue Admin-Seite,
  Auto-Insert, Shortcode `[medialab_share]`, neuer Kanal `copy` via
  Clipboard-API), Share-Buttons als Gutenberg-Block `medialab/social-share`.
  *(Quelle: root CHANGELOG.md — vollständig, siehe Git-Historie für exakten
  Wortlaut falls benötigt)*
- **1.8.5** (2026-04-23) — E-Mail-Obfuskierung: Fix bei Gutenberg-Buttons,
  `protect_content_emails()` baute `<a>`-Tag neu auf und verlor dabei
  Original-Attribute. *(Detailbeschreibung nur teilweise eingesehen,
  Kernaussage aber gesichert)*
- **1.0.0–1.8.0** — Keine Detail-Informationen verfügbar. Erste bekannte
  Version laut Git-Historie referenziert einen frühen Commit „Inquiry
  Setup". Für eine vollständige Rekonstruktion müsste die Git-Historie
  direkt ausgewertet werden (`git log --follow` auf die Plugin-Hauptdatei) —
  außerhalb dessen, was aus dem aktuellen Codestand ableitbar ist.

---

## ⚠️ Offene Punkte

1. ~~Versionslücke 1.21.1–1.24.0 komplett undokumentiert~~ — **behoben
   21.08.2026** durch Auswertung von `git log` zwischen den vorhandenen
   Tags (`media-lab-agency-core-v1.21.0` bis `v1.23.0`, plus `HEAD` für
   den noch nicht getaggten `1.24.0`-Stand). Einträge für `1.22.0`,
   `1.23.0` und `1.24.0` jetzt aus echten Commit-Messages befüllt.
   **Verbleibend:** `1.24.0` sollte noch mit einem Tag versehen werden
   (`media-lab-agency-core-v1.24.0`), analog zu den Vorgängerversionen —
   ist aktuell nur als loser `HEAD`-Stand referenzierbar.
2. **1.19.1 (Swiper-Fix) und 1.20.1/1.21.0:** Datum nicht exakt
   verifiziert (keine Tags für diesen Bereich vorhanden, nur aus
   Reihenfolge/README erschlossen). Inhalt ist gesichert, nur die exakte
   Zeitangabe nicht. Niedrige Priorität — bei Bedarf über
   `git log --follow -- cms/wp-content/plugins/media-lab-agency-core/media-lab-agency-core.php`
   nachschlagbar, analog zum Vorgehen oben.
3. **1.0.0–1.8.0:** weiterhin komplett offen, keine Tags in diesem
   Bereich vorhanden (`git tag -l "media-lab-agency-core-v*"` beginnt
   erst bei `1.20.1`). Für eine Rekonstruktion müsste die volle,
   ungetaggte Commit-Historie der Plugin-Hauptdatei durchsucht werden —
   eigener, größerer Aufwand, hier bewusst nicht angegangen.
