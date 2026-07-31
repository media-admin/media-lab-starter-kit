<?php
/**
 * Plugin Name: Media Lab WooCommerce
 * Plugin URI:  https://media-lab.de
 * Description: WooCommerce integration for Media Lab Agency sites
 * Version:     1.0.2
 * Author:      Media Lab
 * Text Domain: media-lab-woocommerce
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'MEDIA_LAB_WC_VERSION', '1.0.2' );
define( 'MEDIA_LAB_WC_PATH', plugin_dir_path( __FILE__ ) );
define( 'MEDIA_LAB_WC_URL', plugin_dir_url( __FILE__ ) );

// Includes ohne WooCommerce-Abhängigkeit
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
    require_once MEDIA_LAB_WC_PATH . 'inc/catalog-mode.php';
    require_once MEDIA_LAB_WC_PATH . 'inc/configurator/class-configurator.php';

    // ── Medialab WooCommerce Filters ─────────────────────────────────────────
    require_once MEDIA_LAB_WC_PATH . 'inc/filters/filter-config.php';
    require_once MEDIA_LAB_WC_PATH . 'inc/filters/acf-fields.php';
    require_once MEDIA_LAB_WC_PATH . 'inc/filters/ajax-handlers.php';
    require_once MEDIA_LAB_WC_PATH . 'inc/filters/filter-bar.php';
    require_once MEDIA_LAB_WC_PATH . 'inc/filters/setup.php';
    require_once MEDIA_LAB_WC_PATH . 'inc/filters/admin-overview.php';
} );
