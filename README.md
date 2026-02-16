# Media Lab Starter Kit

**Professional WordPress Agency Framework** - Enterprise-level plugin architecture for scalable client projects.

[![Version](https://img.shields.io/badge/version-1.2.0-blue.svg)](https://github.com/media-admin/media-lab-starter-kit/releases)
[![PHP](https://img.shields.io/badge/PHP-8.0+-purple.svg)](https://php.net)
[![WordPress](https://img.shields.io/badge/WordPress-6.0+-blue.svg)](https://wordpress.org)
[![Tests](https://img.shields.io/badge/tests-23%2F23%20passing-brightgreen.svg)](#testing)

---

## 🎯 Overview

Complete WordPress starter kit with modular plugin architecture designed for agency workflows. Built for maintainability, scalability, and rapid client deployment.

### Key Features

- ✅ **Modular Plugin System** - Separate concerns (Framework, Content, Analytics, SEO)
- ✅ **Reusable Core** - Deploy framework across multiple projects
- ✅ **Client-Ready** - Duplicate and customize per client
- ✅ **Fully Tested** - 23 automated tests, 100% passing
- ✅ **Production-Ready** - Used in live client projects

---

## 📦 Plugin Architecture

### 1. Media Lab Agency Core `v1.1.0`

**Framework & Feature Library** - Reusable across all projects

#### Features
- **44 Shortcodes**: Hero Slider, Accordion, Stats, Testimonials, Modal, Tabs, Carousel, FAQ, Timeline, Video Player, and more
- **AJAX Features**: Search, Load More, Post Filters
- **Security**: Enhanced security features
- **Admin Customizations**: Dashboard improvements
- **Helper Functions**: Utility functions for theme development

#### Installation
```bash
# Already included in starter kit
wp plugin activate media-lab-agency-core
```

#### Usage
```php
// In templates or content
[accordion]
  [accordion_item title="Title"]Content[/accordion_item]
[/accordion]

[hero_slider]
  [hero_slide title="Slide 1"]Content[/hero_slide]
[/hero_slider]

[stats number="1000" label="Projects" icon="check"]
```

---

### 2. Media Lab Project Starter `v1.0.0`

**Content Structure** - Duplicate per client, customize as needed

#### Features
- **9 Custom Post Types**: Team, Project, Job, Service, Testimonial, FAQ, Google Map, Hero Slide, Carousel
- **7 Custom Taxonomies**: Project Category, Service Category, FAQ Category, Carousel Category, Job Category, Job Type, Job Location
- **11 ACF Field Groups**: 65 custom fields (JSON format for version control)
- **ACF Configuration**: Automatic JSON load/save paths

#### Installation
```bash
wp plugin activate media-lab-project-starter
```

#### Customization for Clients
```bash
# Duplicate plugin for new client
cp -r media-lab-project-starter/ client-name-project/

# Update plugin header in client-name-project.php
# Customize CPTs and ACF fields as needed
```

#### Usage
```php
// Query custom post type
$team = new WP_Query([
    'post_type' => 'team',
    'posts_per_page' => 6
]);

// Get ACF field
$job_location = get_field('job_location');
```

---

### 3. Media Lab Analytics `v1.0.0`

**Tracking & Analytics** - Centralized analytics management

#### Features
- **Google Analytics 4**: Easy GA4 integration
- **Google Tag Manager**: GTM container support
- **Facebook Pixel**: FB tracking integration
- **Custom Events**: Track custom interactions
- **Auto-Tracking**: Form submissions, WooCommerce events
- **Privacy**: GDPR-friendly, role-based exclusion

#### Installation
```bash
wp plugin activate media-lab-analytics
```

#### Configuration
Navigate to **Settings → Analytics** and add your tracking IDs:
- Google Analytics 4 ID (Format: `G-XXXXXXXXXX`)
- Google Tag Manager ID (Format: `GTM-XXXXXXX`)
- Facebook Pixel ID (Format: `XXXXXXXXXXXXXXX`)

#### Custom Events
```php
// Track button click
do_action('medialab_track_event', 'button_click', [
    'button_name' => 'Download Brochure',
    'button_location' => 'homepage'
]);

// Track video play
do_action('medialab_track_event', 'video_play', [
    'video_title' => 'Product Demo',
    'video_duration' => '02:30'
]);
```

#### Auto-Tracked Events
- Form submissions (all forms)
- WooCommerce: Add to Cart, Purchase
- Custom events via action hook

---

### 4. Media Lab SEO Toolkit `v1.0.0`

**SEO Optimization** - Comprehensive SEO solution

#### Features
- **Schema.org Markup**: Automatic JSON-LD structured data
- **Open Graph Tags**: Facebook, LinkedIn sharing optimization
- **Twitter Cards**: Enhanced Twitter previews
- **Canonical URLs**: Prevent duplicate content
- **Meta Management**: Title and description optimization
- **Breadcrumbs**: Automatic breadcrumb navigation

#### Installation
```bash
wp plugin activate media-lab-seo
```

#### Configuration
Navigate to **Settings → SEO Toolkit**:
- Enable/disable features individually
- Configure site name
- Add Twitter username
- Set default social image (1200x630px recommended)

#### Schema Types Supported
- **Organization**: Company information (homepage)
- **WebSite**: Site-wide data with SearchAction
- **Article**: Blog posts with author, dates, images
- **Product**: WooCommerce products with pricing
- **BreadcrumbList**: Navigation hierarchy

#### Breadcrumbs Usage
```php
// In your theme template
if (function_exists('medialab_seo_breadcrumbs')) {
    medialab_seo_breadcrumbs([
        'separator' => ' › ',
        'home_title' => 'Home',
        'wrapper_class' => 'breadcrumbs'
    ]);
}
```

---

## 🚀 Quick Start

### Requirements
- PHP 8.0+
- WordPress 6.0+
- Advanced Custom Fields PRO (for Project Starter)

### Installation

1. **Clone Repository**
```bash
git clone https://github.com/media-admin/media-lab-starter-kit.git
cd media-lab-starter-kit
```

2. **Install Dependencies**
```bash
npm install
composer install
```

3. **Activate Plugins**
```bash
cd cms
wp plugin activate media-lab-agency-core
wp plugin activate media-lab-project-starter
wp plugin activate media-lab-analytics
wp plugin activate media-lab-seo
```

4. **Activate Theme**
```bash
wp theme activate custom-theme
```

5. **Build Assets**
```bash
npm run build
# or for development
npm run dev
```

---

## 🧪 Testing

Run automated test suite:
```bash
./tests/run-tests.sh
```

**Test Coverage:**
- ✅ Plugin activation (4 plugins)
- ✅ Shortcode registration (4 shortcodes)
- ✅ Custom Post Types (3 CPTs)
- ✅ ACF functionality (3 tests)
- ✅ Theme activation
- ✅ AJAX actions (3 tests)
- ✅ Analytics settings (2 tests)
- ✅ SEO functionality (3 tests)

**Total: 23 tests, 100% passing**

---

## 🎨 Theme Structure
```
custom-theme/
├── assets/
│   ├── scss/           # SCSS with design tokens
│   └── js/             # JavaScript modules (24 components)
├── dist/               # Compiled assets (Vite)
├── inc/
│   ├── enqueue.php     # Asset loading
│   ├── helpers.php     # Theme helpers
│   └── walker-nav-menu.php
├── template-parts/     # Reusable components
├── functions.php       # Theme setup (118 lines)
└── style.css           # Theme header
```

---

## 🔧 Development

### Build System
- **Vite** for fast asset compilation
- **SCSS** with design tokens
- **Hot Module Replacement** in development

### Commands
```bash
npm run dev          # Development with HMR
npm run build        # Production build
npm run preview      # Preview production build
```

### Code Quality
- Manual development (no automated tools)
- WordPress best practices
- Modular architecture
- Version controlled (Git)

---

## 📋 For New Client Projects

### Setup New Client

1. **Duplicate Project Plugin**
```bash
cp -r cms/wp-content/plugins/media-lab-project-starter/ \
      cms/wp-content/plugins/client-name-project/
```

2. **Update Plugin Header**
Edit `client-name-project.php`:
```php
/**
 * Plugin Name: Client Name Project
 * Description: Custom content structure for Client Name
 */
```

3. **Customize**
- Modify CPTs as needed
- Adjust ACF fields
- Update taxonomies

4. **Activate**
```bash
wp plugin activate client-name-project
```

### Core Plugin
**Never modify** - use across all projects unchanged

### Analytics & SEO Plugins
Activate per project, configure tracking IDs per client

---

## 📊 Architecture Benefits

### Separation of Concerns
- **Core Plugin**: Reusable framework features
- **Project Plugin**: Client-specific business logic
- **Theme**: Presentation layer only

### Maintainability
- Clear boundaries between components
- Easy to update/replace individual parts
- No code duplication

### Scalability
- Core Plugin: Use across all projects
- Project Plugin: Duplicate per client
- Theme: Swap without losing functionality

### Version Control
- ACF Fields in JSON (merge-friendly)
- Modular commits
- Clear change history

---

## 🏗️ Project Structure
```
media-lab-starter-kit/
├── cms/
│   └── wp-content/
│       ├── plugins/
│       │   ├── media-lab-agency-core/
│       │   ├── media-lab-project-starter/
│       │   ├── media-lab-analytics/
│       │   └── media-lab-seo/
│       ├── themes/
│       │   └── custom-theme/
│       └── mu-plugins/
├── tests/
│   ├── run-tests.sh
│   └── README.md
├── node_modules/
├── package.json
├── vite.config.js
└── README.md
```

---

## 📖 Documentation

- **Plugin READMEs**: Each plugin includes detailed documentation
- **Testing Guide**: `tests/README.md`
- **Change Log**: `CHANGELOG.md`
- **Deployment**: `DEPLOYMENT.md`

---

## 🔄 Updates & Maintenance

### Update Core Plugin
Updates automatically apply to all projects using the plugin.

### Update Project Plugin
1. Make changes to master copy
2. Git pull on client sites
3. Or manually sync changes

### Theme Updates
Theme updates independent of plugins - functionality preserved.

---

## 🤝 Contributing

This is a private agency starter kit. For team members:

1. Create feature branch
2. Make changes
3. Run tests: `./tests/run-tests.sh`
4. Create pull request

---

## 📜 License

Proprietary - Media Lab Tritremmel GmbH

---

## 🆘 Support

- **Issues**: GitHub Issues
- **Email**: markus.tritremmel@media-lab.at
- **Website**: https://www.media-lab.at

---

## 📈 Changelog

### v1.2.0 (2026-02-16)
- ✅ Added Media Lab Analytics Plugin
- ✅ Added Media Lab SEO Toolkit Plugin
- ✅ 23 automated tests (100% passing)
- ✅ Complete cleanup (removed backups)

### v1.1.0 (2026-02-16)
- ✅ Plugin architecture migration
- ✅ Core Plugin with 44 shortcodes + AJAX
- ✅ Project Plugin with CPTs + ACF
- ✅ Theme cleanup (118 lines)
- ✅ 16 automated tests

### v1.0.0
- Initial plugin architecture
- Theme with Vite build system
- SCSS design tokens
- 24 JavaScript components

---

**Built with ❤️ by Media Lab Tritremmel GmbH**
