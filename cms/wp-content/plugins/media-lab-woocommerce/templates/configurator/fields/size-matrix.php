<?php
/**
 * Size Matrix Field Template
 * Für Textilien: Mehrere Größen mit unterschiedlichen Mengen
 */

$options = $step['options']; // Größen (S, M, L, XL, etc.)
?>

<div class="configurator-field configurator-field--size-matrix">
    <div class="configurator-size-matrix">
        
        <table class="size-matrix-table">
            <thead>
                <tr>
                    <th>Größe</th>
                    <th>Aufpreis</th>
                    <th>Menge</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($options as $option) : 
                    if (!$option['available']) continue;
                    $size_key = $option['value'];
                ?>
                    <tr>
                        <td class="size-matrix-table__size">
                            <strong><?php echo esc_html($option['label']); ?></strong>
                        </td>
                        <td class="size-matrix-table__price">
                            <?php if ($option['price_modifier'] != 0) : ?>
                                <?php echo $option['price_modifier'] > 0 ? '+' : ''; ?>
                                <?php echo wc_price($option['price_modifier']); ?>
                            <?php else : ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td class="size-matrix-table__quantity">
                            <div class="size-matrix-quantity">
                                <button type="button" 
                                        class="size-matrix-quantity__btn"
                                        @click="decrementSize('<?php echo esc_js($step_id); ?>', '<?php echo esc_js($size_key); ?>')">
                                    −
                                </button>
                                
                                <input type="number" 
                                       class="size-matrix-quantity__input"
                                       x-model.number="config['<?php echo esc_js($step_id); ?>']['<?php echo esc_js($size_key); ?>']"
                                       @input="onSizeChange('<?php echo esc_js($step_id); ?>')"
                                       min="0"
                                       step="1"
                                       placeholder="0">
                                
                                <button type="button" 
                                        class="size-matrix-quantity__btn"
                                        @click="incrementSize('<?php echo esc_js($step_id); ?>', '<?php echo esc_js($size_key); ?>')">
                                    +
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="size-matrix-total">
                    <td colspan="2"><strong>Gesamtmenge:</strong></td>
                    <td>
                        <strong x-text="getSizeMatrixTotal('<?php echo esc_js($step_id); ?>') + ' Stück'"></strong>
                    </td>
                </tr>
            </tfoot>
        </table>
        
        <div class="size-matrix-hint">
            💡 Tipp: Geben Sie die gewünschte Menge für jede Größe ein
        </div>
        
    </div>
</div>

<style>
.configurator-size-matrix {
    max-width: 600px;
    margin: 0 auto;
}

.size-matrix-table {
    width: 100%;
    border-collapse: collapse;
    background: #fff;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.size-matrix-table thead {
    background: #f8f9fa;
}

.size-matrix-table th {
    padding: 1rem;
    text-align: left;
    font-weight: 600;
    border-bottom: 2px solid #e0e0e0;
}

.size-matrix-table tbody tr {
    border-bottom: 1px solid #f0f0f0;
}

.size-matrix-table tbody tr:hover {
    background: #f8f9fa;
}

.size-matrix-table td {
    padding: 1rem;
}

.size-matrix-table__size {
    font-size: 18px;
}

.size-matrix-table__price {
    color: red;
    font-weight: 600;
}

.size-matrix-quantity {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.size-matrix-quantity__btn {
    width: 36px;
    height: 36px;
    background: red;
    color: #fff;
    border: none;
    border-radius: 6px;
    font-size: 18px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s ease;
}

.size-matrix-quantity__btn:hover {
    background: #c00;
    transform: scale(1.1);
}

.size-matrix-quantity__btn:active {
    transform: scale(0.95);
}

.size-matrix-quantity__input {
    width: 80px;
    text-align: center;
    padding: 0.5rem;
    border: 2px solid #e0e0e0;
    border-radius: 6px;
    font-size: 16px;
    font-weight: 600;
}

.size-matrix-quantity__input:focus {
    outline: none;
    border-color: red;
}

.size-matrix-total {
    background: #f8f9fa;
    border-top: 2px solid #e0e0e0;
}

.size-matrix-total td {
    padding: 1.5rem 1rem;
    font-size: 18px;
}

.size-matrix-hint {
    margin-top: 1rem;
    padding: 1rem;
    background: #fff3cd;
    border-radius: 8px;
    text-align: center;
    color: #856404;
    font-size: 14px;
}

@media (max-width: 768px) {
    .size-matrix-table th,
    .size-matrix-table td {
        padding: 0.75rem 0.5rem;
        font-size: 14px;
    }
    
    .size-matrix-quantity__input {
        width: 60px;
    }
}
</style>
