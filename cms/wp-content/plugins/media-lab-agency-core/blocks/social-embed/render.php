<?php
/**
 * Block-Render: Social Media Embed (Facebook / Instagram)
 *
 * Rendert NIE ein aktives iframe/Embed. Der eigentliche Provider-Code
 * (Facebook: iframe / Instagram: blockquote + embed.js) wird erst nach
 * Consent von social-embed-consent.js (Theme) injiziert - siehe dort für
 * die providerabhängige Logik.
 *
 * Unterschied zum älteren medialab/facebook-video-Block (bleibt bestehen,
 * bereits produktive Inhalte funktionieren unverändert weiter):
 * dieser Block kann mehrere Provider bedienen, daher kein statisch
 * vorgerendertes <iframe> mehr, sondern ein leerer .ml-social-embed__embed
 * Container, den JS je nach data-provider befüllt.
 *
 * @package MediaLabAgencyCore
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$provider  = get_field( 'se_provider' ) ?: 'facebook';
$url       = get_field( 'se_url' );
$thumbnail = get_field( 'se_thumbnail' );
$caption   = get_field( 'se_caption' );
$show_text = get_field( 'se_show_text' );

if ( ( empty( $url ) || empty( $thumbnail ) ) && ! $is_preview ) return;

$classes = array_filter( [
    'ml-block-social-embed',
    'ml-social-embed--' . sanitize_html_class( $provider ),
    ! empty( $block['className'] ) ? $block['className'] : '',
    ! empty( $block['align'] )     ? 'align' . $block['align'] : '',
] );
$block_id = ! empty( $block['anchor'] ) ? ' id="' . esc_attr( $block['anchor'] ) . '"' : '';

if ( $is_preview && ( empty( $url ) || empty( $thumbnail ) ) ) {
    echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '" style="padding:2rem;background:#f0f0f0;text-align:center;">';
    echo '<p style="color:#aaa;font-size:.875rem;">' . esc_html__( 'Social Embed – bitte Anbieter, URL und Vorschaubild in den Block-Einstellungen ausfüllen.', 'media-lab-agency-core' ) . '</p>';
    echo '</div>';
    return;
}

$thumb_src = is_array( $thumbnail ) ? ( $thumbnail['sizes']['large'] ?? $thumbnail['url'] ?? '' ) : wp_get_attachment_url( (int) $thumbnail );
$thumb_alt = is_array( $thumbnail ) ? ( $thumbnail['alt'] ?: __( 'Vorschaubild', 'media-lab-agency-core' ) ) : '';
$thumb_w   = is_array( $thumbnail ) ? (int) ( $thumbnail['width']  ?? 0 ) : 0;
$thumb_h   = is_array( $thumbnail ) ? (int) ( $thumbnail['height'] ?? 0 ) : 0;

// Provider-abhängiger Button-Text, gemeinsamer Hint-/Settings-Text (beides Meta-Produkte).
// Registriert über pll_register_string() in inc/social-embed-fields.php.
$button_source = ( $provider === 'instagram' )
    ? 'Instagram-Beitrag laden & anzeigen'
    : 'Facebook-Video laden & abspielen';

$button_text = function_exists( 'pll__' )
    ? pll__( $button_source )
    : __( $button_source, 'media-lab-agency-core' );

$hint_text = function_exists( 'pll__' )
    ? pll__( 'Beim Laden werden Daten an Meta Platforms Ireland Ltd. (USA) übertragen.' )
    : __( 'Beim Laden werden Daten an Meta Platforms Ireland Ltd. (USA) übertragen.', 'media-lab-agency-core' );

$settings_text = function_exists( 'pll__' )
    ? pll__( 'Cookie-Einstellungen anpassen' )
    : __( 'Cookie-Einstellungen anpassen', 'media-lab-agency-core' );
?>
<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
     data-provider="<?php echo esc_attr( $provider ); ?>"
     data-url="<?php echo esc_url( $url ); ?>"
     <?php if ( $provider === 'facebook' ) : ?>data-show-text="<?php echo $show_text ? 'true' : 'false'; ?>"<?php endif; ?>
     <?php echo $block_id; ?>>

    <div class="ml-social-embed__container">

        <img
            class="ml-social-embed__thumbnail"
            src="<?php echo esc_url( $thumb_src ); ?>"
            alt="<?php echo esc_attr( $thumb_alt ); ?>"
            loading="lazy"
            <?php if ( $thumb_w && $thumb_h ) : ?>width="<?php echo (int) $thumb_w; ?>" height="<?php echo (int) $thumb_h; ?>"<?php endif; ?>
        >

        <!-- Wird erst nach Consent von social-embed-consent.js befüllt (iframe bei Facebook, blockquote+embed.js bei Instagram) -->
        <div class="ml-social-embed__embed" hidden></div>

        <button type="button" class="ml-social-embed__play-button" data-se-accept-comfort>
            <span class="ml-social-embed__play-icon ml-social-embed__play-icon--<?php echo esc_attr( $provider ); ?>" aria-hidden="true"></span>
            <?php echo esc_html( $button_text ); ?>
        </button>
    </div>

    <p class="ml-social-embed__hint">
        <?php echo esc_html( $hint_text ); ?>
        <button type="button" class="ml-social-embed__settings-link" data-se-open-settings><?php echo esc_html( $settings_text ); ?></button>
    </p>

    <?php if ( $caption ) : ?>
        <p class="ml-social-embed__caption"><?php echo esc_html( $caption ); ?></p>
    <?php endif; ?>

</div>
