<?php
/**
 * WooCommerce AJAX Load More Integration
 */

if (!defined('ABSPATH')) exit;

/**
 * Add product data to load more results
 */
add_filter('media_lab_load_more_post_data', function($data, $post_id, $post_type) {
    if ($post_type !== 'product') return $data;
    if (!function_exists('wc_get_product')) return $data;

    $product = wc_get_product($post_id);
    if (!$product) return $data;

    $data['price']         = $product->get_price_html();
    $data['regular_price'] = $product->get_regular_price();
    $data['sale_price']    = $product->get_sale_price();
    $data['in_stock']      = $product->is_in_stock();

    return $data;
}, 10, 3);
