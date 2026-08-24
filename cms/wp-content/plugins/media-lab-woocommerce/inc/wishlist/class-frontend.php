<?php
/**
 * Wunschlisten-Frontend: Buttons + Seiten-Shortcode.
 *
 * Theme-Override für Custom-Produktkarten-Markup (z.B. Themes, die alle
 * WooCommerce-Standard-Hooks entfernen und die Karte selbst zusammenbauen):
 *
 *   add_filter( 'mlw_wishlist_auto_loop_button', '__return_false' );
 *   add_filter( 'mlw_wishlist_auto_single_button', '__return_false' ); // optional, meist nicht nötig
 *
 * Danach im eigenen Karten-Template manuell aufrufen:
 *
 *   echo MediaLab_Wishlist_Frontend::render_button_html( $product->get_id() );
 *
 * Button-Stil: Standard ist 'icon' (reines Herz-Icon). Umstellbar ohne
 * Core-Code anzufassen:
 *   add_filter( 'mlw_wishlist_button_style', fn() => 'text' );       // nur Text
 *   add_filter( 'mlw_wishlist_button_style', fn() => 'icon_text' );  // Icon + Text
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

        // Themes mit vollständig selbst gebautem Produktkarten-Markup können
        // diesen automatischen Hook deaktivieren und render_button_html()
        // stattdessen an eigener Stelle aufrufen (z.B. juwelier-janecka).
        if ( ! apply_filters( 'mlw_wishlist_auto_loop_button', true ) ) return;

        $style = apply_filters( 'mlw_wishlist_button_style', 'icon' );

        echo self::render_button_html( $product->get_id(), false, $style ); // phpcs:ignore WordPress.Security.EscapeOutput -- bereits in render_button_html() escaped
    }

    public static function render_single_button(): void {
        global $product;
        if ( ! $product instanceof WC_Product ) return;

        if ( ! apply_filters( 'mlw_wishlist_auto_single_button', true ) ) return;

        $style = apply_filters( 'mlw_wishlist_button_style', 'icon' );

        echo self::render_button_html( $product->get_id(), true, $style ); // phpcs:ignore WordPress.Security.EscapeOutput -- bereits in render_button_html() escaped
    }


    private static function is_configurable( int $product_id ): bool {
        return function_exists( 'get_field' ) && (bool) get_field( 'is_configurable', $product_id );
    }

    /**
     * Gibt das Wunschlisten-Button-HTML für ein Produkt zurück, ohne es
     * auszugeben. Öffentlich nutzbar für Themes, die die automatischen
     * Hooks (render_loop_button()/render_single_button()) deaktivieren
     * und den Button stattdessen an einer eigenen Stelle im Custom-
     * Produktkarten-Markup platzieren wollen (siehe mlw_wishlist_auto_*-
     * Filter oben in init()).
     *
     * Gibt einen leeren String zurück, wenn das Produkt konfigurierbar
     * ist (bekommt einen eigenen Wizard-Button, siehe is_configurable())
     * - der Aufrufer muss diesen Fall daher NICHT selbst prüfen.
     *
     * @param string $style  'icon' (Standard) | 'text' | 'icon_text'
     *
     * @example
     *   // Im eigenen Produktkarten-Template:
     *   echo MediaLab_Wishlist_Frontend::render_button_html( $product->get_id() );
     *   echo MediaLab_Wishlist_Frontend::render_button_html( $product->get_id(), false, 'icon_text' );
     */
    public static function render_button_html( int $product_id, bool $large = false, string $style = 'icon' ): string {
        if ( self::is_configurable( $product_id ) ) return '';
        if ( ! in_array( $style, [ 'icon', 'text', 'icon_text' ], true ) ) $style = 'icon';

        $label     = MediaLab_Inquiry_Settings::wording( 'add_button' );
        $is_active = class_exists( 'MediaLab_Wishlist_Storage' ) && MediaLab_Wishlist_Storage::has_product( $product_id );

        $show_icon  = $style === 'icon' || $style === 'icon_text';
        $show_label = $style === 'text' || $style === 'icon_text';

        $class = 'mlw-add-to-wishlist'
               . ( $large ? ' mlw-add-to-wishlist--single' : ' mlw-add-to-wishlist--loop' )
               . ' mlw-add-to-wishlist--' . str_replace( '_', '-', $style ) // --icon | --text | --icon-text
               . ( $is_active ? ' is-active' : '' );

        $icon_html = '';
        if ( $show_icon ) {
            // Derselbe Herz-Pfad wie im Nav-Icon (add_nav_icon()) - bewusst
            // identisch gehalten für visuelle Konsistenz.
            $icon_html = '<svg class="mlw-add-to-wishlist__icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8z"/></svg>';
        }

        $label_html = $show_label
            ? '<span class="mlw-add-to-wishlist__label">' . esc_html( $label ) . '</span>'
            : '';

        // Ohne sichtbares Label (reiner Icon-Stil) braucht der Button ein
        // aria-label für Screenreader - bei sichtbarem Label ist der Text
        // selbst schon der zugängliche Name, aria-label wäre dort redundant.
        $aria_label = ! $show_label ? sprintf( ' aria-label="%s"', esc_attr( $label ) ) : '';

        return sprintf(
            '<button type="button" class="%s" data-product-id="%d"%s aria-pressed="%s">%s%s</button>',
            esc_attr( $class ),
            $product_id,
            $aria_label,
            $is_active ? 'true' : 'false',
            $icon_html,
            $label_html
        );
    }


    // ── Wunschlisten-Seite (Shortcode) ───────────────────────────────────────

    /**
     * Verwendung: [mlw_wishlist_page] auf einer eigenen WordPress-Seite platzieren.
     */
    public static function render_wishlist_page(): string {
        $share_token = isset( $_GET['mlw_share'] ) ? sanitize_text_field( wp_unslash( $_GET['mlw_share'] ) ) : '';

        if ( $share_token && class_exists( 'MediaLab_Wishlist_Share' ) ) {
            $items = MediaLab_Wishlist_Share::get_items_by_token( $share_token );

            if ( $items !== null ) {
                $shared_items = MediaLab_Wishlist_Storage::get_items_for_display( $items );
                ob_start();
                include MEDIA_LAB_WC_PATH . 'templates/wishlist/shared.php';
                return ob_get_clean();
            }
            // Ungültiger/abgelaufener Token: bewusst KEIN Fehler, sondern
            // stiller Fallback auf die normale (eigene) Wunschlistenansicht
            // des aktuellen Besuchers darunter.
        }

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