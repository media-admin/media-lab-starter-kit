/**
 * AJAX Filters Component
 * 
 * Professional filtering system with:
 * - Search filters
 * - Taxonomy filters (checkboxes, buttons, dropdowns)
 * - Range filters
 * - Sort options
 * - Pagination
 * - Loading states
 * - Active filter display
 */

export default class AjaxFilters {
  constructor() {
    this.containers = document.querySelectorAll('.ajax-filters');
    
    if (this.containers.length === 0) {
      console.log('ℹ️ No AJAX Filters on this page');
      return;
    }
    
    console.log(`✅ AJAX Filters: Found ${this.containers.length} container(s)`);
    this.init();
  }
  
  init() {
    this.containers.forEach(container => {
      this.initContainer(container);
    });
  }
  
  initContainer(container) {
    // Settings from data attributes
    const settings = {
      postType: container.dataset.postType || 'post',
      postsPerPage: parseInt(container.dataset.postsPerPage) || 12,
      template: container.dataset.template || 'card',
      columns: parseInt(container.dataset.columns) || 3,
    };
    
    // Store settings on container
    container.filterSettings = settings;
    container.currentPage = 1;
    container.activeFilters = {
      search: '',
      taxonomies: {},
      meta: {},
      orderby: 'date',
      order: 'DESC'
    };
    
    // DOM elements
    const elements = {
      sidebar: container.querySelector('.ajax-filters__sidebar'),
      results: container.querySelector('.ajax-filters__results'),
      grid: container.querySelector('.ajax-filters__grid'),
      loading: container.querySelector('.ajax-filters__loading'),
      count: container.querySelector('.ajax-filters__count-number'),
      pagination: container.querySelector('.ajax-filters__pagination'),
      activeList: container.querySelector('.ajax-filters__active-list'),
      activeContainer: container.querySelector('.ajax-filters__active'),
      resetBtn: container.querySelector('.ajax-filters__reset'),
      sortSelect: container.querySelector('.ajax-filters__sort-select'),
    };
    
    container.elements = elements;
    
    // Setup event listeners
    this.setupSearchFilter(container);
    this.setupTaxonomyFilters(container);
    this.setupRangeFilters(container);
    this.setupSortFilter(container);
    this.setupResetButton(container);
    
    // Initial load
    console.log('🔄 Loading initial results for:', settings.postType);
    this.loadResults(container);
  }
  
  // ============================================
  // SEARCH FILTER
  // ============================================
  setupSearchFilter(container) {
    const searchInputs = container.querySelectorAll('.ajax-filters__search-input');
    
    searchInputs.forEach(input => {
      let debounceTimer;
      
      input.addEventListener('input', (e) => {
        clearTimeout(debounceTimer);
        
        debounceTimer = setTimeout(() => {
          container.activeFilters.search = e.target.value.trim();
          container.currentPage = 1;
          this.loadResults(container);
        }, 500);
      });
    });
  }
  
  // ============================================
  // TAXONOMY FILTERS
  // ============================================
  setupTaxonomyFilters(container) {
    // Checkboxes
    const checkboxes = container.querySelectorAll('.ajax-filters__taxonomy-checkbox');
    checkboxes.forEach(checkbox => {
      checkbox.addEventListener('change', (e) => {
        this.handleTaxonomyChange(container, e.target);
      });
    });
    
    // Buttons
    const buttons = container.querySelectorAll('.ajax-filters__taxonomy-button');
    buttons.forEach(button => {
      button.addEventListener('click', (e) => {
        e.preventDefault();
        button.classList.toggle('is-active');
        
        const taxonomy = button.dataset.taxonomy;
        const term = button.dataset.term;
        
        this.toggleTaxonomyTerm(container, taxonomy, term);
        container.currentPage = 1;
        this.loadResults(container);
      });
    });
    
    // Dropdowns
    const dropdowns = container.querySelectorAll('.ajax-filters__taxonomy-select');
    dropdowns.forEach(select => {
      select.addEventListener('change', (e) => {
        const taxonomy = select.dataset.taxonomy;
        const value = select.value;
        
        if (value) {
          container.activeFilters.taxonomies[taxonomy] = [value];
        } else {
          delete container.activeFilters.taxonomies[taxonomy];
        }
        
        container.currentPage = 1;
        this.loadResults(container);
      });
    });
  }
  
