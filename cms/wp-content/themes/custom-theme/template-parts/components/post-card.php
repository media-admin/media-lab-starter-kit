<?php
/**
 * Template Part: Post Card
 *
 * Wiederverwendbare Post-Card für archive.php, search.php, Widgets u.a.
 *
 * Verwendung:
 *   // Im Loop (global $post gesetzt):
 *   get_template_part('template-parts/components/post-card');
 *
 *   // Mit explizitem Post-Objekt:
 *   set_query_var('post_card_post', $post_object);
 *   get_template_part('template-parts/components/post-card');
 *
 *   // Variante:
 *   set_query_var('post_card_variant', 'horizontal'); // default: 'default'
 *   get_template_part('template-parts/components/post-card');
 *
 * Optionale Erweiterungen (alle abwärtskompatibel, werden nach dem Rendern
 * zurückgesetzt) - genutzt z. B. von template-parts/search/result-card.php:
 *
 *   post_card_badge         string  Badge statt erster Kategorie (z. B. Inhaltstyp)
 *   post_card_type          string  Post-Type-Slug -> Klasse .post-card--type-{slug}
 *   post_card_price         string  Fertiges Preis-HTML (z. B. WooCommerce), vertrauenswürdig
 *   post_card_title_html    string  Titel mit <mark>-Hervorhebung (bereits escaped)
 *   post_card_excerpt_html  string  Ausschnitt mit <mark>-Hervorhebung (bereits escaped)
 *   post_card_link_label    string  Link-Text statt "Lesen"
 *   post_card_show          array   thumbnail|excerpt|date|author|link => bool (Standard: alle true)
 *
 * @package custom-theme
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Post-Objekt holen (aus query_var oder globalem Loop)
$card_post         = get_query_var( 'post_card_post', null );
$card_variant      = get_query_var( 'post_card_variant', 'default' );
$card_badge        = (string) get_query_var( 'post_card_badge', '' );
$card_type         = (string) get_query_var( 'post_card_type', '' );
$card_price        = (string) get_query_var( 'post_card_price', '' );
$card_title_html   = (string) get_query_var( 'post_card_title_html', '' );
$card_excerpt_html = (string) get_query_var( 'post_card_excerpt_html', '' );
$card_link_label   = (string) get_query_var( 'post_card_link_label', '' );
$card_show         = get_query_var( 'post_card_show', [] );

// Query-Vars zurücksetzen
set_query_var( 'post_card_post', null );
set_query_var( 'post_card_variant', 'default' );
set_query_var( 'post_card_badge', '' );
set_query_var( 'post_card_type', '' );
set_query_var( 'post_card_price', '' );
set_query_var( 'post_card_title_html', '' );
set_query_var( 'post_card_excerpt_html', '' );
set_query_var( 'post_card_link_label', '' );
set_query_var( 'post_card_show', [] );

$show = wp_parse_args( is_array( $card_show ) ? $card_show : [], [
    'thumbnail' => true,
    'excerpt'   => true,
    'date'      => true,
    'author'    => true,
    'link'      => true,
] );

if ( $card_post ) {
    $post_id    = $card_post->ID;
    $title      = get_the_title( $card_post );
    $permalink  = get_permalink( $card_post );
    $excerpt    = get_the_excerpt( $card_post );
    $date       = get_the_date( '', $card_post );
    $date_iso   = get_the_date( 'c', $card_post );
    $author     = get_the_author_meta( 'display_name', $card_post->post_author );
    $thumb_id   = get_post_thumbnail_id( $card_post );
} else {
    $post_id    = get_the_ID();
    $title      = get_the_title();
    $permalink  = get_permalink();
    $excerpt    = get_the_excerpt();
    $date       = get_the_date();
    $date_iso   = get_the_date( 'c' );
    $author     = get_the_author();
    $thumb_id   = get_post_thumbnail_id();
}

// Kategorie (erste)
$categories = get_the_category( $post_id );
$category   = ! empty( $categories ) ? $categories[0] : null;

// CSS-Klassen
$card_classes = [ 'post-card' ];
if ( $card_variant !== 'default' ) {
    $card_classes[] = 'post-card--' . esc_attr( $card_variant );
}
if ( $card_type !== '' ) {
    $card_classes[] = 'post-card--type-' . sanitize_html_class( $card_type );
}

// Hervorhebung: nur <mark> zulassen (Quelle ist bereits escaped, das hier ist Absicherung)
$allowed_mark = [ 'mark' => [] ];

$link_label = $card_link_label !== '' ? $card_link_label : __( 'Lesen', 'custom-theme' );
?>

<article class="<?php echo esc_attr( implode( ' ', $card_classes ) ); ?>">

    <?php /* ── Bild ────────────────────────────────────────────────────────── */ ?>
    <?php if ( $show['thumbnail'] && $thumb_id ) : ?>
    <a class="post-card__thumbnail" href="<?php echo esc_url( $permalink ); ?>" tabindex="-1" aria-hidden="true">
        <?php echo wp_get_attachment_image( $thumb_id, 'medium_large', false, [
            'class'   => 'post-card__img',
            'loading' => 'lazy',
            'alt'     => esc_attr( $title ),
        ] ); ?>
    </a>
    <?php endif; ?>

    <?php /* ── Inhalt ──────────────────────────────────────────────────────── */ ?>
    <div class="post-card__content">

        <?php /* Badge: explizit übergeben (z. B. Inhaltstyp) oder erste Kategorie */ ?>
        <?php if ( $card_badge !== '' ) : ?>
        <div class="post-card__category">
            <span class="post-card__category-link post-card__category-link--static">
                <?php echo esc_html( $card_badge ); ?>
            </span>
        </div>
        <?php elseif ( $category ) : ?>
        <div class="post-card__category">
            <a class="post-card__category-link" href="<?php echo esc_url( get_category_link( $category->term_id ) ); ?>">
                <?php echo esc_html( $category->name ); ?>
            </a>
        </div>
        <?php endif; ?>

        <?php /* Titel */ ?>
        <h3 class="post-card__title">
            <a class="post-card__title-link" href="<?php echo esc_url( $permalink ); ?>">
                <?php echo $card_title_html !== '' ? wp_kses( $card_title_html, $allowed_mark ) : esc_html( $title ); ?>
            </a>
        </h3>

        <?php /* Preis (z. B. WooCommerce) */ ?>
        <?php if ( $card_price !== '' ) : ?>
        <div class="post-card__price">
            <?php echo $card_price; // phpcs:ignore WordPress.Security.EscapeOutput -- vertrauenswürdiges Preis-HTML (get_price_html) ?>
        </div>
        <?php endif; ?>

        <?php /* Excerpt */ ?>
        <?php if ( $show['excerpt'] ) : ?>
            <?php if ( $card_excerpt_html !== '' ) : ?>
            <p class="post-card__excerpt">
                <?php echo wp_kses( $card_excerpt_html, $allowed_mark ); ?>
            </p>
            <?php elseif ( $excerpt ) : ?>
            <p class="post-card__excerpt">
                <?php echo esc_html( wp_trim_words( $excerpt, 18, '…' ) ); ?>
            </p>
            <?php endif; ?>
        <?php endif; ?>

        <?php /* Meta + Link */ ?>
        <?php if ( $show['date'] || ( $show['author'] && $author ) || $show['link'] ) : ?>
        <footer class="post-card__footer">
            <div class="post-card__meta">
                <?php if ( $show['date'] ) : ?>
                <time class="post-card__date" datetime="<?php echo esc_attr( $date_iso ); ?>">
                    <?php echo esc_html( $date ); ?>
                </time>
                <?php endif; ?>
                <?php if ( $show['author'] && $author ) : ?>
                    <?php if ( $show['date'] ) : ?>
                    <span class="post-card__meta-sep" aria-hidden="true">·</span>
                    <?php endif; ?>
                <span class="post-card__author"><?php echo esc_html( $author ); ?></span>
                <?php endif; ?>
            </div>
            <?php if ( $show['link'] ) : ?>
            <a class="post-card__link" href="<?php echo esc_url( $permalink ); ?>" aria-label="<?php echo esc_attr( sprintf( '%s: %s', $link_label, $title ) ); ?>">
                <?php echo esc_html( $link_label ); ?> →
            </a>
            <?php endif; ?>
        </footer>
        <?php endif; ?>

    </div>

</article>
