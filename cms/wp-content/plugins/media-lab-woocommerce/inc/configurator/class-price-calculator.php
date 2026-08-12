<?php
/**
 * Price Calculator with Tier Pricing
 */

if (!defined('ABSPATH')) exit;

class MediaLab_Price_Calculator {
    
    private $product_id;
    private $base_price;
    private $steps;
    
    public function __construct($product_id) {
        $this->product_id = $product_id;
        $product = wc_get_product($product_id);
        $this->base_price = floatval($product->get_regular_price());
        $this->steps = get_field('config_steps', $product_id);
    }
    
    /**
     * Calculate total price
     */
    public function calculate($config) {
        $breakdown = $this->get_breakdown($config);
        return $breakdown['total'];
    }
    
    /**
     * Get detailed price breakdown.
     *
     * WICHTIG: Nutzt konsequent WooCommerce's eigene Steuer-Funktionen
     * (wc_get_price_excluding_tax()/wc_get_price_including_tax()) statt
     * eines hartcodierten Steuersatzes. Dadurch werden automatisch korrekt
     * berücksichtigt: die Steuerklasse des jeweiligen Produkts, ob Preise im
     * Shop netto oder brutto eingegeben werden (wc_prices_include_tax()),
     * die in WooCommerce hinterlegten Steuersätze (Land/Zone), und WP
     * Germanized (hakt sich über dieselben Standard-WooCommerce-Filter/
     * -Funktionen ein wie jede andere Preisausgabe im Shop).
     */
    public function get_breakdown($config) {
        $product = wc_get_product($this->product_id);

        $breakdown = array(
            'base_price' => $this->base_price,
            'additions' => array(),
            'subtotal' => $this->base_price,
            'quantity' => isset($config['quantity']) ? intval($config['quantity']) : 1,
            'tier_discount' => 0,
            'tier_discount_percent' => 0,
            'total_before_tax' => 0,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'total' => 0,
            'unit_price' => 0,
            'tax_display_mode' => get_option('woocommerce_tax_display_shop', 'excl'), // 'incl' oder 'excl' - Shop-Einstellung
            'price_suffix' => '',
        );
        
        // Calculate additions from each step
        foreach ($this->steps as $step) {
            $step_id = $step['step_id'];
            
            if (!isset($config[$step_id])) continue;
            
            $selected_value = $config[$step_id];
            $options = $step['options'];

            // Manche Step-Typen (contact_form, file_upload, textarea) haben
            // bewusst KEINE Optionen (options === false) - ohne diese Prüfung
            // crasht foreach() darauf mit einer PHP-Warnung.
            if ( ! is_array( $options ) ) continue;

            // size_matrix braucht eine eigene Berechnung: pro Größe eine
            // EIGENE Menge, daher ein nach Menge GEWICHTETER Durchschnitts-
            // Aufpreis pro Stück statt eines einzelnen Auswahl-Aufpreises wie
            // bei select/radio/checkbox. Die generische matches_selection()-
            // Logik darunter kann das nicht abbilden (sie vergleicht einzelne
            // Werte, hier ist $selected_value aber eine Größe→Menge-Zuordnung).
            if ( $step['step_type'] === 'size_matrix' && is_array( $selected_value ) ) {
                $total_qty = 0;
                $weighted_sum = 0;
                foreach ( $options as $option ) {
                    $size_key = $option['value'];
                    $qty = isset( $selected_value[ $size_key ] ) ? intval( $selected_value[ $size_key ] ) : 0;
                    if ( $qty <= 0 ) continue;

                    $total_qty += $qty;
                    $price_modifier = floatval( $option['price_modifier'] );
                    $weighted_sum  += $price_modifier * $qty;

                    if ( $price_modifier != 0 ) {
                        $breakdown['additions'][] = array(
                            'label' => $option['label'] . ' (' . $qty . 'x)',
                            'price' => $price_modifier,
                        );
                    }
                }
                if ( $total_qty > 0 ) {
                    $breakdown['subtotal'] += $weighted_sum / $total_qty;
                }
                continue; // Für diesen Step ist die generische Logik unten nicht zutreffend.
            }
            
            // Find selected option and add price
            foreach ($options as $option) {
                if ($this->matches_selection($option, $selected_value)) {
                    $price_modifier = floatval($option['price_modifier']);
                    
                    if ($price_modifier != 0) {
                        $breakdown['additions'][] = array(
                            'label' => $option['label'],
                            'price' => $price_modifier,
                        );
                        $breakdown['subtotal'] += $price_modifier;
                    }
                }
            }
        }
        
        // Apply tier pricing
        $quantity = $breakdown['quantity'];
        $tier_data = $this->get_tier_discount($quantity);
        $breakdown['tier_discount_percent'] = $tier_data['discount_percent'];
        $breakdown['tier_discount'] = $breakdown['subtotal'] * $tier_data['discount_percent'];

        // Preis pro Stück nach Rabatt, in derselben Basis wie der Produktpreis
        // im Shop eingegeben wird (netto oder brutto, je nach wc_prices_include_tax()).
        $unit_price_after_discount = $breakdown['subtotal'] - $breakdown['tier_discount'];

        if ( $product && wc_tax_enabled() && $product->is_taxable() ) {
            // WooCommerce übernimmt die komplette Steuer-Logik (Steuerklasse,
            // Land/Zone, Rundungsregeln) - wir geben nur den Preis pro Stück
            // und die Menge mit, WooCommerce macht daraus netto/brutto korrekt.
            $breakdown['total_before_tax'] = wc_get_price_excluding_tax( $product, array( 'qty' => $quantity, 'price' => $unit_price_after_discount ) );
            $total_incl_tax                = wc_get_price_including_tax( $product, array( 'qty' => $quantity, 'price' => $unit_price_after_discount ) );
            $breakdown['tax_amount']       = $total_incl_tax - $breakdown['total_before_tax'];

            $unit_net   = wc_get_price_excluding_tax( $product, array( 'qty' => 1, 'price' => $unit_price_after_discount ) );
            $unit_gross = wc_get_price_including_tax( $product, array( 'qty' => 1, 'price' => $unit_price_after_discount ) );

            // Tatsächlichen Steuersatz aus WooCommerce ermitteln (für die Anzeige "MwSt. (X%)")
            $tax_rates = WC_Tax::get_rates( $product->get_tax_class() );
            $first_rate = ! empty( $tax_rates ) ? reset( $tax_rates ) : null;
            $breakdown['tax_rate'] = $first_rate ? round( (float) $first_rate['rate'], 2 ) : 0;
        } else {
            // Steuern in WooCommerce deaktiviert oder Produkt nicht steuerpflichtig
            $breakdown['total_before_tax'] = $unit_price_after_discount * $quantity;
            $breakdown['tax_amount']       = 0;
            $unit_net   = $unit_price_after_discount;
            $unit_gross = $unit_price_after_discount;
            $breakdown['tax_rate'] = 0;
        }

        $breakdown['total'] = $breakdown['total_before_tax'] + $breakdown['tax_amount'];

        // Anzeige-Preis pro Stück entsprechend der Shop-Einstellung wählen
        // (woocommerce_tax_display_shop: 'incl' oder 'excl'), damit die
        // Live-Vorschau/Staffeltabelle exakt dasselbe zeigt wie der Rest des Shops.
        $breakdown['unit_price'] = $breakdown['tax_display_mode'] === 'incl' ? $unit_gross : $unit_net;

        // Standard-WooCommerce-Hinweismechanismus (wc_get_price_suffix()) - läuft
        // bewusst UNABHÄNGIG von wc_tax_enabled(), da der Hinweistext auch bei
        // deaktivierten Steuern erscheinen soll (siehe inc/price-suffix.php).
        // WICHTIG: wc_get_price_suffix() gehört zu WooCommerce's Frontend-
        // Funktionen, die bei reinen Ajax-Requests (admin-ajax.php) nicht
        // zuverlässig geladen sind - das führte zu einem fatalen "Call to
        // undefined function"-Fehler bei JEDER Ajax-Preisberechnung und jedem
        // Absenden (Anfrage & Wunschliste). Datei bei Bedarf gezielt nachladen,
        // statt den Hinweistext in der Live-Vorschau einfach wegzulassen.
        if ( ! function_exists( 'wc_get_price_suffix' ) && defined( 'WC_ABSPATH' ) ) {
            $wc_price_functions = WC_ABSPATH . 'includes/wc-price-functions.php';
            if ( file_exists( $wc_price_functions ) ) {
                require_once $wc_price_functions;
            }
        }
        if ( $product && function_exists( 'wc_get_price_suffix' ) ) {
            $breakdown['price_suffix'] = wc_get_price_suffix( $product, $breakdown['unit_price'], 1 );
        }
        
        return $breakdown;
    }

