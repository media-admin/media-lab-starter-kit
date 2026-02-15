# Troubleshooting Guide

Complete troubleshooting guide for common issues in the WordPress Agency Starter Kit.

---

## 📋 Table of Contents

- [Quick Diagnostics](#quick-diagnostics)
- [Installation Issues](#installation-issues)
- [AJAX Issues](#ajax-issues)
- [Asset Loading Issues](#asset-loading-issues)
- [Custom Post Type Issues](#custom-post-type-issues)
- [ACF Issues](#acf-issues)
- [Shortcode Issues](#shortcode-issues)
- [Filter System Issues](#filter-system-issues)
- [Database Issues](#database-issues)
- [Performance Issues](#performance-issues)
- [Server Issues](#server-issues)
- [Emergency Procedures](#emergency-procedures)

---

## 🔍 Quick Diagnostics

### Step 1: Enable Debug Mode

**File:** `cms/wp-config.php`

Add before `/* That's all, stop editing! */`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
define('SCRIPT_DEBUG', true);
```

**Check debug log:**
```bash
tail -f cms/wp-content/debug.log
```

### Step 2: Browser Console Check

1. Open browser DevTools (F12)
2. Go to Console tab
3. Look for errors (red text)
4. Note error messages

### Step 3: Network Tab Check

1. Open DevTools → Network tab
2. Reload page (F5)
3. Look for failed requests (red)
4. Check status codes (404, 403, 500)

### Step 4: System Health Check
```bash
# Check WordPress version
cd cms && wp core version && cd ..

# Check theme
cd cms && wp theme list && cd ..

# Check plugins
cd cms && wp plugin list && cd ..

# Check database
cd cms && wp db check && cd ..

# Check rewrite rules
cd cms && wp rewrite flush && cd ..
```

---

## 🚨 Installation Issues

### Issue: White Screen of Death (WSOD)

**Symptoms:**
- Blank white page
- No error messages
- Site completely inaccessible

**Causes:**
- PHP syntax error
- Memory limit exceeded
- Plugin conflict
- Theme error

**Solutions:**

**1. Check Error Log:**
```bash
tail -50 cms/wp-content/debug.log
```

**2. Disable Plugins:**
```bash
cd cms
wp plugin deactivate --all
cd ..
```

**3. Switch to Default Theme:**
```bash
cd cms
wp theme activate twentytwentyfour
cd ..
```

**4. Increase Memory Limit:**

Add to `wp-config.php`:
```php
define('WP_MEMORY_LIMIT', '256M');
define('WP_MAX_MEMORY_LIMIT', '512M');
```

**5. Check PHP Syntax:**
```bash
php -l cms/wp-content/themes/custom-theme/functions.php
```

---

### Issue: Database Connection Error

**Symptoms:**
- "Error establishing a database connection"
- Cannot access site or admin

**Causes:**
- Wrong database credentials
- Database server down
- Database corrupted

**Solutions:**

**1. Verify Credentials:**

Check `cms/wp-config.php`:
```php
define('DB_NAME', 'your_database');
define('DB_USER', 'your_user');
define('DB_PASSWORD', 'your_password');
define('DB_HOST', 'localhost'); // or '127.0.0.1'
```

**2. Test Database Connection:**
```bash
cd cms
wp db check
cd ..
```

**3. Restart Database Server:**
```bash
# macOS (Valet)
valet restart

# MySQL
sudo service mysql restart

# Docker
docker-compose restart db
```

**4. Repair Database:**

Add to `wp-config.php`:
```php
define('WP_ALLOW_REPAIR', true);
```

Visit: `http://yoursite.test/wp-admin/maint/repair.php`

**Remember to remove after repair!**

---

### Issue: 404 on All Pages (Except Homepage)

**Symptoms:**
- Homepage works
- All other pages return 404
- Admin area works

**Cause:**
- Permalink structure not set
- `.htaccess` missing or incorrect

**Solutions:**

**1. Flush Rewrite Rules:**
```bash
cd cms
wp rewrite flush
cd ..
```

**2. Regenerate .htaccess:**
```bash
cd cms
wp rewrite structure '/%postname%/' --hard
cd ..
```

**3. Check .htaccess Exists:**
```bash
ls -la cms/.htaccess
```

If missing, create `cms/.htaccess`:
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
```

**4. Check Apache mod_rewrite:**
```bash
# Enable mod_rewrite (Ubuntu/Debian)
sudo a2enmod rewrite
sudo service apache2 restart
```

---

## ⚠️ AJAX Issues

### Issue: 403 Forbidden Error on AJAX Requests

**Symptoms:**
- AJAX request fails with 403
- Console shows: `Failed to load resource: 403`
- Features like search, filters don't work

**Causes:**
- Nonce mismatch
- Nonce not passed
- Action not registered
- CORS issue

**Solutions:**

**1. Check Nonce Exists:**

Browser Console:
```javascript
console.log(window.customTheme);
// Should show object with nonce, filtersNonce, etc.
```

If `undefined`, see [customTheme undefined](#issue-windowcustomtheme-is-undefined)

**2. Verify Nonce Names Match:**

**Frontend (JavaScript):**
```javascript
// In ajax-filters.js
nonce: window.customTheme.filtersNonce
```

**Backend (PHP):**
```php
// In ajax-filters.php
check_ajax_referer('ajax_filters_nonce', 'nonce');
```

**Localization (PHP):**
```php
// In enqueue.php
'filtersNonce' => wp_create_nonce('ajax_filters_nonce')
```

All three must use: `ajax_filters_nonce`

**3. Check Action Registered:**
```php
// Must have both
add_action('wp_ajax_ajax_filter_posts', 'agency_core_ajax_filter_posts');
add_action('wp_ajax_nopriv_ajax_filter_posts', 'agency_core_ajax_filter_posts');
```

**4. Verify AJAX URL:**

Browser Console:
```javascript
console.log(window.customTheme.ajaxUrl);
// Should show: "https://yoursite.test/wp-admin/admin-ajax.php"
```

**5. Test AJAX Endpoint:**
```bash
curl -X POST https://yoursite.test/wp-admin/admin-ajax.php \
  -d "action=ajax_filter_posts" \
  -d "nonce=test"
```

---

### Issue: window.customTheme is undefined

**Symptoms:**
- Console shows: `Cannot read property 'ajaxUrl' of undefined`
- AJAX features don't work
- Scripts fail

**Causes:**
- Script not enqueued
- `wp_localize_script` not called
- Wrong script handle
- `defer` attribute issue
- enqueue.php not loaded

**Solutions:**

**1. Check enqueue.php Path:**

**File:** `cms/wp-content/themes/custom-theme/functions.php`

Should have:
```php
require_once get_template_directory() . '/src/inc/enqueue.php';
// NOT: require_once get_template_directory() . '/inc/enqueue.php';
```

**2. Verify Script Enqueued:**
```bash
cd cms
wp eval 'global $wp_scripts; print_r($wp_scripts->registered);'
cd ..
```

Look for `custom-theme-script`.

**3. Check Localization:**

**File:** `cms/wp-content/themes/custom-theme/src/inc/enqueue.php`

Should have:
```php
wp_add_inline_script('custom-theme-script', 
    'window.customTheme = ' . json_encode(array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('custom-theme-nonce'),
        'searchNonce' => wp_create_nonce('agency_search_nonce'),
        'filtersNonce' => wp_create_nonce('ajax_filters_nonce'),
    )) . ';',
    'before' // IMPORTANT: before, not after!
);
```

**4. Check Page Source:**

View page source (Ctrl+U), search for `customTheme`.

Should find:
```html
<script>
window.customTheme = {"ajaxUrl":"...","nonce":"...","filtersNonce":"..."};
</script>
```

If not found, localization not working.

**5. Clear All Caches:**
```bash
# WordPress cache
cd cms && wp cache flush && cd ..

# Build assets
npm run build

# Browser cache
# Chrome: Ctrl+Shift+Delete
```

---

### Issue: AJAX Request Returns Empty Response

**Symptoms:**
- No 403 error
- Request succeeds (200)
- But response is empty or "0"

**Causes:**
- PHP error before output
- `die()` not called
- Function doesn't exist
- Wrong return format

**Solutions:**

**1. Check Debug Log:**
```bash
tail -20 cms/wp-content/debug.log
```

**2. Add Debug Output:**
```php
function agency_core_ajax_filter_posts() {
    error_log('AJAX function called!');
    
    // Your code
    
    error_log('Sending response...');
    wp_send_json($response);
}
```

**3. Verify wp_send_json Usage:**
```php
// Correct
wp_send_json(array(
    'success' => true,
    'data' => $data
));

// Also correct
wp_send_json_success($data);
wp_send_json_error($error);

// Wrong - will return "0"
echo json_encode($data);
die();
```

**4. Check for Early die():**
```php
// Remove any die() before wp_send_json
// die(); // ← Remove this
wp_send_json($response);
```

---

## 📦 Asset Loading Issues

### Issue: CSS/JS Files Not Loading (404)

**Symptoms:**
- No styling on site
- JavaScript doesn't work
- Console shows 404 errors

**Causes:**
- Assets not built
- Wrong file path
- Incorrect enqueue path

**Solutions:**

**1. Build Assets:**
```bash
npm run build
```

**2. Check Files Exist:**
```bash
ls -la cms/wp-content/themes/custom-theme/assets/dist/css/
ls -la cms/wp-content/themes/custom-theme/assets/dist/js/
```

Should see `style.css` and `main.js`.

**3. Verify Enqueue Path:**

**File:** `cms/wp-content/themes/custom-theme/src/inc/enqueue.php`
```php
wp_enqueue_style(
    'custom-theme-style',
    get_template_directory_uri() . '/assets/dist/css/style.css',
    array(),
    filemtime(get_template_directory() . '/assets/dist/css/style.css')
);
```

**4. Check Permissions:**
```bash
chmod -R 755 cms/wp-content/themes/custom-theme/assets/dist
```

---

### Issue: Changes to SCSS/JS Not Showing

**Symptoms:**
- Edit files but no changes visible
- Old styles still showing
- Old JavaScript still running

**Causes:**
- Assets not rebuilt
- Browser cache
- WordPress cache
- CDN cache

**Solutions:**

**1. Rebuild Assets:**
```bash
npm run build
```

**2. Hard Refresh Browser:**

- Chrome/Firefox: `Ctrl + Shift + R` (Windows/Linux)
- Chrome/Firefox: `Cmd + Shift + R` (Mac)
- Safari: `Cmd + Option + R`

**3. Clear WordPress Cache:**
```bash
cd cms
wp cache flush
cd ..
```

**4. Disable Caching Plugin:**

Temporarily deactivate WP Rocket, W3 Total Cache, etc.

**5. Check Filemtime:**

Enqueue should use `filemtime()` for cache busting:
```php
wp_enqueue_style(
    'custom-theme-style',
    get_template_directory_uri() . '/assets/dist/css/style.css',
    array(),
    filemtime(get_template_directory() . '/assets/dist/css/style.css') // ← Cache buster
);
```

---

### Issue: Build Process Fails

**Symptoms:**
- `npm run build` throws errors
- `npm run dev` doesn't start
- Module not found errors

**Solutions:**

**1. Delete and Reinstall:**
```bash
rm -rf node_modules
rm package-lock.json
npm install
```

**2. Check Node Version:**
```bash
node -v
# Should be 18.0.0 or higher
```

If wrong version:
```bash
# Install nvm (Node Version Manager)
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.0/install.sh | bash

# Install correct Node version
nvm install 18
nvm use 18
```

**3. Check package.json:**
```bash
cat package.json
# Verify scripts section exists
```

**4. Clear npm Cache:**
```bash
npm cache clean --force
npm install
```

---

## 🏗️ Custom Post Type Issues

### Issue: Custom Post Type Not Showing in Admin

**Symptoms:**
- CPT menu missing from admin sidebar
- Can't create posts of that type
- Frontend might still work

**Causes:**
- CPT not registered
- Registration code not running
- `init` hook not used
- Wrong capability type

**Solutions:**

**1. Check Registration:**
```bash
cd cms
wp post-type list
cd ..
```

Should show your custom post type.

**2. Verify init Hook:**
```php
// Correct
add_action('init', 'agency_core_register_job_cpt');

// Wrong
agency_core_register_job_cpt(); // Called too early
```

**3. Flush Rewrite Rules:**
```bash
cd cms
wp rewrite flush
cd ..
```

**4. Check show_ui Parameter:**
```php
register_post_type('job', array(
    'show_ui' => true, // Must be true
    'show_in_menu' => true, // Must be true
    // ...
));
```

**5. Check Capability:**
```php
'capability_type' => 'post', // Standard capabilities
```

---

### Issue: CPT Posts Return 404

**Symptoms:**
- Can create posts in admin
- But clicking "View" returns 404
- Archive page also 404

**Cause:**
- Rewrite rules not flushed

**Solution:**
```bash
cd cms
wp rewrite flush
cd ..
```

After flushing, visit a post again.

---

### Issue: CPT Taxonomy Not Showing Terms

**Symptoms:**
- Taxonomy registered
- But no terms show in admin
- Can't assign terms to posts

**Solutions:**

**1. Check Taxonomy Registration:**
```bash
cd cms
wp taxonomy list
cd ..
```

**2. Verify Object Type:**
```php
register_taxonomy('job_category', array('job'), $args);
//                                      ^^^^^^ Must match CPT slug
```

**3. Check show_ui:**
```php
register_taxonomy('job_category', array('job'), array(
    'show_ui' => true, // Must be true
    'show_admin_column' => true,
    // ...
));
```

**4. Flush Rewrite Rules:**
```bash
cd cms
wp rewrite flush
cd ..
```

---

## 🎨 ACF Issues

### Issue: ACF Fields Not Showing

**Symptoms:**
- Edit post but no custom fields
- Field group exists but fields missing
- Other field groups work

**Causes:**
- Location rules wrong
- Field group not published
- ACF not activated
- Template path mismatch

**Solutions:**

**1. Check ACF is Active:**
```bash
cd cms
wp plugin list | grep advanced-custom-fields
cd ..
```

Should show `active`.

**2. Check Location Rules:**

**File:** `cms/wp-content/mu-plugins/agency-core/inc/acf-fields.php`
```php
'location' => array(
    array(
        array(
            'param' => 'post_type',
            'operator' => '==',
            'value' => 'job', // Must match exact post type slug
        ),
    ),
),
```

**3. Check Field Group Status:**

Go to **Custom Fields** in admin, verify field group is published.

**4. For Page Templates:**

Location rule must match EXACT template path:
```php
// If template is: templates/template-landing.php
'value' => 'templates/template-landing.php', // Include folder!

// NOT:
'value' => 'template-landing.php', // Wrong!
```

---

### Issue: ACF get_field() Returns Empty

**Symptoms:**
- Fields show in admin
- Can save values
- But `get_field()` returns nothing

**Solutions:**

**1. Check Field Name:**
```php
// Must match exactly (case-sensitive)
$value = get_field('field_name'); // Check spelling
```

**2. Pass Post ID:**
```php
// Outside the loop
$value = get_field('field_name', $post_id);
```

**3. Check Return Format:**

For image fields:
```php
// If return format is 'url'
$image = get_field('image'); // Returns string URL

// If return format is 'array'
$image = get_field('image'); // Returns array
$url = $image['url'];
```

**4. Verify Field Saved:**
```bash
cd cms
wp post meta list POST_ID
cd ..
```

Should show your field name.

---

### Issue: ACF Repeater Not Working

**Symptoms:**
- Can add rows in admin
- But `have_rows()` returns false
- Or rows don't display

**Cause:**
- ACF Pro not active (Repeater is Pro feature)
- Wrong field name
- Missing post ID

**Solutions:**

**1. Check ACF Pro:**
```bash
cd cms
wp plugin list | grep advanced-custom-fields-pro
cd ..
```

**2. Correct Usage:**
```php
if (have_rows('repeater_field')) {
    while (have_rows('repeater_field')) {
        the_row();
        $text = get_sub_field('text'); // Use get_sub_field
        $image = get_sub_field('image');
    }
}
```

**3. Outside the Loop:**
```php
if (have_rows('repeater_field', $post_id)) {
    while (have_rows('repeater_field', $post_id)) {
        the_row();
        // ...
    }
}
```

---

## 📝 Shortcode Issues

### Issue: Shortcode Displays as Text

**Symptoms:**
- `[shortcode]` shows on page
- Not processed
- Just plain text

**Causes:**
- Shortcode not registered
- Wrong shortcode name
- Typographic quotes

**Solutions:**

**1. Check Shortcode Registered:**
```php
// In shortcodes.php, must have:
add_shortcode('my_shortcode', 'my_shortcode_function');
```

**2. Check Shortcode Name:**
```
[my_shortcode] ← Correct
[my-shortcode] ← If registered with hyphen
[myShortcode] ← Wrong (case matters!)
```

**3. Fix Typographic Quotes:**

**Wrong:**
```
[shortcode param="value"]  // Fancy quotes
```

**Correct:**
```
[shortcode param="value"]  // Normal quotes
```

Our system auto-fixes this, but check if disabled.

**4. Verify File Loaded:**

Check `cms/wp-content/mu-plugins/agency-core/agency-core.php`:
```php
require_once AGENCY_CORE_PATH . 'inc/shortcodes.php';
```

---

### Issue: Nested Shortcodes Not Working

**Symptoms:**
- Parent shortcode works
- Child shortcodes show as text
- Inner content not processed

**Solution:**

Use `do_shortcode()` on content:
```php
function parent_shortcode($atts, $content = null) {
    $output = '<div class="parent">';
    $output .= do_shortcode($content); // Process nested shortcodes
    $output .= '</div>';
    return $output;
}
```

---

## 🔍 Filter System Issues

### Issue: Filters Not Returning Results

**Symptoms:**
- Filters UI works
- Loading spinner shows
- But "0 Results" displays
- Yet posts exist

**Solutions:**

**1. Check Post Type:**
```bash
cd cms
wp post list --post_type=job --post_status=publish
cd ..
```

**2. Check Taxonomy Slugs:**
```bash
cd cms
wp term list job_category
cd ..
```

Verify slug matches exactly in filter.

**3. Check Meta Keys:**
```bash
cd cms
wp post meta list POST_ID
cd ..
```

For range filters, check meta key exists and is numeric.

**4. Test Query Manually:**

Add to `functions.php` temporarily:
```php
add_action('init', function() {
    $query = new WP_Query(array(
        'post_type' => 'job',
        'posts_per_page' => 5,
    ));
    error_log('Found: ' . $query->found_posts);
});
```

Check debug log for count.

---

### Issue: Range Slider Not Filtering

**Symptoms:**
- Sliders move
- Values update
- But results don't change

**Solutions:**

**1. Check Meta Values Are Numeric:**
```bash
cd cms
wp post meta list POST_ID
cd ..
```

Should show numeric values like `50000`, not text like `50k`.

**2. Verify Meta Key Name:**
```
[filter_range key="salary_min" ...]
```

Key must match exactly (case-sensitive).

**3. Check ACF Field Type:**

Field type must be "Number", not "Text".

**4. Test Meta Query:**
```php
$args = array(
    'post_type' => 'job',
    'meta_query' => array(
        array(
            'key' => 'salary_min',
            'value' => array(30000, 80000),
            'compare' => 'BETWEEN',
            'type' => 'NUMERIC',
        ),
    ),
);
$query = new WP_Query($args);
error_log('Results: ' . $query->found_posts);
```

---

### Issue: Active Filter Tags Not Removable

**Symptoms:**
- Filters applied
- Tags show
- But clicking X doesn't remove

**Solutions:**

**1. Check Data Attributes:**

Tags should have:
```html
<button data-taxonomy="category" data-value="news">
```

**2. Verify Event Binding:**

In `ajax-filters.js`, check:
```javascript
this.activeFiltersList.querySelectorAll('.ajax-filters__active-tag')
    .forEach(tag => {
        tag.addEventListener('click', (e) => this.removeActiveFilter(e));
    });
```

**3. Rebuild JavaScript:**
```bash
npm run build
```

---

## 💾 Database Issues

### Issue: Database Connection Lost

**Symptoms:**
- "Error establishing database connection"
- Site worked, then stopped
- No recent changes

**Solutions:**

**1. Check MySQL Running:**
```bash
# macOS
brew services list | grep mysql

# Linux
sudo service mysql status

# Docker
docker-compose ps
```

**2. Restart MySQL:**
```bash
# macOS
brew services restart mysql

# Linux
sudo service mysql restart

# Docker
docker-compose restart db
```

**3. Check Disk Space:**
```bash
df -h
```

MySQL needs space to run.

**4. Check MySQL Logs:**
```bash
# macOS
tail -50 /usr/local/var/mysql/*.err

# Linux
tail -50 /var/log/mysql/error.log
```

---

### Issue: Database Tables Missing

**Symptoms:**
- Fresh install
- But some features broken
- "Table doesn't exist" errors

**Solutions:**

**1. Check Tables Exist:**
```bash
cd cms
wp db tables
cd ..
```

**2. Reinstall WordPress Tables:**
```bash
cd cms
wp core install --skip-config
cd ..
```

**3. Run Database Repair:**

Add to `wp-config.php`:
```php
define('WP_ALLOW_REPAIR', true);
```

Visit: `http://yoursite.test/wp-admin/maint/repair.php`

---

## ⚡ Performance Issues

### Issue: Site Very Slow

**Symptoms:**
- Pages take 5+ seconds to load
- Admin dashboard slow
- Database queries slow

**Solutions:**

**1. Check Plugin Query Performance:**

Install Query Monitor plugin:
```bash
cd cms
wp plugin install query-monitor --activate
cd ..
```

View slow queries in admin bar.

**2. Enable Object Caching:**
```bash
cd cms
wp plugin install redis-cache --activate
wp redis enable
cd ..
```

**3. Optimize Database:**
```bash
cd cms
wp db optimize
cd ..
```

**4. Check for Heavy Plugins:**
```bash
cd cms
wp plugin deactivate --all
cd ..
```

Reactivate one by one to find culprit.

**5. Increase PHP Memory:**

`wp-config.php`:
```php
define('WP_MEMORY_LIMIT', '512M');
```

---

### Issue: High CPU Usage

**Solutions:**

**1. Check WP-Cron:**

Disable WP-Cron and use system cron:

`wp-config.php`:
```php
define('DISABLE_WP_CRON', true);
```

Add to system crontab:
```bash
*/15 * * * * cd /path/to/cms && wp cron event run --due-now
```

**2. Limit Post Revisions:**

`wp-config.php`:
```php
define('WP_POST_REVISIONS', 3);
```

**3. Clean Up Transients:**
```bash
cd cms
wp transient delete --all
cd ..
```

---

## 🖥️ Server Issues

### Issue: 500 Internal Server Error

**Symptoms:**
- "500 Internal Server Error"
- White page
- No details shown

**Solutions:**

**1. Check Error Logs:**
```bash
# Apache
tail -50 /var/log/apache2/error.log

# Nginx
tail -50 /var/log/nginx/error.log

# PHP
tail -50 /var/log/php/error.log
```

**2. Check .htaccess:**

Rename temporarily:
```bash
mv cms/.htaccess cms/.htaccess.bak
```

If site works, `.htaccess` has syntax error.

**3. Check PHP Version:**
```bash
php -v
```

Must be 8.0+.

**4. Check PHP Extensions:**
```bash
php -m
```

Required: mysqli, gd, curl, mbstring, xml

---

### Issue: Upload Limit Too Small

**Symptoms:**
- "File exceeds maximum upload size"
- Can't upload large images
- Import fails

**Solutions:**

**1. Check Current Limits:**
```bash
cd cms
wp eval 'echo "Upload max: " . ini_get("upload_max_filesize") . "\n";'
wp eval 'echo "Post max: " . ini_get("post_max_size") . "\n";'
cd ..
```

**2. Increase via wp-config.php:**
```php
@ini_set('upload_max_filesize', '64M');
@ini_set('post_max_size', '64M');
@ini_set('max_execution_time', '300');
```

**3. Increase via .htaccess:**
```apache
php_value upload_max_filesize 64M
php_value post_max_size 64M
php_value max_execution_time 300
php_value max_input_time 300
```

**4. Increase via php.ini:**
```ini
upload_max_filesize = 64M
post_max_size = 64M
max_execution_time = 300
```

Restart web server after changes.

---

### Issue: Cache Plugin Breaks AJAX Features

**Symptoms:**
- AJAX search not working
- Filters not updating
- Load more button fails
- After installing WP Fastest Cache

**Causes:**
- JS minification breaking code
- JS combination breaking load order
- admin-ajax.php being cached

**Solutions:**

**1. Disable JS Optimization:**

Go to **Settings → WP Fastest Cache**
- ☐ Minify JS (disable)
- ☐ Combine JS (disable)
- Click Submit

**2. Exclude admin-ajax.php:**

Go to **Exclude** tab:
```
/wp-admin/admin-ajax.php
```

**3. Exclude specific pages with AJAX:**

If you have a Jobs page with filters at `/jobs`:
```
/jobs/
```

**4. Programmatic Exclusion:**

Add to `functions.php`:
```php
/**
 * Exclude pages from WP Fastest Cache
 */
add_filter('wpfc_exclude_current_page', function($exclude) {
    // Exclude pages with AJAX features
    if (is_page('jobs') || is_page('projects') || is_post_type_archive('job')) {
        return true;
    }
    return $exclude;
});
```

**5. Test thoroughly:**
```bash
# Clear cache
cd cms
wp cache flush
wp eval 'do_action("wpfc_delete_cache");'
cd ..

# Test each AJAX feature
# - Search
# - Filters
# - Load More
# - Forms
```

**6. Alternative - Exclude JS files:**

If only specific JS files break:

In WP Fastest Cache → **Exclude** tab → **Exclude JS**:
```
/wp-content/themes/custom-theme/assets/dist/js/main.js
```

---

### Issue: WP Fastest Cache vs. Other Plugins

**Symptoms:**
- Plugin conflicts
- Features not working after cache activation

**Common Conflicts:**

**1. Contact Form 7:**
- Solution: Exclude form pages from cache

**2. WooCommerce:**
- Solution: Exclude cart, checkout, my-account (done by default)

**3. Custom AJAX:**
- Solution: Exclude admin-ajax.php and specific pages

**4. Page Builders (Elementor, etc.):**
- Usually no conflict, but clear cache after editing

**Prevention:**
```php
// In wp-config.php, temporarily disable cache during development
define('WP_CACHE', false);

// Re-enable for production
define('WP_CACHE', true);
```


## 🆘 Emergency Procedures

### Complete Site Failure

**Step 1: Put Site in Maintenance Mode**

Create `cms/.maintenance`:
```php
<?php $upgrading = time(); ?>
```

**Step 2: Backup Current State**
```bash
# Backup database
cd cms
wp db export backup-$(date +%Y%m%d-%H%M%S).sql
cd ..

# Backup files
tar -czf backup-$(date +%Y%m%d-%H%M%S).tar.gz cms/wp-content
```

**Step 3: Disable All Plugins**
```bash
cd cms
wp plugin deactivate --all
cd ..
```

**Step 4: Switch to Default Theme**
```bash
cd cms
wp theme activate twentytwentyfour
cd ..
```

**Step 5: Test Site**

If site works, problem is in theme or plugin.

**Step 6: Restore Gradually**
```bash
# Activate plugins one by one
cd cms
wp plugin activate plugin-name
cd ..

# Test after each activation
```

**Step 7: Remove Maintenance Mode**
```bash
rm cms/.maintenance
```

---

### Lost Admin Access

**Reset Admin Password via WP-CLI:**
```bash
cd cms
wp user update admin --user_pass=newpassword
cd ..
```

**Reset via Database:**
```bash
cd cms
wp db query "UPDATE wp_users SET user_pass=MD5('newpassword') WHERE user_login='admin';"
cd ..
```

**Create New Admin User:**
```bash
cd cms
wp user create emergency emergency@example.com --role=administrator --user_pass=temppass
cd ..
```

---

### Database Corruption

**Step 1: Backup Immediately**
```bash
cd cms
wp db export backup-corrupted-$(date +%Y%m%d-%H%M%S).sql
cd ..
```

**Step 2: Check Tables:**
```bash
cd cms
wp db check
cd ..
```

**Step 3: Repair Tables:**
```bash
cd cms
wp db repair
cd ..
```

**Step 4: Optimize:**
```bash
cd cms
wp db optimize
cd ..
```

---

## 📞 Getting Help

### Before Asking for Help

Collect this information:

1. **Error Message:** Exact text
2. **When It Started:** After what change?
3. **Browser/Device:** What are you using?
4. **WordPress Version:** `wp core version`
5. **PHP Version:** `php -v`
6. **Theme Version:** Check functions.php
7. **Active Plugins:** `wp plugin list --status=active`
8. **Error Log:** Last 20 lines
9. **What You've Tried:** List troubleshooting steps

### Support Channels

**Internal:**
- Check documentation first
- Ask team members
- Check internal wiki
- Create ticket in project management

**External:**
- WordPress Support Forums
- Stack Overflow
- GitHub Issues
- Plugin support

### Emergency Contacts

**Critical Issues (Site Down):**
- Lead Developer: dev@your-agency.com
- Phone: +XX XXX XXX XXXX

**Non-Critical:**
- Support: support@your-agency.com
- Documentation: docs@your-agency.com

---

## 🔧 Diagnostic Tools

### Useful Commands
```bash
# System health check
cd cms && wp doctor check --all && cd ..

# Find slow queries
cd cms && wp query monitor && cd ..

# Check file permissions
find cms -type f -not -perm 644

# Check directory permissions
find cms -type d -not -perm 755

# Find large files
du -h cms/wp-content/uploads | sort -rh | head -20

# Check disk space
df -h

# Check memory usage
free -m

# Check running processes
top -n 1
```

### Browser DevTools

**Console Commands:**
```javascript
// Check customTheme
console.log(window.customTheme);

// Check jQuery
console.log(jQuery);

// Check for duplicate IDs
document.querySelectorAll('[id]').forEach(el => {
    const id = el.id;
    if (document.querySelectorAll(`#${id}`).length > 1) {
        console.warn('Duplicate ID:', id);
    }
});

// Test AJAX endpoint
fetch(window.customTheme.ajaxUrl + '?action=test')
    .then(r => r.json())
    .then(console.log);
```

---

## ✅ Prevention Checklist

### Before Deploying

- [ ] Test on staging environment
- [ ] Run `npm run build`
- [ ] Clear all caches
- [ ] Test all features
- [ ] Check console for errors
- [ ] Test on multiple browsers
- [ ] Test on mobile devices
- [ ] Backup database
- [ ] Document changes
- [ ] Have rollback plan

### Regular Maintenance

**Daily:**
- [ ] Check error logs
- [ ] Monitor uptime
- [ ] Check backup completed

**Weekly:**
- [ ] Update plugins
- [ ] Review security logs
- [ ] Test backups
- [ ] Check disk space

**Monthly:**
- [ ] Update WordPress core
- [ ] Optimize database
- [ ] Review analytics
- [ ] Update documentation
- [ ] Security audit

---

**Remember: Most issues have simple solutions. Stay calm, check logs, and work through systematically!** 🔧

---

**Last Updated:** February 2026  
**Version:** 1.0.0