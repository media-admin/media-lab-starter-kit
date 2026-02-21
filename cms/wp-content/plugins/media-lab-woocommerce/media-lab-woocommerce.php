<?php
/**
 * Plugin Name: Media Lab WooCommerce
 * Plugin URI:  https://media-lab.de
 * Description: WooCommerce integration for Media Lab Agency sites
 * Version:     1.0.0
 * Author:      Media Lab
 * Text Domain: media-lab-woocommerce
 */

if (!defined('ABSPATH')) exit;

define('MEDIA_LAB_WC_VERSION', '1.0.0');
define('MEDIA_LAB_WC_PATH', plugin_dir_path(__FILE__));
define('MEDIA_LAB_WC_URL', plugin_dir_url(__FILE__));

// Includes
require_once MEDIA_LAB_WC_PATH . 'inc/shortcodes.php';
require_once MEDIA_LAB_WC_PATH . 'inc/ajax-search-wc.php';
require_once MEDIA_LAB_WC_PATH . 'inc/ajax-load-more-wc.php';
require_once MEDIA_LAB_WC_PATH . 'inc/enqueue.php';
require_once MEDIA_LAB_WC_PATH . 'inc/catalog-mode.php';

// WooCommerce Theme Support
add_action('after_setup_theme', function() {
    add_theme_support('woocommerce');
    add_theme_support('wc-product-gallery-zoom');
    add_theme_support('wc-product-gallery-lightbox');
    add_theme_support('wc-product-gallery-slider');
});
