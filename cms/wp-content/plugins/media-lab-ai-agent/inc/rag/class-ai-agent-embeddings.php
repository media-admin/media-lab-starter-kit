<?php
/**
 * Embedding-Erzeugung für das RAG-Modul.
 *
 * Bewusst getrennt von der Provider-Registry des Chat-Moduls: Embeddings
 * laufen unabhängig vom gewählten Chat-Anbieter (Anthropic, OpenAI, ...)
 * immer über die OpenAI Embeddings API, da Anthropic aktuell keine eigene
 * Embedding-API anbietet. Falls sich das ändert, kann hier ebenfalls eine
 * Registry ergänzt werden — für den Start reicht ein einzelner Client.
 *
 * @package MediaLab_AI_Agent
 */

if (!defined('ABSPATH')) {
    exit;
}

class MLT_AI_Embeddings {

    private const API_URL = 'https://api.openai.com/v1/embeddings';
    private const MODEL = 'text-embedding-3-small';
    // Stand: regelmäßig gegen https://openai.com/api/pricing prüfen.
    private const COST_PER_1K_TOKENS = 0.00002;
    public const BUDGET_PROVIDER_ID = 'openai-embeddings';

    /**
     * Erzeugt einen Embedding-Vektor für den gegebenen Text und loggt die
     * Kosten unter einer eigenen Budget-Provider-ID ("openai-embeddings"),
     * getrennt vom Chat-Provider — Embeddings fallen unabhängig davon an,
     * ob der Chat gerade über Anthropic oder OpenAI läuft.
     *
     * @return array{vector: float[], model: string}
     * @throws MLT_AI_Provider_Exception
     */
    public static function create(string $text): array {
        $api_key = mlt_ai_get_decrypted_key('openai');
        if ($api_key === '') {
            throw new MLT_AI_Provider_Exception(
                'Kein OpenAI API Key hinterlegt — wird auch für Embeddings benötigt (RAG-Modul).'
            );
        }

        $response = wp_remote_post(self::API_URL, [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode([
                'model' => self::MODEL,
                'input' => $text,
            ]),
            'timeout' => 20,
        ]);

        if (is_wp_error($response)) {
            throw new MLT_AI_Provider_Exception('Embedding-API nicht erreichbar: ' . $response->get_error_message());
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($status_code !== 200) {
            $error_message = $body['error']['message'] ?? ('HTTP ' . $status_code);
            throw new MLT_AI_Provider_Exception('Embedding-API Fehler: ' . $error_message);
        }

        $vector = $body['data'][0]['embedding'] ?? null;
        if (!is_array($vector)) {
            throw new MLT_AI_Provider_Exception('Embedding-API: ungültige Antwort erhalten.');
        }

        $total_tokens = (int) ($body['usage']['total_tokens'] ?? 0);
        $cost = $total_tokens / 1000 * self::COST_PER_1K_TOKENS;
        mlt_ai_update_budget(self::BUDGET_PROVIDER_ID, $cost);

        return [
            'vector' => $vector,
            'model'  => self::MODEL,
        ];
    }

    /**
     * Cosine-Similarity zweier Vektoren gleicher Länge. Rückgabe zwischen -1 und 1,
     * höher = ähnlicher.
     */
    public static function cosine_similarity(array $a, array $b): float {
        $count = count($a);
        if ($count === 0 || $count !== count($b)) {
            return 0.0;
        }

        $dot = 0.0;
        $norm_a = 0.0;
        $norm_b = 0.0;

        for ($i = 0; $i < $count; $i++) {
            $dot += $a[$i] * $b[$i];
            $norm_a += $a[$i] ** 2;
            $norm_b += $b[$i] ** 2;
        }

        if ($norm_a === 0.0 || $norm_b === 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($norm_a) * sqrt($norm_b));
    }
}
