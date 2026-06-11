# Changelog

Alle wesentlichen Änderungen am Media Lab Starter Kit werden hier dokumentiert.
Format basiert auf [Keep a Changelog](https://keepachangelog.com/de/1.0.0/),
Versionierung nach [Semantic Versioning](https://semver.org/lang/de/).

---

## [1.21.0] - 2026-06-11

### media-lab-agency-core 1.13.0

#### Added
- **Cloudflare Turnstile** (`inc/turnstile.php`) – Neue CAPTCHA-Integration als
  datenschutzfreundliche Alternative zu reCAPTCHA. Läuft parallel zu hCaptcha;
  Admin-Notice wenn beide für denselben Scope aktiv sind.
  - Scopes: CF7 (default: an), WP Login (an), WooCommerce Checkout (an),
    WooCommerce Login (an), WooCommerce Registrierung (an)
  - Öffentliche API: `medialab_turnstile_render()`, `medialab_turnstile_verify()`,
    `medialab_turnstile_active()`
  - DSGVO-Modus: „Berechtigtes Interesse" (Standard, empfohlen) oder
    „Consent-abhängig" (Widget rendert erst nach Zustimmung zur konfigurierten
    Cookie-Kategorie; dockt via `mlConsentUpdated`-Event und MutationObserver
    an das Media Lab Cookie Consent System an)
  - Widget-Optionen: Erscheinungsbild (auto/light/dark), Größe (normal/compact/flexible)
  - Konfiguration: Agency Core → Spam-Schutz

#### Changed
- **Honeypot – konfigurierbare Parameter** (`inc/honeypot.php`) –
  Mindest-Ausfüllzeit und maximales Formular-Alter sind jetzt in
  Agency Core → Spam-Schutz konfigurierbar (statt nur über Konstanten).
  Konstanten (z.B. in `wp-config.php`) haben weiterhin Vorrang – bestehende
  Projekte bleiben vollständig kompatibel.
  Neue ACF-Felder: `honeypot_min_time` (Standard: 3 s), `honeypot_max_age`
  (Standard: 86400 s / 24 h).
- **ACF-Settings – Spam-Schutz-Sektion** (`inc/acf-settings.php`) –
  Honeypot-Parameter-Felder und vollständige Turnstile-Konfiguration in die
  bestehende Spam-Schutz-Seite integriert (vor hCaptcha).

#### Changed
- **Cookie Consent – Standard-Texte** (`inc/cookie-consent.php`) –
  Alle Default-Texte überarbeitet für sofortigen Einsatz ohne projektspezifische
  Anpassung:
  - Banner-Titel: präziser und rechtlich klarer
  - Banner-Text: vollständiger mit Erwähnung technisch notwendiger Cookies
  - Ablehnen-Button: „Nur Notwendige" statt „Ablehnen" (klarer für Nutzer)
  - Modal-Intro: Hinweis auf nicht deaktivierbare notwendige Cookies ergänzt
  - Kategorie-Beschreibungen: Beispiele (Google Analytics, Meta Pixel, YouTube,
    Google Maps) und rechtliche Einordnung ergänzt
  - Notwendig-Beschreibung: Cloudflare Turnstile-Hinweis (berechtigtes Interesse)
    wird dynamisch ergänzt wenn Turnstile aktiv ist

---


## [1.20.3] - 2026-06-11

### media-lab-agency-core 1.12.2

#### Added
- **Hero – Featured Image Fallback** (`inc/hero-image.php`) –
  `media_lab_get_hero_image()` erweitert um dritte Stufe in der
  Fallback-Kette für das Desktop-Bild: seitenspezifisches Feld →
  globale Option `hero_fallback_desktop` → **Featured Image des Posts**.
  Ermöglicht Hero-Anzeige ohne explizite Hero-Konfiguration, wenn ein
  Featured Image gesetzt ist (typisch bei Blog-Posts und CPTs).

#### Changed
- **Hero – Numerische Höhe** (`inc/hero-image.php`) –
  `media_lab_get_hero_image()` erlaubt jetzt ganzzahlige Pixel-Werte als
  `height` (z.B. `"500"`). Bisher wurden nur Named Heights (`sm`, `md`,
  `lg`, `xl`) akzeptiert; andere Werte fielen auf `'md'` zurück. Numerische
  Werte werden als String durchgereicht und im Template Part als
  `style="height:500px"` gesetzt (statt CSS-Klasse).

### custom-theme 1.14.3

#### Fixed
- **Hero – `width`/`height`-Attribute auf `<img>`** (`template-parts/hero-image.php`) –
  `_medialab_resolve_image()` liefert bereits `width` und `height`, diese
  wurden aber im Template Part nicht ausgegeben. Fehlende Dimensionen führen
  zu Cumulative Layout Shift (CLS). Beide Attribute werden jetzt auf `<img>`
  (und `<picture>`-Fallback) gesetzt, sofern die Funktion Werte zurückgibt.

#### Changed
- **Hero – Numerische Höhe** (`template-parts/hero-image.php`) –
  Ist `$hero['height']` ein numerischer Wert, wird `style="height:Npx"` direkt
  auf dem `<section>`-Element gesetzt statt einer CSS-Klasse. Named Heights
  (`sm`, `md`, `lg`, `xl`) verhalten sich unverändert als CSS-Klassen.

---


## [1.20.2] - 2026-06-11

### custom-theme 1.14.2

#### Added
- **`--header-height` CSS-Variable** (`header.php`) – Neue CSS-Custom-Property
  im `:root`-Scope mit generischem Fallback `80px`. Wird nach `window.load`
  via Inline-JS auf die tatsächliche `offsetHeight` des `.site-header` gesetzt
  und bei `resize` aktualisiert (Admin-Bar, Orientierungswechsel). Layoutsysteme
  wie Hero-Image können damit exakt offsetten ohne hartcodierte Pixelwerte.
  Betroffene Regeln im selben `<style>`-Block: `.hero-image { margin-top }`,
  `.site-main:has(> .hero-image)` und `.hero-image--vpos-bottom .hero-image__content`.

#### Fixed
- **CF7 Select-Darstellung** (`header.php`) – Theme-Bug: `padding: 24px 32px`
  kombiniert mit `height: 48px; box-sizing: border-box` ergibt 0px
  Content-Bereich, Select-Text unsichtbar. Fix: vertikales Padding auf `0`
  zurückgesetzt, Text per `line-height: 44px` zentriert. Gilt für
  `.wpcf7-form-control.wpcf7-select` und `.wpcf7 select`.
  Zusätzlich: `input[type="date"]::-webkit-datetime-edit` Farbe für
  iOS/Safari-Platzhalter gesetzt.

---


## [1.20.1] - 2026-06-11

### media-lab-agency-core 1.12.1

#### Fixed
- **SVG Sanitizer – Lowercase-Allowlist** (`inc/svg-support.php`) –
  `$allowed_tags` und `$forbidden_tags` vollständig auf Lowercase normalisiert.
  `strtolower($child->localName)` ergab z.B. `"radialgradient"`, aber die
  Allowlist enthielt `"radialGradient"` → Gradient-Elemente wurden fälschlich
  entfernt, SVG-Farbverläufe waren nach dem Upload unsichtbar. Betrifft alle
  CamelCase-Tags: `linearGradient`, `radialGradient`, `clipPath`, `textPath`,
  `foreignObject`, Animations- und Filter-Elemente.
- **Native Blocks – wp.domReady()-Wrapper** (`assets/src/js/blocks.js`) –
  `registerBlockType()` wurde außerhalb von `wp.domReady()` aufgerufen, was
  bei bestimmten Ladereihenfolgen zu Race Conditions führte (`wp.blocks` noch
  nicht initialisiert). Gesamter Block-Registrierungscode in `wp.domReady()`
  gewrapped.
- **Editor-CSS im Gutenberg-Iframe** (`inc/blocks.php`) – Seit WP 6.3 wird
  der Editor in einem Iframe gerendert; Styles über `enqueue_block_editor_assets`
  landen außerhalb des Iframes und waren im Editor unsichtbar. Hook auf
  `enqueue_block_assets` (mit `is_admin()`-Guard) geändert. `wp-edit-blocks`-
  Dependency bei Editor-Styles entfernt (verursachte Konflikte im Iframe-Kontext).
- **wp-dom-ready Dependency** (`inc/blocks.php`) – `wp-dom-ready` zu den
  Script-Dependencies von `medialab-blocks` hinzugefügt, damit der
  `wp.domReady()`-Wrapper in `blocks.js` korrekt aufgelöst wird.

### Starter Kit (root)

#### Fixed
- **Vite – Swiper stabiler Chunk-Name** (`vite.config.js`) – Swiper wurde
  mit Hash im Dateinamen gebaut (`chunks/swiper-[hash].js`), was bei jedem
  Build die URL änderte und `wp_enqueue_script('swiper', ...)` mit hartem
  Pfad brechen ließ. `manualChunks` und `STABLE_CHUNKS`-Logik ergänzt:
  Swiper landet jetzt immer unter `chunks/swiper.js`.
- **vite.config.blocks.js – ES-Module-Format** (`vite.config.blocks.js`) –
  `format: 'es'` explizit gesetzt; `external` korrekt in `rollupOptions`
  verschoben (war außerhalb); `cssCodeSplit: false` entfernt (dort nicht
  zutreffend). Konsistent mit dem `type="module"`-Filter in `inc/blocks.php`.

---


## [1.20.0] - 2026-05-22

### media-lab-agency-core 1.9.2

#### Added
- **Honeypot Spam Protection** (`inc/honeypot.php`) – DSGVO-konformer
  Spam-Schutz ohne externe Requests, Cookies oder personenbezogene Daten.
  Zwei Schichten:
  - Schicht 1: Honeypot-Feld `_ml_website` – für echte Nutzer via
    Off-Screen-CSS vollständig unsichtbar (kein `display:none` –
    fortgeschrittene Bots erkennen diese Property); Bots, die alle
    sichtbaren Felder befüllen, verraten sich durch das gefüllte Feld.
  - Schicht 2: Time-Check mit HMAC-signiertem Zeitstempel (`_ml_form_ts`);
    prüft Mindest-Ausfüllzeit (3 s) und maximales Formular-Alter (24 h für
    gecachte Seiten); Signatur via `wp_hash()` + `wp_salt('nonce')`,
    Vergleich timing-safe mit `hash_equals()`.
  - CF7: automatische Integration via `wpcf7_form_elements` +
    `wpcf7_spam`-Filter (Priority 5, läuft vor Akismet).
  - Öffentliche API für eigene Formulare und das Bookings-Plugin:
    `medialab_honeypot_render()` und `medialab_honeypot_check()`.
  - Fehlercodes: `hp_missing`, `hp_filled`, `hp_too_fast`, `hp_expired`,
    `hp_ts_missing`, `hp_ts_malformed`, `hp_ts_invalid`.
- **Top Header – Arrow Buttons** (`inc/top-header-order.php`) –
  Zusätzlich zu Drag & Drop sind jetzt ▲ / ▼ Pfeil-Buttons pro Element
  verfügbar. Erstes/letztes Element: jeweiliger Button automatisch disabled.
  Fokus-Management für Tastaturnavigation. Beide Methoden nutzen denselben
  AJAX-Endpunkt.
- **Post Order – Arrow Buttons** (`assets/js/post-order.js`,
  `assets/css/post-order.css`) – Zusätzlich zu Drag & Drop sind jetzt ▲ / ▼
  Pfeil-Buttons in der Admin-Listenansicht verfügbar. Quick-Edit-kompatibel
  (Controls werden nach `ajaxComplete` neu eingefügt). Fokus-Management für
  Tastaturnavigation.

#### Fixed
- **Top Header – Reihenfolge** (`inc/top-header-order.php`) –
  `medialab_get_top_header_order()` und `medialab_get_top_header_social_order()`
  crashten mit `array_merge(): Argument #1 must be of type array, string given`
  wenn die Option noch im alten JSON-String-Format in `wp_options` gespeichert
  war. Beide Funktionen normalisieren den gespeicherten Wert jetzt via
  `is_string()` + `json_decode()` (Rückwärtskompatibilität).

### custom-theme 1.14.1

#### Added
- **Honeypot CSS** (`assets/src/scss/components/_honeypot.scss`) –
  Off-Screen-Positionierung für `.ml-hp`-Wrapper (`position: absolute;
  left: -9999px`), `pointer-events: none`, `opacity: 0`; kein
  `display:none` aus Sicherheitsgründen.

---

## [1.19.1] - 2026-05-12

### media-lab-agency-core 1.9.1

#### Added
- **Top Header – Drag & Drop Reihenfolge** (`inc/top-header-order.php`) –
  Neue Seite auf Agency Core → Top Header: Kontakt-Elemente (Adresse,
  Öffnungszeiten, Telefon, E-Mail) und Social-Media-Kanäle (Facebook,
  Instagram, LinkedIn, X/Twitter, YouTube, Xing) sind per Drag & Drop
  sortierbar. Reihenfolge wird in `wp_options` als JSON gespeichert
  (`medialab_top_header_item_order`, `medialab_top_header_social_order`).
  AJAX-Handler mit Nonce-Schutz; `jquery-ui-sortable` aus WP Core.

#### Changed
- **Social Share Buttons** (`assets/css/social-share.css`) – Standard-Button-
  Größe von `2.5rem` auf `1.75rem` reduziert; Padding von `0 0.75rem` auf
  `0 0.5rem`; SVG-Größe von `1.125rem` auf `1rem` verkleinert.

### custom-theme 1.14.0

#### Added
- **Notifications – Rich Content Popup** (`assets/src/scss/components/_notifications.scss`) –
  Neue Modifier-Klassen `.notification-popup--rich` und
  `.notification-popup__body--rich` mit scoped Gutenberg-Block-Styles
  (wp:image, wp:buttons, Überschriften, Listen, Separator). Overlay-Opacity
  von `0.5` auf `0.85` erhöht.

#### Changed
- **Notifications – Gutenberg-Content** (`assets/src/js/components/notifications.js`) –
  `showPopup()` rendert jetzt `n.content` (Gutenberg-HTML) vorrangig vor
  `n.message` (ACF-Kurztext). Bei Rich Content: kein Dashicon-Icon,
  Popup-Breite auf Container ausgedehnt.
- **Notifications – Popup-Sizing** (`_notifications.scss`) –
  Popup-Breite auf `$container-width` gesetzt; Overlay-Padding dynamisch:
  `max($spacing-4, calc((100vw - $container-width) / 2))` → Popup sitzt
  pixel-genau im Container-Raster.
- **Notifications – Text-Ausrichtung** (`_notifications.scss`) –
  `text-align: center` auf `.notification-popup` greift nur noch wenn kein
  Rich Content vorhanden (`&:not(.notification-popup--rich)`); Gutenberg-
  Ausrichtungsklassen (`.has-text-align-*`) werden vollständig respektiert.
- **Top Header – Reihenfolge** (`header.php`) –
  Feste `if/endif`-Blöcke für Kontakt-Elemente durch Render-Map + Loop
  ersetzt. Reihenfolge kommt aus `medialab_get_top_header_order()` und
  `medialab_get_top_header_social_order()` (Fallback: bisherige
  Standard-Reihenfolge). Social Media bleibt rechtsbündig.

---

## [1.19.0] - 2026-05-07

### media-lab-agency-core 1.9.0

#### Added
- **Cookie Consent – Mehrsprachigkeit** (`inc/cookie-consent.php`)
  - Neue Option „Mehrsprachigkeit aktivieren" in Agency Core → Cookie Consent
  - Repeater-Feld `cc_languages`: pro Sprache eigene Texte für Banner, Buttons,
    Modal, Kategorie-Bezeichnungen und Datenschutz-URL
  - Spracherkennung in folgender Priorität: Polylang → WPML (`ICL_LANGUAGE_CODE`) →
    WP-Locale-Fallback (`get_locale()` auf 2 Zeichen gekürzt)
  - Die erste Repeater-Zeile gilt als Standard-Sprache (Fallback bei
    fehlender Übereinstimmung)
  - Wenn Mehrsprachigkeit deaktiviert ist, bleiben alle bisherigen Flat-Felder
    unverändert aktiv (vollständige Rückwärtskompatibilität)
  - Neues ACF-Feld `cc_always_active`: konfigurierbar statt hartkodiert
  - Neues ACF-Feld `cc_banner_text_usa`: Zusatztext für Drittstaaten-Hinweis,
    pro Sprache konfigurierbar
  - Kategorie-Aktivierung (Statistik/Marketing/Komfort) bleibt global;
    nur Labels und Beschreibungen sind sprachabhängig
  - Code-Snippets (GA4, Meta Pixel …) bleiben sprachunabhängig in den Flat-Feldern

- **Cookie Consent – JS** (`assets/src/js/components/cookie-notice.js`)
  - Hardkodierte deutsche Fallback-Texte durch sprachneutrale Defaults ersetzt
  - `bannerTextUSA` wird jetzt aus `window.cookieConsent.texts` gelesen (PHP-gesteuert)
  - `bannerTitle` wird im Banner-HTML gerendert wenn gesetzt

- **Share-Buttons – Globale Konfiguration** (`inc/social-share.php`)
  - Neue Admin-Seite Agency Core → Share-Buttons (`agency-core-social-share`)
  - Zentrale Konfiguration: aktivierte Kanäle, Standard-Layout, Label-Text
  - Auto-Insert: optionale automatische Einbindung nach `the_content` für
    konfigurierbare Post-Types
  - Shortcode `[medialab_share]` liest globale Defaults; einzelne Attribute
    können weiterhin pro Instanz überschrieben werden
  - Neue PHP-Template-Funktion `medialab_share( $args )` für Theme-Templates
  - Neuer Kanal `copy` – „Link kopieren" via `navigator.clipboard` mit
    2-Sekunden-Feedback-Label (kein externes Script)

- **Share-Buttons – Gutenberg Block** (`blocks/social-share/`)
  - Neuer ACF-Block `medialab/social-share` in der Kategorie „Design"
  - Block-Inspector: „Globale Einstellungen überschreiben" schaltet
    individuelle Kanal-/Layout-/Label-Auswahl pro Block-Instanz frei
  - `render.php` merged Block-Felder mit globalen Defaults
  - Vorschau-Modus (`"mode": "preview"`) im Editor

#### Changed
- `MEDIALAB_CORE_VERSION` auf `1.9.0` angehoben
- `inc/blocks.php`: `'social-share'` zur ACF-Blocks-Liste hinzugefügt

---

## [1.18.1] - 2026-04-23

### media-lab-agency-core 1.8.5

#### Fixed
- **E-Mail Obfuskierung – Gutenberg Buttons** – `protect_content_emails()` in
  `email-obfuscation.php` baute das `<a>`-Tag bisher komplett neu auf, wodurch
  alle Original-Attribute (insb. `class="wp-block-button__link wp-element-button"`)
  verloren gingen und Buttons nicht mehr korrekt dargestellt wurden. Die Funktion
  modifiziert nun das bestehende Tag chirurgisch: nur `href` wird ersetzt und
  `data-obf-email`/`data-obf-label` werden ergänzt – alle anderen Attribute
  (`class`, `id`, `target`, `rel`, …) bleiben erhalten.

---

## [1.18.0] - 2026-03-26

### custom-theme 1.13.1

#### Added
- **Footer Legal Navigation** – neue Menu-Location `footer-legal` registriert
  - Ausgabe via `wp_nav_menu()` in `footer.php` (Tiefe 1, keine Submenüs)
  - Geeignet für Impressum, Datenschutz, AGB, Cookie-Richtlinie
  - Zuweisung im WP-Admin unter Design → Menüs
- **Footer Legal Styling** – `_footer.scss`
  - `.footer-legal` – dezente horizontale Link-Leiste mit Trennpunkten (`·`)
  - `.footer-legal a` – `font-size-xs`, `color-text-muted`, Hover: `color-primary`
  - `.site-footer__bottom` – Flex-Layout: Copyright links, Legal-Links rechts
  - Responsive: unterhalb 768px gestapelt, linksbündig
- **Credit-Line** – dezenter Agentur-Hinweis ganz unten im Footer
  - Text: „Konzept und Programmierung: Media Lab Tritremmel GmbH"
  - Link auf `https://www.media-lab.at` (öffnet in neuem Tab)
  - Styling: `opacity: 0.6` im Ruhezustand, `opacity: 1` bei Hover
  - Trennlinie (`border-top`) zwischen Legal-Bereich und Credit-Line

---

## [1.17.0] - 2026-03-10

### custom-theme 1.13.0

#### Added
- **WCAG 2.1 AA Audit** – 11 Fixes implementiert
  - Skip-Link für Tastaturnavigation
  - Keyboard-Pause für animierte Elemente
  - Primärfarbe `#ff0000` → `#d40000` (WCAG Kontrastanforderung)
  - Focus-Styles für alle interaktiven Elemente
  - `aria-hidden` auf dekorativen Elementen
  - Alt-Text-Fallback für Bilder ohne Alt-Attribut
  - Heading-Level-Hierarchie korrigiert
  - Touch-Targets auf min. 44×44px vergrößert
  - `prefers-reduced-motion` Media Query eingebaut
  - Kontrast-Fixes für Text auf farbigen Hintergründen
  - Semantische Struktur (`main`, `nav`, `footer` Landmarks)

---

## [1.16.0] - 2026-02-20

### custom-theme 1.12.0 / media-lab-agency-core 1.6.0

#### Added
- **8 Custom Gutenberg Blocks** abgeschlossen (Kategorie „Design")
  - Hero, Testimonial, Team-Mitglied, Logo-Leiste, Logo-Slider (ACF-Blöcke)
  - CTA-Banner, Accordion/FAQ, Icon+Text (Native Blöcke)
- **ACF-Felder** via PHP registriert (`inc/acf-blocks.php`)
