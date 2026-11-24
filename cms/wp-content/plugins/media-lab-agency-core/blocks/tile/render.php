<?php
/**
 * Kachel (medialab/tile) – Render-Template
 *
 * Dynamischer Kind-Block des Kachel-Rasters (medialab/tile-grid).
 * Dynamisch, weil die Überschriftenebene vom Eltern-Block per Block-Kontext
 * kommt (save() hat keinen Zugriff auf Kontext).
 *
 * Barrierefreiheit:
 *   - Je Kachel genau EIN Link (kein verschachtelter Link): entweder der
 *     Button-Text oder – ohne Button-Text – der Titel. Der Link wird per CSS
 *     über die ganze Kachel gespannt (::after), die Kachel ist klickbar.
 *   - Rein dekorative Bilder und Icons: alt="" bzw. aria-hidden.
 *   - Überschriftenebene kommt vom Raster (h2–h4), Standard h3.
 *   - Titel und Text erlauben nur Inline-Formatierung ohne Links.
 *
 * Variablen von WordPress: $attributes, $content, $block (WP_Block).
 *
 * @package MediaLabAgencyCore
 * @since   1.40.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$icon       = trim( (string) ( $attributes['icon']      ?? '' ) );
$image_id   = (int) ( $attributes['imageId']            ?? 0 );
$image_url  = trim( (string) ( $attributes['imageUrl']  ?? '' ) );
$image_alt  = (string) ( $attributes['imageAlt']        ?? '' );
$title      = trim( (string) ( $attributes['title']     ?? '' ) );
$text       = trim( (string) ( $attributes['text']      ?? '' ) );
$link_url   = trim( (string) ( $attributes['linkUrl']   ?? '' ) );
$link_text  = trim( (string) ( $attributes['linkText']  ?? '' ) );
$new_tab    = ! empty( $attributes['linkTarget'] );

// Leere Kachel nicht ausgeben.
if ( $title === '' && $text === '' && $icon === '' && $image_id === 0 && $image_url === '' ) {
    return;
}

$level = $block->context['medialab/headingLevel'] ?? 'h3';
$level = in_array( $level, [ 'h2', 'h3', 'h4' ], true ) ? $level : 'h3';

// Nur Inline-Formatierung, keine Links (verschachtelte Links wären ungültig).
$inline_allowed = [
    'strong' => [],
    'b'      => [],
    'em'     => [],
    'i'      => [],
    'br'     => [],
];

$has_link = ( $link_url !== '' );
$rel      = $new_tab ? ' rel="noopener noreferrer"' : '';
$target   = $new_tab ? ' target="_blank"' : '';

$title_plain = wp_strip_all_tags( $title );

$wrapper = get_block_wrapper_attributes( [
    'class' => 'ml-tile' . ( $has_link ? ' ml-tile--linked' : '' ),
] );

// Bild (bevorzugt über Attachment-ID für srcset/Abmessungen).
$image_html = '';
if ( $image_id > 0 ) {
    $image_html = wp_get_attachment_image( $image_id, 'medium_large', false, [
        'class'    => 'ml-tile__img',
        'alt'      => $image_alt,
        'loading'  => 'lazy',
        'decoding' => 'async',
    ] );
}
if ( $image_html === '' && $image_url !== '' ) {
    $image_html = sprintf(
        '<img class="ml-tile__img" src="%s" alt="%s" loading="lazy" decoding="async">',
        esc_url( $image_url ),
        esc_attr( $image_alt )
    );
}

// Zusatz für Screenreader am Link.
$sr_parts = [];
if ( $link_text !== '' && $title_plain !== '' ) {
    $sr_parts[] = $title_plain;
}
if ( $new_tab ) {
    $sr_parts[] = __( 'öffnet in neuem Tab', 'media-lab-agency-core' );
}
$sr_suffix = '';
if ( $sr_parts ) {
    $sr_suffix = ' <span class="ml-tile__sr">(' . esc_html( implode( ', ', $sr_parts ) ) . ')</span>';
}
?>
<li <?php echo $wrapper; ?>>

    <?php if ( $image_html !== '' ) : ?>
    <div class="ml-tile__media"><?php echo $image_html; // phpcs:ignore WordPress.Security.EscapeOutput -- von WP bzw. esc_* erzeugt ?></div>
    <?php endif; ?>

    <?php if ( $icon !== '' ) : ?>
    <div class="ml-tile__icon" aria-hidden="true"><?php echo esc_html( $icon ); ?></div>
    <?php endif; ?>

    <div class="ml-tile__body">

        <?php if ( $title !== '' ) : ?>
        <<?php echo $level; ?> class="ml-tile__title">
            <?php if ( $has_link && $link_text === '' ) : ?>
            <a class="ml-tile__link" href="<?php echo esc_url( $link_url ); ?>"<?php echo $target . $rel; ?>><?php echo wp_kses( $title, $inline_allowed ); ?><?php echo $sr_suffix; // phpcs:ignore WordPress.Security.EscapeOutput -- bereits escaped ?></a>
            <?php else : ?>
            <?php echo wp_kses( $title, $inline_allowed ); ?>
            <?php endif; ?>
        </<?php echo $level; ?>>
        <?php endif; ?>

        <?php if ( $text !== '' ) : ?>
        <p class="ml-tile__text"><?php echo wp_kses( $text, $inline_allowed ); ?></p>
        <?php endif; ?>

        <?php if ( $has_link && $link_text !== '' ) : ?>
        <p class="ml-tile__action">
            <a class="btn btn--outline ml-tile__link" href="<?php echo esc_url( $link_url ); ?>"<?php echo $target . $rel; ?>><?php echo esc_html( $link_text ); ?><?php echo $sr_suffix; // phpcs:ignore WordPress.Security.EscapeOutput -- bereits escaped ?></a>
        </p>
        <?php endif; ?>

    </div>
</li>
