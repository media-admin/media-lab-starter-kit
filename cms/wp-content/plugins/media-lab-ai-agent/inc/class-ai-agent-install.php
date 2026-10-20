<?php
/**
 * Install / Uninstall / Cron-Setup für media-lab-ai-agent
 *
 * @package MediaLab_AI_Agent
 */

if (!defined('ABSPATH')) {
    exit;
}

class MLT_AI_Agent_Install {

    const DB_VERSION = '1.1.0';
    const DB_VERSION_OPTION = 'mlt_ai_db_version';

    /**
     * Wird bei Plugin-Aktivierung aufgerufen (register_activation_hook).
     */
    public static function activate(): void {
        self::create_tables();
        self::schedule_cron_events();
        update_option(self::DB_VERSION_OPTION, self::DB_VERSION);
    }

    /**
     * Wird bei Plugin-Deaktivierung aufgerufen (register_deactivation_hook).
     * Tabellen bleiben bestehen — Löschung nur über echtes Uninstall (uninstall.php).
     */
    public static function deactivate(): void {
        self::clear_cron_events();
    }

    /**
     * Prüft bei jedem plugins_loaded, ob ein DB-Upgrade nötig ist.
     * Wichtig für Starter-Kit-Deployments per Full-Directory-Replace ohne Re-Activation-Hook.
     */
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

        $conversations_table = $wpdb->prefix . 'mlt_ai_conversations';
        $budget_table        = $wpdb->prefix . 'mlt_ai_budget_log';
        $events_table        = $wpdb->prefix . 'mlt_ai_events';

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Konversations-Log. Bewusst ohne IP/User-Bezug — session_id ist ein
        // zufälliger, nicht rückführbarer Hash (siehe class-ai-agent-rest.php).
        $sql_conversations = "CREATE TABLE {$conversations_table} (
            id BIGINT UNSIGNED AUTO_INCREMENT,
            session_id VARCHAR(64) NOT NULL,
            provider_id VARCHAR(32) NOT NULL,
            model VARCHAR(64) NOT NULL,
            lang VARCHAR(10) NOT NULL DEFAULT 'de',
            message_role ENUM('user','assistant') NOT NULL,
            message_text TEXT NOT NULL,
            input_tokens INT UNSIGNED DEFAULT 0,
            output_tokens INT UNSIGNED DEFAULT 0,
            cost_estimate DECIMAL(10,6) DEFAULT 0,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY session_id (session_id),
            KEY created_at (created_at)
        ) {$charset_collate};";
        dbDelta($sql_conversations);

        // Tages-Budget-Aggregation, getrennt von den einzelnen Nachrichten,
        // damit der Kill-Switch-Check (mlt_ai_budget_exceeded) ohne SUM() über
        // die potenziell große conversations-Tabelle auskommt.
        $sql_budget = "CREATE TABLE {$budget_table} (
            id BIGINT UNSIGNED AUTO_INCREMENT,
            budget_date DATE NOT NULL,
            provider_id VARCHAR(32) NOT NULL,
            total_cost DECIMAL(10,6) NOT NULL DEFAULT 0,
            total_requests INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            UNIQUE KEY budget_date_provider (budget_date, provider_id)
        ) {$charset_collate};";
        dbDelta($sql_budget);

        // Nutzungsmessung (seit 1.10.0, siehe class-ai-agent-analytics.php).
        // Anonymisiert: keine IP, Fragetexte maskiert, URLs ohne Query-String.
        // has_context: NULL = RAG aus, 0 = nichts Passendes gefunden, 1 = Treffer.
        $sql_events = "CREATE TABLE {$events_table} (
            id BIGINT UNSIGNED AUTO_INCREMENT,
            event_type VARCHAR(24) NOT NULL,
            session_id VARCHAR(64) NOT NULL,
            lang VARCHAR(10) NOT NULL DEFAULT 'de',
            page_path VARCHAR(255) NULL,
            query_text TEXT NULL,
            sources TEXT NULL,
            best_score DECIMAL(5,4) NULL,
            has_context TINYINT(1) NULL,
            latency_ms INT UNSIGNED NULL,
            target_url VARCHAR(500) NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY type_created (event_type, created_at),
            KEY session_id (session_id),
            KEY created_at (created_at)
        ) {$charset_collate};";
        dbDelta($sql_events);
    }

    private static function schedule_cron_events(): void {
        if (!wp_next_scheduled('mlt_ai_daily_retention_cleanup')) {
            wp_schedule_event(strtotime('tomorrow 03:00:00'), 'daily', 'mlt_ai_daily_retention_cleanup');
        }
        // Budget-Tabelle braucht keinen expliziten Reset-Cron: neuer Tag = neue
        // Zeile durch UNIQUE KEY (budget_date, provider_id) in mlt_ai_update_budget().
    }

    private static function clear_cron_events(): void {
        $timestamp = wp_next_scheduled('mlt_ai_daily_retention_cleanup');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'mlt_ai_daily_retention_cleanup');
        }
    }
}

/**
 * Retention-Cleanup: löscht Konversationen älter als mlt_ai_retention_days (ACF Option).
 * Hängt an dem in schedule_cron_events() registrierten Cron-Hook.
 */
add_action('mlt_ai_daily_retention_cleanup', function () {
    global $wpdb;

    // Zentrale Einstellung für Chatverläufe UND Nutzungsstatistik, auf einen
    // sinnvollen Bereich (1–730 Tage) begrenzt.
    $retention_days = MLT_AI_Agent_Analytics::get_retention_days();
    $table = $wpdb->prefix . 'mlt_ai_conversations';

    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$table} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $retention_days
        )
    );

    // Nutzungsstatistik (wp_mlt_ai_events): gleiche Aufbewahrungsdauer.
    MLT_AI_Agent_Analytics::cleanup($retention_days);

    // Budget-Log bewusst länger aufbewahren (kein PII, dient dem Kosten-Reporting
    // in der SEO-Toolkit-Dashboard-Card) — eigenes, deutlich längeres Retention-Fenster.
    $budget_table = $wpdb->prefix . 'mlt_ai_budget_log';
    $wpdb->query(
        "DELETE FROM {$budget_table} WHERE budget_date < DATE_SUB(CURDATE(), INTERVAL 400 DAY)"
    );
});
