<?php
/**
 * Anthropic-Provider für media-lab-ai-agent.
 *
 * @package MediaLab_AI_Agent
 */

if (!defined('ABSPATH')) {
    exit;
}

class MLT_AI_Provider_Anthropic implements MLT_AI_Provider_Interface {

    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION = '2023-06-01';
    private const MAX_RESPONSE_TOKENS = 500;

    public function get_id(): string {
        return 'anthropic';
    }

    public function get_label(): string {
        return 'Anthropic (Claude)';
    }

    public function get_available_models(): array {
        return [
            'claude-haiku-4-5-20251001' => 'Claude Haiku 4.5 (günstig, empfohlen)',
            'claude-sonnet-5'           => 'Claude Sonnet 5 (leistungsstärker)',
        ];
    }

    public function get_cost_per_1k_tokens(string $model): array {
        // Stand: Preisliste regelmäßig gegen https://www.anthropic.com/pricing prüfen.
        $prices = [
            'claude-haiku-4-5-20251001' => ['input' => 0.001, 'output' => 0.005],
            'claude-sonnet-5'           => ['input' => 0.003, 'output' => 0.015],
        ];

        return $prices[$model] ?? ['input' => 0.0, 'output' => 0.0];
    }

    public function send_message(string $message, string $system_prompt, string $lang): array {
        $api_key = mlt_ai_get_decrypted_key('anthropic');
        if ($api_key === '') {
            throw new MLT_AI_Provider_Exception('Kein Anthropic API Key hinterlegt.');
        }

        $model = get_field('mlt_ai_model', 'option') ?: 'claude-haiku-4-5-20251001';

        $response = wp_remote_post(self::API_URL, [
            'headers' => [
                'x-api-key'         => $api_key,
                'anthropic-version' => self::API_VERSION,
                'content-type'      => 'application/json',
            ],
            'body' => wp_json_encode([
                'model'      => $model,
                'max_tokens' => self::MAX_RESPONSE_TOKENS,
                'system'     => $system_prompt,
                'messages'   => [
                    ['role' => 'user', 'content' => $message],
                ],
            ]),
            'timeout' => 20,
        ]);

        if (is_wp_error($response)) {
            throw new MLT_AI_Provider_Exception('Anthropic API nicht erreichbar: ' . $response->get_error_message());
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($status_code !== 200) {
            $error_message = $body['error']['message'] ?? ('HTTP ' . $status_code);
            throw new MLT_AI_Provider_Exception('Anthropic API Fehler: ' . $error_message);
        }

        $text = $body['content'][0]['text'] ?? '';
        if ($text === '') {
            throw new MLT_AI_Provider_Exception('Anthropic API: leere Antwort erhalten.');
        }

        $usage = $body['usage'] ?? [];
        $input_tokens = (int) ($usage['input_tokens'] ?? 0);
        $output_tokens = (int) ($usage['output_tokens'] ?? 0);

        return [
            'text'           => $text,
            'input_tokens'   => $input_tokens,
            'output_tokens'  => $output_tokens,
            'cost_estimate'  => $this->calculate_cost($input_tokens, $output_tokens, $model),
        ];
    }

    private function calculate_cost(int $input_tokens, int $output_tokens, string $model): float {
        $prices = $this->get_cost_per_1k_tokens($model);
        return ($input_tokens / 1000 * $prices['input']) + ($output_tokens / 1000 * $prices['output']);
    }
}
