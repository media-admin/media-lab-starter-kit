<?php
/**
 * Zentrale Engine für alle Anfragen (Cart, Konfigurator, Wunschliste).
 *
 * Erwartetes Item-Format (pro Eintrag in $items):
 *   [
 *       'product_id'      => int,
 *       'quantity'        => int,
 *       'name'            => string (optional, wird sonst aus product_id ermittelt),
 *       'config'          => array|null   Rohe Konfigurator-Antworten (step_id => Wert),
 *       'config_display'  => array|null   Label => lesbarer Wert, fürs Mail-Rendering
 *       'price_breakdown' => array|null   Wie in class-price-calculator.php
 *       'attachments'     => int[]|null   Attachment-IDs von Datei-Uploads (Konfigurator)
 *   ]
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MediaLab_Inquiry_Engine {

    /**
     * Validiert und verarbeitet eine Anfrage vollständig.
     *
     * @param array  $items       Liste der angefragten Produkte, siehe Datei-Kommentar.
     * @param array  $contact     Kontaktdaten aus dem Formular: name, email, phone, message, + konfigurierte Zusatzfelder.
     * @param string $source      'cart' | 'configurator' | 'wishlist'
     *
     * @return int|WP_Error  Post-ID der Anfrage oder WP_Error bei Validierungsfehlern.
     */
    public static function submit( array $items, array $contact, string $source ) {
        if ( empty( $items ) ) {
            return new WP_Error( 'mlw_no_items', __( 'Es wurden keine Produkte angegeben.', 'media-lab-woocommerce' ) );
        }

        $name  = sanitize_text_field( $contact['name']  ?? '' );
        $email = sanitize_email( $contact['email'] ?? '' );
        $phone = sanitize_text_field( $contact['phone'] ?? '' );
        $msg   = sanitize_textarea_field( $contact['message'] ?? '' );

        if ( ! $name )  return new WP_Error( 'mlw_missing_name',  __( 'Bitte geben Sie Ihren Namen an.', 'media-lab-woocommerce' ) );
        if ( ! is_email( $email ) ) return new WP_Error( 'mlw_invalid_email', __( 'Bitte geben Sie eine gültige E-Mail-Adresse an.', 'media-lab-woocommerce' ) );

        // Datenschutz-Zustimmung
        if ( MediaLab_Inquiry_Settings::privacy_required() && empty( $contact['privacy_consent'] ) ) {
            return new WP_Error( 'mlw_privacy_required', __( 'Bitte stimmen Sie der Datenschutzerklärung zu.', 'media-lab-woocommerce' ) );
        }

        // Konfigurierbare Zusatzfelder validieren (Label in aktueller Sprache aufgelöst)
        $extra_fields = [];
        foreach ( MediaLab_Inquiry_Settings::get_form_fields_localized() as $field ) {
            $key   = $field['field_key'] ?? '';
            if ( ! $key ) continue;
            $value = $contact[ $key ] ?? '';

            if ( ! empty( $field['required'] ) && ( $value === '' || $value === null ) ) {
                /* translators: %s: Feldname aus der Formularfeld-Konfiguration */
                return new WP_Error( 'mlw_missing_field', sprintf( __( 'Bitte füllen Sie das Feld „%s" aus.', 'media-lab-woocommerce' ), $field['label'] ?? $key ) );
            }

            if ( $field['field_type'] === 'checkbox' ) {
                $extra_fields[ $key ] = ! empty( $value );
            } elseif ( is_array( $value ) ) {
                $extra_fields[ $key ] = array_map( 'sanitize_text_field', $value );
            } else {
                $extra_fields[ $key ] = sanitize_text_field( (string) $value );
            }
        }

        // Items normalisieren (Produktname nachladen, falls nicht mitgeliefert)
        $normalized_items = [];
        foreach ( $items as $item ) {
            $product_id = isset( $item['product_id'] ) ? (int) $item['product_id'] : 0;
            $product    = $product_id ? wc_get_product( $product_id ) : null;

            $normalized_items[] = [
                'product_id'      => $product_id,
                'quantity'        => max( 1, (int) ( $item['quantity'] ?? 1 ) ),
                'name'            => $item['name'] ?? ( $product ? $product->get_name() : __( 'Unbekanntes Produkt', 'media-lab-woocommerce' ) ),
                'sku'             => $product ? $product->get_sku() : '',
                'config'          => $item['config']          ?? null,
                'config_display'  => $item['config_display']  ?? null,
                'price_breakdown' => $item['price_breakdown'] ?? null,
                'attachments'     => array_map( 'intval', $item['attachments'] ?? [] ),
            ];
        }

        $data = [
            'name'         => $name,
            'email'        => $email,
            'phone'        => $phone,
            'message'      => $msg,
            'items'        => $normalized_items,
            'extra_fields' => $extra_fields,
            'source'       => $source,
        ];

        // Hook: vor dem Speichern (z.B. für projektspezifische Erweiterungen)
        $data = apply_filters( 'mlw_before_save_inquiry', $data );
        if ( ! $data ) {
            return new WP_Error( 'mlw_cancelled', __( 'Die Anfrage wurde abgebrochen.', 'media-lab-woocommerce' ) );
        }

        // ── Anfrage als CPT speichern ────────────────────────────────────────
        // Hinweis: Der Post-Titel ist ein interner Backend-Bezeichner (Admin-Sprache der Seite),
        // KEIN an den Kunden gerichteter Inhalt - bleibt daher bewusst unübersetzt/statisch.
        $title = sprintf( 'Anfrage – %s – %s', $data['name'], date_i18n( 'd.m.Y H:i' ) );
        $inquiry_id = wp_insert_post( [
            'post_type'   => 'mlw_inquiry',
            'post_status' => 'mlw-open',
            'post_title'  => sanitize_text_field( $title ),
        ] );

        if ( is_wp_error( $inquiry_id ) ) {
            return new WP_Error( 'mlw_save_failed', __( 'Die Anfrage konnte nicht gespeichert werden.', 'media-lab-woocommerce' ) );
        }

        update_post_meta( $inquiry_id, 'mlw_inquiry_source',       $data['source'] );
        update_post_meta( $inquiry_id, 'mlw_inquiry_name',         $data['name'] );
        update_post_meta( $inquiry_id, 'mlw_inquiry_email',        $data['email'] );
        update_post_meta( $inquiry_id, 'mlw_inquiry_phone',        $data['phone'] );
        update_post_meta( $inquiry_id, 'mlw_inquiry_message',      $data['message'] );
        update_post_meta( $inquiry_id, 'mlw_inquiry_items',        $data['items'] );
        update_post_meta( $inquiry_id, 'mlw_inquiry_extra_fields', $data['extra_fields'] );

        // Datei-Uploads final der Anfrage zuordnen (siehe class-upload-cleanup.php):
        // Marker "pending" entfernen, damit der Cleanup-Cron sie nie mehr anfasst.
        foreach ( $data['items'] as $item ) {
            foreach ( $item['attachments'] as $att_id ) {
                delete_post_meta( $att_id, '_mlw_pending_upload' );
                update_post_meta( $att_id, '_mlw_inquiry_id', $inquiry_id );
                // Attachment inhaltlich dem Anfrage-Post zuordnen (auch in der Mediathek sichtbar)
                wp_update_post( [ 'ID' => $att_id, 'post_parent' => $inquiry_id ] );
            }
        }

        // ── Versand über alle aktiven Kanäle ─────────────────────────────────
        MediaLab_Inquiry_Channels::dispatch( $inquiry_id, $data );

        do_action( 'mlw_after_save_inquiry', $inquiry_id, $data );

        return $inquiry_id;
    }
}
