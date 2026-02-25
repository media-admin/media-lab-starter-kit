<?php
/**
 * Custom Post Order with Drag & Drop
 * Ersetzt "Simple Custom Post Order" Plugin
 */

if (!defined('ABSPATH')) exit;

class MediaLab_Post_Order {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_ajax_update_post_order', array($this, 'ajax_update_post_order'));
        add_action('pre_get_posts', array($this, 'apply_custom_order'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'Sortierung',
            'Sortierung',
            'edit_posts',
            'media-lab-post-order',
            array($this, 'render_admin_page'),
            'dashicons-sort',
            25
        );
    }
    
    public function render_admin_page() {
        $post_types = get_post_types(array('public' => true), 'objects');
        $current_type = isset($_GET['post_type']) ? sanitize_text_field($_GET['post_type']) : 'post';
        
        include MEDIA_LAB_CORE_PATH . 'templates/admin/post-order.php';
    }
    
    public function ajax_update_post_order() {
        check_ajax_referer('post_order_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Keine Berechtigung');
        }
        
        $order = isset($_POST['order']) ? json_decode(stripslashes($_POST['order']), true) : array();
        
        if (empty($order)) {
            wp_send_json_error('Keine Daten');
        }
        
        global $wpdb;
        
        foreach ($order as $position => $post_id) {
            $wpdb->update(
                $wpdb->posts,
                array('menu_order' => $position),
                array('ID' => intval($post_id)),
                array('%d'),
                array('%d')
            );
        }
        
        wp_cache_flush();
        
        wp_send_json_success('Reihenfolge gespeichert');
    }
    
    public function apply_custom_order($query) {
        if (is_admin() || !$query->is_main_query()) {
            return;
        }
        
        $post_type = $query->get('post_type');
        if (!$post_type) {
            $post_type = 'post';
        }
        
        $query->set('orderby', 'menu_order');
        $query->set('order', 'ASC');
    }
    
    public function enqueue_scripts($hook) {
        if ($hook !== 'toplevel_page_media-lab-post-order') {
            return;
        }
        
        wp_enqueue_script(
            'sortablejs',
            'https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js',
            array(),
            '1.15.0',
            true
        );
        
        wp_enqueue_script(
            'media-lab-post-order',
            MEDIA_LAB_CORE_URL . 'assets/js/post-order.js',
            array('jquery', 'sortablejs'),
            '1.0.0',
            true
        );
        
        wp_localize_script('media-lab-post-order', 'postOrderData', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('post_order_nonce'),
        ));
        
        wp_enqueue_style(
            'media-lab-post-order',
            MEDIA_LAB_CORE_URL . 'assets/css/post-order.css',
            array(),
            '1.0.0'
        );
    }
}

MediaLab_Post_Order::get_instance();
