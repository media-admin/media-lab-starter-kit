<?php
/**
 * Such-Icon + Such-Overlay in der Hauptnavigation.
 *
 * Bewusst NICHT von media-lab-woocommerce/MediaLab_Inquiry_Settings abhängig
 * (analog zum Wishlist-Icon-Pattern dort, siehe inc/wishlist/class-frontend.php
 * ::add_nav_icon()) - Suche ist ein allgemeines Theme-/Core-Feature und muss
 * auch ohne aktives WooCommerce-Plugin funktionieren. Toggle liegt daher in
 * Agency Core selbst: Logo / Globale Einstellungen -> UI-Features
 * (siehe inc/acf-settings.php, Feld 'search_enabled').
 *
 * Wiederverwendet die bestehende Theme-Komponente .ajax-search 1:1 (Markup,
 * SCSS, JS) - baut nur eine Overlay-Hülle (Backdrop + Panel + Toggle) drumherum.
 * ajax-search.js selbst wird NICHT verändert.
 *
 * WICHTIG (Timing): render_overlay() hängt an 'wp_footer' mit Standard-
 * Priorität 10. WordPress druckt Footer-Scripts erst bei Priorität 20
 * (wp_print_footer_scripts). Das Overlay-Markup mit der .ajax-search-Box
 * steht dadurch garantiert schon im DOM, bevor ajax-search.js (das nur
 * einmal beim Script-Start querySelectorAll('.ajax-search') aufruft, kein
 * MutationObserver) und unser eigenes Toggle-Script laufen.
 *
 * Datei ablegen unter: inc/nav-search-icon.php
 * Einbindung: require_once in media-lab-agency-core.php, direkt nach
 * inc/ajax-search.php (siehe Hauptdatei).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MediaLab_Nav_Search_Icon {

    public static function init(): void {
        add_filter( 'wp_nav_menu_items', [ __CLASS__, 'add_nav_icon' ], 10, 2 );
        add_action( 'wp_footer',         [ __CLASS__, 'render_overlay' ], 10 );
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
    }

    // ── Nav-Icon ─────────────────────────────────────────────────────────────

    /**
     * Hängt das Such-Icon als zusätzliches <li> ans Ende des Hauptmenüs an.
     * Filter greift für JEDE Menüposition - hier gezielt auf "primary"
     * beschränkt, exakt wie beim Wishlist-Icon in media-lab-woocommerce.
     */
    public static function add_nav_icon( string $items, $args ): string {
        if ( empty( $args->theme_location ) || $args->theme_location !== 'primary' ) {
            return $items;
        }

        if ( ! self::is_enabled() ) {
            return $items;
        }

        $label = __( 'Suche öffnen', 'media-lab-core' );

        $icon = '<svg class="mlc-nav-search-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">'
              . '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>';

        $items .= sprintf(
            '<li class="menu-item mlc-nav-search-item"><button type="button" class="mlc-nav-search-toggle" aria-label="%s" aria-expanded="false" aria-controls="mlc-search-overlay"><span class="mlc-nav-search-icon-wrap">%s</span></button></li>',
            esc_attr( $label ),
            $icon
        );

        return $items;
    }

    // ── Overlay-Markup ───────────────────────────────────────────────────────

    /**
     * Rendert die Overlay-Hülle + die bestehende .ajax-search-Komponente
     * (Formular, Input, Loading-Indicator, Submit-Button, Results-Container)
     * exakt nach dem Markup-Vertrag, den ajax-search.js erwartet:
     * .ajax-search__form / __input / __results (Pflicht), __loading / __submit
     * (optional, werden von ajax-search.js sauber behandelt falls vorhanden).
     */
    public static function render_overlay(): void {
        if ( ! self::is_enabled() ) return;
        ?>
        <div id="mlc-search-overlay" class="mlc-search-overlay" aria-hidden="true">
            <div class="mlc-search-overlay__backdrop"></div>
            <div class="mlc-search-overlay__panel" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Suche', 'media-lab-core' ); ?>">
                <button type="button" class="mlc-search-overlay__close" aria-label="<?php esc_attr_e( 'Suche schließen', 'media-lab-core' ); ?>">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>

                <div class="ajax-search" data-limit="6" data-post-types="post,page,product">
                    <form class="ajax-search__form" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
                        <div class="ajax-search__input-wrapper">
                            <span class="ajax-search__icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                            </span>
                            <input type="search" class="ajax-search__input" name="s" placeholder="<?php esc_attr_e( 'Wonach suchst du?', 'media-lab-core' ); ?>" autocomplete="off">
                            <span class="ajax-search__loading" style="display:none;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke-opacity=".25"/><path d="M21 12a9 9 0 0 0-9-9"/></svg>
                            </span>
                            <button type="submit" class="ajax-search__submit" aria-label="<?php esc_attr_e( 'Suchen', 'media-lab-core' ); ?>">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                            </button>
                        </div>
                    </form>
                    <div class="ajax-search__results" style="display:none;"></div>
                </div>
            </div>
        </div>
        <?php
    }

    // ── Assets ───────────────────────────────────────────────────────────────

    public static function enqueue_assets(): void {
        if ( ! self::is_enabled() ) return;

        // Kein Sass-Build im Plugin (siehe Projekt-Konvention: Plugin-JS/CSS
        // ohne Build-Step, direkt aus assets/) - reines CSS für die
        // Overlay-Hülle. Das eigentliche .ajax-search-Aussehen kommt
        // unverändert aus dem bereits geladenen Theme-Stylesheet.
        wp_enqueue_style(
            'mlc-nav-search-overlay',
            MEDIALAB_CORE_URL . 'assets/css/nav-search-overlay.css',
            [],
            MEDIALAB_CORE_VERSION
        );

        wp_enqueue_script(
            'mlc-nav-search-toggle',
            MEDIALAB_CORE_URL . 'assets/js/nav-search-toggle.js',
            [],
            MEDIALAB_CORE_VERSION,
            true // in_footer - läuft nach dem Overlay-Markup (siehe Klassen-Kommentar oben)
        );
    }

    // ── Helper ───────────────────────────────────────────────────────────────

    /**
     * ACF-Options-Read nach dem etablierten Projekt-Muster: get_field($key, 'option')
     * kapselt den 'options_'-Prefix korrekt (siehe medialab_heartbeat_get_setting()
     * in inc/heartbeat.php als Referenzimplementierung desselben Musters).
     * Default true, falls das Feld (z.B. vor dem ersten Speichern der
     * Optionsseite auf einer frischen Installation) noch keinen Wert hat.
     */
    private static function is_enabled(): bool {
        if ( ! function_exists( 'get_field' ) ) return false;
        $value = get_field( 'search_enabled', 'option' );
        return $value === null ? true : (bool) $value;
    }
}

MediaLab_Nav_Search_Icon::init();
