# Deployment Guide

Complete guide for deploying the WordPress Agency Starter Kit to production.

---

## 📋 Table of Contents

- [Pre-Deployment Checklist](#pre-deployment-checklist)
- [Server Requirements](#server-requirements)
- [Deployment Methods](#deployment-methods)
- [Manual Deployment](#manual-deployment)
- [Git-Based Deployment](#git-based-deployment)
- [Database Migration](#database-migration)
- [Environment Configuration](#environment-configuration)
- [SSL & Security](#ssl--security)
- [Post-Deployment](#post-deployment)
- [Rollback Procedures](#rollback-procedures)
- [CI/CD Pipeline](#cicd-pipeline)
- [Monitoring & Maintenance](#monitoring--maintenance)

---

## ✅ Pre-Deployment Checklist

### Code Preparation

- [ ] All features tested locally
- [ ] No console errors
- [ ] All assets compiled (`npm run build`)
- [ ] Code committed to Git
- [ ] Version tagged (e.g., `v1.0.0`)
- [ ] Documentation updated
- [ ] Changelog updated
- [ ] Database backup created
- [ ] Environment variables documented

### Content Preparation

- [ ] All content reviewed
- [ ] Images optimized
- [ ] SEO meta tags set
- [ ] Contact forms tested
- [ ] Navigation menus configured
- [ ] Footer information updated
- [ ] Privacy policy & legal pages complete
- [ ] 404 page customized

### Performance

- [ ] Images compressed
- [ ] Unused plugins removed
- [ ] Database optimized
- [ ] Caching configured
- [ ] Minification enabled
- [ ] Lazy loading enabled

### Security

- [ ] Admin password changed
- [ ] Default admin username changed
- [ ] Unnecessary users removed
- [ ] File permissions set correctly
- [ ] Security headers configured
- [ ] Firewall rules prepared
- [ ] SSL certificate ordered
- [ ] Backup strategy in place

---

## 🖥️ Server Requirements

### Minimum Requirements
```
PHP:          8.0 or higher
MySQL:        5.7+ or MariaDB 10.3+
Web Server:   Apache 2.4+ or Nginx 1.18+
Disk Space:   500 MB minimum (1 GB recommended)
Memory:       256 MB minimum (512 MB recommended)
```

### Recommended Requirements
```
PHP:          8.3
MySQL:        8.0 or MariaDB 10.6+
Web Server:   Nginx 1.24+
Disk Space:   2 GB
Memory:       1 GB
CPU:          2 cores
```

### Required PHP Extensions
```
mysqli
gd or imagick
curl
mbstring
xml
zip
openssl
tokenizer
fileinfo
```

**Check PHP Extensions:**
```bash
php -m
```

### Apache Modules (if using Apache)
```
mod_rewrite
mod_headers
mod_expires
mod_deflate
mod_ssl
```

**Enable modules:**
```bash
sudo a2enmod rewrite headers expires deflate ssl
sudo systemctl restart apache2
```

---

## 🚀 Deployment Methods

### Method Comparison

| Method | Difficulty | Speed | Automation | Best For |
|--------|-----------|-------|------------|----------|
| Manual FTP | Easy | Slow | None | Small sites, one-time |
| Manual SSH | Medium | Medium | Partial | Medium sites |
| Git + Webhooks | Hard | Fast | Full | Professional, frequent updates |
| CI/CD Pipeline | Hard | Fast | Full | Enterprise, teams |

---

## 📦 Manual Deployment

### Step 1: Build Assets
```bash
# On local machine
npm run build
```

Verify files created:
```bash
ls -la cms/wp-content/themes/custom-theme/assets/dist/
```

### Step 2: Create Archive
```bash
# Create deployment package
tar -czf deployment.tar.gz \
  cms/wp-content/themes/custom-theme \
  cms/wp-content/mu-plugins/agency-core \
  cms/wp-content/plugins/advanced-custom-fields-pro
```

### Step 3: Upload to Server

**Option A: FTP**

1. Connect to server via FTP client (FileZilla, Cyberduck)
2. Navigate to `/public_html` or `/var/www/html`
3. Upload extracted files
4. Overwrite existing files

**Option B: SCP**
```bash
# Upload via SCP
scp deployment.tar.gz user@yourserver.com:/tmp/

# SSH into server
ssh user@yourserver.com

# Extract
cd /var/www/html
sudo tar -xzf /tmp/deployment.tar.gz
sudo chown -R www-data:www-data cms/wp-content
```

### Step 4: Set Permissions
```bash
# On server
cd /var/www/html

# Set directory permissions
find cms -type d -exec chmod 755 {} \;

# Set file permissions
find cms -type f -exec chmod 644 {} \;

# Make wp-config.php read-only
chmod 440 cms/wp-config.php

# Set ownership
chown -R www-data:www-data cms
```

### Step 5: Configure WordPress

**Create wp-config.php on server:**
```bash
cd cms

# Create from sample
cp wp-config-sample.php wp-config.php
nano wp-config.php
```

**Edit configuration:**
```php
<?php
// Database settings
define('DB_NAME', 'production_db');
define('DB_USER', 'production_user');
define('DB_PASSWORD', 'strong_password_here');
define('DB_HOST', 'localhost');
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');

// Authentication keys (generate at https://api.wordpress.org/secret-key/1.1/salt/)
define('AUTH_KEY',         'put your unique phrase here');
define('SECURE_AUTH_KEY',  'put your unique phrase here');
define('LOGGED_IN_KEY',    'put your unique phrase here');
define('NONCE_KEY',        'put your unique phrase here');
define('AUTH_SALT',        'put your unique phrase here');
define('SECURE_AUTH_SALT', 'put your unique phrase here');
define('LOGGED_IN_SALT',   'put your unique phrase here');
define('NONCE_SALT',       'put your unique phrase here');

// Table prefix
$table_prefix = 'wp_';

// Debugging (disable in production)
define('WP_DEBUG', false);
define('WP_DEBUG_LOG', false);
define('WP_DEBUG_DISPLAY', false);

// Memory limits
define('WP_MEMORY_LIMIT', '256M');
define('WP_MAX_MEMORY_LIMIT', '512M');

// Security
define('DISALLOW_FILE_EDIT', true);
define('FORCE_SSL_ADMIN', true);

// Auto-save interval (10 minutes)
define('AUTOSAVE_INTERVAL', 600);

// Post revisions limit
define('WP_POST_REVISIONS', 3);

// Disable cron (use system cron)
define('DISABLE_WP_CRON', true);

/* That's all, stop editing! Happy publishing. */
require_once ABSPATH . 'wp-settings.php';
```

---

## 🔄 Git-Based Deployment

### Architecture
```
Local Development
    ↓ (git push)
GitHub/GitLab Repository
    ↓ (webhook)
Production Server
    ↓ (git pull)
Live Website
```

### Initial Setup

#### 1. Server Preparation

**SSH into server:**
```bash
ssh user@yourserver.com
```

**Install Git:**
```bash
sudo apt update
sudo apt install git
```

**Generate SSH key for deployment:**
```bash
ssh-keygen -t ed25519 -C "deploy@yourserver.com"
cat ~/.ssh/id_ed25519.pub
# Copy this key
```

**Add to GitHub/GitLab:**
1. Go to repository settings
2. Deploy Keys (GitHub) or Deploy Tokens (GitLab)
3. Add new deploy key
4. Paste public key

#### 2. Clone Repository
```bash
cd /var/www
sudo git clone git@github.com:your-agency/your-project.git html
sudo chown -R www-data:www-data html
cd html
```

#### 3. Install Dependencies
```bash
# Composer (if needed)
composer install --no-dev --optimize-autoloader

# Node modules are NOT needed on server (assets pre-built)
```

#### 4. Setup Webhook

**Create deployment script:**
```bash
sudo nano /var/www/deploy.sh
```

**Script content:**
```bash
#!/bin/bash

# Configuration
REPO_DIR="/var/www/html"
LOG_FILE="/var/www/deploy.log"

# Logging function
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

log "=== Deployment Started ==="

# Navigate to repository
cd $REPO_DIR || exit 1

# Stash any local changes
log "Stashing local changes..."
git stash

# Pull latest changes
log "Pulling latest changes..."
git pull origin main

if [ $? -ne 0 ]; then
    log "ERROR: Git pull failed"
    exit 1
fi

# Set permissions
log "Setting permissions..."
sudo chown -R www-data:www-data $REPO_DIR
find $REPO_DIR/cms -type d -exec chmod 755 {} \;
find $REPO_DIR/cms -type f -exec chmod 644 {} \;

# Flush WordPress cache
log "Flushing WordPress cache..."
cd $REPO_DIR/cms
wp cache flush --allow-root

# Flush rewrite rules
log "Flushing rewrite rules..."
wp rewrite flush --allow-root

log "=== Deployment Completed Successfully ==="
```

**Make executable:**
```bash
sudo chmod +x /var/www/deploy.sh
```

**Test script:**
```bash
sudo /var/www/deploy.sh
```

#### 5. Configure Webhook Endpoint

**Create webhook handler:**
```bash
sudo nano /var/www/html/webhook.php
```

**Webhook script:**
```php
<?php
/**
 * GitHub/GitLab Webhook Handler
 */

// Security: Verify secret
$secret = 'your_webhook_secret_here';
$headers = getallheaders();
$signature = $headers['X-Hub-Signature-256'] ?? ''; // GitHub
$token = $headers['X-Gitlab-Token'] ?? ''; // GitLab

// Verify GitHub signature
if ($signature) {
    $payload = file_get_contents('php://input');
    $hash = 'sha256=' . hash_hmac('sha256', $payload, $secret);
    
    if (!hash_equals($hash, $signature)) {
        http_response_code(403);
        die('Invalid signature');
    }
}

// Verify GitLab token
if ($token && $token !== $secret) {
    http_response_code(403);
    die('Invalid token');
}

// Log webhook received
file_put_contents('/var/www/webhook.log', date('Y-m-d H:i:s') . " - Webhook received\n", FILE_APPEND);

// Execute deployment script
$output = shell_exec('sudo /var/www/deploy.sh 2>&1');

// Log deployment output
file_put_contents('/var/www/webhook.log', $output . "\n", FILE_APPEND);

// Return success
http_response_code(200);
echo json_encode(['status' => 'success', 'message' => 'Deployment initiated']);
```

**Add to sudoers (allow www-data to run deploy script):**
```bash
sudo visudo
```

Add line:
```
www-data ALL=(ALL) NOPASSWD: /var/www/deploy.sh
```

#### 6. Configure Webhook in GitHub/GitLab

**GitHub:**
1. Go to repository → Settings → Webhooks
2. Click "Add webhook"
3. Payload URL: `https://yoursite.com/webhook.php`
4. Content type: `application/json`
5. Secret: Your secret from webhook.php
6. Events: Just the push event
7. Active: ✓
8. Click "Add webhook"

**GitLab:**
1. Go to repository → Settings → Webhooks
2. URL: `https://yoursite.com/webhook.php`
3. Secret token: Your secret
4. Trigger: Push events
5. Enable: ✓
6. Click "Add webhook"

#### 7. Test Deployment
```bash
# Make a change locally
echo "test" > test.txt
git add test.txt
git commit -m "test: webhook deployment"
git push origin main

# Check webhook log on server
tail -f /var/www/webhook.log

# Check deployment log
tail -f /var/www/deploy.log
```

### Using Ploi (Recommended)

**Ploi** is a server management tool that simplifies Git-based deployments.

#### Setup with Ploi

1. **Create Ploi Account**
   - Visit https://ploi.io
   - Sign up for account

2. **Connect Server**
   - Add your server to Ploi
   - Ploi installs required software

3. **Create Site**
   - Add new site
   - Enter domain name
   - Choose PHP version (8.3)

4. **Connect Repository**
   - Connect GitHub/GitLab
   - Select repository
   - Choose branch (main)

5. **Configure Deployment Script**
```bash
# Ploi's deployment script editor
cd {SITE_DIRECTORY}
git pull origin main

# Build assets (if building on server)
# npm install
# npm run build

# Set permissions
sudo chown -R {USER}:{USER} .
find . -type d -exec chmod 755 {} \;
find . -type f -exec chmod 644 {} \;

# WordPress specific
cd cms
wp cache flush --allow-root
wp rewrite flush --allow-root

echo "Deployment completed: $(date)"
```

6. **Enable Auto Deploy**
   - Ploi creates webhook automatically
   - Push to Git triggers deployment

7. **Configure Environment**
   - Set environment variables in Ploi
   - Add secrets (DB passwords, API keys)

#### Ploi Features

- ✅ One-click SSL (Let's Encrypt)
- ✅ Auto-deploy on push
- ✅ Deployment history
- ✅ Rollback to previous deployment
- ✅ Queue workers
- ✅ Cron jobs
- ✅ Database management
- ✅ Firewall management
- ✅ Email notifications

---

## 💾 Database Migration

### Export from Development
```bash
# On local machine
cd cms

# Export database
wp db export backup-$(date +%Y%m%d-%H%M%S).sql

# Compress
gzip backup-*.sql
```

### Import to Production
```bash
# Upload SQL file to server
scp backup-*.sql.gz user@yourserver.com:/tmp/

# SSH into server
ssh user@yourserver.com

# Decompress
gunzip /tmp/backup-*.sql.gz

# Import
cd /var/www/html/cms
wp db import /tmp/backup-*.sql

# Search-replace URLs
wp search-replace 'http://local.test' 'https://yoursite.com' --all-tables

# Search-replace file paths (if needed)
wp search-replace '/Users/you/Sites/project' '/var/www/html' --all-tables

# Flush cache
wp cache flush
wp rewrite flush
```

### Database Migration with WP-CLI
```bash
# Export from dev
wp db export - | gzip > database.sql.gz

# Transfer to production
scp database.sql.gz user@production:/tmp/

# On production
cd /var/www/html/cms
gunzip < /tmp/database.sql.gz | wp db import -

# Update URLs
wp search-replace 'dev-site.test' 'production-site.com' --all-tables

# Update home and siteurl
wp option update home 'https://production-site.com'
wp option update siteurl 'https://production-site.com'
```

---

## ⚙️ Environment Configuration

### Environment Variables

**Create .env file (optional):**
```bash
# /var/www/html/.env
DB_NAME=production_db
DB_USER=production_user
DB_PASSWORD=secure_password
DB_HOST=localhost

WP_HOME=https://yoursite.com
WP_SITEURL=https://yoursite.com/cms

WP_DEBUG=false
WP_DEBUG_LOG=false
```

**Load in wp-config.php:**
```php
// Load environment variables
if (file_exists(__DIR__ . '/../.env')) {
    $env = parse_ini_file(__DIR__ . '/../.env');
    foreach ($env as $key => $value) {
        putenv("$key=$value");
    }
}

define('DB_NAME', getenv('DB_NAME'));
define('DB_USER', getenv('DB_USER'));
define('DB_PASSWORD', getenv('DB_PASSWORD'));
define('DB_HOST', getenv('DB_HOST'));
```

### Production Optimizations

**wp-config.php additions:**
```php
// Disable post revisions
define('WP_POST_REVISIONS', 3);

// Increase auto-save interval (10 minutes)
define('AUTOSAVE_INTERVAL', 600);

// Disable file editing
define('DISALLOW_FILE_EDIT', true);

// Disable plugin/theme updates (if using Git deployment)
define('DISALLOW_FILE_MODS', true);

// Force SSL
define('FORCE_SSL_ADMIN', true);

// Set correct home/siteurl
define('WP_HOME', 'https://yoursite.com');
define('WP_SITEURL', 'https://yoursite.com/cms');

// Use system cron
define('DISABLE_WP_CRON', true);

// Enable Redis cache (if installed)
define('WP_CACHE', true);
define('WP_REDIS_HOST', 'localhost');
define('WP_REDIS_PORT', 6379);
```

### System Cron

**Disable WP-Cron:**
```php
// In wp-config.php
define('DISABLE_WP_CRON', true);
```

**Setup system cron:**
```bash
sudo crontab -e
```

Add:
```cron
*/15 * * * * cd /var/www/html/cms && wp cron event run --due-now --allow-root >> /var/log/wp-cron.log 2>&1
```

---

## 🔒 SSL & Security

### Install SSL Certificate

#### Let's Encrypt (Free, via Certbot)
```bash
# Install Certbot
sudo apt update
sudo apt install certbot python3-certbot-nginx

# Obtain certificate
sudo certbot --nginx -d yoursite.com -d www.yoursite.com

# Auto-renewal (certbot adds this automatically)
sudo certbot renew --dry-run
```

#### Or via Ploi

1. Go to site in Ploi
2. Click SSL tab
3. Click "Install Let's Encrypt"
4. Done!

### Security Headers

**Nginx configuration:**
```nginx
# /etc/nginx/sites-available/yoursite.com

server {
    listen 443 ssl http2;
    server_name yoursite.com www.yoursite.com;
    
    root /var/www/html/cms;
    index index.php;
    
    # SSL certificates
    ssl_certificate /etc/letsencrypt/live/yoursite.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/yoursite.com/privkey.pem;
    
    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Permissions-Policy "geolocation=(), microphone=(), camera=()" always;
    
    # HSTS
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;
    
    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_types text/plain text/css text/xml text/javascript application/javascript application/xml+rss application/json;
    
    # Cache static files
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
    
    # WordPress permalinks
    location / {
        try_files $uri $uri/ /index.php?$args;
    }
    
    # PHP processing
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
    
    # Deny access to sensitive files
    location ~ /\. {
        deny all;
    }
    
    location ~ /wp-config.php {
        deny all;
    }
}

# Redirect HTTP to HTTPS
server {
    listen 80;
    server_name yoursite.com www.yoursite.com;
    return 301 https://$server_name$request_uri;
}
```

**Reload Nginx:**
```bash
sudo nginx -t
sudo systemctl reload nginx
```

### Firewall Configuration

**UFW (Ubuntu):**
```bash
# Enable firewall
sudo ufw enable

# Allow SSH
sudo ufw allow 22

# Allow HTTP and HTTPS
sudo ufw allow 80
sudo ufw allow 443

# Check status
sudo ufw status
```

### File Permissions
```bash
cd /var/www/html

# Correct ownership
sudo chown -R www-data:www-data cms

# Directory permissions
find cms -type d -exec chmod 755 {} \;

# File permissions
find cms -type f -exec chmod 644 {} \;

# Protect wp-config.php
chmod 440 cms/wp-config.php

# Protect .htaccess
chmod 444 cms/.htaccess
```

---

## ✅ Post-Deployment

### Testing Checklist

- [ ] Homepage loads
- [ ] All pages accessible
- [ ] Forms work
- [ ] Search works
- [ ] AJAX features work (filters, load more)
- [ ] Images load correctly
- [ ] Navigation menus work
- [ ] SSL certificate valid
- [ ] Mobile responsive
- [ ] Contact form emails received
- [ ] Admin login works
- [ ] Speed test acceptable (<3s load time)

### Performance Testing

**GTmetrix:**
- Visit https://gtmetrix.com
- Enter your URL
- Check scores

**PageSpeed Insights:**
- Visit https://pagespeed.web.dev
- Enter your URL
- Aim for 90+ score

**WebPageTest:**
- Visit https://webpagetest.org
- Test from multiple locations

### Security Testing

**Sucuri SiteCheck:**
- Visit https://sitecheck.sucuri.net
- Scan your site
- Check for vulnerabilities

**SSL Labs:**
- Visit https://ssllabs.com/ssltest/
- Test SSL configuration
- Aim for A+ rating

### SEO Verification

- [ ] Google Search Console configured
- [ ] Sitemap submitted
- [ ] robots.txt configured
- [ ] Meta tags present
- [ ] Open Graph tags set
- [ ] Schema markup added

### Monitoring Setup

**Uptime Monitoring:**
- UptimeRobot (free)
- Pingdom
- StatusCake

**Error Logging:**
```php
// Enable error logging
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
@ini_set('display_errors', 0);

// Custom error log location
@ini_set('error_log', '/var/www/logs/php-errors.log');
```

**Check logs:**
```bash
tail -f /var/www/logs/php-errors.log
tail -f /var/www/html/cms/wp-content/debug.log
```

---

## 🔙 Rollback Procedures

### Quick Rollback (Git)
```bash
# View deployment history
cd /var/www/html
git log --oneline -10

# Rollback to previous commit
git reset --hard HEAD~1

# Or rollback to specific commit
git reset --hard abc1234

# Force update files
git pull origin main

# Restart services
sudo systemctl reload nginx
sudo systemctl reload php8.3-fpm
```

### Database Rollback

**Always backup before major changes:**
```bash
# Before deployment
wp db export backup-pre-deployment-$(date +%Y%m%d-%H%M%S).sql

# If rollback needed
wp db import backup-pre-deployment-20260211-143000.sql
```

### Using Ploi Rollback

1. Go to site in Ploi
2. Click "Deployments" tab
3. Find previous successful deployment
4. Click "Redeploy"

---

## 🔄 CI/CD Pipeline

### GitHub Actions Example

**File:** `.github/workflows/deploy.yml`
```yaml
name: Deploy to Production

on:
  push:
    branches:
      - main

jobs:
  deploy:
    runs-on: ubuntu-latest
    
    steps:
      - name: Checkout code
        uses: actions/checkout@v3
      
      - name: Setup Node.js
        uses: actions/setup-node@v3
        with:
          node-version: '18'
      
      - name: Install dependencies
        run: npm ci
      
      - name: Build assets
        run: npm run build
      
      - name: Deploy to server
        uses: appleboy/ssh-action@master
        with:
          host: ${{ secrets.SERVER_HOST }}
          username: ${{ secrets.SERVER_USER }}
          key: ${{ secrets.SSH_PRIVATE_KEY }}
          script: |
            cd /var/www/html
            git pull origin main
            sudo /var/www/deploy.sh
      
      - name: Notify success
        if: success()
        run: echo "Deployment successful!"
      
      - name: Notify failure
        if: failure()
        run: echo "Deployment failed!"
```

### GitLab CI Example

**File:** `.gitlab-ci.yml`
```yaml
stages:
  - build
  - deploy

build:
  stage: build
  image: node:18
  script:
    - npm ci
    - npm run build
  artifacts:
    paths:
      - cms/wp-content/themes/custom-theme/assets/dist/
    expire_in: 1 hour
  only:
    - main

deploy:
  stage: deploy
  image: alpine:latest
  before_script:
    - apk add --no-cache openssh-client
    - eval $(ssh-agent -s)
    - echo "$SSH_PRIVATE_KEY" | tr -d '\r' | ssh-add -
    - mkdir -p ~/.ssh
    - chmod 700 ~/.ssh
  script:
    - ssh -o StrictHostKeyChecking=no $SERVER_USER@$SERVER_HOST "cd /var/www/html && git pull origin main && sudo /var/www/deploy.sh"
  only:
    - main
```

---

## 📊 Monitoring & Maintenance

### Daily Checks
```bash
# Check disk space
df -h

# Check error logs
tail -50 /var/www/html/cms/wp-content/debug.log

# Check uptime
uptime

# Check memory
free -m
```

### Weekly Tasks

- [ ] Review error logs
- [ ] Check backup completed
- [ ] Review analytics
- [ ] Test contact forms
- [ ] Check site speed
- [ ] Review comments/spam
- [ ] Update plugins (if needed)

### Monthly Tasks

- [ ] WordPress core update
- [ ] Theme update (from Git)
- [ ] Plugin updates
- [ ] Database optimization
- [ ] Security audit
- [ ] Performance review
- [ ] Broken link check
- [ ] SSL certificate check

### Automated Backups

**Using WP-CLI + Cron:**
```bash
# Create backup script
sudo nano /var/www/backup.sh
```
```bash
#!/bin/bash

BACKUP_DIR="/var/backups/wordpress"
TIMESTAMP=$(date +%Y%m%d-%H%M%S)

# Create backup directory
mkdir -p $BACKUP_DIR

# Database backup
cd /var/www/html/cms
wp db export $BACKUP_DIR/db-$TIMESTAMP.sql --allow-root

# Compress
gzip $BACKUP_DIR/db-$TIMESTAMP.sql

# Files backup
tar -czf $BACKUP_DIR/files-$TIMESTAMP.tar.gz \
  /var/www/html/cms/wp-content/uploads

# Keep only last 7 days
find $BACKUP_DIR -type f -mtime +7 -delete

echo "Backup completed: $TIMESTAMP"
```

**Add to cron:**
```bash
sudo crontab -e
```
```cron
# Daily backup at 2 AM
0 2 * * * /var/www/backup.sh >> /var/log/backup.log 2>&1
```

---

## 🎯 Performance Optimization

### Server-Level

**PHP-FPM Optimization:**
```bash
sudo nano /etc/php/8.3/fpm/pool.d/www.conf
```
```ini
pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
pm.max_requests = 500
```

**OPcache:**
```bash
sudo nano /etc/php/8.3/fpm/conf.d/10-opcache.ini
```
```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.revalidate_freq=2
opcache.fast_shutdown=1
```

**Redis Cache:**
```bash
# Install Redis
sudo apt install redis-server php-redis

# Start Redis
sudo systemctl start redis-server
sudo systemctl enable redis-server

# Install WordPress Redis plugin
cd /var/www/html/cms
wp plugin install redis-cache --activate --allow-root
wp redis enable --allow-root
```

### WordPress Caching & Optimization

#### Install WP Fastest Cache
```bash
cd /var/www/html/cms
wp plugin install wp-fastest-cache --activate --allow-root

# Configure via WP-CLI (optional)
wp option update wpfc_options '{"wpfc_cache_system":"on","wpfc_minify_html":"on","wpfc_minify_css":"on","wpfc_gzip":"on","wpfc_browser_caching":"on"}' --format=json
```

#### Recommended Settings

**File:** WP Admin → Settings → WP Fastest Cache
```
Cache System:           ☑ Enable
Cache Timeout:          ☑ Enable (43200 seconds / 12 hours)
Preload:                ☑ Enable
New Post:               ☑ Clear all cache
Update Post:            ☑ Clear only post cache
Minify HTML:            ☑ Enable
Minify CSS:             ☑ Enable
Combine CSS:            ☑ Enable
Minify JS:              ☐ Disable (can break AJAX features)
Combine JS:             ☐ Disable (can break AJAX features)
Gzip:                   ☑ Enable
Browser Caching:        ☑ Enable
Disable Emojis:         ☑ Enable
```

**Exclude Pages:**

Add these to "Exclude" tab:
```
/wp-admin/
/wp-login.php
/cart/
/checkout/
/my-account/
```

**Clear Cache via WP-CLI:**
```bash
# Clear all cache
wp cache flush --allow-root

# Or WP Fastest Cache specific
wp eval 'do_action("wpfc_delete_cache");'
```

#### Alternative: Redis Object Cache

For advanced setups, combine WP Fastest Cache (page cache) with Redis (object cache):
```bash
# Install Redis server
sudo apt install redis-server php-redis

# Enable Redis
sudo systemctl start redis-server
sudo systemctl enable redis-server

# Install WordPress plugin
wp plugin install redis-cache --activate --allow-root

# Enable Redis cache
wp redis enable --allow-root
```

**wp-config.php additions:**
```php
// Redis configuration
define('WP_CACHE', true);
define('WP_REDIS_HOST', 'localhost');
define('WP_REDIS_PORT', 6379);
define('WP_REDIS_TIMEOUT', 1);
define('WP_REDIS_READ_TIMEOUT', 1);
define('WP_REDIS_DATABASE', 0);

// Optional: Redis password
// define('WP_REDIS_PASSWORD', 'your-password');
```

#### Caching Stack Comparison

**Option A: Basic (Free, Recommended)**
```
WP Fastest Cache (page cache, minification)
+ Cloudflare (CDN, edge cache)
= Great performance for 90% of sites
```

**Option B: Advanced (Free, Server-dependent)**
```
WP Fastest Cache (page cache, minification)
+ Redis (object cache, transients)
+ Cloudflare (CDN, edge cache)
= Excellent performance for high-traffic sites
```

**Option C: Professional (Paid)**
```
WP Rocket (~€59/year)
+ Redis (object cache)
+ Cloudflare (CDN, edge cache)
= Maximum performance, easiest management
```

**Option D: Enterprise (Complex)**
```
Nginx FastCGI Cache (server-level page cache)
+ Redis (object cache)
+ Cloudflare (CDN, edge cache)
+ Varnish (optional, HTTP accelerator)
= Ultimate performance, requires DevOps expertise
```

---

## 📞 Support & Resources

### Deployment Issues

- Check [TROUBLESHOOTING.md](./TROUBLESHOOTING.md)
- Review server logs
- Check webhook logs
- Test manually

### External Resources

- [WordPress.org Deployment](https://wordpress.org/support/article/moving-wordpress/)
- [Ploi Documentation](https://ploi.io/documentation)
- [DigitalOcean Tutorials](https://digitalocean.com/community/tutorials)
- [Nginx Documentation](https://nginx.org/en/docs/)

### Emergency Contacts

- Lead Developer: dev@your-agency.com
- DevOps: devops@your-agency.com
- Hosting Support: hosting@your-provider.com

---

**Last Updated:** February 2026  
**Version:** 1.0.0