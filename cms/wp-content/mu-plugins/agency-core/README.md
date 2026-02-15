# Agency Core

**Version:** 1.0.0  
**Author:** Media Lab Tritremmel GmbH  
**License:** GPL v2 or later

## Description

Agency Core provides essential business functionality for agency websites, including:

- Custom Post Types (Team, Projects, Testimonials, Services)
- ACF Field Groups
- 21+ Professional Shortcodes
- Admin enhancements

This plugin is **theme-independent** and persists across theme changes.

---

## Features

### Custom Post Types

#### 1. Team Members
- Fields: Role, Email, Phone, Social Media, Display Order
- Shortcode: `[team_query]`

#### 2. Projects
- Fields: Client, Date, URL, Gallery, Technologies
- Taxonomy: Project Categories
- Shortcode: `[projects_query]`

#### 3. Testimonials
- Fields: Author, Company, Position, Rating, Image
- Shortcode: `[testimonials_query]`

#### 4. Services
- Fields: Icon, Price, Features
- Shortcode: `[services_query]`

---

## Available Shortcodes

### Hero Slider
```
[hero_slider autoplay="true" loop="true" delay="5000"]
[hero_slide image="url" title="Title" subtitle="Subtitle"]
Content
[/hero_slide]
[/hero_slider]
```

### Pricing Tables
```
[pricing_tables columns="3"]
[pricing_table title="Basic" price="29"]
[pricing_feature]Feature[/pricing_feature]
[/pricing_table]
[/pricing_tables]
```

### Team Query
```
[team_query columns="3" number="6" orderby="menu_order"]
```

### Stats/Counters
```
[stats columns="4"]
[stat number="100" suffix="+" label="Clients"]
[/stats]
```

### And 17+ more shortcodes!

---

## Requirements

- WordPress 5.9+
- PHP 7.4+
- Advanced Custom Fields (recommended)

---

## Installation

This plugin is installed as a Must-Use (MU) Plugin:

1. Upload `agency-core` folder to `/wp-content/mu-plugins/`
2. Ensure `000-mu-plugin-loader.php` loads the plugin
3. Activate and configure

---

## Theme Compatibility

Agency Core works with **any** WordPress theme. For best results, use with our custom themes that include styling for all components.

### Theme Integration

Themes can hook into Agency Core:
```php
// Add custom classes to shortcodes
add_filter('agency_core_shortcode_wrapper_class', function($class, $shortcode) {
    return $class . ' my-custom-class';
}, 10, 2);
```

---

## Development

Agency Core separates **business logic** from **presentation**:

- **Plugin:** CPTs, Fields, Shortcodes (functionality)
- **Theme:** Templates, CSS, JS (styling)

This allows:
- Theme changes without losing functionality
- Reusable across multiple projects
- Clean separation of concerns

---

## Support

For support, please contact: support@your-agency.com

---

## Changelog

### 1.0.0 (2026-02-09)
- Initial release
- 4 Custom Post Types
- 21+ Shortcodes
- ACF Integration
- Admin enhancements