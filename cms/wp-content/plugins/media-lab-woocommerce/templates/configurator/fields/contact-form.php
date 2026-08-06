<?php
/**
 * Contact Form Field Template
 *
 * Name, E-Mail, Telefon sind Basisfelder (bewusst weiterhin fest, da sie die
 * Inquiry-Engine immer erwartet). Zusätzliche, projektspezifisch konfigurierte
 * Felder sowie die Datenschutz-Zustimmung werden dynamisch aus den
 * Inquiry-Einstellungen gerendert - siehe inc/inquiry/class-settings.php.
 */
$mlw_extra_fields     = class_exists( 'MediaLab_Inquiry_Settings' ) ? MediaLab_Inquiry_Settings::get_form_fields_localized() : [];
$mlw_privacy_required = class_exists( 'MediaLab_Inquiry_Settings' ) ? MediaLab_Inquiry_Settings::privacy_required() : false;
$mlw_privacy_text     = class_exists( 'MediaLab_Inquiry_Settings' ) ? MediaLab_Inquiry_Settings::privacy_text() : '';
?>

<div class="configurator-field configurator-field--contact">
    <div class="configurator-contact-form">
        
        <!-- Name -->
        <div class="configurator-form-group">
            <label class="configurator-form-label" for="customer_name">
                Name <span class="required">*</span>
            </label>
            <input type="text" 
                   id="customer_name"
                   class="configurator-form-input"
                   x-model="config['customer_name']"
                   @input="onFieldChange('customer_name')"
                   placeholder="Ihr vollständiger Name"
                   required>
        </div>
        
        <!-- E-Mail -->
        <div class="configurator-form-group">
            <label class="configurator-form-label" for="customer_email">
                E-Mail <span class="required">*</span>
            </label>
            <input type="email" 
                   id="customer_email"
                   class="configurator-form-input"
                   x-model="config['customer_email']"
                   @input="onFieldChange('customer_email')"
                   placeholder="ihre@email.de"
                   required>
        </div>
        
        <!-- Telefon -->
        <div class="configurator-form-group">
            <label class="configurator-form-label" for="customer_phone">
                Telefon
            </label>
            <input type="tel" 
                   id="customer_phone"
                   class="configurator-form-input"
                   x-model="config['customer_phone']"
                   @input="onFieldChange('customer_phone')"
                   placeholder="+43 123 456789">
        </div>

        <?php foreach ( $mlw_extra_fields as $field ) :
            $key         = esc_attr( $field['field_key'] ?? '' );
            $label       = esc_html( $field['label'] ?? $key );
            $required    = ! empty( $field['required'] );
            $placeholder = esc_attr( $field['placeholder'] ?? '' );
            if ( ! $key ) continue;
        ?>
            <div class="configurator-form-group">
                <label class="configurator-form-label" for="mlw_field_<?php echo $key; ?>">
                    <?php echo $label; ?><?php if ( $required ) : ?> <span class="required">*</span><?php endif; ?>
                </label>
                <?php if ( ( $field['field_type'] ?? 'text' ) === 'textarea' ) : ?>
                    <textarea id="mlw_field_<?php echo $key; ?>" class="configurator-form-input" rows="3"
                        x-model="config['<?php echo $key; ?>']" @input="onFieldChange('<?php echo $key; ?>')"
                        placeholder="<?php echo $placeholder; ?>" <?php echo $required ? 'required' : ''; ?>></textarea>
                <?php elseif ( ( $field['field_type'] ?? 'text' ) === 'select' ) : ?>
                    <select id="mlw_field_<?php echo $key; ?>" class="configurator-form-input"
                        x-model="config['<?php echo $key; ?>']" @change="onFieldChange('<?php echo $key; ?>')"
                        <?php echo $required ? 'required' : ''; ?>>
                        <option value=""></option>
                        <?php foreach ( array_filter( array_map( 'trim', explode( ',', (string) ( $field['options'] ?? '' ) ) ) ) as $option ) : ?>
                            <option value="<?php echo esc_attr( $option ); ?>"><?php echo esc_html( $option ); ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php elseif ( ( $field['field_type'] ?? 'text' ) === 'checkbox' ) : ?>
                    <label style="font-weight:normal;display:flex;gap:0.5rem;align-items:center;">
                        <input type="checkbox" id="mlw_field_<?php echo $key; ?>"
                            x-model="config['<?php echo $key; ?>']" @change="onFieldChange('<?php echo $key; ?>')">
                        <?php echo $label; ?>
                    </label>
                <?php else : ?>
                    <input type="<?php echo esc_attr( $field['field_type'] ?? 'text' ); ?>" id="mlw_field_<?php echo $key; ?>" class="configurator-form-input"
                        x-model="config['<?php echo $key; ?>']" @input="onFieldChange('<?php echo $key; ?>')"
                        placeholder="<?php echo $placeholder; ?>" <?php echo $required ? 'required' : ''; ?>>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <?php if ( $mlw_privacy_required ) : ?>
            <div class="configurator-form-group configurator-form-group--privacy">
                <label style="font-weight:normal;display:flex;gap:0.5rem;align-items:flex-start;font-size:14px;">
                    <input type="checkbox" style="margin-top:3px;"
                        x-model="config['privacy_consent']" @change="onFieldChange('privacy_consent')" required>
                    <span><?php echo wp_kses_post( $mlw_privacy_text ); ?></span>
                </label>
            </div>
        <?php endif; ?>
        
    </div>
</div>

<style>
.configurator-contact-form {
    max-width: 500px;
    margin: 0 auto;
}

.configurator-form-group {
    margin-bottom: 1.5rem;
}

.configurator-form-label {
    display: block;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: #333;
}

.configurator-form-label .required {
    color: red;
}

.configurator-form-input {
    width: 100%;
    padding: 1rem;
    font-size: 16px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    transition: border-color 0.2s;
}

.configurator-form-input:focus {
    outline: none;
    border-color: red;
    box-shadow: 0 0 0 3px rgba(255,0,0,0.1);
}

.configurator-form-input:invalid {
    border-color: #ef4444;
}

@media (max-width: 768px) {
    .configurator-contact-form {
        max-width: 100%;
    }
}
</style>
