<?php
/**
 * Retrieval-Schritt des RAG-Moduls: findet zur Nutzer-Frage die relevantesten
 * indexierten Content-Chunks.
 *
 * @package MediaLab_AI_Agent
 */

if (!defined('ABSPATH')) {
    exit;
}

class MLT_AI_Rag_Retriever {

    private const TOP_K = 5;
    // 0.72 war zu streng für kurzen/generischen Content (fand oft nichts).
    // 0.5 war immer noch zu streng für sprachübergreifende/fachbegriffliche
    // Treffer: an einer echten technischen PDF (deutsche Frage, englischer
    // Fachtext) lag der höchste gemessene Wert unter allen 90 Chunks bei nur
    // 0,30 — weit unter 0,5, obwohl der Inhalt eindeutig relevant war.
    // Wichtig für die Kalibrierung: Der Text-Chunk-Treffer muss nicht
    // "perfekt" sein — er entscheidet nur, OB ein Dokument als relevant genug
    // gilt, um bei PDFs komplett nativ ans Modell mitgeschickt zu werden (siehe
    // get_context() unten); die eigentliche Detailsuche übernimmt das Modell
    // beim Lesen der Original-PDF. 0.25 lässt solche Treffer durch, ohne bei
    // klar irrelevantem Content beliebig viel Rauschen durchzulassen.
    private const MIN_SIMILARITY = 0.25;

    // Maximal so viele PDF-Anhänge pro Anfrage nativ mitschicken (Kosten- und
    // Anthropic-Request-Size-Limit im Blick behalten, siehe MAX_ATTACHMENT_BYTES).
    private const MAX_ATTACHMENTS = 2;

    // Anthropic-Request-Limit liegt bei 32MB gesamt (inkl. restlichem Payload) —
    // 25MB Sicherheitsmarge pro einzelner Datei, auch bei zwei Anhängen zusammen
    // im grünen Bereich.
    private const MAX_ATTACHMENT_BYTES = 25 * 1024 * 1024;

