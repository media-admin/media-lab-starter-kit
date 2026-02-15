/**
 * AJAX Filters Component
 * 
 * Advanced filtering system for posts, CPTs, and WooCommerce products.
 * Supports taxonomy filters, meta filters, search, sorting, and pagination.
 * 
 * @package Custom_Theme
 */

class AjaxFilters {
    constructor(container) {
        this.container = container;
        this.postType = container.dataset.postType || 'post';
        this.postsPerPage = parseInt(container.dataset.postsPerPage) || 12;
        this.template = container.dataset.template || 'card';
        this.columns = container.dataset.columns || '3';
        this.currentPage = 1;
        this.isLoading = false;
        
        // Elements
        this.sidebar = container.querySelector('.ajax-filters__sidebar');
        this.grid = container.querySelector('.ajax-filters__grid');
        this.pagination = container.querySelector('.ajax-filters__pagination');
        this.loading = container.querySelector('.ajax-filters__loading');
        this.countElement = container.querySelector('.ajax-filters__count-number');
        this.sortSelect = container.querySelector('.ajax-filters__sort-select');
        this.resetButton = container.querySelector('.ajax-filters__reset');
        this.activeFiltersContainer = container.querySelector('.ajax-filters__active');
        this.activeFiltersList = container.querySelector('.ajax-filters__active-list');
        
        // Search
        this.searchInput = container.querySelector('.ajax-filter__search-input');
        this.searchButton = container.querySelector('.ajax-filter__search-button');
        this.searchTimeout = null;
        
        this.init();
    }
    
    init() {
        console.log('🔧 Initializing AJAX Filters for:', this.postType);
        
        // Bind events
        this.bindEvents();
        
        // Load initial results
        this.loadResults();
        
        // Parse URL parameters
        this.parseUrlParams();
    }
    
    bindEvents() {
        // Taxonomy filters (checkboxes, radios)
        this.container.querySelectorAll('.ajax-filter__option input').forEach(input => {
            input.addEventListener('change', () => this.handleFilterChange());
        });
        
        // Taxonomy filters (dropdowns)
        this.container.querySelectorAll('.ajax-filter__select').forEach(select => {
            select.addEventListener('change', () => this.handleFilterChange());
        });
        
        // Taxonomy filters (buttons)
        this.container.querySelectorAll('.ajax-filter__button').forEach(button => {
            button.addEventListener('click', (e) => this.handleButtonFilter(e));
        });
        
        // Range sliders
        this.container.querySelectorAll('.ajax-filter__range-input').forEach(slider => {
            slider.addEventListener('input', (e) => this.handleRangeChange(e));
            slider.addEventListener('change', () => this.handleFilterChange());
        });
        
        // Search
        if (this.searchInput) {
            this.searchInput.addEventListener('input', () => this.handleSearchInput());
            if (this.searchButton) {
                this.searchButton.addEventListener('click', () => this.handleFilterChange());
            }
        }
        
        // Sort
        if (this.sortSelect) {
            this.sortSelect.addEventListener('change', () => this.handleSortChange());
        }
        
        // Reset
        if (this.resetButton) {
            this.resetButton.addEventListener('click', () => this.resetFilters());
        }
        
        console.log('✅ Events bound');
    }
    
    handleFilterChange() {
        console.log('🔄 Filter changed');
        this.currentPage = 1;
        this.loadResults();
    }
    
    handleButtonFilter(e) {
        const button = e.currentTarget;
        button.classList.toggle('is-active');
        this.handleFilterChange();
    }
    
