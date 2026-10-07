# Changelog — Custom Theme

Alle wesentlichen Änderungen werden in dieser Datei dokumentiert.
Format: [Keep a Changelog](https://keepachangelog.com/de/1.0.0/)
Versionierung: [Semantic Versioning](https://semver.org/)

**Hinweis zur Vollständigkeit:** Diese Datei existiert seit 22.08.2026.
Ältere Versionshistorie (vor `1.15.3`) wurde **bewusst nicht
rekonstruiert** (siehe `docs/BACKLOG.md`, „Struktur/Prozess" — anders als
bei `media-lab-agency-core` wurde hier keine aufwendige Git-Log-/Tag-
Auswertung gemacht). Bei Bedarf nachträglich über
`git log --follow -- cms/wp-content/themes/custom-theme/style.css`
möglich.

---

## [Unreleased]

### Added
- `woocommerce/_single-product.scss`: Einzelprodukt-Layout (Galerie und Summary nebeneinander), Linien-Lupe, Rezensionen mit SVG-Sternen und Theme-Formularfeldern, Produktkarten (Marke, Verfuegbarkeit, Herz ueber dem Bild), Anfrage-Mengenfeld und Textbutton
- `functions.php`: Standard-Filter fuer `media-lab-woocommerce` ab 2.10.0
- `_single-product.scss`: Variationsformular im Theme-Stil (Catalog-Mode-Variantenauswahl), Meta-Zeile ordnet Marke, Kategorie und Artikelnummer per `order`
- **Ajax-Suche: Startertext, Mindestzeichen-Hinweis und „Alle Ergebnisse"-Link**
  (`assets/src/js/components/ajax-search.js`, `assets/src/scss/components/_ajax-search.scss` 1.3.0)
  – Startertext erscheint beim Fokus auf das leere Suchfeld, der Hinweis solange
  weniger Zeichen als das Minimum eingegeben sind; optionaler Link zur
  vollständigen Suchergebnisseite. Neue Klassen `.ajax-search__intro`,
  `.ajax-search__hint`, `.ajax-search__all`. Texte und Optionen werden in
  `media-lab-agency-core` unter Agency Core → Suche / Live-Suche gepflegt
  (ab Plugin 1.30.0).

### Changed
- **`ajax-search.js` liest seine Konfiguration aus `data-config`** (JSON am
  `.ajax-search`-Container, gerendert von `MediaLab_Search_Settings` im Plugin)
  statt Texte, Limit, Post-Types, Mindestzeichen (2) und Debounce (300 ms) fest
  einzubauen: Texte inkl. Post-Type-Labels, Anzeige-Optionen (Vorschaubild,
  Typ, Datum, Ausschnitt, Preis) und Seitensprache. Ohne `data-config`
  (Altmarkup) gelten dieselben Defaults wie bisher – Verhalten unverändert.
- Der AJAX-Request sendet zusätzlich `lang` (Seitensprache), damit das Plugin
  bei Polylang/WPML nur Treffer der aktuellen Sprache liefert.
- Fehlerantworten des Servers (z. B. Rate-Limit 429, ungültiger Nonce) zeigen
  den Fehlertext statt „Keine Ergebnisse gefunden."

### Fixed
- **Veraltete Antworten bei schnellem Tippen** (`ajax-search.js`) – eine spät
  eintreffende Antwort einer älteren Anfrage konnte die aktuelle Trefferliste
  überschreiben bzw. nach dem Leeren des Feldes wieder einblenden. Antworten
  werden jetzt per Request-Zähler verworfen, wenn sie nicht mehr zur letzten
  Anfrage gehören.
- **`alt`-Attribut der Treffer-Thumbnails enthielt HTML** (`ajax-search.js`) –
  der Titel kommt mit `<mark>`-Highlighting vom Server und wurde unverändert in
  das `alt`-Attribut übernommen (zerbrach das Markup, sobald ein Treffer
  hervorgehoben war). `alt` bekommt jetzt den reinen, escapten Text.

## [1.15.4] - 2026-08-22

### Added
- **Interne README.md komplett überarbeitet** — stand seit dem
  allerersten Release unverändert auf Version `1.0.0`
  (`style.css` war längst bei `1.15.x`). Neu: echte Requirements
  (WP 6.0+/PHP 8.0+ statt veralteter 5.9+/7.4+), echte Design-Tokens
  (`$color-primary: #e00000` statt der nie zutreffenden Platzhalter
  `#667eea`/`#764ba2`), repräsentative JS-Component-Liste, Verweise auf
  `docs/06_DEVELOPMENT.md` statt Duplikation. Richtigstellung: Es gibt
  **keinen** klassischen WordPress-Customizer für dieses Theme (kein
  `customize_register()`-Hook) — die alte README hatte fälschlich
  „Configure in Customizer" behauptet. Tatsächliche Anpassung läuft über
  SCSS-Tokens (Build-Zeit) bzw. ACF-Options-Seiten aus
  `media-lab-agency-core` (Laufzeit).

### Fixed
- **`CUSTOM_THEME_VERSION` war von `style.css` entkoppelt** (`functions.php`)
  – Konstante stand hartcodiert auf eingefrorenem `'1.4.0'`, während
  `style.css` (die für WordPress maßgebliche Versionsnummer) längst bei
  `1.15.3` stand. Praktisch folgenlos (nur Cache-Busting-Dekoration für
  das Haupt-JS, Vite nutzt ohnehin Content-Hashes im Dateinamen), aber
  irreführend beim Debuggen. Jetzt dynamisch über
  `wp_get_theme()->get('Version')` gezogen — kann nicht mehr aus dem
  Takt geraten.

---

## [1.15.3] - 2026-08-22

### Fixed
- **Modal-Komponente ließ sich nicht öffnen** (`assets/src/scss/components/_modal.scss`)
  – CSS zeigte das Modal nur bei Klasse `.is-active`, `modal.js` setzte
  aber konsequent `.is-open` (Öffnen, Schließen, ESC-Taste-Handler).
  Klick auf einen Trigger löste zwar korrekt `openModal()` aus, aber die
  Sichtbarkeits-Regel griff nie. Betraf jede Nutzung von
  `[modal_trigger]`/`[modal]` unabhängig vom Inhalt. SCSS-Selektor von
  `.is-active` auf `.is-open` umbenannt.

### Documentation
- **CF7-Layout-Helfer-Klassennamen präzisiert** (`docs/06_DEVELOPMENT.md`)
  – ein Formular nutzte `cf7-two-columns`/`cf7-full-width` statt der
  tatsächlich in `_contact-form-7.scss` definierten
  `cf7-grid-2`/`cf7-full`. Da die falschen Klassennamen im kompilierten
  CSS nicht existieren, fiel das Formular lautlos auf 1-spaltiges
  Block-Layout zurück (kein Fehler, keine Warnung). Warnhinweis mit den
  vier tatsächlich gültigen Klassennamen ergänzt.