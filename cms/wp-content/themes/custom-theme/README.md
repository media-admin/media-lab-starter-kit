# Custom Theme — Interne Entwickler-Doku

> Das ist die **interne** README des Themes selbst
> (`cms/wp-content/themes/custom-theme/README.md`). Für die
> Projekt-weite Doku (alle Plugins, Setup, Deployment) siehe die
> **Root-README** (`media-lab-starter-kit/README.md`) und `docs/`.

**Version:** 1.15.4 (siehe `style.css`-Header)
**Benötigt:** `media-lab-agency-core` (siehe Hinweis unten)
**Autor:** Media Lab Tritremmel GmbH

---

## Versions-Handling

`CUSTOM_THEME_VERSION` (in `functions.php`) wird dynamisch aus dem
`style.css`-Header gezogen (`wp_get_theme()->get('Version')`), nicht
mehr hartcodiert — kann dadurch nicht mehr aus dem Takt geraten. Vorher
stand die Konstante lange auf einem eingefrorenen `'1.4.0'`, während
`style.css` längst weiter war; seit 22.08.2026 behoben.

---

## Voraussetzungen

- WordPress 6.0+
- PHP 8.0+
- Node.js 18+ (nur für die Entwicklung, nicht fürs Deployment)
- **`media-lab-agency-core`** aktiv — das Theme prüft das selbst
  (`customtheme_check_required_plugins()` in `functions.php`) und zeigt
  einen Admin-Hinweis, funktioniert aber auch ohne (reduzierter
  Funktionsumfang: keine ACF-Options-Felder, keine Shortcodes, keine
  Custom-Blocks)

---

## Setup

```bash
cd cms/wp-content/themes/custom-theme
npm install
npm run dev      # Entwicklung mit Hot Module Replacement
npm run build    # Production-Build
```

