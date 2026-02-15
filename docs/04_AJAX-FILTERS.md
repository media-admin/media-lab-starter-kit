# AJAX Filters System Guide

Comprehensive guide for the Advanced AJAX Filters system - a professional filtering solution comparable to commercial plugins like Ajax Filter Pro.

---

## 📋 Table of Contents

- [Overview](#overview)
- [Quick Start](#quick-start)
- [System Architecture](#system-architecture)
- [Container Shortcode](#container-shortcode)
- [Filter Types](#filter-types)
- [Result Templates](#result-templates)
- [Advanced Examples](#advanced-examples)
- [Customization](#customization)
- [Performance](#performance)
- [Troubleshooting](#troubleshooting)
- [API Reference](#api-reference)

---

## 🎯 Overview

The AJAX Filters system provides advanced filtering capabilities for any WordPress content type (posts, custom post types, WooCommerce products) without page reloads.

### Features

✅ **Multiple Filter Types**
- Taxonomy filters (checkbox, radio, dropdown, buttons)
- Meta field range sliders
- Text search with debounce
- Sort options

✅ **Advanced Functionality**
- Real-time AJAX filtering
- Pagination with AJAX
- Active filter tags (removable)
- URL parameters (shareable links)
- Reset button
- Result count
- Loading states

✅ **5 Result Templates**
- Card layout
- List layout
- Grid layout
- Job layout (specialized)
- Product layout (WooCommerce)

✅ **Professional Features**
- Mobile responsive
- SEO friendly (URLs)
- Accessibility compliant
- Error handling
- No results messaging

---

## 🚀 Quick Start

### Basic Example
```
[ajax_filters post_type="post" posts_per_page="12" template="card" columns="3"]
  [filter_search placeholder="Search..."]
  [filter_taxonomy taxonomy="category" type="checkbox" label="Category"]
[/ajax_filters]
```

### Job Board Example
```
[ajax_filters post_type="job" posts_per_page="12" template="job" columns="2"]
  [filter_search placeholder="Search jobs..." label="Search"]
  [filter_taxonomy taxonomy="job_category" type="checkbox" label="Category"]
  [filter_taxonomy taxonomy="job_type" type="buttons" label="Employment Type"]
  [filter_taxonomy taxonomy="job_location" type="dropdown" label="Location"]
  [filter_range key="salary_min" min="0" max="150000" step="5000" label="Minimum Salary" suffix=" €"]
[/ajax_filters]
```

### E-Commerce Example
```
[ajax_filters post_type="product" posts_per_page="16" template="product" columns="4"]
  [filter_search placeholder="Search products..."]
  [filter_taxonomy taxonomy="product_cat" type="checkbox" label="Categories"]
  [filter_range key="_price" min="0" max="1000" step="10" label="Price" suffix=" €"]
  [filter_taxonomy taxonomy="pa_color" type="buttons" label="Color"]
[/ajax_filters]
```

---

## 🏗️ System Architecture

### How It Works
```
User Interface (Browser)
    ↓
JavaScript collects filters
    ↓
AJAX request to WordPress
    ↓
PHP processes query
    ↓
Results rendered with template
    ↓
JSON response sent back
    ↓
JavaScript updates UI
```

### Files Structure
```
Backend (PHP):
cms/wp-content/mu-plugins/agency-core/inc/
└── ajax-filters.php              # AJAX handler & templates

Frontend (JavaScript):
cms/wp-content/themes/custom-theme/assets/src/js/
└── components/
    └── ajax-filters.js           # Frontend logic

Styling (SCSS):
cms/wp-content/themes/custom-theme/assets/src/scss/
└── components/
    └── _ajax-filters.scss        # Filter styles
```

### Request Flow

1. **User interacts** with filter (checkbox, slider, search)
2. **JavaScript collects** all active filters
3. **AJAX request** sent to `wp-admin/admin-ajax.php`
4. **PHP handler** processes request:
   - Validates nonce
   - Builds WP_Query with filters
   - Renders results with template
5. **JSON response** contains:
   - HTML results
   - Total found posts
   - Max pages
   - Current page
6. **JavaScript updates**:
   - Results grid
   - Result count
   - Pagination
   - Active filter tags
   - URL parameters

---

## 📦 Container Shortcode

### Syntax
```
[ajax_filters 
  post_type="post"
  posts_per_page="12"
  template="card"
  columns="3"
  show_count="true"
  show_sort="true"
  show_reset="true"
]
  <!-- Filter shortcodes here -->
[/ajax_filters]
```

### Parameters

| Parameter | Type | Default | Options | Description |
|-----------|------|---------|---------|-------------|
| `post_type` | string | `post` | Any CPT | Post type to filter |
| `posts_per_page` | int | `12` | 1-100 | Results per page |
| `template` | string | `card` | `card`, `list`, `grid`, `job`, `product` | Result template |
| `columns` | int | `3` | 1-4 | Grid columns |
| `show_count` | bool | `true` | `true`, `false` | Show result count |
| `show_sort` | bool | `true` | `true`, `false` | Show sort dropdown |
| `show_reset` | bool | `true` | `true`, `false` | Show reset button |

### Container Structure

The container creates this HTML structure:
```html
<div class="ajax-filters" data-post-type="job" data-posts-per-page="12">
  
  <!-- Sidebar with filters -->
  <div class="ajax-filters__sidebar">
    <div class="ajax-filters__header">
      <h3>Filter</h3>
      <button class="ajax-filters__reset">Reset</button>
    </div>
    
    <div class="ajax-filters__forms">
      <!-- Filter widgets here -->
    </div>
    
    <div class="ajax-filters__active">
      <!-- Active filter tags -->
    </div>
  </div>
  
  <!-- Results area -->
  <div class="ajax-filters__results">
    <div class="ajax-filters__toolbar">
      <div class="ajax-filters__count">12 Results</div>
      <select class="ajax-filters__sort-select">
        <option>Newest first</option>
        <!-- More options -->
      </select>
    </div>
    
    <div class="ajax-filters__loading"><!-- Spinner --></div>
    
    <div class="ajax-filters__grid">
      <!-- Results cards -->
    </div>
    
    <div class="ajax-filters__pagination">
      <!-- Pagination buttons -->
    </div>
  </div>
  
</div>
```

---

## 🔍 Filter Types

### 1. Search Filter

Text search with debounce.

**Shortcode:**
```
[filter_search placeholder="Search..." label="Search"]
```

**Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `placeholder` | string | `Search...` | Input placeholder |
| `label` | string | `Search` | Filter label |

**Features:**
- 500ms debounce (auto-search after typing stops)
- Search button for manual trigger
- Searches post title and content
- Can be combined with other filters

**Example:**
```
[filter_search placeholder="Search jobs by title or keyword..." label="Job Search"]
```

---

### 2. Taxonomy Filters

Filter by WordPress taxonomies (categories, tags, custom taxonomies).

**Shortcode:**
```
[filter_taxonomy 
  taxonomy="category"
  type="checkbox"
  label="Category"
  show_count="true"
  operator="IN"
]
```

**Parameters:**

| Parameter | Type | Default | Options | Description |
|-----------|------|---------|---------|-------------|
| `taxonomy` | string | required | Any taxonomy | Taxonomy slug |
| `type` | string | `checkbox` | `checkbox`, `radio`, `dropdown`, `buttons` | Display type |
| `label` | string | required | Any text | Filter label |
| `show_count` | bool | `true` | `true`, `false` | Show term counts |
| `operator` | string | `IN` | `IN`, `AND`, `NOT IN` | Query operator |

#### Type: Checkbox (Multiple Selection)

**Best for:** 3-10 options, multiple selections needed
```
[filter_taxonomy taxonomy="job_category" type="checkbox" label="Job Category" show_count="true"]
```

**Output:**
```
☐ Development (12)
☐ Design (8)
☐ Marketing (15)
☐ Sales (6)
```

**Use cases:**
- Post categories
- Product categories
- Job categories
- Project types

---

#### Type: Radio (Single Selection)

**Best for:** 2-5 options, single selection
```
[filter_taxonomy taxonomy="job_type" type="radio" label="Employment Type"]
```

**Output:**
```
○ Full-time
○ Part-time
○ Contract
○ Freelance
```

**Use cases:**
- Employment type
- Property status (for sale/rent)
- Event type
- Product condition (new/used)

---

#### Type: Dropdown (Single Selection)

**Best for:** 10+ options, single selection, save space
```
[filter_taxonomy taxonomy="job_location" type="dropdown" label="Location" show_count="true"]
```

**Output:**
```
[Select Location ▼]
  All
  Vienna (23)
  Berlin (15)
  Munich (8)
  ...
```

**Use cases:**
- Locations (many cities)
- Years
- Alphabetical lists
- Large term sets

---

#### Type: Buttons (Multiple Selection)

**Best for:** 2-8 important options, visual emphasis
```
[filter_taxonomy taxonomy="product_cat" type="buttons" label="Categories" show_count="true"]
```

**Output:**
```
[Electronics] [Clothing] [Books] [Home & Garden]
```

Buttons toggle active state on click.

**Use cases:**
- Featured categories
- Product attributes (size, color)
- Important filters
- Visual tags

---

### 3. Range Slider Filter

Filter numeric meta fields with min/max range.

**Shortcode:**
```
[filter_range 
  key="price"
  min="0"
  max="1000"
  step="10"
  label="Price"
  prefix=""
  suffix=" €"
]
```

**Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `key` | string | required | Meta field key |
| `min` | int | `0` | Minimum value |
| `max` | int | `100` | Maximum value |
| `step` | int | `1` | Step increment |
| `label` | string | required | Filter label |
| `prefix` | string | - | Value prefix (e.g., "$") |
| `suffix` | string | - | Value suffix (e.g., " €") |

**Features:**
- Dual sliders (min and max)
- Live value display
- Prevents overlap
- Custom formatting (prefix/suffix)

**Common Use Cases:**

#### Price Range
```
[filter_range key="_price" min="0" max="500" step="10" label="Price" suffix=" €"]
```

#### Salary Range
```
[filter_range key="salary_min" min="20000" max="150000" step="5000" label="Minimum Salary" suffix=" € / year"]
```

#### Size/Area Range
```
[filter_range key="sqm" min="20" max="500" step="10" label="Living Area" suffix=" m²"]
```

#### Year Range
```
[filter_range key="year" min="2000" max="2026" step="1" label="Year"]
```

#### Distance Range
```
[filter_range key="distance" min="0" max="100" step="5" label="Distance" suffix=" km"]
```

#### Rating Range
```
[filter_range key="rating" min="1" max="5" step="1" label="Minimum Rating" suffix=" stars"]
```

**Meta Field Setup:**

For range filters to work, posts need numeric meta fields:
```php
// When saving post (or use ACF)
update_post_meta($post_id, 'price', 299);
update_post_meta($post_id, 'salary_min', 45000);
update_post_meta($post_id, 'sqm', 85);
```

With ACF, create a "Number" field with the appropriate key.

---

## 📐 Result Templates

### Card Template (Default)

**Best for:** Blog posts, projects, general content

**Usage:**
```
[ajax_filters template="card" columns="3"]
```

**Layout:**
- Featured image (16:9 ratio)
- Title
- Meta information (date, author)
- Excerpt
- "Read more" link

**Columns:** 1-4 (responsive)

---

### List Template

**Best for:** News, events, compact listings

**Usage:**
```
[ajax_filters template="list" columns="1"]
```

**Layout:**
- Small thumbnail (left)
- Title and excerpt (right)
- Meta information
- Horizontal layout

**Columns:** Usually 1, max 2

---

### Grid Template

**Best for:** Portfolios, galleries, image-focused

**Usage:**
```
[ajax_filters template="grid" columns="4"]
```

**Layout:**
- Large image (1:1 ratio)
- Overlay on hover
- Title centered
- Minimal text

**Columns:** 2-4 (responsive)

---

### Job Template (Specialized)

**Best for:** Job boards, career pages

**Usage:**
```
[ajax_filters template="job" columns="2"]
```

**Layout:**
- Job title
- Location with icon
- Employment type badge
- Salary range
- Excerpt
- "View Details" button
- Featured badge (if applicable)
- Left border color indicator

**Features:**
- Specialized styling
- Custom badges
- Conditional elements

**Required ACF Fields:**
- `employment_type`
- `salary_min`
- `salary_max`
- `featured` (true/false)
- Taxonomy: `job_location`

---

### Product Template (WooCommerce)

**Best for:** E-commerce, shop pages

**Usage:**
```
[ajax_filters template="product" columns="4"]
```

**Layout:**
- Product image
- Title
- Price (with sale price)
- Star rating
- "Sale" badge (if on sale)
- Add to cart button

**Features:**
- WooCommerce integration
- Price formatting
- Sale indicators
- Cart functionality

**Requirements:**
- WooCommerce plugin active
- Product post type

---

## 🎨 Advanced Examples

### Complete Job Board
```
<h1>Find Your Dream Job</h1>
<p>Browse through 150+ open positions at leading companies</p>

[ajax_filters 
  post_type="job" 
  posts_per_page="9" 
  template="job" 
  columns="3"
  show_count="true"
  show_sort="true"
  show_reset="true"
]
  
  <!-- Search -->
  [filter_search 
    placeholder="Job title, company, or keyword..." 
    label="Search Jobs"
  ]
  
  <!-- Category (Multiple) -->
  [filter_taxonomy 
    taxonomy="job_category" 
    type="checkbox" 
    label="Category" 
    show_count="true"
  ]
  
  <!-- Employment Type (Buttons) -->
  [filter_taxonomy 
    taxonomy="job_type" 
    type="buttons" 
    label="Employment Type" 
    show_count="true"
  ]
  
  <!-- Location (Dropdown) -->
  [filter_taxonomy 
    taxonomy="job_location" 
    type="dropdown" 
    label="Location" 
    show_count="true"
  ]
  
  <!-- Salary Range -->
  [filter_range 
    key="salary_min" 
    min="20000" 
    max="150000" 
    step="5000" 
    label="Minimum Salary" 
    suffix=" € / year"
  ]
  
  <!-- Experience Range -->
  [filter_range 
    key="experience_years" 
    min="0" 
    max="15" 
    step="1" 
    label="Years of Experience" 
    suffix=" years"
  ]

[/ajax_filters]
```

**Features:**
- 6 different filter types
- Job-specific template
- Salary and experience ranges
- Multiple taxonomy filters
- Professional styling

---

### E-Commerce Product Filter
```
<h1>Our Products</h1>

[ajax_filters 
  post_type="product" 
  posts_per_page="20" 
  template="product" 
  columns="4"
]
  
  <!-- Search -->
  [filter_search placeholder="Search products..."]
  
  <!-- Categories -->
  [filter_taxonomy 
    taxonomy="product_cat" 
    type="checkbox" 
    label="Categories" 
    show_count="true"
  ]
  
  <!-- Brands (if custom taxonomy exists) -->
  [filter_taxonomy 
    taxonomy="brand" 
    type="buttons" 
    label="Brands"
  ]
  
  <!-- Price Range -->
  [filter_range 
    key="_price" 
    min="0" 
    max="1000" 
    step="10" 
    label="Price" 
    suffix=" €"
  ]
  
  <!-- Size (Product Attribute) -->
  [filter_taxonomy 
    taxonomy="pa_size" 
    type="buttons" 
    label="Size"
  ]
  
  <!-- Color (Product Attribute) -->
  [filter_taxonomy 
    taxonomy="pa_color" 
    type="buttons" 
    label="Color"
  ]
  
  <!-- On Sale (if custom field exists) -->
  [filter_taxonomy 
    taxonomy="product_visibility" 
    type="checkbox" 
    label="Special Offers"
  ]

[/ajax_filters]
```

**Features:**
- WooCommerce integration
- Product attributes (size, color)
- Price filtering
- Sale items filter
- 4-column grid

---

### Real Estate Property Filter
```
<h1>Find Your Property</h1>

[ajax_filters 
  post_type="property" 
  posts_per_page="12" 
  template="card" 
  columns="3"
]
  
  <!-- Search -->
  [filter_search 
    placeholder="Address, city, or ZIP code..." 
    label="Location Search"
  ]
  
  <!-- Property Type -->
  [filter_taxonomy 
    taxonomy="property_type" 
    type="buttons" 
    label="Property Type" 
    show_count="true"
  ]
  
  <!-- Status -->
  [filter_taxonomy 
    taxonomy="property_status" 
    type="radio" 
    label="Status"
  ]
  
  <!-- Price Range -->
  [filter_range 
    key="price" 
    min="100000" 
    max="2000000" 
    step="50000" 
    label="Price" 
    suffix=" €"
  ]
  
  <!-- Living Area -->
  [filter_range 
    key="sqm" 
    min="20" 
    max="500" 
    step="10" 
    label="Living Area" 
    suffix=" m²"
  ]
  
  <!-- Rooms -->
  [filter_range 
    key="rooms" 
    min="1" 
    max="10" 
    step="1" 
    label="Number of Rooms"
  ]
  
  <!-- City -->
  [filter_taxonomy 
    taxonomy="city" 
    type="dropdown" 
    label="City" 
    show_count="true"
  ]

[/ajax_filters]
```

---

### Restaurant Menu Filter
```
<h1>Our Menu</h1>

[ajax_filters 
  post_type="menu_item" 
  posts_per_page="20" 
  template="card" 
  columns="3"
]
  
  <!-- Search -->
  [filter_search placeholder="Search dishes..."]
  
  <!-- Menu Category -->
  [filter_taxonomy 
    taxonomy="menu_category" 
    type="buttons" 
    label="Category" 
    show_count="true"
  ]
  
  <!-- Dietary -->
  [filter_taxonomy 
    taxonomy="dietary" 
    type="checkbox" 
    label="Dietary Options"
  ]
  
  <!-- Allergens -->
  [filter_taxonomy 
    taxonomy="allergens" 
    type="checkbox" 
    label="Allergen-Free"
  ]
  
  <!-- Price Range -->
  [filter_range 
    key="price" 
    min="5" 
    max="50" 
    step="1" 
    label="Price" 
    suffix=" €"
  ]

[/ajax_filters]
```

---

### Event Calendar Filter
```
<h1>Upcoming Events</h1>

[ajax_filters 
  post_type="event" 
  posts_per_page="12" 
  template="list" 
  columns="1"
]
  
  <!-- Search -->
  [filter_search placeholder="Search events..."]
  
  <!-- Event Category -->
  [filter_taxonomy 
    taxonomy="event_category" 
    type="buttons" 
    label="Event Type"
  ]
  
  <!-- Location -->
  [filter_taxonomy 
    taxonomy="event_location" 
    type="dropdown" 
    label="Location"
  ]
  
  <!-- Year -->
  [filter_range 
    key="event_year" 
    min="2024" 
    max="2026" 
    step="1" 
    label="Year"
  ]

[/ajax_filters]
```

---

## 🎨 Customization

### Custom Result Template

You can create your own result template by adding it to the backend.

**File:** `cms/wp-content/mu-plugins/agency-core/inc/ajax-filters.php`

**Add new template function:**
```php
/**
 * Custom Event Template
 */
function agency_core_template_filter_event() {
    $event_date = get_field('event_date');
    $event_location = get_field('event_location');
    ?>
    <article class="filter-result filter-result--event">
        <div class="filter-result__date">
            <?php echo date('M d, Y', strtotime($event_date)); ?>
        </div>
        
        <h3 class="filter-result__title">
            <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
        </h3>
        
        <div class="filter-result__location">
            <span class="dashicons dashicons-location"></span>
            <?php echo esc_html($event_location); ?>
        </div>
        
        <div class="filter-result__excerpt">
            <?php echo wp_trim_words(get_the_excerpt(), 20); ?>
        </div>
        
        <a href="<?php the_permalink(); ?>" class="filter-result__button">
            Register Now
        </a>
    </article>
    <?php
}
```

**Register template:**

Find the `agency_core_render_filter_template()` function and add:
```php
case 'event':
    agency_core_template_filter_event();
    break;
```

**Use it:**
```
[ajax_filters template="event"]
```

---

### Custom Styling

**File:** `cms/wp-content/themes/custom-theme/assets/src/scss/components/_ajax-filters-custom.scss`
```scss
// Custom filter styling
.ajax-filters {
  // Change sidebar background
  &__sidebar {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
  }
  
  // Custom button style
  .ajax-filter__button {
    border-radius: 20px;
    
    &.is-active {
      background: #f59e0b;
      border-color: #f59e0b;
    }
  }
  
  // Custom result cards
  .filter-result {
    border: 2px solid #e5e7eb;
    transition: all 0.3s ease;
    
    &:hover {
      border-color: #667eea;
      box-shadow: 0 10px 30px rgba(102, 126, 234, 0.2);
    }
  }
}
```

**Import in style.scss:**
```scss
@import 'components/ajax-filters-custom';
```

---

### JavaScript Hooks

You can hook into JavaScript events:

**File:** `cms/wp-content/themes/custom-theme/assets/src/js/custom-filters.js`
```javascript
document.addEventListener('DOMContentLoaded', () => {
  // Get filter container
  const filterContainer = document.querySelector('.ajax-filters');
  
  if (filterContainer) {
    // Listen for filter changes
    filterContainer.addEventListener('filterChanged', (e) => {
      console.log('Filters changed:', e.detail);
      
      // Your custom logic
      // e.g., Google Analytics tracking
      gtag('event', 'filter_used', {
        'filter_type': e.detail.filterType,
        'filter_value': e.detail.filterValue
      });
    });
    
    // Listen for results loaded
    filterContainer.addEventListener('resultsLoaded', (e) => {
      console.log('Results loaded:', e.detail.foundPosts);
      
      // Your custom logic
    });
  }
});
```

---

## ⚡ Performance

### Optimization Tips

**1. Limit Results**
```
posts_per_page="12"  // Good: 12 items
posts_per_page="100" // Bad: Too many
```

**2. Index Meta Fields**

For range filters, add database indexes:
```sql
ALTER TABLE wp_postmeta ADD INDEX meta_key_value (meta_key, meta_value(10));
```

**3. Cache Query Results**

Enable object caching (Redis, Memcached):
```php
// In wp-config.php
define('WP_CACHE', true);
```

**4. Lazy Load Images**

Enable lazy loading in theme:
```php
add_filter('wp_lazy_loading_enabled', '__return_true');
```

**5. Optimize Taxonomies**

Only query needed taxonomy fields:
```php
// Already implemented in ajax-filters.php
'tax_query' => array(
    'relation' => 'AND',
    // Optimized queries
)
```

---

## 🐛 Troubleshooting

### Issue: Filters Not Working

**Symptoms:**
- Clicking filters does nothing
- Console shows errors
- 403 Forbidden error

**Solutions:**

1. **Check nonce:**
```javascript
// Browser console
console.log(window.customTheme.filtersNonce);
// Should show a string, not undefined
```

2. **Verify enqueue.php path:**
```php
// In functions.php, should be:
require_once get_template_directory() . '/src/inc/enqueue.php';
```

3. **Check JavaScript loads:**
```bash
# Browser DevTools → Network → Filter: JS
# Should see main.js with status 200
```

4. **Clear all caches:**
```bash
cd cms && wp cache flush && cd ..
npm run build
# Clear browser cache (CTRL+SHIFT+DELETE)
```

---

### Issue: No Results Showing

**Symptoms:**
- Grid is empty
- "0 Results" shows
- But posts exist

**Solutions:**

1. **Check post status:**
```bash
cd cms
wp post list --post_type=job --post_status=publish
cd ..
```

2. **Verify taxonomy slugs:**
```bash
cd cms
wp term list job_category
# Check taxonomy slug matches exactly
cd ..
```

3. **Check meta key names:**
```php
// In WordPress admin, edit a post
// Custom Fields → Check exact meta key spelling
```

4. **Test query manually:**
```php
// Add to functions.php temporarily
add_action('init', function() {
    $query = new WP_Query(array(
        'post_type' => 'job',
        'posts_per_page' => 5,
    ));
    error_log('Found: ' . $query->found_posts);
});
```

---

### Issue: Range Slider Not Working

**Symptoms:**
- Sliders don't move
- Values don't update
- No filtering happens

**Solutions:**

1. **Check meta field exists:**
```bash
cd cms
wp post meta list POST_ID
# Replace POST_ID with actual post ID
cd ..
```

2. **Verify numeric values:**
```php
// Meta values must be numeric
update_post_meta($post_id, 'salary_min', 45000); // Good
update_post_meta($post_id, 'salary_min', '45000'); // Also works
update_post_meta($post_id, 'salary_min', '45k'); // BAD!
```

3. **Check ACF field type:**
- Must be "Number" field type
- Not "Text" field

---

### Issue: Pagination Not Working

**Symptoms:**
- Page 2+ shows same results
- Pagination buttons don't work

**Solutions:**

1. **Check permalink structure:**
```bash
cd cms
wp rewrite structure '/%postname%/'
wp rewrite flush
cd ..
```

2. **Verify AJAX handler:**
```javascript
// Browser console
console.log('Max pages:', data.max_pages);
// Should show number > 1
```

---

### Issue: Active Filters Not Removable

**Symptoms:**
- Clicking X doesn't remove filter
- Filter stays active

**Solutions:**

1. **Check data attributes:**
```html
<!-- Tags should have data attributes -->
<button data-taxonomy="category" data-value="news">
  News <span>×</span>
</button>
```

2. **Verify JavaScript event binding:**
```javascript
// Should see in ajax-filters.js
this.activeFiltersList.querySelectorAll('.ajax-filters__active-tag')
  .forEach(tag => {
    tag.addEventListener('click', (e) => this.removeActiveFilter(e));
  });
```

---

## 📚 API Reference

### PHP Filters

**Modify query arguments:**
```php
add_filter('agency_core_ajax_filter_query_args', function($args, $post_type) {
    if ($post_type === 'job') {
        // Only show featured jobs
        $args['meta_query'][] = array(
            'key' => 'featured',
            'value' => '1',
            'compare' => '='
        );
    }
    return $args;
}, 10, 2);
```

**Modify result template:**
```php
add_filter('agency_core_filter_result_template', function($template, $post_type) {
    if ($post_type === 'job') {
        return 'custom-job';
    }
    return $template;
}, 10, 2);
```

### PHP Actions

**Before query execution:**
```php
add_action('agency_core_before_filter_query', function($args) {
    error_log('Filter query: ' . print_r($args, true));
});
```

**After query execution:**
```php
add_action('agency_core_after_filter_query', function($query) {
    error_log('Found posts: ' . $query->found_posts);
});
```

### JavaScript Events

**Filter changed:**
```javascript
document.addEventListener('filterChanged', (e) => {
    console.log('Filter changed:', e.detail);
});
```

**Results loaded:**
```javascript
document.addEventListener('resultsLoaded', (e) => {
    console.log('Results:', e.detail);
});
```

---

## 🎓 Best Practices

### Filter Organization

**Good:**
```
1. Search (top)
2. Primary filters (most important taxonomies)
3. Secondary filters (less important)
4. Range filters (bottom)
```

**Bad:**
```
Range, Search, Taxonomy, Taxonomy (random order)
```

### Filter Type Selection

| Scenario | Use |
|----------|-----|
| 3-10 options, multiple select | Checkboxes |
| 2-5 options, single select | Radio buttons |
| 10+ options, single select | Dropdown |
| 2-8 important options | Buttons |
| Numeric range | Range slider |
| Text query | Search |

### Performance

- Keep `posts_per_page` reasonable (12-20)
- Use pagination for large result sets
- Enable caching
- Index meta fields
- Optimize images

### User Experience

- Provide clear labels
- Show result counts
- Include reset button
- Display active filters
- Show loading states
- Handle no results gracefully

### Mobile

- Test all filters on mobile
- Ensure touch targets are 44x44px minimum
- Consider collapsible sidebar on mobile
- Test landscape and portrait

---

## 📞 Support

Need help with filters?

- Check [Troubleshooting](#troubleshooting) above
- Review [SHORTCODES.md](./SHORTCODES.md)
- Search [GitHub Issues](https://github.com/your-agency/wordpress-starter-kit/issues)
- Contact: support@your-agency.com

---

**Last Updated:** February 2026  
**Version:** 1.0.0