    handleRangeChange(e) {
        const slider = e.target;
        const filterContainer = slider.closest('.ajax-filter--range');
        const minSlider = filterContainer.querySelector('.ajax-filter__range-min');
        const maxSlider = filterContainer.querySelector('.ajax-filter__range-max');
        const minValue = filterContainer.querySelector('.ajax-filter__range-min-value');
        const maxValue = filterContainer.querySelector('.ajax-filter__range-max-value');
        
        // Update display values
        minValue.textContent = this.formatNumber(minSlider.value);
        maxValue.textContent = this.formatNumber(maxSlider.value);
        
        // Prevent overlap
        if (parseInt(minSlider.value) > parseInt(maxSlider.value)) {
            if (slider.classList.contains('ajax-filter__range-min')) {
                minSlider.value = maxSlider.value;
                minValue.textContent = this.formatNumber(minSlider.value);
            } else {
                maxSlider.value = minSlider.value;
                maxValue.textContent = this.formatNumber(maxSlider.value);
            }
        }
    }
    
    handleSearchInput() {
        clearTimeout(this.searchTimeout);
        this.searchTimeout = setTimeout(() => {
            this.handleFilterChange();
        }, 500); // Debounce 500ms
    }
    
    handleSortChange() {
        console.log('🔀 Sort changed');
        this.handleFilterChange();
    }
    
