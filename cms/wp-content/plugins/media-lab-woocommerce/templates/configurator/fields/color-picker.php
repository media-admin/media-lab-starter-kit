<?php
/**
 * Color Picker Field Template
 */

$options = $step['options'];
?>

<div class="configurator-field configurator-field--color">
    <div class="configurator-colors">
        <?php foreach ($options as $option) : 
            if (!$option['available']) continue;
            
            // Versuche Farbe aus Option zu extrahieren
            $color_value = isset($option['color']) ? $option['color'] : '#' . $option['value'];
            $has_image = !empty($option['image']);
        ?>
            <label class="configurator-color">
                <input type="radio" 
                       name="config_<?php echo esc_attr($step_id); ?>"
                       :value="'<?php echo esc_attr($option['value']); ?>'"
                       x-model="config['<?php echo esc_js($step_id); ?>']"
                       @change="onFieldChange('<?php echo esc_js($step_id); ?>')">
                
                <div class="configurator-color__swatch">
                    <?php if ($has_image) : ?>
                        <img src="<?php echo esc_url($option['image']['sizes']['thumbnail']); ?>" 
                             alt="<?php echo esc_attr($option['label']); ?>"
                             class="configurator-color__image">
                    <?php else : ?>
                        <div class="configurator-color__circle" 
                             style="background: <?php echo esc_attr($color_value); ?>"></div>
                    <?php endif; ?>
                    
                    <span class="configurator-color__checkmark">✓</span>
                </div>
                
                <div class="configurator-color__label">
                    <?php echo esc_html($option['label']); ?>
                </div>
                
                <?php if ($option['price_modifier'] != 0) : ?>
                    <div class="configurator-color__price">
                        <?php echo $option['price_modifier'] > 0 ? '+' : ''; ?>
                        <?php echo wc_price($option['price_modifier']); ?>
                    </div>
                <?php endif; ?>
            </label>
        <?php endforeach; ?>
    </div>
</div>

<style>
.configurator-colors {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
    gap: 1.5rem;
}

.configurator-color {
    cursor: pointer;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.75rem;
}

.configurator-color input {
    position: absolute;
    opacity: 0;
}

.configurator-color__swatch {
    position: relative;
    width: 80px;
    height: 80px;
    border: 3px solid #e0e0e0;
    border-radius: 50%;
    overflow: hidden;
    transition: all 0.2s ease;
}

.configurator-color input:checked + .configurator-color__swatch {
    border-color: red;
    box-shadow: 0 4px 12px rgba(255,0,0,0.3);
    transform: scale(1.1);
}

.configurator-color__swatch:hover {
    border-color: red;
    transform: scale(1.05);
}

.configurator-color__circle {
    width: 100%;
    height: 100%;
    border-radius: 50%;
}

.configurator-color__image {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.configurator-color__checkmark {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 32px;
    color: #fff;
    text-shadow: 0 2px 4px rgba(0,0,0,0.5);
    opacity: 0;
    transition: opacity 0.2s ease;
}

.configurator-color input:checked + .configurator-color__swatch .configurator-color__checkmark {
    opacity: 1;
}

.configurator-color__label {
    font-weight: 600;
    font-size: 14px;
    color: #333;
}

.configurator-color__price {
    font-size: 12px;
    color: red;
    font-weight: 600;
}

@media (max-width: 768px) {
    .configurator-colors {
        grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
        gap: 1rem;
    }
    
    .configurator-color__swatch {
        width: 60px;
        height: 60px;
    }
}
</style>
