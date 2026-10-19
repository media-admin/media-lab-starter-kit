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

## [1.18.7] - 2026-10-16

### Fixed
- **Weitere Scroll-Sperren am `body`** (Lightbox, Top-Header, Cookie-Dialog, Popups, Such-Overlay des Cores) bekamen dieselbe Ursache: `html { overflow-x: hidden; }` lässt den `overflow` des `body` nicht mehr auf den Viewport wirken. Sie werden jetzt per `html:has(body…)` auf `html` übertragen, der `body` bleibt dabei kein Scroll-Container, damit der sticky Header klebt (`_hero-slider.scss`)
- **Mobil-Menü, Scroll-Sperre:** `navigation.js` setzte beim Öffnen `overflow: hidden` inline am `body`. Seit `html` selbst `overflow-x: hidden` hat (1.18.5), macht das den `body` zum Scroll-Container: Der sticky Header klebte bei offenem Menü nicht mehr (nach dem Scrollen weg, nur der Theme-Switcher blieb), und der Hintergrund ließ sich weiter scrollen. Jetzt setzt `navigation.js` die Klasse `is-menu-open` an `html`, `html.is-menu-open { overflow: hidden; }` sperrt am Viewport (`navigation.js`, `_navigation.scss`)

## [1.18.6] - 2026-10-16

### Fixed
- **Suche, Suchfeld am Desktop:** Das Suchfeld im Seitenkopf und im Leer-Zustand blieb so breit wie ein Standard-Input (ca. 250 px), weil das `<label>` als Flex-Item nicht wuchs. Das Label wächst jetzt, das Feld füllt den Platz neben dem Button (Seitenkopf bis 560 px Formularbreite, Leer-Zustand bis 520 px Feldbreite) (`_search-results.scss`)
- **Suche, Leer-Zustand mobil:** Der Button „Suchen“ lief aus dem Bildschirm (eigenes Formular `.archive-empty__form`, Feld bis 320 px, `<label>` als Flex-Item). Das Feld nutzt jetzt die volle Breite, der Button ist mobil nur visuell ausgeblendet (`_search-results.scss`)
- **WooCommerce-Pagination:** Die Regel `nav.woocommerce-pagination ul li a, … li span` galt auch für die Spans innerhalb der Pfeil-Links (Pfeil und Screenreader-Text), der Pfeil bekam einen Rahmen im Rahmen und einen leeren Kasten daneben. Die Selektoren gelten jetzt nur für direkte Kinder (`li > a`, `li > span`)
- **Suche mobil:** Der Suchbegriff steht in der Überschrift in einer eigenen Zeile, die Anführungszeichen gehören dazu (`search.php`). Das Suchfeld im Seitenkopf nutzt die volle Breite (WordPress rendert es in einem `<label>`, das Label war das Flex-Item und blieb so breit wie ein Standard-Input), der Text beginnt links mit `$spacing-2` statt `$spacing-4`
- **Suchfelder:** Das Löschen-Kreuz (WebKit, `type="search"`) erscheint in der Textfarbe statt im Browser-Blau (`_search-results.scss`)

## [1.18.5] - 2026-10-16

### Fixed
- **Sticky Header (alle Breiten):** `html, body { overflow-x: hidden; }` in `components/_hero-slider.scss` machte den `body` zum Scroll-Container, deshalb klebte der sticky Header nie (gemessen: `position: sticky`, nach dem Scrollen trotzdem 1325 px über dem Bildschirm). Jetzt `html { overflow-x: hidden; }` und `body { overflow-x: clip; }`, dazu `html { scroll-padding-top: var(--header-height, 0px); }`, damit Anker-Sprünge nicht unter dem Header landen. Projekte mit eigener Kopie der Regel haben denselben Fehler

## [1.18.4] - 2026-10-16

