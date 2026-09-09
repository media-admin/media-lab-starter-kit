<?php
/**
 * OpenAI-Provider für media-lab-ai-agent.
 *
 * @package MediaLab_AI_Agent
 */

if (!defined('ABSPATH')) {
    exit;
}

class MLT_AI_Provider_OpenAI implements MLT_AI_Provider_Interface {

    private const API_URL = 'https://api.openai.com/v1/chat/completions';
    private const MAX_RESPONSE_TOKENS = 500;

    public function get_id(): string {
        return 'openai';
    }

    public function get_label(): string {
        return 'OpenAI';
    }

    public function get_available_models(): array {
        return [
            'gpt-4o-mini' => 'GPT-4o mini (günstig, empfohlen)',
            'gpt-4o'      => 'GPT-4o (leistungsstärker)',
        ];
    }

    public function get_cost_per_1k_tokens(string $model): array {
        // Stand: Preisliste regelmäßig gegen https://openai.com/api/pricing prüfen.
        $prices = [
            'gpt-4o-mini' => ['input' => 0.00015, 'output' => 0.0006],
            'gpt-4o'      => ['input' => 0.0025, 'output' => 0.01],
        ];

        return $prices[$model] ?? ['input' => 0.0, 'output' => 0.0];
    }

    public function send_message(string $message, string $system_prompt, string $lang): array {
        $api_key = mlt_ai_get_decrypted_key('openai');
        if ($api_key === '') {
            throw new MLT_AI_Provider_Exception('Kein OpenAI API Key hinterlegt.');
        }

        $model = get_field('mlt_ai_model', 'option') ?: 'gpt-4o-mini';

        $response = wp_remote_post(self::API_URL, [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode([
                'model'      => $model,
                'max_tokens' => self::MAX_RESPONSE_TOKENS,
                'messages'   => [
                    ['role' => 'system', 'content' => $system_prompt],
                    ['role' => 'user', 'content' => $message],
                ],
            ]),
            'timeout' => 20,
        ]);

        if (is_wp_error($response)) {
            throw new MLT_AI_Provider_Exception('OpenAI API nicht erreichbar: ' . $response->get_error_message());
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($status_code !== 200) {
            $error_message = $body['error']['message'] ?? ('HTTP ' . $status_code);
            throw new MLT_AI_Provider_Exception('OpenAI API Fehler: ' . $error_message);
        }

        $text = $body['choices'][0]['message']['content'] ?? '';
        if ($text === '') {
            throw new MLT_AI_Provider_Exception('OpenAI API: leere Antwort erhalten.');
        }

        $usage = $body['usage'] ?? [];
        $input_tokens = (int) ($usage['prompt_tokens'] ?? 0);
        $output_tokens = (int) ($usage['completion_tokens'] ?? 0);

        return [
            'text'          => $text,
            'input_tokens'  => $input_tokens,
            'output_tokens' => $output_tokens,
            'cost_estimate' => $this->calculate_cost($input_tokens, $output_tokens, $model),
        ];
    }

    private function calculate_cost(int $input_tokens, int $output_tokens, string $model): float {
        $prices = $this->get_cost_per_1k_tokens($model);
        return ($input_tokens / 1000 * $prices['input']) + ($output_tokens / 1000 * $prices['output']);
    }
}
