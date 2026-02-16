# Development Guide

**Version:** 1.2.0  
**Last Updated:** February 16, 2026

Comprehensive development guide for the Media Lab Starter Kit.

---

## Table of Contents

1. [Development Setup](#development-setup)
2. [Build System](#build-system)
3. [Development Workflow](#development-workflow)
4. [Plugin Development](#plugin-development)
5. [Theme Development](#theme-development)
6. [JavaScript Development](#javascript-development)
7. [SCSS Development](#scss-development)
8. [Git Workflow](#git-workflow)
9. [Testing](#testing)
10. [Best Practices](#best-practices)

---

## Development Setup

### Prerequisites
```bash
# Check versions
php -v        # 8.0+
node -v       # 16+
npm -v        # 8+
composer -v   # 2.0+
git --version # 2.0+
```

### Local Environment

**Laravel Valet (macOS):**
```bash
cd ~/Valet-Umgebung/media-lab-starter-kit
valet link
# Access: http://media-lab-starter-kit.test
```

**Environment Variables:**
```bash
# In .env (create if needed)
WP_ENV=development
WP_DEBUG=true
WP_DEBUG_LOG=true
WP_DEBUG_DISPLAY=false
```

### IDE Setup

**VS Code (Recommended):**
```json
// .vscode/settings.json
{
  "editor.tabSize": 4,
  "editor.insertSpaces": true,
  "files.associations": {
    "*.php": "php"
  },
  "intelephense.environment.phpVersion": "8.0.0"
}
```

**Extensions:**
- PHP Intelephense
- ESLint
- Prettier
- Volar (for Vue if needed)

---

## Build System

### Vite Configuration

Build system uses **Vite 4** for fast development and optimized production builds.

**Config File:** `vite.config.js`
```javascript
import { defineConfig } from 'vite';
import path from 'path';

export default defineConfig({
  build: {
    manifest: true,
    rollupOptions: {
      input: {
        main: path.resolve(__dirname, 'cms/wp-content/themes/custom-theme/assets/js/main.js')
      }
    },
    outDir: path.resolve(__dirname, 'cms/wp-content/themes/custom-theme/dist')
  },
  server: {
    origin: 'http://media-lab-starter-kit.test'
  }
});
```

### Build Commands

**Development Mode:**
```bash
npm run dev
```
- Hot Module Replacement (HMR)
- Source maps enabled
- Fast rebuilds
- Watch mode active

**Production Build:**
```bash
npm run build
```
- Minified assets
- Optimized bundles
- Tree shaking
- Hash-based filenames

**Preview Production:**
```bash
npm run preview
```

### Asset Structure
```
cms/wp-content/themes/custom-theme/
├── assets/
│   ├── scss/
│   │   ├── main.scss          (Entry point)
│   │   ├── _variables.scss    (Design tokens)
│   │   ├── _mixins.scss       (Reusable mixins)
│   │   ├── base/              (Reset, typography)
│   │   ├── components/        (Buttons, cards, etc)
│   │   ├── layout/            (Grid, header, footer)
│   │   └── utilities/         (Helpers)
│   │
│   └── js/
│       ├── main.js            (Entry point)
│       ├── modules/           (Feature modules)
│       │   ├── accordion.js
│       │   ├── modal.js
│       │   ├── slider.js
│       │   └── ... (24 modules)
│       └── utils/             (Utilities)
│
└── dist/                      (Compiled output)
    ├── main-[hash].css
    ├── main-[hash].js
    └── manifest.json
```

---

## Development Workflow

### Daily Workflow

**1. Start Development Server:**
```bash
cd /path/to/media-lab-starter-kit
npm run dev
```

**2. Make Changes:**
- Edit SCSS files in `assets/scss/`
- Edit JS files in `assets/js/`
- Edit PHP files in theme/plugins

**3. Test Changes:**
- HMR updates automatically
- Check browser console
- Test responsive design

**4. Commit Changes:**
```bash
git add .
git commit -m "Feature: Description"
git push
```

### Feature Development

**1. Create Feature Branch:**
```bash
git checkout -b feature/new-feature
```

**2. Develop Feature:**
- Write code
- Test thoroughly
- Update documentation

**3. Run Tests:**
```bash
./tests/run-tests.sh
```

**4. Merge to Main:**
```bash
git checkout main
git merge feature/new-feature
git push
```

---

## Plugin Development

### Creating New Plugin

**1. Plugin Structure:**
```bash
mkdir -p cms/wp-content/plugins/my-plugin/inc

cd cms/wp-content/plugins/my-plugin
```

**2. Main Plugin File:**
```php
<?php
/**
 * Plugin Name: My Plugin
 * Version: 1.0.0
 * Author: Your Name
 * Requires PHP: 8.0
 */

if (!defined('ABSPATH')) exit;

define('MY_PLUGIN_VERSION', '1.0.0');
define('MY_PLUGIN_PATH', plugin_dir_path(__FILE__));

// Load components
require_once MY_PLUGIN_PATH . 'inc/functions.php';

// Initialization
function my_plugin_init() {
    // Plugin initialization
}
add_action('plugins_loaded', 'my_plugin_init');
```

**3. Activation Hook:**
```php
register_activation_hook(__FILE__, function() {
    // Set default options
    add_option('my_plugin_version', MY_PLUGIN_VERSION);
    
    // Flush rewrite rules
    flush_rewrite_rules();
});
```

### Modifying Existing Plugins

**Core Plugin (Don't Modify):**
- Use hooks and filters instead
- Create separate plugin for modifications

**Project Plugin (Modify for Clients):**
- Duplicate first
- Rename and customize
- Update plugin header

**Example - Add Custom CPT:**
```php
// In inc/custom-post-types.php
register_post_type('custom_type', [
    'label' => 'Custom Type',
    'public' => true,
    'supports' => ['title', 'editor', 'thumbnail'],
    'has_archive' => true,
    'rewrite' => ['slug' => 'custom-type']
]);
```

---

## Theme Development

### Theme Structure
```
custom-theme/
├── functions.php           (118 lines - keep minimal)
├── style.css              (Theme header)
├── index.php              (Main template)
├── header.php             (Site header)
├── footer.php             (Site footer)
├── singular.php           (Single posts/pages)
├── archive.php            (Archives)
│
├── template-parts/        (Reusable components)
│   ├── content.php
│   ├── content-post.php
│   └── ...
│
├── inc/                   (Theme functions)
│   ├── enqueue.php        (Asset loading)
│   ├── helpers.php        (Helper functions)
│   └── walker-nav-menu.php
│
└── assets/                (Source files)
    ├── scss/
    └── js/
```

### Theme Functions

**Keep functions.php Minimal:**
```php
<?php
// Theme setup
function customtheme_setup() {
    add_theme_support('post-thumbnails');
    register_nav_menus([
        'primary' => 'Primary Menu'
    ]);
}
add_action('after_setup_theme', 'customtheme_setup');

// Load assets
require_once get_template_directory() . '/inc/enqueue.php';

// Check required plugins
function customtheme_check_required_plugins() {
    $required = [
        'media-lab-agency-core',
        'media-lab-project-starter'
    ];
    // Check logic...
}
```

### Template Hierarchy
```
page.php              → Page template
single.php            → Single post
archive.php           → Archive pages
singular.php          → Single any post type
index.php             → Fallback
```

**Custom Templates:**
```php
<?php
/**
 * Template Name: Full Width
 */
get_header();
// Template content
get_footer();
```

---

## JavaScript Development

### Module System

All JavaScript is modular using ES6 modules.

**Entry Point:** `assets/js/main.js`
```javascript
// Import modules
import { initAccordion } from './modules/accordion';
import { initModal } from './modules/modal';
import { initSlider } from './modules/slider';

// DOM Ready
document.addEventListener('DOMContentLoaded', () => {
    
    // Initialize components
    initAccordion();
    initModal();
    initSlider();
    
    // More initializations...
});
```

### Creating New Module

**1. Create Module File:**
```javascript
// assets/js/modules/my-feature.js

export function initMyFeature() {
    const elements = document.querySelectorAll('.my-feature');
    
    if (!elements.length) return;
    
    elements.forEach(element => {
        element.addEventListener('click', handleClick);
    });
}

function handleClick(e) {
    // Handle click
}
```

**2. Import in main.js:**
```javascript
import { initMyFeature } from './modules/my-feature';

document.addEventListener('DOMContentLoaded', () => {
    initMyFeature();
});
```

### Error Handling
```javascript
// Wrap in try-catch
export function initComponent() {
    try {
        // Component logic
    } catch (error) {
        console.error('Component initialization failed:', error);
    }
}
```

### AJAX Requests
```javascript
// Standard AJAX pattern
async function fetchData(action, data) {
    try {
        const formData = new FormData();
        formData.append('action', action);
        
        Object.entries(data).forEach(([key, value]) => {
            formData.append(key, value);
        });
        
        const response = await fetch(ajaxurl, {
            method: 'POST',
            body: formData
        });
        
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        
        return await response.json();
        
    } catch (error) {
        console.error('AJAX Error:', error);
        return null;
    }
}
```

---

## SCSS Development

### File Structure
```
assets/scss/
├── main.scss              (Entry - imports all)
├── _variables.scss        (Design tokens)
├── _mixins.scss          (Reusable mixins)
│
├── base/
│   ├── _reset.scss       (CSS reset)
│   ├── _typography.scss  (Fonts, headings)
│   └── _global.scss      (Global styles)
│
├── components/
│   ├── _buttons.scss
│   ├── _cards.scss
│   ├── _forms.scss
│   └── ...
│
├── layout/
│   ├── _grid.scss
│   ├── _header.scss
│   ├── _footer.scss
│   └── _sidebar.scss
│
└── utilities/
    ├── _helpers.scss
    └── _spacing.scss
```

### Design Tokens

**Variables:** `_variables.scss`
```scss
// Colors
$primary: #007bff;
$secondary: #6c757d;
$success: #28a745;
$danger: #dc3545;

// Spacing
$spacing-unit: 1rem;
$spacing-xs: $spacing-unit * 0.5;   // 0.5rem
$spacing-sm: $spacing-unit;          // 1rem
$spacing-md: $spacing-unit * 2;      // 2rem
$spacing-lg: $spacing-unit * 3;      // 3rem

// Typography
$font-family-base: 'Inter', sans-serif;
$font-size-base: 1rem;
$line-height-base: 1.5;

// Breakpoints
$breakpoint-sm: 576px;
$breakpoint-md: 768px;
$breakpoint-lg: 992px;
$breakpoint-xl: 1200px;
```

### Mixins

**Common Mixins:** `_mixins.scss`
```scss
// Responsive breakpoints
@mixin respond-to($breakpoint) {
    @media (min-width: $breakpoint) {
        @content;
    }
}

// Flexbox center
@mixin flex-center {
    display: flex;
    align-items: center;
    justify-content: center;
}

// Transition
@mixin transition($property: all, $duration: 0.3s) {
    transition: $property $duration ease;
}
```

### Component Styling
```scss
// components/_buttons.scss
.btn {
    display: inline-block;
    padding: $spacing-sm $spacing-md;
    font-family: $font-family-base;
    font-size: $font-size-base;
    border-radius: 4px;
    @include transition(background-color);
    
    &:hover {
        opacity: 0.9;
    }
    
    &--primary {
        background: $primary;
        color: white;
    }
    
    &--large {
        padding: $spacing-md $spacing-lg;
        font-size: 1.25rem;
    }
}
```

---

## Git Workflow

### Branch Strategy
```
main              → Production-ready code
develop           → Integration branch
feature/*         → New features
fix/*             → Bug fixes
hotfix/*          → Emergency fixes
```

### Commit Messages

**Format:**
```
Type: Brief description

Detailed explanation (optional)

- Change 1
- Change 2
```

**Types:**
- `Add:` New feature
- `Update:` Modify existing
- `Fix:` Bug fix
- `Docs:` Documentation
- `Style:` Formatting
- `Refactor:` Code restructure
- `Test:` Add tests
- `Cleanup:` Remove code

**Examples:**
```bash
git commit -m "Add: Hero slider shortcode"
git commit -m "Fix: Modal close button not working"
git commit -m "Update: Improve search performance"
```

### Common Commands
```bash
# Status
git status
git log --oneline -5

# Branch
git checkout -b feature/name
git branch -d feature/name

# Stage & Commit
git add .
git commit -m "Message"

# Push
git push origin branch-name

# Pull
git pull origin main

# Merge
git checkout main
git merge feature/name
```

---

## Testing

### Run Test Suite
```bash
cd /path/to/media-lab-starter-kit
./tests/run-tests.sh
```

**Expected Output:**
```
✅ All tests passed!
Passed: 23
Failed: 0
Total: 23
```

### Manual Testing

**1. Plugin Functionality:**
```bash
# Verify plugins active
wp plugin list --status=active

# Check shortcodes
wp eval 'global $shortcode_tags; echo count($shortcode_tags);'

# Check CPTs
wp post-type list
```

**2. Frontend Testing:**
- Load homepage
- Check console (no errors)
- Test responsive design
- Test all shortcodes
- Test AJAX features

**3. Performance Testing:**
```bash
# Run Lighthouse
npm install -g lighthouse
lighthouse http://media-lab-starter-kit.test --view
```

---

## Best Practices

### PHP
```php
// ✅ Good
function prefix_function_name() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    $data = get_option('my_option');
    // Process data...
}

// ❌ Bad
function myFunction() {  // No prefix
    $data = $_GET['data'];  // No sanitization
    echo $data;  // No escaping
}
```

### JavaScript
```javascript
// ✅ Good
async function fetchData() {
    try {
        const response = await fetch(url);
        const data = await response.json();
        return data;
    } catch (error) {
        console.error('Error:', error);
        return null;
    }
}

// ❌ Bad
function fetchData() {
    fetch(url).then(r => r.json()).then(d => {
        // Nested promises, no error handling
    });
}
```

### SCSS
```scss
// ✅ Good - BEM naming
.card {
    &__header {
        font-size: 1.5rem;
    }
    
    &__body {
        padding: 1rem;
    }
    
    &--featured {
        border: 2px solid gold;
    }
}

// ❌ Bad - Deep nesting
.card {
    .header {
        .title {
            .text {
                font-size: 1.5rem;  // Too nested
            }
        }
    }
}
```

### Security
```php
// Always sanitize input
$value = sanitize_text_field($_POST['field']);

// Always escape output
echo esc_html($value);
echo esc_url($url);
echo esc_attr($attribute);

// Check capabilities
if (!current_user_can('edit_posts')) {
    wp_die('Unauthorized');
}

// Verify nonces
if (!wp_verify_nonce($_POST['nonce'], 'action_name')) {
    wp_die('Invalid nonce');
}
```

---

## Debugging

### Enable Debug Mode
```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
define('SCRIPT_DEBUG', true);
```

### View Logs
```bash
# Debug log
tail -f cms/wp-content/debug.log

# PHP error log
tail -f /path/to/php-error.log

# Valet log (macOS)
tail -f ~/.valet/Log/nginx-error.log
```

### Browser DevTools
```javascript
// Console debugging
console.log('Value:', value);
console.table(array);
console.error('Error:', error);

// Breakpoints
debugger;  // Pauses execution

// Network monitoring
// DevTools → Network tab
// Filter: XHR, JS, CSS
```

---

## Next Steps

- **Troubleshooting:** [Troubleshooting Guide](07_TROUBLESHOOTING.md)
- **Custom Post Types:** [CPT Documentation](08_CUSTOM-POST-TYPES.md)
- **Testing:** [Testing Guide](11_TESTING.md)

---

**Happy coding!** 💻  
**Next:** [Troubleshooting](07_TROUBLESHOOTING.md) →
