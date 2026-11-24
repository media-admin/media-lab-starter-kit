<?php
/**
 * Schritt (medialab/step) – Render-Template
 *
 * Dynamischer Kind-Block des Schritte-Blocks (medialab/steps). Dynamisch,
 * weil die Überschriftenebene vom Eltern-Block per Block-Kontext kommt.
 *
 * Die Nummer wird nicht gespeichert, sondern per CSS-Zähler aus der
 * Reihenfolge erzeugt – Schritte lassen sich also umsortieren, ohne
 * Nummern nachzuziehen. Semantik: <ol role="list"> mit <li>.
 *
 * Variablen von WordPress: $attributes, $content, $block (WP_Block).
 *
 * @package MediaLabAgencyCore
 * @since   1.40.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$title = trim( (string) ( $attributes['title'] ?? '' ) );
$text  = trim( (string) ( $attributes['text']  ?? '' ) );

// Leeren Schritt nicht ausgeben (würde sonst eine leere Nummer zeigen).
if ( $title === '' && $text === '' ) {
    return;
}

$level = $block->context['medialab/headingLevel'] ?? 'h3';
$level = in_array( $level, [ 'h2', 'h3', 'h4' ], true ) ? $level : 'h3';

$inline_allowed = [
    'strong' => [],
    'b'      => [],
    'em'     => [],
    'i'      => [],
    'br'     => [],
    'a'      => [ 'href' => [], 'target' => [], 'rel' => [] ],
];

$wrapper = get_block_wrapper_attributes( [ 'class' => 'ml-step' ] );
?>
<li <?php echo $wrapper; ?>>
    <div class="ml-step__body">

        <?php if ( $title !== '' ) : ?>
        <<?php echo $level; ?> class="ml-step__title"><?php echo wp_kses( $title, $inline_allowed ); ?></<?php echo $level; ?>>
        <?php endif; ?>

        <?php if ( $text !== '' ) : ?>
        <p class="ml-step__text"><?php echo wp_kses( $text, $inline_allowed ); ?></p>
        <?php endif; ?>

    </div>
</li>
