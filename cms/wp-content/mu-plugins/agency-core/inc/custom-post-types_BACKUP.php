<?php
/**
 * Custom Post Types
 * 
 * Register all custom post types for the agency core functionality.
 * These CPTs persist across theme changes.
 * 
 * @package Agency_Core
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register Team CPT
 */
function agency_core_register_team_cpt() {
    $labels = array(
        'name' => __('Team', 'agency-core'),
        'singular_name' => __('Team Member', 'agency-core'),
        'menu_name' => __('Team', 'agency-core'),
        'add_new' => __('Add New', 'agency-core'),
        'add_new_item' => __('Add New Team Member', 'agency-core'),
        'edit_item' => __('Edit Team Member', 'agency-core'),
        'new_item' => __('New Team Member', 'agency-core'),
        'view_item' => __('View Team Member', 'agency-core'),
        'search_items' => __('Search Team', 'agency-core'),
        'not_found' => __('No team members found', 'agency-core'),
        'not_found_in_trash' => __('No team members found in trash', 'agency-core'),
    );
    
    $args = array(
        'labels' => $labels,
        'public' => true,
        'has_archive' => true,
        'show_in_rest' => true,
        'supports' => array('title', 'editor', 'thumbnail', 'excerpt', 'page-attributes'),
        'menu_icon' => 'dashicons-groups',
        'menu_position' => 20,
        'rewrite' => array('slug' => 'team'),
        'capability_type' => 'post',
    );
    
    register_post_type('team', $args);
}
add_action('init', 'agency_core_register_team_cpt');

/**
 * Register Projects CPT
 */
function agency_core_register_projects_cpt() {
    $labels = array(
        'name' => __('Projects', 'agency-core'),
        'singular_name' => __('Project', 'agency-core'),
        'menu_name' => __('Projects', 'agency-core'),
        'add_new' => __('Add New', 'agency-core'),
        'add_new_item' => __('Add New Project', 'agency-core'),
        'edit_item' => __('Edit Project', 'agency-core'),
        'new_item' => __('New Project', 'agency-core'),
        'view_item' => __('View Project', 'agency-core'),
        'search_items' => __('Search Projects', 'agency-core'),
        'not_found' => __('No projects found', 'agency-core'),
        'not_found_in_trash' => __('No projects found in trash', 'agency-core'),
    );
    
    $args = array(
        'labels' => $labels,
        'public' => true,
        'has_archive' => true,
        'show_in_rest' => true,
        'supports' => array('title', 'editor', 'thumbnail', 'excerpt', 'page-attributes'),
        'menu_icon' => 'dashicons-portfolio',
        'menu_position' => 21,
        'rewrite' => array('slug' => 'projects'),
        'capability_type' => 'post',
        'taxonomies' => array('project_category'),
    );
    
    register_post_type('project', $args);
}
add_action('init', 'agency_core_register_projects_cpt');

/**
 * Register Project Categories
 */
function agency_core_register_project_categories() {
    $labels = array(
        'name' => __('Project Categories', 'agency-core'),
        'singular_name' => __('Project Category', 'agency-core'),
        'search_items' => __('Search Categories', 'agency-core'),
        'all_items' => __('All Categories', 'agency-core'),
        'parent_item' => __('Parent Category', 'agency-core'),
        'parent_item_colon' => __('Parent Category:', 'agency-core'),
        'edit_item' => __('Edit Category', 'agency-core'),
        'update_item' => __('Update Category', 'agency-core'),
        'add_new_item' => __('Add New Category', 'agency-core'),
        'new_item_name' => __('New Category Name', 'agency-core'),
        'menu_name' => __('Categories', 'agency-core'),
    );
    
    register_taxonomy('project_category', array('project'), array(
        'labels' => $labels,
        'hierarchical' => true,
        'show_in_rest' => true,
        'show_admin_column' => true,
        'rewrite' => array('slug' => 'projekt-kategorie'),
    ));
}
add_action('init', 'agency_core_register_project_categories');


/**
 * Register Testimonials CPT
 */
