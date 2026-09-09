<?php
/**
 * REST-Endpoint für media-lab-ai-agent.
 * Bewusst providerunabhängig — kennt nur MLT_AI_Provider_Interface, nicht die
 * konkrete Implementierung. Anbieterwechsel passiert komplett in der Registry/ACF.
 *
 * @package MediaLab_AI_Agent
 */

if (!defined('ABSPATH')) {
    exit;
}

class MLT_AI_Agent_Rest {

    public static function register_routes(): void {
        register_rest_route('medialab/v1', '/ai-chat', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'handle_chat'],
            'permission_callback' => '__return_true',
            'args'                => [
                'message'    => ['required' => true, 'type' => 'string'],
                'session_id' => ['required' => true, 'type' => 'string'],
                'lang'       => ['required' => false, 'type' => 'string'],
            ],
        ]);
    }

    public static function handle_chat(WP_REST_Request $request) {
        if (!get_field('mlt_ai_enabled', 'option')) {
            return new WP_Error('disabled', 'AI Agent ist nicht aktiv.', ['status' => 403]);
        }

        $message = sanitize_textarea_field((string) $request->get_param('message'));
        $session_id = sanitize_text_field((string) $request->get_param('session_id'));
        $lang = sanitize_text_field((string) $request->get_param('lang')) ?: 'de';

        if ($message === '' || $session_id === '' || !preg_match('/^mlt_[a-f0-9]{32}$/', $session_id)) {
            return new WP_Error('invalid_input', 'Ungültige Anfrage.', ['status' => 400]);
        }

        if (mb_strlen($message) > 1000) {
            return new WP_Error('message_too_long', 'Nachricht zu lang.', ['status' => 400]);
        }

        if (self::session_limit_exceeded($session_id)) {
            return new WP_Error('session_limit', 'Nachrichtenlimit für diese Unterhaltung erreicht.', ['status' => 429]);
        }

        if (mlt_ai_budget_exceeded()) {
            return new WP_Error('budget', 'Tagesbudget erreicht.', ['status' => 429]);
        }

        try {
            $provider = MLT_AI_Provider_Registry::get_active();
        } catch (MLT_AI_Provider_Exception $e) {
            mlt_ai_log_error($e->getMessage());
            return new WP_Error('provider_unavailable', 'AI-Dienst nicht konfiguriert.', ['status' => 502]);
        }

        $system_prompt = self::get_system_prompt($lang);
        $system_prompt = self::augment_with_rag_context($system_prompt, $message, $lang);

        try {
            $result = $provider->send_message($message, $system_prompt, $lang);
        } catch (MLT_AI_Provider_Exception $e) {
            mlt_ai_log_error($e->getMessage(), ['provider' => $provider->get_id(), 'lang' => $lang]);
            return new WP_Error('provider_error', 'AI-Dienst aktuell nicht erreichbar.', ['status' => 502]);
        }

        self::log_conversation($session_id, $provider->get_id(), $lang, $message, $result);
        mlt_ai_update_budget($provider->get_id(), $result['cost_estimate']);

        return rest_ensure_response(['reply' => $result['text']]);
    }

    /**
     * Rate-Limit pro Session gegen Missbrauch/Prompt-Injection-Spam.
     * Zählt bewusst nur user-Nachrichten der laufenden Session in der DB,
     * kein zusätzlicher Cache nötig — Sessions sind kurzlebig genug.
     */
    private static function session_limit_exceeded(string $session_id): bool {
        global $wpdb;

        $max_messages = (int) (get_field('mlt_ai_max_messages_per_session', 'option') ?: 15);
        $table = $wpdb->prefix . 'mlt_ai_conversations';

        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE session_id = %s AND message_role = 'user'",
                $session_id
            )
        );

        return $count >= $max_messages;
    }

    /**
     * Liest den System-Prompt für die gegebene Sprache aus dem ACF-Repeater
     * (mlt_ai_system_prompts). Fällt auf den ersten vorhandenen Eintrag zurück,
     * falls die angefragte Sprache nicht konfiguriert ist. Ergänzt immer eine
     * Formatierungs-Vorgabe: das Chat-Widget zeigt nur reinen Text an, Markdown
     * (Überschriften, Fett-Schrift, Aufzählungszeichen) würde als rohe
     * Sonderzeichen ("**", "#", "-") sichtbar bleiben statt formatiert zu werden.
     */
    private static function get_system_prompt(string $lang): string {
        $formatting_note = ' Antworte in normalem Fließtext ohne Markdown-Formatierung '
            . '(keine #-Überschriften, kein **fett**, keine Aufzählungszeichen mit - oder *).';

        $prompts = get_field('mlt_ai_system_prompts', 'option');

        if (empty($prompts) || !is_array($prompts)) {
            return 'Du bist ein hilfreicher Assistent auf einer Unternehmenswebsite. Antworte kurz und freundlich.'
                . $formatting_note;
        }

        foreach ($prompts as $row) {
            if (($row['lang'] ?? '') === $lang) {
                return (string) $row['prompt'] . $formatting_note;
            }
        }

        // Fallback: erster konfigurierter Prompt statt hartem Fehler.
        return (string) ($prompts[0]['prompt'] ?? '') . $formatting_note;
    }

    /**
     * Ergänzt den System-Prompt um relevante Website-Inhalte (RAG). Bricht
     * nie hart ab — falls RAG deaktiviert ist oder kein Treffer gefunden
     * wird, läuft der Chat unverändert mit dem reinen System-Prompt weiter.
     */
    private static function augment_with_rag_context(string $system_prompt, string $message, string $lang): string {
        if (!class_exists('MLT_AI_Rag_Retriever')) {
            return $system_prompt;
        }

        $context_chunks = MLT_AI_Rag_Retriever::get_context($message, $lang);
        if (empty($context_chunks)) {
            return $system_prompt;
        }

        $context_block = implode("\n---\n", $context_chunks);

        return $system_prompt
            . "\n\nRelevante Informationen von der Website (nutze diese für deine Antwort, "
            . "erfinde keine Details, die hier nicht stehen). Jeder Abschnitt beginnt mit "
            . "\"Quelle: <URL>\" — wenn du dich auf einen bestimmten Abschnitt beziehst, "
            . "nenne am Ende deiner Antwort die passende(n) URL(s) als einfachen Klartext-Link:\n"
            . $context_block;
    }

    private static function log_conversation(
        string $session_id,
        string $provider_id,
        string $lang,
        string $user_message,
        array $result
    ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'mlt_ai_conversations';
        $now = current_time('mysql');

        $wpdb->insert($table, [
            'session_id'     => $session_id,
            'provider_id'    => $provider_id,
            'model'          => get_field('mlt_ai_model', 'option') ?: '',
            'lang'           => $lang,
            'message_role'   => 'user',
            'message_text'   => $user_message,
            'input_tokens'   => $result['input_tokens'],
            'output_tokens'  => 0,
            'cost_estimate'  => 0,
            'created_at'     => $now,
        ]);

        $wpdb->insert($table, [
            'session_id'     => $session_id,
            'provider_id'    => $provider_id,
            'model'          => get_field('mlt_ai_model', 'option') ?: '',
            'lang'           => $lang,
            'message_role'   => 'assistant',
            'message_text'   => $result['text'],
            'input_tokens'   => 0,
            'output_tokens'  => $result['output_tokens'],
            'cost_estimate'  => $result['cost_estimate'],
            'created_at'     => $now,
        ]);
    }
}

add_action('rest_api_init', [MLT_AI_Agent_Rest::class, 'register_routes']);
