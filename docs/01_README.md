# WordPress Agency Starter Kit

> A production-ready, enterprise-level WordPress starter kit for agencies managing multiple client websites.

![Version](https://img.shields.io/badge/version-1.0.0-blue.svg)
![WordPress](https://img.shields.io/badge/WordPress-6.4+-green.svg)
![PHP](https://img.shields.io/badge/PHP-8.0+-purple.svg)
![License](https://img.shields.io/badge/license-MIT-orange.svg)

---

## 🎯 Overview

This is a comprehensive WordPress system built for agencies managing 20+ client websites. It provides a scalable, maintainable foundation with modern development practices, custom themes, and sophisticated component libraries.

### Key Features

- ✅ **9 Custom Post Types** - Jobs, Projects, Team, Services, Testimonials, Hero Slides, Carousel, FAQ, Google Maps
- ✅ **11 ACF Field Groups** - Complete content management
- ✅ **45+ Shortcodes** - Flexible page building
- ✅ **Advanced AJAX Filters** - Professional filtering system
- ✅ **AJAX Search** - Live search with WooCommerce integration
- ✅ **Modern Build System** - Vite with hot-reload
- ✅ **WooCommerce Ready** - E-commerce integration
- ✅ **Git-Based Workflow** - Version control & deployment
- ✅ **Production Ready** - CI/CD, monitoring, backups

---

## 📦 System Architecture
```
cms/
├── wp-content/
│   ├── mu-plugins/
│   │   └── agency-core/          # Business logic
│   │       ├── inc/
│   │       │   ├── custom-post-types.php
│   │       │   ├── acf-fields.php
│   │       │   ├── shortcodes.php
│   │       │   ├── ajax-search.php
│   │       │   ├── ajax-filters.php
│   │       │   └── ajax-load-more.php
│   │       └── agency-core.php
│   │
│   └── themes/
│       └── custom-theme/          # Presentation layer
│           ├── assets/
│           │   ├── src/           # Source files
│           │   │   ├── scss/
│           │   │   └── js/
│           │   └── dist/          # Compiled assets
│           ├── src/
│           │   └── inc/
│           │       └── enqueue.php
│           └── functions.php
│
└── [WordPress core files]
```

---

## 🚀 Quick Start

### Prerequisites

- **PHP:** 8.0+
- **WordPress:** 6.4+
- **Node.js:** 18+
- **Composer:** 2.0+
- **Valet/XAMPP/Local:** Development environment

### Installation
```bash
# 1. Clone repository
git clone https://github.com/your-agency/wordpress-starter-kit.git
cd wordpress-starter-kit

# 2. Install dependencies
composer install
npm install

# 3. Setup WordPress
cd cms
wp core config --dbname=database --dbuser=user --dbpass=password
wp core install --url=site.test --title="Site Title" --admin_user=admin --admin_email=admin@example.com
cd ..

# 4. Build assets
npm run build

# 5. Activate theme
cd cms
wp theme activate custom-theme
cd ..
```

For detailed setup instructions, see [INSTALLATION.md](./INSTALLATION.md)

---

## 📖 Documentation

- **[Installation Guide](./INSTALLATION.md)** - Complete setup instructions
- **[Usage Guide](./USAGE.md)** - How to use the system
- **[Shortcodes Reference](./SHORTCODES.md)** - All available shortcodes
- **[AJAX Filters Guide](./AJAX-FILTERS.md)** - Advanced filtering system
- **[Custom Post Types](./CUSTOM-POST-TYPES.md)** - CPT documentation
- **[ACF Fields](./ACF-FIELDS.md)** - Field groups reference
- **[Development Guide](./DEVELOPMENT.md)** - For developers
- **[Deployment Guide](./DEPLOYMENT.md)** - Production deployment
- **[Troubleshooting](./TROUBLESHOOTING.md)** - Common issues
- **[API Reference](./API.md)** - Filters & actions

---

## 🎨 Features Overview

### Custom Post Types

| Post Type | Purpose | Taxonomies |
|-----------|---------|------------|
| Jobs | Job listings | Category, Type, Location |
| Projects | Portfolio items | Category |
| Team | Team members | Department, Position |
| Services | Service offerings | Category |
| Testimonials | Client reviews | Industry |
| Hero Slides | Homepage sliders | - |
| Carousel | Image carousels | Category |
| FAQ | Frequently asked questions | Category |
| Google Maps | GDPR-compliant maps | - |

### Shortcodes Categories

- **Layout:** accordion, tabs, timeline, modal
- **Content:** hero_slider, carousel, testimonials, team_cards
- **Interactive:** ajax_search, ajax_filters, posts_load_more
- **Stats:** stats, pricing_tables
- **Media:** video_player, image_comparison, logo_carousel
- **Utilities:** notification, spoiler, google_map

### AJAX Features

- **Live Search** - Instant search results with dropdown
- **Advanced Filters** - Taxonomy, meta, range, search filters
- **Load More** - Infinite scroll pagination
- **WooCommerce** - Product filtering and search

---

## 💻 Development

### Development Workflow
```bash
# Start dev server with hot-reload
npm run dev

# Build for production
npm run build

# Watch for changes
npm run watch
```

### Code Standards

- **PHP:** PSR-12, WordPress Coding Standards
- **JavaScript:** ES6+, ESLint
- **CSS:** BEM methodology, SCSS
- **Git:** Conventional Commits

### Project Structure
```
assets/src/
├── scss/
│   ├── base/              # Variables, reset, typography
│   ├── components/        # UI components
│   ├── layout/            # Layout structures
│   ├── templates/         # Page templates
│   └── style.scss         # Main entry point
│
└── js/
    ├── components/        # JS components
    ├── utils/             # Helper functions
    └── main.js            # Main entry point
```

---

## 🔧 Customization

### Adding a Custom Post Type

1. Edit `cms/wp-content/mu-plugins/agency-core/inc/custom-post-types.php`
2. Add registration function
3. Create ACF field group in `acf-fields.php`
4. Add shortcode in `shortcodes.php`
5. Create template in theme

### Creating Custom Shortcodes
```php
function my_custom_shortcode($atts, $content = null) {
    $atts = shortcode_atts(array(
        'title' => 'Default Title',
    ), $atts);
    
    return '<div class="custom">' . esc_html($atts['title']) . '</div>';
}
add_shortcode('my_custom', 'my_custom_shortcode');
```

---

## 🚢 Deployment

### Production Checklist

- [ ] Build assets: `npm run build`
- [ ] Test all functionality
- [ ] Backup database
- [ ] Update .env file
- [ ] Push to Git repository
- [ ] Deploy via CI/CD
- [ ] Clear all caches
- [ ] Test on production

See [DEPLOYMENT.md](./DEPLOYMENT.md) for detailed instructions.

---

## 🐛 Troubleshooting

### Common Issues

**Issue:** Scripts not loading  
**Solution:** Run `npm run build` and clear cache

**Issue:** Filters returning 403  
**Solution:** Check nonce names match in PHP and JavaScript

**Issue:** ACF fields not showing  
**Solution:** Verify location rules and template paths

For more solutions, see [TROUBLESHOOTING.md](./TROUBLESHOOTING.md)

---

## 🤝 Contributing

We welcome contributions! Please follow these steps:

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/amazing-feature`
3. Commit changes: `git commit -m 'Add amazing feature'`
4. Push to branch: `git push origin feature/amazing-feature`
5. Open a Pull Request

---

## 📝 Changelog

See [CHANGELOG.md](./CHANGELOG.md) for version history.

---

## 📄 License

This project is licensed under the MIT License - see the LICENSE file for details.

---

## 🙏 Credits

- **Agency Core Plugin** - Custom business logic
- **Advanced Custom Fields Pro** - Content management
- **Vite** - Modern build tool
- **Swiper** - Touch slider
- **WordPress** - CMS platform

---

## 📞 Support

- **Documentation:** [docs/](./docs/)
- **Issues:** [GitHub Issues](https://github.com/your-agency/wordpress-starter-kit/issues)
- **Email:** support@your-agency.com

---

Built with ❤️ by Media Lab Tritremmel GmbH