<?php
/**
 * Kosten-Dashboard für media-lab-ai-agent.
 *
 * Bewusst als eigene Admin-Seite im Plugin selbst umgesetzt (nicht an
 * media-lab-seo gekoppelt), damit das Plugin auch auf fremden/Nicht-Starter-Kit-
 * Projekten standalone funktioniert. Stellt zusätzlich einen Filter bereit,
 * über den z.B. die SEO Toolkit die Zahlen für eine eigene Dashboard-Card
 * abgreifen kann, falls beide Plugins gemeinsam laufen.
 *
 * @package MediaLab_AI_Agent
 */

if (!defined('ABSPATH')) {
    exit;
}

class MLT_AI_Agent_Dashboard {

    public static function init(): void {
        add_action('admin_menu', [self::class, 'register_page']);
        add_action('wp_dashboard_setup', [self::class, 'register_widget']);
        add_filter('mlt_ai_get_cost_summary', [self::class, 'get_summary'], 10, 1);
    }

    public static function register_page(): void {
        // Ursprünglich als Submenu unter der ACF-Options-Page registriert —
        // WP-Core-add_submenu_page() und eine von ACF selbst erzeugte
        // Options-Page als Parent vertragen sich nicht zuverlässig: ACF
        // registriert seine Options-Pages intern anders als normale
        // WP-Menüs, wodurch das Submenu nicht sauber in WordPress' internes
        // Link-Auflösungssystem eingetragen wird (Symptom: generierter Link
        // fehlt "admin.php?page=", zeigt stattdessen direkt auf
        // "/wp-admin/<slug>" → 404/Redirect auf die Startseite).
        // Fix: eigener Top-Level-Menüpunkt, komplett unabhängig von ACF.
        if (!mlt_ai_options_page_available()) {
            return;
        }

        add_menu_page(
            'AI Agent — Kosten',
            'AI Agent Kosten',
            'manage_options',
            'mlt-ai-costs',
            [self::class, 'render_page'],
            'dashicons-chart-bar',
            81
        );
    }

    public static function register_widget(): void {
        wp_add_dashboard_widget(
            'mlt_ai_cost_widget',
            'AI Agent — Kosten (30 Tage)',
            [self::class, 'render_widget']
        );
    }

    /**
     * Zentrale Aggregation, wiederverwendbar für Seite, Widget und externen Filter.
     *
     * @return array{total: float, by_provider: array<string,float>, by_day: array}
     */
    public static function get_summary(int $days = 30): array {
        $rows = mlt_ai_get_spend_last_days($days);

        $total = 0.0;
        $by_provider = [];
        $by_day = [];

        foreach ($rows as $row) {
            $cost = (float) $row['total_cost'];
            $total += $cost;

            $provider = $row['provider_id'];
            $by_provider[$provider] = ($by_provider[$provider] ?? 0) + $cost;

            $date = $row['budget_date'];
            $by_day[$date] = ($by_day[$date] ?? 0) + $cost;
        }

        ksort($by_day);

        return [
            'total'       => $total,
            'by_provider' => $by_provider,
            'by_day'      => $by_day,
        ];
    }

    public static function render_widget(): void {
        $summary = self::get_summary(30);
        $daily_budget = (float) (get_field('mlt_ai_daily_budget', 'option') ?: 0);

        echo '<p><strong>' . esc_html(self::format_cost($summary['total'])) . '</strong> in den letzten 30 Tagen</p>';

        if (!empty($summary['by_provider'])) {
            echo '<ul style="margin:0;">';
            foreach ($summary['by_provider'] as $provider => $cost) {
                echo '<li>' . esc_html(self::provider_label($provider)) . ': ' . esc_html(self::format_cost($cost)) . '</li>';
            }
            echo '</ul>';
        }

        if ($daily_budget > 0) {
            $today = date('Y-m-d');
            $spent_today = $summary['by_day'][$today] ?? 0;
            $pct = min(100, round($spent_today / $daily_budget * 100));
            echo '<p style="margin-top:8px;">Heute: ' . esc_html(self::format_cost($spent_today))
                . ' von ' . esc_html(self::format_cost($daily_budget)) . ' (' . esc_html((string) $pct) . '%)</p>';
        }

        echo '<p><a href="' . esc_url(admin_url('admin.php?page=mlt-ai-costs')) . '">Details ansehen →</a></p>';
    }

