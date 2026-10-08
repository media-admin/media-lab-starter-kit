<?php
/**
 * Comments Template
 *
 * Wird von single.php per comments_template() geladen. Ohne diese Datei fällt
 * WordPress auf die veraltete Kern-Datei wp-includes/theme-compat/comments.php
 * zurück ("Deprecated: Theme ohne comments.php").
 *
 * Aufbau und Optik orientieren sich an den WooCommerce-Rezensionen (Avatar links,
 * Text rechts, getrennte Einträge, Theme-Formularfelder) - Styles:
 * assets/src/scss/templates/_comments.scss
 *
 * @package custom-theme
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Passwortgeschützte Beiträge: Kommentare erst nach Eingabe des Passworts
if ( post_password_required() ) {
    return;
}

if ( ! function_exists( 'customtheme_comment' ) ) {
    /**
     * Markup eines einzelnen Kommentars (Callback für wp_list_comments()).
     * Das schließende </li> bzw. </div> setzt WordPress selbst.
     */
    function customtheme_comment( $comment, $args, $depth ) {
        $tag = ( 'div' === $args['style'] ) ? 'div' : 'li';
        ?>
        <<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput ?> id="comment-<?php comment_ID(); ?>" <?php comment_class( 'comment' ); ?>>
            <article class="comment__container" id="div-comment-<?php comment_ID(); ?>">

                <?php if ( ! empty( $args['avatar_size'] ) ) : ?>
                    <?php echo get_avatar( $comment, (int) $args['avatar_size'], '', '', [ 'class' => 'comment__avatar' ] ); ?>
                <?php endif; ?>

                <div class="comment__body">

                    <header class="comment__meta">
                        <span class="comment__author"><?php echo get_comment_author_link( $comment ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
                        <a class="comment__date" href="<?php echo esc_url( get_comment_link( $comment, $args ) ); ?>">
                            <time datetime="<?php echo esc_attr( get_comment_time( 'c' ) ); ?>">
                                <?php
                                printf(
                                    /* translators: 1: Datum, 2: Uhrzeit */
                                    esc_html__( '%1$s, %2$s', 'custom-theme' ),
                                    esc_html( get_comment_date( '', $comment ) ),
                                    esc_html( get_comment_time() )
                                );
                                ?>
                            </time>
                        </a>
                        <?php edit_comment_link( esc_html__( 'Bearbeiten', 'custom-theme' ), '<span class="comment__edit">', '</span>' ); ?>
                    </header>

                    <?php if ( '0' === $comment->comment_approved ) : ?>
                    <p class="comment__moderation">
                        <?php esc_html_e( 'Dein Kommentar wartet auf Freischaltung.', 'custom-theme' ); ?>
                    </p>
                    <?php endif; ?>

                    <div class="comment__text">
                        <?php comment_text(); ?>
                    </div>

                    <?php
                    comment_reply_link( array_merge( $args, [
                        'add_below' => 'div-comment',
                        'depth'     => $depth,
                        'max_depth' => $args['max_depth'],
                        'before'    => '<div class="comment__reply">',
                        'after'     => '</div>',
                    ] ) );
                    ?>

                </div>
            </article>
        <?php
    }
}
?>

<section id="comments" class="comments-area">

    <?php if ( have_comments() ) : ?>

    <h2 class="comments-title">
        <?php
        printf(
            esc_html( _n( '%s Kommentar', '%s Kommentare', get_comments_number(), 'custom-theme' ) ),
            esc_html( number_format_i18n( get_comments_number() ) )
        );
        ?>
    </h2>

    <ol class="comment-list">
        <?php
        wp_list_comments( [
            'style'       => 'ol',
            'short_ping'  => true,
            'avatar_size' => 48,
            'callback'    => 'customtheme_comment',
        ] );
        ?>
    </ol>

    <?php the_comments_navigation( [
        'prev_text' => '← ' . esc_html__( 'Ältere Kommentare', 'custom-theme' ),
        'next_text' => esc_html__( 'Neuere Kommentare', 'custom-theme' ) . ' →',
    ] ); ?>

    <?php endif; ?>

    <?php if ( ! comments_open() && get_comments_number() && post_type_supports( get_post_type(), 'comments' ) ) : ?>
    <p class="comments-closed"><?php esc_html_e( 'Kommentare sind geschlossen.', 'custom-theme' ); ?></p>
    <?php endif; ?>

    <?php
    comment_form( [
        'title_reply'        => esc_html__( 'Schreibe einen Kommentar', 'custom-theme' ),
        'title_reply_before' => '<h2 id="reply-title" class="comment-reply-title">',
        'title_reply_after'  => '</h2>',
        'class_form'         => 'comment-form',
        'class_submit'       => 'btn btn--primary',
        'label_submit'       => esc_html__( 'Kommentar abschicken', 'custom-theme' ),
    ] );
    ?>

</section>
