<?php
/**
 * Provider-Registry für media-lab-ai-agent.
 * Ermöglicht Anbieterwechsel über ACF-Option ohne Code-Änderung, sowie
 * Erweiterung um Client-spezifische Provider über den mlt_ai_register_providers Hook.
 *
 * @package MediaLab_AI_Agent
 */

if (!defined('ABSPATH')) {
    exit;
}

class MLT_AI_Provider_Registry {

    /** @var array<string, MLT_AI_Provider_Interface> */
    private static array $providers = [];

    public static function register(MLT_AI_Provider_Interface $provider): void {
        self::$providers[$provider->get_id()] = $provider;
    }

    public static function get_active(): MLT_AI_Provider_Interface {
        $active_id = get_field('mlt_ai_provider', 'option') ?: 'anthropic';

        if (!isset(self::$providers[$active_id])) {
            throw new MLT_AI_Provider_Exception(
                sprintf('AI Provider "%s" ist nicht registriert.', esc_html($active_id))
            );
        }

        return self::$providers[$active_id];
    }

    public static function get(string $provider_id): ?MLT_AI_Provider_Interface {
        return self::$providers[$provider_id] ?? null;
    }

    /**
     * @return array<string, MLT_AI_Provider_Interface>
     */
    public static function get_all(): array {
        return self::$providers;
    }

    /**
     * Für ACF-Select-Choices (Provider-Dropdown).
     * @return array<string,string>
     */
    public static function get_choices(): array {
        $choices = [];
        foreach (self::$providers as $id => $provider) {
            $choices[$id] = $provider->get_label();
        }
        return $choices;
    }
}

/**
 * Registriert die eingebauten Provider und öffnet den Erweiterungspunkt
 * für Client-spezifische Provider (z.B. eigener Azure-OpenAI-Endpoint).
 * Läuft auf plugins_loaded, damit ACF und andere Plugins bereits verfügbar sind.
 */
add_action('plugins_loaded', function () {
    MLT_AI_Provider_Registry::register(new MLT_AI_Provider_Anthropic());
    MLT_AI_Provider_Registry::register(new MLT_AI_Provider_OpenAI());

    /**
     * Erweiterungspunkt: weitere Provider registrieren, ohne den Plugin-Core
     * anzufassen. Beispiel in einem Client-Theme oder Custom-Plugin:
     *
     * add_action('mlt_ai_register_providers', function() {
     *     MLT_AI_Provider_Registry::register(new My_Custom_Provider());
     * });
     */
    do_action('mlt_ai_register_providers');
}, 20);
