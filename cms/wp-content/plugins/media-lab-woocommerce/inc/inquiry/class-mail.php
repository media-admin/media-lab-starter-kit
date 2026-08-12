<?php
/**
 * Mail-Versand für Inquiry-Anfragen: Platzhalter-Ersetzung, HTML-Wrapper,
 * Produktlisten-Formatierung (inkl. Konfigurator-Konfiguration & Preisaufschlüsselung).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MediaLab_Inquiry_Mail {

    /**
     * Sendet Kunden- und Admin-Mail für eine Anfrage.
     *
     * @param int   $inquiry_id  Post-ID des mlw_inquiry.
     * @param array $data        Normalisierte Anfrage-Daten, siehe Inquiry_Engine::submit().
     */
    public static function send( int $inquiry_id, array $data ): void {
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo( 'name' ) . ' <' . get_option( 'admin_email' ) . '>',
        ];

        $attachments = self::collect_attachments( $data );

        // ── Kunden-Mail ──────────────────────────────────────────────────────
        if ( is_email( $data['email'] ?? '' ) ) {
            $tpl     = MediaLab_Inquiry_Settings::get_mail_template( 'customer' );
            $subject = wp_strip_all_tags( self::replace_placeholders( $tpl['subject'], $inquiry_id, $data ) );
            $body    = self::wrap_html( self::replace_placeholders( $tpl['template'], $inquiry_id, $data ) );
            wp_mail( $data['email'], $subject, $body, $headers );
        }

        // ── Admin-Mail ───────────────────────────────────────────────────────
        $config    = MediaLab_Inquiry_Settings::channel_config( 'email' );
        $recipient = $config['recipient'] ?? get_option( 'admin_email' );
        $tpl       = MediaLab_Inquiry_Settings::get_mail_template( 'admin' );
        $subject   = wp_strip_all_tags( self::replace_placeholders( $tpl['subject'], $inquiry_id, $data ) );
        $body      = self::wrap_html( self::replace_placeholders( $tpl['template'], $inquiry_id, $data ) );

        foreach ( array_filter( array_map( 'trim', explode( ',', (string) $recipient ) ) ) as $to ) {
            if ( is_email( $to ) ) {
                wp_mail( $to, $subject, $body, $headers, $attachments );
            }
        }
    }

    // ── Platzhalter-Ersetzung ────────────────────────────────────────────────

    public static function replace_placeholders( string $template, int $inquiry_id, array $data ): string {
        $placeholders = [
            '{name}'         => esc_html( $data['name']    ?? '' ),
            '{email}'        => esc_html( $data['email']   ?? '' ),
            '{phone}'        => esc_html( $data['phone']   ?? '' ),
            '{message}'      => nl2br( esc_html( $data['message'] ?? '' ) ),
            '{product_list}' => self::format_product_list( $data['items'] ?? [] ),
            '{inquiry_id}'   => '#' . $inquiry_id,
            '{source}'       => self::source_label( $data['source'] ?? '' ),
            '{site_name}'    => esc_html( get_bloginfo( 'name' ) ),
        ];

        // Zusätzliche, projektspezifisch konfigurierte Formularfelder
        foreach ( $data['extra_fields'] ?? [] as $key => $value ) {
            $placeholders[ '{' . $key . '}' ] = is_array( $value ) ? esc_html( implode( ', ', $value ) ) : esc_html( (string) $value );
        }

        return str_replace( array_keys( $placeholders ), array_values( $placeholders ), $template );
    }

    private static function source_label( string $source ): string {
        $labels = [
            'cart'         => 'Warenkorb',
            'configurator' => 'Konfigurator',
            'wishlist'     => 'Wunschliste',
        ];
        return $labels[ $source ] ?? $source;
    }

    // ── Produktlisten-Formatierung (inkl. Konfigurator-Daten & Preis) ────────

    public static function format_product_list( array $items ): string {
        if ( empty( $items ) ) return '<p><em>Keine Produkte angegeben.</em></p>';

        $html = '<table cellpadding="8" cellspacing="0" border="0" style="border-collapse:collapse;width:100%">';
        $i    = 0;
        foreach ( $items as $item ) {
            $bg   = ( $i++ % 2 === 0 ) ? 'background:#f9f9f9' : '';
            $name = esc_html( $item['name'] ?? '' );
            $qty  = (int) ( $item['quantity'] ?? 1 );
            $sku  = trim( (string) ( $item['sku'] ?? '' ) );

            $html .= '<tr style="' . $bg . '"><td colspan="2"><strong>' . $name . '</strong> (Menge: ' . $qty . ')';
            if ( $sku !== '' ) {
                $html .= '<br><span style="color:#888;font-size:12px">Art.-Nr.: ' . esc_html( $sku ) . '</span>';
            }

            // Konfigurator-Details, falls vorhanden
            if ( ! empty( $item['config_display'] ) && is_array( $item['config_display'] ) ) {
                $html .= '<table cellpadding="4" cellspacing="0" border="0" style="width:100%;margin-top:6px">';
                foreach ( $item['config_display'] as $label => $value ) {
                    $html .= '<tr><td style="color:#666;width:40%">' . esc_html( $label ) . '</td><td>' . esc_html( (string) $value ) . '</td></tr>';
                }
                $html .= '</table>';
            }

            // Preisaufschlüsselung, falls vorhanden.
            //
            // Vorher wurden nur base_price, additions, tier_discount und total
            // gezeigt - der Gesamtbetrag war zwar korrekt (inkl. Steuer), für
            // den Kunden aber nicht nachvollziehbar, wie er zustande kommt.
            // Ergänzt um Zwischensumme/Stück, Menge, Zwischensumme vor Steuer
            // und eine eigene MwSt.-Zeile. Alle neuen Felder sind optional
            // (isset-Checks) - price_breakdown-Arrays im alten, schmaleren
            // Format (z.B. aus älteren Aufrufern) werden weiterhin unterstützt.
            if ( ! empty( $item['price_breakdown'] ) && is_array( $item['price_breakdown'] ) && function_exists( 'wc_price' ) ) {
                $pb = $item['price_breakdown'];
                $breakdown_qty = isset( $pb['quantity'] ) ? (int) $pb['quantity'] : $qty;

                $html .= '<table cellpadding="4" cellspacing="0" border="0" style="width:100%;margin-top:6px;border-top:1px solid #eee">';

                if ( isset( $pb['base_price'] ) ) {
                    $html .= '<tr><td style="color:#666;width:40%">Basispreis</td><td>' . wp_kses_post( wc_price( $pb['base_price'] ) ) . '</td></tr>';
                }

                foreach ( $pb['additions'] ?? [] as $addition ) {
                    $html .= '<tr><td style="color:#666">+ ' . esc_html( $addition['label'] ?? '' ) . '</td><td>' . wp_kses_post( wc_price( $addition['price'] ?? 0 ) ) . '</td></tr>';
                }

                if ( isset( $pb['subtotal'] ) ) {
                    $html .= '<tr><td style="color:#666;border-top:1px solid #f0f0f0"><strong>Zwischensumme (pro Stück)</strong></td><td style="border-top:1px solid #f0f0f0"><strong>' . wp_kses_post( wc_price( $pb['subtotal'] ) ) . '</strong></td></tr>';
                }

                if ( isset( $pb['quantity'] ) ) {
                    $html .= '<tr><td style="color:#666">Menge</td><td>' . $breakdown_qty . ' Stück</td></tr>';
                }

                if ( ! empty( $pb['tier_discount'] ) ) {
                    $discount_label = 'Mengenrabatt';
                    if ( isset( $pb['tier_discount_percent'] ) ) {
                        $discount_label .= ' (' . round( $pb['tier_discount_percent'] * 100 ) . '%)';
                    }
                    $html .= '<tr><td style="color:#666">' . esc_html( $discount_label ) . '</td><td>-' . wp_kses_post( wc_price( $pb['tier_discount'] * $breakdown_qty ) ) . '</td></tr>';
                }

                if ( isset( $pb['total_before_tax'] ) ) {
                    $html .= '<tr><td style="color:#666;border-top:1px solid #eee">Zwischensumme</td><td style="border-top:1px solid #eee">' . wp_kses_post( wc_price( $pb['total_before_tax'] ) ) . '</td></tr>';
                }

                if ( ! empty( $pb['tax_rate'] ) && isset( $pb['tax_amount'] ) ) {
                    $html .= '<tr><td style="color:#666">MwSt. (' . esc_html( (string) $pb['tax_rate'] ) . '%)</td><td>' . wp_kses_post( wc_price( $pb['tax_amount'] ) ) . '</td></tr>';
                }

                if ( isset( $pb['total'] ) ) {
                    $html .= '<tr><td style="border-top:2px solid #ddd"><strong>Gesamt</strong></td><td style="border-top:2px solid #ddd"><strong>' . wp_kses_post( wc_price( $pb['total'] ) ) . '</strong></td></tr>';
                }

                $html .= '</table>';
            }

            // Datei-Uploads, falls vorhanden
            if ( ! empty( $item['attachments'] ) && is_array( $item['attachments'] ) ) {
                $html .= '<p style="margin-top:6px">';
                foreach ( $item['attachments'] as $att_id ) {
                    $url = wp_get_attachment_url( $att_id );
                    if ( $url ) {
                        $html .= '📎 <a href="' . esc_url( $url ) . '">' . esc_html( basename( $url ) ) . '</a><br>';
                    }
                }
                $html .= '</p>';
            }

            $html .= '</td></tr>';
        }
        $html .= '</table>';

        return $html;
    }

    /**
     * Sammelt alle Attachment-IDs aus den Items (z.B. Konfigurator-Uploads) für den E-Mail-Anhang.
     */
    private static function collect_attachments( array $data ): array {
        $paths = [];
        foreach ( $data['items'] ?? [] as $item ) {
            foreach ( $item['attachments'] ?? [] as $att_id ) {
                $path = get_attached_file( (int) $att_id );
                if ( $path ) $paths[] = $path;
            }
        }
        return $paths;
    }

    // ── HTML-Wrapper (identisches Design zum Booking-Tool) ────────────────────

    public static function wrap_html( string $content ): string {
        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
            body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;font-size:15px;color:#333;margin:0;padding:0;background:#f4f4f4}
            .wrap{max-width:600px;margin:32px auto;background:#fff;border-radius:6px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08)}
            .body{padding:32px} table{width:100%;border-collapse:collapse;margin:16px 0} td{padding:10px 12px;border-bottom:1px solid #eee;vertical-align:top}
            h2,h3{color:#111} a{color:#0073aa} .footer{background:#f9f9f9;padding:16px 32px;font-size:12px;color:#888;border-top:1px solid #eee}
        </style></head><body><div class="wrap"><div class="body">' . $content . '</div>
        <div class="footer">' . esc_html( get_bloginfo( 'name' ) ) . ' · ' . esc_html( home_url() ) . '</div></div></body></html>';
    }
}
