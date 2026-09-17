<?php
/**
 * Provider-Abstraktion für media-lab-ai-agent.
 *
 * @package MediaLab_AI_Agent
 */

if (!defined('ABSPATH')) {
    exit;
}

interface MLT_AI_Provider_Interface {

    /**
     * Sendet eine Nachricht an den jeweiligen AI-Dienst.
     *
     * @param array $attachments Optionale Datei-Anhänge (z.B. PDFs für native
     *   Dokumenten-Analyse), je Eintrag ['path' => string, 'media_type' => string].
     *   Provider, die keine nativen Anhänge unterstützen, ignorieren den
     *   Parameter (Fallback: reiner Text-Kontext im System-Prompt).
     * @return array{text: string, input_tokens: int, output_tokens: int, cost_estimate: float}
     * @throws MLT_AI_Provider_Exception bei API-Fehlern, Timeouts, ungültigem Key etc.
     */
    public function send_message(string $message, string $system_prompt, string $lang, array $attachments = []): array;

    public function get_id(): string;

    public function get_label(): string;

    /**
     * @return array<string,string> model_id => Anzeigename, fürs ACF-Select
     */
    public function get_available_models(): array;

    /**
     * @return array{input: float, output: float} Preis pro 1000 Tokens in EUR
     */
    public function get_cost_per_1k_tokens(string $model): array;
}

class MLT_AI_Provider_Exception extends RuntimeException {
}
