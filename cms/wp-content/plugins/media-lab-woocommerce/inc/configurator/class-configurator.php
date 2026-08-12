<?php
/**
 * Product Configurator Main Class
 */

if (!defined('ABSPATH')) exit;

class MediaLab_Product_Configurator {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // ACF Fields registrieren
        add_action('acf/init', array($this, 'register_acf_fields'));
        
        // Frontend Hooks
        // WICHTIG: NICHT an 'woocommerce_before_add_to_cart_button' hängen - dieser Hook
        // wird nur INNERHALB der Add-to-Cart-Vorlage ausgelöst, die catalog-mode.php bei
        // aktivem "Kaufbuttons verstecken" komplett von 'woocommerce_single_product_summary'
        // entfernt (siehe MediaLab_WC_Catalog_Mode::activate_catalog_mode()). Dadurch würde
        // der Konfigurator für JEDES konfigurierbare Produkt nie mehr feuern, sobald Catalog
        // Mode aktiv ist. Stattdessen direkt an 'woocommerce_single_product_summary' hängen,
        // mit fester Priorität VOR der Standard-Add-to-Cart-Priorität (30) - so bleibt die
        // bisherige visuelle Reihenfolge (Tabs, dann Konfigurator) erhalten und die Anzeige
        // ist unabhängig von Catalog-Mode-Einstellungen.
        add_action('woocommerce_single_product_summary', array($this, 'move_tabs_before_configurator'), 24);
        add_action('woocommerce_single_product_summary', array($this, 'maybe_show_configurator'), 25);

        // Rezensionen für konfigurierbare Produkte AUSSERHALB von .summary
        // ausgeben (voller Seitenbreite unterhalb des Konfigurators), statt
        // wie zuvor innerhalb von .summary eingequetscht auf 50% Breite.
        add_action('woocommerce_after_single_product_summary', array($this, 'render_reviews_for_configurable'), 20);
        add_filter('woocommerce_placeholder_img_src', array($this, 'replace_placeholder_image'), 999);
        add_filter('woocommerce_add_to_cart_validation', array($this, 'validate_configuration'), 10, 3);
        add_filter('woocommerce_add_cart_item_data', array($this, 'add_configuration_to_cart'), 10, 2);
        
        // Cart Hooks
        add_filter('woocommerce_get_item_data', array($this, 'display_configuration_in_cart'), 10, 2);
        add_action('woocommerce_before_calculate_totals', array($this, 'update_cart_item_price'));
        
