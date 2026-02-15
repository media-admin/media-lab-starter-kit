<?php
/**
 * ACF Field Groups
 * 
 * Define ACF field groups for custom post types.
 * 
 * @package Agency_Core
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register ACF Field Groups
 */
add_action('acf/include_field_types', 'agency_core_register_acf_fields');


function agency_core_register_acf_fields() {
    // Check if ACF is available
    if (!function_exists('acf_add_local_field_group')) {
        return;
    }
    
    // Team Member Fields
    agency_core_register_team_fields();
    
    // Project Fields
    agency_core_register_project_fields();
    
    // Testimonial Fields
    agency_core_register_testimonial_fields();
    
    // Service Fields
    agency_core_register_service_fields();

    // Hero Slides Fields
    agency_core_register_hero_slide_fields();

    // FAQ Fields
    agency_core_register_faq_fields();

    // Job Fields
    agency_core_register_job_fields();

    // Carousel Fields
    agency_core_register_carousel_fields();

    // Google Maps Fields
    agency_core_register_maps_fields();

    // WooCommerce Product Fields
    agency_core_register_product_fields();

    // Page Builder Fields
    agency_core_register_page_builder_fields();

    


}
add_action('acf/init', 'agency_core_register_acf_fields');



// Accordion Block Fields
if (function_exists('acf_add_local_field_group')) {
    
    acf_add_local_field_group(array(
        'key' => 'group_accordion_block',
        'title' => 'Accordion Settings',
        'fields' => array(
            array(
                'key' => 'field_accordion_items',
                'label' => 'Accordion Items',
                'name' => 'accordion_items',
                'type' => 'repeater',
                'layout' => 'block',
                'button_label' => '+ Item hinzufügen',
                'min' => 1,
                'sub_fields' => array(
                    array(
                        'key' => 'field_accordion_title',
                        'label' => 'Titel',
                        'name' => 'title',
                        'type' => 'text',
                        'required' => 1,
                        'placeholder' => 'z.B. Wie funktioniert das?',
                    ),
                    array(
                        'key' => 'field_accordion_content',
                        'label' => 'Inhalt',
                        'name' => 'content',
                        'type' => 'wysiwyg',
                        'tabs' => 'all',
                        'toolbar' => 'full',
                        'media_upload' => 1,
                        'required' => 1,
                    ),
                ),
            ),
            array(
                'key' => 'field_allow_multiple_open',
                'label' => 'Mehrere Items gleichzeitig öffnen?',
                'name' => 'allow_multiple_open',
                'type' => 'true_false',
                'default_value' => 0,
                'ui' => 1,
                'instructions' => 'Wenn aktiviert, können mehrere Accordion-Items gleichzeitig geöffnet sein.',
            ),
            array(
                'key' => 'field_first_item_open',
                'label' => 'Erstes Item standardmäßig offen?',
                'name' => 'first_item_open',
                'type' => 'true_false',
                'default_value' => 0,
                'ui' => 1,
            ),
        ),
        'location' => array(
            array(
                array(
                    'param' => 'block',
                    'operator' => '==',
                    'value' => 'acf/accordion',
                ),
            ),
        ),
    ));
    
    // Hero Slider Block Fields
    acf_add_local_field_group(array(
        'key' => 'group_hero_slider_block',
        'title' => 'Hero Slider Settings',
        'fields' => array(
            array(
                'key' => 'field_hero_slides',
                'label' => 'Slides',
                'name' => 'hero_slides',
                'type' => 'repeater',
                'layout' => 'block',
                'button_label' => '+ Slide hinzufügen',
                'min' => 1,
                'sub_fields' => array(
                    array(
                        'key' => 'field_slide_image',
                        'label' => 'Hintergrundbild',
                        'name' => 'image',
                        'type' => 'image',
                        'return_format' => 'array',
                        'preview_size' => 'medium',
                        'required' => 1,
                    ),
                    array(
                        'key' => 'field_slide_title',
                        'label' => 'Titel',
                        'name' => 'title',
                        'type' => 'text',
                        'required' => 1,
                    ),
                    array(
                        'key' => 'field_slide_subtitle',
                        'label' => 'Untertitel',
                        'name' => 'subtitle',
                        'type' => 'textarea',
                        'rows' => 3,
                    ),
                    array(
                        'key' => 'field_slide_button_text',
                        'label' => 'Button Text',
                        'name' => 'button_text',
                        'type' => 'text',
                    ),
                    array(
                        'key' => 'field_slide_button_link',
                        'label' => 'Button Link',
                        'name' => 'button_link',
                        'type' => 'link',
                        'return_format' => 'array',
                    ),
                ),
            ),
            array(
                'key' => 'field_slider_autoplay',
                'label' => 'Autoplay aktivieren?',
                'name' => 'autoplay',
                'type' => 'true_false',
                'default_value' => 1,
                'ui' => 1,
            ),
            array(
                'key' => 'field_slider_delay',
                'label' => 'Autoplay Verzögerung (ms)',
                'name' => 'delay',
                'type' => 'number',
                'default_value' => 5000,
                'min' => 1000,
                'step' => 1000,
                'conditional_logic' => array(
                    array(
                        array(
                            'field' => 'field_slider_autoplay',
                            'operator' => '==',
                            'value' => '1',
                        ),
                    ),
                ),
            ),
        ),
        'location' => array(
            array(
                array(
                    'param' => 'block',
                    'operator' => '==',
                    'value' => 'acf/hero-slider',
                ),
            ),
        ),
    ));
}



