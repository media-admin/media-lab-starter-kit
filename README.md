# Media Lab Starter Kit

**Professional WordPress Agency Framework** – Modulares Plugin-System für skalierbare Kundenprojekte.

[![PHP](https://img.shields.io/badge/PHP-8.0+-purple.svg)](https://php.net)
[![WordPress](https://img.shields.io/badge/WordPress-6.0+-blue.svg)](https://wordpress.org)
[![License](https://img.shields.io/badge/license-proprietary-red.svg)](#lizenz)

---

## Übersicht

Vollständiges WordPress-Starter-Kit mit modularer Plugin-Architektur für Agentur-Workflows.
Entwickelt für Wartbarkeit, Sicherheit und schnelles Client-Deployment.

### Architektur-Prinzip

```
media-lab-agency-core     →  Wiederverwendbares Framework (nie modifizieren)
media-lab-seo              →  SEO-Toolkit (pro Projekt aktivieren + konfigurieren)
media-lab-woocommerce      →  WooCommerce-Erweiterungen (Catalog Mode, Konfigurator, Wunschliste, Filter)
media-lab-bookings         →  Standortbasiertes Buchungssystem
media-lab-events           →  Event-CPT mit Shortcodes
media-lab-backup           →  SFTP-Backup zur Hetzner Storage Box
media-lab-project-starter  →  Scaffold, wird pro Projekt dupliziert + individuell angepasst (siehe unten)
custom-theme                →  Präsentationsebene (pro Projekt anpassen)
```

---

## Komponenten & Versionen

Versionsnummern hier sind eine Momentaufnahme und driften erfahrungsgemäß
schnell auseinander (siehe `docs/BACKLOG.md` für mehrere Fälle, in denen
genau das passiert ist). **Verbindlich ist immer der `Version:`-Header der
jeweiligen Komponente selbst** (Plugin-Hauptdatei bzw. Theme `style.css`),
nicht diese Tabelle.

| Komponente                 | Version (Stand 13.08.2026) | Beschreibung                                    |
|-----------------------------|------------------------------|--------------------------------------------------|
| `media-lab-agency-core`     | `1.20.1`                    | Framework-Plugin (nicht modifizieren)            |
| `media-lab-seo`             | `1.9.1`                     | SEO-Toolkit + GSC/GA4-Dashboard + Report-Mailer  |
| `media-lab-woocommerce`     | `2.5.1`                     | WooCommerce-Erweiterungen                        |
| `media-lab-bookings`        | `1.7.0`                     | Buchungssystem                                   |
| `media-lab-events`          | `1.0.1`                     | Event-CPT + Shortcodes                           |
| `media-lab-backup`          | `1.3.4`                     | SFTP-Backup                                      |
| `media-lab-project-starter` | `1.0.0` (Scaffold)          | Vorlage, siehe eigener Abschnitt unten           |
| `custom-theme`              | `1.15.2`                    | Theme (Vite-Build, SCSS, JS)                     |

---

## `media-lab-project-starter` — Projekt-Scaffold

Anders als die übrigen Plugins wird `media-lab-project-starter` **nicht**
identisch über alle Kundenprojekte verteilt, sondern pro neuem Projekt
**dupliziert und individuell angepasst** (eigener CPT-/Taxonomie-/ACF-Bedarf
je Kunde). Enthält bewusst nur ein minimales Grundgerüst:

```
inc/custom-post-types.php   Projekt-spezifische CPTs (leer/Beispiel im Scaffold)
inc/taxonomies.php          Projekt-spezifische Taxonomien
inc/acf-config.php          Projekt-spezifische ACF-Felder
```

Benötigt `media-lab-agency-core` als aktives Plugin (Dependency-Check über
`medialab_core_version()`, siehe `inc/helpers.php` in Agency Core).

**Für neue Projekte:** Plugin-Ordner kopieren, umbenennen (Slug, Text
Domain, Konstanten-Präfix anpassen), dann projektspezifische CPTs/
Taxonomien/ACF-Felder darin aufbauen — nicht das Original-Scaffold direkt
für ein Kundenprojekt verwenden.

---

## Footer-Menüs

Das Theme registriert drei Menü-Locations (verifiziert gegen
`functions.php`):

| Location       | Beschreibung                                          |
|----------------|-------------------------------------------------------|
| `primary`      | Hauptnavigation                                       |
| `footer`       | Footer Navigation (oberer Footer-Bereich)             |
| `footer-legal` | Footer Legal (Impressum, Datenschutz, AGB, etc.)      |

---

## Projektstruktur

```
media-lab-starter-kit/
├── cms/
│   └── wp-content/
│       ├── plugins/
│       │   ├── media-lab-agency-core/       # Framework
│       │   ├── media-lab-seo/               # SEO-Toolkit
│       │   ├── media-lab-woocommerce/       # WooCommerce-Erweiterungen
│       │   ├── media-lab-bookings/          # Buchungssystem
│       │   ├── media-lab-events/            # Event-CPT
│       │   ├── media-lab-backup/            # SFTP-Backup
│       │   └── media-lab-project-starter/   # Scaffold (pro Projekt duplizieren)
│       └── themes/
│           └── custom-theme/                # Theme
│               ├── assets/src/scss/         # SCSS + Design-Tokens
│               ├── assets/src/js/           # JS-Komponenten
│               ├── assets/dist/             # Build-Output (nicht committen)
│               ├── inc/                     # PHP-Helpers (ACF, Enqueue, etc.)
│               └── template-parts/          # Wiederverwendbare Template-Teile
├── docs/                                    # Dokumentation
├── scripts/                                 # Deploy-/Backup-/Heartbeat-Scripts
├── vite.config.js                           # Theme-Build
├── vite.config.blocks.js                    # Block-Build (Blocks liegen in media-lab-agency-core/blocks/, nicht im Theme)
├── package.json
└── .gitignore
```

> **Hinweis Gutenberg-Blocks:** Die 8 Custom-Blocks (Hero, Testimonial,
> Team-Mitglied, Logo-Leiste, Logo-Slider, CTA-Banner, Accordion/FAQ,
> Icon+Text) sind Teil von `media-lab-agency-core` (`inc/blocks.php`,
> `blocks/{name}/`), nicht des Themes — siehe
> [docs/03_PLUGINS.md](docs/03_PLUGINS.md) für die vollständige Referenz.
> `vite.config.blocks.js` baut die JS-Assets dafür, das PHP-seitige
> Registrieren passiert im Plugin.

---

## Build-System

```bash
# Entwicklung starten
npm run dev

# Produktions-Build
npm run build

# Dev-Server stoppen
npm run dev:stop
```

Zwei separate Vite-Configs werden via `npm run build` nacheinander ausgeführt:
- `vite.config.js` → Theme-Assets (SCSS, JS)
- `vite.config.blocks.js` → Gutenberg-Block-Assets (für `media-lab-agency-core`, siehe Hinweis oben)

---

## Dokumentation

| Dokument                     | Inhalt                              |
|------------------------------|--------------------------------------|
| [docs/02_INSTALLATION.md]    | Vollständige Installationsanleitung  |
| [docs/03_PLUGINS.md]         | Plugin-Referenz                      |
| [docs/12_ANALYTICS.md]       | Analytics-Dokumentation              |
| [docs/13_SEO.md]             | SEO-Toolkit-Dokumentation            |
| [docs/14_BOOKINGS.md]        | Bookings-Plugin-Dokumentation        |
| [docs/06_DEVELOPMENT.md]     | Entwicklungs-Guide                   |
| [docs/07_TROUBLESHOOTING.md] | Fehlerbehebung                       |
| [docs/09_ACF-FIELDS.md]      | ACF-Felder-Referenz                  |
| [docs/BACKLOG.md]            | Offene Punkte & Änderungshistorie größerer Aufräum-Sessions |

**Versionshistorie:** Es gibt bewusst **keine** projektweite Changelog-
Tabelle mehr in dieser README. Jede Komponente pflegt ihre eigene
`CHANGELOG.md` (bzw. bei `media-lab-agency-core` aktuell noch inline in
dessen README) — eine zusätzliche, hier duplizierte Versionshistorie lief
in der Vergangenheit mehrfach aus dem Takt mit den tatsächlichen
Komponenten-Versionen (siehe `docs/BACKLOG.md`).

---

## Lizenz

Proprietär – Media Lab Tritremmel GmbH
Kontakt: [markus.tritremmel@media-lab.at](mailto:markus.tritremmel@media-lab.at)
Website: [www.media-lab.at](https://www.media-lab.at)
