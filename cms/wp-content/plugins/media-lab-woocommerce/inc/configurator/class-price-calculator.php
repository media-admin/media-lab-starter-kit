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
     * Get detailed price breakdown
     */
    public function get_breakdown($config) {
        $breakdown = array(
            'base_price' => $this->base_price,
            'additions' => array(),
            'subtotal' => $this->base_price,
            'quantity' => isset($config['quantity']) ? intval($config['quantity']) : 1,
            'tier_discount' => 0,
            'tier_discount_percent' => 0,
            'total_before_tax' => 0,
            'tax_rate' => 20,
            'tax_amount' => 0,
            'total' => 0,
        );
        
        // Calculate additions from each step
        foreach ($this->steps as $step) {
            $step_id = $step['step_id'];
            
            if (!isset($config[$step_id])) continue;
            
            $selected_value = $config[$step_id];
            $options = $step['options'];
            
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
        
        // Calculate total
        $breakdown['total_before_tax'] = ($breakdown['subtotal'] - $breakdown['tier_discount']) * $quantity;
        $breakdown['tax_amount'] = $breakdown['total_before_tax'] * ($breakdown['tax_rate'] / 100);
        $breakdown['total'] = $breakdown['total_before_tax'] + $breakdown['tax_amount'];
        
        // Price per unit after discount
        $breakdown['unit_price'] = $breakdown['subtotal'] - ($breakdown['subtotal'] * $tier_data['discount_percent']);
        
        return $breakdown;
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
