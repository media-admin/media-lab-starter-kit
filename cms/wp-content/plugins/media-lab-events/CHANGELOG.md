# Changelog

Alle wesentlichen Änderungen werden in dieser Datei dokumentiert.
Format basiert auf [Keep a Changelog](https://keepachangelog.com/de/1.0.0/),
Versionierung nach [Semantic Versioning](https://semver.org/lang/de/).

---

## [1.0.1] - 2026-08-13

### media-lab-events 1.0.1

#### Fixed

- **Kritisch: Event-Sortierung und "vergangene Events ausblenden"-Filter
  waren nicht zuverlässig chronologisch korrekt.** Das ACF-Feld
  `event_date_start` nutzte `return_format => 'd.m.Y H:i'` (Tag zuerst).
  `[events_grid]` sortierte und filterte dieses Feld aber als reinen
  String (`orderby => 'meta_value'` ohne Typ-Angabe, Vergleichswert im
  selben `d.m.Y H:i`-Format) — bei diesem Format ist String-Vergleich
  **nicht** chronologisch korrekt (Beispiel: `"01.03.2026"` gilt
  string-sortiert als "kleiner" als `"15.01.2026"`, obwohl der 15. Januar
  chronologisch früher liegt). Betraf jedes Projekt, das das Plugin
  nutzt, abhängig von der zufälligen Tag/Monat-Konstellation der
  jeweiligen Events.

  Fix:
  - `inc/acf.php`: `return_format` beider Datumsfelder
    (`event_date_start`, `event_date_end`) auf `Y-m-d H:i:s` geändert -
    als String korrekt chronologisch sortierbar, und identisch zu dem
    Format, in dem ACF `date_time_picker`-Werte ohnehin **immer** intern
    in der Datenbank speichert (unabhängig vom `return_format`) - daher
    **keine Datenmigration nötig**, nur `get_field()` liefert ab sofort
    ein anderes Format.
  - `inc/shortcodes.php`: `meta_type => 'DATETIME'` für Sortierung und
    Filter-Vergleich ergänzt (statt `type => 'CHAR'`), Vergleichswert für
    "vergangene Events ausblenden" auf `current_time('mysql')`
    umgestellt (passend zum jetzt korrekten `Y-m-d H:i:s`-Format).
  - `inc/shortcodes.php`: Frontend-Anzeige in `[events_grid]` und
    `[event_detail]` konvertiert das jetzt technische
    `Y-m-d H:i:s`-Format über `date_i18n('d.m.Y H:i', strtotime(...))`
    zurück ins gewohnte deutsche Anzeigeformat - visuell keine Änderung
    für Website-Besucher.

---

## [1.0.0] - 2026-02-21

### media-lab-events 1.0.0

#### Added

- Initiales Release
- Custom Post Type `event` (Slug `events`, Archiv aktiv, REST-API aktiv)
- Taxonomy `event_category` (hierarchisch, Slug `event-category`)
- ACF-Feldgruppe „Event Details": Startdatum (Pflicht), Enddatum
  (optional), Ort, Preis (Freitext, leer = kein Preis-Badge)
- Shortcode `[events_grid]` - Raster-Ansicht mit Spalten-/Limit-/
  Kategorie-/Filter-Parametern, Bild, Kategorien, Datum, Ort, Preis-Badge,
  Excerpt
- Shortcode `[event_detail]` - Meta-Block (Datum, Ort, Preis) für
  Verwendung in Theme-Templates
