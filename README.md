# Agency WordPress Template

Professional WordPress setup with Agency Core plugin and custom theme.

## 🏗️ Architecture
```
├── cms/                           WordPress Installation
│   ├── wp-content/
│   │   ├── mu-plugins/
│   │   │   ├── agency-core/      Business Logic (CPTs, ACF, Shortcodes)
│   │   │   └── custom-blocks/    Shortcode Definitions
│   │   └── themes/
│   │       └── custom-theme/     Presentation Layer (SCSS, JS, Templates)
│   └── wp-config.php             (not in Git - use wp-config-sample.php)
├── node_modules/                  (not in Git)
├── package.json                   Build dependencies
└── vite.config.js                 Build configuration
```

## 🚀 Quick Start

### Prerequisites
- PHP 8.0+
- MySQL 8.0+
- Node.js 18+
- Composer (optional)

### Installation

1. **Clone repository**
```bash
   git clone <your-repo-url>
   cd <project-name>
```

2. **Install dependencies**
```bash
   npm install
```

3. **WordPress setup**
```bash
   # Copy wp-config sample
   cp cms/wp-config-sample.php cms/wp-config.php
   
   # Edit wp-config.php with your database credentials
```

4. **Build assets**
```bash
   npm run build
```

5. **Install WordPress**
   - Visit your local domain
   - Complete WordPress installation
   - Install Advanced Custom Fields plugin

### Development
```bash
# Start dev server with HMR
npm run dev

# Build for production
npm run build
```

## 📦 Features

### Agency Core Plugin
- 4 Custom Post Types (Team, Projects, Testimonials, Services)
- ACF Field Groups
- 21+ Professional Shortcodes
- Theme-independent business logic

### Custom Theme
- Vite build system
- SCSS with Autoprefixer
- Swiper.js integration
- Responsive grid layouts
- Modern component architecture

### Shortcodes
- Hero Slider (responsive images, Swiper)
- Pricing Tables (3+ column layouts)
- Team Cards (ACF integration)
- Stats/Counters (animated)
- Testimonials Slider
- FAQ Accordion
- Tabs, Timeline, Modal, and more...

## 🔧 Configuration

### Build Configuration
See `vite.config.js` for build settings.

### WordPress Configuration
See `cms/wp-config-sample.php` for required constants.

## 📝 Documentation

- **Agency Core:** `/cms/wp-content/mu-plugins/agency-core/README.md`
- **Custom Theme:** `/cms/wp-content/themes/custom-theme/README.md`

## 🤝 Contributing

1. Create a feature branch
2. Make your changes
3. Test thoroughly
4. Submit a pull request

## 📄 License

Proprietary - All rights reserved

## 👥 Authors

Media Lab Tritremmel GmbH