  handleTaxonomyChange(container, checkbox) {
    const taxonomy = checkbox.dataset.taxonomy;
    const term = checkbox.value;
    
    this.toggleTaxonomyTerm(container, taxonomy, term);
    container.currentPage = 1;
    this.loadResults(container);
  }
  
  toggleTaxonomyTerm(container, taxonomy, term) {
    if (!container.activeFilters.taxonomies[taxonomy]) {
      container.activeFilters.taxonomies[taxonomy] = [];
    }
    
    const index = container.activeFilters.taxonomies[taxonomy].indexOf(term);
    
    if (index > -1) {
      container.activeFilters.taxonomies[taxonomy].splice(index, 1);
      
      if (container.activeFilters.taxonomies[taxonomy].length === 0) {
        delete container.activeFilters.taxonomies[taxonomy];
      }
    } else {
      container.activeFilters.taxonomies[taxonomy].push(term);
    }
  }
  
  // ============================================
  // RANGE FILTERS
  // ============================================
  setupRangeFilters(container) {
    const rangeInputs = container.querySelectorAll('.ajax-filters__range-input');
    
    rangeInputs.forEach(input => {
      const key = input.dataset.key;
      const valueDisplay = input.parentElement.querySelector('.ajax-filters__range-value');
      
      // Update display on input
      input.addEventListener('input', (e) => {
        if (valueDisplay) {
          const prefix = input.dataset.prefix || '';
          const suffix = input.dataset.suffix || '';
          valueDisplay.textContent = prefix + e.target.value + suffix;
        }
      });
      
      // Apply filter on change (mouseup)
      input.addEventListener('change', (e) => {
        const value = parseFloat(e.target.value);
        const min = parseFloat(input.min);
        const max = parseFloat(input.max);
        
        if (!container.activeFilters.meta[key]) {
          container.activeFilters.meta[key] = {};
        }
        
        // Store the value (for range we'd need min/max inputs)
        container.activeFilters.meta[key].value = value;
        
        container.currentPage = 1;
        this.loadResults(container);
      });
    });
  }
  
  // ============================================
  // SORT FILTER
  // ============================================
  setupSortFilter(container) {
    const sortSelect = container.elements.sortSelect;
    
    if (!sortSelect) return;
    
    sortSelect.addEventListener('change', (e) => {
      const value = e.target.value;
      const [orderby, order] = value.split('-');
      
      container.activeFilters.orderby = orderby;
      container.activeFilters.order = order.toUpperCase();
      
      this.loadResults(container);
    });
  }
  
  // ============================================
  // RESET BUTTON
  // ============================================
  setupResetButton(container) {
    const resetBtn = container.elements.resetBtn;
    
    if (!resetBtn) return;
    
    resetBtn.addEventListener('click', (e) => {
      e.preventDefault();
      this.resetFilters(container);
    });
  }
  
  resetFilters(container) {
    // Reset active filters
    container.activeFilters = {
      search: '',
      taxonomies: {},
      meta: {},
      orderby: 'date',
      order: 'DESC'
    };
    
    container.currentPage = 1;
    
    // Reset UI
    container.querySelectorAll('.ajax-filters__search-input').forEach(input => {
      input.value = '';
    });
    
    container.querySelectorAll('.ajax-filters__taxonomy-checkbox').forEach(checkbox => {
      checkbox.checked = false;
    });
    
    container.querySelectorAll('.ajax-filters__taxonomy-button').forEach(button => {
      button.classList.remove('is-active');
    });
    
    container.querySelectorAll('.ajax-filters__taxonomy-select').forEach(select => {
      select.selectedIndex = 0;
    });
    
    container.querySelectorAll('.ajax-filters__range-input').forEach(input => {
      input.value = input.min;
      const event = new Event('input');
      input.dispatchEvent(event);
    });
    
    // Reset sort
    if (container.elements.sortSelect) {
      container.elements.sortSelect.value = 'date-desc';
    }
    
    // Hide reset button
    if (container.elements.resetBtn) {
      container.elements.resetBtn.style.display = 'none';
    }
    
    // Hide active filters
    if (container.elements.activeContainer) {
      container.elements.activeContainer.style.display = 'none';
    }
    
    // Reload
    this.loadResults(container);
  }
  
