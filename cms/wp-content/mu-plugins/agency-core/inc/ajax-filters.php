<?php
/**
 * AJAX Filters System
 * 
 * Provides advanced filtering for posts, CPTs, and WooCommerce products.
 * 
 * @package Agency_Core
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register AJAX endpoints
 */
add_action('wp_ajax_ajax_filter_posts', 'agency_core_ajax_filter_posts');
add_action('wp_ajax_nopriv_ajax_filter_posts', 'agency_core_ajax_filter_posts');

/**
 * AJAX Filter Posts Handler
 */
function agency_core_ajax_filter_posts() {
    // Verify nonce
    check_ajax_referer('ajax_filters_nonce', 'nonce');
    
    // Get filter parameters
    $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : 'post';
    $posts_per_page = isset($_POST['posts_per_page']) ? intval($_POST['posts_per_page']) : 12;
    $paged = isset($_POST['paged']) ? intval($_POST['paged']) : 1;
    $template = isset($_POST['template']) ? sanitize_text_field($_POST['template']) : 'card';
    
    // Taxonomy filters
    $tax_filters = isset($_POST['taxonomies']) ? $_POST['taxonomies'] : array();
    
    // Meta filters
    $meta_filters = isset($_POST['meta']) ? $_POST['meta'] : array();
    
    // Search
    $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
    
    // Sort
    $orderby = isset($_POST['orderby']) ? sanitize_text_field($_POST['orderby']) : 'date';
    $order = isset($_POST['order']) ? sanitize_text_field($_POST['order']) : 'DESC';
    
    // Build query args
    $args = array(
        'post_type' => $post_type,
        'posts_per_page' => $posts_per_page,
        'paged' => $paged,
        'post_status' => 'publish',
        'orderby' => $orderby,
        'order' => $order,
    );
    
    // Add search
    if (!empty($search)) {
        $args['s'] = $search;
    }
    
    // Build tax query
    if (!empty($tax_filters)) {
        $tax_query = array('relation' => 'AND');
        
        foreach ($tax_filters as $taxonomy => $terms) {
            if (!empty($terms) && is_array($terms)) {
                $tax_query[] = array(
                    'taxonomy' => sanitize_text_field($taxonomy),
                    'field' => 'slug',
                    'terms' => array_map('sanitize_text_field', $terms),
                    'operator' => 'IN',
                );
            }
        }
        
        if (count($tax_query) > 1) {
            $args['tax_query'] = $tax_query;
        }
    }
    
    // Build meta query
    if (!empty($meta_filters)) {
        $meta_query = array('relation' => 'AND');
        
        foreach ($meta_filters as $filter) {
            if (isset($filter['key'])) {
                $meta_item = array(
                    'key' => sanitize_text_field($filter['key']),
                );
                
                // Range filter
                if (isset($filter['min']) && isset($filter['max'])) {
                    $meta_item['value'] = array(
                        floatval($filter['min']),
                        floatval($filter['max'])
                    );
                    $meta_item['compare'] = 'BETWEEN';
                    $meta_item['type'] = 'NUMERIC';
                }
                // Single value
                else if (isset($filter['value'])) {
                    $meta_item['value'] = sanitize_text_field($filter['value']);
                    $meta_item['compare'] = isset($filter['compare']) ? $filter['compare'] : '=';
                }
                
                $meta_query[] = $meta_item;
            }
        }
        
        if (count($meta_query) > 1) {
            $args['meta_query'] = $meta_query;
        }
    }
    
    // Execute query
    $query = new WP_Query($args);
    
    // Prepare response
    $response = array(
        'success' => true,
        'found_posts' => $query->found_posts,
        'max_pages' => $query->max_num_pages,
        'current_page' => $paged,
        'posts' => array(),
        'html' => '',
    );
    
    // Generate HTML
    ob_start();
    
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            
            // Render template
            agency_core_render_filter_template($template, $post_type);
        }
        
        $response['html'] = ob_get_clean();
    } else {
        $response['html'] = '<div class="ajax-filters__no-results"><p>Keine Ergebnisse gefunden.</p></div>';
    }
    
    wp_reset_postdata();
    
    // Send response
    wp_send_json($response);
}

/**
 * Render filter result template
 */
function agency_core_render_filter_template($template, $post_type) {
    $template = sanitize_text_field($template);
    
    switch ($template) {
        case 'card':
            agency_core_template_filter_card($post_type);
            break;
            
        case 'list':
            agency_core_template_filter_list($post_type);
            break;
            
        case 'grid':
            agency_core_template_filter_grid($post_type);
            break;
            
        case 'job':
            agency_core_template_filter_job();
            break;
            
        case 'product':
            agency_core_template_filter_product();
            break;
            
        default:
            agency_core_template_filter_card($post_type);
            break;
    }
}

/**
 * Card Template
 */
