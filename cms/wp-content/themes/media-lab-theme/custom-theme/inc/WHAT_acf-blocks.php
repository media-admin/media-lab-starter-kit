<?php
/**
 * ACF Blocks Registration
 */

// Register ACF Blocks
add_action('acf/init', 'register_acf_blocks');

function register_acf_blocks() {
    // Check if ACF exists
    if (!function_exists('acf_register_block_type')) {
        return;
    }
    
    // Accordion Block
    acf_register_block_type(array(
        'name'              => 'accordion',
        'title'             => __('Accordion'),
        'description'       => __('Ein interaktives Accordion-Element'),
        'render_template'   => 'template-parts/blocks/accordion.php',
        'category'          => 'formatting',
        'icon'              => 'list-view',
        'keywords'          => array('accordion', 'faq', 'toggle'),
        'supports'          => array(
            'align' => false,
            'mode' => true,
            'jsx' => true
        ),
    ));
}