  // ============================================
  // LOAD RESULTS
  // ============================================
  async loadResults(container) {
    const settings = container.filterSettings;
    const filters = container.activeFilters;
    
    // Show loading
    this.showLoading(container);
    
    // Check for nonce
    if (!window.customTheme || !window.customTheme.filtersNonce) {
      console.error('❌ Filters nonce missing');
      this.showError(container, 'Configuration error');
      return;
    }
    
    // Build request data
    const formData = new FormData();
    formData.append('action', 'ajax_filter_posts');
    formData.append('nonce', window.customTheme.filtersNonce);
    formData.append('post_type', settings.postType);
    formData.append('posts_per_page', settings.postsPerPage);
    formData.append('paged', container.currentPage);
    formData.append('template', settings.template);
    
    // Search
    if (filters.search) {
      formData.append('search', filters.search);
    }
    
    // Taxonomies
    if (Object.keys(filters.taxonomies).length > 0) {
      formData.append('taxonomies', JSON.stringify(filters.taxonomies));
    }
    
    // Meta
    if (Object.keys(filters.meta).length > 0) {
      formData.append('meta', JSON.stringify(filters.meta));
    }
    
    // Sort
    formData.append('orderby', filters.orderby);
    formData.append('order', filters.order);
    
    console.log('🔄 Loading results with filters:', {
      postType: settings.postType,
      page: container.currentPage,
      filters: filters
    });
    
    try {
      const response = await fetch(window.customTheme.ajaxUrl, {
        method: 'POST',
        body: formData
      });
      
      const data = await response.json();
      
      console.log('📦 Response:', data);
      
      if (data.success) {
        this.renderResults(container, data.data);
        this.updateActiveFilters(container);
      } else {
        console.error('❌ Filter error:', data.data?.message || 'Unknown error');
        this.showError(container, data.data?.message || 'Error loading results');
      }
    } catch (error) {
      console.error('❌ AJAX error:', error);
      this.showError(container, 'Network error');
    } finally {
      this.hideLoading(container);
    }
  }
  
  // ============================================
  // RENDER RESULTS
  // ============================================
  renderResults(container, data) {
    const { posts, found_posts, max_pages, current_page } = data;
    const grid = container.elements.grid;
    
    // Clear grid
    grid.innerHTML = '';
    
    // Update count
    if (container.elements.count) {
      container.elements.count.textContent = found_posts || 0;
    }
    
    // Render posts
    if (posts && posts.length > 0) {
      posts.forEach((post, index) => {
        setTimeout(() => {
          const element = this.renderPost(post, container.filterSettings.template);
          element.style.opacity = '0';
          element.style.transform = 'translateY(20px)';
          grid.appendChild(element);
          
          setTimeout(() => {
            element.style.transition = 'all 0.3s ease';
            element.style.opacity = '1';
            element.style.transform = 'translateY(0)';
          }, 50);
        }, index * 50);
      });
    } else {
      grid.innerHTML = '<div class="ajax-filters__no-results">Keine Ergebnisse gefunden.</div>';
    }
    
    // Render pagination
    this.renderPagination(container, current_page, max_pages);
  }
  
  renderPost(post, template) {
    const article = document.createElement('article');
    article.className = `ajax-filters__item ajax-filters__item--${template}`;
    
    // Use template-specific rendering if available
    switch (template) {
      case 'job':
        return this.renderJobPost(post);
      case 'project':
        return this.renderProjectPost(post);
      case 'team':
        return this.renderTeamPost(post);
      default:
        return this.renderCardPost(post);
    }
  }
  
