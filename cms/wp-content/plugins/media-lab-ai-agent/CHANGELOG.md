# Changelog

Alle nennenswerten Änderungen an diesem Plugin werden hier dokumentiert.
Format angelehnt an [Keep a Changelog](https://keepachangelog.com/), Versionierung nach [Semantic Versioning](https://semver.org/).

## [1.8.1] – unreleased

### fix(ai-agent)
- `is_post_indexable()` prüft zusätzlich `is_post_publicly_viewable()` (WP-Core-Funktion) — fängt Custom-Post-Types ab, die absichtlich nicht öffentlich abrufbar sind, obwohl sie `post_status = publish` haben. Klarstellung in den Kommentaren: WordPress-Rollen schränken die Sichtbarkeit veröffentlichter Inhalte im Frontend nicht ein (dafür sind Rollen nicht gedacht) — echte rollenbasierte Einschränkung ist immer Sache eines Mitglieder-Plugins, wofür der bestehende `mlt_ai_rag_is_post_indexable`-Filter der vorgesehene Anknüpfungspunkt bleibt.

## [1.8.0] – 2026-09-23

### feat(ai-agent)
- **Inkrementelle Reindexierung ("nur geänderte Inhalte"):** Neuer zweiter Button neben der bestehenden vollständigen Neuindexierung — sowohl für Beiträge/Seiten/Produkte als auch für Dateien. Vergleicht `post_modified` gegen den zuletzt gespeicherten `updated_at`-Zeitstempel der Embeddings und überspringt unverändertes Material, statt bei jedem Klick alles neu zu embedden. Spart API-Kosten und Zeit bei großen Content-Mengen (100+ Dokumente).
- **Bewusst als Ergänzung, nicht als Ersatz:** Die inkrementelle Prüfung erkennt nur Änderungen am WordPress-Inhalt selbst — nicht, wenn sich die Indexierungs-Logik des Plugins durch ein Update geändert hat (z.B. die Encoding-/Splitting-Fixes in 1.4.x–1.6.x). Nach einem Plugin-Update bleibt die vollständige Neuindexierung der richtige Weg.
- **Bekannte Einschränkung:** Erkennt nur Änderungen, die über WordPress selbst laufen (`post_modified` wird nur bei Bearbeitung über WP aktualisiert) — eine Datei, die z.B. per FTP direkt ausgetauscht wird, ohne den WordPress-Datensatz neu zu speichern, wird von der inkrementellen Prüfung nicht erkannt.

## [1.7.0] – 2026-09-16

### feat(ai-agent)
- **Login-/Passwort-geschützter Content wird nie mehr indexiert:** WordPress' eingebauter Passwortschutz (`post_password`) wurde bisher nicht geprüft — ein passwortgeschützter Beitrag hatte trotzdem `post_status = publish` und landete unbemerkt im durchsuchbaren Index, auch für Besucher ohne Passwort. Neue zentrale Prüfung `MLT_AI_Rag_Indexer::is_post_indexable()` (Beiträge/Seiten/Produkte) und `is_attachment_indexable()` (Dateien, prüft den übergeordneten Beitrag) greift jetzt beim Einzel- **und** Massen-Indexieren.
- Erweiterungspunkt `mlt_ai_rag_is_post_indexable`-Filter für client-spezifische Mitglieder-/Zugriffs-Plugins (z.B. Restrict Content Pro, MemberPress) — WordPress-Core kennt außerhalb von Passwortschutz und privatem Status kein rollenbasiertes Sichtbarkeits-System, das ist immer Plugin-Sache.
- Die Produkt→Dokument-Verknüpfung (liest Dateien direkt aus dem ACF-Feld, unabhängig vom Such-Index) umging diese Prüfung bisher — jetzt zentral in `maybe_attach_pdf()` mit abgedeckt, greift für beide Anhang-Pfade (direkter Such-Treffer und Produkt-Verknüpfung).
- `bulk_reindex()` nutzt zusätzlich `WP_Query`s eingebauten `has_password`-Parameter für effizienten Ausschluss auf DB-Ebene, plus denselben Filter-Check pro Beitrag für nicht-eingebaute Einschränkungen.
- **Bewusste Design-Entscheidung:** Die Prüfung läuft beim Indexieren, nicht pro Chat-Anfrage abhängig von der fragenden Person — der Chat verhält sich für alle Besucher identisch (kein rollenabhängiges Mehr-oder-weniger-sehen). Geschützter Content landet dadurch strukturell gar nicht erst in der durchsuchbaren Datenbank, unabhängig davon, wer fragt.
- **Bekannte Einschränkung:** Falls ein Beitrag *nach* der Indexierung nachträglich passwortgeschützt wird, aber eine bereits daran hängende Datei nicht separat neu gespeichert wird, bleibt die Datei bis zum nächsten manuellen Reindex im Index (kein automatischer Kaskaden-Trigger vom übergeordneten Beitrag zu dessen Anhängen).

## [1.6.1] – 2026-09-16

### fix(ai-agent)
- Ghostscript-Normalisierung (1.6.0) blieb wirkungslos: Ab Ghostscript 10.03 schreibt `pdfwrite` standardmäßig **selbst wieder** XRef-Streams/Object-Streams (offiziell dokumentiertes Verhalten seit diesem Release) — die "normalisierte" Datei hatte exakt dasselbe Problem wie das Original. Fix: `-dCompatibilityLevel=1.4` erzwingt klassisches PDF-1.4-Format, das XRef-Streams technisch gar nicht kennt (erst ab PDF 1.5 spezifiziert) — laut Ghostscript-Doku wird die Einstellung dadurch automatisch deaktiviert. Verifiziert gegen echte Testdatei (Ghostscript 10.08.0, lokal + Hetzner-Shared-Hosting).

## [1.6.0] – 2026-09-16

### feat(ai-agent)
- **PDF-Seiten-Splitting für Dokumente über Anthropics 100-Seiten-Limit:** Neue Abhängigkeit `setasign/fpdi` + `setasign/fpdf` (beide MIT, lokal gebaut, `vendor/`-Ordner committed — kein Server-Composer nötig, siehe `vendor/README.md`). PDFs mit mehr als 95 Seiten werden beim Indexieren automatisch in ≤90-seitige "Fenster"-Dateien aufgeteilt (`class-ai-agent-pdf-splitter.php`), jedes Fenster wird einzeln indexiert. Bei einer Anfrage wird das für die konkrete Frage am besten passende Fenster ermittelt (nicht blind das erste) und nativ mitgeschickt — bleibt garantiert unter dem Seitenlimit, unabhängig von der Gesamtlänge des Originaldokuments.
- **Ghostscript-Normalisierung als Fallback, wenn der kostenlose FPDI-Parser scheitert:** PDFs mit komprimierten Cross-Reference-Tabellen/Object-Streams (PDF 1.5+, betrifft z.B. Ausgaben von "Tracker's PDF-Tools") kann die kostenlose FPDI-Version strukturell nicht lesen — die kommerzielle FPDI-PDF-Parser-Lizenz (ab 100€) war beim Kunden ohne Budget keine Option. Stattdessen: Falls `exec()`/`shell_exec()` erlaubt sind und `gs` (Ghostscript) auf dem Server installiert ist, wird die PDF einmalig über Ghostscripts `pdfwrite`-Device neu geschrieben (Standard-Konvertierung, kein Custom-PostScript, Ghostscripts eigenes Sandboxing bleibt aktiv) — das Ergebnis nutzt praktisch immer klassische Cross-Reference-Tabellen, die FPDI danach lesen kann. **Verifiziert auf Hetzner-Shared-Hosting** (Ghostscript 10.0.0 vorinstalliert, `exec()` uneingeschränkt) — dem Hosting der wichtigsten Kundenprojekte. Ohne Ghostscript: sauberer Fallback auf die Originaldatei (kein Absturz).
- Neue Spalte `source_path` in `wp_mlt_ai_embeddings` (DB_VERSION 1.1.0) — verweist bei gesplitteten Dokumenten auf das jeweilige Fenster; bei nicht gesplitteten Dokumenten `NULL` (Regelfall bleibt unverändert, nutzt weiterhin die Originaldatei).
- Für über Produkte verknüpfte Dokumente (`mlt_ai_product_documents`) wird das beste Fenster über eine gezielte Ähnlichkeitssuche unter allen Chunks des verlinkten Dokuments ermittelt (`get_best_source_path()`), nicht mehr das erste/einzige Fenster blind angenommen.
- **Wichtig für Bestandsdateien:** Bereits vor diesem Update indexierte lange PDFs (>95 Seiten) profitieren erst nach erneuter Indexierung vom Splitting (Werkzeuge → AI Agent Reindex → „Dateien neu indexieren").

## [1.5.3] – 2026-09-16

### debug(ai-agent)
- Temporäres `RAG-DEBUG`-Logging (siehe unreleased-Eintrag) nach erfolgreicher Diagnose wieder entfernt. **Ergebnis der Diagnose:** Die komplette Produkt→Dokument→Anhang-Kette funktioniert korrekt (Produkt gefunden → Dokument-Feld korrekt ausgelesen → PDF korrekt eingelesen → an Anthropic-API gesendet). Der eigentliche Grund, warum kein natives PDF-Verständnis ankam: Anthropics Limit von **100 PDF-Seiten pro Anfrage** — das konkrete Test-Dokument hat 120 Seiten. Der in 1.4.2 gebaute Fallback-Mechanismus griff wie vorgesehen (Rückfall auf Text-Kontext statt Fehlermeldung), aber die Text-Chunks des betroffenen Dokuments erreichen bei sprachübergreifenden Anfragen oft nicht die Top-5-Auswahl (siehe 1.4.5), wodurch bei sehr langen PDFs weder der native Anhang noch aussagekräftiger Text-Kontext zur Verfügung steht. Für Dokumente über 100 Seiten bräuchte es echtes Seiten-Splitting (nur relevante Seiten statt der ganzen Datei senden) — das ist ein neues Feature, kein Bugfix, und noch nicht umgesetzt.

## [1.5.2] – 2026-09-15

### fix(ai-agent)
- Produkt→Dokument-Verknüpfung robuster gegen nicht-synchronisierte ACF-Feldgruppen: `extract_file_id()` im Retriever und die Dateinamen-Auflösung im Index-Anreicherungs-Filter akzeptieren jetzt sowohl das erwartete ACF-Array-Format als auch eine rohe Attachment-ID (Fallback über `get_attached_file()`), statt bei fehlendem Sync still `null`/leer zu liefern.

## [1.5.1] – 2026-09-15

### fix(ai-agent)
- System-Prompt-Anweisung zur Quellenangabe verschärft: Link zu einem gefundenen Produkt/Dokument soll jetzt auch dann genannt werden, wenn das Modell unsicher ist oder Rückfragen stellt — bisher blieb die Antwort bei Unsicherheit komplett linkfrei, obwohl ein passendes Produkt klar gefunden wurde.

## [1.5.0] – 2026-09-15

### feat(ai-agent)
- **Produkt→Dokument-Verknüpfung wird jetzt tatsächlich genutzt:** Landet ein Produkt in den Top-Treffern, werden dessen verknüpfte Dokumente (ACF-Feld `mlt_ai_product_documents`) automatisch als native PDF-Anhänge mitgeschickt — unabhängig davon, ob die PDF-eigenen Text-Chunks selbst hoch genug scoren. Grund: kurze, strukturell ähnliche Produkt-Chunks (Titel/Preis/Verfügbarkeit) scoren in der Praxis oft höher als lange technische Fachtexte, wodurch PDF-Chunks nie in die Top-K kamen, obwohl das zugehörige Produkt eindeutig gefunden wurde (beobachtet: Produkt-Score 0,37 vs. PDF-Chunk-Score 0,30 bei identischem Sachverhalt — reines Schwellwert-Tuning hätte das nicht gelöst, da generische Test-Produkte durch strukturelle Ähnlichkeit teils sogar höher scoren als der eigentlich relevante Inhalt).
- Anhang-Eignungsprüfung (Typ, Größe) in `maybe_attach_pdf()` zentralisiert — gilt jetzt einheitlich für direkte PDF-Treffer und über Produkte verknüpfte Dokumente.

## [1.4.5] – 2026-09-15

### fix(ai-agent)
- RAG-Schwellwert (`MIN_SIMILARITY`) von 0.5 auf 0.25 gesenkt — an einer echten technischen PDF (deutsche Frage, englischer Fachtext) lag der höchste gemessene Ähnlichkeitswert unter allen 90 Chunks bei nur 0,30, obwohl der Inhalt eindeutig relevant war. Sprachübergreifende + fachbegriffliche Embedding-Ähnlichkeit ist strukturell schwächer als gleichsprachige Treffer. Diagnose per einmaligem `wp eval-file`-Script direkt gegen echte gespeicherte Embeddings verifiziert, nicht geraten.

## [1.4.4] – 2026-09-15

### fix(ai-agent)
- **Hauptursache für unvollständige PDF-Indexierung gefunden:** PDF-Text nutzt meist WinAnsiEncoding (≈ Windows-1252) — Zeichen wie ©, typografische Anführungszeichen, ° kamen als rohe Einzelbyte-Werte durch unseren Extraktor, was als UTF-8 interpretiert ungültige Byte-Sequenzen ergibt. `$wpdb->insert()` lehnt solche Werte in einer UTF-8-Spalte klaglos ab (kein Fehler, kein Log, die Zeile fehlt einfach) — an einer echten technischen PDF betraf das 72 von 89 Chunks. Fix: Extrahierter Text wird jetzt von Windows-1252 nach UTF-8 konvertiert (`convert_from_winansi()`), verbleibende Einzelfälle (z.B. Symbol-Font-Zeichen) fängt ein Sicherheitsnetz ab (`ensure_valid_utf8()`, entfernt statt den ganzen Chunk zu verlieren).
- `store_chunk()` prüft jetzt den Rückgabewert von `$wpdb->insert()` und loggt fehlgeschlagene Inserts explizit (`RAG: Chunk-Insert fehlgeschlagen`) — verhindert, dass zukünftige ähnliche Probleme wieder unbemerkt bleiben.

## [1.4.3] – 2026-09-15

### fix(ai-agent)
- Der 1.4.2-Fix (CR/LF/CRLF-Erkennung) war zwar korrekt, aber der zugrundeliegende `preg_match_all` mit `.*?` über die **gesamte** Roh-Datei konnte bei großen PDFs (getestet: 14,8MB, 120 Seiten) PHPs PCRE-Backtracking-Limit reißen und dadurch komplett `false` zurückgeben — Symptom war weiterhin "kein Text extrahiert", obwohl das Pattern selbst korrekt war. Umgestellt auf lineares `strpos()`-basiertes Scannen der Stream-Grenzen (kein Backtracking, keine Limits).
- Zusätzlich: Streams über 200KB (komprimiert) werden jetzt übersprungen, bevor das Text-Operator-Regex darauf losgelassen wird — große Streams sind praktisch immer eingebettete Bilder/Schriftarten (auch FlateDecode-komprimiert, aber ohne Tj/TJ-Text), nie echte Seiteninhalte. Reduziert die Verarbeitungszeit bei bebilderten technischen Manuals drastisch (getestet: 57s → 1,3s bei identischem Textergebnis) — wichtig, da PHPs Ausführungszeit-Limit sonst mitten in der Indexierung greifen und die Datei unvollständig/gar nicht indexiert lassen kann.

## [1.4.2] – 2026-09-15

### fix(ai-agent)
- **Kritischer Bug in der PDF-Textextraktion:** Stream-Erkennung verlangte zwingend `\r?\n` (CRLF oder LF) vor `endstream`. PDF-Spezifikation definiert Zeilenende aber als CR, LF **oder** CRLF — PDFs von Generatoren, die konsequent bloßes CR nutzen (beobachtet bei „Tracker's PDF-Tools"), lieferten dadurch **0 erkannte Streams**, also komplett leeren indexierten Text, ohne jede Fehlermeldung. Betroffene Dokumente waren im RAG-System faktisch unsichtbar, unabhängig von der Suchanfrage. Pattern auf `(?:\r\n|\r|\n)` erweitert (beide Stream-Grenzen).
- REST-Handler fängt jetzt Anthropic-API-Fehler bei PDF-Anhängen ab (z.B. Anthropics 100-Seiten-Limit bei sehr langen Manuals) und versucht die Anfrage automatisch einmal ohne Anhänge erneut (Fallback auf reinen Text-Kontext) statt den kompletten Chat-Turn mit einer Fehlermeldung scheitern zu lassen.

## [1.4.1] – 2026-09-15

### feat(ai-agent)
- Neue ACF-Feldgruppe „Produktdokumente (AI Agent)" direkt am WooCommerce-Produkt: Datei-Repeater für Datenblätter, Manuals, Spec Sheets (Upload auf PDF/PPTX/DOCX beschränkt). Eigene, plugin-gehörige Feldgruppe statt Erweiterung der Starter-Kit-eigenen „Additional Product Information" — bleibt damit portabel für fremde WordPress-Projekte ohne dieses Starter Kit.
- Hochgeladene Dokumente werden automatisch indexiert (regulärer `add_attachment`-Hook, unabhängig vom Produktbezug).
- Zusätzlich: Produktseite selbst nennt ihre zugehörigen Dokumente im RAG-Index-Text (`class-ai-agent-product-documents.php`, nutzt den bestehenden Filter `mlt_ai_rag_indexable_text_parts` — keine Core-Indexer-Änderung nötig) — verbessert die Auffindbarkeit, auch wenn die PDF-Textextraktion selbst bei formellastigen Inhalten schwächer trifft.

## [1.4.0] – 2026-09-15

### feat(ai-agent)
- **Zweistufiges RAG für formel-/diagrammlastige PDFs** (z.B. Manuals, Datenblätter mit chemischen/mathematischen Formeln): Text-Embeddings finden weiterhin das passende Dokument, aber statt nur extrahiertem (bei Formeln unzuverlässigem) Text wird die Original-PDF-Datei jetzt zusätzlich **nativ** an Claude mitgeschickt (Anthropic Messages API `document`-Content-Block, base64-kodiert) — Formeln, Diagramme und Tabellen bleiben visuell korrekt erhalten, da das Modell die Seite tatsächlich "sieht" statt nur linearisierten Text zu bekommen.
- `MLT_AI_Provider_Interface::send_message()` um optionalen `$attachments`-Parameter erweitert (Breaking Change für eigene Custom-Provider aus dem `mlt_ai_register_providers`-Hook — Signatur anpassen, Standardwert `[]` macht es aber abwärtskompatibel für Provider, die Anhänge ignorieren).
- Anthropic-Provider nutzt Anhänge nativ; OpenAI-Provider ignoriert sie bewusst (fällt auf Text-Kontext zurück) — kein natives PDF-Input in unserer aktuellen OpenAI-Implementierung.
- Retriever liefert max. 2 PDF-Anhänge pro Anfrage (Kosten-/Request-Size-Limit), Größenlimit 25MB/Datei (Anthropic-Request-Limit: 32MB gesamt). Zu große Treffer fallen automatisch auf reinen Text-Kontext zurück statt den Request abzubrechen.
- **Bekannte Einschränkung:** natives PDF-Verständnis ist auf Anthropic als Chat-Provider beschränkt; bei OpenAI als Anbieter sinkt die Formel-Treue auf das Niveau der Text-Extraktion. Anthropics eigene PDF-Grenzen (32MB, 100 Seiten bei Standard-Kontextfenster) gelten unverändert.

## [1.3.0] – 2026-09-09

### feat(ai-agent)
- RAG-Modul kann jetzt zusätzlich Dateien aus der Mediathek indexieren: PDF, PowerPoint (.pptx), Word (.docx) — z.B. Manuals, Datenblätter, Spezifikationen, die sonst nirgends als WordPress-Content vorliegen.
- Textextraktion komplett dependency-frei (`class-ai-agent-file-extractor.php`): PDF über zlib-Streamdekomprimierung + Textoperator-Parsing, PPTX/DOCX über `ZipArchive` + DOM/XPath. Kein Composer-Vendor-Ordner nötig, bleibt SFTP-Deploy-kompatibel.
- Neue Uploads werden automatisch indexiert (`add_attachment`/`edit_attachment`); Bestandsdateien über einen zweiten, unabhängigen Reindex-Button (Werkzeuge → AI Agent Reindex → "Dateien neu indexieren", eigener Cron-Batch à 10 Dateien).
- Retriever verlinkt bei Datei-Treffern direkt auf die Datei-URL (`wp_get_attachment_url()`) statt auf eine Attachment-Seite.
- **Bekannte Einschränkung:** funktioniert nur bei textbasierten ("born-digital") Dateien. Gescannte PDFs ohne echten Text-Layer benötigen OCR und werden nicht unterstützt.

## [1.2.6] – 2026-09-09

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
