<?php
/**
 * Theme Setup
 */

if (!defined('ABSPATH')) exit;

function customtheme_setup() {
    // Translation
    load_theme_textdomain('customtheme', CUSTOMTHEME_DIR . '/languages');
    
    // Theme Support
    add_theme_support('automatic-feed-links');
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    
    // Image Sizes (basierend auf Figma)
    add_image_size('customtheme-hero', 1920, 1080, true);
    add_image_size('customtheme-card', 600, 400, true);

    // Additional responsive image sizes
    add_image_size('customtheme-mobile', 375, 0, false);
    add_image_size('customtheme-tablet', 768, 0, false);
    add_image_size('customtheme-desktop', 1200, 0, false);
    
    // Thumbnail sizes
    add_image_size('customtheme-thumbnail-small', 150, 150, true);
    add_image_size('customtheme-thumbnail-medium', 300, 300, true);
    
    // Navigation Menus
    register_nav_menus(array(
        'primary' => __('Primary Menu', 'customtheme'),
        'footer'  => __('Footer Menu', 'customtheme'),
    ));
    
    // HTML5 Support
    add_theme_support('html5', array(
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
        'style',
        'script',
    ));
    
    // Block Editor
    add_theme_support('wp-block-styles');
    add_theme_support('align-wide');
    add_theme_support('editor-styles');
}
add_action('after_setup_theme', 'customtheme_setup');

// Widget Areas
function customtheme_widgets_init() {
    register_sidebar(array(
        'name'          => __('Sidebar', 'customtheme'),
        'id'            => 'sidebar-1',
        'before_widget' => '<section id="%1$s" class="widget %2$s">',
        'after_widget'  => '</section>',
        'before_title'  => '<h2 class="widget-title">',
        'after_title'   => '</h2>',
    ));
    
    register_sidebar(array(
        'name'          => __('Footer 1', 'customtheme'),
        'id'            => 'footer-1',
        'before_widget' => '<div class="widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ));
}
add_action('widgets_init', 'customtheme_widgets_init');