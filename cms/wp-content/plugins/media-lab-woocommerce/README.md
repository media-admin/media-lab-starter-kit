# Media Lab WooCommerce

WooCommerce-Integrationsplugin für Media-Lab-Agentur-Websites: Catalog Mode
(Anfrage statt Kauf), Produkt-Konfigurator, Produktfilter, Suche-Erweiterungen
und eine **Wunschlisten-Funktion**, gebaut auf einer zentralen
**Inquiry-Engine**, die Cart-Anfrage, Konfigurator-Anfrage und Wunschliste
einheitlich verarbeitet.

Entwickelt von [Media Lab Tritremmel GmbH](https://media-lab.at).

---

## Features

- **Catalog Mode**: Shop ohne Kauf-Funktion, Anfrage-Formular statt Checkout; optional mit sichtbarer Variantenauswahl bei variablen Produkten
- **Produkt-Konfigurator**: mehrstufiger Wizard (Alpine.js) mit Preisberechnung, Mengenrabatt-Staffeln, Datei-Uploads; Konfigurator-Schritte lassen sich optional als Shop-Filter nutzen
- **Wunschliste**: eigenständig vom WooCommerce-Warenkorb; unterstützt konfigurierte Produkte (Preis/Konfiguration/Datei-Uploads) und Varianten; teilbar per Link; im Shop-Modus (ohne Catalog Mode) mit „In den Warenkorb"
- **Inquiry-Engine**: zentrale Anfrage-Verarbeitung (CPT, Validierung, Mail-Versand über mehrere Kanäle, Mehrsprachigkeit) – gemeinsam genutzt von Cart-Anfrage, Konfigurator-Anfrage und Wunschliste
- **Produktfilter**: AJAX-Filterleiste pro Kategorie/Marke konfigurierbar, Wording und Attribut-Labels mehrsprachig pflegbar, Unterkategorie-Mehrfachauswahl
- **Suche**: Filterleiste auf der Suchergebnisseite (aus der Treffermenge berechnet), Kategorien/Marken als Treffer und eigene Einträge, SKU-Teilsuche, Sichtbarkeits-Ausschluss
- **Shop-Erweiterungen**: Preis-Suffix (WP Germanized), „Produkte pro Seite", Summenzeile und Mengeneinheit auf der Produktseite, optionales Einzelprodukt-Layout (Opt-in)
- **Load-More** und AJAX-Suche (`inc/ajax-load-more-wc.php`, `inc/ajax-search-wc.php`)

---

## Voraussetzungen

- WordPress 6.0+
- PHP 8.0+
- WooCommerce (aktiv)
- Advanced Custom Fields Pro (für alle Einstellungsseiten)
- `media-lab-agency-core` (Honeypot-Spam-Schutz der Formulare)
- Optional: WP Germanized (Pro) für Rechtshinweise im Konfigurator, Einheit im Lagertext; Polylang oder WPML für Mehrsprachigkeit (nicht erforderlich, siehe unten)

### Zusammenspiel mit Core und Theme

Einige Funktionen brauchen eine Mindestversion der Nachbar-Komponenten.
Verbindlich ist immer der `Version:`-Header der jeweiligen Komponente.

| Funktion | Benötigt |
|---|---|
| Kategorien und Marken als Einträge in der Live-Suche | `media-lab-agency-core` ab 1.36.0 (ohne Core erscheint nur die Zeile „Passende Kategorien und Marken" auf der Ergebnisseite) |
| Zeile „Passende Kategorien und Marken" auf der Ergebnisseite | `search.php` des Themes gibt `mlw_get_search_term_links()` aus (Kit-Theme ab 1.17.0) |
| Unterkategorie-Mehrfachauswahl | `custom-theme` ab 1.18.0 (`mlwf-filters.js` sammelt `subcat[]`; mit älterem Theme-JS wirkt bei mehreren Haken nur der zuletzt gesetzte) |
| Filter auf der Suchergebnisseite | Theme reiht das Filter-JS auf der Suche ein (`is_search()`) und gibt in `search.php` `mlwf_get_search_filter_bar()` aus; Projekt-Kopien von `mlwf-filters.js` aktualisieren |
| Suche ohne Kappung bei 500 Treffern je Term | `media-lab-agency-core` ab 1.35.1 |
| Interaktive Filterleiste (Preis-Slider, Dropdowns, AJAX) | Frontend-JS/SCSS im Theme (`mlwf-filters.js`, Styling über die Sass-Pipeline) |
| Such-Erweiterungen (SKU, Sichtbarkeit, Kategorien/Marken als Treffer), Suchfilter, Trefferanzeige | `media-lab-agency-core` ab 1.35.0 (Erweiterungspunkte `media_lab_search_woo_matches` u. a., Text `serp_range`, Filter `media_lab_search_serp_per_page`) |
| Wunschliste teilen (Button) | `media-lab-agency-core` ab 1.25.0 (`medialab_share()` mit `url`/`title`). Ohne Core gibt der Shortcode nichts aus, mit einem älteren Core teilt der Button die Seiten-URL statt des Token-Links |

---

## Dependencies

Keine Composer-Abhängigkeiten — kein `composer.json`/`composer.lock`
vorhanden. Reines Hook-/ACF-basiertes PHP ohne externe Bibliotheken.
(Ausnahme im Starter Kit: `media-lab-backup`, das phpseclib3 für
SSH-Key-Auth benötigt.)

---

## Installation

1. Upload nach `/wp-content/plugins/media-lab-woocommerce/`
2. Im WordPress-Backend aktivieren
3. Einstellungen unter **Anfragen → Einstellungen** konfigurieren (siehe unten)

Beim Aktivieren legt das Plugin die Tabelle `wp_mlw_shared_wishlists` für das
Teilen von Wunschlisten an; beim Deaktivieren wird der Cron-Hook
`mlw_wishlist_share_cleanup` entfernt.

> **Update bestehender Projekte (von einer Version vor 2.6.0):** Die Tabelle
> wird ausschließlich beim Aktivieren angelegt, nicht beim bloßen Überschreiben
> der Plugin-Dateien. Ohne die Tabelle funktioniert das Teilen nicht. Nach dem
> Einspielen das Plugin einmal deaktivieren und wieder aktivieren, oder die
> Tabelle direkt anlegen:
>
> ```bash
> wp eval 'MediaLab_Wishlist_Share::create_table();'
> ```

---

## Catalog Mode und Shop-Modus

Ob ein Projekt Anfragen statt Käufen abwickelt, steuert die ACF-Option
`wc_catalog_mode_enabled`.

- **Catalog Mode an**: Kaufbuttons entfallen, Anfrage-Formular statt Checkout. Die Wunschlisten-Seite zeigt ein Grid mit Anfrage-Formular.
- **Catalog Mode aus (Shop-Modus)**: Die Wunschlisten-Seite zeigt eine Tabelle mit Checkboxen, Hinzufügedatum, Lagerstatus und „In den Warenkorb" statt des Anfrage-Formulars. In den Warenkorb gelegte Artikel verschwinden im selben Zug von der Wunschliste; konfigurierte Artikel und nicht ausreichend vorrätige Artikel werden übersprungen und mit Meldung angezeigt.

Option **„Variantenauswahl anzeigen"** (`wc_catalog_mode_show_variations`,
Standard aus): zeigt bei variablen Produkten mit „Kaufbuttons verstecken" die
Auswahlfelder (Farbe, Größe) weiterhin an, ohne Kaufbutton und Mengenfeld.
Preis und Verfügbarkeit reagieren auf die Auswahl. Konfigurierbare Produkte
bleiben unverändert.

---

## Wunschliste einrichten

1. Neue WordPress-Seite anlegen (z.B. "Wunschliste"), Inhalt: `[mlw_wishlist_page]`
2. Optional: **Anfragen → Einstellungen → Tab „Navigation"** – Icon im Hauptmenü aktivieren, die eben angelegte Seite auswählen
3. Formularfelder, Pflichtfelder, Datenschutz-Text und Mail-Templates unter **Anfragen → Einstellungen** anpassen – gelten automatisch für Cart-Anfrage, Konfigurator-Anfrage **und** Wunschliste gemeinsam

### Button-Stil

Der Wunschlisten-Button erscheint standardmäßig als Herz-Icon. Der Stil ist
projektweit umstellbar:

```php
add_filter( 'mlw_wishlist_button_style', fn() => 'text' );       // nur Text
add_filter( 'mlw_wishlist_button_style', fn() => 'icon_text' );  // Icon + Text
```

Der Button zeigt beim Rendern sofort den Zustand „schon auf der Liste". Ein
erneuter Klick entfernt den Artikel wieder. Der Text im aktiven Zustand
(„Entfernen-Button") ist in den Einstellungen pflegbar.

Themes mit eigenem Produktkarten-Markup schalten die automatischen Hooks ab
(`mlw_wishlist_auto_loop_button`, `mlw_wishlist_auto_single_button`, jeweils
Standard `true`) und setzen den Button selbst per `render_button_html()`
(`inc/wishlist/class-frontend.php`).

### Varianten

Variable Produkte werden mit der gewählten Variante gespeichert (Eintrag
`variation_<id>`). Ohne gewählte Variante lässt sich ein variables Produkt
nicht hinzufügen. Wunschzettel-Seite, geteilte Liste und Anfrage (Mail,
gespeicherte Anfrage, Kanäle) enthalten Variante, Varianten-SKU und Merkmale.

### Teilen per Link

Kunden und Gäste teilen ihre Wunschliste über einen dauerhaften Token-Link
(`inc/wishlist/class-share.php`, Tabelle `wp_mlw_shared_wishlists`). Den Button
gibt `templates/wishlist/page.php` automatisch aus: im Catalog Mode im Kopfbereich
(sobald Artikel auf der Liste stehen), im Shop-Modus unter der Tabelle. Für eine
eigene Platzierung gibt es den Shortcode `[mlw_wishlist_share_link]`. Der Button
baut die URL `<Wunschlisten-Seite>?mlw_share=<Token>` und reicht sie an
`medialab_share()` aus `media-lab-agency-core` weiter.

- **Eingeloggte Kunden:** Der Token verweist nur auf die User-ID, die Items werden bei jedem Aufruf live aus der Wunschliste des Kunden geladen. Der Link zeigt daher immer den aktuellen Stand und bleibt dauerhaft bestehen.
- **Gäste:** Beim Teilen wird ein Snapshot der Liste gespeichert. Wiederholtes Teilen liefert denselben Link und aktualisiert nur den Snapshot. Artikel, die der Gast nach dem Teilen hinzufügt, sieht der Empfänger erst nach erneutem Teilen. Snapshots werden täglich per Cron bereinigt, wenn die letzte Aktualisierung länger als 90 Tage zurückliegt (Filter `mlw_wishlist_share_guest_retention_days`).

Empfänger sehen eine schreibgeschützte Ansicht (`templates/wishlist/shared.php`).

### Preise und Mengen

- Stückpreis nach Menge (z.B. Staffel) per Filter `mlw_wishlist_unit_price`; gilt nicht für konfigurierte Artikel.
- Auf der Einzelproduktseite zeigt eine Summenzeile „Gesamt (N Stück): Summe". Für Mengenstaffeln gibt es JS-Haken (siehe Hooks-Referenz).
- Optionales Mengenfeld auf der Einzelproduktseite: Filter `mlw_wishlist_single_quantity`. Bei einfachen Produkten ersetzt eine erneute Anfrage die Menge statt sie zu addieren (Filter `mlw_wishlist_replace_quantity`).

### Konfigurierbare Produkte

Damit ein Produkt im Konfigurator-Wizard erscheint, braucht es (per ACF am
Produkt): `is_configurable = true`, `config_type = standard`, sowie
mindestens einen `config_steps`-Eintrag vom Typ `contact_form` (rendert
Name/E-Mail/Telefon + die konfigurierten Zusatzfelder) – ohne diesen Step
gibt es im Wizard kein Kontaktformular.

> **Hinweis:** Der `config_type` (Textilien/Drucksorten/Give-Aways/
> Benutzerdefiniert) dient rein der Organisation im Backend - alle Typen
> laden die Konfigurationsschritte identisch aus dem `config_steps`-Repeater
> (seit dem Fix in 2.5.1, siehe CHANGELOG.md).

Bei variablen Produkten gilt als Basispreis der günstigste Variantenpreis,
wenn der reguläre Preis am Parent leer ist.

---

## Einstellungen

### Anfragen → Einstellungen

| Tab | Inhalt |
|---|---|
| Wording | Bezeichnungen, Button-Texte, Erfolgsmeldungen (Anfrage und Wunschliste getrennt), Hinweistext „Wunschliste leer", „Entfernen-Button", Preis-Hinweistext, „Verfügbarkeit: ohne Bestand" (einsprachiger Fallback) |
| Sprachen | Mehrsprachigkeit an/aus, pro Sprache: Wording, Wunschlisten-Seite, Mail-Templates |
| Formularfelder | Eingebautes Telefonfeld (Optional / Pflichtfeld / Ausblenden, gilt für alle Anfrage-Formulare), Zusatzfelder (Text/E-Mail/Auswahl/Checkbox/…), Pflicht/Optional, pro Sprache übersetzbar |
| Kanäle | E-Mail, WhatsApp-Link, Webhook (JSON-POST) – beliebig kombinierbar |
| Mail-Templates | Kunden-/Admin-Mail, Platzhalter-basiert (einsprachiger Fallback) |
| Navigation | Wunschlisten-Icon im Hauptmenü an/aus, Mengen-Badge an/aus |

Mehrsprachigkeit funktioniert unabhängig davon, ob WPML, Polylang oder gar
kein Mehrsprachigkeits-Plugin installiert ist (eigene Sprach-Erkennung:
Polylang → WPML → `get_locale()`). Fallback-Kette für Texte: passende Sprache →
erste konfigurierte Sprache → sprachneutrales Feld → Code-Default.

### Produkte → Filter-Einstellungen

Eigene Optionsseite des Filter-Systems, unabhängig von den Anfrage-Einstellungen.

| Tab | Inhalt |
|---|---|
| Wording | Preis-, Kategorie-, Marke-, Zurücksetzen-Label sowie Lade- und Leer-Text (einsprachiger Fallback). Das Kategorie-Label gilt für den Unterkategorie-Filter. |
| Attribut-Labels | Überschreibt die Anzeige-Bezeichnung eines Attributs (oder `product_brand`) nur in der Filterleiste, ohne das WooCommerce-Attribut umzubenennen. Ohne Eintrag gilt das Standard-Label. |
| Sprachen | Mehrsprachigkeit an/aus, pro Sprache: Wording und Attribut-Labels. Die erste Zeile ist die Fallback-Sprache. |

Im Code: `MediaLab_Filter_Settings::label( 'price' | 'category' | 'brand' | 'reset' | 'loading' | 'no_products' )`
und `MediaLab_Filter_Settings::attribute_label( $slug )` (leer, wenn nichts konfiguriert ist).

### Weitere Einstellungen

- **„Produkte pro Seite"**: WooCommerce → Einstellungen → Produkte → Anzeige (Option `mlw_products_per_page`). Wirkt auf Shop-/Kategorie-Seiten, AJAX-gefilterte Ansichten und die native Produktsuche.
- **Preis-Suffix** (`inc/price-suffix.php`): nutzt den mehrsprachigen Preis-Hinweistext der Anfrage-Einstellungen, sonst das native WooCommerce-Feld „Preis-Anzeige-Suffix". Unabhängig von der Inquiry-Engine nutzbar.

### Telefonfeld und reservierte Feld-Keys

Name, E-Mail und Telefon sind eingebaute Basisfelder. Das Telefonfeld steuert
die Einstellung **„Telefon"** (Tab „Formularfelder"): *Optional* (Standard),
*Pflichtfeld* oder *Ausblenden*. Sie gilt einheitlich für Anfrage-Seite
(Checkout), `[mlw_inquiry_form]`, Konfigurator-Kontaktformular und Wunschliste.
Die verbindliche Prüfung läuft serverseitig in der Inquiry-Engine.

Die Keys `name`, `email`, `phone`, `message` und `privacy_consent` sind für
Basisfelder reserviert und lassen sich im Repeater „Zusätzliche Felder" nicht
als Feld-Key vergeben (ACF-Validierung beim Speichern). Bereits vorhandene
Zeilen mit einem reservierten Key werden nicht mehr ausgegeben.

---

## Produktfilter

### Konfiguration pro Kategorie und Marke

Jede Produktkategorie und Marke hat eigene Filter-Felder (ACF am Term, Präfix
`mlwf_`): Attribute, Preis-Slider, Marken-Filter, Unterkategorien, Reihenfolge
sowie „Von Elternkategorie erben". Hat ein Term keine eigene Konfiguration, gilt
die Konfiguration der Elternkategorie (bei aktiver Vererbung), sonst die globale
Standard-Konfiguration. Eine Admin-Übersicht (`inc/filters/admin-overview.php`)
zeigt für alle Kategorien und Marken die aktiven Filter und ihre Quelle.

### Filterleiste

Die Leiste hängt sich selbst an `woocommerce_before_shop_loop` (Priorität 15),
der Produkt-Container `.wc-products-container` ist das Ziel des AJAX-Austauschs.
Die Theme-Seite braucht keine eigene Verdrahtung.

- Die native WooCommerce-Sortierung erscheint neben den Filtergruppen und lässt sich mit `add_filter( 'mlwf_filter_bar_show_sort', '__return_false' )` abschalten. Der Sortierwechsel selbst lädt noch die Seite neu (Follow-up aus 2.4.0, siehe CHANGELOG.md).
- Die Gruppe „Kategorie" nutzt Checkboxen (`subcat[]`, ODER-Verknüpfung). Initialzustand aus der URL: `?subcat=damenuhren,herrenuhren`; ein einzelner Wert funktioniert weiterhin.
- AJAX-Action `mlwf_filter_products` (Alias `ajax_filter_posts` für abweichende Theme-Konventionen). Die Nonce wird unter `mlwf_filter_nonce` oder `ajax_filters_nonce` akzeptiert.
- Mobil/Tablet: Schalter „Filter" mit Zähler der aktiven Filter.

### Konfigurator-Schritte als Filter

`config_steps` vom Typ select/radio/checkbox/color_picker lassen sich mit einem
bestehenden WooCommerce-Attribut verknüpfen (`use_as_filter` +
`filter_attribute`). Beim Speichern des Produkts gleicht
`inc/configurator/filter-sync.php` die Options-Labels mit den Terms der Taxonomie
ab (Matching per Label, Neuanlage falls nötig) und schreibt die `term_id` nach
`filter_term_id`. Für Bestandsprodukte:

```bash
wp mlwf sync-configurator-filters --dry-run   # Vorschau
wp mlwf sync-configurator-filters             # ausführen
```

`value` (Konfigurator-intern) und Attribut-Term-Slug bleiben getrennte
Bezeichner; Verknüpfungen laufen über das Label.

---

## Suche

### Filter auf der Suchergebnisseite

`inc/filters/search-filters.php` berechnet Kategorie, Marke und alle
Produktattribute aus der Treffermenge der Suche (nicht aus der globalen
Filter-Konfiguration). Es erscheinen nur Werte, die in mindestens einem Treffer
vorkommen, mit Anzahl; Filter mit nur einem Wert entfallen. Dazu kommen ein
Preis-Slider mit der Preisspanne der Treffer, eine Sortierung (Relevanz, Preis
auf-/absteigend, Neueste) und die Trefferanzeige „Ergebnisse 1–24 von 139 …"
(Text aus der Core-Einstellung `serp_range`).

Filter-URLs der Suche tragen das Präfix `f_` (`?s=ring&f_product_cat=eheringe`),
damit WordPress sie nicht als Kategorie-Archiv deutet. `price_min`, `price_max`
und `orderby` gelten ohne Präfix. Als Standard für „Ergebnisse pro Seite" gilt die
Anzahl der Shop-Seite, wenn im Core `0` eingestellt ist (Filter
`media_lab_search_serp_per_page`).

### Such-Erweiterungen

`inc/search-woo-extras.php`:

- Produkte mit Katalog-Sichtbarkeit „Nur Shop" oder „Versteckt" erscheinen nicht in der Suche (bei „Nicht vorrätige Artikel ausblenden" auch ausverkaufte). Wer sie in der Suche haben will, stellt sie auf „Shop und Suchergebnisse".
- SKU-Teilsuche (Varianten führen zum Elternprodukt).
- Kategorien und Marken zählen standardmäßig als Treffer (Filter `mlw_search_taxonomies`). Kurze Begriffe finden dadurch alle Produkte passender Kategorien; wer das nicht möchte, schließt Kategorien per Filter aus (Beispiel im Kopfkommentar der Datei).
- Kategorien und Marken als eigene Einträge: Live-Suche mit bis zu 3 Einträgen ganz oben (Label „Kategorie"/„Marke", Name, Produktanzahl, Link), Ergebnisseite mit der Zeile „Passende Kategorien und Marken". Reihenfolge: exakter Name, Wortanfang, „enthält"; Kategorien vor Marken, dann nach Produktanzahl.

---

## Hooks und JS-Schnittstellen

### PHP-Filter

| Hook | Zweck |
|---|---|
| `mlwf_filter_bar_show_sort` | WooCommerce-Sortierung in der Filterleiste ein-/ausblenden |
| `mlwf_enqueue_frontend_data` | Weitere Seiten fordern das Filter-Objekt `window.mlwf` an |
| `mlwf_search_facet_taxonomies`, `mlwf_search_facet_min_terms`, `mlwf_search_facets`, `mlwf_search_filter_min_products`, `mlwf_search_sort_options` | Filter auf der Suchergebnisseite anpassen |
| `mlw_search_taxonomies` | Taxonomien, die als Treffer zählen (Standard `product_cat`, `product_brand`) |
| `mlw_search_excluded_visibility_terms` | Sichtbarkeits-Terms, die aus der Suche ausgeschlossen werden |
| `mlw_search_term_taxonomies`, `mlw_search_term_results_max` | Kategorien/Marken als Einträge (Standard `product_cat`, `product_brand`; leer = keine; Maximum 3) |
| `media_lab_search_serp_per_page` | „Ergebnisse pro Seite" der Suche (Core-Filter) |
| `mlw_wishlist_button_style` | Button-Stil: `icon`, `text`, `icon_text` |
| `mlw_wishlist_auto_loop_button`, `mlw_wishlist_auto_single_button` | Automatische Button-Hooks abschalten (Standard `true`) |
| `mlw_wishlist_single_quantity`, `mlw_wishlist_single_button_style` | Mengenfeld und Button-Stil auf der Einzelproduktseite |
| `mlw_wishlist_replace_quantity` | Erneutes Hinzufügen ersetzt die Menge statt zu addieren |
| `mlw_wishlist_unit_price( $preis, $item, $produkt, $menge )` | Stückpreis nach Menge anpassen |
| `mlw_wishlist_share_guest_retention_days` | Aufbewahrung geteilter Gast-Wunschlisten (Standard 90 Tage) |
| `mlw_single_product_layout`, `mlw_loop_product_info`, `mlw_loop_availability`, `mlw_reviews_always_open` | Optionales Einzelprodukt-/Karten-Layout (`inc/single-product-layout.php`, Standard aus) |
| `mlw_stock_unit( $einheit, $produkt )`, `mlw_stock_unit_enabled` | Mengeneinheit im Lagertext (Quelle: Germanized-Feld „Einheit") |

### JavaScript

- `window.mlwUnitPriceFilters`: Funktionen `( preis, menge, ctx ) => neuerPreis`, z.B. für eine Mengenstaffel.
- Events `mlw:recalculate` und `mlw:price-updated`.
- `window.mlwFormatMoney()`: Preisformat aus den WooCommerce-Einstellungen.

### Shortcodes

- `[mlw_wishlist_page]`: Wunschlisten-Seite
- `[mlw_wishlist_share_link]`: Teilen-Button für eigene Platzierung (die Wunschlisten-Seite gibt ihn selbst aus, siehe „Teilen per Link")
- `[mlw_inquiry_form]`: Anfrage-Formular außerhalb des Catalog-Mode-Checkouts (z.B. Landingpage); Theme-Override via `yourtheme/media-lab-woocommerce/inquiry-form.php`

---

## Testdaten

WP-CLI-Skripte im Plugin: `create-test-products.php` (ein einfaches und ein
konfigurierbares Produkt) und `create-category-test-products.php` (36
Testprodukte über drei Kategorien, inkl. Mengen-Step für Staffelpreis-Tests).

---

## Architektur-Hinweis

Die Inquiry-Engine (`inc/inquiry/`) ist absichtlich von der Wunschliste
(`inc/wishlist/`) getrennt: Erstere kennt nur "Items + Kontaktdaten + Quelle",
Letztere ist reine Datenhaltung (Session/User-Meta) ohne Wissen über
Mail-Versand. `catalog-mode.php` und `class-configurator.php` reichen ihre
Daten ebenfalls nur an die Engine durch. Neue Anfrage-Quellen lassen sich so
ergänzen, ohne Mail-/Validierungs-/Mehrsprachigkeits-Logik zu duplizieren.

Ebenso ist das Filter-System (`inc/filters/`) von der Inquiry-Engine entkoppelt:
Es hat eigene Einstellungen (`MediaLab_Filter_Settings`) und eine eigene
Sprach-Erkennung (`MediaLab_Filter_I18n`), die bewusst als Kopie von
`inc/inquiry/class-i18n.php` geführt wird. Beide Systeme funktionieren
unabhängig voneinander, auch in Projekten, die nur eines davon einbinden.
Änderungen an der Sprach-Erkennung müssen daher an beiden Stellen nachgezogen
werden.

---

## Changelog

Siehe [CHANGELOG.md](./CHANGELOG.md) für die vollständige Versionshistorie.
Aktuelle Version: siehe `Version:`-Header in `media-lab-woocommerce.php`.

---

## Lizenz

GPL v2 or later — https://www.gnu.org/licenses/gpl-2.0.html
