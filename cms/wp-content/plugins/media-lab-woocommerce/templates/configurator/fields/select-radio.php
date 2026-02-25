<?php
/**
 * Select/Radio Field Template
 * Vars: $step, $step_id, $step_type
 */

$options = $step['options'];
$is_radio = $step_type === 'radio';
?>

<div class="configurator-field configurator-field--<?php echo $is_radio ? 'radio' : 'select'; ?>">
    
    <?php if ($is_radio) : ?>
        <!-- Radio Buttons with Images -->
        <div class="configurator-options configurator-options--radio">
            <?php foreach ($options as $option) : 
                if (!$option['available']) continue;
            ?>
                <label class="configurator-option">
                    <input type="radio" 
                           name="config_<?php echo esc_attr($step_id); ?>"
                           :value="'<?php echo esc_attr($option['value']); ?>'"
                           x-model="config['<?php echo esc_js($step_id); ?>']"
                           @change="onFieldChange('<?php echo esc_js($step_id); ?>')">
                    
                    <div class="configurator-option__content">
                        <?php if (!empty($option['image'])) : ?>
                            <div class="configurator-option__image">
                                <img src="<?php echo esc_url($option['image']['sizes']['medium']); ?>" 
                                     alt="<?php echo esc_attr($option['label']); ?>">
                            </div>
                        <?php endif; ?>
                        
                        <div class="configurator-option__label">
                            <?php echo esc_html($option['label']); ?>
                        </div>
                        
                        <?php if ($option['price_modifier'] != 0) : ?>
                            <div class="configurator-option__price">
                                <?php echo $option['price_modifier'] > 0 ? '+' : ''; ?>
                                <?php echo wc_price($option['price_modifier']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </label>
            <?php endforeach; ?>
        </div>
        
    <?php else : ?>
        <!-- Dropdown Select -->
        <select class="configurator-select"
                x-model="config['<?php echo esc_js($step_id); ?>']"
                @change="onFieldChange('<?php echo esc_js($step_id); ?>')">
            <option value="">Bitte wählen...</option>
            <?php foreach ($options as $option) : 
                if (!$option['available']) continue;
            ?>
                <option value="<?php echo esc_attr($option['value']); ?>">
                    <?php echo esc_html($option['label']); ?>
                    <?php if ($option['price_modifier'] != 0) : ?>
                        (<?php echo $option['price_modifier'] > 0 ? '+' : ''; ?><?php echo wc_price($option['price_modifier']); ?>)
                    <?php endif; ?>
                </option>
            <?php endforeach; ?>
        </select>
    <?php endif; ?>
    
</div>

<style>
.configurator-options--radio {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1rem;
}

.configurator-option {
    position: relative;
    cursor: pointer;
    display: block;
}

.configurator-option input[type="radio"] {
    position: absolute;
    opacity: 0;
}

.configurator-option__content {
    border: 2px solid #e0e0e0;
    border-radius: 12px;
    padding: 1rem;
    text-align: center;
    transition: all 0.2s ease;
}

.configurator-option input:checked + .configurator-option__content {
    border-color: red;
    background: #fff5f5;
    box-shadow: 0 4px 12px rgba(255,0,0,0.2);
}

.configurator-option__content:hover {
    border-color: red;
    transform: translateY(-4px);
}

.configurator-option__image {
    width: 100%;
    height: 150px;
    margin-bottom: 1rem;
    border-radius: 8px;
    overflow: hidden;
}

.configurator-option__image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.configurator-option__label {
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.configurator-option__price {
    color: red;
    font-weight: 600;
    font-size: 14px;
}

.configurator-select {
    width: 100%;
    padding: 1rem;
    font-size: 16px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    background: #fff;
    transition: border-color 0.2s;
}

.configurator-select:focus {
    outline: none;
    border-color: red;
    box-shadow: 0 0 0 3px rgba(255,0,0,0.1);
}

@media (max-width: 768px) {
    .configurator-options--radio {
        grid-template-columns: 1fr;
    }
}
</style>