### Changed
- **Mobiles Menü: Such-Auslöser und Wunschliste** erscheinen als Textzeilen wie die übrigen Einträge (Höhe 3 rem, gleicher Einzug und gleiche Schrift), unabhängig davon, ob das Plugin Icon oder Text rendert. Ohne Label zeigt der Such-Auslöser den Text seines `aria-label`, die Wunschliste ihren `aria-label` mit dem Zähler als Badge rechts (`_navigation.scss`). Desktop unverändert

## [1.18.3] - 2026-10-16

### Changed
- **Mobiler Header (unter 992 px, Burger statt Desktop-Menü):** flach (Mindesthöhe 4rem statt bisher ca. 140 px durch doppeltes Padding) und sticky. `--header-height` wird weiterhin per Skript aus der echten Höhe gesetzt, Hero-Offset, Menü und Theme-Switcher folgen automatisch. Pro Projekt überschreibbar mit `--header-mobile-height` und `--header-mobile-position` (`relative` schaltet das Mitscrollen ab) (`_navigation.scss`)
- **Mobiles Menü kompakt:** Zeilenhöhe 48 px (Untermenüs 44 px, WCAG 2.5.5) statt ca. 90 px, kleinerer Rahmen, Höhe `100dvh`, `overscroll-behavior: contain`. Scrollen bleibt als Rückfall bei vielen Einträgen
- **Theme-Switcher mobil:** unter 992 px links neben dem Burger im Header (44 px, mittig zur Header-Höhe, über dem Header), statt darunter zu schweben (`_theme-toggle.scss`). Position pro Projekt mit `--theme-toggle-mobile-top` und `--theme-toggle-mobile-right` anpassbar. Desktop unverändert

### Fixed
- **Mobiles Menü:** Untermenüs werden angezeigt, sobald der Listenpunkt `.is-open` trägt (`.menu-item-has-children.is-open > .sub-menu`). `navigation.js` setzt die Klasse am Listenpunkt, das CSS erwartete sie am `.sub-menu`
- **Logo mobil:** Ein langer Seitenname läuft nicht mehr unter Burger und Theme-Switcher, er wird mit „…" gekürzt (`.site-logo` reserviert den Platz)
- **Suche mobil:** Das Suchfeld bekam keine `min-width: 0` und schob den Button „Suchen" aus dem Bildschirm (`serp-search-form`). Im Seitenkopf der Suche ist der Button unter 768 px nur noch visuell ausgeblendet (Enter und Screenreader funktionieren weiter), das Feld nutzt die volle Breite
- **Suche mit Produktfiltern:** Weniger Abstand zwischen Seitenkopf und Filterleiste (unter 992 px), mehr Abstand über der Trefferanzeige und über dem ersten Ergebnis, die Trefferanzeige schließt unter 992 px bündig mit dem Schalter „Filter" ab (`_search-results.scss`)

### Hinweis
- Projekte mit Hero direkt unter dem Header: nach dem Abgleich mobil prüfen, der Header ist jetzt kürzer und sticky. Mitscrollen abschalten: `:root { --header-mobile-position: relative; }`

## [1.18.2] - 2026-10-16

### Added
- `ajax-search.js`, `_ajax-search.scss`: optionale Treffer-Felder `availability_badge`, `product_badge_label` und `product_badge_class` (Badge unter dem Preis, z. B. von einem Projekt-Plugin über den Filter `media_lab_ajax_search_result`). Ohne die Felder ändert sich nichts. Abgeleitet aus dem Stand in at.media-lab

### Fixed
- Suche: Der Schalter „Filter" der Produktfilter (`.wc-filter-bar__toggle`, Markup aus media-lab-woocommerce ab 2.17.0) hatte keine Stile und erschien auf dem Desktop als ungestylter Knopf. Unterhalb von 992 px klappt er die Filtergruppen jetzt auf (mit Zähler der aktiven Filter und Pfeil), ab 992 px ist er ausgeblendet und die Gruppen stehen als Pills neben der Sortierung (`_search-results.scss`, abgeleitet aus juwelier-janecka)

## [1.18.1] - 2026-10-15

