<?php
/**
 * Nutzungsmessung für media-lab-ai-agent.
 *
 * Erfasst anonymisiert, wie der Chat genutzt wird: gestellte Fragen, ob dazu
 * Website-Inhalte gefunden wurden (Content-Lücken), welche Quellen das RAG
 * liefert, Antwortzeit, Widget-Öffnungen und Klicks auf Links in Antworten.
 *
 * Bewusst im Plugin selbst umgesetzt (nicht im SEO Toolkit), damit das Plugin
 * standalone funktioniert. Über den Filter `mlt_ai_get_usage_summary` kann das
 * SEO Toolkit die Zahlen read-only abgreifen (analog `mlt_ai_get_cost_summary`).
 *
 * Datenschutz:
 * - Keine IP, kein User-Bezug. Die Session-ID ist dieselbe zufällige ID wie im
 *   Konversations-Log (sessionStorage, nach Consent erzeugt).
 * - Fragetexte werden vor dem Speichern maskiert (E-Mail, IBAN, lange Zahlen).
 * - Seiten- und Link-URLs werden ohne Query-String/Fragment gespeichert.
 * - Aufbewahrung: dieselbe Einstellung wie bei den Chatverläufen
 *   (`mlt_ai_retention_days`), Löschung per täglichem Cron.
 * - Ein-/ausschaltbar über `mlt_ai_analytics_enabled`.
 *
 * @package MediaLab_AI_Agent
 */

if (!defined('ABSPATH')) {
    exit;
}

class MLT_AI_Agent_Analytics {

    const TABLE_SUFFIX = 'mlt_ai_events';

    /** Event-Typen, die der Browser per Beacon melden darf. */
    const CLIENT_EVENT_TYPES = ['widget_open', 'source_click'];

    const MIN_RETENTION_DAYS = 1;
    const MAX_RETENTION_DAYS = 730;
    const DEFAULT_RETENTION_DAYS = 30;

    // Missbrauchsschutz für den Beacon-Endpoint (Transients, keine IP-Speicherung).
    const TRACK_LIMIT_PER_SESSION_PER_HOUR = 60;
    const TRACK_LIMIT_GLOBAL_PER_HOUR = 3000;

    public static function init(): void {
        add_action('rest_api_init', [self::class, 'register_routes']);
        add_action('admin_menu', [self::class, 'register_page'], 20);
        add_filter('mlt_ai_get_usage_summary', [self::class, 'get_summary'], 10, 1);
    }

    public static function table(): string {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_SUFFIX;
    }

    /**
     * Ist die Nutzungsmessung eingeschaltet? Standard: an. get_field() kann
     * "nie gespeichert" und "bewusst ausgeschaltet" nicht unterscheiden —
     * die gespeicherte Option schon (gleiches Muster wie bei
     * mlt_ai_widget_dismissible).
     */
    public static function is_enabled(): bool {
        $raw = get_option('options_mlt_ai_analytics_enabled', null);
        $enabled = $raw === null ? true : (bool) $raw;
        return (bool) apply_filters('mlt_ai_analytics_enabled', $enabled);
    }

    /**
     * Aufbewahrungsdauer in Tagen (gemeinsame Einstellung für Chatverläufe
     * und Nutzungsstatistik), auf einen sinnvollen Bereich begrenzt.
     */
    public static function get_retention_days(): int {
        $days = (int) (get_field('mlt_ai_retention_days', 'option') ?: self::DEFAULT_RETENTION_DAYS);
        return max(self::MIN_RETENTION_DAYS, min(self::MAX_RETENTION_DAYS, $days));
    }

