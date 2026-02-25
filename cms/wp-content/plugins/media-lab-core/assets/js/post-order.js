/**
 * Post Order - Drag & Drop Sortierung
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        const sortableContainer = document.getElementById('sortable-posts');
        
        if (!sortableContainer) {
            return;
        }
        
        // Sortable.js initialisieren
        const sortable = Sortable.create(sortableContainer, {
            animation: 150,
            handle: '.sortable-handle',
            ghostClass: 'sortable-ghost',
            dragClass: 'sortable-drag',
            chosenClass: 'sortable-chosen',
            
            onEnd: function(evt) {
                // Neue Reihenfolge sammeln
                const items = sortableContainer.querySelectorAll('.sortable-item');
                const order = [];
                
                items.forEach((item, index) => {
                    order.push(item.getAttribute('data-id'));
                    // Update Position Label
                    const posLabel = item.querySelector('.menu-order strong');
                    if (posLabel) {
                        posLabel.textContent = index;
                    }
                });
                
                // Per AJAX speichern
                saveOrder(order);
            }
        });
        
        /**
         * Reihenfolge per AJAX speichern
         */
        function saveOrder(order) {
            const statusEl = $('#save-status');
            const statusText = statusEl.find('.status-text');
            
            // Zeige Spinner
            statusEl.fadeIn();
            statusText.text('Speichere...');
            
            $.ajax({
                url: postOrderData.ajax_url,
                type: 'POST',
                data: {
                    action: 'update_post_order',
                    nonce: postOrderData.nonce,
                    order: JSON.stringify(order)
                },
                success: function(response) {
                    if (response.success) {
                        statusText.text('✓ Gespeichert');
                        setTimeout(function() {
                            statusEl.fadeOut();
                        }, 1500);
                    } else {
                        statusText.text('✗ Fehler: ' + response.data);
                        statusEl.addClass('error');
                    }
                },
                error: function() {
                    statusText.text('✗ Verbindungsfehler');
                    statusEl.addClass('error');
                }
            });
        }
        
    });
    
})(jQuery);