    /**
     * @return array{text: string[], attachments: array<int, array{path: string, media_type: string}>}
     *   text: formatierte Kontext-Snippets (immer befüllt, wenn Treffer da sind — dient als
     *         Fallback-Kontext und für Quellenangaben, auch wenn zusätzlich PDFs angehängt werden).
     *   attachments: PDF-Dateien, die nativ (nicht nur als Text) an den Provider gehen sollen —
     *                aktuell nur unterstützt, wenn der gewählte Provider das auch nutzt (Anthropic).
     */
    public static function get_context(string $query, string $lang): array {
        $empty = ['text' => [], 'attachments' => []];

        if (!get_field('mlt_ai_rag_enabled', 'option')) {
            return $empty;
        }

        try {
            $query_embedding = MLT_AI_Embeddings::create($query);
        } catch (MLT_AI_Provider_Exception $e) {
            mlt_ai_log_error('RAG-Retrieval fehlgeschlagen: ' . $e->getMessage());
            return $empty; // Chat funktioniert trotzdem weiter, nur ohne Content-Kontext.
        }

        global $wpdb;
        $table = $wpdb->prefix . 'mlt_ai_embeddings';

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT object_type, object_id, chunk_text, embedding, source_path FROM {$table} WHERE lang = %s",
                $lang
            ),
            ARRAY_A
        );

        if (empty($rows)) {
            return $empty;
        }

        $scored = [];
        foreach ($rows as $row) {
            $vector = json_decode($row['embedding'], true);
            if (!is_array($vector)) {
                continue;
            }

            $similarity = MLT_AI_Embeddings::cosine_similarity($query_embedding['vector'], $vector);
            if ($similarity >= self::MIN_SIMILARITY) {
                $scored[] = [
                    'similarity'   => $similarity,
                    'text'         => $row['chunk_text'],
                    'object_id'    => (int) $row['object_id'],
                    'object_type'  => $row['object_type'],
                    'source_path'  => $row['source_path'] ?: null,
                ];
            }
        }

        if (empty($scored)) {
            return $empty;
        }

        usort($scored, fn($a, $b) => $b['similarity'] <=> $a['similarity']);
        $top = array_slice($scored, 0, self::TOP_K);

        $text_chunks = [];
        $attachments = [];
        $attachment_ids_seen = [];

        foreach ($top as $row) {
            // Quelle (Permalink bzw. bei Dateien die direkte Datei-URL) voranstellen,
            // damit das Modell in seiner Antwort auf die konkrete Seite/das
            // Produkt/die Datei verlinken kann. Bewusst erst hier (nicht schon
            // beim Indexieren/Embedding) ergänzt, damit die URL selbst die
            // Vektor-Ähnlichkeit nicht verzerrt.
            $url = $row['object_type'] === 'attachment'
                ? wp_get_attachment_url($row['object_id'])
                : get_permalink($row['object_id']);
            $source_line = $url ? "Quelle: {$url}\n" : '';
            $text_chunks[] = $source_line . $row['text'];

            // Zusätzlich: bei PDF-Treffern die Originaldatei (bzw. das für
            // diese Anfrage beste Seiten-Fenster, falls das Dokument über
            // dem Seitenlimit liegt und gesplittet wurde) zum nativen
            // Mitschicken vormerken. Formeln/Diagramme/Tabellen bleiben so
            // visuell korrekt erhalten statt nur als linearisierter Text.
            if ($row['object_type'] === 'attachment') {
                self::maybe_attach_pdf($row['object_id'], $row['source_path'], $attachments, $attachment_ids_seen);
            }

            // Wenn ein PRODUKT in den Top-Treffern landet: dessen verknüpfte
            // Dokumente (ACF-Feld mlt_ai_product_documents) ebenfalls anhängen
            // — unabhängig davon, ob die PDF-eigenen Chunks selbst hoch genug
            // scoren. Notwendig, weil kurze, strukturell ähnliche Produkt-Chunks
            // (Titel/Preis/Verfügbarkeit) in der Praxis oft höher scoren als
            // lange technische Fachtexte, wodurch die PDF-Chunks selbst nie in
            // die Top-K kommen, obwohl das zugehörige Produkt eindeutig
            // gefunden wurde (beobachtet: Produkt-Score 0,37 vs. PDF-Chunk-
            // Score 0,30 bei sonst identischem Sachverhalt). Für das beste
            // Seiten-Fenster wird die Anfrage separat gegen ALLE Chunks des
            // verlinkten Dokuments gescort statt blind Fenster 0 zu nehmen.
            if ($row['object_type'] === 'product' && function_exists('get_field')) {
                $documents = get_field('mlt_ai_product_documents', $row['object_id']);
                if (!empty($documents) && is_array($documents)) {
                    foreach ($documents as $doc_row) {
                        $file_id = self::extract_file_id($doc_row['file'] ?? null);
                        if ($file_id) {
                            $best_path = self::get_best_source_path($file_id, $query_embedding['vector']);
                            self::maybe_attach_pdf($file_id, $best_path, $attachments, $attachment_ids_seen);
                        }
                    }
                }
            }
        }

        return ['text' => $text_chunks, 'attachments' => $attachments];
    }

    /**
     * Ermittelt unter allen indexierten Chunks/Fenstern eines Dokuments
     * dasjenige mit der höchsten Ähnlichkeit zur aktuellen Anfrage, und gibt
     * dessen source_path zurück (oder null, wenn das Dokument nicht
     * gesplittet wurde bzw. keine eigenen Chunks hat — dann nutzt
     * maybe_attach_pdf() die Originaldatei). Wird für über Produkte
     * verlinkte Dokumente gebraucht, da dort kein einzelner Chunk-Treffer
     * aus der Haupt-Top-K-Auswahl vorliegt, der das passende Fenster anzeigt.
     */
    private static function get_best_source_path(int $object_id, array $query_vector): ?string {
        global $wpdb;
        $table = $wpdb->prefix . 'mlt_ai_embeddings';

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT embedding, source_path FROM {$table} WHERE object_type = 'attachment' AND object_id = %d",
                $object_id
            ),
            ARRAY_A
        );

        if (empty($rows)) {
            return null;
        }

        $best_similarity = -1.0;
        $best_path = null;

        foreach ($rows as $row) {
            $vector = json_decode($row['embedding'], true);
            if (!is_array($vector)) {
                continue;
            }
            $similarity = MLT_AI_Embeddings::cosine_similarity($query_vector, $vector);
            if ($similarity > $best_similarity) {
                $best_similarity = $similarity;
                $best_path = $row['source_path'] ?: null;
            }
        }

        return $best_path;
    }

    /**
     * Liest die Attachment-ID aus dem 'file'-Sub-Feld robust aus — normalerweise
     * ein Array (ACF-Rückgabeformat "array": ['ID' => ..., 'url' => ...]), aber
     * falls die Feldgruppe nie synchronisiert wurde, kennt ACF den Feldtyp
     * nicht und liefert stattdessen die rohe gespeicherte ID (int/numerischer
     * String) zurück. Beide Fälle abdecken statt bei fehlendem Sync einfach
     * `null` zu produzieren.
     *
     * @param mixed $file
     */
    private static function extract_file_id($file): ?int {
        if (is_array($file)) {
            $id = $file['ID'] ?? $file['id'] ?? null;
            return $id ? (int) $id : null;
        }
        if (is_numeric($file)) {
            return (int) $file;
        }
        return null;
    }

    /**
     * Prüft, ob eine gegebene Attachment-ID eine anhängbare PDF ist (Typ,
     * Größe) und ergänzt sie in $attachments, falls ja. Zentralisiert, da
     * sowohl direkte PDF-Chunk-Treffer als auch über Produkte verknüpfte
     * Dokumente denselben Eignungs-Check durchlaufen müssen.
     *
     * @param string|null $override_path Falls gesetzt (z.B. das beste Seiten-
     *   Fenster eines gesplitteten Dokuments), wird dieser Pfad statt der
     *   über get_attached_file() ermittelten Originaldatei verwendet.
     * @param array<int, array{path: string, media_type: string}> $attachments
     * @param int[] $attachment_ids_seen
     */
    private static function maybe_attach_pdf(
        int $attachment_id,
        ?string $override_path,
        array &$attachments,
        array &$attachment_ids_seen
    ): void {
        if (count($attachments) >= self::MAX_ATTACHMENTS || in_array($attachment_id, $attachment_ids_seen, true)) {
            return;
        }

        $mime_type = get_post_mime_type($attachment_id);
        if ($mime_type !== 'application/pdf') {
            return;
        }

        $path = ($override_path && file_exists($override_path)) ? $override_path : get_attached_file($attachment_id);

        if ($path && file_exists($path) && filesize($path) <= self::MAX_ATTACHMENT_BYTES) {
            $attachments[] = ['path' => $path, 'media_type' => $mime_type];
            $attachment_ids_seen[] = $attachment_id;
        } else {
            mlt_ai_log_error(
                'RAG: PDF-Treffer zu groß für nativen Anhang, nutze nur Text-Kontext',
                ['object_id' => $attachment_id]
            );
        }
    }
}