function agency_core_template_filter_card($post_type) {
    ?>
    <article class="filter-result filter-result--card" data-post-id="<?php echo get_the_ID(); ?>">
        <?php if (has_post_thumbnail()) : ?>
            <div class="filter-result__thumbnail">
                <a href="<?php the_permalink(); ?>">
                    <?php the_post_thumbnail('medium'); ?>
                </a>
            </div>
        <?php endif; ?>
        
        <div class="filter-result__content">
            <h3 class="filter-result__title">
                <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
            </h3>
            
            <div class="filter-result__meta">
                <span class="filter-result__date"><?php echo get_the_date('d.m.Y'); ?></span>
            </div>
            
            <div class="filter-result__excerpt">
                <?php echo wp_trim_words(get_the_excerpt(), 20); ?>
            </div>
            
            <a href="<?php the_permalink(); ?>" class="filter-result__link">
                Mehr erfahren →
            </a>
        </div>
    </article>
    <?php
}

/**
 * List Template
 */
function agency_core_template_filter_list($post_type) {
    ?>
    <article class="filter-result filter-result--list" data-post-id="<?php echo get_the_ID(); ?>">
        <?php if (has_post_thumbnail()) : ?>
            <div class="filter-result__thumbnail">
                <a href="<?php the_permalink(); ?>">
                    <?php the_post_thumbnail('thumbnail'); ?>
                </a>
            </div>
        <?php endif; ?>
        
        <div class="filter-result__content">
            <h3 class="filter-result__title">
                <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
            </h3>
            
            <div class="filter-result__excerpt">
                <?php echo wp_trim_words(get_the_excerpt(), 30); ?>
            </div>
            
            <div class="filter-result__meta">
                <span class="filter-result__date"><?php echo get_the_date('d.m.Y'); ?></span>
            </div>
        </div>
    </article>
    <?php
}

/**
 * Grid Template
 */
function agency_core_template_filter_grid($post_type) {
    ?>
    <article class="filter-result filter-result--grid" data-post-id="<?php echo get_the_ID(); ?>">
        <?php if (has_post_thumbnail()) : ?>
            <div class="filter-result__thumbnail">
                <a href="<?php the_permalink(); ?>">
                    <?php the_post_thumbnail('large'); ?>
                    <div class="filter-result__overlay">
                        <span class="filter-result__view">Ansehen</span>
                    </div>
                </a>
            </div>
        <?php endif; ?>
        
        <div class="filter-result__content">
            <h3 class="filter-result__title">
                <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
            </h3>
        </div>
    </article>
    <?php
}

/**
 * Job Template
 */
function agency_core_template_filter_job() {
    $employment_type = get_field('employment_type');
    $location_terms = get_the_terms(get_the_ID(), 'job_location');
    $salary_min = get_field('salary_min');
    $salary_max = get_field('salary_max');
    $featured = get_field('featured');
    ?>
    <article class="filter-result filter-result--job <?php echo $featured ? 'filter-result--featured' : ''; ?>">
        <?php if ($featured) : ?>
            <span class="filter-result__badge">Featured</span>
        <?php endif; ?>
        
        <div class="filter-result__header">
            <h3 class="filter-result__title">
                <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
            </h3>
            
            <?php if ($location_terms && !is_wp_error($location_terms)) : ?>
                <div class="filter-result__location">
                    <span class="dashicons dashicons-location"></span>
                    <?php echo esc_html($location_terms[0]->name); ?>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="filter-result__meta">
            <?php if ($employment_type) : ?>
                <span class="filter-result__type"><?php echo esc_html($employment_type); ?></span>
            <?php endif; ?>
            
            <?php if ($salary_min && $salary_max) : ?>
                <span class="filter-result__salary">
                    <?php echo number_format($salary_min, 0, ',', '.'); ?> - <?php echo number_format($salary_max, 0, ',', '.'); ?> €
                </span>
            <?php endif; ?>
        </div>
        
        <div class="filter-result__excerpt">
            <?php echo wp_trim_words(get_the_excerpt(), 25); ?>
        </div>
        
        <a href="<?php the_permalink(); ?>" class="filter-result__button">
            Details ansehen
        </a>
    </article>
    <?php
}

/**
 * Product Template (WooCommerce)
 */
function agency_core_template_filter_product() {
    if (!function_exists('wc_get_product')) {
        return;
    }
    
    $product = wc_get_product(get_the_ID());
    if (!$product) {
        return;
    }
    ?>
    <article class="filter-result filter-result--product">
        <?php if (has_post_thumbnail()) : ?>
            <div class="filter-result__thumbnail">
                <a href="<?php the_permalink(); ?>">
                    <?php the_post_thumbnail('medium'); ?>
                    <?php if ($product->is_on_sale()) : ?>
                        <span class="filter-result__sale-badge">Sale</span>
                    <?php endif; ?>
                </a>
            </div>
        <?php endif; ?>
        
        <div class="filter-result__content">
            <h3 class="filter-result__title">
                <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
            </h3>
            
            <div class="filter-result__price">
                <?php echo $product->get_price_html(); ?>
            </div>
            
            <?php if ($product->get_rating_count() > 0) : ?>
                <div class="filter-result__rating">
                    <?php echo wc_get_rating_html($product->get_average_rating()); ?>
                </div>
            <?php endif; ?>
            
            <a href="<?php echo esc_url($product->add_to_cart_url()); ?>" class="filter-result__add-to-cart button">
                <?php echo esc_html($product->add_to_cart_text()); ?>
            </a>
        </div>
    </article>
    <?php
}