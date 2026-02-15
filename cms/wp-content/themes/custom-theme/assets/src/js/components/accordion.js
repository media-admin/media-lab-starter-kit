/**
 * Accordion Component
 */

export default class Accordion {
  constructor() {
    this.accordions = document.querySelectorAll('.accordion');
    
    if (this.accordions.length > 0) {
      this.init();
    }
  }
  
  init() {
    this.accordions.forEach(accordion => {
      this.initAccordion(accordion);
    });
  }
  
  initAccordion(accordion) {
    const items = accordion.querySelectorAll('.accordion__item');
    
    if (!items || items.length === 0) {
      return;
    }
    
    items.forEach(item => {
      const trigger = item.querySelector('.accordion__trigger');
      const content = item.querySelector('.accordion__content');
      
      // Skip if elements not found
      if (!trigger || !content) {
        return;
      }
      
      trigger.addEventListener('click', () => {
        const isActive = item.classList.contains('is-active');
        
        // Close all items (if you want single-open behavior)
        // Uncomment these lines for single-open:
        // items.forEach(otherItem => {
        //   otherItem.classList.remove('is-active');
        // });
        
        // Toggle current item
        if (isActive) {
          item.classList.remove('is-active');
          trigger.setAttribute('aria-expanded', 'false');
          content.style.maxHeight = null;
        } else {
          item.classList.add('is-active');
          trigger.setAttribute('aria-expanded', 'true');
          content.style.maxHeight = content.scrollHeight + 'px';
        }
      });
    });
  }
}

// Initialize
new Accordion();