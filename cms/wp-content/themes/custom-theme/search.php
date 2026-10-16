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
 * Produktfilter: Gibt es unter den Treffern Produkte und ist das WooCommerce-Plugin
 * aktiv, steht über den Ergebnissen die Filterleiste samt Trefferanzeige
 * ("Ergebnisse 1–24 von 139 werden angezeigt"), beides aus
 * mlwf_get_search_filter_bar(). Die Ergebnisse stehen dann in
 * .wc-products-container - das JS der Filter (mlwf-filters.js) ersetzt deren
 * Inhalt per AJAX. Die Sortier-Auswahl des Cores entfällt in dem Fall (die
 * Filterleiste hat eine eigene, die auch nach Preis sortiert). Der Wrapper
 * bekommt die Klasse .search-layout--filters: der Trenner unter dem Seitenkopf
 * entfällt, weil dann die Trefferanzeige (.wc-loop-header) wie im Shop den
 * Trenner zu den Ergebnissen setzt.
 *
 * Die einzelne Karte rendert template-parts/search/result-card.php.
 *
 * Pagination anpassen (Pfeil-Texte, Anzahl der Seitenzahlen ...), ohne das Template
 * zu ändern - z. B. im Theme, damit sie wie die Shop-Pagination aussieht:
 *
 *   add_filter( 'ml_search_pagination_args', function ( array $args ): array {
 *       return array_merge( $args, [ 'end_size' => 3, 'mid_size' => 3 ] );
 *   } );
 *
 * Die Argumente gehen an paginate_links(); 'type' bleibt immer 'array'.
 *
 * @package custom-theme
 */

get_header();
get_template_part( 'template-parts/components/breadcrumbs' );

global $wp_query;
$search_query = get_search_query();          // escaped (für die Ausgabe)
$search_raw   = get_search_query( false );   // roh (für Funktionen, die selbst escapen)
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

// Produktfilter + Trefferanzeige (WooCommerce-Plugin, optional)
$filter_bar = ( $search_raw !== '' && function_exists( 'mlwf_get_search_filter_bar' ) )
    ? mlwf_get_search_filter_bar( $search_raw )
    : '';

$layout_class = 'archive-layout search-layout container' . ( $filter_bar !== '' ? ' search-layout--filters' : '' );
?>

<main id="primary" class="site-main">
<div class="<?php echo esc_attr( $layout_class ); ?>">

    <?php /* ── Header ───────────────────────────────────────────────────── */ ?>
    <header class="archive-header">

        <span class="archive-header__badge"><?php esc_html_e( 'Suche', 'custom-theme' ); ?></span>

        <h1 class="archive-header__title">
            <?php if ( $search_query ) : ?>
                <?php
                // Die Anführungszeichen gehören zum Suchbegriff: so bleibt „Begriff“ beim Umbruch zusammen (mobil eigene Zeile)
                $title_format = esc_html( $t( 'serp_title', __( 'Suchergebnisse für: „%s“', 'custom-theme' ) ) );
                $quote_open   = '(?:„|“|‚|«|&quot;|&#039;)?';
                $quote_close  = '(?:“|”|’|»|&quot;|&#039;)?';
                if ( preg_match( '/^(.*?)(' . $quote_open . ')%s(' . $quote_close . ')(.*)$/su', $title_format, $tm ) ) {
                    // phpcs:ignore WordPress.Security.EscapeOutput -- Format per esc_html() escaped, Suchbegriff per esc_html()
                    echo $tm[1] . '<span class="archive-header__term">' . $tm[2] . esc_html( $search_query ) . $tm[3] . '</span>' . $tm[4];
                } else {
                    // phpcs:ignore WordPress.Security.EscapeOutput -- Format per esc_html() escaped, Suchbegriff per esc_html()
                    printf( $title_format, '<span class="archive-header__term">' . esc_html( $search_query ) . '</span>' );
                }
                ?>
            <?php else : ?>
                <?php esc_html_e( 'Suchergebnisse', 'custom-theme' ); ?>
            <?php endif; ?>
        </h1>

        <?php if ( $found_posts > 0 ) : ?>
        <?php /* js-search-count: wird vom Filter-JS nach dem Filtern aktualisiert */ ?>
        <p class="archive-header__count js-search-count"
           data-one="<?php echo esc_attr( $t( 'serp_count_one', '%s Ergebnis' ) ); ?>"
           data-many="<?php echo esc_attr( $t( 'serp_count_many', '%s Ergebnisse' ) ); ?>">
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

        <?php /* Passende Kategorien und Marken (WooCommerce-Plugin, optional) */ ?>
        <?php if ( $search_raw !== '' && function_exists( 'mlw_get_search_term_links' ) ) : ?>
            <?php echo mlw_get_search_term_links( $search_raw ); // phpcs:ignore WordPress.Security.EscapeOutput -- Markup in mlw_get_search_term_links() escaped ?>
        <?php endif; ?>

    </header>

    <?php /* ── Produktfilter + Trefferanzeige (Facetten aus allen gefundenen Produkten) ── */ ?>
    <?php echo $filter_bar; // phpcs:ignore WordPress.Security.EscapeOutput -- Markup in mlwf_get_search_filter_bar() escaped ?>

    <?php if ( $filter_bar !== '' ) : ?><div class="wc-products-container"><?php endif; ?>

    <?php if ( have_posts() ) : ?>

    <?php /* ── Sortierung (optional, Such-Einstellungen; entfällt mit Filterleiste) ── */ ?>
    <?php if ( $filter_bar === '' && $found_posts > 1 && ! empty( $serp['sort_ui'] ) && count( $serp['sort_options'] ) > 1 ) : ?>
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
    $pagination_args = (array) apply_filters( 'ml_search_pagination_args', [
        'prev_text' => '← ' . esc_html__( 'Zurück', 'custom-theme' ),
        'next_text' => esc_html__( 'Weiter', 'custom-theme' ) . ' →',
    ] );
    $pagination_args['type'] = 'array'; // das Markup unten erwartet ein Array

    $pagination = paginate_links( $pagination_args );

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

    <?php if ( $filter_bar !== '' ) : ?></div><!-- .wc-products-container --><?php endif; ?>

</div><!-- .archive-layout -->
</main>

<?php get_footer(); ?>