### Added
- Standard-Styles für die Shop-Modus-Wunschlisten-Tabelle (`_wishlist.scss`): Tabelle, Auswahl-Zelle mit Checkbox und Entfernen-Button, Karten-Ansicht unter 1100px (Zellen mit `data-label`-Präfix), Teilen-Zeile im Fußbereich (`.mlw-wishlist__share--footer`) und Modifier `.mlw-wishlist-message--warning`. Abgeleitet aus dem Stand in juwelier-janecka

### Hinweis
- Gehört zu `media-lab-woocommerce` 2.19.1 (Markup in `templates/wishlist/item-row-shop.php` und `page.php`)

## [1.18.0] - 2026-10-14

### Changed
- **`mlwf-filters.js`: Unterkategorie-Filter als Mehrfachauswahl** –
  `collectFormData()` sammelt `subcat[]` als Liste, `updateUrl()` schreibt
  `?subcat=a,b` in die URL, `syncFromUrl()` stellt die Haken beim Laden wieder
  her, `updateActiveCount()` und `hasActiveFilters()` berücksichtigen die
  Unterkategorien (Zähler, „Zurücksetzen“).

### Hinweis
- Gehört zu `media-lab-woocommerce` 2.19.0 (Markup `subcat[]` und AJAX-Handler).

## [1.17.0] - 2026-10-13

### Added
- `ajax-search.js`: Typ-Labels „Kategorie“ (`product_cat`) und „Marke“ (`product_brand`) für Treffer ohne Post-ID, `_ajax-search.scss`: `.ajax-search__count` (Produktanzahl hinter dem Namen)
- `search.php`: Zeile „Passende Kategorien und Marken“ unter dem Suchfeld, wenn das WooCommerce-Plugin (ab 2.18.0) sie liefert (`mlw_get_search_term_links()`); `_search-results.scss`: `.search-terms`

## [1.16.0] - 2026-10-13

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
- **`post-card.php`: eigene Meta-Angaben im Footer** (`post_card_meta`) – Liste aus
  `[ 'label' => …, 'value' => … ]` oder Strings, z. B. Artikelnummer und Verfügbarkeit statt Datum;
  leere Werte werden übersprungen, abwärtskompatibel (`_cards.scss`: `.post-card__meta-item`,
  `__meta-label`, `__meta-value`).
- **`result-card.php`: Filter für projektspezifische Suchkarten** – `ml_search_card_meta`
  (Meta-Angaben je Inhaltstyp, ersetzt das Datum) und `ml_search_card_link_label` (Link-Text je
  Inhaltstyp). Dadurch braucht es keine eigene `card-{post_type}.php` mehr, wenn nur Meta oder
  Link-Text abweichen.
- **`comments.php`** (neu) – Kommentar-Liste und -Formular im Theme-Stil (Avatar links, Text rechts,
  getrennte Einträge, Theme-Formularfelder wie die WooCommerce-Rezensionen), Styles in
  `templates/_comments.scss` (neu, in `style.scss` einbinden). Verschachtelte Antworten,
  Kommentar-Paginierung, Freischaltungs-Hinweis.
