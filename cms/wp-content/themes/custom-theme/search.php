<?php
/**
 * Search Results Template
 *
 * Leitet sich strukturell von archive.php ab: dieselben Bausteine
 * (.archive-layout, .archive-header, .post-grid, .post-card,
 * .archive-pagination, .archive-empty) - Änderungen am Archiv-Design
 * wirken damit automatisch auch auf die Suchergebnisse.
 *
 * Layout, Spalten, Ergebnisse pro Seite, Sortierung und Texte kommen aus den
 * Such-Einstellungen im Plugin (Agency Core → Suche / Live-Suche → Ergebnisseite);
 * ohne Plugin gelten die Standardwerte unten.
 *
 * Die einzelne Karte rendert template-parts/search/result-card.php.
 *
 * @package custom-theme
 */

get_header();
get_template_part( 'template-parts/components/breadcrumbs' );

global $wp_query;
$search_query = get_search_query();
$found_posts  = (int) $wp_query->found_posts;

// Konfiguration (Plugin) mit Fallbacks
$has_settings = class_exists( 'MediaLab_Search_Settings' );
$serp         = $has_settings ? MediaLab_Search_Settings::serp() : [];
$text         = $has_settings ? MediaLab_Search_Settings::get()['text'] : [];

$layout  = $serp['layout'] ?? 'grid';
$columns = (int) ( $serp['columns'] ?? 3 );

$t = static function ( string $key, string $fallback ) use ( $text ): string {
    return ( $text[ $key ] ?? '' ) !== '' ? (string) $text[ $key ] : $fallback;
};

$grid_class = $layout === 'list' ? 'post-grid--list' : 'post-grid--cols-' . $columns;
?>

<main id="primary" class="site-main">
<div class="archive-layout search-layout container">

    <?php /* ── Header ───────────────────────────────────────────────────── */ ?>
    <header class="archive-header">

        <span class="archive-header__badge"><?php esc_html_e( 'Suche', 'custom-theme' ); ?></span>

        <h1 class="archive-header__title">
            <?php if ( $search_query ) : ?>
                <?php
                printf(
                    esc_html( $t( 'serp_title', __( 'Suchergebnisse für: „%s“', 'custom-theme' ) ) ),
                    '<span class="archive-header__term">' . esc_html( $search_query ) . '</span>'
                );
                ?>
            <?php else : ?>
                <?php esc_html_e( 'Suchergebnisse', 'custom-theme' ); ?>
            <?php endif; ?>
        </h1>

        <?php if ( $found_posts > 0 ) : ?>
        <p class="archive-header__count">
            <?php
            printf(
                esc_html( $found_posts === 1
                    ? $t( 'serp_count_one', '%s Ergebnis' )
                    : $t( 'serp_count_many', '%s Ergebnisse' ) ),
                esc_html( number_format_i18n( $found_posts ) )
            );
            ?>
        </p>
        <?php endif; ?>

        <?php /* Suchformular zum Verfeinern */ ?>
        <div class="archive-header__search">
            <?php get_search_form(); ?>
        </div>

    </header>

    <?php if ( have_posts() ) : ?>

    <?php /* ── Sortierung (optional, Such-Einstellungen) ───────────────── */ ?>
    <?php if ( $found_posts > 1 && ! empty( $serp['sort_ui'] ) && count( $serp['sort_options'] ) > 1 ) : ?>
    <div class="search-toolbar">
        <form class="search-sort" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
            <input type="hidden" name="s" value="<?php echo esc_attr( $search_query ); ?>">
            <?php if ( ! empty( $_GET['post_type'] ) ) : ?>
            <input type="hidden" name="post_type" value="<?php echo esc_attr( sanitize_key( wp_unslash( $_GET['post_type'] ) ) ); ?>">
            <?php endif; ?>

            <label class="search-sort__label" for="search-sort">
                <?php echo esc_html( $t( 'serp_sort_label', __( 'Sortieren nach', 'custom-theme' ) ) ); ?>
            </label>
            <select class="search-sort__select" id="search-sort" name="sort" onchange="this.form.submit()">
                <?php foreach ( $serp['sort_options'] as $value => $label ) : ?>
                <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $serp['sort_current'], $value ); ?>>
                    <?php echo esc_html( $label ); ?>
                </option>
                <?php endforeach; ?>
            </select>
            <noscript><button type="submit" class="btn btn--outline">OK</button></noscript>
        </form>
    </div>
    <?php endif; ?>

    <?php /* ── Ergebnisse (gleiches Grid + Karte wie das Archiv) ───────── */ ?>
    <div class="post-grid <?php echo esc_attr( $grid_class ); ?>">
        <?php while ( have_posts() ) : the_post(); ?>
            <?php get_template_part( 'template-parts/search/result-card' ); ?>
        <?php endwhile; ?>
    </div>

    <?php /* ── Pagination ───────────────────────────────────────────────── */ ?>
    <?php
    $pagination = paginate_links( [
        'prev_text' => '← ' . esc_html__( 'Zurück', 'custom-theme' ),
        'next_text' => esc_html__( 'Weiter', 'custom-theme' ) . ' →',
        'type'      => 'array',
    ] );

    if ( $pagination ) :
    ?>
    <nav class="archive-pagination" aria-label="<?php esc_attr_e( 'Seitennavigation', 'custom-theme' ); ?>">
        <ul class="archive-pagination__list">
            <?php foreach ( $pagination as $page ) : ?>
            <li class="archive-pagination__item"><?php echo $page; // phpcs:ignore -- paginate_links() escaped ?></li>
            <?php endforeach; ?>
        </ul>
    </nav>
    <?php endif; ?>

    <?php /* ── Keine Ergebnisse ─────────────────────────────────────────── */ ?>
    <?php else : ?>

    <div class="archive-empty">
        <svg class="archive-empty__icon" width="64" height="64" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="1.5" aria-hidden="true">
            <circle cx="11" cy="11" r="8"/>
            <path d="m21 21-4.35-4.35"/>
            <line x1="11" y1="8" x2="11" y2="14"/>
            <line x1="11" y1="16" x2="11.01" y2="16"/>
        </svg>

        <h2 class="archive-empty__title">
            <?php echo esc_html( $t( 'serp_empty_title', __( 'Keine Ergebnisse gefunden', 'custom-theme' ) ) ); ?>
        </h2>

        <?php if ( $search_query ) : ?>
        <p class="archive-empty__text">
            <?php
            printf(
                esc_html( $t( 'serp_empty_text', __( 'Für „%s“ wurden keine Inhalte gefunden. Versuche es mit anderen Suchbegriffen.', 'custom-theme' ) ) ),
                esc_html( $search_query )
            );
            ?>
        </p>
        <?php endif; ?>

        <div class="archive-empty__form">
            <?php get_search_form(); ?>
        </div>

        <a class="btn btn--outline" href="<?php echo esc_url( home_url( '/' ) ); ?>">
            <?php esc_html_e( '← Zur Startseite', 'custom-theme' ); ?>
        </a>
    </div>

    <?php endif; ?>

</div><!-- .archive-layout -->
</main>

<?php get_footer(); ?>
