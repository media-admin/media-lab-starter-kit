<?php
/**
 * Plugin Name: Agency Core
 * Plugin URI: https://your-agency.com
 * Description: Core functionality for agency websites - Custom Post Types, ACF Fields, and Shortcodes
 * Version: 1.0.0
 * Author: Your Agency
 * Author URI: https://your-agency.com
 * License: GPL v2 or later
 * Text Domain: agency-core
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('AGENCY_CORE_VERSION', '1.0.0');
define('AGENCY_CORE_PATH', plugin_dir_path(__FILE__));
define('AGENCY_CORE_URL', plugin_dir_url(__FILE__));
define('AGENCY_CORE_FILE', __FILE__);

/**
 * Main Agency Core Class
 */
class Agency_Core {
    
    private static $instance = null;
    
    /**
     * Get instance
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->includes();
        $this->init_hooks();
    }
    
    /**
     * Include required files
     */
    private function includes() {
        require_once AGENCY_CORE_PATH . 'inc/custom-post-types.php';
        require_once AGENCY_CORE_PATH . 'inc/acf-fields.php';
        require_once AGENCY_CORE_PATH . 'inc/shortcodes.php';
        require_once AGENCY_CORE_PATH . 'inc/admin.php';
        require_once AGENCY_CORE_PATH . 'inc/ajax-search.php';
        require_once AGENCY_CORE_PATH . 'inc/ajax-load-more.php';
        require_once AGENCY_CORE_PATH . 'inc/ajax-filters.php';
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('init', array($this, 'load_textdomain'));
        add_action('admin_notices', array($this, 'check_dependencies'));
    }
    
    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain('agency-core', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }
    
    /**
     * Check for required dependencies
     */
    public function check_dependencies() {
        // Check for ACF
        if (!class_exists('ACF')) {
            echo '<div class="notice notice-warning"><p><strong>Agency Core:</strong> Advanced Custom Fields plugin is recommended for full functionality.</p></div>';
        }
    }
    
    /**
     * Get plugin version
     */
    public function get_version() {
        return AGENCY_CORE_VERSION;
    }
}

/**
 * Initialize the plugin
 * 
 * Returns the main instance of Agency Core
 */
function agency_core() {
    return Agency_Core::instance();
}

// Initialize Agency Core
agency_core();