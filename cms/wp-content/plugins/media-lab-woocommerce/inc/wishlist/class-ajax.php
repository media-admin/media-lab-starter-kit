<?php
/**
 * Ajax-Endpunkte der Wunschliste.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MediaLab_Wishlist_Ajax {

    const NONCE_ACTION = 'mlw_wishlist_nonce';

    public static function init(): void {
        $actions = [
            'mlw_wishlist_add'          => 'add',
            'mlw_wishlist_remove'       => 'remove',
            'mlw_wishlist_update_qty'   => 'update_qty',
            'mlw_wishlist_get'          => 'get',
            'mlw_wishlist_submit'       => 'submit',
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

        // Konfigurator-Daten sind optional (nur bei configurable Produkten mitgeliefert).
        $config      = self::decode_json( $_POST['config']      ?? '' );
        $attachments = self::decode_json( $_POST['attachments'] ?? '' );

        $config_display  = null;
        $price_breakdown = null;

        // WICHTIG: Bei konfigurierbaren Produkten werden Anzeige & Preis NICHT
        // aus dem Request übernommen (Client könnte manipulierte Werte senden),
        // sondern serverseitig aus der rohen Konfiguration neu berechnet - über
        // dieselben Helper, die auch Cart-Anzeige und Konfigurator-Anfrage nutzen
        // (siehe class-configurator.php: get_config_display_array()/get_price_breakdown()).
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

        // Falls die Konfiguration bereits Kontaktdaten enthält (aus dem
        // Konfigurator-Kontaktdaten-Step), für die Vorausfüllung des
        // Absende-Formulars auf der Wunschlisten-Seite merken.
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

    // ── Anfrage absenden ─────────────────────────────────────────────────────

    public static function submit(): void {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );

        // Honeypot-Prüfung (Core-Plugin), analog zum Booking-Tool.
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

        // Items fürs Engine-Format aufbereiten (Produktname nachladen, damit sie
        // auch dann in der Mail steht, wenn das Produkt später gelöscht wird).
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

        // Kontaktdaten: Basisfelder + alle konfigurierten Zusatzfelder generisch durchreichen.
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

        // Wunschliste nach erfolgreichem Versand leeren.
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
