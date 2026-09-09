<?php
/**
 * Verschlüsselung der pro-Provider API-Keys für media-lab-ai-agent.
 *
 * Prinzip: AES-256-GCM mit einem Schlüssel, der aus den in wp-config.php
 * vorhandenen Salts (AUTH_KEY / SECURE_AUTH_KEY) abgeleitet wird. Dadurch ist
 * kein zusätzliches Secret nötig, aber der verschlüsselte Wert ist an die
 * jeweilige WP-Installation gebunden (bewusst so — kein Key-Sharing zwischen
 * Client-Sites über die DB hinweg).
 *
 * @package MediaLab_AI_Agent
 */

if (!defined('ABSPATH')) {
    exit;
}

class MLT_AI_Crypto {

    private const CIPHER = 'aes-256-gcm';

    /**
     * Leitet einen 32-Byte-Schlüssel aus den wp-config.php Salts ab.
     * Bricht kontrolliert ab, falls die Salts fehlen (z.B. lokale Fehlkonfiguration),
     * statt mit einem schwachen Fallback-Schlüssel weiterzumachen.
     */
    private static function derive_key(): string {
        if (!defined('AUTH_KEY') || !defined('SECURE_AUTH_KEY') || AUTH_KEY === '' || SECURE_AUTH_KEY === '') {
            throw new RuntimeException(
                'AUTH_KEY / SECURE_AUTH_KEY fehlen in wp-config.php — AI-Agent-Verschlüsselung nicht möglich.'
            );
        }
        return hash('sha256', AUTH_KEY . SECURE_AUTH_KEY . 'mlt_ai_agent', true);
    }

    /**
     * Verschlüsselt einen Klartext-String (z.B. API Key vor dem Speichern in ACF).
     * Rückgabeformat: base64(iv) . ':' . base64(tag) . ':' . base64(ciphertext)
     */
    public static function encrypt(string $plaintext): string {
        if ($plaintext === '') {
            return '';
        }

        $key = self::derive_key();
        $iv_length = openssl_cipher_iv_length(self::CIPHER);
        $iv = openssl_random_pseudo_bytes($iv_length);

        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag);

        if ($ciphertext === false) {
            throw new RuntimeException('AI-Agent: Verschlüsselung fehlgeschlagen.');
        }

        return base64_encode($iv) . ':' . base64_encode($tag) . ':' . base64_encode($ciphertext);
    }

    /**
     * Entschlüsselt einen mit encrypt() erzeugten String.
     * Gibt bei fehlerhaftem/altem Format bewusst '' zurück statt eines Fatal Errors,
     * damit ein manuell im ACF-Feld eingetragener Klartext-Key nicht die ganze
     * Seite crasht — der aufrufende Provider-Code sollte dann einen klaren
     * Fehler ("API Key ungültig") an den Nutzer zurückgeben.
     */
    public static function decrypt(string $encoded): string {
        if ($encoded === '') {
            return '';
        }

        $parts = explode(':', $encoded);
        if (count($parts) !== 3) {
            return '';
        }

        [$iv_b64, $tag_b64, $ciphertext_b64] = $parts;
        $iv = base64_decode($iv_b64, true);
        $tag = base64_decode($tag_b64, true);
        $ciphertext = base64_decode($ciphertext_b64, true);

        if ($iv === false || $tag === false || $ciphertext === false) {
            return '';
        }

        try {
            $key = self::derive_key();
        } catch (RuntimeException $e) {
            mlt_ai_log_error($e->getMessage());
            return '';
        }

        $plaintext = openssl_decrypt($ciphertext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag);

        return $plaintext === false ? '' : $plaintext;
    }
}

/**
 * Liest den API-Key des angegebenen Providers aus ACF, entschlüsselt ihn.
 * Erkennt zusätzlich unverschlüsselte Alt-/Manuell-Eingaben (kein ':' im Wert)
 * und verschlüsselt sie transparent nach — komfortabel, falls ein Key beim
 * ersten Setup direkt im ACF-Backend eingetippt wird.
 */
function mlt_ai_get_decrypted_key(string $provider_id): string {
    $field_name = "mlt_ai_key_{$provider_id}";
    $stored = get_field($field_name, 'option');

    if (empty($stored)) {
        return '';
    }

    // Heuristik: verschlüsselte Werte enthalten immer zwei ':' (iv:tag:ciphertext).
    if (substr_count($stored, ':') !== 2) {
        $encrypted = MLT_AI_Crypto::encrypt($stored);
        update_field($field_name, $encrypted, 'option');
        return $stored;
    }

    return MLT_AI_Crypto::decrypt($stored);
}

/**
 * Zentrales Error-Logging für den AI Agent. Nutzt Sentry falls verfügbar
 * (Konvention aus media-lab-agency-core), sonst error_log() als Fallback.
 */
function mlt_ai_log_error(string $message, array $context = []): void {
    if (function_exists('\Sentry\captureMessage')) {
        \Sentry\captureMessage("[AI-Agent] {$message}");
    }
    error_log('[media-lab-ai-agent] ' . $message . (empty($context) ? '' : ' ' . wp_json_encode($context)));
}