        // Order Hooks
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'save_configuration_to_order'), 10, 4);
        
        // Admin Hooks
        add_action('woocommerce_admin_order_item_headers', array($this, 'admin_order_item_headers'));
        add_action('woocommerce_admin_order_item_values', array($this, 'admin_order_item_values'), 10, 3);
        
        // AJAX Handlers
        add_action('wp_ajax_get_conditional_options', array($this, 'ajax_get_conditional_options'));
        add_action('wp_ajax_nopriv_get_conditional_options', array($this, 'ajax_get_conditional_options'));
        add_action('wp_ajax_calculate_price', array($this, 'ajax_calculate_price'));
        add_action('wp_ajax_nopriv_calculate_price', array($this, 'ajax_calculate_price'));
        add_action('wp_ajax_upload_configurator_file', array($this, 'ajax_upload_configurator_file'));
        add_action('wp_ajax_nopriv_upload_configurator_file', array($this, 'ajax_upload_configurator_file'));
        add_action('wp_ajax_configurator_inquiry', array($this, 'ajax_configurator_inquiry'));
        add_action('wp_ajax_nopriv_configurator_inquiry', array($this, 'ajax_configurator_inquiry'));
        
        // Assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    public function is_configurable($product_id) {
        return (bool) get_field('is_configurable', $product_id);
    }
    
    public function get_configurator_type($product_id) {
        return get_field('config_type', $product_id);
    }
    
    public function get_configuration_steps($product_id) {
        /**
         * Vormals: bei Konfigurator-Typ 'textile' wurden die Steps über
         * get_textile_steps() aus separaten CPT-Posts geladen (Post-IDs
         * erwartet in der 'config_steps'-Postmeta). Diese CPT-Registrierung
         * existiert im Plugin nirgends - die tatsächlichen Konfigurations-
         * schritte werden für ALLE Konfigurator-Typen im selben
         * ACF-Repeater-Feld 'config_steps' direkt auf dem Produkt
         * gespeichert (siehe class-acf-fields.php, field_config_steps).
         *
         * Der rohe get_post_meta($product_id, 'config_steps', true)-Aufruf
         * in get_textile_steps() liefert bei einem ACF-Repeater aber NICHT
         * die Zeilen, sondern nur die interne Zeilenanzahl als String
         * (z.B. "5") - der is_array()-Check dort schlug dadurch IMMER fehl,
         * get_textile_steps() gab IMMER ein leeres Array zurück. Jedes
         * Produkt mit Konfigurator-Typ "Textilien" zeigte dadurch 0 Schritte
         * (direkter Sprung zur Zusammenfassung), unabhängig davon wie viele
         * Zeilen im Repeater tatsächlich gepflegt waren.
         *
         * Fix: ALLE Konfigurator-Typen laden konsistent aus dem
         * ACF-Repeater. get_textile_steps() bleibt unverändert im Code
         * stehen (privat, ungenutzt) statt sie zu löschen - falls das
         * CPT-basierte System an anderer, uns nicht vorliegender Stelle
         * doch noch benötigt wird, ist der Code weiterhin vorhanden und
         * kann gezielt reaktiviert werden.
         */
        $steps = get_field('config_steps', $product_id);
        return $steps ? $steps : array();
    }
    
    private function get_textile_steps($product_id) {
        // Hole Step IDs aus Meta
        $step_ids = get_post_meta($product_id, 'config_steps', true);
        
        if (!$step_ids || !is_array($step_ids)) {
            return array();
        }
        
        $steps = array();
        foreach ($step_ids as $step_id) {
            $post = get_post($step_id);
            if (!$post) continue;
            
            $steps[] = array(
                'step_id' => get_post_meta($step_id, 'step_id', true),
                'step_label' => get_post_meta($step_id, 'step_label', true),
                'step_type' => get_post_meta($step_id, 'step_type', true),
                'required' => get_post_meta($step_id, 'required', true),
                'show_in_summary' => get_post_meta($step_id, 'show_in_summary', true),
                'description' => get_post_meta($step_id, 'description', true),
                'options' => get_post_meta($step_id, 'options', true) ?: false,
                'conditions' => get_post_meta($step_id, 'conditions', true) ?: false,
                'min_value' => get_post_meta($step_id, 'min_value', true) ?: '',
                'max_value' => get_post_meta($step_id, 'max_value', true) ?: '',
                'allowed_file_types' => get_post_meta($step_id, 'allowed_file_types', true) ?: 'pdf,jpg,png',
                'max_file_size' => get_post_meta($step_id, 'max_file_size', true) ?: 10,
            );
        }
        

        return $steps;
    }
    
    public function register_acf_fields() {
        if (!function_exists('acf_add_local_field_group')) return;
        
        require_once MEDIA_LAB_WC_PATH . 'inc/configurator/class-acf-fields.php';
        MediaLab_Configurator_ACF_Fields::register();
    }
    
    public function maybe_show_configurator() {
        global $product;
        
        if (!$this->is_configurable($product->get_id())) {
            return;
        }
        
        // Entferne ALLE Add-to-Cart Hooks
        remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30);
        remove_action('woocommerce_simple_add_to_cart', 'woocommerce_simple_add_to_cart', 30);
        remove_action('woocommerce_variable_add_to_cart', 'woocommerce_variable_add_to_cart', 30);
        
        // Entferne Preis für konfigurierbare Produkte
        remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_price', 10);
        
        // Verstecke Quantity + Preis + Catalog Message per CSS
        echo '<style>
            .single_add_to_cart_button, 
            .quantity,
            .product.type-product .price,
            .product.type-product .wc-catalog-mode-message { 
                display: none !important; 
            }
            /* Placeholder Bild für Produkte ohne Bild */
            .woocommerce-product-gallery__image--placeholder {
                display: block !important;
            }
        </style>';
        
        // Ersetze komplettes Gallery HTML wenn kein Bild vorhanden
        if (!$product->get_image_id()) {
            add_filter('woocommerce_single_product_image_gallery_html', function($html, $product_id) {
                $placeholder = wc_placeholder_img_src('woocommerce_single');
                return '<div class="woocommerce-product-gallery woocommerce-product-gallery--with-images">
                    <figure class="woocommerce-product-gallery__wrapper">
                        <div class="woocommerce-product-gallery__image">
                            <img src="' . esc_url($placeholder) . '" alt="Placeholder" class="wp-post-image" />
                        </div>
                    </figure>
                </div>';
            }, 999, 2);
        }
        
        $this->render_configurator($product->get_id());
    }
    
    private function render_configurator($product_id) {
        $steps = $this->get_configuration_steps($product_id);
        $type = $this->get_configurator_type($product_id);
        
        include MEDIA_LAB_WC_PATH . 'templates/configurator/wizard.php';
    }
    
    public function validate_configuration($passed, $product_id, $quantity) {
        if (!$this->is_configurable($product_id)) {
            return $passed;
        }
        
        if (!isset($_POST['product_config'])) {
            wc_add_notice('Bitte konfigurieren Sie das Produkt.', 'error');
            return false;
        }
        
        $config = json_decode(stripslashes($_POST['product_config']), true);
        $steps = $this->get_configuration_steps($product_id);
        
        foreach ($steps as $step) {
            if (isset($step['required']) && $step['required']) {
                $step_id = $step['step_id'];
                if (empty($config[$step_id])) {
                    wc_add_notice(sprintf('Bitte wählen Sie: %s', $step['step_label']), 'error');
                    return false;
                }
            }
        }
        
        return $passed;
    }
    
    public function add_configuration_to_cart($cart_item_data, $product_id) {
        if (!$this->is_configurable($product_id)) {
            return $cart_item_data;
        }
        
        if (isset($_POST['product_config'])) {
            $config = json_decode(stripslashes($_POST['product_config']), true);
            $cart_item_data['product_config'] = $config;
            $cart_item_data['unique_key'] = md5(json_encode($config) . time());
        }
        
        return $cart_item_data;
    }
    
    public function display_configuration_in_cart($item_data, $cart_item) {
        if (!isset($cart_item['product_config'])) {
            return $item_data;
        }

        $display = $this->get_config_display_array($cart_item['product_id'], $cart_item['product_config']);
        foreach ($display as $label => $value) {
            $item_data[] = array(
                'name'  => $label,
                'value' => $value,
            );
        }

        return $item_data;
    }

    /**
     * Formatiert eine rohe Konfiguration (step_id => Wert) als lesbares
     * Label => Wert-Array. Wiederverwendbar für Cart-Anzeige, Wunschliste
     * und Mail-Versand (siehe inc/inquiry/class-mail.php), damit Cart,
     * Wunschliste und Mail immer identisch formatiert sind - EINE Quelle
     * statt an drei Stellen dieselbe Logik zu duplizieren.
     *
     * @return array<string,string> Label => formatierter Wert
     */
    public function get_config_display_array($product_id, $config): array {
        $steps  = $this->get_configuration_steps($product_id);
        $result = [];

        foreach ($steps as $step) {
            $step_id = $step['step_id'];
            // Konfigurator initialisiert JEDES Step-Feld beim Start mit einem
            // leeren String (siehe configurator.js, init()) - auch nie befüllte
            // file_upload/text/select-Steps. isset() allein reicht daher nicht,
            // sonst erscheinen z.B. nie hochgeladene "Logo-Datei"-Steps leer in
            // der Anzeige. '0' bleibt bewusst gültig (z.B. Mengenfeld = 0).
            if ( ! isset( $config[ $step_id ] ) || $config[ $step_id ] === '' || empty( $step['show_in_summary'] ) ) continue;

            $value = $config[$step_id];

            if ( in_array( $step['step_type'], [ 'select', 'radio', 'color_picker' ], true ) && !empty($step['options']) ) {
                $option  = null;
                foreach ($step['options'] as $opt) {
                    if ($opt['value'] === $value) { $option = $opt; break; }
                }
                $value = $option ? $option['label'] : $value;
            } elseif ($step['step_type'] === 'checkbox' && is_array($value) && !empty($step['options'])) {
                $labels = [];
                foreach ($value as $v) {
                    foreach ($step['options'] as $opt) {
                        if ($opt['value'] === $v) { $labels[] = $opt['label']; break; }
                    }
                }
                $value = implode(', ', $labels);
            } elseif ($step['step_type'] === 'size_matrix' && is_array($value)) {
                $sizes = [];
                foreach ($value as $size => $qty) {
                    if ($qty > 0) $sizes[] = "$size: {$qty}x";
                }
                $value = implode(', ', $sizes);
            } elseif ($step['step_type'] === 'file_upload' && is_array($value)) {
                $value = $value['filename'] ?? $value['name'] ?? basename((string) ($value['url'] ?? ''));
            } elseif (is_array($value)) {
                $value = implode(', ', $value);
            }

            // Nach der Formatierung nochmal prüfen: leere Checkbox-Auswahl
            // (initialisiert als []) landet z.B. als leerer implode()-String hier.
            if ($value === '' || $value === null) continue;

            $result[$step['step_label']] = (string) $value;
        }

        return $result;
    }

    /**
     * Sammelt alle Attachment-IDs aus file_upload-Steps einer Konfiguration
     * (für den finalen Anfrage-Anhang, siehe class-inquiry-engine.php).
     *
     * @return int[]
     */
    public function get_attachment_ids_from_config($config): array {
        $ids = [];
        if (!is_array($config)) return $ids;

        foreach ($config as $value) {
            if (is_array($value) && !empty($value['id'])) {
                $ids[] = (int) $value['id'];
            }
        }
        return $ids;
    }

    /**
     * Liefert die Preisaufschlüsselung für ein konfigurierbares Produkt,
     * oder null wenn das Produkt nicht konfigurierbar ist. Wiederverwendbar
     * für Cart, Wunschliste und Konfigurator-Direktanfrage.
     */
    public function get_price_breakdown($product_id, $config): ?array {
        if (!$this->is_configurable($product_id)) return null;

        require_once MEDIA_LAB_WC_PATH . 'inc/configurator/class-price-calculator.php';
        $calculator = new MediaLab_Price_Calculator($product_id);
        return $calculator->get_breakdown($config);
    }
    
    public function update_cart_item_price($cart) {
        if (is_admin() && !defined('DOING_AJAX')) return;
        
        foreach ($cart->get_cart() as $cart_item) {
            if (isset($cart_item['product_config'])) {
                $product_id = $cart_item['product_id'];
                $config = $cart_item['product_config'];
                
                $steps = get_field('config_steps', $product_id);
                if (!is_array($steps) || empty($steps)) {
                    continue;
                }
                
                require_once MEDIA_LAB_WC_PATH . 'inc/configurator/class-price-calculator.php';
                $calculator = new MediaLab_Price_Calculator($product_id);
                
                $new_price = $calculator->calculate($config);
                $cart_item['data']->set_price($new_price);
            }
        }
    }
    
    public function save_configuration_to_order($item, $cart_item_key, $values, $order) {
        if (isset($values['product_config'])) {
            $item->add_meta_data('_product_config', $values['product_config'], true);
        }
    }
    
    public function admin_order_item_headers() {
        echo '<th class="item-configuration">Konfiguration</th>';
    }
    
    public function admin_order_item_values($product, $item, $item_id) {
        $config = $item->get_meta('_product_config');
        
        if ($config) {
            echo '<td class="item-configuration">';
            echo '<button type="button" class="button view-config" data-config=\'' . esc_attr(json_encode($config)) . '\'>Details anzeigen</button>';
            echo '</td>';
        } else {
            echo '<td class="item-configuration">-</td>';
        }
    }
    
    public function ajax_get_conditional_options() {
        check_ajax_referer('configurator_nonce', 'nonce');
        
        $product_id = intval($_POST['product_id']);
        $step_id = sanitize_text_field($_POST['step_id']);
        $current_config = isset($_POST['config']) ? json_decode(stripslashes($_POST['config']), true) : array();
        
        $steps = $this->get_configuration_steps($product_id);
        $target_step = null;
        
        foreach ($steps as $step) {
            if ($step['step_id'] === $step_id) {
                $target_step = $step;
                break;
            }
        }
        
        if (!$target_step) {
            wp_send_json_error('Step not found');
        }
        
        $filtered_options = $this->filter_conditional_options($target_step, $current_config);
        wp_send_json_success($filtered_options);
    }
    
    private function filter_conditional_options($step, $config) {
        if (empty($step['conditions'])) {
            return $step['options'];
        }
        
        $conditions = $step['conditions'];
        $options = $step['options'];
        
        $conditions_met = true;
        foreach ($conditions as $condition) {
            $field = $condition['field'];
            $operator = $condition['operator'];
            $value = $condition['value'];
            
            if (!isset($config[$field])) {
                $conditions_met = false;
                break;
            }
            
            switch ($operator) {
                case '==':
                    if ($config[$field] !== $value) {
                        $conditions_met = false;
                    }
                    break;
                case '!=':
                    if ($config[$field] === $value) {
                        $conditions_met = false;
                    }
                    break;
            }
        }
        
        if (!$conditions_met) {
            return array();
        }
        
        return $options;
    }
    
    public function ajax_calculate_price() {
        check_ajax_referer('configurator_nonce', 'nonce');
        
        $product_id = intval($_POST['product_id']);
        $config = json_decode(stripslashes($_POST['config']), true);
        
        require_once MEDIA_LAB_WC_PATH . 'inc/configurator/class-price-calculator.php';
        $calculator = new MediaLab_Price_Calculator($product_id);
        
        $price_breakdown = $calculator->get_breakdown($config);

        // Serverseitig berechnete Staffelpreise ergänzen (siehe
        // class-price-calculator.php, get_tiers_with_prices()). Ersetzt die
        // bisherige rein client-seitige Berechnung in configurator.js
        // (calculateTierPrice()), die bei Bruttopreis-Eingabe UND
        // Anzeige-Einstellung 'incl' die Steuer doppelt aufschlug, weil sie
        // fälschlich die Anzeige- statt der Eingabe-Einstellung als
        // Kriterium nutzte (siehe BACKLOG.md).
        $price_breakdown['tiers_with_prices'] = $calculator->get_tiers_with_prices( $price_breakdown['subtotal'] );
        
        wp_send_json_success($price_breakdown);
    }
    
    public function ajax_upload_configurator_file() {
        check_ajax_referer('configurator_nonce', 'nonce');
        
        if (!isset($_FILES['file'])) {
            wp_send_json_error('Keine Datei hochgeladen');
        }
        
        $file = $_FILES['file'];
        $step_id = sanitize_text_field($_POST['step_id']);
        
        $allowed_types = array('pdf', 'ai', 'eps', 'png', 'jpg', 'jpeg');
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file_ext, $allowed_types)) {
            wp_send_json_error('Dateityp nicht erlaubt. Erlaubt: ' . implode(', ', $allowed_types));
        }
        
        $max_size = 10 * 1024 * 1024;
        if ($file['size'] > $max_size) {
            wp_send_json_error('Datei zu groß. Maximum: 10MB');
        }
        
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        
        $upload_overrides = array(
            'test_form' => false,
            'mimes' => array(
                'pdf' => 'application/pdf',
                'ai' => 'application/postscript',
                'eps' => 'application/postscript',
                'png' => 'image/png',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
            ),
        );
        
        $uploaded_file = wp_handle_upload($file, $upload_overrides);
        
        if (isset($uploaded_file['error'])) {
            wp_send_json_error($uploaded_file['error']);
        }
        
        $attachment_id = wp_insert_attachment(array(
            'post_mime_type' => $uploaded_file['type'],
            'post_title' => sanitize_file_name(pathinfo($file['name'], PATHINFO_FILENAME)),
            'post_content' => '',
            'post_status' => 'inherit',
        ), $uploaded_file['file']);
        
        if (is_wp_error($attachment_id)) {
            wp_send_json_error('Fehler beim Erstellen des Attachments');
        }
        
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attachment_data = wp_generate_attachment_metadata($attachment_id, $uploaded_file['file']);
        wp_update_attachment_metadata($attachment_id, $attachment_data);
        
        // Marker für den Upload-Cleanup-Cron (inc/inquiry/class-upload-cleanup.php).
        // Wird entfernt, sobald der Upload final einer Anfrage zugeordnet wird
        // (Inquiry_Engine::submit()) - nur dann greift der Cron NIE ein.
        update_post_meta($attachment_id, '_mlw_pending_upload', 1);
        update_post_meta($attachment_id, '_mlw_upload_timestamp', time());
        
        wp_send_json_success(array(
            'id' => $attachment_id,
            'url' => $uploaded_file['url'],
            'filename' => basename($uploaded_file['file']),
            'type' => $uploaded_file['type'],
        ));
    }
    
    /**
     * Verarbeitet die Konfigurator-Direktanfrage ("Anfrage senden" statt
     * "In den Warenkorb").
     *
     * Dünner Wrapper: baut aus der Konfiguration ein Engine-Item (inkl.
     * Konfigurationsanzeige, Preisaufschlüsselung und Datei-Attachments über
     * die wiederverwendbaren Helper get_config_display_array()/
     * get_price_breakdown()/get_attachment_ids_from_config()) und übergibt
     * an die zentrale Inquiry-Engine. Ersetzt die frühere, hier fest
     * verdrahtete Klartext-wp_mail()-Logik.
     */
    public function ajax_configurator_inquiry() {
        check_ajax_referer('configurator_nonce', 'nonce');
        
        $product_id = intval($_POST['product_id']);
        $config     = json_decode(stripslashes($_POST['config']), true);
        $contact    = json_decode(stripslashes($_POST['contact']), true);
        
        $product = wc_get_product($product_id);
        if (!$product) {
            wp_send_json_error('Produkt nicht gefunden');
        }

        $config = is_array($config) ? $config : [];
        $contact = is_array($contact) ? $contact : [];

        $item = [
            'product_id'      => $product_id,
            'quantity'        => isset($config['quantity']) ? max(1, intval($config['quantity'])) : 1,
            'name'            => $product->get_name(),
            'config'          => $config,
            'config_display'  => $this->get_config_display_array($product_id, $config),
            'price_breakdown' => $this->get_price_breakdown($product_id, $config),
            'attachments'     => $this->get_attachment_ids_from_config($config),
        ];

        // Kontaktdaten: Basisfelder aus dem JS-Objekt (siehe configurator.js sendInquiry())
        // + alle konfigurierten Zusatzfelder, die dort bereits generisch mitgeschickt werden.
        $contact_data = [
            'name'            => sanitize_text_field( $contact['name']    ?? '' ),
            'email'           => sanitize_email( $contact['email']        ?? '' ),
            'phone'           => sanitize_text_field( $contact['phone']   ?? '' ),
            'message'         => sanitize_textarea_field( $contact['message'] ?? '' ),
            'privacy_consent' => ! empty( $contact['privacy_consent'] ),
        ];
        if ( class_exists( 'MediaLab_Inquiry_Settings' ) ) {
            foreach ( MediaLab_Inquiry_Settings::get_form_fields() as $field ) {
                $key = $field['field_key'] ?? '';
                if ( $key && isset( $contact[ $key ] ) ) {
                    $raw = $contact[ $key ];
                    $contact_data[ $key ] = is_array( $raw ) ? array_map( 'sanitize_text_field', $raw ) : sanitize_text_field( (string) $raw );
                }
            }
        }

        $result = MediaLab_Inquiry_Engine::submit( [ $item ], $contact_data, 'configurator' );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        wp_send_json_success( MediaLab_Inquiry_Settings::wording( 'success' ) );
    }
    
    /**
     * Verschiebe Tabs (Beschreibung, Rezensionen) VOR den Konfigurator
     */
    /**
     * Verwende WooCommerce Standard Placeholder für Produkte ohne Bild
     */
    public function custom_placeholder_image($src) {
        // WooCommerce Standard Placeholder
        return WC()->plugin_url() . '/assets/images/placeholder.png';
    }
    
    /**
     * Ändere Placeholder URL zu 768x768 Version
     */
    public function replace_placeholder_image($src) {
        // Wenn der alte 600x600 Placeholder verwendet wird, ersetze ihn
        if (strpos($src, 'woocommerce-placeholder-600x600.webp') !== false) {
            return content_url('uploads/woocommerce-placeholder-768x768.webp');
        }
        return $src;
    }
    
    /**
     * Gibt die Produkt-Rezensionen für konfigurierbare Produkte aus - AUSSERHALB
     * von .summary (voller Breite unterhalb des Konfigurators), da dieser Hook
     * ('woocommerce_after_single_product_summary') erst NACH dem Schließen von
     * .summary feuert. Für nicht-konfigurierbare Produkte unverändert (Theme
     * kümmert sich dort selbst darum, hier keine doppelte Ausgabe).
     */
    public function render_reviews_for_configurable() {
        global $product;
        if (!$product || !$this->is_configurable($product->get_id())) {
            return;
        }

        if (comments_open() || get_comments_number()) {
            echo '<div class="product-reviews-section" style="margin-top: 3rem;">';
            comments_template();
            echo '</div>';
        }
    }

    public function move_tabs_before_configurator() {
        global $product;
        
        if (!$this->is_configurable($product)) {
            return;
        }
        
        // Entferne Tabs von Standard-Position (nach add_to_cart)
        remove_action('woocommerce_after_single_product_summary', 'woocommerce_output_product_data_tabs', 10);
        
        // Zeige Tabs VOR dem Konfigurator
        woocommerce_output_product_data_tabs();
    }
    
    public function enqueue_scripts() {
        if (!is_product()) return;

        // WICHTIG: global $product ist am 'wp_enqueue_scripts'-Hook (feuert VOR
        // der eigentlichen Content-Loop) bei WooCommerce typischerweise noch
        // NICHT gesetzt - das passiert erst später während the_post() in der
        // Loop selbst. Daher zuverlässig über die Query-ID laden statt über
        // den globalen $product, der hier unzuverlässig/leer sein kann.
        $product = wc_get_product( get_queried_object_id() );

        if (!is_a($product, 'WC_Product')) {
            return;
        }
        
        if (!$this->is_configurable($product->get_id())) return;
        
        // Alpine.js - OHNE Abhängigkeiten, mit defer
        wp_enqueue_script(
            'alpine-js',
            MEDIA_LAB_WC_URL . 'assets/js/alpine.min.js',
            array(),
            '3.14.1',
            false // Footer, aber VOR configurator
        );
        
        // Configurator NACH Alpine
        wp_enqueue_script(
            'medialab-configurator',
            MEDIA_LAB_WC_URL . 'assets/js/configurator.js',
            array('jquery'),
            time(), // Cache buster
            true
        );
        
        // Defer für Alpine via Filter
        add_filter('script_loader_tag', function($tag, $handle) {
            if ($handle === 'alpine-js') {
                return str_replace(' src', ' defer src', $tag);
            }
            return $tag;
        }, 10, 2);
        
        wp_localize_script('medialab-configurator', 'configuratorData', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('configurator_nonce'),
            'product_id' => $product->get_id(),
            'cart_url' => wc_get_cart_url(),
            // Damit sendInquiry() (configurator.js) die dynamisch konfigurierten
            // Zusatzfelder generisch mitsenden kann, ohne sie hier hart zu verdrahten.
            'extraFieldKeys' => class_exists( 'MediaLab_Inquiry_Settings' )
                ? array_values( array_filter( array_map( fn( $f ) => $f['field_key'] ?? '', MediaLab_Inquiry_Settings::get_form_fields() ) ) )
                : array(),
            // Teilmenge der obigen Liste, die als Pflichtfeld markiert ist -
            // für die client-seitige Validierung vor "Zur Zusammenfassung"
            // (siehe isStepValid() in configurator.js).
            'requiredExtraFieldKeys' => class_exists( 'MediaLab_Inquiry_Settings' )
                ? array_values( array_filter( array_map( fn( $f ) => ! empty( $f['required'] ) ? ( $f['field_key'] ?? '' ) : '', MediaLab_Inquiry_Settings::get_form_fields() ) ) )
                : array(),
            'privacyRequired' => class_exists( 'MediaLab_Inquiry_Settings' ) ? MediaLab_Inquiry_Settings::privacy_required() : false,
            // Für den "Zur Wunschliste hinzufügen"-Button im Wizard (eigener Nonce,
            // da die Wunschliste ein separater Ajax-Namespace ist, siehe class-ajax.php).
            'wishlistNonce'   => wp_create_nonce( class_exists( 'MediaLab_Wishlist_Ajax' ) ? MediaLab_Wishlist_Ajax::NONCE_ACTION : 'mlw_wishlist_nonce' ),
            'wishlistAddLabel' => class_exists( 'MediaLab_Inquiry_Settings' ) ? MediaLab_Inquiry_Settings::wording( 'add_button' ) : 'Zur Wunschliste hinzufügen',
        ));
        
        wp_enqueue_style(
            'medialab-configurator',
            MEDIA_LAB_WC_URL . 'assets/css/configurator.css',
            array(),
            time() // dynamischer Cache-Buster, analog zu den JS-Enqueues oben - statische '1.0.0' verhinderte zuverlässiges Ausliefern von CSS-Änderungen
        );
    }
}

// Init
MediaLab_Product_Configurator::get_instance();