- `search.php`: Produktfilter und Trefferanzeige über den Ergebnissen, wenn das WooCommerce-Plugin aktiv ist (`mlwf_get_search_filter_bar()`, media-lab-woocommerce 2.17.0). Die Ergebnisse stehen dann in `.wc-products-container`, damit das Filter-JS sie per AJAX austauschen kann; der Wrapper bekommt die Klasse `search-layout--filters`. Die Sortier-Auswahl des Cores entfällt in dem Fall (die Filterleiste hat eine eigene)
- `search.php`: Filter `ml_search_pagination_args` für die Argumente der Pagination (Pfeiltexte, `end_size`, `mid_size`), ohne das Template zu ändern
- `mlwf-filters.js`: Such-Kontext. Der Suchbegriff geht bei jedem Request mit, die Preisspanne gilt für die gefundenen Produkte, Filter stehen mit Präfix `f_` in der URL (`?s=ring&f_product_cat=…`), beim Neuladen werden die Checkboxen gesetzt, „Zurücksetzen" lädt die ungefilterte Ergebnisseite. Trefferanzeige (`.js-product-count`, Text aus `data-range`) und Zähler im Seitenkopf (`.js-search-count`) werden nach dem Filtern aktualisiert
- `assets/src/js/components/mlwf-filters.js`: interaktive Filter-Bar für WooCommerce (Dropdowns, Preis-Slider, AJAX), portiert aus Janecka; eingebunden über `main.js`
- `assets/src/scss/woocommerce/_woocommerce.scss`, `style.scss`: Styling der Filter-Bar (Tokens, Breakpoints), noUiSlider-CSS über die Sass-Pipeline. `package.json` und `package-lock.json` wurden im selben Commit angepasst
- `footer.php`: 4 Zeilen für das neue Plugin `media-lab-ai-agent`

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
- `_ajax-search.scss`: Farben über Theme-Tokens (Dark Mode), Meldungen „Keine Ergebnisse" und Fehler im Ergebnis-Container statt absolut positioniert, `:focus-visible`, Höhe des Dropdowns an den Viewport gekoppelt
- `mlwf-filters.js`: `/page/N/` bleibt nach einem Filterwechsel nicht mehr in der URL stehen (auch im Shop)
- `woocommerce/_single-product.scss`: Begleitänderungen zur Variantenauswahl-Politur in media-lab-woocommerce 2.11.0 (siehe dort)
- `woocommerce/_single-product.scss`: drei Zeilen entfernt (Begleitänderung zur Wunschliste im WooCommerce-Plugin: Gesamtsumme unter dem Preis, Menge auf der Liste änderbar)

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
- **Ajax-Suche: Submit-Button auf WooCommerce-Seiten zu breit** (`woocommerce/_woocommerce.scss`) –
  die Regel `.woocommerce form button[type="submit"]` (Theme-Button-Look inkl. Padding) griff auch auf
  den runden Submit-Button im Such-Overlay und drückte ihn auf Shop- und Produktseiten breit.
  Der Selektor nimmt `.ajax-search__submit` jetzt aus (`:not(.ajax-search__submit)`).
- **`.post-card`-Footer: Meta-Angaben brachen mitten im Wert um** (`components/_cards.scss`) – bei
  eigenen Meta-Angaben (`post_card_meta`, z. B. Artikelnummer) blieb neben dem Link zu wenig Platz,
  sodass Werte wie die Artikelnummer zerrissen wurden. Meta-Angaben stehen jetzt untereinander in
  voller Breite, der Link folgt rechts darunter; Karten ohne eigene Meta-Angaben unverändert.
- `_archive.scss`: Die Pagination-Regeln galten für alle verschachtelten `a` und `span` und verzerrten Pfeile mit Screenreader-Text. Jetzt nur für direkte Kinder (`> a`, `> span`)
- `inc/woocommerce.php`, `woocommerce/_single-product.scss`: Die Standard-Sidebar erscheint nicht mehr unter WooCommerce-Archiven, dazu mehr Abstand unter den Produktlisten
- `inc/enqueue.php`: Die Version von `main.js` richtet sich nach der Änderungszeit der Datei statt nach der festen Theme-Version. Verhindert eine veraltete `main.js`, die auf gelöschte Chunks verweist
- `base/_global.scss`: Abstand über dem Seitentitel (`.entry-header`); Hero und Breadcrumbs bleiben unberührt
- `components/_wishlist.scss`: Formular auf der Wunschzettel-Seite mit Klasse, Abständen und schmalem Absenden-Button

### Hinweis zum Update

- Das Kit-Theme bringt das Grundstyling der Filterleiste mit (Shop), aber keine Regeln für die Leiste auf der Suche (`.wc-filter-bar--search`: Schalter „Filter" mobil, Zeilen neben der Sortierung). Projekte, die die Filter auf der Suche nutzen, ergänzen sie im Theme
- Projekt-Kopien von `mlwf-filters.js` aktualisieren, sonst stimmen die URLs der Filter nicht mehr mit dem Plugin überein

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