<?php
/**
 * Icon-Bibliothek – Ausgabe von Inline-SVG aus den mitgelieferten Sprites
 *
 * Bibliothek: assets/icons/ (Tabler Icons, Elegant Icons; Lizenzen siehe
 * assets/icons/LICENSES.md). Gespeichert wird nur die ID, zum Beispiel
 * "tabler:shirt" oder "elegant:icon_pencil".
 *
 * Sicherheit: Es wird nur gelesen, was zum festen Muster der ID passt und im
 * Sprite des Plugins steht. Der Name fließt nie in einen Dateipfad ein; die
 * Sprite-Datei ergibt sich allein aus der Bibliothek (tabler oder elegant).
 * Das SVG stammt aus dem Plugin selbst und wird deshalb ohne Escaping ausgegeben.
 *
 * @package MediaLabAgencyCore
 * @since   1.40.1
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! function_exists( 'medialab_icon_is_id' ) ) {

    /**
     * Prüft, ob ein Wert das Format einer Icon-ID hat (Bibliothek:Name).
     * Nichts anderes als dieses Format wird als Icon behandelt; alles andere
     * (zum Beispiel ein Emoji) bleibt Text.
     */
    function medialab_icon_is_id( $value ): bool {
        return is_string( $value ) && (bool) preg_match( '/^(tabler|elegant):[a-z0-9_-]{1,80}$/', $value );
    }
}

if ( ! function_exists( 'medialab_icon_svg' ) ) {

    /**
     * Liefert ein Inline-SVG für eine Icon-ID oder einen leeren String,
     * wenn die ID ungültig oder unbekannt ist.
     *
     * Das SVG ist dekorativ (aria-hidden), nutzt currentColor und ist 1em groß;
     * Größe und Farbe kommen aus dem CSS des umgebenden Blocks.
     *
     * @param string $id z. B. "tabler:shirt".
     */
    function medialab_icon_svg( $id ): string {
        static $sprites = [];
        static $cache   = [];

        $id = is_string( $id ) ? trim( $id ) : '';
        if ( ! medialab_icon_is_id( $id ) ) {
            return '';
        }
        if ( isset( $cache[ $id ] ) ) {
            return $cache[ $id ];
        }

        [ $set, $name ] = explode( ':', $id, 2 );

        if ( ! isset( $sprites[ $set ] ) ) {
            $file            = MEDIALAB_CORE_PATH . 'assets/icons/sprite-' . $set . '.svg';
            $sprites[ $set ] = is_readable( $file ) ? (string) file_get_contents( $file ) : '';
        }
        $sprite = $sprites[ $set ];

        $needle = '<symbol id="' . $set . '-' . $name . '" viewBox="';
        $start  = strpos( $sprite, $needle );
        if ( $start === false ) {
            return $cache[ $id ] = '';
        }
        $vb_start = $start + strlen( $needle );
        $vb_end   = strpos( $sprite, '">', $vb_start );
        $end      = ( $vb_end === false ) ? false : strpos( $sprite, '</symbol>', $vb_end );
        if ( $vb_end === false || $end === false ) {
            return $cache[ $id ] = '';
        }

        $viewbox = substr( $sprite, $vb_start, $vb_end - $vb_start );
        $inner   = substr( $sprite, $vb_end + 2, $end - $vb_end - 2 );

        return $cache[ $id ] = sprintf(
            '<svg class="ml-icon ml-icon--%1$s" xmlns="http://www.w3.org/2000/svg" viewBox="%2$s" aria-hidden="true" focusable="false">%3$s</svg>',
            $set,
            esc_attr( $viewbox ),
            $inner
        );
    }
}

/**
 * Konfiguration für die Icon-Auswahl im Editor (window.mlIconLibrary).
 */
function medialab_icon_editor_config(): array {
    return [
        'baseUrl' => MEDIALAB_CORE_URL . 'assets/icons/',
        'version' => MEDIALAB_CORE_VERSION,
    ];
}
