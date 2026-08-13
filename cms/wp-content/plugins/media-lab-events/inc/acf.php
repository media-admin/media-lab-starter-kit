<?php
/**
 * ACF Fields for Events
 */

if (!defined('ABSPATH')) exit;

add_action('acf/init', function() {

    if (!function_exists('acf_add_local_field_group')) return;

    acf_add_local_field_group(array(
        'key'    => 'group_event_details',
        'title'  => 'Event Details',
        'fields' => array(

            // Date Start
            array(
                'key'           => 'field_event_date_start',
                'label'         => 'Startdatum',
                'name'          => 'event_date_start',
                'type'          => 'date_time_picker',
                'required'      => 1,
                'display_format' => 'd.m.Y H:i',
                // return_format bewusst NICHT identisch zu display_format:
                // 'd.m.Y H:i' (Tag zuerst) ist als String nicht chronologisch
                // sortierbar (z.B. "01.03.2026" gilt string-sortiert als
                // "kleiner" als "15.01.2026", obwohl 15. Januar früher liegt).
                // 'Y-m-d H:i:s' (Jahr zuerst) sortiert als String korrekt und
                // ist zusätzlich das Format, in dem ACF date_time_picker-Werte
                // ohnehin intern in der Datenbank speichert - unabhängig vom
                // return_format. Diese Änderung erfordert daher KEINE
                // Datenmigration, nur die Ausgabe von get_field() ändert sich.
                // Frontend-Anzeige entsprechend in shortcodes.php via
                // date_i18n() zurück ins deutsche Format konvertiert.
                'return_format'  => 'Y-m-d H:i:s',
                'first_day'      => 1,
            ),

            // Date End
            array(
                'key'           => 'field_event_date_end',
                'label'         => 'Enddatum',
                'name'          => 'event_date_end',
                'type'          => 'date_time_picker',
                'required'      => 0,
                'display_format' => 'd.m.Y H:i',
                'return_format'  => 'Y-m-d H:i:s',
                'first_day'      => 1,
            ),

            // Location
            array(
                'key'      => 'field_event_location',
                'label'    => 'Ort',
                'name'     => 'event_location',
                'type'     => 'text',
                'required' => 0,
            ),

            // Price
            array(
                'key'          => 'field_event_price',
                'label'        => 'Preis',
                'name'         => 'event_price',
                'type'         => 'text',
                'required'     => 0,
                'placeholder'  => 'z.B. 25,00 € oder Kostenlos',
                'instructions' => 'Leer lassen wenn kein Preis angezeigt werden soll.',
            ),

        ),
        'location' => array(array(array(
            'param'    => 'post_type',
            'operator' => '==',
            'value'    => 'event',
        ))),
        'menu_order'  => 0,
        'position'    => 'normal',
        'style'       => 'default',
        'label_placement' => 'top',
    ));

});
