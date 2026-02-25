<?php
/**
 * WooCommerce Catalog Mode
 */

if (!defined('ABSPATH')) exit;

class MediaLab_WC_Catalog_Mode {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('acf/init', array($this, 'add_options_page'));
        add_action('init', array($this, 'maybe_activate_catalog_mode'), 20);
        add_action('init', array($this, 'register_inquiry_hooks'), 30);
    }
    
    public function maybe_activate_catalog_mode() {
        if ($this->is_catalog_mode_active()) {
            $this->activate_catalog_mode();
        }
    }
    
    public function register_inquiry_hooks() {
        if ($this->is_catalog_mode_active()) {
            add_filter('template_include', array($this, 'override_checkout_template'), 999);
            add_action('wp_ajax_wc_catalog_inquiry', array($this, 'handle_inquiry_submission'));
            add_action('wp_ajax_nopriv_wc_catalog_inquiry', array($this, 'handle_inquiry_submission'));
            add_action('wp_ajax_update_cart_quantity', array($this, 'update_cart_quantity'));
            add_action('wp_ajax_nopriv_update_cart_quantity', array($this, 'update_cart_quantity'));
        }
    }
    
    public function add_options_page() {
        if (!function_exists('acf_add_options_page')) return;
        
        acf_add_options_sub_page(array(
            'page_title'  => 'WooCommerce Catalog Mode',
            'menu_title'  => 'Catalog Mode',
            'parent_slug' => 'woocommerce',
            'capability'  => 'manage_woocommerce',
            'slug'        => 'wc-catalog-mode',
        ));
        
        acf_add_local_field_group(array(
            'key'    => 'group_wc_catalog_mode',
            'title'  => 'Catalog Mode Einstellungen',
            'fields' => array(
                array(
                    'key'           => 'field_wc_catalog_mode_enabled',
                    'label'         => 'Catalog Mode aktivieren',
                    'name'          => 'wc_catalog_mode_enabled',
                    'type'          => 'true_false',
                    'instructions'  => 'Aktiviert Katalogmodus mit Anfrage statt Kauf',
                    'default_value' => 0,
                    'ui'            => 1,
                ),
                array(
                    'key'           => 'field_wc_catalog_mode_hide_prices',
                    'label'         => 'Preise verstecken',
                    'name'          => 'wc_catalog_mode_hide_prices',
                    'type'          => 'true_false',
                    'default_value' => 1,
                    'ui'            => 1,
                    'conditional_logic' => array(array(array(
                        'field'    => 'field_wc_catalog_mode_enabled',
                        'operator' => '==',
                        'value'    => '1',
                    ))),
                ),
                array(
                    'key'           => 'field_wc_catalog_mode_hide_buttons',
                    'label'         => 'Kaufbuttons verstecken',
                    'name'          => 'wc_catalog_mode_hide_buttons',
                    'type'          => 'true_false',
                    'default_value' => 1,
                    'ui'            => 1,
                    'conditional_logic' => array(array(array(
                        'field'    => 'field_wc_catalog_mode_enabled',
                        'operator' => '==',
                        'value'    => '1',
                    ))),
                ),
                array(
                    'key'           => 'field_wc_catalog_mode_message',
                    'label'         => 'Info-Text',
                    'name'          => 'wc_catalog_mode_message',
                    'type'          => 'textarea',
                    'placeholder'   => 'Preise auf Anfrage',
                    'rows'          => 2,
                    'conditional_logic' => array(array(array(
                        'field'    => 'field_wc_catalog_mode_enabled',
                        'operator' => '==',
                        'value'    => '1',
                    ))),
                ),
                array(
                    'key'           => 'field_wc_catalog_mode_hide_cart',
                    'label'         => 'Warenkorb-Seite verstecken',
                    'name'          => 'wc_catalog_mode_hide_cart',
                    'type'          => 'true_false',
                    'default_value' => 0,
                    'ui'            => 1,
                    'conditional_logic' => array(array(array(
                        'field'    => 'field_wc_catalog_mode_enabled',
                        'operator' => '==',
                        'value'    => '1',
                    ))),
                ),
            ),
            'location' => array(array(array(
                'param'    => 'options_page',
                'operator' => '==',
                'value'    => 'wc-catalog-mode',
            ))),
        ));
    }
    
    public function is_catalog_mode_active() {
        return get_field('wc_catalog_mode_enabled', 'option') === true;
    }
    
    private function activate_catalog_mode() {
        $hide_prices = get_field('wc_catalog_mode_hide_prices', 'option');
        $hide_buttons = get_field('wc_catalog_mode_hide_buttons', 'option');
        
        if ($hide_prices === true) {
            remove_action('woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10);
            remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_price', 10);
        }
        
        if ($hide_buttons === true) {
            remove_action('woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10);
            remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30);
        }
        
        add_action('woocommerce_after_shop_loop_item_title', array($this, 'display_catalog_message'), 10);
        add_action('woocommerce_single_product_summary', array($this, 'display_catalog_message'), 10);
        
        if (get_field('wc_catalog_mode_hide_cart', 'option')) {
            add_filter('woocommerce_is_purchasable', '__return_false');
            add_action('template_redirect', array($this, 'disable_cart_checkout'));
        }
    }
    
    public function display_catalog_message() {
        $message = get_field('wc_catalog_mode_message', 'option');
        if ($message) {
            echo '<div class="wc-catalog-mode-message">' . esc_html($message) . '</div>';
        }
    }
    
    public function disable_cart_checkout() {
        global $wp;
        
        // WICHTIG: Erlaube Warenkorb wenn konfigurierbare Produkte im Cart
        if (WC()->cart) {
            foreach (WC()->cart->get_cart() as $cart_item) {
                $product_id = $cart_item['product_id'];
                // Wenn konfigurierbar, erlaube Warenkorb
                if (get_field('is_configurable', $product_id)) {
                    return; // Nicht redirecten!
                }
            }
        }
        
        $current_url = home_url($wp->request);
        $cart_url = wc_get_page_permalink('cart');
        $checkout_url = wc_get_page_permalink('checkout');
        
        // Prüfe ob aktuelle URL Warenkorb oder Kasse ist (auch deutsche URLs)
        if (is_cart() || is_checkout() || 
            strpos($current_url, '/warenkorb') !== false || 
            strpos($current_url, '/kasse') !== false ||
            strpos($current_url, $cart_url) !== false ||
            strpos($current_url, $checkout_url) !== false) {
            wp_redirect(wc_get_page_permalink('shop'));
            exit;
        }
    }
    
    public function override_checkout_template($template) {
        if (is_checkout() && !is_wc_endpoint_url()) {
            $custom_template = MEDIA_LAB_WC_PATH . 'templates/inquiry-checkout.php';
            if (file_exists($custom_template)) {
                return $custom_template;
            }
        }
        return $template;
    }
    
    public function handle_inquiry_submission() {
        check_ajax_referer('wc_catalog_inquiry', 'nonce');
        
        $name = sanitize_text_field($_POST['name']);
        $email = sanitize_email($_POST['email']);
        $phone = sanitize_text_field($_POST['phone']);
        $message = sanitize_textarea_field($_POST['message']);
        
        if (!$name || !$email) {
            wp_send_json_error('Bitte Name und E-Mail angeben.');
        }
        
        $cart_items = WC()->cart->get_cart();
        $admin_email = get_option('admin_email');
        $subject = 'Neue Produktanfrage von ' . $name;
        
        $body = "Neue Anfrage:\n\n";
        $body .= "Name: $name\n";
        $body .= "E-Mail: $email\n";
        $body .= "Telefon: $phone\n";
        $body .= "Nachricht: $message\n\n";
        $body .= "--- Angefragte Produkte ---\n\n";
        
        foreach ($cart_items as $cart_item) {
            $product = $cart_item['data'];
            $body .= sprintf(
                "%s (Menge: %d)\n",
                $product->get_name(),
                $cart_item['quantity']
            );
        }
        
        $sent = wp_mail($admin_email, $subject, $body);
        
        if ($sent) {
            $customer_subject = 'Ihre Produktanfrage';
            $customer_body = "Hallo $name,\n\nvielen Dank für Ihre Anfrage. Wir melden uns in Kürze bei Ihnen.\n\nMit freundlichen Grüßen";
            wp_mail($email, $customer_subject, $customer_body);
            
            WC()->cart->empty_cart();
            wp_send_json_success('Vielen Dank! Ihre Anfrage wurde versendet.');
        } else {
            wp_send_json_error('Fehler beim Versenden. Bitte erneut versuchen.');
        }
    }
    
    public function update_cart_quantity() {
        check_ajax_referer('update_cart_quantity', 'nonce');
        
        $cart_item_key = sanitize_text_field($_POST['cart_item_key']);
        $quantity = absint($_POST['quantity']);
        
        if ($quantity < 1) {
            wp_send_json_error('Ungültige Menge');
        }
        
        WC()->cart->set_quantity($cart_item_key, $quantity);
        
        wp_send_json_success('Warenkorb aktualisiert');
    }
}

// Init
MediaLab_WC_Catalog_Mode::get_instance();
