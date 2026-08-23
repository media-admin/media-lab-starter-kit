<?php
/**
 * Plugin Name: Media Lab WooCommerce
 * Plugin URI:  https://media-lab.at
 * Description: WooCommerce integration for Media Lab Agency sites
 * Version:     2.6.0
 * Author:      Media Lab
 * Text Domain: media-lab-woocommerce
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'MEDIA_LAB_WC_VERSION', '2.6.0' );
define( 'MEDIA_LAB_WC_PATH', plugin_dir_path( __FILE__ ) );
define( 'MEDIA_LAB_WC_URL', plugin_dir_url( __FILE__ ) );

// Includes ohne WooCommerce-Abhängigkeit
require_once MEDIA_LAB_WC_PATH . 'inc/price-suffix.php';
require_once MEDIA_LAB_WC_PATH . 'inc/shop-products-per-page.php';
require_once MEDIA_LAB_WC_PATH . 'inc/shortcodes.php';
require_once MEDIA_LAB_WC_PATH . 'inc/ajax-search-wc.php';
require_once MEDIA_LAB_WC_PATH . 'inc/ajax-load-more-wc.php';

// WooCommerce Theme Support
add_action( 'after_setup_theme', function () {
    add_theme_support( 'woocommerce' );
    add_theme_support( 'wc-product-gallery-zoom' );
    add_theme_support( 'wc-product-gallery-lightbox' );
    add_theme_support( 'wc-product-gallery-slider' );
} );

// Alle WooCommerce-abhängigen Includes erst nach plugins_loaded
add_action( 'plugins_loaded', function () {
    if ( ! class_exists( 'WooCommerce' ) ) return;

    require_once MEDIA_LAB_WC_PATH . 'inc/enqueue.php';
    require_once MEDIA_LAB_WC_PATH . 'inc/hpos-product-type-cache-fix.php';

    // ── Inquiry-Engine (gemeinsamer Kern für Cart-Anfrage, Konfigurator-Anfrage & Wunschliste) ──
    require_once MEDIA_LAB_WC_PATH . 'inc/inquiry/class-cpt.php';
    require_once MEDIA_LAB_WC_PATH . 'inc/inquiry/class-i18n.php';
    require_once MEDIA_LAB_WC_PATH . 'inc/inquiry/class-settings.php';
    require_once MEDIA_LAB_WC_PATH . 'inc/inquiry/class-mail.php';
    require_once MEDIA_LAB_WC_PATH . 'inc/inquiry/class-channels.php';
    require_once MEDIA_LAB_WC_PATH . 'inc/inquiry/class-inquiry-engine.php';
    require_once MEDIA_LAB_WC_PATH . 'inc/inquiry/class-upload-cleanup.php';
    // [2.3.0] Aktiviert templates/inquiry-form.php ([mlw_inquiry_form]) - siehe BACKLOG.md,
    // war zuvor an keiner Stelle eingebunden (totes Template).
    require_once MEDIA_LAB_WC_PATH . 'inc/inquiry/class-shortcode.php';

    require_once MEDIA_LAB_WC_PATH . 'inc/catalog-mode.php';
    require_once MEDIA_LAB_WC_PATH . 'inc/configurator/class-configurator.php';
    require_once MEDIA_LAB_WC_PATH . 'inc/configurator/filter-sync.php';

    // ── Wunschliste (nutzt die Inquiry-Engine, siehe oben) ────────────────────
    require_once MEDIA_LAB_WC_PATH . 'inc/wishlist/class-storage.php';
    require_once MEDIA_LAB_WC_PATH . 'inc/wishlist/class-ajax.php';
    require_once MEDIA_LAB_WC_PATH . 'inc/wishlist/class-frontend.php';
    require_once MEDIA_LAB_WC_PATH . 'inc/wishlist/class-share.php';
    require_once MEDIA_LAB_WC_PATH . 'inc/wishlist/class-enqueue.php';

    // ── Medialab WooCommerce Filters ─────────────────────────────────────────
    require_once MEDIA_LAB_WC_PATH . 'inc/filters/class-i18n.php';
    require_once MEDIA_LAB_WC_PATH . 'inc/filters/class-settings.php';
    require_once MEDIA_LAB_WC_PATH . 'inc/filters/filter-config.php';
    require_once MEDIA_LAB_WC_PATH . 'inc/filters/acf-fields.php';
    require_once MEDIA_LAB_WC_PATH . 'inc/filters/ajax-handlers.php';
    require_once MEDIA_LAB_WC_PATH . 'inc/filters/filter-bar.php';
    require_once MEDIA_LAB_WC_PATH . 'inc/filters/setup.php';
    require_once MEDIA_LAB_WC_PATH . 'inc/filters/admin-overview.php';

    MediaLab_Filter_Settings::init();
} );

// ─── Activation / Deactivation ───────────────────────────────────────────────
// Direkter require_once statt Verlass auf 'plugins_loaded': Aktivierung kann
// theoretisch ausgelöst werden, bevor WooCommerce selbst aktiv ist (dann läuft
// zwar ohnehin nichts vom Plugin, aber die Tabelle soll trotzdem sauber
// angelegt werden können, sobald WooCommerce da ist - kein Verlass auf
// Hook-Reihenfolge zwischen Plugin-Aktivierung und 'plugins_loaded').
register_activation_hook( __FILE__, function () {
    require_once MEDIA_LAB_WC_PATH . 'inc/wishlist/class-share.php';
    if ( class_exists( 'MediaLab_Wishlist_Share' ) ) {
        MediaLab_Wishlist_Share::create_table();
    }
} );

register_deactivation_hook( __FILE__, function () {
    wp_clear_scheduled_hook( 'mlw_wishlist_share_cleanup' );
} );
