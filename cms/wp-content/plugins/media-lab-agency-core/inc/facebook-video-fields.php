<?php
/**
 * ACF Block Field Group – Facebook Video
 *
 * Bewusst als eigene Datei gehalten (statt Ergänzung in inc/acf-blocks.php),
 * um die bestehende, gewachsene Datei nicht anzufassen. Kann bei Gelegenheit
 * dort integriert werden, wenn gewünscht.
 *
 * Block: medialab/facebook-video → group_block_facebook_video
 *
 * @package MediaLabAgencyCore
 * @since   1.20.0 (Platzhalter – bitte an tatsächliche Plugin-Version anpassen)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'acf/init', 'medialab_register_facebook_video_fields' );

function medialab_register_facebook_video_fields(): void {
    if ( ! function_exists( 'acf_add_local_field_group' ) ) return;
    acf_add_local_field_group( [
        'key'    => 'group_block_facebook_video',
        'title'  => 'Facebook Video Block',
        'fields' => [
            [
                'key'          => 'field_fbv_url',
                'label'        => 'Facebook Video URL',
                'name'         => 'fbv_url',
                'type'         => 'url',
                'required'     => 1,
                'placeholder'  => 'https://www.facebook.com/.../videos/...',
                'instructions' => 'Link zum Facebook-Video (Post- oder Watch-URL).',
            ],
            [
                'key'           => 'field_fbv_thumbnail',
                'label'         => 'Vorschaubild',
                'name'          => 'fbv_thumbnail',
                'type'          => 'image',
                'required'      => 1,
                'return_format' => 'array',
                'preview_size'  => 'medium',
                'instructions'  => 'Eigenes Vorschaubild – wird angezeigt, bevor Consent erteilt wurde (kein Nachladen von Facebook nötig).',
            ],
            [
                'key'     => 'field_fbv_caption',
                'label'   => 'Bildunterschrift (optional)',
                'name'    => 'fbv_caption',
                'type'    => 'text',
                'wrapper' => [ 'width' => '50' ],
            ],
            [
                'key'           => 'field_fbv_show_text',
                'label'         => 'Facebook-Beitragstext mitladen',
                'name'          => 'fbv_show_text',
                'type'          => 'true_false',
                'ui'            => 1,
                'default_value' => 0,
                'wrapper'       => [ 'width' => '50' ],
            ],
        ],
        'location' => [ [ [
            'param'    => 'block',
            'operator' => '==',
            'value'    => 'medialab/facebook-video',
        ] ] ],
        'menu_order' => 0,
        'position'   => 'normal',
        'style'      => 'default',
    ] );
}

/**
 * Polylang String-Übersetzung für die UI-Texte des Blocks.
 *
 * Ersetzt die vorher hart codierte DE/ES-Fallunterscheidung in render.php.
 * Admins können die Übersetzungen jetzt unter
 * Sprachen → Übersetzungen ("Facebook Video Block"-Gruppe) pflegen,
 * ohne Code-Deploy - auch für weitere Sprachen als DE/ES.
 *
 * Die deutschen Strings sind die Registrierungs-Quelle (Polylang matcht
 * pll__() exakt gegen den hier registrierten String). Die spanische
 * Übersetzung aus der ursprünglichen Version ist unten als Kommentar
 * hinterlegt, damit sie beim manuellen Eintragen nicht neu formuliert
 * werden muss.
 *
 * ES-Übersetzungen zum Eintragen unter Sprachen → Übersetzungen:
 *   „Facebook-Video laden & abspielen“
 *     → „Cargar y reproducir el vídeo de Facebook“
 *   „Beim Laden werden Daten an Meta Platforms Ireland Ltd. (USA) übertragen.“
 *     → „Al cargar el vídeo se transmiten datos a Meta Platforms Ireland Ltd. (EE. UU.).“
 *   „Cookie-Einstellungen anpassen“
 *     → „Ajustar las preferencias de cookies“
 */
add_action( 'init', 'medialab_register_facebook_video_strings' );

function medialab_register_facebook_video_strings(): void {
    if ( ! function_exists( 'pll_register_string' ) ) return;

    $group = 'Facebook Video Block';

    pll_register_string(
        'fbv_button',
        'Facebook-Video laden & abspielen',
        $group
    );
    pll_register_string(
        'fbv_hint',
        'Beim Laden werden Daten an Meta Platforms Ireland Ltd. (USA) übertragen.',
        $group
    );
    pll_register_string(
        'fbv_settings',
        'Cookie-Einstellungen anpassen',
        $group
    );
}
