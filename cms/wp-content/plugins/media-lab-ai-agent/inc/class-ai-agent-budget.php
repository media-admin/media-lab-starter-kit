<?php
/**
 * Tages-Budget-Kill-Switch für media-lab-ai-agent
 *
 * @package MediaLab_AI_Agent
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Prüft, ob das Tageslimit (mlt_ai_daily_budget, ACF Option, in EUR) bereits
 * erreicht ist. Gilt provider-übergreifend, da ein Client-Budget unabhängig
 * vom gewählten Anbieter definiert wird.
 */
function mlt_ai_budget_exceeded(): bool {
    global $wpdb;

    $daily_budget = (float) (get_field('mlt_ai_daily_budget', 'option') ?: 0);
    if ($daily_budget <= 0) {
        // 0 oder leer = kein Limit gesetzt. Bewusste Entscheidung statt stillem
        // Default, damit ein vergessenes Feld nicht versehentlich den Agent blockt.
        return false;
    }

    $table = $wpdb->prefix . 'mlt_ai_budget_log';
    $spent_today = (float) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COALESCE(SUM(total_cost), 0) FROM {$table} WHERE budget_date = %s",
            current_time('Y-m-d')
        )
    );

    return $spent_today >= $daily_budget;
}

/**
 * Schreibt die Kosten eines Requests fort (UPSERT über UNIQUE KEY budget_date+provider_id).
 */
function mlt_ai_update_budget(string $provider_id, float $cost_estimate): void {
    global $wpdb;
    $table = $wpdb->prefix . 'mlt_ai_budget_log';

    $wpdb->query(
        $wpdb->prepare(
            "INSERT INTO {$table} (budget_date, provider_id, total_cost, total_requests)
             VALUES (%s, %s, %f, 1)
             ON DUPLICATE KEY UPDATE
                total_cost = total_cost + VALUES(total_cost),
                total_requests = total_requests + 1",
            current_time('Y-m-d'),
            $provider_id,
            $cost_estimate
        )
    );
}

/**
 * Für die geplante Kosten-Dashboard-Card im SEO-Toolkit: Summe der letzten N Tage.
 */
function mlt_ai_get_spend_last_days(int $days = 30): array {
    global $wpdb;
    $table = $wpdb->prefix . 'mlt_ai_budget_log';

    return $wpdb->get_results(
        $wpdb->prepare(
            "SELECT budget_date, provider_id, total_cost, total_requests
             FROM {$table}
             WHERE budget_date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
             ORDER BY budget_date ASC",
            $days
        ),
        ARRAY_A
    );
}
