<?php
/**
 * Text/Textarea Field Template
 */

$is_textarea = $step_type === 'textarea';
?>

<div class="configurator-field configurator-field--text">
    <?php if ($is_textarea) : ?>
        <textarea class="configurator-textarea"
                  x-model="config['<?php echo esc_js($step_id); ?>']"
                  @input="onFieldChange('<?php echo esc_js($step_id); ?>')"
                  rows="5"
                  placeholder="Ihre Eingabe..."></textarea>
    <?php else : ?>
        <input type="text" 
               class="configurator-input"
               x-model="config['<?php echo esc_js($step_id); ?>']"
               @input="onFieldChange('<?php echo esc_js($step_id); ?>')"
               placeholder="Ihre Eingabe...">
    <?php endif; ?>
</div>

<style>
.configurator-input,
.configurator-textarea {
    width: 100%;
    padding: 1rem;
    font-size: 16px;
    font-family: inherit;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    transition: border-color 0.2s;
}

.configurator-input:focus,
.configurator-textarea:focus {
    outline: none;
    border-color: red;
    box-shadow: 0 0 0 3px rgba(255,0,0,0.1);
}

.configurator-textarea {
    resize: vertical;
    min-height: 120px;
}
</style>
