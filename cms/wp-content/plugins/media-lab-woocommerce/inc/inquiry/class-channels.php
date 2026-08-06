<?php
/**
 * Versand an konfigurierbare Kanäle (WhatsApp-Link, Webhook).
 * E-Mail wird direkt über MediaLab_Inquiry_Mail::send() abgewickelt.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MediaLab_Inquiry_Channels {

    /**
     * Verarbeitet alle aktiven Kanäle für eine Anfrage.
     *
     * @return array Liste erfolgreich verarbeiteter Kanäle, z.B. [ 'email', 'webhook' ]
     */
    public static function dispatch( int $inquiry_id, array $data ): array {
        $active = MediaLab_Inquiry_Settings::get_active_channels();
        $sent   = [];

        foreach ( $active as $channel ) {
            switch ( $channel ) {
                case 'email':
                    MediaLab_Inquiry_Mail::send( $inquiry_id, $data );
                    $sent[] = 'email';
                    break;

                case 'whatsapp':
                    // WhatsApp-Link kann nicht "gesendet" werden – wir generieren ihn nur
                    // und speichern ihn am Post, damit er im Frontend/Backend nutzbar ist.
                    $url = self::build_whatsapp_url( $inquiry_id, $data );
                    if ( $url ) {
                        update_post_meta( $inquiry_id, 'mlw_whatsapp_url', $url );
                        $sent[] = 'whatsapp';
                    }
                    break;

                case 'webhook':
                    if ( self::send_webhook( $inquiry_id, $data ) ) {
                        $sent[] = 'webhook';
                    }
                    break;
            }
        }

        update_post_meta( $inquiry_id, 'mlw_inquiry_channels_sent', $sent );
        return $sent;
    }

    // ── WhatsApp ─────────────────────────────────────────────────────────────

    public static function build_whatsapp_url( int $inquiry_id, array $data ): string {
        $config = MediaLab_Inquiry_Settings::channel_config( 'whatsapp' );
        $number = preg_replace( '/[^0-9]/', '', (string) ( $config['number'] ?? '' ) );
        if ( ! $number ) return '';

        $text = MediaLab_Inquiry_Mail::replace_placeholders( $config['template'], $inquiry_id, $data );
        $text = wp_strip_all_tags( str_replace( [ '<br>', '<br/>', '<br />' ], "\n", $text ) );

        return 'https://wa.me/' . $number . '?text=' . rawurlencode( $text );
    }

    // ── Webhook ──────────────────────────────────────────────────────────────

    private static function send_webhook( int $inquiry_id, array $data ): bool {
        $config = MediaLab_Inquiry_Settings::channel_config( 'webhook' );
        $url    = $config['url'] ?? '';
        if ( ! $url ) return false;

        $payload = [
            'inquiry_id' => $inquiry_id,
            'source'     => $data['source']  ?? '',
            'name'       => $data['name']    ?? '',
            'email'      => $data['email']   ?? '',
            'phone'      => $data['phone']   ?? '',
            'message'    => $data['message'] ?? '',
            'items'      => array_map( function ( $item ) {
                return [
                    'product_id' => $item['product_id'] ?? null,
                    'name'       => $item['name']        ?? '',
                    'quantity'   => $item['quantity']     ?? 1,
                    'config'     => $item['config_display'] ?? null,
                ];
            }, $data['items'] ?? [] ),
            'extra_fields' => $data['extra_fields'] ?? [],
            'site'         => home_url(),
            'timestamp'    => current_time( 'mysql' ),
        ];

        $headers = [ 'Content-Type' => 'application/json' ];
        if ( ! empty( $config['secret'] ) ) {
            $headers['X-MLW-Secret'] = $config['secret'];
        }

        $response = wp_remote_post( $url, [
            'headers' => $headers,
            'body'    => wp_json_encode( $payload ),
            'timeout' => 10,
        ] );

        $success = ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) < 300;

        update_post_meta( $inquiry_id, 'mlw_webhook_last_response', [
            'success' => $success,
            'code'    => is_wp_error( $response ) ? $response->get_error_message() : wp_remote_retrieve_response_code( $response ),
            'time'    => current_time( 'mysql' ),
        ] );

        return $success;
    }
}
