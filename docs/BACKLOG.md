# Backlog

Offene Punkte, gefunden beim WooCommerce-Sync `at.janecka-2026` ↔ Starter Kit
(August 2026). Nicht alle Punkte sind neu entstanden — einige sind
vorbestehende Bugs im Starter Kit selbst, die beim Merge erstmals sichtbar
wurden.

---

## media-lab-woocommerce

### Kompatibilität — sollte in den Starter Kit zurückfließen

- **Theme-Alias-Hook (`ajax_filter_posts`) fehlt in `ajax-handlers.php`.**
  Janeckas Theme sendet AJAX-Filter-Requests unter `action: 'ajax_filter_posts'`
  statt `janecka_filter_products`/`mlwf_filter_products`. Der Starter Kit
  kennt nur die neueren Action-Namen. Sollte als zusätzlicher, dokumentierter
  Alias ergänzt werden — wirkungslos für Projekte, die den alten Namen nicht
  nutzen, aber schützt vor stillem Totalausfall der Filterfunktion bei
  Projekten mit ähnlicher Theme-Konvention wie Janecka.

- **Duale Nonce-Prüfung (`mlwf_filter_nonce` + `ajax_filters_nonce`) fehlt.**
  Gleicher Hintergrund wie oben — Janeckas Theme erzeugt den Nonce unter
  einem anderen Namen als der Starter Kit erwartet. Ohne Fallback: 403 bei
  jedem Filter-Request.

### Bugs — vorbestehend im Starter Kit, unabhängig vom Janecka-Merge

- **`templates/inquiry-checkout.php`: hartcodierter Steuersatz.**
  `$tax_rate = 20; // Österreich Standard-Satz` in der Cart-Review-Tabelle,
  während `class-price-calculator.php` bereits korrekt über
  `wc_get_price_excluding_tax()`/`wc_get_price_including_tax()` rechnet.
  Führt zu falschen Beträgen bei abweichender Steuerklasse oder Land.

- **`templates/inquiry-form.php`: totes Template.**
  Nirgends in `inc/` eingebunden (nur ein vermutlich fehlgeleiteter
  Kommentarverweis in `catalog-mode.php`). Klären: für Shortcode-Nutzung
  vorgesehen und nachrüsten, oder entfernen.

- **`templates/configurator/wizard.php`: Wishlist-Button ohne Guard.**
  Der "Zur Wunschliste hinzufügen"-Button ist unconditional im Markup.
  Für Projekte, die `inc/wishlist/` nicht mitnehmen (wie Janecka), zeigt
  das Frontend einen sichtbaren, aber funktionslosen Button (AJAX-Request
  gegen nicht-existenten Endpoint). Fix bei Janecka:
  `<?php if ( class_exists( 'MediaLab_Wishlist_Ajax' ) ) : ?>` um den
  Button. Sollte als Standard-Absicherung in den Starter Kit übernommen
  werden, nicht nur projektspezifisch gepatcht.

- **`inc/configurator/class-acf-fields.php`: `step_type` Choice `contact_form`
  fehlt.** `wizard.php`, `class-configurator.php` und
  `class-price-calculator.php` unterstützen den Step-Typ `contact_form`
  aktiv (eigener `switch`-Case, eigene Validierungslogik), aber die
  ACF-Feldgruppe listet ihn nicht in den `choices` von `field_step_type`.
  Ergebnis: Im Produkt-Editor ist "Kontaktformular" nicht auswählbar,
  obwohl der Konfigurator-Wizard ohne diesen Step gar nicht funktionsfähig
  ist (die Inquiry-Engine verlangt serverseitig Name/E-Mail, die ohne
  diesen Step nie erfasst werden). Bei Janecka nachgetragen:
  `'contact_form' => 'Kontaktformular',`

- **`templates/configurator/fields/number.php`: `isset()` reicht nicht.**
```php
  $min = isset($step['min_value']) ? $step['min_value'] : 1;
  $max = isset($step['max_value']) ? $step['max_value'] : 10000;
```
  ACF liefert bei leer gelassenem Feld einen leeren String zurück, nicht
  `null` — `isset('')` ist `true`, der Fallback greift also nie. Ergebnis:
  Mengenschalter (+/-) ohne `max`-Wert im `@click`-Handler, Buttons ohne
  Funktion. Robusterer Fix:
```php
  $min = (isset($step['min_value']) && $step['min_value'] !== '') ? $step['min_value'] : 1;
  $max = (isset($step['max_value']) && $step['max_value'] !== '') ? $step['max_value'] : 10000;
```

- **`inc/inquiry/class-mail.php`: Preisaufschlüsselung in der Anfrage-Mail
  unvollständig.** Zeigt nur `base_price`, `additions`, `tier_discount` und
  `total` — keine Zwischensumme, Menge oder MwSt.-Zeile. Der angezeigte
  Gesamtbetrag ist korrekt (inkl. Steuer), aber für den Kunden nicht
  nachvollziehbar, wie er zustande kommt. Bei Janecka erweitert um
  Zwischensumme (pro Stück), Menge, Zwischensumme vor Steuer, MwSt.-Zeile
  — siehe Commit in `at.janecka-2026`, analog in Starter Kit nachziehen.

### Feature-Kandidat aus `filter-bar.php`

- **Sortier-Layout (`wc-filter-bar__groups-sort`) fehlt im Starter Kit.**
  Janecka rendert Filter und `woocommerce_catalog_ordering()` in zwei
  getrennten, nebeneinanderliegenden Gruppen-Containern. Generisch
  sinnvoll, guter Kandidat für den Starter Kit — im Gegensatz zur
  `brand-is-active`-Filterung (ACF-Feld `brand-is-active`) oder
  `mlwf_render_filter_bar_for_category()` (aktuell ungenutzt bei
  Janecka), die beide projektspezifisch bleiben sollten.

---

## media-lab-agency-core

- **`assets/js/block-logo-slider.js` WCAG-2.2.2-Fokus-Pause nicht portiert.**
  Die entfernte Datei enthielt eine Autoplay-Pause bei Tastaturfokus
  (Barrierefreiheit). Die neue Theme-seitige Implementierung
  (`ml-logo-slider.js`) hat dieses Verhalten nicht übernommen. War laut
  Code-Kommentar durch einen Lade-Bug ohnehin nie aktiv (totes JS wurde nie
  ausgeführt), ist also keine neue Regression — aber ein Feature, das
  aktuell nirgends existiert. Relevant, falls Barrierefreiheit für ein
  Projekt Anforderung ist.

- **`assets/css/block-slider.css` Skeleton-Kommentar verweist auf gelöschte
  Datei.** Kommentar nennt `assets/js/block-slider.js` als Quelle für die
  `swiper-initialized`-Klasse — diese Datei existiert nicht mehr
  (Funktionalität wanderte ins Theme). Kosmetisch, aber sollte
  aktualisiert werden: der Fallback-Mechanismus (`skeleton-done`-Klasse)
  muss vom initialisierenden Theme-Script gesetzt werden, nicht vom
  Plugin. Referenzimplementierung: `at.janecka-2026/.../ml-slider.js`.

---

## Struktur / Prozess (aus früheren Sessions, weiterhin offen)

- Plugin-Struktur-Standardisierung: `media-lab-backup` ist Referenz
  (README + CHANGELOG.md + composer.json/lock + uninstall.php).
  `media-lab-agency-core` hat nur README, `media-lab-bookings` nur
  CHANGELOG.md, `media-lab-events`/`media-lab-woocommerce` haben nichts,
  `media-lab-seo` nutzt `CHANGES.md` statt `CHANGELOG.md`.
  `media-lab-woocommerce` hat inzwischen ein aktuelles CHANGELOG.md,
  weiterhin kein README/composer.json.

- `.gitignore`-Lücken: `cms/wp-content/languages/**` und
  `cms/wp-content/plugins/woocommerce-services/**` werden fälschlich
  getrackt, erzeugen Rauschen bei jedem `git status`.

---

## Nächste Projekte / Sync-Ausblick

- `org.churum-meru-2026` nutzt `media-lab-woocommerce` nicht mehr — beim
  nächsten Sync-Durchgang prüfen, ob andere Starter-Kit-Plugins
  (`media-lab-agency-core`, `media-lab-backup`, `media-lab-seo`) dort
  ebenfalls divergieren.
