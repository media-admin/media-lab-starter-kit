/**
 * AJAX Filters Component
 */

export default class AjaxFilters {
  constructor() {
    this.containers = document.querySelectorAll('.ajax-filters');
    
    // Nur initialisieren wenn Filter-Container existieren
    if (this.containers.length > 0) {
      this.init();
    } else {
      console.log('ℹ️ Keine AJAX Filters auf dieser Seite');
    }
  }
  
  init() {
    this.containers.forEach(container => {
      if (!container || !container.dataset) return;
      
      const postType = container.dataset.postType;
      const postsPerPage = container.dataset.postsPerPage;
      const template = container.dataset.template;
      const columns = container.dataset.columns;
      
      // Weitere Initialisierung...
      console.log('AJAX Filters initialisiert für:', postType);
    });
  }
}