    /**
     * Liefert für jede Preisstufe (get_all_tiers()) den korrekten Preis pro
     * Stück - netto, brutto und den zur Shop-Anzeigeeinstellung passenden
     * Wert - basierend auf dem übergebenen Preis pro Stück VOR Mengenrabatt
     * (also demselben Wert wie $breakdown['subtotal'] aus get_breakdown()).
     *
     * Behebt den Staffelpreis-Doppelsteuer-Bug: configurator.js
     * (calculateTierPrice()) hat den client-seitig übergebenen subtotal-Wert
     * bislang IMMER als netto behandelt und bei Bruttopreis-Anzeige selbst
     * Steuer aufgeschlagen. Bei Bruttopreis-EINGABE
     * (woocommerce_prices_include_tax = yes, üblich bei DACH-B2C-Shops) ist
     * subtotal aber bereits brutto - die Steuer wurde dadurch ein zweites
     * Mal aufgeschlagen. Diese Methode nutzt stattdessen dieselben
     * WooCommerce-Steuerfunktionen wie get_breakdown() (keine geratene
     * Netto/Brutto-Logik mehr), sodass der Client den fertig berechneten
     * Wert nur noch nachschlagen muss (siehe calculateTierPrice() in
     * configurator.js und tiers_with_prices in ajax_calculate_price()).
     *
     * @param float $subtotal Preis pro Stück vor Mengenrabatt (Basispreis + Aufschläge).
     * @return array<int,array{min_quantity:int,discount_percent:float,unit_price_net:float,unit_price_gross:float,unit_price:float}>
     */
    public function get_tiers_with_prices( float $subtotal ): array {
        $product     = wc_get_product( $this->product_id );
        $tiers       = $this->get_all_tiers();
        $tax_display = get_option( 'woocommerce_tax_display_shop', 'excl' );
        $result      = [];

        foreach ( $tiers as $tier ) {
            $discount_percent    = floatval( $tier['discount_percent'] ) / 100;
            $unit_after_discount = $subtotal * ( 1 - $discount_percent );

            if ( $product && wc_tax_enabled() && $product->is_taxable() ) {
                $unit_net   = wc_get_price_excluding_tax( $product, array( 'qty' => 1, 'price' => $unit_after_discount ) );
                $unit_gross = wc_get_price_including_tax( $product, array( 'qty' => 1, 'price' => $unit_after_discount ) );
            } else {
                $unit_net   = $unit_after_discount;
                $unit_gross = $unit_after_discount;
            }

            $result[] = array(
                'min_quantity'     => (int) $tier['min_quantity'],
                'discount_percent' => floatval( $tier['discount_percent'] ),
                'unit_price_net'   => $unit_net,
                'unit_price_gross' => $unit_gross,
                // Anzeige-Preis entsprechend derselben Shop-Einstellung wie
                // unit_price in get_breakdown() - damit Live-Vorschau und
                // Staffeltabelle immer konsistent zueinander sind.
                'unit_price'       => $tax_display === 'incl' ? $unit_gross : $unit_net,
            );
        }

        return $result;
    }
    
