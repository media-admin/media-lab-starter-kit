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
        quantityStep: initialData.quantityStep,
        
        currentStep: 1,
        totalSteps: initialData.steps.length,
        
        config: {},
        errors: [],
        isProcessing: false,
        
        priceBreakdown: {
            base_price: initialData.basePrice,
            additions: [],
            subtotal: initialData.basePrice,
            quantity: initialData.minQty || 1,
            tier_discount: 0,
            tier_discount_percent: 0,
            total_before_tax: initialData.basePrice,
            tax_rate: 20,
            tax_amount: 0,
            total: initialData.basePrice
        },
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
            this.scrollToConfiguratorTop();
        },
        
        prevStep() {
            if (this.currentStep > 1) {
                this.currentStep--;
                this.errors = [];
                this.scrollToConfiguratorTop();
            }
        },
        
        goToStep(stepNumber) {
            this.currentStep = stepNumber;
            this.errors = [];
            this.scrollToConfiguratorTop();
        },

        // Scrollt beim Schrittwechsel gezielt an den Anfang des Konfigurators,
        // statt es dem Browser zu überlassen: Steps mit stark unterschiedlicher
        // Höhe (z.B. der Mengen-Step mit Staffelpreis-Tabelle) verschieben beim
        // Reflow sonst die Seite unvorhersehbar, was wie ein Sprung ans
        // Seitenende wirkt.
        scrollToConfiguratorTop() {
            this.$nextTick(() => {
                const el = this.$root.closest('.product-configurator') || this.$root;
                if (el && el.scrollIntoView) {
                    el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        },
        
        canProceed() {
            const currentStepData = this.steps[this.currentStep - 1];

            // Zusammenfassungs-Schritt (currentStep === totalSteps + 1) hat KEINEN
            // Eintrag im steps-Array - hier ist "Weiter" ohnehin ausgeblendet
            // (x-show), aber Alpine wertet :disabled trotzdem bei jeder
            // Reaktivitäts-Änderung aus. Ohne diese Absicherung crasht das bei
            // jedem Tastendruck im letzten Formularfeld vor der Zusammenfassung.
            if (!currentStepData) {
                return true;
            }

            return this.isStepValid(currentStepData);
        },

        // Prüft einen einzelnen Step auf Vollständigkeit (unabhängig davon, ob
        // es der AKTUELLE Step ist) - wiederverwendet von canProceed() (nur
        // aktueller Step) und isConfigurationComplete() (ALLE Pflicht-Steps,
        // für die "ab"-Preis-Anzeige, siehe wizard.php Live-Preisvorschau).
        isStepValid(stepData) {
            if (!stepData.required) {
                return true;
            }

            // Contact Form ZUERST prüfen
            if (stepData.step_type === 'contact_form') {
                if (!this.config['customer_name'] || this.config['customer_name'].trim() === '') return false;
                if (!this.config['customer_email'] || this.config['customer_email'].trim() === '') return false;

                // Konfigurierte Pflicht-Zusatzfelder (z.B. "Firma") prüfen -
                // vorher wurde hier nur Name/E-Mail geprüft, wodurch man mit
                // "Zur Zusammenfassung" weiterkommen konnte, obwohl Pflicht-
                // Zusatzfelder oder die Datenschutz-Checkbox noch fehlten.
                const requiredKeys = (typeof configuratorData !== 'undefined' && configuratorData.requiredExtraFieldKeys) || [];
                for (const key of requiredKeys) {
                    const val = this.config[key];
                    if (val === undefined || val === null || val === '' || val === false) return false;
                }

                if (typeof configuratorData !== 'undefined' && configuratorData.privacyRequired && !this.config['privacy_consent']) {
                    return false;
                }

                return true;
            }

            const value = this.config[stepData.step_id];

            if (stepData.step_type === 'checkbox') {
                return value && value.length > 0;
            } else if (stepData.step_type === 'size_matrix') {
                return this.getSizeMatrixTotal(stepData.step_id) > 0;
            } else if (stepData.step_type === 'number') {
                const numValue = parseInt(value);
                const minValue = parseInt(stepData.min_value) || 0;
                return !isNaN(numValue) && numValue >= minValue;
            } else {
                return value !== '' && value !== null && value !== undefined;
            }
        },

        // Sind ALLE Pflicht-Steps ausgefüllt (nicht nur der aktuelle)?
        // Steuert die "ab"-Vorsilbe der Live-Preisvorschau (siehe wizard.php):
        // solange noch Pflichtangaben fehlen, ist der angezeigte Preis nur ein
        // Ausgangswert ("ab X €"), kein finaler Preis.
        isConfigurationComplete() {
            return this.steps.every((step) => this.isStepValid(step));
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
            // Bevorzugt den vom Server bereits fertig berechneten Preis nutzen
            // (siehe class-price-calculator.php, get_tiers_with_prices() und
            // class-configurator.php, ajax_calculate_price()). Die alte,
            // rein client-seitige Berechnung unten nutzte fälschlich
            // tax_display_mode (Anzeige-Einstellung) statt der tatsächlichen
            // Preiseingabe-Basis als Kriterium dafür, ob nochmal Steuer
            // aufgeschlagen wird - bei Bruttopreis-Eingabe (üblich in
            // DACH-B2C-Shops) wurde die im subtotal bereits enthaltene
            // Steuer dadurch ein zweites Mal aufgeschlagen (siehe
            // BACKLOG.md, "doppelte MwSt.-Berechnung in der
            // Staffelpreis-Tabelle").
            //
            // parseFloat() auf beiden Seiten des Vergleichs, da
            // discount_percent aus PHP/ACF je nach Konfiguration mal als
            // Zahl, mal als numerischer String im JSON ankommen kann und
            // wizard.php den Parameter direkt als PHP-Ausgabewert übergibt
            // (<?php echo $tier['discount_percent']; ?>).
            if (this.priceBreakdown && Array.isArray(this.priceBreakdown.tiers_with_prices)) {
                const tier = this.priceBreakdown.tiers_with_prices.find(
                    (t) => parseFloat(t.discount_percent) === parseFloat(discountPercent)
                );
                if (tier) {
                    return this.formatPrice(tier.unit_price);
                }
            }

            // Fallback: alte, client-seitige Näherung - nur relevant, falls
            // priceBreakdown ausnahmsweise noch KEIN tiers_with_prices enthält
            // (z.B. während einer Deploy-Übergangsphase mit älterem
            // Server-Stand). Absichtlich unverändert belassen als Sicherheitsnetz,
            // NICHT als primärer Berechnungsweg.
            const price = this.priceBreakdown ? this.priceBreakdown.subtotal : this.basePrice;
            const taxRate = this.priceBreakdown ? this.priceBreakdown.tax_rate : 0;
            const showGross = this.priceBreakdown && this.priceBreakdown.tax_display_mode === 'incl';
            let discounted = price * (1 - discountPercent / 100);
            if (showGross) {
                discounted = discounted * (1 + taxRate / 100);
            }
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
        
        // Zur Wunschliste hinzufügen (statt Anfrage/Warenkorb)
        async addToWishlist() {
            this.isProcessing = true;
            this.errors = [];

            // Optionale "Nachricht"-Felder ermitteln: ALLE textarea/text-Steps
            // AUSSERHALB des Kontaktformulars (z.B. "Besondere Wünsche"), damit
            // sie beim Übernehmen in die Wunschliste als EINE kombinierte
            // Nachricht mitgeschickt werden - falls ein Produkt mehrere solcher
            // Felder hat, statt nur das erste zu übernehmen. Die Step-Keys sind
            // projektspezifisch (z.B. "anmerkungen"), daher generisch über den
            // Step-Typ statt hartcodiert ermittelt.
            const messageSteps = this.steps.filter(
                (s) => (s.step_type === 'textarea' || s.step_type === 'text') && s.step_type !== 'contact_form'
            );
            const messageValue = messageSteps
                .map((s) => {
                    const val = (this.config[s.step_id] || '').toString().trim();
                    if (!val) return '';
                    // Label voranstellen, sobald mehr als ein Feld befüllt ist -
                    // bei nur einem Feld reicht der reine Wert (kein "Label: " nötig).
                    return messageSteps.length > 1 ? `${s.step_label}: ${val}` : val;
                })
                .filter((v) => v !== '')
                .join('\n\n');

            try {
                const response = await fetch(configuratorData.ajax_url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'mlw_wishlist_add',
                        nonce: configuratorData.wishlistNonce,
                        product_id: this.productId,
                        quantity: this.config.quantity || 1,
                        config: JSON.stringify(this.config),
                        message: messageValue
                    })
                });

                const data = await response.json();

                if (data.success) {
                    alert('✅ Zur Wunschliste hinzugefügt!');
                    if (typeof mlwWishlist !== 'undefined' && data.data && typeof data.data.count !== 'undefined') {
                        mlwWishlist.count = data.data.count;
                        document.querySelectorAll('.mlw-wishlist-count').forEach(function (el) {
                            el.textContent = data.data.count;
                        });
                    }
                    window.location.href = '/';
                } else {
                    this.errors = [(data.data && data.data.message) || 'Fehler beim Hinzufügen zur Wunschliste'];
                }
            } catch (error) {
                console.error('Wishlist error:', error);
                this.errors = ['Fehler beim Hinzufügen zur Wunschliste. Bitte versuchen Sie es erneut.'];
            } finally {
                this.isProcessing = false;
            }
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
                    message: this.config.notes || '',
                    privacy_consent: !!this.config.privacy_consent
                };

                // Dynamisch konfigurierte Zusatzfelder generisch mitsenden
                // (Feld-Keys kommen aus den Inquiry-Einstellungen, siehe class-configurator.php enqueue_scripts()).
                (configuratorData.extraFieldKeys || []).forEach((key) => {
                    contactData[key] = this.config[key] || '';
                });
                
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
