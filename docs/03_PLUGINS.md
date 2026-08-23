# Plugin-Dokumentation

**Version:** 1.14.0 | **Letzte Aktualisierung:** 2026-08-13

---

## Übersicht

| Plugin | Version | Zweck | Modifizierbar? |
|---|---|---|---|
| media-lab-agency-core | 1.7.0 | Framework + Features | ❌ Nie |
| media-lab-seo | 1.9.1 | SEO-Toolkit + Dashboard + Reports | ✅ Konfigurierbar |
| media-lab-project-starter | 1.0.0 (Scaffold) | Projekt-spezifische CPTs/Taxonomien/ACF – wird pro Projekt dupliziert, nicht identisch deployt | ✅ Wird individuell angepasst |
| advanced-custom-fields-pro | aktuell | Custom Fields | ✅ Konfigurierbar |

> Versionsnummern in dieser Tabelle sind eine Momentaufnahme und driften
> erfahrungsgemäß schnell auseinander (siehe Root-README-Hinweis dazu).
> Verbindlich ist immer der `Version:`-Header der jeweiligen Plugin-
> Hauptdatei.


---

## media-lab-agency-core `v1.7.0`

**Datei:** `cms/wp-content/plugins/media-lab-agency-core/media-lab-agency-core.php`

Dieses Plugin wird **unverändert auf allen Projekten eingesetzt**. Nie direkt modifizieren – stattdessen WordPress-Hooks verwenden.

### Enthaltene Module

| Datei | Inhalt |
|---|---|
| `inc/shortcodes.php` | 44 Shortcodes |
| `inc/ajax-search.php` | AJAX-Suche (Rate-Limit: 20/min) |
| `inc/ajax-filters.php` | Post-Filter (Rate-Limit: 30/min) |
| `inc/ajax-load-more.php` | Load More (Rate-Limit: 30/min) |
| `inc/helpers.php` | `medialab_get_thumbnail()`, `medialab_check_rate_limit()` |
| `inc/smtp.php` | PHPMailer-Konfiguration via wp-config.php Konstanten |
| `inc/svg-support.php` | SVG-Upload mit Allowlist-Sanitizer |
| `inc/activity-log.php` | Activity Log mit DSGVO-IP-Anonymisierung |
| `inc/acf-settings.php` | 10 separate ACF Options Sub-Pages (Plugin Status, Maintenance, Logo, Hero, Cookie Consent, SMTP, Spam-Schutz, Top Header, Mehrsprachigkeit, White Label) |
| `inc/post-order.php` | Drag & Drop Post/Term-Order |
| `inc/white-label.php` | Admin White-Labeling |
| `assets/js/smtp-test.js` | SMTP Test-Mail Admin-Script |
| `inc/maintenance.php` | Maintenance Mode (503, Admin-Bypass, ACF-konfigurierbar) |
| `inc/media-replace.php` | Medien ersetzen ohne Plugin-Verlust der Attachment-ID |
| `inc/cookie-consent.php` | Cookie Consent Manager (Banner, Modal, Toggle-Integration, Snippet-Verwaltung) |
| `inc/hcaptcha.php` | hCaptcha Integration – CF7, WP-Login, WooCommerce (kein Plugin nötig) |
| `inc/hero-image.php` | Hero Image – Subtitle, zwei Buttons (primary/outline/ghost), Höhe, Ausrichtung, Opacity |
| `inc/blocks.php` | Gutenberg Custom Blocks – Registrierung aller 8 Blöcke (ACF + Native), conditional Asset-Loading |

### Gutenberg Custom Blocks

Aktivierung: automatisch aktiv sobald `inc/blocks.php` geladen ist. Alle Blöcke erscheinen unter **Design** im Gutenberg-Editor.

**Übersicht:**