    public static function render_page(): void {
        $summary = self::get_summary(30);
        $daily_budget = (float) (get_field('mlt_ai_daily_budget', 'option') ?: 0);

        echo '<div class="wrap"><h1>AI Agent — Kosten-Dashboard</h1>';

        echo '<div style="display:flex;gap:24px;margin:20px 0;">';
        self::render_stat_card('Gesamt (30 Tage)', self::format_cost($summary['total']));
        if ($daily_budget > 0) {
            self::render_stat_card('Tagesbudget', self::format_cost($daily_budget));
        }
        echo '</div>';

        echo '<h2>Nach Anbieter</h2>';
        if (empty($summary['by_provider'])) {
            echo '<p>Noch keine Daten vorhanden.</p>';
        } else {
            echo '<table class="widefat striped" style="max-width:500px;"><thead><tr><th>Anbieter</th><th>Kosten (30 Tage)</th></tr></thead><tbody>';
            foreach ($summary['by_provider'] as $provider => $cost) {
                echo '<tr><td>' . esc_html(self::provider_label($provider)) . '</td><td>' . esc_html(self::format_cost($cost)) . '</td></tr>';
            }
            echo '</tbody></table>';
        }

        echo '<h2>Verlauf pro Tag</h2>';
        self::render_bar_chart($summary['by_day']);

        echo '</div>';
    }

    private static function render_stat_card(string $label, string $value): void {
        echo '<div style="background:#fff;border:1px solid #ccd0d4;border-radius:4px;padding:16px 20px;min-width:180px;">';
        echo '<div style="font-size:12px;color:#666;text-transform:uppercase;">' . esc_html($label) . '</div>';
        echo '<div style="font-size:24px;font-weight:600;">' . esc_html($value) . '</div>';
        echo '</div>';
    }

    /**
     * Einfaches CSS-Balkendiagramm ohne JS-Dependency (kein Chart.js o.ä. nötig
     * für diese überschaubare Datenmenge — max. 30-90 Balken).
     */
    private static function render_bar_chart(array $by_day): void {
        if (empty($by_day)) {
            echo '<p>Noch keine Daten vorhanden.</p>';
            return;
        }

        $max = max($by_day);
        if ($max <= 0) {
            $max = 1;
        }

        echo '<div style="display:flex;align-items:flex-end;gap:4px;height:160px;border-bottom:1px solid #ccd0d4;padding:0 8px;overflow-x:auto;">';
        foreach ($by_day as $date => $cost) {
            $height_pct = max(2, round($cost / $max * 100));
            $formatted_date = date('d.m.', strtotime($date));
            printf(
                '<div title="%s: %s" style="flex:0 0 18px;height:%d%%;background:#1a1a1a;border-radius:2px 2px 0 0;" aria-label="%s"></div>',
                esc_attr($formatted_date),
                esc_attr(self::format_cost($cost)),
                (int) $height_pct,
                esc_attr($formatted_date . ': ' . self::format_cost($cost))
            );
        }
        echo '</div>';
        echo '<p style="font-size:12px;color:#666;">Balken zeigen tägliche Kosten, Maus über Balken für Details.</p>';
    }

    private static function format_cost(float $cost): string {
        return '€ ' . number_format($cost, 2, ',', '.');
    }

    private static function provider_label(string $provider_id): string {
        if ($provider_id === MLT_AI_Embeddings::BUDGET_PROVIDER_ID) {
            return 'OpenAI (Embeddings/RAG)';
        }

        $provider = MLT_AI_Provider_Registry::get($provider_id);
        return $provider ? $provider->get_label() : ucfirst($provider_id);
    }
}

MLT_AI_Agent_Dashboard::init();
