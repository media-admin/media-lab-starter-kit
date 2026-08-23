<?php
/**
 * Einstellungen für die Inquiry-Engine (Anfragen aus Cart, Konfigurator & Wunschliste).
 *
 * EINE globale ACF-Optionsseite (projektspezifisch editierbar), da wir uns auf
 * "eine Konfiguration pro Seite" geeinigt haben.
 *
 * Verwendung im Code:
 *   MediaLab_Inquiry_Settings::get_form_fields()   → konfigurierte Kontaktfelder (Repeater)
 *   MediaLab_Inquiry_Settings::get_active_channels() → aktive Versandkanäle
 *   MediaLab_Inquiry_Settings::get_mail_template( 'customer' | 'admin' )
 *   MediaLab_Inquiry_Settings::wording( 'submit_label' | ... )
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MediaLab_Inquiry_Settings {

    const OPTION_PAGE_SLUG = 'mlw-inquiry-settings';

    public static function init(): void {
        add_action( 'acf/include_fields', [ __CLASS__, 'register_options_page' ] );
        add_action( 'admin_menu', [ __CLASS__, 'add_settings_submenu' ], 30 );
        add_action( 'admin_notices', [ __CLASS__, 'render_completeness_notice' ] );
    }

    // ── ACF Options Page ──────────────────────────────────────────────────────

    public static function register_options_page(): void {
        if ( ! function_exists( 'acf_add_options_page' ) ) return;

        acf_add_options_sub_page( [
            'page_title'  => 'Anfrage-Einstellungen',
            'menu_title'  => 'Einstellungen',
            'parent_slug' => 'edit.php?post_type=mlw_inquiry',
            'capability'  => 'manage_woocommerce',
            'menu_slug'   => self::OPTION_PAGE_SLUG,
            'autoload'    => true,
        ] );

        if ( ! function_exists( 'acf_add_local_field_group' ) ) return;

        acf_add_local_field_group( [
            'key'      => 'group_mlw_inquiry_settings',
            'title'    => 'Anfrage-Einstellungen',
            'location' => [ [ [ 'param' => 'options_page', 'operator' => '==', 'value' => self::OPTION_PAGE_SLUG ] ] ],
            'fields'   => array_merge(
                self::fields_wording(),
                self::fields_languages(),
                self::fields_form(),
                self::fields_channels(),
                self::fields_mail_templates(),
                self::fields_navigation()
            ),
        ] );
    }

    // ── Tab: Wording ─────────────────────────────────────────────────────────

    private static function fields_wording(): array {
        $hide_if_multilang = [ [ [ 'field' => 'field_mlw_multilang_enabled', 'operator' => '!=', 'value' => '1' ] ] ];
        return [
            [ 'key' => 'field_mlw_tab_wording', 'label' => 'Wording', 'name' => '', 'type' => 'tab' ],
            [
                'key'     => 'field_mlw_wording_flat_info',
                'label'   => '',
                'name'    => '',
                'type'    => 'message',
                'message' => '<p><em>Einsprachige Fallback-Texte. Werden verwendet, wenn Mehrsprachigkeit deaktiviert ist oder keine Sprache passt (Tab „Sprachen").</em></p>',
            ],
            [
                'key'         => 'field_mlw_wishlist_label',
                'label'       => 'Bezeichnung Wunschliste',
                'name'        => 'mlw_wishlist_label',
                'type'        => 'text',
                'placeholder' => 'Wunschliste',
                'wrapper'     => [ 'width' => '33' ],
                'conditional_logic' => $hide_if_multilang,
            ],
            [
                'key'         => 'field_mlw_add_button_label',
                'label'       => '„Hinzufügen"-Button',
                'name'        => 'mlw_add_button_label',
                'type'        => 'text',
                'placeholder' => 'Zur Wunschliste hinzufügen',
                'wrapper'     => [ 'width' => '33' ],
                'conditional_logic' => $hide_if_multilang,
            ],
            [
                'key'         => 'field_mlw_submit_label',
                'label'       => 'Absenden-Button',
                'name'        => 'mlw_submit_label',
                'type'        => 'text',
                'placeholder' => 'Anfrage senden',
                'wrapper'     => [ 'width' => '33' ],
                'conditional_logic' => $hide_if_multilang,
            ],
            [
                'key'         => 'field_mlw_success_message',
                'label'       => 'Erfolgsmeldung nach Absenden',
                'name'        => 'mlw_success_message',
                'type'        => 'text',
                'instructions'=> 'Gilt für Cart-Anfrage und Konfigurator-Anfrage.',
                'placeholder' => 'Vielen Dank! Ihre Anfrage wurde erfolgreich übermittelt. Wir melden uns in Kürze bei Ihnen.',
                'conditional_logic' => $hide_if_multilang,
            ],
            [
                'key'         => 'field_mlw_wishlist_success_message',
                'label'       => 'Erfolgsmeldung Wunschliste',
                'name'        => 'mlw_wishlist_success_message',
                'type'        => 'text',
                'instructions'=> 'Eigene Erfolgsmeldung nach dem Absenden der Wunschliste (unabhängig von der Anfrage-Erfolgsmeldung oben).',
                'placeholder' => 'Vielen Dank für Ihre Wunschliste! Wir melden uns in Kürze bei Ihnen.',
                'conditional_logic' => $hide_if_multilang,
            ],
            [
                'key'         => 'field_mlw_wishlist_empty_message',
                'label'       => 'Hinweistext: Wunschliste leer',
                'name'        => 'mlw_wishlist_empty_message',
                'type'        => 'text',
                'instructions'=> 'Wird angezeigt, wenn die Wunschliste keine Artikel enthält.',
                'placeholder' => 'Ihre Wunschliste ist zurzeit leer.',
                'conditional_logic' => $hide_if_multilang,
            ],
            [
                'key'         => 'field_mlw_price_notice',
                'label'       => 'Preis-Hinweistext',
                'name'        => 'mlw_price_notice',
                'type'        => 'text',
                'instructions'=> 'Erscheint neben Preisangaben im Konfigurator (z.B. "zzgl. Versandkosten" oder ein eigener Steuer-/Rechtshinweis). Leer lassen, um nichts anzuzeigen.',
                'placeholder' => 'zzgl. Versandkosten',
                'conditional_logic' => $hide_if_multilang,
            ],
        ];
    }

    // ── Tab: Sprachen ────────────────────────────────────────────────────────

    private static function fields_languages(): array {
        $sep = fn( string $label ) => [
            'key' => 'field_mlw_lang_sep_' . sanitize_key( $label ), 'label' => ' ', 'name' => '',
            'type' => 'message', 'message' => '<strong style="font-size:12px;color:#555;">' . esc_html( $label ) . '</strong>',
        ];

        return [
            [ 'key' => 'field_mlw_tab_languages', 'label' => 'Sprachen', 'name' => '', 'type' => 'tab' ],
            [
                'key'     => 'field_mlw_multilang_info',
                'label'   => '',
                'name'    => '',
                'type'    => 'message',
                'message' => '<p>Aktiviere Mehrsprachigkeit, um Wording und Mail-Texte je Sprache zu pflegen. Spracherkennung: Polylang → WPML → WP-Locale-Fallback - funktioniert unabhängig davon, welches (oder ob überhaupt ein) Mehrsprachigkeits-Plugin installiert ist. Die erste Zeile gilt als Fallback-Sprache.</p>',
            ],
            [
                'key'           => 'field_mlw_multilang_enabled',
                'label'         => 'Mehrsprachigkeit aktivieren',
                'name'          => 'mlw_multilang_enabled',
                'type'          => 'true_false',
                'ui'            => 1,
                'default_value' => 0,
            ],
            [
                'key'               => 'field_mlw_languages',
                'label'             => 'Sprachen',
                'name'              => 'mlw_languages',
                'type'              => 'repeater',
                'min'               => 0,
                'layout'            => 'block',
                'button_label'      => 'Sprache hinzufügen',
                'instructions'      => 'Sprachcodes: de, en, fr, it, es, …',
                'conditional_logic' => [ [ [ 'field' => 'field_mlw_multilang_enabled', 'operator' => '==', 'value' => '1' ] ] ],
                'sub_fields'        => [
                    [ 'key' => 'field_mlw_lang_code', 'label' => 'Sprachcode',  'name' => 'lang_code', 'type' => 'text', 'required' => 1, 'placeholder' => 'de', 'wrapper' => [ 'width' => '20' ] ],
                    [ 'key' => 'field_mlw_lang_name', 'label' => 'Bezeichnung', 'name' => 'lang_name', 'type' => 'text', 'placeholder' => 'Deutsch', 'instructions' => 'Nur zur internen Orientierung.', 'wrapper' => [ 'width' => '30' ] ],

                    $sep( 'Wording' ),
                    [ 'key' => 'field_mlw_lang_wishlist_label', 'label' => 'Bezeichnung Wunschliste', 'name' => 'wishlist_label', 'type' => 'text', 'wrapper' => [ 'width' => '14' ] ],
                    [ 'key' => 'field_mlw_lang_add_button',     'label' => '„Hinzufügen"-Button',       'name' => 'add_button',      'type' => 'text', 'wrapper' => [ 'width' => '14' ] ],
                    [ 'key' => 'field_mlw_lang_submit_button',  'label' => 'Absenden-Button',            'name' => 'submit_button',   'type' => 'text', 'wrapper' => [ 'width' => '14' ] ],
                    [ 'key' => 'field_mlw_lang_success',        'label' => 'Erfolgsmeldung',             'name' => 'success',         'type' => 'text', 'wrapper' => [ 'width' => '14' ] ],
                    [ 'key' => 'field_mlw_lang_wishlist_success','label' => 'Erfolgsmeldung Wunschliste','name' => 'wishlist_success','type' => 'text', 'wrapper' => [ 'width' => '14' ] ],
                    [ 'key' => 'field_mlw_lang_wishlist_empty', 'label' => 'Hinweistext: Liste leer',    'name' => 'wishlist_empty',  'type' => 'text', 'wrapper' => [ 'width' => '14' ] ],
                    [ 'key' => 'field_mlw_lang_price_notice',   'label' => 'Preis-Hinweistext',          'name' => 'price_notice',    'type' => 'text', 'placeholder' => 'zzgl. Versandkosten', 'wrapper' => [ 'width' => '16' ] ],
                    [ 'key' => 'field_mlw_lang_privacy_text',   'label' => 'Datenschutz-Text',           'name' => 'privacy_text',    'type' => 'textarea', 'rows' => 2, 'wrapper' => [ 'width' => '100' ] ],

                    $sep( 'Navigation' ),
                    [
                        'key'           => 'field_mlw_lang_wishlist_page',
                        'label'         => 'Wunschlisten-Seite',
                        'name'          => 'wishlist_page',
                        'type'          => 'post_object',
                        'post_type'     => [ 'page' ],
                        'return_format' => 'id',
                        'instructions'  => 'Die übersetzte Seite mit [mlw_wishlist_page] für DIESE Sprache.',
                        'wrapper'       => [ 'width' => '100' ],
                    ],

                    $sep( 'Mail: Kunde' ),
                    [ 'key' => 'field_mlw_lang_mail_customer_subject',  'label' => 'Betreff', 'name' => 'mail_customer_subject',  'type' => 'text', 'wrapper' => [ 'width' => '100' ] ],
                    [ 'key' => 'field_mlw_lang_mail_customer_template', 'label' => 'Inhalt',  'name' => 'mail_customer_template', 'type' => 'wysiwyg', 'tabs' => 'all', 'media_upload' => 0, 'toolbar' => 'basic' ],

                    $sep( 'Mail: Admin' ),
                    [ 'key' => 'field_mlw_lang_mail_admin_subject',  'label' => 'Betreff', 'name' => 'mail_admin_subject',  'type' => 'text', 'wrapper' => [ 'width' => '100' ] ],
                    [ 'key' => 'field_mlw_lang_mail_admin_template', 'label' => 'Inhalt',  'name' => 'mail_admin_template', 'type' => 'wysiwyg', 'tabs' => 'all', 'media_upload' => 0, 'toolbar' => 'basic' ],
                ],
            ],
        ];
    }

    // ── Tab: Formularfelder ──────────────────────────────────────────────────

    private static function fields_form(): array {
        return [
            [ 'key' => 'field_mlw_tab_form', 'label' => 'Formularfelder', 'name' => '', 'type' => 'tab' ],
            [
                'key'     => 'field_mlw_form_info',
                'label'   => '',
                'name'    => '',
                'type'    => 'message',
                'message' => '<p>Diese Felder erscheinen im Anfrage-Formular (Cart-Anfrage, Konfigurator-Anfrage und Wunschliste). Name, E-Mail-Adresse und die Produktliste sind immer Teil der Anfrage und müssen hier nicht separat angelegt werden.</p>',
            ],
            [
                'key'          => 'field_mlw_form_fields',
                'label'        => 'Zusätzliche Felder',
                'name'         => 'mlw_form_fields',
                'type'         => 'repeater',
                'layout'       => 'table',
                'button_label' => 'Feld hinzufügen',
                'sub_fields'   => [
                    [
                        'key'   => 'field_mlw_ff_key',
                        'label' => 'Feld-Key',
                        'name'  => 'field_key',
                        'type'  => 'text',
                        'instructions' => 'Interner Bezeichner, z.B. "phone" oder "company". Nur Kleinbuchstaben, Zahlen, Unterstrich. Sprachunabhängig - wird als Platzhalter {feldkey} in Mails verwendet.',
                        'required' => 1,
                        'wrapper' => [ 'width' => '20' ],
                    ],
                    [
                        'key'   => 'field_mlw_ff_label',
                        'label' => 'Label (Fallback)',
                        'name'  => 'label',
                        'type'  => 'text',
                        'required' => 1,
                        'instructions' => 'Wird verwendet, wenn Mehrsprachigkeit deaktiviert ist oder keine Sprache passt.',
                        'wrapper' => [ 'width' => '25' ],
                    ],
                    [
                        'key'     => 'field_mlw_ff_type',
                        'label'   => 'Feldtyp',
                        'name'    => 'field_type',
                        'type'    => 'select',
                        'choices' => [
                            'text'     => 'Text',
                            'email'    => 'E-Mail',
                            'tel'      => 'Telefon',
                            'textarea' => 'Textarea',
                            'select'   => 'Auswahl (Dropdown)',
                            'checkbox' => 'Checkbox',
                            'date'     => 'Datum',
                        ],
                        'default_value' => 'text',
                        'wrapper' => [ 'width' => '15' ],
                    ],
                    [
                        'key'         => 'field_mlw_ff_options',
                        'label'       => 'Optionen (Fallback)',
                        'name'        => 'options',
                        'type'        => 'text',
                        'instructions'=> 'Nur bei „Auswahl": kommagetrennte Liste, z.B. Vormittag,Nachmittag,Abend',
                        'wrapper'     => [ 'width' => '20' ],
                        'conditional_logic' => [ [ [ 'field' => 'field_mlw_ff_type', 'operator' => '==', 'value' => 'select' ] ] ],
                    ],
                    [
                        'key'   => 'field_mlw_ff_required',
                        'label' => 'Pflichtfeld',
                        'name'  => 'required',
                        'type'  => 'true_false',
                        'ui'    => 1,
                        'default_value' => 0,
                        'wrapper' => [ 'width' => '10' ],
                    ],
                    [
                        'key'   => 'field_mlw_ff_placeholder',
                        'label' => 'Platzhalter-Text (Fallback)',
                        'name'  => 'placeholder',
                        'type'  => 'text',
                        'wrapper' => [ 'width' => '10' ],
                    ],
                    [
                        'key'               => 'field_mlw_ff_translations',
                        'label'             => 'Übersetzungen',
                        'name'              => 'field_translations',
                        'type'              => 'repeater',
                        'layout'            => 'table',
                        'button_label'      => 'Sprache hinzufügen',
                        'instructions'      => 'Nur nötig, wenn Mehrsprachigkeit aktiv ist. Leer gelassene Werte nutzen den Fallback links.',
                        'sub_fields'        => [
                            [ 'key' => 'field_mlw_fft_code',        'label' => 'Sprachcode',  'name' => 'lang_code',    'type' => 'text', 'placeholder' => 'de', 'wrapper' => [ 'width' => '15' ] ],
                            [ 'key' => 'field_mlw_fft_label',       'label' => 'Label',       'name' => 'label',        'type' => 'text', 'wrapper' => [ 'width' => '30' ] ],
                            [ 'key' => 'field_mlw_fft_placeholder', 'label' => 'Platzhalter', 'name' => 'placeholder', 'type' => 'text', 'wrapper' => [ 'width' => '30' ] ],
                            [ 'key' => 'field_mlw_fft_options',     'label' => 'Optionen',    'name' => 'options',     'type' => 'text', 'instructions' => 'Nur bei „Auswahl"', 'wrapper' => [ 'width' => '25' ] ],
                        ],
                    ],
                ],
            ],
            [
                'key'     => 'field_mlw_privacy_required',
                'label'   => 'Datenschutz-Zustimmung verpflichtend',
                'name'    => 'mlw_privacy_required',
                'type'    => 'true_false',
                'default_value' => 1,
                'ui'      => 1,
            ],
            [
                'key'     => 'field_mlw_privacy_text',
                'label'   => 'Datenschutz-Text (Checkbox-Label, HTML erlaubt)',
                'name'    => 'mlw_privacy_text',
                'type'    => 'wysiwyg',
                'tabs'    => 'text',
                'media_upload' => 0,
                'toolbar' => 'basic',
                'placeholder' => 'Ich habe die Datenschutzerklärung gelesen und stimme der Verarbeitung meiner Daten zu.',
                'conditional_logic' => [ [ [ 'field' => 'field_mlw_privacy_required', 'operator' => '==', 'value' => '1' ] ] ],
            ],
        ];
    }

    // ── Tab: Kanäle ──────────────────────────────────────────────────────────

    private static function fields_channels(): array {
        return [
            [ 'key' => 'field_mlw_tab_channels', 'label' => 'Kanäle', 'name' => '', 'type' => 'tab' ],
            [
                'key'     => 'field_mlw_channels_info',
                'label'   => '',
                'name'    => '',
                'type'    => 'message',
                'message' => '<p>Über welche Kanäle sollen eingehende Anfragen versendet werden? Beliebig kombinierbar.</p>',
            ],

            // E-Mail
            [ 'key' => 'field_mlw_email_enabled', 'label' => 'E-Mail aktiv', 'name' => 'mlw_channel_email_enabled', 'type' => 'true_false', 'ui' => 1, 'default_value' => 1, 'wrapper' => [ 'width' => '20' ] ],
            [
                'key'   => 'field_mlw_email_recipient',
                'label' => 'Empfänger-E-Mail(s)',
                'name'  => 'mlw_channel_email_recipient',
                'type'  => 'text',
                'instructions' => 'Kommagetrennt bei mehreren Empfängern. Leer = Admin-E-Mail der Seite.',
                'wrapper' => [ 'width' => '40' ],
                'conditional_logic' => [ [ [ 'field' => 'field_mlw_email_enabled', 'operator' => '==', 'value' => '1' ] ] ],
            ],

            // WhatsApp
            [ 'key' => 'field_mlw_whatsapp_enabled', 'label' => 'WhatsApp-Link aktiv', 'name' => 'mlw_channel_whatsapp_enabled', 'type' => 'true_false', 'ui' => 1, 'default_value' => 0, 'wrapper' => [ 'width' => '20' ] ],
            [
                'key'   => 'field_mlw_whatsapp_number',
                'label' => 'WhatsApp-Nummer',
                'name'  => 'mlw_channel_whatsapp_number',
                'type'  => 'text',
                'instructions' => 'Internationales Format ohne +, z.B. 4366412345678',
                'wrapper' => [ 'width' => '40' ],
                'conditional_logic' => [ [ [ 'field' => 'field_mlw_whatsapp_enabled', 'operator' => '==', 'value' => '1' ] ] ],
            ],
            [
                'key'   => 'field_mlw_whatsapp_template',
                'label' => 'WhatsApp-Textvorlage',
                'name'  => 'mlw_channel_whatsapp_template',
                'type'  => 'textarea',
                'rows'  => 3,
                'placeholder' => "Neue Anfrage von {name} ({email})\n{product_list}",
                'conditional_logic' => [ [ [ 'field' => 'field_mlw_whatsapp_enabled', 'operator' => '==', 'value' => '1' ] ] ],
            ],

            // Webhook
            [ 'key' => 'field_mlw_webhook_enabled', 'label' => 'Webhook aktiv', 'name' => 'mlw_channel_webhook_enabled', 'type' => 'true_false', 'ui' => 1, 'default_value' => 0, 'wrapper' => [ 'width' => '20' ] ],
            [
                'key'   => 'field_mlw_webhook_url',
                'label' => 'Webhook-URL',
                'name'  => 'mlw_channel_webhook_url',
                'type'  => 'url',
                'instructions' => 'Ziel-URL für einen POST-Request mit JSON-Payload (z.B. Zapier, Make, eigenes CRM).',
                'wrapper' => [ 'width' => '40' ],
                'conditional_logic' => [ [ [ 'field' => 'field_mlw_webhook_enabled', 'operator' => '==', 'value' => '1' ] ] ],
            ],
            [
                'key'   => 'field_mlw_webhook_secret',
                'label' => 'Webhook-Secret',
                'name'  => 'mlw_channel_webhook_secret',
                'type'  => 'text',
                'instructions' => 'Wird als Header "X-MLW-Secret" mitgesendet, damit der Empfänger die Anfrage verifizieren kann.',
                'wrapper' => [ 'width' => '40' ],
                'conditional_logic' => [ [ [ 'field' => 'field_mlw_webhook_enabled', 'operator' => '==', 'value' => '1' ] ] ],
            ],
        ];
    }

    // ── Tab: Mail-Templates ──────────────────────────────────────────────────

    private static function fields_mail_templates(): array {
        $hide_if_multilang = [ [ [ 'field' => 'field_mlw_multilang_enabled', 'operator' => '!=', 'value' => '1' ] ] ];
        return [
            [ 'key' => 'field_mlw_tab_mails', 'label' => 'Mail-Templates', 'name' => '', 'type' => 'tab' ],
            [
                'key'     => 'field_mlw_mail_info',
                'label'   => '',
                'name'    => '',
                'type'    => 'message',
                'message' => '<p>Verfügbare Platzhalter: <code>{name}</code>, <code>{email}</code>, <code>{phone}</code>, <code>{message}</code>, <code>{product_list}</code>, <code>{inquiry_id}</code>, <code>{source}</code>, <code>{site_name}</code>. Zusätzliche Felder aus dem Formular-Tab stehen als <code>{feldkey}</code> zur Verfügung.</p><p><em>Einsprachige Fallback-Vorlagen unten - bei aktiver Mehrsprachigkeit bitte im Tab „Sprachen" pflegen.</em></p>',
            ],

            [ 'key' => 'field_mlw_mail_customer_subject', 'label' => 'Kunden-Mail: Betreff', 'name' => 'mlw_mail_customer_subject', 'type' => 'text', 'placeholder' => 'Ihre Anfrage bei {site_name}', 'wrapper' => [ 'width' => '50' ], 'conditional_logic' => $hide_if_multilang ],
            [ 'key' => 'field_mlw_mail_admin_subject',    'label' => 'Admin-Mail: Betreff',    'name' => 'mlw_mail_admin_subject',    'type' => 'text', 'placeholder' => 'Neue Anfrage von {name}', 'wrapper' => [ 'width' => '50' ], 'conditional_logic' => $hide_if_multilang ],

            [
                'key'   => 'field_mlw_mail_customer_template',
                'label' => 'Kunden-Mail: Inhalt',
                'name'  => 'mlw_mail_customer_template',
                'type'  => 'wysiwyg',
                'tabs'  => 'all',
                'media_upload' => 0,
                'toolbar' => 'full',
                'conditional_logic' => $hide_if_multilang,
            ],
            [
                'key'   => 'field_mlw_mail_admin_template',
                'label' => 'Admin-Mail: Inhalt',
                'name'  => 'mlw_mail_admin_template',
                'type'  => 'wysiwyg',
                'tabs'  => 'all',
                'media_upload' => 0,
                'toolbar' => 'full',
                'conditional_logic' => $hide_if_multilang,
            ],
        ];
    }

    // ── Tab: Navigation ──────────────────────────────────────────────────────

    private static function fields_navigation(): array {
        return [
            [ 'key' => 'field_mlw_tab_nav', 'label' => 'Navigation', 'name' => '', 'type' => 'tab' ],
            [
                'key'     => 'field_mlw_nav_info',
                'label'   => '',
                'name'    => '',
                'type'    => 'message',
                'message' => '<p>Optionales Wunschlisten-Icon im Hauptmenü (Desktop + Mobile, automatisch über den Theme-Menüpunkt "primary"). Erfordert eine eigene Seite mit dem Shortcode <code>[mlw_wishlist_page]</code>.</p>',
            ],
            [
                'key'           => 'field_mlw_nav_icon_enabled',
                'label'         => 'Icon im Hauptmenü anzeigen',
                'name'          => 'mlw_nav_icon_enabled',
                'type'          => 'true_false',
                'ui'            => 1,
                'default_value' => 0,
                'wrapper'       => [ 'width' => '33' ],
            ],
            [
                'key'           => 'field_mlw_nav_icon_show_count',
                'label'         => 'Anzahl der Artikel anzeigen',
                'name'          => 'mlw_nav_icon_show_count',
                'type'          => 'true_false',
                'ui'            => 1,
                'default_value' => 1,
                'wrapper'       => [ 'width' => '33' ],
                'conditional_logic' => [ [ [ 'field' => 'field_mlw_nav_icon_enabled', 'operator' => '==', 'value' => '1' ] ] ],
            ],
            [
                'key'           => 'field_mlw_nav_wishlist_page',
                'label'         => 'Wunschlisten-Seite (Fallback)',
                'name'          => 'mlw_nav_wishlist_page',
                'type'          => 'post_object',
                'post_type'     => [ 'page' ],
                'return_format' => 'id',
                'instructions'  => 'Bei aktiver Mehrsprachigkeit bitte stattdessen im Tab "Sprachen" pro Sprache pflegen. Dieses Feld dient nur als Fallback.',
                'wrapper'       => [ 'width' => '34' ],
                'conditional_logic' => [ [ [ 'field' => 'field_mlw_nav_icon_enabled', 'operator' => '==', 'value' => '1' ] ] ],
            ],
        ];
    }

    // ── Fallback-Menü falls ACF nicht verfügbar ────────────────────────────────

    public static function add_settings_submenu(): void {
        if ( function_exists( 'acf_add_options_page' ) ) return;
        add_submenu_page( 'edit.php?post_type=mlw_inquiry', 'Einstellungen', 'Einstellungen', 'manage_woocommerce', self::OPTION_PAGE_SLUG, [ __CLASS__, 'fallback_page' ] );
    }

    public static function fallback_page(): void {
        echo '<div class="wrap"><h1>Anfrage-Einstellungen</h1><p>ACF Pro wird benötigt, um die Einstellungen zu konfigurieren.</p></div>';
    }

    // ── Öffentliche Helper ───────────────────────────────────────────────────

    /**
     * Konfigurierte Zusatzfelder des Anfrage-Formulars (Rohdaten, unlokalisiert).
     */
    public static function get_form_fields(): array {
        if ( ! function_exists( 'get_field' ) ) return [];
        $fields = get_field( 'mlw_form_fields', 'option' );
        return is_array( $fields ) ? $fields : [];
    }

    /**
     * Wie get_form_fields(), aber Label/Placeholder/Optionen sind bereits
     * für die aktuelle Sprache aufgelöst (Sprach-Zeile → Flat-Feld-Fallback).
     */
    public static function get_form_fields_localized(): array {
        $fields = self::get_form_fields();
        $lang   = MediaLab_Inquiry_I18n::current_language();

        foreach ( $fields as &$field ) {
            $translations = is_array( $field['field_translations'] ?? null ) ? $field['field_translations'] : [];

            $row         = MediaLab_Inquiry_I18n::resolve_row( $translations, 'lang_code', $lang );
            $default_row = $translations[0] ?? null; // erste konfigurierte Sprache dieses Feldes

            $pick = function ( string $sub_key ) use ( $row, $default_row, $field, $translations ): string {
                if ( ! empty( $translations ) ) {
                    if ( ! empty( $row[ $sub_key ] ) )         return $row[ $sub_key ];
                    if ( ! empty( $default_row[ $sub_key ] ) ) return $default_row[ $sub_key ];
                }
                return $field[ $sub_key ] ?? ''; // Flat-Feld-Fallback (letzte Stufe)
            };

            $field['label']       = $pick( 'label' )       ?: ( $field['label'] ?? '' );
            $field['placeholder'] = $pick( 'placeholder' ) ?: ( $field['placeholder'] ?? '' );
            $field['options']     = $pick( 'options' )     ?: ( $field['options'] ?? '' );
        }
        unset( $field );

        return $fields;
    }

    public static function privacy_required(): bool {
        if ( ! function_exists( 'get_field' ) ) return true;
        $val = get_field( 'mlw_privacy_required', 'option' );
        return $val === null ? true : (bool) $val;
    }

    public static function privacy_text(): string {
        $val = self::lang_value( 'privacy_text' );
        if ( $val ) return $val;
        $text = function_exists( 'get_field' ) ? get_field( 'mlw_privacy_text', 'option' ) : '';
        return $text ?: 'Ich habe die Datenschutzerklärung gelesen und stimme der Verarbeitung meiner Daten zu.';
    }

    /**
     * Interner Helper: liefert die zur aktuellen Sprache passende Zeile aus
     * mlw_languages, oder null wenn Mehrsprachigkeit deaktiviert/nicht konfiguriert ist.
     * ACHTUNG: Kann eine Zeile mit leeren Werten zurückgeben (Sprache existiert,
     * aber noch nicht befüllt) - dafür gibt es default_language_row() als nächste Stufe.
     */
    private static function current_language_row(): ?array {
        if ( ! MediaLab_Inquiry_I18n::multilang_enabled() ) return null;
        if ( ! function_exists( 'get_field' ) ) return null;
        $rows = get_field( 'mlw_languages', 'option' );
        if ( ! is_array( $rows ) || empty( $rows ) ) return null;
        return MediaLab_Inquiry_I18n::resolve_row( $rows, 'lang_code' );
    }

    /**
     * Erste konfigurierte Sprachzeile - dient als "Fallback-Sprache" (z.B. Deutsch),
     * bevor auf das sprachneutrale Flat-Feld oder den Code-Default zurückgefallen wird.
     * Das verhindert, dass eine leere Zeile (z.B. Spanisch ohne Inhalte) direkt auf
     * den hartcodierten deutschen Code-Default durchfällt.
     */
    private static function default_language_row(): ?array {
        if ( ! MediaLab_Inquiry_I18n::multilang_enabled() ) return null;
        if ( ! function_exists( 'get_field' ) ) return null;
        $rows = get_field( 'mlw_languages', 'option' );
        return ( is_array( $rows ) && ! empty( $rows ) ) ? $rows[0] : null;
    }

    /**
     * Liest einen Wert aus einer Sprachzeile mit vollständiger Fallback-Kette:
     * passende Sprache → erste konfigurierte Sprache → '' (Aufrufer prüft weiter).
     */
    private static function lang_value( string $key ): string {
        $row = self::current_language_row();
        if ( $row && ! empty( $row[ $key ] ) ) return trim( (string) $row[ $key ] );

        $default_row = self::default_language_row();
        if ( $default_row && ! empty( $default_row[ $key ] ) ) return trim( (string) $default_row[ $key ] );

        return '';
    }

    /**
     * Liste aktiver Kanäle, z.B. [ 'email', 'whatsapp', 'webhook' ].
     */
    public static function get_active_channels(): array {
        if ( ! function_exists( 'get_field' ) ) return [ 'email' ];
        $channels = [];
        if ( get_field( 'mlw_channel_email_enabled', 'option' ) )    $channels[] = 'email';
        if ( get_field( 'mlw_channel_whatsapp_enabled', 'option' ) ) $channels[] = 'whatsapp';
        if ( get_field( 'mlw_channel_webhook_enabled', 'option' ) )  $channels[] = 'webhook';
        return $channels ?: [ 'email' ]; // Fallback: mindestens E-Mail, damit keine Anfrage ins Leere läuft
    }

    public static function channel_config( string $channel ): array {
        if ( ! function_exists( 'get_field' ) ) return [];
        switch ( $channel ) {
            case 'email':
                return [
                    'recipient' => get_field( 'mlw_channel_email_recipient', 'option' ) ?: get_option( 'admin_email' ),
                ];
            case 'whatsapp':
                return [
                    'number'   => get_field( 'mlw_channel_whatsapp_number', 'option' ),
                    'template' => get_field( 'mlw_channel_whatsapp_template', 'option' ) ?: "Neue Anfrage von {name} ({email})\n{product_list}",
                ];
            case 'webhook':
                return [
                    'url'    => get_field( 'mlw_channel_webhook_url', 'option' ),
                    'secret' => get_field( 'mlw_channel_webhook_secret', 'option' ),
                ];
        }
        return [];
    }

    public static function get_mail_template( string $type ): array {
        $default = self::default_mail_template( $type );

        $subject_key  = $type === 'customer' ? 'mail_customer_subject'  : 'mail_admin_subject';
        $template_key = $type === 'customer' ? 'mail_customer_template' : 'mail_admin_template';

        $subject  = self::lang_value( $subject_key )  ?: self::flat_mail_field( $type, 'subject' )  ?: $default['subject'];
        $template = self::lang_value( $template_key ) ?: self::flat_mail_field( $type, 'template' ) ?: $default['template'];

        return [ 'subject' => $subject, 'template' => $template ];
    }

    private static function flat_mail_field( string $type, string $part ): string {
        if ( ! function_exists( 'get_field' ) ) return '';
        $field = $type === 'customer'
            ? ( $part === 'subject' ? 'mlw_mail_customer_subject' : 'mlw_mail_customer_template' )
            : ( $part === 'subject' ? 'mlw_mail_admin_subject'    : 'mlw_mail_admin_template' );
        return (string) ( get_field( $field, 'option' ) ?: '' );
    }

    private static function default_mail_template( string $type ): array {
        if ( $type === 'customer' ) {
            return [
                'subject'  => 'Ihre Anfrage bei {site_name}',
                'template' => '<p>Guten Tag {name},</p><p>vielen Dank für Ihre Anfrage. Wir haben folgende Produkte erhalten:</p>{product_list}<p>Wir melden uns in Kürze bei Ihnen.</p><p>Mit freundlichen Grüßen,<br>{site_name}</p>',
            ];
        }
        return [
            'subject'  => 'Neue Anfrage von {name}',
            'template' => '<p>Neue Anfrage (#{inquiry_id}, Quelle: {source}) eingegangen.</p><p><strong>Name:</strong> {name}<br><strong>E-Mail:</strong> {email}<br><strong>Telefon:</strong> {phone}</p><h3>Produkte</h3>{product_list}<p><strong>Nachricht:</strong><br>{message}</p>',
        ];
    }

    // ── Navigation-Icon-Einstellungen ────────────────────────────────────────

    public static function nav_icon_enabled(): bool {
        return function_exists( 'get_field' ) && (bool) get_field( 'mlw_nav_icon_enabled', 'option' );
    }

    public static function nav_icon_show_count(): bool {
        if ( ! function_exists( 'get_field' ) ) return true;
        $val = get_field( 'mlw_nav_icon_show_count', 'option' );
        return $val === null ? true : (bool) $val;
    }

    /**
     * URL der Wunschlisten-Seite, oder '' falls nicht konfiguriert.
     * Fallback-Kette wie sonstiges Wording: passende Sprache -> erste
     * konfigurierte Sprache -> flaches Fallback-Feld.
     */
    public static function nav_wishlist_page_url(): string {
        if ( ! function_exists( 'get_field' ) ) return '';

        $page_id = null;
        $lang_val = self::lang_value( 'wishlist_page' );
        if ( $lang_val ) $page_id = (int) $lang_val;

        if ( ! $page_id ) {
            $flat = get_field( 'mlw_nav_wishlist_page', 'option' );
            if ( $flat ) $page_id = (int) $flat;
        }

        if ( ! $page_id ) return '';
        $url = get_permalink( $page_id );
        return $url ?: '';
    }

    public static function wording( string $key ): string {
        $defaults = [
            'wishlist_label'   => 'Wunschliste',
            'add_button'       => 'Zur Wunschliste hinzufügen',
            'submit_button'    => 'Anfrage senden',
            'success'          => 'Vielen Dank! Ihre Anfrage wurde erfolgreich übermittelt. Wir melden uns in Kürze bei Ihnen.',
            'wishlist_success' => 'Vielen Dank für Ihre Wunschliste! Wir melden uns in Kürze bei Ihnen.',
            'wishlist_empty'   => 'Ihre Wunschliste ist zurzeit leer.',
            'price_notice'     => '',
        ];
        $field_map = [
            'wishlist_label'   => 'mlw_wishlist_label',
            'add_button'       => 'mlw_add_button_label',
            'submit_button'    => 'mlw_submit_label',
            'success'          => 'mlw_success_message',
            'wishlist_success' => 'mlw_wishlist_success_message',
            'wishlist_empty'   => 'mlw_wishlist_empty_message',
            'price_notice'     => 'mlw_price_notice',
        ];
        if ( ! isset( $field_map[ $key ] ) ) return '';

        // 1. + 2. Sprach-Zeile (passende Sprache, sonst erste konfigurierte Sprache)
        $val = self::lang_value( $key );
        if ( $val ) return $val;

        // 3. Flat-Feld
        if ( function_exists( 'get_field' ) ) {
            $val = get_field( $field_map[ $key ], 'option' );
            if ( $val && trim( $val ) !== '' ) return trim( $val );
        }

        // 4. Code-Default (nur als allerletzte Notbremse)
        return $defaults[ $key ] ?? '';
    }

    // ── Vollständigkeits-Prüfung für Übersetzungen ──────────────────────────

    /**
     * Zeigt im Backend einen dezenten Hinweis, wenn bei aktiver Mehrsprachigkeit
     * für eine konfigurierte Sprache Wording- oder Mail-Texte fehlen. Ersetzt
     * NICHT die Anfrage-Verarbeitung selbst (die fällt bei fehlenden Werten
     * automatisch auf das Flat-Feld / den Code-Default zurück) - dient nur
     * der Redaktions-Kontrolle.
     */
    public static function render_completeness_notice(): void {
        $screen = get_current_screen();
        // Hinweis: NICHT auf 'mlw_inquiry' im Screen-ID prüfen - der Hook-Präfix für
        // Unterseiten von add_menu_page() leitet sich vom Menü-TITEL ab ("Anfragen" →
        // "anfragen_page_..."), nicht vom Post-Type-Slug. self::OPTION_PAGE_SLUG ist
        // dagegen immer exakter Bestandteil der Screen-ID, unabhängig vom Menütitel.
        if ( ! $screen || strpos( (string) $screen->id, self::OPTION_PAGE_SLUG ) === false ) return;
        if ( ! MediaLab_Inquiry_I18n::multilang_enabled() ) return;
        if ( ! function_exists( 'get_field' ) ) return;

        $rows = get_field( 'mlw_languages', 'option' );
        if ( ! is_array( $rows ) || empty( $rows ) ) return;

        $checked_keys = [
            'wishlist_label' => 'Bezeichnung Wunschliste',
            'add_button'     => '„Hinzufügen"-Button',
            'submit_button'  => 'Absenden-Button',
            'success'        => 'Erfolgsmeldung',
            'mail_customer_subject'  => 'Kunden-Mail: Betreff',
            'mail_customer_template' => 'Kunden-Mail: Inhalt',
            'mail_admin_subject'     => 'Admin-Mail: Betreff',
            'mail_admin_template'    => 'Admin-Mail: Inhalt',
        ];

        $missing = [];
        foreach ( $rows as $row ) {
            $code = trim( (string) ( $row['lang_code'] ?? '' ) );
            if ( ! $code ) continue;

            $gaps = [];
            foreach ( $checked_keys as $key => $label ) {
                if ( empty( $row[ $key ] ) ) $gaps[] = $label;
            }
            if ( $gaps ) $missing[ $code ] = $gaps;
        }

        if ( empty( $missing ) ) return;

        echo '<div class="notice notice-warning"><p><strong>Anfrage-Einstellungen:</strong> Für folgende Sprachen fehlen noch Übersetzungen (es greift währenddessen der einsprachige Fallback):</p><ul style="margin-left:20px;list-style:disc">';
        foreach ( $missing as $code => $gaps ) {
            echo '<li><strong>' . esc_html( strtoupper( $code ) ) . '</strong>: ' . esc_html( implode( ', ', $gaps ) ) . '</li>';
        }
        echo '</ul></div>';
    }
}

MediaLab_Inquiry_Settings::init();
