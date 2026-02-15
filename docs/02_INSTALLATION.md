# Installation Guide

Complete step-by-step installation guide for the WordPress Agency Starter Kit.

---

## 📋 Table of Contents

- [Prerequisites](#prerequisites)
- [Local Development Setup](#local-development-setup)
- [WordPress Installation](#wordpress-installation)
- [Theme & Plugin Setup](#theme--plugin-setup)
- [Asset Compilation](#asset-compilation)
- [Database Configuration](#database-configuration)
- [Post-Installation](#post-installation)
- [Troubleshooting](#troubleshooting)

---

## 🔧 Prerequisites

### Required Software

| Software | Version | Purpose |
|----------|---------|---------|
| **PHP** | 8.0+ | WordPress core & plugins |
| **MySQL/MariaDB** | 5.7+ / 10.3+ | Database |
| **Node.js** | 18+ | Asset compilation |
| **npm** | 9+ | Package management |
| **Composer** | 2.0+ | PHP dependencies |
| **WP-CLI** | 2.8+ | WordPress management |

### Development Environment

Choose one:

- **Valet** (macOS) - Recommended for Mac users
- **Local by Flywheel** - Cross-platform GUI
- **XAMPP** - Windows/Linux
- **Docker** - Advanced users

### Check Your System
```bash
# Check PHP version
php -v
# Should show: PHP 8.0.0 or higher

# Check Node version
node -v
# Should show: v18.0.0 or higher

# Check npm version
npm -v
# Should show: 9.0.0 or higher

# Check Composer
composer --version
# Should show: Composer version 2.0.0 or higher

# Check WP-CLI
wp --version
# Should show: WP-CLI 2.8.0 or higher
```

If any are missing, install them first.

---

## 🚀 Local Development Setup

### Option 1: Valet (macOS - Recommended)
```bash
# 1. Install Valet (if not already installed)
composer global require laravel/valet
valet install

# 2. Create project directory
mkdir -p ~/Sites/your-project
cd ~/Sites/your-project

# 3. Clone or create project structure
# (Continue to WordPress Installation)
```

### Option 2: Local by Flywheel

1. Download [Local](https://localwp.com/)
2. Install and open Local
3. Click "Create a new site"
4. Choose custom environment:
   - **PHP:** 8.0+
   - **Web Server:** nginx
   - **Database:** MySQL 8.0+
5. Note the site path: `/Users/you/Local Sites/your-site/app/public`

### Option 3: XAMPP

1. Download [XAMPP](https://www.apachefriends.org/)
2. Install XAMPP
3. Start Apache and MySQL
4. Create project in `C:\xampp\htdocs\your-project` (Windows) or `/Applications/XAMPP/htdocs/your-project` (Mac)

---

## 📦 WordPress Installation

### Step 1: Download WordPress
```bash
# Navigate to your project directory
cd ~/Sites/your-project  # Valet
# OR
cd ~/Local\ Sites/your-site/app/public  # Local
# OR
cd /Applications/XAMPP/htdocs/your-project  # XAMPP

# Download WordPress
wp core download --path=cms --locale=de_DE

# Or download manually and extract to 'cms' folder
```

### Step 2: Create Database

**Option A: WP-CLI (Recommended)**
```bash
# Create database
wp db create

# Or with specific name
mysql -u root -p -e "CREATE DATABASE your_database CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

**Option B: phpMyAdmin**

1. Open `http://localhost/phpmyadmin`
2. Click "Databases"
3. Enter database name: `your_database`
4. Collation: `utf8mb4_unicode_ci`
5. Click "Create"

### Step 3: Configure WordPress
```bash
cd cms

# Create wp-config.php
wp core config \
  --dbname=your_database \
  --dbuser=root \
  --dbpass=root \
  --dbprefix=wp_ \
  --locale=de_DE

# For Valet/Local, dbpass might be 'root' or empty
# For XAMPP Windows, dbpass is usually empty
```

### Step 4: Install WordPress
```bash
# Install WordPress
wp core install \
  --url=your-site.test \
  --title="Your Site Title" \
  --admin_user=admin \
  --admin_password=admin123 \
  --admin_email=admin@example.com

# Go back to project root
cd ..
```

**Important:** Change admin password after installation!

---

## 🎨 Theme & Plugin Setup

### Step 1: Install Agency Core Plugin
```bash
# The Agency Core plugin should be in:
cms/wp-content/mu-plugins/agency-core/

# Structure:
cms/wp-content/mu-plugins/
└── agency-core/
    ├── agency-core.php
    ├── inc/
    │   ├── custom-post-types.php
    │   ├── acf-fields.php
    │   ├── shortcodes.php
    │   ├── ajax-search.php
    │   ├── ajax-filters.php
    │   └── ajax-load-more.php
    └── README.md
```

**Verify it's active:**
```bash
cd cms
wp plugin list
# Should show "agency-core" as "Must Use"
cd ..
```

### Step 2: Install Custom Theme
```bash
# Theme should be in:
cms/wp-content/themes/custom-theme/

# Structure:
cms/wp-content/themes/custom-theme/
├── functions.php
├── header.php
├── footer.php
├── index.php
├── search.php
├── assets/
│   ├── src/
│   └── dist/
└── src/
    └── inc/
        └── enqueue.php
```

**Activate theme:**
```bash
cd cms
wp theme activate custom-theme
cd ..
```

### Step 3: Install Advanced Custom Fields Pro

**Option A: Manual Installation**

1. Download ACF Pro from your account
2. Upload to `cms/wp-content/plugins/advanced-custom-fields-pro/`
3. Activate:
```bash
cd cms
wp plugin activate advanced-custom-fields-pro
cd ..
```

**Option B: WP-CLI with License Key**
```bash
cd cms
wp plugin install advanced-custom-fields-pro --activate
# Enter license key when prompted
cd ..
```

### Step 4: Install WooCommerce (Optional)
```bash
cd cms
wp plugin install woocommerce --activate
cd ..
```

---

## 🛠️ Asset Compilation

### Step 1: Install Node Dependencies
```bash
# In project root
npm install
```

This installs:
- Vite (build tool)
- SCSS processor
- JavaScript bundler
- All dependencies

### Step 2: Build Assets

**For Development (with hot-reload):**
```bash
npm run dev
```

Keep this running in a terminal. Changes to SCSS/JS will auto-reload.

**For Production:**
```bash
npm run build
```

This creates optimized files in:
- `cms/wp-content/themes/custom-theme/assets/dist/css/style.css`
- `cms/wp-content/themes/custom-theme/assets/dist/js/main.js`

### Step 3: Verify Assets Loaded

1. Visit `http://your-site.test`
2. Open browser DevTools (F12)
3. Check Console for:
```
   ✅ customTheme loaded: {ajaxUrl: "...", nonce: "..."}
```
4. Check Network tab for:
   - `style.css` (Status: 200)
   - `main.js` (Status: 200)

---

## 💾 Database Configuration

### Import Sample Data (Optional)

If you have a sample database:
```bash
cd cms

# Import SQL file
wp db import path/to/sample-data.sql

# Search-replace URLs (if needed)
wp search-replace 'old-url.com' 'your-site.test'

cd ..
```

### Generate Permalinks
```bash
cd cms

# Set permalink structure
wp rewrite structure '/%postname%/'

# Flush rewrite rules
wp rewrite flush

cd ..
```

### Enable Debugging (Development Only)

Edit `cms/wp-config.php`:
```php
// Before "That's all, stop editing!"

// Enable debugging
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
define('SCRIPT_DEBUG', true);

// Increase memory limit
define('WP_MEMORY_LIMIT', '256M');
define('WP_MAX_MEMORY_LIMIT', '512M');
```

---

## ✅ Post-Installation

### Step 1: Verify Installation

**Check WordPress:**
```bash
cd cms
wp core version
wp plugin list
wp theme list
cd ..
```

**Check Build:**
```bash
ls -la cms/wp-content/themes/custom-theme/assets/dist/
# Should show css/ and js/ directories
```

### Step 2: Create Test Content
```bash
cd cms

# Create test pages
wp post create --post_type=page --post_title='Home' --post_status=publish
wp post create --post_type=page --post_title='About' --post_status=publish
wp post create --post_type=page --post_title='Contact' --post_status=publish

# Create test job
wp post create --post_type=job --post_title='Senior Developer' --post_status=publish

# Create test project
wp post create --post_type=project --post_title='Sample Project' --post_status=publish

cd ..
```

### Step 3: Configure Settings

**General Settings:**
```bash
cd cms

# Set site title and description
wp option update blogname "Your Site Title"
wp option update blogdescription "Your Site Description"

# Set timezone
wp option update timezone_string "Europe/Vienna"

# Set date format
wp option update date_format "d.m.Y"

cd ..
```

**Reading Settings:**

1. Go to `Settings → Reading`
2. Set "Your homepage displays" to "A static page"
3. Choose Homepage: "Home"
4. Save Changes

**Permalink Settings:**

1. Go to `Settings → Permalinks`
2. Choose "Post name" structure
3. Save Changes

### Step 4: Create Test User
```bash
cd cms

# Create editor user
wp user create editor editor@example.com \
  --role=editor \
  --user_pass=editor123 \
  --display_name="Editor User"

cd ..
```

### Step 5: Security Hardening

**Delete default content:**
```bash
cd cms

# Delete default post
wp post delete 1 --force

# Delete default comment
wp comment delete 1 --force

# Delete unused themes (keep custom-theme)
wp theme delete twentytwentythree twentytwentyfour

# Delete unused plugins
wp plugin delete hello akismet

cd ..
```

**Create .htaccess security rules:**

Create `cms/.htaccess`:
```apache
# BEGIN WordPress
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteBase /
RewriteRule ^index\.php$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]
</IfModule>
# END WordPress

# Disable directory browsing
Options -Indexes

# Protect wp-config.php
<files wp-config.php>
order allow,deny
deny from all
</files>

# Protect .htaccess
<files .htaccess>
order allow,deny
deny from all
</files>
```

---

## 🧪 Testing Installation

### Functional Tests

- [ ] Homepage loads correctly
- [ ] Admin dashboard accessible at `/wp-admin`
- [ ] Theme styles are applied
- [ ] JavaScript console shows no errors
- [ ] Custom Post Types appear in admin menu
- [ ] ACF fields appear when editing posts
- [ ] Shortcodes work on pages
- [ ] AJAX search functions
- [ ] AJAX filters function (if used)
- [ ] Media uploads work
- [ ] Permalink structure works

### Performance Tests
```bash
# Check site speed
curl -o /dev/null -s -w 'Total: %{time_total}s\n' http://your-site.test

# Should be under 1 second for local
```

### Browser Tests

Test in:
- [ ] Chrome
- [ ] Firefox
- [ ] Safari
- [ ] Edge

Test on:
- [ ] Desktop
- [ ] Tablet (DevTools)
- [ ] Mobile (DevTools)

---

## 🐛 Troubleshooting

### Issue: White Screen of Death

**Cause:** PHP error, memory limit, or plugin conflict

**Solution:**
```bash
# Enable debugging
# Edit cms/wp-config.php, add:
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

# Check error log
tail -f cms/wp-content/debug.log
```

### Issue: Assets Not Loading

**Cause:** Build not run or permissions issue

**Solution:**
```bash
# Rebuild assets
npm run build

# Check file permissions
chmod -R 755 cms/wp-content/themes/custom-theme/assets/dist

# Clear cache
cd cms && wp cache flush && cd ..
```

### Issue: Database Connection Error

**Cause:** Wrong credentials in wp-config.php

**Solution:**
```bash
cd cms

# Test database connection
wp db check

# If fails, recreate wp-config.php
wp core config \
  --dbname=correct_database \
  --dbuser=correct_user \
  --dbpass=correct_password \
  --force

cd ..
```

### Issue: Permalinks Return 404

**Cause:** .htaccess missing or rewrite rules not flushed

**Solution:**
```bash
cd cms

# Flush rewrite rules
wp rewrite flush

# Regenerate .htaccess
wp rewrite structure '/%postname%/' --hard

cd ..
```

### Issue: customTheme is undefined

**Cause:** enqueue.php not loaded or wrong path

**Solution:**

Check `cms/wp-content/themes/custom-theme/functions.php` line 66:
```php
// Should be:
require_once get_template_directory() . '/src/inc/enqueue.php';
// NOT:
require_once get_template_directory() . '/inc/enqueue.php';
```

### Issue: ACF Fields Not Showing

**Cause:** ACF Pro not activated or field group location rules wrong

**Solution:**
```bash
cd cms

# Check if ACF is active
wp plugin list | grep advanced-custom-fields

# If not active:
wp plugin activate advanced-custom-fields-pro

# Flush rewrite rules
wp rewrite flush

cd ..
```

---

## 🎯 Next Steps

After successful installation:

1. Read [USAGE.md](./USAGE.md) for basic usage
2. Read [SHORTCODES.md](./SHORTCODES.md) for available shortcodes
3. Read [AJAX-FILTERS.md](./AJAX-FILTERS.md) for advanced filtering
4. Configure your content in WordPress admin
5. Start building your site!

---

## 📞 Support

If you encounter issues not covered here:

1. Check [TROUBLESHOOTING.md](./TROUBLESHOOTING.md)
2. Search [GitHub Issues](https://github.com/your-agency/wordpress-starter-kit/issues)
3. Contact support: support@your-agency.com

---

**Installation complete!** 🎉

You're now ready to start building with the WordPress Agency Starter Kit.