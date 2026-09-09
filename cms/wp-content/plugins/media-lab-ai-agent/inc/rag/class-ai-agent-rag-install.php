<?php
/**
 * DB-Setup für das RAG-Modul (Embeddings-Speicher).
 * Eigene Datei statt Erweiterung von class-ai-agent-install.php, damit das
 * RAG-Modul komplett optional bleibt (kein Zwang zur Nutzung).
 *
 * @package MediaLab_AI_Agent
 */

if (!defined('ABSPATH')) {
    exit;
}

class MLT_AI_Rag_Install {

    const DB_VERSION = '1.0.1';
    const DB_VERSION_OPTION = 'mlt_ai_rag_db_version';

    public static function activate(): void {
        self::create_tables();
        update_option(self::DB_VERSION_OPTION, self::DB_VERSION);
    }

    public static function maybe_upgrade(): void {
        $installed = get_option(self::DB_VERSION_OPTION);
        if ($installed !== self::DB_VERSION) {
            self::create_tables();
            update_option(self::DB_VERSION_OPTION, self::DB_VERSION);
        }
    }

    private static function create_tables(): void {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $table = $wpdb->prefix . 'mlt_ai_embeddings';

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // embedding als JSON-String (LONGTEXT) statt nativem Vektor-Typ, da
        // Shared-Hosting-MySQL i.d.R. keine Vektor-Extensions bietet.
        // Cosine-Similarity wird serverseitig in PHP berechnet (siehe
        // class-ai-agent-retriever.php) — bei den Content-Mengen typischer
        // Client-Sites (paar hundert bis wenige tausend Chunks) ausreichend performant.
        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT,
            object_type VARCHAR(32) NOT NULL,
            object_id BIGINT UNSIGNED NOT NULL,
            chunk_index SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            lang VARCHAR(10) NOT NULL DEFAULT 'de',
            chunk_text TEXT NOT NULL,
            embedding LONGTEXT NOT NULL,
            embedding_model VARCHAR(64) NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY object_lookup (object_type, object_id),
            KEY lang (lang)
        ) {$charset_collate};";

        dbDelta($sql);
    }
}
