# Custom Post Types Reference

Complete reference for all Custom Post Types in the WordPress Agency Starter Kit.

---

## 📋 Table of Contents

- [Overview](#overview)
- [Jobs](#jobs)
- [Projects](#projects)
- [Team](#team)
- [Services](#services)
- [Testimonials](#testimonials)
- [Hero Slides](#hero-slides)
- [Carousel](#carousel)
- [FAQ](#faq)
- [Google Maps](#google-maps)
- [Best Practices](#best-practices)
- [Creating Custom Templates](#creating-custom-templates)

---

## 🎯 Overview

The system includes 9 pre-built Custom Post Types (CPTs) for common agency needs:

| Post Type | Slug | Purpose | Icon |
|-----------|------|---------|------|
| Jobs | `job` | Job listings & career pages | briefcase |
| Projects | `project` | Portfolio items | portfolio |
| Team | `team` | Team member profiles | groups |
| Services | `services` | Service offerings | star-filled |
| Testimonials | `testimonials` | Client reviews | format-quote |
| Hero Slides | `hero_slide` | Homepage sliders | slides |
| Carousel | `carousel` | Image carousels | images-alt2 |
| FAQ | `faq` | Frequently asked questions | editor-help |
| Google Maps | `google_map` | GDPR-compliant maps | location-alt |

**All CPTs are registered in:**
`cms/wp-content/mu-plugins/agency-core/inc/custom-post-types.php`

---

## 💼 Jobs

### Purpose

Manage job postings and career opportunities.

### Registration Details
```php
Post Type: job
Slug: /jobs/
Menu Position: 20
Supports: title, editor, thumbnail, excerpt
Hierarchical: No
Has Archive: Yes
Show in REST: Yes
```

### Taxonomies

#### 1. Job Category (`job_category`)

- **Type:** Hierarchical (like categories)
- **Slug:** `/job-category/`
- **Purpose:** Organize jobs by department/area
- **Examples:** Development, Design, Marketing, Sales

#### 2. Job Type (`job_type`)

- **Type:** Hierarchical
- **Slug:** `/job-type/`
- **Purpose:** Employment type classification
- **Examples:** Full-time, Part-time, Contract, Freelance

#### 3. Job Location (`job_location`)

- **Type:** Hierarchical
- **Slug:** `/job-location/`
- **Purpose:** Geographic location
- **Examples:** Vienna, Berlin, Remote, On-site

### ACF Fields

| Field Name | Type | Required | Description |
|------------|------|----------|-------------|
| `employment_type` | Select | No | Employment type (Full-time, Part-time, etc.) |
| `remote_work` | True/False | No | Remote work available |
| `salary_min` | Number | No | Minimum salary |
| `salary_max` | Number | No | Maximum salary |
| `experience_years` | Number | No | Required years of experience |
| `application_deadline` | Date Picker | No | Last day to apply |
| `application_url` | URL | No | External application link |
| `featured` | True/False | No | Feature this job listing |

### Usage in Templates
```php
<?php
// Get ACF fields
$employment_type = get_field('employment_type');
$remote_work = get_field('remote_work');
$salary_min = get_field('salary_min');
$salary_max = get_field('salary_max');
$experience_years = get_field('experience_years');
$deadline = get_field('application_deadline');
$application_url = get_field('application_url');
$featured = get_field('featured');

// Get taxonomies
$categories = get_the_terms(get_the_ID(), 'job_category');
$job_types = get_the_terms(get_the_ID(), 'job_type');
$locations = get_the_terms(get_the_ID(), 'job_location');
?>

<article class="job-post <?php echo $featured ? 'job-post--featured' : ''; ?>">
    <h1><?php the_title(); ?></h1>
    
    <?php if ($categories) : ?>
        <div class="job-categories">
            <?php foreach ($categories as $cat) : ?>
                <span class="badge"><?php echo esc_html($cat->name); ?></span>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <div class="job-meta">
        <?php if ($employment_type) : ?>
            <span class="job-type"><?php echo esc_html($employment_type); ?></span>
        <?php endif; ?>
        
        <?php if ($locations) : ?>
            <span class="job-location">
                <i class="dashicons dashicons-location"></i>
                <?php echo esc_html($locations[0]->name); ?>
            </span>
        <?php endif; ?>
        
        <?php if ($remote_work) : ?>
            <span class="job-remote">Remote OK</span>
        <?php endif; ?>
    </div>
    
    <?php if ($salary_min && $salary_max) : ?>
        <div class="job-salary">
            Salary: €<?php echo number_format($salary_min, 0, ',', '.'); ?> - 
            €<?php echo number_format($salary_max, 0, ',', '.'); ?>
        </div>
    <?php endif; ?>
    
    <div class="job-content">
        <?php the_content(); ?>
    </div>
    
    <?php if ($deadline) : ?>
        <div class="job-deadline">
            Apply by: <?php echo date('F j, Y', strtotime($deadline)); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($application_url) : ?>
        <a href="<?php echo esc_url($application_url); ?>" class="button button--primary">
            Apply Now
        </a>
    <?php else : ?>
        <a href="<?php echo get_permalink(); ?>/apply" class="button button--primary">
            Apply Now
        </a>
    <?php endif; ?>
</article>
```

### Shortcodes
```
[posts_grid post_type="job" category="development" limit="9" columns="3"]
[ajax_filters post_type="job" posts_per_page="12" template="job"]
```

### Common Queries
```php
// Get all featured jobs
$args = array(
    'post_type' => 'job',
    'posts_per_page' => 5,
    'meta_query' => array(
        array(
            'key' => 'featured',
            'value' => '1',
            'compare' => '='
        )
    )
);
$featured_jobs = new WP_Query($args);

// Get jobs by location
$args = array(
    'post_type' => 'job',
    'tax_query' => array(
        array(
            'taxonomy' => 'job_location',
            'field' => 'slug',
            'terms' => 'vienna'
        )
    )
);
$vienna_jobs = new WP_Query($args);

// Get remote jobs
$args = array(
    'post_type' => 'job',
    'meta_query' => array(
        array(
            'key' => 'remote_work',
            'value' => '1',
            'compare' => '='
        )
    )
);
$remote_jobs = new WP_Query($args);
```

---

## 🎨 Projects

### Purpose

Showcase portfolio items and case studies.

### Registration Details
```php
Post Type: project
Slug: /projects/
Menu Position: 21
Supports: title, editor, thumbnail, excerpt
Hierarchical: No
Has Archive: Yes
Show in REST: Yes
```

### Taxonomies

#### Project Category (`project_category`)

- **Type:** Hierarchical
- **Slug:** `/project-category/`
- **Purpose:** Categorize projects by type
- **Examples:** Web Design, Development, Branding, Marketing

### ACF Fields

| Field Name | Type | Required | Description |
|------------|------|----------|-------------|
| `client_name` | Text | No | Client/company name |
| `project_date` | Date Picker | No | Completion date |
| `project_url` | URL | No | Live project URL |
| `project_type` | Text | No | Type of project |
| `technologies` | Text | No | Technologies used |
| `project_duration` | Text | No | Project duration |
| `gallery` | Gallery | No | Additional project images |

### Usage in Templates
```php
<?php
$client = get_field('client_name');
$date = get_field('project_date');
$url = get_field('project_url');
$type = get_field('project_type');
$tech = get_field('technologies');
$duration = get_field('project_duration');
$gallery = get_field('gallery');
$categories = get_the_terms(get_the_ID(), 'project_category');
?>

<article class="project-single">
    <div class="project-hero">
        <?php the_post_thumbnail('large'); ?>
    </div>
    
    <div class="project-info">
        <h1><?php the_title(); ?></h1>
        
        <div class="project-meta">
            <?php if ($client) : ?>
                <div class="project-client">
                    <strong>Client:</strong> <?php echo esc_html($client); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($date) : ?>
                <div class="project-date">
                    <strong>Date:</strong> <?php echo date('F Y', strtotime($date)); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($categories) : ?>
                <div class="project-categories">
                    <strong>Category:</strong>
                    <?php echo esc_html($categories[0]->name); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($tech) : ?>
                <div class="project-tech">
                    <strong>Technologies:</strong> <?php echo esc_html($tech); ?>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="project-content">
            <?php the_content(); ?>
        </div>
        
        <?php if ($gallery) : ?>
            <div class="project-gallery">
                <h2>Gallery</h2>
                <div class="gallery-grid">
                    <?php foreach ($gallery as $image) : ?>
                        <a href="<?php echo esc_url($image['url']); ?>" data-lightbox="gallery">
                            <img src="<?php echo esc_url($image['sizes']['medium']); ?>" 
                                 alt="<?php echo esc_attr($image['alt']); ?>">
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($url) : ?>
            <a href="<?php echo esc_url($url); ?>" target="_blank" class="button">
                Visit Live Site →
            </a>
        <?php endif; ?>
    </div>
</article>
```

### Shortcodes
```
[posts_grid post_type="project" category="web-design" limit="6" columns="3"]
[ajax_filters post_type="project" template="grid" columns="3"]
```

---

## 👥 Team

### Purpose

Display team member profiles and bios.

### Registration Details
```php
Post Type: team
Slug: /team/
Menu Position: 22
Supports: title, editor, thumbnail
Hierarchical: No
Has Archive: Yes
Show in REST: Yes
```

### Taxonomies

#### 1. Department (`department`)

- **Type:** Hierarchical
- **Purpose:** Organize by department
- **Examples:** Development, Design, Sales, Management

#### 2. Position (`position`)

- **Type:** Hierarchical
- **Purpose:** Job titles
- **Examples:** Manager, Developer, Designer, Marketing Specialist

### ACF Fields

| Field Name | Type | Required | Description |
|------------|------|----------|-------------|
| `job_title` | Text | Yes | Position/title |
| `email` | Email | No | Contact email |
| `phone` | Text | No | Phone number |
| `linkedin` | URL | No | LinkedIn profile |
| `twitter` | Text | No | Twitter handle |
| `github` | URL | No | GitHub profile |
| `skills` | Text | No | Comma-separated skills |
| `order` | Number | No | Display order |

### Usage in Templates
```php
<?php
$job_title = get_field('job_title');
$email = get_field('email');
$phone = get_field('phone');
$linkedin = get_field('linkedin');
$twitter = get_field('twitter');
$github = get_field('github');
$skills = get_field('skills');
$departments = get_the_terms(get_the_ID(), 'department');
?>

<div class="team-member">
    <?php if (has_post_thumbnail()) : ?>
        <div class="team-member__photo">
            <?php the_post_thumbnail('medium'); ?>
        </div>
    <?php endif; ?>
    
    <div class="team-member__info">
        <h3 class="team-member__name"><?php the_title(); ?></h3>
        
        <?php if ($job_title) : ?>
            <p class="team-member__title"><?php echo esc_html($job_title); ?></p>
        <?php endif; ?>
        
        <?php if ($departments) : ?>
            <p class="team-member__department"><?php echo esc_html($departments[0]->name); ?></p>
        <?php endif; ?>
        
        <div class="team-member__bio">
            <?php the_content(); ?>
        </div>
        
        <?php if ($skills) : ?>
            <div class="team-member__skills">
                <strong>Skills:</strong>
                <?php 
                $skills_array = explode(',', $skills);
                foreach ($skills_array as $skill) : ?>
                    <span class="skill-tag"><?php echo trim($skill); ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <div class="team-member__social">
            <?php if ($email) : ?>
                <a href="mailto:<?php echo esc_attr($email); ?>" class="social-link">
                    <i class="dashicons dashicons-email"></i>
                </a>
            <?php endif; ?>
            
            <?php if ($phone) : ?>
                <a href="tel:<?php echo esc_attr($phone); ?>" class="social-link">
                    <i class="dashicons dashicons-phone"></i>
                </a>
            <?php endif; ?>
            
            <?php if ($linkedin) : ?>
                <a href="<?php echo esc_url($linkedin); ?>" target="_blank" class="social-link">
                    <i class="fab fa-linkedin"></i>
                </a>
            <?php endif; ?>
            
            <?php if ($twitter) : ?>
                <a href="https://twitter.com/<?php echo esc_attr($twitter); ?>" target="_blank" class="social-link">
                    <i class="fab fa-twitter"></i>
                </a>
            <?php endif; ?>
            
            <?php if ($github) : ?>
                <a href="<?php echo esc_url($github); ?>" target="_blank" class="social-link">
                    <i class="fab fa-github"></i>
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>
```

### Shortcodes
```
[team_cards department="development" limit="8" columns="4"]
[posts_grid post_type="team" limit="12" columns="4"]
```

---

## ⚙️ Services

### Purpose

Describe service offerings and packages.

### Registration Details
```php
Post Type: services
Slug: /services/
Menu Position: 23
Supports: title, editor, thumbnail, excerpt
Hierarchical: No
Has Archive: Yes
Show in REST: Yes
```

### Taxonomies

#### Service Category (`service_category`)

- **Type:** Hierarchical
- **Purpose:** Group related services
- **Examples:** Web Development, Design, Marketing, Consulting

### ACF Fields

| Field Name | Type | Required | Description |
|------------|------|----------|-------------|
| `price_from` | Number | No | Starting price |
| `duration` | Text | No | Typical duration |
| `icon` | Text | No | Icon class (dashicons, fontawesome) |
| `features` | Textarea | No | List of features (one per line) |
| `featured` | True/False | No | Feature this service |

### Usage in Templates
```php
<?php
$price_from = get_field('price_from');
$duration = get_field('duration');
$icon = get_field('icon');
$features = get_field('features');
$featured = get_field('featured');
$categories = get_the_terms(get_the_ID(), 'service_category');
?>

<article class="service <?php echo $featured ? 'service--featured' : ''; ?>">
    <?php if ($icon) : ?>
        <div class="service__icon">
            <i class="<?php echo esc_attr($icon); ?>"></i>
        </div>
    <?php endif; ?>
    
    <h2 class="service__title"><?php the_title(); ?></h2>
    
    <?php if ($categories) : ?>
        <p class="service__category"><?php echo esc_html($categories[0]->name); ?></p>
    <?php endif; ?>
    
    <div class="service__description">
        <?php the_excerpt(); ?>
    </div>
    
    <?php if ($features) : ?>
        <ul class="service__features">
            <?php 
            $features_array = explode("\n", $features);
            foreach ($features_array as $feature) : 
                $feature = trim($feature);
                if (!empty($feature)) : ?>
                    <li><?php echo esc_html($feature); ?></li>
                <?php endif;
            endforeach; ?>
        </ul>
    <?php endif; ?>
    
    <div class="service__footer">
        <?php if ($price_from) : ?>
            <div class="service__price">
                From €<?php echo number_format($price_from, 2); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($duration) : ?>
            <div class="service__duration">
                <?php echo esc_html($duration); ?>
            </div>
        <?php endif; ?>
        
        <a href="<?php the_permalink(); ?>" class="button">
            Learn More
        </a>
    </div>
</article>
```

### Shortcodes
```
[posts_grid post_type="services" category="web-development" limit="6" columns="3"]
```

---

## 💬 Testimonials

### Purpose

Display client reviews and testimonials.

### Registration Details
```php
Post Type: testimonials
Slug: /testimonials/
Menu Position: 24
Supports: title, editor, thumbnail
Hierarchical: No
Has Archive: Yes
Show in REST: Yes
```

### Taxonomies

#### Industry (`industry`)

- **Type:** Hierarchical
- **Purpose:** Client's industry
- **Examples:** Technology, Healthcare, Finance, E-commerce

### ACF Fields

| Field Name | Type | Required | Description |
|------------|------|----------|-------------|
| `client_position` | Text | No | Client's job title |
| `company_name` | Text | Yes | Company name |
| `company_url` | URL | No | Company website |
| `rating` | Number | No | Star rating (1-5) |
| `project_type` | Text | No | Type of project |
| `featured` | True/False | No | Feature this testimonial |

### Usage in Templates
```php
<?php
$position = get_field('client_position');
$company = get_field('company_name');
$company_url = get_field('company_url');
$rating = get_field('rating');
$project_type = get_field('project_type');
$featured = get_field('featured');
$industries = get_the_terms(get_the_ID(), 'industry');
?>

<div class="testimonial <?php echo $featured ? 'testimonial--featured' : ''; ?>">
    <div class="testimonial__quote">
        <?php the_content(); ?>
    </div>
    
    <?php if ($rating) : ?>
        <div class="testimonial__rating">
            <?php for ($i = 1; $i <= 5; $i++) : ?>
                <span class="star <?php echo $i <= $rating ? 'star--filled' : ''; ?>">★</span>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
    
    <div class="testimonial__author">
        <?php if (has_post_thumbnail()) : ?>
            <div class="testimonial__photo">
                <?php the_post_thumbnail('thumbnail'); ?>
            </div>
        <?php endif; ?>
        
        <div class="testimonial__info">
            <div class="testimonial__name"><?php the_title(); ?></div>
            
            <?php if ($position) : ?>
                <div class="testimonial__position"><?php echo esc_html($position); ?></div>
            <?php endif; ?>
            
            <?php if ($company) : ?>
                <div class="testimonial__company">
                    <?php if ($company_url) : ?>
                        <a href="<?php echo esc_url($company_url); ?>" target="_blank">
                            <?php echo esc_html($company); ?>
                        </a>
                    <?php else : ?>
                        <?php echo esc_html($company); ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($industries) : ?>
                <div class="testimonial__industry">
                    <?php echo esc_html($industries[0]->name); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
```

### Shortcodes
```
[testimonials category="featured" limit="6" layout="carousel"]
[posts_grid post_type="testimonials" limit="9" columns="3"]
```

---

## 🎬 Hero Slides

### Purpose

Homepage hero slider content.

### Registration Details
```php
Post Type: hero_slide
Slug: Not publicly visible
Menu Position: 25
Supports: title, editor
Hierarchical: No
Has Archive: No
Show in REST: Yes
```

### ACF Fields

| Field Name | Type | Required | Description |
|------------|------|----------|-------------|
| `subtitle` | Text | No | Subtitle above/below title |
| `button_text` | Text | No | CTA button text |
| `button_url` | URL | No | CTA button link |
| `button_style` | Select | No | primary, secondary, outline |
| `image_desktop` | Image | Yes | Desktop image (1920x1080) |
| `image_mobile` | Image | No | Mobile image (800x600) |
| `overlay_opacity` | Number | No | Overlay darkness (0-100) |
| `text_color` | Select | No | light, dark |

### Usage

**Backend:**

1. Go to **Hero Slides → Add New**
2. Enter title (main heading)
3. Enter subtitle (optional)
4. Upload desktop image (1920x1080px)
5. Upload mobile image (optional)
6. Enter button text and URL
7. Set overlay opacity
8. Choose text color (light/dark)
9. Set menu order (for sorting)
10. Publish

**Frontend:**
```
[hero_slider_query limit="3" autoplay="true" delay="5000"]
```

---

## 📸 Carousel

### Purpose

Image carousels for logos, partners, gallery.

### Registration Details
```php
Post Type: carousel
Slug: Not publicly visible
Menu Position: 26
Supports: title, thumbnail
Hierarchical: No
Has Archive: No
Show in REST: Yes
```

### Taxonomies

#### Carousel Category (`carousel_category`)

- **Type:** Hierarchical
- **Purpose:** Group carousel items
- **Examples:** Clients, Partners, Awards, Gallery

### ACF Fields

| Field Name | Type | Required | Description |
|------------|------|----------|-------------|
| `link_url` | URL | No | Where image links to |
| `order` | Number | No | Display order |

### Usage

**Backend:**

1. Go to **Carousel → Add New**
2. Enter title (for reference)
3. Upload featured image
4. Add link URL (optional)
5. Set carousel category
6. Set order number
7. Publish

**Frontend:**
```
[carousel category="clients" limit="8" autoplay="true"]
```

---

## ❓ FAQ

### Purpose

Frequently Asked Questions.

### Registration Details
```php
Post Type: faq
Slug: Not publicly visible
Menu Position: 27
Supports: title, editor
Hierarchical: No
Has Archive: No
Show in REST: Yes
```

### Taxonomies

#### FAQ Category (`faq_category`)

- **Type:** Hierarchical
- **Purpose:** Group related FAQs
- **Examples:** General, Technical, Billing, Support

### ACF Fields

| Field Name | Type | Required | Description |
|------------|------|----------|-------------|
| `order` | Number | No | Display order |

### Usage

**Backend:**

1. Go to **FAQ → Add New**
2. Enter question as title
3. Enter answer in content editor
4. Set FAQ category
5. Set order number
6. Publish

**Frontend:**
```
[faq_accordion category="general"]
[faq_list category="technical" limit="10"]
```

---

## 🗺️ Google Maps

### Purpose

GDPR-compliant embedded Google Maps.

### Registration Details
```php
Post Type: google_map
Slug: Not publicly visible
Menu Position: 28
Supports: title
Hierarchical: No
Has Archive: No
Show in REST: Yes
```

### ACF Fields

| Field Name | Type | Required | Description |
|------------|------|----------|-------------|
| `address` | Text | Yes | Full address |
| `latitude` | Number | Yes | Latitude coordinate |
| `longitude` | Number | Longitude coordinate |
| `zoom_level` | Number | No | Zoom level (1-20, default 14) |
| `privacy_notice` | Textarea | Yes | GDPR privacy text |
| `marker_icon` | Image | No | Custom map marker |

### Usage

**Backend:**

1. Go to **Google Maps → Add New**
2. Enter location name
3. Enter full address
4. Enter coordinates (or auto-detect)
5. Set zoom level
6. Enter privacy notice
7. Upload custom marker (optional)
8. Publish
9. Note the Post ID

**Frontend:**
```
[google_map location_id="123" height="400px"]
```

---

## 💡 Best Practices

### Content Guidelines

**1. Use Descriptive Titles**

✅ Good: "Senior Full-Stack Developer"  
❌ Bad: "Job 1"

**2. Fill Required Fields**

Always complete required fields for best results.

**3. Optimize Images**

- Compress before upload
- Use appropriate dimensions
- Add alt text

**4. Use Categories Consistently**

Create taxonomy terms before adding posts.

**5. Set Display Order**

Use order fields for predictable sorting.

### Performance Tips

**1. Limit Archive Queries**
```php
// In functions.php
add_action('pre_get_posts', function($query) {
    if ($query->is_main_query() && !is_admin() && $query->is_post_type_archive('job')) {
        $query->set('posts_per_page', 12);
    }
});
```

**2. Use Pagination**
```php
the_posts_pagination(array(
    'mid_size' => 2,
    'prev_text' => '← Previous',
    'next_text' => 'Next →',
));
```

**3. Cache Heavy Queries**
```php
$jobs = wp_cache_get('featured_jobs');
if (false === $jobs) {
    $jobs = new WP_Query($args);
    wp_cache_set('featured_jobs', $jobs, '', 3600);
}
```

---

## 🎨 Creating Custom Templates

### Single Template

**File:** `cms/wp-content/themes/custom-theme/single-job.php`
```php
<?php
/**
 * Single Job Template
 */
get_header(); ?>

<main class="site-main">
    <?php while (have_posts()) : the_post(); ?>
        
        <article id="post-<?php the_ID(); ?>" <?php post_class('job-single'); ?>>
            <header class="job-header">
                <h1><?php the_title(); ?></h1>
                
                <div class="job-meta">
                    <?php
                    $employment_type = get_field('employment_type');
                    $locations = get_the_terms(get_the_ID(), 'job_location');
                    ?>
                    
                    <?php if ($employment_type) : ?>
                        <span><?php echo esc_html($employment_type); ?></span>
                    <?php endif; ?>
                    
                    <?php if ($locations) : ?>
                        <span><?php echo esc_html($locations[0]->name); ?></span>
                    <?php endif; ?>
                </div>
            </header>
            
            <div class="job-content">
                <?php the_content(); ?>
            </div>
            
            <footer class="job-footer">
                <a href="<?php echo get_post_type_archive_link('job'); ?>" class="button button--secondary">
                    ← Back to Jobs
                </a>
                <a href="#" class="button button--primary">
                    Apply Now
                </a>
            </footer>
        </article>
        
    <?php endwhile; ?>
</main>

<?php get_footer(); ?>
```

### Archive Template

**File:** `cms/wp-content/themes/custom-theme/archive-job.php`
```php
<?php
/**
 * Job Archive Template
 */
get_header(); ?>

<main class="site-main">
    <header class="archive-header">
        <h1>Career Opportunities</h1>
        <p>Join our team of talented professionals</p>
    </header>
    
    <?php if (have_posts()) : ?>
        
        <div class="jobs-grid">
            <?php while (have_posts()) : the_post(); ?>
                <?php get_template_part('template-parts/content', 'job'); ?>
            <?php endwhile; ?>
        </div>
        
        <?php the_posts_pagination(); ?>
        
    <?php else : ?>
        
        <p>No job openings at this time. Check back soon!</p>
        
    <?php endif; ?>
</main>

<?php get_footer(); ?>
```

### Template Part

**File:** `cms/wp-content/themes/custom-theme/template-parts/content-job.php`
```php
<?php
/**
 * Job Card Template Part
 */
$employment_type = get_field('employment_type');
$locations = get_the_terms(get_the_ID(), 'job_location');
$salary_min = get_field('salary_min');
$salary_max = get_field('salary_max');
$featured = get_field('featured');
?>

<article id="post-<?php the_ID(); ?>" <?php post_class('job-card'); ?>>
    <?php if ($featured) : ?>
        <span class="job-card__badge">Featured</span>
    <?php endif; ?>
    
    <h2 class="job-card__title">
        <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
    </h2>
    
    <div class="job-card__meta">
        <?php if ($employment_type) : ?>
            <span class="job-type"><?php echo esc_html($employment_type); ?></span>
        <?php endif; ?>
        
        <?php if ($locations) : ?>
            <span class="job-location">
                <i class="dashicons dashicons-location"></i>
                <?php echo esc_html($locations[0]->name); ?>
            </span>
        <?php endif; ?>
    </div>
    
    <div class="job-card__excerpt">
        <?php echo wp_trim_words(get_the_excerpt(), 20); ?>
    </div>
    
    <?php if ($salary_min && $salary_max) : ?>
        <div class="job-card__salary">
            €<?php echo number_format($salary_min, 0, ',', '.'); ?> - 
            €<?php echo number_format($salary_max, 0, ',', '.'); ?>
        </div>
    <?php endif; ?>
    
    <a href="<?php the_permalink(); ?>" class="job-card__link">
        View Details →
    </a>
</article>
```

---

## 🔗 Related Documentation

- [ACF Fields Reference](./ACF-FIELDS.md) - Detailed field documentation
- [Shortcodes](./SHORTCODES.md) - Using CPTs in shortcodes
- [AJAX Filters](./AJAX-FILTERS.md) - Filtering CPTs
- [Development Guide](./DEVELOPMENT.md) - Creating new CPTs

---

**Last Updated:** February 2026  
**Version:** 1.0.0