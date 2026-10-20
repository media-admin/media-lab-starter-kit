# AI Agent Dokumentation

**Plugin:** `media-lab-ai-agent` v1.10.1 | **Letzte Aktualisierung:** 2026-10-20

> **Single Source of Truth** für Details sind die `README.md` und die
> `CHANGELOG.md` im Plugin-Ordner
> (`cms/wp-content/plugins/media-lab-ai-agent/`). Diese Doku ergänzt sie um
> die Starter-Kit-Sicht (Einordnung, Checkliste pro Projekt, Datenschutz,
> Troubleshooting) und wiederholt bewusst keine Versionshistorie.

---

## Inhaltsverzeichnis

1. [Übersicht](#übersicht)
2. [Checkliste pro Kundenprojekt](#checkliste-pro-kundenprojekt)
3. [Menüpunkte & Seiten](#menüpunkte--seiten)
4. [Daten & Aufbewahrung](#daten--aufbewahrung)
5. [Nutzungsmessung](#nutzungsmessung)
6. [Datenschutz](#datenschutz)
7. [Hooks & Schnittstellen](#hooks--schnittstellen)
8. [Deployment & Updates](#deployment--updates)
9. [Troubleshooting](#troubleshooting)

---

## Übersicht

`media-lab-ai-agent` ist ein AI-Chat-Assistent für Kundenwebsites mit
austauschbarem Anbieter (Anthropic, OpenAI) und optionalem Website-Wissen
(RAG: Seiten, Beiträge, Produkte, PDF/PPTX/DOCX aus der Mediathek).

| Punkt | Wert |
|---|---|
| Ordner | `cms/wp-content/plugins/media-lab-ai-agent` |
| Abhängigkeiten | **ACF PRO** (Options-Pages), PHP **8.1+** (höher als der Starter-Kit-Standard 8.0) |
| Eigenständig? | Ja — keine Abhängigkeit zu `media-lab-agency-core` oder `media-lab-seo` |
| Provider | Anthropic, OpenAI (Wechsel per ACF-Option, ohne Deploy) |
| RAG-Embeddings | Immer OpenAI (eigener Key nötig), unabhängig vom Chat-Provider |
| Optional | Polylang (sprachspezifische Prompts/Suche), WooCommerce (Produkte indexieren) |

Details zu Features, RAG, PDF-Verarbeitung und Zugriffsschutz: siehe Plugin-`README.md`.

---

## Checkliste pro Kundenprojekt

1. Plugin aktivieren (mindestens einmal, damit Tabellen und Cron angelegt werden).
2. **ACF-Sync** anstoßen (Custom Fields → Feldgruppen), damit „AI Agent Einstellungen" aktuell ist.
3. **AI Agent** → API-Key des Providers eintragen, Modell wählen, mindestens einen System-Prompt (pro Sprache) anlegen.
4. **Tagesbudget** setzen (EUR; `0` = kein Limit) und **Max. Nachrichten pro Session** prüfen (Standard 15).
5. **Aufbewahrungsdauer** festlegen (Standard 30 Tage, erlaubt 1–730) und **Nutzungsmessung** bewusst ein- oder ausschalten.
6. Optional RAG: Website-Wissen aktivieren, Inhaltstypen wählen, danach **Werkzeuge → AI Agent Reindex** (vollständig).
7. Widget einbinden: `mlt_ai_render_widget()` im Theme oder Shortcode `[mlt_ai_widget]`.
8. Consent-Cookie an das Consent-System des Projekts anbinden (Standard: `mlt_consent_ai_agent` = `granted`, per Filter änderbar).
9. **Datenschutzerklärung** des Projekts prüfen/ergänzen (siehe [Datenschutz](#datenschutz)).
10. Testen: Chat nach Consent nutzen, Link in der Antwort klicken, **AI Agent Auswertung → Nutzung** prüfen.

---

## Menüpunkte & Seiten

| Ort | Inhalt |
|---|---|
| **AI Agent** (Options-Page, Slug `acf-options-ai-agent`) | Alle Einstellungen: Provider, Keys, Budget, Aufbewahrung, Nutzungsmessung, Widget-Verhalten, RAG, System-Prompts |
| **AI Agent Auswertung → Kosten** (Slug `mlt-ai-costs`) | Kosten-Dashboard (30 Tage, nach Anbieter, pro Tag) + WP-Dashboard-Widget |
| **AI Agent Auswertung → Nutzung** (Slug `mlt-ai-usage`) | Fragen, Inhaltslücken, Quellen, Widget-Öffnungen, Link-Klicks, Antwortzeit |
| **Werkzeuge → AI Agent Reindex** | Reindexierung Inhalte und Dateien (vollständig oder nur geänderte) per WP-Cron |

---

## Daten & Aufbewahrung

| Tabelle | Inhalt | Aufbewahrung |
|---|---|---|
| `wp_mlt_ai_conversations` | Chat-Nachrichten (Fragen und Antworten), Tokens, Kosten | `mlt_ai_retention_days` |
| `wp_mlt_ai_events` | Nutzungsstatistik (anonymisiert, siehe unten) | `mlt_ai_retention_days` |
| `wp_mlt_ai_budget_log` | Tageskosten je Anbieter (kein Personenbezug) | fest 400 Tage |
| `wp_mlt_ai_embeddings` | RAG-Index (Chunks und Vektoren) | über Reindex |

- Löschung per WP-Cron `mlt_ai_daily_retention_cleanup`, täglich ca. 03:00 Uhr.
- Eine geänderte Aufbewahrungsdauer greift beim nächsten Cron-Lauf.
- Der Cron wird bei der **Aktivierung** eingeplant (nicht bei jedem Laden).

---

## Nutzungsmessung

Seit v1.10.0. Zeigt, wie der Chat tatsächlich genutzt wird — vor allem
**Inhaltslücken** (Fragen, zu denen das RAG nichts Passendes fand) als Grundlage für neue Inhalte.

| Event | Erfasst durch | Wann |
|---|---|---|
| `chat_message` | Server, beim Chat-Request | Frage, Retrieval-Ergebnis (Quellen, bester Score), Antwortzeit, Seitenpfad |
| `widget_open` | Browser-Beacon | Besucher öffnet das Widget per Klick (einmal pro Seitenaufruf) |
| `source_click` | Browser-Beacon | Klick auf einen Link in einer Chat-Antwort (URL ohne Query-String) |

- Beacon-Endpoint: `POST /wp-json/medialab/v1/ai-track` (Limit 60 Events/Session/Stunde, 3000 global).
- Schalter: AI Agent → **Nutzungsmessung aktiv** (Standard: an). Ausgeschaltet sendet das Widget keine Beacons und der Server schreibt nichts mehr.
- „Ohne passenden Inhalt" ist nur aussagekräftig, wenn **Website-Wissen (RAG)** aktiv ist.
- „Meistgenutzte Quellen" zeigt, welche Inhalte dem Modell als Kontext mitgegeben wurden, nicht zwingend, was die Antwort inhaltlich verwendet.

---

## Datenschutz

- **Übermittlung an den AI-Anbieter** (Drittland): Das Widget fragt vor der ersten Nutzung per Consent-Hinweis ab. Chat und Session-ID entstehen erst **nach** Zustimmung.
- **Anbieterverträge** (AVV/DPA) und Datenschutzerklärung pro Projekt prüfen — die technische Umsetzung ersetzt keine rechtliche Prüfung.
- **Nutzungsmessung:** keine IP, kein User-Bezug; Fragetexte in `wp_mlt_ai_events` werden maskiert (E-Mail → `[email]`, IBAN → `[iban]`, Ziffernfolgen ab 8 Ziffern → `[nummer]`); URLs ohne Query-String/Fragment. Die Datenschutzerklärung sollte die anonymisierte Auswertung erwähnen.
- **Achtung:** `wp_mlt_ai_conversations` speichert Fragen und Antworten **unmaskiert** (gleiche Aufbewahrungsdauer). Das Widget weist Besucher an, keine sensiblen Daten einzugeben.
- Chat-Hinweis nach Art. 50 EU AI Act ist im Widget-Text enthalten.
- Passwortgeschützter und nicht öffentlicher Content wird nie indexiert (siehe Plugin-README, Abschnitt Zugriffsschutz).

---

## Hooks & Schnittstellen

| Name | Art | Zweck |
|---|---|---|
| `mlt_ai_widget_default_state` | Filter | `collapsed` / `expanded`, z.B. nur auf der Startseite auto-öffnen |
| `mlt_ai_widget_dismissible` | Filter | × zum Ausblenden des Widgets |
| `mlt_ai_consent_cookie_name` / `mlt_ai_consent_cookie_value` | Filter | An vorhandenes Consent-System anbinden |
| `mlt_ai_analytics_enabled` | Filter | Nutzungsmessung per Code erzwingen/abschalten |
| `mlt_ai_get_cost_summary` | Filter | Kostenzahlen für andere Plugins (`apply_filters('mlt_ai_get_cost_summary', 30)`) |
| `mlt_ai_get_usage_summary` | Filter | Nutzungszahlen für andere Plugins (`apply_filters('mlt_ai_get_usage_summary', 30)`) |
| `mlt_ai_rag_is_post_indexable` | Filter | Mitglieder-/Zugriffs-Plugins: Beiträge vom Index ausschließen |
| `mlt_ai_rag_indexable_text_parts` | Filter | Zusätzliche (ACF-)Felder in den indexierten Text aufnehmen |

Die beiden `*_summary`-Filter sind der vorgesehene Weg, Zahlen z.B. in das
SEO Toolkit zu bringen, ohne die Plugins zu koppeln.

---

## Deployment & Updates

- Deployment per Full-Directory-Replace (SFTP). DB-Upgrades laufen automatisch beim nächsten Laden (`plugins_loaded`), ein erneutes Aktivieren ist nicht nötig.
- Nach jedem Update: **ACF-Sync** anstoßen (neue/geänderte Felder).
- Nach Updates, die die Indexierung betreffen (siehe CHANGELOG): **vollständige** Neuindexierung unter Werkzeuge → AI Agent Reindex. „Nur geänderte Inhalte" erkennt Logik-Änderungen des Plugins nicht.
- Bei Plugin-Änderungen den `vendor/`-Ordner (FPDI/FPDF) mit deployen; er ist bewusst im Repo, kein Server-Composer nötig.

---

## Troubleshooting

### Chat antwortet nicht / Fehlermeldung im Widget

1. **AI Agent aktiv** eingeschaltet, API-Key und Modell gesetzt?
2. Tagesbudget erreicht? (Widget zeigt „nicht verfügbar", Details unter AI Agent Auswertung → Kosten.)
3. Fehler im `debug.log` suchen: Einträge beginnen mit `[media-lab-ai-agent]`.

### Nutzung zeigt keine Daten

```bash
wp db query "SELECT event_type, query_text, has_context, latency_ms FROM wp_mlt_ai_events ORDER BY id DESC LIMIT 5"
```

- Tabelle fehlt: Plugin einmal laden lassen (DB-Upgrade) bzw. deaktivieren/aktivieren.
- Nur `chat_message`, keine Klicks/Öffnungen: Im Browser (Netzwerk-Tab) den Aufruf `ai-track` prüfen; Antwort sollte `{"ok":true}` sein. Fehlt das Attribut `data-track-endpoint` am Widget-Container, ist die Nutzungsmessung ausgeschaltet.
- „Widget geöffnet" zählt nur Klicks des Besuchers **nach** erteiltem Consent.

### Aufbewahrung greift nicht

```bash
wp cron event list | grep mlt_ai
```

Fehlt `mlt_ai_daily_retention_cleanup`, Plugin einmal deaktivieren und wieder aktivieren (plant den Cron neu ein).

### ACF zeigt „Synchronisierung verfügbar"

Custom Fields → Feldgruppen → „AI Agent Einstellungen" synchronisieren. Das Feld-JSON liegt im Plugin unter `acf/`.

### RAG findet nichts / PDFs werden nicht verstanden

Siehe Plugin-`README.md` (RAG, PDFs über 100 Seiten, Ghostscript-Fallback, gescannte PDFs ohne Text-Layer).

---

## Weiterführende Docs

- Plugin-`README.md` und `CHANGELOG.md` (`cms/wp-content/plugins/media-lab-ai-agent/`)
- [Plugin-Übersicht](03_PLUGINS.md)
- [SEO-Dokumentation](13_SEO.md)
- [Analytics-Dokumentation](12_ANALYTICS.md)
