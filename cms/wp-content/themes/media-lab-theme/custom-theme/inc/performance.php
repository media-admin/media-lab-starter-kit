<?php
/**
 * Performance Optimization Functions
 * 
 * @package CustomTheme
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add loading="lazy" to images in content
 */
function customtheme_add_lazy_loading($content) {
    if (is_admin()) {
        return $content;
    }
    
    // Add loading="lazy" to img tags that don't have it
    $content = preg_replace(
        '/<img((?![^>]*loading=)[^>]*)>/i',
        '<img$1 loading="lazy">',
        $content
    );
    
    return $content;
}
add_filter('the_content', 'customtheme_add_lazy_loading');
add_filter('post_thumbnail_html', 'customtheme_add_lazy_loading');
add_filter('widget_text', 'customtheme_add_lazy_loading');

/**
 * Add loading="lazy" to iframes (YouTube, Vimeo, etc.)
 */
function customtheme_lazy_load_iframes($content) {
    if (is_admin()) {
        return $content;
    }
    
    $content = preg_replace(
        '/<iframe((?![^>]*loading=)[^>]*)>/i',
        '<iframe$1 loading="lazy">',
        $content
    );
    
    return $content;
}
add_filter('the_content', 'customtheme_lazy_load_iframes');

/**
 * Disable lazy loading for above-the-fold images
 */
function customtheme_skip_lazy_loading_hero($attr, $attachment, $size) {
    // Hero images should load immediately
    if ($size === 'customtheme-hero') {
        $attr['loading'] = 'eager';
    }
    
    return $attr;
}
add_filter('wp_get_attachment_image_attributes', 'customtheme_skip_lazy_loading_hero', 10, 3);

/**
 * Preload critical hero image
 */
function customtheme_preload_hero_image() {
    // Only on homepage
    if (!is_front_page()) {
        return;
    }
    
    // Get hero image if ACF is active
    if (function_exists('get_field')) {
        $hero_image = get_field('hero_image');
        if ($hero_image) {
            $image_url = wp_get_attachment_image_url($hero_image, 'customtheme-hero');
            if ($image_url) {
                echo '<link rel="preload" as="image" href="' . esc_url($image_url) . '">' . "\n";
            }
        }
    }
}
add_action('wp_head', 'customtheme_preload_hero_image', 1);

/**
 * Remove unnecessary WordPress scripts
 */
function customtheme_dequeue_scripts() {
    // Remove jQuery Migrate (meist nicht benötigt)
    wp_deregister_script('jquery-migrate');
    
    // Remove WP Embed Script on non-singular pages
    if (!is_singular()) {
        wp_deregister_script('wp-embed');
    }
    
    // Remove Emoji Scripts (meist nicht benötigt)
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('admin_print_scripts', 'print_emoji_detection_script');
    remove_action('wp_print_styles', 'print_emoji_styles');
    remove_action('admin_print_styles', 'print_emoji_styles');
    remove_filter('the_content_feed', 'wp_staticize_emoji');
    remove_filter('comment_text_rss', 'wp_staticize_emoji');
    remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
    
    // Remove Gutenberg Block CSS if not using blocks
    // wp_dequeue_style('wp-block-library');
    // wp_dequeue_style('wp-block-library-theme');
    // wp_dequeue_style('global-styles');
}
add_action('wp_enqueue_scripts', 'customtheme_dequeue_scripts', 100);

/**
 * Disable Emoji DNS Prefetch
 */
function customtheme_disable_emoji_dns_prefetch($urls, $relation_type) {
    if ('dns-prefetch' === $relation_type) {
        $emoji_url = apply_filters('emoji_svg_url', 'https://s.w.org/images/core/emoji/');
        $urls = array_diff($urls, array($emoji_url));
    }
    return $urls;
}
add_filter('wp_resource_hints', 'customtheme_disable_emoji_dns_prefetch', 10, 2);

/**
 * Remove query strings from static resources
 */
function customtheme_remove_query_strings($src) {
    if (strpos($src, '?ver=')) {
        $src = remove_query_arg('ver', $src);
    }
    return $src;
}
add_filter('script_loader_src', 'customtheme_remove_query_strings', 15);
add_filter('style_loader_src', 'customtheme_remove_query_strings', 15);

/**
 * Defer JavaScript Loading
 */
function customtheme_defer_scripts($tag, $handle, $src) {
    // Don't defer if in admin
    if (is_admin()) {
        return $tag;
    }
    
    // Scripts to defer
    $defer_scripts = array(
        'customtheme-main',
    );
    
    if (in_array($handle, $defer_scripts)) {
        return str_replace('<script', '<script defer', $tag);
    }
    
    return $tag;
}
add_filter('script_loader_tag', 'customtheme_defer_scripts', 10, 3);

/**
 * Clean up WordPress <head>
 */
function customtheme_cleanup_head() {
    // Remove WP version
    remove_action('wp_head', 'wp_generator');
    
    // Remove WLW Manifest
    remove_action('wp_head', 'wlwmanifest_link');
    
    // Remove RSD Link
    remove_action('wp_head', 'rsd_link');
    
    // Remove Shortlink
    remove_action('wp_head', 'wp_shortlink_wp_head');
    
    // Remove REST API Links (if not needed)
    remove_action('wp_head', 'rest_output_link_wp_head');
    remove_action('template_redirect', 'rest_output_link_header', 11);
}
add_action('init', 'customtheme_cleanup_head');