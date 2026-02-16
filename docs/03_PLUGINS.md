# Plugin Architecture Documentation

**Version:** 1.2.0  
**Last Updated:** February 16, 2026

Complete guide to the modular plugin system in Media Lab Starter Kit.

---

## Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [Media Lab Agency Core](#media-lab-agency-core)
3. [Media Lab Project Starter](#media-lab-project-starter)
4. [Media Lab Analytics](#media-lab-analytics)
5. [Media Lab SEO](#media-lab-seo)
6. [Plugin Dependencies](#plugin-dependencies)
7. [Client Customization](#client-customization)
8. [Best Practices](#best-practices)

---

## Architecture Overview

### Plugin System Philosophy

The starter kit uses a **modular plugin architecture** that separates concerns:
```
┌─────────────────────────────────────────────────┐
│                                                 │
│  Theme (Presentation Layer)                    │
│  └─ Templates, CSS, JS                         │
│                                                 │
└─────────────────────────────────────────────────┘
                      ↓ depends on
┌─────────────────────────────────────────────────┐
│                                                 │
│  Plugins (Business Logic)                      │
│  ├─ Core (Framework - Reusable)               │
│  ├─ Project (Content - Per Client)            │
│  ├─ Analytics (Tracking - Optional)           │
│  └─ SEO (Optimization - Optional)             │
│                                                 │
└─────────────────────────────────────────────────┘
```

### Benefits

**Separation of Concerns:**
- Theme = Presentation only
- Plugins = All functionality
- Clear boundaries

**Reusability:**
- Core Plugin = Use across all projects
- Project Plugin = Duplicate per client
- No code duplication

**Maintainability:**
- Update core framework independently
- Client customizations isolated
- Version control friendly

**Scalability:**
- Add plugins as needed
- Remove unused features
- Modular growth

---

## Media Lab Agency Core

**Version:** 1.1.0  
**Type:** Framework Plugin (Reusable)  
**Required:** Yes

### Purpose

Provides core framework features used across all projects. **Never modify** - use as-is for all clients.

### Features

#### 1. Shortcodes (44 total)

Complete library of content building blocks:
- Layout: Hero Slider, Accordion, Tabs, Timeline
- Content: Stats, Testimonials, FAQ, Team Grid
- Interactive: Modal, Video Player, Carousel
- **See:** [Shortcodes Documentation](04_SHORTCODES.md)

#### 2. AJAX Features

Professional filtering and loading:
- Search with live results
- Load More pagination
- Advanced post filters
- **See:** [AJAX Features Documentation](05_AJAX-FEATURES.md)

#### 3. Security Enhancements

Hardened WordPress security:
- Login protection
- File upload restrictions
- Security headers
- XSS prevention

#### 4. Admin Customizations

Improved admin experience:
- Custom dashboard widgets
- Streamlined menus
- Quick links
- Better UX

#### 5. Helper Functions

Developer utilities:
```php
// Get plugin version
medialab_core_version(); // Returns: '1.1.0'

// Check if Core active
function_exists('medialab_core_version');
```

### File Structure
```
media-lab-agency-core/
├── media-lab-agency-core.php    (Main plugin file)
├── README.md                     (Plugin documentation)
└── inc/
    ├── shortcodes.php           (44 shortcodes - 118KB)
    ├── ajax-search.php          (Live search)
    ├── ajax-load-more.php       (Pagination)
    ├── ajax-filters.php         (Post filtering)
    ├── security.php             (Security features)
    ├── admin.php                (Admin customizations)
    └── helpers.php              (Utility functions)
```

### Usage
```bash
# Activation
wp plugin activate media-lab-agency-core

# Verify
wp plugin is-active media-lab-agency-core

# Check version
wp eval "echo medialab_core_version();"
```

### Updates

When updating Core Plugin:
1. Test in staging
2. Update via Git
3. All client sites get improvements automatically
4. No per-client modifications needed

---

## Media Lab Project Starter

**Version:** 1.0.0  
**Type:** Content Structure Plugin (Client-Specific)  
**Required:** Yes

### Purpose

Provides content architecture. **Duplicate and customize** for each client.

### Features

#### 1. Custom Post Types (9)
```php
'team'        => Team Members
'project'     => Portfolio Projects
'job'         => Job Listings
'service'     => Services Offered
'testimonial' => Client Testimonials
'faq'         => FAQ Items
'gmap'        => Google Maps
'hero_slide'  => Hero Slider Slides
'carousel'    => Carousel Items
```

**See:** [Custom Post Types Documentation](08_CUSTOM-POST-TYPES.md)

#### 2. Custom Taxonomies (7)
```php
'project_category'  => Organize projects
'service_category'  => Organize services
'faq_category'      => Organize FAQs
'carousel_category' => Organize carousel items
'job_category'      => Organize jobs
'job_type'          => Full-time, Part-time, etc.
'job_location'      => Remote, Office, etc.
```

#### 3. ACF Field Groups (11)

65 custom fields in JSON format:
- Team Member fields
- Project fields
- Job fields
- Service fields
- And more...

**See:** [ACF Fields Documentation](09_ACF-FIELDS.md)

#### 4. ACF Configuration

Automatic JSON load/save:
```php
// Save path
add_filter('acf/settings/save_json', function($path) {
    return MEDIALAB_PROJECT_PATH . 'acf-json';
});

// Load path
add_filter('acf/settings/load_json', function($paths) {
    $paths[] = MEDIALAB_PROJECT_PATH . 'acf-json';
    return $paths;
});
```

### File Structure
```
media-lab-project-starter/
├── media-lab-project-starter.php  (Main file)
├── README.md                       (Documentation)
├── acf-json/                       (11 JSON files)
│   ├── group_team_member.json
│   ├── group_project.json
│   ├── group_job.json
│   └── ... (8 more)
└── inc/
    ├── custom-post-types.php      (9 CPTs - 23KB)
    ├── taxonomies.php             (7 taxonomies)
    └── acf-config.php             (ACF paths)
```

### Dependency Check

Plugin checks for Core Plugin:
```php
function medialab_project_check_dependencies() {
    if (!function_exists('medialab_core_version')) {
        // Shows admin notice
        return false;
    }
    return true;
}
```

### Usage
```bash
# Activation
wp plugin activate media-lab-project-starter

# Verify CPTs
wp post-type list | grep team

# Verify ACF
wp eval "echo count(acf_get_field_groups());"  # Should be 11+
```

---

## Media Lab Analytics

**Version:** 1.0.0  
**Type:** Optional Feature Plugin  
**Required:** No (but recommended)

### Purpose

Centralized tracking and analytics management.

### Features

#### 1. Google Analytics 4
```php
// Automatic injection in <head>
// Format: G-XXXXXXXXXX
```

#### 2. Google Tag Manager
```php
// GTM in <head> and <body>
// Format: GTM-XXXXXXX
```

#### 3. Facebook Pixel
```php
// FB Pixel with PageView
// Format: XXXXXXXXXXXXXXX
```

#### 4. Custom Events
```php
// Track custom interactions
do_action('medialab_track_event', 'button_click', [
    'button_name' => 'Download PDF',
    'button_location' => 'sidebar'
]);

// Track video play
do_action('medialab_track_event', 'video_play', [
    'video_title' => 'Product Demo'
]);
```

#### 5. Auto-Tracking

Automatically tracks:
- Form submissions (all forms)
- WooCommerce: Add to Cart
- WooCommerce: Purchase

#### 6. Privacy Features

- Admin users excluded by default
- IP anonymization (GA4)
- Role-based exclusion
- GDPR-friendly

### Configuration

**Settings Page:** Settings → Analytics
```
✅ Enable Tracking
✅ Google Analytics 4 ID: G-XXXXXXXXXX
✅ Google Tag Manager ID: GTM-XXXXXXX
✅ Facebook Pixel ID: XXXXXXXXXXXXXXX
```

### File Structure
```
media-lab-analytics/
├── media-lab-analytics.php        (Main file)
├── README.md                       (Documentation)
└── inc/
    ├── settings.php               (Admin page)
    ├── tracking.php               (GA4, GTM, FB output)
    ├── events.php                 (Custom events, auto-tracking)
    └── (no assets folder needed)
```

**See:** [Analytics Documentation](12_ANALYTICS.md)

---

## Media Lab SEO

**Version:** 1.0.0  
**Type:** Optional Feature Plugin  
**Required:** No (but recommended)

### Purpose

Comprehensive SEO optimization with structured data.

### Features

#### 1. Schema.org Markup

Automatic JSON-LD structured data:
```json
{
  "@context": "https://schema.org",
  "@graph": [
    {
      "@type": "Organization",
      "name": "Company Name"
    },
    {
      "@type": "Article",
      "headline": "Blog Post Title"
    }
  ]
}
```

**Schema Types:**
- Organization (Homepage)
- WebSite (Site-wide)
- Article (Blog posts)
- Product (WooCommerce)
- BreadcrumbList (Navigation)

#### 2. Open Graph Tags

Facebook/LinkedIn optimization:
```html
<meta property="og:title" content="Page Title">
<meta property="og:description" content="Description">
<meta property="og:image" content="image.jpg">
```

#### 3. Twitter Cards

Enhanced Twitter previews:
```html
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="Page Title">
```

#### 4. Canonical URLs

Prevent duplicate content:
```html
<link rel="canonical" href="https://example.com/page/">
```

#### 5. Breadcrumbs

Navigation function:
```php
// In template
if (function_exists('medialab_seo_breadcrumbs')) {
    medialab_seo_breadcrumbs();
}
```

### Configuration

**Settings Page:** Settings → SEO Toolkit
```
✅ Enable SEO Features
✅ Schema.org Markup: Enabled
✅ Open Graph Tags: Enabled
✅ Twitter Cards: Enabled
Site Name: Company Name
Twitter Username: @handle
Default Image: https://example.com/og-image.jpg
```

### File Structure
```
media-lab-seo/
├── media-lab-seo.php              (Main file)
├── README.md                       (Documentation)
└── inc/
    ├── settings.php               (Admin page)
    ├── schema.php                 (JSON-LD output)
    ├── opengraph.php              (OG tags)
    ├── twitter.php                (Twitter cards)
    ├── meta.php                   (Canonical, meta)
    └── breadcrumbs.php            (Breadcrumb function)
```

**See:** [SEO Documentation](13_SEO.md)

---

## Plugin Dependencies

### Dependency Tree
```
Theme (custom-theme)
  ↓ requires
Core Plugin (media-lab-agency-core)
  ↓ required by
Project Plugin (media-lab-project-starter)
  ↓ optional
Analytics Plugin (media-lab-analytics)
  ↓ optional
SEO Plugin (media-lab-seo)
```

### Required Dependencies

**Core Plugin needs:**
- WordPress 6.0+
- PHP 8.0+

**Project Plugin needs:**
- Core Plugin (checked on activation)
- Advanced Custom Fields PRO
- WordPress 6.0+
- PHP 8.0+

**Analytics Plugin needs:**
- WordPress 6.0+
- PHP 8.0+

**SEO Plugin needs:**
- WordPress 6.0+
- PHP 8.0+

### Activation Order
```bash
# 1. Core (must be first)
wp plugin activate media-lab-agency-core

# 2. Project (checks for Core)
wp plugin activate media-lab-project-starter

# 3. Optional plugins (any order)
wp plugin activate media-lab-analytics
wp plugin activate media-lab-seo
```

---

## Client Customization

### For New Client

#### 1. Duplicate Project Plugin
```bash
cd cms/wp-content/plugins

# Copy Project Plugin
cp -r media-lab-project-starter/ acme-corp-project/

# Enter directory
cd acme-corp-project/
```

#### 2. Update Plugin Header

Edit `acme-corp-project.php`:
```php
<?php
/**
 * Plugin Name: ACME Corp Project
 * Plugin URI: https://acmecorp.com
 * Description: Custom content structure for ACME Corporation
 * Version: 1.0.0
 * Author: Media Lab
 * Author URI: https://medialab.at
 * Text Domain: acme-corp-project
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

// Update constants
define('ACME_CORP_VERSION', '1.0.0');
define('ACME_CORP_PATH', plugin_dir_path(__FILE__));
// etc...
```

#### 3. Customize CPTs

Edit `inc/custom-post-types.php`:
```php
// Add client-specific CPT
register_post_type('product', [
    'label' => 'Products',
    'public' => true,
    'supports' => ['title', 'editor', 'thumbnail'],
]);

// Remove unused CPTs
// Comment out or delete unwanted post types
```

#### 4. Customize ACF Fields

- Add new field groups in WordPress admin
- Export to JSON (automatic)
- Fields save to `acme-corp-project/acf-json/`
- Version control changes

#### 5. Activate
```bash
wp plugin activate acme-corp-project

# Verify
wp plugin list | grep acme-corp
```

### What NOT to Modify

**Never modify:**
- Core Plugin (`media-lab-agency-core`)
- Analytics Plugin (`media-lab-analytics`)
- SEO Plugin (`media-lab-seo`)

**Always duplicate & customize:**
- Project Plugin (`media-lab-project-starter`)

---

## Best Practices

### 1. Version Control
```bash
# Track plugin changes
git add cms/wp-content/plugins/media-lab-*/
git commit -m "Update: Plugin improvements"

# Track ACF JSON
git add cms/wp-content/plugins/*/acf-json/*.json
git commit -m "Update: ACF fields"
```

### 2. Testing
```bash
# Always test after plugin updates
./tests/run-tests.sh

# Test activation
wp plugin activate plugin-name --debug
```

### 3. Documentation

Update plugin README.md when making changes:
```markdown
## Changelog

### 1.1.0
- Added: New feature X
- Fixed: Bug Y
- Updated: Dependency Z
```

### 4. Dependencies

Always check dependencies before activation:
```php
function check_dependencies() {
    if (!function_exists('required_function')) {
        add_action('admin_notices', function() {
            echo '<div class="error"><p>Missing dependency!</p></div>';
        });
        return false;
    }
    return true;
}
```

### 5. Deactivation

Safe deactivation hooks:
```php
register_deactivation_hook(__FILE__, function() {
    // Clean up temporary data
    // Don't delete permanent data
    flush_rewrite_rules();
});
```

---

## Troubleshooting

### Plugin Won't Activate
```bash
# Check dependencies
wp plugin is-active media-lab-agency-core

# Check for errors
wp plugin activate plugin-name --debug

# Check PHP version
php -v  # Must be 8.0+
```

### Features Not Working
```bash
# Verify plugin active
wp plugin list --status=active

# Clear cache
wp cache flush

# Check for JavaScript errors
# Open browser console (F12)
```

### ACF Fields Not Loading
```bash
# Check JSON files exist
ls -la cms/wp-content/plugins/media-lab-project-starter/acf-json/

# Verify ACF PRO active
wp plugin is-active advanced-custom-fields-pro

# Sync in admin
# Go to: Custom Fields → Tools → Sync available
```

---

## Next Steps

- **Learn Shortcodes:** [Shortcodes Documentation](04_SHORTCODES.md)
- **AJAX Features:** [AJAX Documentation](05_AJAX-FEATURES.md)
- **Development:** [Development Guide](06_DEVELOPMENT.md)
- **Testing:** [Testing Guide](11_TESTING.md)

---

**Master the plugin system!** 🔌  
**Next:** [Shortcodes Reference](04_SHORTCODES.md) →
