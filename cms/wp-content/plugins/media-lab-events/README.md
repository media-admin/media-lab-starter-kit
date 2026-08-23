# Media Lab Events

Schlankes Event-Management-Plugin für WordPress: Custom Post Type,
Event-Kategorien, ACF-Felder für Datum/Ort/Preis und zwei Shortcodes für
Raster-Ansicht und Detail-Meta-Block.

Entwickelt von [Media Lab Tritremmel GmbH](https://media-lab.at).

---

## Features

- **Custom Post Type `event`** — eigenes Icon im Menü, Archiv aktiv (`/events/`), Gutenberg/REST-API aktiv, unterstützt Titel/Editor/Beitragsbild/Excerpt
- **Taxonomy `event_category`** — hierarchisch, eigenes Archiv (`/event-category/`)
- **ACF-Feldgruppe „Event Details"**: Startdatum (Pflicht), Enddatum (optional), Ort, Preis (Freitext — leer lassen für kein Preis-Badge)
- **Shortcode `[events_grid]`** — Raster-Ansicht mit Bild, Kategorien, Datum, Ort, Preis-Badge und Excerpt; filterbar nach Kategorie, mit/ohne vergangene Events, sortier- und limitierbar
- **Shortcode `[event_detail]`** — reiner Meta-Block (Datum/Ort/Preis) für die Verwendung in Theme-Templates

---

## Voraussetzungen

- WordPress (Mindestversion nicht im Plugin-Header angegeben)
- Advanced Custom Fields Pro (für die Event-Details-Feldgruppe)


## Dependencies

Keine Composer-Abhängigkeiten — kein `composer.json`/`composer.lock`
vorhanden. Reines Hook-/ACF-basiertes PHP ohne externe Bibliotheken.
(Ausnahme im Starter Kit: `media-lab-backup`, das phpseclib3 für
SSH-Key-Auth benötigt.)

---

## Installation

1. Upload nach `/wp-content/plugins/media-lab-events/`
2. Im WordPress-Backend aktivieren
3. Events anlegen: **Events → Neu**, Startdatum ist Pflichtfeld
4. Optional: Event-Kategorien anlegen unter **Events → Event-Kategorien**
5. Raster irgendwo einbinden: `[events_grid]`

---

## Shortcode: `[events_grid]`

```
[events_grid]
[events_grid columns="3" limit="6" category="workshop" show_past="false"]
[events_grid category="workshop,konzert" order="DESC"]
```

| Attribut | Standard | Beschreibung |
|---|---|---|
| `columns` | `3` | Nur als CSS-Klasse `events-grid--columns-{n}` gesetzt — das Grid-Layout selbst muss im Theme-CSS definiert werden, das Plugin liefert kein eigenes Grid-CSS |
| `limit` | `6` | Anzahl angezeigter Events |
| `category` | *(leer)* | Ein oder mehrere Kategorie-Slugs, kommagetrennt |
| `show_past` | `false` | `true` zeigt auch bereits vergangene Events (Vergleich gegen Startdatum) |
| `orderby` | `event_date_start` | Aktuell nicht als eigener Parameter ausgewertet — Sortierung läuft im Code fest über `event_date_start`, siehe Hinweis unten |
| `order` | `ASC` | `ASC` oder `DESC` |

> **Hinweis:** Der `orderby`-Parameter wird zwar über `shortcode_atts()`
> entgegengenommen, im `WP_Query`-Aufbau aber nicht ausgewertet — es wird
> immer nach `event_date_start` sortiert, unabhängig vom übergebenen Wert.
> Für andere Sortierfelder müsste `inc/shortcodes.php` erweitert werden.

Pro Event-Karte: Beitragsbild (falls vorhanden) mit Preis-Badge, Kategorien,
Titel (verlinkt), Datum (Start – Ende), Ort, Excerpt (falls gesetzt),
"Details ansehen"-Link.

---

## Shortcode: `[event_detail]`

```
[event_detail]
[event_detail id="123"]
```

Ohne `id`-Attribut wird die aktuelle Post-ID verwendet (`get_the_ID()`) —
gedacht für die Einbindung innerhalb der Single-Event-Template-Ausgabe.
Zeigt Datum, Ort und Preis als einfachen Meta-Block, jeweils nur wenn das
entsprechende Feld gesetzt ist.

---

## Bekannte Einschränkungen

- **Kein eigenes Grid-CSS.** `columns`-Attribut setzt nur die CSS-Klasse,
  das tatsächliche Spalten-Layout muss im Theme definiert werden.
- **`orderby`-Parameter wird ignoriert** (siehe Hinweis oben) — Sortierung
  ist fest auf `event_date_start` verdrahtet.
- **Keine Wiederkehrende-Events-Funktion.** Jedes Event ist ein
  eigenständiger Post, kein Serien-/Wiederholungs-Mechanismus.
- **Kein iCal-Export/-Feed** (anders als `media-lab-bookings`).

---

## Changelog

Siehe [CHANGELOG.md](./CHANGELOG.md) für die vollständige Versionshistorie.
Aktuelle Version: siehe `Version:`-Header in `media-lab-events.php`.

**Wichtig für Projekte, die vor 1.0.1 auf diesem Plugin aufbauen:** 1.0.1
behebt einen Sortier-/Filter-Bug bei Events (siehe CHANGELOG) durch eine
Änderung am `return_format` des ACF-Datumsfelds. Das erfordert **keine**
Datenmigration (ACF speichert das Datum ohnehin immer im selben internen
Format), reines Code-Update reicht.

---

## Lizenz

Keine Lizenzangabe im Plugin-Header gefunden — vor externer Weitergabe klären.