| Block | Typ | Slug | Besonderheit |
|---|---|---|---|
| Hero | ACF | `medialab/hero` | Bild, Overlay, Kicker, Titel, Subtitle, 2× CTA, Höhe, Ausrichtung |
| Testimonial | ACF | `medialab/testimonial` | Zitat, Name, Rolle, Bild, Sterne 1–5, Stil (card/minimal/centered) |
| Team-Mitglied | ACF | `medialab/team-member` | Foto, Name, Rolle, Bio, LinkedIn/Xing/Instagram/E-Mail |
| Logo-Leiste | ACF | `medialab/logo-grid` | Repeater, 3–6 Spalten, Graustufen-Toggle |
| Logo-Slider | ACF | `medialab/logo-slider` | Swiper, Autoplay, Loop, Geschwindigkeit, Graustufen |
| CTA-Banner | Native | `medialab/cta-banner` | RichText, Button-URL, 4 Hintergrundfarben, align full/wide |
| Accordion/FAQ | Native | `medialab/accordion` | `<details>/<summary>`, ARIA, allow-multiple Toggle |
| Icon + Text | Native | `medialab/icon-text` | Emoji/Dashicon, Farbe, Layout top/left |

**ACF Blocks** – PHP-Rendering, kein Build-Step. Felder über ACF-Feldgruppen pflegen.
Jeder Block hat eine `render.php` und eine `block.json` unter `blocks/{name}/`.

**Native Blocks** – JS/block.json via Vite-Build (`vite.config.blocks.js`).
Source: `assets/src/js/blocks.js` → Build: `assets/dist/js/blocks.js`.

**Asset-Loading (conditional):**
```php
// Accordion-JS nur wenn Block auf der Seite
if ( has_block( 'medialab/accordion' ) ) { wp_enqueue_script('medialab-accordion', ...); }

// Swiper nur wenn Logo-Slider auf der Seite
if ( has_block( 'medialab/logo-slider' ) ) { wp_enqueue_script('swiper', ...); }
```

**Neuen Block hinzufügen:**
1. Ordner `blocks/{name}/` anlegen
2. `block.json` + `render.php` (ACF) oder `edit.js`-Eintrag in `blocks.js` (Native) erstellen
3. Block-Slug in `medialab_register_acf_blocks()` oder `medialab_register_native_blocks()` eintragen


### SMTP-Konfiguration

Credentials via `wp-config.php` Konstanten (Passwort landet nie in der DB):

```php
define('MEDIALAB_SMTP_ENABLED',   true);
define('MEDIALAB_SMTP_HOST',      'smtp.example.com');
define('MEDIALAB_SMTP_PORT',      587);
define('MEDIALAB_SMTP_USER',      'user@example.com');
define('MEDIALAB_SMTP_PASS',      'geheimes-passwort');
define('MEDIALAB_SMTP_ENC',       'tls');   // tls | ssl | ''
define('MEDIALAB_SMTP_FROM',      'noreply@example.com');
define('MEDIALAB_SMTP_FROM_NAME', 'Meine Website');
```

Alternativ (weniger sicher): Konfiguration via **Agency Core → E-Mail / SMTP**.

### SVG-Uploads

SVG-Upload ist auf **Administratoren beschränkt**. Uploads werden automatisch sanitiert:
- Entfernt: `<script>`, `<foreignObject>`, `<animate>`, externe `<use href>`, alle `on*`-Handler
- Erlaubt: Definierte Allowlist für sichere SVG-Tags und -Attribute


### Maintenance Mode

Aktivierung unter **Agency Core → Maintenance Mode / Wartungsmodus**.

- HTTP 503 + `Retry-After: 3600` (SEO-konform)
- Eingeloggte Admins sehen die normale Website + orangen Admin-Bar-Hinweis
- Konfigurierbar: Überschrift, Nachricht, Datum, Logo, Browser-Titel
- Notfall-Fallback ohne Backend:

```php
// wp-config.php
define('MEDIALAB_MAINTENANCE_MODE', true);
```

### Media Replace

Ermöglicht das Ersetzen von Mediendateien ohne Verlust der Attachment-ID oder Verwendungen im Content. Kein Drittanbieter-Plugin nötig.

