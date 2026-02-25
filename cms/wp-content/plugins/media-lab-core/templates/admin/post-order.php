<?php
/**
 * Admin Template: Post Order
 * Drag & Drop Sortierung
 */

if (!defined('ABSPATH')) exit;

// Hole Posts vom aktuellen Post Type
$args = array(
    'post_type' => $current_type,
    'posts_per_page' => -1,
    'orderby' => 'menu_order',
    'order' => 'ASC',
    'post_status' => 'publish',
);

$posts = get_posts($args);
?>

<div class="wrap media-lab-post-order">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <!-- Post Type Selector -->
    <div class="post-type-selector">
        <label for="post-type-select">Inhaltstyp:</label>
        <select id="post-type-select" onchange="window.location.href='?page=media-lab-post-order&post_type=' + this.value">
            <?php foreach ($post_types as $type) : 
                if (in_array($type->name, array('attachment', 'revision', 'nav_menu_item'))) continue;
            ?>
                <option value="<?php echo esc_attr($type->name); ?>" <?php selected($current_type, $type->name); ?>>
                    <?php echo esc_html($type->labels->name); ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <span class="description">
            Wählen Sie den Inhaltstyp aus, dessen Reihenfolge Sie ändern möchten.
        </span>
    </div>
    
    <?php if (empty($posts)) : ?>
        <div class="notice notice-info">
            <p>Keine veröffentlichten <?php echo esc_html($post_types[$current_type]->labels->name); ?> gefunden.</p>
        </div>
    <?php else : ?>
        
        <div class="order-info">
            <p>
                <strong><?php echo count($posts); ?> <?php echo esc_html($post_types[$current_type]->labels->name); ?></strong>
            </p>
            <p class="description">
                ⬍ Ziehen Sie die Einträge per Drag & Drop, um die Reihenfolge zu ändern. Die Änderungen werden automatisch gespeichert.
            </p>
        </div>
        
        <div id="sortable-posts" class="sortable-posts">
            <?php foreach ($posts as $post) : 
                $thumbnail = get_the_post_thumbnail($post->ID, 'thumbnail');
                $edit_link = get_edit_post_link($post->ID);
            ?>
                <div class="sortable-item" data-id="<?php echo esc_attr($post->ID); ?>">
                    <div class="sortable-handle">
                        <span class="dashicons dashicons-menu"></span>
                    </div>
                    
                    <?php if ($thumbnail) : ?>
                        <div class="sortable-thumbnail">
                            <?php echo $thumbnail; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="sortable-content">
                        <div class="sortable-title">
                            <a href="<?php echo esc_url($edit_link); ?>" target="_blank">
                                <?php echo esc_html($post->post_title); ?>
                            </a>
                        </div>
                        
                        <div class="sortable-meta">
                            <span class="post-id">ID: <?php echo $post->ID; ?></span>
                            <span class="post-date">
                                <?php echo get_the_date('d.m.Y', $post->ID); ?>
                            </span>
                            <?php if ($post->post_status !== 'publish') : ?>
                                <span class="post-status">
                                    <?php echo esc_html($post->post_status); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="sortable-actions">
                        <span class="menu-order">Position: <strong><?php echo $post->menu_order; ?></strong></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="save-status" id="save-status" style="display: none;">
            <span class="spinner is-active"></span>
            <span class="status-text">Speichere...</span>
        </div>
        
    <?php endif; ?>
</div>
