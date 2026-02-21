<?php
/**
 * Custom Taxonomies Registration
 * 
 * @package MediaLab_Project
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register Custom Taxonomies
 */
function medialab_project_register_taxonomies() {
    
    // Project Category
    register_taxonomy('project_category', 'project', array(
        'labels' => array(
            'name' => 'Projekt Kategorien',
            'singular_name' => 'Projekt Kategorie',
        ),
        'hierarchical' => true,
        'public' => true,
        'show_in_rest' => true,
        'show_admin_column' => true,
    ));
    
    // Service Category
    register_taxonomy('service_category', 'service', array(
        'labels' => array(
            'name' => 'Leistungs-Kategorien',
            'singular_name' => 'Leistungs-Kategorie',
        ),
        'hierarchical' => true,
        'public' => true,
        'show_in_rest' => true,
        'show_admin_column' => true,
    ));
    
    // FAQ Category
    register_taxonomy('faq_category', 'faq', array(
        'labels' => array(
            'name' => 'FAQ Kategorien',
            'singular_name' => 'FAQ Kategorie',
        ),
        'hierarchical' => true,
        'public' => false,
        'show_in_rest' => true,
        'show_admin_column' => true,
    ));
    
    // Carousel Category
    register_taxonomy('carousel_category', 'carousel', array(
        'labels' => array(
            'name' => 'Karussell Kategorien',
            'singular_name' => 'Karussell Kategorie',
        ),
        'hierarchical' => true,
        'public' => false,
        'show_in_rest' => true,
        'show_admin_column' => true,
    ));
    
    // Job Category
    register_taxonomy('job_category', 'job', array(
        'labels' => array(
            'name' => 'Job Categories',
            'singular_name' => 'Job Category',
        ),
        'hierarchical' => true,
        'public' => true,
        'show_in_rest' => true,
        'show_admin_column' => true,
    ));
    
    // Job Type
    register_taxonomy('job_type', 'job', array(
        'labels' => array(
            'name' => 'Job Types',
            'singular_name' => 'Job Type',
        ),
        'hierarchical' => true,
        'public' => true,
        'show_in_rest' => true,
        'show_admin_column' => true,
    ));
    
    // Job Location
    register_taxonomy('job_location', 'job', array(
        'labels' => array(
            'name' => 'Job Locations',
            'singular_name' => 'Job Location',
        ),
        'hierarchical' => true,
        'public' => true,
        'show_in_rest' => true,
        'show_admin_column' => true,
    ));
}
add_action('init', 'medialab_project_register_taxonomies');
