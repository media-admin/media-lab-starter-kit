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

        // Datei-Indexierung (PDF/PPTX/DOCX) — eigener, unabhängiger Pfad, da
        // Anhänge (post_type 'attachment') ein anderes Status-Modell haben
        // (immer 'inherit', nie 'publish') und über MLT_AI_File_Extractor
        // erst in Text umgewandelt werden müssen.
        add_action('add_attachment', [self::class, 'maybe_index_attachment']);
        add_action('edit_attachment', [self::class, 'maybe_index_attachment']);
        add_action('mlt_ai_rag_bulk_reindex_files', [self::class, 'bulk_reindex_files']);
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
        array $embedding,
        ?string $source_path = null
    ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'mlt_ai_embeddings';

        $result = $wpdb->insert($table, [
            'object_type'     => $object_type,
            'object_id'       => $object_id,
            'chunk_index'     => $chunk_index,
            'lang'            => $lang,
            'chunk_text'      => $chunk_text,
            'embedding'       => wp_json_encode($embedding['vector']),
            'embedding_model' => $embedding['model'],
            'source_path'     => $source_path,
            'updated_at'      => current_time('mysql'),
        ]);

        // Rückgabewert nicht länger ignorieren: $wpdb->insert() gibt bei DB-Fehlern
        // (z.B. ungültige UTF-8-Bytes in einer UTF-8-Spalte, zu lange Werte)
        // `false` zurück, ohne eine Exception zu werfen — ohne diese Prüfung
        // verschwinden betroffene Chunks unbemerkt aus dem Index (siehe
        // ensure_valid_utf8() in class-ai-agent-file-extractor.php für den
        // Haupt-Auslöser bei PDFs).
        if ($result === false) {
            mlt_ai_log_error(
                'RAG: Chunk-Insert fehlgeschlagen: ' . $wpdb->last_error,
                ['object_type' => $object_type, 'object_id' => $object_id, 'chunk_index' => $chunk_index]
            );
            return false;
        }

        return true;
    }

    public static function delete_post_embeddings(int $post_id): void {
        global $wpdb;
        $table = $wpdb->prefix . 'mlt_ai_embeddings';
        $wpdb->delete($table, ['object_id' => $post_id]);

        if (class_exists('MLT_AI_Pdf_Splitter')) {
            MLT_AI_Pdf_Splitter::delete_windows($post_id);
        }
    }

    /**
     * Indexiert eine einzelne Mediendatei (PDF/PPTX/DOCX), falls Datei-Indexierung
     * aktiv ist und der MIME-Typ zu den ausgewählten Dateitypen gehört. Wird bei
     * Upload/Bearbeitung eines Anhangs automatisch aufgerufen.
     */
    public static function maybe_index_attachment(int $attachment_id): void {
        if (!get_field('mlt_ai_rag_enabled', 'option') || !get_field('mlt_ai_rag_index_files', 'option')) {
            return;
        }

        $mime_type = get_post_mime_type($attachment_id);
        if (!in_array($mime_type, self::get_indexed_mime_types(), true)) {
            return;
        }

        self::index_attachment($attachment_id);
    }

    public static function index_attachment(int $attachment_id): void {
        $mime_type = get_post_mime_type($attachment_id);
        $file_path = get_attached_file($attachment_id);

        if (!$file_path || !file_exists($file_path)) {
            mlt_ai_log_error('RAG-Datei-Indexierung: Datei nicht gefunden', ['attachment_id' => $attachment_id]);
            return;
        }

        self::delete_post_embeddings($attachment_id);

        // Bei PDFs über Anthropics 100-Seiten-Limit für native Dokumenten-
        // Anhänge: in kleinere, eigenständige "Fenster"-Dateien aufteilen und
        // JEDES Fenster einzeln indexieren (eigene Chunks/Embeddings, aber
        // gleiche object_id — die Suche wählt zur Anfrage passend das beste
        // Fenster aus, siehe get_best_attachment_source() im Retriever).
        // Bei Dateien unterhalb des Schwellwerts oder falls FPDI fehlt/
        // fehlschlägt: einfach die Originaldatei als einziges "Fenster".
        $windows = $mime_type === 'application/pdf' && class_exists('MLT_AI_Pdf_Splitter')
            ? MLT_AI_Pdf_Splitter::get_window_paths($file_path, $attachment_id)
            : [$file_path];

        $title = get_the_title($attachment_id);
        $lang = function_exists('pll_get_post_language')
            ? (pll_get_post_language($attachment_id) ?: 'de')
            : 'de';

        $any_indexed = false;
        $chunk_index = 0;

        foreach ($windows as $window_path) {
            $extracted = MLT_AI_File_Extractor::extract_text($window_path, $mime_type);
            if (trim($extracted) === '') {
                continue;
            }

            // source_path nur setzen, wenn tatsächlich gesplittet wurde (mehr
            // als ein Fenster ODER das einzige Fenster ist nicht die
            // Originaldatei) — sonst NULL, damit der Retriever ganz normal
            // get_attached_file() nutzt (schlanker Regelfall bleibt unverändert).
            $source_path = $window_path !== $file_path ? $window_path : null;

            $text = "Dateiname: {$title}\nInhalt: {$extracted}";
            $chunks = self::chunk_text($text);

            foreach ($chunks as $chunk) {
                try {
                    $embedding = MLT_AI_Embeddings::create($chunk);
                } catch (MLT_AI_Provider_Exception $e) {
                    mlt_ai_log_error('RAG-Datei-Indexierung fehlgeschlagen: ' . $e->getMessage(), ['attachment_id' => $attachment_id]);
                    continue 2; // nächstes Fenster versuchen statt komplett abzubrechen
                }

                self::store_chunk('attachment', $attachment_id, $chunk_index, $lang, $chunk, $embedding, $source_path);
                $chunk_index++;
                $any_indexed = true;
            }
        }

        if (!$any_indexed) {
            // Kein Fehler im engeren Sinn (z.B. gescanntes PDF ohne Text-Layer) —
            // aber sichtbar loggen, damit es beim Debuggen nicht als "hat
            // funktioniert" missverstanden wird.
            mlt_ai_log_error(
                'RAG-Datei-Indexierung: kein Text extrahiert (evtl. gescanntes PDF ohne Text-Layer?)',
                ['attachment_id' => $attachment_id, 'mime' => $mime_type]
            );
        }
    }

    /**
     * Liest die ausgewählten Datei-Typen (ACF-Checkbox) und übersetzt sie in
     * die tatsächlichen MIME-Types für den WP_Query-Filter bzw. den Abgleich
     * beim Upload-Hook.
     *
     * @return string[]
     */
    private static function get_indexed_mime_types(): array {
        $selected = get_field('mlt_ai_rag_file_types', 'option');
        if (empty($selected) || !is_array($selected)) {
            return [];
        }

        $map = [
            'pdf'  => 'application/pdf',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];

        $mimes = [];
        foreach ($selected as $key) {
            if (isset($map[$key])) {
                $mimes[] = $map[$key];
            }
        }

        return $mimes;
    }

    /**
     * Batch-Neuindexierung aller bereits hochgeladenen Dateien passenden Typs
     * (für Bestandsdateien, die vor Aktivierung der Datei-Indexierung schon in
     * der Mediathek lagen). Kleinere Batch-Größe als bei Content-Posts, da
     * PDF-Textextraktion mehr Rechenzeit braucht als reine Datenbank-Reads.
     */
    public static function bulk_reindex_files(int $offset = 0): void {
        if (!get_field('mlt_ai_rag_index_files', 'option')) {
            update_option('mlt_ai_rag_file_reindex_status', 'Datei-Indexierung ist deaktiviert.');
            return;
        }

        $mime_types = self::get_indexed_mime_types();
        if (empty($mime_types)) {
            update_option('mlt_ai_rag_file_reindex_status', 'Keine Dateitypen ausgewählt.');
            return;
        }

        $batch_size = 10;

        $query = new WP_Query([
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'post_mime_type' => $mime_types,
            'posts_per_page' => $batch_size,
            'offset'         => $offset,
            'fields'         => 'ids',
            'orderby'        => 'ID',
            'order'          => 'ASC',
        ]);

        if (empty($query->posts)) {
            update_option('mlt_ai_rag_file_reindex_status', 'completed');
            return;
        }

        foreach ($query->posts as $attachment_id) {
            self::index_attachment((int) $attachment_id);
        }

        update_option('mlt_ai_rag_file_reindex_status', sprintf('läuft (ab Position %d)', $offset + $batch_size));
        wp_schedule_single_event(time() + 30, 'mlt_ai_rag_bulk_reindex_files', [$offset + $batch_size]);
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
