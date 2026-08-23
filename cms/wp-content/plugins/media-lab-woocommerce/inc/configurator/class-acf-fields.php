<?php
/**
 * ACF Field Definitions for Product Configurator
 */

if (!defined('ABSPATH')) exit;

class MediaLab_Configurator_ACF_Fields {
    
    public static function register() {
        
        // Main Configurator Settings
        acf_add_local_field_group(array(
            'key' => 'group_product_configurator',
            'title' => 'Produkt Konfigurator',
            'fields' => array(
                
                // Enable Configurator
                array(
                    'key' => 'field_is_configurable',
                    'label' => 'Konfigurator aktivieren',
                    'name' => 'is_configurable',
                    'type' => 'true_false',
                    'instructions' => 'Aktiviert den Step-by-Step Produktkonfigurator',
                    'default_value' => 0,
                    'ui' => 1,
                ),
                
                // Configurator Type
                array(
                    'key' => 'field_config_type',
                    'label' => 'Konfigurator-Typ',
                    'name' => 'config_type',
                    'type' => 'select',
                    'instructions' => 'Wähle den Typ des Produkts',
                    'choices' => array(
                        'textile' => 'Textilien (T-Shirts, Jacken, etc.)',
                        'print' => 'Drucksorten (Visitenkarten, Flyer, etc.)',
                        'giveaway' => 'Give-Aways (Kugelschreiber, USB-Sticks, etc.)',
                        'custom' => 'Benutzerdefiniert',
                    ),
                    'default_value' => 'textile',
                    'conditional_logic' => array(
                        array(
                            array(
                                'field' => 'field_is_configurable',
                                'operator' => '==',
                                'value' => '1',
                            ),
                        ),
                    ),
                ),
                
                // Configuration Steps (Repeater)
                array(
                    'key' => 'field_config_steps',
                    'label' => 'Konfigurationsschritte',
                    'name' => 'config_steps',
                    'type' => 'repeater',
                    'instructions' => 'Definiere die Schritte des Konfigurators',
                    'layout' => 'block',
                    'button_label' => 'Schritt hinzufügen',
                    'conditional_logic' => array(
                        array(
                            array(
                                'field' => 'field_is_configurable',
                                'operator' => '==',
                                'value' => '1',
                            ),
                        ),
                    ),
                    'sub_fields' => array(
                        
                        // Step ID
                        array(
                            'key' => 'field_step_id',
                            'label' => 'Step ID',
                            'name' => 'step_id',
                            'type' => 'text',
                            'instructions' => 'Eindeutige ID (z.B. "material", "color", "size")',
                            'required' => 1,
                            'wrapper' => array('width' => '50'),
                        ),
                        
                        // Step Label
                        array(
                            'key' => 'field_step_label',
                            'label' => 'Schritt-Bezeichnung',
                            'name' => 'step_label',
                            'type' => 'text',
                            'instructions' => 'Wird dem Kunden angezeigt',
                            'required' => 1,
                            'wrapper' => array('width' => '50'),
                        ),
                        
                        // Step Type
                        array(
                            'key' => 'field_step_type',
                            'label' => 'Eingabe-Typ',
                            'name' => 'step_type',
                            'type' => 'select',
                            'choices' => array(
                                'select' => 'Dropdown (Select)',
                                'radio' => 'Radio Buttons',
                                'checkbox' => 'Checkboxen (Mehrfachauswahl)',
                                'number' => 'Zahleneingabe',
                                'text' => 'Texteingabe',
                                'textarea' => 'Textbereich',
                                'file_upload' => 'Datei-Upload',
                                'size_matrix' => 'Größen-Matrix (für Textilien)',
                                'color_picker' => 'Farbauswahl',
                                // Wird von wizard.php, class-configurator.php und
                                // class-price-calculator.php bereits aktiv unterstützt
                                // (eigener switch-Case + Validierungslogik), fehlte hier
                                // aber in den ACF-Choices - dadurch im Produkt-Editor
                                // nicht auswählbar, obwohl die Inquiry-Engine serverseitig
                                // Name/E-Mail aus genau diesem Step-Typ verlangt.
                                'contact_form' => 'Kontaktformular',
                            ),
                            'default_value' => 'select',
                            'required' => 1,
                            'wrapper' => array('width' => '33'),
                        ),
                        
                        // Required
                        array(
                            'key' => 'field_step_required',
                            'label' => 'Pflichtfeld',
                            'name' => 'required',
                            'type' => 'true_false',
                            'default_value' => 1,
                            'ui' => 1,
                            'wrapper' => array('width' => '33'),
                        ),
                        
                        // Show in Summary
                        array(
                            'key' => 'field_step_show_in_summary',
                            'label' => 'In Zusammenfassung',
                            'name' => 'show_in_summary',
                            'type' => 'true_false',
                            'default_value' => 1,
                            'ui' => 1,
                            'wrapper' => array('width' => '34'),
                        ),
                        
                        // Description
                        array(
                            'key' => 'field_step_description',
                            'label' => 'Beschreibung',
                            'name' => 'description',
                            'type' => 'textarea',
                            'instructions' => 'Hilfetext für den Kunden (optional)',
                            'rows' => 2,
                        ),

                        // Als Filter verwenden
                        array(
                            'key' => 'field_step_use_as_filter',
                            'label' => 'Als Filter verwenden',
                            'name' => 'use_as_filter',
                            'type' => 'true_false',
                            'instructions' => 'Verknüpft die Options-Werte dieses Steps mit einem bestehenden WooCommerce-Attribut, damit das Produkt darüber in der Shop-Filter-Bar filterbar wird.',
                            'default_value' => 0,
                            'ui' => 1,
                            'conditional_logic' => array(
                                array(
                                    array('field' => 'field_step_type', 'operator' => '==', 'value' => 'select'),
                                ),
                                array(
                                    array('field' => 'field_step_type', 'operator' => '==', 'value' => 'radio'),
                                ),
                                array(
                                    array('field' => 'field_step_type', 'operator' => '==', 'value' => 'checkbox'),
                                ),
                                array(
                                    array('field' => 'field_step_type', 'operator' => '==', 'value' => 'color_picker'),
                                ),
                            ),
                            'wrapper' => array('width' => '50'),
                        ),
                        
                        // Verknüpftes Attribut
                        array(
                            'key' => 'field_step_filter_attribute',
                            'label' => 'Verknüpftes Attribut',
                            'name' => 'filter_attribute',
                            'type' => 'select',
                            'choices' => self::attribute_choices(),
                            'instructions' => 'Bestehendes WooCommerce-Attribut, dessen Terms aus den Bezeichnungen der Options dieses Steps gepflegt werden (bei Bedarf automatisch neu angelegt).',
                            'conditional_logic' => array(
                                array(
                                    array('field' => 'field_step_type', 'operator' => '==', 'value' => 'select'),
                                    array('field' => 'field_step_use_as_filter', 'operator' => '==', 'value' => '1'),
                                ),
                                array(
                                    array('field' => 'field_step_type', 'operator' => '==', 'value' => 'radio'),
                                    array('field' => 'field_step_use_as_filter', 'operator' => '==', 'value' => '1'),
                                ),
                                array(
                                    array('field' => 'field_step_type', 'operator' => '==', 'value' => 'checkbox'),
                                    array('field' => 'field_step_use_as_filter', 'operator' => '==', 'value' => '1'),
                                ),
                                array(
                                    array('field' => 'field_step_type', 'operator' => '==', 'value' => 'color_picker'),
                                    array('field' => 'field_step_use_as_filter', 'operator' => '==', 'value' => '1'),
                                ),
                            ),
                            'wrapper' => array('width' => '50'),
                        ),
                        
                        // Options (Repeater)
                        array(
                            'key' => 'field_step_options',
                            'label' => 'Optionen',
                            'name' => 'options',
                            'type' => 'repeater',
                            'layout' => 'table',
                            'button_label' => 'Option hinzufügen',
                            'conditional_logic' => array(
                                array(
                                    array(
                                        'field' => 'field_step_type',
                                        'operator' => '!=',
                                        'value' => 'text',
                                    ),
                                    array(
                                        'field' => 'field_step_type',
                                        'operator' => '!=',
                                        'value' => 'textarea',
                                    ),
                                    array(
                                        'field' => 'field_step_type',
                                        'operator' => '!=',
                                        'value' => 'number',
                                    ),
                                    array(
                                        'field' => 'field_step_type',
                                        'operator' => '!=',
                                        'value' => 'file_upload',
                                    ),
                                ),
                            ),
                            'sub_fields' => array(
                                
                                // Option Value
                                array(
                                    'key' => 'field_option_value',
                                    'label' => 'Wert',
                                    'name' => 'value',
                                    'type' => 'text',
                                    'required' => 1,
                                ),
                                
                                // Option Label
                                array(
                                    'key' => 'field_option_label',
                                    'label' => 'Bezeichnung',
                                    'name' => 'label',
                                    'type' => 'text',
                                    'required' => 1,
                                ),
                                
                                // Price Modifier
                                array(
                                    'key' => 'field_option_price',
                                    'label' => 'Preisaufschlag (€)',
                                    'name' => 'price_modifier',
                                    'type' => 'number',
                                    'step' => '0.01',
                                    'default_value' => 0,
                                ),
                                
                                // Image
                                array(
                                    'key' => 'field_option_image',
                                    'label' => 'Bild',
                                    'name' => 'image',
                                    'type' => 'image',
                                    'return_format' => 'array',
                                    'preview_size' => 'thumbnail',
                                ),
                                
                                // Available
                                array(
                                    'key' => 'field_option_available',
                                    'label' => 'Verfügbar',
                                    'name' => 'available',
                                    'type' => 'true_false',
                                    'default_value' => 1,
                                    'ui' => 1,
                                ),
                                
                                // Verknüpfter Attribut-Term (automatisch von filter-sync.php gepflegt)
                                array(
                                    'key' => 'field_option_filter_term_id',
                                    'label' => 'Verknüpfter Attribut-Term',
                                    'name' => 'filter_term_id',
                                    'type' => 'text',
                                    'instructions' => 'Wird automatisch gesetzt, sobald "Als Filter verwenden" am Step aktiv ist - Brücke zwischen dieser Option und dem WooCommerce-Attribut-Term. Nicht manuell befüllen, "value" bleibt davon unberührt.',
                                    'readonly' => 1,
                                    'wrapper' => array('width' => '50'),
                                ),
                            ),
                        ),
                        
                        // Conditional Logic
                        array(
                            'key' => 'field_step_conditions',
                            'label' => 'Bedingte Anzeige',
                            'name' => 'conditions',
                            'type' => 'repeater',
                            'instructions' => 'Zeige diesen Schritt nur wenn Bedingungen erfüllt sind',
                            'layout' => 'table',
                            'button_label' => 'Bedingung hinzufügen',
                            'sub_fields' => array(
                                
                                // Field
                                array(
                                    'key' => 'field_condition_field',
                                    'label' => 'Feld',
                                    'name' => 'field',
                                    'type' => 'text',
                                    'instructions' => 'Step ID des vorherigen Schritts',
                                    'placeholder' => 'z.B. material',
                                ),
                                
                                // Operator
                                array(
                                    'key' => 'field_condition_operator',
                                    'label' => 'Operator',
                                    'name' => 'operator',
                                    'type' => 'select',
                                    'choices' => array(
                                        '==' => 'ist gleich',
                                        '!=' => 'ist nicht',
                                    ),
                                    'default_value' => '==',
                                ),
                                
                                // Value
                                array(
                                    'key' => 'field_condition_value',
                                    'label' => 'Wert',
                                    'name' => 'value',
                                    'type' => 'text',
                                    'instructions' => 'Option-Wert zum Vergleich',
                                    'placeholder' => 'z.B. wood',
                                ),
                            ),
                        ),
                        
                        // Min/Max (for number inputs)
                        array(
                            'key' => 'field_step_min',
                            'label' => 'Minimum',
                            'name' => 'min_value',
                            'type' => 'number',
                            'conditional_logic' => array(
                                array(
                                    array(
                                        'field' => 'field_step_type',
                                        'operator' => '==',
                                        'value' => 'number',
                                    ),
                                ),
                            ),
                            'wrapper' => array('width' => '50'),
                        ),
                        
                        array(
                            'key' => 'field_step_max',
                            'label' => 'Maximum',
                            'name' => 'max_value',
                            'type' => 'number',
                            'conditional_logic' => array(
                                array(
                                    array(
                                        'field' => 'field_step_type',
                                        'operator' => '==',
                                        'value' => 'number',
                                    ),
                                ),
                            ),
                            'wrapper' => array('width' => '50'),
                        ),
                        
                        // File Upload Settings
                        array(
                            'key' => 'field_step_allowed_types',
                            'label' => 'Erlaubte Dateitypen',
                            'name' => 'allowed_file_types',
                            'type' => 'text',
                            'instructions' => 'Komma-getrennt: pdf,jpg,png,ai,eps',
                            'default_value' => 'pdf,jpg,png',
                            'conditional_logic' => array(
                                array(
                                    array(
                                        'field' => 'field_step_type',
                                        'operator' => '==',
                                        'value' => 'file_upload',
                                    ),
                                ),
                            ),
                            'wrapper' => array('width' => '50'),
                        ),
                        
                        array(
                            'key' => 'field_step_max_file_size',
                            'label' => 'Max. Dateigröße (MB)',
                            'name' => 'max_file_size',
                            'type' => 'number',
                            'default_value' => 10,
                            'conditional_logic' => array(
                                array(
                                    array(
                                        'field' => 'field_step_type',
                                        'operator' => '==',
                                        'value' => 'file_upload',
                                    ),
                                ),
                            ),
                            'wrapper' => array('width' => '50'),
                        ),
                    ),
                ),
                
                // Tier Pricing
                array(
                    'key' => 'field_tier_pricing',
                    'label' => 'Staffelpreise',
                    'name' => 'tier_pricing',
                    'type' => 'repeater',
                    'instructions' => 'Definiere Mengenrabatte',
                    'layout' => 'table',
                    'button_label' => 'Preisstufe hinzufügen',
                    'conditional_logic' => array(
                        array(
                            array(
                                'field' => 'field_is_configurable',
                                'operator' => '==',
                                'value' => '1',
                            ),
                        ),
                    ),
                    'sub_fields' => array(
                        
                        // Min Quantity
                        array(
                            'key' => 'field_tier_min_qty',
                            'label' => 'Ab Menge',
                            'name' => 'min_quantity',
                            'type' => 'number',
                            'required' => 1,
                            'min' => 1,
                        ),
                        
                        // Discount Percent
                        array(
                            'key' => 'field_tier_discount',
                            'label' => 'Rabatt (%)',
                            'name' => 'discount_percent',
                            'type' => 'number',
                            'required' => 1,
                            'min' => 0,
                            'max' => 100,
                            'step' => 0.1,
                        ),
                    ),
                ),
                
                // Show Tier Pricing Table
                array(
                    'key' => 'field_show_tier_table',
                    'label' => 'Staffelpreis-Tabelle anzeigen',
                    'name' => 'show_tier_table',
                    'type' => 'true_false',
                    'instructions' => 'Zeigt Kunden eine Übersicht der Mengenrabatte',
                    'default_value' => 1,
                    'ui' => 1,
                    'conditional_logic' => array(
                        array(
                            array(
                                'field' => 'field_is_configurable',
                                'operator' => '==',
                                'value' => '1',
                            ),
                        ),
                    ),
                ),
                
                // Minimum Order Quantity
                array(
                    'key' => 'field_min_order_qty',
                    'label' => 'Mindestbestellmenge',
                    'name' => 'min_order_quantity',
                    'type' => 'number',
                    'default_value' => 1,
                    'min' => 1,
                    'conditional_logic' => array(
                        array(
                            array(
                                'field' => 'field_is_configurable',
                                'operator' => '==',
                                'value' => '1',
                            ),
                        ),
                    ),
                    'wrapper' => array('width' => '50'),
                ),
                
                // Maximum Order Quantity
                array(
                    'key' => 'field_max_order_qty',
                    'label' => 'Maximalbestellmenge',
                    'name' => 'max_order_quantity',
                    'type' => 'number',
                    'default_value' => 10000,
                    'min' => 1,
                    'conditional_logic' => array(
                        array(
                            array(
                                'field' => 'field_is_configurable',
                                'operator' => '==',
                                'value' => '1',
                            ),
                        ),
                    ),
                    'wrapper' => array('width' => '50'),
                ),
                
                // Custom Inquiry for Large Orders
                array(
                    'key' => 'field_large_order_threshold',
                    'label' => 'Angebots-Schwellwert',
                    'name' => 'large_order_threshold',
                    'type' => 'number',
                    'instructions' => 'Ab dieser Menge: "Individuelles Angebot anfragen"',
                    'default_value' => 1000,
                    'min' => 0,
                    'conditional_logic' => array(
                        array(
                            array(
                                'field' => 'field_is_configurable',
                                'operator' => '==',
                                'value' => '1',
                            ),
                        ),
                    ),
                ),
                
                // Lead Time
                array(
                    'key' => 'field_production_time',
                    'label' => 'Produktionszeit (Werktage)',
                    'name' => 'production_time',
                    'type' => 'number',
                    'instructions' => 'Standard-Lieferzeit',
                    'default_value' => 10,
                    'min' => 1,
                    'conditional_logic' => array(
                        array(
                            array(
                                'field' => 'field_is_configurable',
                                'operator' => '==',
                                'value' => '1',
                            ),
                        ),
                    ),
                    'wrapper' => array('width' => '50'),
                ),
                
                // Express Options
                array(
                    'key' => 'field_express_available',
                    'label' => 'Express-Produktion verfügbar',
                    'name' => 'express_available',
                    'type' => 'true_false',
                    'default_value' => 0,
                    'ui' => 1,
                    'conditional_logic' => array(
                        array(
                            array(
                                'field' => 'field_is_configurable',
                                'operator' => '==',
                                'value' => '1',
                            ),
                        ),
                    ),
                    'wrapper' => array('width' => '50'),
                ),
                
                // Express Pricing
                array(
                    'key' => 'field_express_options',
                    'label' => 'Express-Optionen',
                    'name' => 'express_options',
                    'type' => 'repeater',
                    'layout' => 'table',
                    'button_label' => 'Express-Option hinzufügen',
                    'conditional_logic' => array(
                        array(
                            array(
                                'field' => 'field_express_available',
                                'operator' => '==',
                                'value' => '1',
                            ),
                        ),
                    ),
                    'sub_fields' => array(
                        array(
                            'key' => 'field_express_label',
                            'label' => 'Bezeichnung',
                            'name' => 'label',
                            'type' => 'text',
                            'placeholder' => 'z.B. Express (5 Tage)',
                        ),
                        array(
                            'key' => 'field_express_days',
                            'label' => 'Werktage',
                            'name' => 'days',
                            'type' => 'number',
                            'min' => 1,
                        ),
                        array(
                            'key' => 'field_express_price',
                            'label' => 'Aufpreis (€)',
                            'name' => 'price',
                            'type' => 'number',
                            'step' => '0.01',
                        ),
                    ),
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'product',
                    ),
                ),
            ),
            'menu_order' => 10,
            'position' => 'normal',
            'style' => 'default',
        ));
    }

    /**
     * Bestehende WooCommerce-Attribute als Choices - für 'field_step_filter_attribute'.
     * Bewusst eine eigene Kopie (nicht aus inc/filters/ importiert), damit der
     * Konfigurator ohne Abhängigkeit zum Filter-System funktioniert.
     */
    public static function attribute_choices(): array {
        $choices = array();
        if ( function_exists( 'wc_get_attribute_taxonomies' ) ) {
            foreach ( wc_get_attribute_taxonomies() as $attr ) {
                $slug = wc_attribute_taxonomy_name( $attr->attribute_name );
                $choices[ $slug ] = $attr->attribute_label . ' (' . $slug . ')';
            }
        }
        return $choices;
    }
}