  renderCardPost(post) {
    const article = document.createElement('article');
    article.className = 'post-card';
    
    article.innerHTML = `
      ${post.thumbnail ? `
        <div class="post-card__thumbnail">
          <a href="${post.url}">
            <img src="${post.thumbnail}" alt="${this.esc(post.title)}" loading="lazy">
          </a>
        </div>
      ` : ''}
      <div class="post-card__content">
        <h3 class="post-card__title">
          <a href="${post.url}">${this.esc(post.title)}</a>
        </h3>
        <div class="post-card__meta">
          <span class="post-card__date">${post.date || ''}</span>
        </div>
        ${post.excerpt ? `
          <div class="post-card__excerpt">${this.esc(post.excerpt)}</div>
        ` : ''}
        <a href="${post.url}" class="post-card__link">Mehr lesen →</a>
      </div>
    `;
    
    return article;
  }
  
  renderJobPost(post) {
    const article = document.createElement('article');
    article.className = 'job-card';
    
    article.innerHTML = `
      <div class="job-card__header">
        <h3 class="job-card__title">
          <a href="${post.url}">${this.esc(post.title)}</a>
        </h3>
        ${post.location ? `<p class="job-card__location">${this.esc(post.location)}</p>` : ''}
      </div>
      <div class="job-card__content">
        ${post.excerpt ? `<p>${this.esc(post.excerpt)}</p>` : ''}
      </div>
      <div class="job-card__footer">
        ${post.employment_type ? `<span class="job-card__type">${this.esc(post.employment_type)}</span>` : ''}
        <a href="${post.url}" class="job-card__apply">Bewerben →</a>
      </div>
    `;
    
    return article;
  }
  
  renderProjectPost(post) {
    const article = document.createElement('article');
    article.className = 'project-card';
    
    article.innerHTML = `
      ${post.thumbnail_large ? `
        <div class="project-card__image">
          <a href="${post.url}">
            <img src="${post.thumbnail_large}" alt="${this.esc(post.title)}" loading="lazy">
          </a>
        </div>
      ` : ''}
      <div class="project-card__content">
        <h3 class="project-card__title">
          <a href="${post.url}">${this.esc(post.title)}</a>
        </h3>
        ${post.client ? `<p class="project-card__client">Client: ${this.esc(post.client)}</p>` : ''}
      </div>
    `;
    
    return article;
  }
  
  renderTeamPost(post) {
    const div = document.createElement('div');
    div.className = 'team-card';
    
    div.innerHTML = `
      ${post.thumbnail ? `
        <div class="team-card__image">
          <img src="${post.thumbnail}" alt="${this.esc(post.title)}" loading="lazy">
        </div>
      ` : ''}
      <div class="team-card__content">
        <h3 class="team-card__name">${this.esc(post.title)}</h3>
        ${post.role ? `<p class="team-card__role">${this.esc(post.role)}</p>` : ''}
      </div>
    `;
    
    return div;
  }
  
