<?php
/**
 * WooCommerce Shortcodes
 */

if (!defined('ABSPATH')) exit;

/**
 * Products Grid
 * Usage: [products_grid columns="3" limit="8" category="shirts" featured="true" sale="true"]
 */
function media_lab_wc_products_grid_shortcode($atts) {
    $atts = shortcode_atts(array(
        'columns' => '3',
        'limit'   => '8',
        'category' => '',
        'featured' => '',
        'sale'    => '',
        'orderby' => 'date',
    ), $atts);

    $args = array(
        'post_type'      => 'product',
        'posts_per_page' => intval($atts['limit']),
        'orderby'        => $atts['orderby'],
    );

    if ($atts['category']) {
        $args['tax_query'] = array(array(
            'taxonomy' => 'product_cat',
            'field'    => 'slug',
            'terms'    => $atts['category'],
        ));
    }

    if ($atts['featured'] === 'true') {
        $args['tax_query'][] = array(
            'taxonomy' => 'product_visibility',
            'field'    => 'name',
            'terms'    => 'featured',
        );
    }

    if ($atts['sale'] === 'true') {
        $args['meta_query'] = array(array(
            'key'     => '_sale_price',
            'value'   => 0,
            'compare' => '>',
            'type'    => 'NUMERIC',
        ));
    }

    $products = new WP_Query($args);

    ob_start();

    if ($products->have_posts()) {
        echo '<ul class="products columns-' . esc_attr($atts['columns']) . '">';
        while ($products->have_posts()) {
            $products->the_post();
            wc_get_template_part('content', 'product');
        }
        echo '</ul>';
        wp_reset_postdata();
    }

    return ob_get_clean();
}
add_shortcode('products_grid', 'media_lab_wc_products_grid_shortcode');
