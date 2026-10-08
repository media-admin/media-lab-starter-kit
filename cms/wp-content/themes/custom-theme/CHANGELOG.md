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
- **Suchergebnisseite: Sortier-Leiste** (`.search-toolbar` / `.search-sort`) und Grid-Modifier
  `.post-grid--cols-2` / `--cols-4` / `--list` (`_search-results.scss` 2.0.0, generisch – gehören
  langfristig nach `_archive.scss`).
- **`template-parts/search/result-card.php`**: bereitet einen Treffer auf (Typ-Badge, Preis,
  Kontext-Ausschnitt, Hervorhebung, „Attribut: Wert") und rendert ihn über die Post-Card. Eigene
  Karte pro Inhaltstyp über `template-parts/search/card-{post_type}.php`.
- **`post-card.php`: optionale Erweiterungen** (`post_card_badge`, `post_card_type`, `post_card_price`,
  `post_card_title_html`, `post_card_excerpt_html`, `post_card_link_label`, `post_card_show`) –
  vollständig abwärtskompatibel, Archiv und Load-More bleiben unverändert.
- **`comments.php`** (neu) – Kommentar-Liste und -Formular im Theme-Stil (Avatar links, Text rechts,
  getrennte Einträge, Theme-Formularfelder wie die WooCommerce-Rezensionen), Styles in
  `templates/_comments.scss` (neu, in `style.scss` einbinden). Verschachtelte Antworten,
  Kommentar-Paginierung, Freischaltungs-Hinweis.

### Changed
- **`.single-post-layout`: volle Container-Breite, linksbündig** (bisher 760 px zentriert) – wie
  Archive und Seiten. Lesebreite bei Bedarf über `$single-layout-max-width` (Standard `none`).
- **`archive.php`: Zähler mit dem Namen des Inhaltstyps** – statt immer „N Beiträge“ zeigen Archive
  (auch Taxonomie-Archive eigener Inhaltstypen) die Bezeichnung des Typs, z. B. „5 Leistungen“ /
  „1 Leistung“. Die Bezeichnungen sind frei wählbar und mehrsprachig: Agency Core → Suche / Live-Suche
  → Texte → „Bezeichnungen der Inhaltstypen“, Format `slug=Singular|Plural`
  (z. B. `team=Teammitglied|Teammitglieder`, ab media-lab-agency-core 1.33.0). Ohne Eintrag gelten die
  Labels des Inhaltstyps (`labels->singular_name` / `labels->name`), ohne Plugin ebenfalls.
- **`.post-card` konsolidiert** – war doppelt definiert (`components/_cards.scss` mit harten Farben
  und `templates/_archive.scss` token-basiert), das Ergebnis hing von der Lade-Reihenfolge ab.
  Jetzt einzige Quelle: `components/_cards.scss` (2.0.0), Dark-Mode-fähig, unterstützt beide
  Markup-Varianten (`post-card.php` und die Load-More/AJAX-Templates mit `__title a` /
  `__thumbnail img`). Mit umgezogen: Preis, statisches Badge, Typ-Farben (`$post-card-type-colors`),
  `<mark>`-Hervorhebung. `templates/_archive.scss` (1.3.0) enthält nur noch Header, Grid
  (inkl. `.post-grid--cols-2/--cols-4/--list`), Leer-Zustand und Pagination;
  `templates/_search-results.scss` (2.1.0) nur noch Such-Formular, Sortier-Leiste und
  Leer-Zustands-Details. Optik unverändert (entspricht dem bisher gerenderten Misch-Ergebnis).
- **`search.php` baut auf den Archiv-Bausteinen auf** (`.archive-layout`, `.archive-header`,
  `.post-grid`, `.post-card`, `.archive-pagination`, `.archive-empty`) statt auf einem eigenen
  Listen-Design. Layout, Spalten, Ergebnisse pro Seite, Sortierung und Texte kommen aus den
  Such-Einstellungen im Plugin (ab media-lab-agency-core 1.31.0); ohne Plugin gelten Standardwerte.
- **`_search-results.scss` auf 2.0.0 reduziert** – die Klassen `.search-page`, `.search-header*`,
  `.search-results-list`, `.search-result*` und `.search-empty*` entfallen. Projekte mit eigener
  `search.php` müssen auf die Archiv-Klassen umstellen.
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
- **„Deprecated: Theme ohne comments.php“ unter Einzelbeiträgen** – dem Theme fehlte eine `comments.php`,
  WordPress fiel auf die veraltete Kern-Datei zurück (inkl. ungestyltem Formular). Mit `comments.php` behoben.
- **`<mark>`-Hervorhebung zerriss Wörter** (z. B. „Arbeits jack e“) – seitliches Padding entfernt
  (Suchergebnis-Karten und Live-Suche, `_ajax-search.scss` 1.3.1).
- **Suchergebnisseite ohne Archiv-Styles** – `_search-results.scss` setzte die Archiv-Bausteine
  (`.archive-header`, `.post-grid`, `.post-card`, `.archive-empty`) voraus, `templates/_archive.scss`
  war aber nicht in `style.scss` eingebunden: kein Gap/Grid, Layout-Umschaltung (Raster/Liste)
  ohne Wirkung, weiße Karten mit unlesbarem Text im Dark Mode, nicht zentrierter Leer-Zustand.
  `_search-results.scss` (2.0.1) lädt `archive` jetzt selbst per `@use 'archive'` (Sass lädt Module
  nur einmal, keine Dopplung). **Hinweis:** Auch `archive.php` (Kategorie-/Tag-Archive) hing an
  demselben fehlenden Import.
- **Suchergebnisse ohne Vorschaubild hatten nur eine schmale Textspalte** – das alte
  `.search-result`-Grid reservierte fest 200 px für das Bild, auch wenn keines vorhanden war.
  Entfällt mit der Post-Card (Bild optional, Inhalt nutzt die volle Breite).
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
