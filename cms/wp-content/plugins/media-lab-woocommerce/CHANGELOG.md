# Changelog

Alle wesentlichen Änderungen werden in dieser Datei dokumentiert.
Format basiert auf [Keep a Changelog](https://keepachangelog.com/de/1.0.0/),
Versionierung nach [Semantic Versioning](https://semver.org/lang/de/).

---

## [2.0.1] - 2026-08-06

### media-lab-woocommerce 2.0.1

#### Fixed
- **Fehlendes Lade-/Sperr-Feedback in `wishlist.js`** – `handleRemove()` und
  `handleQtyChange()` gaben während des laufenden AJAX-Requests keinerlei
  visuelles Signal, wodurch Doppelklicks auf "Entfernen" oder schnelles
  mehrfaches Ändern der Menge unbemerkt mehrere Requests auslösen konnten.
  - `handleRemove()`: betroffene `.mlw-wishlist-item`-Zeile bekommt sofort
    die Klasse `is-removing` (Opacity 0.4, `pointer-events: none`) und der
    Entfernen-Button wird deaktiviert; zusätzlicher `removingItemIds`-Set
    verhindert parallele Requests für dieselbe Item-ID.
  - `handleQtyChange()`: Mengen-Input wird für die Dauer des (debounced)
    Requests deaktiviert.
  - Kein klassisches Skeleton-Screen hier bewusst nicht eingesetzt: die
    Operationen laufen über `get_user_meta()`-Storage ohne externe Requests
    und sind typischerweise deutlich unter 200ms – ein Skeleton würde eher
    Flackern erzeugen als Wartezeit fühlbar verkürzen. Siehe
    `media-lab-agency-core` CHANGELOG für die zentrale Skeleton-API, die für
    langsamere AJAX-Fälle (z.B. Post-/CPT-Filter) zum Einsatz kommt.

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
