<?php
/**
 * Indexierung von WordPress-Content für das RAG-Modul.
 *
 * @package MediaLab_AI_Agent
 */

if (!defined('ABSPATH')) {
    exit;
}

class MLT_AI_Rag_Indexer {

    private const CHUNK_MAX_CHARS = 1200;

    public static function init(): void {
        add_action('save_post', [self::class, 'maybe_index_post'], 20, 3);
        add_action('before_delete_post', [self::class, 'delete_post_embeddings']);
        add_action('mlt_ai_rag_bulk_reindex', [self::class, 'bulk_reindex']);
    }

    public static function maybe_index_post(int $post_id, WP_Post $post, bool $update): void {
        if (!get_field('mlt_ai_rag_enabled', 'option')) {
            return;
        }
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }
        if ($post->post_status !== 'publish') {
            // Bei Statuswechsel weg von "publish" (z.B. auf Entwurf) alte Chunks entfernen.
            self::delete_post_embeddings($post_id);
            return;
        }

        $indexed_post_types = self::get_indexed_post_types();
        if (!in_array($post->post_type, $indexed_post_types, true)) {
            return;
        }

        self::index_post($post_id);
    }

    /**
     * @return string[]
     */
    public static function get_indexed_post_types(): array {
        $configured = get_field('mlt_ai_rag_post_types', 'option');
        if (empty($configured) || !is_array($configured)) {
            return ['post', 'page'];
        }
        return $configured;
    }

    public static function index_post(int $post_id): void {
        $post = get_post($post_id);
        if (!$post) {
            return;
        }

        $lang = function_exists('pll_get_post_language')
            ? (pll_get_post_language($post_id) ?: 'de')
            : 'de';

        $text = self::build_indexable_text($post);
        if (trim($text) === '') {
            self::delete_post_embeddings($post_id);
            return;
        }

        $chunks = self::chunk_text($text);

        // Alte Chunks löschen und neu schreiben — einfacher und sicherer als
        // Diffing, bei den überschaubaren Content-Mengen kein Performance-Problem.
        self::delete_post_embeddings($post_id);

        foreach ($chunks as $index => $chunk) {
            try {
                $embedding = MLT_AI_Embeddings::create($chunk);
            } catch (MLT_AI_Provider_Exception $e) {
                mlt_ai_log_error('RAG-Indexierung fehlgeschlagen: ' . $e->getMessage(), ['post_id' => $post_id]);
                return; // Bei API-Fehler abbrechen, nicht teilweise indexieren.
            }

            self::store_chunk($post->post_type, $post_id, $index, $lang, $chunk, $embedding);
        }
    }

    /**
     * Baut den zu indexierenden Text aus Titel, Inhalt und relevanten
     * strukturierten Feldern (Taxonomien, ausgewählte ACF-Felder, WooCommerce-Preis).
     * Über den Filter erweiterbar, damit Client-spezifische Felder (z.B.
     * Material, Edelstein bei Janecka) ohne Core-Änderung ergänzt werden können.
     */
    private static function build_indexable_text(WP_Post $post): string {
        $parts = [];
        $parts[] = 'Titel: ' . $post->post_title;

        $content = wp_strip_all_tags(strip_shortcodes($post->post_content));
        if ($content !== '') {
            $parts[] = 'Inhalt: ' . $content;
        }

        $excerpt = wp_strip_all_tags($post->post_excerpt);
        if ($excerpt !== '') {
            $parts[] = 'Kurzbeschreibung: ' . $excerpt;
        }

        // Taxonomien (Kategorien, Tags, Produktattribute wie pa_material o.ä.)
        $taxonomies = get_object_taxonomies($post->post_type, 'names');
        foreach ($taxonomies as $taxonomy) {
            $terms = get_the_terms($post->ID, $taxonomy);
            if (is_array($terms) && !empty($terms)) {
                $term_names = wp_list_pluck($terms, 'name');
                $parts[] = ucfirst($taxonomy) . ': ' . implode(', ', $term_names);
            }
        }

        // WooCommerce-Preis, falls vorhanden — wichtig für Produkt-Fragen
        // ("was kostet...") ohne WooCommerce hart als Abhängigkeit vorauszusetzen.
        if (function_exists('wc_get_product')) {
            $product = wc_get_product($post->ID);
            if ($product) {
                $parts[] = 'Preis: ' . $product->get_price() . ' ' . get_woocommerce_currency();
                if ($product->is_in_stock()) {
                    $parts[] = 'Verfügbarkeit: auf Lager';
                } else {
                    $parts[] = 'Verfügbarkeit: nicht auf Lager';
                }
            }
        }

        /**
         * Erweiterungspunkt: zusätzliche ACF-Felder oder Client-spezifischen
         * Kontext ergänzen, ohne den Core anzufassen.
         *
         * add_filter('mlt_ai_rag_indexable_text_parts', function($parts, $post) {
         *     $material = get_field('material', $post->ID);
         *     if ($material) { $parts[] = 'Material: ' . $material; }
         *     return $parts;
         * }, 10, 2);
         */
        $parts = apply_filters('mlt_ai_rag_indexable_text_parts', $parts, $post);

        return implode("\n", $parts);
    }

    /**
     * Zerlegt Text in Chunks von max. CHUNK_MAX_CHARS Zeichen, bevorzugt an
     * Absatzgrenzen (\n) statt hart mitten im Wort zu schneiden.
     *
     * @return string[]
     */
    private static function chunk_text(string $text): array {
        $paragraphs = preg_split('/\n+/', trim($text)) ?: [];
        $chunks = [];
        $current = '';

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') {
                continue;
            }

            if (mb_strlen($current) + mb_strlen($paragraph) + 1 > self::CHUNK_MAX_CHARS) {
                if ($current !== '') {
                    $chunks[] = $current;
                }
                // Einzelner Absatz länger als Chunk-Limit: hart aufteilen.
                if (mb_strlen($paragraph) > self::CHUNK_MAX_CHARS) {
                    foreach (mb_str_split($paragraph, self::CHUNK_MAX_CHARS) as $piece) {
                        $chunks[] = $piece;
                    }
                    $current = '';
                } else {
                    $current = $paragraph;
                }
            } else {
                $current = $current === '' ? $paragraph : $current . "\n" . $paragraph;
            }
        }

        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }

    private static function store_chunk(
        string $object_type,
        int $object_id,
        int $chunk_index,
        string $lang,
        string $chunk_text,
        array $embedding
    ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'mlt_ai_embeddings';

        $wpdb->insert($table, [
            'object_type'     => $object_type,
            'object_id'       => $object_id,
            'chunk_index'     => $chunk_index,
            'lang'            => $lang,
            'chunk_text'      => $chunk_text,
            'embedding'       => wp_json_encode($embedding['vector']),
            'embedding_model' => $embedding['model'],
            'updated_at'      => current_time('mysql'),
        ]);
    }

    public static function delete_post_embeddings(int $post_id): void {
        global $wpdb;
        $table = $wpdb->prefix . 'mlt_ai_embeddings';
        $wpdb->delete($table, ['object_id' => $post_id]);
    }

    /**
     * Initiale Vollindexierung. Läuft als Batch über wp-cron, um Timeouts auf
     * restriktivem Shared-Hosting zu vermeiden (gleiches Prinzip wie euer
     * Chunk-Ansatz in media-lab-backup) — verarbeitet pro Aufruf max. 20 Posts
     * und plant sich bei Bedarf selbst erneut.
     */
    public static function bulk_reindex(int $offset = 0): void {
        $batch_size = 20;
        $post_types = self::get_indexed_post_types();

        $query = new WP_Query([
            'post_type'      => $post_types,
            'post_status'    => 'publish',
            'posts_per_page' => $batch_size,
            'offset'         => $offset,
            'fields'         => 'ids',
            'orderby'        => 'ID',
            'order'          => 'ASC',
        ]);

        if (empty($query->posts)) {
            update_option('mlt_ai_rag_reindex_status', 'completed');
            return;
        }

        foreach ($query->posts as $post_id) {
            self::index_post((int) $post_id);
        }

        update_option('mlt_ai_rag_reindex_status', sprintf('läuft (ab Position %d)', $offset + $batch_size));

        // Nächsten Batch in 30 Sekunden einplanen statt alles in einem Request.
        wp_schedule_single_event(time() + 30, 'mlt_ai_rag_bulk_reindex', [$offset + $batch_size]);
    }
}

MLT_AI_Rag_Indexer::init();
