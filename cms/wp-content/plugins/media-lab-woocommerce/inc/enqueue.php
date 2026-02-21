<?php
/**
 * Enqueue WooCommerce styles
 */

if (!defined('ABSPATH')) exit;

add_action('wp_enqueue_scripts', function() {
    // Nur laden wenn WooCommerce Seite
    if (!is_woocommerce() && !is_cart() && !is_checkout() && !is_account_page()) return;
    
    // Custom WooCommerce styles (from theme if exists)
    $style_path = get_template_directory() . '/assets/dist/css/woocommerce.css';
    if (file_exists($style_path)) {
        wp_enqueue_style(
            'media-lab-woocommerce',
            get_template_directory_uri() . '/assets/dist/css/woocommerce.css',
            array('media-lab-theme'),
            filemtime($style_path)
        );
    }
});