/**
 * Team Member Fields
 */
function agency_core_register_team_fields() {
    acf_add_local_field_group(array(
        'key' => 'group_team_member',
        'title' => 'Team Member Details',
        'fields' => array(
            array(
                'key' => 'field_team_role',
                'label' => 'Role / Position',
                'name' => 'role',
                'type' => 'text',
                'required' => 1,
                'placeholder' => 'e.g., CEO, Developer, Designer',
            ),
            array(
                'key' => 'field_team_email',
                'label' => 'Email',
                'name' => 'email',
                'type' => 'email',
                'placeholder' => 'email@example.com',
            ),
            array(
                'key' => 'field_team_phone',
                'label' => 'Phone',
                'name' => 'phone',
                'type' => 'text',
                'placeholder' => '+43 XXX XXX XXX',
            ),
            array(
                'key' => 'field_team_social',
                'label' => 'Social Media',
                'name' => 'social_media',
                'type' => 'group',
                'layout' => 'block',
                'sub_fields' => array(
                    array(
                        'key' => 'field_team_linkedin',
                        'label' => 'LinkedIn',
                        'name' => 'linkedin',
                        'type' => 'url',
                        'placeholder' => 'https://linkedin.com/in/username',
                    ),
                    array(
                        'key' => 'field_team_twitter',
                        'label' => 'Twitter',
                        'name' => 'twitter',
                        'type' => 'url',
                        'placeholder' => 'https://twitter.com/username',
                    ),
                    array(
                        'key' => 'field_team_facebook',
                        'label' => 'Facebook',
                        'name' => 'facebook',
                        'type' => 'url',
                        'placeholder' => 'https://facebook.com/username',
                    ),
                    array(
                        'key' => 'field_team_instagram',
                        'label' => 'Instagram',
                        'name' => 'instagram',
                        'type' => 'url',
                        'placeholder' => 'https://instagram.com/username',
                    ),
                ),
            ),
            array(
                'key' => 'field_team_display_order',
                'label' => 'Display Order',
                'name' => 'display_order',
                'type' => 'number',
                'default_value' => 0,
                'min' => 0,
                'step' => 1,
                'instructions' => 'Lower numbers appear first (0 = first)',
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
        'menu_order' => 0,
        'position' => 'normal',
        'style' => 'default',
        'label_placement' => 'top',
        'instruction_placement' => 'label',
    ));
}

/**
 * Project Fields
 */
function agency_core_register_project_fields() {
    acf_add_local_field_group(array(
        'key' => 'group_project',
        'title' => 'Project Details',
        'fields' => array(
            array(
                'key' => 'field_project_client',
                'label' => 'Client',
                'name' => 'client',
                'type' => 'text',
                'placeholder' => 'Client name',
            ),
            array(
                'key' => 'field_project_date',
                'label' => 'Project Date',
                'name' => 'project_date',
                'type' => 'date_picker',
                'display_format' => 'd/m/Y',
                'return_format' => 'd/m/Y',
                'first_day' => 1,
            ),
            array(
                'key' => 'field_project_url',
                'label' => 'Project URL',
                'name' => 'project_url',
                'type' => 'url',
                'placeholder' => 'https://example.com',
            ),
            array(
                'key' => 'field_project_gallery',
                'label' => 'Gallery',
                'name' => 'gallery',
                'type' => 'gallery',
                'return_format' => 'array',
                'preview_size' => 'medium',
                'library' => 'all',
            ),
            array(
                'key' => 'field_project_technologies',
                'label' => 'Technologies Used',
                'name' => 'technologies',
                'type' => 'textarea',
                'rows' => 3,
                'placeholder' => 'e.g., WordPress, React, PHP',
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
        'menu_order' => 0,
        'position' => 'normal',
        'style' => 'default',
    ));
}

/**
 * Testimonial Fields
 */
function agency_core_register_testimonial_fields() {
    acf_add_local_field_group(array(
        'key' => 'group_testimonial',
        'title' => 'Testimonial Details',
        'fields' => array(
            array(
                'key' => 'field_testimonial_author',
                'label' => 'Author Name',
                'name' => 'author_name',
                'type' => 'text',
                'required' => 1,
                'placeholder' => 'John Doe',
            ),
            array(
                'key' => 'field_testimonial_company',
                'label' => 'Company',
                'name' => 'company',
                'type' => 'text',
                'placeholder' => 'Company Name',
            ),
            array(
                'key' => 'field_testimonial_position',
                'label' => 'Position',
                'name' => 'position',
                'type' => 'text',
                'placeholder' => 'CEO, Founder, etc.',
            ),
            array(
                'key' => 'field_testimonial_rating',
                'label' => 'Rating',
                'name' => 'rating',
                'type' => 'number',
                'min' => 1,
                'max' => 5,
                'step' => 1,
                'default_value' => 5,
            ),
            array(
                'key' => 'field_testimonial_image',
                'label' => 'Author Image',
                'name' => 'author_image',
                'type' => 'image',
                'return_format' => 'url',
                'preview_size' => 'thumbnail',
                'library' => 'all',
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
        'menu_order' => 0,
        'position' => 'normal',
        'style' => 'default',
    ));
}

/**
 * Service Fields
 */
function agency_core_register_service_fields() {
    acf_add_local_field_group(array(
        'key' => 'group_service',
        'title' => 'Service Details',
        'fields' => array(
            array(
                'key' => 'field_service_icon',
                'label' => 'Icon Class',
                'name' => 'icon',
                'type' => 'text',
                'instructions' => 'Dashicon class (e.g., dashicons-admin-tools) or Font Awesome (e.g., fa-rocket)',
                'placeholder' => 'dashicons-admin-tools',
            ),
            array(
                'key' => 'field_service_price',
                'label' => 'Starting Price',
                'name' => 'price',
                'type' => 'text',
                'instructions' => 'e.g., "ab 500€" or "On Request"',
                'placeholder' => 'ab 500€',
            ),
            array(
                'key' => 'field_service_features',
                'label' => 'Key Features',
                'name' => 'features',
                'type' => 'repeater',
                'button_label' => 'Add Feature',
                'layout' => 'table',
                'sub_fields' => array(
                    array(
                        'key' => 'field_service_feature_text',
                        'label' => 'Feature',
                        'name' => 'feature_text',
                        'type' => 'text',
                        'placeholder' => 'Feature description',
                    ),
                ),
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
        'menu_order' => 0,
        'position' => 'normal',
        'style' => 'default',
    ));
}


/**
 * Product Additional Fields
 */
function agency_core_register_product_fields() {
    acf_add_local_field_group(array(
        'key' => 'group_product_additional',
        'title' => 'Additional Product Information',
        'fields' => array(
            array(
                'key' => 'field_product_highlights',
                'label' => 'Product Highlights',
                'name' => 'product_highlights',
                'type' => 'repeater',
                'button_label' => 'Add Highlight',
                'layout' => 'table',
                'sub_fields' => array(
                    array(
                        'key' => 'field_highlight_text',
                        'label' => 'Highlight',
                        'name' => 'highlight_text',
                        'type' => 'text',
                    ),
                    array(
                        'key' => 'field_highlight_icon',
                        'label' => 'Icon (optional)',
                        'name' => 'highlight_icon',
                        'type' => 'text',
                        'placeholder' => 'dashicons-yes',
                    ),
                ),
            ),
            array(
                'key' => 'field_product_specifications',
                'label' => 'Specifications',
                'name' => 'specifications',
                'type' => 'repeater',
                'button_label' => 'Add Specification',
                'layout' => 'table',
                'sub_fields' => array(
                    array(
                        'key' => 'field_spec_label',
                        'label' => 'Label',
                        'name' => 'spec_label',
                        'type' => 'text',
                        'placeholder' => 'Größe, Gewicht, Material...',
                    ),
                    array(
                        'key' => 'field_spec_value',
                        'label' => 'Value',
                        'name' => 'spec_value',
                        'type' => 'text',
                    ),
                ),
            ),
            array(
                'key' => 'field_product_video',
                'label' => 'Product Video URL',
                'name' => 'product_video',
                'type' => 'url',
                'placeholder' => 'YouTube oder Vimeo URL',
            ),
            array(
                'key' => 'field_product_badge',
                'label' => 'Custom Badge',
                'name' => 'product_badge',
                'type' => 'select',
                'choices' => array(
                    '' => 'None',
                    'new' => 'Neu',
                    'bestseller' => 'Bestseller',
                    'limited' => 'Limitiert',
                    'eco' => 'Umweltfreundlich',
                ),
                'allow_null' => 1,
            ),
        ),
        'location' => array(
            array(
                array(
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => 'product',
                ),
            ),
        ),
        'menu_order' => 0,
        'position' => 'normal',
        'style' => 'default',
    ));
}


/**
 * Hero Slide Fields
 */
function agency_core_register_hero_slide_fields() {
    acf_add_local_field_group(array(
        'key' => 'group_hero_slide',
        'title' => 'Hero Slide Details',
        'fields' => array(
            array(
                'key' => 'field_hero_subtitle',
                'label' => 'Subtitle',
                'name' => 'subtitle',
                'type' => 'text',
                'placeholder' => 'Untertitel oder Tagline',
            ),
            array(
                'key' => 'field_hero_button_text',
                'label' => 'Button Text',
                'name' => 'button_text',
                'type' => 'text',
                'placeholder' => 'z.B. Mehr erfahren',
            ),
            array(
                'key' => 'field_hero_button_url',
                'label' => 'Button URL',
                'name' => 'button_url',
                'type' => 'url',
                'placeholder' => 'https://example.com',
            ),
            array(
                'key' => 'field_hero_button_style',
                'label' => 'Button Style',
                'name' => 'button_style',
                'type' => 'select',
                'choices' => array(
                    'primary' => 'Primary',
                    'secondary' => 'Secondary',
                    'outline' => 'Outline',
                ),
                'default_value' => 'primary',
            ),
            array(
                'key' => 'field_hero_image_desktop',
                'label' => 'Desktop Image',
                'name' => 'image_desktop',
                'type' => 'image',
                'return_format' => 'url',
                'preview_size' => 'medium',
                'library' => 'all',
                'instructions' => 'Empfohlene Größe: 1920x1080px',
            ),
            array(
                'key' => 'field_hero_image_mobile',
                'label' => 'Mobile Image',
                'name' => 'image_mobile',
                'type' => 'image',
                'return_format' => 'url',
                'preview_size' => 'medium',
                'library' => 'all',
                'instructions' => 'Empfohlene Größe: 800x600px',
            ),
            array(
                'key' => 'field_hero_overlay_opacity',
                'label' => 'Overlay Opacity',
                'name' => 'overlay_opacity',
                'type' => 'range',
                'min' => 0,
                'max' => 100,
                'step' => 5,
                'default_value' => 30,
                'append' => '%',
            ),
            array(
                'key' => 'field_hero_text_color',
                'label' => 'Text Color',
                'name' => 'text_color',
                'type' => 'select',
                'choices' => array(
                    'light' => 'Light (White)',
                    'dark' => 'Dark (Black)',
                ),
                'default_value' => 'light',
            ),
        ),
        'location' => array(
            array(
                array(
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => 'hero_slide',
                ),
            ),
        ),
        'menu_order' => 0,
        'position' => 'normal',
        'style' => 'default',
    ));
}

/**
 * FAQ Fields
 */
function agency_core_register_faq_fields() {
    acf_add_local_field_group(array(
        'key' => 'group_faq',
        'title' => 'FAQ Details',
        'fields' => array(
            array(
                'key' => 'field_faq_answer',
                'label' => 'Answer',
                'name' => 'answer',
                'type' => 'wysiwyg',
                'required' => 1,
                'tabs' => 'all',
                'toolbar' => 'basic',
                'media_upload' => 0,
                'instructions' => 'The question is the post title, this is the answer.',
            ),
            array(
                'key' => 'field_faq_order',
                'label' => 'Display Order',
                'name' => 'display_order',
                'type' => 'number',
                'default_value' => 0,
                'min' => 0,
                'step' => 1,
                'instructions' => 'Lower numbers appear first (0 = first)',
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
        'menu_order' => 0,
        'position' => 'normal',
        'style' => 'default',
    ));
}

/**
 * Carousel Fields
 */
function agency_core_register_carousel_fields() {
    acf_add_local_field_group(array(
        'key' => 'group_carousel',
        'title' => 'Carousel Item Details',
        'fields' => array(
            array(
                'key' => 'field_carousel_subtitle',
                'label' => 'Subtitle',
                'name' => 'subtitle',
                'type' => 'text',
                'placeholder' => 'Optional subtitle or tagline',
            ),
            array(
                'key' => 'field_carousel_link',
                'label' => 'Link URL',
                'name' => 'link_url',
                'type' => 'url',
                'placeholder' => 'https://example.com',
            ),
            array(
                'key' => 'field_carousel_link_target',
                'label' => 'Open Link In',
                'name' => 'link_target',
                'type' => 'select',
                'choices' => array(
                    '_self' => 'Same Window',
                    '_blank' => 'New Window',
                ),
                'default_value' => '_self',
            ),
            array(
                'key' => 'field_carousel_image',
                'label' => 'Image',
                'name' => 'image',
                'type' => 'image',
                'return_format' => 'array',
                'preview_size' => 'medium',
                'library' => 'all',
                'instructions' => 'Recommended size: 800x600px',
            ),
            array(
                'key' => 'field_carousel_overlay',
                'label' => 'Show Text Overlay',
                'name' => 'show_overlay',
                'type' => 'true_false',
                'default_value' => 1,
                'ui' => 1,
            ),
            array(
                'key' => 'field_carousel_display_order',
                'label' => 'Display Order',
                'name' => 'display_order',
                'type' => 'number',
                'default_value' => 0,
                'min' => 0,
                'step' => 1,
                'instructions' => 'Lower numbers appear first',
            ),
        ),
        'location' => array(
            array(
                array(
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => 'carousel',
                ),
            ),
        ),
        'menu_order' => 0,
        'position' => 'normal',
        'style' => 'default',
    ));
}

/**
 * Google Maps Fields
 */
function agency_core_register_maps_fields() {
    acf_add_local_field_group(array(
        'key' => 'group_gmap',
        'title' => 'Map Details',
        'fields' => array(
            array(
                'key' => 'field_gmap_address',
                'label' => 'Adresse',
                'name' => 'address',
                'type' => 'text',
                'required' => 1,
                'placeholder' => 'Musterstraße 123, 1010 Wien',
                'instructions' => 'Vollständige Adresse für die Karte',
            ),
            array(
                'key' => 'field_gmap_lat',
                'label' => 'Latitude (Breitengrad)',
                'name' => 'latitude',
                'type' => 'text',
                'required' => 1,
                'placeholder' => '48.2082',
                'instructions' => 'z.B. 48.2082 (Google Maps → Rechtsklick → Koordinaten)',
            ),
            array(
                'key' => 'field_gmap_lng',
                'label' => 'Longitude (Längengrad)',
                'name' => 'longitude',
                'type' => 'text',
                'required' => 1,
                'placeholder' => '16.3738',
                'instructions' => 'z.B. 16.3738',
            ),
            array(
                'key' => 'field_gmap_zoom',
                'label' => 'Zoom Level',
                'name' => 'zoom',
                'type' => 'number',
                'default_value' => 15,
                'min' => 1,
                'max' => 20,
                'step' => 1,
                'instructions' => '1 = Welt, 15 = Stadt, 20 = Gebäude',
            ),
            array(
                'key' => 'field_gmap_marker_title',
                'label' => 'Marker Titel',
                'name' => 'marker_title',
                'type' => 'text',
                'placeholder' => 'Unser Büro',
            ),
            array(
                'key' => 'field_gmap_marker_description',
                'label' => 'Marker Beschreibung',
                'name' => 'marker_description',
                'type' => 'textarea',
                'rows' => 3,
                'placeholder' => 'Zusätzliche Infos für den Marker',
            ),
            array(
                'key' => 'field_gmap_style',
                'label' => 'Map Style',
                'name' => 'map_style',
                'type' => 'select',
                'choices' => array(
                    'default' => 'Standard',
                    'silver' => 'Silber',
                    'dark' => 'Dark Mode',
                    'retro' => 'Retro',
                ),
                'default_value' => 'default',
            ),
        ),
        'location' => array(
            array(
                array(
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => 'gmap',
                ),
            ),
        ),
        'menu_order' => 0,
        'position' => 'normal',
        'style' => 'default',
    ));
}








/**
 * Page Builder Flexible Content Fields
 */
function agency_core_register_page_builder_fields() {
    acf_add_local_field_group(array(
        'key' => 'group_page_builder',
        'title' => 'Page Builder',
        'fields' => array(
            array(
                'key' => 'field_content_builder',
                'label' => 'Content Builder',
                'name' => 'content_builder',
                'type' => 'flexible_content',
                'instructions' => 'Baue deine Seite mit flexiblen Content-Blöcken',
                'button_label' => 'Block hinzufügen',
                'layouts' => array(
                    
                    // ============================================
                    // HERO SECTION
                    // ============================================
                    'hero' => array(
                        'key' => 'layout_hero',
                        'name' => 'hero',
                        'label' => 'Hero Section',
                        'display' => 'block',
                        'sub_fields' => array(
                            array(
                                'key' => 'field_hero_title',
                                'label' => 'Title',
                                'name' => 'title',
                                'type' => 'text',
                            ),
                            array(
                                'key' => 'field_hero_subtitle',
                                'label' => 'Subtitle',
                                'name' => 'subtitle',
                                'type' => 'text',
                            ),
                            array(
                                'key' => 'field_hero_content',
                                'label' => 'Content',
                                'name' => 'content',
                                'type' => 'textarea',
                                'rows' => 3,
                            ),
                            array(
                                'key' => 'field_hero_button_text',
                                'label' => 'Button Text',
                                'name' => 'button_text',
                                'type' => 'text',
                            ),
                            array(
                                'key' => 'field_hero_button_url',
                                'label' => 'Button URL',
                                'name' => 'button_url',
                                'type' => 'url',
                            ),
                            array(
                                'key' => 'field_hero_background_image',
                                'label' => 'Background Image',
                                'name' => 'background_image',
                                'type' => 'image',
                                'return_format' => 'array',
                                'preview_size' => 'medium',
                            ),
                        ),
                    ),
                    
                    // ============================================
                    // TEXT SECTION
                    // ============================================
                    'text' => array(
                        'key' => 'layout_text',
                        'name' => 'text',
                        'label' => 'Text Section',
                        'display' => 'block',
                        'sub_fields' => array(
                            array(
                                'key' => 'field_text_title',
                                'label' => 'Title',
                                'name' => 'title',
                                'type' => 'text',
                            ),
                            array(
                                'key' => 'field_text_content',
                                'label' => 'Content',
                                'name' => 'content',
                                'type' => 'wysiwyg',
                                'tabs' => 'all',
                                'toolbar' => 'full',
                                'media_upload' => 1,
                            ),
                            array(
                                'key' => 'field_text_width',
                                'label' => 'Width',
                                'name' => 'width',
                                'type' => 'select',
                                'choices' => array(
                                    'narrow' => 'Narrow (800px)',
                                    'normal' => 'Normal (1200px)',
                                    'wide' => 'Wide (1400px)',
                                    'full' => 'Full Width',
                                ),
                                'default_value' => 'normal',
                            ),
                        ),
                    ),
                    
                    // ============================================
                    // TWO COLUMNS
                    // ============================================
                    'two_columns' => array(
                        'key' => 'layout_two_columns',
                        'name' => 'two_columns',
                        'label' => 'Two Columns',
                        'display' => 'block',
                        'sub_fields' => array(
                            array(
                                'key' => 'field_col_left',
                                'label' => 'Left Column',
                                'name' => 'left_column',
                                'type' => 'wysiwyg',
                            ),
                            array(
                                'key' => 'field_col_right',
                                'label' => 'Right Column',
                                'name' => 'right_column',
                                'type' => 'wysiwyg',
                            ),
                            array(
                                'key' => 'field_col_ratio',
                                'label' => 'Column Ratio',
                                'name' => 'ratio',
                                'type' => 'select',
                                'choices' => array(
                                    '50-50' => '50% / 50%',
                                    '60-40' => '60% / 40%',
                                    '40-60' => '40% / 60%',
                                    '70-30' => '70% / 30%',
                                    '30-70' => '30% / 70%',
                                ),
                                'default_value' => '50-50',
                            ),
                        ),
                    ),
                    
                    // ============================================
                    // IMAGE + TEXT
                    // ============================================
                    'image_text' => array(
                        'key' => 'layout_image_text',
                        'name' => 'image_text',
                        'label' => 'Image + Text',
                        'display' => 'block',
                        'sub_fields' => array(
                            array(
                                'key' => 'field_it_image',
                                'label' => 'Image',
                                'name' => 'image',
                                'type' => 'image',
                                'return_format' => 'array',
                                'preview_size' => 'medium',
                            ),
                            array(
                                'key' => 'field_it_title',
                                'label' => 'Title',
                                'name' => 'title',
                                'type' => 'text',
                            ),
                            array(
                                'key' => 'field_it_content',
                                'label' => 'Content',
                                'name' => 'content',
                                'type' => 'wysiwyg',
                            ),
                            array(
                                'key' => 'field_it_position',
                                'label' => 'Image Position',
                                'name' => 'image_position',
                                'type' => 'select',
                                'choices' => array(
                                    'left' => 'Image Left',
                                    'right' => 'Image Right',
                                ),
                                'default_value' => 'left',
                            ),
                        ),
                    ),
                    
                    // ============================================
                    // SHORTCODE BLOCK
                    // ============================================
                    'shortcode' => array(
                        'key' => 'layout_shortcode',
                        'name' => 'shortcode',
                        'label' => 'Shortcode',
                        'display' => 'block',
                        'sub_fields' => array(
                            array(
                                'key' => 'field_shortcode_content',
                                'label' => 'Shortcode',
                                'name' => 'shortcode',
                                'type' => 'textarea',
                                'instructions' => 'Füge einen Shortcode ein, z.B. [team_query] oder [pricing_tables]',
                                'rows' => 3,
                            ),
                        ),
                    ),
                    
                    // ============================================
                    // CTA SECTION
                    // ============================================
                    'cta' => array(
                        'key' => 'layout_cta',
                        'name' => 'cta',
                        'label' => 'Call to Action',
                        'display' => 'block',
                        'sub_fields' => array(
                            array(
                                'key' => 'field_cta_title',
                                'label' => 'Title',
                                'name' => 'title',
                                'type' => 'text',
                            ),
                            array(
                                'key' => 'field_cta_text',
                                'label' => 'Text',
                                'name' => 'text',
                                'type' => 'textarea',
                                'rows' => 3,
                            ),
                            array(
                                'key' => 'field_cta_button_text',
                                'label' => 'Button Text',
                                'name' => 'button_text',
                                'type' => 'text',
                            ),
                            array(
                                'key' => 'field_cta_button_url',
                                'label' => 'Button URL',
                                'name' => 'button_url',
                                'type' => 'url',
                            ),
                            array(
                                'key' => 'field_cta_background',
                                'label' => 'Background Color',
                                'name' => 'background',
                                'type' => 'select',
                                'choices' => array(
                                    'primary' => 'Primary Color',
                                    'secondary' => 'Secondary Color',
                                    'gray' => 'Gray',
                                ),
                                'default_value' => 'primary',
                            ),
                        ),
                    ),
                    
                ),
            ),
        ),
        'location' => array(
            array(
                array(
                    'param' => 'page_template',
                    'operator' => '==',
                    'value' => 'page-templates/page-builder-acf.php',
                ),
            ),
        ),
        'menu_order' => 0,
        'position' => 'normal',
        'style' => 'default',
        'label_placement' => 'top',
        'instruction_placement' => 'label',
    ));
}



// Accordion Block Fields
if (function_exists('acf_add_local_field_group')) {
    
    acf_add_local_field_group(array(
        'key' => 'group_accordion_block',
        'title' => 'Accordion Settings',
        'fields' => array(
            array(
                'key' => 'field_accordion_items',
                'label' => 'Accordion Items',
                'name' => 'accordion_items',
                'type' => 'repeater',
                'layout' => 'block',
                'button_label' => '+ Item hinzufügen',
                'min' => 1,
                'sub_fields' => array(
                    array(
                        'key' => 'field_accordion_title',
                        'label' => 'Titel',
                        'name' => 'title',
                        'type' => 'text',
                        'required' => 1,
                        'placeholder' => 'z.B. Wie funktioniert das?',
                    ),
                    array(
                        'key' => 'field_accordion_content',
                        'label' => 'Inhalt',
                        'name' => 'content',
                        'type' => 'wysiwyg',
                        'tabs' => 'all',
                        'toolbar' => 'full',
                        'media_upload' => 1,
                        'required' => 1,
                    ),
                ),
            ),
            array(
                'key' => 'field_allow_multiple_open',
                'label' => 'Mehrere Items gleichzeitig öffnen?',
                'name' => 'allow_multiple_open',
                'type' => 'true_false',
                'default_value' => 0,
                'ui' => 1,
                'instructions' => 'Wenn aktiviert, können mehrere Accordion-Items gleichzeitig geöffnet sein.',
            ),
            array(
                'key' => 'field_first_item_open',
                'label' => 'Erstes Item standardmäßig offen?',
                'name' => 'first_item_open',
                'type' => 'true_false',
                'default_value' => 0,
                'ui' => 1,
            ),
        ),
        'location' => array(
            array(
                array(
                    'param' => 'block',
                    'operator' => '==',
                    'value' => 'acf/accordion',
                ),
            ),
        ),
    ));
    
    // Hero Slider Block Fields
    acf_add_local_field_group(array(
        'key' => 'group_hero_slider_block',
        'title' => 'Hero Slider Settings',
        'fields' => array(
            array(
                'key' => 'field_hero_slides',
                'label' => 'Slides',
                'name' => 'hero_slides',
                'type' => 'repeater',
                'layout' => 'block',
                'button_label' => '+ Slide hinzufügen',
                'min' => 1,
                'sub_fields' => array(
                    array(
                        'key' => 'field_slide_image',
                        'label' => 'Hintergrundbild',
                        'name' => 'image',
                        'type' => 'image',
                        'return_format' => 'array',
                        'preview_size' => 'medium',
                        'required' => 1,
                    ),
                    array(
                        'key' => 'field_slide_title',
                        'label' => 'Titel',
                        'name' => 'title',
                        'type' => 'text',
                        'required' => 1,
                    ),
                    array(
                        'key' => 'field_slide_subtitle',
                        'label' => 'Untertitel',
                        'name' => 'subtitle',
                        'type' => 'textarea',
                        'rows' => 3,
                    ),
                    array(
                        'key' => 'field_slide_button_text',
                        'label' => 'Button Text',
                        'name' => 'button_text',
                        'type' => 'text',
                    ),
                    array(
                        'key' => 'field_slide_button_link',
                        'label' => 'Button Link',
                        'name' => 'button_link',
                        'type' => 'link',
                        'return_format' => 'array',
                    ),
                ),
            ),
            array(
                'key' => 'field_slider_autoplay',
                'label' => 'Autoplay aktivieren?',
                'name' => 'autoplay',
                'type' => 'true_false',
                'default_value' => 1,
                'ui' => 1,
            ),
            array(
                'key' => 'field_slider_delay',
                'label' => 'Autoplay Verzögerung (ms)',
                'name' => 'delay',
                'type' => 'number',
                'default_value' => 5000,
                'min' => 1000,
                'step' => 1000,
                'conditional_logic' => array(
                    array(
                        array(
                            'field' => 'field_slider_autoplay',
                            'operator' => '==',
                            'value' => '1',
                        ),
                    ),
                ),
            ),
        ),
        'location' => array(
            array(
                array(
                    'param' => 'block',
                    'operator' => '==',
                    'value' => 'acf/hero-slider',
                ),
            ),
        ),
    ));
}

/**
 * Job Fields
 */
function agency_core_register_job_fields() {
    acf_add_local_field_group(array(
        'key' => 'group_job',
        'title' => 'Job Details',
        'fields' => array(
            
            // ============================================
            // BASIC INFO
            // ============================================
            array(
                'key' => 'field_job_subtitle',
                'label' => 'Subtitle / Tagline',
                'name' => 'subtitle',
                'type' => 'text',
                'placeholder' => 'z.B. "Join our team"',
                'instructions' => 'Optional: Kurzer Untertitel für die Job-Anzeige',
            ),
            
            array(
                'key' => 'field_job_employment_type',
                'label' => 'Employment Type',
                'name' => 'employment_type',
                'type' => 'select',
                'required' => 1,
                'choices' => array(
                    'full-time' => 'Full-Time (Vollzeit)',
                    'part-time' => 'Part-Time (Teilzeit)',
                    'contract' => 'Contract (Vertrag)',
                    'temporary' => 'Temporary (Befristet)',
                    'internship' => 'Internship (Praktikum)',
                    'freelance' => 'Freelance',
                ),
                'default_value' => 'full-time',
            ),
            
            array(
                'key' => 'field_job_experience_level',
                'label' => 'Experience Level',
                'name' => 'experience_level',
                'type' => 'select',
                'choices' => array(
                    'entry' => 'Entry Level (Berufseinsteiger)',
                    'junior' => 'Junior (1-3 Jahre)',
                    'mid' => 'Mid-Level (3-5 Jahre)',
                    'senior' => 'Senior (5+ Jahre)',
                    'lead' => 'Lead/Manager',
                ),
                'default_value' => 'mid',
            ),
            
            array(
                'key' => 'field_job_remote',
                'label' => 'Remote Work',
                'name' => 'remote_work',
                'type' => 'select',
                'choices' => array(
                    'office' => 'Office Only (Nur vor Ort)',
                    'hybrid' => 'Hybrid (Teilweise Remote)',
                    'remote' => 'Fully Remote (Vollständig Remote)',
                ),
                'default_value' => 'hybrid',
            ),
            
            // ============================================
            // SALARY & BENEFITS
            // ============================================
            array(
                'key' => 'field_job_salary_min',
                'label' => 'Salary Min (€)',
                'name' => 'salary_min',
                'type' => 'number',
                'placeholder' => '40000',
                'instructions' => 'Mindestgehalt pro Jahr in Euro (leer lassen für "Nach Vereinbarung")',
            ),
            
            array(
                'key' => 'field_job_salary_max',
                'label' => 'Salary Max (€)',
                'name' => 'salary_max',
                'type' => 'number',
                'placeholder' => '60000',
                'instructions' => 'Maximalgehalt pro Jahr in Euro',
            ),
            
            array(
                'key' => 'field_job_salary_display',
                'label' => 'Display Salary',
                'name' => 'salary_display',
                'type' => 'true_false',
                'default_value' => 1,
                'ui' => 1,
                'instructions' => 'Gehalt auf der Webseite anzeigen?',
            ),
            
            array(
                'key' => 'field_job_benefits',
                'label' => 'Benefits',
                'name' => 'benefits',
                'type' => 'repeater',
                'button_label' => 'Add Benefit',
                'layout' => 'table',
                'sub_fields' => array(
                    array(
                        'key' => 'field_benefit_text',
                        'label' => 'Benefit',
                        'name' => 'benefit_text',
                        'type' => 'text',
                        'placeholder' => 'z.B. 30 Urlaubstage, Homeoffice, etc.',
                    ),
                    array(
                        'key' => 'field_benefit_icon',
                        'label' => 'Icon (optional)',
                        'name' => 'benefit_icon',
                        'type' => 'text',
                        'placeholder' => 'dashicons-yes',
                    ),
                ),
            ),
            
            // ============================================
            // REQUIREMENTS
            // ============================================
            array(
                'key' => 'field_job_requirements',
                'label' => 'Requirements',
                'name' => 'requirements',
                'type' => 'repeater',
                'button_label' => 'Add Requirement',
                'layout' => 'table',
                'instructions' => 'Was muss der Bewerber mitbringen?',
                'sub_fields' => array(
                    array(
                        'key' => 'field_requirement_text',
                        'label' => 'Requirement',
                        'name' => 'requirement_text',
                        'type' => 'text',
                        'placeholder' => 'z.B. 3+ Jahre Erfahrung mit React',
                    ),
                ),
            ),
            
            array(
                'key' => 'field_job_responsibilities',
                'label' => 'Responsibilities',
                'name' => 'responsibilities',
                'type' => 'repeater',
                'button_label' => 'Add Responsibility',
                'layout' => 'table',
                'instructions' => 'Was sind die Hauptaufgaben?',
                'sub_fields' => array(
                    array(
                        'key' => 'field_responsibility_text',
                        'label' => 'Responsibility',
                        'name' => 'responsibility_text',
                        'type' => 'text',
                        'placeholder' => 'z.B. Entwicklung von Web-Applikationen',
                    ),
                ),
            ),
            
            array(
                'key' => 'field_job_skills',
                'label' => 'Required Skills',
                'name' => 'skills',
                'type' => 'textarea',
                'rows' => 3,
                'placeholder' => 'Eine Zeile pro Skill, z.B.:
                                    JavaScript
                                    React
                                    Node.js',
                'instructions' => 'Eine Zeile pro Skill',
            ),
            
            // ============================================
            // APPLICATION
            // ============================================
            array(
                'key' => 'field_job_application_deadline',
                'label' => 'Application Deadline',
                'name' => 'application_deadline',
                'type' => 'date_picker',
                'display_format' => 'd.m.Y',
                'return_format' => 'Y-m-d',
                'first_day' => 1,
                'instructions' => 'Bewerbungsfrist (optional)',
            ),
            
            array(
                'key' => 'field_job_application_email',
                'label' => 'Application Email',
                'name' => 'application_email',
                'type' => 'email',
                'required' => 1,
                'placeholder' => 'jobs@example.com',
                'instructions' => 'E-Mail für Bewerbungen',
            ),
            
            array(
                'key' => 'field_job_application_url',
                'label' => 'Application URL (optional)',
                'name' => 'application_url',
                'type' => 'url',
                'placeholder' => 'https://example.com/apply',
                'instructions' => 'Optional: Externer Bewerbungslink',
            ),
            
            array(
                'key' => 'field_job_contact_person',
                'label' => 'Contact Person',
                'name' => 'contact_person',
                'type' => 'text',
                'placeholder' => 'Max Mustermann',
                'instructions' => 'Ansprechpartner für Rückfragen',
            ),
            
            array(
                'key' => 'field_job_contact_phone',
                'label' => 'Contact Phone',
                'name' => 'contact_phone',
                'type' => 'text',
                'placeholder' => '+43 XXX XXX XXX',
            ),
            
            // ============================================
            // STATUS & DISPLAY
            // ============================================
            array(
                'key' => 'field_job_featured',
                'label' => 'Featured Job',
                'name' => 'featured',
                'type' => 'true_false',
                'default_value' => 0,
                'ui' => 1,
                'instructions' => 'Job als "Featured" hervorheben?',
            ),
            
            array(
                'key' => 'field_job_urgent',
                'label' => 'Urgent Hiring',
                'name' => 'urgent',
                'type' => 'true_false',
                'default_value' => 0,
                'ui' => 1,
                'instructions' => 'Dringende Stellenbesetzung?',
            ),
            
            array(
                'key' => 'field_job_start_date',
                'label' => 'Start Date',
                'name' => 'start_date',
                'type' => 'text',
                'placeholder' => 'z.B. "Sofort", "01.06.2025", "Nach Vereinbarung"',
                'instructions' => 'Gewünschtes Startdatum',
            ),
            
        ),
        'location' => array(
            array(
                array(
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => 'job',
                ),
            ),
        ),
        'menu_order' => 0,
        'position' => 'normal',
        'style' => 'default',
        'label_placement' => 'top',
        'instruction_placement' => 'label',
    ));
}