    // ─────────────────────────────────────────────────────────────────────
    // Erfassung
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Protokolliert eine Chat-Anfrage samt Retrieval-Ergebnis.
     *
     * @param array $rag Ergebnis von MLT_AI_Rag_Retriever::get_context()
     */
    public static function log_chat(
        string $session_id,
        string $lang,
        string $message,
        array $rag,
        bool $rag_enabled,
        int $latency_ms,
        ?string $page_path
    ): void {
        if (!self::is_enabled()) {
            return;
        }

        $sources = [];
        foreach (($rag['sources'] ?? []) as $source) {
            $key = $source['type'] . ':' . $source['id'];
            $sources[$key] = [(int) $source['id'], (string) $source['type']];
        }

        self::insert([
            'event_type'  => 'chat_message',
            'session_id'  => $session_id,
            'lang'        => $lang,
            'page_path'   => $page_path,
            'query_text'  => mb_substr(self::mask_pii($message), 0, 500),
            'sources'     => $sources ? wp_json_encode(array_values($sources)) : null,
            'best_score'  => isset($rag['best_score']) ? round((float) $rag['best_score'], 4) : null,
            // NULL = RAG aus (keine Aussage möglich), 0 = nichts Passendes gefunden.
            'has_context' => $rag_enabled ? (empty($rag['text']) ? 0 : 1) : null,
            'latency_ms'  => max(0, $latency_ms),
        ]);
    }

    /**
     * Maskiert offensichtliche personenbezogene Muster in Freitext.
     * Bewusst konservativ und ohne Anspruch auf Vollständigkeit — der Chat
     * weist Besucher zusätzlich an, keine sensiblen Daten einzugeben.
     */
    public static function mask_pii(string $text): string {
        // E-Mail-Adressen
        $text = (string) preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu', '[email]', $text);

        // IBAN (Ländercode + Prüfziffern + mind. 3 Vierergruppen)
        $text = (string) preg_replace('/\b[A-Z]{2}\d{2}(?: ?[A-Z0-9]{4}){3,7}(?: ?[A-Z0-9]{1,4})?\b/', '[iban]', $text);

        // Telefon-/Kontonummern o.ä.: Ziffernfolgen mit mind. 8 Ziffern
        // (inkl. Leerzeichen, Bindestrich, Klammern, Slash, Punkt).
        $text = (string) preg_replace_callback(
            '/(?<![\w])\+?\d[\d\s().\/\-]{6,}\d(?![\w])/u',
            static function (array $m): string {
                return preg_match_all('/\d/', $m[0]) >= 8 ? '[nummer]' : $m[0];
            },
            $text
        );

        return $text;
    }

    /**
     * Nur den Pfad einer URL behalten (kein Host, kein Query-String).
     */
    public static function sanitize_path($raw): ?string {
        $raw = is_string($raw) ? trim($raw) : '';
        if ($raw === '') {
            return null;
        }
        $path = wp_parse_url($raw, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return null;
        }
        $path = '/' . ltrim($path, '/');
        return mb_substr(sanitize_text_field($path), 0, 255);
    }

    /**
     * http(s)-URL ohne Query-String und Fragment, oder NULL.
     */
    public static function sanitize_target_url($raw): ?string {
        $raw = is_string($raw) ? trim($raw) : '';
        if ($raw === '') {
            return null;
        }
        $parts = wp_parse_url($raw);
        if (!is_array($parts) || empty($parts['host']) || empty($parts['scheme'])) {
            return null;
        }
        if (!in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return null;
        }
        $url = strtolower($parts['scheme']) . '://' . strtolower($parts['host'])
            . (isset($parts['port']) ? ':' . (int) $parts['port'] : '')
            . ($parts['path'] ?? '/');

        return mb_substr(esc_url_raw($url), 0, 500) ?: null;
    }

    private static function insert(array $row): void {
        global $wpdb;

        $defaults = [
            'event_type'  => '',
            'session_id'  => '',
            'lang'        => 'de',
            'page_path'   => null,
            'query_text'  => null,
            'sources'     => null,
            'best_score'  => null,
            'has_context' => null,
            'latency_ms'  => null,
            'target_url'  => null,
            'created_at'  => current_time('mysql'),
        ];

        // Ein Fehler beim Messen darf den Chat nie beeinträchtigen.
        $wpdb->insert(self::table(), array_merge($defaults, $row));
    }

    // ─────────────────────────────────────────────────────────────────────
    // Beacon-Endpoint (Widget-Öffnungen, Link-Klicks)
    // ─────────────────────────────────────────────────────────────────────

