<?php
/**
 * Contact Form Field Template
 * Hardcoded fields: Name, E-Mail, Telefon
 */
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
