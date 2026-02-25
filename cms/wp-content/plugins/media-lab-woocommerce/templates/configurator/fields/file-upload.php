<?php
/**
 * File Upload Field Template
 */

$allowed_types = isset($step['allowed_file_types']) ? $step['allowed_file_types'] : 'pdf,jpg,png';
$max_size = isset($step['max_file_size']) ? $step['max_file_size'] : 10;
?>

<div class="configurator-field configurator-field--upload">
    <div class="configurator-upload">
        
        <input type="file" 
               :id="'file-<?php echo esc_attr($step_id); ?>'"
               class="configurator-upload__input"
               accept=".<?php echo str_replace(',', ',.', $allowed_types); ?>"
               @change="handleFileUpload($event, '<?php echo esc_js($step_id); ?>')">
        
        <label :for="'file-<?php echo esc_attr($step_id); ?>'" 
               class="configurator-upload__label"
               x-show="!config['<?php echo esc_js($step_id); ?>']">
            <div class="configurator-upload__icon">📤</div>
            <div class="configurator-upload__text">
                <strong>Datei hochladen</strong>
                <span>oder hier ablegen</span>
            </div>
            <div class="configurator-upload__hint">
                Erlaubt: <?php echo strtoupper(str_replace(',', ', ', $allowed_types)); ?> 
                (max. <?php echo $max_size; ?> MB)
            </div>
        </label>
        
        <div class="configurator-upload__preview" 
             x-show="config['<?php echo esc_js($step_id); ?>']">
            <div class="configurator-upload__file">
                <div class="configurator-upload__file-icon">📄</div>
                <div class="configurator-upload__file-info">
                    <div class="configurator-upload__file-name" 
                         x-text="config['<?php echo esc_js($step_id); ?>']?.name"></div>
                    <div class="configurator-upload__file-size" 
                         x-text="formatFileSize(config['<?php echo esc_js($step_id); ?>']?.size)"></div>
                </div>
                <button type="button" 
                        class="configurator-upload__remove"
                        @click="removeFile('<?php echo esc_js($step_id); ?>')">
                    ✕
                </button>
            </div>
        </div>
        
        <div class="configurator-upload__progress" 
             x-show="uploadProgress['<?php echo esc_js($step_id); ?>'] > 0 && uploadProgress['<?php echo esc_js($step_id); ?>'] < 100">
            <div class="configurator-upload__progress-bar">
                <div class="configurator-upload__progress-fill" 
                     :style="`width: ${uploadProgress['<?php echo esc_js($step_id); ?>']}%`"></div>
            </div>
            <div class="configurator-upload__progress-text" 
                 x-text="uploadProgress['<?php echo esc_js($step_id); ?>'] + '%'"></div>
        </div>
        
    </div>
</div>

<style>
.configurator-upload__input {
    display: none;
}

.configurator-upload__label {
    display: block;
    border: 3px dashed #e0e0e0;
    border-radius: 12px;
    padding: 3rem 2rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s ease;
}

.configurator-upload__label:hover {
    border-color: red;
    background: #fff5f5;
}

.configurator-upload__icon {
    font-size: 48px;
    margin-bottom: 1rem;
}

.configurator-upload__text {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.configurator-upload__text strong {
    font-size: 18px;
    color: red;
}

.configurator-upload__text span {
    color: #666;
    font-size: 14px;
}

.configurator-upload__hint {
    font-size: 12px;
    color: #999;
}

.configurator-upload__preview {
    background: #f8f9fa;
    border: 2px solid #e0e0e0;
    border-radius: 12px;
    padding: 1.5rem;
}

.configurator-upload__file {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.configurator-upload__file-icon {
    font-size: 32px;
}

.configurator-upload__file-info {
    flex: 1;
}

.configurator-upload__file-name {
    font-weight: 600;
    margin-bottom: 0.25rem;
}

.configurator-upload__file-size {
    font-size: 14px;
    color: #666;
}

.configurator-upload__remove {
    background: #ef4444;
    color: #fff;
    border: none;
    border-radius: 50%;
    width: 32px;
    height: 32px;
    cursor: pointer;
    font-size: 18px;
    transition: all 0.2s ease;
}

.configurator-upload__remove:hover {
    background: #dc2626;
    transform: scale(1.1);
}

.configurator-upload__progress {
    margin-top: 1rem;
}

.configurator-upload__progress-bar {
    height: 8px;
    background: #e0e0e0;
    border-radius: 9999px;
    overflow: hidden;
}

.configurator-upload__progress-fill {
    height: 100%;
    background: red;
    transition: width 0.3s ease;
}

.configurator-upload__progress-text {
    text-align: center;
    margin-top: 0.5rem;
    font-size: 14px;
    font-weight: 600;
    color: red;
}
</style>
