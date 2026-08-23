# Changelog

Alle wesentlichen Änderungen werden in dieser Datei dokumentiert.
Format basiert auf [Keep a Changelog](https://keepachangelog.com/de/1.0.0/),
Versionierung nach [Semantic Versioning](https://semver.org/lang/de/).

## [2.7.0] – 2026-08-23

### Neu: Konfigurierbare & mehrsprachige Filter-Labels
- Neue Optionsseite *Produkte → Filter-Einstellungen* (`inc/filters/class-settings.php`):
  Preis-/Kategorie-/Marke-/Zurücksetzen-Label, Attribut-Label-Overrides, je Sprache
  pflegbar (eigener, von der Inquiry-Engine entkoppelter Sprachen-Repeater +
  plugin-agnostische Spracherkennung, `inc/filters/class-i18n.php`, Polylang → WPML
  → WP-Locale-Fallback).
- `mlwf_get_attribute_labels()` nutzt jetzt Custom-Labels vor dem WC-nativen
  Attribut-Label.

### Neu: Konfigurator-Steps als Shop-Filter nutzbar (kuratierter Ansatz)
- `config_steps` (select/radio/checkbox/color_picker) können optional mit einem
  bestehenden WooCommerce-Attribut verknüpft werden (`use_as_filter` +
  `filter_attribute`, `inc/configurator/class-acf-fields.php`).
- `inc/configurator/filter-sync.php`: gleicht Options-Labels beim Produkt-Speichern
  mit Terms der gewählten Taxonomie ab (Matching per Label, Neuanlage falls nötig),
  schreibt die ermittelte term_id zusätzlich in `filter_term_id` zurück (Brücke,
  ohne das interne `value`-Feld anzufassen).
- `wp mlwf sync-configurator-filters [--dry-run]`: einmaliger Bulk-Sync für
  Bestandsprodukte, die vor diesem Feature gespeichert wurden.
- Wichtig: `value` (Konfigurator-intern) und Attribut-Term-Slug bleiben getrennte
  Bezeichner — künftige Verknüpfungen (z.B. Vorauswahl im Wizard) müssen explizit
  über das Label brücken.

### Fix: Produktfilter-Bar war nie mit dem Theme verdrahtet
- `mlwf_render_filter_bar()` existierte bereits vollständig, wurde aber nirgends
  gehookt. Jetzt selbst-registrierend an `woocommerce_before_shop_loop` (Prio 15) —
  funktioniert in jedem Projekt ohne Theme-seitige Verdrahtung.
- Neuer `.wc-products-container`-Wrapper um `ul.products` + Pagination
  (`woocommerce_before_shop_loop`/`_after_shop_loop`, Prio 20), Ziel für den
  AJAX-Ergebnisaustausch im Frontend-JS.

### Neu: Interaktive Filter-Bar (Frontend-JS + Styling)
- `assets/src/js/components/mlwf-filters.js`: portiert & generalisiert aus dem
  Janecka-Projekt (at.janecka-2026) — Action-Namen/Nonce jetzt dynamisch aus
  `window.mlwf` statt hartkodiert.
- Preis-Slider (noUISlider), Dropdown-Filtergruppen, AJAX-Filterung, URL-State,
  Pagination-Interception.
- SCSS ebenfalls aus Janecka portiert, an Starter-Kit-Breakpoints (`respond-to`)
  und Design-Tokens angepasst (Dark-Mode-sicher statt hartkodierter Farben);
  Fullwidth-Breakout entfernt, Bar liegt innerhalb `.container`.
- noUiSlider-CSS läuft über die Sass- statt JS-Pipeline (`@use` in
  `_woocommerce.scss`, `--load-path=node_modules` in `build:css`) — Vite bindet
  aus JS importiertes CSS im Production-Build nicht automatisch ein.

---


## [2.6.0] - 2026-08-22

### Added
- **Wunschlisten-Button-API für Custom-Card-Themes** (`inc/wishlist/class-frontend.php`)
  – neue öffentliche Methode `render_button_html()` gibt das Button-HTML
  zurück statt es auszugeben. Zwei neue Filter
  `mlw_wishlist_auto_loop_button`/`mlw_wishlist_auto_single_button`
  (Default `true`, kein Breaking Change) erlauben Themes mit komplett
  selbst gebautem Produktkarten-Markup, die automatischen Hooks
  abzuschalten und den Button an eigener Stelle zu platzieren. Erster
  Baustein der TI-Wishlist-Migration bei `juwelier-janecka` (siehe
  docs/BACKLOG.md).
- **Wunschlisten-Sharing per Token-Link** (`inc/wishlist/class-share.php`,
  neue Tabelle `wp_mlw_shared_wishlists`) – Kunden und Gäste können ihre
  Wunschliste über einen dauerhaften Link mit Dritten teilen, nutzt
  `medialab_share()` aus agency-core für die Button-UI. Bei eingeloggten
  Kunden zeigt der Link immer den aktuellen Listenstand (Live-Load); bei
  Gästen wird ein Snapshot gespeichert (Session-Storage ist nicht
  dauerhaft genug für einen teilbaren Link), automatisches Cleanup nach
  90 Tagen (`mlw_wishlist_share_guest_retention_days`-Filter). Neue
  Read-Only-Ansicht (`templates/wishlist/shared.php`) für Empfänger,
  inkl. "In den Warenkorb"/"Produkt ansehen" pro Artikel.
- **Wunschlisten-Seiten-Templates** (`templates/wishlist/page.php`,
  `templates/wishlist/item-row.php`) – existierten bisher nicht,
  `[mlw_wishlist_page]` lief ins Leere. Item-Zeilen als Grid-Spalten
  (Bild/Details/Einzelpreis/Menge/Positionsgesamtpreis/Entfernen), die
  sich mit der Gesamtsumme-Zeile dasselbe Spalten-Raster teilen
  (`assets/src/scss/components/_wishlist.scss`), sodass
  Positionsgesamtpreise exakt über dem Gesamtbetrag ausgerichtet sind.
- **Hinweistext "Wunschliste leer" backend-steuerbar + mehrsprachig**
  (`inc/inquiry/class-settings.php`) – neuer Wording-Key `wishlist_empty`,
  volle Fallback-Kette wie alle anderen Wording-Felder (Sprache →
  einsprachiges Flat-Feld → Code-Default).

### Fixed
- **Preisformatierung driftete nach Ajax-Updates** (`inc/wishlist/class-storage.php`,
  `inc/wishlist/class-ajax.php`, `assets/js/wishlist.js`) – JS formatierte
  Preise nach Menge-Ändern/Entfernen selbst nach (hartcodiertes `€`,
  keine Rücksicht auf WooCommerce-Währungseinstellungen), wodurch die
  Darstellung vom initialen PHP-Render abweichen konnte. Server liefert
  jetzt fertig formatiertes `wc_price()`-HTML (`unit_price_html`,
  `line_total_html`, `grand_total_html`) mit, JS übernimmt es 1:1.

### Changed
- `inc/wishlist/class-storage.php::get_items_for_display()` akzeptiert
  jetzt optional eine explizite Item-Liste statt immer nur die des
  aktuellen Besuchers (nötig für die Token-Sharing-Ansicht). Neue
  öffentliche `get_items_for_user_id()`.

---

## [2.5.1] - 2026-08-12

### media-lab-woocommerce 2.5.1

Hotfix, unabhängig von den BACKLOG.md-Paketen entdeckt (beim Testen von
"Test - Step-Typen Abdeckung (Custom T-Shirt)").

#### Fixed

- **Kritisch: Jedes Produkt mit Konfigurator-Typ "Textilien" zeigte 0
  Konfigurationsschritte** (Wizard sprang direkt zur Zusammenfassung,
  "Schritt 1 von 1"), unabhängig davon wie viele Zeilen im
  "Konfigurationsschritte"-Repeater tatsächlich gepflegt waren.

  Ursache: `get_configuration_steps()` verzweigte bei `config_type ===
  'textile'` in `get_textile_steps()` - eine Methode, die die Steps über
  ein separates CPT-basiertes System laden sollte (Post-IDs in der
  `config_steps`-Postmeta erwartet). Diese CPT-Registrierung existiert im
  Plugin nirgends; die tatsächlichen Schritte werden für alle
  Konfigurator-Typen im ACF-Repeater `config_steps` direkt auf dem
  Produkt gespeichert. Ein roher `get_post_meta()`-Aufruf auf ein
  ACF-Repeater-Feld liefert aber nur die interne Zeilenanzahl als String
  (z.B. `"5"`) statt eines Arrays - der `is_array()`-Guard in
  `get_textile_steps()` schlug dadurch immer fehl, die Methode gab immer
  `array()` zurück.

  Fix: `get_configuration_steps()` lädt jetzt für alle Konfigurator-Typen
  konsistent aus dem ACF-Repeater. `get_textile_steps()` bleibt
  unverändert (privat, ungenutzt) im Code, falls das CPT-basierte System
  an anderer Stelle doch noch benötigt wird.

---

## [2.5.0] - 2026-08-12

### media-lab-woocommerce 2.5.0

Behebt den Staffelpreis-Doppelsteuer-Bug aus BACKLOG.md ("Paket C") - kritisch
für jedes Projekt mit Bruttopreis-Eingabe (`woocommerce_prices_include_tax = yes`,
üblich bei DACH-B2C-Shops).

#### Fixed

- **Kritisch: Staffelpreis-Tabelle im Konfigurator zeigte bei
  Bruttopreis-Eingabe einen zu hohen Preis/Stück.** Die Live-Preisvorschau
  (`get_breakdown()`) war bereits korrekt (siehe 2.1.0), aber
  `calculateTierPrice()` in `configurator.js` hat den client-seitig
  übergebenen `subtotal`-Wert weiterhin immer als netto behandelt und bei
  Bruttopreis-Anzeige selbst Steuer aufgeschlagen - obwohl `subtotal` bei
  Bruttopreis-Eingabe bereits brutto war. Ergebnis: Steuer wurde ein
  zweites Mal aufgeschlagen (Beispiel aus dem Backlog: 135€/Stück korrekt
  vs. 162€/Stück fälschlich angezeigt in der Tabelle).

  Umgesetzt wie im Backlog skizziert:
  - `class-price-calculator.php`: neue Methode `get_tiers_with_prices()`
    berechnet pro Preisstufe den korrekten Preis/Stück mit denselben
    `wc_get_price_excluding_tax()`/`wc_get_price_including_tax()`-Aufrufen
    wie `get_breakdown()` - keine geratene Netto/Brutto-Logik mehr.
  - `class-configurator.php`, `ajax_calculate_price()`: `price_breakdown`
    um `tiers_with_prices` ergänzt.
  - `configurator.js`, `calculateTierPrice()`: schlägt jetzt bevorzugt den
    vom Server gelieferten `unit_price` aus `tiers_with_prices` nach,
    alte client-seitige Berechnung bleibt nur noch als Fallback (falls
    `tiers_with_prices` einmal fehlen sollte, z.B. während einer
    Deploy-Übergangsphase).
  - `wizard.php`: `calculateTierPrice()`-Aufruf und `is-active`-Vergleich
    casten `min_quantity`/`discount_percent` jetzt explizit zu (int)/(float)
    beim Ausgeben in den JS-Kontext - verhindert einen stillen Fallback auf
    die alte Berechnung durch einen Typ-Mismatch beim `parseFloat()`-Vergleich
    in `configurator.js`.

---

## [2.4.0] - 2026-08-12

### media-lab-woocommerce 2.4.0

Kompatibilitäts-Ergänzungen aus BACKLOG.md ("Paket B") - Filter-System
kompatibler zu abweichenden Theme-Konventionen (Janecka-Merge), plus ein
generisches Sortier-Layout für die Filter-Bar.

#### Added

- **Sortier-Layout** (`inc/filters/filter-bar.php`): WooCommerce's native
  Sortierung (`woocommerce_catalog_ordering()`) wird jetzt standardmäßig in
  einem eigenen `.wc-filter-bar__groups-sort`-Container neben den
  Filter-Gruppen gerendert - abschaltbar per
  `add_filter( 'mlwf_filter_bar_show_sort', '__return_false' )`. Bewusst
  außerhalb von `.js-filter-form`, da `woocommerce_catalog_ordering()` ein
  eigenes `<form>`-Tag ausgibt (kein verschachteltes `<form>` möglich).
  **Follow-up offen:** volle AJAX-Integration (Sortierwechsel ohne
  Page-Reload) erfordert einen zusätzlichen Change-Handler in
  `ajax-filters.js` - lag bei diesem Release nicht vor.

#### Fixed

- **`inc/filters/ajax-handlers.php`:** Theme-Alias-Action `ajax_filter_posts`
  ergänzt - manche Theme-Konventionen (z.B. Janeckas Theme) senden
  AJAX-Filter-Requests unter diesem Namen statt
  `janecka_filter_products`/`mlwf_filter_products`. Rein additiver Alias,
  wirkungslos für Projekte, die den Namen nicht nutzen.
- **`inc/filters/ajax-handlers.php`:** Duale Nonce-Prüfung
  (`mlwf_filter_nonce` + `ajax_filters_nonce`) über neue
  `mlwf_verify_filter_nonce()`-Hilfsfunktion, angewendet in
  `mlwf_ajax_filter_products()` und `mlwf_ajax_get_price_range()`. Ohne
  Fallback: 403 bei jedem Filter-Request in Projekten, deren Theme den
  Nonce unter dem abweichenden Namen erzeugt.

---

## [2.3.0] - 2026-08-12

### media-lab-woocommerce 2.3.0

Bugfixes und ein neuer Shortcode, gefunden beim WooCommerce-Sync
`at.janecka-2026` ↔ Starter Kit (siehe BACKLOG.md, "Paket A" - vorbestehende
Bugs im Starter Kit selbst, unabhängig vom Janecka-Merge).

#### Added

- Neuer Shortcode `[mlw_inquiry_form]` (`inc/inquiry/class-shortcode.php`)
  macht `templates/inquiry-form.php` nutzbar - das Template war zuvor an
  keiner Stelle in `inc/` eingebunden (totes Template). Für Nutzung
  außerhalb des automatischen Catalog-Mode-Checkout-Flows, z.B. auf einer
  eigenen Landingpage. Unterstützt Theme-Override via
  `yourtheme/media-lab-woocommerce/inquiry-form.php`.
- ACF-Feldgruppe des Konfigurators: Step-Type `contact_form` als Auswahl
  ergänzt (`inc/configurator/class-acf-fields.php`) - wurde von
  `wizard.php`, `class-configurator.php` und `class-price-calculator.php`
  bereits aktiv unterstützt, war im Produkt-Editor aber nicht auswählbar

#### Fixed

- **`templates/inquiry-checkout.php`:** hartcodierter Steuersatz
  (`$tax_rate = 20`) durch echte `WC()->cart`-Steuerwerte
  (`get_subtotal()`/`get_subtotal_tax()`/`get_total()`) ersetzt -
  berücksichtigt jetzt Steuerklasse und Kundenland/-status statt eines
  fixen österreichischen Standardsatzes
- **`templates/configurator/fields/number.php`:** `isset()`-Bug behoben -
  ACF liefert bei leer gelassenem Feld einen leeren String statt `null`,
  wodurch der min/max-Fallback (1/10000) nie griff und die
  Mengenschalter (+/-) ohne gesetztes Maximum funktionslos waren
- **`templates/configurator/wizard.php`:** Wishlist-Button jetzt hinter
  `class_exists('MediaLab_Wishlist_Ajax')`-Guard - Projekte ohne
  `inc/wishlist/` (z.B. `at.janecka-2026`) zeigten zuvor einen sichtbaren,
  aber funktionslosen Button
- **`inc/inquiry/class-mail.php`:** Preisaufschlüsselung in der
  Anfrage-Mail um Zwischensumme (pro Stück), Menge, Zwischensumme vor
  Steuer und eine eigene MwSt.-Zeile erweitert - der angezeigte
  Gesamtbetrag war zuvor korrekt, aber für den Kunden nicht
  nachvollziehbar, wie er zustande kam

#### Known Issues / Follow-ups

- Kommentarverweis in `inc/catalog-mode.php`, der auf
  `templates/inquiry-form.php` zeigt, ist vermutlich fehlgeleitet und
  sollte im Zuge des neuen Shortcodes geprüft/korrigiert werden - Datei
  lag bei diesem Release nicht vor
- `templates/inquiry-form.php` selbst unverändert übernommen (kein
  eigener Fix, nur über den neuen Shortcode erreichbar)

---

## [2.2.0] - 2026-08-10

### media-lab-woocommerce 2.2.0

Kritische Bugfixes rund um bisher ungetestete Konfigurator-Step-Typen
(size_matrix, color_picker, checkbox, radio, textarea), Wunschlisten-
Übernahme aus dem Konfigurator, sowie zwei unabhängige Shop-Einstellungen.

#### Added

- Artikelnummer/SKU wird jetzt bei allen drei Inquiry-Quellen (Cart-Anfrage,
  Konfigurator, Wunschliste) sowohl in der Mail als auch auf der
  Wunschlisten-Seite angezeigt
- Kontaktdaten (Name/E-Mail/Telefon/Firma) UND alle textarea-Felder
  (z.B. "Besondere Wünsche") werden beim Übernehmen vom Konfigurator in die
  Wunschliste automatisch gemerkt und das Wunschlisten-Formular damit
  vorausgefüllt - bei mehreren textarea-Feldern werden diese zu einer
  Nachricht mit Label-Präfix kombiniert
- Eigene, unabhängig konfigurierbare und mehrsprachige Erfolgsmeldung für
  die Wunschliste (vorher geteilt mit Cart-/Konfigurator-Anfrage)
- Neues Feld "Produkte pro Seite" unter WooCommerce → Einstellungen →
  Produkte → Anzeige (`inc/shop-products-per-page.php`) - überschreibt den
  bisher im Theme hartcodierten Wert (12), wirkt auf Shop-/Kategorie-
  Übersichtsseiten und die native Produktsuche, unabhängig von der
  Inquiry-Engine nutzbar
- Client-seitige Validierung vor "Zur Zusammenfassung": prüft jetzt auch
  konfigurierte Pflicht-Zusatzfelder und die Datenschutz-Checkbox, nicht
  mehr nur Name/E-Mail
- Sanftes Scrollen zum Konfigurator-Anfang bei jedem Schrittwechsel

#### Fixed

- **Fataler Fehler `Call to undefined function wc_get_price_suffix()`** bei
  jeder Ajax-Preisberechnung und jedem Absenden (Anfrage & Wunschliste) -
  WooCommerce-Frontend-Funktionen sind bei reinen Ajax-Requests nicht
  zuverlässig geladen, Datei wird jetzt bei Bedarf gezielt nachgeladen
- Größen-Matrix-Aufpreise (z.B. XL/XXL) flossen nie in die Preisberechnung
  ein - mengengewichteter Durchschnittsaufpreis pro Stück ergänzt
- Wunschliste blieb nach Ajax-Hinzufügen leer: WooCommerce setzt seinen
  Session-Cookie bei reinen Ajax-Requests nicht zuverlässig, wird jetzt
  explizit erzwungen
- Wunschlisten-Einzelpreis nutzte versehentlich `price_breakdown['total']`
  (Gesamtpreis für die volle Konfigurations-Menge) statt `unit_price` -
  führte bei Produkten mit Mengen-/Größen-Matrix-Step zu einer doppelten
  Multiplikation und absurd hohen Anzeigepreisen
- Wunschlisten-Badge zeigte die Summe aller Mengen statt der Positionsanzahl
- `color_picker`-Auswahl zeigte den rohen Hex-Wert statt des Farbnamens
  (fehlte bei der Label-Auflösung)
- Platzhalterbild-Fallback fehlte im JS-Nachrendern der Wunschliste (nur im
  PHP-Template vorhanden) - führte zu einem kaputten Bild-Icon nach
  Mengen-Änderungen
- `foreach()`-Warnung bei Step-Typen ohne Optionen (`contact_form`,
  `file_upload`, `textarea`)
- Native Browser-Validierungs-Sprechblase (nicht stylbar, nicht
  mehrsprachig) durch `novalidate` + eigene, übersetzbare Validierung ersetzt

---

## [2.1.0] - 2026-08-07

### media-lab-woocommerce 2.1.0

Konfigurator-Feinschliff: korrekte Steuerberechnung über WooCommerce, Layout-Fixes,
Mengen-/Staffelpreis-UX, WP-Germanized-Integration.

#### Added

- Freier, mehrsprachiger „Preis-Hinweistext" in den Inquiry-Einstellungen (Wording/Sprachen-Tab)
- `inc/price-suffix.php` – globaler Hook in `woocommerce_get_price_suffix`, **nicht** an die Inquiry-Engine gekoppelt: nutzt die eigene mehrsprachige Einstellung, falls konfiguriert, sonst WooCommerce's natives „Preis-Anzeige-Suffix"-Feld (Einstellungen → Steuern) - funktioniert damit auch in Projekten ohne Inquiry-Engine
- WP-Germanized-Rechtshinweise (Steuer/Versandkosten) direkt unter der Live-Preisvorschau im Konfigurator, über die offiziellen WPG-Shortcodes (`gzd_product_tax_notice`, `gzd_product_shipping_notice`) statt WPGs Standard-Ausgabeposition
- `create-category-test-products.php` (WP-CLI-Skript) – 36 Testprodukte über 3 Kategorien (Drucksorten/Textilien/Give-aways), inkl. Mengen-Step für Staffelpreis-Tests

#### Changed

- `class-price-calculator.php`: komplette Preisberechnung auf WooCommerce's eigene Steuer-Funktionen (`wc_get_price_excluding_tax()`/`wc_get_price_including_tax()`) umgestellt statt hartcodierter 20 % - berücksichtigt jetzt Steuerklasse, `wc_prices_include_tax()` und die tatsächliche Shop-Steueranzeige-Einstellung (`woocommerce_tax_display_shop`)
- Konfigurator-Navigation: „Anfrage absenden" bekommt bei drei Buttons (Zurück/Wunschliste/Anfrage) eine eigene volle Zeile, statt mit den anderen um Platz zu konkurrieren
- Schritt-Zähler („Schritt X von Y") zählt den Zusammenfassungs-Schritt jetzt korrekt mit (Denominator `totalSteps + 1`)

#### Fixed

- **Layout-Kollaps der `.summary`-Spalte** auf Produktseiten ohne eigenes Bild: mehrere ineinandergreifende Ursachen gefunden und behoben - u.a. eine zu allgemeine theme-weite `[data-columns]`-Grid-Regel, die ungewollt auch auf die WooCommerce-Produktgalerie griff (kollidierte mit demselben, für andere Theme-Komponenten gedachten Attribut)
- Rezensionen wurden bei konfigurierbaren Produkten innerhalb von `.summary` ausgegeben (dadurch auf 50 % Breite gequetscht) - jetzt über einen eigenen Hook (`woocommerce_after_single_product_summary`) außerhalb davon
- `unit_price` in der Preisaufschlüsselung enthielt keine MwSt., während `total` sie enthielt - beide Werte widersprachen sich sichtbar bei Menge 1 (jetzt behoben, da beide über dieselbe WooCommerce-Steuerfunktion laufen)
- Native Browser-Spinner-Pfeile im Mengenfeld überlagerten die eigenen Plus/Minus-Buttons
- Zwei vorbestehende Bugs in meinem eigenen Testprodukt-Anlage-Skript: fehlender Mengen-Step (keine Staffelpreis-Anzeige testbar) und bei 10 Give-away-Produkten fehlender Kontaktdaten-Step (identischer Bug wie schon einmal beim Kinder-T-Shirt-Testprodukt gefunden)

---

## [2.0.0] - 2026-08-06

### media-lab-woocommerce 2.0.0

Große Erweiterung: zentrale **Inquiry-Engine** (gemeinsamer Kern für Cart-Anfrage,
Konfigurator-Anfrage und die neue **Wunschliste**) plus vollständiges
Wunschlisten-Feature inkl. Frontend, Mehrsprachigkeit und Navigations-Icon.

#### Added

**Inquiry-Engine (`inc/inquiry/`)**
- `class-cpt.php` – neuer CPT `mlw_inquiry` für alle Anfragen (Cart/Konfigurator/Wunschliste), eigene Stati (Offen/In Bearbeitung/Erledigt/Archiviert), Backend-Menü mit Quelle/Kanäle/Status-Spalten
- `class-settings.php` – zentrale ACF-Optionsseite: editierbare Formularfelder (Pflicht/Optional, projektspezifisch), Kanäle (E-Mail/WhatsApp/Webhook), editierbare Mail-Templates, Mehrsprachigkeit (siehe unten), Navigation (siehe unten)
- `class-i18n.php` – Sprach-Erkennung (Polylang → WPML → WP-Locale-Fallback), plugin-agnostisch
- `class-mail.php` – Platzhalter-Ersetzung, HTML-Wrapper, Produktlisten-Formatierung inkl. Konfigurator-Daten/Preisaufschlüsselung/Datei-Anhängen
- `class-channels.php` – Versand an aktive Kanäle (E-Mail, WhatsApp-Link, Webhook mit Secret-Header)
- `class-inquiry-engine.php` – zentrale `submit()`-Methode: Validierung (inkl. konfigurierbarer Pflichtfelder & Datenschutz-Zustimmung), CPT-Speicherung, Multi-Channel-Versand
- `class-upload-cleanup.php` – täglicher Cron, löscht ausschließlich explizit als "pending" getaggte, nie einer Anfrage zugeordnete Konfigurator-Uploads nach 30 Tagen

**Mehrsprachigkeit**
- Tab "Sprachen" in den Einstellungen: beliebig viele Sprachen, Wording/Mail-Templates/Datenschutztext pro Sprache
- Formularfeld-Labels/Placeholder/Optionen ebenfalls pro Sprache pflegbar (verschachtelter Repeater)
- Dreistufige Fallback-Kette: passende Sprache → erste konfigurierte Sprache → sprachneutrales Flat-Feld → Code-Default
- Vollständigkeits-Hinweis im Backend (welche Sprache hat noch fehlende Übersetzungen)

**Wunschliste (`inc/wishlist/`, `templates/wishlist/`)**
- `class-storage.php` – eigenständige Datenhaltung unabhängig vom `WC()->cart` (Gast: `WC()->session`, eingeloggt: User-Meta inkl. Login-Merge), unterstützt Konfigurator-Items (Konfiguration, Preisaufschlüsselung, Datei-Uploads)
- `class-ajax.php` – add/remove/update_qty/get/submit; Preis & Konfigurationsanzeige werden bei konfigurierbaren Produkten serverseitig neu berechnet (verhindert manipulierte Preise über den Client)
- `class-frontend.php` – Add-to-Wishlist-Buttons (Shop-Loop + Einzelproduktseite), Shortcode `[mlw_wishlist_page]`, optionales Wunschlisten-Icon im Hauptmenü (Desktop + Mobile automatisch) mit Mengen-Badge
- `class-enqueue.php` – Frontend-Assets, lokalisierte Daten (Nonce, Wording, i18n-Strings)
- `templates/wishlist/page.php` + `item-row.php` – Wunschlisten-Seite mit Menge ändern/entfernen, Einzelpreis, Zeilensumme, Gesamtsumme, Absende-Formular
- `assets/js/wishlist.js` – vollständige Frontend-Logik (Add/Remove/Menge/Absenden), Live-Neurendering ohne Reload
- Konfigurator-Wunschlisten-Abzweig: neuer Button "Zur Wunschliste hinzufügen" im Wizard neben "Anfrage senden"

**Navigation**
- Neue Einstellungen (Tab "Navigation" bzw. pro Sprache): Icon im Hauptmenü an/aus, Anzahl-Badge an/aus, Wunschlisten-Seite (pro Sprache wählbar, mit Fallback)

**Testdaten**
- `create-test-products.php` (WP-CLI-Skript) – legt ein einfaches und ein konfigurierbares Testprodukt an

#### Changed

- `inc/catalog-mode.php`: `handle_inquiry_submission()` auf dünnen Wrapper um die Inquiry-Engine umgestellt (vorher: eigene, hartcodierte Klartext-`wp_mail()`-Logik ohne Validierung/Mehrsprachigkeit)
- `inc/configurator/class-configurator.php`:
  - `ajax_configurator_inquiry()` ebenfalls auf die Inquiry-Engine umgestellt
  - Neue wiederverwendbare Helper: `get_config_display_array()`, `get_attachment_ids_from_config()`, `get_price_breakdown()` (jetzt gemeinsame Quelle für Cart-Anzeige, Wunschliste und Mail-Versand statt dreifach dupliziertem Code)
  - `display_configuration_in_cart()` nutzt jetzt `get_config_display_array()`
- `templates/inquiry-checkout.php`: rendert jetzt dynamisch konfigurierte Zusatzfelder + Datenschutz-Checkbox; JS sammelt Formularfelder generisch statt hartcodiert
- `templates/configurator/fields/contact-form.php`: ebenso um dynamische Zusatzfelder + Datenschutz-Checkbox erweitert
- `assets/js/configurator.js`: `sendInquiry()` sammelt Zusatzfelder generisch; neue `addToWishlist()`-Methode

#### Fixed

Mehrere vorbestehende, bis dato unbemerkte Bugs (unabhängig von den neuen Features gefunden und behoben):

- **Catalog Mode × Konfigurator:** Hook-Kollision an `woocommerce_before_add_to_cart_button` – bei aktivem "Kaufbuttons verstecken" feuerte der Konfigurator nie, da Catalog Mode den übergeordneten Hook entfernt. Konfigurator hängt jetzt an `woocommerce_single_product_summary` mit fester Priorität.
- **`class-configurator.php`, `canProceed()`:** fehlende Existenzprüfung führte zu einem JS-Crash auf dem Zusammenfassungs-Schritt (Alpine.js `:disabled`-Binding wird auch bei `x-show="false"` ausgewertet).
- **`templates/configurator/wizard.php`:** doppelte, hartcodierte `<script>`-Tags (Alpine.js, configurator.js, `configuratorData`) kollidierten mit der korrekten `wp_enqueue_script()`/`wp_localize_script()`-Registrierung (`SyntaxError: Identifier 'configuratorData' has already been declared`) – entfernt.
- **`class-configurator.php`, `enqueue_scripts()`:** verließ sich auf `global $product`, das am `wp_enqueue_scripts`-Hook bei WooCommerce unzuverlässig gesetzt ist – auf `wc_get_product( get_queried_object_id() )` umgestellt.
- **`get_config_display_array()`:** leere Werte (Konfigurator initialisiert jedes Step-Feld beim Start mit `''` bzw. `[]`) wurden fälschlich als "vorhanden" angezeigt (z.B. leere "Logo-Datei"-Zeile ohne Upload) – zusätzliche Leer-Prüfung ergänzt.
- **`inc/inquiry/class-cpt.php`:** Backend-Hinweis-Anzeige (Vollständigkeits-Check) prüfte auf einen von der Menü-*Beschriftung* abhängigen Screen-ID-Präfix statt auf den stabilen Options-Page-Slug.
- **`inc/inquiry/class-cpt.php`:** Standard-„Alle"-Ansicht im Backend zeigte 0 Einträge, da WordPress bei CPTs mit ausschließlich Custom-Post-Stati ohne expliziten Query-Parameter nicht zuverlässig alle registrierten Stati berücksichtigt – `pre_get_posts`-Fix ergänzt.
- **`inc/inquiry/class-settings.php`:** Sprach-Fallback-Kette fiel bei einer konfigurierten, aber leeren Sprachzeile direkt auf den (hartcodiert deutschen) Code-Default durch, statt zuerst die erste konfigurierte Sprache zu versuchen.

#### Known Issues / Follow-ups

- Theme-CSS-Layout-Bug bei Produkten ohne eigenes Bild (Text bricht zeichenweise um) – vermutlich Platzhalterbild ohne `max-width:100%` in der Produktgalerie. Nicht Teil dieses Plugins, separates Ticket empfohlen.
- Client-seitige Pflichtfeld-Validierung im Konfigurator-Wizard prüft neue Zusatzfelder/Datenschutz-Checkbox noch nicht inline (serverseitige Validierung greift zuverlässig, aber ohne Schritt-für-Schritt-UX-Hinweis).
- "Schritt X von Y"-Anzeige im Konfigurator-Wizard zählt den Zusammenfassungs-Schritt nicht korrekt mit (kosmetisch).
- Honeypot-Mindestzeit (Core-Plugin, Standard 3s) kann bei sehr schnellem Ausfüllen/Autofill zu Fehlversuchen führen – ggf. projektweit über Agency-Core-Einstellungen anpassen.

---

## [1.0.2] - vor dieser Session

Letzter Stand vor Beginn der Wunschlisten-Entwicklung (Catalog Mode, Konfigurator-Basisfunktion, Filter/Suche).
