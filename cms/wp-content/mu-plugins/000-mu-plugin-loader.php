<?php
/**
 * MU-Plugin Loader
 * 
 * Loads plugins from subdirectories in mu-plugins.
 * Priority: Agency Core > Other plugins
 * 
 * @package MU_Loader
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Load Agency Core (PRIORITY)
 */
$agency_core_file = WPMU_PLUGIN_DIR . '/agency-core/agency-core.php';
if (file_exists($agency_core_file)) {
    require_once $agency_core_file;
    
    // Log successful load (only in debug mode)
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Agency Core loaded successfully');
    }
} else {
    // Critical error - Agency Core not found
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p><strong>Critical:</strong> Agency Core plugin not found. Please ensure it is installed in mu-plugins/agency-core/</p></div>';
    });
}

/**
 * Load Custom Blocks (Legacy - will be deprecated)
 * 
 * Note: Custom blocks are now loaded via Agency Core.
 * This is kept for backwards compatibility during migration.
 */
// Commented out as it's now loaded via Agency Core
// $custom_blocks_file = WPMU_PLUGIN_DIR . '/custom-blocks/custom-blocks.php';
// if (file_exists($custom_blocks_file)) {
//     require_once $custom_blocks_file;
// }

/**
 * Load Custom Functionality (Legacy - will be deprecated)
 * 
 * Note: CPTs and ACF are now in Agency Core.
 * This can be removed after successful migration.
 */
// Commented out as functionality moved to Agency Core
// $custom_func_file = WPMU_PLUGIN_DIR . '/custom-functionality/custom-functionality.php';
// if (file_exists($custom_func_file)) {
//     require_once $custom_func_file;
// }

/**
 * Display loaded plugins info (admin only, debug mode)
 */
if (defined('WP_DEBUG') && WP_DEBUG && is_admin()) {
    add_action('admin_footer', function() {
        if (current_user_can('manage_options')) {
            echo '<!-- Agency Core MU-Plugin Loader: Active -->';
            echo '<!-- Agency Core Version: ' . (defined('AGENCY_CORE_VERSION') ? AGENCY_CORE_VERSION : 'Not loaded') . ' -->';
        }
    });
}