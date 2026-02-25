<?php
/**
 * Number Field Template
 */

$min = isset($step['min_value']) ? $step['min_value'] : 1;
$max = isset($step['max_value']) ? $step['max_value'] : 10000;

// Check if this is the quantity step
$is_quantity = ($step_id === 'quantity');
?>

<div class="configurator-field configurator-field--number">
    <div class="configurator-number">
        <button type="button" 
                class="configurator-number__btn configurator-number__btn--minus"
                @click="decrementNumber('<?php echo esc_js($step_id); ?>', <?php echo $min; ?>)">
            −
        </button>
        
        <input type="number" 
               class="configurator-number__input"
               x-model.number="config['<?php echo esc_js($step_id); ?>']"
               @input="onFieldChange('<?php echo esc_js($step_id); ?>')"
               min="<?php echo esc_attr($min); ?>"
               max="<?php echo esc_attr($max); ?>"
               step="1"
               placeholder="Anzahl eingeben">
        
        <button type="button" 
                class="configurator-number__btn configurator-number__btn--plus"
                @click="incrementNumber('<?php echo esc_js($step_id); ?>', <?php echo $max; ?>)">
            +
        </button>
    </div>
    
    <?php if ($is_quantity) : ?>
        <div class="configurator-number__hint">
            Mindestbestellmenge: <?php echo $min; ?> Stück
        </div>
    <?php endif; ?>
</div>

<style>
.configurator-number {
    display: flex;
    align-items: center;
    gap: 1rem;
    max-width: 300px;
    margin: 0 auto;
}

.configurator-number__btn {
    width: 50px;
    height: 50px;
    background: red;
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: 24px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s ease;
}

.configurator-number__btn:hover {
    background: #c00;
    transform: scale(1.1);
}

.configurator-number__btn:active {
    transform: scale(0.95);
}

.configurator-number__input {
    flex: 1;
    text-align: center;
    font-size: 24px;
    font-weight: 700;
    padding: 1rem;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
}

.configurator-number__input:focus {
    outline: none;
    border-color: red;
    box-shadow: 0 0 0 3px rgba(255,0,0,0.1);
}

.configurator-number__hint {
    text-align: center;
    margin-top: 1rem;
    color: #666;
    font-size: 14px;
}
</style>
