# Changelog

Alle wesentlichen Änderungen werden in dieser Datei dokumentiert.
Format basiert auf [Keep a Changelog](https://keepachangelog.com/de/1.0.0/),
Versionierung nach [Semantic Versioning](https://semver.org/lang/de/).

**Hinweis zur Versionsnummerierung:** Die Versionshistorie vor 1.4.0 wurde
rekonstruiert und hier neu, durchgehend nummeriert. Grund: Die ursprünglichen
Versionsnummern in Plugin-Header/Konstante liefen zeitweise nicht monoton -
am 10. März 2026 wurde bereits `1.3.0` erreicht (SEO-Dashboard, GSC OAuth2
API, Report-Mailer, GA4+Matomo-Adapter), beim Merge mit `media-lab-toolkit`
am 25. März sprang die Nummer aber fälschlich auf `1.1.0` zurück - siehe
1.4.0 unten. Im Gegensatz zu einem ähnlichen Fund bei `media-lab-bookings`
ist hierbei **kein Feature verloren gegangen**, nur die Zählung war falsch.

---

## [1.15.0] - 2026-10-05

### media-lab-seo 1.15.0

#### Added
- **Prüfung der Verifizierungs-Codes (Search Console und Bing)** (`MLT_Settings::parse_verification()`).
  Die Felder „Google Search Console – Verification Code" und „Bing Webmaster Tools – Verification
  Code" speichern nur noch gültige Codes:
  - akzeptiert wird der reine Code, das TXT-Format `google-site-verification=CODE` bzw.
    `msvalidate.01=CODE` (auch samt Anführungszeichen, wie die DNS-Anzeige sie liefert) und ein
    **ganz eingefügter `<meta …>`-Tag** – daraus wird der `content`-Wert übernommen (Info-Meldung);
  - **abgelehnt** (nicht gespeichert, Fehlermeldung oben auf der Seite) werden URLs, Domains und
    Property-Kennungen wie `sc-domain:…`, Leerzeichen/Sonderzeichen, unplausible Längen und
    Meta-Tags eines anderen Dienstes (z. B. Bing-Tag im Google-Feld);
  - bei einer abgelehnten Eingabe bleibt ein vorheriger *gültiger* Wert erhalten, ein ungültiger
    Altwert wird geleert.
- **Hinweis zur Datenverzögerung im E-Mail-Report:** Fehlen im Säulendiagramm die jüngsten Tage
  (siehe 1.14.1), steht darunter „Die letzten 2 Tage werden noch nicht dargestellt
  (Datenverzögerung)." – derselbe, übersetzbare Text wie im Dashboard (`MLT_Chart::lag_note()`).

#### Fixed
- **Ungültiger Inhalt wurde als Meta-Tag ausgegeben:** Das Feld wurde unbesehen gespeichert und
  ausgegeben – steht dort z. B. die Property-URL, entstand
  `<meta name="google-site-verification" content="https://…">`, das nichts verifiziert. Die
  Ausgabe im `<head>` (`MLT_SEO::verification_tags()`) prüft den gespeicherten Wert jetzt erneut:
  Bestehende Fehleinträge auf bereits ausgerollten Sites verschwinden daher **ohne Zutun** aus dem
  Quelltext, ein eingefügter ganzer Tag erscheint nur noch einfach (nicht mehr als Tag im Tag).
  Auf der Einstellungsseite weist ein Warnhinweis auf einen ungültig gespeicherten Wert hin.

---

## [1.14.1] - 2026-10-05

### media-lab-seo 1.14.1

#### Fixed
- **Verlaufs-Chart und Mail-Säulen stürzten am letzten Tag auf „0" ab.** Google liefert
  Tageswerte mit Verzögerung; für die jüngsten Tage fehlen die Zeilen einfach, und das Plugin
  füllte sie mit 0 auf – ein scheinbarer Einbruch am Ende des Zeitraums. Fehlen am Ende des
  Zeitraums 1–3 Tage komplett (nur bei Zeiträumen bis kurz vor heute), werden sie jetzt nicht
  mehr gezeichnet, im Vergleichszeitraum werden gleich viele Tage weggelassen. Die Legende
  nennt den tatsächlich dargestellten Zeitraum, im Dashboard steht ein Hinweis
  „Die letzten 2 Tage werden noch nicht dargestellt (Datenverzögerung)".
  (`MLT_Timeseries::trailing_lag()`, Konstante `MAX_LAG_DAYS`)
- **Dezimalkomma statt Punkt, Position mit Nachkommastelle:** Ø CTR und Ø Position erschienen als
  „12.2%" und – im Dashboard und im WP-Widget – gerundet als „5" (neben einer Veränderung von
  „−1,3"). Jetzt „12,2 %" und „5,2" im Dashboard, im WP-Widget und im E-Mail-Report.
- **WP-Dashboard-Widget „SEO Übersicht":** Icon und Titel standen an den entgegengesetzten
  Rändern der Titelleiste (WordPress legt Titel per Flexbox auseinander). Der Titel steht jetzt
  in einem `<span>`. Die Fußzeile nennt den tatsächlichen Zeitraum („07.09. – 03.10.") statt
  „Letzte 28 Tage".

---

## [1.14.0] - 2026-10-05

### media-lab-seo 1.14.0

#### Added
- **Veränderung pro Zeile in „Top Keywords" und „Top Seiten"** – im SEO-Dashboard und im
  E-Mail-Report. Unter den Klicks steht die **absolute** Veränderung gegenüber dem
  Vergleichszeitraum (z. B. „▲ +30", „▼ −8"; bei kleinen Zahlen aussagekräftiger als
  Prozentwerte), unter der Position die Verbesserung/Verschlechterung (kleiner = besser, grün).
  Der Dashboard-Tooltip nennt den Vergleichswert; die Kartenüberschrift zeigt „Δ vs. Vorperiode".
  - Abgleich über die ersten 500 Zeilen des Vergleichszeitraums (je eine zusätzliche, gecachte
    Abfrage für Keywords und Seiten). Einträge, die dort fehlen, sind „neu" (nur Klicks, keine
    Positionsveränderung).
  - Gilt derselbe Vergleichsmodus wie überall (Vorperiode/Vorjahr/aus); keine Zeilen-Deltas
    ohne belastbare Vergleichsbasis (kein Vergleich, jenseits der 16-Monats-Grenze, Vergleich
    ohne Daten, fehlgeschlagene Abfrage).
  - Neue Methoden `MLT_Compare::row_deltas()` und `MLT_Delta::html_mail_inline()`, neuer
    Delta-Typ `abs` in `MLT_Delta::calc()`; `MLT_Compare::fetch()` nimmt optional die aktuellen
    Listen entgegen (`$queries`, `$pages`) und liefert `query_deltas` / `page_deltas` zurück.
- **Eindeutige Seiten-Beschriftung** (`MLT_Compare::page_labels()`): Normalerweise nur der Pfad.
  Haben mehrere URLs denselben Pfad (z. B. `https://x.at/` und `https://www.x.at/` – Hinweis auf
  fehlende Weiterleitungen/Canonicals), steht zusätzlich der Host, im Zweifel die volle URL.
  Vorher erschien dieselbe Startseite zweimal als „/".

---

## [1.13.0] - 2026-10-05

### media-lab-seo 1.13.0

#### Added
- **Grafiken im E-Mail-Report** (`MLT_Chart::mail_columns()`, `mail_bar()`, `bucketize()` in
  `inc/class-chart.php`). E-Mail-Programme führen kein JavaScript aus, Gmail/Outlook zeigen
  kein Inline-SVG und blockieren externe Bilder – die Grafiken bestehen deshalb nur aus
  verschachtelten HTML-Tabellen mit festen Zellhöhen und Hintergrundfarben (funktioniert
  auch im Outlook-Desktop-Renderer; keine Bilder, keine Klassen, kein `<style>`-Block).
  - **Säulendiagramm** „Klicks" unter den Search-Console-Kacheln und „Seitenaufrufe" unter den
    Analytics-Kacheln: aktueller Zeitraum (farbig) neben dem Vergleichszeitraum (grau), mit
    Legende, Spitzenwert und Anfangs-/Enddatum. Bis 31 Tage pro Tag, bis 120 Tage pro Woche,
    darüber je 4 Wochen. Die Blöcke werden vom Ende her gebildet; ein unvollständiger Rest am
    Anfang des Zeitraums entfällt.
  - **Balken in den Listen:** Top-Keywords und Top-Seiten (Anteil am besten Eintrag, nach
    Klicks), Traffic-Quellen (Anteil am besten Eintrag) mit Prozentanteil an allen Sessions.
  - Ohne Vergleich (oder jenseits der 16-Monats-Grenze der Search Console) entfallen nur die
    grauen Vergleichsbalken. Ohne Tageswerte (Ausfall, keine Daten) erscheint der Report wie
    bisher ohne Säulendiagramm.
- **Größen-Sicherung:** Gmail kürzt Mails ab ca. 102 KB. Überschreitet das Report-HTML 80 KB
  (z. B. durch sehr lange Keywords/URLs), wird der Report ohne Säulendiagramme gesendet.
  Limit per Filter `mlt_report_max_html_bytes` änderbar. Typische Größe: ca. 50 KB bei
  28 Tagen mit Vergleich, ca. 40 KB ohne.
- Report-Rohdaten (Filter `mlt_weekly_report_html`) enthalten den Key `charts`
  (Tageswerte, dieselbe Quelle wie der Dashboard-Chart).

---

## [1.12.0] - 2026-10-05

### media-lab-seo 1.12.0

#### Added
- **Verlaufs-Chart im SEO-Dashboard** (`inc/class-chart.php`, neu): Karte „Verlauf" mit Reitern
  Klicks · Impressionen · Seitenaufrufe · Sessions. Tageswerte des aktuellen Zeitraums als
  Linie, der Vergleichszeitraum (Vorperiode/Vorjahr, siehe 1.11.0) gestrichelt dahinter.
  Hover zeigt pro Tag den Wert, den Vergleichstag und die Veränderung. Die Charts werden als
  Inline-SVG serverseitig gerendert – keine JavaScript-Bibliothek, kein CDN, nur ein paar
  Zeilen JS zum Umschalten der Reiter. Ohne Daten (oder bei fehlgeschlagenem Abruf) fehlt
  der jeweilige Chart; bei „Kein Vergleich" bzw. jenseits der 16-Monats-Grenze der Search
  Console entfällt nur die gestrichelte Linie.
- **Zeitreihen-Abrufe:** `MLT_GSC_API::get_timeseries()`, `MLT_GA4_API::get_timeseries()`
  (6 h Cache, Fehlerabrufe werden nicht gecacht) sowie neues optionales Interface
  `MLT_Analytics_Timeseries_Interface`, umgesetzt für GA4 (OAuth und Service-Account) und
  Matomo. Eigene Adapter (Filter `mlt_analytics_adapter`) müssen es nicht implementieren –
  dann zeigt das Dashboard nur die Search-Console-Charts.
- **Test-Report:** Der Button in den Einstellungen sendet jetzt den **echten Report mit
  aktuellen Zahlen** (Betreff „[TEST] …") an die erste ausgefüllte Adresse – nicht an alle
  Empfänger. Ändert den Versandstatus (`mlt_last_report_sent/_status`) nicht. Cron-Versand
  und Test teilen sich `MLT_Report_Mailer::render()`.

#### Fixed
- **Wöchentlicher Report wurde nie automatisch versendet, wenn er neu aktiviert wurde:**
  `inc/report-schedule.php` plante den Cron-Event unter dem Namen `mlt_send_weekly_report`,
  der Mailer (`MLT_Report_Mailer`) lauscht aber auf `mlt_weekly_report` – der geplante Event
  hatte keinen Handler. Die Einstellungsseite zeigte deshalb dauerhaft „Nächster geplanter
  Versand: —". Nur Sites, bei denen noch ein älterer `mlt_weekly_report`-Event existierte
  (z. B. aus der Zeit vor 1.6.0), versendeten zufällig weiter – bis der Event durch Aus-/Einschalten
  oder eine Deaktivierung verloren ging. Der Hook-Name ist jetzt einheitlich `mlt_weekly_report`
  (Konstante `MLT_REPORT_CRON_HOOK`), der frühere Name wird aufgeräumt.
  Die Planung folgt dem Report-Schalter: aktiv und ohne Termin → wird geplant (auch nach einem
  Update), ausgeschaltet → vorhandene Termine werden entfernt. Bestehende, korrekte Termine
  bleiben unverändert.
- **Test-Mail-Button tat nichts:** `assets/admin.js` las das seit 1.6.0 nicht mehr vorhandene
  Feld `#mlt_report_email` (→ JavaScript-Fehler, kein Ladehinweis, keine Meldung, kein Versand).
  Liest jetzt die erste ausgefüllte Adresse aus der Empfänger-Liste (siehe auch „Test-Report").
- **Dashboard-Karten waren unformatiert:** Die Styles für `.mlt-card`, `.mlt-grid`,
  `.mlt-header`, `.mlt-hint` und `.mlt-notice` standen nur in `admin.css` (lädt nur auf der
  Einstellungsseite). Sie liegen jetzt auch in `dashboard.css`; der Inline-`<style>`-Block der
  Dashboard-Seite (Datumsleiste, Consent-Karte) wurde dorthin verschoben.
- **Consent-Balken waren unsichtbar:** `.mlt-consent-row__fill` ist ein `<span>`; Breite und
  Höhe wirkten nicht (fehlendes `display:block`).

#### Changed
- Test-Mail-Button heißt „Test-Report senden"; die frühere reine SMTP-Test-Mail
  (`build_test_mail_html()`) entfällt – der Report prüft den SMTP-Versand mit.

---

## [1.11.0] - 2026-10-05

### media-lab-seo 1.11.0

#### Added
- **Vergleichszeitraum** (`inc/class-compare.php`, neu) – zentrale Einstellung
  `mlt_compare_mode` unter **SEO Toolkit → Einstellungen → SEO**: Vorperiode (gleich
  lang, direkt davor), Vorjahreszeitraum oder kein Vergleich. Gilt einheitlich für
  SEO-Dashboard, WP-Dashboard-Widget und den wöchentlichen E-Mail-Report.
  - KPI-Kacheln (GSC und Analytics) zeigen die Veränderung (▲/▼) gegenüber dem
    Vergleichszeitraum; im Dashboard mit Tooltip (Vergleichswert), im Report als
    Zusatzzeile unter jeder Kachel plus Hinweis „Vergleich: …" im Header.
  - CTR als Differenz in Prozentpunkten, Ø Position mit umgekehrter Farblogik
    (kleiner = besser).
  - Search Console hält nur ca. 16 Monate Daten: liegt der Vergleichszeitraum weiter
    zurück (z. B. bei 365 Tagen), wird kein Vergleich berechnet, stattdessen erscheint
    ein Hinweis. Hat einer der beiden Zeiträume keine Daten (leerer oder fehlgeschlagener
    API-Abruf, junge Property), werden keine Deltas angezeigt statt irreführender Werte
    wie „−100 %".
  - **Hinweis zur Messtoleranz** unter dem Vergleich (Dashboard und E-Mail-Report): erklärt,
    warum Zahlen leicht von anderen Auswertungen abweichen können (nachträglich korrigierte
    Daten, Datenschutz-Filterung, fehlende Cookie-Zustimmung, unterschiedliche Zählweisen) und
    dass kleine Veränderungen bei niedrigen Zahlen wenig aussagekräftig sind. Übersetzbar
    (Text Domain `media-lab-seo`), erscheint nur, wenn tatsächlich Veränderungswerte angezeigt
    werden. Neuer Filter `mlt_compare_tolerance_note` zum Anpassen des Textes oder (leerer
    String) zum Ausblenden.
  - Neuer Filter `mlt_compare_mode` zum projektspezifischen Überschreiben des Modus.
  - Report-Rohdaten (Filter `mlt_weekly_report_html`) enthalten den neuen Key `compare`.

#### Changed
- WP-Dashboard-Widget nutzt `MLT_GSC_API::get_active_range()` statt einer eigenen
  Zeitraum-Berechnung (identisch zu Dashboard und Report).
- Label „Standard-Zeitraum" nennt jetzt „Dashboard & Report" (steuert beides).
- Report-Header nennt den Zeitraum ehrlich: „Zeitraum: 27 Tage (01.09.2026 – 27.09.2026)"
  statt „letzte 26 Tage". Der Zeitraum endet wegen der GSC-Verzögerung immer bei
  heute − 2 Tage; ein „28-Tage"-Zeitraum umfasst daher 27 Kalendertage (Start- und
  Endtag inklusive gezählt). Die Zählung war vorher zusätzlich um einen Tag zu kurz.

#### Fixed
- **Fehlgeschlagene API-Abrufe wurden 6 Stunden als „0" gecacht** (`inc/class-gsc-api.php`,
  `inc/class-ga4-api.php`). Token abgelaufen, Timeout oder API-Fehler wurden wie ein leeres
  Ergebnis behandelt: Dashboard und Wochen-Report zeigten bis zum Ablauf Nullwerte, obwohl
  die Verbindung längst wieder funktionierte. Jetzt unterscheiden `query_api()` bzw.
  `run_report()` zwischen einer gültigen Antwort ohne Treffer (wird gecacht) und einem
  Fehler (`null`, wird nicht gecacht, der nächste Aufruf fragt neu). Zusätzlich ein Abruf-
  Stopp pro Request nach Netzwerk-/Serverfehlern (5xx, 429, Timeout), damit sich bei einer
  Google-Störung nicht mehrere 15-Sekunden-Timeouts zu einem hängenden Dashboard addieren.
  Service-Account- und Matomo-Pfad cachen keine Ergebnisse und sind nicht betroffen.
- **`/llms.txt` wurde nicht ausgeliefert:** `inc/class-llms-txt.php` (1.10.2) wurde in
  `media-lab-seo.php` weder geladen noch instanziiert; der Schalter unter
  **SEO Toolkit → Schema** blieb damit wirkungslos. Klasse wird jetzt geladen
  (`new MLT_LLMS_Txt()`).
- Doppeltes `require_once` von `class-schema-admin.php` entfernt.
- Plugin-Header und `MLT_VERSION` standen auf 1.10.1, obwohl das CHANGELOG bereits 1.10.2 führte.

#### Notes
- Der in 1.2.0 genannte „Vergleich vs. Vorperiode" der KPI-Kacheln war damals nicht
  implementiert (es wurden nur Absolutwerte angezeigt) – erst mit 1.11.0 gibt es ihn tatsächlich.

---

## [1.10.2] - 2026-09-23

### media-lab-seo 1.10.2

#### Added
- **`/llms.txt`-Ausgabe** (`inc/class-llms-txt.php`, neu) – automatisch
  generierte, kuratierte Markdown-Seitenübersicht nach dem Community-Format
  von llmstxt.org (kein offizieller Web-Standard; Unterstützung durch Google,
  OpenAI und andere große KI-Anbieter nicht bestätigt – siehe Einordnung in
  `docs/13_SEO.md`). Läuft über den normalen WordPress-Frontcontroller
  (wie `robots.txt`), keine eigene Rewrite-Rule nötig, funktioniert auch im
  `/cms`-Subdirectory-Setup.
  - Abschnitte: Seiten, Leistungen (CPT `service`, falls vorhanden), Blog
    (neueste Beiträge, Anzahl einstellbar)
  - Schaltet sich automatisch ab, wenn „Sichtbarkeit für Suchmaschinen
    blockieren" aktiv ist
  - WooCommerce-Systemseiten (Warenkorb, Kasse, Mein Konto, Shop, AGB)
    automatisch ausgeblendet
  - Neue Einstellungen unter **SEO Toolkit → Schema**: Ein-/Aus-Schalter,
    Anzahl Blogbeiträge
  - Fünf neue Filter: `mlt_llms_txt_enabled`, `mlt_llms_txt_description`,
    `mlt_llms_txt_post_limit`, `mlt_llms_txt_sections`, `mlt_llms_txt_content`

---

## [1.10.1] - 2026-09-22

### media-lab-seo 1.10.1

#### Added
- **Schema.org als verknüpfter `@graph`** (`inc/class-schema.php`, komplett überarbeitet) –
  ein JSON-LD-Block statt mehrerer Einzelblöcke; Organization/LocalBusiness, WebSite,
  WebPage und BreadcrumbList sind per `@id` verknüpft.
- **Automatische Schema-Typen je Inhalt:** `post` → `BlogPosting` (+ Autor als `Person`,
  nur bei echtem Namen – sonst Organisation), `service` → `Service`, `team` → `Person`,
  `job` → `JobPosting`, `project` → `CreativeWork`; Seiten/Archive → `WebPage` /
  `CollectionPage` / `SearchResultsPage`, Autoren-Archiv → `ProfilePage`.
- **FAQPage-Erkennung:** Seiten mit `[faq_accordion]` oder `<details><summary>`
  (inkl. Core-Details-Block) werden automatisch zur `FAQPage`; Fragen werden über den
  Fragetext dedupliziert.
- **Neue Quellen** (`inc/class-schema-sources.php`, neu): `[pricing_table]` →
  `OfferCatalog`, `[google_map]` → `Place`, `[mlb_booking_form]` → `LocalBusiness`
  je Standort inkl. `OpeningHoursSpecification` und Leistungen, `[team_member]` →
  `Person` (auch verschachtelt in `[team_cards]`), CPT `event` → `Event`.
- **Neues Admin-Modul** (`inc/class-schema-admin.php`, neu): Untermenü
  „SEO Toolkit → Schema" (Organisationstyp, Telefon, E-Mail, Adresse, Öffnungszeiten,
  Einzugsgebiet, sameAs), Metabox „Schema (SEO / AEO)" pro Beitrag/Seite
  (deaktivieren, Seitentyp überschreiben, eigenes JSON-LD nur für Administratoren),
  Profilfelder am WP-Benutzer (LinkedIn, Xing, Instagram, X, Facebook, YouTube)
  für das Autoren-Schema.
- **13 neue Filter** für Erweiterung ohne Plugin-Code anzufassen: `mlt_schema_enabled`,
  `mlt_schema_graph`, `mlt_schema_organization`, `mlt_schema_org_types`,
  `mlt_schema_post_type_builders`, `mlt_schema_faq_items`, `mlt_schema_article_type`,
  `mlt_schema_description`, `mlt_schema_person_contact`, `mlt_schema_default_country`,
  `mlt_schema_author_is_person`, `mlt_schema_author_url`, `mlt_schema_location_email`.

#### Changed
- Beiträge: `Article` → `BlogPosting` (per Filter `mlt_schema_article_type` änderbar).
- Organization-Logo: `logo_desktop` (Agency Core → Logo / Globale Einstellungen) hat
  Vorrang vor dem Social-Default-Bild.
- Bei aktivem Yoast SEO, Rank Math oder SEOPress gibt das Plugin kein Schema mehr aus
  (Vermeidung von Doppel-Markup; per `mlt_schema_enabled` überschreibbar).
- Standort-E-Mail (`mlb_location_email`, interne Kopie-Adresse) wird bewusst **nicht**
  ausgegeben, außer per Filter `mlt_schema_location_email`.

#### Fixed
- **Organization-Kontaktdaten und Logo wurden nie ausgegeben.** Der bisherige Code las
  ACF-Optionen (`logo`, `phone`, `email`, `address`), die im Agency Core nicht existieren.
  Liest jetzt die tatsächlichen Felder (`logo_desktop`, `top_header_phone`,
  `top_header_email`, `top_header_address`, `top_header_social`) – nur, wenn der Top
  Header aktiv ist und der jeweilige Eintrag nicht abgeschaltet wurde; die
  Schema-Einstellungen haben Vorrang.
- **FAQ-Erkennung griff nie:** gesucht wurde nach einem nie existierenden Shortcode
  `[faq]`; der reale Name ist `[faq_accordion]`.
- **Team-Mitglieder aus `[team_member]`-Shortcodes wurden nicht erkannt** (nur der
  CPT-Fall über `[team_query]` war abgedeckt); jetzt erzeugt jeder `[team_member]`
  einen eigenen `Person`-Node, inkl. Verweis von der Organisation über `employee`.
- **`og:description`/`twitter:description` enthielten rohen Shortcode-Text**
  (`inc/class-seo.php`): `get_description()` entfernte per `wp_strip_all_tags()` nur
  HTML-Tags, keine Shortcode-Syntax (`[projects_query …]`, `[posts_load_more]`, …).
  `strip_shortcodes()` jetzt vor `wp_strip_all_tags()` ergänzt, für Excerpt- und
  Content-Zweig.
- JSON-LD-Ausgabe: Inhalte mit `</script>` konnten den Script-Block verlassen
  (`JSON_HEX_TAG`/`JSON_HEX_AMP` ergänzt).

---  

## [1.10.0] - 2026-09-20

### media-lab-seo 1.10.0

#### Added
- **Schema.org als verknüpfter `@graph`** (`inc/class-schema.php`, komplett überarbeitet) –
  ein JSON-LD-Block statt mehrerer Einzelblöcke; Organization, WebSite, WebPage und
  BreadcrumbList sind per `@id` verknüpft.
- **Automatische Schema-Typen je Inhalt:** `post` → `BlogPosting` (+ Autor als `Person`),
  `service` → `Service`, `team` → `Person`, `job` → `JobPosting`, `project` → `CreativeWork`;
  Seiten/Archive → `WebPage` / `CollectionPage` / `SearchResultsPage`, Autoren-Archiv → `ProfilePage`.
- **FAQPage-Erkennung:** Seiten mit `[faq]`, `[accordion_item]` oder `<details>/<summary>`
  (inkl. Core-Details-Block) werden automatisch zur `FAQPage`.
- **Zusätzliche Quellen** (`inc/class-schema-sources.php`, neu): `[pricing_table]` → `OfferCatalog`,
  `[google_map]` → `Place`, `[mlb_booking_form]` → `LocalBusiness` je Standort inkl.
  `OpeningHoursSpecification` und Leistungen, CPT `event` → `Event`.
- **Admin** (`inc/class-schema-admin.php`, neu): Untermenü „SEO Toolkit → Schema"
  (Organisationstyp, Telefon, E-Mail, Adresse, Öffnungszeiten, Einzugsgebiet, sameAs),
  Metabox „Schema (SEO / AEO)" pro Beitrag/Seite (Schema deaktivieren, Seitentyp überschreiben,
  eigenes JSON-LD nur für Administratoren), Profilfelder am WP-Benutzer
  (LinkedIn, Xing, Instagram, X, Facebook, YouTube) für das Autoren-Schema.
- **Neue Filter:** `mlt_schema_enabled`, `mlt_schema_graph`, `mlt_schema_organization`,
  `mlt_schema_org_types`, `mlt_schema_post_type_builders`, `mlt_schema_faq_items`,
  `mlt_schema_article_type`, `mlt_schema_description`, `mlt_schema_person_contact`,
  `mlt_schema_default_country`, `mlt_schema_author_is_person`, `mlt_schema_author_url`,
  `mlt_schema_location_email`.

#### Changed
- Beiträge: `Article` → `BlogPosting` (per Filter `mlt_schema_article_type` rückgängig zu machen).
- Autor wird nur bei echtem Namen als `Person` ausgegeben; bei Login-Namen wie „admin"
  (oder Anzeigename = Login) ist die Organisation der Autor.
- Organization-Logo: echtes ACF-Logo (`logo_desktop`) hat Vorrang vor dem Social-Default-Bild
  (`mlt_og_default_image`).
- Bei aktivem Yoast SEO, Rank Math oder SEOPress gibt das Plugin kein Schema mehr aus
  (Vermeidung von Doppel-Markup; per `mlt_schema_enabled` überschreibbar).
- Standort-E-Mail (`mlb_location_email`, interne Kopie-Adresse) wird bewusst **nicht**
  ausgegeben, außer per Filter `mlt_schema_location_email`.
- `MLT_Schema::get_breadcrumb_list()` bleibt öffentlich (Rückwärtskompatibilität).

#### Fixed
- **Organization-Kontaktdaten und Logo wurden nie ausgegeben.** Der Code las die ACF-Optionen
  `logo`, `phone`, `email` und `address`, die im Agency Core nicht existieren. Jetzt werden die
  tatsächlichen Felder gelesen (`logo_desktop`, `top_header_phone`, `top_header_email`,
  `top_header_address`, `top_header_social`) – nur, wenn der Top Header aktiv ist und der
  jeweilige Eintrag nicht abgeschaltet wurde. Die Schema-Einstellungen haben Vorrang.
- JSON-LD-Ausgabe: Inhalte mit `</script>` konnten den Script-Block verlassen
  (`JSON_HEX_TAG`/`JSON_HEX_AMP` ergänzt).

---

## [1.9.2] - 2026-08-19

### media-lab-seo 1.9.2

#### Fixed
- Wöchentlicher Report nutzte für Analytics-Daten und die angezeigte
  Datumsspanne einen hart codierten 28-Tage-Zeitraum statt der
  konfigurierten `mlt_default_range`-Einstellung, wodurch GSC- und
  Analytics-Zahlen bei geändertem Standard-Zeitraum aus verschiedenen
  Perioden stammten. Zeitraum wird jetzt einmalig über
  `MLT_GSC_API::get_active_range()` bestimmt und für GSC, Analytics
  und die Header-Anzeige einheitlich verwendet.
  
---

## [1.9.1] - 2026-08-13

### media-lab-seo 1.9.1

#### Fixed
- Veralteter Docblock-Kommentar in `inc/class-report-mailer.php` korrigiert
  - verwies fälschlich auf `class-settings.php` als Ort der Hook-Registrierung
  (Überbleibsel aus einer früheren Version mit einem inzwischen entfernten
  Legacy-Handler, siehe 1.9.0). Rein kosmetisch, keine Funktionsänderung.

---

## [1.9.0] - 2026-06-30

### media-lab-seo 1.9.0

#### Added
- **Consent-Rate-Tracking** (`inc/class-consent-stats.php`, neu) - DSGVO-Compliance-Auswertung, wie viele Besucher Analytics-Consent geben

#### Fixed
- **Kritisch: Wöchentlicher SEO-Report wurde doppelt versendet.** Gefunden beim Janecka-Projekt - zwei Mails zur exakt selben Minute, mit unterschiedlichen Templates. Ursache: Ein alter Legacy-Handler (`send_weekly_report()`/`build_report_html()` in `class-settings.php`) und der eigentliche `MLT_Report_Mailer::send()` waren beide auf denselben Cron-Hook `mlt_weekly_report` registriert. Legacy-Handler entfernt, nur noch `MLT_Report_Mailer` sendet.
- GA4-OAuth-Verbindungsfehler durch falsch konfigurierten SMTP-Benutzernamen (Agency-Core-Einstellung, nicht Plugin-Bug) - beim Debuggen mit aufgefallen und dokumentiert

---

## [1.8.0] - 2026-06-24

### media-lab-seo 1.8.0

#### Added
- SEO-Dashboard um dynamische Datumsbereichsoptionen und einen konfigurierbaren Standard-Zeitraum in den Einstellungen erweitert (vorher fest auf 28 Tage)

---

## [1.7.0] - 2026-06-24

### media-lab-seo 1.7.0

#### Added
- **Bing Webmaster Tools Verifizierung** - neues Einstellungsfeld `mlt_bing_verification`, gibt bei gesetztem Wert ein `<meta name="msvalidate.01">`-Tag aus, direkt nach dem GSC-Verifizierungs-Tag in `class-seo.php`. Einrichtungsanleitung siehe README.md.

---

## [1.6.0] - 2026-06-23

### media-lab-seo 1.6.0

#### Added
- **Dynamische Report-Empfänger** (`inc/report-recipients.php`, neu) - beliebig viele Empfänger-Adressen statt nur der WordPress-Admin-E-Mail
- **Konfigurierbarer Versand-Zeitplan** (`inc/report-schedule.php`, neu) - Wochentag und Uhrzeit für den wöchentlichen Report einstellbar statt fix Montag 08:00 Uhr

---

## [1.5.0] - 2026-06-11

### media-lab-seo 1.5.0

#### Changed
- GA4-API-Integration überarbeitet/erweitert (Detail-Umfang dieses Commits nicht vollständig dokumentiert - Commit-Message erwähnt zusätzlich Übersetzungsarbeiten an media-lab-woocommerce, die nicht zu diesem Plugin gehören und hier nicht aufgeführt sind)

---

## [1.4.0] - 2026-03-25

### media-lab-seo 1.4.0

**Merge-Release.** `media-lab-toolkit` (separat entwickeltes, schlankeres
Plugin: Open Graph, Twitter Cards, Canonical URLs, einfacher
GSC-Verifizierungs-Meta-Tag, Consent-aware GA4/GTM-Tracking mit Agency-Core-
Cookie-Consent-Bridge) und das bisherige `media-lab-seo` (GSC OAuth2 API,
SEO-Dashboard, Report-Mailer, GA4+Matomo-Adapter) wurden zu einem
gemeinsamen Plugin zusammengeführt.

#### Changed
- Plugin-Ordner `media-lab-toolkit/` → `media-lab-seo/`, Haupt-Datei
  `media-lab-toolkit.php` → `media-lab-seo.php`, Plugin-Name-Header
  `Media Lab Toolkit` → `Media Lab SEO Toolkit`, Text Domain
  `media-lab-toolkit` → `media-lab-seo`. Interne Konstanten (`MLT_*`) und
  Option-Keys (`mlt_*`) bewusst unverändert gelassen, um bestehende
  DB-Einträge nicht zu invalidieren.
- Admin-Menüpunkt von „ML Toolkit" auf „SEO Toolkit" umbenannt

#### Fixed
- Versionsnummer bei diesem Merge fälschlich von `1.3.0` auf `1.1.0`
  zurückgesetzt statt fortgesetzt - siehe Hinweis am Anfang dieser Datei.
  Kein Feature-Verlust, nur die Zählung war falsch.

---

## [1.3.0] - 2026-03-10

### media-lab-seo 1.3.0

#### Added
- **Analytics-Adapter** (`inc/class-analytics-adapter.php`, neu) - pluggbare Schnittstelle für Pageview-/Traffic-Daten, austauschbar per Filter `medialab_analytics_adapter`
- **GA4 Data API Adapter** - Authentifizierung via Service-Account-JWT (RS256), kein zweiter OAuth-Flow nötig
- **Matomo Reporting API Adapter**

---

## [1.2.0] - 2026-03-10

### media-lab-seo 1.2.0

#### Added
- **SEO-Dashboard** (`inc/class-seo-dashboard.php`, neu) - KPI-Kacheln (Klicks, Impressionen, Ø CTR, Ø Position, jeweils vs. Vorperiode), Top-10-Keywords- und Top-10-Seiten-Tabellen, plus WordPress-Dashboard-Widget
- **GSC OAuth2 API** (`inc/class-gsc-api.php`, neu) - vollständige Google-Search-Console-Anbindung via OAuth2 Authorization Code Flow, Token-Speicherung in `wp_options`, automatische Token-Erneuerung, 1h-Cache via Transients
- **Wöchentlicher Report-Mailer** (`inc/class-report-mailer.php`, `inc/class-report-template.php`, neu) - HTML-Report mit GSC-KPIs, Top-Keywords, Top-Seiten, automatischer Versand via Agency-Core-SMTP

---

## [1.1.1] - 2026-03-04

### media-lab-seo 1.1.1

#### Fixed
- Patch-Release (Detail-Umfang nicht überliefert - Commit-Message enthielt keine Beschreibung)

---

## [1.1.0] - 2026-03-04

### media-lab-seo 1.1.0

#### Changed
- Release (Detail-Umfang nicht überliefert - Commit-Message enthielt keine Beschreibung über "release: v1.1.0" hinaus)

---

## [1.0.0] - 2026-02-16

### media-lab-seo 1.0.0

#### Added
- Initiales Release (13 Dateien)
- Open Graph Tags (`og:type`, `og:url`, `og:title`, `og:description`, `og:image`, `og:locale`, `og:site_name`)
- Twitter Cards (`summary_large_image`)
- Canonical-URL-Ausgabe (WordPress-eigene Canonical-Ausgabe deaktiviert, um Dopplung zu vermeiden)
- GSC-Verifizierungs-Meta-Tag (`google-site-verification`)
