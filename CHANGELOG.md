# Changelog

Alle wesentlichen Änderungen am Media Lab Starter Kit werden hier dokumentiert.
Format basiert auf [Keep a Changelog](https://keepachangelog.com/de/1.0.0/),
Versionierung nach [Semantic Versioning](https://semver.org/lang/de/).

---
## [1.25.0] - 2026-08-12

### media-lab-agency-core 1.20.0

#### Added
- **Heartbeat Monitoring** (`inc/heartbeat.php`) – Push-basiertes
  Monitoring als Ersatz für klassische Pull-Uptime-Checks (UptimeRobot,
  Better-Stack-HTTP-Monitore). Löst wiederkehrende Fehlalarme, die durch
  externe Prober bei Shared-Hosting-typischen Effekten entstehen
  (langsame Antwortzeiten, WAF-/Ping-Blockaden, DNS-Hänger) – die Seite
  meldet sich stattdessen selbst in festem Intervall.
  - Neuer REST-Endpoint `/wp-json/medialab/v1/heartbeat` (Token-Auth via
    `hash_equals()`, Token wird bei Erstaktivierung automatisch generiert)
  - Führt vor jedem Ping einen Mini-Health-Check durch (DB-Verbindung);
    bei Fehlschlag wird `/fail` an die Provider-URL angehängt statt
    stillzuschweigen
  - Kompatibel mit Better Stack Heartbeats und Healthchecks.io (beide
    nutzen dasselbe simple HTTP-GET-Ping-Prinzip)
  - Neue ACF-Options-Page „Heartbeat Monitoring" (`inc/acf-settings.php`):
    Enable-Toggle, Provider-Auswahl (nur informativ), Heartbeat-URL,
    fertig zusammengesetzter REST-Endpoint inkl. Token zum Copy-Paste
    für den Server-Cronjob, Anzeige des letzten erfolgreichen Pings
  - `medialab_heartbeat_get_setting()`: liest Config primär über
    `get_field($name, 'option')` (berücksichtigt ACFs `options_`-Präfix
    bei Optionsseiten), fällt auf direktes `get_option()` zurück –
    bleibt damit auch für Sites ohne ACF-Konfiguration (z. B. Ersteinrichtung
    per WP-CLI) funktionsfähig
  - Empfohlenes Deployment-Modell: zentraler Dispatcher-Cronjob auf
    eigenem Hetzner-Webhosting statt Einzel-Cronjobs pro Client-Site –
    umgeht Cron-Restriktionen bei manchen Hostern (World4You u.a.) und
    reduziert benötigte Cron-Slots auf 1 statt 1 pro Client

#### Removed
- **`inc/security.php` entfernt** – leerer Platzhalter seit dem ersten
  Commit des Repos (`263c7d5`, „Inquiry Setup"), nie mit Inhalt befüllt.
  Tatsächliche Security-Funktionalität war von Anfang an auf
  `hcaptcha.php`, `honeypot.php`, `turnstile.php`,
  `spam-content-filter.php` und `class-mla-security-scanner.php`
  aufgeteilt. Entsprechender `require_once`-Aufruf in
  `media-lab-agency-core.php` ebenfalls entfernt.

---

## [1.24.2] - 2026-08-07

### media-lab-agency-core 1.19.2

#### Fixed
- **Security-Scan meldete auf nginx-Systemen dauerhaft unbehebbare
  Fehler** ("Subdirectory-Fix für wp-content/wp-includes"), inkl.
  wöchentlicher Alarm-E-Mail und rotem ❌ im Admin - obwohl die
  zugrunde liegende `.htaccess`-Prüfung auf nginx grundsätzlich nie
  etwas finden kann (nginx wertet `.htaccess` nicht aus; die
  entsprechende Konfiguration gehört in den Vhost). Betraf praktisch
  jedes Starter-Kit-Projekt mit `/cms/`-Subdirectory-Setup auf nginx.
- `check_uploads_php_blocked()` hatte diese nginx-Erkennung bereits
  (liefert dort `'warn'` statt `'fail'`) - `check_subdirectory_htaccess_fix()`
  jedoch nicht. Beide nutzen jetzt einen gemeinsamen `is_nginx()`-Helper;
  auf nginx liefert der Subdirectory-Check jetzt ebenfalls `'warn'`
  (⚠️ statt ❌, löst keine Alarm-Mail aus - `run_scan_and_notify()`
  reagiert ohnehin nur auf `'fail'`) mit einer nginx-`location`-Block-
  Anleitung statt der Apache-`.htaccess`-Anleitung.
- `check_xmlrpc()` und `check_directory_listing()` prüfen echtes
  Live-Server-Verhalten (HTTP-Requests) und sind dadurch bereits
  server-agnostisch korrekt - deren Fix-Anleitung war aber nur für
  Apache brauchbar. Beide zeigen jetzt zusätzlich die passende
  nginx-Anleitung.
- Neuer Hinweis-Banner auf der Security-Scan-Seite bei erkanntem
  nginx: erklärt kurz, warum manche Prüfungen als Hinweis statt Fehler
  erscheinen, statt das kommentarlos zu ändern.

---

## [1.24.1] - 2026-08-07

### media-lab-agency-core 1.19.1 + custom-theme 1.15.2

#### Fixed
- **`Uncaught SyntaxError: Unexpected token 'export'` in `swiper.js`**
  (Konsole, auf jeder Seite mit `medialab/slider`- oder
  `medialab/logo-slider`-Block). Ursache: `inc/blocks.php` versuchte,
  Swipers rohen Vite-ESM-Chunk (`assets/dist/js/chunks/swiper.js`,
  enthält `export`-Statements) als **klassisches** `<script>` zu laden -
  das kann kein Browser parsen. `window.Swiper`/globales `Swiper` gab es
  dadurch nie.
- **Ursache war ein doppelter, konkurrierender Initialisierungs-Ansatz:**
  Das Theme importiert Swiper bereits korrekt per ESM
  (`custom-theme/assets/src/js/components/ml-slider.js`, Teil des
  regulären Vite-Bundles über `main.js` → `.ml-slider__swiper`) - das
  hat immer funktioniert. Parallel dazu versuchte
  `media-lab-agency-core/assets/js/block-slider.js`, dieselben Elemente
  über ein globales `window.Swiper` nochmal zu initialisieren, das es
  nie gab. `block-slider.js` war dadurch faktisch tot (`el.swiper`-Check
  verhinderte Doppel-Init durch `ml-slider.js`, der eigene Init-Pfad
  schlug immer fehl).
- **Fix:**
  - `media-lab-agency-core/inc/blocks.php`: kaputten `swiper`-Enqueue
    (Script + Style) entfernt; Swiper-CSS kommt bereits über das Theme
    (`main.js` importiert `swiper/css/bundle`).
  - `media-lab-agency-core/assets/js/block-slider.js` **entfernt**
    (redundant/tot). `assets/css/block-slider.css` (unser Skeleton aus
    1.19.0) bleibt unverändert bestehen - hängt nur an Swipers eigener
    `swiper-initialized`-Klasse, unabhängig davon, welches Script
    `new Swiper()` aufruft.
  - `custom-theme/assets/src/js/components/ml-slider.js`: übernimmt
    jetzt zusätzlich den Skeleton-Fallback (`ml-slider__swiper--skeleton-done`)
    in allen Fehlerpfaden (ungültige Config, Swiper-Init wirft Fehler) -
    vorher in `block-slider.js`.
  - `medialab-logo-slider` (`block-logo-slider.js`) ebenfalls
    deaktiviert, gleicher Bug, gleiche Ursache. **Achtung:**
    `block-logo-slider.js` enthält eine WCAG-2.2.2-Fokus-Pause
    (Autoplay pausiert bei Tastaturfokus), die `ml-logo-slider.js`
    (Theme) nicht hat - war durch den Bug aber ohnehin nie aktiv. Vor
    einer echten Reaktivierung müsste diese Logik nach
    `ml-logo-slider.js` portiert werden - **bewusst nicht in diesem Fix
    entschieden**, das ist ein eigenständiges Thema.

#### Bekannter Trade-off (weiterhin offen)
- `medialab/logo-slider` hat aktuell keine WCAG-2.2.2-Fokus-Pause im
  Frontend aktiv (siehe oben). Funktional unverändert zum vorherigen
  Zustand (war vorher durch den Bug ebenfalls inaktiv), aber jetzt ohne
  den zusätzlichen Konsolen-Fehler.

---

## [1.24.0] - 2026-08-06

### media-lab-agency-core 1.19.0

#### Added
- **Skeleton für `medialab/slider` (Stufe 2)** – reiner CSS-Skeleton für
  die Zeitspanne zwischen erstem Paint (Folien bereits im HTML, aber
  ungestylt gestapelt) und abgeschlossener Swiper-Initialisierung.
  Nutzt Swipers eigene `swiper-initialized`-Klasse als CSS-Selektor
  (`:not(.swiper-initialized)`) – **kein zusätzliches JS zum Ein-/
  Ausblenden nötig**, nur ein Sicherheitsnetz in `block-slider.js`
  (`ml-slider__swiper--skeleton-done`), das den Skeleton in allen
  Fehler-/Abbruchpfaden beendet (Swiper-Bundle fehlt, ungültige
  Config, 0 Folien, Init wirft Fehler) – sonst würde bei einem Fehler
  für immer geshimmert statt die rohen Folien zu zeigen. Shimmer-
  Keyframe wird aus dem site-weiten `skeleton.css` (1.18.0) wieder-
  verwendet, nicht neu definiert.
- **Frontend-Verifizierung der nativen-Block-Migration abgeschlossen**
  (offen seit 1.16.0/1.17.x): `medialab/slide` setzt die Klasse
  `swiper-slide` bereits direkt am eigenen `useBlockProps.save()`-Root,
  wodurch die Wrapping-Logik in `block-slider.js` (Kinder ohne
  `.swiper-slide`-Klasse einwickeln) für nativ erzeugte Folien in der
  Praxis nie greift – rein defensiver Fallback für Alt-Content. Bestätigt:
  Migration ist mit dem bestehenden Frontend-JS vollständig kompatibel.

#### Nicht geändert (bewusst)
- **`medialab/parallax` bekommt keinen Skeleton.** Das Hintergrundbild
  wird direkt als Inline-`background-image` in der statischen
  `save()`-Ausgabe gerendert (normales Browser-Bildladen, keine JS-
  Abhängigkeit für die Sichtbarkeit). Der Scroll-Effekt ist reine
  Progressive Enhancement ohne "kaputt bis JS läuft"-Zustand – ein
  Skeleton hätte hier keinen Nutzen, siehe Begründung analog zur
  Wishlist-Entscheidung in 1.23.1.

#### Bekannter Trade-off
- Folien-Bilder nutzen `loading="lazy"`; während der (kurzen) Skeleton-
  Phase ist `.ml-slider__wrapper` per `visibility: hidden` ausgeblendet,
  was den nativen Lazy-Load-Trigger für das erste (oft LCP-relevante)
  Bild geringfügig verzögern kann. In der Praxis vernachlässigbar, da
  Swiper-Init nicht auf Bild-Ladezeit wartet, sondern nur auf das
  bereits geladene Swiper-JS-Bundle – nur der Vollständigkeit halber
  dokumentiert.

---

## [1.23.2] - 2026-08-06

### custom-theme 1.15.1

#### Fixed
- **`Deprecated: Theme ohne sidebar.php`-Notice auf `/shop/`** –
  `add_theme_support('woocommerce')` in `functions.php` deklarierte kein
  `'wc-no-sidebar'`, wodurch WooCommerce auf Shop-/Archiv-Seiten
  `get_sidebar('shop')` aufrief. Da das Theme nie eine `sidebar.php`
  hatte (eigene Sidebar-Systeme: `ajax-filters__sidebar`,
  `media-lab-woocommerce` filter-bar.php), fiel WordPress auf die
  veraltete `wp-includes/theme-compat/sidebar.php` zurück und meldete
  das als Deprecation-Warning. Fix: `'wc-no-sidebar' => true` ergänzt.
  Bestand unabhängig von den Skeleton-/Wishlist-Änderungen dieser
  Woche, ist nur zufällig beim Testen aufgefallen.

---

## [1.23.1] - 2026-08-06

### media-lab-woocommerce 2.0.1

#### Fixed
- Fehlendes Lade-/Sperr-Feedback in `wishlist.js` beim Entfernen bzw.
  Ändern der Menge behoben (Doppelklick-Schutz, Button-/Input-Disable
  während des Requests). Bewusst **kein** Skeleton-Screen hier – die
  Operationen sind für ein Skeleton zu schnell, siehe
  `media-lab-woocommerce/CHANGELOG.md` für die Begründung.

---

## [1.23.0] - 2026-08-05

### media-lab-agency-core 1.18.0

#### Added
- **Zentrale Skeleton-Loading-API** (`inc/skeleton.php`, `assets/css/skeleton.css`,
  `assets/js/skeleton.js`) – wiederverwendbares Feature für Skeleton-Screens
  statt Spinner/Opacity-Dimming bei AJAX-Requests und verzögerter
  JS-Initialisierung (z.B. Swiper). Site-weit als `window.MediaLabSkeleton`
  verfügbar (JS) sowie über `medialab_render_skeleton()` (PHP, für
  serverseitig gerenderte Platzhalter). Vier Varianten: `card`, `list`,
  `text`, `slide`. Farben per CSS Custom Properties
  (`--medialab-skeleton-base`, `--medialab-skeleton-shine`) im Theme
  überschreibbar, Struktur (Größen, Shimmer-Animation) bleibt im Plugin.
  Respektiert `prefers-reduced-motion`.
- Dies ist Stufe 1 einer dreistufigen Skeleton-Einführung: (1) AJAX-Content
  [erledigt], (2) JS-init-abhängige Blocks (Slider/Parallax) [offen],
  (3) initialer Seitenaufbau [offen].

### custom-theme 1.15.0

#### Changed
- **`ajax-filters.js`**: `showLoading()`/`hideLoading()` nutzen jetzt
  `window.MediaLabSkeleton`, um während des AJAX-Requests Skeleton-Karten
  passend zum jeweiligen Template (card/job/project/team/event) direkt im
  Ergebnis-Grid anzuzeigen, statt nur einen Spinner einzublenden und das
  Grid abzudunkeln. Fallback auf das alte Spinner-Verhalten, falls
  `media-lab-agency-core` (noch) nicht geladen ist.

---

## [1.22.7] - 2026-08-04

### media-lab-backup 1.3.4

#### Fixed
- **Verwaister `caffeinate`-Prozess nach Backup-Abschluss** – der in 1.3.3
  eingeführte `launchctl asuser`-Fix startete `caffeinate` korrekt, aber
  `maybe_stop_caffeinate()` killte die falsche PID (die von `launchctl`,
  nicht von `caffeinate`). Fix: gezieltes Beenden per `pkill -f` anhand des
  Kommandostrings. Siehe `media-lab-backup/CHANGELOG.md` für Details.

---

## [1.22.6] - 2026-08-03

### media-lab-backup 1.3.3

#### Fixed
- **caffeinate-Fix funktionierte nicht im asynchronen WP-Cron-Kontext** –
  siehe `media-lab-backup/CHANGELOG.md` für Details. `launchctl asuser`
  reicht die Power-Management-Assertion korrekt in die GUI-Session durch.

---

## [1.22.5] - 2026-08-03

### media-lab-agency-core 1.17.5

#### Added
- **WP All Import Integration** (`inc/integrations/wp-all-import-timeout.php`,
  `inc/integrations/wp-all-import-custom-download.php`) – Zwei Fixes für
  Bilder-Downloads bei WP-All-Import-Importen:
  - Timeout-Fix: `pmxi_image_download_timeout`-Filter erhöht den
    Bilder-Download-Timeout von 5s auf 30s (überschreibbar via
    `mlac_wpai_image_timeout_seconds`).
  - `custom_file_download()`-Helper: Umgeht User-Agent-basiertes Blocking
    durch CDNs/WAFs, die den erkennbaren WP-All-Import-UA stillschweigend
    droppen (Symptom: `cURL error 28`, TCP/TLS-Handshake erfolgreich, aber
    0 bytes empfangen – Tarpitting statt 403). Lädt stattdessen über
    `wp_remote_get()` mit Standard-WordPress-UA herunter.
  - **Erfordert manuelle Einrichtung pro Import-Template**: Bild-Feld auf
    `[custom_file_download({Bildfeld}, "ext")]` umstellen und die Image-Option
    "Use images currently uploaded in wp-content/uploads/wpallimport/files/"
    aktivieren. Backport aus dem Janecka-Projekt (union-glashuette.com
    blockte den Standard-UA).

---

## [1.22.4] - 2026-07-16

### media-lab-agency-core 1.17.3

#### Added
- **Post Navigation – zweiter paralleler Einstiegspunkt im Block Editor**
  (`assets/js/post-navigation.js`) – zusätzlich zum Toolbar-Icon (siehe
  1.17.2) erscheint die Prev/Next-Navigation jetzt wieder als eigenes Panel
  im „Dokument"-Tab der Standard-Seitenleiste (`PluginDocumentSettingPanel`),
  parallel zum `PluginSidebar`-Icon. Beide SlotFills sind unabhängige
  Registrierungen ohne Konflikt.

---

## [1.22.3] - 2026-07-16

### media-lab-agency-core 1.17.2

#### Changed
- **Post Navigation im Block Editor** (`assets/js/post-navigation.js`) – von
  `PluginDocumentSettingPanel` auf `PluginSidebar` umgestellt: Die
  Prev/Next-Navigation bekommt jetzt ein eigenes, dauerhaft sichtbares Icon
  in der oberen Block-Editor-Toolbar (zusätzlich per Menüeintrag im
  „⋮"-More-Menu erreichbar) statt versteckt im „Dokument"-Tab der Seitenleiste
  gesucht werden zu müssen.

#### Known limitation
- Der klassische Prev/Next-Button-Bereich (`edit_form_top`, `inc/post-
  navigation.php`) gehört zum Classic-Editor-Template
  (`wp-admin/edit-form-advanced.php`) und wird im Block Editor grundsätzlich
  nicht mit gerendert. Er greift daher nur bei Post Types, die tatsächlich
  den Classic Editor nutzen; für Block-Editor-Screens ist das
  Gutenberg-Toolbar-Icon (siehe oben) der primäre Einstiegspunkt.

---

## [1.22.2] - 2026-07-16

### media-lab-agency-core 1.17.1

#### Fixed
- **Drag-Handle überlappt Thumbnail in schmalen Spalten** (`assets/js/post-order.js`,
  `assets/css/post-order.css`) – der vorherige Ansatz (Flex-Layout innerhalb von
  `td.column-thumb`) hat nicht zuverlässig funktioniert, da die Spalte fix und
  schmal ist und Handle + Thumbnail sich weiterhin überlappt/umgebrochen haben
  (z. B. WooCommerce-Produktübersicht). Stattdessen bekommt der Drag-Handle
  jetzt eine eigene, dedizierte `<td class="medialab-drag-handle-cell">` als
  erste Zelle jeder Zeile statt in eine bestehende Spalte hinein gerendert zu
  werden. Kopf- und Fußzeile der Tabelle werden per JS um eine passende leere
  `<th>` ergänzt, damit die Spaltenanzahl übereinstimmt. Die alten
  `column-title`/`column-thumb`-Flex-Regeln in `post-order.css` wurden entfernt
  und durch Styles für die neue Handle-Spalte ersetzt.

---

## [1.22.1] - 2026-07-16

### media-lab-agency-core 1.17.0

#### Added
- **Post Navigation** (`inc/post-navigation.php`, `assets/js/post-navigation.js`,
  `assets/css/post-navigation.css`) – neue "Voriger/Nächster"-Navigation direkt
  auf der Post-/Page-/CPT- sowie Taxonomy-Term-Detailseite im Backend, analog zu
  Drittanbieter-Plugins wie "Post Navigation", aber für alle Inhaltstypen inkl.
  Taxonomien und mit Anbindung an die bestehende Post-Order-Funktion:
  - Reihenfolge: menu_order bei aktiver Post Order (`MediaLab_Post_Order`),
    sonst Fallback auf WP-Standard (hierarchische Post Types: Titel A–Z, sonst
    Datum absteigend; Taxonomien: Name A–Z).
  - Berücksichtigt den Filter-/Suchkontext der Ausgangs-Listenansicht (Suche,
    Status, Autor, Monatsarchiv, Taxonomie-Filter), erkannt über den
    HTTP-Referer bzw. über den mitgeführten Query-Param `mlpn_ctx`, der über
    mehrere Navigationsschritte hinweg erhalten bleibt.
  - UI kombiniert klassischen Button-Bereich oberhalb des Titelfelds
    (funktioniert in Classic Editor UND Block Editor via `edit_form_top`) mit
    einem zusätzlichen Gutenberg-Sidebar-Panel ("Document Settings") für Post
    Types mit Block Editor; für Taxonomy-Terms via
    `"{$taxonomy}_term_edit_form_top"`.
  - Erweiterbar über die Filter `medialab_post_navigation_excluded_post_types`,
    `medialab_post_navigation_excluded_taxonomies` und
    `medialab_post_navigation_max_items`.

#### Changed
- **Post Order Einstellungsseite** (`inc/post-order.php`) – neue "Alle Post
  Types aktivieren" / "Alle Taxonomien aktivieren" Checkboxen oberhalb der
  jeweiligen Tabelle; togglen alle Einzel-Checkboxen der Gruppe (die fixe,
  deaktivierte "page"-Zeile bleibt unangetastet) und zeigen bei Teilauswahl
  einen `indeterminate`-Zustand.
- **Post Order Thumbnail-Spalte** (`assets/css/post-order.css`) – `td.column-
  thumb` (z. B. WooCommerce-Produktübersicht) rendert Drag-Handle und
  Thumbnail jetzt über `display:flex` nebeneinander statt überlappend in
  derselben Zelle.

---

## [1.22.0] - 2026-07-16

### custom-theme (Starter-Kit-Theme)

#### Fixed
- **Fatal Error in `customtheme_webp_picture_element()`** (`inc/performance.php`)
  – der 5. Parameter (`$attr`) hatte den strikten Type-Hint `array`. Core ruft
  `wp_get_attachment_image()` aber mit dem Default `$attr = ''` (leerer
  String) auf, wenn kein Attribut-Array übergeben wird – z. B.
  `WC_Meta_Box_Product_Images::output()` im WooCommerce-Produkt-Editor. Das
  führte zu einem TypeError, der die Seite ab dieser Stelle abbrach (z. B.
  WooCommerce-Produkt-Edit-Screen zeigte nur noch die halbe Seite). Type-Hint
  entfernt, Default-Wert `array()` ergänzt (`$attr` wird im Funktionskörper
  ohnehin nicht verwendet). Weitere `wp_get_attachment_image*`-Filter-Callbacks
  im Theme geprüft (`grep`) – keine weiteren betroffen, da
  `wp_get_attachment_image_attributes` erst nach Normalisierung durch Core
  feuert.

---

## [1.21.1] - 2026-06-11

### media-lab-backup 1.3.1

#### Fixed
- **File-Backup Timeout auf Shared Hosting** (`includes/class-mlb-file-backup.php`,
  `includes/class-mlb-sftp.php`, `includes/class-mlb-logger.php`) –
  File-Backups schlugen nach 60 Minuten mit „Job-Timeout" fehl, während
  DB-Backups erfolgreich liefen. Zwei Ursachen behoben:

  **Ursache 1 – SFTP-Upload ohne Chunking** (`class-mlb-sftp.php`):
  `SFTP::SOURCE_LOCAL_FILE` lädt die gesamte Datei in einem Paket in den RAM.
  Bei 500 MB – 3 GB ZIP-Archiven führt das auf Shared Hosting (256–512 MB
  `memory_limit`) zur Erschöpfung. Ersetzt durch manuellen Chunked-Transfer:
  Datei wird in 8-MB-Blöcken via `fread()` gelesen und mit Offset-Parameter
  in `sftp->put()` gesendet. Speicher-Peak sinkt von Dateigröße auf ~16 MB.

  **Ursache 2 – ZIP-Komprimierung CPU-intensiv** (`class-mlb-file-backup.php`):
  `ZipArchive::CM_DEFLATE` komprimiert jeden File beim Hinzufügen — bei
  tausenden JPEG/PNG/MP4-Dateien (die bereits komprimiert sind) entsteht
  massiver CPU-Overhead ohne nennenswerte Größenersparnis. Umgestellt auf
  `ZipArchive::CM_STORE` (keine Komprimierung) — 3–10× schneller. Zusätzlich
  `gc_collect_cycles()` alle 500 Dateien um ZipArchive-internen Puffer
  freizugeben.

  **Timeout-Konstante** (`class-mlb-logger.php`):
  `JOB_TIMEOUT_MINUTES` von 60 auf 240 Minuten erhöht, damit der Logger
  laufende Jobs nicht fälschlicherweise als Timeout markiert.

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