    public static function register_routes(): void {
        register_rest_route('medialab/v1', '/ai-track', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'handle_track'],
            'permission_callback' => '__return_true',
            'args'                => [
                'event_type' => ['required' => true, 'type' => 'string'],
                'session_id' => ['required' => true, 'type' => 'string'],
                'lang'       => ['required' => false, 'type' => 'string'],
                'page'       => ['required' => false, 'type' => 'string'],
                'target_url' => ['required' => false, 'type' => 'string'],
            ],
        ]);
    }

    public static function handle_track(WP_REST_Request $request) {
        if (!get_field('mlt_ai_enabled', 'option')) {
            return new WP_Error('disabled', 'AI Agent ist nicht aktiv.', ['status' => 403]);
        }

        if (!self::is_enabled()) {
            return rest_ensure_response(['ok' => false]);
        }

        $event_type = sanitize_key((string) $request->get_param('event_type'));
        $session_id = sanitize_text_field((string) $request->get_param('session_id'));

        if (!in_array($event_type, self::CLIENT_EVENT_TYPES, true)
            || !preg_match('/^mlt_[a-f0-9]{32}$/', $session_id)) {
            return new WP_Error('invalid_input', 'Ungültige Anfrage.', ['status' => 400]);
        }

        if (self::track_rate_limited($session_id)) {
            return new WP_Error('rate_limited', 'Zu viele Anfragen.', ['status' => 429]);
        }

        $target_url = null;
        if ($event_type === 'source_click') {
            $target_url = self::sanitize_target_url($request->get_param('target_url'));
            if ($target_url === null) {
                return new WP_Error('invalid_input', 'Ungültige Ziel-URL.', ['status' => 400]);
            }
        }

        $lang = sanitize_text_field((string) $request->get_param('lang')) ?: 'de';

        self::insert([
            'event_type' => $event_type,
            'session_id' => $session_id,
            'lang'       => mb_substr($lang, 0, 10),
            'page_path'  => self::sanitize_path($request->get_param('page')),
            'target_url' => $target_url,
        ]);

        return rest_ensure_response(['ok' => true]);
    }

    /**
     * Zwei einfache Transient-Zähler (pro Session, global je Stunde). Es wird
     * keine IP verarbeitet; der globale Deckel begrenzt das Fluten der Tabelle
     * auch dann, wenn jemand laufend neue Session-IDs erfindet.
     */
    private static function track_rate_limited(string $session_id): bool {
        $session_key = 'mlt_ai_trk_' . md5($session_id);
        $global_key = 'mlt_ai_trk_all';

        $session_count = (int) get_transient($session_key);
        $global_count = (int) get_transient($global_key);

        if ($session_count >= self::TRACK_LIMIT_PER_SESSION_PER_HOUR
            || $global_count >= self::TRACK_LIMIT_GLOBAL_PER_HOUR) {
            return true;
        }

        set_transient($session_key, $session_count + 1, HOUR_IN_SECONDS);
        set_transient($global_key, $global_count + 1, HOUR_IN_SECONDS);

        return false;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Aufbewahrung
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Löscht Events, die älter als $days Tage sind. Wird vom täglichen
     * Retention-Cron (class-ai-agent-install.php) aufgerufen.
     */
    public static function cleanup(int $days): void {
        global $wpdb;

        $days = max(self::MIN_RETENTION_DAYS, min(self::MAX_RETENTION_DAYS, $days));
        $table = self::table();

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE created_at < DATE_SUB(%s, INTERVAL %d DAY)",
                current_time('mysql'),
                $days
            )
        );
    }

    /**
     * @return array{count: int, oldest: ?string}
     */
    public static function get_storage_info(): array {
        global $wpdb;
        $table = self::table();

        $row = $wpdb->get_row("SELECT COUNT(*) AS c, MIN(created_at) AS oldest FROM {$table}", ARRAY_A);

        return [
            'count'  => (int) ($row['c'] ?? 0),
            'oldest' => $row['oldest'] ?? null,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Auswertung
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Zentrale Aggregation für Admin-Seite und externen Filter.
     *
     * @return array<string,mixed>
     */
    public static function get_summary(int $days = 30): array {
        global $wpdb;

        $days = max(1, min(self::MAX_RETENTION_DAYS, $days));
        $table = self::table();
        $now = current_time('mysql');

        $where = $wpdb->prepare('created_at >= DATE_SUB(%s, INTERVAL %d DAY)', $now, $days);

        $totals = $wpdb->get_row(
            "SELECT
                SUM(event_type = 'chat_message')                              AS messages,
                COUNT(DISTINCT CASE WHEN event_type = 'chat_message' THEN session_id END) AS sessions,
                SUM(event_type = 'widget_open')                               AS opens,
                SUM(event_type = 'source_click')                              AS clicks,
                SUM(event_type = 'chat_message' AND has_context = 1)          AS answered,
                SUM(event_type = 'chat_message' AND has_context = 0)          AS unanswered,
                AVG(CASE WHEN event_type = 'chat_message' THEN latency_ms END) AS avg_latency
             FROM {$table} WHERE {$where}",
            ARRAY_A
        ) ?: [];

        $by_day = [];
        $day_rows = $wpdb->get_results(
            "SELECT DATE(created_at) AS d, COUNT(*) AS c
             FROM {$table} WHERE {$where} AND event_type = 'chat_message'
             GROUP BY DATE(created_at) ORDER BY d ASC",
            ARRAY_A
        );
        foreach ($day_rows as $row) {
            $by_day[$row['d']] = (int) $row['c'];
        }

        $top_queries = $wpdb->get_results(
            "SELECT LOWER(TRIM(query_text)) AS q, COUNT(*) AS c
             FROM {$table} WHERE {$where} AND event_type = 'chat_message' AND query_text IS NOT NULL
             GROUP BY q ORDER BY c DESC, q ASC LIMIT 15",
            ARRAY_A
        );

        $gaps = $wpdb->get_results(
            "SELECT LOWER(TRIM(query_text)) AS q, COUNT(*) AS c, MAX(best_score) AS best
             FROM {$table} WHERE {$where} AND event_type = 'chat_message' AND has_context = 0 AND query_text IS NOT NULL
             GROUP BY q ORDER BY c DESC, q ASC LIMIT 15",
            ARRAY_A
        );

        $top_clicks = $wpdb->get_results(
            "SELECT target_url AS url, COUNT(*) AS c
             FROM {$table} WHERE {$where} AND event_type = 'source_click' AND target_url IS NOT NULL
             GROUP BY target_url ORDER BY c DESC, url ASC LIMIT 15",
            ARRAY_A
        );

        // Quellen-Zähler in PHP (JSON-Spalte, kein JSON_TABLE auf Shared-Hosting-MySQL nötig).
        $source_rows = $wpdb->get_col(
            "SELECT sources FROM {$table}
             WHERE {$where} AND event_type = 'chat_message' AND sources IS NOT NULL
             ORDER BY id DESC LIMIT 5000"
        );
        $source_counts = [];
        foreach ($source_rows as $json) {
            $list = json_decode((string) $json, true);
            if (!is_array($list)) {
                continue;
            }
            foreach ($list as $entry) {
                if (!is_array($entry) || count($entry) < 2) {
                    continue;
                }
                $key = $entry[1] . ':' . (int) $entry[0];
                $source_counts[$key] = ($source_counts[$key] ?? 0) + 1;
            }
        }
        arsort($source_counts);
        $top_sources = [];
        foreach (array_slice($source_counts, 0, 15, true) as $key => $count) {
            [$type, $id] = explode(':', $key, 2);
            $top_sources[] = ['type' => $type, 'id' => (int) $id, 'count' => $count];
        }

        $messages = (int) ($totals['messages'] ?? 0);
        $answered = (int) ($totals['answered'] ?? 0);
        $unanswered = (int) ($totals['unanswered'] ?? 0);

        return [
            'days'              => $days,
            'messages'          => $messages,
            'sessions'          => (int) ($totals['sessions'] ?? 0),
            'opens'             => (int) ($totals['opens'] ?? 0),
            'clicks'            => (int) ($totals['clicks'] ?? 0),
            'answered'          => $answered,
            'unanswered'        => $unanswered,
            'unanswered_rate'   => ($answered + $unanswered) > 0 ? $unanswered / ($answered + $unanswered) : null,
            'avg_latency_ms'    => isset($totals['avg_latency']) ? (int) round((float) $totals['avg_latency']) : null,
            'by_day'            => $by_day,
            'top_queries'       => $top_queries,
            'gaps'              => $gaps,
            'top_sources'       => $top_sources,
            'top_clicks'        => $top_clicks,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Admin-Seite
    // ─────────────────────────────────────────────────────────────────────

    public static function register_page(): void {
        if (!function_exists('mlt_ai_options_page_available') || !mlt_ai_options_page_available()) {
            return;
        }

        // Hängt unter dem (nicht von ACF erzeugten) Top-Level-Menü des
        // Kosten-Dashboards — dort funktioniert add_submenu_page() normal.
        add_submenu_page(
            'mlt-ai-costs',
            'AI Agent — Nutzung',
            'Nutzung',
            'manage_options',
            'mlt-ai-usage',
            [self::class, 'render_page']
        );
    }

    public static function render_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $allowed = [7, 30, 90, 365];
        $days = isset($_GET['days']) ? (int) $_GET['days'] : 30; // phpcs:ignore WordPress.Security.NonceVerification
        $days = in_array($days, $allowed, true) ? $days : 30;

        $summary = self::get_summary($days);
        $retention = self::get_retention_days();
        $storage = self::get_storage_info();

        echo '<div class="wrap"><h1>AI Agent — Nutzung</h1>';

        if (!self::is_enabled()) {
            echo '<div class="notice notice-warning inline"><p>Die Nutzungsmessung ist ausgeschaltet '
                . '(AI Agent → „Nutzungsmessung aktiv“). Es werden keine neuen Daten erfasst.</p></div>';
        }

        // Zeitraum-Auswahl
        echo '<p>Zeitraum: ';
        foreach ($allowed as $option) {
            $url = add_query_arg(['page' => 'mlt-ai-usage', 'days' => $option], admin_url('admin.php'));
            $label = $option . ' Tage';
            echo $option === $days
                ? '<strong>' . esc_html($label) . '</strong> '
                : '<a href="' . esc_url($url) . '">' . esc_html($label) . '</a> ';
        }
        echo '</p>';

        if ($days > $retention) {
            echo '<div class="notice notice-info inline"><p>Die Aufbewahrung ist auf <strong>'
                . esc_html((string) $retention) . ' Tage</strong> eingestellt — ältere Daten wurden bereits gelöscht, '
                . 'der gewählte Zeitraum ist daher nicht vollständig.</p></div>';
        }

        // Kennzahlen
        echo '<div style="display:flex;flex-wrap:wrap;gap:16px;margin:20px 0;">';
        self::render_stat_card('Fragen', number_format_i18n($summary['messages']));
        self::render_stat_card('Unterhaltungen', number_format_i18n($summary['sessions']));
        self::render_stat_card('Widget geöffnet', number_format_i18n($summary['opens']));
        self::render_stat_card('Klicks auf Links', number_format_i18n($summary['clicks']));
        self::render_stat_card(
            'Ohne passenden Inhalt',
            $summary['unanswered_rate'] === null
                ? '—'
                : number_format_i18n($summary['unanswered_rate'] * 100, 0) . ' %'
        );
        self::render_stat_card(
            'Ø Antwortzeit',
            $summary['avg_latency_ms'] === null
                ? '—'
                : number_format_i18n($summary['avg_latency_ms'] / 1000, 1) . ' s'
        );
        echo '</div>';

        echo '<h2>Fragen pro Tag</h2>';
        self::render_bar_chart($summary['by_day']);

        echo '<h2>Häufigste Fragen</h2>';
        self::render_query_table($summary['top_queries'], false);

        echo '<h2>Inhaltslücken</h2>';
        echo '<p class="description">Fragen, zu denen das Website-Wissen (RAG) nichts Passendes gefunden hat — '
            . 'Kandidaten für neue oder bessere Inhalte. Nur sinnvoll, wenn „Website-Wissen (RAG)“ aktiv ist.</p>';
        self::render_query_table($summary['gaps'], true);

        echo '<h2>Meistgenutzte Quellen</h2>';
        self::render_sources_table($summary['top_sources']);

        echo '<h2>Meistgeklickte Links in Antworten</h2>';
        if (empty($summary['top_clicks'])) {
            echo '<p>Noch keine Daten vorhanden.</p>';
        } else {
            echo '<table class="widefat striped" style="max-width:900px;"><thead><tr><th>Link</th><th style="width:80px;">Klicks</th></tr></thead><tbody>';
            foreach ($summary['top_clicks'] as $row) {
                echo '<tr><td><a href="' . esc_url($row['url']) . '" target="_blank" rel="noopener noreferrer">'
                    . esc_html($row['url']) . '</a></td><td>' . esc_html((string) (int) $row['c']) . '</td></tr>';
            }
            echo '</tbody></table>';
        }

        // Speicher-Info / Aufbewahrung
        echo '<h2>Gespeicherte Daten</h2>';
        echo '<p>' . esc_html(number_format_i18n($storage['count'])) . ' Einträge';
        if ($storage['oldest']) {
            echo ', ältester vom ' . esc_html(mysql2date('d.m.Y', $storage['oldest']));
        }
        echo '. Aufbewahrung: <strong>' . esc_html((string) $retention) . ' Tage</strong> '
            . '(gilt auch für die Chatverläufe; Änderung unter AI Agent → „Aufbewahrungsdauer“, '
            . 'die Löschung läuft täglich um ca. 03:00 Uhr).</p>';

        echo '</div>';
    }

    private static function render_stat_card(string $label, string $value): void {
        echo '<div style="background:#fff;border:1px solid #ccd0d4;border-radius:4px;padding:16px 20px;min-width:150px;">';
        echo '<div style="font-size:12px;color:#666;text-transform:uppercase;">' . esc_html($label) . '</div>';
        echo '<div style="font-size:24px;font-weight:600;">' . esc_html($value) . '</div>';
        echo '</div>';
    }

    private static function render_bar_chart(array $by_day): void {
        if (empty($by_day)) {
            echo '<p>Noch keine Daten vorhanden.</p>';
            return;
        }

        $max = max($by_day);
        if ($max <= 0) {
            $max = 1;
        }

        echo '<div style="display:flex;align-items:flex-end;gap:4px;height:140px;border-bottom:1px solid #ccd0d4;padding:0 8px;overflow-x:auto;">';
        foreach ($by_day as $date => $count) {
            $height_pct = max(2, (int) round($count / $max * 100));
            $label = date_i18n('d.m.', strtotime($date)) . ': ' . $count;
            printf(
                '<div title="%1$s" aria-label="%1$s" style="flex:0 0 18px;height:%2$d%%;background:#1a1a1a;border-radius:2px 2px 0 0;"></div>',
                esc_attr($label),
                $height_pct
            );
        }
        echo '</div>';
        echo '<p style="font-size:12px;color:#666;">Balken zeigen die Fragen pro Tag, Maus über Balken für Details.</p>';
    }

    private static function render_query_table(array $rows, bool $with_score): void {
        if (empty($rows)) {
            echo '<p>Noch keine Daten vorhanden.</p>';
            return;
        }

        echo '<table class="widefat striped" style="max-width:900px;"><thead><tr><th>Frage</th><th style="width:80px;">Anzahl</th>';
        if ($with_score) {
            echo '<th style="width:140px;">Bester Treffer</th>';
        }
        echo '</tr></thead><tbody>';
        foreach ($rows as $row) {
            echo '<tr><td>' . esc_html((string) $row['q']) . '</td><td>' . esc_html((string) (int) $row['c']) . '</td>';
            if ($with_score) {
                echo '<td>' . ($row['best'] !== null ? esc_html(number_format_i18n((float) $row['best'], 2)) : '—') . '</td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    private static function render_sources_table(array $sources): void {
        if (empty($sources)) {
            echo '<p>Noch keine Daten vorhanden.</p>';
            return;
        }

        echo '<table class="widefat striped" style="max-width:900px;"><thead><tr><th>Quelle</th><th style="width:100px;">Typ</th><th style="width:80px;">Treffer</th></tr></thead><tbody>';
        foreach ($sources as $source) {
            $id = (int) $source['id'];
            $title = get_the_title($id);
            $url = $source['type'] === 'attachment' ? wp_get_attachment_url($id) : get_permalink($id);

            if ($title === '' && !$url) {
                $cell = '<em>(gelöscht, ID ' . esc_html((string) $id) . ')</em>';
            } elseif ($url) {
                $cell = '<a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">'
                    . esc_html($title !== '' ? $title : $url) . '</a>';
            } else {
                $cell = esc_html($title);
            }

            echo '<tr><td>' . $cell . '</td><td>' . esc_html((string) $source['type']) . '</td><td>'
                . esc_html((string) (int) $source['count']) . '</td></tr>';
        }
        echo '</tbody></table>';
    }
}

MLT_AI_Agent_Analytics::init();
