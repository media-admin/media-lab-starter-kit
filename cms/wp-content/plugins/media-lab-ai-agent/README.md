# Media Lab AI Agent

Datenschutzkonformer, mehrsprachiger AI-Chat-Assistent für WordPress mit austauschbarem Anbieter (Anthropic, OpenAI, …) und optionalem RAG-Modul (Website-Inhalte als Kontext).

Eigenständiges Plugin — keine Abhängigkeit zu anderen `media-lab-*`-Plugins. Läuft sowohl im Starter-Kit-Ökosystem als auch standalone auf fremden WordPress-Projekten.

## Voraussetzungen

- **PHP 8.1+**
- **Advanced Custom Fields PRO** (zwingend — Options-Pages sind ein PRO-Feature, mit ACF Free läuft das Plugin nicht)
- API-Key für mindestens einen Chat-Provider (Anthropic und/oder OpenAI)
- Für das RAG-Modul zusätzlich: OpenAI API Key (Embeddings), unabhängig vom gewählten Chat-Provider

Optional (Plugin erkennt Verfügbarkeit automatisch, kein Hard-Fail):
- **Polylang** — für sprachspezifische System-Prompts und RAG-Sprachfilterung. Ohne Polylang läuft alles auf `de`.
- **WooCommerce** — Produkte können indexiert werden (Preis, Verfügbarkeit, Attribute).

## Setup

1. Plugin nach `wp-content/plugins/media-lab-ai-agent` kopieren, in WP-Admin aktivieren.
2. Unter **AI Agent** (eigener Menüpunkt, erscheint erst nach Aktivierung) API-Key(s) eintragen, Anbieter wählen, mindestens einen System-Prompt anlegen.
3. `AI Agent aktiv` einschalten.
4. Widget im Theme einbinden:
   ```php
   <?php if (function_exists('mlt_ai_render_widget')) { mlt_ai_render_widget(); } ?>
   ```
   oder per Shortcode `[mlt_ai_widget]`.

### RAG-Modul (optional)

1. `Website-Wissen (RAG) aktiv` einschalten, Post-Types wählen.
2. **Werkzeuge → AI Agent Reindex** → Neuindexierung starten (läuft per WP-Cron in 20er-Batches).
3. Erweiterbar über den Filter `mlt_ai_rag_indexable_text_parts`, um client-spezifische ACF-Felder in den indexierten Kontext aufzunehmen.

### Consent (DSGVO)

Das Frontend-Widget zeigt vor jeder Chat-Nutzung einen Consent-Hinweis; erst nach Zustimmung wird die erste Anfrage an den externen AI-Anbieter geschickt. Cookie-Name/-Wert sind über Filter anpassbar, um das Widget an ein bestehendes Consent-Tool der jeweiligen Site anzukoppeln:

```php
add_filter('mlt_ai_consent_cookie_name', fn() => 'mein_cookie_name');
add_filter('mlt_ai_consent_cookie_value', fn() => 'accepted');
```

**Hinweis:** Die technische Umsetzung ersetzt keine rechtliche Prüfung — ob der Consent-Text und die Datenschutzerklärung der jeweiligen Site die Datenübertragung an den gewählten AI-Anbieter korrekt abdecken, muss projektspezifisch geprüft werden.

## Architektur

```
media-lab-ai-agent.php          Bootstrap, ACF-Options-Page, Widget-Enqueue, Shortcode
inc/
  class-ai-agent-install.php    DB-Tabellen (Conversations, Budget-Log), Cron, Versions-Upgrade
  class-ai-agent-budget.php     Tages-Budget-Kill-Switch, Kosten-Reporting
  class-ai-agent-crypto.php     AES-256-GCM-Verschlüsselung der API-Keys
  class-ai-agent-rest.php       REST-Endpoint (providerunabhängig)
  class-ai-agent-dashboard.php  Kosten-Dashboard (Admin-Seite + WP-Dashboard-Widget)
  providers/
    interface-ai-provider.php
    class-provider-anthropic.php
    class-provider-openai.php
    class-provider-registry.php Anbieterwechsel über ACF-Option, ohne Code-Deploy
  rag/
    class-ai-agent-rag-install.php  Embeddings-Tabelle
    class-ai-agent-embeddings.php   OpenAI-Embedding-Client + Cosine-Similarity
    class-ai-agent-indexer.php      Content-Indexierung (save_post-Hook + Bulk-Reindex)
    class-ai-agent-retriever.php    Retrieval-Schritt für den REST-Handler
acf/
  group_mlt_ai_agent_settings.json  ACF-Feldgruppe (lokaler JSON-Sync)
assets/
  css/ai-agent-widget.css
  js/ai-agent-widget.js         Vanilla JS, kein Build-Step
```

### Erweiterungspunkte

- `mlt_ai_register_providers` — weitere Chat-Provider registrieren (z.B. Azure OpenAI, EU-Anbieter)
- `mlt_ai_rag_indexable_text_parts` — zusätzliche Felder in den RAG-Index aufnehmen
- `mlt_ai_consent_cookie_name` / `mlt_ai_consent_cookie_value` — Consent-Cookie an bestehendes Tool ankoppeln
- `mlt_ai_get_cost_summary` — Kosten-Daten für externes Reporting (z.B. SEO Toolkit) abgreifen

## Bekannte Einschränkungen

- Vektor-Suche läuft in PHP (Cosine-Similarity über alle Chunks) — bei sehr großen Content-Mengen (mehrere zehntausend Chunks) nicht performant genug, dann externe Vektor-DB nötig.
- Embeddings laufen ausschließlich über OpenAI (Anthropic bietet aktuell keine eigene Embedding-API).
- Erfordert ACF PRO — ohne sichtbaren Admin-Hinweis, falls nicht vorhanden.

## Versionierung

Plugin-Header-Version und `MLT_AI_AGENT_VERSION`-Konstante in `media-lab-ai-agent.php` müssen synchron mit den CHANGELOG-Einträgen gehalten werden.

## ACF-JSON manuell bearbeiten

Bei manuellen Änderungen an `acf/group_mlt_ai_agent_settings.json` (statt Export aus dem ACF-UI) unbedingt das `"modified"`-Feld am Dateiende auf einen aktuellen Unix-Timestamp setzen (z.B. `date +%s` im Terminal). ACFs Sync-Erkennung vergleicht **nicht den Inhalt**, sondern ausschließlich diesen Zeitstempel gegen den zuletzt synchronisierten DB-Stand — ohne aktuelleren Timestamp zeigt ACF trotz geänderter Felder weiterhin "Gespeichert" statt "Synchronisierung verfügbar" an, und die Änderung bleibt unbemerkt inaktiv.
