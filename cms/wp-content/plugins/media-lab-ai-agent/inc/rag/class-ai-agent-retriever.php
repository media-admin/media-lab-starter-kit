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
    // 0.72 war zu streng für kurzen/generischen Content (fand oft nichts,
    // Modell antwortete dann komplett ohne Website-Kontext). 0.5 ist ein
    // brauchbarer Kompromiss; bei viel Content ggf. wieder anheben, um
    // Rauschen zu reduzieren.
    private const MIN_SIMILARITY = 0.5;

    /**
     * @return string[] Array formatierter Kontext-Snippets, leer falls RAG aus
     *                   oder kein ausreichend ähnlicher Treffer gefunden wurde.
     */
    public static function get_context(string $query, string $lang): array {
        if (!get_field('mlt_ai_rag_enabled', 'option')) {
            return [];
        }

        try {
            $query_embedding = MLT_AI_Embeddings::create($query);
        } catch (MLT_AI_Provider_Exception $e) {
            mlt_ai_log_error('RAG-Retrieval fehlgeschlagen: ' . $e->getMessage());
            return []; // Chat funktioniert trotzdem weiter, nur ohne Content-Kontext.
        }

        global $wpdb;
        $table = $wpdb->prefix . 'mlt_ai_embeddings';

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT object_type, object_id, chunk_text, embedding FROM {$table} WHERE lang = %s",
                $lang
            ),
            ARRAY_A
        );

        if (empty($rows)) {
            return [];
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
                    'similarity' => $similarity,
                    'text'       => $row['chunk_text'],
                    'object_id'  => (int) $row['object_id'],
                ];
            }
        }

        if (empty($scored)) {
            return [];
        }

        usort($scored, fn($a, $b) => $b['similarity'] <=> $a['similarity']);
        $top = array_slice($scored, 0, self::TOP_K);

        // Quelle (Permalink) pro Treffer voranstellen, damit das Modell in
        // seiner Antwort auf die konkrete Seite/das Produkt verlinken kann.
        // Wird bewusst erst hier (nicht schon beim Indexieren/Embedding)
        // ergänzt, damit die URL selbst die Vektor-Ähnlichkeit nicht verzerrt.
        return array_map(function ($row) {
            $url = get_permalink($row['object_id']);
            $source_line = $url ? "Quelle: {$url}\n" : '';
            return $source_line . $row['text'];
        }, $top);
    }
}