  // ============================================
  // PAGINATION
  // ============================================
  renderPagination(container, currentPage, maxPages) {
    const paginationEl = container.elements.pagination;
    
    if (!paginationEl || maxPages <= 1) {
      if (paginationEl) paginationEl.innerHTML = '';
      return;
    }
    
    let html = '<div class="ajax-filters__pagination-list">';
    
    // Previous
    if (currentPage > 1) {
      html += `<button class="ajax-filters__pagination-btn" data-page="${currentPage - 1}">← Zurück</button>`;
    }
    
    // Pages
    for (let i = 1; i <= maxPages; i++) {
      if (i === currentPage) {
        html += `<span class="ajax-filters__pagination-btn is-active">${i}</span>`;
      } else if (
        i === 1 || 
        i === maxPages || 
        (i >= currentPage - 2 && i <= currentPage + 2)
      ) {
        html += `<button class="ajax-filters__pagination-btn" data-page="${i}">${i}</button>`;
      } else if (
        i === currentPage - 3 || 
        i === currentPage + 3
      ) {
        html += `<span class="ajax-filters__pagination-dots">...</span>`;
      }
    }
    
    // Next
    if (currentPage < maxPages) {
      html += `<button class="ajax-filters__pagination-btn" data-page="${currentPage + 1}">Weiter →</button>`;
    }
    
    html += '</div>';
    
    paginationEl.innerHTML = html;
    
    // Add click handlers
    paginationEl.querySelectorAll('[data-page]').forEach(btn => {
      btn.addEventListener('click', () => {
        container.currentPage = parseInt(btn.dataset.page);
        this.loadResults(container);
        
        // Scroll to top of results
        container.elements.results.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
    });
  }
  
  // ============================================
  // ACTIVE FILTERS DISPLAY
  // ============================================
  updateActiveFilters(container) {
    const activeList = container.elements.activeList;
    const activeContainer = container.elements.activeContainer;
    const resetBtn = container.elements.resetBtn;
    
    if (!activeList || !activeContainer) return;
    
    const filters = container.activeFilters;
    let html = '';
    let hasFilters = false;
    
    // Search
    if (filters.search) {
      html += `<span class="ajax-filters__active-tag" data-filter="search">
        Suche: ${this.esc(filters.search)}
        <button data-remove="search">×</button>
      </span>`;
      hasFilters = true;
    }
    
    // Taxonomies
    for (const [taxonomy, terms] of Object.entries(filters.taxonomies)) {
      if (terms && terms.length > 0) {
        terms.forEach(term => {
          html += `<span class="ajax-filters__active-tag" data-filter="taxonomy" data-taxonomy="${taxonomy}" data-term="${term}">
            ${this.esc(term)}
            <button data-remove="taxonomy" data-taxonomy="${taxonomy}" data-term="${term}">×</button>
          </span>`;
        });
        hasFilters = true;
      }
    }
    
    activeList.innerHTML = html;
    activeContainer.style.display = hasFilters ? 'block' : 'none';
    
    if (resetBtn) {
      resetBtn.style.display = hasFilters ? 'block' : 'none';
    }
    
    // Add remove handlers
    activeList.querySelectorAll('[data-remove]').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        const type = btn.dataset.remove;
        
        if (type === 'search') {
          container.activeFilters.search = '';
          container.querySelectorAll('.ajax-filters__search-input').forEach(input => {
            input.value = '';
          });
        } else if (type === 'taxonomy') {
          const taxonomy = btn.dataset.taxonomy;
          const term = btn.dataset.term;
          this.toggleTaxonomyTerm(container, taxonomy, term);
          
          // Update UI
          container.querySelectorAll(`.ajax-filters__taxonomy-checkbox[data-taxonomy="${taxonomy}"][value="${term}"]`).forEach(cb => {
            cb.checked = false;
          });
          container.querySelectorAll(`.ajax-filters__taxonomy-button[data-taxonomy="${taxonomy}"][data-term="${term}"]`).forEach(button => {
            button.classList.remove('is-active');
          });
        }
        
        this.loadResults(container);
      });
    });
  }
  
  // ============================================
  // LOADING STATES
  // ============================================
  showLoading(container) {
    if (container.elements.loading) {
      container.elements.loading.style.display = 'flex';
    }
    if (container.elements.grid) {
      container.elements.grid.style.opacity = '0.5';
    }
  }
  
  hideLoading(container) {
    if (container.elements.loading) {
      container.elements.loading.style.display = 'none';
    }
    if (container.elements.grid) {
      container.elements.grid.style.opacity = '1';
    }
  }
  
  showError(container, message) {
    if (container.elements.grid) {
      container.elements.grid.innerHTML = `
        <div class="ajax-filters__error">
          <p>${this.esc(message)}</p>
        </div>
      `;
    }
  }
  
  // ============================================
  // UTILITIES
  // ============================================
  esc(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => {
    console.log('🚀 AJAX Filters: Initializing after DOMContentLoaded');
    new AjaxFilters();
  });
} else {
  console.log('🚀 AJAX Filters: DOM already ready, initializing now');
  new AjaxFilters();
}
