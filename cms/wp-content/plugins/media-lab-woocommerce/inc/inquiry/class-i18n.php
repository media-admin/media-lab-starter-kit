<?php
/**
 * Sprach-Erkennung und Übersetzungs-Auflösung für die Inquiry-Engine.
 *
 * Plugin-agnostisch: funktioniert mit Polylang, WPML oder ganz ohne
 * Mehrsprachigkeits-Plugin. Reihenfolge wie im Cookie-Consent-Modul
 * (inc/cookie-consent.php) im Core-Plugin: Polylang → WPML → WP-Locale.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MediaLab_Inquiry_I18n {

    /**
     * Gibt den 2-Zeichen-Sprachcode der aktuellen Anfrage zurück.
     */
    public static function current_language(): string {
        if ( function_exists( 'pll_current_language' ) ) {
            $lang = pll_current_language( 'slug' );
            if ( $lang ) return (string) $lang;
        }
        if ( defined( 'ICL_LANGUAGE_CODE' ) && ICL_LANGUAGE_CODE ) {
            return (string) ICL_LANGUAGE_CODE;
        }
        $locale = get_locale();
        return substr( $locale, 0, 2 ) ?: 'de';
    }

    /**
     * Sucht in einem Repeater-Array ($rows) die Zeile, deren $code_field
     * zur aktuellen (oder übergebenen) Sprache passt. Fällt auf die erste
     * Zeile zurück, wenn keine Übereinstimmung gefunden wird.
     *
     * @param array       $rows       ACF-Repeater-Zeilen.
     * @param string      $code_field Sub-Field-Name des Sprachcodes, z.B. 'lang_code'.
     * @param string|null $lang       Sprachcode, Default: aktuelle Sprache.
     */
    public static function resolve_row( array $rows, string $code_field, ?string $lang = null ): ?array {
        if ( empty( $rows ) ) return null;

        $lang     = $lang ?? self::current_language();
        $fallback = $rows[0];

        foreach ( $rows as $row ) {
            $code = isset( $row[ $code_field ] ) ? trim( (string) $row[ $code_field ] ) : '';
            if ( $code === $lang ) return $row;
        }

        return $fallback;
    }

    /**
     * Liste konfigurierter Sprachen als [ code => name ], z.B. [ 'de' => 'Deutsch', 'en' => 'English' ].
     */
    public static function active_languages(): array {
        if ( ! function_exists( 'get_field' ) ) return [];
        $rows = get_field( 'mlw_languages', 'option' );
        if ( ! is_array( $rows ) ) return [];

        $out = [];
        foreach ( $rows as $row ) {
            $code = trim( (string) ( $row['lang_code'] ?? '' ) );
            if ( ! $code ) continue;
            $out[ $code ] = $row['lang_name'] ?: $code;
        }
        return $out;
    }

    public static function multilang_enabled(): bool {
        if ( ! function_exists( 'get_field' ) ) return false;
        return (bool) get_field( 'mlw_multilang_enabled', 'option' );
    }
}
