<?php
/**
 * ACF Field Groups
 * 
 * Defined in PHP for version control
 * 
 * @package CustomTheme
 */

if (!defined('ABSPATH')) {
    exit;
}

// DEBUG
error_log('ACF Fields file loaded!');

if (!function_exists('acf_add_local_field_group')) {
    error_log('ERROR: ACF is not active!');
    return;
} else {
    error_log('ACF is active, loading fields...');
}

/**
 * Hero Slider Fields
 */
acf_add_local_field_group(array(
    'key' => 'group_hero_slider',
    'title' => 'Hero Slider Einstellungen',
    'fields' => array(
        array(
            'key' => 'field_slide_subtitle',
            'label' => 'Untertitel',
            'name' => 'slide_subtitle',
            'type' => 'text',
        ),
        array(
            'key' => 'field_slide_button_text',
            'label' => 'Button Text',
            'name' => 'slide_button_text',
            'type' => 'text',
            'default_value' => 'Mehr erfahren',
        ),
        array(
            'key' => 'field_slide_button_link',
            'label' => 'Button Link',
            'name' => 'slide_button_link',
            'type' => 'link',
        ),
    ),
    'location' => array(
        array(
            array(
                'param' => 'post_type',
                'operator' => '==',
                'value' => 'slide',
            ),
        ),
    ),
));

/**
 * Project Fields
 */
acf_add_local_field_group(array(
    'key' => 'group_project',
    'title' => 'Projekt Details',
    'fields' => array(
        array(
            'key' => 'field_project_url',
            'label' => 'Projekt URL',
            'name' => 'project_url',
            'type' => 'url',
        ),
        array(
            'key' => 'field_project_client',
            'label' => 'Kunde',
            'name' => 'project_client',
            'type' => 'text',
        ),
        array(
            'key' => 'field_project_year',
            'label' => 'Jahr',
            'name' => 'project_year',
            'type' => 'number',
            'default_value' => date('Y'),
        ),
        array(
            'key' => 'field_project_gallery',
            'label' => 'Projekt Galerie',
            'name' => 'project_gallery',
            'type' => 'gallery',
            'return_format' => 'array',
        ),
    ),
    'location' => array(
        array(
            array(
                'param' => 'post_type',
                'operator' => '==',
                'value' => 'project',
            ),
        ),
    ),
));

/**
 * Team Member Fields
 */
acf_add_local_field_group(array(
    'key' => 'group_team',
    'title' => 'Team Mitglied Details',
    'fields' => array(
        array(
            'key' => 'field_team_position',
            'label' => 'Position',
            'name' => 'team_position',
            'type' => 'text',
            'required' => true,
        ),
        array(
            'key' => 'field_team_email',
            'label' => 'E-Mail',
            'name' => 'team_email',
            'type' => 'email',
        ),
        array(
            'key' => 'field_team_phone',
            'label' => 'Telefon',
            'name' => 'team_phone',
            'type' => 'text',
        ),
        array(
            'key' => 'field_team_social',
            'label' => 'Social Media',
            'name' => 'team_social',
            'type' => 'repeater',
            'sub_fields' => array(
                array(
                    'key' => 'field_social_platform',
                    'label' => 'Plattform',
                    'name' => 'platform',
                    'type' => 'select',
                    'choices' => array(
                        'linkedin' => 'LinkedIn',
                        'twitter' => 'Twitter',
                        'github' => 'GitHub',
                        'instagram' => 'Instagram',
                    ),
                ),
                array(
                    'key' => 'field_social_url',
                    'label' => 'URL',
                    'name' => 'url',
                    'type' => 'url',
                ),
            ),
        ),
    ),
    'location' => array(
        array(
            array(
                'param' => 'post_type',
                'operator' => '==',
                'value' => 'team',
            ),
        ),
    ),
));

/**
 * Testimonial Fields
 */
acf_add_local_field_group(array(
    'key' => 'group_testimonial',
    'title' => 'Testimonial Details',
    'fields' => array(
        array(
            'key' => 'field_testimonial_rating',
            'label' => 'Bewertung',
            'name' => 'testimonial_rating',
            'type' => 'range',
            'min' => 1,
            'max' => 5,
            'step' => 1,
            'default_value' => 5,
        ),
        array(
            'key' => 'field_testimonial_company',
            'label' => 'Firma',
            'name' => 'testimonial_company',
            'type' => 'text',
        ),
        array(
            'key' => 'field_testimonial_position',
            'label' => 'Position',
            'name' => 'testimonial_position',
            'type' => 'text',
        ),
    ),
    'location' => array(
        array(
            array(
                'param' => 'post_type',
                'operator' => '==',
                'value' => 'testimonial',
            ),
        ),
    ),
));

/**
 * Service Fields
 */