**Zugang:**
- Medien → Attachment bearbeiten → **„Neue Datei hochladen"**
- Medien-Bibliothek (Listenansicht) → **„Datei ersetzen"**

**Was passiert beim Ersetzen:**
- Alte Datei wird überschrieben (optional: Dateiname beibehalten)
- Alle Thumbnails/Bildgrößen werden neu generiert
- Attachment-ID, URL und alle Verwendungen im Content bleiben unverändert
- MIME-Typ wird aktualisiert wenn sich der Dateityp ändert
- Eintrag im Activity Log

### Cookie Consent Manager

Aktivierung: automatisch aktiv. Konfiguration unter **Agency Core → Cookie Consent**.

**Features:**
- Banner mit „Alle akzeptieren" / „Einstellungen" / „Ablehnen"
- Settings Modal mit Toggle pro Kategorie
- Floating Button 🍪 (immer sichtbar, unten links) öffnet Modal jederzeit
- 4 Kategorien: Notwendig (immer aktiv), Statistik, Marketing, Komfort
- Consent gespeichert als JSON in `localStorage` inkl. Version + Timestamp

**Code-Snippets im Backend verwalten:**

Unter **Cookie Consent → Code-Snippets** können pro Kategorie Head- und Body-Code eingetragen werden:

| Kategorie | Wann geladen | Typische Dienste |
|---|---|---|
| Notwendig | Immer (kein Consent nötig) | Eigene Consent-APIs, DSGVO-Chat |
| Statistik | Nach Zustimmung | GA4, Matomo, Hotjar |
| Marketing | Nach Zustimmung | Meta Pixel, Google Ads, LinkedIn Insight |
| Komfort | Nach Zustimmung | YouTube API, Google Maps JS |

**Public JS-API:**
```javascript
// Consent prüfen
window.CookieConsent.hasConsent('statistics'); // → true/false

// Modal programmatisch öffnen
window.CookieConsent.openSettings();

// Auf Consent-Änderungen reagieren
document.addEventListener('cookies:changed', (e) => {
    if (e.detail.statistics) { /* GA4 aktivieren */ }
    if (e.detail.marketing)  { /* Pixel aktivieren */ }
});
```

**Consent-Version erhöhen** (erzwingt erneute Zustimmung bei allen Besuchern):
Unter *Cookie Consent → Consent-Version* die Zahl erhöhen.


### Security-Features

- **Rate-Limiting:** Alle öffentlichen AJAX-Endpunkte sind per Transient begrenzt
- **IP-Anonymisierung:** Activity Log anonymisiert IPs nach 90 Tagen via WP-Cron
- **Output-Escaping:** Alle Shortcode-Ausgaben mit `esc_html()`, `esc_attr()`, `esc_url()`

### Helper-Funktionen

```php
// Responsives Thumbnail-Image (srcset + lazy loading)
echo medialab_get_thumbnail($post_id, 'medium', ['class' => 'mein-bild']);
medialab_the_thumbnail($post_id, 'large'); // direkte Ausgabe

// Rate-Limiting in eigenen AJAX-Handlern
if (!medialab_check_rate_limit('meine_action', 20, 60)) {
    wp_send_json_error(['message' => 'Too many requests.'], 429);
}
```

---


### UI-Features (Logo / Globale Einstellungen)

Zwei optionale UI-Komponenten werden über ACF zentral gesteuert:

| Feld | Name | Standard | Beschreibung |
|---|---|---|---|
| Back-to-Top Button | `btt_enabled` | ✅ An | Einblend-Button zum Seitenanfang nach 300px Scroll |
| Scroll Progress Bar | `scroll_progress_enabled` | ❌ Aus | Fortschrittslinie oben – nur auf `single.php` |

**Back-to-Top Button** (`footer.php` → `back-to-top.js` → `_back-to-top.scss`):
- SVG-Chevron, Hover-Animation, Keyboard-Support (Enter/Space)
- Nur im DOM wenn `btt_enabled = 1` → JS initialisiert nur wenn Element vorhanden

