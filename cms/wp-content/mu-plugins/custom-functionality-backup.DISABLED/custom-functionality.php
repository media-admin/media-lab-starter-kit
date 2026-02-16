<?php
/**
 * Custom Functionality Plugin
 * 
 * Custom Post Types, Taxonomies & ACF Fields
 * 
 * @package CustomTheme
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register Custom Post Types
 */
function customfunc_register_post_types() {
    // Projects (Portfolio)
    register_post_type('project', array(
        'labels' => array(
            'name'               => 'Projekte',
            'singular_name'      => 'Projekt',
            'add_new'            => 'Neu hinzufügen',
            'add_new_item'       => 'Neues Projekt',
            'edit_item'          => 'Projekt bearbeiten',
            'view_item'          => 'Projekt ansehen',
            'search_items'       => 'Projekte suchen',
            'not_found'          => 'Keine Projekte gefunden',
        ),
        'public'             => true,
        'has_archive'        => true,
        'menu_icon'          => 'dashicons-portfolio',
        'supports'           => array('title', 'editor', 'thumbnail', 'excerpt'),
        'show_in_rest'       => true,
        'rewrite'            => array('slug' => 'projekte'),
    ));
    
    // Team Members
    register_post_type('team', array(
        'labels' => array(
            'name'               => 'Team',
            'singular_name'      => 'Team Mitglied',
            'add_new'            => 'Neu hinzufügen',
            'add_new_item'       => 'Neues Team Mitglied',
            'edit_item'          => 'Team Mitglied bearbeiten',
        ),
        'public'             => true,
        'has_archive'        => true,
        'menu_icon'          => 'dashicons-groups',
        'supports'           => array('title', 'editor', 'thumbnail'),
        'show_in_rest'       => true,
        'rewrite'            => array('slug' => 'team'),
    ));
    
    // Testimonials
    register_post_type('testimonial', array(
        'labels' => array(
            'name'               => 'Testimonials',
            'singular_name'      => 'Testimonial',
            'add_new'            => 'Neu hinzufügen',
            'add_new_item'       => 'Neues Testimonial',
        ),
        'public'             => true,
        'has_archive'        => false,
        'menu_icon'          => 'dashicons-format-quote',
        'supports'           => array('title', 'editor', 'thumbnail'),
        'show_in_rest'       => true,
    ));
    
    // Services
    register_post_type('service', array(
        'labels' => array(
            'name'               => 'Leistungen',
            'singular_name'      => 'Leistung',
            'add_new'            => 'Neu hinzufügen',
            'add_new_item'       => 'Neue Leistung',
        ),
        'public'             => true,
        'has_archive'        => true,
        'menu_icon'          => 'dashicons-admin-tools',
        'supports'           => array('title', 'editor', 'thumbnail', 'excerpt'),
        'show_in_rest'       => true,
        'rewrite'            => array('slug' => 'leistungen'),
    ));
    
    // Hero Slides
    register_post_type('slide', array(
        'labels' => array(
            'name'               => 'Hero Slides',
            'singular_name'      => 'Slide',
            'add_new'            => 'Neu hinzufügen',
            'add_new_item'       => 'Neuen Slide',
        ),
        'public'             => false,
        'show_ui'            => true,
        'has_archive'        => false,
        'menu_icon'          => 'dashicons-slides',
        'supports'           => array('title', 'thumbnail'),
        'show_in_rest'       => true,
    ));
    
    // FAQ
    register_post_type('faq', array(
        'labels' => array(
            'name'               => 'FAQ',
            'singular_name'      => 'Frage',
            'add_new'            => 'Neu hinzufügen',
            'add_new_item'       => 'Neue Frage',
        ),
        'public'             => true,
        'has_archive'        => true,
        'menu_icon'          => 'dashicons-editor-help',
        'supports'           => array('title', 'editor'),
        'show_in_rest'       => true,
        'rewrite'            => array('slug' => 'faq'),
    ));
}
add_action('init', 'customfunc_register_post_types');

/**
 * Register Taxonomies
 */
function customfunc_register_taxonomies() {
    // Project Categories
    register_taxonomy('project_category', 'project', array(
        'labels' => array(
            'name'          => 'Projekt Kategorien',
            'singular_name' => 'Projekt Kategorie',
        ),
        'hierarchical'      => true,
        'show_admin_column' => true,
        'show_in_rest'      => true,
        'rewrite'           => array('slug' => 'projekt-kategorie'),
    ));
    
    // Service Categories
    register_taxonomy('service_category', 'service', array(
        'labels' => array(
            'name'          => 'Leistungs-Kategorien',
            'singular_name' => 'Leistungs-Kategorie',
        ),
        'hierarchical'      => true,
        'show_admin_column' => true,
        'show_in_rest'      => true,
    ));
}
add_action('init', 'customfunc_register_taxonomies');

/**
 * Flush Rewrite Rules (run once after activation)
 */
function customfunc_flush_rewrites() {
    customfunc_register_post_types();
    customfunc_register_taxonomies();
    flush_rewrite_rules();
}
// Uncomment once to flush, then comment again:
// add_action('init', 'customfunc_flush_rewrites');

/**
 * Load ACF Fields
 */
add_action('acf/init', function() {
    $acf_fields_file = __DIR__ . '/acf-fields.php';
    if (file_exists($acf_fields_file)) {
        require_once $acf_fields_file;
    }
});