# Caching Strategy Guide

Complete guide for caching setup and optimization in the WordPress Agency Starter Kit.

---

## 📋 Table of Contents

- [Caching Overview](#caching-overview)
- [WP Fastest Cache Setup](#wp-fastest-cache-setup)
- [Redis Object Cache](#redis-object-cache)
- [Cloudflare CDN](#cloudflare-cdn)
- [Performance Testing](#performance-testing)
- [Troubleshooting](#troubleshooting)
- [Advanced Optimization](#advanced-optimization)

---

## 🎯 Caching Overview

### Why WP Fastest Cache?

**Chosen as default because:**
- ✅ Simple, clean interface
- ✅ Essential features in free version
- ✅ CSS/HTML/JS minification included
- ✅ Combine CSS files
- ✅ Client-friendly (non-technical users can manage)
- ✅ Fast setup (5 minutes)
- ✅ Good performance (90+ Lighthouse scores)
- ✅ Active development & support

**vs. Alternatives:**

| Feature | WP Fastest Cache | WP Super Cache | WP Rocket |
|---------|------------------|----------------|-----------|
| **Price** | Free | Free | €59/year |
| **Minification** | ✅ | ❌ | ✅ |
| **Combine CSS** | ✅ | ❌ | ✅ |
| **Ease of Use** | ⭐⭐⭐⭐⭐ | ⭐⭐⭐ | ⭐⭐⭐⭐⭐ |
| **Setup Time** | 5 min | 20 min | 5 min |
| **Critical CSS** | ❌ | ❌ | ✅ |
| **LazyLoad** | ❌ | ❌ | ✅ |

---

## ⚙️ WP Fastest Cache Setup

### Installation
```bash
# Via WP-CLI
cd cms
wp plugin install wp-fastest-cache --activate
cd ..

# Or via WordPress Admin
# Plugins → Add New → Search "WP Fastest Cache" → Install → Activate
```

### Recommended Configuration

**Settings → WP Fastest Cache → Settings Tab**

#### Basic Settings
```
☑ Cache System
  └─ Enable caching

☑ Cache Timeout
  └─ Timeout: 43200 (12 hours)
  └─ Automatically clears cache every 12 hours

☑ Preload
  └─ ☑ Homepage
  └─ ☑ Posts
  └─ ☑ Pages
  └─ ☑ Categories
  └─ ☑ Tags
  └─ Creates cache files in advance

☑ New Post
  └─ Clear all cache when new post published

☑ Update Post
  └─ Clear only this post's cache when updated
```

#### Optimization Settings
```
☑ Minify HTML
  └─ Removes unnecessary spaces, comments

☑ Minify CSS
  └─ Minifies CSS files

☑ Combine CSS
  └─ Combines multiple CSS files into one
  └─ Reduces HTTP requests

☐ Minify JS
  └─ DISABLE - Can break AJAX features
  └─ Your Vite build already minifies JS

☐ Combine JS
  └─ DISABLE - Can break load order
  └─ Can cause conflicts with jQuery

☑ Gzip
  └─ Compresses files (70-90% reduction)

☑ Browser Caching
  └─ Leverages browser cache
  └─ Sets expiry headers

☑ Disable Emojis
  └─ Removes WordPress emoji JS
  └─ Saves ~16KB + 1 HTTP request
```

#### Mobile Settings
```
☑ Mobile
  └─ Enable mobile cache

☑ Mobile Theme
  └─ If you have separate mobile theme
  └─ Leave unchecked for responsive themes
```

### Exclude Settings

**Settings → WP Fastest Cache → Exclude Tab**

#### Exclude Pages

Add these URLs (one per line):
```
/wp-admin/
/wp-login.php
/cart/
/checkout/
/my-account/
/admin-ajax.php
```

If you have AJAX filter pages:
```
/jobs/
/projects/
```

#### Exclude CSS

Usually not needed. Only exclude if specific CSS file causes issues.

#### Exclude JS

Exclude if specific JS breaks when minified:
```
/wp-content/themes/custom-theme/assets/dist/js/main.js
```

Or third-party scripts:
```
/wp-content/plugins/contact-form-7/
```

#### Exclude User Agents

For bot testing:
```
bot
crawler
spider
```

#### Exclude Cookies

For logged-in users or cart functionality:
```
wordpress_logged_in_
woocommerce_items_in_cart
```

---

## 🔴 Redis Object Cache

### What is Object Cache?

**Page Cache (WP Fastest Cache):**
- Caches entire HTML pages
- Served directly to visitors
- Fastest possible delivery

**Object Cache (Redis):**
- Caches database queries
- Caches WordPress transients
- Reduces database load

**Combined = Maximum Performance**

### Prerequisites
```bash
# Check if Redis is installed
redis-cli ping
# Should return: PONG

# If not installed:
sudo apt update
sudo apt install redis-server php-redis

# Start Redis
sudo systemctl start redis-server
sudo systemctl enable redis-server
```

### Installation
```bash
cd cms

# Install plugin
wp plugin install redis-cache --activate

# Enable Redis
wp redis enable

# Check status
wp redis status

# Should show:
# Status: Connected
# Client: PhpRedis
```

### Configuration

**wp-config.php additions:**
```php
// Enable object cache
define('WP_CACHE', true);

// Redis settings
define('WP_REDIS_HOST', 'localhost');
define('WP_REDIS_PORT', 6379);
define('WP_REDIS_TIMEOUT', 1);
define('WP_REDIS_READ_TIMEOUT', 1);
define('WP_REDIS_DATABASE', 0);

// Optional: Set prefix for multiple sites
define('WP_CACHE_KEY_SALT', 'yoursite.com');

// Optional: Password (if Redis password set)
// define('WP_REDIS_PASSWORD', 'your-password');

// Optional: Max TTL
define('WP_REDIS_MAXTTL', 86400); // 24 hours
```

### Verify Redis is Working
```bash
# Check Redis info
redis-cli info stats

# Monitor Redis in real-time
redis-cli monitor

# Check cache keys
redis-cli keys '*'

# Flush Redis (if needed)
redis-cli flushall
```

**In WordPress:**

Go to **Settings → Redis**
- Should show "Connected"
- Shows cache hits/misses ratio
- Click "Flush Object Cache" to test

---

## ☁️ Cloudflare CDN

### Setup Cloudflare (Free)

**1. Create Account:**
- Visit https://cloudflare.com
- Sign up (free plan)

**2. Add Site:**
- Click "Add a Site"
- Enter your domain
- Choose Free plan

**3. Update Nameservers:**
- Cloudflare provides nameservers
- Update at your domain registrar
- Wait for DNS propagation (up to 48h)

**4. Configure Settings:**

**SSL/TLS:**
```
Encryption Mode: Full (Strict)
Always Use HTTPS: On
Automatic HTTPS Rewrites: On
```

**Speed → Optimization:**
```
Auto Minify:
  ☑ JavaScript
  ☑ CSS
  ☑ HTML

Brotli: On
Rocket Loader: Off (can conflict with jQuery)
```

**Caching:**
```
Caching Level: Standard
Browser Cache TTL: 4 hours
Always Online: On
Development Mode: Off (turn on during dev)
```

**Page Rules (Free: 3 rules):**

Rule 1 - Cache Everything:
```
URL: yoursite.com/*
Settings:
  - Cache Level: Cache Everything
  - Edge Cache TTL: 1 month
```

Rule 2 - Bypass Admin:
```
URL: yoursite.com/wp-admin/*
Settings:
  - Cache Level: Bypass
```

Rule 3 - Bypass Cart/Checkout:
```
URL: yoursite.com/cart/*
Settings:
  - Cache Level: Bypass
```

### Clear Cloudflare Cache

**Via Dashboard:**
1. Go to Cloudflare Dashboard
2. Caching → Configuration
3. Click "Purge Everything"

**Via API:**
```bash
# Get API Key from Cloudflare Dashboard → My Profile → API Tokens

# Purge all cache
curl -X POST "https://api.cloudflare.com/client/v4/zones/YOUR_ZONE_ID/purge_cache" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Content-Type: application/json" \
  --data '{"purge_everything":true}'
```

---

## 📊 Performance Testing

### Tools

**1. GTmetrix:**
- https://gtmetrix.com
- Test from multiple locations
- Detailed waterfall analysis

**2. Google PageSpeed Insights:**
- https://pagespeed.web.dev
- Core Web Vitals
- Mobile & Desktop scores

**3. WebPageTest:**
- https://webpagetest.org
- Advanced testing
- Filmstrip view

**4. Lighthouse (Chrome DevTools):**
```bash
# Via Chrome DevTools
# F12 → Lighthouse → Run

# Or CLI
npx lighthouse https://yoursite.com --view
```

### Target Metrics

**Good Performance:**
```
Lighthouse Score:        90+
Load Time:              < 2 seconds
Time to First Byte:     < 200ms
First Contentful Paint: < 1.5 seconds
Largest Contentful Paint: < 2.5 seconds
Total Blocking Time:    < 200ms
Cumulative Layout Shift: < 0.1
```

**Before vs After Caching:**

| Metric | Before | After | Improvement |
|--------|--------|-------|-------------|
| Load Time | 3.2s | 0.8s | 75% faster |
| TTFB | 800ms | 120ms | 85% faster |
| Page Size | 2.5MB | 1.2MB | 52% smaller |
| Requests | 45 | 32 | 29% fewer |
| Lighthouse | 65 | 94 | +29 points |

### Testing Workflow

**1. Before Optimization:**
```bash
# Clear all caches
wp cache flush
wp eval 'do_action("wpfc_delete_cache");'

# Test
npx lighthouse https://yoursite.test --view
# Note baseline scores
```

**2. Enable WP Fastest Cache:**
```bash
# Configure settings (see above)
# Clear cache again
# Test
npx lighthouse https://yoursite.test --view
# Compare scores
```

**3. Add Redis:**
```bash
wp redis enable
# Test again
# Compare improvement
```

**4. Add Cloudflare:**
```bash
# Configure Cloudflare
# Wait 5 minutes for propagation
# Test from GTmetrix (real external test)
# Final comparison
```

---

## 🐛 Troubleshooting

### Issue: Cache Not Working

**Check if cache is active:**
```bash
# Via WP-CLI
wp plugin list | grep wp-fastest-cache
# Should show: active

# Check if files are cached
ls -la cms/wp-content/cache/all/
# Should show cached HTML files
```

**Test cache headers:**
```bash
curl -I https://yoursite.com | grep -i cache
# Should show:
# x-cache: HIT
# cache-control: max-age=...
```

**Common fixes:**
```bash
# 1. Clear cache
wp eval 'do_action("wpfc_delete_cache");'

# 2. Check permissions
sudo chown -R www-data:www-data cms/wp-content/cache
sudo chmod -R 755 cms/wp-content/cache

# 3. Recreate cache folder
sudo rm -rf cms/wp-content/cache/all
sudo mkdir -p cms/wp-content/cache/all
sudo chown -R www-data:www-data cms/wp-content/cache
```

### Issue: AJAX Features Broken

**Symptoms:**
- Search doesn't work
- Filters don't update
- Load more button fails

**Solution:**
```php
// In functions.php
add_filter('wpfc_exclude_current_page', function($exclude) {
    // Exclude AJAX pages
    if (is_page('jobs') || is_page('projects')) {
        return true;
    }
    
    // Exclude AJAX requests
    if (defined('DOING_AJAX') && DOING_AJAX) {
        return true;
    }
    
    return $exclude;
});
```

**Or in WP Fastest Cache settings:**

Exclude → Pages:
```
/wp-admin/admin-ajax.php
/jobs/
/projects/
```

### Issue: Mixed Content Warnings

**After enabling SSL:**
```bash
# Search-replace HTTP to HTTPS
wp search-replace 'http://yoursite.com' 'https://yoursite.com' --all-tables

# Update options
wp option update home 'https://yoursite.com'
wp option update siteurl 'https://yoursite.com'

# Clear cache
wp eval 'do_action("wpfc_delete_cache");'
```

### Issue: High Memory Usage

**Redis using too much memory:**
```bash
# Check Redis memory
redis-cli info memory

# Set max memory
redis-cli config set maxmemory 256mb
redis-cli config set maxmemory-policy allkeys-lru

# Make permanent in /etc/redis/redis.conf
sudo nano /etc/redis/redis.conf
```

Add:
```
maxmemory 256mb
maxmemory-policy allkeys-lru
```

---

## 🚀 Advanced Optimization

### Preload Cache

**WP Fastest Cache preload:**
- Automatically creates cache for all pages
- Runs in background
- Configurable in settings

**Manual preload:**
```bash
# Preload specific pages
curl -s https://yoursite.com/ > /dev/null
curl -s https://yoursite.com/about/ > /dev/null
curl -s https://yoursite.com/contact/ > /dev/null

# Preload sitemap
wget -q https://yoursite.com/sitemap.xml -O - | \
  grep -oP '(?<=<loc>)[^<]+' | \
  while read url; do
    curl -s "$url" > /dev/null
    echo "Preloaded: $url"
  done
```

### Critical CSS

**Not in WP Fastest Cache Free, but can be added:**
```bash
# Install Critical CSS plugin
wp plugin install autoptimize --activate

# Or generate manually with Critical
npm install -g critical

critical https://yoursite.com \
  --base cms/wp-content/themes/custom-theme \
  --inline > critical.css
```

### Database Optimization
```bash
# Optimize database
wp db optimize

# Clean up
wp transient delete --all
wp post delete $(wp post list --post_status=trash --format=ids) --force

# Limit revisions in wp-config.php
define('WP_POST_REVISIONS', 3);
```

### Image Optimization

**Install WebP plugin:**
```bash
wp plugin install webp-converter-for-media --activate
```

**Or use Cloudflare Polish (Pro plan):**
- Automatic WebP conversion
- Lossless/Lossy compression
- No server resources needed

---

## 📈 Monitoring

### Set Up Monitoring

**1. Uptime Monitoring:**
- UptimeRobot (free)
- Pingdom
- StatusCake

**2. Performance Monitoring:**
```bash
# Daily performance test
0 2 * * * lighthouse https://yoursite.com --output=json > /var/log/lighthouse-$(date +\%Y\%m\%d).json
```

**3. Cache Hit Ratio:**

For Redis:
```bash
# Check hit ratio
redis-cli info stats | grep keyspace_hits
redis-cli info stats | grep keyspace_misses

# Good ratio: > 80% hits
```

For Cloudflare:
- Dashboard → Analytics → Performance
- Cache Requests chart
- Target: > 80% cached

---

## 🎯 Complete Caching Stack

### Recommended Setup

**Level 1 - Basic (Free):**
```
WordPress Site
  └─ WP Fastest Cache (HTML minification, Gzip)
     └─ Cloudflare (CDN, edge cache)
```

**Level 2 - Optimal (Free, requires Redis):**
```
WordPress Site
  ├─ WP Fastest Cache (page cache, minification)
  ├─ Redis (object cache, transients)
  └─ Cloudflare (CDN, edge cache, Brotli)
```

**Level 3 - Professional (Paid):**
```
WordPress Site
  ├─ WP Rocket (€59/year - page cache, critical CSS, lazy load)
  ├─ Redis (object cache)
  └─ Cloudflare Pro (€20/month - Polish, Argo, better analytics)
```

**Level 4 - Enterprise (Complex):**
```
Nginx Server
  └─ Varnish (HTTP accelerator)
     └─ Nginx FastCGI Cache (server-level page cache)
        └─ WordPress
           ├─ Redis (object cache)
           └─ Cloudflare Enterprise
```

---

## 📞 Support

For caching issues:
- Check [TROUBLESHOOTING.md](./TROUBLESHOOTING.md)
- WP Fastest Cache Support: https://wordpress.org/support/plugin/wp-fastest-cache/
- Redis Cache Plugin: https://wordpress.org/support/plugin/redis-cache/

---

**Last Updated:** February 2026  
**Version:** 1.0.0