# Media Lab Starter Kit

**Professional WordPress Agency Framework** – Modulares Plugin-System für skalierbare Kundenprojekte.

[![Version](https://img.shields.io/badge/version-1.22.5-blue.svg)](CHANGELOG.md)
[![PHP](https://img.shields.io/badge/PHP-8.0+-purple.svg)](https://php.net)
[![WordPress](https://img.shields.io/badge/WordPress-6.0+-blue.svg)](https://wordpress.org)
[![License](https://img.shields.io/badge/license-proprietary-red.svg)](#lizenz)

---

## Übersicht

Vollständiges WordPress-Starter-Kit mit modularer Plugin-Architektur für Agentur-Workflows. Entwickelt für Wartbarkeit, Sicherheit und schnelles Client-Deployment.

### Architektur-Prinzip

```
media-lab-agency-core   →  Wiederverwendbares Framework (nie modifizieren)
media-lab-seo           →  SEO-Toolkit (pro Projekt aktivieren + konfigurieren)
media-lab-bookings      →  Buchungssystem (optional, bei Bedarf aktivieren)
custom-theme            →  Präsentationsebene (pro Projekt anpassen)
```

---

## Plugins & Versionen

### media-lab-agency-core `v1.17.5`

Framework-Plugin – wird **unverändert** auf allen Projekten eingesetzt.

**Shortcodes & Content:**
- 44+ Shortcodes: Hero Slider, Accordion, Stats, Testimonials, Modal, Tabs, Carousel, FAQ, Timeline, Video Player, Team, Projects, Services u.v.m.
- Google Maps Shortcode: iframe-Embed, Cookie-Consent-integriert, Fullwidth-Option
- Spoiler Shortcode: Viewport-Control, Gradient-Fade-Design
- Pricing Table & Stats Shortcodes: CSS-klassen-basiertes Responsive-Layout (kein Inline-Style)
- Notifications CPT + Shortcodes: systemweite Benachrichtigungen als eigener Post-Typ
- Social Share: konfigurierbare Teilen-Schaltflächen

**Gutenberg:**
- 8 Custom Blocks (Kategorie „Design"): Hero, Testimonial, Team-Mitglied, Logo-Leiste, Logo-Slider (ACF), CTA-Banner, Accordion/FAQ, Icon+Text (native)
- ACF Blocks via `inc/acf-blocks.php` (programmatisch registriert)
- Hero Image Block

**AJAX:**
- Search, Load More, Post-Filter mit Rate-Limiting (30 Req/min)

**Security & DSGVO:**
- SVG-Sanitizer (Allowlist, DOMDocument)
- IP-Anonymisierung (DSGVO, 90-Tage-Cron)
- hCaptcha-Integration (mit ACF PRO Guard)
- E-Mail-Obfuskierung (Spam-Schutz für Mailadressen im Frontend)
- Open Redirect Fix, SQL-Wildcard-Schutz, DB-Flooding Prevention
- Activity Log (Admin-seitige Protokollierung)

**Cookie Consent & Datenschutz:**
- DSGVO-konformes Cookie-Consent-System
- Konfigurierbare Texte: `saveConsent`, `essentialOnly`, `openSettings`
- Google Consent Mode v2

**Admin & Konfiguration:**
- ACF Options Page + Feldgruppen (Top Header, Multi-Language) – programmatisch via PHP
- Multi-Language-Support (aktivierbar via ACF-Toggle)
- Drag & Drop Post/Term-Order
- Duplicate Post/Term
- SMTP-Mailer (Credentials aus `wp-config.php`)
- White-Label / Agentur-Branding
- Maintenance Mode (ACF-konfigurierbar, 503-Header, Admin-Bypass)
- Media Replace

**Import-Tools:**
  - WP All Import: Timeout-Fix & UA-Blocking-Workaround für Bilder-Downloads (manuelle Template-Einrichtung pro Import nötig)

**Helper:**
- `medialab_get_thumbnail()` – responsive Bilder mit srcset + lazy loading

> **Hinweis:** ACF PRO ist eine Pflichtabhängigkeit. Alle ACF-Aufrufe sind mit `function_exists('get_field')` abgesichert; bei fehlendem ACF PRO erscheint ein Admin-Notice.

---

### media-lab-seo `v1.3.0`

SEO-Toolkit – pro Projekt aktivieren und konfigurieren.

**Features:**
- Schema.org JSON-LD Structured Data
- Meta-Tags, Open Graph Tags, Twitter Cards, Canonical URLs
- Breadcrumbs mit strukturierten Daten
- Sitemap-Integration
- Redirect Manager (301/302, verwaltbar im Admin)
- Google Search Console OAuth2-Integration
- Analytics Adapter: GA4 & Matomo (umschaltbar)
- Image Sitemap via Custom Rewrite Endpoint
- Wöchentliche E-Mail-Reports via Agency Core SMTP

---

### media-lab-bookings `v1.3.1`

Standortbasiertes Buchungssystem – optional aktivierbar.

**Features:**
- 2 Custom Post Types: Standorte & Buchungen
- ACF-Feldgruppen (programmatisch registriert)
- AJAX-basiertes Slot-Loading mit Flatpickr Datepicker
- Shortcode `[mlb_booking_form]`
- DSGVO-Consent-Checkbox im Buchungsformular
- Vollständiges Admin-Dashboard (Buchungsübersicht, Statusverwaltung)
- WooCommerce-Style Template Overrides für projektspezifische Anpassungen
- Status-Change E-Mail-Benachrichtigungen *(geplant)*

---

### custom-theme `v1.14.0`

Präsentationsebene – enthält keine Business-Logik.

**Build & Assets:**
- Vite Multi-Config Build:
  - `vite.config.js` → Theme-Assets (SCSS, JS)
  - `vite.config.blocks.js` → Gutenberg-Block-Assets
- 27+ Dynamic-Import-Komponenten (JS nur geladen wenn DOM-Element vorhanden)
- Self-Hosted Fonts via Vite `publicDir` (`assets/public/fonts/` → `assets/dist/fonts/`)
- `cssCodeSplit: false` – CSS via `filemtime()` eingebunden

**SCSS & Design:**
- Design-Tokens vollständig als CSS Custom Properties
- `@use` / `@forward` (Dart Sass 2.0+ kompatibel), 7-1-Architektur
- Fluid Typography & Spacing: `clamp()`-basierte Tokens
- `respond-to()` / `respond-below()` Breakpoint-Mixins
- Fullwidth-Helper: `.fullwidth`, `.fullwidth--bg`, `.fullwidth--media`
- Dark Mode (CSS Custom Properties, `data-theme`-Attribut)
- Swiper.js mit `_swiper-overrides.scss` für konsistente CSS-Kontrolle

**Navigation:**
- 4-Ebenen Navigation: Desktop (Flyout) + Mobile (Accordion)
- Footer Navigation: `footer` Menu-Location, Flyout nach oben (Desktop)
- Footer Legal Navigation: `footer-legal` Menu-Location (Impressum, Datenschutz, AGB)
- Viewport-Kollisionserkennung: `.opens-left` bei Überlauf

**Barrierefreiheit (WCAG 2.1 AA):**
- Skip-Link für Tastaturnavigation
- Focus-Styles für alle interaktiven Elemente
- Touch-Targets auf min. 44×44px
- `prefers-reduced-motion` Media Query
- `aria-hidden` auf dekorativen Elementen
- Semantische Landmarks (`main`, `nav`, `footer`)
- Primärfarbe `#d40000` (WCAG-konformer Kontrast)

**Seiten & Footer:**
- Welcome Page Template: ACF-Felder, automatische Redirect-Logik nach Setup
- 404-Seite mit Suchformular und Quick-Links aus Hauptmenü
- Footer Legal-Menü + Agentur-Creditline (Hover-Opacity, `border-top`-Trenner)

**Performance & Security:**
- Emoji/oEmbed deaktiviert, WP Head bereinigt, Responsive Images
- HTTP-Header in `.htaccess` (X-Frame-Options, X-Content-Type-Options etc.)

---

## Schnellstart

### Voraussetzungen

| Software | Version |
|---|---|
| PHP | 8.0+ |
| MySQL / MariaDB | 5.7+ / 10.3+ |
| Node.js | 18+ |
| npm | 9+ |
| Composer | 2.0+ |
| WP-CLI | 2.8+ |
| ACF PRO | aktuell |

### Installation

```bash
# 1. Repository klonen
git clone https://github.com/media-admin/media-lab-starter-kit.git
cd media-lab-starter-kit

# 2. Dependencies
npm install
composer install

# 3. WordPress (Valet-Beispiel)
cd cms
wp core download --locale=de_DE
wp core config --dbname=media_lab --dbuser=root --dbpass=root
wp core install \
  --url=media-lab-starter-kit.test \
  --title="Media Lab" \
  --admin_user=admin \
  --admin_password=SICHERES_PASSWORT \
  --admin_email=admin@media-lab.at

# 4. Plugins & Theme aktivieren
wp plugin activate media-lab-agency-core media-lab-seo advanced-custom-fields-pro
wp theme activate custom-theme
cd ..

# 5. Assets bauen
npm run build
```

Vollständige Anleitung: [docs/02_INSTALLATION.md](docs/02_INSTALLATION.md)

---

## Build-System

```bash
npm run dev        # Development mit Hot Reload (Valet Hot-File-Mechanismus)
npm run build      # Production Build (Theme + Blocks)
npm run watch      # Watch ohne Dev-Server
npm run dev:stop   # Hot-File entfernen (vor Production-Build ausführen)
```

**Build-Output:**
```
assets/dist/
├── css/style.css          # Alle Styles (eine Datei, cssCodeSplit: false)
├── js/main.js             # Kern-Bundle
├── js/ajax-filters.js     # Lazy Chunk
├── js/ajax-search.js      # Lazy Chunk
├── js/load-more.js        # Lazy Chunk
├── js/google-maps.js      # Lazy Chunk
├── js/notifications.js    # Lazy Chunk
├── fonts/                 # Self-Hosted Fonts (via publicDir)
└── js/chunks/             # Automatische Vite-Chunks
```

---

## SMTP-Konfiguration

SMTP-Credentials in `cms/wp-config.php` definieren – **nie** in der Datenbank speichern:

```php
define('MEDIALAB_SMTP_ENABLED',   true);
define('MEDIALAB_SMTP_HOST',      'smtp.example.com');
define('MEDIALAB_SMTP_PORT',      587);
define('MEDIALAB_SMTP_USER',      'user@example.com');
define('MEDIALAB_SMTP_PASS',      'geheimes-passwort');
define('MEDIALAB_SMTP_ENC',       'tls');
define('MEDIALAB_SMTP_FROM',      'noreply@example.com');
define('MEDIALAB_SMTP_FROM_NAME', 'Meine Website');
```

---

## Neues Kundenprojekt

```bash
./scripts/setup-project.sh
```

Fragt nach Projekt-Name, Theme-Slug, Plugin-Slug und Text-Domain und benennt automatisch um.

Vollständige Anleitung: [docs/10_DEPLOYMENT.md](docs/10_DEPLOYMENT.md)

---

## Projektstruktur

```
media-lab-starter-kit/
├── cms/
│   └── wp-content/
│       ├── plugins/
│       │   ├── media-lab-agency-core/     # Framework v1.17.5
│       │   ├── media-lab-seo/             # SEO-Toolkit v1.3.0
│       │   └── media-lab-bookings/        # Buchungssystem v1.3.1
│       └── themes/
│           └── custom-theme/              # Theme v1.14.0
│               ├── assets/src/scss/       # SCSS + Design-Tokens + Fluid Tokens
│               ├── assets/src/js/         # 27+ JS-Komponenten
│               ├── assets/public/fonts/   # Self-Hosted Fonts (Quelle)
│               ├── assets/dist/           # Build-Output (nicht committen)
│               └── inc/                   # PHP-Helpers, Cookie Consent
├── docs/                                  # Dokumentation
├── scripts/                               # Deploy-Scripts
├── tests/                                 # Playwright E2E
├── vite.config.js                         # Theme-Assets Build
├── vite.config.blocks.js                  # Gutenberg-Block-Assets Build
├── package.json
└── CHANGELOG.md
```

---

## Entwicklungs-Prinzipien

| Thema | Regel |
|---|---|
| ACF PRO | Immer `function_exists('get_field')` prüfen; Admin-Notice bei fehlendem ACF |
| PHP Inline Styles | Kein `!important` via PHP – Layout-Kontrolle ausschließlich über CSS-Klassen |
| Vite Fonts | Fonts in `assets/public/fonts/` ablegen (werden verbatim nach `dist/fonts/` kopiert) |
| Vite Hot-File | Vor Production-Build immer `npm run dev:stop &&` ausführen |
| IIFE Init | `new ClassName()` immer ans **Ende** des IIFE – nie vor Prototype-Definitionen |
| Helper in Templates | Hilfsfunktionen nie im inkludierten Template definieren – `Cannot redeclare`-Fehler |
| Shortcode-Syntax | Immer **einzeilig** – mehrzeilige Syntax bricht Attribut-Parsing |
| CPT Slugs | Registrierungs-Slug und Query-Slug müssen exakt übereinstimmen |
| Swiper CSS | Swiper injiziert CSS via JS → `_swiper-overrides.scss` zuletzt in `style.scss` laden |
| Google Maps URLs | Nur `google.com/maps/embed?pb=...` verwenden – keine Shortlinks (`maps.app.goo.gl`) |
| ACF Feldgruppen | Programmatisch via PHP registrieren (nie nur in der DB) – Version Control-fähig |

---

## Dokumentation

| Dokument | Inhalt |
|---|---|
| [docs/02_INSTALLATION.md](docs/02_INSTALLATION.md) | Vollständige Installationsanleitung |
| [docs/03_PLUGINS.md](docs/03_PLUGINS.md) | Plugin-Übersicht & Konfiguration |
| [docs/04_SHORTCODES.md](docs/04_SHORTCODES.md) | Shortcode-Referenz |
| [docs/05_AJAX-FEATURES.md](docs/05_AJAX-FEATURES.md) | AJAX Search, Filter, Load More |
| [docs/06_DEVELOPMENT.md](docs/06_DEVELOPMENT.md) | Entwicklungs-Guide |
| [docs/07_TROUBLESHOOTING.md](docs/07_TROUBLESHOOTING.md) | Fehlerbehebung |
| [docs/09_ACF-FIELDS.md](docs/09_ACF-FIELDS.md) | ACF-Feldgruppen-Referenz |
| [docs/10_DEPLOYMENT.md](docs/10_DEPLOYMENT.md) | Neues Kundenprojekt deployen |
| [CHANGELOG.md](CHANGELOG.md) | Versionshistorie |
| [WORKFLOW.md](WORKFLOW.md) | Git-Workflow & Branching |

---

## Versionshistorie (Auszug)

| Kit-Version | Komponente | Schwerpunkt |
|---|---|---|
| v1.22.5 | agency-core 1.17.5 | WP All Import: Timeout- & UA-Blocking-Fix für Bilder-Downloads |
| v1.18.0 | custom-theme 1.14.0 | Footer Legal Navigation, Agentur-Creditline |
| v1.17.0 | custom-theme 1.13.0 | WCAG 2.1 AA Audit – 11 Accessibility-Fixes |
| v1.16.0 | theme 1.12.0 / core 1.6.0 | 8 Custom Gutenberg Blocks (Design-Kategorie) |
| v1.15.0 | agency-core 1.5.0 | ACF Feldgruppen programmatisch via PHP |
| v1.14.0 | – | Vite Multi-Config Build, Stale Hot-File Fix |
| v1.13.0 | custom-theme 1.10.0 | 4-Ebenen Navigation, Footer Nav, Collision Detection |
| v1.12.0 | media-lab-seo 1.3.0 | SEO Meta-Tags, Open Graph, Schema.org, Sitemap |
| v1.11.0 | – | Dark Mode, Maintenance Mode |
| v1.10.0 | – | 404-Seite, Security-Fixes (Open Redirect, SQL, DB) |
| v1.9.0 | – | Performance: Code-Splitting, Lazy Loading |
| v1.8.0 | – | Security: Rate-Limiting, SMTP, HTTP-Header |
| v1.7.0 | – | Fullwidth-Helper, Design Tokens als CSS Custom Properties |
| v1.6.0 | – | Sass-Migration: 7-1-Architektur, Mixins, Breakpoints |
| v1.0.0 | – | Initiales Starter Kit Setup |

Vollständiger Changelog: [CHANGELOG.md](CHANGELOG.md)

---

## Lizenz

Proprietär – Media Lab Tritremmel GmbH  
Kontakt: [markus.tritremmel@media-lab.at](mailto:markus.tritremmel@media-lab.at)  
Website: [www.media-lab.at](https://www.media-lab.at)
