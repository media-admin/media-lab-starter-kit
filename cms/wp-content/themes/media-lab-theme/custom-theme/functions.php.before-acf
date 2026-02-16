<?php
/**
 * Custom Theme Functions
 * 
 * This theme relies on Agency Core plugin for business logic.
 * Theme only handles presentation layer (CSS, JS, Templates).
 * 
 * @package Custom_Theme
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Theme version
define('CUSTOM_THEME_VERSION', '1.0.0');

/**
 * Check if Agency Core is active
 */
if (!defined('AGENCY_CORE_VERSION')) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p><strong>Theme Error:</strong> This theme requires the <strong>Agency Core</strong> plugin. Please ensure it is installed and activated.</p></div>';
    });
}

/**
 * Theme Setup
 */
function customtheme_setup() {
    // Add theme support
    add_theme_support('post-thumbnails');
    add_theme_support('title-tag');
    add_theme_support('custom-logo');
    add_theme_support('html5', array(
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
    ));
    add_theme_support('responsive-embeds');
    add_theme_support('editor-styles');
    
    // Register navigation menus
    register_nav_menus(array(
        'primary' => __('Primary Menu', 'custom-theme'),
        'footer' => __('Footer Menu', 'custom-theme'),
    ));
    
    // Add image sizes
    add_image_size('custom-thumbnail', 400, 300, true);
    add_image_size('custom-medium', 800, 600, true);
    add_image_size('custom-large', 1200, 900, true);
}
add_action('after_setup_theme', 'customtheme_setup');

/**
 * Enqueue scripts and styles
 */
require_once get_template_directory() . '/inc/enqueue.php';

/**
 * Theme-specific customizations
 */

// Add theme wrapper classes to shortcodes
add_filter('agency_core_shortcode_wrapper_class', function($class, $shortcode) {
    return $class . ' theme-styled';
}, 10, 2);

// Customize excerpt length
add_filter('excerpt_length', function($length) {
    return 20;
});

// Customize excerpt more
add_filter('excerpt_more', function($more) {
    return '...';
});

/**
 * Custom Walker for Navigation
 */
require_once get_template_directory() . '/inc/walker-nav-menu.php';

/**
 * Block Patterns (optional)
 */
// require_once get_template_directory() . '/inc/block-patterns.php';

/**
 * Customizer Settings (optional)
 */
// require_once get_template_directory() . '/inc/customizer.php';



/**
 * WooCommerce Support
 */
function customtheme_woocommerce_support() {
    add_theme_support('woocommerce');
    
    // Product gallery features
    add_theme_support('wc-product-gallery-zoom');
    add_theme_support('wc-product-gallery-lightbox');
    add_theme_support('wc-product-gallery-slider');
}
add_action('after_setup_theme', 'customtheme_woocommerce_support');

/**
 * WooCommerce customizations
 */
require_once get_template_directory() . '/inc/woocommerce.php';


/**
 * Fix typographic quotes in shortcodes
 * Converts fancy quotes to normal quotes before shortcode processing
 */
add_filter('the_content', 'fix_shortcode_fancy_quotes', 7); // Priority 7 = before do_shortcode (11)
function fix_shortcode_fancy_quotes($content) {
    // Replace various quote styles with normal quotes in shortcodes
    $content = preg_replace_callback(
        '/\[([^\]]+)\]/',
        function($matches) {
            $shortcode = $matches[1];
            
            // Replace all fancy quote variations with normal quotes
            $shortcode = str_replace(
                ['"', '"', '″', '‶', '〝', '〞', '＂'],  // Fancy quotes
                '"',                                      // Normal quote
                $shortcode
            );
            
            return '[' . $shortcode . ']';
        },
        $content
    );
    
    return $content;
}

/**
 * ACF JSON Save/Load Point
 */
add_filter('acf/settings/save_json', function($path) {
    return get_stylesheet_directory() . '/acf-json';
});

add_filter('acf/settings/load_json', function($paths) {
    unset($paths[0]);
    $paths[] = get_stylesheet_directory() . '/acf-json';
    return $paths;
});