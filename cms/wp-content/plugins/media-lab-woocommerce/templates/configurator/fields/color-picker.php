<?php
/**
 * Color Picker Field Template
 *
 * Farbfelder statt Namens-Buttons. Den Farbwert liefert der Filter 'mlw_configurator_color_value'
 * (CSS-Wert für background: Farbname, Hex-Wert oder Verlauf) oder, falls vorhanden, $option['color'].
 * Ohne Farbwert und ohne Bild bleibt die Option ein Button mit dem Namen. Es werden keine Farben geraten.
 * Der Name der gewählten Farbe steht unter den Feldern; jede Option hat zusätzlich title und Screenreader-Text.
 *
 * Filter: apply_filters( 'mlw_configurator_color_value', string $css_value, array $option, array $step )
 */

$items = [];
$names = [];

foreach ( (array) $step['options'] as $option ) {
    if ( empty( $option['available'] ) ) {
        continue;
    }

    $swatch = ( isset( $option['color'] ) && $option['color'] !== '' ) ? (string) $option['color'] : '';
    if ( $swatch === '' ) {
        $swatch = (string) apply_filters( 'mlw_configurator_color_value', '', $option, $step );
    }

    $image_url = ! empty( $option['image'] ) ? (string) ( $option['image']['sizes']['thumbnail'] ?? '' ) : '';

    $items[] = [
        'option' => $option,
        'swatch' => $swatch,
        'image'  => $image_url,
    ];
    $names[ (string) $option['value'] ] = (string) $option['label'];
}
?>

<div class="configurator-field configurator-field--color"
     x-data="{ colorNames: <?php echo esc_attr( wp_json_encode( $names ) ); ?> }">
    <div class="configurator-colors" role="radiogroup" aria-label="<?php echo esc_attr( $step['step_label'] ?? '' ); ?>">
        <?php foreach ( $items as $item ) :
            $option = $item['option'];
            $label  = (string) $option['label'];
            $kind   = $item['image'] !== '' ? 'image' : ( $item['swatch'] !== '' ? 'color' : 'text' );
        ?>
            <label class="configurator-color configurator-color--<?php echo esc_attr( $kind ); ?>"
                   title="<?php echo esc_attr( $label ); ?>">
                <input type="radio"
                       name="config_<?php echo esc_attr( $step_id ); ?>"
                       :value="'<?php echo esc_js( $option['value'] ); ?>'"
                       x-model="config['<?php echo esc_js( $step_id ); ?>']"
                       @change="onFieldChange('<?php echo esc_js( $step_id ); ?>')">

                <?php if ( $kind === 'image' ) : ?>
                    <span class="configurator-color__swatch">
                        <img src="<?php echo esc_url( $item['image'] ); ?>" alt="" class="configurator-color__image">
                    </span>
                    <span class="configurator-color__sr"><?php echo esc_html( $label ); ?></span>
                <?php elseif ( $kind === 'color' ) : ?>
                    <span class="configurator-color__swatch"
                          style="--mlw-swatch: <?php echo esc_attr( $item['swatch'] ); ?>"></span>
                    <span class="configurator-color__sr"><?php echo esc_html( $label ); ?></span>
                <?php else : ?>
                    <span class="configurator-color__text"><?php echo esc_html( $label ); ?></span>
                <?php endif; ?>

                <?php if ( ! empty( $option['price_modifier'] ) && (float) $option['price_modifier'] !== 0.0 ) : ?>
                    <span class="configurator-color__price">
                        <?php echo (float) $option['price_modifier'] > 0 ? '+' : ''; ?>
                        <?php echo wc_price( $option['price_modifier'] ); ?>
                    </span>
                <?php endif; ?>
            </label>
        <?php endforeach; ?>
    </div>

    <p class="configurator-colors__selected" aria-live="polite" style="display: none"
       x-show="config['<?php echo esc_js( $step_id ); ?>']"
       x-text="colorNames[config['<?php echo esc_js( $step_id ); ?>']] || ''"></p>
</div>

<style>
.configurator-colors {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
}

.configurator-color {
    position: relative;
    display: inline-flex;
    flex-direction: column;
    align-items: center;
    gap: 0.25rem;
    cursor: pointer;
}

.configurator-color input {
    position: absolute;
    width: 1px;
    height: 1px;
    margin: 0;
    opacity: 0;
    pointer-events: none;
}

.configurator-color__sr {
    position: absolute;
    width: 1px;
    height: 1px;
    overflow: hidden;
    clip: rect(0 0 0 0);
    white-space: nowrap;
}

.configurator-color__swatch {
    display: block;
    width: 2.5rem;
    height: 2.5rem;
    border: 1px solid var(--color-border);
    border-radius: 50%;
    overflow: hidden;
    background: var(--mlw-swatch, transparent);
    transition: box-shadow 0.15s ease, transform 0.15s ease;
}

.configurator-color:hover .configurator-color__swatch {
    transform: scale(1.08);
}

.configurator-color input:checked + .configurator-color__swatch {
    box-shadow: 0 0 0 2px var(--color-card-bg), 0 0 0 4px var(--color-text);
}

.configurator-color__image {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.configurator-color__text {
    display: inline-block;
    padding: 0.5rem 0.875rem;
    border: 1px solid var(--color-border);
    border-radius: 0.5rem;
    background: transparent;
    color: var(--color-text);
    font-size: 0.875rem;
    font-weight: 600;
    transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease;
}

.configurator-color:hover .configurator-color__text {
    border-color: var(--color-text);
}

.configurator-color input:checked + .configurator-color__text {
    border-color: var(--color-text);
    background: var(--color-text);
    color: var(--color-card-bg);
}

.configurator-color input:focus-visible + .configurator-color__swatch,
.configurator-color input:focus-visible + .configurator-color__text {
    outline: 2px solid var(--color-text);
    outline-offset: 4px;
}

.configurator-color__price {
    color: var(--color-text-muted);
    font-size: 0.75rem;
    font-weight: 600;
}

.configurator-colors__selected {
    margin: 0.75rem 0 0;
    color: var(--color-text);
    font-size: 0.875rem;
    font-weight: 600;
}
</style>
