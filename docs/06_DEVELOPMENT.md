# Development Guide

Comprehensive guide for developers working on the WordPress Agency Starter Kit.

---

## 📋 Table of Contents

- [Development Environment](#development-environment)
- [Code Standards](#code-standards)
- [Project Structure](#project-structure)
- [Development Workflow](#development-workflow)
- [Creating Custom Post Types](#creating-custom-post-types)
- [Creating Shortcodes](#creating-shortcodes)
- [Working with ACF](#working-with-acf)
- [JavaScript Development](#javascript-development)
- [SCSS Development](#scss-development)
- [PHP Best Practices](#php-best-practices)
- [Hooks & Filters](#hooks--filters)
- [Testing](#testing)
- [Debugging](#debugging)
- [Performance](#performance)
- [Security](#security)
- [Git Workflow](#git-workflow)

---

## 💻 Development Environment

### Prerequisites
```bash
# Required versions
PHP >= 8.0
Node.js >= 18.0
npm >= 9.0
Composer >= 2.0
WP-CLI >= 2.8

# Check versions
php -v
node -v
npm -v
composer --version
wp --version
```

### Local Setup

**Option 1: Valet (macOS - Recommended)**
```bash
# Install Valet
composer global require laravel/valet
valet install

# Park directory
cd ~/Sites
valet park

# Create project
mkdir your-project && cd your-project
wp core download --path=cms
cd cms
wp core config --dbname=database --dbuser=root --dbpass=root
wp core install --url=your-project.test --title="Site" --admin_user=admin --admin_email=admin@example.com
cd ..

# Install dependencies
composer install
npm install

# Start development
npm run dev
```

**Option 2: Docker**
```yaml
# docker-compose.yml
version: '3.8'

services:
  wordpress:
    image: wordpress:latest
    ports:
      - "8080:80"
    environment:
      WORDPRESS_DB_HOST: db
      WORDPRESS_DB_USER: wordpress
      WORDPRESS_DB_PASSWORD: wordpress
      WORDPRESS_DB_NAME: wordpress
    volumes:
      - ./cms:/var/www/html
      
  db:
    image: mysql:8.0
    environment:
      MYSQL_DATABASE: wordpress
      MYSQL_USER: wordpress
      MYSQL_PASSWORD: wordpress
      MYSQL_ROOT_PASSWORD: rootpassword
    volumes:
      - db_data:/var/lib/mysql

volumes:
  db_data:
```
```bash
# Start containers
docker-compose up -d

# Install WordPress
docker-compose exec wordpress wp core install \
  --url=localhost:8080 \
  --title="Site" \
  --admin_user=admin \
  --admin_email=admin@example.com
```

### IDE Setup

**VS Code (Recommended)**

**.vscode/settings.json:**
```json
{
  "editor.tabSize": 4,
  "editor.insertSpaces": true,
  "files.associations": {
    "*.php": "php"
  },
  "php.validate.executablePath": "/usr/local/bin/php",
  "emmet.includeLanguages": {
    "php": "html"
  },
  "[php]": {
    "editor.defaultFormatter": "bmewburn.vscode-intelephense-client"
  },
  "[javascript]": {
    "editor.defaultFormatter": "esbenp.prettier-vscode"
  },
  "[scss]": {
    "editor.defaultFormatter": "esbenp.prettier-vscode"
  }
}
```

**Recommended Extensions:**

- PHP Intelephense
- ESLint
- Prettier
- SCSS Formatter
- WordPress Snippets
- GitLens

**PHPStorm**

1. Install WordPress plugin
2. Set PHP interpreter
3. Configure code style: PSR-12
4. Enable WordPress coding standards

---

## 📏 Code Standards

### PHP Standards

**Follow PSR-12 and WordPress Coding Standards**
```php
<?php
/**
 * File description
 * 
 * @package Agency_Core
 * @since 1.0.0
 */

// Always use strict comparison
if ( 'value' === $variable ) {
    // Code
}

// Use Yoda conditions (WordPress standard)
if ( 10 === $count ) {
    // Code
}

// Function naming: lowercase with underscores
function agency_core_custom_function() {
    // Code
}

// Class naming: PascalCase
class Agency_Core_Custom_Class {
    // Properties: camelCase
    private $propertyName;
    
    // Methods: snake_case (WordPress standard)
    public function get_property_name() {
        return $this->propertyName;
    }
}

// Always escape output
echo esc_html( $variable );
echo esc_attr( $attribute );
echo esc_url( $url );

// Always sanitize input
$clean = sanitize_text_field( $_POST['field'] );
$email = sanitize_email( $_POST['email'] );

// Use nonce verification
if ( ! wp_verify_nonce( $_POST['nonce'], 'action_name' ) ) {
    wp_die( 'Security check failed' );
}
```

### JavaScript Standards

**ES6+ with ESLint**
```javascript
// Use const/let, never var
const API_URL = 'https://api.example.com';
let counter = 0;

// Arrow functions
const fetchData = async () => {
    const response = await fetch(API_URL);
    return response.json();
};

// Destructuring
const { name, email } = user;
const [first, second] = array;

// Template literals
const message = `Hello ${name}, your email is ${email}`;

// Spread operator
const newArray = [...oldArray, newItem];
const newObject = { ...oldObject, newProp: 'value' };

// Always use === (strict equality)
if (value === 'test') {
    // Code
}

// Error handling
try {
    const data = await fetchData();
} catch (error) {
    console.error('Error:', error);
}

// Comments
/**
 * Function description
 * @param {string} name - User name
 * @returns {Object} User object
 */
function getUser(name) {
    return { name };
}
```

### SCSS Standards

**BEM Methodology**
```scss
// Block
.component {
    display: flex;
    
    // Element
    &__title {
        font-size: $font-size-xl;
        color: $color-primary;
    }
    
    &__content {
        padding: $spacing-md;
    }
    
    // Modifier
    &--featured {
        border: 2px solid $color-primary;
    }
    
    // State
    &.is-active {
        background: $color-primary;
    }
}

// Nesting max 3 levels
.parent {
    .child {
        .grandchild {
            // Max depth
        }
    }
}

// Use variables
$color-primary: #667eea;
$spacing-md: 1.5rem;

// Mixins for reusable patterns
@mixin flex-center {
    display: flex;
    align-items: center;
    justify-content: center;
}

.component {
    @include flex-center;
}
```

---

## 📁 Project Structure
```
project-root/
├── cms/                              # WordPress installation
│   ├── wp-admin/
│   ├── wp-includes/
│   └── wp-content/
│       ├── mu-plugins/
│       │   └── agency-core/          # Business logic plugin
│       │       ├── agency-core.php
│       │       └── inc/
│       │           ├── custom-post-types.php
│       │           ├── acf-fields.php
│       │           ├── shortcodes.php
│       │           ├── ajax-search.php
│       │           ├── ajax-filters.php
│       │           └── ajax-load-more.php
│       │
│       ├── plugins/
│       │   └── advanced-custom-fields-pro/
│       │
│       └── themes/
│           └── custom-theme/         # Presentation layer
│               ├── assets/
│               │   ├── src/          # Source files
│               │   │   ├── scss/
│               │   │   │   ├── base/
│               │   │   │   ├── components/
│               │   │   │   ├── layout/
│               │   │   │   └── style.scss
│               │   │   └── js/
│               │   │       ├── components/
│               │   │       ├── utils/
│               │   │       └── main.js
│               │   └── dist/         # Compiled assets
│               │       ├── css/
│               │       └── js/
│               ├── src/
│               │   └── inc/
│               │       └── enqueue.php
│               ├── template-parts/
│               ├── functions.php
│               ├── header.php
│               ├── footer.php
│               └── index.php
│
├── docs/                             # Documentation
├── tests/                            # Test files
├── node_modules/                     # Node dependencies
├── vendor/                           # Composer dependencies
├── .git/
├── .gitignore
├── composer.json
├── package.json
├── vite.config.js
└── README.md
```

### Separation of Concerns

**Agency Core Plugin (Business Logic):**
- Custom Post Types
- ACF Field Groups
- Shortcodes
- AJAX Handlers
- Custom Taxonomies
- Business logic

**Custom Theme (Presentation):**
- Templates
- Styles (SCSS)
- JavaScript
- Asset compilation
- Layout structure

**Why This Structure?**
- Theme can be changed without losing functionality
- Plugin contains all business logic
- Easy to update and maintain
- Portable across projects

---

## 🔄 Development Workflow

### Daily Workflow
```bash
# 1. Start development server
npm run dev

# 2. Make changes to SCSS/JS
# Files auto-reload in browser

# 3. Work on PHP
# Changes take effect immediately

# 4. Test locally
# Browse site and test features

# 5. Commit changes
git add .
git commit -m "feat: add new feature"
git push
```

### Build Commands
```bash
# Development (with hot-reload)
npm run dev

# Production build (optimized)
npm run build

# Watch mode (rebuild on changes)
npm run watch

# Lint JavaScript
npm run lint

# Lint SCSS
npm run lint:scss

# Format code
npm run format
```

### Asset Compilation

**Vite Configuration:**
```javascript
// vite.config.js
import { defineConfig } from 'vite';
import path from 'path';

export default defineConfig({
    build: {
        outDir: 'cms/wp-content/themes/custom-theme/assets/dist',
        rollupOptions: {
            input: {
                main: path.resolve(__dirname, 'cms/wp-content/themes/custom-theme/assets/src/js/main.js'),
                style: path.resolve(__dirname, 'cms/wp-content/themes/custom-theme/assets/src/scss/style.scss'),
            },
            output: {
                entryFileNames: 'js/[name].js',
                assetFileNames: 'css/[name].[ext]'
            }
        }
    },
    server: {
        port: 3000,
        hmr: {
            host: 'localhost',
        }
    }
});
```

---

## 🏗️ Creating Custom Post Types

### Step 1: Register Post Type

**File:** `cms/wp-content/mu-plugins/agency-core/inc/custom-post-types.php`
```php
<?php
/**
 * Register Events Custom Post Type
 */
function agency_core_register_events_cpt() {
    $labels = array(
        'name'               => _x('Events', 'post type general name', 'agency-core'),
        'singular_name'      => _x('Event', 'post type singular name', 'agency-core'),
        'menu_name'          => _x('Events', 'admin menu', 'agency-core'),
        'add_new'            => _x('Add New', 'event', 'agency-core'),
        'add_new_item'       => __('Add New Event', 'agency-core'),
        'edit_item'          => __('Edit Event', 'agency-core'),
        'new_item'           => __('New Event', 'agency-core'),
        'view_item'          => __('View Event', 'agency-core'),
        'search_items'       => __('Search Events', 'agency-core'),
        'not_found'          => __('No events found', 'agency-core'),
        'not_found_in_trash' => __('No events found in Trash', 'agency-core'),
    );

    $args = array(
        'labels'              => $labels,
        'public'              => true,
        'publicly_queryable'  => true,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'menu_icon'           => 'dashicons-calendar-alt',
        'menu_position'       => 20,
        'query_var'           => true,
        'rewrite'             => array('slug' => 'events'),
        'capability_type'     => 'post',
        'has_archive'         => true,
        'hierarchical'        => false,
        'supports'            => array('title', 'editor', 'thumbnail', 'excerpt'),
        'show_in_rest'        => true, // Enable Gutenberg
    );

    register_post_type('event', $args);
}
add_action('init', 'agency_core_register_events_cpt');
```

### Step 2: Register Taxonomies
```php
/**
 * Register Event Category Taxonomy
 */
function agency_core_register_event_category_taxonomy() {
    $labels = array(
        'name'              => _x('Event Categories', 'taxonomy general name', 'agency-core'),
        'singular_name'     => _x('Event Category', 'taxonomy singular name', 'agency-core'),
        'search_items'      => __('Search Categories', 'agency-core'),
        'all_items'         => __('All Categories', 'agency-core'),
        'edit_item'         => __('Edit Category', 'agency-core'),
        'update_item'       => __('Update Category', 'agency-core'),
        'add_new_item'      => __('Add New Category', 'agency-core'),
        'new_item_name'     => __('New Category Name', 'agency-core'),
        'menu_name'         => __('Categories', 'agency-core'),
    );

    $args = array(
        'hierarchical'      => true, // Like categories
        'labels'            => $labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array('slug' => 'event-category'),
        'show_in_rest'      => true,
    );

    register_taxonomy('event_category', array('event'), $args);
}
add_action('init', 'agency_core_register_event_category_taxonomy');
```

### Step 3: Create ACF Fields

**File:** `cms/wp-content/mu-plugins/agency-core/inc/acf-fields.php`
```php
<?php
/**
 * Register Event ACF Fields
 */
function agency_core_register_event_fields() {
    if (function_exists('acf_add_local_field_group')) {
        acf_add_local_field_group(array(
            'key' => 'group_event',
            'title' => 'Event Details',
            'fields' => array(
                array(
                    'key' => 'field_event_date',
                    'label' => 'Event Date',
                    'name' => 'event_date',
                    'type' => 'date_picker',
                    'required' => 1,
                    'display_format' => 'd/m/Y',
                    'return_format' => 'Y-m-d',
                ),
                array(
                    'key' => 'field_event_time',
                    'label' => 'Event Time',
                    'name' => 'event_time',
                    'type' => 'time_picker',
                    'display_format' => 'H:i',
                    'return_format' => 'H:i:s',
                ),
                array(
                    'key' => 'field_event_location',
                    'label' => 'Location',
                    'name' => 'event_location',
                    'type' => 'text',
                ),
                array(
                    'key' => 'field_event_capacity',
                    'label' => 'Capacity',
                    'name' => 'event_capacity',
                    'type' => 'number',
                    'min' => 0,
                ),
                array(
                    'key' => 'field_event_price',
                    'label' => 'Ticket Price',
                    'name' => 'event_price',
                    'type' => 'number',
                    'min' => 0,
                    'step' => 0.01,
                    'prepend' => '€',
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'event',
                    ),
                ),
            ),
            'menu_order' => 0,
            'position' => 'normal',
            'style' => 'default',
        ));
    }
}
add_action('acf/init', 'agency_core_register_event_fields');
```

### Step 4: Flush Rewrite Rules
```bash
cd cms
wp rewrite flush
cd ..
```

---

## 🎨 Creating Shortcodes

### Basic Shortcode

**File:** `cms/wp-content/mu-plugins/agency-core/inc/shortcodes.php`
```php
<?php
/**
 * Event List Shortcode
 * 
 * Usage: [event_list limit="6" category="workshop"]
 */
function event_list_shortcode($atts) {
    // Parse attributes
    $atts = shortcode_atts(array(
        'limit' => '6',
        'category' => '',
        'orderby' => 'event_date',
        'order' => 'ASC',
    ), $atts);
    
    // Build query args
    $args = array(
        'post_type' => 'event',
        'posts_per_page' => intval($atts['limit']),
        'orderby' => 'meta_value',
        'meta_key' => 'event_date',
        'order' => $atts['order'],
    );
    
    // Add category filter
    if (!empty($atts['category'])) {
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'event_category',
                'field' => 'slug',
                'terms' => sanitize_text_field($atts['category']),
            ),
        );
    }
    
    // Execute query
    $query = new WP_Query($args);
    
    // Start output buffering
    ob_start();
    
    if ($query->have_posts()) {
        echo '<div class="event-list">';
        
        while ($query->have_posts()) {
            $query->the_post();
            
            // Get ACF fields
            $event_date = get_field('event_date');
            $event_time = get_field('event_time');
            $event_location = get_field('event_location');
            $event_price = get_field('event_price');
            
            ?>
            <article class="event-card">
                <?php if (has_post_thumbnail()) : ?>
                    <div class="event-card__image">
                        <?php the_post_thumbnail('medium'); ?>
                    </div>
                <?php endif; ?>
                
                <div class="event-card__content">
                    <h3 class="event-card__title">
                        <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                    </h3>
                    
                    <div class="event-card__meta">
                        <?php if ($event_date) : ?>
                            <span class="event-card__date">
                                <i class="dashicons dashicons-calendar"></i>
                                <?php echo date('F j, Y', strtotime($event_date)); ?>
                            </span>
                        <?php endif; ?>
                        
                        <?php if ($event_time) : ?>
                            <span class="event-card__time">
                                <i class="dashicons dashicons-clock"></i>
                                <?php echo date('g:i A', strtotime($event_time)); ?>
                            </span>
                        <?php endif; ?>
                        
                        <?php if ($event_location) : ?>
                            <span class="event-card__location">
                                <i class="dashicons dashicons-location"></i>
                                <?php echo esc_html($event_location); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="event-card__excerpt">
                        <?php echo wp_trim_words(get_the_excerpt(), 20); ?>
                    </div>
                    
                    <?php if ($event_price !== null) : ?>
                        <div class="event-card__price">
                            <?php if ($event_price == 0) : ?>
                                Free
                            <?php else : ?>
                                €<?php echo number_format($event_price, 2); ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <a href="<?php the_permalink(); ?>" class="event-card__button">
                        View Details →
                    </a>
                </div>
            </article>
            <?php
        }
        
        echo '</div>';
    } else {
        echo '<p>No events found.</p>';
    }
    
    wp_reset_postdata();
    
    // Return output
    return ob_get_clean();
}
add_shortcode('event_list', 'event_list_shortcode');
```

### Nested Shortcode (Container + Items)
```php
<?php
/**
 * Feature List Container
 * Usage: [feature_list][feature icon="check"]Text[/feature][/feature_list]
 */
function feature_list_shortcode($atts, $content = null) {
    $atts = shortcode_atts(array(
        'columns' => '3',
        'style' => 'default',
    ), $atts);
    
    $output = '<div class="feature-list feature-list--columns-' . esc_attr($atts['columns']) . ' feature-list--' . esc_attr($atts['style']) . '">';
    $output .= do_shortcode($content);
    $output .= '</div>';
    
    return $output;
}
add_shortcode('feature_list', 'feature_list_shortcode');

/**
 * Feature Item
 */
function feature_item_shortcode($atts, $content = null) {
    $atts = shortcode_atts(array(
        'icon' => 'check',
        'title' => '',
    ), $atts);
    
    $output = '<div class="feature-item">';
    
    if (!empty($atts['icon'])) {
        $output .= '<div class="feature-item__icon">';
        $output .= '<i class="dashicons dashicons-' . esc_attr($atts['icon']) . '"></i>';
        $output .= '</div>';
    }
    
    $output .= '<div class="feature-item__content">';
    
    if (!empty($atts['title'])) {
        $output .= '<h4 class="feature-item__title">' . esc_html($atts['title']) . '</h4>';
    }
    
    $output .= '<p class="feature-item__text">' . do_shortcode($content) . '</p>';
    $output .= '</div>';
    $output .= '</div>';
    
    return $output;
}
add_shortcode('feature', 'feature_item_shortcode');
```

---

## 🔧 Working with ACF

### Programmatic Field Creation
```php
<?php
/**
 * Create ACF field group programmatically
 */
function create_acf_field_group() {
    if (function_exists('acf_add_local_field_group')) {
        acf_add_local_field_group(array(
            'key' => 'group_example',
            'title' => 'Example Fields',
            'fields' => array(
                // Text field
                array(
                    'key' => 'field_text',
                    'label' => 'Text Field',
                    'name' => 'text_field',
                    'type' => 'text',
                    'required' => 1,
                    'placeholder' => 'Enter text...',
                ),
                
                // Textarea
                array(
                    'key' => 'field_textarea',
                    'label' => 'Textarea',
                    'name' => 'textarea_field',
                    'type' => 'textarea',
                    'rows' => 4,
                ),
                
                // WYSIWYG Editor
                array(
                    'key' => 'field_wysiwyg',
                    'label' => 'Content',
                    'name' => 'content_field',
                    'type' => 'wysiwyg',
                    'tabs' => 'visual',
                    'toolbar' => 'basic',
                    'media_upload' => 1,
                ),
                
                // Number
                array(
                    'key' => 'field_number',
                    'label' => 'Number',
                    'name' => 'number_field',
                    'type' => 'number',
                    'min' => 0,
                    'max' => 100,
                    'step' => 1,
                ),
                
                // True/False
                array(
                    'key' => 'field_boolean',
                    'label' => 'Enable Feature',
                    'name' => 'boolean_field',
                    'type' => 'true_false',
                    'ui' => 1,
                    'default_value' => 0,
                ),
                
                // Select
                array(
                    'key' => 'field_select',
                    'label' => 'Select Option',
                    'name' => 'select_field',
                    'type' => 'select',
                    'choices' => array(
                        'option1' => 'Option 1',
                        'option2' => 'Option 2',
                        'option3' => 'Option 3',
                    ),
                    'default_value' => 'option1',
                    'allow_null' => 0,
                    'multiple' => 0,
                ),
                
                // Radio
                array(
                    'key' => 'field_radio',
                    'label' => 'Radio Buttons',
                    'name' => 'radio_field',
                    'type' => 'radio',
                    'choices' => array(
                        'choice1' => 'Choice 1',
                        'choice2' => 'Choice 2',
                    ),
                    'layout' => 'horizontal',
                ),
                
                // Checkbox
                array(
                    'key' => 'field_checkbox',
                    'label' => 'Checkboxes',
                    'name' => 'checkbox_field',
                    'type' => 'checkbox',
                    'choices' => array(
                        'check1' => 'Check 1',
                        'check2' => 'Check 2',
                    ),
                ),
                
                // Image
                array(
                    'key' => 'field_image',
                    'label' => 'Image',
                    'name' => 'image_field',
                    'type' => 'image',
                    'return_format' => 'url', // 'array', 'url', 'id'
                    'preview_size' => 'medium',
                    'library' => 'all',
                ),
                
                // File
                array(
                    'key' => 'field_file',
                    'label' => 'File Upload',
                    'name' => 'file_field',
                    'type' => 'file',
                    'return_format' => 'array',
                    'mime_types' => 'pdf,doc,docx',
                ),
                
                // Gallery
                array(
                    'key' => 'field_gallery',
                    'label' => 'Image Gallery',
                    'name' => 'gallery_field',
                    'type' => 'gallery',
                    'min' => 1,
                    'max' => 10,
                    'preview_size' => 'thumbnail',
                ),
                
                // Date Picker
                array(
                    'key' => 'field_date',
                    'label' => 'Date',
                    'name' => 'date_field',
                    'type' => 'date_picker',
                    'display_format' => 'd/m/Y',
                    'return_format' => 'Y-m-d',
                ),
                
                // Date Time Picker
                array(
                    'key' => 'field_datetime',
                    'label' => 'Date & Time',
                    'name' => 'datetime_field',
                    'type' => 'date_time_picker',
                    'display_format' => 'd/m/Y g:i a',
                    'return_format' => 'Y-m-d H:i:s',
                ),
                
                // Color Picker
                array(
                    'key' => 'field_color',
                    'label' => 'Color',
                    'name' => 'color_field',
                    'type' => 'color_picker',
                    'default_value' => '#000000',
                ),
                
                // URL
                array(
                    'key' => 'field_url',
                    'label' => 'Website URL',
                    'name' => 'url_field',
                    'type' => 'url',
                    'placeholder' => 'https://example.com',
                ),
                
                // Email
                array(
                    'key' => 'field_email',
                    'label' => 'Email Address',
                    'name' => 'email_field',
                    'type' => 'email',
                    'placeholder' => 'email@example.com',
                ),
                
                // Repeater (ACF Pro)
                array(
                    'key' => 'field_repeater',
                    'label' => 'Repeater',
                    'name' => 'repeater_field',
                    'type' => 'repeater',
                    'min' => 1,
                    'max' => 10,
                    'layout' => 'table',
                    'button_label' => 'Add Item',
                    'sub_fields' => array(
                        array(
                            'key' => 'field_repeater_text',
                            'label' => 'Text',
                            'name' => 'text',
                            'type' => 'text',
                        ),
                        array(
                            'key' => 'field_repeater_image',
                            'label' => 'Image',
                            'name' => 'image',
                            'type' => 'image',
                            'return_format' => 'url',
                        ),
                    ),
                ),
                
                // Flexible Content (ACF Pro)
                array(
                    'key' => 'field_flexible',
                    'label' => 'Content Blocks',
                    'name' => 'flexible_field',
                    'type' => 'flexible_content',
                    'button_label' => 'Add Block',
                    'layouts' => array(
                        // Text Block
                        array(
                            'key' => 'layout_text',
                            'name' => 'text_block',
                            'label' => 'Text Block',
                            'display' => 'block',
                            'sub_fields' => array(
                                array(
                                    'key' => 'field_text_content',
                                    'label' => 'Content',
                                    'name' => 'content',
                                    'type' => 'wysiwyg',
                                ),
                            ),
                        ),
                        // Image Block
                        array(
                            'key' => 'layout_image',
                            'name' => 'image_block',
                            'label' => 'Image Block',
                            'display' => 'block',
                            'sub_fields' => array(
                                array(
                                    'key' => 'field_image_image',
                                    'label' => 'Image',
                                    'name' => 'image',
                                    'type' => 'image',
                                    'return_format' => 'array',
                                ),
                            ),
                        ),
                    ),
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'page',
                    ),
                ),
            ),
            'menu_order' => 0,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
        ));
    }
}
add_action('acf/init', 'create_acf_field_group');
```

### Retrieving ACF Fields
```php
<?php
// Get single field
$value = get_field('field_name');

// Get field with post ID
$value = get_field('field_name', $post_id);

// Get field with default fallback
$value = get_field('field_name') ?: 'Default Value';

// Check if field exists
if (get_field('field_name')) {
    // Field has value
}

// Get image field (array return format)
$image = get_field('image_field');
if ($image) {
    echo '<img src="' . esc_url($image['url']) . '" alt="' . esc_attr($image['alt']) . '">';
}

// Get repeater field
if (have_rows('repeater_field')) {
    while (have_rows('repeater_field')) {
        the_row();
        $text = get_sub_field('text');
        $image = get_sub_field('image');
        
        echo '<div>';
        echo '<p>' . esc_html($text) . '</p>';
        echo '<img src="' . esc_url($image) . '">';
        echo '</div>';
    }
}

// Get flexible content
if (have_rows('flexible_field')) {
    while (have_rows('flexible_field')) {
        the_row();
        
        if (get_row_layout() == 'text_block') {
            $content = get_sub_field('content');
            echo '<div class="text-block">' . $content . '</div>';
        }
        elseif (get_row_layout() == 'image_block') {
            $image = get_sub_field('image');
            echo '<div class="image-block">';
            echo '<img src="' . esc_url($image['url']) . '">';
            echo '</div>';
        }
    }
}
```

---

## ⚙️ JavaScript Development

### Module Structure

**File:** `cms/wp-content/themes/custom-theme/assets/src/js/components/example.js`
```javascript
/**
 * Example Component
 * 
 * Description of what this component does.
 * 
 * @package Custom_Theme
 */

class ExampleComponent {
    constructor(element) {
        this.element = element;
        this.options = {
            autoplay: element.dataset.autoplay === 'true',
            delay: parseInt(element.dataset.delay) || 5000,
        };
        
        this.init();
    }
    
    init() {
        console.log('Example component initialized');
        this.bindEvents();
    }
    
    bindEvents() {
        this.element.addEventListener('click', (e) => this.handleClick(e));
    }
    
    handleClick(e) {
        console.log('Clicked:', e.target);
    }
    
    destroy() {
        // Cleanup
        this.element.removeEventListener('click', this.handleClick);
    }
}

// Initialize all instances
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.example-component').forEach(element => {
        new ExampleComponent(element);
    });
});

// Export for use in other modules
export default ExampleComponent;
```

### AJAX Requests
```javascript
/**
 * Make AJAX request to WordPress
 */
async function makeAjaxRequest(action, data = {}) {
    try {
        const response = await fetch(window.customTheme.ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: action,
                nonce: window.customTheme.nonce,
                ...data,
            }),
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.data?.message || 'Request failed');
        }
        
        return result.data;
    } catch (error) {
        console.error('AJAX Error:', error);
        throw error;
    }
}

// Usage
async function loadPosts() {
    try {
        const data = await makeAjaxRequest('load_posts', {
            post_type: 'post',
            posts_per_page: 10,
        });
        
        console.log('Posts loaded:', data);
    } catch (error) {
        console.error('Failed to load posts:', error);
    }
}
```

### Utility Functions
```javascript
/**
 * Debounce function
 */
export function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/**
 * Throttle function
 */
export function throttle(func, limit) {
    let inThrottle;
    return function executedFunction(...args) {
        if (!inThrottle) {
            func.apply(this, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
}

/**
 * Check if element is in viewport
 */
export function isInViewport(element) {
    const rect = element.getBoundingClientRect();
    return (
        rect.top >= 0 &&
        rect.left >= 0 &&
        rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
        rect.right <= (window.innerWidth || document.documentElement.clientWidth)
    );
}

/**
 * Format number with thousand separators
 */
export function formatNumber(number) {
    return new Intl.NumberFormat('de-DE').format(number);
}

/**
 * Format currency
 */
export function formatCurrency(amount, currency = 'EUR') {
    return new Intl.NumberFormat('de-DE', {
        style: 'currency',
        currency: currency,
    }).format(amount);
}
```

---

## 🎨 SCSS Development

### File Organization
```scss
// style.scss (main entry point)
@import 'base/variables';
@import 'base/reset';
@import 'base/typography';
@import 'base/utilities';

@import 'layout/header';
@import 'layout/footer';
@import 'layout/sidebar';
@import 'layout/grid';

@import 'components/button';
@import 'components/card';
@import 'components/form';
@import 'components/modal';
@import 'components/ajax-filters';
// ... more components

@import 'templates/home';
@import 'templates/archive';
@import 'templates/single';
```

### Variables

**File:** `base/_variables.scss`
```scss
// Colors
$color-primary: #667eea;
$color-secondary: #764ba2;
$color-success: #10b981;
$color-warning: #f59e0b;
$color-error: #ef4444;
$color-info: #3b82f6;

$color-text: #1f2937;
$color-text-muted: #6b7280;
$color-border: #e5e7eb;
$color-background: #f9fafb;
$color-white: #ffffff;

// Gray scale
$color-gray-50: #f9fafb;
$color-gray-100: #f3f4f6;
$color-gray-200: #e5e7eb;
$color-gray-300: #d1d5db;
$color-gray-400: #9ca3af;
$color-gray-500: #6b7280;
$color-gray-600: #4b5563;
$color-gray-700: #374151;
$color-gray-800: #1f2937;
$color-gray-900: #111827;

// Typography
$font-family-base: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
$font-family-heading: 'Inter', $font-family-base;
$font-family-mono: 'Fira Code', Consolas, Monaco, monospace;

$font-size-xs: 0.75rem;    // 12px
$font-size-sm: 0.875rem;   // 14px
$font-size-base: 1rem;     // 16px
$font-size-lg: 1.125rem;   // 18px
$font-size-xl: 1.25rem;    // 20px
$font-size-2xl: 1.5rem;    // 24px
$font-size-3xl: 1.875rem;  // 30px
$font-size-4xl: 2.25rem;   // 36px
$font-size-5xl: 3rem;      // 48px

$font-weight-normal: 400;
$font-weight-medium: 500;
$font-weight-semibold: 600;
$font-weight-bold: 700;

$line-height-tight: 1.25;
$line-height-normal: 1.5;
$line-height-relaxed: 1.75;

// Spacing
$spacing-xs: 0.25rem;   // 4px
$spacing-sm: 0.5rem;    // 8px
$spacing-md: 1rem;      // 16px
$spacing-lg: 1.5rem;    // 24px
$spacing-xl: 2rem;      // 32px
$spacing-2xl: 3rem;     // 48px
$spacing-3xl: 4rem;     // 64px

// Border radius
$radius-sm: 0.25rem;    // 4px
$radius-base: 0.5rem;   // 8px
$radius-lg: 0.75rem;    // 12px
$radius-xl: 1rem;       // 16px
$radius-full: 9999px;   // Pill shape

// Shadows
$shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
$shadow-base: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
$shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
$shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
$shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);

// Transitions
$transition-fast: 150ms ease-in-out;
$transition-base: 300ms ease-in-out;
$transition-slow: 500ms ease-in-out;

// Breakpoints
$breakpoint-sm: 640px;
$breakpoint-md: 768px;
$breakpoint-lg: 1024px;
$breakpoint-xl: 1280px;
$breakpoint-2xl: 1536px;

// Z-index
$z-index-dropdown: 1000;
$z-index-sticky: 1020;
$z-index-fixed: 1030;
$z-index-modal-backdrop: 1040;
$z-index-modal: 1050;
$z-index-popover: 1060;
$z-index-tooltip: 1070;
```

### Mixins

**File:** `base/_mixins.scss`
```scss
// Responsive breakpoints
@mixin breakpoint($point) {
    @if $point == sm {
        @media (min-width: $breakpoint-sm) { @content; }
    }
    @else if $point == md {
        @media (min-width: $breakpoint-md) { @content; }
    }
    @else if $point == lg {
        @media (min-width: $breakpoint-lg) { @content; }
    }
    @else if $point == xl {
        @media (min-width: $breakpoint-xl) { @content; }
    }
    @else if $point == 2xl {
        @media (min-width: $breakpoint-2xl) { @content; }
    }
}

// Flexbox utilities
@mixin flex-center {
    display: flex;
    align-items: center;
    justify-content: center;
}

@mixin flex-between {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

// Truncate text
@mixin truncate {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

// Multi-line truncate
@mixin line-clamp($lines) {
    display: -webkit-box;
    -webkit-line-clamp: $lines;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

// Clearfix
@mixin clearfix {
    &::after {
        content: '';
        display: table;
        clear: both;
    }
}

// Aspect ratio
@mixin aspect-ratio($width, $height) {
    position: relative;
    
    &::before {
        content: '';
        display: block;
        padding-top: ($height / $width) * 100%;
    }
    
    > * {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
    }
}

// Button reset
@mixin button-reset {
    background: none;
    border: none;
    padding: 0;
    margin: 0;
    font: inherit;
    color: inherit;
    cursor: pointer;
    
    &:focus {
        outline: none;
    }
}

// Visually hidden (accessible)
@mixin visually-hidden {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}

// Container
@mixin container {
    width: 100%;
    max-width: 1280px;
    margin: 0 auto;
    padding: 0 $spacing-lg;
    
    @include breakpoint(lg) {
        padding: 0 $spacing-2xl;
    }
}
```

### Component Example
```scss
// components/_button.scss
.button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: $spacing-sm;
    
    padding: $spacing-md $spacing-lg;
    
    font-family: $font-family-base;
    font-size: $font-size-base;
    font-weight: $font-weight-semibold;
    line-height: 1;
    text-decoration: none;
    
    background: $color-primary;
    color: $color-white;
    border: 2px solid transparent;
    border-radius: $radius-base;
    
    cursor: pointer;
    transition: all $transition-base;
    
    // Hover state
    &:hover {
        background: darken($color-primary, 10%);
        transform: translateY(-2px);
        box-shadow: $shadow-md;
    }
    
    // Focus state
    &:focus {
        outline: none;
        box-shadow: 0 0 0 3px rgba($color-primary, 0.3);
    }
    
    // Active state
    &:active {
        transform: translateY(0);
    }
    
    // Disabled state
    &:disabled,
    &.is-disabled {
        opacity: 0.5;
        cursor: not-allowed;
        pointer-events: none;
    }
    
    // Variants
    &--secondary {
        background: $color-secondary;
        
        &:hover {
            background: darken($color-secondary, 10%);
        }
    }
    
    &--outline {
        background: transparent;
        color: $color-primary;
        border-color: $color-primary;
        
        &:hover {
            background: $color-primary;
            color: $color-white;
        }
    }
    
    &--ghost {
        background: transparent;
        color: $color-primary;
        
        &:hover {
            background: rgba($color-primary, 0.1);
        }
    }
    
    // Sizes
    &--small {
        padding: $spacing-sm $spacing-md;
        font-size: $font-size-sm;
    }
    
    &--large {
        padding: $spacing-lg $spacing-xl;
        font-size: $font-size-lg;
    }
    
    // Full width
    &--block {
        display: flex;
        width: 100%;
    }
    
    // Icon only
    &--icon {
        padding: $spacing-md;
        
        svg {
            width: 20px;
            height: 20px;
        }
    }
    
    // Loading state
    &--loading {
        position: relative;
        color: transparent;
        pointer-events: none;
        
        &::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 16px;
            height: 16px;
            margin: -8px 0 0 -8px;
            border: 2px solid $color-white;
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }
    }
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

---

## 🎯 PHP Best Practices

### Naming Conventions
```php
// Functions: prefix with theme/plugin slug
function agency_core_custom_function() {}

// Classes: PascalCase with prefix
class Agency_Core_Custom_Class {}

// Constants: UPPERCASE
define('AGENCY_CORE_VERSION', '1.0.0');

// Variables: snake_case
$post_count = 10;
```

### Security
```php
// Always escape output
echo esc_html($text);
echo esc_attr($attribute);
echo esc_url($url);
echo wp_kses_post($html); // Allow safe HTML

// Always sanitize input
$clean = sanitize_text_field($_POST['field']);
$email = sanitize_email($_POST['email']);
$int = absint($_POST['number']);

// Always verify nonces
if (!wp_verify_nonce($_POST['nonce'], 'action_name')) {
    wp_die('Security check failed');
}

// Prepare queries properly
$wpdb->prepare("SELECT * FROM $wpdb->posts WHERE ID = %d", $post_id);
```

---

## 🔗 Hooks & Filters

### Common Actions
```php
// Init - register CPTs, taxonomies
add_action('init', 'your_function');

// After theme setup
add_action('after_setup_theme', 'your_function');

// Enqueue scripts
add_action('wp_enqueue_scripts', 'your_function');

// AJAX for logged in users
add_action('wp_ajax_your_action', 'your_function');

// AJAX for all users
add_action('wp_ajax_nopriv_your_action', 'your_function');

// Save post
add_action('save_post', 'your_function', 10, 2);
```

### Common Filters
```php
// Modify excerpt length
add_filter('excerpt_length', function($length) {
    return 20;
});

// Modify title
add_filter('the_title', function($title) {
    return strtoupper($title);
});

// Modify query
add_filter('pre_get_posts', function($query) {
    if ($query->is_main_query() && !is_admin()) {
        $query->set('posts_per_page', 12);
    }
    return $query;
});
```

---

## 🧪 Testing

### Manual Testing Checklist

- [ ] Test on Chrome, Firefox, Safari, Edge
- [ ] Test on mobile, tablet, desktop
- [ ] Test all shortcodes
- [ ] Test all custom post types
- [ ] Test AJAX features
- [ ] Test forms
- [ ] Check console for errors
- [ ] Check network tab for failed requests
- [ ] Test with plugins disabled
- [ ] Test with default theme

### Browser Console Tests
```javascript
// Test customTheme object
console.log(window.customTheme);

// Test AJAX endpoint
fetch(window.customTheme.ajaxUrl + '?action=test')
    .then(r => r.json())
    .then(d => console.log(d));

// Test components
document.querySelectorAll('.ajax-filters').length;
```

---

## 🐛 Debugging

### Enable WordPress Debug Mode

**wp-config.php:**
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
define('SCRIPT_DEBUG', true);
```

### Debug Functions
```php
// Log to debug.log
error_log('Debug message: ' . print_r($data, true));

// Die and dump
echo '<pre>';
print_r($data);
echo '</pre>';
die();

// WordPress debug functions
var_dump($data);
wp_die($message);
```

### Common Issues

**White Screen:**
- Check debug.log
- Disable plugins one by one
- Switch to default theme

**AJAX 403:**
- Check nonce names match
- Verify AJAX action registered
- Check user permissions

**Assets Not Loading:**
- Run `npm run build`
- Clear cache
- Check file paths

---

## ⚡ Performance

### Optimization Tips
```php
// Cache query results
$posts = wp_cache_get('my_posts', 'my_group');
if (false === $posts) {
    $posts = new WP_Query($args);
    wp_cache_set('my_posts', $posts, 'my_group', 3600);
}

// Lazy load images
add_filter('wp_lazy_loading_enabled', '__return_true');

// Limit post revisions
define('WP_POST_REVISIONS', 3);

// Disable post revisions
define('WP_POST_REVISIONS', false);
```

### Asset Optimization
```bash
# Minify in production
npm run build

# Optimize images before upload
# Use ImageOptim, TinyPNG, etc.

# Enable caching plugin
# WP Rocket, W3 Total Cache, etc.
```

---

## 🔒 Security

### Security Checklist

- [ ] Use nonces for all forms
- [ ] Sanitize all input
- [ ] Escape all output
- [ ] Use prepared statements
- [ ] Check user capabilities
- [ ] Validate file uploads
- [ ] Use HTTPS
- [ ] Keep WordPress updated
- [ ] Use strong passwords
- [ ] Limit login attempts
- [ ] Regular backups

### Code Examples
```php
// Check user capability
if (!current_user_can('edit_posts')) {
    wp_die('Access denied');
}

// Validate file upload
$allowed = array('jpg', 'jpeg', 'png', 'gif');
$ext = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
if (!in_array($ext, $allowed)) {
    wp_die('Invalid file type');
}
```

---

## 🔀 Git Workflow

### Branch Strategy
```bash
main        # Production
develop     # Development
feature/*   # New features
bugfix/*    # Bug fixes
hotfix/*    # Urgent fixes
```

### Common Commands
```bash
# Create feature branch
git checkout -b feature/new-feature develop

# Commit changes
git add .
git commit -m "feat: add new feature"

# Push branch
git push origin feature/new-feature

# Merge to develop
git checkout develop
git merge feature/new-feature

# Delete branch
git branch -d feature/new-feature
```

### Commit Message Format
```
feat: add new feature
fix: fix bug
docs: update documentation
style: format code
refactor: refactor code
test: add tests
chore: update dependencies
```

---

## 📚 Additional Resources

### WordPress

- [WordPress Developer Handbook](https://developer.wordpress.org/)
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- [WP-CLI Documentation](https://wp-cli.org/)
- [WordPress Stack Exchange](https://wordpress.stackexchange.com/)

### JavaScript

- [MDN Web Docs](https://developer.mozilla.org/)
- [JavaScript.info](https://javascript.info/)
- [ES6 Features](http://es6-features.org/)

### SCSS

- [Sass Documentation](https://sass-lang.com/documentation)
- [BEM Methodology](http://getbem.com/)

### Tools

- [Vite Documentation](https://vitejs.dev/)
- [ACF Documentation](https://www.advancedcustomfields.com/resources/)
- [WooCommerce Docs](https://woocommerce.com/documentation/)

---

## 🎓 Learning Path

### For Beginners

1. Learn HTML, CSS, JavaScript basics
2. Understand WordPress fundamentals
3. Study PHP basics
4. Learn WordPress template hierarchy
5. Practice with simple plugins/themes
6. Study ACF and custom fields
7. Learn AJAX in WordPress

### For Intermediate

1. Master WordPress hooks system
2. Learn OOP in PHP
3. Study advanced JavaScript (ES6+)
4. Learn build tools (Vite, Webpack)
5. Study SCSS and methodologies (BEM)
6. Practice with REST API
7. Learn testing basics

### For Advanced

1. Study WordPress core architecture
2. Master performance optimization
3. Learn security best practices
4. Study advanced database queries
5. Learn CI/CD pipelines
6. Master Git workflows
7. Contribute to open source

---

## 🤝 Contributing

### Code Style

- Follow WordPress Coding Standards
- Use PSR-12 for PHP
- Use ESLint for JavaScript
- Use BEM for CSS

### Pull Request Process

1. Fork repository
2. Create feature branch
3. Make changes
4. Test thoroughly
5. Commit with clear messages
6. Push to fork
7. Create pull request
8. Address review comments

### Code Review Checklist

- [ ] Code follows style guide
- [ ] No console.log() in production
- [ ] All functions documented
- [ ] Security best practices followed
- [ ] Tested on multiple browsers
- [ ] No breaking changes
- [ ] Documentation updated

---

## 📞 Getting Help

### Internal Resources

- Check this documentation
- Review [TROUBLESHOOTING.md](./TROUBLESHOOTING.md)
- Ask team members
- Check internal wiki

### External Resources

- WordPress Support Forums
- Stack Overflow
- GitHub Issues
- Plugin documentation

### Emergency Contacts

- Lead Developer: dev@your-agency.com
- DevOps: devops@your-agency.com
- Support: support@your-agency.com

---

## 🎉 You're Ready to Develop!

You now have the knowledge to:
- Set up development environment
- Follow code standards
- Create custom post types
- Build shortcodes
- Work with ACF
- Develop JavaScript components
- Write maintainable SCSS
- Debug effectively
- Optimize performance
- Ensure security
- Use Git properly

**Happy coding!** 💻🚀

---

**Last Updated:** February 2026  
**Version:** 1.0.0
