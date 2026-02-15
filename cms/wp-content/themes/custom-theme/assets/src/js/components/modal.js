/**
 * Modal Component
 */

export default class Modal {
  constructor(element) {
    this.modal = element;
    this.init();
  }
  
  init() {
    // Close button
    const closeBtn = this.modal.querySelector('[data-modal-close]');
    if (closeBtn) {
      closeBtn.addEventListener('click', () => this.close());
    }
    
    // Background click
    this.modal.addEventListener('click', (e) => {
      if (e.target === this.modal) {
        this.close();
      }
    });
    
    // Escape key
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && this.modal.classList.contains('is-active')) {
        this.close();
      }
    });
  }
  
  open() {
    this.modal.classList.add('is-active');
    document.body.style.overflow = 'hidden';
    
    // Emit custom event
    this.modal.dispatchEvent(new CustomEvent('modal:opened'));
  }
  
  close() {
    this.modal.classList.remove('is-active');
    document.body.style.overflow = '';
    
    // Emit custom event
    this.modal.dispatchEvent(new CustomEvent('modal:closed'));
  }
}

// Auto-initialize
document.addEventListener('DOMContentLoaded', () => {
  const modals = document.querySelectorAll('.modal');
  const modalInstances = new Map();
  
  modals.forEach(modal => {
    const instance = new Modal(modal);
    modalInstances.set(modal.id, instance);
  });
  
  // Listen for triggers
  document.addEventListener('click', (e) => {
    const trigger = e.target.closest('[data-modal-trigger]');
    if (trigger) {
      e.preventDefault();
      const modalId = trigger.dataset.modalTrigger;
      const instance = modalInstances.get(modalId);
      if (instance) {
        instance.open();
      }
    }
  });
});

// CF7 Integration - Modal schließen nach erfolgreichem Submit
document.addEventListener('wpcf7mailsent', function(event) {
  // Finde das Modal, in dem das Formular ist
  const form = event.target;
  const modal = form.closest('.modal');
  
  if (modal) {
    // Warte 2 Sekunden, dann schließe das Modal
    setTimeout(() => {
      modal.classList.remove('is-active');
      document.body.classList.remove('modal-open');
      
      // Success-Nachricht anzeigen (optional)
      // alert('Vielen Dank! Ihre Nachricht wurde gesendet.');
    }, 2000);
  }
}, false);