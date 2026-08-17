<?php
/**
 * YouTube Consent-Gate für den nativen core/embed-Block
 *
 * Anders als die Facebook/Instagram-Blöcke KEIN eigener Block, sondern ein
 * Filter auf die native WordPress-Embed-Funktionalität: Redakteure fügen
 * weiterhin einfach eine YouTube-URL im core/embed-Block ein (wie bisher).
 * Der render_block-Filter läuft während do_blocks() (im Frontend, bei
 * the_content) NACH WordPress' eigener oEmbed-Verarbeitung - $block_content
 * enthält an dieser Stelle bereits das fertige <iframe>. Wir ersetzen es
 * durch unser Placeholder-Pattern; das echte iframe wird erst nach Consent
 * von youtube-embed-consent.js (Theme) injiziert.
 *
 * WICHTIG: Betrifft nur das Frontend. Im Block-Editor selbst rendert
 * core/embed seine Vorschau rein client-seitig via oEmbed-REST-Proxy,
 * unabhängig von diesem PHP-Filter - Redakteure sehen beim Bearbeiten
 * weiterhin die normale YouTube-Vorschau (unkritisch, da nur für
 * eingeloggte Redakteure sichtbar, nicht für Website-Besucher).
 *
 * @package MediaLabAgencyCore
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_filter( 'render_block', 'medialab_gate_youtube_embed', 10, 2 );

function medialab_gate_youtube_embed( string $block_content, array $block ): string {
    if ( ( $block['blockName'] ?? '' ) !== 'core/embed' ) return $block_content;
    if ( is_admin() ) return $block_content;

    $url = $block['attrs']['url'] ?? '';
    $video_id = medialab_youtube_extract_id( $url );
    if ( ! $video_id ) return $block_content; // kein YouTube (oder ID nicht erkannt) -> unverändert lassen, z.B. Vimeo etc.

    $thumbnail_src = esc_url( "https://i.ytimg.com/vi/{$video_id}/hqdefault.jpg" );
    $embed_src     = esc_url( "https://www.youtube-nocookie.com/embed/{$video_id}?autoplay=1" );

    $button_text = function_exists( 'pll__' )
        ? pll__( 'YouTube-Video laden & abspielen' )
        : __( 'YouTube-Video laden & abspielen', 'media-lab-agency-core' );

    $hint_text = function_exists( 'pll__' )
        ? pll__( 'Beim Laden werden Daten an Google Ireland Limited (USA) übertragen.' )
        : __( 'Beim Laden werden Daten an Google Ireland Limited (USA) übertragen.', 'media-lab-agency-core' );

    $settings_text = function_exists( 'pll__' )
        ? pll__( 'Cookie-Einstellungen anpassen' )
        : __( 'Cookie-Einstellungen anpassen', 'media-lab-agency-core' );

    ob_start();
    ?>
    <div class="ml-block-youtube-embed" data-video-id="<?php echo esc_attr( $video_id ); ?>" data-url="<?php echo $embed_src; ?>">
        <div class="ml-youtube-embed__container">
            <img
                class="ml-youtube-embed__thumbnail"
                src="<?php echo $thumbnail_src; ?>"
                loading="lazy"
                alt="<?php esc_attr_e( 'YouTube Video Vorschau', 'media-lab-agency-core' ); ?>"
            >
            <!-- Wird erst nach Consent von youtube-embed-consent.js befüllt -->
            <div class="ml-youtube-embed__embed" hidden></div>

            <button type="button" class="ml-youtube-embed__play-button" data-yt-accept-comfort>
                <span class="ml-youtube-embed__play-icon" aria-hidden="true"></span>
                <?php echo esc_html( $button_text ); ?>
            </button>
        </div>

        <p class="ml-youtube-embed__hint">
            <?php echo esc_html( $hint_text ); ?>
            <button type="button" class="ml-youtube-embed__settings-link" data-yt-open-settings><?php echo esc_html( $settings_text ); ?></button>
        </p>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Extrahiert die 11-stellige YouTube-Video-ID aus gängigen URL-Formaten:
 * watch?v=, youtu.be/, embed/, shorts/, youtube-nocookie.com/embed/.
 *
 * @return string|null null wenn keine YouTube-URL bzw. ID nicht erkennbar.
 */
function medialab_youtube_extract_id( string $url ): ?string {
    if ( preg_match(
        '#(?:youtube\.com/(?:watch\?v=|embed/|v/|shorts/)|youtu\.be/|youtube-nocookie\.com/embed/)([A-Za-z0-9_-]{11})#',
        $url,
        $matches
    ) ) {
        return $matches[1];
    }
    return null;
}

/**
 * Polylang String-Übersetzung, gleiches Muster wie bei den anderen
 * Consent-Gates. Admins pflegen Übersetzungen unter Sprachen →
 * Übersetzungen, Gruppe "YouTube Embed".
 *
 * ES-Übersetzungen zum Eintragen:
 *   „YouTube-Video laden & abspielen“
 *     → „Cargar y reproducir el vídeo de YouTube“
 *   „Beim Laden werden Daten an Google Ireland Limited (USA) übertragen.“
 *     → „Al cargar el vídeo se transmiten datos a Google Ireland Limited (EE. UU.).“
 *   „Cookie-Einstellungen anpassen“
 *     → „Ajustar las preferencias de cookies“
 */
add_action( 'init', 'medialab_register_youtube_embed_strings' );

function medialab_register_youtube_embed_strings(): void {
    if ( ! function_exists( 'pll_register_string' ) ) return;

    $group = 'YouTube Embed';

    pll_register_string( 'yt_button',   'YouTube-Video laden & abspielen', $group );
    pll_register_string( 'yt_hint',     'Beim Laden werden Daten an Google Ireland Limited (USA) übertragen.', $group );
    pll_register_string( 'yt_settings', 'Cookie-Einstellungen anpassen', $group );
}
