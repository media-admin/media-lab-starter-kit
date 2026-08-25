<?php
/**
 * Ajax-Endpunkte der Wunschliste.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MediaLab_Wishlist_Ajax {

    const NONCE_ACTION = 'mlw_wishlist_nonce';

    public static function init(): void {
        $actions = [
            'mlw_wishlist_add'               => 'add',
            'mlw_wishlist_remove'            => 'remove',
            'mlw_wishlist_remove_by_product' => 'remove_by_product',
            'mlw_wishlist_update_qty'        => 'update_qty',
            'mlw_wishlist_get'               => 'get',
            'mlw_wishlist_submit'            => 'submit',
            'mlw_wishlist_bulk_add_to_cart'  => 'bulk_add_to_cart',
        ];
        foreach ( $actions as $action => $method ) {
            add_action( "wp_ajax_{$action}",        [ __CLASS__, $method ] );
            add_action( "wp_ajax_nopriv_{$action}", [ __CLASS__, $method ] );
        }
    }

    // ── Hinzufügen ───────────────────────────────────────────────────────────

    public static function add(): void {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );

        $product_id = (int) ( $_POST['product_id'] ?? 0 );
        $quantity   = max( 1, (int) ( $_POST['quantity'] ?? 1 ) );

        $config      = self::decode_json( $_POST['config']      ?? '' );
        $attachments = self::decode_json( $_POST['attachments'] ?? '' );

        $config_display  = null;
        $price_breakdown = null;

        if ( $config && is_array( $config ) && class_exists( 'MediaLab_Product_Configurator' ) ) {
            $configurator    = MediaLab_Product_Configurator::get_instance();
            $config_display  = $configurator->get_config_display_array( $product_id, $config );
            $price_breakdown = $configurator->get_price_breakdown( $product_id, $config );
        }

        $result = MediaLab_Wishlist_Storage::add( [
            'product_id'      => $product_id,
            'quantity'        => $quantity,
            'config'          => $config,
            'config_display'  => $config_display,
            'price_breakdown' => $price_breakdown,
            'attachments'     => is_array( $attachments ) ? array_map( 'intval', $attachments ) : [],
        ] );

        if ( is_array( $config ) && ( ! empty( $config['customer_name'] ) || ! empty( $config['customer_email'] ) ) ) {
            MediaLab_Wishlist_Storage::save_last_contact( [
                'name'    => $config['customer_name']  ?? '',
                'email'   => $config['customer_email'] ?? '',
                'phone'   => $config['customer_phone'] ?? '',
                'company' => $config['company']        ?? '',
                'message' => sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) ),
            ] );
        }

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        wp_send_json_success( [
            'items'            => MediaLab_Wishlist_Storage::get_items_for_display(),
            'count'            => MediaLab_Wishlist_Storage::count(),
            'grand_total_html' => wc_price( MediaLab_Wishlist_Storage::get_grand_total() ),
        ] );
    }

    // ── Entfernen ────────────────────────────────────────────────────────────

    public static function remove(): void {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );

        $item_id = sanitize_text_field( $_POST['item_id'] ?? '' );
        if ( ! $item_id ) wp_send_json_error( [ 'message' => __( 'Ungültiger Wunschlisten-Eintrag.', 'media-lab-woocommerce' ) ] );

        MediaLab_Wishlist_Storage::remove( $item_id );

        wp_send_json_success( [
            'items'            => MediaLab_Wishlist_Storage::get_items_for_display(),
            'count'            => MediaLab_Wishlist_Storage::count(),
            'grand_total_html' => wc_price( MediaLab_Wishlist_Storage::get_grand_total() ),
        ] );
    }

    /**
     * Entfernt einen Wunschlisten-Eintrag anhand der Produkt-ID statt der
     * internen item_id - für den Toggle-Klick auf der Produktkarte (Herz-
     * Icon), wo nur die Produkt-ID bekannt ist, nicht die item_id.
     * Konfigurierte Varianten desselben Produkts bleiben unberührt (gleiche
     * Abgrenzung wie MediaLab_Wishlist_Storage::has_product()).
     */
    public static function remove_by_product(): void {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );

        $product_id = (int) ( $_POST['product_id'] ?? 0 );
        if ( ! $product_id ) wp_send_json_error( [ 'message' => __( 'Ungültiges Produkt.', 'media-lab-woocommerce' ) ] );

        foreach ( MediaLab_Wishlist_Storage::get_items() as $item ) {
            if ( (int) ( $item['product_id'] ?? 0 ) === $product_id && empty( $item['config'] ) ) {
                MediaLab_Wishlist_Storage::remove( $item['item_id'] );
                break;
            }
        }

        wp_send_json_success( [
            'items'            => MediaLab_Wishlist_Storage::get_items_for_display(),
            'count'            => MediaLab_Wishlist_Storage::count(),
            'grand_total_html' => wc_price( MediaLab_Wishlist_Storage::get_grand_total() ),
        ] );
    }

    // ── Menge ändern ─────────────────────────────────────────────────────────

    public static function update_qty(): void {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );

        $item_id  = sanitize_text_field( $_POST['item_id'] ?? '' );
        $quantity = (int) ( $_POST['quantity'] ?? 1 );
        if ( ! $item_id ) wp_send_json_error( [ 'message' => __( 'Ungültiger Wunschlisten-Eintrag.', 'media-lab-woocommerce' ) ] );

        MediaLab_Wishlist_Storage::update_quantity( $item_id, $quantity );

        wp_send_json_success( [
            'items'            => MediaLab_Wishlist_Storage::get_items_for_display(),
            'count'            => MediaLab_Wishlist_Storage::count(),
            'grand_total_html' => wc_price( MediaLab_Wishlist_Storage::get_grand_total() ),
        ] );
    }

    // ── Laden (z.B. für Wunschlisten-Seite / Widget-Refresh) ────────────────

    public static function get(): void {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );

        wp_send_json_success( [
            'items'            => MediaLab_Wishlist_Storage::get_items_for_display(),
            'count'            => MediaLab_Wishlist_Storage::count(),
            'grand_total_html' => wc_price( MediaLab_Wishlist_Storage::get_grand_total() ),
        ] );
    }

    // ── Gesammelt in den Warenkorb (Shop-Modus, kein Catalog Mode) ──────────

    /**
     * Fügt mehrere ausgewählte Wunschlisten-Artikel gesammelt dem WooCommerce-
     * Warenkorb hinzu und entfernt sie im selben Zug aus der Wunschliste -
     * "In den Warenkorb" verschiebt den Artikel, statt ihn dort scheinbar
     * unverändert liegen zu lassen. Wird sowohl vom einzelnen
     * "In den Warenkorb"-Button pro Zeile (ein Artikel) als auch vom
     * Sammel-Button (mehrere Artikel) genutzt - siehe wishlist.js,
     * handleBulkAdd().
     *
     * Übersprungen werden: konfigurierte Artikel, variable Produkte (Größen-
     * /Farbvarianten - brauchen eine explizite Auswahl, die wir hier nicht
     * haben) und nicht (mehr) ausreichend vorrätige Artikel.
     */
    public static function bulk_add_to_cart(): void {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );

        $item_ids = isset( $_POST['item_ids'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['item_ids'] ) ) : [];
        if ( empty( $item_ids ) ) {
            wp_send_json_error( [ 'message' => __( 'Keine Artikel ausgewählt.', 'media-lab-woocommerce' ) ] );
        }

        $added_ids   = [];
        $skipped_ids = [];

        foreach ( MediaLab_Wishlist_Storage::get_items() as $item ) {
            if ( ! in_array( $item['item_id'], $item_ids, true ) ) continue;

            if ( ! empty( $item['config'] ) ) { $skipped_ids[] = $item['item_id']; continue; }

            $product   = wc_get_product( $item['product_id'] );
            $is_simple = $product && $product->is_type( 'simple' );
            $in_stock  = $is_simple && $product->is_in_stock() && ( $product->get_stock_quantity() === null || $product->get_stock_quantity() > 0 );

            if ( ! $in_stock ) { $skipped_ids[] = $item['item_id']; continue; }

            $result = WC()->cart->add_to_cart( $item['product_id'], max( 1, (int) $item['quantity'] ) );

            if ( $result ) {
                MediaLab_Wishlist_Storage::remove( $item['item_id'] );
                $added_ids[] = $item['item_id'];
            } else {
                $skipped_ids[] = $item['item_id'];
            }
        }

        wp_send_json_success( [
            'added'       => count( $added_ids ),
            'skipped'     => count( $skipped_ids ),
            'added_ids'   => $added_ids,
            'skipped_ids' => $skipped_ids,
            'cart_url'    => wc_get_cart_url(),
            'count'       => MediaLab_Wishlist_Storage::count(),
        ] );
    }

    // ── Anfrage absenden ─────────────────────────────────────────────────────

    public static function submit(): void {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );

        if ( function_exists( 'medialab_honeypot_check' ) ) {
            $hp_check = medialab_honeypot_check();
            if ( is_wp_error( $hp_check ) ) {
                wp_send_json_error( [ 'message' => $hp_check->get_error_message(), 'code' => $hp_check->get_error_code() ], 400 );
            }
        }

        $items = MediaLab_Wishlist_Storage::get_items();
        if ( empty( $items ) ) {
            wp_send_json_error( [ 'message' => __( 'Ihre Wunschliste ist leer.', 'media-lab-woocommerce' ) ] );
        }

        $engine_items = [];
        foreach ( $items as $item ) {
            $product = wc_get_product( $item['product_id'] );
            $engine_items[] = [
                'product_id'      => $item['product_id'],
                'quantity'        => $item['quantity'],
                'name'            => $product ? $product->get_name() : null,
                'config'          => $item['config']          ?? null,
                'config_display'  => $item['config_display']  ?? null,
                'price_breakdown' => $item['price_breakdown'] ?? null,
                'attachments'     => $item['attachments']     ?? [],
            ];
        }

        $contact = [
            'name'            => sanitize_text_field( $_POST['name']    ?? '' ),
            'email'           => sanitize_email( $_POST['email']        ?? '' ),
            'phone'           => sanitize_text_field( $_POST['phone']   ?? '' ),
            'message'         => sanitize_textarea_field( $_POST['message'] ?? '' ),
            'privacy_consent' => ! empty( $_POST['privacy_consent'] ),
        ];
        foreach ( MediaLab_Inquiry_Settings::get_form_fields() as $field ) {
            $key = $field['field_key'] ?? '';
            if ( $key && isset( $_POST[ $key ] ) ) {
                $raw = wp_unslash( $_POST[ $key ] );
                $contact[ $key ] = is_array( $raw ) ? array_map( 'sanitize_text_field', $raw ) : sanitize_text_field( $raw );
            }
        }

        $result = MediaLab_Inquiry_Engine::submit( $engine_items, $contact, 'wishlist' );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        MediaLab_Wishlist_Storage::clear();

        wp_send_json_success( [
            'items'            => MediaLab_Wishlist_Storage::get_items_for_display(),
            'count'            => MediaLab_Wishlist_Storage::count(),
            'grand_total_html' => wc_price( MediaLab_Wishlist_Storage::get_grand_total() ),
        ] );
    }

    // ── Helper ───────────────────────────────────────────────────────────────

    private static function decode_json( $raw ) {
        if ( ! $raw || ! is_string( $raw ) ) return null;
        $decoded = json_decode( stripslashes( $raw ), true );
        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }
}

MediaLab_Wishlist_Ajax::init();
