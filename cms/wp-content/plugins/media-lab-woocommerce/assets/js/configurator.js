/**
 * Product Configurator Alpine.js Component
 */

function productConfigurator(initialData) {
    return {
        // State
        productId: initialData.productId,
        basePrice: initialData.basePrice,
        steps: initialData.steps,
        minQty: initialData.minQty,
        maxQty: initialData.maxQty,
        tiers: initialData.tiers,
        
        currentStep: 1,
        totalSteps: initialData.steps.length,
        
        config: {},
        errors: [],
        isProcessing: false,
        
        priceBreakdown: null,
        estimatedPrice: initialData.basePrice,
        pricePerUnit: initialData.basePrice,
        
        uploadProgress: {},
        uploadedFiles: {},
        
        // Initialization
        init() {
            // Initialize config with empty values
            this.steps.forEach(step => {
                if (step.step_type === 'checkbox') {
                    this.config[step.step_id] = [];
                } else if (step.step_type === 'size_matrix') {
                    this.config[step.step_id] = {};
                    step.options.forEach(opt => {
                        this.config[step.step_id][opt.value] = 0;
                    });
                } else if (step.step_id === 'quantity') {
                    this.config[step.step_id] = this.minQty;
                } else {
                    this.config[step.step_id] = '';
                }
            });
            
            // Calculate initial price
            this.calculatePrice();
        },
        
        // Navigation
        nextStep() {
            if (!this.canProceed()) {
                this.errors = ['Bitte füllen Sie alle Pflichtfelder aus.'];
                return;
            }
            
            this.errors = [];
            
            if (this.currentStep === this.totalSteps) {
                // Go to summary
                this.currentStep++;
                this.calculatePrice();
            } else {
                this.currentStep++;
            }
        },
        
        prevStep() {
            if (this.currentStep > 1) {
                this.currentStep--;
                this.errors = [];
            }
        },
        
        goToStep(stepNumber) {
            this.currentStep = stepNumber;
            this.errors = [];
        },
        
        canProceed() {
            const currentStepData = this.steps[this.currentStep - 1];
            
            if (!currentStepData.required) {
                return true;
            }
            
            const value = this.config[currentStepData.step_id];
            
            if (currentStepData.step_type === 'checkbox') {
                return value && value.length > 0;
            } else if (currentStepData.step_type === 'size_matrix') {
                return this.getSizeMatrixTotal(currentStepData.step_id) > 0;
            } else {
                return value !== '' && value !== null && value !== undefined;
            }
        },
        
        // Field Changes
        onFieldChange(stepId) {
            this.calculatePrice();
            
            // Check for conditional steps
            this.checkConditionalSteps();
        },
        
        checkConditionalSteps() {
            // TODO: Implement conditional logic
        },
        
        // Number field helpers
        incrementNumber(stepId, max) {
            if (!this.config[stepId]) {
                this.config[stepId] = 0;
            }
            if (this.config[stepId] < max) {
                this.config[stepId]++;
                this.onFieldChange(stepId);
            }
        },
        
        decrementNumber(stepId, min) {
            if (!this.config[stepId]) {
                this.config[stepId] = min;
            }
            if (this.config[stepId] > min) {
                this.config[stepId]--;
                this.onFieldChange(stepId);
            }
        },
        
        // Size matrix helpers
        incrementSize(stepId, sizeKey) {
            if (!this.config[stepId][sizeKey]) {
                this.config[stepId][sizeKey] = 0;
            }
            this.config[stepId][sizeKey]++;
            this.onSizeChange(stepId);
        },
        
        decrementSize(stepId, sizeKey) {
            if (!this.config[stepId][sizeKey] || this.config[stepId][sizeKey] <= 0) {
                return;
            }
            this.config[stepId][sizeKey]--;
            this.onSizeChange(stepId);
        },
        
        onSizeChange(stepId) {
            // Update total quantity
            const total = this.getSizeMatrixTotal(stepId);
            this.config['quantity'] = total;
            this.calculatePrice();
        },
        
        getSizeMatrixTotal(stepId) {
            if (!this.config[stepId]) return 0;
            
            return Object.values(this.config[stepId]).reduce((sum, qty) => {
                return sum + (parseInt(qty) || 0);
            }, 0);
        },
        
        // File Upload
        async handleFileUpload(event, stepId) {
            const file = event.target.files[0];
            if (!file) return;
            
            // Validate file
            const maxSize = 10 * 1024 * 1024; // 10MB
            if (file.size > maxSize) {
                this.errors = ['Datei ist zu groß. Maximum: 10MB'];
                return;
            }
            
            // Store file info
            this.config[stepId] = {
                name: file.name,
                size: file.size,
                type: file.type,
                file: file
            };
            
            // Upload file
            await this.uploadFile(file, stepId);
        },
        
        async uploadFile(file, stepId) {
            const formData = new FormData();
            formData.append('action', 'upload_configurator_file');
            formData.append('nonce', configuratorData.nonce);
            formData.append('file', file);
            formData.append('step_id', stepId);
            
            this.uploadProgress[stepId] = 0;
            
            try {
                const response = await fetch(configuratorData.ajax_url, {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    this.uploadedFiles[stepId] = data.data;
                    this.config[stepId].url = data.data.url;
                    this.config[stepId].id = data.data.id;
                    this.uploadProgress[stepId] = 100;
                } else {
                    this.errors = [data.data || 'Upload fehlgeschlagen'];
                    delete this.config[stepId];
                }
            } catch (error) {
                console.error('Upload error:', error);
                this.errors = ['Upload fehlgeschlagen'];
                delete this.config[stepId];
            }
        },
        
        removeFile(stepId) {
            delete this.config[stepId];
            delete this.uploadedFiles[stepId];
            delete this.uploadProgress[stepId];
            this.onFieldChange(stepId);
        },
        
        formatFileSize(bytes) {
            if (!bytes) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        },
        
        // Price Calculation
        async calculatePrice() {
            try {
                const response = await fetch(configuratorData.ajax_url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'calculate_price',
                        nonce: configuratorData.nonce,
                        product_id: this.productId,
                        config: JSON.stringify(this.config)
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    this.priceBreakdown = data.data;
                    this.estimatedPrice = data.data.total;
                    this.pricePerUnit = data.data.unit_price;
                }
            } catch (error) {
                console.error('Price calculation error:', error);
            }
        },
        
        calculateTierPrice(discountPercent) {
            const price = this.priceBreakdown ? this.priceBreakdown.subtotal : this.basePrice;
            const discounted = price * (1 - discountPercent / 100);
            return this.formatPrice(discounted);
        },
        
        formatPrice(price) {
            return new Intl.NumberFormat('de-DE', {
                style: 'currency',
                currency: 'EUR'
            }).format(price);
        },
        
        // Summary
        getSummaryItems() {
            const items = [];
            
            this.steps.forEach((step, index) => {
                if (!step.show_in_summary) return;
                
                const stepId = step.step_id;
                const value = this.config[stepId];
                
                if (!value || (Array.isArray(value) && value.length === 0)) return;
                
                let displayValue = value;
                
                // Format based on type
                if (step.step_type === 'select' || step.step_type === 'radio') {
                    const option = step.options.find(opt => opt.value === value);
                    displayValue = option ? option.label : value;
                } else if (step.step_type === 'checkbox') {
                    const labels = value.map(v => {
                        const opt = step.options.find(o => o.value === v);
                        return opt ? opt.label : v;
                    });
                    displayValue = labels.join(', ');
                } else if (step.step_type === 'size_matrix') {
                    const sizes = Object.entries(value)
                        .filter(([k, v]) => v > 0)
                        .map(([k, v]) => `${k}: ${v}x`);
                    displayValue = sizes.join(', ');
                } else if (step.step_type === 'file_upload') {
                    displayValue = value.name;
                }
                
                items.push({
                    label: step.step_label,
                    value: displayValue,
                    step: index + 1
                });
            });
            
            return items;
        },
        
        // Send Inquiry (statt Add to Cart)
        async sendInquiry() {
            this.isProcessing = true;
            this.errors = [];
            
            try {
                // Hole Kontaktdaten wenn vorhanden
                const contactData = {
                    name: this.config.customer_name || '',
                    email: this.config.customer_email || '',
                    phone: this.config.customer_phone || '',
                    message: this.config.notes || ''
                };
                
                const response = await fetch(configuratorData.ajax_url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'configurator_inquiry',
                        nonce: configuratorData.nonce,
                        product_id: this.productId,
                        config: JSON.stringify(this.config),
                        contact: JSON.stringify(contactData)
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Zeige Erfolg
                    alert('✅ Vielen Dank! Ihre Anfrage wurde versendet. Wir melden uns in Kürze bei Ihnen.');
                    // Redirect zur Startseite
                    window.location.href = '/';
                } else {
                    this.errors = [data.data || 'Fehler beim Senden der Anfrage'];
                }
            } catch (error) {
                console.error('Inquiry error:', error);
                this.errors = ['Fehler beim Senden der Anfrage. Bitte versuchen Sie es erneut.'];
            } finally {
                this.isProcessing = false;
            }
        }
    };
}