**Wichtig:** `npm run build` sollte immer vom **Projekt-Root**
ausgeführt werden, nicht aus diesem Theme-Ordner — baut in einem
Rutsch sowohl die Theme-Assets als auch die Gutenberg-Block-Assets von
`media-lab-agency-core`. Details, Vite-Konfiguration,
`vite-dev.mjs`-Mechanismus (Hot-Datei etc.) siehe
[docs/06_DEVELOPMENT.md](../../../../docs/06_DEVELOPMENT.md#build-system) —
hier nicht dupliziert.

---

## Projektstruktur (Theme-intern)

```
custom-theme/
├── assets/
│   ├── src/
│   │   ├── scss/            SCSS-Quellen (7-1-Architektur, siehe unten)
│   │   └── js/               JS-Komponenten (ESM, dynamischer Import)
│   ├── dist/                  Build-Output — NICHT committen (.gitignore)
│   └── hot                    Nur während `npm run dev` vorhanden — NICHT committen
├── inc/                       PHP-Includes (Enqueue, Performance, Welcome Mode, …)
├── template-parts/            Wiederverwendbare Template-Teile
├── page-templates/             Page-Templates (z.B. Welcome Page)
├── theme.json                  Block-Editor-Einschränkungen (siehe unten)
├── functions.php
├── header.php / footer.php
├── single.php / archive.php / search.php / 404.php
└── style.css                   Theme-Header (WordPress-maßgebliche Versionsnummer)
```

---

## SCSS-Architektur (Kurzfassung)

Vollständige Doku: [docs/06_DEVELOPMENT.md](../../../../docs/06_DEVELOPMENT.md#scss-architektur).

7-1-artige Struktur unter `assets/src/scss/`: `abstracts/` (Design-Tokens
+ Mixins, via `@use '../abstracts' as *;` in jedem Partial eingebunden),
`base/`, `components/` (~35 Partials, ein Partial pro UI-Komponente),
`layout/`, `pages/`, `templates/`, `utilities/`, `woocommerce/`.

**Design-Tokens ändern** — `abstracts/_variables.scss`:

```scss
// Farben
$color-primary        #e00000
$color-secondary      #1a1a2e
$color-success        #0a8754
$color-warning        #f59e0b
$color-error          #dc2626

// Spacing
$spacing-xs   0.25rem
$spacing-sm   0.5rem
$spacing-md   1rem
$spacing-lg   1.5rem
$spacing-xl   2rem
$spacing-2xl  3rem

// Breakpoints
$breakpoint-sm   480px
$breakpoint-md   768px
$breakpoint-lg   1024px
$breakpoint-xl   1280px
```

> Die alte Version dieser README nannte hier `$color-primary: #667eea`
> und `$color-secondary: #764ba2` — das stimmt nicht mehr, war
> vermutlich nie mehr als ein Platzhalter aus der allerersten
> Theme-Version.

Typografie und die meisten Abstände laufen zusätzlich über
`clamp()`-basierte CSS Custom Properties (`var(--text-h1)`,
`var(--space-section)` etc.) statt fixer SCSS-Variablen — fluid, ohne
Media-Query-Sprünge. `respond-to()`/`respond-below()` sind nur noch für
**strukturelle** Layout-Änderungen (Grid-Spalten, `display: none`)
gedacht, nicht für Typografie/Spacing.

---

## JavaScript-Architektur (Kurzfassung)

Vollständige Doku: [docs/06_DEVELOPMENT.md](../../../../docs/06_DEVELOPMENT.md#javascript-architektur).

`main.js` lädt Kern-Komponenten (z.B. `Navigation`) sofort, alles
andere per dynamischem Import nur, wenn das passende DOM-Element
tatsächlich auf der Seite vorhanden ist — kein ungenutztes JS im
Bundle-Pfad.

**Repräsentative Auswahl** (keine vollständige Liste — die pflegt sich
am besten selbst über `assets/src/js/components/`):

| Komponente | Zweck |
|---|---|
| `navigation.js` | Header/Footer-Navigation, 4 Ebenen, Mobile-Accordion, Desktop-Flyout-Kollisionserkennung |
| `accordion.js` / `faq-accordion.js` | Zwei getrennte Accordion-Varianten (generisch vs. FAQ-spezifisch) |
| `spoiler.js` | Ein-/ausklappbarer Inhaltsbereich, `show_on`-Attribut für Viewport-Steuerung |
| `tabs.js` | Tab-Umschaltung |
| `stats-counter.js` | Hochzählende Zahlen beim Scrollen ins Viewport |
| `scroll-animations.js` | `data-animate`-gesteuerte Scroll-Trigger |
| `video-player.js` | Custom-Video-Player-Steuerung |
| `image-comparison.js` | Vorher/Nachher-Bildvergleich |
| `ajax-search.js` / `load-more.js` / `ajax-filters.js` | AJAX-Interaktion mit `media-lab-agency-core`/`media-lab-woocommerce` |
| `google-maps.js` | Lazy-geladene Google-Maps-Einbindung |
| `toggle.js` | 3-State-Switch (on/off/unavailable), auch programmatisch nutzbar |
| `youtube-embed-consent.js` / `fb-video-consent.js` / `social-embed-consent.js` | Cookie-Consent-Gates für eingebettete Drittanbieter-Inhalte |

Neue Komponente erstellen: Muster siehe
[docs/06_DEVELOPMENT.md](../../../../docs/06_DEVELOPMENT.md#javascript-architektur)
(Komponenten-Klasse + Registrierung in `main.js` mit `has()`-Check).

---

## „Customizer" — was hier wirklich passiert

**Korrektur zur alten README:** Es gibt **keinen** klassischen
WordPress-Customizer-Bereich mit eigenen Sections/Settings für dieses
Theme (kein `customize_register()`-Hook im Code). Die alte README
sprach von „Configure in Customizer" — das war schon länger nicht mehr
zutreffend.

Tatsächlich läuft Anpassung auf zwei Ebenen:

1. **Entwickler, zur Build-Zeit:** SCSS-Design-Tokens (siehe oben) —
   Farben, Spacing, Breakpoints. Erfordert `npm run build`.
2. **Redakteure, zur Laufzeit:** ACF-Options-Seiten aus
   `media-lab-agency-core` (Logo, globale Einstellungen, Hero Image,
   Top-Header, Social Share, …) — siehe
   [docs/03_PLUGINS.md](../../../../docs/03_PLUGINS.md). Das
   Haupt-Logo im Header läuft z.B. über `get_field('logo_desktop',
   'option')`, **nicht** über den nativen WP-Customizer-Logo-Upload.

`add_theme_support('custom-logo')` ist im Code vorhanden, wird aber nur
als Fallback in der Welcome-Page-Vorlage genutzt (`has_custom_logo()`/
`the_custom_logo()`), falls dort kein eigenes ACF-Logo gesetzt ist —
nicht auf der regulären Site.

`theme.json` deaktiviert zudem bewusst nahezu alle nativen
Block-Editor-Anpassungsmöglichkeiten (Farben, Typografie, Spacing —
alle auf `false`), damit Redakteure im Block-Editor nicht versehentlich
am Design-System vorbei eigene Farben/Schriftgrößen setzen.

---

## Navigation

4 Ebenen, in Header **und** Footer. Drei registrierte Menü-Positionen
(`functions.php`):

| Location | Zweck |
|---|---|
| `primary` | Hauptnavigation |
| `footer` | Footer-Navigation (oberer Bereich) |
| `footer-legal` | Footer Legal (Impressum, Datenschutz, AGB, …) |

Details zum Ebenen-Verhalten (Dropdown/Flyout/Viewport-Kollision,
Mobile-Accordion) siehe
[docs/06_DEVELOPMENT.md](../../../../docs/06_DEVELOPMENT.md#navigation--4-ebenen).

---

## Theme-Support (`functions.php`)

```php
add_theme_support('post-thumbnails');
add_theme_support('title-tag');
add_theme_support('custom-logo');          // siehe Customizer-Hinweis oben
add_theme_support('html5', [...]);
add_theme_support('responsive-embeds');
add_theme_support('editor-styles');
```

Bei aktivem WooCommerce zusätzlich `wc-no-sidebar` (unterdrückt
WooCommerce-eigene Sidebar-Suche — das Theme hat eigene
Filter-/Sidebar-Systeme), `wc-product-gallery-zoom`,
`-lightbox`, `-slider`.

Bild-Größen: `custom-thumbnail` (400×300), `custom-medium` (800×600),
`custom-large` (1200×900), alle hart zugeschnitten (`true`).

---

## Optionale Komponenten (`functions.php`)

Werden nur geladen, wenn die Datei existiert (schadet also nicht, wenn
sie in einem Projekt-Fork entfernt wird):

```
walker-nav-menu.php
helpers.php
woocommerce.php
woocommerce-emails.php
acf-welcome.php    — ACF-Felder für die Welcome Page
welcome-mode.php   — Redirect-Logik Welcome Mode (auskommentieren zum Deaktivieren)
```

Welcome Mode selbst (temporäre Baustellen-Seite) ist ausführlich in
[docs/06_DEVELOPMENT.md](../../../../docs/06_DEVELOPMENT.md#welcome-mode)
dokumentiert.

---

## Dokumentation — wo was steht

Diese README ist bewusst **kein** vollständiges Nachschlagewerk. Für
alles Tiefere:

| Dokument | Inhalt |
|---|---|
| [CHANGELOG.md](./CHANGELOG.md) | Versionshistorie dieses Themes (seit `1.15.3`) |
| [docs/06_DEVELOPMENT.md](../../../../docs/06_DEVELOPMENT.md) | Vite/SCSS/JS-Architektur im Detail, Git-Workflow, Best Practices |
| [docs/03_PLUGINS.md](../../../../docs/03_PLUGINS.md) | Alle Plugins inkl. der ACF-Options-Seiten, die dieses Theme konsumiert |
| [docs/07_TROUBLESHOOTING.md](../../../../docs/07_TROUBLESHOOTING.md) | Fehlerbehebung |
| [docs/09_ACF-FIELDS.md](../../../../docs/09_ACF-FIELDS.md) | ACF-Felder-Referenz |
| [docs/BACKLOG.md](../../../../docs/BACKLOG.md) | Offene Punkte, Änderungshistorie größerer Aufräum-Sessions |

Root-README des gesamten Starter Kits:
[media-lab-starter-kit/README.md](../../../../README.md).

---

## Lizenz

Proprietär – Media Lab Tritremmel GmbH
Kontakt: [markus.tritremmel@media-lab.at](mailto:markus.tritremmel@media-lab.at)
