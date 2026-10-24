<?php
/**
 * Backend-Ansicht einer Anfrage (Post-Typ mlw_inquiry).
 *
 * - Kasten "Anfrage-Details": Kunde, Nachricht, Kanäle und Produkte mit Auswahl und Preis (nur lesen).
 * - Kasten "Status": ersetzt "Veröffentlichen". Anfragen haben eigene Status (mlw-open, mlw-in-progress, mlw-done,
 *   mlw-archived). Würde eine Anfrage "veröffentlicht", verschwände sie aus der Liste (siehe class-cpt.php).
 * - Spalte "Kunde" in der Liste.
 *
 * Gespeichert wird weiterhin in den Meta-Feldern mlw_inquiry_* (siehe class-inquiry-engine.php).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MediaLab_Inquiry_Admin {

    private const STATUSES = [ 'mlw-open', 'mlw-in-progress', 'mlw-done', 'mlw-archived' ];

    public static function init(): void {
        add_action( 'add_meta_boxes_mlw_inquiry', [ __CLASS__, 'add_boxes' ] );
        add_filter( 'wp_insert_post_data', [ __CLASS__, 'keep_inquiry_status' ], 10, 2 );
        add_filter( 'manage_mlw_inquiry_posts_columns', [ __CLASS__, 'columns' ], 20 );
        add_action( 'manage_mlw_inquiry_posts_custom_column', [ __CLASS__, 'column_content' ], 10, 2 );
    }

    // ── Kästen ──────────────────────────────────────────────────────────────

    public static function add_boxes( $post ): void {
        remove_meta_box( 'submitdiv', 'mlw_inquiry', 'side' );
        add_meta_box( 'mlw_inquiry_status', __( 'Status', 'media-lab-woocommerce' ), [ __CLASS__, 'render_status_box' ], 'mlw_inquiry', 'side', 'high' );
        add_meta_box( 'mlw_inquiry_details', __( 'Anfrage-Details', 'media-lab-woocommerce' ), [ __CLASS__, 'render_details_box' ], 'mlw_inquiry', 'normal', 'high' );
    }

    public static function render_status_box( $post ): void {
        $current = in_array( $post->post_status, self::STATUSES, true ) ? $post->post_status : 'mlw-open';
        wp_nonce_field( 'mlw_inquiry_status', 'mlw_inquiry_status_nonce' );

        echo '<p><label for="mlw_inquiry_status" class="screen-reader-text">' . esc_html__( 'Status', 'media-lab-woocommerce' ) . '</label>';
        echo '<select name="mlw_inquiry_status" id="mlw_inquiry_status" style="width:100%">';
        foreach ( self::STATUSES as $slug ) {
            $obj = get_post_status_object( $slug );
            printf(
                '<option value="%s"%s>%s</option>',
                esc_attr( $slug ),
                selected( $current, $slug, false ),
                esc_html( $obj ? $obj->label : $slug )
            );
        }
        echo '</select></p>';

        if ( $post->post_date && $post->post_status !== 'auto-draft' ) {
            echo '<p class="description">' . esc_html( sprintf( __( 'Eingegangen am %s', 'media-lab-woocommerce' ), get_the_date( 'd.m.Y H:i', $post ) ) ) . '</p>';
        }

        echo '<div style="display:flex;justify-content:space-between;align-items:center;margin-top:12px">';
        if ( current_user_can( 'delete_post', $post->ID ) && $post->post_status !== 'auto-draft' ) {
            echo '<a class="submitdelete deletion" href="' . esc_url( get_delete_post_link( $post->ID ) ) . '">' . esc_html__( 'In den Papierkorb verschieben', 'media-lab-woocommerce' ) . '</a>';
        } else {
            echo '<span></span>';
        }
        submit_button( __( 'Speichern', 'media-lab-woocommerce' ), 'primary', 'save', false );
        echo '</div>';
    }

    public static function render_details_box( $post ): void {
        $m = static function ( string $key ) use ( $post ) {
            return get_post_meta( $post->ID, $key, true );
        };

        $source_labels = [
            'cart'         => __( 'Warenkorb', 'media-lab-woocommerce' ),
            'configurator' => __( 'Konfigurator', 'media-lab-woocommerce' ),
            'wishlist'     => __( 'Wunschliste', 'media-lab-woocommerce' ),
        ];
        $source   = (string) $m( 'mlw_inquiry_source' );
        $name     = (string) $m( 'mlw_inquiry_name' );
        $email    = (string) $m( 'mlw_inquiry_email' );
        $phone    = (string) $m( 'mlw_inquiry_phone' );
        $message  = (string) $m( 'mlw_inquiry_message' );
        $extra    = $m( 'mlw_inquiry_extra_fields' );
        $items    = $m( 'mlw_inquiry_items' );
        $channels = $m( 'mlw_inquiry_channels_sent' );
        $wa_url   = (string) $m( 'mlw_whatsapp_url' );

        echo '<table class="widefat striped"><tbody>';
        self::row( __( 'Quelle', 'media-lab-woocommerce' ), esc_html( $source_labels[ $source ] ?? ( $source ?: '–' ) ) );
        self::row( __( 'Name', 'media-lab-woocommerce' ), esc_html( $name ?: '–' ) );
        self::row( __( 'E-Mail', 'media-lab-woocommerce' ), $email ? '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>' : '–' );
        self::row(
            __( 'Telefon', 'media-lab-woocommerce' ),
            $phone ? '<a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ) . '">' . esc_html( $phone ) . '</a>' : '–'
        );
        if ( is_array( $extra ) ) {
            foreach ( $extra as $key => $value ) {
                $text = is_array( $value ) ? implode( ', ', array_map( 'strval', $value ) ) : (string) $value;
                self::row( (string) $key, esc_html( $text !== '' ? $text : '–' ) );
            }
        }
        self::row( __( 'Nachricht', 'media-lab-woocommerce' ), $message !== '' ? nl2br( esc_html( $message ) ) : '–' );

        $channel_html = is_array( $channels ) && $channels ? esc_html( implode( ', ', $channels ) ) : '–';
        if ( $wa_url !== '' ) {
            $channel_html .= ' &middot; <a href="' . esc_url( $wa_url ) . '" target="_blank" rel="noopener">' . esc_html__( 'WhatsApp-Text öffnen', 'media-lab-woocommerce' ) . '</a>';
        }
        self::row( __( 'Kanäle', 'media-lab-woocommerce' ), $channel_html );
        echo '</tbody></table>';

        if ( ! is_array( $items ) || ! $items ) {
            echo '<p>' . esc_html__( 'Keine Produkte an dieser Anfrage gespeichert.', 'media-lab-woocommerce' ) . '</p>';
            return;
        }

        echo '<h3>' . esc_html__( 'Produkte', 'media-lab-woocommerce' ) . '</h3>';
        foreach ( $items as $item ) {
            if ( ! is_array( $item ) ) continue;
            self::render_item( $item );
        }
    }

    private static function render_item( array $item ): void {
        $product_id = (int) ( $item['product_id'] ?? 0 );
        $title      = (string) ( $item['name'] ?? '' );
        $edit_link  = $product_id ? get_edit_post_link( $product_id ) : '';
        $head       = esc_html( $title !== '' ? $title : '#' . $product_id );
        if ( $edit_link ) {
            $head = '<a href="' . esc_url( $edit_link ) . '">' . $head . '</a>';
        }

        echo '<table class="widefat striped" style="margin-bottom:12px"><thead><tr><th colspan="2">' . $head . '</th></tr></thead><tbody>';

        if ( ! empty( $item['sku'] ) ) {
            self::row( __( 'Artikelnummer', 'media-lab-woocommerce' ), esc_html( (string) $item['sku'] ) );
        }
        if ( ! empty( $item['variation_id'] ) ) {
            self::row( __( 'Variante', 'media-lab-woocommerce' ), esc_html( '#' . (int) $item['variation_id'] ) );
        }
        self::row( __( 'Menge', 'media-lab-woocommerce' ), esc_html( (string) ( $item['quantity'] ?? '1' ) ) );

        if ( ! empty( $item['config_display'] ) && is_array( $item['config_display'] ) ) {
            foreach ( $item['config_display'] as $label => $value ) {
                $text = is_array( $value ) ? implode( ', ', array_map( 'strval', $value ) ) : (string) $value;
                self::row( (string) $label, esc_html( $text !== '' ? $text : '–' ) );
            }
        }

        $pb = $item['price_breakdown'] ?? null;
        if ( is_array( $pb ) && isset( $pb['total'] ) ) {
            $price = static function ( $value ): string {
                return function_exists( 'wc_price' ) ? wp_kses_post( wc_price( (float) $value ) ) : esc_html( number_format_i18n( (float) $value, 2 ) );
            };
            if ( isset( $pb['unit_price'] ) ) {
                self::row( __( 'Einzelpreis', 'media-lab-woocommerce' ), $price( $pb['unit_price'] ) );
            }
            if ( ! empty( $pb['tier_discount_percent'] ) ) {
                self::row( __( 'Staffelrabatt', 'media-lab-woocommerce' ), esc_html( (string) $pb['tier_discount_percent'] . ' %' ) );
            }
            self::row( __( 'Gesamt', 'media-lab-woocommerce' ), $price( $pb['total'] ) );
        }

        if ( ! empty( $item['attachments'] ) && is_array( $item['attachments'] ) ) {
            $links = [];
            foreach ( $item['attachments'] as $file ) {
                $url  = is_array( $file ) ? (string) ( $file['url'] ?? '' ) : (string) $file;
                $text = is_array( $file ) ? (string) ( $file['name'] ?? $file['filename'] ?? '' ) : '';
                if ( $url === '' ) continue;
                $links[] = '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( $text !== '' ? $text : basename( $url ) ) . '</a>';
            }
            if ( $links ) {
                self::row( __( 'Dateien', 'media-lab-woocommerce' ), implode( '<br>', $links ) );
            }
        }

        echo '</tbody></table>';
    }

    /** $html muss bereits escaped sein. */
    private static function row( string $label, string $html ): void {
        echo '<tr><th style="width:180px;text-align:left">' . esc_html( $label ) . '</th><td>' . $html . '</td></tr>';
    }

    // ── Status beim Speichern ───────────────────────────────────────────────

    /**
     * Das Standardformular würde eine Anfrage auf "Entwurf" oder "Veröffentlicht" setzen. Wir übernehmen stattdessen den
     * Wert aus dem Kasten "Status". Ohne unsere Auswahl (zum Beispiel Papierkorb, Programm-Aufrufe) ändert sich nichts.
     */
    public static function keep_inquiry_status( array $data, array $postarr ): array {
        if ( ( $data['post_type'] ?? '' ) !== 'mlw_inquiry' || ! is_admin() ) return $data;
        if ( ! isset( $_POST['mlw_inquiry_status'], $_POST['mlw_inquiry_status_nonce'] ) ) return $data;
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mlw_inquiry_status_nonce'] ) ), 'mlw_inquiry_status' ) ) return $data;

        $post_id = (int) ( $postarr['ID'] ?? 0 );
        if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) return $data;

        $chosen = sanitize_key( wp_unslash( $_POST['mlw_inquiry_status'] ) );
        $form_states = [ 'draft', 'publish', 'pending', 'future', 'private', 'auto-draft' ];
        if ( in_array( $chosen, self::STATUSES, true ) && in_array( $data['post_status'] ?? '', $form_states, true ) ) {
            $data['post_status'] = $chosen;
        }
        return $data;
    }

    // ── Liste ───────────────────────────────────────────────────────────────

    public static function columns( array $columns ): array {
        $new = [];
        foreach ( $columns as $key => $label ) {
            $new[ $key ] = $label;
            if ( $key === 'title' ) {
                $new['mlw_customer'] = __( 'Kunde', 'media-lab-woocommerce' );
            }
        }
        return $new;
    }

    public static function column_content( string $column, int $post_id ): void {
        if ( $column !== 'mlw_customer' ) return;
        $name  = (string) get_post_meta( $post_id, 'mlw_inquiry_name', true );
        $email = (string) get_post_meta( $post_id, 'mlw_inquiry_email', true );
        echo esc_html( $name !== '' ? $name : '–' );
        if ( $email !== '' ) {
            echo '<br><a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>';
        }
    }
}

if ( is_admin() ) {
    MediaLab_Inquiry_Admin::init();
}
