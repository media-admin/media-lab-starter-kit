<?php
/**
 * ACF Block Field Group + Polylang Strings – Social Embed (Facebook/Instagram)
 *
 * Block: medialab/social-embed → group_block_social_embed
 *
 * @package MediaLabAgencyCore
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'acf/init', 'medialab_register_social_embed_fields' );

function medialab_register_social_embed_fields(): void {
    if ( ! function_exists( 'acf_add_local_field_group' ) ) return;

    acf_add_local_field_group( [
        'key'    => 'group_block_social_embed',
        'title'  => 'Social Embed Block',
        'fields' => [
            [
                'key'           => 'field_se_provider',
                'label'         => 'Anbieter',
                'name'          => 'se_provider',
                'type'          => 'select',
                'required'      => 1,
                'choices'       => [
                    'facebook'  => 'Facebook (Video)',
                    'instagram' => 'Instagram (Beitrag)',
                ],
                'default_value' => 'facebook',
                'allow_null'    => 0,
                'ui'            => 1,
                'instructions'  => 'Bestimmt, wie der Embed technisch geladen wird (Facebook: iframe / Instagram: embed.js).',
            ],
            [
                'key'          => 'field_se_url',
                'label'        => 'URL',
                'name'         => 'se_url',
                'type'         => 'url',
                'required'     => 1,
                'placeholder'  => 'https://www.facebook.com/.../videos/... oder https://www.instagram.com/p/...',
                'instructions' => 'Link zum Facebook-Video bzw. zum Instagram-Beitrag.',
            ],
            [
                'key'           => 'field_se_thumbnail',
                'label'         => 'Vorschaubild',
                'name'          => 'se_thumbnail',
                'type'          => 'image',
                'required'      => 1,
                'return_format' => 'array',
                'preview_size'  => 'medium',
                'instructions'  => 'Eigenes Vorschaubild – wird angezeigt, bevor Consent erteilt wurde (kein Nachladen von Meta-Servern nötig).',
            ],
            [
                'key'     => 'field_se_caption',
                'label'   => 'Bildunterschrift (optional)',
                'name'    => 'se_caption',
                'type'    => 'text',
                'wrapper' => [ 'width' => '50' ],
            ],
            [
                'key'               => 'field_se_show_text',
                'label'             => 'Facebook-Beitragstext mitladen',
                'name'              => 'se_show_text',
                'type'              => 'true_false',
                'ui'                => 1,
                'default_value'     => 0,
                'wrapper'           => [ 'width' => '50' ],
                'instructions'      => 'Nur relevant bei Anbieter „Facebook".',
                'conditional_logic' => [ [ [
                    'field'    => 'field_se_provider',
                    'operator' => '==',
                    'value'    => 'facebook',
                ] ] ],
            ],
        ],
        'location' => [ [ [
            'param'    => 'block',
            'operator' => '==',
            'value'    => 'medialab/social-embed',
        ] ] ],
        'menu_order' => 0,
        'position'   => 'normal',
        'style'      => 'default',
    ] );
}

/**
 * Polylang String-Übersetzung für die UI-Texte des Blocks.
 *
 * Admins pflegen Übersetzungen unter Sprachen → Übersetzungen,
 * Gruppe "Social Embed Block" - kein Code-Deploy für neue Sprachen nötig.
 *
 * ES-Übersetzungen zum Eintragen unter Sprachen → Übersetzungen:
 *   „Facebook-Video laden & abspielen“
 *     → „Cargar y reproducir el vídeo de Facebook“
 *   „Instagram-Beitrag laden & anzeigen“
 *     → „Cargar y mostrar la publicación de Instagram“
 *   „Beim Laden werden Daten an Meta Platforms Ireland Ltd. (USA) übertragen.“
 *     → „Al cargar el contenido se transmiten datos a Meta Platforms Ireland Ltd. (EE. UU.).“
 *   „Cookie-Einstellungen anpassen“
 *     → „Ajustar las preferencias de cookies“
 */
add_action( 'init', 'medialab_register_social_embed_strings' );

function medialab_register_social_embed_strings(): void {
    if ( ! function_exists( 'pll_register_string' ) ) return;

    $group = 'Social Embed Block';

    pll_register_string( 'se_button_facebook',  'Facebook-Video laden & abspielen', $group );
    pll_register_string( 'se_button_instagram', 'Instagram-Beitrag laden & anzeigen', $group );
    pll_register_string( 'se_hint',             'Beim Laden werden Daten an Meta Platforms Ireland Ltd. (USA) übertragen.', $group );
    pll_register_string( 'se_settings',         'Cookie-Einstellungen anpassen', $group );
}
