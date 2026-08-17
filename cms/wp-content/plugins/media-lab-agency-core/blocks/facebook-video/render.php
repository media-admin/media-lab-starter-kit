<?php
/**
 * Block-Render: Facebook Video
 *
 * Rendert NIE ein aktives iframe. Das iframe wird mit leerem src + hidden
 * ausgegeben; data-src trägt die eigentliche Embed-URL. Das Nachladen
 * übernimmt fb-video-consent.js im Theme (analog zu google-maps.js),
 * gesteuert über die CookieConsent Public API (Kategorie "comfort").
 *
 * @package MediaLabAgencyCore
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$fb_url    = get_field( 'fbv_url' );
$thumbnail = get_field( 'fbv_thumbnail' );
$caption   = get_field( 'fbv_caption' );
$show_text = get_field( 'fbv_show_text' );

if ( ( empty( $fb_url ) || empty( $thumbnail ) ) && ! $is_preview ) return;

$classes = array_filter( [
    'ml-block-facebook-video',
    ! empty( $block['className'] ) ? $block['className'] : '',
    ! empty( $block['align'] )     ? 'align' . $block['align'] : '',
] );
$block_id = ! empty( $block['anchor'] ) ? ' id="' . esc_attr( $block['anchor'] ) . '"' : '';

if ( $is_preview && ( empty( $fb_url ) || empty( $thumbnail ) ) ) {
    echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '" style="padding:2rem;background:#f0f0f0;text-align:center;">';
    echo '<p style="color:#aaa;font-size:.875rem;">' . esc_html__( 'Facebook Video – bitte URL und Vorschaubild in den Block-Einstellungen ausfüllen.', 'media-lab-agency-core' ) . '</p>';
    echo '</div>';
    return;
}

$embed_src = add_query_arg(
    [
        'href'      => rawurlencode( $fb_url ),
        'show_text' => $show_text ? 'true' : 'false',
        'width'     => '734',
    ],
    'https://www.facebook.com/plugins/video.php'
);

$thumb_src = is_array( $thumbnail ) ? ( $thumbnail['sizes']['large'] ?? $thumbnail['url'] ?? '' ) : wp_get_attachment_url( (int) $thumbnail );
$thumb_alt = is_array( $thumbnail ) ? ( $thumbnail['alt'] ?: __( 'Facebook Video Vorschau', 'media-lab-agency-core' ) ) : '';
$thumb_w   = is_array( $thumbnail ) ? (int) ( $thumbnail['width']  ?? 0 ) : 0;
$thumb_h   = is_array( $thumbnail ) ? (int) ( $thumbnail['height'] ?? 0 ) : 0;

// UI-Texte über Polylang String-Übersetzung (Sprachen → Übersetzungen,
// Gruppe "Facebook Video Block"), registriert in facebook-video-fields.php.
// Fallback auf Deutsch, falls Polylang deaktiviert ist.
$button_text = function_exists( 'pll__' )
    ? pll__( 'Facebook-Video laden & abspielen' )
    : __( 'Facebook-Video laden & abspielen', 'media-lab-agency-core' );

$hint_text = function_exists( 'pll__' )
    ? pll__( 'Beim Laden werden Daten an Meta Platforms Ireland Ltd. (USA) übertragen.' )
    : __( 'Beim Laden werden Daten an Meta Platforms Ireland Ltd. (USA) übertragen.', 'media-lab-agency-core' );

$settings_text = function_exists( 'pll__' )
    ? pll__( 'Cookie-Einstellungen anpassen' )
    : __( 'Cookie-Einstellungen anpassen', 'media-lab-agency-core' );
?>
<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"<?php echo $block_id; ?>>

    <div class="ml-fbv__container" data-fbv-category="comfort">

        <img
            class="ml-fbv__thumbnail"
            src="<?php echo esc_url( $thumb_src ); ?>"
            alt="<?php echo esc_attr( $thumb_alt ); ?>"
            loading="lazy"
            <?php if ( $thumb_w && $thumb_h ) : ?>width="<?php echo (int) $thumb_w; ?>" height="<?php echo (int) $thumb_h; ?>"<?php endif; ?>
        >

        <iframe
            class="ml-fbv__iframe"
            data-src="<?php echo esc_url( $embed_src ); ?>"
            title="<?php esc_attr_e( 'Facebook Video', 'media-lab-agency-core' ); ?>"
            loading="lazy"
            scrolling="no"
            frameborder="0"
            allow="autoplay; clipboard-write; encrypted-media; picture-in-picture; web-share"
            allowfullscreen
            hidden
        ></iframe>

        <button type="button" class="ml-fbv__play-button" data-fbv-accept-comfort>
            <span class="ml-fbv__play-icon" aria-hidden="true"></span>
            <?php echo esc_html( $button_text ); ?>
        </button>
    </div>

    <p class="ml-fbv__hint">
        <?php echo esc_html( $hint_text ); ?>
        <button type="button" class="ml-fbv__settings-link" data-fbv-open-settings><?php echo esc_html( $settings_text ); ?></button>
    </p>

    <?php if ( $caption ) : ?>
        <p class="ml-fbv__caption"><?php echo esc_html( $caption ); ?></p>
    <?php endif; ?>

</div>