    /**
     * Check if option matches selection
     */
    private function matches_selection($option, $selected_value) {
        if (is_array($selected_value)) {
            return in_array($option['value'], $selected_value);
        }
        return $option['value'] === $selected_value;
    }
    
    /**
     * Get tier discount
     */
    private function get_tier_discount($quantity) {
        // Get tier pricing from ACF or use defaults
        $tier_pricing = get_field('tier_pricing', $this->product_id);
        
        if (!$tier_pricing) {
            // Default tiers
            $tier_pricing = array(
                array('min_quantity' => 1, 'discount_percent' => 0),
                array('min_quantity' => 50, 'discount_percent' => 0),
                array('min_quantity' => 100, 'discount_percent' => 10),
                array('min_quantity' => 250, 'discount_percent' => 15),
                array('min_quantity' => 500, 'discount_percent' => 20),
                array('min_quantity' => 1000, 'discount_percent' => 25),
            );
        }
        
        $applicable_discount = 0;
        $applicable_tier = null;
        
        foreach ($tier_pricing as $tier) {
            if ($quantity >= $tier['min_quantity']) {
                $applicable_discount = floatval($tier['discount_percent']) / 100;
                $applicable_tier = $tier;
            }
        }
        
        return array(
            'discount_percent' => $applicable_discount,
            'tier' => $applicable_tier,
        );
    }
    
    /**
     * Get all available tiers for display
     */
    public function get_all_tiers() {
        $tier_pricing = get_field('tier_pricing', $this->product_id);
        
        if (!$tier_pricing) {
            return array(
                array('min_quantity' => 1, 'discount_percent' => 0),
                array('min_quantity' => 50, 'discount_percent' => 0),
                array('min_quantity' => 100, 'discount_percent' => 10),
                array('min_quantity' => 250, 'discount_percent' => 15),
                array('min_quantity' => 500, 'discount_percent' => 20),
                array('min_quantity' => 1000, 'discount_percent' => 25),
            );
        }
        
        return $tier_pricing;
    }
    
    /**
     * Format price for display
     */
    public static function format_price($price) {
        return wc_price($price);
    }
}