function agency_core_register_testimonials_cpt() {
    $labels = array(
        'name' => __('Testimonials', 'agency-core'),
        'singular_name' => __('Testimonial', 'agency-core'),
        'menu_name' => __('Testimonials', 'agency-core'),
        'add_new' => __('Add New', 'agency-core'),
        'add_new_item' => __('Add New Testimonial', 'agency-core'),
        'edit_item' => __('Edit Testimonial', 'agency-core'),
        'new_item' => __('New Testimonial', 'agency-core'),
        'view_item' => __('View Testimonial', 'agency-core'),
        'search_items' => __('Search Testimonials', 'agency-core'),
        'not_found' => __('No testimonials found', 'agency-core'),
        'not_found_in_trash' => __('No testimonials found in trash', 'agency-core'),
    );
    
    $args = array(
        'labels' => $labels,
        'public' => true,
        'has_archive' => true,
        'show_in_rest' => true,
        'supports' => array('title', 'editor', 'thumbnail', 'page-attributes'),
        'menu_icon' => 'dashicons-testimonial',
        'menu_position' => 22,
        'rewrite' => array('slug' => 'testimonials'),
        'capability_type' => 'post',
    );
    
    register_post_type('testimonial', $args);
}
add_action('init', 'agency_core_register_testimonials_cpt');

/**
 * Register Services CPT
 */
function agency_core_register_services_cpt() {
    $labels = array(
        'name' => __('Services', 'agency-core'),
        'singular_name' => __('Service', 'agency-core'),
        'menu_name' => __('Services', 'agency-core'),
        'add_new' => __('Add New', 'agency-core'),
        'add_new_item' => __('Add New Service', 'agency-core'),
        'edit_item' => __('Edit Service', 'agency-core'),
        'new_item' => __('New Service', 'agency-core'),
        'view_item' => __('View Service', 'agency-core'),
        'search_items' => __('Search Services', 'agency-core'),
        'not_found' => __('No services found', 'agency-core'),
        'not_found_in_trash' => __('No services found in trash', 'agency-core'),
    );
    
    $args = array(
        'labels' => $labels,
        'public' => true,
        'has_archive' => true,
        'show_in_rest' => true,
        'supports' => array('title', 'editor', 'thumbnail', 'excerpt', 'page-attributes'),
        'menu_icon' => 'dashicons-admin-tools',
        'menu_position' => 23,
        'rewrite' => array('slug' => 'leistungen'),
        'capability_type' => 'post',
    );
    
    register_post_type('service', $args);
}
add_action('init', 'agency_core_register_services_cpt');

/**
 * Register Google Maps Post Type
 */
function agency_core_register_maps_cpt() {
    $labels = array(
        'name'                  => _x('Maps', 'Post Type General Name', 'agency-core'),
        'singular_name'         => _x('Map', 'Post Type Singular Name', 'agency-core'),
        'menu_name'             => __('Google Maps', 'agency-core'),
        'name_admin_bar'        => __('Map', 'agency-core'),
        'all_items'             => __('All Maps', 'agency-core'),
        'add_new_item'          => __('Add New Map', 'agency-core'),
        'add_new'               => __('Add New', 'agency-core'),
        'new_item'              => __('New Map', 'agency-core'),
        'edit_item'             => __('Edit Map', 'agency-core'),
        'update_item'           => __('Update Map', 'agency-core'),
        'view_item'             => __('View Map', 'agency-core'),
        'search_items'          => __('Search Map', 'agency-core'),
        'not_found'             => __('Not found', 'agency-core'),
        'not_found_in_trash'    => __('Not found in Trash', 'agency-core'),
    );
    
    $args = array(
        'label'                 => __('Map', 'agency-core'),
        'description'           => __('Google Maps Locations', 'agency-core'),
        'labels'                => $labels,
        'supports'              => array('title'),
        'hierarchical'          => false,
        'public'                => false,
        'show_ui'               => true,
        'show_in_menu'          => true,
        'menu_position'         => 27,
        'menu_icon'             => 'dashicons-location-alt',
        'show_in_admin_bar'     => true,
        'show_in_nav_menus'     => false,
        'can_export'            => true,
        'has_archive'           => false,
        'exclude_from_search'   => true,
        'publicly_queryable'    => false,
        'capability_type'       => 'post',
        'show_in_rest'          => true,
    );
    
    register_post_type('gmap', $args);
}
add_action('init', 'agency_core_register_maps_cpt', 0);


// ========================================================================

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
            'name'               => 'Hero Slidesss',
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