    loadResults(page = 1) {
        if (this.isLoading) {
            console.log('⏳ Already loading...');
            return;
        }
        
        this.isLoading = true;
        this.currentPage = page;
        
        console.log('📡 Loading results for page:', page);
        
        // Show loading state
        this.showLoading();
        
        // Collect filters
        const filters = this.collectFilters();
        
        console.log('📋 Filters:', filters);
        
        // Make AJAX request
        fetch(window.customTheme.ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'ajax_filter_posts',
                nonce: window.customTheme.filtersNonce,
                post_type: this.postType,
                posts_per_page: this.postsPerPage,
                paged: page,
                template: this.template,
                taxonomies: JSON.stringify(filters.taxonomies),
                meta: JSON.stringify(filters.meta),
                search: filters.search,
                orderby: filters.orderby,
                order: filters.order,
            })
        })
        .then(response => response.json())
        .then(data => {
            console.log('✅ Results loaded:', data);
            
            if (data.success) {
                // Update results
                this.updateResults(data);
                
                // Update count
                this.updateCount(data.found_posts);
                
                // Update pagination
                this.updatePagination(data.max_pages, data.current_page);
                
                // Update active filters
                this.updateActiveFilters(filters);
                
                // Update URL
                this.updateUrl(filters);
                
                // Show/hide reset button
                this.toggleResetButton(filters);
            } else {
                console.error('❌ Error loading results');
                this.showError();
            }
        })
        .catch(error => {
            console.error('❌ AJAX Error:', error);
            this.showError();
        })
        .finally(() => {
            this.hideLoading();
            this.isLoading = false;
        });
    }
    
    collectFilters() {
        const filters = {
            taxonomies: {},
            meta: [],
            search: '',
            orderby: 'date',
            order: 'DESC'
        };
        
        // Taxonomy filters (checkboxes & radios)
        this.container.querySelectorAll('.ajax-filter--taxonomy').forEach(filter => {
            const taxonomy = filter.dataset.taxonomy;
            const checkedInputs = filter.querySelectorAll('input:checked');
            
            if (checkedInputs.length > 0) {
                filters.taxonomies[taxonomy] = Array.from(checkedInputs).map(input => input.value);
            }
        });
        
        // Taxonomy filters (dropdowns)
        this.container.querySelectorAll('.ajax-filter__select').forEach(select => {
            const taxonomy = select.dataset.taxonomy;
            if (select.value) {
                filters.taxonomies[taxonomy] = [select.value];
            }
        });
        
        // Taxonomy filters (buttons)
        this.container.querySelectorAll('.ajax-filter__button.is-active').forEach(button => {
            const taxonomy = button.dataset.taxonomy;
            const value = button.dataset.value;
            
            if (!filters.taxonomies[taxonomy]) {
                filters.taxonomies[taxonomy] = [];
            }
            filters.taxonomies[taxonomy].push(value);
        });
        
        // Range filters
        this.container.querySelectorAll('.ajax-filter--range').forEach(filter => {
            const key = filter.dataset.metaKey;
            const minSlider = filter.querySelector('.ajax-filter__range-min');
            const maxSlider = filter.querySelector('.ajax-filter__range-max');
            
            if (minSlider && maxSlider) {
                const min = parseFloat(minSlider.value);
                const max = parseFloat(maxSlider.value);
                const defaultMin = parseFloat(minSlider.min);
                const defaultMax = parseFloat(minSlider.max);
                
                // Only add if not default values
                if (min !== defaultMin || max !== defaultMax) {
                    filters.meta.push({
                        key: key,
                        min: min,
                        max: max
                    });
                }
            }
        });
        
        // Search
        if (this.searchInput && this.searchInput.value.trim()) {
            filters.search = this.searchInput.value.trim();
        }
        
        // Sort
        if (this.sortSelect && this.sortSelect.value) {
            const [orderby, order] = this.sortSelect.value.split('-');
            filters.orderby = orderby;
            filters.order = order.toUpperCase();
        }
        
        return filters;
    }
    
    updateResults(data) {
        // Fade out
        this.grid.style.opacity = '0';
        
        setTimeout(() => {
            // Update HTML
            this.grid.innerHTML = data.html;
            
            // Fade in
            this.grid.style.opacity = '1';
            
            // Scroll to top of results
            this.grid.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 200);
    }
    
    updateCount(count) {
        if (this.countElement) {
            this.countElement.textContent = this.formatNumber(count);
        }
    }
    
    updatePagination(maxPages, currentPage) {
        if (!this.pagination || maxPages <= 1) {
            this.pagination.innerHTML = '';
            return;
        }
        
        let html = '<div class="ajax-filters__pagination-wrapper">';
        
        // Previous button
        if (currentPage > 1) {
            html += `<button class="ajax-filters__page-button ajax-filters__page-prev" data-page="${currentPage - 1}">← Zurück</button>`;
        }
        
        // Page numbers
        html += '<div class="ajax-filters__page-numbers">';
        
        for (let i = 1; i <= maxPages; i++) {
            // Show first, last, current, and 2 around current
            if (i === 1 || i === maxPages || (i >= currentPage - 2 && i <= currentPage + 2)) {
                const activeClass = i === currentPage ? ' is-active' : '';
                html += `<button class="ajax-filters__page-number${activeClass}" data-page="${i}">${i}</button>`;
            } else if (i === currentPage - 3 || i === currentPage + 3) {
                html += '<span class="ajax-filters__page-dots">...</span>';
            }
        }
        
        html += '</div>';
        
        // Next button
        if (currentPage < maxPages) {
            html += `<button class="ajax-filters__page-button ajax-filters__page-next" data-page="${currentPage + 1}">Weiter →</button>`;
        }
        
        html += '</div>';
        
        this.pagination.innerHTML = html;
        
        // Bind pagination events
        this.pagination.querySelectorAll('[data-page]').forEach(button => {
            button.addEventListener('click', (e) => {
                const page = parseInt(e.currentTarget.dataset.page);
                this.loadResults(page);
            });
        });
    }
    
    updateActiveFilters(filters) {
        if (!this.activeFiltersList) return;
        
        let html = '';
        let hasActiveFilters = false;
        
        // Taxonomy filters
        Object.keys(filters.taxonomies).forEach(taxonomy => {
            filters.taxonomies[taxonomy].forEach(termSlug => {
                hasActiveFilters = true;
                const termName = this.getTermName(taxonomy, termSlug);
                html += `
                    <button class="ajax-filters__active-tag" data-taxonomy="${taxonomy}" data-value="${termSlug}">
                        ${termName}
                        <span class="ajax-filters__active-remove">×</span>
                    </button>
                `;
            });
        });
        
        // Meta filters
        filters.meta.forEach(metaFilter => {
            hasActiveFilters = true;
            html += `
                <button class="ajax-filters__active-tag" data-meta-key="${metaFilter.key}">
                    ${metaFilter.key}: ${this.formatNumber(metaFilter.min)} - ${this.formatNumber(metaFilter.max)}
                    <span class="ajax-filters__active-remove">×</span>
                </button>
            `;
        });
        
        // Search
        if (filters.search) {
            hasActiveFilters = true;
            html += `
                <button class="ajax-filters__active-tag" data-search>
                    Suche: "${filters.search}"
                    <span class="ajax-filters__active-remove">×</span>
                </button>
            `;
        }
        
        this.activeFiltersList.innerHTML = html;
        
        // Show/hide active filters container
        if (hasActiveFilters) {
            this.activeFiltersContainer.style.display = 'block';
        } else {
            this.activeFiltersContainer.style.display = 'none';
        }
        
        // Bind remove events
        this.activeFiltersList.querySelectorAll('.ajax-filters__active-tag').forEach(tag => {
            tag.addEventListener('click', (e) => this.removeActiveFilter(e));
        });
    }
    
    removeActiveFilter(e) {
        const tag = e.currentTarget;
        
        // Taxonomy filter
        if (tag.dataset.taxonomy) {
            const taxonomy = tag.dataset.taxonomy;
            const value = tag.dataset.value;
            
            // Uncheck checkbox/radio
            const input = this.container.querySelector(`input[data-taxonomy="${taxonomy}"][value="${value}"]`);
            if (input) {
                input.checked = false;
            }
            
            // Deselect dropdown
            const select = this.container.querySelector(`select[data-taxonomy="${taxonomy}"]`);
            if (select && select.value === value) {
                select.value = '';
            }
            
            // Deactivate button
            const button = this.container.querySelector(`.ajax-filter__button[data-taxonomy="${taxonomy}"][data-value="${value}"]`);
            if (button) {
                button.classList.remove('is-active');
            }
        }
        
        // Meta filter
        else if (tag.dataset.metaKey) {
            const key = tag.dataset.metaKey;
            const filter = this.container.querySelector(`.ajax-filter--range[data-meta-key="${key}"]`);
            
            if (filter) {
                const minSlider = filter.querySelector('.ajax-filter__range-min');
                const maxSlider = filter.querySelector('.ajax-filter__range-max');
                
                // Reset to defaults
                if (minSlider) minSlider.value = minSlider.min;
                if (maxSlider) maxSlider.value = maxSlider.max;
                
                // Update display
                const minValue = filter.querySelector('.ajax-filter__range-min-value');
                const maxValue = filter.querySelector('.ajax-filter__range-max-value');
                if (minValue) minValue.textContent = this.formatNumber(minSlider.min);
                if (maxValue) maxValue.textContent = this.formatNumber(maxSlider.max);
            }
        }
        
        // Search filter
        else if (tag.hasAttribute('data-search')) {
            if (this.searchInput) {
                this.searchInput.value = '';
            }
        }
        
        this.handleFilterChange();
    }
    
    resetFilters() {
        console.log('🔄 Resetting all filters');
        
        // Uncheck all checkboxes and radios
        this.container.querySelectorAll('.ajax-filter__option input').forEach(input => {
            input.checked = false;
        });
        
        // Reset all dropdowns
        this.container.querySelectorAll('.ajax-filter__select').forEach(select => {
            select.value = '';
        });
        
        // Deactivate all buttons
        this.container.querySelectorAll('.ajax-filter__button').forEach(button => {
            button.classList.remove('is-active');
        });
        
        // Reset all range sliders
        this.container.querySelectorAll('.ajax-filter--range').forEach(filter => {
            const minSlider = filter.querySelector('.ajax-filter__range-min');
            const maxSlider = filter.querySelector('.ajax-filter__range-max');
            
            if (minSlider) {
                minSlider.value = minSlider.min;
                const minValue = filter.querySelector('.ajax-filter__range-min-value');
                if (minValue) minValue.textContent = this.formatNumber(minSlider.min);
            }
            
            if (maxSlider) {
                maxSlider.value = maxSlider.max;
                const maxValue = filter.querySelector('.ajax-filter__range-max-value');
                if (maxValue) maxValue.textContent = this.formatNumber(maxSlider.max);
            }
        });
        
        // Clear search
        if (this.searchInput) {
            this.searchInput.value = '';
        }
        
        // Reset sort
        if (this.sortSelect) {
            this.sortSelect.value = 'date-desc';
        }
        
        // Reload results
        this.handleFilterChange();
    }
    
    toggleResetButton(filters) {
        if (!this.resetButton) return;
        
        const hasFilters = 
            Object.keys(filters.taxonomies).length > 0 ||
            filters.meta.length > 0 ||
            filters.search.length > 0;
        
        if (hasFilters) {
            this.resetButton.style.display = 'inline-block';
        } else {
            this.resetButton.style.display = 'none';
        }
    }
    
    updateUrl(filters) {
        const url = new URL(window.location);
        
        // Clear existing filter params
        url.searchParams.delete('filters');
        
        // Add new filters if any
        const hasFilters = 
            Object.keys(filters.taxonomies).length > 0 ||
            filters.meta.length > 0 ||
            filters.search.length > 0;
        
        if (hasFilters) {
            url.searchParams.set('filters', btoa(JSON.stringify(filters)));
        }
        
        // Update URL without reload
        window.history.pushState({}, '', url);
    }
    
    parseUrlParams() {
        const url = new URL(window.location);
        const filtersParam = url.searchParams.get('filters');
        
        if (filtersParam) {
            try {
                const filters = JSON.parse(atob(filtersParam));
                this.applyFiltersFromUrl(filters);
            } catch (e) {
                console.error('Error parsing URL filters:', e);
            }
        }
    }
    
    applyFiltersFromUrl(filters) {
        // Apply taxonomy filters
        Object.keys(filters.taxonomies).forEach(taxonomy => {
            filters.taxonomies[taxonomy].forEach(value => {
                const input = this.container.querySelector(`input[data-taxonomy="${taxonomy}"][value="${value}"]`);
                if (input) {
                    input.checked = true;
                }
            });
        });
        
        // Apply search
        if (filters.search && this.searchInput) {
            this.searchInput.value = filters.search;
        }
        
        // Apply sort
        if (filters.orderby && filters.order && this.sortSelect) {
            this.sortSelect.value = `${filters.orderby}-${filters.order.toLowerCase()}`;
        }
    }
    
    showLoading() {
        if (this.loading) {
            this.loading.style.display = 'flex';
        }
        this.grid.style.opacity = '0.5';
    }
    
    hideLoading() {
        if (this.loading) {
            this.loading.style.display = 'none';
        }
        this.grid.style.opacity = '1';
    }
    
    showError() {
        this.grid.innerHTML = '<div class="ajax-filters__error"><p>Fehler beim Laden der Ergebnisse. Bitte versuchen Sie es erneut.</p></div>';
    }
    
    getTermName(taxonomy, slug) {
        const input = this.container.querySelector(`input[data-taxonomy="${taxonomy}"][value="${slug}"]`);
        if (input) {
            const label = input.closest('.ajax-filter__option');
            if (label) {
                return label.querySelector('.ajax-filter__option-label').textContent.trim();
            }
        }
        
        const option = this.container.querySelector(`option[value="${slug}"]`);
        if (option) {
            return option.textContent.trim();
        }
        
        return slug;
    }
    
    formatNumber(number) {
        return new Intl.NumberFormat('de-DE').format(number);
    }
}

// Initialize all AJAX Filters on page
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.ajax-filters').forEach(container => {
        new AjaxFilters(container);
    });
});

// Export for use in other modules
export default AjaxFilters;