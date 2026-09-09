# Changelog

Alle nennenswerten Änderungen an diesem Plugin werden hier dokumentiert.
Format angelehnt an [Keep a Changelog](https://keepachangelog.com/), Versionierung nach [Semantic Versioning](https://semver.org/).

## [1.2.6] – unreleased

### feat(ai-agent)
- RAG-Kontext enthält jetzt die Quell-URL (Permalink) jedes gefundenen Abschnitts — Modell kann in Antworten auf konkrete Seiten/Produkte verlinken statt nur aufzuzählen.
- System-Prompt um eine generelle "kein Markdown"-Vorgabe ergänzt (gilt immer, nicht nur bei RAG-Treffern) — das Chat-Widget zeigt reinen Text an, `**fett**`/`#`-Überschriften erschienen bisher als rohe Sonderzeichen statt Formatierung.
- Frontend-Widget rendert Chat-Antworten jetzt über `innerHTML` (mit HTML-Escaping) statt `textContent` und wandelt einfache http(s)-URLs automatisch in klickbare Links um. Farb-Anpassung für Links in beiden Bubble-Varianten (User/Assistant) im CSS ergänzt.

## [1.2.5] – 2026-09-09

### fix(ai-agent)
- RAG-Retrieval-Schwellwert (`MIN_SIMILARITY`) war mit 0.72 zu streng für kurzen/generischen Testcontent — fand dadurch oft keine Treffer, wodurch der Chat komplett ohne Website-Kontext antwortete (wirkte wie "RAG greift nicht", obwohl technisch alles korrekt lief). Auf 0.5 gesenkt, `TOP_K` von 4 auf 5 erhöht.

## [1.2.4] – 2026-09-09

### fix(ai-agent)
- **Kritischer Bug:** Alle drei `CREATE TABLE`-Statements (`wp_mlt_ai_conversations`, `wp_mlt_ai_budget_log`, `wp_mlt_ai_embeddings`) definierten den Primary Key doppelt (`id BIGINT ... AUTO_INCREMENT PRIMARY KEY` in der Spalte **und** separat `PRIMARY KEY (id)`). `dbDelta()` bricht bei doppelter Primary-Key-Definition still ab, ohne die Tabelle anzulegen und ohne sichtbaren Fehler — betroffene Installationen hatten dadurch **keine einzige** der drei Plugin-Tabellen, was erst bei der ersten Schreiboperation auffiel (`WordPress database error ... doesn't exist`). Fix: Primary Key nur noch als separate `PRIMARY KEY  (id)`-Zeile (dbDelta-Konvention: exakt zwei Leerzeichen vor der Klammer). DB_VERSION-Konstanten hochgezählt, damit `maybe_upgrade()` die Tabellen bei bestehenden Installationen automatisch nachträglich korrekt erstellt.

## [1.2.3] – 2026-09-09

### fix(ai-agent)
- OpenAI-Key-Feld war zwar sichtbar, aber örtlich getrennt vom RAG-Toggle platziert (nur oben bei "Anbieter"), dadurch leicht zu übersehen, wenn Chat-Anbieter = Anthropic und nur RAG aktiviert ist. Hinweistext direkt unter "Website-Wissen (RAG) aktiv" ergänzt, der auf das Feld weiter oben verweist (kein dupliziertes Feld, um Datenkonflikte zu vermeiden).

## [1.2.2] – 2026-09-08

### fix(ai-agent)
- OpenAI-API-Key-Feld war nur sichtbar, wenn Anbieter = OpenAI gewählt war — fehlte also komplett, sobald Anbieter = Anthropic **und gleichzeitig** RAG aktiviert war, obwohl Embeddings in diesem Fall zwingend einen OpenAI-Key brauchen. Conditional Logic auf ODER-Bedingung (Anbieter = OpenAI **oder** RAG aktiv) erweitert.
- Hilfetexte bei beiden API-Key-Feldern um direkte Links zur jeweiligen Key-Erstellung ergänzt (console.anthropic.com/settings/keys, platform.openai.com/api-keys).
- JSON-Datei fehlte das von ACF für die Sync-Erkennung genutzte `"modified"`-Feld — händisch bearbeitete Local-JSON-Dateien ohne aktuellen Timestamp werden von ACF trotz inhaltlicher Änderung als bereits synchron behandelt ("Gespeichert" statt "Synchronisierung verfügbar"). Timestamp ergänzt, Vorgehen für zukünftige manuelle Edits im README dokumentiert.

## [1.2.1] – 2026-09-08

### fix(ai-agent)
- ACF Field-Group-JSON war als Array `[ {...} ]` statt als einzelnes Objekt strukturiert — ACFs lokaler JSON-Sync ignoriert Dateien in diesem Format stillschweigend, ohne Fehlermeldung. Datei umbenannt zu `group_mlt_ai_agent_settings.json` und auf reines Objekt entpackt.
- Options-Page-Registrierung (`acf_add_options_page()`) schlägt bei fehlendem **ACF PRO** (Options-Pages sind ein PRO-Feature) bisher still fehl. Jetzt: sichtbarer Admin-Hinweis (`admin_notices`) statt lautlosem Abbruch.
- Kosten-Dashboard als `add_submenu_page()` unter der von ACF selbst erzeugten Options-Page registriert — WP-Core-Submenu-Registrierung und ACF-eigene Options-Page-Registrierung vertragen sich nicht zuverlässig: der generierte Link fehlte das übliche `admin.php?page=`-Präfix (zeigte stattdessen direkt auf `/wp-admin/<slug>`, 404/Redirect auf Startseite). Betraf zusätzlich den übergeordneten "AI Agent"-Menüpunkt selbst, da WordPress bei vorhandenen Submenus dessen Link vom ersten Submenu-Eintrag ableitet. Fix: Kosten-Dashboard läuft jetzt als eigenständiger Top-Level-Menüpunkt, komplett unabhängig von ACFs Menü-Registrierung.

## [1.2.0] – 2026-09-08

### feat(ai-agent)
- Kosten-Dashboard: eigene Admin-Seite (`AI Agent → Kosten-Dashboard`) mit Aufschlüsselung nach Anbieter und Tagesverlauf (CSS-Balkendiagramm, keine JS-Dependency)
- WP-Dashboard-Widget für Schnellüberblick (30-Tage-Summe, Tagesbudget-Fortschritt)
- Filter `mlt_ai_get_cost_summary` als Erweiterungspunkt für externe Reporting-Integrationen
- Embedding-Kosten (RAG-Indexierung + Retrieval) werden jetzt separat unter Provider-ID `openai-embeddings` im Budget-Log erfasst — zuvor unvollständige Kostenerfassung, da nur Chat-Kosten geloggt wurden

## [1.1.0] – 2026-09-08

### feat(ai-agent)
- RAG-Modul: Indexierung von Post-Types (Beiträge, Seiten, WooCommerce-Produkte) inkl. Taxonomien, Preis/Verfügbarkeit als durchsuchbarer Kontext
- Embedding-Erzeugung über OpenAI Embeddings API (`text-embedding-3-small`), unabhängig vom gewählten Chat-Provider
- Cosine-Similarity-Retrieval in PHP (keine externe Vektor-DB nötig bei den üblichen Content-Mengen einer Client-Site)
- Batch-Reindexierung per WP-Cron (20 Posts/Durchlauf) über Werkzeuge → AI Agent Reindex, timeout-sicher für Shared-Hosting
- Consent-Gating im Frontend-Widget: Chat startet erst nach expliziter Zustimmung zur Datenübertragung an den externen AI-Anbieter; Cookie-Name/-Wert per Filter (`mlt_ai_consent_cookie_name`, `mlt_ai_consent_cookie_value`) an bestehende Consent-Tools ankoppelbar
- Erweiterungspunkt `mlt_ai_rag_indexable_text_parts` für Client-spezifische Zusatzfelder (z.B. Material, Edelstein)

## [1.0.0] – 2026-09-08

### feat(ai-agent)
- Initiale Version: providerunabhängiger Chat-Agent für WordPress
- Provider-Abstraktion (`MLT_AI_Provider_Interface`) mit austauschbarem Anbieter über ACF-Option, ohne Code-Deploy
- Eingebaute Provider: Anthropic (Claude), OpenAI
- AES-256-GCM-Verschlüsselung der API-Keys, abgeleitet aus `AUTH_KEY`/`SECURE_AUTH_KEY` der jeweiligen WP-Installation
- REST-Endpoint (`medialab/v1/ai-chat`) mit Rate-Limiting pro Session, Tages-Budget-Kill-Switch
- Mehrsprachige System-Prompts über ACF-Repeater, gekoppelt an Polylang (optional, fällt auf `de` zurück falls nicht aktiv)
- Vanilla-JS-Frontend-Widget ohne Build-Step, EU-AI-Act-Art.-50-Transparenzhinweis
- 30-Tage-Datenretention für Chatverläufe (konfigurierbar, WP-Cron)
