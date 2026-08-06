<?php
/**
 * Wunschlisten-Frontend: Buttons + Seiten-Shortcode.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MediaLab_Wishlist_Frontend {

    public static function init(): void {
        // Button auf Produktkarten (Shop-/Kategorie-Loop)
        add_action( 'woocommerce_after_shop_loop_item', [ __CLASS__, 'render_loop_button' ], 15 );

        // Button auf der Einzelproduktseite - NUR für nicht-konfigurierbare Produkte.
        // Konfigurierbare Produkte bekommen ihren eigenen "Zur Wunschliste"-Button
        // direkt im Wizard (siehe assets/js/configurator.js + wizard.php Navigation).
        add_action( 'woocommerce_single_product_summary', [ __CLASS__, 'render_single_button' ], 31 );

        add_shortcode( 'mlw_wishlist_page', [ __CLASS__, 'render_wishlist_page' ] );

        // Wunschlisten-Icon im Hauptmenü (Theme-Menüposition "primary") - optional,
        // siehe Einstellungen Tab "Navigation". WICHTIG: Der generische Filter
        // 'wp_nav_menu_items' wird genutzt (nicht 'wp_nav_menu_primary_items'),
        // da letzterer am tatsächlichen WordPress-Menü-SLUG hängt (Aussehen → Menüs),
        // nicht an der theme_location - der Menü-Slug ist aber unbekannt/projekt-
        // abhängig. Die theme_location wird stattdessen im Callback selbst geprüft,
        // das greift zuverlässig für Desktop- UND Mobile-Menü im Theme.
        add_filter( 'wp_nav_menu_items', [ __CLASS__, 'add_nav_icon' ], 10, 2 );
    }

    // ── Buttons ──────────────────────────────────────────────────────────────

    public static function render_loop_button(): void {
        global $product;
        if ( ! $product instanceof WC_Product ) return;
        if ( self::is_configurable( $product->get_id() ) ) return; // eigener Wizard-Button

        self::render_button( $product->get_id() );
    }

    public static function render_single_button(): void {
        global $product;
        if ( ! $product instanceof WC_Product ) return;
        if ( self::is_configurable( $product->get_id() ) ) return; // eigener Wizard-Button

        self::render_button( $product->get_id(), true );
    }

    private static function is_configurable( int $product_id ): bool {
        return function_exists( 'get_field' ) && (bool) get_field( 'is_configurable', $product_id );
    }

    private static function render_button( int $product_id, bool $large = false ): void {
        $label = MediaLab_Inquiry_Settings::wording( 'add_button' );
        $class = 'mlw-add-to-wishlist' . ( $large ? ' mlw-add-to-wishlist--single' : ' mlw-add-to-wishlist--loop' );
        printf(
            '<button type="button" class="%s" data-product-id="%d">%s</button>',
            esc_attr( $class ),
            $product_id,
            esc_html( $label )
        );
    }

    // ── Wunschlisten-Seite (Shortcode) ───────────────────────────────────────

    /**
     * Verwendung: [mlw_wishlist_page] auf einer eigenen WordPress-Seite platzieren.
     */
    public static function render_wishlist_page(): string {
        ob_start();
        include MEDIA_LAB_WC_PATH . 'templates/wishlist/page.php';
        return ob_get_clean();
    }

    // ── Menü-Icon ────────────────────────────────────────────────────────────

    /**
     * Hängt das Wunschlisten-Icon als zusätzliches <li> ans Ende des
     * Hauptmenüs an (Filter greift für jeden wp_nav_menu()-Aufruf mit
     * theme_location "primary" - Desktop + Mobile im Theme automatisch).
     */
    public static function add_nav_icon( string $items, $args ): string {
        // Generischer Filter feuert für JEDE Menüposition - hier gezielt auf
        // "primary" einschränken (die theme_location, an der das Theme sein
        // Hauptmenü in header.php registriert, siehe wp_nav_menu()-Aufrufe dort).
        if ( empty( $args->theme_location ) || $args->theme_location !== 'primary' ) {
            return $items;
        }

        if ( ! class_exists( 'MediaLab_Inquiry_Settings' ) || ! MediaLab_Inquiry_Settings::nav_icon_enabled() ) {
            return $items;
        }

        $url = MediaLab_Inquiry_Settings::nav_wishlist_page_url();
        if ( ! $url ) return $items; // keine Seite konfiguriert - Icon lieber weglassen als kaputten Link zeigen

        $show_count = MediaLab_Inquiry_Settings::nav_icon_show_count();
        $count      = MediaLab_Wishlist_Storage::count();
        $label      = MediaLab_Inquiry_Settings::wording( 'wishlist_label' );

        $badge = '';
        if ( $show_count ) {
            $badge = sprintf(
                '<span class="mlw-wishlist-count" style="%s">%d</span>',
                $count > 0 ? '' : 'display:none;',
                $count
            );
        }

        $icon = '<svg class="mlw-nav-wishlist-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8z"/></svg>';

        $items .= sprintf(
            '<li class="menu-item mlw-nav-wishlist-item"><a href="%s" class="mlw-nav-wishlist-link" aria-label="%s"><span class="mlw-nav-wishlist-icon-wrap">%s%s</span></a></li>',
            esc_url( $url ),
            esc_attr( $label ),
            $icon,
            $badge
        );

        return $items;
    }
}

MediaLab_Wishlist_Frontend::init();
