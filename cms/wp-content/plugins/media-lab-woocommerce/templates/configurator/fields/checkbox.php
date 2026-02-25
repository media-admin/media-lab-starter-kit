<?php
/**
 * Checkbox Field Template (Multiple Selection)
 */

$options = $step['options'];
?>

<div class="configurator-field configurator-field--checkbox">
    <div class="configurator-checkboxes">
        <?php foreach ($options as $option) : 
            if (!$option['available']) continue;
        ?>
            <label class="configurator-checkbox">
                <input type="checkbox" 
                       :value="'<?php echo esc_attr($option['value']); ?>'"
                       x-model="config['<?php echo esc_js($step_id); ?>']"
                       @change="onFieldChange('<?php echo esc_js($step_id); ?>')">
                
                <div class="configurator-checkbox__content">
                    <?php if (!empty($option['image'])) : ?>
                        <div class="configurator-checkbox__image">
                            <img src="<?php echo esc_url($option['image']['sizes']['thumbnail']); ?>" 
                                 alt="<?php echo esc_attr($option['label']); ?>">
                        </div>
                    <?php endif; ?>
                    
                    <div class="configurator-checkbox__info">
                        <div class="configurator-checkbox__label">
                            <?php echo esc_html($option['label']); ?>
                        </div>
                        
                        <?php if ($option['price_modifier'] != 0) : ?>
                            <div class="configurator-checkbox__price">
                                <?php echo $option['price_modifier'] > 0 ? '+' : ''; ?>
                                <?php echo wc_price($option['price_modifier']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </label>
        <?php endforeach; ?>
    </div>
</div>

<style>
.configurator-checkboxes {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.configurator-checkbox {
    position: relative;
    cursor: pointer;
    display: block;
}

.configurator-checkbox input[type="checkbox"] {
    position: absolute;
    opacity: 0;
}

.configurator-checkbox__content {
    display: flex;
    align-items: center;
    gap: 1rem;
    border: 2px solid #e0e0e0;
    border-radius: 12px;
    padding: 1rem;
    transition: all 0.2s ease;
}

.configurator-checkbox input:checked + .configurator-checkbox__content {
    border-color: red;
    background: #fff5f5;
}

.configurator-checkbox__content:hover {
    border-color: red;
}

.configurator-checkbox__image {
    width: 60px;
    height: 60px;
    border-radius: 8px;
    overflow: hidden;
    flex-shrink: 0;
}

.configurator-checkbox__image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.configurator-checkbox__info {
    flex: 1;
}

.configurator-checkbox__label {
    font-weight: 600;
    margin-bottom: 0.25rem;
}

.configurator-checkbox__price {
    color: red;
    font-weight: 600;
    font-size: 14px;
}
</style>
