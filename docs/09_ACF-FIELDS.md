# ACF Fields Reference

Complete reference for all Advanced Custom Fields (ACF) field groups in the WordPress Agency Starter Kit.

---

## 📋 Table of Contents

- [Overview](#overview)
- [Job Details](#job-details)
- [Project Details](#project-details)
- [Team Member Details](#team-member-details)
- [Service Details](#service-details)
- [Testimonial Details](#testimonial-details)
- [Hero Slide Details](#hero-slide-details)
- [Carousel Details](#carousel-details)
- [FAQ Details](#faq-details)
- [Google Map Details](#google-map-details)
- [Field Types Reference](#field-types-reference)
- [Working with ACF](#working-with-acf)

---

## 🎯 Overview

All ACF field groups are registered programmatically in:
`cms/wp-content/mu-plugins/agency-core/inc/acf-fields.php`

### Benefits of Programmatic Registration

- Version controlled
- Portable across environments
- No database dependencies
- Faster performance
- Easier deployment

### Field Groups Summary

| Field Group | Post Type | Fields | Purpose |
|-------------|-----------|--------|---------|
| Job Details | job | 8 | Employment information |
| Project Details | project | 7 | Portfolio project data |
| Team Member Details | team | 8 | Staff profiles |
| Service Details | services | 5 | Service offerings |
| Testimonial Details | testimonials | 6 | Client reviews |
| Hero Slide Details | hero_slide | 8 | Homepage slider |
| Carousel Details | carousel | 2 | Image carousels |
| FAQ Details | faq | 1 | Question sorting |
| Google Map Details | google_map | 6 | Map locations |

**Total Fields: 51**

---

## 💼 Job Details

### Overview
```php
Field Group Key: group_job_details
Title: Job Details
Post Type: job
Position: Normal
Style: Default
```

### Location Rules
```php
'location' => array(
    array(
        array(
            'param' => 'post_type',
            'operator' => '==',
            'value' => 'job',
        ),
    ),
),
```

### Fields

#### 1. Employment Type
```php
Field Key: field_employment_type
Label: Employment Type
Name: employment_type
Type: select
Required: No
Choices:
  - full_time: Full-time
  - part_time: Part-time
  - contract: Contract
  - freelance: Freelance
  - internship: Internship
Default: full_time
Allow Null: No
Multiple: No
```

**Usage:**
```php
$employment_type = get_field('employment_type');
// Returns: 'full_time', 'part_time', etc.

// Display value
$employment_labels = array(
    'full_time' => 'Full-time',
    'part_time' => 'Part-time',
    'contract' => 'Contract',
    'freelance' => 'Freelance',
    'internship' => 'Internship',
);
echo $employment_labels[$employment_type];
```

#### 2. Remote Work
```php
Field Key: field_remote_work
Label: Remote Work Available
Name: remote_work
Type: true_false
Required: No
Message: This position offers remote work
UI: Yes (toggle)
Default: 0
```

**Usage:**
```php
$remote = get_field('remote_work');
// Returns: true (1) or false (0)

if ($remote) {
    echo '<span class="badge badge--remote">Remote OK</span>';
}
```

#### 3. Salary Minimum
```php
Field Key: field_salary_min
Label: Minimum Salary
Name: salary_min
Type: number
Required: No
Default: 0
Placeholder: e.g., 40000
Min: 0
Max: null
Step: 1000
Prepend: €
Append: per year
```

**Usage:**
```php
$salary_min = get_field('salary_min');
// Returns: 40000 (integer)

echo '€' . number_format($salary_min, 0, ',', '.');
// Output: €40.000
```

#### 4. Salary Maximum
```php
Field Key: field_salary_max
Label: Maximum Salary
Name: salary_max
Type: number
Required: No
Default: 0
Placeholder: e.g., 80000
Min: 0
Max: null
Step: 1000
Prepend: €
Append: per year
```

**Usage:**
```php
$salary_min = get_field('salary_min');
$salary_max = get_field('salary_max');

if ($salary_min && $salary_max) {
    echo '€' . number_format($salary_min, 0, ',', '.') . ' - €' . number_format($salary_max, 0, ',', '.');
}
// Output: €40.000 - €80.000
```

#### 5. Experience Years
```php
Field Key: field_experience_years
Label: Required Years of Experience
Name: experience_years
Type: number
Required: No
Default: 0
Min: 0
Max: 20
Step: 1
Append: years
```

**Usage:**
```php
$experience = get_field('experience_years');
// Returns: 3 (integer)

if ($experience > 0) {
    echo $experience . ' years of experience required';
}
```

#### 6. Application Deadline
```php
Field Key: field_application_deadline
Label: Application Deadline
Name: application_deadline
Type: date_picker
Required: No
Display Format: d/m/Y
Return Format: Y-m-d
First Day: 1 (Monday)
```

**Usage:**
```php
$deadline = get_field('application_deadline');
// Returns: '2026-03-15' (string)

if ($deadline) {
    $deadline_date = DateTime::createFromFormat('Y-m-d', $deadline);
    $today = new DateTime();
    
    if ($deadline_date > $today) {
        echo 'Apply by: ' . $deadline_date->format('F j, Y');
    } else {
        echo '<span class="expired">Deadline passed</span>';
    }
}
```

#### 7. Application URL
```php
Field Key: field_application_url
Label: External Application URL
Name: application_url
Type: url
Required: No
Placeholder: https://example.com/apply
```

**Usage:**
```php
$app_url = get_field('application_url');
// Returns: 'https://example.com/apply' (string)

if ($app_url) {
    echo '<a href="' . esc_url($app_url) . '">Apply on External Site</a>';
} else {
    echo '<a href="' . get_permalink() . '/apply">Apply Now</a>';
}
```

#### 8. Featured
```php
Field Key: field_job_featured
Label: Featured Job
Name: featured
Type: true_false
Required: No
Message: Mark this job as featured
UI: Yes
Default: 0
```

**Usage:**
```php
$featured = get_field('featured');
// Returns: true (1) or false (0)

$classes = array('job-card');
if ($featured) {
    $classes[] = 'job-card--featured';
}
echo '<article class="' . implode(' ', $classes) . '">';
```

---

## 🎨 Project Details

### Overview
```php
Field Group Key: group_project_details
Title: Project Details
Post Type: project
Position: Normal
Style: Default
```

### Fields

#### 1. Client Name
```php
Field Key: field_client_name
Label: Client Name
Name: client_name
Type: text
Required: No
Placeholder: Company or Individual
Max Length: 100
```

**Usage:**
```php
$client = get_field('client_name');
echo 'Client: ' . esc_html($client);
```

#### 2. Project Date
```php
Field Key: field_project_date
Label: Project Completion Date
Name: project_date
Type: date_picker
Required: No
Display Format: F Y
Return Format: Y-m-d
```

**Usage:**
```php
$date = get_field('project_date');
// Returns: '2025-06-15'

if ($date) {
    echo date('F Y', strtotime($date));
    // Output: June 2025
}
```

#### 3. Project URL
```php
Field Key: field_project_url
Label: Live Project URL
Name: project_url
Type: url
Required: No
Placeholder: https://example.com
```

**Usage:**
```php
$url = get_field('project_url');

if ($url) {
    echo '<a href="' . esc_url($url) . '" target="_blank" rel="noopener">
            View Live Site <span class="external-icon">↗</span>
          </a>';
}
```

#### 4. Project Type
```php
Field Key: field_project_type
Label: Project Type
Name: project_type
Type: text
Required: No
Placeholder: e.g., Website, Branding, Mobile App
```

**Usage:**
```php
$type = get_field('project_type');
echo '<span class="project-type">' . esc_html($type) . '</span>';
```

#### 5. Technologies Used
```php
Field Key: field_technologies
Label: Technologies Used
Name: technologies
Type: text
Required: No
Placeholder: WordPress, React, Node.js
```

**Usage:**
```php
$tech = get_field('technologies');

if ($tech) {
    $tech_array = array_map('trim', explode(',', $tech));
    echo '<div class="tech-stack">';
    foreach ($tech_array as $technology) {
        echo '<span class="tech-badge">' . esc_html($technology) . '</span>';
    }
    echo '</div>';
}
```

#### 6. Project Duration
```php
Field Key: field_project_duration
Label: Project Duration
Name: project_duration
Type: text
Required: No
Placeholder: e.g., 3 months, 6 weeks
```

**Usage:**
```php
$duration = get_field('project_duration');
echo 'Duration: ' . esc_html($duration);
```

#### 7. Gallery
```php
Field Key: field_project_gallery
Label: Project Gallery
Name: gallery
Type: gallery
Required: No
Return Format: array
Insert: append
Library: all
Min: 0
Max: 20
Min Width: 800px
Min Height: 600px
Preview Size: medium
```

**Usage:**
```php
$gallery = get_field('gallery');
// Returns: array of image arrays

if ($gallery) {
    echo '<div class="project-gallery">';
    foreach ($gallery as $image) {
        echo '<a href="' . esc_url($image['url']) . '" data-lightbox="gallery">';
        echo '<img src="' . esc_url($image['sizes']['medium']) . '" alt="' . esc_attr($image['alt']) . '">';
        echo '</a>';
    }
    echo '</div>';
}
```

---

## 👥 Team Member Details

### Overview
```php
Field Group Key: group_team_details
Title: Team Member Details
Post Type: team
Position: Normal
Style: Default
```

### Fields

#### 1. Job Title
```php
Field Key: field_job_title
Label: Job Title/Position
Name: job_title
Type: text
Required: Yes
Placeholder: e.g., Senior Developer
```

**Usage:**
```php
$job_title = get_field('job_title');
echo '<h3>' . esc_html($job_title) . '</h3>';
```

#### 2. Email
```php
Field Key: field_team_email
Label: Email Address
Name: email
Type: email
Required: No
Placeholder: name@company.com
```

**Usage:**
```php
$email = get_field('email');

if ($email) {
    echo '<a href="mailto:' . esc_attr($email) . '" class="email-link">
            <i class="dashicons dashicons-email"></i> ' . esc_html($email) . '
          </a>';
}
```

#### 3. Phone
```php
Field Key: field_team_phone
Label: Phone Number
Name: phone
Type: text
Required: No
Placeholder: +43 123 456 7890
```

**Usage:**
```php
$phone = get_field('phone');

if ($phone) {
    // Remove spaces for tel: link
    $phone_clean = str_replace(' ', '', $phone);
    echo '<a href="tel:' . esc_attr($phone_clean) . '">' . esc_html($phone) . '</a>';
}
```

#### 4. LinkedIn
```php
Field Key: field_linkedin
Label: LinkedIn Profile URL
Name: linkedin
Type: url
Required: No
Placeholder: https://linkedin.com/in/username
```

**Usage:**
```php
$linkedin = get_field('linkedin');

if ($linkedin) {
    echo '<a href="' . esc_url($linkedin) . '" target="_blank" rel="noopener" class="social-link">
            <i class="fab fa-linkedin"></i>
          </a>';
}
```

#### 5. Twitter
```php
Field Key: field_twitter
Label: Twitter Handle
Name: twitter
Type: text
Required: No
Placeholder: @username
Prepend: @
```

**Usage:**
```php
$twitter = get_field('twitter');

if ($twitter) {
    // Remove @ if present
    $handle = ltrim($twitter, '@');
    echo '<a href="https://twitter.com/' . esc_attr($handle) . '" target="_blank" rel="noopener">
            <i class="fab fa-twitter"></i> @' . esc_html($handle) . '
          </a>';
}
```

#### 6. GitHub
```php
Field Key: field_github
Label: GitHub Profile URL
Name: github
Type: url
Required: No
Placeholder: https://github.com/username
```

**Usage:**
```php
$github = get_field('github');

if ($github) {
    echo '<a href="' . esc_url($github) . '" target="_blank" rel="noopener">
            <i class="fab fa-github"></i>
          </a>';
}
```

#### 7. Skills
```php
Field Key: field_skills
Label: Skills
Name: skills
Type: text
Required: No
Placeholder: PHP, JavaScript, React, WordPress
Instructions: Comma-separated list of skills
```

**Usage:**
```php
$skills = get_field('skills');

if ($skills) {
    $skills_array = array_map('trim', explode(',', $skills));
    echo '<div class="skills-list">';
    foreach ($skills_array as $skill) {
        echo '<span class="skill-tag">' . esc_html($skill) . '</span>';
    }
    echo '</div>';
}
```

#### 8. Order
```php
Field Key: field_team_order
Label: Display Order
Name: order
Type: number
Required: No
Default: 0
Min: 0
Step: 1
Instructions: Lower numbers appear first
```

**Usage:**
```php
// In WP_Query
$args = array(
    'post_type' => 'team',
    'meta_key' => 'order',
    'orderby' => 'meta_value_num',
    'order' => 'ASC',
);
$team_query = new WP_Query($args);
```

---

## ⚙️ Service Details

### Overview
```php
Field Group Key: group_service_details
Title: Service Details
Post Type: services
Position: Normal
Style: Default
```

### Fields

#### 1. Price From
```php
Field Key: field_price_from
Label: Starting Price
Name: price_from
Type: number
Required: No
Default: 0
Min: 0
Step: 0.01
Prepend: €
```

**Usage:**
```php
$price = get_field('price_from');

if ($price) {
    echo 'From €' . number_format($price, 2, ',', '.');
}
```

#### 2. Duration
```php
Field Key: field_duration
Label: Typical Duration
Name: duration
Type: text
Required: No
Placeholder: e.g., 2-4 weeks
```

**Usage:**
```php
$duration = get_field('duration');
echo '<span class="duration"><i class="dashicons dashicons-clock"></i> ' . esc_html($duration) . '</span>';
```

#### 3. Icon
```php
Field Key: field_icon
Label: Icon Class
Name: icon
Type: text
Required: No
Placeholder: dashicons-star-filled or fas fa-star
Instructions: Enter Dashicons or Font Awesome class
```

**Usage:**
```php
$icon = get_field('icon');

if ($icon) {
    // Detect icon type
    if (strpos($icon, 'dashicons') !== false) {
        echo '<i class="' . esc_attr($icon) . '"></i>';
    } else {
        echo '<i class="' . esc_attr($icon) . '"></i>';
    }
}
```

#### 4. Features
```php
Field Key: field_features
Label: Features List
Name: features
Type: textarea
Required: No
Rows: 10
Placeholder: One feature per line
Instructions: List key features, one per line
```

**Usage:**
```php
$features = get_field('features');

if ($features) {
    $features_array = explode("\n", $features);
    echo '<ul class="features-list">';
    foreach ($features_array as $feature) {
        $feature = trim($feature);
        if (!empty($feature)) {
            echo '<li><i class="dashicons dashicons-yes"></i> ' . esc_html($feature) . '</li>';
        }
    }
    echo '</ul>';
}
```

#### 5. Featured
```php
Field Key: field_service_featured
Label: Featured Service
Name: featured
Type: true_false
Required: No
Message: Highlight this service
UI: Yes
Default: 0
```

**Usage:**
```php
$featured = get_field('featured');

if ($featured) {
    echo '<span class="badge badge--featured">Popular</span>';
}
```

---

## 💬 Testimonial Details

### Overview
```php
Field Group Key: group_testimonial_details
Title: Testimonial Details
Post Type: testimonials
Position: Normal
Style: Default
```

### Fields

#### 1. Client Position
```php
Field Key: field_client_position
Label: Client Position/Title
Name: client_position
Type: text
Required: No
Placeholder: e.g., CEO, Marketing Director
```

**Usage:**
```php
$position = get_field('client_position');
echo '<p class="client-position">' . esc_html($position) . '</p>';
```

#### 2. Company Name
```php
Field Key: field_company_name
Label: Company Name
Name: company_name
Type: text
Required: Yes
Placeholder: Company or Organization
```

**Usage:**
```php
$company = get_field('company_name');
echo '<p class="company-name">' . esc_html($company) . '</p>';
```

#### 3. Company URL
```php
Field Key: field_company_url
Label: Company Website
Name: company_url
Type: url
Required: No
Placeholder: https://example.com
```

**Usage:**
```php
$company = get_field('company_name');
$url = get_field('company_url');

if ($url) {
    echo '<a href="' . esc_url($url) . '" target="_blank" rel="noopener">' . esc_html($company) . '</a>';
} else {
    echo esc_html($company);
}
```

#### 4. Rating
```php
Field Key: field_rating
Label: Star Rating
Name: rating
Type: number
Required: No
Default: 5
Min: 1
Max: 5
Step: 1
```

**Usage:**
```php
$rating = get_field('rating');

if ($rating) {
    echo '<div class="star-rating">';
    for ($i = 1; $i <= 5; $i++) {
        $class = $i <= $rating ? 'star--filled' : 'star--empty';
        echo '<span class="star ' . $class . '">★</span>';
    }
    echo '</div>';
}
```

#### 5. Project Type
```php
Field Key: field_testimonial_project_type
Label: Project Type
Name: project_type
Type: text
Required: No
Placeholder: e.g., Website Redesign
```

**Usage:**
```php
$project = get_field('project_type');

if ($project) {
    echo '<p class="project-type">Project: ' . esc_html($project) . '</p>';
}
```

#### 6. Featured
```php
Field Key: field_testimonial_featured
Label: Featured Testimonial
Name: featured
Type: true_false
Required: No
Message: Show in featured testimonials
UI: Yes
Default: 0
```

**Usage:**
```php
// Query featured testimonials
$args = array(
    'post_type' => 'testimonials',
    'posts_per_page' => 6,
    'meta_query' => array(
        array(
            'key' => 'featured',
            'value' => '1',
            'compare' => '='
        )
    )
);
$featured_testimonials = new WP_Query($args);
```

---

## 🎬 Hero Slide Details

### Overview
```php
Field Group Key: group_hero_slide_details
Title: Hero Slide Details
Post Type: hero_slide
Position: Normal
Style: Default
```

### Fields

#### 1. Subtitle
```php
Field Key: field_subtitle
Label: Subtitle
Name: subtitle
Type: text
Required: No
Placeholder: Additional text above or below title
```

**Usage:**
```php
$subtitle = get_field('subtitle');

if ($subtitle) {
    echo '<p class="hero-subtitle">' . esc_html($subtitle) . '</p>';
}
```

#### 2. Button Text
```php
Field Key: field_button_text
Label: Button Text
Name: button_text
Type: text
Required: No
Placeholder: Learn More
Default: Learn More
```

**Usage:**
```php
$button_text = get_field('button_text');
$button_url = get_field('button_url');

if ($button_text && $button_url) {
    echo '<a href="' . esc_url($button_url) . '" class="hero-button">' . esc_html($button_text) . '</a>';
}
```

#### 3. Button URL
```php
Field Key: field_button_url
Label: Button URL
Name: button_url
Type: url
Required: No
Placeholder: https://example.com
```

#### 4. Button Style
```php
Field Key: field_button_style
Label: Button Style
Name: button_style
Type: select
Required: No
Choices:
  - primary: Primary
  - secondary: Secondary
  - outline: Outline
Default: primary
```

**Usage:**
```php
$button_text = get_field('button_text');
$button_url = get_field('button_url');
$button_style = get_field('button_style');

if ($button_text && $button_url) {
    echo '<a href="' . esc_url($button_url) . '" class="button button--' . esc_attr($button_style) . '">';
    echo esc_html($button_text);
    echo '</a>';
}
```

#### 5. Desktop Image
```php
Field Key: field_image_desktop
Label: Desktop Image
Name: image_desktop
Type: image
Required: Yes
Return Format: array
Preview Size: medium
Library: all
Min Width: 1920px
Min Height: 1080px
```

**Usage:**
```php
$desktop_image = get_field('image_desktop');
// Returns: array with url, width, height, alt, etc.

if ($desktop_image) {
    echo '<img src="' . esc_url($desktop_image['url']) . '" 
               alt="' . esc_attr($desktop_image['alt']) . '"
               width="' . esc_attr($desktop_image['width']) . '"
               height="' . esc_attr($desktop_image['height']) . '">';
}
```

#### 6. Mobile Image
```php
Field Key: field_image_mobile
Label: Mobile Image
Name: image_mobile
Type: image
Required: No
Return Format: array
Preview Size: medium
Instructions: Optional mobile-optimized image
```

**Usage:**
```php
$desktop_image = get_field('image_desktop');
$mobile_image = get_field('image_mobile');

echo '<picture>';
if ($mobile_image) {
    echo '<source media="(max-width: 768px)" srcset="' . esc_url($mobile_image['url']) . '">';
}
echo '<img src="' . esc_url($desktop_image['url']) . '" alt="' . esc_attr($desktop_image['alt']) . '">';
echo '</picture>';
```

#### 7. Overlay Opacity
```php
Field Key: field_overlay_opacity
Label: Overlay Opacity
Name: overlay_opacity
Type: number
Required: No
Default: 30
Min: 0
Max: 100
Step: 5
Append: %
Instructions: Dark overlay over image (0-100%)
```

**Usage:**
```php
$opacity = get_field('overlay_opacity');
// Returns: 30 (integer)

$opacity_decimal = $opacity / 100;
echo '<div class="hero-overlay" style="background: rgba(0, 0, 0, ' . $opacity_decimal . ');"></div>';
```

#### 8. Text Color
```php
Field Key: field_text_color
Label: Text Color
Name: text_color
Type: select
Required: No
Choices:
  - light: Light (for dark images)
  - dark: Dark (for light images)
Default: light
```

**Usage:**
```php
$text_color = get_field('text_color');

echo '<div class="hero-content hero-content--' . esc_attr($text_color) . '">';
// Content here
echo '</div>';
```

---

## 📸 Carousel Details

### Overview
```php
Field Group Key: group_carousel_details
Title: Carousel Details
Post Type: carousel
Position: Normal
Style: Default
```

### Fields

#### 1. Link URL
```php
Field Key: field_carousel_link
Label: Link URL
Name: link_url
Type: url
Required: No
Placeholder: https://example.com
Instructions: Where should this image link to?
```

**Usage:**
```php
$link = get_field('link_url');
$image = get_the_post_thumbnail_url(get_the_ID(), 'medium');

if ($link) {
    echo '<a href="' . esc_url($link) . '" target="_blank">';
    echo '<img src="' . esc_url($image) . '" alt="' . get_the_title() . '">';
    echo '</a>';
} else {
    echo '<img src="' . esc_url($image) . '" alt="' . get_the_title() . '">';
}
```

#### 2. Order
```php
Field Key: field_carousel_order
Label: Display Order
Name: order
Type: number
Required: No
Default: 0
Min: 0
Step: 1
```

**Usage:**
```php
$args = array(
    'post_type' => 'carousel',
    'posts_per_page' => -1,
    'meta_key' => 'order',
    'orderby' => 'meta_value_num',
    'order' => 'ASC',
    'tax_query' => array(
        array(
            'taxonomy' => 'carousel_category',
            'field' => 'slug',
            'terms' => 'clients',
        ),
    ),
);
```

---

## ❓ FAQ Details

### Overview
```php
Field Group Key: group_faq_details
Title: FAQ Details
Post Type: faq
Position: Normal
Style: Default
```

### Fields

#### 1. Order
```php
Field Key: field_faq_order
Label: Display Order
Name: order
Type: number
Required: No
Default: 0
Min: 0
Step: 1
Instructions: Lower numbers appear first
```

**Usage:**
```php
$args = array(
    'post_type' => 'faq',
    'posts_per_page' => -1,
    'meta_key' => 'order',
    'orderby' => 'meta_value_num',
    'order' => 'ASC',
);
```

---

## 🗺️ Google Map Details

### Overview
```php
Field Group Key: group_google_map_details
Title: Google Map Details
Post Type: google_map
Position: Normal
Style: Default
```

### Fields

#### 1. Address
```php
Field Key: field_address
Label: Full Address
Name: address
Type: text
Required: Yes
Placeholder: Street, City, Country
```

**Usage:**
```php
$address = get_field('address');
echo '<p class="map-address">' . esc_html($address) . '</p>';
```

#### 2. Latitude
```php
Field Key: field_latitude
Label: Latitude
Name: latitude
Type: number
Required: Yes
Placeholder: 48.2082
Step: 0.000001
```

**Usage:**
```php
$lat = get_field('latitude');
$lng = get_field('longitude');

// For Google Maps embed
$map_url = 'https://maps.google.com/maps?q=' . $lat . ',' . $lng . '&z=15&output=embed';
```

#### 3. Longitude
```php
Field Key: field_longitude
Label: Longitude
Name: longitude
Type: number
Required: Yes
Placeholder: 16.3738
Step: 0.000001
```

#### 4. Zoom Level
```php
Field Key: field_zoom_level
Label: Zoom Level
Name: zoom_level
Type: number
Required: No
Default: 14
Min: 1
Max: 20
Step: 1
```

**Usage:**
```php
$zoom = get_field('zoom_level');
// Use in Google Maps API
```

#### 5. Privacy Notice
```php
Field Key: field_privacy_notice
Label: GDPR Privacy Notice
Name: privacy_notice
Type: textarea
Required: Yes
Rows: 4
Default: By loading this map, you agree to Google's privacy policy and terms of service.
```

**Usage:**
```php
$privacy = get_field('privacy_notice');

echo '<div class="gdpr-notice">';
echo '<p>' . esc_html($privacy) . '</p>';
echo '<button class="load-map-button">Load Map</button>';
echo '</div>';
```

#### 6. Marker Icon
```php
Field Key: field_marker_icon
Label: Custom Marker Icon
Name: marker_icon
Type: image
Required: No
Return Format: url
Preview Size: thumbnail
```

**Usage:**
```php
$marker = get_field('marker_icon');

if ($marker) {
    // Use custom marker in Google Maps
    // marker: { icon: '<?php echo esc_js($marker); ?>' }
}
```

---

## 📚 Field Types Reference

### Common Field Types

#### Text
```php
$value = get_field('field_name');
// Returns: string or empty string
```

#### Textarea
```php
$value = get_field('field_name');
// Returns: string with newlines or empty string
```

#### Number
```php
$value = get_field('field_name');
// Returns: integer/float or empty string
```

#### Email
```php
$email = get_field('field_name');
// Returns: valid email string or empty string
```

#### URL
```php
$url = get_field('field_name');
// Returns: valid URL string or empty string
```

#### True/False
```php
$value = get_field('field_name');
// Returns: true (1) or false (0)
```

#### Select
```php
$value = get_field('field_name');
// Returns: chosen value (string) or empty string
```

#### Date Picker
```php
$date = get_field('field_name');
// Returns: date string in return format (e.g., 'Y-m-d')
```

#### Image
```php
// Return format: array
$image = get_field('field_name');
// Returns: array with keys: ID, url, alt, title, width, height, sizes

// Return format: url
$image_url = get_field('field_name');
// Returns: string URL

// Return format: id
$image_id = get_field('field_name');
// Returns: integer (attachment ID)
```

#### Gallery
```php
$gallery = get_field('field_name');
// Returns: array of image arrays

foreach ($gallery as $image) {
    echo '<img src="' . $image['url'] . '" alt="' . $image['alt'] . '">';
}
```

---

## 🔧 Working with ACF

### Retrieving Fields
```php
// Current post
$value = get_field('field_name');

// Specific post
$value = get_field('field_name', $post_id);

// With default fallback
$value = get_field('field_name') ?: 'Default Value';

// Check if field has value
if (get_field('field_name')) {
    // Has value
}

// Get field object (for debugging)
$field_object = get_field_object('field_name');
print_r($field_object);
```

### Updating Fields
```php
// Update field
update_field('field_name', 'new value', $post_id);

// Update multiple fields
$fields = array(
    'field_name_1' => 'value 1',
    'field_name_2' => 'value 2',
);

foreach ($fields as $field => $value) {
    update_field($field, $value, $post_id);
}
```

### Conditional Logic

Fields can have conditional logic in their registration:
```php
'conditional_logic' => array(
    array(
        array(
            'field' => 'field_employment_type',
            'operator' => '==',
            'value' => 'full_time',
        ),
    ),
),
```

### Best Practices

**1. Always Escape Output**
```php
// Text
echo esc_html($value);

// HTML attributes
echo esc_attr($value);

// URLs
echo esc_url($value);

// JavaScript
echo esc_js($value);
```

**2. Check Before Using**
```php
// Good
$value = get_field('field_name');
if ($value) {
    echo $value;
}

// Bad
echo get_field('field_name'); // Could output nothing
```

**3. Use Type Casting**
```php
// For numbers
$salary = (int) get_field('salary_min');

// For arrays
$gallery = (array) get_field('gallery');
```

**4. Handle Empty States**
```php
$image = get_field('image');
if ($image) {
    echo '<img src="' . $image['url'] . '">';
} else {
    echo '<img src="/path/to/placeholder.jpg">';
}
```

### Debugging
```php
// Display all field values
$post_id = get_the_ID();
$fields = get_fields($post_id);
echo '<pre>';
print_r($fields);
echo '</pre>';

// Check if field exists
if (get_field_object('field_name')) {
    echo 'Field exists';
}

// Get field key
$field = get_field_object('field_name');
echo 'Field Key: ' . $field['key'];
```

### Performance
```php
// Cache heavy queries
$team_members = wp_cache_get('team_members_with_fields');

if (false === $team_members) {
    $query = new WP_Query(array(
        'post_type' => 'team',
        'posts_per_page' => -1,
    ));
    
    $team_members = array();
    while ($query->have_posts()) {
        $query->the_post();
        $team_members[] = array(
            'id' => get_the_ID(),
            'title' => get_the_title(),
            'job_title' => get_field('job_title'),
            'email' => get_field('email'),
        );
    }
    
    wp_cache_set('team_members_with_fields', $team_members, '', 3600);
}
```

---

## 🔗 Related Documentation

- [Custom Post Types](./CUSTOM-POST-TYPES.md) - CPT documentation
- [Shortcodes](./SHORTCODES.md) - Using ACF fields in shortcodes
- [Development Guide](./DEVELOPMENT.md) - Creating new field groups
- [Usage Guide](./USAGE.md) - Content manager perspective

---

**Last Updated:** February 2026  
**Version:** 1.0.0