**Scroll Progress Bar** (`header.php` → `scroll-progress.js` → `_scroll-progress.scss`):
- 3px Linie, Farbe `$color-primary`, Glow-Dot am rechten Ende
- CSS Custom Property `--scroll-progress`, per `requestAnimationFrame` aktualisiert
- ARIA `role="progressbar"` + `aria-valuenow`
- Nur auf `is_single()` + nur wenn `scroll_progress_enabled = 1`


### hCaptcha

DSGVO-konformer CAPTCHA-Schutz ohne Drittanbieter-Plugin. Konfiguration unter **Agency Core → Spam-Schutz / E-Mail Obfuskierung**.

**Voraussetzung:** Kostenloser Account auf [hcaptcha.com](https://hcaptcha.com) → Site anlegen → Site Key + Secret Key kopieren.

**Abgedeckte Formulare:**

| Formular | Hook (Frontend) | Hook (Validierung) |
|---|---|---|
| Contact Form 7 | `wpcf7_form_elements` | `wpcf7_validate` |
| WP-Login | `login_form` | `authenticate` (Prio 30) |
| WooCommerce Checkout | `woocommerce_review_order_before_submit` | `woocommerce_checkout_process` |
| WooCommerce Registrierung | `woocommerce_register_form` | `woocommerce_process_registration_errors` |

**Widget-Optionen:**

| Einstellung | Optionen | Beschreibung |
|---|---|---|
| Theme | `light` / `dark` | Passt sich dem Design an |
| Größe | `normal` / `compact` / `invisible` | Invisible: kein sichtbares Widget, nur bei verdächtigem Verhalten |

**Öffentliche Funktionen:**

```php
// Status prüfen (aktiv + Keys gesetzt?)
medialab_hcaptcha_active(): bool

// Widget-HTML ausgeben
medialab_hcaptcha_widget( string $id = '' ): string

// Token serverseitig verifizieren
medialab_hcaptcha_verify(): bool|WP_Error
```

**Script-Einbindung:** `hcaptcha-api` wird nur auf Seiten geladen, auf denen ein Widget sichtbar ist – kein unnötiges JS auf allen Seiten.


### Hero Image

Konfiguration unter **Agency Core → Hero Image**.

**Neue Felder (v1.9.0):**

| Feld | Key | Typ | Beschreibung |
|---|---|---|---|
| Untertitel | `hero_image_subtitle` | text | Optionaler Untertitel unter dem Seitentitel |
| Button 1 Text | `hero_btn1_text` | text | Beschriftung des ersten Buttons |
| Button 1 URL | `hero_btn1_url` | url | Ziel des ersten Buttons |
| Button 1 Stil | `hero_btn1_style` | select | `primary` / `outline` / `ghost` |
| Button 2 Text | `hero_btn2_text` | text | Zweiter optionaler Button |
| Button 2 URL | `hero_btn2_url` | url | Ziel des zweiten Buttons |
| Button 2 Stil | `hero_btn2_style` | select | `primary` / `outline` / `ghost` |
| Ausrichtung | `hero_image_align` | select | `left` / `center` / `right` |
| Höhe | `hero_image_height` | select | `sm` / `md` / `lg` / `xl` |
| Vertikale Position | `hero_image_vpos` | select | `top` / `middle` / `bottom` |
| Bild-Opacity | `hero_image_opacity` | range | 0–100, überschreibt globale Einstellung |

**Globale Felder (Options):**

| Feld | Key | Beschreibung |
|---|---|---|
| Standard-Höhe | `hero_default_height` | Fallback wenn per-Post-Feld leer |
| Standard-Ausrichtung | `hero_default_align` | Fallback für Textausrichtung |

**CSS-Klassen am Hero-Element:**

```
.hero-image--sm / --md / --lg / --xl          Höhe
.hero-image--align-left / --center / --right  Textausrichtung
.hero-image--vpos-top / --middle / --bottom   Vertikale Bildposition
```

**Button-Varianten `.btn--light`:**
- `primary` → weißer Button mit Primärfarbe-Text
- `outline` → transparenter Button mit weißem Rand
- `ghost` → komplett transparent


---


## Shortcode: `[spoiler]` / `[read_more]`

Zeigt Inhalte zunächst abgeschnitten mit Fade-Overlay und Chevron-Button an.
Beim Klick auf den Pfeil öffnet sich der vollständige Content.

### Parameter

| Parameter | Werte | Standard | Beschreibung |
|---|---|---|---|
| `open_text` | String | `Mehr anzeigen` | Aria-Label des Buttons (geschlossen) |
| `close_text` | String | `Weniger anzeigen` | Aria-Label des Buttons (geöffnet) |
| `open` | `true` / `false` | `false` | Startet den Spoiler im geöffneten Zustand |
| `style` | `default` / `bordered` / `minimal` | `default` | Visueller Stil |
| `icon` | `true` / `false` | `true` | Chevron-Icon anzeigen |
| `show_on` | `all` / `desktop` / `mobile` | `all` | Auf welchem Viewport der Toggle aktiv ist |

### `show_on` – Viewport-Steuerung

| Wert | Desktop (≥ 768 px) | Mobile (< 768 px) |
|---|---|---|
| `all` | Toggle aktiv | Toggle aktiv |
| `desktop` | Toggle aktiv | Content immer vollständig sichtbar |
| `mobile` | Content immer vollständig sichtbar | Toggle aktiv |

Auf dem Ausnahme-Viewport wird die Klasse `spoiler--passive` gesetzt:
der Button wird ausgeblendet, der Content vollständig ohne Fade dargestellt.
Bei Browser-Resize reagiert der Component automatisch (Debounce 150 ms).
Breakpoint-Grenze: **768 px** (entspricht `$breakpoint-md`).

### Verwendung

```
[spoiler]
Dieser Inhalt wird auf allen Geräten hinter dem Fade versteckt.
[/spoiler]

[spoiler show_on="mobile" open_text="Mehr lesen"]
Auf Desktop immer vollständig sichtbar, auf Mobile togglebar.
[/spoiler]

[spoiler show_on="desktop" style="bordered"]
Auf Mobile immer vollständig sichtbar, auf Desktop togglebar.
[/spoiler]

[spoiler open="true"]
Startet bereits geöffnet.
[/spoiler]
```

### Alias

`[read_more]` ist ein Alias für `[spoiler]` mit den Defaults
`style="minimal"` und `open_text="Weiterlesen"`.

### Design-Verhalten

- Content ist immer sichtbar, wird aber auf `max-height: 180px` begrenzt
- Ein linearer Gradient-Fade blendet den unteren Rand aus (`--color-bg`)
- Der Chevron-Button sitzt unterhalb des Fades, ohne Rahmen oder Hintergrund
- Im offenen Zustand: `max-height: none`, Fade-Opacity auf `0`
- Sichtbarkeitssteuerung vollständig via CSS (`max-height` + `opacity`) – kein `display: none` via JS

### Technische Dateien

| Datei | Pfad |
|---|---|
| PHP Shortcode | `cms/wp-content/plugins/media-lab-agency-core/inc/shortcodes.php` |
| JavaScript | `cms/wp-content/themes/custom-theme/src/js/components/Spoiler.js` |
| SCSS | `cms/wp-content/themes/custom-theme/src/scss/components/_spoiler.scss` |

### Changelog

| Version | Änderung |
|---|---|
| 1.7.0 | `show_on`-Parameter hinzugefügt (Viewport-Steuerung: `all` / `desktop` / `mobile`) |
| 1.7.0 | Visuelles Redesign: Fade-Overlay + reiner Chevron-Button als Standard |
| 1.7.0 | Sichtbarkeitslogik von JS (`display:none`) auf CSS (`max-height` + `opacity`) umgestellt |
| 1.7.0 | Button-Position von oberhalb nach unterhalb des Contents verschoben |
| 1.7.0 | `spoiler--passive`-Klasse für Ausnahme-Viewport (kein Toggle, kein Fade) |

---


## media-lab-seo `v1.9.1`

**Datei:** `cms/wp-content/plugins/media-lab-seo/media-lab-seo.php`

Pro Projekt aktivieren und konfigurieren unter **SEO Toolkit**. Benötigt
zwingend `media-lab-agency-core` als aktives Plugin (Dependency-Check,
sonst automatische Selbst-Deaktivierung mit Admin-Notice).

**Module (Stand 1.9.1):**

| Modul | Datei | Beschreibung |
|---|---|---|
| GSC API | `inc/class-gsc-api.php` | OAuth2, Token-Management, Datenabruf |
| GA4 API | `inc/class-ga4-api.php` | GA4 OAuth (separater Flow von GSC) |
| Analytics-Adapter | `inc/class-analytics-adapter.php` | Pluggbare Schnittstelle: GA4 / Matomo / eigene Implementierung |
| Settings | `inc/class-settings.php` | Einstellungsseite: Grid aus Cards (SEO inkl. Bing, Google Search Console, GA4, Matomo, Wöchentlicher Report) |
| SEO | `inc/class-seo.php` | Open Graph, Twitter Cards, Canonical, GSC- + Bing-Verifizierungs-Tags |
| Schema | `inc/class-schema.php` | Schema.org JSON-LD |
| Breadcrumbs | `inc/class-breadcrumbs.php` | Breadcrumbs + Schema.org BreadcrumbList |
| Redirects | `inc/class-redirects.php` | 301/302-Manager |
| Consent-Stats | `inc/class-consent-stats.php` | DSGVO Consent-Rate-Tracking (seit 1.9.0), liest read-only aus Agency-Core-Tabelle `wp_mlt_consent_log` |
| SEO Dashboard | `inc/class-seo-dashboard.php` | Eigenständiger Admin-Menüpunkt + WP-Dashboard-Widget, konfigurierbarer Datumsbereich (seit 1.8.0) |
| Report Template | `inc/class-report-template.php` | HTML-Mail, Inline-CSS |
| Report Mailer | `inc/class-report-mailer.php` | WP-Cron wöchentlich - **einziger** Handler auf `mlt_weekly_report` (siehe Duplikat-Mail-Fix 1.9.0 im CHANGELOG des Plugins) |
| Report Recipients | `inc/report-recipients.php` | Dynamische Empfänger-Liste statt nur Admin-E-Mail (seit 1.6.0) |
| Report Schedule | `inc/report-schedule.php` | Konfigurierbarer Versandtag/-uhrzeit statt fix Montag 08:00 (seit 1.6.0) |

**Menü-Struktur:**
```
SEO Toolkit (Top-Level, eigenes Icon)
├── Einstellungen   (slug: media-lab-seo)   → Cards: SEO (inkl. Bing-Tag), Google Search Console (OAuth), GA4, Matomo, Wöchentlicher Report
└── Dashboard       (slug: mlt-dashboard)   → KPI-Kacheln, Top-Keywords/-Seiten, Datumsbereich-Picker, Consent-Rate-Card, „Mit Google verbinden"
```
Einstellungen und Dashboard sind zwei **gleichrangige** Untermenüpunkte
(getrennte `add_submenu_page()`-Aufrufe in `class-settings.php` bzw.
`class-seo-dashboard.php`), keine Verschachtelung.

### Features

| Feature | Beschreibung |
|---|---|
| Schema.org JSON-LD | Organization, WebSite, Article, Product, BreadcrumbList |
| Open Graph | Facebook und LinkedIn sharing |
| Twitter Cards | Erweiterte Twitter-Vorschauen |
| Canonical URLs | Duplicate Content verhindern |
| Breadcrumbs | Automatische Brotkrummen-Navigation |
| Google Search Console | Vollständige OAuth2-Anbindung (nicht nur ein Verifizierungs-Tag) |
| Bing Webmaster Tools | Verifizierungs-Meta-Tag (`msvalidate.01`) |
| Consent-Rate-Tracking | DSGVO-Auswertung, wie viele Besucher Analytics-Consent geben |

### Schema-Typen

- **Organization** (Homepage): Firmeninfos
- **WebSite** (Global): Site-weite Daten inkl. SearchAction
- **Article** (Blogposts): Autor, Datum, Bild
- **Product** (WooCommerce): Preis, Verfügbarkeit
- **BreadcrumbList** (alle Seiten): Navigation

### Breadcrumbs im Template

```php
if (function_exists('medialab_seo_breadcrumbs')) {
    medialab_seo_breadcrumbs([
        'separator'   => ' › ',
        'home_title'  => 'Home',
        'wrapper_class' => 'breadcrumbs',
    ]);
}
```

### Konfiguration

Ausführliche Einrichtung (GSC-OAuth, Bing, GA4, Matomo, Report-Empfänger)
siehe [13_SEO.md](13_SEO.md) bzw. die Plugin-eigene README unter
`cms/wp-content/plugins/media-lab-seo/README.md`.

---

## media-lab-project-starter `v1.0.0` (Scaffold)

**Datei:** `cms/wp-content/plugins/media-lab-project-starter/media-lab-project-starter.php`

Anders als die übrigen Plugins wird dieses Plugin **nicht** identisch über
alle Kundenprojekte verteilt, sondern pro neuem Projekt **dupliziert und
individuell angepasst** (eigener CPT-/Taxonomie-/ACF-Bedarf je Kunde). Für
Details zum Duplizieren/Umbenennen siehe die ausführlichere Beschreibung
im Root-README (Abschnitt „media-lab-project-starter — Projekt-Scaffold").

Enthält bewusst nur ein minimales Grundgerüst:

| Datei | Inhalt |
|---|---|
| `inc/custom-post-types.php` | Projekt-spezifische CPTs (leer/Beispiel im Scaffold) |
| `inc/taxonomies.php` | Projekt-spezifische Taxonomien |
| `inc/acf-config.php` | Projekt-spezifische ACF-Felder |

Benötigt `media-lab-agency-core` als aktives Plugin (Dependency-Check über
`medialab_core_version()`, siehe `inc/helpers.php` in Agency Core).

Keine eigenen Composer-Abhängigkeiten, kein eigenes `uninstall.php`-
relevantes Datenaufkommen über CPT-Content hinaus (siehe
`docs/BACKLOG.md`, Struktur/Prozess-Punkte).

---

## Advanced Custom Fields Pro

Wird für ACF Options Pages und alle Custom Fields benötigt. Lizenz unter [advancedcustomfields.com](https://www.advancedcustomfields.com/).

### ACF JSON-Sync

Feldgruppen werden als JSON in `acf-json/` versioniert. Automatisch aktiv nach Plugin-Aktivierung.

```bash
# Nach Git-Pull: ACF Felder synchronisieren
# WordPress-Admin → Eigene Felder → Synchronisieren verfügbar
```

---

## Plugins die NICHT enthalten sind

Diese Plugins können bei Bedarf pro Projekt ergänzt werden:

| Plugin | Zweck | Hinweis |
|---|---|---|
| WooCommerce | E-Commerce | SCSS-Partial `_woocommerce.scss` bereits vorhanden |
| media-lab-events | Event-Management | Optional, liegt im Repo |

> **Korrektur (2026-08-13):** `media-lab-analytics` stand hier vorher als
> separates, optionales Plugin gelistet - das ist veraltet. Analytics
> (GA4/GTM-Frontend-Tracking + GA4/Matomo-Adapter fürs SEO-Dashboard)
> wurde am 25.03.2026 vollständig in `media-lab-seo` integriert (siehe
> CHANGELOG des Plugins, 1.4.0). Ein eigenständiges
> `media-lab-analytics`-Plugin existiert im Repo nicht mehr (verifiziert).

---

**Weiter:** [docs/04_SHORTCODES.md](04_SHORTCODES.md) – Shortcode-Referenz