acf_add_local_field_group(array(
    'key' => 'group_service',
    'title' => 'Leistungs Details',
    'fields' => array(
        array(
            'key' => 'field_service_icon',
            'label' => 'Icon',
            'name' => 'service_icon',
            'type' => 'image',
            'return_format' => 'array',
        ),
        array(
            'key' => 'field_service_features',
            'label' => 'Features',
            'name' => 'service_features',
            'type' => 'repeater',
            'sub_fields' => array(
                array(
                    'key' => 'field_feature_text',
                    'label' => 'Feature',
                    'name' => 'text',
                    'type' => 'text',
                ),
            ),
        ),
        array(
            'key' => 'field_service_price',
            'label' => 'Preis',
            'name' => 'service_price',
            'type' => 'text',
            'placeholder' => 'Ab 999€',
        ),
    ),
    'location' => array(
        array(
            array(
                'param' => 'post_type',
                'operator' => '==',
                'value' => 'service',
            ),
        ),
    ),
));

/**
 * FAQ Fields (Order)
 */
acf_add_local_field_group(array(
    'key' => 'group_faq',
    'title' => 'FAQ Einstellungen',
    'fields' => array(
        array(
            'key' => 'field_faq_order',
            'label' => 'Reihenfolge',
            'name' => 'faq_order',
            'type' => 'number',
            'default_value' => 10,
        ),
    ),
    'location' => array(
        array(
            array(
                'param' => 'post_type',
                'operator' => '==',
                'value' => 'faq',
            ),
        ),
    ),
));

/**
 * Page Builder Fields (Flexible Content)
 */
acf_add_local_field_group(array(
    'key' => 'group_page_builder',
    'title' => 'Page Builder',
    'fields' => array(
        array(
            'key' => 'field_page_sections',
            'label' => 'Sections',
            'name' => 'page_sections',
            'type' => 'flexible_content',
            'button_label' => 'Section hinzufügen',
            'layouts' => array(
                'layout_hero_slider' => array(
                    'key' => 'layout_hero_slider',
                    'name' => 'hero_slider',
                    'label' => 'Hero Slider',
                    'display' => 'block',
                    'sub_fields' => array(
                        array(
                            'key' => 'field_hero_slides',
                            'label' => 'Slides',
                            'name' => 'slides',
                            'type' => 'relationship',
                            'post_type' => array('slide'),
                            'return_format' => 'object',
                        ),
                    ),
                ),
                'layout_accordion' => array(
                    'key' => 'layout_accordion',
                    'name' => 'accordion',
                    'label' => 'Accordion (FAQ)',
                    'display' => 'block',
                    'sub_fields' => array(
                        array(
                            'key' => 'field_accordion_title',
                            'label' => 'Titel',
                            'name' => 'title',
                            'type' => 'text',
                        ),
                        array(
                            'key' => 'field_accordion_faqs',
                            'label' => 'FAQs',
                            'name' => 'faqs',
                            'type' => 'relationship',
                            'post_type' => array('faq'),
                            'return_format' => 'object',
                        ),
                    ),
                ),
                'layout_projects_grid' => array(
                    'key' => 'layout_projects',
                    'name' => 'projects_grid',
                    'label' => 'Projekte Grid',
                    'display' => 'block',
                    'sub_fields' => array(
                        array(
                            'key' => 'field_projects_title',
                            'label' => 'Titel',
                            'name' => 'title',
                            'type' => 'text',
                        ),
                        array(
                            'key' => 'field_projects_select',
                            'label' => 'Projekte',
                            'name' => 'projects',
                            'type' => 'relationship',
                            'post_type' => array('project'),
                            'return_format' => 'object',
                        ),
                    ),
                ),
                'layout_team' => array(
                    'key' => 'layout_team',
                    'name' => 'team',
                    'label' => 'Team Section',
                    'display' => 'block',
                    'sub_fields' => array(
                        array(
                            'key' => 'field_team_title',
                            'label' => 'Titel',
                            'name' => 'title',
                            'type' => 'text',
                        ),
                        array(
                            'key' => 'field_team_members',
                            'label' => 'Team Mitglieder',
                            'name' => 'members',
                            'type' => 'relationship',
                            'post_type' => array('team'),
                            'return_format' => 'object',
                        ),
                    ),
                ),
                'layout_testimonials' => array(
                    'key' => 'layout_testimonials',
                    'name' => 'testimonials',
                    'label' => 'Testimonials',
                    'display' => 'block',
                    'sub_fields' => array(
                        array(
                            'key' => 'field_testimonials_title',
                            'label' => 'Titel',
                            'name' => 'title',
                            'type' => 'text',
                        ),
                        array(
                            'key' => 'field_testimonials_select',
                            'label' => 'Testimonials',
                            'name' => 'testimonials',
                            'type' => 'relationship',
                            'post_type' => array('testimonial'),
                            'return_format' => 'object',
                        ),
                    ),
                ),
            ),
        ),
    ),
    'location' => array(
        array(
            array(
                'param' => 'post_type',
                'operator' => '==',
                'value' => 'page',
            ),
        ),
    ),
));